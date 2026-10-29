# Update Flow — analysis (Update Shield)

**Date:** 2026-10-28
**Status:** Analysis only. No production code changed.
**Owner:** Quentin.
**Scope owner:** Pro (`updatronix-pro`), with Free interactions in the delay gate,
the cron schedule, and the logger.
**Prerequisites read:** `01-version-lock.md`, `02-update-queue.md`.
**Related:** `45-update-engine-architecture.md` §5.5, §5.8, §6–§8, §10–§12;
`50-update-engine-4.4-flow.md`; `52-update-flow-phase-5.2.md`;
`23-update-flow-review.md`.

---

## 1. Scope and non-goals

### In scope

1. The meaning of a Flow chain: an ordering constraint on **automatic** updates,
   not a manual execution feature.
2. The definition of “predecessor done”, “predecessor not ready”, and
   “predecessor never ready”.
3. The state machine for one chain item and the behavior of its downstream
   items in every state.
4. The interaction with Schedule (hold), Shield (compatibility and Safe Mode),
   Version Lock (manager-managed items), the native automatic updater, and the
   Free logger.
5. The removal of the manual Flow run: the button, the REST route, the
   cooldown, the manual resume, and the per-step polling of a manual run.
6. The persistence and display of a waiting state after the queue
   simplification recommended in `02-update-queue.md`.
7. The exhaustive case matrix and the blocking cases.

### Non-goals

1. Implementing Flow. The implementation waits until Version Lock, Queue, and
   Schedule are implemented.
2. Replacing the native updater. WordPress executes the write.
3. Changing the chain editor's visual design beyond removing the run action and
   adding a read-only last-pass summary.
4. Changing Version Lock, Queue, or Schedule decisions. This document consumes
   their settled decisions and records the contracts.
5. A staged or live run. The evidence is code reading, existing tests, and the
   existing run book.

---

## 2. Current behavior

### 2.1 Current-state map

Legend: “Match?” compares the current behavior with the owner's model in §4.
`Partial` means the happy path works but the state cannot express the owner's
requirement.

| Surface | Implementation (file:line) | Actual behavior | Native interaction | Match? |
|---|---|---|---|---|
| Chain storage and schema | `class-updatronix-update-flow.php:25`, `:51-79`, `:402-413`; `class-updatronix-update-flow.php:471-635` | Site option `updatronix_pro_flow_chains` (a network option on Multisite), v1 `{version, chains}`; validated on save and read; 20 chains, 50 steps, duplicate, core-last, cycle, installed-item checks | None | Partial |
| Planner order, dedupe, limits | `class-updatronix-flow-planner.php:101-102`, `:129-177`, `:219-268`, `:315-331`, `:333-451`, `:610-654` | One node per `type:id`; one edge per consecutive pair; stable Kahn topological sort; dedupe by item and target; `priority` is the topological rank; `sequence` is the declaring chain's position | None | Order: yes. Readiness: no |
| Missing offer | `class-updatronix-flow-planner.php:370-404`; `class-updatronix-update-plan.php:155-167` | No offer, empty target, or `target === installed` becomes a `skipped` fact with `no_pending_offer` or `already_installed`; no row; the chain continues | None | No |
| Item uninstalled | `class-updatronix-flow-planner.php:274-313`, `:341-367` | A deleted item becomes a `blocked` fact (`flow_item_unknown`) at its sequence; occurrences at or after it in the same chain are `canceled`; cross-chain occurrences are `canceled` | None | Partial |
| Policy denial | `class-updatronix-update-kernel.php:162-235`; `class-updatronix-flow-runner.php:386-430` | Enqueue evaluates the ordered chain; the first denial inserts a `blocked` row, stops the run, and calls `cancel_flow_after()` | Shield adapter and engine chain | No |
| Chain dependency | `class-updatronix-chain-dependency-policy.php:55-83`; `class-updatronix-update-queue.php:1143-1172` | A downstream row is claimable only when every earlier row in the **same chain and run** is `succeeded`; a missing earlier row means “skipped” and lets the row through | None | No |
| Failure and cancel | `class-updatronix-update-kernel.php:919-953`; `class-updatronix-update-queue.php:979-1008`, `:1014-1049` | A failed, uncertain, or blocked job calls `cancel_downstream()` for its chain and `cancel_flow_after()` for every higher-priority row in the merged run | None | No |
| Retry and backoff | `class-updatronix-update-kernel.php:352-421`, `:740-774`; `class-updatronix-update-job.php:34-44` | Retryable failures requeue at 5, 15, and 60 minutes up to `max_attempts`; after exhaustion the row is `failed`; the chain then cancels downstream | Native lock | Partial |
| Automatic tick | `class-updatronix-queue-scheduler.php:48-52`, `:89-133`, `:143-203` | `wp_maybe_auto_update` priority 1 plans Flow, enqueues all steps, and executes one step per worker; a follow-up drains the next step | Runs before `WP_Automatic_Updater::run()` at priority 10, so the plan can use stale transients | Partial |
| Manual run, route, UI | `class-updatronix-update-flow.php:118-130`, `:260-289`, `:327-342`; `UpdateFlowPage.js:309-340`, `:1225-1285` | `POST /flow/run` with a five-minute per-user/per-chain cooldown; a “Run now” button per chain; a manual run may resume terminal rows | The runner calls the queue kernel, not `run()` | No |
| Run summary and polling | `class-updatronix-flow-runner.php:233-301`, `:505-574`, `:663-697`; `UpdateFlowPage.js:267-303` | Bounded option `updatronix_pro_update_runs` (20 runs) plus live queue rows; `GET /flow/runs/{run_id}`; the UI polls every two seconds while pending/running | None | Partial |
| Queue-off behavior | `class-updatronix-queue-scheduler.php:101-115`; `class-updatronix-engine-policy.php:87-97` | Flow runs even when `queue_enabled` is false; the automatic queue is canceled with `queue_disabled`; Flow jobs use the same kernel and table | Flow is a second execution path beside the native pass | No |
| Schedule hold | `class-updatronix-autoupdatedelay.php:21`, `:96-105`, `:185-257`; `class-updatronix-delay-policy.php:68-112` | Free owns the ledger and the soak window; the engine delay policy calls Free's filter; a held step becomes `blocked` at enqueue and the chain stops | Free `auto_update_*` at priority 21 | No |
| Version Lock | `class-updatronix-version-control-policy.php:67-89`; `class-updatronix-policy-chain.php:36-42` | Native VCS is denied always; Composer is denied when `version_control_lock` is on; a denial is `blocked`; the policy is not gated by `safe_mode`; compatibility is evaluated before version control | Adapter chain for native items | Partial |
| Multisite | `class-updatronix-update-engine-bootstrap.php` availability; `class-updatronix-update-flow.php:183-199`; `class-updatronix-flow-planner.php:526-552` | Engine and pass run on the main site only; Flow REST requires Super Admin; chains are a site/network option; the planner uses the main site's installed items | `run()` is main-network/main-site only | Partial |
| Native opt-in | `class-updatronix-engine-policy.php:98-105`; `class-updatronix-update-executor.php:126-131` | The native opt-in check applies only to `automatic` origin; a Flow job bypasses it and the executor sets `autoupdate = true` for plugins and themes | Flow forces an item the owner did not opt into | No |
| Core and translations | `class-updatronix-flow-planner.php:219-233`; `class-updatronix-queue-scheduler.php:545-613` | Core is allowed only as the last step; translations are not chain steps; core is still subject to native `should_update()` | Core offer and native policy | Partial |
| Log and summary | `class-updatronix-update-journal.php:23-26`, `:38-90`, `:222-264`; `class-updatronix-update-logger.php:49-70` | One Free log row per queue transition; the event key hashes the queue marker, site, type, id, target, transition, and attempts; the run summary is a separate option; there is no `waiting` row | Free log table | No |

### 2.2 What the current code proves

1. The planner is deterministic and side-effect free except for reading the
   catalogue and the native transients.
2. The planner treats “no offer” as a **plan fact**, not as an item state. It
   cannot distinguish “already at the latest known version” from “the release
   is not discovered yet”, because `Updatronix_Update_Executor::get_pending_offer()`
   returns `null` for both (`class-updatronix-update-executor.php:293-337`).
3. The queue dependency is same-chain and sequence-based. It does not read the
   merged graph's incoming edges, and a missing earlier row is treated as a
   skipped step that lets the row through (`class-updatronix-update-queue.php:1143-1172`).
4. A denial or failure cancels rows by **topological rank across the merged
   run**, not by graph descendants (`class-updatronix-update-queue.php:979-1008`,
   `:1014-1049`; `class-updatronix-update-kernel.php:943-948`).
5. The active-run guard and the run summary depend on the queue table
   (`class-updatronix-flow-runner.php:205-222`; `class-updatronix-update-flow.php:230-238`).
   Deleting the table, as `02-update-queue.md` recommends, removes both.
6. The Flow origin bypasses the native opt-in check and the executor forces
   `autoupdate = true` (`class-updatronix-engine-policy.php:98-105`;
   `class-updatronix-update-executor.php:126-131`). Flow currently updates
   items the owner did not opt into.
7. The Flow plan is built at `wp_maybe_auto_update` priority 1, before
   `WP_Automatic_Updater::run()` refreshes the update transients
   (`class-updatronix-queue-scheduler.php:89-203`). The queue analysis
   recommends building the order at the first gate call, after discovery
   (`02-update-queue.md` §7.3).
8. The log event key does not include the run or pass id
   (`class-updatronix-update-journal.php:76-90`). A later run with the same
   target, transition, and attempt count can be deduplicated away.
9. Canceled downstream rows are updated in SQL but not journaled
   (`class-updatronix-update-kernel.php:518-539`;
   `class-updatronix-update-queue.php:1014-1049`). The summary can say
   `canceled` while the log has no row.
10. The run status derivation treats any `pending` step as `running`
    (`class-updatronix-flow-runner.php:663-697`). A waiting step would show as
    “running” forever.

---

## 3. Native WordPress interaction

### 3.1 The native automatic pass

WordPress runs automatic updates on `wp_maybe_auto_update`, which
`wp_version_check()` schedules. `WP_Automatic_Updater::run()`:

1. returns unless the request is the main network and main site
   (`class-wp-automatic-updater.php:644-648`);
2. acquires `auto_updater.lock` (`:651`);
3. refreshes the plugin, theme, and core transients (`:668`, `:688`, `:711`);
4. calls `should_update()` for each item; `should_update()` applies the
   `auto_update_{$type}` filter family;
5. updates plugins and themes, then core, then translations;
6. sends one email per `run()` containing the `update_results`;
7. fires `automatic_updates_complete` and releases the lock (`:786`).

### 3.2 Where Flow can attach

| Native point | Flow's use | Constraint |
|---|---|---|
| `wp_maybe_auto_update` priority 1 | Start the pass and build the order | Do **not** build the order here. `run()` refreshes discovery after this point. Build it at the first gate call. |
| `auto_update_plugin/theme/core` at `PHP_INT_MAX` | Select one item for this invocation; deny the others; deny every chain node that is not done, including waiting nodes | Never return `true`. The gate is a selection mechanism, not a policy. |
| `WP_Automatic_Updater::run()` | The only executor | Flow must not call `update()` or a raw upgrader. |
| `should_update()` | Native opt-in, VCS, `WP_AUTO_UPDATE_CORE`, and the native lock | Flow must pass through it; no forced `autoupdate = true`. |
| Native email and `automatic_updates_complete` | One item per invocation gives one email per item | Flow must not send its own email. |
| `upgrader_process_complete` | Free logger row | One row per item outcome. |
| `auto_updater.lock` | Serializes a manual update, WP-CLI, and the pass | Flow must not acquire a second lock. |

### 3.3 What native cannot do

1. It cannot see a predecessor relationship. The gate must express it.
2. It cannot wait. A denied item is simply not in this `run()`; the next
   `run()` re-evaluates.
3. It cannot distinguish “no offer because latest” from “no offer because not
   discovered”. The pass must read the `checked` map and `last_checked`.
4. It cannot update an item that is not opted in without a filter returning
   `true`. If Flow is restricted to opted-in items, no forcing is needed.
5. It cannot give a per-item “waiting” state. The pass must persist that state.

### 3.4 Manual updates

`update-core.php`, the plugin and theme update links, `update.php`, and WP-CLI
`wp plugin update` bypass the `auto_update_*` filters. Flow governs automatic
updates only. A manual update during a pass is detected by re-reading the
installed version; the pass marks the item done and continues.

---

## 4. Owner expectation

### 4.1 The owner's statements, restated

1. **Remove the manual action.** The owner never asked for “Run now”. Remove
   the button and the manual Flow execution feature.
2. **Flow orders automatic updates.** If automatic updates are pending for a
   chain (for example a payment gateway and WooCommerce), the gateway updates
   automatically before WooCommerce, rather than in arrival order.
3. **A predecessor must actually be done.** A downstream item waits until its
   predecessor is updated, then continues on a later pass.
4. **A held offer is not done.** If the predecessor has a pending offer held by
   Schedule, the downstream waits.
5. **No silent assumption.** If the predecessor's update data is stale or its
   hosting is unknown, the system must not assume “done”.
6. **Every case is listed before code.** The owner reads the matrix and the
   blocking cases.
7. **Flow is implemented last.** After Version Lock, Queue, and Schedule.
8. **Shield and Schedule are touchy.** The day-1/day-2 gateway example must
   hold.

### 4.2 Match matrix

| # | Owner expectation | Verdict | Evidence |
|---|---|---|---|
| 1 | Remove Run now | **Does not match** | `POST /flow/run` (`class-updatronix-update-flow.php:118-130`), the cooldown (`:327-342`), the button (`UpdateFlowPage.js:1225-1285`), and manual resume (`class-updatronix-flow-runner.php:432-453`). |
| 2 | Gateway before WooCommerce | **Partial** | The planner orders a chain topologically, but a missing offer skips the step and lets the successor run (`class-updatronix-flow-planner.php:374-404`). |
| 3 | Downstream waits for a real update | **Does not match** | A held or blocked step cancels downstream rows (`class-updatronix-flow-runner.php:394-430`); a failure cancels by topological rank (`class-updatronix-update-kernel.php:943-948`). |
| 4 | Held offer blocks downstream | **Does not match** | The held step becomes `blocked`; `cancel_flow_after()` cancels every higher-priority row. |
| 5 | Stale or unknown data is not “done” | **Does not match** | `get_pending_offer()` returns `null` for both “latest” and “unknown”; the planner skips. |
| 6 | Exhaustive matrix first | **This document** | §7 and §8. |
| 7 | Flow last | **Scheduled** | The synthesis prompt owns the implementation order. |
| 8 | Day-1/day-2 example holds | **Does not match** | See §4.3. |

### 4.3 The day-1/day-2 example, walked through

Assumptions: WooCommerce has an offer on day 1; the payment gateway has an
offer on day 2; Schedule holds every automatic offer for one day; the chain is
`gateway → WooCommerce`; the automatic check runs at 03:00.

**Day 1, 03:00.** WooCommerce has an offer. The gateway has no offer. If the
gateway is a WordPress.org item, it is installed and present in the native
`checked` map, so it is `already_latest` and done; WooCommerce may proceed.
If the gateway is premium or self-hosted and absent from `checked`, it is
`unknown_offer` and WooCommerce waits. WooCommerce's own one-day hold starts on
day 1, so under either reading it is not eligible until day 2. The hold makes
the day-1 case moot **if WooCommerce's hold is at least one day**.

**Day 2, 03:00.** WooCommerce's hold expires. The gateway's offer appears and
its own one-day hold starts, so the gateway is eligible on day 3. The gateway
is `offer_held`, not done. WooCommerce must wait. The current code instead
marks the gateway `blocked` and cancels WooCommerce
(`class-updatronix-flow-runner.php:394-430`). That is the blocking behavior the
owner wants changed.

**Day 3, 03:00.** The gateway is eligible; the pass updates it. The follow-up
continues the same pass, re-evaluates WooCommerce, and updates it. The current
code never reaches this point because the day-2 run canceled WooCommerce.

**The day-1 case, settled.** If WooCommerce's hold is 0, or its hold expires
before the gateway's offer appears, the owner's fresh-evidence answer governs:

- If the gateway is listed in the native `checked` map and the data is fresh,
  the gateway is `already_latest`, so WooCommerce may update on day 1. This is
  the discovery-window risk the owner accepted.
- If the gateway is premium or self-hosted and absent from `checked`, the
  gateway is `unknown_offer`, so WooCommerce waits. This is stricter than the
  letter of the day-1 example, and it matches the owner's “hold WooCommerce”
  intent.
- A stale transient or an API error always makes the gateway
  `unknown_offer`, so WooCommerce waits.

The settled decision is §9 S3/S13 and §10 OPEN-1.

**The precise evidence rule.** “At the latest known version” must be proven,
not inferred from the absence of an offer:

| Evidence | Meaning | Readiness |
|---|---|---|
| `update_plugins->response[$id]` or `update_themes->response[$slug]` exists | A newer version is offered. | `offer_pending` |
| No response entry, `checked[$id]` exists, `last_checked` is fresh, `checked[$id] === installed` | The update API knows the item and reports the installed version as current. | `already_latest` (done) |
| No response entry, the item is absent from `checked`, or `last_checked` is stale, or the installed version is unreadable | The system does not know whether a release exists. | `unknown_offer` (wait, warn) |
| The item is not installed | No installed root. | `not_installed` (never ready) |
| The item is version-locked or not opted in | No automatic path. | `locked` / `not_managed` (never ready until the state changes) |

The current planner only reads `response`. It never reads `checked` or
`last_checked` for the decision (`class-updatronix-flow-planner.php:370-404`;
`class-updatronix-update-executor.php:293-337`). The rule above is the core
change.

---

## 5. Gap analysis

| # | Gap | Impact | Evidence |
|---|---|---|---|
| G1 | “No offer” conflates done, not-yet-released, stale, and unknown hosting | A downstream item updates out of order, or a predecessor waits forever with no reason. | `class-updatronix-flow-planner.php:374-404`; `class-updatronix-update-executor.php:293-337`. |
| G2 | The planner has no `waiting` state | The only outcomes are a job, a skip, a block, or a cancel. The owner's model cannot be expressed. | `class-updatronix-update-plan.php:41-76`. |
| G3 | The chain dependency is same-chain and sequence-based | Multi-predecessor graphs and shared nodes update out of order. | `class-updatronix-update-queue.php:1143-1172`; `class-updatronix-flow-planner.php:338-347`. |
| G4 | A failure or block cancels by topological rank, not by descendants | Independent chains stop. | `class-updatronix-update-kernel.php:943-948`; `class-updatronix-update-queue.php:979-1008`. |
| G5 | A held or blocked predecessor cancels downstream | The day-2 gateway example fails. | `class-updatronix-flow-runner.php:394-430`. |
| G6 | Flow is a second execution path | It runs while the queue is off, uses the queue table, and bypasses the native opt-in. | `class-updatronix-queue-scheduler.php:101-115`; `class-updatronix-engine-policy.php:87-105`. |
| G7 | Manual run was never requested | The button, route, cooldown, resume, and polling add surface and abuse paths. | `class-updatronix-update-flow.php:118-130`, `:327-342`; `UpdateFlowPage.js:1225-1285`. |
| G8 | The waiting state has no persistence or display | After the queue table is removed, there is nowhere to show “B waits for A”. | `class-updatronix-flow-runner.php:205-301`. |
| G9 | The ownership handshake is queue-row based | A waiting node with no row is not owned; the native run can update it out of order. | `class-updatronix-shield-ownership.php:159-190`. |
| G10 | The log key omits the pass id and canceled rows are not journaled | The log and the summary diverge. | `class-updatronix-update-journal.php:76-90`; `class-updatronix-update-kernel.php:518-539`. |
| G11 | Version Lock is not gated by `safe_mode` and is evaluated after compatibility | The owner's precedence and master-switch decisions are not honored. | `class-updatronix-policy-chain.php:36-42`; `class-updatronix-version-control-policy.php:67-89`. |
| G12 | The plan is built before discovery | A stale offer can make Flow run the wrong target or skip a real one. | `class-updatronix-queue-scheduler.php:89-203`. |
| G13 | The chain reference survives item removal without a visible next action | A deleted item blocks the chain; the owner sees `flow_item_unknown` only in the run summary. | `class-updatronix-flow-planner.php:274-313`. |
| G14 | The Flow UI has no reason column | The owner cannot see why B is waiting. | `UpdateFlowPage.js:783-844`; `flowRunState.js:23-25`. |
| G15 | Multisite chains are network-wide but the planner uses the main site's catalogue | A subsite-only item is “unknown” and blocks the chain. | `class-updatronix-flow-planner.php:526-552`. |

---

## 6. State machine and readiness rules

### 6.1 One item, the item states

The vocabulary is shared with Schedule and Logger. The prompt's names map as
follows: `unknown` is `unknown_offer`; `blocked` is the umbrella for
`offer_blocked`, `not_managed`, `not_installed`, and `never_ready`; the
blocking case `B-n` names the specific one. “Terminal for this pass” means the
pass does not try again; a later pass re-evaluates.

| State | Meaning | Evidence | Terminal for this pass? |
|---|---|---|---|
| `no_offer` | No pending offer in the native transient. This is a resolver input, not a decision. | `get_pending_offer()` returns `null`. | No |
| `already_latest` | Installed version equals the latest version known to the update API, and the data is fresh. | No response entry; `checked[$id]` exists; `last_checked` is fresh; `checked[$id] === installed`. | Yes (done) |
| `unknown_offer` (`unknown`) | No offer, but the data is stale or absent, the item is absent from `checked`, or the installed version is unreadable. | `last_checked` older than the TTL or missing; `checked` has no entry; installed version `''`. | No (wait, warn) |
| `offer_pending` | A newer version is offered; the resolver has not yet established eligibility. | `response[$id]` exists; target differs from installed. | No |
| `offer_eligible` | The offer is pending, the hold has expired, the ordered policies allow it, and every incoming predecessor is done. | `response[$id]`; Free delay `true`; policy chain `allowed`; predecessor states `done`. | No (may execute) |
| `offer_held` | The offer is inside Free's soak window. | `Updatronix_AutoUpdateDelay::filter_*()` returns `false`; Free's ledger has `first_seen`. | No (wait) |
| `offer_blocked` | Shield compatibility or another blocking policy denies the offer. | First denial in the ordered chain; code `incompatible`, `php_incompatible`, `wp_incompatible`, or a native-guard/environment denial. Version-control denials map to `locked`; a changed target maps to `offer_pending` with a new target. | No (block this pass) |
| `locked` | Version Lock manages the item; no automatic path. | Version Lock decision `allowed = false` with `manager`, `scope`, `reason`. | No (wait for manager) |
| `not_managed` | The item is not opted into native automatic updates. | Not in `auto_update_plugins` / `auto_update_themes`; core policy denies. | No (wait or block) |
| `not_installed` | The item root no longer resolves. | Catalogue check fails. | Never |
| `never_ready` | The item cannot update automatically: not hosted where the API can see it, automatic updates forced off, or no manager path. | `AUTOMATIC_UPDATER_DISABLED`, `DISALLOW_FILE_MODS`, `WP_AUTO_UPDATE_CORE === false`, absence from every known source, or a permanent VCS block. | Never |
| `waiting_for_predecessor` | The item itself is ready or resolved, but at least one incoming predecessor is not done. | Incoming edges; predecessor state not `done`. | No |
| `running` | Claimed and executing. | Pass state / lease. | No |
| `succeeded` | The target version is installed. | Installed version equals the target. | Yes |
| `failed` | The attempt failed; the installed version is unchanged. | Result code; installed version equals the from-version. | Yes for this pass |
| `uncertain` | Files changed but the installed version is neither the source nor the target. | Result code `partial_state`. | Yes; manual review |
| `skipped` | A plan fact: the item is done or not applicable for this pass. It is **only** valid when the state is `already_latest` or the item is not in the pass. | — | Yes |
| `canceled` | The chain was disabled or deleted, or the pass was abandoned. It is **not** a state for a transient predecessor. | — | Yes |

`blocked` is the owner-facing umbrella: `offer_blocked`, `locked`,
`not_managed`, `not_installed`, and `never_ready` are its reasons.
`failed` is not the same as `blocked`: a failed item may succeed on the next
pass; a blocked item needs a policy, a manager, or a configuration change.

### 6.2 The readiness rules

**Rule R1 — A pending offer is never done.** If `response[$id]` exists, the
item is not done, even when the offer is held by Schedule. The only exception
is when the installed version already equals the offered target (a manual
update happened before the pass).

**Rule R2 — “Latest known” requires fresh `checked` evidence.** No response
entry is `already_latest` only when the item appears in the native `checked`
map, `last_checked` is within the TTL, and `checked[$id] === installed`. This
is the only “done without an update” state. Core uses the same rule with its
own evidence: `get_core_updates()` has no upgrade offer and
`update_core->last_checked` is fresh.

**Rule R3 — Unknown is not done.** A stale transient, a missing `last_checked`,
an item absent from `checked`, an unreadable installed version, or an update
API error is `unknown_offer`. The downstream waits and the UI says the data is
stale. The system never assumes done.

**Consequence for premium and self-hosted items.** A plugin or theme that the
WordPress.org API does not list is absent from `checked`. When its own updater
has no offer, there is no “latest known” evidence, so the item is
`unknown_offer` and its successors wait. This is the price of the owner's
fresh-evidence answer. The chain editor must say so when a premium item is used
as a predecessor, and the owner can keep it out of the predecessor position or
accept the wait. The alternative (optimistic) was rejected because it silently
updates out of order.


**Rule R4 — Installed is not the same as updated.** A manual update, a WP-CLI
update, or a previous pass that installed the target is done because the
installed version equals the target. A manual update to a version that is not
the target is not done.

**Rule R5 — A locked item is not done.** It is `locked`; the downstream waits
for the manager. A manager update to the target makes it done.

**Rule R6 — A Shield denial is not done.** It is `offer_blocked`; the
downstream waits or blocks, with the denial reason.

**Rule R7 — A failed item is not done.** It is `failed` for this pass; the
downstream waits; the next pass re-evaluates.

**Rule R8 — A non-opted item is not managed.** It is `not_managed`; the
downstream waits or blocks. Flow does not force the native opt-in.

**Rule R9 — A removed item is never ready.** It is `not_installed`; the
downstream blocks for this pass, with a visible reason.

**Rule R10 — All incoming predecessors count.** The dependency set is every
incoming edge in the merged graph, not only the immediate predecessor in the
declaring chain. A shared node waits for every predecessor.

**Rule R11 — No timeout.** A wait lasts until the predecessor's state changes
or the owner edits the chain. The next scheduled check re-evaluates it. A
permanent state blocks the pass with a visible reason; it does not cancel the
chain and does not spin.

### 6.3 Transitions and events

| From | Event | To | Evidence used |
|---|---|---|---|
| `no_offer` | `checked[$id] === installed`, data fresh | `already_latest` | `checked`, `last_checked` |
| `no_offer` | Data stale, missing, or item absent from `checked` | `unknown_offer` | `last_checked`, `checked` |
| `no_offer` | A response entry appears | `offer_pending` | `response[$id]` |
| `offer_pending` | Free delay returns `false` | `offer_held` | Free ledger, `first_seen` |
| `offer_held` | `now >= first_seen + delay` and the policies allow | `offer_eligible` | Free ledger, policy result |
| `offer_pending` | The offer's target differs from the stored target | `offer_pending` (re-plan to the new target) | `response[$id]`, installed version |
| `offer_pending` | First blocking policy denies | `offer_blocked` or `locked` | Ordered policy result |
| `offer_blocked` | The policy passes on a later evaluation | `offer_pending` | Ordered policy result |
| `locked` | The manager installs a new version | `already_latest` or `offer_pending` | Installed version, response |
| `offer_pending` | A predecessor is not done | `waiting_for_predecessor` | Incoming edges, predecessor state |
| `waiting_for_predecessor` | Every predecessor is done, the hold expired, and the policies allow | `offer_eligible` | Incoming edges, Free ledger, policy result |
| `offer_eligible` | A later re-evaluation sees a hold or a denial | `offer_held` or `offer_blocked` | Free ledger, policy result |
| `offer_eligible` | The gate selects the item and native `run()` starts it | `running` | Pass state |
| `running` | Installed version equals the target | `succeeded` | Installed version |
| `running` | Installed version equals the from-version | `failed` | Installed version, result code |
| `running` | Installed version is neither | `uncertain` | Installed version |
| `failed` | A later pass re-evaluates and the offer remains | `offer_pending` | Response, installed version |
| `any` | The item root disappears | `not_installed` | Catalogue |
| `any` | The chain is disabled or deleted | `canceled` | Chain snapshot |

### 6.4 What the downstream does in each state

| Predecessor state | Downstream action | Persisted | Display | Notification |
|---|---|---|---|---|
| `already_latest` | Proceed | Done fact | “Already at the latest version” | None |
| `succeeded` | Proceed | Done fact | “Updated” | Native per-item email |
| `unknown_offer` | Wait | `waiting_for_predecessor`, reason `unknown_offer` | “Waiting for A: update data is stale” | Once when the wait starts |
| `offer_pending` / `offer_eligible` / `offer_held` | Wait | `waiting_for_predecessor`, reason `offer_held`/`offer_pending`, eligible time | “Waiting for A: held until 3 Nov 03:00” or “Waiting for A: queued” | Once when the wait starts |
| `offer_blocked` | Block this pass | `blocked_by_predecessor` with the policy code | “Waiting for A: incompatible” | Once when the pass blocks |
| `locked` | Block this pass; A is `waiting_for_manager` | `blocked_by_predecessor`, reason `locked`, with manager | “Waiting for A: managed via Git” | Once when the pass blocks |
| `not_managed` | Block this pass | `blocked_by_predecessor`, reason `not_managed` | “A is not opted into automatic updates” | Once when the pass blocks |
| `running` | Wait | `waiting_for_predecessor` | “Waiting for A: running” | None |
| `failed` | Wait | `waiting_for_predecessor`, reason `failed` | “Waiting for A: failed; retrying next check” | The native failure email |
| `uncertain` | Wait or block | `blocked_by_predecessor`, reason `uncertain` | “A needs review” | Once |
| `not_installed` | Block | `blocked_by_predecessor`, reason `not_installed` | “A is no longer installed” | Once |
| `never_ready` | Block | `blocked_by_predecessor`, reason `never_ready` | “A cannot update automatically” | Once |
| `canceled` | Block | `blocked_by_predecessor`, reason `chain_canceled` | “The chain was stopped” | None |

---

## 7. Exhaustive case matrix

### 7.1 Legend and reading rules

- **Chain/graph:** `A→B` is a declared chain; `A→B, C→D` means two chains;
  `A→B, C→B` means two incoming predecessors for B.
- **Item state:** the resolver state from §6.1. `offer` means an offered
  item, refined to `offer_pending`, `offer_eligible`, or `offer_held`;
  `held` means `offer_held`; `none/latest` means `already_latest`;
  `none/stale` means `unknown_offer`.
- **Queue state:** the current queue switch and row state. `off` = the
  automatic queue is disabled; `on` = enabled; `row:*` = an existing row.
  After the simplification, the pass option replaces the queue table.
- **Blocking?** `Yes` when the current design cannot express the expected
  behavior without a new concept (mainly `waiting`), or when it would loop,
  deadlock, update out of order, or silently skip. `Partial` when the happy
  path works but the state or the display is not expressible.
- **Decision:** `S` = settled rule in §9; `O-n` = the question in §10, now
  settled (the answer is in the §10 table); `B-n` = blocking case in §8.

Every row assumes the chain is enabled and the site is licensed unless the row
says otherwise.

### 7.2 A. Basic availability

| ID | Chain/graph | Item A state | Item B state | Schedule/hold | Shield/lock | Queue state | Expected behavior | Current behavior | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|---|
| UF-A01 | A→B | offer | offer | none | allow | off | A updates, then B; one item per invocation | Same: topological order, one per worker | No | S1 |
| UF-A02 | A→B | offer | none/latest | none | allow | off | A updates; B is done (`already_latest`); pass completes | A runs; B `skipped`; chain completes | No | S1 |
| UF-A03 | A→B | none/latest | offer | none | allow | off | B may proceed if fresh `checked` proves A is current; otherwise B waits | B runs; A `skipped` | No if R2 accepted; **Yes** if the owner wants strict order | O-1 |
| UF-A04 | A→B | none/stale | offer | none | allow | off | A is `unknown_offer`; B waits and warns | B runs; A `skipped` | **Yes** | O-1, B1 |
| UF-A05 | A→B | none/latest | none/latest | none | allow | off | No updates; pass completes; no email | Both `skipped`; completed | No | S1 |
| UF-A06 | A→B | already at target (manual) | offer | none | allow | off | A done; B proceeds | A `skipped` (`already_installed`); B runs | No | S1 |
| UF-A07 | A→B | offer | already at target | none | allow | off | B done; A runs | B `skipped`; A runs | No | S1 |
| UF-A08 | A→B | offer disappears between plan and exec | offer | none | allow | off | Re-evaluate A: if fresh `checked` says latest, A done; else wait/notify; B waits | A `succeeded` with `no_longer_offered`; B runs | Partial | S6 |
| UF-A09 | A→B | offer | offer disappears between plan and exec | none | allow | off | B `skipped` (withdrawn); A proceeds; summary says skipped, not succeeded | B `succeeded` with `no_longer_offered`; summary shows succeeded | Partial | S6 |
| UF-A10 | A→B | target changes between plan and exec | offer | none | allow | off | Re-plan A to the new target; B waits; A updates; B follows | Pending-offer policy denies `stale_offer`; A `blocked`; B `canceled` | **Yes** | B2, B14 |
| UF-A11 | A→B | offer appears after plan | offer | none | allow | off | Next pass; B waits if A becomes held | A not in the plan; next tick re-plans | No | S5 |
| UF-A12 | A→B | offer | offer appears after plan | none | allow | off | B joins the next pass; A's order is unchanged | B not in the plan; next tick | No | S5 |

### 7.3 B. Schedule and hold

| ID | Chain/graph | Item A state | Item B state | Schedule/hold | Shield/lock | Queue state | Expected behavior | Current behavior | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|---|
| UF-B01 | A→B | offer | held | 1 day on B | allow | off | A updates; B waits for its hold; B updates after the hold | A runs; B `blocked`; B's blocked row is a barrier; next pass cannot re-plan it | **Yes** | B5, S3 |
| UF-B02 | A→B | held (offer day 2) | offer (hold expired) | 1 day on A | allow | off | B waits for A; A updates when eligible; B follows in the same pass | A `blocked` at enqueue; `cancel_flow_after()` cancels B | **Yes** | B2, S2 |
| UF-B03 | A→B | held, expires first | held, expires later | 1 day each | allow | off | Both wait; A updates first; B follows | A blocked; B canceled | **Yes** | B2 |
| UF-B04 | A→B | held, expires later | held, expires first | 1 day each | allow | off | B waits for A even though B's hold expires first | A blocked; B canceled | **Yes** | B2 |
| UF-B05 | A→B | offer | offer | hold 0 | allow | off | Both eligible; A then B | Both run | No | S3 |
| UF-B06 | A→B | held | offer | hold 365 days | allow | off | B waits up to a year; visible reason; no bypass | A blocked; B canceled | **Yes** | B2, O-3 |
| UF-B07 | A→B | offer appears while B is held by Flow | held | 1 day on B | allow | off | A's hold starts on discovery; B waits for A and its own hold | B blocked; A may be skipped in the same plan; next pass B's row is deduped | **Yes** | B5 |
| UF-B08 | A→B | held | offer | recurrence changes while B waits | allow | off | B's wait reason shows the new next check; no bypass | No waiting state; blocked row | Partial | S4 |
| UF-B09 | A→B | held | offer | preferred time changes | allow | off | Same as UF-B08 | Same | Partial | S4 |
| UF-B10 | A→B | held | offer | next check overdue | allow | off | Follow-up may drain an eligible step; it must not bypass A's hold | Follow-up drains; A stays blocked | Partial | S4 |
| UF-B11 | A→B | held | offer | WP-Cron disabled | allow | off | No follow-up; waiting persists; UI says cron is disabled | Queue needs an external cron; no waiting state | Partial | S4 |
| UF-B12 | A→B | offer | offer | both held, same expiry | allow | off | Stable order A then B | A likely runs first; B blocked/canceled | Partial | B2 |

### 7.4 C. Shield and Version Lock

| ID | Chain/graph | Item A state | Item B state | Schedule/hold | Shield/lock | Queue state | Expected behavior | Current behavior | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|---|
| UF-C01 | A→B | offer_blocked (incompatible) | offer | none | Shield on | off | B waits for A; A is `blocked_by_shield`; one notification; no out-of-order update | A `blocked`; B `canceled` | **Yes** | B4, O-4 |
| UF-C02 | A→B | offer | offer_blocked | none | Shield on | off | A updates; B waits for a fix; visible reason | A runs; B `blocked`; terminal row | Partial | B4 |
| UF-C03 | A→B | locked (Git) | offer | none | Version Lock on | off | B waits for the manager; A is `waiting_for_manager`; one notification | A `blocked` (`native_vcs`); B canceled | **Yes** | B6 |
| UF-C04 | A→B | offer | locked | none | Version Lock on | off | A updates; B waits for the manager | A runs; B `blocked` | Partial | B6 |
| UF-C05 | A→B | incompatible + locked | offer | none | both | off | One primary reason (Version Lock first per 01), manager named; B waits | Compatibility wins (policy order); B canceled | **Yes** | B7 |
| UF-C06 | A→B | unknown to detector | offer | none | detector unknown | off | Fail closed with a visible warning; B waits | Detector unknown allows A; B runs | **Yes** | B7 |
| UF-C07 | A→B | manually updated after lock | offer | none | Version Lock on | off | A done; B proceeds; lock does not block the manual update | A's blocked row remains a barrier; B canceled | **Yes** | B6 |
| UF-C08 | A→B | offer | locked + manually updated to target | none | Version Lock on | off | B done; A proceeds | B's blocked row remains; A runs; summary blocked | Partial | B6 |
| UF-C09 | A→B | `safe_mode` off | offer | none | Version Lock on | off | Version Lock does not operate; Flow still orders the automatic items | Engine policy still denies native VCS/Composer; not gated by `safe_mode` | **Yes** | B7 |

### 7.5 D. Failures

| ID | Chain/graph | Item A state | Item B state | Schedule/hold | Shield/lock | Queue state | Expected behavior | Current behavior | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|---|
| UF-D01 | A→B | failed (terminal) | offer | none | allow | off | A failed; B waits; next pass retries A; B is not canceled | A failed; B and every higher-priority row canceled | **Yes** | B3 |
| UF-D02 | A→B | failed (retryable) | offer | none | allow | off | A retried on the next pass; B waits; no in-pass retry loop | In-pass 5/15/60 min retries; after exhaustion B canceled | **Yes** | B3 |
| UF-D03 | A→B | uncertain | offer | none | allow | off | A needs review; B waits/blocks with reason; no auto-retry | A uncertain; B canceled | **Yes** | B3 |
| UF-D04 | A→B | succeeded | failed | none | allow | off | A done; B failed; B's descendants wait; independent chains continue | B failed; downstream and independent chains canceled | **Yes** | B3 |
| UF-D05 | A→B | succeeded | failed (last step) | none | allow | off | A done; B failed; the pass records B's failure and continues with independent branches | B failed; every higher-priority row canceled | **Yes** | B3 |
| UF-D06 | A→B | succeeded | succeeded | none | allow | off | Both done | Both done | No | S1 |
| UF-D07 | A→B | failed | failed | none | allow | off | A attempted; B waits for A; B is attempted only after A is done | A fails; B canceled before it runs | Partial | B3 |
| UF-D08 | A→B | disk full | offer | none | allow | off | A failed; native email; B waits; next pass retries A | Retry loop; then failed; B canceled | **Yes** | B3 |
| UF-D09 | A→B | fatal error with rollback | offer | none | allow | off | A failed; rollback evidence; B waits; no auto-retry | Lease expiry; A failed/uncertain; B canceled | **Yes** | B3 |
| UF-D10 | A→B | failure leaves native lock held | offer | none | allow | off | Lock expires; follow-up in 5 min; B waits | Lock-busy retry 5 min; B pending | Partial | B3 |
| UF-D11 | A→B | canceled by admin (if the path remains) | offer | none | allow | off | A canceled; B canceled with a visible reason; log row | A canceled; B canceled in SQL; no log row for B | Partial | S7 |
| UF-D12 | A→B | failed; B independent chain C→D | offer | none | allow | off | B waits; C/D continue | B, C, D canceled by topological rank | **Yes** | B9 |
| UF-D13 | A→B | failed; A is shared with C→A | offer | none | allow | off | Both B and C wait; no other chain affected | All higher-priority rows canceled | **Yes** | B9 |

### 7.6 E. Graph shape

| ID | Chain/graph | Item A state | Item B state | Schedule/hold | Shield/lock | Queue state | Expected behavior | Current behavior | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|---|
| UF-E01 | A→B→C | A failed | B, C offer | none | allow | off | B and C wait; not canceled | B and C canceled | **Yes** | B3 |
| UF-E02 | A→B→C | A offer | B none/latest; C offer | none | allow | off | A runs; B is done; C runs after A; order A, C | A and C run; B skipped | No | S1 |
| UF-E03 | A→B, C→D | A failed | B, C, D offer | none | allow | off | B waits; C/D continue | All higher-priority rows canceled | **Yes** | B9 |
| UF-E04 | A→B, A→C | A succeeds | B, C offer | none | allow | off | B and C run in stable order | Same | No | S1 |
| UF-E05 | A→B, C→B | A succeeds, C failed | B offer | none | allow | off | B waits for C; it does not run after A alone | B's chain id is the first declaring chain; C is not checked; B runs | **Yes** | B10 |
| UF-E06 | A→B, C→B | B offer | A/C offer | none | allow | off | One B row; both A and C must be done | First chain wins; the other predecessor is a `deduplicated` fact and is not a dependency | **Yes** | B10 |
| UF-E07 | A→B, A→B (two chains) | A/B offer | — | none | allow | off | One A and one B; both chains agree on the order | Dedupe; first chain owns; same order | No | S1 |
| UF-E08 | A→B→A (cycle) | — | — | — | — | off | Rejected with the loop path | `flow_cycle` with the loop | No | S8 |
| UF-E09 | A→B, B→A across chains | — | — | — | — | off | Rejected with the loop path | `flow_cycle` | No | S8 |
| UF-E10 | plugin→core | plugin offer | core offer | none | allow | off | Plugin then core; core last | Same; core subject to native `should_update()` | No | S8 |
| UF-E11 | core in chain 1, plugin in chain 2 | core offer | plugin offer | none | allow | off | Core is last in its chain, but the merged order may place it before another chain's plugin; the owner confirmed chain-local, so this is allowed | Topological tie-break; no global core-last guarantee | No | S12 |
| UF-E12 | 50+ steps / 20+ chains | — | — | — | — | off | Reject with a clear limit error; no silent truncation | Planner rejects; `collect_offers()` silently slices to 50 for the automatic queue | Partial | S8 |
| UF-E13 | A→B, A uninstalled after save | not_installed | offer | none | allow | off | B blocks with `not_installed`; no cancel; visible | A `blocked`; B `canceled` | **Yes** | B8 |
| UF-E14 | A→B, B uninstalled after save | offer | not_installed | none | allow | off | A may run; B blocks with `not_installed` | B `blocked`; A runs | Partial | B8 |
| UF-E15 | A→B, both items in two chains with different orders | offer | offer | none | allow | off | One merged order; a conflict is a cycle and is rejected | Cycle check rejects incompatible orders | No | S8 |

### 7.7 F. Pass and execution

| ID | Chain/graph | Item A state | Item B state | Schedule/hold | Shield/lock | Queue state | Expected behavior | Current behavior | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|---|
| UF-F01 | A→B | offer | offer | none | allow | off | Flow is the ordering function of the pass; one pass, one gate | Flow runs a separate runner and kernel while the automatic queue is off | **Yes** | B11 |
| UF-F02 | A→B | offer | offer | none | allow | on | Same pass; Flow orders the chained items; native default for the rest | Flow runs first, then the automatic queue plans separately; two paths | **Yes** | B11 |
| UF-F03 | A→B | offer | offer | none | allow | off, Flow disabled | Native loop runs all eligible items | Native loop | No | S5 |
| UF-F04 | A→B | offer | offer | none | allow | on, Flow disabled | The automatic queue runs one item per invocation; no chain order | Queue plans all offers, one per worker | No | S5 |
| UF-F05 | A→B | offer | offer | none | allow | off | One item per native invocation, then a follow-up | One item per worker; follow-up | No | S5 |
| UF-F06 | A→B | offer | offer | none | allow | off | A follow-up may drain the current pass; it must not bypass a hold | Follow-up at +1 s; no pass state | Partial | S4 |
| UF-F07 | A→B | offer | offer | none | allow | off | Two cron requests: native lock; one pass | Native lock; `active_run` 409 | No | S5 |
| UF-F08 | A→B | offer | offer | manual update of B during pass | allow | off | B is already installed; pass marks B done | `already_installed` | No | S6 |
| UF-F09 | A→B | offer | offer | WP-CLI update of A during pass | allow | off | A done; B proceeds | Installed version check | No | S6 |
| UF-F10 | A→B | offer | offer | Multisite, network chain, item only on one site | allow | off | Main site only; a subsite-only item is `not_installed` and visible | Planner uses the main site's catalogue; blocked | Partial | S9 |
| UF-F11 | A→B | offer | offer | license expires mid-pass | allow | off | Stop new steps; pass ends; chains intact; visible | License policy denies; active rows may remain | Partial | S9 |
| UF-F12 | A→B | offer | offer | engine kill switch flipped mid-pass | allow | off | Stop; visible; no partial order | `is_available()` false; runner refuses | Partial | S9 |
| UF-F13 | A→B | offer | offer | new offer appears mid-pass | allow | off | Next pass; the stored order is stable | Not in the plan; next tick | No | S5 |
| UF-F14 | A→B | none/stale | offer | update transient stale at pass start | allow | off | A `unknown_offer`; B waits; warn | A skipped; B runs | **Yes** | B1 |
| UF-F15 | A→B | none (API 500) | offer | API error | allow | off | A `unknown_offer`; B waits; warn | A skipped; B runs | **Yes** | B1 |
| UF-F16 | A→B | offer | offer | maintenance mode | allow | off | Native maintenance mode; one item | Native | No | S5 |
| UF-F17 | A→B | offer | offer | A deactivated between steps | allow | off | Re-evaluate; A may still be installed but inactive; update is allowed; B waits for done | Executor reads installed version; no activity check | Partial | S6 |
| UF-F18 | A→B | offer | offer | theme switched between steps | allow | off | Re-evaluate B; if the theme is no longer active, it is still installed and can update; B waits | Installed version; no active check | Partial | S6 |
| UF-F19 | A→B | offer | offer | object cache flush mid-pass | allow | off | Pass option survives | Queue table survives; option survives | No | S5 |
| UF-F20 | A→B | offer | offer | follow-up event lost | allow | off | Next scheduled check resumes the pass; the UI shows waiting | A pending Flow row makes `active_run()` return non-null; every later run is 409 until a worker runs | **Yes** | B12 |
| UF-F21 | A→B | offer | offer | option write fails mid-pass | allow | off | Fail closed; no partial order; visible | Queue insert fails; runner returns | Partial | S5 |
| UF-F22 | A→B | offer | offer | DB down | allow | off | No pass; no update; visible | Queue query fails; no pass | No | S5 |
| UF-F23 | A→B | offer | offer | `DISALLOW_FILE_MODS` / `AUTOMATIC_UPDATER_DISABLED` | allow | off | A `never_ready`; B blocks with reason | Native guards deny at enqueue; A blocked; B canceled | **Yes** | B8 |
| UF-F24 | A→B | offer | offer | site in maintenance mode from another process | allow | off | Native lock/upgrade; one item | Native lock | No | S5 |
| UF-F25 | A→B | offer | offer | A's offer target is not hosted on WordPress.org | allow | off | If the offer exists, A is `offer_pending`; if not, A is `unknown_offer` | `checked` is not consulted; A skipped | **Yes** | B1 |

### 7.8 G. UI and manual action

| ID | Chain/graph | Item A state | Item B state | Schedule/hold | Shield/lock | Queue state | Expected behavior | Current behavior | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|---|
| UF-G01 | A→B | offer | offer | none | allow | off | No Run now; no manual route; the pass is the only path | Button, route, cooldown, resume | **Yes** | B13 |
| UF-G02 | A→B | held | offer | none | allow | off | The page says “B waits for A: held until …” | B shows `blocked`/`canceled`; no reason column | **Yes** | B13 |
| UF-G03 | A→B | offer | offer | none | allow | off | The summary is read-only; polling stops when the pass ends | The UI polls only while a run id is active; after reload it may show nothing | Partial | B13 |
| UF-G04 | A→B | offer | offer | unsaved chain edits at pass start | allow | off | The pass uses the saved chains; the UI warns about unsaved edits | Same; Run now is disabled when dirty | Partial | S7 |
| UF-G05 | A→B | offer | offer | chain disabled while a pass is running | allow | off | The current pass finishes its snapshot; the next pass excludes the chain | Current run continues; next plan excludes | No | S7 |
| UF-G06 | A→B | offer | offer | chain deleted while a pass is running | allow | off | Stop the chain; cancel its pending steps; keep the pass summary; visible | Rows persist; the plan still runs; the chain is orphaned | **Yes** | B14 |
| UF-G07 | A→B | offer | offer | license inactive, Flow screen open | allow | off | Chain editor visible read-only; no run; clear notice | `check_permission()` denies; screen gated | No | S7 |
| UF-G08 | A→B | held | offer | summary says waiting, log says blocked | allow | off | One vocabulary; a `waiting` log row when the wait starts | Summary `canceled`/`blocked`; log has no waiting row | **Yes** | B15 |
| UF-G09 | A→B | offer | offer | two admins edit chains concurrently | allow | off | Last save wins; the pass uses its snapshot; warning | Last save wins | Partial | S7 |
| UF-G10 | A→B | offer | offer | Flow page opened during an active pass | allow | off | The page reads the active pass id and its states | `active_run_id` only exists while the queue table exists | Partial | B12 |
| UF-G11 | A→B | offer | offer | page reloaded after a failed pass | allow | off | Last pass summary, failed step, reason, log link | Run summary option (20) survives; no “waiting” state | Partial | B13 |
| UF-G12 | A→B | offer | offer | chain has one step | allow | off | Rejected: a chain needs two steps | `flow_steps_min` | No | S8 |

### 7.9 Coverage of the prompt's required cases

| Required case | Rows |
|---|---|
| A and B offers, eligible | UF-A01 |
| A offer, B none | UF-A02 |
| B offer, A none and latest | UF-A03 |
| B offer, A none and stale/unknown | UF-A04, UF-F14, UF-F15 |
| Neither has an offer | UF-A05 |
| A already at target | UF-A06 |
| B already at target | UF-A07 |
| A offer disappears | UF-A08 |
| B offer disappears | UF-A09 |
| A target changes | UF-A10 |
| A eligible, B held | UF-B01 |
| A held, B eligible | UF-B02 |
| Both held, A first / B first | UF-B03, UF-B04 |
| Hold 0 / max | UF-B05, UF-B06 |
| A offer appears while B held | UF-B07 |
| Recurrence / preferred time changes | UF-B08, UF-B09 |
| Next check overdue / WP-Cron disabled | UF-B10, UF-B11 |
| A Shield-blocked, B eligible | UF-C01 |
| B Shield-blocked, A eligible | UF-C02 |
| A locked, B eligible | UF-C03 |
| B locked, A eligible | UF-C04 |
| A incompatible + locked | UF-C05 |
| A unknown to detector | UF-C06 |
| A manually updated after lock | UF-C07 |
| A fails terminally / retryably / uncertain | UF-D01, UF-D02, UF-D03 |
| B fails, A succeeded | UF-D04 |
| A succeeds, B fails | UF-D05 |
| A and B both fail | UF-D07 |
| Disk full | UF-D08 |
| Fatal with rollback | UF-D09 |
| Native lock held | UF-D10 |
| A canceled by admin | UF-D11 |
| A→B→C; A fails | UF-E01 |
| A→B→C; B no offer, A/C offer | UF-E02 |
| A→B and C→D; A fails | UF-E03 |
| Shared predecessor | UF-E04 |
| Multiple predecessors, one fails | UF-E05 |
| B in two chains | UF-E06 |
| Same item twice in one chain | UF-E07 (and S8) |
| Cycle across two chains | UF-E08, UF-E09 |
| Core as last step | UF-E10 |
| Core in one chain, plugin in another | UF-E11 |
| 50+ steps / 20+ chains | UF-E12 |
| Chain item uninstalled | UF-E13, UF-E14 |
| Queue disabled, Flow enabled | UF-F01 |
| Queue enabled, Flow disabled | UF-F04 |
| Both enabled / both disabled | UF-F02, UF-F03 |
| One item per worker vs per pass | UF-F05 |
| Follow-up before next check | UF-F06 |
| Two cron requests | UF-F07 |
| Manual update during pass | UF-F08 |
| WP-CLI during pass | UF-F09 |
| Multisite | UF-F10 |
| License expires mid-pass | UF-F11 |
| Kill switch mid-pass | UF-F12 |
| New offer mid-pass | UF-F13 |
| Stale transient / API 500 | UF-F14, UF-F15 |
| Maintenance mode | UF-F16 |
| Plugin activated/deactivated | UF-F17 |
| Theme switched | UF-F18 |
| Run now clicked | UF-G01 |
| Why B is waiting | UF-G02, UF-G08 |
| Summary polled while idle | UF-G03 |
| Unsaved edits at pass start | UF-G04 |
| Chain disabled / deleted mid-pass | UF-G05, UF-G06 |
| License inactive screen | UF-G07 |

---

## 8. Blocking cases and proposed treatments

“Blocking” means the current design cannot express the expected behavior
without a new concept, or it would update out of order, loop, deadlock, or
silently skip. The treatment is the settled handling; §9 records the decisions
and §10 records the answers.

| ID | Case | Why it blocks today | Proposed treatment | Decision |
|---|---|---|---|---|
| B1 | A has no offer; B has one. Is A done? | The planner treats no offer as skipped and lets B run (`class-updatronix-flow-planner.php:374-404`). | Add `already_latest` vs `unknown_offer` using `checked` + `last_checked` (R2/R3). `already_latest` → done; `unknown_offer` → B waits and the UI warns. For items absent from `checked` (premium/self-hosted), the settled answer is `unknown_offer`: B waits and the editor warns. | O-1 |
| B2 | A is held; B is eligible | The held step becomes `blocked`; `cancel_flow_after()` cancels B (`class-updatronix-flow-runner.php:394-430`). The blocked row is reused by the next plan and the automatic path never calls `recheck_blocked()`, so A itself never re-plans automatically. | A is `offer_held` with an eligible time; B is `waiting_for_predecessor`; B is not canceled. The next pass re-evaluates A. | O-2 |
| B3 | A failed or uncertain; B pending | `finish()` cancels downstream and every higher-priority row (`class-updatronix-update-kernel.php:943-948`). | A is `failed` for this pass; B waits; descendants wait; independent branches continue. No in-pass retry loop; the next pass retries A once. | O-3 |
| B4 | A is Shield-blocked; B eligible | A is `blocked`; B is canceled. | A is `blocked_by_shield`; B waits with the policy code and a notification. If the denial is permanent for the offer, B blocks for this pass; the pass does not spin. | O-4 |
| B5 | A is version-locked; B eligible | A is `blocked` (`native_vcs`/`version_controlled`); B canceled. | A is `waiting_for_manager`; B waits; one notification names the manager. A manager update to the target makes A done. | S2 |
| B6 | A is not installed | A is `blocked`; B is canceled. | A is `not_installed`; B is `blocked_by_predecessor`, not canceled; the UI names the missing item and links to the chain editor. | O-4 |
| B7 | A is not opted into automatic updates | Flow bypasses the native opt-in and forces `autoupdate = true` (`class-updatronix-engine-policy.php:98-105`; `class-updatronix-update-executor.php:126-131`). | `not_managed`; Flow does not force. If A is a predecessor, B waits or blocks with a notice. The owner opts A in or edits the chain. | O-5 |
| B8 | A cannot update automatically (not hosted, forced off) | A no-offer item is skipped and B runs; a native-guard denial (`DISALLOW_FILE_MODS`, `AUTOMATIC_UPDATER_DISABLED`) blocks A and cancels B. | `never_ready`; B blocks with a visible reason; no spin. The owner edits the chain or fixes the configuration. | O-4 |
| B9 | A failure cancels an independent chain | `cancel_flow_after()` cancels every higher-priority row (`class-updatronix-update-queue.php:979-1008`). | Only descendants wait or block. The pass continues with independent branches. | S2 |
| B10 | B has two predecessors; one fails | B's dependency check reads only its declaring chain's earlier rows (`class-updatronix-update-queue.php:1143-1172`). | Every incoming edge is a dependency. B waits until all predecessors are done. Dedupe one B row; keep all edges. | S2 |
| B11 | Flow is a second execution path | Flow runs while the queue is off and calls the kernel directly (`class-updatronix-queue-scheduler.php:101-115`; `class-updatronix-flow-runner.php:166-176`). | Flow becomes the ordering function of the one pass. The pass builds one order, one gate, one state machine. No runner, no separate run id. | S5 |
| B12 | A lost follow-up leaves a pending row and every later run 409 | `active_run()` returns any pending Flow row; a lost follow-up leaves the row until the next worker runs, and with WP-Cron disabled there is no worker (`class-updatronix-flow-runner.php:205-222`; `class-updatronix-queue-scheduler.php:312-317`). | The pass option owns the active state and a `last_heartbeat`; the next scheduled check replaces or resumes a stale pass. The Flow page reads the pass, not a queue row. | S5 |
| B13 | The manual run was never requested | `POST /flow/run`, cooldown, Run now, manual resume (`class-updatronix-update-flow.php:118-130`, `:327-342`; `UpdateFlowPage.js:1225-1285`; `class-updatronix-flow-runner.php:432-453`). | Remove the button, the route, the cooldown, the manual resume, and the per-step polling of a manual run. Keep the chain editor and a read-only last-pass summary. | O-6 |
| B14 | A chain is deleted or edited while a pass runs | The runner persists rows; the plan is not re-read; the chain list is not part of the pass (`class-updatronix-flow-runner.php:364-497`). | Snapshot the chain ids and steps in the pass option. A deleted chain stops its pending nodes and is marked canceled; the summary keeps the snapshot. An edit applies to the next pass. | S7 |
| B15 | The summary and the log disagree | No `waiting` row; canceled rows are not journaled; the event key omits the pass id (`class-updatronix-update-journal.php:76-90`; `class-updatronix-update-kernel.php:518-539`). | One vocabulary; a `waiting` row when a wait starts; a pass id in the event key; a pass summary row; log every canceled downstream row. | S6 |
| B16 | A target change between plan and execution blocks the chain | The pending-offer policy denies `stale_offer`; the kernel finishes `blocked` and cancels downstream (`class-updatronix-pending-offer-policy.php:68-79`). | Re-read the offer and target at execution. If the target changed, re-plan the node to the new target; keep descendants waiting. | S6 |
| B17 | Core or translations interact with Flow | Core is allowed only last; translations are not chain steps; core is subject to `should_update()` (`class-updatronix-flow-planner.php:219-233`). | Keep core-last. Core is a normal gated item, subject to `auto_update_core`/`WP_AUTO_UPDATE_CORE`. Translations are not chain steps; they follow the native pass. | O-5 |
| B18 | A waiting node is not owned; native can update it | Ownership is queue-row based (`class-updatronix-shield-ownership.php:159-190`); a waiting node has no row. | The pass option is the ownership source. The gate denies every chain node that is not `done`, including waiting nodes. | S5 |
| B19 | The Flow UI has no waiting reason | It shows queue statuses only (`UpdateFlowPage.js:783-844`; `flowRunState.js:23-25`). | The page reads the pass node state and shows `waiting_for`, the reason, the eligible time, and the manager. No new interactive control. | S6 |
| B20 | Multisite: a network chain references a subsite-only item | The planner uses the main site's catalogue (`class-updatronix-flow-planner.php:526-552`). | Main-site execution only. A subsite-only item is `not_installed` on the main site and is shown as such. The chain editor warns at save. | S9 |

---

## 9. Decisions settled by grilling

**Settled on 2026-10-28.** The round referenced §7 and §8, and the owner
answered the four grouped decisions in one round, selecting the recommended
option in every group.
The full round is in `updatronix-pro/.agents/notes/2026-10-28-grill-update-flow.md`.

| Group | Owner answer |
|---|---|
| Waiting rule | **Fresh evidence only.** `already_latest` requires a fresh `checked` entry; stale, absent, or unknown data makes the downstream wait and warn. |
| Predecessors and failures | **All edges, descendants only.** Every incoming predecessor counts; a failure affects only descendants; independent chains continue; the next pass retries once. |
| Permanent states | **Block this pass, visible.** A permanent predecessor state blocks its descendants for the pass with a reason and one notification; a later pass re-evaluates. |
| Removal and model | **Apply all recommendations.** One pass/gate; full removal; opted-in only; chain-local core-last; native translations; 20/50; main-site. |

The decisions below are settled. The alternatives in §10 are kept as the
record of what was rejected.

| Q | Settled decision | Why | Owner input |
|---|---|---|---|
| S1 | One merged graph; stable topological order; one job per item and target; core last in its chain. | The current planner is correct here and the tests prove it. | Q4 group |
| S2 | All incoming predecessors; only descendants wait; independent chains continue. A failure or block never cancels a non-descendant. | The owner's model is a dependency graph, not a global rank. | Q2 group |
| S3 | A held offer is not done; the downstream waits. No bypass, including a follow-up. | The day-2 gateway example. | Q1 group |
| S4 | A pass starts at the scheduled check; a follow-up drains it; the next check re-evaluates. A held node ends the pass when no other node is eligible. | Matches the queue analysis's pass boundary. | Q2/Q4 groups |
| S5 | One pass, one gate, one state machine. Flow is the ordering function of the pass; no second execution path. | `02-update-queue.md` Q13. | Q4 group |
| S6 | Re-derive state at execution. A target change re-plans the node; an offer withdrawal is a skip, not a success; a waiting state is logged. | Stale offers are a real state, not an error. | Q1/Q4 groups |
| S7 | Chain edits apply to the next pass; a deleted chain stops its pending nodes; the pass keeps a snapshot. | Avoids a mid-pass re-plan and an orphan row. | Q4 group |
| S8 | Keep 20 chains / 50 steps, filterable; reject with a clear error; no silent truncation. Reject cycles, duplicates, core not last, and single-step chains. | The current validation is sound. | Q4 group |
| S9 | Main-site execution; network-wide chains; Super Admin; a subsite-only item is `not_installed`. | Native `run()` is main-site only. | Q4 group |
| S10 | Remove Run now, `POST /flow/run`, the cooldown, manual resume, and manual-run polling. Keep the chain editor and a read-only last-pass summary. | The owner never asked for manual execution. | Q4 group |
| S11 | Flow applies only to items already opted into automatic updates. A non-opted item is `not_managed`; Flow does not force the native opt-in. | The queue analysis's gate never returns `true`; forcing would bypass the owner's opt-in. | Q4 group |
| S12 | Core last in its chain; translations are not chain steps. | Matches the current validation and the native pass. | Q4 group |
| S13 | A permanent predecessor state (`not_installed`, `not_managed`, `never_ready`, a permanent Shield denial) blocks its descendants for this pass with a visible reason and one notification; a later pass re-evaluates. | No spin, no cancel, no out-of-order update. | Q3 group |
| S14 | No in-pass retry loop. A failed node is skipped for the pass; the next pass retries once; descendants wait. | `02-update-queue.md` Q4. | Q2 group |
| S15 | Manual updates are outside Flow. A manual update to the target makes the node done; a manual update to another version is logged, not blocked. Version Lock remains the manual lock. | Flow governs automatic updates only. | Q4 group |
| S16 | Waiting notification: one log row when the wait starts, one when it becomes permanent; no email for a transient wait. The native per-item email remains the only update email. | Keeps the native email contract. | Q4 group |

---

## 10. Owner decisions (settled 2026-10-28)

**No owner decision remains open.** The owner answered the ten items on
2026-10-28; the answers are recorded here and in
`updatronix-pro/.agents/notes/2026-10-28-grill-update-flow.md`. The detailed
subsections below are kept as the record of the options; the answer table is
authoritative.

| Item | Owner answer |
|---|---|
| OPEN-1 · Day-1 rule | **Fresh evidence only (Option A).** `already_latest` requires a fresh `checked` entry; stale, absent, or unknown data makes the downstream wait and warn. |
| OPEN-2 · Held predecessor | **Wait.** The held node is `offer_held`; the downstream waits; the eligible time is shown. |
| OPEN-3 · Failures | **Next pass.** No in-pass retry loop; descendants wait; independent branches continue; the next pass retries once. |
| OPEN-4 · Permanent states | **Block this pass, visible.** `blocked_by_predecessor`, one notification, automatic re-evaluation on a later pass. |
| OPEN-5 · Native opt-in, core, translations | **Opted-in only; core last in its chain; translations native.** No forced opt-in. |
| OPEN-6 · Removal scope | **Remove Run now, `POST /flow/run`, the cooldown, manual resume, and manual-run polling.** Keep the editor and a read-only last-pass summary. |
| OPEN-7 · Core-last scope | **Chain-local.** Core is last in its chain; no global core-last inversion of another chain. |
| OPEN-8 · Waiting notifications | **One log row when the wait starts, one when it becomes permanent; no email for a transient wait.** |
| OPEN-9 · Manual update of B before A | **Log only.** Flow governs automatic updates; Version Lock remains the manual lock. |
| OPEN-10 · Multisite | **Network chains, main-site execution, Super Admin; a subsite-only item is `not_installed`.** |

### OPEN-1 — Day-1 rule: proceed or wait when the predecessor has no offer?

If A is installed, opted in, and the update API reports it at the latest known
version, may B proceed?

- **Option A (recommended if the owner accepts the discovery window):** Yes.
  `already_latest` requires a fresh `checked` entry. A stale transient, an
  absent `checked` entry, or an API error is `unknown_offer`, so B waits with a
  warning. This treats “latest known” as evidence, not as a guess.
- **Option B:** No. Always wait for A until it is updated. This preserves strict
  order but can wait forever for a premium item that is absent from `checked`.
  The UI must show “waiting; no offer known” and offer the chain editor.
- **Option C:** Proceed only if A is a WordPress.org-hosted item with a fresh
  `checked` entry; otherwise wait. This is Option A with a narrower scope.

The owner's answer also settles what happens when the gateway's release is
simply not discovered yet: under A/C, B may update in the current pass; under
B, B waits and the chain is visibly stuck.

### OPEN-2 — Held predecessor: wait or block for this pass?

Recommendation: wait. A held offer is transient; the next pass re-evaluates.
The UI shows the eligible time. No notification beyond the wait-start row.

The alternative is to block the chain for this pass with a
`blocked_by_schedule` state. It is stronger but does not change the update
order; it only changes the display.

### OPEN-3 — Failure: next pass or in-pass retry?

Recommendation: no in-pass retry loop. A failed item is skipped for this pass;
the next pass retries once. Descendants wait. Independent branches continue.
This matches `02-update-queue.md` Q4.

The alternative is to keep the current 5/15/60-minute backoff inside the pass.
It delays the pass and makes the wait state harder to display.

### OPEN-4 — Permanent predecessor states: wait or hard block?

For `not_installed`, `not_managed`, `never_ready`, and a permanent Shield
denial, the downstream cannot ever proceed without a change.

- **Option A (recommended):** `blocked_by_predecessor` for this pass, visible,
  one notification; the chain resumes automatically on a later pass if the
  state changes. No spin, no cancel.
- **Option B:** Keep waiting with a warning. The chain stays “waiting” until the
  owner edits it. This is acceptable only if the UI makes the state obvious.
- **Option C:** Cancel the chain permanently and require a manual re-enable.
  This is the current behavior in spirit and is not recommended.

### OPEN-5 — Native opt-in, core, and translations

Recommendation: Flow applies only to items already in `auto_update_plugins` /
`auto_update_themes`; a non-opted item is `not_managed`. Core is allowed only
last and is subject to `auto_update_core` and `WP_AUTO_UPDATE_CORE`.
Translations are not chain steps and follow the native pass.

Alternatives: let Flow force the opt-in (rejected: the gate would have to return
`true`), or allow core anywhere (rejected: the native updater's core behavior
is special and the current validation is safer).

### OPEN-6 — Removal scope and read-only summary

Recommendation: remove the Run now button, `POST /flow/run`, the five-minute
cooldown, the manual resume, and the manual-run polling. Keep the chain
editor, the per-step states, and a read-only last-pass summary. Keep
`GET /flow/runs/{run_id}` read-only for the last pass, or fold the summary into
`GET /flow/chains`. Do not keep a “start a pass” action.

Alternative: keep a manual “start a pass” action that enters the same one-pass
gate (not a second executor). This is more surface than the owner asked for.

### OPEN-7 — Global core-last or chain-local core-last?

The current rule is chain-local: core must be the last step of *its* chain.
In a merged graph, a core node may run before another chain's plugin. The owner
must confirm whether core must be last **globally** in the pass.

Recommendation: keep chain-local, because a global core-last rule can invert a
declared chain and surprise the owner. If the owner wants a global rule, the
planner must reject any chain whose plugin would run after core in the merged
order.

### OPEN-8 — Waiting notification frequency

Recommendation: one log row when a wait starts for a target, one row when the
state becomes permanent, and no email for a transient wait. The native per-item
email remains the only email for updates.

The owner may want an email when a chain is blocked by a locked or missing
predecessor. That is a new notification, not a native one.

### OPEN-9 — Does a manual update of B before A violate the chain?

Recommendation: log only. Flow governs automatic updates; a manual update is
the owner's explicit action. Version Lock remains the manual lock when its
optional toggle is on.

Alternative: warn in the Flow page when a manual update breaks the declared
order. Do not block it.

### OPEN-10 — Multisite scope

Recommendation: network-wide chains, main-site execution, Super Admin only. A
subsite-only item is `not_installed` on the main site and the chain editor warns
at save.

Alternative: per-site chains. That is a new data model and is not recommended.

---

## 11. Contracts with Version Lock, Queue, Schedule, and Logger

### 11.1 Version Lock

| Direction | Contract |
|---|---|
| Version Lock provides to Flow | A per-item decision: `allowed`, `native_vcs`, `version_controlled`, `unknown`, with `manager`, `scope`, `reason`, and `evidence`. The decision is gated by `safe_mode` (owner decision Q2 in `01-version-lock.md`). |
| Flow provides to Version Lock | A plan-time hook and a `waiting_for_manager` state. A locked predecessor does not make the run `failed`; downstream steps wait. The pass summary names the manager. |
| Precedence | One primary reason. Version Lock first, then compatibility, then delay (owner decision Q13 in `01-version-lock.md`). The current policy chain evaluates compatibility before version control (`class-updatronix-policy-chain.php:36-42`); the implementation must reorder or merge the results. |
| Manual updates | Version Lock's optional manual block is independent of Flow. When it is off, a manual update is allowed; Flow logs it and treats the installed target as done. |

### 11.2 Queue

| Direction | Contract |
|---|---|
| Queue provides to Flow | One pass, one order, one gate, one execution path. The pass option, not the queue table, is the source of truth for the active order and the ownership set. |
| Flow provides to Queue | The ordered nodes for the chained items, with all incoming edges and the readiness state. The pass builder merges them with the native default order for the remaining offers. |
| Failure policy | A failed node is skipped for the pass; descendants wait; independent branches continue. The pass never cancels a node that is not a descendant. |
| Active pass | The pass option holds `pass_id`, `started_at`, `order`, `nodes`, `predecessors`, `waiting_for`, `attempted`, `done`, `last_item`, `lock_waits`, and `next_check_at`. It is bounded to 50 nodes and deleted at pass end. |
| Ownership | The gate denies every chain node that is not `done`, including waiting nodes, even when no queue row exists. The native `run()` must not update a waiting node. |
| Removal | Delete the Flow runner, its route, its cooldown, and its manual resume. Keep the planner and the policy chain. |

### 11.3 Schedule

| Direction | Contract |
|---|---|
| Schedule provides to Flow | The next `wp_maybe_auto_update` timestamp, the per-offer hold, the first-seen time, and the eligible time. |
| Flow provides to Schedule | The pass id, the ordered nodes, the current node, the per-node state (`waiting`, `held`, `blocked`, `running`, `succeeded`, `failed`, `skipped`), the predecessor, and the next follow-up time. |
| Display | The Schedule screen must not promise a next automatic update before a predecessor is done. A held predecessor makes the downstream's true next moment “after the predecessor”, not the downstream's own hold expiry. |
| Bypass | A follow-up may drain an already-started pass, but it must not bypass a hold. The delay policy is re-evaluated at execution. |

### 11.4 Logger

| Direction | Contract |
|---|---|
| Logger provides to Flow | One Free log row per item outcome, an idempotent event key, and the bounded query API. |
| Flow provides to Logger | The pass id, the item, the from/to versions, the outcome, the reason code, the source (`automatic`, `flow`), and the predecessor for a waiting row. The event key must include the pass id (the current key omits it: `class-updatronix-update-journal.php:76-90`). |
| Waiting rows | One row when a wait starts, one when it becomes permanent. The row action type is `prevented` with a `waiting_for_predecessor` reason, or a new additive action type if the logger owner prefers one. |
| Canceled rows | Every canceled downstream row gets a log row. The current `cancel_downstream()`/`cancel_flow_after()` update SQL only. |
| Pass row | One row per pass with the counts and the final status. The pass id travels in the event key until the logger schema settles. |

---

## 12. Recommended architecture

### 12.1 One gate, one pass, one state machine

```
wp_maybe_auto_update
        │
        ▼
WP_Automatic_Updater::run()  ── refresh discovery ──► first auto_update_* call
        │                                              │
        │                                              ▼
        │                                   build the pass (once)
        │                                   Flow DAG + native offers
        │                                   readiness resolver
        │                                              │
        │                                              ▼
        │                                   one item: return $update
        │                                   others: return false
        │                                              │
        ▼                                              ▼
native update() for one item            pass state: done / failed / waiting
        │                                              │
        ▼                                              ▼
native email, lock, rollback             follow-up at +1 s, capped by next check
```

The pass is the only writer. The gate is a selection mechanism; it never
returns `true`. The pass state is the only source of truth for the active order
and the ownership set.

### 12.2 The pass option

One non-autoloaded site option on the main site:

```php
// Option: updatronix_pro_update_pass
array(
    'pass_id'       => '32 hex characters',
    'started_at'    => '2026-10-28 03:00:00',
    'next_check_at' => 1761706800,
    'order'         => array( 'plugin:gateway/gateway.php', 'plugin:woocommerce/woocommerce.php' ),
    'nodes'         => array(
        'plugin:gateway/gateway.php' => array(
            'type'        => 'plugin',
            'id'          => 'gateway/gateway.php',
            'state'       => 'offer_held',
            'target'      => '2.0.0',
            'predecessors'=> array(),
            'waiting_for' => '',
            'eligible_at' => 1761793200,
            'attempted'   => 0,
        ),
        'plugin:woocommerce/woocommerce.php' => array(
            'type'        => 'plugin',
            'id'          => 'woocommerce/woocommerce.php',
            'state'       => 'waiting_for_predecessor',
            'target'      => '9.2.0',
            'predecessors'=> array( 'plugin:gateway/gateway.php' ),
            'waiting_for' => 'plugin:gateway/gateway.php',
            'eligible_at' => 0,
            'attempted'   => 0,
        ),
    ),
    'done'          => array(),
    'last_item'     => '',
    'lock_waits'    => 0,
    'flow_snapshot' => array(
        'plugin:gateway/gateway.php'     => array( 'chain' => 'payments', 'sequence' => 1 ),
        'plugin:woocommerce/woocommerce.php' => array( 'chain' => 'payments', 'sequence' => 2 ),
    ),
)
```

Rules:

1. Bounded to 50 nodes.
2. Written before the native call for the attempt marker.
3. Deleted at pass end, on deactivation, and on uninstall.
4. The last-pass summary is copied to `updatronix_pro_update_runs` (20 max) before
   deletion.
5. The option is the ownership set: the gate denies every node that is not
   `done`, including nodes with no queue row.
6. A stale pass (no heartbeat, no follow-up) is replaced by the next scheduled
   check, not resumed blindly.
7. Before a stale pass is replaced, reconcile the last item's installed version:
   target installed → `succeeded`; the from-version installed → retry; neither
   → `uncertain` and manual review. This is the crash-recovery rule that keeps
   a chain from resuming at the wrong step.

### 12.3 The order builder

`Updatronix_Update_Pass::build_order()`:

1. Read the enabled chains from `Updatronix_Update_Flow::get_saved_chains()`.
2. Run `Updatronix_Flow_Planner::plan()` to get nodes, edges, targets, and
   per-node facts. The planner remains deterministic and side-effect free.
3. Merge the native offers that are not in a chain, in native order: plugins,
   themes, core.
4. Resolve each node's readiness with one function:
   `updatronix_pro_flow_readiness( $node, $pass )`. It reads the installed
   version, the response entry, the `checked` map, `last_checked`, the Free
   delay result, the ordered policy result, the native opt-in, and the pass
   state.
5. Compute the schedulable set: a node is schedulable when its state is
   `offer_eligible` and every incoming predecessor is `done`.
6. If no node is schedulable, end the pass. If a node is waiting on a hold and
   another node is schedulable, run the schedulable node; the held node stays
   waiting.
7. Deduplicate by item and target; keep every incoming edge.
8. Apply the limits: 20 chains and 50 steps per chain (the current planner's
   defaults), and bound the pass to 50 nodes. Report overflow instead of
   slicing silently.
9. Persist the order and the state.
10. A new offer that appears after the order is built waits for the next pass.

### 12.4 The gate

For each `auto_update_{type}` call:

1. Read the pass option.
2. Resolve the item's node.
3. If the item is not a chain node, leave the incoming decision unchanged
   (Flow does not own it).
4. If the item is a chain node and its state is `done`, return `false` (it
   must not run again in this pass).
5. If the item is a chain node, its state is `offer_eligible`, and it is the
   next node in the stable order, return the incoming `$update` unchanged.
6. Otherwise return `false`.
7. Never return `true`.

The gate does not re-run the policy chain. The chain already ran in Free's
delay filter and Shield's adapter. The pass logs one denial per item.

### 12.5 Execution and finalize

1. Native `run()` executes the selected item with the native lock, maintenance
   mode, rollback, email, and completion action.
2. The pass reads the installed version and the offer after the call.
3. Classify: target installed → `succeeded`; offer gone and target not
   installed → `skipped`; unchanged and offer remains → `failed`; item missing
   → `not_installed`; neither source nor target → `uncertain`.
4. Write one Free log row, with the pass id in the event key.
5. If a schedulable node remains, schedule one follow-up at `time() + 1`.
6. If none remains, write the pass summary, copy the last-pass summary, and
   delete the pass option.
7. If the next scheduled check arrives first, replace the pass with a new one;
   the old pass is abandoned and summarized.

### 12.6 Failure and waiting rules

1. A failed node is `failed` for this pass. Descendants wait. Independent
   branches continue.
2. A locked node is `locked`; descendants wait for the manager.
3. A held node is `offer_held`; descendants wait for the eligible time.
4. An unknown node is `unknown_offer`; descendants wait and the UI warns.
5. A missing or never-ready node blocks its descendants for this pass, with a
   reason. The pass does not spin.
6. No node is canceled because a predecessor is transiently not done.
7. A canceled node is reserved for a disabled/deleted chain or an abandoned
   pass.

### 12.7 The Flow page

- Chain list and editor: unchanged, minus Run now.
- Per-step state: `pending`, `running`, `succeeded`, `failed`, `skipped`,
  `blocked`, `canceled`, plus `waiting` with `waiting_for` and the reason.
- Last-pass card: pass id, start/end, counts, the first waiting reason, and the
  log link.
- No polling loop. Load the last pass on page load; refresh on an explicit
  action or after a settings save.
- Copy: “Enabled chains order automatic updates. A step waits until its
  predecessor is updated. A held step waits for its hold and for every
  predecessor.”
- Remove “Run now starts a chain immediately” and the run dialog.

### 12.8 Why not an alternative

| Alternative | Why not |
|---|---|
| Keep the custom queue and the Flow runner | The owner asked for one-by-one native execution; two paths mean two state machines and two failure policies. The queue analysis deletes the table. |
| Run the whole chain in one REST request | It defeats one-item-per-invocation and the native lock; a long chain times out. |
| Treat “no offer” as done always | It silently updates out of order when a release is not discovered yet. |
| Treat “no offer” as not done always | A premium item absent from `checked` blocks the chain forever. The owner must choose the evidence rule (OPEN-1). |
| Cancel the chain on a failed predecessor | It stops independent chains and needs a manual re-enable; the owner wants automatic continuation. |
| Add a second lock or a lease for Flow | Native `run()` already owns `auto_updater.lock`; a second lock is a deadlock risk. |

---

## 13. Implementation constraints and test strategy

### 13.1 Constraints

1. **No second execution path.** Only `WP_Automatic_Updater::run()` executes.
   The pass never calls `update()` or a raw upgrader.
2. **Gate priority.** `auto_update_*` at `PHP_INT_MAX`, after Free's delay at 21
   and Shield's adapter at `PHP_INT_MAX - 5`. The gate returns the incoming
   value for the selected item and `false` for the rest. It never returns
   `true`.
3. **Order after discovery.** Build the order at the first gate call, after
   `run()` refreshes the transients. Do not build it at
   `wp_maybe_auto_update` priority 1.
4. **One pass option.** Non-autoloaded, main-site, bounded to 50 nodes, deleted
   at pass end, deactivation, and uninstall. The attempt marker is written
   before the native call.
5. **Ownership.** The pass option is the ownership source. The gate denies every
   chain node that is not `done`, including waiting nodes.
6. **All incoming predecessors.** The dependency set is the merged graph's
   incoming edges, not the declaring chain's earlier rows.
7. **No in-pass retry loop.** A failed node is skipped for the pass; the next
   pass retries once.
8. **No cancellation of non-descendants.** A failure, block, or hold only
   affects descendants.
9. **Waiting is not `pending`.** The state vocabulary is `already_latest`,
   `unknown_offer`, `offer_pending`, `offer_eligible`, `offer_held`,
   `offer_blocked`, `locked`, `not_managed`, `not_installed`, `never_ready`,
   `waiting_for_predecessor`, `running`, `succeeded`, `failed`, `uncertain`,
   `skipped`, `canceled`. `blocked` is the owner-facing umbrella for
   `offer_blocked`, `locked`, `not_managed`, `not_installed`, and
   `never_ready`.
10. **No manual run.** No `POST /flow/run`, no cooldown, no resume, no Run now.
11. **Native opt-in.** Flow applies only to opted-in items. No forced
    `autoupdate = true`.
12. **Version Lock precedence.** Version Lock first, then compatibility, then
    delay; one primary reason; the manager label is shown. The policy chain
    must be reordered or its results merged.
13. **Safe Mode.** Version Lock is gated by `safe_mode`; Flow does not operate
    Version Lock when Update Shield is off.
14. **Multisite.** Main site only; network chains; Super Admin; a subsite-only
    item is `not_installed`.
15. **Limits.** 20 chains / 50 steps, filterable; pass order 50 nodes; no silent
    truncation.
16. **i18n.** New strings need translator comments; the text domain is
    `updatronix-pro`. Do not reword existing strings without owner approval.
17. **Security.** No new REST route for running. The read-only summary route
    keeps the existing capability, license, and Super Admin gates. The pass
    writes only its own option.
18. **Performance.** One discovery refresh per item is the native cost. The pass
    is bounded; the follow-up is immediate; no second discovery pass.
19. **Compatibility.** Existing chains migrate once; no dual-read shim. Existing
    run summaries remain readable for 20 runs. No version bump in this
    analysis.
20. **No code in this analysis.** The plan owns the implementation.

### 13.2 Test strategy

**Unit**

- Readiness resolver: `already_latest`, `unknown_offer`, `offer_pending`,
  `offer_held`, `offer_blocked`, `locked`, `not_managed`, `not_installed`,
  `never_ready`, `waiting_for_predecessor`, `running`, `succeeded`, `failed`,
  `uncertain`, `skipped`, `canceled`.
- `checked` map and `last_checked` freshness; stale, missing, and API-error
  cases.
- All incoming predecessors: A→B, C→B; one fails; B waits.
- Dedupe with shared nodes: one row, all edges.
- Chain edits: deleted chain, disabled chain, edited steps.
- Limits: 20 chains, 50 steps, 51 offers, single-step chain.
- Core-last: chain-local vs global (per OPEN-7).
- Version Lock precedence and `safe_mode` gate.
- Pass state: attempt marker, bounded nodes, pass end, stale pass replacement,
  option deletion.

**Integration** (real WordPress, local fixtures, no network)

- Day-1/day-2: gateway offer injected on day 2 with a one-day hold;
  WooCommerce offer on day 1; the gateway updates first, WooCommerce follows.
  Use a clock/filter to advance `last_checked` and the Free ledger without
  sleeping.
- Held predecessor: B waits, is not canceled; the next pass updates A, then B.
- Stale transient: A `unknown_offer`; B waits; the UI/log says stale.
- Shield block: A blocked; B waits/blocks; no write.
- Version Lock: A locked; B waits; a manager update to the target makes A done.
- Failure: A failed; B waits; independent C/D continue; the next pass retries A.
- Target change between plan and execution: A re-plans to the new target; B
  waits.
- Offer withdrawal: A/B skipped, not succeeded; the summary is truthful.
- Manual update during a pass: target installed; pass marks done.
- WP-CLI update: same.
- Two cron requests: native lock; one pass.
- Lost follow-up: the next scheduled check replaces the stale pass.
- Multisite: main site only; a subsite-only item is `not_installed`.
- License inactive and kill switch: no pass, visible.
- Free logger: waiting row, blocked row, canceled row, pass row; event-key
  dedupe includes the pass id.
- No `POST /flow/run`; the route returns 404; the page has no Run now.

**JavaScript**

- Last-pass summary: counts, waiting reason, empty state, failed step.
- No Run now button; `canRunChain` is removed or unused.
- `waiting` badge intent and label.
- No polling loop; refresh on load.

**E2E**

- Flow page has no Run now; the chain editor still saves.
- A waiting step shows the predecessor and the reason.
- The last-pass card links to the log row.
- No new interactive control, so no new a11y surface beyond the existing
  editor.

**Fixtures**

- Local plugin and theme zips; injected offers through
  `pre_set_site_transient_update_plugins` / `_update_themes`; a synthetic
  `checked` map and `last_checked`; a Free ledger fixture; a clock/filter to
  advance the hold; `pre_wp_mail` capture; `pre_http_request` synthetic
  responses. No external socket.

**Mutation and counterfactual**

- Run `bin/harness mutation` on the readiness and pass-state lines; MSI ≥ 70.
- Run `bin/harness counterfactual` on lines Infection cannot reach.
- The plan must record survivors and equivalents.

---

## 14. Evidence

### 14.1 Commands run

```bash
cd wp-content/plugins/updatronix-pro
bin/harness graft ask "flow planner topological order" --source
bin/harness graft ask "flow runner active run" --source
bin/harness graft grep "no_pending_offer"
bin/harness graft callers Updatronix_Flow_Planner
bin/harness graft skeleton inc/update-engine/class-updatronix-flow-planner.php
bin/harness graft skeleton inc/update-flow/class-updatronix-flow-runner.php
bin/harness graft skeleton inc/update-engine/class-updatronix-update-kernel.php
bin/harness graft skeleton inc/update-flow/class-updatronix-update-flow.php
bin/harness graft skeleton assets/src/js/flowRunState.js
bin/harness graft skeleton inc/settings/options.php
```

No test suite was run: this analysis changes documentation only. The existing
suite results are quoted from `50-update-engine-4.4-flow.md` §5 and
`52-update-flow-phase-5.2.md` (Pro 352 unit, 120 integration, 81 JS; Free
unchanged).

### 14.2 Files read

Pro: `inc/update-engine/class-updatronix-flow-planner.php`,
`class-updatronix-update-plan.php`, `class-updatronix-update-planner.php`,
`class-updatronix-update-job.php`, `class-updatronix-policy-chain.php`,
`class-updatronix-chain-dependency-policy.php`,
`class-updatronix-delay-policy.php`,
`class-updatronix-pending-offer-policy.php`,
`class-updatronix-version-control-policy.php`,
`class-updatronix-engine-policy.php`,
`class-updatronix-shield-enforcement.php`,
`class-updatronix-shield-ownership.php`,
`class-updatronix-update-queue.php`,
`class-updatronix-update-kernel.php`,
`class-updatronix-update-executor.php`,
`class-updatronix-update-journal.php`,
`class-updatronix-queue-scheduler.php`,
`inc/update-flow/class-updatronix-update-flow.php`,
`inc/update-flow/class-updatronix-flow-runner.php`,
`inc/update-shield/class-updatronix-safe-mode.php`,
`inc/settings/options.php`, `assets/src/js/components/UpdateFlowPage.js`,
`assets/src/js/flowRunState.js`, `tests/Unit/UpdateFlowPlannerTest.php`,
`tests/Integration/UpdateFlowRunTest.php`,
`tests/Integration/UpdateFlowRestTest.php`,
`tests/Integration/UpdateQueueLiveFixtureTest.php`.

Free: `inc/classes/class-updatronix-autoupdatedelay.php`,
`inc/classes/class-updatronix-cron.php`,
`inc/classes/class-updatronix-update-logger.php`,
`inc/classes/class-updatronix-logger.php`,
`inc/classes/class-updatronix-security.php`, `inc/settings/options.php`.

Docs: `45-update-engine-architecture.md` §5.5, §5.8, §6–§8, §10–§12;
`50-update-engine-4.4-flow.md`; `52-update-flow-phase-5.2.md`;
`23-update-flow-review.md`; `01-version-lock.md`; `02-update-queue.md`;
the rethink `README.md` and the prompt pack.

### 14.3 What was not verified

1. No staged host with real cron. The day-1/day-2 example is a code walk, not a
   live run.
2. No live Multisite pass. The main-site and Super Admin rules are read from
   the code and the existing tests.
3. No new network call or update API capture. The `checked` map behavior is
   WordPress core behavior, not a measured response on this site.
4. No measurement of the update API's freshness window on a real host. The
   6-hour TTL is the existing Flow warning threshold
   (`class-updatronix-flow-runner.php:310-320`), not an owner decision.
5. No production code, no test run, no version bump, no commit.

### 14.4 QA and grilling records

- Grilling: `updatronix-pro/.agents/notes/2026-10-28-grill-update-flow.md`.
- QA: `updatronix-pro/.agents/notes/2026-10-28-qa-update-flow.md`.

### 14.5 QA disposition

The QA audit found 20 issues. The table records where each one is treated. No
fix is proposed in the audit note; the treatments are the settled decisions in
§9 and the blocking cases in §8.

| QA | Severity | Finding | Treatment |
|---|---|---|---|
| QA-01 | Critical | A missing offer is treated as done; the successor runs out of order. | B1, S1/S3, R1–R3 |
| QA-02 | Critical | A held or blocked predecessor cancels the successor, which is never re-planned automatically. | B2/B5, S3 |
| QA-03 | Critical | A failed or blocked predecessor is a permanent native barrier. | B3, S14 |
| QA-04 | Critical | A skipped or waiting node is not owned; native can update it out of order. | B18, S5 |
| QA-05 | High | Multi-predecessor graphs check only the declaring chain. | B10, S2 |
| QA-06 | High | A manual resume resets attempts; the event key omits the run id. | B15, S6, S10 |
| QA-07 | High | Canceled downstream rows are not journaled. | B15, S6 |
| QA-08 | High | The active-run guard depends on the queue table. | B12, S5 |
| QA-09 | High | Flow forces items that are not opted in. | B7, S11 |
| QA-10 | High | Crash recovery resumes a stale row without re-validating the chain. | B14, S7 |
| QA-11 | Medium | A target change becomes `blocked` and cancels downstream. | B16, S6 |
| QA-12 | Medium | A removed item becomes `uncertain`, not `not_installed`. | B6, S13 |
| QA-13 | Medium | The plan is built before discovery. | §13.1 constraint 3 |
| QA-14 | Medium | Three cycle checks can disagree; one invalid chain stops every chain. | B14, S8 |
| QA-15 | Medium | The run status says `running` for a waiting step. | §6.1, §12.7 |
| QA-16 | Medium | The UI cannot show why a step waits. | B19, §12.7 |
| QA-17 | Low | `collect_offers()` silently slices to 50. | §13.1 constraint 15 |
| QA-18 | Low | Version Lock precedence and the `safe_mode` gate are wrong. | §11.1 |
| QA-19 | Info | Multisite chains use the main-site catalogue. | B20, §13.1 constraint 14 |
| QA-20 | Info | The cooldown is consumed before a failed run. | S10 (route removed) |


---

## 15. Review gate

**Owner review required: read §7 (case matrix) and §8 (blocking cases) before
any plan. Flow implementation waits until Version Lock, Queue, and Schedule are
implemented.**

**Analysis complete; do not implement.**

---

## 16. Implementation addendum (Step 5, 2026-10-29)

The shipped code follows S1–S16 with these implementation choices; the
decisions above are not rewritten.

1. **Plan-time predecessor mode.** The planner resolves each node's own
   readiness and treats only *permanent* predecessor states (`locked`,
   `offer_blocked`, `not_managed`, `not_installed`, `never_ready`) as a wait.
   An eligible predecessor that has not run yet is an execution-order fact: the
   pass gate and the revalidation in `Updatronix_Update_Pass_Runner` apply the
   full "every incoming edge is done" rule. This keeps every chain node in one
   deterministic order and stops plan-time waiting from hiding the whole chain
   behind its head.
2. **`flow_nodes.state` is filled at plan time.** T2 describes it as empty at
   plan time; the shipped planner stores the item-local readiness state (and
   reason/target/manager/evidence) so the pass order and the UI can show it
   without a second resolver. The pass still recomputes waiting at gate and
   finalize.
3. **Temporarily absent offer.** Before the native refresh, an offer can be
   absent because the previous item's install cleared the transient. For an
   unattempted node the revalidation keeps the planned state; the native pass
   either restores the offer or `skip_stalled_node()` records the skip. An
   attempted node with an absent offer is `skipped`, never `succeeded`.
4. **State vocabulary.** `version_locked` from the Version Lock decision is
   recorded as the taxonomy's `version_locked_native`; `blocked_by_predecessor`
   remains `waiting_for_predecessor` with `blocking=true` and a
   `flow_predecessor_*` reason.
5. **Snapshot shape.** The pass `flow_snapshot` carries the T2 plan snapshot
   (`chains`, `nodes`, `edges`) plus the per-node display occurrence
   (`chain`, `sequence`, `occurrences`), the `overflow`/`errors` facts, and the
   last `changes` diff. There is no second store.
6. **Last pass.** The cached summary carries the eight owner-facing counters,
   the first waiting reason, and a compact `flow_nodes` map so the Flow page
   can render a step state after the pass is deleted.
7. **Free changelog fix.** The `pro-104-changelog` theme variant required
   loading `wp-admin/includes/theme.php` before `themes_api()`; the REST
   log-details path did not. The include is the only Free production change
   beyond the stale-copy cleanup.
