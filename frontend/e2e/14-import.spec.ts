import { expect, test, type Page } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Phase 4 background import end to end (issue #7: the synchronous import of
 * Phase 2 became a background job), on the "Visit requests" form of
 * 04-forms: a CSV is uploaded from the records list, its column matched to a
 * field, every row checked first, then imported as a job whose progress and
 * outcome the dialog follows; the records exist afterwards.
 */
test.describe.configure({ mode: 'serial' })

test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function api(page: Page, path: string): Promise<Record<string, unknown>> {
  const origin = new URL(page.url()).origin
  const res = await page.request.get(`/api/v1${path}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: origin, Referer: page.url() } })
  expect(res.status(), `GET ${path}: ${await res.text()}`).toBeLessThan(300)
  return (await res.json()) as Record<string, unknown>
}

test('a CSV is checked and imported in the background from the records list', async ({ page }) => {
  const problems = watchConsole(page)
  const forms = (await api(page, '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid
  const tag = randomUUID().slice(0, 6)

  await page.goto(`/app/${form}`)
  await expect(page.getByTestId('records-table')).toBeVisible()
  await page.getByTestId('records-actions').click()
  await page.getByRole('menuitem', { name: 'Import' }).click()
  const dialog = page.getByTestId('import-dialog')
  await expect(dialog).toBeVisible()
  await dialog.getByTestId('import-file').setInputFiles({
    name: `visits-${tag}.csv`,
    mimeType: 'text/csv',
    buffer: Buffer.from(`Visitor\nImported ${tag} one\nImported ${tag} two\n`, 'utf8'),
  })
  await expect(dialog.getByTestId('import-columns')).toBeVisible()
  await expect(dialog.getByTestId('import-target-0')).toContainText('Visitor')
  await page.screenshot({ path: 'test-results/import-columns.png', fullPage: true })
  await dialog.getByTestId('import-check').click()
  await expect(dialog.getByTestId('import-preview')).toContainText('2')
  await dialog.getByTestId('import-start').click()
  await expect(dialog.getByTestId('import-outcome')).toBeVisible({ timeout: 30_000 })
  await expect(dialog.getByTestId('import-progress')).toContainText('2 created')
  await page.screenshot({ path: 'test-results/import-done.png', fullPage: true })

  const rows = (await api(page, `/r/${form}?per_page=100`)).data as { values: { visitor: string } }[]
  expect(
    rows
      .filter((r) => r.values.visitor.startsWith(`Imported ${tag}`))
      .map((r) => r.values.visitor)
      .sort(),
  ).toEqual([`Imported ${tag} one`, `Imported ${tag} two`])
  expect(problems).toEqual([])
})
