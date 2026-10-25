# Settings — design-conformance review

**Date:** 2026-10-25
**Scope:** Free > Settings only. Review only; no UI source changed.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/settings.html`, `docs/wpds/reference/screens/src/screens/Settings.jsx`, `app.css`, `ui.jsx`.
**Current:** `tools.php?page=updatronix&tab=settings`, `SettingsPanel.js`, `_shell.scss`, shared `PageHeader`/`Section`/`SaveBar`.

## Method

- Opened both screens at 1280×900; captured the top and scrolled Data/notes regions.
- Compared the Logging/Email split, card heights, recipient field, notification state, warning Notice, Data section, closing notes, and save bar.
- Queried WPDS MCP for CheckboxControl, Fieldset, SearchableChipSelectControl, SwitchControl, and InputControl.
- Temporarily enabled email notifications (`notify_enabled=true`, core/plugin events, `admin@example.com`) to compare the reference state, then restored the original settings.

## Baseline and region map

| Region | Reference | Current | Result |
| --- | --- | --- | --- |
| Split cards | 469×462 each, stretch | 382×478 each, stretch | Equal cards; 16px height delta from the accepted narrower admin frame/wrapping |
| Page/header/save | Page header + active Save | Page header + active Save | Match |
| Save bar | Top bar, title + Save + shortcut | Top bar, title + Save + shortcut | Match |
| Email card | Recipients, Fieldset, warning Notice | Same components | Match |
| Data card | 954×170 | 779×170 | Same height; width delta is the accepted admin frame |
| Closing “One save” note | 541×32 | 541×40 | Width match; line-height differs (ST-001) |
| Data switch | Reference `switch-list` wrapper | Direct SwitchControl in card content | No visible card-height difference; accepted implementation detail |
| Default data state | admin@example.com, Core/Plugin events checked, Disable all off | qa@example.com, all event checkboxes disabled, Disable all on | Site data state; enabled fixture matched the reference layout |

## Findings

### ST-001 — Closing note line-height is larger than the reference

- **Severity:** Medium
- **Reference:** `.surface-note` is 541px wide and 32px high at 1280; it carries width/color, not an extra font/line-height override.
- **Current:** `.updatronix-surface-note` is 541px wide but 40px high; it sets `font-size`/`line-height` and wraps the text in a `Text` body-sm.
- **Impact:** The final explanatory note is 8px taller and visually looser than the reference.
- **Regression risk:** `.updatronix-surface-note` is shared by Auto updates/Documentation; changing it affects those notes too. Recommend scoping or aligning the shared class after checking those screens.

### ST-002 — Original notification state is disabled

- **Severity:** Data/state, not a UI defect
- **Reference:** Email notifications enabled: admin@example.com, Core updates and Plugin/theme updates checked, Disable all off.
- **Current baseline:** `qa@example.com`, `notify_enabled=false`, empty `notify_on`, Disable all checked; event checkboxes are disabled.
- **Evidence:** A temporary enabled fixture produced the reference-like state; the original settings were restored afterward.
- **Impact:** The default live screen looks different from the mock because the site has notifications disabled. Do not “fix” this as a design defect without a product decision.

### ST-003 — Recipient field stays editable while all emails are disabled

- **Severity:** Low (state UX)
- **Current:** With `emailsOff` true, the event checkboxes are disabled but the `SearchableChipSelectControl` remains active.
- **Reference:** No disabled notification state is mocked.
- **Impact:** A user can edit recipients while email delivery is disabled; changes are staged but have no immediate effect.
- **Regression risk:** State behavior; owner decision whether to disable the recipient field when “Disable all update emails” is on.

### ST-004 — Data section wrapper differs internally

- **Severity:** Low (not visible)
- **Reference:** Data content is wrapped in `.switch-list`.
- **Current:** The SwitchControl is a direct Card.Content child.
- **Evidence:** Data card is 170px in both; child heights 56px vs 80px with different padding, but the visual result matches and no card-height difference remains.
- **Impact:** No visible defect found; record as an implementation detail unless the SwitchControl spacing changes.

### ST-005 — Card height delta

- **Severity:** Low / accepted
- **Current:** Logging/Email cards are 478px at 1280 versus 462px in the reference. The live page is narrower because of the WordPress admin menu; descriptions wrap more.
- **Impact:** Expected from the accepted admin-frame deviation. No local code fix recommended.

### ST-006 — Matching areas

- Page title/description, active Save, save bar, split breakpoint/stretch, recovery-mode warning, recipient chips, Fieldset, notification switches, Data switch, Data copy, “Off by default” detail, and the second email-delivery note match.
- The current shared split now matches the reference at 800 (two columns) and 560 (one column, 12px gap).
- WPDS component API usage matches the MCP documentation for CheckboxControl, Fieldset, SearchableChipSelectControl, SwitchControl, and InputControl.

## Accepted deviations / no action

- The WordPress admin menu/toolbar remain around the plugin shell; the page width and card-height deltas follow from that owner decision.
- Recipient address, notification enablement, event selection, and site data are not design defects.
- The Data section’s internal wrapper difference has no visible card-height or layout effect.

## Resolved decisions

1. **Note typography — align the shared class:** `.updatronix-surface-note` now uses the 16px line-height token.
2. **Emails-off state — disable the recipient field:** the email chip field is disabled whenever “Disable all update emails” is active.
3. **Notification baseline — keep the disabled site state:** the temporary enabled fixture was removed and the original disabled settings restored.

## Implementation result (2026-10-25)

- Settings closing note now renders 541×32 with line-height 16px; Auto updates note is 482×32 and Documentation note is 482×16 with the same 16px line-height.
- Recipient chip controls, clear buttons, input, and combobox are disabled at the restored notifications-disabled baseline.
- Settings split remains one column with a 12px gap at 560/390 and no horizontal overflow.
- Free lint/CSS/token checks, 15 JS tests, build, PHPStan, and 213 PHPUnit tests pass.
- Original settings restored: `notify_enabled=false`, empty `notify_on`, `qa@example.com`.

Remaining gaps: no dedicated visual/disabled-state test; full screen-reader and cross-browser passes remain separate work. The card-height/admin-frame and internal Data-wrapper differences are accepted/no-action.
