import { afterEach, describe, expect, it } from 'vitest'
import { humanize, labelOf, pickText, setDefaultTextLocale } from './i18nText'

describe('pickText', () => {
  afterEach(() => setDefaultTextLocale('en'))

  it('falls back to the default language, then any filled one, skipping blanks', () => {
    setDefaultTextLocale('ar')
    expect(pickText({ en: ' ', ar: 'الرمز' }, 'en')).toBe('الرمز')
    expect(pickText({ en: 'Code', ar: 'الرمز' }, 'en')).toBe('Code')
    expect(pickText({ fr: 'Code FR' }, 'en')).toBe('Code FR')
    expect(pickText({ en: '', ar: '' }, 'en')).toBeNull()
    expect(pickText(null, 'en')).toBeNull()
  })

  it('never returns a raw key: a missing label becomes a readable phrase', () => {
    expect(labelOf({ en: 'Visit date' }, 'ar', 'visit_date')).toBe('Visit date')
    expect(labelOf({}, 'en', 'visit_date')).toBe('Visit date')
    expect(humanize('contact-email')).toBe('Contact email')
    expect(humanize('VAT_no')).toBe('VAT no')
  })
})
