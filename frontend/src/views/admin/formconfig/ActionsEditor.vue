<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import I18nInput from '@/builder/I18nInput.vue'
import { useBuilder } from '@/builder/useBuilder'
import ChoiceGallery from '@/components/config/ChoiceGallery.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { useSession } from '@/stores/session'
import { actionsApi, labelOf, newUuid, workflowApi, type ActionBuiltin, type ActionDoc, type ActionPlacement, type TransitionDoc } from './api'
import ErrorList from './ErrorList.vue'
import StepListEditor from './StepListEditor.vue'
import { useHashedDocument } from './useHashedDocument'

/**
 * Actions of a form (specification §4.15): built-in operations placed where
 * people need them, and custom actions made of steps that run one after the
 * other on each chosen record. Who may run an action is set in Roles &
 * permissions; here it gets its place, availability condition, confirmation,
 * justification and run mode.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const session = useSession()
const builder = useBuilder()
const doc = useHashedDocument<ActionDoc[]>(
  () => actionsApi.load(props.form),
  (v, h) => actionsApi.save(props.form, v, h),
)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const err = (path: string) => doc.errors.value[path]
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))

const BUILTIN_PLACES: Record<ActionBuiltin, ActionPlacement[]> = {
  duplicate: ['row', 'view_page'],
  print: ['row', 'view_page'],
  bulk_update: ['bulk', 'toolbar'],
  bulk_status: ['bulk', 'toolbar'],
  bulk_delete: ['bulk', 'toolbar'],
  bulk_reassign: ['bulk', 'toolbar'],
}
const ALL_PLACES: ActionPlacement[] = ['row', 'bulk', 'toolbar', 'view_page']
const placesOf = (a: ActionDoc) => (a.kind === 'builtin' && a.builtin ? BUILTIN_PLACES[a.builtin] : ALL_PLACES)
const transitions = ref<TransitionDoc[]>([])
onMounted(async () => {
  transitions.value = (await workflowApi.load(props.form).catch(() => null))?.document.transitions ?? []
})
const transitionLabel = (x: TransitionDoc) => labelOf(x.i18n.name, locale.value, x.key, dl.value)
const transitionOptions = computed(() => [
  { value: null as string | null, label: t('act.transition_choose') },
  ...transitions.value.map((x) => ({ value: x.uuid as string | null, label: transitionLabel(x) })),
])

// What can be added, by purpose (design system §5.7: pickers that say what each thing does).
type Choice = ActionBuiltin | 'move_selected' | 'custom'
const adding = ref(false)
const choiceGroups = computed(() => [
  {
    title: t('act.choose.group_selected'),
    items: (
      [
        ['move_selected', 'pi-check-circle'],
        ['bulk_status', 'pi-arrow-right'],
        ['bulk_update', 'pi-pencil'],
        ['bulk_reassign', 'pi-user-plus'],
        ['bulk_delete', 'pi-trash'],
      ] as [Choice, string][]
    ).map(([value, icon]) => ({ value, icon, title: t(`act.choose.${value}`), description: t(`act.choose.${value}_desc`) })),
  },
  {
    title: t('act.choose.group_one'),
    items: (
      [
        ['duplicate', 'pi-copy'],
        ['print', 'pi-print'],
      ] as [Choice, string][]
    ).map(([value, icon]) => ({ value, icon, title: t(`act.choose.${value}`), description: t(`act.choose.${value}_desc`) })),
  },
  { title: t('act.choose.group_custom'), items: [{ value: 'custom' as Choice, icon: 'pi-sitemap', title: t('act.choose.custom'), description: t('act.choose.custom_desc') }] },
])
function choose(c: Choice): void {
  adding.value = false
  if (c === 'custom') {
    addCustom()
    return
  }
  if (c !== 'move_selected') return void doc.value.value?.push(baseAction('builtin', c))
  // "Approve all selected": a bulk status change that always makes one transition.
  const a = baseAction('builtin', 'bulk_status')
  const first = transitions.value.find((x) => /approv/i.test(x.key)) ?? transitions.value[0]
  a.settings = { transition: first?.uuid ?? null }
  if (first) a.i18n.label = { ...first.i18n.name }
  a.icon = 'pi-check'
  a.color = 'success'
  a.confirm = true
  doc.value.value?.push(a)
}
function setFixed(a: ActionDoc, uuid: string | null): void {
  a.settings = { transition: uuid }
}
const levels = computed(() => (['not_required', 'optional', 'mandatory'] as const).map((v) => ({ value: v, label: t(`act.level.${v}`) })))
const runModes = computed(() => (['auto', 'sync', 'queued'] as const).map((v) => ({ value: v, label: t(`act.mode.${v}`) })))
const icons = [
  'pi-bolt',
  'pi-check',
  'pi-send',
  'pi-copy',
  'pi-print',
  'pi-file',
  'pi-envelope',
  'pi-bell',
  'pi-flag',
  'pi-star',
  'pi-arrow-right',
  'pi-refresh',
  'pi-user-plus',
  'pi-link',
  'pi-trash',
]
const iconOptions = computed(() => [{ value: null, label: t('act.no_icon') }, ...icons.map((v) => ({ value: v as string | null, label: t(`act.icon_name.${v.slice(3).replace('-', '_')}`) }))])
const colors = computed(() => [
  { value: null, label: t('act.color.default') },
  ...(['primary', 'secondary', 'success', 'warn', 'danger', 'info'] as const).map((v) => ({ value: v as string | null, label: t(`act.color.${v}`) })),
])

function baseAction(kind: 'builtin' | 'custom', builtin: ActionBuiltin | null): ActionDoc {
  const n = (doc.value.value?.length ?? 0) + 1
  return {
    uuid: newUuid(),
    key: kind === 'builtin' && builtin ? `${builtin}_${n}` : `action_${n}`,
    kind,
    builtin,
    settings: builtin === 'bulk_status' ? { transition: null } : null,
    i18n: { label: kind === 'builtin' && builtin ? { [dl.value]: t(`act.builtin.${builtin}`) } : {}, confirmation: {}, success: {} },
    placements: kind === 'builtin' && builtin ? [BUILTIN_PLACES[builtin][0]!] : ['row'],
    condition: null,
    confirm: builtin === 'bulk_delete',
    runMode: 'auto',
    queueThreshold: null,
    maxRecords: null,
    justification: 'not_required',
    icon: null,
    color: builtin === 'bulk_delete' ? 'danger' : null,
    active: true,
    steps: [],
  }
}
const addCustom = () => doc.value.value?.push(baseAction('custom', null))
function togglePlace(a: ActionDoc, p: ActionPlacement, on: boolean): void {
  a.placements = on ? [...new Set([...a.placements, p])] : a.placements.filter((x) => x !== p)
}
function move<T>(list: T[], i: number, d: -1 | 1): void {
  const j = i + d
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
}

const actionTitle = (a: ActionDoc) => labelOf(a.i18n.label, locale.value, a.key, dl.value) || t('act.untitled')
const fixedName = (a: ActionDoc) => {
  const x = transitions.value.find((tr) => tr.uuid === a.settings?.transition)
  return x ? transitionLabel(x) : null
}
const kindText = (a: ActionDoc) =>
  a.kind !== 'builtin' || !a.builtin
    ? t('act.custom', { n: a.steps.length })
    : a.builtin === 'bulk_status' && fixedName(a)
      ? t('act.builtin_fixed', { name: fixedName(a)! })
      : t(`act.builtin.${a.builtin}`)
const actionSubtitle = (a: ActionDoc) => `${kindText(a)} · ${a.placements.map((p) => t(`act.place.${p}`)).join(', ')}${a.active ? '' : ' · ' + t('act.inactive')}`
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="actions-editor">
    <TabIntro :title="t('formconfig.tab.actions')" :text="t('act.hint')" />

    <EmptyState v-if="doc.value.value && !doc.value.value.length" icon="pi pi-bolt" :title="t('act.empty_title')" :description="t('act.empty_text')" testid="act-empty">
      <Button icon="pi pi-plus" :label="t('act.add')" size="small" data-testid="act-add-first" @click="adding = true" />
    </EmptyState>

    <template v-if="doc.value.value">
      <ConfigItem
        v-for="(a, i) in doc.value.value"
        :key="a.uuid"
        :title="actionTitle(a)"
        :subtitle="actionSubtitle(a)"
        :index="i"
        :count="doc.value.value.length"
        movable
        :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`actions.${i}.`))"
        :testid="`act-item-${i}`"
        @move="(d: -1 | 1) => move(doc.value.value!, i, d)"
        @remove="doc.value.value!.splice(i, 1)"
      >
        <ConfigSection :id="`act-basics-${i}`" :title="t('act.section.basics')">
          <I18nInput v-model="a.i18n.label" class="w-field-md" :label="t('act.label')" :maxlength="120" :invalid="!!err(`actions.${i}.i18n.label.${dl}`)" :data-testid="`act-label-${i}`" />
          <div class="cfg-row">
            <ConfigField :label="t('act.key')" :for="`act-key-${i}`" width="sm" :hint="t('act.key_hint')" :error="err(`actions.${i}.key`)" required>
              <InputText :id="`act-key-${i}`" v-model="a.key" class="ltr-value" dir="ltr" size="small" maxlength="48" />
            </ConfigField>
            <ConfigField :label="t('act.icon')" :for="`act-icon-${i}`" width="xs">
              <Select v-model="a.icon" :input-id="`act-icon-${i}`" :options="iconOptions" option-label="label" option-value="value" size="small">
                <template #option="{ option }">
                  <span class="inline-flex items-center gap-2"><i v-if="option.value" :class="['pi', option.value]" aria-hidden="true" />{{ option.label }}</span>
                </template>
              </Select>
            </ConfigField>
            <ConfigField :label="t('act.color_label')" :for="`act-color-${i}`" width="xs">
              <Select v-model="a.color" :input-id="`act-color-${i}`" :options="colors" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <ConfigField
            v-if="a.builtin === 'bulk_status'"
            :label="t('act.fixed_transition')"
            :for="`act-fixed-${i}`"
            width="md"
            :hint="t('act.fixed_transition_hint')"
            :error="err(`actions.${i}.settings.transition`)"
          >
            <Select
              :model-value="a.settings?.transition ?? null"
              :input-id="`act-fixed-${i}`"
              :options="transitionOptions"
              option-label="label"
              option-value="value"
              size="small"
              :data-testid="`act-fixed-${i}`"
              @update:model-value="(v: string | null) => setFixed(a, v)"
            />
          </ConfigField>
          <SettingSwitch :id="`act-active-${i}`" v-model="a.active" :label="t('act.active')" :description="t('act.active_desc')" />
        </ConfigSection>

        <ConfigSection :id="`act-where-${i}`" :title="t('act.section.where')" :description="t('act.permission_hint')">
          <ConfigField :label="t('act.placements')" :for="`act-place-${i}-${placesOf(a)[0]}`" width="lg" :error="err(`actions.${i}.placements`)">
            <div class="flex flex-wrap gap-4">
              <label v-for="p in placesOf(a)" :key="p" class="inline-flex items-center gap-2 text-sm">
                <Checkbox :input-id="`act-place-${i}-${p}`" :model-value="a.placements.includes(p)" binary @update:model-value="(v: boolean) => togglePlace(a, p, v)" />
                {{ t(`act.place.${p}`) }}
              </label>
            </div>
          </ConfigField>
          <div v-if="scope" class="flex flex-col gap-1">
            <h4 class="m-0 text-sm font-semibold">{{ t('act.available_when') }}</h4>
            <ExpressionInput v-model="a.condition" :scope="scope" expected="boolean" allow-empty :label="t('act.available_when')" />
            <p class="m-0 text-xs text-muted-color">{{ t('act.available_when_hint') }}</p>
          </div>
        </ConfigSection>

        <ConfigSection :id="`act-run-${i}`" :title="t('act.section.run')" :default-open="false">
          <SettingSwitch :id="`act-confirm-${i}`" v-model="a.confirm" :label="t('act.confirm')" :description="t('act.confirm_desc')" />
          <I18nInput v-if="a.confirm" v-model="a.i18n.confirmation" class="w-field-lg" multiline :rows="2" :label="t('act.confirmation')" :maxlength="500" />
          <I18nInput v-model="a.i18n.success" class="w-field-lg" :label="t('act.success')" :maxlength="255" />
          <div class="cfg-row">
            <ConfigField :label="t('act.justification')" :for="`act-just-${i}`" width="sm" :hint="t('act.justification_hint')">
              <Select v-model="a.justification" :input-id="`act-just-${i}`" :options="levels" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('act.run_mode')" :for="`act-mode-${i}`" width="sm" :hint="t(`act.mode_hint.${a.runMode}`)">
              <Select v-model="a.runMode" :input-id="`act-mode-${i}`" :options="runModes" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <div class="cfg-row">
            <ConfigField v-if="a.runMode === 'auto'" :label="t('act.queue_threshold')" :for="`act-thr-${i}`" width="xs" :hint="t('act.default_setting')" :error="err(`actions.${i}.queueThreshold`)">
              <InputNumber v-model="a.queueThreshold" :input-id="`act-thr-${i}`" :min="1" :max="10000" size="small" fluid />
            </ConfigField>
            <ConfigField :label="t('act.max_records')" :for="`act-max-${i}`" width="xs" :hint="t('act.default_setting')" :error="err(`actions.${i}.maxRecords`)">
              <InputNumber v-model="a.maxRecords" :input-id="`act-max-${i}`" :min="1" :max="100000" size="small" fluid />
            </ConfigField>
          </div>
        </ConfigSection>

        <ConfigSection v-if="a.kind === 'custom'" :id="`act-steps-${i}`" :title="t('act.section.steps')" :count="a.steps.length" :description="t('act.steps_hint')">
          <p v-if="err(`actions.${i}.steps`)" class="m-0 text-xs text-danger">{{ err(`actions.${i}.steps`) }}</p>
          <StepListEditor v-model:steps="a.steps" :form="form" :errors="doc.errors.value" :path="`actions.${i}.steps`" :ids="`${i}`" mode="action" />
        </ConfigSection>
      </ConfigItem>

      <Button v-if="doc.value.value.length" icon="pi pi-plus" :label="t('act.add')" size="small" outlined class="self-start" data-testid="act-add" @click="adding = true" />
    </template>

    <Dialog v-model:visible="adding" modal :header="t('act.add_title')" :style="{ width: '52rem', maxWidth: 'calc(100vw - 2rem)' }" data-testid="act-add-dialog">
      <ChoiceGallery :groups="choiceGroups" testid="act-choice" @choose="choose" />
    </Dialog>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="act" @save="doc.save" @discard="doc.discard" />
  </div>
</template>
