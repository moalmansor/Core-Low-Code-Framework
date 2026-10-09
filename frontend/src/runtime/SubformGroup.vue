<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import { computed, inject, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, http, ensureCsrf } from '@/api/http'
import { newUuid } from './api'
import { RECORD_UUID, useRenderer } from './context'
import FormRenderer from './FormRenderer.vue'
import GroupBody from './GroupBody.vue'
import { pickText } from './i18nText'
import type { ClientDefinition, ClientGroup, Values } from './types'

/**
 * Inline sub-form of a linked form: its records live in the linked form's own
 * table with a key to this record. Once this record is stored, the group lists
 * its linked records and adds new ones through
 * `/r/{form}/{record}/subforms/{group}`, which runs the linked form's pipeline.
 */
const props = defineProps<{ group: ClientGroup }>()
const ctx = useRenderer()
const { t } = useI18n()
const recordUuid = inject(RECORD_UUID, ref(null))
const title = computed(() => pickText(props.group.i18n?.title, ctx.locale.value) ?? props.group.key)
const description = computed(() => pickText(props.group.i18n?.description, ctx.locale.value))
const target = computed(() => props.group.subform?.form ?? null)
const endpoint = computed(() => (ctx.formUuid.value && recordUuid.value ? `/r/${ctx.formUuid.value}/${recordUuid.value}/subforms/${props.group.key}` : null))

interface LinkedRecord {
  uuid: string
  title: string | null
  system: { record_number?: string | null }
}
const items = ref<LinkedRecord[]>([])
const total = ref(0)
const canAdd = ref(false)
const loadError = ref('')

async function load(): Promise<void> {
  if (!endpoint.value) return
  loadError.value = ''
  try {
    const res = await get<{ data: LinkedRecord[]; meta: { total: number; can_add: boolean } }>(endpoint.value, { per_page: 100 })
    items.value = res.data
    total.value = res.meta.total
    canAdd.value = res.meta.can_add
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : String(e)
  }
}
watch(endpoint, load, { immediate: true })

const adding = ref(false)
const addDefinition = ref<ClientDefinition | null>(null)
const addValues = ref<Values>({})
const addErrors = ref<Record<string, string[]>>({})
const addMessage = ref('')
const saving = ref(false)
let idempotencyKey = ''

async function openAdd(): Promise<void> {
  if (!target.value) return
  addValues.value = {}
  addErrors.value = {}
  addMessage.value = ''
  idempotencyKey = newUuid()
  addDefinition.value = (await get<{ data: ClientDefinition }>(`/r/${target.value}/definition`, { mode: 'create' })).data
  adding.value = true
}

async function saveAdd(): Promise<void> {
  if (!endpoint.value) return
  saving.value = true
  addErrors.value = {}
  addMessage.value = ''
  try {
    await ensureCsrf()
    await http.post(endpoint.value, { values: addValues.value }, { headers: { 'Idempotency-Key': idempotencyKey } })
    adding.value = false
    await load()
  } catch (e) {
    if (e instanceof ApiError) {
      addErrors.value = e.body.errors ?? {}
      addMessage.value = e.message
    } else {
      addMessage.value = String(e)
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="rounded-lg border border-dashed border-surface-300 dark:border-surface-600 p-4" :data-group="group.key">
    <header class="flex flex-wrap items-center gap-2 mb-2">
      <i class="pi pi-clone" />
      <h3 class="text-base font-semibold flex-1">{{ title }}</h3>
      <Button v-if="endpoint && canAdd && ctx.mode.value !== 'print'" type="button" :label="t('runtime.subform_add')" icon="pi pi-plus" size="small" data-testid="subform-add" @click="openAdd" />
      <RouterLink v-if="target && ctx.formUuid.value && ctx.mode.value !== 'print'" v-slot="{ navigate }" :to="`/app/${target}`" custom>
        <Button type="button" :label="t('runtime.subform_open')" icon="pi pi-external-link" size="small" outlined @click="navigate" />
      </RouterLink>
    </header>
    <p v-if="description" class="text-sm text-muted-color mb-2">{{ description }}</p>
    <template v-if="endpoint">
      <Message v-if="loadError" severity="error" size="small">{{ loadError }}</Message>
      <p v-else-if="items.length === 0" class="text-sm text-muted-color">{{ t('runtime.subform_empty') }}</p>
      <ul v-else class="flex flex-col gap-1" data-testid="subform-items">
        <li v-for="item in items" :key="item.uuid">
          <RouterLink :to="`/app/${target}/${item.uuid}`" class="text-primary hover:underline">{{ item.title ?? item.system.record_number ?? item.uuid }}</RouterLink>
        </li>
      </ul>
      <p v-if="total > items.length" class="text-xs text-muted-color mt-1">{{ t('runtime.subform_more', { n: total - items.length }) }}</p>
    </template>
    <p v-else class="text-sm text-muted-color">{{ ctx.mode.value === 'create' ? t('runtime.subform_after_save') : t('runtime.subform_hint') }}</p>
    <GroupBody :group="group" :row="null" />

    <Dialog v-model:visible="adding" modal :header="t('runtime.subform_add_title', { name: title })" class="w-full max-w-3xl">
      <Message v-if="addMessage" severity="error" size="small" class="mb-3">{{ addMessage }}</Message>
      <FormRenderer v-if="addDefinition" v-model="addValues" :definition="addDefinition" mode="create" :form-uuid="target ?? undefined" :errors="addErrors" />
      <template #footer>
        <Button type="button" :label="t('common.cancel')" text @click="adding = false" />
        <Button type="button" :label="t('common.save')" icon="pi pi-check" :loading="saving" data-testid="subform-save" @click="saveAdd" />
      </template>
    </Dialog>
  </section>
</template>
