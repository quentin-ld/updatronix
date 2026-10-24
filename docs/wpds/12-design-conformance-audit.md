# Design-conformance audit

Date: 2026-10-24. Scope: the implemented Free screens against the Open Design
reference screens and the field guide.

## How this audit was done

Each screen was compared against both of these, not against the task file's
paraphrase:

1. The screen source under
   [`reference/screens/src/screens/`](reference/screens/src/screens/).
2. The rendered Open Design screen at
   `open-design-project/2dc80110-…/screens/<screen>.html`.

The rendered screen is the visual contract. The guide's prose explains the
rules, but it does not replace the screen.

## Verdict

The shell's rail is close to the reference. The working surfaces are not. Three
screens diverge enough that they should be treated as unfinished: Schedule,
Settings, and the Update log. The page header is also missing the reference's
meta badges and per-screen actions on every screen.

## Findings

### 1. Page header — every screen

| | Reference | Current |
|---|---|---|
| Structure | Per-screen `PageHeader`: breadcrumb, one `h1`, description, optional meta badges, optional actions. | Shell-owned header with breadcrumb, `h1`, description. No meta badges, no per-screen actions. |
| Actions | Schedule: `Run a check now` and `Save changes`. Settings: `Save changes`. Update log: `Retention 90 days` meta; Export is in the section header. | No page actions. Save controls live only in the sticky bar; Export lives in the toolbar. |
| Focus | Page title receives focus on navigation. | The shell title receives focus, but the page cannot contribute actions or meta. |

**Remediation:** add a page-header slot/context so each screen supplies `meta`
and `actions`, then add the reference actions and badges per screen. This is the
first remediation wave because every other fix depends on it.

### 2. Schedule

| | Reference | Current |
|---|---|---|
| Sections | `Update checks` (WordPress cron), `Next run` (computed from the saved schedule), `What the hold does` (table). | `Update checks`, `Delay updates`. |
| Frequency | `SelectControl` with Daily, Twice daily, Hourly. | `SelectControl` with WordPress default, hourly, twice daily, daily, weekly. |
| Preferred time | `SelectControl` with 03:00, 06:00, 12:00, 21:00. | Two number inputs for hour and minute. |
| Hold | `Hold new releases for (days)` 0–30, one field. | A switch plus a days field under a separate `Delay updates` section. |
| Header | Meta `Next check: …`; actions `Run a check now` and `Save changes`. | No meta, no actions. |
| Notes | Next-run explanation and the hold table explain what the hold does. | No Next-run section, no hold table. |

**Remediation:** rewrite Schedule to the reference composition. Map the single
hold field to `delay_updates.enabled = hold > 0` and `delay_value = hold`. Add
the `Run a check now` action with a server endpoint (or defer the action with a
recorded decision).

### 3. Settings

| | Reference | Current |
|---|---|---|
| Sections | `Logging` (Update history), `Email notifications` (WordPress update emails), `Data` (uninstall behavior). | `Logging`, `Email notifications`. |
| Recipients | `SearchableChipSelectControl` chips, add on Enter, remove one or all. | Plain email text input. |
| Notification events | Core updates, Plugin and theme updates, Debug summary, Technical alerts, Recovery mode (always on, disabled). | Core updates, Plugin and theme updates, Debug email, Recovery mode (technical email). No always-on recovery row. |
| Master switch | One `Disable all update emails` switch. | `Manage update notifications` switch plus `Disable all`. |
| Data | `Delete all data on uninstall` switch, off by default. | Missing. Backend has no option for it. |
| Extra | The reference has no `Clear all logs` button. | A `Clear all logs` destructive action sits in Logging. |
| Header | Description includes “uninstall data”; `Save changes` action. | Description omits uninstall data; no page action. |
| Notes | “One save for the whole surface…” and “Email delivery is handled by WordPress…”. | Missing. |

**Remediation:** rewrite Settings to the reference composition. Add the
`delete_data_on_uninstall` option and honor it in `uninstall.php`. Map the
recipients chips to the comma-separated `notify_emails` value. Keep the clear-log
action only if the owner wants an explicit exception to the reference; otherwise
remove it.

### 4. Update log

| | Reference | Current |
|---|---|---|
| Surface | DataViews `activity` layout: day headings, feed rows with type/item, version range, `trigger · actor`, status, time, actions. | DataViews `table` with columns Item, Type, Version, Trigger, Status, Date, Actions. |
| Wrapper | `Section` “Recent updates” with description and an `Export logs` action in the section header. | No section wrapper; Export sits in the DataViews toolbar. |
| Header | Meta `Retention 90 days`. | No meta. |
| Details | Preview aside on wide screens; the guide allows a dialog/drawer on narrow screens. | Modal dialog only. |
| Grouping | Rows grouped by day. | Flat table; no day grouping. |

**Remediation:** restore the `activity` layout with `groupBy: { field: 'day' }`,
the reference fields (`item`, `summary`, `typeIcon`, `day`, `date`, `kind`,
`trigger`, `actor`, `status`, `time`), the Section wrapper, the header meta, and
the preview aside. Keep the WPDS details and delete dialogs as the narrow-screen
fallback.

### 5. Auto updates

Not migrated. It still uses the legacy panel and per-control saves. The
reference uses cards and one manual save. Remediation is `F3-03`.

### 6. Documentation and Support

The content is close, but both lack the page-header meta and the reference's
exact card composition. Documentation should show the `3 guides` meta badge and
the guide kickers. Support should keep the four checks and the forum link.
Remediation is part of the page-header wave plus a card review.

### 7. Health and the Pro screens

Health is a placeholder. The reference has readiness checks, open issues, retry,
a resolved archive, and diagnostics. Update Shield, Update Flow, White label,
and the license page are legacy or placeholders. These are the F4 and P2–P5
tasks; they must be built against their reference screens, not invented.

## Required process change

Every screen task must now begin and end with a reference comparison:

1. Open `reference/screens/src/screens/<Screen>.jsx` and the rendered
   `open-design-project/…/screens/<screen>.html`.
2. List the reference's sections, controls, copy, meta, actions, and states.
3. Implement against that list.
4. Record the comparison and any deliberate deviation in the task log and in
   this audit.
5. A screen is not done while it differs in layout, copy, controls, or states
   without a recorded decision.

## Remediation waves

| Wave | Scope | Priority |
|---|---|---|
| R1 | Page-header slot plus meta/actions on Settings, Schedule, and Update log. | P0 |
| R2 | Schedule rewrite to the reference composition, including the hold field and Next-run section. | P0 |
| R3 | Settings rewrite: chips, event labels, always-on recovery, Data card, notes, uninstall option. | P0 |
| R4 | Update log activity layout, day grouping, Section wrapper, header meta, preview aside. | P0 |
| R5 | Auto updates, Documentation/Support meta, Health, and the Pro screens. | P1 |

### Status at 2026-10-24

| Wave | Status | Evidence |
|---|---|---|
| R1 | Partial | The log, Schedule, and Settings screens render their own `PageHeader` with meta and actions. Documentation, Support, Health, and the Pro screens still use the shell header. |
| R2 | Done | Schedule matches the reference composition: Update checks, Next run, What the hold does, the 0–30 hold field, the preferred-time select, header meta/actions, and `POST /run-check`. |
| R3 | Done | Settings matches the reference: three cards, chip recipients, five notification rows, the always-on recovery warning, the Data opt-in, and the closing notes. |
| R4 | Done | The log uses the DataViews activity feed grouped by day, a Section header with Export, the retention meta, and a details preview aside on wide screens with the dialog fallback on narrow screens. |
| R5 | Pending | Auto updates, Health, and the Pro screens. |

## What not to do

- Do not treat the guide's screen-contract prose as a substitute for the
  rendered screen. Where they differ, the rendered screen is the visual
  reference and the difference is recorded here.
- Do not add a control the reference does not have without recording why.
- Do not mark a screen done from a build or a unit test alone. The visual
  comparison is part of the acceptance gate.
