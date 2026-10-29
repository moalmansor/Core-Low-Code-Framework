import { expect, test, type Page, type Response } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { sameSite, signIn, watchConsole, withRateLimitLifted } from './support.ts'

/**
 * The unreachable-screen guard: every route the client registers under /admin
 * (read from the running application's router, so a new route is covered the
 * moment it is added) is opened as the Super Admin, and every tab on it is
 * clicked. A screen fails when the API refuses or cannot serve what it asks
 * for (403, 404, 405 or 5xx, or 422 to a GET: the Super Admin holds every
 * permission, so a refusal means the screen cannot be used), when the page
 * throws, or when it renders no heading. tests/Unit/SettingsScreenTest.php checks the same
 * wiring from the source in the fast suite.
 */
test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

interface RouteInfo {
  path: string
  name: string | null
}

/** A required parameter of a route, filled with a real or "new" value; null when no value opens it. */
function fill(path: string, values: Record<string, string>): string | null {
  let missing = false
  const url = path.replace(/\/:([a-zA-Z_]+)(\([^)]*\))?\?/g, '').replace(/\/:([a-zA-Z_]+)(\([^)]*\))?/g, (_, name: string) => {
    const v = values[name]
    if (v === undefined) missing = true
    return `/${v ?? ''}`
  })
  return missing ? null : url
}

test('every admin route resolves and renders, and every tab on it loads', async ({ page }) => {
  test.setTimeout(900_000)
  const problems = watchConsole(page)
  await page.goto('/admin')
  const form = ((await sameSite(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]).find((f) => f.key === 'visit_requests')!
  const application = ((await sameSite(page, 'GET', '/applications')).data as { uuid: string }[])[0]!.uuid
  // Editors open on "new" for an object that does not exist yet; forms and applications take the pilot's.
  const values: Record<string, string> = { form: form.uuid, application }
  for (const p of ['theme', 'screen', 'page', 'profile', 'automation', 'channel', 'template', 'extension', 'webhook', 'endpoint', 'source', 'job', 'report', 'dashboard']) values[p] = 'new'
  // Routes whose parameter means something else: an external form has its own id; a package or drift report is
  // only ever made from a file and a blueprint only from a form, so the earliest existing one is opened
  // (17-packages.spec.ts and 22-blueprints.spec.ts make them).
  const first = async (path: string) => ((await sameSite(page, 'GET', path)).data as { uuid: string }[])[0]?.uuid
  const OVERRIDES: Record<string, Record<string, string | undefined>> = {
    '/admin/external/forms/:form': { form: 'new' },
    '/admin/packages/p/:package': { package: await first('/packages') },
    '/admin/packages/reports/:report': { report: await first('/drift-reports') },
    '/admin/blueprints/:blueprint': { blueprint: await first('/blueprints') },
  }
  const notYet: string[] = []

  const routes = await page.evaluate(() => {
    const app = (document.querySelector('#app') as unknown as { __vue_app__: { config: { globalProperties: { $router: { getRoutes(): { path: string; name?: unknown }[] } } } } }).__vue_app__
    return app.config.globalProperties.$router.getRoutes().map((r) => ({ path: r.path, name: typeof r.name === 'string' ? r.name : null }))
  })
  const admin = routes.filter((r: RouteInfo) => r.path === '/admin' || r.path.startsWith('/admin/'))
  expect(admin.length, 'admin routes registered').toBeGreaterThan(40)

  const failures: string[] = []
  const visited: string[] = []
  let where = ''
  const onResponse = (r: Response) => {
    const url = new URL(r.url())
    if (!url.pathname.startsWith('/api/v1/')) return
    const s = r.status()
    // A 422 to a GET means the screen asked for something the API cannot serve; to a POST it is the server
    // checking a draft (an editor's live preview of an empty template says what is still missing), not a fault.
    const refused = [403, 404, 405].includes(s) || (s === 422 && r.request().method() === 'GET') || s >= 500
    if (refused) failures.push(`${where}: ${r.request().method()} ${url.pathname}${url.search} → ${s}`)
  }

  await withRateLimitLifted(page, async () => {
    page.on('response', onResponse)
    try {
      for (const route of admin) {
        const own = OVERRIDES[route.path]
        if (own && Object.values(own).includes(undefined)) {
          notYet.push(route.path)
          continue
        }
        const url = fill(route.path, { ...values, ...(own as Record<string, string>) })
        expect(url, `${route.path} has a parameter this guard cannot fill: give it a value above`).not.toBeNull()
        where = url!
        await page.goto(url!)
        await expect(page.locator('main').getByRole('heading').first(), `${url} renders its heading`).toBeVisible()
        await page.waitForLoadState('networkidle')
        visited.push(url!)
        await clickEveryTab(page, url!, (w) => (where = w))
      }
    } finally {
      page.off('response', onResponse)
    }
  })
  test.info().annotations.push({ type: 'routes', description: `${visited.length} admin routes opened${notYet.length ? `; none to open yet: ${notYet.join(', ')}` : ''}` })
  expect(failures).toEqual([])
  expect(problems).toEqual([])
})

/** Clicks each tab in the main area once, waiting for what it loads. */
async function clickEveryTab(page: Page, url: string, setWhere: (w: string) => void): Promise<void> {
  const tabs = page.locator('main [role="tab"]')
  const count = await tabs.count()
  for (let i = 0; i < count; i++) {
    const tab = tabs.nth(i)
    if (!(await tab.isVisible()) || (await tab.getAttribute('aria-disabled')) === 'true') continue
    const label = ((await tab.textContent()) ?? '').trim()
    setWhere(`${url} [tab ${label}]`)
    await tab.click()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('main').getByRole('heading').first(), `${url} [tab ${label}] still renders`).toBeVisible()
  }
}
