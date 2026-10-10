import type { Breakpoints } from './types'

/**
 * The automatic width of a field the admin has not sized (design system
 * §5.6): short values share the row (one column on phones, two from tablets,
 * three on wide screens); long text, files, maps and display blocks take the
 * whole row. A width set in the builder always wins.
 */
export const WIDE_TYPES = new Set([
  'textarea',
  'rich_text',
  'markdown',
  'code',
  'json',
  'key_value',
  'file_multi',
  'image_upload',
  'camera',
  'signature',
  'map_location',
  'consent',
  'checkbox_group',
  'radio_group',
  'static_html',
  'heading',
  'divider',
  'spacer',
  'display_image',
  'alert_box',
])

export const AUTO_SPAN: Breakpoints = { xs: 12, md: 6, xl: 4 }
const FULL: Breakpoints = { xs: 12 }

export function autoSpan(type: string): Breakpoints {
  return WIDE_TYPES.has(type) ? FULL : AUTO_SPAN
}

/** Whether the builder set any width for the element. */
export function hasWidth(width: Breakpoints | undefined | null): boolean {
  return !!width && Object.values(width).some((v) => typeof v === 'number' && v > 0)
}
