import { inject, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import type { FormIndex } from './formIndex'
import type { RowRef, RuleEnv, RuleState } from './rules'
import type { ClientField, ClientGroup, FileMeta, FormMode, References, Row, Values } from './types'

/** Everything field and group components share inside one FormRenderer. */
export interface RendererContext {
  index: ComputedRef<FormIndex>
  mode: ComputedRef<FormMode>
  formUuid: ComputedRef<string | undefined>
  locale: ComputedRef<string>
  values: ComputedRef<Values>
  state: ComputedRef<RuleState>
  env: ComputedRef<RuleEnv>
  /** Titles of referenced records known so far (props plus picks made in this session). */
  references: ComputedRef<References>
  files: Ref<Record<string, FileMeta>>
  setValue(field: ClientField, value: unknown, row: RowRef | null): void
  setRows(repeaterKey: string, rows: Row[]): void
  /** Runs the field's configured events (change, focus, blur). */
  fieldEvent(field: ClientField, on: 'change' | 'focus' | 'blur', row: RowRef | null): void
  /** Bumped whenever a field's options must be reloaded. */
  optionsVersion(fieldUuid: string): number
  rememberTitle(fieldKey: string, uuid: string, title: string): void
  rememberFile(meta: FileMeta): void
  /** Messages shown under a path (server errors, then client errors once the path was touched). */
  errorsAt(path: string): string[]
  touch(path: string): void
  /** Client validation of the paths below a group (wizard steps); marks them touched. */
  validateGroup(group: ClientGroup): boolean
  /** Restores the values the form opened with (reset buttons). */
  reset(): void
  /** Value of a calculated display element (output, progress, meter). */
  displayValue(field: ClientField, row: RowRef | null): unknown
}

export const RENDERER: InjectionKey<RendererContext> = Symbol('lcf-renderer')

/** File metadata of the record being shown (uuid → meta), provided by record screens. */
export const RECORD_FILES: InjectionKey<Ref<Record<string, FileMeta>>> = Symbol('lcf-record-files')

export function useRenderer(): RendererContext {
  const ctx = inject(RENDERER)
  if (!ctx) throw new Error('Field components must be rendered inside FormRenderer')
  return ctx
}

/** The error/value path of a field: `key`, or `repeater.index.key` inside a row. */
export function pathOf(field: ClientField, row: RowRef | null): string {
  return row === null ? field.key : `${row[0]}.${row[1]}.${field.key}`
}

/** A field's value in the main record or in a row. */
export function valueOf(values: Values, field: ClientField, row: RowRef | null): unknown {
  if (row === null) return values[field.key] ?? null
  const rows = values[row[0]]
  return Array.isArray(rows) ? ((rows[row[1]] as Row | undefined)?.[field.key] ?? null) : null
}
