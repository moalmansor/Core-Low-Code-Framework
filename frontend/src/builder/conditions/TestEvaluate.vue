<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { references } from '@/expressions'
import { builderApi, type EvaluateResult } from '../api'
import type { Ast } from '../types'
import { pick, type ExpressionScope } from './scope'
import { useBuilder } from '../useBuilder'

/**
 * Evaluates an expression on the server (`POST /expressions/evaluate`) with
 * sample values typed in here; the acting user is the signed-in admin.
 */
const props = defineProps<{ ast: Ast; scope: ExpressionScope }>()
const { t } = useI18n()
const builder = useBuilder()

const inputs = computed(() => {
  const seen = new Map<string, { scope: 'record' | 'old'; key: string; type: string; label: string }>()
  for (const ref of references(props.ast)) {
    if (!['record', 'old', 'row'].includes(ref.scope)) continue
    const key = ref.path[0]
    if (!key) continue
    const f = props.scope.fields.find((x) => x.key === key)
    if (!f) continue
    const scope = ref.scope === 'old' ? 'old' : 'record'
    seen.set(`${scope}.${key}`, { scope, key, type: f.valueType, label: pick(f.label, builder.locale, key) })
  }
  return [...seen.values()]
})

const values = reactive<Record<string, string>>({})
const mode = ref<'create' | 'edit' | 'view' | 'print'>('edit')
const today = ref('')
const result = ref<EvaluateResult | null>(null)
const failure = ref<string | null>(null)
const busy = ref(false)

function envelope(type: string, raw: string): unknown {
  if (raw === '') return { t: 'null' }
  if (type === 'boolean') return { t: 'boolean', v: raw === 'true' }
  if (type.startsWith('list<')) {
    const item = type.slice(5, -1)
    return { t: 'list', v: raw.split(',').map((s) => ({ t: item === 'any' ? 'text' : item, v: s.trim() })) }
  }
  if (type === 'datetime' && !raw.endsWith('Z')) return { t: 'datetime', v: `${raw.length === 16 ? `${raw}:00` : raw}Z` }
  if (type === 'time' && raw.length === 5) return { t: 'time', v: `${raw}:00` }
  return { t: ['number', 'date', 'datetime', 'time', 'duration'].includes(type) ? type : 'text', v: raw }
}

async function run(): Promise<void> {
  busy.value = true
  failure.value = null
  const record: Record<string, unknown> = {}
  const old: Record<string, unknown> = {}
  for (const i of inputs.value) (i.scope === 'old' ? old : record)[i.key] = envelope(i.type, values[`${i.scope}.${i.key}`] ?? '')
  try {
    result.value = await builderApi.evaluate({ ast: props.ast, record, ...(Object.keys(old).length ? { old } : {}), mode: mode.value, ...(today.value ? { today: today.value } : {}) })
  } catch (e) {
    result.value = null
    failure.value = e instanceof ApiError ? e.message : String(e)
  } finally {
    busy.value = false
  }
}

const shown = computed(() => {
  const v = result.value?.value
  if (!v) return ''
  if (v.t === 'null') return 'null'
  return `${JSON.stringify(v.v)} (${t(`builder.value_type.${v.t}`)})`
})
const modes = computed(() => (['create', 'edit', 'view', 'print'] as const).map((m) => ({ value: m, label: t(`builder.mode.${m}`) })))

function inputType(type: string): string {
  if (type === 'date') return 'date'
  if (type === 'datetime') return 'datetime-local'
  if (type === 'time') return 'time'
  return 'text'
}
</script>

<template>
  <div class="rounded-md border border-line p-2 flex flex-col gap-2 text-sm">
    <p class="text-xs text-muted-color">{{ t('builder.test.hint') }}</p>
    <div v-for="i in inputs" :key="`${i.scope}.${i.key}`" class="flex flex-wrap items-center gap-2">
      <label :for="`te-${i.scope}-${i.key}`" class="w-40 truncate">{{ i.scope === 'old' ? t('builder.test.previous', { field: i.label }) : i.label }}</label>
      <Select
        v-if="i.type === 'boolean'"
        :id="`te-${i.scope}-${i.key}`"
        v-model="values[`${i.scope}.${i.key}`]"
        :options="[
          { value: '', label: t('builder.value_type.null') },
          { value: 'true', label: t('builder.true') },
          { value: 'false', label: t('builder.false') },
        ]"
        option-label="label"
        option-value="value"
        size="small"
        class="w-40"
      />
      <InputText
        v-else
        :id="`te-${i.scope}-${i.key}`"
        v-model="values[`${i.scope}.${i.key}`]"
        :type="inputType(i.type)"
        :placeholder="i.type.startsWith('list<') ? t('builder.test.comma_separated') : i.type"
        size="small"
        class="flex-1 min-w-32"
        :class="{ 'ltr-value': i.type !== 'text' }"
      />
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <Select v-model="mode" :options="modes" option-label="label" option-value="value" size="small" :aria-label="t('builder.test.mode')" />
      <InputText v-model="today" type="date" size="small" class="ltr-value" :aria-label="t('builder.test.today')" :title="t('builder.test.today')" />
      <Button size="small" icon="pi pi-play" :label="t('builder.test.run')" :loading="busy" @click="run" />
    </div>
    <Message v-if="failure" severity="error" size="small">{{ failure }}</Message>
    <div v-if="result" class="flex flex-col gap-1" aria-live="polite">
      <div>
        {{ t('builder.test.result') }}: <span class="font-mono ltr-value">{{ shown }}</span>
      </div>
      <ul v-if="result.diagnostics.length" class="text-xs text-warning">
        <li v-for="d in result.diagnostics" :key="d.code + d.node">
          {{ t(`builder.diagnostic.${d.code.toLowerCase()}`) }} <span class="ltr-value text-muted-color">{{ d.node }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
