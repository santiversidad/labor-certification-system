import { expect, request as playwrightRequest, test, type APIRequestContext, type Page } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { TOTP } from 'otpauth';

const apiOrigin = 'http://e2e-api:8280';
const browserOrigin = 'http://e2e-web:3200';
const contexts = new Map<string, APIRequestContext>();
const headerOwners = new WeakMap<object, string>();
const patched = new WeakSet<APIRequestContext>();
const activeBrowserHandle = new WeakMap<Page, string>();
const recoveries = new Map<string, string[]>();
const fixtureCounters = new Map<string, number>();
const failures = new WeakMap<Page, Array<{ path: string; status: number; code?: string }>>();
let serial = 0;

test.afterEach(async ({ page }, info) => {
  if (info.status !== info.expectedStatus) console.log('Security response metadata:', JSON.stringify(failures.get(page) ?? []));
});
function observe(page: Page) {
  if (failures.has(page)) return;
  failures.set(page, []);
  page.on('response', async (response) => {
    if (response.status() < 400 || !response.url().includes('/api/v1/')) return;
    const path = new URL(response.url()).pathname.replace(/[a-f0-9]{32,}/g, '[capability]').replace(/\/\d+(?=\/|$)/g, '/[id]');
    let code: string | undefined;
    try { const body = await response.json(); if (typeof body.code === 'string' && /^[A-Z_]{3,80}$/.test(body.code)) code = body.code; } catch { /* No body or secrets recorded. */ }
    failures.get(page)?.push({ path, status: response.status(), code });
  });
}

export function sessionHeaders(handle: string) {
  const headers = { Accept: 'application/json' };
  headerOwners.set(headers, handle);
  return headers;
}
async function csrfHeaders(context: APIRequestContext) {
  const cookie = (await context.storageState()).cookies.find((value) => value.name === 'XSRF-TOKEN');
  return { Accept: 'application/json', Origin: browserOrigin, 'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.value) : '' };
}
function patch(context: APIRequestContext) {
  if (patched.has(context)) return;
  patched.add(context);
  for (const method of ['get', 'post', 'put', 'patch', 'delete'] as const) {
    const original = context[method].bind(context);
    context[method] = async (url, options) => {
      const handle = options?.headers ? headerOwners.get(options.headers) : undefined;
      if (!handle) return original(url, options);
      const owned = contexts.get(handle);
      if (!owned) throw new Error('Missing synthetic test session');
      return owned[method](url, { ...options, headers: await csrfHeaders(owned) });
    };
  }
}
function register(context: APIRequestContext) {
  const handle = 'test-session-' + ++serial; // Runner-only handle, never a server bearer or persisted browser credential.
  contexts.set(handle, context);
  return handle;
}
function recoveryFor(username: string) {
  const normalized = username.toLowerCase();
  const known = recoveries.get(normalized);
  if (known?.length) return known.shift()!;
  const file = test.info().file;
  const offset = file.endsWith('autoservice.spec.ts') ? 0 : file.endsWith('identity-lifecycle.spec.ts') ? 100 : file.endsWith('payment-workflow.spec.ts') ? 200 : file.endsWith('dependency-position-selection.spec.ts') ? 450 : 300;
  const key = normalized + ':' + offset;
  const index = offset + (fixtureCounters.get(key) ?? 0); fixtureCounters.set(key, index - offset + 1);
  return createHmac('sha256', 'e2e-only-fixture-seed').update(normalized + ':' + index).digest('hex');
}
export async function apiSession(caller: APIRequestContext, username: string, password: string) {
  patch(caller);
  const context = await playwrightRequest.newContext({ extraHTTPHeaders: { Origin: browserOrigin, Accept: 'application/json' } });
  await context.get(apiOrigin + '/sanctum/csrf-cookie');
  const response = await context.post(apiOrigin + '/api/v1/auth/login', { headers: await csrfHeaders(context), data: { username, password } });
  expect(response.status()).toBe(200);
  const result = (await response.json()).data;
  expect(result).not.toHaveProperty('token');
  if (result.status === 'MFA_REQUIRED') {
    const proof = await context.post(apiOrigin + '/api/v1/auth/mfa/verify', { headers: await csrfHeaders(context), data: { code: recoveryFor(username) } });
    expect(proof.status()).toBe(200); result.user = (await proof.json()).data.user;
  }
  return { ...result, token: register(context) };
}
export async function captureBrowserSession(page: Page) {
  patch(page.request);
  const state = await page.context().storageState();
  const cookies = state.cookies.filter((cookie) => cookie.domain === 'e2e-web').map((cookie) => ({ ...cookie, domain: 'e2e-api' }));
  const context = await playwrightRequest.newContext({ storageState: { cookies, origins: [] }, extraHTTPHeaders: { Origin: browserOrigin, Accept: 'application/json' } });
  const handle = activeBrowserHandle.get(page);
  if (handle) {
    await contexts.get(handle)?.dispose(); contexts.set(handle, context); return handle;
  }
  const created = register(context); activeBrowserHandle.set(page, created); return created;
}
export async function completeMfa(page: Page, username: string) {
  await expect(page).toHaveURL(/\/seguridad\/mfa/);
  await expect(page.getByRole('heading', { name: /^(Configurar autenticación en dos pasos|Verificar código)$/ })).toBeVisible();
  if (await page.getByRole('heading', { name: 'Configurar autenticación en dos pasos', exact: true }).count()) {
    const value = await pendingMfaSecret(page);
    await page.getByLabel('Código de 6 dígitos', { exact: true }).fill(new TOTP({ secret: value }).generate());
    const response = page.waitForResponse((r) => r.url().endsWith('/auth/mfa/confirm') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Activar autenticación en dos pasos', exact: true }).click();
    const result = await response; expect(result.status()).toBe(200);
    recoveries.set(username.toLowerCase(), (await result.json()).data.recovery_codes);
    await page.getByRole('button', { name: 'Ya los guardé, continuar' }).click();
  } else {
    await page.getByRole('button', { name: 'Usar código de recuperación' }).click();
    await page.getByLabel('Código de recuperación').fill(recoveryFor(username));
    await page.getByRole('button', { name: 'Verificar y continuar' }).click();
  }
  await expect(page).toHaveURL(/\/admin\/dashboard/);
  await captureBrowserSession(page);
}
// Read synthetic test-account material only in memory. Boolean assertions avoid
// printing its value on failure; screenshots must mask both QR and manual key.
export async function pendingMfaSecret(page: Page) {
  await expect(page.getByTestId('mfa-qr')).toBeVisible();
  await page.getByText('No puedo escanear el código', { exact: true }).click();
  const secret = await page.getByTestId('mfa-secret').inputValue();
  expect(Boolean(secret)).toBe(true);
  await page.getByText('No puedo escanear el código', { exact: true }).click();
  return secret;
}
export async function browserSession(page: Page, username: string, password: string) {
  observe(page);
  activeBrowserHandle.delete(page);
  await page.goto('/login');
  await page.getByLabel('Usuario', { exact: true }).fill(username);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  const response = page.waitForResponse((r) => r.url().endsWith('/auth/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  const result = await response; expect(result.status()).toBe(200);
  const data = (await result.json()).data;
  expect(data).not.toHaveProperty('token');
  if (data.status === 'MFA_REQUIRED' || data.status === 'MFA_ENROLLMENT_REQUIRED') await completeMfa(page, username);
  return { ...data, token: await captureBrowserSession(page) };
}
