// Optional real-data smoke check against the already bootstrapped CT development stack.
// Run with CT_ADMIN_PASSWORD set; this script never touches recovery or the test databases.
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { randomBytes } from 'node:crypto';

const require = createRequire(new URL('../frontend/react-app/package.json', import.meta.url));
const { chromium } = require('playwright');
const adminPassword = process.env.CT_ADMIN_PASSWORD;
if (!adminPassword) throw new Error('CT_ADMIN_PASSWORD_REQUIRED');

const web = 'http://localhost:3200';
const api = 'http://localhost:8280/api/v1';
const newPassword = `CtManual${randomBytes(10).toString('hex')}A1`;
const suffix = `${Date.now()}`.slice(-11);
const completeDocument = `91${suffix}01`;
const incompleteDocument = `91${suffix}02`;

const browser = await chromium.launch({ channel: 'msedge', headless: true });
const context = await browser.newContext({ baseURL: web, acceptDownloads: true });
const page = await context.newPage();

async function login(document, password) {
  await page.goto('/login');
  await page.getByLabel('Cédula', { exact: true }).fill(document);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  const response = page.waitForResponse(r => r.url().endsWith('/api/v1/auth/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  const result = await response;
  assert.equal(result.status(), 200, `login ${document}`);
  return (await result.json()).data;
}

async function logout() {
  await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
  await page.waitForURL(/\/login/);
}

async function createEmployee(document, cargoId, fichaId, label) {
  await page.goto('/admin/funcionarios/nuevo');
  await page.getByLabel('Número de cédula').fill(document);
  await page.getByLabel('Nombres', { exact: true }).fill('Prueba');
  await page.getByLabel('Apellidos', { exact: true }).fill(`Manual CT ${label}`);
  await page.getByLabel('Dependencia', { exact: true }).fill('Talento Humano CT');
  await page.getByLabel('Fecha de vinculación').fill('2024-01-15');
  await page.locator('select[name="cargo_id"]').selectOption(String(cargoId));
  const selector = page.getByLabel('Ficha del Manual de Funciones');
  await selector.locator(`option[value="${fichaId}"]`).waitFor({ state: 'attached' });
  await selector.selectOption(String(fichaId));
  assert.equal(await selector.inputValue(), String(fichaId));
  const response = page.waitForResponse(r => r.url().endsWith('/api/v1/funcionarios') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Guardar funcionario' }).click();
  const result = await response;
  assert.equal(result.status(), 201, `create employee: ${await result.text()}`);
  await page.waitForURL(/\/admin\/funcionarios\/\d+$/);
  return (await result.json()).data.id;
}

async function employeeSession(document) {
  await login(document, document);
  await page.waitForURL(/\/cambiar-contrasena/);
  await page.getByLabel('Contraseña temporal', { exact: true }).fill(document);
  await page.getByLabel('Nueva contraseña', { exact: true }).fill(newPassword);
  await page.getByLabel('Confirmar nueva contraseña', { exact: true }).fill(newPassword);
  await page.getByRole('button', { name: 'Guardar y continuar' }).click();
  await page.waitForURL(/\/app\/inicio/);
  const session = await page.evaluate(() => JSON.parse(localStorage.getItem('clv_session')));
  assert.ok(session?.token);
  return session.token;
}

async function requestCertificate(token, type) {
  const response = await context.request.post(`${api}/solicitudes`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    data: { tipo_certificado: type },
  });
  return { status: response.status(), body: await response.json() };
}

async function manualSelection(adminToken, sourceId, code, grade, requireEligible = true) {
  const headers = { Authorization: `Bearer ${adminToken}`, Accept: 'application/json' };
  const cargosResponse = await context.request.get(`${api}/cargos?per_page=100`, { headers });
  assert.equal(cargosResponse.status(), 200);
  const cargos = (await cargosResponse.json()).data;
  const cargo = cargos.find(item => item.codigo === code && item.grado === grade);
  assert.ok(cargo, `cargo ${code}/${grade}`);
  const fichasResponse = await context.request.get(`${api}/manual-funciones/fichas?cargo_id=${cargo.id}`, { headers });
  assert.equal(fichasResponse.status(), 200);
  const ficha = (await fichasResponse.json()).data.find(item => item.source_id === sourceId && item.estado === 'publicado');
  assert.ok(ficha, `ficha publicada ${sourceId}`);
  if (requireEligible) assert.equal(ficha.vigente, true, `ficha certificable ${sourceId}`);
  return { cargoId: cargo.id, fichaId: ficha.id };
}

try {
  const admin = await login('000000001', adminPassword);
  assert.equal(admin.user.permisos.length, 36);
  const completeSelection = await manualSelection(admin.token, 'MF-0002', '020', '02');
  const incompleteSelection = await manualSelection(admin.token, 'MF-0167', '219', '07', false);
  const completeId = await createEmployee(completeDocument, completeSelection.cargoId, completeSelection.fichaId, 'Completo MF-0002');
  const incompleteId = await createEmployee(incompleteDocument, incompleteSelection.cargoId, incompleteSelection.fichaId, 'Incompleto MF-0167');
  await logout();

  const completeToken = await employeeSession(completeDocument);
  const simple = await requestCertificate(completeToken, 'sencillo');
  assert.equal(simple.status, 201);
  assert.equal(simple.body.data.resultado, 'generada');
  const functions = await requestCertificate(completeToken, 'funciones');
  assert.equal(functions.status, 201);
  assert.equal(functions.body.data.resultado, 'generada');
  assert.equal(functions.body.data.certificado.snapshot_datos.manual_funciones.source_id, 'MF-0002');
  assert.equal(functions.body.data.certificado.snapshot_datos.manual_funciones.funciones.length, 11);
  const pdf = await context.request.get(`http://localhost:8280${functions.body.data.descarga_url}`, {
    headers: { Authorization: `Bearer ${completeToken}` },
  });
  assert.equal(pdf.status(), 200);
  assert.equal((await pdf.body()).subarray(0, 5).toString(), '%PDF-');
  await logout();

  const incompleteToken = await employeeSession(incompleteDocument);
  const incompleteSimple = await requestCertificate(incompleteToken, 'sencillo');
  assert.equal(incompleteSimple.status, 201);
  assert.equal(incompleteSimple.body.data.resultado, 'generada');
  const incompleteFunctions = await requestCertificate(incompleteToken, 'funciones');
  assert.equal(incompleteFunctions.status, 409);
  assert.equal(incompleteFunctions.body.code, 'MANUAL_FICHA_INCOMPLETA');
  console.log(JSON.stringify({ completeId, completeDocument, completeFicha: 'MF-0002',
    simple: simple.status, functions: functions.status, functionsCount: 11, pdf: pdf.status,
    incompleteId, incompleteDocument, incompleteFicha: 'MF-0167',
    incompleteSimple: incompleteSimple.status, incompleteFunctions: incompleteFunctions.status,
    incompleteCode: incompleteFunctions.body.code }));
} finally {
  await browser.close();
}
