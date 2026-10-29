/**
 * The person's display preferences (specification §4.36, ADR-0063): time
 * zone, calendar, digits, date pattern and number separators. Set from
 * `/me` and read by every formatter, so dates and numbers read the way the
 * person chose wherever they appear. A field's own setting (a fixed time
 * zone, a Hijri date field) still wins.
 */
export interface DisplayPrefs {
  timeZone: string | null
  calendar: 'gregorian' | 'hijri' | 'both' | null
  digits: 'western' | 'arabic_indic' | null
  dateFormat: string | null
  number: { group: string | null; decimal: string } | null
}

const EMPTY: DisplayPrefs = { timeZone: null, calendar: null, digits: null, dateFormat: null, number: null }
let current: DisplayPrefs = { ...EMPTY }

export function setDisplayPrefs(p: Partial<DisplayPrefs> | null): void {
  current = { ...EMPTY, ...(p ?? {}) }
}

export function displayPrefs(): DisplayPrefs {
  return current
}

const ARABIC_INDIC = '٠١٢٣٤٥٦٧٨٩'

/** The text with the person's digits: Arabic-Indic or Western, or as written. */
export function withDigits(text: string, digits: DisplayPrefs['digits'] = current.digits): string {
  if (digits === 'arabic_indic') return text.replace(/[0-9]/g, (d) => ARABIC_INDIC[Number(d)]!)
  if (digits === 'western') return text.replace(/[٠-٩]/g, (d) => String(ARABIC_INDIC.indexOf(d)))
  return text
}

/**
 * A Gregorian date in a pattern such as `dd/MM/yyyy` or `yyyy-MM-dd HH:mm`
 * (the letters y, M, d, H, h, m, s, a). Other characters are kept.
 */
export function formatPattern(date: Date, pattern: string, timeZone: string): string {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-u-ca-gregory', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' })
      .formatToParts(date)
      .map((p) => [p.type, p.value]),
  ) as Record<string, string>
  const hour = Number(parts.hour ?? '0')
  const tokens: Record<string, string> = {
    yyyy: parts.year ?? '',
    yy: (parts.year ?? '').slice(-2),
    MM: parts.month ?? '',
    M: String(Number(parts.month ?? '0')),
    dd: parts.day ?? '',
    d: String(Number(parts.day ?? '0')),
    HH: parts.hour ?? '',
    H: String(hour),
    hh: String(((hour + 11) % 12) + 1).padStart(2, '0'),
    h: String(((hour + 11) % 12) + 1),
    mm: parts.minute ?? '',
    ss: parts.second ?? '',
    a: hour < 12 ? 'AM' : 'PM',
  }
  return pattern.replace(/yyyy|yy|MM|M|dd|d|HH|H|hh|h|mm|ss|a/g, (t) => tokens[t] ?? t)
}
