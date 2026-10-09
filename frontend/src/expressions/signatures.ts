/**
 * The function registry of expression-language.md §9: argument counts, the
 * static parameter types the type checker enforces, and result types. Twin of
 * backend/app/Expressions/Evaluation/Signatures.php.
 *
 * Parameter type sets use the names of §3 plus `any`, `list` (a list of any
 * item type), and `scalar` (number, text, date, datetime, time, duration).
 * The last parameter entry repeats for variadic functions. Result types are a
 * type name or one of: `arg:N` (type of argument N), `branches` (common type
 * of if/switch/coalesce results), `item:N` (item type of list argument N),
 * `minmax`, `aggregate`.
 */

interface Signature {
  min: number
  max: number | null
  params: string[][]
  returns: string
}

const SCALAR = ['number', 'text', 'date', 'datetime', 'time', 'duration']

const TABLE: Readonly<Record<string, Signature>> = {
  // §9.1
  if: { min: 3, max: 3, params: [['boolean'], ['any'], ['any']], returns: 'branches' },
  switch: { min: 2, max: null, params: [['any']], returns: 'branches' },
  coalesce: { min: 1, max: null, params: [['any']], returns: 'branches' },
  is_empty: { min: 1, max: 1, params: [['any']], returns: 'boolean' },
  is_null: { min: 1, max: 1, params: [['any']], returns: 'boolean' },
  in: { min: 2, max: 2, params: [['any'], ['list']], returns: 'boolean' },
  changed: { min: 1, max: 1, params: [['any']], returns: 'boolean' },
  changed_from_to: { min: 3, max: 3, params: [['any'], ['any'], ['any']], returns: 'boolean' },
  has_role: { min: 1, max: 1, params: [['text']], returns: 'boolean' },
  in_department: { min: 1, max: 2, params: [['text'], ['boolean']], returns: 'boolean' },
  // §9.2
  abs: { min: 1, max: 1, params: [['number']], returns: 'number' },
  round: { min: 1, max: 2, params: [['number'], ['number']], returns: 'number' },
  floor: { min: 1, max: 1, params: [['number']], returns: 'number' },
  ceil: { min: 1, max: 1, params: [['number']], returns: 'number' },
  trunc: { min: 1, max: 2, params: [['number'], ['number']], returns: 'number' },
  mod: { min: 2, max: 2, params: [['number'], ['number']], returns: 'number' },
  power: { min: 2, max: 2, params: [['number'], ['number']], returns: 'number' },
  sqrt: { min: 1, max: 1, params: [['number']], returns: 'number' },
  min: { min: 1, max: null, params: [['scalar', 'list']], returns: 'minmax' },
  max: { min: 1, max: null, params: [['scalar', 'list']], returns: 'minmax' },
  clamp: { min: 3, max: 3, params: [['scalar'], ['scalar'], ['scalar']], returns: 'arg:0' },
  sign: { min: 1, max: 1, params: [['number']], returns: 'number' },
  // §9.3
  len: { min: 1, max: 1, params: [['text']], returns: 'number' },
  upper: { min: 1, max: 1, params: [['text']], returns: 'text' },
  lower: { min: 1, max: 1, params: [['text']], returns: 'text' },
  trim: { min: 1, max: 1, params: [['text']], returns: 'text' },
  left: { min: 2, max: 2, params: [['text'], ['number']], returns: 'text' },
  right: { min: 2, max: 2, params: [['text'], ['number']], returns: 'text' },
  mid: { min: 3, max: 3, params: [['text'], ['number'], ['number']], returns: 'text' },
  contains: { min: 2, max: 2, params: [['text'], ['text']], returns: 'boolean' },
  starts_with: { min: 2, max: 2, params: [['text'], ['text']], returns: 'boolean' },
  ends_with: { min: 2, max: 2, params: [['text'], ['text']], returns: 'boolean' },
  replace: { min: 3, max: 3, params: [['text'], ['text'], ['text']], returns: 'text' },
  concat: { min: 1, max: null, params: [['any']], returns: 'text' },
  split: { min: 2, max: 2, params: [['text'], ['text']], returns: 'list<text>' },
  pad_left: { min: 3, max: 3, params: [['text'], ['number'], ['text']], returns: 'text' },
  matches: { min: 2, max: 2, params: [['text'], ['text']], returns: 'boolean' },
  normalize_arabic: { min: 1, max: 1, params: [['text']], returns: 'text' },
  // §9.4
  today: { min: 0, max: 0, params: [], returns: 'date' },
  now: { min: 0, max: 0, params: [], returns: 'datetime' },
  date: { min: 3, max: 3, params: [['number'], ['number'], ['number']], returns: 'date' },
  datetime: { min: 2, max: 2, params: [['date'], ['time']], returns: 'datetime' },
  year: { min: 1, max: 1, params: [['date']], returns: 'number' },
  month: { min: 1, max: 1, params: [['date']], returns: 'number' },
  day: { min: 1, max: 1, params: [['date']], returns: 'number' },
  weekday: { min: 1, max: 1, params: [['date']], returns: 'number' },
  add_days: { min: 2, max: 2, params: [['date'], ['number']], returns: 'date' },
  add_months: { min: 2, max: 2, params: [['date'], ['number']], returns: 'date' },
  add_years: { min: 2, max: 2, params: [['date'], ['number']], returns: 'date' },
  diff_days: { min: 2, max: 2, params: [['date'], ['date']], returns: 'number' },
  diff_months: { min: 2, max: 2, params: [['date'], ['date']], returns: 'number' },
  start_of_month: { min: 1, max: 1, params: [['date']], returns: 'date' },
  end_of_month: { min: 1, max: 1, params: [['date']], returns: 'date' },
  seconds: { min: 1, max: 1, params: [['number']], returns: 'duration' },
  minutes: { min: 1, max: 1, params: [['number']], returns: 'duration' },
  hours: { min: 1, max: 1, params: [['number']], returns: 'duration' },
  days: { min: 1, max: 1, params: [['number']], returns: 'duration' },
  duration_seconds: { min: 1, max: 1, params: [['duration']], returns: 'number' },
  to_date: { min: 1, max: 1, params: [['date', 'datetime', 'text']], returns: 'date' },
  hijri_year: { min: 1, max: 1, params: [['date']], returns: 'number' },
  hijri_month: { min: 1, max: 1, params: [['date']], returns: 'number' },
  hijri_day: { min: 1, max: 1, params: [['date']], returns: 'number' },
  from_hijri: { min: 3, max: 3, params: [['number'], ['number'], ['number']], returns: 'date' },
  hijri_text: { min: 1, max: 1, params: [['date']], returns: 'text' },
  is_working_day: { min: 1, max: 1, params: [['date']], returns: 'boolean' },
  add_working_days: { min: 2, max: 2, params: [['date'], ['number']], returns: 'date' },
  // §9.5 (list form; row form handled by isRowForm)
  sum: { min: 1, max: 2, params: [['list'], ['any']], returns: 'aggregate' },
  avg: { min: 1, max: 2, params: [['list'], ['any']], returns: 'aggregate' },
  count: { min: 1, max: 2, params: [['list'], ['boolean']], returns: 'number' },
  first: { min: 1, max: 2, params: [['list'], ['any']], returns: 'aggregate' },
  last: { min: 1, max: 2, params: [['list'], ['any']], returns: 'aggregate' },
  join: { min: 2, max: 3, params: [['list'], ['any'], ['text']], returns: 'text' },
  distinct: { min: 1, max: 1, params: [['list']], returns: 'arg:0' },
  // §9.6
  to_number: { min: 1, max: 1, params: [['number', 'text', 'boolean']], returns: 'number' },
  to_text: { min: 1, max: 1, params: [['any']], returns: 'text' },
  to_boolean: { min: 1, max: 1, params: [['boolean', 'number', 'text']], returns: 'boolean' },
}

export function exists(fn: string): boolean {
  return Object.prototype.hasOwnProperty.call(TABLE, fn)
}

export function names(): string[] {
  return Object.keys(TABLE)
}

/** [min, max] argument counts (max null = variadic), or null for an unknown function. */
export function arity(fn: string): [number, number | null] | null {
  if (!exists(fn)) return null
  const s = TABLE[fn]!
  return [s.min, s.max]
}

/** Row form of an aggregate (§9.5): `sum(rows, expr)`, `count(rows, cond)`, `join(rows, expr, sep)`. */
export function isRowForm(fn: string, argCount: number): boolean {
  switch (fn) {
    case 'sum':
    case 'avg':
    case 'first':
    case 'last':
    case 'count':
      return argCount === 2
    case 'join':
      return argCount === 3
    default:
      return false
  }
}

/** Allowed static types for argument `index` (already expanded: `scalar` → concrete types). */
export function paramTypes(fn: string, index: number, argCount: number): string[] {
  const s = TABLE[fn]!
  if (isRowForm(fn, argCount)) {
    // rows reference, per-row expression, separator
    if (index === 0) return ['list']
    if (index === 1) return fn === 'count' ? ['boolean'] : ['any']
    return ['text']
  }
  if (['sum', 'avg', 'first', 'last'].includes(fn) || (fn === 'count' && argCount === 1)) return ['list']
  if (fn === 'join') return index === 0 ? ['list'] : ['text']
  const params = s.params
  const set = params[index] ?? (params.length === 0 ? ['any'] : params[params.length - 1]!)
  return set.flatMap((t) => (t === 'scalar' ? SCALAR : [t]))
}

export function returns(fn: string): string {
  return TABLE[fn]!.returns
}

/** Functions whose first argument must be a reference. */
export function requiresReference(fn: string): boolean {
  return fn === 'changed' || fn === 'changed_from_to'
}
