import { expect, test, type APIRequestContext, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const api = 'http://e2e-api:8280/api/v1';
const evidence = 'evidence/identity-phase28';
const headers = (token: string) => ({ Authorization: 'Bearer ' + token, Accept: 'application/json' });
const personalPassword = 'PersonalIdentityClave2026';
let employeeUsername = '';
let secretaryUsername = '';

async function apiLogin(request: APIRequestContext, username: string, password: string) {
  const response = await request.post(api + '/auth/login', { data: { username, password } });
  expect(response.status()).toBe(200);
  return (await response.json()).data as { token: string; user: { id: number; tipo_cuenta: string; username: string } };
}
async function login(page: Page, username: string, password: string) {
  await page.goto('/login');
  await page.getByLabel('Usuario', { exact: true }).fill(username);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  const response = page.waitForResponse((r) => r.url().endsWith('/auth/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  const result = await response;
  expect(result.status()).toBe(200);
  return (await result.json()).data as { token: string; user: { id: number } };
}
async function capture(page: Page, name: string) {
  // Wait for actual content before capturing, including auth and lazy-route boundaries.
  await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
  await expect(page.getByText('Validando sesión...', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Cargando pantalla...', { exact: true })).toHaveCount(0);
  await expect(page.locator('.animate-pulse')).toHaveCount(0);
  await page.screenshot({ animations: 'disabled', path: evidence + '/' + name + '.png', fullPage: await page.getByRole('dialog').count() === 0, mask: [page.getByTestId('temporary-password'), page.locator('input[type="password"]')] });
}
async function employee(request: APIRequestContext, admin: string) {
  const username = '98' + Date.now();
  const cargos = await (await request.get(api + '/cargos?per_page=100', { headers: headers(admin) })).json();
  const cargo = cargos.data.find((c: { codigo: string }) => c.codigo === 'E2E');
  const created = await request.post(api + '/funcionarios', { headers: headers(admin), data: {
    numero_documento: username, tipo_documento: 'CC', nombres: 'María', apellidos: 'Test',
    estado: 'activo', fecha_ingreso: '2024-01-15', cargo_id: cargo.id,
    tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa',
  } });
  expect(created.status()).toBe(201);
  const session = await apiLogin(request, username, username);
  expect((await request.put(api + '/auth/change-password', { headers: headers(session.token), data: {
    current_password: username, password: personalPassword, password_confirmation: personalPassword,
  } })).status()).toBe(200);
  return username;
}
async function changeTemporary(page: Page, temporary: string) {
  await expect(page).toHaveURL(/\/cambiar-contrasena/);
  await page.getByLabel('Contraseña temporal', { exact: true }).fill(temporary);
  await page.getByLabel('Nueva contraseña', { exact: true }).fill(personalPassword);
  await page.getByLabel('Confirmar nueva contraseña', { exact: true }).fill(personalPassword);
  await page.getByRole('button', { name: 'Guardar y continuar' }).click();
  await expect(page).toHaveURL(/\/admin\/dashboard/);
}

test.use({ trace: 'off', timezoneId: 'America/Bogota' });

test.describe.serial('Fase 2.8 identidades independientes', () => {
  // Never persist temporary credentials in trace/network artifacts.
  test.use({ viewport: { width: 1440, height: 900 } });
  test.beforeAll(async () => { await mkdir(evidence, { recursive: true }); });

  test('A: administrador individual ingresa por username y mantiene contexto', async ({ page }) => {
    await page.goto('/login'); await capture(page, '01-login-usuario');
    for (const width of [360, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
      if (width === 360) await capture(page, '17-mobile-login');
    }
    await login(page, 'E2E-ADMIN', 'E2EAdminClave2026');
    await expect(page).toHaveURL(/\/admin\/dashboard/); await capture(page, '06-admin-dashboard');
    await page.goto('/admin/usuarios-internos'); await expect(page.getByText('e2e-admin', { exact: true })).toBeVisible();
    await capture(page, '02-usuarios-internos');
    await page.goto('/app/inicio'); await expect(page).toHaveURL(/\/admin\/dashboard/);
  });

  test('B: Secretaría individual conserva pagos y rechaza administración de usuarios', async ({ page }) => {
    const session = await login(page, 'E2E-SECRETARIA', 'E2ESecretaria2026');
    await expect(page).toHaveURL(/\/admin\/dashboard/); await capture(page, '07-secretaria-dashboard');
    await page.goto('/admin/pagos'); await expect(page.getByRole('heading', { name: 'Validación de pagos' })).toBeVisible();
    expect((await page.request.get(api + '/admin/usuarios-internos', { headers: headers(session.token) })).status()).toBe(403);
    await page.goto('/admin/usuarios-internos'); await expect(page).toHaveURL(/\/admin\/dashboard/);
  });

  test('C: funcionario conserva login por cédula y su autoservicio', async ({ page }) => {
    const admin = await apiLogin(page.request, 'e2e-admin', 'E2EAdminClave2026');
    employeeUsername = await employee(page.request, admin.token);
    await login(page, employeeUsername, personalPassword);
    await expect(page).toHaveURL(/\/app\/inicio/); await capture(page, '13-persona-cuenta-funcionario');
    await page.goto('/admin/usuarios-internos'); await expect(page).toHaveURL(/\/app\/inicio/);
  });

  test('D: una persona con dos cuentas tiene credenciales, tokens y cupos separados', async ({ page, browser }) => {
    const admin = await apiLogin(page.request, 'e2e-admin', 'E2EAdminClave2026');
    const username = 'maria.test.' + Date.now();
    const response = await page.request.post(api + '/admin/usuarios-internos', { headers: headers(admin.token),
      data: { name: 'María Test', username, role: 'admin' } });
    expect(response.status()).toBe(201);
    const temporary = (await response.json()).data.temporary_password as string;
    const internal = await login(page, username, temporary);
    await changeTemporary(page, temporary);
    await capture(page, '14-persona-cuenta-backoffice');
    const context = await browser.newContext(); const personal = await context.newPage();
    try {
      const employeeSession = await login(personal, employeeUsername, personalPassword);
      await expect(personal).toHaveURL(/\/app\/inicio/);
      expect(employeeSession.user.id).not.toBe(internal.user.id);
      expect(employeeSession.token).not.toBe(internal.token);
      expect((await page.request.get(api + '/mi-certificacion/disponibilidad', { headers: headers(internal.token) })).status()).toBe(403);
      expect((await personal.request.patch(api + '/configuracion/certificaciones', { headers: headers(employeeSession.token), data: { requiere_pago_certificado: false } })).status()).toBe(403);
      expect((await page.request.patch(api + '/configuracion/certificaciones', { headers: headers(internal.token), data: { requiere_pago_certificado: false } })).status()).toBe(200);
      const certificate = await personal.request.post(api + '/solicitudes', { headers: headers(employeeSession.token), data: { tipo_certificado: 'sencillo' } });
      expect(certificate.status()).toBe(201); expect((await certificate.json()).data.resultado).toBe('generada');
      expect((await page.request.post(api + '/solicitudes', { headers: headers(internal.token), data: { tipo_certificado: 'sencillo' } })).status()).toBe(403);
    } finally { await context.close(); }
  });

  test('E: administrador crea Secretaría, muestra temporal una vez y obliga cambio', async ({ page, browser }) => {
    await login(page, 'e2e-admin', 'E2EAdminClave2026');
    await page.goto('/admin/usuarios-internos');
    await page.getByRole('button', { name: 'Crear usuario interno' }).click();
    await capture(page, '03-crear-backoffice');
    secretaryUsername = 'secretaria.test.' + Date.now();
    await page.getByLabel('Nombre descriptivo').fill('Secretaría Ficticia Fase 28');
    await page.getByLabel('Usuario institucional individual').fill(secretaryUsername);
    await page.getByRole('button', { name: 'Crear cuenta' }).click();
    const secretField = page.getByLabel('Contraseña temporal generada');
    await expect(secretField).toBeVisible(); const temporary = await secretField.inputValue();
    await capture(page, '04-password-temporal');
    for (const width of [360, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
      await page.getByRole('button', { name: 'Cerrar y descartar' }).focus();
      await page.keyboard.press('Tab');
      await expect(page.getByRole('button', { name: 'Cerrar modal' })).toBeFocused();
      if (width === 360) await capture(page, '18-mobile-password-temporal');
    }
    expect(await page.evaluate((secret) => JSON.stringify(localStorage).includes(secret) || JSON.stringify(sessionStorage).includes(secret), temporary)).toBe(false);
    const context = await browser.newContext(); const secretary = await context.newPage();
    try {
      const session = await login(secretary, secretaryUsername, temporary);
      await expect(secretary).toHaveURL(/\/cambiar-contrasena/);
      await capture(secretary, '05-cambio-password-obligatorio');
      expect((await secretary.request.get(api + '/secretaria/pagos', { headers: headers(session.token) })).status()).toBe(403);
      await changeTemporary(secretary, temporary);
      await secretary.goto('/admin/pagos'); await expect(secretary.getByRole('heading', { name: 'Validación de pagos' })).toBeVisible();
    } finally { await context.close(); }
    await page.getByRole('button', { name: 'Cerrar y descartar' }).click();
    await expect(secretField).toHaveCount(0);
    for (const width of [360, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await expect(page.getByRole('heading', { name: 'Usuarios internos', exact: true })).toBeVisible();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
      if (width === 360) await capture(page, '15-mobile-usuarios');
      if (width === 768) await capture(page, '22-tablet-usuarios');
      await page.getByRole('button', { name: 'Crear usuario interno' }).click();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
      if (width === 360) await capture(page, '19-mobile-crear');
      await page.getByRole('button', { name: 'Cancelar', exact: true }).click();
    }
  });

  test('F: desactivar corta sesión existente; reactivar y reset no la reviven', async ({ page, browser }) => {
    const context = await browser.newContext(); const secretary = await context.newPage();
    try {
      const oldSession = await login(secretary, secretaryUsername, personalPassword);
      await login(page, 'e2e-admin', 'E2EAdminClave2026');
      await page.goto('/admin/usuarios-internos');
      await page.getByLabel('Buscar por nombre o usuario').fill(secretaryUsername);
      const row = page.getByRole('row').filter({ hasText: secretaryUsername });
      await expect(row).toBeVisible();
      await row.getByRole('button', { name: 'Desactivar acceso' }).click();
      await page.getByLabel('Motivo de desactivación').fill('Fin de cargo ficticio E2E');
      await capture(page, '08-desactivar-usuario');
      for (const width of [360, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        if (width === 360) await capture(page, '20-mobile-desactivar');
      }
      await page.getByRole('button', { name: 'Confirmar cambio' }).click();
      await expect(row.getByText('Inactivo', { exact: true })).toBeVisible();
      expect((await secretary.request.get(api + '/auth/me', { headers: headers(oldSession.token) })).status()).toBe(401);
      await secretary.reload(); await expect(secretary).toHaveURL(/\/login/);
      await secretary.getByLabel('Usuario', { exact: true }).fill(secretaryUsername);
      await secretary.getByLabel('Contraseña', { exact: true }).fill(personalPassword);
      await secretary.getByRole('button', { name: 'Ingresar', exact: true }).click();
      await expect(secretary.getByRole('alert')).toContainText('inactiva'); await capture(secretary, '09-usuario-desactivado');
      await row.getByRole('button', { name: 'Reactivar acceso' }).click(); await capture(page, '10-reactivar');
      await page.getByRole('button', { name: 'Confirmar cambio' }).click();
      await expect(page.getByLabel('Contraseña temporal generada')).toBeVisible();
      await page.getByRole('button', { name: 'Cerrar y descartar' }).click();
      expect((await secretary.request.get(api + '/auth/me', { headers: headers(oldSession.token) })).status()).toBe(401);
      await row.getByRole('button', { name: 'Restablecer contraseña' }).click(); await capture(page, '11-reset-password');
      await page.getByRole('button', { name: 'Confirmar cambio' }).click();
      const temporary = await page.getByLabel('Contraseña temporal generada').inputValue();
      await login(secretary, secretaryUsername, temporary); await changeTemporary(secretary, temporary);
      await page.getByRole('button', { name: 'Cerrar y descartar' }).click();
      await row.getByRole('button', { name: 'Cambiar rol' }).click();
      await page.getByLabel('Rol de acceso').selectOption('admin');
      await capture(page, '16-cambio-rol');
      for (const width of [360, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        if (width === 360) await capture(page, '21-mobile-cambio-rol');
      }
      await page.getByRole('button', { name: 'Confirmar cambio' }).click();
      await expect(row.getByText('Administrador', { exact: true })).toBeVisible();
      await secretary.reload(); await expect(secretary).toHaveURL(/\/login/);
    } finally { await context.close(); }
  });

  test('G: último administrador y autodesactivación se protegen en servidor y UI', async ({ page }) => {
    const session = await login(page, 'e2e-admin', 'E2EAdminClave2026');
    const listing = await (await page.request.get(api + '/admin/usuarios-internos?role=admin', { headers: headers(session.token) })).json();
    const admins = listing.data as Array<{ id: number }>;
    expect(admins.length).toBeGreaterThan(1);
    expect((await page.request.post(api + '/admin/usuarios-internos/' + session.user.id + '/desactivar', {
      headers: headers(session.token), data: { motivo: 'Self test' },
    })).status()).toBe(409);
    for (const user of admins.filter((user) => user.id !== session.user.id)) {
      expect((await page.request.post(api + '/admin/usuarios-internos/' + user.id + '/cambiar-rol', {
        headers: headers(session.token), data: { role: 'secretario' },
      })).status()).toBe(200);
    }
    await page.goto('/admin/usuarios-internos');
    await page.getByLabel('Buscar por nombre o usuario').fill('e2e-admin');
    const row = page.getByRole('row').filter({ hasText: 'e2e-admin' });
    await row.getByRole('button', { name: 'Cambiar rol' }).click();
    await page.getByLabel('Rol de acceso').selectOption('secretario');
    await page.getByRole('button', { name: 'Confirmar cambio' }).click();
    await expect(page.getByRole('alert')).toContainText('al menos un administrador activo');
    await capture(page, '12-ultimo-admin-warning');
  });
});
