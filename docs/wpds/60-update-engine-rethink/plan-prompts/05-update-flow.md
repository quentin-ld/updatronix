# Plan 05 — Update Flow

> Copy everything below the line into the Flow tab, after `/architect`.
> Plan only. Do not implement. Flow is implemented last.

---

You are in **plan mode** for **Update Flow**. The five analyses are complete and
all owner decisions are settled. Do not re-litigate a settled decision. If you
believe one is wrong, stop and tell the owner.

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
3. All four prior plans:
   - `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-version-lock-rework.md`
   - `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-update-queue-rework.md`
   - `updatronix/.agents/tasks/YYYY-MM-DD-feature-update-schedule-rework.md`
   - `updatronix/.agents/tasks/YYYY-MM-DD-feature-logger-rework.md`
   If any is missing, stop and tell the owner which plan to finish first.
4. This feature’s QA and grilling notes:
   `updatronix-pro/.agents/notes/2026-10-28-qa-update-flow.md`,
   `2026-10-28-grill-update-flow.md`.
5. Pro code:
   - `inc/update-engine/class-updatronix-flow-planner.php`
   - `inc/update-flow/class-updatronix-update-flow.php`
   - `inc/update-flow/class-updatronix-flow-runner.php`
   - `inc/update-engine/class-updatronix-update-kernel.php`
   - `class-updatronix-update-queue.php`
   - `class-updatronix-chain-dependency-policy.php`
   - `class-updatronix-delay-policy.php`
   - `class-updatronix-pending-offer-policy.php`
   - `class-updatronix-shield-enforcement.php`
   - `class-updatronix-policy-chain.php`
   - `assets/src/js/components/UpdateFlowPage.js`
   - `assets/src/js/flowRunState.js`
   - `inc/settings/options.php`
6. Free code:
   - `inc/classes/class-updatronix-autoupdatedelay.php`
   - `inc/classes/class-updatronix-cron.php`
   - `inc/classes/class-updatronix-update-logger.php`
7. Tests:
   - `tests/Unit/UpdateFlowPlannerTest.php`
   - `tests/Integration/UpdateFlowRunTest.php`
   - `tests/Integration/UpdateFlowRestTest.php`
   - `tests/Integration/UpdateQueueLiveFixtureTest.php`
   - `tests/js/flowRunState.test.js`

Use `bin/harness graft ask "flow planner readiness" --source`,
`bin/harness graft ask "flow runner manual run" --source`, and
`bin/harness graft callers Updatronix_Flow_Planner` before opening files.

## Position in the five-plan sequence

This is **plan 5 of 5**, and Flow is implemented **last**. Plans 1–4 must exist.
Flow is the ordering function of the pass; it is not a second execution path.
You own the planner, the Flow REST surface, the Flow UI, the removal of the
manual runner, and the Flow integration with the pass. Do not plan the Queue,
Schedule, Logger, or Version Lock implementation.

## Settled decisions to encode

These are the owner’s decisions from `03-update-flow.md` §9–§10. Do not change
them.

1. **One pass, one gate.** Flow builds the ordered nodes for chained items; the
   pass builder merges them with the native default order for the rest. No
   second executor, no separate run id.
2. **Readiness.** `already_latest` requires a **fresh** `checked` entry. Stale,
   absent, or unknown data is `unknown_offer`: the downstream waits and warns.
3. **Held predecessor.** A held offer is `offer_held`; the downstream waits; the
   eligible time is shown. No bypass, including a follow-up.
4. **Failures.** No in-pass retry loop. A failed node is skipped for the pass;
   the next pass retries once; descendants wait; independent branches continue.
5. **Permanent states.** `not_installed`, `not_managed`, `never_ready`, and a
   permanent Shield denial make the descendants `blocked_by_predecessor` for
   the pass, visible, one notification; a later pass re-evaluates.
6. **Graph.** All incoming predecessors count; only descendants wait/block;
   independent chains continue. Dedupe one node per item/target; keep all
   edges. Core last in its chain; no global core inversion.
7. **Native opt-in.** Flow applies only to items already in
   `auto_update_plugins`/`auto_update_themes`. A non-opted item is
   `not_managed`; Flow never forces the opt-in.
8. **Translations.** Not chain steps; native `run()` processes them last.
9. **Manual execution removed.** Remove `Run now`, `POST /flow/run`, the
   five-minute cooldown, manual resume, and manual-run polling. Keep the chain
   editor, per-step states, and a read-only last-pass summary.
10. **Manual updates.** Outside Flow. A manual update to the target makes the
    node done; another version is logged, not blocked. Version Lock remains the
    manual lock.
11. **Waiting notifications.** One log row when the wait starts, one when it
    becomes permanent; no email for a transient wait. Native per-item email
    remains the only update email.
12. **Multisite.** Network chains, main-site execution, Super Admin; a
    subsite-only item is `not_installed`.
13. **Chain edits.** Apply to the next pass. A deleted chain stops its pending
    nodes; the pass keeps a snapshot.
14. **Target changes.** Re-read the offer/target at execution. A target change
    re-plans the node; an offer withdrawal is a skip, not a success.
15. **Limits.** Keep 20 chains / 50 steps, filterable; reject cycles,
    duplicates, core not last, single-step chains; no silent truncation.

## Plan scope

In scope:

- Planner readiness: `already_latest` vs `unknown_offer`, held, blocked,
  permanent, failed, not installed, not managed, never ready.
- All incoming edges; descendants-only waiting/blocking; independent branches.
- Pass integration: ordered nodes, predecessors, waiting facts, ownership,
  state machine, pass snapshot, target re-read, offer withdrawal.
- Removal of `Run now`, `POST /flow/run`, cooldown, manual resume, manual-run
  polling, and the old runner/kernel Flow methods.
- Flow UI: chain editor, per-step states, waiting reason/predecessor/eligible
  time/manager, read-only last-pass summary; no new action.
- Logging: waiting-start/permanent rows, canceled downstream rows, pass
  summary, reason codes.
- Tests and docs.

Out of scope:

- The Queue pass implementation (plan 2).
- The Schedule calculator/UI (plan 3).
- The Logger schema/UI (plan 4).
- The Version Lock detector/UI (plan 1).

## Required plan tasks

At least:

1. **Readiness engine.** Fresh `checked` evidence; `already_latest`;
   `unknown_offer`; held; blocked; permanent; failed; not installed; not
   managed; never ready.
2. **Graph.** All incoming edges; descendants-only propagation; independent
   branches; dedupe; core last; limits.
3. **Pass integration.** Merge Flow order with native default; pass option
   fields; ownership of waiting nodes; snapshot; heartbeat; stale-pass recovery.
4. **Execution.** Re-read offer/target; target change re-plan; offer withdrawal
   skip; failure skip; next-pass retry; no in-pass backoff; independent
   branches continue.
5. **Removal.** Delete the Flow runner, route, cooldown, manual resume, manual
   polling, and the Flow-specific kernel/queue methods; keep the planner and
   policy chain.
6. **UI.** Chain editor; per-step states; waiting reason/predecessor/eligible/
   manager; read-only last-pass summary; no Run now; no manual-run polling.
7. **Logging.** Waiting start/permanent, canceled downstream, pass summary,
   reason codes, predecessor, chain id, pass id.
8. **Tests.** Planner readiness/edges; execution order/wait/failure/independent
   branches; REST removal; UI states; live fixture; multisite; no forced
   opt-in; manual update.
9. **Docs.** Update the analysis/contract docs if implementation reveals a
   mismatch; do not rewrite settled decisions.

## Edge cases the plan must map

Use the 75-row matrix in `03-update-flow.md` §7 and the 20 blocking cases in
§8. At minimum:

- A/B both offers; A only; B only; A at latest with fresh evidence; A with
  stale/absent/unknown data; neither;
- A already at target; B already at target; offer disappears; target changes;
- A held; B held; both held; hold 0/max; new offer while waiting; recurrence/
  time change; overdue; WP-Cron disabled;
- A Shield-blocked; B Shield-blocked; A/B version-locked; A unknown; A both
  incompatible and locked; manual update after lock;
- A fails/uncertain; B fails; both fail; disk-full; fatal; lock held; cancel;
- A→B→C; A→B and C→D; shared predecessor; multiple predecessors; duplicate;
  cycle; core last; core + plugin chains; limits; uninstalled item;
- queue off/on; both off; one item per worker; follow-up before next check;
  two cron requests; manual update during pass; WP-CLI; multisite; license/
  engine flip mid-pass; new offer mid-pass; stale transient; maintenance mode;
  activation/theme switch between steps; UI/manual action; unsaved edits;
  disabled/deleted chain.

## Contracts and overlaps

The task file must include:

- **Needs from Version Lock:** `locked`/`waiting_for_manager`, manager label,
  plan-time predicate.
- **Needs from Queue:** one pass, one gate, one order, pass option fields,
  ownership of waiting nodes, failure policy, pass summary.
- **Needs from Schedule:** hold, first-seen, eligible, next check, state facts.
- **Needs from Logger:** reason/state codes, waiting row, canceled row, pass
  summary, event key.
- **Provides to Queue:** ordered nodes, all edges, readiness/waiting facts.
- **Provides to Schedule:** waiting/predecessor/eligible facts.
- **Provides to Logger:** waiting/permanent/canceled/pass summary facts.
- **Do not touch:** the Version Lock detector/UI, the Queue pass
  implementation, the Schedule calculator/UI, the Logger schema/UI. Those
  belong to plans 1–4. If a contract is missing, stop and tell the owner; do
  not create a second implementation.

## Plan file

Write the task file to:

`updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-update-flow-rework.md`

Frontmatter:

```yaml
---
type: feature
slug: update-flow-rework
date: YYYY-MM-DD
uid: YYYYMMDD-<random>
status: planning
review_required: yes
risk: [rest, ui, cron, multisite, ordering]
---
```

Include: Goal · Context · Tasks · Edge cases · Contracts and overlaps ·
Do-not-touch · Test gate · Rollback · Session checkpoint · Log · Feedback.

## Test gate

- Pro `bin/harness test`, `integration`, `test:js`, `lint`, `build`.
- `bin/harness counterfactual` or `mutation` on the changed lines.
- Planner: fresh/stale/absent/unknown; all edges; descendants only; core last;
  dedupe; limits; cycles.
- Execution: wait/held/blocked/permanent/failure/target change/offer
  withdrawal; independent branches continue; no forced opt-in; manual update.
- REST: `POST /flow/run` is gone; `GET /flow/runs/{id}` is read-only or folded
  into the chains payload; capability/license/Super Admin gates preserved.
- UI: no Run now; per-step states; waiting reason/predecessor/eligible/manager;
  read-only last-pass summary; no manual-run polling.
- Live fixture: two local plugin zips, one step at a time, queue off, Shield
  block, duplicate worker; native lock; no second executor.
- Manual: chain editor, waiting state, deleted/edited chain, multisite.
- E2E only if the host supports it; otherwise record the gap.

## Implementation gate

Flow is implemented **last**. The task file must state:

- Implementation starts only after plans 1–4 are implemented and the synthesis
  order is approved.
- The Flow plan must not introduce a second execution path.
- If the pass contract from plan 2 is not implemented yet, stop; do not
  simulate it with the old runner.

## Stop condition

Plan only. Do not edit production code. Do not start Phase 4. After writing the
task file, return a ~10-line summary with:

- the readiness/graph model;
- the pass integration;
- the removal inventory;
- the UI states;
- the blocking edge cases;
- the test gate;
- the implementation dependency;
- anything the synthesis must reconcile.

End with: “Ready to start, or would you like to adjust anything?”
