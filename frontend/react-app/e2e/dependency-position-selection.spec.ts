import { expect, test, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { browserSession, apiSession, sessionHeaders } from './security-session';
import { selectEmployment } from './dependency-selection';

const api = 'http://e2e-api:8280/api/v1';
const evidence = 'evidence/dependency-position-selection';
async function capture(page: Page, file: string) {
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: `${evidence}/${file}.png`, fullPage: true, animations: 'disabled' });
}
test.use({ viewport: { width: 1440, height: 1000 }, timezoneId: 'America/Bogota', locale: 'es-CO' });
for (const unidad of ['A', 'B']) {
  test(`dependencia ${unidad}: cargos filtrados, edición y certificados con ficha exacta`, async ({ page }) => {
    await mkdir(evidence, { recursive: true });
    await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026');
    await page.goto('/admin/funcionarios');
    await page.getByRole('link', { name: 'Crear funcionario' }).click();
    const documento = `DP${Date.now()}${unidad}`;
    await page.getByLabel('Número de cédula').fill(documento);
    await page.getByLabel('Nombres', { exact: true }).fill('Persona sintética');
    await page.getByLabel('Apellidos', { exact: true }).fill('Dependencia ' + unidad);
    const unitSelect = page.getByRole('combobox', { name: 'Dependencia', exact: true });
    const position = page.getByRole('combobox', { name: 'Cargo', exact: true });
    await expect(position).toBeDisabled();
    await page.getByLabel('Fecha de vinculación').fill('2024-01-15');
    await expect(unitSelect.locator('option').filter({ hasText: 'Dependencia A E2E' })).toHaveCount(1);
    if (unidad === 'A') await capture(page, '01-create-employee-dependency');
    await selectEmployment(page, 'Profesional de dependencias E2E · DPE/02', `Dependencia ${unidad} E2E`);
    await expect(position.locator('option').filter({ hasText: `Cargo exclusivo ${unidad} E2E` })).toHaveCount(1);
    await expect(position.locator('option').filter({ hasText: 'Cargo transversal E2E' })).toHaveCount(1);
    await expect(position.locator('option').filter({ hasText: `Cargo exclusivo ${unidad === 'A' ? 'B' : 'A'} E2E` })).toHaveCount(0);
    await expect(page.getByText(/Perfil funcional asociado:/)).toContainText(`E2E-UNIDAD-${unidad}-DPE`);
    if (unidad === 'A') {
      await capture(page, '02-position-filtered');
      await page.getByLabel('Buscar cargo por nombre, código o grado').fill('DPE');
      await expect(position).toHaveValue('');
      await expect(position.locator('option')).toHaveCount(2);
      await capture(page, '03-position-search');
      await position.selectOption({ label: 'Profesional de dependencias E2E · DPE/02' });
      await unitSelect.selectOption({ label: 'Dependencia B E2E' });
      await expect(position).toHaveValue('');
      await expect(page.getByText(/Perfil funcional asociado:/)).toHaveCount(0);
      await expect(page.getByLabel('Buscar cargo por nombre, código o grado')).toHaveValue('');
      await selectEmployment(page, 'Profesional de dependencias E2E · DPE/02', 'Dependencia A E2E');
    }
    const submit = page.waitForResponse((response) => response.url().endsWith('/api/v1/funcionarios') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Guardar funcionario' }).click();
    const response = await submit;
    expect(response.status()).toBe(201);
    const body = (await response.json()).data;
    expect(body.unidad_organizacional.nombre).toBe(`Dependencia ${unidad} E2E`);
    expect(body.asignacion_actual.ficha_manual.source_id).toBe(`E2E-UNIDAD-${unidad}-DPE`);
    await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
    await page.getByRole('button', { name: 'Editar', exact: true }).click();
    await expect(unitSelect).toHaveValue(body.unidad_organizacional.id);
    await expect(position).toHaveValue(String(body.cargo.id));
    await expect(position).toBeEnabled();
    await expect(page.getByText('Cargando cargos...', { exact: true })).toHaveCount(0);
    await expect(page.getByText(/Perfil funcional registrado:/)).toContainText(`E2E-UNIDAD-${unidad}-DPE`);
    if (unidad === 'A') {
      await capture(page, '04-edit-employee');
      for (const width of [360, 768, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await unitSelect.focus();
        await page.keyboard.press('Tab');
        await expect(page.getByLabel('Buscar dependencia por nombre')).toBeFocused();
        if (width === 360) await capture(page, '05-mobile');
        if (width === 768) await capture(page, '06-tablet');
      }
    }
    await page.getByRole('button', { name: 'Guardar funcionario' }).click();
    await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
    await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
    const employee = await apiSession(page.request, documento, documento);
    const password = 'Dependency personal phrase 2026';
    expect((await page.request.put(api + '/auth/change-password', { headers: sessionHeaders(employee.token), data: { current_password: documento, password, password_confirmation: password } })).status()).toBe(200);
    await browserSession(page, documento, password);
    for (const tipo of ['sencillo', 'funciones']) {
      await page.goto(`/app/solicitudes/nueva/${tipo}`);
      const generated = page.waitForResponse((r) => r.url().endsWith('/api/v1/solicitudes') && r.request().method() === 'POST');
      await page.getByRole('button', { name: tipo === 'sencillo' ? 'Generar certificado sencillo' : 'Generar certificado con funciones' }).click();
      const result = await generated;
      expect(result.status()).toBe(201);
      const certificate = (await result.json()).data.certificado;
      if (tipo === 'funciones') {
        expect(certificate.snapshot_datos.manual_funciones.source_id).toBe(`E2E-UNIDAD-${unidad}-DPE`);
        expect(certificate.snapshot_datos.manual_funciones.funciones[0].descripcion).toBe(`Función exclusiva de dependencia ${unidad} E2E.`);
      }
      await expect(page.getByRole('button', { name: 'Descargar PDF' })).toBeVisible();
    }
  });
}

test('perfil transversal conserva la unidad elegida y no inventa una dependencia', async ({ page }) => {
  await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026');
  await page.goto('/admin/funcionarios/nuevo');
  await page.getByLabel('Número de cédula').fill(`DG${Date.now()}`);
  await page.getByLabel('Nombres', { exact: true }).fill('Persona transversal');
  await page.getByLabel('Apellidos', { exact: true }).fill('Sintética');
  await selectEmployment(page, 'Cargo transversal E2E · DPG/01', 'Dependencia B E2E');
  await expect(page.getByRole('combobox', { name: 'Dependencia', exact: true }).locator('option').filter({ hasText: 'Todas las dependencias' })).toHaveCount(0);
  await expect(page.getByText(/Perfil funcional asociado:/)).toContainText('E2E-TRANSVERSAL-001');
  const submitted = page.waitForResponse((response) => response.url().endsWith('/api/v1/funcionarios') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Guardar funcionario' }).click();
  const response = await submitted;
  expect(response.status()).toBe(201);
  const body = (await response.json()).data;
  expect(body.dependencia).toBe('Dependencia B E2E');
  expect(body.unidad_organizacional.nombre).toBe('Dependencia B E2E');
  expect(body.asignacion_actual.ficha_manual.source_id).toBe('E2E-TRANSVERSAL-001');
  await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
  await page.getByRole('button', { name: 'Editar', exact: true }).click();
  await expect(page.getByRole('combobox', { name: 'Dependencia', exact: true })).toHaveValue(body.unidad_organizacional.id);
  await expect(page.getByText(/Perfil funcional registrado:/)).toContainText('E2E-TRANSVERSAL-001');
});
