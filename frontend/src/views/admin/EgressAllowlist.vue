<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ToggleSwitch from 'primevue/toggleswitch'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'

interface Entry {
  uuid: string
  host_pattern: string
  ports: number[]
  allow_http: boolean
  description: string | null
  is_active: boolean
}
const { t } = useI18n()
const entries = ref<Entry[]>([])
const draft = reactive({ host_pattern: '', ports: '443', allow_http: false, description: '' })
const error = ref('')

async function load(): Promise<void> {
  entries.value = (await get<{ data: Entry[] }>('/egress-allowlist')).data
}
onMounted(load)

async function add(): Promise<void> {
  error.value = ''
  try {
    await send('post', '/egress-allowlist', {
      ...draft,
      ports: draft.ports
        .split(',')
        .map((p) => Number(p.trim()))
        .filter(Boolean),
    })
    Object.assign(draft, { host_pattern: '', ports: '443', allow_http: false, description: '' })
    await load()
  } catch (e) {
    if (e instanceof ApiError) error.value = Object.values(e.fieldErrors)[0] ?? e.message
  }
}
async function toggle(entry: Entry): Promise<void> {
  await send('patch', `/egress-allowlist/${entry.uuid}`, { is_active: !entry.is_active })
  await load()
}
async function remove(entry: Entry): Promise<void> {
  await send('delete', `/egress-allowlist/${entry.uuid}`)
  await load()
}
</script>

<template>
  <p class="text-muted-color mb-3">{{ t('egress.hint') }}</p>
  <Message v-if="error" severity="error" class="mb-3">{{ error }}</Message>
  <form class="flex flex-wrap items-end gap-2 mb-4" @submit.prevent="add">
    <div class="field">
      <label for="eh">{{ t('egress.host') }}</label
      ><InputText id="eh" v-model="draft.host_pattern" class="ltr-value" placeholder="api.example.com / *.example.com" />
    </div>
    <div class="field">
      <label for="ep">{{ t('egress.ports') }}</label
      ><InputText id="ep" v-model="draft.ports" class="ltr-value w-28" />
    </div>
    <div class="field">
      <label for="ed">{{ t('egress.description') }}</label
      ><InputText id="ed" v-model="draft.description" />
    </div>
    <label class="flex items-center gap-2 mb-2"><ToggleSwitch v-model="draft.allow_http" />{{ t('egress.allow_http') }}</label>
    <Button type="submit" icon="pi pi-plus" :label="t('common.add')" :disabled="!draft.host_pattern" />
  </form>
  <DataTable :value="entries" size="small" data-key="uuid">
    <template #empty>{{ t('egress.empty') }}</template>
    <Column :header="t('egress.host')"
      ><template #body="{ data }"
        ><span class="ltr-value">{{ data.host_pattern }}</span></template
      ></Column
    >
    <Column :header="t('egress.ports')"
      ><template #body="{ data }"
        ><span class="ltr-value">{{ data.ports.join(', ') }}</span></template
      ></Column
    >
    <Column field="description" :header="t('egress.description')" />
    <Column :header="t('common.active')"
      ><template #body="{ data }"><ToggleSwitch :model-value="data.is_active" @update:model-value="toggle(data)" /></template
    ></Column>
    <Column style="width: 4rem"
      ><template #body="{ data }"><Button icon="pi pi-trash" text severity="danger" :aria-label="t('common.delete')" @click="remove(data)" /></template
    ></Column>
  </DataTable>
</template>
