# Schedule — design-conformance review

**Date:** 2026-10-25
**Scope:** Free > Schedule only. Review only; no UI source changed.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/schedule.html`, `docs/wpds/reference/screens/src/screens/Schedule.jsx`, `app.css`, `ui.jsx`.
**Current:** `tools.php?page=updatronix&tab=schedule`, `SchedulePanel.js`, `_shell.scss`, shared `PageHeader`/`Section`/`SaveBar`.

## Method

- Opened the reference and live screens at 1280×800, 800×900, 560×900, and 390×844; captured the desktop/mobile views and measured the split, cards, table, note, and header.
- Read the reference Schedule composition and the current panel, including `scheduleMeta`, date formatting, `ConstantNotices`, and the shared save model.
- Queried WPDS MCP for SelectControl, InputControl, Button, Notice, Text, Stack, and Card.
- Verified the `Run a check now` interaction and the save/save-bar state.

## Region map and computed baseline

| Region | Reference | Current | Result |
| --- | --- | --- | --- |
| Page/header | Page header + meta + Run/Save | Page header + meta + Run/Save | Match |
| Split at 1280 | 2 columns, `align-items: stretch`, both cards 418px | 2 columns, `align-items: start`, cards 458px and 292px | Differs |
| Split at 800 | 2 columns (`359px 359px`) | 1 column (`698px`) | Differs |
| Split stack breakpoint | 760px | 900px | Differs |
| Table | th 4/8px, td 8px | th 4/8px, td 8px | Match |
| Final note | `.surface-note` width 541px | full-width Text 779px | Differs |
| Save | Active Save, no Discard, shortcut visible | Active Save, no Discard, shortcut visible | Match |
| Next check meta | `24 Oct, 03:00` | `26 October 2026 0h00` | Data/locale format |
| Next run title | `24 October 2026 at 03:00` | `26 October 2026 0h00` | Data/locale format |

## Findings

### SC-001 — Frequency has an extra description

- **Severity:** Medium
- **Reference:** The Frequency select has only its label.
- **Current:** It adds “Sets how often WordPress checks for updates and runs automatic updates.” below the field.
- **Impact:** The Update checks card grows; together with SC-002 it makes the two cards unequal. The extra copy is not in the reference.
- **Regression risk:** Copy-only; low. Owner may prefer to keep the help text.

### SC-002 — Split stacks too early and does not stretch cards

- **Severity:** High
- **Reference:** `.split` is two columns until 760px and uses `align-items: stretch`; both cards are 418px at 1280 and remain side by side at 800px.
- **Current:** `.updatronix-split` stacks at 900px and uses `align-items: start`; at 800px it is one column, and at 1280 the cards are 458px/292px.
- **Impact:** Tablet layout differs, and the Next run card does not match the Update checks card height on desktop.
- **Regression risk:** Shared shell CSS (`_shell.scss`); safe for Schedule, but other `.updatronix-split` consumers must be checked.

### SC-003 — Final note is full-width

- **Severity:** Medium
- **Reference:** The closing note uses `.surface-note` at 541px at 1280.
- **Current:** The closing note is a full-width `Text` (779px).
- **Impact:** Longer line length than the reference and different visual weight.
- **Regression risk:** Screen-local class/width; low.

### SC-004 — Next-check date/time format differs

- **Severity:** Medium (data/locale decision)
- **Reference:** Header meta `Next check: 24 Oct, 03:00`; Next run `24 October 2026 at 03:00`.
- **Current:** Both use `26 October 2026 0h00`. The source uses WordPress `date_format` + `time_format`; this site has `j F Y` and `G\hi`, so the long date and `0h00` are locale-driven.
- **Impact:** The output is consistent with WordPress settings but not with the reference’s compact English format.
- **Regression risk:** Changing formatting would affect the header meta and Next run only; owner decision whether to respect site settings or the reference.

### SC-005 — Run-check feedback is inline and changes the button

- **Severity:** Medium
- **Reference:** “Run a check now” stays unchanged; feedback is a transient snackbar (“Update check started…”).
- **Current:** While running, the button is disabled and reads “Checking…”; after the request it renders a persistent inline Notice titled “Update check”.
- **Evidence:** Live click produced the inline notice “Update check started. This can take a minute.”; the reference uses `notify`/snackbar.
- **Impact:** Different interaction feedback, an extra persistent surface, and a different button state.
- **Regression risk:** Interaction/state decision; may affect how other screens report background actions.

### SC-006 — Frequency option set is broader than the reference

- **Severity:** Low (product/feature decision)
- **Reference:** Daily, Twice daily, Hourly.
- **Current:** Adds Weekly and WordPress default.
- **Impact:** The reference does not show these options. They may be intentional product capabilities; removing them would be a behavior regression.
- **Regression risk:** Product/API decision.

### SC-007 — Preferred time data differs

- **Severity:** Low (data)
- **Reference:** 03:00.
- **Current:** 01:00 (saved site schedule; the current value is also added to the options).
- **Impact:** Expected data difference, not a UI defect.

### SC-008 — Schedule constant notices are an extra state

- **Severity:** Low
- **Reference:** No constant-notice state is mocked.
- **Current:** `ConstantNotices` renders dismissible wp-config warnings inside Update checks.
- **Impact:** Functional extension; no baseline constant is defined, so it is not visible in the default screen.
- **Regression risk:** Low; state-only.

### SC-009 — Matching areas

- Page header title/description, Run/Save order, meta badge, save bar, header offsets, table headers, table cells, Cron notice, hold copy, and section copy match the reference.
- The final note text matches; only its width differs (SC-003).
- The table cells use the same 4/8px header and 8px body padding; the width difference is the accepted admin-frame/page-width deviation.

## Accepted deviations / no action

- WordPress admin menu and toolbar remain around the plugin shell; page widths and the fixed save-bar offset follow from that owner decision.
- The current preferred time, next-check timestamp, and data values are site data.
- The shared save model is already aligned (active Save, no Discard, hidden ≤560 shortcut).

## Resolved decisions

1. **Frequency options — keep all five:** Daily, Twice daily, Hourly, Weekly, and WordPress default remain.
2. **Next-check format — keep the WordPress site format:** respect `date_format`/`time_format`.
3. **Run-check feedback — match the reference:** snackbar notice, unchanged active button, no persistent inline Notice.
4. **Frequency description — remove it:** the extra help copy is gone.

## Implementation result (2026-10-25)

Implemented and verified:

- Split uses `align-items: stretch`, stays two columns through 760px, and uses a 12px gap at ≤560px.
- Both split cards are 418px at 1280px; the closing note is 541px.
- Frequency has no extra description.
- Run a check dispatches an info/error snackbar through the shared SnackbarHost; the button remains “Run a check now” and active; no inline Notice is rendered.
- Frequency options and the WordPress date format are unchanged.
- Schedule verified at 1280/800/760/560/390; the shared Settings split verified at 800/560 with no overflow.
- Free lint/CSS/token checks, 15 JS tests, build, PHPStan, and 213 PHPUnit tests pass.

Remaining gaps: no dedicated PHP/JS tests were added for the visual/snackbar changes; full screen-reader and cross-browser passes remain separate verification work.
