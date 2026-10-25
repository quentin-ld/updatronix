# Automatic updates WPDS MCP review

**Date:** 2026-10-25
**Scope:** Free Automatic updates screen (`tools.php?page=updatronix&tab=auto-updates`).
**Reference:** Open Design `auto-updates.html`, `AutoUpdates.jsx`, `app.css`, and the live WPDS components.
**Status:** Review only. No UI source changed.

## Method

- Opened reference/live at 1280×900 and 390×900, with the 760/960 shell edges checked.
- Compared page header/Save, core modes, translations, theme/plugin rows, links, switches, unavailable state, mobile spacing, and save surface.
- Used WPDS MCP for `SwitchControl`, `RadioGroupControl`, `Card`, `EmptyState`, `Link`, `Badge`, `Notice`, and `Button`.
- Checked the live DOM, computed styles, external-link pseudos, and the WordPress core auto-update options.

## What conforms

- Page title/description and one Save changes action; active Save model, no Discard.
- Core mode radio group, translations switch, theme/plugin switches, badges, row descriptions, and section composition.
- Mobile reflow: switches move below the description; Save changes spacing below the header is 16px.
- Unavailable Updatronix 3000 row uses a disabled switch and reason (accepted product state).
- “Visit plugin site” for non-WordPress.org destinations (accepted label decision).
- Site data: `auto_update_core_major`, `_minor`, and `_dev` are all `enabled`; “All releases” is the correct selected state, not a UI defect.
- `SwitchControl`, `RadioGroupControl`, `Card`, `EmptyState`, and `Link` match the WPDS MCP `@wordpress/ui` docs.

## Remaining findings

### AU28-01 — External links show two new-tab indicators

**Severity:** Medium
**Regression risk:** Low

**Evidence:** Every theme/plugin `View on WordPress.org` / `Visit plugin site` link has both:
- the WPDS `Link` inner `link-icon` whose `::after` content is `⁠↗`; and
- the dashboard-wide `a[target="_blank"]::after` external-link mask
  (`mask-image: url("data:image/svg+xml…")`).

The rendered link therefore shows the arrow plus the extra document/external icon. The reference shows one indicator. The current mobile screenshot shows both at the bottom of the Themes row.

**Recommendation:** Suppress the dashboard external-link pseudo for links inside `.updatronix-autoupdates-panel`, keeping the WPDS Link indicator (or the reverse). Verify theme, plugin-check, GitHub, and Updatronix 3000 links; keep `target="_blank"` and the accessible “opens in a new tab” label.

### AU28-02 — Constant notices are not announced

**Severity:** Medium
**Regression risk:** Low

**Evidence:** `ConstantNotices` uses WPDS `Notice.Root`, and the MCP states that `Notice` does not announce its content. The constant-warning state has no `speak()`/live region. The current detail is a separate save/notice surface.

**Recommendation:** Announce the constant-warning Notice with `speak()` or a polite live region when constants appear; no visual change.

### AU28-03 — WPDS MCP package coverage

**Severity:** Low (review limitation)
**Regression risk:** None if local package types are kept.

**Evidence:** `SwitchControl`, `RadioGroupControl`, `Card`, `EmptyState`, and `Link` are documented as `@wordpress/ui` and match the screen. The MCP `Button` entry still describes `@wordpress/components`; the screen uses `@wordpress/ui` Button variants/tones. `Text`/`Stack` are local.

**Recommendation:** Keep the pinned local `@wordpress/ui` types as authoritative and record the MCP coverage gap; do not change component APIs to satisfy older MCP docs.

## Grilling QA — states and risks

- Covered: All releases/all site core options, translations on, active theme, active plugins, unavailable Pro switch/reason, active Save, 1280 and 390, link indicators, MCP components.
- Not covered in this pass: inactive theme/plugin rows, empty lists, constants fixture, save success/error/network, many rows/long names, 760/960 behavior, keyboard traversal of every switch/radio, screen-reader announcement, and the Pro visual pass.
- The only visible regression found is AU28-01. AU28-02 is a state/latent a11y issue.
- Keep the accepted product decisions: disabled unavailable switch + reason, active Save/no Discard, “Visit plugin site” labels, and long WordPress.org descriptions.

## Recommendations / no-regression order

1. Fix AU28-01 with a scoped auto-updates pseudo-element rule; do not remove the WPDS Link accessible indicator.
2. Add AU28-02 announcement; no layout change.
3. Record AU28-03 as an MCP coverage gap; keep local packages.
4. Re-test 1280/760/390 and the theme/plugin external links; do not change the Save model, row reflow, admin frame, or unavailable state.

## Files

Fix taskfile: `.agents/tasks/2026-10-25-auto-updates-wpds-review.md`.
