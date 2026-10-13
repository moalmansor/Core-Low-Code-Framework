import { expect, test, type Page } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Phase 4 data quality end to end, on the "Visit requests" form of 04-forms:
 * a duplicate rule is added on the form's Data quality tab; saving a record
 * that looks like one already saved warns and saves only when confirmed; a
 * sweep finds the pair, which is merged with a justification; the merged-away
 * record waits in the recycle bin with its purge date; the repair scans run.
 */
test.describe.configure({ mode: 'serial' })

test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function api(page: Page, method: 'GET' | 'PUT' | 'POST', path: string, data?: unknown): Promise<Record<string, unknown>> {
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

test('a duplicate is warned about, found by a sweep, merged with a justification and kept in the recycle bin', async ({ page }) => {
  const problems = watchConsole(page)
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid
  const fields = ((await api(page, 'GET', `/forms/${form}/draft`)).data as { document: { fields: { uuid: string; key: string }[] } }).document.fields
  const visitor = fields.find((f) => f.key === 'visitor')!.uuid
  const tag = randomUUID().slice(0, 6)

  // The tab: a rule is added and saved from the screen.
  await page.goto(`/admin/forms/${form}/configure/data_quality`)
  await expect(page.getByTestId('dq-editor')).toBeVisible()
  await page.getByTestId('dq-add').first().click()
  await page.getByTestId('dq-name-0-en').fill(`Same visitor ${tag}`)
  await page.getByTestId('dq-save').click()
  await expect(page.getByTestId('dq-rule-0')).toContainText(`Same visitor ${tag}`)
  await expect(page.getByTestId('dq-sweep-run-0')).toBeEnabled()

  // The rule compares the visitor ignoring case and spacing (set through the document the screen edits).
  const rules = (await api(page, 'GET', `/forms/${form}/duplicate-rules`)).data as { rules: { uuid: string }[]; hash: string }
  await api(page, 'PUT', `/forms/${form}/duplicate-rules`, {
    base_hash: rules.hash,
    rules: [
      {
        uuid: rules.rules[0].uuid,
        i18n: { name: { en: `Same visitor ${tag}`, ar: `الزائر نفسه ${tag}` }, message: {} },
        fields: [{ field: visitor, method: 'normalized', threshold: null }],
        mode: 'all',
        action: 'warn',
        appliesOn: 'both',
        active: true,
      },
    ],
  })
  const first = ((await api(page, 'POST', `/r/${form}`, { values: { visitor: `Dup ${tag} Visitor` } })).data as { uuid: string }).uuid

  // Saving a look-alike warns; "Save anyway" keeps it.
  await page.goto(`/app/${form}/new`)
  await page.getByTestId('field-visitor').locator('input').fill(`dup  ${tag}  VISITOR`)
  await page.getByTestId('record-save').click()
  await expect(page.getByTestId('dup-dialog')).toBeVisible()
  await expect(page.getByTestId('dup-dialog')).toContainText(`Dup ${tag} Visitor`)
  await page.screenshot({ path: 'test-results/dq-duplicate-warning.png', fullPage: true })
  await page.getByTestId('dup-confirm').click()
  await expect(page).toHaveURL(new RegExp(`/app/${form}/[0-9a-f-]{36}$`))
  const second = page.url().split('/').pop()!

  // The sweep finds the pair; merging it asks for a reason.
  await page.goto(`/admin/forms/${form}/configure/data_quality`)
  await page.getByTestId('dq-sweep-run-0').click()
  const group = page.locator('[data-testid^="dq-group-0-"]').filter({ hasText: `Dup ${tag} Visitor` })
  await expect(group).toBeVisible({ timeout: 20000 })
  await page.screenshot({ path: 'test-results/dq-sweep.png', fullPage: true })
  await group.locator('[data-testid^="dq-merge-"]').click()
  await expect(page.getByTestId('merge-view')).toBeVisible()
  await expect(page.getByTestId('merge-row-visitor')).toBeVisible()
  await page.getByTestId(`merge-keep-${first}`).click()
  await page.getByTestId(`merge-choice-visitor-${first}`).click()
  await page.screenshot({ path: 'test-results/dq-merge.png', fullPage: true })
  await page.getByTestId('merge-submit').click()
  await expect(page.getByTestId('justification-dialog')).toBeVisible()
  await page.getByTestId('justification-text').fill('The same visitor was registered twice.')
  await page.getByTestId('justification-submit').click()
  await expect(page).toHaveURL(new RegExp(`/app/${form}/${first}$`))

  // The merged-away record is in the recycle bin, with the day it is removed for good.
  const bin = (await api(page, 'GET', `/r/${form}?trashed=1&per_page=100`)).data as { uuid: string; system: { purge_after: string | null } }[]
  expect(bin.find((r) => r.uuid === second)?.system.purge_after).toBeTruthy()

  // The repair scans run from the same tab.
  await page.goto(`/admin/forms/${form}/configure/data_quality`)
  await page.getByTestId('dq-scan-orphan_scan').click()
  await expect(page.getByTestId('dq-found-orphan_scan')).toBeVisible({ timeout: 20000 })
  await page.getByTestId('dq-scan-validation_sweep').click()
  await expect(page.getByTestId('dq-found-validation_sweep')).toBeVisible({ timeout: 20000 })

  // The rule is removed again so later runs start clean.
  const now = (await api(page, 'GET', `/forms/${form}/duplicate-rules`)).data as { hash: string }
  await api(page, 'PUT', `/forms/${form}/duplicate-rules`, { base_hash: now.hash, rules: [] })
  expect(problems).toEqual([])
})
