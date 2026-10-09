import { formatDate, formatDatetime, formatTime, parseDate, parseDatetime, parseTime } from './civil'
import { Decimal } from './decimal'
import { ArrayRecord, Values, type Value } from './values'

/**
 * The typed value encoding of expression-language.md §6/§10:
 * `{"t": "number", "v": "12.5"}`, lists as `{"t":"list","v":[…]}`, repeater
 * rows as `{"t":"rows","v":[{field: <typed>…}…]}` and records as
 * `{"t":"record","v":{field: <typed>…}}`. A record may carry its display
 * title and identity under the reserved keys `@title` and `@id` (field keys
 * never start with `@`). Twin of backend/app/Expressions/Values/Envelope.php.
 */
export type EncodedValue =
  | { t: 'null' }
  | { t: 'boolean'; v: boolean }
  | { t: 'number' | 'duration' | 'text' | 'date' | 'datetime' | 'time'; v: string }
  | { t: 'list'; v: EncodedValue[] }
  | { t: 'record'; v: { '@title'?: string; '@id'?: string } }

export function encode(value: Value): EncodedValue {
  switch (value.type) {
    case 'null':
      return { t: 'null' }
    case 'boolean':
      return { t: 'boolean', v: value.data }
    case 'number':
    case 'duration':
      return { t: value.type, v: value.data.toString() }
    case 'text':
      return { t: 'text', v: value.data }
    case 'date':
      return { t: 'date', v: formatDate(value.data) }
    case 'datetime':
      return { t: 'datetime', v: formatDatetime(value.data) }
    case 'time':
      return { t: 'time', v: formatTime(value.data) }
    case 'list':
      return { t: 'list', v: value.data.map(encode) }
    case 'record': {
      const v: { '@title'?: string; '@id'?: string } = {}
      const title = value.data.title()
      const identity = value.data.identity()
      if (title !== null) v['@title'] = title
      if (identity !== null) v['@id'] = identity
      return { t: 'record', v }
    }
  }
}

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

/** PHP's (string) cast of a JSON scalar. */
function str(value: unknown): string {
  if (value === null || value === undefined || value === false) return ''
  if (value === true) return '1'
  return String(value)
}

function entries(value: unknown): unknown[] {
  if (Array.isArray(value)) return value
  if (isObject(value)) return Object.values(value)
  return value === null || value === undefined ? [] : [value]
}

/** Decodes a typed envelope; throws on a malformed one. */
export function decode(envelope: unknown): Value {
  if (envelope === null || envelope === undefined) return Values.null()
  if (!isObject(envelope)) throw new Error('Invalid envelope')
  const t = envelope.t ?? 'null'
  const v = envelope.v ?? null
  const fail = (what: string): never => {
    throw new Error(`Invalid ${what} ${str(v)}`)
  }
  switch (t) {
    case 'null':
      return Values.null()
    case 'boolean':
      return Values.bool(Boolean(v) && v !== '0')
    case 'number':
      return Values.number(Decimal.parse(str(v)) ?? fail('number'))
    case 'duration':
      return Values.duration(Decimal.parse(str(v)) ?? fail('duration'))
    case 'text':
      return Values.text(str(v))
    case 'date':
      return Values.date(parseDate(str(v)) ?? fail('date'))
    case 'datetime':
      return Values.datetime(parseDatetime(str(v)) ?? fail('datetime'))
    case 'time':
      return Values.time(parseTime(str(v)) ?? fail('time'))
    case 'list':
      return Values.list(entries(v).map(decode))
    case 'rows':
      return Values.list(entries(v).map((row) => Values.record(record(isObject(row) ? row : {}))))
    case 'record':
      return Values.record(record(isObject(v) ? v : {}))
    default:
      throw new Error(`Unknown envelope type ${str(t)}`)
  }
}

/** A record from `{field: <typed>…}` with the optional reserved keys `@title` and `@id`. */
export function record(fields: Readonly<Record<string, unknown>>): ArrayRecord {
  const values = new Map<string, Value>()
  const title = fields['@title'] !== undefined && fields['@title'] !== null ? str(fields['@title']) : null
  const identity = fields['@id'] !== undefined && fields['@id'] !== null ? str(fields['@id']) : null
  for (const [key, envelope] of Object.entries(fields)) {
    if (key === '@title' || key === '@id') continue
    values.set(key, decode(envelope))
  }
  return new ArrayRecord(values, title, identity)
}
