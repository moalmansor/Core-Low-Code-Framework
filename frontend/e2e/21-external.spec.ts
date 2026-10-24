import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * External access (specification §4.32): a form published outside is
 * filled in by a visitor without an account — through the proof-of-work
 * check — and creates a record; a link shares that record with someone
 * without an account and stops working once withdrawn.
 */
test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function api(page: Page, method: 'GET' | 'POST' | 'PUT' | 'DELETE', path: string, data?: unknown): Promise<Record<string, unknown>> {
  const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value)
  const res = await page.request.fetch(`/api/v1${path}`, {
    method,
    data,
    headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: new URL(page.url()).origin, Referer: page.url() },
  })
  expect(res.status(), `${method} ${path}: ${await res.text()}`).toBeLessThan(300)
  return res.status() === 204 ? {} : ((await res.json()) as Record<string, unknown>)
}

test('a visitor fills in an external form, and a record link shares the record until it is withdrawn', async ({ page, browser }) => {
  test.setTimeout(240_000)
  const problems = watchConsole(page)
  await page.goto('/')
  const form = ((await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]).find((f) => f.key === 'visit_requests')!.uuid
  const me = (await api(page, 'GET', '/me')).data as { uuid: string }
  const exposable = ((await api(page, 'GET', `/external-forms/fields?form=${form}`)).data as { fields: { uuid: string; display: boolean }[] }).fields
  const slug = `visit-e2e-${Date.now().toString(36)}`
  const x = (
    await api(page, 'POST', '/external-forms', {
      form: {
        form,
        slug,
        title: { en: 'Request a visit', ar: 'اطلب زيارة' },
        intro: { en: 'Tell us who is coming.' },
        success_message: { en: 'We received your request.' },
        theme: null,
        initial_status: null,
        fields: exposable.filter((f) => !f.display).map((f) => f.uuid),
        captcha: true,
        rate_limit_per_ip: 50,
        opens_at: null,
        closes_at: null,
        daily_window: null,
        submission_cap: null,
        verification: 'none',
        run_as: me.uuid,
        active: true,
        audience: 'public',
        roles: [],
      },
    })
  ).data as { uuid: string }

  // The editor shows what the form can show outside.
  await page.goto(`/admin/external/forms/${x.uuid}`)
  await expect(page.getByTestId('xa-form-editor')).toBeVisible()
  await expect(page.getByTestId('xa-field-visitor')).toBeVisible()

  // A visitor with no account: the page, the check, the record.
  const visitor = await browser.newContext()
  const outside = await visitor.newPage()
  const outsideProblems = watchConsole(outside)
  const name = `Visitor ${Date.now().toString(36)}`
  await outside.goto(`/x/f/${slug}`)
  await expect(outside.getByTestId('x-form-title')).toHaveText('Request a visit')
  await outside.getByTestId('field-visitor').locator('input').fill(name)
  await outside.getByTestId('x-submit').click()
  await expect(outside.getByTestId('x-form-done')).toContainText('We received your request.', { timeout: 60_000 })

  const found = (await api(page, 'GET', `/r/${form}?search=${encodeURIComponent(name)}`)).data as { uuid: string }[]
  expect(found).toHaveLength(1)

  // Shared from the record: the link shows it, once withdrawn it does not.
  await page.goto(`/app/${form}/${found[0]!.uuid}`)
  await page.getByTestId('record-share').click()
  await expect(page.getByTestId('share-dialog')).toBeVisible()
  await page.getByTestId('share-create').click()
  const url = (await page.getByTestId('share-url').textContent())!.trim()
  await outside.goto(new URL(url).pathname)
  await expect(outside.getByTestId('x-link-page')).toContainText(name)
  await page.getByTestId('share-revoke').first().click()
  await expect(page.getByTestId('share-links')).toContainText('Withdrawn')
  await outside.reload()
  await expect(outside.getByTestId('x-link-unavailable')).toBeVisible()
  await visitor.close()

  await api(page, 'DELETE', `/external-forms/${x.uuid}`)
  expect(problems).toEqual([])
  expect(outsideProblems).toEqual([])
})
