import type { InjectionKey, Slots } from 'vue'
import type { ClientDefinition, RecordPayload } from '../types'
import type { StatusPayload } from './types'

/** A View Mode panel as `GET /r/{form}/{record}/panels` returns it. */
export interface Panel {
  uuid: string
  parent: string | null
  type: string
  title: string | null
  content: string | null
  config: Record<string, unknown>
  data: unknown
}

export interface RelatedData {
  form: { uuid: string; key: string }
  total: number
  columns: { key: string; label: string }[]
  rows: { uuid: string; title: string | null; status: StatusPayload | null; cells: Record<string, unknown> }[]
  can_create: boolean
}

export interface PanelContext {
  form: string
  record: RecordPayload
  definition: ClientDefinition
  children: Map<string | null, Panel[]>
  slots: Slots
}

export const PANEL_CONTEXT: InjectionKey<PanelContext> = Symbol('panel-context')

/** The form body limited to the chosen top-level groups (all when none chosen). */
export function bodyFor(definition: ClientDefinition, groupKeys: string[]): ClientDefinition {
  if (!groupKeys.length) return definition
  const keep = new Set(definition.groups.filter((g) => groupKeys.includes(g.key)).map((g) => g.uuid))
  let grew = true
  while (grew) {
    grew = false
    for (const g of definition.groups) {
      if (g.parent && keep.has(g.parent) && !keep.has(g.uuid)) {
        keep.add(g.uuid)
        grew = true
      }
    }
  }
  return { ...definition, groups: definition.groups.filter((g) => keep.has(g.uuid)), fields: definition.fields.filter((f) => f.group !== null && keep.has(f.group)) }
}

/** Groups panels by parent, keeping their order. */
export function byParent(panels: Panel[]): Map<string | null, Panel[]> {
  const map = new Map<string | null, Panel[]>()
  for (const p of panels) map.set(p.parent, [...(map.get(p.parent) ?? []), p])
  return map
}
