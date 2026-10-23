import { expect, request, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Every screen added in Phases 4 and 5, opened in English and Arabic on a desktop
 * window and a phone: the page reads in the language's direction, nothing
 * makes the page scroll sideways, and the screen has its title. This is the
 * half of the design checklist (docs/design-system.md §5.7) that needs the
 * rendered page; src/design/screens.spec.ts checks the rest from the source.
 * A new screen is added to SCREENS.
 */
test.describe.configure({ mode: 'serial' })

test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function api(page: Page, method: 'GET' | 'PATCH', path: string, data?: unknown): Promise<Record<string, unknown>> {
  // A same-site request, as the app sends it (the API accepts the session only from its own pages).
  const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value)
  const origin = new URL(page.url()).origin
  const res = await page.request.fetch(`/api/v1${path}`, {
    method,
    data,
    headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: origin, Referer: page.url() },
  })
  expect(res.status(), `${method} ${path}`).toBeLessThan(300)
  return (await res.json()) as Record<string, unknown>
}

async function setLanguage(page: Page, code: 'en' | 'ar'): Promise<void> {
  const rtl = (await page.locator('html').getAttribute('dir')) === 'rtl'
  if (rtl === (code === 'ar')) return
  const saved = page.waitForResponse((r) => r.url().endsWith('/me/preferences') && r.request().method() === 'PATCH')
  await page.getByTestId('language-switcher').click()
  await page.getByRole('option', { name: code === 'ar' ? 'العربية' : 'English' }).click()
  await saved
  await expect(page.locator('html')).toHaveAttribute('dir', code === 'ar' ? 'rtl' : 'ltr')
}

const SIZES = [
  { name: 'desktop', width: 1280, height: 800 },
  { name: 'phone', width: 390, height: 844 },
]

test('every Phase 4 and Phase 5 screen reads in both directions without sideways scrolling, on a desktop and a phone', async ({ page }) => {
  test.setTimeout(1_200_000)
  const problems = watchConsole(page)
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid
  const SCREENS = [
    '/admin/operations',
    '/admin/operations/emails',
    '/admin/operations/submissions',
    '/admin/operations/jobs',
    '/admin/operations/alerts',
    '/admin/notifications',
    '/admin/notifications/new',
    '/admin/documents',
    '/admin/documents/new',
    '/admin/automations',
    `/admin/forms/${form}/automations/new`,
    '/admin/scheduled-tasks',
    `/admin/forms/${form}/configure/notifications`,
    `/admin/forms/${form}/configure/actions`,
    `/admin/forms/${form}/configure/downloads`,
    `/admin/forms/${form}/downloads/new`,
    `/admin/forms/${form}/configure/automations`,
    `/admin/forms/${form}/configure/data_quality`,
    `/app/${form}/downloads`,
    '/admin/integrations',
    '/admin/integrations/inbound',
    '/admin/integrations/tokens',
    '/admin/integrations/webhooks/new',
    '/admin/integrations/inbound/new',
    '/profile?tab=tokens',
    '/admin/extensions',
    '/admin/extensions/new',
    '/admin/packages',
    '/admin/packages/reports',
    '/admin/reports',
    '/admin/reports/dashboards',
    '/admin/reports/r/new',
    '/admin/reports/d/new',
    '/insights',
    '/admin/home-screens',
    '/admin/home-screens/new',
    '/admin/announcements',
    '/admin/search',
    '/admin/themes',
    '/admin/themes/new',
    '/admin/notification-channels',
    '/admin/notification-channels/new',
    '/admin/integrations/sources',
    '/admin/integrations/sources/new',
    '/admin/integrations/sync',
    '/admin/integrations/sync/new',
    '/admin/external',
    '/admin/external/accounts',
    '/admin/external/forms/new',
    '/admin/access-policies',
    '/x/portal',
  ]

  await page.goto('/admin')
  // Opening 112 pages back to back is far above a person's pace: the sweep lifts the API rate limit (a System
  // Setting) while it runs and puts it back after, so it never measures the limiter instead of the screens.
  const limit = ((await api(page, 'GET', '/settings/security')).data as { api_rate_limit_per_minute: number }).api_rate_limit_per_minute
  await api(page, 'PATCH', '/settings/security', { api_rate_limit_per_minute: 10_000 })
  try {
    for (const code of ['en', 'ar'] as const) {
      await setLanguage(page, code)
      for (const size of SIZES) {
        await page.setViewportSize({ width: size.width, height: size.height })
        for (const url of SCREENS) {
          const where = `${code} ${size.name} ${url}`
          await page.goto(url)
          await expect(page.locator('main').getByRole('heading').first(), where).toBeVisible()
          await page.waitForLoadState('networkidle')
          await expect(page.locator('html'), where).toHaveAttribute('dir', code === 'ar' ? 'rtl' : 'ltr')
          const m = await page.evaluate(() => {
            const main = document.querySelector('main')!
            // What sticks out of the page or of the main area, other than what scrolls inside its own box (tables, code).
            const wide = [...main.querySelectorAll<HTMLElement>('*')]
              .filter((el) => {
                const r = el.getBoundingClientRect()
                if (r.width === 0 || getComputedStyle(el).position === 'fixed') return false
                const box = main.getBoundingClientRect()
                return r.left < box.left - 1 || r.right > box.right + 1
              })
              .filter((el) => {
                const own = el.closest('.p-datatable-table-container, .p-tablist-content, .overflow-auto, .overflow-x-auto, pre, .sr-only, .p-hidden-accessible, .monaco-editor')
                return own === null || own === main
              })
              .slice(0, 3)
              .map((el) => `${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 3).join('.')} "${(el.textContent ?? '').trim().slice(0, 40)}"`)
            return { pageScrollsSideways: document.documentElement.scrollWidth > window.innerWidth + 1 || main.scrollWidth > main.clientWidth + 1, wide }
          })
          expect(m.wide, where).toEqual([])
          expect(m.pageScrollsSideways, where).toBe(false)
          if (size.name === 'phone' && url === '/admin/operations') await page.screenshot({ path: `test-results/screens-${code}-phone-operations.png`, fullPage: true })
        }
      }
    }
  } finally {
    await setLanguage(page, 'en')
    await api(page, 'PATCH', '/settings/security', { api_rate_limit_per_minute: limit })
    // The sweep's requests still count in the limiter's current minute: once the ordinary limit is back,
    // wait that minute out, so the next spec's sign-in is not refused with 429 (which reads as signed out).
    // Both buckets: the signed-in one (per user) and the anonymous one (per address), which the sign-in
    // and maintenance pages of the sweep used and which the next spec's sign-in page uses first.
    const anonymous = await request.newContext({ baseURL: new URL(page.url()).origin })
    const status = async (ctx: typeof page.request) => (await ctx.get('/api/v1/me', { headers: { Accept: 'application/json' } })).status()
    await expect.poll(async () => [await status(page.request), await status(anonymous)].includes(429), { timeout: 90_000, intervals: [5_000] }).toBe(false)
    await anonymous.dispose()
  }
  expect(problems).toEqual([])
})
