<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ConfigSection from '@/components/config/ConfigSection.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { joinList } from '@/runtime/lists'
import { errorText, fieldErrors, formatDateTime } from '../admin/building/shared'
import { defaultAction, packagesApi, type Choice, type PackageDetail } from './api'
import ReportTable from './ReportTable.vue'

/**
 * One package. An import shows its drift report with a choice per object;
 * applying it runs in the background and the outcome of each object is shown.
 */
const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()
const uuid = String(route.params.package)
const pkg = ref<PackageDetail | null>(null)
const choices = ref<Record<string, Choice>>({})
const errors = ref<Record<string, string>>({})
const confirmBlocking = ref(false)
const confirmDestructive = ref(false)
const applying = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

async function load(): Promise<void> {
  try {
    pkg.value = await packagesApi.show(uuid)
    if (pkg.value.report) {
      const next: Record<string, Choice> = {}
      for (const row of pkg.value.report.objects) {
        const before = pkg.value.choices?.[row.uuid] ?? choices.value[row.uuid]
        const action = before?.action ?? defaultAction(row)
        if (!pkg.value.report.ambiguous || action) next[row.uuid] = { action: action ?? (null as unknown as Choice['action']), key: before?.key ?? (action === 'rename' ? row.key : null) }
      }
      choices.value = next
    }
    if (pkg.value.status === 'applying') timer = setTimeout(load, 2000)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('pkg.load_failed')), life: 6000 })
  }
}
onMounted(load)
onBeforeUnmount(() => timer && clearTimeout(timer))

const canApply = computed(() => pkg.value?.direction === 'import' && ['validated', 'conflicts', 'failed'].includes(pkg.value.status) && !pkg.value.report?.ambiguous)
async function apply(): Promise<void> {
  applying.value = true
  errors.value = {}
  try {
    const body: Record<string, Choice> = {}
    for (const [k, c] of Object.entries(choices.value)) if (c.action) body[k] = c.action === 'rename' ? c : { action: c.action }
    pkg.value = await packagesApi.apply(uuid, body, { confirm_blocking: confirmBlocking.value, confirm_destructive: confirmDestructive.value })
    await load()
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('pkg.apply_failed')), life: 8000 })
  } finally {
    applying.value = false
  }
}
const statusSeverity = (s: string) => (s === 'applied' ? 'success' : s === 'failed' ? 'danger' : s === 'conflicts' ? 'warn' : s === 'applying' ? 'info' : 'secondary')
const outcomeSeverity = (s: string) => (s === 'applied' ? 'success' : s === 'failed' ? 'danger' : 'secondary')
const labelOf = (u?: string) => pkg.value?.report?.objects.find((o) => o.uuid === u)?.label ?? pkg.value?.items.find((o) => o.uuid === u)?.label ?? t('pkg.whole_package')
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="package-detail">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.packages' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.packages') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ pkg?.name ?? t('pkg.package') }}</h1>
        <p v-if="pkg" class="m-0 text-sm text-muted-color">
          <template v-if="pkg.direction === 'import'">{{
            t('pkg.imported_from', { source: pkg.source || t('pkg.unknown_source'), when: formatDateTime(pkg.source_detail?.exportedAt, locale) })
          }}</template>
          <template v-else>{{ t('pkg.exported_on', { when: formatDateTime(pkg.created_at, locale) }) }}</template>
        </p>
      </div>
      <div v-if="pkg" class="flex items-center gap-2">
        <Tag :severity="statusSeverity(pkg.status)" :value="t(`pkg.status_name.${pkg.direction === 'export' ? 'exported' : pkg.status}`)" data-testid="pkg-status" />
        <Button icon="pi pi-download" :label="t('pkg.download')" size="small" text @click="packagesApi.download(pkg.uuid, pkg.name)" />
      </div>
    </header>

    <template v-if="pkg">
      <ConfigSection v-if="pkg.direction === 'export'" id="pkg-contents" :title="t('pkg.contents')">
        <ul class="m-0 ps-5 flex flex-col gap-1 text-sm">
          <li v-for="i in pkg.items" :key="i.uuid">
            <span dir="auto">{{ i.label }}</span> <span class="text-muted-color">· {{ t(`pkg.type.${i.type}`) }}</span>
          </li>
        </ul>
      </ConfigSection>

      <template v-else-if="pkg.report">
        <Message v-if="pkg.report.ambiguous" severity="error" :closable="false" data-testid="pkg-ambiguous">{{ t('pkg.ambiguous_text') }}</Message>
        <Message v-if="pkg.report.missingSubjects.length" severity="warn" :closable="false">
          {{
            t('pkg.missing_subjects', {
              list: joinList(
                pkg.report.missingSubjects.map((s) => `${t(`pkg.subject.${s.type}`)} ${s.key}`),
                locale,
              ),
            })
          }}
        </Message>
        <ConfigSection id="pkg-report" :title="t('pkg.report_title')" :description="t('pkg.report_hint')">
          <ReportTable :rows="pkg.report.objects" :choices="canApply ? choices : null" :errors="errors" />
        </ConfigSection>
        <ConfigSection v-if="pkg.report.schema.length" id="pkg-schema" :title="t('pkg.schema_title')" :description="t('pkg.schema_hint')">
          <ul class="m-0 ps-5 text-sm flex flex-col gap-1">
            <li v-for="s in pkg.report.schema" :key="s.form">{{ t('pkg.schema_row', { name: labelOf(s.form), n: s.differences.length }) }}</li>
          </ul>
        </ConfigSection>
        <ConfigSection v-if="canApply" id="pkg-apply" :title="t('pkg.apply_title')" :description="t('pkg.apply_hint')">
          <SettingSwitch id="pkg-confirm-blocking" v-model="confirmBlocking" :label="t('pkg.confirm_blocking')" :description="t('pkg.confirm_blocking_desc')" />
          <SettingSwitch id="pkg-confirm-destructive" v-model="confirmDestructive" :label="t('pkg.confirm_destructive')" :description="t('pkg.confirm_destructive_desc')" />
          <div>
            <Button icon="pi pi-check" :label="t('pkg.apply')" size="small" :loading="applying" data-testid="pkg-apply" @click="apply" />
          </div>
        </ConfigSection>
      </template>

      <p v-if="pkg.status === 'applying'" class="m-0 text-sm text-muted-color inline-flex items-center gap-2" role="status">
        <i class="pi pi-spin pi-spinner" aria-hidden="true" />{{ t('pkg.applying_text') }}
      </p>
      <ConfigSection v-if="pkg.results?.length" id="pkg-results" :title="t('pkg.results_title')">
        <ul class="m-0 p-0 list-none flex flex-col gap-2" data-testid="pkg-results">
          <li v-for="(r, i) in pkg.results" :key="r.uuid ?? i" class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-2">
              <Tag :severity="outcomeSeverity(r.outcome)" :value="t(`pkg.outcome.${r.outcome}`)" />
              <span class="text-sm font-medium" dir="auto">{{ labelOf(r.uuid) }}</span>
            </div>
            <p v-for="(n, j) in r.notes" :key="j" class="m-0 text-sm text-muted-color" dir="auto">{{ n }}</p>
          </li>
        </ul>
      </ConfigSection>
    </template>
  </div>
</template>
