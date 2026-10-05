import { expect, request as playwrightRequest, test, type APIRequestContext, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { TOTP } from 'otpauth';
import { apiSession, browserSession, captureBrowserSession, pendingMfaSecret, sessionHeaders } from './security-session';

const api = 'http://e2e-api:8280/api/v1';
const origin = 'http://e2e-web:3200';
const evidence = 'evidence/security-phase29';
const password = 'Security personal phrase 2026';
let admin = ''; let target = ''; let targetId = 0; let secret = ''; let codes: string[] = [];
let employee = ''; let employeeId = 0; let requestId = 0;
let targetSession = ''; let caller: APIRequestContext;

async function capture(page: Page, file: string) {
  await expect(page.getByText('Validando sesión...', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Cargando pantalla...', { exact: true })).toHaveCount(0);
  await page.screenshot({ path: `${evidence}/${file}.png`, animations: 'disabled', fullPage: true,
    mask: [page.getByTestId('mfa-qr'), page.getByTestId('mfa-secret'), page.getByTestId('recovery-codes'), page.locator('input[type="password"]'), page.getByLabel('Código de 6 dígitos', { exact: true }), page.getByLabel('Código de autenticación', { exact: true }), page.getByLabel('Código de recuperación', { exact: true })] });
}
async function begin(page: Page, username: string, pass = password) {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.getByLabel('Usuario', { exact: true }).fill(username);
  await page.getByLabel('Contraseña', { exact: true }).fill(pass);
  const response = page.waitForResponse((r) => r.url().endsWith('/auth/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  const result = await response; expect(result.status()).toBe(200);
  expect((await result.json()).data).not.toHaveProperty('token');
}
async function recover(page: Page, code: string) {
  await expect(page.getByRole('heading', { name: 'Verificar código', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Usar código de recuperación' }).click();
  await page.getByLabel('Código de recuperación', { exact: true }).fill(code);
  const response = page.waitForResponse((r) => r.url().endsWith('/mfa/verify') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Verificar y continuar' }).click();
  return response;
}
async function newEmployee(suffix: string) {
  const username = 'S29' + Date.now() + suffix;
  const cargos = await (await caller.get(api + '/cargos?per_page=100', { headers: sessionHeaders(admin) })).json();
  const cargo = cargos.data.find((item: { codigo: string }) => item.codigo === 'E2E');
  const created = await caller.post(api + '/funcionarios', { headers: sessionHeaders(admin), data: {
    numero_documento: username, tipo_documento: 'CC', nombres: 'Persona seguridad', apellidos: 'Sintética ' + suffix,
    estado: 'activo', fecha_ingreso: '2024-01-15', cargo_id: cargo.id,
    tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa',
  } });
  expect(created.status()).toBe(201); const data = (await created.json()).data;
  const session = await apiSession(caller, username, username);
  expect((await caller.put(api + '/auth/change-password', { headers: sessionHeaders(session.token), data: {
    current_password: username, password, password_confirmation: password,
  } })).status()).toBe(200);
  return { username, id: data.id, session: session.token };
}

test.describe.serial('Fase 2.9 seguridad de navegador A–L', () => {
  test.use({ viewport: { width: 1440, height: 900 } });
  test.beforeAll(async () => {
    await mkdir(evidence, { recursive: true }); caller = await playwrightRequest.newContext();
    admin = (await apiSession(caller, 'e2e-admin', 'E2EAdminClave2026')).token;
  });
  test.afterAll(async () => { await caller.dispose(); });

  test('A: funcionario usa cookie HttpOnly y autoservicio sin storage de credenciales', async ({ page }) => {
    const account = await newEmployee('A'); employee = account.username; employeeId = account.id;
    await page.goto('/login'); await expect(page.getByLabel('Usuario', { exact: true })).toBeVisible(); await capture(page, '01-login');
    await browserSession(page, employee, password); await expect(page).toHaveURL(/\/app\/inicio/);
    await expect(page.getByText('Disponibilidad mensual de certificados')).toBeVisible();
    const cookies = await page.context().cookies(); expect(cookies.some((cookie) => cookie.httpOnly && cookie.sameSite === 'Lax')).toBe(true);
    expect(await page.evaluate(() => ({ local: localStorage.length, session: sessionStorage.length }))).toEqual({ local: 0, session: 0 });
  });
  test('B: administrador pendiente enrolla TOTP y conserva secreto cifrado y códigos solo en memoria', async ({ page }) => {
    target = 'security.admin.' + Date.now();
    const created = await caller.post(api + '/admin/usuarios-internos', { headers: sessionHeaders(admin), data: { username: target, name: 'Administrador sintético seguridad', role: 'admin' } });
    expect(created.status()).toBe(201); const data = (await created.json()).data; targetId = data.user.id;
    const pending = await apiSession(caller, target, data.temporary_password);
    expect((await caller.put(api + '/auth/change-password', { headers: sessionHeaders(pending.token), data: { current_password: data.temporary_password, password, password_confirmation: password } })).status()).toBe(200);
    await begin(page, target); await expect(page).toHaveURL(/\/seguridad\/mfa/);
    secret = await pendingMfaSecret(page);
    await page.reload();
    expect((await pendingMfaSecret(page)) === secret).toBe(true);
    await page.getByRole('button', { name: 'Cancelar y cerrar sesión' }).click();
    await expect(page).toHaveURL(/\/login/);
    await begin(page, target);
    const next = await pendingMfaSecret(page);
    expect(next === secret).toBe(false); secret = next;
    await page.getByLabel('Código de 6 dígitos', { exact: true }).fill('000000');
    const invalid = page.waitForResponse((r) => r.url().endsWith('/mfa/confirm') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Activar autenticación en dos pasos', exact: true }).click();
    expect((await invalid).status()).toBe(422);
    await expect(page.getByRole('alert')).toContainText('El código no es válido o ya cambió.');
    expect((await page.request.get(api + '/admin/usuarios-internos', { headers: sessionHeaders(await captureBrowserSession(page)) })).status()).toBe(401);
    await capture(page, '02-mfa-setup-redacted'); await page.setViewportSize({ width: 360, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true); await capture(page, '09-mobile-mfa');
    await page.setViewportSize({ width: 768, height: 1024 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true); await capture(page, '11-tablet-mfa-redacted');
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.getByLabel('Código de 6 dígitos', { exact: true }).fill(new TOTP({ secret }).generate());
    const response = page.waitForResponse((r) => r.url().endsWith('/mfa/confirm') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Activar autenticación en dos pasos', exact: true }).click();
    const proof = await response; expect(proof.status()).toBe(200); codes = (await proof.json()).data.recovery_codes; expect(codes).toHaveLength(8);
    await expect(page.getByTestId('recovery-codes')).toBeVisible(); await capture(page, '04-recovery-codes-redacted');
    await page.getByRole('button', { name: 'Ya los guardé, continuar' }).click(); await expect(page).toHaveURL(/\/admin\/dashboard/);
    await page.goto('/admin/seguridad'); await expect(page.getByRole('heading', { name: 'Seguridad de la cuenta' })).toBeVisible(); await capture(page, '07-security-admin');
  });
  test('C: Secretaría completa segundo factor y accede al contexto de pagos', async ({ page }) => {
    await browserSession(page, 'e2e-secretaria', 'E2ESecretaria2026');
    await page.goto('/admin/pagos'); await expect(page.getByRole('heading', { name: 'Validación de pagos' })).toBeVisible();
    await page.goto('/admin/seguridad'); await expect(page.getByRole('heading', { name: 'Seguridad de la cuenta' })).toBeVisible(); await capture(page, '08-security-secretaria');
  });
  test('D: bearer viejo y storage legado no autentican ni sobreviven bootstrap', async ({ page }) => {
    await page.goto('/login'); await page.evaluate(() => localStorage.setItem('clv_session', JSON.stringify({ token: 'synthetic-obsolete-token', user: { id: 1 } })));
    await page.reload(); await expect(page.getByLabel('Usuario', { exact: true })).toBeVisible();
    expect(await page.evaluate(() => localStorage.getItem('clv_session'))).toBeNull();
    expect((await page.request.get(api + '/auth/me', { headers: { Origin: origin, Authorization: 'Bearer synthetic-obsolete-token', Accept: 'application/json' } })).status()).toBe(401);
    await page.goto('/admin/usuarios-internos'); await expect(page).toHaveURL(/\/login/);
  });
  test('E: mutación con cookie y sin CSRF se bloquea incluso en same-origin', async ({ page }) => {
    await browserSession(page, employee, password);
    const status = await page.evaluate(async () => (await fetch('/api/v1/solicitudes', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ tipo_certificado: 'sencillo' }) })).status);
    expect(status).toBe(419);
  });
  test('F: desactivación invalida cookie y muestra expiración en desktop y móvil', async ({ page }) => {
    await browserSession(page, employee, password);
    expect((await caller.put(`${api}/funcionarios/${employeeId}`, { headers: sessionHeaders(admin), data: { estado: 'suspendido' } })).status()).toBe(200);
    await page.reload(); await expect(page).toHaveURL(/\/login\?sesion=expirada/); await expect(page.getByRole('status').filter({ hasText: 'Sesión expirada' })).toBeVisible();
    await capture(page, '05-session-expired'); await page.setViewportSize({ width: 360, height: 900 });
    await capture(page, '10-mobile-session-expired'); expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  });
  test('G: cambio de rol revoca la sesión completa previa', async ({ page }) => {
    await begin(page, target); expect((await recover(page, codes.shift()!)).status()).toBe(200); await expect(page).toHaveURL(/\/admin\/dashboard/);
    targetSession = await captureBrowserSession(page);
    expect((await caller.post(`${api}/admin/usuarios-internos/${targetId}/cambiar-rol`, { headers: sessionHeaders(admin), data: { role: 'secretario' } })).status()).toBe(200);
    expect((await page.request.get(api + '/auth/me', { headers: sessionHeaders(targetSession) })).status()).toBe(401);
    await page.reload(); await expect(page).toHaveURL(/\/login/);
  });
  test('H: recovery code se consume una sola vez y no se reutiliza', async ({ page }) => {
    const code = codes.shift()!; await begin(page, target); await capture(page, '03-mfa-verify');
    expect((await recover(page, code)).status()).toBe(200); await expect(page).toHaveURL(/\/admin\/dashboard/);
    await begin(page, target); expect((await recover(page, code)).status()).toBe(422);
    await expect(page.getByRole('alert').filter({ hasText: 'El código de recuperación no es válido o ya fue utilizado.' }).first()).toBeVisible();
    await page.getByLabel('Código de recuperación', { exact: true }).fill(codes.shift()!);
    let proof = page.waitForResponse((r) => r.url().endsWith('/mfa/verify') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Verificar y continuar' }).click();
    let result = await proof;
    // Enrollment's new invalid-code case counts against the existing 5/min
    // actor limit. Respect the server's retry window; never loosen the limit.
    if (result.status() === 429) {
      const retrySeconds = Number(result.headers()['retry-after']);
      expect(retrySeconds > 0 && retrySeconds <= 60).toBe(true);
      await page.waitForTimeout(retrySeconds * 1000 + 250);
      proof = page.waitForResponse((r) => r.url().endsWith('/mfa/verify') && r.request().method() === 'POST');
      await page.getByRole('button', { name: 'Verificar y continuar' }).click();
      result = await proof;
    }
    expect(result.status()).toBe(200); await expect(page).toHaveURL(/\/admin\/dashboard/);
    targetSession = await captureBrowserSession(page);
  });
  test('I: reset MFA por otro administrador invalida cookies y exige nuevo enrollment', async ({ page }) => {
    expect((await caller.post(`${api}/admin/usuarios-internos/${targetId}/reset-mfa`, { headers: sessionHeaders(admin) })).status()).toBe(200);
    // The old session is checked using its isolated cookie context, never a bearer.
    expect((await caller.get(api + '/auth/me', { headers: sessionHeaders(targetSession) })).status()).toBe(401);
    await begin(page, target); await expect(page.getByRole('heading', { name: 'Configurar autenticación en dos pasos', exact: true })).toBeVisible();
    expect((await pendingMfaSecret(page)) === secret).toBe(false);
  });
  test('J: login limita intentos temporalmente y muestra mensaje controlado', async ({ page }) => {
    await page.goto('/login'); await page.getByLabel('Usuario', { exact: true }).fill('unknown.security.' + Date.now());
    await page.getByLabel('Contraseña', { exact: true }).fill('Wrong synthetic phrase');
    for (let attempt = 0; attempt < 6; attempt++) {
      const response = page.waitForResponse((r) => r.url().endsWith('/auth/login') && r.request().method() === 'POST');
      await page.getByRole('button', { name: 'Ingresar', exact: true }).click(); const result = await response;
      expect(result.status()).toBe(attempt < 5 ? 422 : 429); if (attempt === 5) expect(result.headers()['retry-after']).toBeTruthy();
      await expect(page.getByRole('button', { name: 'Ingresar', exact: true })).toBeEnabled();
    }
    await expect(page.getByRole('alert')).toContainText(/demasiados intentos/i); await capture(page, '06-too-many-attempts');
  });
  test('K: funcionario ajeno no consulta solicitud, certificado ni comprobante', async () => {
    const own = await newEmployee('K'); const other = await newEmployee('X');
    expect((await caller.patch(api + '/configuracion/certificaciones', { headers: sessionHeaders(admin), data: { requiere_pago_certificado: true } })).status()).toBe(200);
    const created = await caller.post(api + '/solicitudes', { headers: sessionHeaders(own.session), data: { tipo_certificado: 'sencillo' } }); expect(created.status()).toBe(201);
    requestId = (await created.json()).data.solicitud.id;
    const uploaded = await caller.post(`${api}/solicitudes/${requestId}/pago/comprobantes`, { headers: sessionHeaders(own.session), multipart: { archivo: { name: 'synthetic.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\nSynthetic receipt\n') } } }); expect(uploaded.status()).toBe(201);
    const detail = await (await caller.get(`${api}/solicitudes/${requestId}`, { headers: sessionHeaders(own.session) })).json();
    const file = detail.data.pago_soporte.comprobantes[0].archivo_url;
    expect((await caller.get(`${api}/solicitudes/${requestId}`, { headers: sessionHeaders(other.session) })).status()).toBe(403);
    expect((await caller.get('http://e2e-api:8280' + file, { headers: sessionHeaders(other.session) })).status()).toBe(403);
    expect((await caller.get(`${api}/solicitudes/${requestId}/certificado`, { headers: sessionHeaders(other.session) })).status()).toBe(403);
    expect((await caller.patch(api + '/configuracion/certificaciones', { headers: sessionHeaders(admin), data: { requiere_pago_certificado: false } })).status()).toBe(200);
  });
  test('L: build real bajo CSP de producción conserva SPA y rechaza archivos privados', async ({ page }) => {
    const violations: string[] = []; page.on('console', (message) => { if (/violates.*Content Security Policy|Refused to/.test(message.text())) violations.push(message.text()); });
    const response = await page.goto('http://e2e-production/login'); expect(response?.status()).toBe(200);
    const headers = response!.headers(); const policy = headers['content-security-policy'];
    expect(policy).toContain("script-src 'self'"); expect(policy).not.toContain('unsafe-eval');
    expect(policy.match(/(?:^|;)\s*script-src\s+([^;]+)/)?.[1]).not.toContain('unsafe-inline');
    expect(headers['x-content-type-options']).toBe('nosniff'); expect(headers['x-frame-options']).toBe('DENY'); expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
    expect(headers['strict-transport-security']).toBeUndefined(); await expect(page.getByLabel('Usuario', { exact: true })).toBeVisible();
    expect(violations).toEqual([]); expect((await page.request.get('http://e2e-production/.env')).status()).toBe(403);
  });
});
