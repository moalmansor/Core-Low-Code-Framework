import type { RawNode } from '@/expressions'

/**
 * The definition the browser receives (backend ClientDefinition::build):
 * the published form minus server-only data and minus every element the
 * reader may not see. Shapes follow the form-draft JSON schema
 * (backend/app/Modules/Forms/Schemas/form-draft.schema.json).
 */

export type I18nText = Record<string, string>
export type Breakpoints = Partial<Record<'xs' | 'sm' | 'md' | 'lg' | 'xl', number>>
export type AccessLevel = 'hidden' | 'read_only' | 'editable' | 'required'
export type FormMode = 'create' | 'edit' | 'view' | 'print'

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

export interface FieldOptions {
  source: 'static' | 'collection' | 'form' | 'query' | 'users' | 'roles' | 'departments'
  static?: StaticOption[]
  collection?: string | null
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

export interface FieldValidation {
  required?: boolean
  length?: { min?: number | null; max?: number | null }
  number?: { min?: string | null; max?: string | null; step?: string | null }
  pattern?: string | null
  format?: 'email' | 'url' | 'phone' | 'national_id' | 'iban' | 'numeric' | 'arabic' | 'english' | 'alphanumeric' | null
  date?: { min?: RawNode | null; max?: RawNode | null; disabledWeekdays?: number[]; disabledDates?: string[]; noPast?: boolean; noFuture?: boolean }
  file?: { types?: string[]; mimes?: string[]; maxSizeKb?: number | null; maxCount?: number | null; image?: Record<string, number | null> }
  unique?: unknown
  compare?: { op: 'gt' | 'gte' | 'lt' | 'lte' | 'eq' | 'neq' | 'before' | 'after'; field: string }[]
  async?: { type: 'exists_in' | 'not_exists_in'; collection: string; path: string[] } | null
  custom?: { when: RawNode; messageKey: string }[]
}

export interface FieldDefault {
  kind: 'static' | 'current_user' | 'current_department' | 'now' | 'today' | 'url_param' | 'field' | 'formula' | 'reference'
  value?: unknown
  param?: string | null
  field?: string | null
  expr?: RawNode | null
  reference?: { path: string[] } | null
}

export interface FieldBehavior {
  default?: FieldDefault | null
  formula?: RawNode | null
  transforms?: ('trim' | 'uppercase' | 'lowercase' | 'collapse_spaces')[]
  mask?: string | null
  number?: { thousandSeparator?: boolean; decimals?: number | null; currency?: string | null; symbolPosition?: 'before' | 'after'; unit?: string | null }
  date?: { displayFormat?: string | null; calendar?: 'gregorian' | 'hijri' | 'dual'; firstDayOfWeek?: number; timeStep?: number; hourCycle?: '12h' | '24h'; timezone?: string }
  digits?: 'western' | 'arabic_indic' | 'locale'
  autofill?: { from: string[]; to: string; overwrite?: boolean }[]
}

export type UiPropValue = string | number | boolean | null | (string | number | boolean | null)[] | I18nText

export interface FieldUi {
  size?: 'small' | 'medium' | 'large'
  icon?: string | null
  width?: Breakpoints
  labelPosition?: 'top' | 'side' | 'hidden'
  autofocus?: boolean
  tabIndex?: number | null
  autocomplete?: string | null
  spellcheck?: boolean
  cssClass?: string | null
  props?: Record<string, UiPropValue>
}

export interface FieldEvent {
  on: 'change' | 'focus' | 'blur'
  when?: RawNode | null
  do: {
    type: 'set_field' | 'reload_options' | 'run_action' | 'call_webhook' | 'notify'
    target?: string | null
    value?: RawNode | null
    severity?: 'info' | 'success' | 'warning' | 'error'
    message?: I18nText
  }[]
}

export interface ClientField {
  uuid: string
  key: string
  type: string
  group: string | null
  order: number
  storage: { length?: number | null; precision?: number | null; scale?: number | null; multiCurrency?: boolean }
  options: FieldOptions | null
  validation: FieldValidation
  behavior: FieldBehavior
  ui: FieldUi
  table: { visible?: boolean; sortable?: boolean; filterable?: boolean; searchable?: boolean; displayFormat?: string | null }
  events: FieldEvent[]
  flags: { sensitive: boolean }
  relation: string | null
  i18n: {
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
  access: AccessLevel
  serverComputed: boolean
}

export interface ClientGroup {
  uuid: string
  key: string
  type: 'section' | 'fieldset' | 'card' | 'tabs' | 'tab' | 'wizard' | 'step' | 'row' | 'column' | 'panel' | 'accordion' | 'repeater' | 'subform'
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
  validation?: { minFilled?: number | null; rules?: { when: RawNode; message?: I18nText }[]; minFilledMessage?: I18nText } | null
  repeater?: {
    minRows?: number
    maxRows?: number | null
    defaultRows?: number
    display?: 'table' | 'cards'
    aggregates?: { field: string; fn: 'sum' | 'avg' | 'min' | 'max' | 'count'; label?: I18nText }[]
    rowPermissions?: { add?: string[]; remove?: string[]; reorder?: string[] }
  } | null
  wizard?: { validateBeforeNext?: boolean; allowJump?: boolean } | null
  subform?: { form: string; relation?: string | null } | null
  i18n?: { title?: I18nText; description?: I18nText }
  access: AccessLevel
}

export interface EffectTarget {
  type: 'field' | 'group' | 'option' | 'action' | 'transition'
  uuid: string
}

export interface Effect {
  effect: 'show' | 'hide' | 'enable' | 'disable' | 'read_only' | 'require' | 'set_value' | 'clear_value' | 'reload_options' | 'show_message' | 'block_submit' | 'trigger_action'
  target?: EffectTarget
  value?: RawNode
  severity?: 'info' | 'success' | 'warning' | 'error'
  message?: I18nText
  action?: string
}

export interface Condition {
  uuid: string
  owner: { type: 'form' | 'field' | 'group' | 'option'; uuid: string }
  name?: string | null
  when: RawNode
  effects: Effect[]
  else?: Effect[]
  evaluateOn?: 'always' | 'change'
  runtime?: 'client_and_server' | 'server_only'
  order?: number
  active?: boolean
}

export interface ClientRelation {
  uuid: string
  key: string
  type: 'one_to_one' | 'one_to_many' | 'many_to_one' | 'many_to_many'
  target: string
  kind: 'reference' | 'child_table' | 'subform'
  display?: string | null
  value?: string | null
}

export interface ClientDefinition {
  form: {
    uuid: string
    key: string
    kind: 'form' | 'collection'
    version: number | null
    icon: string | null
    settings: {
      allowComments?: boolean
      allowAttachments?: boolean
      afterSubmit?: { redirect?: 'view' | 'list' | 'new' }
      modes?: Partial<Record<FormMode, boolean>>
      [key: string]: unknown
    }
    titleTemplate: RawNode | null
    i18n: { name?: I18nText; description?: I18nText; submitButtonLabel?: I18nText }
  }
  mode: string
  access: { form: string; modes: Partial<Record<'view' | 'create' | 'edit' | 'delete' | 'print', boolean>> }
  groups: ClientGroup[]
  fields: ClientField[]
  relations: ClientRelation[]
  conditions: Condition[]
  /** Present on GET /r/{form}/definition. */
  name?: string
  names?: I18nText
}

/** Values by field key; repeater keys hold row arrays. */
export type Values = Record<string, unknown>
export type Row = Record<string, unknown>

/** Metadata of a stored file as returned by uploads and record payloads. */
export interface FileMeta {
  uuid: string
  name: string
  size: number
  mime: string
  width?: number | null
  height?: number | null
}

/** Display titles of referenced records: field key → record uuid → title. */
export type References = Record<string, Record<string, string>>

/** A record as returned by GET /r/{form}/{record}. */
export interface RecordPayload {
  uuid: string
  row_version: number
  title: string | null
  values: Values
  references: References
  files: Record<string, FileMeta>
  system: {
    record_number: string | null
    version: number | null
    created_at: string | null
    created_by: string | null
    updated_at: string | null
    updated_by: string | null
    deleted_at: string | null
  }
  permissions?: { edit: boolean; delete: boolean; restore: boolean; print: boolean; view_log: boolean }
  changed?: string[]
}
