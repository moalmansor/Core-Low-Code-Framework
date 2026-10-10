import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * Internal identifiers never reach the interface (design system §5.6): a
 * missing label falls back through `labelOf` / `humanize`, never to a raw
 * key, UUID or id. This scans the source for the fallbacks that leaked
 * before (`?? field.key`, `?? uuid`, `titles[u] ?? u`, raw error paths).
 */
function files(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return files(path)
    return /\.(vue|ts)$/.test(name) && !name.endsWith('.spec.ts') ? [path] : []
  })
}

const LEAKS: [RegExp, string][] = [
  [/(\?\?|\|\|) [\w.?]+\.key\b(?!\s*(===|!==|\)\s*=>))/, 'falls back to a key'],
  [/(\?\?|\|\|) (u|uuid|id|key)\s*(\}|\)|,|"|$)/, 'falls back to an identifier'],
  [/\{\{\s*[\w.]+\.path\s*\}\}/, 'shows a raw path'],
]

/** Deliberate uses, each with its reason: not shown to users, or a technical admin view of storage. */
const ALLOWED: Record<string, string> = {
  'builder/document.ts': 'builds unique keys from keys; not displayed',
  'views/admin/TranslationsView.vue': 'row identity for the table; not displayed',
  'builder/DiffView.vue': 'the version diff for administrators shows the definition paths it compares',
  'views/admin/building/MigrationPlans.vue': 'the backup location on disk, for operators',
}

describe('the interface never shows internal identifiers', () => {
  const root = join(__dirname, '..')
  for (const file of files(root)) {
    const rel = file.slice(root.length + 1)
    it(rel, () => {
      const lines = readFileSync(file, 'utf8').split('\n')
      const found = lines.flatMap((line, i) => (ALLOWED[rel] || ALLOWED[`${rel}:${i + 1}`] ? [] : LEAKS.filter(([re]) => re.test(line)).map(([, what]) => `${rel}:${i + 1} ${what}: ${line.trim()}`)))
      expect(found).toEqual([])
    })
  }
})
