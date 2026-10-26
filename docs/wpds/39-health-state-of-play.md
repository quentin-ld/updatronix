# Health — state of play

**Date:** 2026-10-26
**Screen:** Health (`health`, Free)
**Contract:** `04-screen-contracts.md` §8
**Status:** Audit complete for code and automated tests; the manual
keyboard/screen-reader/browser pass and a live retry remain for Phase 6.

## Contract

A readiness summary, six environment checks, open issues from failed updates,
recently resolved failures, a retry flow, and a diagnostics dialog with copy and
download. The snapshot is cached for five minutes and rebuilt on “Run check”.

## Claims matrix

| Claim | Where | Code path | Runtime proof | Verdict |
|---|---|---|---|---|
| Six readiness checks | Checks card | `Updatronix_Health::build_checks()` returns WordPress, PHP, disk, cron, lock, retries. | New integration test asserts six rows. | True |
| Open issues come from failed updates | Open issues | Failed log rows are grouped by item in the 30-day window. | Health snapshot test + existing logger tests. | True |
| Retry is limited to plugin/theme failures | Retry action | `queue_retry()` rejects core/translation, expired, non-failed, and unauthorized items. | New integration test covers the missing-log 404; the guards remain unit-reviewable. | True |
| Diagnostics include environment data | Diagnostics dialog | `get_diagnostics()` returns the environment table and text bundle. | New integration test asserts both keys and non-empty text. | True |
| A diagnostics failure can be retried | Diagnostics dialog | `canLoadDiagnostics()` allows retry after an error; the dialog renders Retry. | 4 new JS cases. | True |
| Run check reports failure | Run check | A failed forced refresh now creates an error notice. | Code path; no component-level failure test yet. | True |
| Empty and logging-off states are explained | Open issues | `EmptyState` and the logging-off `Notice` render. | Existing review 18/32. | True |

## Internal mechanics

| Layer | Path | Notes |
|---|---|---|
| Screen | `assets/src/js/pages/HealthPage.js` | Summary, checks, issues, resolved, retry dialog, diagnostics dialog. |
| Diagnostics helper | `assets/src/js/pages/healthDiagnostics.js` | `canLoadDiagnostics()` decides reuse vs retry. |
| Snapshot | `inc/classes/class-updatronix-health.php` | Cached 5 min; force rebuild on Run check. |
| Checks | `build_checks()` / `check_*()` | WordPress, PHP, disk, WP-Cron, lock, retry availability. |
| Retry | `queue_retry()` + `run_retry()` | Capability, 30-day window, lock, 3/hour rate limit, cron retry. |
| REST | `inc/classes/class-updatronix-settings.php` | `/health`, `/health/check`, `/health/diagnostics`, `/health/retry`. |

## Findings and fixes

| ID | Severity | Finding | Fix |
|---|---|---|---|
| HT-01 | MEDIUM | A diagnostics request error was cached forever: the dialog's guard returned early and offered no retry. | `canLoadDiagnostics()` now permits a retry after an error; the error state renders a Retry button. |
| HT-02 | MEDIUM | A failed “Run check” was silent; only the inline page error appeared when a snapshot already existed. | The failure path now creates an error notice. |
| HT-03 | MEDIUM | The Health REST surface had no dedicated integration coverage. | Added `HealthRestApiTest` for snapshot structure, diagnostics, missing-log 404, and capability 403. |
| HT-04 | INFO | The 2026-10-25 review accepted the diagnostics dialog, absolute dates, and Copy details. | Recorded as intentional deviations; no change. |
| HT-05 | INFO | Open issues in the local fixture are review data, not production sample data. | Data-only; code path exercised by the snapshot test. |

No P0/P1 defect was found in the snapshot builder, the retry capability gate,
or the diagnostics payload.

## Native vs custom

| Surface | Implementation | Native option | Decision |
|---|---|---|---|
| Snapshot | Custom service over logs + Site Health data | No native snapshot | Justified |
| Checks | Native WordPress APIs (`get_core_updates`, `wp_next_scheduled`, `disk_free_space`) | Native | Native |
| Retry | WP-Cron + `Plugin_Upgrader`/`Theme_Upgrader` | Native | Native |
| Diagnostics | Request data + redacted text | No native bundle | Justified |
| UI | WPDS `Card`, `Badge`, `EmptyState`, `Dialog`, `Notice` | WPDS | Native |

## Test inventory

| Suite | Coverage |
|---|---|
| JS | `tests/js/healthDiagnostics.test.js` (4), plus the shared 56 |
| Integration | `HealthRestApiTest` (4), 73 tests total |
| Unit | `AdminTabsTest` shell coverage; Health builder uses integration |

## Feature development guide

1. **Check** — add a `check_*` method, return the shared `check()` shape, and
   include it in `build_checks()`.
2. **Data** — read native WordPress state; do not duplicate it in an option.
3. **Issue** — derive from failed log rows; keep the 30-day window and grouping
   key stable.
4. **Retry** — reuse the capability, lock, rate-limit, and cron path; do not
   bypass `queue_retry()`.
5. **UI** — use WPDS cards/badges/dialogs; keep the empty, logging-off, loading,
   error, and retry-error states.
6. **Tests** — integration for endpoints and snapshot shape, JS for dialog
   helpers, E2E for the full retry when available.
7. **Docs** — update this file, the contract, and the implementation log.

## Known gaps

- E2E remains blocked by `libnspr4.so`.
- No manual screen-reader pass on the diagnostics dialog, retry dialog, or check
  grid.
- No live plugin/theme retry in this pass.
- The local open issues are review fixtures.
- No component-level Run-check 500 test.
- No version bump.

## Exit

- Claims: all true or explicitly accepted.
- P0/P1: HT-01/02/03 fixed; HT-04/05 recorded.
- Tests: JS 60, integration 73, unit 213, build pass.
- Review/security: diagnostics/retry routes reuse the existing capability and
  nonce; no new surface.
- No version bump.
