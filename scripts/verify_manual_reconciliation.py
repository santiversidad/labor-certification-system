"""Read-only checks against captured scl_db baseline, sources and existing PDFs."""
import hashlib
import json
import re
import subprocess
from pathlib import Path

from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / 'backend/laravel-app/database/data'


def query(sql):
    result = subprocess.run(['docker', 'exec', 'scl_postgres', 'psql', '-U', 'postgres', '-d', 'scl_db', '-At', '-c', sql], capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stderr.decode('utf-8'))
    return json.loads(result.stdout.decode('utf-8'))


snapshot = query("""SELECT json_build_object(
 'fichas',(SELECT count(*) FROM manual_cargo_versiones),
 'funciones',(SELECT count(*) FROM manual_funciones_esenciales),
 'cargos',(SELECT count(*) FROM cargos),
 'version',(SELECT json_agg(json_build_object('id',id,'estado',estado,'vigencia_desde',vigencia_desde) ORDER BY id) FROM manual_funciones_versiones),
 'certificados',(SELECT json_agg(json_build_object('id',id,'hash_pdf',hash_pdf,'snapshot',snapshot_datos) ORDER BY id) FROM certificados),
 'asignaciones',(SELECT json_agg(row_to_json(fc) ORDER BY id) FROM funcionario_cargo fc),
 'importaciones',(SELECT count(*) FROM manual_importaciones))""")
(ROOT / 'tmp/reconciliation-db-after.json').write_text(json.dumps(snapshot, ensure_ascii=False, indent=2), encoding='utf-8')
before = json.loads((ROOT / 'tmp/reconciliation-db-before.json').read_text(encoding='utf-8-sig'))
# SQL result sets in the earlier baseline had no ORDER BY. Compare rows by PK;
# never reorder nested function arrays, whose order is part of the certificate.
for key in ['version', 'certificados', 'asignaciones']:
    before[key] = sorted(before[key], key=lambda row: row['id'])
assert snapshot == before, 'Cambió el estado capturado: ' + ', '.join(k for k in before if before[k] != snapshot[k])
source = json.loads((DATA / 'manual_funciones_decreto_015_2023.json').read_text(encoding='utf-8'))
byid = {r['perfil_id']: r for r in source}
stored = query("""SELECT json_agg(json_build_object('source_id',m.source_id,'metadata',m.metadata_manual,
 'funciones',(SELECT json_agg(json_build_object('orden',f.orden,'texto',f.descripcion,'grupo',f.grupo,'numero_fuente',f.numero_fuente) ORDER BY f.orden)
 FROM manual_funciones_esenciales f WHERE f.manual_cargo_version_id=m.id)) ORDER BY m.source_id) FROM manual_cargo_versiones m""")
assert len(stored) == len(source) == 344
for record in stored:
    assert record['metadata'] == byid[record['source_id']], record['source_id']
    assert record['funciones'] == byid[record['source_id']]['funciones'], record['source_id']
versions = query('SELECT json_agg(json_build_object(\'id\',id,\'estado\',estado,\'acto_fecha\',acto_fecha,\'vigencia_desde\',vigencia_desde,\'vigencia_hasta\',vigencia_hasta)) FROM manual_funciones_versiones')
assert versions == [{'id': 10, 'estado': 'borrador', 'acto_fecha': None, 'vigencia_desde': None, 'vigencia_hasta': None}]
assert query('SELECT count(*) FROM manual_funciones_comunes_nivel') == 0
pdfs = []
for cert in snapshot['certificados']:
    snap = cert['snapshot']
    sid = snap['manual']['source_id']
    path = ROOT / 'backend/laravel-app/storage/app/private/manual-demo' / (sid + '.pdf')
    digest = hashlib.sha256(path.read_bytes()).hexdigest()
    assert digest == cert['hash_pdf']
    assert hashlib.sha256((ROOT / 'output/pdf/manual-demo' / path.name).read_bytes()).hexdigest() == digest
    assert snap['schema_version'] == 2
    assert [f['descripcion'] for f in snap['funciones_especificas']] == [f['texto'] for f in byid[sid]['funciones']]
    assert snap['funciones_comunes'] == []
    document = PdfReader(path)
    text = '\n'.join(p.extract_text() for p in document.pages)
    text = re.sub(r'CERTIFICADO LABORAL TEMPORAL - continuacion|Pagina \d+ de \d+', '', text)
    normalize = lambda s: re.sub(r'\s+', ' ', s).strip()
    for f in byid[sid]['funciones']:
        assert normalize(f['texto']) in normalize(text), (sid, f['orden'])
    assert 'CERTIFICADO LABORAL TEMPORAL' in text and 'SIN VALIDEZ OFICIAL' in text
    pdfs.append({'certificado_id': cert['id'], 'source_id': sid, 'sha256': digest, 'paginas': len(document.pages), 'funciones_correctas': len(byid[sid]['funciones'])})
metadata = json.loads((DATA / 'manual_funciones_decreto_015_2023_reconciliado.json').read_text(encoding='utf-8'))['metadata']
for src in metadata['fuentes']:
    assert hashlib.sha256(Path(src['ruta']).read_bytes()).hexdigest() == src['sha256']
    if src['formato'] in ['xlsx', 'json']:
        assert hashlib.sha256((DATA / src['archivo']).read_bytes()).hexdigest() == src['sha256']
result = {'estado_persistente_igual_al_preflight': True, 'fichas_iguales_al_json_original': len(stored),
          'funciones_iguales_al_json_original': sum(len(r['funciones']) for r in stored), 'cargos': snapshot['cargos'],
          'asignaciones_historicas_intactas': len(snapshot['asignaciones']), 'snapshots_intactos': len(snapshot['certificados']),
          'importaciones_antes_despues': [before['importaciones'], snapshot['importaciones']], 'versiones': versions,
          'funciones_comunes': 0, 'pdfs_existentes_intactos': pdfs, 'fuentes_originales_intactas': True}
(ROOT / 'MANUAL_RECONCILIATION_VERIFICATION.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(json.dumps(result, ensure_ascii=False, indent=2))
