import { expect, test, type Page } from '@playwright/test';
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { getDocument } from 'pdfjs-dist/legacy/build/pdf.mjs';
import { apiSession, browserSession, sessionHeaders } from './security-session';
import { selectEmployment } from './dependency-selection';
import type { TemplateDetail } from '../src/features/plantillas-certificados/types/template.types';

const api = 'http://e2e-api:8280/api/v1';
const root = api + '/admin/plantillas-certificados';
const evidence = 'evidence/certificate-template-editor';
const hash = (bytes: Buffer) => createHash('sha256').update(bytes).digest('hex');
async function capture(page: Page, name: string) {
  await mkdir(evidence, { recursive: true });
  await page.screenshot({ path: `${evidence}/${name}.png`, fullPage: true, animations: 'disabled' });
}
async function open(page: Page, title: string) { const summary = page.locator('summary').filter({ hasText: new RegExp(`^${title}$`) }); if (!(await summary.evaluate((element) => element.parentElement!.hasAttribute('open')))) await summary.click(); }
async function pdfText(bytes: Buffer) {
  const task = getDocument({ data: new Uint8Array(bytes) }); const doc = await task.promise; const pages: string[] = [];
  for (let i = 1; i <= doc.numPages; i++) { const text = await (await doc.getPage(i)).getTextContent(); pages.push(text.items.flatMap((item) => 'str' in item ? [item.str] : []).join(' ')); }
  await task.destroy(); return pages;
}
test.use({ viewport: { width: 1440, height: 1000 }, timezoneId: 'America/Bogota', locale: 'es-CO' });

test.describe.serial('Editor institucional y preservación documental', () => {
  let version: TemplateDetail; let employee = ''; let certificateId = 0; let oldPdfHash = ''; let oldSnapshot = '';
  test('admin edita bloques, variables, logos y firmas; revisa PDF real y publica', async ({ page }) => {
    const timings: { path: string; method: string; ms: number; status: number }[] = [];
    const starts = new Map<object, number>();
    page.on('request', (request) => { if (request.url().includes('/plantillas-certificados')) starts.set(request, Date.now()); });
    page.on('response', (response) => { const start = starts.get(response.request()); if (start) timings.push({ path: new URL(response.url()).pathname.replace(/\/\d+(?=\/|$)/g, '/:id'), method: response.request().method(), ms: Date.now() - start, status: response.status() }); });
    const session = await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026'); const headers = sessionHeaders(session.token);
    expect(session.user.permisos).toHaveLength(37);
    await page.getByRole('link', { name: 'Plantillas de certificados', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Plantillas de certificados', exact: true })).toBeVisible(); await capture(page, '01-listado');
    await page.getByRole('region', { name: 'Certificado con funciones' }).getByRole('button', { name: 'Crear versión', exact: true }).click();
    await expect(page.getByLabel('Título del certificado')).toBeVisible();
    await page.getByLabel('Título del certificado').fill('CERTIFICACIÓN SINTÉTICA E2E');
    await page.getByRole('button', { name: 'Agregar párrafo' }).click(); await page.getByLabel('Texto del párrafo 3').fill('Documento de prueba de ');
    await page.getByRole('region', { name: 'Párrafo 3', exact: true }).getByRole('button', { name: 'Insertar variable' }).click();
    await page.getByLabel('Buscar variable').fill('Nombre completo'); await page.getByRole('button', { name: /Nombre completo/ }).click();
    await expect(page.getByLabel('Texto del párrafo 3')).toHaveValue('Documento de prueba de [Nombre completo]');
    await page.getByRole('button', { name: 'Subir párrafo 3' }).click();
    await open(page, 'Tipografía'); await page.getByLabel('Familia tipográfica').selectOption('DejaVu Serif');
    await open(page, 'Firma institucional'); await page.getByLabel('Nombre del firmante', { exact: true }).fill('Firmante sintético E2E');
    await open(page, 'Firmas adicionales'); await page.getByRole('button', { name: 'Agregar firma adicional' }).click(); await page.getByLabel('Etiqueta adicional 1').fill('Revisó'); await page.getByLabel('Nombre adicional 1').fill('Revisor sintético E2E');
    await open(page, 'Identidad visual'); await page.getByLabel('Procedencia de la nueva imagen izquierda').fill('Imagen sintética para validación E2E');
    // A tiny generated PNG fixture contains no institutional/person data.
    const png = await page.evaluate(() => { const canvas = document.createElement('canvas'); canvas.width = canvas.height = 200; const ctx = canvas.getContext('2d')!; ctx.fillStyle = '#173E6C'; ctx.fillRect(0, 0, 200, 200); ctx.fillStyle = '#FFF'; ctx.font = '20px sans-serif'; ctx.fillText('SINTÉTICO', 35, 105); return canvas.toDataURL().split(',')[1]; });
    await page.getByLabel('Reemplazar escudo').setInputFiles({ name: 'sintetico.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64') });
    await expect(page.getByText('Validando imagen…')).toHaveCount(0); await expect(page.getByRole('button', { name: 'Guardar borrador' })).toBeEnabled();
    const saved = page.waitForResponse((r) => /plantillas-certificados\/versiones\/\d+$/.test(r.url()) && r.request().method() === 'PUT');
    await page.getByRole('button', { name: 'Guardar borrador' }).click(); expect((await saved).status()).toBe(200); version = (await (await saved).json()).data;
    await expect(page.getByRole('button', { name: 'Publicar versión' })).toBeDisabled();
    await page.getByRole('button', { name: 'Generar vista previa' }).click(); await expect(page.getByRole('img', { name: /PDF sintético/ })).toBeVisible(); await expect(page.getByText(/^Página 1 de \d+$/)).toBeVisible();
    await expect(page.locator('canvas')).toHaveAttribute('aria-busy', 'false');
    await expect.poll(() => page.locator('canvas').evaluate((canvas: HTMLCanvasElement) => { const pixels = canvas.getContext('2d')!.getImageData(0, 0, canvas.width, canvas.height).data; let ink = 0; for (let i = 0; i < pixels.length; i += 4) if (pixels[i] < 220 && pixels[i + 3] > 0) ink++; return ink > 200; })).toBe(true);
    await capture(page, '02-editor-pdf');
    const download = page.waitForEvent('download'); await page.getByRole('link', { name: 'Descargar vista previa' }).click(); const pdf = await readFile((await (await download).path())!);
    expect((await pdfText(pdf)).join(' ')).toContain('CERTIFICACIÓN SINTÉTICA E2E');
    const counts = await page.request.get(`${api}/solicitudes`, { headers }); expect(counts.status()).toBe(200);
    await page.getByRole('button', { name: 'Publicar versión' }).click(); await capture(page, '03-confirmacion');
    await page.getByRole('button', { name: 'Confirmar publicación' }).click(); await expect(page.getByText(/Esta versión es inmutable/)).toBeVisible();
    const current = (await (await page.request.get(`${root}/versiones/${version.id}`, { headers })).json()).data; expect(current.estado).toBe('publicada');
    await capture(page, '04-publicada');
    await writeFile(`${evidence}/performance.json`, JSON.stringify({ environment: 'Isolated synthetic E2E, Windows Docker bind mount; not a production SLA', requests: timings }, null, 2));
  });
  test('nuevo certificado congela plantilla y funciones exactas de la ficha', async ({ page }) => {
    await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026');
    employee = `93${Date.now()}`; await page.goto('/admin/funcionarios/nuevo'); await page.getByLabel('Número de cédula').fill(employee); await page.getByLabel('Nombres', { exact: true }).fill('Persona'); await page.getByLabel('Apellidos', { exact: true }).fill('Sintética Plantilla E2E'); await selectEmployment(page, 'Profesional E2E · E2E/01', 'Autoservicio E2E');
    await page.getByRole('button', { name: 'Guardar funcionario' }).click(); await expect(page).toHaveURL(/\/admin\/funcionarios\/\d+$/);
    await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click(); await browserSession(page, employee, employee);
    await page.getByLabel('Contraseña temporal', { exact: true }).fill(employee); await page.getByLabel('Nueva contraseña', { exact: true }).fill('PlantillaSintetica2026'); await page.getByLabel('Confirmar nueva contraseña', { exact: true }).fill('PlantillaSintetica2026'); await page.getByRole('button', { name: 'Guardar y continuar' }).click(); await expect(page).toHaveURL(/\/app\/inicio/);
    await page.getByRole('button', { name: 'Solicitar con funciones' }).click();
    const generation = page.waitForResponse((r) => r.url().endsWith('/api/v1/solicitudes') && r.request().method() === 'POST'); await page.getByRole('button', { name: 'Generar certificado con funciones' }).click();
    const response = await generation; expect(response.status()).toBe(201); const issued = (await response.json()).data; certificateId = issued.certificado.id; oldSnapshot = JSON.stringify(issued.certificado.snapshot_datos);
    expect(issued.certificado.snapshot_datos.plantilla_certificado.id).toBe(version.id); expect(issued.certificado.snapshot_datos.manual_funciones.funciones).toHaveLength(24);
    const download = page.waitForEvent('download'); await page.getByRole('button', { name: 'Descargar PDF', exact: true }).click(); const bytes = await readFile((await (await download).path())!); oldPdfHash = hash(bytes); const pages = await pdfText(bytes); const text = pages.join(' ').replace(/\s+/g, ' ');
    expect(text).toContain('CERTIFICACIÓN SINTÉTICA E2E');
    for (const entry of issued.certificado.snapshot_datos.manual_funciones.funciones) expect(text).toContain(entry.descripcion.replace(/\s+/g, ' '));
    for (const text of pages) { expect(text).toContain('BORRADOR'); expect(text).toContain('892.099.324-3'); expect(text).toContain('QR'); }
    await capture(page, '05-certificado-generado');
  });
  test('segunda publicación conserva el PDF y snapshot anteriores e historial', async ({ page }) => {
    const session = await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026'); const headers = sessionHeaders(session.token);
    const created = await page.request.post(`${root}/${version.plantilla_id}/versiones`, { headers, data: {} }); expect(created.status()).toBe(201); const next = (await created.json()).data;
    next.contenido.title = 'SEGUNDA VERSIÓN SINTÉTICA';
    const saved = await page.request.put(`${root}/versiones/${next.id}`, { headers, data: { revision: next.revision, contenido: next.contenido } }); expect(saved.status()).toBe(200); const fresh = (await saved.json()).data;
    const published = await page.request.post(`${root}/versiones/${next.id}/publicar`, { headers, data: { revision: fresh.revision, confirmado: true } }); expect(published.status()).toBe(200);
    const old = (await (await page.request.get(`${api}/certificados/${certificateId}`, { headers })).json()).data; expect(JSON.stringify(old.snapshot_datos)).toBe(oldSnapshot);
    const pdf = await page.request.get(`${api}/certificados/${certificateId}/descargar`, { headers }); expect(pdf.status()).toBe(200); expect(hash(await pdf.body())).toBe(oldPdfHash);
    await page.goto('/admin/plantillas-certificados'); await page.getByRole('region', { name: 'Certificado con funciones' }).getByRole('button', { name: 'Historial' }).click(); await expect(page.getByText('Versión 1 · archivada')).toBeVisible(); await expect(page.getByText('Versión 2 · publicada')).toBeVisible(); await capture(page, '06-historial');
  });
  test('concurrencia real conserva un borrador y una publicación; payloads maliciosos fallan', async ({ page, request }) => {
    const session = await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026'); const headers = sessionHeaders(session.token);
    // Two independent authenticated sessions exercise PostgreSQL row locks.
    const users = (await (await page.request.get(`${api}/admin/usuarios-internos?q=e2e-template-concurrent`, { headers })).json()).data;
    const concurrent = users.find((user: { username: string }) => user.username === 'e2e-template-concurrent'); expect(concurrent).toBeTruthy();
    if (!concurrent.roles.includes('admin')) expect((await page.request.post(`${api}/admin/usuarios-internos/${concurrent.id}/cambiar-rol`, { headers, data: { role: 'admin' } })).status()).toBe(200);
    const second = await apiSession(request, 'E2E-TEMPLATE-CONCURRENT', 'E2EAdminClave2026'); const other = sessionHeaders(second.token);
    const results = await Promise.all([page.request.post(`${root}/1/versiones`, { headers, data: {} }), request.post(`${root}/1/versiones`, { headers: other, data: {} })]); expect(results.map((r) => r.status()).sort()).toEqual([201, 409]); const draft = (await results.find((r) => r.status() === 201)!.json()).data;
    const malicious = structuredClone(draft.contenido); malicious.functions = { title: 'INYECCIÓN', size: 10, line_height: 1.4 };
    expect((await page.request.put(`${root}/versiones/${draft.id}`, { headers, data: { revision: draft.revision, contenido: malicious } })).status()).toBe(422);
    malicious.functions = null; malicious.paragraphs[0].runs = [{ type: 'variable', variable: 'funcionario.password' }]; expect((await page.request.put(`${root}/versiones/${draft.id}`, { headers, data: { revision: draft.revision, contenido: malicious } })).status()).toBe(422);
    const publication = await Promise.all([page.request.post(`${root}/versiones/${draft.id}/publicar`, { headers, data: { revision: draft.revision, confirmado: true } }), request.post(`${root}/versiones/${draft.id}/publicar`, { headers: other, data: { revision: draft.revision, confirmado: true } })]); expect(publication.map((r) => r.status()).sort()).toEqual([200, 409]);
    const preview = await page.request.post(`${root}/versiones/${draft.id}/preview`, { headers, data: { escenario: 'breve' } }); expect(preview.status()).toBe(200); const text = (await pdfText(await preview.body())).join(' '); expect(text).toContain('SIN FUNCIONES'); expect(text).not.toContain('Función sintética');
  });
  test('editor responde a 360, 768, 1024 y 1440; PDF extenso navega páginas con teclado', async ({ page }) => {
    const session = await browserSession(page, 'E2E-ADMIN', 'E2EAdminClave2026'); const headers = sessionHeaders(session.token); const created = await page.request.post(`${root}/2/versiones`, { headers, data: {} }); expect(created.status()).toBe(201); const draft = (await created.json()).data;
    await page.goto(`/admin/plantillas-certificados/versiones/${draft.id}`);
    for (const width of [360, 768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 900 }); await expect(page.getByLabel('Título del certificado')).toBeVisible(); await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true); await capture(page, `07-editor-${width}`);
    }
    await page.getByLabel('Extensión del ejemplo').selectOption('extenso'); await page.getByRole('button', { name: 'Generar vista previa' }).click(); await expect(page.getByText(/^Página 1 de \d+$/)).toBeVisible(); await page.getByRole('button', { name: 'Página siguiente' }).focus(); await page.keyboard.press('Enter'); await expect(page.getByText(/^Página 2 de \d+$/)).toBeVisible(); await capture(page, '08-pdf-extenso');
    await page.setViewportSize({ width: 360, height: 900 }); await expect(page.getByRole('tab', { name: 'Edición', exact: true })).toBeVisible(); await page.getByRole('tab', { name: 'Edición', exact: true }).click(); await expect(page.getByLabel('Título del certificado')).toBeVisible(); await page.getByRole('tab', { name: 'Vista previa', exact: true }).click(); await expect(page.getByRole('img', { name: /PDF sintético/ })).toBeVisible(); await capture(page, '09-pdf-movil');
  });
});
for (const account of [{ username: 'E2E-SECRETARIA', password: 'E2ESecretaria2026', home: /\/admin\/dashboard/ }, { username: 'E2E-INCOMPLETE', password: 'E2EIncomplete2026', home: /\/app\/inicio/ }]) {
  test(`${account.username}: plantilla oculta, ruta protegida y API 403`, async ({ page }) => {
    const session = await browserSession(page, account.username, account.password); await expect(page.getByRole('link', { name: 'Plantillas de certificados', exact: true })).toHaveCount(0);
    await page.goto('/admin/plantillas-certificados'); await expect(page).toHaveURL(account.home);
    expect((await page.request.get(root, { headers: sessionHeaders(session.token) })).status()).toBe(403);
  });
}
test('producción conserva CSP estricta y dibuja PDF con worker local', async ({ page }) => {
  const violations: string[] = []; await page.addInitScript(() => document.addEventListener('securitypolicyviolation', (event) => { (window as unknown as { templateViolations: string[] }).templateViolations ??= []; (window as unknown as { templateViolations: string[] }).templateViolations.push(event.violatedDirective); }));
  page.on('console', (message) => { if (message.type() === 'error' && /Content Security Policy|unsafe-eval|worker-src/.test(message.text())) violations.push(message.text()); });
  await page.goto('http://e2e-production/login'); await page.getByLabel('Usuario', { exact: true }).fill('E2E-ADMIN'); await page.getByLabel('Contraseña', { exact: true }).fill('E2EAdminClave2026'); await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/\/seguridad\/mfa/);
  const { createHmac } = await import('node:crypto'); const code = createHmac('sha256', 'e2e-only-fixture-seed').update('e2e-admin:740').digest('hex'); await page.getByRole('button', { name: 'Usar código de recuperación' }).click(); await page.getByLabel('Código de recuperación', { exact: true }).fill(code); await page.getByRole('button', { name: 'Verificar y continuar' }).click(); await expect(page).toHaveURL(/\/admin\/dashboard/);
  // Existing Zod feature detection attempts eval during login, caught by CSP.
  // Isolate that baseline probe; the editor/PDF must introduce no violations.
  const baselineProbes = await page.evaluate(() => { const state = window as unknown as { templateViolations?: string[] }; const baseline = state.templateViolations ?? []; state.templateViolations = []; return baseline; });
  await page.getByRole('link', { name: 'Plantillas de certificados', exact: true }).click(); await page.getByRole('region', { name: 'Certificado con funciones' }).getByRole('button', { name: 'Continuar borrador' }).click(); await page.getByRole('button', { name: 'Generar vista previa' }).click(); await expect(page.getByText(/^Página 1 de \d+$/)).toBeVisible();
  await expect(page.locator('canvas')).toHaveAttribute('aria-busy', 'false'); expect(violations).toEqual([]); expect(await page.evaluate(() => (window as unknown as { templateViolations?: string[] }).templateViolations ?? [])).toEqual([]); await capture(page, '10-produccion-csp');
  await writeFile(`${evidence}/production-csp.json`, JSON.stringify({ baseline_login_probe_directives: baselineProbes, editor_pdf_directives: [], worker: 'same-origin ES module, application/javascript', wasm: false }, null, 2));
});
