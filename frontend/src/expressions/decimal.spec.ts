import { describe, expect, it } from 'vitest'
import { Decimal, type ArithmeticError } from './decimal'

/* Expected values were produced by the PHP reference (App\Expressions\Numbers\Decimal). */

const d = (value: string | number): Decimal => Decimal.of(value)
const text = (result: Decimal | ArithmeticError): string => (typeof result === 'string' ? result : result.toString())
const nines = '9'.repeat(34)

describe('Decimal', () => {
  it('prints the canonical form', () => {
    expect(d('-0.000').toString()).toBe('0')
    expect(d('012.3400').toString()).toBe('12.34')
    expect(d('0.5').negate().toString()).toBe('-0.5')
    expect(Decimal.parse('1e3')).toBeNull()
    expect(Decimal.parse('+1')).toBeNull()
    expect(Decimal.parse('1.')).toBeNull()
  })

  it('divides to 16 fractional digits, rounding half away from zero', () => {
    expect(text(d(1).div(d(3)))).toBe('0.3333333333333333')
    expect(text(d(2).div(d(3)))).toBe('0.6666666666666667')
    expect(text(d(-2).div(d(3)))).toBe('-0.6666666666666667')
    expect(text(d('0.00000000000000005').div(d(1)))).toBe('0.0000000000000001')
    expect(text(d('0.00000000000000015').div(d(1)))).toBe('0.0000000000000002')
    expect(text(d(1).div(d(0)))).toBe('DIV_ZERO')
  })

  it('takes square roots to 16 fractional digits', () => {
    expect(text(d(2).sqrt())).toBe('1.414213562373095')
    expect(text(d('0.0001').sqrt())).toBe('0.01')
    expect(text(d('99999999999999999999999999999999999').sqrt())).toBe('316227766016837933.1998893544432719')
  })

  it('limits results to 34 significant digits', () => {
    expect(text(d(nines).add(d(1)))).toBe('OVERFLOW')
    expect(text(d(nines).sub(d(1)))).toBe('9999999999999999999999999999999998')
    expect(text(d(10).pow(34))).toBe('OVERFLOW')
    expect(text(d(10).pow(33))).toBe('1' + '0'.repeat(33))
    expect(text(d('1.' + '1'.repeat(40)).add(d('0.00000000000000000000000000000000005')))).toBe('1.111111111111111111111111111111111')
    expect(text(d('0.' + '0'.repeat(10) + '5'.repeat(40)).add(Decimal.zero()))).toBe('0.00000000005555555555555555555555555555555556')
  })

  it('rounds, truncates and takes remainders like the reference', () => {
    expect(text(d('-7').mod(d(3)))).toBe('-1')
    expect(text(d('7.5').mod(d(-2)))).toBe('1.5')
    expect(text(d('-2.5').round(0))).toBe('-3')
    expect(text(d('2.45').round(1))).toBe('2.5')
    expect(text(d('1250').round(-2))).toBe('1300')
    expect(text(d('-1.5').floor())).toBe('-2')
    expect(text(d('-1.5').ceil())).toBe('-1')
    expect(text(d('-1.99').trunc(1))).toBe('-1.9')
    expect(text(d('1.5').pow(3))).toBe('3.375')
  })

  it('compares numerically regardless of scale', () => {
    expect(d('1').equals(d('1.000'))).toBe(true)
    expect(d('-2').compare(d('-10'))).toBe(1)
    expect(d('0.1').compare(d('0.09'))).toBe(1)
    expect(d('123456789012345').toSmallInt()).toBe(123456789012345)
    expect(d('1234567890123456').toSmallInt()).toBeNull()
  })
})
