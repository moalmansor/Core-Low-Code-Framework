<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { pick } from '../conditions/scope'
import { uniqueKey } from '../document'
import FormPicker from '../FormPicker.vue'
import { targetFields, type TargetField } from '../targets'
import type { FieldDef, FieldTypeInfo, RelationDef, RelationType } from '../types'
import { useBuilder } from '../useBuilder'
import { newUuid } from '../uuid'

/**
 * Relation binding of a lookup or of a list whose options come from another
 * form (specification §4.6): target, cardinality, on-delete rule, and the
 * target fields shown and stored.
 */
const props = defineProps<{ info: FieldTypeInfo }>()
const field = defineModel<FieldDef>('field', { required: true })
const { t } = useI18n()
const builder = useBuilder()

const relation = computed<RelationDef | undefined>(() => builder.doc?.relations.find((r) => r.uuid === field.value.relation))
const fields = ref<TargetField[]>([])
const loading = ref(false)

watch(
  () => relation.value?.target,
  async (target) => {
    fields.value = []
    if (!target) return
    loading.value = true
    try {
      fields.value = await targetFields(target)
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

const types = computed(() =>
  (['many_to_one', 'one_to_one', 'many_to_many'] as RelationType[]).map((v) => ({ value: v, label: t(`builder.relation.type_${v}`), disabled: props.info.multiple !== (v === 'many_to_many') })),
)
const deletes = computed(() => (['restrict', 'cascade', 'set_null'] as const).map((v) => ({ value: v, label: t(`builder.relation.on_delete_${v}`) })))
const fieldOptions = computed(() => fields.value.map((f) => ({ value: f.uuid, label: `${pick(f.label, builder.locale, f.key)} (${f.key})` })))

function setTarget(target: string | null | undefined): void {
  builder.mutate((d) => {
    const f = d.fields.find((x) => x.uuid === field.value.uuid)
    if (!f) return
    const existing = d.relations.find((r) => r.uuid === f.relation)
    if (!target) {
      if (existing) d.relations = d.relations.filter((r) => r.uuid !== existing.uuid)
      f.relation = null
      if (f.options && (f.options.source === 'collection' || f.options.source === 'form')) f.options.collection = null
      return
    }
    if (existing) {
      existing.target = target
      existing.display = null
      existing.value = null
    } else {
      const r: RelationDef = {
        uuid: newUuid(),
        key: uniqueKey(f.key, new Set(d.relations.map((x) => x.key))),
        type: props.info.multiple ? 'many_to_many' : 'many_to_one',
        target,
        kind: 'reference',
        onDelete: 'restrict',
        display: null,
        value: null,
        inverse: null,
      }
      d.relations.push(r)
      f.relation = r.uuid
    }
    if (f.options && (f.options.source === 'collection' || f.options.source === 'form')) f.options.collection = target
  })
}

const inverseInvalid = computed(() => !!relation.value?.inverse && !/^[a-z][a-z0-9_]{0,47}$/.test(relation.value.inverse))
</script>

<template>
  <fieldset class="flex flex-col gap-2 rounded border border-line p-2">
    <legend class="text-sm font-medium px-1">{{ t('builder.relation.title') }}</legend>
    <label class="field"
      ><span>{{ t('builder.relation.target') }}</span>
      <FormPicker :model-value="relation?.target ?? null" :exclude="null" @update:model-value="setTarget" />
    </label>
    <template v-if="relation">
      <div class="grid grid-cols-2 gap-2">
        <label class="field"
          ><span>{{ t('builder.relation.type') }}</span>
          <Select v-model="relation.type" :options="types" option-label="label" option-value="value" option-disabled="disabled" size="small" />
        </label>
        <label class="field"
          ><span>{{ t('builder.relation.on_delete') }}</span>
          <Select v-model="relation.onDelete" :options="deletes" option-label="label" option-value="value" size="small" />
        </label>
        <label class="field"
          ><span>{{ t('builder.relation.display') }}</span>
          <Select
            :model-value="relation.display ?? null"
            :options="fieldOptions"
            option-label="label"
            option-value="value"
            show-clear
            filter
            :loading="loading"
            size="small"
            @update:model-value="(v) => (relation!.display = v ?? null)"
          />
        </label>
        <label class="field"
          ><span>{{ t('builder.relation.value') }}</span>
          <Select
            :model-value="relation.value ?? null"
            :options="fieldOptions"
            option-label="label"
            option-value="value"
            show-clear
            filter
            :loading="loading"
            :placeholder="t('builder.relation.value_id')"
            size="small"
            @update:model-value="(v) => (relation!.value = v ?? null)"
          />
        </label>
        <label class="field col-span-2"
          ><span>{{ t('builder.relation.inverse') }}</span>
          <InputText
            :model-value="relation.inverse ?? ''"
            size="small"
            class="ltr-value font-mono"
            :invalid="inverseInvalid"
            maxlength="48"
            @update:model-value="(v: string | undefined) => (relation!.inverse = v ? v : null)"
          />
          <span class="text-xs text-muted-color">{{ t('builder.relation.inverse_hint') }}</span>
        </label>
      </div>
      <Message v-if="!loading && fields.length === 0" severity="warn" size="small">{{ t('builder.relation.target_unpublished') }}</Message>
      <Button size="small" text severity="danger" icon="pi pi-times" :label="t('builder.relation.remove')" class="self-start" @click="setTarget(null)" />
    </template>
  </fieldset>
</template>
