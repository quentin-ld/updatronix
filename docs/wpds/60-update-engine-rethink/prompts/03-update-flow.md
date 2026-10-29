# Prompt 03 — Update Flow

> Copy everything below the line into a fresh tab after `01-version-lock.md`
> and `02-update-queue.md` exist. Analysis only; do not implement. Flow is
> implemented last, after the other features are done.

---

You are the analysis agent for **Update Flow** in the Updatronix plugin family.
The owner is Quentin. The repo’s `AGENTS.md` requires US English replies and
the WordPress documentation style; follow it.

## Repos and commands

- Free plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix`
- Pro plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix-pro`
- Shared product docs: `wp-content/plugins/updatronix/docs/wpds`
- Every command goes through `bin/harness` in the repo you are in. Never call
  `php`, `composer`, `node`, `npm`, or `wp` directly.

## Read first

1. `AGENTS.md` and `workflow.md`.
2. The two required analyses:
   - `docs/wpds/60-update-engine-rethink/analysis/01-version-lock.md`
   - `docs/wpds/60-update-engine-rethink/analysis/02-update-queue.md`
   If either is missing, stop and tell the owner which tab to run first.
3. `docs/wpds/45-update-engine-architecture.md` §5.5, §5.8, §6–§8, §10–§12.
4. `docs/wpds/50-update-engine-4.4-flow.md` and
   `docs/wpds/52-update-flow-phase-5.2.md`.
5. `docs/wpds/23-update-flow-review.md`.
6. Pro code:
   - `inc/update-engine/class-updatronix-flow-planner.php`
   - `inc/update-flow/class-updatronix-update-flow.php`
   - `inc/update-flow/class-updatronix-flow-runner.php`
   - `inc/update-engine/class-updatronix-update-kernel.php` (enqueue, claim,
     dependency, cancel)
   - `inc/update-engine/class-updatronix-update-queue.php`
     (`dependencies_satisfied`, `cancel_downstream`, `cancel_flow_after`)
   - `inc/update-engine/class-updatronix-chain-dependency-policy.php`
   - `inc/update-engine/class-updatronix-delay-policy.php`
   - `inc/update-engine/class-updatronix-pending-offer-policy.php`
   - `inc/update-engine/class-updatronix-shield-enforcement.php`
   - `inc/update-engine/class-updatronix-policy-chain.php`
   - `assets/src/js/components/UpdateFlowPage.js`
   - `assets/src/js/flowRunState.js`
   - `inc/settings/options.php`
7. Free code:
   - `inc/classes/class-updatronix-autoupdatedelay.php`
   - `inc/classes/class-updatronix-cron.php`
   - `inc/classes/class-updatronix-update-logger.php`
8. Tests:
   - `tests/Unit/UpdateFlowPlannerTest.php`
   - `tests/Integration/UpdateFlowRunTest.php`
   - `tests/Integration/UpdateFlowRestTest.php`
   - `tests/Integration/UpdateQueueLiveFixtureTest.php`
   - `tests/js/flowRunState.test.js`

Use `bin/harness graft ask "flow planner topological order" --source`,
`bin/harness graft ask "flow runner active run" --source`,
`bin/harness graft grep "no_pending_offer"`, and
`bin/harness graft callers Updatronix_Flow_Planner` before opening files.

## What the owner expects

The owner’s words, translated and grouped:

1. **Remove the button and the manual Flow execution feature.** The owner
   never asked for “Run now”.
2. Flow applies to **automatic updates**. If automatic updates are pending
   (for example a payment gateway and WooCommerce) and a Flow exists for them,
   the payment gateway updates automatically **before** WooCommerce, rather
   than in the default order of arrival.
3. This is important and touchy. It interacts with Shield and Schedule.
4. Example: WooCommerce’s update is available on day 1, the payment gateway’s
   update is available on day 2, and Schedule has a one-day hold for automatic
   updates. WooCommerce must be **held** until the payment gateway update is
   done.
5. The owner wants every possible interaction and edge case listed, case by
   case, before any code. The owner will read the list. Blocking cases must be
   identified and a treatment proposed.
6. Flow is implemented **last**, after the other features are changed.

## The semantic problem to solve

The current planner only orders the offers that exist at plan time:

- If a step has no pending offer, it becomes `skipped` and the chain
  continues.
- If a step is delayed or Shield-blocked, it becomes `blocked` and pending
  rows after it are canceled.
- A failure cancels pending rows after its topological rank across the merged
  run, including independent chains.

That is not the owner’s model. The owner wants a downstream item to **wait**
until its predecessor is actually updated, and then continue on a later pass.
The analysis must define, precisely:

- when a predecessor is “done” (already at the latest version, successfully
  updated, skipped because no offer, or manually updated);
- when it is “not yet ready” (no offer yet, offer held by Schedule, offer
  blocked by Shield, version-locked, running, failed);
- when it is “never going to be ready” (not installed, not hosted on
  WordPress.org, auto-update forced off, version-locked with no manager path);
- what the downstream item does in each state: wait, skip, block, cancel, or
  notify;
- how the state is persisted and displayed if there is no queue table after
  the queue simplification.

**Important distinction the analysis must settle:** “no pending offer” can
mean “already at the latest version” or “the release is not out yet”. The
system cannot see the future. It can see whether the item is at the latest
version known to the update API. Define the rule:

- If the predecessor is at the latest known version, it is done; downstream
  may proceed.
- If the predecessor has a pending offer, even one held by Schedule, it is
  not done; downstream waits.
- If the predecessor’s update data is stale or its hosting is unknown, the
  system must not silently assume “done”; it must wait or warn.

Confirm this rule against the owner’s day-1/day-2 example. On day 2 the
gateway has a pending offer (held until day 3), so WooCommerce waits. On day 1
the gateway has no offer and is at the latest known version, so the rule would
let WooCommerce proceed; ask the owner whether that is acceptable, and what
should happen if the gateway’s release is simply not discovered yet.

## Phase 1 — current-state map

| Surface | Implementation (file:line) | Actual behavior | Native interaction | Owner match? |
|---|---|---|---|---|
| Chain storage/schema | | | | |
| Planner order/dedupe/limits | | | | |
| Missing offer | | | | |
| Item uninstalled | | | | |
| Policy denial | | | | |
| Chain dependency | | | | |
| Failure/cancel | | | | |
| Retry/backoff | | | | |
| Auto tick | | | | |
| Manual run/route/UI | | | | |
| Run summary/polling | | | | |
| Queue-off behavior | | | | |
| Schedule hold | | | | |
| Version Lock | | | | |
| Multisite | | | | |

Then write a **state machine** for one item: `no_offer`, `offer_pending`,
`offer_held`, `offer_eligible`, `waiting_for_predecessor`, `blocked`,
`locked`, `running`, `succeeded`, `failed`, `skipped`, `canceled`,
`already_latest`, `unknown`. For each transition, name the event that causes
it and the evidence used.

## Phase 2 — the exhaustive case matrix (the main deliverable)

Before grilling, produce the matrix. The owner must read it before any plan.
Do not collapse cases. If two cases differ in one variable, list both.

Columns:

| ID | Chain/graph | Item A state | Item B state | Schedule/hold | Shield/lock | Queue state | Expected behavior | Current behavior | Blocking? | Decision needed |
|---|---|---|---|---|---|---|---|---|---|---|

At minimum, cover:

### A. Basic availability

1. A and B both have offers, both eligible.
2. A has an offer, B has none.
3. B has an offer, A has none and A is at the latest known version.
4. B has an offer, A has none and A’s update data is stale/unknown.
5. Neither has an offer.
6. A is already at the target (manual update before the pass).
7. B is already at the target.
8. A’s offer disappears between plan and execution.
9. B’s offer disappears between plan and execution.
10. A’s target version changes between plan and execution.

### B. Schedule/hold

11. A eligible, B in hold.
12. A in hold, B eligible.
13. A and B both in hold, A expires first.
14. A and B both in hold, B expires first.
15. Hold is 0.
16. Hold is at the maximum.
17. A’s offer appears while B is already held by Flow.
18. The schedule recurrence changes while B is waiting.
19. The preferred time changes while B is waiting.
20. The next check is overdue.
21. WP-Cron is disabled.

### C. Shield and Version Lock

22. A is Shield-blocked, B eligible.
23. B is Shield-blocked, A eligible.
24. A is version-locked, B eligible.
25. B is version-locked, A eligible.
26. A is both incompatible and version-locked.
27. A is unknown to the version detector.
28. A is manually updated after being locked.

### D. Failures

29. A fails terminally, B pending.
30. A fails retryably, B pending.
31. A is uncertain, B pending.
32. B fails, A succeeded.
33. A succeeds, B fails.
34. A and B both fail.
35. A’s failure is a disk-full error.
36. A’s failure is a fatal error with rollback.
37. A’s failure leaves the native lock held.
38. A is canceled by the user/admin (if manual cancel still exists).

### E. Graph shape

39. A → B → C; A fails.
40. A → B → C; B has no offer but A and C do.
41. A → B and C → D; A fails; C/D independent.
42. A → B and A → C (shared predecessor); A succeeds.
43. A → B and C → B (multiple predecessors); one fails.
44. B appears in two chains with different predecessors.
45. The same item appears twice in one chain.
46. A cycle across two chains.
47. Core as the last step of a chain.
48. Core in one chain and a plugin in another.
49. A chain with 50+ steps; a graph with 20+ chains.
50. A chain item is uninstalled after the chain is saved.

### F. Pass and execution

51. Queue disabled, Flow enabled.
52. Queue enabled, Flow disabled.
53. Both enabled.
54. Both disabled.
55. One item per worker vs one item per scheduled pass.
56. A follow-up runs before the next scheduled check.
57. Two cron requests race.
58. A manual update runs during the pass.
59. WP-CLI updates an item during the pass.
60. The site is multisite; the chain is network-wide; the item exists on one site.
61. The license expires mid-pass.
62. The engine kill switch is flipped mid-pass.
63. A new offer appears mid-pass.
64. The update transient is stale when the pass starts.
65. The update API returns a 500 or an empty response.
66. The site is in maintenance mode.
67. A plugin is activated/deactivated between steps.
68. A theme is switched between steps.

### G. UI and manual action

69. The owner clicks “Run now” (current) — remove it.
70. The owner expects to see why B is waiting.
71. The run summary is polled while nothing is running.
72. The chain has unsaved edits while a pass starts.
73. The chain is disabled while a pass is running.
74. The chain is deleted while a pass is running.
75. The license is inactive and the Flow screen is opened.

The agent must add any case it discovers. For each row, mark **blocking** if
the current design cannot express the expected behavior without a new concept
(e.g. “waiting”), or if the behavior would loop, deadlock, update out of
order, or silently skip.

## Phase 3 — grilling

Invoke `grilling` at `.agents/skills/grilling/SKILL.md`. Ask the whole frontier
in one round, with recommendations, then wait. Write
`wp-content/plugins/updatronix-pro/.agents/notes/YYYY-MM-DD-grill-update-flow.md`.

Seed questions:

1. **Waiting vs skipping.** If A has no offer but is at the latest known
   version, may B proceed? If A has a pending offer held by Schedule, B waits.
   If A’s data is stale, B waits. Confirm.
2. **Predecessor definition.** All incoming predecessors in the merged graph,
   or only the immediate predecessor in the declaring chain?
   Recommendation: all incoming predecessors.
3. **Independent chains.** Does A’s failure block C → D? Recommendation: no;
   only descendants of A wait/fail.
4. **Waiting duration.** Wait until the predecessor is updated, until a
   timeout, or until the next scheduled check? What notification, and how
   often? Recommendation: wait until the predecessor is done; notify once when
   the wait starts and once when it becomes blocking (e.g. locked/uninstalled).
5. **Manual updates.** Does a manual update of B before A violate the flow?
   Block it, warn, or log only? Recommendation: log only; Flow governs
   automatic updates. Version Lock remains the manual lock.
6. **Native opt-in.** Does a Flow item need native auto-update enabled, or
   does Flow force it? Recommendation: Flow applies only to items already
   opted into automatic updates; a non-opted item is “not managed”, not
   silently forced.
7. **Core and translations.** Keep core-last? Do translations participate?
   Recommendation: keep core-last; translations follow the native pass and are
   not chain steps.
8. **Failure handling.** Does a failed step stop the chain permanently, retry
   on the next pass, or retry with backoff? Does a failed step block
   independent chains? Recommendation: retry on the next pass; descendants
   wait; independent chains continue.
9. **Pass boundary.** Does Flow run inside the same pass as the automatic
   queue, or on its own tick? If the queue is simplified to a one-item native
   gate, Flow should be the ordering function of that gate, not a second
   execution path. Confirm.
10. **Persistence/UI.** With no manual run and possibly no queue table, how do
    you show “B is waiting for A”? A derived state on the Flow page, a log
    row, a transient, or a read-only pass summary? Recommendation: derive the
    state each pass, persist a bounded pass summary, show it on the Flow page,
    and log transitions.
11. **Remove Run now.** Remove the button, route, cooldown, per-step polling,
    and manual resume? Keep a read-only last-pass summary? Recommendation:
    remove the action and the route; keep the last-pass summary and the
    per-step states.
12. **Limits.** Keep 20 chains / 50 steps? Raise/lower? Recommendation: keep,
    with filters.
13. **Multisite.** Network-wide chains, per-site execution, Super Admin only.
    Confirm.
14. **Blocking cases.** For each blocking row, does the owner want a hard stop,
    a wait, a notification, or a manual resolution?

## Phase 4 — QA

Invoke `qa` in audit mode. Focus on:

- out-of-order updates caused by a skipped predecessor;
- a chain that deadlocks forever on a locked/uninstalled predecessor;
- a failure that cancels independent chains;
- a chain that resumes from the wrong step after a crash;
- a manual run route that can be replayed or abused;
- a chain edited/deleted during a pass;
- a stale offer that makes Flow run the wrong target;
- a schedule hold that is bypassed by a follow-up;
- a Flow pass that races the native updater or a manual update;
- multisite cross-site data leaks;
- log/summary divergence (the UI says waiting, the log says blocked);
- an unbounded graph or a cycle that slips past the UI.

Write `.agents/notes/YYYY-MM-DD-qa-update-flow.md` with severity-ranked
findings and no fixes.

## Phase 5 — analysis deliverable

Write `docs/wpds/60-update-engine-rethink/analysis/03-update-flow.md`.
Required sections:

1. Scope and non-goals.
2. Current behavior table with `file:line`.
3. Native WordPress interaction.
4. Owner expectation and the day-1/day-2 gateway example.
5. Gap analysis.
6. State machine and readiness rules.
7. **The exhaustive case matrix** (all rows, including the blocking markers).
8. Blocking cases and proposed treatments.
9. Decisions settled by grilling.
10. Open questions (`OPEN — owner decision`).
11. Contracts with Version Lock, Queue, Schedule, and Logger.
12. Recommended architecture: one gate / one pass / one state machine, or a
    justified alternative. Describe how it plugs into the simplified queue.
13. Implementation constraints and test strategy.
14. Evidence.

## Review gate

Do not propose a code plan until the owner has read the case matrix and
answered the blocking rows. The analysis doc must end with:

> Owner review required: read §7 (case matrix) and §8 (blocking cases) before
> any plan. Flow implementation waits until Version Lock, Queue, and Schedule
> are implemented.

Then:

> Analysis complete; do not implement.

## Stop condition

Do not edit production code. Do not create a task file. Do not remove the Run
now button yet. Do not implement Flow.
