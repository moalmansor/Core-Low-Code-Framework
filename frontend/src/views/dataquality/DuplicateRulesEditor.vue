<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBuilder } from '@/builder/useBuilder'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { formatDatetime } from '@/runtime/format'
import { useSession } from '@/stores/session'
import I18nInput from '@/builder/I18nInput.vue'
import { errorText } from '@/views/admin/building/shared'
import { labelOf, newUuid } from '@/views/admin/formconfig/api'
import ErrorList from '@/views/admin/formconfig/ErrorList.vue'
import { useHashedDocument } from '@/views/admin/formconfig/useHashedDocument'
import { dataQualityApi, duplicateRulesApi, recordName, type DuplicateRuleDoc, type MatchFieldDoc, type SweepResult } from './api'
import RepairPanel from './RepairPanel.vue'

/**
 * A form's data quality (specification §4.35): duplicate rules — which
 * fields make two records the same (exactly, ignoring case, spacing and
 * Arabic letter forms, or similar enough), whether a match warns or blocks
 * the save, and on create, edit or both — and a sweep of the existing
 * records for each rule, whose groups open in the merge screen. With "Repair
 * data", the broken-link and validation scans follow.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const builder = useBuilder()
const session = useSession()
const doc = useHashedDocument<DuplicateRuleDoc[]>(
  () => duplicateRulesApi.load(props.form),
  (v, h) => duplicateRulesApi.save(props.form, v, h),
)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const published = computed(() => doc.extra.value.published !== false)
const lastSweeps = computed(() => (doc.extra.value.sweeps ?? {}) as Record<string, { at: string | null; operation: string | null }>)

// Fields a rule can compare: stored main-form values of a comparable kind (the server checks the same).
const COMPARABLE = ['string', 'text', 'phone', 'number', 'int', 'decimal', 'date', 'datetime', 'choice', 'lookup', 'user', 'department', 'currency']
const TEXT = ['string', 'text', 'phone']
const groupOf = (uuid: string | null | undefined) => (builder.doc?.groups ?? []).find((g) => g.uuid === uuid)
const storageOf = (type: string) => builder.catalog.fields.find((c) => c.key === type)?.storage ?? ''
const fieldLabel = (f: { key: string; i18n?: { label?: Record<string, string> } }) => labelOf(f.i18n?.label ?? {}, locale.value, f.key, dl.value)
const fields = computed(() =>
  (builder.doc?.fields ?? [])
    .filter((f) => groupOf(f.group)?.type !== 'repeater' && COMPARABLE.includes(storageOf(f.type)))
    .map((f) => ({ value: f.uuid, label: fieldLabel(f), text: TEXT.includes(storageOf(f.type)) })),
)
const isText = (uuid: string) => fields.value.find((f) => f.value === uuid)?.text ?? false
const nameOf = (uuid: string) => fields.value.find((f) => f.value === uuid)?.label ?? t('dq.unknown_field')

const methods = (m: MatchFieldDoc) => (['exact', 'normalized', 'fuzzy'] as const).filter((v) => v === 'exact' || isText(m.field)).map((v) => ({ value: v, label: t(`dq.method.${v}`) }))
const modes = computed(() => (['all', 'any'] as const).map((v) => ({ value: v, label: t(`dq.mode.${v}`) })))
const actions = computed(() => (['warn', 'block'] as const).map((v) => ({ value: v, label: t(`dq.action.${v}`) })))
const appliesOn = computed(() => (['both', 'create', 'update'] as const).map((v) => ({ value: v, label: t(`dq.applies.${v}`) })))
const err = (path: string) => doc.errors.value[path]

function add(): void {
  doc.value.value?.push({
    uuid: newUuid(),
    i18n: { name: {}, message: {} },
    fields: fields.value.length ? [{ field: fields.value[0].value, method: fields.value[0].text ? 'normalized' : 'exact', threshold: null }] : [],
    mode: 'all',
    action: 'warn',
    appliesOn: 'both',
    active: true,
  })
}
function addField(r: DuplicateRuleDoc): void {
  const next = fields.value.find((f) => !r.fields.some((m) => m.field === f.value)) ?? fields.value[0]
  if (next) r.fields.push({ field: next.value, method: next.text ? 'normalized' : 'exact', threshold: null })
}
function onField(m: MatchFieldDoc): void {
  if (!isText(m.field)) {
    m.method = 'exact'
    m.threshold = null
  }
}
function onMethod(m: MatchFieldDoc): void {
  m.threshold = m.method === 'fuzzy' ? (m.threshold ?? 0.85) : null
}
const ruleTitle = (r: DuplicateRuleDoc) => labelOf(r.i18n.name, locale.value, '', dl.value) || t('dq.untitled_rule')
const ruleSummary = (r: DuplicateRuleDoc) => r.fields.map((m) => nameOf(m.field)).join(r.mode === 'all' ? ' + ' : ` ${t('dq.or')} `)

// Sweeps: one per saved rule, polled until the background job finishes.
const sweeps = ref<Record<string, SweepResult>>({})
const sweepError = ref<Record<string, string>>({})
let timer: ReturnType<typeof setTimeout> | null = null
onBeforeUnmount(() => timer && clearTimeout(timer))
const saved = (r: DuplicateRuleDoc) => !doc.dirty.value && (doc.extra.value.rules as DuplicateRuleDoc[] | undefined)?.some((x) => x.uuid === r.uuid)
const running = (uuid: string) => ['queued', 'running'].includes(sweeps.value[uuid]?.status ?? '')

async function sweep(r: DuplicateRuleDoc): Promise<void> {
  delete sweepError.value[r.uuid]
  try {
    sweeps.value[r.uuid] = await dataQualityApi.sweep(r.uuid)
    poll(r.uuid)
  } catch (e) {
    sweepError.value[r.uuid] = errorText(e, t('dq.sweep_failed'))
  }
}
function poll(rule: string): void {
  const s = sweeps.value[rule]
  if (!s || !['queued', 'running'].includes(s.status)) return
  timer = setTimeout(async () => {
    try {
      sweeps.value[rule] = await dataQualityApi.sweepResult(s.uuid)
    } catch (e) {
      sweepError.value[rule] = errorText(e, t('dq.sweep_failed'))
      return
    }
    poll(rule)
  }, 1500)
}
async function showLast(rule: string, operation: string): Promise<void> {
  try {
    sweeps.value[rule] = await dataQualityApi.sweepResult(operation)
    poll(rule)
  } catch (e) {
    sweepError.value[rule] = errorText(e, t('dq.sweep_failed'))
  }
}
const mergeLink = (group: { uuid: string }[]) => ({
  name: 'records.merge',
  params: { form: props.form },
  query: {
    records: group
      .slice(0, 11)
      .map((g) => g.uuid)
      .join(','),
  },
})
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="dq-editor">
    <TabIntro :title="t('formconfig.tab.data_quality')" :text="t('dq.hint')" />
    <EmptyState v-if="!published" icon="pi pi-lock" :title="t('dq.unpublished_title')" :description="t('dq.publish_first')" testid="dq-unpublished" />

    <div v-if="doc.value.value" class="rounded-xl border border-line bg-card px-5">
      <ConfigSection id="dq-rules" :title="t('dq.rules')" :description="t('dq.rules_desc')" :count="doc.value.value.length">
        <EmptyState v-if="!doc.value.value.length" icon="pi pi-clone" :title="t('dq.empty_title')" :description="t('dq.empty_text')" testid="dq-empty">
          <Button icon="pi pi-plus" :label="t('dq.add')" size="small" outlined data-testid="dq-add" :disabled="!fields.length" @click="add" />
        </EmptyState>
        <template v-else>
          <ConfigItem
            v-for="(r, i) in doc.value.value"
            :key="r.uuid"
            :title="ruleTitle(r)"
            :subtitle="ruleSummary(r)"
            :index="i"
            :count="doc.value.value.length"
            :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`rules.${i}.`))"
            :testid="`dq-rule-${i}`"
            @remove="doc.value.value!.splice(i, 1)"
          >
            <template #badges>
              <Tag :value="t(`dq.action.${r.action}`)" :severity="r.action === 'block' ? 'danger' : 'warn'" />
              <Tag v-if="!r.active" :value="t('dq.inactive')" severity="secondary" />
            </template>
            <I18nInput v-model="r.i18n.name" class="w-field-md" :label="t('dq.name')" :maxlength="120" :invalid="!!err(`rules.${i}.i18n.name.${dl}`)" :data-testid="`dq-name-${i}`" />

            <div class="flex flex-col gap-2">
              <h4 class="m-0 text-sm font-semibold">{{ t('dq.match_on') }}</h4>
              <div v-for="(m, j) in r.fields" :key="j" class="cfg-row items-end" :data-testid="`dq-field-${i}-${j}`">
                <ConfigField :label="t('dq.field')" :for="`dq-f-${i}-${j}`" width="md" :error="err(`rules.${i}.fields.${j}.field`)">
                  <Select v-model="m.field" :input-id="`dq-f-${i}-${j}`" :options="fields" option-label="label" option-value="value" filter size="small" @change="onField(m)" />
                </ConfigField>
                <ConfigField :label="t('dq.compare')" :for="`dq-m-${i}-${j}`" width="md" :hint="t(`dq.method_hint.${m.method}`)" :error="err(`rules.${i}.fields.${j}.method`)">
                  <Select v-model="m.method" :input-id="`dq-m-${i}-${j}`" :options="methods(m)" option-label="label" option-value="value" size="small" @change="onMethod(m)" />
                </ConfigField>
                <ConfigField v-if="m.method === 'fuzzy'" :label="t('dq.threshold')" :for="`dq-t-${i}-${j}`" width="xs" :hint="t('dq.threshold_hint')" :error="err(`rules.${i}.fields.${j}.threshold`)">
                  <InputNumber v-model="m.threshold" :input-id="`dq-t-${i}-${j}`" :min="0.5" :max="1" :step="0.05" :min-fraction-digits="2" :max-fraction-digits="2" show-buttons size="small" />
                </ConfigField>
                <Button
                  icon="pi pi-times"
                  text
                  rounded
                  size="small"
                  severity="secondary"
                  :aria-label="t('dq.remove_field')"
                  :disabled="r.fields.length === 1"
                  class="mb-1"
                  @click="r.fields.splice(j, 1)"
                />
              </div>
              <small v-if="err(`rules.${i}.fields`)" class="field-error">{{ err(`rules.${i}.fields`) }}</small>
              <Button icon="pi pi-plus" :label="t('dq.add_field')" size="small" text class="self-start" :data-testid="`dq-add-field-${i}`" @click="addField(r)" />
            </div>

            <ConfigField v-if="r.fields.length > 1" :label="t('dq.mode_label')" :for="`dq-mode-${i}`" width="md">
              <Select v-model="r.mode" :input-id="`dq-mode-${i}`" :options="modes" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <h4 class="m-0 text-sm font-semibold">{{ t('dq.what_happens') }}</h4>
            <div class="cfg-row">
              <ConfigField :label="t('dq.action_label')" :for="`dq-act-${i}`" width="md" :hint="t(`dq.action_hint.${r.action}`)">
                <Select v-model="r.action" :input-id="`dq-act-${i}`" :options="actions" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <ConfigField :label="t('dq.applies_label')" :for="`dq-on-${i}`" width="md">
                <Select v-model="r.appliesOn" :input-id="`dq-on-${i}`" :options="appliesOn" option-label="label" option-value="value" size="small" />
              </ConfigField>
            </div>
            <I18nInput v-model="r.i18n.message" class="w-field-lg" multiline :rows="2" :label="t('dq.message')" :maxlength="500" :data-testid="`dq-msg-${i}`" />
            <SettingSwitch :id="`dq-active-${i}`" v-model="r.active" :label="t('dq.active')" :description="t('dq.active_desc')" />
          </ConfigItem>
          <Button icon="pi pi-plus" :label="t('dq.add')" size="small" outlined class="self-start" data-testid="dq-add" :disabled="!fields.length" @click="add" />
        </template>
      </ConfigSection>
    </div>

    <div v-if="published && doc.value.value" class="rounded-xl border border-line bg-card px-5" data-testid="dq-checks">
      <ConfigSection id="dq-find" :title="t('dq.find_title')" :description="t('dq.find_desc')">
        <EmptyState v-if="!doc.value.value.length" icon="pi pi-search" :title="t('dq.find_empty_title')" :description="t('dq.find_empty_text')" />
        <template v-for="(r, i) in doc.value.value" :key="r.uuid">
          <div class="dq-sweep" :data-testid="`dq-sweep-${i}`">
            <h4 class="m-0 text-sm font-semibold" dir="auto">{{ ruleTitle(r) }}</h4>
            <div class="flex flex-wrap items-center gap-2">
              <Button
                icon="pi pi-search"
                :label="t('dq.sweep')"
                size="small"
                outlined
                :loading="running(r.uuid)"
                :disabled="!saved(r) || !published || running(r.uuid)"
                :data-testid="`dq-sweep-run-${i}`"
                @click="sweep(r)"
              />
              <span v-if="!saved(r)" class="text-sm text-muted-color">{{ t('dq.save_to_sweep') }}</span>
              <template v-else-if="!sweeps[r.uuid] && lastSweeps[r.uuid]?.operation">
                <span class="text-sm text-muted-color">{{ t('dq.last_sweep', { at: formatDatetime(lastSweeps[r.uuid].at!, locale) }) }}</span>
                <Button :label="t('dq.show_results')" size="small" text @click="showLast(r.uuid, lastSweeps[r.uuid].operation!)" />
              </template>
            </div>
            <Message v-if="sweepError[r.uuid]" severity="error" :closable="false">{{ sweepError[r.uuid] }}</Message>
            <template v-if="sweeps[r.uuid]?.status === 'completed'">
              <p class="m-0 text-sm" :data-testid="`dq-sweep-count-${i}`">{{ t('dq.groups_found', { n: sweeps[r.uuid].groups.length }) }}</p>
              <ul v-if="sweeps[r.uuid].groups.length" class="dq-groups">
                <li v-for="(g, k) in sweeps[r.uuid].groups" :key="k" class="dq-group" :data-testid="`dq-group-${i}-${k}`">
                  <div class="min-w-0 flex-1 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                    <RouterLink v-for="m in g" :key="m.uuid" :to="{ name: 'records.view', params: { form, record: m.uuid } }" class="text-primary" dir="auto">{{
                      recordName(m, t('dq.a_record'))
                    }}</RouterLink>
                  </div>
                  <RouterLink v-if="session.can('system.merge_records')" :to="mergeLink(g)" class="text-sm text-primary shrink-0" :data-testid="`dq-merge-${i}-${k}`">
                    <i class="pi pi-clone me-1" aria-hidden="true" />{{ t('dq.merge') }}
                  </RouterLink>
                </li>
              </ul>
              <p v-if="sweeps[r.uuid].hidden" class="m-0 text-xs text-muted-color">{{ t('dq.hidden_records', { n: sweeps[r.uuid].hidden }) }}</p>
            </template>
            <Message v-else-if="sweeps[r.uuid]?.status === 'failed'" severity="error" :closable="false">{{ t('dq.sweep_failed') }}</Message>
          </div>
        </template>
      </ConfigSection>
    </div>
    <RepairPanel v-if="session.can('system.repair_data') && published" :form="form" />

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="dq" @save="doc.save()" @discard="doc.discard()" />
  </div>
</template>

<style scoped>
.dq-sweep {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.dq-sweep + .dq-sweep {
  padding-top: 0.75rem;
  border-top: 1px solid var(--color-line);
}
.dq-groups {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.dq-group {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  background: var(--color-subtle);
}
</style>
