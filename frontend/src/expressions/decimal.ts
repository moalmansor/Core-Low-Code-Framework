/**
 * Exact decimal number of the expression language (expression-language.md §3,
 * §4.1): value = sign × magnitude / 10^scale. Results are limited to 34
 * significant digits: more than 34 integer digits is an overflow, extra
 * fractional digits are rounded half away from zero. Division rounds to 16
 * fractional digits. Twin of backend/app/Expressions/Numbers/Decimal.php: the
 * same algorithm on native BigInt, so both runtimes return identical
 * canonical strings (ADR-0027 §1).
 */

/** Diagnostic codes an arithmetic operation can produce. */
export type ArithmeticError = 'DIV_ZERO' | 'OVERFLOW' | 'INVALID_ARG'

type Sign = -1 | 0 | 1
type RoundingMode = 'half_away' | 'trunc' | 'floor' | 'ceil'

const DECIMAL_PATTERN = /^(-?)(\d+)(?:\.(\d+))?$/

function pow10(n: number): bigint {
  return 10n ** BigInt(n)
}

function digitCount(n: bigint): number {
  return n.toString().length
}

/** Floor of the square root (Newton iteration from a power of ten above the root). */
function isqrt(n: bigint): bigint {
  if (n === 0n) return 0n
  let x = pow10(Math.trunc((digitCount(n) + 1) / 2))
  for (;;) {
    const y = (x + n / x) / 2n
    if (y >= x) return x
    x = y
  }
}

export class Decimal {
  static readonly MAX_DIGITS = 34
  static readonly DIV_SCALE = 16

  readonly sign: Sign
  /** Absolute value of the unscaled integer. */
  readonly magnitude: bigint
  /** Number of fractional digits, ≥ 0. */
  readonly scale: number

  private constructor(sign: Sign, magnitude: bigint, scale: number) {
    this.sign = sign
    this.magnitude = magnitude
    this.scale = scale
  }

  static zero(): Decimal {
    return new Decimal(0, 0n, 0)
  }

  /** From an integer or a decimal string; throws on anything else. */
  static of(value: number | bigint | string): Decimal {
    const text = typeof value === 'number' ? (Number.isSafeInteger(value) ? String(value) : '') : String(value)
    const parsed = Decimal.parse(text)
    if (parsed === null) throw new Error(`Not a decimal: ${String(value)}`)
    return parsed
  }

  /** Parses `-?digits(.digits)?`; returns null for anything else. */
  static parse(text: string): Decimal | null {
    const m = DECIMAL_PATTERN.exec(text)
    if (m === null) return null
    const fraction = m[3] ?? ''
    return Decimal.make(m[1] === '-' ? -1 : 1, BigInt((m[2] ?? '0') + fraction), fraction.length)
  }

  private static make(sign: number, magnitude: bigint, scale: number): Decimal {
    if (magnitude === 0n) return new Decimal(0, 0n, 0)
    // Drop trailing fractional zeros (canonical form).
    while (scale > 0 && magnitude % 10n === 0n) {
      magnitude /= 10n
      scale--
    }
    return new Decimal(sign < 0 ? -1 : 1, magnitude, scale)
  }

  /** Canonical text: no exponent, no trailing fractional zeros, `0` for zero. */
  toString(): string {
    if (this.sign === 0) return '0'
    let digits = this.magnitude.toString()
    if (this.scale > 0) {
      digits = digits.padStart(this.scale + 1, '0')
      digits = digits.slice(0, -this.scale) + '.' + digits.slice(-this.scale)
    }
    return (this.sign < 0 ? '-' : '') + digits
  }

  /** Significant digits of the canonical form (leading zeros excluded). */
  significantDigits(): number {
    return this.sign === 0 ? 1 : digitCount(this.magnitude)
  }

  integerDigits(): number {
    return Math.max(0, digitCount(this.magnitude) - this.scale)
  }

  isZero(): boolean {
    return this.sign === 0
  }

  isNegative(): boolean {
    return this.sign < 0
  }

  isInteger(): boolean {
    return this.scale === 0
  }

  /** The value as a JS number when it is an integer of at most 15 digits. */
  toSmallInt(): number | null {
    if (this.scale !== 0 || digitCount(this.magnitude) > 15) return null
    return this.sign * Number(this.magnitude)
  }

  negate(): Decimal {
    return new Decimal(-this.sign as Sign, this.magnitude, this.scale)
  }

  abs(): Decimal {
    return new Decimal(Math.abs(this.sign) as Sign, this.magnitude, this.scale)
  }

  compare(other: Decimal): number {
    if (this.sign !== other.sign) return this.sign < other.sign ? -1 : 1
    if (this.sign === 0) return 0
    const [a, b] = Decimal.aligned(this, other)
    const cmp = a === b ? 0 : a < b ? -1 : 1
    return this.sign > 0 ? cmp : -cmp
  }

  equals(other: Decimal): boolean {
    return this.compare(other) === 0
  }

  add(other: Decimal): Decimal | ArithmeticError {
    return Decimal.limit(Decimal.exactAdd(this, other))
  }

  sub(other: Decimal): Decimal | ArithmeticError {
    return Decimal.limit(Decimal.exactAdd(this, other.negate()))
  }

  mul(other: Decimal): Decimal | ArithmeticError {
    if (this.sign === 0 || other.sign === 0) return Decimal.zero()
    return Decimal.limit(Decimal.make(this.sign * other.sign, this.magnitude * other.magnitude, this.scale + other.scale))
  }

  /** DIV_ZERO, OVERFLOW, or the quotient rounded half away from zero to `scale` fractional digits. */
  div(other: Decimal, scale: number = Decimal.DIV_SCALE): Decimal | ArithmeticError {
    if (other.sign === 0) return 'DIV_ZERO'
    if (this.sign === 0) return Decimal.zero()
    // a/b = (ma·10^sb) / (mb·10^sa)
    const numerator = this.magnitude * pow10(other.scale + scale)
    const denominator = other.magnitude * pow10(this.scale)
    let q = numerator / denominator
    const r = numerator % denominator
    if (r * 2n >= denominator) q += 1n
    return Decimal.limit(Decimal.make(this.sign * other.sign, q, scale))
  }

  /** Remainder with the sign of the dividend. */
  mod(other: Decimal): Decimal | ArithmeticError {
    if (other.sign === 0) return 'DIV_ZERO'
    const [a, b, scale] = Decimal.alignedWithScale(this, other)
    return Decimal.limit(Decimal.make(this.sign, a % b, scale))
  }

  /** Integer power, exact; `n` 0…64. */
  pow(n: number): Decimal | ArithmeticError {
    let result = Decimal.of(1)
    for (let i = 0; i < n; i++) {
      result = Decimal.make(result.sign * this.sign, result.magnitude * this.magnitude, result.scale + this.scale)
      if (result.integerDigits() > Decimal.MAX_DIGITS) return 'OVERFLOW'
    }
    return Decimal.limit(result)
  }

  /** Square root rounded half away from zero to 16 fractional digits; the caller rejects negatives. */
  sqrt(): Decimal | ArithmeticError {
    if (this.sign === 0) return Decimal.zero()
    // floor(x · 10^34), whose integer square root is floor(√x · 10^17).
    const shift = 2 * (Decimal.DIV_SCALE + 1) - this.scale
    const n = shift >= 0 ? this.magnitude * pow10(shift) : this.magnitude / pow10(-shift)
    const root = isqrt(n)
    let q = root / 10n
    if (root % 10n >= 5n) q += 1n
    return Decimal.limit(Decimal.make(1, q, Decimal.DIV_SCALE))
  }

  /** Round half away from zero to `digits` fractional digits (may be negative). */
  round(digits: number): Decimal | ArithmeticError {
    return Decimal.limit(this.rescale(digits, 'half_away'))
  }

  trunc(digits = 0): Decimal | ArithmeticError {
    return Decimal.limit(this.rescale(digits, 'trunc'))
  }

  floor(): Decimal | ArithmeticError {
    return Decimal.limit(this.rescale(0, 'floor'))
  }

  ceil(): Decimal | ArithmeticError {
    return Decimal.limit(this.rescale(0, 'ceil'))
  }

  private rescale(digits: number, mode: RoundingMode): Decimal {
    if (this.sign === 0 || digits >= this.scale) return this
    const divisor = pow10(this.scale - digits)
    let q = this.magnitude / divisor
    const r = this.magnitude % divisor
    let up: boolean
    switch (mode) {
      case 'half_away':
        up = r * 2n >= divisor
        break
      case 'trunc':
        up = false
        break
      case 'floor':
        up = this.sign < 0 && r !== 0n
        break
      case 'ceil':
        up = this.sign > 0 && r !== 0n
        break
    }
    if (up) q += 1n
    if (digits < 0) return Decimal.make(this.sign, q * pow10(-digits), 0)
    return Decimal.make(this.sign, q, digits)
  }

  /**
   * Applies the 34-significant-digit rule: more than 34 integer digits is an
   * overflow; extra fractional digits are rounded half away from zero.
   */
  static limit(value: Decimal): Decimal | 'OVERFLOW' {
    if (value.sign === 0) return value
    const integerDigits = value.integerDigits()
    if (integerDigits > Decimal.MAX_DIGITS) return 'OVERFLOW'
    const leadingZeros = integerDigits > 0 ? 0 : value.scale - digitCount(value.magnitude)
    const allowedFraction = integerDigits > 0 ? Decimal.MAX_DIGITS - integerDigits : Decimal.MAX_DIGITS + leadingZeros
    if (value.scale > allowedFraction) {
      value = value.rescale(allowedFraction, 'half_away')
      if (value.integerDigits() > Decimal.MAX_DIGITS) return 'OVERFLOW'
    }
    return value
  }

  private static exactAdd(a: Decimal, b: Decimal): Decimal {
    if (a.sign === 0) return b
    if (b.sign === 0) return a
    const [x, y, scale] = Decimal.alignedWithScale(a, b)
    if (a.sign === b.sign) return Decimal.make(a.sign, x + y, scale)
    if (x === y) return Decimal.zero()
    return x > y ? Decimal.make(a.sign, x - y, scale) : Decimal.make(b.sign, y - x, scale)
  }

  private static aligned(a: Decimal, b: Decimal): [bigint, bigint] {
    const [x, y] = Decimal.alignedWithScale(a, b)
    return [x, y]
  }

  private static alignedWithScale(a: Decimal, b: Decimal): [bigint, bigint, number] {
    const scale = Math.max(a.scale, b.scale)
    return [a.magnitude * pow10(scale - a.scale), b.magnitude * pow10(scale - b.scale), scale]
  }
}
