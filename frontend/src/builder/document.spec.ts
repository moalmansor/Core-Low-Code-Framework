import { describe, expect, it } from 'vitest'
import {
  canPlace,
  childrenOf,
  duplicateElements,
  extractFragment,
  flatten,
  groupFragment,
  insertFragment,
  isValidKey,
  locateIssue,
  moveElements,
  newField,
  normalizeDocument,
  removeElements,
  renameKey,
  shiftElement,
  toKey,
  uniqueKey,
} from './document'
import type { DraftDocument, FieldTypeInfo, GroupTypeInfo } from './types'

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/

// The group rules GET /field-types returns (FieldTypeRegistry).
const GROUPS: GroupTypeInfo[] = [
  { key: 'section', data: false, parents: null, children: null },
  { key: 'tabs', data: false, parents: null, children: 'tab' },
  { key: 'tab', data: false, parents: ['tabs'], children: null },
  { key: 'row', data: false, parents: null, children: 'column' },
  { key: 'column', data: false, parents: ['row'], children: null },
  { key: 'wizard', data: false, parents: null, children: 'step' },
  { key: 'step', data: false, parents: ['wizard'], children: null },
  { key: 'repeater', data: true, parents: null, children: null },
  { key: 'subform', data: true, parents: null, children: null },
]

const TEXT: FieldTypeInfo = {
  key: 'text',
  category: 'text',
  stored: true,
  storage: 'string',
  value_type: 'text',
  validation: ['required', 'length'],
  options: false,
  multiple: false,
  filter: 'text',
  native: true,
  icon: 'pi pi-pencil',
  calculated: false,
  defaults: { length: 255 },
}
const SELECT: FieldTypeInfo = { ...TEXT, key: 'select', storage: 'choice', options: true, filter: 'choice' }

function emptyDoc(): DraftDocument {
  return { form: { uuid: '11111111-1111-4111-8111-111111111111', key: 'leave', kind: 'form' }, groups: [], fields: [], relations: [], conditions: [] }
}

function field(doc: DraftDocument, info = TEXT, parent: string | null = null, index = 99): string {
  const f = newField(info, { en: 'Field' })
  return insertFragment(doc, GROUPS, { groups: [], fields: [f], conditions: [], relations: [] }, parent, index)![0]!
}

function group(doc: DraftDocument, type: Parameters<typeof groupFragment>[0], parent: string | null = null, index = 99): string {
  return insertFragment(
    doc,
    GROUPS,
    groupFragment(type, () => ({ en: type })),
    parent,
    index,
  )![0]!
}

/** The required members of each object kind in form-draft.schema.json. */
function assertSchemaShape(doc: DraftDocument): void {
  const keys = new Set<string>()
  for (const g of doc.groups) {
    expect(g.uuid).toMatch(UUID)
    expect(isValidKey(g.key)).toBe(true)
    expect(typeof g.type).toBe('string')
    expect(g.parent === null || UUID.test(g.parent)).toBe(true)
    expect(Number.isInteger(g.order) && g.order >= 0).toBe(true)
    expect(keys.has(g.key)).toBe(false)
    keys.add(g.key)
  }
  for (const f of doc.fields) {
    expect(f.uuid).toMatch(UUID)
    expect(isValidKey(f.key)).toBe(true)
    expect(f.type).toMatch(/^[a-z][a-z0-9_]{0,47}$/)
    expect(f.group === null || doc.groups.some((g) => g.uuid === f.group)).toBe(true)
    expect(Number.isInteger(f.order) && f.order >= 0).toBe(true)
    expect(keys.has(f.key)).toBe(false)
    keys.add(f.key)
  }
  for (const c of doc.conditions) {
    expect(c.uuid).toMatch(UUID)
    expect(['form', 'field', 'group', 'option']).toContain(c.owner.type)
    expect(c.when).toBeTruthy()
    expect(Array.isArray(c.effects)).toBe(true)
  }
  for (const r of doc.relations) for (const k of ['uuid', 'key', 'type', 'target', 'kind', 'onDelete'] as const) expect(r[k]).toBeTruthy()
  const all = [...doc.groups, ...doc.fields, ...doc.conditions].map((x) => x.uuid)
  expect(new Set(all).size).toBe(all.length)
}

describe('keys', () => {
  it('turns any text into a schema-valid snake_case key', () => {
    expect(toKey('Start Date')).toBe('start_date')
    expect(toKey('9 lives')).toBe('field_9_lives')
    expect(toKey('تاريخ')).toBe('field')
    expect(toKey('and')).toBe('and_value')
    expect(toKey('x'.repeat(80)).length).toBe(48)
  })

  it('suffixes keys until they are unique', () => {
    const taken = new Set(['amount', 'amount_2'])
    expect(uniqueKey('amount', taken)).toBe('amount_3')
    expect(uniqueKey('amount_2', taken)).toBe('amount_3')
    expect(uniqueKey('total', taken)).toBe('total')
  })
})

describe('document operations', () => {
  it('inserts fields and groups with fresh uuids, unique keys and consecutive orders', () => {
    const doc = emptyDoc()
    const a = field(doc)
    const b = field(doc)
    const s = group(doc, 'section', null, 0)
    expect(childrenOf(doc, null).map((r) => r.uuid)).toEqual([s, a, b])
    expect(doc.fields.map((f) => f.key)).toEqual(['text', 'text_2'])
    expect(doc.groups.find((g) => g.uuid === s)!.order).toBe(0)
    assertSchemaShape(doc)
  })

  it('adds the children structural containers require', () => {
    const doc = emptyDoc()
    const row = group(doc, 'row')
    expect(childrenOf(doc, row)).toHaveLength(2)
    expect(doc.groups.filter((g) => g.parent === row).every((g) => g.type === 'column')).toBe(true)
    assertSchemaShape(doc)
  })

  it('nests groups inside groups and refuses invalid nesting', () => {
    const doc = emptyDoc()
    const tabs = group(doc, 'tabs')
    const tab = childrenOf(doc, tabs)[0]!.uuid
    const section = group(doc, 'section', tab)
    const inner = field(doc, TEXT, section)
    expect(flatten(doc).map((r) => [r.uuid, r.depth])).toEqual([
      [tabs, 0],
      [tab, 1],
      [section, 2],
      [inner, 3],
    ])
    // Fields cannot sit directly in tabs; a tab only in tabs; repeaters not inside repeaters.
    expect(canPlace(doc, GROUPS, { kind: 'field', type: 'text' }, tabs)).toBe(false)
    expect(canPlace(doc, GROUPS, { kind: 'group', type: 'tab' }, null)).toBe(false)
    const rep = group(doc, 'repeater')
    expect(canPlace(doc, GROUPS, { kind: 'group', type: 'repeater' }, rep)).toBe(false)
    expect(canPlace(doc, GROUPS, { kind: 'field', type: 'submit' }, rep)).toBe(false)
    expect(
      insertFragment(
        doc,
        GROUPS,
        groupFragment('tab', () => ({})),
        null,
        0,
      ),
    ).toBeNull()
  })

  it('moves and reorders elements, and never puts a group inside itself', () => {
    const doc = emptyDoc()
    const a = field(doc)
    const b = field(doc)
    const s = group(doc, 'section')
    expect(moveElements(doc, GROUPS, [a], s, 0)).toBe(true)
    expect(childrenOf(doc, s).map((r) => r.uuid)).toEqual([a])
    expect(childrenOf(doc, null).map((r) => r.uuid)).toEqual([b, s])
    expect(moveElements(doc, GROUPS, [s], s, 0)).toBe(false)
    const inner = group(doc, 'section', s)
    expect(moveElements(doc, GROUPS, [s], inner, 0)).toBe(false)
    expect(shiftElement(doc, GROUPS, s, -1)).toBe(true)
    expect(childrenOf(doc, null).map((r) => r.uuid)).toEqual([s, b])
    expect(shiftElement(doc, GROUPS, s, -1)).toBe(false)
    assertSchemaShape(doc)
  })

  it('deletes a group with its subtree, the rules they own and effects targeting them', () => {
    const doc = emptyDoc()
    const s = group(doc, 'section')
    const inner = field(doc, TEXT, s)
    const outside = field(doc)
    doc.conditions.push(
      { uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', owner: { type: 'field', uuid: inner }, when: { k: 'lit', t: 'boolean', v: true }, effects: [] },
      {
        uuid: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        owner: { type: 'field', uuid: outside },
        when: { k: 'lit', t: 'boolean', v: true },
        effects: [
          { effect: 'hide', target: { type: 'field', uuid: inner } },
          { effect: 'show', target: { type: 'field', uuid: outside } },
        ],
      },
    )
    doc.fields.find((f) => f.uuid === outside)!.validation = { compare: [{ op: 'eq', field: inner }] }
    removeElements(doc, [s])
    expect(doc.groups).toHaveLength(0)
    expect(doc.fields.map((f) => f.uuid)).toEqual([outside])
    expect(doc.conditions.map((c) => c.uuid)).toEqual(['bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'])
    expect(doc.conditions[0]!.effects).toEqual([{ effect: 'show', target: { type: 'field', uuid: outside } }])
    expect(doc.fields[0]!.validation!.compare).toEqual([])
    expect(doc.fields[0]!.order).toBe(0)
  })

  it('pastes copies with new uuids, unique keys, and internal references remapped', () => {
    const doc = emptyDoc()
    const s = group(doc, 'section')
    const amount = field(doc, TEXT, s)
    const status = field(doc, SELECT, s)
    const sf = doc.fields.find((f) => f.uuid === status)!
    sf.options = { source: 'static', static: [{ uuid: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', value: 'a' }], dependsOn: amount }
    sf.behavior = { formula: { k: 'ref', scope: 'record', path: [doc.fields.find((f) => f.uuid === amount)!.key] } }
    doc.conditions.push({
      uuid: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
      owner: { type: 'field', uuid: amount },
      when: { k: 'bin', op: '=', a: { k: 'ref', scope: 'record', path: ['text'] }, b: { k: 'lit', t: 'text', v: 'x' } },
      effects: [{ effect: 'require', target: { type: 'field', uuid: status } }],
    })
    const fragment = extractFragment(doc, [s])
    const pasted = insertFragment(doc, GROUPS, fragment, null, 99)!
    expect(pasted).toHaveLength(1)
    assertSchemaShape(doc)
    const copyGroup = doc.groups.find((g) => g.uuid === pasted[0])!
    expect(copyGroup.key).toBe('section_2')
    const copies = doc.fields.filter((f) => f.group === copyGroup.uuid)
    expect(copies.map((f) => f.key)).toEqual(['text_2', 'select_2'])
    const [copyAmount, copyStatus] = copies
    expect(copyStatus!.options!.dependsOn).toBe(copyAmount!.uuid)
    expect(copyStatus!.options!.static![0]!.uuid).not.toBe('cccccccc-cccc-4ccc-8ccc-cccccccccccc')
    expect(copyStatus!.behavior!.formula).toEqual({ k: 'ref', scope: 'record', path: ['text_2'] })
    const rule = doc.conditions.find((c) => c.owner.uuid === copyAmount!.uuid)!
    expect(rule.uuid).not.toBe('dddddddd-dddd-4ddd-8ddd-dddddddddddd')
    expect(rule.effects[0]!.target!.uuid).toBe(copyStatus!.uuid)
    expect(rule.when).toMatchObject({ a: { path: ['text_2'] } })
  })

  it('clears references a pasted element makes to elements of another form', () => {
    const source = emptyDoc()
    const a = field(source)
    const b = field(source)
    source.fields.find((f) => f.uuid === b)!.validation = { compare: [{ op: 'eq', field: a }] }
    const target = emptyDoc()
    insertFragment(target, GROUPS, extractFragment(source, [b]), null, 0)
    expect(target.fields[0]!.validation!.compare).toEqual([])
    assertSchemaShape(target)
  })

  it('duplicates the selection right after it', () => {
    const doc = emptyDoc()
    const a = field(doc)
    const b = field(doc)
    const copies = duplicateElements(doc, GROUPS, [a])!
    expect(childrenOf(doc, null).map((r) => r.uuid)).toEqual([a, copies[0], b])
    assertSchemaShape(doc)
  })

  it('normalises empty maps encoded as arrays and locates validator paths', () => {
    const raw = JSON.parse(
      JSON.stringify({
        form: { uuid: '11111111-1111-4111-8111-111111111111', key: 'leave', kind: 'form', settings: [], i18n: [] },
        groups: [{ uuid: '22222222-2222-4222-8222-222222222222', key: 's', type: 'section', parent: null, order: 0, layout: [], i18n: [] }],
        fields: [{ uuid: '33333333-3333-4333-8333-333333333333', key: 'f', type: 'text', group: null, order: 1, i18n: [], ui: [] }],
        relations: [],
        conditions: [],
      }),
    ) as DraftDocument
    const doc = normalizeDocument(raw)
    expect(doc.form.settings).toEqual({})
    expect(doc.groups[0]!.layout).toEqual({})
    expect(doc.fields[0]!.i18n).toEqual({})
    expect(locateIssue(doc, 'fields.0.validation.pattern')).toEqual({ uuid: '33333333-3333-4333-8333-333333333333', kind: 'field', property: 'validation.pattern' })
    expect(locateIssue(doc, 'groups.0.parent').uuid).toBe('22222222-2222-4222-8222-222222222222')
    expect(locateIssue(doc, 'form.key')).toEqual({ uuid: null, kind: 'form', property: 'form.key' })
  })

  it('renames a key together with every expression reference to it', () => {
    const doc = emptyDoc()
    const a = field(doc)
    const b = field(doc)
    doc.fields.find((f) => f.uuid === b)!.behavior = { formula: { k: 'bin', op: '+', a: { k: 'ref', scope: 'record', path: ['text'] }, b: { k: 'ref', scope: 'old', path: ['text'] } } }
    doc.conditions.push({ uuid: 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', owner: { type: 'field', uuid: b }, when: { k: 'ref', scope: 'user', path: ['text'] }, effects: [] })
    renameKey(doc, a, 'amount')
    expect(doc.fields.find((f) => f.uuid === a)!.key).toBe('amount')
    expect(doc.fields.find((f) => f.uuid === b)!.behavior!.formula).toEqual({ k: 'bin', op: '+', a: { k: 'ref', scope: 'record', path: ['amount'] }, b: { k: 'ref', scope: 'old', path: ['amount'] } })
    // References in other scopes (the user's attributes) are not field references.
    expect(doc.conditions[0]!.when).toEqual({ k: 'ref', scope: 'user', path: ['text'] })
  })
})
