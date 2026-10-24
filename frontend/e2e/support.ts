import { execFileSync } from 'node:child_process'
import { createHmac } from 'node:crypto'
import { expect, request, type Page, type Response } from '@playwright/test'

export const backendDir = process.env.LCF_BACKEND_DIR ?? '../backend'
export const admin = { name: 'E2E Root', email: 'root@e2e.test', password: 'Vq7!mR2#tL9$wZ4p-Xk' }

/**
 * Runs an Artisan command against the backend under test: locally with PHP, or
 * inside the Docker stack when LCF_E2E_DOCKER=1 (CI's stack smoke test).
 */
export function artisan(...args: string[]): string {
  if (process.env.LCF_E2E_DOCKER === '1') {
    // HOME=/tmp: tinker (psysh) writes its config under the home folder, which the image's www-data cannot.
    return execFileSync('docker', ['compose', 'exec', '-T', '-e', 'HOME=/tmp', 'app', 'php', 'artisan', ...args, '--no-ansi'], {
      cwd: process.env.LCF_E2E_COMPOSE_DIR ?? '..',
      encoding: 'utf8',
    })
  }
  return execFileSync('php', ['artisan', ...args, '--no-ansi'], { cwd: backendDir, encoding: 'utf8' })
}

/** RFC 6238 TOTP (SHA-1, 30 s, 6 digits) from a base32 secret. */
export function totp(secret: string, offset = 0): string {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'
  let bits = ''
  for (const c of secret.replace(/=+$/, '').toUpperCase()) bits += alphabet.indexOf(c).toString(2).padStart(5, '0')
  const key = Buffer.from(bits.match(/.{8}/g)!.map((b) => parseInt(b, 2)))
  const counter = Buffer.alloc(8)
  counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 1000 / 30) + offset))
  const h = createHmac('sha1', key).update(counter).digest()
  const o = h[h.length - 1]! & 0xf
  return String((h.readUInt32BE(o) & 0x7fffffff) % 1_000_000).padStart(6, '0')
}

/** Fails the test on any Content-Security-Policy violation or uncaught error. */
export function watchConsole(page: Page): string[] {
  const problems: string[] = []
  page.on('console', (m) => {
    if (m.type() === 'error' && /Content Security Policy|Refused to/i.test(m.text())) problems.push(m.text())
  })
  page.on('pageerror', (e) => problems.push(e.message))
  return problems
}

export async function fillOtp(page: Page, testId: string, code: string): Promise<void> {
  // Fill each box on its own: typing into the first box relies on focus moving
  // automatically, which is timing-sensitive on slow machines.
  const inputs = page.getByTestId(testId).locator('input')
  for (let i = 0; i < code.length; i++) {
    await inputs.nth(i).fill(code[i]!)
    await expect(inputs.nth(i)).toHaveValue(code[i]!)
  }
}

const usedSteps = '.e2e-totp-step'

/**
 * A code for a 30-second step not used before: the server rejects replayed
 * codes, so wait for the next step when this one was already spent.
 */
export async function freshTotp(secret: string): Promise<string> {
  const { existsSync, readFileSync, writeFileSync } = await import('node:fs')
  const last = existsSync(usedSteps) ? Number(readFileSync(usedSteps, 'utf8')) : 0
  let step = Math.floor(Date.now() / 30000)
  while (step <= last) {
    await new Promise((r) => setTimeout(r, 1000))
    step = Math.floor(Date.now() / 30000)
  }
  writeFileSync(usedSteps, String(step))
  return totp(secret)
}

export async function signIn(page: Page, secret: string): Promise<void> {
  // Redirects and refusals from the server while signing in, reported if signing in fails.
  const refused: Promise<string>[] = []
  const watch = (r: Response) => {
    if (r.status() >= 300 && /\/(api|sanctum)\//.test(r.url()))
      refused.push(
        r.text().then(
          (body) => `${r.request().method()} ${new URL(r.url()).pathname} → ${r.status()} ${body.slice(0, 200)}`,
          () => `${r.request().method()} ${new URL(r.url()).pathname} → ${r.status()}`,
        ),
      )
  }
  page.on('response', watch)
  try {
    await page.goto('/login')
    await page.getByTestId('login-email').fill(admin.email)
    await page.getByTestId('login-password').locator('input').fill(admin.password)
    await page.getByTestId('login-submit').click()
    await expect(page).toHaveURL(/login\/two-factor/)
    await fillOtp(page, 'otp', await freshTotp(secret))
    await page.getByTestId('otp-submit').click()
    await expect(page).not.toHaveURL(/login/)
  } catch (e) {
    throw new Error(`${(e as Error).message}\nServer responses while signing in:\n${(await Promise.all(refused)).join('\n')}`, { cause: e })
  } finally {
    page.off('response', watch)
  }
}

/** A same-site API call, as the app sends it (the API accepts the session only from its own pages). */
export async function sameSite(page: Page, method: 'GET' | 'PATCH', path: string, data?: unknown): Promise<Record<string, unknown>> {
  const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value)
  const res = await page.request.fetch(`/api/v1${path}`, {
    method,
    data,
    headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', Origin: new URL(page.url()).origin, Referer: page.url() },
  })
  expect(res.status(), `${method} ${path}`).toBeLessThan(300)
  return (await res.json()) as Record<string, unknown>
}

/**
 * Runs a sweep that opens pages far above a person's pace: lifts the API rate limit (a System Setting) while
 * it runs and puts it back after, so the sweep never measures the limiter instead of the screens.
 */
export async function withRateLimitLifted(page: Page, run: () => Promise<void>): Promise<void> {
  const limit = ((await sameSite(page, 'GET', '/settings/security')).data as { api_rate_limit_per_minute: number }).api_rate_limit_per_minute
  await sameSite(page, 'PATCH', '/settings/security', { api_rate_limit_per_minute: 10_000 })
  try {
    await run()
  } finally {
    await sameSite(page, 'PATCH', '/settings/security', { api_rate_limit_per_minute: limit })
    // The sweep's requests still count in the limiter's current minute: once the ordinary limit is back,
    // wait that minute out, so the next spec's sign-in is not refused with 429 (which reads as signed out).
    // Both buckets: the signed-in one (per user) and the anonymous one (per address), which the sign-in
    // and public pages of the sweep used and which the next spec's sign-in page uses first.
    const anonymous = await request.newContext({ baseURL: new URL(page.url()).origin })
    const status = async (ctx: typeof page.request) => (await ctx.get('/api/v1/me', { headers: { Accept: 'application/json' } })).status()
    await expect.poll(async () => [await status(page.request), await status(anonymous)].includes(429), { timeout: 90_000, intervals: [5_000] }).toBe(false)
    // No longer refused is not the same as empty: on a fast runner the window that just opened can already be
    // nearly full from the sweep's last requests. Wait out one whole window (60 seconds) so the next spec's
    // sign-in starts from empty buckets, then check once more.
    await page.waitForTimeout(61_000)
    await expect.poll(async () => [await status(page.request), await status(anonymous)].includes(429), { timeout: 90_000, intervals: [5_000] }).toBe(false)
    await anonymous.dispose()
  }
}
