import { fieldType, isStored, storageOf, type Storage } from './fieldTypes'
import type { ClientDefinition, ClientField, ClientGroup, ClientRelation } from './types'

export interface RepeaterInfo {
  group: ClientGroup
  /** Row field key → field uuid. */
  fields: Record<string, string>
}

/**
 * A client definition indexed like the server's FormRuntime: fields by uuid
 * and key, groups, repeaters with their row fields, relations, and which
 * fields reference other records.
 */
export class FormIndex {
  readonly fields = new Map<string, ClientField>()
  /** Top-level field key → uuid. */
  readonly keys = new Map<string, string>()
  readonly groups = new Map<string, ClientGroup>()
  readonly repeaters = new Map<string, RepeaterInfo>()
  /** Repeater key → repeater group uuid. */
  readonly repeaterKeys = new Map<string, string>()
  /** Field uuid → repeater uuid of fields inside a repeater. */
  readonly fieldRepeater = new Map<string, string>()
  readonly relations = new Map<string, ClientRelation>()
  readonly children = new Map<string | null, ClientGroup[]>()
  readonly groupFields = new Map<string | null, ClientField[]>()

  readonly definition: ClientDefinition

  constructor(definition: ClientDefinition) {
    this.definition = definition
    for (const g of definition.groups) {
      this.groups.set(g.uuid, g)
      if (g.type === 'repeater') {
        this.repeaters.set(g.uuid, { group: g, fields: {} })
        this.repeaterKeys.set(g.key, g.uuid)
      }
    }
    for (const g of definition.groups) {
      // A group whose parent was withheld (hidden by access) is not rendered.
      if (g.parent !== null && !this.groups.has(g.parent)) continue
      this.children.set(g.parent, [...(this.children.get(g.parent) ?? []), g])
    }
    for (const r of definition.relations) this.relations.set(r.uuid, r)
    for (const f of definition.fields) {
      this.fields.set(f.uuid, f)
      const repeater = this.repeaterOf(f.group)
      if (repeater !== null) {
        this.fieldRepeater.set(f.uuid, repeater)
        this.repeaters.get(repeater)!.fields[f.key] = f.uuid
      } else {
        this.keys.set(f.key, f.uuid)
      }
      if (f.group !== null && !this.groups.has(f.group)) continue
      this.groupFields.set(f.group, [...(this.groupFields.get(f.group) ?? []), f])
    }
    const byOrder = <T extends { order: number }>(a: T, b: T) => a.order - b.order
    for (const list of this.children.values()) list.sort(byOrder)
    for (const list of this.groupFields.values()) list.sort(byOrder)
  }

  /** Fields of the main record (not inside repeaters), in definition order. */
  mainFields(): ClientField[] {
    return this.definition.fields.filter((f) => !this.fieldRepeater.has(f.uuid))
  }

  rowFields(repeaterUuid: string): ClientField[] {
    return Object.values(this.repeaters.get(repeaterUuid)?.fields ?? {}).map((u) => this.fields.get(u)!)
  }

  fieldByKey(key: string): ClientField | null {
    const uuid = this.keys.get(key)
    if (uuid !== undefined) return this.fields.get(uuid) ?? null
    for (const rep of this.repeaters.values()) {
      if (rep.fields[key] !== undefined) return this.fields.get(rep.fields[key]!) ?? null
    }
    return null
  }

  storage(field: ClientField): Storage {
    return storageOf(field.type)
  }

  isStored(field: ClientField): boolean {
    return isStored(field.type)
  }

  relationOf(field: ClientField): ClientRelation | null {
    return field.relation !== null ? (this.relations.get(field.relation) ?? null) : null
  }

  /** Whether the value is a record uuid (or list of uuids): lookups, pickers, collection-backed choices. */
  isReference(field: ClientField): boolean {
    const storage = this.storage(field)
    if (storage === 'user' || storage === 'role' || storage === 'department' || storage === 'lookup') return true
    return (storage === 'choice' || storage === 'multi_choice') && this.relationOf(field) !== null
  }

  isMultiReference(field: ClientField): boolean {
    return this.relationOf(field)?.type === 'many_to_many'
  }

  /** Multiple values (lists): multi choice, files, many-to-many references. */
  isList(field: ClientField): boolean {
    const storage = this.storage(field)
    return storage === 'multi_choice' || storage === 'files' || this.isMultiReference(field)
  }

  isCalculated(field: ClientField): boolean {
    return fieldType(field.type)?.calculated ?? false
  }

  /** Repeater group uuid that contains a group (excluding the group itself when it is a repeater). */
  groupRepeater(groupUuid: string): string | null {
    let g = this.groups.get(groupUuid) ?? null
    const seen = new Set<string>()
    while (g !== null && !seen.has(g.uuid)) {
      seen.add(g.uuid)
      const parent = g.parent === null ? null : (this.groups.get(g.parent) ?? null)
      if (parent !== null && parent.type === 'repeater') return parent.uuid
      g = parent
    }
    return null
  }

  /** Ancestor chain of a group, nearest first, the group included. */
  groupChain(groupUuid: string | null): ClientGroup[] {
    const out: ClientGroup[] = []
    const seen = new Set<string>()
    let id = groupUuid
    while (id !== null && !seen.has(id)) {
      seen.add(id)
      const g = this.groups.get(id)
      if (g === undefined) break
      out.push(g)
      id = g.parent
    }
    return out
  }

  /** All fields below a group, at any depth. */
  fieldsInGroup(groupUuid: string): ClientField[] {
    return this.definition.fields.filter((f) => this.groupChain(f.group).some((g) => g.uuid === groupUuid))
  }

  private repeaterOf(groupUuid: string | null): string | null {
    for (const g of this.groupChain(groupUuid)) if (g.type === 'repeater') return g.uuid
    return null
  }
}
