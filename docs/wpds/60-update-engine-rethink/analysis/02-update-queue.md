# Update Queue — analysis (Update Shield)

**Date:** 2026-10-28
**Status:** Analysis only. No production code changed, no task file created,
no version bumped.
**Owner:** Quentin.
**Feature owner:** Pro (`updatronix-pro`).
**Shared docs read:** 45 §2.2/§3.1/§4/§5.6–5.9, 46, 47 §4, 48, 50 §2.5,
`60-update-engine-rethink/README.md`, and `analysis/01-version-lock.md`.
**Grilling record:** `updatronix-pro/.agents/notes/2026-10-28-grill-update-queue.md`
(Round 1 and Round 2, both settled).
**QA record:** `updatronix-pro/.agents/notes/2026-10-28-qa-update-queue.md`.

---

## 1. Scope and non-goals

### In scope

- The execution model for automatic updates: one item at a time through
  native WordPress.
- The native `WP_Automatic_Updater::run()` interaction: filters, hooks,
  lock, maintenance mode, fatal-error rollback, discovery, email, and
  `automatic_updates_complete`.
- The persistent state needed to remember the pass and the next item.
- Per-item email and per-item log rows.
- Removal of the custom queue table, kernel, REST routes, summary, Health
  checks, manual actions, and the queue switch.
- Contracts with Version Lock, Flow, Schedule, and the Logger.
- Upgrade, multisite, WP-CLI, manual-update, fatal, and disk-full behavior.

### Non-goals

- Implementing the change. No production file was edited.
- Re-deciding Version Lock's detection or manual policy. Analysis 01 owns
  those decisions; this document consumes them.
- Designing the Flow ordering algorithm. Analysis 03 owns it; this document
  defines the execution contract Flow plugs into.
- Designing the Schedule display and its native countdown override. Analysis
  04 owns it.
- Designing the Logger schema. Analysis 05 owns it; this document states what
  the pass needs from it.
- Changing existing i18n strings without owner approval.

---

## 2. Current behavior

The queue is **dormant in production**: `queue_enabled` is `false` by default
(`inc/settings/options.php:36`), the save path forces it to `false` while
`queue_live` is false (`inc/settings/options.php:103-106`), `queue_live` is a
filter that defaults to `false`
(`inc/update-engine/class-updatronix-queue-rest.php:45-56`), and the Shield
switch is disabled (`assets/src/js/components/UpdateShieldPage.js:948-950`).
A live probe of this site returned:

```json
{"safe_mode":true,...,"queue_enabled":false,...}
```

and `Updatronix Pro: License decryption failed`, so the engine is not
registered here. The grouped behavior the owner observes is the **native
WordPress loop**, not the queue: `WP_Automatic_Updater::run()` iterates every
pending plugin and theme and sends one combined email
(`wp-admin/includes/class-wp-automatic-updater.php:667-704, 1163-1224`).

| Surface | Implementation (file:line) | Actual behavior | Native interaction | Owner match? |
|---|---|---|---|---|
| Dormancy gates | `inc/settings/options.php:29-40, 81-118`; `inc/update-engine/class-updatronix-queue-rest.php:45-56`; `assets/src/js/components/UpdateShieldPage.js:937-955` | `safe_mode` defaults `false`; `queue_enabled` defaults `false`; the save path forces `false` while `queue_live` is false; `queue_live` defaults `false`; the switch is disabled. The one-shot migration meant to clear a stored `true` (`options.php:131-149`) has **no production call site**, so a licensed site with a stored `true` still runs the queue. | None by itself; the engine registers when the license is active (`inc/classes/class-updatronix-pro-bootstrap.php:102`), and the kill switch defaults `true` since 4.4 (`class-updatronix-update-engine-bootstrap.php:50-63`). | Does not match: dormant is not guaranteed by the gate. |
| Plan / tick | `class-updatronix-queue-scheduler.php:48, 89-130, 214-266` | `tick()` runs at `wp_maybe_auto_update` priority 1. It plans Flow first (`:99-102`), then one `run_id` for every offer (`:120-121`). `plan()` inserts one job per offer and evaluates the ordered policy chain at enqueue (`:229-257`). Offers are capped at 50 (`:612`), read from the pre-refresh transients (`:545-613`), and never include translations. | Runs before `WP_Automatic_Updater::run()` at priority 10. `run()` refreshes plugins, themes, and core after the plan (`class-wp-automatic-updater.php:668, 688, 711`). | Partially matches: it serializes, but on stale data. |
| Worker / claim / lease | `class-updatronix-queue-scheduler.php:275-330, 368-386`; `class-updatronix-update-queue.php:522-596, 760-784`; `class-updatronix-update-kernel.php:253-341, 652-729` | `process()` claims one job (`run( 1 )`, `:321`), renews a 15-minute lease, reconciles expired leases, and schedules the next pending row at its `available_at` (capped at one hour). A recurring hourly event and a single follow-up event exist. | No native lock is used at the queue level; the executor acquires `auto_updater.lock` around `update()` (`class-updatronix-update-executor.php:133-143`). | Partially matches: one item per worker, but the lease is unnecessary for the owner's model. |
| Executor | `class-updatronix-update-executor.php:111-199` | Calls `WP_Automatic_Updater::update()` directly (`:193-199`), never `run()`. It clones the offer, sets `autoupdate=true` for plugins/themes, observes `pre_auto_update`, sets the scoped global, and releases the lock in `finally`. | Keeps maintenance mode, the fatal-error loopback, and the temp-backup rollback. Loses the native discovery refresh, the native batch email, and `automatic_updates_complete` for the batch. | Does not match: the owner asked for native execution. |
| Retry / backoff | `class-updatronix-update-kernel.php:401-420, 740-774`; `class-updatronix-update-queue.php:825-855` | Retryable failures return the row to `pending` with 5, 15, or 60 minutes ±20% jitter. Attempts are capped at 3 (filterable to 10). After exhaustion the row becomes `failed`. | None. The native updater does not retry. | Does not match: the owner described a disk-write precaution, not a retry engine. |
| Failure / cancel | `class-updatronix-update-kernel.php:518-539, 919-953`; `class-updatronix-update-queue.php:857-1046` | A terminal failure or block cancels downstream chain rows (`upstream_failed`) and, for Flow, every later row in the run (`cancel_flow_after`). Cancel is manual and refuses a `running` row. | None. | Does not match: a failure can cancel unrelated work. |
| Email | `class-updatronix-update-notifier.php:84-176` | One digest per **run**, only when a step is `failed`, `blocked`, or `uncertain`, deduplicated by `run_id`. Successes are silent. The scheduler runs one job per invocation (`:321`), so a pass can send at most one digest, listing only its first failed/blocked/uncertain item. | The digest is a Pro `wp_mail()` call, not the native email. Recipients come from Free's notification settings (`:31-74`). | Does not match: no per-item email, no success email. |
| Log rows | `class-updatronix-update-journal.php:38-92, 222-267`; `class-updatronix-update-notifier.php:113-131` | One Free log row per transition, keyed by `queue|site|type|id|target|transition|attempts`. Run summaries live in the bounded option `updatronix_pro_update_runs` (20 runs). The digest writes a fake `plugin` row whose `item_id` is the run id. | The Free log is the audit trail. The native loop also writes one row per item through `Updatronix_Update_Logger` (`automatic_updates_complete`). | Partially matches: per-item rows exist, but the pass row is an option and the digest row is noise. |
| Manual actions | `class-updatronix-queue-rest.php:65-140, 226-345` | Five REST routes: list, summary, retry, cancel, run-now. `POST /queue/run` can execute a job outside the automatic pass, even when the queue is off. | None. | Does not match: the owner asked for nothing manual. |
| Queue UI / Health | `assets/src/js/components/UpdateShieldPage.js:937-1120`; `assets/src/js/queueSummary.js:46-124`; `class-updatronix-queue-summary.php:29-271`; `inc/classes/class-updatronix-pro-bootstrap.php:91-95` | The Shield page renders a queue card with six counters, warnings, retry/cancel/run buttons, and a disabled switch. Five Health checks append to Free's Health screen on the main site while the license is active. | Read-only; no native interaction. | Does not match: a queue screen for a feature that should not exist. |
| Schedule interaction | `inc/classes/class-updatronix-autoupdatedelay.php:96-105, 185-251, 570-624`; `class-updatronix-delay-policy.php:68-111` | Free's soak window runs at `auto_update_*` priority 21. The engine's delay policy calls the same Free filter at enqueue. A held offer becomes a `blocked` row. The queue's follow-up can run one second after the tick, independently of the next scheduled check. | A held item is denied to native WordPress by the ownership handshake once its row exists (`class-updatronix-shield-ownership.php:159-190`). | Does not match: the hold can freeze the item (see QA-02). |
| Flow interaction | `class-updatronix-queue-scheduler.php:143-203`; `inc/update-flow/class-updatronix-flow-runner.php:44, 205, 364-405` | `tick()` plans Flow regardless of `queue_enabled` (`:101-115`). The Flow runner enqueues a plan and the kernel drains one step per invocation. Flow and automatic jobs share the queue, the kernel, and the run summary. | Flow uses the native executor per step, not `run()`. | Partially matches: one state machine, but two origins and a second execution path. |
| Multisite | `class-updatronix-update-engine-bootstrap.php:91-107`; `class-updatronix-update-queue.php:73-77`; `class-updatronix-queue-scheduler.php:234`; `class-updatronix-update-kernel.php:1084-1090` | The engine registers on the main site only. The table uses `$wpdb->base_prefix`, so it is network-wide. The scheduler writes `get_current_blog_id()`; the kernel normalizes `site_id` to the main site. | `run()` returns early unless the request is the main network and main site (`class-wp-automatic-updater.php:647-649`). | Matches for the main site; no live Multisite proof. |

### Code that decides the behavior

The scheduler plans every offer into one run and the worker drains one job:

```php
// class-updatronix-queue-scheduler.php:120-124
$run_id = Updatronix_Update_Kernel::new_run_id();
$result = self::plan( Updatronix_Update_Engine::runtime_kernel(), $run_id );

if ( $result['planned'] > 0 ) {
    self::schedule_follow_up( 1 );
}
```

The executor calls `update()`, not `run()`:

```php
// class-updatronix-update-executor.php:193-199
private function native_update( string $type, object $item ) {
    if ( ! class_exists( 'WP_Automatic_Updater' ) ) {
        return new WP_Error( 'updater_unavailable', ... );
    }

    return ( new WP_Automatic_Updater() )->update( $type, $item );
}
```

The ownership handshake denies native WordPress for any barrier row:

```php
// class-updatronix-shield-ownership.php:182-186
if ( class_exists( 'Updatronix_Update_Queue' ) && Updatronix_Update_Queue::table_exists() && ... ) {
    $queue = new Updatronix_Update_Queue();
    $rows  = $queue->get_item_barriers( $type, $id, $target );
    $owned = array() !== $rows;
}
```

The digest is keyed by run, not by item:

```php
// class-updatronix-update-notifier.php:186-188
public static function digest_event_key( string $run_id ): string {
    return hash( 'sha256', 'queue|digest|' . $run_id );
}
```

---

## 3. Native WordPress interaction

### 3.1 `WP_Automatic_Updater::run()`

`run()` is the native automatic-update pass
(`wp-admin/includes/class-wp-automatic-updater.php:642-787`):

1. Returns early when the updater is disabled (`:643-645`) or the request is
   not the main network and main site (`:647-649`).
2. Acquires `WP_Upgrader::create_lock( 'auto_updater' )` (`:651`); returns
   early when the lock is held.
3. Removes the asynchronous discovery hooks (`:662-665`).
4. Refreshes plugins (`wp_update_plugins()`), then loops every plugin offer
   (`:668-685`).
5. Refreshes themes and loops every theme offer (`:688-704`).
6. Refreshes core and updates the core offer if
   `find_core_auto_update()` returns one (`:711-716`).
7. Re-checks themes and plugins with the per-item success stats (`:722-736`).
8. Updates translations last (`:739-751`).
9. Sends the debug email when enabled (`:753-768`), then the core email or the
   plugin/theme email (`:770-774`).
10. Fires `automatic_updates_complete` with `update_results` (`:783`).
11. Releases the lock (`:786`).

The email is **one per run**, not one per item:

- Core: `after_core_update()` → `send_email( 'success'|'fail', … )`
  (`:797-900`).
- Plugins and themes: `after_plugin_theme_update()` collects every successful
  and failed item and calls `send_plugin_theme_email( 'success'|'fail'|'mixed' )`
  once (`:1163-1224`, `:1235+`).
- Translations: no automatic email path.

### 3.2 `WP_Automatic_Updater::update()`

`update()` performs one item (`:362-635`):

1. Builds the upgrader and the filesystem context (`:363-384`).
2. Calls `should_update()` (`:387-389`). When it returns false, `update()`
   returns `false` **before** appending to `update_results` (`:627-632`), so a
   denied item produces no email and no `automatic_updates_complete` entry.
3. Fires `pre_auto_update` (`:401`).
4. Runs the upgrader, maintenance mode, the fatal-error loopback, and the
   rollback handling (`:403-625`).
5. Appends `{item, result, name, messages}` to `update_results` (`:627-632`).

`should_update()` is where the item decision is made (`:195-289`):

- `is_disabled()`, filesystem credentials, and `is_vcs_checkout( $context )`
  (`:199-217`).
- The item's native auto-update setting (`:219-232`).
- `apply_filters( "auto_update_{$type}", $update, $item )` (`:268`).
- Core compatibility checks (`:277-289`).

The filter order on this site is: Free's delay gate at priority 21
(`inc/classes/class-updatronix-autoupdatedelay.php:96-105`), Shield's adapter
at `PHP_INT_MAX - 5`, and the queue's ownership handshake at `PHP_INT_MAX`
(`class-updatronix-shield-ownership.php:42-47`).

### 3.3 What calling `update()` instead of `run()` loses

| Native behavior | `run()` | `update()` (current executor) |
|---|---|---|
| Plugin/theme discovery refresh | Yes (`:668, 688`) | No |
| Core discovery refresh | Yes (`:711`) | No |
| Batch email | Yes (`:770-774`) | No |
| `automatic_updates_complete` for the batch | Yes (`:783`) | No |
| Native order (plugins, themes, core, translations) | Yes (`:667-751`) | No; the kernel order decides |
| `auto_updater.lock` | Yes (`:651, 786`) | Yes, acquired by the executor |
| Maintenance mode | Yes | Yes |
| Fatal-error loopback and rollback | Yes | Yes |
| Core rollback | Yes | Yes |
| Main-network/main-site guard | Yes (`:647-649`) | No (the engine guards separately) |

The owner's "still native" requirement is therefore only half met today: the
item write is native, but the pass — discovery, order, email, and completion
signal — is not.

### 3.4 What a one-item gate changes

`run()` iterates every offer and calls `update()` for each. The gate runs
inside `should_update()` as the `auto_update_{type}` filter. Returning `false`
for every item except the chosen one means:

- The chosen item executes through the native path, with the native lock,
  maintenance mode, rollback, email, and `automatic_updates_complete`.
- The other items return `false` from `update()` before `update_results` is
  appended, so they are **not** marked failed and produce **no** email.
- One item per `run()` means one email per item, because the native email is
  per run.
- The native discovery refresh runs once per invocation, not once per pass.

The gate must **never return `true`**. It returns the incoming `$update`
unchanged for the chosen item, so Free's hold and Shield's lock still apply.
Returning `true` at a late priority would bypass both. Translations are not
gated; they remain in the native loop, last.

---

## 4. Owner expectation

The owner's words, restated in the owner's terms:

1. **Too complicated.** "The current queue is far too complicated. I did not
   ask for a job system with a queue table, leases, retries, backoff, REST
   actions, or manual management."
2. **One by one, natively.** "When automatic updates are triggered
   automatically, instead of launching everything at once, Updatronix creates
   a queue that launches the automatic updates one by one with the native
   WordPress system."
3. **A disk-write precaution.** "Sometimes a disk-write error prevents updates
   after the first one, perhaps because the first update is not completely
   finished. It is a simple precaution."
4. **Nothing manual.** "Nothing manual to manage. It applies only to the
   automatic update process when several items must be updated in the same
   pass."
5. **Ungroup email and log.** "Instead of one email with all updates grouped
   and one log with the grouped process, the owner wants the process executed
   one by one, with one email per item. Still native: the queue ungroups
   automatic updates."

### Match matrix

| # | Owner expectation | Verdict | Evidence |
|---|---|---|---|
| 1 | Simple, no job system | **Does not match** | ~6,500 lines of queue-only PHP, a 26-column table, leases, retries, five REST routes, a queue card, five Health checks. |
| 2 | One by one through native WordPress | **Does not match** | The executor calls `update()`, not `run()` (`class-updatronix-update-executor.php:193-199`); native email, discovery, and completion are lost. |
| 3 | A precaution against a disk-write error | **Partially matches** | The queue serializes one job per worker, but a failed item becomes a permanent native barrier (QA-02, QA-03), so the precaution turns into a freeze. |
| 4 | Nothing manual | **Does not match** | Retry, cancel, run-now, the queue card, and the switch (`class-updatronix-queue-rest.php:65-345`; `UpdateShieldPage.js:1074-1120`). |
| 5 | Automatic updates only | **Partially matches** | Flow shares the kernel and drains even when `queue_enabled` is false (`class-updatronix-queue-scheduler.php:101-115`); `POST /queue/run` can execute jobs outside a pass. |
| 6 | One email per item | **Does not match** | One digest per run, failures only, deduplicated by run id; the scheduler runs one job per invocation, so later failures in the same pass are silent (QA-04). |
| 7 | Ungroup the log | **Partially matches** | The kernel writes one row per item, but the run summary is a separate option and the digest writes a fake item row (QA-10). |
| 8 | Queue off means native | **Partially matches** | Native runs, but `queue_live` does not gate execution (QA-01) and stale barrier rows can deny items (QA-02, QA-05). |

---

## 5. Gap analysis

### 5.1 The central question

**Is the current custom queue/kernel the right implementation, or can it be
replaced by a thin native serialization layer?**

The current implementation is the wrong shape for the owner's request. It
builds a durable job system to do something WordPress already exposes: a
single-item permission decision inside `run()`. The queue is dormant, so it
delivers none of the owner's outcomes in production; when it is live, it
still does not deliver per-item email, it loses the native discovery and
completion semantics, and it freezes failed or held items behind a barrier
that only a manual recheck can clear.

A thin native layer is feasible and is the recommendation:

- `run()` performs the update, so the native lock, maintenance mode,
  fatal-error rollback, discovery, email, and `automatic_updates_complete`
  are preserved.
- One item per invocation is expressed through the existing
  `auto_update_{type}` filters; the gate returns `false` for the other items
  and leaves the chosen item's incoming decision untouched.
- A small non-autoloaded option remembers the pass id, the order, the cursor,
  and the attempt count. No table, no lease, no retry loop, no REST.
- One single follow-up event drains the pass; it never bypasses a hold.
- The native email is per item because each `run()` attempts one item.
- One Free log row per item outcome plus one pass summary row.

The caveats are real and are handled in §7: the order must be built after
`run()` refreshes the transients; the gate must never return `true`; a fatal
error needs an attempt marker written before execution; the pass must be
bounded; and translations are the documented native exception.

### 5.2 Gaps against the owner's expectation

| # | Gap | Impact | Evidence |
|---|---|---|---|
| G1 | No native batch semantics | The owner gets neither the native email nor the native completion signal. | Executor `update()` only. |
| G2 | No per-item email | At most one digest per pass, failures only; successes silent. | Notifier `:84-176`; scheduler `run( 1 )` `:321`. |
| G3 | Dormancy is not gated | A stored `queue_enabled=true` runs while the UI says dormant. | `is_queue_enabled()` `:395-403`; migration never called. |
| G4 | Failed/held items freeze | A terminal row is a barrier; the `job_key` dedupe blocks re-planning; no recheck in the scheduler. | Job `:79-86, 388-411`; ownership `:159-190`; kernel `:196-223`. |
| G5 | Manual surface | Five routes, queue card, retry/cancel/run, switch. | Queue REST `:65-345`; UI `:937-1120`. |
| G6 | Flow shares the queue | Two origins, one kernel, global cancel on failure. | Scheduler `:143-203`; kernel `:942-948`. |
| G7 | Uninstall residue | Run summaries and schema version survive. | Pro uninstall `:78-104`. |
| G8 | Documentation drift | Docs describe a kill switch and defaults that no longer match. | QA-13. |

---

## 6. Complexity inventory

### 6.1 What exists

| Artifact | Kind | Size | Queue-only? |
|---|---|---|---|
| `class-updatronix-update-queue.php` | table, CRUD, claim, lease, sweeper | 1,369 lines | Yes |
| `class-updatronix-update-kernel.php` | enqueue, claim, execute, retry, cancel, reconcile | 1,091 lines | Mostly (also Flow) |
| `class-updatronix-queue-scheduler.php` | tick, plan, worker, cron | 687 lines | Yes |
| `class-updatronix-queue-rest.php` | five REST routes | 412 lines | Yes |
| `class-updatronix-queue-summary.php` | summary + five Health checks | 426 lines | Yes |
| `class-updatronix-update-job.php` | status machine, job key | 529 lines | Yes |
| `class-updatronix-update-notifier.php` | digest email | 268 lines | Yes |
| `class-updatronix-update-journal.php` | Free log adapter + run summaries | 402 lines | Partly |
| `class-updatronix-shield-ownership.php` | `auto_update_*` barrier handshake | 242 lines | Yes |
| `class-updatronix-update-executor.php` | native `update()` adapter | 491 lines | Yes |
| `class-updatronix-update-execution-result.php` | result value object | 159 lines | Yes |
| `class-updatronix-update-engine-bootstrap.php` | loader, kill switch, gates | 290 lines | Yes |
| `class-updatronix-update-engine.php` | facade, kernel factory | 135 lines | Yes |
| `class-updatronix-update-plan.php` | plan value object | 267 lines | Flow |
| `class-updatronix-update-planner.php` | planner contract | 31 lines | Flow |
| `class-updatronix-flow-planner.php` | chain graph → plan | 701 lines | Flow |
| Policy classes | license, engine, native guards, WP/PHP compatibility, delay, version control, abandoned, pending offer, chain | ~1,300 lines | Shield/Flow |
| Shield classes | enforcement, ledger, state | 1,067 lines | Shield |
| Table | `{base_prefix}updatronix_pro_update_queue` | 26 columns, 6 indexes | Yes |
| Options | `updatronix_pro_queue_db_version`, `updatronix_pro_update_runs`, `updatronix_pro_queue_default_migrated`, `queue_enabled` | 4 keys | Yes |
| Transient | `updatronix_pro_queue_rate_{action}_{user}` | 5 s | Yes |
| Cron | `updatronix_pro_process_queue` (hourly), `updatronix_pro_process_queue_now` (single) | 2 hooks | Yes |
| REST | `/queue`, `/queue/summary`, `/queue/{id}/retry`, `/queue/{id}`, `/queue/run` | 5 routes | Yes |
| JS | `queueSummary.js`, `queueSummary.test.js`, the Shield queue card, `useProSettings` keys | ~285 lines + card | Yes |
| Tests | 13 integration/unit files, ~87 test methods | — | Mostly (some also cover Flow and Shield) |

The queue-only PHP is about **6,500 lines** (`update-queue`, `kernel`,
`scheduler`, `queue-rest`, `queue-summary`, `update-job`, `notifier`,
`journal`, `ownership`, `executor`, `execution-result`, `bootstrap`, `engine`).
The whole `inc/update-engine/` directory is 11,220 lines.

### 6.2 Keep / replace / delete

| Artifact | Disposition | Why |
|---|---|---|
| Queue table and repository | **Delete** | A non-autoloaded option holds the pass; no claim, lease, or status rows. |
| Kernel | **Delete** | `run()` owns discovery, lock, and execution; a small pass controller replaces enqueue/claim/retry. |
| Scheduler | **Replace** | A lazy pass controller plus one single follow-up event; no hourly worker. |
| Queue REST | **Delete** | No manual management; no routes. |
| Queue summary + Health | **Delete** | No queue screen; the pass summary is one log row (and optionally a read-only Schedule display owned by analysis 04). |
| Update job value object | **Delete** | The pass state is an array of item keys and attempts. |
| Notifier | **Delete** | Native per-item email replaces the digest. |
| Journal | **Replace** | Keep a thin `record_item()` and `record_pass()` around the Free logger; the pass id goes in the event key. |
| Ownership handshake | **Replace** | A one-item gate replaces the barrier table; no terminal rows to deny native. |
| Executor | **Delete** | The gate lets `run()` execute; no direct `update()` call. |
| Execution result | **Delete** | The outcome is derived from the installed version and the offer after `run()`. |
| Engine bootstrap/facade | **Replace** | A smaller registration: license + main-site guard, gate filters, follow-up action, finalize hook. |
| Policy chain + policies | **Keep** | Version Lock and Shield still decide per item; the gate consumes their `auto_update_*` result. |
| Flow planner + plan objects | **Keep** | Flow becomes the ordering function of the pass; analysis 03 owns the algorithm. |
| Shield ledger/enforcement/state | **Keep** | Unchanged by this feature. |
| Free logger, delay gate, notifications | **Keep** | The pass writes through the Free logger and respects the Free hold. |
| Queue JS + tests | **Delete** | No queue screen, no counters, no manual actions. |
| Docs 46, 48, 50 §2.5 | **Rewrite/delete** | They describe the queue that this analysis recommends deleting. |
| Tests for the queue | **Delete/rewrite** | Replace with pass-driven tests. |

---

## 7. Recommended execution model

### 7.1 Shape

```
WP-Cron ── wp_version_check ── wp_maybe_auto_update ── WP_Automatic_Updater::run()
                                                          │
                                       auto_update_* gate at PHP_INT_MAX
                                                          │
                                    ┌─────────────────────┴─────────────────────┐
                                    │ chosen item: return $update unchanged      │
                                    │ other items: return false                 │
                                    └─────────────────────┬─────────────────────┘
                                                          │
                                          native update() (one item)
                                          native lock, maintenance mode,
                                          fatal rollback, native email
                                                          │
                                          post-run: advance the pass
                                                          │
                                     single follow-up event (time() + 1)
                                                          │
                                          until the pass is drained
```

No table, no lease, no custom email, no REST, no queue screen.

### 7.2 State

One non-autoloaded option on the main site:

```php
// Option: updatronix_pro_update_pass
array(
    'pass_id'    => '32 hex characters',
    'started_at' => '2026-10-28 03:00:00',
    'order'      => array(
        'plugin:foo/foo.php',
        'theme:bar',
        'core:core',
    ),
    'attempted'  => array(
        'plugin:foo/foo.php' => 1,
    ),
    'done'       => array(
        'plugin:foo/foo.php' => 'succeeded',
    ),
    'last_item'  => 'plugin:foo/foo.php',
    'lock_waits' => 0,
)
```

- Bounded to 50 item keys (the current `collect_offers()` cap).
- Deleted when the pass ends and on uninstall.
- The attempt marker is written **before** `run()` executes the item, so a
  fatal error leaves evidence.
- No autoload; one option read and one write per item.

### 7.3 Lifecycle, step by step

**Step 0 — Registration.**

- Register only when the license is active and the request is on the main
  site (the current `Updatronix_Update_Engine_Bootstrap::is_available()`
  rule).
- Add the gate filters at `PHP_INT_MAX` for
  `auto_update_plugin`, `auto_update_theme`, and `auto_update_core`.
  Translations are not gated.
- Add the finalize hook on `shutdown` at priority 1.
- Add the follow-up action `updatronix_pro_update_pass_follow_up`.
- Register nothing else: no cron worker, no REST, no admin panel.

**Step 1 — Pass start.**

- The scheduled `wp_maybe_auto_update` action fires from `wp_version_check()`
  (`wp-includes/update.php:885-891`).
- The pass is created **lazily by the first gate call**, not at
  `wp_maybe_auto_update` priority 1. Reason: `run()` refreshes the plugin,
  theme, and core transients before it loops
  (`class-wp-automatic-updater.php:668, 688, 711`), so a plan built before
  the refresh sees stale offers (QA-11).

**Step 2 — Order build (first gate call, after discovery).**

`Updatronix_Update_Pass::build_order()`:

1. Flow planner output for the enabled chains: topological order, dedupe,
   only items with a pending offer (analysis 03 owns the algorithm).
2. Native default for the remaining offers: plugins in
   `update_plugins->response` order that are in `auto_update_plugins`, then
   themes in `update_themes->response` order that are in
   `auto_update_themes`.
3. Core, if `find_core_auto_update()` returns an offer (or
   `Core_Upgrader::should_update_to_version()` accepts it).
4. Translations are **not** gated: native `run()` processes them last in the
   same invocation. This is the native exception settled on 2026-10-28.
5. Filter: `updatronix_pro_update_pass_order`.
6. Persist the order.

A new offer that appears after the order is built waits for the next pass.

**Step 3 — Gate decision.**

For each `auto_update_plugin|theme|core` call:

1. Read or create the pass.
2. If the order is empty, build it.
3. Compute `$key = $type . ':' . $id` and the target version.
4. If `$key` is not the next unattempted key in the order → return `false`.
   Do **not** mark the item failed; it is simply not this invocation's item.
5. If `$key` is the next key:
   - If `$update` is not `true` → mark `attempted[$key]++`, return
     `$update` unchanged. The Free hold, the Shield block, and the native
     checks all stand.
   - Persist `attempted[$key]++` and `last_item` **before** returning.
   - Return `$update` unchanged (`true`).
6. Never return `true`. The gate is a selection mechanism, not a policy.

**Step 4 — Native execution.**

- `run()` acquires `auto_updater.lock` (`:651`), refreshes discovery, calls
  `update()` for the chosen item, sends the native email, fires
  `automatic_updates_complete`, and releases the lock (`:786`).
- Maintenance mode, the fatal-error loopback, the temp-backup rollback, and
  the core rollback are native.
- The other items produce no `update_results` entry and no email.

**Step 5 — Post-run finalize.**

On `shutdown` priority 1 (and as a fallback after the follow-up callback):

1. Re-read the chosen item's installed version and pending offer.
2. Classify:
   - target installed → `succeeded`;
   - offer gone and target not installed → `skipped` (no longer offered);
   - installed version unchanged and offer remains → `failed` (or `retry`
     when the cause is a lock);
   - item missing → `skipped`.
3. Write one Free log row per item outcome, with the pass id in the event key.
4. If items remain and the pass is younger than the next scheduled check →
   schedule one single event at `time() + 1` that calls
   `do_action( 'wp_maybe_auto_update' )`.
5. If none remain → write the pass summary row, delete the option, and
   unschedule the follow-up.

**Step 6 — Failure handling.**

| Situation | Detection | Pass action | Native safety |
|---|---|---|---|
| Item fails (`WP_Error`) | installed version unchanged, offer remains | `attempted++`, mark `failed`, skip for this pass | Native email reports the failure |
| Fatal error mid-item | request dies after the attempt marker | next request sees the attempt; retry once, then mark `failed` and move on | Native loopback rollback |
| Disk full | `WP_Error`/filesystem error | `failed`, skip for this pass | Native email |
| Lock busy | no progress and `auto_updater.lock` present | follow-up in 5 minutes, cap 3 per item | Native lock |
| Offer gone | transient lacks the key | `skipped`, next item | — |
| Item removed | installed version empty | `skipped`, next item | — |
| Manual update during the pass | target installed | `succeeded`, next item | — |
| Hold or Shield block | `$update` false at the gate | `attempted++`, skip for this pass | Free/Shield policy stands |
| Two cron requests | native lock | one runs, the other returns and reschedules | Native lock |
| New offer mid-pass | not in the stored order | wait for the next pass | — |

A failed item **never blocks the rest of the pass**. It is skipped for this
pass and retried by the next pass, which has a new pass id.

**Step 7 — Pass end.**

- The pass ends when no unattempted item remains in the order, or when the
  next scheduled `wp_maybe_auto_update` tick starts a new pass (the option is
  replaced with a new `pass_id`).
- The pass summary row is the only durable grouping; the option is deleted.

### 7.4 Exact hooks and filters

| Hook | Type | Priority | Purpose |
|---|---|---|---|
| `auto_update_plugin` | filter, 2 args | `PHP_INT_MAX` | Select the one plugin for this invocation; deny the others. |
| `auto_update_theme` | filter, 2 args | `PHP_INT_MAX` | Same for themes. |
| `auto_update_core` | filter, 2 args | `PHP_INT_MAX` | Same for core. |
| `auto_update_translation` | not registered | — | Translations stay native and last. |
| `shutdown` | action | 1 | Finalize the pass after `run()`. |
| `updatronix_pro_update_pass_follow_up` | action | 10 | Drain the next item. |
| `updatronix_pro_update_pass_order` | filter | — | Let Flow and integrations shape the order. |
| `updatronix_pro_update_pass_max_attempts` | filter | — | Attempt cap per item (default 2). |
| `updatronix_pro_update_pass_enabled` | filter | — | Test and emergency switch (default true). |

### 7.5 What the model deliberately does not do

- It does not call `run()` or `update()` itself. WordPress does.
- It does not acquire a lock. `run()` owns `auto_updater.lock`.
- It does not send email. The native email is the per-item email.
- It does not store a queue row, a lease, a status, or a retry schedule.
- It does not expose a REST route, a screen, or a Health check.
- It does not gate translations. They remain in the native loop, last, in the
  same invocation.
- It does not retry inside a request. The next pass retries.

---

## 8. Exhaustive case matrix

Legend: "Queue state" is the state of the current queue (off, on, or a row
status). "Blocking?" asks whether the **current** implementation blocks the
expected behavior. "Decision" points at the settled decision or an open one.

| ID | Eligible items | Origin | Queue state | Schedule / hold | Item type | Expected behavior | Current behavior | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|
| UQ-01 | 0 | native | off | no hold | — | No update, no email, no pass. | Native loop no-op. | No | Settled |
| UQ-02 | 1 | native | off | no hold | plugin | One native update, one email, one log row. | Native loop, one email, one row. | No | Settled |
| UQ-03 | 1 | native | on | no hold | plugin | One pass, one item, one native email. | Plan + one job + a digest only on failure. | Partial | Settled |
| UQ-04 | 2 | native | off | no hold | plugin + plugin | Two invocations, two emails. | One native pass, one grouped email. | No | Settled |
| UQ-05 | 2 | native | on | no hold | plugin + plugin | Two invocations, two emails. | One run id; one job per worker; one digest for the first failure. | Yes | Settled |
| UQ-06 | 2 | native | off | no hold | plugin + theme | Plugin then theme, one email each. | One native pass, one grouped email. | No | Settled |
| UQ-07 | 2 | native | on | no hold | plugin + theme | Two invocations, one email each. | One run, two jobs, digest only. | Yes | Settled |
| UQ-08 | 2 | native | off | no hold | plugin + core | Plugin then core, one email each. | Native loop; core has its own email. | No | Settled |
| UQ-09 | 2 | flow | on | no hold | core + plugin | Flow order: core then plugin, one at a time. | Planner topological order; kernel one job per worker. | Partial | Settled |
| UQ-10 | 1 | native | off | no hold | translation | Native translation update, last in the same invocation; no per-item email. | Native loop. | No | Settled (native exception) |
| UQ-11 | 2 | native | on | no hold | plugin + translation | Plugin serialized; translations run natively last, not serialized. | Queue never plans translations; native updates them in the same pass. | Yes | Settled (native exception) |
| UQ-12 | 1 | native | on | no hold | plugin | Failure logged, skip, next pass retries. | 5/15/60 min retries, then `failed` forever; barrier. | Yes | Settled |
| UQ-13 | 1 | native | on | no hold | plugin | Disk-full failure logged, native email, pass continues. | Retry loop, then `failed`; barrier. | Yes | Settled |
| UQ-14 | 3 | native | on | no hold | plugin ×3 | Items 2–3 update; item 1 retried next pass. | Pending items drain; item 1 is failed/barrier. | Partial | Settled |
| UQ-15 | 2 | native | on | no hold | plugin + plugin | Two emails: one success, one failure. | One digest for the first failure; success silent. | Yes | Settled |
| UQ-16 | 1 | native | on | no hold | plugin | Lock-busy → follow-up in 5 min; no double write. | Executor retries in 5 min; native `run()` returns early with no signal. | Partial | Settled |
| UQ-17 | 1 | native | on | no hold | plugin | Fatal → native rollback; attempt marker; pass moves on. | Lease 15 min, `recovered_after_crash`. | Partial | Settled |
| UQ-18 | 2 | native | on | no hold | plugin + plugin | Fatal on item 1; item 2 still runs after recovery. | Item 2 runs; item 1 is `uncertain`/barrier. | Partial | Settled |
| UQ-19 | 1 | native | on | no hold | plugin | Stale offer re-read; update to the new target or skip. | Plan target 1.1 → `no_longer_offered`; 1.2 waits for the next tick. | Partial | Settled |
| UQ-20 | 1 | native | on | no hold | plugin | Item removed mid-pass → skip, pass continues. | Installed version empty → `uncertain`; barrier. | Yes | Settled |
| UQ-21 | 2 | native | on | no hold | plugin | Two cron requests: one executes, one returns. | Native lock; executor `lock_busy` retry. | No | Settled |
| UQ-22 | 1 | manual + native | on | no hold | plugin | Manual update during a pass → pass sees target installed and skips. | Executor `already_installed`. | Partial | Settled |
| UQ-23 | 1 | native | on | no hold | plugin | New offer after the snapshot waits for the next pass. | Not planned until the next tick. | No | Settled |
| UQ-24 | 1 | native | off | no hold | plugin | Native loop unchanged. | Native loop. | No | Settled |
| UQ-25 | 1 | native | on (stale rows) | no hold | plugin | Engine absent; native loop. | Native loop; pending canceled, terminal rows remain. | Partial | Settled |
| UQ-26 | 1 | native | on | no hold | plugin | No barrier; native loop. | `blocked` rows deny native; item frozen. | Yes | Settled |
| UQ-27 | 1 | native | on | no hold | plugin | Multisite main site only. | Main site only. | No | Settled |
| UQ-28 | 1 | native | on | no hold | plugin | No engine on a subsite. | No engine. | No | Settled |
| UQ-29 | 1 | manual | n/a | n/a | plugin | WP-CLI `wp plugin update` outside the gate. | Outside the gate. | No | Settled |
| UQ-30 | 1 | native | on | no hold | plugin | `wp cron event run wp_maybe_auto_update` → one item. | One item per worker. | No | Settled |
| UQ-31 | 1 | native | on | hold active | plugin | Not updated; next pass after the hold. | `blocked` row + barrier; never re-planned. | Yes | Settled |
| UQ-32 | 1 | native | on | hold expired | plugin | Next pass updates. | Still blocked until a manual recheck. | Yes | Settled |
| UQ-33 | 2 | flow | on | no hold | plugin + plugin | One step per invocation; second after the first. | Flow runner one step per worker. | Partial | Settled |
| UQ-34 | 2 | flow | on | no hold | plugin + plugin | Predecessor fails → downstream waits/skips; run not failed. | `cancel_downstream`; run failed. | Yes | Settled |
| UQ-35 | 2 | flow | on | no hold | plugin + plugin | Locked predecessor → `waiting_for_manager`; downstream waits. | `blocked` + downstream canceled. | Yes | Settled |
| UQ-36 | 5 | native | on | no hold | mixed | Five invocations, five emails, one pass row. | One run; one digest for the first failure. | Yes | Settled |
| UQ-37 | 50+ | native | on | no hold | mixed | Bounded pass; overflow next pass, reported. | Capped at 50, silent. | Partial | Settled |
| UQ-38 | 2 | native | on | no hold | core + plugin | Native order: plugin then core; no security jump. | Native loop: plugin then core. | No | Settled |
| UQ-39 | 1 | native | on | no hold | plugin | Object-cache flush mid-pass → pass survives. | Table survives. | No | Settled |
| UQ-40 | 1 | native | on | no hold | plugin | `DISABLE_WP_CRON` → follow-up needs a cron hit. | Worker needs cron. | No | Settled |
| UQ-41 | 1 | native | on (stored true) | no hold | plugin | Serialization on, or dormant by an explicit gate. | Queue runs while the UI says dormant. | Yes | Settled |
| UQ-42 | 1 | native | on | no hold | plugin | No offer at pass start → no item. | No plan. | No | Settled |

### 8.1 Cases the prompt requires, and where they are

| Required case | Rows |
|---|---|
| 0, 1, 2, N items | UQ-01, UQ-02, UQ-04/05, UQ-36/37 |
| plugin + plugin | UQ-04, UQ-05, UQ-15, UQ-18 |
| plugin + theme | UQ-06, UQ-07 |
| plugin + core | UQ-08, UQ-38 |
| core + plugin (Flow order) | UQ-09 |
| translation | UQ-10, UQ-11 |
| one failure | UQ-12, UQ-13, UQ-14, UQ-15 |
| one lock-busy | UQ-16, UQ-21 |
| one fatal error | UQ-17, UQ-18 |
| one disk-full | UQ-13 |
| one stale offer | UQ-19 |
| one item removed mid-pass | UQ-20 |
| two cron requests | UQ-21 |
| a manual update during a pass | UQ-22 |
| a new offer mid-pass | UQ-23 |
| queue off | UQ-24 |
| license inactive | UQ-25, UQ-26 |
| multisite | UQ-27, UQ-28 |
| WP-CLI | UQ-29, UQ-30 |

---

## 9. Blocking cases and proposed handling

| ID | Case | Why it blocks today | Proposed handling |
|---|---|---|---|
| B1 | A held (soak) item | It becomes a `blocked` row; the handshake denies native; `job_key` prevents re-planning; no scheduler recheck. | The gate leaves the incoming `false` and marks the item attempted for this pass; the next pass re-evaluates the hold. No row, no barrier. |
| B2 | A failed item | It becomes a `failed` row; the next pass cannot re-plan it; native is denied. | Skip for this pass; the next pass has a new pass id and retries once. |
| B3 | A blocked (Shield/Version Lock) item | Same barrier; only a manual recheck clears it. | Skip for this pass; the next pass re-evaluates the policy. The block is logged, not frozen. |
| B4 | Later failures in a pass | The digest is deduplicated by run id; only the first failure emails. | Native per-item email; every attempted item produces its own native email. |
| B5 | A crash mid-item | The lease survives until a worker sweeps it; with the worker unscheduled it survives indefinitely. | The attempt marker is written before `run()`; the next request advances after a bounded retry; no lease. |
| B6 | License lapse | Terminal rows survive; on reactivation they deny native. | No table and no barrier rows; the pass option is deleted on deactivation/uninstall. |
| B7 | Translations | The queue never plans them; native updates them inside the serialized pass. | Owner decision: serialize them last, or leave them native and say so. |
| B8 | Flow predecessor failure | `cancel_downstream()` and `cancel_flow_after()` cancel unrelated rows; the run is failed. | The pass records the item failure and continues; downstream Flow steps wait or skip, per analysis 03. |
| B9 | Manual REST actions | `POST /queue/run` executes outside a pass. | Delete the routes and the UI actions. |
| B10 | Uninstall | The runs option and the schema version survive. | Delete both; drop the table with the queue. |
| B11 | `queue_live` not gated | A stored `true` runs while the UI says dormant. | Delete the switch; serialization is always on, or gate on an explicit `pass_enabled` filter. |
| B12 | Stale plan | The plan is built before `run()` refreshes discovery. | Build the order lazily at the first gate call, after discovery. |

---

## 10. Decisions settled by grilling

The owner answered the four grouped decisions on 2026-10-28 and selected the
recommended option in every group. The full record is in
`updatronix-pro/.agents/notes/2026-10-28-grill-update-queue.md`.

| Q | Settled decision | Owner input |
|---|---|---|
| Q1 · One by one | One item per native invocation, then one single follow-up until the pass is drained. | Execution group |
| Q2 · `run()` vs `update()` | `run()` with a one-item gate; the gate never returns `true`. | Execution group |
| Q3 · Persistence | Non-autoloaded option cursor; delete the table. | State group |
| Q4 · Retries | No automatic retry loop; a failed item is skipped for the pass; the next pass retries. | State group |
| Q5 · Email | Native per-item email, successes included. | Email and log group |
| Q6 · Log | One item outcome row plus one pass summary row. | Email and log group |
| Q7 · Manual management | Remove the queue screen, retry/cancel/run, summary, and Health checks. | Removal group |
| Q8 · Order | Flow orders the items it names; native order for the rest (plugins, themes, core, translations). No security jump. | Execution and Removal groups; Round 2 |
| Q9 · Pass boundary | Starts at the scheduled tick; a follow-up drains it; a held item never bypasses its hold; a new offer after the snapshot waits for the next pass. | Execution and Removal groups; Round 2 |
| Q10 · Schedule | Follow-up may drain the pass but not bypass a hold. | Removal group |
| Q11 · Queue switch | Removed. | Removal group |
| Q12 · Deletion | Table, kernel, queue REST, queue summary, Health, manual actions, switch; keep Flow planner and policy chain. | Removal group |
| Q13 · Flow | Flow becomes the ordering function of the one-item gate; no second execution path. | Execution and Removal groups |
| Q14 · Compatibility | Drop legacy rows on upgrade; main-site only; WP-CLI and manual updates outside the gate; native lock serializes races; fatal and disk-full follow the skip rule. | Removal and State groups |

### Round 2 — the three remaining questions (2026-10-28)

| Question | Owner answer | Settled decision |
|---|---|---|
| Order within a pass | "I don't know, choose the best option." | Delegated. Flow order first for chained items; native default for the rest: plugins, themes, core, translations. A core security release keeps its native position; no jump-the-queue rule. |
| Email | "Native emails." | Native per-item email confirmed, successes included; one item log row plus one pass summary row. |
| Translations and a new offer mid-pass | "The most native close way." | Translations are not gated: native `run()` processes them last in the same invocation, as a documented exception. A new offer that appears after the pass snapshot waits for the next scheduled pass, mirroring native `run()`'s single refresh snapshot. |

The second answer confirms the Round 1 email decision rather than answering
the translation question directly; the translation question is settled by the
third answer, "The most native close way." If the owner meant otherwise, only
the translation exception changes.

---

## 11. Owner decisions (no open question)

**No owner decision remains open.** The three questions left open in Round 1
were answered on 2026-10-28:

1. **Order within a pass.** Delegated to the analysis. Flow order first for
   chained items; native default for the rest (plugins, themes, core);
   translations last; no security jump.
2. **Translations.** The most native-close rule: not gated; native `run()`
   processes them last in the same invocation; no per-item email.
3. **A new offer mid-pass.** The most native-close rule: it waits for the next
   pass, mirroring native `run()`'s single refresh snapshot.

### Residual implementation assumptions

1. **Option name.** `updatronix_pro_update_pass`, non-autoloaded, main site.
2. **Attempt marker.** Written before `run()` executes the item, so a fatal
   error leaves evidence and cannot loop forever.
3. **Pass size.** Bounded to 50 item keys, matching the current
   `collect_offers()` cap.
4. **Attempt cap.** Two attempts per item per pass; a failed item is skipped
   and retried by the next pass.
5. **Lock-busy.** A held `auto_updater.lock` produces a follow-up in five
   minutes, capped at three per item.
6. **Pass lifetime.** The pass ends when no unattempted item remains or when
   the next scheduled `wp_maybe_auto_update` tick starts a new pass.
7. **Legacy state.** The queue table, its options, and the switch are deleted;
   nothing is migrated.

---

## 12. Contracts with Version Lock, Flow, Schedule, and Logger

### 12.1 Version Lock

| Direction | Contract |
|---|---|
| Provides to the pass | A per-item decision (`allowed`, `native_vcs`, `version_controlled`, `unknown`) with `manager`, `scope`, `reason`, and `evidence`. A locked item is excluded at order build; if it is already in the order, the gate leaves the incoming `false` and the pass skips it. |
| Needs from the pass | A plan-time hook (`updatronix_pro_update_pass_order`) and a scoped execution flag (`updatronix_pro_update_pass_executing`) so the optional manual guard allows the pass's own native write. |
| Overlap | One item, one primary reason. Version Lock first, then compatibility, then delay. The pass does not re-evaluate a policy that the gate already denied; it logs the denial once. |

### 12.2 Flow

| Direction | Contract |
|---|---|
| Provides to the pass | An ordered plan: one node per `type:id`, topological order, dedupe, core-last validation, and a `waiting_for_predecessor` fact. Analysis 03 owns the algorithm. |
| Needs from the pass | One execution path; the pass is the only writer. A failed step must not cancel unrelated chains; downstream steps wait or skip with a reason. The pass summary is the run summary. |
| Manual Flow | `POST /flow/run` and the "Run" button are analysis 03's scope; if they survive, they must start a pass, not a second executor. |

### 12.3 Schedule

| Direction | Contract |
|---|---|
| Provides to the pass | The next `wp_maybe_auto_update` timestamp and the soak window for each offer. |
| Needs from the pass | `pass_id`, `started_at`, the ordered items, the current item, the per-item state (`pending`, `held`, `blocked`, `running`, `succeeded`, `failed`, `skipped`), and the next follow-up time. Translations are not pass items; they run natively last. The Schedule screen must not promise a next automatic update for a held or locked item. |
| Native countdown | The native "Automatic update scheduled in …" text is the next check, not the install; analysis 04 owns the override. |

### 12.4 Logger

| Direction | Contract |
|---|---|
| Provides to the pass | One Free log row per item outcome, an idempotent event key, and the bounded query API. |
| Needs from the pass | The pass id, the item, the from/to versions, the outcome, the reason code, and the source (`automatic`, `flow`). The event key must include the pass id; the current key does not, so a later pass can be deduped away (QA-09). |
| Pass row | One row per pass with the counts and the final status. The log table has no pass column today; the pass id travels in the event key until analysis 05 settles the schema. |
| No digest row | The notifier's fake `plugin` row disappears with the notifier (QA-10). |

---

## 13. Implementation constraints and test strategy

### 13.1 Constraints

1. **No second execution path.** Only `run()` executes. The pass never calls
   `update()` or a raw upgrader.
2. **Gate priority.** `auto_update_plugin|theme|core` at `PHP_INT_MAX`, after
   Free's delay at 21 and Shield's adapter at `PHP_INT_MAX - 5`. The gate
   returns the incoming value for the chosen item and `false` for the rest.
   It never returns `true`. Translations are not gated.
3. **Order after discovery.** Build the order at the first gate call, after
   `run()` refreshes the transients. Do not precompute at
   `wp_maybe_auto_update` priority 1. Keep translations native and last.
4. **State.** One non-autoloaded option, bounded to 50 items, with the
   attempt marker written before execution. Delete it at pass end, on
   deactivation, and on uninstall.
5. **No lock of our own.** `run()` owns `auto_updater.lock`. Detect lock-busy
   by the absence of progress, not by a second lock.
6. **One follow-up event.** A single event at `time() + 1`, capped by the
   next scheduled check. No busy loop, no hourly worker.
7. **Failure policy.** A failed, held, or blocked item is skipped for the
   pass; it never blocks the rest. The next pass retries once.
8. **Multisite.** Register on the main site only; the state option is a
   network option; `run()` enforces the main network and main site.
9. **WP-CLI and manual updates.** Outside the gate. The pass detects them by
   re-reading the installed version.
10. **Deletion.** Drop the table, delete `updatronix_pro_queue_db_version`,
    `updatronix_pro_update_runs`, `updatronix_pro_queue_default_migrated`,
    `queue_enabled`, and the rate-limit transients. Clear both cron hooks.
11. **i18n.** New strings need translator comments; the text domain is
    `updatronix-pro` for Pro strings. Do not reword existing strings without
    owner approval.
12. **Security.** No new REST route, no client input, no capability check on
    the cron path. The pass writes only its own option.
13. **Performance.** One discovery refresh per item is the cost of native
    `run()`. Keep the pass bounded (50) and the follow-up immediate. Do not
    add a second discovery pass.
14. **Compatibility.** Existing queue rows are not migrated; the table is
    dropped and the option deleted. A site that stored `queue_enabled=true`
    loses the setting with the switch.
15. **No code in this analysis.** The plan owns the implementation.

### 13.2 Test strategy

**Unit**

- Order builder: Flow order first, native plugin order, theme order, core,
  translations; dedupe; missing offers; a new offer mid-pass.
- Gate decision table: chosen item returns the incoming value; other items
  return `false`; a vetoed chosen item marks attempted and returns `false`;
  the gate never returns `true`.
- Pass state: attempt marker before execution, bounded attempts, pass end,
  option deletion, new pass id on the next tick.
- Finalize: target installed, offer gone, unchanged version, item missing,
  manual update.
- Lock-busy detection and the follow-up cap.
- Failure policy: a failed item does not block the rest.

**Integration** (real WordPress, local fixtures, no network)

- Two plugin offers, one `run()` invocation per item: exactly one install,
  two native emails, two `automatic_updates_complete` entries with one result
  each.
- A translation offer: updated natively last in the same invocation; not
  serialized; no per-item email.
- One success and one failure: both emails, both log rows, the pass row
  counts.
- A held offer: no update, no barrier, the next pass after the hold updates.
- A failed offer: logged, skipped, the next pass retries once.
- A fatal error injected through a filter: the attempt marker survives, the
  next request moves on, the native rollback runs.
- A disk-full error injected through the filesystem filter: failed, skipped,
  the rest of the pass continues.
- Lock busy: a held `auto_updater.lock` produces a follow-up, not a failure.
- Two concurrent `wp_maybe_auto_update` calls: one writes.
- A manual update during a pass: the pass skips the item.
- Multisite: main site only; no state on a subsite.
- WP-CLI: `wp cron event run wp_maybe_auto_update` executes one item;
  `wp plugin update` bypasses the pass.

**JavaScript**

- Delete `queueSummary.test.js` and the queue card tests with the queue.
- If a read-only pass summary is kept on the Shield or Schedule screen, test
  the counters, the dates, and the empty state.
- No new interactive control, so no new a11y surface.

**E2E**

- Native plugin and theme screens are unchanged by the pass.
- The Schedule screen shows the pass and the item states, or says nothing
  when no pass is active (analysis 04 owns the copy).
- No queue screen, no retry/cancel/run buttons.

**Fixtures**

- Local zips, injected offers through
  `pre_set_site_transient_update_plugins`/`_update_themes`, `pre_wp_mail`
  capture, `pre_http_request` synthetic responses. No external socket.
- A fatal-error fixture, a disk-full fixture, and a lock fixture.
- Multisite via `WP_MULTISITE=1 bin/harness integration`.
- No `assertTrue( true )`, no skipped test without a reason, no assertion in
  a `try/catch`.

**Anti-tautology**

- A test that allows two items in one `run()` must fail.
- A test that produces a grouped email for two items must fail.
- A test that leaves a barrier after a failure must fail.

---

## 14. Evidence

### 14.1 Commands run

```bash
cd wp-content/plugins/updatronix-pro
bin/harness graft ask "queue scheduler worker" --source
bin/harness graft ask "native executor update" --source
bin/harness graft grep "queue_live"
bin/harness graft callers Updatronix_Queue_Scheduler
bin/harness wp option get updatronix_pro_shield_settings --format=json
```

The live probe returned `queue_enabled=false`, `safe_mode=true`, and
`Updatronix Pro: License decryption failed`, so the queue is dormant on this
site and the engine is not registered.

### 14.2 Files read

Pro:

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
- `inc/update-engine/class-updatronix-update-engine.php`
- `inc/update-engine/class-updatronix-update-job.php`
- `inc/update-engine/class-updatronix-policy-chain.php`
- `inc/update-engine/class-updatronix-delay-policy.php`
- `inc/settings/options.php`
- `inc/classes/class-updatronix-pro-bootstrap.php`
- `inc/classes/class-updatronix-pro-uninstall.php`
- `assets/src/js/components/UpdateShieldPage.js`
- `assets/src/js/queueSummary.js`
- `assets/src/js/shieldState.js`
- `tests/Integration/UpdateQueueLiveFixtureTest.php`

Free:

- `inc/classes/class-updatronix-autoupdatedelay.php`
- `inc/classes/class-updatronix-update-logger.php`
- `inc/classes/class-updatronix-notifications.php`
- `inc/classes/class-updatronix-logger.php`

WordPress core:

- `wp-admin/includes/class-wp-automatic-updater.php`
- `wp-admin/includes/class-wp-upgrader.php`
- `wp-includes/update.php`

Docs:

- `docs/wpds/45-update-engine-architecture.md` §2.2, §3.1, §4, §5.6–5.9
- `docs/wpds/46-update-engine-4.2-kernel.md`
- `docs/wpds/47-update-engine-4.3a-shield-policies.md` §4
- `docs/wpds/48-update-engine-4.3b-queue-live.md`
- `docs/wpds/50-update-engine-4.4-flow.md` §2.5
- `docs/wpds/60-update-engine-rethink/README.md`
- `docs/wpds/60-update-engine-rethink/analysis/01-version-lock.md`

### 14.3 What was not verified

- A live licensed site with a stored `queue_enabled=true` (the QA-01 case).
- A live Multisite pass.
- A real fatal-error loopback and a real disk-full write.
- A real WP-CLI update against a pass.
- The existing test suites were not run; the tests were read, not executed.
- E2E remains blocked by the host library (`libnspr4.so`).

### 14.4 Bottom line

The custom queue is a well-tested dormant subsystem that solves a problem the
owner did not ask for. It does not produce per-item email, it bypasses the
native pass when it runs, and its barrier model turns a failed or held item
into a permanent native refusal. A thin native layer — one item per `run()`,
a one-item `auto_update_*` gate, a non-autoloaded pass option, one follow-up
event, native email, and one log row per item plus a pass row — meets the
owner's request with a fraction of the code and keeps WordPress's own safety
behavior. The recommendation is to replace, not repair.

---

Analysis complete; do not implement. Ready for plan mode after the other tabs
finish.

**No owner decision remains open.** The three questions were answered on
2026-10-28: the order was delegated to the analysis (Flow first for chained
items, native for the rest, translations last), native emails were confirmed,
and the most native-close behavior was chosen for translations (native, last,
not serialized) and for a new offer mid-pass (it waits for the next pass).

---

## 15. Implementation addendum — Step 4 (2026-10-29)

Shipped as planned with these recorded differences. The settled decisions in
§10–§11 are unchanged.

**Delivered.** `Updatronix_Update_Pass` (option, bounds, lifecycle, summary,
last-pass cache), `Updatronix_Update_Pass_Order`, `Updatronix_Update_Pass_Gate`
(selection only, never `true`, one per request, translations not gated),
`Updatronix_Update_Pass_Runner` (follow-up, shutdown finalize, lock waits,
stale recovery), `Updatronix_Update_Pass_Log` (item row + pass summary through
`Updatronix_Decision_Log`, native-row enrichment via `updatronix_log_context`),
`Updatronix_Update_Pass_Migration`, and `Updatronix_Update_Pass_Health` (three
checks). The queue table, kernel, scheduler, queue REST, queue summary,
notifier, journal, execution-result value object, queue UI, queue switch, and
queue Health checks are deleted. Flow execution is frozen: the runner and the
two run routes are removed and the page shows a notice; the chain editor works.

**Deviations, each verified in the live fixture.**

1. **Plan-time discovery.** `run()` refreshes plugins before the plugin loop but
   themes only before the theme loop, so the first gate call cannot see a theme
   offer. The order builder therefore refreshes `wp_version_check()`,
   `wp_update_plugins()`, and `wp_update_themes()` before collecting offers
   (native's own refresh a moment later reuses the fresh transients). Without
   this, plugin + theme would always split across two passes.
2. **`from` on a node.** Stale recovery needs the from-version to tell a failed
   attempt from a manual update, so the node stores `from` alongside `target`.
3. **Optional pass keys.** `overflow`, `new_offer_deferred`, and
   `translations_deferred` are optional top-level keys; the required shape is
   the one in §7.2 plus the summary's counts.
4. **Stalled node skip.** When a pass node loses its offer before its gate call
   (the item was removed mid-pass), the finalizer marks it `skipped` with
   `not_installed` or `no_longer_offered` and advances; otherwise the pass would
   wait forever for a gate call that never comes.
5. **Native lock lookup.** Lock-busy reads the current site's
   `auto_updater.lock` option (core writes it into the options table), not the
   network option.
6. **Queue Health removed.** The three pass checks are `pass_state`,
   `pass_last_run`, and `pass_follow_up`.
7. **Not exercised locally.** A real core install (no network-free core
   package), a real fatal loopback, and a translation install: the matrix proves
   them at the node/order level and with the gate contract, not with a live
   install. Recorded as a gap for the release checklist.

**Live fixture.** `tests/Integration/UpdatePassLiveFixtureTest` — 11 tests, 80
assertions: two plugins one per invocation with two native emails; plugin +
theme serialized; failure skipped and retried by the next pass; held item never
installed; Version Lock item never installed; bypass restores the native loop;
manual update truthful; lock busy waits five minutes without double-writing;
stale pass replaced; item removed mid-pass skipped; one row per item with no
duplicate “updated”.
