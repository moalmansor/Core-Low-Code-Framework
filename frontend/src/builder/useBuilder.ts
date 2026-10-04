import { computed, inject, provide, reactive, ref, shallowRef, watch, type InjectionKey, type Ref } from 'vue'
import { ApiError } from '@/api/http'
import { i18n } from '@/i18n'
import { useSession } from '@/stores/session'
import { builderApi, type FieldTemplate, type FormDetail } from './api'
import { hasClipboard, readClipboard, writeClipboard } from './clipboard'
import {
  canPlace,
  childrenOf,
  clone,
  descendants,
  duplicateElements,
  extractFragment,
  findField,
  findGroup,
  groupFragment,
  insertFragment,
  locateIssues,
  moveElements,
  newField,
  normalizeDocument,
  parentOf,
  refOf,
  removeElements,
  shiftElement,
  topLevel,
  type LocatedIssue,
} from './document'
import { History } from './history'
import type { DraftDocument, DraftIssue, FieldTypeCatalog, FieldTypeInfo, Fragment, GroupType, I18nText, LocaleOption } from './types'

/**
 * State and commands of one builder session (one form). Every change goes
 * through the document; a deep watcher records each distinct state in the
 * undo history and schedules an autosave of the whole document, guarded by
 * `draft_updated_at` (architecture §13.1).
 */

export type SaveState = 'loading' | 'saved' | 'dirty' | 'saving' | 'error' | 'invalid' | 'conflict' | 'locked'

export type DragPayload = { source: 'palette'; kind: 'field' | 'group'; type: string } | { source: 'template'; uuid: string } | { source: 'canvas'; uuids: string[] }

const HISTORY_DELAY = 350
const SAVE_DELAY = 1500

export function createBuilder(formUuid: string) {
  const session = useSession()
  const form = ref<FormDetail | null>(null)
  const doc = ref<DraftDocument | null>(null) as Ref<DraftDocument | null>
  const catalog = shallowRef<FieldTypeCatalog>({ fields: [], groups: [] })
  const templates = ref<FieldTemplate[]>([])
  const selection = ref<string[]>([])
  const anchor = ref<string | null>(null)
  const saveState = ref<SaveState>('loading')
  const stamp = ref<string | null>(null)
  const lastSavedAt = ref<Date | null>(null)
  const errors = ref<LocatedIssue[]>([])
  const problems = ref<LocatedIssue[]>([])
  const conflict = ref<{ updatedBy: string | null } | null>(null)
  const failure = ref<string | null>(null)
  const drag = ref<DragPayload | null>(null)
  const clipboardFilled = ref(hasClipboard())
  const historyDepth = reactive({ undo: 0, redo: 0 })
  const loadError = ref<string | null>(null)

  let history = new History('')
  let lastSaved = ''
  let historyTimer: ReturnType<typeof setTimeout> | null = null
  let saveTimer: ReturnType<typeof setTimeout> | null = null
  let saving: Promise<void> | null = null
  let saveAgain = false

  const locales = computed<LocaleOption[]>(() => (session.boot?.locales ?? []).map((l) => ({ code: l.code, native_name: l.native_name, direction: l.direction, is_default: l.is_default })))
  const locale = computed(() => session.locale)
  const typeInfo = (key: string): FieldTypeInfo | undefined => catalog.value.fields.find((t) => t.key === key)

  /** A label in every enabled locale whose interface catalog has it. */
  function labelIn(key: string, named: Record<string, unknown> = {}): I18nText {
    const out: I18nText = {}
    const available = i18n.global.availableLocales as string[]
    for (const l of locales.value) {
      if (available.includes(l.code)) out[l.code] = i18n.global.t(key, named, { locale: l.code })
    }
    return out
  }

  // ---------------------------------------------------------------- loading

  async function load(): Promise<void> {
    saveState.value = 'loading'
    loadError.value = null
    try {
      const [detail, draft, types, tpls] = await Promise.all([builderApi.form(formUuid), builderApi.draft(formUuid), builderApi.fieldTypes(), builderApi.templates()])
      form.value = detail
      catalog.value = types
      templates.value = tpls
      applyDraft(normalizeDocument(draft.document), draft.draft_updated_at, draft.problems)
    } catch (e) {
      loadError.value = e instanceof ApiError ? e.message : String(e)
      throw e
    }
  }

  function applyDraft(document: DraftDocument, updatedAt: string | null, serverProblems: DraftIssue[]): void {
    const json = JSON.stringify(document)
    history = new History(json)
    lastSaved = json
    syncDepth()
    doc.value = document
    stamp.value = updatedAt
    errors.value = []
    problems.value = locateIssues(document, serverProblems, 'problem')
    conflict.value = null
    failure.value = null
    selection.value = selection.value.filter((u) => refOf(document, u) !== null)
    saveState.value = form.value?.state === 'schema_inconsistent' ? 'locked' : 'saved'
  }

  async function reload(): Promise<void> {
    cancelTimers()
    const draft = await builderApi.draft(formUuid)
    form.value = await builderApi.form(formUuid)
    applyDraft(normalizeDocument(draft.document), draft.draft_updated_at, draft.problems)
  }

  async function reloadTemplates(): Promise<void> {
    templates.value = await builderApi.templates()
  }

  // ---------------------------------------------------------------- history

  function syncDepth(): void {
    Object.assign(historyDepth, history.depth)
  }

  /** Records the current document as a history state (if it changed). */
  function commit(): void {
    if (historyTimer) {
      clearTimeout(historyTimer)
      historyTimer = null
    }
    if (!doc.value) return
    if (history.record(JSON.stringify(doc.value))) {
      syncDepth()
      markDirty()
    }
  }

  watch(
    doc,
    () => {
      if (!doc.value) return
      if (historyTimer) clearTimeout(historyTimer)
      historyTimer = setTimeout(commit, HISTORY_DELAY)
    },
    { deep: true },
  )

  /** Applies a structural change as one undoable step. */
  function mutate<T>(change: (d: DraftDocument) => T): T | undefined {
    if (!doc.value) return undefined
    commit()
    const result = change(doc.value)
    commit()
    return result
  }

  function restore(state: string | null): void {
    if (state === null) return
    if (historyTimer) clearTimeout(historyTimer)
    historyTimer = null
    doc.value = JSON.parse(state) as DraftDocument
    syncDepth()
    selection.value = selection.value.filter((u) => refOf(doc.value!, u) !== null)
    markDirty()
  }

  function undo(): void {
    commit()
    restore(history.undo())
  }

  function redo(): void {
    commit()
    restore(history.redo())
  }

  // ---------------------------------------------------------------- saving

  function markDirty(): void {
    if (saveState.value === 'conflict' || saveState.value === 'locked') return
    if (history.current !== lastSaved) saveState.value = 'dirty'
    if (saveTimer) clearTimeout(saveTimer)
    saveTimer = setTimeout(() => void save(), SAVE_DELAY)
  }

  function cancelTimers(): void {
    if (saveTimer) clearTimeout(saveTimer)
    if (historyTimer) clearTimeout(historyTimer)
    saveTimer = historyTimer = null
  }

  async function save(): Promise<void> {
    commit()
    if (saveTimer) clearTimeout(saveTimer)
    saveTimer = null
    if (saving) {
      saveAgain = true
      return saving
    }
    if (!doc.value || saveState.value === 'conflict' || saveState.value === 'locked') return
    const json = history.current
    if (json === lastSaved) {
      if (saveState.value !== 'invalid') saveState.value = 'saved'
      return
    }
    const sent = JSON.parse(json) as DraftDocument
    saveState.value = 'saving'
    saving = (async () => {
      try {
        const result = await builderApi.saveDraft(formUuid, sent, stamp.value)
        stamp.value = result.draft_updated_at
        lastSaved = json
        lastSavedAt.value = new Date()
        errors.value = []
        failure.value = null
        problems.value = locateIssues(sent, result.problems, 'problem')
        saveState.value = history.current === json ? 'saved' : 'dirty'
      } catch (e) {
        if (e instanceof ApiError && e.status === 409 && e.code === 'draft_conflict') {
          conflict.value = { updatedBy: ((e.body as Record<string, unknown>).updated_by as string | null) ?? null }
          saveState.value = 'conflict'
        } else if (e instanceof ApiError && e.status === 422 && e.code === 'draft_invalid') {
          const list = (e.body as unknown as { errors: DraftIssue[] }).errors
          errors.value = locateIssues(sent, Array.isArray(list) ? list : [], 'error')
          saveState.value = 'invalid'
        } else if (e instanceof ApiError && e.status === 423) {
          failure.value = e.message
          saveState.value = 'locked'
        } else {
          failure.value = e instanceof ApiError ? e.message : String(e)
          saveState.value = 'error'
        }
      } finally {
        saving = null
      }
    })()
    await saving
    if (saveAgain) {
      saveAgain = false
      await save()
    }
  }

  /** Saves pending changes before leaving; true when nothing is left unsaved. */
  async function flush(): Promise<boolean> {
    commit()
    if (history.current === lastSaved) return true
    await save()
    return history.current === lastSaved
  }

  const hasUnsaved = computed(() => saveState.value !== 'saved' && saveState.value !== 'loading')

  // ---------------------------------------------------------------- selection

  const primary = computed(() => selection.value[selection.value.length - 1] ?? null)

  function select(uuid: string | null, mode: 'replace' | 'toggle' | 'range' = 'replace'): void {
    if (uuid === null) {
      selection.value = []
      anchor.value = null
      return
    }
    if (mode === 'toggle') {
      selection.value = selection.value.includes(uuid) ? selection.value.filter((u) => u !== uuid) : [...selection.value, uuid]
      anchor.value = uuid
      return
    }
    if (mode === 'range' && anchor.value && doc.value) {
      const parent = parentOf(doc.value, uuid)
      if (parentOf(doc.value, anchor.value) === parent) {
        const siblings = childrenOf(doc.value, parent).map((r) => r.uuid)
        const a = siblings.indexOf(anchor.value)
        const b = siblings.indexOf(uuid)
        selection.value = siblings.slice(Math.min(a, b), Math.max(a, b) + 1)
        return
      }
    }
    selection.value = [uuid]
    anchor.value = uuid
  }

  // ---------------------------------------------------------------- placement

  /** Where an inserted element goes: inside the selected group, after the selected element, or at the end. */
  function insertionPoint(kind: 'field' | 'group', type: string): { parent: string | null; index: number } | null {
    const d = doc.value
    if (!d) return null
    const groups = catalog.value.groups
    const current = primary.value
    if (current) {
      const g = findGroup(d, current)
      if (g && canPlace(d, groups, { kind, type }, g.uuid)) return { parent: g.uuid, index: childrenOf(d, g.uuid).length }
      const parent = parentOf(d, current)
      if (canPlace(d, groups, { kind, type }, parent)) return { parent, index: childrenOf(d, parent).findIndex((r) => r.uuid === current) + 1 }
    }
    if (canPlace(d, groups, { kind, type }, null)) return { parent: null, index: childrenOf(d, null).length }
    return null
  }

  function paletteFragment(kind: 'field' | 'group', type: string): Fragment | null {
    if (kind === 'group')
      return groupFragment(type as GroupType, (t, n) => {
        const title = labelIn(`builder.group.${t}`)
        if (n > 1) for (const code of Object.keys(title)) title[code] = `${title[code]} ${n}`
        return title
      })
    const info = typeInfo(type)
    if (!info) return null
    return { groups: [], fields: [newField(info, labelIn(`builder.type.${type}`))], conditions: [], relations: [] }
  }

  function templateFragment(uuid: string): Fragment | null {
    const t = templates.value.find((x) => x.uuid === uuid)
    if (!t) return null
    const fragment: Fragment = { groups: clone(t.definition.groups ?? []), fields: clone(t.definition.fields ?? []), conditions: clone(t.definition.conditions ?? []), relations: [] }
    if (t.kind === 'field') for (const f of fragment.fields) f.template = t.uuid
    return fragment
  }

  /** Inserts a fragment; returns false when it cannot be placed there. */
  function place(fragment: Fragment, parent: string | null, index: number): boolean {
    const inserted = mutate((d) => insertFragment(d, catalog.value.groups, fragment, parent, index))
    if (!inserted) return false
    selection.value = inserted
    anchor.value = inserted[0] ?? null
    return true
  }

  function insertFromPalette(kind: 'field' | 'group', type: string): boolean {
    const fragment = paletteFragment(kind, type)
    const at = insertionPoint(kind, type)
    if (!fragment || !at) return false
    return place(fragment, at.parent, at.index)
  }

  function insertTemplate(uuid: string, at?: { parent: string | null; index: number }): boolean {
    const fragment = templateFragment(uuid)
    if (!fragment) return false
    const root = fragment.groups.find((g) => !fragment.groups.some((x) => x.uuid === g.parent)) ?? null
    const point = at ?? insertionPoint(root ? 'group' : 'field', root?.type ?? fragment.fields[0]?.type ?? 'text')
    if (!point || !place(fragment, point.parent, point.index)) return false
    void builderApi.templateUsed(uuid).catch(() => undefined)
    return true
  }

  /** Handles a drop of the current drag payload at a position of a container. */
  function drop(parent: string | null, index: number): boolean {
    const payload = drag.value
    drag.value = null
    if (!payload || !doc.value) return false
    if (payload.source === 'palette') {
      const fragment = paletteFragment(payload.kind, payload.type)
      return fragment ? place(fragment, parent, index) : false
    }
    if (payload.source === 'template') return insertTemplate(payload.uuid, { parent, index })
    const d = doc.value
    const moving = new Set(topLevel(d, payload.uuids))
    const before = childrenOf(d, parent)
      .slice(0, index)
      .filter((r) => !moving.has(r.uuid)).length
    return mutate((x) => moveElements(x, catalog.value.groups, [...moving], parent, before)) ?? false
  }

  /** Whether the current drag payload may be dropped into a container. */
  function canDrop(parent: string | null): boolean {
    const payload = drag.value
    const d = doc.value
    if (!payload || !d) return false
    const groups = catalog.value.groups
    if (payload.source === 'palette') return canPlace(d, groups, { kind: payload.kind, type: payload.type }, parent)
    if (payload.source === 'template') {
      const fragment = templateFragment(payload.uuid)
      if (!fragment) return false
      const roots = [...fragment.groups.filter((g) => !fragment.groups.some((x) => x.uuid === g.parent)), ...fragment.fields.filter((f) => !fragment.groups.some((x) => x.uuid === f.group))]
      return roots.every((r) => canPlace(d, groups, 'parent' in r ? { kind: 'group', type: r.type } : { kind: 'field', type: r.type }, parent))
    }
    return topLevel(d, payload.uuids).every((uuid) => {
      const g = findGroup(d, uuid)
      if (g) {
        if (parent !== null && (parent === uuid || descendants(d, uuid).groups.includes(parent))) return false
        const containsData = descendants(d, uuid).groups.some((x) => ['repeater', 'subform'].includes(findGroup(d, x)!.type))
        return canPlace(d, groups, { kind: 'group', type: g.type, containsData }, parent)
      }
      const f = findField(d, uuid)
      return !!f && canPlace(d, groups, { kind: 'field', type: f.type }, parent)
    })
  }

  // ---------------------------------------------------------------- commands

  function copy(): boolean {
    if (!doc.value || selection.value.length === 0) return false
    writeClipboard(extractFragment(doc.value, selection.value))
    clipboardFilled.value = true
    return true
  }

  function cut(): boolean {
    if (!copy()) return false
    remove()
    return true
  }

  function paste(): boolean {
    const fragment = readClipboard()
    if (!fragment || !doc.value) return false
    const root = fragment.groups.find((g) => !fragment.groups.some((x) => x.uuid === g.parent))
    const first = root ? { kind: 'group' as const, type: root.type as string } : { kind: 'field' as const, type: fragment.fields[0]?.type ?? 'text' }
    const at = insertionPoint(first.kind, first.type)
    return at ? place(fragment, at.parent, at.index) : false
  }

  function duplicate(): boolean {
    if (selection.value.length === 0) return false
    const copies = mutate((d) => duplicateElements(d, catalog.value.groups, selection.value))
    if (!copies) return false
    selection.value = copies
    return true
  }

  function remove(uuids: string[] = selection.value): void {
    if (uuids.length === 0) return
    mutate((d) => removeElements(d, uuids))
    selection.value = selection.value.filter((u) => doc.value && refOf(doc.value, u) !== null)
  }

  function shift(delta: -1 | 1, uuid: string | null = primary.value): boolean {
    if (!uuid) return false
    return mutate((d) => shiftElement(d, catalog.value.groups, uuid, delta)) ?? false
  }

  function selectionFragment(): Fragment | null {
    return doc.value && selection.value.length ? extractFragment(doc.value, selection.value) : null
  }

  return reactive({
    formUuid,
    form,
    doc,
    catalog,
    templates,
    selection,
    primary,
    saveState,
    stamp,
    lastSavedAt,
    errors,
    problems,
    conflict,
    failure,
    drag,
    clipboardFilled,
    historyDepth,
    loadError,
    locales,
    locale,
    hasUnsaved,
    typeInfo,
    labelIn,
    load,
    reload,
    reloadTemplates,
    commit,
    mutate,
    undo,
    redo,
    save,
    flush,
    select,
    insertFromPalette,
    insertTemplate,
    drop,
    canDrop,
    copy,
    cut,
    paste,
    duplicate,
    remove,
    shift,
    selectionFragment,
    cancelTimers,
  })
}

export type Builder = ReturnType<typeof createBuilder>

const KEY: InjectionKey<Builder> = Symbol('builder')

export function provideBuilder(builder: Builder): void {
  provide(KEY, builder)
}

export function useBuilder(): Builder {
  const builder = inject(KEY)
  if (!builder) throw new Error('useBuilder() outside a form builder')
  return builder
}
