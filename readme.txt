=== Updatronix - Update Manager Enhanced ===
Contributors: quentinldd
Donate link: https://buymeacoffee.com/quentinld
Tags: updates, auto-update, maintenance, security, audit-log
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.2.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress updates made easy. Monitor every change, control all updates, and fine-tune your maintenance workflow.

== Description ==

WordPress applies your updates, but it keeps no record of them. Not what, not when, not how. Updatronix adds two layers to the native system: one for monitoring, one for control.

Updatronix is built on WordPress's own update engine, everything keeps working exactly as WordPress intends. It simply adds the monitoring and control you're missing: a clear record of every update, and every automatic update setting on one page.

= Built for every user in their diversity of needs =

* **Solo site owners:** Keep your site up to date with peace of mind. You'll always know what changed.
* **Freelancers:** When a client asks what you did, the answer is right there.
* **Developers:** Real control over automatic updates and scheduling, with the full detail behind every event.
* **Agencies:** The same update policy you trust, running on every client site, each with its own log.

== Features ==

= Update logs =

Every core, plugin, theme, and translation update is logged with its before and after versions, what triggered it, and how it ended. If something goes wrong, you have a precise starting point instead of a guess.

Filter the log however you like and export it in one click — handy for maintenance reports.

= Auto-updates =

Automatic update settings are scattered across several places in WordPress. Updatronix brings them together on one page: core, plugins, themes, and translations.

= Schedule =

Choose how often WordPress checks for updates and the time of day. Hold new releases for a few days, so unstable builds get fixed before they reach you.

= Settings =

Set how long the log is kept, route WordPress update emails to the right address, or turn them off entirely. Recovery mode emails stay on, so you won't lock yourself out.

== Updatronix 3000 ==

**Updatronix 3000** is the Pro edition of Updatronix. **Update Shield** checks every automatic update against your WordPress and PHP versions before it runs, so an incompatible automatic update won't break your site. More is on the roadmap: Update Flow, Development Tools, Advanced Exports, Queue List, and White Label. One license, one site, updates for life.

== Privacy ==

Nothing leaves your site. No analytics, no telemetry, no third-party calls. Updatronix reads from WordPress and writes to your site's storage, and that's the whole network footprint.

== Accessibility ==

Updatronix aims to be fully accessible to all of its users. If you run into a problem, open a support thread on the plugin page and it'll get fixed.

== Multisite Support ==

Updatronix supports multisite networks. Network-activate it once and manage settings, update history, schedule, and email controls from the Network Admin, with one unified update log.

== Screenshots ==

1. Update logs: The chronological view, with status, trigger, and version change for every entry.
2. Update logs: Filtering controls in action, with active filter tags and a reset option.
3. Update logs: The export modal — filter summary, merge option, column selection, and generated report.
4. Update logs: The export modal — plain-text report output with copy options.
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

Open **Tools → Updatronix**. The first tab is the log: date, item, version change, and outcome. Click any row to see the full detail. Logging is on by default.

= Can I export my update log? =

Yes. Filter the log however you like, then click **Export logs**. You get a clean report you can drop into an email to your team, a client, or whoever you've called in to help.

= Can I turn off WordPress update notification emails completely? =

Yes. In **Settings**, turn on **Disable all update notification emails**. Recovery mode emails are deliberately kept on: without them, you could lock yourself out of your own site.

= Can I delay automatic updates? =

Yes. In **Schedule**, enable **Delay updates** and choose how many days WordPress should wait after a release appears. Updates stay automatic; they're simply deferred, so unstable builds get fixed before they reach you.

= Does Updatronix work with my plugins, themes, and host? =

Yes. Updatronix hooks into the same update pipeline WordPress already runs, so everything that updates through the standard system gets logged, whether it comes from WordPress.org, a private source, or your host's mirror.

= Does Updatronix replace WordPress's native update system? =

No. Updatronix builds on WordPress's update engine without replacing it. Everything that goes through the standard update system keeps working exactly as before; Updatronix logs and manages, nothing more.

= Can Updatronix undo a failed update? =

No, and that's not its purpose. When an update fails, Updatronix captures the WordPress error and the version snapshot before WordPress moves on. That's the data you need to recover by hand, or to hand to your host's support. For extra peace of mind, schedule updates right after a backup.

= Where does my data go? =

Nowhere. Logs and settings stay on your site. The plugin makes no outbound network calls of its own: every API it touches is one WordPress was already going to call without it.

= How long are log entries kept? =

Up to you. In **Settings**, set the retention window between 1 and 365 days. The default is 90 days; a daily cleanup drops anything older.

= Does Updatronix work on multisite? =

Yes. Network-activate Updatronix and manage everything as a Super Admin from the Network Admin. Settings, schedule, email controls, and update history are shared across every site in one unified log.

== Changelog ==

= 1.2.0 =
* Security: Harden security.
* Dev: Harden WordPress Coding Standards.
* Improvement: Update interface components.

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
