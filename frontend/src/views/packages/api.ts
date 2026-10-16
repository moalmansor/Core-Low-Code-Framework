import { get, send } from '@/api/http'
import { downloadBlob } from '@/runtime/api'

/** Configuration packages and drift reports (specification §4.10, §4.22, ADR-0052). */
export type ObjectType = 'application' | 'form' | 'email_template' | 'document_template' | 'download_profile'
export const OBJECT_TYPES: ObjectType[] = ['application', 'form', 'email_template', 'document_template', 'download_profile']

export type ObjectState = 'new' | 'key_taken' | 'same' | 'changed' | 'changed_here' | 'changed_in_both' | 'unpublished_here' | 'schema_drift'
export type Action = 'take' | 'keep' | 'rename'
export type PackageStatus = 'validated' | 'conflicts' | 'applying' | 'applied' | 'failed'

export interface CatalogItem {
  uuid: string
  key: string
  label: string
  note?: string | null
}
export type Catalog = Record<ObjectType, CatalogItem[]>

export interface ReportRow {
  type: ObjectType
  uuid: string
  key: string
  localKey?: string
  label: string
  state: ObjectState
  differences: string[]
  missing: string[]
}
export interface SchemaDrift {
  form: string
  key: string
  table: string
  differences: unknown[]
}
export interface Report {
  objects: ReportRow[]
  schema: SchemaDrift[]
  ambiguous: boolean
  conflicts: string[]
  missingSubjects: { type: string; key: string }[]
}
export interface Result {
  uuid?: string
  type?: ObjectType
  key?: string
  outcome: 'applied' | 'kept' | 'failed' | 'not_run'
  notes: string[]
}
export interface PackageSummary {
  uuid: string
  direction: 'export' | 'import'
  name: string
  status: PackageStatus
  objects: number
  source: string | null
  by: string | null
  created_at: string
  applied_at: string | null
}
export interface PackageDetail extends PackageSummary {
  source_detail: { environment?: string; url?: string; exportedAt?: string; exportedBy?: string } | null
  items: { type: ObjectType; uuid: string; key: string; label?: string }[]
  report: Report | null
  choices: Record<string, { action: Action; key: string }> | null
  results: Result[] | null
}
export interface DriftReport {
  uuid: string
  source: string
  target: string
  ambiguous: boolean
  created_at: string
  counts: Partial<Record<ObjectState, number>>
  objects?: ReportRow[]
  schema?: SchemaDrift[]
}
export interface Choice {
  action: Action
  key?: string | null
}

function upload(path: string, file: File) {
  const body = new FormData()
  body.append('file', file)
  return send<{ data: unknown }>('post', path, body)
}

export const packagesApi = {
  list: async () => (await get<{ data: PackageSummary[] }>('/packages')).data,
  catalog: async () => (await get<{ data: Catalog }>('/packages/catalog')).data,
  show: async (uuid: string) => (await get<{ data: PackageDetail }>(`/packages/${uuid}`)).data,
  export: async (name: string, objects: { type: ObjectType; uuid: string }[]) => (await send<{ data: PackageDetail }>('post', '/packages/export', { name, objects })).data,
  import: async (file: File) => (await upload('/packages/import', file)).data as PackageDetail,
  compare: async (file: File) => (await upload('/packages/compare', file)).data as DriftReport,
  apply: async (uuid: string, choices: Record<string, Choice>, options: { confirm_blocking: boolean; confirm_destructive: boolean }) =>
    (await send<{ data: PackageDetail }>('post', `/packages/${uuid}/apply`, { choices, ...options })).data,
  download: (uuid: string, name: string) => downloadBlob(`/packages/${uuid}/download`, {}, `${name}.json`),
  reports: async () => (await get<{ data: DriftReport[] }>('/drift-reports')).data,
  report: async (uuid: string) => (await get<{ data: DriftReport }>(`/drift-reports/${uuid}`)).data,
}

/** States that stop the whole package until they are resolved here. */
export const BLOCKING: ObjectState[] = ['changed_in_both', 'unpublished_here', 'schema_drift']

/** The actions an object in a given state can take. */
export function actionsFor(row: ReportRow): Action[] {
  if (BLOCKING.includes(row.state)) return []
  if (row.missing.length) return ['keep']
  switch (row.state) {
    case 'new':
      return ['take', 'rename', 'keep']
    case 'key_taken':
      return ['rename', 'keep']
    case 'same':
      return ['keep']
    default:
      return ['take', 'keep']
  }
}

/** What is chosen for an object before the person changes anything. */
export function defaultAction(row: ReportRow): Action | null {
  if (BLOCKING.includes(row.state)) return null
  if (row.missing.length) return 'keep'
  if (row.state === 'new') return 'take'
  if (row.state === 'same' || row.state === 'changed_here') return 'keep'
  return null
}
