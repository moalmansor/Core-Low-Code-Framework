<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Asks for a fresh authenticator (or recovery) code before a sensitive
 * change; the server verifies it (specification §4.11 step-up).
 */
const { t } = useI18n()
const visible = ref(false)
const code = ref('')
const message = ref('')
let resolver: ((v: string | null) => void) | null = null

function ask(hint = ''): Promise<string | null> {
  code.value = ''
  message.value = hint
  visible.value = true
  return new Promise((resolve) => (resolver = resolve))
}

function close(value: string | null): void {
  visible.value = false
  resolver?.(value)
  resolver = null
}

defineExpose({ ask })
</script>

<template>
  <Dialog :visible="visible" modal :header="t('stepup.title')" :style="{ width: '24rem' }" @update:visible="(v: boolean) => !v && close(null)">
    <form class="flex flex-col gap-3" @submit.prevent="close(code.trim())">
      <p class="text-sm">{{ t('stepup.hint') }}</p>
      <p v-if="message" class="field-error">{{ message }}</p>
      <InputText v-model="code" autocomplete="one-time-code" class="ltr-value" autofocus data-testid="stepup-code" />
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="close(null)" />
        <Button type="submit" :label="t('auth.verify')" :disabled="!code.trim()" data-testid="stepup-submit" />
      </div>
    </form>
  </Dialog>
</template>
