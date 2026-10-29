# Update engine — Phase 4.4 Flow planner

**Date:** 2026-10-27
**Scope:** Pro `inc/update-engine/`, `inc/update-flow/`, `inc/classes/`,
`assets/src/`, and the unit/integration/JS suites. Free documentation only.
**Status:** Superseded by Step 5 (2026-10-29). This document records the 4.4
planner/runner design; the runner, `Run now`, `POST /flow/run`,
`GET /flow/runs/{id}`, the cooldown, manual resume, and polling are removed.
Flow now orders the one automatic pass. The queue/kernel references below are
historical.
**Related:** `45-update-engine-architecture.md` §5.5, §5.8, §6, §7, §8, §10,
§11, §12.3, §14; `49-update-engine-4.3c-counterfactual.md`; Pro
`.agents/tasks/2026-10-27-update-engine-flow.md`.

---

## 1. Summary

1. Update Flow is now a planner above the shared kernel. The inline runner is
   deleted; there is no wrapper and no second execution path.
2. `Updatronix_Flow_Planner` validates installed items, the 20-chain/50-step
   limits, duplicate steps, core-last, and cycles; it builds one stable
   topological order over the merged graph and deduplicates by item and target.
3. Every Flow job is enqueued through `Updatronix_Update_Kernel`, so the ordered
   Shield policy chain runs at enqueue and again immediately before execution.
   A denial is terminal for the step and stops the run; downstream rows and
   facts are canceled.
4. `POST /flow/run` returns a `run_id`; `GET /flow/runs/{run_id}` returns the
   live per-step summary. A manual run executes one step and schedules the next
   one; the automatic Flow tick follows the same one-step rule.
5. Flow storage is `{version:1, chains:[…]}` only. A bare list is migrated once
   and is not dual-read afterwards.
6. The Flow page has a real Run now action per chain, per-step status badges,
   a run summary, polling, and a truthful `Enable` toggle. No tab was added.
7. The scheduler plans Flow at `wp_maybe_auto_update` priority 1, after
   discovery and before `WP_Automatic_Updater::run()` at priority 10. Flow runs
   even while the automatic queue is off; the queue does not own Flow.
8. The engine kill switch is on by default in 4.4 because Flow executes through
   the kernel. `queue_enabled`, `queue_live`, and the Shield queue switch stay
   off; the automatic queue remains dormant.
9. The three Flow-relevant 4.3c gaps are closed by targeted tests: integer row
   hydration, the plan `created_at` fallback, and the license callback cast.
10. No version bump, no commit, no legacy path, no second lock, journal, or
    table.

---

## 2. What changed

### 2.1 Planner

`inc/update-engine/class-updatronix-flow-planner.php` implements the
`Updatronix_Update_Planner` contract. It is deterministic and side-effect free
except for reading the catalogue and the native transients.

Validation:

- at most 20 chains per plan and 50 steps per chain, both filterable through
  `updatronix_pro_flow_max_chains` and `updatronix_pro_flow_max_steps`;
- every step parses as `plugin:<file>`, `theme:<stylesheet>`, or
  `core:wordpress`;
- plugin and theme steps resolve to an installed item; core resolves to
  `wordpress`;
- no duplicate step inside a chain;
- core appears at most once and only as the last step of its chain;
- a cycle across the shared graph is rejected with the loop path.

Ordering:

- one node per `type:id`, one edge per consecutive pair;
- stable Kahn topological sort; tie-break by chain list order, then step index,
  then item id;
- one job per item and target version; later occurrences are
  `deduplicated` facts;
- `sequence` is the position in the declaring chain; `priority` is the global
  topological rank. The kernel claim order is priority, sequence, id.

Offer resolution:

- the installed version and the current native offer are read once per node;
- no offer, an empty target, or `target === installed` becomes a `skipped`
  fact; no queue row is written and the chain continues;
- an item that disappeared after the chain was saved becomes a `blocked` fact
  with `flow_item_unknown`; the chain stops at that step and later steps are
  `canceled`.

### 2.2 Plan value object

`Updatronix_Update_Plan` now also carries `canceled[]` and `errors[]` facts.
`add_canceled()` records a step that will not run because the run stopped;
`add_error()` marks the plan non-executable. This is additive to the 4.2 shape.

### 2.3 Runner (superseded 2026-10-29)

> The runner described here is deleted. The pass order and pass runner in
> `inc/update-engine/` own execution; Flow only plans.

`inc/update-flow/class-updatronix-flow-runner.php` (`Updatronix_Flow_Runner`)
plans, enqueues, persists the plan summary, and starts one worker step.

- One active Flow row (`pending`, `claimed`, or `running`) blocks a new run with
  `flow_run_active` (409).
- A manual run may resume `failed`, `uncertain`, or `canceled` rows into the new
  run after an explicit request. A fresh policy denial on a terminal row is
  surfaced by the kernel even when the job key already exists, so the resume is
  refused. A blocked row must pass the Shield recheck first; it is never
  resumed directly.
- An automatic tick never resurrects a terminal row.
- A policy denial at enqueue inserts the blocked row, records the denial, stops
  the run, and cancels pending rows after the blocked job's topological rank.
- A failure/uncertainty at execution cancels pending rows after that step across
  the merged run, not only within its declaring chain.
- A retryable failure leaves the row pending with backoff and does not stop the
  chain.
- The run summary is stored in the existing bounded
  `updatronix_pro_update_runs` option (last 20 runs). No second history store.

### 2.4 Policy chain

Every Flow job goes through `Updatronix_Update_Kernel::enqueue()` and
`execute_job()`, which evaluate the same ordered chain as the automatic queue:
license, engine, native guards, WP compatibility, PHP compatibility, Free delay,
VCS/Composer, abandoned advisory, pending offer, and chain dependency.

The chain dependency policy now allows a new pending row at enqueue (id 0) and
remains authoritative before execution. Without that guard a chain would block
its own second step at enqueue because the first row is still pending. The queue
claim still skips a downstream row until every earlier row in the same chain has
succeeded. The policy is not bypassed; it is evaluated at the point where the
predecessor state is meaningful.

### 2.5 Scheduler

> **Superseded (2026-10-29, Step 4).** The scheduler is deleted. The automatic
> order is built once per pass at the first gate call, drained by one
> `updatronix_pro_update_pass_follow_up` event, and Flow execution is frozen
> until Step 5. The paragraph below describes the retired scheduler.

`Updatronix_Queue_Scheduler::tick()` plans and starts one Flow step at
`wp_maybe_auto_update` priority 1. Flow planning is independent of
`queue_enabled`; that switch only controls the automatic queue. The worker still
serializes one job per run and schedules the next pending row at its
`available_at`.

The scheduler no longer schedules a second execution path. The old
`updatronix_before_update_check` Flow hook, the transient lock, and
`class-updatronix-update-flow-runner.php` are removed.

### 2.6 REST

| Method | Route | Gate |
|---|---|---|
| `POST` | `/updatronix-pro/v1/flow/run` | Pro capability, Super Admin on Multisite, active license, engine available, nonce via cookie auth, per-user/per-chain 5-minute cooldown. |
| `GET` | `/updatronix-pro/v1/flow/runs/{run_id}` | Same gate; run id is 32 lowercase hex. |

`POST /flow/run` accepts a chain id or no chain id (all enabled chains). It
never accepts a package URL, path, callable, shell command, or item list. It
returns `run_id`, `status`, `planned`, `blocked`, `processed`,
`stale_offers`, `offers_checked_at`, and the current `summary`. The summary
contains `steps[]`, `deduplicated[]`, `skipped[]`, `blocked[]`, `canceled[]`,
counts, and errors.

The route does not perform a fresh network discovery. It reads the current
native transients and reports `stale_offers=true` when the newest
`last_checked` is older than six hours (filterable through
`updatronix_pro_flow_offers_ttl`). The regular `wp_maybe_auto_update` tick owns
discovery.

### 2.7 Storage migration

`Updatronix_Update_Flow::maybe_migrate()` runs once from the Pro bootstrap:

- a bare list is wrapped as `{version:1, chains:[…]}`;
- a v1 shape is left untouched;
- an unknown/future shape is not read;
- `get_saved_chains()` reads v1 only; there is no legacy shim.

### 2.8 UI (superseded 2026-10-29)

> The Run now action, polling, and run card below are removed. The page is
> read-only for execution and renders Waiting/Blocked step facts plus a
> read-only Last pass card.

`UpdateFlowPage.js`:

- `Run now` on every enabled chain; disabled while a run is active, while the
  surface is saving, or while there are unsaved edits;
- the scheduled toggle is labelled `Enable <chain>`, not `Run`;
- polling `GET /flow/runs/{id}` every 2 seconds while pending/running;
- per-step badges for `pending`, `running`, `succeeded`, `failed`, `skipped`,
  `blocked`, and `canceled`, with the machine reason;
- a run card with the short run id, status, counters, offer-staleness warning,
  and plan errors;
- one tab, one surface; no new navigation entry.

The copy says that enabled chains run on the regular update check one step at a
time, that Run now starts a chain immediately, and that Shield policies are
checked on every step. It does not promise automatic dependency discovery or a
full-chain re-plan on retry.

### 2.9 Engine default

`updatronix_pro_update_engine_enabled` now defaults to true so Flow can execute
through the kernel. The automatic queue still defaults to off and is canceled by
the tick while `queue_enabled` is false. `queue_live` is still false, the Shield
switch is still disabled, and no queue route advertises live work. The constant
`UPDATRONIX_PRO_UPDATE_ENGINE_ENABLED` and the filter remain the emergency stop.

---

## 3. Run status model

Queue statuses are internal; the run summary uses the stable display
vocabulary:

| Queue | Summary |
|---|---|
| `pending` | `pending` |
| `claimed` / `running` | `running` |
| `succeeded` | `succeeded` |
| `failed` / `uncertain` | `failed` (the reason keeps `partial_state`) |
| `blocked` | `blocked` |
| `canceled` | `canceled` |
| no queue row | `skipped` |

Overall status: `running` if any step is pending/running; otherwise `failed` if
any step failed/blocked; otherwise `canceled` if any step was canceled; otherwise
`completed`. A plan with only skipped steps is `completed`.

A chain stops at its first failed, uncertain, or blocked step. Because the plan
is one merged graph, a stop cancels pending nodes after that topological rank in
the same run, including downstream nodes declared by another chain. This matches
the old runner's "stop the remaining chains" behavior and is the safe choice for
a user-declared dependency graph. It is not a full-site rollback promise.

---

## 4. Tests

### 4.1 Task 0 — 4.3c gaps

| Gap | Test |
|---|---|
| `class-updatronix-update-job.php:305` integer hydration | `UpdateJobTest::test_from_row_casts_every_numeric_field_to_int` asserts all six fields are ints. |
| `class-updatronix-update-plan.php:90` `created_at` fallback | `UpdatePolicyPlanTest::test_plan_created_at_explicit_and_fallback` asserts explicit preservation and the UTC-now fallback. |
| `class-updatronix-update-kernel.php:715` license cast | `UpdateKernelBackoffTest::test_license_check_casts_truthy_non_boolean` uses `"false"` and `"0.0"`. |

The remaining 4.3c gaps (abandoned-policy fallback, notifier 32-recipient
boundary, version-control fail-open log) are untouched and remain Phase 6 debt.

### 4.2 New coverage

Unit:

- planner: topological order and stable tie-break, cross-chain dedupe, unknown
  item, cycle, duplicate, chain limit, core-last, missing offer;
- Flow storage: one-shot migration, legacy list not read, installed-item
  validation, core-last, limits;
- chain dependency: enqueue guard;
- task 0 gaps.

Integration:

- runner: two-step success, Shield block, mid-chain failure across two chains,
  retry/backoff, cancel, expired lease with an unknown version, manual re-run
  refusal when a policy now denies a terminal row;
- REST: subscriber, admin, Multisite non-Super-Admin matrix, inactive license,
  404/409/429, run summary without offers;
- live fixture: two local plugin zips updated one step at a time with
  `queue_enabled=false`, Shield block with no write, duplicate worker against
  the native `auto_updater` lock.

JS:

- run-active detection, counters, per-chain step lookup, badge intent, run
  eligibility, short run id.

### 4.3 Local live fixture

`UpdateQueueLiveFixtureTest` now has three Flow cases. Every offer is injected
through `pre_set_site_transient_update_*`; every package is a local zip served
by `upgrader_pre_download`; any non-WordPress.org HTTP request fails. The first
`POST /flow/run` executes exactly one step; the follow-up worker executes the
next. The blocked case asserts `0` upgrader completions and unchanged versions.

---

## 5. Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 352 tests, 1352 assertions, pass. |
| Pro `bin/harness integration` | 120 tests, 1073 assertions, pass; 1 intentional Multisite-only skip. |
| Pro `bin/harness test:js` | 81 tests, pass. |
| Pro `bin/harness lint:js` | Pass, 0 warnings. |
| Pro `bin/harness lint:files` on changed PHP | Pass, 0 errors and 0 warnings (25 files). |
| Pro `bin/harness build` | Pass. |
| Pro `bin/harness counterfactual` | 4.4 scope: 28 checked, 22 killed, 0 survived, 6 equivalent. Final full-diff Phase 6 run: 73 checked, 64 killed, 0 survived, 9 equivalent. |
| Free suites | Not re-run: no Free code changed (documentation only). |
| E2E | `bin/harness e2e --list` OK (2 tests); the full suite was not run or claimed. |

---

## 6. Counterfactual

Command:

```bash
cd wp-content/plugins/updatronix-pro
HARNESS_MUTATION_BASE=83c9db4 HARNESS_COUNTERFACTUAL_MAX=900 \
  bash bin/harness counterfactual
```

Result:

| Disposition | Lines |
|---|---:|
| Killed | 22 |
| Survived | 0 |
| Classified equivalent | 6 |
| **Checked** | **28** |

The 4.4 gate checked 28 of 76 substitutable lines with the unit suite. The
final full-diff Phase 6 run checks all 73 executed lines: 64 killed, 0 survived,
9 classified equivalent. Integration-only lines remain outside the unit gate.

The six equivalents are annotated on their lines:

| Line | Twin | Why it cannot change behaviour |
|---|---|---|
| `52` | `||` → `&&` | WordPress and the unit bootstrap always define both site-option functions; the branch is outside the supported runtime. |
| `62` | `||` → `&&` | An array carrying either key is associative, so the following `! array_is_list()` guard returns false for the same inputs. |
| `74` | `array_values` → `array_filter` | A bare list of chains contains non-empty arrays; an empty entry is not a supported chain shape. |
| `409` | `&&` → `||` | A non-array `chains` value is passed to `validate_chains()`, which returns `WP_Error`, so `get_saved_chains()` returns `[]` either way. |
| `479` | `(int)` → `(string)` | The filter contract is a numeric string, which `min()`/`max()` compare numerically. |
| `480` | `(int)` → `(string)` | Same as 479 for the step limit. |

The task-0 lines are killed: job integer hydration, plan `created_at` fallback,
and the license cast. The Flow-run lines exposed by the first pass were killed
by the added migration, route-argument, and installed-item error assertions.

---

## 7. Limits and debt

1. The 4.4 scope is recorded above; the final full-diff Phase 6 run records
   all 73 checked lines with 0 survivors and 9 classified equivalents.
2. The local live fixture is not a staged host with real cron. No staging run
   was performed.
3. No live Multisite run; the pure Super-Admin matrix and the main-site guard
   cover the rule.
4. E2E passes 2/2 after the Chromium runtime is installed in the DDEV web
   container; the persistent image setup is recorded in 58-e2e-infrastructure.
5. The queue is dormant by owner decision: `queue_live=false`,
   `queue_enabled=false`, Shield switch disabled. 4.4 does not activate it.
6. The three remaining 4.3c unit gaps are Phase 6 debt; they are not Flow
   execution lines.
7. A retry reuses the persisted job; the planner is not re-run for a retry. A new
   manual chain run resumes terminal rows only for that explicit action.
8. The UI maps `uncertain` to `failed` with the `partial_state` reason; the
   queue row keeps the distinct status.
9. Automatic Flow runs start one step per tick/follow-up. A chain of N steps
   takes N worker runs, not one request.
10. No version bump, no commit, no legacy path.

---

## 8. Explicit decisions

- **4.4-D1 — Planner owns order.** One merged graph, stable topological order,
  deduplicated by item and target; no second ordering path.
- **4.4-D2 — Run is a real action.** `POST /flow/run` + run summary; the
  scheduled toggle is `Enable`.
- **4.4-D3 — Shield is mandatory.** Every Flow job uses the same policy chain;
  a denial is terminal and stops the run.
- **4.4-D4 — Old runner removed.** File, require, hook registration, and
  transient lock are deleted; no wrapper.
- **4.4-D5 — Chain schema.** `{version:1, chains:[…]}` with a one-shot
  migration and no dual read.
- **4.4-D6 — Scheduler anchor.** `wp_maybe_auto_update` priority 1 after
  discovery; one step per worker run.
- **4.4-D7 — Core last.** Core is allowed only as the last step of a chain.
- **4.4-D8 — Run limits.** One active run; per-user/per-chain five-minute
  cooldown; 409/429.
- **4.4-D9 — Engine on, queue off.** The engine kill switch defaults to true so
  Flow can execute; `queue_enabled`, `queue_live`, and the Shield switch remain
  false/disabled.

---

## 9. Phase 5 / Phase 6 handoff

- 5.1 can now show truthful Shield claims and the read-only queue summary; the
  queue remains dormant.
- 5.2 can build on the delivered Flow run action and per-step UI; there is no
  second Flow screen to wire.
- Phase 6 owns the remaining 4.3c unit gaps, a staged live run, the Multisite
  live pass, and E2E.
- Queue activation still requires a new owner decision after a staged run. Do
  not use this document to justify `queue_live=true`.
