<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { elementKeys, isValidKey, renameKey, toKey } from '../document'
import { pick } from '../conditions/scope'
import { useBuilder } from '../useBuilder'

/**
 * The key of a field or group: snake_case, unique among fields and groups.
 * Renaming also rewrites expression references to the old key.
 */
const props = defineProps<{ uuid: string; current: string; label?: Record<string, string>; locked?: boolean }>()
const { t } = useI18n()
const builder = useBuilder()
const text = ref(props.current)
watch(
  () => props.current,
  (v) => (text.value = v),
)

const error = computed(() => {
  if (text.value === props.current) return null
  if (!isValidKey(text.value)) return t('builder.key.invalid')
  if (builder.doc && elementKeys(builder.doc, props.uuid).has(text.value)) return t('builder.key.taken')
  return null
})

function apply(): void {
  if (error.value || text.value === props.current) return
  const key = text.value
  builder.mutate((d) => renameKey(d, props.uuid, key))
}

function suggest(): void {
  const base = pick(props.label, 'en', '') || pick(props.label, builder.locale, '')
  if (!base) return
  text.value = toKey(base)
  if (builder.doc && elementKeys(builder.doc, props.uuid).has(text.value)) {
    let n = 2
    while (elementKeys(builder.doc, props.uuid).has(`${text.value}_${n}`)) n++
    text.value = `${text.value}_${n}`
  }
  apply()
}
</script>

<template>
  <div class="field">
    <label :for="`key-${uuid}`">{{ t('builder.key.label') }}</label>
    <div class="flex gap-1">
      <InputText
        :id="`key-${uuid}`"
        v-model="text"
        size="small"
        class="flex-1 ltr-value font-mono"
        :invalid="!!error"
        :disabled="locked"
        maxlength="48"
        data-testid="prop-key"
        @blur="apply"
        @keydown.enter.prevent="apply"
      />
      <Button size="small" text icon="pi pi-sparkles" :disabled="locked" :aria-label="t('builder.key.from_label')" :title="t('builder.key.from_label')" @click="suggest" />
    </div>
    <span v-if="error" class="field-error">{{ error }}</span>
    <span v-else class="text-xs text-muted-color">{{ locked ? t('builder.key.locked') : t('builder.key.hint') }}</span>
  </div>
</template>
