<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRenderer } from '../context'
import { pickText } from '../i18nText'
import SafeHtml from '../SafeHtml'
import type { InputProps } from './props'

/** Checkbox, toggle switch, and consent checkbox with its linked terms. */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const { t } = useI18n()
const checked = computed(() => props.modelValue === true || props.modelValue === 1 || props.modelValue === '1')
const label = computed(() => pickText(props.field.i18n.label, ctx.locale.value) ?? props.field.key)
const terms = computed(() => pickText(props.field.i18n.consentTerms, ctx.locale.value))
const tooltip = computed(() => pickText(props.field.i18n.tooltip, ctx.locale.value))
const showTerms = ref(false)

function set(v: boolean): void {
  emit('update:modelValue', v)
}
function accept(): void {
  set(true)
  showTerms.value = false
}
</script>

<template>
  <div class="flex items-start gap-2">
    <ToggleSwitch
      v-if="field.type === 'toggle'"
      :input-id="inputId"
      :model-value="checked"
      :disabled="disabled"
      :invalid="invalid"
      @update:model-value="set"
      @focus="emit('focus')"
      @blur="emit('blur')"
    />
    <Checkbox v-else :input-id="inputId" :model-value="checked" binary :disabled="disabled" :invalid="invalid" @update:model-value="set" @focus="emit('focus')" @blur="emit('blur')" />
    <div class="flex flex-col">
      <label :for="inputId" class="cursor-pointer">
        {{ label }}<span v-if="required" class="text-red-500 ms-1" :aria-label="t('runtime.required')">*</span>
        <i v-if="tooltip" v-tooltip.top="tooltip" class="pi pi-info-circle ms-1 text-muted-color" tabindex="0" :aria-label="tooltip" />
      </label>
      <Button v-if="field.type === 'consent' && terms" type="button" :label="t('runtime.consent_read_terms')" link size="small" class="!p-0 self-start" @click="showTerms = true" />
    </div>
    <Dialog v-model:visible="showTerms" modal :header="label" :style="{ width: '40rem' }">
      <SafeHtml :html="terms ?? ''" />
      <template #footer>
        <Button v-if="!disabled" :label="t('runtime.consent_accept')" icon="pi pi-check" @click="accept" />
        <Button :label="t('common.cancel')" severity="secondary" @click="showTerms = false" />
      </template>
    </Dialog>
  </div>
</template>
