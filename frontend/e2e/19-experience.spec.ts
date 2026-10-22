import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Pages, navigation, home screens, announcements and global search
 * (specification §4.29): a content page built in its editor and placed in
 * the menu, a home screen, a banner that can be dismissed, and a record found
 * from the search box.
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

interface MenuNode {
  uuid: string
  type: string
  labels?: Record<string, string>
  children?: MenuNode[]
  [k: string]: unknown
}
/** The admin menu tree as the PUT expects it (labels per language under `label`). */
const asPayload = (nodes: MenuNode[]): unknown[] =>
  nodes.map((n) => {
    const out: Record<string, unknown> = { ...n, label: n.labels ?? {}, children: asPayload(n.children ?? []) }
    delete out.labels
    delete out.permission
    return out
  })

test('a page is built and placed in the menu, a home screen and a banner greet people, and search finds a record', async ({ page }) => {
  test.setTimeout(240_000)
  const problems = watchConsole(page)
  await page.goto('/')
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string; application: { uuid: string } }[]
  const form = forms.find((f) => f.key === 'visit_requests')!
  const app = form.application.uuid

  // A content page, written in its editor.
  await page.goto(`/admin/applications/${app}/pages/new`)
  await expect(page.getByTestId('page-editor')).toBeVisible()
  await page.getByRole('textbox', { name: 'Name (English)' }).fill('Visitor guide')
  await page.getByTestId('page-key').fill('visitor_guide')
  await page.getByTestId('page-content-en').getByRole('textbox').fill('Bring your ID to reception.')
  await page.locator('#page-published').click()
  await page.getByTestId('page-save').click()
  await expect(page).toHaveURL(/\/pages\/[0-9a-f-]{36}$/)
  const pageUuid = page.url().split('/').pop()!

  // Placed in the menu, with the rest of the menu kept.
  const menu = (await api(page, 'GET', `/applications/${app}/menu`)).data as MenuNode[]
  await api(page, 'PUT', `/applications/${app}/menu`, { items: [...asPayload(menu), { type: 'page', target: pageUuid, label: { en: 'Visitor guide', ar: 'دليل الزائر' }, children: [] }] })
  await page.goto('/')
  await page.getByTestId('sidebar').getByRole('link', { name: 'Visitor guide' }).click()
  await expect(page.getByTestId('page-content')).toContainText('Bring your ID to reception.')

  // A home screen for everyone.
  const home = (
    await api(page, 'POST', '/home-screens', {
      screen: {
        application: null,
        target_type: 'default',
        target: null,
        priority: 0,
        active: true,
        i18n: { name: { en: 'Welcome desk' } },
        widgets: [{ type: 'html', i18n: { title: { en: 'Today' }, text: { en: '<p>Reception opens at eight.</p>' } }, layout: { w: 12, h: 1 } }],
      },
    })
  ).data as { uuid: string }
  await page.goto('/')
  await expect(page.getByTestId('home-widgets')).toContainText('Reception opens at eight.')

  // A banner, dismissed for good.
  const banner = (
    await api(page, 'POST', '/announcements', {
      announcement: { severity: 'warning', placement: 'banner', published: true, dismissible: true, roles: [], i18n: { title: { en: 'Fire drill at noon' }, body: {} } },
    })
  ).data as { uuid: string }
  await page.reload()
  await expect(page.getByTestId(`banner-${banner.uuid}`)).toContainText('Fire drill at noon')
  await page.getByTestId(`banner-${banner.uuid}`).getByRole('button', { name: 'Dismiss' }).click()
  await page.reload()
  await expect(page.getByTestId('home-widgets')).toBeVisible()
  await expect(page.getByTestId(`banner-${banner.uuid}`)).toHaveCount(0)

  // Global search over the visitor's name.
  const config = ((await api(page, 'GET', '/search-configs')).data as { form: string; available: { uuid: string; key: string }[] }[]).find((c) => c.form === form.uuid)!
  const visitor = config.available.find((f) => f.key === 'visitor')!.uuid
  await api(page, 'PUT', `/search-configs/${form.uuid}`, { config: { enabled: true, fields: [{ field: visitor, weight: 3 }], subtitle: null, roles: [] } })
  const records = (await api(page, 'GET', `/r/${form.uuid}?per_page=1`)).data as { uuid: string; values: { visitor?: string } }[]
  const name = String(records[0]!.values.visitor ?? '')
  await page.getByTestId('global-search').fill(name.slice(0, Math.max(2, Math.min(name.length, 6))))
  await page.getByTestId('global-search-results').getByRole('button').first().click()
  await expect(page).toHaveURL(new RegExp(`/app/${form.uuid}/[0-9a-f-]{36}$`))

  // Leave the shared environment as it was.
  await api(page, 'DELETE', `/home-screens/${home.uuid}`)
  await api(page, 'DELETE', `/announcements/${banner.uuid}`)
  expect(problems).toEqual([])
})
