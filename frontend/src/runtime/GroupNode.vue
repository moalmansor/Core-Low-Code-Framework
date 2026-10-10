<script setup lang="ts">
import Button from 'primevue/button'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRenderer } from './context'
import GroupBody from './GroupBody.vue'
import { labelOf, pickText } from './i18nText'
import RepeaterGroup from './RepeaterGroup.vue'
import { isGroupHidden, type RowRef } from './rules'
import SubformGroup from './SubformGroup.vue'
import type { ClientGroup, Row } from './types'

/** One group: section, fieldset, card, panel, accordion, row/column, tabs, wizard, repeater or sub-form. */
const props = defineProps<{ group: ClientGroup; row: RowRef | null }>()
const ctx = useRenderer()
const { t } = useI18n()

const title = computed(() => pickText(props.group.i18n?.title, ctx.locale.value))
const description = computed(() => pickText(props.group.i18n?.description, ctx.locale.value))
const printing = computed(() => ctx.mode.value === 'print')
const collapsible = computed(() => !printing.value && (props.group.collapsible === true || props.group.type === 'accordion'))
const open = ref(props.group.defaultState !== 'closed')
const icon = computed(() => props.group.layout?.icon ?? null)

const frame = computed(() => {
  const l = props.group.layout ?? {}
  return [
    l.border === 'strong' ? 'border-2 border-line-input' : l.border === 'subtle' ? 'border border-line' : '',
    l.background === 'accent' ? 'bg-primary-subtle' : l.background === 'subtle' ? 'bg-subtle' : '',
    l.border && l.border !== 'none' ? 'rounded-lg p-4' : l.background && l.background !== 'none' ? 'rounded-lg p-4' : '',
    l.cssClass ?? '',
  ]
})

const children = computed(() => (ctx.index.value.children.get(props.group.uuid) ?? []).filter((g) => !isGroupHidden(ctx.index.value, g.uuid, ctx.state.value, props.row)))

/** Paths with an error below a group, for the error dot on tabs and steps. */
function hasErrors(g: ClientGroup): boolean {
  const index = ctx.index.value
  for (const f of index.fieldsInGroup(g.uuid)) {
    const repeater = index.fieldRepeater.get(f.uuid)
    if (repeater === undefined) {
      if (ctx.errorsAt(f.key).length) return true
      continue
    }
    const key = index.repeaters.get(repeater)!.group.key
    if (ctx.errorsAt(key).length) return true
    const rows = Array.isArray(ctx.values.value[key]) ? (ctx.values.value[key] as Row[]) : []
    if (rows.some((_r, i) => ctx.errorsAt(`${key}.${i}.${f.key}`).length)) return true
  }
  return ctx.errorsAt(`_group.${g.key}`).length > 0
}

// Tabs
const activeTab = ref<string>('')
watch(
  children,
  (list) => {
    if (!list.some((g) => g.uuid === activeTab.value)) activeTab.value = list[0]?.uuid ?? ''
  },
  { immediate: true },
)

// Wizard
const step = ref(0)
const visited = ref(new Set<number>([0]))
watch(children, (list) => {
  if (step.value >= list.length) step.value = Math.max(0, list.length - 1)
})
const allowJump = computed(() => props.group.wizard?.allowJump === true)
function canLeave(): boolean {
  const current = children.value[step.value]
  if (!current || props.group.wizard?.validateBeforeNext === false) return true
  if (ctx.mode.value !== 'create' && ctx.mode.value !== 'edit') return true
  return ctx.validateGroup(current)
}
function goTo(i: number): void {
  if (i === step.value || i < 0 || i >= children.value.length) return
  if (i > step.value && !canLeave()) return
  if (i > step.value + 1 && !allowJump.value && !visited.value.has(i)) return
  step.value = i
  visited.value.add(i)
}
const groupErrors = computed(() => ctx.errorsAt(`_group.${props.group.key}`))
</script>

<template>
  <RepeaterGroup v-if="group.type === 'repeater'" :group="group" />
  <SubformGroup v-else-if="group.type === 'subform'" :group="group" />

  <section v-else-if="group.type === 'tabs'" :class="frame" :data-group="group.key">
    <h3 v-if="title" class="text-base font-semibold mb-2">{{ title }}</h3>
    <template v-if="printing">
      <div v-for="tab in children" :key="tab.uuid" class="mb-4">
        <h4 class="font-semibold mb-2">{{ labelOf(tab.i18n?.title, ctx.locale.value, tab.key) }}</h4>
        <GroupBody :group="tab" :row="row" />
      </div>
    </template>
    <Tabs v-else v-model:value="activeTab" scrollable>
      <TabList>
        <Tab v-for="tab in children" :key="tab.uuid" :value="tab.uuid" :data-testid="`tab-${tab.key}`">
          <i v-if="tab.layout?.icon" :class="`${tab.layout.icon} me-2`" />{{ labelOf(tab.i18n?.title, ctx.locale.value, tab.key) }}
          <span v-if="hasErrors(tab)" class="ms-2 inline-block w-2 h-2 rounded-full bg-danger" :aria-label="t('runtime.has_errors')" />
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel v-for="tab in children" :key="tab.uuid" :value="tab.uuid">
          <p v-if="pickText(tab.i18n?.description, ctx.locale.value)" class="text-sm text-muted-color mb-3">{{ pickText(tab.i18n?.description, ctx.locale.value) }}</p>
          <GroupBody :group="tab" :row="row" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </section>

  <section v-else-if="group.type === 'wizard'" :class="frame" :data-group="group.key">
    <h3 v-if="title" class="text-base font-semibold mb-2">{{ title }}</h3>
    <template v-if="ctx.mode.value === 'view' || printing">
      <div v-for="s in children" :key="s.uuid" class="mb-4">
        <h4 class="font-semibold mb-2">{{ labelOf(s.i18n?.title, ctx.locale.value, s.key) }}</h4>
        <GroupBody :group="s" :row="row" />
      </div>
    </template>
    <template v-else>
      <ol class="flex flex-wrap gap-2 mb-4" :aria-label="t('runtime.wizard_steps')">
        <li v-for="(s, i) in children" :key="s.uuid">
          <button
            type="button"
            class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-sm"
            :class="i === step ? 'border-primary bg-primary text-primary-contrast' : 'border-line-strong'"
            :aria-current="i === step ? 'step' : undefined"
            :disabled="!(allowJump || visited.has(i) || i === step + 1)"
            :data-testid="`step-${s.key}`"
            @click="goTo(i)"
          >
            <span class="font-semibold">{{ i + 1 }}</span>
            <span>{{ labelOf(s.i18n?.title, ctx.locale.value, s.key) }}</span>
            <span v-if="hasErrors(s)" class="inline-block w-2 h-2 rounded-full bg-danger" :aria-label="t('runtime.has_errors')" />
          </button>
        </li>
      </ol>
      <template v-for="(s, i) in children" :key="s.uuid">
        <div v-if="i === step">
          <p v-if="pickText(s.i18n?.description, ctx.locale.value)" class="text-sm text-muted-color mb-3">{{ pickText(s.i18n?.description, ctx.locale.value) }}</p>
          <GroupBody :group="s" :row="row" />
        </div>
      </template>
      <div class="flex justify-between mt-4 lcf-no-print">
        <Button type="button" :label="t('runtime.previous')" icon="pi pi-arrow-left rtl:rotate-180" severity="secondary" outlined :disabled="step === 0" @click="goTo(step - 1)" />
        <Button
          type="button"
          :label="t('runtime.next')"
          icon="pi pi-arrow-right rtl:rotate-180"
          icon-pos="right"
          :disabled="step >= children.length - 1"
          data-testid="wizard-next"
          @click="goTo(step + 1)"
        />
      </div>
    </template>
  </section>

  <GroupBody v-else-if="group.type === 'row' || group.type === 'column'" :group="group" :row="row" :class="frame" />

  <fieldset v-else-if="group.type === 'fieldset'" class="border border-line rounded-lg p-4" :class="frame" :data-group="group.key">
    <legend v-if="title" class="px-2 font-semibold">
      <i v-if="icon" :class="`${icon} me-2`" />{{ title }}
      <button v-if="collapsible" type="button" class="ms-2 text-muted-color" :aria-expanded="open" :aria-label="t('runtime.toggle_section')" @click="open = !open">
        <i :class="open ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" />
      </button>
    </legend>
    <p v-if="description" class="text-sm text-muted-color mb-3">{{ description }}</p>
    <GroupBody v-show="open || !collapsible" :group="group" :row="row" />
    <p v-for="(e, i) in groupErrors" :key="i" class="field-error mt-2">{{ e }}</p>
  </fieldset>

  <section
    v-else
    class="lcf-section"
    :class="[group.type === 'card' ? 'rounded-xl border border-line bg-card shadow-sm p-4' : '', group.type === 'panel' || group.type === 'accordion' ? 'rounded-lg border border-line' : '', frame]"
    :data-group="group.key"
  >
    <header
      v-if="title || collapsible"
      class="flex items-center gap-2"
      :class="group.type === 'panel' || group.type === 'accordion' ? 'px-4 py-3 bg-subtle rounded-t-lg' : group.type === 'card' ? 'mb-3' : 'lcf-section-head'"
    >
      <i v-if="icon" :class="icon" />
      <h3 class="text-base font-semibold flex-1">{{ title }}</h3>
      <Button
        v-if="collapsible"
        type="button"
        :icon="open ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
        text
        rounded
        size="small"
        :aria-expanded="open"
        :aria-label="t('runtime.toggle_section')"
        class="lcf-no-print"
        @click="open = !open"
      />
    </header>
    <div v-show="open || !collapsible" :class="group.type === 'panel' || group.type === 'accordion' ? 'p-4' : ''">
      <p v-if="description" class="text-sm text-muted-color mb-3">{{ description }}</p>
      <GroupBody :group="group" :row="row" />
      <p v-for="(e, i) in groupErrors" :key="i" class="field-error mt-2">{{ e }}</p>
    </div>
  </section>
</template>
