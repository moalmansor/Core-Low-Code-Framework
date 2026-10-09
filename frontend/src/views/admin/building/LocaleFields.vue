<script setup lang="ts">
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { useSession } from '@/stores/session'

/**
 * One input per enabled locale for a translatable value (text is stored per
 * locale, never as fixed Arabic and English columns). Each input takes the
 * direction of its own language.
 */
const props = defineProps<{ label: string; field: string; errors?: Record<string, string>; multiline?: boolean; idPrefix: string }>()
const model = defineModel<Record<string, string>>({ required: true })
const session = useSession()
const err = (code: string) => props.errors?.[`${props.field}.${code}`] ?? (code === session.boot?.default_locale ? props.errors?.[props.field] : undefined)
</script>

<template>
  <div v-for="l in session.boot?.locales ?? []" :key="l.code" class="field">
    <label :for="`${idPrefix}-${l.code}`">{{ label }} ({{ l.native_name }})<span v-if="l.code === session.boot?.default_locale" class="text-danger ms-1" aria-hidden="true">*</span></label>
    <Textarea v-if="multiline" :id="`${idPrefix}-${l.code}`" v-model="model[l.code]" :dir="l.direction" rows="2" auto-resize :data-testid="`${idPrefix}-${l.code}`" />
    <InputText v-else :id="`${idPrefix}-${l.code}`" v-model="model[l.code]" :dir="l.direction" :data-testid="`${idPrefix}-${l.code}`" />
    <span v-if="err(l.code)" class="field-error">{{ err(l.code) }}</span>
  </div>
</template>
