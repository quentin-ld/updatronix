# Update Flow review (2026-10-25)

**Status:** Review only. No UI source has been changed for these findings.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/update-flow.html`, `docs/wpds/reference/screens/src/screens/UpdateFlow.jsx`, `docs/wpds/reference/screens/src/app.css`, `docs/wpds/reference/screens/src/ui.jsx`.
**Current:** `wp-content/plugins/updatronix-pro/assets/src/js/components/UpdateFlowPage.js`, `assets/src/scss/_pro-settings.scss`.
**WPDS MCP:** SelectControl, TextareaControl, and EmptyState checked. Dialog and IconButton are not present in the MCP catalog; the current implementation still imports them from `@wordpress/ui`.

## Method

- Opened the reference screen and the live destination together with an active test license.
- Reviewed the empty state, then seeded two chains through the real flow REST endpoint to compare the populated chain, step, check, and editor states.
- Captured the page at 1280×900; measured 960/560/390. Captured the Edit chain dialog and inline rename state.
- Restored the flow chains to an empty array and removed the temporary license after the review.
- The seeded chain items are the installed site items, not the reference’s WooCommerce sample data; treat labels/versions as test data.

## Findings

### UF-001 — New chain button wraps and is too tall

**Severity:** High
**Evidence:** At 1280 the live New chain button is `105×50` and its label wraps to two lines. At 960/560/390 it is `113×40`. The reference is `113×32`, single-line.
**Cause:** The screen uses a default-size outline button without the reference’s compact size; the section header’s available width then wraps the label at desktop.
**Impact:** The primary section action is double height and reads as broken at the top of the screen.
**Safe fix:** Use the compact/32px button size and prevent the label from wrapping.
**Regression risk:** Low; header action only.

### UF-002 — Step names are not emphasized

**Severity:** High/Medium
**Evidence:** The live step-name Text computes `font-weight: 400`; the reference `.flow-step-name` is `600`.
**Impact:** Item names are not visually distinct from their type/version line; the three-step list reads flat.
**Safe fix:** Apply the emphasis weight to `.updatronix-flow-step-main` title text (or add a `.flow-step-name` equivalent).
**Regression risk:** Low; chain step typography only.

### UF-003 — Step-number typography drifts

**Severity:** Medium
**Evidence:** Live step index is `13px/18.2px`, weight 400; reference is `12px/12px`, weight 600. Both boxes are 24×24.
**Impact:** Numbers sit low and look like plain text instead of sequence markers.
**Safe fix:** Set the small font, 1 line-height, and emphasis weight.
**Regression risk:** Low.

### UF-004 — Edit chain button is not compact

**Severity:** Medium
**Evidence:** Live Edit chain button is `110×40`; reference is `110×32`.
**Impact:** Each chain card gains 8px of action height; combined with step-name/index differences the live card is 338px vs the reference 312px.
**Safe fix:** Use the compact button size for Edit chain and New chain.
**Regression risk:** Low.

### UF-005 — Dependency-check row is short

**Severity:** Medium
**Evidence:** Live check row is 42px high with `18.2px` line-height; reference is 58px with `24px` line-height. The live check is rendered inside the chain-list container; the reference renders it after the chain list.
**Impact:** The check reads as part of the last chain card rather than a separate status surface; the vertical rhythm is compressed.
**Safe fix:** Use the reference check padding/line-height and place it after the chain list inside the Section.
**Regression risk:** Low; status row only.

### UF-006 — Closing note is full-width

**Severity:** Medium
**Evidence:** Live note is 779×16 at 1280; reference is 541×32 (80ch measure).
**Impact:** The one-save explanation runs across the whole page and loses the reference reading measure.
**Safe fix:** Apply the shared surface-note/80ch measure.
**Regression risk:** Low; footer only.

### UF-007 — Chain card height delta

**Severity:** Low (partly accepted)
**Evidence:** Live chain is 729×338; reference is 904×312. The page is narrower by the accepted admin frame (779 vs 954), but the compact New/Edit buttons, step-name weight, and step-index line-height account for part of the 26px delta.
**Assessment:** Recheck after UF-001/002/003/004; the remaining width-driven wrapping is accepted.

### UF-008 — Editor dialog height and controls

**Severity:** Medium
**Evidence:** Both dialogs are 560px wide (the current default matches the reference medium width), but the live dialog is 582px high vs 548px. The live Add step button is default size; the reference editor uses compact controls.
**Impact:** The editor is taller and the step-editor footer/actions sit lower; at 390 it is more likely to scroll.
**Safe fix:** Use compact Add step/editor action sizing and recheck the dialog height at 1280/390.
**Regression risk:** Low; editor only.

### UF-009 — Inline rename actions differ

**Severity:** Medium
**Evidence:** The live rename row uses text “Save name” and “Cancel” buttons; the reference uses a compact Save button plus a close `IconButton`. The live row was 464px wide and expanded the chain head.
**Impact:** Rename feels heavier than the reference and takes more horizontal/vertical space.
**Safe fix:** Use the reference compact Save + close icon controls; keep Enter/Escape behavior.
**Regression risk:** Low; rename-only interaction.

### UF-010 — Empty state is an extra product state

**Severity:** Low / accepted
**Evidence:** The live initial state showed “No chains yet” with a New chain action. The reference screen is seeded with two sample chains, so it has no empty state.
**Assessment:** Keep the empty state. It is a real product state using EmptyState.Root/Icon/Title/Description/Actions; no reference comparison exists for it.

### UF-011 — License/loading/error states are extra

**Severity:** Low / accepted
**Evidence:** Inactive-license gate, license loading, chain loading, retry error, and save notices are not in the reference prototype. They use WPDS primitives and are required product states.

### UF-012 — Matching areas

- PageHeader title/description, Save changes + shortcut, shared active Save model/SaveBar.
- Chains card/header, description, New chain entry point.
- Two chain cards with name, description, step count, rename/delete/switch tools.
- Ordered step rows, step type/version, move up/down, remove, disabled first/last/remove-at-two-step states.
- Dependency check label, chain count copy, and No loops/Loop found Badge.
- Save-time loop guard and move-time loop rejection.
- Editor width 560px, fields/labels, SelectControl/TextareaControl, step rows, Add step, Cancel/Save chain, loop conflict, AlertDialog deletion.
- At 960/560/390 the page has no horizontal overflow; chain widths follow the admin frame (808/444/274 vs 844/460/290).

## Accepted deviations

- WordPress admin menu/toolbar and narrower page/card width.
- Installed-item labels/versions instead of the reference’s WooCommerce sample data.
- Empty and inactive-license product states.
- Chain card height delta attributable to the admin width plus the typography/button findings above.

## Open decisions

- None requiring product direction. UF-001 through UF-009 are screen-local safe fixes; UF-010/011 are accepted product states.
- The review-seeded chains were removed; the site’s flow list is empty again.

## Safe fix taskfile

`.agents/tasks/2026-10-25-update-flow-review.md` lists UF-001 through UF-009.
