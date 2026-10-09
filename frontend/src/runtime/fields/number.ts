import { asciiDigits } from '@/expressions/unicode'

/**
 * Normalizes typed number text: Arabic-Indic and other digits become ASCII,
 * the Arabic decimal separator becomes `.`, group separators are dropped.
 * Returns null when the text cannot be (part of) a number. With `final`, a
 * trailing `.` or lone `-` is removed and leading zeros are trimmed.
 */
export function normalizeNumber(raw: string, final: boolean): string | null {
  let s = asciiDigits(raw.trim())
    .replace(/[,٬\s]/g, '')
    .replace(/٫/g, '.')
  if (s === '') return ''
  if (!/^-?\d*(\.\d*)?$/.test(s)) return null
  if (final) {
    if (s === '-' || s === '.' || s === '-.') return ''
    s = s.replace(/\.$/, '')
    s = s.replace(/^(-?)0+(?=\d)/, '$1')
    if (s.startsWith('.')) s = `0${s}`
    if (s.startsWith('-.')) s = `-0${s.slice(1)}`
  }
  return s
}
