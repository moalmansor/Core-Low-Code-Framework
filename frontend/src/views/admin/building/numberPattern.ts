import { daysFromCivil } from '@/expressions/civil'
import { fromDays } from '@/expressions/ummAlQura'

/**
 * Client mirror of the server's NumberGenerator rendering (specification
 * §4.34), used for the live preview while a pattern is edited. The server's
 * own `next_preview` remains authoritative once a sequence is saved.
 *
 * Tokens: {prefix} {yyyy} {yy} {MM} {dd} {seq} {seq:N}.
 */

export const PATTERN_TOKENS = ['{prefix}', '{yyyy}', '{yy}', '{MM}', '{dd}', '{seq}', '{seq:6}'] as const
const TOKEN_SOURCE = '\\{(prefix|yyyy|yy|MM|dd|seq(?::(\\d{1,2}))?)\\}'

export type ResetPeriod = 'never' | 'daily' | 'monthly' | 'yearly'
export type NumberCalendar = 'gregorian' | 'hijri'

export interface DateParts {
  yyyy: string
  yy: string
  MM: string
  dd: string
}

export interface SequenceShape {
  pattern: string
  prefix: string | null
  padding: number
  step: number
  reset_period: ResetPeriod
  calendar: NumberCalendar
  period_key: string
  current_value: number
}

const pad = (n: number, width: number) => String(n).padStart(width, '0')

export function validPattern(pattern: string): boolean {
  if (!pattern.includes('{seq') || [...pattern].length > 255) return false
  const rest = pattern.replace(new RegExp(TOKEN_SOURCE, 'g'), '')
  return !/[{}]/.test(rest)
}

/** Date parts of a calendar day (year, month, day as seen in the chosen calendar). */
export function dateParts(calendar: NumberCalendar, year: number, month: number, day: number): DateParts {
  if (calendar === 'hijri') {
    const h = fromDays(daysFromCivil(year, month, day))
    if (h) return { yyyy: pad(h[0], 4), yy: pad(h[0] % 100, 2), MM: pad(h[1], 2), dd: pad(h[2], 2) }
  }
  return { yyyy: pad(year, 4), yy: pad(year % 100, 2), MM: pad(month, 2), dd: pad(day, 2) }
}

export function periodKey(reset: ResetPeriod, parts: DateParts): string {
  switch (reset) {
    case 'daily':
      return parts.yyyy + parts.MM + parts.dd
    case 'monthly':
      return parts.yyyy + parts.MM
    case 'yearly':
      return parts.yyyy
    default:
      return 'all'
  }
}

export function renderPattern(pattern: string, prefix: string, padding: number, parts: DateParts, value: number): string {
  return pattern.replace(new RegExp(TOKEN_SOURCE, 'g'), (_m, token: string, width: string | undefined) => {
    if (token === 'prefix') return prefix
    if (token.startsWith('seq')) return String(value).padStart(width ? Number(width) : padding, '0')
    return parts[token as keyof DateParts]
  })
}

/** The value the next number would take (the counter restarts when the period changes). */
export function nextValue(seq: SequenceShape, parts: DateParts): number {
  return periodKey(seq.reset_period, parts) === seq.period_key ? seq.current_value + seq.step : seq.step
}

export function previewNext(seq: SequenceShape, on: Date = new Date()): string {
  const parts = dateParts(seq.calendar, on.getFullYear(), on.getMonth() + 1, on.getDate())
  return renderPattern(seq.pattern, seq.prefix ?? '', seq.padding, parts, nextValue(seq, parts))
}
