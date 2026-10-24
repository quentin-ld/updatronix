# Free migration plan

Free owns the shell, the shared patterns, the WPDS runtime, and seven
destinations. This plan is ordered so every phase leaves the plugin shippable
and testable. Pro starts only after `G-F2`.

## Principles

- One phase, one verifiable outcome. Do not mix a build change with a screen change.
- Every task cites the document that owns its contract.
- REST, SQL, capability, multisite, and new admin-control work sets
  `review_required: yes` in the task file.
- No version bump. `UPDATRONIX_VERSION` changes only in `F6-05`, after explicit
  owner authorization.
- Lint immediately after PHP, JS, REST, and auth work. Batch comments and pure CSS.
- Keep the legacy shell runnable behind a build-time flag until `F5-01`.

## Phase overview

| Phase | Outcome | Tasks | Gate |
|---|---|---|---|
| F0 | Build foundation and compatibility proof | 6 | `G-F0`: the shell renders with WPDS on 7.1, and the bundle boundaries are proven |
| F1 | Rail shell and destination routing | 6 | `G-F1`: eleven destinations resolve; Free-only upgrade path works; responsive and keyboard pass |
| F2 | Shared page patterns | 4 | `G-F2`: page header, surface, save model, and dialogs are reusable and tested |
| F3 | Existing Free screens on the new patterns | 5 | `G-F3`: logs, auto updates, schedule, and settings pass the twelve-item checklist |
| F4 | New Free surfaces | 4 | `G-F4`: Health, Documentation, and Support are complete; the hub decision is recorded |
| F5 | Legacy removal and content cleanup | 4 | `G-F5`: no legacy shell, token, or `.button`/`.notice` surface remains |
| F6 | Hardening and release candidate | 5 | `G-F6`: full gates, Plugin Check, a11y, multisite, performance, release notes |

## F0 — Foundation and compatibility

> **Executed 2026-10-24.** See [`11-implementation-log.md`](11-implementation-log.md).
> The spike found that core 7.1 ships `@wordpress/element` 8.3.0 and that
> bundling element 10 removes the `wp-theme` dependency. The declared WordPress
> floor stays 6.2 (`D-22`) until a 6.2 smoke test runs.

### F0-01 · Compatibility spike

- **Change.** Verify the pinned WPDS set on WordPress 7.0 and 7.1: the
  `@wordpress/element` version, the `private-apis` version, the React version,
  `ThemeProvider` rendering, a `Button`, a `Card`, a `DataViews` table, and a
  `Page` from `@wordpress/admin-ui`.
- **Output.** A short note in the task file: verified floor, any package that
  must move, and the exact runtime handles that remain external.
- **Verification.** A throwaway admin page that renders the components on both
  versions; no production code.
- **Closes.** `R-01`, `D-18`.

### F0-02 · Package alignment

- **Change.** Update `package.json` and the lockfile to the guide's set:
  `@wordpress/ui` 0.25.0, `@wordpress/theme` 3.0.0, `@wordpress/components`
  43.0.0, `@wordpress/dataviews` 21.0.0, `@wordpress/admin-ui` 3.0.0,
  `@wordpress/icons` 19.0.0. Keep `@wordpress/element` at the version the target
  WordPress ships.
- **Verification.** `npm install`, `npm run build`, `npm run lint`,
  `npm run lint:css`; the build succeeds and the asset manifest contains no
  unexpected WordPress handle.
- **Depends on.** `F0-01`.

### F0-03 · Webpack boundary

- **Change.** Add `webpack.config.js`. Bundle the six WPDS packages. Keep React,
  `@wordpress/element`, and the infrastructure packages external. Add a build
  assertion that fails when a WPDS package leaks into the external list.
- **Verification.** Inspect `assets/build/index.asset.php`: no `wp-ui`,
  `wp-theme`, `wp-admin-ui`, `wp-dataviews`, `wp-components`, or `wp-icons`
  dependency is required for the bundled set. Compare bundle sizes before and
  after; record the delta.
- **Depends on.** `F0-02`.

### F0-04 · Shared runtime global

- **Change.** Add `assets/src/js/shell/wpds.js`. Expose
  `window.updatronix.wpds = { version, ui, theme, adminUi, dataviews, components, icons }`
  before any render. Document the contract in the task file and link to
  `03-target-architecture.md`.
- **Verification.** A JS unit test imports the module and asserts the shape and
  the version. A manual check in the browser asserts the global exists before
  the page mounts.
- **Depends on.** `F0-03`.

### F0-05 · Theme and tokens

- **Change.** Wrap the app in `ThemeProvider`, seed it from
  `getAdminThemeColors()`, import `@wordpress/theme/design-tokens.css` once, and
  remove conflicting legacy token definitions. Do not migrate screens yet.
- **Verification.** A blank shell renders with the default WPDS palette; the
  admin color scheme changes the rail seed; no duplicate token stylesheet is
  enqueued.
- **Depends on.** `F0-04`.

### F0-06 · Test harness for the shell

- **Change.** Add Vitest coverage for the runtime contract and Playwright smoke
  coverage for the admin page. Add a fixture page that mounts the shell with
  mock settings.
- **Verification.** `bin/harness test:js` and `bin/harness e2e` pass with the
  smoke test.
- **Depends on.** `F0-05`.

**Gate `G-F0`.** The shell renders on 7.1 with a WPDS button and card; the
bundle assertion passes; the shared global is present; no screen has been
migrated yet.

## F1 — Shell and navigation

### F1-01 · Rail shell

- **Change.** Build `App.js`, `Rail.js`, and the two-column layout. 264px rail,
  fluid content, max 960px. Brand lockup, version footer, four groups.
- **Verification.** Visual check at 1440px, 1024px, and 900px; no horizontal
  scroll; rail scrolls independently.
- **Depends on.** `G-F0`.

### F1-02 · Responsive navigation

- **Change.** Below 900px, replace the rail with a top bar and a `Drawer`. Keep
  the current destination visible in the bar.
- **Verification.** Keyboard opens and closes the drawer; Escape closes it;
  focus returns to the trigger; the page does not shift.
- **Depends on.** `F1-01`.

### F1-03 · Destination registry

- **Change.** Extend `updatronix_default_admin_tabs()` with `group`,
  `description`, `icon` key, `capability`, `pro`, and `panel`. Normalize third-party
  tabs. Seed the Free destinations and the `updatronix-3000` upgrade destination.
- **Verification.** PHP unit tests for normalization, sorting, unknown groups,
  unknown icons, and missing capabilities. A JS test asserts every destination
  renders once.
- **Review.** `review_required: yes` (capability surface).
- **Depends on.** `F1-01`.

### F1-04 · Routing and deep links

- **Change.** Move from `replaceState` to `pushState` plus `popstate`. Preserve
  `?tab=` slugs. Resolve unknown slugs to the first accessible destination. Focus
  the page title and announce the change.
- **Verification.** Each slug deep-links; Back and Forward work; an unknown slug
  falls back; a Pro slug on Free shows the upgrade page.
- **Depends on.** `F1-03`.

### F1-05 · Pro destinations and upgrade path

- **Change.** Render Pro destinations with a Pro badge. When Pro is absent or
  the panel is not registered, render the Updatronix 3000 upgrade page. When Pro
  is present but the panel throws, render the error boundary.
- **Verification.** Three fixture states: no Pro, Pro without the panel, Pro
  panel that throws. The shell survives all three.
- **Closes.** `D-19`.
- **Depends on.** `F1-04`.

### F1-06 · Shell accessibility

- **Change.** Skip link, landmarks, `aria-current`, visible focus, rail keyboard
  order, route announcement, reduced motion.
- **Verification.** Keyboard-only pass; screen-reader pass on one desktop and one
  mobile viewport; axe or equivalent scan with no critical finding.
- **Depends on.** `F1-02`, `F1-04`.

**Gate `G-F1`.** Eleven destinations resolve; Free-only and Pro-present states
both work; the responsive drawer passes; the keyboard pass is clean.

## F2 — Shared page patterns

### F2-01 · Page header

- **Change.** `PageHeader` with breadcrumb, title, description, meta badges, and
  at most one primary action. Focus target for route changes.
- **Verification.** Unit tests for the one-primary-action rule; visual check for
  long titles and long descriptions.
- **Depends on.** `G-F1`.

### F2-02 · Surface primitives

- **Change.** `Surface`, `Section`, `Notice` wrapper, `EmptyState` wrapper,
  `ErrorNotice`, and the icon-key mapping. Use WPDS compound components.
- **Verification.** Story or fixture page with all states; no hex values; no
  legacy class names.
- **Depends on.** `F2-01`.

### F2-03 · Save model

- **Change.** Draft state, dirty comparison, sticky save bar, `Ctrl+S` / `Cmd+S`
  scoped to the surface, `beforeunload` guard while dirty, disabled save when
  clean, success and error feedback.
- **Verification.** Unit tests for dirty, clean, shortcut, discard, and
  beforeunload. E2E for one form.
- **Depends on.** `F2-02`.

### F2-04 · Dialogs

- **Change.** `Dialog` for details and forms; `AlertDialog` for irreversible
  actions; focus trap, Escape, return focus, narrow-viewport sheet behavior.
- **Verification.** Keyboard tests for open, close, confirm, cancel, and focus
  return.
- **Depends on.** `F2-02`.

**Gate `G-F2`.** The four patterns are reusable, tested, and documented. Pro may
start P0 and P1.

## F3 — Existing Free screens

### F3-01 · Update logs table

- **Change.** Replace the custom log layout with one `DataViews` table. Preserve
  the existing fields, filters, search, sorting, pagination, and row actions.
- **Verification.** Parity test against the current filter fields; E2E for
  search, filter, sort, and paginate.
- **Review.** `review_required: yes` (REST + admin UI).
- **Depends on.** `G-F2`.

### F3-02 · Log dialogs

- **Change.** Details dialog, export dialog, delete `AlertDialog`. Reuse the
  export engine and the details content.
- **Verification.** Export produces the same report as the current build; delete
  names the entry and reports failure; focus returns.
- **Depends on.** `F3-01`.

### F3-03 · Auto updates manual save

- **Change.** Cards for core mode, translations, themes, and plugins. Add an
  atomic batch save endpoint or extend `/auto-updates` to accept a full payload.
  Remove per-toggle requests. Remove the white sub-navigation.
- **Verification.** One save commits all changes; a partial failure leaves the
  draft intact; constants remain read-only; multisite works.
- **Review.** `review_required: yes` (REST + new controls).
- **Depends on.** `F2-03`.

### F3-04 · Schedule

- **Change.** Three sections: `Update checks` (frequency, preferred time,
  hold days), `Next run` (saved next run and the shared-cron notice), and
  `What the hold does` (the reference table). One manual save. `Run a check
  now` calls `POST /run-check`.
- **Verification.** Hold persists as `delay_updates.enabled` plus
  `delay_value` (0–30 in the UI); the run-check endpoint refreshes core,
  plugin, theme, and translation data; next run uses saved values; WP-Cron
  shared notice; timezone label.
- **Depends on.** `F2-03`.

### F3-05 · Settings

- **Change.** Three cards: Logging, Email notifications, Data. Recipients are
  chips; the Data switch persists `delete_data_on_uninstall`; recovery warning;
  closing notes. Manual save. No sub-tabs.
- **Verification.** Retention 1 to 365; recipients validation; recovery mode
  always on; uninstall off by default and honored by `uninstall.php`; successful
  save notice.
- **Depends on.** `F2-03`.

**Gate `G-F3`.** All four existing screens pass the twelve-item checklist and
the save-model tests. No legacy `.button` or `.notice` classes remain in their
scopes.

## F4 — New Free surfaces

### F4-01 · Health

- **Change.** Implement `FEAT-F-HEALTH`: readiness checks, open issues, retry,
  resolved archive, diagnostics.
- **Verification.** REST permission and validation tests; E2E for each check
  state; retry idempotence.
- **Review.** `review_required: yes` (REST + capabilities).
- **Depends on.** `G-F3`, `D-17`.

### F4-02 · Documentation

- **Change.** Implement `FEAT-F-DOCS`: three linked guide cards.
- **Verification.** Link check; no network request beyond assets; translation
  extraction.
- **Depends on.** `G-F3`.

### F4-03 · Support

- **Change.** Implement `FEAT-F-SUPPORT`: four checks, forum link, cross-link.
- **Verification.** Link check; no network request beyond assets.
- **Depends on.** `G-F3`.

### F4-04 · Screens hub decision

- **Change.** Record whether the hub ships. Default: it does not. If it ships,
  add a twelfth destination with its own route and capability.
- **Verification.** Decision recorded in `02-decisions.md`; no dead code if the
  hub is not shipped.
- **Closes.** `D-13`.

**Gate `G-F4`.** Health, Documentation, and Support pass their contracts; the
hub decision is recorded.

## F5 — Legacy removal and cleanup

### F5-01 · Remove the legacy shell

- **Change.** Delete the gradient header, the white secondary navigation column,
  the `Tabs` component, the legacy shell SCSS, and the build-time legacy flag.
  Search for `.button`, `.notice`, `#2271b1`, and `--wp-components-*`.
- **Verification.** `rg` returns no legacy surface in `assets/src`; the build
  contains no legacy stylesheet; the shell renders from the new entry only.
- **Depends on.** `G-F4`.

### F5-02 · i18n pass

- **Change.** Extract every new string, regenerate the `.pot`, add translator
  comments, and verify the split-bundle translation loader still feeds async
  chunks. Do not reword existing translated strings without confirmation.
- **Verification.** `composer run make:pot`; a translated locale renders the new
  shell; no missing text domain.
- **Depends on.** `F5-01`.

### F5-03 · Token lint

- **Change.** Add a check that fails on hex, `rgb()`, `hsl()`, and raw px values
  in color, spacing, radius, and duration declarations outside
  `docs/wpds/reference/` and documented brand assets.
- **Verification.** The check fails on a seeded violation and passes on the
  codebase.
- **Depends on.** `F5-01`.

### F5-04 · Documentation and changelog

- **Change.** Update the README screenshots, the developer notes, the upgrade
  notice, and the changelog entry. State the new WordPress floor and the UI
  change.
- **Verification.** README facts match the plugin header and the bundle; links
  resolve.
- **Depends on.** `F5-02`.

**Gate `G-F5`.** No legacy shell remains; i18n and token lint pass; the
documentation states the new floor.

## F6 — Hardening and release candidate

### F6-01 · Accessibility audit

- **Change.** Audit every destination with a keyboard and a screen reader;
  record findings and fix them.
- **Verification.** No critical or high finding; the report is in `.agents/notes/`.
- **Depends on.** `G-F5`.

### F6-02 · Multisite and network admin

- **Change.** Verify every destination in Network Admin, the capability model,
  the network-scoped settings, and the single log table.
- **Verification.** Integration suite plus a manual network-admin pass.
- **Depends on.** `G-F5`.

### F6-03 · Performance and bundle budget

- **Change.** Record initial JS, per-route chunks, and CSS. Verify DataViews is
  not in the initial shell chunk.
- **Verification.** Budget comparison against the F0 baseline; no unexplained
  regression above 15%.
- **Depends on.** `G-F5`.

### F6-04 · Full gate

- **Change.** Run every gate: `npm run test:all`, `bin/harness integration`,
  `bin/harness e2e`, `bin/harness mutation`, `composer run lint:pcp`, and the
  visual smoke.
- **Verification.** All green; the visual snapshots are reviewed.
- **Depends on.** `F6-01` to `F6-03`.

### F6-05 · Release candidate

- **Change.** Bump to 2.0.0 only with explicit authorization. Update the
  `Stable tag`, the headers, the README, and the changelog. Build the zip.
- **Verification.** `bin/harness zip`; install the zip on a clean site and on a
  multisite network; run the smoke test.
- **Depends on.** `F6-04`.

**Gate `G-F6`.** The twelve-item checklist in
[`09-acceptance.md`](09-acceptance.md) passes. The release candidate is ready for
`/reviewer` and `/release`.

## Dependency summary

```
F0-01 → F0-02 → F0-03 → F0-04 → F0-05 → F0-06
                                      ↓
F1-01 → F1-02 → F1-06
  ↓
F1-03 → F1-04 → F1-05
  ↓
F2-01 → F2-02 → F2-03 → F2-04
  ↓
F3-01 → F3-02
F3-03, F3-04, F3-05
  ↓
F4-01, F4-02, F4-03, F4-04
  ↓
F5-01 → F5-02 → F5-03 → F5-04
  ↓
F6-01, F6-02, F6-03 → F6-04 → F6-05
```

## Review flags

| Task | Why |
|---|---|
| `F1-03` | Capability metadata and destination access. |
| `F3-01`, `F3-02` | REST, export, delete, admin UI. |
| `F3-03` | New batch save endpoint and per-item auto-update controls. |
| `F3-05` | Email routing and uninstall data deletion. |
| `F4-01` | New REST surface, retry action, capabilities. |
| `F5-02` | Translation strings and text domains. |
| `F6-05` | Version bump and release. |
