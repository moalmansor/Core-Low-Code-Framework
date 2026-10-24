import assert from 'node:assert/strict'
import { test } from 'node:test'
import { problems } from './check-report.mjs'

const repo = 'acme/app'
const headSha = 'a4c1bbf91c8e1397ac6b60c7954c149220321319'
const body = (id = 42, sha = 'a4c1bbf') => `## 2. Tests\n\nCI run [${id}](https://github.com/${repo}/actions/runs/${id}) on the head commit \`${sha}\`: every job passed.\n`
const run = (over = {}) => ({ path: '.github/workflows/ci.yml', head_sha: headSha, status: 'completed', conclusion: 'success', ...over })
const jobs = [
  { name: 'Backend tests (mysql)', conclusion: 'success' },
  { name: 'Backend tests (sqlsrv)', conclusion: 'success' },
]

test('a finished, passing run on the head commit is accepted', () => {
  assert.deepEqual(problems({ body: body(), repo, headSha, run: run(), jobs }), [])
})

test('a run on an earlier commit is refused, even when it passed', () => {
  const p = problems({ body: body(42, '23c302d'), repo, headSha, run: run({ head_sha: '23c302d7df923e543e31645ba0fec486f1f95488' }), jobs })
  assert.equal(p.length, 2)
  assert.match(p.join('\n'), /names commit 23c302d/)
  assert.match(p.join('\n'), /tested 23c302d, not the head commit a4c1bbf/)
})

test('a run still going, cancelled or failed is refused', () => {
  assert.match(problems({ body: body(), repo, headSha, run: run({ status: 'in_progress', conclusion: null }), jobs })[0], /has not finished/)
  assert.match(problems({ body: body(), repo, headSha, run: run({ conclusion: 'cancelled' }), jobs })[0], /did not pass \(cancelled\)/)
  assert.match(problems({ body: body(), repo, headSha, run: run({ conclusion: 'failure' }), jobs })[0], /did not pass \(failure\)/)
})

test('a cancelled or skipped job is refused even when the run reads as passed', () => {
  const p = problems({ body: body(), repo, headSha, run: run(), jobs: [...jobs, { name: 'End-to-end (sqlsrv)', conclusion: 'skipped' }] })
  assert.deepEqual(p, ['Job "End-to-end (sqlsrv)" of run 42: skipped.'])
})

test('no citation, two citations, a link to another run, another repository or another workflow is refused', () => {
  assert.match(problems({ body: 'no run here', repo, headSha, run: null, jobs: [] })[0], /cites no CI run/)
  assert.match(problems({ body: body(1) + body(2), repo, headSha, run: run(), jobs })[0], /cites 2 runs/)
  const other = `CI run [42](https://github.com/${repo}/actions/runs/41) on the head commit \`a4c1bbf\``
  assert.match(problems({ body: other, repo, headSha, run: run(), jobs })[0], /links to run 41/)
  assert.match(problems({ body: body(), repo: 'acme/other', headSha, run: run(), jobs })[0], /belongs to acme\/app/)
  assert.match(problems({ body: body(), repo, headSha, run: run({ path: '.github/workflows/autobuild.yml' }), jobs })[0], /not a CI run/)
})

test('a run that does not exist is refused', () => {
  assert.match(problems({ body: body(), repo, headSha, run: null, jobs: [] }).at(-1), /does not exist/)
})
