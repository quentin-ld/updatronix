=== Updatronix – Update Manager Enhanced ===
Contributors: quentinldd
Donate link: https://buymeacoffee.com/quentinld
Tags: updates, auto-updates, update-log, maintenance, multisite
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 2.0.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Update WordPress without fear. Monitor every change, take precise control over all updates, schedule on your terms.

== Description ==

Updating WordPress should not feel like a gamble.

WordPress keeps your site updated, but it does not keep a clear record of what changed. Updatronix adds that record and puts the automatic update settings on one screen. You see the version before and after, what started the update, how it ended, and why it was allowed, held, or blocked.

It uses WordPress's own update engine. It does not replace the updater.

= What you get =

**A clear record.** Every update that runs through WordPress's updater is logged: core, plugins, themes, and translations. Open a row for the versions, trigger, result, and decision. Filter, search, and export when you need it.

**One place to control automatic updates.** Core, plugins, themes, and translations are on one screen. The Plugins, Themes, and Updates screens also show whether an item updates automatically.

**A schedule that fits your site.** Choose how often WordPress checks for updates and the time of day. Hold new releases for up to 365 days, so a release has time to settle. Updates stay automatic; they are deferred. A delay also delays fixes, so choose a window that matches your risk.

**Update emails that reach the right person.** Route update emails to the technical manager, or turn them off if the log is enough. Recovery mode emails stay on.

**A report you can share.** Export a plain-text report for a maintenance ticket, a client email, or your own records.

= What it doesn't do =

Updatronix is not a backup plugin. It gives you the record and the controls; keep a backup and a recovery procedure you trust.

= Who it is for =

* **Solo site owners:** Know what changed and when, without reading code.
* **Freelancers:** Show a client exactly what you did, with versions and results.
* **Developers and agencies:** Keep a clear record on each site.

= Updatronix 3000 =

Updatronix 3000 is the Pro edition. It adds automation when a site needs it:

* **Update Shield** checks WordPress and PHP versions before an automatic update and blocks a detected mismatch.
* **Version Lock** keeps automatic updates off for Git, SVN, Mercurial, and Bazaar checkouts and for Composer-managed items.
* **Update Flow** runs updates in the order you set.
* **One automatic pass** updates one item at a time with WordPress's own updater.
* **Structured exports, white label, and developer tools.** CSV, JSON, and XML exports; your name, logo, and colors on the screens and emails; hooks, functions, and a REST API.

Updatronix 3000 requires the free Updatronix plugin. A license covers one site or one multisite network.

= Privacy =

The free plugin sends no analytics, no telemetry, and no calls to our servers. Logs and settings stay in your site's database. It uses WordPress's own APIs to show plugin and theme details; those requests go to WordPress.org.

Updatronix 3000 checks its license with our server. That request carries the license key and the site domain. The license server is hosted in France. See the [privacy policy](https://holdmywp.com/en/privacy-policy/) for details.

= Accessibility =

We want Updatronix to work for everyone. If you find an accessibility problem, open a support thread and we will look at it.

== Screenshots ==

1. Update logs: A held update and a failed update, with the version change and the result.
2. Update logs: The filter menu, with the filters for the log fields.
3. Update logs: A failed entry expanded to show what happened.
4. Update logs: The export dialog, with the generated report.
5. Auto updates: Core update mode, translation toggle, and per-theme controls with status and version.
6. Auto updates: Per-plugin controls with status, version, and description.
7. Schedule: Recurrence, preferred time, next run, and the 3-day hold.
8. Health: The update checks, with the action for the one that needs attention.
9. Settings: Logging, retention, email routing, and the uninstall data choice.
10. Multisite: The network update log, with the blue mark on the rail.
11. Updatronix 3000: Update Shield, with Version Lock and the behavior on an incompatible update.
12. Updatronix 3000: Update Flow, with a dependency chain that updates analytics and SEO before core.
13. Updatronix 3000: White label, with your brand name, logo, and colors on the screens.

== Installation ==

1. Search for "Updatronix" in **Plugins → Add New**, or upload the plugin files to `/wp-content/plugins/updatronix/`.
2. Activate the plugin from the Plugins screen.
3. Open **Tools → Updatronix** (or **Dashboard → Update logs**) to see the history and adjust the settings.

Activation creates the log table and schedules a daily cleanup. Deactivation cancels the cleanup but keeps your data. Deletion removes the data only if you turn on **Delete all data on uninstall** in Settings. On multisite, network-activate the plugin from the Network Admin; its data lives at the network level.

== Frequently Asked Questions ==

= Where do I see the history of updates on my site? =

Open **Tools → Updatronix**. The first tab is the log. It shows the date, the item, the version change, and the result. Open a row to see the decision behind it.

= What happens if an update fails? =

Updatronix records the item, the version before the update, the version it tried to install, and the WordPress error. Open the entry to see it. Keep a backup and a recovery procedure you trust.

= Can I export my update log? =

Yes. Filter or search the log, then click **Export logs**. The free plugin writes a plain-text report. Updatronix 3000 adds CSV, JSON, and XML.

= Can I turn off WordPress update notification emails completely? =

Yes. In **Settings**, turn on **Disable all update notification emails**. Recovery mode emails stay on, so you can still recover a locked site.

= Can I delay automatic updates? =

Yes. In **Schedule**, enable **Delay updates** and choose 0 to 365 days. At 0, the hold is off. Updates stay automatic; they are deferred. A delay gives a release time to settle, but it also delays fixes, so choose a window that matches the risk of your site.

= Does Updatronix check whether an update is compatible with my site? =

The free plugin records what happened and shows what is waiting or held. Updatronix 3000 adds **Update Shield**, which checks an automatic update against the WordPress and PHP versions declared by the update and blocks a detected mismatch. It does not test plugin or theme interactions.

= Does Updatronix work with my plugins, themes, and host? =

Yes. Updatronix uses WordPress's own update engine, so it works with plugins, themes, and hosts that follow WordPress's update conventions and coding standards.

= Does Updatronix replace WordPress's native update system? =

No. It adds a log and controls around the update engine WordPress already uses. The update process itself is unchanged.

= Where does my data go? =

Logs and settings stay in your site's database. The free plugin sends no analytics, no telemetry, and no calls to our servers. Updatronix 3000 checks its license with our server using the key and site domain; the server is hosted in France.

= How long are log entries kept? =

From 1 to 365 days. The default is 90. A daily cleanup removes older entries.

= Does Updatronix work on multisite? =

Yes. Network-activate Updatronix and manage settings, schedule, email controls, and update history from the Network Admin. The log records the site ID for each update.

= Do I need Updatronix 3000? =

No. Updatronix 3000 is for sites that need automation: compatibility checks, version locks, update order, one automatic pass, structured exports, white label, and developer tools. The free plugin works on its own.

== Support ==

Questions? Open a thread on the [support forum](https://wordpress.org/support/plugin/updatronix/).

If Updatronix helps you, [leave a review](https://wordpress.org/plugins/updatronix/#reviews) and tell your friends. You can also sponsor development through [GitHub Sponsors](https://github.com/sponsors/quentin-ld) or [buy the author a coffee](https://buymeacoffee.com/quentinld).

== Changelog ==

= 2.0.1 =
* Documentation: Update documentation and screenshots.

= 2.0.0 =
* New: Each log entry records the decision behind an update and the pass or chain it belonged to; old rows show "Legacy - reason not recorded".
* New: Schedule keeps the check frequency, preferred time, and a hold of up to 365 days; the screen shows the WordPress default instead of an empty field.
* New: Export the log as a plain-text report, with Date optional and Element and Version always included.
* New: The admin uses the WordPress Design System with a dark rail, eleven destinations, one header per screen, one save button, and reduced-motion support; the rail shows the blue mark, or the 3000 mark while Pro is active.
* New: The Updatronix 3000 destination shows what the Pro edition includes and links to its plugin page.
* Improvement: The Plugins, Themes, and Updates screens show auto-update status and a short next-pass summary, and keep their icons and names when the schedule notice replaces the native text.
* Improvement: WP-Cron warnings move to Health, which explains when the alert can be ignored, and the auto-update estimate stays accurate with WP-Cron disabled.
* Fix: Theme details no longer fail on the changelog request, Cmd/Ctrl+S saves dirty screens without opening the browser dialog, Health centers its empty state, and Settings no longer repeats its save and email notes.
* Documentation: Refresh the short description, the banner, and the WordPress.org screenshots.
* Note: Updatronix 3000 0.8.0 runs one automatic pass and drops the legacy queue table; back up the database before upgrading, because queue rows are not migrated.
* Dev: `updatronix_log_decision_layer_enabled` and `updatronix_native_update_time_enabled` control the decision layer and native update time.
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
