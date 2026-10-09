<script setup lang="ts">
import Button from 'primevue/button'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { fileUrl, openFile } from '../api'
import { useRenderer } from '../context'

/** Attached files with names and sizes, image thumbnails, opening through short-lived signed URLs, and removal. */
const props = defineProps<{ uuids: string[]; removable?: boolean; images?: boolean }>()
const emit = defineEmits<{ remove: [uuid: string] }>()
const ctx = useRenderer()
const { t } = useI18n()
const thumbs = ref<Record<string, string>>({})
const failed = ref<Record<string, boolean>>({})

const items = computed(() => props.uuids.map((u) => ({ uuid: u, meta: ctx.files.value[u] ?? null })))
const isImage = (u: string) => props.images || (ctx.files.value[u]?.mime ?? '').startsWith('image/')

watch(
  () => props.uuids.filter(isImage).join(','),
  async () => {
    for (const u of props.uuids.filter(isImage)) {
      if (thumbs.value[u] || failed.value[u]) continue
      try {
        thumbs.value = { ...thumbs.value, [u]: await fileUrl(u) }
      } catch {
        failed.value = { ...failed.value, [u]: true }
      }
    }
  },
  { immediate: true },
)

function size(bytes: number | undefined): string {
  if (!bytes) return ''
  return bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
async function open(u: string): Promise<void> {
  try {
    await openFile(u)
  } catch {
    failed.value = { ...failed.value, [u]: true }
  }
}
</script>

<template>
  <ul class="flex flex-col gap-2">
    <li v-for="f in items" :key="f.uuid" class="flex items-center gap-3 rounded-md border border-line p-2" :data-testid="`file-${f.uuid}`">
      <img v-if="thumbs[f.uuid]" :src="thumbs[f.uuid]" :alt="f.meta?.name ?? t('runtime.file')" class="w-14 h-14 object-cover rounded" />
      <i v-else class="pi pi-file text-2xl text-muted-color" />
      <div class="flex-1 min-w-0">
        <div class="truncate" dir="auto">{{ f.meta?.name ?? t('runtime.file') }}</div>
        <div class="text-xs text-muted-color ltr-value">{{ size(f.meta?.size) }}</div>
        <div v-if="failed[f.uuid]" class="text-xs field-error">{{ t('runtime.file_unavailable') }}</div>
      </div>
      <Button type="button" icon="pi pi-download" text rounded size="small" :aria-label="t('runtime.open_file')" class="lcf-no-print" @click="open(f.uuid)" />
      <Button
        v-if="removable"
        type="button"
        icon="pi pi-times"
        text
        rounded
        size="small"
        severity="danger"
        :aria-label="t('runtime.remove_file')"
        class="lcf-no-print"
        @click="emit('remove', f.uuid)"
      />
    </li>
  </ul>
</template>
