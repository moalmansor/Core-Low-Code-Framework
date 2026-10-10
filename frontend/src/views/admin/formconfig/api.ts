import { get, send } from '@/api/http'
import type { Ast } from '@/builder/types'
import { humanize } from '@/runtime/i18nText'

/**
 * Types and calls of a form's Phase 3 configuration documents (architecture
 * §21: Workflow, SLA, Views, Justification, Assignment). Every document is
 * saved whole with the hash it was loaded with; a 409 answer carries the
 * newer document.
 */

export type I18nMap = Record<string, string>

export interface StatusDoc {
  uuid: string
  key: string
  i18n: { name: I18nMap }
  color: string
  icon: string | null
  initial: boolean
  final: boolean
  order: number
  position: { x: number; y: number } | null
}

export interface ApproverDoc {
  type: 'user' | 'role' | 'department'
  uuid: string
  weight: number
}

export interface TransitionDoc {
  uuid: string
  key: string
  from: string | null
  to: string
  i18n: { name: I18nMap }
  condition: Ast | null
  requiredFields: string[]
  comment: 'none' | 'optional' | 'mandatory'
  attachments: 'none' | 'optional' | 'mandatory'
  approval: {
    mode: 'none' | 'all' | 'any_n' | 'quorum'
    approvers: ApproverDoc[]
    n: number | null
    quorumWeight: number | null
    dueInMinutes: number | null
    rejection: 'immediate' | 'wait_all'
    rejectionStatus: string | null
  }
  confirmation: boolean
  style: { color?: string; icon?: string } | null
  order: number
  edge: Record<string, unknown> | null
}

export interface EscalationDoc {
  afterMinutes: number
  action: 'notify' | 'reassign' | 'transition'
  params: { to?: { type: string; uuid?: string | null } | { type: string; uuid?: string | null }[]; transition?: string }
}

export interface SlaDoc {
  uuid: string
  status: string
  durationMinutes: number
  workingTime: boolean
  calendar: string | null
  warnBeforeMinutes: number | null
  escalations: EscalationDoc[]
  condition: Ast | null
  active: boolean
}

export interface WorkflowDocument {
  statuses: StatusDoc[]
  transitions: TransitionDoc[]
  sla: SlaDoc[]
}

export interface Issue {
  path: string
  code: string
  message: string
}

export interface WorkflowState {
  document: WorkflowDocument
  hash: string
  problems: Issue[]
  warnings: Issue[]
  published: boolean
}

export interface StatusMappingOverview {
  removed: { uuid: string; key: string; name: string; records: number; to: string | null }[]
  targets: { uuid: string; key: string; name: string }[]
  unassigned: number
}

export const workflowApi = {
  load: (form: string) => get<{ data: WorkflowState }>(`/forms/${form}/workflow`).then((r) => r.data),
  save: (form: string, document: WorkflowDocument, hash: string) => send<{ data: WorkflowState }>('put', `/forms/${form}/workflow`, { document, base_hash: hash }).then((r) => r.data),
  mapping: (form: string) => get<{ data: StatusMappingOverview }>(`/forms/${form}/workflow/status-mapping`).then((r) => r.data),
  chooseMapping: (form: string, mappings: { from: string; to: string }[]) => send<{ data: StatusMappingOverview }>('post', `/forms/${form}/workflow/status-mapping`, { mappings }).then((r) => r.data),
}

/** A document saved whole with its concurrency hash. */
export interface Versioned<T> {
  value: T
  hash: string
}

export function newUuid(): string {
  return crypto.randomUUID()
}

export type SubjectType = 'everyone' | 'role' | 'department' | 'user'
export interface SubjectRef {
  type: SubjectType
  uuid: string | null
}

export interface RecordRuleDoc {
  uuid: string
  subject: SubjectRef
  operation: 'view' | 'edit' | 'delete' | 'all'
  scope: 'none' | 'own' | 'own_department' | 'department_tree' | 'assigned' | 'all' | 'custom'
  effect: 'allow' | 'deny' | 'hard_deny'
  priority: number
  condition: Ast | null
}

export interface JustificationRuleDoc {
  uuid: string
  scope: 'form' | 'group' | 'field' | 'status' | 'transition' | 'delete' | 'restore' | 'reassign'
  target: string | null
  subject: SubjectRef
  level: 'not_required' | 'optional' | 'mandatory'
  condition: Ast | null
  levelWhen: 'not_required' | 'optional' | 'mandatory' | null
  text: { min: number | null; max: number | null }
  reasonCodes: { mode: 'none' | 'optional' | 'required'; source: 'codes' | 'collection'; set: string | null; collection: string | null }
  attachments: { mode: 'none' | 'optional' | 'required'; max: number | null; rules: { types?: string[]; maxSizeKb?: number } | null }
  showSummary: boolean
  active: boolean
  i18n: { title: I18nMap; help: I18nMap }
}

export interface AssignmentRuleDoc {
  uuid: string
  transition: string | null
  strategy: 'user' | 'role' | 'department' | 'field_user' | 'creator_manager' | 'round_robin' | 'least_loaded'
  target: { type: 'user' | 'role' | 'department'; uuid: string } | null
  field: string | null
  condition: Ast | null
  dueInMinutes: number | null
  workingTime: boolean
  priority: number
}

export type Path = string[]

export interface ViewColumnDoc {
  uuid?: string
  path: Path
  i18n: { label: I18nMap }
  width?: number | null
  pinned: 'none' | 'start' | 'end'
  visible: boolean
  sortable: boolean
  format?: Record<string, unknown> | null
  aggregate: 'none' | 'count' | 'sum' | 'avg' | 'min' | 'max'
}

export interface ViewFilterDoc {
  uuid?: string
  path: Path
  i18n: { label: I18nMap }
  type?: string
  operators?: string[]
  quick: boolean
  default?: unknown
}

export interface ViewDoc {
  uuid: string
  key: string
  i18n: { name: I18nMap }
  default: boolean
  priority: number
  pageSize: number
  defaultSort: { path: Path; dir: 'asc' | 'desc' }[]
  showTotals: boolean
  columnChooser: boolean
  globalSearch: boolean
  rowOptions: { view: boolean; edit: boolean; log: boolean }
  includeInQueues: boolean
  columns: ViewColumnDoc[]
  filters: ViewFilterDoc[]
}

export interface PathNode {
  key: string
  path: Path
  type: string
  label: I18nMap
  children: PathNode[]
  form?: { uuid: string; key: string }
}

export interface PanelDoc {
  uuid: string
  parent: string | null
  type: 'tabs' | 'tab' | 'section' | 'related_table' | 'derived_fields' | 'summary_widget' | 'status_timeline' | 'comments' | 'attachments' | 'form_body' | 'html'
  i18n: { title: I18nMap; content: I18nMap }
  relationPath?: Path | null
  config: Record<string, unknown>
  visibility: Ast | null
  order: number
}

export interface PreviewDoc {
  displayPaths: Path[]
  layout: { columns: number }
  autofill: { from: Path; to: string; overwrite: boolean }[]
  drawer: boolean
}

export interface PrintLayoutDoc {
  uuid: string
  key: string
  i18n: { name: I18nMap; header: I18nMap; footer: I18nMap }
  paper: 'a4' | 'a3' | 'letter' | 'legal'
  orientation: 'portrait' | 'landscape'
  layout: { sections: { type: 'form_body' | 'fields' | 'panel' | 'status_history' | 'page_break'; fields?: string[]; panel?: string }[] }
  showLogo: boolean
  default: boolean
}

/** GET/PUT of a hashed document at `path` whose payload key is `key`. */
export function documentApi<T>(path: string, key: string) {
  return {
    load: async (form: string): Promise<{ value: T; hash: string; extra: Record<string, unknown> }> => {
      const { data } = await get<{ data: Record<string, unknown> & { hash: string } }>(`/forms/${form}/${path}`)
      return { value: data[key] as T, hash: data.hash, extra: data }
    },
    save: async (form: string, value: T, hash: string): Promise<{ value: T; hash: string; extra: Record<string, unknown> }> => {
      const { data } = await send<{ data: Record<string, unknown> & { hash: string } }>('put', `/forms/${form}/${path}`, { [key]: value, base_hash: hash })
      return { value: data[key] as T, hash: data.hash, extra: data }
    },
  }
}

export const recordRulesApi = documentApi<RecordRuleDoc[]>('record-access-rules', 'rules')
export const justificationRulesApi = documentApi<JustificationRuleDoc[]>('justification-rules', 'rules')
export const assignmentRulesApi = documentApi<AssignmentRuleDoc[]>('assignment-rules', 'rules')
export const viewsApi = documentApi<ViewDoc[]>('views', 'views')
export const panelsApi = documentApi<PanelDoc[]>('view-panels', 'panels')
export const printLayoutsApi = documentApi<PrintLayoutDoc[]>('print-layouts', 'layouts')
export const previewsApi = {
  load: (form: string) => get<{ data: { default: PreviewDoc | null; fields: (PreviewDoc & { field: string })[]; hash: string } }>(`/forms/${form}/reference-previews`).then((r) => r.data),
  save: (form: string, doc: { default: PreviewDoc | null; fields: (PreviewDoc & { field: string })[] }, hash: string) =>
    send<{ data: { default: PreviewDoc | null; fields: (PreviewDoc & { field: string })[]; hash: string } }>('put', `/forms/${form}/reference-previews`, { ...doc, base_hash: hash }).then(
      (r) => r.data,
    ),
}

/** Label of a translatable map in the interface language, falling back to the default language, then the key. */
export function labelOf(map: I18nMap | undefined | null, locale: string, fallback: string, defaultLocale = 'en'): string {
  return map?.[locale] || map?.[defaultLocale] || Object.values(map ?? {}).find((v) => !!v) || humanize(fallback)
}
