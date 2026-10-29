<script setup lang="ts">
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { send } from '@/api/http'
import { useSession } from '@/stores/session'
import { formatDateTime } from '@/views/admin/building/shared'

/**
 * Always shown while an administrator is acting as someone else
 * (specification §4.36, §5; ADR-0061). It cannot be dismissed; stopping
 * returns to the administrator's own session.
 */
const { t, locale } = useI18n()
const session = useSession()
const router = useRouter()
const toast = useToast()
const busy = ref(false)
const info = computed(() => session.me?.impersonation ?? null)

async function stop(): Promise<void> {
  busy.value = true
  try {
    await send('post', '/impersonation/stop')
    await session.loadMe()
    toast.add({ severity: 'success', summary: t('imp.stopped'), life: 4000 })
    await router.push({ name: 'admin.users' })
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div v-if="info" class="imp-banner" role="alert" data-testid="impersonation-banner">
    <i class="pi pi-user-edit" aria-hidden="true" />
    <span class="flex-1 min-w-0 text-sm">
      <strong>{{ t('imp.acting_as', { name: session.me?.name ?? '' }) }}</strong>
      {{ t('imp.started_by', { name: info.by, at: formatDateTime(info.expires_at, locale) }) }}
    </span>
    <Button :label="t('imp.stop')" icon="pi pi-sign-out" size="small" severity="contrast" :loading="busy" data-testid="impersonation-stop" @click="stop" />
  </div>
</template>

<style scoped>
.imp-banner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 1rem;
  background: var(--bg-surface);
  border-block-end: 1px solid var(--border);
  border-inline-start: 6px solid var(--danger);
}
</style>
