/**
 * Colour arithmetic for the theme (docs/design-system.md): WCAG 2.1 contrast,
 * the label colour for an admin-chosen background, and the shades derived from
 * a brand colour. The PHP twin is App\Support\Color\Contrast; both are tested
 * against the same values.
 */

/** The two label colours a coloured background chooses between (tokens --on-color-light / --on-color-dark). */
export const ON_COLOR_LIGHT = '#ffffff'
export const ON_COLOR_DARK = '#0f1419'
/** Shown by a colour input while no colour is chosen (the default status-pill colour). */
export const PICKER_FALLBACK = '#3f4c5a'
/** The surfaces a brand colour must read on (tokens --bg-surface, light and dark). */
export const SURFACE_LIGHT = '#ffffff'
export const SURFACE_DARK = '#171e26'
export const SUBTLE_LIGHT = '#f8fafc'
export const SUBTLE_DARK = '#1e2733'

export const AA_TEXT = 4.5
export const AA_NON_TEXT = 3

export type Rgb = [number, number, number]

export function parseHex(hex: string): Rgb | null {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim())
  if (!m) return null
  const n = parseInt(m[1]!, 16)
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255]
}

export function toHex([r, g, b]: Rgb): string {
  return (
    '#' +
    [r, g, b]
      .map((v) =>
        Math.round(Math.min(255, Math.max(0, v)))
          .toString(16)
          .padStart(2, '0'),
      )
      .join('')
  )
}

export function luminance(hex: string): number {
  const rgb = parseHex(hex)
  if (!rgb) return 0
  const [r, g, b] = rgb.map((v) => {
    const c = v / 255
    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  }) as Rgb
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

export function contrast(a: string, b: string): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x) as [number, number]
  return (hi + 0.05) / (lo + 0.05)
}

/** The readable label colour for a background the admin chose (status and boolean pills, colour chips). */
export function labelColor(background: string): string {
  return contrast(ON_COLOR_LIGHT, background) >= contrast(ON_COLOR_DARK, background) ? ON_COLOR_LIGHT : ON_COLOR_DARK
}

/** Ratio of the label {@link labelColor} picks. */
export function labelContrast(background: string): number {
  return contrast(labelColor(background), background)
}

function mix(a: string, b: string, weightOfA: number): string {
  const x = parseHex(a)!
  const y = parseHex(b)!
  return toHex([0, 1, 2].map((i) => x[i]! * weightOfA + y[i]! * (1 - weightOfA)) as Rgb)
}

/** HSL lightness shift that keeps hue and saturation. */
function shiftLightness(hex: string, delta: number): string {
  const [r, g, b] = parseHex(hex)!.map((v) => v / 255) as Rgb
  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  let h = 0
  let s = 0
  const l = (max + min) / 2
  if (max !== min) {
    const d = max - min
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
    h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4
    h /= 6
  }
  const nl = Math.min(1, Math.max(0, l + delta))
  const q = nl < 0.5 ? nl * (1 + s) : nl + s - nl * s
  const p = 2 * nl - q
  const hue = (t: number) => {
    let u = t
    if (u < 0) u += 1
    if (u > 1) u -= 1
    if (u < 1 / 6) return p + (q - p) * 6 * u
    if (u < 1 / 2) return q
    if (u < 2 / 3) return p + (q - p) * (2 / 3 - u) * 6
    return p
  }
  return s === 0 ? toHex([nl * 255, nl * 255, nl * 255]) : toHex([hue(h + 1 / 3) * 255, hue(h) * 255, hue(h - 1 / 3) * 255])
}

/**
 * The nearest shade of `hex` (same hue, lightness moved in the given
 * direction) that reaches `target` against every background, or null.
 */
export function nearestPassing(hex: string, backgrounds: string[], target: number, direction: 'darker' | 'lighter'): string | null {
  for (let step = 0; step <= 400; step++) {
    const c = step === 0 ? hex.toLowerCase() : shiftLightness(hex, (direction === 'darker' ? -1 : 1) * step * 0.0025)
    if (backgrounds.every((bg) => contrast(c, bg) >= target)) return c
  }
  return null
}

export interface BrandShades {
  primary: string
  hover: string
  subtle: string
  onPrimary: string
  onPrimarySubtle: string
}

export interface BrandCheck {
  /** Contrast of the brand colour as text on surfaces (needs 4.5:1). */
  ratio: number
  ok: boolean
  /** Nearest passing shade when not ok. */
  suggestion: string | null
}

/** Checks a brand colour for one mode: as text on the surface and subtle backgrounds. */
export function checkBrand(hex: string, mode: 'light' | 'dark'): BrandCheck {
  const bgs = mode === 'light' ? [SURFACE_LIGHT, SUBTLE_LIGHT] : [SURFACE_DARK, SUBTLE_DARK]
  const ratio = Math.min(...bgs.map((bg) => contrast(hex, bg)))
  const ok = ratio >= AA_TEXT
  return { ratio, ok, suggestion: ok ? null : nearestPassing(hex, bgs, AA_TEXT, mode === 'light' ? 'darker' : 'lighter') }
}

/** The dark-mode brand colour when the admin sets none: the light one, lightened until it reads on dark surfaces. */
export function darkVariant(light: string): string {
  return nearestPassing(light, [SURFACE_DARK, SUBTLE_DARK], AA_TEXT, 'lighter') ?? light
}

/**
 * Every brand-derived token for one mode. Hover is one step further from the
 * surface; subtle is a 10% (light) or 22% (dark) tint over the surface; the
 * text colour on subtle is the hover shade, moved further if it does not reach
 * 4.5:1 there.
 */
export function brandShades(primary: string, mode: 'light' | 'dark'): BrandShades {
  const surface = mode === 'light' ? SURFACE_LIGHT : SURFACE_DARK
  const hover = shiftLightness(primary, mode === 'light' ? -0.08 : 0.06)
  const subtle = mix(primary, surface, mode === 'light' ? 0.1 : 0.22)
  const onPrimarySubtle = nearestPassing(hover, [subtle], AA_TEXT, mode === 'light' ? 'darker' : 'lighter') ?? hover
  return { primary: primary.toLowerCase(), hover, subtle, onPrimary: labelColor(primary), onPrimarySubtle }
}
