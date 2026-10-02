<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import { icons } from '@/layouts/AppShell.vue'

interface Area {
  key: string
  section: string
  route: string
}
const { t } = useI18n()
const areas = ref<Area[]>([])
onMounted(async () => {
  areas.value = (await get<{ data: { areas: Area[] } }>('/admin/console')).data.areas
})
const sections = computed(() => {
  const g = new Map<string, Area[]>()
  for (const a of areas.value) g.set(a.section, [...(g.get(a.section) ?? []), a])
  return [...g.entries()]
})
</script>

<template>
  <h1 class="page-title">{{ t('admin.title') }}</h1>
  <p v-if="!areas.length" class="text-muted-color">{{ t('admin.no_areas') }}</p>
  <section v-for="[section, items] in sections" :key="section" class="mb-6">
    <h2 class="font-semibold mb-3">{{ t(`admin.section.${section}`) }}</h2>
    <div class="grid gap-3 grid-cols-[repeat(auto-fill,minmax(14rem,1fr))]">
      <RouterLink
        v-for="a in items"
        :key="a.key"
        :to="a.route"
        class="p-4 rounded-xl bg-surface-0 dark:bg-surface-900 shadow-sm hover:shadow flex gap-3 items-start no-underline text-color"
        :data-testid="`area-${a.key}`"
      >
        <i :class="`pi ${icons[a.key] ?? 'pi-circle'} text-primary text-xl`" />
        <div>
          <div class="font-medium">{{ t(`admin.area.${a.key}`) }}</div>
          <div class="text-sm text-muted-color">{{ t(`admin.area_hint.${a.key}`) }}</div>
        </div>
      </RouterLink>
    </div>
  </section>
</template>
