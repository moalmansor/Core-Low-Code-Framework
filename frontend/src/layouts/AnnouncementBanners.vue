<script setup lang="ts">
import Button from 'primevue/button'
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import SafeHtml from '@/runtime/SafeHtml'
import { useSession } from '@/stores/session'
import { announcementsApi, maintenanceApi, type ActiveAnnouncement, type MaintenanceState } from '@/views/experience/api'

/**
 * Banners across the top of every page (specification §4.29): announcements
 * placed as banners, and — for people who may work during maintenance — a
 * reminder that the system is offline for everyone else.
 */
const { t } = useI18n()
const session = useSession()
const banners = ref<ActiveAnnouncement[]>([])
const maintenance = ref<MaintenanceState | null>(null)

async function load(): Promise<void> {
  if (session.gate !== 'none') return
  try {
    const [b, m] = await Promise.all([announcementsApi.active('banner'), maintenanceApi.get()])
    banners.value = b
    maintenance.value = m
  } catch {
    // Banners are a convenience: a failed load shows none.
  }
}
onMounted(load)
watch(() => [session.locale, session.gate], load)

async function dismiss(a: ActiveAnnouncement): Promise<void> {
  banners.value = banners.value.filter((b) => b.uuid !== a.uuid)
  try {
    await announcementsApi.dismiss(a.uuid)
  } catch {
    // Dismissing again later is harmless.
  }
}
</script>

<template>
  <div v-if="banners.length || maintenance?.active" class="flex flex-col" data-testid="banners">
    <div v-if="maintenance?.active" class="banner sev-warning" role="status" data-testid="maintenance-banner">
      <i class="pi pi-wrench" aria-hidden="true" />
      <span class="flex-1 text-sm"
        >{{ t('xp.maintenance_on') }} <span dir="auto">{{ maintenance.message }}</span></span
      >
    </div>
    <div v-for="a in banners" :key="a.uuid" class="banner" :class="`sev-${a.severity}`" role="status" :data-testid="`banner-${a.uuid}`">
      <i :class="a.severity === 'danger' || a.severity === 'warning' ? 'pi pi-exclamation-triangle' : a.severity === 'success' ? 'pi pi-check-circle' : 'pi pi-info-circle'" aria-hidden="true" />
      <div class="flex-1 min-w-0 text-sm">
        <strong dir="auto">{{ a.title }}</strong>
        <SafeHtml v-if="a.body" :html="a.body" />
      </div>
      <Button v-if="a.dismissible" icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('xp.dismiss')" @click="dismiss(a)" />
    </div>
  </div>
</template>

<style scoped>
.banner {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.5rem 1rem;
  border-block-end: 1px solid var(--border);
  background: var(--bg-surface);
  border-inline-start: 4px solid var(--primary);
}
.banner > i {
  margin-block-start: 0.2rem;
}
.sev-success {
  border-inline-start-color: var(--success);
}
.sev-warning {
  border-inline-start-color: var(--warning);
}
.sev-danger {
  border-inline-start-color: var(--danger);
}
</style>
