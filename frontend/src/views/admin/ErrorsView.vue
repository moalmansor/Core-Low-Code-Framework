<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get, send } from '@/api/http'
import { useSession } from '@/stores/session'

interface Group {
  id: number
  exception_class: string
  message_sample: string
  module: string | null
  severity: string
  status: string
  occurrences: number
  first_seen_at: string
  last_seen_at: string
  assignee_user_id: number | null
  notes: string | null
}
interface Occurrence {
  id: number
  reference_code: string
  occurred_at: string
  file: string
  line: number
  trace: string
  request: Record<string, unknown> | null
  user_id: number | null
  correlation_id: string
}
const { t } = useI18n()
const toast = useToast()
const session = useSession()
const rows = ref<Group[]>([])
const total = ref(0)
const f = reactive({ status: 'new' as string | null, severity: null as string | null, search: '', reference: '', page: 1, per_page: 25 })
const open = ref<{ group: Group; occurrences: Occurrence[] } | null>(null)
const statuses = ['new', 'in_progress', 'resolved', 'ignored']
const severities = ['warning', 'error', 'critical', 'alert', 'emergency']

async function load(): Promise<void> {
  const res = await get<{ data: Group[]; total: number }>('/errors/groups', {
    status: f.status || undefined,
    severity: f.severity || undefined,
    search: f.search || undefined,
    page: f.page,
    per_page: f.per_page,
  })
  rows.value = res.data
  total.value = res.total
}
onMounted(load)
function onPage(e: DataTablePageEvent): void {
  f.page = e.page + 1
  f.per_page = e.rows
  load()
}
async function show(id: number): Promise<void> {
  open.value = (await get<{ data: { group: Group; occurrences: Occurrence[] } }>(`/errors/groups/${id}`)).data
}
async function findReference(): Promise<void> {
  const code = f.reference.trim().toUpperCase()
  if (!/^E-[A-Z0-9]{10}$/.test(code)) return
  try {
    const res = await get<{ data: { error_group_id: number } }>(`/errors/reference/${code}`)
    await show(res.data.error_group_id)
  } catch {
    toast.add({ severity: 'warn', summary: t('errors_ui.reference_not_found'), life: 4000 })
  }
}
async function update(changes: Partial<Group>): Promise<void> {
  if (!open.value) return
  await send('patch', `/errors/groups/${open.value.group.id}`, changes)
  Object.assign(open.value.group, changes)
  toast.add({ severity: 'success', summary: t('common.saved'), life: 2000 })
  await load()
}
const sev = (s: string) => ({ warning: 'warn', error: 'danger', critical: 'danger', alert: 'danger', emergency: 'danger' })[s] ?? 'secondary'
</script>

<template>
  <h1 class="page-title">{{ t('admin.area.error_monitoring') }}</h1>
  <form class="flex flex-wrap items-end gap-2 mb-3" @submit.prevent="((f.page = 1), load())">
    <div class="field">
      <label for="es">{{ t('users.status_label') }}</label
      ><Select v-model="f.status" input-id="es" :options="statuses.map((s) => ({ v: s, l: t(`errors_ui.status.${s}`) }))" option-label="l" option-value="v" show-clear @change="load" />
    </div>
    <div class="field">
      <label for="ev">{{ t('errors_ui.severity') }}</label
      ><Select v-model="f.severity" input-id="ev" :options="severities" show-clear @change="load" />
    </div>
    <div class="field">
      <label for="eq">{{ t('common.search') }}</label
      ><InputText id="eq" v-model="f.search" />
    </div>
    <Button type="submit" icon="pi pi-filter" :label="t('common.filter')" />
    <div class="flex-1" />
    <div class="field">
      <label for="er">{{ t('errors_ui.reference') }}</label
      ><InputText id="er" v-model="f.reference" placeholder="E-XXXXXXXXXX" class="ltr-value" @keyup.enter="findReference" />
    </div>
    <Button severity="secondary" icon="pi pi-search" :aria-label="t('common.search')" @click="findReference" />
  </form>
  <DataTable :value="rows" lazy paginator :rows="f.per_page" :total-records="total" data-key="id" size="small" @page="onPage" @row-click="(e) => show(e.data.id)">
    <template #empty>{{ t('errors_ui.none') }}</template>
    <Column :header="t('errors_ui.error')"
      ><template #body="{ data }"
        ><div class="ltr-value font-medium text-sm">{{ data.exception_class }}</div>
        <div class="ltr-value text-xs text-muted-color truncate max-w-xl">{{ data.message_sample }}</div></template
      ></Column
    >
    <Column :header="t('errors_ui.severity')"
      ><template #body="{ data }"><Tag :severity="sev(data.severity)" :value="data.severity" /></template
    ></Column>
    <Column field="occurrences" :header="t('errors_ui.occurrences')" />
    <Column :header="t('errors_ui.last_seen')"
      ><template #body="{ data }"
        ><span class="text-sm">{{ new Date(data.last_seen_at).toLocaleString(session.locale) }}</span></template
      ></Column
    >
    <Column :header="t('users.status_label')"
      ><template #body="{ data }">{{ t(`errors_ui.status.${data.status}`) }}</template></Column
    >
  </DataTable>

  <Drawer :visible="!!open" position="right" class="!w-full md:!w-[48rem]" :header="open?.group.exception_class" @update:visible="(v: boolean) => !v && (open = null)">
    <div v-if="open" class="flex flex-col gap-4 text-sm">
      <p class="ltr-value">{{ open.group.message_sample }}</p>
      <div class="flex flex-wrap gap-3">
        <div class="field">
          <label>{{ t('users.status_label') }}</label
          ><Select
            :model-value="open.group.status"
            :options="statuses.map((s) => ({ v: s, l: t(`errors_ui.status.${s}`) }))"
            option-label="l"
            option-value="v"
            @update:model-value="(v: string) => update({ status: v })"
          />
        </div>
        <div class="field">
          <label>{{ t('errors_ui.assignee') }}</label
          ><Button
            size="small"
            severity="secondary"
            :label="open.group.assignee_user_id === session.me?.id ? t('errors_ui.unassign') : t('errors_ui.assign_me')"
            @click="update({ assignee_user_id: open.group.assignee_user_id === session.me?.id ? null : (session.me?.id ?? null) })"
          />
        </div>
      </div>
      <div class="field">
        <label for="en">{{ t('errors_ui.notes') }}</label
        ><Textarea id="en" v-model="open.group.notes as string" rows="3" />
        <div><Button size="small" :label="t('common.save')" @click="update({ notes: open.group.notes })" /></div>
      </div>
      <h3 class="font-semibold">{{ t('errors_ui.recent') }}</h3>
      <details v-for="o in open.occurrences" :key="o.id" class="rounded border border-line p-2">
        <summary class="cursor-pointer ltr-value">{{ o.reference_code }} · {{ new Date(o.occurred_at).toLocaleString(session.locale) }} · {{ o.file }}:{{ o.line }}</summary>
        <div class="mt-2 ltr-value"><strong>correlation:</strong> {{ o.correlation_id }}</div>
        <pre class="mt-2 ltr-value text-xs overflow-auto max-h-64 bg-subtle p-2 rounded">{{ o.trace }}</pre>
        <pre v-if="o.request" class="mt-2 ltr-value text-xs overflow-auto max-h-48 bg-subtle p-2 rounded">{{ JSON.stringify(o.request, null, 2) }}</pre>
      </details>
    </div>
  </Drawer>
</template>
