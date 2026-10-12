<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import { useSession } from '@/stores/session'
import { newUuid } from '@/views/admin/formconfig/api'
import type { BlockType, Placeholders, TemplateBlock } from '@/views/admin/notifications/api'
import FormScope from '@/views/admin/notifications/FormScope.vue'

/**
 * The blocks of a template design (architecture §19.14), shared by email and
 * document templates: heading, text, button, field list, table of a
 * repeater, divider, spacer and page break — whichever the template kind
 * allows — each shown always or only when a condition holds.
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

const blockTypes = computed(() => props.types.map((v) => ({ value: v, label: t(`tpl.block.${v}`) })))
const newBlockType = ref<BlockType>('text')
const fieldTokens = computed(() => [...props.placeholders.fields, ...props.placeholders.system.filter((s) => ['@record_number', '@status', '@created_at', '@updated_at'].includes(s.token))])
const repeaterOptions = computed(() => props.placeholders.repeaters.map((r) => ({ value: r.key, label: r.label })))
const columnsOf = (group: string | undefined) => props.placeholders.repeaters.find((r) => r.key === group)?.columns.map((c) => ({ value: c.key, label: c.label })) ?? []
const linkOptions = computed(() => [
  { value: 'record', label: t('tpl.link_record') },
  { value: 'url', label: t('tpl.link_url') },
])
const urlPlaceholder = 'https://'

function addBlock(): void {
  const type = newBlockType.value
  const b: TemplateBlock = { uuid: newUuid(), type, condition: null }
  if (type === 'heading' || type === 'text') b.text = {}
  if (type === 'button') Object.assign(b, { label: {}, link: props.form ? 'record' : 'url', url: '' })
  if (type === 'fields') b.fields = []
  if (type === 'table') Object.assign(b, { group: props.placeholders.repeaters[0]?.key ?? '', columns: [] })
  blocks.value.push(b)
}
function move(i: number, d: -1 | 1): void {
  const j = i + d
  if (j < 0 || j >= blocks.value.length) return
  ;[blocks.value[i], blocks.value[j]] = [blocks.value[j]!, blocks.value[i]!]
}
const blockTitle = (b: TemplateBlock, i: number) => `${i + 1}. ${t(`tpl.block.${b.type}`)}`
const blockSummary = (b: TemplateBlock) => (b.condition ? t('tpl.conditional') : undefined)
</script>

<template>
  <EmptyState v-if="!blocks.length" icon="pi pi-align-left" :title="t('tpl.blocks_empty_title')" :description="emptyText ?? t('tpl.blocks_empty_text')" />
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
        <I18nInput v-if="b.type === 'heading'" v-model="b.text" class="w-field-lg" :label="t('tpl.heading_text')" :maxlength="255" :invalid="!!err(`${i}.text.${dl}`)" />
        <I18nInput v-if="b.type === 'text'" v-model="b.text" class="w-field-lg" multiline :rows="5" :label="t('tpl.text')" :maxlength="5000" :invalid="!!err(`${i}.text.${dl}`)" />
        <p v-if="b.type === 'text'" class="m-0 text-xs text-muted-color">{{ t('tpl.text_hint') }}</p>
        <template v-if="b.type === 'button'">
          <I18nInput v-model="b.label" class="w-field-md" :label="t('tpl.button_label')" :maxlength="120" />
          <ConfigField :label="t('tpl.button_link')" :for="`tpl-link-${i}`" width="sm">
            <Select v-model="b.link" :input-id="`tpl-link-${i}`" :options="linkOptions" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <ConfigField v-if="b.link === 'url'" :label="t('tpl.url')" :for="`tpl-url-${i}`" width="lg" :error="err(`${i}.url`)">
            <InputText :id="`tpl-url-${i}`" v-model="b.url as never" dir="ltr" size="small" :placeholder="urlPlaceholder" />
          </ConfigField>
        </template>
        <ConfigField v-if="b.type === 'fields'" :label="t('tpl.fields')" :for="`tpl-fields-${i}`" width="lg" :hint="t('tpl.fields_hint')">
          <MultiSelect v-model="b.fields" :input-id="`tpl-fields-${i}`" :options="fieldTokens" option-label="label" option-value="token" filter display="chip" size="small" />
        </ConfigField>
        <template v-if="b.type === 'table'">
          <ConfigField :label="t('tpl.table')" :for="`tpl-group-${i}`" width="md" :error="err(`${i}.group`)">
            <Select v-model="b.group" :input-id="`tpl-group-${i}`" :options="repeaterOptions" option-label="label" option-value="value" size="small" @update:model-value="b.columns = []" />
          </ConfigField>
          <ConfigField :label="t('tpl.columns')" :for="`tpl-cols-${i}`" width="lg">
            <MultiSelect v-model="b.columns" :input-id="`tpl-cols-${i}`" :options="columnsOf(b.group)" option-label="label" option-value="value" display="chip" size="small" />
          </ConfigField>
        </template>
        <p v-if="b.type === 'page_break'" class="m-0 text-xs text-muted-color">{{ t('tpl.page_break_hint') }}</p>
        <div v-if="form && scope" class="flex flex-col gap-1">
          <h4 class="m-0 text-sm font-semibold">{{ t('tpl.show_when') }}</h4>
          <ExpressionInput v-model="b.condition" :scope="scope" expected="boolean" allow-empty :label="t('tpl.show_when')" />
          <p class="m-0 text-xs text-muted-color">{{ t('tpl.show_when_hint') }}</p>
        </div>
      </div>
    </ConfigItem>
  </FormScope>
  <div class="flex flex-wrap items-center gap-2">
    <Select v-model="newBlockType" :options="blockTypes" option-label="label" option-value="value" size="small" class="w-field-sm" :aria-label="t('tpl.block_type')" />
    <Button icon="pi pi-plus" :label="t('tpl.add_block')" size="small" outlined data-testid="tpl-add-block" @click="addBlock" />
  </div>
</template>
