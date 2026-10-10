<script setup lang="ts">
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute } from 'vue-router'
import { get, send } from '@/api/http'
import { useSession } from '@/stores/session'
import MenuItemEditor from './MenuItemEditor.vue'
import MenuTreeList from './MenuTreeList.vue'
import {
  MAX_MENU_LEVELS,
  MENU_TYPES,
  blankNode,
  findNode,
  fromApi,
  indent,
  moveBy,
  outdent,
  removeNode,
  toPayload,
  validateTree,
  type MenuItemApi,
  type MenuNode,
  type MenuTreeContext,
} from './menuTree'
import { errorText, fetchFormOptions, FORM_OPTION_PERMISSIONS, type FormOption } from './shared'
import { humanize } from '@/runtime/i18nText'

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const session = useSession()
const appUuid = computed(() => String(route.params.application))

const appName = ref('')
const tree = ref<MenuNode[]>([])
const saved = ref('[]')
const forms = ref<FormOption[]>([])
const formsAvailable = FORM_OPTION_PERMISSIONS.some((p) => session.can(p))
const loading = ref(true)
const saving = ref(false)
const selectedId = ref<string | null>(null)
const showProblems = ref(false)
const defaultLocale = computed(() => session.boot?.default_locale ?? 'en')

const selected = computed(() => (selectedId.value ? findNode(tree.value, selectedId.value) : null))
const dirty = computed(() => JSON.stringify(toPayload(tree.value)) !== saved.value)
const problems = computed(() => validateTree(tree.value, defaultLocale.value))
const problemIds = computed(() => new Set(problems.value.map((p) => p.id)))

function adopt(items: MenuItemApi[]): void {
  tree.value = fromApi(items)
  saved.value = JSON.stringify(toPayload(tree.value))
  selectedId.value = null
  showProblems.value = false
}

onMounted(async () => {
  try {
    const [apps, menu] = await Promise.all([get<{ data: { uuid: string; key: string; name: string }[] }>('/applications'), get<{ data: MenuItemApi[] }>(`/applications/${appUuid.value}/menu`)])
    const app = apps.data.find((a) => a.uuid === appUuid.value)
    appName.value = app?.name ?? (app ? humanize(app.key) : '')
    adopt(menu.data)
    if (formsAvailable) forms.value = await fetchFormOptions()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})

onBeforeRouteLeave(() => !dirty.value || window.confirm(t('building.unsaved_leave')))

const formsById = computed(() => new Map(forms.value.map((f) => [f.uuid, f])))
function labelOf(n: MenuNode): string {
  return n.labels[session.locale] || n.labels[defaultLocale.value] || Object.values(n.labels).find(Boolean) || t('building.menu.untitled')
}
function targetOf(n: MenuNode): string {
  if (n.type === 'link') return n.url
  if ((n.type === 'form' || n.type === 'collection') && n.target) {
    const f = formsById.value.get(n.target)
    return f ? f.key : ''
  }
  return ''
}

const ctx: MenuTreeContext = {
  selected: () => selectedId.value,
  hasProblem: (id) => showProblems.value && problemIds.value.has(id),
  labelOf,
  targetOf,
  select: (id) => (selectedId.value = id),
  remove: (id) => {
    const node = findNode(tree.value, id)
    if (!node) return
    const drop = () => {
      removeNode(tree.value, id)
      if (selectedId.value && !findNode(tree.value, selectedId.value)) selectedId.value = null
    }
    if (!node.children.length) return drop()
    confirm.require({
      message: t('building.menu.remove_with_children', { name: labelOf(node), n: node.children.length }),
      header: t('common.confirm'),
      acceptProps: { label: t('common.delete'), severity: 'danger' },
      rejectProps: { label: t('common.cancel'), severity: 'secondary' },
      accept: drop,
    })
  },
  addChild: (id) => {
    const parent = findNode(tree.value, id)
    if (!parent) return
    const child = blankNode('form')
    parent.children.push(child)
    selectedId.value = child.id
  },
  move: (id, how) => {
    if (how === 'up') moveBy(tree.value, id, -1)
    else if (how === 'down') moveBy(tree.value, id, 1)
    else if (how === 'in') indent(tree.value, id)
    else outdent(tree.value, id)
  },
}
provide('menuTree', ctx)

const addMenu = ref<InstanceType<typeof Menu> | null>(null)
const addItems = computed(() =>
  MENU_TYPES.map((type) => ({
    label: t(`building.menu.type.${type}`),
    command: () => {
      const n = blankNode(type)
      tree.value.push(n)
      selectedId.value = n.id
    },
  })),
)

const problemMessages = computed(() =>
  problems.value.map((p) => {
    const node = p.id ? findNode(tree.value, p.id) : null
    return node ? `${labelOf(node)}: ${t(`building.menu.problem.${p.code}`)}` : t(`building.menu.problem.${p.code}`, { n: MAX_MENU_LEVELS })
  }),
)

async function save(): Promise<void> {
  showProblems.value = true
  if (problems.value.length) return
  saving.value = true
  try {
    const res = await send<{ data: MenuItemApi[] }>('put', `/applications/${appUuid.value}/menu`, { items: toPayload(tree.value) })
    adopt(res.data)
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
async function discard(): Promise<void> {
  const res = await get<{ data: MenuItemApi[] }>(`/applications/${appUuid.value}/menu`)
  adopt(res.data)
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <RouterLink :to="{ name: 'admin.applications' }" class="text-sm"><i class="pi pi-arrow-left rtl:rotate-180 me-1" />{{ t('admin.area.applications') }}</RouterLink>
    <h1 class="page-title !mb-0 flex-1">{{ t('building.menu.title', { name: appName }) }}</h1>
    <span v-if="dirty" class="text-sm text-muted-color">{{ t('building.unsaved') }}</span>
    <Button icon="pi pi-plus" :label="t('building.menu.add')" severity="secondary" aria-haspopup="true" data-testid="menu-add" @click="addMenu?.toggle($event)" />
    <Menu ref="addMenu" :model="addItems" popup />
    <Button severity="secondary" :label="t('common.reset')" :disabled="!dirty" @click="discard" />
    <Button icon="pi pi-check" :label="t('common.save')" :disabled="!dirty" :loading="saving" data-testid="menu-save" @click="save" />
  </div>
  <Message severity="secondary" size="small" class="mb-3">{{ t('building.menu.hint') }}</Message>
  <Message v-if="!formsAvailable" severity="warn" size="small" class="mb-3">{{ t('building.menu.forms_unavailable') }}</Message>
  <Message v-if="showProblems && problemMessages.length" severity="error" class="mb-3">
    <ul class="list-disc ps-5">
      <li v-for="(m, i) in problemMessages" :key="i">{{ m }}</li>
    </ul>
  </Message>

  <div v-if="!loading" class="flex flex-col lg:flex-row gap-4">
    <section class="flex-1 min-w-0" data-testid="menu-tree">
      <p v-if="!tree.length" class="text-muted-color mb-2">{{ t('building.menu.empty') }}</p>
      <MenuTreeList :nodes="tree" :depth="0" />
    </section>
    <aside class="lg:w-96 shrink-0">
      <MenuItemEditor v-if="selected" :node="selected" :forms="forms" :default-locale="defaultLocale" />
      <p v-if="!selected" class="text-muted-color text-sm">{{ t('building.menu.select_item') }}</p>
    </aside>
  </div>
</template>
