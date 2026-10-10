import { Decimal, evaluate, type Context } from '@/expressions'
import { parseDate, weekday } from '@/expressions/civil'
import { matches } from '@/expressions/safeRegex'
import { asciiDigits, length as textLength } from '@/expressions/unicode'
import type { FormIndex } from './formIndex'
import { labelOf, pickText } from './i18nText'
import { isGroupHidden, isHidden, RuleState, type RowRef } from './rules'
import type { ClientField, I18nText, Row, Values } from './types'
import { isEmpty, ValuesRecord } from './values'

/**
 * Client validation for immediate feedback, mirroring backend
 * RecordValidator for every rule that needs no database: required, length,
 * pattern, formats, numbers, dates, selections, file counts, comparisons,
 * custom rules, repeater row counts, group rules and submit blocks.
 * Uniqueness, existence and stored-file checks stay on the server, whose
 * 422 errors are merged under the same keys.
 */

export type Translate = (key: string, params?: Record<string, unknown>) => string

const FORMATS: Record<string, RegExp> = {
  email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/u,
  url: /^https?:\/\/[^\s/$.?#].[^\s]*$/iu,
  phone: /^\+?[0-9 ()-]{6,20}$/,
  numeric: /^[0-9]+$/,
  arabic: /^[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF\s0-9.,\u060C\u061B-]+$/u,
  english: /^[A-Za-z\s0-9.,;:'"!?()-]+$/,
  alphanumeric: /^[\p{L}\p{N}]+$/u,
}

export function iban(value: string): boolean {
  const s = value.replace(/ /g, '').toUpperCase()
  if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/.test(s)) return false
  const moved = s.slice(4) + s.slice(0, 4)
  let rem = 0
  for (const ch of moved) {
    const digits = /[A-Z]/.test(ch) ? String(ch.charCodeAt(0) - 55) : ch
    for (const d of digits) rem = (rem * 10 + Number(d)) % 97
  }
  return rem === 1
}

export function formatValid(format: string, value: string): boolean {
  if (format === 'iban') return iban(value)
  if (format === 'national_id') return /^[0-9]{8,15}$/.test(asciiDigits(value))
  return FORMATS[format]?.test(value) ?? true
}

export interface ValidationInput {
  index: FormIndex
  values: Values
  state: RuleState
  context: Context
  locale: string
  t: Translate
}

export function validate(input: ValidationInput): Record<string, string[]> {
  const { index, values, state, context } = input
  const errors: Record<string, string[]> = {}
  const add = (path: string, message: string) => {
    if (!(errors[path] ?? []).includes(message)) errors[path] = [...(errors[path] ?? []), message]
  }
  const base = context.with({ record: ValuesRecord.forRecord(index, values) })
  for (const f of index.mainFields()) {
    if (isHidden(index, f, state, null)) continue
    validateField(input, f, values[f.key] ?? null, values, null, f.key, base, add)
  }
  for (const [repUuid, rep] of index.repeaters) {
    const key = rep.group.key
    if (isGroupHidden(index, repUuid, state, null)) continue
    const rows = Array.isArray(values[key]) ? (values[key] as Row[]) : []
    const cfg = rep.group.repeater ?? {}
    if (rows.length < (cfg.minRows ?? 0)) add(key, input.t('runtime.rule.min_rows', { min: cfg.minRows }))
    if (cfg.maxRows !== null && cfg.maxRows !== undefined && rows.length > cfg.maxRows) add(key, input.t('runtime.rule.max_rows', { max: cfg.maxRows }))
    rows.forEach((row, i) => {
      const rowCtx = base.withRow(ValuesRecord.forRow(index, repUuid, row))
      for (const f of index.rowFields(repUuid)) {
        if (isHidden(index, f, state, [key, i])) continue
        validateField(input, f, row[f.key] ?? null, row, [key, i], `${key}.${i}.${f.key}`, rowCtx, add)
      }
    })
  }
  for (const g of index.definition.groups) {
    if (index.repeaters.has(g.uuid) || isGroupHidden(index, g.uuid, state, null) || !g.validation) continue
    const min = g.validation.minFilled ?? 0
    if (min > 0) {
      const filled = index.mainFields().filter((f) => index.groupChain(f.group).some((x) => x.uuid === g.uuid) && !isEmpty(values[f.key])).length
      if (filled < min) add(`_group.${g.key}`, pickText(g.validation.minFilledMessage, input.locale) ?? input.t('runtime.rule.min_filled', { min }))
    }
    for (const rule of g.validation.rules ?? []) {
      if (truthy(rule.when, base)) add(`_group.${g.key}`, pickText(rule.message, input.locale) ?? input.t('runtime.rule.custom', { attribute: '' }))
    }
  }
  for (const b of state.blocks) add('_form', pickText(b.message, input.locale) ?? input.t('runtime.rule.blocked'))
  return errors
}

function truthy(ast: unknown, ctx: Context): boolean {
  const v = evaluate(ast as never, ctx).value
  return v.type === 'boolean' && v.data
}

function validateField(input: ValidationInput, f: ClientField, v: unknown, scope: Values | Row, row: RowRef | null, path: string, ctx: Context, add: (path: string, message: string) => void): void {
  const { index, state, locale } = input
  if (!index.isStored(f) || index.isCalculated(f) || index.storage(f) === 'auto_number' || f.serverComputed) return
  const label = labelOf(f.i18n.label, locale, f.key)
  const msg = (rule: string, params: Record<string, unknown> = {}) => {
    const custom = pickText(f.i18n.messages?.[rule], locale)
    if (custom !== null) return custom
    const key = `runtime.rule.${rule}`
    const text = input.t(key, { attribute: label, ...params })
    return text === key ? input.t('runtime.rule.custom', { attribute: label }) : text
  }
  const rules = f.validation ?? {}
  const required = rules.required === true || f.access === 'required' || state.flag(f.uuid, 'required', row)
  if (isEmpty(v)) {
    if (required) add(path, msg('required'))
    return
  }
  const storage = index.storage(f)
  if (typeof v === 'string' && ['string', 'text', 'longtext', 'choice'].includes(storage)) {
    const len = textLength(v)
    if (rules.length?.min !== null && rules.length?.min !== undefined && len < rules.length.min) add(path, msg('length', { min: rules.length.min, max: rules.length.max ?? '' }))
    if (rules.length?.max !== null && rules.length?.max !== undefined && len > rules.length.max) add(path, msg('length', { min: rules.length.min ?? 0, max: rules.length.max }))
    const limit = f.storage.length
    if (limit !== null && limit !== undefined && ['string', 'choice'].includes(storage) && len > limit) add(path, msg('too_long', { max: limit }))
    if (rules.pattern && matches(v, rules.pattern) !== true) add(path, msg('pattern'))
    if (rules.format && !formatValid(rules.format, v)) add(path, msg('format'))
  }
  if (storage === 'phone' && typeof v === 'object' && rules.format && rules.format !== 'phone') {
    if (!formatValid(rules.format, String((v as { number?: unknown }).number ?? ''))) add(path, msg('format'))
  }

  const raw = storage === 'currency' && typeof v === 'object' && v !== null ? (v as { amount?: unknown }).amount : v
  const number = ['decimal', 'number', 'int', 'duration', 'currency'].includes(storage) ? Decimal.parse(String(raw ?? '')) : null
  if (number !== null) {
    const precision = f.storage.precision
    const scale = f.storage.scale
    if (precision && scale !== null && scale !== undefined && ['decimal', 'currency'].includes(storage)) {
      const [intPart = '', fraction = ''] = number.abs().toString().split('.')
      if (intPart.replace(/^0+/, '').length > precision - scale || fraction.length > scale) add(path, msg('precision', { precision, scale }))
    }
    const min = rules.number?.min ? Decimal.parse(rules.number.min) : null
    const max = rules.number?.max ? Decimal.parse(rules.number.max) : null
    if (min !== null && number.compare(min) < 0) add(path, msg('number', { min: rules.number?.min, max: rules.number?.max ?? '' }))
    if (max !== null && number.compare(max) > 0) add(path, msg('number', { min: rules.number?.min ?? '', max: rules.number?.max }))
    const step = rules.number?.step ? Decimal.parse(rules.number.step) : null
    if (step !== null && !step.isZero()) {
      const diff = number.sub(min ?? Decimal.zero())
      const rem = diff instanceof Decimal ? diff.mod(step) : null
      if (rem instanceof Decimal && !rem.isZero()) add(path, msg('step', { step: rules.number?.step }))
    }
  }

  if (['date', 'datetime', 'range_date', 'range_datetime'].includes(storage)) {
    const dates = typeof v === 'object' && v !== null ? [(v as { from?: unknown }).from, (v as { to?: unknown }).to].filter((x) => x) : [v]
    for (const d of dates) validateDate(String(d), rules.date ?? {}, ctx, path, msg, add)
  }

  if (f.options?.source === 'static' && !f.options.allowCustom && !index.isReference(f)) {
    const allowed = (f.options.static ?? []).filter((o) => o.active !== false).map((o) => o.value)
    const items = Array.isArray(v) ? v : [v]
    if (items.some((x) => !allowed.includes(String(x)))) add(path, msg('option'))
  }
  if (Array.isArray(v) && (index.isList(f) || storage === 'multi_choice')) {
    if (f.options?.min !== null && f.options?.min !== undefined && v.length < f.options.min) add(path, msg('min_selections', { min: f.options.min }))
    if (f.options?.max !== null && f.options?.max !== undefined && v.length > f.options.max) add(path, msg('max_selections', { max: f.options.max }))
  }
  if ((storage === 'files' || storage === 'file') && rules.file?.maxCount) {
    const count = Array.isArray(v) ? v.length : 1
    if (count > rules.file.maxCount) add(path, msg('file_count', { max: rules.file.maxCount }))
  }

  for (const cmp of rules.compare ?? []) {
    const other = index.fields.get(cmp.field)
    if (!other) continue
    const ov = scope[other.key] ?? null
    if (isEmpty(ov)) continue
    const a = typeof v === 'object' && v !== null ? (v as { amount?: unknown }).amount : v
    const b = typeof ov === 'object' && ov !== null ? (ov as { amount?: unknown }).amount : ov
    const da = Decimal.parse(String(a ?? ''))
    const db = Decimal.parse(String(b ?? ''))
    const c = da !== null && db !== null ? da.compare(db) : Math.sign(String(a).localeCompare(String(b), 'en'))
    const ok = { gt: c > 0, after: c > 0, gte: c >= 0, lt: c < 0, before: c < 0, lte: c <= 0, eq: c === 0, neq: c !== 0 }[cmp.op]
    if (!ok) add(path, msg('compare', { op: input.t(`runtime.compare.${cmp.op}`), other: labelOf(other.i18n.label, locale, other.key) }))
  }

  for (const custom of rules.custom ?? []) {
    if (truthy(custom.when, ctx)) add(path, msg(custom.messageKey))
  }
}

function validateDate(
  value: string,
  rules: NonNullable<ClientField['validation']['date']>,
  ctx: Context,
  path: string,
  msg: (rule: string, params?: Record<string, unknown>) => string,
  add: (path: string, message: string) => void,
): void {
  const day = parseDate(value.slice(0, 10))
  if (day === null) return
  if (rules.noPast && day < ctx.today) add(path, msg('no_past'))
  if (rules.noFuture && day > ctx.today) add(path, msg('no_future'))
  if ((rules.disabledWeekdays ?? []).includes(weekday(day))) add(path, msg('disabled_weekday'))
  if ((rules.disabledDates ?? []).includes(value.slice(0, 10))) add(path, msg('disabled_date'))
  for (const [bound, sign] of [
    ['min', 1],
    ['max', -1],
  ] as const) {
    const ast = rules[bound]
    if (!ast) continue
    const limit = evaluate(ast, ctx).value
    const limitDay = limit.type === 'date' ? limit.data : limit.type === 'datetime' ? Math.trunc(limit.data / 86400) : null
    if (limitDay !== null && (day - limitDay) * sign < 0) {
      const iso = new Date(limitDay * 86400000).toISOString().slice(0, 10)
      add(path, msg(`date_${bound}`, { date: iso }))
    }
  }
}

/** Errors of one field path (and its row paths) from an error map. */
export function errorsFor(errors: Record<string, string[]> | undefined, path: string): string[] {
  return errors?.[path] ?? []
}

export function hasText(map: I18nText | undefined): boolean {
  return !!map && Object.values(map).some((s) => s.trim() !== '')
}
