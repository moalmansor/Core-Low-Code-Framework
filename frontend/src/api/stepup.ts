import type { Ref } from 'vue'
import { ApiError } from './http'

type Asker = { ask: (hint?: string) => Promise<string | null> }

/**
 * Run a request; if the server asks for step-up confirmation, prompt for a
 * code and retry with it (at most three attempts).
 */
export async function withStepUp<T>(dialog: Ref<Asker | null>, run: (code?: string) => Promise<T>): Promise<T | null> {
  let code: string | undefined
  let hint = ''
  for (let attempt = 0; attempt < 4; attempt++) {
    try {
      return await run(code)
    } catch (e) {
      if (!(e instanceof ApiError) || e.status !== 422 || !e.fieldErrors.confirmation_code) throw e
      if (attempt > 0) hint = e.fieldErrors.confirmation_code
      const answer = await dialog.value?.ask(hint)
      if (!answer) return null
      code = answer
    }
  }
  return null
}
