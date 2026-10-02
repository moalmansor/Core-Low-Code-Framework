import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support'

test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

test('administrators build the department tree and create users', async ({ page }) => {
  const problems = watchConsole(page)
  await page.getByTestId('nav-departments').click()
  await page.getByTestId('new-department').click()
  await page.getByTestId('department-code').fill('FIN')
  await page.getByTestId('department-name-en').fill('Finance')
  await page.getByTestId('department-name-ar').fill('المالية')
  await page.getByTestId('department-save').click()
  await expect(page.getByTestId('department-tree')).toContainText('Finance')

  await page.getByTestId('nav-users').click()
  await page.getByTestId('new-user').click()
  await page.getByTestId('user-name').fill('Sara Ali')
  await page.getByTestId('user-email').fill('sara@e2e.test')
  await page.getByTestId('user-save').click()
  await expect(page.locator('table')).toContainText('sara@e2e.test')
  expect(problems).toEqual([])
})

test('the unified permissions screen shows roles, grants and "view as user"', async ({ page }) => {
  const problems = watchConsole(page)
  await page.getByTestId('nav-roles_permissions').click()
  await expect(page.getByTestId('role-list')).toContainText('Super Admin')
  await expect(page.getByTestId('perm-system.manage_users')).toBeVisible()
  await page.getByRole('tab', { name: 'View as user' }).click()
  await page.getByPlaceholder('Find a user…').fill('Sara')
  await page.getByRole('option', { name: /Sara Ali/ }).click()
  await expect(page.getByTestId('view-as-result')).toContainText('Manage users')
  expect(problems).toEqual([])
})

test('settings, translations, audit log and error monitoring open', async ({ page }) => {
  const problems = watchConsole(page)
  await page.getByTestId('nav-system_settings').click()
  await page.getByTestId('settings-tab-security').click()
  await expect(page.getByLabel('Minimum password length')).toBeVisible()
  await page.getByTestId('nav-translations').click()
  await expect(page.getByTestId('translation-type')).toBeVisible()
  await page.getByTestId('nav-audit_log').click()
  await expect(page.locator('table')).toContainText('setup.completed')
  await page.getByTestId('nav-error_monitoring').click()
  await expect(page.locator('h1')).toContainText('Error monitoring')
  expect(problems).toEqual([])
})
