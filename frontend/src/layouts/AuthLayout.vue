<script setup lang="ts">
import { computed } from 'vue'
import BrandMark from '@/components/BrandMark.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import { useSession } from '@/stores/session'

const session = useSession()
const login = computed(() => session.theme?.login ?? null)
// The background artwork is set through the CSSOM (a bound style), never as style markup.
const background = computed(() => {
  const url = session.theme?.assets.login_background
  return url ? { backgroundImage: `url("${encodeURI(url)}")` } : undefined
})
</script>

<template>
  <div class="min-h-full flex flex-col items-center justify-center p-4 gap-6 bg-cover bg-center" :style="background" data-testid="auth-layout">
    <div class="w-full max-w-md flex items-center justify-between bg-card/90 rounded-xl px-3 py-2">
      <BrandMark size="lg" />
      <LanguageSwitcher />
    </div>
    <main class="w-full max-w-md bg-card rounded-xl shadow p-6">
      <RouterView />
    </main>
    <footer
      v-if="login && (login.support || login.legal_links.length)"
      class="w-full max-w-md flex flex-wrap items-center gap-x-4 gap-y-1 text-sm bg-card/90 rounded-xl px-3 py-2"
      data-testid="auth-footer"
    >
      <span v-if="login.support" class="text-muted-color flex-1 min-w-0" data-testid="auth-support"><i class="pi pi-question-circle me-1" aria-hidden="true" />{{ login.support }}</span>
      <a v-for="l in login.legal_links" :key="l.url" :href="l.url" class="text-link underline" rel="noopener noreferrer" target="_blank">{{ l.label }}</a>
    </footer>
  </div>
</template>
