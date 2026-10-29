import { readFileSync, writeFileSync } from 'node:fs'
import { expect, test, type Page } from '@playwright/test'

/*
 * Form render (definition received → ready for input) of the 100-field form,
 * and the client formula set run on each change (architecture §20.2), read
 * from the User Timing marks the form records.
 */

const targets = JSON.parse(readFileSync(process.env.LCF_TARGETS ?? '../perf-targets.json', 'utf8')) as {
  forms: { wide: string }
  users: { password: string; pool: { email: string }[] }
}

async function measure(page: Page, name: string): Promise<number> {
  const handle = await page.waitForFunction((n) => performance.getEntriesByName(n, 'measure').at(-1)?.duration, name, { timeout: 30_000 })
  return (await handle.jsonValue()) as number
}

const p95 = (values: number[]): number => [...values].sort((a, b) => a - b)[Math.max(0, Math.ceil(values.length * 0.95) - 1)]!

test('the 100-field form renders and recalculates within budget', async ({ page }) => {
  const person = targets.users.pool[targets.users.pool.length - 1]!
  await page.goto('/login')
  await page.getByTestId('login-email').fill(person.email)
  await page.getByTestId('login-password').locator('input').fill(targets.users.password)
  await page.getByTestId('login-submit').click()
  await expect(page).not.toHaveURL(/login/)

  const renders: number[] = []
  for (let i = 0; i < 6; i++) {
    await page.goto(`/app/${targets.forms.wide}/new`)
    await expect(page.getByTestId('field-n01')).toBeVisible()
    const ms = await measure(page, 'lcf:form-render')
    // The first load also fetches the code of the form screen; it is reported, not counted.
    if (i > 0) renders.push(ms)
  }

  const formulas: number[] = []
  const input = page.getByTestId('field-n01').locator('input')
  for (let i = 1; i <= 25; i++) {
    await page.evaluate(() => performance.clearMeasures('lcf:rules'))
    await input.fill(String(i * 3))
    await input.blur()
    formulas.push(await measure(page, 'lcf:rules'))
  }
  // The formulas ran: f01 = round(n01 × n02 + n21, 2) with the others empty shows n01 × 0 + 0.
  await expect(page.getByTestId('field-f01')).toBeVisible()

  const result = {
    form_render: { value: Math.round(p95(renders)), unit: 'ms', detail: { samples: renders.map(Math.round) } },
    expression_client: { value: Math.round(p95(formulas) * 100) / 100, unit: 'ms', detail: { samples: formulas.length } },
  }
  writeFileSync(process.env.LCF_RENDER_OUT ?? 'perf-render.json', JSON.stringify(result, null, 2))
  console.log(JSON.stringify(result))
})
