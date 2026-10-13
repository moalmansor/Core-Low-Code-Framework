<script setup lang="ts">
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AlertsPanel from './AlertsPanel.vue'
import EmailsPanel from './EmailsPanel.vue'
import JobsPanel from './JobsPanel.vue'
import OverviewPanel from './OverviewPanel.vue'
import SubmissionsPanel from './SubmissionsPanel.vue'

/**
 * The Operations Center (specification §4.2): health at a glance, the
 * e-mail queue, failed submissions, failed jobs and threshold alerts. Every
 * action is audited on the server.
 */
const TABS = ['overview', 'emails', 'submissions', 'jobs', 'alerts'] as const
type TabKey = (typeof TABS)[number]
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const tab = ref<TabKey>(TABS.includes(route.params.tab as TabKey) ? (route.params.tab as TabKey) : 'overview')
watch(tab, (v) => router.replace({ name: 'admin.operations', params: { tab: v === 'overview' ? undefined : v } }))
const open = computed({ get: () => tab.value, set: (v: TabKey) => (tab.value = v) })
</script>

<template>
  <div class="flex flex-col" data-testid="operations-center">
    <h1 class="page-title !mb-1">{{ t('admin.area.operations_center') }}</h1>
    <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('ops.subtitle') }}</p>
    <Tabs v-model:value="open" scrollable class="mt-6">
      <TabList>
        <Tab v-for="k in TABS" :key="k" :value="k" :data-testid="`ops-tab-${k}`">{{ t(`ops.tab.${k}`) }}</Tab>
      </TabList>
      <TabPanels class="!px-0 !pt-6 !pb-0 !bg-transparent">
        <TabPanel v-for="k in TABS" :key="k" :value="k">
          <template v-if="k === tab">
            <OverviewPanel v-if="k === 'overview'" @open="(v: TabKey) => (tab = v)" />
            <EmailsPanel v-else-if="k === 'emails'" />
            <SubmissionsPanel v-else-if="k === 'submissions'" />
            <JobsPanel v-else-if="k === 'jobs'" />
            <AlertsPanel v-else-if="k === 'alerts'" />
          </template>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
