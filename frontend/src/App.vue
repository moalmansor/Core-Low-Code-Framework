<script setup lang="ts">
import ConfirmDialog from 'primevue/confirmdialog'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { onApiError } from '@/api/http'
import { useSession } from '@/stores/session'

const toast = useToast()
const router = useRouter()
const session = useSession()
const { t } = useI18n()

onApiError((e) => {
  if (e.status === 401 && session.me) {
    session.me = null
    router.push({ name: 'login', query: e.code === 'session_expired' ? { expired: '1' } : {} })
    return
  }
  if (e.status === 403 && (e.code === 'two_factor_enrollment_required' || e.code === 'password_expired')) {
    session.loadMe()
    router.push({ name: 'profile', query: { tab: e.code === 'password_expired' ? 'password' : 'security' } })
    return
  }
  if (e.status === 419) {
    toast.add({ severity: 'warn', summary: t('errors.page_expired'), life: 6000 })
    return
  }
  if (e.status === 429) {
    toast.add({ severity: 'warn', summary: t('errors.too_many_requests'), life: 6000 })
    return
  }
  if (e.status >= 500 || e.status === 0) {
    toast.add({
      severity: 'error',
      summary: t('errors.unexpected'),
      detail: e.body.reference ? t('errors.reference', { reference: e.body.reference }) : undefined,
      life: 12000,
    })
  }
})
</script>

<template>
  <Toast position="top-center" />
  <ConfirmDialog />
  <RouterView />
</template>
