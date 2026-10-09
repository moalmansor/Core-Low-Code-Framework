import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/** The interface system (docs/design-system.md): page frame, data tables, properties panel, theme, brand colour. */
test.describe.configure({ mode: 'serial' })

test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function setLanguage(page: Page, code: 'en' | 'ar'): Promise<void> {
  const rtl = (await page.locator('html').getAttribute('dir')) === 'rtl'
  if (rtl === (code === 'ar')) return
  const saved = page.waitForResponse((r) => r.url().endsWith('/me/preferences') && r.request().method() === 'PATCH')
  await page.getByTestId('language-switcher').click()
  await page.getByRole('option', { name: code === 'ar' ? 'العربية' : 'English' }).click()
  await saved
  await expect(page.locator('html')).toHaveAttribute('dir', code === 'ar' ? 'rtl' : 'ltr')
}

test('the page frame fills the window at every size and zoom, in both directions', async ({ page }) => {
  const problems = watchConsole(page)
  // CSS viewports of common windows, plus 1920×1080 at 150 % zoom (1280×720) and at 80 % zoom (2400×1350).
  const sizes: [number, number][] = [
    [1280, 720],
    [1440, 900],
    [1920, 1080],
    [2400, 1350],
    [2560, 1440],
  ]
  for (const code of ['en', 'ar'] as const) {
    await setLanguage(page, code)
    for (const [width, height] of sizes) {
      await page.setViewportSize({ width, height })
      for (const area of ['departments', 'users', 'audit_log']) {
        await page.getByTestId(`nav-${area}`).click()
        await expect(page.locator('main h1')).toBeVisible()
        const m = await page.evaluate(() => {
          const side = document.querySelector('[data-testid="app-sidebar"]')!.getBoundingClientRect()
          const nav = document.querySelector('[data-testid="sidebar"]') as HTMLElement
          return {
            sidebarTop: side.top,
            sidebarBottom: side.bottom,
            viewport: window.innerHeight,
            pageScrolls: document.documentElement.scrollHeight > window.innerHeight + 1,
            navScrollable: nav.scrollHeight > nav.clientHeight,
            navOverflowsBox: nav.getBoundingClientRect().bottom > side.bottom + 1,
          }
        })
        const where = `${code} ${width}×${height} ${area}`
        expect(m.sidebarTop, where).toBe(0)
        expect(Math.abs(m.sidebarBottom - m.viewport), where).toBeLessThanOrEqual(1)
        expect(m.pageScrolls, where).toBe(false)
        expect(m.navOverflowsBox, where).toBe(false)
        // The full menu is longer than a 720px window: it scrolls inside the sidebar.
        if (height <= 720) expect(m.navScrollable, where).toBe(true)
      }
    }
  }
  await setLanguage(page, 'en')
  expect(problems).toEqual([])
})

test('a brand colour set in Appearance & Branding reaches every screen, and an unreadable one is corrected', async ({ page }) => {
  const problems = watchConsole(page)
  await page.goto('/admin/settings/branding')
  const editor = page.getByTestId('brand-colour')
  await expect(editor).toBeVisible()
  // Too light to read as text: the editor offers the nearest readable shade.
  await page.getByTestId('brand-primary').fill('#7dd3fc')
  await expect(page.getByTestId('brand-primary-fails')).toBeVisible()
  await page.getByTestId('brand-primary-use').click()
  await expect(page.getByTestId('brand-primary-ok')).toBeVisible()
  const chosen = (await page.getByTestId('brand-primary').inputValue()).toLowerCase()
  await page.getByTestId('settings-save-branding').click()
  await expect(page.getByTestId('brand-primary')).toHaveValue(chosen)

  // After a reload, every screen uses it: the root token and a primary button.
  await page.goto('/admin/forms')
  await expect(page.getByTestId('forms-table')).toBeVisible()
  await expect.poll(() => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--primary').trim().toLowerCase())).toBe(chosen)
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(chosen.slice(i, i + 2), 16))
  await expect.poll(() => page.getByTestId('form-new').evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(`rgb(${r}, ${g}, ${b})`)

  // Back to the theme default for the other tests.
  await page.goto('/admin/settings/branding')
  await page.getByTestId('brand-primary').fill('')
  await page.getByTestId('settings-save-branding').click()
  await expect(page.getByTestId('brand-primary')).toHaveValue('')
  expect(problems).toEqual([])
})

test('the properties panel finds a setting across tabs and keeps sections tidy', async ({ page }) => {
  const problems = watchConsole(page)
  await page.goto('/')
  await page.getByTestId('nav-app-operations').getByRole('link', { name: 'Visit requests' }).click()
  await expect(page.getByTestId('records-table')).toBeVisible()
  const form = page.url().split('/app/')[1]!.split(/[/?]/)[0]!
  await page.goto(`/admin/forms/${form}/builder`)
  await page.getByTestId('canvas-visitor').click()
  const panel = page.getByTestId('field-properties')

  // A few tabs; the rarely used ones sit under More.
  await expect(panel.getByTestId('tab-general')).toBeVisible()
  await expect(panel.getByTestId('tab-table')).toHaveCount(0)
  await expect(panel.getByTestId('section-field-basics')).toBeVisible()
  await expect(panel.getByTestId('prop-required')).toBeVisible()
  // Help text starts folded; opening it shows its settings.
  await expect(panel.getByTestId('section-field-help')).toHaveAttribute('data-open', 'false')
  await panel.getByTestId('section-field-help').getByRole('button', { name: 'Help text' }).click()
  await expect(panel.getByTestId('section-field-help')).toHaveAttribute('data-open', 'true')
  // Only the interface language is shown; the others fold under one line.
  await expect(panel.getByTestId('prop-label').locator('input')).toHaveCount(1)

  // "Find a setting" reaches a setting on another tab, opened, and the tabs step aside.
  await panel.getByTestId('panel-search').fill('filter')
  await expect(panel.getByTestId('section-field-column')).toHaveAttribute('data-open', 'true')
  await expect(panel.getByTestId('section-field-basics')).toHaveCount(0)
  await expect(panel.getByTestId('tab-general')).toHaveCount(0)
  await panel.getByTestId('section-field-column').getByRole('switch', { name: 'Filterable' }).check()
  await panel.getByTestId('panel-search').fill('')
  await expect(panel.getByTestId('tab-general')).toBeVisible()

  // The More menu opens the table tab, where the change is kept.
  await panel.getByTestId('tab-more').click()
  await page.getByRole('menuitem', { name: 'Table & export' }).click()
  await expect(panel.getByTestId('section-field-column').getByRole('switch', { name: 'Filterable' })).toBeChecked()

  // Published, so the records list can filter on the visitor.
  await page.getByTestId('save-now').click()
  await expect(page.getByTestId('save-state')).toContainText(/saved/i)
  await page.getByTestId('open-publish').click()
  await page.getByTestId('publish-continue').click()
  await page.getByTestId('publish-confirm').click()
  await expect(page.getByTestId('publish-dialog')).toContainText(/applied|published/i, { timeout: 60_000 })
  expect(problems).toEqual([])
})

test('the records table counts, filters through the URL, and deletes a selection', async ({ page }) => {
  const problems = watchConsole(page)
  await page.goto('/')
  await page.getByTestId('nav-app-operations').getByRole('link', { name: 'Visit requests' }).click()
  await expect(page.getByTestId('records-table')).toBeVisible()
  const form = page.url().split('/app/')[1]!.split(/[/?]/)[0]!
  const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value)
  const headers = { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: new URL(page.url()).origin, Referer: page.url() }
  for (const visitor of ['Zainab Ali', 'Zaid Omar', 'زينب علي']) expect((await page.request.post(`/api/v1/r/${form}`, { data: { values: { visitor } }, headers })).status()).toBe(201)
  await page.reload()
  await expect(page.getByTestId('records-count')).toHaveText(/^1–\d+ of \d+$/)

  // A typed filter: visitor starts with "Zai".
  await page.getByTestId('records-add-filter').click()
  await page.getByRole('option', { name: 'Visitor' }).click()
  const row = page.getByTestId('records-filter-visitor')
  await row.getByRole('combobox', { name: 'Condition' }).click()
  await page.getByRole('option', { name: 'Starts with' }).click()
  await row.getByRole('textbox', { name: 'Value' }).fill('Zai')
  await page.getByTestId('records-apply-filters').click()
  await expect(page.getByTestId('records-count')).toHaveText('1–2 of 2')
  expect(decodeURIComponent(page.url())).toContain('f.visitor=starts_with:Zai')

  // The URL carries the list: a reload (or a shared link) shows the same records.
  await page.reload()
  await expect(page.getByTestId('records-count')).toHaveText('1–2 of 2')
  const rows = page.getByTestId('records-table').locator('tbody tr')
  await expect(rows).toHaveCount(2)
  await expect(page.getByTestId('records-table')).not.toContainText('زينب علي')

  // Select both, delete them from the Actions menu; each delete is version-checked by the server.
  await rows.nth(0).getByRole('checkbox').check()
  await rows.nth(1).getByRole('checkbox').check()
  await page.getByTestId('records-actions').click()
  await page.getByRole('menuitem', { name: 'Delete 2 selected' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click()
  await expect(page.getByText('2 records deleted')).toBeVisible()
  await expect(page.getByTestId('records-count')).toHaveText('No records')
  expect(problems).toEqual([])
})
