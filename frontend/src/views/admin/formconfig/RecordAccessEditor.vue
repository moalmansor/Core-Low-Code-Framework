<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import { useBuilder } from '@/builder/useBuilder'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { newUuid, recordRulesApi, type RecordRuleDoc } from './api'
import ErrorList from './ErrorList.vue'
import SaveBar from './SaveBar.vue'
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
async function explain(): Promise<void> {
  if (!explainUser.value?.uuid) return
  explained.value = (await get<{ data: typeof explained.value }>(`/forms/${props.form}/record-access-explain`, { user: explainUser.value.uuid })).data
}
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="record-access-editor">
    <SaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" :add-label="t('record_access.add')" testid="ra" @add="add" @save="doc.save()" />
    <Message severity="secondary" :closable="false" class="text-sm">{{ t('record_access.hint') }}</Message>
    <div v-for="(r, i) in doc.value.value ?? []" :key="r.uuid" class="rounded-lg border border-line p-3 flex flex-col gap-2">
      <div class="grid gap-2 md:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto] items-end">
        <div class="field min-w-0">
          <span class="text-sm font-medium">{{ t('record_access.who') }}</span>
          <SubjectPicker v-model="r.subject as never" />
        </div>
        <div class="field">
          <label :for="`ra-op-${i}`">{{ t('record_access.operation') }}</label>
          <Select v-model="r.operation" :input-id="`ra-op-${i}`" :options="ops" option-label="label" option-value="value" size="small" />
        </div>
        <div class="field">
          <label :for="`ra-scope-${i}`">{{ t('record_access.records') }}</label>
          <Select v-model="r.scope" :input-id="`ra-scope-${i}`" :options="scopes" option-label="label" option-value="value" size="small" />
        </div>
        <div class="field">
          <label :for="`ra-eff-${i}`">{{ t('record_access.effect_label') }}</label>
          <Select v-model="r.effect" :input-id="`ra-eff-${i}`" :options="effects" option-label="label" option-value="value" size="small" />
        </div>
        <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="doc.value.value!.splice(i, 1)" />
      </div>
      <ExpressionInput v-if="r.scope === 'custom'" v-model="r.condition" :scope="scope" expected="boolean" :allow-empty="false" :label="t('record_access.condition')" />
    </div>
    <ErrorList :errors="doc.errors.value" />

    <section class="rounded-lg border border-line p-3 flex flex-col gap-2">
      <h3 class="font-semibold text-sm">{{ t('record_access.explain') }}</h3>
      <div class="flex gap-2 items-center">
        <SubjectPicker v-model="explainUser" :types="['user']" class="flex-1" />
        <Button :label="t('record_access.explain_run')" size="small" :disabled="!explainUser?.uuid" @click="explain" />
      </div>
      <dl v-if="explained" class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <template v-for="(v, op) in explained" :key="op">
          <dt class="font-medium">{{ t(`record_access.op.${op}`) }}</dt>
          <dd>
            {{ v.scopes.length || v.custom ? [...v.scopes.map((s) => t(`record_access.scope.${s}`)), ...(v.custom ? [t('record_access.scope.custom')] : [])].join(', ') : t('record_access.scope.none') }}
            <span v-if="v.exclusions" class="text-muted-color"> · {{ t('record_access.exclusions', { n: v.exclusions }) }}</span>
          </dd>
        </template>
      </dl>
    </section>
  </div>
</template>
