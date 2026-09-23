import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  testIgnore: process.env.E2E_SCREENSHOTS ? [] : ['**/control-screenshots.spec.ts'],
  workers: 1,
  retries: 0,
  timeout: 120_000,
  expect: { timeout: 20_000 },
  use: { baseURL: process.env.E2E_BASE_URL ?? 'http://e2e-web:3000', channel: process.env.PLAYWRIGHT_CHANNEL || undefined, trace: 'retain-on-failure' },
  reporter: 'list',
});
