import { describe, expect, it } from 'vitest'
import { sha256Hex, solve } from './pow'

describe('proof of work', () => {
  it('computes SHA-256 like the server', () => {
    expect(sha256Hex('')).toBe('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855')
    expect(sha256Hex('abc')).toBe('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad')
    // Two blocks.
    expect(sha256Hex('a'.repeat(64))).toBe('ffe054fe7ae0cb6dc65c3af9b61d5209f439851db43d0ba5997337df154668eb')
  })

  it('finds the number behind a challenge', async () => {
    const salt = '0123456789abcdef01234567?expires=1999999999'
    const challenge = sha256Hex(salt + 1234)
    const answer = await solve({ algorithm: 'SHA-256', challenge, maxnumber: 5000, salt, signature: 'sig' })
    expect(answer).toEqual({ salt, number: 1234, challenge, signature: 'sig' })
    expect(await solve({ algorithm: 'SHA-256', challenge: 'f'.repeat(64), maxnumber: 50, salt, signature: 'sig' })).toBeNull()
  })
})
