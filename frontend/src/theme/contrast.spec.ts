import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { AA_NON_TEXT, AA_TEXT, brandShades, checkBrand, contrast, darkVariant, labelColor, labelContrast, ON_COLOR_DARK, ON_COLOR_LIGHT } from './color'

/**
 * WCAG 2.1 AA for the product theme, recomputed from tokens.css so a changed
 * value cannot quietly break contrast (docs/design-system.md, contrast table).
 */
const css = readFileSync(join(__dirname, 'tokens.css'), 'utf8')

function block(selector: string): Record<string, string> {
  const start = css.indexOf(selector)
  const body = css.slice(start, css.indexOf('}', start))
  const out: Record<string, string> = {}
  for (const m of body.matchAll(/--([a-z-]+):\s*([^;]+);/g)) {
    const value = m[2]!.trim()
    const hex = /#[0-9a-f]{6}/i.exec(value)?.[0]
    if (hex) out[m[1]!] = hex.toLowerCase()
  }
  return out
}

const light = block(':root,\n.app-light {')
const dark = { ...light, ...block('.app-dark {') }
const modes = { light, dark }

// [what, foreground token, background tokens, minimum ratio]
const pairs: [string, string, string[], number][] = [
  ['body text', 'text', ['bg-page', 'bg-surface', 'bg-subtle', 'danger-subtle', 'primary-subtle'], AA_TEXT],
  ['muted text, placeholders, column headers', 'text-muted', ['bg-page', 'bg-surface', 'bg-subtle'], AA_TEXT],
  ['links', 'link', ['bg-surface', 'bg-subtle', 'primary-subtle'], AA_TEXT],
  ['primary as text', 'primary', ['bg-page', 'bg-surface', 'bg-subtle'], AA_TEXT],
  ['text on primary-subtle (badges, selected tab)', 'on-primary-subtle', ['primary-subtle'], AA_TEXT],
  ['button labels', 'on-primary', ['primary', 'primary-hover'], AA_TEXT],
  ['danger text', 'danger', ['bg-surface', 'bg-subtle'], AA_TEXT],
  ['warning text', 'warning', ['bg-surface', 'bg-subtle'], AA_TEXT],
  ['success text', 'success', ['bg-surface', 'bg-subtle'], AA_TEXT],
  ['pill labels', 'on-neutral-pill', ['neutral-pill'], AA_TEXT],
  ['yes pills', 'on-success', ['success'], AA_TEXT],
  ['no pills', 'on-danger', ['danger'], AA_TEXT],
  ['input borders', 'border-input', ['bg-surface', 'bg-subtle'], AA_NON_TEXT],
  ['focus ring', 'primary', ['bg-surface', 'bg-subtle', 'bg-page'], AA_NON_TEXT],
]

describe('theme contrast (WCAG 2.1 AA)', () => {
  for (const [mode, tokens] of Object.entries(modes)) {
    for (const [what, fg, bgs, min] of pairs) {
      it(`${mode}: ${what}`, () => {
        for (const bg of bgs) {
          expect(tokens[fg], `--${fg}`).toBeDefined()
          expect(tokens[bg], `--${bg}`).toBeDefined()
          expect(contrast(tokens[fg]!, tokens[bg]!), `--${fg} on --${bg}`).toBeGreaterThanOrEqual(min)
        }
      })
    }
    it(`${mode}: pill labels on the theme's own pill colours`, () => {
      for (const bg of ['neutral-pill', 'danger', 'success', 'warning', 'primary']) expect(labelContrast(tokens[bg]!), `--${bg}`).toBeGreaterThanOrEqual(AA_TEXT)
    })
  }

  it('keeps the owner palette values that are not contrast fixes', () => {
    expect([light['bg-page'], light['bg-surface'], light['text'], light['link'], light['danger']]).toEqual(['#f4f6f9', '#ffffff', '#1f2937', '#107c52', '#d92d20'])
    expect([dark['bg-page'], dark['bg-surface'], dark['text'], dark['primary'], dark['danger']]).toEqual(['#0f1419', '#171e26', '#e6edf3', '#4d96e8', '#f2635c'])
  })
})

describe('admin-chosen colours', () => {
  it('matches the server arithmetic (App\\Support\\Color\\Contrast asserts the same values)', () => {
    expect(contrast('#ffffff', '#1a6fd4').toFixed(2)).toBe('4.92')
    expect(contrast('#e6edf3', '#171e26').toFixed(2)).toBe('14.22')
  })

  it('picks the more readable label for a pill background', () => {
    expect(labelColor('#3f4c5a')).toBe(ON_COLOR_LIGHT)
    expect(labelColor('#f6d860')).toBe(ON_COLOR_DARK)
    expect(labelContrast('#7a7a7a')).toBeLessThan(AA_TEXT) // the worst mid-tone (4.31:1): the status editor warns about it
  })

  it('derives every brand token from one colour, readable in both modes', () => {
    for (const brand of ['#1a6fd4', '#7c3aed', '#0f766e', '#b45309', '#be123c']) {
      const check = checkBrand(brand, 'light')
      const primary = check.ok ? brand : check.suggestion!
      const l = brandShades(primary, 'light')
      const d = brandShades(darkVariant(primary), 'dark')
      for (const [shades, surface] of [
        [l, '#ffffff'],
        [d, '#171e26'],
      ] as const) {
        expect(contrast(shades.primary, surface), `${brand} primary`).toBeGreaterThanOrEqual(AA_TEXT)
        expect(contrast(shades.onPrimary, shades.primary), `${brand} label`).toBeGreaterThanOrEqual(AA_TEXT)
        expect(contrast(shades.onPrimarySubtle, shades.subtle), `${brand} badge`).toBeGreaterThanOrEqual(AA_TEXT)
      }
    }
  })

  it('flags a brand colour that is too light and suggests the nearest passing shade', () => {
    const check = checkBrand('#7dd3fc', 'light')
    expect(check.ok).toBe(false)
    expect(contrast(check.suggestion!, '#ffffff')).toBeGreaterThanOrEqual(AA_TEXT)
    expect(checkBrand('#1a6fd4', 'light').ok).toBe(true)
  })
})
