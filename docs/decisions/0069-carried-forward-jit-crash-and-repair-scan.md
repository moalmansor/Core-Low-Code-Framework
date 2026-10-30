# ADR-0069: The two items carried into Phase 6 — the PHP JIT crash and the run 152 repair scan

- Status: Accepted (Phase 6)
- Date: 2026-10-29

## Context

Two failures were set aside in earlier phases and carried into Phase 6:

1. **PHP JIT crash.** During Phase 2 the end-to-end jobs crashed with a PHP segfault on the form publish analysis. CI and the image were set to keep the OPcache JIT off, and the cause was never explained.
2. **Repair scan "timeout".** In CI run 152 (Phase 4), the Docker stack on SQL Server waited 20 seconds for a broken-link scan that never showed a result. The next runs passed. It was recorded as a SQL Server scan timeout without a cause.

## Findings

### 1. The JIT crash is in PHP 8.3.35's engine, not in this code

- **What the October 9 logs show.** CI run 37942031807 printed the kernel line:

  ```
  php8.3[5607]: segfault at 7f111ca267d0 ip 000055a367dc887d … error 6 in php8.3[3ba87d,…]
  ```

  That is a write fault at an offset inside the PHP binary itself, not in application code or in JIT output.
- **The replay isolated OPcache.** In run 37947084879 (also PHP 8.3.35), the same publish analysis was replayed from the CLI against that job's database:

  | Configuration | Result |
  |---|---|
  | Plain | Passed |
  | Garbage collector off | Passed |
  | File cache | Passed |
  | OPcache with setup-php's JIT | Exit 139 (segfault) on the first form |

- **It does not reproduce on other PHP versions:**
  - **PHP 8.3.6 (locally).** The same analysis was forced through the tracing and function JIT with every hot threshold at 1, so all code is compiled at once, for 30 rounds. No crash.
  - **PHP 8.3.36, today's CI version.** The new "PHP JIT" workflow (`.github/workflows/php-jit.yml`) runs the whole engine suite inside gdb with PHP's debug symbols, under both JIT modes. That is 190 tests, including every publish. Both modes exited normally (run 40832062414).
- **Conclusion.** The crash was a defect of PHP 8.3.35's JIT, inside the engine. A newer PHP release does not have it, and no application change was needed or possible.

**Decision.**
- The JIT stays off in the image and in CI. The reference environment of the performance budgets (architecture §20.1) is sized without it. The workload is database- and I/O-bound, where the JIT gains little and adds an engine risk that has already bitten once.
- The "PHP JIT" workflow keeps running the engine suite under the JIT on every push to a phase branch and to `main`. If a PHP release crashes again, it prints the C backtrace instead of leaving a guess. If the JIT is ever to be turned on, it can be, on evidence.
- The workflow never blocks a merge, because production does not run the JIT.

### 2. Run 152 was not a SQL Server timeout: the scan was queued and the page never asked for it again

The job log of the failing Docker job (SQL Server, run 38344572557) holds the nginx and PHP-FPM access logs of the window.

- **The scan was queued.** At 04:16:39 the page loaded the last scans (`GET /forms/{form}/repair`, 64 bytes) and started a scan (`POST /forms/{form}/repair`, 202, 191 bytes).
  - nginx counts chunked-encoding framing in these sizes: 11 bytes for a body under 256 bytes, 12 for one under 4 096.
  - That makes the bodies exactly 53 bytes (no scans yet) and 180 bytes. 180 is a scan in status `queued`. A `running`, `completed` or `failed` scan would be 181, 198 or more.
- **The page then sent nothing for 22 seconds.** The next request is the following test signing in at 04:17:01. The only traffic in between is the 5-second health check, which PHP-FPM answered every time, so the server was not saturated.
  - The panel polls a queued scan every 1.5 seconds, so its poll never ran.
  - A slow or blocked scan on SQL Server would not explain this. The browser never asked.
- **Ruled out:**
  - **The data-quality tab hiding the panel.** It is hidden when the form reads as unpublished. Rebuilding that tab's response on SQL Server today, with the same rule, tag length and finished sweep, gives exactly 585 bytes with `"published":true`. 585 + 12 = 597, as logged.
  - **A remount of the panel.** A remount would have reloaded the last scans, and only one such request was made.
  - **The database's isolation.** `READ_COMMITTED_SNAPSHOT` is on in both CI and `db:ensure`, so readers never wait for writers.
- **What remains unconfirmed.** The run's Playwright trace, which would show the browser side, is in an artifact that the environment used for this investigation could not download (its proxy refuses GitHub's artifact storage). The client-side step that stopped the poll is therefore narrowed but not confirmed.
- **What changed since.** The panel at that commit replaced its whole state when the last scans arrived, and let a scan start before they had. It was rewritten the next day (`7d51171`): a scan can start only after the state has loaded, and the state never replaces a scan started meanwhile. The end-to-end test now polls the server for the scan's own status and prints its error. The Docker SQL Server job has passed this step in every run since.

**Decisions:**
- The data-quality tab shows the repair panel only once its own document has loaded, as it already did for the duplicate checks. A scan cannot start while the tab is still deciding whether the form is published.
- **A defect found in the same log is fixed.** The worker's last 200 lines were one `ErrorAlertMail` failing about every millisecond. The cause:
  - the `mail` supervisor ran jobs with unlimited attempts and no backoff;
  - the alert e-mail set no limit of its own;
  - outgoing mail was not configured in that stack.

  Now:
  - error alerts are not queued while outgoing mail is not configured (the group is in Error Monitoring either way);
  - an alert is tried at most three times, a minute and five minutes apart;
  - the `mail` supervisor gives one attempt to any job that sets none of its own.

## Consequences

- No unexplained failure is carried out of Phase 6. One is explained down to the PHP release. The other is shown not to be a database problem, with the remaining client-side detail stated as unconfirmed.
- A future PHP release that crashes under the JIT is caught with a backtrace.
- A misconfigured mail server can no longer turn one error into a loop of retries.
