import { expect, test, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { browserSession, sessionHeaders } from './security-session';

const api = 'http://e2e-api:8280/api/v1/escala-salarial';
const evidence = 'evidence/salary-scale';
async function capture(page: Page, file: string) {
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
  await page.screenshot({ path: `${evidence}/${file}.png`, fullPage: true, animations: 'disabled' });
}
test.use({ viewport: { width: 1440, height: 1000 }, timezoneId: 'America/Bogota', locale: 'es-CO' });

test('admin crea borrador desde base, revisa aumento, edita, publica y conserva históricos', async ({ page }) => {
  await mkdir(evidence, { recursive: true });
  const session = await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026');
  const headers = sessionHeaders(session.token);
  const years = (await (await page.request.get(`${api}/vigencias`, { headers })).json()).data;
  const base = years.find((year: { anio: number }) => year.anio === 2025);
  const next = Math.max(2026, ...years.map((year: { anio: number }) => year.anio + 1));
  const historical = (await (await page.request.get(`${api}/vigencias/${base.id}`, { headers })).json()).data.items;
  await page.getByRole('link', { name: 'Escala salarial', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Escala salarial', exact: true })).toBeVisible();
  await expect(page.getByText('Profesional salarial sintético E2E', { exact: true })).toBeVisible();
  await capture(page, '01-table-desktop');
  await page.getByLabel('Buscar por código, grado o denominación').fill('909');
  await page.getByRole('button', { name: 'Buscar', exact: true }).click();
  await expect(page.getByText('Profesional salarial sintético E2E', { exact: true })).toBeVisible();
  await expect(page.getByText('Consultando escala salarial…', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Técnico salarial sintético E2E', { exact: true })).toHaveCount(0);
  await capture(page, '02-search');
  await page.getByLabel('Buscar por código, grado o denominación').fill(''); await page.getByRole('button', { name: 'Buscar', exact: true }).click();
  await page.getByRole('button', { name: 'Crear vigencia', exact: true }).click();
  await page.getByLabel('Año nuevo').fill(String(next));
  await page.getByLabel('Vigencia base', { exact: true }).selectOption(String(base.id));
  await page.getByLabel('Fuente / procedencia').fill('Ejercicio sintético E2E; no oficial');
  await capture(page, '03-create-year');
  await page.getByLabel('Porcentaje de aumento (%) · opcional', { exact: true }).fill('7,00');
  await page.getByRole('button', { name: 'Previsualizar aumento' }).click();
  await expect(page.getByText('Vista previa · 2 filas')).toBeVisible();
  await capture(page, '04-increase-preview');
  const created = page.waitForResponse((response) => response.url().endsWith('/escala-salarial/vigencias') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Confirmar generación del borrador' }).click();
  const response = await created; expect(response.status()).toBe(201); const target = (await response.json()).data;
  const region = page.getByRole('region', { name: /Tabla de escala salarial/ });
  const edit = page.getByRole('button', { name: `Editar 909 grado 07 ${next}` });
  await expect(edit).toBeVisible(); await edit.scrollIntoViewIfNeeded(); await capture(page, '05-draft-2026');
  await edit.click(); await page.getByLabel('Asignación básica mensual (COP)').fill('1234567'); await page.getByLabel('Número de cargos en esta vigencia').fill('8');
  await capture(page, '06-edit-value');
  await page.getByRole('button', { name: 'Guardar valor' }).click(); await expect(page.getByRole('dialog')).toHaveCount(0);
  await page.getByRole('button', { name: `Publicar ${next}`, exact: true }).click();
  await expect(page.getByText(/Publicar en el sistema no acredita un decreto/)).toBeVisible();
  await page.getByRole('button', { name: 'Confirmar publicación' }).click();
  await expect(page.getByRole('button', { name: `Publicar ${next}`, exact: true })).toHaveCount(0);
  const saved = (await (await page.request.get(`${api}/vigencias/${target.id}`, { headers })).json()).data;
  expect(saved.estado).toBe('vigente'); expect(saved.items.find((item: { codigo: string }) => item.codigo === '909').asignacion_basica_mensual).toBe('1234567');
  const after = (await (await page.request.get(`${api}/vigencias/${base.id}`, { headers })).json()).data.items;
  expect(after).toEqual(historical);
  await page.setViewportSize({ width: 360, height: 800 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  expect(await region.evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true);
  await region.evaluate((element) => { element.scrollLeft = 0; }); await capture(page, '07-mobile');
});

test('concurrencia real: año único, cálculo único, edición y publicación no duplicadas', async ({ page }) => {
  const session = await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026'); const headers = sessionHeaders(session.token);
  const years = (await (await page.request.get(`${api}/vigencias`, { headers })).json()).data;
  const base = years.find((year: { anio: number }) => year.anio === 2025);
  const next = Math.max(...years.map((year: { anio: number }) => year.anio)) + 1;
  const data = { anio: next, fuente: 'Concurrencia sintética E2E', vigencia_base_id: base.id, base_revision: base.revision };
  const creations = await Promise.all([1, 2].map(() => page.request.post(`${api}/vigencias`, { headers, data })));
  expect(creations.map((response) => response.status()).sort()).toEqual([201, 409]);
  const target = (await creations.find((response) => response.status() === 201)!.json()).data;
  const calculation = { revision: target.revision, vigencia_base_id: base.id, base_revision: base.revision, porcentaje: '7', confirmado: true };
  const results = await Promise.all([1, 2].map(() => page.request.post(`${api}/vigencias/${target.id}/calcular`, { headers, data: calculation })));
  expect(results.map((response) => response.status()).sort()).toEqual([200, 409]);
  let fresh = (await (await page.request.get(`${api}/vigencias/${target.id}`, { headers })).json()).data;
  const item = fresh.items[0];
  const edits = await Promise.all(['1111111', '2222222'].map((value) => page.request.patch(`${api}/items/${item.id}`, { headers, data: { revision: item.revision, numero_cargos: 9, asignacion_basica_mensual: value } })));
  expect(edits.map((response) => response.status()).sort()).toEqual([200, 409]);
  fresh = (await (await page.request.get(`${api}/vigencias/${target.id}`, { headers })).json()).data;
  const publish = await Promise.all([1, 2].map(() => page.request.post(`${api}/vigencias/${target.id}/publicar`, { headers, data: { revision: fresh.revision, confirmado: true } })));
  expect(publish.map((response) => response.status()).sort()).toEqual([200, 409]);
});

for (const account of [{ username: 'E2E-SECRETARIA', password: 'E2ESecretaria2026', home: /\/admin\/dashboard/ }, { username: 'E2E-INCOMPLETE', password: 'E2EIncomplete2026', home: /\/app\/inicio/ }]) {
  test(`${account.username}: menú oculto, ruta protegida y API 403`, async ({ page }) => {
    const session = await browserSession(page, account.username, account.password);
    await expect(page.getByRole('link', { name: 'Escala salarial', exact: true })).toHaveCount(0);
    await page.goto('/admin/escala-salarial'); await expect(page).toHaveURL(account.home);
    const response = await page.request.get(api, { headers: sessionHeaders(session.token) }); expect(response.status()).toBe(403);
  });
}
