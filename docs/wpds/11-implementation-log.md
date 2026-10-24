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
