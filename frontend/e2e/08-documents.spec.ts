import { expect, test, type Page } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Phase 4 document templates end to end, on the "Visit requests" form of
 * 04-forms: a template designed from blocks with its live preview and a PDF
 * generated for a record; a Word template uploaded and generated; and a
 * notification rule that attaches a document.
 */
test.describe.configure({ mode: 'serial' })

test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function api(page: Page, method: 'GET' | 'POST', path: string, data?: unknown): Promise<Record<string, unknown>> {
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

async function visitForm(page: Page): Promise<string> {
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  return forms.find((f) => f.key === 'visit_requests')!.uuid
}

async function chooseForm(page: Page): Promise<void> {
  await page.locator('#doc-form').click()
  await page.getByRole('option', { name: 'Visit requests' }).click()
}

async function chooseRecord(page: Page): Promise<void> {
  await page.locator('#doc-rec').click()
  await page.getByRole('option').first().click()
}

test('a designed template previews a record live and downloads as PDF', async ({ page }) => {
  const problems = watchConsole(page)
  const form = await visitForm(page)
  await api(page, 'POST', `/r/${form}`, { values: { visitor: 'Documented Visitor' } })

  await page.goto('/admin/documents')
  await expect(page.getByTestId('documents-view')).toBeVisible()
  await page.getByTestId('doc-new').first().click()
  await expect(page.getByTestId('document-editor')).toBeVisible()
  const key = `pass_${randomUUID().slice(0, 6)}`
  await page
    .getByTestId('doc-name')
    .locator('input')
    .first()
    .fill(`Visit pass ${key.slice(-6)}`)
  await page
    .getByTestId('doc-name')
    .getByRole('button', { name: /Other languages/ })
    .click()
  await page
    .getByTestId('doc-name')
    .getByLabel('Name (العربية)')
    .fill(`تصريح زيارة ${key.slice(-6)}`)
  await page.getByTestId('doc-key').fill(key)
  await chooseForm(page)
  await page.getByTestId('tpl-add-block').click()
  await page.getByTestId('tpl-block-0').locator('textarea').first().fill('Visit pass for **{{ visitor }}**')
  await chooseRecord(page)
  const preview = page.frameLocator('[data-testid="doc-preview"]')
  await expect(preview.locator('body')).toContainText('Visit pass for', { timeout: 15_000 })
  await page.getByTestId('doc-save').click()
  await expect(page.getByTestId('doc-bar')).toContainText('All changes saved')
  await page.screenshot({ path: 'test-results/documents-design.png', fullPage: true })

  // The saved template, generated for the chosen record.
  await chooseRecord(page)
  const download = page.waitForEvent('download')
  await page.getByTestId('doc-generate').click()
  const file = await download
  expect(file.suggestedFilename()).toMatch(new RegExp(`^Visit pass ${key.slice(-6)}.*\\.pdf$`))
  expect(
    readFileSync(await file.path())
      .subarray(0, 4)
      .toString(),
  ).toBe('%PDF')

  expect(problems).toEqual([])
})

test('a Word template is uploaded, its placeholders found, and generated', async ({ page }) => {
  const problems = watchConsole(page)
  await page.goto('/admin/documents/new')
  await expect(page.getByTestId('document-editor')).toBeVisible()
  const key = `letter_${randomUUID().slice(0, 6)}`
  await page
    .getByTestId('doc-name')
    .locator('input')
    .first()
    .fill(`Visit letter ${key.slice(-6)}`)
  await page.getByTestId('doc-key').fill(key)
  await chooseForm(page)
  await page.getByTestId('doc-kind').getByText('A Word file').click()
  await page.getByTestId('doc-upload-en').setInputFiles('e2e/fixtures/visit-letter.docx')
  await expect(page.getByTestId('doc-found')).toHaveText('2 placeholders found.')
  await page.getByTestId('doc-format-docx').click()
  await page.getByTestId('doc-save').click()
  await expect(page.getByTestId('doc-bar')).toContainText('All changes saved')
  await expect(page.getByTestId('doc-file-en')).toContainText('visit-letter.docx')
  await page.screenshot({ path: 'test-results/documents-word.png', fullPage: true })

  await chooseRecord(page)
  await page.locator('#doc-gen-format').click()
  await page.getByRole('option', { name: 'Word' }).click()
  const download = page.waitForEvent('download')
  await page.getByTestId('doc-generate').click()
  expect((await download).suggestedFilename()).toMatch(/\.docx$/)

  expect(problems).toEqual([])
})

test('a notification rule attaches a document', async ({ page }) => {
  const problems = watchConsole(page)
  const form = await visitForm(page)
  await page.goto(`/admin/forms/${form}/configure/notifications`)
  await expect(page.getByTestId('nr-rule-0')).toBeVisible()
  // Documents go with emails: the rule from 07 only notifies in the app until email is on.
  if (!(await page.locator('#nr-ch-email-0').isChecked())) {
    await expect(page.getByTestId('nr-add-doc-0')).toHaveCount(0)
    await page.locator('#nr-ch-email-0').click()
  }
  await page.getByTestId('nr-add-doc-0').click()
  await expect(page.getByTestId('nr-doc-0-0')).toBeVisible()
  const attached = await page.locator('[data-testid^="nr-doc-0-"]').count()
  await page.getByTestId('nr-save').click()
  await expect(page.getByTestId('nr-bar')).toContainText('All changes saved')
  const rules = ((await api(page, 'GET', `/forms/${form}/notification-rules`)).data as { rules: { attachments: { documents: unknown[] } | null }[] }).rules
  expect(rules[0]!.attachments?.documents.length).toBe(attached)
  await page.screenshot({ path: 'test-results/documents-rule.png', fullPage: true })

  expect(problems).toEqual([])
})
