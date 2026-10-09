import type { DraftIssue } from './types'

type Translate = (key: string, named?: Record<string, unknown>) => string

/**
 * The text of a DraftValidator issue in the interface language. Schema-level
 * detail never reaches this point (the server keeps it behind a reference);
 * expression issues add the formula language's own diagnostic.
 */
export function issueText(t: Translate, te: (key: string) => boolean, issue: DraftIssue): string {
  const key = `builder.issue.${issue.code}`
  if (!te(key)) return issue.message
  const text = t(key, issue.params ?? {})
  return issue.code.startsWith('expression_') ? `${text}: ${issue.message}` : text
}

/** Where an issue sits, as the name of the property-panel tab that holds it (never a raw path). */
const AREAS: [RegExp, string][] = [
  [/^conditions(\.|$)/, 'rules'],
  [/^(validation|validationRules)(\.|$)/, 'validation'],
  [/^options(\.|$)/, 'options'],
  [/^(storage|relation|subform)(\.|$)/, 'data'],
  [/^(behavior|default|formula)(\.|$)/, 'behavior'],
  [/^events(\.|$)/, 'events'],
  [/^(table|export)(\.|$)/, 'table'],
  [/^(i18n|ui|key|type)(\.|$)/, 'general'],
]

export function issueArea(t: Translate, property: string | undefined): string {
  if (!property) return ''
  const hit = AREAS.find(([re]) => re.test(property))
  return hit ? t(`builder.tab.${hit[1]}`) : ''
}
