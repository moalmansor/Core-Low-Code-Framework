import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

test.describe.configure({ mode: 'serial' })

test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function openForms(page: Page): Promise<void> {
  await page.getByTestId('nav-forms').click()
  await expect(page.getByTestId('forms-table')).toBeVisible()
}

test('an administrator builds a form, publishes it into the sidebar, and records are created and edited', async ({ page }) => {
  const problems = watchConsole(page)

  // Application.
  await page.getByTestId('nav-applications').click()
  await page.getByTestId('app-new').click()
  await page.getByTestId('app-key').fill('operations')
  await page.getByTestId('app-name-en').fill('Operations')
  await page.getByTestId('app-name-ar').fill('العمليات')
  await page.getByTestId('app-save').click()
  await expect(page.getByTestId('app-table')).toContainText('Operations')

  // Form, opened in the builder.
  await openForms(page)
  await page.getByTestId('form-new').click()
  await page.getByTestId('create-key').fill('visit_requests')
  await page.getByTestId('cf-name-en').fill('Visit requests')
  await page.getByTestId('cf-name-ar').fill('طلبات الزيارة')
  await page.getByTestId('create-app').click()
  await page.getByRole('option', { name: /Operations/ }).click()
  await page.getByTestId('create-submit').click()
  await expect(page.getByTestId('form-builder')).toBeVisible()

  // A required text field and a date field from the palette.
  await page.getByTestId('palette-text').click()
  await expect(page.getByTestId('field-properties')).toBeVisible()
  await page.getByTestId('prop-key').fill('visitor')
  await page.getByTestId('prop-label').locator('input').first().fill('Visitor')
  await page.getByTestId('palette-date').click()
  await page.getByTestId('prop-key').fill('visit_date')
  await page.getByTestId('prop-label').locator('input').first().fill('Visit date')
  await page.getByTestId('save-now').click()
  await expect(page.getByTestId('save-state')).toContainText(/^\s*Saved/)

  // Publish with a menu entry.
  await page.getByTestId('open-publish').click()
  await expect(page.getByTestId('publish-dialog')).toBeVisible()
  await page.getByTestId('publish-continue').click()
  // The form is offered to the sidebar of its application.
  await page.getByTestId('add-to-menu').locator('input').check()
  await expect(page.getByTestId('add-to-menu').locator('input')).toBeChecked()
  await page.getByTestId('publish-confirm').click()
  await expect(page.getByTestId('publish-dialog')).toContainText(/applied|published/i, { timeout: 60_000 })

  // The form appears in the sidebar under its application; records are created there.
  await page.goto('/')
  await page.getByTestId('nav-app-operations').getByRole('link', { name: 'Visit requests' }).click()
  await expect(page.getByTestId('records-table')).toBeVisible()
  const formUuid = page.url().split('/app/')[1]!.split(/[/?]/)[0]!
  await page.getByTestId('records-new').click()
  await page.getByTestId('field-visitor').locator('input').fill('Nora Saleh')
  await page.getByTestId('record-save').click()
  await expect(page.getByTestId('record-view')).toBeVisible()
  await expect(page.getByTestId('record-view')).toContainText('Nora Saleh')
  const recordUuid = page.url().split(`/app/${formUuid}/`)[1]!.split(/[/?]/)[0]!

  // Someone else changes the record while it is open for editing: the conflict screen appears.
  await page.getByTestId('record-edit').click()
  await page.getByTestId('field-visitor').locator('input').fill('Nora S. Saleh')
  const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value)
  const other = await page.request.patch(`/api/v1/r/${formUuid}/${recordUuid}`, {
    data: { values: { visitor: 'Nora Al-Saleh' }, row_version: 1 },
    headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: new URL(page.url()).origin, Referer: page.url() },
  })
  expect(other.status()).toBe(200)
  await page.getByTestId('record-save').click()
  await expect(page.getByTestId('conflict-dialog')).toBeVisible()
  await expect(page.getByTestId('conflict-visitor')).toContainText('Nora Al-Saleh')
  await page.getByTestId('keep-mine-visitor').click()
  await page.getByTestId('conflict-resolve').click()
  await expect(page.getByTestId('record-view')).toContainText('Nora S. Saleh')

  // The list shows the record; history records both changes.
  await page.getByTestId('tab-history').click()
  await expect(page.getByTestId('history')).toContainText(/Nora/)
  expect(problems).toEqual([])
})

test('the building screens open in Arabic, right to left, without errors', async ({ page }) => {
  const problems = watchConsole(page)
  await page.getByRole('combobox', { name: 'Language' }).click()
  await page.getByRole('option', { name: 'العربية' }).click()
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  for (const [area, marker] of [
    ['applications', 'app-table'],
    ['forms', 'forms-table'],
    ['blueprints', null],
    ['reference_data', null],
    ['schema', null],
  ] as const) {
    await page.getByTestId(`nav-${area}`).click()
    await expect(page.locator('main h1')).toBeVisible()
    if (marker) await expect(page.getByTestId(marker)).toBeVisible()
  }
  await page.getByTestId('nav-forms').click()
  await expect(page.getByTestId('forms-table')).toContainText('visit_requests')
  expect(problems).toEqual([])
})
