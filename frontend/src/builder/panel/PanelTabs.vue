<script setup lang="ts">
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePanel } from './panel'

/**
 * The tab bar of a properties panel: a few tabs that never scroll, a "More"
 * menu for the rest, and "Find a setting", which searches every tab.
 */
const props = defineProps<{ tabs: { value: string; label: string }[]; more?: { value: string; label: string }[] }>()
const { t } = useI18n()
const panel = usePanel()
const menu = ref<InstanceType<typeof Menu> | null>(null)
const moreActive = computed(() => (props.more ?? []).find((m) => m.value === panel.tab.value) ?? null)
const moreItems = computed(() => (props.more ?? []).map((m) => ({ label: m.label, icon: m.value === panel.tab.value ? 'pi pi-check' : undefined, command: () => select(m.value), data: m.value })))
function select(value: string): void {
  panel.tab.value = value
  panel.query.value = ''
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <IconField>
      <InputIcon class="pi pi-search" />
      <InputText v-model="panel.query.value" size="small" class="w-full" type="search" :placeholder="t('builder.panel.find')" :aria-label="t('builder.panel.find')" data-testid="panel-search" />
    </IconField>
    <div v-if="!panel.query.value.trim()" class="panel-tabs" role="tablist">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        role="tab"
        :aria-selected="panel.tab.value === tab.value"
        :class="['panel-tab', { 'is-active': panel.tab.value === tab.value }]"
        :data-testid="`tab-${tab.value}`"
        @click="select(tab.value)"
      >
        {{ tab.label }}
      </button>
      <template v-if="more?.length">
        <button type="button" :class="['panel-tab', { 'is-active': moreActive }]" aria-haspopup="true" data-testid="tab-more" @click="(e: Event) => menu?.toggle(e)">
          {{ moreActive ? moreActive.label : t('builder.panel.more') }} <i class="pi pi-chevron-down text-[0.625rem]" aria-hidden="true" />
        </button>
        <Menu ref="menu" :model="moreItems" popup>
          <template #item="{ item, props: itemProps }">
            <a v-bind="itemProps.action" :data-testid="`tab-${item.data}`"
              ><i v-if="item.icon" :class="item.icon" /><span>{{ item.label }}</span></a
            >
          </template>
        </Menu>
      </template>
    </div>
    <p v-else class="text-xs text-muted-color flex items-center gap-2">
      {{ t('builder.panel.searching') }}
      <Button :label="t('builder.panel.clear')" size="small" text @click="panel.query.value = ''" />
    </p>
  </div>
</template>

<style scoped>
.panel-tabs {
  display: flex;
  gap: 0.125rem;
  border-block-end: 1px solid var(--border);
}
.panel-tab {
  padding: 0.5rem 0.5rem;
  font-size: var(--text-size-sm);
  font-weight: 500;
  color: var(--text-muted);
  background: none;
  border: 0;
  border-block-end: 2px solid transparent;
  margin-block-end: -1px;
  cursor: pointer;
  white-space: nowrap;
}
.panel-tab:hover {
  color: var(--text);
}
.panel-tab.is-active {
  color: var(--on-primary-subtle);
  border-color: var(--primary);
  font-weight: 600;
}
</style>
