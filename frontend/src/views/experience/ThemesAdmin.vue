<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import EmptyState from '@/components/config/EmptyState.vue'
import { useSession } from '@/stores/session'
import { errorText } from '../admin/building/shared'
import { themesApi, type ThemeSummary } from './api'

/**
 * Themes (specification §4.28, ADR-0055): the organization's default theme and
 * the themes applications wear. Without a default theme the product theme
 * applies, with the Appearance & Branding colour.
 */
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const router = useRouter()
const session = useSession()
const themes = ref<ThemeSummary[]>([])
const loading = ref(true)
const importer = ref<HTMLInputElement | null>(null)

async function load(): Promise<void> {
  try {
    themes.value = await themesApi.list()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)
const open = (theme: string) => router.push({ name: 'admin.themes.theme', params: { theme } })

async function importFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  let parsed: unknown
  try {
    parsed = JSON.parse(await file.text())
  } catch {
    toast.add({ severity: 'error', summary: t('xp.theme_import_unreadable'), life: 6000 })
    return
  }
  try {
    const made = await themesApi.importDoc(parsed)
    toast.add({ severity: 'success', summary: t('xp.theme_imported'), life: 3000 })
    await open(made.uuid!)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.theme_import_failed')), life: 8000 })
  }
}

function useProductTheme(): void {
  confirm.require({
    header: t('xp.theme_clear_default_title'),
    message: t('xp.theme_clear_default_text'),
    acceptProps: { label: t('xp.theme_clear_default') },
    rejectProps: { label: t('xp.cancel'), text: true },
    accept: async () => {
      try {
        await themesApi.clearDefault()
        await Promise.all([load(), session.loadBootstrap()])
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('xp.save_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="themes-admin">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.themes') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('xp.themes_subtitle') }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button
          v-if="themes.some((x) => x.default)"
          icon="pi pi-replay"
          :label="t('xp.theme_clear_default')"
          size="small"
          severity="secondary"
          text
          data-testid="themes-clear-default"
          @click="useProductTheme"
        />
        <Button icon="pi pi-upload" :label="t('xp.theme_import')" size="small" severity="secondary" outlined data-testid="themes-import" @click="importer?.click()" />
        <input ref="importer" type="file" accept=".json,application/json" class="hidden" data-testid="themes-import-file" @change="importFile" />
        <Button icon="pi pi-plus" :label="t('xp.new_theme')" size="small" data-testid="themes-new" @click="open('new')" />
      </div>
    </header>
    <p v-if="!loading && !themes.some((x) => x.default)" class="m-0 text-sm text-muted-color" data-testid="themes-product">
      <i class="pi pi-info-circle me-1" aria-hidden="true" />{{ t('xp.theme_product_in_use') }}
    </p>
    <EmptyState v-if="!loading && !themes.length" icon="pi pi-palette" :title="t('xp.themes_empty_title')" :description="t('xp.themes_empty_text')" testid="themes-empty">
      <Button icon="pi pi-plus" :label="t('xp.new_theme')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="themes" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as ThemeSummary).uuid)">
      <Column :header="t('xp.name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('xp.theme_used_by')">
        <template #body="{ data }">
          <Tag v-if="data.default" severity="success" :value="t('xp.theme_default')" />
          <span v-else-if="data.application_name" dir="auto">{{ data.application_name }}</span>
          <span v-else class="text-muted-color">{{ t('xp.theme_unused') }}</span>
        </template>
      </Column>
      <Column :header="t('xp.theme_version')">
        <template #body="{ data }">{{ data.version }}</template>
      </Column>
      <Column :header="t('xp.theme_contrast')">
        <template #body="{ data }">
          <Tag v-if="data.warnings" severity="warn" :value="t('xp.theme_warnings', { n: data.warnings })" />
          <Tag v-else severity="success" :value="t('xp.theme_contrast_ok')" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
