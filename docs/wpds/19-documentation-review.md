# Documentation — design-conformance review

**Date:** 2026-10-25
**Scope:** Free > Documentation only. Review only; no UI source changed.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/documentation.html`, `docs/wpds/reference/screens/src/screens/Documentation.jsx`, `app.css`, `ui.jsx`.
**Current:** `tools.php?page=updatronix&tab=documentation`, `DocumentationPage.js`, `_shell.scss`.

## Method

- Opened both screens at 1280×900 and captured the full card grid/note.
- Measured the grid, card, title/kicker/description/read-link typography, note, and responsive columns at 1280/960/800/560/390.
- Read the reference and current card composition; Card/Text/Link/Icon usage matches the WPDS APIs, with the title/kicker/read differences coming from local CSS.

## Computed baseline

| Region | Reference | Current | Result |
| --- | --- | --- | --- |
| Grid at 1280 | 3 columns, 16px gap, 964px | 3 columns, 16px gap, 789px | Match structure; width delta from admin frame |
| Card content | 24px padding, 24px gap | 24px padding, 24px gap | Match |
| Card top | 40px | 40px | Match |
| Card title | 20px / 32px line, heading font | 15px / 20px line, body font | Differs |
| Kicker | 16px high | 54px high (`flex: 1`) | Differs |
| Description | 13px / 20px | 13px / 20px | Match |
| Read guide | 12px / 24px line, no wrap | 12px / 18.2px line | Differs |
| Card height | 303px | 315px | Differs (typography + narrower width) |
| Note | 541×16, 16px line | 541×16, 16px line | Match |
| Grid at 960/800 | 2 columns | 1 column | Differs |
| Grid at 560/390 | 1 column | 1 column, no overflow | Match |

## Findings

### DOC-001 — Card titles use body typography

- **Severity:** High
- **Reference:** `.doc-title` uses the heading font, `font-size-xl`, `line-height-xl`, margin 0; computed 20px/32px.
- **Current:** `.updatronix-doc-title` only sets `display:block`, `margin-bottom:4px`, and emphasis weight; computed 15px/20px in the body font.
- **Impact:** Card titles are visibly smaller and less prominent than the reference; the first card title sits lower due the extra margin.
- **Regression risk:** Screen-local CSS (`_shell.scss`); low.

### DOC-002 — Kicker is stretched by `flex: 1`

- **Severity:** High
- **Reference:** Kicker is a normal 16px line box.
- **Current:** `.updatronix-doc-kicker` has `flex: 1`, producing a 54px box in the current card and creating the large gap between icon and title.
- **Impact:** The card copy vertical rhythm is wrong and contributes to the 315px vs 303px card height.
- **Regression risk:** `.updatronix-doc-kicker` is Documentation-specific; low. If the flex was intended for another card, scope it.

### DOC-003 — Read-guide line box is smaller

- **Severity:** Medium
- **Reference:** Read guide line-height 24px; card footer 37px.
- **Current:** Read guide line-height 18.2px; footer 31px. The reference also uses `white-space: nowrap`.
- **Impact:** The footer/read affordance is visually smaller and less aligned with the reference.
- **Regression risk:** Documentation-specific; low.

### DOC-004 — Grid has no 2-column tablet state and stacks too early

- **Severity:** High
- **Reference:** 3 columns above 1200px, 2 columns from 1200 to 760px, 1 column at ≤760px.
- **Current:** 3 columns above 960px, then 1 column at ≤960px; at 960/800 current is one column while the reference is two.
- **Impact:** The Documentation grid loses the reference tablet composition and gives cards too much width at 800–960.
- **Regression risk:** `.updatronix-doc-grid` is Documentation-specific; low. Add the 1200px two-column state and move the one-column breakpoint to 760px.

### DOC-005 — Card height delta

- **Severity:** Low
- **Current:** 315px vs reference 303px at 1280; the narrower admin-frame width wraps descriptions more, while DOC-001/DOC-002 also change the internal rhythm.
- **Impact:** Recheck after the typography fixes; part of the delta is the accepted admin frame.

### DOC-006 — Matching areas

- Page header title/description/meta “3 guides”, three-column composition at 1280, 16px gap, 24px card padding/content gap, icon/external icon placement, kicker copy, descriptions, Card.Title heading semantics, card link/aria-label/new-tab target, Read guide arrow, Pro icon treatment, closing note width/line-height/link, and Support navigation match.
- No horizontal overflow at 560/390.
- Card/Text/Link/Icon components match WPDS MCP documentation; the mismatches are local typography and grid rules.

## Accepted deviations / no action

- WordPress admin menu/toolbar and the resulting page/card width delta.
- The card height difference attributable to the narrower admin frame/wrapping.
- No data/API state: this is a static screen.
- No owner/product decisions are required for the found issues; all are local CSS fixes.

## Implementation result (2026-10-25)

The safe tasks are implemented:

- Card title now uses the heading font at 20px/32px with zero margin.
- Kicker is a 16px line (`flex: none`).
- Read guide uses the 24px line and no-wrap.
- Grid is 3 columns above 1200px, 2 columns at ≤1200px, and 1 column at ≤760px.
- Verified at 1280/1200/960/800/760/560/390; no horizontal overflow.
- Card height remains 341px vs the reference 303px because the narrower admin frame wraps the title/description; this is recorded as an accepted frame delta.
- Free lint/CSS/token checks, 15 JS tests, build, PHPStan, and 213 PHPUnit tests pass.

Remaining gaps: no dedicated visual regression test; full screen-reader and cross-browser passes remain separate work.
