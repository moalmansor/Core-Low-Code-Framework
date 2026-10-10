import { describe, expect, it } from 'vitest'
import { ApiError } from '@/api/http'
import { useJustification } from './useJustification'
import type { JustificationPayload, JustificationPrompt } from './workflow/types'

const prompt: JustificationPrompt = {
  level: 'mandatory',
  title: 'Why?',
  help: null,
  text: { min: 5, max: 200, required: true },
  reason_codes: { mode: 'none', options: [], collections: [] },
  attachments: { mode: 'none', max: 10, rules: null },
  changes: [],
}
const required = () => new ApiError(422, { message: 'needed', code: 'justification_required', justification: prompt } as never)
const invalid = () => new ApiError(422, { message: 'bad', code: 'justification_invalid', errors: { reason_text: ['Too short'] }, justification: prompt } as never)
const payload = (text: string): JustificationPayload => ({ reason_text: text, reason_code: null, note: null, attachments: [] })

describe('useJustification', () => {
  it('passes straight through when no justification is asked for', async () => {
    const j = useJustification()
    await expect(j.run(async () => 'ok')).resolves.toBe('ok')
    expect(j.prompt.value).toBeNull()
  })

  it('asks, shows server errors, and retries with what the user entered', async () => {
    const j = useJustification()
    const sent: (JustificationPayload | null)[] = []
    const result = j.run(async (given) => {
      sent.push(given)
      if (!given) throw required()
      if ((given.reason_text ?? '').length < 5) throw invalid()
      return 'saved'
    })
    await Promise.resolve()
    await Promise.resolve()
    expect(j.prompt.value?.title).toBe('Why?')
    await j.submit(payload('no'))
    expect(j.errors.value).toEqual({ reason_text: 'Too short' })
    expect(j.prompt.value).not.toBeNull()
    await j.submit(payload('a good reason'))
    await expect(result).resolves.toBe('saved')
    expect(j.prompt.value).toBeNull()
    expect(sent.map((s) => s?.reason_text ?? null)).toEqual([null, 'no', 'a good reason'])
  })

  it('resolves to null when the user cancels, and rethrows other errors', async () => {
    const j = useJustification()
    const cancelled = j.run(async (given) => {
      if (!given) throw required()
      return 'never'
    })
    await Promise.resolve()
    await Promise.resolve()
    j.cancel()
    await expect(cancelled).resolves.toBeNull()
    await expect(j.run(async () => Promise.reject(new ApiError(409, { message: 'conflict', code: 'conflict' })))).rejects.toBeInstanceOf(ApiError)
  })
})
