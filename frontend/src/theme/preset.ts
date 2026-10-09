import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

/**
 * PrimeVue's Aura preset re-pointed at the product theme (src/theme/tokens.css,
 * docs/design-system.md). Every colour here is a token reference, so the
 * library's components follow the light and dark values, the contrast fixes,
 * and a brand colour set in Appearance & Branding.
 *
 * Aura picks surface steps with `light-dark()`, reading a low step for
 * backgrounds in light mode and a high one in dark mode. Each step is therefore
 * defined as a pair of roles: what the step is used for in light mode and what
 * it is used for in dark mode.
 */
const pair = (light: string, dark: string) => `light-dark(var(${light}), var(${dark}))`

const severity = (tone: 'danger' | 'warning' | 'success' | 'primary') => {
  const edge = `var(--${tone})`
  const tint = tone === 'primary' ? 'var(--primary-subtle)' : `var(--${tone}-subtle)`
  // Banner text stays in the body colour; the tone marks the icon and the edge (design system, banners).
  return {
    background: tint,
    borderColor: edge,
    color: 'var(--text)',
    shadow: 'none',
    closeButton: { hoverBackground: 'var(--bg-subtle)', focusRing: { color: 'var(--focus-ring)', shadow: 'none' } },
    outlined: { color: 'var(--text)', borderColor: edge },
    simple: { color: 'var(--text)' },
  }
}

export const ProductPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50: 'var(--primary-subtle)',
      100: 'var(--primary-subtle)',
      200: 'color-mix(in srgb, var(--primary) 35%, var(--bg-surface))',
      300: 'color-mix(in srgb, var(--primary) 60%, var(--bg-surface))',
      400: 'color-mix(in srgb, var(--primary) 85%, var(--bg-surface))',
      500: 'var(--primary)',
      600: 'var(--primary-hover)',
      700: 'var(--on-primary-subtle)',
      800: 'var(--on-primary-subtle)',
      900: 'var(--on-primary-subtle)',
      950: 'var(--on-primary-subtle)',
      color: 'var(--primary)',
      contrastColor: 'var(--on-primary)',
      hoverColor: 'var(--primary-hover)',
      activeColor: 'var(--primary-hover)',
    },
    surface: {
      0: pair('--bg-surface', '--text'),
      50: pair('--bg-subtle', '--text'),
      100: pair('--bg-page', '--text'),
      200: pair('--border', '--text-muted'),
      300: pair('--border-strong', '--text-muted'),
      400: pair('--text-faint', '--text-muted'),
      500: pair('--text-muted', '--text-faint'),
      600: pair('--text-muted', '--border-strong'),
      700: pair('--text', '--border'),
      800: pair('--text', '--bg-subtle'),
      900: pair('--text', '--bg-surface'),
      950: pair('--text', '--bg-page'),
    },
    focusRing: { width: '2px', style: 'solid', color: 'var(--focus-ring)', offset: '2px', shadow: 'none' },
    formField: {
      background: 'var(--bg-surface)',
      disabledBackground: 'var(--bg-subtle)',
      filledBackground: 'var(--bg-subtle)',
      filledHoverBackground: 'var(--bg-subtle)',
      filledFocusBackground: 'var(--bg-subtle)',
      borderColor: 'var(--border-input)',
      hoverBorderColor: 'var(--text-muted)',
      focusBorderColor: 'var(--primary)',
      invalidBorderColor: 'var(--danger)',
      color: 'var(--text)',
      disabledColor: 'var(--text-faint)',
      placeholderColor: 'var(--text-muted)',
      invalidPlaceholderColor: 'var(--danger)',
      floatLabelColor: 'var(--text-muted)',
      floatLabelFocusColor: 'var(--primary)',
      floatLabelActiveColor: 'var(--text-muted)',
      iconColor: 'var(--text-muted)',
      shadow: 'none',
    },
    content: { background: 'var(--bg-surface)', hoverBackground: 'var(--bg-subtle)', borderColor: 'var(--border)', color: 'var(--text)', hoverColor: 'var(--text)' },
    text: { color: 'var(--text)', hoverColor: 'var(--text)', mutedColor: 'var(--text-muted)', hoverMutedColor: 'var(--text)' },
    highlight: { background: 'var(--primary-subtle)', focusBackground: 'var(--primary-subtle)', color: 'var(--on-primary-subtle)', focusColor: 'var(--on-primary-subtle)' },
    mask: { background: 'var(--overlay)', color: 'var(--text)' },
  },
  components: {
    message: { info: severity('primary'), success: severity('success'), warn: severity('warning'), error: severity('danger'), secondary: severity('primary'), contrast: severity('primary') },
    toast: {
      info: { ...severity('primary'), detailColor: 'var(--text)' },
      success: { ...severity('success'), detailColor: 'var(--text)' },
      warn: { ...severity('warning'), detailColor: 'var(--text)' },
      error: { ...severity('danger'), detailColor: 'var(--text)' },
      secondary: { ...severity('primary'), detailColor: 'var(--text)' },
      contrast: { ...severity('primary'), detailColor: 'var(--text)' },
    },
    tag: {
      primary: { background: 'var(--primary-subtle)', color: 'var(--on-primary-subtle)' },
      secondary: { background: 'var(--bg-subtle)', color: 'var(--text-muted)' },
      success: { background: 'var(--success-subtle)', color: 'var(--text)' },
      info: { background: 'var(--primary-subtle)', color: 'var(--on-primary-subtle)' },
      warn: { background: 'var(--warning-subtle)', color: 'var(--text)' },
      danger: { background: 'var(--danger-subtle)', color: 'var(--text)' },
      contrast: { background: 'var(--text)', color: 'var(--bg-surface)' },
    },
    datatable: {
      root: { borderColor: 'var(--border)' },
      columnTitle: { fontWeight: '500', fontSize: 'var(--table-header-size)' },
      headerCell: {
        background: 'var(--bg-subtle)',
        color: 'var(--text-muted)',
        selectedBackground: 'var(--bg-subtle)',
        selectedColor: 'var(--text)',
        borderColor: 'var(--border)',
        padding: '0.625rem 0.75rem',
      },
      bodyCell: { borderColor: 'var(--border)', padding: '0.8125rem 0.75rem', selectedBorderColor: 'var(--border)' },
      row: { hoverBackground: 'var(--bg-subtle)', stripedBackground: 'var(--bg-subtle)' },
    },
  },
})
