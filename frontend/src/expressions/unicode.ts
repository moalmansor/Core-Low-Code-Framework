/**
 * Text semantics of the expression language (§3, §9.3): NFC-normalized
 * strings measured and indexed in Unicode code points, simple case mapping,
 * White_Space trimming, and Arabic normalization. Twin of
 * backend/app/Expressions/Text/Unicode.php. JavaScript strings are UTF-16, so
 * every position and length here counts code points, never code units.
 */

export const MAX_LENGTH = 65535

/** Unicode White_Space (PropList.txt). */
const WHITE_SPACE = new Set([
  0x0009, 0x000a, 0x000b, 0x000c, 0x000d, 0x0020, 0x0085, 0x00a0, 0x1680, 0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006, 0x2007, 0x2008, 0x2009, 0x200a, 0x2028, 0x2029, 0x202f, 0x205f,
  0x3000,
])

const LONE_SURROGATE = /[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?<![\uD800-\uDBFF])[\uDC00-\uDFFF]/g

/** NFC; ill-formed UTF-16 (lone surrogates) is repaired with `?` as PHP's mb_convert_encoding does. */
export function nfc(text: string): string {
  return text.replace(LONE_SURROGATE, '?').normalize('NFC')
}

export function codePoints(text: string): string[] {
  return Array.from(text)
}

export function length(text: string): number {
  let n = 0
  for (let i = 0; i < text.length; i++) {
    const c = text.charCodeAt(i)
    if (c >= 0xd800 && c <= 0xdbff && i + 1 < text.length) {
      const next = text.charCodeAt(i + 1)
      if (next >= 0xdc00 && next <= 0xdfff) i++
    }
    n++
  }
  return n
}

/** Substring by code points (`start` ≥ 0; `count` ≥ 0 or to the end). */
export function substr(text: string, start: number, count: number | null = null): string {
  const chars = codePoints(text)
  return chars.slice(start, count === null ? undefined : start + count).join('')
}

/** Code-point (= UTF-8 byte) order, as PHP's strcmp: -1, 0 or 1. */
export function compare(a: string, b: string): number {
  if (a === b) return 0
  const x = codePoints(a)
  const y = codePoints(b)
  const n = Math.min(x.length, y.length)
  for (let i = 0; i < n; i++) {
    const ca = x[i]!.codePointAt(0)!
    const cb = y[i]!.codePointAt(0)!
    if (ca !== cb) return ca < cb ? -1 : 1
  }
  return x.length === y.length ? 0 : x.length < y.length ? -1 : 1
}

/*
 * Simple (one-to-one) case mapping, ADR-0027 §6. JavaScript only exposes the
 * full mapping, so each code point is mapped on its own and kept unchanged
 * when the full mapping is not exactly one code point; the code points whose
 * simple mapping differs from that rule are fixed below. Verified against
 * PHP's mb_convert_case(MB_CASE_UPPER_SIMPLE / MB_CASE_LOWER_SIMPLE) over
 * every code point.
 */
const SPECIAL_UPPER = new Map<number, number>([
  [0x1fb3, 0x1fbc],
  [0x1fc3, 0x1fcc],
  [0x1ff3, 0x1ffc],
])
for (const base of [0x1f80, 0x1f90, 0x1fa0]) {
  for (let i = 0; i < 8; i++) SPECIAL_UPPER.set(base + i, base + i + 8)
}
const SPECIAL_LOWER = new Map<number, number>([[0x0130, 0x0069]])
/*
 * Case pairs added in Unicode 16.0 (Latin, Cyrillic, Garay). The reference
 * runtime (PHP 8.3 mbstring, Unicode 15.1) leaves them unchanged, while newer
 * JavaScript engines map them; they are pinned to the reference so the result
 * never depends on the browser's ICU version.
 */
for (const cp of [0x019b, 0x0264, 0x1c89, 0x1c8a, 0xa7cb, 0xa7cc, 0xa7cd, 0xa7da, 0xa7db, 0xa7dc]) {
  SPECIAL_UPPER.set(cp, cp)
  SPECIAL_LOWER.set(cp, cp)
}
for (let cp = 0x10d50; cp <= 0x10d65; cp++) {
  for (const c of [cp, cp + 0x20]) {
    SPECIAL_UPPER.set(c, c)
    SPECIAL_LOWER.set(c, c)
  }
}

function mapCase(text: string, special: Map<number, number>, full: (s: string) => string): string {
  let out = ''
  for (const char of text) {
    const cp = char.codePointAt(0)!
    const fixed = special.get(cp)
    if (fixed !== undefined) {
      out += String.fromCodePoint(fixed)
      continue
    }
    const mapped = full(char)
    out += mapped.length > 0 && length(mapped) === 1 ? mapped : char
  }
  return out
}

export function upper(text: string): string {
  return mapCase(text, SPECIAL_UPPER, (s) => s.toUpperCase())
}

export function lower(text: string): string {
  return mapCase(text, SPECIAL_LOWER, (s) => s.toLowerCase())
}

export function isWhiteSpace(char: string): boolean {
  return char !== '' && WHITE_SPACE.has(char.codePointAt(0)!)
}

export function trim(text: string): string {
  const chars = codePoints(text)
  let start = 0
  let end = chars.length
  while (start < end && isWhiteSpace(chars[start]!)) start++
  while (end > start && isWhiteSpace(chars[end - 1]!)) end--
  return chars.slice(start, end).join('')
}

/** Arabic-Indic (U+0660…) and Extended Arabic-Indic (U+06F0…) digits → ASCII. */
export function asciiDigits(text: string): string {
  return text.replace(/[\u0660-\u0669\u06F0-\u06F9]/g, (c) => {
    const cp = c.charCodeAt(0)
    return String(cp - (cp >= 0x06f0 ? 0x06f0 : 0x0660))
  })
}

/**
 * Removes tashkeel (U+064B–U+065F, U+0670) and tatweel (U+0640); folds
 * أ إ آ → ا, ى → ي, ة → ه; converts Arabic-Indic digits to ASCII.
 */
export function normalizeArabic(text: string): string {
  let out = ''
  for (const char of asciiDigits(text)) {
    const cp = char.codePointAt(0)!
    if ((cp >= 0x064b && cp <= 0x065f) || cp === 0x0670 || cp === 0x0640) continue
    if (cp === 0x0623 || cp === 0x0625 || cp === 0x0622) out += '\u0627'
    else if (cp === 0x0649) out += '\u064A'
    else if (cp === 0x0629) out += '\u0647'
    else out += char
  }
  return out
}
