# Update logs second-pass design-conformance review

**Date:** 2026-10-25
**Scope:** Free Update logs screen at `tools.php?page=updatronix&tab=logs`.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/update-log.html`, `docs/wpds/reference/screens/src/screens/UpdateLog.jsx`, `ui.jsx`.
**Status:** Implemented and verified on 2026-10-25. No UI source was changed in the review pass; the fixes below were applied afterward.

## Method

- Opened the reference and live screens at 1280×900, 560×900, and 390×900, plus the 980/960 and 560 edges.
- Captured screenshots at 1280 and inspected the live screen with 34 log records (core, plugin, theme, translation; success, warning, failed, cancelled, delayed, info; manual, automatic, upload; install, update, downgrade, same-version, uninstall contexts).
- Compared rendered DOM geometry, computed styles, scroll containers, and interaction states.
- Tested the Export logs flow with the default Plain text format, including the generated output.
- Checked the WPDS MCP for `Button`, `Badge`, `SearchControl`, and `Notice`; DataViews is not exposed as a standalone MCP entry and remains a local package contract.
- Checked the current code in `ActivityLogPanel.js`, `ExportLogsModal.js`, `_activity-log.scss`, and the shared `PageHeader`/shell styles.

## Findings

### UL2-001 — Type icons overflow the activity item column

**Severity:** High
**Regression risk:** Low

**Evidence:** The live activity icon is **40×40**, but its `.dataviews-view-activity__item-type-icon` holder is **25×25**; the icon overflows the holder by 15px on every row. The reference icon and holder are both **32×32**. The overflow is visible at 1280 and 390: the theme/plugin glyphs sit outside the timeline column and partially overlap the row.

**Code:** `_activity-log.scss` sets `.updatronix-log-type-icon` to 40px while the shared `--wpds-dimension-size-md` holder computes to 25px in the live theme.
**Expected:** One 32×32 icon inside a 32×32 holder, aligned to the timeline.
**Current:** 40×40 child inside a 25×25 holder.

### UL2-002 — Activity rows have no hover/focus surface and only the title button opens details

**Severity:** High
**Regression risk:** Medium

**Evidence:** The live `.dataviews-view-activity__item` has `cursor: auto`; the reference row has `cursor: pointer`. Clicking the row body at 1280 (x700/y400) leaves the page unchanged. The only clickable descendant in the live row DOM is the `Actions` button; clicking the title text button **does** open the preview (`Details` / `Loading log details…`), but the row itself has no click target, hover background, or focus-within treatment. The reference makes the whole activity row the detail affordance.

**Expected:** Row-wide click, pointer cursor, hover background, and visible keyboard focus; the title remains a secondary affordance.
**Current:** The row is inert; only the title button and the Actions menu are interactive.

### UL2-003 — Plain-text export is rendered as a wrapping `<pre>`

**Severity:** High
**Regression risk:** Low

**Evidence:** After generating the default Plain text export, the live output element is `<pre class="updatronix-export-output">`. Computed styles are `white-space: pre-wrap`, `overflow-wrap: anywhere`, `overflow-x: auto`; its content width is **878px** inside an **895px** box and its scroll height is **3304px**. The long report columns therefore wrap, destroying the fixed-width alignment. The stylesheet still targets `textarea#updatronix-export-output`, which no longer exists.

**Expected:** Plain text uses `white-space: pre` and horizontal scrolling (or a `textarea`), preserving fixed-width columns.
**Current:** The `<pre>` inherits `pre-wrap` and `overflow-wrap:anywhere`; columns wrap.

### UL2-004 — Retention tag touches the Recent updates section

**Severity:** High
**Regression risk:** Low

**Evidence:** At 1280 the live retention tag ends at y=220 and the section begins at y=220: **0px gap**. The reference tag ends at y=188 and `.log-layout` begins at y=212: **24px gap**. At 390 the live gap is also **0px**, while the reference is **16px**. The live page root `.updatronix-logs` contains the PageHeader and Section directly; there is no page-body gap between them.

**Expected:** The page header/section rhythm from the reference: 24px at desktop, 16px at mobile.
**Current:** The tag’s bottom edge is the section’s top edge.

### UL2-005 — Load more is buried in the inner shell scroller on mobile

**Severity:** Medium
**Regression risk:** Medium

**Evidence:** At 390 the live `.updatronix-shell-main` is **4706px** tall inside a 778px viewport. The Load more block is at document y=4723; `window.scrollY` remains 0 and `document.documentElement.scrollHeight` is 900. After setting the inner scroller to its maximum (`scrollTop=3928`), the button appears at y=804. It is `display:flex` and visible once reached, but there is no sticky footer and normal window scrolling does not reach it. This matches the reported “hidden on mobile” behavior.

**Expected:** A persistent list footer or a Load more control reachable through the page’s normal scroll path.
**Current:** The control exists only at the bottom of the internal `.updatronix-shell-main` scroll.

### UL2-006 — Mobile spacing still collapses at the retention tag and section

**Severity:** Medium
**Regression risk:** Low

**Evidence:** The 390 page itself now uses `16px 16px 40px` padding with a 16px gap, matching the reference. The remaining mobile spacing defect is the same 0px tag-to-section gap from UL2-004; at 560 the page keeps `16px 16px 40px` and a 16px gap, but the section still starts immediately under the tag. The reference keeps a 16px separation at 390.

**Expected:** The reference mobile rhythm, including the header-to-section gap.
**Current:** Correct page padding, collapsed header-to-section spacing.

### UL2-007 — The empty state is rendered before the first log response

**Severity:** Medium
**Regression risk:** Low

**Evidence:** On first opening the live screen with 34 rows in the database, the DOM showed “No update logs yet. Entries appear after WordPress runs an update.” before the rows appeared. The page later rendered the 34 records. The empty state is visible during the initial fetch rather than a loading placeholder.

**Expected:** Empty state only after `!loading && !error && total === 0`.
**Current:** Empty copy is eligible while the first request is in flight.

### UL2-008 — Export styling is attached to a removed textarea

**Severity:** Low
**Regression risk:** Low

**Evidence:** `_activity-log.scss` defines the export output under `textarea#updatronix-export-output`, but `ExportLogsModal.js` renders `<pre className="updatronix-export-output">`. The intended mono/pre/overflow rules never apply. This is the code cause of UL2-003.

## Implementation result (2026-10-25)

- **UL2-001 fixed:** icons and holders are 32×32; the balanced-density 25px override is constrained away. Verified at 489/418 with 32×32.
- **UL2-002 fixed:** rows have `cursor: pointer`, hover/focus background, focus ring, and a delegated row click that opens the detail preview. The title button and Actions menu remain keyboard/pointer targets.
- **UL2-003/UL2-008 fixed:** the actual `<pre class="updatronix-export-output">` is styled with `white-space: pre`, `overflow-wrap: normal`, and horizontal scrolling; measured 2025px content inside 878px at 1280.
- **UL2-004/UL2-006 fixed:** retention-tag to section gap is 24px at 1280 and 16px at 390/560.
- **UL2-005 fixed:** on ≤600px the Load more footer is fixed to the bottom (390×57; button 91×40) and visible without scrolling the inner shell; the page gets 72px bottom padding.
- **Activity alignment fixed:** the DataViews activity wrapper now has `padding-inline: 0`; the toolbar, activity wrapper, and item all measure x=469/w=729 at 1280 and x=53/w=274 at 390. No horizontal overflow.
- **UL2-007 fixed:** `useLogs` now exposes `loaded`; the initial screen shows “Loading update logs…” before the first response, then the empty state only after load.
- **D1 chosen:** whole-row click with the title button retained.
- **D2 chosen:** fixed mobile list footer.
- Verification: Free ESLint, Stylelint, token lint, 15/15 JS tests, build; browser checks at 1280 and 390; no horizontal overflow.

## Matching regions

The following areas matched within the source-of-truth differences already accepted for the admin frame:

- Page header title, description, retention tag text, breadcrumb and search label (“Search update log”).
- Section heading/description and Export logs placement.
- Activity row height (152px), date grouping, status badges, version ranges without a `v` prefix, trigger/actor text.
- Details preview content (identity, WordPress.org link, summary, changelog, process, advanced details) and the mobile stacking behavior.
- Export scope/format controls, copy/download actions, filters, and the 25-row + Load more model.
- No horizontal page overflow at 390; page padding and page gap now match the reference mobile values.

## WPDS MCP inventory

- `Button` (components): default/compact/small sizes and label/shortcut guidance checked; the current Export button uses the documented compact pattern.
- `Badge`: `stable`, `medium`, `high`, `informational`, `none` intents confirmed; current status mapping is valid.
- `SearchControl`: label/size guidance confirmed; the visible “Search update log” label is correct.
- `Notice`: compound `Notice.Root/Title/Description/Actions` confirmed.
- DataViews activity is not exposed by the MCP; its local UI contract is the reference for row/click/hover semantics.

## Regression-risk decisions

- UL2-002 changes the DataViews activity interaction model (row click, hover, focus). It needs an explicit decision because it can conflict with the title button and Actions menu.
- UL2-005 changes the mobile scroll/footer model; it touches the shared shell scroll container.
- UL2-001 and UL2-003 are low-risk style fixes but should be applied together with the reference row/Export checks.
- UL2-004/UL2-006 are low-risk spacing fixes; they must not reintroduce the section card padding or the 560 page compaction.

## Files

Implementation taskfile: `.agents/tasks/2026-10-25-update-log-review-second-pass.md`.
