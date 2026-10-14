"""Read-only QA of synthetic template PDFs: every page, source text and image geometry."""
import argparse
import hashlib
import json
import re
from pathlib import Path

import pypdfium2 as pdfium

parser = argparse.ArgumentParser()
parser.add_argument('--directory', type=Path, required=True)
parser.add_argument('--renders', type=Path, required=True)
args = parser.parse_args()
args.renders.mkdir(parents=True, exist_ok=True)
manifest = json.loads((args.directory / 'manifest.json').read_text(encoding='utf-8'))
results = []
flat = lambda s: re.sub(r'\s+', '', s)
for name, record in manifest.items():
    path = args.directory / f'{name}.pdf'
    assert hashlib.sha256(path.read_bytes()).hexdigest() == record['sha256'], name
    document = pdfium.PdfDocument(str(path))
    assert len(document) == record['paginas'], name
    body = ''
    for index, page in enumerate(document):
        width, height = page.get_size()
        assert abs(width - 595.28) < 1 and abs(height - 841.89) < 1, (name, 'A4')
        text = page.get_textpage().get_text_range()
        for needle in ['BORRADOR', 'SIN VALIDEZ OFICIAL', 'Espacio de verificación reservado', '892.099.324-3', f'Página {index + 1} de {len(document)}']:
            assert flat(needle) in flat(text), (name, index + 1, needle)
        images = [obj for obj in page.get_objects() if isinstance(obj, pdfium.PdfImage)]
        assert len(images) == 2, (name, index + 1, 'header images')
        for image in images:
            left, bottom, right, top = image.get_bounds()
            px, py = image.get_px_size()
            assert bottom >= height - 105 and 49 <= left < right <= 546, (name, index + 1, 'header bounds')
            assert abs((right-left)/(top-bottom) - px/py) < .02, (name, 'image stretched')
        body += page.get_textpage().get_text_bounded(49, 110, 546, height-160)
        page.render(scale=1.5).to_pil().save(args.renders / f'{name}-pagina-{index + 1}.png')
    source = args.directory / f'{name}-snapshot.json'
    functions = 0
    if source.exists():
        snapshot = json.loads(source.read_text(encoding='utf-8'))
        for function in snapshot.get('manual_funciones', {}).get('funciones', []):
            assert flat(function['descripcion']) in flat(body), (name, function['orden'], 'source function missing/altered')
            functions += 1
    results.append({'archivo': path.name, 'paginas': len(document), 'imagenes_por_pagina': 2, 'funciones_exactas': functions})
print(json.dumps({'pdfs': len(results), 'paginas': sum(r['paginas'] for r in results), 'resultados': results}, ensure_ascii=False, indent=2))
