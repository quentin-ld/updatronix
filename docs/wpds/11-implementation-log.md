# Implementation log

## 2026-10-24 · F0 foundation and F1 shell skeleton

Branch `major/wpds`, Free repository. Pro is untouched.

### What landed

| Area | Change |
|---|---|
| Packages | Aligned to `@wordpress/ui` 0.25.0, `@wordpress/theme` 3.0.0, `@wordpress/components` 43.0.0, `@wordpress/dataviews` 21.0.0, `@wordpress/admin-ui` 3.0.0, `@wordpress/icons` 19.0.0, `@wordpress/element` 10.0.0. React stays at 18.3.1. |
| Build | `webpack.config.js` bundles the WPDS closure and keeps React, ReactDOM, `api-fetch`, `data`, `i18n`, `notices`, `dom-ready`, `hooks`, `url`, and `date` on the core handles. |
| Runtime | `assets/src/js/shell/wpds.js` exposes the curated runtime on `window.updatronix.wpds`. It exposes `element`, `ui`, `theme`, `adminUi`, `components`, and `icons`; dataviews stays route-level. |
| Theme | `ThemeProvider` with `cornerRadius="subtle"` and `isRoot`. The primary seed follows the admin color scheme; the content background stays light (`#fcfcfc`). |
| Shell | Dark 264px rail, four groups, eleven destinations, Pro badges, version footer, skip link, `aria-current`, route focus, `pushState` / `popstate`, and a mobile drawer below 900px. |
| Pages | Documentation and Support are implemented. Health is an explicit empty state until F4. Pro destinations mount the legacy Pro panel when present and show a transitional empty state otherwise. |
| Legacy removal | The gradient header, the cross-links row, the version line, and the duplicate panel introductions are gone. The `#updatronix-settings` mount point is unchanged. |
| Tests | `tests/js/routes.test.js` covers the destination registry, PHP tab merging, URL building, and deep-link fallback. 12 tests. |

### Compatibility findings

- WordPress 7.1.3 ships `@wordpress/element` 8.3.0, `@wordpress/theme` 1.0.0, `@wordpress/admin-ui` 2.6.0, `@wordpress/i18n` 6.24.0, and `@wordpress/compose` 8.4.0. The guide's set is newer than the runtime.
- The pinned set requires element 10, so element 10 is bundled. React 18.3.1 is shared and external.
- `@wordpress/keycodes` 5 exports `withIgnoreIMEEvents`; core 7.1's `wp-keycodes` does not. Bundling keycodes fixed an InputControl crash.
- `@wordpress/admin-ui` breadcrumbs require a router context. The shell uses a plain breadcrumb nav; `Breadcrumbs` is not called.
- The built manifest no longer declares `wp-theme`. The new floor is not yet proven; see `D-22`. The declared `Requires at least: 6.2` is unchanged until a 6.2 smoke test runs.

### Verification

| Check | Result |
|---|---|
| `npm run lint` | Pass. |
| `npm run lint:css` | Pass. |
| `npm run build` | Pass. Entry 330 KiB; the lazy page chunk is 2.04 MiB. |
| `bin/harness test:js` | 12 tests pass. |
| `composer run verify:php` | PHPStan clean; 213 unit tests pass. WPCS reports pre-existing warnings in export, notifications, and settings classes, not in the changed files. |
| Manual browser pass | Desktop rail, all eleven destinations, deep links, unknown-slug fallback, Back navigation, mobile drawer, Pro legacy mount, no console errors. |

The manual pass used a local administrator and the DDEV site `https://updatronix.ddev.site`.

### Known gaps

1. The four existing panels still use legacy markup and styles; F3 migrates them.
2. Health is a placeholder; F4 implements it.
3. Pro still bundles its own WPDS copy and mounts through the legacy DOM renderer. P0 and P1 move it to the shared runtime and the component contract.
4. The page chunk is 2.04 MiB. F6-03 owns the budget and code splitting.
5. The e2e config still points at `harness.ddev.site`, which returns 404. Update `.config/wp-tests.env` before relying on `bin/harness e2e`.
6. WordPress 6.2 compatibility is plausible but unproven. Do not change the header until a 6.2 smoke test runs.

### Next

1. `F1-05` formalizes the Pro upgrade state when Pro is absent.
2. `F2-01` to `F2-04` build the shared page header, surface, save model, and dialogs.
3. `F3-01` migrates the update log to the target DataViews surface.
4. `P0-01` to `P0-03` move Pro onto the shared runtime and the component registry.

## 2026-10-24 · F2 patterns and the first two screen migrations

### What landed

| Area | Change |
|---|---|
| Section | `assets/src/js/shell/Section.js` wraps the WPDS card with a heading and description. |
| Save model | `assets/src/js/shell/SaveBar.js` adds a sticky bar, dirty state, `Ctrl+S` / `Cmd+S`, a `beforeunload` guard, and Discard. |
| Save override | `usePluginSettings.saveSettings()` accepts an optional settings override, so a page can save its local draft without mutating the parent state on every keystroke. |
| Schedule | Migrated to two WPDS sections, `SelectControl`, `InputControl`, `SwitchControl`, `Notice`, and the new save bar. Dirty state covers the schedule subtree only. |
| Settings | Migrated to Logging and Email notifications sections with `SwitchControl`, `InputControl`, `Fieldset`, `CheckboxControl`, `Notice`, and the save bar. Clearing logs uses `AlertDialog` with `intent="irreversible"`. The Data card is deferred until the backend has an uninstall option. |
| Dialogs | The destructive clear-logs action uses `AlertDialog` and names the object; Cancel is focused on open. |

### Verification

| Check | Result |
|---|---|
| `npm run lint` | Pass. |
| `npm run lint:css` | Pass. |
| `npm run build` | Pass. Entry 333 KiB; lazy page chunk 2.01 MiB. |
| `npm test` | 12 tests pass. |
| Manual browser pass | Schedule: change hour, dirty state, save, next-run update, revert. Settings: dirty state, `Ctrl+S`, discard, conditional email fields, `AlertDialog` open and cancel. No console errors. |

### Known gaps after this wave

1. The Settings Data card is not shipped: the backend has no uninstall-deletion option. The route description now says “Logging, retention, and email routing.”
2. Auto updates still uses the legacy panel.
3. The page chunk remains 2.01 MiB; F6-03 owns the budget.

### Next

1. `F3-01` and `F3-02`: migrate the update log to DataViews and its details, export, and delete dialogs.
2. `F3-03`: migrate auto updates to cards and one manual save.
3. `F5-01`: remove the legacy shell styles and the now-unused `Tabs` component.

## 2026-10-24 · F3-01 and F3-02 update log table and dialogs

### What landed

| Area | Change |
|---|---|
| Table | One DataViews table with Item, Type, Version, Trigger, Status, Date, and Actions. The version cell is compact (`v7.1.2 → v7.1.3`), and the footer uses the server total. |
| Actions | Row actions open WPDS dialogs: View details opens a `Dialog`, Delete opens an `AlertDialog` with `intent="irreversible"`. |
| Status | WPDS `Badge` with the `stable`, `medium`, `high`, `informational`, and `none` intents. |
| Details | `LogDetailsContent` now uses the WPDS `Notice` and `Text` components; the summary, process details, and advanced trace are unchanged. |
| States | Error, logging-disabled, loading, empty, and delete-failure states are explicit. |

### Remaining legacy surface

The export modal still uses the legacy `Modal` and core controls. It works and keeps its chunked export behavior, but it is the next F3-02 slice.

### Verification

| Check | Result |
|---|---|
| `npm run lint` | Pass. |
| `npm run lint:css` | Pass. |
| `npm run build` | Pass. Entry 335 KiB; lazy page chunk 2.01 MiB. |
| `npm test` | 12 tests pass. |
| Manual browser pass | Seven rows render with the correct columns; details dialog, delete alert dialog open and cancel, export modal opens and closes, focus returns to the export button, no console errors. |

### Next

1. Migrate the export modal to the WPDS `Dialog` and controls.
2. `F3-03`: migrate auto updates to cards and one manual save.
3. `F5-01`: remove the legacy shell styles and the unused log card layout helpers.

## 2026-10-24 · Export modal and design-conformance audit

### What landed

- The export modal now uses the WPDS `Dialog`, `SwitchControl`,
  `CheckboxControl`, `Notice`, and `Button`, with a `<pre>` output preview.
  `F3-02` is complete.
- The reference comparison found that the page header, Schedule, Settings, and
  Update log diverge from the Open Design screens. The audit is
  [`12-design-conformance-audit.md`](12-design-conformance-audit.md).
- A mandatory design-conformance gate was added to
  [`09-acceptance.md`](09-acceptance.md), [`10-task-index.md`](10-task-index.md),
  and the README.

### What is not conformant

| Screen | Main deviation |
|---|---|
| Page header | No meta badges or per-screen actions. |
| Schedule | Missing the hold field, Next-run section, hold table, header meta, and actions; hour/minute inputs replace the preferred-time select. |
| Settings | Missing the Data card, chip recipients, always-on recovery row, and notes; has extra controls. |
| Update log | Table instead of the reference activity feed with day grouping and a section header. |

### Next

1. `R1`: add the page-header meta/action slot and apply it to the three screens.
2. `R2`: rewrite Schedule to the reference composition.
3. `R3`: rewrite Settings, including the uninstall option.
4. `R4`: restore the Update log activity layout and preview.

## 2026-10-24 · Schedule conformance (R2)

### What landed

- Schedule now matches the reference composition: `Update checks` and
  `Next run` in a two-column split, then `What the hold does` with the
  reference table and closing note.
- Frequency is a select with the reference labels plus Weekly and WordPress
  default for backward compatibility. Preferred time is a select, and the
  saved time is preserved when it is not one of the four reference times.
- `Hold new releases for (days)` is a single 0–30 field. It maps to
  `delay_updates.enabled = hold > 0` and `delay_value = hold`.
- The page header has the `Next check` meta badge plus `Run a check now` and
  `Save changes` actions.
- `POST /updatronix/v1/run-check` refreshes core, plugin, theme, and
  translation update data. It requires the manage capability.

### Verification

- `npm run lint`, `npm run lint:css`, `npm run build`, and `npm test`
  (12/12) pass.
- `bin/harness composer run verify:php`: PHPStan clean; PHPUnit 213 tests,
  415 assertions.
- Browser pass: the three sections render, `Run a check now` returns the
  success notice, changing the hold to 2 enables the header Save, and the
  saved option is `{"enabled":true,"delay_value":2}`.

### Next

1. `R3`: rewrite Settings with chip recipients, the five notification rows,
   the Data card, the notes, and the uninstall option.
2. `R4`: restore the Update log activity layout, day grouping, and preview.

## 2026-10-24 · Settings conformance (R3)

### What landed

- Settings now has the reference composition: Logging and Email notifications
  in a split, the recovery warning, Data, and the closing notes.
- Recipients use `SearchableChipSelectControl`. The comma-separated
  `notify_emails` value is parsed into chips and serialized back on save.
- The notification rows are Core updates, Plugin and theme updates, Debug
  summary, Technical alerts, and the always-on Recovery mode row.
- The Data card persists `delete_data_on_uninstall` (off by default).
  `Updatronix_Uninstall::run()` now returns early when the opt-in is off.
- Removed the old `Manage update notifications` switch and the `Clear all logs`
  action, which the reference does not have. The REST payload whitelist now
  includes `delete_data_on_uninstall`.

### Verification

- `npm run lint`, `npm run lint:css`, `npm run build`, and `npm test` (12/12)
  pass.
- `bin/harness composer run verify:php`: PHPStan clean; PHPUnit 213 tests,
  415 assertions.
- Browser pass: adding `qa@example.com` creates a chip and enables Save; the
  saved option contains the address. Toggling the Data switch and saving stores
  `"delete_data_on_uninstall":true`; toggling it back stores `false`.

### Next

1. `R4`: restore the Update log activity layout, day grouping, Section wrapper,
   and preview aside.
2. `R5`: Auto updates, Documentation/Support meta, Health, and the Pro screens.

## 2026-10-24 · Update log conformance (R4)

### What landed

- The update log now uses the DataViews `activity` layout grouped by day,
  with `item`, `summary`, and `typeIcon` as the title, description, and media
  fields, and `status` and `time` as the visible fields.
- The feed is wrapped in the `Recent updates` Section with the Export action.
  The page header keeps the `Retention N days` meta.
- Selecting a row opens the details preview aside on screens wider than
  1200px. Below that breakpoint the existing `Dialog` remains.
- Server pagination and every filter remain in place. The feed note explains
  the retention cleanup.
- Added the reference activity spacing for the type column and timeline.

### Verification

- `npm run lint`, `npm run lint:css`, `npm run build`, and `npm test` (12/12)
  pass.
- Browser pass: the feed groups by day, rows show type/name, version,
  trigger · actor, status, time, and actions; the preview aside opens and
  closes; the Export dialog still opens.

### Next

1. `R5`: Auto updates to reference cards and one manual save.
2. `R5`: Documentation and Support page-header meta.
3. `F4-01` and the Pro screens remain separate tasks.

## 2026-10-24 · Auto updates conformance (R5)

### What landed

- Auto updates now uses the reference composition: Core update mode radio,
  Translations switch, Themes list, Plugins list, and the closing note.
- Each theme or plugin row shows the logo or a fallback icon, the active badge,
  the version, the description, the WordPress.org link, and one switch.
  Unavailable and locked items keep their explicit states.
- The page has one draft and one Save action. `saveAll` compares the draft with
  the saved snapshot and sends only the changed values through the existing
  native REST mutations. A partial failure keeps the draft intact and shows an
  error, so retry resends the changed values.
- The route label is now `Auto updates`, matching the reference.

### Verification

- `npm run lint`, `npm run lint:css`, `npm run build`, and `npm test` (12/12)
  pass.
- Browser pass: the page renders the four sections, the core mode changed to
  Minor releases only, one Save persisted it and showed the success notice,
  and the mode was restored to All releases afterwards.

### Next

1. `R5`: Documentation and Support page-header meta.
2. `F4-01`: Health.
3. `P2`–`P5`: the Pro screens.

## 2026-10-24 · Documentation and Support conformance (R5)

### What landed

- Documentation has its own `PageHeader` with the `3 guides` meta, the
  three-column card grid, the `Read guide` footer, the Pro icon treatment, and
  the closing note that links to Support.
- Support has its own `PageHeader`, the numbered `Before you post` section, and
  the separate WordPress.org forum card with the CTA, documentation link, and
  privacy note.
- The shell header is hidden for both destinations so each page owns its header.

### Verification

- `npm run lint`, `npm run lint:css`, `npm run build`, and `npm test` (12/12)
  pass.
- Browser pass: Documentation shows the header meta, three cards, and the
  Support link; Support shows the numbered steps and the forum card.

### Next

1. `F4-01`: Health.
2. `P2`–`P5`: the Pro screens.

## 2026-10-24 · Health (F4-01)

### What landed

- `Updatronix_Health` builds a five-minute cached snapshot from six checks:
  WordPress version, PHP version, disk space, WP-Cron, update locks, and
  automatic retries.
- Open issues are derived from failed log rows in the last 30 days, grouped by
  item with an attempt count. Recently resolved is derived from a success that
  follows a failure. No new table was added.
- Diagnostics returns an environment summary without filesystem paths.
- The retry flow validates the log entry, capability, a per-item lock, and a
  three-per-hour rate limit, then queues a WP-Cron event. Plugin and theme
  retries run through the native upgrader and record the result. Core and
  translation retries are rejected with a link to the WordPress Updates screen.
- REST routes: `GET /health`, `POST /health/check`, `POST /health/retry`, and
  `GET /health/diagnostics`.
- `HealthPage` implements the reference composition: readiness summary, six
  checks, open-issue rows, details and retry dialogs, resolved table, and the
  diagnostics dialog with copy and download.

### Verification

- PHPStan clean; PHPUnit 213 tests, 415 assertions.
- `npm run lint`, `npm run lint:css`, `npm run build`, and `npm test`
  (12/12) pass.
- Browser pass: the all-clear state, six checks, Run check, and diagnostics all
  work. A temporary failed log produced the attention summary, open issue,
  details dialog, and retry dialog; deleting the row and running the check
  returned the page to zero open issues.

### Next

1. `P0`: the Pro shared runtime and package alignment.
2. `P2`–`P5`: the Pro screens.

## 2026-10-24 · Pro shared runtime (P0)

### What landed

- Pro's package pins match Free for `@wordpress/ui`, `theme`, `admin-ui`,
  `components`, `dataviews`, and `icons`. `element` is 10.0.0 and React is
  18.3.1.
- Pro's `webpack.config.js` maps WPDS and `@wordpress/element` to
  `window.updatronix.wpds`, maps those shared packages to the
  `updatronix-scripts` handle, bundles `dataviews`/`compose`/`private-apis`/
  `primitives` only if imported, and leaves the other WordPress handles alone.
- Free imports the runtime from its entry so the global exists before Pro
  executes. The runtime also exposes the legacy components Pro still imports
  during the migration.
- Pro registers `Updatronix3000Panel` as a React component
  (`updatronixPanel = 'component'`) and dispatches
  `updatronix:pro-panel-registered`. Free listens and re-renders the active Pro
  destination.
- Pro's stylesheet depends on `updatronix-style`, not `wp-components`.
- `WpdsPackageAlignmentTest` compares the six shared pins across both plugins
  and skips when the sibling checkout is absent.

### Verification

- Pro build: 11.1 KiB `index.js`; manifest deps are `react-jsx-runtime`,
  `updatronix-scripts`, `wp-api-fetch`, `wp-data`, `wp-i18n`, and `wp-notices`.
  No `wp-ui`, `wp-theme`, `wp-icons`, `wp-components`, or `wp-element`.
- Pro PHPStan clean; 189 PHPUnit tests and 565 assertions.
- Pro ESLint and Stylelint pass.
- Browser pass: the Updatronix 3000 tab renders the Pro license activation
  form inside Free's shell and providers; the console has no new errors.
- Free's entry grows to about 1.7 MiB because the shared runtime is now in the
  initial chunk. `F6-03` owns the final budget and an extracted runtime entry
  is the likely follow-up.

### Next

1. `P1`: rail and destination contracts for the Pro screens.
2. `P2`–`P5`: license, Shield, Flow, White label.

## 2026-10-24 · Pro license page (P2)

### What landed

- The Updatronix 3000 destination is now a dedicated license page. It renders
  the inactive empty state, the active state with the masked key, and the six
  Pro features.
- Activation uses the existing encrypted storage and REST service. A malformed
  key is rejected inline; server and network errors appear inline with a retry.
  Deactivation is confirmed before the license is cleared.
- Development mode and a decryption failure have distinct states. The status
  endpoint now returns `dev_mode` and `decryption_error` flags.
- Update Shield no longer shares the license destination. It returns as its own
  destination in P3.
- Every Pro panel is wrapped in the Free error boundary. A render failure names
  the panel and offers a retry without taking down the rail.
- The `updatronix-3000` destination description now says “Manage your
  Updatronix 3000 license and Pro tools.” so it stays accurate when active.

### Verification

- Pro ESLint, Stylelint, and build pass; PHPStan is clean; 189 PHPUnit tests and
  565 assertions pass.
- Free ESLint and build pass; 12/12 JS tests pass.
- Browser pass: the inactive license page renders inside the Free shell, the six
  features are visible, and a malformed key produces the inline format error.
  The active/deactivation path was not exercised without a real license key.

### Next

1. `P1-03`: multisite capability routing.
2. `P3`: Update Shield.

## 2026-10-24 · Pro capability routing (P1-03)

### What landed

- Free localizes `canManagePro` from the new `updatronix_can_manage_pro`
  filter. Pro answers it with `current_user_can( UPDATRONIX_PRO_CAP_MANAGE )`
  and, on Multisite, requires a Super Admin.
- Free's destination registry gains `filterDestinationsForUser()`. On a
  Pro-active install without the capability, the four Pro destinations are
  removed from the rail. On Free-only installs they remain visible with the
  upgrade path.
- The shell computes the visible destinations once per page load from the
  server flags; the capability is not inferred in the browser.

### Verification

- Free ESLint and build pass; 15/15 JS tests, including the three capability
  cases.
- Pro PHPStan is clean; 189 PHPUnit tests and 565 assertions pass.
- Browser pass: the administrator receives `canManagePro: "1"` and sees all
  four Pro destinations.

### Next

1. `P3`: Update Shield.

## 2026-10-24 · Update Shield (P3)

### What landed

- The shield option gains `incompatible_behavior`, `re_enable`,
  `notify_email`, `queue_enabled`, and `flag_abandoned`, with defaults,
  partial-merge support, and sanitizers. Existing installs migrate
  `notify_email` from `notify_on_block`, and the two keys stay in sync.
- The dedicated Update Shield page renders Protection, Update queue, and
  Recently blocked from the reference composition. It has one manual save,
  a dirty state, `Ctrl+S`, the recheck action, and the compatibility note.
- `GET /shield/blocked` reads the existing Free log table, keeps the
  safe-mode action types from the last 30 days, and returns item, type,
  version, reason, date, and a link to the matching log entry.
- All shield REST routes now use `check_shield_permission()`: capability,
  Super Admin on Multisite, and an active license or dev mode. The UI mirrors
  that gate.
- Safe Mode now reads `notify_email`, falling back to the legacy
  `notify_on_block` value.

### Verification

- Pro ESLint, Stylelint, and build pass; PHPStan is clean; 191 PHPUnit tests
  and 582 assertions pass.
- Browser pass with a temporary local license: the form rendered, the queue
  toggle produced the dirty state, Save persisted `queue_enabled: false`,
  and a second save restored `true`. The Recent updates table showed its
  empty state. The temporary license option was removed afterwards.
- WPCS reports only the pre-existing unused-parameter warnings in Safe Mode
  and the license REST class.

### Next

1. `P4`: Update Flow.

## 2026-10-24 · Update Flow chains (P4-01 to P4-03)

### What landed

- `Updatronix_Update_Flow` stores ordered chains in a network option and
  exposes `GET/POST /flow/chains` plus `GET /flow/items`. The item list is the
  installed core, plugins, and themes.
- Validation rejects an empty name, fewer than two steps, duplicate steps, and
  cycles across all chains. The REST response carries a stable error code and
  the offending field or loop.
- The Update Flow page follows the reference composition: chain list, editor
  dialog, keyboard reorder controls, delete confirmation that names the chain,
  the dependency check, and one manual save with `Ctrl+S`.
- The page and REST routes are gated by the Pro capability, Super Admin on
  Multisite, and an active license or dev mode.
- Unit tests cover normalization, name validation, the two-step floor,
  duplicate steps, cross-chain cycles, and route registration.

### Verification

- Pro ESLint, Stylelint, and build pass; PHPStan is clean; 197 PHPUnit tests
  and 599 assertions pass.
- Browser pass with a temporary local license: the empty state rendered, a
  “Payments first” chain was created with a plugin and theme step, saved, and
  read back from `updatronix_pro_flow_chains`. The temporary license and chain
  option were removed afterwards.
- `P4-04` execution integration is not implemented. The chains are stored and
  validated, but no update run consumes them yet, so the P4 gate remains open.

### Next

1. `P4-04`: run chains before the regular update check, stop on failure, and
   record each step in the log.
2. `P5`: White label.

## 2026-10-24 · White label (P5)

### What landed

- Pro stores the network-wide white label option and exposes
  `GET/POST /white-label`. Text, hex colors, the attachment ID, and the two
  switches are sanitized server-side; the route is gated by the Pro
  capability, Super Admin on Multisite, and an active license or dev mode.
- The White label page follows the reference composition: Brand, Colors,
  Replace, and Preview, with logo upload/removal, brand and menu colors,
  replacement and email switches, the admin/email previews, reset
  confirmation, and one manual save with `Ctrl+S`.
- Free localizes the brand from `updatronix_admin_brand` and uses it for the
  admin menu label, the rail wordmark, and the page breadcrumb. Pro filters
  the menu label and the plugin-generated email brand name. Native WordPress
  update emails are not re-templated.

### Verification

- Pro ESLint, Stylelint, and build pass; PHPStan is clean; 200 PHPUnit tests
  and 612 assertions pass.
- Free ESLint, build, and 213 PHPUnit tests pass; PHPStan is clean.
- Browser pass with a temporary local license: the page rendered, `Acme
  Studio` and `Site updates` were saved and read back, the admin menu label
  changed to `Site updates`, and the rail wordmark changed to `Acme Studio`.
  The temporary license and white label option were removed afterwards.
- The logo upload and reset confirmation are code-reviewed but were not
  exercised in the browser. The rail brand and menu label were verified; the
  breadcrumb uses the same setting.

### Next

1. `P6`: roadmap gates.
2. Free `F5`/`F6`.
3. `P4-04`: flow execution.

## 2026-10-24 · Roadmap gates (P6)

### What landed

- `D-14` is confirmed as the final decision for all three roadmap features:
  Queue list lives inside Update Shield, Advanced exports in the Update logs
  export dialog, and Development tools under Documentation.
- The three briefs now say “Deferred under `D-14`” with the reason and the
  missing backend contract. No separate rail screens or code were added.

### Verification

- The documentation check passes; no code or tests changed.
- `G-P6` is closed as three explicit deferrals rather than silently dropped
  features.

### Next

1. Free `F5`/`F6`.
2. `P4-04`: flow execution.

## 2026-10-24 · Update Flow execution (P4-04)

### What landed

- Free fires `updatronix_before_update_check` before the regular update check.
- Pro's `Updatronix_Update_Flow_Runner` acquires a five-minute lock, walks the
  enabled chains top to bottom, and updates plugin, theme, or core items only
  when the update transient offers one. It stops at the first failure and
  writes each successful or failed step to the update log with the chain name.
- The runner is gated by the active license and uses WordPress's own
  `Plugin_Upgrader`, `Theme_Upgrader`, and `Core_Upgrader` with the automatic
  skin.

### Verification

- Pro PHPStan is clean; 200 PHPUnit tests and 612 assertions pass; Free PHPUnit
  tests pass.
- A live update run was not exercised: the test site has no pending update
  offers, and forcing one would modify installed code. The runner and its
  failure/logging paths are code-reviewed and PHPStan-checked.

### Next

1. Free `F5`/`F6`.
2. Release candidate authorization.

## 2026-10-24 · Legacy cleanup and release gate (F5/F6)

### What landed

- Removed the unused legacy `Tabs` component and its stylesheet, and removed
  the legacy shell import.
- Replaced the remaining raw colors in the active Free styles with WPDS tokens.
  Added `npm run lint:tokens` and wired it into `test:all`; the rail palette and
  the legacy component-variable bridge are documented exceptions.
- Regenerated `languages/updatronix.pot` without translator-comment warnings.
- Added the Unreleased changelog entry and the admin-interface README note.
- Recorded the accessibility, Multisite, bundle, and full-gate limits in
  `.agents/notes/2026-10-24-wpds-release-gate.md`.

### Verification

- Free ESLint, Stylelint, token lint, 15/15 JS tests, and build pass.
- Free initial entry is 1.7 MB; the page chunks are 476 KB and 98 KB.
  DataViews remains in the page chunk, not the initial shell chunk. The webpack
  runtime remains in the entry; extracting it needs an enqueue change and is
  recorded, not changed.
- Free PHPStan is clean and 213 PHPUnit tests pass; Pro PHPStan is clean and
  200 PHPUnit tests pass.
- E2E was attempted after pointing the smoke at `tools.php`; Chromium cannot
  launch in this environment because its runtime cannot load `libnspr4.so`.
  The manual browser passes cover the same page.
- Mutation testing, a full screen-reader pass, and a manual Network Admin pass
  were not available in this environment.

### Next

1. `F6-05`: version and release-candidate authorization.

## 2026-10-25 · Spacing sweep and interaction closure

### What landed

- Free shared spacing: `926bd1e` (page header, section, row, mobile),
  `8400b92` (Documentation card, Support rows/actions, surface note), and
  `58f44c3` (shared table cells).
- Pro spacing: `c454679` (24px header-to-body gap, License feature grid, Flow
  chain/step chrome, White label two-column form, Shield table cells).
- Interaction closure: `e475364` (Free `?log=<id>` deep link) and `4bc59f9`
  (Pro dirty state from saved snapshots plus inline Flow rename).
- The 960/390 pass found no horizontal overflow. License features and White
  label form/previews collapse correctly; Free Settings split collapses at 390.
- The temporary Pro license and Flow chain used for the checks were removed.
- Legacy Pro DOM panel support is deliberately kept for one release; Pro 0.8
  uses the React registry.

### Verification

- Free ESLint, Stylelint, token lint, build, 15/15 JS tests, PHPStan, and 213
  PHPUnit tests pass.
- Pro ESLint, Stylelint, build, PHPStan, and 200 PHPUnit tests pass.
- Browser checks: Shield Save disabled → enabled → disabled after a toggle and
  revert; Flow rename changed the name and enabled Save, then renaming back
  disabled Save; the Free log deep link opened the details preview and removed
  the query parameter.
- E2E remains blocked by `libnspr4.so`; mutation testing, a full screen-reader
  pass, a manual Network Admin pass, and a live Flow update run were not
  available.

### Next

1. `F6-05` / `P7-04`: release-candidate authorization.

## 2026-10-26 · Screen audit baseline

### What landed

- Added `docs/wpds/33-screen-audit-protocol.md`: the shared protocol for one
  screen per fresh tab, the eleven-screen inventory, the test inventory, the
  claims matrix, the native-vs-custom table, and the feature development guide.
- Corrected `tests/Integration/LogsRestApiTest.php` to expect the bare version
  span `1.0 → 1.1`. The code and the update-log review specify bare versions;
  the test still expected the removed `v` prefix.

### Verification

| Check | Result |
|---|---|
| Free `bin/harness test` | 213 tests, 415 assertions, pass. |
| Free `bin/harness integration` | 65 tests, 172 assertions, 13 skipped, pass. |
| Free `bin/harness test:js` | 15 tests, pass. |
| Free `bin/harness build` | Pass. Entry 1.91 MiB; existing size warnings unchanged. |
| Pro `bin/harness test` | 200 tests, 612 assertions, pass. |
| Pro `bin/harness build` | Pass. Entry 74 KiB. |

### Known gaps

1. E2E remains blocked by the host's missing `libnspr4.so`.
2. Pro has no JS, integration, or E2E suite yet; Phase 6 owns the cross-cutting
   suite work.
3. Mutation testing and a full screen-reader pass were not available in this
   environment.
4. The Free webpack runtime remains in the entry chunk; extracting it needs an
   enqueue change and is not part of this baseline.

### Next

1. `1.2`: fix the license key format, placeholder, mask, and tests.
2. `1.3` and `1.4`: implement the edition and license gates in Free and Pro.

## 2026-10-26 · Phase 1.2 and 1.3

### What landed

- **1.2 — License format.** Replaced the 4×4 client pattern with
  `UPD-PRO-XXXXXXXX-XXXXXXXX-XXXXXXXX`, stopped uppercasing the case-sensitive
  payload, updated the placeholder, error copy, and mask, added server-side key
  validation and real-format masking, and added 10 JS tests plus 4 PHP tests.
- **1.3 — Free gating.** The rail now keeps only Updatronix 3000 on a Free-only
  install, keeps only the activation screen when Pro is installed without an
  active license, and hides Shield, Flow, and White Label until Pro, the
  capability, and the license are all active. The Free-only Updatronix 3000
  destination is a presentation with no license form and no self-link.
- The localized boot payload now exposes `licenseActive` and `exportFormats`.
  Free defaults to `txt`; the export dialog hides the format selector when only
  one format is available, and the export REST route rejects JSON while only
  plain text is enabled.

### Verification

| Check | Result |
|---|---|
| Pro `bin/harness test:js` | 10 tests, pass. |
| Pro `bin/harness test` | 204 tests, 621 assertions, pass. |
| Free `bin/harness test:js` | 16 tests, pass. |
| Free `bin/harness test` | 213 tests, 415 assertions, pass. |
| Free `bin/harness integration` | 66 tests, 173 assertions, 13 skipped, pass. |
| Free `bin/harness lint:js` | Pass. |
| Free and Pro `bin/harness build` | Pass; Free entry 1.91 MiB with the existing warnings, Pro entry 74 KiB. |
| Focused WPCS | 0 errors; only a pre-existing unused `$request` warning in the Pro license REST class. |

### Known gaps

1. E2E remains blocked by the host's missing `libnspr4.so`.
2. Pro still registers Safe Mode, White Label, and the Pro panels at bootstrap
   regardless of the license; 1.4 closes that server-side gate.
3. The Free-only Updatronix 3000 presentation is intentionally minimal; Phase 3
   owns the license screen polish and Phase 5 owns the full marketing surface.
4. No version bump.

### Next

1. `1.4`: gate Pro bootstrap, hooks, REST, panels, and assets on the license.
2. `1.5`: write the roadmap matrix.

## 2026-10-26 · Phase 1.4

### What landed

- Pro now exposes `updatronix_license_active` and `updatronix_export_formats`
  to Free. The license screen, license REST, connector, enqueue, and updater
  always load so activation stays possible.
- Shield, Flow, the Flow runner, and White label register only when the license
  is active. Their REST routes and brand filters therefore disappear with the
  license, while their permission callbacks keep the existing defense in depth.
- Pro's admin entry now imports each panel with a dynamic import. An inactive
  license loads only the activation panel; the Shield, Flow, and White label
  chunks are not requested.
- Removed the legacy Pro DOM mount from Free's `ProDestination`; the React
  registry is the only Pro panel contract.

### Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 207 tests, 632 assertions, pass. `ProGatingTest` covers inactive and active bootstrap, plus export formats. |
| Pro `bin/harness lint:js` | Pass. |
| Pro `bin/harness build` | Pass. Entry 4.12 KiB plus four panel chunks; the inactive path loads only the activation chunk. |
| Pro focused WPCS | 0 errors, 0 warnings. |
| Free `bin/harness lint:js` | Pass after removing the legacy mount. |
| Free `bin/harness build` | Pass; entry 1.91 MiB with the existing warnings. |

### Known gaps

1. E2E remains blocked by the host's missing `libnspr4.so`.
2. A manual browser pass with an inactive license is still needed to confirm the
   activation-only rail and the absence of Shield/Flow/White label chunks.
3. The Free-only Updatronix 3000 presentation remains intentionally minimal.
4. No version bump.

### Next

1. `1.5`: write the roadmap matrix.

## 2026-10-26 · Phase 1.5

### What landed

- Added `docs/wpds/34-roadmap-matrix.md` with the frozen Free/Pro split, the
  current code state, the dependency order, the briefs that still need writing,
  the acceptance rules, and the out-of-scope items.
- Linked the matrix from `docs/wpds/10-task-index.md` so every Phase 5 feature
  starts from the same contract.
- The matrix records the three settings that are currently UI-only
  (`queue_enabled`, `flag_abandoned`, `re_enable`) and requires each to be wired
  or hidden before release.

### Verification

- `docs/wpds/34-roadmap-matrix.md` exists and names every owner roadmap item
  R-01 to R-10.
- The Free/Pro split matches the owner correction: FTP/external logging and
  direct changelog links are Free; the other feature items are Pro; prices are
  out of code.
- No source code changed in this step.

### Next

1. Phase 2.1: Settings screen audit, starting with the uninstall-data setting.
2. Phase 2.2: Update logs screen audit.

## 2026-10-26 · Phase 2.1 — Settings

### What landed

- Extracted the Settings draft comparison into
  `assets/src/js/components/settingsDraft.js`; added `isSettingsDirty()` and
  `canLeaveDraft()` plus 11 Vitest cases covering the uninstall opt-in, reverted
  fields, notification order, and the discard decision.
- Fixed the `popstate` dirty guard and restored the current tab URL when the
  user cancels. The rail guard and the browser guard now share one decision.
- Associated and announced the recipient error with `id`, `role="alert"`,
  `aria-invalid`, and `aria-describedby`; removed the dead `onDiscard` SaveBar
  prop.
- Added the `PUT /updratronix/v1/settings` integration test for the opt-in,
  the direct uninstall decision test, and the multisite helper-name fix.
- Required `manage_options` to turn the irreversible uninstall opt-in on;
  turning it off remains available.
- Wrote `docs/wpds/35-settings-state-of-play.md`, updated the Settings contract
  key list, and refreshed the protocol test inventory.

### Verification

| Check | Result |
|---|---|
| Free `bin/harness test:js` | 27 tests, pass. |
| Free `bin/harness integration` | 69 tests, 180 assertions, 13 skipped, pass. |
| Free `bin/harness test` | 213 tests, 415 assertions, pass. |
| Free `bin/harness lint:js` | Pass. |
| Free `bin/harness build` | Pass; entry 1.91 MiB with the existing warnings. |
| Focused WPCS | 0 errors; 5 pre-existing unused-`$request` warnings. |
| Review/security subagent passes | Completed; SET-06 to SET-10 fixed. |

### Known gaps

1. E2E remains blocked by `libnspr4.so`.
2. The keyboard/screen-reader/browser pass for Settings is still manual.
3. A component-level 500/network save test is still missing.
4. Retention clamps per keystroke; the contract's validation-error state is a
   recorded follow-up.
5. No version bump.

### Next

1. Phase 2.2: Update logs screen audit.

## 2026-10-26 · Phase 2.2 — Update logs

### What landed

- Extracted `parseDeepLinkLogId()` and added 6 JS cases for the Pro
  blocked-history `?log=<id>` link; `SettingsPage` now uses it.
- Extracted `filterExportFormats()` and added 6 JS cases. The export dialog
  keeps known formats, removes duplicates, and falls back to `txt`; Free cannot
  expose CSV or JSON.
- Wrote `docs/wpds/36-update-logs-state-of-play.md` with the claims matrix,
  mechanics, findings, test inventory, and feature guide.
- Verified the deep-link and format-gating claims; no P0/P1 defect was found in
  the filters, row actions, details dialog, or delete dialog.
- Recorded UL-04 (redaction fail-open on `preg_replace()` null) as an open
  security follow-up from the security pass.

### Verification

| Check | Result |
|---|---|
| Free `bin/harness test:js` | 39 tests, pass. |
| Free `bin/harness integration` | 69 tests, 180 assertions, 13 skipped, pass. |
| Free `bin/harness lint:js` | Pass. |
| Free `bin/harness build` | Pass; entry 1.91 MiB with the existing warnings. |
| PHP suites | Unchanged from 2.1: 213 unit tests pass; integration rerun above. |

### Known gaps

1. E2E remains blocked by `libnspr4.so`.
2. No live large-table export run in this pass.
3. No manual screen-reader pass on the details or export dialogs.
4. UL-04 redaction fail-open remains open.
5. No version bump.

### Next

1. Phase 2.3: Auto updates screen audit.

## 2026-10-26 · Phase 2.3 — Auto updates

### What landed

- Extracted `buildAutoUpdateDraft()` and `collectAutoUpdateMutations()` into
  `autoUpdatesDraft.js`; added 6 Vitest cases for defaults, changed-only rows,
  and the core → translations → themes → plugins order.
- `saveAll` now applies that ordered plan and, on a later mutation failure,
  refetches the server state before reporting. The notice says some changes may
  have been applied and the page now shows the server state.
- The panel shows a visible warning with Retry when a refresh fails while data
  is still on screen, instead of silently keeping stale values.
- Removed the dead `onDiscard` SaveBar prop.
- Wrote `docs/wpds/37-auto-updates-state-of-play.md` with the claims matrix,
  mechanics, findings, test inventory, and feature guide.

### Verification

| Check | Result |
|---|---|
| Free `bin/harness test:js` | 45 tests, pass. |
| Free `bin/harness integration` | 69 tests, 180 assertions, 13 skipped, pass. |
| Free `bin/harness lint:js` | Pass. |
| Free `bin/harness build` | Pass; entry 1.91 MiB with the existing warnings. |
| PHP suites | 213 unit tests from 2.1; integration rerun above. |

### Known gaps

1. E2E remains blocked by `libnspr4.so`.
2. No component-level test simulates a 500 on the third mutation.
3. No manual screen-reader pass on the constant notices or refresh warning.
4. No live update run.
5. No version bump.

### Next

1. Phase 2.4: Schedule screen audit.

## 2026-10-26 · Phase 2.4 — Schedule

### What landed

- Extracted `scheduleDraft.js` with `getSchedule`, `getHoldDays`,
  `isScheduleDirty`, and `validateHoldDays`; added 11 Vitest cases for fallback,
  hold bounds, dirty/revert, and the validation copy.
- Added the explicit site-timezone label under Preferred time.
- Added the WP-Cron-disabled warning in Next run.
- Replaced silent hold clamping with a validation error; the last valid draft
  stays in place.
- Removed the dead `onDiscard` SaveBar prop.
- Wrote `docs/wpds/38-schedule-state-of-play.md` with the claims matrix,
  mechanics, findings, test inventory, and feature guide.

### Verification

| Check | Result |
|---|---|
| Free `bin/harness test:js` | 56 tests, pass. |
| Free `bin/harness integration` | 69 tests, 180 assertions, 13 skipped, pass. |
| Free `bin/harness test` | 213 tests, 415 assertions, pass. |
| Free `bin/harness lint:js` | Pass. |
| Free `bin/harness build` | Pass; entry 1.91 MiB with the existing warnings. |

### Known gaps

1. E2E remains blocked by `libnspr4.so`.
2. No manual screen-reader pass on the timezone label, hold error, or WP-Cron
   notice.
3. No live cron run.
4. No component-level save-500 test.
5. No version bump.

### Next

1. Phase 2.5: Health screen audit.

## 2026-10-26 · Phase 2.5 — Health

### What landed

- Added `canLoadDiagnostics()` and a Retry action to the diagnostics error state;
  a failed request no longer caches an unretryable error.
- Added an error notice when “Run check” fails.
- Added `HealthRestApiTest` for the snapshot shape, diagnostics payload, missing
  retry 404, and capability 403.
- Wrote `docs/wpds/39-health-state-of-play.md` with the claims matrix, mechanics,
  findings, test inventory, and feature guide.

### Verification

| Check | Result |
|---|---|
| Free `bin/harness test:js` | 60 tests, pass. |
| Free `bin/harness integration` | 73 tests, 197 assertions, 13 skipped, pass. |
| Free `bin/harness test` | 213 tests, 415 assertions, pass. |
| Free `bin/harness lint:js` | Pass. |
| Free `bin/harness build` | Pass; entry 1.91 MiB with the existing warnings. |

### Known gaps

1. E2E remains blocked by `libnspr4.so`.
2. No manual screen-reader pass on the diagnostics, retry, or check grid.
3. No live retry.
4. Local open issues are review fixtures.
5. No version bump.

### Next

1. Phase 2.6: Documentation screen audit.

## 2026-10-26 · Phase 2.6 — Documentation

### What landed

- Moved the three guide URLs into `pages/staticLinks.js` and added 3 JS cases
  asserting HTTPS, the documentation host, uniqueness, and the guide names.
- Added the WPDS `Pro` badge to the Updatronix 3000 guide card, matching
  `FEAT-F-DOCS`.
- Wrote `docs/wpds/40-documentation-state-of-play.md` with the claims matrix,
  mechanics, findings, test inventory, and feature guide.

### Verification

| Check | Result |
|---|---|
| Free `bin/harness test:js` | 63 tests, pass. |
| Free `bin/harness lint:js` | Pass. |
| Free `bin/harness build` | Pass; entry 1.91 MiB with the existing warnings. |

### Known gaps

1. E2E remains blocked by `libnspr4.so`.
2. No manual screen-reader pass on the cards or closing note.
3. Link reachability was not network-tested.
4. No visual regression test.
5. No version bump.

### Next

1. Phase 2.7: Support screen audit.

## 2026-10-26 · Phase 2.7 — Support

### What landed

- Moved the WordPress.org forum URL into `pages/staticLinks.js`; the shared
  static-link test now covers HTTPS, host, path, and the three documentation
  guides.
- Verified the review-20 fixes remain in place: visible CTA, brand icon, 900px
  stack breakpoint, and the smaller mobile step number.
- Wrote `docs/wpds/41-support-state-of-play.md` with the claims matrix,
  mechanics, findings, test inventory, and feature guide.

### Verification

| Check | Result |
|---|---|
| Free `bin/harness test:js` | 63 tests, pass. |
| Free `bin/harness lint:js` | Pass. |
| Free `bin/harness build` | Pass; entry 1.91 MiB with the existing warnings. |

### Known gaps

1. E2E remains blocked by `libnspr4.so`.
2. No manual screen-reader pass on the steps, CTA, or privacy note.
3. Link reachability was not network-tested.
4. No visual regression test.
5. No version bump.

### Next

1. Phase 2 close: sweep the 2026-10-24 QA findings.

## 2026-10-26 · Phase 2 close — QA sweep

### What landed

- Added `docs/wpds/42-qa-2026-10-24-sweep.md`: every Free finding from the
  2026-10-24 QA note is marked Fixed, Partial, Open, or Accepted with the
  evidence and the next owner.
- The Pro findings are carried forward to Phase 3/5, the update-engine findings
  to Phase 4, and the environment/manual gaps to Phase 6.
- All seven Free screens now have a state-of-play dossier and a claims matrix.

### Verification

| Check | Result |
|---|---|
| Free `bin/harness test:js` | 63 tests, pass. |
| Free `bin/harness integration` | 73 tests, 197 assertions, 13 skipped, pass. |
| Free `bin/harness test` | 213 tests, 415 assertions, pass. |
| Free `bin/harness lint:js` | Pass. |
| Free `bin/harness build` | Pass; entry 1.91 MiB with the existing warnings. |
| E2E | Blocked by `libnspr4.so`. |

### Open after Phase 2

1. Pro screen guards, dirty flags, feedback, and SaveBar verification (Phase 3/5).
2. Flow runner failure/live coverage and the shared queue kernel (Phase 4).
3. Component-level interaction tests, screen-reader pass, 901–960/390 pass,
   slow-network/concurrent-save tests, E2E host dependency (Phase 6).

### Next

1. Phase 3.1: Updatronix 3000 / license screen audit in a fresh tab.
2. Phase 3.2: White label screen audit.

## 2026-10-26 · Phase 3.1 — Updatronix 3000 / licence

### What landed

- Audited the Pro licence screen against contract §4, `FEAT-P-LICENSE`, the
  roadmap matrix, the prior review, and the QA/reviewer/security checklists.
- Corrected the claims: Queue list and Development tools are roadmap; Advanced
  exports is CSV/JSON; XML is roadmap; Shield no longer claims the queue or
  version-controlled locking. The Free-only presentation says the same.
- Added the reload boundary after activation, deactivation, and clear. Pro's
  PHP hooks, REST routes, and panel chunks are registered at boot; a one-shot
  session-storage notice preserves the success message across the reload.
- Server activation errors are inline, with `aria-invalid`,
  `aria-errormessage`, and focus on the field after render. A 429 names the
  remaining seconds and returns `retry_after`.
- Added distinct status-service and invalid-licence states. A decryption error
  is now detected even when the stored status is `invalid`, so the clear path
  cannot trap the user.
- Redacted the submitted key from upstream error messages. Status never
  contains the full key.
- Wrote `43-updatronix-3000-state-of-play.md`; updated contract §4, the feature
  brief, the roadmap row for R-08, the audit protocol inventory/baseline, this
  log, and both changelogs.

### Verification

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
| Manual runtime | Active/inactive/loading, activation and deactivation reload, clear reload, 400/429 inline, status error, invalid, decryption+invalid, dev mode, Free-only copy |
| E2E | Free suite 2/2 pass after installing Playwright Chromium and its system libraries in the DDEV container; Pro has no E2E suite. |

### Known gaps

1. Pro still has no integration suite; route registration and validation are
   unit-tested through the recording shim.
2. No live Multisite pass; the Super-Admin-only decision is unit-tested.
3. The Free export dialog's XML branch still renders CSV; it is deferred to the
   Free update-log audit/Phase 6.
4. No screen-reader or RTL pass.
5. Pro still has no E2E configuration; the Free suite passes 2/2.
6. No version bump and no commit.

### Next

1. Phase 3.2: White label screen audit.
2. Phase 6: Pro integration/E2E, the Free XML export fix, and the
   accessibility pass.

## 2026-10-26 · Phase 3.2 — White label

### What landed

- Audited the Pro White label screen against contract §7,
  `FEAT-P-WHITE-LABEL`, the prior reviews 24 and 31, the roadmap matrix, and
  the QA/reviewer/security checklists.
- Made white label opt-in: both replacement switches now default to false in
  PHP and JS. Reset restores the same defaults.
- Hardened the PHP class: `sanitize_text_field` with 60/40 character caps,
  `sanitize_hex_color`, `rest_sanitize_boolean`, known-key normalization on
  read and write, and a fail-closed license check. A changed invalid logo now
  returns 400 `updatronix_invalid_logo` without writing the option.
- Restricted the logo to PNG, JPEG, or WebP attachments that exist, are images
  (not SVG), are readable by the current user, respect `upload_files`, are at
  most 1 MB, and are at least 64×64 px. The client checks the same type/size/
  dimension rules before uploading and clears the input afterwards.
- Applied the colors to the real shell: the brand color seeds the WPDS
  `ThemeProvider` (buttons, links, focus, active states), the menu color drives
  the rail/shell CSS variables, the active rail item uses the brand color, and
  a light-menu body class switches the rail to a readable dark-on-light palette.
- Replaced the preview mocks with the real rail destinations and the real
  plain-text Safe mode email (subject and body, name-only branding). The copy
  now says what the code does; the old “hides names/links/marks”, “name and color
  in emails”, and “SVG or PNG” claims are gone.
- Fixed the state machine: license loading, license-status error, inactive
  license, settings load error, settings loading, ready. A failed initial GET
  now shows the error card with Retry instead of a permanent spinner; a license
  status failure has its own card; save failures are inline and in a snackbar.
- Named both color palettes for assistive technology and described both
  previews as images. The admin preview uses the real visible destinations.
- Renamed the shared `licenseReload` helper to `adminReload`; White label now
  reloads after save with a one-shot notice, so the rail and admin menu update
  immediately.
- Wrote `44-white-label-state-of-play.md` and updated contract §7, the feature
  brief, the audit protocol inventory/baseline, the roadmap row, this log, the
  prior review pointer, and both changelogs.

### Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 243 tests, 778 assertions, pass |
| Pro `bin/harness test:js` | 3 files, 41 tests, pass |
| Pro `bin/harness lint:js` | Pass, 0 warnings |
| Pro `bin/harness build` | Pass |
| Pro `bin/harness lint:files` on modified PHP | Pass |
| Pro `bin/harness lint` | WPCS has only pre-existing warnings in unrelated files; PHPStan no errors |
| Free `bin/harness test` | 213 tests, 415 assertions, pass |
| Free `bin/harness test:js` | 63 tests, pass |
| Free `bin/harness lint:js` | Pass, 0 warnings |
| Free `bin/harness build` | Pass; existing runtime/size warnings |
| Free `bin/harness lint:files` on modified PHP | Pass |
| Free `bin/harness integration` | 73 tests, 197 assertions, 13 skipped, pass |
| Manual runtime | Colors (Navy/Teal), light menu, save/reload notice, logo SVG/small/large rejection, valid PNG upload, server 400, load-error, status-error, reset defaults |
| E2E | Not re-run in this task; Free baseline 2/2 from Phase 3.1. Pro has no E2E configuration |

### Known gaps

1. Pro still has no integration suite; the route and callbacks are unit-tested
   through the WordPress shim.
2. No live Multisite pass; the Super-Admin gate and the network note are
   unit-tested.
3. No screen-reader or RTL pass. The color controls and preview descriptions
   are improved, but the full traversal remains Phase 6.
4. The email application is name-only for the Safe mode notification. Branded
   HTML email and core update-email retemplating are not in this release.
5. Pro still has no E2E configuration; the Free suite passes 2/2.
6. No version bump and no commit.

### Next

1. Phase 3.3: Update Shield and Update Flow screen audits.
2. Phase 6: Pro integration/E2E, a live Multisite pass, the Free XML export
   fix, and the accessibility pass.

## 2026-10-26 · Phase 4.2 — Shared update engine kernel (dormant)

Branch `major/wpds`, Free and Pro. No version bump, no commit.

### What landed

| Area | Change |
|---|---|
| Kernel | `inc/update-engine/`: job/status value object, policy result and contract, plan and planner contract, execution result, queue repository, native executor adapter, journal, notifier, kernel, facade, dormant bootstrap. |
| Queue | Main-site table `updatronix_pro_update_queue`, schema `1.0.0`, `dbDelta` install/upgrade, schema option, atomic conditional claim, 15-minute lease, attempt increment, dependency gate, retry/backoff, cancel, sweep, retention, item barriers. |
| Executor | Only `WP_Automatic_Updater::update()`; native `auto_updater` lock; `pre_auto_update` observation; result mapping for `true`/`false`/`WP_Error`/`null`; scoped execution flag for the 4.3 handshake. |
| Journal | Free logger adapter, `sha256` event keys, action/status mapping, DB-level blocked history, bounded non-autoloaded run summaries. |
| Notifier | One plain-text digest per run, Free recipient settings, Pro `notify_email`, event-key dedupe, no client address. |
| Free | Additive `updatronix_log_action_types` and `updatronix_log_query_args` filters; `action_type`, `action_types`, `created_at_after`, `created_at_before` SQL filters. No Free option or transient added. |
| Bootstrap | `updatronix_pro_update_engine_enabled` default false; registration requires the switch, an active license, and the main site. No cron, REST route, `auto_update_*` filter, panel, or email in 4.2. |
| Uninstall | 4.2-D6: the queue table is always dropped from the main site, with the network-admin/WP-CLI guard, even when Free's data opt-in is false. |
| Tests | First Pro integration suite: PHPUnit integration config/bootstrap, 11 integration files, real plugin and theme fixture updates through the native updater, and 10 pure unit files. |

### Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 291 tests, 1002 assertions, pass (baseline 243). |
| Pro `bin/harness integration` | 65 tests, 644 assertions, pass; 1 intentional Multisite-only skip (baseline: no suite). |
| Pro `bin/harness test:js` | 41 tests, pass (unchanged). |
| Pro `bin/harness lint:js` | Pass, 0 warnings. |
| Pro `bin/harness lint:files` on modified PHP | Pass. |
| Pro `bin/harness lint` (full) | Pass; PHPStan no errors. The test-tree filename rule is excluded in `.config/phpcs-project.xml` because PHPUnit discovers PascalCase test classes. |
| Pro `bin/harness build` | Pass. |
| Pro `bin/harness counterfactual` | 7 substitutable changed lines, 0 executed by the unit suite. These are declarative value-object property defaults plus uninstall/bootstrap wiring; the wiring is covered by the integration suite, the defaults by constructor behavior. Recorded as a coverage finding, not a pass. |
| Free `bin/harness test` | 216 tests, 422 assertions, pass (baseline 213). |
| Free `bin/harness integration` | 77 tests, 208 assertions, pass, 13 pre-existing skips (baseline 73). |
| Free `bin/harness lint:files` on modified PHP | Pass. |
| E2E | Not run; still blocked by `libnspr4.so`. Not claimed. |

The integration proof includes two-worker atomic claim, attempts increment once,
expired-lease recovery for target/source/unknown/exhausted versions, a stale
worker blocked after the sweep, retry/backoff and exhaustion, lock-busy requeue,
cancel and downstream cancel, license-gated cancellation, policy denial before
the updater, chain failure, run summaries, journal dedupe, digest one-send,
executor mapping, and real plugin/theme fixture installs with no external
network. The Multisite live guard is skipped in the single-site run; the
Super-Admin rule is covered by the pure capability matrix.

### Known gaps

1. No live Multisite run. The table/main-site/worker rule and the Super-Admin
   gate are tested by the pure matrix and the guarded integration test.
2. No real disk-full or fatal-loopback fixture. The failure paths are injected
   or simulated; the native plugin/theme fixture proves the executor path.
3. Site Health queue checks and the queue REST routes remain 4.3/5.1 surfaces;
   the 4.2 mission forbids UI/routes.
4. `queue_enabled` is not read by the engine. Its Shield default is left for
   4.3-D8 so 4.2 does not change Shield settings; the queue is inactive because
   no scheduler is registered.
5. E2E remains blocked by the host library.
6. No version bump and no commit.

### Next

1. Phase 4.3: Shield policies, VCS/Composer detection, `disable`/`cancel`,
   re-enable, abandoned advisory, blocked history, the ownership handshake, the
   scheduler, queue REST routes, and the `queue_enabled=false` migration.
2. Phase 4.4: the Flow planner, `POST /flow/run`, run summaries, and removal of
   the old runner.
3. Phase 6: Pro E2E and the live Multisite pass when the environment allows.

## 2026-10-26 · Phase 4.3a — Shield policies on the shared kernel

Branch `major/wpds`, Free and Pro. No version bump, no commit.

### What landed

| Area | Change |
|---|---|
| Policies | Ordered engine chain: license, engine/native opt-in, native guards, WordPress compatibility, PHP compatibility, Free delay, VCS/Composer, abandoned advisory, pending offer, chain dependency. The adapter chain is the blocking subset for non-engine items. |
| Compatibility | `Updatronix_Compatibility` is the only rule source; Safe Mode delegates to it. |
| Ownership | `auto_update_*` handshake at `PHP_INT_MAX`; the queue table is the source of truth; active and unresolved terminal rows are barriers; the executor's scoped flag passes its own job; no second lock. |
| Behavior | `disable` writes the native toggle and the ledger; `cancel` does not; core always cancels. Recheck honors the setting. |
| Re-enable | Shield-disabled items only, newer target only, blocking-policy check, ledger removal, `auto_update_reenabled`. |
| VCS/Composer | Native VCS is authoritative. Composer requires the item-root pair, realpath containment, no symlinks, cache invalidation, fail-open, and the extension filter. |
| Abandoned | Fixed WordPress.org data, 12 months, 7-day bounded cache, 20 lookups, advisory only; policies are cache-only. |
| Scheduler | `wp_maybe_auto_update` priority 1, hourly worker + earliest-availability follow-ups, one claimed/running run, expired-lease sweep, retention, queue-off automatic cancellation, Flow/manual drainage. |
| Queue REST | List, summary, retry, cancel, run; capability + Super Admin + license + engine availability; per-user cooldown; 404/409/429. |
| Blocked history | DB-level action/date filter and SQL pagination; the UI loads more pages. |
| Notifier | Engine one digest per run; adapter one digest per request for new event-key rows; no per-item email. |
| Settings | `queue_enabled` default false; one-shot stored-true migration; forced false while `queue_live` is false; `version_control_lock` default true. |
| UI | Disabled queue switch with honest dormant copy; real Composer lock; core cancel note; newer-release re-enable copy; abandoned advisories; blocked pagination; license copy. |
| Uninstall | Removes the Shield ledger, migration flag, and abandoned cache in addition to the queue table/settings/license. |

### Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 310 tests, 1097 assertions, pass. |
| Pro `bin/harness integration` | 79 tests, 733 assertions, pass; 1 intentional Multisite-only skip. |
| Pro `bin/harness test:js` | 41 tests, pass. |
| Pro `bin/harness lint:js` | Pass, 0 warnings. |
| Pro `bin/harness lint:files` on modified PHP | Pass. |
| Pro `bin/harness lint` (full) | Pass; PHPStan no errors. |
| Pro `bin/harness build` | Pass. |
| Pro `bin/harness counterfactual` | Recorded coverage finding: 12 of 61 executed changed lines checked (4 killed, 8 reported surviving); manual substitutions kill six, one is equivalent, two are covered by the new defaults test; the remaining 49 were not asked. Not claimed as a pass. |
| Pro `bin/harness mutation` | Not run in 4.3a; the counterfactual gate was chosen for this diff. |
| Free `bin/harness test` | No Free code changed; baseline 216 tests / 422 assertions. |
| Free `bin/harness integration` | No Free code changed; baseline 77 tests / 208 assertions, 13 skips. |
| E2E | Not run; still blocked by `libnspr4.so`. Not claimed. |

The integration proof covers the real policy chain on a blocked plugin, a
blocked theme, `disable` vs `cancel`, recheck requeue, scheduler idempotence,
one worker run, queue-off manual drainage, expired-lease recovery, the
cross-request handshake, `/queue` gates and callbacks, DB-level blocked history,
and the one-shot queue migration. Unit coverage covers every policy, the chain
order/short-circuit, re-enable, Composer detection, abandoned cache/cap, the
ledger, and the ownership handshake.

### Dormancy

The queue is dormant for three independent reasons:

1. `updatronix_pro_update_engine_enabled` is still false by default.
2. `queue_enabled` is false by default, forced false on save while
   `updatronix_pro_queue_live` is false, and a stored true is migrated once.
3. The Shield switch is disabled with honest copy.

No live queue run, no live Multisite run, and no real disk-full test were
available. E2E remains blocked.

### Known gaps

1. 4.3b/5.1 still owns the live queue fixture, the queue summary, and Site
   Health queue checks.
2. Flow still uses the old runner and bypasses Shield; 4.4 closes it.
3. The queue switch is disabled, not removed.
4. The Multisite live guard is skipped in the single-site run; the Super-Admin
   matrix covers the rule.
5. No version bump and no commit.

### Next

1. Phase 4.3b: live queue fixture, Screen claims, queue summary, and Site
   Health, still behind the live gate.
2. Phase 4.4: Flow planner, `POST /flow/run`, and runner removal.

## 2026-10-26 · Phase 4.3b — Queue live preparation

Branch `major/wpds`, Free and Pro. No version bump, no commit. The queue is
still dormant: `queue_live` false, `queue_enabled` false by default, engine kill
switch false by default, Shield switch disabled. The owner decided on
2026-10-26 to keep `queue_live=false`; no activation was performed.

### What landed

| Area | Change |
|---|---|
| Live fixture | `UpdateQueueLiveFixtureTest` runs the real scheduler, kernel, native executor, Free journal, notifier, and REST callbacks against local plugin/theme zips. Offers are injected through the native transient filters; WordPress.org update checks are answered synthetically; no package or API socket is opened. |
| Scenarios | Plugin/theme success, Shield block, retryable failure, terminal failure + REST retry, cancel, three expired-lease recoveries, duplicate worker against the native lock, duplicate planning, one digest per run. |
| Queue summary | `Updatronix_Queue_Summary` provides counters, last run, next worker, next queued job, stale leases, last error, and warnings. It is embedded in the Shield payload and returned by the existing `/queue/summary` route. |
| Shield UI | Existing queue card shows counters/dates/warnings; retry/cancel/run use the existing routes and are hidden while `queue_live` is false; the switch stays disabled. No new tab or screen. |
| Health | Free `updatronix_health_checks` filter; Pro appends five read-only queue checks on the main site with an active license. Dormant queue with actionable jobs is attention. |
| Correctness | Scheduler passes the filtered `max_attempts` to planned jobs; nullable queue inserts write SQL `NULL`; Health registration is testable. |

### Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 313 tests, 1107 assertions, pass. |
| Pro `bin/harness integration` | 95 tests, 900 assertions, pass; 1 intentional Multisite-only skip. |
| Pro `bin/harness test:js` | 53 tests, pass. |
| Pro `bin/harness lint:js` | Pass, 0 warnings. |
| Pro `bin/harness lint:files` on modified PHP | Pass, 0 errors. |
| Pro `bin/harness build` | Pass. |
| Pro `bin/harness counterfactual` | 200 of 200 executed changed lines checked; 92 killed, 107 survived, 1 equivalent. Seven survivors manually killed; 100 documented. **Not a pass.** |
| Free `bin/harness test` | 216 tests, 422 assertions, pass. |
| Free `bin/harness integration` | 78 tests, 212 assertions, pass, 13 pre-existing skips. |
| Free `bin/harness lint:files` on modified PHP | Pass, 0 errors. |
| Free `bin/harness build` | Pass. |
| E2E | Not run; still blocked by `libnspr4.so`. Not claimed. |

### Dormancy and decision

The local fixture sets `queue_enabled` directly in the test database only. It
does not change `queue_live`, the production default, the save path, or the
engine kill switch. The owner chose on 2026-10-26 to keep the queue dormant;
activation requires a new explicit decision after a staged run.

### Known gaps

1. The live fixture is local, not a staged host with real cron.
2. No live Multisite run.
3. The counterfactual has 107 survivors; 7 are manually killed, 100 documented.
4. Flow still bypasses Shield; 4.4 closes it.
5. E2E remains blocked.

### Next

1. Owner decision recorded: keep dormant (2026-10-26). Revisit only after a
   staged run and a new explicit decision.
2. Phase 4.4: Flow planner, `POST /flow/run`, and runner removal. Stop before
   4.4 until the owner asks for it.

## 2026-10-27 · Phase 4.4 — Flow planner on the shared kernel

Branch `major/wpds`, Free and Pro. No version bump, no commit. The queue is
still dormant: `queue_live=false`, `queue_enabled=false`, the Shield queue
switch is disabled. Flow now executes through the shared kernel, so the engine
kill switch defaults to true (**4.4-D9**); the automatic queue is still off.

### What landed

| Area | Change |
|---|---|
| Planner | `Updatronix_Flow_Planner` validates installed items, 20 chains/50 steps, duplicate steps, core-last, and cycles; builds one stable topological order over the merged graph; deduplicates by item and target; resolves native offers. |
| Plan facts | `Updatronix_Update_Plan` carries `canceled[]` and `errors[]` in addition to skipped/deduplicated/blocked. |
| Runner | `Updatronix_Flow_Runner` plans above `Updatronix_Update_Kernel`, enqueues every job through the Shield policy chain, starts one step per worker, and stores the run summary in the existing bounded option. |
| Policy | Chain dependency is allowed at enqueue for a new pending row and remains authoritative before execution; the queue claim already enforces predecessor success. No policy is bypassed. |
| Scheduling | Flow is planned at `wp_maybe_auto_update` priority 1, after discovery and before `WP_Automatic_Updater::run()`; `queue_enabled` controls only the automatic queue. |
| REST | `POST /flow/run` and `GET /flow/runs/{run_id}` with capability, Super Admin, license, engine availability, nonce via cookie auth, and a per-user/per-chain 5-minute cooldown. |
| Storage | `updatronix_pro_flow_chains` is `{version:1, chains:[…]}`; a bare list is migrated once and is not dual-read. |
| UI | Real Run now per chain, polling, per-step states, run summary, `Enable` toggle, honest one-step/Shield copy; no new tab. |
| Removal | The old Flow runner file, require, hook registration, and transient lock are deleted. One kernel, one native lock, one journal, one table. |
| Task 0 | Closed the three Flow-relevant 4.3c gaps: job integer hydration, plan `created_at` fallback, kernel license cast. |

### Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 348 tests, 1279 assertions, pass. |
| Pro `bin/harness integration` | 113 tests, 1028 assertions, pass; 1 intentional Multisite-only skip. |
| Pro `bin/harness test:js` | 61 tests, pass. |
| Pro `bin/harness lint:js` | Pass, 0 warnings. |
| Pro `bin/harness lint:files` on changed PHP | Pass, 0 errors and 0 warnings (25 files). |
| Pro `bin/harness build` | Pass. |
| Pro `bin/harness counterfactual` | 28 checked of 28 executed changed lines; 22 killed, 0 survived, 6 classified equivalent. 76 lines are substitutable and 28 unit-executed; integration-only lines are outside the gate. **Not a whole-diff pass.** |
| Free suites | Not re-run: no Free code changed (documentation only). |
| E2E | `bin/harness e2e --list` OK (2 tests); the full suite was not run or claimed. |

### Known gaps

1. The local live fixture is not a staged host with real cron.
2. No live Multisite run.
3. E2E remains blocked by `libnspr4.so`.
4. The three remaining 4.3c gaps stay Phase 6 debt.
5. The queue is dormant by owner decision; 4.4 did not activate it.

### Next

1. Phase 5.1/5.2 can wire truthful Shield claims and build on the delivered Flow
   action; no second Flow screen.
2. Phase 6: remaining 4.3c gaps, staged live run, Multisite live pass, E2E.
3. Queue activation still requires a new owner decision after a staged run.
