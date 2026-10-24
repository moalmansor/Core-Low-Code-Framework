<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ApiError } from '@/api/http'
import EmptyState from '@/components/config/EmptyState.vue'
import { formatDatetime } from '@/runtime/format'
import FormRenderer from '@/runtime/FormRenderer.vue'
import { submissionValues } from '@/runtime/submission'
import type { Values } from '@/runtime/types'
import { publicApi, type LinkPage } from '../api'

/**
 * A link to one record (specification §4.32, ADR-0058): it shows the record
 * as its sender sees it and offers the one thing the link allows — approve
 * or reject through a chosen step, or complete one section. What is done is
 * done as the sender, with every rule of the system applied.
 */
const { t, locale } = useI18n()
const route = useRoute()
const token = computed(() => String(route.params.token))
const loading = ref(true)
const failure = ref<string | null>(null)
const page = ref<LinkPage | null>(null)
const values = ref<Values>({})
const comment = ref('')
const errors = ref<Record<string, string[]>>({})
const banner = ref('')
const busy = ref(false)
const done = ref(false)
const renderer = ref<InstanceType<typeof FormRenderer> | null>(null)

onMounted(async () => {
  try {
    page.value = await publicApi.link(token.value)
    values.value = { ...page.value.values }
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : t('xp_form.unavailable')
  } finally {
    loading.value = false
  }
})
const acting = computed(() => page.value !== null && page.value.action !== 'view')
const commentAllowed = computed(() => page.value?.transition !== null && page.value?.transition?.comment !== 'none')

async function perform(): Promise<void> {
  if (!page.value || busy.value) return
  banner.value = ''
  errors.value = {}
  let body: { row_version: number; values?: Values; comment?: string } = { row_version: page.value.row_version }
  if (page.value.action === 'complete_section') {
    if (!renderer.value) return
    if (Object.keys(renderer.value.validate()).length) {
      banner.value = t('records.fix_errors')
      return
    }
    body = { ...body, values: submissionValues(renderer.value.index, renderer.value.state, 'edit', renderer.value.values as Values, page.value.values) }
  } else if (comment.value.trim() !== '') {
    body = { ...body, comment: comment.value.trim() }
  }
  busy.value = true
  try {
    await publicApi.useLink(token.value, body)
    done.value = true
  } catch (e) {
    if (e instanceof ApiError) {
      errors.value = (e.body.errors as Record<string, string[]> | undefined) ?? {}
      banner.value = e.status === 409 ? t('xp_link.changed') : e.message
    } else banner.value = t('xp_form.unavailable')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="x-link-page">
    <div v-if="loading" class="flex justify-center p-8"><ProgressSpinner /></div>
    <EmptyState v-else-if="failure" icon="pi pi-link" :title="t('xp_link.unavailable_title')" :description="failure" testid="x-link-unavailable" />
    <template v-else-if="page">
      <header class="flex flex-col gap-1">
        <p class="m-0 text-sm text-muted-color">{{ t('xp_link.sent_by', { name: page.sender }) }}</p>
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="page-title !mb-0 flex-1 min-w-0" dir="auto">
            {{ page.form_name }}<span v-if="page.title" dir="auto"> · {{ page.title }}</span>
          </h1>
          <Tag v-if="page.status" :value="page.status.name" severity="secondary" data-testid="x-link-status" />
        </div>
        <p class="m-0 text-xs text-muted-color">{{ t('xp_link.expires', { date: formatDatetime(page.expires_at, locale) }) }}</p>
      </header>

      <Message v-if="done" severity="success" data-testid="x-link-done">{{ t(`xp_link.done_${page.action}`) }}</Message>
      <template v-else>
        <Message v-if="banner" severity="error" data-testid="x-link-banner">{{ banner }}</Message>
        <p v-if="page.action === 'complete_section'" class="m-0">{{ t('xp_link.complete_hint') }}</p>
        <FormRenderer ref="renderer" v-model="values" :definition="page.definition" :mode="page.action === 'complete_section' ? 'edit' : 'view'" :references="page.references" :errors="errors" />
        <section v-if="acting" class="flex flex-col gap-2 border-t border-surface pt-4">
          <template v-if="page.transition">
            <h2 class="text-base font-semibold m-0" dir="auto">{{ page.transition.label }}</h2>
            <template v-if="commentAllowed">
              <label for="x-link-comment" class="text-sm">{{ page.transition.comment === 'mandatory' ? t('xp_link.comment_required') : t('xp_link.comment') }}</label>
              <Textarea id="x-link-comment" v-model="comment" rows="3" class="w-full" :invalid="!!errors.comment" data-testid="x-link-comment" />
              <small v-if="errors.comment" class="text-danger" role="alert">{{ errors.comment[0] }}</small>
            </template>
          </template>
          <div>
            <Button
              :icon="page.action === 'reject' ? 'pi pi-times' : 'pi pi-check'"
              :label="page.transition ? page.transition.label : t('xp_link.save')"
              :severity="page.action === 'reject' ? 'danger' : undefined"
              :loading="busy"
              data-testid="x-link-act"
              @click="perform"
            />
          </div>
        </section>
      </template>
    </template>
  </div>
</template>
