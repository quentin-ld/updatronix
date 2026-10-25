# Health — design-conformance review

**Date:** 2026-10-25
**Scope:** Free > Health only. Review only; no UI source changed.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/errors.html`, `docs/wpds/reference/screens/src/screens/Errors.jsx`, `app.css`, `ui.jsx`.
**Current:** `tools.php?page=updatronix&tab=health`, `HealthPage.js`, shared `PageHeader`/`Section`.

## Method

- Opened both screens at 1280×900; captured the top summary/checks region and the scrolled Open issues/Recently resolved region.
- Read the reference Errors composition and the current HealthPage, including summary/check cards, issue rows, resolved table, diagnostics, retry/detail dialogs, loading/error states, and date labels.
- Queried WPDS MCP for Badge, Button, Card, EmptyState, Link, Notice, and Dialog (the MCP currently has no Dialog entry; current/reference both use the WPDS Dialog API).
- Activated the current Download diagnostics action and inspected the resulting dialog; did not mutate site data.

## Region map

| Region | Reference | Current | Result |
| --- | --- | --- | --- |
| Page header/meta/actions | Health + 3 meta badges + Download/Run | Same structure; current values/date differ | Match structurally |
| Summary card | Warning card, badge, 2/6, disk/retry sentence | Warning card, badge, 1/6, failed-updates sentence | Data/state |
| Checks grid | 6 cards, 2 columns | 6 cards, 2 columns | Match layout |
| Open issues | 2 rows, View details/Retry | 2 synthetic fixture rows, same controls | Data/state |
| Recently resolved | 3-row table | Empty “No failures…” message | Data/state |
| WordPress admin frame | Absent | Present | Accepted deviation |
| Page/check width | 954px | 779px due admin frame | Accepted width delta |
| Date labels | Relative `4 min ago` / `Ran 2 min ago` | Absolute site format `25 October 2026 11h42` / `26 October 2026 0h00` | Differs (locale/format) |

## Findings

### H-001 — Download diagnostics opens a dialog instead of downloading

- **Severity:** High (interaction/feature)
- **Reference:** The header button triggers a transient snackbar: “Diagnostics bundle prepared. Check your downloads.” There is no diagnostics dialog.
- **Current:** The button opens a WPDS Dialog titled “Diagnostics” with environment definitions, a text dump, Copy, and Download; the header button itself does not download.
- **Evidence:** Live click opened the dialog with WordPress/PHP/Database/Server/memory/timezone/multisite/plugin/log-table values and Copy/Download footer actions.
- **Impact:** The header label promises a download but opens a review surface; this is the largest behavioral difference on the screen.
- **Regression risk:** Product/interaction decision; the current dialog is a useful diagnostic surface, but it should at least be renamed or the header should perform the download directly.

### H-002 — Run check feedback is inline and changes the button

- **Severity:** Medium
- **Reference:** “Run check” stays unchanged; feedback is a snackbar.
- **Current:** While running, the button is disabled and reads “Checking…”; after the request it renders a persistent inline Notice titled “Health check complete”.
- **Evidence:** `HealthPage.js` `runCheck`/`notice` state; reference uses `notify`.
- **Impact:** Extra persistent surface and a different button state; the same pattern was found on Schedule.
- **Regression risk:** Interaction decision; consider one shared background-action feedback pattern.

### H-003 — Absolute dates versus reference relative times

- **Severity:** Medium (locale/data)
- **Reference:** `Checked 4 min ago`; WP-Cron `Ran 2 min ago`; issue dates `21 Oct, 22:14`.
- **Current:** `Checked 25 October 2026 11h42`; WP-Cron `26 October 2026 0h00`; issue dates absolute. The current output follows the site `j F Y` + `G\hi` format.
- **Impact:** Health is a recency-focused screen; absolute long timestamps are harder to scan and `0h00` is locale-specific.
- **Regression risk:** Formatting decision; relative-time helpers must handle old/future/missing timestamps and timezone.

### H-004 — Details dialog adds copy UI and a large size

- **Severity:** Low/medium
- **Reference:** Detail dialog with the error summary and technical detail; no Copy details button.
- **Current:** `Dialog.Popup size="large"` plus a “Copy details” button and copy-state text.
- **Evidence:** `HealthPage.js` detail dialog.
- **Impact:** Extra UI and dialog width; not in the reference.
- **Regression risk:** Utility feature decision; the copy action is useful for support, but it is a deliberate addition.

### H-005 — Open issues and resolved data are fixture/state data

- **Severity:** Data
- **Current:** Open issues are `Review fixture incompatible theme` and `Review fixture failed plugin`, with `UPD_INCOMPATIBLE_FAILED` / `UPD_FAILED_FAILED`; Recently resolved is empty.
- **Reference:** Contact Form 7 / Rank Math SEO and a three-row resolved table.
- **Impact:** The live screen is showing review fixtures; this is not a design defect, but it makes the Open issues/Recently resolved comparison a data comparison rather than a layout comparison.
- **Note:** The code path for the resolved table exists; it is simply not exercised by the current data.

### H-006 — Check content/link differs

- **Severity:** Data/content
- **Current:** Automatic retries is “Available“, link “Review failed updates” to the update log.
- **Reference:** “Paused“, link “Review retry settings” to Auto updates.
- **Impact:** The check is correct for the current site state; the label/link target is product content, not a layout bug.

### H-007 — Matching areas

- Page header structure, summary-card composition, 2-column check grid, check icons/status badges, Open issues row structure and actions, resolved-table columns, EmptyState, warning Notice, and responsive stacking match.
- The current Open issues rows use the same severity/summary/version/site/PHP sequence and View details/Retry controls.
- The admin-frame width causes the header description to wrap to two lines and the check cards to be narrower; this is the accepted frame deviation.

## Accepted / data-only differences

- WordPress admin menu/toolbar and the resulting page/card width.
- Current check values (WordPress 7.1.3, PHP 8.4.24, disk 1.3 TB, retries Available), counts, summary sentence, issue names/severity/codes, and resolved emptiness.
- The fixtures currently in the update log; they should not be treated as production sample data.
- Loading, fetch-error, logging-off, and retry-error states are extra functional states not in the reference.
- WPDS MCP has no Dialog entry; current/reference both use the same `@wordpress/ui` Dialog API, so no component mismatch was found.

## Resolved decisions

1. **Diagnostics — keep the dialog, rename the action:** the header button is now “View diagnostics”; the dialog keeps environment data, Copy, and Download.
2. **Run check — match the reference:** success/warning snackbar through the shared SnackbarHost; the button stays “Run check” and active; no inline Notice. Retry also uses a snackbar.
3. **Time display — keep the site absolute format:** no change to `Checked …`, WP-Cron, or issue timestamps.
4. **Details dialog — keep the utility:** large dialog and Copy details remain, accepting the reference delta.

## Implementation result (2026-10-25)

- “View diagnostics” opens the existing diagnostics dialog.
- Run check uses success/warning notices; retry uses a success notice; the pre-existing inline health notice and Checking state were removed.
- Absolute site timestamps and the large detail dialog with Copy details remain.
- Verified Health at 1280/560/390 with no horizontal overflow; check grid columns collapse to one column at mobile.
- Free lint/CSS/token checks, 15 JS tests, build, PHPStan, and 213 PHPUnit tests pass.
- Open issues remain review fixtures and Recently resolved is empty; these are data, not UI changes.
- Mobile open issues: at ≤900px, issue actions now take a full-width row below the icon/body (reference behavior). At 390 the body is 222px wide, actions 274px, row height 217px, with no horizontal overflow; this replaces the previous 41px-wide body/433px row.

Remaining gaps: no dedicated visual/snackbar/dialog tests; full screen-reader and cross-browser passes remain separate work.
