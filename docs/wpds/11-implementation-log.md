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
