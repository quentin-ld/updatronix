# Design conformance review — WPDS candidate vs Open Design

**Date:** 2026-10-24
**Status:** Review only. No UI code has been changed for these findings.
**Candidate:** `major/wpds` in `updatronix` / `updatronix-pro`, rendered at
`https://updatronix.ddev.site/wp-admin/tools.php?page=updatronix`.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/*.html`
and `docs/wpds/reference/screens/src/app.css` + `ui.jsx`.

## Method

- Read the reference shell, page header, save bar, rail, page, note, and card
  rules in `app.css` and `ui.jsx`.
- Read the rendered reference screen `update-log.html` and its source
  `UpdateLog.jsx`.
- Read every candidate screen component and the shell CSS.
- Inspected the current admin in the browser and measured the layout from the
  component/CSS source.

Severity: **P0** blocks the design, **P1** visible or interaction-breaking,
**P2** polish or consistency.

## Executive summary

The candidate uses WPDS components and the section content is mostly migrated,
but the **application frame is not the reference frame**. The current app is a
grid placed inside WordPress admin content; the reference is a full-viewport
dark shell with a full-height rail and a white rounded content surface.

The save interaction is also structurally different. The reference puts Save in
the page header and adds a fixed top save bar only after that button scrolls
out. The candidate puts a sticky bar at the bottom of three Free screens and
puts plain inline Save buttons on the Pro screens, so Save disappears when the
Pro page scrolls.

Transient feedback is also different: the reference uses a fixed black snackbar
at the bottom center for save, copy, delete, and error feedback. The candidate
puts WordPress notice boxes at the top of the page, or only updates an
off-screen live region.

The result is not a small spacing pass. The shell, the single-header rule, and
the save model need to be corrected first; per-screen deltas will then be
re-checked against the rendered Open Design screens.

## P0 — Shell and page frame

### SHELL-01 — The plugin is not full-bleed inside the WordPress page

**Current:** `updatronix_options_page()` renders
`.updatronix-dashboard-wrap > .updatronix-page > main#updatronix-settings`
inside `#wpbody-content` (`inc/admin/menu.php:80-114`). There is no CSS for
these wrappers, no negative margin, and no full-height reset. WordPress
admin padding remains around the app.

**Reference:** `.shell` is the page (`app.css:55-63`):
`display:grid; grid-template-columns:264px minmax(0,1fr); height:100vh;
height:100dvh; overflow:hidden; background:#1e1e1e`.

**Impact:** the padding the user sees is WordPress chrome, not the design.
The rail cannot be full height while its grid is only as tall as the content.

**Required outcome:** the app owns the content viewport edge-to-edge, with no
WordPress padding around the shell. `#wpbody-content`, `.wrap`, and the footer
need a deliberate full-bleed plan, without affecting other admin pages.

### SHELL-02 — The rail is inset, rounded, and shorter than the screen

**Current:** `.updatronix-rail` is `position:sticky; top:calc(32px + md);
max-height:calc(100vh - 32px - (2 * md)); border-radius:lg; padding:lg md`
(`_shell.scss:35-47`).

**Reference:** `.rail` is static, `align-self:stretch; height:100%; min-height:0;
overflow-y:auto; padding:lg; background:#25292b`; no radius (`app.css:65-77`).
The rail is a full-height column, not a floating card.

**Impact:** the dark bar stops before the bottom, has rounded corners, and
starts below the admin bar offset. This is the second half of the user's first
report.

### SHELL-03 — The dark shell background is missing

**Current:** `.updatronix-shell` has no background (`_shell.scss:11-17`). Only
the rail is dark; the area around the content is the WordPress admin background.

**Reference:** the shell is `#1e1e1e`, the rail is `#25292b`, and the main
surface is the white card (`app.css:55-77,308-336`).

**Impact:** the dark field beside the white card does not exist, so the
reference composition cannot read correctly.

### SHELL-04 — Content is not wrapped in the white rounded card

**Current:** `.updatronix-shell-main` only has `min-width:0`
(`_shell.scss:148-150`). `AppShell` renders `{children}` directly
(`AppShell.js:101-110`). `.updatronix-surface` exists but is unused
(`_shell.scss:344-349`).

**Reference:** `.main` is the white surface:
`margin:md md md 0; overflow-y:auto; background:surface-neutral;
border-radius:xl; box-shadow:...;` and it is the scroll container
(`app.css:308-336`).

**Impact:** no white rounded card, no dark gap on the left/top/bottom, no
internal scrollbar. This is the core of the user's first report.

### SHELL-05 — No reference page column

**Current:** pages render `PageHeader` + `.updatronix-page-body` directly
(`_shell.scss:169-223`). There is no max width or page padding; width is
inherited from WordPress.

**Reference:** `.page` is `width:100%; max-width:1120px; margin:0 auto;
padding:3xl xl (3xl * 2); gap:xl` (`app.css:338-355`).

**Impact:** line length, title alignment, and section width do not match the
reference. The page needs a real page column inside the main surface.

### SHELL-06 — The scroll container is the window, not the main surface

**Current:** the rail is sticky to the window; the page scrolls the WordPress
document. The SaveBar is `position:sticky; bottom:0` inside the form
(`_shell.scss:900-912`).

**Reference:** `.main` has `overflow-y:auto` and the SaveBar is positioned from
`.main`'s bounding rectangle (`ui.jsx:165-181`).

**Impact:** the reference save-bar visibility/positioning cannot work until the
main surface is the scroll container. This is the structural prerequisite for
the top save bar.

### SHELL-07 — WordPress chrome is not accounted for

The candidate has no rule for `#wpfooter`, admin notices, or the `.wrap`
wrapper. The reference is a standalone shell. Notices/footer can appear inside
or below the card, and the app cannot reach the viewport edges.

### SHELL-08 — Mobile shell is not the reference shell

**Current:** at ≤900px `.updatronix-shell` becomes `display:block`, the rail is
hidden, and `.updatronix-mobile-bar` is rendered inside the main content with a
margin and radius (`_shell.scss:942-967`).

**Reference:** the mobile bar is part of the shell; `.main` remains the white
surface and the rail becomes a drawer. The dark bar is full width, not a
rounded inset inside the page.

## P1 — Header model

### HDR-01 — Two different headers are live

- `AppShell` renders its own breadcrumb/`h1`/description/actions
  (`AppShell.js:62-107`).
- `PageHeader` renders another breadcrumb/`h1`/description/meta/actions
  (`PageHeader.js:32-82`).
- `SettingsPage` hides the shell header for `logs`, `schedule`, `settings`,
  `auto-updates`, `documentation`, `support`, `health`
  (`SettingsPage.js:147-154,266`), so Free screens use one header and Pro
  screens use the other.

**Reference:** one `PageHeader` on every screen (`ui.jsx:58-83`).

**Impact:** title size, breadcrumb markup, actions, focus, and spacing differ
by screen. Pro screens have no page-header action slot.

### HDR-02 — The shell breadcrumb ignores the brand

`AppShell` hardcodes `Updatronix` in the breadcrumb (`AppShell.js:74`).
`PageHeader` uses `settings.brand?.name` (`PageHeader.js:20-21,38`). White
label therefore works on Free screens with `PageHeader` and not on Pro screens
using `AppShell`.

### HDR-03 — Page header typography does not match

**Current:** `.updatronix-page-title` is `font-size:xl`; description is the
default text size; breadcrumb is a plain `nav > a + span` (`_shell.scss:183-206`,
`PageHeader.js:37-48`).

**Reference:** `.page-title` is `2xl`; `.page-sub` is `body-lg`, max 68ch;
breadcrumb is `Breadcrumb.Root/LinkItem/CurrentItem` (`app.css:366-393`,
`ui.jsx:62-65`).

### HDR-04 — Pro screens have no header actions

The reference `Update Shield`, `Update Flow`, and `White label` headers include
a SaveButton (and a shortcut hint) in `page-actions`; `License` has a dynamic
description. The candidate's Pro screens use the AppShell header, which has no
action slot, and put Save in a plain row at the top of the body.

### HDR-05 — The license header description is static

The reference license page changes the header description between inactive
("Activate your Updatronix 3000 license...") and active ("Your license is active
for this site.") (`License.jsx:36-43`). The candidate shell shows the static
route description "Manage your Updatronix 3000 license and Pro tools."
(`routes.js`).

### HDR-06 — Page header semantics and focus are split

`AppShell` and `PageHeader` each own a title ref and a focus effect. The
reference has one title. The candidate's two paths make it easy to focus a
header that is not rendered, and the plain breadcrumb lacks the WPDS
breadcrumb semantics.

## P1 — Save model

### SAVE-08 — SaveBar is at the bottom, not fixed at the top

**Current:** `.updatronix-savebar` is `position:sticky; bottom:0`, always
rendered at the end of the form (`_shell.scss:900-912`; `SaveBar.js:95-135`).

**Reference:** `SaveBar` is a `Card.Root` with `position:fixed`, appears only
when the header SaveButton leaves the viewport (IntersectionObserver on
`anchorRef`), and is positioned to the `.main` rectangle (`ui.jsx:146-216`;
`app.css:423-443`).

**Impact:** the requested "save button in a top bar when we scroll" does not
exist. The current bar is at the bottom and is always visible.

### SAVE-09 — SaveBar content and visibility differ

The reference bar contains the page title and a SaveButton; it appears only
when scrolled. The current bar contains an "Unsaved changes"/"All changes
saved" status, a Discard button, and a Save button, and is present even when
clean (`SaveBar.js:99-134`). The always-on bottom bar occupies layout space and
does not match the reference state model.

### SAVE-10 — Pro screens have no SaveBar

`UpdateShieldPage`, `UpdateFlowPage`, and `WhiteLabelPage` render a plain
top-of-body Save button. The reference has a PageHeader SaveButton plus the
fixed SaveBar. When those pages scroll, Save is not available.

### SAVE-11 — Shortcut hints are inconsistent

The reference SaveButton shows `Ctrl+S`/`⌘S` both in the header and in the save
bar (`ui.jsx:120-133`). The candidate's header Save buttons do not show the
shortcut; only the bottom SaveBar does (`SaveBar.js:128`). The Pro pages have
keyboard handlers but no visible hint.

### SAVE-12 — No save anchor or scroll trigger

There is no `saveButtonRef`/`IntersectionObserver`/`.main` anchor in the
candidate. The reference needs all three to decide when the top bar appears.
This must be built with the shell, not added to the current bottom bar.

### SAVE-13 — Save controls are duplicated or inconsistent

Free Auto updates, Schedule, and Settings render a header SaveButton and a
bottom SaveBar. Pro Shield, Flow, and White label render only a body top row.
The reference is consistent: header SaveButton + scroll SaveBar on every saved
surface.

## P1 — Rail details

### RAIL-14 — Group labels are uppercase in the candidate

The current `.updatronix-rail-label` has `text-transform:uppercase`
(`_shell.scss:96-102`), so the rail reads `UPDATES`, `AUTOMATIONS`, `SYSTEM`,
`HELP`. The reference `.rail-label` has no text-transform and renders
`Updates`, `Automations`, `System`, `Help` (`app.css:127-130`; rendered
`update-log.html`).

### RAIL-15 — Pro badges are on all four Pro destinations

The candidate renders a `Pro` badge for every `edition === 'pro'` item
(`Rail.js:90-95`), so Update Shield, Update Flow, and White label also show
`Pro`. The rendered reference screen only labels `Updatronix 3000` as Pro;
the other three are plain destination links.

### RAIL-16 — The active rail item is missing its white marker

The reference draws a 2px white bar on the left of the current item
(`app.css:153-162`). The candidate's `.is-current` only changes the background
and weight (`_shell.scss:104-128`), so the current destination is less
distinguishable.

### RAIL-17 — Rail brand typography is smaller

Reference `.rail-name` uses the heading font, `font-size:lg`, `white` and
emphasis (`app.css:103-109`). Current `.updatronix-rail-name` uses the body
font scale `md` and no explicit white (`_shell.scss:74-78`).

### RAIL-18 — Rail links are denser and have a different hit area

Reference links use `padding:sm md`, no minimum height (`app.css:132-141`).
The candidate uses `min-height:36px` and `padding:xs sm`
(`_shell.scss:104-110`), changing rhythm and density.

### RAIL-19 — Rail focus ring may not contrast

The reference uses a white focus outline on the dark rail (`app.css:79-82`).
The candidate uses `--wpds-color-stroke-focus` (`_shell.scss:118-120`), which
needs a contrast check against `#25292b`.

## P1 — Note styling

### NOTE-20 — The note is a boxed panel, reference is plain text

The reference `Note` is a body-sm `Text` with `surface-note` (max 80ch, muted)
(`ui.jsx:108-114`; `app.css:475-479`). The candidate's
`.updatronix-surface-note` adds a background, border, radius, and padding
(`_shell.scss:232-237`). Auto updates and Schedule use this boxed variant.

## P1 — Per-screen findings

### Update logs

- **LOG-01** The reference closes the page with a Note that entries older than
  90 days are removed (`UpdateLog.jsx:626`). The candidate's log panel has the
  retention badge but no closing Note (`ActivityLogPanel.js`; only `Retention
  %s days` at line 545).
- **LOG-02** The page surface, max width, and title/description typography are
  missing (SHELL-04/SHELL-05/HDR-03).
- **LOG-03** The reference details area is a `Card.Root` inside the scrollable
  `log-layout` (`UpdateLog.jsx:554-567`); the candidate uses a custom
  `.updatronix-log-preview` aside. Width, sticky behavior, and card treatment
  need a rendered comparison after the shell fix.
- **LOG-04** The reference header has no action; Export lives in the Recent
  updates Section (`UpdateLog.jsx:492-510`). The candidate keeps Export in the
  Section, which is correct; the visual placement must be rechecked inside the
  new page column.

### Auto updates

- **AUTO-01** Save is a header SaveButton plus a bottom SaveBar in the
  candidate; the reference is header SaveButton plus the fixed top SaveBar
  (SAVE-08/SAVE-10).
- **AUTO-02** The closing note is a boxed `.updatronix-surface-note`; the
  reference is the plain `Note` (NOTE-20).
- **AUTO-03** Title/description size and page width differ (HDR-03/SHELL-05).
- **AUTO-04** The section order is already Core, Translations, Themes, Plugins,
  matching the reference. The section content itself is not the blocker.

### Schedule

- **SCH-01** Save is header SaveButton plus a bottom SaveBar; reference is the
  fixed top SaveBar (SAVE-08).
- **SCH-02** The closing note uses the boxed `.updatronix-surface-note`; the
  reference is plain `Note` (NOTE-20).
- **SCH-03** Title/description size, page width, and split spacing differ
  (HDR-03/SHELL-05).
- **SCH-04** The header action order (Run a check now, Save) matches the
  reference; only the save placement/style differs.

### Settings

- **SET-01** Save is header SaveButton plus a bottom SaveBar; reference is the
  fixed top SaveBar (SAVE-08).
- **SET-02** Title/description size and page width differ
  (HDR-03/SHELL-05).
- **SET-03** The reference uses `Note` at the end; the candidate's closing
  explanatory Text/box needs a style comparison after the shell fix.

### Updatronix 3000 (license)

- **LIC-01** No PageHeader. The AppShell header is static and has no actions;
  the reference has a PageHeader with a dynamic active/inactive description
  (`License.jsx:36-43`; HDR-05).
- **LIC-02** The license card is not inside the white main surface/page column
  (SHELL-04/SHELL-05).
- **LIC-03** The feature list, active/inactive empty states, and Note need a
  rendered comparison at the reference width; the candidate adds dev-mode and
  decryption states that the prototype does not have, so those are expected
  additions, not defects.
- **LIC-04** The page has no SaveBar (correct; activation is its own action),
  but it also has no header action slot for a future license action.

### Update Shield

- **SH-01** No PageHeader/SaveBar. Save is a plain body-top row; the reference
  has a PageHeader SaveButton and a fixed top SaveBar (`UpdateShield.jsx:36-45`).
- **SH-02** The candidate's Recently blocked table adds a Log column
  (`UpdateShieldPage.js`); the reference has Item, Version, Reason, Blocked.
  The link is useful, but it is an unrecorded design deviation. Decide whether
  the Log column stays or moves into the row action.
- **SH-03** The page surface/width/title typography differ
  (SHELL-04/SHELL-05/HDR-03).
- **SH-04** The Protection/version-lock/queue/radio content is close; verify
  lock-card spacing, switch density, and the Note after the shell fix.

### Update Flow

- **FLOW-01** No PageHeader/SaveBar. Save is a body-top row; the reference has
  a PageHeader SaveButton and a fixed top SaveBar (`UpdateFlow.jsx:288-297`).
- **FLOW-02** The reference supports inline rename from the chain list
  (`UpdateFlow.jsx:329-353`); the candidate's pencil opens the editor instead.
  This is a missing interaction.
- **FLOW-03** The reference editor dialog uses `Dialog.Popup size="medium"`
  (`UpdateFlow.jsx:485-487`); the candidate uses a default `Dialog.Popup`.
- **FLOW-04** The page surface, page width, and title typography differ
  (SHELL-04/SHELL-05/HDR-03).
- **FLOW-05** The dependency check, chain cards, and delete confirmation are
  close; recompare after the shell fix.

### White label

- **WL-01** No PageHeader/SaveBar. The reference puts Reset and Save in the
  PageHeader actions and adds the fixed top SaveBar
  (`WhiteLabel.jsx:110-124`). The candidate has a body-top row.
- **WL-02** The closing note is a Text + Button pair; the reference uses a
  `Note` with a Link (`WhiteLabel.jsx:314-316`).
- **WL-03** The page surface/width/title typography differ
  (SHELL-04/SHELL-05/HDR-03).
- **WL-04** The logo upload path differs: the candidate uploads to the media
  library, while the prototype uses a local object URL. That is a functional
  improvement, but the preview and failure states need a rendered comparison.
- **WL-05** The brand/colors/replace/preview content is close; verify preview
  card widths, the light-menu preview, and the email preview at the reference
  page width.

### Health

- **HEALTH-01** PageHeader is present with meta/actions; title/description
  typography and page width still differ (HDR-03/SHELL-05).
- **HEALTH-02** The summary card, checks, open issues, and resolved table need
  a rendered comparison at the reference width; the shell surface is missing
  (SHELL-04).
- **HEALTH-03** No SaveBar is correct; Health is read-only.

### Documentation

- **DOC-01** PageHeader is present; title/description typography and page width
  differ (HDR-03/SHELL-05).
- **DOC-02** The doc grid/cards use prefixed `updatronix-doc-*` classes; compare
  card heights, icon boxes, and the bottom read link at the reference width.

### Support

- **SUP-01** PageHeader is present; title/description typography and page width
  differ (HDR-03/SHELL-05).
- **SUP-02** The support layout and forum card need a rendered comparison at
  the reference width.

### Hub

- **HUB-01** The reference has a Hub screen; the candidate intentionally does
  not ship it (`D-13`). This is a documented deviation, not a defect, but the
  design review should record it explicitly.

## P2 — Consistency and accessibility

- **ACC-01** The page title uses `outline:none` on focus
  (`_shell.scss:190-193`). Confirm programmatic focus is not mistaken for a
  keyboard focus state.
- **ACC-02** The rail focus color needs contrast verification (RAIL-19).
- **ACC-03** The AppShell and PageHeader focus effects duplicate the title-focus
  behaviour (HDR-06).
- **ACC-04** The log preview is sticky at ≥1200px; the reference uses a scroll
  container/card. Recheck after the shell becomes the scroll container.
- **ACC-05** WordPress admin notices and the footer are not positioned; they can
  break the full-height composition (SHELL-07).
- **ACC-06** The mobile save action is only at the bottom; the reference top bar
  is missing at mobile widths too (SAVE-08/SHELL-08).

## UX — Micro-interactions and feedback

These are the interaction-level mismatches that the first pass missed. The
reference is not only a layout; it has a specific feedback model.

### UX-01 — The save/action snackbar is missing

**Reference:** `SnackbarProvider` keeps local notices, auto-dismisses each after
4500 ms, and renders `SnackbarList` in a fixed `.app-snackbar` at the bottom
center of the viewport (`ui.jsx:19-56`; `app.css:1453-1469`). That produces the
black centered toast the user sees after a save, copy, delete, or error. It is
dismissible and has a spoken message.

**Current:** `Notices` renders `NoticeList` from the WordPress notices store in
`.updatronix-notices`, at the top of the app content
(`components/Notices.js:1-21`; `SettingsPage.js:268`;
`_shell.scss:351-360`). `SnackbarList` is exposed in the shared runtime but is
not used (`shell/wpds.js:56,172`). Save success notices are created with
`createSuccessNotice` in the Free hooks and Pro panels, so they appear as
WordPress notice boxes at the top, not as the reference black snackbar.

**Impact:** the save confirmation does not appear where the design says, does
not have the reference dark styling, and is not a transient global toast. This
is the exact example the user called out.

**MCP check:** WPDS `Snackbar` is native in `@wordpress/components`; it
auto-dismisses after 6 seconds, or 10 with actions, and exposes `spokenMessage`,
`politeness`, `explicitDismiss`, and `actions`. The reference provider's
4.5-second dismissal and fixed bottom-center list are custom. The candidate's
runtime exposes `SnackbarList` but not `Snackbar`, and the app uses
`NoticeList`.

### UX-02 — No auto-dismiss, stacking, or toast timing

The reference removes each snackbar after 4.5 seconds and supports multiple
snackbars in the fixed list. The current notices store has no equivalent
4.5-second lifecycle; notices remain until dismissed or the store is cleared.
There is also no global bottom-center stacking container; notices render inside
the page content and can be pushed by the page layout.

### UX-03 — Save feedback is duplicated and split

Free screens currently show a top notice after save and also change the bottom
SaveBar to "All changes saved" (`SaveBar.js:99-134`). Pro screens show a top
notice and leave the body Save button in place. The reference shows one
snackbar and the scroll-triggered SaveBar; it does not duplicate the success
message in two places.

### UX-04 — Export copy is silent to sighted users

`ExportLogsModal.copyExport()` writes a message only to an off-screen live
region (`ExportLogsModal.js:303-340`). The reference action feedback is a
visible snackbar. A copy action that only announces to assistive technology
leaves sighted users without confirmation.

### UX-05 — Save, copy, delete, recheck, activation, and reset use different feedback paths

The candidate mixes three models:

- WordPress notices store (`createSuccessNotice`/`createErrorNotice`) on Free
  hooks and Pro pages.
- Local inline `Notice.Root` blocks for page-level errors.
- A live region for clipboard copy.

The reference uses one `useSnackbar` path for transient feedback. A single
surface can therefore show feedback in the wrong place, or not at all.

### UX-06 — SaveBar and snackbar states are not coordinated

The reference SaveBar appears only while the header SaveButton is out of view;
the snackbar appears after the save. The candidate's bottom bar is always
present and its status text changes, while a top notice is added. There is no
single source of truth for "saving", "saved", and "failed" feedback.

### UX-07 — Shortcut discoverability is incomplete

The reference SaveButton includes the `Ctrl+S`/`⌘S` hint in the header and in
the SaveBar (`ui.jsx:120-133,201-215`). The candidate has the hint only in the
bottom Free SaveBar; Pro body save buttons have no hint, and the License page
has no save shortcut because it has no save action.

### UX-08 — Dialog focus, escape, and return need a formal pass

The candidate uses WPDS `Dialog.Root`/`AlertDialog.Root` in the log, export,
flow, shield, and white-label surfaces. The reference uses the same family, but
the review did not confirm focus trap, Escape close, return focus to the
trigger, or scroll lock on every dialog. Register these as tests, not assumed
passes.

### UX-09 — Destructive confirmations need target names and focus return

The reference names the chain in the delete dialog and uses an irreversible
AlertDialog. The candidate does this for chains and logs; the white-label reset
and license deactivation also need the same target-specific copy, focus return,
and snackbar after completion. Verify each one.

### UX-10 — Row action affordance differs

The reference log row exposes an `Actions` menu button (`update-log.html`
rendered snapshot; `UpdateLog.jsx` row actions). The candidate uses the
DataViews actions rendering. Keyboard opening, menu focus, and the details
action must be compared; this is a micro-interaction, not only a column layout.

### UX-11 — Toast accessibility is not specified

The reference snackbar supplies `spokenMessage` and a dismiss button
(`ui.jsx:22-35,43-45`). The candidate's notice list and live region do not
prove the same announcement timing. Register a screen-reader test for save,
error, copy, and delete.

### UX-12 — Rail hover/current/focus and mobile drawer

The reference has a white 2px current marker, a white focus outline on the dark
rail, and a mobile drawer with its own focus/escape path (`app.css:79-82,
132-162`). The candidate is missing the marker, uses the general focus token,
and puts the mobile bar inside the content. These are interaction/visual
details, not only colors.

## Micro-interaction test register

Each test compares the candidate against the rendered Open Design screen at
desktop width and at 390px. "Candidate status" is the current review evidence,
not a claim that the test was executed.

| ID | Scenario | Reference expectation | Candidate status |
|---|---|---|---|
| UX-T01 | Save Settings | Black snackbar bottom center; message; close; auto-dismiss after 4.5s | Top `NoticeList`; bottom SaveBar status; no snackbar. **Mismatch.** |
| UX-T02 | Save Auto updates | Same snackbar; header SaveButton + scroll SaveBar | Top notice; bottom SaveBar. **Mismatch.** |
| UX-T03 | Save Schedule | Same snackbar; header Save + Run a check | Top notice; bottom SaveBar. **Mismatch.** |
| UX-T04 | Save Update Shield | Header Save + scroll SaveBar; snackbar | Body-top Save; top notice; no scroll bar. **Mismatch.** |
| UX-T05 | Save Update Flow | Header Save + scroll SaveBar; snackbar | Body-top Save; top notice; no scroll bar. **Mismatch.** |
| UX-T06 | Save White label | Header Reset/Save + scroll SaveBar; snackbar | Body-top Reset/Save; top notice; no scroll bar. **Mismatch.** |
| UX-T07 | Save failure | Error snackbar; draft preserved; no false success | Top error notice; bottom status; **false "All changes saved" confirmed on Settings and Schedule** (`SettingsPanel.js:199`, `SchedulePanel.js:196`). |
| UX-T08 | Copy export | Snackbar "Copied"; clipboard content | Live region only. **Mismatch for sighted users.** |
| UX-T09 | Delete log entry | AlertDialog names entry; focus returns; snackbar | Dialog exists; feedback path top notice. **Needs test.** |
| UX-T10 | License activate/deactivate | Snackbar; header description changes; masked key | Top notice; header static. **Mismatch.** |
| UX-T11 | Health run/retry | Loading state; snackbar; retry queue visible | Top notice; loading exists. **Needs test.** |
| UX-T12 | Flow create/update/delete | Snackbar; dialog focus/return; dependency check | Top notice; editor dialog exists. **Needs test.** |
| UX-T13 | White label reset/upload error | Reset confirmation; snackbar; inline upload error | AlertDialog exists; top notice; upload error inline. **Needs test.** |
| UX-T14 | Ctrl+S on every saved screen | Saves; shortcut hint visible in header and bar | Free bottom hint; Pro no hint. **Mismatch.** |
| UX-T15 | Scroll after header save | Top SaveBar appears at the main surface top; disappears at header | No top bar; bottom bar only. **Mismatch.** |
| UX-T16 | Open/close dialog | Focus trap; Escape; focus returns to trigger | WPDS components used; not verified. **Needs test.** |
| UX-T17 | Destructive dialog | Target-specific title; irreversible style; focus return | Existing AlertDialogs; verify all. **Needs test.** |
| UX-T18 | Mobile drawer | Full-width dark bar; drawer focus/escape; content card | Mobile bar inside content; no top save. **Mismatch.** |
| UX-T19 | Screen reader save/error/copy | Spoken message; dismiss; no duplicate announcement | NoticeList/live region; not verified. **Needs test.** |
| UX-T20 | Multiple feedback messages | Snackbars stack bottom center; each auto-dismisses | Top notices stack in content; no timer. **Mismatch.** |
| UX-T21 | Row action menu | Actions menu opens by keyboard; details action works | DataViews action; needs comparison. **Needs test.** |
| UX-T22 | Loading and empty states | No layout jump; empty state remains centered in card | Current spinners/empty states exist; verify. **Needs test.** |

## Second-pass review — review-skill findings

The available `review` skill inspected both trees from source after the first
pass. It did not render the browser; focus, sticky, and contrast findings are
source-read and must be confirmed visually. These findings are additional to
the shell/header/save/rail findings above.

### Should-fix

- **UX-13 — A failed save can say "All changes saved."** Settings and Schedule
  push the draft into the shared settings state before the request, so the
  saved snapshot re-baselines to the draft, Save disables, and the beforeunload
  guard drops even when the request fails (`SettingsPanel.js:199`;
  `SchedulePanel.js:196`). Auto updates gates on the save result
  (`AutoUpdatesPanel.js:297-304`); copy that pattern.
- **UX-14 — Unsaved drafts are lost on rail navigation.** The only protection
  is `beforeunload` in the shared SaveBar (`SaveBar.js:77-91`). Rail and
  popstate navigation unmount the panel (`Rail.js:36-39`;
  `SettingsPage.js:89-99`) and silently discard the draft. Pro pages have no
  guard at all.
- **UX-15 — Clearing a decryption-broken license bypasses confirmation.**
  "Clear stored license" calls `deactivateLicense` immediately
  (`LicensePanel.js:205`), while normal deactivation uses an irreversible
  AlertDialog that names the site (`LicensePanel.js:319-321,431-453`).
- **UX-16 — Some success notices fire before persistence.** White-label reset
  and Flow chain create/delete announce success while only setting `dirty`
  (`WhiteLabelPage.js:250`; `UpdateFlowPage.js:453,470`). Leaving the page
  then loses work the UI already called successful.
- **UX-17 — Notices move between layouts and survive navigation.** The notice
  block renders before `renderDestination()` (`SettingsPage.js:268`), so it
  sits above the H1 on the seven hide-header screens and below the H1 on Pro
  screens. `Notices` only removes on click (`Notices.js:11-21`); navigation
  does not clear old notices.
- **UX-18 — PageHeader steals focus on first paint.** `PageHeader` focuses its
  title on mount without the first-render guard used by `AppShell`
  (`PageHeader.js:35` vs `AppShell.js:37-41`). The reference PageHeader has no
  focus effect at all. This affects every hide-header screen on first load.

### Nits

- **UX-19 — Mobile breakpoint is 900px, reference is 960px.** At 901–960px the
  candidate still shows the 264px desktop rail (`_shell.scss:948`;
  `Rail.js:120`; reference `app.css:1968`).
- **UX-20 — Drawer targets and focus are smaller than the reference.** The
  drawer reuses the 36px desktop rail row; the reference uses 44px
  (`Rail.js:163`; `app.css:291-298`). The menu trigger is 36×36 with no hover
  (`app.css:231-248`), no white `:focus-visible` in the drawer/mobile bar
  (`app.css:250-254`), and no safe-area insets (`app.css:284`).
- **UX-21 — Pro dirty flags are one-way.** Reverting a Pro field to the saved
  value still shows "Unsaved changes" (`UpdateShieldPage.js:92`;
  `UpdateFlowPage.js:157`; `WhiteLabelPage.js:103`). Free screens compare
  signatures (`SettingsPanel.js:75-118`).
- **UX-22 — Rail hover and current share the same background.** Both use
  `$updatronix-rail-bg-raised` (`_shell.scss:114`); the reference uses 0.12 for
  hover and 0.18 plus the marker for current (`app.css:143-162`).
- **UX-23 — Some loading states are bare spinners.** Shield and Health render
  `<Spinner/>` without `role="status"` or a label
  (`UpdateShieldPage.js:504`; `HealthPage.js:322`), while sibling states use a
  labelled status.
- **UX-24 — A failed re-fetch is silent once data exists.**
  `AutoUpdatesPanel.js:244` only renders `fetchError` when there is no data.
- **UX-25 — Ctrl+S is active behind the Flow editor dialog.** The
  document-level handler posts the stale `chains` while the editor is open
  (`UpdateFlowPage.js:240`); the dialog should own or block the shortcut.
- **UX-26 — Save-bar shortcut is not hidden on small screens.**
  `_shell.scss:924` keeps it visible; the reference hides it at ≤560px
  (`app.css:441-446`).
- **UX-27 — No JS regression coverage for interactions.** `tests/js/routes.test.js`
  is the only JS test; none of the save/toast/focus/dialog/mobile behaviours
  above have a regression guard.

### Additional test register

| ID | Scenario | Reference expectation | Candidate status |
|---|---|---|---|
| UX-T23 | Failed Settings/Schedule save | Error feedback; draft preserved; no clean state | False "All changes saved" (UX-13). **Mismatch.** |
| UX-T24 | Edit then use rail/popstate | Confirm discard or preserve the draft | Draft lost (UX-14). **Mismatch.** |
| UX-T25 | Clear decryption-broken license | Destructive confirmation naming the site | Immediate call (UX-15). **Mismatch.** |
| UX-T26 | Reset/create/delete before save | No success until persistence | Premature success notices (UX-16). **Mismatch.** |
| UX-T27 | Notices on hide-header vs Pro | One consistent position; clear on navigation | Above H1 on Free, below on Pro; stale (UX-17). **Mismatch.** |
| UX-T28 | First paint of a hide-header screen | No focus stolen | PageHeader focuses the H1 (UX-18). **Mismatch.** |
| UX-T29 | 901–960px viewport | Mobile shell at 960px | Desktop rail still visible (UX-19). **Mismatch.** |
| UX-T30 | Drawer at 390px | 44px rows; hover/focus; safe area | 36px; missing hover/focus/safe area (UX-20). **Mismatch.** |
| UX-T31 | Revert a Pro field to saved value | Clean state | Dirty remains (UX-21). **Mismatch.** |
| UX-T32 | Rail hover vs current | Distinct backgrounds + marker | Same background (UX-22). **Mismatch.** |
| UX-T33 | Shield/Health loading | Labelled status announcement | Bare Spinner (UX-23). **Needs test.** |
| UX-T34 | Auto updates re-fetch failure | Visible error with existing data | Silent (UX-24). **Mismatch.** |
| UX-T35 | Ctrl+S with Flow editor open | No stale save; dialog shortcut handling | Document handler fires (UX-25). **Mismatch.** |
| UX-T36 | Save-bar shortcut at ≤560px | Hidden | Visible (UX-26). **Mismatch.** |
| UX-T37 | Interaction regression suite | Coverage for save/toast/focus/mobile | Only routes test (UX-27). **Missing.** |

## What is already close

- WPDS `Section`/`Card` composition is used on the migrated screens.
- The page content of Update logs, Auto updates, Schedule, Settings, License,
  Shield, Flow, White label, Health, Documentation, and Support is largely
  present.
- The rail order, groups, destination labels, and Pro destinations are in
  place.
- The license, Shield, Flow, and White label pages have the right state/data
  concepts.
- The reference SaveBar/PageHeader components exist in the prototype and can
  be used as the contract for the fix.

## Recommended fix order (not applied)

1. **Shell:** full-bleed mount, 100dvh grid, dark shell background, static
   full-height rail, white `.main` card with internal scroll, `.page` column
   at 1120px.
2. **Header:** one `PageHeader` for every destination; remove the AppShell
   header; use the brand name and Breadcrumb.Root; title 2xl/body-lg.
3. **Save:** move SaveButton into PageHeader actions; implement the fixed top
   SaveBar with the header anchor and `.main` positioning; add it to all saved
   Pro screens; remove the always-on bottom bar; show the shortcut.
4. **Rail details:** sentence-case group labels, reference Pro labelling,
   active marker, brand typography, link density, focus contrast.
5. **Per-screen deltas:** log Note, License header, Shield Log column decision,
   Flow inline rename, dialog size, note styling, page width.
6. **Responsive:** rebuild the mobile bar/drawer against the reference in the
   new shell.
7. **Re-run the visual gate:** compare each screen to its rendered Open Design
   HTML at desktop and mobile widths, and record each remaining deviation.

## Open decisions for the owner

1. **Pro badges:** keep a Pro badge on all four Pro destinations (current,
   `D-19`) or only on Updatronix 3000 as the rendered reference shows?
2. **Shield Log column:** keep the extra Log column in Recently blocked, or
   move the link into the row action to match the reference?
3. **Flow inline rename:** restore the reference inline rename in addition to
   the editor, or accept editor-only rename?
4. **Bottom SaveBar:** remove it entirely in favour of the top scroll bar, or
   keep a bottom fallback for mobile?
5. **Hub:** keep it unshipped per `D-13`?

## Evidence index

- Reference shell: `docs/wpds/reference/screens/src/app.css:55-77,308-355`.
- Reference header/save: `docs/wpds/reference/screens/src/app.css:366-443`,
  `ui.jsx:58-83,120-216`.
- Reference note: `app.css:475-479`.
- Current shell: `assets/src/scss/shell/_shell.scss:11-17,35-47,148-150,
  169-223,232-237,344-349,900-912,942-967`.
- Current header/save: `assets/src/js/shell/AppShell.js:62-110`,
  `PageHeader.js`, `SaveBar.js`.
- Current screen ownership: `assets/src/js/pages/SettingsPage.js:147-154,266`.
- Current rail: `assets/src/js/shell/Rail.js:59-95`,
  `assets/src/scss/shell/_shell.scss:96-128`.
- Current log note absence: `assets/src/js/components/activityLog/ActivityLogPanel.js:539-545`.
- Current Pro local save rows: `UpdateShieldPage.js`, `UpdateFlowPage.js`,
  `WhiteLabelPage.js`.
- Rendered reference: `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/update-log.html`.

## Review status

This document records the findings only. No shell, screen, CSS, or save-bar
code was changed as part of this review.
