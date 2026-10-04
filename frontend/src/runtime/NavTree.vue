<script setup lang="ts">
import { ref } from 'vue'
import { safeHref } from './SafeHtml'

/** A published menu item (GET /navigation), resolved for the signed-in user. */
export interface NavItem {
  uuid: string
  type: 'form' | 'collection' | 'link' | 'separator' | 'header'
  target?: string | null
  url?: string | null
  open_in_new_tab?: boolean
  icon?: string | null
  label?: string | null
  children?: NavItem[]
}

/** Sidebar menu of an application: form and collection links, external links, headers, separators, nested groups. */
defineProps<{ items: NavItem[]; depth?: number }>()
const open = ref<Record<string, boolean>>({})
const toggle = (uuid: string) => (open.value = { ...open.value, [uuid]: !(open.value[uuid] ?? true) })
const isOpen = (uuid: string) => open.value[uuid] ?? true
</script>

<template>
  <template v-for="item in items" :key="item.uuid">
    <hr v-if="item.type === 'separator'" class="my-2 border-surface-200 dark:border-surface-700" />
    <template v-else-if="item.type === 'header'">
      <button type="button" class="nav-header" :style="{ paddingInlineStart: `${0.75 + (depth ?? 0) * 0.75}rem` }" :aria-expanded="isOpen(item.uuid)" @click="toggle(item.uuid)">
        <i v-if="item.icon" :class="item.icon" />
        <span class="flex-1 text-start">{{ item.label }}</span>
        <i :class="isOpen(item.uuid) ? 'pi pi-chevron-down' : 'pi pi-chevron-right rtl:rotate-180'" class="text-xs" />
      </button>
      <NavTree v-if="isOpen(item.uuid) && item.children?.length" :items="item.children" :depth="(depth ?? 0) + 1" />
    </template>
    <template v-else>
      <RouterLink
        v-if="(item.type === 'form' || item.type === 'collection') && item.target"
        class="nav-link"
        :to="`/app/${item.target}`"
        active-class="nav-active"
        :style="{ paddingInlineStart: `${0.75 + (depth ?? 0) * 0.75}rem` }"
        :data-testid="`nav-item-${item.uuid}`"
      >
        <i :class="item.icon || (item.type === 'collection' ? 'pi pi-table' : 'pi pi-file')" />
        <span class="truncate">{{ item.label }}</span>
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
      <NavTree v-if="item.children?.length" :items="item.children" :depth="(depth ?? 0) + 1" />
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
  color: var(--p-text-color);
  text-decoration: none;
  width: 100%;
}
.nav-header {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
  background: none;
  border: 0;
  cursor: pointer;
}
.nav-link:hover,
.nav-header:hover {
  background: var(--p-surface-100);
}
.nav-active {
  background: var(--p-primary-50);
  color: var(--p-primary-700);
  font-weight: 600;
}
:global(.app-dark) .nav-link:hover,
:global(.app-dark) .nav-header:hover {
  background: var(--p-surface-800);
}
</style>
