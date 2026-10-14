<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ConfigField from '@/components/config/ConfigField.vue'
import { useSession } from '@/stores/session'
import { fieldTreeApi, labelOf, type Path, type PathNode } from '@/views/admin/formconfig/api'
import PathPicker from '@/views/admin/formconfig/PathPicker.vue'
import type { Placeholder } from '@/views/admin/notifications/api'
import FormatPicker from './FormatPicker.vue'
import { token, valueKind, type Format } from './formats'

/**
 * Chooses a value for a template instead of typing its placeholder
 * (specification §4.17): a field of the record or of a form it links to,
 * followed through any number of links, or a system value (record number,
 * status, dates, who created or changed it, who is producing the document),
 * with how it is shown. Emits the path and its format.
 */
const props = withDefaults(
  defineProps<{
    /** The form whose fields are offered (its field tree is loaded). */
    form?: string | null
    /** Or a field tree given directly (a linked form's, for its columns). */
    tree?: PathNode[] | null
    /** System values offered besides the fields. */
    system?: Placeholder[]
    label?: string
    icon?: string
    testid?: string
  }>(),
  { form: null, tree: null, system: () => [], label: undefined, icon: 'pi pi-plus-circle', testid: undefined },
)
const emit = defineEmits<{ choose: [path: string, format: Format] }>()
const { t, locale } = useI18n()
const session = useSession()
const dl = computed(() => session.boot?.default_locale ?? 'en')

const open = ref(false)
const loaded = ref<PathNode[]>([])
const cache = new Map<string, PathNode[]>()
watch(open, async (v) => {
  if (!v || props.tree || !props.form) return
  if (!cache.has(props.form)) cache.set(props.form, await fieldTreeApi.load(props.form).catch(() => []))
  loaded.value = cache.get(props.form) ?? []
})
const nodes = computed(() => props.tree ?? loaded.value)
const hasFields = computed(() => !!props.form || !!props.tree)
const sources = computed(() => [...(hasFields.value ? [{ value: 'field', label: t('tpl.pick.from_record') }] : []), ...(props.system.length ? [{ value: 'system', label: t('tpl.pick.system') }] : [])])
const source = ref<'field' | 'system'>('field')
watch(
  sources,
  (s) => {
    if (!s.some((x) => x.value === source.value)) source.value = (s[0]?.value as 'field' | 'system') ?? 'system'
  },
  { immediate: true },
)
const path = ref<Path | null>(null)
const systemToken = ref<string | null>(null)
const format = ref<Format>({})
watch([source, path, systemToken], () => (format.value = {}))

const byPath = computed(() => {
  const out = new Map<string, PathNode>()
  const walk = (list: PathNode[]) => list.forEach((n) => (out.set(n.path.join('.'), n), walk(n.children)))
  walk(nodes.value)
  return out
})
const chosen = computed(() => (source.value === 'system' ? systemToken.value : path.value?.length ? path.value.join('.') : null))
const SYSTEM_KINDS: Record<string, 'date' | 'datetime'> = { '@created_at': 'datetime', '@updated_at': 'datetime', '@now': 'datetime', '@today': 'date' }
const kind = computed(() => {
  if (!chosen.value) return 'text'
  if (source.value === 'system') return SYSTEM_KINDS[chosen.value] ?? 'text'
  return valueKind(byPath.value.get(chosen.value)?.storage ?? (chosen.value.endsWith('@created_at') || chosen.value.endsWith('@updated_at') ? 'datetime' : null))
})
const chosenLabel = computed(() => {
  if (!chosen.value) return ''
  if (source.value === 'system') return props.system.find((s) => s.token === chosen.value)?.label ?? ''
  const parts = chosen.value.split('.')
  return parts
    .map((_, i) => byPath.value.get(parts.slice(0, i + 1).join('.')))
    .map((n) => (n ? labelOf(n.label, locale.value, n.key, dl.value) : ''))
    .join(' › ')
})
const systemOptions = computed(() => props.system.map((s) => ({ value: s.token, label: s.label })))

function choose(): void {
  if (!chosen.value) return
  emit('choose', chosen.value, format.value)
  open.value = false
  path.value = null
  systemToken.value = null
}
</script>

<template>
  <Button :icon="icon" :label="label ?? t('tpl.pick.insert')" size="small" text class="self-start !px-1" :data-testid="testid" @click="open = true" />
  <Dialog v-model:visible="open" modal :header="t('tpl.pick.title')" :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }" :data-testid="testid ? `${testid}-dialog` : undefined">
    <div class="flex flex-col gap-4">
      <SelectButton v-if="sources.length > 1" v-model="source" :options="sources" option-label="label" option-value="value" :allow-empty="false" size="small" />
      <ConfigField v-if="source === 'field'" :label="t('tpl.pick.field')" :for="`${testid ?? 'pick'}-path`" width="lg" :hint="t('tpl.pick.field_hint')">
        <PathPicker v-model="path" :tree="nodes" leaf-only :default-locale="dl" :input-id="`${testid ?? 'pick'}-path`" />
      </ConfigField>
      <ConfigField v-else :label="t('tpl.pick.system_value')" :for="`${testid ?? 'pick'}-sys`" width="md">
        <Select v-model="systemToken" :input-id="`${testid ?? 'pick'}-sys`" :options="systemOptions" option-label="label" option-value="value" filter size="small" />
      </ConfigField>
      <section v-if="chosen" class="flex flex-col gap-2">
        <h3 class="m-0 text-sm font-semibold">{{ t('tpl.format.title') }}</h3>
        <FormatPicker v-model="format" :kind="kind" :id-prefix="`${testid ?? 'pick'}-fmt`" />
        <p class="m-0 text-sm">
          <span class="text-muted-color">{{ chosenLabel }}</span>
          <code class="ltr-value ms-2 text-xs" dir="ltr">{{ token(chosen, format) }}</code>
        </p>
      </section>
    </div>
    <template #footer>
      <Button :label="t('common.cancel')" text @click="open = false" />
      <Button :label="t('tpl.pick.use')" icon="pi pi-check" :disabled="!chosen" :data-testid="testid ? `${testid}-use` : undefined" @click="choose" />
    </template>
  </Dialog>
</template>
