<script setup lang="ts">
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { computed, inject, provide, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { routeLocationKey } from 'vue-router'
import { evaluate } from '@/expressions'
import { useSession } from '@/stores/session'
import { pathOf, RECORD_FILES, RENDERER, valueOf, type RendererContext } from './context'
import { FormIndex } from './formIndex'
import GroupBody from './GroupBody.vue'
import { pickText } from './i18nText'
import { applyDefaults, baseContext, contextUser, RuleState, runRules, type RowRef, type RuleEnv } from './rules'
import type { ClientDefinition, ClientField, ClientGroup, FileMeta, FormMode, References, Row, Values } from './types'
import { validate as runValidation } from './validation'
import { same, toApi, ValuesRecord } from './values'

/**
 * Renders a client definition in create, edit, view or print mode: groups
 * (sections, tabs, wizards, rows/columns, panels, repeaters, sub-forms),
 * every field type, display elements, live conditions, defaults, formulas
 * and client validation through the TypeScript expression runtime.
 */
const props = defineProps<{
  definition: ClientDefinition
  modelValue: Record<string, unknown>
  mode: 'create' | 'edit' | 'view' | 'print'
  errors?: Record<string, string[]>
  formUuid?: string
  references?: Record<string, Record<string, string>>
}>()
const emit = defineEmits<{ 'update:modelValue': [value: Record<string, unknown>] }>()

const { t, locale } = useI18n()
const session = useSession()
const route = inject(routeLocationKey, null)
let toast: ReturnType<typeof useToast> | null = null
try {
  toast = useToast()
} catch {
  toast = null
}

const index = computed(() => new FormIndex(props.definition))
const mode = computed<FormMode>(() => props.mode)
const editable = computed(() => props.mode === 'create' || props.mode === 'edit')
const localTitles = reactive<References>({})
const references = computed<References>(() => {
  const out: References = {}
  for (const [k, v] of Object.entries(props.references ?? {})) out[k] = { ...v }
  for (const [k, v] of Object.entries(localTitles)) out[k] = { ...(out[k] ?? {}), ...v }
  return out
})
const providedFiles = inject(RECORD_FILES, null)
const files = ref<Record<string, FileMeta>>({})
watch(
  () => providedFiles?.value,
  (v) => (files.value = { ...files.value, ...(v ?? {}) }),
  { immediate: true, deep: true },
)

const params = computed<Record<string, string>>(() => {
  const out: Record<string, string> = {}
  for (const [k, v] of Object.entries(route?.query ?? {})) if (typeof v === 'string') out[k] = v
  return out
})

const env = computed<RuleEnv>(() => ({
  mode: props.mode,
  user: props.definition.user
    ? contextUser(props.definition.user, locale.value)
    : { id: session.me?.id ?? null, uuid: session.me?.uuid ?? null, name: session.me?.name ?? null, email: session.me?.email ?? null, roles: session.me?.roles ?? [], locale: locale.value },
  params: params.value,
  locale: locale.value,
  references: references.value,
}))

/** Values the form opened with: the `old` scope in edit mode and the reset target. */
const initial = ref<Values>(JSON.parse(JSON.stringify(props.modelValue ?? {})) as Values)
const defaultsApplied = ref(false)

const evaluation = computed(() => {
  try {
    return runRules(index.value, props.modelValue ?? {}, props.mode === 'edit' ? initial.value : null, env.value)
  } catch {
    return { values: props.modelValue ?? {}, state: new RuleState() }
  }
})
const state = computed(() => evaluation.value.state)
const values = computed(() => (editable.value ? evaluation.value.values : (props.modelValue ?? {})))

function commit(next: Values): void {
  const result = runRules(index.value, next, props.mode === 'edit' ? initial.value : null, env.value)
  emit('update:modelValue', result.values)
}

// Defaults on create, then keep formulas and set/clear effects applied.
watch(
  [index, () => props.mode],
  () => {
    if (props.mode === 'create' && !defaultsApplied.value) {
      defaultsApplied.value = true
      const withDefaults = applyDefaults(index.value, props.modelValue ?? {}, env.value)
      initial.value = JSON.parse(JSON.stringify(withDefaults)) as Values
      commit(withDefaults)
    }
  },
  { immediate: true },
)
// Guard against rules that never settle (for example a value set from now()).
let autoEmits = 0
let autoWindow = 0
watch(evaluation, (ev) => {
  if (!editable.value || same(ev.values, props.modelValue)) return
  const now = Date.now()
  if (now - autoWindow > 1000) {
    autoWindow = now
    autoEmits = 0
  }
  if (++autoEmits > 20) return
  emit('update:modelValue', ev.values)
})

// Options reload tokens: bumped by `reload_options` effects as they start to apply, and by events.
const reloadTokens = reactive<Record<string, number>>({})
let lastReloads = new Set<string>()
watch(state, (s) => {
  for (const uuid of s.reloads) if (!lastReloads.has(uuid)) reloadTokens[uuid] = (reloadTokens[uuid] ?? 0) + 1
  lastReloads = new Set(s.reloads)
})

// Errors: server errors until the field changes; client errors once a path was touched or a submit was attempted.
const touched = reactive(new Set<string>())
const editedSinceServer = reactive(new Set<string>())
const showAll = ref(false)
watch(
  () => props.errors,
  () => editedSinceServer.clear(),
)
const clientErrors = computed<Record<string, string[]>>(() => {
  if (!editable.value) return {}
  try {
    return runValidation({ index: index.value, values: values.value, state: state.value, context: baseContext(index.value, env.value), locale: locale.value, t })
  } catch {
    return {}
  }
})

function errorsAt(path: string): string[] {
  const server = editedSinceServer.has(path) ? [] : (props.errors?.[path] ?? [])
  const client = showAll.value || touched.has(path) ? (clientErrors.value[path] ?? []) : []
  return [...new Set([...server, ...client])]
}

function setValue(field: ClientField, value: unknown, row: RowRef | null): void {
  if (!editable.value) return
  const next = JSON.parse(JSON.stringify(values.value)) as Values
  if (row === null) next[field.key] = value
  else {
    const rows = Array.isArray(next[row[0]]) ? (next[row[0]] as Row[]) : []
    rows[row[1]] = { ...(rows[row[1]] ?? {}), [field.key]: value }
    next[row[0]] = rows
  }
  const path = pathOf(field, row)
  touched.add(path)
  editedSinceServer.add(path)
  commit(next)
  runEvents(field, 'change', row, next)
}

function setRows(key: string, rows: Row[]): void {
  if (!editable.value) return
  touched.add(key)
  editedSinceServer.add(key)
  commit({ ...JSON.parse(JSON.stringify(values.value)), [key]: rows })
}

function runEvents(field: ClientField, on: 'change' | 'focus' | 'blur', row: RowRef | null, current: Values = values.value): void {
  const events = (field.events ?? []).filter((e) => e.on === on)
  if (!events.length || !editable.value) return
  const record = ValuesRecord.forRecord(index.value, current, references.value)
  let ctx = baseContext(index.value, env.value).with({ record })
  const repeater = index.value.fieldRepeater.get(field.uuid)
  if (row !== null && repeater !== undefined) {
    const r = (current[row[0]] as Row[] | undefined)?.[row[1]] ?? {}
    ctx = ctx.withRow(ValuesRecord.forRow(index.value, repeater, r, references.value))
  }
  let next: Values | null = null
  for (const e of events) {
    if (e.when) {
      const w = evaluate(e.when, ctx).value
      if (!(w.type === 'boolean' && w.data)) continue
    }
    for (const step of e.do) {
      const target = step.target ? index.value.fields.get(step.target) : undefined
      if (step.type === 'set_field' && target && step.value) {
        next ??= JSON.parse(JSON.stringify(current)) as Values
        const v = toApi(index.value, target, evaluate(step.value, ctx).value)
        const targetRow = row !== null && index.value.fieldRepeater.has(target.uuid) ? row : null
        if (targetRow === null) next[target.key] = v
        else ((next[targetRow[0]] as Row[])[targetRow[1]] ??= {})[target.key] = v
      } else if (step.type === 'reload_options' && target) {
        reloadTokens[target.uuid] = (reloadTokens[target.uuid] ?? 0) + 1
      } else if (step.type === 'notify') {
        const text = pickText(step.message, locale.value)
        if (text) toast?.add({ severity: step.severity === 'warning' ? 'warn' : (step.severity ?? 'info'), summary: text, life: 5000 })
      }
      // run_action and call_webhook execute server-side through the actions module.
    }
  }
  if (next !== null) commit(next)
}

function groupPaths(group: ClientGroup): string[] {
  const out: string[] = []
  for (const f of index.value.fieldsInGroup(group.uuid)) {
    const repeater = index.value.fieldRepeater.get(f.uuid)
    if (repeater === undefined) out.push(f.key)
    else {
      const key = index.value.repeaters.get(repeater)!.group.key
      out.push(key)
      const rows = Array.isArray(values.value[key]) ? (values.value[key] as Row[]) : []
      rows.forEach((_r, i) => out.push(`${key}.${i}.${f.key}`))
    }
  }
  out.push(`_group.${group.key}`)
  for (const g of index.value.definition.groups) if (index.value.groupChain(g.uuid).some((x) => x.uuid === group.uuid)) out.push(`_group.${g.key}`, g.key)
  return [...new Set(out)]
}

const ctx: RendererContext = {
  index,
  mode,
  formUuid: computed(() => props.formUuid),
  locale: computed(() => locale.value),
  values,
  state,
  env,
  references,
  files,
  setValue,
  setRows,
  fieldEvent: (field, on, row) => runEvents(field, on, row),
  optionsVersion: (uuid) => reloadTokens[uuid] ?? 0,
  rememberTitle(fieldKey, uuid, title) {
    localTitles[fieldKey] = { ...(localTitles[fieldKey] ?? {}), [uuid]: title }
  },
  rememberFile(meta) {
    files.value = { ...files.value, [meta.uuid]: meta }
  },
  errorsAt,
  touch: (path) => touched.add(path),
  validateGroup(group) {
    const paths = groupPaths(group)
    paths.forEach((p) => touched.add(p))
    return paths.every((p) => (clientErrors.value[p] ?? []).length === 0)
  },
  reset() {
    commit(JSON.parse(JSON.stringify(initial.value)) as Values)
    touched.clear()
  },
  displayValue(field, row) {
    const v = state.value.display.get(row === null ? field.uuid : RuleState.key(field.uuid, row))
    return v === undefined ? valueOf(values.value, field, row) : toApi(index.value, { ...field, type: 'text' }, v)
  },
}
provide(RENDERER, ctx)

const messages = computed(() =>
  state.value.messages.map((m) => ({ severity: m.severity === 'warning' ? 'warn' : m.severity, text: pickText(m.message, locale.value) ?? '', key: m.condition })).filter((m) => m.text !== ''),
)
const formErrors = computed(() => errorsAt('_form'))

defineExpose({
  /** Client validation of the whole form; shows every message. Returns the errors by path. */
  validate(): Record<string, string[]> {
    showAll.value = true
    return clientErrors.value
  },
  /** Submit is blocked by a condition (block_submit). */
  blocked: computed(() => state.value.blocks.length > 0),
  /** The values after rules, ready to send. */
  values,
  /** Effects of the conditions (flags, messages, blocks) for the current values. */
  state,
  /** The indexed definition. */
  index,
})
</script>

<template>
  <div class="lcf-form" :class="`lcf-mode-${mode}`" :data-mode="mode" data-testid="form-renderer">
    <Message v-for="m in messages" :key="m.key" :severity="m.severity" class="mb-3">{{ m.text }}</Message>
    <Message v-for="(e, i) in formErrors" :key="`fe-${i}`" severity="error" class="mb-3" data-testid="form-error">{{ e }}</Message>
    <GroupBody :group="null" :row="null" />
  </div>
</template>

<style>
.lcf-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: var(--lcf-gap, 1rem);
}
.lcf-cell {
  grid-column: span var(--lcf-xs, 12) / span var(--lcf-xs, 12);
  min-width: 0;
}
@media (min-width: 640px) {
  .lcf-cell {
    grid-column: span var(--lcf-sm, var(--lcf-xs, 12)) / span var(--lcf-sm, var(--lcf-xs, 12));
  }
}
@media (min-width: 768px) {
  .lcf-cell {
    grid-column: span var(--lcf-md, var(--lcf-sm, var(--lcf-xs, 12))) / span var(--lcf-md, var(--lcf-sm, var(--lcf-xs, 12)));
  }
}
@media (min-width: 1024px) {
  .lcf-cell {
    grid-column: span var(--lcf-lg, var(--lcf-md, var(--lcf-sm, var(--lcf-xs, 12)))) / span var(--lcf-lg, var(--lcf-md, var(--lcf-sm, var(--lcf-xs, 12))));
  }
}
@media (min-width: 1280px) {
  .lcf-cell {
    grid-column: span var(--lcf-xl, var(--lcf-lg, var(--lcf-md, var(--lcf-sm, var(--lcf-xs, 12))))) / span var(--lcf-xl, var(--lcf-lg, var(--lcf-md, var(--lcf-sm, var(--lcf-xs, 12)))));
  }
}
/* Record pages (design system §5.6): in View Mode the label is quiet and the
   value carries the weight; empty values read as empty, not as content. */
.lcf-mode-view .lcf-grid {
  gap: calc(var(--lcf-gap, 1rem) + 0.25rem) calc(var(--lcf-gap, 1rem) * 2);
}
.lcf-mode-view .lcf-field .lcf-label {
  font-size: var(--text-size-sm);
  font-weight: 400;
  color: var(--text-muted);
}
.lcf-mode-view .lcf-field .lcf-value {
  min-height: 0;
  padding-block: 0;
  font-weight: 500;
  color: var(--text);
}
.lcf-empty {
  font-size: var(--text-size-sm);
  font-style: italic;
  font-weight: 400;
  color: var(--text-muted);
}
/* A section after other content starts a new block. */
.lcf-cell + .lcf-cell > .lcf-section,
.lcf-cell + .lcf-cell > [data-group] {
  margin-block-start: 1rem;
}
.lcf-section-head {
  padding-block-end: 0.5rem;
  margin-block-end: 1rem;
  border-block-end: 1px solid var(--border);
}
.lcf-rich :where(p, ul, ol, blockquote, pre, table) {
  margin-block: 0.5rem;
}
.lcf-rich :where(ul, ol) {
  padding-inline-start: 1.5rem;
}
.lcf-rich ul {
  list-style: disc;
}
.lcf-rich ol {
  list-style: decimal;
}
.lcf-rich blockquote {
  border-inline-start: 3px solid var(--border-strong);
  padding-inline-start: 0.75rem;
  color: var(--text-muted);
}
.lcf-rich a {
  color: var(--primary);
  text-decoration: underline;
}
.lcf-rich h1 {
  font-size: 1.5rem;
  font-weight: 600;
}
.lcf-rich h2 {
  font-size: 1.3rem;
  font-weight: 600;
}
.lcf-rich h3 {
  font-size: 1.15rem;
  font-weight: 600;
}
.lcf-rich pre {
  direction: ltr;
  overflow-x: auto;
  background: var(--bg-subtle);
  padding: 0.5rem;
  border-radius: 0.375rem;
}
.lcf-rich td,
.lcf-rich th {
  border: 1px solid var(--border);
  padding: 0.25rem 0.5rem;
}
@media print {
  .lcf-no-print {
    display: none !important;
  }
}
</style>
