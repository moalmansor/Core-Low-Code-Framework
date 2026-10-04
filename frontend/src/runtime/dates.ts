import { civilFromDays, daysFromCivil, formatDate, formatDatetime, parseDate, parseDatetime, timezoneOffset, weekday } from '@/expressions/civil'
import { asciiDigits } from '@/expressions/unicode'

/**
 * Conversions between native input values and API values (backend
 * ValueCodec): dates `YYYY-MM-DD`, times `HH:MM:SS`, datetimes in UTC
 * `YYYY-MM-DDTHH:MM:SSZ`, months stored as their first day and ISO weeks as
 * their Monday. A field's timezone setting decides how a local datetime maps
 * to UTC: `user` (the browser's zone), `utc`, or `fixed:<IANA zone>`.
 */

function pad(n: number, w = 2): string {
  return String(n).padStart(w, '0')
}

function offsetAt(epoch: number, timezone: string): number {
  if (timezone === 'utc') return 0
  if (timezone.startsWith('fixed:')) {
    try {
      return timezoneOffset(epoch, timezone.slice(6))
    } catch {
      return 0
    }
  }
  return -new Date(epoch * 1000).getTimezoneOffset() * 60
}

/** API datetime → value of an `<input type="datetime-local">`. */
export function datetimeToInput(api: unknown, timezone = 'user'): string {
  const epoch = typeof api === 'string' ? parseDatetime(api) : null
  if (epoch === null) return ''
  return formatDatetime(epoch + offsetAt(epoch, timezone)).slice(0, 16)
}

/** `<input type="datetime-local">` value → API datetime (UTC), or null. */
export function inputToDatetime(input: string, timezone = 'user'): string | null {
  const m = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(asciiDigits(input.trim()))
  if (m === null) return null
  const day = parseDate(m[1]!)
  if (day === null) return null
  const local = day * 86400 + Number(m[2]) * 3600 + Number(m[3]) * 60 + Number(m[4] ?? 0)
  let epoch = local - offsetAt(local, timezone)
  epoch = local - offsetAt(epoch, timezone)
  return formatDatetime(epoch)
}

export function timeToInput(api: unknown): string {
  return typeof api === 'string' ? api.slice(0, 5) : ''
}

export function inputToTime(input: string): string | null {
  const m = /^(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(asciiDigits(input.trim()))
  if (m === null || Number(m[1]) > 23 || Number(m[2]) > 59) return null
  return `${m[1]}:${m[2]}:${m[3] ?? '00'}`
}

export function monthToInput(api: unknown): string {
  return typeof api === 'string' ? api.slice(0, 7) : ''
}

export function inputToMonth(input: string): string | null {
  const s = asciiDigits(input.trim())
  return /^\d{4}-\d{2}$/.test(s) && parseDate(`${s}-01`) !== null ? `${s}-01` : null
}

/** ISO week (`YYYY-Www`) of a date. */
export function weekToInput(api: unknown): string {
  const day = typeof api === 'string' ? parseDate(api.slice(0, 10)) : null
  if (day === null) return ''
  const thursday = day - ((weekday(day) + 6) % 7) + 3
  const [year] = civilFromDays(thursday)
  const firstThursday = daysFromCivil(year, 1, 4)
  const week1Monday = firstThursday - ((weekday(firstThursday) + 6) % 7)
  return `${pad(year, 4)}-W${pad(Math.floor((thursday - 3 - week1Monday) / 7) + 1)}`
}

/** `YYYY-Www` → the Monday of that ISO week (as the server stores it). */
export function inputToWeek(input: string): string | null {
  const m = /^(\d{4})-W(\d{2})$/.exec(asciiDigits(input.trim()))
  if (m === null) return null
  const year = Number(m[1])
  const week = Number(m[2])
  if (week < 1 || week > 53) return null
  const jan4 = daysFromCivil(year, 1, 4)
  const monday = jan4 - ((weekday(jan4) + 6) % 7) + (week - 1) * 7
  return formatDate(monday)
}

export function dateToInput(api: unknown): string {
  return typeof api === 'string' ? api.slice(0, 10) : ''
}

export function inputToDate(input: string): string | null {
  const s = asciiDigits(input.trim())
  return parseDate(s) !== null ? s : null
}

/** Hijri (Umm al-Qura) rendering of an API date for the dual/Hijri calendar hint. */
export function hijriText(api: unknown, locale: string): string {
  if (typeof api !== 'string' || parseDate(api.slice(0, 10)) === null) return ''
  return new Intl.DateTimeFormat(`${locale}-u-ca-islamic-umalqura`, { timeZone: 'UTC', year: 'numeric', month: 'long', day: 'numeric' }).format(new Date(`${api.slice(0, 10)}T00:00:00Z`))
}
