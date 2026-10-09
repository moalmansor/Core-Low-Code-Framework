<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import { reloadCatalog } from '@/i18n'
import { useSession } from '@/stores/session'

interface Row {
  type: string
  object: string | null
  key: string
  field: string
  source: string | null
  value: string | null
}
const { t } = useI18n()
const toast = useToast()
const session = useSession()
const types = ref<{ type: string; label: string }[]>([])
const locales = ref<{ code: string; native_name: string; direction: string }[]>([])
const filters = reactive({ type: 'ui', locale: '', untranslated: true, search: '', page: 1, per_page: 50 })
const rows = ref<Row[]>([])
const total = ref(0)
const edits = ref<Record<string, string>>({})
const direction = computed(() => locales.value.find((l) => l.code === filters.locale)?.direction ?? 'ltr')
const rowId = (r: Row) => `${r.type}|${r.object ?? r.key}|${r.field}`

onMounted(async () => {
  types.value = (await get<{ data: { type: string; label: string }[] }>('/translations/types')).data
  locales.value = (await get<{ data: { code: string; native_name: string; direction: string; is_default: boolean }[] }>('/locales')).data
  filters.locale = locales.value.find((l) => l.code !== session.boot?.default_locale)?.code ?? locales.value[0]?.code ?? 'en'
})

async function load(): Promise<void> {
  if (!filters.locale) return
  const res = await get<{ data: Row[]; total: number }>('/translations', { ...filters, untranslated: filters.untranslated ? 1 : 0, search: filters.search || undefined })
  rows.value = res.data
  total.value = res.total
  edits.value = {}
}
watch(
  () => [filters.type, filters.locale, filters.untranslated],
  () => ((filters.page = 1), load()),
)

function onPage(e: DataTablePageEvent): void {
  filters.page = e.page + 1
  filters.per_page = e.rows
  load()
}

async function save(): Promise<void> {
  const items = rows.value.filter((r) => edits.value[rowId(r)] !== undefined).map((r) => ({ type: r.type, object: r.object, key: r.key, field: r.field, value: edits.value[rowId(r)] || null }))
  try {
    await send('put', '/translations', { locale: filters.locale, items })
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    if (filters.type === 'ui') reloadCatalog(filters.locale)
    await load()
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 6000 })
  }
}

async function exportCatalog(): Promise<void> {
  const data = (await get<{ data: unknown }>('/translations/export', { locale: filters.locale })).data
  const a = document.createElement('a')
  a.href = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }))
  a.download = `translations-${filters.locale}.json`
  a.click()
  URL.revokeObjectURL(a.href)
}

async function importCatalog(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || file.size > 2_000_000) return
  try {
    const body = JSON.parse(await file.text())
    const res = await send<{ data: { imported: number } }>('post', '/translations/import', { ...body, locale: filters.locale })
    toast.add({ severity: 'success', summary: t('translations.imported', { n: res.data.imported }), life: 4000 })
    reloadCatalog(filters.locale)
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: e instanceof ApiError ? e.message : t('access.invalid_file'), life: 6000 })
  }
}
const pending = computed(() => Object.keys(edits.value).length)
</script>

<template>
  <h1 class="page-title">{{ t('admin.area.translations') }}</h1>
  <div class="flex flex-wrap items-end gap-3 mb-3">
    <div class="field">
      <label for="tt">{{ t('translations.type') }}</label
      ><Select v-model="filters.type" input-id="tt" :options="types.map((x) => ({ v: x.type, l: t(x.label) }))" option-label="l" option-value="v" data-testid="translation-type" />
    </div>
    <div class="field">
      <label for="tl">{{ t('translations.locale') }}</label
      ><Select v-model="filters.locale" input-id="tl" :options="locales" option-label="native_name" option-value="code" />
    </div>
    <div class="field">
      <label for="ts">{{ t('common.search') }}</label
      ><InputText id="ts" v-model="filters.search" @keyup.enter="load" />
    </div>
    <label class="flex items-center gap-2 mb-2"><ToggleSwitch v-model="filters.untranslated" />{{ t('translations.only_missing') }}</label>
    <div class="flex-1" />
    <Button severity="secondary" icon="pi pi-download" :label="t('access.export')" @click="exportCatalog" />
    <label class="p-button p-button-secondary cursor-pointer"
      ><i class="pi pi-upload me-2" />{{ t('access.import') }}<input type="file" accept="application/json" class="hidden" @change="importCatalog"
    /></label>
  </div>
  <DataTable :value="rows" lazy paginator :rows="filters.per_page" :total-records="total" :data-key="rowId" size="small" @page="onPage">
    <template #empty>{{ filters.untranslated ? t('translations.all_done') : t('common.no_results') }}</template>
    <Column :header="t('translations.key')" style="width: 25%"
      ><template #body="{ data }"
        ><span class="ltr-value text-sm">{{ data.key }}</span>
        <div v-if="data.type !== 'ui'" class="text-xs text-muted-color">{{ data.field }}</div></template
      ></Column
    >
    <Column :header="t('translations.source')" style="width: 30%"
      ><template #body="{ data }">{{ data.source }}</template></Column
    >
    <Column :header="t('translations.translation')">
      <template #body="{ data }">
        <Textarea
          :model-value="edits[rowId(data)] ?? data.value ?? ''"
          :dir="direction"
          rows="1"
          auto-resize
          class="w-full"
          @update:model-value="(v: string | undefined) => (edits[rowId(data)] = v ?? '')"
        />
      </template>
    </Column>
  </DataTable>
  <div class="sticky bottom-0 py-3 bg-subtle flex justify-end gap-2">
    <span v-if="pending" class="self-center text-sm">{{ t('access.pending_changes', { n: pending }) }}</span>
    <Button :label="t('common.save')" icon="pi pi-check" :disabled="!pending" data-testid="save-translations" @click="save" />
  </div>
</template>
