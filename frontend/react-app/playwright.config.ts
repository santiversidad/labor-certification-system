import { defineConfig } from '@playwright/test';

// Failure page snapshots can include one-time secrets. Only explicitly masked
// screenshots are retained (verified with Playwright 1.58.2's capture guard).
process.env.PLAYWRIGHT_NO_COPY_PROMPT = '1';

export default defineConfig({
  testDir: './e2e',
  testIgnore: process.env.E2E_SCREENSHOTS ? [] : ['**/control-screenshots.spec.ts'],
  workers: 1,
  retries: 0,
  timeout: 120_000,
  expect: { timeout: 20_000 },
  use: { baseURL: process.env.E2E_BASE_URL ?? 'http://e2e-web:3200', channel: process.env.PLAYWRIGHT_CHANNEL || undefined, trace: 'off' },
  reporter: 'list',
});
