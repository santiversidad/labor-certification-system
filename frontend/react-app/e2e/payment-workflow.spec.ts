import { fixtureUnit } from './dependency-selection';
import { apiSession, browserSession, sessionHeaders } from './security-session';
import { expect, test, type APIRequestContext, type Page } from '@playwright/test';
import { mkdir, readFile } from 'node:fs/promises';

const api = 'http://e2e-api:8280/api/v1';
const evidence = 'test-results/regression-phase27';
const receipt = { name: 'comprobante-ficticio.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n% Comprobante SINTETICO sin pago real.\n') };
const headers = sessionHeaders;

async function apiLogin(request: APIRequestContext, username: string, password: string) { return (await apiSession(request, username, password)).token; }
async function login(page: Page, username: string, password: string) { const result = await browserSession(page, username, password); await expect(page).toHaveURL(/\/(app\/inicio|admin\/dashboard)$/); return result.token; }
async function employee(request: APIRequestContext, admin: string, suffix: string) {
  const documento = `P27${Date.now()}${suffix}`.slice(0, 20);
  const cargos = await (await request.get(`${api}/cargos?per_page=100`, { headers: headers(admin) })).json();
  const cargo = cargos.data.find((c: { codigo: string }) => c.codigo === 'E2E'); expect(cargo).toBeTruthy();
  const r = await request.post(`${api}/funcionarios`, { headers: headers(admin), data: { tipo_documento: 'CC', numero_documento: documento, nombres: 'Funcionario pago', apellidos: `Ficticio ${suffix}`, estado: 'activo', fecha_ingreso: '2024-01-15', unidad_organizacional_id: await fixtureUnit(request, admin), cargo_id: cargo.id, tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa' } });
  expect(r.status()).toBe(201);
  const token = await apiLogin(request, documento, documento);
  expect((await request.put(`${api}/auth/change-password`, { headers: headers(token), data: { current_password: documento, password: 'E2EPagoClave2026', password_confirmation: 'E2EPagoClave2026' } })).status()).toBe(200);
  return { documento, token };
}
async function configure(request: APIRequestContext, admin: string, active: boolean) {
  expect((await request.patch(`${api}/configuracion/certificaciones`, { headers: headers(admin), data: { requiere_pago_certificado: active } })).status()).toBe(200);
}
async function capture(page: Page, name: string) {
  await expect(page.locator('.animate-pulse')).toHaveCount(0); await page.screenshot({ path: `${evidence}/${name}.png`, fullPage: true });
}
async function create(page: Page) {
  await page.goto('/app/solicitudes/nueva/sencillo'); await expect(page.getByText('Este certificado requiere pago')).toBeVisible();
  const response = page.waitForResponse((r) => r.url().endsWith('/api/v1/solicitudes') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Crear solicitud sencillo' }).click();
  const r = await response; expect(r.status()).toBe(201); const data = (await r.json()).data; expect(data.resultado).toBe('pendiente_pago');
  expect(data).not.toHaveProperty('certificado'); await expect(page.getByText('Solicitud creada — Pago pendiente')).toBeVisible();
  return data.solicitud.id as number;
}
async function upload(page: Page) {
  await page.getByLabel('Comprobante de pago').setInputFiles(receipt);
  const response = page.waitForResponse((r) => r.url().endsWith('/pago/comprobantes') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Enviar comprobante' }).click(); expect((await response).status()).toBe(201);
  await expect(page.getByRole('heading', { name: 'Historial de comprobantes' })).toBeVisible();
  await expect(page.getByText('Secretaría está revisando el comprobante.', { exact: false })).toBeVisible();
}
async function confirm(page: Page) {
  await page.getByRole('button', { name: 'Confirmar pago', exact: true }).click();
  const response = page.waitForResponse((r) => r.url().endsWith('/confirmar') && r.request().method() === 'POST');
  await page.getByRole('dialog').getByRole('button', { name: 'Confirmar pago', exact: true }).click();
  const r = await response; expect(r.status()).toBe(200); expect((await r.json()).data.estado).toBe('generada');
  await expect(page.getByText('Pago confirmado', { exact: false }).first()).toBeVisible();
}

test.describe('Fase 2.7 pago real con evidencia ficticia', () => {
  test.describe.configure({ mode: 'serial' });
  test.beforeAll(async () => { await mkdir(evidence, { recursive: true }); });
  test('pago OFF mantiene expedición automática y descarga', async ({ page }) => {
    const admin = await apiLogin(page.request, 'E2E-ADMIN', 'E2EAdminClave2026'); await configure(page.request, admin, false);
    const u = await employee(page.request, admin, 'OFF'); u.token = await login(page, u.documento, 'E2EPagoClave2026');
    await page.goto('/app/solicitudes/nueva/sencillo'); await page.getByRole('button', { name: 'Generar certificado sencillo' }).click();
    await expect(page.getByText('Certificación generada correctamente')).toBeVisible();
    await capture(page, '01-pago-desactivado');
  });

  test('pago ON: aviso, upload, Secretaría confirma y titular descarga', async ({ page, browser }) => {
    // Change configuration through the actual administrative UI.
    const admin = await login(page, 'E2E-ADMIN', 'E2EAdminClave2026'); await page.goto('/admin/configuracion');
    await page.getByRole('switch', { name: 'Exigir pago para certificaciones' }).click();
    await page.getByRole('button', { name: 'Activar pago', exact: true }).click(); await expect(page.getByText('ACTIVADO', { exact: true })).toBeVisible();
    const u = await employee(page.request, admin, 'ON'); u.token = await login(page, u.documento, 'E2EPagoClave2026');
    await page.setViewportSize({ width: 1440, height: 1000 }); await page.goto('/app/solicitudes/nueva/sencillo');
    await expect(page.getByText('Este certificado requiere pago')).toBeVisible(); await capture(page, '02-pago-activo-aviso');
    const id = await create(page); await capture(page, '03-solicitud-pendiente-pago');
    await page.getByRole('link', { name: 'Adjuntar comprobante y consultar estado' }).click();
    await expect(page.getByRole('heading', { name: 'Adjuntar comprobante', exact: true })).toBeVisible(); await capture(page, '04-cargar-comprobante');
    await page.setViewportSize({ width: 360, height: 900 }); await capture(page, '13-mobile-pago');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.setViewportSize({ width: 1440, height: 1000 }); await upload(page); await capture(page, '05-pago-en-revision');
    const ctx = await browser.newContext(); const secretary = await ctx.newPage();
    await login(secretary, 'E2E-SECRETARIA', 'E2ESecretaria2026'); await secretary.goto('/admin/pagos');
    await expect(secretary.getByRole('heading', { name: 'Validación de pagos', exact: true })).toBeVisible(); await capture(secretary, '06-secretaria-bandeja');
    await secretary.goto(`/admin/pagos/${id}`); await expect(secretary.getByRole('heading', { name: /Pago de solicitud/ })).toBeVisible();
    await capture(secretary, '07-secretaria-detalle');
    await secretary.setViewportSize({ width: 360, height: 900 }); await capture(secretary, '14-mobile-secretaria');
    expect(await secretary.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await secretary.setViewportSize({ width: 1440, height: 1000 });
    await secretary.getByRole('button', { name: 'Confirmar pago', exact: true }).click(); await capture(secretary, '08-confirmar-pago');
    await secretary.getByRole('dialog').getByRole('button', { name: 'Cancelar', exact: true }).click(); await confirm(secretary);
    await capture(secretary, '11-pago-confirmado');
    await page.getByRole('button', { name: 'Actualizar estado' }).click(); await expect(page.getByRole('button', { name: 'Descargar PDF' })).toBeVisible(); await capture(page, '12-certificado-generado');
    const download = page.waitForEvent('download'); await page.getByRole('button', { name: 'Descargar PDF' }).click(); const file = await download;
    expect((await readFile((await file.path())!)).subarray(0, 5).toString()).toBe('%PDF-');
    const detail = await (await page.request.get(`${api}/solicitudes/${id}`, { headers: headers(u.token) })).json(); expect(detail.data.certificado.snapshot_schema_version).toBe(3);
    await ctx.close();
  });

  test('rechazo llega al titular y segundo comprobante usa misma solicitud; IDOR bloqueado', async ({ page }) => {
    const admin = await apiLogin(page.request, 'E2E-ADMIN', 'E2EAdminClave2026'); await configure(page.request, admin, true);
    const u = await employee(page.request, admin, 'REJ'); u.token = await login(page, u.documento, 'E2EPagoClave2026');
    const id = await create(page); await page.goto(`/app/solicitudes/${id}`); await upload(page);
    const owner = await (await page.request.get(`${api}/solicitudes/${id}`, { headers: headers(u.token) })).json();
    const fileUrl = owner.data.pago_soporte.comprobantes[0].archivo_url;
    const other = await employee(page.request, admin, 'IDOR');
    expect((await page.request.get(`http://e2e-api:8280${fileUrl}`, { headers: headers(other.token) })).status()).toBe(403);
    expect((await page.request.get(`${api}/solicitudes/${id}`, { headers: headers(other.token) })).status()).toBe(403);
    await login(page, 'E2E-SECRETARIA', 'E2ESecretaria2026'); await page.goto(`/admin/pagos/${id}`);
    await page.getByRole('button', { name: 'Rechazar pago', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Registrar rechazo' })).toBeDisabled();
    await page.getByLabel('Motivo del rechazo').fill('Comprobante ficticio ilegible'); await page.getByRole('button', { name: 'Registrar rechazo' }).click();
    await expect(page.getByText('Pago no validado', { exact: true }).first()).toBeVisible();
    u.token = await login(page, u.documento, 'E2EPagoClave2026'); await page.goto(`/app/solicitudes/${id}`);
    await expect(page.getByText('Comprobante ficticio ilegible', { exact: true })).toBeVisible(); await capture(page, '09-pago-rechazado-funcionario');
    await page.getByLabel('Comprobante de pago').setInputFiles(receipt); await capture(page, '10-reemplazar-comprobante'); await upload(page);
    await login(page, 'E2E-SECRETARIA', 'E2ESecretaria2026'); await page.goto(`/admin/pagos/${id}`); await confirm(page);
    u.token = await apiLogin(page.request, u.documento, 'E2EPagoClave2026'); // Switching this browser invalidated its former employee cookie.
    const final = await (await page.request.get(`${api}/solicitudes/${id}`, { headers: headers(u.token) })).json();
    expect(final.data.id).toBe(id); expect(final.data.pago_soporte.comprobantes.map((c: { estado: string }) => c.estado)).toEqual(['rechazado', 'confirmado']);
    expect((await page.request.get(`http://e2e-api:8280${fileUrl}`, { headers: headers(u.token) })).status()).toBe(200);
  });

  test('concurrencia real: crear, upload, dos confirmaciones y confirmar/rechazar', async ({ page }) => {
    const request = page.request; const admin = await apiLogin(request, 'E2E-ADMIN', 'E2EAdminClave2026'); await configure(request, admin, true);
    const u = await employee(request, admin, 'CON');
    const created = await Promise.all([1, 2].map(() => request.post(`${api}/solicitudes`, { headers: headers(u.token), data: { tipo_certificado: 'sencillo' } })));
    expect(created.map((r) => r.status()).sort()).toEqual([201, 409]); const id = (await created.find((r) => r.status() === 201)!.json()).data.solicitud.id;
    const uploaded = await Promise.all([1, 2].map(() => request.post(`${api}/solicitudes/${id}/pago/comprobantes`, { headers: headers(u.token), multipart: { archivo: receipt } })));
    expect(uploaded.map((r) => r.status()).sort()).toEqual([201, 409]);
    const s1 = await apiLogin(request, 'E2E-SECRETARIA', 'E2ESecretaria2026'); const s2 = await apiLogin(request, 'E2E-SECRETARIA-2', 'E2ESecretaria2026');
    const confirmed = await Promise.all([s1, s2].map((token) => request.post(`${api}/secretaria/pagos/${id}/confirmar`, { headers: headers(token) })));
    expect(confirmed.map((r) => r.status())).toEqual([200, 200]);
    const certs = await (await request.get(`${api}/certificados?per_page=100`, { headers: headers(admin) })).json();
    expect(certs.data.filter((c: { solicitud: { id: number } }) => c.solicitud.id === id)).toHaveLength(1);
    const r = await request.post(`${api}/solicitudes`, { headers: headers(u.token), data: { tipo_certificado: 'funciones' } }); expect(r.status()).toBe(201); const raceId = (await r.json()).data.solicitud.id;
    expect((await request.post(`${api}/solicitudes/${raceId}/pago/comprobantes`, { headers: headers(u.token), multipart: { archivo: receipt } })).status()).toBe(201);
    const race = await Promise.all([
      request.post(`${api}/secretaria/pagos/${raceId}/confirmar`, { headers: headers(s1) }),
      request.post(`${api}/secretaria/pagos/${raceId}/rechazar`, { headers: headers(s2), data: { motivo: 'Revisión concurrente ficticia' } }),
    ]);
    expect(race.map((r) => r.status()).sort()).toEqual([200, 409]);
    const final = await (await request.get(`${api}/solicitudes/${raceId}`, { headers: headers(u.token) })).json(); expect(['generada', 'pago_rechazado']).toContain(final.data.estado);
    await configure(request, admin, false);
  });
});
