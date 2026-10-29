import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Retention and personal data (specification §4.26): a retention policy is
 * written and run from the screen, a legal hold placed on a pilot record keeps
 * it from being deleted and is lifted with a reason, a personal data request
 * finds the person's account and is rejected with a reason, and the storage
 * tab shows the monthly log partitions and a quota.
 */
test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function api(page: Page, method: 'GET' | 'POST' | 'DELETE', path: string, data?: unknown): Promise<{ status: number; body: Record<string, unknown> }> {
  const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value)
  const res = await page.request.fetch(`/api/v1${path}`, {
    method,
    data,
    headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: new URL(page.url()).origin, Referer: page.url() },
  })
  return { status: res.status(), body: res.status() === 204 ? {} : ((await res.json()) as Record<string, unknown>) }
}

test('a policy runs, a legal hold blocks a delete until lifted, a personal data request is reviewed, and storage shows the log partitions', async ({ page }) => {
  test.setTimeout(240_000)
  const problems = watchConsole(page)
  await page.goto('/admin/retention')
  await expect(page.getByRole('heading', { name: 'Retention and personal data' })).toBeVisible()

  // A policy: e-mail log entries kept for ten years, then archived.
  await page.getByTestId('ret-new-policy').click()
  await page.getByTestId('ret-policy-editor').getByRole('textbox').first().fill('E-mail log, ten years')
  await page.getByTestId('ret-class').click()
  await page.getByRole('option', { name: 'E-mail log', exact: true }).click()
  await page.locator('#ret-days').fill('3650')
  await page.getByTestId('ret-policy-save').click()
  await expect(page.getByTestId('ret-run')).toBeVisible()
  await page.getByTestId('ret-run').click()
  await page.goto('/admin/retention')
  await expect(page.getByTestId('ret-runs').getByText('Succeeded').first()).toBeVisible({ timeout: 30_000 })

  // A legal hold on a pilot record.
  const form = ((await api(page, 'GET', '/forms?search=visit_requests')).body.data as { uuid: string; key: string }[]).find((f) => f.key === 'visit_requests')!.uuid
  const record = ((await api(page, 'GET', `/r/${form}?per_page=1`)).body.data as { uuid: string; row_version: number }[])[0]!
  await api(page, 'POST', '/legal-holds', { form, record: record.uuid, reason: 'Complaint under review' })
  expect((await api(page, 'DELETE', `/r/${form}/${record.uuid}`, { row_version: record.row_version })).status).toBe(423)
  await page.goto('/admin/retention/holds')
  await expect(page.getByText('Complaint under review')).toBeVisible()
  await page.getByRole('button', { name: 'Lift' }).first().click()
  await page.getByTestId('lift-reason').fill('Complaint closed')
  await page.getByTestId('lift-confirm').click()
  await expect(page.getByText('Complaint closed')).toBeVisible()

  // A personal data request: located, then rejected with a reason.
  await page.goto('/admin/retention/personal')
  await page.getByTestId('ret-pd-new').click()
  await page.getByTestId('pd-type').click()
  await page.getByRole('option', { name: "Export a person's data" }).click()
  await page.getByTestId('pd-email').fill('root@e2e.test')
  await page.getByTestId('pd-create').click()
  await expect(page.getByTestId('ret-pd-request')).toBeVisible()
  await expect(page.getByText('Waiting for review')).toBeVisible({ timeout: 30_000 })
  await expect(page.getByText('E2E Root', { exact: false }).first()).toBeVisible()
  await page.getByTestId('pd-reason').fill('The request did not come from the person')
  await page.getByTestId('pd-reject').click()
  await expect(page.getByText('Rejected')).toBeVisible()

  // Storage: the monthly log partitions and a quota.
  await page.goto('/admin/retention/storage')
  await expect(page.getByRole('heading', { name: 'Log growth by month' })).toBeVisible()
  await expect(page.getByText(/monthly partitions in place/).first()).toBeVisible()
  await page.getByTestId('st-add-quota').click()
  // The new quota's "For" field (the first choice inside the storage panel, not the language switcher).
  await page.getByTestId('ret-storage').getByRole('combobox').first().click()
  await page.getByRole('option', { name: 'The whole organization' }).click()
  await page.getByTestId('st-quotas-save').click()
  await expect(page.getByText('Within limits')).toBeVisible()
  expect(problems).toEqual([])
})
