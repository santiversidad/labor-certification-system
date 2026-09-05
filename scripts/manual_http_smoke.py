"""Exercise localhost only with explicitly marked development accounts; never print credentials."""
import json
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

root = Path(__file__).resolve().parents[1]
base = 'http://localhost:8080/api/v1'

def call(path, payload=None, token=None):
    headers = {'Accept': 'application/json', 'Content-Type': 'application/json'}
    if token:
        headers['Authorization'] = 'Bearer '+token
    req = Request(base+path, data=json.dumps(payload).encode() if payload is not None else None, headers=headers)
    try:
        with urlopen(req,timeout=25) as response:
            return response.status, json.load(response)
    except HTTPError as error:
        return error.code, json.load(error)

results=[]
for source_id in ['MF-0228','MF-0229']:
    credentials=json.loads((root/f'backend/laravel-app/storage/app/private/manual-demo/acceso-{source_id}.json').read_text())
    assert credentials['documento'].startswith('TEST-MANUAL-')
    status, login=call('/auth/login',{'cedula':credentials['documento'],'password':credentials['password']})
    assert status==200,(source_id,status,login.get('code'))
    token=login['data']['token']
    status, availability=call('/mi-certificacion/disponibilidad',token=token)
    assert status==200
    status, duplicate=call('/solicitudes',{'tipo_certificado':'funciones','requiere_salario':False},token)
    assert status==409 and duplicate['code']=='MONTHLY_CERTIFICATE_LIMIT',(status,duplicate.get('code'))
    status, salary=call('/solicitudes',{'tipo_certificado':'funciones','requiere_salario':True},token)
    assert status==409 and salary['code']=='SALARIO_NO_RESOLUBLE',(status,salary.get('code'))
    call('/auth/logout',{},token)
    results.append({'source_id':source_id,'login':200,'disponibilidad':200,'segunda_solicitud':duplicate['code'],'con_salario_sin_fuente':salary['code']})
(root/'tmp/manual-http-smoke.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(results,ensure_ascii=False,indent=2))
