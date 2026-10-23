<script setup lang="ts">
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AccountsPanel from './AccountsPanel.vue'
import FormsPanel from './FormsPanel.vue'

/**
 * External access (specification §4.32, ADR-0058): forms published outside
 * and the external portal's accounts, roles and registration, for holders
 * of "Manage external access".
 */
const TABS = ['forms', 'accounts'] as const
type TabKey = (typeof TABS)[number]
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const tab = ref<TabKey>(TABS.includes(route.params.tab as TabKey) ? (route.params.tab as TabKey) : 'forms')
watch(tab, (v) => router.replace({ name: 'admin.external', params: { tab: v === 'forms' ? undefined : v } }))
const open = computed({ get: () => tab.value, set: (v: TabKey) => (tab.value = v) })
</script>

<template>
  <div class="flex flex-col" data-testid="external-admin">
    <h1 class="page-title !mb-1">{{ t('admin.area.external_access') }}</h1>
    <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('xa.subtitle') }}</p>
    <Tabs v-model:value="open" scrollable class="mt-6">
      <TabList>
        <Tab v-for="k in TABS" :key="k" :value="k" :data-testid="`xa-tab-${k}`">{{ t(`xa.tab.${k}`) }}</Tab>
      </TabList>
      <TabPanels class="!px-0 !pt-6 !pb-0 !bg-transparent">
        <TabPanel v-for="k in TABS" :key="k" :value="k">
          <template v-if="k === tab">
            <FormsPanel v-if="k === 'forms'" />
            <AccountsPanel v-else />
          </template>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
