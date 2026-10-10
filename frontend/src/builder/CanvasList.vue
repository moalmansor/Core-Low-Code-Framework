<script setup lang="ts">
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { autoSpan } from '@/runtime/autoLayout'
import { canvasView, spanAt } from './canvas'
import { pick } from './conditions/scope'
import { childrenOf, findField, findGroup } from './document'
import type { FieldDef, GroupDef } from './types'
import { useBuilder } from './useBuilder'

/**
 * One container of the canvas (the form root or a group) with its children,
 * recursively. Native drag and drop: elements and palette items are dropped
 * before or after an element, or into an empty container; a line shows where.
 */
const props = withDefaults(defineProps<{ parent: string | null; horizontal?: boolean }>(), { horizontal: false })
const { t } = useI18n()
const builder = useBuilder()

const items = computed(() => {
  const d = builder.doc
  if (!d) return []
  return childrenOf(d, props.parent).map((ref) => (ref.kind === 'group' ? { kind: 'group' as const, group: findGroup(d, ref.uuid)! } : { kind: 'field' as const, field: findField(d, ref.uuid)! }))
})
const parentGroup = computed(() => (props.parent ? findGroup(builder.doc!, props.parent) : undefined))
const locale = computed(() => canvasView.locale ?? builder.locale)
const issues = computed(() => {
  const map = new Map<string, 'error' | 'problem'>()
  for (const i of [...builder.problems, ...builder.errors]) if (i.uuid) map.set(i.uuid, i.severity === 'error' || map.get(i.uuid) === 'error' ? 'error' : 'problem')
  return map
})
const ruleCounts = computed(() => {
  const map = new Map<string, number>()
  for (const c of builder.doc?.conditions ?? []) map.set(c.owner.uuid, (map.get(c.owner.uuid) ?? 0) + 1)
  return map
})

function uuidOf(item: (typeof items.value)[number]): string {
  return item.kind === 'group' ? item.group.uuid : item.field.uuid
}

function span(item: (typeof items.value)[number]): number {
  if (item.kind === 'field') {
    const g = parentGroup.value
    const sized = g?.type === 'row' || g?.type === 'column' || Object.values(g?.layout?.columns ?? {}).some((v) => v)
    return spanAt(item.field.ui?.width, canvasView.breakpoint, sized ? undefined : autoSpan(item.field.type))
  }
  if (item.group.type === 'column' || item.group.layout?.span) return spanAt(item.group.layout?.span, canvasView.breakpoint)
  return 12
}

// ---------------------------------------------------------------- drag and drop

function onItemDragOver(e: DragEvent, index: number): void {
  if (!builder.canDrop(props.parent)) return
  e.preventDefault()
  e.stopPropagation()
  const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
  let after: boolean
  if (props.horizontal) {
    const rtl = getComputedStyle(e.currentTarget as HTMLElement).direction === 'rtl'
    after = rtl ? e.clientX < rect.left + rect.width / 2 : e.clientX > rect.left + rect.width / 2
  } else after = e.clientY > rect.top + rect.height / 2
  canvasView.indicator = { parent: props.parent, index: after ? index + 1 : index }
  if (e.dataTransfer) e.dataTransfer.dropEffect = builder.drag?.source === 'canvas' ? 'move' : 'copy'
}

function onListDragOver(e: DragEvent): void {
  if (!builder.canDrop(props.parent)) return
  e.preventDefault()
  e.stopPropagation()
  if (canvasView.indicator?.parent !== props.parent) canvasView.indicator = { parent: props.parent, index: items.value.length }
}

function onDrop(e: DragEvent): void {
  const at = canvasView.indicator
  if (!at || at.parent !== props.parent) return
  e.preventDefault()
  e.stopPropagation()
  canvasView.indicator = null
  if (!builder.drop(props.parent, at.index)) builder.drag = null
}

function onDragStart(e: DragEvent, uuid: string): void {
  e.stopPropagation()
  if (!builder.selection.includes(uuid)) builder.select(uuid)
  builder.drag = { source: 'canvas', uuids: [...builder.selection] }
  e.dataTransfer?.setData('text/plain', 'lcf-builder')
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}

function onDragEnd(): void {
  builder.drag = null
  canvasView.indicator = null
}

const showLine = (index: number) => canvasView.indicator?.parent === props.parent && canvasView.indicator.index === index && builder.drag !== null

// ---------------------------------------------------------------- selection

function onClick(e: MouseEvent, uuid: string): void {
  e.stopPropagation()
  builder.select(uuid, e.shiftKey ? 'range' : e.ctrlKey || e.metaKey ? 'toggle' : 'replace')
}

function onKey(e: KeyboardEvent, uuid: string): void {
  if (e.target !== e.currentTarget) return
  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault()
    builder.select(uuid, e.shiftKey ? 'range' : e.ctrlKey || e.metaKey ? 'toggle' : 'replace')
  }
}

function fieldLabel(f: FieldDef): string {
  return pick(f.i18n?.label, locale.value, f.key)
}
function groupTitle(g: GroupDef): string {
  return pick(g.i18n?.title, locale.value, t(`builder.group.${g.type}`))
}
function fieldIcon(f: FieldDef): string {
  return builder.typeInfo(f.type)?.icon ?? 'pi pi-question'
}
const dir = computed(() => builder.locales.find((l) => l.code === locale.value)?.direction ?? 'ltr')
</script>

<template>
  <div :class="['grid grid-cols-12 gap-2 min-h-12 rounded p-1', items.length === 0 ? 'border border-dashed border-line-strong' : '']" :dir="dir" role="list" @dragover="onListDragOver" @drop="onDrop">
    <template v-for="(item, index) in items" :key="uuidOf(item)">
      <div role="listitem" :style="{ gridColumn: `span ${span(item)} / span ${span(item)}` }" class="relative min-w-0" @dragover="(e) => onItemDragOver(e, index)" @drop="onDrop">
        <div v-if="showLine(index)" :class="['absolute bg-primary rounded z-10', horizontal ? 'inset-y-0 -start-1.5 w-1' : 'inset-x-0 -top-1.5 h-1']" aria-hidden="true" />
        <div
          v-if="showLine(index + 1) && index === items.length - 1"
          :class="['absolute bg-primary rounded z-10', horizontal ? 'inset-y-0 -end-1.5 w-1' : 'inset-x-0 -bottom-1.5 h-1']"
          aria-hidden="true"
        />

        <!-- Group -->
        <section
          v-if="item.kind === 'group'"
          :tabindex="0"
          draggable="true"
          :aria-selected="builder.selection.includes(item.group.uuid)"
          :aria-label="`${t(`builder.group.${item.group.type}`)}: ${groupTitle(item.group)}`"
          :data-testid="`canvas-${item.group.key}`"
          :class="[
            'rounded-md border bg-card outline-none focus-visible:ring-2 focus-visible:ring-primary',
            builder.selection.includes(item.group.uuid) ? 'border-primary ring-1 ring-primary' : 'border-line-strong',
            issues.get(item.group.uuid) === 'error' ? '!border-danger' : issues.get(item.group.uuid) === 'problem' ? '!border-warning' : '',
          ]"
          @click="(e) => onClick(e, item.group.uuid)"
          @keydown="(e) => onKey(e, item.group.uuid)"
          @dragstart="(e) => onDragStart(e, item.group.uuid)"
          @dragend="onDragEnd"
        >
          <header class="flex items-center gap-2 px-2 py-1 border-b border-line bg-subtle rounded-t-md cursor-grab">
            <i class="pi pi-objects-column text-xs text-muted-color" aria-hidden="true" />
            <span class="font-medium text-sm truncate">{{ groupTitle(item.group) }}</span>
            <span class="text-xs text-muted-color">{{ t(`builder.group.${item.group.type}`) }}</span>
            <span class="text-xs text-muted-color ltr-value">{{ item.group.key }}</span>
            <span v-if="ruleCounts.get(item.group.uuid)" class="text-xs text-primary" :title="t('builder.canvas.rules', { n: ruleCounts.get(item.group.uuid) })"
              ><i class="pi pi-bolt" aria-hidden="true" />{{ ruleCounts.get(item.group.uuid) }}</span
            >
            <i
              v-if="issues.get(item.group.uuid)"
              :class="issues.get(item.group.uuid) === 'error' ? 'pi pi-times-circle text-danger' : 'pi pi-exclamation-triangle text-warning'"
              :title="t('builder.problems.title')"
            />
            <span class="flex-1" />
            <template v-if="builder.selection.length === 1 && builder.selection[0] === item.group.uuid">
              <Button size="small" text icon="pi pi-arrow-up" :aria-label="t('builder.move_up')" :disabled="index === 0" @click.stop="builder.shift(-1, item.group.uuid)" />
              <Button size="small" text icon="pi pi-arrow-down" :aria-label="t('builder.move_down')" :disabled="index === items.length - 1" @click.stop="builder.shift(1, item.group.uuid)" />
              <Button size="small" text icon="pi pi-copy" :aria-label="t('builder.action.duplicate')" @click.stop="builder.duplicate()" />
              <Button size="small" text severity="danger" icon="pi pi-trash" :aria-label="t('builder.action.delete')" @click.stop="builder.remove([item.group.uuid])" />
            </template>
          </header>
          <div class="p-2">
            <CanvasList :parent="item.group.uuid" :horizontal="item.group.type === 'row'" />
          </div>
        </section>

        <!-- Field -->
        <div
          v-else
          :tabindex="0"
          draggable="true"
          :aria-selected="builder.selection.includes(item.field.uuid)"
          :aria-label="`${t(`builder.type.${item.field.type}`)}: ${fieldLabel(item.field)}`"
          :data-testid="`canvas-${item.field.key}`"
          :class="[
            'rounded-md border px-2 py-1.5 bg-card cursor-grab outline-none focus-visible:ring-2 focus-visible:ring-primary',
            builder.selection.includes(item.field.uuid) ? 'border-primary ring-1 ring-primary' : 'border-transparent hover:border-line-strong',
            issues.get(item.field.uuid) === 'error' ? '!border-danger' : issues.get(item.field.uuid) === 'problem' ? '!border-warning' : '',
          ]"
          @click="(e) => onClick(e, item.field.uuid)"
          @keydown="(e) => onKey(e, item.field.uuid)"
          @dragstart="(e) => onDragStart(e, item.field.uuid)"
          @dragend="onDragEnd"
        >
          <div class="flex items-center gap-1.5 text-sm">
            <i :class="fieldIcon(item.field)" class="text-xs text-muted-color" aria-hidden="true" />
            <span class="font-medium truncate">{{ fieldLabel(item.field) }}</span>
            <span v-if="item.field.validation?.required" class="text-danger" :title="t('builder.validation.required')">*</span>
            <span v-if="ruleCounts.get(item.field.uuid)" class="text-xs text-primary" :title="t('builder.canvas.rules', { n: ruleCounts.get(item.field.uuid) })"
              ><i class="pi pi-bolt" aria-hidden="true" />{{ ruleCounts.get(item.field.uuid) }}</span
            >
            <i v-if="item.field.behavior?.formula" class="pi pi-calculator text-xs text-primary" :title="t('builder.behavior.formula')" />
            <i
              v-if="issues.get(item.field.uuid)"
              :class="issues.get(item.field.uuid) === 'error' ? 'pi pi-times-circle text-danger' : 'pi pi-exclamation-triangle text-warning'"
              :title="t('builder.problems.title')"
            />
            <span class="flex-1" />
            <span class="text-xs text-muted-color ltr-value truncate">{{ item.field.key }}</span>
            <template v-if="builder.selection.length === 1 && builder.selection[0] === item.field.uuid">
              <Button size="small" text icon="pi pi-arrow-up" :aria-label="t('builder.move_up')" :disabled="index === 0" @click.stop="builder.shift(-1, item.field.uuid)" />
              <Button size="small" text icon="pi pi-arrow-down" :aria-label="t('builder.move_down')" :disabled="index === items.length - 1" @click.stop="builder.shift(1, item.field.uuid)" />
              <Button size="small" text icon="pi pi-copy" :aria-label="t('builder.action.duplicate')" @click.stop="builder.duplicate()" />
              <Button size="small" text severity="danger" icon="pi pi-trash" :aria-label="t('builder.action.delete')" @click.stop="builder.remove([item.field.uuid])" />
            </template>
          </div>
          <div
            v-if="builder.typeInfo(item.field.type)?.storage !== 'none'"
            class="mt-1 h-7 rounded border border-line bg-subtle px-2 text-xs text-muted-color flex items-center truncate"
            aria-hidden="true"
          >
            {{ pick(item.field.i18n?.placeholder, locale, t(`builder.type.${item.field.type}`)) }}
          </div>
          <div v-else-if="item.field.i18n?.content" class="mt-1 text-xs text-muted-color line-clamp-2">{{ pick(item.field.i18n.content, locale) }}</div>
        </div>
      </div>
    </template>
    <div v-if="items.length === 0" class="col-span-12 text-xs text-muted-color text-center py-3 relative">
      <div v-if="showLine(0)" class="absolute inset-x-0 top-0 h-1 bg-primary rounded" aria-hidden="true" />
      {{
        parentGroup && ['tabs', 'wizard', 'row'].includes(parentGroup.type)
          ? t('builder.canvas.drop_structural', { type: t(`builder.group.${parentGroup.type === 'tabs' ? 'tab' : parentGroup.type === 'wizard' ? 'step' : 'column'}`) })
          : t('builder.canvas.drop_here')
      }}
    </div>
  </div>
</template>
