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
import CalendarsPanel from './reference/CalendarsPanel.vue'
import CurrenciesPanel from './reference/CurrenciesPanel.vue'
import SequencesPanel from './reference/SequencesPanel.vue'
import UnitsPanel from './reference/UnitsPanel.vue'

/** Reference data, calendars and numbering (specification §4.34); each tab is shown only to holders of its permission. */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const session = useSession()

const ALL = [
  { key: 'calendars', permission: 'system.manage_calendars' },
  { key: 'numbering', permission: 'system.manage_numbering' },
  { key: 'currencies', permission: 'system.manage_currencies' },
  { key: 'units', permission: 'system.manage_reference_data' },
]
const tabs = computed(() => ALL.filter((x) => session.can(x.permission)).map((x) => x.key))
const initial = String(route.params.tab ?? '')
const tab = ref(tabs.value.includes(initial) ? initial : (tabs.value[0] ?? 'calendars'))
watch(tab, (v) => router.replace({ name: 'admin.reference', params: { tab: v } }))
</script>

<template>
  <h1 class="page-title">{{ t('admin.area.reference_data') }}</h1>
  <Tabs v-model:value="tab" scrollable>
    <TabList>
      <Tab v-for="k in tabs" :key="k" :value="k" :data-testid="`ref-tab-${k}`">{{ t(`building.ref.tab.${k}`) }}</Tab>
    </TabList>
    <TabPanels>
      <TabPanel v-for="k in tabs" :key="k" :value="k">
        <template v-if="k === tab">
          <CalendarsPanel v-if="k === 'calendars'" />
          <SequencesPanel v-else-if="k === 'numbering'" />
          <CurrenciesPanel v-else-if="k === 'currencies'" />
          <UnitsPanel v-else />
        </template>
      </TabPanel>
    </TabPanels>
  </Tabs>
</template>
