<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useSession } from '@/stores/session'
import { errorText } from './admin/building/shared'
import { homeApi, type ShownHome } from './experience/api'
import HomeWidget from './experience/HomeWidget.vue'
import PersonalCorner from './PersonalCorner.vue'

/**
 * The landing page (specification §4.29): the home screen meant for this
 * person — their own, a role's, a department's or the default — or, for an
 * application, that application's. Without one, a plain welcome.
 */
const { t } = useI18n()
const session = useSession()
const route = useRoute()
const application = computed(() => (typeof route.params.application === 'string' ? route.params.application : null))
const home = ref<ShownHome | null>(null)
const loaded = ref(false)
const failed = ref<string | null>(null)

async function load(): Promise<void> {
  loaded.value = false
  failed.value = null
  try {
    home.value = await homeApi.mine(application.value)
  } catch (e) {
    home.value = null
    failed.value = errorText(e, t('xp.load_failed'))
  } finally {
    loaded.value = true
  }
}
watch(application, load, { immediate: true })
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="home">
    <Message v-if="failed" severity="warn" :closable="false" data-testid="home-failed">{{ failed }}</Message>
    <template v-else-if="loaded && home">
      <h1 class="page-title !mb-0" dir="auto">{{ home.label }}</h1>
      <p class="m-0 text-muted-color">{{ t('home.welcome', { name: session.me?.name }) }}</p>
      <PersonalCorner v-if="!application" />
      <div class="home-grid" data-testid="home-widgets">
        <HomeWidget v-for="w in home.widgets" :key="w.uuid" :widget="w" />
      </div>
    </template>
    <div v-else-if="loaded" class="max-w-2xl">
      <h1 class="page-title">{{ t('home.welcome', { name: session.me?.name }) }}</h1>
      <p class="text-muted-color mb-4">{{ t('home.empty_shell') }}</p>
      <PersonalCorner class="mb-4" />
      <RouterLink v-if="session.me?.permissions.length" :to="{ name: 'admin' }"><Button :label="t('admin.title')" icon="pi pi-cog" /></RouterLink>
    </div>
  </div>
</template>

<style scoped>
.home-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 1rem;
}
</style>
