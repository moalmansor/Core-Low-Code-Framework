<script setup lang="ts">
import BrandMark from '@/components/BrandMark.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import { useI18n } from 'vue-i18n'

/**
 * The frame of the external pages (specification §4.32, ADR-0058): forms
 * published outside, the portal, record links and signing. No internal
 * navigation; the organization's brand and the language switch only.
 */
const { t } = useI18n()
</script>

<template>
  <div class="min-h-full flex flex-col items-center p-4 gap-6" data-testid="external-layout">
    <header class="w-full max-w-3xl flex flex-wrap items-center justify-between gap-2 bg-card rounded-xl px-3 py-2">
      <BrandMark size="lg" />
      <div class="flex items-center gap-3">
        <RouterLink :to="{ name: 'x.portal' }" class="text-sm text-link" data-testid="x-portal-link">{{ t('xp_portal.portal') }}</RouterLink>
        <LanguageSwitcher />
      </div>
    </header>
    <main class="w-full max-w-3xl bg-card rounded-xl shadow p-4 sm:p-6">
      <RouterView />
    </main>
  </div>
</template>
