import { describe, expect, it } from 'vitest'
import { activeNavId, type NavCandidate } from './navActive'

const menu: NavCandidate[] = [
  { id: 'home', path: '/', exact: true },
  { id: 'my_work', path: '/my-work' },
  { id: 'app-a', path: '/app/form-a' },
  { id: 'app-a-again', path: '/app/form-a' },
  { id: 'app-ab', path: '/app/form-ab' },
  { id: 'admin', path: '/admin', exact: true },
  { id: 'forms', path: '/admin/forms' },
  { id: 'settings', path: '/admin/settings' },
  { id: 'branding', path: '/admin/settings/branding' },
]

describe('activeNavId', () => {
  it.each([
    ['/', 'home'],
    ['/my-work', 'my_work'],
    ['/admin', 'admin'],
    ['/admin/forms', 'forms'],
    ['/admin/forms/123/configure/views', 'forms'],
    ['/admin/forms/123/builder?x=1', 'forms'],
    ['/admin/settings', 'settings'],
    ['/admin/settings/security', 'settings'],
    ['/admin/settings/branding', 'branding'],
    ['/app/form-a', 'app-a'],
    ['/app/form-a/rec-1/edit', 'app-a'],
    ['/app/form-ab', 'app-ab'],
    ['/admin/users/', null],
    ['/profile', null],
  ])('%s → %s', (path, id) => {
    expect(activeNavId(path, menu)).toBe(id)
  })

  it('never highlights two entries pointing to the same place', () => {
    expect(activeNavId('/app/form-a', menu)).toBe('app-a')
  })
})
