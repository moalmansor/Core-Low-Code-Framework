import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Help, tours and usage insight (specification §4.37): help written for the
 * visit requests form shows as a tooltip beside its field and in the Help
 * panel; a first-use tour starts on the form once and can be taken again from
 * Help; the form's use shows in Usage insight.
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

test('help shows beside a field and in the Help panel, a first-use tour runs once, and the form shows in Usage insight', async ({ page }) => {
  test.setTimeout(180_000)
  const problems = watchConsole(page)
  await page.goto('/')
  const form = ((await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]).find((f) => f.key === 'visit_requests')!.uuid
  const fields = ((await api(page, 'GET', `/help-content/targets?form=${form}`)).data as { fields: { uuid: string; key: string }[] }).fields
  const visitor = fields.find((f) => f.key === 'visitor')!

  // Help is written on the Help content screen.
  await page.goto('/admin/help')
  await page.getByTestId('help-new').first().click()
  await page.getByTestId('help-type').click()
  await page.getByRole('option', { name: 'Field', exact: true }).click()
  await page.getByTestId('help-form-pick').click()
  await page.getByRole('option', { name: 'Visit requests', exact: true }).click()
  await page.getByTestId('help-target').click()
  await page.getByRole('option', { name: 'Visitor', exact: true }).click()
  await page.getByTestId('help-display').click()
  await page.getByRole('option', { name: 'Tooltip beside it' }).click()
  await page.getByTestId('help-form').getByRole('textbox').first().fill('Who to ask')
  await page.getByTestId('help-body-en').fill('The full name, as on their ID')
  await page.locator('#help-published').click()
  await page.getByTestId('help-save').click()
  await expect(page.getByTestId('help-form')).toBeHidden()
  await api(page, 'POST', '/help-content', {
    help: { target_type: 'form', target: form, display: 'side_panel', published: true, sort_order: 0, i18n: { title: { en: 'Before you ask' }, body: { en: '<p>Visits need two days notice.</p>' } } },
  })
  await api(page, 'POST', '/tours', {
    tour: {
      form,
      place: null,
      trigger: 'first_use',
      roles: [],
      active: true,
      i18n: { name: { en: 'Visit requests in a minute' } },
      steps: [
        { key: 'welcome', target: null, placement: 'auto', i18n: { title: { en: 'Welcome' }, body: { en: 'Here are the visit requests.' } } },
        { key: 'add', target: '[data-tour="new_record"]', placement: 'auto', i18n: { title: { en: 'Ask for a visit' }, body: { en: 'Start a new request here.' } } },
      ],
    },
  })

  // The tour starts by itself the first time.
  await page.goto(`/app/${form}`)
  await expect(page.getByTestId('tour')).toBeVisible()
  await expect(page.getByTestId('tour')).toContainText('Here are the visit requests.')
  await page.getByTestId('tour-next').click()
  await expect(page.getByTestId('tour')).toContainText('Start a new request here.')
  await page.getByTestId('tour-next').click()
  await expect(page.getByTestId('tour')).toBeHidden()
  // Not again; but Help offers it, with the help written for the form.
  await page.reload()
  await expect(page.getByTestId('records-new')).toBeVisible()
  await expect(page.getByTestId('tour')).toBeHidden()
  await page.getByTestId('help-button').click()
  await expect(page.getByTestId('help-panel')).toContainText('Visits need two days notice.')
  await page.getByTestId('help-tour').click()
  await expect(page.getByTestId('tour')).toBeVisible()
  await page.getByTestId('tour-close').click()

  // The tooltip beside the field.
  await page.getByTestId('records-new').click()
  const tip = page.locator('[data-field="visitor"] [aria-label="The full name, as on their ID"]')
  await expect(tip).toBeVisible()
  await page.getByTestId('field-visitor').getByRole('textbox').first().fill('Usage visitor')
  await page.getByTestId('record-save').click()
  await expect(page.getByTestId('record-view')).toBeVisible()

  // Usage insight counts the view and the submission.
  await page.goto('/admin/usage')
  const row = page.getByTestId('usage-insight').getByRole('row', { name: /Visit requests/ })
  await expect(row).toBeVisible()
  await row.click()
  await expect(page.getByTestId('usage-detail')).toBeVisible()
  expect(visitor.uuid).toBeTruthy()
  expect(problems).toEqual([])
})
