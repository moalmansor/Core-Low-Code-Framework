<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import { formatDatetime } from '@/runtime/format'
import SafeHtml from '@/runtime/SafeHtml'

/** The comments of a record: the thread and, unless read-only, a box to add one. */
interface Comment {
  uuid: string
  parent: string | null
  body: string
  author: { uuid: string; name: string | null }
  created_at: string
  mine: boolean
}
const props = defineProps<{ form: string; record: string; readonly?: boolean }>()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const comments = ref<Comment[] | null>(null)
const body = ref('')
const posting = ref(false)
const error = ref('')
async function load(): Promise<void> {
  error.value = ''
  try {
    comments.value = (await get<{ data: Comment[] }>(`/r/${props.form}/${props.record}/comments`)).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : t('records.load_failed')
  }
}
watch(() => [props.form, props.record], load, { immediate: true })
function escapeHtml(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')
}
async function add(): Promise<void> {
  const text = body.value.trim()
  if (!text) return
  posting.value = true
  try {
    await send('post', `/r/${props.form}/${props.record}/comments`, { body: escapeHtml(text) })
    body.value = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('records.comment_failed')
  } finally {
    posting.value = false
  }
}
function remove(c: Comment): void {
  confirm.require({
    message: t('records.comment_delete_confirm'),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('delete', `/r/${props.form}/${props.record}/comments/${c.uuid}`)
        await load()
      } catch (e) {
        if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 6000 })
      }
    },
  })
}
</script>

<template>
  <Message v-if="error" severity="error" class="mb-3">{{ error }}</Message>
  <p v-if="comments === null && !error" class="text-muted-color">{{ t('records.loading') }}</p>
  <ul v-else-if="comments" class="flex flex-col gap-3 mb-4" data-testid="comments">
    <li v-if="comments.length === 0" class="text-muted-color">{{ t('records.comments_empty') }}</li>
    <li v-for="c in comments" :key="c.uuid" class="rounded-lg border border-line p-3" :class="{ 'ms-8': c.parent }">
      <div class="flex items-center gap-2 mb-1">
        <span class="font-medium">{{ c.author.name ?? '—' }}</span>
        <span class="text-sm text-muted-color flex-1">{{ formatDatetime(c.created_at, locale) }}</span>
        <Button v-if="c.mine && !readonly" icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('common.delete')" @click="remove(c)" />
      </div>
      <SafeHtml :html="c.body" />
    </li>
  </ul>
  <form v-if="!readonly" class="flex flex-col gap-2" @submit.prevent="add">
    <label :for="`new-comment-${record}`" class="text-sm font-medium">{{ t('records.add_comment') }}</label>
    <Textarea :id="`new-comment-${record}`" v-model="body" rows="3" auto-resize maxlength="20000" data-testid="comment-body" />
    <div>
      <Button type="submit" :label="t('records.post_comment')" icon="pi pi-send" :loading="posting" :disabled="!body.trim()" data-testid="comment-post" />
    </div>
  </form>
</template>
