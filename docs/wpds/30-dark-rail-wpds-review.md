# Dark sidebar WPDS MCP review

**Date:** 2026-10-25
**Scope:** Free admin rail and mobile navigation drawer.
**Reference:** Open Design rail in `update-log.html`, `ui.jsx`, `app.css`; live WPDS Navigation/Drawer.
**Status:** Review only. No UI source changed.

## Method

- Opened reference/live at 1440×900 and 390×900.
- Measured the rail, brand, labels, links, active row, footer, and mobile drawer.
- Used WPDS MCP for `Badge`, `Button`, `Text`; `Navigation` and `Drawer` have no MCP entries.
- Captured desktop and mobile-drawer screenshots.

## What conforms

- Rail width/padding and content inset: 264px rail, 16px padding; brand, labels, links, active row, and footer align at the same relative x positions as the reference.
- Brand: 32px icon, 15px/20px 600 name, 12px/16px muted subtitle.
- Section labels: 12px/16px, 8px inline padding.
- Links: 40px desktop, 44px drawer, 24px line-height, full-width active row with a 2×32px white left indicator.
- Only Updatronix 3000 carries the Pro badge.
- Footer sits at the same y position; version is bottom-anchored.
- Mobile drawer: 320×900, 20px/600 white “Navigation” heading, close control, sections, active indicator, Pro badge, separator, and bottom version.
- `Badge`, `Button`, and `Text` match the WPDS MCP `@wordpress/ui` docs.

## Accepted deviations

- WordPress admin frame; the rail starts at x=160 instead of x=0.
- Rail background `#1e1e1e` instead of the reference `#25292b`, per owner request.
- Link base `#b4b4b4` and transparent hover to `#f0f0f0`, per owner request.
- Version content differs (`1.3.0` vs reference `1.2.0`); product version.

## Finding

### DR30-01 — Footer version typography and color

**Severity:** Low
**Regression risk:** Low

**Evidence:** The desktop footer and mobile drawer version render at 12px/16px, color `#a7adb3` (the shared muted body-sm style). The reference rail meta is 13px/24px, color `#f0f0f1`. Position and separator already match.

**Recommendation:** Style the version as the reference rail meta (13px/24px, normal foreground) in both the desktop footer and mobile drawer; keep bottom anchoring and the separator. Verify at 1440, 390, and the drawer.

## MCP coverage

- `Navigation` and `Drawer` are not exposed by the WPDS MCP. The implementation uses the local `@wordpress/ui` components; local types remain authoritative.
- `Badge`, `Button`, and `Text` are covered.

## Grilling QA

- Covered: desktop rail at 1440, mobile bar/drawer at 390, active/inactive rows, Pro badge, section labels, brand, footer, and MCP component coverage.
- Not covered in this pass: 960/760 breakpoint edges, keyboard focus trap/Escape, hover/focus colors, RTL, long labels, screen-reader reading order, and the no-license/Pro-gating states.
- No other visual difference was found.

## Files

Fix taskfile: `.agents/tasks/2026-10-25-dark-rail-wpds-review.md`.
