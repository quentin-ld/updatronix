# Update Shield review (2026-10-25)

**Status:** Implemented and verified in the Pro plugin.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/update-shield.html`, `docs/wpds/reference/screens/src/screens/UpdateShield.jsx`, `docs/wpds/reference/screens/src/app.css`, `docs/wpds/reference/screens/src/ui.jsx`.
**Current:** `wp-content/plugins/updatronix-pro/assets/src/js/components/UpdateShieldPage.js`, `assets/src/scss/_pro-settings.scss`.
**WPDS MCP:** SwitchControl, RadioGroupControl, Badge, Link, Button, Card, Icon, and Text checked. The current Button is from `@wordpress/ui`, while the MCP Button entry documents `@wordpress/components`; the rendered link action below is the visible consequence to review.

## Method

- Opened the reference screen and the live destination together with an active test license.
- Captured the top and bottom at 1280×900; measured 960/560/390.
- Reviewed Protection in both off and on states. The live site defaults `safe_mode` off, so the disabled state was checked first, then Protect this site was switched on to match the reference.
- Compared the header/save model, section headers/actions, lock card, radio/switch controls, email details, queue, blocked table, dates, row typography, and closing note.
- The blocked table was populated by the review fixtures (safe-mode, incompatible theme, prevented plugin); those are test data, not a production state.
- The temporary test license was removed; the live site is inactive again.

## Findings

### US-001 — “Open update log” is a filled primary button

**Severity:** High
**Evidence:** The live action is a `128×40` filled blue button (`rgb(56, 88, 233)` background, white text). The reference is a `98×24` underlined blue text link with no fill.
**Cause:** The current screen renders a `Button` with `variant="link"`, but the WPDS Button renders it as a brand button. The reference uses `Link`.
**Impact:** The section header gains a second primary action and over-emphasizes a navigation link.
**Safe fix:** Use `Link` (or the supported link/tertiary variant) for “Open update log”; keep the log navigation behavior.
**Regression risk:** Low; header-only change.

### US-002 — Blocked-table type is inline

**Severity:** High
**Evidence:** The item subtext computes `display:inline` and renders as “Review fixture safe mode pluginPlugin”. The reference `.cell-sub` is `display:block`, 16px high under the item.
**Impact:** The type is glued to the item name; row height is 35px vs the reference 53px and the table reads as a single run-on string.
**Safe fix:** Make the type subtext `display:block` (or reuse the reference `.cell-sub` treatment).
**Regression risk:** Low; table-only CSS.

### US-003 — Blocked dates are raw timestamps

**Severity:** Medium
**Evidence:** Live: `2026-10-25 09:48:36`. Reference: `21 Oct, 22:14`.
**Impact:** The table’s most scannable column is technical and inconsistent with the product date format.
**Safe fix:** Format blocked dates with the site date/time or the reference short month/day/time format.
**Regression risk:** Low; display-only.

### US-004 — Blocked table does not preserve its minimum width

**Severity:** Medium
**Evidence:** At 560 the live table client/scroll widths are `444/444`, so columns squeeze to fit. The reference is `460/640` and scrolls inside `.table-scroll`. At 390 the live table is `274/300` (small 26px scroll); the reference is `290/640`.
**Impact:** The live table compresses the reason/date columns at tablet/mobile instead of preserving readable columns and scrolling horizontally.
**Safe fix:** Apply the reference table min-width and keep the horizontal `.table-scroll` wrapper.
**Regression risk:** Low; table-only CSS.

### US-005 — Closing note is full-width

**Severity:** Medium
**Evidence:** At 1280/960 the live note is 779/858px wide and 16px high; the reference is 541px wide and 32px high (80ch note measure). At 560/390 both are 32px high and similar width.
**Impact:** Desktop/tablet copy runs across the full page and loses the reference reading measure.
**Safe fix:** Use the shared `updatronix-surface-note`/Note measure, as done on other screens.
**Regression risk:** Low; footer-only.

### US-006 — Blocked-table item names are links

**Severity:** Medium / owner decision
**Evidence:** Live item names are blue links to `tab=logs&log=<id>`. Reference names are plain text. The extra link is useful for investigation but is not in the approved screen.
**Decision:** Keep the log link (recommended for this product) or match the reference plain text.
**Regression risk:** Low either way; navigate behavior already exists.

### US-007 — Recheck compatibility is an added section action

**Severity:** Medium / owner decision
**Evidence:** Live Protection header contains a `161×40` outline “Recheck compatibility” button; the reference has no section action. The button also increases the header/action area.
**Decision:** Keep the product action (recommended) and document the deviation, or move/remove it to match the reference.
**Regression risk:** Low if kept; it is the only trigger for the recheck endpoint.

### US-008 — Recipient detail is generic

**Severity:** Low / owner decision
**Evidence:** Live: “Goes to the site administrator.” Reference: “Goes to admin@example.com.” followed by Change recipient.
**Decision:** Show the actual configured recipient or keep the privacy-preserving generic copy.
**Regression risk:** Low; copy/recipient display.

### US-009 — Version/date data shape

**Severity:** Low (data)
**Evidence:** Live fixture rows show `v7.1.0`, `v2.0`, `v5.0.0`; the reference shows ranges (`10.0.0 → 10.1.0`). This is the available fixture payload, not a CSS defect. If both versions exist, render the range without the `v` prefix to match the reference.
**Regression risk:** Low; display mapping only.

### US-010 — Extra product states

**Severity:** Low / accepted extension
**Evidence:** Active license loading, inactive-license gate, blocked-loading/error/empty, recheck notices, and deactivation are not in the reference prototype. They use WPDS primitives and are real product states.
**Assessment:** Keep; no reference comparison exists.

### US-011 — Matching areas

- PageHeader title/description and Save changes + shortcut; shared active Save model and SaveBar.
- Protection Section, switch list, version-control lock card with Locked/Off badge, radio group, re-enable/email/abandoned switches.
- Update queue Section and queue switch.
- Recently blocked Section heading/description and four table columns; the reference table structure is present.
- Six-column page/card composition, 16px page gap, 16px card padding, and section heading typography.
- Responsive: page has no horizontal overflow; the table scrolls at 390. Table/container widths differ only by the accepted admin frame at 1280/960.
- The safe-mode off state (live default) and on state both render correctly; the reference shows on.

## Accepted deviations

- WordPress admin menu/toolbar and the resulting page/card width difference.
- The live table was populated by review fixtures; treat those rows as test data.
- The live site defaults Protect this site off; the reference shows it on. Both states were reviewed.

## Resolved decisions

1. **US-006:** kept the item → log links.
2. **US-007:** kept the Recheck compatibility action.
3. **US-008:** kept the privacy-preserving “site administrator” copy.

## Implementation result (2026-10-25)

- **US-001 fixed:** “Open update log” is now a real `Link` (98×18 at 1280, underlined brand color) with a deep link to the log destination; the filled blue button is gone.
- **US-002 fixed:** the item-type subtext is `display:block`; item and type render on separate lines and rows regain their reference height.
- **US-003 fixed:** blocked dates use `j M, H:i` via `wp_date` (`25 Oct, 10:48`); raw timestamps are no longer shown.
- **US-004 fixed:** the shield table has a 640px minimum width. At 560 the client/scroll widths are `444/640`; at 390 `274/640`; the page itself does not overflow.
- **US-005 fixed:** the closing note uses the shared 80ch measure (`541×32` at 1280/960, available width on mobile).
- **US-009 fixed:** blocked versions render ranges (`7.0.0 → 7.1.0`) without a `v` prefix when both versions exist.
- **US-006 resolved:** blocked item → log links are kept.
- **US-007 resolved:** the Recheck compatibility action is kept as a product-only action.
- **US-008 resolved:** the generic “site administrator” recipient copy is kept for privacy.

Verification: Pro lint/CSS/build pass; PHPStan is clean and 200 PHPUnit tests pass; the active Shield view is verified at 1280/960/560/390; the temporary license was removed and the site is inactive.

## Assumptions

- The reference screen is the approved visual target; product-only controls can be kept when documented.
- The table is expected to scroll horizontally on narrow screens rather than compress columns.
- The note copy is product copy; only its measure/treatment is under review.

## Safe fix taskfile

`.agents/tasks/2026-10-25-update-shield-review.md` lists US-001, US-002, US-003, US-004, and US-005. US-006, US-007, and US-008 need an owner decision.
