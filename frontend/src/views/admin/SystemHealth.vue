<script setup lang="ts">
import Tag from 'primevue/tag'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'

interface Health {
  checks: Record<string, string>
  audit_chain_verified_at: string | null
  open_error_groups: number
  failed_jobs: number
  pending_outbox_events: number
  php_version: string
  database_driver: string
  app_version: string
}
const { t } = useI18n()
const health = ref<Health | null>(null)
onMounted(async () => {
  health.value = (await get<{ data: Health }>('/admin/health')).data
})
const severity = (v: string) => (v === 'ok' ? 'success' : v === 'failing' ? 'danger' : 'secondary')
</script>

<template>
  <h1 class="page-title">{{ t('admin.area.system_health') }}</h1>
  <div v-if="health" class="grid gap-4 grid-cols-[repeat(auto-fill,minmax(16rem,1fr))]">
    <div v-for="(v, k) in health.checks" :key="k" class="p-4 rounded-xl bg-surface-0 dark:bg-surface-900 shadow-sm flex justify-between items-center">
      <span>{{ t(`health.check.${k}`) }}</span><Tag :severity="severity(v)" :value="t(`health.state.${v}`)" />
    </div>
    <div class="p-4 rounded-xl bg-surface-0 dark:bg-surface-900 shadow-sm"><div class="text-sm text-muted-color">{{ t('health.open_errors') }}</div><div class="text-2xl">{{ health.open_error_groups }}</div></div>
    <div class="p-4 rounded-xl bg-surface-0 dark:bg-surface-900 shadow-sm"><div class="text-sm text-muted-color">{{ t('health.failed_jobs') }}</div><div class="text-2xl">{{ health.failed_jobs }}</div></div>
    <div class="p-4 rounded-xl bg-surface-0 dark:bg-surface-900 shadow-sm"><div class="text-sm text-muted-color">{{ t('health.pending_events') }}</div><div class="text-2xl">{{ health.pending_outbox_events }}</div></div>
    <div class="p-4 rounded-xl bg-surface-0 dark:bg-surface-900 shadow-sm"><div class="text-sm text-muted-color">{{ t('health.audit_verified') }}</div><div class="ltr-value">{{ health.audit_chain_verified_at ?? '—' }}</div></div>
    <div class="p-4 rounded-xl bg-surface-0 dark:bg-surface-900 shadow-sm text-sm ltr-value">v{{ health.app_version }} · PHP {{ health.php_version }} · {{ health.database_driver }}</div>
  </div>
</template>
