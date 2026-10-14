import assert from 'node:assert/strict';
import { readFile, writeFile, unlink } from 'node:fs/promises';

// Requires the guarded PHP fixture preparation and migrated CT development stack.
const session = JSON.parse(await readFile(new URL('../output/payment-phase27-session.json', import.meta.url), 'utf8'));
const api = 'http://localhost:8280/api/v1';
const results = { users: Object.fromEntries(Object.entries(session.users).map(([k, v]) => [k, v.id])), employees: session.employees, requests: [], assertions: [] };
async function call(actor, path, method = 'GET', data, status = 200) {
  const headers = { Accept: 'application/json', Authorization: `Bearer ${session.users[actor].token}` };
  if (data && !(data instanceof FormData)) headers['Content-Type'] = 'application/json';
  const r = await fetch(`${api}${path}`, { method, headers, body: data instanceof FormData ? data : data ? JSON.stringify(data) : undefined });
  assert.equal(r.status, status, `${method} ${path}: ${await r.clone().text()}`);
  return r;
}
async function configure(active) { await call('admin', '/configuracion/certificaciones', 'PATCH', { requiere_pago_certificado: active }); }
async function create(actor, tipo) { const data = (await (await call(actor, '/solicitudes', 'POST', { tipo_certificado: tipo }, 201)).json()).data; results.requests.push({ id: data.solicitud.id, actor, tipo, created: data.resultado }); return data; }
async function upload(id, content) {
  const form = new FormData(); form.append('archivo', new Blob([`%PDF-1.4\n% Evidencia FICTICIA CT ${content}\n`], { type: 'application/pdf' }), `ficticio-${content}.pdf`);
  return (await (await call('on', `/solicitudes/${id}/pago/comprobantes`, 'POST', form, 201)).json()).data;
}
const initial = (await (await call('admin', '/configuracion/certificaciones')).json()).data.requiere_pago_certificado;
try {
  await configure(false);
  for (const tipo of ['sencillo', 'funciones']) {
    const r = await create('off', tipo); assert.equal(r.resultado, 'generada'); assert.equal(r.certificado.snapshot_schema_version, 3);
    if (tipo === 'funciones') { assert.equal(r.certificado.snapshot_datos.manual_funciones.source_id, 'MF-0002'); assert.equal(r.certificado.snapshot_datos.manual_funciones.funciones.length, 11); }
  }
  assert.equal((await create('incomplete', 'sencillo')).resultado, 'generada');
  const invalid = await (await call('incomplete', '/solicitudes', 'POST', { tipo_certificado: 'funciones' }, 409)).json(); assert.equal(invalid.code, 'MANUAL_FICHA_INCOMPLETA');
  await configure(true);
  for (const tipo of ['sencillo', 'funciones']) {
    const r = await create('on', tipo); assert.equal(r.resultado, 'pendiente_pago'); assert.equal(r.certificado, undefined); const id = r.solicitud.id;
    const first = await upload(id, `${tipo}-1`); const firstFile = first.comprobantes[0].archivo_url;
    if (tipo === 'funciones') {
      await call('secretaria', `/secretaria/pagos/${id}/rechazar`, 'POST', { motivo: 'Evidencia ficticia para probar rechazo' });
      const detail = (await (await call('on', `/solicitudes/${id}`)).json()).data; assert.equal(detail.estado, 'pago_rechazado');
      const second = await upload(id, `${tipo}-2`); assert.equal(second.comprobantes.length, 2); await call('on', firstFile.slice('/api/v1'.length));
    }
    const confirmed = (await (await call('secretaria', `/secretaria/pagos/${id}/confirmar`, 'POST')).json()).data; assert.equal(confirmed.estado, 'generada');
    await call('secretaria', `/secretaria/pagos/${id}/confirmar`, 'POST');
    const pdf = Buffer.from(await (await call('on', `/solicitudes/${id}/certificado`)).arrayBuffer()); assert.equal(pdf.subarray(0, 5).toString(), '%PDF-');
    await call('off', firstFile.slice('/api/v1'.length), 'GET', undefined, 403);
    results.assertions.push({ id, state: confirmed.estado, evidence_versions: confirmed.comprobantes.length, idempotent_confirmation: true, pdf: true, ownership: true });
  }
  await writeFile(new URL('../output/payment-phase27-smoke.json', import.meta.url), JSON.stringify(results, null, 2));
  console.log(JSON.stringify(results, null, 2));
} finally {
  await configure(initial);
  for (const actor of Object.keys(session.users)) { await call(actor, '/auth/logout', 'POST'); }
  await unlink(new URL('../output/payment-phase27-session.json', import.meta.url));
}
