import ar from '../../../backend/resources/ui-strings/ar.json'
import en from '../../../backend/resources/ui-strings/en.json'

// One catalog file per locale plus one file per area in `ui-strings/{locale}/`,
// merged the same way the server merges them.
const areas = import.meta.glob<Record<string, string>>('../../../backend/resources/ui-strings/*/*.json', { eager: true, import: 'default' })

function merged(locale: string, base: Record<string, string>): Record<string, string> {
  const out = { ...base }
  for (const [path, messages] of Object.entries(areas)) {
    if (path.includes(`/ui-strings/${locale}/`)) Object.assign(out, messages)
  }
  return out
}

export const bundled: Record<string, Record<string, string>> = { en: merged('en', en), ar: merged('ar', ar) }
