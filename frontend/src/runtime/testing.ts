import type { ClientDefinition, ClientField, ClientGroup, Condition } from './types'

/** Builders of client definitions for the runtime's unit tests. */

let seq = 0
export function uuid(): string {
  seq++
  return `00000000-0000-4000-8000-${String(seq).padStart(12, '0')}`
}

export function field(key: string, type: string, extra: Partial<ClientField> = {}): ClientField {
  return {
    uuid: uuid(),
    key,
    type,
    group: null,
    order: seq,
    storage: {},
    options: null,
    validation: {},
    behavior: {},
    ui: {},
    table: {},
    events: [],
    flags: { sensitive: false },
    relation: null,
    i18n: { label: { en: key, ar: `ar_${key}` } },
    access: 'editable',
    serverComputed: false,
    ...extra,
  }
}

export function group(key: string, type: ClientGroup['type'], extra: Partial<ClientGroup> = {}): ClientGroup {
  return { uuid: uuid(), key, type, parent: null, order: seq, access: 'editable', i18n: { title: { en: key, ar: `ar_${key}` } }, ...extra }
}

export function definition(fields: ClientField[], groups: ClientGroup[] = [], conditions: Condition[] = [], extra: Partial<ClientDefinition> = {}): ClientDefinition {
  return {
    form: { uuid: uuid(), key: 'test_form', kind: 'form', version: 1, icon: null, settings: {}, titleTemplate: null, i18n: { name: { en: 'Test', ar: 'اختبار' } } },
    mode: 'create',
    access: { form: 'editable', modes: { view: true, create: true, edit: true, delete: true, print: true } },
    groups,
    fields,
    relations: [],
    conditions,
    ...extra,
  }
}

export const ast = {
  ref: (...path: string[]) => ({ k: 'ref', scope: 'record', path }),
  row: (...path: string[]) => ({ k: 'ref', scope: 'row', path }),
  num: (v: string) => ({ k: 'lit', t: 'number', v }),
  text: (v: string) => ({ k: 'lit', t: 'text', v }),
  bin: (op: string, a: unknown, b: unknown) => ({ k: 'bin', op, a, b }),
  call: (fn: string, ...args: unknown[]) => ({ k: 'call', fn, args }),
}
