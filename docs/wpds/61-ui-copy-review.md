# UI copy review — WordPress Documentation Style Guide

**Date:** 2026-11-01
**Scope:** Every reader-visible string in Free and Pro: 1,541 i18n call
sites, 1,085 unique strings, across 63 JS files and both PHP trees.
**Status:** Contract for the rewrite. The changes below are applied in the
same pass; nothing outside this document is reworded.

## How this was done

The four skills ran in the order their own triggers require:

1. `grilling` settled scope, destination, French, and renames in
   `.agents/notes/2026-11-01-grill-ui-copy.md`.
2. `writing` supplied the guide's rules and its word list. Every row names
   the section that decides it.
3. `humanizer` removed the tells the guide does not name: staging openers,
   forced triads, dashes used as connectors, and redundant closers.
4. `qa` audits the result without fixing it, in
   `.agents/notes/2026-11-01-qa-ui-copy.md`.

A finding is one defect class, not one string. The rows below are the
complete before-and-after list: 292 string replacements plus the
structural edits and plural forms listed after the tables.

## Guide sections used

- **General guidelines** — no excessive claims, no future features.
- **Language and grammar** — articles, sentence case, second person, present
  tense, active voice, common technical terms, US spelling.
- **Punctuation** — straight quotes, no ellipses, semicolons, em dash only for
  a break in the flow.
- **Formatting** — sentence case, consistent terminology, `Maximum` labels.
- **Linking** — link text that gives context.
- **Word list** — `canceled`, `e.g.` not used, `auto-` not hyphenated.

## Free

### `assets/src/js/shell/routes.js`

| Before | After | Guide section |
|---|---|---|
| Manage your Updatronix 3000 license and Pro tools. | Manage your Updatronix 3000 license and tools. | language and grammar |
| Official guides for Updatronix, Updatronix 3000, and the WordPress update system. | Guides for Updatronix, Updatronix 3000, and the WordPress update system. | general guidelines |

### `assets/src/js/shell/SaveBar.js`

| Before | After | Guide section |
|---|---|---|
| Saving… | Saving | punctuation |

### `assets/src/js/components/SettingsPanel.js`

| Before | After | Guide section |
|---|---|---|
| One save for the whole surface. Toggles are staged until you choose Save changes, and nothing is persisted on this page. | One save for the whole screen. Changes stay on this page until you choose Save changes. | language and grammar |
| Email delivery is handled by WordPress; Updatronix only changes who receives update emails. | WordPress sends the emails; Updatronix only changes who receives them. | language and grammar |

### `assets/src/js/components/activityLog/ActivityLogPanel.js`

| Before | After | Guide section |
|---|---|---|
| Search and filter the history, then open a record for the full process details. | Search and filter the history, then open a record for the full details. | language and grammar |
| Entries older than %s days are removed by the daily cleanup task. | The daily cleanup removes entries older than %s days. | language and grammar |
| Loading update logs… | Loading update logs | punctuation |
| Retention %s days | Retention: %s days | punctuation |

### `assets/src/js/components/activityLog/ExportLogsModal.js`

| Before | After | Guide section |
|---|---|---|
| No filters applied — all logs in the current view are included. | No filters applied. All logs in the current view are included. | punctuation |
| Generating the export… | Generating the export | punctuation |

### `assets/src/js/components/activityLog/LogDetailsContent.js`

| Before | After | Guide section |
|---|---|---|
| Loading log details… | Loading log details | punctuation |
| Server paths have been redacted for security. | Updatronix removed server paths from this entry for security. | language and grammar |

### `assets/src/js/components/activityLog/logFilters.js`

| Before | After | Guide section |
|---|---|---|
| Cancelled | Canceled | word list |
| oldest first | Oldest first | formatting |
| newest first | Newest first | formatting |

### `assets/src/js/components/activityLog/utils.js`

| Before | After | Guide section |
|---|---|---|
| Cancelled | Canceled | word list |
| Predecessor | Earlier step | vocabulary |

### `assets/src/js/components/activityLog/constants.js`

| Before | After | Guide section |
|---|---|---|
| Auto-updates disabled by Safe Mode | Automatic updates disabled by Safe mode | vocabulary |
| Managed by Composer | Installed with Composer | language and grammar |
| Version control state could not be read | The version-control state could not be read | language and grammar |
| The update lease expired | The update lock expired | language and grammar |
| Version control state was not verified | The version-control state was not verified | language and grammar |
| The update is no longer offered | This update is no longer available | language and grammar |
| The update offer changed | A different update is available | language and grammar |
| The update offer is unavailable | The update is unavailable | language and grammar |
| No update offered | No update available | language and grammar |
| Waiting for a predecessor | Waiting for an earlier step | language and grammar |
| A predecessor failed | An earlier step failed | language and grammar |
| A predecessor has no update offer | An earlier step has no update available | language and grammar |
| A predecessor is blocked | An earlier step is blocked | language and grammar |
| A predecessor is managed by version control | An earlier step is managed by version control | language and grammar |
| A predecessor needs review | An earlier step needs review | language and grammar |
| A predecessor is not managed automatically | An earlier step is not managed automatically | language and grammar |
| A predecessor is not installed | An earlier step is not installed | language and grammar |
| A predecessor cannot update automatically | An earlier step cannot update automatically | language and grammar |
| Legacy — reason not recorded | Legacy: reason not recorded | punctuation |
| Automatic pass summary | Automatic update summary | vocabulary |
| Flow | Update Flow | vocabulary |
| Queue | Update queue | vocabulary |

### `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js`

| Before | After | Guide section |
|---|---|---|
| Loading auto-update settings… | Loading auto updates | punctuation |
| Auto-updates for %s | Auto updates for %s | vocabulary |
| Auto-updates could not be loaded | Auto updates could not be loaded | vocabulary |
| Auto-updates could not be refreshed | Auto updates could not be refreshed | vocabulary |
| Translation auto-updates | Automatic translation updates | vocabulary |
| Turning auto-updates on does not install anything right away. The next scheduled check uses these settings after you save. | Turning automatic updates on does not install anything right away. The next scheduled check uses these settings after you save. | vocabulary |
| Auto-updates | Auto updates | vocabulary |

### `assets/src/js/components/autoUpdates/ConstantNotices.js`

| Before | After | Guide section |
|---|---|---|
| DISABLE_WP_CRON is set in your wp-config.php file. WordPress does not run scheduled tasks during normal visits. If you use a system cron job, make sure it calls wp-cron.php regularly so automatic updates and other scheduled events run properly. | DISABLE_WP_CRON is set in your wp-config.php file. WordPress does not run scheduled tasks during normal visits. A system cron job that calls wp-cron.php regularly keeps automatic updates and other scheduled events working. | language and grammar |

### `assets/src/js/components/autoUpdates/CoreSection.js`

| Before | After | Guide section |
|---|---|---|
| Minor releases only (e.g. 6.4.1 to 6.4.2) | Minor releases only (for example, 6.4.1 to 6.4.2) | word list |
| All releases, including major (e.g. 6.4 to 6.5) | All releases, including major (for example, 6.4 to 6.5) | word list |
| Disabled (no core auto-updates) | Disabled (no automatic core updates) | vocabulary |
| Core auto-update mode | Core update mode | vocabulary |

### `assets/src/js/components/autoUpdates/ItemsDataViewSection.js`

| Before | After | Guide section |
|---|---|---|
| Auto-update | Auto updates | vocabulary |
| Locked by Safe Mode due to incompatibility | Locked by Safe mode due to incompatibility | vocabulary |
| Disable auto-update for %s | Disable automatic updates for %s | vocabulary |
| Enable auto-update for %s | Enable automatic updates for %s | vocabulary |

### `assets/src/js/components/autoUpdates/TranslationsSection.js`

| Before | After | Guide section |
|---|---|---|
| Translation auto-updates | Automatic translation updates | vocabulary |

### `assets/src/js/components/schedule/scheduleDraft.js`

| Before | After | Guide section |
|---|---|---|
| Run a check now refreshes the update data. It does not start the hold; the hold starts at the next automatic pass. | Run a check now refreshes the update data. It does not start the hold; the hold starts at the next automatic run. | vocabulary |
| New releases are held for %d day after the first automatic check sees them; the earliest install is the first check at or after that. | New releases are held for %d day after the first automatic check finds them. The earliest install is the first check at or after that. | punctuation |
| New releases are held for %d days after the first automatic check sees them; the earliest install is the first check at or after that. | New releases are held for %d days after the first automatic check finds them. The earliest install is the first check at or after that. | punctuation |
| The hold applies after the first automatic check sees a release. It applies to every core automatic update; WordPress does not expose a reliable security-release flag. | The hold applies after the first automatic check finds a release. It applies to every core automatic update; WordPress does not report which releases are security releases. | language and grammar |

### `assets/src/js/components/schedule/SchedulePanel.js`

| Before | After | Guide section |
|---|---|---|
| The check runs on the next cron tick after this time. Twice daily runs again 12 hours later. | The check runs at the next WP-Cron event after this time. Twice daily runs again 12 hours later. | language and grammar |
| Hold new releases for (days) | Hold period (days) | formatting |
| Updates stay automatic; they are deferred so unstable builds can be fixed first. | Updates stay automatic; Updatronix defers them so unstable releases can be fixed first. | language and grammar |
| WordPress will not run the scheduled check until WP-Cron is enabled or a real cron job calls it. | WordPress does not run the scheduled check until WP-Cron is enabled or a system cron job calls it. | language and grammar |
| If another plugin delays WP-Cron, the check runs on the next available tick rather than exactly at the selected time. | If another plugin delays WP-Cron, the check runs at the next available event instead of exactly at the selected time. | language and grammar |

### `assets/src/js/hooks/useAutoUpdates.js`

| Before | After | Guide section |
|---|---|---|
| Your auto-update settings could not be loaded. Try refreshing the page. | Your automatic update settings could not be loaded. Refresh the page. | vocabulary |
| Core auto-update setting saved. | Core update setting saved. | vocabulary |
| The core auto-update setting could not be saved. Check your connection and try again. | The core update setting could not be saved. Check your connection, and then try again. | vocabulary |
| Auto-update enabled for this plugin. | Automatic updates enabled for this plugin. | vocabulary |
| Auto-update disabled for this plugin. | Automatic updates disabled for this plugin. | vocabulary |
| The plugin auto-update setting could not be changed. Try again. | The plugin update setting could not be changed. Try again. | vocabulary |
| Auto-update enabled for this theme. | Automatic updates enabled for this theme. | vocabulary |
| Auto-update disabled for this theme. | Automatic updates disabled for this theme. | vocabulary |
| The theme auto-update setting could not be changed. Try again. | The theme update setting could not be changed. Try again. | vocabulary |
| Translation auto-update setting saved. | Translation update setting saved. | vocabulary |
| The translation auto-update setting could not be changed. Try again. | The translation update setting could not be changed. Try again. | vocabulary |
| Auto-update settings saved. | Automatic update settings saved. | vocabulary |
| The auto-update settings could not be saved. Try again. | The automatic update settings could not be saved. Try again. | vocabulary |
| Some auto-update changes may have been applied. The page now shows the server state. Review the changes and try again. | Some automatic update changes may have been applied. The page now shows the server state. Review the changes, and then try again. | vocabulary |

### `assets/src/js/hooks/useLogs.js`

| Before | After | Guide section |
|---|---|---|
| Your update logs could not be loaded. Try refreshing the page. | Your update logs could not be loaded. Refresh the page. | language and grammar |

### `assets/src/js/hooks/usePluginSettings.js`

| Before | After | Guide section |
|---|---|---|
| Your settings could not be saved. Check your connection and try again. | Your settings could not be saved. Check your connection, and then try again. | punctuation |

### `assets/src/js/pages/DocumentationPage.js`

| Before | After | Guide section |
|---|---|---|
| Pro guide | Updatronix 3000 guide | vocabulary |
| License activation, Update Shield, safe mode, and the Pro maintenance workflow. | License activation, Update Shield, Safe mode, and the Updatronix 3000 maintenance workflow. | vocabulary |

### `assets/src/js/pages/HealthPage.js`

| Before | After | Guide section |
|---|---|---|
| Running health checks… | Running health checks | punctuation |
| Scheduling… | Scheduling | punctuation |
| %d open issue | %d issue to review | formatting |
| %d open issues | %d issues to review | formatting |
| No open issues | No issues to review | formatting |
| Learn more | Read the guide | linking |

### `assets/src/js/pages/ProDestination.js`

| Before | After | Guide section |
|---|---|---|
| This Pro panel is not available yet. | This panel is not available in this version of Updatronix 3000. | general guidelines |
| The panel is part of the next Updatronix 3000 update. Your current Pro tools remain available on the Updatronix 3000 page. | Your Updatronix 3000 tools are on that page. | general guidelines |
| Updatronix 3000 is the Pro edition. | Updatronix 3000 adds more update tools. | general guidelines |
| It adds Update Shield, Update Flow, white label, and advanced exports (CSV and JSON). XML export and development tools are on the roadmap. No Pro feature is active on this site. | Updatronix 3000 adds Update Shield, Update Flow, White label, and advanced exports (CSV and JSON). No Updatronix 3000 feature is active on this site. | general guidelines |

### `assets/src/js/pages/SupportPage.js`

| Before | After | Guide section |
|---|---|---|
| Free support for Updatronix is handled by the WordPress.org community. | The WordPress.org community provides free support for Updatronix. | language and grammar |

### `inc/admin/menu.php`

| Before | After | Guide section |
|---|---|---|
| Loading your Updatronix settings… | Loading your Updatronix settings | punctuation |

### `inc/classes/class-updatronix-health.php`

| Before | After | Guide section |
|---|---|---|
| Supported PHP version. The update stack is tested through PHP 8.4. | Supported PHP version. Updatronix tests its update tools through PHP 8.4. | language and grammar |
| Max execution time | Maximum execution time | formatting |
| Upload max filesize | Maximum upload file size | formatting |
| Retry finished successfully. | Retry finished. | language and grammar |

### `inc/classes/class-updatronix-log-taxonomy.php`

| Before | After | Guide section |
|---|---|---|
| Managed by Composer | Installed with Composer | language and grammar |
| Version control state could not be read | The version-control state could not be read | language and grammar |
| The update lease expired | The update lock expired | language and grammar |
| Version control state was not verified | The version-control state was not verified | language and grammar |
| The update is no longer offered | This update is no longer available | language and grammar |
| The update offer changed | A different update is available | language and grammar |
| The update offer is unavailable | The update is unavailable | language and grammar |
| No update offered | No update available | language and grammar |
| Waiting for a predecessor | Waiting for an earlier step | language and grammar |
| A predecessor failed | An earlier step failed | language and grammar |
| A predecessor has no update offer | An earlier step has no update available | language and grammar |
| A predecessor is blocked | An earlier step is blocked | language and grammar |
| A predecessor is managed by version control | An earlier step is managed by version control | language and grammar |
| A predecessor needs review | An earlier step needs review | language and grammar |
| A predecessor is not managed automatically | An earlier step is not managed automatically | language and grammar |
| A predecessor is not installed | An earlier step is not installed | language and grammar |
| A predecessor cannot update automatically | An earlier step cannot update automatically | language and grammar |
| Legacy — reason not recorded | Legacy: reason not recorded | punctuation |
| Automatic pass summary | Automatic update summary | vocabulary |
| Flow | Update Flow | vocabulary |
| Queue | Update queue | vocabulary |

### `inc/classes/class-updatronix-schedule-state.php`

| Before | After | Guide section |
|---|---|---|
| The last automatic attempt failed. The next pass re-evaluates. | The last automatic attempt failed. The next automatic run re-evaluates. | vocabulary |
| Next in the current pass at %s. It may run after the current item. | Next in the current run at %s. It may run after the current item. | vocabulary |

### `inc/classes/class-updatronix-settings.php`

| Before | After | Guide section |
|---|---|---|
| Queue position %1$d of %2$d | Update queue position %1$d of %2$d | vocabulary |
| Auto-updates disabled by Safe Mode | Automatic updates disabled by Safe mode | vocabulary |
| That constant is not recognised by Updatronix. | Updatronix does not recognize that constant. | word list |

### `inc/classes/class-updatronix-update-logger.php`

| Before | After | Guide section |
|---|---|---|
| Downloading the update from %s… | Downloading the update from %s | punctuation |
| Unpacking the update… | Unpacking the update | punctuation |

### `inc/classes/class-updatronix-export-body-builder.php`

| Before | After | Guide section |
|---|---|---|
| Cancelled | Canceled | word list |
| Auto-updates disabled by Safe Mode | Automatic updates disabled by Safe mode | vocabulary |

### `inc/core/tabs.php`

| Before | After | Guide section |
|---|---|---|
| Auto-updates | Auto updates | vocabulary |

## Pro

### `assets/src/js/components/LicensePanel.js`

| Before | After | Guide section |
|---|---|---|
| Hooks, functions, and a REST API for your own pipeline. | Hooks, functions, and a REST API for your own update workflow. | language and grammar |
| Protection, order, branding, and tools for larger sites. | Protection, update order, branding, and developer tools. | general guidelines |
| Loading license status… | Loading license status | punctuation |
| Removing… | Removing | punctuation |
| Deactivating… | Deactivating | punctuation |
| Activating… | Activating | punctuation |
| WordPress salts may have changed. Clear the stored license and activate the key again. | Your WordPress security keys may have changed. Remove the stored license, and then activate the key again. | language and grammar |
| Clear stored license? | Remove stored license? | formatting |
| Clear license | Remove license | formatting |
| Your license unlocks the Pro tools available in this release. | Your license enables Updatronix 3000 tools on this site. | vocabulary |
| Pro features are enabled for development. No license is stored. | Updatronix 3000 features are enabled for development. No license is stored. | vocabulary |
| Enter the license key from your Updatronix account to enable Update Shield and the other Pro tools. | Enter the license key from your Updatronix account to enable Update Shield and the other Updatronix 3000 tools. | vocabulary |
| This removes the license for %s and disables the Pro tools on this site. | This removes the license for %s and disables the Updatronix 3000 tools on this site. | vocabulary |
| The license key is stored encrypted on this site and validated by the Updatronix license service. | Updatronix stores the license key encrypted on this site and validates it with the license service. | language and grammar |

### `assets/src/js/components/UpdateFlowPage.js`

| Before | After | Guide section |
|---|---|---|
| Checking your license… | Checking your license | punctuation |
| Loading chains… | Loading chains | punctuation |
| Last pass | Last run | vocabulary |
| Read-only. The pass runs on the regular update check; reload this page to see the latest result. | Read-only. Runs happen on the regular update check; reload this page to see the latest result. | vocabulary |
| Pass %s | Run %s | vocabulary |
| Enabled chains order automatic updates. A step waits until its predecessor is updated. A held step waits for its hold and for every predecessor. | Enabled chains order automatic updates. A step waits until the step before it is updated. A held step waits for its hold and for every earlier step. | language and grammar |
| Create a chain to control the order updates install in. | Create a chain to control the order that updates install in. | language and grammar |
| e.g. Payments first | For example, payments first | language and grammar |
| e.g. Gateways update before the shop, so checkout is ready. | For example, gateways update before the shop, so checkout is ready. | language and grammar |
| One save for the whole surface. Chain edits are staged until you choose Save changes, and circular requirements are blocked. | One save for the whole screen. Chain edits stay on this page until you choose Save changes, and the editor blocks circular requirements. | language and grammar |

### `assets/src/js/components/UpdateShieldPage.js`

| Before | After | Guide section |
|---|---|---|
| Compatibility recheck failed. Please try again. | Compatibility recheck failed. Try again. | language and grammar |
| Checking your license… | Checking your license | punctuation |
| Loading shield settings… | Loading Update Shield settings | punctuation |
| Rechecking… | Rechecking | punctuation |
| Only items Shield turned off, and only when a newer release passes every policy. The same release stays manual. | Only items that Update Shield turned off, and only when a newer release passes every policy. The same release stays manual. | vocabulary |
| Only available when incompatible updates turn off auto-updates. | Only available when incompatible updates turn off automatic updates. | vocabulary |
| Protects WordPress’s native automatic updates. Every Update Flow step evaluates the same Shield policies. | Protects WordPress' native automatic updates. Every Update Flow step evaluates the same Update Shield policies. | punctuation |
| Checks WordPress and PHP compatibility before WordPress’s native automatic update installs. | Checks WordPress and PHP compatibility before WordPress' native automatic update installs. | punctuation |
| WordPress blocks automatic updates in the checkout contexts it detects. Version Lock also covers each item root and Composer provenance, and keeps the native auto-update toggle locked. | WordPress blocks automatic updates in the version-control checkouts it detects. Version Lock also covers each item's root folder and items installed with Composer, and it keeps the native automatic updates switch locked. | language and grammar |
| Checked %1$d of %2$d installed items. Native VCS: %3$d detected. Composer: %4$d detected. | Checked %1$d of %2$d installed items: %3$d in a version-control checkout, %4$d installed with Composer. | language and grammar |
| Items in a native version-control checkout | Items in a version-control checkout | vocabulary |
| Composer-managed items | Items installed with Composer | vocabulary |
| WordPress also refuses its own automatic updates in a checkout it detects. Version Lock does not change manual WordPress behavior. | WordPress also refuses its own automatic updates in a version-control checkout it detects. Version Lock does not change how manual WordPress updates work. | language and grammar |
| Blocks automatic updates for item-root Git, SVN, Mercurial, and Bazaar checkouts and for items installed by the project Composer. The native toggle is locked; the stored option is left unchanged. | Blocks automatic updates for items in a Git, SVN, Mercurial, or Bazaar checkout, and for items installed with Composer. The native switch stays locked, and the stored option does not change. | language and grammar |
| Blocks native admin single, bulk, update-core, and direct update URLs for locked items. WP-CLI stays available as the escape hatch. | For locked items, blocks single, bulk, and update-core updates in the admin, plus direct update URLs. WP-CLI still works for these items. | language and grammar |
| Applies to Shield-managed updates, including Flow steps. Core has no auto-update switch, so both options skip only that core release. | Applies to updates managed by Update Shield, including Update Flow steps. WordPress core has no automatic updates switch, so both options skip only that core release. | vocabulary |
| Re-enable auto-updates automatically | Automatically re-enable automatic updates | vocabulary |
| Sends one email when Shield blocks an update. | Sends one email when Update Shield blocks an update. | vocabulary |
| Flag abandoned plugins | Flag plugins with no recent release | language and grammar |
| Advisory only: warns when an item has had no release for the configured threshold. It never blocks an update. | Flags an item that has had no release for the configured period. It never blocks an update. | language and grammar |
| Auto re-enable is off. | Automatic re-enable is off. | vocabulary |
| Auto-update re-enable state | Automatic re-enable state | vocabulary |
| Current offer | Current update | language and grammar |
| Advisory threshold: %s without a release. Data comes from WordPress.org. | Warning threshold: %s without a release. Data comes from WordPress.org. | language and grammar |
| Last advisory check: %s | Last warning check: %s | language and grammar |
| Abandoned advisories | No recent release warnings | language and grammar |
| Abandoned advisories are off; no advisory rows are shown. | No recent release warnings are shown because the check is off. | vocabulary |
| Auto re-enable is on. %d item(s) are eligible now. | Automatic re-enable is on. %d items are eligible now. | vocabulary |

### `assets/src/js/components/UpdateShieldPanel.js`

| Before | After | Guide section |
|---|---|---|
| Rechecking compatibility… | Rechecking compatibility | punctuation |

### `assets/src/js/components/WhiteLabelPage.js`

| Before | After | Guide section |
|---|---|---|
| Checking your license… | Checking your license | punctuation |
| Loading branding… | Loading branding | punctuation |
| Uploading… | Uploading | punctuation |
| Safe mode on %1$s (%2$s) found incompatible items. Auto-updates have been disabled for the affected items. | Safe mode on %1$s (%2$s) found incompatible items. Automatic updates have been disabled for the affected items. | vocabulary |
| Replaces “Updatronix” in the rail, breadcrumbs, and Safe mode emails. | Replaces "Updatronix" in the rail, breadcrumbs, and Safe mode emails. | punctuation |

### `assets/src/js/flowRunState.js`

| Before | After | Guide section |
|---|---|---|
| Waiting for its predecessor. | Waiting for the step before it. | language and grammar |
| Its predecessor is blocked. | The step before it is blocked. | language and grammar |
| Its predecessor is managed by another tool. | The step before it is managed by another tool. | language and grammar |
| Its predecessor failed; the next pass retries once. | The step before it failed; the next run retries once. | language and grammar |
| Its predecessor needs review. | The step before it needs review. | language and grammar |
| Its predecessor has no fresh update data. | The step before it has no fresh update data. | language and grammar |
| Its predecessor is not set to update automatically. | The step before it is not set to update automatically. | language and grammar |
| Its predecessor is no longer installed. | The step before it is no longer installed. | language and grammar |
| Its predecessor cannot update automatically. | The step before it cannot update automatically. | language and grammar |
| The target version changed; the next pass re-plans it. | The target version changed; the next run plans it again. | vocabulary |
| The offer is no longer available. | This update is no longer available. | language and grammar |

### `assets/src/js/hooks/useLicense.js`

| Before | After | Guide section |
|---|---|---|
| Failed to fetch license status. | License status could not be loaded. | language and grammar |
| License activated successfully. | License activated. | language and grammar |

### `assets/src/js/hooks/useProSettings.js`

| Before | After | Guide section |
|---|---|---|
| Failed to load Pro settings | Updatronix 3000 settings could not be loaded. | vocabulary |
| Your Pro settings could not be saved | Your Updatronix 3000 settings could not be saved. | vocabulary |

### `assets/src/js/shieldState.js`

| Before | After | Guide section |
|---|---|---|
| %1$s — %2$d days since the last release | %1$s: %2$d days since the last release | punctuation |
| Blocked by a policy | Blocked by an Update Shield rule | vocabulary |
| Blocked target unknown | Blocked version unknown | language and grammar |
| Block it and turn off auto-updates for that item | Block it and turn off automatic updates for that item | vocabulary |
| The update does not install for this release; auto-updates stay on and the next release is checked again. | The update does not install for this release; automatic updates stay on, and Update Shield checks the next release again. | vocabulary |

### `assets/src/index.js`

| Before | After | Guide section |
|---|---|---|
| Updatronix 3000 could not start because the shared Updatronix design-system runtime is missing. Update Updatronix (Free) to 2.0 or newer. | Updatronix 3000 could not start because the shared Updatronix runtime is missing. Update Updatronix to 2.0 or newer. | language and grammar |

### `updatronix-pro.php`

| Before | After | Guide section |
|---|---|---|
| Updatronix 3000 requires the Updatronix plugin. <a href="%s">Install Updatronix</a> to enable Pro features. | Updatronix 3000 requires the Updatronix plugin. <a href="%s">Install Updatronix</a> to enable Updatronix 3000 features. | vocabulary |
| Updatronix 3000 requires Updatronix %1$s or later. You are running %2$s. <a href="%3$s">Update Updatronix</a> to enable Pro features. | Updatronix 3000 requires Updatronix %1$s or later. You are running %2$s. <a href="%3$s">Update Updatronix</a> to enable Updatronix 3000 features. | vocabulary |

### `inc/license/class-updatronix-pro-license-api.php`

| Before | After | Guide section |
|---|---|---|
| Invalid API response format. | The license server returned an unexpected response. | general guidelines |
| Unexpected API response structure. | The license server returned an unexpected response. | general guidelines |
| API unreachable. | The license server could not be reached. | general guidelines |
| API returned HTTP %d. | The license server returned HTTP %d. | general guidelines |

### `inc/license/class-updatronix-pro-license-handler.php`

| Before | After | Guide section |
|---|---|---|
| Updatronix 3000 license decryption failed. Please re-activate your license key to restore Pro features. | Updatronix 3000 could not decrypt the stored license. Activate your license key again to restore Updatronix 3000 features. | language and grammar |
| Updatronix 3000 license validation failed. Please re-activate your license key to restore Pro features. | Updatronix 3000 could not validate the license. Activate your license key again to restore Updatronix 3000 features. | language and grammar |

### `inc/license/class-updatronix-pro-license-rest.php`

| Before | After | Guide section |
|---|---|---|
| Please wait %d seconds before attempting another activation. | Wait %d seconds before you try to activate again. | language and grammar |
| Activation rejected by server. | The license server rejected the activation. | language and grammar |
| License activated successfully. | License activated. | language and grammar |
| License deactivated successfully. | License deactivated. | language and grammar |
| Deactivation rejected by server. | The license server rejected the deactivation. | language and grammar |

### `inc/license/class-updatronix-pro-license-updater.php`

| Before | After | Guide section |
|---|---|---|
| A newer version is available, but the license server returned no package URL. Configure the product download link on the license server, or supply the release zip URL via the updatronix_pro_update_package filter. | A newer version of Updatronix 3000 is available, but the license server returned no download URL. Set the download link for the product on the license server, or pass the release ZIP URL through the updatronix_pro_update_package filter. | language and grammar |
| An update for Updatronix 3000 is available, but the license server returned no download URL. Configure the product as downloadable on the license server, or provide the release zip URL via the updatronix_pro_update_package filter. | A newer version of Updatronix 3000 is available, but the license server returned no download URL. Set the download link for the product on the license server, or pass the release ZIP URL through the updatronix_pro_update_package filter. | language and grammar |

### `inc/update-engine/class-updatronix-compatibility.php`

| Before | After | Guide section |
|---|---|---|
| requires WordPress %1$s — your version is %2$s | requires WordPress %1$s; your version is %2$s | punctuation |
| requires PHP %1$s — your version is %2$s | requires PHP %1$s; your version is %2$s | punctuation |
| incompatible requirements not met | Requirements not met | formatting |

### `inc/update-engine/class-updatronix-decision-log.php`

| Before | After | Guide section |
|---|---|---|
| Automatic updates pass | Automatic update run | vocabulary |
| Automatic updates pass completed. | Automatic update run completed. | vocabulary |

### `inc/update-engine/class-updatronix-delay-policy.php`

| Before | After | Guide section |
|---|---|---|
| The offer is still inside the soak window configured in Updatronix. | The update is still inside the hold period configured in Updatronix. | language and grammar |

### `inc/update-engine/class-updatronix-engine-policy.php`

| Before | After | Guide section |
|---|---|---|
| The update engine is not available on this site. | The update service is not available on this site. | language and grammar |

### `inc/update-engine/class-updatronix-pending-offer-policy.php`

| Before | After | Guide section |
|---|---|---|
| The update offer is no longer pending. | The update is no longer pending. | language and grammar |
| The pending offer targets a different version. | The pending update targets a different version. | language and grammar |

### `inc/update-engine/class-updatronix-shield-enforcement.php`

| Before | After | Guide section |
|---|---|---|
| Shield could not turn the native auto-update switch back on; it will retry on the next update check. | Update Shield could not turn the native automatic updates switch back on. It retries on the next update check. | vocabulary |
| Safe Mode re-enabled auto-updates for %1$s "%2$s" after %3$s passed the compatibility check. | Safe mode re-enabled automatic updates for %1$s "%2$s" after %3$s passed the compatibility check. | vocabulary |
| Auto-updates are already on; Shield will remove its record. | Automatic updates are already on. Update Shield removes its record. | vocabulary |
| The blocked release version is unknown, so Shield cannot prove a newer release exists. | The blocked release version is unknown, so Update Shield cannot prove that a newer release exists. | vocabulary |
| The same release is still pending; re-enabling the same target stays manual. | The same release is still pending. Re-enabling the same target stays manual. | punctuation |
| The available release is older than the blocked target; no re-enable is attempted. | The available release is older than the blocked version, so Update Shield skips re-enabling. | language and grammar |
| Automatic re-enable is off. Turn on Protect this site, Re-enable auto-updates, and the “turn off auto-updates” behavior to use it. | Automatic re-enable is off. Turn on Protect this site and Automatically re-enable automatic updates, and set incompatible updates to turn automatic updates off. | punctuation |
| A newer release passes every Shield policy; it will be re-enabled on the next update check. | A newer release passes every Update Shield rule. It is re-enabled on the next update check. | vocabulary |
| Safe Mode disabled auto-updates for incompatible %1$s "%2$s": %3$s | Safe mode disabled automatic updates for incompatible %1$s "%2$s": %3$s | vocabulary |
| Safe Mode prevented auto-update of %1$s "%2$s": %3$s | Safe mode prevented the automatic update of %1$s "%2$s": %3$s | vocabulary |
| Safe Mode blocked %1$s "%2$s" (%4$s): %3$s | Safe mode blocked %1$s "%2$s" (%4$s): %3$s | vocabulary |

### `inc/update-engine/class-updatronix-update-pass-gate.php`

| Before | After | Guide section |
|---|---|---|
| The automatic update pass could not be created; native automatic updates run unserialized for this request. | The automatic update run could not be created. Native automatic updates run without it for this request. | language and grammar |

### `inc/update-engine/class-updatronix-update-pass-health.php`

| Before | After | Guide section |
|---|---|---|
| Automatic update pass | Automatic update run | vocabulary |
| No automatic update pass is active. The next scheduled check starts one. | No automatic update run is active. The next scheduled check starts one. | vocabulary |
| Review the pass | Review the run | vocabulary |
| No pass yet | No run yet | vocabulary |
| Automatic update last pass | Last automatic update run | vocabulary |
| No automatic update pass has been recorded yet. | No automatic update run has been recorded yet. | vocabulary |
| Automatic update drain | Automatic update queue | vocabulary |
| A pass is active but its follow-up event is not scheduled. The next scheduled check restarts it. | A run is active, but its follow-up event is not scheduled. The next scheduled check restarts it. | punctuation |
| The pass drains one item at a time through one follow-up event. | The run processes one item at a time through one follow-up event. | language and grammar |
| A pass is draining %1$d item(s); %2$d done. Pass %3$s. | The update run is processing %1$d item; %2$d done. Run %3$s. | vocabulary |
| The last pass covered %1$d item(s): %2$d done, %3$d failed, %4$d uncertain. | The last run covered %1$d item: %2$d done, %3$d failed, %4$d uncertain. | vocabulary |

### `inc/update-engine/class-updatronix-update-pass-log.php`

| Before | After | Guide section |
|---|---|---|
| Automatic updates pass: %1$d items, %2$d done, %3$d failed. | Automatic update run: %1$d items, %2$d done, %3$d failed. | vocabulary |

### `inc/update-engine/class-updatronix-version-control-detector.php`

| Before | After | Guide section |
|---|---|---|
| Managed via Composer. | Installed with Composer. | language and grammar |
| the item root is unreadable | The item's root folder cannot be read. | language and grammar |
| the WordPress update checker is unavailable | The WordPress update checker is unavailable. | formatting |
| WordPress reports a VCS checkout that the direct scan could not identify | WordPress reports a version-control checkout that the scan could not identify. | language and grammar |
| a VCS marker resolves outside the inspected path | A version-control marker points outside the inspected folder. | language and grammar |
| the item root | the item's root folder | language and grammar |
| the site root | the site's root folder | language and grammar |
| bundled composer.json + vendor | Bundled Composer files | language and grammar |
| symlinked item root outside the WordPress roots | A symlinked root folder outside the WordPress folders | language and grammar |
| a symlinked item root has no Composer provenance | A symlinked root folder has no record of a Composer install. | language and grammar |
| the project Composer metadata is unreadable | The project's Composer metadata cannot be read. | language and grammar |
| no evidence | No evidence | formatting |

### `inc/update-shield/class-updatronix-safe-mode.php`

| Before | After | Guide section |
|---|---|---|
| Note: Delayed updates are also active — they apply to all compatible updates. | Delayed updates are also active. They apply to all compatible updates. | punctuation |
| Safe Mode detected incompatible %1$s "%2$s": %3$s | Safe mode detected incompatible %1$s "%2$s": %3$s | vocabulary |
| Safe Mode disabled auto-updates for incompatible %1$s "%2$s": %3$s | Safe mode disabled automatic updates for incompatible %1$s "%2$s": %3$s | vocabulary |
| Safe Mode detected incompatible %1$s "%2$s"; auto-updates stay on: %3$s | Safe mode detected incompatible %1$s "%2$s"; automatic updates stay on: %3$s | vocabulary |
| Safe Mode prevented auto-update of %1$s "%2$s" and disabled auto-updates: %3$s | Safe mode prevented the automatic update of %1$s "%2$s" and disabled automatic updates: %3$s | vocabulary |
| Email notification was queued for the configured recipient. | Updatronix queued an email notification for the configured recipient. | language and grammar |
| Auto-update locked by Safe mode: %s | Automatic updates locked by Safe mode: %s | vocabulary |
| Auto-update locked | Automatic updates locked | vocabulary |
|   - %1$s (%2$s) — Reason: %3$s |   - %1$s (%2$s): %3$s | punctuation |
| Auto-updates have been disabled for incompatible plugins and themes. You can re-enable them manually in the admin. | Automatic updates have been disabled for incompatible plugins and themes. You can re-enable them in the admin. | vocabulary |
| These updates were canceled for this release only. Auto-updates remain on. | Update Shield canceled these updates for this release only. Automatic updates remain on. | vocabulary |
| This notification was sent by Updatronix Pro Safe mode. | Updatronix 3000 Safe mode sent this email. | vocabulary |

### `inc/update-shield/class-updatronix-update-shield.php`

| Before | After | Guide section |
|---|---|---|
| WordPress.org API unreachable. Please try again later. | WordPress.org could not be reached. Try again later. | general guidelines |
| Re-enable auto-updates when a later release is compatible | Re-enable automatic updates when a later release is compatible | vocabulary |
| Version Lock: block item-root VCS checkouts and Composer-managed items | Version Lock: block version-control checkouts and items installed with Composer | language and grammar |

### `inc/update-shield/class-updatronix-version-lock-admin.php`

| Before | After | Guide section |
|---|---|---|
| Auto-update locked: managed via %s | Automatic updates locked: managed via %s | vocabulary |
| The update of this item is managed via %s. Please update through your manager. | This item is managed by %s. Update it there. | language and grammar |
| Automatic updates for this item are managed via %s. Manual updates are allowed. | Automatic updates for this item are managed by %s. Manual updates are allowed. | vocabulary |
| Version Lock is off because the old “Composer lock” switch was saved off on this site. The switch now also covers item-root Git, SVN, Mercurial, and Bazaar checkouts and Composer provenance, not only bundled Composer metadata. Turn on Version Lock in Update Shield → Version Lock. Manual updates stay available unless you also enable “Block manual updates”. | Version Lock is off because the Composer lock switch was saved as off on this site. The switch now also covers Git, SVN, Mercurial, and Bazaar checkouts, and Composer installs, not only bundled Composer files. Turn on Version Lock in Update Shield > Version Lock. Manual updates stay available unless you also turn on Block manual updates. | language and grammar |

### `inc/update-shield/class-updatronix-version-lock-guard.php`

| Before | After | Guide section |
|---|---|---|
| The update of this item is managed via %s. Please update through your manager. | This item is managed by %s. Update it there. | language and grammar |
| Automatic updates for this item are managed via %s. Manual updates are allowed. | Automatic updates for this item are managed by %s. Manual updates are allowed. | vocabulary |

### `inc/white-label/class-updatronix-white-label.php`

| Before | After | Guide section |
|---|---|---|
| The selected logo is not a valid image attachment. | The logo must be a PNG, JPEG, or WebP image. | language and grammar |

## Structural edits

These are not one-string swaps and are listed outside the tables.

1. **`updatronix-pro/assets/src/js/components/LicensePanel.js`** — the
   Developer tools feature is shipped, but it carried `status: 'roadmap'`.
   Set it to `available`, delete the Roadmap badge branch, delete the
   Roadmap note, and delete the `Roadmap` badge string. The XML export
   promise goes with it: the guide forbids predicting future features.
2. **`updatronix-pro/assets/src/js/components/UpdateShieldPanel.js`** —
   `Blocked items: %1$d plugins, %2$d themes%3$s` produced `1 plugins`.
   Rebuild the summary from three `_n()` counts, including core, so every
   number agrees with its noun. Remove the `, 1 core` fragment.
3. **`updatronix-pro/assets/src/js/components/UpdateShieldPage.js`** —
   four `item(s)` strings become `_n()` calls, and the eligible-count
   sentence becomes `_n()` so one eligible item reads as singular.
4. **`updatronix/inc/admin/links.php`** — remove the ` ☕` suffix from the
   Support link. Decorative emoji are not part of interface copy.
5. **`updatronix/inc/classes/class-updatronix-health.php`** — the two
   disk-space strings embed U+00A0 between the number and the unit
   (`"\u{00A0}"`), so the unit cannot wrap. The visible text is unchanged
   apart from the space.
6. **`updatronix/assets/src/js/pages/HealthPage.js`** — the issue-count
   translators comment now says `%d: number of issues to review.` No string
   changed.
7. **`updatronix-pro/assets/src/js/components/UpdateShieldPanel.js`** and
   **`shieldState.js`** — the blocked summary is built from non-zero `_n()`
   labels and wrapped in `Blocked items: %s`, so one blocked plugin reads
   `Blocked items: 1 plugin`.
8. **`updatronix-pro/inc/license/class-updatronix-pro-license-api.php`** —
   both parse failures keep the shared user message and add a `cause` key
   (`invalid_json`, `unexpected_structure`), logged under `WP_DEBUG`.
9. **`updatronix-pro/assets/src/scss/_pro-settings.scss`** — delete the dead
   `.updatronix-license-roadmap-note` rule.
10. **`updatronix-pro/assets/src/js/components/LicensePanel.js`** — remove the
    unused `status` property from `FEATURES`.

## Plural forms added

The pass converted every `item(s)` and count sentence to `_n()`. The full
list of plural pairs touched:

| Plugin | Singular | Plural |
|---|---|---|
| Free | `%d issue to review` | `%d issues to review` |
| Free | `New releases are held for %d day …` | `New releases are held for %d days …` |
| Pro | `%d item could not be inspected. …` | `%d items could not be inspected. …` |
| Pro | `Automatic re-enable is on. %d item is eligible now.` | `Automatic re-enable is on. %d items are eligible now.` |
| Pro | `%d cached item has unknown release data. …` | `%d cached items have unknown release data. …` |
| Pro | `%d installed item is not in the warning list. …` | `%d installed items are not in the warning list. …` |
| Pro | `The update run is processing %1$d item; …` | `The update run is processing %1$d items; …` |
| Pro | `The last run covered %1$d item: …` | `The last run covered %1$d items: …` |
| Pro | `%d plugin` / `%d theme` / `%d core item` | `%d plugins` / `%d themes` / `%d core items` |

## Humanizer pass

Ran over every changed sentence, surface by surface. It removed staging
openers (`Note:`, `Warning only:`), label-colon status lines, em dashes used
as connectors, ellipses in progress labels, and passive sentences whose actor
was known.

| Surface | What the pass changed |
|---|---|
| Free shell, Settings, Support | Named the actor: WordPress sends the emails, Updatronix removes server paths, the WordPress.org community provides support. |
| Free Update logs | Dropped "process details", the em dash in the filter summary, and the ellipsis in loading labels. |
| Free Auto updates, Schedule | Replaced cron, build, and tick jargon; named Updatronix as the actor that defers releases; removed semicolon joins. |
| Free Health | Removed the ellipsis, replaced "Learn more" with "Read the guide", aligned the issue count with the heading. |
| Free Updatronix 3000 preview | Removed the future-release sentence and the repeated product name. |
| Pro License | Removed the roadmap opener, "Failed to", "successfully", and the passive license-storage sentence. |
| Pro Update Flow | Replaced "pass" with "run", "predecessor" with "earlier step", and named the editor as the actor that blocks circular requirements. |
| Pro Update Shield | Replaced policy, offer, provenance, and drain jargon; removed the "Warning only:" opener; merged the checked-items status into one sentence; named Update Shield as the actor. |
| Pro White label | Straightened quotes, removed ellipses, named Safe mode. |
| Pro PHP | Named Safe mode, Update Shield, and Updatronix in log and notice messages; removed "Please", "successfully", and the "Note:" opener. |
| Final consistency pass | Named the subject in the Updatronix 3000 preview, pluralized the run summaries, and normalized the last Shield log messages. |

The five tells that survive a rewrite most often:

- Not-X-but-Y: 0.
- A one-line closer: 0. The short final sentences are instructions
  ("Refresh the page.", "Try again.") or facts ("Automatic updates remain
  on."), not restatements of the sentence before.
- A dash: 0.
- A triad: 5, each a list of real features or named tools (Git, SVN,
  Mercurial, Bazaar; single, bulk, update-core).
- A bold label: 0.

No new copy uses an ellipsis, a curly quote, an exclamation point, or a
decorative emoji.

## Kept on purpose

- The `Pro` badge on the rail and on the Documentation card. It is a marker,
  not prose; the surrounding sentences use `Updatronix 3000`.
- `diagnostics` on the Health screen. The glossary reserves it for the data
  payload, which is exactly what the copy and its Copy button refer to.
- `WP-Cron`, `wp-config.php`, `wp-cron.php`, and the constant names. They are
  literal WordPress terms a site owner can act on.
- `Pass` as a column header in the update log. The surrounding prose uses
  `run`; the column keeps the stored vocabulary.
- Every placeholder, `<a href>` fragment, translators comment, and link
  target in the touched strings.

## French

`wp-content/languages/plugins/updatronix-fr_FR.po` is a GlotPress pack, not
a repository file. Each changed string falls back to English in the French
admin until translate.wordpress.org catches up. The tables above are the
list to re-translate; the local pack is not edited.

## Verification

- `bin/harness pot` in both plugins, then confirm every changed `msgid` in
  this document appears in the new `.pot`.
- `bin/harness lint`, `bin/harness test`, `bin/harness test:js` in both.
- `git diff --check` in both.
- `tests/Unit/CopyGlossaryTest.php` in both plugins guards the banned terms.
- `tests/js/shieldState.test.js` pins the blocked summary, and
  `tests/Unit/LicenseApiTest.php` pins the parse causes.
