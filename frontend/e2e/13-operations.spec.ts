import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Phase 4 Operations Center end to end: the overview shows the health of
 * every service; an e-mail sent by an earlier spec is opened with its
 * rendered HTML and resent to edited recipients; the failed-submission and
 * failed-job lists open; an alert rule is added for a role and saved.
 */
test.describe.configure({ mode: 'serial' })

test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

test('the Operations Center shows health, resends an e-mail to new recipients and saves an alert rule', async ({ page }) => {
  const problems = watchConsole(page)
  await page.goto('/admin/operations')
  await expect(page.getByTestId('operations-center')).toBeVisible()
  await expect(page.getByTestId('ops-check-database')).toContainText('OK')
  await expect(page.getByTestId('ops-count-failed_jobs')).toBeVisible()
  await expect(page.getByTestId('ops-storage')).toContainText('%')
  await page.screenshot({ path: 'test-results/ops-overview.png', fullPage: true })

  // The e-mail queue: open one, see it rendered, resend it to someone else.
  await page.getByTestId('ops-tab-emails').click()
  const rows = page.getByTestId('ops-email-table').locator('tbody tr')
  await expect(rows.first()).toBeVisible()
  await rows.first().locator('td').nth(2).click()
  const dialog = page.getByTestId('ops-email-dialog')
  await expect(dialog).toBeVisible()
  await expect(dialog.getByTestId('ops-email-html')).toBeVisible()
  await dialog.getByTestId('ops-rcpt-to').fill('operations-check@example.test')
  await dialog.getByTestId('ops-rcpt-cc').fill('')
  await dialog.getByTestId('ops-rcpt-bcc').fill('')
  await page.screenshot({ path: 'test-results/ops-email.png', fullPage: true })
  await dialog.getByTestId('ops-email-resend-edited').click()
  await expect(page.getByText('E-mails queued again: 1')).toBeVisible()
  await page.getByTestId('ops-email-status').click()
  await page.getByRole('option', { name: 'Pending' }).click()
  await expect(page.getByTestId('ops-email-table')).toContainText('operations-check@example.test')

  // Failed submissions and jobs open with their explanations.
  await page.getByTestId('ops-tab-submissions').click()
  await expect(page.getByTestId('ops-submissions')).toContainText('Saves the system could not complete')
  await page.getByTestId('ops-tab-jobs').click()
  await expect(page.getByTestId('ops-jobs')).toContainText('Failures of background work')

  // An alert rule for the Super Admin role.
  await page.getByTestId('ops-tab-alerts').click()
  await expect(page.getByTestId('ops-alerts')).toBeVisible()
  await page.getByTestId('ops-alert-add').first().click()
  await page.getByTestId('ops-alert-roles-0').click()
  await page.getByRole('option', { name: 'Super Admin' }).click()
  await page.keyboard.press('Escape')
  await page.getByTestId('ops-alert-save').click()
  await expect(page.getByTestId('ops-alert-0')).toContainText('Failed submissions reaches 5')
  await expect(page.getByTestId('ops-alert-0')).toContainText('Now:')
  await page.screenshot({ path: 'test-results/ops-alerts.png', fullPage: true })
  expect(problems).toEqual([])
})
