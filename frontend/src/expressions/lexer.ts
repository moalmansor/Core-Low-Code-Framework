import { StaticError } from './staticError'
import { codePoints, nfc } from './unicode'

/**
 * Tokenizer for the text syntax of expression-language.md §2. Twin of
 * backend/app/Expressions/Parsing/Lexer.php; positions are code-point offsets.
 */

export type TokenType = 'number' | 'string' | 'date' | 'datetime' | 'time' | 'ident' | 'keyword' | 'scope' | 'op' | 'punct' | 'eof'

export interface Token {
  type: TokenType
  value: string
  pos: number
  end: number
}

export const KEYWORDS = ['and', 'or', 'not', 'true', 'false', 'null']
export const SCOPES = ['user', 'record', 'old', 'row', 'parent', 'context']

const OPERATORS = ['!=', '<=', '>=', '=', '<', '>', '&', '+', '-', '*', '/', '%']
const WHITESPACE = [' ', '\t', '\r', '\n']
const PUNCTUATION = ['(', ')', '[', ']', ',', '.']
const LITERAL_PREFIXES: Record<string, TokenType> = { d: 'date', dt: 'datetime', t: 'time' }

function isDigit(c: string | undefined): c is string {
  return c !== undefined && c.length === 1 && c >= '0' && c <= '9'
}

/** ASCII letter (PHP ctype_alpha in the C locale). */
export function isLetter(c: string | undefined): c is string {
  return c !== undefined && c.length === 1 && ((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z'))
}

function isWordChar(c: string | undefined): c is string {
  return isLetter(c) || isDigit(c) || c === '_'
}

export class Lexer {
  private readonly chars: string[]
  private pos = 0

  constructor(source: string) {
    this.chars = codePoints(source)
  }

  tokenize(): Token[] {
    const tokens: Token[] = []
    const n = this.chars.length
    for (;;) {
      while (this.pos < n && WHITESPACE.includes(this.chars[this.pos]!)) this.pos++
      if (this.pos >= n) {
        tokens.push({ type: 'eof', value: '', pos: this.pos, end: this.pos })
        return tokens
      }
      const start = this.pos
      const c = this.chars[this.pos]!

      if (isDigit(c)) {
        tokens.push(this.number(start))
      } else if (c === '"') {
        const value = this.string()
        tokens.push({ type: 'string', value, pos: start, end: this.pos })
      } else if (c === '@') {
        this.pos++
        const word = this.word()
        if (!SCOPES.includes(word)) throw new StaticError('SYNTAX', `Unknown scope '@${word}'`, start)
        tokens.push({ type: 'scope', value: word, pos: start, end: this.pos })
      } else if (isLetter(c)) {
        const word = this.word()
        const literalType = Object.prototype.hasOwnProperty.call(LITERAL_PREFIXES, word) ? LITERAL_PREFIXES[word] : undefined
        if (this.chars[this.pos] === '"' && literalType !== undefined) {
          this.pos++
          const body = this.untilQuote()
          tokens.push({ type: literalType, value: body, pos: start, end: this.pos })
        } else if (KEYWORDS.includes(word)) {
          tokens.push({ type: 'keyword', value: word, pos: start, end: this.pos })
        } else {
          tokens.push({ type: 'ident', value: word, pos: start, end: this.pos })
        }
      } else if (PUNCTUATION.includes(c)) {
        this.pos++
        tokens.push({ type: 'punct', value: c, pos: start, end: this.pos })
      } else {
        const two = c + (this.chars[this.pos + 1] ?? '')
        const op = OPERATORS.includes(two) ? two : OPERATORS.includes(c) ? c : null
        if (op === null) throw new StaticError('SYNTAX', `Unexpected character '${c}'`, start)
        this.pos += op.length
        tokens.push({ type: 'op', value: op, pos: start, end: this.pos })
      }
    }
  }

  private word(): string {
    let word = ''
    while (isWordChar(this.chars[this.pos])) {
      word += this.chars[this.pos]
      this.pos++
    }
    return word
  }

  private number(start: number): Token {
    let text = ''
    while (isDigit(this.chars[this.pos])) {
      text += this.chars[this.pos]
      this.pos++
    }
    if (this.chars[this.pos] === '.' && isDigit(this.chars[this.pos + 1])) {
      text += '.'
      this.pos++
      while (isDigit(this.chars[this.pos])) {
        text += this.chars[this.pos]
        this.pos++
      }
    }
    const c = this.chars[this.pos]
    if (isLetter(c) || c === '_') throw new StaticError('SYNTAX', 'A number cannot be followed by a letter', this.pos)
    return { type: 'number', value: text, pos: start, end: this.pos }
  }

  private string(): string {
    this.pos++ // opening quote
    let out = ''
    for (;;) {
      const c = this.chars[this.pos]
      if (c === undefined) throw new StaticError('SYNTAX', 'Unterminated string', this.pos)
      this.pos++
      if (c === '"') return nfc(out)
      if (c !== '\\') {
        out += c
        continue
      }
      const e = this.chars[this.pos]
      this.pos++
      if (e === '"' || e === '\\') {
        out += e
      } else if (e === 'n') {
        out += '\n'
      } else if (e === 't') {
        out += '\t'
      } else if (e === 'u') {
        const hex = this.chars.slice(this.pos, this.pos + 4).join('')
        if (!/^[0-9A-Fa-f]{4}$/.test(hex)) throw new StaticError('SYNTAX', 'Invalid \\u escape', this.pos)
        this.pos += 4
        const cp = Number.parseInt(hex, 16)
        if (cp >= 0xd800 && cp <= 0xdfff) throw new StaticError('SYNTAX', 'Surrogate code points are not allowed', this.pos)
        out += String.fromCodePoint(cp)
      } else {
        throw new StaticError('SYNTAX', 'Invalid escape', this.pos)
      }
    }
  }

  private untilQuote(): string {
    let out = ''
    for (;;) {
      const c = this.chars[this.pos]
      if (c === '"') break
      if (c === undefined) throw new StaticError('SYNTAX', 'Unterminated literal', this.pos)
      out += c
      this.pos++
    }
    this.pos++
    return out
  }
}
