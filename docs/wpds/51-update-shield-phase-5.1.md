# Update Shield — Phase 5.1

**Date:** 2026-10-27
**Screen:** Update Shield (`updatronix-3000`, Pro)
**Status:** Implemented and verified by the automated suites; full E2E passed
2/2. The queue remains dormant: `queue_live=false`, `queue_enabled=false`, the
Shield queue switch is disabled, and no version was bumped.
**Related:** `34-roadmap-matrix.md` (R-02, R-04, R-07),
`45-update-engine-architecture.md`, `47-update-engine-4.3a-shield-policies.md`,
`48-update-engine-4.3b-queue-live.md`, `50-update-engine-4.4-flow.md`,
`FEAT-P-SHIELD`, `FEAT-P-QUEUE`.

---

## 1. What changed

The Shield screen now reads a read-only `Updatronix_Shield_State` payload instead
of inferring version-control, abandoned, and re-enable states from unrelated
settings.

| Area | Change |
|---|---|
| State | `Updatronix_Shield_State` returns `version_control`, `abandoned`, and `reenable` sections; it only reads detectors, the bounded cache, the ledger, and native offers. |
| REST | `GET /updratronix-pro/v1/shield/settings` includes the three state sections; the save path is unchanged. |
| Version control | The screen shows the Composer lock separately from native VCS refusal. Native Git/SVN/Hg/Bzr is always refused by WordPress; the Composer switch remains usable because the engine’s version-control policy consumes it for Shield-managed jobs. |
| Abandoned | The screen shows the configured threshold, cached counts, unknown rows, and unchecked rows; unknown is never described as safe. |
| Re-enable | The screen shows blocked target, current offer, eligibility state, last attempt, and failure reason. Same-target stays manual. `re_enable` is disabled only while Safe Mode is off. |
| Queue | The summary is read-only; the switch stays disabled while `queue_live=false`. |
| Copy | `disable` and `cancel` are described as different outcomes; the Protection copy names native automatic updates and Flow’s shared policy chain. |
| Tests | 4 unit cases, 7 integration cases, and 20 pure JS cases cover the tri-state detection, abandoned state, re-enable status/attempts, filtered defaults, and read-only payload. |

## 2. Claims matrix

| Claim | Backend | Verdict |
|---|---|---|
| “Protects WordPress’s native automatic updates” | `safe_mode` + the Shield policy chain | True |
| “Every Update Flow step evaluates the same Shield policies” | Flow enqueues through the kernel; Shield chain at enqueue and execution | True |
| “Version control lock” badge | `version_control_lock` for Composer; native VCS is always refused by WordPress | True, with separated copy |
| “Composer lock” switch | `version_control_lock`; usable for Shield-managed jobs and applied by the native Safe Mode adapter when `safe_mode` is on | True |
| “When an update is incompatible” | `incompatible_behavior` (`disable` / `cancel`); core always canceled for that release | True |
| “Re-enable auto-updates automatically” | `re_enable`; only items disabled by Shield and only a newer compatible target; disabled while Safe Mode is off | True; same target stays manual |
| “Email the technical manager” | `notify_email`; one digest per run | True |
| “Flag abandoned plugins” | `flag_abandoned`; advisory only, threshold filter | True |
| “Abandoned threshold” | `updatronix_pro_abandoned_threshold_days`, default 365; the screen does not hard-code 12 months | True |
| “Unknown is not treated as safe” | `unknown` and `unchecked` are separate counts/rows; neither is shown as safe | True |
| “Auto re-enable is on/off” | `reenable_state.enabled`, eligible count, per-item state, reason, and last attempt | True |
| Queue counts and last run | `Updatronix_Queue_Summary::get()` | True, read-only |
| “Queue automatic updates” switch | `queue_live && queue_enabled`; disabled while dormant | True, disabled |
| “This switch stays off until a live end-to-end queue test passes” | Save path forces `queue_enabled=false` while `queue_live=false` | True |

## 3. R-02 / R-04 / R-07

- **R-02 — queue:** table, scheduler, worker, `/queue` routes, local live
  fixture, read-only summary, and Health checks exist. The queue is dormant by
  owner decision; activation needs a staged run and a new explicit decision.
- **R-04 — version control / abandoned:** native VCS and Composer detection are
  tri-state (`yes`, `no`, `unknown`); unknown fails open and is shown as
  unknown. A partial Composer pair is `no` and writes no fail-open row. The
  abandoned advisory uses the bounded cache and a configurable threshold; the
  screen does not claim a live host scan that did not run.
- **R-07 — re-enable:** the ledger, newer-target policy, and attempt states are
  exposed. A same-target offer remains manual; a failed toggle stays visible.
  The status and action share `evaluate_entry()`.

## 4. Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | **352 tests, 1352 assertions, pass.** |
| Pro `bin/harness integration` | **120 tests, 1073 assertions, 1 intentional Multisite-only skip, pass.** |
| Pro `bin/harness test:js` | **81 tests across 6 files, pass** (20 Shield state cases). |
| Pro `bin/harness lint:js` | Pass, 0 errors/warnings. |
| Pro `bin/harness lint:files` on changed PHP | Pass, 10 files, 0 errors/warnings. |
| Pro `bin/harness build` | Pass. |
| Free suites | 216 unit, 84 integration, 67 JS; the 5.1 screen did not change Free code. |
| Free E2E | **2/2 Playwright tests passed.** |
| Pro `bin/harness e2e --list` | Exit 0; Pro has no E2E suite. |

## 5. Counterfactual

Base `c9d104b`, `HARNESS_COUNTERFACTUAL_MAX=200`:

- 86 substitutable changed lines; 73 executed by the unit suite; 73 checked.
- **64 killed.**
- **0 survived.**
- **9 classified equivalent:**
  - `class-updatronix-abandoned-detector.php:74` — numeric threshold cast; the
    `int` return type coerces it.
  - `:215` — `checked_at` numeric cast; callers cast it.
  - `class-updatronix-shield-enforcement.php:347` — `site_id` numeric cast; the
    job key concatenates it and the property is typed `int`.
  - `:358`, `:359`, `:360` — defensive `?? true` defaults; the settings
    function merges the defaults.
  - `:361` — the abandoned policy is advisory and never changes the allow
    result.
  - `:419` — `gmdate()` numeric cast.
  - `class-updatronix-shield-ledger.php:130` — redundant-write guard only.
- 13 substitutable lines are integration-only and outside the unit gate.

The full Phase 6 run checked all 73 executed lines: 64 killed, 0 survived,
9 classified equivalent.

## 6. Known gaps

- No staged host run with real cron; the local fixture remains the live proof.
- No live Multisite pass.
- Full E2E passed on this host; it is not a substitute for a staged host run.
- Phase 6 closes the abandoned-policy defensive fallback and the notifier
  32-recipient boundary; the version-control fail-open log gap is closed here.
- Queue activation is an owner decision after a staged run; this phase did not
  flip `queue_live`.
- The screen has not had a fresh manual screen-reader pass.

## 7. Decisions

- **5.1-D1 — Read-only state.** The state builder never saves settings and never
  performs a network lookup.
- **5.1-D2 — Native VCS is not a toggle.** WordPress always refuses updates in
  native checkouts; only the Composer lock is a Shield setting.
- **5.1-D3 — Abandoned is advisory.** Unknown is displayed as unknown and never
  treated as safe.
- **5.1-D4 — Re-enable is narrow.** Only Shield-disabled items with a newer
  compatible target are eligible; same-target stays manual.
- **5.1-D5 — Queue stays dormant.** The summary is read-only and the switch is
  disabled until a staged run and a new owner decision.
- **5.1-D6 — Copy names the native adapter and Flow’s shared policy chain;
  engine-consumed switches stay usable.**
- **5.1-D7 — Queue stays dormant with no activation control.**

## 8. Exit

- Claims: true or explicitly qualified.
- P0/P1: none found in this pass.
- Tests: Pro 352 unit / 120 integration / 81 JS; Free 216 / 84 / 67; E2E 2/2.
- Review/security: no new write path; the state builder is read-only.
- No version bump, no commit.
