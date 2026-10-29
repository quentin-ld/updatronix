# Cross-feature synthesis — update engine rethink

**Date:** 2026-10-28
**Status:** Planning only. No production code changed.
**Owner:** Quentin.
**Scope:** Update Shield (Version Lock, Update Queue), Update Flow, Update
Schedule, and the update logger in `updatronix` and `updatronix-pro`.
**Inputs:** The five analyses in `updatronix/docs/wpds/60-update-engine-rethink/analysis/`
and the five task files named in §2.1. All ten exist; no input was missing.

---

## 1. Executive summary

The five plans agree on the core model:

1. One automatic pass, identified by one `pass_id`.
2. One item per native `WP_Automatic_Updater::run()` invocation, through a
   one-item `auto_update_plugin|theme|core` gate that never returns `true`.
3. Native per-item email; no custom digest.
4. One actual-update row owned by the Free native logger; one pass summary
   row; no duplicate success row.
5. Version Lock first, then compatibility, then the Schedule hold.
6. Flow orders the automatic pass and is implemented last.

They disagree on five material contracts. This document resolves them:

| # | Conflict | Resolution |
|---|---|---|
| C1 | Native filter precedence: Version Lock at 20, Free delay at 21, compatibility at `PHP_INT_MAX - 5` cannot produce “Version Lock → compatibility → delay.” | One ordered Pro adapter at priority **19** evaluates environment/native guards, then Version Lock, then WP/PHP compatibility. Free delay stays at 21. The Safe Mode adapter stays at `PHP_INT_MAX - 5` as a fallback and never writes a second row. |
| C2 | Core identity: plans use `core:core`; the code and saved chains use `core:wordpress`. | Canonical key is **`core:wordpress`**. `core:core` is a read-only legacy alias and is never written. |
| C3 | Three state vocabularies (18 readiness, 14 display before this synthesis, 9 log states) with no mapping. | One three-layer vocabulary and one mapping table (§6.5). `blocked_by_predecessor` is not a state; it is `waiting_for_predecessor` with `blocking=true` and a `flow_predecessor_*` reason. The Schedule gains `blocked`, `failed`, `uncertain`, and `running`, for 18 display states. |
| C4 | Reason-code collisions: `native_vcs` means “Updatronix detector” in plan 1 and “WordPress core refusal” in plan 5; `version_lock_unknown` means both fail-closed and fail-open. | `version_locked_native` is the public VCS code with `context.detector=updatronix/wordpress`; `native_vcs` is an internal legacy alias. Split `version_lock_unknown` (fail-closed block) from `version_lock_unverified` (fail-open advisory). |
| C5 | Queue and Logger disagree on who writes the pass log, and on whether the journal survives. | The Logger Step lands before Queue. `Updatronix_Decision_Log` is the only Pro write adapter. Queue replaces the journal with `Updatronix_Update_Pass_Log`; the journal is deleted, not adapted twice. The log `system`/`pass` row is the audit source; `updatronix_pro_last_pass` is a one-entry cache for the Flow page. |

The plans also leave four gaps that must be closed in implementation:

1. The Schedule calculator has no `blocked`/policy input, so a
   compatibility-blocked item would still show a countdown.
2. The plans use `core:core` while the code uses `core:wordpress`.
3. `first_seen` starts when the delay gate runs, which is after Version Lock
   and compatibility; the owner’s copy says the hold starts when the check
   first sees the release.
4. Deleting the kernel makes the Flow runner fatal before Flow is implemented.

**Recommended implementation order:**

1. Version Lock.
2. Logger foundation (Free schema/API + Pro `Decision_Log`).
3. Schedule (calculator, native display, delayed-row rewrite).
4. Queue (one-item pass; delete the queue stack; freeze Flow execution).
5. Flow (last; remove the freeze; full readiness and pass integration).

This is a deliberate change from the prompt’s likely order (Queue second,
Logger fourth). Queue must write the final log rows and needs the Schedule
calculator for `eligible_at`. The Logger schema is Free and independent; the
Schedule calculator is read-only and forward-compatible before the pass. That
order avoids two log formats and a rewritten hold calculation.

**Verdict:** GO with targeted amendments. No plan needs a full re-plan. The
three owner decisions were approved on 2026-10-28; the resolution is recorded
at the end of this document.

---

## 2. Inputs, method, and verification

### 2.1 Inputs

| Kind | Feature | Path | Status |
|---|---|---|---|
| Analysis | Version Lock | `updatronix/docs/wpds/60-update-engine-rethink/analysis/01-version-lock.md` | Present |
| Analysis | Update Queue | `updatronix/docs/wpds/60-update-engine-rethink/analysis/02-update-queue.md` | Present |
| Analysis | Update Flow | `updatronix/docs/wpds/60-update-engine-rethink/analysis/03-update-flow.md` | Present |
| Analysis | Update Schedule | `updatronix/docs/wpds/60-update-engine-rethink/analysis/04-update-schedule.md` | Present |
| Analysis | Logger | `updatronix/docs/wpds/60-update-engine-rethink/analysis/05-logger.md` | Present |
| Plan | Version Lock | `updatronix-pro/.agents/tasks/2026-10-28-feature-version-lock-rework.md` | Present |
| Plan | Update Queue | `updatronix-pro/.agents/tasks/2026-10-28-feature-update-queue-rework.md` | Present |
| Plan | Update Schedule | `updatronix/.agents/tasks/2026-10-28-feature-update-schedule-rework.md` | Present |
| Plan | Logger | `updatronix/.agents/tasks/2026-10-28-feature-logger-rework.md` | Present |
| Plan | Update Flow | `updatronix-pro/.agents/tasks/2026-10-28-feature-update-flow-rework.md` | Present |

### 2.2 What was verified in code

The synthesis did not read whole files. These are the surfaces that decide the
contracts:

| Claim | Evidence |
|---|---|
| The engine facade builds a kernel and reads `queue_enabled`. | `updatronix-pro/inc/update-engine/class-updatronix-update-engine.php:100-112`. |
| The Flow runner requires `Updatronix_Update_Kernel` and calls `runtime_kernel()`. | `updatronix-pro/inc/update-flow/class-updatronix-flow-runner.php:53-55`. |
| The queue scheduler registers `wp_maybe_auto_update` at priority 1 and the ownership handshake. | `updatronix-pro/inc/update-engine/class-updatronix-queue-scheduler.php:29-52`. |
| The engine policy chain is license, engine, native guards, WP compatibility, PHP compatibility, delay, version control, abandoned, pending offer, chain dependency. | `updatronix-pro/inc/update-engine/class-updatronix-policy-chain.php:33-42`. |
| Free’s delay filter runs at priority 21 and respects an incoming `false`. | `updatronix/inc/classes/class-updatronix-autoupdatedelay.php:42,101-104,570-628`. |
| Safe Mode registers `auto_update_*` at `PHP_INT_MAX - 5`. | `updatronix-pro/inc/update-shield/class-updatronix-safe-mode.php:120-122`. |
| `UPDATRONIX_DB_VERSION` is `UPDATRONIX_VERSION`. | `updatronix/inc/core/constants.php:44-46`. |
| `Updatronix_Logger::log()` takes 12 positional arguments. | `updatronix/inc/classes/class-updatronix-logger.php:68-80`. |
| The log table has 17 columns and no decision fields. | `updatronix/inc/classes/class-updatronix-database.php:80-104`. |
| The canonical core key in the planner and Flow controller is `core:wordpress`. | `updatronix-pro/inc/update-engine/class-updatronix-flow-planner.php:489`; `updatronix-pro/inc/update-flow/class-updatronix-update-flow.php:427`. |
| Flow registers `POST /flow/run` and `GET /flow/runs/{id}`. | `updatronix-pro/inc/update-flow/class-updatronix-update-flow.php:118,137`. |
| Queue registers five REST routes. | `updatronix-pro/inc/update-engine/class-updatronix-queue-rest.php:66,79,91,110,129`. |
| Pro uninstall drops the queue table. | `updatronix-pro/inc/classes/class-updatronix-pro-uninstall.php:78-82`. |
| Free uninstall drops the log table on the main site. | `updatronix/inc/classes/class-updatronix-uninstall.php:129`. |
| `updatronix_schedule_item_state`, `updatronix_auto_update_can_toggle`, and `updatronix_pro_update_pass` do not exist yet. | `grep` over both `inc/` trees returns no production match. They are new contracts. |
| `performed_as` allows `manual`, `automatic`, `upload` only. | `updatronix/inc/classes/class-updatronix-security.php:38-42`. |
| The log action allowlist has ten action types; `system` is not a log type. | `updatronix/inc/classes/class-updatronix-security.php:21-35`. |

No production code was edited. The only files written are this document and
`implementation-order.md`.

### 2.3 Reading rules

- “Must” is a contract. “Should” is a recommendation.
- `plugin:a/a.php`, `theme:slug`, and `core:wordpress` are item keys. The
  colon is part of the key; do not parse it with `explode()` without a limit.
- “Pass” means the one automatic pass in analysis 02. “Queue” means the legacy
  custom queue that the pass replaces.
- “Owner” means Quentin. “OPEN — owner decision” means the implementation
  must not guess.

---

## 3. Conflict matrix

### 3.1 Master matrix

Each cell names the plan’s position or “—” when the feature has no position.
“Conflict?” is yes when two plans claim the same surface with incompatible
shapes or writers.

| Shared surface | Version Lock | Queue | Flow | Schedule | Logger | Conflict? | Resolution |
|---|---|---|---|---|---|---|---|
| Item identity (`type:id`) | Uses `type`/`id` in the decision facade. | `plugin:{file}`, `theme:{stylesheet}`, `core:core` in the pass example. | `type:id`; `core` node. | JS map uses `core:core`. | `log_type` + `item_slug`. | **Yes** | Canonical `plugin:{plugin_file}`, `theme:{stylesheet}`, `core:wordpress`, `translation:{type}:{slug}:{language}`. `core:core` is a read-only alias. One Free `Updatronix_Item_State` reader; Pro `Update_Executor` delegates. |
| Installed/target version reader | Detector needs the item root, not the version. | `Update_Executor` read-only methods. | Readiness reads installed and offer. | `Schedule_Context` reads transients. | Native logger reads the upgrader result. | **Yes** | `Updatronix_Item_State::get()` is the only reader. Schedule, Queue, and Flow consume it. The native logger may keep the upgrader result for the actual row; it does not create a second reader. |
| Pending-offer reader | — | `get_pending_offer()`. | Reads `response` and `checked`. | Reads `response`, `checked`, `last_checked`. | Writes the offer target. | **Yes** | Same `Item_State::get()`. One freshness TTL: `updatronix_schedule_offer_ttl`, default 6 hours. `updatronix_pro_flow_offers_ttl` becomes an alias or is removed. |
| Detection result (VCS/Composer) | Owns the detector and `decision()`. | Consumes at order build. | Consumes the lock state. | Consumes `lock` facts. | Consumes reason codes. | No (one owner) | Version Lock owns. `decision()` is the public contract; `Shield_State::version_control()` is the request map for Schedule. No other feature runs the detector. |
| Policy chain / reason codes | Reorders the chain; adds `reason_code`. | Keeps the chain; `queue_check` becomes true. | Consumes the first denial. | Consumes `policy` facts. | Owns aliases and labels. | **Yes** | Engine chain: license, engine, native guards, version control, WP compatibility, PHP compatibility, delay, abandoned, pending offer, chain dependency. Native ordered adapter at 19: native guards/environment, version lock, then compatibility. Canonical reason codes; legacy codes are aliases. |
| Pass/run id | Needs the execution flag. | Owns `pass_id` and the pass option. | Consumes `pass_id`. | Consumes pass facts. | Stores `pass_id`. | **Yes** | One `pass_id`, 32 lowercase hex. No separate `run_id`. Manual/CLI/external/health use `request_id`. Option `updatronix_pro_update_pass`; last-pass cache `updatronix_pro_last_pass`. |
| State machine vocabulary | `locked`, `waiting_for_manager`. | Node states (`offer_eligible`, `held`, `locked`, `blocked`, `failed`, …). | 18 readiness states. | 14 display states, no `blocked`. | 9 decision states. | **Yes** | Three layers with one mapping table (§6.5). Add `blocked`, `failed`, `uncertain` to the display layer. `blocked_by_predecessor` = `waiting_for_predecessor` + `blocking=true` + reason. |
| Next-eligible calculation | No countdown for locked. | Stores `eligible_at` in nodes. | Reads Schedule facts. | Owns the calculator. | Stores `next_eligible_at`. | **Yes** | One `Schedule_State::calculate()`. Queue, Flow, and Logger consume it. `first_seen` is written at plan-time discovery (§6.6). UTC storage; site timezone display; null when there is no promise. |
| Native `auto_update_*` filters | Proposed Version Lock-only adapter at 20. | Gate at `PHP_INT_MAX`. | Supplies order. | None. | None. | **Yes** | Ordered Pro adapter at **19**: environment/native guards, then Version Lock, then WP/PHP compatibility. Free delay at 21. Safe Mode fallback at `PHP_INT_MAX - 5`. Gate at `PHP_INT_MAX`. Each filter respects an incoming `false`; one row. |
| Native `run()` gate | Needs the execution flag. | Owns the gate. | Needs the gate. | None. | Writes pass rows. | **Yes** | Selection-only gate; one item per request; never returns `true`; translations not gated. Policies write denials; the gate marks the node state and never marks an unselected item failed. |
| Queue table/options | — | Deletes. | Runner uses the kernel. | Reads pass facts. | Journal uses queue state. | **Yes** | Delete the table, rows, options, transients, cron, routes, UI, summary, and Health checks. One-shot migration; no row migration. The pass option replaces the table. |
| Flow chains/planner | — | Order builder consumes the planner. | Owns the planner, readiness, graph, and snapshot. | Reads flow facts. | Stores chain id and predecessor. | No (Flow owns) | Flow owns the planner and readiness. Queue consumes `flow_nodes`, `flow_edges`, `flow_snapshot`, `flow_errors`. No second order builder. |
| Flow run summary | — | Pass summary. | Last-pass card. | Reads pass facts. | `system`/`pass` row. | **Yes** | The log `system`/`pass` row is the audit source. `updatronix_pro_last_pass` is a one-entry cache for the Flow page. `updatronix_pro_update_runs` is deleted. |
| Shield settings | Adds `version_lock_manual_updates`; relabels `version_control_lock`. | Removes `queue_enabled`. | — | — | — | **Sequential** | Step 1 owns the Version Lock key. Step 4 owns the queue key removal. Each preserves the other’s keys; REST round-trip tests. |
| Schedule settings | — | — | — | Owns Free `options.php`, `scheduleDraft.js`, `SchedulePanel.js`. | — | No | Schedule owns hold `0..365`, `HH:MM`, recurrence, and the core-security copy. No other step edits these files. |
| Log table/schema | Adds reason metadata to the policy result. | Writes `pass_id`, item rows, pass row. | Writes chain, predecessor, wait rows. | Provides `next_eligible_at`. | Owns the schema and migration. | **Yes** | Logger owns additive columns and the dedicated schema version. Queue and Flow write only through `log_event()`/`Decision_Log`; no direct SQL. |
| Activity log UI | Shield card links. | Queue card removed. | Flow page states. | Log links. | Owns filters, columns, details. | **Yes** | Logger owns the activity log. `UpdateShieldPage.js` is edited sequentially by Steps 1, 2, and 4. `UpdateFlowPage.js` is owned by Steps 4 (freeze) and 5 (full). |
| Export schema | — | — | — | — | Owns. | No | Append new columns after the old ones; same filters as the UI; truthful status map; keep every old column and field. |
| External log payload | — | — | — | — | Owns. | No | Additive optional fields; keep the old required arguments and payload keys; `updatronix_log_updated` fires only on enrichment. |
| REST routes | Uses Shield routes. | Deletes five queue routes. | Deletes two Flow routes. | Adds none. | Extends `/logs`. | **Sequential** | Route table in §6.8. Removed routes return 404. `GET /flow/chains` gains `active_pass`/`last_pass` in Step 5. No new Schedule route. |
| Admin JS components | `version-lock-augment.js`, Shield page. | Removes queue UI. | Flow page. | `native-update-time.js`. | Activity log. | **Yes** | One owner per component per step. Server filters at 90 (Version Lock) and 110 (Schedule); JS runs in the same order; Schedule no-ops on a locked row. |
| Cron hooks | — | Adds the pass follow-up; deletes queue hooks. | Removes the cooldown. | Free cron unchanged. | Retention uses the Free daily cron. | **Yes** | One cron inventory (§6.12). Unschedule legacy hooks on migration. The pass follow-up is one event at `time()+1`; no new recurring event. |
| Multisite behavior | Network decision; main-site engine. | Pass is main-site only. | Network chains; main-site execution. | Network schedule; main-site cron. | Execution-site `site_id`, `network`, `target_site_id`. | **Yes** | One attribution rule (§6.13). Subsite automatic updates remain native/all-at-once; documented. Schedule shows `network`; Flow shows `not_installed`; Logger sets `network=1` only for network-scoped decisions. |
| Uninstall/cleanup | Key lives in the Shield option. | Queue table, options, pass, last-pass cache. | No schema. | No schema. | Log table existing drop. | **Sequential** | Step 4 owns queue/pass cleanup. Logger owns log schema. Version Lock’s key is removed with the Shield option. Schedule has no schema. |

### 3.2 Conflict resolutions in detail

#### C1 — Native filter precedence

The native `auto_update_*` filters are a chain. A later filter can only preserve
or lower the incoming value if it respects an incoming `false`. Free’s delay
filter does (`true !== $update` returns `$update`), and Safe Mode returns
`$update` on allow. That makes the first denial sticky, but the **order** is
still wrong: Version Lock at 20, Free delay at 21, Safe Mode compatibility at
`PHP_INT_MAX - 5` evaluates the hold before compatibility. A held and
incompatible item would log `schedule_hold`, not `shield_incompatible_*`,
violating owner decision Q13 in analysis 01.

**Resolution.** The Version Lock plan adds **one ordered Pro adapter** at
priority **19** for `auto_update_plugin|theme|core`. It evaluates, in order:

1. Version Lock (`version_locked_native`, `version_locked_composer`,
   `version_lock_unknown`).
2. WP compatibility (`shield_incompatible_wp`).
3. PHP compatibility (`shield_incompatible_php`).
4. Environment/native guards (`environment_disabled`, when Pro owns them).

It returns `false` at the first denial and the incoming value otherwise. Free’s
delay filter at 21 then evaluates only allowed items. The existing Safe Mode
adapter at `PHP_INT_MAX - 5` remains a fallback for sites where the ordered
adapter is not registered (Pro inactive) and for Site Health probes. It must
detect that the ordered adapter already evaluated the item in this request and
must not write a second row.

The engine policy chain is reordered to the same precedence: license, engine,
native guards, **version control**, **WP compatibility**, **PHP compatibility**,
delay, abandoned, pending offer, chain dependency.

The gate at `PHP_INT_MAX` is **selection-only**. It never re-runs the policy
chain and never writes a denial row. A denied item reaches the gate as `false`;
if it is the selected node, the gate records the pass node state
(`locked`, `blocked`, `held`, or `not_managed`) and returns `false`.

**Why not move Free’s delay priority?** It is a Free-owned filter with an
existing public behavior. Moving it would change Free-only sites and every
third-party integration. A Pro ordered adapter is additive and reversible.

#### C2 — Core identity

The plans use `core:core` in the pass example and the JS map. The code and
every saved chain use `core:wordpress`:

- `Updatronix_Flow_Planner` emits `core:wordpress`.
- `Updatronix_Update_Flow` returns `core:wordpress` in the item picker.
- Existing tests use `core:wordpress`.

Writing `core:core` would break saved chains, the Flow picker, and the planner
tests.

**Resolution.** The canonical key is **`core:wordpress`**. `core:core` is a
read-only legacy alias normalized at the boundary (`Item_State::normalize_key()`,
Flow planner input, JS map). New rows, pass nodes, logs, and tests write
`core:wordpress`. A migration is not needed because the code already writes it;
the plans’ examples are corrected.

#### C3 — State vocabulary

The three layers are not interchangeable:

- **Readiness** answers “may this item run in this pass?” It has 18 states and
  is owned by Flow/Queue.
- **Display** answers “what should this surface say?” It has 17 states and is
  owned by Schedule.
- **Decision** answers “what does the log record?” It has 9 states and is owned
  by Logger.

The full mapping is §6.5. The important rules:

- `blocked_by_predecessor` is **not** a state. It is
  `waiting_for_predecessor` with `blocking=true` and a
  `flow_predecessor_blocked|locked|not_managed|not_installed|never_ready|uncertain`
  reason.
- `already_latest` is a readiness done fact. When written per item, it uses
  decision state `skipped` with reason `already_latest`; normally it is only
  counted in the pass summary.
- `offer_blocked`, `locked`, `not_managed`, `not_installed`, and `never_ready`
  all map to the display state `blocked` (or `locked` for Version Lock) and the
  log state `blocked`.
- `failed` and `uncertain` are terminal for the pass and map to their own log
  states. They are not `blocked`.
- `canceled` is reserved for a disabled/deleted chain or an abandoned pass. A
  transient predecessor never cancels a node.

The Schedule display layer gains `blocked`, `failed`, and `uncertain`; without
them a compatibility-blocked or failed item would still show a countdown
(see C9).

#### C4 — Reason codes

There are two collisions:

1. `native_vcs` is the existing policy code for the Updatronix VCS detector in
   plan 1, but analysis 05 defines it as “WordPress itself refuses a VCS
   checkout.” Both can happen.
2. `version_lock_unknown` is defined as fail-open/info in analysis 05, but plan
   1 uses it for fail-closed/unreadable evidence and adds
   `version_lock_unverified` for fail-open.

**Resolution.**

- The public code for any VCS lock is **`version_locked_native`**. Its context
  carries `detector=updatronix|wordpress`, `manager`, `scope`, and `evidence`.
  `native_vcs` remains the internal policy code and a legacy alias; new log
  rows do not use it as the public reason.
- The public code for a Composer lock is **`version_locked_composer`**.
  `version_controlled` remains the internal policy code and a legacy alias.
- **`version_lock_unknown`** = fail-closed, decision state `blocked`, action
  `prevented`, status `warning`. Used for positive evidence that cannot be read
  (`unknown_unreadable`, `unknown_marker_escape`, `unknown_missing_detector`).
- **`version_lock_unverified`** = fail-open, decision state `info`, action
  `advisory`, status `info`. Used only when there is no evidence at all. One
  advisory row per item/target, not per tick.

The alias map in §6.7 translates every old code. The UI shows one label per
public code; no synonyms.

#### C5 — Logger and Queue order, writers, and pass summary

Plan 2 deletes the journal; plan 4 edits it. Plan 2 writes a temporary pass log
with the old key and `update` action “until plan 5 ships”; plan 4 adds the
`pass` action and v2 key. Plan 3 says the pass summary may be copied to
`updatronix_pro_update_runs`; plan 4 says the log row is the source.

**Resolution.**

- The **Logger foundation lands before Queue**. It adds the schema, taxonomy,
  `log_event()`, `Updatronix_Log_Event::key()`, and `Updatronix_Decision_Log`.
  Queue uses the final API from its first commit; there is no temporary log
  format.
- **`Updatronix_Decision_Log` is the only Pro write adapter.** It calls Free’s
  `Updatronix_Logger::log_event()` and never writes SQL.
- Queue replaces the journal with `Updatronix_Update_Pass_Log`; the journal file
  is deleted in Step 4. The journal’s duplicate success row is fixed in Step 2
  by delegating it to `Decision_Log` for the interim, then the file is deleted.
  This keeps the log truthful even if the owner ships an intermediate release.
- The **log `system`/`pass` row is the audit source**. `updatronix_pro_last_pass`
  is a one-entry, non-autoloaded cache written at pass end for the Flow page
  when logging is disabled. `updatronix_pro_update_runs` is deleted and not
  migrated.
- The pass option is active state only and is deleted at pass end, deactivation,
  and uninstall. It is not a history store.

#### C6 — Flow run summary

The Flow page needs a last-pass card. If the only source is the log and the
owner disables logging, the card disappears.

**Resolution.** Read the latest `system`/`pass` log row first; fall back to
`updatronix_pro_last_pass`. The cache contains only `pass_id`, `started_at`,
`finished_at`, `status`, the nine counts, `first_waiting_reason`, and
`overflow`. It is non-autoloaded, bounded to one entry, deleted on deactivation
and uninstall. It is never used for audit or filters. The Flow page says “No
pass summary is available” when neither source exists.

#### C7 — Flow runner, kernel deletion, and “Flow last”

Plan 2 deletes the kernel and scheduler. The Flow runner calls
`runtime_kernel()` and type-checks `Updatronix_Update_Kernel`
(`class-updatronix-flow-runner.php:53-55`). If the kernel is deleted in Step 4
without touching Flow, `POST /flow/run` and the Flow page fatal.

Plan 2’s recommendation is to land Flow with Queue, which violates “Flow last.”
Keeping a kernel stub is a second execution path.

**Resolution.**

- Step 4 **freezes Flow execution** as part of deleting the queue:
  - Delete `Updatronix_Flow_Runner` and the Flow-specific kernel methods.
  - Remove `POST /flow/run` and `GET /flow/runs/{id}`; return 404 (not 500).
  - Remove the five-minute cooldown transient.
  - Remove the Flow runner from the Pro bootstrap and scheduler.
  - In `UpdateFlowPage.js`, remove Run now, the run dialog, and polling; show
    one read-only notice: “Flow ordering is being moved to the automatic pass.
    The chain editor remains; manual runs are unavailable until the next step.”
  - Keep the chain editor, save/discard, dependency check, and item picker.
  - Keep `GET /flow/chains`; return `active_pass=null`, `last_pass=null`.
- Step 5 implements readiness, graph, pass order integration, waiting/cancel
  logging, and the final Flow UI. It removes the freeze notice and adds
  `active_pass`/`last_pass`.
- **Do not cut a release between Step 4 and Step 5.** If the owner forces one,
  the freeze notice is the shipped state and OPEN-2 must be approved.

This is the only exception to “Flow last”: the **execution surface** is removed
with the queue because the queue is its execution model. The Flow **feature**
(ordering, readiness, waiting, last-pass card) remains last.

#### C8 — Schedule delayed-row write ownership

Plan 3 T5 changes `class-updatronix-autoupdatedelay.php` to use the calculator’s
`eligible_at`. Plan 4 T7 also changes the same file to use `log_event()` with
`schedule_hold`. Both cannot own the write.

**Resolution.** **Schedule owns the delayed-row call site.** The Logger Step
provides `log_event()` and the taxonomy; it does **not** edit
`class-updatronix-autoupdatedelay.php`. Schedule replaces the delayed log call
with:

```php
Updatronix_Logger::log_event( array(
    'log_type'         => $type,
    'action_type'      => 'update',
    'status'           => 'delayed',
    'reason_code'      => 'schedule_hold',
    'decision_state'   => 'delayed',
    'decision_stage'   => 'policy',
    'origin'           => 'automatic',
    'next_eligible_at' => $eligible_at_utc,
    // item, versions, message, context
) );
```

The value comes from `Updatronix_Schedule_State::calculate()`. The old
`max( first_seen + delay, time() )` is removed. One row per offer/target/state
start. This preserves the “one time source” contract and avoids two agents
editing the same method.

#### C9 — Schedule has no blocked/failed state

The Schedule calculator’s inputs are offer, auto-update, recurrence, hold,
cron, Flow, queue, and lock. There is no compatibility or policy input. The
display states have no `blocked`, `failed`, or `uncertain`. A
compatibility-blocked item with `incompatible_behavior=cancel` stays in
`auto_update_plugins`; the calculator would show `eligible` or `held` and
promise an install that the policy will deny.

**Resolution.** Add `policy` facts and three display states:

- The Pro `Schedule_Facts` callback returns
  `policy` (`state=none|blocked`, `reason_code`, `message`, `policy_id`).
  It computes a read-only compatibility/environment summary from the offer
  (`requires`, `requires_php`, `tested`) and the Shield settings
  (`control_wp`, `control_php`), and reads the active pass node state when
  present. It does not run the full policy chain per row.
- The display states are `blocked` (“Blocked by {policy}. No automatic install
  time.”), `failed` (“The last automatic attempt failed. The next pass
  retries.”), and `uncertain` (“The last attempt needs review. No automatic
  install time.”).
- State precedence inserts `blocked` after `locked` and before `not_managed`;
  `failed`/`uncertain` after `waiting_for_predecessor` and before
  `not_observed`.
- A missing or truncated `policy`/`lock` fact for an installed item is
  `unknown`, not `eligible`, when the Pro filter is active. A truncated
  200-item map is `unknown`. When Pro is inactive, Free shows its own schedule
  state (it cannot know about Pro locks) and may say “Update Shield is not
  active.”

#### C10 — `first_seen` discovery semantics

The owner’s copy says the hold starts when the next automatic check first sees
a release. The current implementation writes `first_seen` inside the delay
gate. After C1, the delay gate runs after Version Lock and compatibility, so a
blocked item’s hold would start only when the block clears. That contradicts
the copy.

**Resolution.** The **pass order builder is the discovery write point**:

- At plan time, before policy evaluation, it calls
  `Updatronix_AutoUpdateDelay::observe_offer( $type, $offer )` once per offer.
  The method records `first_seen` in the ledger without logging, without
  gating, and without changing the offer.
- The delay gate remains the only **gate**; it reads the ledger and writes the
  delayed row when it denies.
- On a Free-only site (no pass), the delay gate remains the writer, as today.
- The Schedule display reads the ledger through the read-only
  `peek_first_seen()`; it never writes.
- A locked item may have a `first_seen` row. The display shows `locked`, not a
  hold. When it unlocks, the hold may already have elapsed; the next pass
  updates it. This matches “the hold starts when the check first sees the
  release.”

This is an amendment to plan 3 (add `observe_offer()` alongside
`peek_first_seen()` and make the peek read a plan-time value) and plan 2 (call
it at plan time). It does not change the hold range or the formula.

#### C11 — Native UI composition

Version Lock and Schedule both edit the native plugin/theme rows, the
single-site theme template, and the update-core preamble.

**Resolution.** One order, one owner per slot:

| Surface | First writer | Second writer | Rule |
|---|---|---|---|
| `plugin_auto_update_setting_html` | Version Lock at 90 | Schedule at 110 | Version Lock replaces the toggle with a lock label. Schedule replaces only the inner text of `.auto-update-time`; it no-ops when the row is locked. |
| `theme_auto_update_setting_html` | Version Lock at 90 | Schedule at 110 | Same rule for the network theme row. |
| `theme_auto_update_setting_template` | Version Lock at 90 | Schedule at 110 | Version Lock adds a locked branch. Schedule replaces the native time call with `{{{ data.updatronixAutoUpdateText }}}` only when the branch is unlocked. |
| `core_upgrade_preamble` | Version Lock at 10 | Schedule at 20 | Version Lock appends its managed-items notice; Schedule appends the next-pass summary. Neither replaces the other. |
| `update-core` rows (JS) | Version Lock script | Schedule script | Version Lock removes/disables controls in full lock. Schedule replaces only the time text and skips locked rows. |
| WP-Cron hint | Schedule | — | One line, only when `DISABLE_WP_CRON`; no global banner. |

Both scripts are idempotent, feature-detect, use `textContent` (never
`innerHTML`), and carry distinct data attributes. Tests cover locked, unlocked,
held, waiting, queued, blocked, failed, and no-offer rows.

#### C12 — Translations

Translations are not pass nodes. Native `run()` processes them last in the same
invocation, even when the gate selected a plugin. They have no per-item email.
Plan 4’s L30 says the pass summary counts a translation; plan 2 says
translations are not nodes.

**Resolution.** Translations are **outside the pass**:

- No pass node, no gate call, no per-item email.
- The native logger writes their update rows with `log_type=translation`,
  `origin=automatic`, no `pass_id`, `reason=updated`.
- The pass summary does not include them in the nine counts. If the owner wants
  visibility, `context.translations=[count]` is a note, not a count.
- The Schedule shows translation details only; no countdown.
- The Flow planner rejects a translation step (it is not a valid item type).

#### C13 — Manual guard, core, and the pass exemption

Plan 1’s manual guard derives a plugin/theme id from `hook_extra`; it does not
cover core. The analysis case matrix includes a site-wide `ABSPATH/.git` that
locks core. The pass’s own write must not be blocked by the manual guard.

**Resolution.**

- The manual guard covers `plugin`, `theme`, and `core`. For core, it checks
  `hook_extra['type']='core'` and an `ABSPATH` marker (or a positive detector
  result for core). It blocks only when `safe_mode`,
  `version_control_lock`, and `version_lock_manual_updates` are on.
- The guard allows when `Updatronix_Update_Executor::is_executing()` is true or
  the global `updatronix_pro_update_pass_executing` equals the current
  `pass_id`. `is_executing()` is a compatibility wrapper around the pass flag.
- The guard allows WP-CLI (`defined('WP_CLI') && WP_CLI`) and documents it as
  the escape hatch. It does not block fresh installs (`action=install` or no
  matching `hook_extra`).
- Third-party REST routes that call the upgrader are covered by the same
  `upgrader_pre_install` guard when the manual toggle is on.
- A block writes one row: `action_type=prevented`, `status=warning`,
  `decision_state=blocked`, `origin=manual`, `performed_as=manual`,
  `update_context=single|bulk`, `reason_code=version_locked_*`, with
  `manager`/`scope`/`evidence` in context.

---

## 4. Dead-code and migration inventory

The inventory is in deletion order. “Step” refers to §5. The compatibility
window is the period during which old data still renders.

| # | Surface | Current code | Step | Migration / cleanup | Compatibility window | Rollback | Proof tests |
|---|---|---|---|---|---|---|---|
| D1 | Queue table `{base_prefix}updatronix_pro_update_queue` | `class-updatronix-update-queue.php` | 4 | One-shot `updatronix_pro_queue_removed` flag; `DROP TABLE IF EXISTS`; no row migration. Operational state is discarded by settled decision Q14. | Old rows are not rendered. Log rows remain the history. | Table cannot be restored with rows. The old code recreates an empty table on activation. Take a database backup before Step 4. | `UpdatePassDeletionTest`: `SHOW TABLES` has no queue table; a fixture with a legacy table migrates once; a second run is a no-op; a subsite request does not run it. |
| D2 | Queue options | `updatronix_pro_queue_db_version`, `updatronix_pro_update_runs`, `updatronix_pro_queue_default_migrated`, `queue_enabled` | 4 | Delete options; remove `queue_enabled` from the network Shield settings. Preserve every other Shield key. | Old settings JSON is ignored; defaults apply. | Old code recreates the options with defaults. | Settings/REST round-trip; uninstall cleanup; no `queue_enabled` key. |
| D3 | Queue transients and cron | `updatronix_pro_queue_rate_*`, `updatronix_pro_process_queue`, `updatronix_pro_process_queue_now` | 4 | Delete transients; `wp_clear_scheduled_hook()` for both hooks; clear on deactivation and uninstall. | No old events fire. | Old code reschedules on activation. | `wp_next_scheduled()` false; no rate transients; no spin. |
| D4 | Queue REST routes | Five routes in `class-updatronix-queue-rest.php` | 4 | Unregister; remove the class; the shipped JS no longer calls them. | Old builds receive 404. | Old build restores the routes from the previous release. | REST tests: each route 404; no route callback; capability/nonce tests removed. |
| D5 | Queue UI | Queue card, counters, retry/cancel/run, switch in `UpdateShieldPage.js`; `queueSummary.js`; queue settings keys | 4 | Remove the card and actions; delete `queueSummary.js` and its test; remove `queue_live`, `queue_enabled`, `queue_summary` from `useProSettings`; remove `queueCopy`. | No queue UI. The Shield page still shows Version Lock and blocked history. | Old build restores the UI; no data loss. | JS tests: no queue strings, no retry/cancel/run, no switch; Shield page fixtures pass. |
| D6 | Queue summary and Health checks | `class-updatronix-queue-summary.php`; Pro `updatronix_health_checks` filter; `should_register_queue_health()` | 4 | Delete the class and filter. Replace with three pass checks (OPEN-4): pass option stale/no follow-up, WP-Cron disabled, native lock busy. | No queue Health rows. Pass Health rows appear when the pass exists. | Old build restores the queue checks. | Health tests: no queue checks; pass checks fire only on their conditions. |
| D7 | Manual retry/cancel/run | Queue REST actions and Shield UI | 4 | Delete; no replacement. Manual updates remain native. | Old links/buttons 404 or are absent. | Old build restores the actions. | REST/JS tests: no manual route; no button. |
| D8 | Flow runner | `inc/update-flow/class-updatronix-flow-runner.php`; Flow-specific kernel methods | 4 (freeze), 5 (final) | Delete the runner; remove `POST /flow/run`, `GET /flow/runs/{id}`, the 5-minute cooldown, resume, and polling. Keep the chain editor and `GET /flow/chains`. | Flow page is read-only with a freeze notice during Step 4. Step 5 restores ordering and the last-pass card. | Restore the previous plugin build; do not reimplement a second execution path. Chain data is untouched. | REST tests: both routes 404; no runner class; no cooldown transient; no `WP_Automatic_Updater::update()` outside the pass; chain editor works. |
| D9 | Flow `Run now` and polling | `UpdateFlowPage.js` | 4 (freeze), 5 (final) | Remove Run now, run dialog, `startRun`, `runningChain`, `RUN_PATH`, `RUNS_PATH`, the 2-second poll, and the manual-run copy. | Read-only freeze notice; last-pass card after Step 5. | Previous build. | JS/E2E tests: no Run now; no polling; waiting/last-pass states. |
| D10 | Digest notifier | `class-updatronix-update-notifier.php`; fake digest log row | 2 (fake row), 4 (file) | Remove the fake row in Step 2. Delete the notifier in Step 4. Native per-item email is the only email. | Old digest stops; native email continues. | Old build restores the digest; no data loss. | Email tests: two items → two invocations → two native emails; no digest; denied item → no email. |
| D11 | Duplicate journal success row | `class-updatronix-update-journal.php` success path | 2 (delegate), 4 (delete) | Step 2 delegates the success row to `Decision_Log`; if the native row exists, enrich it and do not insert. Step 4 deletes the journal and replaces it with the pass log. | Old rows remain; new rows have one actual-update row. | Old build restores the journal. | Log dedupe tests: one native row; no second “updated” row; `attach_context` fallback. |
| D12 | Big blue delay notice | `inc/admin/native-update-delay-notice.php`; require in `updatronix.php` | 3 | Delete the file and require. Add the one-line WP-Cron hint in `inc/admin/native-update-time.php`. | No banner; native text is overridden per row; one-line hint only when `DISABLE_WP_CRON`. | Restore the file and require. | UI tests: no banner; hint only when disabled; capability check. |
| D13 | Provenance-blind detector path | `class-updatronix-version-control-detector.php` `get_native_context()` and `composer_state()` | 1 | Replace with item-root/ancestor scan and project Composer provenance. Keep `updatronix_pro_is_version_controlled` as an override (yes→no only). Cache request-scoped; invalidate on the existing hooks. | Old stored settings are preserved. Old log rows are not re-detected. | Revert the detector files; no schema. | Detector tests: item-root `.git`, parent `.git`, site `.git`, theme, core, project Composer, wp-packagist, Bedrock, bundled, lock-only, symlink, unreadable, missing updater. |
| D14 | Old log action types and statuses | `prevented`, `incompatible`, `safe_mode_disabled`, `disabled`, `same_version`, `auto_update_reenabled`, `delayed` | 2 | Keep every old value readable. Add `pass` and `advisory` to the action allowlist; add `system` to the log types. Map old codes through the alias map; never reword an existing `__()` string. | Old rows render with “Legacy — reason not recorded” when the new fields are empty. | Old code ignores the new columns. | Log/export tests: old row, old status, old action, alias map, legacy label. |
| D15 | Ownership handshake | `class-updatronix-shield-ownership.php`; Safe Mode `is_owned()`; Flow runner `remember()` | 4 (replace), 5 (remove call) | Keep a no-op shim until Step 4. Step 4 replaces Safe Mode’s `is_owned()` with the pass execution flag and deletes the shim. Step 5 removes the runner `remember()` call (the runner is already gone). | No ownership rows; the pass option is the ownership source. | Old build restores the handshake. | Ownership tests: no `is_owned()` call; pass flag exempts the pass; Safe Mode still blocks non-pass items. |
| D16 | Executor execution path | `Updatronix_Update_Executor::execute()`, `native_update()`, `classify_result()`, lock path | 4 | Keep the read-only methods (`get_pending_offer`, `get_installed_version`, `get_item_name`) and `is_executing()` as a pass-flag wrapper. Remove `execute()` and the lock path. | No engine execution path; native `run()` is the only executor. | Old build restores the executor. | Executor tests: no `execute()`; read-only methods; `is_executing()` true only during a pass. |
| D17 | Last-20 run summaries | `updatronix_pro_update_runs` option in `class-updatronix-update-journal.php` | 4 | Delete the option; no migration. The pass summary log row is the audit source; `updatronix_pro_last_pass` is a one-entry cache. | Old run summaries are not shown; log rows remain. | Old code recreates the option. | Migration tests: no `updatronix_pro_update_runs`; last-pass cache one entry; logging-off fallback. |
| D18 | Flow TTL | `updatronix_pro_flow_offers_ttl` | 5 | Alias to `updatronix_schedule_offer_ttl` for one release; mark deprecated; remove later. | Old filter calls still work. | Restore the old filter. | TTL tests: one value; alias works; no second freshness rule. |
| D19 | Queue live switch | `queue_live` and the Shield queue switch | 4 | Remove the live flag and the switch. The pass has one emergency bypass (`updatronix_pro_update_pass_enabled`). | No queue switch. | Old build restores the switch (default off). | Settings tests: no `queue_live`; bypass works. |

### 4.1 Compatibility window

- **Log rows:** old rows keep `''`/`0`/`NULL` in the new columns and render with
  “Legacy — reason not recorded.” The delay-prefix backfill is the only
  deterministic backfill (`event_key LIKE 'udly|%'`).
- **Event keys:** old keys remain unique; new keys use `v2|`. The column is
  `varchar(191)`, so both fit.
- **Flow chains:** saved chains use `core:wordpress`; no migration. `core:core`
  is accepted as an alias if an old draft contains it.
- **Settings:** `version_control_lock` values are preserved; `queue_enabled` is
  removed; `delay_updates` values remain valid (`0` means off; old 1–365
  values are unchanged); `time=''` remains valid and the UI falls back to
  `03:00`.
- **REST:** removed routes return 404; the shipped JS no longer calls them.
- **Queue rows:** not rendered, not migrated. The log is the history.

### 4.2 Rollback rules

| Step | Rollback point | Data loss |
|---|---|---|
| 1 Version Lock | Revert the Pro/Free files; keep the Shield option. The new key is inert. Detector cache is request-scoped. | None. |
| 2 Logger | Set `updatronix_log_decision_layer_enabled=false`; `log_event()` delegates to the old `log()` shape; the UI/export fall back to legacy fields. Keep the new columns. | None; old columns/rows remain. |
| 3 Schedule | Disable `updatronix_native_update_time_enabled`; native text returns. Revert files; restore the big notice if needed. Settings remain valid. | None. |
| 4 Queue | First set `updatronix_pro_update_pass_enabled=false`; the gate/follow-up no-op and native runs the whole loop. Then revert files. The queue table is dropped and its rows are gone by design; the log remains. The pass option is deleted. | Queue operational rows. Take a backup. |
| 5 Flow | Revert the Flow files; keep the pass, log, and chains. Delete a stale `updatronix_pro_update_pass` option. | None; log rows remain. |

No version bump, commit, or release is part of any rollback.

### 4.3 Tests that prove the deletion leaves no dead path

- `grep` for `Updatronix_Update_Queue`, `Updatronix_Update_Kernel`,
  `Updatronix_Queue_Scheduler`, `Updatronix_Queue_REST`,
  `Updatronix_Queue_Summary`, `Updatronix_Update_Notifier`,
  `Updatronix_Update_Journal`, `Updatronix_Flow_Runner` returns only
  compatibility notes or no result.
- `SHOW TABLES` has no queue table; `wp_next_scheduled()` has no queue hooks.
- REST tests assert 404 for every removed route.
- JS tests assert no queue strings, no Run now, no polling.
- The live fixture asserts one install, one email per item, one item row, one
  pass summary, no duplicate “updated” row, no digest.
- The migration test seeds a legacy table/options/transients/cron and asserts a
  clean state after one run and a no-op after the second.
- The Health test asserts no queue checks and the three pass checks.

---

## 5. Implementation order

### 5.1 The rule and the proposed order

**Owner rule:** Flow is implemented last, after the other features are changed.
Nothing in this order puts Flow before the execution model.

| Step | Feature | Repo | Task file | Why this position |
|---|---|---|---|---|
| 1 | Version Lock | Pro (+ Free toggle guard) | `updatronix-pro/.agents/tasks/2026-10-28-feature-version-lock-rework.md` | Independent; its decision is consumed by Queue, Flow, Schedule, and Logger. It removes items from the update set and makes the later states truthful. |
| 2 | Logger foundation | Free (+ Pro `Decision_Log`) | `updatronix/.agents/tasks/2026-10-28-feature-logger-rework.md` | Queue must write the final schema/key from its first commit. The Free schema is independent of the pass. The Pro adapter is a thin wrapper over `log_event()`. |
| 3 | Update Schedule | Free (+ Pro `Schedule_Facts`) | `updatronix/.agents/tasks/2026-10-28-feature-update-schedule-rework.md` | Queue needs the calculator for `eligible_at` and the canonical item reader. Schedule’s queue/Flow branches are forward-compatible with defaults and tested with stubs. |
| 4 | Update Queue (pass) | Pro (+ Free tests) | `updatronix-pro/.agents/tasks/2026-10-28-feature-update-queue-rework.md` | The execution model. It uses the Logger schema and the Schedule calculator; deletes the queue stack; freezes Flow execution. |
| 5 | Update Flow | Pro (+ Free tests) | `updatronix-pro/.agents/tasks/2026-10-28-feature-update-flow-rework.md` | Last. It consumes the pass gate, Version Lock decision, Schedule hold, and Logger reason codes. It removes the freeze and restores ordering and the last-pass card. |

### 5.2 Why not the prompt’s likely order (Queue second, Logger fourth)

The prompt’s likely order is Version Lock → Queue → Schedule → Logger → Flow.
It is workable only if Queue accepts temporary compromises:

- Queue would write pass rows with the old key and `update` action, then Logger
  would rewrite them. Two formats, two test matrices, and a migration risk.
- Queue would compute `eligible_at` from the ledger/`max(first_seen + delay,
  time())`, then Schedule would replace it. The pass would store a time that
  the Schedule later calls wrong.
- Schedule would need the pass facts to display `queued`, but it can default to
  `queue.state=none` and test the branch with a stub. It does not need Queue
  first.
- Logger would need the pass id and final vocabulary, but its Free schema and
  `Decision_Log` adapter can land before the pass and be consumed by Queue.

The recommended order removes both temporary compromises. The only cost is that
the Schedule ships before the pass; its `queued` and real `waiting` branches are
activated by Steps 4 and 5 through the same filter, with no second
implementation.

If the owner rejects the order, the fallback is: Queue second with a temporary
`update` action and a ledger-based `eligible_at`, then Logger/Schedule rewrite.
That fallback is recorded as a risk, not recommended.

### 5.3 Step 1 — Version Lock

**Repo and task:** Pro; `2026-10-28-feature-version-lock-rework.md`.

**Owns:**

- Pro: detector, provenance, policy, policy chain order, engine gate, Shield
  state, Shield enforcement, Safe Mode gate, Shield settings/page/JS, native
  notices/toggle locks, manual guard, ordered native adapter at 19, JS augment,
  tests, docs.
- Free: `updatronix_auto_update_can_toggle` filter in the REST toggle handlers;
  `AutoUpdateToggleGuardTest`; no other Free production change.
- Options: `version_lock_manual_updates` inside the existing network Shield
  option; `version_control_lock` relabel; no new option.
- Hooks: `updatronix_pro_is_version_controlled` (existing);
  `updatronix_auto_update_can_toggle` (new).

**Must not touch:** `class-updatronix-update-pass*`, `class-updatronix-flow-*`,
`class-updatronix-queue-*`, `class-updatronix-logger.php`,
`class-updatronix-database.php`, `class-updatronix-autoupdatedelay.php`,
`SchedulePanel.js`, `UpdateFlowPage.js`, `native-update-time.js`.

**Entry criteria:** Owner approves this synthesis and OPEN-1/OPEN-2/OPEN-3;
base commits recorded; existing suites baseline recorded; the version-lock
analysis and task file are attached.

**Task amendments:**

1. T6: canonical `reason_code` values are `version_locked_native`,
   `version_locked_composer`, `version_lock_unknown`, and
   `version_lock_unverified`. Keep `native_vcs`/`version_controlled` as
   internal codes and aliases; do not write them as the public reason.
2. T8: replace the Version Lock-only adapter at 20 with the **ordered adapter
   at 19** (environment/native guards → Version Lock → WP compatibility →
   PHP compatibility). Free delay stays at 21. The Safe Mode adapter stays at
   `PHP_INT_MAX - 5` as a fallback and must not double-write.
3. T13: provide `decision()` and the `Shield_State::version_control()` map.
   Do not create the Schedule facts callback here; Step 3 owns it.
4. T18: add the **core** case to the manual guard (`ABSPATH` marker) and the
   pass execution flag/`is_executing()` exemption.
5. T20: add tests for the ordered adapter, core manual guard, and the
   fail-closed/fail-open split.

**Exit criteria:**

- Item-root, parent, site-wide, theme, core, project Composer, wp-packagist,
  Bedrock, bundled, lock-only, symlink, unreadable, and missing-updater cases
  have deterministic tests.
- A locked item is denied by Version Lock before compatibility and delay; one
  row; no duplicate with Safe Mode.
- Native plugin row, theme row/card, update-core, Auto updates, and Shield card
  show the manager state and lock the toggle.
- The manual toggle off/on behaves as settled; WP-CLI is documented and
  allowed; the pass’s own write is exempt.
- Multisite network/subsite matrix passes or self-skips with a reason.
- Lint is clean; no version bump.

**Test gate:**

```bash
cd wp-content/plugins/updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness counterfactual
bin/harness mutation          # MSI >= 70 on changed lines
cd ../updatronix
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
```

E2E `tests/e2e/pro-106-version-lock.spec.js` if the host supports Playwright;
otherwise record the gap.

**Manual verification:** plugin row, single-site theme card, network theme row,
update-core plugin/theme tables, Updatronix Auto updates row, Shield card,
direct `update.php` URL, bulk update, WP-CLI, manual toggle off/on, Safe Mode
off, multisite network admin and subsite.

**Rollback point:** base commit; revert the Pro/Free files; keep the Shield
option; delete any persistent detector transient (the plan prefers
request-scoped caching). No schema change.

### 5.4 Step 2 — Logger foundation

**Repo and task:** Free; `2026-10-28-feature-logger-rework.md`; Pro
`Decision_Log` in the same step.

**Owns:**

- Free: `Updatronix_Log_Taxonomy`, `Updatronix_Log_Event`,
  `Updatronix_Log_Query`, `Updatronix_Log_Retention`, the schema and migration,
  `log_event()`, `attach_context()`, `update_summary()`,
  `get_latest_for_item()`, the native logger context, core finalization,
  native/manual/external/health write points, activity log filters/details,
  export, external log, retention, tests, docs.
- Pro: `Updatronix_Decision_Log`; the Shield, Safe Mode, and Version Lock block
  write points; the journal’s interim delegation; the notifier’s fake-row
  removal.
- Options: `updatronix_log_db_version` / `Updatronix_Database::SCHEMA_VERSION`;
  `updatronix_log_decision_layer_enabled` (rollback switch); no plugin version
  bump.

**Must not touch:** `class-updatronix-autoupdatedelay.php` (Step 3 owns the
delayed-row rewrite), the pass/queue/Flow classes, `SchedulePanel.js`,
`native-update-time.js`, `UpdateShieldPage.js` beyond the log links, Shield
settings.

**Entry criteria:** Step 1 exit; the logger task file and analysis attached;
the current log table schema captured (`SHOW CREATE TABLE`, `SHOW INDEX`, row
count, one delayed row).

**Task amendments:**

1. T2: use a dedicated `SCHEMA_VERSION` for the log table; do not change
   `UPDATRONIX_VERSION` or `UPDATRONIX_DB_VERSION`. Additive columns only.
2. T3: add `version_lock_unverified` to the reason enum, `cli` to the origin
   enum, and `system` to the log types. Add `pass` and `advisory` to the Free
   base action allowlist. Keep `queue_*` codes as legacy aliases, not active
   writers.
3. T5: keep `log()` exactly compatible; `log_event()` is the canonical writer.
4. T7: **do not edit `class-updatronix-autoupdatedelay.php`**. The delayed-row
   rewrite moves to Step 3. Keep the native logger and core finalizer.
5. T12: `Decision_Log` takes the surviving `Updatronix_Update_Job` and the
   policy result. It writes only through `log_event()`.
6. T13: delegate the journal success row to `Decision_Log` for the interim;
   migrate Shield, Safe Mode, and Version Lock block write points; remove the
   notifier’s fake row. Do not delete the journal; Step 4 does.
7. T14: do **not** edit the Flow runner. Provide `record_wait()` and
   `record_cancel()` with unit stubs. Step 5 wires them; Step 4 wires
   `record_pass()` in the pass log.
8. T15: the truthfulness matrix covers Free states now and Pro states with
   stubs; the full matrix runs in Step 5.

**Exit criteria:**

- A v1 log table upgrades once; old rows render with “Legacy — reason not
  recorded”; the delay-prefix backfill is idempotent.
- One native actual-update row per disk change; no duplicate journal success
  row; `attach_context()` enriches without re-firing the insert hook.
- Every UI filter is server-side; the total and export match; the status map
  never labels `info`/`warning`/unknown as “Success.”
- External log old callers work; the new optional fields store; both hooks are
  additive; a strict consumer test passes.
- The five indexes exist; `EXPLAIN` is recorded; no speculative item/target
  index.
- `Decision_Log` writes one row per item/target/attempt/policy/pass; repeated
  waits/blocks are counted in the pass summary, not reinserted.
- No version bump.

**Test gate:**

```bash
cd wp-content/plugins/updatronix
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
WP_MULTISITE=1 bin/harness integration --filter Multisite
cd ../updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness counterfactual
bin/harness mutation
```

**Manual verification:** activity log filters (reason, state, origin, pass,
chain, next eligible), details for a block/hold/wait/failure/success/legacy
row, export CSV/JSON/XML/text, external log, both hooks, keyboard/screen
reader.

**Rollback point:** set `updatronix_log_decision_layer_enabled=false`;
`log_event()` delegates to the old shape; the UI/export fall back. Keep the new
columns and old rows. No data loss.

### 5.5 Step 3 — Update Schedule

**Repo and task:** Free; `2026-10-28-feature-update-schedule-rework.md`; Pro
`Schedule_Facts` in the same step.

**Owns:**

- Free: `Updatronix_Item_State` (new canonical reader),
  `Updatronix_Schedule_State`, `Updatronix_Schedule_Context`,
  `inc/admin/native-update-time.php`, `assets/src/js/native-update-time.js`,
  `SchedulePanel.js`, `scheduleDraft.js`, `options.php`,
  `class-updatronix-settings.php`, `class-updatronix-cron.php`,
  `class-updatronix-autoupdatedelay.php` (peek + `observe_offer()` +
  delayed-row rewrite),
  `updatronix.php`, `package.json`, tests, docs.
- Pro: `inc/update-engine/class-updatronix-schedule-facts.php` — the single
  `updatronix_schedule_item_state` callback owner. It returns `lock` from
  Version Lock, `policy` from the read-only compatibility/environment summary,
  and `flow`/`queue` defaults (`none`). Steps 4 and 5 extend it.
- Hooks: `updatronix_schedule_item_state`, `updatronix_schedule_offer_ttl`,
  `updatronix_native_update_time_enabled`.

**Must not touch:** the log schema/API, the pass/queue/Flow classes, the
Version Lock detector/UI, the Shield settings/page, `UpdateFlowPage.js`.

**Entry criteria:** Steps 1–2 exit; `log_event()` and the reason taxonomy are
available; the Version Lock decision and Shield state map are available; the
Schedule grill/QA notes are attached.

**Task amendments:**

1. T2: add `Updatronix_Item_State::get()` as the canonical reader for
   installed version, offer, target, name, type, and id. `Schedule_Context`
   consumes it; it does not read transients independently.
2. T2: add the `policy` input and the `blocked`, `failed`, and `uncertain`
   display states. Precedence: `wp_cron_disabled`, `not_scheduled`, `overdue`,
   `locked`, `blocked`, `not_managed`, `no_offer`/`unknown_offer`,
   `waiting_for_predecessor`, `failed`/`uncertain`, `not_observed`, `held`,
   `queued`, `eligible`, `unknown`.
3. T3: the context uses `Item_State`; one map per request; cap 200; a missing
   or truncated lock/policy fact is `unknown`, not `eligible`.
4. T4: add `observe_offer( $type, $offer )` next to `peek_first_seen()`.
   `observe_offer()` is idempotent: it sets `first_seen` only when the row is
   absent, writes nothing else, and logs nothing. `peek_first_seen()` reads the
   ledger written by the delay gate (Free-only) or `observe_offer()` (Pro); it
   never writes.
5. T5: own the delayed-row rewrite via `log_event()` with `schedule_hold`,
   `decision_state=delayed`, `next_eligible_at` from the calculator. Remove
   `max(first_seen + delay, time())`.
6. T11–T15: server filters at 90 (Version Lock) and 110 (Schedule); JS order;
   no-op on locked rows; distinct data attributes; no duplicate notices.
7. T16: delete the big blue notice; add the one-line WP-Cron hint.
8. T18: create the Pro `Schedule_Facts` callback as the single owner. It
   returns `lock`, `policy`, `flow=none`, `queue=none`; it is read-only and
   request-cached. Do not create a second callback in Steps 4/5; extend this
   one.

**Exit criteria:**

- Every display state has a truthful sentence; no state falls through to a
  native countdown.
- The picker exposes every hour/minute; the hold is `0..365`; recurrence
  preserves the preferred time; the core-security claim is gone.
- The delayed log time equals the calculator’s `eligible_at`; no display
  writes a row.
- `observe_offer()` is idempotent; it creates no row, no log entry, and no
  prune; a page render cannot create a `first_seen` row.
- Native plugin/theme/update-core surfaces are correct; the banner is gone;
  the WP-Cron hint is one line.
- Multisite network/subsite surfaces are truthful.
- The 200-item map completes within the test budget; no detector/planner per
  row.

**Test gate:**

```bash
cd wp-content/plugins/updatronix
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
WP_MULTISITE=1 bin/harness integration --filter Multisite
cd ../updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness counterfactual
bin/harness mutation
```

**Manual verification:** Schedule screen at 1280/960/560/390; keyboard and
screen reader; plugin row, single-site theme card, network theme row,
update-core; held, waiting, locked, queued, blocked, failed, no-offer,
not-observed; WP-Cron disabled; subsite.

**Rollback point:** disable
`updatronix_native_update_time_enabled`; native text returns. Revert the files;
restore the big notice if needed. Settings remain valid; no data migration.

### 5.6 Step 4 — Update Queue (the pass)

**Repo and task:** Pro; `2026-10-28-feature-update-queue-rework.md`; Free tests
only.

**Owns:**

- Pro: `Updatronix_Update_Pass`, `Updatronix_Update_Pass_Order`,
  `Updatronix_Update_Pass_Gate`, `Updatronix_Update_Pass_Runner`,
  `Updatronix_Update_Pass_Log`, `Updatronix_Update_Pass_Migration`;
  `Updatronix_Update_Executor` read-only; `Updatronix_Update_Engine` facade;
  the Pro bootstrap; `options.php`; `class-updatronix-update-shield.php`;
  `UpdateShieldPage.js`; `useProSettings.js`; `shieldState.js`;
  `class-updatronix-pro-uninstall.php`; `Schedule_Facts` queue branch; delete
  the queue stack/UI/Health/manual actions; freeze Flow execution; pass tests,
  live fixture, docs.
- Options: `updatronix_pro_update_pass` (active state, non-autoloaded),
  `updatronix_pro_last_pass` (one-entry cache), `updatronix_pro_queue_removed`
  (migration flag). Delete the queue options.
- Hooks: `updatronix_pro_update_pass_order`,
  `updatronix_pro_update_pass_executing` (global/filter),
  `updatronix_pro_update_pass_enabled`, `updatronix_pro_update_pass_follow_up`
  (cron), `updatronix_health_checks` (three pass checks).

**Must not touch:** the log schema/API, the Schedule calculator/UI, the
Version Lock detector/UI, the Flow planner/readiness (Step 5). The only Flow
edits are the Step 4 freeze: delete the runner, remove the run routes, remove
Run now/polling, show the notice.

**Entry criteria:** Steps 1–3 exit; `Item_State`, `Schedule_State`, and
`Decision_Log` are available; the Version Lock decision is available; a
database backup is taken; the queue live fixture baseline is recorded.

**Task amendments:**

1. T1: the pass example uses `core:wordpress`. `MAX_ATTEMPTS=2` is a safety
   bound; the normal flow attempts once and skips a failed node for the pass.
2. T2: stale pass replacement, last-item reconciliation, no resume.
3. T3: the order builder consumes `Item_State` and `Schedule_State`; it calls
   `Updatronix_AutoUpdateDelay::observe_offer()` (added in Step 3) for every
   offer before policy evaluation (C10). Until Flow lands, the order is native
   only; `flow_snapshot` is empty and the Flow branch is a no-op.
4. T4: the gate is selection-only. It never re-runs the policy chain and never
   writes a denial row. One selection per request; never `true`; translations
   not gated; fail-open only when the pass cannot be created.
5. T5: `native_lock_busy` is `waiting`/`skipped`, never `failed`. A held native
   lock schedules a follow-up in five minutes, capped at three waits; after the
   cap, skip the node and advance. No second lock.
6. T6: finalize mapping. A manual update to another version is `uncertain`
   with `partial_state` and `context.manual_update=true`; a target install is
   `succeeded`; an unchanged install with the offer still pending is `failed`;
   a withdrawn offer is `skipped`. No in-pass retry.
7. T7: native per-item email; delete the digest; no Pro `wp_mail()`.
8. T8: `Updatronix_Update_Pass_Log` calls `Decision_Log` for one item row and
   one pass summary; the native actual-update row is enriched via
   `updatronix_log_context`, with `attach_context()` as the fallback. No second
   “updated” row.
9. T9: extend the Step 3 `Schedule_Facts` with `queue` facts; do not create a
   second callback. Add `updatronix_pro_update_pass_*`; the Flow branch stays
   `none`.
10. T10–T12: cutover, delete the queue stack, UI, summary, Health, manual
    actions, and switch. Add three pass Health checks (OPEN-4). No queue
    strings.
11. T13: migration drops the queue table, options, transients, cron, and
    `queue_enabled`; writes `updatronix_pro_queue_removed`; no row migration.
12. T14: deactivation/uninstall deletes the pass, last-pass cache, legacy queue
    state, and cron; main-site only.
13. T15: shrink the bootstrap; remove the scheduler/REST; **freeze Flow**:
    delete the runner, remove `POST /flow/run` and `GET /flow/runs/{id}` (404),
    remove the cooldown, remove Run now/polling, show the read-only notice.
14. T16–T22: tests, live fixture, docs, gate, manual, rollback.

**Exit criteria:**

- Exactly one item can pass per native invocation; the gate never returns
  `true`; two items produce two invocations and two native emails.
- One item outcome row and one pass summary per pass; no duplicate “updated”
  row; no digest; no queue table/route/UI/cron.
- A held item never bypasses its hold; a failed node is skipped for the pass;
  the next pass retries once.
- The follow-up drains the pass; lock-busy waits; stale pass replacement works;
  a fatal/disk-full leaves an attempt marker and recovers.
- Flow execution is frozen; the chain editor still works; no second execution
  path.
- The live fixture, migration, multisite, and Free suites are green.
- No version bump.

**Test gate:**

```bash
cd wp-content/plugins/updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness e2e            # if supported
bin/harness counterfactual
bin/harness mutation
cd ../updatronix
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
```

The live fixture is `UpdatePassLiveFixtureTest`: 0/1/2/N items, plugin+theme,
plugin+core, translation, one failure, one lock-busy, one fatal, one disk-full,
one stale offer, one item removed mid-pass, two cron requests, a manual update
during a pass, a new offer mid-pass, pass disabled, license inactive,
multisite, WP-CLI, a held item, a Version Lock item, and a Flow predecessor
waiting (the last one is a stub until Step 5).

**Manual verification:** scheduled tick with two items (one install, one email
each, one pass row); follow-up and pass end; held item; Version Lock item;
lock-busy; fatal; disk-full; two cron requests; manual update; WP-CLI;
multisite; no queue screen; Flow freeze notice.

**Rollback point:** first set `updatronix_pro_update_pass_enabled=false`; the
gate/follow-up no-op and native runs the whole loop. Then revert the files. The
queue table rows are gone by design; the log remains. The pass option and
last-pass cache are deleted. No version bump.

### 5.7 Step 5 — Update Flow

**Repo and task:** Pro; `2026-10-28-feature-update-flow-rework.md`; Free tests
only.

**Owns:**

- Pro: `Updatronix_Flow_Readiness`, `Updatronix_Flow_Graph`,
  `Updatronix_Flow_Snapshot`; the planner and plan metadata; the pass order
  Flow integration; the pass runner descendants/snapshot/revalidation; the pass
  log Flow calls; the Flow REST controller; `UpdateFlowPage.js`;
  `flowRunState.js`; `Schedule_Facts` flow branch; delete the runner and the
  run routes; remove the freeze; the last-pass card; tests, live fixture, docs.

**Must not touch:** the log schema/API, the Schedule calculator/UI, the
Version Lock detector/UI, the pass schema/gate (read/write through its public
API), the Shield settings/page.

**Entry criteria:** Steps 1–4 exit; the pass freeze is in place; the
`Decision_Log` wait/cancel API, the Schedule facts, and the Version Lock
decision are available; the owner approves no release between Steps 4 and 5
(OPEN-2).

**Task amendments:**

1. T1: readiness consumes `Item_State`, `Schedule_State`, and the Version Lock
   decision. It does not call `Updatronix_AutoUpdateDelay::filter_*()` or the
   detector. `already_latest` requires fresh `checked` evidence; unknown is
   never done.
2. T2: planner emits `flow_nodes`, `flow_edges`, `flow_snapshot`,
   `flow_errors`, `flow_overflow`; `core:wordpress`; all incoming edges; no
   missing-offer `skipped` shortcut; per-chain invalid handling.
3. T3: the pass order consumes the Flow graph; Flow nodes first, native order
   for the rest; all predecessors; 50-node bound; no second store.
4. T4: pass-start snapshot; deleted/edited/disabled chain handling; stale pass
   replacement; target change; offer withdrawal; descendants-only propagation;
   no global cancel.
5. T5: one schedulable node; one item per invocation; no forced opt-in; a held
   node never bypasses its hold; waiting/locked/blocked nodes are not failed;
   manual/WP-CLI updates are logged and may make a node done.
6. T6: remove the runner and the two routes; remove the cooldown; add
   `active_pass`/`last_pass` to `GET /flow/chains`; remove the freeze notice.
7. T7: Flow UI waiting/blocked/locked/failed states; read-only last-pass card;
   no polling; no Run now.
8. T8: `Decision_Log::record_wait()`, `record_cancel()`, `record_pass()`; the
   canonical `flow_*` reasons; one wait row at start, one at permanence; one
   canceled row per node; no per-tick row.
9. T9–T10: tests, live fixture, manual audit, docs, gate.

**Exit criteria:**

- One pass, one gate, one order, one execution path.
- Every chain node has a truthful state; a waiting node names its predecessor
  and reason; a held predecessor is never bypassed; a failed predecessor only
  affects its descendants; independent branches continue.
- A non-opted item is `not_managed`; Flow never forces the opt-in.
- The log has one waiting row at start, one at permanence, one canceled row per
  canceled node, and one pass summary.
- The Flow page has no manual execution and no polling; the last pass is
  read-only and survives a reload.
- The freeze notice is gone; the chain editor still works.

**Test gate:**

```bash
cd wp-content/plugins/updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness e2e            # if supported
bin/harness counterfactual
bin/harness mutation
cd ../updatronix
bin/harness test
```

**Manual verification:** day-1/day-2 gateway/WooCommerce; held/locked/blocked/
failed/uncertain; deleted/edited chain; target change; offer withdrawal;
manual/WP-CLI; two cron requests; multisite; last-pass card after reload; no
Run now; no polling.

**Rollback point:** revert the Flow files; keep the pass, log, and chains;
delete a stale `updatronix_pro_update_pass` option. No data loss; log rows
remain.

### 5.8 Release structure

- **Recommended:** one release containing Steps 1–5. Flow is implemented last
  within the release; there is no shipped Flow freeze.
- **If the owner wants smaller releases:** Steps 1–3 can ship together (Version
  Lock, Logger, Schedule); Step 4 and Step 5 must ship together, because Step 4
  freezes Flow. A release between Step 4 and Step 5 is allowed only with
  OPEN-2 approval and the freeze notice.
- Do not bump `UPDATRONIX_VERSION`, headers, `Stable tag`, or package versions
  without explicit owner authorization. The release skill owns the bump.

### 5.9 What must be true before the first implementation tab

1. The owner approves the order and resolves OPEN-1, OPEN-2, and OPEN-3.
2. Each task file records the amendments in §5; no new task file is created.
3. Base commits for both repos are recorded; the baseline suites are green or
   every pre-existing failure is documented.
4. A database backup is taken before Step 4.
5. The migration flag/rollback switches are named and tested in the task file.
6. No production code is edited before this synthesis is approved.

---

## 6. Shared contracts

These are the contracts the implementation must share. A contract that is not
here is owned by the plan named in §5; do not invent a second shape.

### 6.1 Item identity and offer shape

#### Canonical keys

| Type | Key | Example | Notes |
|---|---|---|---|
| Plugin | `plugin:{plugin_file}` | `plugin:akismet/akismet.php` | The exact WordPress plugin basename. Do not lowercase; Linux filesystems are case-sensitive. |
| Theme | `theme:{stylesheet}` | `theme:twentytwentyfour` | The stylesheet, not the display name. |
| Core | `core:wordpress` | `core:wordpress` | Canonical. `core:core` is a read-only legacy alias, normalized at every boundary and never written. |
| Translation | `translation:{type}:{slug}:{language}` | `translation:plugin:akismet:fr_FR` | Details-only. Not a pass node, not a Flow step, no countdown. |
| System | `system:{slug}` | `system:pass:{pass_id}`, `system:wp_cron` | Log rows only; no installed version, no offer. |

Normalization rules:

- Trim; reject an empty type or id; reject a key with more than one `:` used
  as a separator (use `explode(':', $key, 3)`).
- `core:core` → `core:wordpress`; `core:wordpress` stays.
- Do not translate, lowercase, or sanitize the plugin file/stylesheet beyond
  `sanitize_text_field()` at the input boundary. The identity must match the
  filesystem and the native transient.
- A key is stable across passes; the target version is not part of the key.

#### One reader

Add `Updatronix_Item_State` in Free (`inc/classes/class-updatronix-item-state.php`)
in Step 3. It is the only code that answers the six questions in the prompt:
installed version, pending offer, target, item name, type, and id.

```php
final class Updatronix_Item_State {
    /**
     * @return array<string,mixed>|null Null when the item does not exist.
     */
    public static function get( string $type, string $id ): ?array;

    /**
     * @return array<string,array<string,mixed>> Keyed by `type:id`.
     */
    public static function map( string $surface = '', int $limit = 200 ): array;

    public static function normalize_key( string $type, string $id ): string;

    public static function reset_cache(): void;
}
```

Return shape:

```php
array(
    'type'          => 'plugin',
    'id'            => 'akismet/akismet.php',
    'key'           => 'plugin:akismet/akismet.php',
    'name'          => 'Akismet Anti-spam',
    'installed'     => '5.3',
    'target'        => '5.4',
    'offer'         => $offer_object_or_null,
    'offer_state'   => 'pending', // pending|none|stale|unknown
    'checked'       => true,      // item present in the native `checked` map
    'last_checked'  => 1761706800,
    'auto_update'   => 'on',      // on|off|forced_off|forced_on
    'root'          => '/path/to/plugin',
    'exists'        => true,
    'is_core'       => false,
    'is_translation'=> false,
)
```

Rules:

- Read the plugin/theme/core transients once per request. `map()` builds the
  map once and caps it at 200 by default (filter
  `updatronix_item_state_limit`). Report truncation in the return metadata;
  callers treat a missing entry as `unknown`, not `eligible`.
- `offer_state` is `stale` when `last_checked` is older than
  `updatronix_schedule_offer_ttl` (default 6 hours) or missing; `unknown` when
  the item is absent from `checked` or the installed version is unreadable.
- `auto_update` is `forced_off` for `AUTOMATIC_UPDATER_DISABLED`,
  `DISALLOW_FILE_MODS`, or `WP_AUTO_UPDATE_CORE === false` (core);
  `forced_on` for a native force filter; otherwise `on`/`off` from the
  `auto_update_plugins`/`auto_update_themes` option or the core mode.
- Theme offers: add `theme = stylesheet` when the transient row lacks it, so
  the ledger hash and the item key match the gate.
- Cache key: `type:id` per request. `reset_cache()` runs on settings save,
  offer refresh, pass change, and lock change.
- No persistent cache in v1. If one is added, the key is versioned and the TTL
  is at most 5 minutes; it must include the target and the settings hash.
- Consumers: `Updatronix_Schedule_Context`, `Updatronix_Update_Pass_Order`,
  `Updatronix_Flow_Readiness`, and the Pro `Updatronix_Update_Executor`
  read-only methods. The Pro methods delegate to `Item_State` when it exists;
  they do not read transients independently.
- Exceptions: the Free native logger may use the upgrader result for the
  actual-update row; the external log uses its own payload; tests may inject
  fixtures.

#### One offer shape

The pending offer is the native transient object, not a copy. Do not clone it
to force `autoupdate=true`; the pass never forces the opt-in. The reader exposes
the object and the target; it does not mutate it.

### 6.2 Detection result

The Version Lock detector returns one shape. No other feature runs the
detector; they consume `Updatronix_Version_Control_Policy::decision()` or the
request map.

| Field | Values | Meaning |
|---|---|---|
| `type` | `plugin`, `theme`, `core` | Item type. |
| `id` | plugin file, stylesheet, `wordpress` | Item id. |
| `native` | `yes`, `no`, `unknown` | A VCS marker at the item root or an ancestor. |
| `composer` | `yes`, `no`, `unknown` | Project Composer provenance. |
| `source` | `native`, `composer`, `unknown`, `none` | Primary source. |
| `manager` | `Git`, `SVN`, `Mercurial`, `Bazaar`, `Composer`, `WP Packagist`, `Bedrock`, `external`, `none` | Primary label. |
| `scope` | `item`, `parent`, `site`, `composer`, `unknown`, `none` | Where the evidence is. |
| `evidence` | redacted string | Marker + basename, for example `.git at plugin root` or `installed.json package akismet`. Never a full path in a log row. |
| `path` | string | Internal path for detection; not logged. |
| `reason` | string | Short human reason. |
| `reason_code` | `version_locked_native`, `version_locked_composer`, `version_lock_unknown`, `version_lock_unverified`, or empty | Canonical code. |
| `bundled` | bool | The item bundles Composer dependencies but is not project-managed. |
| `active` | bool | `safe_mode && version_control_lock`. |
| `native_scope` | `item`, `parent`, `site`, `unknown`, `none` | Native marker scope. |
| `composer_package` | string | Package name from `installed.json`/`installed.php`, if any. |
| `composer_project` | string | Project label (`WP Packagist`, `Bedrock`, `Composer`), if any. |

Mapping to the prompt’s four words:

| Prompt term | Detector result |
|---|---|
| `native_vcs` | `source=native`, `native=yes`, manager Git/SVN/Hg/Bzr. |
| `composer_managed` | `source=composer`, `composer=yes`, manager Composer/WP Packagist/Bedrock. |
| `neither` | `source=none`, `native=no`, `composer=no`. |
| `unknown` | `source=unknown`, or `native=unknown`/`composer=unknown` without a positive source. |

Rules:

- Item root first; walk ancestors to `ABSPATH`; stop at the plugin/theme root
  when the item is outside `ABSPATH`; bound the depth. Resolve symlinks with
  `realpath`. `.git` may be a file (worktree) or a directory; `.svn`, `.hg`,
  and `.bzr` must be directories.
- `WP_Automatic_Updater::is_vcs_checkout()` is a cross-check, not the sole
  authority. If it says yes and the direct scan finds no marker, return
  `source=unknown`, `manager=external`, `scope=unknown`; do not invent a path.
- Composer: discover the project root by walking up from `ABSPATH`,
  `WP_CONTENT_DIR`, and `WP_PLUGIN_DIR`; honor `config.vendor-dir`; parse
  `installed.json` (Composer 2 `packages` or Composer 1 flat), fall back to
  `installed.php` only when JSON is absent; accept `wordpress-plugin` and
  `wordpress-theme`; resolve `install-path` relative to `vendor/composer` and
  require a `realpath` match to the item root. `composer.lock` corroborates;
  it is never the sole proof. A bundled `vendor/` or a root-level
  `composer.json` alone is not proof.
- Cache key: item root + detection context, request-scoped. Invalidate on
  `upgrader_process_complete`, `activated_plugin`, `deactivated_plugin`,
  `switch_theme`, `deleted_plugin`, `deleted_theme`, settings save, and
  recheck. A `git pull` is visible only on the next request; do not claim
  otherwise.
- Fail modes:
  - positive/unreadable evidence (`unknown_unreadable`,
    `unknown_marker_escape`, `unknown_missing_detector`) →
    `version_lock_unknown`, `decision_state=blocked`, one row.
  - no evidence at all (`unknown_no_evidence`) → `version_lock_unverified`,
    `decision_state=info`, one advisory row, allow.
  - `updatronix_pro_is_version_controlled` may turn a `yes` into `no`; it must
    not turn `unknown` into `no`.
- One owner: `Updatronix_Version_Control_Policy`. Schedule reads the
  request-scoped `Shield_State::version_control()` map; Queue/Flow call
  `decision()` at plan time. No per-row detector calls.

### 6.3 Decision result

`Updatronix_Version_Control_Policy::decision( $type, $id )` and the general
policy result share one shape:

| Field | Values | Meaning |
|---|---|---|
| `allowed` | bool | Convenience boolean; `true` only for `allow`/`skip`. |
| `decision` | `allow`, `deny`, `defer`, `wait`, `skip` | What the caller may do now. |
| `code` | legacy policy code | `native_vcs`, `version_controlled`, `delay_active`, `php_incompatible`, `wp_incompatible`, `stale_offer`, `dependency_failed`, `license_inactive`, `engine_disabled`, `queue_disabled`, `auto_update_disabled`, `environment_disabled`. |
| `reason_code` | canonical code | §6.7. |
| `message` | string | Human sentence for the row/screen. |
| `next_eligible_at` | int or null | UTC timestamp only when a real promise exists. |
| `policy_id` | string | `version_control`, `wp_compatibility`, `php_compatibility`, `delay`, `pending_offer`, `chain_dependency`, `license`, `engine`, `native_guards`, `abandoned`. |
| `severity` | `info`, `warning`, `error` | UI badge intent. |
| `state` | readiness state | §6.5. |
| `manager`, `scope`, `evidence` | strings | Version Lock metadata, empty otherwise. |
| `active` | bool | Whether the policy ran (Safe Mode/license gate). |
| `native`, `composer`, `bundled` | mixed | Version Lock metadata, empty otherwise. |

Decision semantics:

| Decision | Means | Readiness | Log state |
|---|---|---|---|
| `allow` | May run now. | `offer_eligible` | No row until execution. |
| `deny` | Must not run this pass; a policy, manager, or configuration blocks it. | `offer_blocked`, `locked`, `not_managed`, `not_installed`, `never_ready` | `blocked`. |
| `defer` | May run later at a known time. | `offer_held` | `delayed`. |
| `wait` | Not now; no known time, or waiting for a predecessor. | `waiting_for_predecessor`, `unknown_offer` | `waiting`. |
| `skip` | Not applicable or already done. | `already_latest`, `skipped`, `no_offer` | `skipped` (or no row). |

Precedence: **environment/native guards (precondition) → Version Lock → WP
compatibility → PHP compatibility → Schedule hold → pending offer → Flow
readiness.** The first denial supplies the primary `reason_code`; secondary
reasons go in `context.secondary_reasons`. One item, one primary reason, one
row.

`allowed` is not the only thing callers read. Queue/Flow must use `decision`
and `state`; a boolean-only caller loses the defer/wait/skip distinction.

### 6.4 Pass/run identity

One pass, one id.

| Field | Shape | Notes |
|---|---|---|
| `pass_id` | 32 lowercase hex | Generated at pass start with `bin2hex( random_bytes( 16 ) )`. No separate `run_id`. Legacy run ids in old rows remain readable. |
| `origin` | `automatic`, `flow`, `manual`, `cli`, `external`, `health`, `system` | The decision source. A Flow-ordered item uses `flow`; a native pass item not in a chain uses `automatic`. A manual update uses `manual`; a WP-CLI update uses `cli`. Legacy `queue` remains readable but is never written. |
| `started_at` | UTC `Y-m-d H:i:s` | `current_time( 'mysql', true )`. |
| `finished_at` | UTC `Y-m-d H:i:s` | Set at pass end; also in the summary row. |
| `next_check_at` | int | The next `wp_next_scheduled( 'wp_version_check' )`, or `time() + HOUR_IN_SECONDS`. |
| `order` | array of keys | 1–50 nodes, stable. |
| `nodes` | map key → node | Node shape below. |
| `done` | array of keys | Terminal successes/skips for this pass. |
| `last_item` | key or empty | The last selected item; used for stale recovery. |
| `lock_waits` | int | Consecutive native-lock waits; capped at 3. |
| `last_heartbeat` | int | Updated on every gate selection and finalize. |
| `flow_snapshot` | array | Chain/sequence occurrences at pass start; empty until Flow Step 5. |
| `counts` | map | total, done, blocked, waiting, delayed, skipped, failed, uncertain, canceled. Overflow and translations are context notes, not counts. |

Node shape:

```php
array(
    'type'         => 'plugin',
    'id'           => 'a/a.php',
    'target'       => '1.1.0',
    'state'        => 'offer_eligible',
    'predecessors' => array( 'plugin:gateway/gateway.php' ),
    'waiting_for'  => '',
    'eligible_at'  => 0,
    'attempts'     => 0,
    'reason'       => '',
    'reason_code'  => '',
    'evidence'     => '',
    'manager'      => '',
    'scope'        => '',
)
```

Rules:

- Option name `updatronix_pro_update_pass`; `update_option( ..., false )`;
  non-autoloaded; main site. `MAX_NODES=50`; `MAX_ATTEMPTS=2` as a safety
  bound; normal flow attempts once and skips a failed node for the pass.
- `attempts` is the attempted set; do not store a second map.
- Validate `pass_id` (32 hex), order length, every order key has a node, every
  state is in §6.5, no unknown top-level keys, scalar targets, bounded reasons
  (200 characters).
- The pass starts lazily at the first gate call, after `run()` refreshes the
  transients. Do not build it at `wp_maybe_auto_update` priority 1.
- `is_expired()`: no heartbeat for 15 minutes and `next_check_at <= time()`,
  or the next scheduled check has arrived.
- Stale recovery: read `last_item`; target installed → `succeeded`; the
  from-version installed → `failed` and retry next pass; neither →
  `uncertain` with one row. Create a new pass at the next check; do not resume
  the old order.
- Delete the option at pass end, on deactivation, and on uninstall.
- `updatronix_pro_last_pass` is a one-entry cache for the Flow page. It is not
  the audit source; the `system`/`pass` log row is. Delete it on deactivation
  and uninstall.
- `updatronix_pro_update_pass_executing` equals the current `pass_id` while a
  pass is active; unset on `shutdown`. `Updatronix_Update_Executor::is_executing()`
  is a compatibility wrapper around this flag. There is no second lock.
- No `run_id`; no `updatronix_pro_update_runs` option.

### 6.5 State machine

#### Layer 1 — readiness states (18)

Owned by Flow/Queue. “Terminal for this pass” means the pass does not try
again; a later pass re-evaluates.

| State | Meaning | Terminal for this pass? |
|---|---|---|
| `no_offer` | No pending offer in the native transient. A resolver input, not a decision. | No |
| `already_latest` | Installed equals the latest known version, with fresh `checked` evidence. | Yes (done) |
| `unknown_offer` | No offer, but the data is stale, absent, unreadable, or the item is absent from `checked`. | No (wait, warn) |
| `offer_pending` | A newer version is offered; eligibility is not yet established. | No |
| `offer_eligible` | Offer pending, policies allow, hold expired, every incoming predecessor done. | No (may execute) |
| `offer_held` | Inside the Free soak window. | No (wait) |
| `offer_blocked` | A blocking policy denies (compatibility, environment, native guard). | No (block this pass) |
| `locked` | Version Lock manages the item. | No (wait for manager) |
| `not_managed` | The item is not opted into native automatic updates. | No (wait or block) |
| `not_installed` | The item root no longer resolves. | Never |
| `never_ready` | The item cannot update automatically (forced off, no manager path, permanent block). | Never |
| `waiting_for_predecessor` | The item is resolved, but at least one incoming predecessor is not done. | No |
| `running` | Selected and executing. | No |
| `succeeded` | The target version is installed. | Yes |
| `failed` | The attempt failed; the installed version is unchanged. | Yes for this pass |
| `uncertain` | Files changed; installed is neither source nor target. | Yes; manual review |
| `skipped` | Done or not applicable; valid only with `already_latest` or not in the pass. | Yes |
| `canceled` | Chain disabled/deleted or pass abandoned. Not for a transient predecessor. | Yes |

#### Layer 2 — display states (18)

Owned by Schedule. Each state maps to one sentence; no state falls through to a
native countdown.

| State | Display kind | English sentence |
|---|---|---|
| `network` | `none` | “This site uses the network update schedule. Manage it in Network Admin.” |
| `wp_cron_disabled` | `cron` | “WP-Cron spawning is disabled. A real cron job must call `wp-cron.php`; the next automatic install time is unknown.” |
| `not_scheduled` | `cron` | “Automatic update not scheduled. There may be a problem with WP-Cron.” |
| `overdue` | `cron` | “The next check is overdue by {X}. WP-Cron may be stuck; the next automatic install time is unknown.” |
| `locked` | `managed` | “Managed via {manager}. Update through your manager.” |
| `blocked` | `none` | “Blocked by {policy}. No automatic install time.” |
| `not_managed` | `none` | “Automatic updates are off for this item.” |
| `no_offer` | `none` | “No automatic update pending.” |
| `unknown_offer` | `none` | “Update data is stale or unknown. The next automatic decision is unknown.” |
| `waiting_for_predecessor` | `waiting` | “Waiting for {A}. No automatic install time yet.” |
| `failed` | `none` | “The last automatic attempt failed. The next pass re-evaluates.” |
| `uncertain` | `none` | “The last attempt needs review. No automatic install time.” |
| `not_observed` | `not_observed` | “The hold starts when the next check first sees this release. Next check: {date}. The earliest install is not known yet.” |
| `held` | `earliest` | “Earliest automatic install: {eligible_at} or later ({timezone}). Hold ends {hold_until}.” |
| `queued` | `earliest` | “Eligible at {date}; queued after {N} items in the current pass. It may run later.” |
| `eligible` | `exact` | “Next automatic install: {date} ({timezone}).” |
| `running` | `running` | “Updating now.” |
| `unknown` | `none` | “The next automatic update time is unknown.” |

#### Layer 3 — log decision states (9)

Owned by Logger.

| State | Meaning | Badge intent |
|---|---|---|
| `done` | Target installed or action succeeded. | stable |
| `blocked` | A policy, configuration, or predecessor prevents the attempt this pass. | high |
| `waiting` | Eligible in principle, not now. | informational |
| `delayed` | A soak window holds the offer until a known time. | informational |
| `skipped` | Not attempted and does not need to be. | draft |
| `failed` | The attempt failed. | high |
| `uncertain` | Files changed; review needed. | high |
| `canceled` | Chain, license, or pass stopped the item. | draft |
| `info` | A neutral observation, not an outcome. | none |

#### Mapping table

| Readiness | Display | Log decision |
|---|---|---|
| `no_offer` | `no_offer` | `skipped`/`no_pending_offer` only when a predecessor waits or in the pass summary. |
| `already_latest` | `no_offer` | `skipped`/`already_latest` only when a predecessor waits or in the pass summary. |
| `unknown_offer` | `unknown_offer` | `waiting`/`unknown_offer`. |
| `offer_pending` | `eligible`, `held`, `queued`, or `not_observed` after the hold rules | No row until a transition. |
| `offer_eligible` | `eligible` or `queued` | No row until execution; then `done`/`updated`. |
| `offer_held` | `held` | `delayed`/`schedule_hold`. |
| `offer_blocked` | `blocked` | `blocked`/the policy reason. |
| `locked` | `locked` | `blocked`/`version_locked_*`. |
| `not_managed` | `not_managed` | `blocked`/`not_managed` when a pass or predecessor needs it; otherwise no row. |
| `not_installed` | `blocked` | `blocked`/`not_installed`. |
| `never_ready` | `blocked` | `blocked`/`never_ready`. |
| `waiting_for_predecessor` | `waiting_for_predecessor`, or `blocked` when `blocking=true` | `waiting` or `blocked` with `flow_predecessor_*`. |
| `running` | `running` | No row until finalize. |
| `succeeded` | `no_offer` or `eligible` if a new offer exists | `done`/`updated` (native row). |
| `failed` | `failed` | `failed`/`update_failed`. |
| `uncertain` | `uncertain` | `uncertain`/`partial_state`. |
| `skipped` | `no_offer` | `skipped`/the skip reason. |
| `canceled` | `blocked` | `canceled`/`flow_chain_canceled` or the canceling reason. |

#### Transitions

| From | Event | To | Evidence |
|---|---|---|---|
| `no_offer` | `checked[$id] === installed`, data fresh | `already_latest` | `checked`, `last_checked` |
| `no_offer` | Data stale, missing, or item absent from `checked` | `unknown_offer` | `last_checked`, `checked` |
| `no_offer` | A response entry appears | `offer_pending` | `response[$id]` |
| `offer_pending` | The delay policy defers | `offer_held` | Ledger `first_seen`, hold |
| `offer_held` | `now >= hold_until` and policies allow | `offer_eligible` | Ledger, policy result |
| `offer_pending` | Target differs from the stored target | `offer_pending` (new target) | `response`, installed |
| `offer_pending` | First blocking policy denies | `offer_blocked` or `locked` | Policy result |
| `offer_blocked` | The policy passes on a later evaluation | `offer_pending` | Policy result |
| `locked` | The manager installs a version | `already_latest` or `offer_pending` | Installed, response |
| `offer_pending` | A predecessor is not done | `waiting_for_predecessor` | Incoming edges |
| `waiting_for_predecessor` | Every predecessor done, hold expired, policies allow | `offer_eligible` | Edges, ledger, policy |
| `offer_eligible` | Re-evaluation sees a hold or denial | `offer_held` or `offer_blocked` | Ledger, policy |
| `offer_eligible` | The gate selects the item | `running` | Pass state |
| `running` | Installed equals the target | `succeeded` | Installed |
| `running` | Installed equals the from-version | `failed` | Installed, result |
| `running` | Installed is neither | `uncertain` | Installed |
| `running` | The item root disappears | `not_installed` | Catalogue |
| `failed` | A later pass re-evaluates with an offer | `offer_pending` | Response |
| Any | The item root disappears | `not_installed` | Catalogue |
| Any | The chain is disabled or deleted | `canceled` | Chain snapshot |
| Any | The pass is abandoned | `canceled` for pending nodes | Stale recovery |

`blocked_by_predecessor` is a display/UI label, not a state. Persist
`waiting_for_predecessor` with `blocking=true` and a `flow_predecessor_*`
reason. The Flow UI says “Blocked” when `blocking=true`, “Waiting” otherwise.

### 6.6 Next-eligible calculation

One function:

```php
Updatronix_Schedule_State::calculate( array $input ): array
```

#### Inputs

| Input | Type | Source |
|---|---|---|
| `type`, `id` | string | `Item_State`. |
| `installed_version` | string | `Item_State`. |
| `target_version` | string | `Item_State`. |
| `offer_state` | `none`, `pending`, `stale`, `unknown` | `Item_State`. |
| `auto_update` | `on`, `off`, `forced_off`, `forced_on` | `Item_State`. |
| `recurrence` | `hourly`, `twicedaily`, `daily`, `weekly`, `''` | Free schedule. |
| `preferred_time` | `HH:MM` | Free schedule; ignored for hourly/default. |
| `hold_enabled`, `hold_days` | bool, int `0..365` | Free schedule; `0` = off. |
| `now` | int | UTC. |
| `next_check` | int or false | Main-site `wp_next_scheduled( 'wp_version_check' )`. |
| `cron_disabled` | bool | `DISABLE_WP_CRON`. |
| `first_seen` | int or null | Read-only ledger peek. |
| `policy` | array | Pro `Schedule_Facts`: `state=none/blocked`, `reason_code`, `message`, `policy_id`. |
| `lock` | array | Pro `Schedule_Facts`: `state=none/locked/unknown`, `manager`, `scope`, `reason`. |
| `flow` | array | Pro `Schedule_Facts`: `state=none/waiting/blocked/locked/failed/done`, `waiting_for`, `reason`, `eligible_at`. |
| `queue` | array | Pro `Schedule_Facts`: `state=none/active`, `node_state`, `position`, `total`, `pass_id`, `started_at`, `next_follow_up`. |
| `timezone` | `DateTimeZone` | `wp_timezone()`. |
| `date_format`, `time_format` | string | Site options; fallback `Y-m-d`, `H:i`. |
| `is_subsite` | bool | Multisite context. |

#### Outputs

`state`, `next_check`, `first_seen`, `hold_until`, `eligible_at`,
`execution_at`, `queue_position`, `waiting_for`, `manager`, `display_kind`,
`reason_code`, `details_url`, plus the localized `display` sentence.

#### Precedence

1. `wp_cron_disabled`.
2. `not_scheduled`.
3. `overdue`.
4. `locked`.
5. `blocked` (policy).
6. `not_managed`.
7. `no_offer` / `unknown_offer`.
8. `waiting_for_predecessor`.
9. `failed` / `uncertain`.
10. `not_observed`.
11. `held`.
12. `queued`.
13. `running`.
14. `eligible`.
15. `unknown`.

`network` wins over `not_scheduled` on a Multisite subsite with no local
timestamp. `running` is used only when the pass node is the current item and
the pass is fresh.

#### Formulas

```text
DAY = 86400
interval: hourly 3600, twicedaily 43200, daily 86400, weekly 604800,
          default = the current wp_version_check interval, or 43200.

hold_until = (hold_enabled && hold_days > 0 && first_seen !== null)
    ? first_seen + hold_days * DAY
    : null

first_check_at_or_after(floor, next_check, interval, now):
    if next_check === false: return null
    if next_check < now: return null          # overdue; actual run unknown
    t = next_check
    while t < floor: t += interval
    return t

eligible_at:
    if offer_state !== 'pending': null
    else if auto_update !== 'on': null
    else if hold is off: next_check if future, else null
    else if first_seen === null: null
    else: first_check_at_or_after(hold_until, next_check, interval, now)

execution_at:
    state === 'eligible' ? eligible_at : null
    # queue position is a qualifier, not a timestamp
    # flow waiting/locked/blocked/failed -> null
```

`queue_position` is the number of unattempted items before this item in the
pass order. It is a count, not a duration. Never compute
`eligible_at + position * estimate`.

#### `first_seen` write point

- Pro pass: `Updatronix_Update_Pass_Order` calls
  `Updatronix_AutoUpdateDelay::observe_offer( $type, $offer )` once per offer
  in the refreshed transients, not only the first 50, at plan time and before
  policy evaluation. The method is idempotent: it sets `first_seen` only when
  the row is absent, and writes nothing else. It is added in Step 3.
- Free-only: the delay gate remains the writer.
- The display calls `peek_first_seen()`; it never writes, logs, or prunes.
- The ledger hash is the same `sha256` input the gate uses:
  `type|lower(id)|target` for plugins/themes, `core|current|target` for core,
  and the translation tuple for translations.

#### Timezone, DST, and fallbacks

- Store UTC integers. Display with `wp_timezone()` and the site
  `date_format`/`time_format`; use `wp_date()`, never `date()` with the
  default timezone.
- Use the actual `wp_next_scheduled` timestamp. After a DST transition, show
  the actual local time and a note: “The next check is at 02:00 because of the
  daylight-saving shift. Save the schedule to re-anchor it.” Do not recompute
  a fake 01:00.
- `next_check === false` → `not_scheduled`; no timestamp.
- `next_check < now` → `overdue`; no future timestamp.
- `first_seen === null` with the hold on → `not_observed`; show the next check,
  not a hold expiry.
- `first_seen > now + 300` → ignore the row; `not_observed`; one warning.
- Empty `date_format`/`time_format` → `Y-m-d H:i`; empty
  `wp_timezone_string()` → `UTC`.
- Missing `lock`/`policy` facts for an installed item → `unknown`, not
  `eligible`, when the Pro filter is active; when Pro is inactive, Free shows
  its own schedule state.
- Missing `flow`/`queue` facts → `none` (the display is the item state).
- `next_eligible_at` in the log equals `eligible_at` only when a real promise
  exists; otherwise `NULL`. A waiting, locked, blocked, failed, uncertain,
  no-offer, not-observed, or cron-problem state has no countdown.

### 6.7 Reason taxonomy

One public code per meaning. The UI translates the code; the code is the key.
The label column is the exact US English UI label. No synonyms.

#### Success, skip, and manual

| Code | UI label | Decision state | Action | Status | Writer |
|---|---|---|---|---|---|
| `updated` | “Updated” | `done` | `update` | `success` | Native logger |
| `already_installed` | “Already installed” | `done` | `same_version` | `success` | Native logger |
| `already_latest` | “Already at the latest version” | `skipped` | `update` | `info` | Pass summary; per item only when a predecessor waits |
| `no_pending_offer` | “No update pending” | `skipped` | `update` | `info` | Pass summary; per item only when a predecessor waits |
| `no_longer_offered` | “Update no longer offered” | `skipped` | `update` | `info` | Native logger / pass |
| `manual_update` | “Manual update” | `done` | `update` | `success` | Native logger |
| `external_update` | “External update” | `done` | `update` | `success`/`error` | External log |
| `recovered_after_crash` | “Recovered after an interrupted update” | `done` | `update` | `success` | Pass finalize |
| `health_retry` | “Retry from Site Health” | `info`/`done` | `update` | `info`/`success`/`error` | Health |

#### Version Lock

| Code | UI label | Decision state | Action | Status | Writer |
|---|---|---|---|---|---|
| `version_locked_native` | “Managed via {manager}” | `blocked` | `prevented` | `warning` | Version Lock / native guard |
| `version_locked_composer` | “Managed via Composer” | `blocked` | `prevented` | `warning` | Version Lock |
| `version_lock_unknown` | “Version management could not be verified. The update is blocked.” | `blocked` | `prevented` | `warning` | Version Lock |
| `version_lock_unverified` | “Version management is unverified. The update is allowed.” | `info` | `advisory` | `info` | Version Lock |

#### Shield, environment, and opt-in

| Code | UI label | Decision state | Action | Status | Writer |
|---|---|---|---|---|---|
| `shield_incompatible_wp` | “Requires WordPress {version}” | `blocked` | `incompatible` | `warning` | Shield |
| `shield_incompatible_php` | “Requires PHP {version}” | `blocked` | `incompatible` | `warning` | Shield |
| `shield_disabled_auto_update` | “Automatic updates disabled by Update Shield” | `blocked` | `safe_mode_disabled` | `warning` | Shield |
| `shield_reenabled` | “Automatic updates re-enabled” | `done` | `auto_update_reenabled` | `success` | Shield |
| `shield_policy_block` | “Blocked by Update Shield” | `blocked` | `prevented` | `warning` | Shield |
| `environment_disabled` | “Updates are disabled by the site configuration” | `blocked` | `prevented` | `warning` | Native guards |
| `native_policy_refused` | “WordPress refused the update” | `blocked` | `prevented` | `warning` | Executor / native |
| `not_managed` | “Automatic updates are off for this item” | `blocked` | `prevented` | `warning` | Adapter / pass |
| `not_installed` | “Item is not installed” | `blocked` | `prevented` | `warning` | Pass / Flow |
| `never_ready` | “Item cannot update automatically” | `blocked` | `prevented` | `warning` | Policy / pass |

#### Schedule

| Code | UI label | Decision state | Action | Status | Writer |
|---|---|---|---|---|---|
| `schedule_hold` | “Delayed by the update hold” | `delayed` | `update` | `delayed` | Free delay gate |
| `wp_cron_disabled` | “WP-Cron is disabled” | `info` | `advisory` | `warning` | System / Health |

#### Flow

| Code | UI label | Decision state | Action | Status | Writer |
|---|---|---|---|---|---|
| `flow_waiting_predecessor` | “Waiting for {predecessor}” | `waiting` | `update` | `info` | Flow planner |
| `flow_predecessor_blocked` | “Predecessor blocked” | `blocked` | `prevented` | `warning` | Flow planner |
| `flow_predecessor_locked` | “Predecessor is managed via {manager}” | `blocked` | `prevented` | `warning` | Flow planner |
| `flow_predecessor_failed` | “Predecessor failed; the next pass re-evaluates” | `waiting` | `update` | `info` | Flow planner |
| `flow_predecessor_uncertain` | “Predecessor needs review” | `blocked` | `prevented` | `warning` | Flow planner |
| `flow_predecessor_unknown` | “Predecessor update data is stale” | `waiting` | `update` | `info` | Flow planner |
| `flow_predecessor_not_managed` | “Predecessor is not opted into automatic updates” | `blocked` | `prevented` | `warning` | Flow planner |
| `flow_predecessor_not_installed` | “Predecessor is no longer installed” | `blocked` | `prevented` | `warning` | Flow planner |
| `flow_predecessor_never_ready` | “Predecessor cannot update automatically” | `blocked` | `prevented` | `warning` | Flow planner |
| `flow_chain_invalid` | “Flow chain is invalid” | `blocked` | `prevented` | `warning` | Flow planner / system |
| `flow_chain_canceled` | “Flow chain was canceled” | `canceled` | `disabled` | `cancelled` | Flow runner / pass |
| `flow_offer_stale` | “Update offer changed” | `skipped`/`waiting` | `update` | `info` | Flow gate |

#### Pass, system, and failure

| Code | UI label | Decision state | Action | Status | Writer |
|---|---|---|---|---|---|
| `pass_summary` | “Automatic update pass” | `done`/`blocked`/`failed`/`canceled` | `pass` | `success`/`warning`/`error`/`cancelled` | Pass log |
| `native_lock_busy` | “Another update is running” | `waiting` | `update` | `info` | Gate |
| `license_inactive` | “License inactive” | `canceled` | `disabled` | `cancelled` | Engine / pass |
| `engine_disabled` | “Update engine unavailable” | `blocked` | `prevented` | `warning` | Engine |
| `update_failed` | “Update failed” | `failed` | `failed` | `error` | Native logger / pass |
| `partial_state` | “Update needs review” | `uncertain` | `failed` | `warning` | Executor / pass |
| `abandoned_advisory` | “No release for a long time” | `info` | `advisory` | `warning` | Abandoned detector |
| `unknown` | “Unknown” | `info` | `update` | `info` | Fallback |

#### Legacy codes and aliases

These values remain readable. New rows do not use them as the public reason.

| Legacy code | Canonical reason |
|---|---|
| `native_vcs` | `version_locked_native` (context `detector=updatronix`) |
| `version_controlled` | `version_locked_composer` |
| `delay_active` | `schedule_hold` |
| `php_incompatible` | `shield_incompatible_php` |
| `wp_incompatible` | `shield_incompatible_wp` |
| `stale_offer` | `flow_offer_stale` |
| `dependency_failed` | `flow_predecessor_failed` |
| `auto_update_disabled` | `not_managed` |
| `upstream_blocked` | `flow_predecessor_blocked` |
| `upstream_failed` | `flow_predecessor_failed` |
| `upstream_uncertain` | `flow_predecessor_uncertain` |
| `upstream_canceled` | `flow_chain_canceled` |
| `before_execute_veto` | `native_policy_refused` |
| `upgrader_returned_false`, `upgrader_returned_null` | `update_failed` (context keeps the code) |
| `unknown_result` | `unknown` |
| `updated`, `installed_target` | `updated` |
| `auto_update_reenabled` | `shield_reenabled` (the action type stays `auto_update_reenabled`) |
| `recovered_after_crash` | `recovered_after_crash` |
| `attempts_exhausted` | `queue_attempts_exhausted` (legacy queue) |
| `recovered_lease_expired` | `queue_lease_expired` (legacy queue) |
| `queue_waiting_turn`, `queue_lock_busy`, `queue_retry_backoff`, `queue_attempts_exhausted`, `queue_lease_expired`, `queue_disabled` | Legacy queue only; the pass uses `native_lock_busy`, `pass_summary`, and the Flow reasons |
| `canceled_by_user` | Legacy; no active UI |

`version_lock_unknown` and `version_lock_unverified` are not synonyms:
`unknown` blocks; `unverified` allows with an advisory. The UI uses the exact
labels above.

### 6.8 Log write points and dedupe keys

One event, one writer, one row. “One” means one per item/target/transition;
the previous-state check suppresses a repeat with no change.

| Event | Writer | Rows | Dedupe |
|---|---|---|---|
| Native actual update (plugin/theme/core/translation) | Free native logger, via `log_event()` | One per disk change. | v2 key with `transition=updated`, `reason_code=updated`, `pass_id` when the pass is active, else `request_id`. Pro enriches at insert through `updatronix_log_context`; `attach_context()` is the fallback. |
| Manual admin update | Free native logger | One per manual attempt. | `origin=manual`, `request_id`, `transition=updated`, `reason=manual_update`. Two requests → two rows. |
| WP-CLI update | Free native logger | One per CLI attempt. | `origin=cli`, `request_id`, `reason=manual_update`. |
| Version Lock block | Version Lock via `Decision_Log::record_block()` | One per item/target/policy/pass. | `reason_code=version_locked_*`, `decision_state=blocked`, previous-state check. Safe Mode and Version Lock do not write two rows. |
| Shield compatibility/environment block | Shield enforcement via `record_block()` | One per item/target/policy/pass. | `reason_code=shield_*`/`environment_disabled`, `decision_state=blocked`. |
| Schedule hold | Free delay gate, via `log_event()` from the Schedule step | One per offer/target/state start. | `reason_code=schedule_hold`, `decision_state=delayed`, `next_eligible_at` from the calculator; a changed target or promise is a new row. No per-tick row. |
| Flow wait starts | Flow/pass via `record_wait()` | One when the wait starts; one when it becomes permanent. | `decision_state=waiting`/`blocked`, `reason_code=flow_*`, predecessor; the comparator ignores `pass_id` for a repeated wait. |
| Flow canceled node | Flow/pass via `record_cancel()` | One per canceled node/pass. | `decision_state=canceled`, `reason_code=flow_chain_canceled` or the canceling reason; chain and predecessor in context. |
| Pass item outcome for skipped/held/locked/uncertain | Pass log via `Decision_Log` | One per state start, not per tick. | The pass summary counts repeats; `record_item()` writes only when no policy row exists. |
| Pass summary | Pass log via `record_pass()` | One `system`/`pass` row per pass. | `log_type=system`, `action_type=pass`, `item_slug=pass:{pass_id}`, `reason_code=pass_summary`; counts updated with `update_summary()`, not a second row. |
| Advisory (unverified detection, WP-Cron disabled, abandoned) | Version Lock / Health / abandoned detector | One per state start. | `decision_state=info`, `action_type=advisory`; no per-tick row. |
| External update | External log | One per external call. | `origin=external`, `reason_code=external_update`, source in context. |
| Health retry | Health | One per retry. | `origin=health`, `reason_code=health_retry`, original log id in context. |
| Already-latest item | Pass summary | No per-item row unless a predecessor waits. | The summary counts it; a per-item row uses `decision_state=skipped`, `reason_code=already_latest`. |
| Translation update | Free native logger | One per disk change. | `log_type=translation`, `origin=automatic`, no `pass_id`, `reason=updated`; not a pass node, not in the nine pass counts. |

#### Event key

```text
v2|sha256(
  site_id | origin | pass_id_or_request_id |
  log_type | item_slug | target_version |
  transition | reason_code | attempt |
  predecessor | decision_stage | network |
  next_eligible_at
)
```

- `transition` is `updated`, `failed`, `blocked`, `waiting`, `delayed`,
  `skipped`, `canceled`, or `pass`; it is not the raw job status.
- `pass_id_or_request_id`: the pass id when present; a per-request id for
  manual, CLI, external, health, and system rows.
- `reason_code` replaces the message hash. A reworded message must not create a
  new key; a changed reason must.
- `next_eligible_at` is included for `waiting`/`delayed` rows. The calculator
  returns a stable value; a changed promise is a new row.
- The key is at most 191 characters. Old keys remain unique; new keys use
  `v2|`.

#### Previous-state comparator

Before insert, compare the latest row for the same
`(site_id, log_type, item_slug, target_version)`:

- Compare `decision_state`, `reason_code`, `predecessor`, `target_version`,
  `attempt`, and `next_eligible_at`.
- Ignore `pass_id` for a repeated wait/block, so a later pass does not create a
  per-tick row. The pass summary counts the repeat.
- A changed state, reason, target, predecessor, attempt, or promise writes a
  new row.
- A manual row uses the request id; the comparator does not collapse two
  manual attempts.

#### Hooks and caps

- `updatronix_log_context` (array, item type/id/target) is applied before the
  native insert. Pro supplies pass, chain, predecessor, attempt, origin, stage,
  network, and next eligible. The native actual-update row is complete on the
  first write.
- `updatronix_after_log` keeps the old 12 keys and adds `site_id`, `user_id`,
  `performed_by`, `target_version`, `reason_code`, `decision_state`, `origin`,
  `decision_stage`, `pass_id`, `chain_id`, `predecessor`,
  `next_eligible_at`, `attempt`, `network`, and `context`. Insert-only.
- `updatronix_log_updated` fires only when `attach_context()` enriches an
  existing row. It does not re-fire `updatronix_after_log`.
- Per-pass cap: 1 summary + 50 outcome/decision rows + 50 wait-start rows. A
  truncation writes a `pass_summary` warning with `context.truncated=true`.
- System/pass rows use `performed_as=automatic`; `origin=system` distinguishes
  them. The `performed_as` allowlist stays `manual`, `automatic`, `upload`.
- Stages: `plan`, `policy`, `gate`, `execute`, `finalize`, `observe`. No other
  value is written by a new row.
- No row per tick; no row for every already-latest item; no fake item rows;
  no secrets, paths, package URLs, emails, tokens, or recipient addresses.
- Context is allowlisted, redacted, depth ≤3, list ≤50, string ≤1024 bytes,
  total ≤8192 bytes. `context_truncated=true` and an omitted count when capped.

### 6.9 Native gate

#### Filter and hook order

| Priority | Owner | Purpose | Writes a row? |
|---:|---|---|---|
| 19 | Pro ordered adapter (Step 1) | Environment/native guards → Version Lock → WP compatibility → PHP compatibility. Returns `false` at the first denial. | Yes, one block row. |
| 21 | Free delay filter | Schedule hold; respects an incoming `false`. | Yes, one delayed row. |
| `PHP_INT_MAX - 5` | Pro Safe Mode adapter | Compatibility fallback for Pro-inactive/legacy paths and Site Health probes. | Only when the ordered adapter did not evaluate the item. |
| `PHP_INT_MAX` | Pro pass gate | Select one item; never a policy evaluation. | Only the pass node state and the pass/item row at finalize. |
| — | `auto_update_translation` | Not gated. | Native logger. |

The gate is registered for `auto_update_plugin`, `auto_update_theme`, and
`auto_update_core` only. It is not registered for translations.

#### One selection per request

1. The first gate call in a request loads the pass or creates it, builds the
   order, resolves readiness, and selects the first schedulable node. A node is
   schedulable only when its state is `offer_eligible` and every incoming
   predecessor is `done`.
2. A static `$selected` records the key. Every later gate call in the same
   request returns `false`, even if the selected item was vetoed.
3. For the selected node:
   - If the incoming `$update` is `true` and the node is still eligible, call
     `mark_attempted()`, set `state=running`, save, and return the incoming
     `$update` (which is `true`, not a literal `true`).
   - If the incoming `$update` is `false` or the node is no longer eligible,
     call `mark_attempted()`, set the node state from the pass (`held`,
     `locked`, `offer_blocked`, `not_managed`, or `failed`), save, and return
     the incoming value.
4. The gate **never returns `true`**. It has no path that invents permission.
5. The gate does not re-run the policy chain. Free delay, Shield, and Version
   Lock already ran at priorities 19–21. The gate logs the first denial once,
   by reading the pass node state.

#### Other items are denied, not failed

An item that is not selected is `false` at the gate. It is not marked `failed`,
does not get a failure row, and does not get a native failure email. Its pass
node keeps `offer_eligible`, `waiting_for_predecessor`, `offer_held`,
`locked`, `offer_blocked`, `not_managed`, `not_installed`, `never_ready`,
`failed`, or `uncertain` as resolved. The pass summary counts it. The next
follow-up or the next scheduled check re-evaluates it.

#### Follow-up, lock, and expiry

- At shutdown after a selected item, read the installed version and the offer.
  Map the outcome: target installed → `succeeded`; offer gone and target not
  installed → `skipped`; unchanged and offer remains → `failed`; item missing
  → `skipped`/`not_installed`; neither source nor target → `uncertain`.
- Write one item outcome row, add the key to `done`, clear `last_item`.
- If a pass is active but no selected item was recorded (the offer disappeared
  before its gate call), re-evaluate the order once; mark that node
  `skipped`/`not_installed` and schedule a follow-up if a schedulable node
  remains; otherwise end the pass.
- If a schedulable node remains, schedule **one** single event
  `updatronix_pro_update_pass_follow_up` at `time() + 1`; its callback calls
  `do_action( 'wp_maybe_auto_update' )`. It never calls `run()` or `update()`
  directly.
- Do not schedule a follow-up when no schedulable node remains, when the pass
  is expired, or when the bypass is off.
- Before the follow-up, read the native `auto_updater.lock`. If it is present
  and younger than its release timeout, schedule the follow-up in five minutes
  and increment `lock_waits`. After three waits, mark the node `skipped` with
  `native_lock_busy` and advance. Do not mark it `failed`.
- No second lock, no lease, no busy loop. The native lock serializes two cron
  requests.
- Stale pass: no heartbeat for 15 minutes and `next_check_at <= time()`, or the
  next scheduled check arrived. Replace it; do not resume the old order.
  Reconcile `last_item` first.
- Emergency bypass: `updatronix_pro_update_pass_enabled` (filter/constant),
  default true after cutover. When false, the gate and follow-up do nothing and
  native runs the whole loop.
- Fail-open only when the pass cannot be created (no `$wpdb`/option failure):
  return the incoming value for all items, write one error row, and do not
  follow up. This is a deliberate safety choice; it can run more than one item
  in that request. Record it in the pass log.

#### Core and translations

- Core is a normal gate item. It is `not_managed` when major auto-updates are
  off or `WP_AUTO_UPDATE_CORE === false`. There is no security-release jump.
- Core’s native email is separate. The pass does not send a second email.
- Translations are not gated. Native `run()` processes them last in the same
  invocation, even when the gate selected a plugin. They are not pass nodes,
  have no per-item email, and are not in the nine pass counts.

#### Gate sequence

```text
wp_maybe_auto_update
  │
  ▼
WP_Automatic_Updater::run()  ── refresh transients ──► first auto_update_* call
  │                                                   │
  │                                                   ▼
  │                                        ordered adapter (19): environment
  │                                        → Version Lock → compatibility
  │                                                   │
  │                                        Free delay (21)
  │                                                   │
  │                                        Safe Mode fallback (max-5)
  │                                                   │
  │                                                   ▼
  │                                        pass gate (max): select one item
  │                                        others: false, not failed
  ▼                                                   │
native update() for one item ◄────────────────────────┘
  │
  ▼
native email, lock, rollback, completion
  │
  ▼
shutdown: read result → pass state → one item row
  │
  ├── schedulable remains → follow-up at +1s → wp_maybe_auto_update
  └── none → pass summary row + last-pass cache → delete pass
```

### 6.10 Manual-update boundary

| Action | Version Lock | Flow ordering | Pass | Log |
|---|---|---|---|---|
| Automatic pass | Denies a managed item at 19; one row. | Orders the item if it is in a chain; never forces the opt-in. | Selects one item; exempts its own write via the pass flag. | `origin=automatic` or `flow`; one row. |
| Native admin single update | Blocks only when `version_lock_manual_updates` is on; otherwise allowed with the automatic-only notice. | Does not block. | Outside the gate. | `origin=manual`, `reason=manual_update` or `version_locked_*`. |
| Bulk update | Same as single; locked items are skipped, siblings update. | Does not block. | Outside the gate. | `update_context=bulk`; one row per item. |
| Update-core | Same as single; row notice and checkbox removal in full lock. | Does not block. | Outside the gate. | `origin=manual`. |
| Direct `update.php` URL | Same as single. | Does not block. | Outside the gate. | `origin=manual`. |
| Third-party REST route that calls the upgrader | Same as single when the manual toggle is on; `upgrader_pre_install` covers it. | Does not block. | Outside the gate. | `origin=manual`. |
| WP-CLI | **Documented escape hatch**: do not block. | Does not block. | Outside the gate. | `origin=cli`, `reason=manual_update`; include the manager in context when known. |
| Fresh install | Never block; `action=install` or no matching `hook_extra`. | Not applicable. | Outside the gate. | Normal install row. |
| External update | Not applicable. | Not applicable. | Not applicable. | `origin=external`, `reason=external_update`. |

Rules:

- The manual guard runs on `upgrader_pre_install` only when `safe_mode`,
  `version_control_lock`, and `version_lock_manual_updates` are on.
- It covers plugins, themes, and core. For core, it checks
  `hook_extra['type']='core'` and an `ABSPATH` marker (or a positive core
  detection result).
- It allows when `Updatronix_Update_Executor::is_executing()` is true or
  `$GLOBALS['updatronix_pro_update_pass_executing']` equals the current
  `pass_id`. The wrapper reads the flag; there is no separate execution state.
- It returns `WP_Error( 'updatronix_version_locked', $manager_message )` and
  writes one block row. It does not write the Shield ledger.
- Flow ordering never blocks a manual update. A manual update to the target
  makes a Flow node `done`; a manual update to another version is logged and
  the offer stays pending. Version Lock remains the manual lock.
- The native auto-update toggle is locked whenever Version Lock is on,
  independent of the manual toggle. The stored `auto_update_*` option is left
  unchanged. The Free REST toggle returns 403; the Pro option-write guard
  strips locked items from native AJAX writes.

### 6.11 Native UI composition

C11 is the authoritative rule. Implementation summary:

| Surface | Version Lock | Schedule | Combined behavior |
|---|---|---|---|
| Plugin row | Filter at 90; lock label; notice. | Filter at 110; replace `.auto-update-time` text. | Locked row shows the manager notice; unlocked row shows the time. No duplicate notice. |
| Network theme row | Filter at 90. | Filter at 110. | Same. |
| Single-site theme card | `wp_prepare_themes_for_js` + template locked branch at 90. | Template time replacement at 110. | Locked branch wins; Schedule does not inject a time. |
| update-core | Preamble notice at 10; checkbox removal in full lock. | Preamble summary at 20; row text replacement. | Both `<p>` elements; no replacement; full lock removes the checkbox. |
| JS | `version-lock-augment.js` first. | `native-update-time.js` second. | Schedule no-ops on `data-updatronix-locked`; both are idempotent; `textContent` only. |
| WP-Cron | — | One-line hint. | Only when `DISABLE_WP_CRON`; no banner. |

### 6.12 Storage and lifecycle

| Store | Owner | Autoload | Lifecycle |
|---|---|---|---|
| `{prefix}updatronix_logs` | Free | n/a | Additive columns and five indexes. Retained by the existing retention job; newest blocking row pinned, cap 500. Dropped on uninstall as today. |
| `updatronix_log_db_version` | Free | Yes (small) | Written after a successful `dbDelta`; compared by `maybe_upgrade()`. |
| `updatronix_log_decision_layer_enabled` | Free | Yes | Default true; rollback switch; when false, `log_event()` delegates to the old shape. |
| `updatronix_settings` | Free | Yes | Existing JSON; schedule/logging/notification settings. Hold `0..365`; `time=HH:MM`; old values valid. |
| `updatronix_update_logger_state` | Free | No | Existing request/pending state for the v2 key. |
| Delay ledger (Free plugin option) | Free | No | `observe_offer()` writes `first_seen`; the gate prunes; `peek_first_seen()` is read-only; cap unchanged. |
| `updatronix_pro_update_pass` | Pro | No | Active pass only; max 50 nodes; deleted at pass end, deactivation, uninstall. |
| `updatronix_pro_last_pass` | Pro | No | One-entry cache for the Flow page; deleted at deactivation/uninstall. |
| `updatronix_pro_queue_removed` | Pro | No | Migration flag; retained after the queue removal; deleted on uninstall. |
| `queue_enabled`, `queue_live` | Pro | n/a | Removed. `queue_enabled` removed from the network Shield settings. |
| `version_control_lock`, `version_lock_manual_updates` | Pro | n/a | Inside the network Shield option; preserved/added; removed on uninstall. |
| Flow chains option | Pro | n/a | Network option; unchanged; `core:wordpress` keys. |
| `updatronix_pro_update_runs` | Pro | No | Deleted; not migrated. |
| `{base_prefix}updatronix_pro_update_queue` | Pro | n/a | Dropped once; no rows migrated. |
| `updatronix_pro_queue_rate_*`, queue locks | Pro | n/a | Deleted; no new transient cache. |
| Native `update_plugins`/`update_themes`/`update_core` | WordPress | n/a | Read-only; never mutated by the pass. |
| Native `auto_updater.lock` | WordPress | n/a | Read by the pass; never created by it. |

Cron:

- Free keeps its existing daily retention/cleanup job.
- The pass uses one single event, `updatronix_pro_update_pass_follow_up`, at
  `time()+1`. No recurring Pro event.
- The queue hooks `updatronix_pro_process_queue` and
  `updatronix_pro_process_queue_now` are unscheduled on migration,
  deactivation, and uninstall.
- The Flow `POST /flow/run` cooldown transient is removed in Step 4.

Deactivation and uninstall:

| Step | Deactivation | Uninstall |
|---|---|---|
| Version Lock | No new cleanup; the Shield option stays. | The key is removed with the network Shield option. |
| Logger | No cleanup; the schema stays. | The log table is dropped as today; the schema option and rollback switch are removed. |
| Schedule | No cleanup; settings stay. | The existing Free uninstall removes settings/options. |
| Queue | Delete the pass and last-pass cache; clear the follow-up; keep the migration flag. | Drop the legacy queue table if present; delete the pass, cache, flag, queue options/transients, and cron. |
| Flow | No cleanup; chains stay. | Chains are removed by the existing Pro uninstall. |

### 6.13 Multisite attribution

| Surface | Rule |
|---|---|
| Execution site | `site_id` is the site where the update ran. The pass runs on the main site. |
| Network flag | `network=1` only when the decision applies network-wide: a network-activated plugin/theme, core, a network chain, or a network-scoped policy. A main-site-only plugin is `network=0`. |
| Target site | `context.target_site_id` names the subsite that owns an item when it differs from `site_id`. A subsite-only item in a network chain is `not_installed` on the main site, `network=1`, with the target site. |
| Scope | `context.scope=site/network` explains the decision. |
| Version Lock | Network-wide decision; network admin; main-site engine; Super Admin. A subsite native screen shows the network lock. |
| Flow | Network chains; main-site execution; Super Admin; a subsite-only item is `not_installed`; the editor warns at save. |
| Schedule | Network schedule; main-site cron; network admin; a subsite with no local timestamp shows `network`, never “not scheduled.” |
| Logger | Table on the main site; `site_id` execution site; `network` and `target_site_id` as above; a subsite admin sees only that subsite unless a Super Admin uses the network scope. |
| Uninstall | Main-site table/options/cron plus the existing legacy subsite cleanup. |

**Known limitation:** the one-item-per-invocation pass is registered on the
main site only. Subsite automatic updates remain native and can run more than
one item per invocation. This follows the settled “main-site engine” decision;
it is documented in the release notes and the Schedule/Flow details. A future
per-site pass is out of scope.

### 6.14 Public hooks and filters

| Hook | Signature | Owner | Consumers | Notes |
|---|---|---|---|---|
| `updatronix_pro_is_version_controlled` | `(bool $controlled, string $type, string $id, string $path)` | Version Lock | Integrations | May turn `yes` into `no`; must not turn `unknown` into `no`. |
| `updatronix_auto_update_can_toggle` | `(bool or WP_Error $can, string $type, string $id, bool $enable)` | Version Lock | Free REST toggle, Pro | Returns `WP_Error` for a locked item; Free returns 403. |
| `updatronix_schedule_item_state` | `(array $facts, string $type, string $id, string $target)` | Schedule (`Schedule_Facts`) | Free calculator | Read-only, bounded, request-cached. Returns `lock`, `policy`, `flow`, `queue`. |
| `updatronix_schedule_offer_ttl` | `(int $seconds, string $type, string $id)` | Schedule | Free/Pro | Default 6 hours. One TTL; `updatronix_pro_flow_offers_ttl` is a deprecated alias. |
| `updatronix_native_update_time_enabled` | `(bool $enabled)` | Schedule | Free admin | Emergency off switch; default true. |
| `updatronix_pro_update_pass_order` | `(array $order, array $pass)` | Queue | Pro/integrations | Plan-time order/exclusion; bounded to 50. |
| `updatronix_pro_update_pass_executing` | global/filter `(string $pass_id)` | Queue | Version Lock guard, Safe Mode, Flow | The pass id while active; unset on shutdown. |
| `updatronix_pro_update_pass_enabled` | `(bool $enabled)` | Queue | Pro | Emergency bypass; false → native loop. |
| `updatronix_pro_update_pass_follow_up` | action `(string $pass_id)` | Queue | WP-Cron | One event at +1s; calls `do_action('wp_maybe_auto_update')`. |
| `updatronix_log_context` | `(array $context, string $type, string $id, string $target)` | Logger | Pro | Applied before the native insert. |
| `updatronix_log_updated` | action `(int $log_id, array $fields)` | Logger | Integrations | Fires only on enrichment; not on insert. |
| `updatronix_after_log` | action `(int $log_id, array $data)` | Logger | Integrations | Insert-only; old 12 keys plus additive decision/identity keys. |
| `updatronix_log_action_types` | `(array $types)` | Logger/Security | Pro | Add `pass` and `advisory` in the Free base; keep `auto_update_reenabled`. |
| `updatronix_log_reason_codes`, `updatronix_log_decision_states`, `updatronix_log_origins`, `updatronix_log_stages` | `(array $values)` | Logger | Integrations | Additive allowlists; normalize, unique, drop empty. |
| `updatronix_health_checks` | `(array $checks, bool $main_site)` | Free Health | Pro | Replace the queue checks with the pass checks (OPEN-4). |
| `updatronix_external_update_logged` | action `(int $log_id, array $data)` | External log | Integrations | Unchanged; new fields are additive. |
| `updatronix_filter_should_delay_offer`, `updatronix_skip_auto_update_delay`, `updatronix_auto_update_delay_seconds` | existing | Free delay | Integrations | Unchanged semantics; the gate still respects them. |

---

## 7. Risk register

| Risk | Likelihood | Impact | Detection | Mitigation | Owner decision? |
|---|---|---|---|---|---|
| Native `run()` cannot be limited to one item without false failures | Medium | High | Live fixture: two items → two invocations, one install each; inspect `update_results`; assert no failure row for an unselected item. | Gate never returns `true`; one selection per request; policies write denials; the gate marks the node state, not `failed`; translations are the documented exception. | No |
| Per-item native email is impossible without a custom mail | Low | High | Capture `pre_wp_mail`; two items → two invocations → two emails; a success and a failure each send. | One item per run; no digest; native recipients from Free settings; no Pro `wp_mail()`. If the fixture fails, stop and ask. | No at plan time; if the fixture fails, **OPEN — owner decision** (OPEN-8): accept a grouped email or approve a custom per-item mail |
| Flow waits forever on a locked/uninstalled predecessor | Medium | Medium | Case matrix UF-C03/C04, UF-E13; pass summary first waiting reason; no spin. | A permanent predecessor blocks its descendants for the pass with a reason and one row; the next pass re-evaluates; no cancel, no spin. | No |
| Schedule display cannot be exact for queued items | High | Medium | JS/DOM tests; `queued` shows eligible + position, never a duration; 200-item fixture. | State-first display; position is a count; no `eligible_at + N * estimate`; no exact countdown for waiting/locked/blocked/failed. | No |
| Detector false positives lock normal plugins | Medium | High | Fixture matrix: bundled Composer, project Composer, wp-packagist, Bedrock, site-wide checkout, symlink, unreadable. | Provenance parser (not bundled vendor); fail-open only with no evidence; `updatronix_pro_is_version_controlled` override; visible evidence; no global banner. | No |
| Schema migration breaks export/external log | Low | High | Old-schema row, old event key, strict `updatronix_after_log` consumer, old export cursor. | Additive columns/fields; old keys/columns preserved; dedicated schema version; rollback switch; `dbDelta` once. | No |
| Deleting the queue table loses operational history | High | Medium | Migration fixture; `SHOW TABLES`; log rows remain. | Settled decision Q14: drop, no row migration. Take a database backup before Step 4. The log is the durable history. | No (settled); backup is operational |
| Multisite attribution is wrong | Medium | High | `WP_MULTISITE=1` tests; network/subsite rows; `site_id`, `network`, `target_site_id`. | Execution-site `site_id`; `network=1` only for network-scoped decisions; `target_site_id`; network badge; main-site pass. | No |
| Cron overlap/race | Medium | High | Two cron requests; native lock; heartbeat; follow-up cap; no double item. | One item per request; native `auto_updater.lock` is the only lock; follow-up +1s; lock-busy 5 min, max 3; no lease. | No |
| Disk-full/fatal error recovery | Medium | High | Fixture with disk-full and fatal; attempt marker; stale pass reconciliation. | Native maintenance/rollback; attempt marker before the write; heartbeat; next pass retries once; `uncertain` when neither source nor target. | No |
| Native filter precedence mismatch | High | Medium | Order tests: locked+incompatible → Version Lock; incompatible+held → compatibility; held only → delay; one row. | Ordered Pro adapter at 19; each filter respects an incoming `false`; Safe Mode fallback does not double-write. | No |
| Flow kernel deletion breaks Flow before Step 5 | High | High | Route/class tests; Flow page load; no fatal. | Step 4 freezes Flow execution and removes the runner/routes/UI; Step 5 restores ordering. No release between 4 and 5 unless OPEN-2. | **OPEN — owner decision** (OPEN-2) |
| `version_control_lock=false` changes meaning | Medium | Medium | Settings fixture; existing value preserved; native notice. | Preserve the stored value; document that `false` now disables the whole Updatronix Version Lock; WordPress core still blocks native VCS. | **OPEN — owner decision** (OPEN-3) |
| `first_seen` starts later for blocked items | Medium | Medium | Ledger test: a blocked offer gets `first_seen` at plan time; after unblock, the hold may already be over. | Plan-time `observe_offer()`; Free-only sites keep the gate writer; display shows the hold date as secondary. | Confirmation (OPEN-7), not blocking |
| Schedule has no blocked/failed state | High | Medium | Truth table: incompatible item shows `blocked`, not `eligible`; failed item shows `failed`. | Add `policy` facts and `blocked`/`failed`/`uncertain` display states; missing facts → `unknown`. | No |
| Pass summary storage vs logging disabled | Low | Medium | Flow page with `logging_enabled=false`; last-pass cache present. | Log row is the audit source; `updatronix_pro_last_pass` is a one-entry cache; “No pass summary” when neither exists. | No |
| Queue Health removal loses diagnostics | Medium | Medium | Health screen; pass checks. | Replace the five queue checks with three pass checks (stale/no follow-up, WP-Cron disabled, native lock busy). | **OPEN — owner decision** (OPEN-4), non-blocking |
| Subsite automatic updates remain native/all-at-once | High | Medium | Multisite test; subsite tick with two items. | Document the main-site limitation; do not claim network-wide one-by-one. A future per-site pass is out of scope. | Confirmation (OPEN-6), non-blocking |
| Version Lock manual guard blocks the pass’s own write | Medium | High | Guard exemption test; pass flag; `is_executing()`. | The guard allows when the pass flag equals the current pass id; WP-CLI allowed; one row. | No |
| Core manual guard is missing | Medium | Medium | Core checkout fixture; update-core direct URL. | Add `hook_extra['type']='core'` and an `ABSPATH` marker to the manual guard. | No |
| Native UI filter/JS collision | Medium | Medium | DOM/filter tests for locked, held, waiting, queued, blocked, failed, no-offer. | Version Lock at 90, Schedule at 110; Schedule no-ops on locked rows; distinct data attributes; `textContent` only. | No |
| `core:core` identity mismatch | Medium | High | Flow planner/picker/chain tests; saved chains. | Canonical `core:wordpress`; `core:core` alias; no migration needed. | No |
| Old log action types/aliases | Low | Low | Legacy row/export tests. | Alias map; old values readable; no reworded `__()` strings. | No |
| Log volume/performance | Medium | Medium | 50-item pass, 10k-row export, query count; `EXPLAIN`. | State-transition writes; five indexes; per-pass caps; pin cap 500; no N+1. | No |
| External log strict consumer | Low | Medium | Strict consumer test; old 12 keys. | Additive fields; old required args; `updatronix_log_updated` only on enrichment. | No |
| Release cadence with Flow frozen | Medium | High | Release checklist; no release between Steps 4 and 5. | One release for Steps 1–5; if smaller releases, Step 4+5 together or OPEN-2 approval. | **OPEN — owner decision** (OPEN-2) |

---

## 8. Owner decisions

### 8.1 Blocking decisions

**OPEN-1 — Approve the implementation order.**

The recommended order is Version Lock → Logger foundation → Schedule → Queue →
Flow. The prompt’s likely order is Version Lock → Queue → Schedule → Logger →
Flow. The recommended order avoids a temporary log format and a rewritten
`eligible_at`; Queue uses the final Logger schema and the Schedule calculator
from its first commit.

- Recommended: approve the recommended order.
- Alternative: keep Queue second and accept the temporary `update` action and
  ledger-based `eligible_at`, then rewrite in Logger/Schedule.

**Blocking:** the first implementation tab cannot choose this silently.

**OPEN-2 — Approve the Flow execution freeze and the release cadence.**

Step 4 deletes the kernel/scheduler, so the Flow runner cannot survive. Step 4
must remove the runner, `POST /flow/run`, `GET /flow/runs/{id}`, the cooldown,
Run now, and polling; the Flow page shows a read-only notice until Step 5.
This is a development-phase freeze.

- Recommended: one release for Steps 1–5; no shipped freeze.
- Alternative: ship Steps 1–3, then Step 4+5 together. Do not ship Step 4 alone.
- If the owner insists on shipping Step 4 alone, approve the freeze notice as a
  known limitation.

**Blocking:** the Flow page behavior and the release plan depend on it.

**OPEN-3 — Choose the migration for a stored `version_control_lock=false`.**

The old switch controlled only the Composer lock. The new master controls
native VCS detection, Composer provenance, the native UI lock, and the optional
manual guard. A stored `false` would disable the whole Updatronix Version Lock
for that site; WordPress core still refuses its own VCS contexts.

- Recommended: preserve the stored value and add a one-time admin notice that
  explains the new meaning. This respects the owner’s setting and avoids a
  surprise reset.
- Alternative: reset `false` to `true` on upgrade and tell the owner. This
  restores the automatic lock but changes an explicit setting.

**Blocking for existing sites:** choose before Step 1’s settings migration.

### 8.2 Non-blocking confirmations

**OPEN-4 — Replace the queue Health checks with pass checks.**

Recommended: three checks (pass option stale/no follow-up, WP-Cron disabled,
native lock busy). Alternative: delete the update-engine Health checks
entirely. The recommended option keeps observability without the queue.

**OPEN-5 — Take a database backup before Step 4.**

The queue table rows are dropped by settled decision Q14. Recommended: take a
full database backup before Step 4; no code migration. Alternative: accept the
loss of operational queue rows (the log remains). This is operational, not a
code decision.

**OPEN-6 — Confirm the main-site-only one-by-one limitation.**

The pass is registered on the main site only. Subsite automatic updates remain
native and can run more than one item per invocation. This follows the settled
“main-site engine” decision. Recommended: confirm and document; a per-site pass
is out of scope.

**OPEN-7 — Confirm the hold clock starts at discovery.**

Recommended: `first_seen` starts when the automatic pass first observes the
offer at plan time, even if Version Lock or compatibility blocks it. This
matches the copy “the hold starts when the next check first sees this release.”
Alternative: start the clock only when the item becomes eligible; a blocked
item’s hold would start later. This is a behavior choice, not a schema change.

**OPEN-8 — Contingency: per-item native email cannot be verified.**

If the live fixture shows WordPress groups two items into one email even with
one item per invocation, choose: accept a grouped email (recommended for
simplicity) or approve a custom per-item mail. This is a contingency, not a
current decision; the plan expects one email per item and the fixture must
prove it.

---

## 9. Go / no-go

### 9.1 Plan readiness

| Plan | Verdict | Required amendments |
|---|---|---|
| Version Lock | **GO** | Ordered native adapter at 19 (not Version Lock-only at 20); canonical reason codes and the `unknown`/`unverified` split; core manual guard; `decision()` + Shield state map; no Schedule facts callback. |
| Logger | **GO** | Lands before Queue; do not edit the delay file; add `version_lock_unverified`, `origin=cli`, `system`, `pass`, `advisory`; `Decision_Log` is the only Pro writer; journal interim delegation, deleted in Step 4; no Flow runner edit; pass summary source + last-pass cache. |
| Schedule | **GO** | Create `Updatronix_Item_State`; add `policy` facts and `blocked`/`failed`/`uncertain`/`running` display states; own the delayed-row rewrite; own the single `Schedule_Facts` callback; filter priorities 90/110; `core:wordpress`. |
| Queue | **GO** | Gate is selection-only; final Logger schema from day one; `core:wordpress`; `observe_offer()` at plan time; `native_lock_busy` is waiting/skipped, not failed; Flow freeze/runner/routes/UI removal; pass Health checks; last-pass cache. |
| Flow | **CONDITIONAL GO** | Starts only after Steps 1–4 exit; removes the freeze; consumes the pass gate, Schedule facts, Version Lock decision, and `Decision_Log`; no second execution path; `core:wordpress`; no release between Steps 4 and 5 unless OPEN-2. |

No plan needs a full re-plan. Each needs the amendments recorded in its task
file before implementation; do not create a new task file.

### 9.2 What must be true before the first implementation tab

1. The owner approves the order and resolves OPEN-1, OPEN-2, and OPEN-3.
2. OPEN-4 through OPEN-8 are confirmed or explicitly deferred.
3. Each task file records its amendments from §5; the “Contracts and overlaps”
   section points to this document.
4. Both repos have a recorded base commit and a baseline suite result; any
   pre-existing failure is documented.
5. A database backup is scheduled before Step 4.
6. The rollback switch names and the migration flag are written into the task
   files (`updatronix_log_decision_layer_enabled`,
   `updatronix_native_update_time_enabled`,
   `updatronix_pro_update_pass_enabled`).
7. No production code is edited before this synthesis is approved.

### 9.3 Final recommendation

Proceed with the recommended order and the targeted amendments. The design is
coherent: one pass, one gate, one order, one execution path, one log history,
one reason vocabulary, and one time calculator. The remaining risks are
testable, and the plans already name the cases.

Do not start Flow before the pass, the Logger API, the Schedule facts, and the
Version Lock decision are in place. Do not ship Step 4 without Step 5 unless
OPEN-2 is approved. Do not drop the queue table without a backup.

---

## Appendix A — Verification evidence

The synthesis ran read-only inspections only. No test suite was run because no
production code changed; the gate results belong to each implementation step.
The following commands were used:

```bash
# Input and structure checks
ls -la docs/wpds/60-update-engine-rethink/analysis/
ls -la updatronix-pro/.agents/tasks/
ls -la updatronix/.agents/tasks/

# Code-surface checks
grep -n "function runtime_kernel" updatronix-pro/inc/update-engine/class-updatronix-update-engine.php
grep -n "Updatronix_Update_Kernel" updatronix-pro/inc/update-flow/class-updatronix-flow-runner.php
grep -n "register_rest_route" updatronix-pro/inc/update-engine/class-updatronix-queue-rest.php
grep -n "flow/run\|flow/runs" updatronix-pro/inc/update-flow/class-updatronix-update-flow.php
grep -n "core:wordpress\|core:core" updatronix-pro/inc/update-engine/class-updatronix-flow-planner.php
grep -n "UPDATRONIX_DB_VERSION" updatronix/inc/core/constants.php
grep -n "public static function log(" updatronix/inc/classes/class-updatronix-logger.php
grep -n "FILTER_PRIORITY" updatronix/inc/classes/class-updatronix-autoupdatedelay.php
grep -n "PHP_INT_MAX - 5" updatronix-pro/inc/update-shield/class-updatronix-safe-mode.php
grep -rn "updatronix_schedule_item_state\|updatronix_auto_update_can_toggle\|updatronix_pro_update_pass" updatronix/inc updatronix-pro/inc

# Graft checks
bin/harness graft ask "Updatronix_Update_Engine runtime_kernel queue_enabled facade" --source
bin/harness graft ask "Updatronix Flow Runner kernel update queue scheduler" --source
```

Graft reported approximately 38,037 tokens saved on the first query and 36,221
on the second. The estimates are graft’s own; they are not a test result.

## Appendix B — Glossary

| Term | Meaning |
|---|---|
| Pass | The one automatic update run that starts at `wp_maybe_auto_update` and drains one item per native invocation. |
| Gate | The `auto_update_plugin`, `auto_update_theme`, and `auto_update_core` filter at `PHP_INT_MAX` that selects one item per request. |
| Readiness | The 18-state resolver answer to “may this item run in this pass?” |
| Display state | The Schedule’s 18-state answer to “what should this surface say?” |
| Decision state | The logger’s 9-state answer to “what happened?” |
| Offer | The pending update object from the native transient. |
| Target | The offered version. |
| Installed | The version on disk. |
| First seen | The first time the automatic pass observed the offer; the hold clock. |
| Eligible at | The first nominal check at or after the hold end (or the next check when the hold is off). |
| Execution at | The earliest execution; only when the state is eligible and there is no queue/Flow/lock uncertainty. |
| Primary reason | The first denial in the precedence order; secondary reasons are context. |
| Legacy row | A log row written before the new columns; it renders “Legacy — reason not recorded.” |

## Appendix C — Change log

| Date | Change |
|---|---|
| 2026-10-28 | Synthesis created from the five analyses and five plans. Resolved C1–C13; defined the order and shared contracts; added the risk register and owner decisions. No production code changed. |

**Non-blocking confirmations:** OPEN-4 (replace the queue Health checks with
three pass checks), OPEN-5 (take a database backup before Step 4), OPEN-6
(confirm the main-site-only one-by-one limitation), OPEN-7 (confirm the
hold clock starts at discovery), and OPEN-8 (contingency if per-item native
email cannot be verified). See §8.2.

---

> Synthesis complete. Implementation may start at Step 1. The owner approved
> the order and resolved the blocking decisions on 2026-10-28.

**Owner decisions (resolved 2026-10-28):**

1. **OPEN-1 — Implementation order.** Approved: Version Lock → Logger
   foundation → Schedule → Queue → Flow.
2. **OPEN-2 — Flow freeze and release cadence.** Approved: one release for
   Steps 1–5; no release between Step 4 and Step 5.
3. **OPEN-3 — `version_control_lock=false` migration.** Preserve the stored
   value and add a one-time admin notice that explains the new meaning.

**Non-blocking confirmations:** OPEN-4 through OPEN-8 are confirmed for the
implementation: three pass Health checks, a database backup before Step 4,
main-site-only one-by-one, the hold clock starts at discovery, and the
per-item email contingency.
