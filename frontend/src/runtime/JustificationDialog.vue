<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { uploadFile } from './api'
import { formatLoose } from './format'
import type { JustificationPayload, JustificationPrompt } from './workflow/types'

/**
 * The justification asked for a change (specification §4.24): reason text,
 * a reason code, a note, attachments and, when the rule asks for it, a
 * summary of what changes. Saved justifications are immutable.
 */
const props = defineProps<{ prompt: JustificationPrompt | null; errors?: Record<string, string>; busy?: boolean }>()
const emit = defineEmits<{ submit: [payload: JustificationPayload]; cancel: [] }>()
const { t } = useI18n()

const text = ref('')
const code = ref<string | null>(null)
const note = ref('')
const files = ref<{ uuid: string; name: string }[]>([])
const uploading = ref(false)
const uploadError = ref('')
watch(
  () => props.prompt,
  (p, old) => {
    if (p && !old) {
      text.value = ''
      code.value = null
      note.value = ''
      files.value = []
      uploadError.value = ''
    }
  },
)
const selectedCode = computed(() => props.prompt?.reason_codes.options.find((o) => o.uuid === code.value) ?? null)
const valid = computed(() => {
  const p = props.prompt
  if (!p) return false
  if (p.text.required && text.value.trim().length < Math.max(1, p.text.min ?? 1)) return false
  if (p.reason_codes.mode === 'required' && !code.value) return false
  if (selectedCode.value?.requires_note && !note.value.trim()) return false
  if (p.attachments.mode === 'required' && !files.value.length) return false
  return true
})

async function onFiles(e: Event): Promise<void> {
  const input = e.target as HTMLInputElement
  const list = [...(input.files ?? [])]
  input.value = ''
  uploading.value = true
  uploadError.value = ''
  try {
    for (const f of list.slice(0, Math.max(0, (props.prompt?.attachments.max ?? 10) - files.value.length))) {
      const meta = await uploadFile(f, f.name)
      files.value.push({ uuid: meta.uuid, name: meta.name })
    }
  } catch (err) {
    uploadError.value = err instanceof ApiError ? err.message : t('runtime.upload_failed')
  } finally {
    uploading.value = false
  }
}
function submit(): void {
  emit('submit', {
    reason_text: text.value.trim() || null,
    reason_code: code.value,
    note: note.value.trim() || null,
    attachments: files.value.map((f) => f.uuid),
  })
}
const show = (v: unknown) => (v === '«masked»' ? t('records.masked') : formatLoose(v) || '—')
</script>

<template>
  <Dialog :visible="!!prompt" modal :header="prompt?.title || t('justify.title')" class="w-full max-w-xl" @update:visible="(v) => !v && emit('cancel')">
    <form v-if="prompt" class="flex flex-col gap-3" data-testid="justification-dialog" @submit.prevent="submit">
      <p v-if="prompt.help" class="text-sm text-muted-color">{{ prompt.help }}</p>
      <table v-if="prompt.changes.length" class="w-full text-sm">
        <caption class="text-start font-medium mb-1">{{ t('justify.changes') }}</caption>
        <tbody>
          <tr v-for="c in prompt.changes" :key="c.field" class="align-top">
            <td class="py-1 pe-3 font-medium">{{ c.label }}</td>
            <td class="py-1 pe-3 text-muted-color line-through" dir="auto">{{ show(c.old) }}</td>
            <td class="py-1" dir="auto">{{ show(c.new) }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="prompt.reason_codes.mode !== 'none'" class="field">
        <label for="jd-code">{{ t('justify.reason_code') }}<span v-if="prompt.reason_codes.mode === 'required'" class="text-danger ms-1" aria-hidden="true">*</span></label>
        <Select v-model="code" input-id="jd-code" :options="prompt.reason_codes.options" option-label="label" option-value="uuid" filter show-clear :invalid="!!errors?.reason_code" data-testid="justification-code" />
        <span v-if="errors?.reason_code" class="field-error">{{ errors.reason_code }}</span>
      </div>
      <div v-if="selectedCode?.requires_note" class="field">
        <label for="jd-note">{{ t('justify.note') }}<span class="text-danger ms-1" aria-hidden="true">*</span></label>
        <Textarea id="jd-note" v-model="note" rows="2" maxlength="2000" :invalid="!!errors?.note" />
        <span v-if="errors?.note" class="field-error">{{ errors.note }}</span>
      </div>
      <div class="field">
        <label for="jd-text">{{ t('justify.reason') }}<span v-if="prompt.text.required" class="text-danger ms-1" aria-hidden="true">*</span></label>
        <Textarea id="jd-text" v-model="text" rows="4" auto-resize :maxlength="prompt.text.max" :invalid="!!errors?.reason_text" data-testid="justification-text" />
        <small v-if="prompt.text.min" class="text-muted-color">{{ t('justify.min_length', { n: prompt.text.min }) }}</small>
        <span v-if="errors?.reason_text" class="field-error">{{ errors.reason_text }}</span>
      </div>
      <div v-if="prompt.attachments.mode !== 'none'" class="field">
        <label for="jd-files">{{ t('justify.attachments') }}<span v-if="prompt.attachments.mode === 'required'" class="text-danger ms-1" aria-hidden="true">*</span></label>
        <input id="jd-files" type="file" multiple :disabled="uploading || files.length >= prompt.attachments.max" @change="onFiles" />
        <ul v-if="files.length" class="text-sm flex flex-col gap-1">
          <li v-for="(f, i) in files" :key="f.uuid" class="flex items-center gap-2">
            <i class="pi pi-paperclip" /><span class="flex-1 truncate" dir="auto">{{ f.name }}</span>
            <Button icon="pi pi-times" text size="small" :aria-label="t('common.delete')" @click="files.splice(i, 1)" />
          </li>
        </ul>
        <span v-if="uploadError || errors?.attachments" class="field-error">{{ uploadError || errors?.attachments }}</span>
      </div>
      <Message severity="secondary" size="small" :closable="false">{{ t('justify.immutable') }}</Message>
      <div class="flex justify-end gap-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('cancel')" />
        <Button type="submit" :label="t('justify.submit')" icon="pi pi-check" :disabled="!valid || uploading" :loading="busy" data-testid="justification-submit" />
      </div>
    </form>
  </Dialog>
</template>
