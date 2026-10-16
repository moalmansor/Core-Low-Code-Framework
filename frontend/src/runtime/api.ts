import { ensureCsrf, get, http } from '@/api/http'
import type { FileMeta } from './types'

/** Thin typed calls of the records runtime API (architecture §21.2). */

export interface OptionItem {
  value: string
  label: string
  group?: string | null
  color?: string | null
  icon?: string | null
  parent?: string | null
  uuid?: string
  preview?: Record<string, unknown>
  /** Field labels for the preview values, by key. */
  previewLabels?: Record<string, string>
}

export function newUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID()
  const bytes = new Uint8Array(16)
  crypto.getRandomValues(bytes)
  bytes[6] = (bytes[6]! & 0x0f) | 0x40
  bytes[8] = (bytes[8]! & 0x3f) | 0x80
  const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('')
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

export async function fetchOptions(form: string, field: string, params: { q?: string; depends?: string | null; page?: number; uuids?: string[] }): Promise<{ items: OptionItem[]; total: number }> {
  const res = await get<{ data: OptionItem[]; meta: { total: number } }>(`/r/${form}/options/${field}`, {
    q: params.q || undefined,
    depends: params.depends ?? undefined,
    page: params.page,
    uuids: params.uuids,
  })
  return { items: res.data, total: res.meta.total }
}

export async function uploadFile(file: Blob, name: string, form?: string, field?: string): Promise<FileMeta> {
  await ensureCsrf()
  const body = new FormData()
  body.append('file', file, name)
  if (form && field) {
    body.append('form', form)
    body.append('field', field)
  }
  return (await http.post<{ data: FileMeta }>('/files', body, { timeout: 300000 })).data.data
}

/** A short-lived signed download URL (GET /files/{uuid}/url). */
export async function fileUrl(uuid: string): Promise<string> {
  return (await get<{ data: { url: string } }>(`/files/${uuid}/url`)).data.url
}

export async function openFile(uuid: string): Promise<void> {
  const url = await fileUrl(uuid)
  window.open(url, '_blank', 'noopener')
}

/** Downloads a binary response as a file named by Content-Disposition. */
export async function downloadBlob(url: string, params: Record<string, unknown>, fallbackName: string): Promise<void> {
  const res = await http.get<Blob>(url, { params, responseType: 'blob', timeout: 300000 })
  const disposition = String(res.headers['content-disposition'] ?? '')
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)
  const name = match ? decodeURIComponent(match[1]!) : fallbackName
  const href = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = href
  a.download = name
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(href), 1000)
}
