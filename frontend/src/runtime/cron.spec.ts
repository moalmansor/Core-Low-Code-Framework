import { describe, expect, it } from 'vitest'
import { parseCron, weekdayName } from './cron'

describe('cron as words', () => {
  it('reads the common shapes', () => {
    expect(parseCron('5 * * * *')).toEqual({ kind: 'hourly', minute: 5 })
    expect(parseCron('0 7 * * *')).toEqual({ kind: 'daily', time: '07:00' })
    expect(parseCron('30 18 * * 1')).toEqual({ kind: 'weekly', weekday: 1, time: '18:30' })
    expect(parseCron('0 6 * * 7')).toEqual({ kind: 'weekly', weekday: 0, time: '06:00' })
    expect(parseCron('15 2 1 * *')).toEqual({ kind: 'monthly', day: 1, time: '02:15' })
  })

  it('leaves any other expression as written', () => {
    for (const c of ['*/5 * * * *', '0 7 * * 1-5', '0 7 1 1 *', '0 7', '0 25 * * *', '0 7 1 * 1']) expect(parseCron(c)).toBeNull()
  })

  it('names weekdays in the user language', () => {
    expect(weekdayName(0, 'en')).toBe('Sunday')
    expect(weekdayName(5, 'ar')).toBe('الجمعة')
  })
})
