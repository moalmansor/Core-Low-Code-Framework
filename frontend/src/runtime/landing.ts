import type { Me } from '@/stores/session'

/**
 * Where the person starts (specification §4.36, ADR-0063): home, My Work, an
 * application's home, a form or a page. Null keeps the home page.
 */
export function landingPath(lp: Me['preferences']['landing_page'] | null | undefined): string | null {
  if (!lp || lp.type === 'home') return null
  if (lp.type === 'my_work') return '/my-work'
  if (!lp.target) return null
  return { application: `/a/${lp.target}`, form: `/app/${lp.target}`, page: `/pages/${lp.target}` }[lp.type] ?? null
}
