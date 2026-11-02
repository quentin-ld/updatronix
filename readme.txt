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

See every update. Control every automatic update.

== Description ==

WordPress keeps your site updated, but it does not keep a clear record of what changed. Updatronix adds that record and brings the automatic update settings together on one screen.

It uses the update engine WordPress already runs. You see the version before and after, what started the update, and how it ended. You choose when WordPress checks for updates, how long to hold a new release, and where update emails go.

= What you get =

**A record you can use.** Every core, plugin, theme, and translation update is logged. Open a row to see the versions, the trigger, the result, and the decision behind it. Filter the log or search it. Export a report when you need one.

**One place for automatic updates.** Core, plugins, themes, and translations are on one screen. The Plugins, Themes, and Updates screens also show whether an item updates automatically.

**A schedule that fits your site.** Choose the check frequency and the time of day. Hold new releases for up to 365 days, so a broken release has time to be fixed before it reaches you.

**Health checks.** See what needs attention before the next update. Retry a failed check from the Health screen.

**Settings.** Set how long logs are kept. Route update emails to the right address, or turn them off. Recovery mode emails stay on. You can also choose whether deleting the plugin removes your data.

= Who it is for =

* **Solo site owners:** Know what changed and when.
* **Freelancers:** Show a client exactly what you did.
* **Developers:** Control automatic updates and scheduling, with the details behind each event.
* **Agencies:** Apply one update policy across client sites, with one shared log.

== Updatronix 3000 ==

Updatronix 3000 is the Pro edition. It runs the automatic update routine for you, with a compatibility check before each update and a fixed order between items.

* **Update Shield** compares each automatic update with your WordPress and PHP versions and blocks a mismatch.
* **Version Lock** keeps automatic updates off for Git, SVN, Mercurial, and Bazaar checkouts and for Composer-managed items.
* **Update Flow** runs updates in the order you set, so an item waits for what it depends on.
* **One automatic pass** uses WordPress's own updater for each item, with its lock, maintenance mode, and rollback.
* **Exports.** Download the log as CSV or JSON.
* **White label.** Put your name, logo, and colors on the Updatronix screens and emails.
* **Developer tools.** Connect Updatronix to your own workflow with hooks, functions, and the REST API.

Updatronix 3000 requires the free Updatronix plugin.

== Privacy ==

The free plugin sends no analytics or telemetry. It makes no third-party calls of its own; logs and settings stay in your site's database.

Updatronix 3000 checks its license with our server. That request carries the license key and the site domain.

== Accessibility ==

We want Updatronix to work for everyone. If you find an accessibility problem, open a support thread and we will look at it.

== Multisite Support ==

Updatronix works on multisite. Network-activate it and manage the settings, schedule, email controls, and update log from the Network Admin.

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

Activation creates the log table and schedules a daily cleanup. Deactivation cancels the cleanup but keeps your data. Deletion removes the data only if you turn on **Delete all data on uninstall** in Settings. On multisite, network-activate the plugin from the Network Admin; its data lives at the network level.

== Support ==

Questions? Open a thread on the [support forum](https://wordpress.org/support/plugin/updatronix/).

If Updatronix helps you, [leave a review](https://wordpress.org/plugins/updatronix/#reviews) and tell your friends. You can also sponsor development through [GitHub Sponsors](https://github.com/sponsors/quentin-ld/dashboard).

== Frequently Asked Questions ==

= Where do I see the history of updates on my site? =

Open **Tools → Updatronix**. The first tab is the log. It shows the date, the item, the version change, and the result. Open a row to see the decision behind it. Logging is on by default.

= Can I export my update log? =

Yes. Filter or search the log, then click **Export logs**. The free plugin writes a plain-text report. Updatronix 3000 adds CSV and JSON.

= Can I turn off WordPress update notification emails completely? =

Yes. In **Settings**, turn on **Disable all update notification emails**. Recovery mode emails stay on, so you can still recover a locked site.

= Can I delay automatic updates? =

Yes. In **Schedule**, enable **Delay updates** and choose 0 to 365 days. At 0, the hold is off. Updates stay automatic; they are deferred.

= Does Updatronix check whether an update is compatible with my site? =

The free plugin records what happened and shows what is waiting or held. Updatronix 3000 adds **Update Shield**, which checks an automatic update against your WordPress and PHP versions before it runs and blocks a mismatch.

= Will an update break my site? =

No plugin can promise that. Updatronix gives you the record and the controls. You can hold a release or check what changed. Export the details when you need them. Updatronix 3000 blocks an incompatible automatic update before it runs.

= Does Updatronix work with my plugins, themes, and host? =

Yes. It logs updates that go through WordPress's normal update system, including updates from a private source or a host mirror.

= Does Updatronix replace WordPress's native update system? =

No. It adds a log and controls around the update engine WordPress already uses. The update process itself is unchanged.

= Can Updatronix undo a failed update? =

No. It records the WordPress error and the version before the update, so you have the details to recover or ask your host for help. Schedule updates after a backup if you want an extra safety net.

= Where does my data go? =

Logs and settings stay in your site's database. The free plugin adds no outbound calls. It uses WordPress's own update checks, which already contact WordPress.org or your update source.

= How long are log entries kept? =

From 1 to 365 days. The default is 90. A daily cleanup removes older entries.

= Does Updatronix work on multisite? =

Yes. Network-activate Updatronix and manage it as a Super Admin from the Network Admin. Settings, schedule, email controls, and update history are shared across the network.

= Do I need Updatronix 3000? =

No. The free plugin logs updates and controls the automatic update settings. Updatronix 3000 adds the automation layer.

== Changelog ==

= 2.0.0 =
* New: Each log entry records why an update was allowed, held, or blocked, and which pass or chain it belonged to. Old rows show "Legacy - reason not recorded".
* New: Schedule keeps the check frequency and the preferred time, and holds new releases for up to 365 days. The screen shows the WordPress default instead of an empty field.
* New: Export the log as a plain-text report. The Date column is optional; Element and Version are always included.
* New: The admin uses the WordPress Design System: a dark rail, eleven destinations, one header per screen, and one save button. Health, Documentation, Support, Update Shield, Update Flow, and White label are part of the shell. White label colors apply to it.
* Improvement: The Plugins, Themes, and Updates screens show whether an item updates automatically. Update-core shows a one-paragraph next-pass summary; the next update check is no longer shown as the install time.
* Fix: Theme details load the theme API before fetching a changelog, so the REST request no longer fails.
* Fix: Cmd/Ctrl+S no longer opens the browser save dialog on Updatronix screens. Dirty screens still save.
* Fix: Health centers its empty state. Settings no longer shows the duplicate save and email notes or the recovery-mode notice.
* Note: Updatronix 3000 0.8.0 runs one automatic pass and drops the legacy queue table. Back up the database before upgrading; queue rows are not migrated.
* Copy: The interface follows the WordPress Documentation Style Guide across all eleven destinations.
* Dev: The plugin shares the pinned WordPress Design System packages with Updatronix 3000. `updatronix_log_decision_layer_enabled` and `updatronix_native_update_time_enabled` are the switches.
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
