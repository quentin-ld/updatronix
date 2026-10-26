# Updatronix 3000 state of play

**Date:** 2026-10-26
**Scope:** Pro destination `updatronix-3000` in `updatronix-pro`, and the
Free-only presentation in `updatronix`.
**Status:** Phase 3.1 audit complete. P0/P1 findings fixed. No version bump, no
commit.
**Controllers:** `assets/src/js/components/Updatronix3000Panel.js`,
`LicensePanel.js`, `hooks/useLicense.js`, `licenseKey.js`, `licenseReload.js`,
`assets/src/index.js`, `inc/license/*`, `inc/classes/class-updatronix-pro-bootstrap.php`,
`inc/admin/class-updatronix-pro-enqueue.php`; Free `pages/ProDestination.js` and
`shell/routes.js`.

## What the screen is

The licence screen is the Pro edition's activation and status surface. It is
also the Free-only upgrade presentation when Pro is not installed.

- Inactive: activation empty state, real-format placeholder, inline validation,
  primary activation.
- Active: masked key, registered domain, last check, deactivate.
- States: loading, inactive, activating, active, deactivating, status-service
  error, invalid licence, decryption error, development mode.
- Feature list: Update Shield, Update Flow, White label, Advanced exports
  (CSV and JSON) are available; Queue list and Development tools are roadmap;
  XML export is roadmap.
- A licence change reloads the admin page: Pro's PHP hooks, REST routes, and
  panel chunks are registered at boot from the stored state, so React state
  alone cannot apply the gate.

## Internal mechanics

| Area | Implementation | Notes |
|---|---|---|
| Storage | `updatronix_pro_license` site option; AES-256-CBC with a key derived from three salts | Network-wide; plaintext key is never persisted. |
| Status | `GET updatronix-pro/v1/license/status` | Returns masked key, domain, last check, dev mode, decryption flag. No full key. |
| Activate | `POST .../license/activate` | Validates the real key shape before the callback; 60-second cooldown; redacts the key from upstream messages. |
| Deactivate | `POST .../license/deactivate` | Clears the stored licence; clears an undecryptable key even when status is `invalid`. |
| Permission | `manage_updatronix_pro`; Super Admin on Multisite | Verified with HTTP 403 for a subscriber and unit tests for the Super Admin gate. |
| Nonce | WordPress REST cookie authentication | Native nonce enforcement; application passwords are intentionally nonce-free. |
| Dev mode | `UPDATRONIX_PRO_DEV_MODE` plus a local host (`.test`, `.local`, `.localhost`, localhost, `::1`) | Domain-locked; no license stored. |
| Client key check | `licenseKey.js` | Convenience only; the server is authoritative; case is preserved. |
| Reload after change | `licenseReload.js` | One-shot notice in session storage, then `location.reload()`. |

## Claims matrix

| Claim | Where | Code path | Runtime proof | Verdict | Action |
|---|---|---|---|---|---|
| Update Shield stops incompatible updates and records blocked items | `LicensePanel.js` feature list | `Updatronix_Safe_Mode` blocks and logs; `Shield\get_blocked_history` reads the log | Active screen shows the line; Shield tab is gated by the licence | True | Keep |
| Update Flow runs updates in dependency order | Feature list | Flow storage, REST, UI, runner | Flow tab is gated; runner is code-complete | True (live run still Phase 4) | Keep |
| White label applies name, mark, and colours | Feature list | `inc/white-label/*` | White label tab is gated; options and previews exist | True | Keep |
| Advanced exports offer CSV, XML, JSON | Feature list | Pro filter adds `csv/json/xml`; Free dialog renders formats | CSV and JSON render; XML selection renders a CSV report and downloads `.txt` | **False for XML** | Claim reduced to CSV/JSON; XML roadmap |
| Queue list chains automatic updates | Feature list | `queue_enabled` is stored and rendered; no consumer | No queue screen or runner | **False** | Roadmap badge |
| Development tools expose hooks, functions, REST | Feature list | No dev-tools surface | No screen, no documented pipeline feature | **False** | Roadmap badge |
| “All Updatronix 3000 tools are ready” | Active card | n/a | Contradicted by roadmap items | **False** | Replaced with “unlocks the Pro tools available in this release” |
| Free-only: adds Shield, Flow, white label, advanced exports, queue, dev tools | Free `ProDestination.js` | n/a | Free-only page shows the copy | **False for queue/dev tools; partial for exports** | Copy names available features and roadmap items |
| Licence key is `UPD-PRO-XXXXXXXX-XXXXXXXX-XXXXXXXX` | Placeholder, error, server validator | `licenseKey.js`; `is_valid_key_format()` | Real key accepted; 4×4 rejected; case preserved | True | Keep |
| Full key is never shown after activation | Active card, status response | `mask_license_key()` | Masked as `UPD-PRO-••••••••-••••••••-••••ed14` | True | Keep |
| Deactivation is destructive and confirmed | Active card | `AlertDialog` names the domain; focus starts on Cancel | Verified live | True | Keep |

## Gating matrix

| Situation | Rail | License screen | Pro REST | Pro PHP hooks | Runtime proof |
|---|---|---|---|---|---|
| Free only | Updatronix 3000 presentation | Free `ProDestination` (no form) | None | None | Pro deactivated; new copy verified |
| Pro installed, no licence | Only Updatronix 3000 | Activation only | License routes only | License handler/updater only | Inactive reload; tabs absent |
| Pro + active licence | License + Shield + Flow + White label | Active licence | License + Pro routes | Shield/Flow/White label registered | Active reload; tabs present |
| Activate in-session | After reload: three tabs | Active | Registered on the new request | Registered on the new request | Activation reload verified |
| Deactivate in-session | After reload: license only | Inactive | Pro routes gone on the next request | Pro hooks gone on the next request | Deactivation reload verified |
| Multisite non-Super-Admin | No Pro destinations | No access | 403 | Not relevant | Unit tests; contract now Super-Admin-only |
| No `manage_updatronix_pro` | No Pro destinations | No access | 403 | Not relevant | Unit tests + HTTP 403 |

## REST contract

| Route | Method | Permission | Success | Failure |
|---|---|---|---|---|
| `/license/status` | GET | capability + Super Admin | 200 with status, masked key, domain, last check, dev mode, decryption flag | 403 |
| `/license/activate` | POST | capability + Super Admin | 200 active with masked key and domain | 400 invalid format/server rejection, 429 cooldown, 500 save failure |
| `/license/deactivate` | POST | capability + Super Admin | 200 inactive | 400 inactive/rejected, 500 save failure |

The activate route validates the key shape in `validate_callback` before the
callback. Upstream error messages are redacted with the submitted key. The
status payload never contains the full key.

## States and evidence

| State | Evidence |
|---|---|
| Loading | Spinner + `role="status"` at 1280; no layout jump |
| Inactive | Form at 1280/960/390; placeholder `UPD-PRO-XXXXXXXX-XXXXXXXX-XXXXXXXX`; button enabled; submit-time validation |
| Activating | Input/button disabled; “Activating…” |
| Active | Masked key, domain, last check in site format, Deactivate |
| Deactivating | Dialog names the domain; focus on Cancel; page reloads on success |
| Server rejection | 400 shown inline; input `aria-invalid=true`, `aria-errormessage` points at the error, focus returns to the input |
| Throttle | 429 inline message names the remaining seconds; `retry_after` in the payload |
| Status service error | Dedicated “License status unavailable” card with Retry; no activation form |
| Invalid licence | Warning “Your saved license is no longer valid” above the form |
| Decryption error | Distinct notice + Clear stored licence (confirmed); clear reloads to inactive |
| Development mode | Active card + “No license is stored” note; no key row; verified with a temporary local-host fixture |
| Multisite | Super-Admin-only; no read-only state |

## Findings

| ID | Severity | Status | Summary |
|---|---|---|---|
| LIC-101 | HIGH | Fixed | Queue list and Development tools marked roadmap. |
| LIC-102 | HIGH | Fixed (claim) / deferred (Free XML) | Advanced exports claim reduced; XML defect belongs to the Free export dialog. |
| LIC-103 | HIGH | Fixed | Shield copy no longer claims queue/version-controlled locking. |
| LIC-104 | HIGH | Fixed | Reload after activation/deactivation/clear; one-shot notice. |
| LIC-105 | HIGH | Fixed | Inline activation errors, invalid field, ARIA association, focus. |
| LIC-106 | MEDIUM | Fixed | Status-error card. |
| LIC-107 | MEDIUM | Fixed | Invalid-licence warning. |
| LIC-108 | MEDIUM | Fixed | Decryption recovery with status `invalid`. |
| LIC-109 | MEDIUM | Fixed | `retry_after` and seconds in the 429 message. |
| LIC-110 | LOW | Fixed | `aria-errormessage` + post-render focus. |
| LIC-111 | LOW | Fixed by reload | Last check is fresh after activation. |
| LIC-112 | LOW | Fixed | No key row without a server mask. |
| LIC-113 | INFO | Accepted | Multisite stays Super-Admin-only; contract updated. |

## Divergences and decisions

1. **Roadmap labels, not removal.** The six reference positions stay; Queue
   list and Development tools carry a `Roadmap` badge, and the legend says they
   are not in this release. This keeps the reference composition without a
   false claim.
2. **Advanced exports.** CSV and JSON work; XML does not. The licence screen
   claims only CSV/JSON and marks XML planned. The Free export dialog still
   offers XML and must be fixed in its own screen audit.
3. **Reload boundary.** A client-only state update cannot register Pro's PHP
   hooks or REST routes for the current request. The reload is deliberate; the
   one-shot notice preserves the success message.
4. **Multisite.** The brief's read-only subsite state is not implemented. The
   code denies the status route and the Free rail hides Pro destinations for a
   non-Super-Admin. The contract now says Super-Admin-only.
5. **Free-only copy.** The upgrade destination names the four available
   features and the roadmap items; it no longer promises the queue or dev tools.
6. **XML export defect.** `ExportLogsModal` selects XML but `formatExportBody`
   returns the CSV report, and `downloadExport` has no XML extension/MIME. This
   is recorded for the Free update-log screen audit; it is not silently left as
   a licence claim.

## Test inventory

### Pro

| Suite | Files | Baseline | After |
|---|---|---|---|
| Unit | 19 | 207 tests | 220 tests, 690 assertions |
| JS | 2 | 10 tests | 18 tests |
| Integration | 0 | None | None |
| E2E | 0 | None | Pro has no E2E config; the Free suite passes 2/2 |

New or upgraded coverage:

- `tests/Unit/LicenseRestRouteTest.php`: namespace, three routes, methods,
  permission callback, activation validator accepts the real key and rejects
  4×4/short/empty.
- `tests/Unit/ProConnectorTest.php`: single-site capability and Multisite
  Super Admin.
- `LicenseRestTest`: decryption + `invalid`, no full key in status, activation
  and deactivation message redaction, 429 `retry_after`.
- `LicenseStorageTest`: corrupt key with `invalid` status is a decryption
  error; a decryptable key with `invalid` status is not.
- `tests/js/licenseReload.test.js`: one-shot notice, write-before-reload order,
  blocked storage.

### Free

| Suite | Baseline | After |
|---|---|---|
| JS | 63 tests | 63 tests, pass |
| Unit | 213 tests | Unchanged by this task |
| Integration | 73 tests | Unchanged by this task |

## Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 220 tests, 690 assertions, pass |
| Pro `bin/harness test:js` | 18 tests, pass |
| Pro `bin/harness lint:js` | Pass, 0 warnings |
| Pro `bin/harness build` | Pass |
| Pro `bin/harness lint:files` on modified PHP | Pass |
| Pro `bin/harness lint` | No errors; pre-existing warnings in unrelated tests |
| Pro PHPStan | No errors |
| Free `bin/harness test:js` | 63 tests, pass |
| Free `bin/harness lint:js` | Pass |
| Free `bin/harness build` | Pass; existing runtime-chunk warnings |
| E2E | Free suite 2/2 pass (Playwright Chromium installed in the DDEV container); Pro has no E2E suite. |

## Known limits and Phase 6 follow-ups

1. Pro still has no integration suite. Route registration and validation are
   unit-tested through the recording shim; the full WordPress nonce and
   `rest_api_init` stack is not exercised.
2. Multisite was not run live. The permission matrix is unit-tested and the
   contract now matches the Super-Admin-only implementation.
3. The XML export defect is in the Free export dialog; fix it there and then
   restore the XML claim if the product decides to include it.
4. E2E is available again: the Free suite passes 2/2 after installing Chromium
   and its system libraries in the DDEV container. Pro still has no E2E
   configuration, so the licence journey remains manual/Phase 6.
5. No screen-reader pass and no RTL pass in this environment.
6. The licence screen reloads after a state change. This is required by the
   server-side gate; if the shell later gains a gating event plus server-side
   re-registration, the reload can be revisited.
7. No version bump and no commit.

## Feature development guide

1. **Setting** — add a default, sanitization, REST schema, autoload choice, and
   Multisite scope. Licence data stays in `updatronix_pro_license`.
2. **Capability and nonce** — use `manage_updatronix_pro`; add the Super Admin
   check on Multisite; keep the native REST nonce.
3. **Data** — use the encrypted site option for the key and site options for
   feature settings. Never store a plaintext key.
4. **REST** — `updatronix-pro/v1`; register in `rest_api_init`; declare args,
   validation, permission, and error shapes. Redact the key from any upstream
   message.
5. **UI** — `LicensePanel` owns the licence states; use WPDS `EmptyState`,
   `InputControl`, `Button`, `Notice`, `Badge`, and `AlertDialog`. Keep the
   masked key as text.
6. **Gating** — Free-only: presentation; Pro without a licence: activation
   only; active: three Pro destinations; Multisite: Super Admin only. Reload
   after a licence change.
7. **Tests** — unit for policy and REST callbacks/routes; JS for pure helpers
   and reload semantics; integration for the real WordPress stack; E2E when the
   host is fixed. Mutation/counterfactual on changed policy lines when available.
8. **Docs** — feature brief, this contract, claims matrix, changelog, POT, and
   the audit protocol inventory.
