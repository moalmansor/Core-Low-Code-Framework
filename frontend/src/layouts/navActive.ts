/**
 * Which sidebar entry is the active one (design system §5.1): exactly one,
 * or none. An entry matches when the current path is its path or lies under
 * it (at a "/" boundary); entries marked exact match only their own path.
 * The longest match wins, and on a tie the first entry in menu order, so two
 * entries are never highlighted together.
 */
export interface NavCandidate {
  id: string
  path: string
  exact?: boolean
}

const trim = (p: string): string => (p.length > 1 ? p.replace(/\/+$/, '') : p)

export function activeNavId(current: string, candidates: NavCandidate[]): string | null {
  const path = trim(current.split(/[?#]/)[0] ?? '/')
  let best: NavCandidate | null = null
  for (const c of candidates) {
    const p = trim(c.path)
    const hit = path === p || (!c.exact && p !== '/' && path.startsWith(`${p}/`))
    if (hit && (!best || p.length > trim(best.path).length)) best = c
  }
  return best?.id ?? null
}
