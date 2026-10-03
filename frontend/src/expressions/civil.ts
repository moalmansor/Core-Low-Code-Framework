/**
 * Proleptic Gregorian calendar arithmetic on day numbers (days since
 * 1970-01-01), using Howard Hinnant's civil-from-days algorithms. Twin of
 * backend/app/Expressions/Calendars/Civil.php: both runtimes use the same
 * integer algorithms, never the platform date library, so results are
 * identical over 0001-01-01 … 9999-12-31.
 */

export const MIN_DAY = -719162 // 0001-01-01
export const MAX_DAY = 2932896 // 9999-12-31

/** PHP intdiv: integer division truncated toward zero. */
export function intdiv(a: number, b: number): number {
  return Math.trunc(a / b)
}

function pad(n: number, width: number): string {
  return String(n).padStart(width, '0')
}

export function daysFromCivil(y: number, m: number, d: number): number {
  y -= m <= 2 ? 1 : 0
  const era = intdiv(y >= 0 ? y : y - 399, 400)
  const yoe = y - era * 400
  const doy = intdiv(153 * (m + (m > 2 ? -3 : 9)) + 2, 5) + d - 1
  const doe = yoe * 365 + intdiv(yoe, 4) - intdiv(yoe, 100) + doy
  return era * 146097 + doe - 719468
}

/** [year, month, day] */
export function civilFromDays(z: number): [number, number, number] {
  z += 719468
  const era = intdiv(z >= 0 ? z : z - 146096, 146097)
  const doe = z - era * 146097
  const yoe = intdiv(doe - intdiv(doe, 1460) + intdiv(doe, 36524) - intdiv(doe, 146096), 365)
  const y = yoe + era * 400
  const doy = doe - (365 * yoe + intdiv(yoe, 4) - intdiv(yoe, 100))
  const mp = intdiv(5 * doy + 2, 153)
  const d = doy - intdiv(153 * mp + 2, 5) + 1
  const m = mp + (mp < 10 ? 3 : -9)
  return [y + (m <= 2 ? 1 : 0), m, d]
}

export function isLeap(y: number): boolean {
  return (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0
}

export function daysInMonth(y: number, m: number): number {
  if (m === 2) return isLeap(y) ? 29 : 28
  return m === 4 || m === 6 || m === 9 || m === 11 ? 30 : 31
}

export function isValid(y: number, m: number, d: number): boolean {
  return y >= 1 && y <= 9999 && m >= 1 && m <= 12 && d >= 1 && d <= daysInMonth(y, m)
}

export function inRange(days: number): boolean {
  return days >= MIN_DAY && days <= MAX_DAY
}

/** 0 = Sunday … 6 = Saturday. */
export function weekday(days: number): number {
  return (((days + 4) % 7) + 7) % 7
}

export function formatDate(days: number): string {
  const [y, m, d] = civilFromDays(days)
  return `${pad(y, 4)}-${pad(m, 2)}-${pad(d, 2)}`
}

/** Parses `YYYY-MM-DD`; null when malformed or invalid. */
export function parseDate(text: string): number | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text)
  if (m === null) return null
  const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])]
  return isValid(y, mo, d) ? daysFromCivil(y, mo, d) : null
}

export function formatDatetime(seconds: number): string {
  const days = intdiv(seconds - (((seconds % 86400) + 86400) % 86400), 86400)
  const rest = seconds - days * 86400
  return `${formatDate(days)}T${formatTime(rest)}Z`
}

/** Parses `YYYY-MM-DDTHH:MM:SSZ`. */
export function parseDatetime(text: string): number | null {
  const m = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})Z$/.exec(text)
  if (m === null) return null
  const days = parseDate(m[1] ?? '')
  const time = parseTime(m[2] ?? '')
  return days === null || time === null ? null : days * 86400 + time
}

export function formatTime(seconds: number): string {
  return `${pad(intdiv(seconds, 3600), 2)}:${pad(intdiv(seconds % 3600, 60), 2)}:${pad(seconds % 60, 2)}`
}

export function parseTime(text: string): number | null {
  const m = /^(\d{2}):(\d{2}):(\d{2})$/.exec(text)
  if (m === null) return null
  const [h, i, s] = [Number(m[1]), Number(m[2]), Number(m[3])]
  return h < 24 && i < 60 && s < 60 ? h * 3600 + i * 60 + s : null
}

const offsetFormats = new Map<string, Intl.DateTimeFormat>()

/**
 * UTC offset in seconds of an IANA time zone at an instant (the same tz
 * database rules PHP's DateTimeZone applies). Throws RangeError for an
 * unknown zone.
 */
export function timezoneOffset(epochSeconds: number, timezone: string): number {
  let format = offsetFormats.get(timezone)
  if (format === undefined) {
    format = new Intl.DateTimeFormat('en-US', { timeZone: timezone, timeZoneName: 'longOffset' })
    offsetFormats.set(timezone, format)
  }
  const name = format.formatToParts(new Date(epochSeconds * 1000)).find((p) => p.type === 'timeZoneName')?.value ?? 'GMT'
  const m = /^GMT(?:([+-])(\d{2}):(\d{2})(?::(\d{2}))?)?$/.exec(name)
  if (m === null) throw new RangeError(`Unsupported offset ${name}`)
  if (m[1] === undefined) return 0
  const seconds = Number(m[2]) * 3600 + Number(m[3]) * 60 + Number(m[4] ?? 0)
  return m[1] === '-' ? -seconds : seconds
}

/** The local calendar day (day number) of an instant in an IANA time zone. */
export function localDay(epochSeconds: number, timezone: string): number {
  const local = epochSeconds + timezoneOffset(epochSeconds, timezone)
  return Math.floor(local / 86400)
}
