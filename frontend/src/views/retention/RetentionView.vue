<script setup lang="ts">
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useSession } from '@/stores/session'
import ArchivePanel from './ArchivePanel.vue'
import HoldsPanel from './HoldsPanel.vue'
import PersonalDataPanel from './PersonalDataPanel.vue'
import PoliciesPanel from './PoliciesPanel.vue'
import StoragePanel from './StoragePanel.vue'

/**
 * Retention and personal data (specification §4.26): retention policies and
 * their runs, the archive, legal holds, personal data requests, and storage
 * (log partitions, table growth, quotas and usage). Each tab shows to holders
 * of its permission; the server checks it again on every request.
 */
const session = useSession()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const ALL = [
  { key: 'policies', perm: 'system.manage_retention' },
  { key: 'archive', perm: 'system.manage_retention' },
  { key: 'holds', perm: 'system.apply_legal_hold' },
  { key: 'personal', perm: 'system.manage_personal_data_requests' },
  { key: 'storage', perm: 'system.manage_retention' },
] as const
type TabKey = (typeof ALL)[number]['key']
const tabs = computed(() => ALL.filter((x) => session.can(x.perm)).map((x) => x.key))
const initial = (route.params.tab as TabKey) || tabs.value[0] || 'policies'
const tab = ref<TabKey>(tabs.value.includes(initial) ? initial : (tabs.value[0] ?? 'policies'))
watch(tab, (v) => router.replace({ name: 'admin.retention', params: { tab: v === tabs.value[0] ? undefined : v } }))
</script>

<template>
  <div class="flex flex-col" data-testid="retention-view">
    <h1 class="page-title !mb-1">{{ t('admin.area.retention') }}</h1>
    <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('ret.subtitle') }}</p>
    <Tabs v-model:value="tab" scrollable class="mt-6">
      <TabList>
        <Tab v-for="k in tabs" :key="k" :value="k" :data-testid="`ret-tab-${k}`">{{ t(`ret.tab.${k}`) }}</Tab>
      </TabList>
      <TabPanels class="!px-0 !pt-6 !pb-0 !bg-transparent">
        <TabPanel v-for="k in tabs" :key="k" :value="k">
          <template v-if="k === tab">
            <PoliciesPanel v-if="k === 'policies'" />
            <ArchivePanel v-else-if="k === 'archive'" />
            <HoldsPanel v-else-if="k === 'holds'" />
            <PersonalDataPanel v-else-if="k === 'personal'" />
            <StoragePanel v-else-if="k === 'storage'" />
          </template>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
