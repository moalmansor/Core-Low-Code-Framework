import { Decimal } from './decimal'

/**
 * A record as seen by the expression language: field and relation values by
 * key. The runtime supplies sources backed by records and relation paths; the
 * conformance corpus supplies plain maps (ArrayRecord). Twin of
 * backend/app/Expressions/Values/RecordSource.php.
 */
export interface RecordSource {
  /** The value of a key, or null when the key does not exist (MISSING_REF). */
  get(key: string): Value | null
  /** The record title, used by to_text(record). */
  title(): string | null
  /** Stable identity for equality (form uuid + record id), or null. */
  identity(): string | null
}

/**
 * A typed value of the expression language (expression-language.md §3).
 * Payloads: boolean → boolean; number → Decimal; text → NFC string; date →
 * days since 1970-01-01; datetime → seconds since the Unix epoch (UTC); time
 * → seconds since midnight; duration → Decimal whole seconds; list → values;
 * record → RecordSource. Twin of backend/app/Expressions/Values/Value.php.
 */
export type Value =
  | { readonly type: 'null'; readonly data: null }
  | { readonly type: 'boolean'; readonly data: boolean }
  | { readonly type: 'number'; readonly data: Decimal }
  | { readonly type: 'text'; readonly data: string }
  | { readonly type: 'date'; readonly data: number }
  | { readonly type: 'datetime'; readonly data: number }
  | { readonly type: 'time'; readonly data: number }
  | { readonly type: 'duration'; readonly data: Decimal }
  | { readonly type: 'list'; readonly data: readonly Value[] }
  | { readonly type: 'record'; readonly data: RecordSource }

export type ValueType = Value['type']

const NULL: Value = Object.freeze({ type: 'null', data: null })

export const Values = {
  null: (): Value => NULL,
  bool: (data: boolean): Value => ({ type: 'boolean', data }),
  number: (data: Decimal): Value => ({ type: 'number', data }),
  int: (n: number): Value => ({ type: 'number', data: Decimal.of(n) }),
  text: (data: string): Value => ({ type: 'text', data }),
  date: (days: number): Value => ({ type: 'date', data: days }),
  datetime: (seconds: number): Value => ({ type: 'datetime', data: seconds }),
  time: (seconds: number): Value => ({ type: 'time', data: seconds }),
  duration: (seconds: Decimal): Value => ({ type: 'duration', data: seconds }),
  list: (items: readonly Value[]): Value => ({ type: 'list', data: [...items] }),
  record: (record: RecordSource): Value => ({ type: 'record', data: record }),
}

export function isNull(value: Value): boolean {
  return value.type === 'null'
}

export function items(value: Value): readonly Value[] {
  return value.type === 'list' ? value.data : []
}

/** A record given as a map of already-typed values (corpus, previews, tests). */
export class ArrayRecord implements RecordSource {
  private readonly fields: ReadonlyMap<string, Value>
  private readonly recordTitle: string | null
  private readonly recordIdentity: string | null

  constructor(values: Readonly<Record<string, Value>> | ReadonlyMap<string, Value>, title: string | null = null, identity: string | null = null) {
    this.fields = values instanceof Map ? new Map(values) : new Map(Object.entries(values))
    this.recordTitle = title
    this.recordIdentity = identity
  }

  get(key: string): Value | null {
    return this.fields.get(key) ?? null
  }

  title(): string | null {
    return this.recordTitle
  }

  identity(): string | null {
    return this.recordIdentity
  }

  values(): ReadonlyMap<string, Value> {
    return this.fields
  }
}
