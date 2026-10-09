import { get, send } from '@/api/http'
import type { AstNode } from '@/expressions'
import type { DraftDocument, DraftIssue, FieldTypeCatalog, Fragment } from './types'

/** Typed calls to the form builder API (backend/app/Modules/Forms/routes.php). */

export interface FormSummary {
  uuid: string
  key: string
  kind: 'form' | 'collection'
  name: string
  state: string
  binding_mode: 'managed' | 'bound'
  table_name: string
  icon: string | null
  version: number | null
  next_version: number
  application: { uuid: string; key: string } | null
  record_count: number | null
  updated_at: string | null
}

export interface FormDetail extends FormSummary {
  names: Record<string, string>
  descriptions: Record<string, string>
  draft_updated_at: string | null
  last_plan: { uuid: string; status: string; purpose: string; error: string | null } | null
}

export interface DraftPayload {
  document: DraftDocument
  draft_updated_at: string | null
  problems: DraftIssue[]
}

export interface SaveResult {
  draft_updated_at: string
  problems: DraftIssue[]
}

export interface FieldTemplate {
  uuid: string
  kind: 'field' | 'group'
  category: string | null
  name: string
  description: string | null
  names: Record<string, string>
  usage_count: number
  definition: Partial<Fragment>
  application: string | null
}

export interface ExpressionError {
  code: string
  message: string
  position: number | null
  node: string | null
}

export type ParseResult = { ok: true; ast: AstNode; type: string } | { ok: false; error: ExpressionError }
export type CheckResult = { ok: true; type: string } | { ok: false; error: ExpressionError }

export interface EvaluateResult {
  value: { t: string; v?: unknown }
  diagnostics: { code: string; node: string }[]
}

export interface VersionEntry {
  uuid: string
  version: number
  state: string
  change_class: string | null
  published_at: string
  published_by: string | null
  change_note: string | null
  rollback_of: number | null
  summary: { added: number; removed: number; changed: number } | null
}

export interface PropertyChange {
  path: string
  before: unknown
  after: unknown
}

export interface DiffEntry {
  uuid: string
  key: string | null
  change: 'added' | 'removed' | 'changed'
  after?: Record<string, unknown>
  before?: Record<string, unknown>
  changes?: PropertyChange[]
}

export interface VersionDiff {
  form: PropertyChange[]
  groups: DiffEntry[]
  fields: DiffEntry[]
  relations: DiffEntry[]
  conditions: DiffEntry[]
  access: { added: number; removed: number; changed: number }
  summary: { added: number; removed: number; changed: number }
}

export interface PlanStep {
  sequence: number
  operation: string
  table_name: string
  is_destructive: boolean
  is_online: boolean
  estimated_ms: number
  lock_level?: string
  rows?: number
  sql_preview: string
}

export interface Impact {
  records: {
    count: number
    failing_required: { field: string; key: string; records: number }[]
    type_conflicts: { table: string; column: string; to: string; records: unknown[] }[]
  }
  diff: { added?: number; removed?: number; changed?: number }
  removed_fields: string[]
  permissions: { orphaned_access_rules: number }
  linked_forms: { form: string | null; form_key: string | null; relation: string; broken: boolean }[]
  menus: number
  dependents: Record<string, number>
  schema: {
    steps: number
    online: number
    blocking: { sequence: number; operation: string; table: string; rows: number; estimated_ms: number }[]
    estimated_ms: number
    backup: { tables: string[]; rows: number; retention_days: number } | null
    change_class: string
    plan: PlanStep[]
  }
  requires_confirmation: { blocking_steps: boolean; destructive: boolean; typed: string | null; failing_required: boolean }
  blocking: { code: string; path: string; message: string; detail?: string }[]
  lock_set?: { uuid: string; key: string }[]
  workflow?: { removed_statuses: { uuid: string; key: string; name: string; records: number; to: string | null }[]; unassigned_records: number }
}

export interface ImpactResponse {
  impact: Impact
  impact_hash: string
  diff: VersionDiff
  version: number
}

export interface PlanStatus {
  uuid: string
  form: { uuid: string; key: string; state: string } | null
  purpose: string
  status: string
  to_version: number
  steps_total: number
  steps_applied: number
  error: string | null
  queued_behind: { form: string | null; user: string | null; since: string | null } | null
  steps: { sequence: number; operation: string; table: string; status: string; error: string | null }[]
  snapshots: { uuid: string; kind: string; disk: string; path: string; size_bytes: number; tables: string[]; expires_at: string }[]
}

export const builderApi = {
  form: (form: string) => get<{ data: FormDetail }>(`/forms/${form}`).then((r) => r.data),
  forms: (params: Record<string, unknown>) => get<{ data: FormSummary[] }>('/forms', params).then((r) => r.data),
  draft: (form: string) => get<{ data: DraftPayload }>(`/forms/${form}/draft`).then((r) => r.data),
  saveDraft: (form: string, document: DraftDocument, stamp: string | null) => send<{ data: SaveResult }>('put', `/forms/${form}/draft`, { document, draft_updated_at: stamp }).then((r) => r.data),
  validateDraft: (form: string, document: DraftDocument) => send<{ data: { errors: DraftIssue[]; problems: DraftIssue[] } }>('post', `/forms/${form}/draft/validate`, { document }).then((r) => r.data),
  fieldTypes: () => get<{ data: FieldTypeCatalog }>('/field-types').then((r) => r.data),
  templates: () => get<{ data: FieldTemplate[] }>('/field-templates').then((r) => r.data),
  saveTemplate: (body: { kind: 'field' | 'group'; category: string | null; name: Record<string, string>; description: Record<string, string>; definition: Fragment; application?: string | null }) =>
    send<{ data: { uuid: string } }>('post', '/field-templates', body).then((r) => r.data),
  templateUsed: (uuid: string) => send('post', `/field-templates/${uuid}/used`),
  deleteTemplate: (uuid: string) => send('delete', `/field-templates/${uuid}`),
  parse: (body: { source: string; form?: string | null; expected?: string | null; rows?: string | null }) => send<{ data: ParseResult }>('post', '/expressions/parse', body).then((r) => r.data),
  check: (body: { ast: AstNode; form?: string | null; expected?: string | null; rows?: string | null }) => send<{ data: CheckResult }>('post', '/expressions/check', body).then((r) => r.data),
  evaluate: (body: { ast: AstNode; record?: Record<string, unknown>; old?: Record<string, unknown>; mode?: string; today?: string }) =>
    send<{ data: EvaluateResult }>('post', '/expressions/evaluate', body).then((r) => r.data),
  preview: (form: string, params: { mode: string; as_user?: string | null; as_role?: string | null }) =>
    get<{ data: { definition: Record<string, unknown>; problems: DraftIssue[] } }>(
      `/forms/${form}/preview`,
      Object.fromEntries(Object.entries(params).filter(([, v]) => v !== null && v !== undefined)),
    ).then((r) => r.data),
  impact: (form: string) => send<{ data: ImpactResponse }>('post', `/forms/${form}/impact`).then((r) => r.data),
  publish: (form: string, body: Record<string, unknown>) => send<{ data: { plan: string; status: string } }>('post', `/forms/${form}/publish`, body).then((r) => r.data),
  plan: (plan: string) => get<{ data: PlanStatus }>(`/migration-plans/${plan}`).then((r) => r.data),
  versions: (form: string) => get<{ data: VersionEntry[] }>(`/forms/${form}/versions`).then((r) => r.data),
  version: (form: string, n: number) =>
    get<{ data: { version: number; state: string; definition: Record<string, unknown>; impact_report: Impact | null; diff_from_previous: VersionDiff | null } }>(`/forms/${form}/versions/${n}`).then(
      (r) => r.data,
    ),
  diff: (form: string, from: string, to: string) => get<{ data: VersionDiff }>(`/forms/${form}/versions/${from}/diff/${to}`).then((r) => r.data),
  rollback: (form: string, n: number, discardDraft: boolean) =>
    send<{ data: { rollback_of: number; draft_updated_at: string } }>('post', `/forms/${form}/versions/${n}/rollback`, { discard_draft: discardDraft }).then((r) => r.data),
}

/** Reference lists the builder offers in pickers. */
export interface NamedOption {
  uuid: string
  key: string
  name: string
}

export const referenceApi = {
  roles: () => get<{ data: (NamedOption & { is_admin_role: boolean })[] }>('/role-options').then((r) => r.data),
  applications: () => get<{ data: (NamedOption & { status: string; icon: string | null })[] }>('/applications').then((r) => r.data),
  menu: (app: string) => get<{ data: MenuNode[] }>(`/applications/${app}/menu`).then((r) => r.data),
  sequences: () => get<{ data: { uuid: string; key: string; pattern: string | null; next_preview: string | null }[] }>('/number-sequences').then((r) => r.data),
  calendars: () => get<{ data: { uuid: string; key: string; name: string | null }[] }>('/business-calendars').then((r) => r.data),
  departments: () => get<{ data: DepartmentTreeNode[] }>('/departments/tree').then((r) => r.data),
}

export interface MenuNode {
  uuid: string
  type: string
  label?: string
  icon?: string
  children: MenuNode[]
}

export interface DepartmentTreeNode {
  uuid: string
  code: string
  name: string
  children: DepartmentTreeNode[]
}
