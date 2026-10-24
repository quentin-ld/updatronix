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
