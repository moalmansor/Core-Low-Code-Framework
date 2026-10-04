import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { beforeEach, describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import { bundled } from '@/i18n/bundled'
import FormRenderer from './FormRenderer.vue'
import { ast, definition, field, group, uuid } from './testing'
import type { ClientDefinition } from './types'

function build(): ClientDefinition {
  const sec = group('main', 'section')
  const lines = group('lines', 'repeater', { repeater: { minRows: 0, defaultRows: 1 } })
  const amount = field('amount', 'number', { group: sec.uuid })
  const reason = field('reason', 'text', { group: sec.uuid })
  const double = field('double', 'decimal', { group: sec.uuid, behavior: { formula: ast.bin('*', ast.ref('amount'), ast.num('2')) as never } })
  const qty = field('qty', 'number', { group: lines.uuid })
  return definition(
    [amount, reason, double, qty, field('title', 'heading', { group: sec.uuid }), field('mystery', 'brand_new_type', { group: sec.uuid })],
    [sec, lines],
    [
      {
        uuid: uuid(),
        owner: { type: 'field', uuid: reason.uuid },
        when: ast.bin('>', ast.ref('amount'), ast.num('100')) as never,
        effects: [],
        else: [{ effect: 'hide', target: { type: 'field', uuid: reason.uuid } }],
      },
    ],
  )
}

function render(locale: 'en' | 'ar', props: Record<string, unknown>) {
  document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr'
  const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', flatJson: true, messages: bundled, missingWarn: false, fallbackWarn: false })
  return mount(FormRenderer, {
    props: { definition: build(), modelValue: {}, mode: 'create', ...props },
    global: { plugins: [i18n, PrimeVue], directives: { tooltip: {} }, stubs: { RouterLink: true } },
    attachTo: document.body,
  })
}

describe('FormRenderer', () => {
  beforeEach(() => setActivePinia(createPinia()))

  for (const locale of ['en', 'ar'] as const) {
    it(`renders groups, fields, repeaters and live rules (${locale})`, async () => {
      const wrapper = render(locale, {})
      await nextTick()
      // Defaults: one repeater row is created.
      const first = wrapper.emitted('update:modelValue')![0]![0] as Record<string, unknown>
      expect(first.lines).toEqual([{}])
      await wrapper.setProps({ modelValue: first })
      expect(wrapper.find('[data-group="main"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="repeater-lines"]').exists()).toBe(true)
      expect(wrapper.find('[data-field="reason"]').exists()).toBe(false)
      expect(wrapper.text()).toContain(locale === 'ar' ? 'ar_amount' : 'amount')
      // Unknown types render a read-only notice instead of failing.
      expect(wrapper.find('[data-testid="value-mystery"]').text()).toContain('brand_new_type')

      const input = wrapper.find('[data-field="amount"] input')
      await input.setValue('150')
      const next = wrapper.emitted('update:modelValue')!.at(-1)![0] as Record<string, unknown>
      expect(next.amount).toBe('150')
      expect(next.double).toBe('300')
      await wrapper.setProps({ modelValue: next })
      expect(wrapper.find('[data-field="reason"]').exists()).toBe(true)
      wrapper.unmount()
    })
  }

  it('shows values read-only in view mode and server errors under fields', async () => {
    const wrapper = render('en', { mode: 'view', modelValue: { amount: '1234.5', lines: [] }, errors: { amount: ['Too big'] } })
    await nextTick()
    expect(wrapper.find('[data-field="amount"] input').exists()).toBe(false)
    expect(wrapper.find('[data-testid="value-amount"]').text()).toBe('1,234.5')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    wrapper.unmount()
  })

  it('validates on demand through the exposed API', async () => {
    const def = build()
    def.fields[0]!.validation = { required: true }
    const wrapper = render('en', { definition: def, modelValue: { lines: [] } })
    await nextTick()
    const errors = (wrapper.vm as unknown as { validate(): Record<string, string[]> }).validate()
    expect(errors.amount).toHaveLength(1)
    await nextTick()
    expect(wrapper.find('[data-field="amount"] [data-testid="field-error"]').exists()).toBe(true)
    wrapper.unmount()
  })
})
