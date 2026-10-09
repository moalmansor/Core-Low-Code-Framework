import type { Component } from 'vue'
import ActionButton from './ActionButton.vue'
import BooleanInput from './BooleanInput.vue'
import ChoiceInput from './ChoiceInput.vue'
import DateInput from './DateInput.vue'
import DisplayElement from './DisplayElement.vue'
import DurationInput from './DurationInput.vue'
import FileInput from './FileInput.vue'
import JsonInput from './JsonInput.vue'
import MapInput from './MapInput.vue'
import NumberInput from './NumberInput.vue'
import PhoneInput from './PhoneInput.vue'
import RangeInput from './RangeInput.vue'
import RatingInput from './RatingInput.vue'
import ReferenceInput from './ReferenceInput.vue'
import RichTextInput from './RichTextInput.vue'
import SignatureInput from './SignatureInput.vue'
import SliderInput from './SliderInput.vue'
import TextAreaInput from './TextAreaInput.vue'
import TextInput from './TextInput.vue'

/**
 * The input component of every field type in the registry. Types missing
 * here (or unknown to this client) render as a read-only value with a notice.
 */
const BY_TYPE: Record<string, Component> = {}
const register = (component: Component, types: string[]) => types.forEach((t) => (BY_TYPE[t] = component))

register(TextInput, ['text', 'password', 'email', 'tel', 'url', 'search', 'national_id', 'iban', 'barcode', 'datalist', 'color'])
register(TextAreaInput, ['textarea', 'code', 'markdown'])
register(RichTextInput, ['rich_text'])
register(NumberInput, ['number', 'decimal', 'percentage', 'currency'])
register(SliderInput, ['range', 'slider'])
register(RatingInput, ['rating'])
register(DurationInput, ['duration'])
register(DateInput, ['date', 'calendar_date', 'month', 'week', 'time', 'datetime_local'])
register(RangeInput, ['date_range', 'time_range', 'datetime_range'])
register(ChoiceInput, [
  'select',
  'select_grouped',
  'dropdown_search',
  'cascading_select',
  'radio',
  'radio_group',
  'button_group',
  'select_multiple',
  'checkbox_group',
  'multi_select_chips',
  'tags',
  'color_palette',
])
register(BooleanInput, ['checkbox', 'toggle', 'consent'])
register(ReferenceInput, ['lookup', 'user_picker', 'role_picker', 'department_picker', 'country_picker', 'city_picker'])
register(FileInput, ['file', 'file_multi', 'image_upload', 'camera'])
register(SignatureInput, ['signature'])
register(MapInput, ['map_location'])
register(PhoneInput, ['phone_intl'])
register(JsonInput, ['json', 'key_value'])
register(DisplayElement, ['static_html', 'heading', 'divider', 'spacer', 'display_image', 'alert_box', 'link', 'output', 'progress', 'meter'])
register(ActionButton, ['button', 'image_button', 'submit', 'reset'])

/** Display elements and buttons render themselves, without a label or value. */
export const DISPLAY_TYPES = new Set(['static_html', 'heading', 'divider', 'spacer', 'display_image', 'alert_box', 'link', 'output', 'progress', 'meter', 'button', 'image_button', 'submit', 'reset'])

/** Always read-only: values produced by the server or by a formula. */
export const READ_ONLY_TYPES = new Set(['auto_number', 'formula'])

/** Types whose label sits beside the control (checkbox-like). */
export const INLINE_LABEL_TYPES = new Set(['checkbox', 'toggle', 'consent'])

export function componentFor(type: string): Component | null {
  return BY_TYPE[type] ?? null
}

export function hasComponent(type: string): boolean {
  return type in BY_TYPE || READ_ONLY_TYPES.has(type) || type === 'hidden'
}
