# Plan 02 — Update Queue

> Copy everything below the line into the Queue tab, after `/architect`.
> Plan only. Do not implement.

---

You are in **plan mode** for **Update Shield → Update Queue**. The five analyses
are complete and all owner decisions are settled. Do not re-litigate a settled
decision. If you believe one is wrong, stop and tell the owner.

## Repos and commands

- Free plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix`
- Pro plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix-pro`
- Every command goes through `bin/harness` in the repo you are in. Never call
  `php`, `composer`, `node`, `npm`, or `wp` directly.

## Read first

1. `AGENTS.md`, `workflow.md`, and `.agents/skills/architect/SKILL.md`.
2. All five analyses:
   - `01-version-lock.md`
   - `02-update-queue.md`
   - `03-update-flow.md`
   - `04-update-schedule.md`
   - `05-logger.md`
3. Plan 1:
   `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-version-lock-rework.md`.
   If it does not exist, stop and tell the owner to finish plan 1.
4. This feature’s QA and grilling notes:
   `updatronix-pro/.agents/notes/2026-10-28-qa-update-queue.md`,
   `2026-10-28-grill-update-queue.md`.
5. Pro code:
   - `inc/update-engine/class-updatronix-queue-scheduler.php`
   - `class-updatronix-update-queue.php`
   - `class-updatronix-update-kernel.php`
   - `class-updatronix-update-executor.php`
   - `class-updatronix-update-notifier.php`
   - `class-updatronix-update-journal.php`
   - `class-updatronix-queue-rest.php`
   - `class-updatronix-queue-summary.php`
   - `class-updatronix-shield-ownership.php`
   - `class-updatronix-update-engine-bootstrap.php`
   - `class-updatronix-update-engine.php`
   - `inc/settings/options.php`
   - `assets/src/js/components/UpdateShieldPage.js`
   - `tests/Integration/UpdateQueueLiveFixtureTest.php`
6. Free code:
   - `inc/classes/class-updatronix-autoupdatedelay.php`
   - `inc/classes/class-updatronix-update-logger.php`
   - `inc/classes/class-updatronix-notifications.php`
   - `inc/classes/class-updatronix-logger.php`
   - `inc/classes/class-updatronix-health.php`
7. WordPress core:
   - `wp-admin/includes/class-wp-automatic-updater.php` — `run()`,
     `update()`, `should_update()`, `send_plugin_theme_email()`,
     `automatic_updates_complete`.
   - `wp-admin/includes/class-wp-upgrader.php` — `create_lock`,
     `release_lock`.
   - `wp-includes/update.php` — `wp_maybe_auto_update`.

Use `bin/harness graft ask "queue scheduler pass" --source`,
`bin/harness graft ask "native executor run" --source`, and
`bin/harness graft callers Updatronix_Queue_Scheduler` before opening files.

## Position in the five-plan sequence

This is **plan 2 of 5**. Plan 1 (Version Lock) must exist. Plans 3–5 will
consume this plan’s pass contract. You own the execution model only. Do not plan
the Flow planner, Schedule UI, or Logger schema.

## Settled decisions to encode

These are the owner’s decisions from `02-update-queue.md` §10–§11. Do not
change them.

1. **One item per native invocation.** Use `WP_Automatic_Updater::run()` with a
   one-item gate. The gate never returns `true`; it only lets one selected item
   through and leaves the others at the incoming `false`.
2. **One pass.** A pass starts at the scheduled `wp_maybe_auto_update` tick.
   A single follow-up drains it; the next scheduled check starts a new pass.
   A held item never bypasses its hold.
3. **Persistence.** One non-autoloaded option on the main site, proposed
   `updatronix_pro_update_pass`. It holds the pass id, started time, order,
   nodes, predecessors, waiting reasons, attempted set, done set, last item,
   lock waits, and next check. Bounded to 50 items. Delete the queue table.
4. **No retry loop.** A failed item is skipped for the pass; the next pass
   retries once. No 5/15/60-minute backoff.
5. **Native email.** One native per-item email, successes included. Remove the
   custom digest notifier and its fake log row.
6. **Log.** One item outcome row plus one pass summary row. The native logger
   owns the actual-update row; Pro enriches it; no duplicate “updated” row.
7. **Remove manual management.** Delete the queue screen actions, retry,
   cancel, run, queue summary, Health checks, and the queue switch. Keep a
   read-only last-pass summary only if the owner wants it; the analysis says
   remove the manual actions.
8. **Order.** Flow order first for chained items; native default for the rest
   (plugins, themes, core); translations last. No security-release jump.
9. **New offer mid-pass.** It waits for the next pass, mirroring native
   `run()`’s single refresh snapshot.
10. **Deletion.** Delete the queue table, kernel, queue REST, queue summary,
    Health checks, manual actions, and switch; keep the policy chain and the
    Flow planner. Drop legacy rows on upgrade; no migration. Delete the pass
    option on deactivation/uninstall.
11. **Compatibility.** Main-site only; WP-CLI and manual updates are outside
    the gate; the native `auto_updater` lock serializes races; fatal and
    disk-full follow the skip rule.
12. **Translations.** Not gated; native `run()` processes them last in the same
    invocation. Document the exception.

## Plan scope

In scope:

- The pass option/value object and its lifecycle.
- The one-item gate for `auto_update_plugin|theme|core|translation`.
- The order builder: Flow plan first for chained items, native default for the
  rest, dedupe, translations last.
- The follow-up event, pass heartbeat, stale-pass recovery, lock-busy retry.
- Native `run()` invocation and result mapping.
- Deletion of the custom queue table/kernel/notifier/REST/summary/Health/
  manual actions/switch and their UI.
- Native per-item email and the Free notification contract.
- The item outcome row + pass summary row, with the pass id.
- Migration/cleanup/uninstall.
- Tests, docs, and the live fixture.

Out of scope:

- Flow planner/readiness (plan 5).
- Schedule calculator/UI (plan 3).
- Logger schema/UI (plan 4).
- Version Lock detector/UI (plan 1).

## Required plan tasks

At least:

1. **Pass model.** Value object, option schema, lifecycle, bounds, heartbeat,
   stale recovery, deletion.
2. **Gate.** `auto_update_*` filters, one selected item, no `true` return,
   executor/ownership exemption, native `run()` call, lock handling.
3. **Order builder.** Flow/chained items first, native default for the rest,
   dedupe, translations last, core position, new-offer snapshot.
4. **Execution.** One item per invocation, result mapping, no retry loop,
   failed-item skip, next-pass retry, lock-busy follow-up (5 min, cap 3).
5. **Email.** Native per-item email, success included, recipients, suppression
   rules, no digest.
6. **Log.** Item outcome row, pass summary row, pass id, event key, native row
   enrichment, no duplicate.
7. **Deletion.** Table, kernel, queue REST, queue summary, Health checks,
   manual actions, switch, notifier, legacy options/transients. Keep the policy
   chain and Flow planner.
8. **Migration/compat.** Existing rows/options, upgrade, deactivation,
   uninstall, multisite main-site.
9. **Tests.** Live fixture: 0/1/2/N items, plugin+theme, plugin+core,
   translation, failure, lock-busy, fatal, disk-full, stale offer, item removed,
   two cron requests, manual update during pass, new offer mid-pass, queue off,
   license inactive, multisite, WP-CLI.
10. **Docs.** Update the analysis/contract docs if implementation reveals a
    mismatch; do not rewrite settled decisions.

## Edge cases the plan must map

Use the 30-row matrix in `02-update-queue.md` §8 and the 12 blocking cases in
§9. At minimum:

- 0 eligible items; 1 item; 2 items; N items;
- plugin+plugin, plugin+theme, plugin+core, core+plugin, translation;
- one failure; one lock-busy; one fatal; one disk-full; one stale offer;
- an item removed mid-pass; two cron requests; a manual update during a pass;
- a new offer mid-pass; queue off/license inactive; multisite; WP-CLI;
- a held item; a version-locked item; a Flow predecessor waiting.

## Contracts and overlaps

The task file must include:

- **Provides to Flow:** one pass, one order, one gate, one execution path; the
  pass option fields; the ownership set; the failure policy; the active-pass
  heartbeat; the pass summary.
- **Provides to Schedule:** `pass_id`, `started_at`, order, current item,
  per-item state, next follow-up time; translations are not pass items.
- **Provides to Logger:** pass id, item outcome row, pass summary row, reason/
  source fields, event key includes the pass id.
- **Needs from Version Lock:** a plan-time exclusion hook and an execution flag.
- **Needs from Flow:** the ordered nodes and readiness facts.
- **Do not touch:** `class-updatronix-flow-planner.php`,
  `class-updatronix-update-flow.php`, `UpdateFlowPage.js`,
  `SchedulePanel.js`, `class-updatronix-autoupdatedelay.php`, the Logger schema
  files. Those belong to plans 1, 3, 4, and 5.

## Plan file

Write the task file to:

`updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-update-queue-rework.md`

Frontmatter:

```yaml
---
type: feature
slug: update-queue-rework
date: YYYY-MM-DD
uid: YYYYMMDD-<random>
status: planning
review_required: yes
risk: [rest, sql, cron, email, multisite]
---
```

Include: Goal · Context · Tasks · Edge cases · Contracts and overlaps ·
Do-not-touch · Test gate · Rollback · Session checkpoint · Log · Feedback.

## Test gate

- Pro `bin/harness test`, `integration`, `test:js`, `lint`, `build`.
- Free `bin/harness test`, `integration`, `test:js`, `lint`, `build` for the
  Free notification/health surfaces.
- `bin/harness counterfactual` or `mutation` on the changed lines.
- The live fixture must prove: one native install, one native email per item,
  one item log row, one pass summary row, no duplicate “updated” row, no queue
  table, no manual route, no digest.
- Manual verification: scheduled tick, follow-up, lock-busy, fatal error,
  disk-full, multisite, WP-CLI, manual update.
- E2E only if the host supports it; otherwise record the gap.

## Stop condition

Plan only. Do not edit production code. Do not start Phase 4. After writing the
task file, return a ~10-line summary with:

- the execution model and pass fields;
- the deletion inventory;
- the contracts it provides;
- the blocking edge cases;
- the test gate;
- the rollback point;
- anything the synthesis must reconcile.

End with: “Ready to start, or would you like to adjust anything?”
