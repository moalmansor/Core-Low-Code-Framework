import { expect, test, type Page } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Phase 4 actions end to end, on the "Visit requests" form of 04-forms: a
 * built-in "Update a field" added in the form configuration and run on two
 * selected records; a custom action with a "Set field values" step run from
 * a row's menu and from the record page.
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

type Rec = { uuid: string; values: Record<string, unknown> }

test('a built-in bulk update runs on a selection, a custom action runs from the row menu and the record page', async ({ page }) => {
  const problems = watchConsole(page)
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid
  const fields = ((await api(page, 'GET', `/forms/${form}/draft`)).data as { document: { fields: { uuid: string; key: string }[] } }).document.fields
  const visitor = fields.find((f) => f.key === 'visitor')!.uuid
  const tag = randomUUID().slice(0, 6)
  const a = ((await api(page, 'POST', `/r/${form}`, { values: { visitor: `Act ${tag} one` } })).data as Rec).uuid
  const b = ((await api(page, 'POST', `/r/${form}`, { values: { visitor: `Act ${tag} two` } })).data as Rec).uuid

  // The configuration tab: a built-in "Update a field", shown on selected records.
  await page.goto(`/admin/forms/${form}/configure/actions`)
  await expect(page.getByTestId('actions-editor')).toBeVisible()
  const existing = await page.locator('[data-testid^="act-item-"]').count()
  await page.getByTestId(existing ? 'act-add' : 'act-add-first').click()
  await page.getByTestId('act-choice-bulk_update').click()
  const item = page.getByTestId(`act-item-${existing}`)
  await expect(item).toContainText('Update a field')
  await expect(item).toContainText('Selected records')
  await page.getByTestId('act-save').click()
  await expect(page.getByTestId('act-bar')).toContainText('All changes saved')
  await page.screenshot({ path: 'test-results/actions-editor.png', fullPage: true })

  // A custom action with one step, saved through the same document the screen edits.
  const doc = (await api(page, 'GET', `/forms/${form}/actions`)).data as { actions: Record<string, unknown>[]; hash: string }
  const label = `Stamp ${tag}`
  const stamp = {
    uuid: randomUUID(),
    key: `stamp_${tag.replace(/[^a-z0-9]/g, 'x')}`,
    kind: 'custom',
    builtin: null,
    i18n: { label: { en: label, ar: `ختم ${tag}` }, confirmation: {}, success: { en: `Stamped ${tag}`, ar: `تم الختم ${tag}` } },
    placements: ['row', 'view_page'],
    condition: null,
    confirm: false,
    runMode: 'auto',
    queueThreshold: null,
    maxRecords: null,
    justification: 'not_required',
    icon: 'pi-check',
    color: null,
    active: true,
    steps: [{ uuid: randomUUID(), type: 'update_fields', config: { set: [{ field: visitor, value: { k: 'lit', t: 'text', v: `Act ${tag} stamped` } }] }, condition: null, onFailure: 'stop' }],
  }
  await api(page, 'PUT', `/forms/${form}/actions`, { actions: [...doc.actions, stamp], base_hash: doc.hash })
  await page.reload()
  await expect(page.getByTestId('actions-editor')).toContainText(label)

  // The list: select both records and run the built-in on them.
  await page.goto(`/app/${form}?q=${encodeURIComponent(`Act ${tag}`)}`)
  const rows = page.getByTestId('records-table').locator('tbody tr')
  await expect(rows).toHaveCount(2)
  await rows.nth(0).getByRole('checkbox').check()
  await rows.nth(1).getByRole('checkbox').check()
  await page.getByTestId('records-actions').click()
  // Re-runs against the same database find earlier copies of the built-in; the newest goes last.
  await page.getByRole('menuitem', { name: 'Update a field (2 selected)' }).last().click()
  const dialog = page.getByTestId('action-dialog')
  await expect(dialog).toContainText('Records: 2')
  await page.getByTestId('action-field').click()
  await page.getByRole('option', { name: 'Visit date' }).click()
  // A date field takes a date picker; typing the day works as well as picking it.
  await dialog.locator('#act-run-value').fill('2026-12-24')
  await page.locator('.p-datepicker-panel .p-datepicker-day-selected').click()
  await expect(page.locator('.p-datepicker-panel')).toBeHidden()
  // The dialog names the records and can try the run first without keeping anything (§4.35).
  await expect(dialog.getByTestId('action-preview')).toContainText(`Act ${tag} one`)
  await dialog.getByTestId('action-try').click()
  await expect(dialog.getByTestId('action-trial')).toContainText('2 would change, 0 would not')
  expect(((await api(page, 'GET', `/r/${form}/${a}`)).data as Rec).values.visit_date).not.toBe('2026-12-24')
  await page.screenshot({ path: 'test-results/actions-run-dialog.png' })
  await page.getByTestId('action-run').click()
  await expect(page.getByText('Done. Records changed: 2')).toBeVisible()
  for (const uuid of [a, b]) {
    expect(((await api(page, 'GET', `/r/${form}/${uuid}`)).data as Rec).values.visit_date).toBe('2026-12-24')
  }

  // A row's menu offers the custom action; it runs at once and changes the record.
  await page.getByTestId(`record-actions-${a}`).click()
  await page.getByRole('menuitem', { name: label }).click()
  await expect(page.getByText(`Stamped ${tag}`)).toBeVisible()
  await expect.poll(async () => ((await api(page, 'GET', `/r/${form}/${a}`)).data as Rec).values.visitor).toBe(`Act ${tag} stamped`)

  // The record page shows it as a button.
  await page.goto(`/app/${form}/${b}`)
  await expect(page.getByTestId('record-view')).toBeVisible()
  await page.getByTestId('record-action').filter({ hasText: label }).click()
  await expect(page.getByText(`Stamped ${tag}`)).toBeVisible()
  await expect.poll(async () => ((await api(page, 'GET', `/r/${form}/${b}`)).data as Rec).values.visitor).toBe(`Act ${tag} stamped`)
  await page.screenshot({ path: 'test-results/actions-record-page.png' })

  expect(problems).toEqual([])
})
