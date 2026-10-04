/**
 * Client copy of the field type registry
 * (backend/app/Modules/Forms/FieldTypes/FieldTypeRegistry.php): how each type
 * is stored, its expression type, whether it takes options, how lists filter
 * it, and whether it is computed. fieldTypes.spec.ts keeps both in step.
 */

export type Storage =
  | 'none'
  | 'string'
  | 'text'
  | 'longtext'
  | 'number'
  | 'int'
  | 'decimal'
  | 'bool'
  | 'date'
  | 'time'
  | 'datetime'
  | 'duration'
  | 'json'
  | 'choice'
  | 'multi_choice'
  | 'lookup'
  | 'multi_lookup'
  | 'user'
  | 'role'
  | 'department'
  | 'file'
  | 'files'
  | 'range_date'
  | 'range_time'
  | 'range_datetime'
  | 'map'
  | 'phone'
  | 'currency'
  | 'consent'
  | 'auto_number'
  | 'formula'

export interface FieldTypeInfo {
  category: 'text' | 'number' | 'datetime' | 'choice' | 'reference' | 'file' | 'special' | 'display' | 'action'
  storage: Storage
  valueType: string
  options: boolean
  multiple: boolean
  filter: 'none' | 'text' | 'number' | 'date' | 'time' | 'datetime' | 'boolean' | 'choice' | 'lookup'
  calculated: boolean
}

export const FIELD_TYPES: Record<string, FieldTypeInfo> = {
  text: { category: 'text', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  password: { category: 'text', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'none', calculated: false },
  email: { category: 'text', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  tel: { category: 'text', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  url: { category: 'text', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  search: { category: 'text', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  number: { category: 'number', storage: 'decimal', valueType: 'number', options: false, multiple: false, filter: 'number', calculated: false },
  range: { category: 'number', storage: 'decimal', valueType: 'number', options: false, multiple: false, filter: 'number', calculated: false },
  date: { category: 'datetime', storage: 'date', valueType: 'date', options: false, multiple: false, filter: 'date', calculated: false },
  time: { category: 'datetime', storage: 'time', valueType: 'time', options: false, multiple: false, filter: 'time', calculated: false },
  datetime_local: { category: 'datetime', storage: 'datetime', valueType: 'datetime', options: false, multiple: false, filter: 'datetime', calculated: false },
  month: { category: 'datetime', storage: 'date', valueType: 'date', options: false, multiple: false, filter: 'date', calculated: false },
  week: { category: 'datetime', storage: 'date', valueType: 'date', options: false, multiple: false, filter: 'date', calculated: false },
  checkbox: { category: 'choice', storage: 'bool', valueType: 'boolean', options: false, multiple: false, filter: 'boolean', calculated: false },
  radio: { category: 'choice', storage: 'choice', valueType: 'text', options: true, multiple: false, filter: 'choice', calculated: false },
  color: { category: 'text', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  file: { category: 'file', storage: 'file', valueType: 'list', options: false, multiple: false, filter: 'none', calculated: false },
  hidden: { category: 'text', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  image_button: { category: 'action', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  button: { category: 'action', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  submit: { category: 'action', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  reset: { category: 'action', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  textarea: { category: 'text', storage: 'text', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  select: { category: 'choice', storage: 'choice', valueType: 'text', options: true, multiple: false, filter: 'choice', calculated: false },
  select_multiple: { category: 'choice', storage: 'multi_choice', valueType: 'list', options: true, multiple: true, filter: 'choice', calculated: false },
  select_grouped: { category: 'choice', storage: 'choice', valueType: 'text', options: true, multiple: false, filter: 'choice', calculated: false },
  datalist: { category: 'choice', storage: 'string', valueType: 'text', options: true, multiple: false, filter: 'text', calculated: false },
  output: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: true },
  progress: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: true },
  meter: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: true },
  rich_text: { category: 'text', storage: 'longtext', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  markdown: { category: 'text', storage: 'longtext', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  code: { category: 'text', storage: 'longtext', valueType: 'text', options: false, multiple: false, filter: 'none', calculated: false },
  currency: { category: 'number', storage: 'currency', valueType: 'number', options: false, multiple: false, filter: 'number', calculated: false },
  percentage: { category: 'number', storage: 'decimal', valueType: 'number', options: false, multiple: false, filter: 'number', calculated: false },
  decimal: { category: 'number', storage: 'decimal', valueType: 'number', options: false, multiple: false, filter: 'number', calculated: false },
  auto_number: { category: 'number', storage: 'auto_number', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  formula: { category: 'number', storage: 'formula', valueType: 'any', options: false, multiple: false, filter: 'number', calculated: true },
  calendar_date: { category: 'datetime', storage: 'date', valueType: 'date', options: false, multiple: false, filter: 'date', calculated: false },
  date_range: { category: 'datetime', storage: 'range_date', valueType: 'date', options: false, multiple: false, filter: 'date', calculated: false },
  time_range: { category: 'datetime', storage: 'range_time', valueType: 'time', options: false, multiple: false, filter: 'time', calculated: false },
  datetime_range: { category: 'datetime', storage: 'range_datetime', valueType: 'datetime', options: false, multiple: false, filter: 'datetime', calculated: false },
  duration: { category: 'datetime', storage: 'duration', valueType: 'duration', options: false, multiple: false, filter: 'number', calculated: false },
  toggle: { category: 'choice', storage: 'bool', valueType: 'boolean', options: false, multiple: false, filter: 'boolean', calculated: false },
  checkbox_group: { category: 'choice', storage: 'multi_choice', valueType: 'list', options: true, multiple: true, filter: 'choice', calculated: false },
  radio_group: { category: 'choice', storage: 'choice', valueType: 'text', options: true, multiple: false, filter: 'choice', calculated: false },
  button_group: { category: 'choice', storage: 'choice', valueType: 'text', options: true, multiple: false, filter: 'choice', calculated: false },
  dropdown_search: { category: 'choice', storage: 'choice', valueType: 'text', options: true, multiple: false, filter: 'choice', calculated: false },
  multi_select_chips: { category: 'choice', storage: 'multi_choice', valueType: 'list', options: true, multiple: true, filter: 'choice', calculated: false },
  tags: { category: 'choice', storage: 'multi_choice', valueType: 'list', options: true, multiple: true, filter: 'choice', calculated: false },
  cascading_select: { category: 'choice', storage: 'choice', valueType: 'text', options: true, multiple: false, filter: 'choice', calculated: false },
  lookup: { category: 'reference', storage: 'lookup', valueType: 'record', options: false, multiple: false, filter: 'lookup', calculated: false },
  user_picker: { category: 'reference', storage: 'user', valueType: 'record', options: false, multiple: false, filter: 'lookup', calculated: false },
  role_picker: { category: 'reference', storage: 'role', valueType: 'record', options: false, multiple: false, filter: 'lookup', calculated: false },
  department_picker: { category: 'reference', storage: 'department', valueType: 'record', options: false, multiple: false, filter: 'lookup', calculated: false },
  country_picker: { category: 'reference', storage: 'lookup', valueType: 'record', options: false, multiple: false, filter: 'lookup', calculated: false },
  city_picker: { category: 'reference', storage: 'lookup', valueType: 'record', options: false, multiple: false, filter: 'lookup', calculated: false },
  rating: { category: 'number', storage: 'int', valueType: 'number', options: false, multiple: false, filter: 'number', calculated: false },
  slider: { category: 'number', storage: 'decimal', valueType: 'number', options: false, multiple: false, filter: 'number', calculated: false },
  color_palette: { category: 'choice', storage: 'string', valueType: 'text', options: true, multiple: false, filter: 'choice', calculated: false },
  file_multi: { category: 'file', storage: 'files', valueType: 'list', options: false, multiple: true, filter: 'none', calculated: false },
  image_upload: { category: 'file', storage: 'file', valueType: 'list', options: false, multiple: false, filter: 'none', calculated: false },
  camera: { category: 'file', storage: 'file', valueType: 'list', options: false, multiple: false, filter: 'none', calculated: false },
  signature: { category: 'special', storage: 'file', valueType: 'list', options: false, multiple: false, filter: 'none', calculated: false },
  map_location: { category: 'special', storage: 'map', valueType: 'text', options: false, multiple: false, filter: 'none', calculated: false },
  phone_intl: { category: 'special', storage: 'phone', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  national_id: { category: 'special', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  iban: { category: 'special', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  barcode: { category: 'special', storage: 'string', valueType: 'text', options: false, multiple: false, filter: 'text', calculated: false },
  json: { category: 'special', storage: 'json', valueType: 'text', options: false, multiple: false, filter: 'none', calculated: false },
  key_value: { category: 'special', storage: 'json', valueType: 'text', options: false, multiple: false, filter: 'none', calculated: false },
  consent: { category: 'special', storage: 'consent', valueType: 'boolean', options: false, multiple: false, filter: 'boolean', calculated: false },
  static_html: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  heading: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  divider: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  spacer: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  display_image: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  alert_box: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
  link: { category: 'display', storage: 'none', valueType: 'null', options: false, multiple: false, filter: 'none', calculated: false },
}

export function fieldType(type: string): FieldTypeInfo | null {
  return Object.prototype.hasOwnProperty.call(FIELD_TYPES, type) ? FIELD_TYPES[type]! : null
}

export function storageOf(type: string): Storage {
  return fieldType(type)?.storage ?? 'none'
}

export function isStored(type: string): boolean {
  return storageOf(type) !== 'none'
}
