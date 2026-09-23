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

test('cambio de identidad admin → funcionario → admin conserva autorización y limpia sesión', async ({ page }) => {
  const api = 'http://e2e-api:8080/api/v1';
  const documento = `9${Date.now()}`;
  await page.goto('/login');
  await page.getByLabel('Cédula', { exact: true }).fill('E2E-ADMIN');
  await page.getByLabel('Contraseña', { exact: true }).fill('E2EAdminClave2026');
  const adminLoginPromise = page.waitForResponse((response) => response.url().endsWith('/api/v1/auth/login') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  const adminLogin = await adminLoginPromise;
  expect(adminLogin.status()).toBe(200);
  const adminSession = (await adminLogin.json()).data;
  const adminId = adminSession.user.id;
  expect(adminSession.user.documento).toBe('E2E-ADMIN');
  expect(adminSession.user.roles).toContain('admin');
  expect(adminSession.user.permisos).toHaveLength(39);
  const adminHeaders = { Authorization: `Bearer ${adminSession.token}`, Accept: 'application/json' };
  const adminMeBefore = await page.request.get(`${api}/auth/me`, { headers: adminHeaders });
  expect(adminMeBefore.status()).toBe(200);
  expect((await adminMeBefore.json()).data.id).toBe(adminId);
  await expect(page).toHaveURL(/\/admin\/dashboard/);

  await page.goto('/admin/funcionarios/nuevo');
  await page.locator('select[name="cargo_id"]').selectOption({ label: 'Profesional E2E · E2E/01' });
  const cargoId = Number(await page.locator('select[name="cargo_id"]').inputValue());
  const duplicate = await page.request.post(`${api}/funcionarios`, {
    headers: adminHeaders,
    data: {
      tipo_documento: 'CC', numero_documento: 'E2E-ADMIN', nombres: 'Duplicado', apellidos: 'Ficticio',
      estado: 'activo', fecha_ingreso: '2024-01-15', dependencia: 'Talento Humano E2E', cargo_id: cargoId,
      tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa',
    },
  });
  expect(duplicate.status()).toBe(422);
  expect((await duplicate.json()).errors.numero_documento).toBeTruthy();

  await page.getByLabel('Número de cédula').fill(documento);
  await page.getByLabel('Nombres', { exact: true }).fill('Persona');
  await page.getByLabel('Apellidos', { exact: true }).fill('Ficticia Diagnóstico');
  await page.getByLabel('Dependencia', { exact: true }).fill('Talento Humano E2E');
  await page.getByLabel('Fecha de vinculación').fill('2024-01-15');
  const createPromise = page.waitForResponse((response) => response.url().endsWith('/api/v1/funcionarios') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Guardar funcionario' }).click();
  const created = await createPromise;
  expect(created.status()).toBe(201);
  const funcionario = (await created.json()).data;
  const funcionarioId = funcionario.id;
  const funcionarioUserId = funcionario.user_id;
  expect(funcionarioUserId).not.toBe(adminId);
  expect(funcionario.asignacion_actual.ficha_manual.source_id).toBe('E2E-001');
  const adminMeAfter = await page.request.get(`${api}/auth/me`, { headers: adminHeaders });
  const adminAfter = (await adminMeAfter.json()).data;
  expect(adminAfter.id).toBe(adminId);
  expect(adminAfter.roles).toContain('admin');
  expect(adminAfter.permisos).toHaveLength(39);
  expect(adminAfter.funcionario).toBeNull();

  await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
  await expect(page).toHaveURL(/\/login/);
  expect(await page.evaluate(() => localStorage.getItem('clv_session'))).toBeNull();
  expect((await page.request.get(`${api}/auth/me`, { headers: adminHeaders })).status()).toBe(401);

  await page.getByLabel('Cédula', { exact: true }).fill(documento);
  await page.getByLabel('Contraseña', { exact: true }).fill(documento);
  const funcionarioLoginPromise = page.waitForResponse((response) => response.url().endsWith('/api/v1/auth/login') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  const funcionarioLogin = await funcionarioLoginPromise;
  expect(funcionarioLogin.status()).toBe(200);
  const funcionarioSession = (await funcionarioLogin.json()).data;
  expect(funcionarioSession.user.id).toBe(funcionarioUserId);
  expect(funcionarioSession.user.documento).toBe(documento);
  expect(funcionarioSession.user.roles).toContain('funcionario');
  const funcionarioHeaders = { Authorization: `Bearer ${funcionarioSession.token}`, Accept: 'application/json' };
  expect((await page.request.get(`${api}/auth/me`, { headers: funcionarioHeaders })).status()).toBe(200);
  await expect(page).toHaveURL(/\/cambiar-contrasena/);
  await page.getByLabel('Contraseña temporal', { exact: true }).fill(documento);
  await page.getByLabel('Nueva contraseña', { exact: true }).fill('MiClaveNueva2026');
  await page.getByLabel('Confirmar nueva contraseña', { exact: true }).fill('MiClaveNueva2026');
  await page.getByRole('button', { name: 'Guardar y continuar' }).click();
  await expect(page).toHaveURL(/\/app\/inicio/);
  const funcionarioMe = await page.request.get(`${api}/auth/me`, { headers: funcionarioHeaders });
  const funcionarioIdentity = (await funcionarioMe.json()).data;
  expect(funcionarioIdentity.id).toBe(funcionarioUserId);
  expect(funcionarioIdentity.funcionario.id).toBe(funcionarioId);
  expect(funcionarioIdentity.funcionario.user_id).toBe(funcionarioUserId);

  const sinSalario = page.locator('article').filter({ hasText: 'Certificación laboral SIN salario' });
  await sinSalario.getByRole('button', { name: 'Solicitar', exact: true }).click();
  await page.getByRole('button', { name: 'Continuar' }).click();
  const solicitudPromise = page.waitForResponse((response) => response.url().endsWith('/api/v1/solicitudes') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Confirmar y generar' }).click();
  const solicitud = await solicitudPromise;
  const solicitudBody = await solicitud.json();
  console.log('SOLICITUD_DIAGNOSTICO', JSON.stringify({ status: solicitud.status(), success: solicitudBody.success, message: solicitudBody.message, code: solicitudBody.code ?? null, errors: solicitudBody.errors ?? null }));
  expect(solicitud.status()).toBe(201);
  expect(solicitudBody.data.resultado).toBe('generada');
  await expect(page.getByRole('heading', { name: 'Certificación generada correctamente' })).toBeVisible();
  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Descargar PDF' }).click();
  const pdfFile = await (await downloadPromise).path();
  expect((await readFile(pdfFile!)).subarray(0, 5).toString()).toBe('%PDF-');

  await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
  await expect(page).toHaveURL(/\/login/);
  expect(await page.evaluate(() => localStorage.getItem('clv_session'))).toBeNull();
  expect((await page.request.get(`${api}/auth/me`, { headers: funcionarioHeaders })).status()).toBe(401);

  await page.getByLabel('Cédula', { exact: true }).fill('E2E-ADMIN');
  await page.getByLabel('Contraseña', { exact: true }).fill('E2EAdminClave2026');
  const adminReloginPromise = page.waitForResponse((response) => response.url().endsWith('/api/v1/auth/login') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  const adminRelogin = await adminReloginPromise;
  expect(adminRelogin.status()).toBe(200);
  const adminAgain = (await adminRelogin.json()).data;
  expect(adminAgain.user.id).toBe(adminId);
  expect(adminAgain.user.roles).toContain('admin');
  expect(adminAgain.user.permisos).toHaveLength(39);
  const adminMeAgain = await page.request.get(`${api}/auth/me`, { headers: { Authorization: `Bearer ${adminAgain.token}`, Accept: 'application/json' } });
  expect(adminMeAgain.status()).toBe(200);
  expect((await adminMeAgain.json()).data.id).toBe(adminId);
  await expect(page).toHaveURL(/\/admin\/dashboard/);
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('clv_session')!).user.id)).toBe(adminId);
  console.log('IDENTIDAD_DIAGNOSTICO', JSON.stringify({ adminId, funcionarioUserId, funcionarioId, ficha: funcionario.asignacion_actual.ficha_manual.source_id }));
});
