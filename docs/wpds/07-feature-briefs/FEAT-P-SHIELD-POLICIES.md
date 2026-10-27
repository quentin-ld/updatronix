# FEAT-P-SHIELD-POLICIES · R-04 and R-07 visible state

| | |
|---|---|
| Edition | Pro |
| Status | Brief for Phase 5.1. The policies exist in 4.3a; this brief defines what the Shield screen may claim and what state the backend must expose. |
| Rail | Automations · Update Shield |
| Depends on | `FEAT-P-SHIELD`, `FEAT-P-QUEUE`, ADR `45` §5.4, 4.3a report `47`, 4.4 report `50` |
| Scope | Version control (native VCS + Composer), abandoned advisory, re-enable, `incompatible_behavior`, and the read-only queue summary. |
| Out of scope | Queue activation, R-02 activation controls, R-03/R-05/R-08, Phase 6 work, a second Shield screen or tab. |

## 1. Why this brief exists

The 4.3a backends are real, but the Shield screen still makes claims that are
not fully backed:

- the version-control badge is driven by `safe_mode`, not by the detector;
- the screen does not show what was detected, what was unknown, or which
  items are waiting for re-enable;
- the abandoned switch hard-codes “12 months”, while the threshold is a
  filterable value;
- the `disable` radio is described as “Cancel it and turn off auto-updates”,
  which reads like `cancel`;
- re-enable says it “turns them back on once a newer release passes”, without
  saying that only Shield-disabled items and only a strictly newer target
  qualify, or why an item is still waiting.

Phase 5.1 makes each visible claim true, or hides the claim. It does **not**
activate the queue and does not add a second screen.

## 2. Claim inventory (Task 0)

Classification: **true/backed**, **true only when licensed**, **false/unsupported**
(remove, hide, or disable), or **future** (hide until real).

| Visible claim / control | Backend that makes it true | Classification | 5.1 action |
|---|---|---|---|
| “Protection” / “Runs before every automatic update.” | Safe Mode filters on `auto_update_*`; every engine job passes the policy chain. | True/backed | Narrow the copy: native automatic updates and every Flow step. |
| “Protect this site” switch (`safe_mode`) | `Updatronix_Safe_Mode::register()` and the adapter chain. The engine chain also runs while the switch is off, so the copy must not imply that the switch disables every Shield policy. | True/backed, but the current copy overclaims | Describe it as the native automatic-update adapter; state that Flow evaluates the same policies on every step. |
| “Version control lock” / badge `Locked` / `Off` | Badge currently reads `safe_mode`; the real state is native VCS + Composer detection + `version_control_lock`. | **False** | Drive the badge and the panel from the detector; show detected and unknown items. |
| “WordPress blocks updates in Git/SVN checkouts. The Composer lock adds Composer-managed items.” | Native `WP_Automatic_Updater::is_vcs_checkout()`; Composer detector; policy chain. | True/backed | Keep; add the real counts and unknown state. |
| “Turn on Protect this site to block updates for version-controlled items.” | Composer lock is consumed by the engine even when the native adapter is off. | **False** | Replace with the actual scope and detected state. |
| “Composer lock” switch (`version_control_lock`) | `Updatronix_Version_Control_Policy` and `updatronix_pro_is_version_controlled`. | True/backed | Keep it usable; show detected Composer items. |
| “When an update is incompatible” radio | `Updatronix_Shield_Enforcement` and the compatibility policies. | True/backed | Fix the `disable` label so it is not described as `cancel`; state the core rule. |
| “Cancel it and turn off auto-updates for that item” (`disable`) | Denies the job and writes the native `auto_update_plugins`/`auto_update_themes` option + ledger. | **False as worded** (“cancel” conflates the two) | Rename to “Block it and turn off auto-updates for that item”. |
| “Cancel this update only” (`cancel`) | Denies the job and leaves the native toggle untouched. | True/backed | Keep. |
| “Core updates are always canceled for that release only.” | Core has no auto-update option; both behaviors deny that release. | True/backed | Keep and test. |
| “Re-enable auto-updates automatically” (`re_enable`) | Ledger + strictly newer target + adapter policy pass + native toggle. | True/backed | Show eligibility and last attempt per item; state the same-target manual rule. |
| “Turns them back on once a newer release passes the compatibility check.” | Only newer targets; only Shield-disabled items; only when `safe_mode`, `re_enable`, and `disable` are on. | Partial | Add the conditions and a truthful per-item state. |
| “Email the technical manager” (`notify_email`) | `Updatronix_Update_Notifier` digest and Safe Mode notification. | True/backed | Keep; show the recipient source and one-digest rule. |
| “Flag abandoned plugins” (`flag_abandoned`) | Cached WordPress.org `last_updated`, advisory policy, recheck scan. | True/backed | Show threshold, cached state, and unknown; never describe unknown as safe. |
| “Warns when an item has had no release for 12 months.” | Threshold is `updatronix_pro_abandoned_threshold_days`, default 365. | True only for the default | Read the threshold from the backend; label a filter override. |
| Abandoned list “No release in the last 12 months:” | The list is filtered with the current threshold. | False when filtered | Use the threshold from the payload. |
| “Queue automatic updates” switch | `queue_enabled` + scheduler + `queue_live`. | **Disabled** | Keep disabled; copy says dormant. |
| Queue counters, last run, next due, stale leases | `Updatronix_Queue_Summary` (read-only). | True/backed | Keep; label the snapshot read-only. |
| Queue retry/cancel/run buttons | Existing queue routes behind `queue_live`. | Future while dormant | Hidden while `queue_live=false`; no activation control. |
| “Queued automatic updates are not enabled yet.” | `queue_live=false`, `queue_enabled=false`, save path forces false. | True/backed | Keep; state that WordPress continues on its native path. |
| “Recently blocked” / 30 days | Free log at DB level with a 30-day bound. | True/backed | Keep. |
| “Checks use the compatibility data WordPress.org publishes. Items with no data are allowed but flagged.” | Unknown compatibility data is allowed; it is not necessarily flagged. | **False** | Replace with: unknown does not block and is not treated as safe. |
| License-inactive screen | `check_shield_permission()` (capability + Super Admin + license). | True only when licensed | Keep; no queue promise. |
| Multisite live / E2E | Not run. | **False if claimed** | Do not claim. |

No control may write a setting that no backend consumes. `control_php_version`
and `control_wp_version` remain backend-only (they are consumed by the
compatibility policies); they are not visible, so they add no claim.

## 3. R-04 — version control and abandoned advisories

### 3.1 Detection state

The detector returns a tri-state for each item:

| Value | Meaning | Policy behavior |
|---|---|---|
| `yes` | Native VCS checkout or Composer pair detected (or the public filter returns true). | Deny when the matching lock applies. |
| `no` | Checked and not controlled. | Allow. |
| `unknown` | The detector could not read the item root, the native detector is unavailable, a symlink escapes the root, or containment fails. | **Fail open (allow)** and log the reason once per request. |

Rules:

1. Native VCS is authoritative. WordPress always refuses `.git/.svn/.hg/.bzr`
   checkouts; turning `version_control_lock` off never overrides that.
2. Composer requires both `composer.json` and
   `vendor/composer/installed.json` inside the item root. A partial pair is
   `no`, not `unknown`; it is a determinate “not Composer-managed” answer and
   must not log a fail-open row.
3. The detector never follows a symlink outside the item root. An escaping
   link is `unknown` and is logged.
4. `updatronix_pro_is_version_controlled` remains the extension point. A filter
   that changes the default result resolves `unknown` to `yes`/`no` only when
   it actually changes the value.
5. The request-scoped cache stores the tri-state, not only a boolean.
6. The Shield payload exposes:
   - `lock` (`version_control_lock`);
   - `checked`, `total`, `truncated`;
   - native detected/unknown counts and item names;
   - Composer detected/unknown counts and item names;
   - the core native state.

The screen must show `unknown` as “could not be inspected; not treated as
safe”, never as “clean”.

### 3.2 Abandoned advisory

- Source: WordPress.org `last_updated` through the fixed `plugins_api` /
  `themes_api` calls.
- Threshold: `updatronix_pro_abandoned_threshold_days`, default 365 days. The
  screen reads the threshold from the payload; it does not hard-code 12 months.
- Cache: site transient, 7 days, 200 entries, 20 lookups per recheck.
- States per cached item: `abandoned`, `current`, `unknown`.
- `unknown` means no usable `last_updated` (API error, empty value, or not
  checked). It is advisory and never blocks.
- The payload exposes `enabled`, `threshold_days`, cached counts, flagged
  items, unknown items, and the number of installed items not present in the
  cache.
- The policy remains network-free on the enqueue/execution path.

### 3.3 Fail-open log gap (4.3c)

The 4.3c counterfactual recorded
`class-updatronix-version-control-detector.php:155` as a unit gap: a mutated
`||` logs a fail-open row when only one Composer marker exists, while the real
branch returns `no` silently. 5.1 touches that line for the tri-state, so it
adds a unit assertion that a partial pair is `no` **and** writes no
fail-open log row. The remaining 4.3c gaps stay Phase 6 debt.

## 4. R-07 — auto re-enable

### 4.1 Eligibility (all must hold)

1. `safe_mode`, `re_enable`, and `incompatible_behavior=disable` are on.
2. The item is in the Shield ledger (Shield disabled it).
3. The item is a plugin or theme, not core.
4. A pending native offer exists.
5. The offer is a **strictly newer** version than the blocked target.
6. The offer passes native guards, WordPress/PHP compatibility, version
   control, and the abandoned advisory.
7. The user has not already re-enabled auto-updates for the item.

The same target is never re-enabled automatically. A user re-enabled item only
loses its ledger entry. Core is never touched.

### 4.2 Visible state

The backend exposes one entry per ledger item:

| State | Meaning |
|---|---|
| `eligible` | All conditions hold; the next update check will re-enable it. |
| `waiting_for_newer_release` | Same or older offer; the same release stays manual. |
| `no_offer` | No pending native offer. |
| `policy_blocked` | A newer offer exists but a blocking policy denies it; the reason code/message is shown. |
| `paused` | The feature is off (`safe_mode`, `re_enable`, or behavior not `disable`). |
| `user_reenabled` | The user already turned auto-updates back on; Shield will drop its record. |
| `toggle_failed` | Shield tried to re-enable but the native toggle did not change; it retries on the next check. |

Each entry also exposes the blocked target, the current target, the block
reason, `disabled_at`, and the last attempt time/result. Attempt metadata is
stored in the existing bounded ledger; no new option or table is added.

## 5. `incompatible_behavior`

- `disable`: deny the job; if the native toggle is on, turn it off through
  `Updatronix_AutoUpdates`, verify it, and record the item + target in the
  ledger.
- `cancel`: deny the job only; leave the native toggle exactly as it was.
- Core has no auto-update option. Both values deny that core release only;
  the screen must not describe core as “turned off”.
- The two options are not synonyms. The `disable` label must say “turn off
  auto-updates for that item”; the `cancel` label must say “cancel this
  update only”.

## 6. R-02 — read-only queue summary

- The screen shows pending/running/failed/done/blocked/uncertain counts, the
  last run, the next worker, the next due job, and stale-lease/dormant/table
  warnings.
- The summary never claims, schedules, cancels, or executes.
- No activation control exists. The switch is disabled while
  `queue_live=false`; `queue_enabled` is forced false on save.
- Retry/cancel/run actions are hidden while `queue_live=false`.
- The copy says the queue is dormant and WordPress continues on its native
  path.

## 7. Decisions pinned by 5.1

- **5.1-D1 — Tri-state detection.** The detector exposes `yes|no|unknown`;
  unknown fails open, is logged, and is shown as unknown. A partial Composer
  pair is `no` and is not logged.
- **5.1-D2 — Detection drives the card.** The version-control badge and panel
  read the detector payload, not `safe_mode`.
- **5.1-D3 — Threshold from the backend.** The abandoned copy reads the
  filterable threshold; it never hard-codes 12 months.
- **5.1-D4 — Re-enable state is shared.** `maybe_reenable()` and the status
  payload use one evaluation function, so the screen cannot disagree with the
  action.
- **5.1-D5 — Attempts live in the ledger.** The bounded Shield ledger stores
  `last_attempt_at` and `last_attempt_result`; no new store.
- **5.1-D6 — Master-switch copy is exact.** “Protect this site” describes the
  native automatic-update adapter; Flow’s use of the same policy chain is
  stated separately.
- **5.1-D7 — Dormant queue.** No activation control, no `queue_live` flip, no
  queue action while dormant.

## 8. Tests

Unit:

- detector tri-state for native yes/no/unknown, Composer yes/no/unknown,
  partial pair (no + no log), symlink/unreadable (unknown + log), filter
  override, cache;
- abandoned threshold, cached `abandoned/current/unknown`, unknown never
  abandoned;
- re-enable eligibility for each state, same target, older target, no offer,
  policy denial, paused, user re-enabled, toggle failure;
- `disable` vs `cancel`, including core;
- settings defaults/partial saves and the new payload keys.

Integration:

- the Shield payload carries `version_control_state`, `abandoned_state`, and
  `reenable_state` while `queue_live=false`;
- a Composer fixture is detected and the lock is consumed;
- a cached abandoned item and an unknown item surface truthfully;
- a ledger item with a newer offer is `eligible`; the same target is
  `waiting_for_newer_release`;
- capability/license gating still applies to the payload.

JS:

- version-control summary counts/labels;
- abandoned threshold and unknown labels;
- re-enable state labels;
- disable vs cancel option copy;
- dormant queue copy and hidden actions.

Accessibility:

- every switch has a visible label and description;
- the lock state is text (`Locked`/`Off`), not colour alone;
- the radio group has a label/legend;
- the re-enable and abandoned lists have accessible names and headers;
- disabled controls keep their description and explain why.

## 9. Acceptance

- Every visible Shield string maps to a backend path or is removed/hidden.
- Unknown VCS/Composer state is shown as unknown, not safe.
- The abandoned threshold shown equals the filterable backend value.
- Re-enable shows why an item is eligible or not; the same target stays
  manual.
- `disable` and `cancel` are described and tested as different behaviors.
- The queue stays dormant; no activation control or `queue_live` flip exists.
- No second screen, tab, queue, journal, lock, or runner.
