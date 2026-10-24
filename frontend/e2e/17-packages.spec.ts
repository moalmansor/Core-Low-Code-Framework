import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Configuration packages (specification §4.10, §4.22): a form is exported
 * with what it depends on, the file is imported back and compared with this
 * environment, and applying it keeps what is already the same.
 */
test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

test('a package is exported, imported back through its drift report, applied and compared', async ({ page }) => {
  test.setTimeout(180_000)
  const problems = watchConsole(page)
  await page.goto('/admin/packages')
  await expect(page.getByTestId('packages-view')).toBeVisible()

  await page.getByTestId('pkg-export').click()
  await expect(page.getByTestId('pkg-export-dialog')).toBeVisible()
  await page.getByTestId('pkg-name').fill('Visits')
  await page.getByTestId('pkg-pick-form-visit_requests').click()
  const downloaded = page.waitForEvent('download')
  await page.getByTestId('pkg-export-submit').click()
  const file = await (await downloaded).path()
  const content = JSON.parse(readFileSync(file, 'utf8')) as { format: string; objects: { type: string }[] }
  expect(content.format).toBe('lcf-package/1')
  expect(content.objects.map((o) => o.type)).toEqual(expect.arrayContaining(['application', 'form']))

  // Imported back here, everything is the same: nothing to choose.
  await page.getByTestId('pkg-import-file').setInputFiles(file)
  await expect(page.getByTestId('package-detail')).toBeVisible()
  await expect(page.getByTestId('pkg-status')).toContainText('Ready to apply')
  await expect(page.getByTestId('pkg-state-visit_requests')).toContainText('Same')
  await page.getByTestId('pkg-apply').click()
  await expect(page.getByTestId('pkg-status')).toContainText('Applied', { timeout: 60_000 })
  await expect(page.getByTestId('pkg-results')).toContainText('Kept')

  // Compared without importing: a drift report.
  await page.goto('/admin/packages/reports')
  await page.getByTestId('pkg-compare-file').setInputFiles(file)
  await expect(page.getByTestId('drift-report')).toBeVisible()
  await expect(page.getByTestId('pkg-state-visit_requests')).toContainText('Same')
  expect(problems).toEqual([])
})
