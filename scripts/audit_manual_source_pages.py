"""Read-only documentary audit. Never writes to the database or the source JSON."""
import argparse
import hashlib
import json
import re
from pathlib import Path

from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
PDF_SHA = '18a3d6c33469f517f09064f2b9e02afa062323997279ef7390f94cf3bd428e35'
JSON_SHA = '261a8af4e5cc0f8aa1394dbcddfcaba7863a499f4ca98e30d20749e415bf356e'


def canonical_hash(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()


def normalize(text):
    # Comparison only: join extraction line breaks/word splits. Preserve punctuation and spelling.
    return re.sub(r'\s+', '', text)


def audit(pdf, source):
    assert hashlib.sha256(pdf.read_bytes()).hexdigest() == PDF_SHA, 'PDF original diferente'
    assert hashlib.sha256(source.read_bytes()).hexdigest() == JSON_SHA, 'JSON original diferente'
    extracted = [p.extract_text() or '' for p in PdfReader(pdf).pages]
    for n, text in enumerate(extracted, 1):
        printed = re.search(r'Página\s+(\d+)\s+de\s+667', text)
        assert (n == 1 and printed is None) or (printed and int(printed[1]) == n), ('numeración PDF', n)
    pages = [re.sub(r'Página\s+\d+\s+de\s+667', '', text) for text in extracted]
    records = json.loads(source.read_text(encoding='utf-8'))
    assert len(pages) == 667 and len(records) == 344
    results = []
    for index, record in enumerate(records):
        raw = record['pagina_inicio']
        # Approved by the user: an orphan heading does not identify the profile.
        # Locate the actual denomination in the documented range, never derive its page by index.
        start = 665 if record['perfil_id'] == 'MF-0344' else raw
        end = records[index + 1]['pagina_inicio'] if index + 1 < len(records) else 666
        text = '\n'.join(pages[start - 1:max(end, start)])
        identity = re.search(r'Denominación\s+del\s+Empleo:\s*(.*?)\s*Código:\s*(\S+)\s*Grado:\s*(\S+)', text, re.S | re.I)
        assert identity and identity[2] == record['codigo'] and identity[3] == record['grado'], record['perfil_id']
        assert normalize(identity[1]) == normalize(record['denominacion']), record['perfil_id']
        for field, pattern in [
            ('nivel', r'Nivel:\s*(.*?)Denominación'),
            ('numero_cargos', r'No\.\s*De\s*Cargos:\s*(.*?)Dependencia:'),
            ('dependencia', r'Dependencia:\s*(.*?)Cargo\s+del\s+Jefe\s+Inmediato:'),
            ('jefe_inmediato', r'Cargo\s+del\s+Jefe\s+Inmediato:\s*(.*?)(?:I{2,3}|IV)\.\s*ÁREA'),
        ]:
            match = re.search(pattern, text, re.S | re.I)
            assert match and normalize(match[1]) == normalize(record[field]), (record['perfil_id'], field)
        page = next(n for n in range(start, max(end, start) + 1) if re.search(r'Denominación\s+del\s+Empleo:', pages[n - 1], re.I))
        purpose = re.search(r'III\.\s*PROPÓSITO\s*PRINCIPAL\s*(.*?)IV\.\s*DESCRIPCIÓN', text, re.S | re.I)
        if record['proposito_principal']:
            assert purpose and normalize(purpose[1]) == normalize(record['proposito_principal']), record['perfil_id']
        area = re.search(r'(?:II|IV)\.\s*ÁREA\s*FUNCIONAL:?\s*(.*?)III\.\s*PROPÓSITO', text, re.S | re.I)
        if record['area_funcional']:
            assert area and normalize(area[1]) == normalize(record['area_funcional']), record['perfil_id']
        section = re.split(r'IV\.\s*DESCRIPCIÓN\s*DE\s*FUNCIONES\s*ESENCIALES', text, flags=re.I)[1]
        section = re.split(r'V\.\s*CONOCIMIENTOS\s*BÁSICOS\s*ESENCIALES', section, flags=re.I)[0]
        flat = normalize(section)
        offset = 0
        for function in record['funciones']:
            needle = normalize(function['texto'])
            found = flat.find(needle, offset)
            assert found >= 0, (record['perfil_id'], function['orden'])
            offset = found + len(needle)
        numbered = len(re.findall(r'^\s*\d+\.\s+', section, re.M))
        assert [int(n) for n in re.findall(r'^\s*(\d+)\.\s+', section, re.M)] == [
            f['numero_fuente'] for f in record['funciones'] if f['numero_fuente'] is not None
        ], (record['perfil_id'], 'numeración fuente')
        if record['perfil_id'] == 'MF-0001':
            # 57 numbered items + the preserved unnumbered function in group G.
            assert numbered == 57 and record['funciones'][-1]['numero_fuente'] is None
        else:
            assert numbered == len(record['funciones']), record['perfil_id']
        # Three source introductions are context, not numbered functions in the existing model.
        # Keep this distinction explicit; compare the complete remaining section, including groups.
        comparable = section
        if record['perfil_id'] == 'MF-0001':
            comparable = re.sub(r'DE ORDEN LEGAL\s*\(.*?los alcaldes tendrán las siguientes:', '', comparable, flags=re.S)
        elif record['perfil_id'] in {'MF-0061', 'MF-0062'}:
            comparable = re.sub(r'^\s*De conformidad.*?medidas:', '', comparable, flags=re.S)
        expected, group = [], ''
        for function in record['funciones']:
            if function['grupo'] != group:
                group = function['grupo']
                # Existing importer labels the bare source marker "G)" as "G) Función legal".
                expected.append('G)' if record['perfil_id'] == 'MF-0001' and group == 'G) Función legal' else group)
            number = function['numero_fuente']
            expected.append(('' if number is None else f'{number}.') + function['texto'])
        assert normalize(comparable) == normalize(''.join(expected)), (record['perfil_id'], 'sección completa')
        results.append({'source_id': record['perfil_id'], 'content_hash': canonical_hash(record),
                        'pagina_inicio': page, 'pagina_pdf': page, 'pagina_fuente_original': raw,
                        'funciones_count': len(record['funciones'])})
    assert len({r['pagina_inicio'] for r in results}) == 344
    assert sum(r['funciones_count'] for r in results) == 3115
    return {'schema': 'manual-documental-v1', 'documento': pdf.name, 'pdf_sha256': PDF_SHA,
            'json_sha256': JSON_SHA, 'paginas_pdf': 667,
            'criterio': 'Página donde aparece la denominación del empleo; decisión del usuario del 12 de octubre de 2026.',
            'comparacion': 'Identificación completa, área y propósito presentes, sección completa de funciones con numeración y grupos. Solo espacios y pies de página normalizados; introducciones no numeradas de MF-0001/MF-0061/MF-0062 documentadas aparte.',
            'fichas': results}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--pdf', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    manifest = audit(args.pdf, ROOT / 'backend/laravel-app/database/data/manual_funciones_decreto_015_2023.json')
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8', newline='\n')
    print('344 fichas, 344 páginas iniciales únicas, 3115 funciones cotejadas. Fuentes intactas.')
