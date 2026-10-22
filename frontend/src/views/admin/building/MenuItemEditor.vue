<script setup lang="ts">
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import type { ExpressionScope } from '@/builder/conditions/scope'
import LocaleFields from './LocaleFields.vue'
import { BADGE_KINDS, MENU_TYPES, badgeFits, hasTarget, type BadgeKind, type MenuNode, type MenuType } from './menuTree'
import type { FormOption } from './shared'

/** Pages, reports and dashboards an item can open. */
export interface MenuObjectOption {
  uuid: string
  label: string
}

/** Properties of the selected menu item. */
const props = defineProps<{ forms: FormOption[]; objects: Record<'page' | 'report' | 'dashboard', MenuObjectOption[] | null>; defaultLocale: string }>()
/** The tree node being edited; nested properties are changed in place. */
const node = defineModel<MenuNode>('node', { required: true })
const { t } = useI18n()
const toast = useToast()

const typeOptions = computed(() => MENU_TYPES.map((v) => ({ value: v, label: t(`building.menu.type.${v}`) })))
/** Visibility rules refer to the person only: no record is open while the menu is built. */
const personScope: ExpressionScope = { fields: [], repeaters: [], rows: null }
const objectType = computed(() => (node.value.type === 'page' || node.value.type === 'report' || node.value.type === 'dashboard' ? node.value.type : null))
const targetOptions = computed(() => {
  const type = node.value.type
  if (type === 'form' || type === 'collection')
    return props.forms
      .filter((f) => f.kind === type)
      .map((f) => ({ value: f.uuid, label: `${f.name} (${f.key})`, state: f.state as string | null }))
      .sort((a, b) => a.label.localeCompare(b.label))
  const list = objectType.value ? props.objects[objectType.value] : null
  return (list ?? []).map((o) => ({ value: o.uuid, label: o.label, state: null as string | null })).sort((a, b) => a.label.localeCompare(b.label))
})
const badgeOptions = computed(() => [
  { value: null, label: t('building.menu.badge_none') },
  ...BADGE_KINDS.filter((k) => badgeFits(k, node.value.type)).map((k) => ({ value: k, label: t(`building.menu.badge.${k}`) })),
])
function changeType(type: MenuType): void {
  const n = node.value
  if (type === 'separator' && n.children.length) {
    toast.add({ severity: 'warn', summary: t('building.menu.separator_children'), life: 6000 })
    return
  }
  const before = n.type
  n.type = type
  if (!hasTarget(type) || before !== type) n.target = null
  if (n.badge && (!badgeFits(n.badge, type) || type === 'header' || type === 'separator')) n.badge = null
  if (type === 'separator') n.condition = null
}
function pickTarget(uuid: string | null): void {
  const n = node.value
  n.target = uuid
  const name = targetOptions.value.find((x) => x.value === uuid)?.label.replace(/ \([a-z0-9_]+\)$/, '')
  if (name && !Object.values(n.labels).some(Boolean)) n.labels = { [props.defaultLocale]: name }
}
function pickBadge(kind: BadgeKind | null): void {
  node.value.badge = kind
}
</script>

<template>
  <div class="rounded-xl border border-line p-4 flex flex-col gap-3 bg-card" data-testid="menu-item-editor">
    <h2 class="font-semibold">{{ t('building.menu.item') }}</h2>
    <div class="field">
      <label for="mi-type">{{ t('building.menu.item_type') }}</label>
      <Select :model-value="node.type" input-id="mi-type" :options="typeOptions" option-label="label" option-value="value" @update:model-value="changeType" />
    </div>
    <template v-if="hasTarget(node.type)">
      <div class="field">
        <label for="mi-target">{{ t(`building.menu.target_${node.type}`) }}</label>
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
            <span v-if="option.state" class="text-xs text-muted-color ms-2">{{ t(`building.form_state.${option.state}`) }}</span>
          </template>
        </Select>
        <small v-if="objectType && props.objects[objectType] === null" class="text-muted-color">{{ t(`building.menu.objects_unavailable.${objectType}`) }}</small>
        <small v-else class="text-muted-color">{{ t(objectType ? `building.menu.target_hint_${objectType}` : 'building.menu.target_hint') }}</small>
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
    <div v-if="node.type !== 'separator' && node.type !== 'header'" class="field">
      <label for="mi-badge">{{ t('building.menu.badge_label') }}</label>
      <Select :model-value="node.badge" input-id="mi-badge" :options="badgeOptions" option-label="label" option-value="value" data-testid="menu-badge" @update:model-value="pickBadge" />
      <small class="text-muted-color">{{ t('building.menu.badge_hint') }}</small>
    </div>
    <div v-if="node.type !== 'separator'" class="field" data-testid="menu-condition">
      <ExpressionInput v-model="node.condition" :scope="personScope" expected="boolean" :label="t('building.menu.condition')" />
      <small class="text-muted-color">{{ t('building.menu.condition_hint') }}</small>
    </div>
    <label class="flex items-center gap-2"><ToggleSwitch v-model="node.is_active" />{{ t('building.menu.active') }}</label>
    <p v-if="node.uuid" class="text-xs text-muted-color">
      {{ t('building.menu.visibility_hint') }} <span class="ltr-value">menu.{{ node.uuid }}.view</span>
    </p>
    <p v-else class="text-xs text-muted-color">{{ t('building.menu.visibility_after_save') }}</p>
  </div>
</template>
