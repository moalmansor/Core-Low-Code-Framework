import { get, send } from '@/api/http'

/** Developer extensions (specification §4.19, ADR-0051). */
export type ExtType = 'server_hook' | 'api_endpoint' | 'client_validator' | 'client_field' | 'form_script'
export const EXT_TYPES: ExtType[] = ['server_hook', 'api_endpoint', 'client_validator', 'client_field', 'form_script']
export type ExtStatus = 'draft' | 'testing' | 'pending_approval' | 'approved' | 'deployed' | 'disabled' | 'rejected'

export interface ExtSummary {
  uuid: string
  key: string
  type: ExtType
  hook_point: string | null
  status: ExtStatus
  timeout_ms: number
  form: string | null
  form_name: string | null
  version: number | null
  deployed_version: number | null
}
export interface TestReport {
  ok: boolean
  values?: Record<string, unknown>
  changed?: string[]
  errors?: Record<string, string[]>
  logs: string[]
  refused?: string | null
  error?: string | null
  duration_ms: number
  answer?: unknown
  compiled?: boolean
  bytes?: number
  checksum?: string
  at?: string
}
export interface ExtVersion {
  uuid: string
  version: number
  checksum: string
  test_status: 'not_run' | 'passed' | 'failed'
  test_report: TestReport | null
  author: string | null
  approved_by: string | null
  approved_at: string | null
  deployed_at: string | null
  rejected_reason: string | null
  created_at: string | null
  deployed?: boolean
}
export interface ExtDetail extends ExtSummary {
  description: string | null
  permission: string | null
  label: Record<string, string>
  source: string
  current: ExtVersion | null
  versions: ExtVersion[]
  can_approve: boolean
}
export interface ExtDoc {
  key: string
  type: ExtType
  hook_point: string | null
  form: string | null
  permission: string | null
  timeout_ms: number
  description: string
  label: Record<string, string>
  source: string
}
export interface ExtMeta {
  points: string[]
  permissions: string[]
  timeout: { min: number; max: number }
}

export const extensionsApi = {
  async list(): Promise<{ rows: ExtSummary[]; canWrite: boolean; canApprove: boolean }> {
    const r = await get<{ data: ExtSummary[]; meta: { can_write: boolean; can_approve: boolean } }>('/extensions')
    return { rows: r.data, canWrite: r.meta.can_write, canApprove: r.meta.can_approve }
  },
  meta: async () => (await get<{ data: ExtMeta }>('/extensions/meta')).data,
  get: async (uuid: string) => (await get<{ data: ExtDetail }>(`/extensions/${uuid}`)).data,
  create: async (d: ExtDoc) => (await send<{ data: ExtDetail }>('post', '/extensions', d)).data,
  // Key and kind are fixed once made: they are not sent again.
  save: async (uuid: string, d: ExtDoc) => (await send<{ data: ExtDetail }>('put', `/extensions/${uuid}`, Object.fromEntries(Object.entries(d).filter(([k]) => k !== 'key' && k !== 'type')))).data,
  test: async (uuid: string, input: { record?: string | null; values?: Record<string, unknown> | null; extra?: Record<string, unknown> | null; body?: Record<string, unknown> | null }) =>
    (await send<{ data: { report: TestReport; extension: ExtDetail } }>('post', `/extensions/${uuid}/test`, input)).data,
  submit: async (uuid: string) => (await send<{ data: ExtDetail }>('post', `/extensions/${uuid}/submit`)).data,
  approve: async (uuid: string) => (await send<{ data: ExtDetail }>('post', `/extensions/${uuid}/approve`)).data,
  reject: async (uuid: string, reason: string) => (await send<{ data: ExtDetail }>('post', `/extensions/${uuid}/reject`, { reason })).data,
  deploy: async (uuid: string, version?: string) => (await send<{ data: ExtDetail }>('post', `/extensions/${uuid}/deploy`, version ? { version } : {})).data,
  disable: async (uuid: string, off: boolean) => (await send<{ data: ExtDetail }>('post', `/extensions/${uuid}/disable`, { off })).data,
  remove: (uuid: string) => send<void>('delete', `/extensions/${uuid}`),
  version: async (uuid: string, version: string) => (await get<{ data: ExtVersion & { source: string } }>(`/extensions/${uuid}/versions/${version}`)).data,
}

/** Code a new extension starts from, by type. */
export const STARTERS: Record<ExtType, string> = {
  server_hook: `final class Hook implements ServerHook
{
    public function handle(HookContext $ctx): void
    {
        // Read a value: $ctx->get('field_key'); change one (before saving): $ctx->set('field_key', $value);
        // Refuse with a field error: $ctx->error('field_key', 'Message'); refuse everything: $ctx->fail('Message');
    }
}
`,
  api_endpoint: `final class Endpoint implements ApiEndpoint
{
    public function handle(ApiRequest $request): array
    {
        // $request->input('name'), $request->records()->list('form_key'), $request->user()
        return ['ok' => true];
    }
}
`,
  client_validator: `lcf.validator((values) => {
  // Return { field_key: 'message' } for each problem, or {} when everything is fine.
  return {}
})
`,
  client_field: `import { createApp, h, ref } from 'vue'

lcf.field({
  mount(el, api) {
    const value = ref(String(api.value ?? ''))
    createApp({
      render: () =>
        h('input', {
          value: value.value,
          disabled: api.readonly,
          dir: api.dir,
          style: 'width: 100%; padding: 6px',
          onInput: (e: Event) => {
            value.value = (e.target as HTMLInputElement).value
            api.setValue(value.value)
          },
        }),
    }).mount(el)
  },
})
`,
  form_script: `lcf.script({
  onChange(values, changed) {
    // Return { set: { field_key: value }, messages: [{ text: '...', severity: 'info' }] }
    return {}
  },
})
`,
}

export const isServer = (t: ExtType) => t === 'server_hook' || t === 'api_endpoint'
export const needsForm = (t: ExtType) => t === 'server_hook' || t === 'client_validator' || t === 'form_script'

export function statusSeverity(s: ExtStatus): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  return s === 'deployed' ? 'success' : s === 'pending_approval' ? 'warn' : s === 'rejected' ? 'danger' : s === 'approved' || s === 'testing' ? 'info' : 'secondary'
}
