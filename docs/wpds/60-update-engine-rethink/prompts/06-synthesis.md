# Prompt 06 — Cross-feature synthesis and implementation order

> Copy everything below the line into a fresh tab **after** the five analyses
> and the five plan-mode task files exist. This is a planning/synthesis task;
> do not implement.

---

You are the synthesis agent for the Updatronix update-engine rethink. The owner
is Quentin. The repo’s `AGENTS.md` requires US English replies and the
WordPress documentation style; follow it.

## Why this tab exists

Five analyses were run independently, then five plans were written in their
tabs. Each plan saw the earlier analyses but not the later plans. This tab
reconciles them before any code is written. Its job is to prevent two agents
from editing the same class, option, table, REST route, JS component, or test
in incompatible ways, and to put the work in a safe order.

## Inputs (all required)

Analyses:

- `docs/wpds/60-update-engine-rethink/analysis/01-version-lock.md`
- `docs/wpds/60-update-engine-rethink/analysis/02-update-queue.md`
- `docs/wpds/60-update-engine-rethink/analysis/03-update-flow.md`
- `docs/wpds/60-update-engine-rethink/analysis/04-update-schedule.md`
- `docs/wpds/60-update-engine-rethink/analysis/05-logger.md`

Plans (find the exact paths; the agents were told to use
`.agents/tasks/YYYY-MM-DD-<type>-<slug>.md`):

- the Version Lock plan in `updatronix-pro/.agents/tasks/`
- the Update Queue plan in `updatronix-pro/.agents/tasks/`
- the Update Flow plan in `updatronix-pro/.agents/tasks/`
- the Update Schedule plan in `updatronix/.agents/tasks/`
- the Logger plan in `updatronix/.agents/tasks/`

If an analysis or plan is missing, stop and tell the owner exactly which tab
must finish first. Do not invent a missing contract.

## Read first

1. `AGENTS.md`, `workflow.md`, and the plan template in the relevant repo.
2. The five analyses and the five plans.
3. `docs/wpds/60-update-engine-rethink/README.md` (the shared vocabulary and
   the seven cross-cutting decisions).
4. `docs/wpds/45-update-engine-architecture.md` §5 (principles, layers,
   policies, kernel, planner).
5. `docs/wpds/50-update-engine-4.4-flow.md` and
   `docs/wpds/52-update-flow-phase-5.2.md`.
6. The current code surfaces named by each plan. Use graft, not whole-file
   reads.

## Phase 1 — conflict matrix

Build one table per shared surface. At minimum:

| Shared surface | Version Lock | Queue | Flow | Schedule | Logger | Conflict? | Resolution |
|---|---|---|---|---|---|---|---|
| Item identity (`type:id`) | | | | | | | |
| Installed/target version reader | | | | | | | |
| Pending-offer reader | | | | | | | |
| Detection result (VCS/Composer) | | | | | | | |
| Policy chain / reason codes | | | | | | | |
| Pass/run id | | | | | | | |
| State machine vocabulary | | | | | | | |
| Next-eligible calculation | | | | | | | |
| Native `auto_update_*` filters | | | | | | | |
| Native `run()` gate | | | | | | | |
| Queue table/options | | | | | | | |
| Flow chains/planner | | | | | | | |
| Flow run summary | | | | | | | |
| Shield settings | | | | | | | |
| Schedule settings | | | | | | | |
| Log table/schema | | | | | | | |
| Activity log UI | | | | | | | |
| Export schema | | | | | | | |
| External log payload | | | | | | | |
| REST routes | | | | | | | |
| Admin JS components | | | | | | | |
| Cron hooks | | | | | | | |
| Multisite behavior | | | | | | | |
| Uninstall/cleanup | | | | | | | |

For each conflict, choose one owner and one contract. Do not leave “both
implement it” or “decide later”. If the plans disagree on a public shape, the
synthesis decides it and records why.

## Phase 2 — dead code and migration inventory

List what the combined design **deletes or replaces**, and in which order:

- the custom queue table, queue rows, options, transients, cron hooks, REST
  routes, queue summary, Health checks, manual retry/cancel/run actions;
- the Flow `Run now` button, route, cooldown, polling if not read-only;
- the custom digest notifier if native per-item email replaces it;
- the duplicate journal rows if the native logger becomes the actual-update
  source;
- the big blue delay notice if the native override replaces it;
- the old detector’s provenance-blind path if the new detector replaces it;
- old log action types that become aliases.

For each deletion, state:

- the migration/cleanup for existing sites (options, table, cron, rows);
- the compatibility window (do old rows still render?);
- the rollback plan (can the old behavior be restored without data loss?);
- the tests that prove the deletion does not leave a dead path.

## Phase 3 — implementation order

The owner’s rule: **Flow is implemented last**, after the other features are
changed. Within that rule, propose the safest order and justify it.

A likely order to validate:

1. **Version Lock** — independent; removes items from the update set; its
   detector and UI are prerequisites for truthful Queue/Flow/Schedule states.
2. **Update Queue** — the execution model; decides whether the custom kernel
   survives, whether native `run()` is the writer, and what the pass id is.
3. **Update Schedule** — the time calculator and native display; depends on
   the queue pass model and the Flow waiting states.
4. **Logger** — the structured evidence; depends on the final vocabulary and
   write points.
5. **Flow** — last; depends on the queue gate, the Schedule hold, the Version
   Lock state, and the Logger reason codes.

If a different order is safer, say so and explain the dependency that forces
it. Do not put Flow before the execution model.

For each step, define:

- the exact files/classes/options/routes/tests it owns;
- the files it must not touch (owned by another step);
- the entry/exit criteria;
- the test gate (`bin/harness test`, `integration`, `test:js`, `lint`,
  `build`, `counterfactual`/`mutation` as appropriate);
- the manual verification on the native screens;
- the rollback point.

## Phase 4 — shared contracts

Write the contracts the implementation must share. At minimum:

1. **Item identity and offer shape.** One function/class answers: installed
   version, pending offer, target, item name, type, id.
2. **Detection result.** `native_vcs`, `composer_managed`, `neither`,
   `unknown`; manager label; evidence path; cache key; invalidation events.
3. **Decision result.** `allow`, `deny`, `defer`, `wait`, `skip`; reason code;
   human message; next eligible time; policy id; severity.
4. **Pass/run identity.** `pass_id`, origin (`automatic`, `flow`, `manual`,
   `cli`), started/finished, cursor, counts.
5. **State machine.** The exact states and transitions, shared by Queue, Flow,
   Schedule, and Logger.
6. **Next-eligible calculation.** Inputs, outputs, timezone, fallbacks.
7. **Reason taxonomy.** The exact codes and their UI labels; no synonyms.
8. **Log write points and dedupe keys.** Who writes what, once.
9. **Native gate.** How one item is allowed per native run; how the other
   items are denied without being marked failed; how the next run is
   scheduled; how the native lock is respected.
10. **Manual-update boundary.** Version Lock blocks manual updates for
    managed items; Flow ordering does not; WP-CLI is a documented exception
    unless the owner says otherwise.

## Phase 5 — risk register and owner decisions

Produce a risk table:

| Risk | Likelihood | Impact | Detection | Mitigation | Owner decision? |
|---|---|---|---|---|---|
| Native `run()` cannot be limited to one item without false failures | | | | | |
| Per-item native email is impossible without a custom mail | | | | | |
| Flow waits forever on a locked/uninstalled predecessor | | | | | |
| Schedule display cannot be exact for queued items | | | | | |
| Detector false positives lock normal plugins | | | | | |
| Schema migration breaks export/external log | | | | | |
| Deleting the queue table loses operational history | | | | | |
| Multisite attribution is wrong | | | | | |
| Cron overlap/race | | | | | |
| Disk-full/fatal error recovery | | | | | |

For every risk that needs an owner choice, write `OPEN — owner decision` and
put it in a single list at the end. Do not hide a decision inside a task.

## Phase 6 — deliverable

Write `docs/wpds/60-update-engine-rethink/analysis/06-synthesis.md` with:

1. Executive summary (one page).
2. The conflict matrix and resolutions.
3. The dead-code/migration inventory.
4. The implementation order with entry/exit criteria and test gates.
5. The shared contracts (item, detection, decision, pass, state machine,
   next-eligible, reasons, log write points, native gate, manual boundary).
6. The risk register.
7. The owner-decision list.
8. The final “go / no-go” recommendation: which plans are ready, which need a
   re-plan, and what must be true before the first implementation tab starts.

Also produce a short `docs/wpds/60-update-engine-rethink/implementation-order.md`
that the owner can follow tab by tab:

```
Step 1 — Version Lock — repo — task file — gate
Step 2 — Queue — repo — task file — gate
...
```

## Stop condition

Do not edit production code. Do not create implementation task files (the
plans already exist). Do not start the first step. End with:

> Synthesis complete. Implementation may start at Step 1 only after the owner
> approves this order and resolves the OPEN decisions.

and list the blocking owner decisions.
