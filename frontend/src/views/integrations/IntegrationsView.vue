<script setup lang="ts">
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import InboundPanel from './InboundPanel.vue'
import TokensPanel from './TokensPanel.vue'
import WebhooksPanel from './WebhooksPanel.vue'

/**
 * Integrations (specification §4.22, §4.33): outgoing webhooks, incoming
 * endpoints and every API token of the organization, for holders of
 * "Manage integrations".
 */
const TABS = ['webhooks', 'inbound', 'tokens'] as const
type TabKey = (typeof TABS)[number]
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const tab = ref<TabKey>(TABS.includes(route.params.tab as TabKey) ? (route.params.tab as TabKey) : 'webhooks')
watch(tab, (v) => router.replace({ name: 'admin.integrations', params: { tab: v === 'webhooks' ? undefined : v } }))
const open = computed({ get: () => tab.value, set: (v: TabKey) => (tab.value = v) })
</script>

<template>
  <div class="flex flex-col" data-testid="integrations-view">
    <h1 class="page-title !mb-1">{{ t('admin.area.integrations') }}</h1>
    <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('intg.subtitle') }}</p>
    <Tabs v-model:value="open" scrollable class="mt-6">
      <TabList>
        <Tab v-for="k in TABS" :key="k" :value="k" :data-testid="`intg-tab-${k}`">{{ t(`intg.tab.${k}`) }}</Tab>
      </TabList>
      <TabPanels class="!px-0 !pt-6 !pb-0 !bg-transparent">
        <TabPanel v-for="k in TABS" :key="k" :value="k">
          <template v-if="k === tab">
            <WebhooksPanel v-if="k === 'webhooks'" />
            <InboundPanel v-else-if="k === 'inbound'" />
            <TokensPanel v-else-if="k === 'tokens'" />
          </template>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
