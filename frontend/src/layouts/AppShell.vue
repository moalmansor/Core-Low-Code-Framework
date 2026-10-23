<script setup lang="ts">
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { get } from '@/api/http'
import BrandMark from '@/components/BrandMark.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import AnnouncementBanners from './AnnouncementBanners.vue'
import ImpersonationBanner from './ImpersonationBanner.vue'
import GlobalSearchBox from './GlobalSearchBox.vue'
import NotificationsBell from './NotificationsBell.vue'
import NavTree, { navPath, type NavItem } from '@/runtime/NavTree.vue'
import { activeNavId, type NavCandidate } from './navActive'
import { useSession } from '@/stores/session'
import type { ClientTheme } from '@/theme/theme'

/** A published application with the menu items the user may see (GET /navigation). */
interface NavApp {
  uuid: string
  key: string
  name: string
  icon: string | null
  color: string | null
  maintenance: boolean
  /** The application has a home screen of its own. */
  home?: boolean
  /** The application's own theme (ADR-0055), applied while the user works in it. */
  theme?: ClientTheme | null
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
  try {
    areas.value = (await get<{ data: { areas: Area[] } }>('/admin/console')).data.areas
  } catch {
    // A refused or failed reload (a rate limit, a dropped connection) keeps the menu already shown.
  }
}
async function loadNavigation(): Promise<void> {
  if (session.gate !== 'none') {
    apps.value = []
    return
  }
  try {
    apps.value = (await get<{ data: NavApp[] }>('/navigation')).data
  } catch {
    // As above: keep the applications already shown.
  }
}
/** Live counts for menu badges, refreshed every minute while the window is visible. */
const badges = ref<Record<string, number>>({})
const hasBadges = (items: NavItem[]): boolean => items.some((i) => i.badge || hasBadges(i.children ?? []))
async function loadBadges(): Promise<void> {
  if (session.gate !== 'none' || !apps.value.some((a) => hasBadges(a.items)) || document.visibilityState === 'hidden') return
  try {
    badges.value = (await get<{ data: Record<string, number> }>('/navigation/badges')).data
  } catch {
    // Counts are a convenience: a failed refresh keeps the last ones.
  }
}
let badgeTimer: ReturnType<typeof setInterval> | undefined
function loadSidebar(): void {
  void loadAreas()
  void loadNavigation().then(loadBadges)
}
onMounted(() => {
  loadSidebar()
  badgeTimer = setInterval(() => void loadBadges(), 60_000)
})
onBeforeUnmount(() => clearInterval(badgeTimer))
watch(() => [session.gate, session.me?.permissions.length, session.locale], loadSidebar)
watch(
  () => [route.fullPath, session.browserTitle],
  () => {
    sidebarOpen.value = false
    // A long menu keeps the active item in view (instant: no motion).
    void nextTick(() => nav.value?.querySelector('.nav-active')?.scrollIntoView({ block: 'nearest' }))
    document.title = [route.meta.title ? t(route.meta.title) : '', session.browserTitle].filter(Boolean).join(' · ')
  },
  { immediate: true },
)

const sections = computed(() => {
  const groups = new Map<string, Area[]>()
  for (const a of areas.value) groups.set(a.section, [...(groups.get(a.section) ?? []), a])
  return [...groups.entries()]
})

// Exactly one sidebar entry is active: the longest match, first in menu order on a tie.
function appCandidates(items: NavItem[]): NavCandidate[] {
  return items.flatMap((i) => {
    const path = i.type === 'my_work' ? null : navPath(i)
    return [...(path ? [{ id: i.uuid, path }] : []), ...appCandidates(i.children ?? [])]
  })
}
const active = computed(() =>
  activeNavId(route.path, [
    { id: 'home', path: '/', exact: true },
    { id: 'my_work', path: '/my-work' },
    { id: 'insights', path: '/insights' },
    { id: 'insights', path: '/reports' },
    { id: 'insights', path: '/dashboards' },
    ...apps.value.flatMap((a) => [...(a.home ? [{ id: `app-home-${a.uuid}`, path: `/a/${a.uuid}`, exact: true }] : []), ...appCandidates(a.items)]),
    { id: 'admin', path: '/admin', exact: true },
    ...areas.value.map((a) => ({ id: `area-${a.key}`, path: a.route })),
  ]),
)
// The application the user is working in wears its own theme; elsewhere the organization's applies.
const holds = (items: NavItem[], id: string | null): boolean => items.some((i) => i.uuid === id || holds(i.children ?? [], id))
const activeApp = computed(() => {
  const param = route.params.application
  return apps.value.find((a) => a.uuid === param || active.value === `app-home-${a.uuid}` || holds(a.items, active.value)) ?? null
})
watch(
  () => activeApp.value?.theme ?? null,
  (theme) => session.useTheme(theme),
  { immediate: true },
)
onBeforeUnmount(() => session.useTheme(null))
const navClass = (id: string) => ({ 'nav-link': true, 'nav-active': active.value === id })
const current = (id: string) => (active.value === id ? 'page' : undefined)

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
        <RouterLink :class="navClass('home')" :to="{ name: 'home' }" active-class="" exact-active-class="" :aria-current="current('home')"><i class="pi pi-home" />{{ t('shell.home') }}</RouterLink>
        <RouterLink :class="navClass('my_work')" :to="{ name: 'my_work' }" active-class="" exact-active-class="" :aria-current="current('my_work')" data-testid="nav-my-work"
          ><i class="pi pi-inbox" />{{ t('my_work.title') }}</RouterLink
        >
        <RouterLink :class="navClass('insights')" :to="{ name: 'insights' }" active-class="" exact-active-class="" :aria-current="current('insights')" data-testid="nav-insights"
          ><i class="pi pi-chart-bar" />{{ t('rep.insights') }}</RouterLink
        >
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
          <template v-if="!collapsedApps[app.uuid]">
            <RouterLink
              v-if="app.home"
              :class="navClass(`app-home-${app.uuid}`)"
              :to="{ name: 'apps.home', params: { application: app.uuid } }"
              active-class=""
              exact-active-class=""
              :aria-current="current(`app-home-${app.uuid}`)"
              :data-testid="`nav-app-home-${app.key}`"
              ><i class="pi pi-home" />{{ t('xp.app_home') }}</RouterLink
            >
            <NavTree :items="app.items" :active="active" :badges="badges" />
          </template>
        </section>
        <div v-if="apps.length && areas.length" class="mt-3 border-t border-line" />
        <RouterLink v-if="areas.length" :class="navClass('admin')" :to="{ name: 'admin' }" active-class="" exact-active-class="" :aria-current="current('admin')"
          ><i class="pi pi-cog" />{{ t('admin.title') }}</RouterLink
        >
        <template v-for="[section, items] in sections" :key="section">
          <div class="mt-3 mb-1 px-3 text-xs uppercase tracking-wide text-muted-color">{{ t(`admin.section.${section}`) }}</div>
          <RouterLink
            v-for="a in items"
            :key="a.key"
            :class="navClass(`area-${a.key}`)"
            :to="a.route"
            active-class=""
            exact-active-class=""
            :aria-current="current(`area-${a.key}`)"
            :data-testid="`nav-${a.key}`"
          >
            <i :class="`pi ${icons[a.key] ?? 'pi-circle'}`" />{{ t(`admin.area.${a.key}`) }}
          </RouterLink>
        </template>
      </nav>
    </aside>
    <div v-if="sidebarOpen" class="fixed inset-0 z-20 bg-overlay lg:hidden" @click="sidebarOpen = false" />
    <div class="flex-1 min-w-0 min-h-0 flex flex-col">
      <header class="h-14 shrink-0 px-4 flex items-center gap-3 bg-card border-b border-line">
        <Button class="lg:hidden" icon="pi pi-bars" text rounded :aria-label="t('shell.menu')" @click="sidebarOpen = true" />
        <GlobalSearchBox />
        <div class="flex-1 max-sm:hidden" />
        <NotificationsBell />
        <LanguageSwitcher />
        <Button :label="session.me?.name" icon="pi pi-user" text aria-haspopup="true" data-testid="user-menu" @click="(e: Event) => userMenu?.toggle(e)" />
        <Menu ref="userMenu" :model="userItems" popup />
      </header>
      <ImpersonationBanner />
      <AnnouncementBanners />
      <main class="app-content flex-1 min-h-0 overflow-auto p-4 lg:p-6" data-testid="app-content">
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
  themes: 'pi-sun',
  notification_channels: 'pi-send',
  external_access: 'pi-globe',
  translations: 'pi-language',
  system_settings: 'pi-sliders-h',
  audit_log: 'pi-history',
  error_monitoring: 'pi-exclamation-triangle',
  applications: 'pi-th-large',
  forms: 'pi-file-edit',
  blueprints: 'pi-clone',
  reference_data: 'pi-book',
  schema: 'pi-database',
  assignment_queues: 'pi-inbox',
  reason_codes: 'pi-comment',
  notifications: 'pi-envelope',
  documents: 'pi-file',
  automations: 'pi-bolt',
  scheduled_tasks: 'pi-clock',
  operations_center: 'pi-server',
  integrations: 'pi-link',
  extensions: 'pi-code',
  packages: 'pi-box',
  reports: 'pi-chart-bar',
  home_screens: 'pi-home',
  global_search: 'pi-search',
  announcements: 'pi-megaphone',
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
