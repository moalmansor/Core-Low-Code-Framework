<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ChoiceGallery from '@/components/config/ChoiceGallery.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { humanize } from '@/runtime/i18nText'
import { useSession } from '@/stores/session'
import { fieldTreeApi, linkedFromApi, newUuid, type LinkedFrom, type PathNode } from '@/views/admin/formconfig/api'
import type { BlockType, Placeholders, TemplateBlock } from '@/views/admin/notifications/api'
import FormScope from '@/views/admin/notifications/FormScope.vue'
import FieldEntryList from './FieldEntryList.vue'
import FormatPicker from './FormatPicker.vue'
import { formatSpec, parseFormat, type Format } from './formats'
import TemplateText from './TemplateText.vue'

/**
 * The blocks of a template design (architecture §19.14), shared by email and
 * document templates: heading, text, button, field list, table of a
 * repeater, table of linked records, signature lines, QR code or barcode,
 * divider, spacer and page break — whichever the template kind allows — each
 * shown always or only when a condition holds. Values are chosen from the
 * form's fields and the system values, with how each is shown.
 */
const props = defineProps<{
  types: BlockType[]
  form: string | null
  placeholders: Placeholders
  errors: Record<string, string>
  /** Prefix of the server's error paths for the blocks. */
  path?: string
  /** What the empty state suggests adding. */
  emptyText?: string
}>()
const blocks = defineModel<TemplateBlock[]>({ required: true })
const { t } = useI18n()
const session = useSession()
const dl = computed(() => session.boot?.default_locale ?? 'en')
const prefix = computed(() => props.path ?? 'design.blocks')
const err = (p: string) => props.errors[`${prefix.value}.${p}`]
const system = computed(() => props.placeholders.system)

// What can be added, described (design system §5.7: pickers that say what each thing does).
const ICONS: Record<BlockType, string> = {
  heading: 'pi-bookmark',
  text: 'pi-align-left',
  button: 'pi-external-link',
  fields: 'pi-list',
  table: 'pi-table',
  related: 'pi-sitemap',
  signature: 'pi-pen-to-square',
  code: 'pi-qrcode',
  divider: 'pi-minus',
  spacer: 'pi-arrows-v',
  page_break: 'pi-file',
}
const GROUPS: { key: string; types: BlockType[] }[] = [
  { key: 'text', types: ['heading', 'text', 'button'] },
  { key: 'values', types: ['fields', 'table', 'related'] },
  { key: 'document', types: ['signature', 'code'] },
  { key: 'layout', types: ['divider', 'spacer', 'page_break'] },
]
const adding = ref(false)
const gallery = computed(() =>
  GROUPS.map((g) => ({
    title: t(`tpl.block_group.${g.key}`),
    items: g.types.filter((v) => props.types.includes(v)).map((v) => ({ value: v, icon: ICONS[v], title: t(`tpl.block.${v}`), description: t(`tpl.block_desc.${v}`) })),
  })).filter((g) => g.items.length),
)
function addBlock(type: BlockType): void {
  adding.value = false
  const b: TemplateBlock = { uuid: newUuid(), type, condition: null }
  if (type === 'heading' || type === 'text') b.text = {}
  if (type === 'button') Object.assign(b, { label: {}, link: props.form ? 'record' : 'url', url: '' })
  if (type === 'fields') b.fields = []
  if (type === 'table') Object.assign(b, { group: props.placeholders.repeaters[0]?.key ?? '', columns: [], formats: {} })
  if (type === 'related') Object.assign(b, { source: linked.value[0]?.form ?? null, via: linked.value[0]?.via[0]?.key ?? '', columns: [], limit: 50 })
  if (type === 'signature') Object.assign(b, { signatories: [{ label: { [dl.value]: t('tpl.signature_default') }, name: {} }], date: true })
  if (type === 'code') Object.assign(b, { kind: 'qr', content: 'link', size: 'medium', caption: true })
  blocks.value.push(b)
}
function move(i: number, d: -1 | 1): void {
  const j = i + d
  if (j < 0 || j >= blocks.value.length) return
  ;[blocks.value[i], blocks.value[j]] = [blocks.value[j]!, blocks.value[i]!]
}
const blockTitle = (b: TemplateBlock, i: number) => `${i + 1}. ${t(`tpl.block.${b.type}`)}`
const blockSummary = (b: TemplateBlock) => (b.condition ? t('tpl.conditional') : undefined)

// Repeater tables.
const repeaterOptions = computed(() => props.placeholders.repeaters.map((r) => ({ value: r.key, label: r.label })))
const columnsOf = (group: string | undefined) => props.placeholders.repeaters.find((r) => r.key === group)?.columns.map((c) => ({ value: c.key, label: c.label })) ?? []
const columnLabel = (group: string | undefined, key: string) => columnsOf(group).find((c) => c.value === key)?.label ?? humanize(key)
function setColumnFormat(b: TemplateBlock, key: string, f: Format): void {
  const formats = { ...(b.formats ?? {}) }
  const spec = formatSpec(f)
  if (spec) formats[key] = spec
  else delete formats[key]
  b.formats = formats
}
const formatOpen = ref<string | null>(null)

// Tables of linked records: the forms that link here, and each one's fields.
const linked = ref<LinkedFrom[]>([])
const trees = ref<Record<string, PathNode[]>>({})
watch(
  () => props.form,
  async (f) => {
    linked.value = f && props.types.includes('related') ? await linkedFromApi.load(f).catch(() => []) : []
  },
  { immediate: true },
)
watch(
  () => blocks.value.filter((b) => b.type === 'related').map((b) => b.source),
  (sources) => {
    for (const s of sources) {
      if (s && !trees.value[s]) void fieldTreeApi.load(s).then((tree) => (trees.value = { ...trees.value, [s]: tree }))
    }
  },
  { immediate: true, deep: true },
)
const sourceOptions = computed(() => linked.value.map((l) => ({ value: l.form, label: l.name })))
const viaOptions = (source: string | null | undefined) => (linked.value.find((l) => l.form === source)?.via ?? []).map((v) => ({ value: v.key, label: v.label }))
function setSource(b: TemplateBlock, source: string): void {
  b.source = source
  b.via = linked.value.find((l) => l.form === source)?.via[0]?.key ?? ''
  b.columns = []
}

// Links, codes and sizes.
const linkOptions = computed(() => [
  { value: 'record', label: t('tpl.link_record') },
  { value: 'url', label: t('tpl.link_url') },
])
const codeKinds = computed(() => [
  { value: 'qr', label: t('tpl.code.qr') },
  { value: 'barcode', label: t('tpl.code.barcode') },
])
const codeContents = computed(() => [
  { value: 'link', label: t('tpl.code.link') },
  { value: 'number', label: t('tpl.code.number') },
])
const codeSizes = computed(() => (['small', 'medium', 'large'] as const).map((v) => ({ value: v, label: t(`tpl.code.size_${v}`) })))
const urlPlaceholder = 'https://'
</script>

<template>
  <EmptyState v-if="!blocks.length" icon="pi pi-align-left" :title="t('tpl.blocks_empty_title')" :description="emptyText ?? t('tpl.blocks_empty_text')">
    <Button icon="pi pi-plus" :label="t('tpl.add_block')" size="small" data-testid="tpl-add-first-block" @click="adding = true" />
  </EmptyState>
  <FormScope :key="form ?? 'none'" v-slot="{ scope }" :form="form">
    <ConfigItem
      v-for="(b, i) in blocks"
      :key="b.uuid"
      :title="blockTitle(b, i)"
      :subtitle="blockSummary(b)"
      :index="i"
      :count="blocks.length"
      movable
      :invalid="Object.keys(errors).some((k) => k.startsWith(`${prefix}.${i}.`))"
      :testid="`tpl-block-${i}`"
      @move="(d: -1 | 1) => move(i, d)"
      @remove="blocks.splice(i, 1)"
    >
      <div class="flex flex-col gap-4">
        <TemplateText
          v-if="b.type === 'heading'"
          v-model="b.text"
          class="w-field-lg"
          :label="t('tpl.heading_text')"
          :form="form"
          :system="system"
          :maxlength="255"
          :invalid="!!err(`${i}.text.${dl}`)"
          :testid="`tpl-heading-${i}`"
        />
        <template v-if="b.type === 'text'">
          <TemplateText
            v-model="b.text"
            class="w-field-lg"
            multiline
            :rows="5"
            :label="t('tpl.text')"
            :form="form"
            :system="system"
            :maxlength="5000"
            :invalid="!!err(`${i}.text.${dl}`)"
            :testid="`tpl-text-${i}`"
          />
          <p class="m-0 text-xs text-muted-color">{{ t('tpl.text_hint') }}</p>
        </template>
        <template v-if="b.type === 'button'">
          <I18nInput v-model="b.label" class="w-field-md" :label="t('tpl.button_label')" :maxlength="120" />
          <ConfigField :label="t('tpl.button_link')" :for="`tpl-link-${i}`" width="sm">
            <Select v-model="b.link" :input-id="`tpl-link-${i}`" :options="linkOptions" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <ConfigField v-if="b.link === 'url'" :label="t('tpl.url')" :for="`tpl-url-${i}`" width="lg" :error="err(`${i}.url`)">
            <InputText :id="`tpl-url-${i}`" v-model="b.url as never" dir="ltr" size="small" :placeholder="urlPlaceholder" />
          </ConfigField>
        </template>
        <div v-if="b.type === 'fields'" class="w-field-lg flex flex-col gap-1">
          <h4 class="m-0 text-sm font-semibold">{{ t('tpl.fields') }}</h4>
          <p class="m-0 text-xs text-muted-color">{{ t('tpl.fields_hint') }}</p>
          <FieldEntryList v-model="b.fields as string[]" :form="form" :system="system" :errors="errors" :path="`${prefix}.${i}.fields`" :testid="`tpl-fields-${i}`" />
        </div>
        <template v-if="b.type === 'table'">
          <ConfigField :label="t('tpl.table')" :for="`tpl-group-${i}`" width="md" :error="err(`${i}.group`)">
            <Select
              v-model="b.group"
              :input-id="`tpl-group-${i}`"
              :options="repeaterOptions"
              option-label="label"
              option-value="value"
              size="small"
              @update:model-value="((b.columns = []), (b.formats = {}))"
            />
          </ConfigField>
          <ConfigField :label="t('tpl.columns')" :for="`tpl-cols-${i}`" width="lg">
            <MultiSelect v-model="b.columns" :input-id="`tpl-cols-${i}`" :options="columnsOf(b.group)" option-label="label" option-value="value" display="chip" size="small" />
          </ConfigField>
          <div v-if="b.columns?.length" class="flex flex-col gap-2">
            <h4 class="m-0 text-sm font-semibold">{{ t('tpl.format.columns') }}</h4>
            <div v-for="c in b.columns" :key="c" class="flex flex-col gap-1">
              <button type="button" class="self-start text-sm text-primary" :aria-expanded="formatOpen === `${i}.${c}`" @click="formatOpen = formatOpen === `${i}.${c}` ? null : `${i}.${c}`">
                {{ columnLabel(b.group, c) }}<span v-if="b.formats?.[c]" class="text-muted-color ms-1">· {{ t('tpl.format.set') }}</span>
              </button>
              <FormatPicker
                v-if="formatOpen === `${i}.${c}`"
                :model-value="parseFormat(b.formats?.[c] ?? '') ?? {}"
                kind="number"
                :id-prefix="`tpl-colfmt-${i}-${c}`"
                @update:model-value="(f: Format) => setColumnFormat(b, c, f)"
              />
            </div>
          </div>
        </template>
        <template v-if="b.type === 'related'">
          <p v-if="!linked.length" class="m-0 text-sm text-muted-color">{{ t('tpl.related_none') }}</p>
          <template v-else>
            <div class="cfg-row">
              <ConfigField :label="t('tpl.related_source')" :for="`tpl-src-${i}`" width="md" :hint="t('tpl.related_source_hint')">
                <Select
                  :model-value="b.source"
                  :input-id="`tpl-src-${i}`"
                  :options="sourceOptions"
                  option-label="label"
                  option-value="value"
                  size="small"
                  :data-testid="`tpl-src-${i}`"
                  @update:model-value="(v: string) => setSource(b, v)"
                />
              </ConfigField>
              <ConfigField v-if="viaOptions(b.source).length > 1" :label="t('tpl.related_via')" :for="`tpl-via-${i}`" width="sm" :error="err(`${i}.via`)">
                <Select v-model="b.via" :input-id="`tpl-via-${i}`" :options="viaOptions(b.source)" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <ConfigField :label="t('tpl.related_limit')" :for="`tpl-lim-${i}`" width="xs">
                <InputNumber v-model="b.limit" :input-id="`tpl-lim-${i}`" :min="1" :max="200" size="small" fluid />
              </ConfigField>
            </div>
            <div v-if="b.source" class="w-field-lg flex flex-col gap-1">
              <h4 class="m-0 text-sm font-semibold">{{ t('tpl.columns') }}</h4>
              <FieldEntryList
                v-model="b.columns as string[]"
                :tree="trees[b.source] ?? []"
                :errors="errors"
                :path="`${prefix}.${i}.columns`"
                :testid="`tpl-relcols-${i}`"
                :add-label="t('tpl.add_column')"
              />
            </div>
          </template>
        </template>
        <template v-if="b.type === 'signature'">
          <div v-for="(p, j) in b.signatories" :key="j" class="tpl-signer" :data-testid="`tpl-signer-${i}-${j}`">
            <div class="flex items-start gap-2">
              <div class="flex-1 min-w-0 flex flex-col gap-3">
                <I18nInput v-model="p.label" class="w-field-md" :label="t('tpl.signature_label')" :maxlength="120" :invalid="!!err(`${i}.signatories.${j}.label.${dl}`)" />
                <TemplateText v-model="p.name" class="w-field-md" :label="t('tpl.signature_name')" :form="form" :system="system" :maxlength="255" />
              </div>
              <Button
                v-if="(b.signatories?.length ?? 0) > 1"
                icon="pi pi-times"
                text
                rounded
                size="small"
                severity="secondary"
                :aria-label="t('tpl.signature_remove')"
                @click="b.signatories!.splice(j, 1)"
              />
            </div>
          </div>
          <Button
            v-if="(b.signatories?.length ?? 0) < 4"
            icon="pi pi-plus"
            :label="t('tpl.signature_add')"
            size="small"
            text
            class="self-start"
            @click="b.signatories!.push({ label: {}, name: {} })"
          />
          <p v-if="err(`${i}.signatories`)" class="m-0 text-xs text-danger">{{ err(`${i}.signatories`) }}</p>
          <SettingSwitch :id="`tpl-sigdate-${i}`" v-model="b.date as boolean" :label="t('tpl.signature_date')" :description="t('tpl.signature_date_desc')" />
        </template>
        <template v-if="b.type === 'code'">
          <ConfigField :label="t('tpl.code.kind')" :for="`tpl-codekind-${i}`" width="md">
            <SelectButton v-model="b.kind" :options="codeKinds" option-label="label" option-value="value" :allow-empty="false" size="small" />
          </ConfigField>
          <div class="cfg-row">
            <ConfigField :label="t('tpl.code.content')" :for="`tpl-codecontent-${i}`" width="sm" :hint="t(`tpl.code.content_hint_${b.content}`)">
              <Select v-model="b.content" :input-id="`tpl-codecontent-${i}`" :options="codeContents" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('tpl.code.size')" :for="`tpl-codesize-${i}`" width="xs">
              <Select v-model="b.size" :input-id="`tpl-codesize-${i}`" :options="codeSizes" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <SettingSwitch :id="`tpl-codecap-${i}`" v-model="b.caption as boolean" :label="t('tpl.code.caption')" :description="t('tpl.code.caption_desc')" />
        </template>
        <p v-if="b.type === 'page_break'" class="m-0 text-xs text-muted-color">{{ t('tpl.page_break_hint') }}</p>
        <div v-if="form && scope" class="flex flex-col gap-1">
          <ExpressionInput v-model="b.condition" :scope="scope" expected="boolean" allow-empty :label="t('tpl.show_when')" />
          <p class="m-0 text-xs text-muted-color">{{ t('tpl.show_when_hint') }}</p>
        </div>
      </div>
    </ConfigItem>
  </FormScope>
  <Button v-if="blocks.length" icon="pi pi-plus" :label="t('tpl.add_block')" size="small" outlined class="self-start" data-testid="tpl-add-block" @click="adding = true" />
  <Dialog v-model:visible="adding" modal :header="t('tpl.add_block_title')" :style="{ width: '52rem', maxWidth: 'calc(100vw - 2rem)' }">
    <ChoiceGallery :groups="gallery" testid="tpl-block-choice" @choose="addBlock" />
  </Dialog>
</template>

<style scoped>
.tpl-signer {
  padding: 0.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
}
</style>
