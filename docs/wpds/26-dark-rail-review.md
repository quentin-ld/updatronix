# Dark navigation rail review

**Date:** 2026-10-25
**Scope:** Free plugin dark rail and mobile navigation at `tools.php?page=updatronix`.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/update-log.html`, `docs/wpds/reference/screens/src/ui.jsx`, `app.css`.
**Status:** Review and recommendations only. No UI source changed.

## Method

- Compared the reference and live screens at 1280×900, 960×900, and 390×900.
- Captured the rail, brand, groups, links, active row, Pro badge, footer, mobile bar, and open navigation drawer.
- Compared DOM geometry, computed styles, hover/focus/active states, and the legacy link-color override.
- Checked the WPDS MCP for `Badge` and `Icon`; `Drawer` is not exposed by the MCP, so its local package contract and the rendered screen are the reference.

## Findings

### DR-001 — The rail is offset by the WordPress admin chrome

**Severity:** High (structural)
**Regression risk:** High

**Evidence:** At 1280 the live rail is **x=160, y=32, 264×868**. The reference rail is **x=0, y=0, 264×900**. The WordPress admin menu is 160px wide and the toolbar is 32px high. At 960 the live mobile bar is **924px** wide versus the reference **960px** because the collapsed admin menu still occupies 36px. At 390 the live mobile bar starts at **y=46** versus **y=0**.

**Expected:** The rail owns the left edge and full viewport height in the reference.
**Current:** The plugin shell is nested inside the WordPress admin frame.

**Recommendation:** Keep the admin frame as already accepted; do not remove the WordPress menu or toolbar in this migration. If full-bleed is required, treat it as a separate opt-in “full-screen admin” project with explicit owner approval and its own navigation/escape behavior. This is the only finding with a high regression risk.

### DR-002 — Rail link rows are 4px shorter than the reference

**Severity:** High
**Regression risk:** Low

**Evidence:** Live rail links are **36px high** with `line-height: 18.2px`; reference links are **40px high** with `line-height: 24px`. The live mobile drawer links are **40px** versus reference **44px**. The mobile bar also computes `line-height: 18.2px` versus the reference `24px`.

**Recommendation:** Set the rail/mobile link line-height to the reference 24px (`line-height-md`) and verify the resulting 40px desktop rows and drawer rhythm. This is CSS-only and scoped to the rail.

### DR-003 — Active and hover text does not turn white

**Severity:** Medium
**Regression risk:** Low

**Evidence:** The live active label and icon are **rgb(240,240,241)**; the reference uses **rgb(255,255,255)**. The active background and indicator are correct. The legacy rule `#updatronix-settings .updatronix-rail a` has ID-level specificity and overrides the `.is-current` color rule in `_shell.scss`.

**Recommendation:** Scope the legacy link-color override to non-current links, or add an active/hover override with equal-or-higher specificity. Verify active, hover, visited, and focus states on desktop and in the drawer.

### DR-004 — Brand text gap is 2px instead of 4px

**Severity:** Low
**Regression risk:** Low

**Evidence:** Live brand block is **38px** high; reference is **40px**. The brand-text column uses `gap: 2px`; the reference uses `gap-xs` (4px). Name, subtitle, icon, and outer brand gap already match.

**Recommendation:** Change `.updatronix-rail-brand-text` to the 4px gap. This changes only the brand block height and the first group offset.

### DR-005 — Rail footer lacks the reference flex rhythm

**Severity:** Low
**Regression risk:** Low

**Evidence:** Live `.updatronix-rail-foot` is `display:block`, `gap:normal`, 35px high; reference is `display:flex; flex-direction:column; gap:12px`, 33px high. Only the version child is rendered, so the visible difference is small, but the contract differs and the version line inherits the 18.2px line-height.

**Recommendation:** Use the reference flex column and 12px gap; the same row/line fix from DR-002 applies to the version text.

### DR-006 — Pro badge height follows the short row

**Severity:** Low
**Regression risk:** Low

**Evidence:** The live Pro badge is **35×21**; reference **35×24**. The Pro link is 36px versus 40px. Badge background (`rgb(244,244,244)`), text (`rgb(30,30,30)`), 8px radius, 4×8 padding, font-size, and position match. The height difference is the row line-height from DR-002.

**Recommendation:** Fix DR-002 first; no separate Badge color/style change is needed.

### DR-007 — Mobile drawer surface and inset differ

**Severity:** Medium
**Regression risk:** Low

**Evidence:** At 390 the live drawer is **320px wide**, background **rgb(37,41,43)** (`$updatronix-rail-bg`), with the first link at **x=13, w=294**. The reference drawer is 320px wide, background **rgb(30,30,30)** (the shell background), with the first link at **x=9, w=302**. The live inner inset is 4px larger.

**Recommendation:** Set the drawer surface to the shell background and reduce the drawer body inset to the reference 8px. Keep the dark rail palette for links; verify with the drawer header and footer.

### DR-008 — Mobile bar/drawer inherit the same line-height and active-color issues

**Severity:** Medium
**Regression risk:** Low

**Evidence:** The live mobile bar is 56px high and uses `rgb(30,30,30)`, matching the reference height and color. Its line-height is 18.2px versus 24px. The drawer active link is white in both, but the desktop active-color override and the short row height appear in the shared rail markup.

**Recommendation:** Apply DR-002/DR-003 to the shared rail links; no separate mobile color scheme.

## Matching regions

- Rail width **264px**, padding **16px**, gap **16px**, background **rgb(37,41,43)**, zero radius.
- Brand icon/subtitle, name **15px/20px/600**, outer brand gap **8px**.
- Group label **12px/16px**, color **rgb(167,173,179)**, padding **0 8px**.
- Link padding **8×12**, icon **20px**, link gap **8px**, radius **4px**.
- Hover background `rgba(0,0,0,0.12)`, active background `rgba(0,0,0,0.18)`, 2px white active indicator, 2px focus outline.
- Group order and destinations, Pro visibility, drawer width **320px**, mobile bar height **56px** and background.
- Rail order and link labels match; the version value is data (1.3.0 live vs 1.2.0 reference).

## Recommendations and regression policy

1. Apply DR-002/DR-003 first; they explain the row, badge, footer, and drawer height/color deltas with the smallest change.
2. Apply DR-004/DR-005/DR-007 as scoped rail-only CSS.
3. Do **not** change the WordPress admin frame for DR-001 without an explicit owner decision; the high regression risk is navigation, viewport, and every admin screen.
4. Re-test 1280, 960, and 390: rail width/height, 40px links, active/hover/focus, drawer inset/background, footer, no horizontal overflow.
5. Do not change the rail width, group order, icon set, Pro visibility, or shell breakpoint in this pass.

## Files

Fix taskfile: `.agents/tasks/2026-10-25-dark-rail-review.md`.
