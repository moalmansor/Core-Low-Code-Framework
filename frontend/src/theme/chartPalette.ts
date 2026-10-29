/**
 * Categorical colours for report charts (docs/design-system.md, ADR-0053).
 * Eight hues in a fixed order, a separate step for each mode, validated for
 * colour-vision deficiency on the app's light and dark surfaces. A chart
 * assigns them in order and never cycles: a ninth series folds into "Other".
 * Three light steps sit below 3:1 on white, so every chart also offers its
 * table of values.
 */
export const CHART_LIGHT = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'] as const
export const CHART_DARK = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'] as const

export function chartPalette(dark: boolean): readonly string[] {
  return dark ? CHART_DARK : CHART_LIGHT
}

/** A theme token's current value, for libraries that need a colour string. */
export function token(name: string): string {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}
