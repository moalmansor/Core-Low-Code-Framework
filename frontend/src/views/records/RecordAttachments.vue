<script setup lang="ts">
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get } from '@/api/http'
import { openFile } from '@/runtime/api'
import type { FileMeta } from '@/runtime/types'

/**
 * Every file of a record: those in its file fields and the evidence attached
 * to transitions and approval decisions.
 */
interface Extra {
  uuid: string
  name: string
  size: number
  mime: string
  source: string
}
const props = defineProps<{ form: string; record: string; files: FileMeta[] }>()
const emit = defineEmits<{ count: [n: number] }>()
const { t } = useI18n()
const toast = useToast()
const extra = ref<Extra[]>([])
watch(
  () => [props.form, props.record],
  async () => {
    extra.value = (await get<{ data: Extra[] }>(`/r/${props.form}/${props.record}/attachments`).catch(() => ({ data: [] as Extra[] }))).data
  },
  { immediate: true },
)
const all = computed(() => [...props.files.map((f) => ({ ...f, source: 'field' })), ...extra.value])
watch(all, (v) => emit('count', v.length), { immediate: true })
function size(bytes: number): string {
  return bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
async function open(uuid: string): Promise<void> {
  try {
    await openFile(uuid)
  } catch (e) {
    toast.add({ severity: 'error', summary: e instanceof ApiError ? e.message : t('runtime.file_unavailable'), life: 6000 })
  }
}
</script>

<template>
  <p v-if="all.length === 0" class="text-muted-color">{{ t('records.attachments_empty') }}</p>
  <ul v-else class="flex flex-col gap-2" data-testid="attachments">
    <li v-for="f in all" :key="f.uuid" class="flex items-center gap-3 rounded-md border border-line p-2">
      <i :class="f.mime.startsWith('image/') ? 'pi pi-image' : 'pi pi-file'" class="text-xl text-muted-color" />
      <span class="flex-1 truncate" dir="auto">{{ f.name }}</span>
      <span v-if="f.source !== 'field'" class="text-xs text-muted-color">{{ t(`records.file_source.${f.source}`) }}</span>
      <span class="text-xs text-muted-color ltr-value">{{ size(f.size) }}</span>
      <Button icon="pi pi-download" :label="t('runtime.open_file')" text size="small" @click="open(f.uuid)" />
    </li>
  </ul>
</template>
