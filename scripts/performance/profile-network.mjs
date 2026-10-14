import dns from 'node:dns/promises';
import http from 'node:http';
import { performance } from 'node:perf_hooks';
const host = process.argv[2] ?? 'nginx';
const measure = async (label, fn) => { const start=performance.now(); try { await fn(); return {label,ms:performance.now()-start}; } catch(e) { return {label,ms:performance.now()-start,error:e.code}; } };
const rows=[];
for(let i=0;i<10;i++) {
  rows.push(await measure('lookup_dual',()=>dns.lookup(host)));
  rows.push(await measure('lookup_ipv4',()=>dns.lookup(host,{family:4})));
  rows.push(await measure('resolve4',()=>dns.resolve4(host)));
  rows.push(await measure('resolve6',()=>dns.resolve6(host)));
}
async function request(agent, lookup) {
  const start=performance.now(); const row={};
  await new Promise((resolve,reject)=>{
    const req=http.get({host,port:80,path:'/up',agent,lookup,headers:{Host:'localhost:8280'}},res=>{
      row.status=res.statusCode; row.ttfb_ms=performance.now()-start;
      res.resume(); res.on('end',()=>{row.total_ms=performance.now()-start; resolve();});
    });
    req.on('socket',s=>{s.once('lookup',()=>row.dns_ms=performance.now()-start);s.once('connect',()=>row.connect_ms=performance.now()-start);});
    req.on('error',reject);
  });
  return row;
}
const keepalive=new http.Agent({keepAlive:true});
const ipv4lookup=(name, options, callback)=> import('node:dns').then(({default:d})=>d.lookup(name,{...options,family:4},callback));
const requests=[];
for (const [label,agent,lookup] of [['no_pool',false,undefined],['keepalive',keepalive,undefined],['ipv4',false,ipv4lookup]]) {
  for(let i=0;i<10;i++) requests.push({label,...await request(agent,lookup)});
}
keepalive.destroy();
console.log(JSON.stringify({host,dns:rows,http:requests},null,2));
