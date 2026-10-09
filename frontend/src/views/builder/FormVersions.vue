<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import { builderApi, type FormDetail, type VersionDiff, type VersionEntry } from '@/builder/api'
import { pick } from '@/builder/conditions/scope'
import DiffView from '@/builder/DiffView.vue'
import { useSession } from '@/stores/session'

/**
 * Version history (specification §4.10): every published version, a visual
 * diff between any two versions or a version and the draft, and rollback,
 * which loads a version into the draft for review and publishing.
 */
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const confirm = useConfirm()
const session = useSession()
const formUuid = String(route.params.form)

const form = ref<FormDetail | null>(null)
const versions = ref<VersionEntry[]>([])
const loading = ref(true)
const failure = ref<string | null>(null)
const from = ref<string | null>(null)
const to = ref<string>('draft')
const diff = ref<VersionDiff | null>(null)
const diffLoading = ref(false)
const viewing = ref<{ version: number; definition: Record<string, unknown>; diff: VersionDiff | null } | null>(null)

const choices = computed(() => [
  { value: 'draft', label: t('builder.versions_page.draft') },
  ...versions.value.map((v) => ({ value: String(v.version), label: t('builder.versions_page.version_n', { n: v.version }) })),
])

onMounted(async () => {
  try {
    ;[form.value, versions.value] = await Promise.all([builderApi.form(formUuid), builderApi.versions(formUuid)])
    if (versions.value.length) {
      from.value = String(versions.value[0]!.version)
      await compare()
    }
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : String(e)
  } finally {
    loading.value = false
  }
})

async function compare(): Promise<void> {
  if (!from.value || !to.value) return
  diffLoading.value = true
  failure.value = null
  try {
    diff.value = await builderApi.diff(formUuid, from.value, to.value)
  } catch (e) {
    diff.value = null
    failure.value = e instanceof ApiError ? e.message : String(e)
  } finally {
    diffLoading.value = false
  }
}

async function view(v: VersionEntry): Promise<void> {
  try {
    const data = await builderApi.version(formUuid, v.version)
    viewing.value = { version: data.version, definition: data.definition, diff: data.diff_from_previous }
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : String(e)
  }
}

/** The rollback class (architecture §13.4) from the changes published after the target version. */
function rollbackClass(v: VersionEntry): 'metadata_only' | 'additive_schema' | 'destructive' {
  const later = versions.value.filter((x) => x.version > v.version).map((x) => x.change_class)
  if (later.includes('destructive')) return 'destructive'
  if (later.includes('additive_schema')) return 'additive_schema'
  return 'metadata_only'
}

function rollback(v: VersionEntry): void {
  const cls = rollbackClass(v)
  confirm.require({
    header: t('builder.versions_page.rollback_title', { n: v.version }),
    message: `${t(`builder.versions_page.rollback_${cls}`)} ${t('builder.versions_page.rollback_then_publish')}`,
    acceptProps: { label: t('builder.versions_page.rollback'), severity: cls === 'destructive' ? 'danger' : 'primary' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: () => void doRollback(v, false),
  })
}

async function doRollback(v: VersionEntry, discard: boolean): Promise<void> {
  try {
    await builderApi.rollback(formUuid, v.version, discard)
    await router.push({ name: 'admin.forms.builder', params: { form: formUuid }, query: { rollback_of: String(v.version) } })
  } catch (e) {
    if (e instanceof ApiError && e.code === 'draft_has_changes') {
      confirm.require({
        header: t('builder.versions_page.discard_title'),
        message: t('builder.versions_page.discard_message'),
        acceptProps: { label: t('builder.versions_page.discard'), severity: 'danger' },
        rejectProps: { label: t('common.cancel'), severity: 'secondary' },
        accept: () => void doRollback(v, true),
      })
    } else failure.value = e instanceof ApiError ? e.message : String(e)
  }
}

const viewedFields = computed(() => {
  const fields = (viewing.value?.definition.fields ?? []) as { uuid: string; key: string; type: string; i18n?: { label?: Record<string, string> } }[]
  return fields
})
function formatDate(iso: string): string {
  return new Date(iso).toLocaleString(session.locale)
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="form-versions">
    <div class="flex flex-wrap items-center gap-2">
      <RouterLink :to="{ name: 'admin.forms.builder', params: { form: formUuid } }" class="p-button p-button-text p-button-sm" :aria-label="t('builder.title')"
        ><i class="pi pi-arrow-left rtl:rotate-180"
      /></RouterLink>
      <h1 class="page-title !mb-0 flex-1">{{ t('builder.versions_page.title', { name: form?.name ?? '' }) }}</h1>
    </div>
    <Message v-if="failure" severity="error">{{ failure }}</Message>
    <div v-if="loading" class="flex justify-center p-6"><ProgressSpinner /></div>
    <template v-else>
      <Message v-if="!versions.length" severity="info">{{ t('builder.versions_page.none') }}</Message>
      <div v-else class="overflow-auto rounded-lg border border-surface-200 dark:border-surface-700">
        <table class="w-full text-sm">
          <thead class="bg-surface-50 dark:bg-surface-800 text-start">
            <tr>
              <th class="p-2 text-start">{{ t('builder.versions_page.version') }}</th>
              <th class="p-2 text-start">{{ t('builder.versions_page.state') }}</th>
              <th class="p-2 text-start">{{ t('builder.versions_page.published') }}</th>
              <th class="p-2 text-start">{{ t('builder.versions_page.change_class') }}</th>
              <th class="p-2 text-start">{{ t('builder.versions_page.changes') }}</th>
              <th class="p-2 text-start">{{ t('builder.versions_page.note') }}</th>
              <th class="p-2">
                <span class="sr-only">{{ t('common.actions') }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="v in versions" :key="v.uuid" class="border-t border-surface-200 dark:border-surface-700" :data-testid="`version-${v.version}`">
              <td class="p-2 font-semibold">
                {{ v.version }}
                <span v-if="v.rollback_of" class="text-xs text-muted-color">{{ t('builder.versions_page.rollback_of', { n: v.rollback_of }) }}</span>
              </td>
              <td class="p-2"><Tag :value="t(`builder.version_state.${v.state}`)" :severity="v.state === 'published' ? 'success' : 'secondary'" /></td>
              <td class="p-2">
                {{ formatDate(v.published_at) }}<span v-if="v.published_by" class="text-muted-color"> · {{ v.published_by }}</span>
              </td>
              <td class="p-2">{{ v.change_class ? t(`builder.change_class.${v.change_class}`) : '—' }}</td>
              <td class="p-2">{{ v.summary ? t('builder.diff.summary', v.summary) : '—' }}</td>
              <td class="p-2 max-w-64 truncate" :title="v.change_note ?? ''">{{ v.change_note ?? '' }}</td>
              <td class="p-2 whitespace-nowrap">
                <Button size="small" text icon="pi pi-eye" :label="t('builder.versions_page.view')" @click="view(v)" />
                <Button v-if="v.state !== 'published'" size="small" text icon="pi pi-replay" :label="t('builder.versions_page.rollback')" :data-testid="`rollback-${v.version}`" @click="rollback(v)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <section v-if="versions.length" class="flex flex-col gap-2">
        <h2 class="font-semibold">{{ t('builder.versions_page.compare') }}</h2>
        <div class="flex flex-wrap items-center gap-2">
          <Select v-model="from" :options="choices" option-label="label" option-value="value" size="small" :aria-label="t('builder.versions_page.from')" />
          <i class="pi pi-arrow-right rtl:rotate-180" aria-hidden="true" />
          <Select v-model="to" :options="choices" option-label="label" option-value="value" size="small" :aria-label="t('builder.versions_page.to')" />
          <Button size="small" icon="pi pi-arrow-right-arrow-left" :label="t('builder.versions_page.show_diff')" :loading="diffLoading" data-testid="compare" @click="compare" />
        </div>
        <DiffView v-if="diff" :diff="diff" />
      </section>
    </template>

    <Dialog
      :visible="!!viewing"
      modal
      :header="t('builder.versions_page.version_n', { n: viewing?.version ?? 0 })"
      :style="{ width: '48rem' }"
      :breakpoints="{ '800px': '95vw' }"
      @update:visible="(v: boolean) => !v && (viewing = null)"
    >
      <div v-if="viewing" class="flex flex-col gap-3">
        <h3 class="font-semibold text-sm">{{ t('builder.versions_page.fields', { n: viewedFields.length }) }}</h3>
        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-1 text-sm">
          <li v-for="f in viewedFields" :key="f.uuid" class="flex gap-2">
            <span>{{ pick(f.i18n?.label, session.locale, f.key) }}</span>
            <span class="text-xs text-muted-color ltr-value">{{ f.key }} · {{ t(`builder.type.${f.type}`) }}</span>
          </li>
        </ul>
        <template v-if="viewing.diff">
          <h3 class="font-semibold text-sm">{{ t('builder.versions_page.changes_from_previous') }}</h3>
          <DiffView :diff="viewing.diff" />
        </template>
      </div>
    </Dialog>
  </div>
</template>
