import { defineConfig, devices } from '@playwright/test'

/**
 * Browser budgets of architecture §20.2, against the Docker stack after the
 * performance dataset was built (backend/tests/Performance). CI's performance
 * workflow runs it; locally:
 * `LCF_E2E_URL=http://localhost:8080 LCF_TARGETS=../perf-targets.json npx playwright test -c playwright.perf.config.ts`.
 */
export default defineConfig({
  testDir: './e2e-perf',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 180_000,
  reporter: [['list']],
  use: {
    baseURL: process.env.LCF_E2E_URL ?? 'http://localhost:8080',
    launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH } : {},
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
