<script setup lang="ts">
import BrandMark from '@/components/BrandMark.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
</script>

<template>
  <div class="min-h-full flex flex-col items-center justify-center p-4 gap-6">
    <div class="w-full max-w-md flex items-center justify-between">
      <BrandMark size="lg" />
      <LanguageSwitcher />
    </div>
    <main class="w-full max-w-md bg-card rounded-xl shadow p-6">
      <RouterView />
    </main>
  </div>
</template>
