# Manual regression gate (Updatronix)

Manual checklist to run **before tagging a release**. Pair this with the automated suite in `workflow.md` — automated tests catch regressions in code paths that have tests; this list catches the rest (UX, real cron firing, real WordPress emails, real multisite behaviour, real upgrades through the admin UI).

Tick each item. If a step fails, file an issue and block the release until it passes.

## How to use

- Run on a **staging copy** of a real site, not on production.
- Run **once on single-site** and **once on multisite** before any release that touches admin UI, settings storage, REST, cron, or notifications. Multisite-only steps are flagged `[MS]`.
- Steps that are only relevant when a feature was touched in the release are flagged `[changed]` — skip them when the release is documentation-only.
- For features added in 1.1 (Schedule tab, hold updates, notifications-disable switch, network schedule), every step in those sections is mandatory until the next major release.

## Environment record

Fill in before starting:

- WordPress version: ___
- PHP version: ___
- Plugin version under test: ___
- Browser + version: ___
- Single site / multisite (subdomain or subdirectory): ___
- Tester: ___
- Date: ___

## 0. Pre-flight (automated)

Run from the plugin root before any manual steps. Details in `workflow.md`.

- [ ] `npm run build:all` passes end to end (includes `verify:php`, Plugin Check, ESLint, Stylelint, Prettier, POT, build).
- [ ] `bin/harness integration` passes (single-site integration suite).
- [ ] `WP_MULTISITE=1 bin/harness integration --filter Multisite` passes (multisite suite).
- [ ] `git status` is clean apart from `assets/build/` and `languages/updatronix.pot` updates that belong to the release.

## 1. Install, activate, deactivate, uninstall

- [ ] Fresh **install** from the WordPress plugin installer (or upload zip from `npm run zip`) succeeds with no PHP notices in `debug.log`.
- [ ] **Activation** creates the `{prefix}updatronix_logs` table (check via WP-CLI: `wp db query "SHOW TABLES LIKE '%updatronix_logs'"`).
- [ ] Activation schedules the daily cleanup cron (`wp cron event list | grep updatronix`).
- [ ] **Deactivation** clears the cleanup cron but keeps the log table and the `updatronix_settings` option.
- [ ] **Uninstall** (delete plugin from the Plugins screen) drops the log table, removes `updatronix_settings`, and on multisite removes `updatronix_network_schedule` exactly once.

## 2. Capabilities and admin UI

- [ ] As **Administrator**, **Tools → Updatronix** and **Dashboard → Update logs** appear; the React shell loads with no console errors and no missing translations.
- [ ] As **Editor / Subscriber**, neither menu entry appears; direct navigation to `tools.php?page=updatronix` returns access denied.
- [ ] `[MS]` On multisite, only **Super Admin** sees Updatronix under **Network Admin**; subsite administrators see no menu, notices, or plugin UI.

## 3. REST API auth

Test with browser DevTools **Network** tab on the settings page, or `curl` with a valid `wordpress_logged_in_*` cookie plus `X-WP-Nonce` from `wpApiSettings.nonce`.

- [ ] **GET** `/wp-json/updatronix/v1/settings` as Administrator → **200**, body has `options` and `schedule_meta`.
- [ ] **GET** without cookies → **401 or 403**.
- [ ] **POST** `/wp-json/updatronix/v1/settings` as Administrator with valid `X-WP-Nonce` → **200**; reload the page and the change is persisted.
- [ ] **POST** without `X-WP-Nonce` (cookie session only) → REST cookie check failure (**403**).
- [ ] **POST** as low-privilege user with cookies → **403**.
- [ ] `[MS]` **POST** to `/settings` with a `schedule` payload as **Super Admin** (Network Admin context) → **200**, the network schedule option **is** updated.
- [ ] `[MS]` Subsite administrators cannot reach Updatronix REST routes (plugin does not load on subsite requests).

## 4. Update logging

- [ ] **Plugin update happy path** — Trigger an update through **Dashboard → Updates** (or `wp plugin update`); confirm a new row appears in **Update logs** with correct From/To versions, the right action label, and a `Success` status.
- [ ] **Theme update happy path** — Same, for a theme.
- [ ] **Translation update** — Trigger a translation update; confirm an entry appears categorised as a translation update.
- [ ] **Core update** (when a real or simulated offer exists) — Confirm a core update produces an entry.
- [ ] **Failure path** — Force a failure (e.g. break filesystem perms or use an invalid `update.zip` URL via filter); confirm a `Failed` entry appears, and opening it shows the captured `WP_Error` rather than a generic message.
- [ ] **Detail modal** — Open any entry; confirm the modal shows the version snapshot, the actor (user vs. cron vs. WP-CLI), and the error block when applicable.
- [ ] **Filtering** — On a list with several entries, filter by **Category**, **Action**, **Date**, and **User**; confirm each filter narrows the list correctly and clearing the filter restores it.
- [ ] **Per-row delete** — Use the per-row delete action; confirm the entry disappears from the list and the DB row is gone.

### Export update logs `[changed in 1.1]`

Run from the **Export update logs** modal (the **Export** button above the logs list). Seed several entries across core, plugins, themes, and translations, including at least one multi-event item and one failure, before testing.

- [ ] **Filters applied summary** — Apply Category, Action, Date, and User filters in the list, then open the modal; confirm the **Filters applied** summary reflects exactly those filters and the sort line shows the active sort.
- [ ] **Generate export (merge on)** — With **Merge logs for the same item** on, generate the export; confirm rows are grouped under `== CORE ==` / `== PLUGINS ==` / `== THEMES ==` / `== TRANSLATIONS ==`, ordered most-recent-first, with version spans and comma-separated dates for merged items.
- [ ] **Generate export (merge off)** — Turn merge off; confirm a single flat list sorted by date with a leading **Category** column, and that the sort line appears.
- [ ] **Report columns** — Uncheck each of the six column toggles in turn and regenerate; confirm the corresponding column (heading row, action, run context, user, status, category) disappears and Element/Version/Date always remain. Reopen the modal; confirm the toggle state persisted (localStorage).
- [ ] **Translation element name** — Confirm translation rows show the slug (e.g. `akismet`), not a display name.
- [ ] **User column** — Confirm manual updates show the operator's display name and automatic updates show `System`; a merged item touched by two users shows both, comma-separated.
- [ ] **Copy with formatting → code editor** — Click **Copy with formatting**, paste into a monospace code editor; confirm columns stay aligned (NBSP padding) and the dash separator row is intact.
- [ ] **Copy with formatting → Word / Gmail** — Paste into Microsoft Word and a Gmail compose window; confirm the monospace `<pre>` block preserves alignment in both rich-text targets.
- [ ] **Copy without formatting → email plain text** — Click **Copy without formatting**, paste into a plain-text field; confirm fields are double-spaced with no dash rules or fixed-width padding, and section headings are preserved.
- [ ] **Screen-reader announcement** — After generating, confirm assistive tech announces "Export ready. Copy the report to save it. It expires after 15 minutes."; after each copy, confirm the formatted/plain copied announcement fires.
- [ ] **Empty result** — Apply a filter that matches no logs and generate; confirm the info notice "No logs match the current filters. The export is empty." appears and no output textarea is shown.
- [ ] **Expiry** — Generate an export, wait > 15 minutes, then attempt a continuation (large export): confirm the session-expired path surfaces "This export session has expired. Start a new export."
- [ ] **Rate limit** — Start many exports in quick succession; confirm the "Too many exports started recently…" notice eventually appears (HTTP 429 with `Retry-After`).

## 5. Auto-updates tab

- [ ] **Core mode switch** — Move between *every release*, *security and minor only*, and *fully manual*; reload and confirm the choice persists. Each choice flips the right `auto_update_*` site option.
- [ ] **Per-plugin toggle** — Toggle auto-update for one plugin from this tab; verify the plugin's row in **Plugins** reflects the same state.
- [ ] **Per-theme toggle** — Same, for a theme.
- [ ] **Translations toggle** — Toggle the translations auto-update; confirm WordPress respects the choice on the next translation check.

## 6. wp-config constants

Edit `wp-config.php` between each step (`wp-config.php` requires a manual file edit; settings should change as soon as the page is reloaded).

- [ ] **`AUTOMATIC_UPDATER_DISABLED = true`** → A locking notice appears on the Auto-updates tab; every auto-update toggle is disabled and explains why.
- [ ] **`DISALLOW_FILE_MODS = true`** → Same locking notice, with copy that mentions file modifications are blocked.
- [ ] **`define('WP_AUTO_UPDATE_CORE', 'minor')`** → Core mode switch is locked to *security and minor only* and shows the constant name.
- [ ] **`define('DISABLE_WP_CRON', true)`** → A dismissible notice appears on the Schedule tab explaining that scheduled tasks won't run on normal visits and the host needs to call `wp-cron.php` on a timer.
- [ ] Dismissing the `DISABLE_WP_CRON` notice → it stays dismissed across reloads (allowlist + dismissed-list mechanism).
- [ ] **REST refusal** — Send a `dismiss-constant` REST request with a constant name that is **not** in the allowlist → **400** with a translatable error.

## 7. Schedule tab `[changed in 1.1]`

### Update checks

- [ ] **Default state** — On a fresh install, the **WordPress recurrence** is selected; the descriptive text says WordPress picks the schedule.
- [ ] **Switch to custom recurrence** — Pick *every hour*, save → next-run preview updates and `wp cron event list | grep wp_version_check` shows the new interval.
- [ ] Same for *twice a day*, *daily*, and *weekly*.
- [ ] **Time of day** — With *daily* or *weekly* selected, set a wall-clock time; reload; confirm the next-run preview is anchored on that time **in the site's `wp_timezone()`**, not UTC.
- [ ] **DST awareness** — Read the help text under the time picker; confirm it warns that recurring runs may shift by up to one hour after a DST transition.
- [ ] **Self-heal** — Manually delete the `wp_version_check` cron event (`wp cron event delete wp_version_check`); load any wp-admin page; confirm the event is restored automatically and the next-run preview is consistent.

### Hold automatic updates

- [ ] **Enable hold** — Turn on **Hold automatic updates**, set delay to e.g. `7` days, save.
- [ ] **Persistence** — Reload; the toggle and the value are still there.
- [ ] **Notice on Updates / Plugins / Themes screens** — Visit each of the three screens; confirm the notice appears, says how many days the hold is for, and disappears when the hold is turned off.
- [ ] **Bounds** — Try `0`, `366`, and `-1` via REST; confirm the schema rejects them (`400`).
- [ ] **Per-release semantics** — Confirm that the per-item delay ledger (`updatronix_auto_update_delay_ledger`) records first-seen timestamps so a 7-day hold means an offer is ≥ 7 days old before it installs.

### Network-scoped schedule `[MS]`

- [ ] **Super Admin saves a schedule** — Confirm `updatronix_network_schedule` site option is written and the change is visible from any subsite.
- [ ] **Subsite Administrator tries to save a schedule** — UI shows the warning notice ("Schedule changes were not saved because they affect every site on this network..."); the network option is unchanged.

## 8. Notifications `[changed in 1.1]`

### Recipient redirect

- [ ] **Single recipient** — In **Settings → Manage update notifications**, set one address. Trigger a notification (e.g. force a successful or failed plugin update). Confirm the email arrives at the configured address, **not** the site admin.
- [ ] **Comma-separated list** — Set 2–3 addresses; trigger a notification; confirm each address receives it.
- [ ] **Header injection guard** — Try to set `victim@example.com\r\nBcc: attacker@example.invalid`; confirm only the legitimate address is accepted (the second part is stripped by `updatronix_sanitize_emails()`).
- [ ] **Recipient cap** — Paste 40 addresses; confirm only the first 32 are accepted (`UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS`) and the surplus is discarded silently.
- [ ] **Raw size cap** — Paste a deliberately oversized string (> 4 KB); confirm it is truncated at `UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES` and the form still saves.

### Per-event filters

- [ ] In the **notify on** checklist, leave only **Core** ticked; trigger a plugin update notification; confirm **no** plugin email is sent and core notifications still go through. Repeat the matrix swap for **Plugin and theme**, **Debug summary**, and **Technical alert**.

### Disable-all switch

- [ ] **Turn on** *Disable all update notification emails*; trigger every category of update notification; confirm **none** are sent (core, plugin, theme, debug summary, technical alert).
- [ ] **Recovery mode email** — Force a fatal error in a mu-plugin (e.g. `throw new RuntimeException('test');` in admin context) so WordPress enters recovery mode; confirm the recovery email **is still sent** to the site administrator (the disable switch must not suppress it).

## 9. Cron

- [ ] **Updatronix retention cron** is scheduled and shows in `wp cron event list` after activation.
- [ ] **Manual run** — `wp cron event run updatronix_cleanup_logs`; confirm logs older than the retention window are deleted and newer ones are kept.
- [ ] **Front-end visit does not arm cron** — On a request without `wp_doing_cron()` / `is_admin()` / `WP_CLI`, confirm `Cron::maybe_schedule_if_needed()` and `maybe_heal_update_check_schedule()` do **not** run (look for unexpected `wp_schedule_event` calls in Query Monitor).
- [ ] **Unrelated settings save does not reset cron** — Save a non-schedule setting (e.g. recipient list); confirm the next `wp_version_check` timestamp does **not** shift.
- [ ] **Scheduled saves rearm cron** — Save the Schedule tab; confirm the next-run timestamp on `wp_version_check` matches the new schedule.

## 10. Multisite `[MS]`

Run on a real network (subdomain and subdirectory if you support both).

- [ ] **Network-global logs** — The Network Admin update history lists entries from **every** site on the network by default (run `wp core update`/`wp plugin update` with `--url=<subsite>` to generate a subsite-tagged entry and confirm it appears without any per-site selection).
- [ ] **Site deletion cleanup** — Delete a subsite that has log entries; its entries no longer appear in the Network Admin history (rows for that `site_id` are purged).
- [ ] **Network schedule** — A change made by Super Admin on the network is visible from every subsite's Schedule tab (read-only) and drives the actual `wp_version_check` event.
- [ ] **Network storage** — Settings saved from Network Admin are identical on every subsite (shared site options).
- [ ] **Uninstall on a network** — Deleting the plugin removes per-site data on **every site** in the network and removes the network-wide `updatronix_network_schedule` option exactly once.

## 11. Accessibility spot-checks

- [ ] **Keyboard only** — Reach every interactive control on every tab using **Tab / Shift+Tab / arrow keys / Space / Enter**; no control is unreachable, no focus trap.
- [ ] **Visible focus** — Focus outline is visible on every focusable element on every tab (no `outline: none` regressions).
- [ ] **Screen reader smoke** — With NVDA or VoiceOver, sweep the Settings page; every form control announces a label; the live region announces save success / save error / "Log deleted." messages.
- [ ] **Notices** — Constant notices (locking and dismissible) are announced to assistive tech (`role="alert"` or equivalent via `Notice`).
- [ ] **Contrast** — The timezone hint, the DST help text, and the recovery-mode help text meet at least the same contrast as the surrounding body text (no `variant="muted"` regressions).

## 12. i18n and l10n

- [ ] `languages/updatronix.pot` was regenerated for this release if any translatable string changed (run `composer run make:pot` and verify the diff matches the changed strings).
- [ ] **Locale switch** — In **Settings → General**, set the site language to a locale with translation files available (or stub a `.po` for `updatronix`); reload the plugin pages; confirm the strings switch and the JS bundle picks up the JSON translations.
- [ ] **No mixed text domains** — `rg "'updatronix'" --type=js | wc -l` is non-zero and `rg --no-filename --multiline "(__|_e|_n|_x|esc_html__|esc_attr__)\(\s*'[^']+',\s*'(?!updatronix)" assets/src inc | wc -l` is zero (no foreign text-domain leaks).

## 13. Browser sanity

Run the admin UI smoke test in each:

- [ ] **Latest Chrome** — Tabs switch, settings save, logs filter, modals open and close.
- [ ] **Latest Firefox** — Same.
- [ ] **Latest Safari** (macOS or iOS Simulator) — Same; check that date pickers and time inputs render correctly.
- [ ] **No console errors** in any of the three on any tab.

## 14. Performance / autoload budget

- [ ] **Autoload check** — `wp option get updatronix_settings --format=json` returns a JSON blob, not megabytes; the option is autoloaded but small (single JSON document).
- [ ] **Autoload total** — Sanity-check that `SELECT SUM(LENGTH(option_value)) FROM {prefix}options WHERE autoload IN ('yes', 'on')` did not grow disproportionately versus the previous release.
- [ ] **Asset bundle** — `assets/build/` chunks are present, gzip-friendly, and within the Webpack size budget (no `WARNING in entrypoint size limit exceeded`).

## 15. Sign-off

When every box above is ticked:

- [ ] Tester signs off here: ___ / ___ / ___
- [ ] Release notes (`readme.txt` `== Changelog ==` and `== Upgrade Notice ==`) match the actually shipped behaviour and call out any user-facing breaking change.
- [ ] `Stable tag:` in `readme.txt` matches the version in `updatronix.php` (`UPDATRONIX_VERSION`) and the git tag about to be created.

---

Quality bar (automated) — for cross-reference, the canonical pipeline lives in **`workflow.md`**:

- `composer run verify:php` (WPCS + PHPStan + unit tests)
- `composer run lint:pcp` (Local / WP-CLI)
- `npm run lint` / `npm run lint:css` / `npm run format` when JS/CSS changed
- `bin/harness integration` (single-site integration tests)
- `WP_MULTISITE=1 bin/harness integration --filter Multisite` (multisite integration tests)

Or run the full pipeline minus integration tests with `npm run build:all`.
