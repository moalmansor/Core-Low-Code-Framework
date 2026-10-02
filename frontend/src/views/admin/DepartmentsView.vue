<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import TreeSelect from 'primevue/treeselect'
import TreeTable from 'primevue/treetable'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import { useSession } from '@/stores/session'
import { departmentNodes, type DepartmentApi, type DepartmentNode } from './departments'

interface Editing {
  uuid?: string
  code: string
  name: Record<string, string>
  parentKey: Record<string, boolean> | null
  sort_order: number
  is_active: boolean
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const session = useSession()
const nodes = ref<DepartmentNode[]>([])
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})

async function load(): Promise<void> {
  nodes.value = departmentNodes((await get<{ data: DepartmentApi[] }>('/departments/tree')).data)
}
onMounted(load)

function parentOf(uuid: string, list = nodes.value, parent: string | null = null): string | null | undefined {
  for (const n of list) {
    if (n.key === uuid) return parent
    const found = parentOf(uuid, n.children, n.key)
    if (found !== undefined) return found
  }
  return undefined
}

function openCreate(parent?: DepartmentApi): void {
  errors.value = {}
  editing.value = { code: '', name: {}, parentKey: parent ? { [parent.uuid]: true } : null, sort_order: 0, is_active: true }
}

function openEdit(d: DepartmentApi): void {
  errors.value = {}
  const parent = parentOf(d.uuid)
  editing.value = { uuid: d.uuid, code: d.code, name: { ...d.names }, parentKey: parent ? { [parent]: true } : null, sort_order: d.sort_order, is_active: d.is_active }
}

async function save(): Promise<void> {
  const e = editing.value!
  const body = { code: e.code, name: e.name, parent: e.parentKey ? Object.keys(e.parentKey)[0] : null, sort_order: e.sort_order, is_active: e.is_active }
  try {
    if (e.uuid) await send('patch', `/departments/${e.uuid}`, body)
    else await send('post', '/departments', body)
    editing.value = null
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await load()
  } catch (err) {
    if (err instanceof ApiError) errors.value = { ...err.fieldErrors, _: err.status === 422 && !Object.keys(err.fieldErrors).length ? err.message : '' }
  }
}

function archive(d: DepartmentApi): void {
  confirm.require({
    message: t('departments.archive_confirm', { name: d.name }),
    header: t('common.confirm'),
    acceptProps: { label: t('departments.archive'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('delete', `/departments/${d.uuid}`)
        await load()
      } catch (err) {
        if (err instanceof ApiError) toast.add({ severity: 'error', summary: Object.values(err.fieldErrors)[0] ?? err.message, life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <h1 class="page-title !mb-0 flex-1">{{ t('admin.area.departments') }}</h1>
    <Button icon="pi pi-plus" :label="t('departments.new')" data-testid="new-department" @click="openCreate()" />
  </div>
  <TreeTable :value="nodes" size="small" data-testid="department-tree">
    <template #empty>{{ t('departments.empty') }}</template>
    <Column field="label" :header="t('departments.name')" expander>
      <template #body="{ node }">{{ node.data.name }} <span class="text-muted-color text-sm ltr-value">{{ node.data.code }}</span></template>
    </Column>
    <Column :header="t('departments.members')"><template #body="{ node }">{{ node.data.members_count }}</template></Column>
    <Column :header="t('users.status_label')"><template #body="{ node }"><Tag :severity="node.data.is_active ? 'success' : 'secondary'" :value="node.data.is_active ? t('common.active') : t('common.inactive')" /></template></Column>
    <Column style="width: 10rem">
      <template #body="{ node }">
        <Button v-tooltip="t('departments.add_child')" icon="pi pi-plus" text rounded :aria-label="t('departments.add_child')" @click="openCreate(node.data)" />
        <Button icon="pi pi-pencil" text rounded :aria-label="t('common.edit')" @click="openEdit(node.data)" />
        <Button v-tooltip="t('departments.archive')" icon="pi pi-box" text rounded severity="danger" :aria-label="t('departments.archive')" @click="archive(node.data)" />
      </template>
    </Column>
  </TreeTable>

  <Dialog :visible="!!editing" modal :header="editing?.uuid ? t('departments.edit') : t('departments.new')" :style="{ width: '36rem' }" @update:visible="(v: boolean) => !v && (editing = null)">
    <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
      <p v-if="errors._" class="field-error">{{ errors._ }}</p>
      <div class="field"><label for="dc">{{ t('departments.code') }}</label><InputText id="dc" v-model="editing.code" class="ltr-value" data-testid="department-code" /><span v-if="errors.code" class="field-error">{{ errors.code }}</span></div>
      <div v-for="l in session.boot?.locales ?? []" :key="l.code" class="field">
        <label :for="`dn-${l.code}`">{{ t('departments.name') }} ({{ l.native_name }})</label>
        <InputText :id="`dn-${l.code}`" v-model="editing.name[l.code]" :dir="l.direction" :data-testid="`department-name-${l.code}`" />
        <span v-if="errors[`name.${l.code}`]" class="field-error">{{ errors[`name.${l.code}`] }}</span>
      </div>
      <div class="field"><label for="dp">{{ t('departments.parent') }}</label><TreeSelect v-model="editing.parentKey" input-id="dp" :options="nodes" selection-mode="single" show-clear :placeholder="t('departments.top_level')" /><span v-if="errors.parent || errors.department" class="field-error">{{ errors.parent || errors.department }}</span></div>
      <div class="flex gap-4">
        <div class="field"><label for="ds">{{ t('common.sort_order') }}</label><InputNumber v-model="editing.sort_order" input-id="ds" :min="0" :use-grouping="false" /></div>
        <label class="flex items-center gap-2 mt-6"><ToggleSwitch v-model="editing.is_active" />{{ t('common.active') }}</label>
      </div>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('common.save')" data-testid="department-save" />
      </div>
    </form>
  </Dialog>
</template>
