import { get } from '@/api/http'
import type { InjectionKey, Ref } from 'vue'

/**
 * Client extensions at run time (specification §4.19, ADR-0051). Each runs
 * in a frame with `sandbox="allow-scripts"` — an opaque origin, so it has no
 * session, no cookies and no access to this page — whose own policy allows
 * no network. The page talks to it only by messages, and only messages from
 * that frame are read. What it answers is advice: the server still decides
 * every save.
 */
export interface ClientExtension {
  uuid: string
  key: string
  type: 'client_validator' | 'client_field' | 'form_script'
  version: string
  label: string
  frame: string
}

export const CLIENT_EXTENSIONS: InjectionKey<Ref<ClientExtension[]>> = Symbol('client-extensions')

export async function loadClientExtensions(form: string): Promise<ClientExtension[]> {
  return (await get<{ data: ClientExtension[] }>(`/forms/${form}/client-extensions`).catch(() => ({ data: [] as ClientExtension[] }))).data
}

type Message = { lcf?: number; type?: string; id?: number; [k: string]: unknown }

/** One sandboxed frame and the requests sent to it. */
export class FrameHost {
  readonly iframe: HTMLIFrameElement
  private ready: Promise<void>
  private markReady!: () => void
  private next = 1
  private pending = new Map<number, (m: Message) => void>()
  private listeners: ((m: Message) => void)[] = []
  private readonly onMessage = (e: MessageEvent) => {
    if (e.source !== this.iframe.contentWindow) return
    const m = e.data as Message
    if (!m || m.lcf !== 1) return
    if (m.type === 'ready') this.markReady()
    if (typeof m.id === 'number' && this.pending.has(m.id)) {
      this.pending.get(m.id)!(m)
      this.pending.delete(m.id)
    }
    for (const l of this.listeners) l(m)
  }

  constructor(url: string, title: string, hidden: boolean) {
    this.iframe = document.createElement('iframe')
    this.iframe.setAttribute('sandbox', 'allow-scripts')
    this.iframe.setAttribute('referrerpolicy', 'no-referrer')
    this.iframe.title = title
    this.iframe.src = url
    if (hidden) {
      this.iframe.setAttribute('aria-hidden', 'true')
      this.iframe.tabIndex = -1
      Object.assign(this.iframe.style, { position: 'absolute', width: '0', height: '0', border: '0', visibility: 'hidden' })
    }
    this.ready = new Promise((resolve) => (this.markReady = resolve))
    window.addEventListener('message', this.onMessage)
  }

  on(listener: (m: Message) => void): void {
    this.listeners.push(listener)
  }

  async post(message: Message): Promise<void> {
    await this.ready
    this.iframe.contentWindow?.postMessage(message, '*')
  }

  /** A request answered by the frame, or null when it does not answer in time. */
  async request(message: Message, timeoutMs = 3000): Promise<Message | null> {
    const id = this.next++
    const answer = new Promise<Message | null>((resolve) => {
      this.pending.set(id, resolve)
      setTimeout(() => {
        if (this.pending.delete(id)) resolve(null)
      }, timeoutMs)
    })
    await Promise.race([this.ready, new Promise((r) => setTimeout(r, timeoutMs))])
    this.iframe.contentWindow?.postMessage({ ...message, id }, '*')
    return answer
  }

  dispose(): void {
    window.removeEventListener('message', this.onMessage)
    this.iframe.remove()
    this.pending.clear()
  }
}

/** The validators and scripts of a form, each in its own hidden frame. */
export class FormExtensions {
  private hosts: { ext: ClientExtension; host: FrameHost }[] = []

  constructor(list: ClientExtension[], locale: string, dir: 'ltr' | 'rtl') {
    for (const ext of list.filter((e) => e.type !== 'client_field')) {
      const host = new FrameHost(ext.frame, ext.label, true)
      document.body.appendChild(host.iframe)
      void host.post({ type: 'init', locale, dir })
      this.hosts.push({ ext, host })
    }
  }

  get empty(): boolean {
    return this.hosts.length === 0
  }

  /** Field errors from every validator: { field_key: message }. */
  async validate(values: Record<string, unknown>): Promise<Record<string, string>> {
    const out: Record<string, string> = {}
    const answers = await Promise.all(this.hosts.filter((h) => h.ext.type === 'client_validator').map((h) => h.host.request({ type: 'validate', values: plain(values) })))
    for (const a of answers) {
      for (const [k, v] of Object.entries((a?.errors as Record<string, unknown>) ?? {})) if (typeof v === 'string' && v && !out[k]) out[k] = v
    }
    return out
  }

  /** What the form scripts set and say after a change. */
  async change(values: Record<string, unknown>, changed: string[]): Promise<{ set: Record<string, unknown>; messages: { text: string; severity: string }[] }> {
    const set: Record<string, unknown> = {}
    const messages: { text: string; severity: string }[] = []
    const answers = await Promise.all(this.hosts.filter((h) => h.ext.type === 'form_script').map((h) => h.host.request({ type: 'change', values: plain(values), changed })))
    for (const a of answers) {
      Object.assign(set, (a?.set as Record<string, unknown>) ?? {})
      for (const m of (a?.messages as { text?: unknown; severity?: unknown }[]) ?? []) {
        if (typeof m?.text === 'string' && m.text) messages.push({ text: m.text.slice(0, 500), severity: m.severity === 'error' || m.severity === 'warn' ? m.severity : 'info' })
      }
    }
    return { set, messages }
  }

  dispose(): void {
    for (const h of this.hosts) h.host.dispose()
    this.hosts = []
  }
}

function plain(v: unknown): unknown {
  try {
    return JSON.parse(JSON.stringify(v ?? null))
  } catch {
    return null
  }
}
