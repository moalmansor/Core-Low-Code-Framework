import { reactive } from 'vue'
import { get, send } from '@/api/http'

/** Guided tours (specification §4.37, ADR-0065) as the person takes them. */
export interface TourStep {
  key: string
  /** `[data-field="…"]`, `[data-group="…"]`, `[data-tour="…"]`, or null for a centred note. */
  target: string | null
  placement: 'auto' | 'top' | 'bottom' | 'start' | 'end'
  title: string | null
  body: string | null
}
export interface Tour {
  uuid: string
  name: string | null
  trigger: 'first_use' | 'manual'
  version: number
  /** A first-use tour the person has not finished or closed in this version. */
  due: boolean
  steps: TourStep[]
}
/** Where tours run: a form's screens, or a place (home, my_work, insights, profile, area.<key>). */
export type TourPlace = { form: string } | { place: string }

/** The tours where the person is, and the one running; shared by the shell, the Help panel and the runner. */
export const tours = reactive<{ here: Tour[]; running: Tour | null }>({ here: [], running: null })

export async function loadTours(where: TourPlace | null): Promise<void> {
  tours.here = []
  if (!where) return
  try {
    tours.here = (await get<{ data: Tour[] }>('/tours/mine', 'form' in where ? { form: where.form } : { place: where.place })).data
  } catch {
    // Tours are a convenience: a failed load shows none.
  }
}

export function startTour(uuid: string): void {
  tours.running = tours.here.find((t) => t.uuid === uuid) ?? null
}

/** Records that the person finished or closed the tour, so a first-use tour does not start again. */
export async function finishTour(tour: Tour, outcome: 'completed' | 'dismissed'): Promise<void> {
  tours.running = null
  const mine = tours.here.find((t) => t.uuid === tour.uuid)
  if (mine) mine.due = false
  try {
    await send('post', `/tours/${tour.uuid}/progress`, { outcome })
  } catch {
    // Not recorded: the tour may start again next time, which is harmless.
  }
}

export const resetMyTours = () => send('delete', '/me/tours')

/** Parts of the screen a step can point at (the server keeps the same list). */
export const TOUR_ANCHORS = ['navigation', 'search', 'notifications', 'help', 'user_menu', 'new_record', 'save_record', 'record_actions'] as const
export const TOUR_PLACES = ['home', 'my_work', 'insights', 'profile'] as const

/** Tours as an administrator edits them. */
export interface TourDoc {
  uuid?: string
  form: string | null
  place: string | null
  trigger: 'first_use' | 'manual'
  roles: string[]
  active: boolean
  i18n: { name: Record<string, string> }
  steps: { key: string; target: string | null; placement: TourStep['placement']; i18n: { title: Record<string, string>; body: Record<string, string> } }[]
  hash?: string
  version?: number
  label?: string
  completed?: number
}
export const toursAdminApi = {
  list: () => get<{ data: TourDoc[] }>('/tours').then((r) => r.data),
  load: (uuid: string) => get<{ data: TourDoc }>(`/tours/${uuid}`).then((r) => r.data),
  create: (tour: TourDoc) => send<{ data: TourDoc }>('post', '/tours', { tour }).then((r) => r.data),
  save: (uuid: string, tour: TourDoc, baseHash: string) => send<{ data: TourDoc }>('put', `/tours/${uuid}`, { tour, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/tours/${uuid}`),
  reset: (uuid: string, roles: string[]) => send<{ data: { people: number } }>('post', `/tours/${uuid}/reset`, { roles }).then((r) => r.data),
}
