import { codePoints } from './unicode'

/**
 * The regex safe subset of expression-language.md §9.3 (ADR-0027 §7). Twin of
 * backend/app/Expressions/Text/SafeRegex.php: literals, `.`, classes with
 * ranges, `\d \w \s` and escaped syntax characters, anchors, capturing and
 * non-capturing groups, alternation, and quantifiers `* + ? {m,n}` with
 * n ≤ 100. No backreferences, lookaround, lazy/possessive quantifiers, flags,
 * or Unicode properties. Both runtimes validate with the same rules before
 * executing, so they accept and reject exactly the same patterns.
 */

export const MAX_LENGTH = 256

const SYNTAX = '^$\\.*+?()[]{}|/'
const CLASS_ESCAPES = ['d', 'w', 's']

/** True when the pattern is inside the safe subset. */
export function isSafe(pattern: string): boolean {
  const chars = codePoints(pattern)
  if (chars.length > MAX_LENGTH) return false
  const n = chars.length
  let i = 0
  let depth = 0
  let canQuantify = false
  while (i < n) {
    const c = chars[i]!
    if (c === '\0') return false
    switch (c) {
      case '\\': {
        const next = chars[i + 1]
        if (next === undefined || !validEscape(next, false)) return false
        i += 2
        canQuantify = true
        break
      }
      case '[': {
        const end = classEnd(chars, i)
        if (end === null) return false
        i = end + 1
        canQuantify = true
        break
      }
      case '(':
        if (chars[i + 1] === '?') {
          if (chars[i + 2] !== ':') return false
          i += 3
        } else {
          i++
        }
        depth++
        canQuantify = false
        break
      case ')':
        if (depth === 0) return false
        depth--
        i++
        canQuantify = true
        break
      case '*':
      case '+':
      case '?':
        if (!canQuantify) return false
        i++
        if (isQuantifierStart(chars, i)) return false // lazy, possessive, or stacked quantifier
        canQuantify = false
        break
      case '{': {
        const end = braceQuantifierEnd(chars, i)
        if (end === null || !canQuantify) return false
        i = end + 1
        if (isQuantifierStart(chars, i)) return false
        canQuantify = false
        break
      }
      case '}':
      case ']':
        return false // unescaped closer is a syntax error in ECMAScript `u` mode
      case '|':
      case '^':
      case '$':
        i++
        canQuantify = false
        break
      default:
        i++
        canQuantify = true
    }
  }
  return depth === 0
}

/** Search semantics (`preg_match` / `RegExp.test`); null when the pattern is unsafe or invalid. */
export function matches(subject: string, pattern: string): boolean | null {
  if (!isSafe(pattern) || pattern.includes('\x01')) return null
  let regex: RegExp
  try {
    regex = new RegExp(portable(pattern), 'u')
  } catch {
    return null
  }
  return regex.test(subject)
}

const SHORTHAND: Record<string, string> = { d: '0-9', w: 'A-Za-z0-9_', s: ' \\t\\n\\r\\f\\v' }

/**
 * Rewrites `\d \w \s` and `.` to explicit ASCII classes, identically to
 * SafeRegex::portable() in PHP (expression-language.md §9.3, ADR-0027).
 */
export function portable(pattern: string): string {
  const chars = Array.from(pattern)
  let out = ''
  let inClass = false
  for (let i = 0; i < chars.length; i++) {
    const c = chars[i]!
    if (c === '\\' && i + 1 < chars.length) {
      const next = chars[++i]!
      const mapped = SHORTHAND[next]
      if (mapped !== undefined) out += inClass ? mapped : `[${mapped}]`
      else out += '\\' + next
      continue
    }
    if (inClass) {
      if (c === ']') inClass = false
      out += c
      continue
    }
    if (c === '[') {
      inClass = true
      out += c
      if (chars[i + 1] === '^') {
        out += '^'
        i++
      }
      if (chars[i + 1] === ']') {
        out += ']'
        i++
      }
      continue
    }
    out += c === '.' ? '[^\\n]' : c
  }
  return out
}

function validEscape(next: string, inClass: boolean): boolean {
  if (['d', 'w', 's', 'n', 't', 'r'].includes(next)) return true
  if (inClass && next === '-') return true
  return next.length === 1 && SYNTAX.includes(next)
}

function isQuantifierStart(chars: string[], i: number): boolean {
  const c = chars[i]
  return c === '*' || c === '+' || c === '?' || (c === '{' && braceQuantifierEnd(chars, i) !== null)
}

/** End index of `{m}`, `{m,}`, or `{m,n}` (n ≤ 100, m ≤ n), else null. */
function braceQuantifierEnd(chars: string[], start: number): number | null {
  let text = ''
  let i = start + 1
  for (; i < chars.length && chars[i] !== '}'; i++) text += chars[i]
  const m = chars[i] === '}' ? /^(\d{1,3})(,(\d{1,3})?)?$/.exec(text) : null
  if (m === null) return null
  const min = Number(m[1])
  const max = m[3] !== undefined ? Number(m[3]) : m[2] !== undefined ? null : min
  if (min > 100 || (max !== null && (max > 100 || max < min))) return null
  return i
}

/** End index (the `]`) of a class starting at `start`, else null. */
function classEnd(chars: string[], start: number): number | null {
  let i = start + 1
  if (chars[i] === '^') i++
  let items = 0
  while (i < chars.length) {
    const c = chars[i]!
    if (c === ']') return items > 0 ? i : null
    if (c === '[') return null
    let single: string | null
    if (c === '\\') {
      const next = chars[i + 1]
      if (next === undefined || !validEscape(next, true)) return null
      single = CLASS_ESCAPES.includes(next) ? null : escapedChar(next)
      i += 2
    } else {
      single = c
      i++
    }
    items++
    // Range a-b
    if (chars[i] === '-' && (chars[i + 1] ?? ']') !== ']') {
      if (single === null) return null
      let endChar = chars[i + 1]!
      if (endChar === '\\') {
        const next = chars[i + 2]
        if (next === undefined || CLASS_ESCAPES.includes(next) || !validEscape(next, true)) return null
        endChar = escapedChar(next)
        i += 3
      } else if (endChar === '[') {
        return null
      } else {
        i += 2
      }
      if (single.codePointAt(0)! > endChar.codePointAt(0)!) return null
    }
  }
  return null
}

function escapedChar(next: string): string {
  switch (next) {
    case 'n':
      return '\n'
    case 't':
      return '\t'
    case 'r':
      return '\r'
    default:
      return next
  }
}
