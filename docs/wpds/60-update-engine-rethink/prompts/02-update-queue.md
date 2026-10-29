# Prompt 02 — Update Shield: Update Queue

> Copy everything below the line into a fresh tab after `01-version-lock.md`
> exists. Analysis only; do not implement.

---

You are the analysis agent for **Update Shield → Update Queue** in the
Updatronix plugin family. The owner is Quentin. The repo’s `AGENTS.md` requires
US English replies and the WordPress documentation style; follow it.

## Repos and commands

- Free plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix`
- Pro plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix-pro`
- Shared product docs: `wp-content/plugins/updatronix/docs/wpds`
- Every command goes through `bin/harness` in the repo you are in. Never call
  `php`, `composer`, `node`, `npm`, or `wp` directly.

## Read first

1. `AGENTS.md` and `workflow.md`.
2. `docs/wpds/60-update-engine-rethink/analysis/01-version-lock.md` — required.
   If it is missing, stop and tell the owner to run prompt 01 first.
3. `docs/wpds/45-update-engine-architecture.md` §2.2, §3.1, §4, §5.6–5.9.
4. `docs/wpds/46-update-engine-4.2-kernel.md`,
   `docs/wpds/47-update-engine-4.3a-shield-policies.md` §4,
   `docs/wpds/48-update-engine-4.3b-queue-live.md`,
   `docs/wpds/50-update-engine-4.4-flow.md` §2.5.
5. Pro code:
   - `inc/update-engine/class-updatronix-queue-scheduler.php`
   - `inc/update-engine/class-updatronix-update-queue.php`
   - `inc/update-engine/class-updatronix-update-kernel.php`
   - `inc/update-engine/class-updatronix-update-executor.php`
   - `inc/update-engine/class-updatronix-update-notifier.php`
   - `inc/update-engine/class-updatronix-update-journal.php`
   - `inc/update-engine/class-updatronix-queue-rest.php`
   - `inc/update-engine/class-updatronix-queue-summary.php`
   - `inc/update-engine/class-updatronix-shield-ownership.php`
   - `inc/update-engine/class-updatronix-update-engine-bootstrap.php`
   - `inc/settings/options.php`
   - `assets/src/js/components/UpdateShieldPage.js`
   - `tests/Integration/UpdateQueueLiveFixtureTest.php`
6. Free code:
   - `inc/classes/class-updatronix-autoupdatedelay.php`
   - `inc/classes/class-updatronix-update-logger.php`
   - `inc/classes/class-updatronix-notifications.php`
   - `inc/classes/class-updatronix-logger.php`
7. WordPress core:
   - `wp-admin/includes/class-wp-automatic-updater.php` — read `run()`,
     `update()`, `should_update()`, `send_plugin_theme_email()`,
     `send_debug_email()`, `automatic_updates_complete`.
   - `wp-includes/update.php` — `wp_maybe_auto_update`,
     `wp_version_check`, `wp_update_plugins`, `wp_update_themes`.
   - `wp-admin/includes/class-wp-upgrader.php` — `create_lock`,
     `release_lock`.

Use `bin/harness graft ask "queue scheduler worker" --source`,
`bin/harness graft ask "native executor update" --source`,
`bin/harness graft grep "queue_live"`, and
`bin/harness graft callers Updatronix_Queue_Scheduler` before opening files.

## What the owner expects

The owner’s words, translated and grouped:

1. The current queue is **far too complicated**. The owner did not ask for a
   job system with a queue table, leases, retries, backoff, REST actions, or
   manual management.
2. The expectation is simple: **when automatic updates are triggered
   automatically**, instead of launching everything at once, Updatronix
   creates a “queue” that launches the automatic updates **one by one with the
   native WordPress system**.
3. Why: sometimes a disk-write error prevents updates after the first one,
   perhaps because the first update is not completely finished. It is a simple
   precaution.
4. Nothing manual to manage. It applies only to the automatic update process
   when several items must be updated in the same pass.
5. Instead of one email with all updates grouped and one log with the grouped
   process, the owner wants the process executed one by one, **with one email
   per item**. Still native: the queue “ungroups” automatic updates.

## The central question

Is the current custom queue/kernel the right implementation, or can it be
replaced by a thin native serialization layer that:

- lets WordPress’s own `WP_Automatic_Updater::run()` perform the update,
- allows **one item per invocation** through the `auto_update_*` filters,
- schedules the next invocation (single event) until the pass is drained,
- uses the native `auto_updater` lock, native maintenance mode, native
  fatal-error rollback, native email, and the Free native logger,
- keeps only the minimum state needed to remember the next item / pass
  progress,
- and has no queue screen, no retry/cancel/run buttons, no queue table if a
  transient/option is sufficient?

Do not assume the answer. Audit both options against the edge cases below and
give the owner a recommendation with evidence.

### What to verify about the current implementation

- `queue_live` is false by default, `queue_enabled` defaults false, the save
  path forces false, and the Shield switch is disabled. Confirm the queue is
  dormant in production and that the owner’s observed “grouped” behavior is
  the native WordPress loop, not the queue.
- The executor calls `WP_Automatic_Updater::update()` directly, not `run()`.
  Confirm what that loses: the native batch email, the native
  `automatic_updates_complete` results, any native discovery refresh, and the
  native loop’s ordering.
- The notifier sends one digest per **run**, only for failed/blocked/uncertain
  steps, deduplicated by `run_id`. Confirm there is no per-item email and no
  success email.
- The scheduler plans all offers into one `run_id`, then the worker processes
  one job per invocation. Confirm whether a second item in the same run can
  ever notify after the first digest.
- The ownership handshake denies native WordPress for rows in
  `pending/claimed/running/failed/uncertain/blocked`. Confirm what happens
  when the engine is disabled, the license is inactive, or the queue is off.
- The queue’s follow-up can run before the next scheduled check. Confirm the
  interaction with the Schedule hold and the owner’s idea of “the scheduled
  automatic update pass”.

## Phase 1 — current-state map

| Surface | Implementation (file:line) | Actual behavior | Native interaction | Owner match? |
|---|---|---|---|---|
| Dormancy gates | | | | |
| Plan/tick | | | | |
| Worker/claim/lease | | | | |
| Executor | | | | |
| Retry/backoff | | | | |
| Failure/cancel | | | | |
| Email | | | | |
| Log rows | | | | |
| Manual actions | | | | |
| Queue UI/Health | | | | |
| Schedule interaction | | | | |
| Flow interaction | | | | |
| Multisite | | | | |

Then write a **complexity inventory**: every class, table, option, transient,
cron hook, REST route, JS surface, and test that exists only because of the
custom queue. For each, say whether the thin native layer would still need it,
could replace it with a transient/option, or could delete it.

## Phase 2 — grilling

Invoke `grilling` at `.agents/skills/grilling/SKILL.md`. Ask the whole frontier
in one round, with recommendations, then wait. Write
`wp-content/plugins/updatronix-pro/.agents/notes/YYYY-MM-DD-grill-update-queue.md`.

Seed questions:

1. **Definition of “one by one”.** One item per HTTP/cron request, one item
   per scheduled pass, or one item every N seconds? Recommendation: one item
   per native automatic-update invocation, followed immediately by a single
   event until the pass is drained.
2. **Native `run()` or native `update()`.** Recommendation: `run()` with a
   one-item gate, so native email, rollback, lock, and logging are preserved.
   Confirm that denying the other items in the same `run()` does not mark them
   failed or send a misleading native email.
3. **Persistence.** No state, a transient cursor, an option cursor, or the
   existing custom table? Recommendation: a small non-autoloaded option or
   transient with the pass id, the ordered item keys, the cursor, and a
   timestamp; delete the table if Flow no longer needs it.
4. **Retries/backoff.** Keep, simplify, or remove? The owner’s reason is disk
   writes, not transient network errors. Recommendation: no automatic retry
   loop; let the next scheduled pass retry naturally, and log the failure.
   Ask whether a failed item should block the rest of the pass or be skipped
   for this pass.
5. **Email.** One native email per item, one digest per pass, one custom email
   per item, or no email? Recommendation: native per-item email by running
   one item per `run()`; if native email cannot be made per-item without
   hacks, send one plain-text item email and log it. Confirm success emails
   are wanted, not only failures.
6. **Log.** One row per item, one row per pass, or both? Recommendation: one
   outcome row per item plus one pass summary row; the item row is the
   primary evidence, the pass row is the grouping.
7. **Manual management.** Remove the queue screen, retry/cancel/run routes,
   queue summary, and Health checks? Recommendation: remove the manual
   actions; keep a read-only “last automatic pass” summary only if the owner
   wants it.
8. **Order.** Native default (core, plugins, themes, translations) or Flow
   order? Where do core security releases and translations sit?
   Recommendation: the automatic gate owns the order; Flow narrows it; native
   default for items not in a chain; translations last.
9. **Pass boundary.** What starts and ends a pass? The scheduled
   `wp_maybe_auto_update` tick? A manual “check now”? A new offer appearing
   mid-pass? Recommendation: a pass starts at the scheduled tick and ends
   when no eligible item remains or the next scheduled tick arrives.
10. **Schedule interaction.** May a follow-up run before the next scheduled
    check, or must every item wait for the next check? Recommendation: a
    follow-up may drain the current pass, but a held item must not bypass its
    hold; the Schedule screen must show the pass and the item states.
11. **Queue switch.** Is there still an on/off switch? The owner describes a
    precaution, not an option. Recommendation: remove the switch; the
    serialization is always on for automatic updates.
12. **Deletion.** Which files, tables, options, routes, tests, and docs are
    deleted? Recommendation: delete the custom queue table, kernel, queue REST,
    queue summary, Health checks, and manual actions if the thin layer passes
    the live fixture; keep the Flow planner and the native gate.
13. **Flow.** If the queue is simplified, how does Flow plug in? Does Flow
    become “the ordering function of the one-item gate”? Recommendation: yes;
    one gate, one pass, one state machine; no second execution path.
14. **Compatibility.** Existing queue rows, options, and logs on an upgraded
    site; multisite; WP-CLI; manual updates during a pass; two cron requests
    racing; a fatal error mid-item; a disk-full error.

## Phase 3 — QA

Invoke `qa` in audit mode. Focus on:

- the current queue’s dormant state being advertised as active (false claims);
- two execution paths (native run + engine `update()`) racing;
- a digest that suppresses later item emails;
- a failed item that blocks the pass forever;
- a crash that leaves a lease/cursor stuck;
- a follow-up that bypasses the schedule hold;
- a queue table row that survives uninstall or license deactivation;
- a per-item native email that is actually a grouped email;
- native `run()` called from a non-cron context and the lock behavior;
- multisite main-site-only execution;
- WP-CLI and manual updates during a pass;
- log volume and duplicate rows.

Write `.agents/notes/YYYY-MM-DD-qa-update-queue.md` with severity-ranked
findings and no fixes.

## Phase 4 — analysis deliverable

Write `docs/wpds/60-update-engine-rethink/analysis/02-update-queue.md`.
Required sections:

1. Scope and non-goals.
2. Current behavior table with `file:line`.
3. Native WordPress interaction.
4. Owner expectation.
5. Gap analysis.
6. Complexity inventory and a **keep / replace / delete** table.
7. Recommended execution model, with the native `run()` one-item gate
   described step by step, including the exact filters, hooks, state, and
   failure handling to validate.
8. Exhaustive case matrix — at least 30 rows. Columns: ID, number of eligible
   items, origin (native/queue/flow/manual), queue state, schedule/hold state,
   item type, expected behavior, current behavior, blocking?, decision.
   Include: 0/1/2/N items, plugin+plugin, plugin+theme, plugin+core,
   core+plugin, translation, one failure, one lock-busy, one fatal error, one
   disk-full, one stale offer, one item removed mid-pass, two cron requests,
   a manual update during a pass, a new offer mid-pass, queue off, license
   inactive, multisite, WP-CLI.
9. Blocking cases and proposed handling.
10. Decisions settled by grilling.
11. Open questions (`OPEN — owner decision`).
12. Contracts with Version Lock, Flow, Schedule, and Logger.
13. Implementation constraints and test strategy.
14. Evidence.

## Stop condition

Do not edit production code. Do not create a task file. End with:

> Analysis complete; do not implement. Ready for plan mode after the other tabs
> finish.

and list the three most important unresolved owner decisions.
