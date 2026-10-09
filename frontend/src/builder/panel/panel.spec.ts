import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick } from 'vue'
import { matches, providePanel, type PanelContext } from './panel'

describe('Find a setting', () => {
  it('matches Arabic regardless of diacritics and letter variants', () => {
    expect(matches('لون', ['لَوْن الخيار'])).toBe(true)
    expect(matches('اعدادات', ['إعدادات العرض'])).toBe(true)
    expect(matches('مخفي', ['مخفى'])).toBe(true)
    expect(matches('قائمه', ['قائمة منسدلة'])).toBe(true)
  })

  it('matches English regardless of case and accents, and an empty query matches everything', () => {
    expect(matches('colo', ['Option colour'])).toBe(true)
    expect(matches('cafe', ['Café hours'])).toBe(true)
    expect(matches('  ', ['anything'])).toBe(true)
    expect(matches('width', ['Placeholder', 'Help text'])).toBe(false)
  })
})

describe('panel state', () => {
  afterEach(() => localStorage.clear())

  function host(kind: string, initial: string, available: string[]): PanelContext {
    let ctx!: PanelContext
    mount(
      defineComponent({
        setup() {
          ctx = providePanel(kind, initial, () => available)
          return () => h('div')
        },
      }),
    )
    return ctx
  }

  it('remembers opened and closed sections in this browser', () => {
    const a = host('field', 'general', ['general'])
    expect(a.isOpen('field.advanced', false)).toBe(false)
    a.toggle('field.advanced', false)
    expect(a.isOpen('field.advanced', false)).toBe(true)
    expect(host('field', 'general', ['general']).isOpen('field.advanced', false)).toBe(true)
  })

  it('keeps the last tab while moving between elements of the same kind, when that tab exists', async () => {
    const a = host('group', 'general', ['general', 'layout'])
    a.tab.value = 'layout'
    await nextTick()
    expect(host('group', 'general', ['general', 'layout']).tab.value).toBe('layout')
    expect(host('group', 'general', ['general']).tab.value).toBe('general')
    expect(host('form', 'general', ['general', 'layout']).tab.value).toBe('general')
  })
})
