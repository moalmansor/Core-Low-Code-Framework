import { expect, test, type Page } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Phase 4 notifications end to end, on the "Visit requests" form of 04-forms:
 * an email template designed with its live preview, a notification rule in
 * the form configuration, then a new record notifies its creator in the
 * notifications center, and the profile offers the channels.
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

test('a template is designed with its preview, a rule notifies the creator, and the bell shows it', async ({ page }) => {
  const problems = watchConsole(page)
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid

  // The template designer: basics, subject, a text block, and the server-rendered preview.
  await page.goto('/admin/notifications')
  await expect(page.getByTestId('templates-view')).toBeVisible()
  await page.getByTestId('tpl-new').first().click()
  await expect(page.getByTestId('template-editor')).toBeVisible()
  const key = `visit_${randomUUID().slice(0, 6)}`
  await page.getByTestId('tpl-key').fill(key)
  await page.locator('#tpl-form').click()
  await page.getByRole('option', { name: 'Visit requests' }).click()
  await page
    .getByTestId('tpl-subject')
    .locator('input')
    .first()
    .fill(`New visit ${key.slice(-6)}: {{ visitor }}`)
  await page
    .getByTestId('tpl-subject')
    .getByRole('button', { name: /Other languages/ })
    .click()
  await page
    .getByTestId('tpl-subject')
    .getByLabel('Subject (العربية)')
    .fill(`زيارة جديدة ${key.slice(-6)}: {{ visitor }}`)
  await page.getByTestId('tpl-add-first-block').click()
  await page.getByTestId('tpl-block-choice-text').click()
  await expect(page.getByTestId('tpl-block-0')).toBeVisible()
  await page.getByTestId('tpl-block-0').locator('textarea').first().fill('A visit was requested by **{{ visitor }}**.')
  await expect(page.getByTestId('tpl-preview-subject')).toContainText('New visit', { timeout: 10_000 })
  await page.getByTestId('tpl-save').click()
  await expect(page.getByTestId('tpl-bar')).toContainText('All changes saved')
  await page.screenshot({ path: 'test-results/notifications-template.png', fullPage: true })

  // A rule in the form configuration: on create, tell the creator in the notifications center.
  const templates = (await api(page, 'GET', `/notifications/templates?form=${form}`)).data as { uuid: string; subject: string }[]
  await page.goto(`/admin/forms/${form}/configure/notifications`)
  await expect(page.getByTestId('notifications-editor')).toBeVisible()
  // Re-runs against the same database find earlier rules; the new one goes last.
  const existing = await page.locator('[data-testid^="nr-rule-"]').count()
  const rule = page.getByTestId(`nr-rule-${existing}`)
  await page.getByTestId('nr-add').click()
  await expect(rule).toBeVisible()
  await rule.getByLabel('Name (English)').fill('Visit created')
  await rule
    .getByRole('button', { name: /Other languages/ })
    .first()
    .click()
  await rule.getByLabel('Name (العربية)').fill('إنشاء زيارة')
  await page.getByTestId(`nr-template-${existing}`).click()
  await page.getByRole('option', { name: templates.find((x) => x.subject.includes(key.slice(-6)))!.subject }).click()
  await page.getByTestId('nr-save').click()
  await expect(page.getByTestId('nr-bar')).toContainText('All changes saved')
  await page.screenshot({ path: 'test-results/notifications-rule.png', fullPage: true })

  // A new record: the creator is told in the notifications center.
  const before = ((await api(page, 'GET', '/me/notifications/count')).data as { unread: number }).unread
  await api(page, 'POST', `/r/${form}`, { values: { visitor: 'Notified Visitor' } })
  // Rules left by earlier runs on the same database may notify too, so the count only has to grow.
  await expect.poll(async () => ((await api(page, 'GET', '/me/notifications/count')).data as { unread: number }).unread, { timeout: 70_000 }).toBeGreaterThan(before)
  const unread = ((await api(page, 'GET', '/me/notifications/count')).data as { unread: number }).unread
  await page.goto('/')
  await expect(page.getByTestId('notifications-unread')).toHaveText(String(unread))
  await page.getByTestId('notifications-bell').click()
  await expect(page.getByTestId('notifications-panel')).toContainText(`New visit ${key.slice(-6)}: Notified Visitor`)
  await page.screenshot({ path: 'test-results/notifications-panel.png' })
  await page
    .getByTestId('notifications-panel')
    .getByText(`New visit ${key.slice(-6)}: Notified Visitor`)
    .first()
    .click()
  await expect(page.getByTestId('record-view')).toBeVisible()

  // People choose their channels on their profile.
  await page.goto('/profile')
  await page.getByRole('tab', { name: 'Preferences' }).click()
  await expect(page.getByTestId('notification-channels')).toBeVisible()

  expect(problems).toEqual([])
})
