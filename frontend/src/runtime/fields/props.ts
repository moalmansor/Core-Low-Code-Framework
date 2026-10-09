import type { RowRef } from '../rules'
import type { ClientField } from '../types'

/** Props every input component receives from FieldNode. */
export interface InputProps {
  field: ClientField
  modelValue: unknown
  row: RowRef | null
  disabled?: boolean
  required?: boolean
  invalid?: boolean
  inputId: string
  size?: 'small' | 'large'
}

/** A type-specific setting from `ui.props` (see runtime/uiProps.ts). */
export function prop(field: ClientField, name: string, fallback: string): string
export function prop(field: ClientField, name: string, fallback: number): number
export function prop(field: ClientField, name: string, fallback: boolean): boolean
export function prop(field: ClientField, name: string, fallback: string | number | boolean): string | number | boolean {
  const v = field.ui.props?.[name]
  return v === undefined || v === null || typeof v !== typeof fallback ? fallback : (v as string | number | boolean)
}
