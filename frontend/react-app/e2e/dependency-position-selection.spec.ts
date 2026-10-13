import { expect, test, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { browserSession, apiSession, captureBrowserSession, sessionHeaders } from './security-session';
import { fixtureUnit, selectEmployment } from './dependency-selection';
import { getDocument } from 'pdfjs-dist/legacy/build/pdf.mjs';

const api = 'http://e2e-api:8280/api/v1';
const evidence = 'evidence/dependency-position-selection';
async function capture(page: Page, file: string) {
  await page.evaluate(() => { (document.activeElement as HTMLElement | null)?.blur(); window.scrollTo(0, 0); });
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
  await page.evaluate(() => new Promise<void>((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve()))));
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
      await page.getByLabel('Buscar cargo por nombre, código, grado o página').fill('DPE');
      await expect(position).toHaveValue('');
      await expect(position.locator('option')).toHaveCount(2);
      await capture(page, '03-position-search');
      await position.selectOption(await position.locator('option').filter({ hasText: 'Profesional de dependencias E2E · DPE/02' }).first().getAttribute('value') ?? '');
      await unitSelect.selectOption({ label: 'Dependencia B E2E' });
      await expect(position).toHaveValue('');
      await expect(page.getByText(/Perfil funcional asociado:/)).toHaveCount(0);
      await expect(page.getByLabel('Buscar cargo por nombre, código, grado o página')).toHaveValue('');
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
    await expect(position).toHaveValue(`ficha-${body.asignacion_actual.manual_cargo_version_id}`);
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

const ambiguityEvidence = 'evidence/manual-ambiguity-ux';
async function captureAmbiguity(page: Page, file: string) {
  await mkdir(ambiguityEvidence, { recursive: true });
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
  await page.screenshot({ path: `${ambiguityEvidence}/${file}.png`, fullPage: true, animations: 'disabled' });
}
async function chooseAmbiguous(page: Page) {
  await page.goto('/admin/funcionarios/nuevo');
  await page.getByLabel('Fecha de vinculación').fill('2024-01-15');
  const unit = page.getByRole('combobox', { name: 'Dependencia', exact: true });
  await expect(unit.locator('option').filter({ hasText: 'Dependencia A E2E' })).toHaveCount(1);
  await unit.selectOption({ label: 'Dependencia A E2E' });
  const position = page.getByRole('combobox', { name: 'Cargo', exact: true });
  await expect(position).toBeEnabled();
  await position.selectOption(await position.locator('option').filter({ hasText: 'Cargo que requiere revisión E2E' }).first().getAttribute('value') ?? '');
}
test('ambigüedad real: explicación, foco, bloqueo y recuperación con cargo único', async ({ page }) => {
  await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026');
  await chooseAmbiguous(page);
  await page.getByLabel('Número de cédula').fill(`MA${Date.now()}`);
  await page.getByLabel('Nombres', { exact: true }).fill('Persona sintética');
  await page.getByLabel('Apellidos', { exact: true }).fill('Ambigüedad UX');
  const alert = page.getByRole('alert');
  await expect(alert).toContainText('Este perfil requiere revisión del Manual');
  await expect(alert).toContainText('No se pudo verificar la referencia documental');
  await expect(page.getByRole('button', { name: /Reintentar/ })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Guardar funcionario' })).toBeDisabled();
  await captureAmbiguity(page, '01-ambiguous-position');
  await page.setViewportSize({ width: 360, height: 900 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await captureAmbiguity(page, '05-mobile');
  await page.setViewportSize({ width: 1440, height: 1000 });
  const unit = page.getByRole('combobox', { name: 'Dependencia', exact: true });
  const position = page.getByRole('combobox', { name: 'Cargo', exact: true });
  const unitId = await unit.inputValue();
  await page.getByRole('button', { name: 'Elegir otro cargo' }).click();
  await expect(position).toHaveValue('');
  await expect(position).toBeFocused();
  await expect(unit).toHaveValue(unitId);
  await expect(alert).toHaveCount(0);
  await expect(page.getByLabel('Nombres', { exact: true })).toHaveValue('Persona sintética');
  await captureAmbiguity(page, '02-choose-another-position');
  await position.selectOption(await position.locator('option').filter({ hasText: 'Profesional de dependencias E2E · DPE/02' }).first().getAttribute('value') ?? '');
  await expect(page.getByText(/Perfil funcional asociado:/)).toContainText('E2E-UNIDAD-A-DPE');
  await expect(page.getByRole('button', { name: 'Guardar funcionario' })).toBeEnabled();
  await captureAmbiguity(page, '03-resolved-position');
  const submitted = page.waitForResponse((r) => r.url().endsWith('/api/v1/funcionarios') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Guardar funcionario' }).click();
  const response = await submitted;
  expect(response.status()).toBe(201);
  expect((await response.json()).data.asignacion_actual.ficha_manual.source_id).toBe('E2E-UNIDAD-A-DPE');
  await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
});
test('fallo técnico controlado permite reintentar y vuelve a consultar el backend real', async ({ page }) => {
  await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026');
  let attempts = 0;
  await page.route('**/api/v1/manual-funciones/dependencias/resolver?*', async (route) => {
    attempts++;
    if (attempts === 1) await route.fulfill({ status: 503, json: { success: false, message: 'Servidor temporalmente no disponible.' } });
    else await route.continue();
  });
  await chooseAmbiguous(page);
  await expect(page.getByRole('alert')).toContainText('No pudimos consultar el Manual');
  await expect(page.getByRole('button', { name: 'Reintentar', exact: true })).toBeVisible();
  await captureAmbiguity(page, '04-network-retry');
  await page.getByRole('button', { name: 'Reintentar', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Este perfil requiere revisión del Manual');
  expect(attempts).toBe(2);
  await expect(page.getByRole('button', { name: /Reintentar/ })).toHaveCount(0);
  await page.getByRole('button', { name: 'Cambiar dependencia' }).click();
  await expect(page.getByRole('combobox', { name: 'Dependencia', exact: true })).toHaveValue('');
  await expect(page.getByRole('combobox', { name: 'Dependencia', exact: true })).toBeFocused();
  await expect(page.getByRole('combobox', { name: 'Cargo', exact: true })).toBeDisabled();
});

test('páginas distintas: vista previa, selección explícita, edición histórica, snapshot y PDF de la segunda ficha', async ({ page }) => {
  await mkdir(evidence, { recursive: true });
  await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026');
  await page.goto('/admin/funcionarios/nuevo');
  await page.getByLabel('Fecha de vinculación').fill('2024-01-15');
  const unidad = page.getByRole('combobox', { name: 'Dependencia', exact: true });
  await expect(unidad.locator('option').filter({ hasText: 'Dependencia A E2E' })).toHaveCount(1);
  await unidad.selectOption({ label: 'Dependencia A E2E' });
  const cargo = page.getByRole('combobox', { name: 'Cargo', exact: true });
  const primero = cargo.locator('option').filter({ hasText: 'Profesional de páginas E2E · PGS/02 · Página 101' });
  const segundo = cargo.locator('option').filter({ hasText: 'Profesional de páginas E2E · PGS/02 · Página 102' });
  await expect(primero).toHaveCount(1);
  await expect(segundo).toHaveCount(1);
  await cargo.selectOption(await primero.getAttribute('value') ?? '');
  await page.getByRole('button', { name: 'Ver funciones del cargo' }).click();
  const preview = page.getByRole('region', { name: 'Funciones del cargo' });
  await expect(preview).toContainText('Función exclusiva de la ficha sintética 101.');
  await expect(preview).toContainText('Página 101');
  await capture(page, '07-first-profile-preview');
  await cargo.selectOption(await segundo.getAttribute('value') ?? '');
  await expect(preview).toHaveCount(0);
  await expect(page.getByText(/Perfil funcional asociado:/)).toContainText('E2E-PAGINA-102');
  await page.getByRole('button', { name: 'Ver funciones del cargo' }).click();
  await expect(preview).toContainText('Función exclusiva de la ficha sintética 102.');
  await expect(preview).not.toContainText('Función exclusiva de la ficha sintética 101.');
  const fichaId = Number((await cargo.inputValue()).replace('ficha-', ''));
  for (const width of [360, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 1000 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await cargo.focus();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Buscar cargo por nombre, código, grado o página')).toBeFocused();
    await capture(page, `08-preview-${width}`);
  }
  await page.getByLabel('Buscar cargo por nombre, código, grado o página').fill('102');
  await expect(cargo).toHaveValue('');
  await expect(preview).toHaveCount(0);
  await expect(cargo.locator('option')).toHaveCount(2);
  await cargo.selectOption(`ficha-${fichaId}`);
  await expect(page.getByText(/Perfil funcional asociado:/)).toContainText('Página 102');
  const documento = `PG${Date.now()}`;
  await page.getByLabel('Número de cédula').fill(documento);
  await page.getByLabel('Nombres', { exact: true }).fill('Persona páginas');
  await page.getByLabel('Apellidos', { exact: true }).fill('Sintética');
  const submitted = page.waitForResponse((r) => r.url().endsWith('/api/v1/funcionarios') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Guardar funcionario' }).click();
  const created = await submitted;
  expect(created.status()).toBe(201);
  const employee = (await created.json()).data;
  expect(employee.asignacion_actual.manual_cargo_version_id).toBe(fichaId);
  expect(employee.asignacion_actual.ficha_manual.pagina_inicio).toBe(102);
  await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
  await page.getByRole('button', { name: 'Editar', exact: true }).click();
  await expect(cargo).toHaveValue(`ficha-${fichaId}`);
  await page.getByRole('button', { name: 'Ver funciones del cargo' }).click();
  await expect(preview).toContainText('Función exclusiva de la ficha sintética 102.');
  await page.getByLabel('Teléfono').fill('3001234567');
  const updated = page.waitForResponse((r) => r.url().endsWith(`/api/v1/funcionarios/${employee.id}`) && r.request().method() === 'PUT');
  await page.getByRole('button', { name: 'Guardar funcionario' }).click();
  const editedResponse = await updated;
  expect(editedResponse.status()).toBe(200);
  const edited = (await editedResponse.json()).data;
  expect(edited.asignacion_actual.id).toBe(employee.asignacion_actual.id);
  expect(edited.asignacion_actual.manual_cargo_version_id).toBe(fichaId);
  await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
  await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
  const session = await apiSession(page.request, documento, documento);
  const password = 'Documentary personal phrase 2026';
  expect((await page.request.put(api + '/auth/change-password', { headers: sessionHeaders(session.token), data: { current_password: documento, password, password_confirmation: password } })).status()).toBe(200);
  await browserSession(page, documento, password);
  await page.goto('/app/solicitudes/nueva/funciones');
  const generated = page.waitForResponse((r) => r.url().endsWith('/api/v1/solicitudes') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Generar certificado con funciones' }).click();
  const result = await generated;
  expect(result.status()).toBe(201);
  const body = (await result.json()).data;
  const snapshot = body.certificado.snapshot_datos.manual_funciones;
  expect(snapshot.ficha_id).toBe(fichaId);
  expect(snapshot.source_id).toBe('E2E-PAGINA-102');
  expect(snapshot.pagina_inicio).toBe(102);
  expect(snapshot.funciones.map((f: { descripcion: string }) => f.descripcion)).toEqual(['Función exclusiva de la ficha sintética 102.']);
  const browser = await captureBrowserSession(page);
  const pdf = await page.request.get(`http://e2e-api:8280${body.descarga_url}`, { headers: sessionHeaders(browser) });
  expect(pdf.status()).toBe(200);
  const loadingTask = getDocument({ data: new Uint8Array(await pdf.body()), useSystemFonts: true });
  const parsed = await loadingTask.promise;
  let text = '';
  for (let n = 1; n <= parsed.numPages; n++) {
    const content = await (await parsed.getPage(n)).getTextContent();
    text += content.items.map((item) => 'str' in item ? item.str : '').join(' ');
  }
  await loadingTask.destroy();
  expect(text).toContain('Función exclusiva de la ficha sintética 102.');
  expect(text).not.toContain('Función exclusiva de la ficha sintética 101.');
  expect(text).toContain('SIN VALIDEZ OFICIAL');
  await capture(page, '09-exact-profile-certificate');
});

test('API rechaza ficha del mismo cargo y grado perteneciente a otra dependencia', async ({ page }) => {
  const admin = await apiSession(page.request, 'E2E-ADMIN', 'E2EAdminClave2026');
  const headers = sessionHeaders(admin.token);
  const unidadA = await fixtureUnit(page.request, admin.token, 'Dependencia A E2E');
  const unidadB = await fixtureUnit(page.request, admin.token, 'Dependencia B E2E');
  const candidates = await page.request.get(api + '/manual-funciones/dependencias/cargos', { headers, params: { unidad_organizacional_id: unidadB, fecha: '2024-01-15', perfiles: 1 } });
  expect(candidates.status()).toBe(200);
  const fichaB = (await candidates.json()).data.find((f: { source_id: string }) => f.source_id === 'E2E-UNIDAD-B-DPE');
  expect(fichaB).toBeTruthy();
  const result = await page.request.post(api + '/funcionarios', { headers, data: {
    tipo_documento: 'CC', numero_documento: `INC${Date.now()}`, nombres: 'Persona sintética', apellidos: 'Incompatible', estado: 'activo', fecha_ingreso: '2024-01-15',
    unidad_organizacional_id: unidadA, cargo_id: fichaB.id, manual_cargo_version_id: fichaB.manual_cargo_version_id, tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa',
  } });
  expect(result.status()).toBe(422);
  expect((await result.json()).errors.manual_cargo_version_id).toBeTruthy();
});
