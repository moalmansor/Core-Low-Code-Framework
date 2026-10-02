<script setup lang="ts">
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import { useSession } from '@/stores/session'

const { t } = useI18n()
const session = useSession()
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="page-title">{{ t('home.welcome', { name: session.me?.name }) }}</h1>
    <p class="text-muted-color mb-4">{{ t('home.empty_shell') }}</p>
    <RouterLink v-if="session.me?.permissions.length" :to="{ name: 'admin' }"><Button :label="t('admin.title')" icon="pi pi-cog" /></RouterLink>
  </div>
</template>
