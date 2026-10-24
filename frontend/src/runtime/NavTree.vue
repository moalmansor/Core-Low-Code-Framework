<script lang="ts">
/** A published menu item (GET /navigation), resolved for the signed-in user. */
export interface NavItem {
  uuid: string
  type: 'form' | 'collection' | 'page' | 'report' | 'dashboard' | 'my_work' | 'link' | 'separator' | 'header'
  target?: string | null
  /** True when the item shows a live count (GET /navigation/badges). */
  badge?: boolean
  url?: string | null
  open_in_new_tab?: boolean
  icon?: string | null
  label?: string | null
  children?: NavItem[]
}

/** Where an item that opens something inside the application goes; null for links, headers and separators. */
export function navPath(item: NavItem): string | null {
  if (item.type === 'my_work') return '/my-work'
  if (!item.target) return null
  if (item.type === 'form' || item.type === 'collection') return `/app/${item.target}`
  if (item.type === 'page') return `/pages/${item.target}`
  if (item.type === 'report') return `/reports/${item.target}`
  if (item.type === 'dashboard') return `/dashboards/${item.target}`
  return null
}

const DEFAULT_ICONS: Partial<Record<NavItem['type'], string>> = {
  form: 'pi pi-file',
  collection: 'pi pi-table',
  page: 'pi pi-book',
  report: 'pi pi-chart-bar',
  dashboard: 'pi pi-objects-column',
  my_work: 'pi pi-inbox',
}
</script>

<script setup lang="ts">
import { ref } from 'vue'
import { safeHref } from './SafeHtml'

/** Sidebar menu of an application: items opening forms, pages, reports and dashboards, links, headers, separators, nested groups, live counts. */
const props = defineProps<{ items: NavItem[]; depth?: number; active?: string | null; badges?: Record<string, number> }>()
const badgeText = (uuid: string): string | null => {
  const n = props.badges?.[uuid]
  if (n === undefined || n === 0) return null
  return n > 999 ? '999+' : String(n)
}
const open = ref<Record<string, boolean>>({})
const toggle = (uuid: string) => (open.value = { ...open.value, [uuid]: !(open.value[uuid] ?? true) })
const isOpen = (uuid: string) => open.value[uuid] ?? true
</script>

<template>
  <template v-for="item in items" :key="item.uuid">
    <hr v-if="item.type === 'separator'" class="my-2 border-line" />
    <template v-else-if="item.type === 'header'">
      <button type="button" class="nav-header" :style="{ paddingInlineStart: `${0.75 + (depth ?? 0) * 0.75}rem` }" :aria-expanded="isOpen(item.uuid)" @click="toggle(item.uuid)">
        <i v-if="item.icon" :class="item.icon" />
        <span class="flex-1 text-start">{{ item.label }}</span>
        <i :class="isOpen(item.uuid) ? 'pi pi-chevron-down' : 'pi pi-chevron-right rtl:rotate-180'" class="text-xs" />
      </button>
      <NavTree v-if="isOpen(item.uuid) && item.children?.length" :items="item.children" :depth="(depth ?? 0) + 1" :active="active" :badges="badges" />
    </template>
    <template v-else>
      <RouterLink
        v-if="navPath(item)"
        class="nav-link"
        :to="navPath(item)!"
        active-class=""
        exact-active-class=""
        :class="{ 'nav-active': active === item.uuid }"
        :aria-current="active === item.uuid ? 'page' : undefined"
        :style="{ paddingInlineStart: `${0.75 + (depth ?? 0) * 0.75}rem` }"
        :data-testid="`nav-item-${item.uuid}`"
      >
        <i :class="item.icon || DEFAULT_ICONS[item.type]" />
        <span class="truncate flex-1">{{ item.label }}</span>
        <span v-if="item.badge && badgeText(item.uuid)" class="nav-badge" :data-testid="`nav-badge-${item.uuid}`">{{ badgeText(item.uuid) }}</span>
      </RouterLink>
      <a
        v-else-if="item.type === 'link' && safeHref(item.url ?? null)"
        class="nav-link"
        :href="safeHref(item.url ?? null)!"
        :target="item.open_in_new_tab ? '_blank' : undefined"
        :rel="item.open_in_new_tab ? 'noopener noreferrer' : undefined"
        :style="{ paddingInlineStart: `${0.75 + (depth ?? 0) * 0.75}rem` }"
      >
        <i :class="item.icon || 'pi pi-link'" />
        <span class="truncate">{{ item.label }}</span>
        <i v-if="item.open_in_new_tab" class="pi pi-external-link text-xs ms-auto" />
      </a>
      <NavTree v-if="item.children?.length" :items="item.children" :depth="(depth ?? 0) + 1" :active="active" :badges="badges" />
    </template>
  </template>
</template>

<style scoped>
.nav-link,
.nav-header {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  color: var(--text);
  text-decoration: none;
  width: 100%;
}
.nav-header {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-muted);
  background: none;
  border: 0;
  cursor: pointer;
}
.nav-link:hover,
.nav-header:hover {
  background: var(--bg-subtle);
}
.nav-badge {
  min-width: 1.5rem;
  padding: 0 0.375rem;
  border-radius: 999px;
  background: var(--primary);
  color: var(--on-primary);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.25rem;
  text-align: center;
  font-variant-numeric: tabular-nums;
}
.nav-active {
  background: var(--primary-subtle);
  color: var(--on-primary-subtle);
  font-weight: 600;
}
</style>
