import { execFileSync } from 'node:child_process'
import { createHmac } from 'node:crypto'
import { expect, type Page } from '@playwright/test'

export const backendDir = process.env.LCF_BACKEND_DIR ?? '../backend'
export const admin = { name: 'E2E Root', email: 'root@e2e.test', password: 'Vq7!mR2#tL9$wZ4p-Xk' }

export function artisan(...args: string[]): string {
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
  await page.goto('/login')
  await page.getByTestId('login-email').fill(admin.email)
  await page.getByTestId('login-password').locator('input').fill(admin.password)
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/login\/two-factor/)
  await fillOtp(page, 'otp', await freshTotp(secret))
  await page.getByTestId('otp-submit').click()
  await expect(page).not.toHaveURL(/login/)
}
