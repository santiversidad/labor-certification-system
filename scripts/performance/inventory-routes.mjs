import { spawnSync } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
const container = process.argv[2] ?? 'scl_ct_app';
const target = process.argv[3] ?? 'output/performance';
const run = spawnSync('docker', ['exec', container, 'php', 'artisan', 'route:list', '--json'], {encoding: 'utf8'});
if (run.status !== 0) throw new Error(run.stderr || run.stdout);
const routes = JSON.parse(run.stdout);
const group = uri => {
  if (uri === '/' || uri === 'up' || uri.includes('validar')) return 'Públicas';
  if (/^(login|logout|sanctum)|\/auth\//.test(uri)) return 'Autenticación';
  if (uri.includes('/admin/')) return 'Admin';
  if (uri.includes('/secretaria/')) return 'Secretario';
  if (uri.includes('/mi-certificacion/')) return 'Funcionario';
  for (const [fragment, title] of Object.entries({'manual-funciones':'Manual', 'certificados':'Certificados', 'pagos':'Pagos', 'escala-salarial':'Escala salarial', 'configuracion':'Configuración', 'auditoria':'Auditoría', 'reportes':'Reportes', 'funcionarios':'Funcionarios', 'solicitudes':'Solicitudes', 'cargos':'Cargos', 'actuaciones-administrativas':'Actuaciones administrativas'})) if (uri.includes(fragment)) return title;
  return 'Demás APIs';
};
mkdirSync(target, {recursive:true});
writeFileSync(`${target}/routes-active.json`, JSON.stringify(routes, null, 2));
let markdown = '# Inventario de rutas activas CT\n\nGenerado con `php artisan route:list --json`. Las categorías son funcionales; el acceso efectivo también depende de políticas del controlador, identidad, MFA y permisos.\n';
for (const name of [...new Set(routes.map(r=>group(r.uri)))]) {
  const items = routes.filter(r=>group(r.uri) === name);
  markdown += `\n## ${name} (${items.length})\n\n|Método|Ruta|Acción|Middleware|\n|---|---|---|---|\n`;
  for (const r of items) markdown += `|${r.method.replaceAll('|', ',')}|\`${r.uri}\`|${r.action.replaceAll('App\\Http\\Controllers\\Api\\V1\\', '')}|${r.middleware.join(', ')}|\n`;
}
writeFileSync(`${target}/routes-inventory.md`, markdown);
const safe = routes.filter(r=>r.method.includes('GET') && !r.uri.startsWith('api/_diagnostics') && r.uri !== '/');
writeFileSync(`${target}/all-get-unauthenticated.json`, JSON.stringify(safe.map(r=>({group:group(r.uri),path:'/'+r.uri.replace(/\{[^}]+\}/g, '999999999'),expected_status: r.uri==='up'?200 : r.uri==='sanctum/csrf-cookie'?204 : r.uri.includes('validar')?404:401})),null,2));
console.log(JSON.stringify({routes:routes.length, safe_gets:safe.length, categories:Object.fromEntries([...new Set(routes.map(r=>group(r.uri)))].map(g=>[g,routes.filter(r=>group(r.uri)===g).length]))}));
