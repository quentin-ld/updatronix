# Update engine — Phase 4.2 kernel, dormant

> **Superseded (2026-10-29, Step 4).** The kernel, the queue table, the queue
> REST routes, the queue summary, the notifier, the journal, and the queue
> Health checks are deleted. One pass (`updatronix_pro_update_pass`) replaced
> them; `WP_Automatic_Updater::run()` is the only execution path, selected by a
> one-item `auto_update_plugin|theme|core` gate. Keep this file as history.

**Date:** 2026-10-26
**Scope:** Pro `inc/update-engine/`, the queue table and schema option, the
uninstall decision, the additive Free logger query/allowlist filters, and the
first Pro integration suite.
**Status:** Implemented and dormant. No scheduler, cron event, REST route,
`auto_update_*` filter, panel, email, or UI is registered by default.
**Related:** ADR `45-update-engine-architecture.md` §5–§7, §10–§12, §14;
`FEAT-P-QUEUE`, `FEAT-P-SHIELD`, `FEAT-P-FLOW`; implementation log `11`.

---

## 1. What landed

| File | Responsibility |
|---|---|
| `class-updatronix-update-job.php` | Job value object, statuses, origins, transitions, normalized `job_key`, row hydration. |
| `class-updatronix-policy-result.php` | `Policy_Result` value object with code, severity, policy, metadata. |
| `class-updatronix-update-policy.php` | Pure policy contract. No implementation ships in 4.2; Shield implements it in 4.3. |
| `class-updatronix-update-plan.php` | `Plan` value object: ordered jobs, skipped/deduplicated/blocked facts. |
| `class-updatronix-update-planner.php` | Planner contract. Flow implements it in 4.4. |
| `class-updatronix-update-execution-result.php` | Executor outcome (`succeeded`, `failed`, `blocked`, `uncertain`, `retry`). |
| `class-updatronix-update-queue.php` | Schema, `dbDelta` install/upgrade, CRUD, atomic claim, lease, release, retry, cancel, sweeper input, retention, item barriers, chain dependency. |
| `class-updatronix-update-executor.php` | Native `WP_Automatic_Updater::update()` adapter, native lock, scoped execution flag, result mapping, offer/version readers. |
| `class-updatronix-update-journal.php` | Free `Updatronix_Logger` adapter, event-key dedupe, blocked-history query, bounded run summaries. |
| `class-updatronix-update-notifier.php` | One deduplicated plain-text digest per run, Free recipient routing, Pro `notify_email` switch. |
| `class-updatronix-update-kernel.php` | Enqueue, claim, execute, retry/backoff, cancel, crash reconciliation, run summary. |
| `class-updatronix-update-engine.php` | Facade: gating, capability rule, kernel factory. |
| `class-updatronix-update-engine-bootstrap.php` | Lazy class loader, kill switch, license/main-site gates, activation/upgrade install. |

Free changes are additive:

| File | Change |
|---|---|
| `inc/classes/class-updatronix-security.php` | `updatronix_log_action_types` filter and `get_allowed_action_types()`. |
| `inc/classes/class-updatronix-logger.php` | `updatronix_log_query_args`, `action_type`, `action_types`, `created_at_after`, `created_at_before`, `action_type` ordering. |
| `tests/Unit/LoggerActionTypesTest.php` | Allowlist stays closed by default and accepts additive filters. |
| `tests/Integration/LoggerActionTypeQueryTest.php` | Single/list/date/filtered queries at the database level. |

No Free transient or option was added or modified.

---

## 2. Dormancy contract

- The kill switch is `updatronix_pro_update_engine_enabled`, default `false`.
  A `UPDATRONIX_PRO_UPDATE_ENGINE_ENABLED` constant is available for tests and
  emergency overrides.
- `Updatronix_Update_Engine_Bootstrap::register()` returns `false` unless the
  switch is on, the stored license is active (dev mode counts), and the request
  is on the main site.
- 4.2 registers nothing: no `updatronix_pro_process_queue` cron event, no
  `auto_update_plugin|theme|core` filter, no REST route, no admin panel, no
  email. `register_runtime()` is intentionally empty until 4.3.
- Engine classes are loaded lazily. The Pro bootstrap only loads the small
  bootstrap file, checks the schema option, and calls `register()` inside the
  existing active-license gate.
- The queue table is installed on activation and on the first request after a
  schema version change. With no `$wpdb` (unit tests), the installer fails
  closed instead of fataling.
- `queue_enabled` is not read by the engine in 4.2 and has no consumer. The
  Shield setting is untouched; ADR 4.3-D8 owns the default migration and the
  worker activation. The queue is inactive because no scheduler exists, not
  because a setting is flipped in this phase.

**Table installation**

- Table: `{base_prefix}updatronix_pro_update_queue`, network/main-site scoped.
- Schema version: `1.0.0`, stored in the network option
  `updatronix_pro_queue_db_version`.
- `Updatronix_Update_Queue::install()` uses `dbDelta`; `maybe_upgrade()` is a
  no-op when the stored version matches.
- All timestamps are UTC (`gmdate`); display conversion remains a surface
  concern.

---

## 3. Queue model

The schema matches ADR §6.2: `id`, `site_id`, `run_id`, `job_key`,
`item_type`, `item_id`, `from_version`, `target_version`, `origin`, `chain_id`,
`sequence`, `priority`, `status`, `attempts`, `max_attempts`, `available_at`,
`lease_owner`, `lease_expires_at`, `last_error_code`, `last_error_message`,
`policy_snapshot`, `result_code`, `log_id`, `created_at`, `updated_at`,
`finished_at`.

Indexes: unique `job_key`, `claim (status, available_at, priority, sequence,
id)`, `site_status`, `item_status`, `lease`, `run`.

Statuses: `pending`, `claimed`, `running`, `succeeded`, `failed`, `uncertain`,
`blocked`, `canceled`. `skipped` is a run-summary fact, not a queue status.

`job_key` is `sha256(site_id|item_type|item_id|target_version|shared)`. The
origin scope is deliberately normalized to `shared`, so an automatic job, a
Flow chain, and a manual retry for the same target reuse one row; the first
origin wins. This resolves the ADR's `origin_scope` wording in favor of the
stated first-origin-wins behavior.

**Claim**

- Candidate rows are selected by `status=pending`, `available_at <= now`,
  priority, sequence, id, bounded to 20.
- Chain dependencies are checked before the conditional update: every earlier
  row in the same `run_id` and `chain_id` must be `succeeded`; a missing row is
  a skipped step and does not block.
- The atomic step is
  `UPDATE … SET status='claimed', lease_owner=?, lease_expires_at=?,
  attempts=attempts+1 … WHERE id=? AND status='pending'`; affected rows must be
  1.
- `lease_owner` is a UUID plus host/pid suffix, bounded to 64 characters.
  The lease is 15 minutes by default, filtered by
  `updatronix_pro_queue_lease_seconds`.

**Retry, backoff, cancel**

- Base delays: 5, 15, and 60 minutes, ±20% jitter, filtered by
  `updatronix_pro_queue_backoff`.
- An explicit retry of `failed`/`uncertain` resets `attempts` to 0, clears the
  error/result/finished fields, and makes the row immediately claimable.
- A `blocked` row is not retried by the engine; Shield recheck or an explicit
  cancel moves it in 4.3.
- Cancel accepts `pending`, `failed`, `uncertain`, and `blocked`; a `running`
  row is rejected so a worker cannot be raced mid-upgrade.
- License inactive or queue off cancels pending `origin=automatic` rows only;
  Flow jobs remain explicit.

**Crash recovery**

The kernel sweeper runs at every worker start in 4.3:

| Installed version | State | Result |
|---|---|---|
| target | `succeeded` | `recovered_after_crash` |
| source, attempts left | `pending` | backoff, `recovered_lease_expired` |
| source, attempts exhausted | `failed` | `attempts_exhausted` |
| anything else | `uncertain` | `partial_state`, no auto-retry |

A stale worker cannot complete a row after the sweeper: `complete()` checks the
lease owner and the claimed/running status.

**Retention**

Terminal rows older than 30 days are deleted in batches of 500. Run summaries
live in the non-autoloaded main-site option `updatronix_pro_update_runs`, last
20 runs.

---

## 4. Executor

- Only `WP_Automatic_Updater::update()` is called. No raw
  `Plugin_Upgrader`/`Theme_Upgrader`/`Core_Upgrader` call exists in the engine.
- The executor loads the native admin/update includes, reads the offer from the
  native update transient, sets `autoupdate=true` for plugins/themes, and
  acquires `WP_Upgrader::create_lock( 'auto_updater', 15 min )`. Lock busy is
  `retry` with a 5-minute `available_at`, not a failure.
- `pre_auto_update` is observed for the duration of the call. The mapping is:

| Native result | Queue outcome | Code |
|---|---|---|
| `true` | `succeeded` | `updated` |
| `false`, `pre_auto_update` did not fire | `blocked` | `native_policy_refused` |
| `false`, `pre_auto_update` fired | `failed`, retryable | `upgrader_returned_false` |
| `WP_Error` retryable | `failed`, pending | native code, retryable list |
| `WP_Error` terminal | `failed` | native code |
| `WP_Error up_to_date` | `succeeded` | `no_longer_offered` |
| `null`, target installed | `succeeded` | `installed_target` |
| `null`, source installed | `failed`, retryable | `upgrader_returned_null` |
| `null`, unknown version | `uncertain` | `partial_state` |

- `Updatronix_Update_Executor::is_executing( type, id, target )` and the global
  `updatronix_pro_update_engine_executing` expose the scoped execution state for
  the 4.3 ownership handshake.
- The executor never calls `run()`; `update()` handles one item.

---

## 5. Kernel

`enqueue()` normalizes the item, fills `job_key`/run/site/timestamps, evaluates
policies at enqueue, inserts with `INSERT … ON DUPLICATE KEY UPDATE id=id`, and
returns the persisted row on a duplicate. A denial at enqueue inserts a
`blocked` row and writes the journal.

`run()` sweeps expired leases, checks the stored license, processes a bounded
batch (default 1, max 5), writes the run summary, and sends one digest. The
batch is one job per worker run so a chain never runs in one request.

`execute_job()`:

1. marks running and renews the lease;
2. re-reads the installed version: target installed → `already_installed`;
   unknown version → `uncertain` and stop;
3. re-reads the pending offer: absent → `no_longer_offered` (success);
4. re-runs the policy chain; a denial → `blocked`;
5. applies `updatronix_pro_update_job_before_execute` (a `WP_Error` is a
   `before_execute_veto` block);
6. calls the executor;
7. maps retryable failures to `pending` with backoff, exhausted attempts to
   `failed`, and terminal outcomes to `succeeded`/`failed`/`blocked`/
   `uncertain`;
8. writes the journal row, completes the queue row with `log_id`, cancels
   downstream chain steps on failure/block/uncertainty, and fires
   `updatronix_pro_update_job_completed`.

Hooks shipped: `updatronix_pro_queue_claimed`, `updatronix_pro_queue_released`,
`updatronix_pro_queue_swept`, `updatronix_pro_update_job_started`,
`updatronix_pro_update_job_completed`,
`updatronix_pro_update_run_completed`. Filters shipped:
`updatronix_pro_update_engine_enabled`, `updatronix_pro_queue_batch_size`,
`updatronix_pro_queue_lease_seconds`, `updatronix_pro_queue_max_attempts`,
`updatronix_pro_queue_backoff`, `updatronix_pro_queue_retryable_errors`,
`updatronix_pro_policy_result`, `updatronix_pro_update_job_before_execute`,
`updatronix_pro_notification_recipients`.

---

## 6. Journal and notifier

- One log row per transition through the Free logger. The event key is
  `sha256('queue|site|type|id|target|transition|attempt')`, so retries are
  distinct and duplicate writes are idempotent.
- Action mapping: success → `update` (`same_version` for
  `already_installed`/`no_longer_offered`); failure → `failed`; policy block →
  `prevented`, `incompatible`, or `safe_mode_disabled`; cancel → `disabled`;
  re-enable (4.3) → `auto_update_reenabled` through the Free allowlist filter.
- `get_blocked_history()` queries the Free log at DB level with
  `action_types` and a 30-day bound; the Shield route can reuse it in 4.3.
- The digest is one plain-text email per run, only when a step is
  `failed`/`blocked`/`uncertain`. Recipients come from Free's
  `notifications_mode`, `notify_enabled`, `notify_emails`, `notify_on` and
  Pro's `notify_email`; no client address is accepted. Dedupe is a log row keyed
  by the run digest event.

---

## 7. Uninstall (4.2-D6)

`Updatronix_Pro_Uninstall::run()` now:

1. returns early on a Multisite subsite unless the request is the network
   admin or WP-CLI (the same guard as Free);
2. always drops the queue table from the main site via
   `Updatronix_Update_Queue::drop_table()`, regardless of Free's
   `delete_data_on_uninstall` opt-in;
3. keeps the existing settings/license cleanup.

The queue is operational state, not user history; a stale pending queue after a
reinstall is dangerous. The integration test proves the drop with the opt-in
explicitly false.

---

## 8. Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 291 tests, 1002 assertions, pass (baseline 243). |
| Pro `bin/harness integration` | 65 tests, 644 assertions, pass; 1 intentional Multisite-only skip (baseline: no suite). |
| Pro `bin/harness test:js` | 41 tests, pass (unchanged). |
| Pro `bin/harness lint:js` | Pass, 0 warnings. |
| Pro `bin/harness lint:files` on modified PHP | Pass. |
| Pro `bin/harness lint` (full) | Pass; PHPStan no errors. Test filenames are excluded from the WordPress filename rule because PHPUnit discovers PascalCase classes. |
| Pro `bin/harness build` | Pass. |
| Pro `bin/harness counterfactual` | 7 substitutable changed lines, 0 executed by the unit suite. They are declarative value-object property defaults plus uninstall/bootstrap wiring; the wiring is exercised by the integration suite and the defaults by constructor behavior. Recorded as a coverage finding, not a pass. |
| Free `bin/harness test` | 216 tests, 422 assertions, pass (baseline 213). |
| Free `bin/harness integration` | 77 tests, 208 assertions, pass, 13 pre-existing skips (baseline 73). |
| Free `bin/harness lint:files` on modified PHP | Pass. |
| E2E | Not run; remains blocked by `libnspr4.so`. Not claimed. |

The Pro integration suite includes:

- schema columns/indexes, idempotent install, schema option, uninstall drop;
- two-worker atomic claim, attempts increment, ordering, run scoping, lease
  expiry/renewal/release, chain dependency, running-cancel rejection;
- crash reconciliation for target/source/unknown/exhausted versions and a stale
  worker;
- retryable/terminal/exhausted retry, lock busy, manual retry, cancel and
  downstream cancel, license-gated cancellation;
- enqueue idempotence, enqueue/execution policy denial, success, already
  installed, missing offer, unknown version, chain failure, run summaries;
- license/capability/main-site gates and the absence of queue cron/REST routes;
- journal event-key dedupe, action/status mapping, blocked history, digest
  recipients and one-send dedupe;
- executor result mapping, `autoupdate`, scoped execution flag, transient offer;
- a real plugin and a real theme installed from a local zip through
  `WP_Automatic_Updater::update()` with no external network.

The Multisite live guard is skipped in the single-site run; the Super-Admin
rule is covered by the pure capability matrix. A live Multisite pass remains an
environment gap, as recorded in doc 45 §13.2.

---

## 9. What 4.3 and 4.4 will use

- **4.3:** implement `Updatronix_Update_Policy` (license, engine, native guards,
  compatibility, delay, VCS/Composer, abandoned, offer, chain), the Shield
  ledger, the `auto_update_*` adapter, the ownership handshake, the scheduler,
  queue REST routes, the queue summary, blocked history, and the default
  `queue_enabled=false` migration. The kernel and table need no change for the
  policy insertion; `Updatronix_Update_Engine::register_runtime()` is the
  registration point.
- **4.4:** implement `Updatronix_Update_Planner`, move the Flow anchor to
  `wp_maybe_auto_update` priority 1, add `POST /flow/run` and
  `GET /flow/runs/{run_id}`, and delete the old runner.

## 10. Out of scope in 4.2

- Any UI, tab, panel, notice, or queue summary.
- Any active Shield/Flow hook, runner change, or `/flow/run` route.
- The Shield policy implementation and the ownership handshake (4.3).
- The Flow planner and run routes (4.4).
- Site Health checks and the queue REST routes; the ADR §12.1 lists them, but
  the 4.2 mission forbids UI/routes and they belong with the 4.3 surfaces.
- R-02/R-04/R-07/R-09 roadmap features.
- Version bump and commit.

## 11. Risks and limits

1. `WP_Automatic_Updater::update()` is a public core method used as the
   executor. The integration fixture and the version floor (WP 6.2) are the
   control; a future core change remains a maintenance risk.
2. The native `should_update()` path still runs. A Flow/manual job sets
   `autoupdate=true`, but native `is_disabled()`, VCS, and filesystem checks can
   still refuse. The executor records `native_policy_refused` rather than
   pretending the policy was the cause.
3. The queue table is real operational state. A failed uninstall test in the
   WordPress suite is rewritten to temporary tables; the Pro test explicitly
   removes that rewrite to prove the real drop.
4. A live Multisite run and a real disk-full test are not available in this
   environment. The pure guards and the injected-failure tests cover the logic.
5. No digest is sent while the engine is dormant; the notifier is exercised
   directly in integration.
