<script setup lang="ts">
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRenderer } from '../context'
import { pickText } from '../i18nText'
import { fieldState, type RowRef } from '../rules'
import type { ClientField } from '../types'
import { prop } from './props'

/**
 * Buttons placed on the form: `button` and `image_button` run the field's
 * configured events, `submit` submits the enclosing form, `reset` restores
 * the values the form opened with.
 */
const props = defineProps<{ field: ClientField; row: RowRef | null }>()
const ctx = useRenderer()
const { t } = useI18n()
const label = computed(
  () => pickText(props.field.i18n.label, ctx.locale.value) ?? (props.field.type === 'submit' ? t('common.save') : props.field.type === 'reset' ? t('common.reset') : props.field.key),
)
const state = computed(() => fieldState(ctx.index.value, props.field, ctx.state.value, props.row, 'edit'))
const active = computed(() => ctx.mode.value === 'create' || ctx.mode.value === 'edit')
const imageSrc = computed(() => {
  const src = prop(props.field, 'src', '')
  return src && ((src.startsWith('/') && !src.startsWith('//')) || /^data:image\//i.test(src)) ? src : null
})
const severity = computed(() => (props.field.type === 'submit' ? undefined : props.field.type === 'reset' ? 'secondary' : (prop(props.field, 'severity', '') as never) || 'secondary'))

function click(): void {
  if (props.field.type === 'reset') ctx.reset()
  else if (props.field.type !== 'submit') ctx.fieldEvent(props.field, 'change', props.row)
}
</script>

<template>
  <div v-if="active" class="lcf-no-print">
    <button v-if="field.type === 'image_button' && imageSrc" type="button" class="rounded focus:outline-2 focus:outline-primary" :disabled="state.disabled" :aria-label="label" @click="click">
      <img :src="imageSrc" :alt="label" class="max-h-16" />
    </button>
    <Button
      v-else
      :type="field.type === 'submit' ? 'submit' : 'button'"
      :label="label"
      :icon="field.ui.icon ?? undefined"
      :severity="severity"
      :disabled="state.disabled"
      :data-testid="`button-${field.key}`"
      @click="click"
    />
  </div>
</template>
