/**
 * Conversion between the menu tree returned by GET /applications/{app}/menu
 * and the editor's working tree, and back into the PUT payload
 * (specification §4.13 menu editor, §4.29 unlimited nesting within the
 * server's depth bound).
 */

export const MENU_TYPES = ['form', 'collection', 'link', 'header', 'separator'] as const
export type MenuType = (typeof MENU_TYPES)[number]

/** The server rejects trees nested deeper than this many levels. */
export const MAX_MENU_LEVELS = 6
export const MAX_MENU_ITEMS = 500

export interface MenuItemApi {
  uuid: string
  type: MenuType
  target?: string | null
  url?: string | null
  open_in_new_tab?: boolean
  icon?: string | null
  label?: string | null
  labels?: Record<string, string>
  is_active?: boolean
  permission?: string
  children?: MenuItemApi[]
}

export interface MenuNode {
  /** Editor-local identity (stable across drags); the uuid is null until saved. */
  id: string
  uuid: string | null
  type: MenuType
  target: string | null
  url: string
  open_in_new_tab: boolean
  icon: string
  labels: Record<string, string>
  is_active: boolean
  children: MenuNode[]
}

export interface MenuItemPayload {
  uuid?: string
  type: MenuType
  target?: string
  url?: string
  open_in_new_tab: boolean
  icon: string | null
  label?: Record<string, string>
  is_active: boolean
  children: MenuItemPayload[]
}

let counter = 0
export function newNodeId(): string {
  counter += 1
  return `n${Date.now().toString(36)}${counter}`
}

export function blankNode(type: MenuType, makeId: () => string = newNodeId): MenuNode {
  return { id: makeId(), uuid: null, type, target: null, url: '', open_in_new_tab: false, icon: '', labels: {}, is_active: true, children: [] }
}

export function fromApi(items: MenuItemApi[], makeId: () => string = newNodeId): MenuNode[] {
  return items.map((i) => ({
    id: makeId(),
    uuid: i.uuid,
    type: i.type,
    target: i.target ?? null,
    url: i.url ?? '',
    open_in_new_tab: i.open_in_new_tab ?? false,
    icon: i.icon ?? '',
    labels: { ...(i.labels ?? {}) },
    is_active: i.is_active ?? true,
    children: fromApi(i.children ?? [], makeId),
  }))
}

function cleanLabels(labels: Record<string, string>): Record<string, string> {
  const out: Record<string, string> = {}
  for (const [code, text] of Object.entries(labels)) {
    const v = (text ?? '').trim()
    if (v !== '') out[code] = v
  }
  return out
}

/** Only the properties that matter for each item type are sent. */
export function toPayload(nodes: MenuNode[]): MenuItemPayload[] {
  return nodes.map((n) => {
    const out: MenuItemPayload = {
      type: n.type,
      open_in_new_tab: n.type === 'link' ? n.open_in_new_tab : false,
      icon: n.type === 'separator' || n.icon.trim() === '' ? null : n.icon.trim(),
      is_active: n.is_active,
      children: n.type === 'separator' ? [] : toPayload(n.children),
    }
    if (n.uuid) out.uuid = n.uuid
    if (n.type !== 'separator') out.label = cleanLabels(n.labels)
    if ((n.type === 'form' || n.type === 'collection') && n.target) out.target = n.target
    if (n.type === 'link') out.url = n.url.trim()
    return out
  })
}

/** Number of nesting levels (an empty tree has 0, a flat list 1). */
export function treeDepth(nodes: MenuNode[]): number {
  return nodes.reduce((max, n) => Math.max(max, 1 + treeDepth(n.children)), 0)
}

export function countNodes(nodes: MenuNode[]): number {
  return nodes.reduce((sum, n) => sum + 1 + countNodes(n.children), 0)
}

export function findNode(nodes: MenuNode[], id: string): MenuNode | null {
  for (const n of nodes) {
    if (n.id === id) return n
    const hit = findNode(n.children, id)
    if (hit) return hit
  }
  return null
}

/** Removes a node (and its subtree) in place; returns whether it was found. */
export function removeNode(nodes: MenuNode[], id: string): boolean {
  const i = nodes.findIndex((n) => n.id === id)
  if (i >= 0) {
    nodes.splice(i, 1)
    return true
  }
  return nodes.some((n) => removeNode(n.children, id))
}

export type MenuProblem = { id: string | null; code: 'too_deep' | 'too_many' | 'label_missing' | 'target_missing' | 'url_invalid' | 'separator_children' }

/** Client-side checks mirroring the server's rules, so problems are shown next to the item. */
export function validateTree(nodes: MenuNode[], defaultLocale: string): MenuProblem[] {
  const problems: MenuProblem[] = []
  if (treeDepth(nodes) > MAX_MENU_LEVELS) problems.push({ id: null, code: 'too_deep' })
  if (countNodes(nodes) > MAX_MENU_ITEMS) problems.push({ id: null, code: 'too_many' })
  const walk = (list: MenuNode[]) => {
    for (const n of list) {
      if (n.type !== 'separator' && !(n.labels[defaultLocale] ?? '').trim()) problems.push({ id: n.id, code: 'label_missing' })
      if ((n.type === 'form' || n.type === 'collection') && !n.target) problems.push({ id: n.id, code: 'target_missing' })
      if (n.type === 'link' && !/^(https:\/\/|\/)\S*$/.test(n.url.trim())) problems.push({ id: n.id, code: 'url_invalid' })
      if (n.type === 'separator' && n.children.length) problems.push({ id: n.id, code: 'separator_children' })
      walk(n.children)
    }
  }
  walk(nodes)
  return problems
}

/** The list holding a node and its index there (null when absent). */
function locate(nodes: MenuNode[], id: string, parent: MenuNode | null = null): { list: MenuNode[]; index: number; parent: MenuNode | null } | null {
  const index = nodes.findIndex((n) => n.id === id)
  if (index >= 0) return { list: nodes, index, parent }
  for (const n of nodes) {
    const hit = locate(n.children, id, n)
    if (hit) return hit
  }
  return null
}

/** Keyboard alternatives to drag and drop. Each returns whether the tree changed. */
export function moveBy(nodes: MenuNode[], id: string, delta: -1 | 1): boolean {
  const at = locate(nodes, id)
  if (!at) return false
  const to = at.index + delta
  if (to < 0 || to >= at.list.length) return false
  const [node] = at.list.splice(at.index, 1)
  at.list.splice(to, 0, node!)
  return true
}

/** Makes the node the last child of its previous sibling. */
export function indent(nodes: MenuNode[], id: string): boolean {
  const at = locate(nodes, id)
  if (!at || at.index === 0) return false
  const previous = at.list[at.index - 1]!
  if (previous.type === 'separator') return false
  const [node] = at.list.splice(at.index, 1)
  previous.children.push(node!)
  return true
}

/** Moves the node out of its parent, right after it. */
export function outdent(nodes: MenuNode[], id: string): boolean {
  const at = locate(nodes, id)
  if (!at || !at.parent) return false
  const outer = locate(nodes, at.parent.id)!
  const [node] = at.list.splice(at.index, 1)
  outer.list.splice(outer.index + 1, 0, node!)
  return true
}

/** Callbacks the editor provides to every level of the tree. */
export interface MenuTreeContext {
  selected: () => string | null
  hasProblem: (id: string) => boolean
  labelOf: (node: MenuNode) => string
  targetOf: (node: MenuNode) => string
  select: (id: string) => void
  remove: (id: string) => void
  addChild: (id: string) => void
  move: (id: string, how: 'up' | 'down' | 'in' | 'out') => void
}
