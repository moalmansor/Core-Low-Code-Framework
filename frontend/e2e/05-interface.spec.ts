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
