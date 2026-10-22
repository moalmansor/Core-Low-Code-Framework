import { applyBrand, brandVariables } from './brand'

/**
 * Applies a theme (specification §4.28, ADR-0055) to the document: its
 * colours for light and dark mode, shape, density, shadow and fonts as
 * --theme-* custom properties (tokens.css falls back to the product values
 * for whatever a theme leaves empty), its primary colour through the brand
 * shades, its custom CSS as a stylesheet of its own, and its favicon.
 * Everything is set through the CSSOM or a <link>, never as inline style
 * markup, so the Content-Security-Policy stays strict.
 */
export type ModeColor = { light?: string | null; dark?: string | null }
export interface ClientTheme {
  uuid: string
  version: number
  colors: Partial<Record<'primary' | 'accent' | 'success' | 'warning' | 'danger' | 'page' | 'surface' | 'subtle' | 'border', ModeColor>>
  radius: 'square' | 'normal' | 'rounded' | null
  density: 'compact' | 'normal' | 'comfortable' | null
  shadow: 'none' | 'soft' | 'strong' | null
  font_latin: 'plex' | 'system' | null
  font_arabic: 'plex' | 'system' | null
  css: string | null
  assets: Record<string, string | null>
  login: { welcome: string | null; support: string | null; legal_links: { url: string; label: string | null }[] }
  browser_title: string | null
}

/** What a theme looks like: the part the preview and the document both paint. */
export interface ThemeLook {
  colors: ClientTheme['colors']
  radius?: string | null
  density?: string | null
  shadow?: string | null
  font_latin?: string | null
  font_arabic?: string | null
}
type Brand = { primary?: string | null; primaryDark?: string | null; favicon?: string | null }

const COLOR_VARS = ['accent', 'success', 'warning', 'danger', 'page', 'surface', 'subtle', 'border'] as const
const RADIUS: Record<string, [string, string]> = { square: ['2px', '4px'], rounded: ['10px', '18px'] }
const DENSITY: Record<string, string> = { compact: '2rem', comfortable: '2.5rem' }
const SHADOW: Record<string, [string, string]> = { none: ['transparent', 'transparent'], strong: ['rgb(15 20 25 / 0.16)', 'rgb(0 0 0 / 0.6)'] }
const SYSTEM_FONT = 'system-ui, sans-serif'

/** Every custom property a theme may set (cleared before the next theme applies). */
export const THEME_VARIABLES = [
  ...COLOR_VARS.flatMap((c) => [`--theme-${c}`, `--theme-${c}-dark`]),
  '--theme-radius-control',
  '--theme-radius-card',
  '--theme-control-height',
  '--theme-shadow',
  '--theme-shadow-dark',
  '--theme-font-ui',
  '--theme-font-ui-arabic',
]

export function themeVariables(theme: ThemeLook | null): Record<string, string> {
  const out: Record<string, string> = {}
  if (!theme) return out
  for (const c of COLOR_VARS) {
    const v = theme.colors[c]
    if (v?.light) out[`--theme-${c}`] = v.light
    if (v?.dark) out[`--theme-${c}-dark`] = v.dark
  }
  const radius = theme.radius ? RADIUS[theme.radius] : undefined
  if (radius) [out['--theme-radius-control'], out['--theme-radius-card']] = radius
  const density = theme.density ? DENSITY[theme.density] : undefined
  if (density) out['--theme-control-height'] = density
  const shadow = theme.shadow ? SHADOW[theme.shadow] : undefined
  if (shadow) [out['--theme-shadow'], out['--theme-shadow-dark']] = shadow
  if (theme.font_latin === 'system') out['--theme-font-ui'] = SYSTEM_FONT
  if (theme.font_arabic === 'system') out['--theme-font-ui-arabic'] = SYSTEM_FONT
  return out
}

/**
 * @param brand the Appearance & Branding colour, used where the theme sets no primary colour
 */
export function applyTheme(theme: ClientTheme | null, brand: Brand, root: HTMLElement = document.documentElement): void {
  for (const name of THEME_VARIABLES) root.style.removeProperty(name)
  for (const [k, v] of Object.entries(themeVariables(theme))) root.style.setProperty(k, v)
  applyBrand(...primaryOf(theme, brand), root)
  if (root !== document.documentElement) return
  setStylesheet(theme?.css ?? null)
  const favicon = theme?.assets.favicon ?? brand.favicon
  if (favicon) setFavicon(favicon)
}

/** The primary colour pair: the theme's, else the Appearance & Branding colour. */
function primaryOf(theme: ThemeLook | null, brand: Brand): [string | null, string | null] {
  const primary = theme?.colors.primary
  return [primary?.light ?? brand.primary ?? null, primary?.dark ?? (primary?.light ? null : (brand.primaryDark ?? null))]
}

const BRAND_VARIABLES = Object.keys(brandVariables('#000000', '#000000'))

/**
 * The custom properties for a preview pane (the theme editor): every theme and
 * brand variable is set, those the theme leaves empty to `initial`, so the
 * pane shows the product values instead of inheriting the theme in use.
 */
export function previewVariables(theme: ThemeLook, brand: Brand): Record<string, string> {
  const out: Record<string, string> = {}
  for (const name of [...THEME_VARIABLES, ...BRAND_VARIABLES]) out[name] = 'initial'
  return { ...out, ...themeVariables(theme), ...brandVariables(...primaryOf(theme, brand)) }
}

function setStylesheet(href: string | null): void {
  let link = document.getElementById('lcf-theme-css') as HTMLLinkElement | null
  if (!href) {
    link?.remove()
    return
  }
  if (!link) {
    link = document.createElement('link')
    link.id = 'lcf-theme-css'
    link.rel = 'stylesheet'
    document.head.appendChild(link)
  }
  if (link.href !== href) link.href = href
}

function setFavicon(href: string): void {
  let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }
  link.href = href
}
