# Plan 03 — Update Schedule

> Copy everything below the line into the Schedule tab, after `/architect`.
> Plan only. Do not implement.

---

You are in **plan mode** for **Update Schedule**. The five analyses are complete
and all owner decisions are settled. Do not re-litigate a settled decision. If
you believe one is wrong, stop and tell the owner.

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
3. Prior plans:
   - `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-version-lock-rework.md`
   - `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-update-queue-rework.md`
   If either is missing, stop and tell the owner which plan to finish first.
4. This feature’s QA and grilling notes:
   `updatronix/.agents/notes/2026-10-28-qa-update-schedule.md`,
   `2026-10-28-grill-update-schedule.md`.
5. Free code:
   - `inc/classes/class-updatronix-cron.php`
   - `inc/classes/class-updatronix-autoupdatedelay.php`
   - `inc/classes/class-updatronix-autoupdates.php`
   - `inc/classes/class-updatronix-settings.php`
   - `inc/settings/options.php`
   - `inc/admin/native-update-delay-notice.php`
   - `assets/src/js/components/schedule/SchedulePanel.js`
   - `assets/src/js/components/schedule/scheduleDraft.js`
   - `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js`
6. Pro code:
   - `inc/update-engine/class-updatronix-delay-policy.php`
   - `class-updatronix-policy-chain.php`
   - `class-updatronix-shield-state.php`
   - `class-updatronix-queue-scheduler.php`
   - `class-updatronix-flow-planner.php`
   - `inc/update-flow/class-updatronix-flow-runner.php`
7. WordPress core:
   - `wp-admin/includes/update.php` — `wp_get_auto_update_message()`.
   - `wp-admin/update-core.php` — the three message calls.
   - `wp-admin/includes/class-wp-plugins-list-table.php` — the
     `auto-update-time` cell.
   - `wp-admin/includes/class-wp-themes-list-table.php`,
     `class-wp-ms-themes-list-table.php`.
   - `wp-admin/js/updates.js` — the “time to next update” text clearing.
   - `wp-includes/update.php` — `wp_version_check`, `wp_maybe_auto_update`.

Use `bin/harness graft ask "unified schedule next run" --source`,
`bin/harness graft ask "delay ledger first seen" --source`, and
`bin/harness graft grep "wp_get_auto_update_message"` before opening files.

## Position in the five-plan sequence

This is **plan 3 of 5**. Plans 1 and 2 must exist. Plan 4 (Logger) and plan 5
(Flow) will consume this plan’s display facts. You own the Schedule UI, cron,
hold calculator, and native override. Do not plan the Flow implementation or
the Logger schema.

## Settled decisions to encode

These are the owner’s decisions from `04-update-schedule.md` §10. Do not change
them.

1. **Picker.** Two WPDS selects: 24 hours and 60 minutes. Stored value stays
   `HH:MM`. Every minute is selectable.
2. **Recurrence.** Keep hourly, twicedaily, daily, weekly, and WordPress
   default. Explain: hourly ignores the time; twicedaily anchors +12h; weekly
   repeats the same weekday/time +7d; default follows core. Preserve the
   preferred time when inactive.
3. **Hold.** Canonical `0..365` days, `0` = off, aligned on client, server, and
   REST. Remove the 0–30 UI vs 1–365 server mismatch.
4. **Core security.** Remove the “security releases skip the hold” claim. The
   hold applies to every core automatic update; no fake security label.
5. **True moment.** Show a **state + earliest execution**, not a false exact
   time. Per-item rows get the item state; update-core gets a next-pass summary
   and a link. No exact time when Flow or the queue makes execution uncertain.
6. **Blue notice.** Remove `native-update-delay-notice.php`’s big banner. Keep
   only a one-line WP-Cron-disabled hint when needed.
7. **States.** Waiting, locked, and queued states show the state, never a
   countdown:
   - waiting: “Waiting for A; no automatic install time yet.”
   - locked: “Managed via {manager}; update through your manager.”
   - queued: “Eligible at X; queued after N; may run later.”
8. **Native override.** Scoped vanilla JS + server-side filters where they
   exist; feature-detect selectors; native fallback; localized strings; no
   focus change; no live region; do not replace the whole cell.
9. **Caching.** One server-side map per request; no N+1; no detector/planner
   calls in the row loop; invalidate on settings/offer/pass/lock changes.
10. **Multisite.** Network schedule, main-site cron, network admin; subsite
    link or no countdown.
11. **Tests.** PHP unit calculator, JS DOM fixtures, integration, E2E,
    multisite.

## Plan scope

In scope:

- The 24-hour + 60-minute picker and validation.
- Canonical hold `0..365`, `0` = off, client/server/REST.
- Recurrence semantics and preferred-time preservation.
- A read-only time calculator:
  `next_check`, `first_seen`, `hold_until`, `eligible_at`, `execution_at`,
  `display` state, timezone, fallbacks.
- `not_observed` when the ledger has no entry.
- Native plugin/theme row override and update-core summary.
- Removal of the big blue notice; one-line WP-Cron hint.
- Per-item state: normal countdown, held, waiting for Flow, queued, locked,
  not_observed, not_managed, WP-Cron problem.
- A read-only Pro facts filter if needed (`updatronix_schedule_item_state`),
  bounded and side-effect free.
- i18n, a11y, performance, multisite, graceful degradation.
- Tests and docs.

Out of scope:

- The Queue pass implementation (plan 2).
- The Flow planner/readiness implementation (plan 5).
- The Logger schema/UI (plan 4).
- The Version Lock detector/UI (plan 1).

## Required plan tasks

At least:

1. **Picker.** Two selects, every hour/minute, WPDS, keyboard/a11y, stored
   `HH:MM`, validation, dirty state.
2. **Hold bounds.** Canonical `0..365`, `0` = off, UI/server/REST, migration of
   stored values, validation messages.
3. **Recurrence semantics.** Hourly/twicedaily/daily/weekly/default, preferred
   time preservation, timezone/DST display, next-check calculation.
4. **Time calculator.** Read-only ledger peek; `first_seen` missing;
   `not_observed`; hold; eligible; next check at/after eligibility; Flow
   predecessor; queue position; lock; WP-Cron disabled/overdue; future
   `first_seen` guard; target change/hash.
5. **Native override.** Plugin row, theme row/card, update-core three
   locations, network admin; selectors; text replacement; fallback; no
   interference with other plugins; localized map; no N+1.
6. **Remove the blue notice.** Delete the Free notice; add the one-line
   WP-Cron hint only when needed; update docs.
7. **State display.** Waiting, held, queued, locked, not_observed,
   not_managed, normal countdown, WP-Cron problem; update-core summary.
8. **Contracts.** Provide the Schedule facts to Flow/Logger; consume the Queue
   pass fields and the Version Lock `locked` state; mark the Flow waiting
   contract as forward-compatible until plan 5.
9. **Tests.** Unit calculator, JS DOM, integration cron/settings, E2E,
   multisite, no-network.
10. **Docs.** Update the analysis/contract docs if implementation reveals a
    mismatch; do not rewrite settled decisions.

## Edge cases the plan must map

Use the 30-row matrix in `04-update-schedule.md` §8 and the 25 blocking cases in
§9. At minimum:

- new offer with no ledger entry; offer target changes; hold 0; hold 365;
- hourly/twicedaily/daily/weekly/default; DST; timezone change; future
  `first_seen`; clock skew;
- WP-Cron disabled/overdue; manual check; manual update; auto-update off;
- core security/minor/major; core major auto-updates off;
- Flow waiting; queue position; version locked; multisite; network admin;
- translation offers; large plugin list; DOM changes; other plugins’ rows;
- update-core core message missing; theme global template; i18n/a11y.

## Contracts and overlaps

The task file must include:

- **Provides to Flow:** `next_check`, per-offer hold, first-seen, eligible time,
  display states.
- **Provides to Logger:** the same state/reason/time facts; no log write from
  the display.
- **Needs from Queue:** `pass_id`, `started_at`, order, current item, per-item
  state, next follow-up.
- **Needs from Version Lock:** `locked` state and manager label.
- **Needs from Flow:** predecessor/waiting state (forward-compatible until plan
  5).
- **Do not touch:** `class-updatronix-flow-planner.php`,
  `class-updatronix-update-kernel.php`,
  `class-updatronix-queue-scheduler.php`, `class-updatronix-logger.php`,
  `class-updatronix-database.php`, `class-updatronix-version-control-*`.
  Those belong to plans 1, 2, 4, and 5.

## Plan file

Write the task file to:

`updatronix/.agents/tasks/YYYY-MM-DD-feature-update-schedule-rework.md`

Frontmatter:

```yaml
---
type: feature
slug: update-schedule-rework
date: YYYY-MM-DD
uid: YYYYMMDD-<random>
status: planning
review_required: yes
risk: [ui, cron, i18n, a11y, multisite]
---
```

Include: Goal · Context · Tasks · Edge cases · Contracts and overlaps ·
Do-not-touch · Test gate · Rollback · Session checkpoint · Log · Feedback.

## Test gate

- Free `bin/harness test`, `integration`, `test:js`, `lint`, `build`.
- Pro `bin/harness test`, `integration`, `test:js`, `lint`, `build` for the
  read-only facts filter.
- `bin/harness counterfactual` or `mutation` on the changed lines.
- JS DOM fixtures for plugin row, theme row, update-core, network admin.
- Manual verification at 1280/960/560/390; keyboard/screen-reader; no
  horizontal overflow; no broken native rows.
- E2E only if the host supports it; otherwise record the gap.

## Stop condition

Plan only. Do not edit production code. Do not start Phase 4. After writing the
task file, return a ~10-line summary with:

- the calculator inputs/outputs;
- the native surfaces and selectors;
- the contracts it provides/needs;
- the blocking edge cases;
- the test gate;
- the rollback point;
- anything the synthesis must reconcile.

End with: “Ready to start, or would you like to adjust anything?”
