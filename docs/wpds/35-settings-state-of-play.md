# Settings — state of play

**Date:** 2026-10-26
**Screen:** Settings (`settings`, Free)
**Contract:** `04-screen-contracts.md` §9
**Status:** Audit complete for code and automated tests; the keyboard,
screen-reader, and browser passes remain for the hardening phase.

## Contract

One manually saved surface for logging, retention, notifications, and the
uninstall-data opt-in. The page header carries Save changes; the scroll SaveBar
repeats it. The primary action is never hidden. The screen reads and writes
`updatronix_settings` through `updatronix/v1/settings`; the schedule subtree is
stored in the network schedule option.

## Claims matrix

| Claim | Where | Code path | Runtime proof | Verdict |
|---|---|---|---|---|
| “One save for the whole surface” | Surface note | `SettingsPanel` keeps a local draft; only `handleSave` calls `saveSettings`. | Draft edits do not write until Save. | True |
| “Toggles are staged until you choose Save changes” | Surface note | `setDraftValue`, `handleNotifyChange`, recipient handlers update local state only. | No REST call before Save. | True |
| “Delete all data on uninstall” | Data card | `delete_data_on_uninstall` in `updatronix_settings`; `Updatronix_Uninstall::should_delete_data()` reads it. | New integration test toggles true and false and inspects the uninstall decision. | True |
| “Off by default so reinstalling keeps your data” | Data card | `UPDATRONIX_SETTINGS_DEFAULTS['delete_data_on_uninstall'] = false`. | Integration test asserts the default. | True |
| “Uninstalling permanently deletes all Updatronix data” | Data card, opt-in on | Uninstall removes logs, settings, schedule, and network options when the opt-in is true. | Integration test proves the decision; full deletion still belongs to the uninstall suite. | True |
| “Recovery mode emails stay on” | Notifications card | `notifications_mode=disabled` suppresses update emails; `recovery_mode_email` and technical mail are outside that suppression. | Notification tests cover the disabled mode; recovery is filtered separately. | True |
| “Email delivery is handled by WordPress” | Closing note | Notifications use `wp_mail` and native update filters. | No custom mailer. | True |

## Internal mechanics

| Layer | Path | Notes |
|---|---|---|
| React screen | `assets/src/js/components/SettingsPanel.js` | One local draft, `savedRef` snapshot, WPDS controls. |
| Pure draft comparison | `assets/src/js/components/settingsDraft.js` | `editableSignature` and `isSettingsDirty`; only the fields this screen edits are compared. |
| Settings hook | `assets/src/js/hooks/usePluginSettings.js` | `PUT updatronix/v1/settings`; shared settings update only on success; notices on success/warning/error. |
| Navigation guard | `assets/src/js/pages/SettingsPage.js` | Dirty confirmation on rail navigation and `popstate`; the URL is restored when the user cancels. |
| REST | `inc/classes/class-updatronix-settings.php` | `GET`/`PUT /settings`; `UPDATRONIX_CAP_MANAGE`; nonce through the REST middleware. |
| Storage | `inc/settings/options.php` | JSON option; booleans and emails sanitized; schedule in the network schedule option. |
| Uninstall | `inc/classes/class-updatronix-uninstall.php` | Opt-in gate; multisite guard; main-site and network cleanup. |

## Findings and fixes

| ID | Severity | Finding | Fix |
|---|---|---|---|
| SET-01 | HIGH | `popstate` changed the destination without running the dirty-draft guard. A browser Back/Forward could silently discard edits. | The `popstate` handler now confirms, restores the current tab URL when the user cancels, and only then switches. |
| SET-02 | MEDIUM | The dirty comparison lived inside the component and had no regression test; a field revert could regress to a one-way dirty flag. | Extracted `editableSignature`/`isSettingsDirty` into `settingsDraft.js` and added 7 Vitest cases, including the uninstall opt-in revert. |
| SET-03 | MEDIUM | The uninstall opt-in had no dedicated test proving the uninstall runner reads it. | Added `SettingsUninstallOptInTest`: default false, persist true/false, and inspect `Updatronix_Uninstall::should_delete_data()`. |
| SET-04 | LOW | The dirty-guard confirmation is a native `window.confirm`, not a WPDS dialog. | Accepted for now; the reference uses a lightweight confirm. Revisit only if the shell adopts a shared unsaved-changes dialog. |
| SET-05 | INFO | Save failure keeps the draft and the dirty state because `setSettings` only runs after a successful response. | Verified by code reading; a component-level network test is still missing. |
| SET-06 | MEDIUM | The recipient error was rendered in `details`, which WPDS does not associate with the input or announce. | The error now has an `id` and `role="alert"`; the control receives `aria-invalid` and `aria-describedby`. |
| SET-07 | MEDIUM | The `popstate` guard had no regression test. | Extracted `canLeaveDraft()` and added 4 Vitest cases covering clean, confirmed, canceled, and message paths. |
| SET-08 | MEDIUM | The uninstall opt-in test bypassed the REST route the Data card uses. | Added a `PUT /updatronix/v1/settings` test with a nonce and an administrator. |
| SET-09 | LOW | The irreversible opt-in could be enabled with `manage_updatronix` alone. | Turning it on now also requires `manage_options`; turning it off stays available. |
| SET-10 | LOW | The multisite uninstall tests called undefined `updronix_*` helpers, so they only passed while skipped. | Corrected them to the real `updatronix_*` helpers. |

The 2026-10-24 QA finding that Settings re-baselined the draft before the
request completed no longer matches the current code: `saveSettings` updates the
shared settings only after the response, and `SettingsPanel` re-baselines only
through its `settings` effect. The new dirty tests pin the revert behavior.

## Native vs custom

| Surface | Implementation | Native option | Decision |
|---|---|---|---|
| Settings storage | JSON option `updatronix_settings` | Options API | Native |
| REST | `updatronix/v1/settings` | REST API + capability + nonce | Native |
| Save feedback | WordPress notices store + shell snackbar host | Notices store | Native store; shell owns placement |
| Email routing | Update filters + `wp_mail` | WordPress mail/update APIs | Native |
| Uninstall | `uninstall.php` + opt-in option | `register_uninstall_hook`/`uninstall.php` | Native |
| Notification chips | `SearchableChipSelectControl` | WPDS component | Custom behavior only where WPDS has no chip input |

## Test inventory

| Suite | Coverage |
|---|---|
| JS | `tests/js/settingsDraft.test.js` (11), `tests/js/routes.test.js` (16) |
| Integration | `RestSettingsAuthTest`, `SettingsPostSaveActionTest`, `SettingsUninstallOptInTest` (3), 69 tests total |
| Unit | `SettingsResolveSiteIdTest`, plus the 213-test suite baseline |

## Feature development guide

1. **Setting** — add the default and sanitizer in `inc/settings/options.php`;
   add the REST schema entry in `class-updatronix-settings.php`; decide whether
   it is site-scoped or network-scoped.
2. **Capability and nonce** — `UPDATRONIX_CAP_MANAGE`; REST nonce through
   `apiFetch`; no new `admin-post` path unless the native REST route cannot
   express the action.
3. **Data** — prefer the settings option; use a dedicated option or custom
   table only when the data has its own lifecycle.
4. **UI** — add the control to `SettingsPanel`, keep it in the local draft, and
   include it in `editableSignature` when it must mark the surface dirty.
5. **Save** — one Save for the surface; never update the saved snapshot before
   the request succeeds.
6. **Gating** — Settings is Free; Pro-only controls belong in the Pro screens,
   not here.
7. **Tests** — unit for sanitizers, integration for REST and storage, JS for
   dirty/save behavior, E2E when the host can run it.
8. **Docs** — update this file, the screen contract, the implementation log, and
   the POT when a user-facing string changes.

## Known gaps

- E2E is blocked by the host's missing `libnspr4.so`.
- No component-level test simulates a 500/network failure on Save; the code path
  is verified by reading and by the dirty-signature tests.
- The latest WPDS review did not re-run the Settings 390/760 layouts with
  notifications enabled; that belongs to the hardening pass.
- A manual browser pass with an opt-in uninstall is destructive and was not run.
  The decision is covered by integration.
- The recipient error is now announced, but a real screen-reader pass has not
  been recorded.
- Retention still clamps per keystroke; the contract's validation-error state is
  recorded for the next pass rather than implemented.
- Free defaults `licenseActive` to false. Pro 0.8+ must supply the
  `updatronix_license_active` filter; an older Pro release would hide the three
  Pro destinations. No legacy shim is planned.

## Review and security

The review pass found SET-06 to SET-08; all are fixed and covered. The security
pass found the irreversible opt-in could be enabled with `manage_updatronix`
alone (SET-09, fixed) and the multisite helper typo (SET-10, fixed). No
injection, authorization, or secret-storage issue was found on this screen.

## Exit

- Claims: all true or explicitly accepted.
- P0/P1: SET-01 fixed; SET-02/03/06/07/08 covered; SET-09 fixed.
- Tests: JS 27, integration 69, unit 213, build pass.
- Review/security: completed; findings addressed in this screen.
- No version bump.
