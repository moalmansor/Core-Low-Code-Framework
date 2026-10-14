/**
 * Placeholder formats (specification §4.17), the browser's side of
 * `ValueFormat` on the server: `{{ due | date:long }}`,
 * `{{ amount | currency:SAR | digits:arabic }}`. The list is fixed; nothing
 * here is evaluated.
 */
export type DateStyle = 'short' | 'medium' | 'long' | 'iso'
export interface Format {
  date?: DateStyle
  datetime?: DateStyle
  number?: number
  currency?: string
  digits?: 'arabic' | 'western'
}

const DATE_STYLES: DateStyle[] = ['short', 'medium', 'long', 'iso']

/** What kind of value a field holds, as far as formatting goes. */
export function valueKind(storage: string | null | undefined): 'date' | 'datetime' | 'number' | 'text' {
  if (storage === 'date') return 'date'
  if (storage === 'datetime') return 'datetime'
  if (storage === 'int' || storage === 'decimal' || storage === 'currency') return 'number'
  return 'text'
}

/** A spec (`date:long|digits:arabic`) as a format, or null when any part is unknown. */
export function parseFormat(spec: string): Format | null {
  const out: Format = {}
  for (const part of spec
    .split('|')
    .map((p) => p.trim())
    .filter(Boolean)) {
    const [name, arg = ''] = part.split(':').map((p) => p.trim()) as [string, string?]
    if ((name === 'date' || name === 'datetime') && DATE_STYLES.includes(arg as DateStyle)) out[name] = arg as DateStyle
    else if (name === 'number' && /^[0-6]$/.test(arg)) out.number = Number(arg)
    else if (name === 'currency' && /^[A-Z]{3}$/.test(arg)) out.currency = arg
    else if (name === 'digits' && (arg === 'arabic' || arg === 'western')) out.digits = arg
    else return null
  }
  return out
}

/** A format written as a spec, in the server's order. */
export function formatSpec(f: Format): string {
  const parts: string[] = []
  if (f.date) parts.push(`date:${f.date}`)
  if (f.datetime) parts.push(`datetime:${f.datetime}`)
  if (f.number !== undefined && f.number !== null) parts.push(`number:${f.number}`)
  if (f.currency) parts.push(`currency:${f.currency}`)
  if (f.digits) parts.push(`digits:${f.digits}`)
  return parts.join('|')
}

/** A path with its format as a field list entry: `amount|currency:SAR`. */
export function entry(path: string, f: Format): string {
  const spec = formatSpec(f)
  return spec ? `${path}|${spec}` : path
}

/** A field list entry split into its path and format. */
export function splitEntry(value: string): { path: string; format: Format } {
  const at = value.indexOf('|')
  if (at < 0) return { path: value.trim(), format: {} }
  return { path: value.slice(0, at).trim(), format: parseFormat(value.slice(at + 1)) ?? {} }
}

/** A placeholder as written in a text: `{{ path }}` or `{{ path | format }}`. */
export function token(path: string, f: Format = {}): string {
  const spec = formatSpec(f)
  return spec ? `{{ ${path} | ${spec.split('|').join(' | ')} }}` : `{{ ${path} }}`
}
