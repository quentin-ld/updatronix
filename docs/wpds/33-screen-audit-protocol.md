# Screen audit protocol

**Date:** 2026-10-26
**Scope:** The eleven Updatronix destinations, Free and Pro.
**Status:** Active. Every screen task starts here.

## Why this exists

The WPDS refactor changed the shell, the page composition, and the shared
patterns. The visual reviews in docs 27–32 confirm the rendered state, but they
do not prove that every control still works, that every claim is true, or that
the Pro edition gates hold. This protocol makes one screen audit repeatable
across fresh tabs and keeps the evidence in the repository.

## Execution model

- One fresh tab per screen, plus dedicated tabs for cross-cutting work.
- Start with `/architect` in the repository that owns the screen: Free
  (`wp-content/plugins/updatronix`) or Pro (`wp-content/plugins/updatronix-pro`).
- Attach the screen task file when the thread resumes. The task file is the
  contract; chat history is not.
- Use `/qa` for the hostile audit, `/reviewer` when the change touches REST,
  exports, options, capabilities, or interactive admin UI, `/security` when the
  surface touches REST, SQL, auth, uploads, export, or Multisite, and
  `/test-author` for every test that is added or upgraded.
- The protocol is identical in both plugins. Pro's skills are generated from
  Free's and differ only in names.

## Inputs

1. `docs/wpds/04-screen-contracts.md` — the behavioral contract.
2. This document — the audit protocol and the screen inventory.
3. The screen's feature brief in `docs/wpds/07-feature-briefs/`.
4. `docs/wpds/reference/screens/src/screens/` — the visual reference.
5. The prior screen review in `docs/wpds/14-…` to `32-…`.
6. `.agents/notes/2026-10-24-qa-wpds-conformance.md` and the Pro equivalent —
   unresolved findings that must be marked fixed or open.
7. `AGENTS.md` and `workflow.md` for the hard rules and the commands.

## Protocol

### 1. Contract

Read the screen contract, the feature brief, and the prior review. Write down
the expected route, header, primary action, save model, data source, states,
capability, and edition. Do not infer a contract from the current UI.

### 2. Inventory

Use `bin/harness graft ask` and `bin/harness graft grep` first, then open source
only at the returned `file:line`. Record:

- React page and child components.
- Hooks and data fetching.
- REST routes, permission callbacks, and schemas.
- Options, custom tables, transients, and autoload impact.
- WordPress hooks and filters the screen depends on.
- Capability, nonce, Multisite, and license gates.
- Existing tests that cover the screen.

### 3. Runtime probe

Open the live screen at 1280×900, 960, and 390. Exercise the states that the
contract names:

- loading, empty, populated, error, and retry;
- save success, save failure, and a network failure;
- license inactive, license active, and decryption error;
- a user without the capability, and a Multisite non-Super-Admin;
- keyboard traversal, focus return, screen-reader reading order, and RTL.

Record console errors, failed network requests, and visible layout regressions.
E2E is available again: Playwright Chromium and its system libraries are
installed in the DDEV container. The Free suite passes 2/2. Pro still has no
`tests/e2e/playwright.config.js`, so its admin journey stays manual until Phase
6 adds a suite.

### 4. Claims matrix

Every user-facing claim is evidence until proven otherwise. Build one row per
claim: label, description, badge, note, feature list item, tooltip, or empty
state. A claim is true only when the code path exists and the live screen
demonstrates it.

| Claim (where) | Code path | Runtime proof | Verdict |
|---|---|---|---|
| Example: “Flag abandoned plugins” | `flag_abandoned` is stored and rendered, no consumer | Toggle has no effect | False |

False claims are either implemented in this task or removed. A “coming soon”
label is not a substitute for a working control.

### 5. Audits

Run the audits before fixing anything.

- `/qa` — hostile behavior, edge cases, silent failures, bad feedback, side
  effects on the rest of WordPress.
- `/reviewer` — coherence, integration, accessibility, performance, docs.
- `/security` — REST permissions, nonces, capabilities, SQL, output escaping,
  file upload, export/download, Multisite, and license gates.

Findings are severity-ranked and file:line specific. No fixes in the audit
pass.

### 6. Fixes

Implement P0/P1/P2 findings with the architect skill. WordPress-native APIs
first: options, REST, capabilities, nonces, `WP_Upgrader`, `WP-Cron`, Site
Health. Use WPDS components for the front end; do not add a custom control when
a WPDS component exists. No legacy path, no compatibility shim, no version
bump.

Every fix must keep the screen's contract and the no-legacy rule. If a fix
changes a translatable string inside `__()`, `_e()`, `_n()`, `_x()`, or an
escaping wrapper, flag it before applying it: AGENTS.md requires owner
confirmation and a changelog entry.

### 7. Tests

Use `/test-author`. A test is accepted only when it can fail and the failure was
observed. Cover the branch that changed, the empty and invalid input, the
capability or nonce failure, and the license gate where relevant.

Choose the suite by the oracle:

- pure policy or formatter → unit;
- REST, options, custom table, capability, Multisite → integration;
- component state and interaction → JS;
- full admin journey → E2E when the host can run it.

Run `bin/harness mutation` or `bin/harness counterfactual` on changed lines
when the change is in scope. Record any mutation survivor as a finding.

### 8. Documentation

Update:

- `docs/wpds/04-screen-contracts.md` when the contract changed.
- `docs/wpds/10-task-index.md` when a task state changed.
- `docs/wpds/11-implementation-log.md` with what landed and how it was verified.
- This document's screen inventory when a path or test moves.
- The screen's state-of-play document, named
  `docs/wpds/<number>-<slug>-state-of-play.md`.

The state-of-play document contains the claims matrix, the internal mechanics,
the divergences, the fix list, the test inventory, and the feature development
guide.

### 9. Exit

A screen is done only when:

- every claim is true or removed;
- every P0/P1 finding is fixed or explicitly deferred with owner approval;
- the new tests fail without the fix and pass with it;
- the focused suite is green;
- the reviewer and security notes exist when the surface triggers them;
- the contract, index, log, and inventory are current;
- no version or `Stable tag` changed without explicit authorization.

## Screen inventory

| # | Destination | Slug | Edition | Primary React surface | PHP / REST / storage | Existing tests | Prior review |
|---|---|---|---|---|---|---|---|
| 1 | Update logs | `logs` | Free | `assets/src/js/components/activityLog/ActivityLogPanel.js`; `activityLog/logDeepLink.js`; `activityLog/exportFormats.js` | `inc/classes/class-updatronix-settings.php`; `class-updatronix-export*.php`; `{prefix}updatronix_logs` | `tests/Integration/LogsRestApiTest.php`; `tests/Unit/Export*`; `tests/js/logDeepLink.test.js`; `tests/js/exportFormats.test.js` | 14, 25, 27 |
| 2 | Auto updates | `auto-updates` | Free | `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js`; `autoUpdates/autoUpdatesDraft.js`; `hooks/useAutoUpdates.js` | `class-updatronix-autoupdates.php`; `class-updatronix-autoupdatedelay.php`; native `auto_update_*` options | `tests/Integration/AutoUpdatesRestApiTest.php`; `tests/Unit/AutoUpdateDelay*`; `AutomaticUpdateResultNotesTest.php`; `tests/js/autoUpdatesDraft.test.js` | 15, 28 |
| 3 | Schedule | `schedule` | Free | `assets/src/js/components/schedule/SchedulePanel.js`; `schedule/scheduleDraft.js` | `class-updatronix-cron.php`; `updatronix_settings.schedule` | `tests/Integration/CronUnifiedScheduleTest.php`; `tests/Unit/ScheduleSanitizersTest.php`; `tests/js/scheduleDraft.test.js` | 16 |
| 4 | Updatronix 3000 | `updatronix-3000` | Pro | `assets/src/js/components/Updatronix3000Panel.js`; `LicensePanel.js`; `hooks/useLicense.js`; `licenseKey.js`; `licenseReload.js` | `inc/license/*`; `updatronix_pro_license` | `tests/Unit/License*`; `ProConnectorTest.php`; `tests/js/licenseKey.test.js`; `tests/js/licenseReload.test.js` | 21, 43 |
| 5 | Update Shield | `update-shield` | Pro | `assets/src/js/components/UpdateShieldPage.js`; `UpdateShieldPanel.js` | `inc/update-shield/*`; `updatronix_pro_shield_settings` | `tests/Unit/SafeMode*`; `UpdateShieldRestTest.php` | 22 |
| 6 | Update Flow | `update-flow` | Pro | `assets/src/js/components/UpdateFlowPage.js` | `inc/update-flow/*`; `updatronix_pro_flow_chains` | `tests/Unit/UpdateFlowValidationTest.php` | 23 |
| 7 | White label | `white-label` | Pro | `assets/src/js/components/WhiteLabelPage.js`; `whiteLabelDraft.js`; `adminReload.js` | `inc/white-label/*`; `updatronix_pro_white_label` | `tests/Unit/WhiteLabelSanitizationTest.php`; `WhiteLabelPermissionTest.php`; `tests/js/whiteLabelDraft.test.js`; `tests/js/adminReload.test.js` | 24, 31, 44 |
| 8 | Health | `health` | Free | `assets/src/js/pages/HealthPage.js`; `pages/healthDiagnostics.js` | `class-updatronix-health.php`; `inc/core/site-health.php` | `tests/Integration/HealthRestApiTest.php`; `tests/js/healthDiagnostics.test.js` | 18 |
| 9 | Settings | `settings` | Free | `assets/src/js/components/SettingsPanel.js`; `pages/SettingsPage.js`; `hooks/usePluginSettings.js`; `components/settingsDraft.js` | `inc/settings/options.php`; `class-updatronix-settings.php`; `updatronix_settings` | `tests/Integration/RestSettingsAuthTest.php`; `SettingsPostSaveActionTest.php`; `SettingsUninstallOptInTest.php`; `tests/Unit/SettingsResolveSiteIdTest.php`; `tests/js/settingsDraft.test.js` | 17, 29 |
| 10 | Documentation | `documentation` | Free | `assets/src/js/pages/DocumentationPage.js`; `pages/staticLinks.js` | Static content and external links | `tests/js/staticLinks.test.js`; `tests/js/routes.test.js` (route) | 19 |
| 11 | Support | `support` | Free | `assets/src/js/pages/SupportPage.js`; `pages/staticLinks.js` | Static content and external links | `tests/js/staticLinks.test.js`; `tests/js/routes.test.js` (route) | 20 |

## Test inventory

### Free

| Suite | Files | Baseline 2026-10-26 |
|---|---|---|
| Unit | 14 | 213 tests, 415 assertions, pass |
| Integration | 9 | 73 tests, 197 assertions, 13 skipped, pass |
| JS | 8 | 63 tests, pass |
| E2E | 1 spec | 2 tests, pass (2026-10-26, after installing the container's Chromium dependencies) |

### Pro

| Suite | Files | Baseline 2026-10-26 |
|---|---|---|
| Unit | 20 | 243 tests, 778 assertions, pass |
| Integration | 0 | None |
| JS | 3 | 41 tests, pass |
| E2E | 0 | None |

The Pro gaps are part of Phase 6. A screen task adds the focused tests it needs
before the cross-cutting suite work.

## Claims matrix template

| Claim | Where it appears | Code path | Runtime proof | Verdict | Action |
|---|---|---|---|---|---|
| | | | | true / false / unverifiable | keep / implement / remove |

## Native vs custom template

| Surface | Current implementation | Native WordPress option | Decision |
|---|---|---|---|
| Setting, REST route, capability, nonce, storage, update execution, cron, email | | | native / justified custom |

A custom implementation is accepted only when the native API cannot express the
behavior. The reason goes in the state-of-play document.

## Feature development guide template

Every screen state-of-play document ends with a short guide for the next
feature:

1. **Setting** — default, sanitization, REST schema, autoload, Multisite scope.
2. **Capability and nonce** — which capability, which permission callback.
3. **Data** — option, custom table, transient, or native WordPress store.
4. **REST** — namespace, route, args, permission callback, error shape.
5. **UI** — page, hook, WPDS components, loading/empty/error states.
6. **Gating** — edition, license, capability, Multisite.
7. **Tests** — unit, integration, JS, E2E, mutation.
8. **Docs** — feature brief, screen contract, changelog, POT.

## Baseline 2026-10-26

Branch `major/wpds`, both repositories. No version bump.

- Free `bin/harness doctor`: host backend, PHP 8.4.24, Composer 2.10.3,
  WP-CLI 2.12.0, Node 24.20.0, npm 11.19.0, graft 0.21.1 with an index.
- Free unit: 213 tests pass.
- Free integration: 73 tests, 197 assertions, 13 skipped, pass.
- Free JS: 63 tests pass.
- Free build: pass; entry 1.91 MiB with the existing size warnings.
- Free E2E: 2 tests, pass after installing Playwright Chromium and its system
  libraries in the DDEV container.
- Pro unit: 243 tests, 778 assertions, pass.
- Pro JS: 41 tests pass (license key shape, reload notices, white label
  draft/state/logo/preview helpers).
- Pro build: pass; entry 25.6 KiB, four panel chunks.
- Pro has a route-registration test and a connector-gating test; integration
  and E2E are still absent.

## Known limits

- E2E now runs for Free. Pro has no `tests/e2e/playwright.config.js`, so its
  admin journey is still a manual pass; Phase 6 owns the Pro E2E suite.
- Mutation testing and a full screen-reader pass are not available in this
  environment.
- The Free build carries the webpack runtime in the entry chunk; extracting it
  needs an enqueue change and is recorded, not changed here.
- Pro's four panels are still registered by a single bundle. Phase 1.4 will
  gate registration; per-panel code splitting is a later performance decision.
- Dev mode is domain-locked to local hosts. It was verified with a temporary
  local-host fixture on 2026-10-26, not on the normal `*.ddev.site` host.
- Multisite is unit-tested only; no live network was available for the screen
  pass.
- The white label email preview and application are name-only for the Safe mode
  notification; Free core update emails are not retemplated.
- The Free export dialog's XML branch renders CSV. It is a Free update-log
  screen defect, recorded in `43-updatronix-3000-state-of-play.md` and deferred
  to the Free audit/Phase 6.
