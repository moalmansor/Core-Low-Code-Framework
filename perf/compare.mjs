// Compares a performance run with the budgets of architecture §20.2 and with
// the engine's baseline: a result over its budget, or more than 20 % slower
// than the baseline, fails the run.
//
//   node perf/compare.mjs --k6 k6-summary.json --measure perf-measure.json \
//     --render perf-render.json --baseline perf/baseline/mysql.json --out perf-results.json
//
// A Markdown table goes to stdout (and to $GITHUB_STEP_SUMMARY in CI).

import { appendFileSync, existsSync, readFileSync, writeFileSync } from 'node:fs'

const args = Object.fromEntries(process.argv.slice(2).reduce((acc, a, i, all) => (a.startsWith('--') ? [...acc, [a.slice(2), all[i + 1]]] : acc), []))
const read = (path) => (path && existsSync(path) ? JSON.parse(readFileSync(path, 'utf8')) : null)

const config = read(new URL('./budgets.json', import.meta.url).pathname)
const k6 = read(args.k6)
const measure = read(args.measure) ?? {}
const render = read(args.render) ?? {}
const baseline = read(args.baseline)
const tolerance = config.regression_tolerance

const results = {}
const rows = []
let failed = false
for (const b of config.budgets) {
  let value = null
  // A trend without samples (every request of that kind failed) was not measured, whatever its p95 says.
  if (b.source === 'k6') value = k6?.metrics?.[b.metric]?.values?.count > 0 ? k6.metrics[b.metric].values['p(95)'] : null
  if (b.source === 'k6-rate') value = k6?.metrics?.[b.metric] ? k6.metrics[b.metric].values.rate * 100 : null
  if (b.source === 'measure') value = measure[b.key]?.value ?? null
  if (b.source === 'render') value = render[b.key]?.value ?? null
  const base = baseline?.[b.key] ?? null
  let verdict = 'pass'
  if (value === null) verdict = 'not measured'
  else if (b.source === 'k6-rate' ? value >= b.budget : value > b.budget) verdict = 'over budget'
  else if (base !== null && base > 0 && b.source !== 'k6-rate' && value > base * (1 + tolerance)) verdict = `regression (> ${Math.round(tolerance * 100)} % over baseline)`
  if (verdict !== 'pass') failed = true
  results[b.key] = value === null ? null : Math.round(value * 100) / 100
  const fmt = (v) => (v === null ? '—' : `${Math.round(v * 100) / 100} ${b.unit}`)
  rows.push(`| ${b.label} | ${fmt(value)} | ${b.source === 'k6-rate' ? '<' : '≤'} ${b.budget} ${b.unit} | ${fmt(base)} | ${verdict === 'pass' ? '✅ pass' : `❌ ${verdict}`} |`)
}
const rate = k6?.metrics?.http_reqs?.values?.rate
const table = [
  '| Budget | p95 / result | Budget | Baseline | Verdict |',
  '|---|---|---|---|---|',
  ...rows,
  '',
  rate === undefined
    ? ''
    : `Load: ${rate.toFixed(1)} requests/s on average over the run (${args.rate ?? 50}/s sustained, then ${args['peak-rate'] ?? 150}/s peak${args.rate && Number(args.rate) !== 50 ? `; §20.2 sets 50/s and 150/s for the reference environment of §20.1, see docs/performance.md` : ''}).`,
].join('\n')
console.log(table)
if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `\n### Performance (${args.engine ?? 'engine'})\n\n${table}\n`)
if (args.out) writeFileSync(args.out, JSON.stringify(results, null, 2) + '\n')
process.exit(failed ? 1 : 0)
