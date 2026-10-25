# Auto updates — design-conformance review

**Date:** 2026-10-25
**Scope:** Free > Auto updates only. Review only; no UI source changed.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/auto-updates.html`, `docs/wpds/reference/screens/src/screens/AutoUpdates.jsx`, `app.css`, `ui.jsx`.
**Current:** `tools.php?page=updatronix&tab=auto-updates`, `AutoUpdatesPanel.js`, `ConstantNotices.js`, `useAutoUpdates.js`, `_auto-updates.scss`, shared `PageHeader`/`Section`/`SaveBar`.

## Method

- Compared the rendered screens at 1440×900, 1280×800, 1024×768, 960×900, 768×1024, 560×900, and 390×844, plus the 980/960, 760/720, and 560 edges.
- Read the reference JSX/CSS and the current component, hook, styles, and shared shell primitives.
- Queried the WPDS MCP for RadioGroupControl, SwitchControl, Badge, Link, Text, Notice, Spinner, Button, Card, and design tokens.
- Recorded computed geometry and tested the dirty/save-bar state.
- Snapshot: `ddev snapshot --name=wpds-auto-updates-review-baseline`.

## Baseline data (not design defects)

The live site is a real data set, not the reference mock:

- Core: `auto_update_core_major`, `auto_update_core_minor`, and `auto_update_core_dev` are `enabled`, so the current **All releases** selection is correct for this site. The reference shows **Minor releases only** because it is a static mock.
- Themes: one active theme (Twenty Twenty-Five 1.5). Plugins: Plugin Check 2.1.0, Updatronix 1.3.0, Updatronix 3000 0.7.0.
- The live Twenty Twenty-Five description is a full WordPress.org paragraph; the reference uses a one-line mock description. This explains the live row being ~150px versus the reference ~109px at 1280px.
- The Updatronix 3000 plugin has no WordPress.org update data, so the live row has no auto-update switch.

These data differences are excluded from the UI-defect list unless they expose a real state/design gap below.

## Region map and computed baseline

| Region | Reference | Current | Result |
| --- | --- | --- | --- |
| Page padding | `32px 20px 64px` at desktop | `32px 20px 64px` | Match |
| Page gap | `24px` | `24px` | Match |
| Header height | 108px at 960/1280 | 108px at 960/1280 | Match |
| Section heading | 145×40, gap 4px | 145×40, gap 4px | Match |
| Section/card | WPDS Card | WPDS Card | Match |
| Row desktop | grid `40px 1fr auto`, padding-bottom 16px, gap 12px | flex row, gap 12px, no bottom padding | Differs |
| Switch column desktop | 44–67px, auto height | 150px, stretched to row height | Differs |
| Switch column ≤760px | Full-width row under the body; `dir: row`, width 604/644, height 32, padding-top 8px | Right column, width 73px, height 166–182px, `dir: column` | Differs |
| Note | `.surface-note`: transparent, no padding | `.updatronix-surface-note`: computed transparent, no padding at 560 | Match (an earlier suspicion was cleared) |
| Save bar | Fixed top, title + Save changes + shortcut | Fixed top, title + Discard + Save changes; shortcut hidden at 560 | Differs (Discard/position) |
| Horizontal overflow | None | None at all tested widths | Match |

The live page is narrower at 980/960 because the WordPress admin menu is not part of the reference. This is the accepted admin-frame deviation from Update logs, not a new Auto updates defect. The live save bar is lower by the same frame offset.

## Findings

### AU-001 — Header Save is disabled on a clean screen

- **Severity:** High
- **Reference:** “Save changes” is active and branded whenever the screen is shown.
- **Current:** `AutoUpdatesPanel` passes `disabled={ ! isDirty || busy }` to `SaveButton`; on load the button is gray and disabled, including at 560/390.
- **Evidence:** 1280/1024/560/390 screenshots; `AutoUpdatesPanel.js` `handleSaveAction`; `SaveBar.js` `SaveButton`.
- **Impact:** The primary action looks unavailable, and the screen differs from the reference before any edit.
- **Regression risk:** Shared save model across Auto updates, Schedule, Settings, and Pro panels. This is an owner decision, not a safe local fix.

### AU-002 — Save shortcut remains visible at 560/390

- **Severity:** Medium
- **Reference:** `.save-shortcut` is `display: none` at ≤560; the reference header button shows only “Save changes”.
- **Current:** `.updatronix-save-button-shortcut` remains `display: block`, 32×20, at 560; the header button reads “Save changes Ctrl+S”. The save-bar shortcut is correctly hidden.
- **Evidence:** Computed at 560: current shortcut `display: block`; reference `.save-shortcut` `display: none`.
- **Impact:** The mobile header is wider/cramped compared with the reference.
- **Regression risk:** Shared `SaveButton` CSS used by every saved screen. Low-risk visual fix, but shared.

### AU-003 — Save bar adds Discard and is offset by the admin frame

- **Severity:** Medium
- **Reference:** Fixed top bar contains the surface title and “Save changes” with shortcut.
- **Current:** Dirty bar contains `Auto updates / Discard / Save changes`; it is fixed at `y=110` at 560 while the reference bar is at `y=64`. The offset is the accepted WordPress toolbar + plugin mobile bar, not a new defect; the extra **Discard** action is an Auto updates/SaveBar difference.
- **Evidence:** Dirty-state computed bar text and rect; `SaveBar.js` renders Discard when `onDiscard` is supplied.
- **Impact:** Extra secondary action and a different bar composition than the reference.
- **Regression risk:** Shared SaveBar. Owner decision whether Discard is an intentional product addition.

### AU-004 — Auto-update switch does not move below the row on tablet/mobile

- **Severity:** High
- **Reference:** At ≤760, `.item-row` becomes a two-column grid (`40px 1fr`); `.item-switch` becomes a full-width row under the body (`dir: row`, width 604/644, height 32, padding-top 8px), with the label and switch laid out horizontally.
- **Current:** `.updatronix-auto-item` stays a flex row; `.updatronix-auto-item-switch` stays a right column (`width: 73px`, `height: 166–182px`, `dir: column`) at 760/720 and 560/390.
- **Evidence:** Computed at 760 and 720; 560/390 screenshots.
- **Impact:** The core row layout does not reflow to the reference on tablet/mobile; the switch column is cramped beside a long description.
- **Regression risk:** Screen-local row CSS; safe to fix in a taskfile. The shared shell is not involved.

### AU-005 — Desktop switch column stretches to the row height

- **Severity:** Medium
- **Reference:** `.item-switch` is 44–67px high at desktop; the label and switch sit at the top of their own auto-height column.
- **Current:** `.updatronix-auto-item-switch` is 150px high at 1280 because it is a stretched flex item alongside a long description.
- **Evidence:** Computed row/switch geometry at 1280; screenshots.
- **Impact:** Vertical alignment and future row backgrounds/borders will treat the switch column as the full row, not its content.
- **Regression risk:** Screen-local CSS; safe.

### AU-006 — “View on WordPress.org” points at non-WordPress.org sites

- **Severity:** Medium
- **Reference:** The link label matches the WordPress.org destination.
- **Current:** Plugin Check links to `github.com/WordPress/plugin-check`; Updatronix 3000 links to `holdmywp.com`; both say “View on WordPress.org”.
- **Evidence:** Live accessibility snapshot and DOM.
- **Impact:** Misleading link text and external destination; content/UX defect.
- **Regression risk:** Copy/label logic only; safe. Decide whether to use “Visit plugin site” for non-WordPress.org URLs or derive the label from the host.

### AU-007 — Unavailable auto-update state replaces the switch with text

- **Severity:** Medium
- **Reference:** Every extension row has the “Auto-updates” label and a switch.
- **Current:** Updatronix 3000 is `update_data_available: false`; the row shows the label and the text “Unavailable” instead of a disabled switch, with no reason.
- **Evidence:** Live snapshot; `ExtensionRow` unavailable branch.
- **Impact:** The row control disappears and the state is not discoverable; it does not match the reference row structure.
- **Regression risk:** State design/product decision. A disabled switch plus reason or a hide-the-column rule needs owner approval.

### AU-008 — Constant notices use the legacy components Notice

- **Severity:** Medium
- **Reference:** WPDS Notice namespace (`Notice.Root`, `Notice.Title`, `Notice.Description`) for noticelike surfaces.
- **Current:** `ConstantNotices.js` imports `Notice` from `@wordpress/components`; the baseline has no constants, so this state was not visually exercised.
- **Evidence:** Source and WPDS MCP. A temporary `AUTOMATIC_UPDATER_DISABLED` / `WP_AUTO_UPDATE_CORE` mu-plugin rendered legacy “Warning notice” blocks and disabled controls; the state is now visually exercised.
- **Impact:** A wp-config constant state renders with the legacy package instead of the migrated WPDS look.
- **Regression risk:** State-only code, but needs a constants fixture and a decision on dismissible/locking semantics.

### AU-011 — Constant warnings repeat in every section

- **Severity:** Medium
- **Reference:** No wp-config constant state is mocked.
- **Current:** With `AUTOMATIC_UPDATER_DISABLED` defined, the same warning is rendered in Core update mode, Translations, Themes, and Plugins (four copies), while the global `dismissibleOnly` pass suppresses all locking constants.
- **Evidence:** Temporary constants fixture; accessibility snapshot of the warning text and disabled controls.
- **Impact:** Repetitive warning stack and duplicated reading order; the user sees the same message four times.
- **Regression risk:** State design. Prefer one global notice with the affected sections listed, or one warning per distinct constant with section links.

### AU-009 — Empty extension lists have no empty state

- **Severity:** Low
- **Reference:** Always populated with mock themes/plugins.
- **Current:** The Themes/Plugins Sections render an empty `.updatronix-auto-list` if the REST data has no rows; there is no EmptyState or explanatory copy.
- **Evidence:** `AutoUpdatesPanel.js` maps `data.themes`/`data.plugins` directly.
- **Impact:** A site with no installed themes/plugins would show blank cards.
- **Regression risk:** Low; screen-local. Empty lists are unreachable with the current site without hiding the Updatronix plugins and theme.

### AU-010 — Loading/error/retry and constant notices are unmocked extensions

- **Severity:** Low
- **Current:** Loading uses a Spinner + text; fetch errors use WPDS Notice + Retry; constant notices add another state. None of these are in the reference.
- **Evidence:** `AutoUpdatesPanel.js`, `ConstantNotices.js`.
- **Impact:** These are functional additions, not parity defects, but they need a design decision and screenshots before release.
- **Regression risk:** Low/medium depending on whether the notice/error surfaces are standardized across screens.

## Fixture and state coverage

| State | Covered | Note |
| --- | --- | --- |
| Core modes | Partially | UI exposes minor/all/disabled; site options enable all. Not saved during review. |
| Translations on/off | Yes | Toggled in the dirty-state test; reloaded without saving. |
| Plugin/theme on/off | Not yet | Needs a temporary inactive extension or a save/revert pass. |
| Active badge | Yes | Twenty Twenty-Five and plugins. |
| Inactive badge | Yes | Temporary Twenty Twenty-Four/Classic Editor fixtures rendered Inactive badges and off switches. |
| `update_data_available: false` | Yes | Updatronix 3000. |
| Locked/constant rows | Yes | Temporary `AUTOMATIC_UPDATER_DISABLED` / `WP_AUTO_UPDATE_CORE` fixture: warnings rendered, controls disabled, duplicate warning per section. |
| Missing description/URL/icon | Partially | Plugin Check/Updatronix 3000 have non-WP.org URLs; no missing icon or empty description fixture. |
| Long names/versions | Partially | Live theme/plugin descriptions are long; no long extension name or version fixture. |
| Empty lists | No | Unreachable without hiding core plugins/theme. |
| Many rows | Partially | Six rows with the two temporary fixtures; not a large-list stress test. |
| Clean/dirty/save bar | Yes | Clean button disabled; dirty toggle enabled the header Save and opened the fixed bar. |
| Saving/saved/error/retry | Partially | Save success verified with the translation switch: save notice appeared, the option became false, then a second save restored it to true. Error/retry not simulated. |
| Network failure | No | Not simulated. |

## Save-state pass (pre-fix baseline)

- Clean load: the header Save is disabled; the fixed bar is absent.
- Dirty: turning the translation switch off enabled the header Save and opened the fixed bar with Discard and Save.
- Save success: clicking Save produced “Auto-update settings saved.”, disabled Save, and persisted `auto_update_translations=false`.
- Restore: turning the switch back on and saving restored `auto_update_translations=true` and returned the screen to a clean state.
- Not simulated: save-error, stale-data/retry, dismissed constants, network failure. These remain an explicit verification gap.

## Cleanup

- Removed the temporary `classic-editor` plugin and `twentytwentyfour` theme.
- Removed the temporary `wp-content/mu-plugins/updatronix-review-constants.php` file.
- Verified baseline: plugins `plugin-check`, `updatronix`, `updatronix-pro`; theme `twentytwentyfive`.
- Verified `auto_update_plugins` and `auto_update_themes` are back to their baseline entries; `AUTOMATIC_UPDATER_DISABLED` is absent.
- `updatronix_settings.auto_update_translations` is restored to `true`.
- Snapshot `wpds-auto-updates-review-baseline` remains available; restore with `ddev snapshot restore wpds-auto-updates-review-baseline`.

## Accepted deviations / no action

- WordPress admin menu and toolbar remain around the plugin shell; page widths and the fixed save-bar offset follow from that owner decision.
- The note matches the reference after computed comparison; no change is required.
- The live core mode, theme count, plugin count, versions, and descriptions are data, not reference defects.

## Resolved owner decisions

1. **Save model — match the reference:** active Save and no Discard in the fixed bar. Implemented in the shared SaveBar/SaveButton and all Free/Pro callers.
2. **Unavailable rows — disabled switch plus reason.** Implemented in `AutoUpdatesPanel`.
3. **Link labels — Visit theme/plugin site** for non-WordPress.org destinations. Implemented.
4. **Mobile shortcut — hidden at ≤560.** Implemented in `_shell.scss`.

## Implementation result (2026-10-25)

T1–T5 and the shared save-model decision are implemented. Verification: Free lint/CSS/token checks, 15 JS tests, build, and 213 PHPUnit tests; Pro lint/build and 200 PHPUnit tests; browser checks at 1280/760/560 (active Save, no Discard, row reflow, hidden shortcut, disabled unavailable switch with reason, corrected link labels); a temporary constants fixture rendered one global WPDS warning per constant; fixtures and options were cleaned up.

Remaining explicit gaps: save-error/network/stale/retry states, empty theme/plugin lists (unreachable on this site), many-row stress, full screen-reader pass, and a Pro visual pass. The earlier “pre-fix baseline” save-state bullets describe the review baseline, not the final build.
