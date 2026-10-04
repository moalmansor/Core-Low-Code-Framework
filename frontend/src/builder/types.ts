import type { AstNode } from '@/expressions'

/**
 * The draft document of architecture §14.1 as the builder edits it. The shape
 * mirrors backend/app/Modules/Forms/Schemas/form-draft.schema.json; every
 * document the builder sends must validate against that schema.
 */

export type I18nText = Record<string, string>
export type Ast = AstNode

export interface Breakpoints {
  xs?: number
  sm?: number
  md?: number
  lg?: number
  xl?: number
}
export const BREAKPOINTS = ['xs', 'sm', 'md', 'lg', 'xl'] as const
export type Breakpoint = (typeof BREAKPOINTS)[number]

export type JustificationLevel = 'inherit' | 'not_required' | 'optional' | 'mandatory'

export interface FormSettings {
  autosaveDrafts?: boolean
  allowComments?: boolean
  allowAttachments?: boolean
  attachmentRules?: { maxCount?: number; maxSizeKb?: number; types?: string[] }
  conflictResolution?: 'field_by_field'
  afterSubmit?: { redirect?: 'view' | 'list' | 'new' }
  modes?: { create?: boolean; edit?: boolean; view?: boolean; print?: boolean }
  searchFields?: string[]
}

export interface FormHeader {
  uuid: string
  key: string
  kind: 'form' | 'collection'
  bindingMode?: 'managed' | 'bound'
  boundTable?: string | null
  application?: string | null
  icon?: string | null
  dataSharing?: 'shared' | 'isolated'
  numbering?: string | null
  calendar?: string | null
  titleTemplate?: Ast | null
  settings?: FormSettings
  version?: number | null
  state?: string
  i18n?: { name?: I18nText; description?: I18nText; submitButtonLabel?: I18nText }
}

export interface CollectionSettings {
  type: 'key_value' | 'table'
  sharedReference?: boolean
  ownerApplication?: string | null
  valueField?: string | null
  labelField?: string | null
  parentField?: string | null
}

export const GROUP_TYPES = ['section', 'fieldset', 'card', 'tabs', 'tab', 'wizard', 'step', 'row', 'column', 'panel', 'accordion', 'repeater', 'subform'] as const
export type GroupType = (typeof GROUP_TYPES)[number]

export interface RepeaterSettings {
  minRows?: number
  maxRows?: number | null
  defaultRows?: number
  display?: 'table' | 'cards'
  aggregates?: { field: string; fn: 'sum' | 'avg' | 'min' | 'max' | 'count'; label?: I18nText }[]
  rowPermissions?: { add?: string[]; remove?: string[]; reorder?: string[] }
}

export interface GroupValidation {
  minFilled?: number | null
  rules?: { when: Ast; message?: I18nText }[]
  minFilledMessage?: I18nText
}

export interface GroupDef {
  uuid: string
  key: string
  type: GroupType
  parent: string | null
  order: number
  layout?: {
    columns?: Breakpoints
    span?: Breakpoints
    spacing?: 'none' | 'sm' | 'md' | 'lg'
    border?: 'none' | 'subtle' | 'strong'
    background?: 'none' | 'subtle' | 'accent'
    cssClass?: string | null
    icon?: string | null
  }
  collapsible?: boolean
  defaultState?: 'open' | 'closed'
  validation?: GroupValidation | null
  repeater?: RepeaterSettings | null
  wizard?: { validateBeforeNext?: boolean; allowJump?: boolean } | null
  subform?: { form: string; relation?: string | null } | null
  justification?: JustificationLevel
  i18n?: { title?: I18nText; description?: I18nText }
}

export interface StorageDef {
  column?: string | null
  boundColumn?: string | null
  dbType?: null | 'string' | 'text' | 'longtext' | 'int' | 'bigint' | 'decimal' | 'bool' | 'date' | 'time' | 'datetime' | 'json'
  length?: number | null
  precision?: number | null
  scale?: number | null
  nullable?: boolean
  default?: string | number | boolean | null
  index?: 'none' | 'index' | 'unique'
  uniqueScope?: string[]
  multiCurrency?: boolean
}

export interface StaticOption {
  uuid: string
  value: string
  group?: string | null
  parent?: string | null
  color?: string | null
  icon?: string | null
  default?: boolean
  active?: boolean
  order?: number
  condition?: string | null
  i18n?: { label?: I18nText }
}

export type OptionSource = 'static' | 'collection' | 'form' | 'query' | 'users' | 'roles' | 'departments'

export interface OptionsDef {
  source: OptionSource
  static?: StaticOption[]
  collection?: string | null
  valuePath?: string[] | null
  labelPath?: string[] | null
  query?: { from: string; where?: Ast | null; sort?: { path: string[]; dir?: 'asc' | 'desc' }[]; limit?: number } | null
  dependsOn?: string | null
  dependsPath?: string[] | null
  allowCustom?: boolean
  min?: number | null
  max?: number | null
  defaults?: string[]
  searchable?: boolean
  lazy?: boolean
  pageSize?: number
  groupBy?: string[] | null
  preview?: string[][]
}

export interface ValidationDef {
  required?: boolean
  length?: { min?: number | null; max?: number | null }
  number?: { min?: string | null; max?: string | null; step?: string | null }
  pattern?: string | null
  format?: null | 'email' | 'url' | 'phone' | 'national_id' | 'iban' | 'numeric' | 'arabic' | 'english' | 'alphanumeric'
  date?: { min?: Ast | null; max?: Ast | null; disabledWeekdays?: number[]; disabledDates?: string[]; noPast?: boolean; noFuture?: boolean }
  file?: {
    types?: string[]
    mimes?: string[]
    maxSizeKb?: number | null
    maxCount?: number | null
    image?: { minWidth?: number | null; maxWidth?: number | null; minHeight?: number | null; maxHeight?: number | null }
  }
  unique?: { scope?: string[]; includeDeleted?: boolean } | null
  compare?: { op: 'gt' | 'gte' | 'lt' | 'lte' | 'eq' | 'neq' | 'before' | 'after'; field: string }[]
  async?: { type: 'exists_in' | 'not_exists_in'; collection: string; path: string[] } | null
  custom?: { when: Ast; messageKey: string }[]
}

export type DefaultKind = 'static' | 'current_user' | 'current_department' | 'now' | 'today' | 'url_param' | 'field' | 'formula' | 'reference'

export interface BehaviorDef {
  default?: { kind: DefaultKind; value?: unknown; param?: string | null; field?: string | null; expr?: Ast | null; reference?: { path: string[] } | null } | null
  formula?: Ast | null
  transforms?: ('trim' | 'uppercase' | 'lowercase' | 'collapse_spaces')[]
  mask?: string | null
  number?: { thousandSeparator?: boolean; decimals?: number | null; currency?: string | null; symbolPosition?: 'before' | 'after'; unit?: string | null }
  date?: { displayFormat?: string | null; calendar?: 'gregorian' | 'hijri' | 'dual'; firstDayOfWeek?: number; timeStep?: number; hourCycle?: '12h' | '24h'; timezone?: string }
  digits?: 'western' | 'arabic_indic' | 'locale'
  file?: { disk?: 'private' | 'public'; folder?: string; naming?: 'uuid' | 'original_sanitized' }
  autofill?: { from: string[]; to: string; overwrite?: boolean }[]
  autoNumber?: string | null
}

export type PropValue = string | number | boolean | null | (string | number | boolean | null)[] | I18nText

export interface UiDef {
  size?: 'small' | 'medium' | 'large'
  icon?: string | null
  width?: Breakpoints
  labelPosition?: 'top' | 'side' | 'hidden'
  autofocus?: boolean
  tabIndex?: number | null
  autocomplete?: string | null
  spellcheck?: boolean
  cssClass?: string | null
  props?: Record<string, PropValue>
}

export interface TableSettings {
  visible?: boolean
  sortable?: boolean
  filterable?: boolean
  searchable?: boolean
  displayFormat?: string | null
}

export interface ExportSettings {
  exportable?: boolean
  importable?: boolean
  excelColumn?: string | null
  print?: boolean
  pdf?: boolean
}

export type EventStepType = 'set_field' | 'reload_options' | 'run_action' | 'call_webhook' | 'notify'
export interface EventStep {
  type: EventStepType
  target?: string | null
  value?: Ast | null
  action?: string | null
  webhook?: string | null
  severity?: 'info' | 'success' | 'warning' | 'error'
  message?: I18nText
}
export interface EventDef {
  on: 'change' | 'focus' | 'blur'
  when?: Ast | null
  do: EventStep[]
}

export interface FieldI18n {
  label?: I18nText
  placeholder?: I18nText
  help?: I18nText
  tooltip?: I18nText
  description?: I18nText
  prefix?: I18nText
  suffix?: I18nText
  columnLabel?: I18nText
  content?: I18nText
  consentTerms?: I18nText
  messages?: Record<string, I18nText>
}

export interface FieldDef {
  uuid: string
  key: string
  type: string
  group: string | null
  order: number
  storage?: StorageDef
  options?: OptionsDef | null
  validation?: ValidationDef
  behavior?: BehaviorDef
  ui?: UiDef
  table?: TableSettings
  export?: ExportSettings
  events?: EventDef[]
  hook?: { extension: string; hook: string } | null
  flags?: { encrypted?: boolean; blindIndex?: boolean; sensitive?: boolean; personal?: boolean; trackChanges?: boolean }
  justification?: JustificationLevel
  relation?: string | null
  template?: string | null
  i18n?: FieldI18n
}

export type RelationType = 'one_to_one' | 'one_to_many' | 'many_to_one' | 'many_to_many'
export interface RelationDef {
  uuid: string
  key: string
  type: RelationType
  target: string
  kind: 'reference' | 'child_table' | 'subform'
  onDelete: 'restrict' | 'cascade' | 'set_null'
  display?: string | null
  value?: string | null
  inverse?: string | null
}

export type TargetType = 'field' | 'group' | 'option' | 'action' | 'transition'
export interface Target {
  type: TargetType
  uuid: string
}

export const EFFECTS = ['show', 'hide', 'enable', 'disable', 'read_only', 'require', 'set_value', 'clear_value', 'reload_options', 'show_message', 'block_submit', 'trigger_action'] as const
export type EffectKind = (typeof EFFECTS)[number]
export interface Effect {
  effect: EffectKind
  target?: Target
  value?: Ast
  severity?: 'info' | 'success' | 'warning' | 'error'
  message?: I18nText
  action?: string
}

export type OwnerType = 'form' | 'field' | 'group' | 'option'
export interface ConditionDef {
  uuid: string
  owner: { type: OwnerType; uuid: string }
  name?: string | null
  when: Ast
  effects: Effect[]
  else?: Effect[]
  evaluateOn?: 'always' | 'change'
  runtime?: 'client_and_server' | 'server_only'
  order?: number
  active?: boolean
}

export interface DraftDocument {
  $schema?: string
  form: FormHeader
  collection?: CollectionSettings | null
  groups: GroupDef[]
  fields: FieldDef[]
  relations: RelationDef[]
  conditions: ConditionDef[]
}

/** One palette entry from `GET /field-types` (FieldType::toArray). */
export interface FieldTypeInfo {
  key: string
  category: string
  stored: boolean
  storage: string
  value_type: string
  validation: string[]
  options: boolean
  multiple: boolean
  filter: string
  native: boolean
  icon: string
  calculated: boolean
  defaults: { length?: number; precision?: number; scale?: number }
}

export interface GroupTypeInfo {
  key: GroupType
  data: boolean
  parents: (string | null)[] | null
  children: string | null
}

export interface FieldTypeCatalog {
  fields: FieldTypeInfo[]
  groups: GroupTypeInfo[]
}

/** A DraftValidator entry: errors block saving, problems block preview and publishing. */
export interface DraftIssue {
  path: string
  code: string
  message: string
  params?: Record<string, unknown>
}

export interface LocaleOption {
  code: string
  native_name: string
  direction: 'ltr' | 'rtl'
  is_default: boolean
}

/** A field or group of the document, addressed by uuid (uuids are unique across the document). */
export interface ElementRef {
  kind: 'field' | 'group'
  uuid: string
}

/** A copyable piece of a document: elements with their descendants, rules and relations. */
export interface Fragment {
  groups: GroupDef[]
  fields: FieldDef[]
  conditions: ConditionDef[]
  relations: RelationDef[]
}
