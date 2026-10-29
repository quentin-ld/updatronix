# Update engine — Phase 4.3b queue live preparation

> **Superseded (2026-10-29, Step 4).** The queue switch, the queue table, the
> queue REST routes, the manual actions, and the queue Health checks are
> deleted. `queue_live`/`queue_enabled` no longer exist; the pass is always on
> unless the `updatronix_pro_update_pass_enabled` filter says otherwise. Keep
> this file as history.

**Date:** 2026-10-26
**Scope:** Pro `inc/update-engine/`, `inc/update-shield/`, Pro bootstrap,
settings/UI, the local live fixture, and the Free Health filter. Free receives
the Health filter, documentation, and the phase log.
**Status:** Implemented and verified locally. The queue remains **dormant**:
`queue_live` is false, `queue_enabled` is false by default, the engine kill
switch is false by default, and the Shield switch is disabled. The owner
decision on `queue_live=true` was **keep dormant** on 2026-10-26: no activation
code was changed.
**Related:** ADR `45-update-engine-architecture.md` §5.6, §5.7, §5.9, §6–§8,
§11, §12.2, §14; 4.2 report `46`; 4.3a report `47`; `FEAT-P-QUEUE`,
`FEAT-P-SHIELD`; implementation log `11`; task index `10`.

---

## 1. What landed

| Area | Change |
|---|---|
| Live fixture | A local integration fixture runs the real scheduler, kernel, native executor, Free journal, notifier, and REST callbacks against local plugin and theme packages. Offers are injected through `pre_set_site_transient_update_plugins` / `_update_themes`; packages are local zips; WordPress.org update checks are answered synthetically, so no socket is opened. |
| Scenarios | Plugin success, theme success, Shield block, retryable execution failure, terminal failure + REST retry, cancel, expired lease with target installed, expired lease with source installed, expired lease with an unknown version, duplicate worker against the native lock, duplicate planning, and one digest per run. |
| Invariants | One native `auto_updater.lock`; no double execution; one Free log row per transition; blocked history includes the block; one digest per run, not per item; a successful run sends no email. |
| Queue summary | `Updatronix_Queue_Summary` is a read-only snapshot shared by the Shield settings payload, the existing `/queue/summary` route, and Health. It exposes pending/running/failed/done/blocked/uncertain counters, the last run, the next worker and next queued job, stale leases, and dormancy warnings. |
| Shield UI | The existing Update queue card shows the counters, dates, and warnings. Retry/cancel/run actions reuse the existing queue routes and are offered only when `queue_live` is true. No new tab and no separate screen. The switch remains disabled while `queue_live` is false. |
| Health | Free exposes an additive `updatronix_health_checks` filter. Pro appends five read-only queue checks to the existing Health surface on the main site: table, worker, leases, last run, and dormancy. A dormant queue with actionable jobs is an attention row. |
| Correctness fixes | `Updatronix_Queue_Scheduler::plan()` now passes the kernel's filtered `max_attempts` to each job. `Updatronix_Update_Queue::insert()` writes literal SQL `NULL` for nullable fields, so a fresh running row is not stored as the zero datetime and mistaken for an expired lease. |
| Testability | `Updatronix_Pro_Bootstrap::should_register_queue_health()` isolates the main-site Health rule. |

---

## 2. Local live fixture

### Exact procedure

The fixture is part of the Pro integration suite and uses the real WordPress
test stack. From the Pro plugin directory:

```bash
cd wp-content/plugins/updatronix-pro
bash bin/harness integration
```

The suite includes `tests/Integration/UpdateQueueLiveFixtureTest.php`. It can
also be selected through the regular PHPUnit integration config when a narrower
run is useful:

```bash
cd wp-content/plugins/updatronix-pro
set -a; . ./.config/wp-tests.env; set +a
export WP_TESTS_DIR="$PWD/$WP_TESTS_DIR"
export WP_CORE_DIR="$PWD/$WP_CORE_DIR"
export TMPDIR="$PWD/$TMPDIR"
vendor/bin/phpunit --configuration=.config/phpunit.integration.xml.dist \
  --filter UpdateQueueLiveFixtureTest
```

The second form must run inside the DDEV web container; the harness above is
the supported one-command path.

### Controlled inputs

1. A plugin fixture is installed at `1.0.0` in `WP_PLUGIN_DIR`; a theme fixture
   is installed at `1.0.0` in the theme root.
2. A local zip at `1.1.0` is built in a temporary directory.
3. The offer is injected through `pre_set_site_transient_update_plugins` or
   `pre_set_site_transient_update_themes`. The package URL is a
   `https://fixture.invalid/` URL; `upgrader_pre_download` returns the local
   zip, so the URL is never fetched.
4. `pre_http_request` answers the WordPress.org plugin/theme/core update checks
   with a synthetic 200 response and returns a `WP_Error` for anything else.
   Every intercepted URL is recorded; the success test asserts that only
   `api.wordpress.org` update-check URLs were seen. No package or API socket is
   opened.
5. `pre_wp_mail` captures mail and returns true; nothing is sent.
6. The test sets the Shield `queue_enabled` option directly. It does **not**
   change the `queue_live` filter or the production default. The switch stays
   disabled and the production gate stays closed.

### Scenarios and assertions

| Scenario | Test | Proof |
|---|---|---|
| Plugin success | `test_live_plugin_success_is_one_native_update` | One pending row; one native install; version `1.1.0`; one Free success row; one upgrader completion; native lock held during install; no digest. |
| Theme success | `test_live_theme_success_is_one_native_update` | Theme version `1.1.0`; one native install; no external package fetch. |
| Shield block | `test_live_shield_block_writes_no_file_and_one_log` | `planned=0`, `blocked=1`; row `blocked/wp_incompatible`; file stays `1.0.0`; no upgrader run; one `incompatible` log row; blocked history contains it; no per-item email. |
| Retryable failure | `test_live_execution_failure_retries_then_succeeds` | Download error leaves the row `pending` with attempts `1`; after availability, the next worker installs `1.1.0`. |
| Terminal failure + REST retry | `test_live_terminal_failure_and_rest_retry` | Exhausted attempt reaches `failed/attempts_exhausted`; the existing retry callback moves it to `pending`; the next worker installs `1.1.0`. |
| Cancel | `test_live_cancel_prevents_execution` | The existing cancel callback reaches `canceled`; the worker does not install; version stays `1.0.0`. |
| Crash, target installed | `test_live_expired_lease_target_installed_is_recovered` | Expired running row becomes `succeeded/recovered_after_crash`; no upgrader run. |
| Crash, source installed | `test_live_expired_lease_source_requeues_with_backoff` | Expired row returns to `pending` with `recovered_lease_expired` and a future `available_at`. |
| Crash, unknown version | `test_live_expired_lease_unknown_version_is_uncertain` | Expired row becomes `uncertain/partial_state`; no auto-retry. |
| Duplicate worker | `test_live_duplicate_worker_respects_native_lock` | A held native lock produces `pending/lock_busy`, attempts `1`, no install; after release the job succeeds. |
| Duplicate planning | `test_live_duplicate_plan_does_not_double_run` | Two ticks produce one row; two workers produce one install. |
| Digest | `test_live_digest_is_one_per_run_not_per_item` | Two failed items in one run produce one email; the second notifier call is deduplicated by the run event key. |

The suite is single-site. A live Multisite pass remains unavailable.

---

## 3. Queue summary in Update Shield

`Updatronix_Queue_Summary::get()` returns:

- `counts` — the persisted statuses;
- `display_counts` — pending, running (`claimed + running`), failed, done
  (`succeeded`), blocked, uncertain;
- `last_run` — status, start/finish, processed jobs, and failed steps from the
  bounded run option;
- `next_run` — the next worker event;
- `next_due` — the earliest pending `available_at`;
- `stale_leases`, `oldest_stale_lease`, `active_run_id`, `last_error`;
- `warnings` — `table_missing`, `stale_leases`, `dormant_jobs`,
  `worker_missing`.

The Shield settings payload embeds the snapshot so the card works even while
the engine is dormant and the `/queue` routes are not registered. The existing
`/queue/summary` route returns the same shape. The UI uses the existing
`/queue/{id}/retry`, `DELETE /queue/{id}`, and `POST /queue/run` routes; it
does not invent a route and it hides those actions while `queue_live` is false.
The switch remains disabled.

The Free Health surface receives five checks through a new additive filter:

| Check | Behavior |
|---|---|
| `queue_table` | Present/missing; missing is attention. |
| `queue_worker` | Scheduled/dormant/missing; a live enabled queue without a worker is attention. |
| `queue_leases` | Expired leases are attention. |
| `queue_last_run` | The last bounded run; failed runs are attention. |
| `queue_dormant_jobs` | Attention when `queue_live=false` and actionable jobs exist. |

No second Health screen and no second queue screen was added.

---

## 4. Dormancy and the owner gate

The queue remains dormant for three independent reasons:

1. `updatronix_pro_update_engine_enabled` is false by default.
2. `queue_enabled` is false by default and forced false on save while
   `updatronix_pro_queue_live` is false.
3. The Shield switch is disabled with the dormant copy.

The 4.3b live fixture sets `queue_enabled` directly in the test database only.
It does not change `queue_live`, does not change the default, and does not
enable a production worker. No code in this phase flips the live gate.

**Owner decision (2026-10-26): keep dormant.** The question
“Active-t-on `queue_live=true` ?” was asked; the owner chose not to activate.
The activation conditions below remain a future checklist, not a current
release. No production default, save path, switch, or kill switch was changed.

### Go-live checklist

- [x] Local live fixture covers enqueue → worker → native install → log →
  digest for plugin and theme.
- [x] Shield block, retry, terminal failure, cancel, crash recovery, expired
  lease, duplicate worker, and duplicate planning are covered.
- [x] One native lock; no double execution; one Free journal; blocked history;
  one digest per run; no per-item email.
- [x] Queue summary on the existing Shield screen; actions use existing routes;
  switch disabled while `queue_live` is false.
- [x] Health checks on the existing Health surface; dormant-jobs warning.
- [x] Full unit, integration, JS, lint, and build matrix green.
- [ ] A staged end-to-end run on a real host with real cron (the integration
  fixture is local).
- [ ] A live Multisite pass (environment gap).
- [ ] Explicit owner approval in the conversation. **The owner chose to keep
  the queue dormant on 2026-10-26; a new decision is required.**
- [ ] Flip the engine kill switch, `queue_live`, and the save/migration path
  together; re-run the matrix.
- [ ] Update the docs, task file, and release notes after activation.

Without the last four items, the queue stays dormant.

---

## 5. Correctness fixes pinned by 4.3b

- **4.3b-D1 — Local live fixture.** The fixture is a real WordPress integration
  test with local zips and synthetic WordPress.org update-check responses. It
  is not a staging run; no external socket is opened.
- **4.3b-D2 — Read-only summary.** The summary is shared by the Shield payload,
  REST, and Health. It never claims, schedules, cancels, or executes.
- **4.3b-D3 — Existing surfaces only.** No new queue tab, no second Health
  screen, no new route. Actions are the existing retry/cancel/run routes and
  are hidden while `queue_live` is false.
- **4.3b-D4 — Health filter.** Free owns the Health surface; Pro appends checks
  through `updatronix_health_checks` on the main site only.
- **4.3b-D5 — Null insert.** Nullable queue fields are written as SQL `NULL`.
  A zero datetime is not a lease.
- **4.3b-D6 — Max attempts.** The scheduler passes the kernel's filtered
  `max_attempts` to planned jobs; the filter is no longer ignored by the plan.
- **4.3b-D7 — Live gate remains closed.** `queue_live` false, switch disabled,
  save path forced false, kill switch false by default. The owner decided on
  2026-10-26 to keep the queue dormant; activation requires a new decision.
- **4.3b-D8 — Flow untouched.** The Flow runner, chains, `POST /flow/run`, and
  Flow screen are out of scope; 4.4 closes the bypass.

---

## 6. Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 313 tests, 1107 assertions, pass. |
| Pro `bin/harness integration` | 95 tests, 900 assertions, pass; 1 intentional Multisite-only skip. |
| Pro `bin/harness test:js` | 53 tests, pass. |
| Pro `bin/harness lint:js` | Pass, 0 warnings. |
| Pro `bin/harness lint:files` on modified PHP | Pass, 0 errors. |
| Pro `bin/harness lint` (full) | Not run in 4.3b; `lint:files` on every changed PHP file is green. |
| Pro `bin/harness build` | Pass. |
| Pro `bin/harness counterfactual` | Full run on 4.2 + 4.3a + 4.3b: 200 of 200 executed changed lines checked; 92 killed, 107 survived, 1 classified equivalent. Seven survivors were killed manually by targeted tests; 100 documented. **Not a pass.** |
| Free `bin/harness test` | 216 tests, 422 assertions, pass. |
| Free `bin/harness integration` | 78 tests, 212 assertions, pass, 13 pre-existing skips. |
| Free `bin/harness lint:files` on modified PHP | Pass, 0 errors (informational warnings only). |
| Free `bin/harness build` | Pass. |
| E2E | Not run; still blocked by `libnspr4.so`. Not claimed. |
| Multisite live | Not available; the Super-Admin matrix and the guarded main-site code path remain the control. |

---

## 7. Counterfactual

The full run used base `e436a5f` (4.2 + 4.3a + 4.3b) and checked every executed
changed line:

```bash
cd wp-content/plugins/updatronix-pro
HARNESS_MUTATION_BASE=e436a5f HARNESS_COUNTERFACTUAL_MAX=202 \
  bash bin/harness counterfactual
```

Result: 791 substitutable changed lines, 200 executed by the unit suite, 200
checked; **92 killed, 107 survived, 1 classified equivalent**. Seven survivors
were killed manually after the gate run by targeted unit tests (the one-shot
migration flag/false value and the abandoned scan items); the remaining 100 are
listed in the Pro QA note with integration/unit-gap dispositions. The gate is
**not a pass**; the 100 lines are documented, not claimed as killed.

The separate `bin/harness mutation` gate was not run in 4.3b; the counterfactual
gate was chosen for this diff.

## 8. Limits

1. The local fixture is not a staging or production run. Real cron timing,
   real hosting filesystem behavior, and a real fatal-error loopback are not
   exercised.
2. No live Multisite pass.
3. No real disk-full test.
4. The queue remains dormant; `queue_live` is false.
5. Flow still uses the old runner and bypasses Shield until 4.4.
6. E2E remains blocked by the host library.
7. No version bump, no commit, no legacy path.

---

## 9. Next

Phase 4.4 owns the Flow planner, `POST /flow/run`, `GET /flow/runs/{run_id}`,
per-step UI, and deletion of the old runner. This phase stops before 4.4.
