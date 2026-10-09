<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { pick } from '@/builder/conditions/scope'
import { createBuilder, provideBuilder } from '@/builder/useBuilder'
import AssignmentEditor from './AssignmentEditor.vue'
import JustificationEditor from './JustificationEditor.vue'
import PanelsEditor from './PanelsEditor.vue'
import PreviewsEditor from './PreviewsEditor.vue'
import PrintLayoutsEditor from './PrintLayoutsEditor.vue'
import RecordAccessEditor from './RecordAccessEditor.vue'
import SlaEditor from './SlaEditor.vue'
import ViewsEditor from './ViewsEditor.vue'
import WorkflowDesigner from './WorkflowDesigner.vue'

/**
 * Configuration of a form beyond its fields (specification §4.12–§4.25):
 * workflow, SLA, record-level access, justification, assignment, table
 * views, View Mode panels, reference previews and print layouts. The form's
 * draft is loaded read-only so the expression and field pickers know the
 * form's fields; every tab saves its own document.
 */
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const formUuid = String(route.params.form)
const builder = createBuilder(formUuid)
provideBuilder(builder)

const TABS = ['workflow', 'sla', 'record_access', 'justification', 'assignment', 'views', 'panels', 'previews', 'print'] as const
type TabKey = (typeof TABS)[number]
const tab = ref<TabKey>(TABS.includes(route.params.tab as TabKey) ? (route.params.tab as TabKey) : 'workflow')
watch(tab, (v) => router.replace({ name: 'admin.forms.configure', params: { form: formUuid, tab: v } }))

const title = computed(() => (builder.doc ? pick(builder.doc.form.i18n?.name, builder.locale, builder.doc.form.key) : ''))
const ready = ref(false)
onMounted(async () => {
  try {
    await builder.load()
  } catch {
    // shown through builder.loadError
  }
  ready.value = true
})
onBeforeUnmount(() => builder.cancelTimers())
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="form-config">
    <div class="flex flex-wrap items-center gap-2">
      <Button icon="pi pi-arrow-left" text :aria-label="t('common.back')" @click="router.push({ name: 'admin.forms' })" />
      <h1 class="page-title m-0">{{ t('formconfig.title', { form: title }) }}</h1>
      <span class="flex-1" />
      <Button icon="pi pi-pencil" :label="t('building.forms.open_builder')" size="small" outlined @click="router.push({ name: 'admin.forms.builder', params: { form: formUuid } })" />
    </div>
    <Message v-if="builder.loadError" severity="error">{{ builder.loadError }}</Message>
    <div v-if="!ready" class="flex justify-center p-6"><ProgressSpinner /></div>
    <Tabs v-else-if="builder.doc" v-model:value="tab" scrollable>
      <TabList>
        <Tab v-for="k in TABS" :key="k" :value="k" :data-testid="`config-tab-${k}`">{{ t(`formconfig.tab.${k}`) }}</Tab>
      </TabList>
      <TabPanels>
        <TabPanel v-for="k in TABS" :key="k" :value="k">
          <template v-if="k === tab">
            <WorkflowDesigner v-if="k === 'workflow'" :form="formUuid" />
            <SlaEditor v-else-if="k === 'sla'" :form="formUuid" />
            <RecordAccessEditor v-else-if="k === 'record_access'" :form="formUuid" />
            <JustificationEditor v-else-if="k === 'justification'" :form="formUuid" />
            <AssignmentEditor v-else-if="k === 'assignment'" :form="formUuid" />
            <ViewsEditor v-else-if="k === 'views'" :form="formUuid" />
            <PanelsEditor v-else-if="k === 'panels'" :form="formUuid" />
            <PreviewsEditor v-else-if="k === 'previews'" :form="formUuid" />
            <PrintLayoutsEditor v-else-if="k === 'print'" :form="formUuid" />
          </template>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
