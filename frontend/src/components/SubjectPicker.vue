<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Select from 'primevue/select'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'

/**
 * Picks a subject: everyone, or a role, department or user by name. Used by
 * approvers, assignees, rule subjects, shares and delegates. Names are looked
 * up from `/subject-options`; the stored value is `{type, uuid}`.
 */
interface Option {
  uuid: string
  name: string
  detail?: string | null
}
const props = withDefaults(defineProps<{ types?: ('everyone' | 'role' | 'department' | 'user')[]; inputId?: string; invalid?: boolean }>(), {
  types: () => ['everyone', 'role', 'department', 'user'],
  inputId: undefined,
})
const model = defineModel<{ type: string; uuid: string | null } | null>({ default: null })
const { t } = useI18n()
const type = ref<string>(model.value?.type ?? props.types[0]!)
const picked = ref<Option | null>(null)
const suggestions = ref<Option[]>([])
const typeOptions = computed(() => props.types.map((v) => ({ value: v, label: t(`subjects.${v}`) })))

async function resolve(): Promise<void> {
  const m = model.value
  if (!m || m.type === 'everyone' || !m.uuid) {
    picked.value = null
    return
  }
  if (picked.value?.uuid === m.uuid) return
  const rows = (await get<{ data: Option[] }>('/subject-options', { type: m.type, uuids: [m.uuid] })).data
  picked.value = rows[0] ?? { uuid: m.uuid, name: m.uuid }
}
watch(
  model,
  (m) => {
    if (m) type.value = m.type
    void resolve()
  },
  { immediate: true, deep: true },
)

async function search(e: { query: string }): Promise<void> {
  suggestions.value = (await get<{ data: Option[] }>('/subject-options', { type: type.value, search: e.query })).data
}
function setType(v: string): void {
  type.value = v
  picked.value = null
  model.value = v === 'everyone' ? { type: 'everyone', uuid: null } : null
}
function setPicked(o: Option | null | string): void {
  if (typeof o === 'string') return
  picked.value = o
  model.value = o ? { type: type.value, uuid: o.uuid } : null
}
</script>

<template>
  <div class="flex gap-2 min-w-0">
    <Select
      v-if="types.length > 1"
      :model-value="type"
      :options="typeOptions"
      option-label="label"
      option-value="value"
      size="small"
      class="w-36 shrink-0"
      :aria-label="t('subjects.type')"
      @update:model-value="setType"
    />
    <AutoComplete
      v-if="type !== 'everyone'"
      :model-value="picked"
      :suggestions="suggestions"
      option-label="name"
      force-selection
      dropdown
      size="small"
      class="flex-1 min-w-0"
      :input-id="inputId"
      :invalid="invalid"
      :placeholder="t(`subjects.pick_${type}`)"
      @complete="search"
      @update:model-value="setPicked"
    >
      <template #option="{ option }">
        <div>
          {{ option.name }} <span v-if="option.detail" class="text-sm text-muted-color ltr-value">{{ option.detail }}</span>
        </div>
      </template>
    </AutoComplete>
  </div>
</template>
