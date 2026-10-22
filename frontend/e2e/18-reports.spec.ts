import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * Reports and dashboards (specification §4.18): a report previewed in its
 * builder, read as a chart and as a table, exported, and placed on a
 * dashboard built on the grid.
 */
test.beforeEach(async ({ page }) => {
  await signIn(page, readFileSync('e2e/.secret', 'utf8').trim())
})

async function api(page: Page, method: 'GET' | 'POST', path: string, data?: unknown): Promise<Record<string, unknown>> {
  const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value)
  const res = await page.request.fetch(`/api/v1${path}`, {
    method,
    data,
    headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: new URL(page.url()).origin, Referer: page.url() },
  })
  expect(res.status(), `${method} ${path}: ${await res.text()}`).toBeLessThan(300)
  return (await res.json()) as Record<string, unknown>
}

test('a report is previewed, read as a chart and a table, exported, and shown on a dashboard', async ({ page }) => {
  test.setTimeout(180_000)
  const problems = watchConsole(page)
  await page.goto('/admin/reports')
  await expect(page.getByTestId('reports-admin')).toBeVisible()
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid
  const made = (
    await api(page, 'POST', '/reports', {
      report: {
        key: 'visits_by_month', form, visualization: 'bar', i18n: { name: { en: 'Visits by month', ar: 'الزيارات حسب الشهر' } },
        dimensions: [{ uuid: crypto.randomUUID(), path: ['@created_at'], bucket: 'month', i18n: { label: {} } }],
        measures: [{ uuid: crypto.randomUUID(), aggregate: 'count', path: null, i18n: { label: {} } }],
      },
    })
  ).data as { uuid: string }

  // The builder previews it as a chart.
  await page.goto(`/admin/reports/r/${made.uuid}`)
  await expect(page.getByTestId('report-editor')).toBeVisible()
  await expect(page.getByTestId('report-canvas').locator('canvas')).toBeVisible({ timeout: 30_000 })

  // The viewer: chart, then its table of values, then a download.
  await page.goto(`/reports/${made.uuid}`)
  await expect(page.getByTestId('report-canvas').locator('canvas')).toBeVisible()
  await page.getByTestId('report-view-toggle').getByText('Table').click()
  await expect(page.getByTestId('report-table')).toContainText('Number of records')
  const download = page.waitForEvent('download')
  await page.getByTestId('report-export').getByRole('button', { name: 'Excel' }).click()
  expect((await download).suggestedFilename()).toMatch(/\.xlsx$/)

  // A dashboard built on the grid.
  await page.goto('/admin/reports/d/new')
  await page.getByRole('textbox', { name: 'Name (English)' }).fill('Front desk')
  await page.getByTestId('dashboard-key').fill('front_desk')
  await page.getByTestId('dashboard-add-report').click()
  await page.getByTestId('widget-report').click()
  await page.getByRole('option', { name: 'Visits by month' }).click()
  await page.getByTestId('dashboard-add-text').click()
  await page.getByRole('textbox', { name: 'Text (English)' }).fill('Updated every morning.')
  await page.getByTestId('dashboard-save').click()
  await expect(page).toHaveURL(/\/admin\/reports\/d\/[0-9a-f-]{36}$/)
  const uuid = page.url().split('/').pop()!
  await page.goto(`/dashboards/${uuid}`)
  await expect(page.getByTestId('dashboard-view')).toContainText('Updated every morning.')
  await expect(page.getByTestId('dashboard-view').locator('canvas')).toBeVisible()
  await page.goto('/insights')
  await expect(page.getByTestId('insights-view')).toContainText('Front desk')
  expect(problems).toEqual([])
})
