import table from '../../../backend/resources/calendars/umm-al-qura.json'
import { parseDate } from './civil'

/**
 * Umm al-Qura Hijri calendar, table-driven for 1300–1600 AH. Reads the same
 * file as backend/app/Expressions/Calendars/UmmAlQura.php
 * (backend/resources/calendars/umm-al-qura.json, generated from ICU), so the
 * two runtimes cannot drift (ADR-0027 §8).
 */

interface HijriYear {
  start: number
  months: number[]
}

interface CalendarTable {
  firstYear: number
  years: { start: string; months: string }[]
}

let years: HijriYear[] | null = null
let firstYear = 1300

function loadYears(): HijriYear[] {
  if (years !== null) return years
  const data: CalendarTable = table
  firstYear = data.firstYear
  years = data.years.map((year) => ({
    start: parseDate(year.start) ?? 0,
    months: Array.from(year.months, (c) => (c === '9' ? 29 : 30)),
  }))
  return years
}

function sum(values: number[]): number {
  return values.reduce((a, b) => a + b, 0)
}

/** [year, month, day], or null outside the table. */
export function fromDays(days: number): [number, number, number] | null {
  const all = loadYears()
  const last = all.length - 1
  const end = all[last]!.start + sum(all[last]!.months)
  if (days < all[0]!.start || days >= end) return null
  // Binary search for the year whose start is the last one ≤ days.
  let lo = 0
  let hi = last
  while (lo < hi) {
    const mid = Math.trunc((lo + hi + 1) / 2)
    if (all[mid]!.start <= days) lo = mid
    else hi = mid - 1
  }
  let offset = days - all[lo]!.start
  const months = all[lo]!.months
  for (let i = 0; i < months.length; i++) {
    if (offset < months[i]!) return [firstYear + lo, i + 1, offset + 1]
    offset -= months[i]!
  }
  return null
}

/**
 * Gregorian day number of a Hijri date, or the diagnostic code
 * (INVALID_DATE outside the table, INVALID_ARG for invalid parts).
 */
export function toDays(year: number, month: number, day: number): number | 'INVALID_DATE' | 'INVALID_ARG' {
  const all = loadYears()
  const index = year - firstYear
  if (index < 0 || index >= all.length) return 'INVALID_DATE'
  const months = all[index]!.months
  if (month < 1 || month > 12 || day < 1 || day > months[month - 1]!) return 'INVALID_ARG'
  return all[index]!.start + sum(months.slice(0, month - 1)) + day - 1
}
