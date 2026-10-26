# Screen contracts

The hub and the eleven destinations. Each contract names the route, the page
header, the working surface, the data source, the states, and the acceptance
criteria. The vendored screen sources under
[`reference/screens/src/screens/`](reference/screens/src/screens/) are the visual
reference; this document is the behavioral contract.

## Route and shell summary

| # | Destination | Slug | Edition | Group | Primary action | Save model | Data source |
|---|---|---|---|---|---|---|---|
| 0 | Screens hub | — | Reference | Outside the rail | None | None | Static data; not shipped |
| 1 | Update logs | `logs` | Free | Updates | Export logs | Immediate per action | `updatronix/v1/logs` |
| 2 | Auto updates | `auto-updates` | Free | Updates | Save changes | Manual, whole surface | `updatronix/v1/auto-updates` |
| 3 | Schedule | `schedule` | Free | Updates | Save changes | Manual | `updatronix/v1/settings` (schedule subtree) |
| 4 | Updatronix 3000 | `updatronix-3000` | Pro | Automations | Activate license | Immediate per action | `updatronix-pro/v1/license/*` |
| 5 | Update Shield | `update-shield` | Pro | Automations | Save changes | Manual | `updatronix-pro/v1/shield/*` |
| 6 | Update Flow | `update-flow` | Pro | Automations | Save changes | Manual | New `updatronix-pro/v1/flow/*` |
| 7 | White label | `white-label` | Pro | Automations | Save changes | Manual | New `updatronix-pro/v1/white-label` |
| 8 | Health | `health` | Free | System | Run check | Immediate per action | New `updatronix/v1/health` |
| 9 | Settings | `settings` | Free | System | Save changes | Manual | `updatronix/v1/settings` |
| 10 | Documentation | `documentation` | Free | Help | None | None | Static links |
| 11 | Support | `support` | Free | Help | Open support forum | None | Static links |

Every destination uses the same page header skeleton: breadcrumb
`Updatronix / <destination>`, one title, one description, optional meta badges,
and at most one primary action. A destination without a primary action does not
invent one.

---

## 1. Update logs

**Target.** One `DataViews` table replaces the current card-like rows and the
custom filter bar. Details open in a `Dialog`. Delete uses an `AlertDialog`.
Export opens a `Dialog` with report options, column selection, and a preview.

**Page header.** Title `Update logs`; description “Every core, plugin, theme, and
translation update, with the version before and after.”; meta badge
`Retention <n> days`; primary action `Export logs`.

**Working surface.**

- Toolbar: search (`Search by item or version`), filters toggle, view config.
- Table fields: Item, Type, Version, Trigger, Status, Date, row actions.
- Row actions: `View details for <item>`, `Delete <item> log entry`.
- Pagination and page-size controls from DataViews.
- Details dialog: summary table, process details, copy action, close.
- Export dialog: report options, column selection, preview, download, cancel.
- Delete dialog: object name, consequence, destructive confirm label.

**Data.** Reuse the existing `/logs` list payload, filters, and the field map in
`assets/src/js/components/activityLog/logFilters.js`. Reuse `ExportLogsModal`
behavior; do not reimplement the export engine. The log table keeps its schema.

**States.**

| State | Requirement |
|---|---|
| Logging disabled | A `Notice` explains that logging is off and links to Settings. The table is hidden, not empty. |
| Empty | `EmptyState` with “No updates logged yet” and a link to Settings. |
| Loading | Table skeleton with the real column widths. |
| Error | `Notice` with the server message and a retry action. |
| Deleting | The row action shows progress; the dialog stays open until the result is known. |

**Acceptance.**

- DataViews is the primary view; no custom card rows remain.
- Search, filters, sorting, pagination, and row actions work with the keyboard.
- The details dialog and export dialog return focus to their trigger.
- Delete uses `AlertDialog` with `intent="irreversible"` and names the entry.
- The retention badge reads from settings, not a hardcoded 90.
- No `--wp-components-*` token and no hex value in the log styles.

---

## 2. Auto updates

**Target.** Card sections: Core update mode, Translations, Themes, Plugins. One
manual save for the whole surface. The white sub-navigation disappears.

**Page header.** Title `Auto updates`; description “Core, translation, theme, and
plugin settings in one place.”; primary action `Save changes`.

**Working surface.**

- Core update mode: radio group with minor releases only, all releases, manual only.
- Translations: one switch. Language packs follow the core mode.
- Themes: table with auto-update switch, item, version, item status.
- Plugins: table with auto-update switch, item, version, item status.
- A note explains that changes are staged until Save changes and apply to the
  next scheduled check.

**Data.** The existing `/auto-updates` state and the per-item endpoints. Add a
batch save path (extend `/auto-updates` with a full payload or add
`/auto-updates/batch`) so one Save commits every change atomically. Until the
batch path exists, the screen must not claim atomicity.

**States.** Loading skeleton; empty theme or plugin table; constant notices
preserved from `ConstantNotices`; save error keeps the draft; success notice
names the surface.

**Acceptance.**

- One save control for the whole surface; no per-toggle request.
- Dirty state covers radio, switch, and table changes.
- Constants defined in `wp-config.php` remain visible and are not editable.
- No white secondary navigation column.
- The screen works on multisite, where the settings are network-scoped.

---

## 3. Schedule

**Target.** One card with frequency, preferred time, hold duration, and a
next-run summary. Manual save.

**Page header.** Title `Schedule`; description “Choose how often WordPress checks
for updates, and how long new releases are held.”; meta badge `Next check:
<date>`; primary action `Save changes`.

**Working surface.**

- Frequency: select from the allowed cron schedules.
- Preferred time: select with a description of the next-tick behavior.
- Hold new releases for (days): number, 0 to 30, with a description.
- `Notice` with the next run, computed from the saved schedule.
- A short section explaining what the hold does and that it applies to automatic
  updates only.

**Data.** The schedule subtree of `updatronix_settings` and the schedule meta
from `Updatronix_Cron::get_schedule_rest_meta()`. Preserve the network-scoped
schedule option on multisite (`updatronix_network_schedule`).

**States.** WP-Cron disabled notice; no next run scheduled; validation error for
an out-of-range hold; save error.

**Acceptance.**

- The next-run summary uses the saved values, not the draft.
- The hold value validates on the client and the server (0 to 30).
- The timezone label is explicit.
- Manual save and the keyboard shortcut work.

---

## 4. Updatronix 3000

**Target.** An empty state with the product mark, a short explanation, a license
input, and a primary activation, followed by the Pro feature list. When active:
a success empty state with a masked key and a deactivate action.

**Page header.** Title `Updatronix 3000`; description switches between “Activate
your Updatronix 3000 license for this site.”, “Your license is active for this
site.”, “Your license needs to be activated again.”, “The stored license key
cannot be decrypted.”, and “The license status could not be loaded.”; no
primary action in the header (activation is inside the card).

**Working surface.**

- Inactive: `EmptyState` with key icon, title, description, `InputControl`,
  inline validation, primary `Activate license`.
- Active: `EmptyState` with check icon, title, description, masked key
  (`UPD-PRO-••••••••-••••••••-••••1234`), registered domain, last check, and
  outline `Deactivate`.
- Feature list, with availability:
  - Update Shield — stops incompatible updates and records blocked items.
  - Update Flow — runs updates in dependency order.
  - White label — applies the agency name, mark, and colors.
  - Advanced exports — CSV and JSON.
  - Queue list — **Roadmap** badge; not available in this release.
  - Development tools — **Roadmap** badge; not available in this release.
  - A legend states that roadmap items, including XML export, are planned and
    not part of the current release.
- A note states whether the site is in development mode, has a decryption
  error, or has an invalid stored license.

**Data.** `/license/status`, `/license/activate`, `/license/deactivate`. Keep the
existing encrypted storage, masking, and error states. Do not accept a key that
the server rejects; the client-side format check is a convenience only. The
activation route validates `UPD-PRO-XXXXXXXX-XXXXXXXX-XXXXXXXX`; case is
preserved. A successful activation, deactivation, or clear reloads the admin
page because Pro's PHP hooks and REST routes are registered at boot from the
stored license. The success message survives the reload as a one-shot notice.

**States.** Loading; inactive; activating; active; deactivating; status-service
error; invalid license; decryption error; development mode; Multisite
non-Super-Admin (no access; the contract is Super-Admin-only).

**Error handling.**

- Client format failure and server rejection use the same inline error element,
  set `aria-invalid` and `aria-errormessage` on the input, and return focus to
  the input after render.
- The status endpoint failure renders a dedicated “License status unavailable”
  card with Retry; it does not render the activation form.
- A 429 response names the remaining seconds and carries `retry_after`.
- A decryption error renders its own notice and a confirmed “Clear stored
  license” action. The clear path works even when the stored status is
  `invalid`.

**Acceptance.**

- The page works with Pro active and as the Free-only upgrade destination.
- The key is never rendered in full after activation.
- Server validation errors appear inline, with the field marked invalid and
  focus on the field.
- The feature list matches the product copy and marks roadmap items explicitly.
- The page reloads after a license state change and the rail matches the new
  state.
- No blue legacy button.

---

## 5. Update Shield

**Target.** A dedicated destination with Protection, Update queue, and Recently
blocked sections. One manual save.

**Page header.** Title `Update Shield`; description “Stop incompatible updates,
queue automatic updates, and protect version-controlled extensions.”; primary
action `Save changes`.

**Working surface.**

- Protection card:
  - `Protect this site` switch.
  - Version control lock panel with a lock icon and a `Locked` or `Off` badge.
  - `When an update is incompatible` radio group: cancel and disable the item’s
    auto-updates, or cancel this update only.
  - `Re-enable auto-updates automatically` switch, enabled only for the
    disable-and-reenable behavior.
  - `Email the technical manager` switch, with the recipient and a link to Settings.
  - `Flag abandoned plugins` switch, with the 12-month rule.
- Update queue card: `Queue automatic updates` switch.
- Recently blocked card: item, version, reason, blocked date; link to the update
  log. Last 30 days.
- A note explains that checks use WordPress.org compatibility data and that
  items with no data are allowed but flagged.

**Data.** Existing `/shield/settings` and `/shield/recheck`. The current option
has `safe_mode`, `notify_on_block`, `control_php_version`, and
`control_wp_version`. The target adds `incompatible_behavior`, `re_enable`,
`notify_email`, `queue_enabled`, and `flag_abandoned`. Add `/shield/blocked` for
the table. Preserve the existing keys and defaults for upgrades.

**States.** License inactive (the panel is unavailable; link to the license
page); loading; no blocked items; recheck running; recheck error; save error;
multisite non-super-admin.

**Acceptance.**

- One save commits every field atomically.
- Existing option values survive the schema extension.
- The blocked table is read-only and links to the log entry.
- Every switch has an accessible label and description.
- The license gate is enforced on the server, not only in the UI.

---

## 6. Update Flow

**Target.** Dependency chains with ordered, reorderable steps. Add and edit in a
`Dialog` with an editable chain name and description. Deletion uses
`AlertDialog`. Validation covers names, duplicates, and circular requirements
across chains. One manual save.

**Page header.** Title `Update Flow`; description “Install updates in the order
your site depends on.”; primary action `Save changes`.

**Working surface.**

- Chain list: name, description, step count, enabled state.
- Each chain: ordered steps with move up, move down, remove, and run actions.
- Add chain: opens the editor dialog.
- Editor dialog: chain name, description, step picker, ordered step list, inline
  validation, save and cancel.
- Knowledge check card: explains that chains run before the regular schedule,
  top to bottom, and that all chains are checked together for cycles.
- A note explains that edits are staged until Save changes.

**Data.** New option `updatronix_pro_update_flow` (network scope), new REST
routes `GET/POST /flow/chains` and `POST /flow/run`. The item catalogue comes
from installed plugins, themes, and core. Execution semantics are settled by
`D-16`: chains run before the regular check, on the same cron tick, and stop on
failure. The first implementation must not claim behavior the backend does not
provide.

**States.** Empty chain list; editor validation error; cycle conflict; run in
progress; run failure; license inactive; save error.

**Acceptance.**

- A chain requires a name and at least two steps.
- Duplicate steps are rejected with a field-level message.
- A cycle across chains is blocked before save, and the message names the loop.
- Reordering is keyboard accessible and announces the new position.
- Delete confirms with the chain name.
- The screen is usable without a mouse.

---

## 7. White label

**Target.** Brand name, menu label, logo, brand and menu colors, replacement
switches, and previews of the real admin rail and the real Safe mode email.
Manual save and reset. White label is opt-in: both switches are off by default.

**Page header.** Title `White label`; description “Put your name, mark, and
colors on every Updatronix screen.”; primary action `Save changes`; secondary
`Reset` in the surface.

**Working surface.**

- Brand card: brand name, menu label, logo upload and remove.
- Colors card: brand color and menu background, with accessible color controls.
  The brand color seeds the WPDS theme (buttons, links, focus, active states);
  the menu background drives the rail, shell frame, mobile bar, and drawer.
- Replace card: replace Updatronix branding, apply the brand name to Safe mode
  notification emails.
- Preview card: the real visible rail destinations and the real plain-text Safe
  mode email, driven by the draft values.
- Reset returns every field to the opt-in default and marks the surface dirty.
- A successful save reloads the admin page so the server-rendered rail and menu
  label update immediately; a one-shot notice survives the reload.

**Data.** Option `updatronix_pro_white_label` (network-wide per `D-15`), REST
route `/white-label`, and a logo attachment reference.

| Field | Rule |
|---|---|
| `brand_name` | `sanitize_text_field`, max 60 characters. |
| `menu_label` | `sanitize_text_field`, max 40 characters. |
| `brand_color` | `sanitize_hex_color`; invalid values fall back to `#3858E9`. |
| `menu_color` | `sanitize_hex_color`; invalid values fall back to `#1E1E1E`. |
| `logo_id` | Positive attachment ID; exists; attachment; PNG/JPEG/WebP; not SVG; readable; `upload_files`; ≤ 1 MB; ≥ 64×64 px. A changed invalid ID returns 400. |
| `replace_branding` | Boolean; default false. |
| `apply_to_emails` | Boolean; default false; requires `replace_branding`. |

Never accept a logo URL, a file path, or raw SVG. Removing or resetting the logo
removes the attachment reference, not the Media Library file.

**Apply points.** The branding applies only to:

- the Updatronix admin menu label (Tools on single site; Network Admin on
  Multisite);
- the shell rail brand name, mark, and colors, and the page breadcrumb;
- the brand color through the WPDS theme for buttons, links, and the active rail
  item; the menu color for the rail and shell background;
- the brand name in the Safe mode blocked-update notification email.

The WordPress admin menu background, other plugins' menus, the site title, the
login screen, and the WordPress logo are untouched.

**States.** License loading; license status error; license inactive; settings
loading; load error with Retry; no logo; upload in progress; upload rejected for
type, size, dimensions, or capability; preview light and dark menu; reset
confirmation; save success; save error; Multisite read-only note.

**Acceptance.**

- The preview reflects the draft, not the saved values.
- The previewed rail and email match what the product actually renders.
- A logo removal removes the attachment reference, not an arbitrary file.
- The branding applies at the points above and nowhere else.
- Reset restores the opt-in defaults and is reversible until save.
- The screen works with the keyboard and a screen reader; color controls have
  accessible names and the previews have text descriptions.
- A failed load or license-status check shows a Retry state, never a permanent
  spinner.
- A successful save updates the real rail and menu after the reload.


---

## 8. Health

**Target.** A readiness summary and six environment checks above the open-issue
retry flow and the resolved archive. Run check and diagnostics actions. The
paused-retry warning stays next to its cause.

**Page header.** Title `Health`; description “See whether this site is ready for
its next update, and what needs attention.”; primary action `Run check`.

**Working surface.**

- Readiness summary: a badge and one sentence.
- Checks card: WordPress version, PHP version, disk space, WP-Cron, update lock,
  automatic retries. Each row has a value, a status badge, and a detail.
- Open issues: failed updates with severity, item, versions, code, message,
  attempts, and a `Retry` action. The detail dialog shows the full process.
- Recently resolved: failures cleared in the last 30 days, with the resolution.
- Diagnostics action: a dialog with the environment summary and a copy action.

**Data.** New `/health` endpoint that composes WordPress site health data,
Updatronix log failures, cron state, disk space, and retry state. Reuse
`Updatronix_ErrorHandler` data where possible. Resolved retention is `D-17`.

**States.** All good; attention; critical; no open issues; check running; check
failed; retry queued; retry failed; multisite.

**Acceptance.**

- Every check names what it measures, the current value, and the consequence.
- A failed check has a next action or states that none exists.
- Retry is idempotent and reports the result.
- The summary agrees with the checks; no contradiction between the badge and the rows.
- The page does not duplicate the update log; it links to it.

---

## 9. Settings

**Target.** Three cards: Logging, Email notifications, Data. Email keeps
recipients, per-event filters, a disable-all switch, and the recovery-mode
warning. Data holds the opt-in uninstall deletion switch. One manual save.

**Page header.** Title `Settings`; description “Logging, retention, email routing,
and uninstall data.”; primary action `Save changes`.

**Working surface.**

- Logging: keep an update log checkbox, retention days (1 to 365).
- Email notifications: recipients, per-event checkboxes (core, plugins and themes,
  debug summary, technical alerts, recovery mode always on), disable all update
  emails, recovery-mode warning.
- Data: delete all data on uninstall, off by default, with an explanation.

**Data.** `/settings`. Keep the existing keys: `logging_enabled`,
`retention_days`, `notifications_mode`, `notify_enabled`, `notify_emails`,
`notify_on`, `auto_update_translations`, `dismissed_constants`,
`delete_data_on_uninstall`. Do not rename a stored key without a migration.

**States.** Validation error for retention; invalid email; recovery warning;
save error; multisite.

**Acceptance.**

- Retention validates on both sides (1 to 365).
- Recipients accept a comma-separated list and reject malformed addresses.
- Recovery mode cannot be disabled; the control is disabled with an explanation.
- The uninstall switch is off by default and is clearly destructive.
- No General and Advanced sub-tabs.

---

## 10. Documentation

**Target.** One page header and three linked guide cards: the plugin, Updatronix
3000, and the WordPress update system. Links open on `holdmywp.com`.

**Page header.** Title `Documentation`; description “Guides for Updatronix,
Updatronix 3000, and the WordPress update system.”

**Working surface.** Three `Card` items with an icon, a title, a one-sentence
description, and an external-link affordance. External links open in a new tab
with `rel="noopener noreferrer"` and an accessible “opens in a new tab” hint.

**Data.** Static constants. No REST call.

**Acceptance.**

- The page renders with no network request other than its own assets.
- Every link is reachable and points to the product documentation.
- The page is translatable; URLs are not translated.

---

## 11. Support

**Target.** Four recommendations before posting, a primary link to the
WordPress.org support forum, and a cross-link to Documentation.

**Page header.** Title `Support`; description “What to check before you post, and
where to get help.”

**Working surface.** Four numbered recommendations with short explanations, a
primary `Open support forum` link, and a secondary link to Documentation.

**Data.** Static constants. No REST call.

**Acceptance.**

- The forum URL matches the readme.
- The checks are actionable and reference real screens.
- The cross-link keeps the user inside the shell.

---

## 12. Screens hub (reference only)

The hub is the design-review index outside the rail. It is not shipped; see
`A-01` in [`02-decisions.md`](02-decisions.md). The vendored source remains the
map of the screen set. If a product decision later ships it, it becomes a twelfth
destination and needs its own route, capability, and acceptance criteria.

---

## Shared acceptance for every destination

- One page title, one description, at most one primary action.
- Every color, space, radius, and duration comes from a `--wpds-*` token.
- No legacy `.button`, `.notice`, `#2271b1`, or `--wp-components-*` value.
- Loading, empty, error, disabled, dirty, saving, and saved states are handled.
- Keyboard: every action reachable; focus visible; dialogs trap and restore focus.
- Screen reader: landmarks, accessible names, live announcements for async results.
- RTL: layout flips with logical properties; icons that imply direction mirror.
- i18n: English source; no concatenated sentences; text domain correct.
- Multisite: the destination respects the network capability model.
- No new remote request, telemetry, or third-party asset.
