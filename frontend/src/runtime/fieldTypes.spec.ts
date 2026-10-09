import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { componentFor, hasComponent } from './fields'
import { FIELD_TYPES } from './fieldTypes'

const php = readFileSync(join(__dirname, '../../../backend/app/Modules/Forms/FieldTypes/FieldTypeRegistry.php'), 'utf8')
const entries = [...php.matchAll(/\$t\('([a-z_]+)', '([a-z]+)', '([a-z_]+)', '([a-z]+)'(.*?)\),\n/g)].map((m) => ({
  key: m[1]!,
  category: m[2]!,
  storage: m[3]!,
  valueType: m[4]!,
  options: m[5]!.includes("'options' => true"),
  multiple: m[5]!.includes("'multiple' => true"),
  filter: /'filter' => '([a-z]+)'/.exec(m[5]!)?.[1] ?? 'none',
  calculated: m[5]!.includes("'calculated' => true"),
}))

describe('field type registry mirror', () => {
  it('matches the server registry type for type', () => {
    expect(entries.length).toBeGreaterThan(70)
    expect(Object.keys(FIELD_TYPES).sort()).toEqual(entries.map((e) => e.key).sort())
    for (const e of entries) {
      const { key, ...info } = e
      expect(FIELD_TYPES[key], key).toEqual(info)
    }
  })

  it('renders every registry type (input component, or read-only/hidden by design)', () => {
    for (const key of Object.keys(FIELD_TYPES)) expect(hasComponent(key), key).toBe(true)
    expect(componentFor('no_such_type')).toBeNull()
  })
})
