import { get, send } from '@/api/http'
import type { Ast } from '@/builder/types'

/** Email templates and their placeholders (specification §4.16). */
export type I18nMap = Record<string, string>
export type BlockType = 'heading' | 'text' | 'button' | 'fields' | 'table' | 'related' | 'signature' | 'code' | 'divider' | 'spacer' | 'page_break'
export interface TemplateBlock {
  uuid: string
  type: BlockType
  text?: I18nMap
  label?: I18nMap
  link?: 'record' | 'url'
  url?: string | null
  fields?: string[]
  group?: string
  /** Columns: a repeater's column keys (table) or paths in the linked form (related), each optionally `path|format`. */
  columns?: string[]
  /** Formats of a repeater table's columns, by column key. */
  formats?: Record<string, string>
  /** A table of linked records: the form that links here, the field it links with, and how many rows at most. */
  source?: string | null
  via?: string
  limit?: number
  /** Signature lines. */
  signatories?: { label: I18nMap; name: I18nMap }[]
  date?: boolean
  /** A QR code or barcode of the record. */
  kind?: 'qr' | 'barcode'
  content?: 'link' | 'number'
  size?: 'small' | 'medium' | 'large'
  caption?: boolean
  condition: Ast | null
}
export interface TemplateDesign {
  style: { accent: string | null; logo: boolean }
  blocks: TemplateBlock[]
}
export interface TemplateDoc {
  uuid?: string
  key: string
  form: string | null
  application?: string | null
  design: TemplateDesign
  subject: I18nMap
  preheader: I18nMap
  active: boolean
  hash?: string
}
export interface TemplateSummary {
  uuid: string
  key: string
  active: boolean
  subject: string
  form: string | null
  form_name: string | null
  used_by: number
}
export interface Placeholder {
  token: string
  label: string
  group: 'system' | 'form' | 'linked'
  user?: boolean
  date?: boolean
  file?: boolean
}
export interface Placeholders {
  system: Placeholder[]
  fields: Placeholder[]
  repeaters: { key: string; label: string; columns: { key: string; label: string }[] }[]
}

export const templatesApi = {
  list: (form?: string | null) => get<{ data: TemplateSummary[] }>('/notifications/templates', form ? { form } : {}).then((r) => r.data),
  load: (uuid: string) => get<{ data: TemplateDoc }>(`/notifications/templates/${uuid}`).then((r) => r.data),
  create: (doc: TemplateDoc) => send<{ data: TemplateDoc }>('post', '/notifications/templates', doc).then((r) => r.data),
  save: (uuid: string, doc: TemplateDoc, hash: string) => send<{ data: TemplateDoc }>('put', `/notifications/templates/${uuid}`, { ...doc, base_hash: hash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/notifications/templates/${uuid}`),
  preview: (doc: TemplateDoc, record: string | null, asUser: string | null, locale: string) =>
    send<{ data: { subject: string; html: string; preheader: string; url: string } }>('post', '/notifications/templates/preview', {
      form: doc.form,
      design: doc.design,
      subject: doc.subject,
      preheader: doc.preheader,
      record,
      as_user: asUser,
      locale,
    }).then((r) => r.data),
  test: (uuid: string, record: string | null, locale: string) =>
    send<{ data: { status: string; to: string } }>('post', `/notifications/templates/${uuid}/test`, { record, locale }).then((r) => r.data),
  placeholders: (form: string | null) => get<{ data: Placeholders }>('/notifications/placeholders', form ? { form } : {}).then((r) => r.data),
}

/** SMS and messaging channels (specification §4.33, ADR-0056). */
export type ChannelType = 'sms' | 'messaging'
export interface ChannelSummary {
  uuid: string
  key: string
  type: ChannelType
  provider: string
  active: boolean
  label: string
  week: { sent: number; failed: number; pending: number }
}
export interface ChannelDoc {
  uuid?: string
  key: string
  type: ChannelType
  provider: string
  name: Record<string, string>
  config: Record<string, string | boolean | null>
  rate_limit_per_minute: number | null
  active: boolean
  hash?: string
  label?: string
}
export type ProviderFields = Record<ChannelType, Record<string, Record<string, { required: boolean; secret: boolean }>>>
export interface ChannelDelivery {
  id: number
  recipient: string
  status: 'pending' | 'sent' | 'failed' | 'cancelled'
  attempts: number
  error: string | null
  title: string | null
  created_at: string
  sent_at: string | null
}

export const channelsApi = {
  list: () => get<{ data: ChannelSummary[]; meta: { providers: ProviderFields } }>('/notification-channels'),
  load: (uuid: string) => get<{ data: ChannelDoc }>(`/notification-channels/${uuid}`).then((r) => r.data),
  create: (channel: ChannelDoc) => send<{ data: ChannelDoc }>('post', '/notification-channels', { channel }).then((r) => r.data),
  save: (uuid: string, channel: ChannelDoc, baseHash: string) => send<{ data: ChannelDoc }>('put', `/notification-channels/${uuid}`, { channel, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/notification-channels/${uuid}`),
  test: (uuid: string, to: string | null) => send<{ data: { ok: boolean; answer: string } }>('post', `/notification-channels/${uuid}/test`, { to }).then((r) => r.data),
  deliveries: (uuid: string, page: number, status: string | null) =>
    get<{ data: ChannelDelivery[]; meta: { total: number } }>(`/notification-channels/${uuid}/deliveries`, { page, ...(status ? { status } : {}) }),
  retry: (uuid: string, id: number) => send('post', `/notification-channels/${uuid}/deliveries/${id}/retry`),
}
