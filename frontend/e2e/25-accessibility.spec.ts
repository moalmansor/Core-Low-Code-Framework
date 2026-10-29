import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { sameSite, signIn, withRateLimitLifted } from './support.ts'

/**
 * WCAG 2.1 AA (specification §6): axe-core's WCAG 2.0/2.1 A and AA rules on
 * every admin screen without a parameter, the home screen, My Work, the
 * profile, and a form's list, new-record form and record page, in English
 * (left to right) and in Arabic (right to left). Any violation fails, with
 * the screen, the rule and the elements it found.
 */
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

test('every screen meets WCAG 2.1 AA in English and in Arabic', async ({ page }) => {
  test.setTimeout(1_800_000)
  await page.goto('/')
  const form = ((await sameSite(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]).find((f) => f.key === 'visit_requests')!
  const record = ((await sameSite(page, 'GET', `/r/${form.uuid}?per_page=1`)).data as { uuid: string }[])[0]
  const routes = await page.evaluate(() => {
    const app = (document.querySelector('#app') as unknown as { __vue_app__: { config: { globalProperties: { $router: { getRoutes(): { path: string }[] } } } } }).__vue_app__
    return app.config.globalProperties.$router.getRoutes().map((r) => r.path)
  })
  const screens = [
    '/',
    '/my-work',
    '/profile',
    ...routes.filter((p: string) => (p === '/admin' || p.startsWith('/admin/')) && !p.includes(':')),
    `/app/${form.uuid}`,
    `/app/${form.uuid}/new`,
    ...(record ? [`/app/${form.uuid}/${record.uuid}`] : []),
  ]
  expect(screens.length).toBeGreaterThan(30)

  const violations: string[] = []
  await withRateLimitLifted(page, async () => {
    for (const lang of ['en', 'ar'] as const) {
      await page.goto('/')
      await setLanguage(page, lang)
      for (const url of screens) {
        await page.goto(url)
        await expect(page.locator('main').getByRole('heading').first(), `${url} renders`).toBeVisible()
        await page.waitForLoadState('networkidle')
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()
        for (const v of result.violations) {
          violations.push(`[${lang}] ${url}: ${v.id} (${v.impact}) — ${v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} [${(n.failureSummary ?? '').replace(/\s+/g, ' ').slice(0, 160)}]`).join(' | ')}`)
        }
      }
    }
    await page.goto('/')
    await setLanguage(page, 'en')
  })
  test.info().annotations.push({ type: 'screens', description: `${screens.length} screens checked in each language` })
  expect(violations).toEqual([])
})
