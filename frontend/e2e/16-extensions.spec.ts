import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { artisan, signIn, watchConsole } from './support.ts'

/**
 * Developer extensions (specification §4.19): the code editor opens under the
 * application's Content-Security-Policy, a client validator is tested,
 * submitted, approved by a second person and deployed, and then checks the
 * record form from its sandboxed frame.
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

test('a client validator goes from the code editor through review to the record form, inside its sandbox', async ({ page }) => {
  test.setTimeout(240_000)
  const problems = watchConsole(page)
  await page.goto('/admin')
  const forms = (await api(page, 'GET', '/forms?search=visit_requests')).data as { uuid: string; key: string }[]
  const form = forms.find((f) => f.key === 'visit_requests')!.uuid

  // The editor: Monaco draws its lines under the page's policy, with no violation.
  await page.goto('/admin/extensions/new')
  await expect(page.getByTestId('extension-editor')).toBeVisible()
  await expect(page.locator('.monaco-editor .view-line').first()).toBeVisible({ timeout: 30_000 })
  await expect(page.locator('.monaco-editor .view-lines')).toContainText('final class Hook')

  const source = "lcf.validator((values) => (String(values.visitor ?? '').trim().length < 3 ? { visitor: 'Write the full name of the visitor.' } : {}))"
  const made = (await api(page, 'POST', '/extensions', { key: 'visitor_check', type: 'client_validator', form, source })).data as { uuid: string }
  await page.goto(`/admin/extensions/${made.uuid}`)
  await expect(page.locator('.monaco-editor .view-lines')).toContainText('lcf.validator', { timeout: 30_000 })
  await page.getByTestId('ext-run-test').click()
  await expect(page.getByTestId('ext-report')).toContainText(/Passed/)
  await page.getByTestId('ext-submit').click()
  await expect(page.getByTestId('ext-status')).toContainText('Waiting for approval')
  await expect(page.getByTestId('ext-approve')).toHaveCount(0) // the author cannot approve

  // A second person approves it (on the server, as that person).
  artisan(
    'tinker',
    `--execute=$e = App\\Modules\\Extensions\\Models\\Extension::withoutGlobalScopes()->where('key', 'visitor_check')->first();
app(App\\Modules\\Core\\Tenancy\\TenantContext::class)->set((int) $e->organization_id);
$u = App\\Modules\\Identity\\Models\\User::withoutGlobalScopes()->firstOrNew(['email' => 'approver@e2e.test']);
$u->forceFill(['name' => 'E2E Approver', 'status' => 'active', 'auth_source' => 'local', 'password' => bin2hex(random_bytes(16)), 'organization_id' => $e->organization_id])->save();
app(App\\Modules\\Extensions\\Extensions::class)->approve($e, $u);`,
  )
  await page.reload()
  await expect(page.getByTestId('ext-status')).toContainText('Approved')
  await page.getByTestId('ext-deploy').click()
  await page.getByRole('button', { name: 'Deploy' }).last().click()
  await expect(page.getByTestId('ext-status')).toContainText('Deployed')

  // On the record form the validator answers from its frame; the server still decides the save.
  await page.goto(`/app/${form}/new`)
  await expect(page.locator('iframe[sandbox="allow-scripts"]')).toHaveCount(1)
  await page.getByTestId('field-visitor').locator('input').fill('Al')
  await page.getByTestId('record-save').click()
  await expect(page.getByTestId('record-form-banner')).toBeVisible()
  await expect(page.getByTestId('field-visitor')).toContainText('Write the full name of the visitor.')
  await page.getByTestId('field-visitor').locator('input').fill('Alia Hassan')
  await page.getByTestId('record-save').click()
  await expect(page.getByTestId('record-view')).toContainText('Alia Hassan')

  // Turned off, it stops at once.
  await page.goto(`/admin/extensions/${made.uuid}`)
  await page.getByTestId('ext-turn-off').click()
  await expect(page.getByTestId('ext-status')).toContainText('Turned off')
  expect(problems).toEqual([])
})
