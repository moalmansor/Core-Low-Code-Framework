<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBuilder } from '@/builder/useBuilder'
import { useSession } from '@/stores/session'
import { labelOf, previewsApi, viewsApi, type Path, type PathNode, type PreviewDoc } from './api'
import ErrorList from './ErrorList.vue'
import PathPicker from './PathPicker.vue'
import SaveBar from './SaveBar.vue'
import { useHashedDocument } from './useHashedDocument'

/**
 * Edit Mode reference previews (specification §4.14 "Edit Mode"): the card
 * shown when a user picks a record of this form in any lookup, and for this
 * form's own lookups an override card plus auto-fill — fields of this form
 * filled from the picked record — and whether the record opens in a drawer.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const session = useSession()
const builder = useBuilder()
type Doc = { default: PreviewDoc | null; fields: (PreviewDoc & { field: string })[] }
const doc = useHashedDocument<Doc>(
  async () => {
    const r = await previewsApi.load(props.form)
    return { value: { default: r.default, fields: r.fields }, hash: r.hash, extra: {} }
  },
  async (v, h) => {
    const r = await previewsApi.save(props.form, v, h)
    return { value: { default: r.default, fields: r.fields }, hash: r.hash, extra: {} }
  },
)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const ownTree = ref<PathNode[]>([])
const trees = reactive<Record<string, PathNode[]>>({})
onMounted(async () => {
  ownTree.value = ((await viewsApi.load(props.form).catch(() => null))?.extra.fields as PathNode[] | undefined) ?? []
})
const fieldsByKey = computed(() => new Map((builder.doc?.fields ?? []).map((f) => [f.key, f])))
/** This form's lookups (top-level fields that link to a form), keyed by field uuid. */
const lookups = computed(() =>
  ownTree.value
    .filter((n) => n.form && fieldsByKey.value.has(n.key))
    .map((n) => ({ value: fieldsByKey.value.get(n.key)!.uuid, label: labelOf(n.label, locale.value, n.key, dl.value), target: n.form!.uuid })),
)
const targetOf = (field: string) => lookups.value.find((l) => l.value === field)?.target ?? null
watch(
  () => [lookups.value, doc.value.value?.fields.map((f) => f.field)] as const,
  () => {
    for (const f of doc.value.value?.fields ?? []) {
      const target = targetOf(f.field)
      if (target && !trees[target]) void viewsApi.load(target).then((r) => (trees[target] = (r.extra.fields as PathNode[]) ?? []))
    }
  },
  { immediate: true, deep: true },
)
const ownFields = computed(() => (builder.doc?.fields ?? []).map((f) => ({ value: f.uuid, label: labelOf(f.i18n?.label ?? {}, locale.value, f.key, dl.value) })))
const targetFields = (field: string) => (trees[targetOf(field) ?? ''] ?? []).filter((n) => n.type !== 'system').map((n) => ({ value: n.key, label: labelOf(n.label, locale.value, n.key, dl.value) }))

const blank = (): PreviewDoc => ({ displayPaths: [], layout: { columns: 1 }, autofill: [], drawer: true })
const columns = [1, 2].map((v) => ({ value: v, label: String(v) }))
const err = (path: string) => doc.errors.value[path]
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="previews-editor">
    <SaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="previews" @save="doc.save()" />
    <Message severity="secondary" :closable="false" class="text-sm">{{ t('previews.hint') }}</Message>
    <template v-if="doc.value.value">
      <section class="rounded-lg border border-line p-3 flex flex-col gap-2">
        <div class="flex items-center gap-2">
          <h3 class="font-semibold flex-1">{{ t('previews.default_card') }}</h3>
          <Button v-if="!doc.value.value.default" icon="pi pi-plus" :label="t('previews.configure')" size="small" outlined data-testid="previews-default-add" @click="doc.value.value.default = blank()" />
          <Button v-else icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="doc.value.value.default = null" />
        </div>
        <template v-if="doc.value.value.default">
          <div v-for="(_, j) in doc.value.value.default.displayPaths" :key="j" class="flex items-end gap-2">
            <PathPicker v-model="doc.value.value.default.displayPaths[j]" :tree="ownTree" :default-locale="dl" class="flex-1" :invalid="!!err(`default.displayPaths.${j}`)" />
            <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="doc.value.value.default.displayPaths.splice(j, 1)" />
          </div>
          <div class="flex flex-wrap items-end gap-4">
            <Button icon="pi pi-plus" :label="t('panels.add_path')" size="small" outlined :disabled="doc.value.value.default.displayPaths.length >= 12" @click="doc.value.value.default.displayPaths.push([] as Path)" />
            <div class="field w-28">
              <label for="pv-def-cols">{{ t('panels.columns') }}</label>
              <Select v-model="doc.value.value.default.layout.columns" input-id="pv-def-cols" :options="columns" option-label="label" option-value="value" size="small" />
            </div>
            <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="doc.value.value.default.drawer" />{{ t('previews.drawer') }}</label>
          </div>
        </template>
      </section>

      <h3 class="font-semibold">{{ t('previews.lookups') }}</h3>
      <p v-if="!lookups.length" class="text-sm text-muted-color">{{ t('previews.no_lookups') }}</p>
      <section v-for="(f, i) in doc.value.value.fields" :key="i" class="rounded-lg border border-line p-3 flex flex-col gap-2" :data-testid="`preview-field-${i}`">
        <div class="flex items-end gap-2">
          <div class="field flex-1">
            <label :for="`pv-f-${i}`">{{ t('previews.lookup') }}</label>
            <Select v-model="f.field" :input-id="`pv-f-${i}`" :options="lookups" option-label="label" option-value="value" size="small" :invalid="!!err(`fields.${i}.field`)" @update:model-value="(f.displayPaths = []), (f.autofill = [])" />
          </div>
          <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="doc.value.value.fields.splice(i, 1)" />
        </div>
        <template v-if="f.field">
          <span class="text-sm font-medium">{{ t('previews.card_fields') }}</span>
          <div v-for="(_, j) in f.displayPaths" :key="j" class="flex items-end gap-2">
            <PathPicker v-model="f.displayPaths[j]" :tree="trees[targetOf(f.field) ?? ''] ?? []" :default-locale="dl" class="flex-1" :invalid="!!err(`fields.${i}.displayPaths.${j}`)" />
            <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="f.displayPaths.splice(j, 1)" />
          </div>
          <div class="flex flex-wrap items-end gap-4">
            <Button icon="pi pi-plus" :label="t('panels.add_path')" size="small" outlined :disabled="f.displayPaths.length >= 12" @click="f.displayPaths.push([] as Path)" />
            <div class="field w-28">
              <label :for="`pv-cols-${i}`">{{ t('panels.columns') }}</label>
              <Select v-model="f.layout.columns" :input-id="`pv-cols-${i}`" :options="columns" option-label="label" option-value="value" size="small" />
            </div>
            <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="f.drawer" />{{ t('previews.drawer') }}</label>
          </div>
          <span class="text-sm font-medium">{{ t('previews.autofill') }}</span>
          <div v-for="(a, j) in f.autofill" :key="j" class="grid gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] items-end">
            <div class="field">
              <label :for="`pv-af-from-${i}-${j}`">{{ t('previews.from') }}</label>
              <Select
                :model-value="a.from[0] ?? null"
                :input-id="`pv-af-from-${i}-${j}`"
                :options="targetFields(f.field)"
                option-label="label"
                option-value="value"
                size="small"
                :invalid="!!err(`fields.${i}.autofill.${j}`)"
                @update:model-value="(v) => (a.from = v ? [v] : [])"
              />
            </div>
            <div class="field">
              <label :for="`pv-af-to-${i}-${j}`">{{ t('previews.to') }}</label>
              <Select v-model="a.to" :input-id="`pv-af-to-${i}-${j}`" :options="ownFields" option-label="label" option-value="value" size="small" />
            </div>
            <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="a.overwrite" />{{ t('previews.overwrite') }}</label>
            <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="f.autofill.splice(j, 1)" />
          </div>
          <div><Button icon="pi pi-plus" :label="t('previews.add_autofill')" size="small" outlined @click="f.autofill.push({ from: [], to: '', overwrite: false })" /></div>
        </template>
      </section>
      <div>
        <Button icon="pi pi-plus" :label="t('previews.add_lookup')" size="small" outlined :disabled="!lookups.length" data-testid="previews-field-add" @click="doc.value.value.fields.push({ field: '', ...blank() })" />
      </div>
    </template>
    <ErrorList :errors="doc.errors.value" />
  </div>
</template>
