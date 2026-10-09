<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed, ref, shallowRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import UserPicker from '@/components/UserPicker.vue'
import FormRenderer from '@/runtime/FormRenderer.vue'
import type { ClientDefinition } from '@/runtime/types'
import { useSession } from '@/stores/session'
import { builderApi, referenceApi, type NamedOption } from './api'
import { locateIssues } from './document'
import { issueText } from './issues'
import { useBuilder } from './useBuilder'

/**
 * Live preview of the saved draft with the runtime renderer (specification
 * §4.3): any mode, desktop/tablet/mobile width, every enabled language, and
 * simulated as a role or a specific user to see exactly what they would see.
 */
const visible = defineModel<boolean>('visible', { required: true })
const { t, te } = useI18n()
const builder = useBuilder()
const session = useSession()

type Mode = 'create' | 'edit' | 'view' | 'print'
const mode = ref<Mode>('create')
const device = ref<'lg' | 'md' | 'xs'>('lg')
const asRole = ref<string | null>(null)
const asUser = ref<{ uuid: string; name: string; email: string } | null>(null)
const roles = ref<NamedOption[]>([])
const definition = shallowRef<ClientDefinition | null>(null)
const values = ref<Record<string, unknown>>({})
const problems = ref<string[]>([])
const loading = ref(false)
const failure = ref<string | null>(null)
const unsaved = ref(false)
let originalLocale: string | null = null

const canPickUser = computed(() => session.can('system.manage_users'))
const modes = computed(() => (['create', 'edit', 'view', 'print'] as const).map((m) => ({ value: m, label: t(`builder.mode.${m}`) })))
const devices = computed(() => [
  { value: 'lg', label: t('builder.canvas.desktop'), icon: 'pi pi-desktop' },
  { value: 'md', label: t('builder.canvas.tablet'), icon: 'pi pi-tablet' },
  { value: 'xs', label: t('builder.canvas.mobile'), icon: 'pi pi-mobile' },
])
const localeOptions = computed(() => builder.locales.map((l) => ({ value: l.code, label: l.native_name })))
const frameClass = computed(() => (device.value === 'xs' ? 'max-w-sm' : device.value === 'md' ? 'max-w-3xl' : 'max-w-5xl'))

async function load(): Promise<void> {
  loading.value = true
  failure.value = null
  try {
    unsaved.value = !(await builder.flush())
    const data = await builderApi.preview(builder.formUuid, { mode: mode.value, as_role: asRole.value, as_user: asRole.value ? null : (asUser.value?.uuid ?? null) })
    definition.value = data.definition as unknown as ClientDefinition
    problems.value = builder.doc ? locateIssues(builder.doc, data.problems, 'problem').map((i) => issueText(t, te, i)) : []
  } catch (e) {
    definition.value = null
    failure.value = e instanceof ApiError ? e.message : String(e)
  } finally {
    loading.value = false
  }
}

watch(visible, async (open) => {
  if (open) {
    originalLocale = session.locale
    values.value = {}
    if (!roles.value.length) roles.value = await referenceApi.roles().catch(() => [])
    await load()
  } else if (originalLocale && originalLocale !== session.locale) {
    await session.switchLocale(originalLocale, false)
  }
})
watch([mode, asRole, asUser], () => {
  if (visible.value) void load()
})
watch(asRole, (r) => {
  if (r) asUser.value = null
})
watch(asUser, (u) => {
  if (u) asRole.value = null
})

async function setLocale(code: string): Promise<void> {
  await session.switchLocale(code, false)
}
</script>

<template>
  <Dialog
    v-model:visible="visible"
    modal
    maximizable
    :header="t('builder.preview.title')"
    :style="{ width: '90vw', height: '90vh' }"
    :breakpoints="{ '640px': '100vw' }"
    content-class="flex flex-col gap-3"
  >
    <div class="flex flex-wrap items-center gap-2" role="toolbar" :aria-label="t('builder.preview.options')">
      <SelectButton v-model="mode" :options="modes" option-label="label" option-value="value" :allow-empty="false" size="small" :aria-label="t('builder.preview.mode')" data-testid="preview-mode" />
      <SelectButton v-model="device" :options="devices" option-value="value" :allow-empty="false" size="small" :aria-label="t('builder.canvas.device')">
        <template #option="{ option }"
          ><i :class="option.icon" :title="option.label" /><span class="sr-only">{{ option.label }}</span></template
        >
      </SelectButton>
      <Select
        :model-value="session.locale"
        :options="localeOptions"
        option-label="label"
        option-value="value"
        size="small"
        :aria-label="t('builder.preview.language')"
        @update:model-value="setLocale"
      />
      <Select
        v-if="roles.length"
        v-model="asRole"
        :options="roles"
        option-label="name"
        option-value="uuid"
        show-clear
        size="small"
        class="w-48"
        :placeholder="t('builder.preview.as_role')"
        :aria-label="t('builder.preview.as_role')"
        data-testid="preview-role"
      />
      <div v-if="canPickUser" class="w-56"><UserPicker v-model="asUser" /></div>
      <Button size="small" text icon="pi pi-refresh" :label="t('builder.preview.reload')" :loading="loading" @click="load" />
    </div>
    <p class="text-xs text-muted-color">{{ asRole ? t('builder.preview.showing_role') : asUser ? t('builder.preview.showing_user', { name: asUser.name }) : t('builder.preview.showing_self') }}</p>
    <Message v-if="unsaved" severity="warn" size="small">{{ t('builder.preview.unsaved') }}</Message>
    <Message v-if="problems.length" severity="warn" size="small">
      <div>{{ t('builder.preview.problems') }}</div>
      <ul class="list-disc ps-5">
        <li v-for="(p, i) in problems" :key="i">{{ p }}</li>
      </ul>
    </Message>
    <Message v-if="failure" severity="error">{{ failure }}</Message>
    <div class="flex-1 overflow-auto bg-page rounded p-3">
      <div :class="['mx-auto bg-card rounded-lg shadow-sm p-4', frameClass]" data-testid="preview-frame">
        <div v-if="loading && !definition" class="flex justify-center p-6"><ProgressSpinner /></div>
        <FormRenderer v-else-if="definition" :key="`${mode}-${asRole}-${asUser?.uuid}`" v-model="values" :definition="definition" :mode="mode" />
      </div>
    </div>
  </Dialog>
</template>
