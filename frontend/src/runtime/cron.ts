/**
 * A schedule's cron expression read as words where it has a common shape
 * (every hour, every day, a weekday or a day of the month at a time), so a
 * list of scheduled tasks reads as sentences. Any other expression returns
 * null and is shown as written.
 */
export type CronText = { kind: 'hourly'; minute: number } | { kind: 'daily'; time: string } | { kind: 'weekly'; weekday: number; time: string } | { kind: 'monthly'; day: number; time: string }

const NUM = /^\d{1,2}$/

export function parseCron(cron: string): CronText | null {
  const parts = cron.trim().split(/\s+/)
  if (parts.length !== 5) return null
  const [m, h, dom, mon, dow] = parts
  if (!NUM.test(m) || +m > 59 || mon !== '*') return null
  if (h === '*' && dom === '*' && dow === '*') return { kind: 'hourly', minute: +m }
  if (!NUM.test(h) || +h > 23) return null
  const time = `${h.padStart(2, '0')}:${m.padStart(2, '0')}`
  if (dom === '*' && dow === '*') return { kind: 'daily', time }
  if (dom === '*' && /^[0-7]$/.test(dow)) return { kind: 'weekly', weekday: +dow % 7, time }
  if (dow === '*' && NUM.test(dom) && +dom >= 1 && +dom <= 31) return { kind: 'monthly', day: +dom, time }
  return null
}

/** The name of a weekday (0 = Sunday) in a language. */
export function weekdayName(day: number, locale: string): string {
  // 2023-01-01 was a Sunday.
  return new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(2023, 0, 1 + day)))
}
