<script setup lang="ts">
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { get } from '@/api/http'
import BrandMark from '@/components/BrandMark.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import { useSession } from '@/stores/session'

interface Area {
  key: string
  section: string
  route: string
}

const session = useSession()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()
const areas = ref<Area[]>([])
const sidebarOpen = ref(false)
const userMenu = ref<InstanceType<typeof Menu> | null>(null)

async function loadAreas(): Promise<void> {
  if (session.gate !== 'none') {
    areas.value = []
    return
  }
  areas.value = (await get<{ data: { areas: Area[] } }>('/admin/console')).data.areas
}
onMounted(loadAreas)
watch(() => [session.gate, session.me?.permissions.length], loadAreas)
watch(
  () => route.fullPath,
  () => {
    sidebarOpen.value = false
    document.title = [route.meta.title ? t(route.meta.title) : '', session.systemName].filter(Boolean).join(' · ')
  },
  { immediate: true },
)

const sections = computed(() => {
  const groups = new Map<string, Area[]>()
  for (const a of areas.value) groups.set(a.section, [...(groups.get(a.section) ?? []), a])
  return [...groups.entries()]
})

const userItems = computed(() => [
  { label: t('profile.title'), icon: 'pi pi-user', command: () => router.push({ name: 'profile' }) },
  {
    label: t('shell.theme_toggle'),
    icon: 'pi pi-moon',
    command: () => {
      const dark = !document.documentElement.classList.contains('app-dark')
      session.applyTheme(dark ? 'dark' : 'light')
    },
  },
  { separator: true },
  {
    label: t('shell.sign_out'),
    icon: 'pi pi-sign-out',
    command: async () => {
      await session.logout()
      router.push({ name: 'login' })
    },
  },
])
</script>

<template>
  <div class="h-full flex">
    <aside
      class="fixed inset-y-0 start-0 z-30 w-64 shrink-0 bg-surface-0 dark:bg-surface-900 border-e border-surface-200 dark:border-surface-700 transition-transform lg:static"
      :class="sidebarOpen ? '' : 'max-lg:ltr:-translate-x-full max-lg:rtl:translate-x-full'"
      :aria-label="t('shell.navigation')"
    >
      <div class="h-14 px-4 flex items-center border-b border-surface-200 dark:border-surface-700">
        <RouterLink :to="{ name: 'home' }"><BrandMark /></RouterLink>
      </div>
      <nav class="p-3 flex flex-col gap-1 overflow-y-auto" data-testid="sidebar">
        <RouterLink class="nav-link" :to="{ name: 'home' }" exact-active-class="nav-active"><i class="pi pi-home" />{{ t('shell.home') }}</RouterLink>
        <RouterLink v-if="areas.length" class="nav-link" :to="{ name: 'admin' }" exact-active-class="nav-active"><i class="pi pi-cog" />{{ t('admin.title') }}</RouterLink>
        <template v-for="[section, items] in sections" :key="section">
          <div class="mt-3 mb-1 px-3 text-xs uppercase tracking-wide text-muted-color">{{ t(`admin.section.${section}`) }}</div>
          <RouterLink v-for="a in items" :key="a.key" class="nav-link" :to="a.route" active-class="nav-active" :data-testid="`nav-${a.key}`">
            <i :class="`pi ${icons[a.key] ?? 'pi-circle'}`" />{{ t(`admin.area.${a.key}`) }}
          </RouterLink>
        </template>
      </nav>
    </aside>
    <div v-if="sidebarOpen" class="fixed inset-0 z-20 bg-black/30 lg:hidden" @click="sidebarOpen = false" />
    <div class="flex-1 min-w-0 flex flex-col">
      <header class="h-14 px-4 flex items-center gap-3 bg-surface-0 dark:bg-surface-900 border-b border-surface-200 dark:border-surface-700">
        <Button class="lg:hidden" icon="pi pi-bars" text rounded :aria-label="t('shell.menu')" @click="sidebarOpen = true" />
        <div class="flex-1" />
        <LanguageSwitcher />
        <Button :label="session.me?.name" icon="pi pi-user" text aria-haspopup="true" data-testid="user-menu" @click="(e: Event) => userMenu?.toggle(e)" />
        <Menu ref="userMenu" :model="userItems" popup />
      </header>
      <main class="flex-1 overflow-auto p-4 lg:p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script lang="ts">
export const icons: Record<string, string> = {
  system_health: 'pi-heart',
  users: 'pi-users',
  departments: 'pi-sitemap',
  roles_permissions: 'pi-shield',
  appearance_branding: 'pi-palette',
  translations: 'pi-language',
  system_settings: 'pi-sliders-h',
  audit_log: 'pi-history',
  error_monitoring: 'pi-exclamation-triangle',
}
</script>

<style scoped>
.nav-link {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  color: var(--p-text-color);
  text-decoration: none;
}
.nav-link:hover {
  background: var(--p-surface-100);
}
.nav-active {
  background: var(--p-primary-50);
  color: var(--p-primary-700);
  font-weight: 600;
}
:global(.app-dark) .nav-link:hover {
  background: var(--p-surface-800);
}
</style>
