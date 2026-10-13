<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ConfigSection from '@/components/config/ConfigSection.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '@/views/admin/building/shared'
import { dataQualityApi, recordName, type RepairFinding, type RepairScan, type ScanType } from './api'

/**
 * Repairing a form's data (specification §4.35, "Repair data"): a scan for
 * links to records that no longer exist, and one for records that would not
 * save under the form's current rules. A broken link is cleared through a
 * normal save (validated, journaled, audited); an invalid record is opened
 * and corrected by hand.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const toast = useToast()
const TYPES: ScanType[] = ['orphan_scan', 'validation_sweep']
const scans = ref<Record<ScanType, RepairScan | null>>({ orphan_scan: null, validation_sweep: null })
const failed = ref<Record<string, string>>({})
const picked = ref<string[]>([])
const clearing = ref(false)
const timers: ReturnType<typeof setTimeout>[] = []
onBeforeUnmount(() => timers.forEach(clearTimeout))
const running = (type: ScanType) => ['queued', 'running'].includes(scans.value[type]?.status ?? '')
const keyOf = (f: RepairFinding) => `${f.record}:${f.field}`
const fixable = computed(() => (scans.value.orphan_scan?.findings ?? []).filter((f) => f.fixable && f.field))

onMounted(async () => {
  try {
    scans.value = await dataQualityApi.repairState(props.form)
    TYPES.forEach(poll)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dq.repair_load_failed')), life: 6000 })
  }
})

async function scan(type: ScanType): Promise<void> {
  try {
    scans.value[type] = await dataQualityApi.scan(props.form, type)
    if (type === 'orphan_scan') picked.value = []
    poll(type)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dq.scan_failed')), life: 6000 })
  }
}
function poll(type: ScanType): void {
  const s = scans.value[type]
  if (!s || !['queued', 'running'].includes(s.status)) return
  timers.push(
    setTimeout(async () => {
      try {
        scans.value[type] = await dataQualityApi.scanResult(s.uuid)
        poll(type)
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('dq.scan_failed')), life: 6000 })
      }
    }, 1500),
  )
}

async function clearPicked(): Promise<void> {
  const items = fixable.value.filter((f) => picked.value.includes(keyOf(f))).map((f) => ({ record: f.record, field: f.field! }))
  if (!items.length || !window.confirm(t('dq.clear_confirm', { n: items.length }))) return
  clearing.value = true
  failed.value = {}
  try {
    const res = await dataQualityApi.clearLinks(props.form, items)
    for (const f of res.failed) failed.value[`${f.record}:${f.field}`] = f.message
    toast.add({
      severity: res.failed.length ? 'warn' : 'success',
      summary: t('dq.cleared', { n: res.fixed }),
      detail: res.failed.length ? t('dq.not_cleared', { n: res.failed.length }) : undefined,
      life: 6000,
    })
    const done = new Set(items.map((i) => `${i.record}:${i.field}`).filter((k) => !failed.value[k]))
    const s = scans.value.orphan_scan
    if (s) s.findings = s.findings.filter((f) => !done.has(keyOf(f)))
    picked.value = picked.value.filter((k) => !done.has(k))
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dq.clear_failed')), life: 6000 })
  } finally {
    clearing.value = false
  }
}
const allPicked = computed({
  get: () => fixable.value.length > 0 && fixable.value.every((f) => picked.value.includes(keyOf(f))),
  set: (v: boolean) => (picked.value = v ? fixable.value.map(keyOf) : []),
})
</script>

<template>
  <div class="rounded-xl border border-line bg-card px-5" data-testid="dq-repair">
    <ConfigSection v-for="type in TYPES" :id="`dq-${type}`" :key="type" :title="t(`dq.scan.${type}`)" :description="t(`dq.scan_desc.${type}`)">
      <div class="flex flex-wrap items-center gap-2">
        <Button icon="pi pi-search" :label="t('dq.run_scan')" size="small" outlined :loading="running(type)" :disabled="running(type)" :data-testid="`dq-scan-${type}`" @click="scan(type)" />
        <span v-if="scans[type]?.finished_at" class="text-sm text-muted-color">{{ t('dq.scanned', { at: formatDatetime(scans[type]!.finished_at!, locale), n: scans[type]!.scanned ?? 0 }) }}</span>
      </div>
      <Message v-if="scans[type]?.status === 'failed'" severity="error" :closable="false">{{ t('dq.scan_failed') }}</Message>
      <template v-else-if="scans[type]?.status === 'completed'">
        <p class="m-0 text-sm" :data-testid="`dq-found-${type}`">{{ t('dq.problems_found', { n: scans[type]!.findings.length }) }}</p>
        <div v-if="type === 'orphan_scan' && fixable.length" class="flex flex-wrap items-center gap-3">
          <label class="flex items-center gap-2 text-sm"> <Checkbox v-model="allPicked" binary input-id="dq-pick-all" />{{ t('dq.pick_all') }} </label>
          <Button icon="pi pi-eraser" :label="t('dq.clear_links', { n: picked.length })" size="small" :disabled="!picked.length" :loading="clearing" data-testid="dq-clear" @click="clearPicked" />
        </div>
        <ul v-if="scans[type]!.findings.length" class="dq-findings">
          <li v-for="f in scans[type]!.findings" :key="keyOf(f)" class="dq-finding" :data-testid="`dq-finding-${f.record}`">
            <Checkbox v-if="type === 'orphan_scan' && f.fixable && f.field" v-model="picked" :value="keyOf(f)" :aria-label="t('dq.pick')" />
            <div class="min-w-0 flex-1 text-sm">
              <RouterLink :to="{ name: f.problem === 'invalid' ? 'records.edit' : 'records.view', params: { form, record: f.record } }" class="text-primary" dir="auto">{{
                recordName(f, t('dq.a_record'))
              }}</RouterLink>
              <span v-if="f.label" class="text-muted-color"> · {{ f.label }}</span>
              <p v-if="f.message" class="m-0 text-xs text-muted-color" dir="auto">{{ f.message }}</p>
              <p v-if="failed[keyOf(f)]" class="m-0 text-xs text-danger" dir="auto">{{ failed[keyOf(f)] }}</p>
            </div>
            <Tag :value="t(`dq.problem.${f.problem}`)" :severity="f.problem === 'invalid' ? 'warn' : 'danger'" />
          </li>
        </ul>
        <p v-if="scans[type]!.more" class="m-0 text-xs text-muted-color">{{ t('dq.more_problems') }}</p>
        <p v-if="scans[type]!.hidden" class="m-0 text-xs text-muted-color">{{ t('dq.hidden_records', { n: scans[type]!.hidden }) }}</p>
      </template>
    </ConfigSection>
  </div>
</template>

<style scoped>
.dq-findings {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.dq-finding {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  background: var(--color-subtle);
}
</style>
