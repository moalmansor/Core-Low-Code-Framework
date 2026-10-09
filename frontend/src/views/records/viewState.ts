/**
 * Table views at runtime (specification §4.14 "Table view"): the view the
 * server chose for the user, its filter conditions in the URL (`vf`), the
 * columns the user shows, and the state a saved view stores.
 */

export interface ViewColumn {
  key: string
  path: string[]
  label: string
  width: number | null
  pinned: 'none' | 'start' | 'end'
  visible: boolean
  sortable: boolean
  format: Record<string, unknown> | null
  aggregate: 'none' | 'count' | 'sum' | 'avg' | 'min' | 'max'
  linked: boolean
}

export interface ViewFilter {
  uuid: string
  path: string[]
  label: string
  type: 'text' | 'number_range' | 'date_range' | 'options' | 'reference' | 'user' | 'status' | 'boolean' | string
  operators: string[]
  quick: boolean
  default: unknown
  options: { value: string; label: string; color?: string | null }[] | null
}

export interface ActiveView {
  uuid: string
  key: string
  name: string
  page_size: number
  default_sort: { path: string[]; dir: 'asc' | 'desc' }[]
  show_totals: boolean
  column_chooser: boolean
  global_search: boolean
  row_options: { view: boolean; edit: boolean; log: boolean }
  columns: ViewColumn[]
  filters: ViewFilter[]
}

export interface ViewCondition {
  op: string
  value?: string | null
  values?: string[]
  from?: string | null
  to?: string | null
}

export interface SavedView {
  uuid: string
  name: string
  view: string
  state: { columns?: string[]; widths?: Record<string, number>; filters?: Record<string, ViewCondition>; sort?: { key: string; dir: 'asc' | 'desc' } | null; page_size?: number; search?: string | null }
  is_default: boolean
  is_shared: boolean
  owner: string | null
  mine: boolean
  shares: { type: string; uuid: string | null }[]
}

/** Whether a condition narrows the list. */
export function conditionActive(c: ViewCondition | undefined): boolean {
  if (!c) return false
  if (c.op === 'is_empty') return true
  if (c.op === 'in') return (c.values ?? []).length > 0
  if (c.op === 'between') return !!c.from || !!c.to
  return c.value !== null && c.value !== undefined && c.value !== ''
}

/** The active conditions only, as the `vf` query parameter carries them. */
export function activeConditions(all: Record<string, ViewCondition>): Record<string, ViewCondition> {
  return Object.fromEntries(Object.entries(all).filter(([, c]) => conditionActive(c)))
}

/** Reads `vf` from the URL; anything malformed is dropped. */
export function decodeConditions(raw: unknown): Record<string, ViewCondition> {
  if (typeof raw !== 'string' || !raw) return {}
  try {
    const parsed = JSON.parse(raw) as unknown
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {}
    const out: Record<string, ViewCondition> = {}
    for (const [k, v] of Object.entries(parsed as Record<string, unknown>)) {
      if (v && typeof v === 'object' && typeof (v as ViewCondition).op === 'string') out[k] = v as ViewCondition
    }
    return out
  } catch {
    return {}
  }
}

export function encodeConditions(all: Record<string, ViewCondition>): string | undefined {
  const active = activeConditions(all)
  return Object.keys(active).length ? JSON.stringify(active) : undefined
}

/** The value shown in a view column: main-form fields from `values`, linked and system paths from `linked`. */
export function columnValue(row: { values: Record<string, unknown>; linked?: Record<string, unknown> }, column: ViewColumn): unknown {
  return column.linked ? (row.linked?.[column.key] ?? null) : (row.values[column.key] ?? null)
}

/** Columns shown: the user's choice (when the view allows it) or the view's visible columns, pinned ones first and last. */
export function shownColumns(view: ActiveView, chosen: string[] | null): ViewColumn[] {
  const list = view.columns.filter((c) => (chosen && view.column_chooser ? chosen.includes(c.key) : c.visible))
  return [...list.filter((c) => c.pinned === 'start'), ...list.filter((c) => c.pinned === 'none'), ...list.filter((c) => c.pinned === 'end')]
}
