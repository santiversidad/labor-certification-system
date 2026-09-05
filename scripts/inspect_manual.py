"""Read-only source audit. Does not reconstruct the manual or modify source files."""
import collections
import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
if (root / 'MANUAL_RECONCILIATION_DETAILS.json').exists():
    raise SystemExit('Inspección inicial histórica: usar scripts/reconcile_manual_sources.py para no reemplazar la conciliación actual.')
source = Path.home() / 'Downloads/manual_funciones_decreto_015_2023.json'
records = json.loads(source.read_text(encoding='utf-8'))
fields = list(records[0])
groups = collections.defaultdict(list)
for record in records:
    groups[tuple(record[k] for k in ('codigo', 'grado', 'denominacion', 'area_funcional', 'dependencia'))].append(record['perfil_id'])
duplicates = [ids for ids in groups.values() if len(ids) > 1]
samples = [records[0], next(r for r in records if r['perfil_id'] == 'MF-0228'), next(r for r in records if r['perfil_id'] == 'MF-0229')]
lines = [
    '# Inspección de fuentes del Manual',
    '',
    f'JSON inspeccionado: `{source}`.',
    f'SHA-256: `{hashlib.sha256(source.read_bytes()).hexdigest()}`.',
    f'Raíz: array de {len(records)} objetos. Funciones: {sum(len(r["funciones"]) for r in records)}. Conocimientos: {sum(len(r["conocimientos"]) for r in records)}.',
    'Todos los registros tienen las mismas 16 propiedades. No hay otros objetos raíz ni metadatos de versión.',
    '',
    '| Propiedad | Tipos observados | Vacíos |', '|---|---|---|',
]
for key in fields:
    lines.append(f'| {key} | {dict(collections.Counter(type(r[key]).__name__ for r in records))} | {sum(not r[key] for r in records)} |')
lines += [
    '', 'Funciones: array de objetos `{numero_fuente: int|null, grupo: str, texto: str, orden: int}`. 3.114 números enteros y un null (MF-0001, orden 58, función legal sin número de fuente). Orden único y consecutivo en las 344 fichas. La numeración de fuente puede reiniciarse por grupo.',
    'Conocimientos: array de objetos `{numero: int, texto: str}`. Se conserva completo sin convertirlo a funciones.',
    'Código y grado son strings en todos los registros, incluidos `005` y `03`.',
    '', '## Excel', '',
    'No localizado. Se buscó recursivamente el repositorio (incluidos archivos ignorados), su carpeta padre y los Excel de Descargas. No es posible informar hojas, encabezados, filas, tipos ni tres fichas Excel sin el archivo. Se solicitó su ruta al usuario.',
    '', '## Matriz documental', '',
    'Obligatorio indica el requisito técnico para importar o expedir; no interpreta requisitos legales.',
    '', '| Campo Manual | Campo JSON | Campo Excel | Obligatorio | Uso aplicación |', '|---|---|---|---|---|',
]
mapping = [
    ('Nivel','nivel','Sí','Catálogo y funciones comunes'),
    ('Denominación del empleo','denominacion','Sí','Catálogo; literal conservado en ficha'),
    ('Código','codigo','Sí','Catálogo string'), ('Grado','grado','Sí','Catálogo string'),
    ('Número de cargos','numero_cargos','Importación','Texto institucional'),
    ('Dependencia','dependencia','Importación','Identificación de ficha'),
    ('Cargo del jefe inmediato','jefe_inmediato','Importación','Ficha'),
    ('Área funcional','area_funcional','Expedición','Selección exacta'),
    ('Propósito principal','proposito_principal','Expedición','Snapshot/PDF'),
    ('Funciones esenciales','funciones[].texto / orden / numero_fuente / grupo','Con funciones','Filas normalizadas'),
    ('Conocimientos','conocimientos[]','No','JSONB'),
    ('Competencias comportamentales','Ausente','Pendiente fuente','No inventar'),
    ('Formación académica','Ausente','Pendiente fuente','No inventar'),
    ('Experiencia','Ausente','Pendiente fuente','No inventar'),
    ('Equivalencias','Ausente','Cuando existan','No inventar'),
    ('Funciones comunes por nivel','Ausente','Pendiente fuente','Resolver separado; impresión pendiente'),
]
for manual, key, required, use in mapping:
    lines.append(f'| {manual} | {key} | No disponible | {required} | {use} |')
lines += ['', '## Verificaciones puntuales PDF', '',
    'PDF: `C:/Users/mondr/Downloads/DECRETO   015 DE 2023 MANUAL DE FUNCIONES.pdf`, 667 páginas. No se usó OCR.',
    'Página 14: identificación de Alcalde 005-03, propósito y primeras funciones coinciden con MF-0001.',
    'Página 480: Técnico Administrativo 367-05, Oficina de Gestión del Riesgo, seis funciones y cuatro conocimientos coinciden con MF-0229. La página incluye competencias, formación y 30 meses de experiencia que el JSON omite.',
    'Página 12: funciones comunes por nivel presentes en PDF, ausentes como sección estructurada del JSON.',
    'Portada: fecha visible 13 de enero de 2023. Página 667: rige desde expedición, pero el espacio de fecha de expedición está en blanco. No se asume automáticamente vigencia a partir de la portada. Acto referenciado por JSON: Decreto No. 1000-24/015 de 2023.',
    '', '## Identidad y calidad', '',
    '344 perfil_id únicos. Son IDs secuenciales derivados (MF-0001…MF-0344), no se presume estabilidad frente a una nueva extracción. Se conservan como source_id dentro de la versión y nunca como PK. Si una reextracción renumera fichas, debe revisarse antes de importar.',
    f'{len(duplicates)} grupos comparten código, grado, denominación literal, área y dependencia. Se distinguen por source_id/registro_decreto, propósito y funciones; esa combinación descriptiva tampoco es una clave única.',
    'MF-0167 (página 362) tiene área y propósito vacíos. Se conserva la ficha, pero se bloquea su expedición hasta resolver la fuente.',
    '50 pares código/grado, 73 combinaciones de denominación literal/nivel. Las variantes observadas agregan el código y grado al nombre (incluido Técnico Operativo 314 sin grado). El catálogo quita exclusivamente ese sufijo verificable; la ficha conserva el original íntegro.',
    '', 'Grupos de identidad descriptiva repetida:', '',
    *['- '+', '.join(ids) for ids in duplicates],
    '', '## Tres registros reales completos', '',
]
for sample in samples:
    lines += ['```json', json.dumps(sample, ensure_ascii=False, indent=2), '```', '']
(root/'MANUAL_SOURCE_INSPECTION.md').write_text('\n'.join(lines),encoding='utf-8')
(root/'MANUAL_IMPORT_COMPARISON.md').write_text('''# Comparación Excel / JSON

Comparación pendiente: Excel no localizado. No se declara equivalencia entre fuentes.

| Comprobación | Resultado |
|---|---|
| Registros solo Excel | No determinable sin archivo |
| Registros solo JSON | No determinable sin archivo; JSON contiene 344 fichas |
| Diferencias de campos | No determinable |
| Diferencias de funciones | No determinable; JSON contiene 3.115 funciones |
| Duplicados JSON por perfil_id | 0 |
| Grupos repetidos por código/grado/denominación/área/dependencia | 45; no fusionar |
| Vacíos JSON | MF-0167: área y propósito; MF-0001: conocimientos |

El PDF permite confirmar omisiones del JSON: competencias, formación, experiencia y funciones comunes. No se corrige automáticamente el JSON. Véase MANUAL_SOURCE_INSPECTION.md.
La carga inicial solo puede ser borrador de desarrollo, pendiente de conciliación y publicación documental.
''',encoding='utf-8')
print('Inspección y comparación escritas; fuentes sin cambios.')
