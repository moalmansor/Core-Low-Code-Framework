import { reactive } from 'vue'
import type { Breakpoint } from './types'

/** View state of the canvas: where a dragged element would land, the simulated device, and the label language. */
export const canvasView = reactive<{ indicator: { parent: string | null; index: number } | null; breakpoint: Breakpoint; locale: string | null }>({
  indicator: null,
  breakpoint: 'lg',
  locale: null,
})

const ORDER: Breakpoint[] = ['xs', 'sm', 'md', 'lg', 'xl']

/**
 * The grid span at a breakpoint: the value set there or at the nearest
 * smaller breakpoint, else 12. A field the admin has not sized takes its
 * automatic width, as the record pages show it (design system §5.6).
 */
export function spanAt(widths: Partial<Record<Breakpoint, number>> | undefined, bp: Breakpoint, auto?: Partial<Record<Breakpoint, number>>): number {
  if (!widths || !Object.values(widths).some((v) => v)) widths = auto
  if (!widths) return 12
  for (let i = ORDER.indexOf(bp); i >= 0; i--) {
    const v = widths[ORDER[i]!]
    if (v) return v
  }
  return 12
}
