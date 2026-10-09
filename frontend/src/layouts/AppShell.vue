<script setup lang="ts">
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { get } from '@/api/http'
import BrandMark from '@/components/BrandMark.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import NavTree, { type NavItem } from '@/runtime/NavTree.vue'
import { useSession } from '@/stores/session'

/** A published application with the menu items the user may see (GET /navigation). */
interface NavApp {
  uuid: string
  key: string
  name: string
  icon: string | null
  color: string | null
  maintenance: boolean
  items: NavItem[]
}

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
const apps = ref<NavApp[]>([])
const collapsedApps = ref<Record<string, boolean>>({})
const sidebarOpen = ref(false)
const userMenu = ref<InstanceType<typeof Menu> | null>(null)
const nav = ref<HTMLElement | null>(null)

async function loadAreas(): Promise<void> {
  if (session.gate !== 'none') {
    areas.value = []
    return
  }
  areas.value = (await get<{ data: { areas: Area[] } }>('/admin/console')).data.areas
}
async function loadNavigation(): Promise<void> {
  if (session.gate !== 'none') {
    apps.value = []
    return
  }
  try {
    apps.value = (await get<{ data: NavApp[] }>('/navigation')).data
  } catch {
    apps.value = []
  }
}
function loadSidebar(): void {
  void loadAreas()
  void loadNavigation()
}
onMounted(loadSidebar)
watch(() => [session.gate, session.me?.permissions.length, session.locale], loadSidebar)
watch(
  () => route.fullPath,
  () => {
    sidebarOpen.value = false
    // A long menu keeps the active item in view (instant: no motion).
    void nextTick(() => nav.value?.querySelector('.nav-active, .router-link-active')?.scrollIntoView({ block: 'nearest' }))
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
  <!-- The frame is the window's height: the menu and the content scroll inside it; the page never does (design system: frame). -->
  <div class="h-dvh flex overflow-hidden" data-testid="app-frame">
    <aside
      class="fixed inset-y-0 start-0 z-30 w-64 shrink-0 flex flex-col bg-card border-e border-line transition-transform lg:static lg:h-dvh"
      data-testid="app-sidebar"
      :class="sidebarOpen ? '' : 'max-lg:ltr:-translate-x-full max-lg:rtl:translate-x-full'"
      :aria-label="t('shell.navigation')"
    >
      <div class="h-14 shrink-0 px-4 flex items-center border-b border-line">
        <RouterLink :to="{ name: 'home' }"><BrandMark /></RouterLink>
      </div>
      <nav ref="nav" class="flex-1 min-h-0 p-3 flex flex-col gap-1 overflow-y-auto overscroll-contain" data-testid="sidebar">
        <RouterLink class="nav-link" :to="{ name: 'home' }" exact-active-class="nav-active"><i class="pi pi-home" />{{ t('shell.home') }}</RouterLink>
        <section v-for="app in apps" :key="app.uuid" class="mt-3" :data-testid="`nav-app-${app.key}`">
          <button
            type="button"
            class="w-full mb-1 px-3 flex items-center gap-2 text-xs uppercase tracking-wide text-muted-color"
            :aria-expanded="!collapsedApps[app.uuid]"
            @click="collapsedApps = { ...collapsedApps, [app.uuid]: !collapsedApps[app.uuid] }"
          >
            <i v-if="app.icon" :class="app.icon" :style="app.color ? { color: app.color } : undefined" />
            <span class="flex-1 text-start">{{ app.name }}</span>
            <i v-if="app.maintenance" v-tooltip="t('records.app_maintenance')" class="pi pi-wrench text-warning" />
            <i :class="collapsedApps[app.uuid] ? 'pi pi-chevron-right rtl:rotate-180' : 'pi pi-chevron-down'" class="text-[0.625rem]" />
          </button>
          <NavTree v-if="!collapsedApps[app.uuid]" :items="app.items" />
        </section>
        <div v-if="apps.length && areas.length" class="mt-3 border-t border-line" />
        <RouterLink v-if="areas.length" class="nav-link" :to="{ name: 'admin' }" exact-active-class="nav-active"><i class="pi pi-cog" />{{ t('admin.title') }}</RouterLink>
        <template v-for="[section, items] in sections" :key="section">
          <div class="mt-3 mb-1 px-3 text-xs uppercase tracking-wide text-muted-color">{{ t(`admin.section.${section}`) }}</div>
          <RouterLink v-for="a in items" :key="a.key" class="nav-link" :to="a.route" active-class="nav-active" :data-testid="`nav-${a.key}`">
            <i :class="`pi ${icons[a.key] ?? 'pi-circle'}`" />{{ t(`admin.area.${a.key}`) }}
          </RouterLink>
        </template>
      </nav>
    </aside>
    <div v-if="sidebarOpen" class="fixed inset-0 z-20 bg-overlay lg:hidden" @click="sidebarOpen = false" />
    <div class="flex-1 min-w-0 min-h-0 flex flex-col">
      <header class="h-14 shrink-0 px-4 flex items-center gap-3 bg-card border-b border-line">
        <Button class="lg:hidden" icon="pi pi-bars" text rounded :aria-label="t('shell.menu')" @click="sidebarOpen = true" />
        <div class="flex-1" />
        <LanguageSwitcher />
        <Button :label="session.me?.name" icon="pi pi-user" text aria-haspopup="true" data-testid="user-menu" @click="(e: Event) => userMenu?.toggle(e)" />
        <Menu ref="userMenu" :model="userItems" popup />
      </header>
      <main class="flex-1 min-h-0 overflow-auto p-4 lg:p-6" data-testid="app-content">
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
  applications: 'pi-th-large',
  forms: 'pi-file-edit',
  blueprints: 'pi-clone',
  reference_data: 'pi-book',
  schema: 'pi-database',
  workflows_views: 'pi-sitemap',
  assignment_queues: 'pi-inbox',
  reason_codes: 'pi-comment',
}
</script>

<style scoped>
.nav-link {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  color: var(--text);
  text-decoration: none;
}
.nav-link:hover {
  background: var(--bg-subtle);
}
.nav-active {
  background: var(--primary-subtle);
  color: var(--on-primary-subtle);
  font-weight: 600;
}
</style>
