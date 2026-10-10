import type { FormIndex } from './formIndex'
import { labelOf } from './i18nText'
import type { ClientField, FileMeta, References } from './types'

/**
 * Read-only text of API values for tables, view mode, the conflict screen
 * and history: option labels, referenced record titles, file names, dates in
 * the reader's calendar, and numbers with the field's formatting.
 */

export interface FormatOptions {
  locale: string
  references?: References
  files?: Record<string, FileMeta>
  yes: string
  no: string
  /** Shown for a linked record without a title (never its identifier). */
  untitled?: string
}

const ARABIC_INDIC = '٠١٢٣٤٥٦٧٨٩'

export function toArabicIndic(text: string): string {
  return text.replace(/[0-9]/g, (d) => ARABIC_INDIC[Number(d)]!)
}

/** Groups the integer digits of a canonical decimal string without losing precision. */
export function formatDecimal(value: string, opts: { decimals?: number | null; thousands?: boolean; locale: string }): string {
  const negative = value.startsWith('-')
  let [int = '0', frac = ''] = value.replace(/^-/, '').split('.')
  if (opts.decimals !== null && opts.decimals !== undefined) {
    if (frac.length > opts.decimals) {
      // Round half up on the digit string.
      const digits = (int + frac.slice(0, opts.decimals)).split('').map(Number)
      if (Number(frac[opts.decimals]) >= 5) {
        let i = digits.length - 1
        while (i >= 0) {
          if (digits[i]! < 9) {
            digits[i]! += 1
            break
          }
          digits[i] = 0
          i--
        }
        if (i < 0) digits.unshift(1)
      }
      const joined = digits.join('')
      int = joined.slice(0, joined.length - opts.decimals) || '0'
      frac = joined.slice(joined.length - opts.decimals)
    } else {
      frac = frac.padEnd(opts.decimals, '0')
    }
  }
  const parts = new Intl.NumberFormat(opts.locale).formatToParts(1234.5)
  const group = parts.find((p) => p.type === 'group')?.value ?? ','
  const decimal = parts.find((p) => p.type === 'decimal')?.value ?? '.'
  if (opts.thousands !== false) int = int.replace(/\B(?=(\d{3})+(?!\d))/g, group)
  return (negative ? '-' : '') + int + (frac ? decimal + frac : '')
}

function calendarFor(field: ClientField | null): string | null {
  const c = field?.behavior.date?.calendar
  return c === 'hijri' || c === 'dual' ? c : null
}

export function formatDate(iso: string, locale: string, field: ClientField | null = null): string {
  const d = new Date(`${iso.slice(0, 10)}T00:00:00Z`)
  if (Number.isNaN(d.getTime())) return iso
  const opts: Intl.DateTimeFormatOptions = { timeZone: 'UTC', year: 'numeric', month: 'short', day: 'numeric' }
  const gregorian = new Intl.DateTimeFormat(`${locale}-u-ca-gregory`, opts).format(d)
  const cal = calendarFor(field)
  if (cal === null) return gregorian
  const hijri = new Intl.DateTimeFormat(`${locale}-u-ca-islamic-umalqura`, opts).format(d)
  return cal === 'hijri' ? hijri : `${gregorian} (${hijri})`
}

export function formatDatetime(iso: string, locale: string, field: ClientField | null = null): string {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  const tz = field?.behavior.date?.timezone
  const timeZone = tz === 'utc' ? 'UTC' : tz?.startsWith('fixed:') ? tz.slice(6) : undefined
  const hour12 = field?.behavior.date?.hourCycle ? field.behavior.date.hourCycle === '12h' : undefined
  try {
    return new Intl.DateTimeFormat(`${locale}-u-ca-${calendarFor(field) === 'hijri' ? 'islamic-umalqura' : 'gregory'}`, { dateStyle: 'medium', timeStyle: 'short', timeZone, hour12 }).format(d)
  } catch {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(d)
  }
}

export function formatTime(value: string, field: ClientField | null = null): string {
  const [h = '00', m = '00'] = value.split(':')
  if (field?.behavior.date?.hourCycle === '12h') {
    const hour = Number(h)
    return `${((hour + 11) % 12) + 1}:${m} ${hour < 12 ? 'AM' : 'PM'}`
  }
  return `${h}:${m}`
}

export function formatDuration(seconds: string | number): string {
  const total = Math.abs(Math.trunc(Number(seconds)))
  if (!Number.isFinite(total)) return String(seconds)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  return `${Number(seconds) < 0 ? '-' : ''}${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

function optionLabel(field: ClientField, value: string, locale: string): string {
  const o = (field.options?.static ?? []).find((x) => x.value === value)
  return o ? labelOf(o.i18n?.label, locale, o.value) : value
}

export function formatValue(index: FormIndex | null, field: ClientField, value: unknown, opts: FormatOptions): string {
  if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) return ''
  const storage = index?.storage(field) ?? 'string'
  // A linked record or file without a title or name never shows its identifier.
  const refTitle = (uuid: unknown) => (typeof uuid === 'string' ? (opts.references?.[field.key]?.[uuid] ?? opts.untitled ?? '—') : String(uuid))
  const fileName = (uuid: unknown) => (typeof uuid === 'string' ? (opts.files?.[uuid]?.name ?? '—') : String(uuid))
  const digits = (s: string) => (field.behavior.digits === 'arabic_indic' || (field.behavior.digits === 'locale' && opts.locale === 'ar') ? toArabicIndic(s) : s)
  const sep = opts.locale === 'ar' ? '، ' : ', '
  if (index?.isReference(field)) return (Array.isArray(value) ? value : [value]).map(refTitle).join(sep)
  const number = (s: string) => digits(formatDecimal(s, { decimals: field.behavior.number?.decimals, thousands: field.behavior.number?.thousandSeparator ?? true, locale: opts.locale }))
  switch (storage) {
    case 'bool':
    case 'consent':
      return value ? opts.yes : opts.no
    case 'choice':
      return optionLabel(field, String(value), opts.locale)
    case 'multi_choice':
      return (Array.isArray(value) ? value : [value]).map((v) => optionLabel(field, String(v), opts.locale)).join(sep)
    case 'file':
    case 'files':
      return (Array.isArray(value) ? value : [value]).map(fileName).join(sep)
    case 'date':
      if (field.type === 'month') return new Intl.DateTimeFormat(opts.locale, { timeZone: 'UTC', year: 'numeric', month: 'long' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00Z`))
      return digits(formatDate(String(value), opts.locale, field))
    case 'datetime':
      return digits(formatDatetime(String(value), opts.locale, field))
    case 'time':
      return digits(formatTime(String(value), field))
    case 'range_date':
    case 'range_datetime':
    case 'range_time': {
      const r = value as { from?: string | null; to?: string | null }
      const one = (x: string | null | undefined) =>
        !x ? '…' : storage === 'range_date' ? formatDate(x, opts.locale, field) : storage === 'range_datetime' ? formatDatetime(x, opts.locale, field) : formatTime(x, field)
      return digits(`${one(r.from)} – ${one(r.to)}`)
    }
    case 'duration':
      return digits(formatDuration(String(value)))
    case 'currency': {
      const amount = typeof value === 'object' && value !== null ? String((value as { amount?: unknown }).amount ?? '') : String(value)
      const code = (typeof value === 'object' && value !== null ? (value as { currency?: string | null }).currency : null) ?? field.behavior.number?.currency ?? ''
      const text = number(amount)
      if (!code) return text
      return field.behavior.number?.symbolPosition === 'before' ? `${code} ${text}` : `${text} ${code}`
    }
    case 'decimal':
    case 'number':
    case 'int':
      return field.type === 'percentage' ? `${number(String(value))}%` : field.type === 'rating' ? digits(String(value)) : number(String(value))
    case 'map': {
      const m = value as { lat?: string; lng?: string; label?: string | null }
      return `${m.label ? `${m.label} ` : ''}(${m.lat ?? ''}, ${m.lng ?? ''})`
    }
    case 'phone': {
      const p = value as { number?: string; country?: string | null }
      return `${p.number ?? ''}${p.country ? ` (${p.country})` : ''}`
    }
    case 'json':
      if (field.type === 'key_value' && typeof value === 'object' && value !== null && !Array.isArray(value)) {
        return Object.entries(value as Record<string, unknown>)
          .map(([k, v]) => `${k}: ${v ?? ''}`)
          .join(sep)
      }
      return JSON.stringify(value)
    case 'formula':
      return typeof value === 'boolean' ? (value ? opts.yes : opts.no) : /^-?\d+(\.\d+)?$/.test(String(value)) ? number(String(value)) : String(value)
    default:
      if (typeof value === 'object') return JSON.stringify(value)
      return String(value)
  }
}

/** A generic value of unknown field (history and conflict entries of removed fields). */
export function formatLoose(value: unknown): string {
  if (value === null || value === undefined) return ''
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}
