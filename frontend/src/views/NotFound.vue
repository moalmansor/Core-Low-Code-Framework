<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="min-h-full flex flex-col items-center justify-center gap-3 p-6 text-center">
    <h1 class="text-4xl font-bold">404</h1>
    <p>{{ t('errors.not_found') }}</p>
    <RouterLink to="/" class="text-primary">{{ t('shell.home') }}</RouterLink>
  </div>
</template>
