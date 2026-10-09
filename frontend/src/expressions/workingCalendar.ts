import { civilFromDays, parseDate, weekday } from './civil'
import { fromDays } from './ummAlQura'

/** The JSON shape of a business calendar in the runtime and the corpus. */
export interface WorkingCalendarData {
  working_days?: (number | string)[]
  holidays?: string[]
  yearly_gregorian?: string[]
  yearly_hijri?: string[]
}

/** PHP intval of a decimal string: leading integer, 0 when none. */
function intval(value: number | string): number {
  if (typeof value === 'number') return Math.trunc(value)
  const n = Number.parseInt(value.trim(), 10)
  return Number.isNaN(n) ? 0 : n
}

function pairs(list: string[]): [number, number][] {
  return list.map((md) => {
    const [m = '', d = ''] = md.split('-')
    return [intval(m), intval(d)]
  })
}

/**
 * The business calendar in an evaluation context (expression-language.md §9.4:
 * form → department → default calendar): working weekdays, explicit holiday
 * dates, and yearly recurring holidays in the Gregorian or Hijri calendar.
 * Twin of backend/app/Expressions/Calendars/WorkingCalendar.php.
 */
export class WorkingCalendar {
  private readonly workingDays: ReadonlySet<number>
  private readonly holidays: ReadonlySet<number>
  private readonly yearlyGregorian: readonly [number, number][]
  private readonly yearlyHijri: readonly [number, number][]

  /**
   * @param workingDays weekdays 0 = Sunday … 6 = Saturday
   * @param holidays day numbers
   * @param yearlyGregorian [month, day]
   * @param yearlyHijri [hijri month, hijri day]
   */
  constructor(workingDays: number[], holidays: number[] = [], yearlyGregorian: [number, number][] = [], yearlyHijri: [number, number][] = []) {
    this.workingDays = new Set(workingDays)
    this.holidays = new Set(holidays)
    this.yearlyGregorian = yearlyGregorian
    this.yearlyHijri = yearlyHijri
  }

  /** Sunday–Thursday with no holidays: used when no calendar is configured. */
  static default(): WorkingCalendar {
    return new WorkingCalendar([0, 1, 2, 3, 4])
  }

  /** From {working_days:[…], holidays:["YYYY-MM-DD"…], yearly_gregorian:["MM-DD"…], yearly_hijri:["MM-DD"…]}. */
  static fromData(data: WorkingCalendarData): WorkingCalendar {
    return new WorkingCalendar(
      (data.working_days ?? [0, 1, 2, 3, 4]).map(intval),
      (data.holidays ?? []).map(parseDate).filter((d): d is number => d !== null),
      pairs(data.yearly_gregorian ?? []),
      pairs(data.yearly_hijri ?? []),
    )
  }

  isWorkingDay(days: number): boolean {
    if (!this.workingDays.has(weekday(days)) || this.holidays.has(days)) return false
    if (this.yearlyGregorian.length > 0) {
      const [, m, d] = civilFromDays(days)
      if (this.yearlyGregorian.some(([hm, hd]) => hm === m && hd === d)) return false
    }
    if (this.yearlyHijri.length > 0) {
      const hijri = fromDays(days)
      if (hijri !== null && this.yearlyHijri.some(([hm, hd]) => hm === hijri[1] && hd === hijri[2])) return false
    }
    return true
  }

  hasWorkingDays(): boolean {
    return this.workingDays.size > 0
  }
}
