import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import { Agent as HttpAgent } from 'node:http'
import { Agent as HttpsAgent } from 'node:https'

const proxyTarget = process.env.VITE_API_PROXY_TARGET
// Docker DNS lookup uses libuv's filesystem thread pool. Reuse connections so
// Windows polling cannot put every API request behind hundreds of file stats.
const proxyAgent = proxyTarget
  ? new (new URL(proxyTarget).protocol === 'https:' ? HttpsAgent : HttpAgent)({
      keepAlive: true, maxSockets: 32, maxFreeSockets: 8, timeout: 30_000,
    })
  : undefined

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: proxyTarget ? Object.fromEntries(['/api', '/sanctum'].map((path) => [path, { target: proxyTarget, changeOrigin: false, agent: proxyAgent }])) : undefined,
    watch: {
      // Keep Windows/Docker HMR while avoiding a 100 ms scan of generated files.
      usePolling: process.env.CHOKIDAR_USEPOLLING === 'true',
      interval: 1000,
      binaryInterval: 1500,
      ignored: ['**/evidence/**', '**/test-results/**', '**/playwright-report/**'],
    },
    headers: { 'X-Content-Type-Options': 'nosniff', 'X-Frame-Options': 'DENY', 'Referrer-Policy': 'strict-origin-when-cross-origin', 'Permissions-Policy': 'camera=(), microphone=(), geolocation=()' },
  },
  test: {
    include: ['src/**/*.test.{ts,tsx}'],
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    css: true,
  },
})
