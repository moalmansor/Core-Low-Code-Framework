import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * The checklist for every new screen (docs/design-system.md §5.7), enforced.
 * Prose alone did not stop flat stacks of controls from shipping in Phase 4,
 * so each rule a source file can be checked for is checked here, on every
 * screen: routed pages, configuration tabs, dialogs and panels. A rule that
 * needs the rendered page (no sideways scroll at phone width, both
 * languages) is checked by e2e/15-screens.spec.ts, which every new screen
 * must be added to.
 *
 * Screens built before the checklist existed are listed in BEFORE_CHECKLIST
 * with the rules they do not meet yet; the list may only shrink. A new
 * screen meets every rule, or the build fails.
 */
const SRC = join(__dirname, '..')

function files(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return files(path)
    return name.endsWith('.vue') ? [path] : []
  })
}

/** Every screen: the views, and the dialogs and panels that open over records. */
const SCREENS = [
  ...files(join(SRC, 'views')),
  // The editors the Phase 4 screens are made of, and the dialogs that open over records.
  ...files(join(SRC, 'components/templates')),
  join(SRC, 'components/config/ChoiceGallery.vue'),
  join(SRC, 'runtime/actions/ActionRunDialog.vue'),
  join(SRC, 'runtime/downloads/DownloadDialog.vue'),
  join(SRC, 'layouts/NotificationsBell.vue'),
]
  .map((f) => relative(SRC, f))
  .sort()

/** Components a route shows as a page (they need a title, §5.1). */
const ROUTED = new Set(readdirSync(join(SRC, 'router')).flatMap((f) => [...readFileSync(join(SRC, 'router', f), 'utf8').matchAll(/import\('@\/(views\/[^']+\.vue)'\)/g)].map((m) => m[1]!)))

type Rule = { id: string; why: string; check: (src: string, file: string) => string[] }

const template = (src: string) => src.slice(src.indexOf('<template>'), src.lastIndexOf('</template>') + 11)
const lines = (src: string, re: RegExp) =>
  src
    .split('\n')
    .map((l, i) => [l, i + 1] as const)
    .filter(([l]) => re.test(l))
    .map(([l, n]) => `line ${n}: ${l.trim().slice(0, 120)}`)

const RULES: Rule[] = [
  {
    id: 'frame',
    why: '§5.1 / §5.7 item 1: a page has a title (h1.page-title), a configuration tab its TabIntro',
    check: (src, file) => (ROUTED.has(file) && !/class="page-title|<TabIntro|<PageHeader/.test(src) ? ['no page title'] : []),
  },
  {
    id: 'one-save-bar',
    why: '§5.7 item 1: one save bar at the bottom',
    check: (src) => ((src.match(/<ConfigSaveBar\b/g) ?? []).length > 1 ? ['more than one save bar'] : []),
  },
  {
    id: 'grouped',
    why: '§5.7 item 2: related settings sit under a heading (ConfigSection); a flat stack of more than six controls is not grouped',
    check: (src) => {
      const controls = (template(src).match(/<(ConfigField|SettingSwitch|I18nInput|TemplateText)\b/g) ?? []).length
      return controls > 6 && !/<ConfigSection\b|<ConfigItem\b|<section\b|<h2\b|<h3\b/.test(src) ? [`${controls} settings with no section or heading`] : []
    },
  },
  {
    id: 'switch-described',
    why: '§5.7 item 3: each switch has a one-line description',
    check: (src) => {
      const tags = template(src).match(/<SettingSwitch\b[^>]*>/gs) ?? []
      return tags.filter((t) => !/\bdescription=|:description=/.test(t)).map((t) => `switch without a description: ${t.replace(/\s+/g, ' ').slice(0, 100)}`)
    },
  },
  {
    id: 'empty-state',
    why: '§5.7 item 5: a table says what it is when it is empty',
    check: (src) => {
      const tables = (template(src).match(/<DataTable\b/g) ?? []).length
      const empties = (template(src).match(/<template #empty>/g) ?? []).length
      return tables > empties && !/<EmptyState\b/.test(src) ? [`${tables - empties} table(s) with neither an #empty slot nor an EmptyState`] : []
    },
  },
  {
    id: 'quiet-help',
    why: '§5.7 item 6: help is quiet text; a Message is only for a real problem or a result',
    check: (src) => lines(template(src), /<Message\b[^>]*severity="info"/),
  },
  {
    id: 'per-locale',
    why: 'CLAUDE.md: translatable text is stored per locale (I18nInput), never as fixed English and Arabic inputs',
    check: (src) => lines(template(src), /v-model="[\w.?!]+\.(en|ar)"|\[['"](en|ar)['"]\]|\.(en|ar)\b\s*\?\?/),
  },
  {
    id: 'empty-value',
    why: '§5.6 / §5.7 item 7: an empty value reads as empty (EmptyValue: "Not filled in"), never a bare dash; a count is a number',
    check: (src) => lines(src, /['"`]—['"`]/),
  },
  {
    id: 'list-join',
    why: 'Arabic and English: lists are joined the way the user language writes them (joinList), not with a fixed comma',
    check: (src) => lines(src, /\.join\(\s*['"`](, |، )['"`]\s*\)/).filter((l) => !/ltr-value|machine-list/.test(l)),
  },
  {
    id: 'logical-sides',
    why: 'Arabic and English: sides are start and end, never left and right',
    check: (src) => lines(src, /\b(margin|padding)-(left|right)\b|text-align:\s*(left|right)|\b(ml|mr|pl|pr)-\d|\bborder-(l|r)-|\b(left|right)-\d|float:\s*(left|right)/),
  },
  {
    id: 'drawer-side',
    why: 'Arabic and English: a drawer opens from the end side, which is the left in Arabic; never a fixed position="right" or "left"',
    check: (src) => lines(template(src), /<Drawer\b[^>]*\sposition="(left|right)"/),
  },
]

/** Screens built before the checklist (Phases 1–3), with the rules they do not meet yet. May only shrink. */
const BEFORE_CHECKLIST: Record<string, string[]> = {
  // Not pages with a title: the not-found page is a message, the form builder has its own toolbar frame.
  'views/NotFound.vue': ['frame'],
  'views/builder/FormBuilder.vue': ['frame'],
  'views/admin/AuditLogView.vue': ['empty-value'],
  'views/admin/LocalesManager.vue': ['empty-state'],
  'views/admin/RolesView.vue': ['list-join', 'quiet-help'],
  'views/admin/SystemHealth.vue': ['empty-value'],
  'views/admin/UsersView.vue': ['empty-value'],
  'views/admin/WorkAdminView.vue': ['list-join'],
  'views/admin/building/BlueprintDetail.vue': ['empty-value', 'list-join'],
  'views/admin/building/BlueprintsView.vue': ['quiet-help'],
  'views/admin/building/FormAccessView.vue': ['empty-value', 'quiet-help'],
  'views/admin/building/FormsView.vue': ['empty-value'],
  'views/admin/building/MigrationPlans.vue': ['empty-value', 'quiet-help'],
  'views/admin/building/SchemaExplorer.vue': ['empty-value', 'list-join', 'quiet-help'],
  'views/admin/building/reference/SequencesPanel.vue': ['empty-value', 'quiet-help'],
  'views/admin/formconfig/RecordAccessEditor.vue': ['list-join'],
  'views/admin/formconfig/SlaEditor.vue': ['switch-described'],
  'views/auth/LoginView.vue': ['quiet-help'],
  'views/builder/FormVersions.vue': ['empty-value', 'quiet-help'],
  'views/profile/ProfileView.vue': ['empty-state', 'quiet-help'],
  'views/records/ConflictDialog.vue': ['empty-value', 'list-join'],
  'views/records/RecordComments.vue': ['empty-value'],
  'views/records/RecordList.vue': ['empty-value'],
  'views/records/RecordView.vue': ['empty-value'],
  'views/work/MyWorkView.vue': ['empty-value'],
}

describe('every screen follows the checklist (design system §5.7)', () => {
  for (const file of SCREENS) {
    it(file, () => {
      const src = readFileSync(join(SRC, file), 'utf8')
      const waived = new Set(BEFORE_CHECKLIST[file] ?? [])
      const found = RULES.filter((r) => !waived.has(r.id)).flatMap((r) => r.check(src, file).map((p) => `${r.id} (${r.why}): ${p}`))
      if (found.length && process.env.SCREENS_REPORT) console.log(`## ${file}\n${found.map((f) => '   ' + f.replace(/ \(.*?\): /, ' | ')).join('\n')}`)
      expect(found.join('\n')).toBe('')
    })
  }
})

describe('the waivers are still needed', () => {
  it('lists only screens that exist', () => {
    expect(Object.keys(BEFORE_CHECKLIST).filter((f) => !SCREENS.includes(f))).toEqual([])
  })
  it.each(Object.entries(BEFORE_CHECKLIST))('%s', (file, ids) => {
    const src = readFileSync(join(SRC, file), 'utf8')
    for (const id of ids) {
      const rule = RULES.find((r) => r.id === id)!
      expect(rule.check(src, file), `${file} now meets "${id}": remove its waiver`).not.toEqual([])
    }
  })
})

describe('the rules catch what they were written for', () => {
  const rule = (id: string) => RULES.find((r) => r.id === id)!
  it('a flat stack of settings', () => {
    expect(rule('grouped').check('<template>' + '<ConfigField />'.repeat(7) + '</template>', 'x.vue')).not.toEqual([])
    expect(rule('grouped').check('<template><ConfigSection>' + '<ConfigField />'.repeat(7) + '</ConfigSection></template>', 'x.vue')).toEqual([])
  })
  it('a table with no empty state, help in an alert, fixed language inputs and physical sides', () => {
    expect(rule('empty-state').check('<template><DataTable :value="rows"></DataTable></template>', 'x.vue')).not.toEqual([])
    expect(rule('quiet-help').check('<template><Message severity="info">Tip</Message></template>', 'x.vue')).not.toEqual([])
    expect(rule('per-locale').check('<template><Textarea v-model="rule.message.en" /></template>', 'x.vue')).not.toEqual([])
    expect(rule('logical-sides').check('.x { margin-left: 4px }', 'x.vue')).not.toEqual([])
    expect(rule('empty-value').check("const when = (iso) => (iso ? f(iso) : '—')", 'x.vue')).not.toEqual([])
    expect(rule('list-join').check("names.join(', ')", 'x.vue')).not.toEqual([])
    expect(rule('switch-described').check('<template><SettingSwitch id="a" v-model="x" label="On" /></template>', 'x.vue')).not.toEqual([])
    expect(rule('drawer-side').check('<template><Drawer :visible="v" position="right"></Drawer></template>', 'x.vue')).not.toEqual([])
    expect(rule('drawer-side').check(`<template><Drawer :visible="v" :position="rtl ? 'left' : 'right'"></Drawer></template>`, 'x.vue')).toEqual([])
  })
})
