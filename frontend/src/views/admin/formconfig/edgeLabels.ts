import { computed, reactive, type ComputedRef, type InjectionKey } from 'vue'

/**
 * Transition labels on the designer canvas never sit on top of each other:
 * each edge reports where its label would go, and labels that would overlap
 * are moved down in turn until they are clear (owner report 11d).
 */
export interface LabelBox {
  x: number
  y: number
  w: number
}

export const LABEL_HEIGHT = 18
const GAP = 4

/** Vertical offsets that separate overlapping labels, in reading order (top to bottom, then left to right). */
export function separateLabels(boxes: Map<string, LabelBox>): Map<string, number> {
  const order = [...boxes.entries()].sort(([, a], [, b]) => a.y - b.y || a.x - b.x)
  const placed: LabelBox[] = []
  const out = new Map<string, number>()
  for (const [id, box] of order) {
    let dy = 0
    const clashes = (y: number) => placed.some((p) => Math.abs(p.x - box.x) < (p.w + box.w) / 2 + GAP && Math.abs(p.y - y) < LABEL_HEIGHT + GAP)
    while (clashes(box.y + dy)) dy += LABEL_HEIGHT + GAP
    placed.push({ ...box, y: box.y + dy })
    out.set(id, dy)
  }
  return out
}

export interface EdgeLabels {
  report: (id: string, box: LabelBox) => void
  forget: (id: string) => void
  offsets: ComputedRef<Map<string, number>>
  select: (id: string) => void
}

export const EDGE_LABELS: InjectionKey<EdgeLabels> = Symbol('edge-labels')

export function createEdgeLabels(select: (id: string) => void): EdgeLabels {
  const boxes = reactive(new Map<string, LabelBox>())
  return {
    report: (id, box) => {
      const old = boxes.get(id)
      if (!old || old.x !== box.x || old.y !== box.y || old.w !== box.w) boxes.set(id, box)
    },
    forget: (id) => boxes.delete(id),
    offsets: computed(() => separateLabels(boxes)),
    select,
  }
}
