# Update engine — Phase 4.3a Shield policies and queue wiring

**Date:** 2026-10-26
**Scope:** Pro `inc/update-engine/`, `inc/update-shield/`, Pro settings/UI,
queue REST routes, and the 4.3a tests. Free receives documentation only.
**Status:** Policies implemented and wired. The queue is **dormant**: the
engine kill switch is still false by default, `queue_enabled` is false, and the
queue switch is disabled in the Shield UI until a live end-to-end fixture
passes. No Flow change.
**Related:** ADR `45-update-engine-architecture.md` §5.4, §5.6, §5.7, §5.8,
§5.9, §6–§8, §10–§12.2, §14; kernel report `46-update-engine-4.2-kernel.md`;
`FEAT-P-SHIELD`, `FEAT-P-QUEUE`; implementation log `11`.

---

## 1. What landed

| Area | Change |
|---|---|
| Policy contract | `Updatronix_Update_Policy` stays the pure contract. `Updatronix_Policy_Chain` builds the ordered engine and adapter chains. |
| Compatibility | `Updatronix_Compatibility` is the single WordPress/PHP rule source; Safe Mode delegates to it instead of keeping a second copy. |
| Policies | License, engine/native opt-in, native guards, WordPress compatibility, PHP compatibility, Free delay, version control/Composer, abandoned advisory, pending offer, chain dependency. |
| Ownership | `Updatronix_Shield_Ownership` registers the `auto_update_*` handshake at `PHP_INT_MAX`; the queue table is the source of truth; the executor's scoped flag passes its own claimed item; active and unresolved terminal rows remain barriers. |
| `incompatible_behavior` | `disable` writes the native `auto_update_plugins`/`auto_update_themes` option and records the Shield ledger; `cancel` leaves it untouched; core is always `cancel`. |
| `re_enable` | Only an item Shield disabled, only a newer target, only when the offer passes every blocking policy; ledger entry removed; `auto_update_reenabled` logged. |
| Abandoned | Fixed WordPress.org `last_updated`, 12-month threshold, 7-day bounded cache, max 20 lookups per recheck, advisory only. Policies read the cache; they never call the network. |
| VCS/Composer | Native `WP_Automatic_Updater::is_vcs_checkout()` is authoritative and always denies. The Composer detector checks `composer.json` + `vendor/composer/installed.json`, uses realpath containment, rejects symlinks, caches, fails open, and exposes `updatronix_pro_is_version_controlled`. |
| Scheduler | `wp_maybe_auto_update` priority 1 plans automatic offers into the queue; recurring hourly worker plus single follow-ups; one job per run; expired-lease sweeps; retention; queue-off cancels pending automatic jobs while Flow/manual jobs still drain. |
| Queue REST | `GET /queue`, `GET /queue/summary`, `POST /queue/{id}/retry`, `DELETE /queue/{id}`, `POST /queue/run`, all behind the Shield gate (capability + Super Admin + license + engine availability) and a short per-user rate limit. |
| Blocked history | `GET /shield/blocked` now queries the Free log at the database level with blocked action types, a 30-day bound, and SQL pagination; the UI can load more pages. |
| Notifier | Engine runs keep the one deduplicated digest per run. Adapter blocks send one digest per request and only for new event-key rows; no per-item email. |
| UI truthfulness | Queue switch disabled with an honest dormant message; Composer lock is its own switch; core `disable` is documented as `cancel`; abandoned advisories are visible; page pagination; license-inactive copy no longer promises the queue. |
| Settings | `queue_enabled` default false; one-shot migration of a stored `true` to `false` until the queue is live; `version_control_lock` default true; the save path forces `queue_enabled=false` while `updatronix_pro_queue_live` is false. |

---

## 2. Policy chain and order

The first denial wins. Each evaluated result is kept in the job's policy
snapshot. The engine chain is:

| Order | Policy id | Denial code | Notes |
|---|---|---|---|
| 1 | `license` | `license_inactive` | Local stored status; no network call. |
| 2 | `engine_enabled` | `engine_disabled`, `queue_disabled`, `auto_update_disabled` | Automatic jobs need the queue and the native opt-in; Flow/manual jobs are explicit. |
| 3 | `native_guards` | `environment_disabled`, `filesystem_unavailable` | `AUTOMATIC_UPDATER_DISABLED`, `DISALLOW_FILE_MODS`, `wp_installing()`, filesystem method, `WP_AUTO_UPDATE_CORE=false`. |
| 4 | `wp_compatibility` | `wp_incompatible` | Plugin `tested` major.minor; theme/core `requires`; unknown allows. |
| 5 | `php_compatibility` | `php_incompatible` | `requires_php`; unknown allows. |
| 6 | `free_delay` | `delay_active` | Delegates to Free's canonical delay filter and ledger; the engine never bypasses the soak window. |
| 7 | `version_control` | `native_vcs`, `version_controlled` | Native VCS never overridden; the lock switch only controls the Composer block. |
| 8 | `abandoned` | never denies | Advisory metadata only; cache-only on the execution path. |
| 9 | `pending_offer` | `stale_offer` | Same target must still be in the native transient. |
| 10 | `chain_dependency` | `dependency_failed` | Earlier rows in the same run/chain must have succeeded. |

The adapter chain for automatic items that are **not** engine-owned contains
native guards, WordPress/PHP compatibility, version control, and the abandoned
advisory. It excludes license/engine/queue, delay, pending offer, and chain
dependency: Free already ran the delay gate earlier in the native filter chain,
and the adapter only denies; it never executes.

### `disable` vs `cancel`

- `disable` applies to plugins and themes only. If the native toggle is on,
  the policy adapter calls `Updatronix_AutoUpdates`, verifies the option, and
  records the item and blocked target in the Shield ledger.
- `cancel` denies the job and leaves the native toggle exactly as it was.
- Core has no auto-update option. Both values behave as `cancel`; the UI says
  so.
- The recheck endpoint applies the same setting. It no longer disables toggles
  while `cancel` is selected.

### Re-enable

`Updatronix_Shield_Enforcement::maybe_reenable()` runs on recheck and on the
automatic-update anchor. It re-enables only when all of these hold:

1. Safe Mode and `re_enable` are on and `incompatible_behavior` is `disable`.
2. The item is in the Shield ledger (Shield disabled it).
3. The current pending offer is a **strictly newer** target than the blocked
   one.
4. The offer passes native guards, WordPress/PHP compatibility, version
   control, and the abandoned advisory.
5. The item is not already re-enabled by the user.

A user re-enabled item only loses its ledger entry. Core is never touched. On
success the ledger entry is removed and the Free log receives
`auto_update_reenabled`.

### VCS and Composer

- Native `.git/.svn/.hg/.bzr` evidence is read through WordPress's own
  `is_vcs_checkout()`. If WordPress is unavailable, the detector fails open;
  it never guesses from an ad-hoc filesystem walk that can false-positive.
- The Composer detector only scans the item root, requires both
  `composer.json` and `vendor/composer/installed.json`, uses `realpath`
  containment, rejects symlinks, and caches the result. It fails open with a
  deduplicated log row.
- `version_control_lock` (default true) controls only the Composer block.
  Turning it off never overrides WordPress's native VCS refusal.
- Cache invalidation is registered on `upgrader_process_complete`,
  `activated_plugin`, `switch_theme`, `deleted_plugin`, and `deleted_theme`.

### Version Lock precedence (2026-10-28)

The chain is native guards, version control, WP compatibility, PHP
compatibility, delay. The native adapter at priority 19 evaluates the same
order. `version_locked_native` and `version_locked_composer` are the public
codes; `native_vcs` and `version_controlled` are legacy aliases.
`version_lock_unknown` blocks; `version_lock_unverified` allows with one
advisory row.

### Abandoned advisory

- Source is WordPress.org `last_updated` through the fixed `plugins_api` /
  `themes_api`, never a client URL.
- Only installed items with a pending offer and native auto-update enabled are
  candidates; at most 20 lookups per recheck.
- The cache is a site transient, seven days, bounded to 200 entries. Unknown
  data and API errors are cached as unknown and are not flagged.
- The threshold is `updatronix_pro_abandoned_threshold_days`, default 365.
- The policy is network-free: enqueue/execution read the cache. The recheck
  endpoint fills it and writes one informational `update` row per new
  item/release. The Shield page displays the cached advisories under the
  switch when `flag_abandoned` is on.

---

## 3. Ownership handshake

`Updatronix_Shield_Ownership::register_handshake()` adds
`auto_update_plugin|theme|core|translation` at `PHP_INT_MAX`, after Safe Mode's
adapter at `PHP_INT_MAX - 5`.

- The scheduler remembers every planned item/target.
- `is_owned()` also reads the queue table through the `item_status` index using
  `pending`, `claimed`, `running`, `failed`, `uncertain`, and `blocked` as
  barriers. The query is cached per request.
- If the executor is running that exact item and target, the handshake passes
  the item through so `WP_Automatic_Updater::should_update()` can run.
- Otherwise an owned item is denied to WordPress's `run()`; the engine remains
  the only writer.
- `succeeded` and `canceled` rows are not barriers. A later request cannot
  steal a queued item; a failed or Shield-blocked row stays a barrier until
  retry, recheck, or cancel.
- Safe Mode passes engine-owned items without applying the adapter a second
  time, so one block produces one row.

The only execution lock is still `WP_Upgrader::create_lock( 'auto_updater' )`
inside the 4.2 executor. No second transient lock was added.

**Version Lock (2026-10-28):** the ordered adapter marks each item it
evaluates; Safe Mode's `PHP_INT_MAX - 5` fallback skips those items, so one
block produces one row. The optional manual guard covers plugin, theme, and
core and exempts the executor and the pass flag.

---

## 4. Scheduler and worker

- `Updatronix_Queue_Scheduler::tick()` runs at `wp_maybe_auto_update`
  priority 1. It plans only items that are in the native auto-update option,
  plus core offers that respect `WP_AUTO_UPDATE_CORE` (including the `minor`
  case).
- `plan()` is idempotent through the 4.2 `job_key`; blocked rows are persisted
  but do not count as planned work.
- `process()` sweeps expired leases, applies retention, refuses a second
  claimed/running run, and executes one job. The recurring event is hourly; a
  single follow-up runs at the earliest pending `available_at` (not a 1-second
  busy loop).
- `queue_enabled` controls automatic jobs only. When it is false, pending
  automatic rows are canceled with `queue_disabled`; Flow/manual rows still
  drain one per worker run. The engine policy also denies automatic jobs when
  the item is no longer in the native opt-in.
- License inactive or engine unavailable: the worker unschedules and returns.
  The Pro bootstrap clears both worker hooks when the license is inactive or
  the kill switch is off.
- The queue is dormant in 4.3a because the kill switch is false by default and
  `queue_enabled` is false. No live queue run is claimed.

---

## 5. Queue REST

Routes are registered only when the engine is available (kill switch, active
license, main site). Every route repeats `Updatronix_Update_Engine_Bootstrap::can_manage()`:
Pro capability, Super Admin on Multisite, and an active license.

| Route | Behavior |
|---|---|
| `GET /queue` | Paginated rows; status/origin/run filters; order whitelist. |
| `GET /queue/summary` | Counts, next run, stale leases, active run id, last error, `queue_enabled`, `queue_live`. |
| `POST /queue/{id}/retry` | Only `failed`/`uncertain`; automatic rows require the queue to be on. |
| `DELETE /queue/{id}` | Only pending/failed/uncertain/blocked; a running row is rejected. |
| `POST /queue/run` | One bounded job; 409 for queue off or an active claimed/running run; 429 for the per-user cooldown. |

No route accepts a package URL, item id, filesystem path, callable, or
recipient. `target_version` comes from the native transient.

---

## 6. Blocked history and notifications

- `GET /shield/blocked` sends `action_types`, `created_at_after`,
  `per_page`, and `page` to the Free logger. The route loads no 200-row window
  and filters nothing in PHP. It returns `items`, `days`, `page`, `per_page`,
  `total`, and `total_pages`.
- The UI requests 50 rows and offers "Load more" while pages remain.
- Engine blocks are journaled once and notified by the existing one-digest-per-
  run notifier. Adapter blocks use one event key per item/target/reason/context
  and only new rows enter the request digest. Neither path sends one email per
  item.
- Recipients remain server-side Free settings plus Pro's `notify_email`; no
  client address is accepted.

---

## 7. UI changes

- Queue card: switch disabled while `queue_live` is false; copy says queued
  automatic updates are not enabled yet and WordPress continues on its native
  path. The switch can only be turned on after the live fixture and an owner
  decision.
- Version control card: native Git/SVN refusal is described separately from
  the Composer lock. The Composer lock is a real switch (`version_control_lock`).
- Incompatible behavior: copy states that core updates are always canceled for
  that release only.
- Re-enable: copy says a **newer** release is required.
- Notifications: copy says one digest, not one email per item.
- Abandoned: cached advisories appear only when the flag is on.
- Blocked table: real pagination.
- License inactive: the copy no longer promises the update queue.

No screen redesign was made.

**Version Lock (2026-10-28):** the native plugin row, single-site theme
card, network theme row, update-core tables, Auto updates row, and Shield card
show the manager label and one notice. The native toggle is a lock label; the
stored option is unchanged. "Block manual updates" is an opt-in switch; when
Update Shield is off, both Version Lock switches are disabled and the card says
"Requires Update Shield".

---

## 8. Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 310 tests, 1097 assertions, pass. |
| Pro `bin/harness integration` | 79 tests, 733 assertions, pass; 1 intentional Multisite-only skip. |
| Pro `bin/harness test:js` | 41 tests, pass. |
| Pro `bin/harness lint:js` | Pass, 0 warnings. |
| Pro `bin/harness lint:files` on modified PHP | Pass. |
| Pro `bin/harness lint` (full) | Pass; PHPStan no errors. |
| Pro `bin/harness build` | Pass. |
| Pro `bin/harness counterfactual` | Recorded coverage finding: the gate checked 12 of 61 executed changed lines (4 killed, 8 reported surviving). Manual substitutions kill six of the eight; line 1272 is an equivalent int/string concatenation; the defaults test kills the other two. The remaining 49 were not asked because the full-cap run exceeded the time budget. Not claimed as a pass. |
| Pro `bin/harness mutation` | Not run in 4.3a; the counterfactual gate was chosen for this diff. |
| Free suites | No Free code changed. The Free baseline remains 216 unit / 77 integration. |
| E2E | Not run; still blocked by `libnspr4.so`. Not claimed. |

The new Pro integration coverage includes:

- an incompatible plugin job blocked by the real chain with `disable` writing
  the native option and the ledger, and `cancel` leaving it untouched;
- a blocked theme fixture;
- a recheck that requeues a blocked row when the same target now passes;
- scheduler idempotence and ownership;
- one worker run, queue-off manual draining, and expired-lease recovery;
- the handshake across requests, including failed and canceled rows;
- queue route registration and the capability/license gate;
- queue list/retry/cancel/run callbacks;
- DB-level blocked-history filtering and pagination;
- the one-shot `queue_enabled` migration and the forced-false save.

Unit coverage includes every policy, chain order/short-circuit, `disable` vs
`cancel`, re-enable, Composer detection, abandoned cache/cap, ownership, and
the queue default migration.

---

## 9. Dormancy and live gate

The queue can remain dormant in 4.3a. It is dormant for three independent
reasons:

1. `updatronix_pro_update_engine_enabled` is still false by default.
2. `queue_enabled` defaults to false and is forced false on save while
   `updatronix_pro_queue_live` is false.
3. The Shield switch is disabled with an honest message.

To make the queue live, the owner must first pass a recorded end-to-end fixture
on staging (enqueue → cron worker → native update → log → digest), then flip
the kill switch policy, the `queue_live` filter/default, and the
`queue_enabled` decision. No code in this phase does that.

---

## 10. Limits and out of scope

- No live queue run, no live Multisite pass, no real disk-full test. The
  single-site integration run skips the Multisite live guard; the Super-Admin
  rule is covered by the pure capability matrix.
- Site Health queue checks are not added; they remain a 4.3b/5.1 surface.
- The Shield page does not show a queue summary yet; that belongs to 4.3b/5.1.
- The queue switch is disabled, not removed.
- Flow still uses the old runner. 4.4 owns the planner, `POST /flow/run`, and
  runner removal. No Flow file was touched.
- No version bump, no commit, no legacy path.
- E2E remains blocked by the host library.

---

## 11. Implementation decisions pinned by 4.3a

- **4.3a-D1 — Policy source.** Safe Mode delegates compatibility to
  `Updatronix_Compatibility`; there is no second rule implementation.
- **4.3a-D2 — Engine chain vs adapter chain.** The engine chain is the full
  ten-policy list. The adapter chain is the blocking subset; delay and
  ownership are handled by the native filter order and the handshake.
- **4.3a-D3 — Native opt-in check.** Automatic plugin/theme jobs must still be
  in the native auto-update option. This is part of `engine_enabled`, not a new
  policy or a marketing feature.
- **4.3a-D4 — Abandoned is cache-only on the execution path.** The recheck is
  the only network entry point; this keeps policies pure and enforces the
  20-lookup cap.
- **4.3a-D5 — Recheck requeues the same target only.** A newer target is left
  to the scheduler; `failed`/`uncertain` still need an explicit retry.
- **4.3a-D6 — One run guard is claimed/running.** Pending rows are queued work,
  not an active run. This avoids a self-deadlock in the worker.
- **4.3a-D7 — Queue off still drains Flow/manual.** `queue_enabled` only gates
  automatic work, per 4.3-D8. The worker unschedules when nothing is left.
- **4.3a-D8 — Queue default migration.** A stored `true` is migrated to
  `false`, the save path forces false, and the switch is disabled. This
  supersedes the earlier “preserve an explicitly saved true” wording for the
  dormant period. The queue can be advertised only after `queue_live` is true
  and the live fixture passes.
