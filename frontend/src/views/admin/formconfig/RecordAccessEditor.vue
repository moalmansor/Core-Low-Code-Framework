<script setup lang="ts">
import Button from 'primevue/button'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import { useBuilder } from '@/builder/useBuilder'
import SubjectPicker from '@/components/SubjectPicker.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { newUuid, recordRulesApi, type RecordRuleDoc } from './api'
import ErrorList from './ErrorList.vue'
import { useHashedDocument } from './useHashedDocument'

/**
 * Record-level rules (specification §4.11 "Record level", architecture §16.6):
 * which records each subject reaches to view, edit or delete — own records,
 * own department, department tree, assigned to them, all, none, or a custom
 * condition. Without rules everyone with the form permission reaches all
 * records; a more specific subject overrides a less specific one; hard denies
 * always apply. "Explain" shows how a user's scope is decided.
 */
const props = defineProps<{ form: string }>()
const { t } = useI18n()
const builder = useBuilder()
const doc = useHashedDocument<RecordRuleDoc[]>(
  () => recordRulesApi.load(props.form),
  (v, h) => recordRulesApi.save(props.form, v, h),
)
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))
const ops = computed(() => (['all', 'view', 'edit', 'delete'] as const).map((v) => ({ value: v, label: t(`record_access.op.${v}`) })))
const scopes = computed(() => (['own', 'own_department', 'department_tree', 'assigned', 'all', 'none', 'custom'] as const).map((v) => ({ value: v, label: t(`record_access.scope.${v}`) })))
const effects = computed(() => (['allow', 'deny', 'hard_deny'] as const).map((v) => ({ value: v, label: t(`record_access.effect.${v}`) })))

function add(): void {
  doc.value.value?.push({ uuid: newUuid(), subject: { type: 'everyone', uuid: null }, operation: 'all', scope: 'own', effect: 'allow', priority: doc.value.value.length, condition: null })
}

const explainUser = ref<{ type: string; uuid: string | null } | null>(null)
const explained = ref<Record<string, { scopes: string[]; custom: number; exclusions: number; tiers: { tier: string; scopes: string[] }[] }> | null>(null)
const ruleTitle = (r: RecordRuleDoc) => `${t(`subjects.${r.subject.type}`)} · ${t(`record_access.op.${r.operation}`)} · ${t(`record_access.scope.${r.scope}`)}`
async function explain(): Promise<void> {
  if (!explainUser.value?.uuid) return
  explained.value = (await get<{ data: typeof explained.value }>(`/forms/${props.form}/record-access-explain`, { user: explainUser.value.uuid })).data
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="record-access-editor">
    <TabIntro :title="t('formconfig.tab.record_access')" :text="t('record_access.hint')" />

    <div v-if="doc.value.value" class="rounded-xl border border-line bg-card px-5">
      <ConfigSection id="ra-rules" :title="t('record_access.rules')" :count="doc.value.value.length">
        <EmptyState v-if="!doc.value.value.length" icon="pi pi-lock-open" :title="t('record_access.empty_title')" :description="t('record_access.empty_text')" testid="ra-empty">
          <Button icon="pi pi-plus" :label="t('record_access.add')" size="small" outlined data-testid="ra-add" @click="add" />
        </EmptyState>
        <template v-else>
          <ConfigItem
            v-for="(r, i) in doc.value.value"
            :key="r.uuid"
            :title="ruleTitle(r)"
            :index="i"
            :count="doc.value.value.length"
            :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`rules.${i}.`))"
            :testid="`ra-rule-${i}`"
            @remove="doc.value.value!.splice(i, 1)"
          >
            <template #badges>
              <Tag :value="t(`record_access.effect.${r.effect}`)" :severity="r.effect === 'allow' ? 'success' : 'danger'" />
            </template>
            <ConfigField :label="t('record_access.who')" width="md">
              <SubjectPicker v-model="r.subject as never" />
            </ConfigField>
            <div class="cfg-row">
              <ConfigField :label="t('record_access.operation')" :for="`ra-op-${i}`" width="sm">
                <Select v-model="r.operation" :input-id="`ra-op-${i}`" :options="ops" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <ConfigField :label="t('record_access.records')" :for="`ra-scope-${i}`" width="md">
                <Select v-model="r.scope" :input-id="`ra-scope-${i}`" :options="scopes" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <ConfigField :label="t('record_access.effect_label')" :for="`ra-eff-${i}`" width="sm" :hint="t(`record_access.effect_hint.${r.effect}`)">
                <Select v-model="r.effect" :input-id="`ra-eff-${i}`" :options="effects" option-label="label" option-value="value" size="small" />
              </ConfigField>
            </div>
            <ExpressionInput v-if="r.scope === 'custom'" v-model="r.condition" :scope="scope" expected="boolean" :allow-empty="false" :label="t('record_access.condition')" />
          </ConfigItem>
          <Button icon="pi pi-plus" :label="t('record_access.add')" size="small" outlined class="self-start" data-testid="ra-add" @click="add" />
        </template>
      </ConfigSection>

      <ConfigSection id="ra-explain" :title="t('record_access.explain')" :description="t('record_access.explain_desc')" :default-open="false">
        <div class="cfg-row items-end">
          <ConfigField :label="t('subjects.user')" width="md">
            <SubjectPicker v-model="explainUser" :types="['user']" />
          </ConfigField>
          <Button :label="t('record_access.explain_run')" size="small" outlined :disabled="!explainUser?.uuid" @click="explain" />
        </div>
        <dl v-if="explained" class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm max-w-[var(--measure)]">
          <template v-for="(v, op) in explained" :key="op">
            <dt class="font-medium">{{ t(`record_access.op.${op}`) }}</dt>
            <dd class="m-0">
              {{
                v.scopes.length || v.custom ? [...v.scopes.map((s) => t(`record_access.scope.${s}`)), ...(v.custom ? [t('record_access.scope.custom')] : [])].join(', ') : t('record_access.scope.none')
              }}
              <span v-if="v.exclusions" class="text-muted-color"> · {{ t('record_access.exclusions', { n: v.exclusions }) }}</span>
            </dd>
          </template>
        </dl>
      </ConfigSection>
    </div>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="ra" @save="doc.save()" @discard="doc.discard()" />
  </div>
</template>
