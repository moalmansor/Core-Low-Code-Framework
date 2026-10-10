<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import SelectButton from 'primevue/selectbutton'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import DelegationsPanel from '@/components/DelegationsPanel.vue'
import { formatDatetime, formatLoose } from '@/runtime/format'
import StatusBadge from '@/runtime/workflow/StatusBadge.vue'
import type { StatusPayload } from '@/runtime/workflow/types'
import { useSession } from '@/stores/session'

/**
 * My Work (specification §4.25): everything assigned to the user, their
 * roles and departments, and to the people they cover for — records to work
 * on and approvals to decide — soonest due first, with the SLA state, the
 * queue's columns, and claiming of queue items. The user's own delegations
 * are managed here too.
 */
interface Item {
  assignment: string
  kind: 'work' | 'approval'
  approval: string | null
  form: { uuid: string; key: string; name: string }
  record: { uuid: string; title: string | null; number: string | null; row_version: number }
  status: StatusPayload | null
  assignee: { type: string; name: string }
  on_behalf_of: string | null
  due_at: string | null
  overdue: boolean
  priority: number
  sla: { state: 'running' | 'warned' | 'breached'; due_at: string } | null
  claim: { by: { uuid: string; name: string | null }; at: string } | null
  claimable: boolean
  columns: { path: string[]; label: string; value: unknown }[]
}
const { t, locale } = useI18n()
const toast = useToast()
const session = useSession()
const tab = ref('work')
const items = ref<Item[]>([])
const total = ref(0)
const page = ref(1)
const truncated = ref(false)
const loading = ref(false)
const error = ref('')
const kind = ref<'all' | 'work' | 'approval'>('all')
const overdue = ref(false)
const canDelegate = computed(() => session.can('system.delegate_own_work') || session.can('system.manage_delegation'))
const kinds = computed(() => (['all', 'work', 'approval'] as const).map((v) => ({ value: v, label: t(`my_work.kind.${v}`) })))

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const res = await get<{ data: Item[]; meta: { total: number; truncated: boolean } }>('/my-work', { kind: kind.value, overdue: overdue.value ? 1 : undefined, page: page.value, per_page: 25 })
    items.value = res.data
    total.value = res.meta.total
    truncated.value = res.meta.truncated
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : t('records.load_failed')
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch([kind, overdue], () => {
  page.value = 1
  void load()
})

async function claim(it: Item, release: boolean): Promise<void> {
  try {
    await send('post', `/r/${it.form.uuid}/${it.record.uuid}/${release ? 'release' : 'claim'}`)
    await load()
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 6000 })
    await load()
  }
}
const when = (iso: string) => formatDatetime(iso, locale.value)
const pages = computed(() => Math.max(1, Math.ceil(total.value / 25)))
</script>

<template>
  <div class="flex flex-col gap-4 max-w-6xl" data-testid="my-work">
    <h1 class="page-title !mb-0">{{ t('my_work.title') }}</h1>
    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="work" data-testid="my-work-tab-items">{{ t('my_work.items') }}</Tab>
        <Tab v-if="canDelegate" value="delegations" data-testid="my-work-tab-delegations">{{ t('delegation.title') }}</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="work">
          <div class="flex flex-wrap items-center gap-3 mb-3">
            <SelectButton v-model="kind" :options="kinds" option-label="label" option-value="value" :allow-empty="false" size="small" data-testid="my-work-kind" />
            <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="overdue" />{{ t('my_work.overdue_only') }}</label>
            <span class="flex-1" />
            <Button icon="pi pi-refresh" :label="t('records.apply_refresh')" size="small" outlined :loading="loading" @click="load" />
          </div>
          <Message v-if="error" severity="error">{{ error }}</Message>
          <Message v-if="truncated" severity="warn" size="small">{{ t('my_work.truncated') }}</Message>
          <p v-if="!loading && !items.length && !error" class="text-muted-color" data-testid="my-work-empty">{{ t('my_work.empty') }}</p>
          <ul class="flex flex-col gap-2">
            <li v-for="it in items" :key="it.assignment" class="rounded-xl border border-line p-3 flex flex-col gap-2" :data-testid="`my-work-item-${it.record.uuid}`">
              <div class="flex flex-wrap items-center gap-2">
                <Tag :value="t(`my_work.kind.${it.kind}`)" :severity="it.kind === 'approval' ? 'info' : 'secondary'" />
                <RouterLink :to="{ name: 'records.view', params: { form: it.form.uuid, record: it.record.uuid } }" class="font-medium text-link" dir="auto">
                  {{ it.record.title ?? it.record.number ?? t('my_work.untitled', { form: it.form.name }) }}
                </RouterLink>
                <span class="text-sm text-muted-color">{{ it.form.name }}</span>
                <StatusBadge :status="it.status" size="sm" />
                <span class="flex-1" />
                <Tag v-if="it.overdue" severity="danger" :value="t('my_work.overdue')" />
                <Tag
                  v-else-if="it.sla"
                  :severity="it.sla.state === 'breached' ? 'danger' : it.sla.state === 'warned' ? 'warn' : 'secondary'"
                  :value="t(`workflow_run.sla.${it.sla.state}`, { at: when(it.sla.due_at) })"
                />
                <span v-if="it.due_at" class="text-sm text-muted-color">{{ t('workflow_run.due', { at: when(it.due_at) }) }}</span>
              </div>
              <dl v-if="it.columns.length" class="grid gap-x-4 gap-y-1 text-sm grid-cols-[repeat(auto-fill,minmax(10rem,1fr))]">
                <div v-for="c in it.columns" :key="c.path.join('.')">
                  <dt class="text-muted-color">{{ c.label }}</dt>
                  <dd dir="auto">{{ formatLoose(c.value) || '—' }}</dd>
                </div>
              </dl>
              <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted-color">{{ t('workflow_run.assigned_to') }} {{ it.assignee.name }}</span>
                <span v-if="it.on_behalf_of" class="text-muted-color">· {{ t('my_work.covering', { name: it.on_behalf_of }) }}</span>
                <span v-if="it.claim" class="text-muted-color">· {{ t('workflow_run.claimed_by', { name: it.claim.by.name ?? '' }) }}</span>
                <span class="flex-1" />
                <Button
                  v-if="it.claimable && !it.claim"
                  icon="pi pi-lock"
                  :label="t('workflow_run.claim')"
                  size="small"
                  outlined
                  :data-testid="`my-work-claim-${it.record.uuid}`"
                  @click="claim(it, false)"
                />
                <Button v-if="it.claim && it.claim.by.uuid === session.me?.uuid" icon="pi pi-lock-open" :label="t('workflow_run.release')" size="small" outlined @click="claim(it, true)" />
                <RouterLink v-slot="{ navigate }" :to="{ name: 'records.view', params: { form: it.form.uuid, record: it.record.uuid } }" custom>
                  <Button
                    :icon="it.kind === 'approval' ? 'pi pi-check-square' : 'pi pi-arrow-right rtl:rotate-180'"
                    :label="it.kind === 'approval' ? t('my_work.decide') : t('my_work.open')"
                    size="small"
                    @click="navigate"
                  />
                </RouterLink>
              </div>
            </li>
          </ul>
          <div v-if="pages > 1" class="flex items-center justify-center gap-2 mt-3">
            <Button icon="pi pi-chevron-left rtl:rotate-180" text :disabled="page <= 1" :aria-label="t('my_work.previous')" @click="(page--, load())" />
            <span class="text-sm tabular-nums">{{ page }} / {{ pages }}</span>
            <Button icon="pi pi-chevron-right rtl:rotate-180" text :disabled="page >= pages" :aria-label="t('my_work.next')" @click="(page++, load())" />
          </div>
        </TabPanel>
        <TabPanel v-if="canDelegate" value="delegations">
          <DelegationsPanel v-if="tab === 'delegations'" :admin="false" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
