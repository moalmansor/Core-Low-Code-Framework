import { localDay } from './civil'
import type { RecordSource, Value } from './values'
import { WorkingCalendar } from './workingCalendar'

/** The acting user as seen by `@user.*` (expression-language.md §2). */
export interface ContextUser {
  id?: number | string | null
  name?: string | null
  email?: string | null
  roles?: string[]
  department?: string | null
  departments?: string[]
  attributes?: Readonly<Record<string, Value>>
  locale?: string | null
}

export interface ContextFields {
  /** Day number of the context date. */
  today: number
  /** Context instant, epoch seconds. */
  now: number
  timezone: string
  mode: string
  locale: string
  form: string | null
  user: ContextUser
  record: RecordSource | null
  old: RecordSource | null
  row: RecordSource | null
  parent: RecordSource | null
  params: Readonly<Record<string, Value>>
  calendar: WorkingCalendar | null
}

/**
 * Everything an expression may read (expression-language.md §2 reference
 * scopes, §9.4 calendar): the record, its previous values, the acting user,
 * and the evaluation context. Immutable; `withRow` derives the row scope used
 * by row-form aggregates. Twin of backend/app/Expressions/Evaluation/Context.php.
 */
export class Context implements Readonly<ContextFields> {
  readonly today: number
  readonly now: number
  readonly timezone: string
  readonly mode: string
  readonly locale: string
  readonly form: string | null
  readonly user: ContextUser
  readonly record: RecordSource | null
  readonly old: RecordSource | null
  readonly row: RecordSource | null
  readonly parent: RecordSource | null
  readonly params: Readonly<Record<string, Value>>
  readonly calendar: WorkingCalendar | null

  constructor(fields: Pick<ContextFields, 'today' | 'now'> & Partial<ContextFields>) {
    this.today = fields.today
    this.now = fields.now
    this.timezone = fields.timezone ?? 'UTC'
    this.mode = fields.mode ?? 'edit'
    this.locale = fields.locale ?? 'en'
    this.form = fields.form ?? null
    this.user = fields.user ?? {}
    this.record = fields.record ?? null
    this.old = fields.old ?? null
    this.row = fields.row ?? null
    this.parent = fields.parent ?? null
    this.params = fields.params ?? {}
    this.calendar = fields.calendar ?? null
  }

  /** The current instant; `today` is its date in `timezone` (an IANA zone). */
  static now(timezone = 'UTC'): Context {
    const now = Math.floor(Date.now() / 1000)
    return new Context({ today: localDay(now, timezone), now, timezone })
  }

  with(changes: Partial<ContextFields>): Context {
    return new Context({ ...this.fields(), ...changes })
  }

  withRow(row: RecordSource): Context {
    return this.with({ row, parent: this.record })
  }

  workingCalendar(): WorkingCalendar {
    return this.calendar ?? WorkingCalendar.default()
  }

  private fields(): ContextFields {
    const { today, now, timezone, mode, locale, form, user, record, old, row, parent, params, calendar } = this
    return { today, now, timezone, mode, locale, form, user, record, old, row, parent, params, calendar }
  }
}
