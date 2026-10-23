import { get, send } from '@/api/http'
import type { ClientDefinition, References, Values } from '@/runtime/types'
import type { ClientTheme } from '@/theme/theme'

/** External access (specification §4.32, ADR-0058): administration, the public pages, and sharing from a record. */
export type I18nMap = Record<string, string>
export type Verification = 'none' | 'email' | 'sms'
export type Audience = 'public' | 'accounts'
export type Availability = 'inactive' | 'not_open' | 'closed' | 'full' | 'outside_hours' | null

export interface DailyWindow {
  from: string
  to: string
  days: number[]
}
export interface ExternalFormDoc {
  uuid?: string
  form: string | null
  slug: string
  title: I18nMap
  intro: I18nMap
  success_message: I18nMap
  theme: string | null
  initial_status: string | null
  fields: string[]
  captcha: boolean
  rate_limit_per_ip: number
  opens_at: string | null
  closes_at: string | null
  daily_window: DailyWindow | null
  submission_cap: number | null
  verification: Verification
  run_as: string | null
  active: boolean
  audience: Audience
  roles: string[]
  /** Write-only: a new password, or removing the current one. */
  password?: string
  clear_password?: boolean
  has_password?: boolean
  hash?: string
  run_as_name?: string | null
  url?: string
  label?: string
  submissions?: number
  availability?: Availability
}
export interface ExternalFormSummary {
  uuid: string
  slug: string
  url: string
  label: string
  form_name: string | null
  active: boolean
  audience: Audience
  submissions: number
  cap: number | null
  availability: Availability
}
export interface ExposableField {
  uuid: string
  key: string
  type: string
  label: string
  display: boolean
  required: boolean
}
export interface StatusChoice {
  uuid: string
  label: string
  initial: boolean
}
export type AccountStatus = 'pending_verification' | 'pending_approval' | 'approved' | 'rejected' | 'suspended'
export interface ExternalAccount {
  uuid: string
  name: string
  email: string
  status: AccountStatus
  role: string
  role_name: string | null
  created_at: string | null
  last_login_at: string | null
  verified: boolean
}
export interface ExternalRole {
  uuid: string
  key: string
  name: I18nMap
  label: string
  accounts: number
}
export interface PortalSettings {
  registration_open: boolean
  registration_role: string | null
  registration_requires_approval: boolean
  portal_url?: string
}

export const externalFormsApi = {
  list: () => get<{ data: ExternalFormSummary[] }>('/external-forms').then((r) => r.data),
  fields: (form: string) => get<{ data: { fields: ExposableField[]; statuses: StatusChoice[] } }>('/external-forms/fields', { form }).then((r) => r.data),
  load: (uuid: string) => get<{ data: ExternalFormDoc }>(`/external-forms/${uuid}`).then((r) => r.data),
  create: (form: ExternalFormDoc) => send<{ data: ExternalFormDoc }>('post', '/external-forms', { form }).then((r) => r.data),
  save: (uuid: string, form: ExternalFormDoc, baseHash: string) => send<{ data: ExternalFormDoc }>('put', `/external-forms/${uuid}`, { form, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/external-forms/${uuid}`),
}
export const accountsApi = {
  list: (status: AccountStatus | null, q: string, page: number) =>
    get<{ data: ExternalAccount[]; meta: { total: number; page: number; per_page: number } }>('/external-accounts', { status: status ?? undefined, q: q || undefined, page }),
  decide: (uuid: string, decision: 'approve' | 'reject' | 'suspend' | 'reactivate') => send('post', `/external-accounts/${uuid}/decision`, { decision }),
  setRole: (uuid: string, role: string) => send('put', `/external-accounts/${uuid}/role`, { role }),
  remove: (uuid: string) => send('delete', `/external-accounts/${uuid}`),
  settings: () => get<{ data: PortalSettings }>('/external-settings').then((r) => r.data),
  saveSettings: (s: PortalSettings) => send<{ data: PortalSettings }>('put', '/external-settings', s).then((r) => r.data),
  roles: () => get<{ data: ExternalRole[] }>('/external-roles').then((r) => r.data),
  createRole: (key: string, name: I18nMap) => send('post', '/external-roles', { key, name }),
  renameRole: (uuid: string, name: I18nMap) => send('put', `/external-roles/${uuid}`, { name }),
  removeRole: (uuid: string) => send('delete', `/external-roles/${uuid}`),
}

// Sharing from a record.
export type LinkAction = 'view' | 'approve' | 'reject' | 'complete_section'
export interface RecordLink {
  uuid: string
  action: LinkAction
  recipient: string | null
  transition: string | null
  section: string | null
  uses: number
  max_uses: number
  expires_at: string
  created_at: string | null
  created_by: string | null
  state: 'active' | 'revoked' | 'expired' | 'used'
  can_revoke: boolean
  url?: string
}
export interface SignatureRow {
  uuid: string
  status: 'pending' | 'viewed' | 'signed' | 'declined' | 'expired' | 'cancelled'
  signer: string | null
  signer_type: 'email' | 'user' | 'external_user'
  template: string | null
  requested_by: string | null
  created_at: string | null
  expires_at: string
  signed_at: string | null
  signed_sha256: string | null
  decline_reason: string | null
  has_signed_file: boolean
  can_cancel: boolean
}
export interface Sharing {
  links: RecordLink[]
  signatures: SignatureRow[]
  choices: { actions: LinkAction[]; transitions: { uuid: string; label: string }[]; sections: { key: string; label: string }[] }
  templates: { uuid: string; name: string }[]
}
export interface NewLink {
  action: LinkAction
  transition: string | null
  section: string | null
  recipient: string
  days: number
  uses: number
}
export interface NewSignature {
  template: string | null
  signer_type: 'email' | 'user'
  signer: string
  days: number
}
export const sharingApi = {
  load: (form: string, record: string) => get<{ data: Sharing }>(`/r/${form}/${record}/sharing`).then((r) => r.data),
  link: (form: string, record: string, link: NewLink) => send<{ data: RecordLink }>('post', `/r/${form}/${record}/links`, { link }).then((r) => r.data),
  revoke: (uuid: string) => send('delete', `/record-links/${uuid}`),
  sign: (form: string, record: string, signature: NewSignature) => send<{ data: SignatureRow }>('post', `/r/${form}/${record}/signature-requests`, { signature }).then((r) => r.data),
  cancel: (uuid: string) => send('post', `/signature-requests/${uuid}/cancel`),
  signedUrl: (uuid: string) => `/api/v1/signature-requests/${uuid}/signed`,
}

// The public pages.
export interface PublicFormPage {
  slug: string
  title: string
  intro: string | null
  theme: ClientTheme | null
  captcha: boolean
  verification: Verification
  password: boolean
  audience: Audience
  availability: Availability
  admitted: boolean
  signed_in?: boolean
  definition?: ClientDefinition
}
export interface PowChallenge {
  algorithm: string
  challenge: string
  maxnumber: number
  salt: string
  signature: string
}
export interface PowAnswer {
  salt: string
  number: number
  challenge: string
  signature: string
}
export interface Submission {
  submission: string
  values: Values
  password?: string
  captcha?: PowAnswer
  verification?: { to: string; code: string }
}
export interface PortalRecordRow {
  slug: string
  form: string
  uuid: string
  reference: string | null
  status: { name: string; color: string } | null
  submitted_at: string
}
export interface StatusPayload {
  uuid: string
  key: string
  name: string
  color: string
  icon?: string | null
}
export interface LinkPage {
  action: LinkAction
  form_name: string
  title: string | null
  status: StatusPayload | null
  sender: string
  expires_at: string
  uses_left: number
  transition: { label: string; comment: 'none' | 'optional' | 'mandatory' } | null
  definition: ClientDefinition
  values: Values
  references: References
  row_version: number
}
export interface SignPage {
  state: string | null
  signer: string | null
  expires_at: string
  sender: string | null
  document: string | null
}

const post = <T = unknown>(url: string, data?: unknown): Promise<T> => send<T>('post', url, data)
export const publicApi = {
  form: (slug: string) => get<{ data: PublicFormPage }>(`/x/forms/${slug}`).then((r) => r.data),
  unlock: (slug: string, password: string) => post<{ data: PublicFormPage }>(`/x/forms/${slug}/unlock`, { password }).then((r) => r.data),
  challenge: (slug: string) => get<{ data: PowChallenge }>(`/x/forms/${slug}/challenge`).then((r) => r.data),
  code: (slug: string, to: string) => post(`/x/forms/${slug}/code`, { to }),
  submit: (slug: string, body: Submission) => post<{ data: { reference: string | null; message: string | null } }>(`/x/forms/${slug}`, body).then((r) => r.data),

  portal: () => get<{ data: { registration_open: boolean; me: { name: string; email: string } | null } }>('/x/portal').then((r) => r.data),
  register: (body: { name: string; email: string; password: string; password_confirmation: string }) => post('/x/portal/register', body),
  verify: (email: string, code: string) => post<{ data: { status: AccountStatus } }>('/x/portal/verify', { email, code }).then((r) => r.data),
  resend: (email: string) => post('/x/portal/resend', { email }),
  login: (email: string, password: string) => post<{ data: { name: string; email: string } }>('/x/portal/login', { email, password }).then((r) => r.data),
  logout: () => post('/x/portal/logout'),
  myForms: () => get<{ data: { slug: string; title: string; intro: string | null }[] }>('/x/portal/forms').then((r) => r.data),
  myRecords: () => get<{ data: PortalRecordRow[] }>('/x/portal/records').then((r) => r.data),
  myRecord: (slug: string, record: string) =>
    get<{ data: { form: string; reference: string | null; status: StatusPayload | null; definition: ClientDefinition; values: Values; references: References } }>(
      `/x/portal/records/${slug}/${record}`,
    ).then((r) => r.data),

  link: (token: string) => get<{ data: LinkPage }>(`/x/links/${token}`).then((r) => r.data),
  useLink: (token: string, body: { row_version: number; values?: Values; comment?: string }) => post(`/x/links/${token}`, body),
  signature: (token: string) => get<{ data: SignPage }>(`/x/signatures/${token}`).then((r) => r.data),
  documentUrl: (token: string) => `/api/v1/x/signatures/${token}/document`,
  sign: (token: string, signature: string) => post(`/x/signatures/${token}/sign`, { signature }),
  decline: (token: string, reason: string) => post(`/x/signatures/${token}/decline`, { reason }),
}
