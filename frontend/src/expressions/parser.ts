import type { AstNode, BinaryOp, Scope } from './ast'
import { parseDate, parseDatetime, parseTime } from './civil'
import { Decimal } from './decimal'
import { Lexer, type Token } from './lexer'
import { StaticError } from './staticError'

const COMPARISON = ['=', '!=', '<', '<=', '>', '>=']

/**
 * Recursive-descent parser for expression-language.md §2, producing the JSON
 * AST of §6. Twin of backend/app/Expressions/Parsing/Parser.php (the
 * reference parser): both produce the same canonical AST. Number literals are
 * canonical; text literals are NFC; `-<number literal>` is folded into a
 * negative literal.
 */
export class Parser {
  private readonly tokens: Token[]
  private i = 0

  private constructor(source: string) {
    this.tokens = new Lexer(source).tokenize()
  }

  static parse(source: string): AstNode {
    const parser = new Parser(source)
    const ast = parser.expression()
    const token = parser.peek()
    if (token.type !== 'eof') throw new StaticError('SYNTAX', `Unexpected '${token.value}'`, token.pos)
    return ast
  }

  private expression(): AstNode {
    return this.orExpr()
  }

  private orExpr(): AstNode {
    let left = this.andExpr()
    while (this.isKeyword('or')) {
      this.next()
      left = { k: 'bin', op: 'or', a: left, b: this.andExpr() }
    }
    return left
  }

  private andExpr(): AstNode {
    let left = this.notExpr()
    while (this.isKeyword('and')) {
      this.next()
      left = { k: 'bin', op: 'and', a: left, b: this.notExpr() }
    }
    return left
  }

  private notExpr(): AstNode {
    if (this.isKeyword('not')) {
      this.next()
      return { k: 'un', op: 'not', a: this.notExpr() }
    }
    return this.comparison()
  }

  private comparison(): AstNode {
    let left = this.concat()
    if (this.isOp(...COMPARISON)) {
      const op = this.next().value as BinaryOp
      left = { k: 'bin', op, a: left, b: this.concat() }
      if (this.isOp(...COMPARISON)) throw new StaticError('SYNTAX', 'Comparison operators cannot be chained', this.peek().pos)
    }
    return left
  }

  private concat(): AstNode {
    let left = this.additive()
    while (this.isOp('&')) {
      this.next()
      left = { k: 'bin', op: '&', a: left, b: this.additive() }
    }
    return left
  }

  private additive(): AstNode {
    let left = this.multiplicative()
    while (this.isOp('+', '-')) {
      const op = this.next().value as BinaryOp
      left = { k: 'bin', op, a: left, b: this.multiplicative() }
    }
    return left
  }

  private multiplicative(): AstNode {
    let left = this.unary()
    while (this.isOp('*', '/', '%')) {
      const op = this.next().value as BinaryOp
      left = { k: 'bin', op, a: left, b: this.unary() }
    }
    return left
  }

  private unary(): AstNode {
    if (this.isOp('-')) {
      this.next()
      const operand = this.unary()
      if (operand.k === 'lit' && operand.t === 'number') {
        return { k: 'lit', t: 'number', v: Decimal.of(operand.v).negate().toString() }
      }
      return { k: 'un', op: 'neg', a: operand }
    }
    return this.primary()
  }

  private primary(): AstNode {
    const token = this.peek()
    switch (token.type) {
      case 'number': {
        this.next()
        const value = Decimal.of(token.value)
        if (value.significantDigits() > Decimal.MAX_DIGITS) throw new StaticError('PRECISION', 'Number literals are limited to 34 significant digits', token.pos)
        return { k: 'lit', t: 'number', v: value.toString() }
      }
      case 'string':
        this.next()
        return { k: 'lit', t: 'text', v: token.value }
      case 'date':
        this.next()
        if (parseDate(token.value) === null) throw new StaticError('SYNTAX', 'Invalid date literal', token.pos)
        return { k: 'lit', t: 'date', v: token.value }
      case 'datetime':
        this.next()
        if (parseDatetime(token.value) === null) throw new StaticError('SYNTAX', 'Invalid datetime literal', token.pos)
        return { k: 'lit', t: 'datetime', v: token.value }
      case 'time':
        this.next()
        if (parseTime(token.value) === null) throw new StaticError('SYNTAX', 'Invalid time literal', token.pos)
        return { k: 'lit', t: 'time', v: token.value }
      case 'keyword':
        this.next()
        switch (token.value) {
          case 'true':
            return { k: 'lit', t: 'boolean', v: true }
          case 'false':
            return { k: 'lit', t: 'boolean', v: false }
          case 'null':
            return { k: 'lit', t: 'null' }
          default:
            throw new StaticError('SYNTAX', `Unexpected keyword '${token.value}'`, token.pos)
        }
      case 'scope':
        this.next()
        if (this.isPunct('.')) this.next()
        return { k: 'ref', scope: token.value as Scope, path: this.path() }
      case 'ident': {
        const after = this.tokens[this.i + 1]!
        if (after.type === 'punct' && after.value === '(' && after.pos === token.end) return this.call()
        return { k: 'ref', scope: 'record', path: this.path() }
      }
      case 'punct':
        if (token.value === '(') {
          this.next()
          const inner = this.expression()
          this.expectPunct(')')
          return inner
        }
        if (token.value === '[') {
          this.next()
          const items: AstNode[] = []
          if (!this.isPunct(']')) {
            do {
              items.push(this.expression())
            } while (this.acceptPunct(','))
          }
          this.expectPunct(']')
          return { k: 'list', items }
        }
        break
    }
    throw new StaticError('SYNTAX', token.type === 'eof' ? 'Unexpected end of expression' : `Unexpected '${token.value}'`, token.pos)
  }

  private call(): AstNode {
    const name = this.next().value
    this.expectPunct('(')
    const args: AstNode[] = []
    if (!this.isPunct(')')) {
      do {
        args.push(this.expression())
      } while (this.acceptPunct(','))
    }
    this.expectPunct(')')
    return { k: 'call', fn: name, args }
  }

  private path(): string[] {
    const segments: string[] = []
    do {
      const token = this.peek()
      if (token.type !== 'ident') throw new StaticError('SYNTAX', 'Expected a field or relation key', token.pos)
      segments.push(this.next().value)
    } while (this.acceptPunct('.'))
    return segments
  }

  private peek(): Token {
    return this.tokens[this.i]!
  }

  private next(): Token {
    return this.tokens[this.i++]!
  }

  private isKeyword(word: string): boolean {
    const t = this.peek()
    return t.type === 'keyword' && t.value === word
  }

  private isOp(...ops: string[]): boolean {
    const t = this.peek()
    return t.type === 'op' && ops.includes(t.value)
  }

  private isPunct(value: string): boolean {
    const t = this.peek()
    return t.type === 'punct' && t.value === value
  }

  private acceptPunct(value: string): boolean {
    if (this.isPunct(value)) {
      this.next()
      return true
    }
    return false
  }

  private expectPunct(value: string): void {
    if (!this.acceptPunct(value)) throw new StaticError('SYNTAX', `Expected '${value}'`, this.peek().pos)
  }
}

/** Parses expression text to the stored AST; throws StaticError. */
export function parse(source: string): AstNode {
  return Parser.parse(source)
}
