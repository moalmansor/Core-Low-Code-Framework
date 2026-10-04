/**
 * Type-specific settings the renderer reads from a field's `ui.props`
 * (form-draft schema: scalar, list or per-locale text values). The builder's
 * properties panel offers exactly these; anything else is ignored.
 * Texts (labels, content, terms) live in the field's `i18n` maps instead.
 */
export interface UiPropSpec {
  name: string
  kind: 'number' | 'boolean' | 'text' | 'i18n' | 'choice' | 'file'
  choices?: string[]
}

export const UI_PROPS: Record<string, UiPropSpec[]> = {
  textarea: [{ name: 'rows', kind: 'number' }],
  code: [
    { name: 'rows', kind: 'number' },
    { name: 'language', kind: 'text' },
  ],
  markdown: [{ name: 'rows', kind: 'number' }],
  range: [
    { name: 'min', kind: 'number' },
    { name: 'max', kind: 'number' },
    { name: 'step', kind: 'number' },
    { name: 'minLabel', kind: 'i18n' },
    { name: 'maxLabel', kind: 'i18n' },
  ],
  slider: [
    { name: 'min', kind: 'number' },
    { name: 'max', kind: 'number' },
    { name: 'step', kind: 'number' },
    { name: 'minLabel', kind: 'i18n' },
    { name: 'maxLabel', kind: 'i18n' },
  ],
  rating: [{ name: 'stars', kind: 'number' }],
  duration: [{ name: 'seconds', kind: 'boolean' }],
  phone_intl: [{ name: 'defaultCountry', kind: 'text' }],
  image_upload: [
    { name: 'crop', kind: 'boolean' },
    { name: 'aspectRatio', kind: 'number' },
    { name: 'maxWidth', kind: 'number' },
    { name: 'maxHeight', kind: 'number' },
  ],
  camera: [
    { name: 'facing', kind: 'choice', choices: ['environment', 'user'] },
    { name: 'maxWidth', kind: 'number' },
    { name: 'maxHeight', kind: 'number' },
  ],
  heading: [{ name: 'level', kind: 'number' }],
  spacer: [{ name: 'size', kind: 'choice', choices: ['sm', 'md', 'lg'] }],
  display_image: [
    { name: 'file', kind: 'file' },
    { name: 'src', kind: 'text' },
    { name: 'width', kind: 'number' },
  ],
  alert_box: [{ name: 'severity', kind: 'choice', choices: ['info', 'success', 'warning', 'error'] }],
  link: [
    { name: 'url', kind: 'text' },
    { name: 'newTab', kind: 'boolean' },
  ],
  progress: [
    { name: 'min', kind: 'number' },
    { name: 'max', kind: 'number' },
  ],
  meter: [
    { name: 'min', kind: 'number' },
    { name: 'max', kind: 'number' },
    { name: 'low', kind: 'number' },
    { name: 'high', kind: 'number' },
    { name: 'optimum', kind: 'number' },
  ],
  button: [{ name: 'severity', kind: 'choice', choices: ['secondary', 'info', 'success', 'warn', 'danger', 'contrast'] }],
  image_button: [{ name: 'src', kind: 'text' }],
}
