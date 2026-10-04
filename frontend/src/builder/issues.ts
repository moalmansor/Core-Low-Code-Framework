import type { DraftIssue } from './types'

type Translate = (key: string, named?: Record<string, unknown>) => string

/**
 * The text of a DraftValidator issue in the interface language: a translated
 * sentence per issue code, followed by the server's detail where the detail
 * names something specific (an expression error, a schema violation).
 */
export function issueText(t: Translate, te: (key: string) => boolean, issue: DraftIssue): string {
  const key = `builder.issue.${issue.code}`
  if (!te(key)) return issue.message
  const text = t(key, issue.params ?? {})
  return issue.code === 'schema' || issue.code.startsWith('expression_') ? `${text}: ${issue.message}` : text
}
