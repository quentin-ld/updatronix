# Support review (2026-10-25)

**Status:** Review only. No UI source has been changed for these findings.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/support.html`, `docs/wpds/reference/screens/src/screens/Support.jsx`, `docs/wpds/reference/screens/src/app.css`, `docs/wpds/reference/screens/src/ui.jsx`.
**Current:** `assets/src/js/pages/SupportPage.js`, `assets/src/scss/shell/_shell.scss`.
**WPDS MCP:** Card, Icon, and Text docs were checked. The MCP has no `ButtonLink` entry; the current and reference screens both import it from `@wordpress/ui`.

## Method

- Opened the Open Design Support screen and the live Support destination together.
- Captured both at 1280×900 and 960×900; measured 800, 560, and 390 widths.
- Compared DOM/computed styles for the layout, both cards, step list, forum icon, kicker, title, CTA, docs link, and privacy footer.
- Checked WPDS Card/Icon/Text APIs and the reference CSS token choices.
- No data or API state exists on this screen; it is static content.

## Findings

### SUP-001 — Support forum CTA text is invisible

**Severity:** High (primary action looks empty)
**Evidence:** At 1280 and 960 the CTA has `color: rgb(56, 88, 233)` and `background-color: rgb(56, 88, 233)`, so the text and new-tab indicator disappear into the button. The accessibility tree still contains “Open the support forum”, which confirms the content exists.
**Reference:** The CTA uses the same blue background with `color: rgb(239, 240, 242)` and a visible arrow.
**Impact:** The screen’s only primary action appears as a blank blue rectangle at every width.
**Likely cause:** The dashboard-wide anchor color reaches the ButtonLink; the local `.updatronix-support-forum-cta` rule only sets `justify-content` and `width`.
**Safe fix:** Scope the inverse foreground color to `.updatronix-support-forum-cta` (and its icon/new-tab indicator), without changing global dashboard link colors.
**Regression risk:** Low; Support-only selector.

### SUP-002 — Forum icon is neutral instead of brand

**Severity:** Medium
**Evidence:** `.updatronix-support-icon` computes 40×40 with `color: rgb(30, 30, 30)` and `background-color: rgb(244, 244, 244)`.
**Reference:** `.support-icon` is 40×40 with the brand foreground and a brand-weak background (the reference’s token resolves to a blue icon treatment).
**Impact:** The forum card’s leading icon reads as a disabled/neutral control instead of the reference brand marker.
**Safe fix:** Use the brand foreground and brand-weak background tokens on `.updatronix-support-icon`; keep the 40px box and radius.
**Regression risk:** Low; Support-specific class.

### SUP-003 — Support grid stacks at 960 instead of 900

**Severity:** Medium
**Evidence:** At 960 the live `.updatronix-support-layout` is one `858px` column. The reference remains two columns at 960 (`561.8px 326.2px`). Both are one column at 800.
**Cause:** The live media rule is `max-width: 960px`; the reference `.support-layout` stacks at `max-width: 900px`.
**Impact:** At 901–960 the “Before you post” card takes the full width and the forum card moves below it; the reference keeps the side-by-side composition.
**Safe fix:** Move the Support layout stack breakpoint to `900px`; check 901 and 900 explicitly.
**Regression risk:** Low; Support-specific layout.

### SUP-004 — Step-number typography and mobile size drift

**Severity:** Medium/Low
**Evidence:** At 1280 the live step number is `13px/18.2px`; the reference is `12px/12px`. At 560 and 390 the live number stays `32×32`; the reference switches to `24×24` (and its step column to `size-sm`).
**Impact:** The number sits slightly low in its circle and is oversized on phones; the 32px circle dominates the step row at 390.
**Safe fix:** Set the step-number font to the small size with a 1 line-height; add the ≤560 size-sm circle and matching step column.
**Regression risk:** Low; Support-only selectors.

### SUP-005 — Card-height and wrapping deltas (accepted)

**Severity:** Low — accepted admin-frame consequence
**Evidence:** At 1280 the live section and forum card are both 365px high; the reference is 351px. The live step description wraps to two lines (`h: 32px`) where the reference is one (`h: 16px`). Live page width is 789px vs the reference 964px; card widths are 489/284 vs 600/348.
**Assessment:** The narrower WordPress admin frame causes the extra wrapping. The card grid, ratio, 16px gap, and stretch alignment match. No action unless the admin-frame decision is revisited.

### SUP-006 — Matching areas

- Page header title/description and `Support` hierarchy.
- `1.55fr / 0.9fr` layout ratio, 16px gap, and stretch alignment.
- Section/Card composition, four-step list, step title `13px/20px`, description `12px/16px`, card padding, dividers, and number circle size at desktop.
- Forum kicker `12px/16px`, title `20px/32px`, description, CTA height, docs link, and privacy divider/padding.
- `Card`, `Icon`, and `Text` props match the WPDS MCP docs; `Read the documentation` routes through the shell.
- No horizontal overflow at 800, 560, or 390 (`scrollWidth === innerWidth`).

## Assumptions

- The 1280 reference is the source of truth; the live admin menu/toolbar and narrower page are accepted.
- The blank CTA and neutral icon are implementation defects, not intentional theme decisions.
- `ButtonLink` is a valid `@wordpress/ui` import even though it is absent from the MCP component list.

## Open questions

- None. All findings are screen-local, safe fixes; no regression-risk change needs owner approval.

## Safe fix taskfile

`.agents/tasks/2026-10-25-support-review.md` lists SUP-001 through SUP-004. SUP-005 is accepted.
