import { readFileSync, writeFileSync, existsSync, unlinkSync } from 'node:fs';
const file='/app/vite.config.ts', backup='/tmp/performance-vite-original.ts';
if(process.argv[2]==='remove') { if(existsSync(backup)){writeFileSync(file,readFileSync(backup));unlinkSync(backup);} process.exit(); }
if(existsSync(backup)) throw new Error('Vite probe already installed.');
let source=readFileSync(file,'utf8');
writeFileSync(backup,source);
const probe=`
const lag = monitorEventLoopDelay({resolution:20}); lag.enable();
const recordProxy = (proxy: any) => {
  proxy.on('proxyReq', (upstream: any, req: any) => { if(req.performanceProbe) { const p=req.performanceProbe; p.proxy_start = performance.now(); p.reused_socket=!upstream.socket?.connecting; upstream.socket?.once('lookup',()=>p.lookup_ms=performance.now()-p.proxy_start); upstream.socket?.once('connect',()=>p.connect_ms=performance.now()-p.proxy_start); } });
  proxy.on('proxyRes', (response: any, req: any) => { if(req.performanceProbe) { req.performanceProbe.proxy_headers = performance.now(); req.performanceProbe.php = response.headers['server-timing']; } });
};
const performancePlugin = () => ({ name: 'temporary-performance-probe', configureServer(server: any) {
  server.middlewares.use((req: any, res: any, next: any) => {
    if(req.headers['x-performance-probe'] === '1') {
      req.performanceProbe = {received:performance.now()};
      res.on('finish', () => {const p=req.performanceProbe; console.log('PERF_PROXY '+JSON.stringify({status:res.statusCode,dispatch_ms:p.proxy_start-p.received,lookup_ms:p.lookup_ms,connect_ms:p.connect_ms,reused_socket:p.reused_socket,upstream_headers_ms:p.proxy_headers-p.proxy_start,total_ms:performance.now()-p.received,php:p.php,event_loop_max_ms:lag.max/1e6,event_loop_p99_ms:lag.percentile(99)/1e6}));lag.reset();});
    }
    next();
  });
} });
`;
source=source.replace("import react from '@vitejs/plugin-react'", "import react from '@vitejs/plugin-react'\nimport { performance, monitorEventLoopDelay } from 'node:perf_hooks'" + probe);
source=source.replace('plugins: [react()]','plugins: [react(), performancePlugin()]');
source=source.replace('target: process.env.VITE_API_PROXY_TARGET, changeOrigin: false','target: process.env.VITE_API_PROXY_TARGET, changeOrigin: false, configure: recordProxy');
source=source.replace('target: proxyTarget, changeOrigin: false, agent: proxyAgent','target: proxyTarget, changeOrigin: false, agent: proxyAgent, configure: recordProxy');
writeFileSync(file,source);
