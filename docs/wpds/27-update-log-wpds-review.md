# Update log WPDS MCP review

**Date:** 2026-10-25
**Scope:** Free Update logs screen (`tools.php?page=updatronix&tab=logs`).
**Reference:** Open Design `update-log.html`, `UpdateLog.jsx`, `app.css`, and the live WordPress/WPDS components.
**Status:** Review only. No UI source changed.

## Method

- Opened the reference and live screen at 1280×900 and 390×900, with the 760/960 shell edges checked.
- Compared rendered geometry, content states, row interaction, details surfaces, export output, and responsive layout.
- Used the WPDS MCP for `Button`, `Badge`, `Notice`, `SearchControl`, `SelectControl`, `Text`, `Link`, and `Icon`.
- Checked the source in `ActivityLogPanel.js`, `LogDetailsContent.js`, `ExportLogsModal.js`, `_activity-log.scss`, and the shared shell styles.

## What now conforms

- Page header title/description, retention tag and 16px mobile gap; 24px desktop gap.
- Activity/toolbar alignment: both x=469/w=729 at 1280 and x=53/w=274 at 390; no horizontal overflow.
- Activity icons: 32×32 holder/icon, 8px radius, x=469; no clipped border.
- Row interaction: pointer, hover/focus background, focus-within ring, row click opens details; title button and Actions menu remain.
- Mobile details: WPDS Dialog at ≤1200, aside above; 390 dialog 342×672, content 292×574, scroll position 0; no scroll-to-bottom.
- Plain-text export: real `<pre>` with `white-space:pre`, horizontal scroll; 2025px content in an 878px viewport at 1280.
- Mobile Load more at 390: fixed footer 390×57 with visible button; no inner-scroll discovery.
- Loading/empty handoff: “Loading update logs…” before the first response; empty state after load.
- Version/status labels, date groups, details summary, filters, and search label match the reference content where data permits.
- Admin frame (WordPress menu/toolbar) remains the accepted structural deviation.

## Remaining findings

### UL27-01 — The mobile search/filter toolbar wraps to two rows

**Severity:** Medium
**Regression risk:** Low

**Evidence:** At 390 the live toolbar is 274px wide and 73px tall: search at y=445 (226px), filter at x=53/y=485, View options at x=295/y=485. The reference keeps search (182px), filter, and View options on one 32px row at y=383. The live filter/view row is an extra 40px and leaves a large empty gap under the search field.

**Recommendation:** Constrain the DataViews search control at mobile so the filter and view-config buttons share the first row, matching the reference. Keep the existing activity/toolbar alignment. Low risk; search/filter behavior is unchanged.

### UL27-02 — Fixed Load more starts only at ≤600px

**Severity:** Medium
**Regression risk:** Medium

**Evidence:** At 390 the Load more bar is fixed at x=0/y=843 (390×57). At 760 the same bar is `position:static` at y=4353, still at the bottom of the inner scroller. The shell already uses its mobile bar at ≤960.

**Recommendation:** Decide the mobile footer boundary: extend the fixed footer through the mobile shell breakpoint (≤960), or document 600px as the intended mobile boundary. Extending it is the safer UX fix but touches the shared scroll/footer contract.

### UL27-03 — Dynamic error/logging notices are not announced

**Severity:** Medium
**Regression risk:** Low

**Evidence:** WPDS MCP `Notice` explicitly states that it does not announce its content; consumers must announce dynamic updates. The early error and logging-off returns in `ActivityLogPanel` render `Notice.Root` without a live region or `speak()`. The delete/details flows have a live region, but these early states do not.

**Recommendation:** Add a polite live-region announcement (or `speak()` from `@wordpress/a11y`) when the error/logging-off/notice state becomes visible; verify with a screen reader.

### UL27-04 — Row click is pointer-only; the title is the keyboard control

**Severity:** Low
**Regression risk:** Low

**Evidence:** The row has `cursor:pointer` and the delegated click handler; keyboard focus reaches the title's role-button wrapper, not the row itself. This is acceptable as long as the title is the accessible action, but the pointer affordance and keyboard surface are not identical.

**Recommendation:** Either document the title button as the keyboard affordance or promote the row to a focusable action with the same label. Do not add nested interactive controls without rechecking the Actions menu.

### UL27-05 — MCP coverage does not match the current @wordpress/ui package

**Severity:** Low (review limitation, not a screen defect)
**Regression risk:** None if local package types are kept.

**Evidence:** MCP `Button` documents `@wordpress/components` variants (`primary`, `secondary`, `tertiary`, `link`) and no `tone`; the live screen uses `@wordpress/ui` `Button` with `outline`/`solid` and `tone`. MCP `SearchControl`/`SelectControl` similarly target `@wordpress/components`, while the screen uses `@wordpress/ui`/DataViews. `DataViews`, `Dialog`, and `IconButton` have no MCP entries.

**Recommendation:** Treat the pinned local `@wordpress/ui`/`@wordpress/dataviews` types as authoritative and record the MCP gap in the review. Do not downgrade components to match the MCP’s older package docs.

## MCP component matrix

| Component | MCP result | Current use | Verdict |
|---|---|---|---|
| Button | `@wordpress/components`; primary/secondary/tertiary/link | `@wordpress/ui`; outline/solid + tone | Package/API gap; local types authoritative |
| Badge | intents incl. high/medium/stable | status/severity badges | Conforms |
| Notice | Root/Title/Description/Actions; no implicit announcement | early error/logging-off notices | Markup conforms; announcement missing |
| SearchControl | `@wordpress/components` | DataViews.Search | Not MCP-validated |
| SelectControl | `@wordpress/components` | `@wordpress/ui` export select | Not MCP-validated |
| Text/Link/Icon | Icon documented; Text/Link local | WPDS ui | Local types |
| DataViews/Dialog/IconButton | No MCP entry | Activity, details, export | Local package contract |

## Grilling QA — states and risks

- Covered: 34 fixture logs across core/plugin/theme/translation; success/warning/failed/cancelled/delayed/info; manual/automatic/upload; desktop and 390 mobile; details dialog; export pre; fixed footer; row click/hover; tag gap; icon alignment.
- Not covered in this pass: zero-log and filter-no-result empty states, logging-off/error rendering, export copy/download and keyboard export, 760/960 toolbar and footer behavior, screen-reader announcement of the early notices, long-name overflow with 100+ rows, RTL, reduced motion, and focus return after the mobile dialog closes.
- The two medium risks are UL27-01 (mobile toolbar) and UL27-02 (fixed footer boundary). The first is visual; the second changes the shared scroll/footer contract.

## Recommendations / no-regression order

1. Fix UL27-01 with a mobile-only search width rule; verify search/filter/view remain keyboard-operable and the activity alignment stays x=53/w=274.
2. Fix UL27-02 only after deciding the mobile boundary; verify 390/760/960 and the fixed footer’s relationship to the dialog.
3. Add UL27-03 announcement with `speak()`; no visual change.
4. Record UL27-05 as an MCP coverage limitation; keep local `@wordpress/ui` APIs.
5. Do not change the admin frame, rail colors, row detail model, or export format in this pass.

## Files

Fix taskfile: `.agents/tasks/2026-10-25-update-log-wpds-review.md`.
