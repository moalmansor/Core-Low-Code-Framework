import { Context, evaluate, Values as V, type ContextUser, type RawNode, type Value } from '@/expressions'
import type { FormIndex } from './formIndex'
import type { ClientField, Effect, EffectTarget, I18nText, References, Row, Values } from './types'
import { toApi, ValuesRecord } from './values'

/**
 * Client evaluation of a form's rules, the twin of backend
 * Records/Runtime/RuleRuntime.php: defaults on create, formulas, and
 * conditions with their effects, run in passes until values settle. Elements
 * inside a repeater are evaluated once per row with row scope. The server
 * re-evaluates everything on save and is authoritative.
 */

export const MAX_PASSES = 5

export type Flag = 'hidden' | 'disabled' | 'readOnly' | 'required'
/** A repeater row: [repeater key, row index]. */
export type RowRef = readonly [string, number]

export interface RuleMessage {
  severity: 'info' | 'success' | 'warning' | 'error'
  message: I18nText
  condition: string
}

export class RuleState {
  /** target uuid (or uuid#repeaterKey#index) → flags */
  readonly flags = new Map<string, Partial<Record<Flag, boolean>>>()
  readonly messages: RuleMessage[] = []
  readonly blocks: { message: I18nText; condition: string }[] = []
  /** Fields whose options a condition asked to reload in this evaluation. */
  readonly reloads = new Set<string>()
  /** Values of calculated display elements (output, progress, meter): uuid or uuid#row key → value. */
  readonly display = new Map<string, Value>()

  set(target: EffectTarget | undefined, flag: Flag, on: boolean, row: RowRef | null): void {
    if (target === undefined) return
    const key = RuleState.key(target.uuid, row)
    this.flags.set(key, { ...(this.flags.get(key) ?? {}), [flag]: on })
  }

  flag(uuid: string, flag: Flag, row: RowRef | null = null): boolean {
    if (row !== null) {
      const own = this.flags.get(RuleState.key(uuid, row))?.[flag]
      if (own !== undefined) return own
    }
    return this.flags.get(uuid)?.[flag] ?? false
  }

  static key(uuid: string, row: RowRef | null): string {
    return row === null ? uuid : `${uuid}#${row[0]}#${row[1]}`
  }
}

export interface RuleEnv {
  mode: 'create' | 'edit' | 'view' | 'print'
  user: ContextUser & { uuid?: string | null }
  /** URL parameters for `url_param` defaults and `@context.param.*`. */
  params?: Record<string, string>
  locale?: string
  timezone?: string
  references?: References
  /** Fixed evaluation instant (tests); defaults to now. */
  now?: { today: number; now: number }
}

export function baseContext(index: FormIndex, env: RuleEnv): Context {
  const timezone = env.timezone ?? 'UTC'
  const base = env.now ? new Context({ ...env.now, timezone }) : Context.now(timezone)
  const params: Record<string, Value> = {}
  for (const [k, v] of Object.entries(env.params ?? {})) params[k] = V.text(v)
  return base.with({ mode: env.mode, form: index.definition.form.key, locale: env.locale ?? 'en', user: env.user, params })
}

function holds(ast: RawNode, ctx: Context): boolean {
  const v = evaluate(ast, ctx).value
  return v.type === 'boolean' && v.data
}

function withRecord(index: FormIndex, ctx: Context, values: Values, old: Values | null, refs: References): Context {
  return ctx.with({ record: ValuesRecord.forRecord(index, values, refs), old: old === null ? null : ValuesRecord.forRecord(index, old, refs) })
}

function rowsOf(values: Values, key: string): Row[] {
  const rows = values[key]
  return Array.isArray(rows) ? (rows as Row[]) : []
}

function clone(values: Values): Values {
  return JSON.parse(JSON.stringify(values)) as Values
}

/** Value of a field's default (backend RuleRuntime::defaultValue). */
export function defaultValue(index: FormIndex, field: ClientField, values: Values, ctx: Context, env: RuleEnv): unknown {
  const d = field.behavior.default
  if (!d) {
    const defaults = [...(field.options?.defaults ?? [])]
    for (const o of field.options?.static ?? []) if (o.default) defaults.push(o.value)
    if (defaults.length === 0) return null
    return index.storage(field) === 'multi_choice' ? [...new Set(defaults)] : defaults[0]
  }
  const storage = index.storage(field)
  switch (d.kind) {
    case 'static':
      return d.value === '' || d.value === undefined ? null : d.value
    case 'current_user':
      return storage === 'user' ? (env.user.uuid ?? null)?.toLowerCase() || null : (env.user.name ?? null)
    case 'current_department':
      // Needs the department's uuid or code, which only the server holds; applied on save.
      return null
    case 'now':
      return toApi(index, field, V.datetime(ctx.now))
    case 'today':
      return toApi(index, field, V.date(ctx.today))
    case 'url_param':
      return d.param && env.params?.[d.param] !== undefined && env.params[d.param] !== '' ? env.params[d.param] : null
    case 'field': {
      const source = d.field ? index.fields.get(d.field) : undefined
      return source ? (values[source.key] ?? null) : null
    }
    case 'formula':
      return d.expr ? toApi(index, field, evaluate(d.expr, ctx).value) : null
    case 'reference':
      return d.reference?.path ? toApi(index, field, evaluate({ k: 'ref', scope: 'record', path: d.reference.path }, ctx).value) : null
    default:
      return null
  }
}

export function applyDefaults(index: FormIndex, input: Values, env: RuleEnv): Values {
  const values = clone(input)
  const refs = env.references ?? {}
  const ctx = baseContext(index, env).with({ record: ValuesRecord.forRecord(index, values, refs) })
  for (const f of index.mainFields()) {
    if ((values[f.key] ?? null) !== null || !index.isStored(f)) continue
    const v = defaultValue(index, f, values, ctx, env)
    if (v !== null && v !== undefined) values[f.key] = v
  }
  for (const [repUuid, rep] of index.repeaters) {
    const key = rep.group.key
    if (!(key in values)) values[key] = Array.from({ length: rep.group.repeater?.defaultRows ?? 0 }, () => ({}))
    rowsOf(values, key).forEach((row, i) => {
      for (const f of index.rowFields(repUuid)) {
        if ((row[f.key] ?? null) !== null) continue
        const v = defaultValue(index, f, values, ctx.withRow(ValuesRecord.forRow(index, repUuid, row, refs)), env)
        if (v !== null && v !== undefined) (values[key] as Row[])[i]![f.key] = v
      }
    })
  }
  return values
}

/** Default values of a new repeater row. */
export function newRow(index: FormIndex, repeaterUuid: string, values: Values, env: RuleEnv): Row {
  const row: Row = {}
  const refs = env.references ?? {}
  const ctx = baseContext(index, env).with({ record: ValuesRecord.forRecord(index, values, refs) })
  for (const f of index.rowFields(repeaterUuid)) {
    const v = defaultValue(index, f, values, ctx.withRow(ValuesRecord.forRow(index, repeaterUuid, row, refs)), env)
    if (v !== null && v !== undefined) row[f.key] = v
  }
  return row
}

function formulas(index: FormIndex, values: Values, ctx: Context, state: RuleState): Values {
  for (const f of index.mainFields()) {
    const ast = f.behavior.formula
    if (!ast) continue
    const result = evaluate(ast, ctx).value
    if (index.isStored(f)) values[f.key] = toApi(index, f, result)
    else state.display.set(f.uuid, result)
  }
  for (const [repUuid, rep] of index.repeaters) {
    const key = rep.group.key
    rowsOf(values, key).forEach((row, i) => {
      const rowCtx = ctx.withRow(ValuesRecord.forRow(index, repUuid, row, {}))
      for (const f of index.rowFields(repUuid)) {
        if (!f.behavior.formula) continue
        const result = evaluate(f.behavior.formula, rowCtx).value
        if (index.isStored(f)) row[f.key] = toApi(index, f, result)
        else state.display.set(RuleState.key(f.uuid, [key, i]), result)
      }
    })
  }
  return values
}

function repeaterOfOwner(index: FormIndex, owner: { type: string; uuid: string }): string | null {
  if (owner.type === 'field') return index.fieldRepeater.get(owner.uuid) ?? null
  if (owner.type === 'group') return index.repeaters.has(owner.uuid) ? null : index.groupRepeater(owner.uuid)
  return null
}

function assign(values: Values, field: ClientField, value: unknown, row: RowRef | null): void {
  if (row !== null) {
    const rows = values[row[0]] as Row[]
    rows[row[1]]![field.key] = value
  } else {
    values[field.key] = value
  }
}

function apply(index: FormIndex, effects: Effect[], values: Values, ctx: Context, state: RuleState, row: RowRef | null, condition: string): void {
  for (const e of effects) {
    const target = e.target
    const field = target?.type === 'field' ? (index.fields.get(target.uuid) ?? null) : null
    const inRow = field !== null && row !== null && index.fieldRepeater.has(field.uuid)
    const at = inRow ? row : null
    switch (e.effect) {
      case 'show':
      case 'enable':
        state.set(target, e.effect === 'show' ? 'hidden' : 'disabled', false, at)
        break
      case 'hide':
        state.set(target, 'hidden', true, at)
        break
      case 'disable':
        state.set(target, 'disabled', true, at)
        break
      case 'read_only':
        state.set(target, 'readOnly', true, at)
        break
      case 'require':
        state.set(target, 'required', true, at)
        break
      case 'set_value':
        if (field !== null && e.value) assign(values, field, toApi(index, field, evaluate(e.value, ctx).value), at)
        break
      case 'clear_value':
        if (field !== null) assign(values, field, null, at)
        break
      case 'show_message':
        state.messages.push({ severity: e.severity ?? 'info', message: e.message ?? {}, condition })
        break
      case 'block_submit':
        state.blocks.push({ message: e.message ?? {}, condition })
        break
      case 'reload_options':
        if (target?.type === 'field') state.reloads.add(target.uuid)
        break
      default:
        // trigger_action runs server-side through the actions module.
        break
    }
  }
}

function conditions(index: FormIndex, values: Values, ctx: Context, state: RuleState): Values {
  const list = [...index.definition.conditions].sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
  for (const c of list) {
    if (c.active === false) continue
    const repeater = repeaterOfOwner(index, c.owner)
    if (repeater === null) {
      apply(index, holds(c.when, ctx) ? c.effects : (c.else ?? []), values, ctx, state, null, c.uuid)
      continue
    }
    const key = index.repeaters.get(repeater)!.group.key
    rowsOf(values, key).forEach((row, i) => {
      const rowCtx = ctx.withRow(ValuesRecord.forRow(index, repeater, row, {}))
      apply(index, holds(c.when, rowCtx) ? c.effects : (c.else ?? []), values, rowCtx, state, [key, i], c.uuid)
    })
  }
  return values
}

/**
 * Runs formulas and conditions (and defaults when asked) over the values,
 * repeating until they settle. Never mutates the input.
 */
export function runRules(index: FormIndex, input: Values, old: Values | null, env: RuleEnv, applyDefaultsFirst = false): { values: Values; state: RuleState } {
  const refs = env.references ?? {}
  const ctx = baseContext(index, env)
  let values = applyDefaultsFirst ? applyDefaults(index, input, env) : clone(input)
  let state = new RuleState()
  for (let pass = 0; pass < MAX_PASSES; pass++) {
    const before = JSON.stringify(values)
    state = new RuleState()
    values = formulas(index, values, withRecord(index, ctx, values, old, refs), state)
    values = conditions(index, values, withRecord(index, ctx, values, old, refs), state)
    if (JSON.stringify(values) === before) break
  }
  return { values, state }
}

/** Whether a field is hidden by a condition on it or on any of its groups (backend RecordValidator::hidden). */
export function isHidden(index: FormIndex, field: ClientField, state: RuleState, row: RowRef | null): boolean {
  if (field.access === 'hidden' || state.flag(field.uuid, 'hidden', row)) return true
  return isGroupHidden(index, field.group, state, row)
}

export function isGroupHidden(index: FormIndex, groupUuid: string | null, state: RuleState, row: RowRef | null): boolean {
  for (const g of index.groupChain(groupUuid)) {
    if (g.access === 'hidden' || state.flag(g.uuid, 'hidden', row) || state.flag(g.uuid, 'hidden')) return true
  }
  return false
}

/** Effective interaction state of a field for the current mode, access level, conditions and groups. */
export function fieldState(index: FormIndex, field: ClientField, state: RuleState, row: RowRef | null, mode: string): { hidden: boolean; readOnly: boolean; disabled: boolean; required: boolean } {
  const hidden = isHidden(index, field, state, row)
  let readOnly =
    mode === 'view' ||
    mode === 'print' ||
    field.access === 'read_only' ||
    state.flag(field.uuid, 'readOnly', row) ||
    index.isCalculated(field) ||
    index.storage(field) === 'auto_number' ||
    field.serverComputed
  let disabled = state.flag(field.uuid, 'disabled', row)
  for (const g of index.groupChain(field.group)) {
    if (g.access === 'read_only' || state.flag(g.uuid, 'readOnly', row) || state.flag(g.uuid, 'readOnly')) readOnly = true
    if (state.flag(g.uuid, 'disabled', row) || state.flag(g.uuid, 'disabled')) disabled = true
  }
  if (field.behavior.formula && index.isStored(field)) readOnly = true
  const required = !readOnly && !disabled && (field.validation.required === true || field.access === 'required' || state.flag(field.uuid, 'required', row))
  return { hidden, readOnly, disabled, required }
}
