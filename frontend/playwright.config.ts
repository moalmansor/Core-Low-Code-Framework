import { defineConfig, devices } from '@playwright/test'

/**
 * End-to-end tests run against a real backend (php artisan serve or the
 * Docker stack) on a freshly migrated and seeded database. CI starts the
 * server; locally: `LCF_E2E_URL=http://127.0.0.1:8000 npm run e2e`.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 120_000, // sign-ins may wait for a fresh TOTP step
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.LCF_E2E_URL ?? 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH } : {},
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
