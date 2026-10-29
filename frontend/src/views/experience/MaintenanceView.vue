<script setup lang="ts">
import Button from 'primevue/button'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useSession } from '@/stores/session'
import { formatDateTime } from '../admin/building/shared'
import { maintenanceApi, type MaintenanceState } from './api'

/** The system is offline for maintenance (specification §4.29): the message, when it should be back, and a way out. */
const { t, locale } = useI18n()
const router = useRouter()
const session = useSession()
const state = ref<MaintenanceState | null>(null)
const checking = ref(false)

async function check(): Promise<void> {
  checking.value = true
  try {
    state.value = await maintenanceApi.get()
    if (!state.value.blocked) await router.replace({ name: 'home' })
  } catch {
    state.value = null
  } finally {
    checking.value = false
  }
}
onMounted(check)
async function signOut(): Promise<void> {
  await session.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="maintenance-view">
    <h1 class="page-title !mb-0">{{ t('xp.maintenance_title') }}</h1>
    <p class="m-0" dir="auto">{{ state?.message ?? session.boot?.maintenance?.message ?? t('xp.maintenance_default') }}</p>
    <p v-if="state?.until" class="m-0 text-sm text-muted-color">{{ t('xp.maintenance_until', { when: formatDateTime(state.until, locale) }) }}</p>
    <div class="flex flex-wrap gap-2">
      <Button icon="pi pi-refresh" :label="t('xp.try_again')" :loading="checking" @click="check" />
      <Button v-if="session.me" severity="secondary" :label="t('shell.sign_out')" @click="signOut" />
    </div>
  </div>
</template>
