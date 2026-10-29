# Plan 04 — Logger

> Copy everything below the line into the Logger tab, after `/architect`.
> Plan only. Do not implement.

---

You are in **plan mode** for the **update logger**. The five analyses are
complete and all owner decisions are settled. Do not re-litigate a settled
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
3. Prior plans:
   - `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-version-lock-rework.md`
   - `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-update-queue-rework.md`
   - `updatronix/.agents/tasks/YYYY-MM-DD-feature-update-schedule-rework.md`
   If any is missing, stop and tell the owner which plan to finish first.
4. This feature’s QA and grilling notes:
   `updatronix/.agents/notes/2026-10-28-qa-logger.md`,
   `2026-10-28-grill-logger.md`.
5. Free code:
   - `inc/classes/class-updatronix-database.php`
   - `inc/classes/class-updatronix-logger.php`
   - `inc/classes/class-updatronix-update-logger.php`
   - `inc/classes/class-updatronix-updatelogstate.php`
   - `inc/classes/class-updatronix-security.php`
   - `inc/classes/class-updatronix-export.php`
   - `inc/classes/class-updatronix-export-query-builder.php`
   - `inc/classes/class-updatronix-export-request-schema.php`
   - `inc/classes/class-updatronix-export-body-builder.php`
   - `inc/classes/class-updatronix-external-log.php`
   - `inc/settings/options.php`
   - `assets/src/js/components/activityLog/ActivityLogPanel.js`
   - `assets/src/js/components/activityLog/logFilters.js`
   - `assets/src/js/components/activityLog/constants.js`
   - `assets/src/js/components/activityLog/LogDetailsContent.js`
   - `assets/src/js/components/activityLog/exportFormats.js`
6. Pro code:
   - `inc/update-engine/class-updatronix-update-journal.php`
   - `class-updatronix-update-kernel.php`
   - `class-updatronix-shield-enforcement.php`
   - `class-updatronix-queue-scheduler.php`
   - `inc/update-flow/class-updatronix-flow-runner.php`
   - `assets/src/js/components/UpdateFlowPage.js`
   - `assets/src/js/components/UpdateShieldPage.js`

Use `bin/harness graft ask "logger decision schema" --source`,
`bin/harness graft ask "update journal write points" --source`, and
`bin/harness graft callers Updatronix_Logger` before opening files.

## Position in the five-plan sequence

This is **plan 4 of 5**. Plans 1–3 must exist. Plan 5 (Flow) will consume this
plan’s schema and write points. You own the log schema, logger API, activity log
UI, export, external log, and the Pro write points. Do not plan the execution
behavior of Queue/Flow/Schedule or the Version Lock detector.

## Settled decisions to encode

These are the owner’s decisions from `05-logger.md` §15. Do not change them.

1. **Schema shape.** Explicit columns for the filterable dimensions:
   `reason_code`, `decision_state`, `origin`, `decision_stage`, `pass_id`,
   `chain_id`, `predecessor`, `next_eligible_at`, `attempt`, `network`,
   `target_version`, `retained`; plus a redacted JSON `context` for the long
   tail. Additive migration with defaults; old rows show “Legacy — reason not
   recorded”.
2. **Decision state.** A new `decision_state` column. Keep the six existing
   statuses (`success`, `error`, `cancelled`, `delayed`, `info`, `warning`) for
   backward compatibility. The UI renders the state badge first and falls back
   to status for legacy rows.
3. **Blocking evidence.** Pin the newest blocking row per
   `(site_id, item_type, item_id, target_version)` beyond retention, cap the
   pinned set at 500, mark `retained=1`.
4. **Event model.** One row per state transition; one actual-update row owned
   by the native logger; one pass summary row. No row on every tick when the
   state did not change.
5. **Native vs Pro.** The native logger writes the actual update; Pro writes
   decisions and enriches the native row through a shared context filter. Never
   two “updated” rows for one update.
6. **Taxonomy.** Keep the ten existing action types; add `pass` and `advisory`
   only; add `reason_code` and `decision_state` for the “why”. Keep the six
   statuses.
7. **Pass identity.** One `pass_id` for the scheduled automatic pass; no
   separate `run_id` unless the queue survives. If it does, `pass_id` is the
   run id for queue rows.
8. **Event key v2.** Include pass, reason, attempt, predecessor, stage,
   network, and `next_eligible_at`; the write point also checks the previous
   state so a later pass is not deduped away.
9. **UI/export.** Add Reason, State, Origin, Pass, Chain, and Next eligible
   filters and columns; make every filter server-side; keep Type/Action/Status/
   User/Day; add a Decision section to the details; extend CSV/JSON/XML and the
   text report additively; keep every old field and column.
10. **External log.** Extend the payload additively; keep
    `updatronix_after_log`; add `updatronix_log_updated` for enrichment.
11. **Multisite.** Execution-site `site_id`; `network` flag; `target_site_id`
    in context.
12. **Truthfulness.** One shared state fact for the screens and the logger;
    one test per screen state; fail on a missing row or a mismatch.
13. **Redaction.** No secrets, paths, package URLs, emails, tokens, or
    recipient addresses; redact all text/context; cap context at 8 KB.
14. **Retention.** Keep default retention for normal rows; pin blocking
    evidence; dedupe waiting rows by state; cap per pass.

## Plan scope

In scope:

- Additive schema migration, DB version, indexes, defaults, backfill,
  rollback.
- `log_event()` API alongside the existing `log()` signature; old callers
  unchanged.
- Reason/state/origin/stage enums and alias map; primary reason precedence:
  Version Lock → Shield compatibility → Schedule hold → Flow/queue readiness.
- Write points: native actual update, engine decision, Shield block, Version
  Lock block, Schedule hold, Flow waiting/permanent, canceled downstream, pass
  summary.
- Dedupe/event key v2; no duplicates; no per-tick rows; no fake digest row.
- Activity log UI filters/columns/details; server-side filters; total/export
  consistency.
- Export schema and status map; CSV/JSON/XML/text.
- External log and `updatronix_after_log` compatibility; `updatronix_log_updated`.
- Retention pinning, redaction, multisite, performance.
- Tests and docs.

Out of scope:

- The Queue pass implementation (plan 2).
- The Flow planner/readiness implementation (plan 5).
- The Schedule calculator/UI (plan 3).
- The Version Lock detector/UI (plan 1).

## Required plan tasks

At least:

1. **Schema.** Columns, types, defaults, indexes, DB version, `dbDelta`,
   migration, rollback, old-row display.
2. **API.** `log_event()` with the new fields; keep `log()` working; sanitize/
   allowlist; event key v2; context cap/redaction.
3. **Taxonomy.** Reason/state/origin/stage enums; alias map; precedence; UI
   labels; export labels.
4. **Write points.** Native update, engine decision, Shield, Version Lock,
   Schedule hold, Flow waiting/permanent, canceled downstream, pass summary.
   One row per transition; no duplicate actual update; no per-tick.
5. **Dedupe.** Event key, previous-state check, repeated wait counted in the
   pass summary, no fake rows.
6. **UI.** Filters/columns/details; server-side query; keep old filters;
   Reason/State/Origin/Pass/Chain/Next eligible; decision details.
7. **Export.** Additive fields, filters, status map, CSV/JSON/XML/text, totals.
8. **External log.** Additive payload; hook compatibility; new update hook.
9. **Retention.** Pin newest blocking row, cap 500, `retained=1`; no evidence
   loss; indexes.
10. **Multisite/redaction/performance.** Execution-site attribution; `network`;
    redact; indexes; per-pass cap; no N+1.
11. **Tests.** Unit mapping, integration schema/migration, JS filters, export
    contract, external log, truthfulness per screen state, retention, multisite.
12. **Docs.** Update the analysis/contract docs if implementation reveals a
    mismatch; do not rewrite settled decisions.

## Edge cases the plan must map

Use the 25-row matrix in `05-logger.md` §12 and the 18 blocking cases in §13. At
minimum:

- native update; engine update; Shield block; Version Lock block; Schedule hold;
  Flow wait; Flow predecessor blocked; Flow failure; queue retry; queue
  exhaustion; manual update; core security; translation; multisite; export;
  external log; retention; legacy row; duplicate native/engine row; event key
  collision; context overflow; redaction leak; UI state with no row; log row
  contradicting the UI; high-volume pass.

## Contracts and overlaps

The task file must include:

- **Provides to Flow:** the reason/state/stage vocabulary, pass id, waiting row,
  canceled row, pass summary row, event key v2.
- **Provides to Queue:** pass summary, item outcome row, native enrichment,
  no duplicate actual update.
- **Provides to Schedule:** no log write from the display; the state fact and
  reason codes.
- **Provides to Version Lock:** reason/manager/evidence fields; one block row.
- **Needs from all four:** their exact states/reasons and write points. If a
  name is not settled, propose one and mark it as a contract to confirm in the
  synthesis.
- **Do not touch:** `class-updatronix-flow-planner.php`,
  `class-updatronix-update-kernel.php`,
  `class-updatronix-queue-scheduler.php`,
  `class-updatronix-version-control-*`, `SchedulePanel.js`. Those belong to
  plans 1–3 and 5.

## Plan file

Write the task file to:

`updatronix/.agents/tasks/YYYY-MM-DD-feature-logger-rework.md`

Frontmatter:

```yaml
---
type: feature
slug: logger-rework
date: YYYY-MM-DD
uid: YYYYMMDD-<random>
status: planning
review_required: yes
risk: [sql, rest, export, multisite, compatibility]
---
```

Include: Goal · Context · Tasks · Edge cases · Contracts and overlaps ·
Do-not-touch · Test gate · Rollback · Session checkpoint · Log · Feedback.

## Test gate

- Free `bin/harness test`, `integration`, `test:js`, `lint`, `build`.
- Pro `bin/harness test`, `integration`, `test:js`, `lint`, `build` for the
  Pro write points.
- `bin/harness counterfactual` or `mutation` on the changed lines.
- Integration: schema migration, old-row display, event key dedupe, export
  contract, external log, retention pinning, multisite.
- JS: filters, columns, details, export summary.
- Truthfulness: one test per screen state (Shield blocked, Version Lock locked,
  Schedule held, Flow waiting, Queue failed) asserting a matching log row.
- Manual: activity log filters, export, external log, screen reader/keyboard.
- E2E only if the host supports it; otherwise record the gap.

## Stop condition

Plan only. Do not edit production code. Do not start Phase 4. After writing the
task file, return a ~10-line summary with:

- the schema and API shape;
- the write points and dedupe rules;
- the UI/export changes;
- the blocking edge cases;
- the test gate;
- the rollback point;
- anything the synthesis must reconcile.

End with: “Ready to start, or would you like to adjust anything?”
