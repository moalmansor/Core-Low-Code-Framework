import { expect, test, type Page } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Phase 3 end to end, on the "Visit requests" form built in 04-forms: a
 * workflow with a transition that needs a comment and a justification and
 * assigns the record, published from the builder; then a record is moved
 * through it on its page, the justification is asked for and shown in the
 * status timeline, and the record appears in My Work.
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

test('a record moves through a workflow with a justification and lands in My Work', async ({ page }) => {
  const problems = watchConsole(page)
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid
  const me = ((await api(page, 'GET', '/me')).data as { uuid: string }).uuid

  // Workflow: Draft → Submitted, the transition asks for a comment.
  const draft = { uuid: randomUUID(), key: 'draft', i18n: { name: { en: 'Draft', ar: 'مسودة' } }, color: '#64748b', icon: null, initial: true, final: false, order: 0, position: { x: 0, y: 0 } }
  const submitted = {
    uuid: randomUUID(),
    key: 'submitted',
    i18n: { name: { en: 'Submitted', ar: 'مقدّم' } },
    color: '#2563eb',
    icon: null,
    initial: false,
    final: true,
    order: 1,
    position: { x: 260, y: 0 },
  }
  const submit = {
    uuid: randomUUID(),
    key: 'submit',
    from: draft.uuid,
    to: submitted.uuid,
    i18n: { name: { en: 'Submit', ar: 'تقديم' } },
    condition: null,
    requiredFields: [],
    comment: 'mandatory',
    attachments: 'none',
    approval: { mode: 'none', approvers: [], n: null, quorumWeight: null, dueInMinutes: null, rejection: 'immediate', rejectionStatus: null },
    confirmation: false,
    style: null,
    order: 0,
    edge: null,
  }
  const wf = (await api(page, 'GET', `/forms/${form}/workflow`)).data as { hash: string }
  const saved = (await api(page, 'PUT', `/forms/${form}/workflow`, { document: { statuses: [draft, submitted], transitions: [submit], sla: [] }, base_hash: wf.hash })).data as {
    document: { transitions: { uuid: string; key: string }[] }
  }
  // A key that existed before keeps its row, so use the uuid the server answered with.
  const submitUuid = saved.document.transitions.find((x) => x.key === 'submit')!.uuid

  // The designer shows it.
  await page.goto(`/admin/forms/${form}/configure/workflow`)
  await expect(page.getByTestId('workflow-designer')).toContainText('Submitted')

  // Configuration screens share one frame (design system §5.5): quiet intro,
  // an empty state holding the add action, and a save bar with Discard.
  await page.getByTestId('config-tab-views').click()
  await expect(page.getByTestId('tab-intro')).toContainText('Columns can show')
  await page.getByTestId('views-empty').getByTestId('views-add').click()
  await expect(page.getByTestId('views-bar')).toContainText('Unsaved changes')
  await page.getByTestId('views-discard').click()
  await expect(page.getByTestId('views-empty')).toBeVisible()
  await expect(page.getByTestId('views-bar')).toContainText('All changes saved')

  // Publish the workflow from the builder.
  await page.goto(`/admin/forms/${form}/builder`)
  await page.getByTestId('open-publish').click()
  await page.getByTestId('publish-continue').click()
  await page.getByTestId('publish-confirm').click()
  await expect(page.getByTestId('publish-dialog')).toContainText(/applied|published/i, { timeout: 60_000 })

  // The transition needs a justification and assigns the record to the administrator.
  const jr = (await api(page, 'GET', `/forms/${form}/justification-rules`)).data as { hash: string }
  await api(page, 'PUT', `/forms/${form}/justification-rules`, {
    base_hash: jr.hash,
    rules: [
      {
        uuid: randomUUID(),
        scope: 'transition',
        target: submitUuid,
        subject: { type: 'everyone', uuid: null },
        level: 'mandatory',
        condition: null,
        levelWhen: null,
        text: { min: 5, max: 500 },
        reasonCodes: { mode: 'none', source: 'codes', set: null, collection: null },
        attachments: { mode: 'none', max: null, rules: null },
        showSummary: false,
        active: true,
        i18n: { title: { en: 'Why submit now?' }, help: {} },
      },
    ],
  })
  const ar = (await api(page, 'GET', `/forms/${form}/assignment-rules`)).data as { hash: string }
  await api(page, 'PUT', `/forms/${form}/assignment-rules`, {
    base_hash: ar.hash,
    rules: [{ uuid: randomUUID(), transition: submitUuid, strategy: 'user', target: { type: 'user', uuid: me }, field: null, condition: null, dueInMinutes: 60, workingTime: false, priority: 1 }],
  })

  // A new record starts in Draft.
  await page.goto(`/app/${form}/new`)
  await page.getByTestId('field-visitor').locator('input').fill('Workflow Visitor')
  await page.getByTestId('record-save').click()
  await expect(page.getByTestId('record-view')).toBeVisible()
  await expect(page.getByTestId('workflow-panel')).toContainText('Draft')
  // Record pages (design system §5.6): an empty value reads as empty, and one sidebar entry is active.
  await expect(page.locator('[data-empty="true"]').first()).toHaveText('Not filled in')
  await expect(page.getByTestId('sidebar').locator('.nav-active')).toHaveCount(1)
  const record = page.url().split(`/app/${form}/`)[1]!.split(/[/?]/)[0]!

  // Submit: the comment is required, then the justification is asked for.
  await page.getByTestId('transition-submit').click()
  const dialog = page.getByTestId('transition-dialog')
  await expect(dialog.getByTestId('transition-perform')).toBeDisabled()
  await dialog.getByTestId('transition-comment').fill('Ready for review')
  await dialog.getByTestId('transition-perform').click()
  const justify = page.getByTestId('justification-dialog')
  await expect(page.getByRole('dialog', { name: 'Why submit now?' })).toBeVisible()
  await justify.getByTestId('justification-text').fill('The visit is confirmed by phone')
  await justify.getByTestId('justification-submit').click()
  await expect(page.getByTestId('workflow-panel')).toContainText('Submitted')
  await expect(page.getByTestId('workflow-panel')).toContainText('E2E Root')

  // The status timeline shows the move, the comment and the justification.
  await page.getByTestId('tab-timeline').click()
  await expect(page.getByTestId('status-timeline')).toContainText('Ready for review')
  await expect(page.getByTestId('timeline-justification')).toContainText('The visit is confirmed by phone')

  // My Work lists the assigned record.
  await page.getByTestId('nav-my-work').click()
  const item = page.getByTestId(`my-work-item-${record}`)
  await expect(item).toContainText('Submitted')
  await expect(item).toContainText('Visit requests')

  expect(problems).toEqual([])
})
