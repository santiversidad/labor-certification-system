// GET-only benchmark. Credentials/bodies are never written to the results.
import { spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const args = process.argv.slice(2);
const option = (key, fallback) => args.includes(key) ? args[args.indexOf(key) + 1] : fallback;
const base = option('--base', 'http://127.0.0.1:8280');
const samples = Number(option('--samples', '20'));
const container = option('--container', '');
const cookie = option('--cookie', '');
const origin = option('--origin', 'http://localhost:3200');
const output = option('--output', 'output/performance/benchmark.json');
const plan = JSON.parse(readFileSync(option('--plan', new URL('./safe-get-plan.json', import.meta.url)), 'utf8'));
if (samples < 10 || samples > 100) throw new Error('Use 10–100 measured requests per endpoint.');
const percentile = (values, q) => values.toSorted((a, b) => a - b)[Math.ceil(values.length * q) - 1];
function request(path) {
  const curlArgs = ['--silent', '--show-error', '--max-time', '40', '--noproxy', '*', '--output', container ? '/dev/null' : (process.platform === 'win32' ? 'NUL' : '/dev/null'),
    '--write-out', '%{json}', '-H', 'Accept: application/json', '-H', `Origin: ${origin}`];
  if (cookie) curlArgs.push('--cookie', cookie);
  const hostHeader = option('--host-header', '');
  if (hostHeader) curlArgs.push('-H', `Host: ${hostHeader}`);
  if (args.includes('--profile')) curlArgs.push('-H', 'X-Performance-Probe: 1');
  curlArgs.push(base + path);
  const command = container ? 'docker' : (process.platform === 'win32' ? 'curl.exe' : 'curl');
  const result = spawnSync(command, container ? ['exec', container, 'curl', ...curlArgs] : curlArgs, { encoding: 'utf8', maxBuffer: 1024 * 1024 });
  if (result.status !== 0) return { error: result.stderr.trim(), status: null };
  const raw = JSON.parse(result.stdout);
  return { status: raw.http_code, dns_ms: raw.time_namelookup * 1000, connect_ms: raw.time_connect * 1000,
    ttfb_ms: raw.time_starttransfer * 1000, total_ms: raw.time_total * 1000, bytes: raw.size_download };
}
const results = { measured_at: new Date().toISOString(), base, container, samples, method: 'GET', endpoints: [] };
mkdirSync(dirname(output), { recursive: true });
for (const entry of plan) {
  if (!entry.path.startsWith('/') || entry.path.includes('://')) throw new Error('Only relative GET paths accepted.');
  const first = request(entry.path);
  const warm = Array.from({ length: samples }, () => request(entry.path));
  const successful = warm.filter(r => r.status === entry.expected_status);
  const summarize = (key) => {
    const values = successful.map(r => r[key]);
    return values.length ? { min: Math.min(...values), p50: percentile(values, .5), p95: percentile(values, .95), max: Math.max(...values) } : null;
  };
  const row = { ...entry, first, warm, statuses: Object.fromEntries([...new Set(warm.map(r => r.status))].map(status => [status, warm.filter(r => r.status === status).length])),
    valid_samples: successful.length, ttfb_ms: summarize('ttfb_ms'), total_ms: summarize('total_ms') };
  results.endpoints.push(row);
  writeFileSync(output, JSON.stringify(results, null, 2) + '\n');
  console.log(`${entry.group} ${entry.path} status=${JSON.stringify(row.statuses)} p50=${row.total_ms?.p50.toFixed(1) ?? 'INVALID'} p95=${row.total_ms?.p95.toFixed(1) ?? 'INVALID'} ms`);
  // Preserve real rate limits (120/min authenticated); sleep between groups.
  const pause = Number(option('--pause-ms', '11000'));
  if (pause) Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, pause);
}
if (results.endpoints.some(r => r.valid_samples !== samples)) process.exitCode = 1;
