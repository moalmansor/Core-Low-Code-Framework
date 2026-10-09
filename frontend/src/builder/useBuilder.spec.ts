import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { builderApi } from './api'
import type { DraftDocument } from './types'
import { createBuilder } from './useBuilder'

vi.mock('./api', () => ({
  builderApi: { form: vi.fn(), draft: vi.fn(), fieldTypes: vi.fn(), templates: vi.fn(), saveDraft: vi.fn() },
}))

const FORM = '11111111-1111-4111-8111-111111111111'
const doc = (): DraftDocument => ({ form: { uuid: FORM, key: 'leave', kind: 'form' }, groups: [], fields: [], relations: [], conditions: [] })

/** A save whose server answer the test releases by hand. */
function pendingSaves(): { release: () => void; calls: DraftDocument[] } {
  const waiting: (() => void)[] = []
  const calls: DraftDocument[] = []
  vi.mocked(builderApi.saveDraft).mockImplementation((_form, document) => {
    calls.push(document)
    return new Promise((resolve) => waiting.push(() => resolve({ draft_updated_at: `t${calls.length}`, problems: [] } as never)))
  })
  return { release: () => waiting.shift()?.(), calls }
}

describe('builder saving', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.mocked(builderApi.form).mockResolvedValue({ uuid: FORM, state: 'draft' } as never)
    vi.mocked(builderApi.draft).mockResolvedValue({ document: doc(), draft_updated_at: 't0', problems: [] } as never)
    vi.mocked(builderApi.fieldTypes).mockResolvedValue({ fields: [], groups: [] })
    vi.mocked(builderApi.templates).mockResolvedValue([])
  })

  it('flush saves an edit made while an earlier save was still in flight', async () => {
    const builder = createBuilder(FORM)
    await builder.load()
    const server = pendingSaves()

    builder.mutate((d) => (d.form.icon = 'pi-one'))
    const first = builder.save() // an autosave, still waiting for the server
    builder.mutate((d) => (d.form.icon = 'pi-two'))
    const flushed = builder.flush() // the publish dialog asks for everything to be saved

    server.release()
    await vi.waitFor(() => expect(server.calls).toHaveLength(2))
    server.release()
    await first

    expect(await flushed).toBe(true)
    expect(server.calls.map((d) => d.form.icon)).toEqual(['pi-one', 'pi-two'])
    expect(builder.saveState).toBe('saved')
    builder.cancelTimers()
  })

  it('flush reports unsaved work when the server refuses the draft', async () => {
    const builder = createBuilder(FORM)
    await builder.load()
    vi.mocked(builderApi.saveDraft).mockRejectedValue(new Error('offline'))
    builder.mutate((d) => (d.form.icon = 'pi-one'))

    expect(await builder.flush()).toBe(false)
    expect(builder.saveState).toBe('error')
    expect(builderApi.saveDraft).toHaveBeenCalledTimes(1)
    builder.cancelTimers()
  })
})
