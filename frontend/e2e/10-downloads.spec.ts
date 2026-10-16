import { expect, test, type Page } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Phase 4 custom downloads end to end, on the "Visit requests" form of
 * 04-forms: a profile built in the form configuration from the relation
 * tree with a live preview, then downloaded as Excel from the records
 * table's Download menu and as CSV from the record page.
 */
test.describe.configure({ mode: 'serial' })

test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function api(page: Page, method: 'GET' | 'POST', path: string, data?: unknown): Promise<Record<string, unknown>> {
  const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value)
  const origin = new URL(page.url()).origin
  const res = await page.request.fetch(`/api/v1${path}`, {
    method,
    data,
    headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: origin, Referer: page.url() },
  })
  expect(res.status(), `${method} ${path}: ${await res.text()}`).toBeLessThan(300)
  return (await res.json()) as Record<string, unknown>
}

test('a profile is built from the relation tree with a live preview, then downloaded from the table and the record page', async ({ page }) => {
  const problems = watchConsole(page)
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid
  const tag = randomUUID().slice(0, 6)
  const record = ((await api(page, 'POST', `/r/${form}`, { values: { visitor: `Download ${tag}` } })).data as { uuid: string }).uuid

  // The builder: name, columns ticked in the tree, the preview shows them.
  await page.goto(`/admin/forms/${form}/configure/downloads`)
  await expect(page.getByTestId('download-profiles')).toBeVisible()
  await page.getByTestId('dl-new').click()
  await expect(page.getByTestId('download-editor')).toBeVisible()
  await page.getByTestId('dl-name').locator('input').first().fill(`Visits ${tag}`)
  await page.getByTestId('dl-key').fill(`visits_${tag.replace(/[^a-z0-9]/g, 'x')}`)
  await expect(page.getByTestId('dl-tree')).toBeVisible()
  await page.getByTestId('dl-pick-Visitor').click()
  await page.getByTestId('dl-pick-Visit date').click()
  await page.getByTestId('dl-pick-Status').click()
  await page.getByRole('button', { name: 'File', exact: true }).click()
  await page.getByTestId('dl-format-csv').click()
  await expect(page.getByTestId('dl-preview')).toContainText(`Download ${tag}`, { timeout: 15_000 })
  await expect(page.getByTestId('dl-preview').locator('th')).toHaveText(['Visitor', 'Visit date', 'Status'])
  // The columns a role receives.
  await page.getByTestId('dl-role').click()
  await page.getByRole('option').first().click()
  await expect(page.getByTestId('dl-effective')).toBeVisible()
  await page.screenshot({ path: 'test-results/downloads-builder.png', fullPage: true })
  await page.getByTestId('dl-save').click()
  await expect(page.getByTestId('dl-bar')).toContainText('All changes saved')
  await expect(page).toHaveURL(/\/downloads\/[0-9a-f-]{36}$/)
  // Offered on the record page too.
  await page.getByRole('checkbox', { name: 'Record page' }).check()
  await page.getByTestId('dl-save').click()
  await expect(page.getByTestId('dl-bar')).toContainText('All changes saved')

  // The records table's Download menu.
  await page.goto(`/app/${form}?q=${encodeURIComponent(`Download ${tag}`)}`)
  await page.getByTestId('records-download').click()
  await page.getByRole('menuitem', { name: `Visits ${tag}` }).click()
  const dialog = page.getByTestId('download-dialog')
  await expect(dialog).toBeVisible()
  const excel = page.waitForEvent('download')
  await page.getByTestId('download-run').click()
  const file = await excel
  expect(file.suggestedFilename()).toMatch(/\.xlsx$/)
  await expect(page.getByTestId('download-result')).toContainText('The file is ready')
  await page.screenshot({ path: 'test-results/downloads-dialog.png' })
  await page.getByTestId('download-close').click()

  // The record page: this record only, as CSV.
  await page.goto(`/app/${form}/${record}`)
  await page.getByTestId('record-download').click()
  await page.getByRole('menuitem', { name: `Visits ${tag}` }).click()
  await page.getByTestId('download-format').getByText('CSV').click()
  const csv = page.waitForEvent('download')
  await page.getByTestId('download-run').click()
  const saved = await csv
  const text = readFileSync(await saved.path(), 'utf8')
  expect(text).toContain(`Download ${tag}`)
  expect(text.split('\n').filter((l) => l.trim() !== '')).toHaveLength(2)

  expect(problems).toEqual([])
})
