=== Updatronix - Update Manager Enhanced ===
Contributors: quentinldd
Donate link: https://buymeacoffee.com/quentinld
Tags: updates, auto-update, maintenance, security, audit-log
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 2.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor every update and control every automatic update, so you can update your site without the worry.

== Description ==

WordPress applies your updates but keeps no record of what changed. Updatronix keeps that record and puts the automatic update settings in one place.

It uses WordPress's own update engine. You get a clear history of every update and every automatic update setting on one page, so a failed update is a fact you can check instead of a guess.

= Update without the worry =

You do not have to choose between staying updated and staying safe. Updatronix shows you what changed, what is waiting, and what will happen next. When an update goes wrong, the log gives you the exact version, trigger, and error to work from.

= Who it is for =

* **Solo site owners:** Keep your site up to date and know what changed.
* **Freelancers:** When a client asks what you did, the answer is in the log.
* **Developers:** Control automatic updates and scheduling, with the detail behind every event.
* **Agencies:** Run the same update policy on every client site, each with its own log.

== Features ==

= Update logs =

Every core, plugin, theme, and translation update is logged with its before and after versions, what triggered it, and how it ended. The log also records the reason, state, origin, stage, pass, chain, predecessor, next eligible time, and attempt behind each update. Existing rows show "Legacy - reason not recorded" where those fields do not apply.

Filter the log by reason, state, or origin, search it, and export it as a clean report. The Date column is optional; Element and Version are always included.

= Auto updates =

Automatic update settings are scattered across several places in WordPress. Updatronix brings them together on one page: core, plugins, themes, and translations. The plugin, theme, and update-core screens also show whether an update installs automatically, or is waiting, held, queued, blocked, or managed.

= Schedule =

Choose how often WordPress checks for updates, down to the hour and minute. Pick the time of day, and hold new releases for 0 to 365 days so unstable builds get fixed before they reach you. The screen previews the next run and keeps the saved frequency.

= Health =

Health checks show what needs attention before the next update, with a retry action when a check fails.

= Settings =

Set how long the log is kept, route WordPress update emails to the right address, or turn them off entirely. Recovery mode emails stay on, so you won't lock yourself out. You can also choose whether uninstall removes your data.

== Updatronix 3000 ==

**Updatronix 3000** is the Pro edition for sites that need the update routine to run itself. It adds the automations on top of the same native engine:

* **Update Shield:** checks every automatic update against your WordPress and PHP versions before it runs, and blocks what does not match.
* **Version Lock:** keeps automatic updates off for VCS checkouts and Composer-managed items, and names the manager.
* **Update Flow:** orders the automatic pass so dependencies run in the right sequence.
* **One automatic pass:** WordPress makes one native call per item and keeps its lock, maintenance mode, and rollback.
* **CSV and JSON exports, White label, and Developer tools.**

Updatronix 3000 requires the free Updatronix plugin.

== Privacy ==

Nothing leaves your site. No analytics, no telemetry, no third-party calls. Updatronix reads from WordPress and writes to your site's storage, and that's the whole network footprint.

== Accessibility ==

Updatronix aims to be fully accessible to all of its users. If you run into a problem, open a support thread on the plugin page and it'll get fixed.

== Multisite Support ==

Updatronix supports multisite networks. Network-activate it once and manage settings, update history, schedule, and email controls from the Network Admin, with one unified update log.

== Screenshots ==

1. Update logs: The chronological view, with status, trigger, and version change for every entry.
2. Update logs: Filtering controls in action, with active filter tags and a reset option.
3. Update logs: The export modal, with filter summary, merge option, column selection, and generated report.
4. Update logs: The export modal, showing plain-text report output with copy options.
5. Update logs: A single entry expanded to show its full detail.
6. Update logs: The delete confirmation dialog for a single log entry.
7. Auto-updates: Core update mode, translation toggle, and per-theme controls with status, version, and description columns.
8. Auto-updates: Per-plugin controls with status, version, and description columns.
9. Schedule: Recurrence, preferred time of day, next run preview, and the delay duration setting.
10. Settings: Logging toggle, retention period, and email notification routing.
11. Settings: Disable-all-emails option.

== Installation ==

1. Search for "Updatronix" in **Plugins → Add New**, or upload the plugin files to `/wp-content/plugins/updatronix/`.
2. Activate the plugin from the Plugins screen.
3. Open **Tools → Updatronix** (or **Dashboard → Update logs**) to see the history and adjust the settings.

Activation creates the log table and schedules a daily cleanup. Deactivation cancels the cleanup but leaves your data alone. Deletion removes everything. On multisite, network-activate the plugin from the Network Admin; its data lives at the network level.

== Support ==

Questions? Open a thread on the [support forum](https://wordpress.org/support/plugin/updatronix/).

If Updatronix helps you, [leave a review](https://wordpress.org/plugins/updatronix/#reviews) and tell your friends. You can also sponsor development through [GitHub Sponsors](https://github.com/sponsors/quentin-ld/dashboard).

== Frequently Asked Questions ==

= Where do I see the history of updates on my site? =

Open **Tools → Updatronix**. The first tab is the log: date, item, version change, outcome, and the decision behind it. Click any row to see the full detail. Logging is on by default.

= Can I export my update log? =

Yes. Filter the log, then click **Export logs**. The free plugin exports a clean plain-text report. Updatronix 3000 adds CSV and JSON.

= Can I turn off WordPress update notification emails completely? =

Yes. In **Settings**, turn on **Disable all update notification emails**. Recovery mode emails are deliberately kept on: without them, you could lock yourself out of your own site.

= Can I delay automatic updates? =

Yes. In **Schedule**, enable **Delay updates** and choose how many days WordPress should wait after a release appears, from 0 to 365. At 0 the hold is off. Updates stay automatic; they are deferred, so unstable builds get fixed before they reach you.

= Does Updatronix check whether an update is compatible with my site? =

The free plugin records what happened and shows what is waiting or held. Updatronix 3000 adds **Update Shield**, which checks an automatic update against your WordPress and PHP versions before it runs and blocks a mismatch.

= Will an update break my site? =

No plugin can promise that. Updatronix gives you the record and the controls: hold new releases, see what changed, and export the detail when something goes wrong. With Updatronix 3000, an incompatible automatic update is blocked before it runs.

= Does Updatronix work with my plugins, themes, and host? =

Yes. Updatronix hooks into the same update pipeline WordPress already runs, so everything that updates through the standard system gets logged, whether it comes from WordPress.org, a private source, or your host's mirror.

= Does Updatronix replace WordPress's native update system? =

No. Updatronix builds on WordPress's update engine without replacing it. Everything that goes through the standard update system keeps working as before; Updatronix logs and manages, nothing more.

= Can Updatronix undo a failed update? =

No, and that is not its purpose. When an update fails, Updatronix captures the WordPress error and the version snapshot before WordPress moves on. That is the data you need to recover by hand, or to hand to your host's support. For extra peace of mind, schedule updates right after a backup.

= Where does my data go? =

Nowhere. Logs and settings stay on your site. The plugin makes no outbound network calls of its own: every API it touches is one WordPress was already going to call without it.

= How long are log entries kept? =

Up to you. In **Settings**, set the retention window between 1 and 365 days. The default is 90 days; a daily cleanup drops anything older.

= Does Updatronix work on multisite? =

Yes. Network-activate Updatronix and manage everything as a Super Admin from the Network Admin. Settings, schedule, email controls, and update history are shared across every site in one unified log.

= Do I need Updatronix 3000? =

No. The free plugin monitors updates and controls the automatic update settings on its own. Updatronix 3000 adds the automations: compatibility checks, Version Lock, ordered passes, CSV and JSON exports, White label, and Developer tools.

== Changelog ==

= 2.0.0 =
* New: The activity log records the reason, state, origin, stage, pass, chain, predecessor, next eligible time, and attempt behind each update. Filter by reason, state, or origin, or open a pass or chain from the log. Existing rows stay and show "Legacy - reason not recorded" where the new fields do not apply.
* New: Schedule accepts every hour and every minute, keeps the preferred time when the frequency ignores it, and holds new releases for 0 to 365 days. The screen shows the WordPress default instead of an empty placeholder and drops the separate hold table.
* New: Export update logs as a clean report. The Date column is optional, and Element and Version are always included.
* New: The admin uses the WordPress Design System: a dark rail, eleven destinations, one header per screen, and one save button. Health, Documentation, Support, Update Shield, Update Flow, and White label are part of the shell, and White label colors apply to it.
* Improvement: The plugin, theme, and update-core screens show whether an update installs automatically, or is waiting, held, queued, blocked, or managed. Update-core shows a one-paragraph next-pass summary; the next update check is no longer shown as the install time.
* Fix: Theme log details load the theme API before fetching a changelog, so the REST details request no longer fatals.
* Fix: Cmd/Ctrl+S no longer opens the browser save dialog on Updatronix screens, and dirty screens still save.
* Fix: Health centers its empty state, and Settings no longer shows the redundant save and email notes or the recovery-mode notice.
* Note: Updatronix 3000 0.8.0 runs one automatic update pass and no longer uses the legacy queue table. Take a database backup before upgrading; queue rows are not migrated.
* Copy: The interface follows the WordPress Documentation Style Guide across all eleven destinations: US English, sentence case, second person, straight quotes, and no ellipses.
* Dev: The plugin bundles the pinned WordPress Design System packages and shares one runtime with Updatronix 3000. `updatronix_log_decision_layer_enabled` and `updatronix_native_update_time_enabled` are the kill switches.
* Compatibility: WordPress 6.2 or later and PHP 8.1 or later.

= 1.3.0 =
* Fix: Chunked exports no longer return HTTP 500 and discard their transient on a continuation request.
* Fix: Chunked exports advance past unrenderable rows, enforce a server-side chunk cap, and honour the wall-clock limit.
* Fix: Log redaction covers license_key and api_key parameters, Bearer tokens, and short log fields.
* Fix: A pre-install callback no longer raises a TypeError when WordPress passes a WP_Error.
* Fix: Cron self-heal no longer runs on unauthenticated admin-ajax requests, and unified scheduling no longer rewrites the cron option on every request.
* Fix: Multisite uninstall only destroys network data from the Network Admin or WP-CLI.
* Fix: The subsite activation notice is only consumed by a network administrator.
* Fix: Capability migration no longer writes role state on unauthenticated front-end requests.
* Fix: Log messages strip markup after decoding entities, and redirect-based failure logging requires the real update-core.php path.
* Dev: The logs table check escapes LIKE wildcards, caches per blog, and only records the schema version when the table exists.

= 1.2.0 =
* Security: Harden security.
* Dev: Harden WordPress Coding Standards.
* Improvement: Update interface components.
* Documentation: Align the author name across the plugin header, the interface footer, and the package metadata.

= 1.1.6 =
* Fix: Builded JS not delivered.

= 1.1.5 =
* Improvement: Enforce multisite compatibility.
* Improvement: Update WordPress UI components.
* Documentation: Screenshots update.
* Documentation: Readme update.
* Dev: Harden tests suite.

= 1.1.4 =
* Tested up to WordPress 7.1.

= 1.1.3 =
* Fix: Auto-updates: Scheduled auto-updates no longer error when update delay is enabled.
* Documentation: Edit 1.1.1 changelogs.

= 1.1.2 =
* Dev: Adopt WordPress Coding Standards, plugin tested up to WP 7.0.3.

= 1.1.1 =
* New: Updatronix 3000 connector (Pro version).
* Fix: Keep the schedule recurrence, preferred time, and hold delay after visiting update pages or when external tools clear the cron table.
* Fix: Correct activity log filters, pagination, and totals, add the missing info and warning statuses, and show an error in log details when a fetch fails.
* Fix: Remove stale auto-update entries for deleted plugins and themes, and improve core version tracking.
* Improvement: Enforce WordPress auto-update restrictions server-side and stop admin notices from stacking.
* Improvement: Harden log exports, use the current view filters, stop at a safe limit, and keep data if the server stops part-way through.
* Security: Redact internal server paths and tokens stored in logs.
* i18n: Remove HTML markup from translation strings.
* Dev: PHP 8.3+ compatibility, internal code cleanup, and unit tests for log redaction.

= 1.1 =
* New: Schedule tab, set how often WordPress checks for updates (hourly, twice daily, daily, or weekly), pick a preferred time of day, and hold automatic installs for a chosen number of days. Active holds show a notice on the Updates, Plugins, and Themes screens, and WordPress schedule messaging stays in sync with your settings.
* New: Export update logs, generate merged or flat reports from your current filters, copy formatted or plain-text output to the clipboard, and access export from the activity log toolbar.
* New: One-click switch in Settings to turn off all WordPress update notification emails (recovery mode emails stay on).
* New: Multisite support, network-activate Updatronix and manage settings, update history, schedule, and email controls as a Super Admin from the Network Admin dashboard. Everything is shared across every site on the network, with one unified log. Single-site installs are unchanged.
* Improvement: Cleaner copy, smoother flow, and accessibility improvements across every tab, including keyboard focus in activity log modals.
* i18n: Save buttons use "Save Changes" to match native WordPress settings screens.

= 1.0.6.1 =
* Fix: Readme.txt text is now naturally wrapped.

= 1.0.6 =
* Change: Align plugin lifecycle with WordPress uninstall expectations.
* Fix: Nested document landmarks for accessibility.
* Fix: Automatic update failures now record the real WP_Error (e.g. filesystem unavailable) in log details, not only generic upgrader messages.
* Change: Update component dependencies and remove hardcoded values in style to use design-token.css instead.
* Change: Update readme.txt content, tone, and voice.
* i18n: Align plugin interface with WordPress interface tone and voice, improving accessibility.

= 1.0.5 =
* Fix: Wire js script translations for bundled files.
* Add: Load theme/plugin descriptions translated into the current admin language for the auto-update panel.
* Add: Translation for "Icon", "Success", "Error", and "Warning" labels.
* Change: Cache the merged Jed/JSON translation inline payload.

= 1.0.4 =
* Fix: Wire js script translations.
* Fix: Wrong logging behavior for minor core auto-update. Was logged as "Reinstall" instead of "Update".
* Change: Code-split the admin JavaScript bundle with lazy-loaded tab modules to keep all emitted chunks below Webpack's recommended size limit and improve wp-admin load performance.
* Change: Align user interface standards to WP 7.0.
* Change: Tested up to WP 7.0-RC2.
* Change: Update screenshots for WordPress.org.
* Change: Update of the logo and banners.

= 1.0.3 =
* Add: Updatronix release on WordPress.org.

= 1.0.2 =
* Fix: Close `ob_start()` buffers safely.

= 1.0.1 =
* Fix: Better handling of updates logging.
* Change: Improve responsive, and plugin global UX.
* Change: Improve the readme.txt.

= 1.0 =
* Add: Initial release of Updatronix.

== Upgrade Notice ==

= 2.0.0 =
Back up your database first. The update log schema only adds columns and indexes, and existing rows stay in place. If you also run Updatronix 3000, 0.8.0 drops its legacy queue table; queue rows are not migrated. The update log remains the history.

= 1.1.3 =
On sites with automatic update delay enabled, a bug could interrupt the scheduled update process. This version fix it.

= 1.1.1 =
Fixes a schedule reset bug: if you use the Schedule tab to set a preferred time and recurrence, visiting some admin pages (or external tools touching the cron table) could reset the next check back to the WordPress default. A self-healing mechanism now catches and corrects it immediately.

= 1.1 =
Adds the Schedule tab, update log export, a switch to silence WordPress update emails, and multisite support, plus accessibility improvements across every tab.

= 1.0.6 =
Improves uninstall cleanup, accessibility, and error logging for failed auto-updates.

= 1.0.5 =
Fixes script translations and adds translation caching for the admin interface.

= 1.0.4 =
Fixes core auto-update logging and aligns the interface with WordPress 7.0.
