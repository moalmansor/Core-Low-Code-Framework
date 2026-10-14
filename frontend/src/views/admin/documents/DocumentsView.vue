<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import { ApiError } from '@/api/http'
import { useSession } from '@/stores/session'
import { errorText } from '../building/shared'
import { documentBlueprintsApi, documentsApi, type DocumentBlueprint, type DocumentSummary } from './api'

/**
 * Document templates (specification §4.17): Word files with placeholders and
 * designed templates, the form each belongs to, the formats it produces, and
 * how many notification rules attach it.
 */
const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const rows = ref<DocumentSummary[]>([])
const pdfFromWord = ref(true)
const loading = ref(true)

async function load(): Promise<void> {
  loading.value = true
  try {
    const r = await documentsApi.list()
    rows.value = r.rows
    pdfFromWord.value = r.pdfFromWord
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('doc.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

// A template made from a blueprint (specification §4.30), for any form.
const session = useSession()
const canBlueprints = computed(() => session.can('system.manage_blueprints'))
const blueprints = ref<DocumentBlueprint[]>([])
const fromOpen = ref(false)
const from = ref({ blueprint: null as string | null, form: null as string | null, key: '', name: {} as Record<string, string> })
const fromErrors = ref<Record<string, string>>({})
const making = ref(false)
async function openFrom(): Promise<void> {
  blueprints.value = await documentBlueprintsApi.list().catch(() => [])
  from.value = { blueprint: blueprints.value[0]?.uuid ?? null, form: null, key: '', name: {} }
  fromErrors.value = {}
  fromOpen.value = true
}
async function makeFrom(): Promise<void> {
  if (!from.value.blueprint) return
  making.value = true
  fromErrors.value = {}
  try {
    const made = await documentBlueprintsApi.instantiate(from.value.blueprint, from.value.form, from.value.key, from.value.name)
    fromOpen.value = false
    open(made.uuid)
  } catch (e) {
    fromErrors.value = e instanceof ApiError ? e.fieldErrors : {}
    if (!Object.keys(fromErrors.value).length) toast.add({ severity: 'error', summary: errorText(e, t('doc.from_blueprint_failed')), life: 6000 })
  } finally {
    making.value = false
  }
}
const blueprintOptions = computed(() => blueprints.value.map((b) => ({ value: b.uuid, label: b.name })))

function open(uuid: string): void {
  void router.push({ name: 'admin.documents.template', params: { template: uuid } })
}
function remove(row: DocumentSummary): void {
  confirm.require({
    header: t('doc.delete_title'),
    message: t('doc.delete_text', { name: row.name }),
    acceptLabel: t('doc.delete'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await documentsApi.remove(row.uuid)
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('doc.delete_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="documents-view">
    <header class="flex flex-wrap items-start gap-3">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.documents') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('doc.subtitle') }}</p>
      </div>
      <Button v-if="canBlueprints" icon="pi pi-clone" :label="t('doc.from_blueprint')" size="small" outlined data-testid="doc-from-blueprint" @click="openFrom" />
      <Button icon="pi pi-plus" :label="t('doc.new')" size="small" data-testid="doc-new" @click="open('new')" />
    </header>
    <Dialog v-model:visible="fromOpen" modal :header="t('doc.from_blueprint')" :style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }" data-testid="doc-from-dialog">
      <EmptyState v-if="!blueprints.length" icon="pi pi-clone" :title="t('doc.no_blueprints_title')" :description="t('doc.no_blueprints_text')" />
      <div v-else class="flex flex-col gap-4">
        <ConfigField :label="t('doc.blueprint')" for="doc-from-bp" width="md">
          <Select v-model="from.blueprint" input-id="doc-from-bp" :options="blueprintOptions" option-label="label" option-value="value" size="small" />
        </ConfigField>
        <ConfigField :label="t('doc.form')" for="doc-from-form" width="md" :hint="t('doc.from_blueprint_form_hint')" :error="fromErrors.form">
          <FormPicker v-model="from.form" input-id="doc-from-form" />
        </ConfigField>
        <ConfigField :label="t('doc.key')" for="doc-from-key" width="sm" :hint="t('doc.key_hint')" :error="fromErrors.key" required>
          <InputText id="doc-from-key" v-model="from.key" dir="ltr" class="ltr-value" size="small" maxlength="48" data-testid="doc-from-key" />
        </ConfigField>
        <I18nInput v-model="from.name" class="w-field-md" :label="t('doc.from_blueprint_name')" :maxlength="255" />
        <ul v-if="Object.keys(fromErrors).some((k) => !['form', 'key'].includes(k))" class="m-0 ps-5 text-sm text-danger">
          <li v-for="(m, k) in fromErrors" v-show="!['form', 'key'].includes(String(k))" :key="k">{{ m }}</li>
        </ul>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="fromOpen = false" />
        <Button
          v-if="blueprints.length"
          :label="t('doc.from_blueprint_make')"
          icon="pi pi-check"
          :loading="making"
          :disabled="!from.blueprint || !from.key"
          data-testid="doc-from-make"
          @click="makeFrom"
        />
      </template>
    </Dialog>
    <Message v-if="!pdfFromWord" severity="info">{{ t('doc.no_converter') }}</Message>

    <EmptyState v-if="!loading && !rows.length" icon="pi pi-file" :title="t('doc.empty_title')" :description="t('doc.empty_text')" testid="doc-empty">
      <Button icon="pi pi-plus" :label="t('doc.new')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as DocumentSummary).uuid)">
      <Column :header="t('doc.name')">
        <template #body="{ data }">
          <span class="font-medium" dir="auto">{{ data.name }}</span>
        </template>
      </Column>
      <Column :header="t('doc.kind')" style="width: 10rem">
        <template #body="{ data }">{{ t(`doc.kind_${data.type}`) }}</template>
      </Column>
      <Column :header="t('doc.form')">
        <template #body="{ data }">
          <span v-if="data.form_name" dir="auto">{{ data.form_name }}</span>
          <span v-else class="text-muted-color">{{ t('doc.general') }}</span>
        </template>
      </Column>
      <Column :header="t('doc.formats')" style="width: 9rem">
        <template #body="{ data }">{{ (data.formats as string[]).map((f) => t(`doc.format_${f}`)).join(', ') }}</template>
      </Column>
      <Column :header="t('doc.state')" style="width: 8rem">
        <template #body="{ data }">
          <Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('doc.active') : t('doc.inactive')" />
        </template>
      </Column>
      <Column style="width: 4rem">
        <template #body="{ data }">
          <Button
            v-tooltip.left="data.used_by > 0 ? t('doc.in_use', { n: data.used_by }) : undefined"
            icon="pi pi-trash"
            text
            rounded
            severity="danger"
            size="small"
            :disabled="data.used_by > 0"
            :aria-label="t('doc.delete')"
            @click.stop="remove(data)"
          />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
