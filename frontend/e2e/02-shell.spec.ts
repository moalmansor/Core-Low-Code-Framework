import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

const secret = () => readFileSync('e2e/.secret', 'utf8').trim()

test('sign-in requires the second factor and the shell switches to Arabic right-to-left', async ({ page }) => {
  const problems = watchConsole(page)
  await signIn(page, secret())
  await expect(page.getByTestId('sidebar')).toBeVisible()
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')

  await page.getByTestId('language-switcher').click()
  await page.getByRole('option', { name: 'العربية' }).click()
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar')
  await expect(page.getByTestId('sidebar')).toContainText('المستخدمون')

  // The preference is stored server-side and survives a reload.
  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')

  await page.getByTestId('language-switcher').click()
  await page.getByRole('option', { name: 'English' }).click()
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
  expect(problems).toEqual([])
})

test('wrong passwords are refused with a generic message', async ({ page }) => {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('root@e2e.test')
  await page.getByTestId('login-password').locator('input').fill('not-the-password')
  await page.getByTestId('login-submit').click()
  await expect(page.getByTestId('login-error')).toBeVisible()
  await expect(page).toHaveURL(/\/login/)
})

test('pages are served with a strict Content-Security-Policy', async ({ page }) => {
  const response = await page.goto('/login')
  const csp = response!.headers()['content-security-policy']!
  expect(csp).toContain("script-src 'self' 'nonce-")
  expect(csp).not.toContain('unsafe-inline')
  expect(response!.headers()['x-frame-options']).toBe('DENY')
})
