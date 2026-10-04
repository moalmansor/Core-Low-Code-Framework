<script setup lang="ts">
import Button from 'primevue/button'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { canvasView } from './canvas'
import CanvasList from './CanvasList.vue'
import { useBuilder } from './useBuilder'

/** The centre panel: editing toolbar and the live canvas. */
const { t } = useI18n()
const builder = useBuilder()
const devices = computed(() => [
  { value: 'lg', label: t('builder.canvas.desktop'), icon: 'pi pi-desktop' },
  { value: 'md', label: t('builder.canvas.tablet'), icon: 'pi pi-tablet' },
  { value: 'xs', label: t('builder.canvas.mobile'), icon: 'pi pi-mobile' },
])
const widthClass = computed(() => (canvasView.breakpoint === 'xs' ? 'max-w-sm' : canvasView.breakpoint === 'md' ? 'max-w-3xl' : 'max-w-none'))
const localeOptions = computed(() => builder.locales.map((l) => ({ value: l.code, label: l.native_name })))
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform)
const mod = isMac ? '⌘' : 'Ctrl'
</script>

<template>
  <div class="flex flex-col h-full min-h-0">
    <div class="flex flex-wrap items-center gap-1 p-2 border-b border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900" role="toolbar" :aria-label="t('builder.canvas.toolbar')">
      <Button size="small" text icon="pi pi-undo" :disabled="!builder.historyDepth.undo" :aria-label="t('builder.action.undo')" :title="`${t('builder.action.undo')} (${mod}+Z)`" data-testid="undo" @click="builder.undo()" />
      <Button size="small" text icon="pi pi-undo -scale-x-100" :disabled="!builder.historyDepth.redo" :aria-label="t('builder.action.redo')" :title="`${t('builder.action.redo')} (${mod}+Shift+Z)`" data-testid="redo" @click="builder.redo()" />
      <span class="w-px h-5 bg-surface-200 dark:bg-surface-700 mx-1" aria-hidden="true" />
      <Button size="small" text icon="pi pi-clone" :disabled="!builder.selection.length" :aria-label="t('builder.action.copy')" :title="`${t('builder.action.copy')} (${mod}+C)`" @click="builder.copy()" />
      <Button size="small" text icon="pi pi-eject" :disabled="!builder.selection.length" :aria-label="t('builder.action.cut')" :title="`${t('builder.action.cut')} (${mod}+X)`" @click="builder.cut()" />
      <Button size="small" text icon="pi pi-clipboard" :disabled="!builder.clipboardFilled" :aria-label="t('builder.action.paste')" :title="`${t('builder.action.paste')} (${mod}+V)`" @click="builder.paste()" />
      <Button size="small" text icon="pi pi-copy" :disabled="!builder.selection.length" :aria-label="t('builder.action.duplicate')" :title="`${t('builder.action.duplicate')} (${mod}+D)`" @click="builder.duplicate()" />
      <Button size="small" text severity="danger" icon="pi pi-trash" :disabled="!builder.selection.length" :aria-label="t('builder.action.delete')" :title="`${t('builder.action.delete')} (Delete)`" @click="builder.remove()" />
      <span class="flex-1" />
      <Select v-model="canvasView.locale" :options="localeOptions" option-label="label" option-value="value" :placeholder="t('builder.canvas.labels_in')" show-clear size="small" class="w-36" :aria-label="t('builder.canvas.labels_in')" />
      <SelectButton v-model="canvasView.breakpoint" :options="devices" option-value="value" :allow-empty="false" size="small" :aria-label="t('builder.canvas.device')">
        <template #option="{ option }"><i :class="option.icon" :title="option.label" /><span class="sr-only">{{ option.label }}</span></template>
      </SelectButton>
    </div>
    <div class="flex-1 overflow-auto p-4 bg-surface-100 dark:bg-surface-950" data-testid="canvas" @click="builder.select(null)">
      <div :class="['mx-auto bg-surface-0 dark:bg-surface-900 rounded-lg shadow-sm p-3 min-h-64', widthClass]">
        <CanvasList :parent="null" />
      </div>
      <p class="text-xs text-muted-color text-center mt-3">{{ t('builder.canvas.shortcuts', { mod }) }}</p>
    </div>
  </div>
</template>
