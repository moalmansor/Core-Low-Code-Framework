<script setup lang="ts">
import Message from 'primevue/message'
import { computed, provide, reactive, ref, useSlots, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get } from '@/api/http'
import type { ClientDefinition, RecordPayload } from '../types'
import PanelItem from './PanelItem.vue'
import { byParent, PANEL_CONTEXT, type Panel } from './panels'

/**
 * A record in View Mode (specification §4.14): the panels an administrator
 * arranged — tabs, sections, the form body, related records and summaries
 * from forms that link to it, linked fields, the status timeline, comments
 * and attachments. The server sends only the panels the user may see, with
 * their data limited to what the user may read. Without panels nothing is
 * rendered and `empty` is emitted, so the page shows the plain form.
 */
const props = defineProps<{ form: string; record: RecordPayload; definition: ClientDefinition }>()
const emit = defineEmits<{ loaded: [count: number] }>()
const { t } = useI18n()
const panels = ref<Panel[] | null>(null)
const error = ref('')
async function load(): Promise<void> {
  error.value = ''
  try {
    panels.value = (await get<{ data: Panel[] }>(`/r/${props.form}/${props.record.uuid}/panels`)).data
    emit('loaded', panels.value.length)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : t('records.load_failed')
    emit('loaded', 0)
  }
}
watch(() => [props.form, props.record.uuid, props.record.row_version], load, { immediate: true })
const children = computed(() => byParent(panels.value ?? []))
provide(
  PANEL_CONTEXT,
  reactive({
    form: computed(() => props.form),
    record: computed(() => props.record),
    definition: computed(() => props.definition),
    children,
    slots: useSlots(),
  }) as never,
)
</script>

<template>
  <Message v-if="error" severity="error">{{ error }}</Message>
  <div v-else-if="panels?.length" class="flex flex-col gap-4" data-testid="record-panels">
    <PanelItem v-for="p in children.get(null) ?? []" :key="p.uuid" :panel="p" />
  </div>
</template>
