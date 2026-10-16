<script setup lang="ts">
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { computed, nextTick, ref, watch, type ComponentPublicInstance } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSession } from '@/stores/session'
import { maybeBuilder } from './useBuilder'
import type { I18nText } from './types'

/**
 * Translatable text, stored per locale (never as fixed Arabic/English
 * columns). The interface language comes first; the other enabled languages
 * fold under it, showing whether each is set (design system: properties
 * panel). Empty entries are removed.
 */
const props = withDefaults(defineProps<{ label: string; multiline?: boolean; rows?: number; maxlength?: number; id?: string; invalid?: boolean }>(), { rows: 3, maxlength: 1000, id: undefined })
const model = defineModel<I18nText | undefined>()
// Outside a form (an email template, say) the enabled languages come from the session.
const builder = maybeBuilder()
const session = useSession()
const locales = computed(() => (builder ? builder.locales : (session.boot?.locales ?? []).map((l) => ({ code: l.code, native_name: l.native_name, direction: l.direction, is_default: l.is_default }))))
const { locale, t } = useI18n()
const uid = props.id ?? `i18n-${Math.random().toString(36).slice(2, 9)}`
const expanded = ref(false)
// A refused save opens every language, so a missing default-language text is in view.
watch(
  () => props.invalid,
  (v) => {
    if (v) expanded.value = true
  },
)

const primary = computed(() => locales.value.find((l) => l.code === locale.value) ?? locales.value[0])
const others = computed(() => locales.value.filter((l) => l.code !== primary.value?.code))
const setCount = computed(() => others.value.filter((l) => !!model.value?.[l.code]).length)

// Where the caret was in each language's input, so a chosen placeholder lands there (insert()).
const elements = new Map<string, HTMLInputElement | HTMLTextAreaElement>()
const caret = ref<{ code: string; start: number; end: number } | null>(null)
function bind(code: string, el: Element | ComponentPublicInstance | null): void {
  const node = (el && '$el' in el ? el.$el : el) as HTMLInputElement | HTMLTextAreaElement | null
  if (node) elements.set(code, node)
  else elements.delete(code)
}
function remember(code: string, e: Event): void {
  const el = e.target as HTMLInputElement | HTMLTextAreaElement
  caret.value = { code, start: el.selectionStart ?? el.value.length, end: el.selectionEnd ?? el.value.length }
}
/** Inserts text at the caret of the language last edited (else at the end of the interface language's text). */
function insert(text: string): void {
  const code = caret.value?.code ?? primary.value?.code
  if (!code) return
  const current = model.value?.[code] ?? ''
  const start = caret.value?.code === code ? Math.min(caret.value.start, current.length) : current.length
  const end = caret.value?.code === code ? Math.min(caret.value.end, current.length) : current.length
  update(code, current.slice(0, start) + text + current.slice(end))
  const at = start + text.length
  caret.value = { code, start: at, end: at }
  void nextTick(() => {
    const el = elements.get(code)
    el?.focus()
    el?.setSelectionRange(at, at)
  })
}
defineExpose({ insert })

function update(code: string, value: string | undefined): void {
  const next: I18nText = { ...(model.value ?? {}) }
  if (value) next[code] = value
  else delete next[code]
  model.value = next
}
</script>

<template>
  <fieldset class="flex flex-col gap-1.5 min-w-0">
    <legend class="text-sm font-medium mb-1 w-full flex flex-wrap items-center justify-between gap-2">
      <span>{{ label }}</span>
      <slot name="tools" />
    </legend>
    <template v-for="l in expanded ? locales : primary ? [primary] : []" :key="l.code">
      <div class="flex items-start gap-2">
        <label v-if="expanded || others.length" :for="`${uid}-${l.code}`" class="text-xs text-muted-color w-8 shrink-0 pt-2 uppercase ltr-value">{{ l.code }}</label>
        <Textarea
          v-if="multiline"
          :id="`${uid}-${l.code}`"
          :ref="(el) => bind(l.code, el)"
          :model-value="model?.[l.code] ?? ''"
          :dir="l.direction"
          :rows="rows"
          :maxlength="maxlength"
          :invalid="invalid"
          auto-resize
          class="w-full"
          :aria-label="`${label} (${l.native_name})`"
          @update:model-value="(v: string | undefined) => update(l.code, v)"
          @keyup="(e: Event) => remember(l.code, e)"
          @click="(e: Event) => remember(l.code, e)"
          @select="(e: Event) => remember(l.code, e)"
          @blur="(e: Event) => remember(l.code, e)"
        />
        <InputText
          v-else
          :id="`${uid}-${l.code}`"
          :ref="(el) => bind(l.code, el)"
          :model-value="model?.[l.code] ?? ''"
          :dir="l.direction"
          :maxlength="maxlength"
          :invalid="invalid"
          size="small"
          class="w-full"
          :aria-label="`${label} (${l.native_name})`"
          @update:model-value="(v: string | undefined) => update(l.code, v)"
          @keyup="(e: Event) => remember(l.code, e)"
          @click="(e: Event) => remember(l.code, e)"
          @select="(e: Event) => remember(l.code, e)"
          @blur="(e: Event) => remember(l.code, e)"
        />
      </div>
    </template>
    <button v-if="others.length" type="button" class="self-start text-xs text-muted-color hover:text-color flex items-center gap-1" :aria-expanded="expanded" @click="expanded = !expanded">
      <i :class="expanded ? 'pi pi-chevron-up' : 'pi pi-language'" class="text-[0.6875rem]" aria-hidden="true" />
      <template v-if="expanded">{{ t('builder.i18n.hide_other') }}</template>
      <template v-else>{{ t('builder.i18n.other', { names: others.map((l) => l.native_name).join(', '), set: setCount, total: others.length }) }}</template>
    </button>
  </fieldset>
</template>
