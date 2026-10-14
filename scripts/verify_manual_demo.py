"""Verify generated PDF text and render all pages, using existing structured source only."""
import hashlib
import json
import re
from pathlib import Path

import pypdfium2 as pdfium
from PIL import Image, ImageDraw
from pypdf import PdfReader

root = Path(__file__).resolve().parents[1]
app = root / 'backend/laravel-app'
source = app / 'database/data/manual_funciones_decreto_015_2023.json'
records = {r['perfil_id']: r for r in json.loads(source.read_text(encoding='utf-8'))}
out = root / 'output/pdf/manual-demo'
out.mkdir(parents=True, exist_ok=True)
previews = root / 'tmp/pdfs/manual-demo'
previews.mkdir(parents=True, exist_ok=True)
result = []
for source_id in ['MF-0001', 'MF-0228', 'MF-0229']:
    origin = app / 'storage/app/private/manual-demo' / f'{source_id}.pdf'
    target = out / origin.name
    target.write_bytes(origin.read_bytes())
    snapshot_path = origin.with_name(f'{source_id}-snapshot.json')
    snapshot = json.loads(snapshot_path.read_text(encoding='utf-8'))
    record = records[source_id]
    assert snapshot['manual']['source_id'] == source_id
    assert [f['descripcion'] for f in snapshot['funciones_especificas']] == [f['texto'] for f in record['funciones']]
    pdf = PdfReader(str(target))
    text = '\n'.join(p.extract_text() for p in pdf.pages)
    text = re.sub(r'CERTIFICADO LABORAL TEMPORAL - continuacion', '', text)
    text = re.sub(r'Pagina \d+ de \d+', '', text)
    normalize = lambda s: re.sub(r'\s+', ' ', s).strip()
    normalized = normalize(text)
    for f in record['funciones']:
        assert normalize(f['texto']) in normalized, (source_id, f['orden'], f['texto'][:100])
    assert 'CERTIFICADO LABORAL TEMPORAL' in normalized
    assert 'SIN VALIDEZ OFICIAL' in normalized
    assert 'salario' not in snapshot
    document = pdfium.PdfDocument(str(target))
    thumbnails = []
    for i in range(len(document)):
        image = document[i].render(scale=1.3).to_pil().convert('RGB')
        image.save(previews / f'{source_id}-{i+1}.png')
        image.thumbnail((306,396))
        thumbnails.append(image)
    columns = min(4,len(thumbnails))
    contact = Image.new('RGB', (columns*326, ((len(thumbnails)+columns-1)//columns)*426), '#dddddd')
    draw = ImageDraw.Draw(contact)
    for i, image in enumerate(thumbnails):
        x, y = (i%columns)*326+10, (i//columns)*426+22
        contact.paste(image,(x,y))
        draw.text((x,y-15),f'{source_id} - {i+1}',fill='black')
    contact.save(previews/f'{source_id}-contact.png')
    result.append({'source_id':source_id,'paginas':len(document),'funciones_verificadas':len(record['funciones']),
        'pdf_sha256':hashlib.sha256(target.read_bytes()).hexdigest(),'pdf':str(target),
        'ficha_id':snapshot['manual']['ficha_id'],'version_id':snapshot['manual']['version_id'],
        'snapshot_exacto':True,'prueba_desarrollo':snapshot['manual']['prueba_desarrollo']})
(root/'tmp/manual-pdf-verification.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(result,ensure_ascii=False,indent=2))
