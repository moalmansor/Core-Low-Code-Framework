<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, send } from '@/api/http'
import SubjectPicker from '@/components/SubjectPicker.vue'
import type { SavedView } from './viewState'

/**
 * Saves the list as the user arranged it (columns, filters, sort, page size,
 * search) on top of a table view: as a new saved view, or over the one that
 * is open when the user owns it; optionally as their default and shared with
 * roles, departments, users or everyone.
 */
const props = defineProps<{ form: string; view: string; state: SavedView['state']; current: SavedView | null }>()
const emit = defineEmits<{ saved: []; close: [] }>()
const { t } = useI18n()
const toast = useToast()
const name = ref(props.current?.mine ? props.current.name : '')
const isDefault = ref(props.current?.mine ? props.current.is_default : false)
const shares = ref<({ type: string; uuid: string | null } | null)[]>(props.current?.mine ? props.current.shares.map((s) => ({ ...s })) : [])
const overwrite = ref(!!props.current?.mine)
const saving = ref(false)
const errors = ref<Record<string, string>>({})

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  const body = {
    name: name.value.trim(),
    state: props.state,
    is_default: isDefault.value,
    shares: shares.value.filter((s): s is { type: string; uuid: string | null } => !!s && (s.type === 'everyone' || !!s.uuid)),
  }
  try {
    if (overwrite.value && props.current?.mine) await send('patch', `/r/${props.form}/saved-views/${props.current.uuid}`, body)
    else await send('post', `/r/${props.form}/saved-views`, { ...body, view: props.view })
    toast.add({ severity: 'success', summary: t('saved_views.saved'), life: 3000 })
    emit('saved')
  } catch (e) {
    errors.value = e instanceof ApiError ? e.fieldErrors : {}
    toast.add({ severity: 'error', summary: e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('workflow.save_failed'), life: 6000 })
  } finally {
    saving.value = false
  }
}
async function remove(): Promise<void> {
  if (!props.current?.mine) return
  try {
    await send('delete', `/r/${props.form}/saved-views/${props.current.uuid}`)
    emit('saved')
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 6000 })
  }
}
</script>

<template>
  <Dialog visible modal :header="t('saved_views.save_current')" class="w-full max-w-lg" @update:visible="(v) => !v && emit('close')">
    <form class="flex flex-col gap-3" data-testid="saved-view-dialog" @submit.prevent="save">
      <label v-if="current?.mine" class="flex items-center gap-2 text-sm"><Checkbox v-model="overwrite" binary />{{ t('saved_views.update_existing', { name: current.name }) }}</label>
      <div class="field">
        <label for="sv-name">{{ t('saved_views.name') }}</label>
        <InputText id="sv-name" v-model="name" maxlength="120" :invalid="!!errors.name" data-testid="saved-view-name" />
        <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
      </div>
      <label class="flex items-center gap-2 text-sm"><Checkbox v-model="isDefault" binary />{{ t('saved_views.default') }}</label>
      <fieldset class="flex flex-col gap-2">
        <legend class="text-sm font-medium mb-1">{{ t('saved_views.share_with') }}</legend>
        <div v-for="(_, i) in shares" :key="i" class="flex items-start gap-2">
          <SubjectPicker v-model="shares[i]" class="flex-1" />
          <Button icon="pi pi-times" text size="small" :aria-label="t('workflow.remove')" @click="shares.splice(i, 1)" />
        </div>
        <div><Button icon="pi pi-plus" :label="t('saved_views.add_share')" size="small" outlined @click="shares.push(null)" /></div>
      </fieldset>
      <div class="flex gap-2">
        <Button v-if="current?.mine" type="button" icon="pi pi-trash" :label="t('saved_views.delete')" text severity="danger" @click="remove" />
        <span class="flex-1" />
        <Button type="button" :label="t('common.cancel')" text @click="emit('close')" />
        <Button type="submit" :label="t('workflow.save')" icon="pi pi-check" :loading="saving" :disabled="!name.trim()" data-testid="saved-view-save" />
      </div>
    </form>
  </Dialog>
</template>
