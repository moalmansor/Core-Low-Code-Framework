import type { ConditionDef, DraftDocument, DraftIssue, Effect, ElementRef, FieldDef, FieldTypeInfo, Fragment, GroupDef, GroupType, GroupTypeInfo, I18nText, RelationDef } from './types'
import { newUuid } from './uuid'

/**
 * Pure operations on the draft document (architecture §14.1): the element
 * tree, insertion, moving, nesting, deletion, copy/paste with fresh uuids and
 * unique keys, and reference clean-up. Every function mutates the document it
 * is given in place; the builder snapshots the document around each call for
 * undo/redo.
 */

/** Expression keywords cannot be keys (expression-language.md §2). */
export const RESERVED_KEYS = ['and', 'or', 'not', 'true', 'false', 'null']
const KEY_MAX = 48
const KEY_PATTERN = /^[a-z][a-z0-9_]{0,47}$/
const BUTTON_TYPES = ['submit', 'reset', 'button', 'image_button']

export function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

export function isValidKey(key: string): boolean {
  return KEY_PATTERN.test(key) && !RESERVED_KEYS.includes(key)
}

/** Converts any text into a snake_case key that the schema accepts. */
export function toKey(text: string, fallback = 'field'): string {
  let key = text
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
  if (!/^[a-z]/.test(key)) key = key === '' ? fallback : `${fallback}_${key}`
  key = key.slice(0, KEY_MAX).replace(/_+$/, '')
  if (RESERVED_KEYS.includes(key)) key = `${key}_value`
  return key
}

/** `base` as a key, suffixed `_2`, `_3`, … until it is not in `taken`. */
export function uniqueKey(base: string, taken: Set<string>): string {
  const key = toKey(base)
  if (!taken.has(key)) return key
  const stem = key.replace(/_\d+$/, '') || key
  for (let n = 2; ; n++) {
    const suffix = `_${n}`
    const candidate = stem.slice(0, KEY_MAX - suffix.length).replace(/_+$/, '') + suffix
    if (!taken.has(candidate)) return candidate
  }
}

/** Field and group keys share one namespace (DraftValidator). */
export function elementKeys(doc: DraftDocument, except: string | null = null): Set<string> {
  const keys = new Set<string>()
  for (const g of doc.groups) if (g.uuid !== except) keys.add(g.key)
  for (const f of doc.fields) if (f.uuid !== except) keys.add(f.key)
  return keys
}

export function findGroup(doc: DraftDocument, uuid: string | null | undefined): GroupDef | undefined {
  return uuid ? doc.groups.find((g) => g.uuid === uuid) : undefined
}

export function findField(doc: DraftDocument, uuid: string | null | undefined): FieldDef | undefined {
  return uuid ? doc.fields.find((f) => f.uuid === uuid) : undefined
}

export function refOf(doc: DraftDocument, uuid: string): ElementRef | null {
  if (findGroup(doc, uuid)) return { kind: 'group', uuid }
  if (findField(doc, uuid)) return { kind: 'field', uuid }
  return null
}

/** The container (group uuid or null for the root) that holds an element. */
export function parentOf(doc: DraftDocument, uuid: string): string | null {
  const g = findGroup(doc, uuid)
  if (g) return g.parent
  return findField(doc, uuid)?.group ?? null
}

/** Direct children of a container, groups and fields together, in display order. */
export function childrenOf(doc: DraftDocument, parent: string | null): ElementRef[] {
  const items: (ElementRef & { order: number; seq: number })[] = []
  doc.groups.forEach((g, i) => {
    if (g.parent === parent) items.push({ kind: 'group', uuid: g.uuid, order: g.order, seq: i })
  })
  doc.fields.forEach((f, i) => {
    if (f.group === parent) items.push({ kind: 'field', uuid: f.uuid, order: f.order, seq: 100000 + i })
  })
  items.sort((a, b) => a.order - b.order || a.seq - b.seq)
  return items.map(({ kind, uuid }) => ({ kind, uuid }))
}

/** Assigns orders 0…n-1 to the children of a container. */
export function renumber(doc: DraftDocument, parent: string | null): void {
  childrenOf(doc, parent).forEach((ref, i) => setOrder(doc, ref.uuid, i))
}

function setOrder(doc: DraftDocument, uuid: string, order: number): void {
  const g = findGroup(doc, uuid)
  if (g) g.order = order
  const f = findField(doc, uuid)
  if (f) f.order = order
}

/** Every element in display order (depth first) with its depth. */
export function flatten(doc: DraftDocument): (ElementRef & { depth: number; parent: string | null })[] {
  const out: (ElementRef & { depth: number; parent: string | null })[] = []
  const walk = (parent: string | null, depth: number, seen: Set<string>) => {
    for (const ref of childrenOf(doc, parent)) {
      out.push({ ...ref, depth, parent })
      if (ref.kind === 'group' && !seen.has(ref.uuid)) walk(ref.uuid, depth + 1, new Set([...seen, ref.uuid]))
    }
  }
  walk(null, 0, new Set())
  return out
}

/** True when `uuid` is `ancestor` or sits anywhere below it. */
export function isWithin(doc: DraftDocument, uuid: string | null, ancestor: string): boolean {
  let current: string | null = uuid
  const seen = new Set<string>()
  while (current !== null && !seen.has(current)) {
    if (current === ancestor) return true
    seen.add(current)
    current = findGroup(doc, current)?.parent ?? null
  }
  return false
}

/** The group uuids and field uuids below a group (not including it). */
export function descendants(doc: DraftDocument, group: string): { groups: string[]; fields: string[] } {
  const groups: string[] = []
  const fields: string[] = []
  const walk = (parent: string) => {
    for (const f of doc.fields) if (f.group === parent) fields.push(f.uuid)
    for (const g of doc.groups) {
      if (g.parent === parent && !groups.includes(g.uuid) && g.uuid !== group) {
        groups.push(g.uuid)
        walk(g.uuid)
      }
    }
  }
  walk(group)
  return { groups, fields }
}

/** The nearest repeater or sub-form at or above a container. */
export function dataAncestor(doc: DraftDocument, container: string | null): GroupDef | null {
  let current = container
  const seen = new Set<string>()
  while (current !== null && !seen.has(current)) {
    seen.add(current)
    const g = findGroup(doc, current)
    if (!g) return null
    if (g.type === 'repeater' || g.type === 'subform') return g
    current = g.parent
  }
  return null
}

/** The repeater whose rows hold a field, if any (for row-scope expressions). */
export function repeaterOf(doc: DraftDocument, field: FieldDef): GroupDef | null {
  const g = dataAncestor(doc, field.group)
  return g?.type === 'repeater' ? g : null
}

export interface Placeable {
  kind: 'field' | 'group'
  type: string
  /** For moved groups: the subtree already contains a repeater or sub-form. */
  containsData?: boolean
}

/**
 * Whether an element of this kind and type may be placed in a container,
 * following the nesting rules the server checks (FieldTypeRegistry
 * GROUP_PARENTS, EXCLUSIVE_CHILDREN, DATA_GROUPS and DraftValidator).
 */
export function canPlace(doc: DraftDocument, catalog: GroupTypeInfo[], item: Placeable, parent: string | null): boolean {
  const parentGroup = findGroup(doc, parent)
  if (parent !== null && !parentGroup) return false
  const parentType = parentGroup?.type ?? null
  const parentInfo = parentType ? catalog.find((g) => g.key === parentType) : undefined
  if (parentType === 'subform') return false
  if (item.kind === 'group') {
    const info = catalog.find((g) => g.key === item.type)
    if (info?.parents && !info.parents.includes(parentType)) return false
    if (parentInfo?.children && parentInfo.children !== item.type) return false
    const inData = dataAncestor(doc, parent) !== null
    if (inData && (info?.data || item.containsData)) return false
    return true
  }
  if (parentInfo?.children) return false
  if (BUTTON_TYPES.includes(item.type) && dataAncestor(doc, parent)?.type === 'repeater') return false
  return true
}

/** Element uuids of a selection without those whose ancestor group is also selected. */
export function topLevel(doc: DraftDocument, uuids: string[]): string[] {
  const set = new Set(uuids)
  const order = flatten(doc).map((r) => r.uuid)
  return uuids
    .filter((u) => {
      let p = parentOf(doc, u)
      const seen = new Set<string>()
      while (p !== null && !seen.has(p)) {
        if (set.has(p)) return false
        seen.add(p)
        p = findGroup(doc, p)?.parent ?? null
      }
      return refOf(doc, u) !== null
    })
    .sort((a, b) => order.indexOf(a) - order.indexOf(b))
}

function optionUuids(fields: FieldDef[]): Set<string> {
  const out = new Set<string>()
  for (const f of fields) for (const o of f.options?.static ?? []) out.add(o.uuid)
  return out
}

/** Copies the selected elements with their subtrees, the rules they own and the relations they use. */
export function extractFragment(doc: DraftDocument, uuids: string[]): Fragment {
  const roots = topLevel(doc, uuids)
  const groupIds = new Set<string>()
  const fieldIds = new Set<string>()
  for (const uuid of roots) {
    if (findGroup(doc, uuid)) {
      groupIds.add(uuid)
      const d = descendants(doc, uuid)
      d.groups.forEach((g) => groupIds.add(g))
      d.fields.forEach((f) => fieldIds.add(f))
    } else fieldIds.add(uuid)
  }
  const groups = clone(doc.groups.filter((g) => groupIds.has(g.uuid)))
  const fields = clone(doc.fields.filter((f) => fieldIds.has(f.uuid)))
  const options = optionUuids(fields)
  const conditions = clone(
    doc.conditions.filter(
      (c) => (c.owner.type === 'field' && fieldIds.has(c.owner.uuid)) || (c.owner.type === 'group' && groupIds.has(c.owner.uuid)) || (c.owner.type === 'option' && options.has(c.owner.uuid)),
    ),
  )
  const relationIds = new Set<string>()
  for (const f of fields) if (f.relation) relationIds.add(f.relation)
  for (const g of groups) if (g.subform?.relation) relationIds.add(g.subform.relation)
  const relations = clone(doc.relations.filter((r) => relationIds.has(r.uuid)))
  // Roots carry their position in the selection so paste keeps the order.
  roots.forEach((uuid, i) => {
    const g = groups.find((x) => x.uuid === uuid)
    if (g) g.order = i
    const f = fields.find((x) => x.uuid === uuid)
    if (f) f.order = i
  })
  return { groups, fields, conditions, relations }
}

const REF_SCOPES = ['record', 'old', 'row', 'parent']

/** Deep walk replacing uuid strings (and AST reference keys) according to the maps. */
function remap(value: unknown, uuids: Map<string, string>, keys: Map<string, string>): unknown {
  if (typeof value === 'string') return uuids.get(value) ?? value
  if (Array.isArray(value)) return value.map((v) => remap(v, uuids, keys))
  if (value === null || typeof value !== 'object') return value
  const obj = value as Record<string, unknown>
  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(obj)) out[k] = remap(v, uuids, keys)
  if (obj.k === 'ref' && REF_SCOPES.includes(String(obj.scope)) && Array.isArray(obj.path) && typeof obj.path[0] === 'string') {
    const renamed = keys.get(obj.path[0])
    if (renamed) out.path = [renamed, ...(obj.path as string[]).slice(1)]
  }
  return out
}

/**
 * Inserts a fragment into a container at an index: every uuid is new, keys
 * are made unique within the document, references inside the fragment follow
 * the new uuids and keys, and references to elements that do not exist in
 * this document are cleared. Returns the uuids of the inserted top-level
 * elements, or null when the fragment cannot be placed there.
 */
export function insertFragment(doc: DraftDocument, catalog: GroupTypeInfo[], fragment: Fragment, parent: string | null, index: number): string[] | null {
  const frag = clone(fragment)
  const groupIds = new Set(frag.groups.map((g) => g.uuid))
  const rootGroups = frag.groups.filter((g) => g.parent === null || !groupIds.has(g.parent))
  const rootFields = frag.fields.filter((f) => f.group === null || !groupIds.has(f.group))
  for (const g of rootGroups) {
    const containsData = frag.groups.some((x) => x !== g && (x.type === 'repeater' || x.type === 'subform'))
    if (!canPlace(doc, catalog, { kind: 'group', type: g.type, containsData }, parent)) return null
  }
  for (const f of rootFields) if (!canPlace(doc, catalog, { kind: 'field', type: f.type }, parent)) return null

  const uuids = new Map<string, string>()
  const fresh = (old: string) => {
    if (!uuids.has(old)) uuids.set(old, newUuid())
  }
  frag.groups.forEach((g) => fresh(g.uuid))
  frag.fields.forEach((f) => {
    fresh(f.uuid)
    f.options?.static?.forEach((o) => fresh(o.uuid))
  })
  frag.conditions.forEach((c) => fresh(c.uuid))

  // Relations: reuse an identical relation already in the document (same uuid), otherwise copy it.
  const relations: RelationDef[] = []
  const relationKeys = new Set(doc.relations.map((r) => r.key))
  for (const r of frag.relations) {
    if (doc.relations.some((x) => x.uuid === r.uuid)) continue
    fresh(r.uuid)
    relations.push(r)
  }

  const taken = elementKeys(doc)
  const keys = new Map<string, string>()
  for (const el of [...frag.groups, ...frag.fields]) {
    const key = uniqueKey(el.key, taken)
    taken.add(key)
    if (key !== el.key) keys.set(el.key, key)
    el.key = key
  }
  for (const r of relations) {
    const key = uniqueKey(r.key, relationKeys)
    relationKeys.add(key)
    r.key = key
  }

  const groups = frag.groups.map((g) => remap(g, uuids, keys) as GroupDef)
  const fields = frag.fields.map((f) => remap(f, uuids, keys) as FieldDef)
  const conditions = frag.conditions.map((c) => remap(c, uuids, keys) as ConditionDef)
  const newRelations = relations.map((r) => remap(r, uuids, keys) as RelationDef)

  const roots = [...rootGroups.map((g) => ({ uuid: uuids.get(g.uuid)!, order: g.order })), ...rootFields.map((f) => ({ uuid: uuids.get(f.uuid)!, order: f.order }))].sort((a, b) => a.order - b.order)
  const rootIds = new Set(roots.map((r) => r.uuid))
  for (const g of groups) if (rootIds.has(g.uuid)) g.parent = parent
  for (const f of fields) if (rootIds.has(f.uuid)) f.group = parent

  const siblings = childrenOf(doc, parent)
  doc.groups.push(...groups)
  doc.fields.push(...fields)
  doc.relations.push(...newRelations)
  doc.conditions.push(...conditions)
  shapeDocument(doc)
  const at = Math.max(0, Math.min(index, siblings.length))
  const ordered = [...siblings.slice(0, at).map((s) => s.uuid), ...roots.map((r) => r.uuid), ...siblings.slice(at).map((s) => s.uuid)]
  ordered.forEach((uuid, i) => setOrder(doc, uuid, i))
  pruneReferences(doc)
  return roots.map((r) => r.uuid)
}

/**
 * Moves elements into a container at an index (counted among the container's
 * children that are not being moved). Returns false when a move would break
 * the nesting rules or put a group inside itself.
 */
export function moveElements(doc: DraftDocument, catalog: GroupTypeInfo[], uuids: string[], parent: string | null, index: number): boolean {
  const roots = topLevel(doc, uuids)
  if (roots.length === 0) return false
  for (const uuid of roots) {
    const g = findGroup(doc, uuid)
    if (g) {
      if (isWithin(doc, parent, uuid)) return false
      const d = descendants(doc, uuid)
      const containsData = d.groups.some((x) => ['repeater', 'subform'].includes(findGroup(doc, x)!.type))
      if (!canPlace(doc, catalog, { kind: 'group', type: g.type, containsData }, parent)) return false
    } else if (!canPlace(doc, catalog, { kind: 'field', type: findField(doc, uuid)!.type }, parent)) return false
  }
  const moving = new Set(roots)
  const oldParents = new Set(roots.map((u) => parentOf(doc, u)))
  const siblings = childrenOf(doc, parent).filter((r) => !moving.has(r.uuid))
  for (const uuid of roots) {
    const g = findGroup(doc, uuid)
    if (g) g.parent = parent
    const f = findField(doc, uuid)
    if (f) f.group = parent
  }
  const at = Math.max(0, Math.min(index, siblings.length))
  const ordered = [...siblings.slice(0, at).map((s) => s.uuid), ...roots, ...siblings.slice(at).map((s) => s.uuid)]
  ordered.forEach((uuid, i) => setOrder(doc, uuid, i))
  for (const p of oldParents) if (p !== parent) renumber(doc, p)
  return true
}

/** Moves one element up (-1) or down (+1) among its siblings. */
export function shiftElement(doc: DraftDocument, catalog: GroupTypeInfo[], uuid: string, delta: -1 | 1): boolean {
  const parent = parentOf(doc, uuid)
  const siblings = childrenOf(doc, parent)
  const i = siblings.findIndex((s) => s.uuid === uuid)
  const j = i + delta
  if (i < 0 || j < 0 || j >= siblings.length) return false
  return moveElements(doc, catalog, [uuid], parent, j)
}

/** Deletes elements with their subtrees, the rules they own, and relations nothing uses any more. */
export function removeElements(doc: DraftDocument, uuids: string[]): void {
  const roots = topLevel(doc, uuids)
  const groupIds = new Set<string>()
  const fieldIds = new Set<string>()
  const parents = new Set<string | null>()
  for (const uuid of roots) {
    parents.add(parentOf(doc, uuid))
    if (findGroup(doc, uuid)) {
      groupIds.add(uuid)
      const d = descendants(doc, uuid)
      d.groups.forEach((g) => groupIds.add(g))
      d.fields.forEach((f) => fieldIds.add(f))
    } else fieldIds.add(uuid)
  }
  const removedRelations = new Set<string>()
  for (const f of doc.fields) if (fieldIds.has(f.uuid) && f.relation) removedRelations.add(f.relation)
  for (const g of doc.groups) if (groupIds.has(g.uuid) && g.subform?.relation) removedRelations.add(g.subform.relation)
  doc.groups = doc.groups.filter((g) => !groupIds.has(g.uuid))
  doc.fields = doc.fields.filter((f) => !fieldIds.has(f.uuid))
  const stillUsed = new Set<string>()
  for (const f of doc.fields) if (f.relation) stillUsed.add(f.relation)
  for (const g of doc.groups) if (g.subform?.relation) stillUsed.add(g.subform.relation)
  doc.relations = doc.relations.filter((r) => !removedRelations.has(r.uuid) || stillUsed.has(r.uuid))
  for (const p of parents) if (p === null || findGroup(doc, p)) renumber(doc, p)
  pruneReferences(doc)
}

/** Copies the selection and places the copy right after the last selected element. */
export function duplicateElements(doc: DraftDocument, catalog: GroupTypeInfo[], uuids: string[]): string[] | null {
  const roots = topLevel(doc, uuids)
  if (roots.length === 0) return null
  const last = roots[roots.length - 1]!
  const parent = parentOf(doc, last)
  const index = childrenOf(doc, parent).findIndex((r) => r.uuid === last) + 1
  return insertFragment(doc, catalog, extractFragment(doc, roots), parent, index)
}

/**
 * Clears references to elements that no longer exist so the document stays
 * storable (dangling references are save errors in DraftValidator).
 */
export function pruneReferences(doc: DraftDocument): void {
  const fields = new Set(doc.fields.map((f) => f.uuid))
  const groups = new Set(doc.groups.map((g) => g.uuid))
  const options = optionUuids(doc.fields)
  const relations = new Set(doc.relations.map((r) => r.uuid))
  const keepField = (u: string) => fields.has(u)

  doc.conditions = doc.conditions.filter((c) => {
    const o = c.owner
    if (o.type === 'form') return o.uuid === doc.form.uuid
    if (o.type === 'field') return fields.has(o.uuid)
    if (o.type === 'group') return groups.has(o.uuid)
    return options.has(o.uuid)
  })
  const targetExists = (e: Effect) => {
    if (!e.target) return true
    if (e.target.type === 'field') return fields.has(e.target.uuid)
    if (e.target.type === 'group') return groups.has(e.target.uuid)
    if (e.target.type === 'option') return options.has(e.target.uuid)
    return true
  }
  const conditionIds = new Set(doc.conditions.map((c) => c.uuid))
  for (const c of doc.conditions) {
    c.effects = c.effects.filter(targetExists)
    if (c.else) c.else = c.else.filter(targetExists)
  }
  for (const g of doc.groups) {
    if (g.parent !== null && !groups.has(g.parent)) g.parent = null
    if (g.repeater?.aggregates) g.repeater.aggregates = g.repeater.aggregates.filter((a) => fields.has(a.field))
    if (g.subform?.relation && !relations.has(g.subform.relation)) g.subform.relation = null
  }
  for (const f of doc.fields) {
    if (f.group !== null && !groups.has(f.group)) f.group = null
    if (f.relation && !relations.has(f.relation)) f.relation = null
    if (f.options) {
      if (f.options.dependsOn && !fields.has(f.options.dependsOn)) f.options.dependsOn = null
      for (const o of f.options.static ?? []) if (o.condition && !conditionIds.has(o.condition)) o.condition = null
    }
    const v = f.validation
    if (v) {
      if (v.unique?.scope) v.unique.scope = v.unique.scope.filter(keepField)
      if (v.compare) v.compare = v.compare.filter((c) => fields.has(c.field))
    }
    if (f.storage?.uniqueScope) f.storage.uniqueScope = f.storage.uniqueScope.filter(keepField)
    const b = f.behavior
    if (b) {
      if (b.autofill) b.autofill = b.autofill.filter((a) => fields.has(a.to))
      if (b.default?.field && !fields.has(b.default.field)) b.default.field = null
    }
    for (const e of f.events ?? []) for (const d of e.do) if (d.target && !fields.has(d.target)) d.target = null
  }
  const c = doc.collection
  if (c) {
    if (c.valueField && !fields.has(c.valueField)) c.valueField = null
    if (c.labelField && !fields.has(c.labelField)) c.labelField = null
    if (c.parentField && !fields.has(c.parentField)) c.parentField = null
  }
  const s = doc.form.settings
  if (s?.searchFields) s.searchFields = s.searchFields.filter(keepField)
}

/** A new field of a palette type with the defaults the registry declares. */
export function newField(info: FieldTypeInfo, label: I18nText): FieldDef {
  const stored = info.stored
  const defaults = info.defaults && !Array.isArray(info.defaults) ? info.defaults : {}
  return {
    uuid: newUuid(),
    key: toKey(info.key),
    type: info.key,
    group: null,
    order: 0,
    storage: stored
      ? {
          ...(defaults.length !== undefined ? { length: defaults.length } : {}),
          ...(defaults.precision !== undefined ? { precision: defaults.precision } : {}),
          ...(defaults.scale !== undefined ? { scale: defaults.scale } : {}),
          nullable: true,
          index: 'none',
        }
      : {},
    options: info.options ? { source: 'static', static: [] } : null,
    validation: {},
    behavior: {},
    ui: {},
    table: stored ? { visible: true, sortable: true, filterable: info.filter !== 'none', searchable: info.filter === 'text' } : {},
    export: stored ? { exportable: true, importable: !info.calculated, print: true, pdf: true } : { print: true, pdf: true },
    events: [],
    flags: { encrypted: false, sensitive: false, personal: false, trackChanges: stored },
    justification: 'inherit',
    relation: null,
    i18n: { label: { ...label } },
  }
}

/** A new group of a type. */
export function newGroup(type: GroupType, title: I18nText): GroupDef {
  return {
    uuid: newUuid(),
    key: type,
    type,
    parent: null,
    order: 0,
    layout: type === 'column' ? { span: { md: 6 } } : {},
    collapsible: type === 'panel' || type === 'accordion',
    defaultState: 'open',
    validation: null,
    repeater: type === 'repeater' ? { minRows: 0, maxRows: null, defaultRows: 0, display: 'table' } : null,
    wizard: type === 'wizard' || type === 'step' ? { validateBeforeNext: true, allowJump: false } : null,
    subform: null,
    justification: 'inherit',
    i18n: { title: { ...title } },
  }
}

/**
 * A palette group as a fragment: structural containers come with the
 * children they require (a tab in tabs, a step in a wizard, two columns in a row).
 */
export function groupFragment(type: GroupType, title: (t: GroupType, n: number) => I18nText): Fragment {
  const root = newGroup(type, title(type, 1))
  const groups = [root]
  const child: GroupType | null = type === 'tabs' ? 'tab' : type === 'wizard' ? 'step' : type === 'row' ? 'column' : null
  if (child) {
    const count = child === 'column' ? 2 : 1
    for (let i = 0; i < count; i++) {
      const g = newGroup(child, title(child, i + 1))
      g.parent = root.uuid
      g.order = i
      groups.push(g)
    }
  }
  return { groups, fields: [], conditions: [], relations: [] }
}

function obj<T extends object>(value: unknown): T {
  return (value && typeof value === 'object' && !Array.isArray(value) ? value : {}) as T
}

function list<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : []
}

/**
 * Normalises a document loaded from the API: PHP encodes empty maps as `[]`,
 * which the builder (and the schema) treat as objects.
 */
export function normalizeDocument(raw: DraftDocument): DraftDocument {
  const doc = clone(raw)
  shapeDocument(doc)
  return doc
}

/** Gives every element the property objects the editors bind to (all optional in the schema). */
export function shapeDocument(doc: DraftDocument): void {
  doc.form.settings = obj(doc.form.settings)
  doc.form.i18n = obj(doc.form.i18n)
  for (const k of ['name', 'description', 'submitButtonLabel'] as const) if (doc.form.i18n[k] !== undefined) doc.form.i18n[k] = obj(doc.form.i18n[k])
  doc.groups = list(doc.groups)
  doc.fields = list(doc.fields)
  doc.relations = list(doc.relations)
  doc.conditions = list(doc.conditions)
  if (doc.collection === undefined) doc.collection = null
  for (const g of doc.groups) {
    g.layout = obj(g.layout)
    g.i18n = obj(g.i18n)
    if (g.i18n.title !== undefined) g.i18n.title = obj(g.i18n.title)
    if (g.i18n.description !== undefined) g.i18n.description = obj(g.i18n.description)
  }
  for (const f of doc.fields) {
    f.storage = obj(f.storage)
    f.validation = obj(f.validation)
    f.behavior = obj(f.behavior)
    f.ui = obj(f.ui)
    if (f.ui.props !== undefined) f.ui.props = obj(f.ui.props)
    f.table = obj(f.table)
    f.export = obj(f.export)
    f.flags = obj(f.flags)
    f.events = list(f.events)
    const i18n = obj<Record<string, unknown>>(f.i18n)
    for (const k of Object.keys(i18n)) i18n[k] = obj(i18n[k])
    if (i18n.messages) for (const k of Object.keys(i18n.messages as object)) (i18n.messages as Record<string, unknown>)[k] = obj((i18n.messages as Record<string, unknown>)[k])
    f.i18n = i18n
    for (const o of f.options?.static ?? []) {
      o.i18n = obj(o.i18n)
      if (o.i18n.label !== undefined) o.i18n.label = obj(o.i18n.label)
    }
  }
  for (const c of doc.conditions) {
    c.effects = list(c.effects)
    c.else = list(c.else)
  }
  if (doc.form.kind === 'collection' && !doc.collection) doc.collection = { type: 'table' }
}

/** Where a validator path (`fields.3.validation.pattern`) points in the document. */
export interface IssueLocation {
  /** Element uuid, or null for form-level settings. */
  uuid: string | null
  kind: 'form' | 'field' | 'group'
  /** The remaining property path inside the element. */
  property: string
}

export function locateIssue(doc: DraftDocument, path: string): IssueLocation {
  const parts = path.split('.')
  const head = parts[0]
  const index = Number(parts[1])
  const rest = parts.slice(2).join('.')
  if (head === 'fields' && doc.fields[index]) return { uuid: doc.fields[index]!.uuid, kind: 'field', property: rest }
  if (head === 'groups' && doc.groups[index]) return { uuid: doc.groups[index]!.uuid, kind: 'group', property: rest }
  if (head === 'conditions' && doc.conditions[index]) {
    const owner = doc.conditions[index]!.owner
    if (owner.type === 'field' || owner.type === 'group') return { uuid: owner.uuid, kind: owner.type, property: `conditions.${rest}` }
    if (owner.type === 'option') {
      const field = doc.fields.find((f) => f.options?.static?.some((o) => o.uuid === owner.uuid))
      if (field) return { uuid: field.uuid, kind: 'field', property: `conditions.${rest}` }
    }
    return { uuid: null, kind: 'form', property: `conditions.${rest}` }
  }
  if (head === 'relations' && doc.relations[index]) {
    const r = doc.relations[index]!
    const field = doc.fields.find((f) => f.relation === r.uuid)
    if (field) return { uuid: field.uuid, kind: 'field', property: `relation.${rest}` }
    const group = doc.groups.find((g) => g.subform?.relation === r.uuid)
    if (group) return { uuid: group.uuid, kind: 'group', property: `subform.relation.${rest}` }
  }
  return { uuid: null, kind: 'form', property: path }
}

export interface LocatedIssue extends DraftIssue, IssueLocation {
  severity: 'error' | 'problem'
}

export function locateIssues(doc: DraftDocument, issues: DraftIssue[], severity: 'error' | 'problem'): LocatedIssue[] {
  return issues.map((i) => ({ ...i, ...locateIssue(doc, i.path), severity }))
}

/** Rewrites the first segment of record/old/row/parent references in every AST of the document. */
function renameInAst(value: unknown, from: string, to: string): unknown {
  if (Array.isArray(value)) return value.map((v) => renameInAst(v, from, to))
  if (value === null || typeof value !== 'object') return value
  const obj = value as Record<string, unknown>
  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(obj)) out[k] = renameInAst(v, from, to)
  if (obj.k === 'ref' && REF_SCOPES.includes(String(obj.scope)) && Array.isArray(obj.path) && obj.path[0] === from) out.path = [to, ...(obj.path as string[]).slice(1)]
  return out
}

/**
 * Renames a field or group key and every expression reference to it, so
 * conditions and formulas keep pointing at the same element.
 */
export function renameKey(doc: DraftDocument, uuid: string, key: string): void {
  const el = findField(doc, uuid) ?? findGroup(doc, uuid)
  if (!el || el.key === key) return
  const from = el.key
  el.key = key
  const fix = <T>(v: T): T => renameInAst(v, from, key) as T
  doc.conditions = doc.conditions.map((c) => ({ ...c, when: fix(c.when), effects: fix(c.effects), else: c.else ? fix(c.else) : c.else }))
  if (doc.form.titleTemplate) doc.form.titleTemplate = fix(doc.form.titleTemplate)
  for (const f of doc.fields) {
    if (f.behavior) f.behavior = fix(f.behavior)
    if (f.validation) f.validation = fix(f.validation)
    if (f.events) f.events = fix(f.events)
    if (f.options?.query) f.options.query = fix(f.options.query)
  }
  for (const g of doc.groups) if (g.validation) g.validation = fix(g.validation)
}
