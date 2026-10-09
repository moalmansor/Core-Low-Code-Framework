<script setup lang="ts">
import Select from 'primevue/select'
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { builderApi, type FormSummary } from './api'
import { searchForms } from './targets'

/** Picks a form or collection by uuid, searching on the server. */
const props = withDefaults(defineProps<{ kind?: 'form' | 'collection' | null; exclude?: string | null; inputId?: string }>(), { kind: null, exclude: null, inputId: undefined })
const model = defineModel<string | null | undefined>()
const { t } = useI18n()
const options = ref<FormSummary[]>([])
let timer: ReturnType<typeof setTimeout> | null = null

async function load(search = ''): Promise<void> {
  const found = (await searchForms(search, props.kind)).filter((f) => f.uuid !== props.exclude)
  // Keep the selected entry visible even when the search does not return it.
  let selected = options.value.find((o) => o.uuid === model.value)
  if (!selected && model.value && !found.some((f) => f.uuid === model.value)) selected = await builderApi.form(model.value).catch(() => undefined)
  options.value = selected && !found.some((f) => f.uuid === selected.uuid) ? [selected, ...found] : found
}

function onFilter(e: { value: string }): void {
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => void load(e.value), 300)
}

onMounted(() => void load())
watch(
  () => props.kind,
  () => void load(),
)
</script>

<template>
  <Select
    v-model="model"
    :options="options"
    option-value="uuid"
    :option-label="(o: FormSummary) => `${o.name} (${o.key})`"
    filter
    show-clear
    :placeholder="t('builder.pick_form')"
    :label-id="inputId"
    size="small"
    class="w-full"
    @filter="onFilter"
  >
    <template #option="{ option }">
      <div class="flex items-center gap-2">
        <i :class="option.kind === 'collection' ? 'pi pi-table' : 'pi pi-file'" />
        <span>{{ option.name }}</span>
        <span class="text-xs text-muted-color ltr-value">{{ option.key }}</span>
        <span v-if="option.version === null" class="text-xs text-warning">{{ t('builder.unpublished') }}</span>
      </div>
    </template>
  </Select>
</template>
