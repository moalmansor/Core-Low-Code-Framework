<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { builderApi } from './api'
import I18nInput from './I18nInput.vue'
import type { I18nText } from './types'
import { useBuilder } from './useBuilder'

/** Saves the selected field, or the selected groups with their contents, to the reusable field library. */
const visible = defineModel<boolean>('visible', { required: true })
const { t } = useI18n()
const builder = useBuilder()
const toast = useToast()

const name = ref<I18nText>({})
const description = ref<I18nText>({})
const category = ref('')
const appOnly = ref(false)
const errors = ref<Record<string, string>>({})
const saving = ref(false)

const fragment = computed(() => (visible.value ? builder.selectionFragment() : null))
const kind = computed<'field' | 'group'>(() => (fragment.value && fragment.value.groups.length === 0 && fragment.value.fields.length === 1 ? 'field' : 'group'))
const invalidSelection = computed(() => !!fragment.value && fragment.value.groups.length === 0 && fragment.value.fields.length > 1)
const defaultLocale = computed(() => builder.locales.find((l) => l.is_default)?.code ?? 'en')

watch(visible, (open) => {
  if (!open) return
  errors.value = {}
  const f = fragment.value
  const first = f?.groups[0]?.i18n?.title ?? f?.fields[0]?.i18n?.label ?? {}
  name.value = { ...first }
  description.value = {}
  category.value = ''
})

async function save(): Promise<void> {
  const f = fragment.value
  if (!f || invalidSelection.value) return
  saving.value = true
  errors.value = {}
  try {
    await builderApi.saveTemplate({
      kind: kind.value,
      category: category.value || null,
      name: name.value,
      description: description.value,
      definition: { groups: f.groups, fields: f.fields.map((x) => ({ ...x, relation: null, template: null })), conditions: f.conditions, relations: [] },
      ...(appOnly.value && builder.doc?.form.application ? { application: builder.doc.form.application } : {}),
    })
    await builder.reloadTemplates()
    toast.add({ severity: 'success', summary: t('builder.library.saved'), life: 3000 })
    visible.value = false
  } catch (e) {
    if (e instanceof ApiError) errors.value = Object.keys(e.fieldErrors).length ? e.fieldErrors : { _: e.message }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="t('builder.library.save_title')" :style="{ width: '34rem' }" :breakpoints="{ '640px': '95vw' }">
    <form class="flex flex-col gap-3" @submit.prevent="save">
      <Message v-if="invalidSelection" severity="warn" size="small">{{ t('builder.library.one_field_or_groups') }}</Message>
      <Message v-else severity="secondary" size="small">{{ kind === 'field' ? t('builder.library.saving_field') : t('builder.library.saving_group') }}</Message>
      <I18nInput v-model="name" :label="t('builder.library.name')" :maxlength="255" :invalid="!!errors[`name.${defaultLocale}`]" />
      <span v-if="errors[`name.${defaultLocale}`]" class="field-error">{{ errors[`name.${defaultLocale}`] }}</span>
      <I18nInput v-model="description" :label="t('builder.library.description')" multiline :rows="2" :maxlength="2000" />
      <label class="field"
        ><span>{{ t('builder.library.category') }}</span>
        <InputText v-model="category" size="small" class="ltr-value" maxlength="64" :invalid="!!category && !/^[a-z0-9_]{1,64}$/.test(category)" placeholder="contact" />
        <span v-if="errors.category" class="field-error">{{ errors.category }}</span>
      </label>
      <label v-if="builder.doc?.form.application" class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="appOnly" />{{ t('builder.library.application_only') }}</label>
      <Message v-if="errors._ || errors.definition" severity="error" size="small">{{ errors._ ?? errors.definition }}</Message>
      <p class="text-xs text-muted-color">{{ t('builder.library.relations_note') }}</p>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="visible = false" />
        <Button type="submit" :label="t('common.save')" :loading="saving" :disabled="invalidSelection || !fragment" data-testid="template-save" />
      </div>
    </form>
  </Dialog>
</template>
