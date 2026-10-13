import { expect, test, type Page } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { admin, signIn, watchConsole } from './support.ts'

/**
 * Phase 4 automations end to end, on the "Visit requests" form of 04-forms:
 * the builder refuses an automation without steps; an automation that runs
 * when a record is created changes the record, shows in the run history and
 * can be tested against a sample record without keeping anything; a
 * scheduled automation appears in the scheduled-task manager and in
 * Admin › Automations, where it is turned off.
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

const text = (v: string) => ({ k: 'lit', t: 'text', v })
const visitorIs = (v: string) => ({ k: 'bin', op: '=', a: { k: 'ref', scope: 'record', path: ['visitor'] }, b: text(v) })

test('an automation runs when a record is created, is tested without keeping changes, and its schedule is listed', async ({ page }) => {
  const problems = watchConsole(page)
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid
  const fields = ((await api(page, 'GET', `/forms/${form}/draft`)).data as { document: { fields: { uuid: string; key: string }[] } }).document.fields
  const visitor = fields.find((f) => f.key === 'visitor')!.uuid
  const me = ((await api(page, 'GET', `/users?search=${encodeURIComponent(admin.email)}`)).data as { uuid: string }[])[0]!.uuid
  const tag = randomUUID().slice(0, 6)
  const key = `greet_${tag.replace(/[^a-z0-9]/g, 'x')}`

  // The builder: a name and the default trigger are not enough without a step.
  await page.goto(`/admin/forms/${form}/configure/automations`)
  await expect(page.getByTestId('automations')).toBeVisible()
  await page.getByTestId('auto-new').click()
  await expect(page.getByTestId('automation-editor')).toBeVisible()
  await page.getByTestId('auto-name').locator('input').first().fill(`Greet ${tag}`)
  await page.getByTestId('auto-key').fill(key)
  await expect(page.getByTestId('auto-trigger-0')).toContainText('A record is created')
  await page.getByTestId('auto-save').click()
  await expect(page.getByTestId('automation-editor')).toContainText('Add at least one step')
  await page.screenshot({ path: 'test-results/automation-builder.png', fullPage: true })
  await page.getByTestId('auto-discard').click()

  // An automation that renames new visits by this tag, saved through the document the screen edits.
  const created = (
    await api(page, 'POST', `/forms/${form}/automations`, {
      automation: {
        key,
        i18n: { name: { en: `Greet ${tag}`, ar: `ترحيب ${tag}` }, description: {} },
        enabled: true,
        triggers: [{ uuid: randomUUID(), type: 'record_created' }],
        condition: visitorIs(`Auto ${tag} new`),
        steps: [{ uuid: randomUUID(), type: 'update_fields', config: { set: [{ field: visitor, value: text(`Auto ${tag} greeted`) }] }, condition: null, onFailure: 'stop' }],
        runAs: 'triggering_user',
        runAsUser: null,
        concurrency: 5,
        retry: { maxAttempts: 3, backoffSeconds: [60] },
        maxRecords: 1000,
        confirmAbove: null,
        maxChainDepth: 3,
      },
    })
  ).data as { automation: { uuid: string } }
  const automation = created.automation.uuid

  // A new matching record is changed by the automation.
  const rec = ((await api(page, 'POST', `/r/${form}`, { values: { visitor: `Auto ${tag} new` } })).data as { uuid: string }).uuid
  await expect.poll(async () => ((await api(page, 'GET', `/r/${form}/${rec}`)).data as { values: { visitor: string } }).values.visitor, { timeout: 20_000 }).toBe(`Auto ${tag} greeted`)

  // Its run in the history, with the step that ran.
  await page.goto(`/admin/forms/${form}/automations/${automation}`)
  await expect(page.getByTestId('automation-editor')).toBeVisible()
  const runs = page.getByTestId('auto-runs')
  await expect(async () => {
    await page.getByTestId('auto-runs-refresh').click()
    await expect(runs).toContainText('Succeeded', { timeout: 2_000 })
  }).toPass({ timeout: 20_000 })
  await runs.getByText('Succeeded').first().click()
  await expect(page.getByTestId('auto-run-detail')).toContainText('Set field values')
  await expect(page.getByTestId('auto-run-detail')).toContainText('1 covered, 1 changed')
  await page.keyboard.press('Escape')

  // A test against another record: the step runs, nothing is kept.
  const sample = ((await api(page, 'POST', `/r/${form}`, { values: { visitor: `Auto ${tag} sample` } })).data as { uuid: string }).uuid
  await page.getByTestId('auto-test').click()
  const dialog = page.getByTestId('auto-test-dialog')
  await dialog.getByTestId('auto-test-record').locator('input').fill(`Auto ${tag} sample`)
  await page.getByRole('option', { name: `Auto ${tag} sample` }).click()
  await dialog.getByTestId('auto-run-test').click()
  await expect(dialog.getByTestId('auto-run-detail')).toBeVisible({ timeout: 15_000 })
  await expect(dialog.getByTestId('auto-run-status')).toContainText('Succeeded')
  await page.screenshot({ path: 'test-results/automation-test.png' })
  expect(((await api(page, 'GET', `/r/${form}/${sample}`)).data as { values: { visitor: string } }).values.visitor).toBe(`Auto ${tag} sample`)
  await dialog.getByRole('button', { name: 'Close' }).first().click()

  // A scheduled automation: listed in the scheduled-task manager with its next run.
  await api(page, 'POST', `/forms/${form}/automations`, {
    automation: {
      key: `nightly_${tag.replace(/[^a-z0-9]/g, 'x')}`,
      i18n: { name: { en: `Nightly ${tag}`, ar: `ليلي ${tag}` }, description: {} },
      enabled: true,
      triggers: [{ uuid: randomUUID(), type: 'schedule', schedule: { frequency: 'daily', time: '02:15', weekday: null, day: null, minute: null, cron: null, timezone: 'Asia/Riyadh' } }],
      condition: visitorIs(`Auto ${tag} never`),
      steps: [{ uuid: randomUUID(), type: 'update_fields', config: { set: [{ field: visitor, value: text('x') }] }, condition: null, onFailure: 'stop' }],
      runAs: 'specific_user',
      runAsUser: me,
      concurrency: 1,
      retry: { maxAttempts: 1, backoffSeconds: [60] },
      maxRecords: 100,
      confirmAbove: null,
      maxChainDepth: 3,
    },
  })
  await page.goto('/admin/scheduled-tasks')
  const row = page.getByTestId('scheduled-tasks').getByRole('row', { name: new RegExp(`Nightly ${tag}`) })
  await expect(row).toContainText('15 2 * * *')
  await expect(row).toContainText('Asia/Riyadh')
  await page.screenshot({ path: 'test-results/scheduled-tasks.png', fullPage: true })

  // Admin › Automations lists both; turning the scheduled one off stops its schedule.
  await page.goto('/admin/automations')
  await expect(page.getByTestId(`auto-row-${key}`)).toContainText(`Greet ${tag}`)
  const nightly = page.getByTestId(`auto-row-nightly_${tag.replace(/[^a-z0-9]/g, 'x')}`)
  await nightly.getByRole('switch').click()
  await expect(page.getByText('The automation is off.')).toBeVisible()
  await page.goto('/admin/scheduled-tasks')
  await expect(page.getByTestId('scheduled-tasks').getByRole('row', { name: new RegExp(`Nightly ${tag}`) })).toContainText('Off')

  expect(problems).toEqual([])
})
