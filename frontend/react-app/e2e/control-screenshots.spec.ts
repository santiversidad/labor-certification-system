import { expect, test, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const output = 'evidence/certificate-types-phase2';

async function capture(page: Page, name: string) {
  await expect(page.locator('.animate-pulse')).toHaveCount(0);
  await page.screenshot({ path: `${output}/${name}.png`, fullPage: true });
}

async function login(page: Page, documento: string, password: string) {
  await page.goto('/login');
  await page.getByLabel('Cédula', { exact: true }).fill(documento);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
}

test('evidencia visual real de Fase 2', async ({ page }) => {
  await mkdir(output, { recursive: true });
  await page.setViewportSize({ width: 1440, height: 900 });

  await page.goto('/login');
  await expect(page.getByRole('heading', { name: 'Iniciar sesión' })).toBeVisible();
  await capture(page, '01-login');

  const documento = `93${Date.now()}`;
  await login(page, 'E2E-ADMIN', 'E2EAdminClave2026');
  await expect(page).toHaveURL(/\/admin\/dashboard/);
  await page.goto('/admin/funcionarios/nuevo');
  await page.getByLabel('Número de cédula').fill(documento);
  await page.getByLabel('Nombres', { exact: true }).fill('Evidencia');
  await page.getByLabel('Apellidos', { exact: true }).fill('Visual Fase Dos');
  await page.getByLabel('Dependencia', { exact: true }).fill('Talento Humano E2E');
  await page.locator('select[name="cargo_id"]').selectOption({ label: 'Profesional E2E · E2E/01' });
  await page.getByLabel('Fecha de vinculación').fill('2024-01-15');
  await expect(page.getByLabel('Ficha del Manual de Funciones')).toHaveValue(/\d+/);
  await page.getByRole('button', { name: 'Guardar funcionario' }).click();
  await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
  const employeeDetailUrl = page.url();
  await page.getByRole('button', { name: 'Cerrar sesión' }).click();

  await login(page, documento, documento);
  await expect(page).toHaveURL(/\/cambiar-contrasena/);
  await page.getByLabel('Contraseña temporal', { exact: true }).fill(documento);
  await page.getByLabel('Nueva contraseña', { exact: true }).fill('MiClaveNueva2026');
  await page.getByLabel('Confirmar nueva contraseña', { exact: true }).fill('MiClaveNueva2026');
  await page.getByRole('button', { name: 'Guardar y continuar' }).click();
  await expect(page).toHaveURL(/\/app\/inicio/);
  await capture(page, '02-dashboard-funcionario');

  await page.goto('/app/solicitudes/nueva?modalidad=sencillo');
  await expect(page.getByRole('radio', { name: /Certificado laboral sencillo/i })).toBeChecked();
  await capture(page, '03-solicitud-sencillo');
  await page.goto('/app/solicitudes/nueva?modalidad=funciones');
  await expect(page.getByRole('radio', { name: /Certificado laboral con funciones/i })).toBeChecked();
  await capture(page, '04-solicitud-funciones');

  await page.goto('/app/solicitudes/nueva?modalidad=sencillo');
  await expect(page.getByRole('radio', { name: /Certificado laboral sencillo/i })).toBeChecked();
  const session = await page.evaluate(() => JSON.parse(localStorage.getItem('clv_session')!));
  const request = await page.request.post('http://e2e-api:8080/api/v1/solicitudes', {
    headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' },
    data: { tipo_certificado: 'sencillo' },
  });
  expect(request.status()).toBe(201);
  const result = (await request.json()).data;
  await page.evaluate((payload) => {
    history.pushState({ ...(history.state ?? {}), usr: payload }, '', '/app/solicitudes/confirmacion');
    window.dispatchEvent(new PopStateEvent('popstate'));
  }, result);
  await expect(page.getByRole('heading', { name: 'Certificación generada correctamente' })).toBeVisible();
  await capture(page, '05-confirmacion');
  await page.getByRole('button', { name: 'Cerrar sesión' }).click();

  await login(page, 'E2E-ADMIN', 'E2EAdminClave2026');
  await expect(page).toHaveURL(/\/admin\/dashboard/);
  await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
  await capture(page, '06-dashboard-admin');
  await page.goto('/admin/funcionarios');
  await expect(page.getByRole('heading', { name: 'Funcionarios', exact: true })).toBeVisible();
  await capture(page, '07-funcionarios');
  await page.goto('/admin/funcionarios/nuevo');
  await expect(page.getByRole('heading', { name: 'Crear funcionario' })).toBeVisible();
  await capture(page, '08-alta-funcionario');
  await page.goto(employeeDetailUrl);
  await expect(page.getByText('Información básica')).toBeVisible();
  await capture(page, '09-detalle-funcionario');
  await page.goto('/admin/cargos');
  await expect(page.getByRole('heading', { name: 'Cargos y grados' })).toBeVisible();
  await capture(page, '10-cargos');
  await page.goto('/admin/manual-funciones');
  await expect(page.getByRole('heading', { name: 'Manual de Funciones' })).toBeVisible();
  await capture(page, '11-manual-funciones');
  await page.goto('/admin/certificaciones');
  await expect(page.getByRole('heading', { name: 'Certificados', exact: true })).toBeVisible();
  await capture(page, '12-certificaciones');
  await page.getByRole('link', { name: /CL-2026-/ }).first().click();
  await expect(page.getByRole('heading', { name: 'Detalle de certificado' })).toBeVisible();
  await capture(page, '13-detalle-certificado');
  await page.goto('/admin/configuracion');
  await expect(page.getByRole('heading', { name: 'Pago de certificaciones' })).toBeVisible();
  await capture(page, '14-configuracion');
  await page.goto('/admin/auditoria');
  await expect(page.getByRole('heading', { name: 'Auditoría' })).toBeVisible();
  await capture(page, '15-auditoria');
  await page.goto('/admin/reportes');
  await expect(page.getByRole('heading', { name: 'Reportes' })).toBeVisible();
  await capture(page, '16-reportes');

  await page.goto('/validar-certificado/codigo-evidencia-no-valido');
  await expect(page.getByRole('heading', { name: 'Validar certificado' })).toBeVisible();
  await expect(page.getByText(/No fue posible validar|no encontrado|inválido/i).first()).toBeVisible();
  await capture(page, '17-validacion-publica');
});
