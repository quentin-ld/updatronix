# Update engine architecture — Phase 4.1 discovery / ADR

**Date:** 2026-10-26
**Scope:** The shared update engine behind Update Shield (R-02, R-04, R-07) and
Update Flow (R-09) in `updatronix-pro`, plus the Free log/notification surface it
uses.
**Status:** Discovery complete. This document is the architecture target, the
contracts, the decisions, the migration plan, and the risk register for Phase
4.2 → 4.3 → 4.4. No production file changed, no behavior changed, no version
bumped.
**Owners:** Quentin (product and release). The owner delegated the decision on
2026-10-26; §13.4 records the resolved choices. No **[owner]** decision remains.
**Basis:** docs 22, 23, 31, 33, 34, 43, 44, `FEAT-P-SHIELD`, `FEAT-P-FLOW`,
`FEAT-P-QUEUE`, the Pro QA note `2026-10-24-qa-wpds-conformance.md`, and the code
read on branch `major/wpds` on 2026-10-26.

> **Guardrail.** This ADR proposes one engine, not a second updater. Updates are
> still installed by WordPress. The engine decides, orders, records, and
> serializes; `WP_Upgrader`/`WP_Automatic_Updater` perform the write.

---

## 1. Executive summary

The site currently has three update-decision paths:

1. WordPress's own `WP_Automatic_Updater`, which owns discovery, policy checks,
   locking, execution, fatal-error rollback, and email.
2. Update Shield, which only alters the `auto_update_plugin|theme|core` filter
   result for automatic updates, plus an after-the-fact `upgrader_process_complete`
   observer.
3. Update Flow, which calls `Plugin_Upgrader`, `Theme_Upgrader`, and
   `Core_Upgrader` directly from a cron hook, under its own transient lock.

Those paths overlap, and the third one bypasses the second one. Update Flow can
therefore install an item that Update Shield would have blocked. The queue
switch and four other Shield controls are stored and rendered but have no
consumer.

The target is a single engine with four explicit layers:

- **Policies (Shield):** pure, ordered decisions on a job. They answer
  "may this item update now, and why?"
- **Planner (Flow):** a dependency graph over user-declared chains that emits
  an ordered, deduplicated plan. It answers "what order?"
- **Kernel:** a persistent job queue with claim/lease, retries/backoff, one
  global native lock, a native executor adapter, a journal, and notifications.
  It answers "when, how, and what happened?"
- **Surfaces:** Shield, Flow, logs, Site Health, REST, and admin notices. They
  display the same state; they do not own execution.

The first release of the kernel (4.2) is dormant. Shield becomes a policy
consumer (4.3). Flow becomes a planner on the kernel and its inline runner is
deleted (4.4). No Phase 5 feature is implemented here.

---

## 2. Current state — cartography

### 2.1 Repositories and ownership

| Area | Repository | Notes |
|---|---|---|
| Cron, discovery, native update loop, log table, notifications, Free settings | `updatronix` | Free drives the WordPress update cycle. |
| License, Shield, Flow, White label, Pro REST/assets | `updatronix-pro` | Pro registers Shield/Flow only when the license is active. |
| Docs and feature briefs | `updatronix/docs/wpds` | This ADR is doc 45. |
| Pro task/notes | `updatronix-pro/.agents` | The task file and three audit notes accompany this ADR. |

### 2.2 Free runtime

| Surface | Implementation | Current behavior |
|---|---|---|
| Bootstrap | `inc/classes/class-updatronix-bootstrap.php:23` | Loads classes, creates the log table, registers Cron, update logger, error handler, Settings, Health, Export, Notifications, AutoUpdates, AutoUpdateDelay. |
| Unified discovery | `inc/classes/class-updatronix-cron.php:54-56, 289-302` | On `wp_version_check` priority 9, fires `updatronix_before_update_check`, then refreshes plugin/theme transients when unified scheduling is active. Core's own `wp_version_check` runs at priority 10. |
| Auto-update trigger | `wp-includes/update.php:331-337` | During cron, `wp_version_check()` fires `wp_maybe_auto_update`, which calls `WP_Automatic_Updater::run()`. |
| Execution lock | `wp-admin/includes/class-wp-upgrader.php:1057-1100` | `WP_Upgrader::create_lock( 'auto_updater' )` is an atomic `INSERT IGNORE` into the options table; default release timeout is one hour. `release_lock()` deletes `auto_updater.lock`. |
| Native updater | `wp-admin/includes/class-wp-automatic-updater.php:642-786` | Acquires the lock, refreshes plugins/themes/core, updates each eligible item, handles plugin/theme fatal-error rollback and core rollback, fires `automatic_updates_complete`, releases the lock. |
| Native item decision | `wp-admin/includes/class-wp-automatic-updater.php:196-275` | `should_update()` checks `is_disabled()`, filesystem credentials, `is_vcs_checkout()`, the item auto-update setting, then applies `auto_update_{$type}`. |
| Native item execution | `wp-admin/includes/class-wp-automatic-updater.php:362-636` | `update()` performs the upgrade, maintenance mode, fatal-error detection/rollback for active plugins, core rollback attempt, and records the result. |
| Native VCS detector | `wp-admin/includes/class-wp-automatic-updater.php:127-176` | Walks from the item context up to the filesystem root looking for `.svn`, `.git`, `.hg`, `.bzr`; filterable by `automatic_updates_is_vcs_checkout`. |
| Log table | `inc/classes/class-updatronix-database.php:80-104` | `{prefix}updatronix_logs` on the main site; `site_id`, type, action, item, versions, status, event key, message, trace, actor, context, date. |
| Logger API | `inc/classes/class-updatronix-logger.php:68-203` | `Updatronix_Logger::log( ... 12 args ... )`; allowlists in `class-updatronix-security.php:21-35`; unique `event_key`; fires `updatronix_after_log`. |
| Update journal | `inc/classes/class-updatronix-update-logger.php:26-41` | Hooks `pre_auto_update`, `upgrader_process_complete`, `automatic_updates_complete`, and several upgrader filters to capture version-before and results. |
| Notifications | `inc/classes/class-updatronix-notifications.php:44-53` | Redirects core, plugin/theme, debug, and recovery-mode emails to the configured recipients. |
| Delay gate | `inc/classes/class-updatronix-autoupdatedelay.php:96-105` | Hooks `auto_update_*` at priority 21 and returns `false` while an offer is inside the soak window. It is not a queue. |
| Uninstall opt-in | `inc/classes/class-updatronix-uninstall.php:56-61` | Data is removed only when `delete_data_on_uninstall` is true. |

### 2.3 Pro bootstrap and gating

`inc/classes/class-updatronix-pro-bootstrap.php:50-72` loads every Pro class,
then registers Shield, Flow, Flow runner, and White label only when
`Updatronix_Pro_License_Storage::is_license_active()` is true. The license
handler and updater stay registered so activation and revalidation work. The
capability is `manage_updatronix_pro`; Multisite requires a Super Admin.

`inc/update-shield/class-updatronix-update-shield.php:117-128` and
`inc/update-flow/class-updatronix-update-flow.php:90-100` repeat the license
check in REST permission callbacks. No Shield/Flow REST route exists while the
license is inactive. The Pro JS bundle is still enqueued, but Shield/Flow/White
label chunks are dynamically imported only when
`window.updatronixSettings.licenseActive` is true
(`assets/src/index.js:60-80`).

### 2.4 Update Shield today

| Surface | Implementation | Current behavior |
|---|---|---|
| Settings | `inc/settings/options.php:25-40, 49-111` | Network option `updatronix_pro_shield_settings`; defaults include `safe_mode=false`, `notify_on_block=true`, `control_php_version=true`, `control_wp_version=true`, `incompatible_behavior=disable`, `re_enable=true`, `notify_email=true`, `queue_enabled=true`, `flag_abandoned=true`. |
| REST | `inc/update-shield/class-updatronix-update-shield.php:52-93` | `GET/POST /shield/settings`, `POST /shield/recheck`, `GET /shield/blocked`. |
| Permission | same file `:102-128` | Capability + Super Admin + active license. |
| Blocked history | same file `:218-298` | Reads 200 Free log rows, post-filters `prevented`, `incompatible`, `safe_mode_disabled`, then 30 days, caps at 50. |
| Recheck | same file `:307-382` | Clears last-checked on update transients, calls `wp_update_plugins/themes/version_check`, then disables auto-updates for blocked items. |
| Filters | `inc/update-shield/class-updatronix-safe-mode.php:110-166` | Loads settings once per request; hooks `auto_update_plugin|theme|core` at `PHP_INT_MAX - 5`, admin notices, row warnings, `core_upgrade_preamble`, `shutdown`, `upgrader_process_complete`, `activated_plugin`, `switch_theme`, and auto-update toggle HTML. |
| Plugin/theme block | same file `:239-326` | Only checks items already present in `auto_update_plugins`/`auto_update_themes`; blocks WP/PHP incompatible offers, records the block, defers disabling the auto-update option. It does not use `incompatible_behavior`. |
| Core block | same file `:344-364` | Blocks only when `$update` is truthy and requirements fail. |
| Compatibility | same file `:637-705` | Plugin WP check uses `tested`; theme/core use `requires`; PHP uses `requires_php`; unknown data is allowed. |
| Blocked items | same file `:724-910` | Reads the three native update transients; caches in a non-persistent object-cache group. |
| Block recording | same file `:1080-1178` | Writes a Free log row with `prevented`, `incompatible`, or `safe_mode_disabled`; adds a recipient note to the message. |
| Disable | same file `:1192-1290` | Only disables items currently present in the native auto-update option; core is skipped; actual toggle happens on `shutdown` through `Updatronix_AutoUpdates`. |
| Email | same file `:594-619, 1715-1784` | One plain-text email per request from the in-memory blocked list; recipient comes from Free's notification settings or `admin_email`. |
| UI | `assets/src/js/components/UpdateShieldPage.js` | Protection, version-control lock card, behavior radio, re-enable/email/abandoned switches, queue switch, blocked table. |
| UI claims | same file `:334-498` | The lock card says Git/Composer items are blocked; the queue switch says automatic updates are serialized; the abandoned switch says 12-month warnings; re-enable says it turns auto-updates back on. None has a backend. |

### 2.5 Update Flow today

| Surface | Implementation | Current behavior |
|---|---|---|
| Storage | `inc/update-flow/class-updatronix-update-flow.php:25` | Network option `updatronix_pro_flow_chains`; stores a bare array of chains, not the versioned object named in `FEAT-P-FLOW`. |
| REST | same file `:41-70` | `GET/POST /flow/chains`, `GET /flow/items`. No run route. |
| Permission | same file `:77-100` | Capability + Super Admin + active license. |
| Save | same file `:135-157` | Validates and replaces the whole list atomically. |
| Validation | same file `:232-350` | Name required/≤120, at least two steps, no duplicates in a chain, all installed? no — it does not check installed items, cycle detection across chains, IDs normalized. |
| Cycle detection | same file `:358-416` | Builds consecutive edges across all chains and rejects any cycle. |
| Items | same file `:181-224` | Plugins, themes, and core with `plugin:<file>`, `theme:<stylesheet>`, `core:wordpress`. |
| Runner registration | `inc/update-flow/class-updatronix-update-flow-runner.php:31-32` | Hooks `updatronix_before_update_check`. |
| Runner lock | same file `:24, 55-59, 98-100` | Site transient `updatronix_pro_flow_lock` for 300 seconds; deleted in `finally`. |
| Runner execution | same file `:40-101, 110-158` | Loops enabled chains and steps; calls `Plugin_Upgrader`, `Theme_Upgrader`, or `Core_Upgrader` directly; stops all chains on the first error. |
| Runner logging | same file `:167-232` | Writes `success` or `error` rows to the Free log with `update_context=flow`. It does not record skipped steps, blocked steps, or later steps. |
| Runner policy | same file `:110-158` | No Shield call, no `should_update`, no VCS check, no filesystem preflight, no retry, no backoff, no per-step persistence. |
| UI | `assets/src/js/components/UpdateFlowPage.js` | Chain list, editor, validation, save. The control labelled “Run %s” (`:777`) toggles `enabled`; it does not run a chain. |
| UI claims | `FEAT-P-FLOW`, page copy | “Run on demand” and per-step results are not implemented. |

### 2.6 Storage, transients, locks, and tables

| Kind | Key | Owner | Current use |
|---|---|---|---|
| Option | `updatronix_settings` | Free | Logging, retention, notifications, uninstall opt-in. |
| Option | `updatronix_network_schedule` | Free | Update-check recurrence and soak window; network-scoped. |
| Option | `auto_update_plugins`, `auto_update_themes` | WordPress | Native per-item auto-update choices. |
| Option | `updatronix_auto_update_delay_ledger` | Free | Bounded first-seen ledger for the soak window. |
| Site option | `updatronix_pro_license` | Pro | Encrypted license and status. |
| Site option | `updatronix_pro_shield_settings` | Pro | Shield settings; network-wide. |
| Site option | `updatronix_pro_flow_chains` | Pro | Chain list; network-wide. |
| Site option | `updatronix_pro_white_label` | Pro | Branding; network-wide. |
| Site transient | `update_plugins`, `update_themes`, `update_core` | WordPress | Pending offers and compatibility metadata. |
| Site transient | `updatronix_pro_update_transient` | Pro | License updater cache. |
| Site transient | `updatronix_pro_flow_lock` | Pro | Flow overlap lock (300 s). |
| Option | `auto_updater.lock` | WordPress | Native global auto-updater lock (1 h default). |
| Table | `{prefix}updatronix_logs` | Free | Single update/block history. |
| Cron | `wp_version_check`, `wp_update_plugins`, `wp_update_themes`, `wp_maybe_auto_update` | WordPress/Free | Discovery and auto-update cycle. |
| Cron | `updatronix_cleanup_logs` | Free | Log retention. |

No queue table exists today.

---

## 3. The real update flow

### 3.1 Automatic update, current

1. WP-Cron runs `wp_version_check`.
2. Free's priority-9 callback fires `updatronix_before_update_check`.
3. The Flow runner reads whatever is in `update_plugins`/`update_themes` at
   that moment and may install chain steps. In unified scheduling, the
   plugin/theme transients are refreshed **after** this hook by Free; core's
   transient is refreshed later by `wp_version_check`. Flow therefore makes
   decisions on data that may be up to one cron cycle old.
4. Core's `wp_version_check` refreshes core offers and, because this is cron,
   fires `wp_maybe_auto_update`.
5. `WP_Automatic_Updater::run()` acquires `auto_updater.lock`, refreshes
   plugins/themes/core, and iterates pending items.
6. For each item, `should_update()` checks `AUTOMATIC_UPDATER_DISABLED`,
   filesystem credentials, VCS checkout, and the item's auto-update setting, then
   applies `auto_update_{$type}`. Free's delay gate (priority 21) and Shield
   (priority `PHP_INT_MAX - 5`) can return `false`.
7. For allowed items, `WP_Automatic_Updater::update()` calls the native
   upgrader, enters maintenance mode, detects fatal errors for active plugins,
   restores the temp backup on failure, and records the result.
8. `automatic_updates_complete` feeds Free's update logger. Shield defers
   auto-update disabling to `shutdown` and sends one email for the request.
9. The lock is released.

### 3.2 Flow run, current

1. Same priority-9 hook as above.
2. If the transient lock is free, Flow sets it.
3. For each enabled chain and each step, Flow checks the relevant update
   transient; if the item has no pending offer it silently continues.
4. It calls the raw upgrader. **This bypasses `should_update`, the
   `auto_update_*` filters, Shield compatibility policy, native VCS refusal, and
   the native fatal-error detection/rollback.** Only `Plugin_Upgrader`'s own
   install-failure temp backup remains.
5. On error it writes one log row and returns, stopping every later chain. On
   success it writes one row per step.
6. The transient lock is deleted in `finally`, but a fatal error leaves it until
   its 300-second TTL expires. There is no retry, no per-step state, and no
   backoff; the next cron tick starts from the top, relying on cleared update
   transients for the steps that already succeeded.

### 3.3 Where each promise is decided today

| Promise | Decided by | Enforced at write? |
|---|---|---|
| Item is compatible with WP/PHP | Shield filter | Only for automatic updates through `auto_update_*` |
| Item is version-controlled | WordPress `is_vcs_checkout` | Only inside `WP_Automatic_Updater::should_update`; ignored by Flow |
| Item has no release for 12 months | Nothing | No |
| Auto-update disabled on incompatibility | Shield filter + shutdown | Only for filter path; `incompatible_behavior` ignored |
| Auto-update re-enabled after a later release | Nothing | No |
| Automatic updates are queued | Nothing | No |
| Chain order is respected | Flow runner | Yes, within one chain, until the first failure |
| Chain respects Shield | Nothing | No |
| Failure is visible and retryable | Free log row | Visible, not retryable |
| Crash does not leave a permanent lock | Flow TTL | Eventually, but without state recovery |

---

## 4. Overlaps and defects

### 4.1 Overlap matrix

| Concern | WordPress | Free | Shield | Flow | Target owner |
|---|---|---|---|---|---|
| Discovery | `wp_update_*` / `wp_version_check` | Unified schedule | Reads transients | Reads transients | Free + engine read only |
| Ordering | Plugins → themes → core → translations | — | — | User chains | Flow planner |
| Eligibility | `should_update`, `auto_update_*` | Delay gate | Compatibility/VCS | None | Shield policies |
| Lock | `auto_updater.lock` | — | — | `flow_lock` transient | Engine, using the native lock |
| Execution | `WP_Automatic_Updater` / upgraders | — | — | Raw upgraders | Engine adapter |
| Journal | — | `updatronix_logs` | Writes rows | Writes rows | Free log via one journal adapter |
| Notification | Core emails | Redirects recipients | Own safe-mode email | None | Engine notifier + Free routing |
| UI state | Update screens | Logs/schedule | Shield page | Flow page | Same job/run read model |

### 4.2 Findings

| ID | Severity | Evidence | Impact | Resolution phase |
|---|---|---|---|---|
| UE-101 | **P0** | `class-updatronix-update-flow-runner.php:110-158` calls upgraders directly; Shield only hooks `auto_update_*` at `class-updatronix-safe-mode.php:118-120`. | Flow installs Shield-blocked items. The product's core safety promise is false for Flow. | 4.4, with the policy call mandatory in the engine |
| UE-102 | **P0** | `incompatible_behavior` is read in UI/REST but no code consumes it (`grep` over `inc/` shows only settings/UI). | “Cancel this update only” behaves like “disable auto-updates”. | 4.3 |
| UE-103 | **P0** | `queue_enabled` defaults true and is rendered, but no queue exists. | The Shield screen claims serialization that does not happen. | 4.3 or hide in 5.1 |
| UE-104 | **P0** | Version-control card is driven only by `safe_mode`; there is no detector, no `version_control_lock` key, and Flow bypasses native VCS refusal. | “Blocks Git/Composer items” is false. | 4.3 |
| UE-105 | **P1** | `flag_abandoned` and `re_enable` have no consumer. | Two more false controls. | 4.3 |
| UE-106 | **P1** | Flow runs at `updatronix_before_update_check` before Free refreshes plugin/theme transients. | Chains decide on stale offers; a step may be skipped or use the previous target. | 4.4 (scheduler anchor) |
| UE-107 | **P1** | Flow transient lock is not coordinated with `auto_updater.lock`; raw upgraders do not acquire it. | Flow can write while WordPress's auto-updater is writing. | 4.2/4.4 |
| UE-108 | **P1** | Flow failure stops all chains and leaves no per-step state; retry starts at the top. | No backoff, no terminal state, repeated failure loops. | 4.4 |
| UE-109 | **P1** | `get_blocked_history` loads 200 rows then filters in PHP. | Blocked rows disappear when recent log volume is high; no DB-level date/type filter, no pagination. | 4.3 |
| UE-110 | **P1** | Safe Mode sends one email per request containing blocked items, with no cross-request dedupe. | Site Health or repeated auto-update probes can spam the recipient. | 4.3 |
| UE-111 | **P1** | Flow validation accepts unknown/uninstalled item ids; no max chain/step limits. | A saved chain can reference a removed item; a plan can be unbounded. | 4.4 |
| UE-112 | **P2** | Flow option shape is a bare array, not the versioned object in `FEAT-P-FLOW`; no schema version. | Future migrations have no anchor. | 4.4 |
| UE-113 | **P2** | Flow UI “Run %s” is an enabled toggle, not a run action; no run REST route. | Label implies an action that does not exist. | 4.4 + 5.2 |
| UE-114 | **P2** | `Updatronix_Safe_Mode` captures settings once at registration; REST saves take effect next request. | Acceptable for cron/admin but must be documented for long-running processes. | 4.3 |
| UE-115 | **P2** | Pro has no integration suite; the runner has no live/fixture test. | Queue correctness cannot be proven. | 4.2 |

### 4.3 What is already true and should be kept

- WordPress.org compatibility data is read from native transients with no extra
  API call (`class-updatronix-safe-mode.php:637-705`).
- Unknown compatibility data is allowed, not blocked.
- Synthetic Site Health offers are excluded.
- The Free log table is the single history; `event_key` already provides
  idempotent rows.
- The native VCS detector and `WP_Upgrader::create_lock()` already exist and are
  public.
- Pro's license/capability/Multisite gate is consistent across Shield and Flow.
- Flow validation and cycle detection are good foundations; the planner should
  reuse them rather than replace them.

---

## 5. Target architecture

### 5.1 Principles

1. **One writer.** Only the engine calls an upgrader. Shield and Flow never do.
2. **One lock.** Reuse `WP_Upgrader::create_lock( 'auto_updater', … )`; no second
   transient lock.
3. **One history.** The Free log table is the only durable audit history.
4. **Policy before write, twice.** Decide at enqueue and again immediately before
   execution.
5. **Durable intent.** A job exists in the database before execution starts, so a
   crash is recoverable and visible.
6. **Native executor.** Use `WP_Automatic_Updater::update()` so WordPress keeps
   maintenance mode, fatal-error detection, and temp-backup rollback.
7. **No silent promises.** A control is shipped only with its backend; otherwise
   it is hidden or labelled roadmap.
8. **No legacy path.** When 4.4 lands, the inline Flow runner is deleted, not
   wrapped.
9. **No bypass.** A Flow job and an automatic job run the same policy chain
   before any upgrader. The `auto_update_*` filter is only an ownership
   handshake; it is never the policy.

### 5.2 Component diagram

```
                         Free (updatronix)
  WP-Cron ── wp_version_check ── wp_maybe_auto_update
     │              │                    │
     │              └── refresh offers   └── WP_Automatic_Updater
     │                                         │
     │                                  auto_update_* filters
     │                                         │
     └──────────────┬──────────────────────────┘
                    │
              Pro update engine
     ┌──────────────┴───────────────────────────────┐
     │  Scheduler                                    │
     │    ├── Planner (Flow)  ── Plan                │
     │    ├── Policy chain (Shield) ── Decision       │
     │    └── Queue (claim/lease/backoff)             │
     │                                               │
     │  Executor ── WP_Automatic_Updater::update()   │
     │     └── WP_Upgrader / native rollback          │
     │                                               │
     │  Journal ── Free Updatronix_Logger             │
     │  Notifier ── Free notification settings        │
     └──────────────┬───────────────────────────────┘
                    │
       REST / admin surfaces (Shield, Flow, logs, Site Health)
```

### 5.3 Layers and proposed files

All new Pro files live under `inc/update-engine/` and are loaded by the Pro
bootstrap only when `Updatronix_Pro_Bootstrap::is_license_active()` is true,
except the value objects and repository needed by tests.

| File | Responsibility | Public? |
|---|---|---|
| `class-updatronix-update-job.php` | Job value object, statuses, result codes, `to_array()`/`from_row()`. | Internal |
| `class-updatronix-update-queue.php` | Schema, CRUD, atomic claim, lease, release, sweeper, bounded queries. | Internal |
| `class-updatronix-update-policy.php` | `Policy_Result` value object and `Policy` interface. | Internal |
| `class-updatronix-shield-policy.php` | Compatibility, VCS/Composer, abandoned, license, incompatible behavior, re-enable. | Internal (filters public) |
| `class-updatronix-update-planner.php` | `Plan` value object and planner interface. | Internal |
| `class-updatronix-flow-planner.php` | Chain graph → ordered, deduplicated plan. | Internal |
| `class-updatronix-update-executor.php` | Native executor adapter, lock, revalidation, result mapping. | Internal |
| `class-updatronix-update-journal.php` | Free logger adapter, event keys, blocked rows, run summary. | Internal |
| `class-updatronix-update-notifier.php` | Digest, recipient resolution, dedupe. | Internal (recipient filters public) |
| `class-updatronix-update-scheduler.php` | Cron, enqueue, drain, reschedule, license gate. | Internal (cron hook documented) |
| `class-updatronix-update-engine.php` | Facade: wires queue, policies, planner, executor, journal, scheduler. | Internal |
| `class-updatronix-update-rest.php` | Queue/run REST routes and schemas. | Route contract public |

`Updatronix_Update_Shield` and `Updatronix_Update_Flow` remain the REST
controllers for their screens; they delegate to the engine. They do not contain
execution logic.

### 5.4 Shield policies

Policies are ordered. The first denial wins; all results are recorded.

| Order | Policy | Inputs | Allow condition | On deny |
|---|---|---|---|---|
| 1 | `license` | Stored license status, dev mode | Active or dev mode | `license_inactive`; cancel pending jobs; no email |
| 2 | `engine_enabled` | `queue_enabled`/Flow intent, filter | Engine owns this job | Fall back to WordPress native path |
| 3 | `native_guards` | `AUTOMATIC_UPDATER_DISABLED`, `DISALLOW_FILE_MODS`, `wp_installing()`, filesystem credentials | Native upgrader can write | `environment_disabled` / `filesystem_unavailable` |
| 4 | `wp_compatibility` | Pending offer `tested`/`requires` vs current WP | Compatible or unknown | `wp_incompatible` |
| 5 | `php_compatibility` | `requires_php` vs current PHP | Compatible or unknown | `php_incompatible` |
| 6 | `free_delay` | Free soak ledger; offer first-seen | Offer outside the window, or delay off | `delay_active`; chain stops |
| 7 | `version_control` | Native `is_vcs_checkout()`; Composer detector + `version_control_lock` | Native VCS never; Composer not controlled or lock off | `native_vcs` / `version_controlled` |
| 8 | `abandoned` | Cached WordPress.org `last_updated` | Released within threshold, unknown, or flag off | **Advisory only**; never denies |
| 9 | `pending_offer` | Current transient has the same target version | Offer still pending | `stale_offer` → succeed/skip |
| 10 | `chain_dependency` | Planner state | All predecessors succeeded/skipped | `dependency_failed`; downstream canceled |

Policy semantics:

- **Compatibility** reuses the current Safe Mode logic but is a pure function.
  The site WP/PHP versions are injected for tests. Unknown fields allow.
- **Version control** uses `WP_Automatic_Updater::is_vcs_checkout( $context )`
  for `.git/.svn/.hg/.bzr`; native VCS evidence is authoritative and always
  denies, for automatic and Flow jobs alike. A Composer detector adds
  `composer.json` + `vendor/composer/installed.json` under the item root, with
  `realpath` containment and no symlink traversal. Cache per item and invalidate
  on `upgrader_process_complete`, `activated_plugin`, `switch_theme`,
  `deleted_plugin`, and `deleted_theme`. It fails open with a logged reason;
  `updatronix_pro_is_version_controlled` is the extension point.
  `version_control_lock` (default true) controls only that Composer block.
  Turning it off does **not** override WordPress's native VCS refusal. The UI
  copy must say: “WordPress blocks updates in Git/SVN checkouts; this lock also
  covers Composer-managed items.” The current UI ties the card to `safe_mode`;
  that mapping must be removed.
- **Abandoned** is advisory. It never blocks and never changes auto-updates. It
  produces a badge/row state and, at most, an informational log row. Data source:
  `plugins_api`/`themes_api` `last_updated`, cached 7 days, only for installed
  WordPress.org items with a pending offer and auto-update enabled, capped at 20
  lookups per recheck. Unknown or API error → not flagged.
- **`incompatible_behavior`**:
  - `disable`: deny; if the item is currently auto-update enabled, disable it
    through `Updatronix_AutoUpdates`, record it in the Shield ledger.
  - `cancel`: deny this job only; leave the native auto-update option unchanged.
  - Core has no auto-update option; both values behave as `cancel` for core and
    the UI must say so.
- **`re_enable`**:
  - Only for plugins/themes.
  - Only when the item is in the Shield ledger as “disabled by Shield”, the user
    did not change the toggle since, `safe_mode` is on, `re_enable` is on, and
    the pending offer passes every blocking policy.
  - Only criterion: a **newer target version** than the blocked one. The same
    target is never re-enabled automatically, even after a WP/PHP change; the
    administrator can re-enable it manually. Remove the ledger entry on success
    and log `auto_update_reenabled`.
  - Never re-enable an item the user disabled, and never touch core.
- **Free delay** is authoritative. The engine does not bypass the soak window.
  A delayed offer is `blocked` with `delay_active`; the chain stops. This is a
  Free policy, not a Shield policy, but it uses the same `Policy_Result` shape.
- **Policy adapter outside the engine.** When an automatic item is not
  engine-owned (queue off, or no engine job for it), the Shield
  `auto_update_*` adapter applies the blocking policies and returns `false`; it
  never executes. Engine-owned items pass the adapter and are denied by the
  ownership handshake, so a block is logged once. Shield therefore stays
  effective whether the queue is on or off.
- **Recheck** re-evaluates `blocked` rows for the same target. If the policy now
  passes and the offer is still pending, the row moves back to `pending`;
  otherwise it stays blocked. `failed`/`uncertain` rows require an explicit retry.
- **License** is local. The engine never calls the license API on the execution
  path. If the stored status is inactive, the worker cancels pending jobs with
  `license_inactive` and exits. If the API is unreachable but the stored status is
  active, the queue continues; a successful later revalidation may mark it
  invalid, and the next worker cancels.

### 5.5 Flow planner

The planner is deterministic and side-effect free except for reading the
catalogue and offers.

1. **Validate** the saved chains with the existing rules, plus:
   - every item id resolves to an installed plugin/theme/core;
   - at most 20 chains per plan and 50 steps per chain (filterable);
   - no duplicate step in a chain;
   - no cycle across chains;
   - core appears at most once and only as the last step of its chain.
2. **Build the graph**: one node per `type:id`, one edge per consecutive pair
   in every chain. Merge duplicate nodes. Reject a cycle with the loop path.
3. **Order**: topological sort. Tie-break by chain list order, then step index,
   then item id. This preserves declared order and is stable across requests.
4. **Deduplicate**: one job per `type:id` per target version. The first planned
   occurrence wins; later occurrences are reported as `deduplicated` in the run
   summary. If two chains demand incompatible orders, the cycle check rejects the
   save.
5. **Resolve offers**: look up the current target version in the native
   transients. No pending offer → a `skipped` fact in the run summary, no queue
   row. The chain proceeds.
6. **Apply policies**: blocking denial → a `blocked` fact; the chain stops and
   downstream nodes are `canceled`. Advisory results are attached but do not
   stop the chain.
7. **Emit** a `Plan`: `run_id`, origin, ordered jobs with `chain_id` and
   `sequence`, skipped/deduped/blocked facts, created timestamp.
8. The scheduler persists the jobs. The planner never calls an upgrader.

Planning occurs:
- for automatic runs, on `wp_maybe_auto_update` priority 1 (after discovery,
  before `WP_Automatic_Updater::run()` at priority 10);
- for manual Flow runs, inside `POST /flow/run` after a fresh bounded discovery
  (or a clear “stale data” warning);
- for retries, not re-planned: the persisted job is retried.

### 5.6 Kernel lifecycle

**Automatic ownership handshake.** At `wp_maybe_auto_update` priority 1, the
scheduler persists the plan and records the planned item keys in a request-cached
`$owned` snapshot. It registers `auto_update_plugin|theme|core|translation` at
`PHP_INT_MAX`; the callback returns `false` when the item is in `$owned` and the
engine is **not** executing that exact job. The snapshot also lazily merges the
active queue jobs (`pending`, `claimed`, `running`) **and unresolved terminal
jobs** (`failed`, `uncertain`, `blocked`) for the same item and target, through
one bounded query cached per request on the `(item_type, item_id, status)` index.
A later core run therefore cannot steal a job enqueued in an earlier request, and
a failed or Shield-blocked step remains a barrier until it is retried, rechecked,
or canceled. `succeeded` and `canceled` rows do not block core. WordPress's `WP_Automatic_Updater::run()` at priority 10 does not
execute an engine-owned item. This filter is an ownership handshake, not a
policy: the policy chain already ran at enqueue and runs again before execution.
The executor sets `updatronix_pro_update_engine_executing` around its own
`WP_Automatic_Updater::update()` call; the handshake passes the claimed item
through in that scope so the native `should_update()` path can run. If
`queue_enabled` is false, automatic items are not enqueued; Flow jobs still use
the queue and drain one per worker run. Failed/uncertain items remain in
`$owned` so core does not retry them in the same tick. The snapshot is
request-scoped; its source of truth is the queue table, not a transient. No
transient and no second lock are used for this handshake.

**Enqueue**

1. A scheduler tick (cron or REST run) starts.
2. It checks license, `DISABLE_WP_CRON` state, and whether another run is active.
3. It builds the plan (Flow) or the automatic candidate list.
4. For each job, it runs policies at enqueue, computes `job_key`, and inserts
   with `INSERT ... ON DUPLICATE KEY UPDATE id=id` semantics (ignore duplicates).
5. It records a run summary and schedules the worker.

**Claim**

1. Worker acquires the native lock (`auto_updater`).
2. It sweeps expired leases.
3. It selects the oldest `pending` job whose `available_at <= now` and whose
   dependencies are satisfied, ordered by priority, `sequence`, id.
4. It performs a conditional `UPDATE ... WHERE id = ? AND status = 'pending'`
   and checks affected rows. Zero rows → re-select (bounded).
5. It writes `lease_owner`, `lease_expires_at`, `attempts = attempts + 1`.

**Execute**

1. Re-read the installed version, the pending offer, and the license.
2. Re-run blocking policies. Any denial → `blocked`, journal, release, notify.
3. If the target is already installed → `succeeded`
   (`result_code=already_installed`).
4. If the offer is no longer pending → `succeeded`/`skipped`
   (`result_code=no_longer_offered`).
5. Call the native executor adapter for the item.
6. Read the installed version again and the executor result.
7. Map to `succeeded`, `failed`, `uncertain`, or `blocked`.
8. Write the journal row and the run summary. Release the lease.

**Release**

- Always in `finally`, including policy denial, skip, error, and shutdown.
- The lock is released only after the queue row leaves `running`/`claimed`.
- A fatal error cannot run `finally`; the lease expiry + reconciliation path
  handles it.

**Drain**

- Default batch size 1, filterable up to 5. After a successful job, schedule the
  next single event immediately (`time() + 1`) so a chain does not wait for the
  next recurring tick. This keeps “one at a time” and avoids request timeouts.
  This applies to Flow jobs even when `queue_enabled` is false; that switch only
  controls automatic (non-Flow) jobs.
- If no jobs remain, record the run as complete and stop.
- If `DISABLE_WP_CRON` is true, the queue only drains when an external cron hits
  `wp-cron.php` or `wp cron event run updatronix_pro_process_queue`. Site Health
  reports this as a warning; the engine never loops on a missed cron.

### 5.7 Native executor adapter

The executor must preserve WordPress's safety behavior. The adapter:

1. Requires `wp-admin/includes/admin.php` (or the equivalent
   plugin/theme/file/misc/update includes), `wp-admin/includes/class-wp-upgrader.php`,
   `wp-admin/includes/class-automatic-upgrader-skin.php`, and
   `wp-admin/includes/class-wp-automatic-updater.php`.
2. Builds the item object exactly as the update transient provides it, with
   `autoupdate = true` for the duration of the job so `should_update()` accepts
   a Flow/manual job that the user did not opt into automatic updates for.
3. Sets a scoped global/filter flag
   `updatronix_pro_update_engine_executing` around the `update()` call so the
   ownership handshake passes the claimed item and Shield filters do not apply
   the “auto-update enabled” check a second time; the engine has already
   decided.
4. Calls `( new WP_Automatic_Updater() )->update( $type, $item )`.
5. Maps the return:
   - `true` → `succeeded`;
   - `false` → inspect whether `pre_auto_update` fired for this job. If it did
     not, the native policy refused the write: `blocked` with
     `result_code=native_policy_refused` (the chain stops). If it did, the
     upgrader returned false: `failed` with `result_code=upgrader_returned_false`
     (retryable classification).
   - `WP_Error` → classify retryable/terminal;
   - `null` → read the installed version; `succeeded` if target installed,
     otherwise `failed`/`uncertain`.
6. Does not call `WP_Automatic_Updater::run()`, because `run()` would process
   every pending item and re-acquire the lock. It uses the public
   `update()` method, which is the same method `run()` uses.
7. Holds `WP_Upgrader::create_lock( 'auto_updater', 15 * MINUTE_IN_SECONDS )`
   around the call. If the lock is unavailable, the job is released with
   `available_at = now + 5 minutes`, not failed.
8. Fires `updatronix_pro_update_job_started`, `…_completed`, and
   `updatronix_pro_update_run_completed` for integrations. It does not fake
   `automatic_updates_complete`; Free's update logger still sees the native
   `upgrader_process_complete` rows.

**Why not raw upgraders?** `Plugin_Upgrader::upgrade()` and
`Theme_Upgrader::upgrade()` provide temp-backup restore on install failure, but
the fatal-error loopback check and core rollback live in
`WP_Automatic_Updater::update()`. Calling raw upgraders would silently drop a
safety guarantee. **Why not `run()`?** It processes the whole site, not one job,
and acquires/releases the lock internally; it cannot be used for a queue.

### 5.8 Journal, blocked history, and notifier

**Journal**

- Every job transition writes one row through `Updatronix_Logger::log()`.
- `event_key = sha256( 'queue|' . site_id . '|' . item_type . '|' . item_id . '|' . target_version . '|' . transition . '|' . attempt )`.
  The existing unique key makes duplicate writes idempotent.
- Action types:
  - success/retry/skip → `update` (or `same_version` where appropriate);
  - failure → `failed`;
  - policy block → `prevented` (compatibility/VCS) or `incompatible`
    (advisory detection);
  - auto-update disabled by Shield → `safe_mode_disabled`;
  - auto-update re-enabled → new `auto_update_reenabled` (add through the Free
    allowlist filter in 4.2).
- The queue table stores the operational state; the log stores the history. No
  second history table.
- `log_id` is stored on the queue row when available for a direct “View log”
  link.

**Blocked history**

- `GET /shield/blocked` reads the Free log with a DB-level filter on
  `action_type IN ('prevented','incompatible','safe_mode_disabled')`,
  `created_at >= now - 30 days`, `ORDER BY created_at DESC`, `LIMIT 50`, with
  pagination. It does not load 200 rows and filter in PHP.
- 4.2 adds an optional `action_type` argument or a documented query filter to
  `Updatronix_Logger::get_logs()`. This is additive and does not change existing
  callers.
- Each row carries `id`, item, type, versions, reason, date, and a deep link to
  `tools.php?page=updatronix&tab=logs&log=<id>`. Free's `logDeepLink.js` already
  reads the `log` parameter.

**Notifier**

- One digest per run (or per scheduler request), not one email per item.
- Resolve recipients from Free's `notifications_mode`, `notify_enabled`,
  `notify_emails`, `notify_on`, then Pro's `notify_email`. Never accept a
  client-supplied address.
- Dedupe by `event_key`; the log's unique key is the ledger. Do not email the
  same block for the same target twice.
- Plain text initially; the White Label `updatronix_email_brand_name` filter
  stays the only branding hook.
- Send after the queue row and log row are committed, not before.
- If `notify_email` is off or Free notifications are disabled, write the log
  but send nothing.

### 5.9 Admin integration

| Surface | Target |
|---|---|
| Shield page | Truthful policy rows; queue summary (pending/running/failed/next run); blocked table with working deep links; retry/cancel only when the queue exists. |
| Flow page | Chains with `enabled` (not “Run”); a real Run action calls `POST /flow/run` and polls the run summary; per-step `success/failed/skipped/blocked/canceled`; no claims of automatic dependency discovery. |
| Update logs | Filter by source (`queue`, `flow`, `shield`) and by run; deep link from Shield/Flow. |
| Site Health | Queue table exists, cron scheduled, WP-Cron disabled, stale leases, failed jobs, license inactive; use `WP_Debug_Data`/Site Health test shape. |
| Admin notices | Actionable failures only; capability-gated; dismissible; link to the queue/log. |
| License screen | Update the feature claims to what is actually shipped (already reduced in Phase 3.1). |

---

## 6. Queue data model

### 6.1 Why a custom table

| Alternative | Why it fails |
|---|---|
| Option (`update_option`) | Read-modify-write is not an atomic claim. Two cron workers can read the same list and both process the same item. No ordering, lease, or bounded query. |
| Transient | TTL is not durability. Object-cache eviction, multisite cache scope, and expiration can lose work. No atomic claim or ordering. A crash leaves no state to reconcile. |
| Post meta / CPT | A queue is not content; CPT queries add overhead and semantics that do not fit claim/lease. |
| Native `WP_Upgrader::create_lock` only | The lock serializes processes but does not persist intent, order, attempts, or results. It also cannot represent a chain. |
| Custom table | Atomic conditional claim, indexes for ordering and status, lease expiry, idempotent `job_key`, bounded retention, and crash reconciliation. `dbDelta` and a schema option make it versioned. |

### 6.2 Queue schema

Main-site, network-scoped table, created with `dbDelta` through
`updatronix_with_main_site()` (same pattern as the Free log table):

```
{prefix}updatronix_pro_update_queue
  id                bigint unsigned NOT NULL AUTO_INCREMENT
  site_id           bigint unsigned NOT NULL DEFAULT 0
  run_id            char(32) NOT NULL DEFAULT ''
  job_key           char(64) NOT NULL
  item_type         varchar(20) NOT NULL DEFAULT 'plugin'
  item_id           varchar(191) NOT NULL DEFAULT ''
  from_version      varchar(64) NOT NULL DEFAULT ''
  target_version    varchar(64) NOT NULL DEFAULT ''
  origin            varchar(20) NOT NULL DEFAULT 'automatic'
  chain_id          varchar(64) DEFAULT NULL
  sequence          int unsigned NOT NULL DEFAULT 0
  priority          smallint NOT NULL DEFAULT 10
  status            varchar(20) NOT NULL DEFAULT 'pending'
  attempts          smallint unsigned NOT NULL DEFAULT 0
  max_attempts      tinyint unsigned NOT NULL DEFAULT 3
  available_at      datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
  lease_owner       varchar(64) DEFAULT NULL
  lease_expires_at  datetime DEFAULT NULL
  last_error_code   varchar(64) NOT NULL DEFAULT ''
  last_error_message text DEFAULT NULL
  policy_snapshot   longtext DEFAULT NULL
  result_code       varchar(64) NOT NULL DEFAULT ''
  log_id            bigint unsigned DEFAULT NULL
  created_at        datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
  updated_at        datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
  finished_at       datetime DEFAULT NULL
  PRIMARY KEY  (id)
  UNIQUE KEY job_key (job_key)
  KEY claim (status, available_at, priority, sequence, id)
  KEY site_status (site_id, status)
  KEY item_status (item_type, item_id, status)
  KEY lease (lease_expires_at)
  KEY run (run_id)
```

All timestamps are UTC (`current_time( 'mysql', true )` / `gmdate`). The site
timezone is a display concern.

### 6.3 Statuses and transitions

```
pending ──claim──> claimed ──start──> running ──success──> succeeded
   │                  │                  │
   │                  │                  ├──terminal error──> failed
   │                  │                  ├──policy deny─────> blocked
   │                  │                  └──partial state───> uncertain
   │                  │
   │                  └──lease expired──> pending | failed | uncertain
   │
   ├──cancel (user/queue off/license off)──> canceled
   └──sweeper (old)──> failed | canceled

blocked ──recheck (policy now passes, target still offered)──> pending
blocked ──recheck (policy still denies)──────────────────────> blocked
failed | uncertain ──explicit retry──────────────────────────> pending
failed | uncertain | blocked ──explicit cancel──────────────> canceled
```

`skipped` is a run-summary fact, not a queue status, because no write is
attempted. `uncertain` means the installed version is neither the from-version
nor the target after a crash; it requires admin review and is never auto-retried.
A `blocked` row is not silently retried; only the Shield recheck or an explicit
retry can move it. A `failed`/`uncertain`/`blocked` row is a barrier for the
native auto-updater until it is retried, rechecked, or canceled.

### 6.4 Claim, lease, and idempotence

- `job_key = sha256( site_id | item_type | item_id | target_version | origin_scope )`.
  `origin_scope` is `automatic`, `flow:<chain_id>`, or `manual`; a chain and an
  automatic run for the same target reuse one row (the first origin wins).
- Unique `job_key` makes re-planning idempotent. A new release produces a new
  key. A failed job is not resurrected by re-planning; it needs an explicit
  retry.
- Claim is a conditional update:
  `UPDATE … SET status='claimed', lease_owner=?, lease_expires_at=?, attempts=attempts+1 … WHERE id=? AND status='pending'`.
  Affected rows must be 1. This is atomic on InnoDB without `SKIP LOCKED`, which
  keeps MySQL 5.7 compatibility (WordPress 6.2 minimum).
- `lease_owner` is `wp_generate_uuid4()` plus a host/pid suffix; it is only a
  diagnostic. `lease_expires_at` is `now + 15 minutes` by default, filterable.
  The worker refreshes it after the policy recheck and before the upgrader call.
- The queue never relies on a PHP process staying alive. A lease is a
  time-bounded claim, not a heartbeat.

### 6.5 Crash recovery

The sweeper runs at every worker start and on an hourly cron event:

1. Select `claimed|running` rows with `lease_expires_at < now`.
2. Re-read the installed version.
3. If installed == target → `succeeded`, `result_code=recovered_after_crash`,
   journal.
4. If installed == from_version:
   - attempts < max → `pending`, `available_at = now + backoff(attempts)`;
   - attempts >= max → `failed`, `result_code=attempts_exhausted`.
5. Else → `uncertain`, `result_code=partial_state`, no auto-retry; journal and
   notify. The UI offers “Inspect” and an explicit retry.
6. Release the native lock if this process owns it (it does not after a crash;
   the lock expires on its own).

### 6.6 Retry and backoff

| Attempt | Default delay | Jitter |
|---|---|---|
| 1 | 5 min | ±20% |
| 2 | 15 min | ±20% |
| 3 | 60 min | ±20% |
| > max | terminal `failed` | — |

Retryable: HTTP/download errors, `fs_unavailable`, `locked`, temporary database
errors, filesystem credential/ownership errors that may resolve. Terminal:
policy blocks, `incompatible_archive`, `bad_request`, `folder_exists`,
`up_to_date` (normalize to success/skip), invalid package, unknown step. Disk
full is retryable with a long delay; after max attempts it is terminal and
visible. All lists are filters.

### 6.7 Retention

- Keep terminal rows for 30 days (filterable), then delete in batches of 500 on
  the hourly sweep. Pending/failed/uncertain rows are never deleted by age while
  they are actionable.
- Run summaries (including skipped/deduped facts) live in a non-autoloaded,
  bounded option `updatronix_pro_update_runs`, last 20 runs, not a second
  history table. The log remains the durable history.

### 6.8 Uninstall

**Decision:** drop the queue table on uninstall even when Free's
`delete_data_on_uninstall` is false, because a queue is operational state and a
stale pending queue after reinstall is dangerous. Run the drop only from the
main site with the network-admin/WP-CLI guard used by Free's uninstall. Keep
settings and logs under the Free opt-in. This is a documented divergence from
`FEAT-P-QUEUE`; the queue is not user history.

---

## 7. Failure semantics

### 7.1 What WordPress can and cannot do

**Can:**

- Restore the previous plugin/theme directory on an install failure through the
  `upgrade-temp-backup` directory (`class-wp-upgrader.php:607-614, 922-936,
  1199-1240`).
- Detect a fatal error in an updated active plugin through the native loopback
  check and restore the temp backup (`class-wp-automatic-updater.php:533-600`).
- Attempt a core rollback when WordPress.org provides a rollback package and the
  update fails (`class-core-upgrader.php:181-255`).
- Report a `WP_Error` and keep the site in maintenance mode only for the
  duration of the request.
- Serialize automatic updates with the `auto_updater.lock`.

**Cannot:**

- Roll back a database migration performed by the new plugin/theme version.
- Guarantee the previous files after a hard kill, power loss, disk failure, or a
  fatal error outside the loopback window.
- Roll back an update that “succeeded” but is semantically broken.
- Roll back a core update when no rollback package exists.
- Detect dependency incompatibilities that WordPress.org does not publish.
- Guarantee that a plugin's activation hook or cron jobs are reversible.

### 7.2 What the engine promises

1. One write at a time per site; no Updatronix process runs concurrently with
   another or with WordPress's auto-updater.
2. The native executor path, including native temp backup and fatal rollback,
   is used for every job.
3. A durable row exists before the write; every attempt has a terminal or
   retry-scheduled state.
4. A crash or timeout does not leave a permanent lock: the lease expires and the
   sweeper reconciles.
5. A policy-blocked item is never written.
6. A chain stops at the first failed/blocked/uncertain step; downstream steps are
   canceled and recorded.
7. Failures are visible in the log, the queue, and the admin surface with a
   reason and a next action.
8. No silent retry after an `uncertain` partial state.
9. No second history table; the Free log is the audit trail.
10. No email to an address the client supplies.

### 7.3 What the engine refuses to promise

1. Full-site rollback, database rollback, or “undo”.
2. That the previous version is always restored.
3. Zero downtime.
4. Compatibility beyond the available WordPress.org metadata.
5. Automatic dependency discovery; chains are user-declared.
6. That a third-party update will not break the site.
7. That a queue survives a deleted database or a wiped filesystem.

### 7.4 Recovery matrix

| Failure | Queue state | Automatic action | User action |
|---|---|---|---|
| Download/HTTP error | `pending` with backoff | Retry up to max | Retry now |
| Filesystem not writable | `failed`/`pending` after preflight | Stop; notify | Fix permissions, retry |
| Disk full | `pending` with long backoff, then `failed` | Retry bounded | Free space, retry |
| Native upgrader returns `WP_Error` | `failed` or `pending` if retryable | According to classification | Inspect log, retry |
| Fatal error, native rollback succeeded | `failed` (`rollback_successful`) | No auto-retry | Review, retry |
| Fatal error, native rollback failed | `failed` (`rollback_failed`) | No auto-retry; critical notice | Restore backup manually |
| Crash/timeout, target installed | `succeeded` (`recovered_after_crash`) | None | — |
| Crash/timeout, from-version installed | `pending` or `failed` | Retry/backoff | Retry if terminal |
| Crash/timeout, unknown version | `uncertain` | None | Inspect, retry |
| Policy block | `blocked` | None | Fix incompatibility/VCS |
| License inactive | `canceled` (`license_inactive`) | No execution | Reactivate |
| Chain step failed | `failed`; downstream `canceled` | None for downstream | Fix step, run chain again |
| No pending offer | Run summary `skipped` | Chain proceeds | — |
| Lock busy | `pending`, +5 min | Retry | — |

---

## 8. Gating, security, and Multisite

### 8.1 License

- The engine is registered only when Free is active and the stored license is
  active (or dev mode). This mirrors the current bootstrap.
- The license is checked again at every worker start and before every job. No
  network call.
- Deactivation:
  - unschedule the queue cron;
  - cancel pending automatic jobs with `license_inactive`; Flow chains and
    settings stay intact;
  - leave Flow chains and settings intact;
  - remove Shield/Flow REST routes and hooks on the next request.
- Reactivation: reschedule the cron; pending jobs may be re-planned, but failed
  or uncertain jobs stay terminal until retried.
- License API unavailable: the stored `active` status continues to work. A later
  successful revalidation may mark it `invalid`; the next worker cancels.
- Dev mode: active, but the queue only runs on a local host because dev mode is
  domain-locked.

### 8.2 Capability and nonce

- REST: `manage_updatronix_pro`; Super Admin on Multisite; active license.
- WordPress REST cookie nonce is enforced by core for cookie auth. Application
  passwords intentionally bypass nonces; document it.
- Cron/worker entry: no capability (it is system work), but it must check the
  license and the engine enable flag. A public HTTP trigger for the worker is
  forbidden; use WP-Cron or WP-CLI.
- Manual retry/cancel/run: capability + license + a per-user rate limit.

### 8.3 Multisite

- `WP_Automatic_Updater::run()` only runs on the main network and main site
  (`class-wp-automatic-updater.php:644-648`). The queue is therefore main-site
  only.
- The table is created on the main site with `updatronix_with_main_site()`;
  `site_id` is stored for future per-site manual runs.
- Non-Super-Admin users cannot see Pro destinations or call Pro REST.
- Do not schedule the queue worker on subsites.
- Network activation and uninstall follow the main-site/network-admin guard used
  by Free's uninstall.

### 8.4 Data and input validation

- REST never accepts a package URL, a filesystem path, a callable, a shell
  command, or an arbitrary item id. It accepts a chain id, a queue id, or a
  run id. The engine resolves installed items server-side.
- Item ids are normalized and compared to the catalogue.
- `target_version` comes from the update transient, never from the client.
- All queue writes use `$wpdb->prepare`/`$wpdb->insert`/`$wpdb->update`; table
  names are plugin-controlled.
- Policy snapshots are JSON-encoded bounded arrays; no serialized objects.
- Log messages are escaped on output; the logger sanitizes on write.

### 8.5 What must not exist when the license is inactive

- No `updatronix_pro_process_queue` cron event.
- No Shield/Flow REST route.
- No `auto_update_*` filter from Shield.
- No engine panel or queue summary.
- No email from Shield/Flow.
- The license screen remains available.

---

## 9. Native WordPress first

| Need | Native API | Decision | Justification |
|---|---|---|---|
| Global execution lock | `WP_Upgrader::create_lock( 'auto_updater', … )` | Use | Already used by core; atomic option insert; one lock for both paths. |
| Item execution | `WP_Automatic_Updater::update()` | Use | Keeps maintenance mode, fatal-error detection/rollback, core rollback, and active-plugin handling. |
| Plugin/theme/core upgraders | `Plugin_Upgrader`, `Theme_Upgrader`, `Core_Upgrader` | Only through `update()` | No raw calls. |
| Scheduling | WP-Cron | Use | Recurring worker + single follow-up events. No custom loop. |
| Settings | Options / site options | Use | Existing pattern. |
| REST | `register_rest_route`, `WP_REST_Request/Response` | Use | Existing pattern. |
| Capability | `current_user_can`, `is_super_admin` | Use | Existing pattern. |
| Compatibility data | Native update transients | Use | No extra API call; unknown allowed. |
| VCS detection | `WP_Automatic_Updater::is_vcs_checkout()` | Use | Covers Git/SVN/Hg/Bzr; filterable. |
| Health | Site Health tests / `WP_Debug_Data` | Use | Surface queue/cron issues where WordPress expects them. |
| Email | `wp_mail` + Free notification filters | Use | Recipient routing stays in Free. |
| Logging | Free `Updatronix_Logger` | Use | Single history; event key idempotence. |
| Queue | Custom table | Justified | No native queue with atomic claim/lease/order. |
| Chain planner | Custom class | Justified | No native dependency-order API; user-declared chains. |
| Composer detection | Custom detector | Justified | Native VCS does not cover Composer. Fail open, filterable. |
| Abandoned data | `plugins_api`/`themes_api` + cache | Justified | No native “abandoned” flag; advisory only. |
| Notification dedupe | Custom ledger/event key | Justified | Native emails are per-update and not policy-aware. |
| Flow run history | Bounded option | Justified | Ephemeral run summary; durable history stays in the log table. |

---

## 10. Public contracts

### 10.1 Stable

Stable means documented here, versioned, and additive-only within `v1`.

**REST routes (namespace `updatronix-pro/v1`)**

| Method | Route | Permission | Purpose |
|---|---|---|---|
| `GET` | `/shield/settings` | capability + Super Admin + license | Current Shield settings and status summary. |
| `POST` | `/shield/settings` | same | Partial settings save. |
| `POST` | `/shield/recheck` | same | Refresh compatibility data and re-evaluate. |
| `GET` | `/shield/blocked` | same | Blocked history, 30 days, paginated. |
| `GET` | `/queue` | same | Queue rows, filtered by status/run, paginated. |
| `GET` | `/queue/summary` | same | Counts, next run, stale leases, last error. |
| `POST` | `/queue/{id}/retry` | same | Retry a failed/uncertain job. |
| `DELETE` | `/queue/{id}` | same | Cancel a pending job. |
| `POST` | `/queue/run` | same | Process the next job now (bounded). |
| `GET` | `/flow/chains` | same | Chains + item catalogue (existing shape). |
| `POST` | `/flow/chains` | same | Atomic chain save (existing shape). |
| `GET` | `/flow/items` | same | Catalogue only. |
| `POST` | `/flow/run` | same | Run one chain or all enabled chains; returns `run_id`. |
| `GET` | `/flow/runs/{run_id}` | same | Run summary with per-step results. |

**Response shapes**

`Job` (stable fields):

```json
{
  "id": 42,
  "run_id": "b7c1…",
  "item": { "type": "plugin", "id": "woocommerce/woocommerce.php", "name": "WooCommerce" },
  "from_version": "9.1.0",
  "target_version": "9.2.0",
  "origin": "flow",
  "chain_id": "payments-first",
  "sequence": 3,
  "status": "failed",
  "attempts": 1,
  "max_attempts": 3,
  "available_at": "2026-10-26T15:00:00Z",
  "lease_expires_at": null,
  "result_code": "download_failed",
  "error": { "code": "download_failed", "message": "…", "retryable": true },
  "log_id": 128,
  "created_at": "2026-10-26T14:55:00Z",
  "finished_at": "2026-10-26T14:56:00Z"
}
```

`RunSummary` (stable fields): `run_id`, `origin`, `started_at`, `finished_at`,
`status`, ordered `steps[]` with `item`, `status`, `reason`, `job_id`,
`log_id`, plus `deduplicated[]` and `skipped[]` facts.

`ShieldPolicyResult` (stable fields): `allowed`, `code`, `message`, `severity`,
`policy`, `metadata`.

**Hooks/filters**

| Hook | Type | Stability | Contract |
|---|---|---|---|
| `updatronix_pro_shield_defaults` | filter | stable | Existing; defaults array. |
| `updatronix_pro_is_version_controlled` | filter | stable | `(bool $controlled, string $type, string $id, string $path)`. |
| `updatronix_pro_update_engine_enabled` | filter | stable | Kill switch; default false in 4.2, true after 4.3 is accepted. |
| `updatronix_pro_queue_batch_size` | filter | stable | Int, 1–5. |
| `updatronix_pro_queue_lease_seconds` | filter | stable | Int, default 900. |
| `updatronix_pro_queue_max_attempts` | filter | stable | Int, default 3. |
| `updatronix_pro_queue_backoff` | filter | stable | `(int $delay, int $attempt, string $code)`. |
| `updatronix_pro_queue_retryable_errors` | filter | stable | `string[]`. |
| `updatronix_pro_flow_plan` | filter | stable | `(Plan $plan, array $chains)`. |
| `updatronix_pro_flow_items` | filter | stable | Catalogue array. |
| `updatronix_pro_policy_result` | filter | stable | `(Policy_Result $result, Job $job)`. |
| `updatronix_pro_update_job_before_execute` | action/filter | stable | Filter may return `WP_Error` to abort. |
| `updatronix_pro_update_job_completed` | action | stable | `(Job $job, mixed $result)`. |
| `updatronix_pro_update_run_completed` | action | stable | `(string $run_id, array $summary)`. |
| `updatronix_pro_abandoned_threshold_days` | filter | stable | Int, default 365. |
| `updatronix_pro_abandoned_data` | filter | stable | `(array|null $data, string $type, string $id)`. |
| `updatronix_pro_notification_recipients` | filter | stable | `(string[] $recipients, string $context)`. |
| `updatronix_log_action_types` | filter (Free) | stable | Additive allowlist for typed rows. |
| `updatronix_log_query_args` | filter (Free) | stable | Additive query args (including `action_type`). |
| `updatronix_after_log` | action (Free) | stable | Existing; row id + data. |

### 10.2 Internal

These may change without notice; do not build integrations on them:

- Class names, constructors, method signatures in `inc/update-engine/`.
- Table name and columns other than the documented status/lease semantics.
- `job_key` hash inputs and algorithm.
- Internal hooks: `updatronix_pro_queue_claimed`,
  `updatronix_pro_queue_released`, `updatronix_pro_queue_swept`,
  `updatronix_pro_plan_built`, `updatronix_pro_policy_evaluated`.
- Log message strings and `event_key` format.
- The bounded `updatronix_pro_update_runs` option shape.
- The `updatronix_pro_process_queue` cron hook name (it is an implementation
  detail; use the REST routes or WP-CLI).
- The exact `WP_Error` codes beyond the documented result codes; treat unknown
  codes as terminal unless `retryable` is true.

### 10.3 Data shape versioning

- Flow chains: `updatronix_pro_flow_chains` becomes
  `{ "version": 1, "chains": [ … ] }`. The current bare array is migrated once
  on upgrade, then only v1 is accepted. No dual-read shim.
- Queue: schema version option `updatronix_pro_queue_db_version`; `dbDelta` on
  upgrade; no destructive migration without a backup notice.
- Shield settings: additive keys only; unknown keys discarded; existing keys
  keep their meaning. `version_control_lock` is added with default true.
- Public REST: additive fields only; no field removal in v1.

---

## 11. Test strategy

### 11.1 Prerequisites

- Pro has no integration suite today (0 files). 4.2 must add
  `tests/Integration/` with the WordPress test bootstrap used by Free and a
  `bin/harness integration` path. Without it, the queue/DB claims are unproven.
- Pro has no E2E config. 4.4 can run a WP-CLI-driven live fixture test without
  Playwright; a Pro E2E suite remains Phase 6.
- The Free log table and Free notification settings are dependencies; their
  integration tests stay in Free.

### 11.2 Matrix

| Layer | Test | Oracle |
|---|---|---|
| Unit | WP compatibility boundaries (`tested` major/minor, `requires`, unknown), PHP boundaries, control toggles. | Pure function; no WP. |
| Unit | Version control: `.git`, Composer pair, neither, unreadable path, symlink escape, cache invalidation, filter override. | Pure detector with a temp fixture tree. |
| Unit | Abandoned: threshold, unknown, API error, cache hit, cap. | Pure with injected data. |
| Unit | `incompatible_behavior` disable vs cancel; core normalization. | Policy result + ledger calls. |
| Unit | Re-enable: newer target, same target, user override, core skip, license off. | Policy + ledger. |
| Unit | Planner: name/min/duplicate/cycle/unknown item/max limits, topological order, stable tie-break, dedupe across chains, core-last rule, no pending offer. | Pure plan. |
| Unit | Queue: job key, status transitions, backoff schedule, retry classification, lease expiry reconciliation, run summary. | Fake repository. |
| Integration | `dbDelta` creates the table, indexes, schema option; upgrade from no table; uninstall policy. | Real DB. |
| Integration | Atomic claim: two workers select the same row; exactly one wins; attempts increment once. | Two DB connections or a simulated conditional update. |
| Integration | Ownership handshake: core's `run()` skips every owned item; a later request's core run also skips a job enqueued in an earlier request; a `failed`/`uncertain`/`blocked` row stays a barrier until retry, recheck, or cancel; the engine's `update()` receives the claimed item; a failed Flow item is not retried by core in the same tick. | Real WP + request-scoped `$owned` fixture + queue row. |
| Integration | Executor mapping: `true`→`succeeded`; `false` before `pre_auto_update`→`blocked`; `false` after it→`failed`; `WP_Error`→retryable/terminal; `null` with target installed→`succeeded`; `null` with unknown version→`uncertain`. | Real WP + injected results. |
| Integration | Queue off: the Shield adapter blocks an incompatible or Composer-managed automatic item; core does not write; one log row. | Real WP + fixture, queue off. |
| Integration | Flow policy: a chain step denied by Shield is never written; downstream steps are `canceled`. | Real WP + fixture. |
| Integration | Free delay: a Flow job inside the soak window is `blocked` with `delay_active`; the chain stops; no write. | Real WP + delay ledger fixture. |
| Integration | Enqueue → claim → execute a fixture plugin update → `succeeded`; installed version changed; log row. | Real WP + local package. |
| Integration | Fixture theme update; fixture plugin with a failing install; temp backup restored; queue `failed`; no downstream job. | Real WP + local package. |
| Integration | Fatal-after-update fixture active plugin: native rollback, `failed` with rollback code, no auto-retry. | Real WP loopback (may need a local HTTP host). |
| Integration | Chain failure in the middle: earlier steps succeeded, failing step `failed`, later steps `canceled`, no later write. | Real WP + fixtures. |
| Integration | Expired lease: `claimed` row past `lease_expires_at`; installed == target → `succeeded`; installed == from → `pending`; unknown → `uncertain`. | DB + fake clock. |
| Integration | Disk full / filesystem failure: inject `WP_Error` via `upgrader_pre_download` or `upgrader_install_package_result`; retryable mapping; no infinite loop. | Simulated, not real disk-full. |
| Integration | `DISALLOW_FILE_MODS`, `AUTOMATIC_UPDATER_DISABLED`, `DISABLE_WP_CRON`, no filesystem credentials. | Constants/filters + Site Health. |
| Integration | License inactive: no hooks, no REST route, worker cancels; license API unreachable with active status: queue continues; status invalid: cancels. | `pre_http_request` + option fixtures. |
| Integration | Multisite: table on main site, no worker on subsite, Super Admin gate, network uninstall guard. | Multisite fixture. |
| Integration | Free log: blocked row, deep link, action-type query, event-key dedupe, no duplicate on retry. | Real Free logger. |
| Integration | REST: routes, permissions, args, 400/403/409/429, idempotent `POST /flow/run`. | Real REST stack. |
| JS | Shield: queue summary, disabled controls when backend absent, no false “run” label, save state. | Vitest. |
| JS | Flow: run dialog, polling, per-step statuses, dirty state, error states. | Vitest. |
| E2E | Activate license, enqueue fixture, process, see log; Flow run; deactivate license. | Playwright when Pro config exists; otherwise manual WP-CLI script. |
| Mutation | Policy and queue pure lines; `bin/harness mutation` MSI ≥ 70; counterfactual on unreachable lines. | Infection. |
| Live | One real plugin update on a staging site; one real chain of two updates; one forced failure; capture log/screens. | Manual, recorded in the task file. |

### 11.3 Fixtures

- A local package directory served through `upgrader_pre_download` or a tiny
  test HTTP server; no external network in integration.
- Fixture plugin/theme versions 1.0.0 → 1.1.0, with a valid zip and a readme.
- A fixture plugin whose 1.1.0 release triggers a fatal on activation/load for
  the native fatal-error path.
- A fixture theme that fails the install (read-only destination or invalid
  archive).
- A fixture with `composer.json` + `vendor/composer/installed.json`, and one
  with only `.git`.
- A fixture with no pending offer.
- A fixture with `requires_php` above the test PHP and one with `tested` below
  the test WP.
- A clock/filter to advance `available_at` and lease expiry without sleeping.

### 11.4 Commands

Use the harness only:

```
bin/harness test               # unit
bin/harness integration        # real WordPress (after 4.2 adds the suite)
bin/harness test:js            # Vitest
bin/harness mutation           # diff-only Infection
bin/harness counterfactual     # lines Infection cannot reach
bin/harness lint
```

No production test is required for this 4.1 document.

---

## 12. Migration plan 4.2 → 4.3 → 4.4

### 12.0 Rules

- No version bump without explicit owner authorization.
- No legacy path, no shim. A one-time data migration is allowed; a dual-read
  compatibility layer is not.
- Each phase starts with a task file and ends with the exit criteria below.
- Phase 5 owns the final screen claims; 4.x may hide a control rather than ship a
  false one.

### 12.1 Phase 4.2 — Kernel, dormant

**Scope**

1. Add `inc/update-engine/` value objects, queue, executor adapter, journal,
   notifier, scheduler, and facade.
2. Add the queue table with `dbDelta`, schema option, upgrade routine, and
   retention sweep.
3. Add Pro integration test bootstrap and the queue/DB/executor fixture suite.
4. Add additive Free logger query/allowlist filters if needed.
5. Add Site Health checks for table, cron, stale leases.
6. Register nothing by default: `updatronix_pro_update_engine_enabled` false, no
   `auto_update_*` filter, no queue cron, no REST routes. The classes are loaded
   for tests and for 4.3.
7. Add the queue table to uninstall handling.

**Exit criteria**

- Table is created on activation/upgrade and removed per the uninstall decision.
- No behavior change with the engine disabled: Free/Pro suites unchanged, no new
  cron event, no new REST route, no new email.
- Integration proof of atomic claim, lease expiry, crash reconciliation,
  retry/backoff, and one fixture update.
- `bin/harness mutation` on the new pure lines passes or survivors are recorded.
- No version bump.

**Explicit decisions**

- **4.2-D1 — Dormant by default.** The engine ships behind
  `updatronix_pro_update_engine_enabled=false`. 4.2 merges with no runtime
  effect.
- **4.2-D2 — Custom queue table.** A table is required for atomic claim, lease,
  order, and idempotence; an option/transient is rejected.
- **4.2-D3 — Native lock.** Reuse `WP_Upgrader::create_lock( 'auto_updater' )`;
  no second lock.
- **4.2-D4 — Native executor.** Wrap `WP_Automatic_Updater::update()`; do not
  call raw upgraders.
- **4.2-D5 — Pro integration suite.** 4.2 is not done without it.
- **4.2-D6 — Uninstall.** Drop the queue table always, from the main site, with
  the network-admin/WP-CLI guard; settings and logs keep the Free opt-in.
- **4.2-D7 — Time.** All queue timestamps are UTC; display uses `wp_date`.

**Implementation decisions (2026-10-26, 4.2)**

These pin points the ADR left to implementation. None changes a 4.3/4.4
decision.

- **Job key scope.** `job_key = sha256(site|type|id|target|shared)`. The origin
  scope is normalized to `shared`, so an automatic run, a Flow chain, and a
  manual retry for the same target reuse one row and the first origin wins.
  This resolves the review's `origin_scope` ambiguity in favor of the stated
  first-origin-wins behavior.
- **Queue insert.** `INSERT ... ON DUPLICATE KEY UPDATE id=id`; a duplicate is
  not an error path and produces no database error output.
- **Explicit retry.** An explicit retry of `failed`/`uncertain` resets
  `attempts` to 0 before the next claim.
- **Skipped fact.** `no_longer_offered` and `already_installed` are `succeeded`
  queue rows with distinct result codes; `skipped` remains a run-summary fact.
- **Lock busy.** Requeued with `available_at = now + 5 minutes`, not failed.
- **Unknown version.** `uncertain` with `partial_state`; manual review only.
- **Journal digest.** The digest is sent once per run and recorded with a
  run-scoped event key in the Free log; the log remains the ledger.
- **Table install.** Activation and the first request after a schema version
  change; the installer fails closed when `$wpdb` is unavailable. The table is
  main-site/network scoped.
- **Dormancy.** Engine classes are lazily loaded; `register_runtime()` is empty
  until 4.3. `queue_enabled` is not read in 4.2 and its Shield default is left
  untouched because 4.3-D8 owns the default migration and worker activation.
- **Uninstall guard.** The network-admin/WP-CLI guard now covers the whole Pro
  uninstall cleanup, not only the queue drop.
- **Site Health.** The queue checks listed in §12.1 are deferred to the 4.3
  surfaces because the 4.2 mission forbids UI/routes; the table and stale-lease
  data are already available.
- **Tests.** Pro now has `tests/Integration`, a PHPUnit integration config, and
  a real plugin/theme fixture update through
  `WP_Automatic_Updater::update()`. The single-site run skips the live
  Multisite guard; the pure capability matrix covers the Super-Admin rule.
  See doc 46 for the full 4.2 report.

### 12.2 Phase 4.3 — Shield policies and queue activation

**Scope**

1. Extract the compatibility checks from `Updatronix_Safe_Mode` into pure
   policies. Keep a thin `auto_update_*` adapter for automatic items that are not
   engine-owned (queue off or no engine job); it delegates to the same policy and
   only denies, never executes. Engine-owned items pass the adapter and are
   stopped by the ownership handshake, so a block is logged once.
2. Add `version_control_lock` (default true), real VCS/Composer detection, and
   the `updatronix_pro_is_version_controlled` filter.
3. Implement `incompatible_behavior` (`disable` vs `cancel`) and the Shield
   ledger.
4. Implement `re_enable` for plugins/themes with the newer-release criterion.
5. Implement abandoned as advisory, cached, capped, and non-blocking.
6. Wire `queue_enabled` to the engine (default false after the migration);
   enqueue automatic offers; suppress the native auto-updater for enqueued items
   only through the ownership handshake.
7. Fix `GET /shield/blocked` to query at the DB level and paginate.
8. Replace per-request email with a deduped digest.
9. Add queue summary to the Shield page; hide or label controls that are not
   real yet.

**Exit criteria**

- Every Shield claim is true or removed; the claims matrix in doc 22/43 is
  updated.
- Tests prove: compatibility block, VCS block, Composer block, `disable` vs
  `cancel`, re-enable, abandoned advisory, queue on/off, license inactive,
  blocked history, notification dedupe.
- A queued item is not executed by the native auto-updater; a non-queued item
  still follows the native path.
- With the queue off, the Shield adapter still blocks an incompatible or
  Composer-managed automatic item; core does not write it.
- No duplicate log rows on retry; blocked deep link opens the row.
- Mutation gate passes on policy lines.
- No version bump.

**Explicit decisions**

- **4.3-D1 — One policy source.** Safe Mode becomes a policy adapter; no
  duplicated compatibility logic.
- **4.3-D2 — Version control.** Native `is_vcs_checkout` is authoritative for
  `.git/.svn/.hg/.bzr` and always denies, automatic and Flow. The Composer
  detector fails open. Never override WordPress's native VCS refusal. Lock off
  removes only the Composer block.
- **4.3-D3 — Abandoned.** Advisory only; WordPress.org `last_updated`; 12 months;
  7-day cache; max 20 lookups per recheck.
- **4.3-D4 — Incompatible behavior.** `disable` writes the native auto-update
  option; `cancel` does not; core always cancels.
- **4.3-D5 — Re-enable.** Only a newer release; only items Shield disabled; never
  core; never a user-disabled item.
- **4.3-D6 — Blocked history.** Free log is the only source; DB-level filter;
  no second table.
- **4.3-D7 — Notifications.** One digest per run; event-key dedupe; no
  client-supplied recipients.
- **4.3-D8 — Queue default.** Change the default to **false** for new installs
  and for sites that never saved the key; preserve an explicitly saved `true`.
  Activate the worker only after the exit criteria pass. The UI must not claim
  queueing before the worker is active. `queue_enabled` only controls automatic
  (non-Flow) jobs; Flow jobs always use the queue and drain one per worker run.

### 12.3 Phase 4.4 — Flow planner and runner removal

**Scope**

1. Introduce `Updatronix_Flow_Planner`; reuse validation/cycle logic; add
   installed-item validation, max limits, topological order, dedupe, core-last.
2. Move the scheduler anchor to `wp_maybe_auto_update` priority 1 (after
   discovery, before `WP_Automatic_Updater::run()`). This amends D-16: the chain
   is planned after the update check and before automatic installation, and its
   steps are serialized one per worker run. A switch to the queue keeps the
   order; it does not run the whole chain in one request.
3. Add `POST /flow/run` and `GET /flow/runs/{run_id}`; add the Run action and
   per-step statuses to the Flow page. Rename the enabled toggle.
4. Migrate `updatronix_pro_flow_chains` to `{version:1, chains:[…]}` once.
5. Make every Flow step pass the Shield policies before execution.
6. Remove `class-updatronix-update-flow-runner.php`, its `require_once`, its
   hook registration, and its transient lock.

**Exit criteria**

- A chain runs in declared order, one step at a time, through the engine; a
  blocked step stops the chain; a failed step stops the chain; no step bypasses
  Shield.
- A chain with no pending updates reports `skipped` and completes.
- A manual run returns a `run_id`; the UI polls and shows per-step results.
- Chain data is v1 only after migration; no dual-read shim.
- The old runner file is deleted; no `updatronix_before_update_check` reference
  remains for Flow.
- A live fixture run on staging is recorded; failure-path tests pass.
- Mutation gate passes.
- No version bump.

**Explicit decisions**

- **4.4-D1 — Planner owns order.** One plan, topological, stable, deduplicated;
  no second ordering path.
- **4.4-D2 — Run is a real action.** `POST /flow/run` + run summary; the UI
  toggle is `Enabled`, not `Run`.
- **4.4-D3 — Shield is mandatory.** Every Flow job calls the same policies; a
  block is `blocked-by-shield` and stops the chain.
- **4.4-D4 — Remove the old runner.** Stop registration, verify, delete the file
  and its hook; no wrapper.
- **4.4-D5 — Chain schema.** `{version:1, chains:[…]}`; one-time migration; no
  legacy read.
- **4.4-D6 — Scheduler anchor.** `wp_maybe_auto_update` priority 1 after
  discovery, before `WP_Automatic_Updater::run()`.
- **4.4-D7 — Core in a chain.** Core is allowed only as the last step of a chain.
- **4.4-D8 — Run limits.** One active run per site; one manual run per chain per
  5 minutes; 409/429 for conflicts.

### 12.4 Removal order for the old runner

1. 4.4 adds the planner and the engine path; the old runner stays registered but
   is not called by the new scheduler.
2. Run the new path against fixtures and staging; compare results and logs.
3. Switch `Updatronix_Update_Flow_Runner::register()` off in the bootstrap.
4. Remove the `updatronix_before_update_check` hook and the transient lock.
5. Delete `inc/update-flow/class-updatronix-update-flow-runner.php`.
6. Remove the `require_once` in `class-updatronix-pro-bootstrap.php`.
7. Update the tests: replace runner tests with planner/kernel tests; do not keep
   a wrapper.
8. Update docs 23, 34, 45 and the task index.

Do not keep the old runner “for one release”. A dormant second runner is a
second execution path and a false safety guarantee.

### 12.5 Phase 5 handoff (not in scope)

- 5.1 wires the Shield screen claims and queue summary.
- 5.2 wires the Flow Run action and per-step UI.
- 5.3/5.4/5.5 remain independent roadmap work.
- The license screen feature list must be updated only when the corresponding
  backend ships.

---

## 13. Risks, assumptions, and open questions

### 13.1 Blocking risks — resolved

No unresolved blocking decision remains. The owner delegated the choices on
2026-10-26; these are the resolutions and the delivery gates that still apply.

| ID | Former blocker | Resolution | Residual delivery gate |
|---|---|---|---|
| BR-01 | Executor choice was unresolved. | Native `WP_Automatic_Updater::update()` adapter; raw upgraders forbidden. | Integration test + staged live run; minimum WP 6.2. |
| BR-02 | Scheduler anchor was unresolved. | `wp_maybe_auto_update` priority 1, after discovery, before core's run; D-16 amended. | Integration test with fresh transients. |
| BR-03 | Pro had no integration suite or live fixture. | The suite is a 4.2 deliverable; no 4.3 before it is green. | 4.2 exit criteria. |
| BR-04 | Multisite scope was not live-tested. | Main-site-only worker; Super-Admin-only REST; network table via `updatronix_with_main_site()`. | Multisite integration test; live pass when available. |
| BR-05 | The queue table has crash/lease semantics. | Atomic claim, lease sweeper, `uncertain`, bounded retries, mutation. | 4.2 integration suite. |
| BR-06 | The UI already claims queue/VCS/abandoned/re-enable. | 4.3 exit criterion: every claim true or hidden; 5.1 owns the final screen copy. | Claims matrix updated row by row. |

### 13.2 High risks

| ID | Risk | Mitigation |
|---|---|---|
| HR-01 | No general rollback; a partial update can break the site. | Native temp backup/fatal rollback, `uncertain` state, no auto-retry, visible failure, staging test. |
| HR-02 | Database migrations are not rolled back. | Document; never promise rollback; recommend backups; `uncertain` stops the chain. |
| HR-03 | Disk full / permissions / `DISALLOW_FILE_MODS` can fail mid-chain. | Preflight policy, retryable classification, bounded attempts, Site Health. |
| HR-04 | License API outage / invalid status. | Local status only on execution; active continues, invalid cancels; no network in worker. |
| HR-05 | WP-Cron is unreliable or disabled. | Site Health warning; external cron docs; single follow-up events; no infinite loop. |
| HR-06 | Flow chains can encode a wrong order. | User-declared, validation, cycle check, preview, per-step results, “no automatic discovery” copy. |
| HR-07 | Notification spam. | Digest, event-key dedupe, Free recipient rules. |
| HR-08 | Blocked history query can miss rows. | DB-level filter, pagination, index on `(created_at, action_type)`. |
| HR-09 | Concurrent core auto-updater and worker. | Shared `auto_updater` lock; lock-busy requeue; no raw upgrader. |
| HR-10 | A fatal error leaves the lease. | Lease expiry, sweeper, reconciliation, `uncertain`. |
| HR-11 | Composer detection false positives/negatives. | Item-root only, realpath containment, cache, fail open, filter. |
| HR-12 | Turning VCS lock off could be misread as allowing automatic updates; it only removes the Composer block. | Explicit UI copy; native VCS is always denied; tests. |
| HR-13 | Abandoned API cost. | Advisory, cached, capped, only pending/auto-enabled items. |
| HR-14 | Engine replacement changes update timing. | Dormant 4.2, queue off/on tests, staging live run before 5.1. |

### 13.3 Assumptions

1. WordPress 6.2+ is the floor; `WP_Automatic_Updater::update()` and
   `WP_Upgrader::create_lock()` are available.
2. Free remains the owner of cron, discovery, the log table, and recipient
   settings.
3. Pro remains network-scoped and main-site-only for automatic updates.
4. The license status is stored locally and does not require a network call on
   the execution path.
5. The Free log table remains the single history; Pro may add typed rows but not
   a second table.
6. The owner accepts that a custom queue table is necessary.
7. No Phase 5 feature is implemented in 4.2–4.4.
8. No version bump until the owner authorizes one.

### 13.4 Resolved decisions (2026-10-26)

The owner delegated the remaining choices. These are final for 4.2–4.4.

| # | Question | Decision |
|---|---|---|
| 1 | Executor | Native `WP_Automatic_Updater::update()` adapter; raw upgraders forbidden. |
| 2 | Scheduler anchor | `wp_maybe_auto_update` priority 1, after discovery, before core's run; chain steps are serialized one per worker run. D-16 is amended accordingly. |
| 3 | Queue default | `false` for new installs and for sites that never saved the key; preserve an explicitly saved `true`. Activate the worker only after the 4.3 exit criteria. |
| 4 | Queue disabled | Cancel pending `origin=automatic` jobs; Flow jobs continue because they are explicit. |
| 5 | Uninstall | Always drop the queue table, from the main site, with the network-admin/WP-CLI guard. Settings/logs keep the Free opt-in. |
| 6 | VCS lock off | Native VCS checkouts are always denied, automatic and Flow. Lock off only removes the Composer block; it never overrides WordPress's VCS refusal. |
| 7 | Re-enable | Only a newer target version. Same-target re-enable stays manual. |
| 8 | Abandoned | WordPress.org `last_updated`, 12 months, advisory only, 7-day cache, max 20 lookups per recheck. |
| 9 | Core in Flow | Last step of a chain only. |
| 10 | Multisite | Main-site-only queue; Super-Admin-only REST; network table via `updatronix_with_main_site()`. |
| 11 | Flow run | 4.4 adds `POST /flow/run` and `GET /flow/runs/{run_id}` plus the UI. 4.2/4.3 do not claim a run action. |
| 12 | Notifications | One deduped digest per run; no client-supplied recipients. |
| 13 | Batch/lease | Batch 1 by default; lease 15 minutes; max 3 attempts; 5/15/60-minute backoff with jitter. |
| 14 | License inactive | Cancel pending `origin=automatic` jobs with `license_inactive`, unschedule the worker, keep data. No execution while inactive. |

**Product revisit point:** the queue default may be flipped to `true` only after
the 4.3 live fixture passes and the owner makes a new explicit decision. No other
decision in this table is open.

---

## 14. Decision register

| ID | Decision | Phase | Status |
|---|---|---|---|
| 4.2-D1 | Engine ships dormant behind `updatronix_pro_update_engine_enabled=false`. | 4.2 | Resolved |
| 4.2-D2 | Queue uses a custom main-site table, not an option/transient. | 4.2 | Resolved |
| 4.2-D3 | Global lock is the native `auto_updater` lock. | 4.2 | Resolved |
| 4.2-D4 | Executor is a `WP_Automatic_Updater::update()` adapter; raw upgraders are forbidden. | 4.2 | Resolved |
| 4.2-D5 | Pro integration suite is a 4.2 deliverable. | 4.2 | Resolved |
| 4.2-D6 | Uninstall always drops the queue table, from the main site, with the network guard. | 4.2 | Resolved |
| 4.2-D7 | Queue timestamps are UTC. | 4.2 | Resolved |
| 4.3-D1 | Safe Mode becomes a policy adapter; one policy source. | 4.3 | Resolved |
| 4.3-D2 | VCS uses native detector + Composer detector; native VCS is always denied; lock off only removes the Composer block. | 4.3 | Resolved |
| 4.3-D3 | Abandoned is advisory, WordPress.org `last_updated`, 12 months, capped cache. | 4.3 | Resolved |
| 4.3-D4 | `disable` writes native auto-update; `cancel` does not; core cancels. | 4.3 | Resolved |
| 4.3-D5 | Re-enable only a newer release, only Shield-disabled items. | 4.3 | Resolved |
| 4.3-D6 | Blocked history reads the Free log at DB level; no second table. | 4.3 | Resolved |
| 4.3-D7 | Notifications are one deduped digest per run. | 4.3 | Resolved |
| 4.3-D8 | Queue default is false for new/unsaved installs; activate after exit criteria; UI cannot claim it earlier. | 4.3 | Resolved |
| 4.4-D1 | Flow planner owns ordering; topological, stable, deduplicated. | 4.4 | Resolved |
| 4.4-D2 | `POST /flow/run` + `GET /flow/runs/{run_id}` + UI run action. | 4.4 | Resolved |
| 4.4-D3 | Every Flow job passes Shield policies; no bypass. | 4.4 | Resolved |
| 4.4-D4 | Old runner is stopped, verified, then deleted; no wrapper. | 4.4 | Resolved |
| 4.4-D5 | Chain schema becomes `{version:1, chains:[…]}` with a one-time migration. | 4.4 | Resolved |
| 4.4-D6 | Scheduler anchor is `wp_maybe_auto_update` priority 1 after discovery; chain steps are serialized one per worker run. | 4.4 | Resolved |
| 4.4-D7 | Core is allowed only as the last step of a chain. | 4.4 | Resolved |
| 4.4-D8 | One active run; manual run rate limit; 409/429. | 4.4 | Resolved |

---

## 15. Appendices

### 15.1 Claims matrix for the current screens

| Claim | Where | Code path | Verdict | Action |
|---|---|---|---|---|
| “Blocks updates for plugins and themes tracked in Git or Composer.” | Shield lock card | No detector; Flow bypasses native VCS | **False** | Implement in 4.3; native VCS is never overridden |
| “Queue automatic updates.” | Shield queue card | `queue_enabled` stored; no consumer | **False** | Implement in 4.3; default off until the live test |
| “Flag abandoned plugins.” | Shield switch | `flag_abandoned` stored; no consumer | **False** | Implement advisory in 4.3 |
| “Re-enable auto-updates automatically.” | Shield switch | `re_enable` stored; no consumer | **False** | Implement in 4.3 |
| “Cancel this update only.” | Shield radio | `incompatible_behavior` stored; no consumer | **False** | Implement in 4.3 |
| “Run %s.” | Flow card | Toggles `enabled`; no run route | **False** | Rename in 4.4; add run in 4.4/5.2 |
| “Chains run before the regular update schedule.” | Flow copy | Runner on pre-check hook | **True but stale data** | Move anchor or refresh in 4.4 |
| “Stops on the first failure.” | Flow copy | Runner returns on first error | **True** | Keep, add per-step state |
| “Update Shield stops incompatible updates.” | License feature list | Auto filter only; Flow bypasses | **Partial** | 4.4 closes the bypass |
| “Blocked history last 30 days.” | Shield table | 200-row post-filter | **Partial** | 4.3 DB-level query |

### 15.2 File inventory

| File | Read for |
|---|---|
| `inc/update-shield/class-updatronix-safe-mode.php` | Filters, compatibility, block records, disable, email |
| `inc/update-shield/class-updatronix-update-shield.php` | REST, permissions, blocked history, recheck |
| `inc/update-flow/class-updatronix-update-flow.php` | Storage, validation, cycle, routes |
| `inc/update-flow/class-updatronix-update-flow-runner.php` | Hook, lock, raw execution, logging |
| `inc/classes/class-updatronix-pro-bootstrap.php` | Gating and load order |
| `inc/settings/options.php` | Shield defaults and save |
| `inc/classes/class-updatronix-pro-uninstall.php` | Uninstall today |
| `inc/admin/class-updatronix-pro-enqueue.php` | Asset loading |
| `assets/src/js/components/UpdateShieldPage.js` | Claims and states |
| `assets/src/js/components/UpdateFlowPage.js` | Claims, save, toggle |
| `tests/Unit/SafeModeBulkShieldTest.php` | Existing policy coverage |
| `tests/Unit/SafeModeSyntheticItemTest.php` | Synthetic-item coverage |
| `tests/Unit/UpdateShieldRestTest.php` | REST/permission coverage |
| `tests/Unit/UpdateFlowValidationTest.php` | Validation coverage |
| `inc/classes/class-updatronix-cron.php` | Discovery and hook order |
| `inc/classes/class-updatronix-database.php` | Log table schema |
| `inc/classes/class-updatronix-logger.php` | Journal contract |
| `inc/classes/class-updatronix-update-logger.php` | Native update journal |
| `inc/classes/class-updatronix-notifications.php` | Recipient routing |
| `inc/classes/class-updatronix-uninstall.php` | Data-deletion opt-in |
| `wp-admin/includes/class-wp-upgrader.php` | Lock, temp backup, restore |
| `wp-admin/includes/class-wp-automatic-updater.php` | Native run/update/rollback |
| `wp-includes/update.php` | Cron trigger and transients |

### 15.3 Definitions

- **Job:** one item at one target version, with one durable queue row.
- **Plan:** an ordered set of jobs and facts produced by a planner.
- **Policy:** a pure decision on a job, with a code, message, and severity.
- **Claim:** the atomic transition from `pending` to `claimed` with a lease.
- **Lease:** a time-bounded ownership token; expiry allows recovery.
- **Uncertain:** a partial state that cannot be classified as success or safe
  retry; requires human review.
- **Run:** a group of jobs created by one planner invocation, identified by
  `run_id`.
- **Journal:** the Free log rows that record what happened.
- **Digest:** one notification for a run, not one per item.

---

**End of ADR.** Phase 4.2 may start: no owner decision remains. The first gate is
the Pro integration suite and the dormant kernel; 4.3 and 4.4 follow only after
their exit criteria pass.
