<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * A secret shown once (an API token, a signing secret): it is never shown
 * again, so the dialog says so and offers to copy it.
 */
const props = defineProps<{ secret: string | null; title: string; text: string }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const copied = ref(false)
async function copy(): Promise<void> {
  if (!props.secret) return
  try {
    await navigator.clipboard.writeText(props.secret)
    copied.value = true
  } catch {
    copied.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="secret !== null"
    modal
    :header="title"
    :closable="false"
    :style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }"
    data-testid="secret-dialog"
    @update:visible="(v: boolean) => !v && emit('close')"
  >
    <div class="flex flex-col gap-3">
      <p class="m-0 text-sm">{{ text }}</p>
      <div class="flex gap-2 items-center">
        <InputText
          :model-value="secret ?? ''"
          readonly
          dir="ltr"
          class="ltr-value flex-1 min-w-0 font-mono"
          size="small"
          data-testid="secret-value"
          @focus="($event.target as HTMLInputElement).select()"
        />
        <Button :icon="copied ? 'pi pi-check' : 'pi pi-copy'" :label="copied ? t('intg.copied') : t('intg.copy')" size="small" outlined @click="copy" />
      </div>
    </div>
    <template #footer>
      <Button :label="t('intg.secret_saved')" icon="pi pi-check" data-testid="secret-done" @click="emit('close')" />
    </template>
  </Dialog>
</template>
