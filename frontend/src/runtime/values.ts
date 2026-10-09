import { formatDate, formatDatetime, formatTime, parseDate, parseDatetime, parseTime } from '@/expressions/civil'
import { Decimal, Values as V, type RecordSource, type Value } from '@/expressions'
import type { FormIndex } from './formIndex'
import type { ClientField, References, Row, Values } from './types'

/**
 * Conversions between API values (backend ValueCodec shapes) and expression
 * values, mirroring backend ValuesRecord::convert and ValueBridge::toApi so
 * the TypeScript runtime sees exactly what the PHP runtime sees.
 */

/** A referenced record known only by uuid and display title. Relation paths beyond it resolve on the server. */
export class ReferenceRecord implements RecordSource {
  private readonly uuid: string
  private readonly display: string | null

  constructor(uuid: string, display: string | null) {
    this.uuid = uuid
    this.display = display
  }

  get(): Value | null {
    return null
  }

  title(): string | null {
    return this.display
  }

  identity(): string | null {
    return this.uuid
  }
}

function num(x: unknown): Value {
  const d = typeof x === 'string' || typeof x === 'number' ? Decimal.parse(String(x)) : null
  return d === null ? V.null() : V.number(d)
}

function dateValue(x: unknown): Value {
  const d = typeof x === 'string' ? parseDate(x.slice(0, 10)) : null
  return d === null ? V.null() : V.date(d)
}

function datetimeValue(x: unknown): Value {
  const d = typeof x === 'string' ? parseDatetime(x) : null
  return d === null ? V.null() : V.datetime(d)
}

function timeValue(x: unknown): Value {
  const d = typeof x === 'string' ? parseTime(x) : null
  return d === null ? V.null() : V.time(d)
}

/** Expression value of a formula field's stored value (dbType is server-only, so the shape decides). */
function formulaValue(v: unknown): Value {
  if (typeof v === 'boolean') return V.bool(v)
  if (typeof v !== 'string') return num(v)
  if (Decimal.parse(v) !== null) return num(v)
  if (parseDatetime(v) !== null) return datetimeValue(v)
  if (parseDate(v) !== null) return dateValue(v)
  if (parseTime(v) !== null) return timeValue(v)
  return V.text(v)
}

/** Expression value of one field's API value. */
export function toExpression(index: FormIndex, field: ClientField, v: unknown, references: References = {}): Value {
  const storage = index.storage(field)
  if (storage === 'none') return V.null()
  if (v === null || v === undefined) return index.isList(field) ? V.list([]) : V.null()
  const ref = (uuid: unknown): Value => (typeof uuid === 'string' ? V.record(new ReferenceRecord(uuid, references[field.key]?.[uuid] ?? null)) : V.null())
  if (index.isMultiReference(field)) return V.list((Array.isArray(v) ? v : [v]).map(ref))
  if (index.isReference(field)) return ref(v)
  switch (storage) {
    case 'multi_choice':
    case 'files':
      return V.list((Array.isArray(v) ? v : [v]).map((x) => V.text(String(x))))
    case 'file':
      return V.list([V.text(String(v))])
    case 'decimal':
    case 'number':
    case 'int':
      return num(v)
    case 'currency':
      return num(typeof v === 'object' && v !== null ? (v as { amount?: unknown }).amount : v)
    case 'duration': {
      const d = Decimal.parse(String(v))
      return d === null ? V.null() : V.duration(d)
    }
    case 'bool':
    case 'consent':
      return V.bool(Boolean(v) && v !== '0' && v !== 'false')
    case 'date':
      return dateValue(v)
    case 'datetime':
      return datetimeValue(v)
    case 'time':
      return timeValue(v)
    case 'range_date':
    case 'range_datetime':
    case 'range_time': {
      const r = (typeof v === 'object' && v !== null ? v : {}) as { from?: unknown; to?: unknown }
      const item = storage === 'range_date' ? dateValue : storage === 'range_datetime' ? datetimeValue : timeValue
      return V.list([item(r.from ?? null), item(r.to ?? null)])
    }
    case 'map': {
      const m = (typeof v === 'object' && v !== null ? v : {}) as { lat?: unknown; lng?: unknown }
      return V.text(`${m.lat ?? ''},${m.lng ?? ''}`)
    }
    case 'phone':
      return V.text(String((typeof v === 'object' && v !== null ? (v as { number?: unknown }).number : v) ?? ''))
    case 'json':
      return V.text(JSON.stringify(v))
    case 'formula':
      return formulaValue(v)
    default:
      return V.text(String(v))
  }
}

function scalar(x: Value): unknown {
  switch (x.type) {
    case 'number':
    case 'duration':
      return x.data.toString()
    case 'text':
    case 'boolean':
      return x.data
    case 'date':
      return formatDate(x.data)
    case 'datetime':
      return formatDatetime(x.data)
    case 'time':
      return formatTime(x.data)
    case 'record':
      return x.data.identity()
    default:
      return null
  }
}

/** API value of an expression result for a field (backend ValueBridge::toApi). */
export function toApi(index: FormIndex, field: ClientField, v: Value): unknown {
  if (v.type === 'null') return null
  const storage = index.storage(field)
  if (v.type === 'list') {
    const items = v.data.map(scalar).filter((x) => x !== null)
    switch (storage) {
      case 'range_date':
      case 'range_time':
      case 'range_datetime':
        return { from: items[0] ?? null, to: items[1] ?? null }
      case 'multi_choice':
      case 'files':
        return items
      default:
        return index.isMultiReference(field) ? items : (items[0] ?? null)
    }
  }
  const s = scalar(v)
  switch (storage) {
    case 'bool':
    case 'consent':
      return typeof s === 'boolean' ? s : Boolean(s)
    case 'decimal':
    case 'number':
    case 'int':
    case 'currency':
    case 'duration':
      return typeof s === 'boolean' ? (s ? '1' : '0') : s
    case 'multi_choice':
    case 'files':
      return s === null ? [] : [String(s)]
    case 'formula':
      return typeof s === 'boolean' ? (s ? '1' : '0') : s
    default:
      return typeof s === 'boolean' ? (s ? 'true' : 'false') : s
  }
}

/**
 * The record (or a repeater row) as an expression record source
 * (backend ValuesRecord): repeaters become lists of row records.
 */
export class ValuesRecord implements RecordSource {
  private readonly cache = new Map<string, Value>()
  private readonly index: FormIndex
  private readonly values: Values | Row
  /** Field key → uuid of the fields this record holds. */
  private readonly keys: ReadonlyMap<string, string>
  private readonly references: References
  private readonly withRepeaters: boolean
  private readonly id: string | null

  constructor(index: FormIndex, values: Values | Row, keys: ReadonlyMap<string, string>, references: References, withRepeaters: boolean, id: string | null = null) {
    this.index = index
    this.values = values
    this.keys = keys
    this.references = references
    this.withRepeaters = withRepeaters
    this.id = id
  }

  static forRecord(index: FormIndex, values: Values, references: References = {}): ValuesRecord {
    return new ValuesRecord(index, values, index.keys, references, true)
  }

  static forRow(index: FormIndex, repeaterUuid: string, row: Row, references: References = {}): ValuesRecord {
    const keys = new Map(Object.entries(index.repeaters.get(repeaterUuid)?.fields ?? {}))
    return new ValuesRecord(index, row, keys, references, false, typeof row.uuid === 'string' ? row.uuid : null)
  }

  get(key: string): Value | null {
    const hit = this.cache.get(key)
    if (hit !== undefined) return hit
    let out: Value
    const repUuid = this.withRepeaters ? this.index.repeaterKeys.get(key) : undefined
    if (repUuid !== undefined) {
      const rows = Array.isArray(this.values[key]) ? (this.values[key] as Row[]) : []
      out = V.list(rows.map((r) => V.record(ValuesRecord.forRow(this.index, repUuid, r && typeof r === 'object' ? r : {}, this.references))))
    } else {
      const uuid = this.keys.get(key)
      if (uuid === undefined) return null
      out = toExpression(this.index, this.index.fields.get(uuid)!, this.values[key] ?? null, this.references)
    }
    this.cache.set(key, out)
    return out
  }

  title(): string | null {
    return null
  }

  identity(): string | null {
    return this.id
  }
}

/** Backend RecordValidator::empty: null, '', [], false, or an object whose members are all empty. */
export function isEmpty(v: unknown): boolean {
  if (v === null || v === undefined || v === '' || v === false) return true
  if (Array.isArray(v)) return v.length === 0
  if (typeof v === 'object') return Object.values(v as Record<string, unknown>).every((x) => x === null || x === undefined || x === '')
  return false
}

/** Text transforms applied on input (backend RecordPipeline::transform). */
export function transform(field: ClientField, value: unknown): unknown {
  if (typeof value !== 'string') return value
  let out = value
  for (const t of field.behavior.transforms ?? []) {
    if (t === 'trim') out = out.trim()
    else if (t === 'uppercase') out = out.toLocaleUpperCase()
    else if (t === 'lowercase') out = out.toLocaleLowerCase()
    else if (t === 'collapse_spaces') out = out.replace(/\s+/gu, ' ')
  }
  return out
}

/** Stable JSON used to compare values (key order of objects is preserved as produced). */
export function same(a: unknown, b: unknown): boolean {
  return JSON.stringify(a ?? null) === JSON.stringify(b ?? null)
}
