import type { Fragment } from './types'

/**
 * The builder clipboard. Kept in browser storage so elements can be copied in
 * one form and pasted into another (specification §4.3); a copy in memory
 * covers private windows where storage is unavailable.
 */
const STORAGE_KEY = 'lcf.builder.clipboard'
let memory: Fragment | null = null

function isFragment(value: unknown): value is Fragment {
  if (!value || typeof value !== 'object') return false
  const v = value as Record<string, unknown>
  return Array.isArray(v.groups) && Array.isArray(v.fields) && Array.isArray(v.conditions) && Array.isArray(v.relations)
}

export function writeClipboard(fragment: Fragment): void {
  memory = fragment
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(fragment))
  } catch {
    /* storage unavailable: the in-memory copy is used */
  }
}

export function readClipboard(): Fragment | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const parsed: unknown = JSON.parse(raw)
      if (isFragment(parsed)) return parsed
    }
  } catch {
    /* fall back to memory */
  }
  return memory
}

export function hasClipboard(): boolean {
  return readClipboard() !== null
}
