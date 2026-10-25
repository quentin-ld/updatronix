# Settings WPDS MCP review

**Date:** 2026-10-25
**Scope:** Free Settings screen (`tools.php?page=updatronix&tab=settings`).
**Reference:** Open Design `settings.html`, `Settings.jsx`, `app.css`, and the live WPDS components.
**Status:** Review only. No UI source changed.

## Method

- Opened reference/live at 1280×900 and 390×900 and compared card composition, split layout, Save placement, notes, warning, and field states.
- Used WPDS MCP for `SearchableChipSelectControl`, `CheckboxControl`, `InputControl`, `Fieldset`, `SwitchControl`, `Notice`, `Text`, and `Button`.
- Toggled “Disable all update emails” without saving to inspect the enabled form state, then reloaded (no option persisted).

## What conforms

- Page header, title/description, Save changes action, and active Save model.
- Logging/Email notifications two-column split at desktop, stacked cards at mobile, equal-height cards, and the 16px Save-to-first-section rhythm.
- Logging switch/retention input, recipient field, notify checkboxes, recovery-mode disabled checkbox, warning, Data card, and closing notes.
- Enabled recipient state: the field and Remove buttons enable; `Remove all recipients` appears and the warning remains.
- `SearchableChipSelectControl` uses `clearButtonLabel`; MCP `showClearButton` defaults to true. The clear-all button is correctly present when the field is enabled.
- `CheckboxControl`, `InputControl`, `Fieldset`, `SwitchControl`, and `Notice` match the current WPDS `@wordpress/ui` docs.

## Findings

### SET29-01 — Live data is notifications-off, not a layout defect

**Severity:** None (accepted owner decision)
**Regression risk:** None

The live site has “Disable all update emails” on, so the recipient field, checkboxes, and clear-all button are disabled. The reference is enabled. Toggling the switch off without saving enabled the field and exposed the clear-all button; reloading restored the disabled state without persisting anything. Current data also differs from the reference: `qa@example.com` instead of `admin@example.com`, `notify_on` empty instead of core/plugin selected.

**Recommendation:** Keep the owner decision; no code change. The enabled and disabled states both render correctly.

### SET29-02 — MCP package coverage

**Severity:** Low (review limitation)
**Regression risk:** None if local types stay authoritative.

`SearchableChipSelectControl`, `CheckboxControl`, `InputControl`, `Fieldset`, and `SwitchControl` are documented as `@wordpress/ui` and match the screen. The MCP `Button` entry still describes `@wordpress/components`; the screen uses `@wordpress/ui` Button. Keep local package types and record the gap.

### SET29-03 — Warning is static

**Severity:** Low

The recovery-mode warning is part of the page content and remains visible when emails are enabled, so it is read in normal document order. WPDS MCP `Notice` does not announce itself, but there is no dynamic appearance to announce here.

**Recommendation:** No change unless the warning becomes conditional.

## MCP component matrix

| Component | MCP | Current use | Verdict |
|---|---|---|---|
| SearchableChipSelectControl | `@wordpress/ui`, clearButtonLabel/showClearButton | recipient field | Conforms |
| CheckboxControl/InputControl/Fieldset | `@wordpress/ui` | notifications/retention | Conforms |
| SwitchControl | `@wordpress/ui` | logging/emails/data | Conforms |
| Notice | Root/Title/Description | recovery warning | Static content; conforms |
| Button | `@wordpress/components` in MCP | `@wordpress/ui` Save | MCP package gap; local types |

## Grilling QA

- Covered: enabled/disabled email form, clear-all recipients, warning, Data switch, Save action, 1280 and 390 layout, and the card split.
- Not covered in this pass: save success/error/network, invalid/duplicate/max recipients, empty recipient list, 760/960 layout, keyboard/screen-reader traversal, RTL, and a full saved-state reload.
- No visual defect was found. The only differences are live data/owner state and the MCP package coverage gap.

## Recommendations

1. No UI fix is required from this pass.
2. Keep the disabled email form as the accepted owner decision.
3. Record SET29-02 and keep local `@wordpress/ui` APIs.
4. Re-test the notification form in the enabled state before release if the saved settings change.

## Files

Review taskfile: `.agents/tasks/2026-10-25-settings-wpds-review.md`.
