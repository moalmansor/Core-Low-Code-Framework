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
  // An identifier handed to a message as a parameter: t('…', { key }), t('…', { name: s.key }), { status: x.uuid } (the designer's warnings leaked this way).
  // (A key inside the message name itself, t(`admin.area.${a.key}`), only picks which message to show.)
  [/\bt\(\s*([`'"])[^`'"]*\1\s*,\s*\{[^}]*(\b(key|uuid|id)\s*[,}]|:\s*[\w.?!]+\.(key|uuid|id)\b)/, 'passes an identifier to a message'],
  // An identifier printed in a template, unless it is shown on purpose as a code (class "ltr-value" on the same line).
  // `errors.key` is the validation message of a Key input, not a key.
  [/^(?!.*ltr-value).*\{\{\s*[\w.?!]*(?<![eE]rrors)\.(key|uuid)\s*\}\}/, 'prints an identifier'],
]

/** Deliberate uses, each with its reason: not shown to users, or a technical admin view of storage. */
const ALLOWED: Record<string, string> = {
  'builder/document.ts': 'builds unique keys from keys; not displayed',
  'views/admin/TranslationsView.vue': 'row identity for the table; not displayed',
  'builder/DiffView.vue': 'the version diff for administrators shows the definition paths it compares',
  'views/admin/building/MigrationPlans.vue': 'the backup location on disk, for operators',
}

/** Messages that are about the identifier itself (an administrator editing a key), with their reason. */
const ALLOWED_PLACEHOLDERS: Record<string, string> = {
  'builder.publish.type_key': 'the administrator types the form key to confirm a destructive publish',
}

describe('the rules catch the leaks they were written for', () => {
  it.each([
    "t('formconfig.workflow.dead_end', { name: s.key })",
    "t('x.y', { status: t.uuid, n: 2 })",
    "t(`x.y`, { key })",
    '<span>{{ field.key }}</span>',
    'const label = props.label ?? field.key',
    'titles[u] ?? u)',
  ])('%s', (line) => {
    expect(LEAKS.some(([re]) => re.test(line))).toBe(true)
  })
  it.each(["t(`admin.area.${a.key}`)", '<span class="field-error">{{ errors.key }}</span>', '<code class="ltr-value">{{ s.key }}</code>'])('%s is not a leak', (line) => {
    expect(LEAKS.some(([re]) => re.test(line))).toBe(false)
  })
})

describe('message catalogs never ask for an identifier', () => {
  // A {key}, {uuid} or {id} placeholder invites a caller to fill it with an identifier.
  const catalogs = join(__dirname, '../../../backend/resources/ui-strings/en')
  for (const name of readdirSync(catalogs)) {
    it(name, () => {
      const messages = JSON.parse(readFileSync(join(catalogs, name), 'utf8')) as Record<string, string>
      const found = Object.entries(messages)
        .filter(([key, text]) => /\{(key|uuid|id)\}/.test(text) && !ALLOWED_PLACEHOLDERS[key])
        .map(([key, text]) => `${key}: ${text}`)
      expect(found).toEqual([])
    })
  }
})

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
