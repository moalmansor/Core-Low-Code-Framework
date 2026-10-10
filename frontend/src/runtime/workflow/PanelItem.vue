<script setup lang="ts">
import Button from 'primevue/button'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { computed, inject, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import FormRenderer from '../FormRenderer.vue'
import { formatLoose } from '../format'
import SafeHtml from '../SafeHtml'
import { bodyFor, PANEL_CONTEXT, type Panel, type RelatedData } from './panels'
import StatusBadge from './StatusBadge.vue'
import StatusTimeline from './StatusTimeline.vue'

/** One View Mode panel and, for containers, its children (rendered recursively). */
const props = defineProps<{ panel: Panel }>()
const { t } = useI18n()
const ctx = inject(PANEL_CONTEXT)!
const kids = computed(() => ctx.children.get(props.panel.uuid) ?? [])
const tab = ref(kids.value[0]?.uuid ?? '')
const collapsed = ref(false)
const related = computed(() => props.panel.data as RelatedData | null)
const items = computed(() => (props.panel.data as { label: string; value: unknown }[] | null) ?? [])
const summary = computed(() => (props.panel.data as { value: unknown } | null)?.value ?? null)
const grid = computed(() => ({ 2: 'md:grid-cols-2', 3: 'md:grid-cols-3' })[props.panel.config.columns as 2 | 3] ?? '')
const cell = (v: unknown) => formatLoose(v) || '—'
</script>

<template>
  <Tabs v-if="panel.type === 'tabs'" v-model:value="tab" :data-testid="`panel-${panel.type}`">
    <TabList>
      <Tab v-for="k in kids" :key="k.uuid" :value="k.uuid">{{ k.title }}</Tab>
    </TabList>
    <TabPanels>
      <TabPanel v-for="k in kids" :key="k.uuid" :value="k.uuid">
        <div class="grid gap-4" :class="{ 2: 'md:grid-cols-2', 3: 'md:grid-cols-3' }[k.config.columns as 2 | 3] ?? ''">
          <PanelItem v-for="c in ctx.children.get(k.uuid) ?? []" :key="c.uuid" :panel="c" />
        </div>
      </TabPanel>
    </TabPanels>
  </Tabs>

  <section v-else class="rounded-xl border border-line p-3 min-w-0" :data-testid="`panel-${panel.type}`">
    <header v-if="panel.title || panel.config.collapsible" class="flex items-center gap-2 mb-2">
      <h2 class="font-semibold flex-1">{{ panel.title }}</h2>
      <Button
        v-if="panel.config.collapsible"
        :icon="collapsed ? 'pi pi-chevron-down' : 'pi pi-chevron-up'"
        text
        size="small"
        :aria-label="collapsed ? t('panels_run.expand') : t('panels_run.collapse')"
        :aria-expanded="!collapsed"
        @click="collapsed = !collapsed"
      />
    </header>
    <template v-if="!collapsed">
      <div v-if="panel.type === 'section' || panel.type === 'tab'" class="grid gap-4" :class="grid">
        <PanelItem v-for="c in kids" :key="c.uuid" :panel="c" />
      </div>

      <FormRenderer
        v-else-if="panel.type === 'form_body'"
        :definition="bodyFor(ctx.definition, (panel.config.groups as string[]) ?? [])"
        :model-value="ctx.record.values"
        mode="view"
        :form-uuid="ctx.form"
        :references="ctx.record.references"
      />

      <dl v-else-if="panel.type === 'derived_fields'" class="grid gap-2 md:grid-cols-2">
        <div v-for="(it, i) in items" :key="i">
          <dt class="text-sm text-muted-color">{{ it.label }}</dt>
          <dd dir="auto">{{ cell(it.value) }}</dd>
        </div>
      </dl>

      <div v-else-if="panel.type === 'summary_widget'" class="text-2xl font-semibold ltr-value">{{ summary === null ? '—' : cell(summary) }}</div>

      <template v-else-if="panel.type === 'related_table'">
        <p v-if="!related" class="text-sm text-muted-color">{{ t('panels_run.no_access') }}</p>
        <template v-else>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-start border-b border-line">
                  <th class="p-2 text-start">{{ t('panels_run.record') }}</th>
                  <th class="p-2 text-start">{{ t('workflow.status') }}</th>
                  <th v-for="c in related.columns" :key="c.key" class="p-2 text-start">{{ c.label }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in related.rows" :key="r.uuid" class="border-b border-line">
                  <td class="p-2">
                    <RouterLink :to="{ name: 'records.view', params: { form: related.form.uuid, record: r.uuid } }" class="text-link" dir="auto">{{ r.title ?? r.uuid.slice(0, 8) }}</RouterLink>
                  </td>
                  <td class="p-2"><StatusBadge :status="r.status" size="sm" /></td>
                  <td v-for="c in related.columns" :key="c.key" class="p-2" dir="auto">{{ cell(r.cells[c.key]) }}</td>
                </tr>
                <tr v-if="!related.rows.length">
                  <td :colspan="related.columns.length + 2" class="p-2 text-muted-color">{{ t('panels_run.none') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="flex items-center gap-2 mt-2 text-sm">
            <span class="text-muted-color flex-1">{{ t('panels_run.total', { n: related.total }) }}</span>
            <RouterLink v-if="related.can_create" :to="{ name: 'records.create', params: { form: related.form.uuid } }" class="text-link">{{ t('panels_run.add') }}</RouterLink>
          </div>
        </template>
      </template>

      <StatusTimeline v-else-if="panel.type === 'status_timeline'" :form="ctx.form" :record="ctx.record.uuid" :version="ctx.record.row_version" />
      <component :is="ctx.slots.comments" v-else-if="panel.type === 'comments'" />
      <component :is="ctx.slots.attachments" v-else-if="panel.type === 'attachments'" />
      <SafeHtml v-else-if="panel.type === 'html'" :html="panel.content ?? ''" />
    </template>
  </section>
</template>
