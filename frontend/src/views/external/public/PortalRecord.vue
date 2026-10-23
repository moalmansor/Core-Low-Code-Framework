<script setup lang="ts">
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ApiError } from '@/api/http'
import EmptyState from '@/components/config/EmptyState.vue'
import FormRenderer from '@/runtime/FormRenderer.vue'
import type { ClientDefinition, References, Values } from '@/runtime/types'
import { publicApi, type StatusPayload } from '../api'

/** One of the signed-in external user's own submissions, read-only, with the fields its form shows and its status. */
const { t } = useI18n()
const route = useRoute()
const loading = ref(true)
const failure = ref<string | null>(null)
const rec = ref<{ form: string; reference: string | null; status: StatusPayload | null; definition: ClientDefinition; values: Values; references: References } | null>(null)

onMounted(async () => {
  try {
    rec.value = await publicApi.myRecord(String(route.params.slug), String(route.params.record))
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : t('xp_form.unavailable')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="x-portal-record">
    <RouterLink :to="{ name: 'x.portal' }" class="text-sm text-primary inline-flex items-center gap-1">
      <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('xp_portal.portal') }}
    </RouterLink>
    <div v-if="loading" class="flex justify-center p-8"><ProgressSpinner /></div>
    <EmptyState v-else-if="failure" icon="pi pi-lock" :title="t('xp_form.unavailable_title')" :description="failure" testid="x-record-unavailable" />
    <template v-else-if="rec">
      <header class="flex flex-wrap items-center gap-3">
        <h1 class="page-title !mb-0 flex-1 min-w-0" dir="auto">
          {{ rec.form }}<span v-if="rec.reference" class="ltr-value"> · {{ rec.reference }}</span>
        </h1>
        <Tag v-if="rec.status" :value="rec.status.name" severity="secondary" data-testid="x-record-status" />
      </header>
      <FormRenderer :model-value="rec.values" :definition="rec.definition" mode="view" :references="rec.references" />
    </template>
  </div>
</template>
