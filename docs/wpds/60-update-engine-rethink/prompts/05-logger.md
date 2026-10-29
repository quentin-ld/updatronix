# Prompt 05 — Update logger

> Copy everything below the line into a fresh tab after `01`–`04` exist.
> Analysis only; do not implement.

---

You are the analysis agent for the **update logger** in the Updatronix plugin
family. The owner is Quentin. The repo’s `AGENTS.md` requires US English
replies and the WordPress documentation style; follow it.

## Repos and commands

- Free plugin (log table, logger API, activity log UI):
  `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix`
- Pro plugin (Shield, Flow, update engine decisions):
  `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix-pro`
- Shared product docs: `wp-content/plugins/updatronix/docs/wpds`
- Every command goes through `bin/harness` in the repo you are in. Never call
  `php`, `composer`, `node`, `npm`, or `wp` directly.

## Read first

1. `AGENTS.md` and `workflow.md`.
2. The four required analyses:
   - `analysis/01-version-lock.md`
   - `analysis/02-update-queue.md`
   - `analysis/03-update-flow.md`
   - `analysis/04-update-schedule.md`
   If any is missing, stop and tell the owner which tab to run first.
3. `docs/wpds/14-update-log-review.md`,
   `docs/wpds/25-update-log-review.md`,
   `docs/wpds/27-update-log-wpds-review.md`,
   `docs/wpds/36-update-logs-state-of-play.md`,
   `docs/wpds/45-update-engine-architecture.md` §2.2, §5.8.
4. Free code:
   - `inc/classes/class-updatronix-database.php`
   - `inc/classes/class-updatronix-logger.php`
   - `inc/classes/class-updatronix-update-logger.php`
   - `inc/classes/class-updatronix-updatelogstate.php`
   - `inc/classes/class-updatronix-security.php` (allowlists)
   - `inc/classes/class-updatronix-export.php` and the export query/schema
     classes
   - `assets/src/js/components/activityLog/ActivityLogPanel.js`
   - `assets/src/js/components/activityLog/logFilters.js`
   - `assets/src/js/components/activityLog/constants.js`
   - `assets/src/js/components/activityLog/LogDetailsContent.js`
   - `assets/src/js/components/activityLog/exportFormats.js`
5. Pro code:
   - `inc/update-engine/class-updatronix-update-journal.php`
   - `inc/update-engine/class-updatronix-update-kernel.php`
   - `inc/update-engine/class-updatronix-shield-enforcement.php`
   - `inc/update-engine/class-updatronix-queue-scheduler.php`
   - `inc/update-flow/class-updatronix-flow-runner.php`
   - `assets/src/js/components/UpdateFlowPage.js`
   - `assets/src/js/components/UpdateShieldPage.js`
6. Tests:
   - `tests/Unit/*Logger*`, `tests/Integration/*Log*`,
     `tests/Integration/*Export*`, `tests/js/*log*`.

Use `bin/harness graft ask "logger action types statuses" --source`,
`bin/harness graft ask "update journal transition mapping" --source`,
`bin/harness graft grep "update_context"`, and
`bin/harness graft callers Updatronix_Logger` before opening files.

## What the owner expects

The owner’s words, translated and grouped:

1. The logger must be upgraded so the owner can see whether the information
   displayed is true, and whether everything needed is there to understand
   **what is blocked, postponed, or adjusted by Flow**.
2. The other four features must settle their states first; the logger must
   describe those states truthfully.
3. The logger is the place to answer: why did this item not update? What is it
   waiting for? What changed the order? What happened in this automatic pass?

## What to verify first (starting points, not conclusions)

- The table columns are fixed: `log_type`, `action_type`, `status`, `message`,
  `version_before`, `version_after`, `event_key`, `performed_as`,
  `update_context` (`bulk`/`single`/empty), timestamps. There is no structured
  reason code, run/pass id, flow chain, predecessor, queue position,
  next-eligible time, or decision stage.
- `event_key` is unique and deduplicates rows. Check how the delay gate, the
  Shield block, the engine transitions, and the native update logger build
  their keys. A repeated “waiting” state must not create a row every tick.
- The delay gate writes `status=delayed` with the eligible time inside the
  message text. The engine journal writes one row per transition, with
  `update_context=single` and coarse action types. The native Free logger
  writes one row per actual update. Check for duplicate rows when the engine
  and the native logger observe the same update.
- The Flow run summary is a bounded option (`updatronix_pro_update_runs`, last
  20), not linked to log rows. The Flow page reads it. The log page cannot
  reconstruct the run from structured data.
- The activity log UI filters by type, action, status, user, and date. It does
  not filter by reason, origin, flow chain, predecessor, or run id. The export
  schema mirrors the same filters.
- The Pro `updatronix_log_action_types` filter adds
  `auto_update_reenabled`. Check whether new action types need the same
  additive filter and whether the export allowlist follows.
- The external logging integration (`class-updatronix-external-log.php`) and
  the public `updatronix_after_log` hook consume the stored fields. A schema
  change must keep them working.
- Multisite stores logs on the main site; the `site_id` column identifies the
  subsite. Check how a network-wide flow/queue decision is attributed.
- Retention, indexes, and table size: the logger is the primary evidence, so
  the design must survive high-volume “waiting” states without bloating the
  table.

## Phase 1 — current-state map

| Surface | Implementation (file:line) | Actual behavior | Consumer | Owner match? |
|---|---|---|---|---|
| Table schema | | | | |
| Logger API | | | | |
| Action/status allowlists | | | | |
| Event key/dedupe | | | | |
| Native update logger | | | | |
| Delay gate logs | | | | |
| Shield block logs | | | | |
| Engine journal | | | | |
| Flow run summary | | | | |
| Activity log UI | | | | |
| Export schema | | | | |
| External log/hook | | | | |
| Multisite | | | | |
| Retention/indexes | | | | |

## Phase 2 — the question → data → UI matrix

Before grilling, write the matrix the owner actually needs. For each question,
name the structured data required, the write point, and the read/display point.

| Owner question | Required data | Write point | Read/display | Current support |
|---|---|---|---|---|
| Why did this item not update in this pass? | | | | |
| Is it blocked, delayed, waiting, or skipped? | | | | |
| What is it waiting for (hold, predecessor, lock, queue position)? | | | | |
| When may it run? | | | | |
| Which Flow chain/reason changed the order? | | | | |
| Did Shield block it, and with which policy? | | | | |
| Did it fail, retry, or exhaust attempts? | | | | |
| Did it succeed, and from which version to which? | | | | |
| Which pass/run does this row belong to? | | | | |
| Was it a manual, automatic, Flow, or queue action? | | | | |
| Is the displayed state in the UI true? | | | | |
| What happened before/after this item in the chain? | | | | |

Then propose a **reason taxonomy**. At minimum:

- `version_locked_native`
- `version_locked_composer`
- `version_lock_unknown`
- `shield_incompatible_wp`
- `shield_incompatible_php`
- `shield_disabled_auto_update`
- `schedule_hold`
- `flow_waiting_predecessor`
- `flow_predecessor_blocked`
- `flow_predecessor_locked`
- `flow_predecessor_failed`
- `flow_chain_invalid`
- `flow_offer_stale`
- `queue_waiting_turn`
- `queue_lock_busy`
- `queue_retry_backoff`
- `queue_attempts_exhausted`
- `native_lock_busy`
- `native_vcs`
- `manual_update`
- `already_latest`
- `no_pending_offer`
- `license_inactive`
- `engine_disabled`
- `wp_cron_disabled`
- `unknown`

Decide whether these are `action_type` values, a new `reason_code` column, or
both. Keep the vocabulary aligned with Flow and Schedule.

## Phase 3 — grilling

Invoke `grilling` at `.agents/skills/grilling/SKILL.md`. Ask the whole frontier
in one round, with recommendations, then wait. Write
`wp-content/plugins/updatronix/.agents/notes/YYYY-MM-DD-grill-logger.md`.

Seed questions:

1. **Structured columns vs JSON.** Add explicit columns for the important
   dimensions, or one JSON `context` column? Recommendation: explicit columns
   for the filterable dimensions (`reason_code`, `origin`, `run_id`,
   `chain_id`, `predecessor`, `next_eligible_at`, `decision_stage`) and a
   JSON `context` for the long tail.
2. **Schema migration.** A new table version, `dbDelta`, a backfill, and a
   rollback plan? What happens to existing rows? Recommendation: additive
   columns with defaults; old rows show “legacy/no reason”; a schema version
   option; no destructive migration.
3. **One row per decision vs one per outcome.** Waiting and re-evaluation can
   be frequent. Recommendation: one row per state transition, deduplicated by
   `event_key` including the state and the pass; no row on every tick if the
   state did not change.
4. **Pass/run identity.** Reuse the queue `run_id` or introduce an
   `automatic_pass_id` that all features share? Recommendation: one
   `pass_id` for the scheduled automatic pass, and keep `run_id` only if the
   simplified queue still needs it.
5. **Native vs Pro duplication.** The native logger already records actual
   updates. Pro records decisions. Recommendation: one actual-update row from
   the native logger; Pro adds decision rows only, with a clear origin; never
   two “updated” rows for one update.
6. **Action taxonomy.** Keep the existing allowlist and add reasons, or
   replace it? Recommendation: keep the existing action types for
   backward compatibility, add `reason_code` for the why, and add new action
   types only when the user-visible action is genuinely new.
7. **Statuses.** Keep `success/error/cancelled/delayed/info/warning`?
   Add `waiting/blocked/skipped`? Recommendation: map new states onto the
   existing statuses and put the nuance in `reason_code`; add `waiting` only
   if the UI needs it.
8. **UI.** Which filters/columns? Recommendation: add Reason, Origin, Flow
   chain, Pass, and Next eligible; keep Type/Action/Status/User/Day; make the
   detail view show the full context and the related pass/chain.
9. **Export.** Extend the export schema and the CSV/JSON columns; keep the
   old fields. Confirm.
10. **Retention/performance.** Indexes for `reason_code`, `run_id`,
    `chain_id`; retention for waiting rows; a cap per pass. Recommendation:
    indexes only on the filters the UI uses; dedupe waiting rows; keep the
    existing retention.
11. **External logging.** Extend the payload additively; never break the
    existing `updatronix_after_log` consumers. Confirm.
12. **Multisite.** Attribute a network-wide decision to the main site or the
    subsite? Recommendation: the site where the update runs, plus a
    `network` origin flag.
13. **Truthfulness audit.** For every state the Shield, Flow, Schedule, and
    Auto updates screens display, the log must have a matching reason. If a
    screen shows “waiting”, the log must show the predecessor; if it shows
    “eligible at X”, the log must show the same calculation. Confirm the audit
    method.
14. **What not to log.** Avoid secrets, full paths, package URLs, and
    recipient addresses. Confirm the redaction rules.

## Phase 4 — QA

Invoke `qa` in audit mode. Focus on:

- a UI state with no log row (silent block/wait);
- a log row that contradicts the UI (says blocked, UI says waiting);
- duplicate “updated” rows from native + engine;
- a waiting row written every cron tick;
- an `event_key` that collapses two genuinely different events;
- a schema change that breaks export, external logging, or the public hook;
- a filter/column that leaks a path, URL, or email;
- a multisite row attributed to the wrong site;
- a retention rule that deletes the only evidence of a blocked item;
- an i18n string with no translator comment;
- a high-volume pass that slows the log table.

Write `.agents/notes/YYYY-MM-DD-qa-logger.md` with severity-ranked findings
and no fixes.

## Phase 5 — analysis deliverable

Write `docs/wpds/60-update-engine-rethink/analysis/05-logger.md`.
Required sections:

1. Scope and non-goals.
2. Current behavior table with `file:line`.
3. Consumers and compatibility surfaces (activity log, export, external log,
   `updatronix_after_log`, Site Health, Flow page).
4. Owner expectation.
5. Gap analysis.
6. The question → data → UI matrix.
7. Reason taxonomy and status mapping.
8. Proposed schema (columns, types, indexes, defaults, migration, rollback).
9. Write points and dedupe rules per event.
10. UI/export changes.
11. Truthfulness audit plan for the other four features.
12. Exhaustive case matrix — at least 25 rows. Columns: ID, event, current
    row, desired row, reason, origin, pass/chain, UI surface, blocking?,
    decision. Include: native update, engine update, Shield block, version
    lock, hold, Flow wait, Flow predecessor blocked, Flow failure, queue
    retry, queue exhaustion, manual update, core security, translation,
    multisite, export, external log, retention.
13. Blocking cases and proposed handling.
14. Decisions settled by grilling.
15. Open questions (`OPEN — owner decision`).
16. Contracts with Version Lock, Queue, Flow, and Schedule.
17. Implementation constraints and test strategy.
18. Evidence.

## Stop condition

Do not edit production code. Do not create a task file. End with:

> Analysis complete; do not implement. Ready for plan mode after the other tabs
> finish.

and list the three most important unresolved owner decisions.
