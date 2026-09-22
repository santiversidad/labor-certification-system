import { expect, test, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const output = 'docs/screenshots';

async function login(page: Page, cedula: string, password = 'password') {
  await page.goto('/login');
  await page.getByLabel('Cédula', { exact: true }).fill(cedula);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
}

test('capturas institucionales sin datos sensibles', async ({ page }) => {
  await mkdir(output, { recursive: true });
  await page.setViewportSize({ width: 1366, height: 768 });
  await page.goto('/login');
  await expect(page.getByRole('heading', { name: 'Iniciar sesión' })).toBeVisible();
  await page.screenshot({ path: `${output}/01-login.png`, fullPage: true });

  await page.evaluate(() => localStorage.setItem('clv_session', JSON.stringify({ token: 'captura-local', user: { id: 9999, name: 'Funcionario Demostración', documento: '000000000', estado: true, must_change_password: true, roles: ['funcionario'], permisos: [] } })));
  await page.goto('/cambiar-contrasena');
  await expect(page.getByRole('heading', { name: 'Cambie su contraseña temporal' })).toBeVisible();
  await page.screenshot({ path: `${output}/02-cambio-contrasena.png`, fullPage: true });
  await page.evaluate(() => localStorage.clear());

  await login(page, '000000003');
  await expect(page).toHaveURL(/\/app\/inicio/);
  await expect(page.getByRole('heading', { name: 'Certificaciones laborales' })).toBeVisible();
  await expect(page.locator('.animate-pulse')).toHaveCount(0);
  await page.screenshot({ path: `${output}/03-dashboard-funcionario.png`, fullPage: true });
  await page.goto('/app/solicitudes/nueva?modalidad=sin_salario');
  await expect(page.getByRole('heading', { name: 'Confirmar certificación laboral' })).toBeVisible();
  await page.screenshot({ path: `${output}/04-solicitud.png`, fullPage: true });
  const result = { resultado: 'generada', solicitud: { id: 9999, radicado: 'CL-DEMO-000001', tipo_certificado: 'laboral', estado: 'generada', requiere_pago: false, requiere_salario: false, periodo_mes: '2026-09-01', created_at: '2026-09-20T10:00:00-05:00' }, descarga_url: '/api/v1/mi-certificacion/descargar/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' };
  await page.evaluate((payload) => history.replaceState({ ...(history.state ?? {}), usr: payload }, '', '/app/solicitudes/confirmacion'), result);
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Certificación generada correctamente' })).toBeVisible();
  await page.screenshot({ path: `${output}/05-resultado.png`, fullPage: true });
  await page.getByRole('button', { name: 'Cerrar sesión' }).click();

  await login(page, '000000001');
  await expect(page).toHaveURL(/\/admin\/dashboard/);
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
  await expect(page.locator('.animate-pulse')).toHaveCount(0);
  await page.screenshot({ path: `${output}/06-dashboard-admin.png`, fullPage: true });
  await page.goto('/admin/funcionarios');
  await page.getByRole('heading', { name: 'Funcionarios' }).waitFor();
  await expect(page.locator('.animate-pulse')).toHaveCount(0);
  await page.screenshot({ path: `${output}/07-funcionarios.png`, fullPage: true });
  await page.goto('/admin/funcionarios/nuevo');
  await expect(page.getByRole('heading', { name: 'Crear funcionario' })).toBeVisible();
  await page.screenshot({ path: `${output}/08-alta-funcionario.png`, fullPage: true });
  await page.goto('/admin/funcionarios');
  await expect(page.locator('.animate-pulse')).toHaveCount(0);
  const detailLink = page.getByRole('button', { name: 'Ver funcionario' }).first();
  await expect(detailLink).toBeVisible();
  await detailLink.click();
  await expect(page.getByText('Información básica')).toBeVisible();
  await page.screenshot({ path: `${output}/09-detalle-funcionario.png`, fullPage: true });
  await page.goto('/admin/manual-funciones');
  await page.getByRole('heading', { name: 'Manual de Funciones' }).waitFor();
  await page.screenshot({ path: `${output}/10-manual-funciones.png`, fullPage: true });
  await page.goto('/admin/configuracion');
  await page.getByRole('heading', { name: 'Pago de certificaciones' }).waitFor();
  await page.screenshot({ path: `${output}/11-configuracion.png`, fullPage: true });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.evaluate(() => localStorage.clear());
  await login(page, '000000003');
  await expect(page).toHaveURL(/\/app\/inicio/);
  await expect(page.getByRole('heading', { name: 'Certificaciones laborales' })).toBeVisible();
  await expect(page.locator('.animate-pulse')).toHaveCount(0);
  await page.screenshot({ path: `${output}/12-dashboard-funcionario-mobile.png`, fullPage: true });
});
