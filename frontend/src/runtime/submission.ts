import { changedValues } from './conflict'
import type { FormIndex } from './formIndex'
import { fieldState, isGroupHidden, type RuleState } from './rules'
import type { Values } from './types'

/**
 * What a save sends (backend RecordPipeline): only values the user may
 * change. Computed values (formulas, auto-numbers, server-computed fields)
 * and fields read-only or disabled by access or a condition are left to the
 * server, which derives them itself and rejects submitted changes to them.
 */
export function submittableKeys(index: FormIndex, state: RuleState, mode: 'create' | 'edit'): Set<string> {
  const keys = new Set<string>()
  for (const f of index.mainFields()) {
    if (!index.isStored(f) || index.isCalculated(f) || index.storage(f) === 'auto_number' || f.serverComputed) continue
    if (f.behavior.formula) continue
    const s = fieldState(index, f, state, null, mode)
    if (s.readOnly || s.disabled) continue
    keys.add(f.key)
  }
  for (const [uuid, rep] of index.repeaters) {
    if (rep.group.access === 'read_only') continue
    if (index.groupChain(uuid).some((g) => g.access === 'read_only' || state.flag(g.uuid, 'readOnly') || state.flag(g.uuid, 'disabled'))) continue
    if (isGroupHidden(index, uuid, state, null) && mode === 'edit') continue
    keys.add(rep.group.key)
  }
  return keys
}

/** The `values` body of a create (every submittable value) or an update (only what changed). */
export function submissionValues(index: FormIndex, state: RuleState, mode: 'create' | 'edit', current: Values, loaded: Values): Values {
  const keys = submittableKeys(index, state, mode)
  const source = mode === 'create' ? current : changedValues(loaded, current)
  const out: Values = {}
  for (const [k, v] of Object.entries(source)) if (keys.has(k)) out[k] = v ?? null
  return out
}
