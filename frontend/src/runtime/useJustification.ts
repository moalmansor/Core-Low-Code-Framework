import { ref } from 'vue'
import { justificationPrompt, type JustificationPayload, type JustificationPrompt } from './workflow/types'

/**
 * Runs a write that may need a justification: the first attempt goes without
 * one; a 422 `justification_required` opens the dialog with the server's
 * prompt, and the write is retried with what the user entered until it
 * passes, fails otherwise, or the user cancels (the promise then resolves to
 * `null`). Bind `prompt`, `errors`, `busy`, `submit` and `cancel` to a
 * JustificationDialog.
 */
export function useJustification() {
  const prompt = ref<JustificationPrompt | null>(null)
  const errors = ref<Record<string, string>>({})
  const busy = ref(false)
  let pending: { attempt: (j: JustificationPayload | null) => Promise<unknown>; resolve: (v: unknown) => void; reject: (e: unknown) => void } | null = null

  async function run<T>(attempt: (justification: JustificationPayload | null) => Promise<T>): Promise<T | null> {
    try {
      return await attempt(null)
    } catch (e) {
      const asked = justificationPrompt(e)
      if (!asked) throw e
      prompt.value = asked.prompt
      errors.value = asked.errors
      return new Promise<T | null>((resolve, reject) => {
        pending = { attempt, resolve: resolve as (v: unknown) => void, reject }
      })
    }
  }

  async function submit(payload: JustificationPayload): Promise<void> {
    if (!pending) return
    busy.value = true
    try {
      const result = await pending.attempt(payload)
      const done = pending
      pending = null
      prompt.value = null
      errors.value = {}
      done.resolve(result)
    } catch (e) {
      const asked = justificationPrompt(e)
      if (asked) {
        prompt.value = asked.prompt
        errors.value = asked.errors
      } else {
        const done = pending
        pending = null
        prompt.value = null
        done?.reject(e)
      }
    } finally {
      busy.value = false
    }
  }

  function cancel(): void {
    const done = pending
    pending = null
    prompt.value = null
    errors.value = {}
    done?.resolve(null)
  }

  return { prompt, errors, busy, run, submit, cancel }
}
