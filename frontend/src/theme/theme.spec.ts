import { describe, expect, it } from 'vitest'
import { themeVariables, type ClientTheme, previewVariables } from './theme'

const base: ClientTheme = {
  uuid: 'x',
  version: 1,
  colors: {},
  radius: null,
  density: null,
  shadow: null,
  font_latin: null,
  font_arabic: null,
  css: null,
  assets: {},
  login: { welcome: null, support: null, legal_links: [] },
  browser_title: null,
}

describe('theme variables', () => {
  it('sets only what the theme sets, per mode', () => {
    expect(themeVariables(null)).toEqual({})
    expect(themeVariables(base)).toEqual({})
    const vars = themeVariables({
      ...base,
      colors: { surface: { light: '#fafafa', dark: null }, accent: { light: '#0b6e99', dark: '#4fb3df' } },
      radius: 'rounded',
      density: 'compact',
      shadow: 'none',
      font_arabic: 'system',
    })
    expect(vars).toEqual({
      '--theme-surface': '#fafafa',
      '--theme-accent': '#0b6e99',
      '--theme-accent-dark': '#4fb3df',
      '--theme-radius-control': '10px',
      '--theme-radius-card': '18px',
      '--theme-control-height': '2rem',
      '--theme-shadow': 'transparent',
      '--theme-shadow-dark': 'transparent',
      '--theme-font-ui-arabic': 'system-ui, sans-serif',
    })
  })
})

describe('previewVariables', () => {
  it('sets every variable, falling back to the product values for what the theme leaves empty', () => {
    const vars = previewVariables({ colors: { page: { light: '#f5f9fb' } }, radius: 'square' }, { primary: null, primaryDark: null })
    expect(vars['--theme-page']).toBe('#f5f9fb')
    expect(vars['--theme-page-dark']).toBe('initial')
    expect(vars['--theme-radius-control']).toBe('2px')
    expect(vars['--brand-primary']).toBe('initial')
  })
  it('uses the brand colour when the theme sets no primary colour', () => {
    const vars = previewVariables({ colors: {} }, { primary: '#0b6e99', primaryDark: null })
    expect(vars['--brand-primary']).toBe('#0b6e99')
    expect(vars['--brand-primary-dark']).not.toBe('initial')
  })
})
