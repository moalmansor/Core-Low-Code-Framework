<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import RadioButton from 'primevue/radiobutton'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, get } from '@/api/http'
import { formatLoose, formatValue } from '@/runtime/format'
import { FormIndex } from '@/runtime/formIndex'
import { labelOf } from '@/runtime/i18nText'
import JustificationDialog from '@/runtime/JustificationDialog.vue'
import type { ClientDefinition, RecordPayload } from '@/runtime/types'
import { useJustification } from '@/runtime/useJustification'
import { dataQualityApi, recordName, type MergePreview } from './api'

/**
 * Merging duplicate records (specification §4.35, "Merge records"): the
 * records side by side, one kept (the survivor) and, for every field that
 * differs, the value it keeps. Links to the others move to the survivor; the
 * others go to the recycle bin; a justification is always given and the
 * merge is kept in an immutable history.
 */
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const justification = useJustification()
const formUuid = computed(() => String(route.params.form))
const uuids = computed(() =>
  String(route.query.records ?? '')
    .split(',')
    .filter((u) => /^[0-9a-f-]{36}$/i.test(u)),
)

const definition = ref<ClientDefinition | null>(null)
const preview = ref<MergePreview | null>(null)
const records = ref<RecordPayload[]>([])
const loadError = ref('')
const survivor = ref('')
const choices = ref<Record<string, string>>({})
const merging = ref(false)
const index = computed(() => (definition.value ? new FormIndex(definition.value) : null))
const formName = computed(() => definition.value?.name ?? '')

// Fields that differ and the person can see; the others are the same everywhere (or kept from the survivor).
const fields = computed(() => (preview.value?.differ ?? []).map((key) => index.value?.fieldByKey(key)).filter((f): f is NonNullable<typeof f> => !!f))
const byUuid = computed(() => Object.fromEntries(records.value.map((r) => [r.uuid, r])))

async function load(): Promise<void> {
  loadError.value = ''
  if (uuids.value.length < 2) {
    loadError.value = t('dq.merge_pick_two')
    return
  }
  try {
    const [d, p, ...rs] = await Promise.all([
      get<{ data: ClientDefinition }>(`/r/${formUuid.value}/definition`, { mode: 'view' }),
      dataQualityApi.mergePreview(formUuid.value, uuids.value),
      ...uuids.value.map((u) => get<{ data: RecordPayload }>(`/r/${formUuid.value}/${u}`)),
    ])
    definition.value = d.data
    preview.value = p
    records.value = rs.map((r) => r.data)
    survivor.value = survivor.value && byUuid.value[survivor.value] ? survivor.value : records.value[0].uuid
    resetChoices()
  } catch (e) {
    loadError.value = e instanceof ApiError && e.status !== 0 ? e.message : t('records.load_failed')
  }
}
onMounted(load)

/** Each field keeps the survivor's value, or the first other one when the survivor has none. */
function resetChoices(): void {
  const next: Record<string, string> = {}
  for (const f of fields.value) {
    const own = byUuid.value[survivor.value]?.values[f.key]
    const filled = records.value.find((r) => !empty(r.values[f.key]))
    next[f.key] = empty(own) && filled ? filled.uuid : survivor.value
  }
  choices.value = next
}
const empty = (v: unknown) => v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0)

function display(r: RecordPayload, key: string): string {
  const f = index.value?.fieldByKey(key)
  if (!(key in r.values)) return t('records.masked')
  const v = r.values[key]
  const text =
    f && index.value
      ? formatValue(index.value, f, v, { locale: locale.value, references: r.references, files: r.files, yes: t('runtime.yes'), no: t('runtime.no'), untitled: t('runtime.untitled_record') })
      : formatLoose(v)
  return text === '' ? '—' : text
}
const previewOf = (r: RecordPayload) => preview.value?.records.find((p) => p.uuid === r.uuid)
const titleOf = (r: RecordPayload) => {
  const p = previewOf(r)
  return p ? recordName(p, t('dq.a_record')) : (r.title ?? r.system.record_number ?? t('dq.a_record'))
}
const linksOf = (r: RecordPayload) => preview.value?.links[r.uuid] ?? 0
const others = computed(() => records.value.filter((r) => r.uuid !== survivor.value))
const moving = computed(() => others.value.reduce((n, r) => n + linksOf(r), 0))

async function merge(): Promise<void> {
  if (!preview.value || merging.value) return
  merging.value = true
  try {
    const versions = Object.fromEntries(preview.value.records.map((r) => [r.uuid, r.row_version]))
    const res = await justification.run((j) =>
      dataQualityApi.merge(formUuid.value, { survivor: survivor.value, merged: others.value.map((r) => r.uuid), choices: choices.value, versions, justification: j }),
    )
    if (res === null) return
    toast.add({ severity: 'success', summary: t('dq.merged', { n: res.merged }), life: 5000 })
    await router.push({ name: 'records.view', params: { form: formUuid.value, record: res.uuid } })
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) {
      toast.add({ severity: 'warn', summary: e.message || t('dq.changed_since'), life: 8000 })
      await load()
    } else {
      toast.add({ severity: 'error', summary: e instanceof ApiError ? e.message : t('dq.merge_failed'), life: 8000 })
    }
  } finally {
    merging.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="merge-view">
    <header>
      <RouterLink :to="{ name: 'records.list', params: { form: formUuid } }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
        <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ formName || t('records.title') }}
      </RouterLink>
      <h1 class="page-title !mb-1">{{ t('dq.merge_title') }}</h1>
      <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('dq.merge_hint') }}</p>
    </header>

    <Message v-if="loadError" severity="error" :closable="false" data-testid="merge-error">{{ loadError }}</Message>
    <div v-else-if="!preview || !definition" class="flex justify-center p-6"><ProgressSpinner style="width: 2.5rem; height: 2.5rem" /></div>
    <template v-else>
      <div class="overflow-x-auto rounded-xl border border-line bg-card">
        <table class="merge-table">
          <thead>
            <tr>
              <th scope="col" class="merge-field">{{ t('dq.field') }}</th>
              <th v-for="r in records" :key="r.uuid" scope="col" :class="{ 'is-survivor': r.uuid === survivor }">
                <label class="flex items-start gap-2 cursor-pointer">
                  <RadioButton v-model="survivor" :value="r.uuid" :input-id="`keep-${r.uuid}`" name="survivor" :data-testid="`merge-keep-${r.uuid}`" @change="resetChoices" />
                  <span class="flex flex-col min-w-0">
                    <span class="font-medium" dir="auto">{{ titleOf(r) }}</span>
                    <span class="text-xs text-muted-color font-normal"> {{ r.uuid === survivor ? t('dq.survivor') : t('dq.merged_away') }} · {{ t('dq.links', { n: linksOf(r) }) }} </span>
                  </span>
                </label>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in fields" :key="f.key" :data-testid="`merge-row-${f.key}`">
              <th scope="row" class="merge-field">{{ labelOf(f.i18n.label, locale, f.key) }}</th>
              <td v-for="r in records" :key="r.uuid" :class="{ 'is-chosen': choices[f.key] === r.uuid }">
                <label class="flex items-start gap-2 cursor-pointer">
                  <RadioButton v-model="choices[f.key]" :value="r.uuid" :name="`choice-${f.key}`" :data-testid="`merge-choice-${f.key}-${r.uuid}`" />
                  <span class="min-w-0 break-words" dir="auto">{{ display(r, f.key) }}</span>
                </label>
              </td>
            </tr>
            <tr v-if="!fields.length">
              <td :colspan="records.length + 1" class="text-sm text-muted-color">{{ t('dq.nothing_differs') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="m-0 text-sm text-muted-color">{{ t('dq.same_fields') }}</p>

      <Message severity="info" :closable="false">
        {{ t('dq.merge_outcome', { n: others.length, links: moving }) }}
      </Message>
      <div class="flex gap-2">
        <Button icon="pi pi-clone" :label="t('dq.merge_n', { n: records.length })" :loading="merging" data-testid="merge-submit" @click="merge" />
        <Button :label="t('common.cancel')" severity="secondary" outlined @click="router.push({ name: 'records.list', params: { form: formUuid } })" />
      </div>
    </template>
    <JustificationDialog :prompt="justification.prompt.value" :errors="justification.errors.value" :busy="justification.busy.value" @submit="justification.submit" @cancel="justification.cancel" />
  </div>
</template>

<style scoped>
.merge-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.merge-table th,
.merge-table td {
  padding: 0.625rem 0.875rem;
  text-align: start;
  vertical-align: top;
  border-bottom: 1px solid var(--color-line);
  min-width: 12rem;
}
.merge-table .merge-field {
  min-width: 10rem;
  font-weight: 500;
  color: var(--color-muted);
}
.merge-table thead th {
  background: var(--color-subtle);
}
.merge-table .is-survivor {
  background: var(--color-primary-subtle);
}
.merge-table .is-chosen {
  background: var(--color-success-subtle);
}
</style>
