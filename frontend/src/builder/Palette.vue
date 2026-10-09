<script setup lang="ts">
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { builderApi } from './api'
import { pick } from './conditions/scope'
import { GROUP_TYPES, type GroupType } from './types'
import { useBuilder } from './useBuilder'

/**
 * The palette (specification §4.3): every input and display element by
 * category, the layout and group elements, and the reusable field library.
 * Items are dragged onto the canvas or clicked to insert at the selection.
 */
const emit = defineEmits<{ saveTemplate: [] }>()
const { t } = useI18n()
const builder = useBuilder()
const toast = useToast()
const confirm = useConfirm()
const search = ref('')
const section = ref<'fields' | 'layout' | 'library'>('fields')

const CATEGORY_ORDER = ['text', 'number', 'datetime', 'choice', 'reference', 'file', 'special', 'display', 'action']
const GROUP_ICONS: Record<GroupType, string> = {
  section: 'pi pi-stop',
  fieldset: 'pi pi-box',
  card: 'pi pi-id-card',
  tabs: 'pi pi-folder',
  tab: 'pi pi-folder-open',
  wizard: 'pi pi-directions',
  step: 'pi pi-angle-double-right',
  row: 'pi pi-pause',
  column: 'pi pi-bars',
  panel: 'pi pi-window-maximize',
  accordion: 'pi pi-list',
  repeater: 'pi pi-table',
  subform: 'pi pi-clone',
}

function matches(...texts: string[]): boolean {
  const q = search.value.trim().toLowerCase()
  return q === '' || texts.some((s) => s.toLowerCase().includes(q))
}

const categories = computed(() => {
  const byCat = new Map<string, { key: string; icon: string; label: string }[]>()
  for (const f of builder.catalog.fields) {
    const label = t(`builder.type.${f.key}`)
    if (!matches(label, f.key)) continue
    byCat.set(f.category, [...(byCat.get(f.category) ?? []), { key: f.key, icon: f.icon, label }])
  }
  return [...byCat.entries()].sort((a, b) => CATEGORY_ORDER.indexOf(a[0]) - CATEGORY_ORDER.indexOf(b[0]))
})
const groups = computed(() =>
  GROUP_TYPES.filter((g) => builder.catalog.groups.some((x) => x.key === g))
    .map((g) => ({ key: g, icon: GROUP_ICONS[g], label: t(`builder.group.${g}`) }))
    .filter((g) => matches(g.label, g.key)),
)
const templates = computed(() => builder.templates.filter((tpl) => matches(tpl.name, tpl.category ?? '', tpl.description ?? '')))

function onDragStart(e: DragEvent, payload: NonNullable<typeof builder.drag>): void {
  builder.drag = payload
  // Some browsers only start a drag when data is set; the payload itself stays in the builder state.
  e.dataTransfer?.setData('text/plain', 'lcf-builder')
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'copy'
}
function onDragEnd(): void {
  builder.drag = null
}

function insert(kind: 'field' | 'group', type: string): void {
  if (!builder.insertFromPalette(kind, type)) toast.add({ severity: 'warn', summary: t('builder.cannot_place'), life: 4000 })
}
function insertTemplate(uuid: string): void {
  if (!builder.insertTemplate(uuid)) toast.add({ severity: 'warn', summary: t('builder.cannot_place'), life: 4000 })
}
function removeTemplate(uuid: string, name: string): void {
  confirm.require({
    message: t('builder.library.delete_confirm', { name }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await builderApi.deleteTemplate(uuid)
        await builder.reloadTemplates()
      } catch (e) {
        toast.add({ severity: 'error', summary: e instanceof ApiError ? e.message : String(e), life: 6000 })
      }
    },
  })
}
const sections = computed(() => [
  { value: 'fields' as const, label: t('builder.palette.fields'), icon: 'pi pi-pencil' },
  { value: 'layout' as const, label: t('builder.palette.layout'), icon: 'pi pi-th-large' },
  { value: 'library' as const, label: t('builder.palette.library'), icon: 'pi pi-bookmark' },
])
</script>

<template>
  <nav class="flex flex-col h-full min-h-0" :aria-label="t('builder.palette.title')">
    <div class="p-2 flex flex-col gap-2 border-b border-line">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" size="small" class="w-full" :placeholder="t('builder.palette.search')" :aria-label="t('builder.palette.search')" data-testid="palette-search" />
      </IconField>
      <div class="grid grid-cols-3 gap-1" role="tablist">
        <button
          v-for="s in sections"
          :key="s.value"
          type="button"
          role="tab"
          :aria-selected="section === s.value"
          :class="['rounded px-1 py-1 text-xs flex flex-col items-center gap-0.5', section === s.value ? 'bg-primary-subtle text-on-primary-subtle' : 'hover:bg-subtle']"
          @click="section = s.value"
        >
          <i :class="s.icon" aria-hidden="true" />{{ s.label }}
        </button>
      </div>
    </div>
    <div class="flex-1 overflow-auto p-2 flex flex-col gap-3">
      <template v-if="section === 'fields'">
        <section v-for="[category, items] in categories" :key="category">
          <h3 class="text-xs font-semibold uppercase text-muted-color mb-1">{{ t(`builder.category.${category}`) }}</h3>
          <ul class="grid grid-cols-2 gap-1">
            <li v-for="item in items" :key="item.key">
              <button
                type="button"
                draggable="true"
                class="w-full flex items-center gap-1.5 rounded border border-line px-1.5 py-1 text-xs text-start hover:border-primary hover:bg-primary-subtle cursor-grab"
                :title="t('builder.palette.item_hint')"
                :data-testid="`palette-${item.key}`"
                @dragstart="(e) => onDragStart(e, { source: 'palette', kind: 'field', type: item.key })"
                @dragend="onDragEnd"
                @click="insert('field', item.key)"
              >
                <i :class="item.icon" aria-hidden="true" /><span class="truncate">{{ item.label }}</span>
              </button>
            </li>
          </ul>
        </section>
        <p v-if="!categories.length" class="text-sm text-muted-color">{{ t('common.no_results') }}</p>
      </template>

      <ul v-else-if="section === 'layout'" class="grid grid-cols-2 gap-1">
        <li v-for="g in groups" :key="g.key">
          <button
            type="button"
            draggable="true"
            class="w-full flex items-center gap-1.5 rounded border border-line px-1.5 py-1 text-xs text-start hover:border-primary hover:bg-primary-subtle cursor-grab"
            :title="t('builder.palette.item_hint')"
            :data-testid="`palette-group-${g.key}`"
            @dragstart="(e) => onDragStart(e, { source: 'palette', kind: 'group', type: g.key })"
            @dragend="onDragEnd"
            @click="insert('group', g.key)"
          >
            <i :class="g.icon" aria-hidden="true" /><span class="truncate">{{ g.label }}</span>
          </button>
        </li>
      </ul>

      <template v-else>
        <Button
          size="small"
          severity="secondary"
          icon="pi pi-bookmark"
          :label="t('builder.library.save_selection')"
          :disabled="!builder.selection.length"
          data-testid="save-template"
          @click="emit('saveTemplate')"
        />
        <p v-if="!templates.length" class="text-sm text-muted-color">{{ t('builder.library.empty') }}</p>
        <ul class="flex flex-col gap-1">
          <li
            v-for="tpl in templates"
            :key="tpl.uuid"
            draggable="true"
            class="rounded border border-line p-1.5 flex items-start gap-2 cursor-grab"
            @dragstart="(e) => onDragStart(e, { source: 'template', uuid: tpl.uuid })"
            @dragend="onDragEnd"
          >
            <i :class="tpl.kind === 'group' ? 'pi pi-objects-column' : 'pi pi-pencil'" class="mt-1" aria-hidden="true" />
            <div class="flex-1 min-w-0">
              <div class="text-sm truncate">{{ pick(tpl.names, builder.locale, tpl.name) }}</div>
              <div class="text-xs text-muted-color truncate">{{ tpl.category ?? t(`builder.library.kind_${tpl.kind}`) }} · {{ t('builder.library.used', { n: tpl.usage_count }) }}</div>
            </div>
            <Button size="small" text icon="pi pi-plus" :aria-label="t('builder.library.insert')" @click="insertTemplate(tpl.uuid)" />
            <Button size="small" text severity="danger" icon="pi pi-trash" :aria-label="t('builder.library.delete')" @click="removeTemplate(tpl.uuid, tpl.name)" />
          </li>
        </ul>
      </template>
    </div>
  </nav>
</template>
