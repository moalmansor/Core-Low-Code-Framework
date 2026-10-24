import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { signIn, watchConsole } from './support.ts'

/**
 * SMS and messaging channels, external data sources and sync jobs
 * (specification §4.33): each is set up from its screen, secrets are never
 * shown back, and a source that cannot be reached fails cleanly — in its
 * test, and in a sync job's dry run — instead of breaking the page. (The
 * test environment has no outside network, so the happy path of each call
 * is covered by the backend tests with a stubbed gateway.)
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

test('a channel, a data source and a sync job are set up from their screens, keep their secrets, and fail cleanly without the network', async ({ page }) => {
  test.setTimeout(240_000)
  const problems = watchConsole(page)
  await page.goto('/')
  const allowed = (await api(page, 'GET', '/egress-allowlist')).data as { uuid: string; host_pattern: string }[]
  const entry =
    allowed.find((e) => e.host_pattern === 'integrations.e2e.example.com') ??
    ((await api(page, 'POST', '/egress-allowlist', { host_pattern: 'integrations.e2e.example.com', ports: [443], allow_http: false, is_active: true })).data as { uuid: string })

  // An SMS channel through an HTTPS endpoint; its header value is kept and never shown again.
  await page.goto('/admin/notification-channels/new')
  await expect(page.getByTestId('channel-editor')).toBeVisible()
  await page.getByRole('textbox', { name: 'Name (English)' }).fill('Text messages')
  await page.getByTestId('channel-key').fill('sms_e2e')
  await page.getByTestId('channel-provider').click()
  await page.getByRole('option', { name: 'Your own HTTPS endpoint' }).click()
  await page.getByTestId('channel-url').fill('https://integrations.e2e.example.com/sms')
  await page.getByTestId('channel-body').fill('{"to": {{to}}, "text": {{text}}}')
  await page.getByTestId('channel-header_name').fill('X-Api-Key')
  await page.getByTestId('channel-header_value').locator('input').fill('e2e-secret-key')
  await page.getByTestId('channel-save').click()
  await expect(page).toHaveURL(/\/admin\/notification-channels\/[0-9a-f-]{36}$/)
  const channel = page.url().split('/').pop()!
  await expect(page.getByTestId('channel-header_value').locator('input')).toHaveValue('')
  expect(JSON.stringify(await api(page, 'GET', `/notification-channels/${channel}`))).not.toContain('e2e-secret-key')
  // Rule authors can choose it.
  expect(((await api(page, 'GET', '/notification-channels/options')).data as { key: string }[]).map((c) => c.key)).toContain('sms_e2e')

  // A REST data source; its test reports that it cannot be reached.
  await page.goto('/admin/integrations/sources/new')
  await expect(page.getByTestId('source-editor')).toBeVisible()
  await page.getByRole('textbox', { name: 'Name (English)' }).fill('Cities')
  await page.getByTestId('source-key').fill('cities_e2e')
  await page.getByTestId('source-url').fill('https://integrations.e2e.example.com/cities')
  await page.getByTestId('source-records').fill('items')
  await page.getByTestId('source-map-value').fill('code')
  await page.getByTestId('source-map-label').fill('name')
  await page.getByTestId('source-save').click()
  await expect(page).toHaveURL(/\/admin\/integrations\/sources\/[0-9a-f-]{36}$/)
  const source = page.url().split('/').pop()!
  await page.getByTestId('source-run-test').click()
  await expect(page.getByTestId('source-test-error')).toBeVisible()

  // A sync job into a form; its dry run fails with the source's reason, shown in the run list.
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!
  const me = (await api(page, 'GET', '/me')).data as { uuid: string }
  const job = (
    await api(page, 'POST', '/sync-jobs', {
      job: {
        name: { en: 'Cities import' },
        source,
        form: form.uuid,
        direction: 'import',
        run_as: me.uuid,
        fields: [{ field: 'visitor', from: 'label' }],
        match: ['visitor'],
        conflict_rule: 'skip',
        active: true,
        schedule: null,
      },
    })
  ).data as { uuid: string }
  await page.goto(`/admin/integrations/sync/${job.uuid}`)
  await expect(page.getByTestId('sync-editor')).toBeVisible()
  await expect(page.getByTestId('sync-field-visitor')).toBeVisible()
  await page.getByTestId('sync-dry-run').click()
  await expect.poll(async () => ((await api(page, 'GET', `/sync-jobs/${job.uuid}/runs`)).data as { status: string }[])[0]?.status, { timeout: 60_000, intervals: [2_000] }).toBe('failed')
  await page.reload()
  await expect(page.getByTestId('sync-runs')).toContainText('Failed')

  // Leave the shared environment as it was.
  await api(page, 'DELETE', `/sync-jobs/${job.uuid}`)
  await api(page, 'DELETE', `/data-sources/${source}`)
  await api(page, 'DELETE', `/notification-channels/${channel}`)
  await api(page, 'DELETE', `/egress-allowlist/${entry.uuid}`)
  expect(problems).toEqual([])
})
