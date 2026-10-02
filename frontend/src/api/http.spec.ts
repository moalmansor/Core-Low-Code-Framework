import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import { ApiError } from './http'
import { withStepUp } from './stepup'

describe('ApiError', () => {
  it('exposes the first message per field', () => {
    const e = new ApiError(422, { message: 'bad', errors: { email: ['taken', 'other'], name: ['required'] } })
    expect(e.fieldErrors).toEqual({ email: 'taken', name: 'required' })
    expect(e.status).toBe(422)
  })
})

describe('withStepUp', () => {
  const stepUpError = new ApiError(422, { errors: { confirmation_code: ['needed'] } })

  it('asks for a code and retries with it', async () => {
    const ask = vi.fn().mockResolvedValue('123456')
    const run = vi.fn().mockRejectedValueOnce(stepUpError).mockResolvedValueOnce('ok')
    await expect(withStepUp(ref({ ask }), run)).resolves.toBe('ok')
    expect(run).toHaveBeenLastCalledWith('123456')
  })

  it('stops when the user cancels', async () => {
    const run = vi.fn().mockRejectedValue(stepUpError)
    await expect(withStepUp(ref({ ask: vi.fn().mockResolvedValue(null) }), run)).resolves.toBeNull()
    expect(run).toHaveBeenCalledTimes(1)
  })

  it('rethrows other errors', async () => {
    const run = vi.fn().mockRejectedValue(new ApiError(403, { message: 'no' }))
    await expect(withStepUp(ref({ ask: vi.fn() }), run)).rejects.toBeInstanceOf(ApiError)
  })
})
