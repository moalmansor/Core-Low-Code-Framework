import { expect, test, type Page } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Blueprints and cloning (specification §4.30): an application is copied
 * from the Applications screen with its forms published and its workflow;
 * a form's workflow is saved as a blueprint and applied to another form.
 */
test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function api(page: Page, method: 'GET' | 'POST' | 'PUT', path: string, data?: unknown): Promise<Record<string, unknown>> {
  const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value)
  const res = await page.request.fetch(`/api/v1${path}`, {
    method,
    data,
    headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: new URL(page.url()).origin, Referer: page.url() },
  })
  expect(res.status(), `${method} ${path}: ${await res.text()}`).toBeLessThan(300)
  return (await res.json()) as Record<string, unknown>
}

/** A form with one text field ("title"), saved as a draft. */
async function makeForm(page: Page, app: string, key: string, name: string): Promise<string> {
  const form = ((await api(page, 'POST', '/forms', { kind: 'form', key, application: app, name: { en: name, ar: name } })).data as { uuid: string }).uuid
  const current = (await api(page, 'GET', `/forms/${form}/draft`)).data as { document: Record<string, unknown>; draft_updated_at: string }
  const title = {
    uuid: randomUUID(),
    key: 'title',
    type: 'text',
    group: null,
    order: 0,
    storage: { nullable: true, index: 'none' },
    options: null,
    validation: {},
    behavior: {},
    ui: {},
    table: {},
    export: {},
    events: {},
    hook: null,
    flags: { trackChanges: true },
    justification: 'inherit',
    relation: null,
    template: null,
    i18n: { label: { en: 'Title', ar: 'العنوان' } },
  }
  await api(page, 'PUT', `/forms/${form}/draft`, { document: { ...current.document, fields: [title] }, draft_updated_at: current.draft_updated_at })
  return form
}

const status = (key: string, en: string, ar: string, initial: boolean, final: boolean, x: number) => ({
  uuid: randomUUID(),
  key,
  i18n: { name: { en, ar } },
  color: '#64748b',
  icon: null,
  initial,
  final,
  order: 0,
  position: { x, y: 0 },
})

test('an application is copied with its forms published, and a workflow blueprint is applied to another form', async ({ page }) => {
  test.setTimeout(240_000)
  const problems = watchConsole(page)
  await page.goto('/')
  const id = Date.now().toString(36)
  const app = ((await api(page, 'POST', '/applications', { key: `bp_${id}`, name: { en: `Purchasing ${id}`, ar: `المشتريات ${id}` } })).data as { uuid: string }).uuid
  const source = await makeForm(page, app, `bp_${id}_orders`, `Orders ${id}`)
  const open = status('open', 'Open', 'مفتوح', true, false, 0)
  const closed = status('closed', 'Closed', 'مغلق', false, true, 260)
  const close = {
    uuid: randomUUID(),
    key: 'close',
    from: open.uuid,
    to: closed.uuid,
    i18n: { name: { en: 'Close', ar: 'إغلاق' } },
    condition: null,
    requiredFields: [],
    comment: 'none',
    attachments: 'none',
    approval: { mode: 'none', approvers: [], n: null, quorumWeight: null, dueInMinutes: null, rejection: 'immediate', rejectionStatus: null },
    confirmation: false,
    style: null,
    order: 0,
    edge: null,
  }
  const wf = (await api(page, 'GET', `/forms/${source}/workflow`)).data as { hash: string }
  await api(page, 'PUT', `/forms/${source}/workflow`, { document: { statuses: [open, closed], transitions: [close], sla: [] }, base_hash: wf.hash })

  // Copy the application from its row menu.
  await page.goto('/admin/applications')
  await page.getByTestId('app-search').fill(`bp_${id}`)
  await page.getByTestId(`app-actions-bp_${id}`).click()
  await page.getByRole('menuitem', { name: 'Make a copy' }).click()
  await page.getByTestId('clone-key').fill(`bpc_${id}`)
  await page.getByTestId('clone-name-en').fill(`Purchasing copy ${id}`)
  await page.getByTestId('clone-submit').click()
  await page.getByTestId('app-search').fill(`bpc_${id}`)
  await expect(page.getByTestId('app-table')).toContainText(`Purchasing copy ${id}`, { timeout: 60_000 })
  const copies = (await api(page, 'GET', `/forms?search=bpc_${id}`)).data as { uuid: string; key: string; state: string }[]
  const copy = copies.find((f) => f.key.startsWith(`bpc_${id}_`))!
  expect(copy.state).toBe('published')
  const copiedStatuses = ((await api(page, 'GET', `/forms/${copy.uuid}/workflow`)).data as { document: { statuses: { key: string }[] } }).document.statuses
  expect(copiedStatuses.map((s) => s.key)).toEqual(['open', 'closed'])

  // Save the source form's workflow as a blueprint and apply it to a form without one.
  const target = await makeForm(page, app, `bp_${id}_returns`, `Returns ${id}`)
  await page.goto('/admin/blueprints')
  await page.getByTestId('bp-new').click()
  await page.getByTestId('bpn-type').click()
  await page.getByRole('option', { name: "A form's workflow" }).click()
  await page.locator('#bpn-source').fill(`bp_${id}_orders`)
  // The copy has the same name; the source sorts first by key.
  await page.getByRole('option', { name: `Orders ${id}` }).first().click()
  await page.getByTestId('bpn-name-en').fill(`Open and closed ${id}`)
  await page.getByTestId('bp-create').click()
  await expect(page.getByRole('heading', { name: `Open and closed ${id}` })).toBeVisible()

  await page.getByTestId('bp-instantiate').click()
  await page.locator('#in-form').click()
  await page.getByRole('option', { name: new RegExp(`Returns ${id}`) }).click()
  await page.getByTestId('in-submit').click()
  await expect(page.getByTestId(`bp-instance-bp_${id}_returns`)).toBeVisible()
  const applied = ((await api(page, 'GET', `/forms/${target}/workflow`)).data as { document: { statuses: { key: string; uuid: string }[] } }).document.statuses
  expect(applied.map((s) => s.key)).toEqual(['open', 'closed'])
  // The target's statuses are its own, not the source's.
  expect(applied.map((s) => s.uuid)).not.toContain(open.uuid)

  // The library lists it.
  await page.goto('/admin/blueprints')
  await expect(page.getByTestId('bp-list')).toContainText(`Open and closed ${id}`)
  expect(problems).toEqual([])
})
