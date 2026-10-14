<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import FormPicker from '@/builder/FormPicker.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '@/views/admin/building/shared'
import { joinList } from '@/runtime/lists'
import { automationsApi, runSeverity, type AutomationSummary } from './api'

/**
 * Automations (specification §4.31): those of one form (the form
 * configuration's "Automations" tab) or of every form (Admin › Automations),
 * with what starts each one, how its last run ended, and a switch to turn it
 * on or off. Opens the builder.
 */
const props = defineProps<{ form?: string }>()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const rows = ref<AutomationSummary[] | null>(null)
const published = ref(true)
const loadError = ref('')
const newForm = ref<string | null>(null)

async function load(): Promise<void> {
  try {
    if (props.form) {
      const r = await automationsApi.list(props.form)
      rows.value = r.automations
      published.value = r.published
    } else {
      rows.value = await automationsApi.all()
    }
  } catch (e) {
    loadError.value = errorText(e, t('auto.load_failed'))
  }
}
onMounted(load)

const open = (form: string, uuid: string) => router.push({ name: 'admin.automations.edit', params: { form, automation: uuid } })
async function toggle(a: AutomationSummary, on: boolean): Promise<void> {
  try {
    await automationsApi.enable(a.uuid, on)
    a.enabled = on
    toast.add({ severity: 'success', summary: on ? t('auto.turned_on') : t('auto.turned_off'), life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('auto.save_failed')), life: 6000 })
  }
}
async function remove(a: AutomationSummary): Promise<void> {
  if (!window.confirm(t('auto.delete_confirm', { name: a.name }))) return
  try {
    await automationsApi.remove(a.uuid)
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('auto.save_failed')), life: 6000 })
  }
}
const triggersText = (a: AutomationSummary) =>
  joinList(
    [...new Set(a.triggers)].map((x) => t(`auto.trigger.${x}`)),
    locale.value,
  )
const subtitle = (a: AutomationSummary) => [props.form ? null : a.form?.name, triggersText(a), t('auto.n_steps', { n: a.steps })].filter(Boolean).join(' · ')
const sorted = computed(() => [...(rows.value ?? [])].sort((x, y) => x.name.localeCompare(y.name, locale.value)))
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="automations">
    <header v-if="!form">
      <h1 class="page-title !mb-1">{{ t('admin.area.automations') }}</h1>
      <p class="m-0 text-sm text-muted-color">{{ t('auto.all_hint') }}</p>
    </header>
    <TabIntro v-else :title="t('formconfig.tab.automations')" :text="t('auto.hint')" />
    <Message v-if="loadError" severity="error">{{ loadError }}</Message>
    <EmptyState v-else-if="!published" icon="pi pi-lock" :title="t('auto.unpublished_title')" :description="t('auto.publish_first')" testid="auto-unpublished" />
    <template v-else-if="rows">
      <EmptyState v-if="!rows.length" icon="pi pi-bolt" :title="t('auto.empty_title')" :description="t('auto.empty_text')" testid="auto-empty" />
      <ul v-else class="m-0 p-0 list-none flex flex-col gap-2">
        <li v-for="a in sorted" :key="a.uuid" class="auto-row" :data-testid="`auto-row-${a.key}`">
          <button type="button" class="auto-open" @click="open(a.form?.uuid ?? form ?? '', a.uuid)">
            <span class="font-medium" dir="auto">{{ a.name }}</span>
            <span class="text-xs text-muted-color" dir="auto">{{ subtitle(a) }}</span>
          </button>
          <Tag v-if="a.lastRun" :severity="runSeverity(a.lastRun.status)" :value="t(`auto.status.${a.lastRun.status}`)" :title="t('auto.last_run_at', { at: formatDatetime(a.lastRun.at, locale) })" />
          <ToggleSwitch :model-value="a.enabled" :aria-label="t('auto.enabled')" @update:model-value="(v: boolean) => toggle(a, v)" />
          <Button icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('auto.delete')" @click="remove(a)" />
        </li>
      </ul>
      <Button v-if="form" icon="pi pi-plus" :label="t('auto.new')" size="small" outlined class="self-start" data-testid="auto-new" @click="open(form, 'new')" />
      <div v-else class="flex flex-wrap items-end gap-2">
        <div class="field w-field-md">
          <label for="auto-new-form" class="text-sm font-medium">{{ t('auto.new_for_form') }}</label>
          <FormPicker v-model="newForm" input-id="auto-new-form" />
        </div>
        <Button icon="pi pi-plus" :label="t('auto.new')" size="small" outlined :disabled="!newForm" data-testid="auto-new" @click="newForm && open(newForm, 'new')" />
      </div>
    </template>
  </div>
</template>

<style scoped>
.auto-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  background: var(--bg-surface);
}
.auto-open {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
  text-align: start;
  color: inherit;
}
</style>
