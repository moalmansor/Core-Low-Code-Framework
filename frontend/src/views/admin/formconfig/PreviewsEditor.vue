<script setup lang="ts">
import Button from 'primevue/button'
import Select from 'primevue/select'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBuilder } from '@/builder/useBuilder'
import { useSession } from '@/stores/session'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { labelOf, previewsApi, viewsApi, type Path, type PathNode, type PreviewDoc } from './api'
import ErrorList from './ErrorList.vue'
import PathPicker from './PathPicker.vue'
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
const lookupLabel = (field: string) => lookups.value.find((l) => l.value === field)?.label ?? t('previews.new_lookup')
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="previews-editor">
    <TabIntro :title="t('formconfig.tab.previews')" :text="t('previews.hint')" />

    <div v-if="doc.value.value" class="rounded-xl border border-line bg-card px-5">
      <ConfigSection id="preview-default" :title="t('previews.default_card')" :description="t('previews.default_card_desc')">
        <EmptyState v-if="!doc.value.value.default" icon="pi pi-id-card" :title="t('previews.no_card')" :description="t('previews.no_card_text')">
          <Button icon="pi pi-plus" :label="t('previews.configure')" size="small" outlined data-testid="previews-default-add" @click="doc.value.value.default = blank()" />
        </EmptyState>
        <template v-else>
          <span class="text-sm font-medium">{{ t('previews.card_fields') }}</span>
          <EmptyState v-if="!doc.value.value.default.displayPaths.length" icon="pi pi-list" :title="t('previews.no_fields')" :description="t('previews.no_fields_text')">
            <Button icon="pi pi-plus" :label="t('panels.add_path')" size="small" outlined @click="doc.value.value.default.displayPaths.push([] as Path)" />
          </EmptyState>
          <template v-else>
            <div v-for="(_, j) in doc.value.value.default.displayPaths" :key="j" class="cfg-row items-end">
              <ConfigField :label="t('views.path')" :for="`pv-def-path-${j}`" width="lg" :error="err(`default.displayPaths.${j}`)">
                <PathPicker v-model="doc.value.value.default.displayPaths[j]" :input-id="`pv-def-path-${j}`" :tree="ownTree" :default-locale="dl" :invalid="!!err(`default.displayPaths.${j}`)" />
              </ConfigField>
              <Button icon="pi pi-trash" text rounded severity="danger" size="small" :aria-label="t('workflow.remove')" @click="doc.value.value.default.displayPaths.splice(j, 1)" />
            </div>
            <Button
              icon="pi pi-plus"
              :label="t('panels.add_path')"
              size="small"
              outlined
              class="self-start"
              :disabled="doc.value.value.default.displayPaths.length >= 12"
              @click="doc.value.value.default.displayPaths.push([] as Path)"
            />
          </template>
          <ConfigField :label="t('panels.columns')" for="pv-def-cols" width="xs">
            <Select v-model="doc.value.value.default.layout.columns" input-id="pv-def-cols" :options="columns" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <SettingSwitch id="pv-def-drawer" v-model="doc.value.value.default.drawer" :label="t('previews.drawer')" :description="t('previews.drawer_desc')" />
          <Button icon="pi pi-trash" :label="t('previews.remove_card')" text severity="danger" size="small" class="self-start" @click="doc.value.value.default = null" />
        </template>
      </ConfigSection>

      <ConfigSection id="preview-lookups" :title="t('previews.lookups')" :count="doc.value.value.fields.length" :description="t('previews.lookups_desc')">
        <EmptyState v-if="!lookups.length" icon="pi pi-link" :title="t('previews.no_lookups')" :description="t('previews.no_lookups_text')" />
        <EmptyState v-else-if="!doc.value.value.fields.length" icon="pi pi-link" :title="t('previews.no_overrides')" :description="t('previews.no_overrides_text')">
          <Button icon="pi pi-plus" :label="t('previews.add_lookup')" size="small" outlined data-testid="previews-field-add" @click="doc.value.value.fields.push({ field: '', ...blank() })" />
        </EmptyState>
        <template v-else>
          <ConfigItem
            v-for="(f, i) in doc.value.value.fields"
            :key="i"
            :title="lookupLabel(f.field)"
            :subtitle="t('previews.summary', { fields: f.displayPaths.length, autofill: f.autofill.length })"
            :index="i"
            :count="doc.value.value.fields.length"
            :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`fields.${i}.`))"
            :testid="`preview-field-${i}`"
            @remove="doc.value.value.fields.splice(i, 1)"
          >
            <ConfigField :label="t('previews.lookup')" :for="`pv-f-${i}`" width="md" :error="err(`fields.${i}.field`)">
              <Select
                v-model="f.field"
                :input-id="`pv-f-${i}`"
                :options="lookups"
                option-label="label"
                option-value="value"
                size="small"
                :invalid="!!err(`fields.${i}.field`)"
                @update:model-value="((f.displayPaths = []), (f.autofill = []))"
              />
            </ConfigField>
            <template v-if="f.field">
              <ConfigSection id="preview-card-fields" :title="t('previews.card_fields')" :count="f.displayPaths.length">
                <EmptyState v-if="!f.displayPaths.length" icon="pi pi-list" :title="t('previews.no_fields')" :description="t('previews.no_fields_text')">
                  <Button icon="pi pi-plus" :label="t('panels.add_path')" size="small" outlined @click="f.displayPaths.push([] as Path)" />
                </EmptyState>
                <template v-else>
                  <div v-for="(_, j) in f.displayPaths" :key="j" class="cfg-row items-end">
                    <ConfigField :label="t('views.path')" :for="`pv-path-${i}-${j}`" width="lg" :error="err(`fields.${i}.displayPaths.${j}`)">
                      <PathPicker
                        v-model="f.displayPaths[j]"
                        :input-id="`pv-path-${i}-${j}`"
                        :tree="trees[targetOf(f.field) ?? ''] ?? []"
                        :default-locale="dl"
                        :invalid="!!err(`fields.${i}.displayPaths.${j}`)"
                      />
                    </ConfigField>
                    <Button icon="pi pi-trash" text rounded severity="danger" size="small" :aria-label="t('workflow.remove')" @click="f.displayPaths.splice(j, 1)" />
                  </div>
                  <Button icon="pi pi-plus" :label="t('panels.add_path')" size="small" outlined class="self-start" :disabled="f.displayPaths.length >= 12" @click="f.displayPaths.push([] as Path)" />
                </template>
                <ConfigField :label="t('panels.columns')" :for="`pv-cols-${i}`" width="xs">
                  <Select v-model="f.layout.columns" :input-id="`pv-cols-${i}`" :options="columns" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <SettingSwitch :id="`pv-drawer-${i}`" v-model="f.drawer" :label="t('previews.drawer')" :description="t('previews.drawer_desc')" />
              </ConfigSection>

              <ConfigSection id="preview-autofill" :title="t('previews.autofill')" :count="f.autofill.length" :description="t('previews.autofill_desc')">
                <EmptyState v-if="!f.autofill.length" icon="pi pi-bolt" :title="t('previews.no_autofill')" :description="t('previews.no_autofill_text')">
                  <Button icon="pi pi-plus" :label="t('previews.add_autofill')" size="small" outlined @click="f.autofill.push({ from: [], to: '', overwrite: false })" />
                </EmptyState>
                <template v-else>
                  <div v-for="(a, j) in f.autofill" :key="j" class="cfg-stack rounded-lg border border-line p-3">
                    <div class="cfg-row items-end">
                      <ConfigField :label="t('previews.from')" :for="`pv-af-from-${i}-${j}`" width="md" :error="err(`fields.${i}.autofill.${j}`)">
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
                      </ConfigField>
                      <i class="pi pi-arrow-right rtl:rotate-180 text-muted-color pb-3" aria-hidden="true" />
                      <ConfigField :label="t('previews.to')" :for="`pv-af-to-${i}-${j}`" width="md">
                        <Select v-model="a.to" :input-id="`pv-af-to-${i}-${j}`" :options="ownFields" option-label="label" option-value="value" size="small" />
                      </ConfigField>
                      <Button icon="pi pi-trash" text rounded severity="danger" size="small" :aria-label="t('workflow.remove')" @click="f.autofill.splice(j, 1)" />
                    </div>
                    <SettingSwitch :id="`pv-af-ow-${i}-${j}`" v-model="a.overwrite" :label="t('previews.overwrite')" :description="t('previews.overwrite_desc')" />
                  </div>
                  <Button icon="pi pi-plus" :label="t('previews.add_autofill')" size="small" outlined class="self-start" @click="f.autofill.push({ from: [], to: '', overwrite: false })" />
                </template>
              </ConfigSection>
            </template>
          </ConfigItem>
          <Button
            icon="pi pi-plus"
            :label="t('previews.add_lookup')"
            size="small"
            outlined
            class="self-start"
            data-testid="previews-field-add"
            @click="doc.value.value.fields.push({ field: '', ...blank() })"
          />
        </template>
      </ConfigSection>
    </div>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="previews" @save="doc.save()" @discard="doc.discard()" />
  </div>
</template>
