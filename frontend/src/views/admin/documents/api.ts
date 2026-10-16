import { get, send } from '@/api/http'
import type { I18nMap, Placeholders, TemplateBlock } from '../notifications/api'

/** Document templates (specification §4.17). */
export type DocKind = 'docx' | 'html'
export type DocFormat = 'docx' | 'pdf'
export interface Paper {
  size: 'a4' | 'a3' | 'a5' | 'letter' | 'legal'
  orientation: 'portrait' | 'landscape'
  margins: { top: number; right: number; bottom: number; left: number }
}
export interface WordFile {
  uuid: string
  name: string
  size: number
}
/** A designed template's design: its accent, title, running header and footer, and blocks. */
export interface DocumentDesign {
  style: { accent: string | null }
  title: I18nMap
  header: { enabled: boolean; logo: boolean; text: I18nMap }
  footer: { enabled: boolean; text: I18nMap; pageNumbers: 'none' | 'page' | 'page_of' }
  blocks: TemplateBlock[]
}
export const emptyDesign = (): DocumentDesign => ({
  style: { accent: null },
  title: {},
  header: { enabled: false, logo: true, text: {} },
  footer: { enabled: false, text: {}, pageNumbers: 'page_of' },
  blocks: [],
})
/** A loaded design with every part present (older templates have no title, header or footer). */
export function fullDesign(d: Partial<DocumentDesign> | null | undefined): DocumentDesign {
  const e = emptyDesign()
  return {
    style: { ...e.style, ...(d?.style ?? {}) },
    title: { ...(d?.title ?? {}) },
    header: { ...e.header, ...(d?.header ?? {}) },
    footer: { ...e.footer, ...(d?.footer ?? {}) },
    blocks: d?.blocks ?? [],
  }
}
export interface DocumentDoc {
  uuid?: string
  key: string
  type: DocKind
  form: string | null
  name: I18nMap
  design: DocumentDesign | null
  files: Record<string, WordFile | null>
  formats: DocFormat[]
  paper: Paper
  placeholders?: string[]
  issues?: string[]
  active: boolean
  hash?: string
}
export interface DocumentSummary {
  uuid: string
  key: string
  type: DocKind
  active: boolean
  formats: DocFormat[]
  name: string
  form: string | null
  form_name: string | null
  used_by: number
}
export interface UploadResult extends WordFile {
  placeholders: string[]
  unknown: string[]
}

/** What the server stores: Word files by their id. */
function body(doc: DocumentDoc): Record<string, unknown> {
  return {
    key: doc.key,
    type: doc.type,
    form: doc.form,
    name: doc.name,
    design: doc.type === 'html' ? doc.design : null,
    files: Object.fromEntries(Object.entries(doc.files).flatMap(([l, f]) => (f ? [[l, f.uuid]] : []))),
    formats: doc.formats,
    paper: doc.paper,
    active: doc.active,
  }
}

export const documentsApi = {
  list: (form?: string | null) =>
    get<{ data: DocumentSummary[]; meta: { pdf_from_word: boolean } }>('/documents/templates', form ? { form } : {}).then((r) => ({ rows: r.data, pdfFromWord: r.meta.pdf_from_word })),
  load: (uuid: string) => get<{ data: DocumentDoc }>(`/documents/templates/${uuid}`).then((r) => r.data),
  create: (doc: DocumentDoc) => send<{ data: DocumentDoc }>('post', '/documents/templates', body(doc)).then((r) => r.data),
  save: (uuid: string, doc: DocumentDoc, hash: string) => send<{ data: DocumentDoc }>('put', `/documents/templates/${uuid}`, { ...body(doc), base_hash: hash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/documents/templates/${uuid}`),
  upload: (file: File, form: string | null) => {
    const data = new FormData()
    data.append('file', file)
    if (form) data.append('form', form)
    return send<{ data: UploadResult }>('post', '/documents/uploads', data).then((r) => r.data)
  },
  preview: (doc: DocumentDoc, record: string | null, locale: string) =>
    send<{ data: { url: string } }>('post', '/documents/templates/preview', { form: doc.form, record, design: doc.design, paper: doc.paper, name: doc.name, locale }).then((r) => r.data.url),
  generate: (uuid: string, record: string | null, format: DocFormat, locale: string) =>
    send<{ data: { name: string; size: number; url: string } }>('post', `/documents/templates/${uuid}/generate`, { record, format, locale }).then((r) => r.data),
  placeholders: (form: string | null) => get<{ data: Placeholders }>('/documents/placeholders', form ? { form } : {}).then((r) => r.data),
}

/** Document templates as blueprints (specification §4.30). */
export interface DocumentBlueprint {
  uuid: string
  name: string
  description: string | null
  version: number | null
}
export const documentBlueprintsApi = {
  list: () => get<{ data: DocumentBlueprint[] }>('/blueprints', { kind: 'document_template' }).then((r) => r.data),
  save: (template: string, name: I18nMap, description: I18nMap) =>
    send<{ data: { uuid: string } }>('post', '/blueprints', { source: template, source_type: 'document_template', name, description }).then((r) => r.data.uuid),
  instantiate: (blueprint: string, form: string | null, key: string, name: I18nMap) =>
    send<{ data: { uuid: string; key: string } }>('post', `/blueprints/${blueprint}/instantiate`, { form, key, name }).then((r) => r.data),
}
