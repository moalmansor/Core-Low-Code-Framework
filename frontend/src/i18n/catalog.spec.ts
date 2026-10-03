import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { bundled } from './bundled'

describe('interface catalogs', () => {
  it('have the same keys in Arabic and English', () => {
    expect(Object.keys(bundled.ar).sort()).toEqual(Object.keys(bundled.en).sort())
  })

  it('have no empty strings and no vue-i18n special characters', () => {
    for (const [locale, messages] of Object.entries(bundled)) {
      for (const [key, value] of Object.entries(messages)) {
        expect(value.trim(), `${locale}:${key}`).not.toBe('')
        expect(value, `${locale}:${key}`).not.toMatch(/[@|]/)
      }
    }
  })

  it('cover every static key used in the source', () => {
    const files: string[] = []
    const walk = (dir: string) => {
      for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name)
        if (entry.isDirectory()) walk(path)
        else if (/\.(vue|ts)$/.test(entry.name) && !entry.name.endsWith('.spec.ts')) files.push(path)
      }
    }
    walk(join(__dirname, '..'))
    const missing = new Set<string>()
    for (const file of files) {
      for (const m of readFileSync(file, 'utf8').matchAll(/\bt\('([a-z_]+(?:\.[a-z0-9_]+)+)'/g)) {
        if (!(m[1]! in bundled.en)) missing.add(m[1]!)
      }
    }
    expect([...missing]).toEqual([])
  })
})
