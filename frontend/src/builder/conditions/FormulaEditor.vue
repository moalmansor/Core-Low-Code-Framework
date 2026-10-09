<script setup lang="ts">
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import Textarea from 'primevue/textarea'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { canonicalJson, compile, StaticError } from '@/expressions'
import { builderApi, type ExpressionError } from '../api'
import type { Ast } from '../types'
import { useBuilder } from '../useBuilder'
import { FUNCTION_GROUPS, signature } from './functions'
import { printExpression } from './print'
import { CONTEXT_ATTRIBUTES, RECORD_ATTRIBUTES, USER_ATTRIBUTES, resolverFor, type ExpressionScope } from './scope'
import TestEvaluate from './TestEvaluate.vue'

/**
 * Text editor for an expression (expression-language.md §2). The browser
 * parser and type checker give instant feedback while typing; the server
 * parser produces the AST that is stored, and the server check against the
 * saved draft is the authoritative verdict.
 */
const props = withDefaults(defineProps<{ scope: ExpressionScope; expected?: string | null; compact?: boolean; initialText?: string; label?: string; allowEmpty?: boolean }>(), {
  expected: null,
  compact: false,
  initialText: undefined,
  label: undefined,
  allowEmpty: true,
})
const model = defineModel<Ast | null | undefined>()
const { t } = useI18n()
const builder = useBuilder()
const uid = `fx-${Math.random().toString(36).slice(2, 9)}`

const text = ref(props.initialText ?? (model.value ? printExpression(model.value) : ''))
const local = ref<{ ok: true; type: string } | { ok: false; code: string; message: string; position: number | null } | null>(null)
const server = ref<{ state: 'idle' | 'checking' | 'ok' | 'error'; type?: string; error?: ExpressionError; stale?: boolean }>({ state: 'idle' })
let lastEmitted = model.value ? canonicalJson(model.value) : ''
let timer: ReturnType<typeof setTimeout> | null = null
let sequence = 0

watch(model, (ast) => {
  const json = ast ? canonicalJson(ast) : ''
  if (json !== lastEmitted) {
    lastEmitted = json
    text.value = ast ? printExpression(ast) : ''
    checkLocal()
  }
})

function checkLocal(): void {
  const source = text.value.trim()
  if (source === '') {
    local.value = null
    return
  }
  try {
    local.value = { ok: true, type: compile(source, resolverFor(props.scope), props.expected).type }
  } catch (e) {
    local.value = e instanceof StaticError ? { ok: false, code: e.errorCode, message: e.message, position: e.position } : { ok: false, code: 'SYNTAX', message: String(e), position: null }
  }
}
checkLocal()

function onInput(): void {
  checkLocal()
  updateSuggestions()
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => void commitToServer(), 600)
}

async function commitToServer(): Promise<void> {
  const source = text.value.trim()
  const run = ++sequence
  if (source === '') {
    if (props.allowEmpty) {
      lastEmitted = ''
      model.value = null
      server.value = { state: 'idle' }
    }
    return
  }
  server.value = { state: 'checking' }
  try {
    // Parsing has no form context: references are checked by the server check below.
    const parsed = await builderApi.parse({ source, expected: null })
    if (run !== sequence) return
    if (!parsed.ok) {
      server.value = { state: 'error', error: parsed.error, stale: true }
      return
    }
    lastEmitted = canonicalJson(parsed.ast)
    model.value = parsed.ast
    const checked = await builderApi.check({ ast: parsed.ast, form: builder.formUuid, expected: props.expected, rows: props.scope.rows })
    if (run !== sequence) return
    server.value = checked.ok ? { state: 'ok', type: checked.type } : { state: 'error', error: checked.error }
  } catch (e) {
    if (run !== sequence) return
    server.value = { state: 'error', error: { code: 'SYNTAX', message: e instanceof ApiError ? e.message : String(e), position: null, node: null }, stale: true }
  }
}

onBeforeUnmount(() => {
  if (timer) {
    clearTimeout(timer)
    void commitToServer()
  }
})

// ---------------------------------------------------------------- autocomplete

const textarea = ref<{ $el: HTMLTextAreaElement } | null>(null)
const suggestions = ref<{ insert: string; label: string; detail: string }[]>([])
const active = ref(0)
let tokenStart = 0

function element(): HTMLTextAreaElement | null {
  const el = textarea.value?.$el
  return el instanceof HTMLTextAreaElement ? el : null
}

function updateSuggestions(): void {
  const el = element()
  if (!el) return
  const caret = el.selectionStart ?? text.value.length
  const before = text.value.slice(0, caret)
  const m = /(@?[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]*)*|@)$/.exec(before)
  if (!m) {
    suggestions.value = []
    return
  }
  const token = m[1]!
  tokenStart = caret - token.length
  const out: { insert: string; label: string; detail: string }[] = []
  const fieldKeys = (rows: boolean) =>
    props.scope.fields.filter((f) => (rows ? f.repeater === props.scope.rows : f.repeater === null)).map((f) => ({ insert: f.key, label: f.key, detail: f.valueType }))
  if (token.startsWith('@')) {
    const [scopeName, ...rest] = token.slice(1).split('.')
    const partial = rest.join('.')
    if (rest.length === 0) {
      for (const s of ['user', 'record', 'old', 'context', ...(props.scope.rows ? ['row', 'parent'] : [])]) out.push({ insert: `@${s}.`, label: `@${s}`, detail: t(`builder.scope_hint.${s}`) })
    } else {
      let members: { insert: string; label: string; detail: string }[] = []
      if (scopeName === 'user') members = Object.entries(USER_ATTRIBUTES).map(([k, v]) => ({ insert: k, label: k, detail: v }))
      else if (scopeName === 'context') members = Object.entries(CONTEXT_ATTRIBUTES).map(([k, v]) => ({ insert: k, label: k, detail: v }))
      else if (scopeName === 'record') members = [...fieldKeys(false), ...Object.entries(RECORD_ATTRIBUTES).map(([k, v]) => ({ insert: k, label: k, detail: v }))]
      else if (scopeName === 'old' || scopeName === 'parent') members = fieldKeys(false)
      else if (scopeName === 'row') members = fieldKeys(true)
      tokenStart = caret - partial.length
      out.push(...members.filter((x) => x.insert.startsWith(partial)))
    }
  } else {
    const lower = token.toLowerCase()
    out.push(...fieldKeys(false).filter((x) => x.insert.startsWith(lower)))
    if (props.scope.rows) out.push(...fieldKeys(true).filter((x) => x.insert.startsWith(lower)))
    out.push(...props.scope.repeaters.filter((r) => r.key.startsWith(lower)).map((r) => ({ insert: r.key, label: r.key, detail: 'list<record>' })))
    out.push(
      ...Object.values(FUNCTION_GROUPS)
        .flat()
        .filter((fn) => fn.startsWith(lower))
        .map((fn) => ({ insert: `${fn}(`, label: fn, detail: signature(fn) })),
    )
  }
  const exact = out.length === 1 && out[0]!.insert === token
  suggestions.value = exact ? [] : out.slice(0, 8)
  active.value = 0
}

function accept(i: number): void {
  const s = suggestions.value[i]
  const el = element()
  if (!s || !el) return
  const caret = el.selectionStart ?? text.value.length
  text.value = text.value.slice(0, tokenStart) + s.insert + text.value.slice(caret)
  const pos = tokenStart + s.insert.length
  suggestions.value = []
  requestAnimationFrame(() => {
    el.focus()
    el.setSelectionRange(pos, pos)
  })
  onInput()
}

function onKeydown(e: KeyboardEvent): void {
  if (suggestions.value.length === 0) return
  if (e.key === 'ArrowDown') {
    active.value = (active.value + 1) % suggestions.value.length
    e.preventDefault()
  } else if (e.key === 'ArrowUp') {
    active.value = (active.value - 1 + suggestions.value.length) % suggestions.value.length
    e.preventDefault()
  } else if (e.key === 'Enter' || e.key === 'Tab') {
    accept(active.value)
    e.preventDefault()
  } else if (e.key === 'Escape') {
    suggestions.value = []
    e.stopPropagation()
  }
}

function insertText(snippet: string): void {
  const el = element()
  const caret = el?.selectionStart ?? text.value.length
  text.value = text.value.slice(0, caret) + snippet + text.value.slice(el?.selectionEnd ?? caret)
  requestAnimationFrame(() => {
    el?.focus()
    el?.setSelectionRange(caret + snippet.length, caret + snippet.length)
  })
  onInput()
}

const functionsPanel = ref<InstanceType<typeof Popover> | null>(null)
const testPanel = ref(false)

const status = computed(() => {
  if (text.value.trim() === '') return { severity: 'muted', text: t('builder.formula.empty') }
  if (local.value && !local.value.ok) return { severity: 'error', text: `${t(`builder.static_error.${local.value.code.toLowerCase()}`)}: ${local.value.message}` }
  if (server.value.state === 'checking') return { severity: 'muted', text: t('builder.formula.checking') }
  if (server.value.state === 'error' && server.value.error)
    return {
      severity: 'error',
      text: `${t(`builder.static_error.${server.value.error.code.toLowerCase()}`)}: ${server.value.error.message}${server.value.stale ? ` — ${t('builder.formula.kept_previous')}` : ''}`,
    }
  if (server.value.state === 'ok') return { severity: 'ok', text: t('builder.formula.valid', { type: server.value.type }) }
  if (local.value?.ok) return { severity: 'ok', text: t('builder.formula.valid_local', { type: local.value.type }) }
  return { severity: 'muted', text: '' }
})
</script>

<template>
  <div class="flex flex-col gap-1 min-w-0">
    <label v-if="label" :for="uid" class="text-sm font-medium">{{ label }}</label>
    <div class="relative">
      <Textarea
        :id="uid"
        ref="textarea"
        v-model="text"
        :rows="compact ? 1 : 3"
        auto-resize
        dir="ltr"
        spellcheck="false"
        autocomplete="off"
        class="w-full font-mono text-sm"
        :invalid="status.severity === 'error'"
        :placeholder="t('builder.formula.placeholder')"
        role="combobox"
        :aria-expanded="suggestions.length > 0"
        :aria-controls="`${uid}-list`"
        :aria-describedby="`${uid}-status`"
        @input="onInput"
        @keydown="onKeydown"
        @click="updateSuggestions"
        @blur="suggestions = []"
      />
      <ul v-if="suggestions.length" :id="`${uid}-list`" role="listbox" class="absolute z-20 inset-x-0 top-full mt-1 rounded-md border border-line bg-card shadow-lg max-h-60 overflow-auto" dir="ltr">
        <li
          v-for="(s, i) in suggestions"
          :key="s.insert + i"
          role="option"
          :aria-selected="i === active"
          :class="['px-2 py-1 cursor-pointer flex justify-between gap-3 text-sm', i === active ? 'bg-primary-subtle' : '']"
          @mousedown.prevent="accept(i)"
        >
          <span class="font-mono">{{ s.label }}</span
          ><span class="text-xs text-muted-color truncate">{{ s.detail }}</span>
        </li>
      </ul>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <span
        :id="`${uid}-status`"
        :class="['text-xs flex-1 min-w-0', status.severity === 'error' ? 'text-danger' : status.severity === 'ok' ? 'text-success' : 'text-muted-color']"
        aria-live="polite"
        >{{ status.text }}</span
      >
      <Button size="small" text icon="pi pi-book" :label="t('builder.formula.functions')" @click="(e: Event) => functionsPanel?.toggle(e)" />
      <Button v-if="!compact || model" size="small" text icon="pi pi-play" :label="t('builder.formula.test')" :disabled="!model" @click="testPanel = !testPanel" />
    </div>
    <TestEvaluate v-if="testPanel && model" :ast="model" :scope="scope" />
    <Popover ref="functionsPanel">
      <div class="w-[28rem] max-w-[90vw] max-h-96 overflow-auto flex flex-col gap-3" dir="ltr">
        <section v-for="(fns, group) in FUNCTION_GROUPS" :key="group">
          <h4 class="text-sm font-semibold mb-1" :dir="builder.locales.find((l) => l.code === builder.locale)?.direction">{{ t(`builder.fn_group.${group}`) }}</h4>
          <ul class="flex flex-col">
            <li v-for="fn in fns" :key="fn">
              <button type="button" class="w-full text-start px-1 py-0.5 rounded hover:bg-subtle font-mono text-xs" @click="insertText(`${fn}(`)">
                {{ signature(fn) }}
              </button>
            </li>
          </ul>
        </section>
        <section>
          <h4 class="text-sm font-semibold mb-1">{{ t('builder.formula.references') }}</h4>
          <p class="text-xs text-muted-color" :dir="builder.locales.find((l) => l.code === builder.locale)?.direction">{{ t('builder.formula.references_hint') }}</p>
        </section>
      </div>
    </Popover>
  </div>
</template>
