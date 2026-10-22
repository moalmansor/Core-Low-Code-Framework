<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import ConfigField from '@/components/config/ConfigField.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import { errorText, fieldErrors } from '../admin/building/shared'
import { searchApi, type SearchConfig } from './api'

/**
 * Global search configuration (specification §4.29): per form, whether it
 * can be found, which text fields are searched and how much each weighs,
 * what a result shows under its title, and which roles may search it.
 */
const { t } = useI18n()
const toast = useToast()
const configs = ref<SearchConfig[]>([])
const roles = ref<{ uuid: string; name: string }[]>([])
const loading = ref(true)
const open = ref<string | null>(null)
const saving = ref<string | null>(null)
const errors = ref<Record<string, Record<string, string>>>({})

onMounted(async () => {
  try {
    const [c, r] = await Promise.all([searchApi.configs(), get<{ data: { uuid: string; name: string }[] }>('/role-options').then((x) => x.data)])
    configs.value = c
    roles.value = r
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})

const labelOf = (c: SearchConfig, uuid: string) => c.available.find((f) => f.uuid === uuid)?.label ?? t('xp.field_gone')
const unused = (c: SearchConfig) => c.available.filter((f) => !c.fields.some((x) => x.field === f.uuid))
function addField(c: SearchConfig, uuid: string | null): void {
  if (uuid) c.fields.push({ field: uuid, weight: 1 })
}
async function save(c: SearchConfig): Promise<void> {
  saving.value = c.form
  errors.value = { ...errors.value, [c.form]: {} }
  try {
    const out = await searchApi.save(c.form, { enabled: c.enabled, fields: c.fields, subtitle: c.subtitle, roles: c.roles })
    Object.assign(c, out)
    toast.add({ severity: 'success', summary: t('xp.saved'), life: 3000 })
  } catch (e) {
    errors.value = { ...errors.value, [c.form]: fieldErrors(e) }
    toast.add({ severity: 'error', summary: errorText(e, t('xp.save_failed')), life: 8000 })
  } finally {
    saving.value = null
  }
}
</script>

<template>
  <div class="flex flex-col gap-4 max-w-[60rem]" data-testid="search-config">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.global_search') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('xp.search_subtitle') }}</p>
      </div>
    </header>
    <EmptyState v-if="!loading && !configs.length" icon="pi pi-search" :title="t('xp.search_empty_title')" :description="t('xp.search_empty_text')" testid="search-empty" />
    <section v-for="c in configs" :key="c.form" class="rounded-xl border border-line bg-card" :data-testid="`search-form-${c.form}`">
      <button type="button" class="w-full flex items-center gap-3 p-4 text-start" :aria-expanded="open === c.form" @click="open = open === c.form ? null : c.form">
        <span class="flex-1 font-medium" dir="auto">{{ c.form_name }}</span>
        <Tag v-if="!c.published" severity="secondary" :value="t('xp.not_published')" />
        <Tag :severity="c.enabled ? 'success' : 'secondary'" :value="c.enabled ? t('xp.searchable') : t('xp.not_searchable')" />
        <i :class="open === c.form ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" aria-hidden="true" />
      </button>
      <div v-if="open === c.form" class="flex flex-col gap-4 px-4 pb-4">
        <label class="flex items-start gap-3">
          <ToggleSwitch v-model="c.enabled" :input-id="`search-on-${c.form}`" :disabled="!c.published" />
          <span class="flex flex-col">
            <span class="text-sm font-medium">{{ t('xp.search_enabled') }}</span>
            <small class="text-muted-color">{{ t('xp.search_enabled_desc') }}</small>
          </span>
        </label>
        <div class="flex flex-col gap-2">
          <span class="text-sm font-medium">{{ t('xp.search_fields') }}</span>
          <small class="text-muted-color">{{ t('xp.search_fields_hint') }}</small>
          <div v-for="(f, i) in c.fields" :key="f.field" class="flex items-center gap-3">
            <span class="flex-1 text-sm" dir="auto">{{ labelOf(c, f.field) }}</span>
            <label class="text-xs text-muted-color" :for="`w-${c.form}-${i}`">{{ t('xp.weight') }}</label>
            <InputNumber v-model="f.weight" :input-id="`w-${c.form}-${i}`" :min="1" :max="10" show-buttons size="small" class="w-28" />
            <Button icon="pi pi-times" text size="small" severity="secondary" :aria-label="t('xp.remove_field')" @click="c.fields.splice(i, 1)" />
          </div>
          <Select
            v-if="unused(c).length"
            :options="unused(c)"
            option-label="label"
            option-value="uuid"
            size="small"
            class="w-field-md"
            :placeholder="t('xp.add_search_field')"
            :data-testid="`search-add-${c.form}`"
            @update:model-value="(v: string | null) => addField(c, v)"
          />
          <small v-if="errors[c.form]?.fields" class="text-danger">{{ errors[c.form]?.fields }}</small>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('xp.result_subtitle')" :for="`sub-${c.form}`" width="md" :hint="t('xp.result_subtitle_hint')">
            <Select v-model="c.subtitle" :input-id="`sub-${c.form}`" :options="c.available" option-label="label" option-value="uuid" show-clear size="small" :placeholder="t('xp.none')" />
          </ConfigField>
          <ConfigField :label="t('xp.search_roles')" :for="`roles-${c.form}`" width="md" :hint="t('xp.search_roles_hint')">
            <MultiSelect v-model="c.roles" :input-id="`roles-${c.form}`" :options="roles" option-label="name" option-value="uuid" filter display="chip" size="small" :placeholder="t('xp.everyone')" />
          </ConfigField>
        </div>
        <div class="flex justify-end">
          <Button icon="pi pi-check" :label="t('xp.save')" size="small" :loading="saving === c.form" :data-testid="`search-save-${c.form}`" @click="save(c)" />
        </div>
      </div>
    </section>
  </div>
</template>
