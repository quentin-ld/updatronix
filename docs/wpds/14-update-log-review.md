# Update logs screen review

**Date:** 2026-10-25  
**Scope:** Free Update logs screen at `tools.php?page=updatronix&tab=logs`.  
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/update-log.html`.  
**Status:** Implemented and verified on 2026-10-25. Owner decisions applied; the normal WordPress admin frame is intentionally retained (UL-001/UL-003 accepted).

## Implementation result

- Safe parity: search label, bare versions, Failed status, download icon, 440px details, long dates, row radius/type scale.
- Shared 560px compaction: Free Logs and Pro Updatronix 3000 measured at 560/390 with no horizontal overflow.
- Details parity: identity/logo, actor, trigger, run type, description, WordPress.org link, changelog, process details, 440px preview, and in-layout narrow preview.
- Export parity: scope (current/all/30 days), CSV/JSON/plain text, merge, headers, columns, preview, copy, and Download; existing cursor/row/byte/chunk limits retained.
- Filters/paging: equivalent labels changed to Type/Action/Triggered by/Day; Showing/Load-more footer with 25 initial rows and +25 loading.
- Verification: Free ESLint, Stylelint, token lint, 15/15 JS tests, build, PHPStan, 213 PHPUnit tests; Pro ESLint, Stylelint, build, PHPStan, 200 PHPUnit tests. Browser checks at 1440, 560, and 390 include details, export, filters, and Load more.
- Remaining accepted gap: UL-016. `delete` is not in the security action allowlist and a missing status is sanitized to `success`; the UI/API should be reconciled separately.

## Method

The reference and live screens were opened side by side at 1440×900, 1280×800,
1024×768, 960×900, 768×1024, 560×900, and 390×844. The 980, 960, 760, 720,
and 560 breakpoint edges were measured with computed layout values and
`document.documentElement.scrollWidth`. Screenshots were captured at every
requested viewport.

## Viewport baseline

| Width | Reference | Live | Difference |
|---|---|---|---|
| 1440 | Captured | Captured | Live keeps the WordPress admin chrome around the plugin shell. |
| 1280 | Captured | Captured | Same shell chrome; live content is narrower than the reference. |
| 1024 | Captured | Captured | WordPress menu and toolbar are visible around the shell. |
| 980 | Rail 264, page 694, header 144 (row) | Rail 264, page 519, header 168 (row); admin menu 160, toolbar 32 | Live page loses 175px to WordPress chrome; header wraps taller by 24px. |
| 960 | Mobile bar 960×56, page 926, padding 20/16/48, gap 24, header 144 | Mobile bar 924×56, page 890, padding 20/16/48, gap 24, header 144; admin menu 36, toolbar 32 | Live shell is 72px narrower; no horizontal overflow. |
| 760 | Mobile bar 760, page 726, header 694×144 (column), layout 694 | Mobile bar 760, page 726, header 694×144 (column), layout 694 | Geometry matches. |
| 720 | Mobile bar 720, page 686, header 654×144 (column), layout 654 | Mobile bar 720, page 686, header 654×144 (column), layout 654 | Geometry matches. |
| 560 | Mobile bar 560, page 526, padding 16/16/40, gap 16, header 494×146, gap 12, layout 494 | Mobile bar 560, page 526, padding 20/16/48, gap 24, header 494×168, gap 16, layout 494 | Reference compaction is missing: 4px top/8px bottom padding, 8px page gap, 4px header gap, and 22px header height. |
| 390 | Captured | Captured | Live keeps the WordPress toolbar above the plugin mobile bar; reference has one bar. |

## Initial findings

### UL-001 — WordPress admin chrome remains around the shell

**Severity:** Blocker  
**Evidence:** At 980 the live page is 519px wide against the reference 694px;
the WordPress admin menu is 160px wide and the toolbar is 32px high. At 960
the admin menu is 36px and the live page is 890px against 926px. At 560 and
below the admin menu is hidden, but the 32px WordPress toolbar still sits above
the plugin mobile bar.  
**Expected:** The app shell owns the viewport, as in the reference.  
**Current:** The shell is mounted inside the WordPress admin chrome, so content
width, header wrapping, and the mobile bar position differ.  
**Regression risk:** High — hiding/collapsing WordPress admin chrome affects
every admin screen and the standard WordPress navigation contract.

### UL-002 — The 560px page compaction is missing

**Severity:** High  
**Evidence:** At 560 the reference page is `16px 16px 40px` with a 16px gap
and a 146px header; the live page is `20px 16px 48px` with a 24px gap and a
168px header.  
**Expected:** The reference mobile density at the 560 breakpoint.  
**Current:** Desktop padding/gap remain active.  
**Regression risk:** Low if scoped to the Update logs page; medium if changed in
the shared shell.

### UL-003 — The mobile bar is offset by the WordPress toolbar

**Severity:** High  
**Evidence:** At 390 and 560 the reference has one 56px mobile bar. The live
screen has the 32px WordPress toolbar plus the 56px plugin mobile bar before
the content card.  
**Expected:** One full-width mobile bar at the top of the app.  
**Current:** Two stacked bars.  
**Regression risk:** High — same global admin chrome decision as UL-001.

## Region map

| Region | Reference | Current | Review note |
|---|---|---|---|
| Shell, rail, mobile bar | `ui.jsx` shell; `.shell`, `.rail`, `.main`, `.mobile-bar` | `AppShell`, `Rail`, `.updatronix-shell`, `.updatronix-rail`, `.updatronix-mobile-bar` | Live shell is nested inside WordPress admin chrome; UL-001/UL-003. |
| Page header and meta | `PageHeader`; `.page-head`, `.page-head-main`, `.page-title`, `.page-sub`, `.page-meta` | `PageHeader`; `.updatronix-page-header`, `.updatronix-page-headrow`, `.updatronix-page-heading` | Header geometry matches at 760/720 but wraps at 980 and misses the 560 compaction. |
| Section header/actions | `Section`; `.section-heading`; `Button size="compact"` | `Section`; `.updatronix-section`; `Button` with `upload` icon | Export icon is an upload arrow instead of the reference download arrow; action height may differ. |
| DataViews toolbar | `DataViews` default toolbar; `searchLabel="Search update log"` | Custom `.updatronix-log-toolbar`; `DataViews.Search label="Search logs"` | Visible search label is “Search logs” vs “Search update log”. |
| Search/filters/view config | DataViews search, filter toggle, view config | Same components in a custom row | Layout matches at 560/720; labels and filter set differ. |
| Activity feed and date groups | `.log-feed`; DataViews activity; `formatDay` en-GB | `.updatronix-log-layout`; DataViews activity; `dateI18n` | Geometry close; date localization is intentional current behavior. |
| Status/type/version cells | `Badge` + status text; `from → to`; type icon/logo | `Badge` + `getStatusLabel`; `getVersionLabel` prefixes `v`; logo fallback | Version prefix and failed-status label differ. |
| Row actions | View details, Delete | View details (only when `detail_available`), Delete | Reference always exposes View details; current hides it for records without detail. |
| Details preview/dialog | Aside `Card.Root` with identity/logo, description, WordPress.org link, summary, changelog, process; scrolls into view | Aside `Section` + `LogDetailsContent` summary/message/trace; switches to `Dialog` at ≤1200px | Content composition and narrow-screen behavior differ. |
| Pagination | 25 initial entries, “Showing X of Y”, Load more | DataViews Footer; per-page 50 with sizes 10/25/50/100 | Different pagination model; no Load more. |
| Retention note | `Note` component | Plain `Text` with `.updatronix-muted` | Copy matches; component treatment may differ. |
| Export dialog | Scope (filtered/all/30 days), format (CSV/JSON/TXT), combine, headers, columns, generated preview, copy/download | Current-view only, plain text, merge switch, columns, generate, copy with/without formatting | Scope, format, download, title, and output framing differ. |
| Notices/snackbar | Local `useSnackbar` | `SnackbarHost` + WP notices store | Bottom-center snackbar is shared; wording/behavior needs comparison. |
| Empty/loading/error | Static reference data; DataViews default empty; no loading/error code | Custom empty string, DataViews loading, error Notice, logging-off Notice | Empty copy and state composition differ; loading/error states need visual checks. |

## Additional code-level findings

### UL-004 — Visible search label is wrong

**Severity:** Medium — **Regression risk:** Low  
**Evidence:** `ActivityLogPanel.js` sets `searchLabel="Search update log"` on
`DataViews` but the custom toolbar sets `DataViews.Search label="Search logs"`.
The live screenshots show “Search logs”; the reference shows “Search update
log”.  
**Expected:** `Search update log` in the visible field.  
**Current:** `Search logs`.

### UL-005 — Versions are prefixed with `v`

**Severity:** High — **Regression risk:** Low  
**Evidence:** `getVersionLabel()` returns `v%1$s → v%2$s` / `v%s`; the
reference summary renders `{ from } → { to }` without a prefix. Live rows show
`v1.2.0` and `v7.1.2 → v7.1.3`; reference rows show `5.3.1 → 5.3.2`.  
**Expected:** No `v` prefix in the feed.  
**Current:** Every version and version range is prefixed.

### UL-006 — Failed records are labelled “Error”

**Severity:** High — **Regression risk:** Low  
**Evidence:** `utils.getStatusLabel()` maps `failed`/`error` to “Error”; the
reference displays “Failed” and uses the high intent. The current filter list
also exposes “Error” rather than “Failed”.  
**Expected:** “Failed” for failed records, with the high badge.  
**Current:** “Error”.

### UL-007 — Details preview does not match the reference composition

**Severity:** High — **Regression risk:** Medium  
**Evidence:** The reference details card has an identity row with logo, item,
status, description, “View on WordPress.org”, a summary with Triggered by /
Trigger / Run type, a changelog/release-notes block, and process details. The
current `LogDetailsContent` shows a summary without the identity/logo, actor,
trigger, or WordPress.org link, has no changelog block, and uses “Process
details”/“Advanced details” only when message/trace data exists.  
**Expected:** The reference details composition.  
**Current:** Reduced summary plus message/trace.

### UL-008 — Pagination model differs

**Severity:** Medium — **Regression risk:** Medium  
**Evidence:** Reference: 25 initial entries, “Showing X of Y entries”, and
“Load more” in 25-row increments. Current: DataViews footer with per-page
choices 10/25/50/100 and an initial per-page of 50; no Load more.  
**Expected:** The reference load-more model.  
**Current:** Standard DataViews pagination.

### UL-009 — Export dialog scope and formats are missing

**Severity:** High — **Regression risk:** Medium  
**Evidence:** Reference export: scope choices (current search/filters, all
entries, last 30 days), format CSV/JSON/plain text, combine repeated updates,
column headers, report columns, generated preview, Copy report, Download. The
current modal only exports the current view as plain text from the server,
offers merge/columns, and has two copy actions. It has no scope selector,
format selector, or Download action.  
**Expected:** The reference export controls and outputs.  
**Current:** Server-generated plain-text preview with copy only.

### UL-010 — Details summary maps trigger and actor incorrectly

**Severity:** High — **Regression risk:** Medium  
**Evidence:** Current summary rows use “Triggered by” for
`performed_as_display` (Automatic/Manual) and “Run type” for
`update_context_display`, and never show the user who performed the update.
Reference summary uses “Triggered by” for the actor, “Trigger” for the
trigger, and “Run type” separately.  
**Expected:** Actor in Triggered by, trigger in Trigger.  
**Current:** Trigger in Triggered by, actor absent.

### UL-011 — Export icon is inverted

**Severity:** Low — **Regression risk:** Low  
**Evidence:** Current Export button uses the `upload` icon (arrow up out of a
tray); the reference uses `download` (arrow into a tray).  
**Expected:** Download icon.  
**Current:** Upload icon.

### UL-012 — Filter surface differs

**Severity:** Medium — **Regression risk:** Medium  
**Evidence:** Reference filters: Item, Type, Status, Trigger, Triggered by,
Day. Current filter registry: Category, Action type, Status, User, Trigger,
Date, Run type. Labels and the number of dimensions differ.  
**Expected:** The reference filter set and labels.  
**Current:** A larger, differently labelled registry.

## WPDS MCP inventory

The WPDS MCP answered for `Button`, `Badge`, `Notice`, `Card`, `Stack`,
`Text`, `Link`, `CheckboxControl`, `SwitchControl`, `SelectControl`,
`SearchControl`, `Tooltip`, `Spinner`, and `EmptyState`. It returned no
entries for `IconButton`, `Dialog`, or `AlertDialog` because those are
compound namespaces; their local package types must be the reference.

- `Badge` intents `stable`, `medium`, `high`, `informational`, and `none` are
  confirmed; the current badge mapping is valid.
- `Notice.Root/Title/Description/Actions` is confirmed; the current compound
  usage is valid.
- `Card.Root/Header/Content`, `Stack`, `Text`, `Link`, and the form controls
  are confirmed.
- The MCP `Button` entry documents `@wordpress/components` variants
  (`primary`, `secondary`, `tertiary`, `link`) while the screen uses the
  `@wordpress/ui` Button with `solid`/`outline`. The reference uses the same
  `@wordpress/ui` props, so this is an MCP/package documentation mismatch,
  not a screen finding. Do not change Button variants from the MCP text alone.
- `SelectControl` exists in WPDS, but the export modal does not expose a
  format selector; this supports UL-009, not a missing component.
- `SearchControl` exists, but this screen correctly uses `DataViews.Search`;
  the reference does the same.
- `Tooltip`, `Spinner`, and `EmptyState` are not used directly here;
  DataViews supplies the loading/empty presentation.

## Computed baseline

| Element | Reference | Current | Note |
|---|---|---|---|
| Page at 1440/960 | `32px 20px 64px`, gap 24 | `32px 20px 64px`, gap 24 | Matches. |
| Page at 560 | `16px 16px 40px`, gap 16 | `20px 16px 48px`, gap 24 | UL-002. |
| Header at 1440/960 | 144px | 144px | Matches. |
| Header at 980 | 144px | 168px | Wraps because the content column is 175px narrower; UL-001. |
| Header at 560 | 146px, gap 12 | 168px, gap 16 | UL-002. |
| Section heading | 40px | 40px | Matches. |
| Activity row | Reference activity row; logo 40px | Current activity row; logo 40px | Visual geometry is close; row content/copy differs. |
| Preview column | 440px | 360px | Reference `.log-layout.has-preview` vs current 360px grid; needs alignment. |
| Preview top | `padding-lg` | `gap-lg` | Same token value; confirm rendered offset. |
| Toolbar at 560 | Search + filter + view config | Search + filter + view config, 444px row | Close; labels and filter set differ. |
| Page body | Gap 24 | Gap 24 | Matches. |
| Retention note | `Text body-sm surface-note` | `Text body-sm updatronix-muted` | Copy matches; component class differs. |
| Main shell at 1440 | 1164×876, radius 12, bg `#fcfcfc`, overflow auto | 989×844, radius 12, bg `#fcfcfc`, overflow auto | Live loses 175px width and 32px height to WordPress chrome. |
| Page at 1440 | 1120 wide, margin 0 17 | 979 wide, margin 0 | Reference max-width 1120 is not reached. |
| Header at 1440 | 1080×144, gap 16 | 939×144, gap 16 | Height matches; width differs. |
| Title / subtitle | 32/40, 15/24, colors `#1e1e1e` / `#707070` | 32/40, 15/24, same colors | Matches. |
| Section heading | 402×40, gap 4 | 402×40, gap 4 | Matches. |
| Activity row | 152 high, radius 4 | 152 high, radius 0 | Row radius differs. |
| Item name / kind | 13/20, 11/16 | 13/20, 11/20 | Kind line-height differs. |
| Trigger/actor meta | 12/20 | 13/20 | Font-size differs. |
| Retention note | 12/16 | 12/16 | Matches. |

## State matrix

| State | Reference | Current | Evidence needed |
|---|---|---|---|
| Default feed | Populated 25+ rows, date groups | 7 current rows, date groups | Captured. |
| Empty | DataViews empty | Custom empty copy + note | Capture empty after cleanup. |
| Loading | Not represented | DataViews loading | Throttle/observe. |
| API error | No error UI in reference | Error Notice | Block REST request and capture. |
| Logging off | No reference state | Info Notice | Toggle setting and capture. |
| Details aside | `Card.Root`, 440px, identity/changelog | `Section`, 360px, summary/message/trace | Open a row at ≥1201px. |
| Details narrow | Reference keeps aside and scrolls to it | Dialog below 1200px | Open a row at 390/560. |
| Delete | AlertDialog | AlertDialog | Open row actions; confirm/cancel. |
| Export open | Scope/format/columns/preview | Merge/columns/plain-text preview | Open Export logs. |
| Export generated | CSV/JSON/TXT preview, copy, download | Plain-text preview, copy only | Generate and inspect. |
| Filters/search | Item/type/status/trigger/actor/day | Category/action/status/user/trigger/date/run type | Open Add filter. |
| Pagination | Load more after 25 | DataViews footer, per-page choices | Generate >25 logs. |
| Long content | Long names/versions | Current data mostly short | Add long-name fixture. |
| Deep link | Not represented | `?log=<id>` opens details | Verified in prior turn. |

## Fixture coverage

Generated 28 fixture logs. Cleanup removed the temporary Classic Editor
plugin and Twenty Twenty-Four theme; their deletion added two uninstall logs, so
the site now holds 30 logs for review. Real WordPress flows produced plugin
install, update, downgrade, and same-version entries (Classic Editor), theme
install and update entries (Twenty Twenty-Four), a core same-version reinstall,
and translation updates. The plugin logger produced the actions/statuses that
production flows cannot reach on this site: failed, warning, cancelled,
delayed, info, prevented, incompatible, disabled, safe_mode_disabled,
uninstall, bulk context, and a long item name. These are logger-API records,
not direct database inserts.

| Dimension | Covered |
|---|---|
| Type | core 2, plugin 13, theme 5, translation 8 |
| Status | success 19, warning 4, error 2, cancelled 1, delayed 1, info 1 |
| Action | update, install, downgrade, same_version, failed, uninstall, prevented, incompatible, disabled, safe_mode_disabled |
| Trigger | manual, automatic, upload; single, bulk, empty context |
| Data shape | long item name, same-version, missing versions, failed message/trace |

**Gaps:** the `delete` action is declared in the UI labels but is not in
`Updatronix_Security::ALLOWED_ACTION_TYPES`, so the logger rewrites it and it
cannot be generated through the supported API. A truly missing status cannot be
created because an empty status is sanitized to `success`; forcing it would
require a direct DB update, which this review deliberately avoids. Language
packs were already current; the core reinstall refreshed the Classic Editor and
Twenty Twenty-Four translation packs.

## Visual confirmation with fixtures

The fixture set (30 logs after cleanup) was reloaded at 1440. The feed renders
all four types, the long-name row, warning/error/cancelled/delayed/info rows,
and the prevented/incompatible/disabled/safe-mode actions. Confirmed
differences:

- The WordPress admin menu and toolbar still surround the shell; the live main
  is 989×844 against the reference 1164×876, and the page is 979 against 1120.
  UL-001/UL-003.
- The visible search field says “Search logs”; the reference says “Search
  update log”. UL-004.
- Feed versions retain the `v` prefix (`v1.6`, `v10.0.0 → v10.1.0`); the
  reference shows bare versions. UL-005.
- Failed/error records are labelled “Error”; the reference uses “Failed”.
  UL-006.
- The Export button uses an up-arrow/upload icon; the reference uses a
  download icon. UL-011.
- The long-name row grows to 172px and its name wraps to 40px with visible
  overflow; no clipping was observed. This is a pass, not a finding.
- The details preview is 360px wide; the reference is 440px. Its date is
  rendered as `10/25/2026, 9:49:23 AM`, while the reference uses the same long
  date label as the feed. The summary shows Item/Category/Action/Status/From/
  To/Triggered by/Run type/Date, with no actor, trigger, identity/logo,
  WordPress.org link, or changelog. UL-007/UL-010 plus UL-013/UL-014.
- The current activity row has radius 0, kind line-height 20px, and meta
  font-size 13px; the reference has radius 4px, kind 16px, and meta 12px.
  UL-015.
- The DataViews footer is absent at 28 records; there is no “Load more” or
  “Showing X of Y” treatment. UL-008.
- The `delete` action and a truly missing status cannot be produced through the
  allowed logger/security API; forcing them would require a direct DB update.
  UL-016.

### UL-013 — Details preview width

**Severity:** Medium — **Regression risk:** Low  
**Evidence:** Live `.updatronix-log-preview` is 360px wide; reference
`.log-preview` is 440px.  
**Expected:** 440px preview column at ≥1201px.  
**Current:** 360px.

### UL-014 — Details date formatting

**Severity:** Medium — **Regression risk:** Low  
**Evidence:** `formatDate()` uses `new Date(...).toLocaleString()`; the live
details show `10/25/2026, 9:49:23 AM`. The feed uses `dateI18n( 'j F Y' )` and
`dateI18n( 'H:i' )`, and the reference details use the long `dateLabel`.  
**Expected:** The same localized long date as the feed.  
**Current:** Browser-locale short date with seconds.

### UL-015 — Activity row radius and micro-typography

**Severity:** Low — **Regression risk:** Low  
**Evidence:** Live row radius 0, kind line-height 20px, meta font-size 13px;
reference row radius 4px, kind 16px, meta 12px.  
**Expected:** Reference row chrome and type scale.  
**Current:** Larger kind line-height and meta text, square row.

### UL-016 — Logger/API action gaps

**Severity:** Medium — **Regression risk:** Medium  
**Evidence:** `delete` is in the UI action labels but not in
`Updatronix_Security::ALLOWED_ACTION_TYPES`; a missing status is sanitized to
`success`.  
**Expected:** The UI and the logger/security allowlists agree; missing status
either has a deliberate legacy representation or is not a supported state.  
**Current:** The UI implies states that the supported logger API cannot create.

## Next passes

- Source and WPDS MCP inventory.
- Native log fixtures for every supported type/action/status/trigger.
- Desktop geometry, states, interactions, accessibility, and error states.
- Complete findings register and safe-fix taskfile.
