import { expect, test, type Page } from '@playwright/test';
import { readFile } from 'node:fs/promises';

const api = 'http://e2e-api:8080/api/v1';
const employeePassword = 'MiClaveNueva2026';
let simpleEmployee = '';
let functionsEmployee = '';

async function login(page: Page, documento: string, password: string) {
  await page.goto('/login');
  await page.getByLabel('Cédula', { exact: true }).fill(documento);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  const loginResponse = page.waitForResponse((response) => response.url().endsWith('/api/v1/auth/login') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  const response = await loginResponse;
  expect(response.status()).toBe(200);
  return (await response.json()).data;
}

async function logout(page: Page) {
  await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
  await expect(page).toHaveURL(/\/login/);
  expect(await page.evaluate(() => localStorage.getItem('clv_session'))).toBeNull();
}

async function createEmployee(page: Page, suffix: string) {
  const documento = `92${Date.now()}${suffix.length}`;
  await page.goto('/admin/funcionarios/nuevo');
  await page.getByLabel('Número de cédula').fill(documento);
  await page.getByLabel('Nombres', { exact: true }).fill('Persona');
  await page.getByLabel('Apellidos', { exact: true }).fill(`Ficticia ${suffix}`);
  await page.getByLabel('Dependencia', { exact: true }).fill('Talento Humano E2E');
  await page.locator('select[name="cargo_id"]').selectOption({ label: 'Profesional E2E · E2E/01' });
  await page.getByLabel('Fecha de vinculación').fill('2024-01-15');
  await expect(page.getByLabel('Ficha del Manual de Funciones')).toHaveValue(/\d+/);
  const responsePromise = page.waitForResponse((response) => response.url().endsWith('/api/v1/funcionarios') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Guardar funcionario' }).click();
  expect((await responsePromise).status()).toBe(201);
  await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
  return documento;
}

async function completeFirstLogin(page: Page, documento: string) {
  await login(page, documento, documento);
  await expect(page).toHaveURL(/\/cambiar-contrasena/);
  await page.goto('/app/inicio');
  await expect(page).toHaveURL(/\/cambiar-contrasena/);
  await page.getByLabel('Contraseña temporal', { exact: true }).fill(documento);
  await page.getByLabel('Nueva contraseña', { exact: true }).fill(employeePassword);
  await page.getByLabel('Confirmar nueva contraseña', { exact: true }).fill(employeePassword);
  await page.getByRole('button', { name: 'Guardar y continuar' }).click();
  await expect(page).toHaveURL(/\/app\/inicio/);
}

async function assertPdfDownload(page: Page) {
  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Descargar PDF' }).click();
  const file = await (await downloadPromise).path();
  expect(file).not.toBeNull();
  expect((await readFile(file!)).subarray(0, 5).toString()).toBe('%PDF-');
}

async function showRealConfirmation(page: Page, payload: unknown) {
  await page.evaluate((result) => {
    history.pushState({ ...(history.state ?? {}), usr: result }, '', '/app/solicitudes/confirmacion');
    window.dispatchEvent(new PopStateEvent('popstate'));
  }, payload);
  await expect(page.getByRole('heading', { name: 'Certificación generada correctamente' })).toBeVisible();
}

async function postCertificate(page: Page, type: 'sencillo' | 'funciones') {
  const session = await page.evaluate(() => JSON.parse(localStorage.getItem('clv_session')!));
  return page.request.post(`${api}/solicitudes`, {
    headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json', 'Content-Type': 'application/json' },
    data: { tipo_certificado: type },
  });
}

test.describe.serial('Fase 2: tipos canónicos sin interfaz salarial', () => {
  test('administración revalida identidad, conserva 36 permisos y navega sin módulo salarial', async ({ page }) => {
    const session = await login(page, 'E2E-ADMIN', 'E2EAdminClave2026');
    expect(session.user.permisos).toHaveLength(36);
    const me = await page.request.get(`${api}/auth/me`, { headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' } });
    expect(me.status()).toBe(200);
    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await expect(page.getByText(/rangos salariales|salario/i)).toHaveCount(0);

    for (const [path, heading] of [
      ['/admin/funcionarios', 'Funcionarios'], ['/admin/manual-funciones', 'Manual de Funciones'],
      ['/admin/configuracion', 'Pago de certificaciones'], ['/admin/certificaciones', 'Certificados'],
      ['/admin/reportes', 'Reportes'],
    ] as const) {
      await page.goto(path);
      await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
      await expect(page.getByText(/rangos salariales|con salario|sin salario/i)).toHaveCount(0);
    }
    await logout(page);
  });

  test('admin crea funcionario y el autoservicio sencillo genera y descarga PDF real', async ({ page }) => {
    await login(page, 'E2E-ADMIN', 'E2EAdminClave2026');
    simpleEmployee = await createEmployee(page, 'Sencillo');
    await logout(page);
    await completeFirstLogin(page, simpleEmployee);
    await page.goto('/app/solicitudes/nueva?modalidad=sencillo');
    await expect(page.getByRole('radio', { name: /Certificado laboral sencillo/i })).toBeChecked();
    const response = await postCertificate(page, 'sencillo');
    expect(response.status()).toBe(201);
    const body = await response.json();
    expect(body.data.solicitud.tipo_certificado).toBe('sencillo');
    expect(body.data.certificado.snapshot_datos).not.toHaveProperty('manual_funciones');
    expect(JSON.stringify(body)).not.toMatch(/salario|requiere_salario/i);
    await showRealConfirmation(page, body.data);
    await expect(page.getByText('Certificado laboral sencillo')).toBeVisible();
    await assertPdfDownload(page);
    await logout(page);
  });

  test('certificado con funciones usa la ficha completa y conserva evidencia en snapshot y PDF', async ({ page }) => {
    await login(page, 'E2E-ADMIN', 'E2EAdminClave2026');
    functionsEmployee = await createEmployee(page, 'Funciones');
    await logout(page);
    await completeFirstLogin(page, functionsEmployee);
    await page.goto('/app/solicitudes/nueva?modalidad=funciones');
    await expect(page.getByRole('radio', { name: /Certificado laboral con funciones/i })).toBeChecked();
    const response = await postCertificate(page, 'funciones');
    expect(response.status()).toBe(201);
    const body = await response.json();
    expect(body.data.solicitud.tipo_certificado).toBe('funciones');
    expect(body.data.certificado.snapshot_datos.manual_funciones.source_id).toBe('E2E-001');
    expect(body.data.certificado.snapshot_datos.manual_funciones.funciones[0].descripcion).toBe('Atender pruebas sintéticas.');
    expect(JSON.stringify(body)).not.toMatch(/salario|requiere_salario/i);
    expect(body.data.descarga_url).toMatch(/^\/api\/v1\/mi-certificacion\/descargar\/[a-f0-9]{64}$/);
    const session = await page.evaluate(() => JSON.parse(localStorage.getItem('clv_session')!));
    const pdf = await page.request.get(`http://e2e-api:8080${body.data.descarga_url}`, { headers: { Authorization: `Bearer ${session.token}` } });
    expect(pdf.status()).toBe(200);
    expect((await pdf.body()).subarray(0, 5).toString()).toBe('%PDF-');
    await logout(page);
  });

  test('la cuota bloquea el mismo tipo y permite el tipo contrario', async ({ page }) => {
    const session = await login(page, functionsEmployee, employeePassword);
    const headers = { Authorization: `Bearer ${session.token}`, Accept: 'application/json', 'Content-Type': 'application/json' };
    const duplicate = await page.request.post(`${api}/solicitudes`, { headers, data: { tipo_certificado: 'funciones' } });
    expect(duplicate.status()).toBe(409);
    expect((await duplicate.json()).code).toBe('MONTHLY_CERTIFICATE_LIMIT');
    const opposite = await page.request.post(`${api}/solicitudes`, { headers, data: { tipo_certificado: 'sencillo' } });
    expect(opposite.status()).toBe(201);
    await logout(page);
  });

  test('logout funcionario y nuevo login admin no reutilizan identidad ni disponibilidad', async ({ page }) => {
    const employeeSession = await login(page, functionsEmployee, employeePassword);
    expect((await page.request.get(`${api}/auth/me`, { headers: { Authorization: `Bearer ${employeeSession.token}` } })).status()).toBe(200);
    await expect(page).toHaveURL(/\/app\/inicio/);
    await logout(page);
    expect((await page.request.get(`${api}/auth/me`, { headers: { Authorization: `Bearer ${employeeSession.token}` } })).status()).toBe(401);
    const adminSession = await login(page, 'E2E-ADMIN', 'E2EAdminClave2026');
    expect(adminSession.user.roles).toContain('admin');
    expect(adminSession.user.permisos).toHaveLength(36);
    await expect(page).toHaveURL(/\/admin\/dashboard/);
  });

  test('ficha incompleta permite sencillo y rechaza únicamente funciones', async ({ page }) => {
    const session = await login(page, 'E2E-INCOMPLETE', 'E2EIncomplete2026');
    const headers = { Authorization: `Bearer ${session.token}`, Accept: 'application/json', 'Content-Type': 'application/json' };
    const simple = await page.request.post(`${api}/solicitudes`, { headers, data: { tipo_certificado: 'sencillo' } });
    expect(simple.status()).toBe(201);
    await page.goto('/app/solicitudes/nueva?modalidad=funciones');
    await expect(page.getByRole('radio', { name: /Certificado laboral con funciones/i })).toBeChecked();
    const response = await postCertificate(page, 'funciones');
    expect(response.status()).toBe(409);
    expect((await response.json()).code).toBe('MANUAL_FICHA_INCOMPLETA');
    await expect(page.getByText(/salario/i)).toHaveCount(0);
  });

  test('las pantallas críticas conservan navegación y contenido en 360 y 768 px', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 800 });
    await login(page, 'E2E-INCOMPLETE', 'E2EIncomplete2026');
    await expect(page.getByRole('heading', { name: 'Certificaciones laborales' })).toBeVisible();
    await expect(page.getByText('Certificado laboral sencillo')).toBeVisible();
    await page.goto('/app/solicitudes/nueva?modalidad=funciones');
    await expect(page.getByRole('radio', { name: /Certificado laboral con funciones/i })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
    await logout(page);

    await page.setViewportSize({ width: 768, height: 900 });
    await login(page, 'E2E-ADMIN', 'E2EAdminClave2026');
    for (const path of ['/admin/funcionarios', '/admin/funcionarios/nuevo', '/admin/manual-funciones', '/admin/certificaciones']) {
      await page.goto(path);
      await expect(page.locator('main')).toBeVisible();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
    }
  });
});
