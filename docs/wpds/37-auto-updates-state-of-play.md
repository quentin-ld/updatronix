# Automatic updates — state of play

**Date:** 2026-10-26
**Screen:** Auto updates (`auto-updates`, Free)
**Contract:** `04-screen-contracts.md` §2
**Status:** Audit complete for code and automated tests; the manual
keyboard/screen-reader/browser pass and a component-level failure test remain
for Phase 6.

## Contract

One manually saved surface for core mode, translations, themes, and plugins.
Native `auto_update_*` options are the source of truth. Constants and the Pro
Safe Mode lock can disable a section; the screen explains why.

## Claims matrix

| Claim | Where | Code path | Runtime proof | Verdict |
|---|---|---|---|---|
| “Core, translation, theme, and plugin settings in one place” | Header | `AutoUpdatesPanel` renders all four sections from one REST payload. | `AutoUpdatesRestApiTest` checks the structure. | True |
| “One save for the whole surface” | Surface note | `saveAll` stages a draft and sends only changed mutations. | 6 new mutation-plan tests. | True |
| “Turning auto-updates on does not install anything right away” | Surface note | The screen writes native options; the next scheduled check reads them. | Native option behavior. | True |
| A locked section explains why | Section notice | `ConstantNotices` + `isSectionLocked()` + `auto_update_locked_reason`. | Existing review 15/28. | True |
| A failed save does not silently leave a partial state | Save flow | `saveAll` refetches after a mutation failure and reports that the page shows the server state. | Code path; a component-level network test is still missing. | True |
| A failed refresh is visible when data is already on screen | Refresh flow | `fetchError && data` renders a warning with Retry. | Code path; no component test yet. | True |

## Internal mechanics

| Layer | Path | Notes |
|---|---|---|
| Screen | `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js` | Four sections, one SaveButton + scroll SaveBar. |
| Draft and mutation plan | `assets/src/js/components/autoUpdates/autoUpdatesDraft.js` | `buildAutoUpdateDraft()` and `collectAutoUpdateMutations()`. |
| State | `assets/src/js/hooks/useAutoUpdates.js` | Fetch, per-item mutations, save-all, refetch on partial failure, dismiss constants. |
| REST | `inc/classes/class-updatronix-settings.php` | `/auto-updates`, `/core`, `/translation`, `/plugin`, `/theme`, `/dismiss-constant`. |
| Native data | `auto_update_plugins`, `auto_update_themes`, `auto_update_translations`, core constants | No parallel option for the toggles. |
| Constants | `Updatronix_AutoUpdates::get_constants()` | `AUTOMATIC_UPDATER_DISABLED`, `DISALLOW_FILE_MODS`, `WP_AUTO_UPDATE_CORE`, `DISABLE_WP_CRON`. |

## Findings and fixes

| ID | Severity | Finding | Fix |
|---|---|---|---|
| AU-01 | HIGH | `saveAll` sent sequential mutations; a later failure left earlier changes on the server with no reconciliation. | It now builds one ordered mutation plan, and on failure refetches the whole dataset before reporting. The notice says the page shows the server state. |
| AU-02 | MEDIUM | A refresh failure after data was loaded was silent because the error branch only rendered when `!data`. | The panel now renders a warning with Retry while keeping the last data. |
| AU-03 | MEDIUM | The save logic had no unit coverage; a stale draft or skipped change could regress unnoticed. | Extracted `buildAutoUpdateDraft()`/`collectAutoUpdateMutations()` and added 6 Vitest cases for defaults, order, and changed-only mutations. |
| AU-04 | LOW | `SaveBar` received an `onDiscard` prop it does not render. | Removed the dead prop and handler. |
| AU-05 | INFO | Constants and locked sections were verified; no new defect found. | Existing notices and tests remain the contract. |

No P0/P1 defect was found in the REST permission gates or the native option
writes. The security surface is the existing capability check plus the REST
nonce; no new exposure was introduced.

## Native vs custom

| Surface | Implementation | Native option | Decision |
|---|---|---|---|
| Core mode | `auto_update_core` / `WP_AUTO_UPDATE_CORE` | Native options/constants | Native |
| Plugin toggles | `auto_update_plugins` | Native option | Native |
| Theme toggles | `auto_update_themes` | Native option | Native |
| Translation toggle | `auto_update_translations` | Native filter/option | Native |
| REST | `updatronix/v1/auto-updates/*` | REST API + capability | Native |
| Locked state | `isSectionLocked()` + reasons | No native UI | Custom explanation over native state |

## Test inventory

| Suite | Coverage |
|---|---|
| JS | `tests/js/autoUpdatesDraft.test.js` (6), plus the shared 39 |
| Integration | `AutoUpdatesRestApiTest`, `AutoUpdateDelay*`, `AutomaticUpdateResultNotesTest` |
| Unit | `AutoUpdateDelay*`, `CoreUpdateLogVersionsTest` |

## Feature development guide

1. **Setting** — prefer the native `auto_update_*` option; do not create a
   parallel boolean.
2. **REST** — add the route under `updatronix/v1/auto-updates`, a capability
   callback, and an args schema.
3. **Draft** — add the field to `buildAutoUpdateDraft()` and the mutation plan
   in `collectAutoUpdateMutations()`.
4. **UI** — add the section/row, keep the draft local, and disable it when the
   section is locked or busy.
5. **Save** — one Save; on failure reconcile with the server before reporting.
6. **Constants** — add the constant to `get_constants()` and a notice reason;
   never hide the lock silently.
7. **Tests** — unit for the mutation plan, integration for REST/options, JS for
   the draft, E2E when available.
8. **Docs** — update this file, the screen contract, and the implementation log.

## Known gaps

- E2E remains blocked by `libnspr4.so`.
- No component-level test simulates a 500 on the third mutation.
- No manual screen-reader pass on the constant notices or the refresh warning.
- The native option writes are covered by integration but not by a live update
  run.
- No version bump.

## Exit

- Claims: all true or explicitly accepted.
- P0/P1: AU-01 fixed; AU-02/03 covered; AU-04 removed.
- Tests: JS 45, integration 69, unit 213, build pass.
- Review/security: code review completed; no blocking security finding.
- No version bump.
