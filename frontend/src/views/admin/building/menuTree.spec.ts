import { describe, expect, it } from 'vitest'
import { MAX_MENU_LEVELS, blankNode, countNodes, findNode, fromApi, indent, moveBy, outdent, removeNode, toPayload, treeDepth, validateTree, type MenuItemApi } from './menuTree'

const ids = () => {
  let i = 0
  return () => `id${++i}`
}

const api: MenuItemApi[] = [
  {
    uuid: '11111111-1111-4111-8111-111111111111',
    type: 'header',
    icon: 'pi pi-folder',
    label: 'Requests',
    labels: { en: 'Requests', ar: 'الطلبات' },
    is_active: true,
    permission: 'menu.x.view',
    children: [
      { uuid: '22222222-2222-4222-8222-222222222222', type: 'form', target: '33333333-3333-4333-8333-333333333333', labels: { en: 'Leave' }, open_in_new_tab: false, is_active: false, children: [] },
      { uuid: '44444444-4444-4444-8444-444444444444', type: 'separator', open_in_new_tab: false, is_active: true, children: [] },
    ],
  },
  { uuid: '55555555-5555-4555-8555-555555555555', type: 'link', url: 'https://example.org/help', open_in_new_tab: true, labels: { en: 'Help' }, is_active: true, children: [] },
]

describe('menu tree conversion', () => {
  it('reads the server tree into editor nodes', () => {
    const tree = fromApi(api, ids())
    expect(tree).toHaveLength(2)
    expect(tree[0]!.id).toBe('id1')
    expect(tree[0]!.children[0]!.target).toBe('33333333-3333-4333-8333-333333333333')
    expect(tree[0]!.children[0]!.is_active).toBe(false)
    expect(tree[1]!.url).toBe('https://example.org/help')
    expect(tree[0]!.icon).toBe('pi pi-folder')
    expect(treeDepth(tree)).toBe(2)
    expect(countNodes(tree)).toBe(4)
  })

  it('round-trips into the PUT payload with only relevant properties', () => {
    const payload = toPayload(fromApi(api, ids()))
    expect(payload[0]).toEqual({
      uuid: '11111111-1111-4111-8111-111111111111',
      type: 'header',
      open_in_new_tab: false,
      icon: 'pi pi-folder',
      is_active: true,
      label: { en: 'Requests', ar: 'الطلبات' },
      children: [
        {
          uuid: '22222222-2222-4222-8222-222222222222',
          type: 'form',
          target: '33333333-3333-4333-8333-333333333333',
          open_in_new_tab: false,
          icon: null,
          is_active: false,
          label: { en: 'Leave' },
          children: [],
        },
        { uuid: '44444444-4444-4444-8444-444444444444', type: 'separator', open_in_new_tab: false, icon: null, is_active: true, children: [] },
      ],
    })
    expect(payload[1]).toMatchObject({ type: 'link', url: 'https://example.org/help', open_in_new_tab: true })
    expect(payload[1]).not.toHaveProperty('target')
  })

  it('omits the uuid of new items and drops blank labels', () => {
    const n = blankNode('header', ids())
    n.labels = { en: '  Reports ', ar: '' }
    expect(toPayload([n])).toEqual([{ type: 'header', open_in_new_tab: false, icon: null, is_active: true, label: { en: 'Reports' }, children: [] }])
  })

  it('finds and removes nodes anywhere in the tree', () => {
    const tree = fromApi(api, ids())
    expect(findNode(tree, 'id2')?.type).toBe('form')
    expect(removeNode(tree, 'id2')).toBe(true)
    expect(findNode(tree, 'id2')).toBeNull()
    expect(removeNode(tree, 'missing')).toBe(false)
  })

  it('reports missing labels, targets, bad links and excessive depth', () => {
    const make = ids()
    const form = blankNode('form', make)
    const link = blankNode('link', make)
    link.labels = { en: 'x' }
    link.url = 'javascript:alert(1)'
    const codes = validateTree([form, link], 'en').map((p) => p.code)
    expect(codes).toEqual(['label_missing', 'target_missing', 'url_invalid'])

    let root = blankNode('header', make)
    root.labels = { en: 'level' }
    const top = root
    for (let i = 0; i < MAX_MENU_LEVELS; i++) {
      const child = blankNode('header', make)
      child.labels = { en: 'level' }
      root.children.push(child)
      root = child
    }
    expect(validateTree([top], 'en').map((p) => p.code)).toEqual(['too_deep'])
  })

  it('checks targets, badges and visibility rules of pages, reports, dashboards and My Work', () => {
    const make = ids()
    const page = blankNode('page', make)
    page.labels = { en: 'Guide' }
    const work = blankNode('my_work', make)
    work.labels = { en: 'Work' }
    work.badge = 'records'
    expect(validateTree([page, work], 'en').map((p) => p.code)).toEqual(['target_missing', 'badge_invalid'])

    page.target = '66666666-6666-4666-8666-666666666666'
    work.badge = 'approvals'
    const rule = { k: 'call', fn: 'has_role', args: [{ k: 'lit', t: 'text', v: 'manager' }] }
    work.condition = rule as never
    expect(validateTree([page, work], 'en')).toEqual([])
    const [p, w] = toPayload([page, work])
    expect(p).toMatchObject({ type: 'page', target: '66666666-6666-4666-8666-666666666666' })
    expect(p).not.toHaveProperty('badge')
    expect(w).toMatchObject({ type: 'my_work', badge: { kind: 'approvals' }, condition: rule })
    expect(w).not.toHaveProperty('target')

    // Read back from the server, badges and rules survive the round trip.
    const back = fromApi([{ uuid: '77777777-7777-4777-8777-777777777777', type: 'my_work', labels: { en: 'Work' }, badge: { kind: 'approvals' }, condition: rule as never, children: [] }], make)
    expect(back[0]!.badge).toBe('approvals')
    expect(back[0]!.condition).toEqual(rule)
  })

  it('reorders, indents and outdents without drag and drop', () => {
    const tree = fromApi(api, ids())
    // id1 header [id2 form, id3 separator], id4 link
    expect(moveBy(tree, 'id4', -1)).toBe(true)
    expect(tree.map((n) => n.id)).toEqual(['id4', 'id1'])
    expect(moveBy(tree, 'id4', -1)).toBe(false)
    expect(indent(tree, 'id1')).toBe(true)
    expect(tree.map((n) => n.id)).toEqual(['id4'])
    expect(tree[0]!.children.map((n) => n.id)).toEqual(['id1'])
    expect(outdent(tree, 'id2')).toBe(true)
    expect(tree[0]!.children.map((n) => n.id)).toEqual(['id1', 'id2'])
    expect(indent(tree, 'id2')).toBe(true)
    expect(findNode(tree, 'id1')!.children.map((n) => n.id)).toEqual(['id3', 'id2'])
    expect(outdent(tree, 'id4')).toBe(false)
  })
})
