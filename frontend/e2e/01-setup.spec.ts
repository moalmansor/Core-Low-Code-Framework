import { expect, test } from '@playwright/test'
import { admin, artisan, fillOtp, freshTotp, watchConsole } from './support'
import { rmSync, writeFileSync } from 'node:fs'

test.beforeAll(() => {
  rmSync('.e2e-totp-step', { force: true })
})

test('first run: the setup wizard configures the system, creates the Super Admin with 2FA, then locks', async ({ page }) => {
  const problems = watchConsole(page)
  const token = artisan('setup:token').trim().split('\n').pop()!.trim()

  await page.goto('/')
  await expect(page).toHaveURL(/\/setup$/)
  await page.getByTestId('setup-token').fill(token)
  await page.getByTestId('setup-next').click()

  await page.getByTestId('system-name-en').fill('Acme Platform')
  await page.getByTestId('system-name-ar').fill('منصة أكمي')
  await page.getByTestId('setup-next').click()
  await page.getByTestId('setup-next').click() // languages: Arabic and English enabled, English default
  await page.getByTestId('setup-next').click() // regional
  await page.getByLabel(/Configure e-mail later/).check()
  await page.getByTestId('setup-next').click()

  await page.getByTestId('admin-name').fill(admin.name)
  await page.getByTestId('admin-email').fill(admin.email)
  await page.getByTestId('new-password').locator('input').fill(admin.password)
  await page.getByTestId('new-password-confirm').locator('input').fill(admin.password)
  await page.getByTestId('show-qr').click()
  const secret = (await page.getByTestId('totp-secret').textContent())!.trim()
  writeFileSync('e2e/.secret', secret)
  await fillOtp(page, 'setup-otp', await freshTotp(secret))
  await page.getByTestId('setup-complete').click()

  await expect(page.getByTestId('recovery-codes').locator('li')).toHaveCount(8)
  await page.getByTestId('open-console').click()
  await expect(page).toHaveURL(/\/admin$/)
  await expect(page.getByTestId('area-users')).toBeVisible()
  await expect(page.getByTestId('area-roles_permissions')).toBeVisible()

  // The wizard is locked for good.
  const status = await page.request.get('/api/v1/setup/status')
  expect(status.status()).toBe(404)
  expect(problems).toEqual([])
})
