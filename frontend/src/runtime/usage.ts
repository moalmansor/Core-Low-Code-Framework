/**
 * Usage insight (specification §4.37, ADR-0066): the screens report when a
 * form is viewed, when someone starts filling one in, and when they leave
 * without submitting. Events are sent in batches; submissions and empty
 * fields are counted on the server. Reporting never gets in the way: a
 * failure is dropped.
 */
export type UsageMetric = 'form_view' | 'form_start' | 'form_abandon'

const queue: { metric: UsageMetric; form: string }[] = []
let timer: ReturnType<typeof setTimeout> | undefined

export function track(metric: UsageMetric, form: string | null | undefined): void {
  if (!form) return
  queue.push({ metric, form })
  if (queue.length >= 40) flushUsage()
  else timer ??= setTimeout(() => flushUsage(), 10_000)
}

/** Sends what is queued; `keepalive` lets it finish while the page closes. */
export function flushUsage(keepalive = false): void {
  clearTimeout(timer)
  timer = undefined
  if (!queue.length || typeof fetch === 'undefined') return
  const events = queue.splice(0, 50)
  const token = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie)?.[1]
  void fetch('/api/v1/usage', {
    method: 'POST',
    credentials: 'same-origin',
    keepalive,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}) },
    body: JSON.stringify({ events }),
  }).catch(() => undefined)
}

if (typeof document !== 'undefined') {
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') flushUsage(true)
  })
}
