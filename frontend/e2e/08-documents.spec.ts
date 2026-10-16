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
  await page.getByTestId('tpl-add-first-block').click()
  await page.getByTestId('tpl-block-choice-text').click()
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

test('values are picked with their format; a header, footer, signatures and a QR code appear; the template is reused through a blueprint', async ({ page }) => {
  const problems = watchConsole(page)
  const form = await visitForm(page)
  await api(page, 'POST', `/r/${form}`, { values: { visitor: 'Blueprint Visitor' } })
  await page.goto('/admin/documents/new')
  await expect(page.getByTestId('document-editor')).toBeVisible()
  const key = `badge_${randomUUID().slice(0, 6)}`
  const name = `Visitor badge ${key.slice(-6)}`
  await page.getByTestId('doc-name').locator('input').first().fill(name)
  await page.getByTestId('doc-key').fill(key)
  await chooseForm(page)

  // A text block whose value is chosen, with how it is shown, rather than typed.
  await page.getByTestId('tpl-add-first-block').click()
  await expect(page.getByTestId('tpl-block-choice')).toContainText('Linked records')
  await page.getByTestId('tpl-block-choice-text').click()
  await page.getByTestId('tpl-block-0').locator('textarea').first().fill('Visitor: ')
  await page.getByTestId('tpl-text-0-pick').click()
  const pick = page.getByTestId('tpl-text-0-pick-dialog')
  await pick.locator('.p-treeselect').click()
  await page.getByRole('treeitem', { name: 'Visitor' }).first().click()
  await pick.locator('#tpl-text-0-pick-fmt-dig').click()
  await page.getByRole('option', { name: 'Arabic-Indic (١٢٣)' }).click()
  await expect(pick).toContainText('{{ visitor | digits:arabic }}')
  await page.screenshot({ path: 'test-results/documents-picker.png', fullPage: true })
  await pick.getByTestId('tpl-text-0-pick-use').click()
  await expect(page.getByTestId('tpl-block-0').locator('textarea').first()).toHaveValue('Visitor: {{ visitor | digits:arabic }}')

  // Title, a header with the logo and a footer with page numbers.
  await page.getByTestId('doc-title').locator('input').first().fill('Badge')
  await page.locator('#doc-header').click()
  await page.getByTestId('doc-header-text').locator('input').first().fill('Front desk')
  await page.locator('#doc-footer').click()
  await page.getByTestId('doc-footer-text').locator('input').first().fill('Keep this badge visible')

  // Signatures and a QR code, from the block gallery.
  await page.getByTestId('tpl-add-block').click()
  await page.getByTestId('tpl-block-choice-signature').click()
  await page.getByTestId('tpl-add-block').click()
  await page.getByTestId('tpl-block-choice-code').click()

  await chooseRecord(page)
  const preview = page.frameLocator('[data-testid="doc-preview"]')
  await expect(preview.locator('body')).toContainText('Front desk', { timeout: 15_000 })
  await expect(preview.locator('body')).toContainText('Page 1 of 1')
  await expect(preview.locator('h1.doc-title')).toHaveText('Badge')
  await expect(preview.locator('.sign-label')).toHaveText('Signature')
  await expect(preview.locator('.code img')).toHaveCount(1)
  await page.getByTestId('doc-save').click()
  await expect(page.getByTestId('doc-bar')).toContainText('All changes saved')
  await page.screenshot({ path: 'test-results/documents-page.png', fullPage: true })

  // Saved as a blueprint, then a new template made from it.
  await page.getByTestId('doc-save-blueprint').click()
  const bp = page.getByTestId('doc-blueprint-dialog')
  await expect(bp.getByRole('textbox').first()).toHaveValue(name)
  await bp.getByTestId('doc-blueprint-save').click()
  await expect(page.getByText('Saved as a blueprint.')).toBeVisible()
  await page.goto('/admin/documents')
  await page.getByTestId('doc-from-blueprint').click()
  const from = page.getByTestId('doc-from-dialog')
  await from.locator('#doc-from-bp').click()
  await page.getByRole('option', { name }).click()
  await from.locator('#doc-from-form').click()
  await page.getByRole('option', { name: 'Visit requests' }).click()
  await from.getByTestId('doc-from-key').fill(`${key}_copy`)
  await from.getByTestId('doc-from-make').click()
  await expect(page.getByTestId('document-editor')).toBeVisible()
  await expect(page.getByTestId('doc-key')).toHaveValue(`${key}_copy`)
  await expect(page.getByTestId('doc-title').locator('input').first()).toHaveValue('Badge')
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
