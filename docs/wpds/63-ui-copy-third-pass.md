# UI copy — third pass

**Status:** English rewrites applied and rebuilt; the Pro French pack is complete
in the Pro `docs/wpds/64-pro-french-pack.md`.

## Why this pass

The live bundle was stale. `assets/build/708.js` still shipped three strings
that no longer exist in `assets/src`:

- `Stop incompatible updates, order automatic updates, and protect version-controlled extensions.`
- `Official guides for Updatronix, Updatronix 3000, and the WordPress update system.`
- `Manage your Updatronix 3000 license and Pro tools.`

The source already had `Blocks incompatible updates and protects version-controlled extensions.`,
`Guides for Updatronix, Updatronix 3000, and the WordPress update system.`, and
`Manage your Updatronix 3000 license and tools.` The first build of this pass removed the
old strings. The remaining nine strings in the user's list are current source, and
are rewritten below.

## Build audit

- Free and Pro bundles rebuilt with `bin/harness build`.
- After the rebuild, the Free bundle contains `Blocks incompatible updates`,
  `Guides for Updatronix`, and `license and tools`; it contains none of the three
  stale strings.
- The Pro bundle has no routes copy and none of the stale strings.
- 55 list-like candidates were found in the fresh source inventory; 46 reach the
  JS bundles. The `live` column below is a JS-bundle check: PHP strings are
  server-rendered and are listed separately.

## Rewrite set

These are the nine current-source strings. They are the user's list with the two
stale build-only strings removed and the current `Blocks…` and `Guides for…`
strings kept in their place.

| File | Current | Proposed | Why |
|---|---|---|---|
| `assets/src/js/shell/routes.js:25` | Every core, plugin, theme, and translation update, with the version before and after. | A record of every update, with the version before and after. | Four update types behind one noun phrase; the page itself shows the types. |
| `assets/src/js/shell/routes.js:36` | Core, translation, theme, and plugin settings in one place. | All automatic update settings in one place. | Four setting types behind “all”; the page itself shows them. |
| `assets/src/js/shell/routes.js:47` | Choose how often WordPress checks for updates, and how long new releases are held. | Choose the update check frequency and the hold period for new releases. | Two decisions in one sentence; no list rhythm. |
| `assets/src/js/shell/routes.js:69` | Blocks incompatible updates and protects version-controlled extensions. | Blocks incompatible updates and protects extensions under version control. | Two clauses; “under version control” replaces the adjective. |
| `assets/src/js/shell/routes.js:91` | Put your name, mark, and colors on every Updatronix screen. | Make every Updatronix screen look like your own. | The three brand fields are settings; the sentence is the outcome. |
| `assets/src/js/shell/routes.js:102` | See whether this site is ready for its next update, and what needs attention. | See what needs attention before the next update. | The second clause carries the action; the yes/no clause is implied. |
| `assets/src/js/shell/routes.js:113` | Logging, retention, email routing, and uninstall data. | Set how long logs are kept and where email goes. Choose what uninstall removes. | Two sentences group the four areas; no list rhythm. |
| `assets/src/js/shell/routes.js:124` | Guides for Updatronix, Updatronix 3000, and the WordPress update system. | Guides to Updatronix and the WordPress update system, including Updatronix 3000. | Two guide families plus the Pro section; no triple. |
| `assets/src/js/shell/routes.js:135` | What to check before you post, and where to ask the WordPress community. | How to report a problem and where to get help from the WordPress community. | Two actions; no list rhythm. |

These nine strings are now in `assets/src/js/shell/routes.js`; the table records the
before and after.

## Kept — live JS copy

These strings are factual enumerations, status values, data summaries, or
two-clause instructions. A list is the clearest form for the enumerations; the
two-clause lines are not list rhythm.

| File | Text | Why |
|---|---|---|
| `assets/src/js/components/SettingsPanel.js:261` | Records every installed, failed, and skipped update. | Status or data enumeration; each item is a real state. |
| `assets/src/js/components/SettingsPanel.js:427` | They contain the recovery link. Do not turn them off, or you could lock yourself out of the site. | Warning with a consequence; not a list. |
| `assets/src/js/components/SettingsPanel.js:446` | Removes update logs, settings, and schedules when the plugin is uninstalled. | Status or data enumeration; each item is a real state. |
| `assets/src/js/components/activityLog/ActivityLogPanel.js:857` | Search and filter the history, then open a record for the full details. | Two-step instruction; not a list. |
| `assets/src/js/components/activityLog/ExportLogsModal.js:386` | Too many exports started recently. Wait a minute, and then try again. | Error with a recovery action; not a list. |
| `assets/src/js/components/activityLog/ExportLogsModal.js:638` | Create a summary of the log matching the current search, filters, and sort. Filters that are not set include every value. | Export control or column label; each item is a real option. |
| `assets/src/js/components/activityLog/ExportLogsModal.js:743` | Combine repeated updates of the same plugin, theme, core release, or translation into a single line with the earliest and latest versions. | Export control or column label; each item is a real option. |
| `assets/src/js/components/activityLog/ExportLogsModal.js:769` | Choose which parts of the report to include. Element, version, and date columns are always shown. | Export control or column label; each item is a real option. |
| `assets/src/js/components/activityLog/LogDetailsContent.js:75` | Could not load the log details. Try again, or check your database connection. | Error with two recovery actions; not a list. |
| `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js:37` | Security and maintenance releases, such as 7.1 → 7.1.1. | Release-type examples; not a list. |
| `assets/src/js/components/autoUpdates/ConstantNotices.js:25` | WP_AUTO_UPDATE_CORE is set in your wp-config.php file. It controls which core updates run automatically, and this setting cannot be changed here. | Constant explanation with two clauses; not a list. |
| `assets/src/js/hooks/useAutoUpdates.js:76` | The core update setting could not be saved. Check your connection, and then try again. | Error with a recovery action; not a list. |
| `assets/src/js/hooks/useAutoUpdates.js:242` | Some automatic update changes may have been applied. The page now shows the server state. Review the changes, and then try again. | Error with a recovery action; not a list. |
| `assets/src/js/hooks/usePluginSettings.js:223` | Your settings could not be saved. Check your connection, and then try again. | Error with a recovery action; not a list. |
| `assets/src/js/pages/DocumentationPage.js:21` | Install Updatronix, then use the guide to understand each update setting and read the update log. | Sequence with “then”; no list. |
| `assets/src/js/pages/DocumentationPage.js:31` | License activation, Update Shield, Safe mode, and the Updatronix 3000 maintenance workflow. | Guide topic or developer list; each item is a real topic. |
| `assets/src/js/pages/DocumentationPage.js:42` | Hooks, filters, and APIs behind the WordPress update system. | Guide topic or developer list; each item is a real topic. |
| `assets/src/js/pages/ProDestination.js:185` | Updatronix 3000 adds Update Shield, Update Flow, White label, and exports in CSV and JSON. No Updatronix 3000 feature is active on this site. | Four real Pro features. |
| `assets/src/js/pages/SupportPage.js:28` | Include the item, versions, error code, and time from the update log. | Four real details to include in a report. |
| `updatronix-pro/assets/src/js/components/LicensePanel.js:51` | Puts your name, mark, and colors on the admin and emails. | Pro feature or brand list; each item is real. |
| `updatronix-pro/assets/src/js/components/LicensePanel.js:65` | Hooks, functions, and a REST API for your own update workflow. | Pro feature or brand list; each item is real. |
| `updatronix-pro/assets/src/js/components/LicensePanel.js:89` | Protection, update order, branding, and developer tools. | Pro feature or brand list; each item is real. |
| `updatronix-pro/assets/src/js/components/LicensePanel.js:313` | Your WordPress security keys may have changed. Remove the stored license, and then activate the key again. | Error with two recovery actions; not a list. |
| `updatronix-pro/assets/src/js/components/UpdateFlowPage.js:1299` | One save for the whole screen. Chain edits stay on this page until you choose Save changes, and the editor blocks circular requirements. | Instruction with two clauses; not a list. |
| `updatronix-pro/assets/src/js/components/UpdateFlowPage.js:1351` | For example, gateways update before the shop, so checkout is ready. | Instruction with two clauses; not a list. |
| `updatronix-pro/assets/src/js/components/UpdateShieldPage.js:305` | Only items that Update Shield turned off, and only when a newer release passes every policy. The same release stays manual. | Two clauses; not a list. |
| `updatronix-pro/assets/src/js/components/UpdateShieldPage.js:567` | Blocks automatic updates for items in a Git, SVN, Mercurial, or Bazaar checkout, and for items installed with Composer. The native switch stays locked, and the stored option does not change. | Pro rule, VCS, or update-type list; each item is real. |
| `updatronix-pro/assets/src/js/components/UpdateShieldPage.js:579` | For locked items, blocks single, bulk, and update-core updates in the admin, plus direct update URLs. WP-CLI still works for these items. | Pro rule, VCS, or update-type list; each item is real. |
| `updatronix-pro/assets/src/js/components/UpdateShieldPage.js:605` | Applies to updates managed by Update Shield, including Update Flow steps. WordPress core has no automatic updates switch, so both options skip only that core release. | Two clauses; not a list. |
| `updatronix-pro/assets/src/js/components/WhiteLabelPage.js:287` | Choose a PNG, JPEG, or WebP image. | Brand, format, or UI-target list; each item is real. |
| `updatronix-pro/assets/src/js/components/WhiteLabelPage.js:489` | PNG, JPEG, or WebP, at least %1$d × %1$d px, up to %2$s. Falls back to your initial. | Brand, format, or UI-target list; each item is real. |
| `updatronix-pro/assets/src/js/components/WhiteLabelPage.js:551` | Replaces "Updatronix" in the rail, breadcrumbs, and Safe mode emails. | Brand, format, or UI-target list; each item is real. |
| `updatronix-pro/assets/src/js/components/WhiteLabelPage.js:665` | Used for buttons, links, and the active menu item. | Brand, format, or UI-target list; each item is real. |
| `updatronix-pro/assets/src/js/components/WhiteLabelPage.js:736` | Replaces the Updatronix name and mark in the admin rail and menu. | Two UI places; not a list. |
| `updatronix-pro/assets/src/js/components/WhiteLabelPage.js:765` | The admin menu and a Safe mode email, as your users see them. | Two preview examples; not a list. |
| `updatronix-pro/assets/src/js/components/WhiteLabelPage.js:887` | This restores the default brand, colors, logo, and switches. Save to apply it. | Brand, format, or UI-target list; each item is real. |
| `updatronix-pro/assets/src/js/shieldState.js:266` | The update does not install for this release; automatic updates stay on, and Update Shield checks the next release again. | Two-state explanation; not a list. |

## Server-rendered PHP copy

These strings are not in the JS bundle because PHP renders them. They still
reach the admin. The list is kept as factual validation or data copy.

| File | Text | Why |
|---|---|---|
| `updatronix-pro/inc/license/class-updatronix-pro-license-updater.php:511` | A newer version of Updatronix 3000 is available, but the license server returned no download URL. Set the download link for the product on the license server, or pass the release ZIP URL through the updatronix_pro_update_package filter. | Server-rendered license notice with two recovery paths; not a list. |
| `updatronix-pro/inc/update-engine/class-updatronix-shield-enforcement.php:364` | Automatic re-enable is off. Turn on Protect this site and Automatically re-enable automatic updates, and set incompatible updates to turn automatic updates off. | Server-rendered policy notice; not a list. |
| `updatronix-pro/inc/update-engine/class-updatronix-update-pass-health.php:86` | The last run covered %1$d item: %2$d done, %3$d failed, %4$d uncertain. | Run counts; data summary, not prose. |
| `updatronix-pro/inc/update-engine/class-updatronix-update-pass-log.php:325` | Automatic update run: %1$d items, %2$d done, %3$d failed. | Run counts; data summary, not prose. |
| `updatronix-pro/inc/update-flow/class-updatronix-update-flow.php:541` | A chain step is not a plugin, theme, or core item. | Item-type validation; each type is real. |
| `updatronix-pro/inc/white-label/class-updatronix-white-label.php:154` | The logo must be a PNG, JPEG, or WebP image. | Image-format validation; each format is real. |

## Source-only JS

These strings are in the source but not in the built bundle. Verify whether the
component is reachable before keeping them in a later dead-code pass.

| File | Text | Why |
|---|---|---|
| `assets/src/js/components/autoUpdates/CoreSection.js:34` | All releases, including major (for example, 6.4 to 6.5) | Not in the built bundle; check reachability. |
| `updatronix-pro/assets/src/js/components/UpdateShieldPage.js:402` | WordPress blocks automatic updates in the version-control checkouts it detects. Version Lock also covers each item\'s root folder and items installed with Composer, and it keeps the native automatic updates switch locked. | Not in the built bundle; check reachability. |
| `updatronix-pro/assets/src/js/components/UpdateShieldPanel.js:80` | Prevent automatic updates of plugins, themes, and WordPress core that have not been tested with your site's current version of WordPress or PHP. | Not in the built bundle; check reachability. |

## Decisions

- Rewrite the nine destination descriptions; their pages still show the item
  types, formats, and VCS names.
- Keep the factual enumerations. A list of image formats, VCS names, update
  types, or status values is the clearest form; rewriting them would hide the
  set the reader needs.
- The two stale build-only strings are not source strings. The rebuild removed
  them; no source rewrite is needed for those two.
- PHP strings are server-rendered; the JS bundle check is not a verdict on them.

## English QA

- Free: 344 PHP tests, 15 JS files. Pro: 483 PHP tests, 6 JS files.
- Free lint: `[OK] No errors`. Pro lint: 0 errors, pre-existing warnings only.
- Both `git diff --check` runs are clean after the two generated asset manifests
  were stripped of trailing whitespace.
- The rendered 390/1280 check is blocked by the login wall and the broken E2E
  database provisioning; it remains manual.

## Verification

- `grep` on the rebuilt Free bundle: no `Stop incompatible`, `Official guides`, or
  `Pro tools`; present `Blocks incompatible`, `Guides for Updatronix`, and
  `license and tools`.
- `build-listlike.py` cross-check: 55 rows, 46 live in JS, 9 server-only or
  source-only.
- The full machine-readable list is the 55-row TSV used to generate this
  document.
