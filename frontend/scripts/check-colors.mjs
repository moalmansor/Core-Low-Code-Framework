/* global console, process, URL */
// Keeps colours in the theme (docs/design-system.md): outside src/theme/ no
// component may write a colour value or use a palette that bypasses the
// semantic tokens. Run by `npm run lint`.
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative, sep } from 'node:path'

const root = new URL('../src/', import.meta.url).pathname
const rules = [
  [/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?(?:[0-9a-fA-F]{2})?\b/g, 'a raw hex colour; use a token from src/theme/tokens.css'],
  [/\b(?:rgb|rgba|hsl|hsla|oklch)\(\s*\d/g, 'a raw colour function; use a token'],
  [
    /\b(?:bg|text|border|ring|outline|divide|fill|stroke|from|via|to|accent|decoration|placeholder|caret|shadow)-(?:red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|slate|gray|zinc|neutral|stone)-\d{2,3}\b/g,
    'a Tailwind palette colour; use a semantic utility (text-danger, bg-subtle, …)',
  ],
  [/\b(?:bg|text|border|ring|outline|divide|fill|stroke)-(?:white|black)\b/g, 'white/black; use bg-card, bg-paper or bg-media'],
  [/\b(?:bg|text|border|ring|outline|divide)-(?:surface|primary)-\d{1,3}\b/g, 'a PrimeVue palette step; use a semantic utility'],
  [/var\(--p-(?:surface|primary|red|orange|amber|yellow|green|emerald|blue|sky|slate|gray|zinc)-\d{1,3}\)/g, 'a PrimeVue palette variable; use a token'],
]

function files(dir) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return files(path)
    return /\.(vue|ts|css)$/.test(name) && !/\.spec\.ts$/.test(name) ? [path] : []
  })
}

const problems = []
for (const file of files(root)) {
  const rel = relative(root, file)
  if (rel.startsWith(`theme${sep}`)) continue
  readFileSync(file, 'utf8')
    .split('\n')
    .forEach((line, i) => {
      for (const [re, why] of rules) {
        for (const m of line.matchAll(re)) {
          if (m[0] === '#app') continue
          problems.push(`src/${rel}:${i + 1}: "${m[0]}" is ${why}`)
        }
      }
    })
}
if (problems.length) {
  console.error(problems.join('\n'))
  console.error(`\n${problems.length} colour(s) outside the theme.`)
  process.exit(1)
}
console.log('Colours: every component uses theme tokens.')
