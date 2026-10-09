<script setup lang="ts">
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import LocaleFields from './LocaleFields.vue'
import { MENU_TYPES, type MenuNode, type MenuType } from './menuTree'
import type { FormOption } from './shared'

/** Properties of the selected menu item. */
const props = defineProps<{ forms: FormOption[]; defaultLocale: string }>()
/** The tree node being edited; nested properties are changed in place. */
const node = defineModel<MenuNode>('node', { required: true })
const { t } = useI18n()
const toast = useToast()

const typeOptions = computed(() => MENU_TYPES.map((v) => ({ value: v, label: t(`building.menu.type.${v}`) })))
const targetOptions = computed(() =>
  props.forms
    .filter((f) => f.kind === node.value.type)
    .map((f) => ({ value: f.uuid, label: `${f.name} (${f.key})`, state: f.state }))
    .sort((a, b) => a.label.localeCompare(b.label)),
)
function changeType(type: MenuType): void {
  const n = node.value
  if (type === 'separator' && n.children.length) {
    toast.add({ severity: 'warn', summary: t('building.menu.separator_children'), life: 6000 })
    return
  }
  n.type = type
  if (type !== 'form' && type !== 'collection') n.target = null
  else if (n.target && props.forms.find((f) => f.uuid === n.target)?.kind !== type) n.target = null
}
function pickTarget(uuid: string | null): void {
  const n = node.value
  n.target = uuid
  const f = uuid ? props.forms.find((x) => x.uuid === uuid) : undefined
  if (f && !Object.values(n.labels).some(Boolean)) n.labels = { [props.defaultLocale]: f.name }
}
</script>

<template>
  <div class="rounded-xl border border-line p-4 flex flex-col gap-3 bg-card" data-testid="menu-item-editor">
    <h2 class="font-semibold">{{ t('building.menu.item') }}</h2>
    <div class="field">
      <label for="mi-type">{{ t('building.menu.item_type') }}</label>
      <Select :model-value="node.type" input-id="mi-type" :options="typeOptions" option-label="label" option-value="value" @update:model-value="changeType" />
    </div>
    <template v-if="node.type === 'form' || node.type === 'collection'">
      <div class="field">
        <label for="mi-target">{{ node.type === 'form' ? t('building.menu.target_form') : t('building.menu.target_collection') }}</label>
        <Select
          :model-value="node.target"
          input-id="mi-target"
          :options="targetOptions"
          option-label="label"
          option-value="value"
          filter
          :placeholder="t('building.menu.pick_target')"
          data-testid="menu-target"
          @update:model-value="pickTarget"
        >
          <template #option="{ option }">
            <span class="flex-1">{{ option.label }}</span>
            <span class="text-xs text-muted-color ms-2">{{ t(`building.form_state.${option.state}`) }}</span>
          </template>
        </Select>
        <small class="text-muted-color">{{ t('building.menu.target_hint') }}</small>
      </div>
    </template>
    <template v-if="node.type === 'link'">
      <div class="field">
        <label for="mi-url">{{ t('building.menu.url') }}</label>
        <InputText id="mi-url" v-model="node.url" class="ltr-value" placeholder="https://… /…" data-testid="menu-url" />
        <small class="text-muted-color">{{ t('building.menu.url_hint') }}</small>
      </div>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="node.open_in_new_tab" />{{ t('building.menu.new_tab') }}</label>
    </template>
    <template v-if="node.type !== 'separator'">
      <LocaleFields v-model="node.labels" :label="t('building.menu.label')" field="label" id-prefix="mi-label" />
      <div class="field">
        <label for="mi-icon">{{ t('building.icon') }}</label>
        <div class="flex items-center gap-2">
          <InputText id="mi-icon" v-model="node.icon" class="ltr-value flex-1" placeholder="pi pi-inbox" />
          <i :class="node.icon" class="text-xl" aria-hidden="true" />
        </div>
        <small class="text-muted-color">{{ t('building.icon_hint') }}</small>
      </div>
    </template>
    <label class="flex items-center gap-2"><ToggleSwitch v-model="node.is_active" />{{ t('building.menu.active') }}</label>
    <p v-if="node.uuid" class="text-xs text-muted-color">
      {{ t('building.menu.visibility_hint') }} <span class="ltr-value">menu.{{ node.uuid }}.view</span>
    </p>
    <p v-else class="text-xs text-muted-color">{{ t('building.menu.visibility_after_save') }}</p>
  </div>
</template>
