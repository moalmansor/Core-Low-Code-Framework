/**
 * State of a records list (docs/design-system.md, data tables): search,
 * typed filters, sorting and paging. It lives in the URL, so a filtered list
 * can be bookmarked and shared, and is sent to the API as `filter[...]`.
 *
 * Operators per filter kind (fieldType(...).filter):
 * - text: contains, starts with, equals
 * - choice with static options: equals, any of
 * - lookup and remote choices: equals (a picked record)
 * - boolean: is
 * - number, date, date-time, time: between (either bound optional)
 */
export type FilterOp = 'contains' | 'starts_with' | 'equals' | 'in' | 'is' | 'between'

export interface FilterRow {
  key: string
  op: FilterOp
  value: string | null
  values: string[]
  from: string | null
  to: string | null
  /** Display text of a picked record (lookups). */
  label: string | null
}

export interface ListState {
  search: string
  page: number
  perPage: number
  sort: string
  direction: 'asc' | 'desc'
  trashed: boolean
  filters: FilterRow[]
}

export const PER_PAGE = [10, 25, 50, 100] as const
export const DEFAULT_STATE: ListState = { search: '', page: 1, perPage: 25, sort: 'updated_at', direction: 'desc', trashed: false, filters: [] }

const RANGE_KINDS = ['number', 'date', 'datetime', 'time']

export function operatorsFor(kind: string, staticChoice: boolean): FilterOp[] {
  if (kind === 'text') return ['contains', 'starts_with', 'equals']
  if (kind === 'choice' && staticChoice) return ['equals', 'in']
  if (kind === 'choice' || kind === 'lookup') return ['equals']
  if (kind === 'boolean') return ['is']
  if (RANGE_KINDS.includes(kind)) return ['between']
  return ['contains']
}

export function emptyRow(key: string, op: FilterOp): FilterRow {
  return { key, op, value: null, values: [], from: null, to: null, label: null }
}

/** Whether a row narrows the list (an empty row is shown but not applied). */
export function isActive(row: FilterRow): boolean {
  if (row.op === 'in') return row.values.length > 0
  if (row.op === 'between') return !!row.from || !!row.to
  return row.value !== null && row.value !== ''
}

/** The API's `filter` object (App\Modules\Records\Runtime\RecordQuery). */
export function toApiFilter(rows: FilterRow[]): Record<string, unknown> {
  const out: Record<string, unknown> = {}
  for (const r of rows.filter(isActive)) {
    if (r.op === 'in') out[r.key] = { op: 'in', values: r.values }
    else if (r.op === 'between') out[r.key] = { ...(r.from ? { from: r.from } : {}), ...(r.to ? { to: r.to } : {}) }
    // A picked record (lookup) and a boolean go as plain values; text and choices carry their operator.
    else if (r.op === 'is' || r.label !== null) out[r.key] = r.value
    else out[r.key] = { op: r.op, value: r.value }
  }
  return out
}

// '~' separates parts and encodeURIComponent leaves it as is.
const enc = (s: string) => encodeURIComponent(s).replace(/~/g, '%7E')
const dec = (s: string | undefined) => (s === undefined || s === '' ? null : decodeURIComponent(s))

/** URL query of a state; defaults are omitted. */
export function encodeQuery(s: ListState): Record<string, string> {
  const q: Record<string, string> = {}
  if (s.search) q.q = s.search
  if (s.page !== 1) q.page = String(s.page)
  if (s.perPage !== DEFAULT_STATE.perPage) q.per = String(s.perPage)
  if (s.sort !== DEFAULT_STATE.sort || s.direction !== DEFAULT_STATE.direction) q.sort = `${s.sort}:${s.direction}`
  if (s.trashed) q.trash = '1'
  for (const r of s.filters) {
    const payload = r.op === 'in' ? r.values.map(enc).join(',') : r.op === 'between' ? `${enc(r.from ?? '')}~${enc(r.to ?? '')}` : `${enc(r.value ?? '')}${r.label !== null ? `~${enc(r.label)}` : ''}`
    q[`f.${r.key}`] = `${r.op}:${payload}`
  }
  return q
}

const OPS: FilterOp[] = ['contains', 'starts_with', 'equals', 'in', 'is', 'between']

export function decodeQuery(query: Record<string, unknown>, allowedKeys: string[]): ListState {
  const str = (v: unknown) => (typeof v === 'string' ? v : Array.isArray(v) && typeof v[0] === 'string' ? v[0] : '')
  const s: ListState = { ...DEFAULT_STATE, filters: [] }
  s.search = str(query.q)
  s.page = Math.max(1, Number.parseInt(str(query.page), 10) || 1)
  const per = Number.parseInt(str(query.per), 10)
  s.perPage = (PER_PAGE as readonly number[]).includes(per) ? per : DEFAULT_STATE.perPage
  const [sort, dir] = str(query.sort).split(':')
  if (sort && /^[a-z_][a-z0-9_]{0,63}$/.test(sort)) {
    s.sort = sort
    s.direction = dir === 'asc' ? 'asc' : 'desc'
  }
  s.trashed = str(query.trash) === '1'
  for (const [k, v] of Object.entries(query)) {
    if (!k.startsWith('f.')) continue
    const key = k.slice(2)
    if (!allowedKeys.includes(key)) continue
    const raw = str(v)
    const colon = raw.indexOf(':')
    const op = raw.slice(0, colon) as FilterOp
    if (colon < 0 || !OPS.includes(op)) continue
    const payload = raw.slice(colon + 1)
    const row = emptyRow(key, op)
    if (op === 'in')
      row.values = payload
        .split(',')
        .map((p) => dec(p) ?? '')
        .filter(Boolean)
    else if (op === 'between') {
      const [from, to] = payload.split('~')
      row.from = dec(from)
      row.to = dec(to)
    } else {
      const [value, label] = payload.split('~')
      row.value = dec(value)
      row.label = label === undefined ? null : dec(label)
    }
    s.filters.push(row)
  }
  return s
}
