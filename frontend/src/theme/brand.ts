import { brandShades, darkVariant, type BrandShades } from './color'

/**
 * Applies the brand colour from Appearance & Branding to every screen: the
 * --brand-* custom properties on the document root take precedence over the
 * defaults in tokens.css (docs/design-system.md, brand colour). With no brand
 * colour set, the theme's own values apply.
 */
const SUFFIX: Record<keyof BrandShades, string> = {
  primary: 'primary',
  hover: 'primary-hover',
  subtle: 'primary-subtle',
  onPrimary: 'on-primary',
  onPrimarySubtle: 'on-primary-subtle',
}

export function brandVariables(primary: string | null, primaryDark: string | null): Record<string, string> {
  const out: Record<string, string> = {}
  if (primary) {
    const light = brandShades(primary, 'light')
    for (const [k, name] of Object.entries(SUFFIX)) out[`--brand-${name}`] = light[k as keyof BrandShades]
  }
  const darkBase = primaryDark ?? (primary ? darkVariant(primary) : null)
  if (darkBase) {
    const dark = brandShades(darkBase, 'dark')
    for (const [k, name] of Object.entries(SUFFIX)) out[`--brand-${name}-dark`] = dark[k as keyof BrandShades]
  }
  return out
}

export function applyBrand(primary: string | null | undefined, primaryDark: string | null | undefined, target: HTMLElement = document.documentElement): void {
  for (const name of Object.values(SUFFIX)) {
    target.style.removeProperty(`--brand-${name}`)
    target.style.removeProperty(`--brand-${name}-dark`)
  }
  for (const [k, v] of Object.entries(brandVariables(primary ?? null, primaryDark ?? null))) target.style.setProperty(k, v)
}
