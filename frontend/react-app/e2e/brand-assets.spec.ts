import { expect, test, type Page } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { mkdir } from 'node:fs/promises';

const output = 'evidence/brand-assets';
let recoveryIndex = 400; // Separate range from the existing synthetic regression fixtures.

async function capture(page: Page, name: string) {
  await expect(page.locator('.animate-pulse')).toHaveCount(0);
  const secrets = [page.getByTestId('mfa-qr'), page.getByTestId('mfa-secret'), page.getByTestId('recovery-codes'), page.getByLabel('Código de 6 dígitos', { exact: true }), page.getByLabel('Código de autenticación', { exact: true }), page.getByLabel('Código de recuperación', { exact: true })];
  for (const input of await page.locator('input[type="password"]').all()) {
    if (await input.inputValue()) secrets.push(input);
  }
  await page.screenshot({ path: `${output}/${name}.png`, fullPage: true, animations: 'disabled',
    mask: secrets });
}

async function login(page: Page, username: string, password: string) {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.getByLabel('Usuario', { exact: true }).fill(username);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  const response = page.waitForResponse((r) => r.url().endsWith('/auth/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  const result = await response;
  expect(result.status()).toBe(200);
  const data = (await result.json()).data;
  expect(data).not.toHaveProperty('token');
  if (data.status === 'MFA_REQUIRED') {
    await expect(page).toHaveURL(/\/seguridad\/mfa/);
    await page.getByRole('button', { name: 'Usar código de recuperación' }).click();
    const code = createHmac('sha256', 'e2e-only-fixture-seed').update(username.toLowerCase() + ':' + recoveryIndex++).digest('hex');
    await page.getByLabel('Código de recuperación', { exact: true }).fill(code);
    await page.getByRole('button', { name: 'Verificar y continuar' }).click();
  }
  await expect(page).toHaveURL(/\/(admin\/dashboard|app\/inicio)$/);
}

async function checkLayout(page: Page, title: string, institutional = true) {
  await expect(page.getByRole('heading', { name: title, exact: true })).toHaveCount(1);
  await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  if (institutional) {
    await expect(page.locator('.institutional-page-header')).toHaveCount(1);
    expect(await page.locator('.institutional-page-header').evaluate((node) => getComputedStyle(node).backgroundImage)).toContain('/assets/headers/villavicencio-panorama.webp');
  }
  for (const img of await page.locator('img[src^="/assets/brand/"]').all()) {
    if (await img.isVisible()) await expect.poll(() => img.evaluate((node: HTMLImageElement) => node.complete && node.naturalWidth > 0)).toBe(true);
  }
}

test('assets institucionales: login, navegación y headers en 360, 768 y 1440 px', async ({ page }) => {
  test.setTimeout(180_000);
  await mkdir(output, { recursive: true });
  const assetResponses: string[] = [];
  page.on('response', (response) => {
    if (response.url().includes('/assets/') && /\.(webp|png)$/.test(response.url())) {
      expect([200, 304]).toContain(response.status());
      assetResponses.push(new URL(response.url()).pathname);
    }
  });
  for (const width of [1440, 768, 360]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/login');
    await checkLayout(page, 'Iniciar sesión', false);
    await expect(page.getByRole('button', { name: 'Ingresar', exact: true })).toBeEnabled();
    const photo = page.locator('img[src="/assets/auth/login-villavicencio.webp"]');
    if (width === 1440) {
      await expect(photo).toBeVisible();
      await expect.poll(() => photo.evaluate((node: HTMLImageElement) => node.complete && node.naturalWidth === 1600 && getComputedStyle(node).objectFit === 'cover')).toBe(true);
    } else await expect(photo).toBeHidden();
    const gov = page.getByRole('link', { name: /Portal Único/ });
    await expect(gov).toHaveAttribute('rel', 'noopener noreferrer');
    await capture(page, width === 1440 ? '01-login-desktop' : width === 360 ? '02-login-mobile' : '07-login-tablet');
  }
  // The form must remain usable when the decorative photo cannot load.
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.route('**/assets/auth/login-villavicencio.webp', (route) => route.abort());
  await page.goto('/login');
  await expect(page.getByLabel('Usuario', { exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Ingresar', exact: true })).toBeVisible();
  await page.unroute('**/assets/auth/login-villavicencio.webp');

  await login(page, 'E2E-ADMIN', 'E2EAdminClave2026');
  const routes = [
    ['/admin/funcionarios', 'Funcionarios', '03-header-funcionarios'],
    ['/admin/certificaciones', 'Certificados', '04-header-certificaciones'],
    ['/admin/manual-funciones', 'Manual de Funciones', '09-header-manual'],
    ['/admin/usuarios-internos', 'Usuarios internos', '10-header-usuarios'],
    ['/admin/configuracion', 'Pago de certificaciones', '11-header-configuracion'],
  ];
  for (const width of [1440, 768, 360]) {
    await page.setViewportSize({ width, height: 900 });
    for (const [path, title, name] of routes) {
      await page.goto(path);
      await checkLayout(page, title);
      if (width === 1440) await capture(page, name);
      if (path === '/admin/funcionarios' && width !== 1440) await capture(page, width === 360 ? '06-header-mobile' : '08-header-tablet');
    }
    // Check the actual menu interaction at each responsive breakpoint.
    if (width < 1024) await page.getByRole('button', { name: 'Abrir menú', exact: true }).click();
    await page.getByRole('link', { name: 'Funcionarios', exact: true }).filter({ visible: true }).click();
    await expect(page).toHaveURL(/\/admin\/funcionarios$/);
  }

  await login(page, 'E2E-SECRETARIA-2', 'E2ESecretaria2026');
  for (const width of [1440, 768, 360]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/admin/pagos');
    await checkLayout(page, 'Validación de pagos');
    if (width === 1440) await capture(page, '05-header-secretaria');
  }
  await login(page, 'E2E-INCOMPLETE', 'E2EIncomplete2026');
  for (const width of [1440, 768, 360]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/app/solicitudes');
    await checkLayout(page, 'Mis solicitudes');
    if (width === 1440) await capture(page, '12-header-mis-solicitudes');
  }
  for (const path of ['/assets/auth/login-villavicencio.webp', '/assets/headers/villavicencio-panorama.webp', '/assets/brand/escudo-villavicencio.webp', '/assets/brand/govco.png']) expect(assetResponses).toContain(path);
});
