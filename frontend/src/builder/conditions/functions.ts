import { arity, isRowForm, names, paramTypes, returns } from '@/expressions/signatures'

/** The function library of expression-language.md §9, grouped as the document groups it. */
export const FUNCTION_GROUPS: Record<string, string[]> = {
  conditional: ['if', 'switch', 'coalesce', 'is_empty', 'is_null', 'in', 'changed', 'changed_from_to', 'has_role', 'in_department'],
  math: ['abs', 'round', 'floor', 'ceil', 'trunc', 'mod', 'power', 'sqrt', 'min', 'max', 'clamp', 'sign'],
  text: ['len', 'upper', 'lower', 'trim', 'left', 'right', 'mid', 'contains', 'starts_with', 'ends_with', 'replace', 'concat', 'split', 'pad_left', 'matches', 'normalize_arabic'],
  date: [
    'today',
    'now',
    'date',
    'datetime',
    'year',
    'month',
    'day',
    'weekday',
    'add_days',
    'add_months',
    'add_years',
    'diff_days',
    'diff_months',
    'start_of_month',
    'end_of_month',
    'seconds',
    'minutes',
    'hours',
    'days',
    'duration_seconds',
    'to_date',
    'hijri_year',
    'hijri_month',
    'hijri_day',
    'from_hijri',
    'hijri_text',
    'is_working_day',
    'add_working_days',
  ],
  aggregate: ['sum', 'avg', 'count', 'first', 'last', 'join', 'distinct'],
  conversion: ['to_number', 'to_text', 'to_boolean'],
}

/** Every registered function appears in exactly one group. */
export function ungrouped(): string[] {
  const grouped = new Set(Object.values(FUNCTION_GROUPS).flat())
  return names().filter((n) => !grouped.has(n))
}

/** A readable signature, e.g. `round(number, number?) → number`; row-form aggregates show both forms. */
export function signature(fn: string): string {
  const a = arity(fn)
  if (!a) return fn
  const [min, max] = a
  const count = max ?? min + 1
  const params: string[] = []
  for (let i = 0; i < count; i++) {
    const types = paramTypes(fn, i, max === null ? count : Math.max(min, i + 1))
    params.push(`${types.join('|')}${i >= min ? '?' : ''}${max === null && i === count - 1 ? ', …' : ''}`)
  }
  let text = `${fn}(${params.join(', ')}) → ${resultLabel(fn)}`
  if (isRowForm(fn, 2) || isRowForm(fn, 3)) {
    const rowArgs = fn === 'join' ? 'rows, expression, text' : fn === 'count' ? 'rows, condition' : 'rows, expression'
    text += `  ·  ${fn}(${rowArgs})`
  }
  return text
}

function resultLabel(fn: string): string {
  const r = returns(fn)
  if (r === 'branches' || r === 'aggregate' || r === 'minmax' || r.startsWith('arg:') || r.startsWith('item:')) return 'any'
  return r
}
