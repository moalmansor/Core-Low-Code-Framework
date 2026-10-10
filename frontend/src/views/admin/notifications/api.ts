import { get, send } from '@/api/http'
import type { Ast } from '@/builder/types'

/** Email templates and their placeholders (specification §4.16). */
export type I18nMap = Record<string, string>
export type BlockType = 'heading' | 'text' | 'button' | 'fields' | 'table' | 'divider' | 'spacer'
export interface TemplateBlock {
  uuid: string
  type: BlockType
  text?: I18nMap
  label?: I18nMap
  link?: 'record' | 'url'
  url?: string | null
  fields?: string[]
  group?: string
  columns?: string[]
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
