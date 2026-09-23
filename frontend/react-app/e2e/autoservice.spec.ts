import { expect, test } from '@playwright/test';
import { readFile } from 'node:fs/promises';

test('admin revalida identidad y navega módulos autorizados', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Cédula', { exact: true }).fill('E2E-ADMIN');
  await page.getByLabel('Contraseña', { exact: true }).fill('E2EAdminClave2026');
  const meResponse = page.waitForResponse((response) => response.url().endsWith('/api/v1/auth/me'));
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  expect((await meResponse).status()).toBe(200);
  await expect(page).toHaveURL(/\/admin\/dashboard/);

  const funcionariosResponse = page.waitForResponse((response) => response.url().includes('/api/v1/funcionarios') && response.request().method() === 'GET');
  await page.goto('/admin/funcionarios');
  expect((await funcionariosResponse).status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Funcionarios' })).toBeVisible();
  await expect(page.getByRole('table', { name: 'Listado de funcionarios' })).toBeVisible();

  const manualResponse = page.waitForResponse((response) => response.url().endsWith('/api/v1/manual-funciones/estado'));
  await page.goto('/admin/manual-funciones');
  expect((await manualResponse).status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Manual de Funciones' })).toBeVisible();
  await expect(page.getByText('Versiones registradas')).toBeVisible();

  const configuracionResponse = page.waitForResponse((response) => response.url().endsWith('/api/v1/configuracion/certificaciones'));
  await page.goto('/admin/configuracion');
  expect((await configuracionResponse).status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Pago de certificaciones' })).toBeVisible();
  await expect(page.getByText('Exigir pago para certificaciones', { exact: true }).first()).toBeVisible();
  await expect(page.getByText(/rol no autorizado/i)).toHaveCount(0);

  await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
  await expect(page).toHaveURL(/\/login/);
});

test('alta administrativa → primer ingreso → PDF propio sin aprobación', async ({ page }) => {
  const documento = `9${Date.now()}`;
  await page.goto('/login');
  await page.getByLabel('Cédula', { exact: true }).fill('E2E-ADMIN');
  await page.getByLabel('Contraseña', { exact: true }).fill('E2EAdminClave2026');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/\/admin\/dashboard/);
  await page.goto('/admin/funcionarios/nuevo');
  await page.getByLabel('Número de cédula').fill(documento);
  await page.getByLabel('Nombres', { exact: true }).fill('Persona');
  await page.getByLabel('Apellidos', { exact: true }).fill('Ficticia E2E');
  await page.getByLabel('Dependencia', { exact: true }).fill('Talento Humano E2E');
  await page.locator('select[name="cargo_id"]').selectOption({ label: 'Profesional E2E · E2E/01' });
  await page.getByLabel('Fecha de vinculación').fill('2024-01-15');
  await expect(page.getByRole('button', { name: 'Guardar funcionario' })).toBeEnabled();
  await page.getByRole('button', { name: 'Guardar funcionario' }).click();
  await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
  await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
  await expect(page).toHaveURL(/\/login/);
  await page.getByLabel('Cédula', { exact: true }).fill(documento);
  await page.getByLabel('Contraseña', { exact: true }).fill(documento);
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/\/cambiar-contrasena/);
  // Direct navigation cannot bypass first-access guard.
  await page.goto('/app/inicio');
  await expect(page).toHaveURL(/\/cambiar-contrasena/);
  await page.getByLabel('Contraseña temporal', { exact: true }).fill(documento);
  await page.getByLabel('Nueva contraseña', { exact: true }).fill('MiClaveNueva2026');
  await page.getByLabel('Confirmar nueva contraseña', { exact: true }).fill('MiClaveNueva2026');
  await page.getByRole('button', { name: 'Guardar y continuar' }).click();
  await expect(page).toHaveURL(/\/app\/inicio/);
  const sinSalario = page.locator('article').filter({ hasText: 'Certificación laboral SIN salario' });
  await sinSalario.getByRole('button', { name: 'Solicitar', exact: true }).click();
  await page.getByRole('button', { name: 'Continuar' }).click();
  await page.getByRole('button', { name: 'Confirmar y generar' }).click();
  await expect(page.getByRole('heading', { name: 'Certificación generada correctamente' })).toBeVisible();
  const downloadEvent = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Descargar PDF' }).click();
  const download = await downloadEvent;
  const file = await download.path();
  expect(file).not.toBeNull();
  expect((await readFile(file!)).subarray(0, 5).toString()).toBe('%PDF-');
  // Two concurrent requests for the other modality must produce exactly one issue.
  const statuses = await page.evaluate(async () => {
    const session = JSON.parse(localStorage.getItem('clv_session')!);
    const request = () => fetch('http://e2e-api:8080/api/v1/solicitudes', {
      method: 'POST', headers: { Authorization: `Bearer ${session.token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ tipo_certificado: 'funciones', requiere_salario: true }),
    }).then((response) => response.status);
    return Promise.all([request(), request()]);
  });
  expect(statuses.sort()).toEqual([201, 409]);
  await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
  await expect(page).toHaveURL(/\/login/);
});
