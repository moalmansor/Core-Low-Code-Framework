<script setup lang="ts">
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorText } from '../admin/building/shared'
import { personalApi } from '../access/api'

/** Pins the record to the person's home page, or unpins it (ADR-0063). */
const props = defineProps<{ form: string; record: string }>()
const { t } = useI18n()
const toast = useToast()
const pinned = ref(false)
const busy = ref(false)

onMounted(async () => {
  try {
    pinned.value = (await personalApi.pins()).some((p) => p.record === props.record)
  } catch {
    // Pins are a convenience; the button starts unpinned.
  }
})
async function toggle(): Promise<void> {
  busy.value = true
  try {
    if (pinned.value) await personalApi.unpin(props.record)
    else await personalApi.pin(props.form, props.record)
    pinned.value = !pinned.value
    toast.add({ severity: 'success', summary: pinned.value ? t('personal.pinned') : t('personal.unpinned'), life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('personal.pin_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Button
    :icon="pinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"
    :label="pinned ? t('personal.unpin') : t('personal.pin')"
    severity="secondary"
    outlined
    :loading="busy"
    data-testid="record-pin"
    @click="toggle"
  />
</template>
