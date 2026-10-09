import axios, { AxiosError } from 'axios'

/**
 * The only HTTP client of the SPA. Same-origin cookie session (Sanctum), CSRF
 * via the XSRF-TOKEN cookie, the active locale and a correlation ID on every
 * request. Errors are normalised into ApiError.
 */
export const http = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  timeout: 30000,
})

let currentLocale = 'en'
export function setRequestLocale(locale: string): void {
  currentLocale = locale
}

http.interceptors.request.use((config) => {
  config.headers.set('X-Locale', currentLocale)
  return config
})

export interface ApiErrorBody {
  message?: string
  code?: string
  reference?: string | null
  correlation_id?: string
  errors?: Record<string, string[]>
}

export class ApiError extends Error {
  readonly status: number
  readonly body: ApiErrorBody

  constructor(status: number, body: ApiErrorBody) {
    super(body.message ?? `HTTP ${status}`)
    this.status = status
    this.body = body
  }

  get code(): string | undefined {
    return this.body.code
  }

  /** First validation message per field. */
  get fieldErrors(): Record<string, string> {
    const out: Record<string, string> = {}
    for (const [k, v] of Object.entries(this.body.errors ?? {})) out[k] = v[0] ?? ''
    return out
  }
}

type Listener = (error: ApiError) => void
const listeners: Listener[] = []
/** Global reactions (redirect to login, 2FA enrollment, error toast). */
export function onApiError(listener: Listener): void {
  listeners.push(listener)
}

http.interceptors.response.use(
  (r) => r,
  (e: AxiosError<ApiErrorBody>) => {
    const err = new ApiError(e.response?.status ?? 0, e.response?.data ?? { message: e.message })
    listeners.forEach((l) => l(err))
    return Promise.reject(err)
  },
)

/** Fetch the CSRF cookie before the first state-changing call of a session. */
let csrfReady: Promise<unknown> | null = null
export function ensureCsrf(): Promise<unknown> {
  csrfReady ??= axios.get('/sanctum/csrf-cookie', { withCredentials: true })
  return csrfReady
}
export function resetCsrf(): void {
  csrfReady = null
}

export async function get<T>(url: string, params?: Record<string, unknown>): Promise<T> {
  return (await http.get<T>(url, { params })).data
}
export async function send<T>(method: 'post' | 'put' | 'patch' | 'delete', url: string, data?: unknown, options?: { headers?: Record<string, string> }): Promise<T> {
  await ensureCsrf()
  return (await http.request<T>({ method, url, data, headers: options?.headers })).data
}
