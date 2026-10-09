<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import Canvas from '@/builder/Canvas.vue'
import { pick } from '@/builder/conditions/scope'
import Palette from '@/builder/Palette.vue'
import PreviewDialog from '@/builder/PreviewDialog.vue'
import ProblemsPanel from '@/builder/ProblemsPanel.vue'
import PropertyPanel from '@/builder/PropertyPanel.vue'
import PublishDialog from '@/builder/PublishDialog.vue'
import TemplateDialog from '@/builder/TemplateDialog.vue'
import { createBuilder, provideBuilder } from '@/builder/useBuilder'

/**
 * The form builder (specification §4.3): palette, canvas and properties,
 * with undo/redo, keyboard shortcuts, autosave of the draft, the problems
 * panel, live preview and publishing.
 */
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const formUuid = String(route.params.form)
const builder = createBuilder(formUuid)
provideBuilder(builder)

const previewOpen = ref(false)
const publishOpen = ref(false)
const templateOpen = ref(false)
const problemsOpen = ref(true)
const panel = ref<'palette' | 'canvas' | 'properties'>('canvas')
const rollbackOf = ref<number | null>(route.query.rollback_of ? Number(route.query.rollback_of) : null)

const title = computed(() => (builder.doc ? pick(builder.doc.form.i18n?.name, builder.locale, builder.doc.form.key) : ''))
const saveLabel = computed(() => {
  switch (builder.saveState) {
    case 'saving':
      return { text: t('builder.save.saving'), icon: 'pi pi-spin pi-spinner', severity: 'secondary' }
    case 'dirty':
      return { text: t('builder.save.unsaved'), icon: 'pi pi-circle-fill', severity: 'warn' }
    case 'saved':
      return {
        text: builder.lastSavedAt ? t('builder.save.saved_at', { time: builder.lastSavedAt.toLocaleTimeString(builder.locale) }) : t('builder.save.saved'),
        icon: 'pi pi-check',
        severity: 'success',
      }
    case 'invalid':
      return { text: t('builder.save.invalid'), icon: 'pi pi-times-circle', severity: 'danger' }
    case 'conflict':
      return { text: t('builder.save.conflict'), icon: 'pi pi-exclamation-triangle', severity: 'danger' }
    case 'locked':
      return { text: t('builder.save.locked'), icon: 'pi pi-lock', severity: 'danger' }
    case 'error':
      return { text: t('builder.save.error'), icon: 'pi pi-exclamation-circle', severity: 'danger' }
    default:
      return { text: t('builder.save.loading'), icon: 'pi pi-spin pi-spinner', severity: 'secondary' }
  }
})
const panels = computed(() => [
  { value: 'palette', label: t('builder.palette.title') },
  { value: 'canvas', label: t('builder.canvas.title') },
  { value: 'properties', label: t('builder.properties') },
])

// ---------------------------------------------------------------- keyboard

function editable(target: EventTarget | null): boolean {
  const el = target as HTMLElement | null
  if (!el) return false
  return el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || !!el.closest('[role="dialog"], .p-select-overlay, .p-popover')
}

function onKeydown(e: KeyboardEvent): void {
  const mod = e.ctrlKey || e.metaKey
  const key = e.key.toLowerCase()
  if (mod && key === 's') {
    e.preventDefault()
    void builder.save()
    return
  }
  if (editable(e.target) || previewOpen.value || publishOpen.value || templateOpen.value) return
  if (mod && key === 'z' && !e.shiftKey) builder.undo()
  else if ((mod && key === 'z' && e.shiftKey) || (mod && key === 'y')) builder.redo()
  else if (mod && key === 'c') builder.copy()
  else if (mod && key === 'x') builder.cut()
  else if (mod && key === 'v') {
    if (!builder.paste()) toast.add({ severity: 'warn', summary: t('builder.cannot_place'), life: 3000 })
  } else if (mod && key === 'd') builder.duplicate()
  else if (mod && key === 'a') builder.selection = (builder.doc?.fields.map((f) => f.uuid) ?? []).concat(builder.doc?.groups.map((g) => g.uuid) ?? [])
  else if (e.key === 'Delete' || e.key === 'Backspace') builder.remove()
  else if (e.altKey && e.key === 'ArrowUp') builder.shift(-1)
  else if (e.altKey && e.key === 'ArrowDown') builder.shift(1)
  else if (e.key === 'Escape') builder.select(null)
  else return
  e.preventDefault()
}

function onBeforeUnload(e: BeforeUnloadEvent): void {
  if (builder.hasUnsaved) {
    void builder.save()
    e.preventDefault()
  }
}

onMounted(async () => {
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('beforeunload', onBeforeUnload)
  try {
    await builder.load()
    if (rollbackOf.value) publishOpen.value = true
  } catch {
    /* loadError is shown */
  }
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('beforeunload', onBeforeUnload)
  builder.cancelTimers()
})
onBeforeRouteLeave(async () => {
  if (!builder.doc || builder.saveState === 'conflict' || builder.saveState === 'locked') return true
  if (await builder.flush()) return true
  return window.confirm(t('builder.leave_unsaved'))
})

async function reloadAfterConflict(): Promise<void> {
  await builder.reload()
}
function downloadMine(): void {
  const blob = new Blob([JSON.stringify(builder.doc, null, 2)], { type: 'application/json' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `${builder.doc?.form.key ?? 'form'}-draft.json`
  a.click()
  URL.revokeObjectURL(a.href)
}
function onPublished(version: number): void {
  toast.add({ severity: 'success', summary: t('builder.publish.applied', { n: version }), life: 5000 })
  if (rollbackOf.value) {
    rollbackOf.value = null
    void router.replace({ query: {} })
  }
}
</script>

<template>
  <div class="-m-4 lg:-m-6 flex flex-col h-[calc(100vh-4rem)] min-h-[32rem]" data-testid="form-builder">
    <header class="flex flex-wrap items-center gap-2 px-3 py-2 border-b border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900">
      <RouterLink to="/admin/forms" class="p-button p-button-text p-button-sm" :aria-label="t('builder.back_to_forms')"><i class="pi pi-arrow-left rtl:rotate-180" /></RouterLink>
      <div class="min-w-0">
        <h1 class="font-semibold truncate">{{ title || t('builder.title') }}</h1>
        <div v-if="builder.form" class="flex items-center gap-2 text-xs text-muted-color">
          <span class="ltr-value">{{ builder.form.key }}</span>
          <Tag :value="t(`builder.state.${builder.form.state}`)" severity="secondary" class="!text-xs !py-0" />
          <span>{{ builder.form.version ? t('builder.published_version', { n: builder.form.version }) : t('builder.never_published') }}</span>
        </div>
      </div>
      <span class="flex-1" />
      <span
        :class="['text-xs flex items-center gap-1', saveLabel.severity === 'danger' ? 'text-red-600' : saveLabel.severity === 'warn' ? 'text-orange-600' : 'text-muted-color']"
        role="status"
        aria-live="polite"
        data-testid="save-state"
      >
        <i :class="saveLabel.icon" aria-hidden="true" />{{ saveLabel.text }}
      </span>
      <Button
        size="small"
        severity="secondary"
        icon="pi pi-save"
        :label="t('builder.save.now')"
        :disabled="!builder.doc || builder.saveState === 'saving'"
        data-testid="save-now"
        @click="builder.save()"
      />
      <Button size="small" severity="secondary" icon="pi pi-eye" :label="t('builder.preview.open')" :disabled="!builder.doc" data-testid="open-preview" @click="previewOpen = true" />
      <RouterLink :to="{ name: 'admin.forms.versions', params: { form: formUuid } }" class="p-button p-button-secondary p-button-sm"
        ><i class="pi pi-history me-1" />{{ t('builder.versions') }}</RouterLink
      >
      <Button size="small" icon="pi pi-send" :label="t('builder.publish.open')" :disabled="!builder.doc || builder.saveState === 'locked'" data-testid="open-publish" @click="publishOpen = true" />
    </header>

    <Message v-if="builder.loadError" severity="error" class="m-3">{{ builder.loadError }}</Message>
    <Message v-if="builder.saveState === 'locked'" severity="error" class="m-3">
      {{ builder.failure ?? t('builder.locked_message') }}
      <RouterLink v-if="builder.form?.last_plan" :to="`/admin/schema/plans/${builder.form.last_plan.uuid}`" class="underline ms-1">{{ t('builder.publish.open_repair') }}</RouterLink>
    </Message>
    <Message v-if="builder.saveState === 'error' && builder.failure" severity="error" class="m-3">{{ t('builder.save.error_detail', { error: builder.failure }) }}</Message>

    <div v-if="!builder.doc && !builder.loadError" class="flex-1 flex items-center justify-center"><ProgressSpinner /></div>
    <template v-else-if="builder.doc">
      <div class="lg:hidden p-2 border-b border-surface-200 dark:border-surface-700">
        <SelectButton v-model="panel" :options="panels" option-label="label" option-value="value" :allow-empty="false" size="small" class="w-full" />
      </div>
      <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-[16rem_minmax(0,1fr)_24rem]">
        <aside :class="['min-h-0 border-e border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900', panel === 'palette' ? 'block' : 'hidden lg:block']">
          <Palette @save-template="templateOpen = true" />
        </aside>
        <main :class="['min-h-0 flex flex-col', panel === 'canvas' ? 'flex' : 'hidden lg:flex']">
          <div class="flex-1 min-h-0"><Canvas /></div>
          <div class="border-t border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900" :class="problemsOpen ? 'h-48' : ''">
            <button type="button" class="w-full text-start px-3 py-1 text-xs text-muted-color flex items-center gap-1" :aria-expanded="problemsOpen" @click="problemsOpen = !problemsOpen">
              <i :class="problemsOpen ? 'pi pi-chevron-down' : 'pi pi-chevron-up'" />{{ t('builder.problems.toggle', { n: builder.errors.length + builder.problems.length }) }}
            </button>
            <div v-if="problemsOpen" class="h-[calc(100%-1.75rem)]"><ProblemsPanel /></div>
          </div>
        </main>
        <aside
          :class="['min-h-0 overflow-auto border-s border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900', panel === 'properties' ? 'block' : 'hidden lg:block']"
          :aria-label="t('builder.properties')"
        >
          <PropertyPanel />
        </aside>
      </div>
    </template>

    <PreviewDialog v-model:visible="previewOpen" />
    <PublishDialog v-model:visible="publishOpen" :rollback-of="rollbackOf" @published="onPublished" />
    <TemplateDialog v-model:visible="templateOpen" />

    <Dialog :visible="!!builder.conflict" modal :closable="false" :header="t('builder.conflict.title')" :style="{ width: '32rem' }">
      <p class="mb-3">{{ builder.conflict?.updatedBy ? t('builder.conflict.by', { name: builder.conflict.updatedBy }) : t('builder.conflict.someone') }}</p>
      <p class="mb-3 text-sm text-muted-color">{{ t('builder.conflict.explain') }}</p>
      <div class="flex flex-wrap justify-end gap-2">
        <Button severity="secondary" icon="pi pi-download" :label="t('builder.conflict.download')" @click="downloadMine" />
        <Button icon="pi pi-refresh" :label="t('builder.conflict.reload')" data-testid="conflict-reload" @click="reloadAfterConflict" />
      </div>
    </Dialog>
  </div>
</template>
