// The verification report of a pull request may only cite a CI run that finished, and passed every job, on
// the pull request's head commit (specification §8.2, ADR-0068). Run by .github/workflows/report.yml on every
// push to the pull request and every edit of its description; the pure part is tested by check-report.test.mjs.

/** The line the report states its run with: `CI run [ID](…/actions/runs/ID) on the head commit `SHA``. */
const CITATION = /^CI run \[(\d+)\]\(https:\/\/github\.com\/([\w.-]+\/[\w.-]+)\/actions\/runs\/(\d+)\) on the head commit `([0-9a-f]{7,40})`/gm

/** What is wrong with the report's citation, or [] when it describes exactly what would be merged. */
export function problems({ body, repo, headSha, run, jobs }) {
  const found = [...(body ?? '').matchAll(CITATION)]
  if (found.length === 0)
    return ['The report cites no CI run. Section 2 must state: CI run [ID](https://github.com/OWNER/REPO/actions/runs/ID) on the head commit `SHA`.']
  if (found.length > 1) return [`The report cites ${found.length} runs as the head commit's; it must cite exactly one.`]
  const [, id, citedRepo, linkId, sha] = found[0]
  const out = []
  if (id !== linkId) out.push(`The cited run ${id} links to run ${linkId}.`)
  if (citedRepo !== repo) out.push(`The cited run belongs to ${citedRepo}, not ${repo}.`)
  if (!headSha.startsWith(sha)) out.push(`The report names commit ${sha}, but the head commit is ${headSha.slice(0, 7)}.`)
  if (run === null) return [...out, `Run ${id} does not exist in ${repo}.`]
  if (run.path !== '.github/workflows/ci.yml') out.push(`Run ${id} is not a CI run (${run.path}).`)
  if (run.head_sha !== headSha) out.push(`Run ${id} tested ${run.head_sha.slice(0, 7)}, not the head commit ${headSha.slice(0, 7)}.`)
  if (run.status !== 'completed') out.push(`Run ${id} has not finished (${run.status}).`)
  else if (run.conclusion !== 'success') out.push(`Run ${id} did not pass (${run.conclusion}).`)
  // A run passes only when every job ran and passed: a cancelled or skipped job proves nothing.
  const bad = (jobs ?? []).filter((j) => j.conclusion !== 'success')
  for (const j of bad) out.push(`Job "${j.name}" of run ${id}: ${j.conclusion ?? j.status}.`)
  if (run.status === 'completed' && (jobs ?? []).length === 0) out.push(`Run ${id} has no jobs.`)
  return out
}

async function main() {
  const { GITHUB_TOKEN: token, GITHUB_REPOSITORY: repo, PR_BODY: body, HEAD_SHA: headSha } = process.env
  const api = async (path) => {
    const res = await fetch(`https://api.github.com/repos/${repo}${path}`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/vnd.github+json', 'X-GitHub-Api-Version': '2022-11-28' },
    })
    if (res.status === 404) return null
    if (!res.ok) throw new Error(`GET ${path}: ${res.status} ${await res.text()}`)
    return res.json()
  }
  const id = [...(body ?? '').matchAll(CITATION)][0]?.[1]
  const run = id ? await api(`/actions/runs/${id}`) : null
  const jobs = run ? ((await api(`/actions/runs/${id}/jobs?filter=latest&per_page=100`))?.jobs ?? []) : []
  const found = problems({ body, repo, headSha, run, jobs })
  if (found.length) {
    for (const p of found) console.log(`::error title=Verification report::${p}`)
    console.log('\nCite a CI run that finished on the head commit with every job passing, then edit the description to re-run this check.')
    process.exit(1)
  }
  console.log(`The report cites run ${id}, which passed every job on the head commit ${headSha.slice(0, 7)}.`)
}

if (process.argv[1] === new URL(import.meta.url).pathname) await main()
