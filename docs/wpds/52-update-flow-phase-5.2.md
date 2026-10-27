# Update Flow — Phase 5.2 state of play

**Date:** 2026-10-27
**Screen:** Update Flow (`flow`, Pro)
**Contract:** `04-screen-contracts.md` §2; ADR `45-update-engine-architecture.md`
**Status:** Screen and backend audited after 4.4. The queue remains dormant;
Flow runs through the shared kernel and the Shield policy chain. No commit and
no version bump.

## Claims matrix

| Claim | Where | Code path | Proof | Verdict |
|---|---|---|---|---|
| “Enabled chains run on the regular update check, top to bottom, one step at a time.” | Chains card | `wp_maybe_auto_update` priority 1 → `Updatronix_Flow_Runner`; one step per worker run | `UpdateFlowRunTest`, kernel/lease tests | True |
| “Run now starts a chain immediately.” | Chain row | `POST /updratronix-pro/v1/flow/run`; 409/429 on conflict/cooldown | `UpdateFlowRestTest` | True |
| “Shield policies are checked on every step.” | Chains description | Flow enqueues through `Updatronix_Update_Kernel`; policy chain at enqueue and execution | `UpdateFlowRunTest::test_shield_block_stops_the_chain` | True |
| “All chains are checked together for circular requirements.” | Chains description | Planner merges the graph, validates cycles, deduplicates by item/target | `UpdateFlowPlannerTest` | True |
| “The update offers are older than six hours…” | Run warning | Offer freshness from the update transients | Existing offer readers; no false green | True |
| “Run in progress” / “Last run” | Run summary | `GET /updratronix-pro/v1/flow/runs/{run_id}` polling | `UpdateFlowRestTest`, `flowRunState.test.js` | True |
| Per-step Pending/Running/Succeeded/Failed/Skipped/Blocked/Canceled | Step rows | Run summary + `statusLabel()` | `flowRunState.test.js`, run integration | True |
| “No chains yet” | Empty state | Empty `chains` array; `New chain` action | Flow validation tests | True |
| “Add at least two steps” / loop warning | Editor | Server validation plus advisory `findLoop()` | `UpdateFlowValidationTest`, planner tests | True |
| “Requires an active license” | Inactive screen | Pro bootstrap + REST permission | `UpdateFlowRestTest::test_inactive_license_is_denied` | True |
| “One shared dependency check” | Page description | Planner above the kernel; one lock, one journal, one table | ADR + integration | True |

## Internal mechanics

| Layer | Path | Notes |
|---|---|---|
| Screen | `assets/src/js/components/UpdateFlowPage.js` | Chain list, editor, Run now, polling, per-step states, loop warning. |
| Pure helpers | `assets/src/js/flowRunState.js` | Badge intent, active run, run counts, sequence lookup, shortened run id. |
| REST | `inc/update-flow/class-updatronix-update-flow.php` + runner | Chains CRUD, `POST /flow/run`, `GET /flow/runs/{run_id}`, capability/license/Super Admin gates. |
| Planner | `inc/update-engine/class-updatronix-flow-planner.php` | Stable topological order, dedupe, limits, core-last, installed checks. |
| Kernel | `inc/update-engine/class-updatronix-update-kernel.php` | Claim/lease, native executor, retry/backoff, journal. |
| Policy | Shield chain | Mandatory on every Flow job; denial is terminal and cancels downstream. |
| Storage | `updratronix_pro_flow_chains` | `{version:1, chains:[…]}`; one-shot migration. |

## Test coverage

| Suite | Flow-specific evidence |
|---|---|
| JS | `flowRunState.test.js` (8) plus the shared 73 |
| Unit | `UpdateFlowPlannerTest` (7), `UpdateFlowValidationTest`, kernel/policy tests |
| Integration | `UpdateFlowRunTest` (7: order, Shield block, mid-chain failure, retry, cancel, expired lease, rerun denial), `UpdateFlowRestTest` (7: capability, subscriber, admin, inactive license, conflicts, no offers, 429, 404) |
| Full Pro | 348 unit / 1279 assertions, 120 integration / 1073 assertions (1 skip), 81 JS |

## Findings

- No P0/P1 defect found in the Flow screen after 4.4.
- The old inline runner is gone; the screen cannot invoke a second execution path.
- `findLoop()` is advisory UI feedback; the server planner remains authoritative.
- A run executes one step per worker request, so a long chain takes several
  requests; the UI polls and reads the bounded run summary.
- The queue switch and `queue_live` are unrelated to Flow; Flow runs while the
  automatic queue is off.

## Known gaps

- No staged host run with real cron; the local fixture is the live proof.
- No live Multisite pass.
- Full E2E is not claimed; `e2e --list` is the only browser evidence.
- The remaining 4.3c unit gaps stay Phase 6 debt.
- No fresh manual screen-reader pass.

## Exit

- Claims: true or explicitly qualified.
- P0/P1: none found.
- Tests: Pro 348 / 120 / 81; Free unchanged.
- Review/security: Flow keeps the kernel lock, journal, and Shield gate; no second runner.
- No version bump, no commit.
