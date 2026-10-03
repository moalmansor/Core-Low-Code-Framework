/**
 * Deterministic, offline layout for the schema ERD (specification §4.8).
 * Referenced (parent) tables are placed in earlier columns than the tables
 * that point at them; each column is stacked vertically, ordered by the
 * average position of the parents to reduce crossing edges. In right-to-left
 * layouts the columns are mirrored so parents sit on the reading start side.
 */

export interface ErdLayoutNode {
  id: string
  columns: number
}
export interface ErdLayoutEdge {
  from: string
  to: string
}
export interface Box {
  x: number
  y: number
  width: number
  height: number
  rank: number
}

export const NODE_WIDTH = 260
export const HEADER_HEIGHT = 36
export const ROW_HEIGHT = 22
export const GAP_X = 120
export const GAP_Y = 40

export function nodeHeight(columns: number): number {
  return HEADER_HEIGHT + Math.max(1, columns) * ROW_HEIGHT + 8
}

export function layoutErd(nodes: ErdLayoutNode[], edges: ErdLayoutEdge[], direction: 'ltr' | 'rtl' = 'ltr'): Map<string, Box> {
  const ids = new Set(nodes.map((n) => n.id))
  const parents = new Map<string, Set<string>>()
  for (const n of nodes) parents.set(n.id, new Set())
  for (const e of edges) {
    if (e.from !== e.to && ids.has(e.from) && ids.has(e.to)) parents.get(e.from)!.add(e.to)
  }

  // Longest path from a root; cycles are broken by ignoring back edges.
  const rank = new Map<string, number>()
  const visiting = new Set<string>()
  const rankOf = (id: string): number => {
    const known = rank.get(id)
    if (known !== undefined) return known
    if (visiting.has(id)) return 0
    visiting.add(id)
    let r = 0
    for (const p of parents.get(id) ?? []) {
      if (!visiting.has(p)) r = Math.max(r, rankOf(p) + 1)
    }
    visiting.delete(id)
    rank.set(id, r)
    return r
  }
  const sorted = [...nodes].sort((a, b) => a.id.localeCompare(b.id))
  for (const n of sorted) rankOf(n.id)

  const columns = new Map<number, ErdLayoutNode[]>()
  for (const n of sorted) {
    const r = rank.get(n.id)!
    columns.set(r, [...(columns.get(r) ?? []), n])
  }

  const out = new Map<string, Box>()
  const order = new Map<string, number>()
  for (const r of [...columns.keys()].sort((a, b) => a - b)) {
    const list = columns.get(r)!
    const weight = (n: ErdLayoutNode): number => {
      const ps = [...(parents.get(n.id) ?? [])].map((p) => order.get(p)).filter((v): v is number => v !== undefined)
      return ps.length ? ps.reduce((s, v) => s + v, 0) / ps.length : Number.MAX_SAFE_INTEGER
    }
    list.sort((a, b) => weight(a) - weight(b) || a.id.localeCompare(b.id))
    let y = 0
    list.forEach((n, i) => {
      order.set(n.id, i)
      const height = nodeHeight(n.columns)
      const x = r * (NODE_WIDTH + GAP_X)
      out.set(n.id, { x: direction === 'rtl' && x !== 0 ? -x : x, y, width: NODE_WIDTH, height, rank: r })
      y += height + GAP_Y
    })
  }
  return out
}

/** Shapes of GET /schema/erd. */
export interface ErdColumn {
  name: string
  type: string
  nullable: boolean
  field: string | null
  system: boolean
}
export interface ErdNodeApi {
  id: string
  role: string
  form: string
  kind: string
  label: string
  columns: ErdColumn[]
}
export interface ErdEdgeApi {
  from: string
  to: string
  column: string
  on_delete: string
}
