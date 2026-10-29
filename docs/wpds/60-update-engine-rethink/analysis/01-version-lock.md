# Version Lock — analysis (Update Shield)

**Date:** 2026-10-28
**Status:** Analysis only. No production code changed, no task file created,
no version bumped.
**Owner:** Quentin.
**Feature owner:** Pro (`updatronix-pro`).
**Shared docs read:** 22, 45 §2.4/§3.1/§4.2/§5.4/§7.1/§8.3, 47 §2/§3/§7,
51.
**Grilling record:** `updatronix-pro/.agents/notes/2026-10-28-grill-version-lock.md`.
**QA record:** `updatronix-pro/.agents/notes/2026-10-28-qa-version-lock.md`.

---

## 1. Scope and non-goals

### In scope

- Native VCS detection for plugins, themes, and core.
- Composer provenance detection: the item itself installed by Composer, not
  merely bundling Composer dependencies.
- The `version_control` policy, its place in the engine/adapter chains, and its
  result/log shape.
- Automatic enforcement in the native updater, the queue, and Flow.
- Manual enforcement in the native admin (single, bulk, update-core, direct
  `update.php` URL), REST, and WP-CLI.
- Native admin presentation: update row, update link, auto-update toggle,
  update-core table, Updatronix Auto updates screen, Shield card.
- The manager label and the short notice.
- Cache/invalidation, multisite, logging, and tests.

### Non-goals

- Replacing WordPress’s update engine or admin pages.
- Deciding the queue execution model, Flow ordering, Schedule display, or the
  Logger schema. Those are the other four analyses; this document states what
  Version Lock needs from them.
- Implementing the fix. No production file was edited.
- Changing existing i18n strings without owner approval.

---

## 2. Current behavior

The table below maps one row per surface. “Verified” means the code path was
read and, where possible, probed on the live DDEV site; probes were read-only
except for the normal Free logger behavior.

| Surface | Implementation (file:line) | Actual behavior | Native WordPress interaction | Verified? |
|---|---|---|---|---|
| Native VCS detection | `inc/update-engine/class-updatronix-version-control-detector.php:250-294, 433-445` | Calls `is_vcs_checkout()` with `WP_PLUGIN_DIR` for every plugin, `get_theme_root()` for every theme, and `ABSPATH` for core. The item root is never passed. | Mirrors the contexts WordPress itself uses in `WP_Automatic_Updater::should_update()` (`wp-admin/includes/class-wp-automatic-updater.php:374-378`). It sees what core sees, so it misses an item-root checkout and flags every item under a parent checkout. | Yes: code + live probe. `vcs_plugins_dir=no`, `vcs_item_root=yes`. |
| Composer detection | Same file `:311-352` | Returns `yes` when the item root contains both `composer.json` and `vendor/composer/installed.json`; rejects marker symlinks and markers outside the root; returns `no` when the pair is partial or the item has no root (single-file); returns `unknown` when the root cannot be resolved. | No native interaction. It is a Pro-only heuristic. It flags bundled dependencies and misses project-level Composer/provenance metadata. | Yes: code + live probe. Both Updatronix plugins report `native=no composer=yes`, though their `installed.json` is dev-only. |
| Policy chain | `inc/update-engine/class-updatronix-policy-chain.php:29-44, 57-67`; `inc/update-engine/class-updatronix-version-control-policy.php:67-96` | Engine order: license, engine, native guards, WP compatibility, PHP compatibility, delay, **version control**, abandoned, pending offer, chain dependency. Adapter order: native guards, WP compatibility, PHP compatibility, **version control**, abandoned. First denial wins. | The policy is pure; it never writes. The native adapter is the only native consumer. | Yes. |
| Automatic queue enforcement | `inc/update-engine/class-updatronix-queue-scheduler.php:218-260, 545-610`; `inc/update-engine/class-updatronix-update-kernel.php:170-236, 993-1020` | The scheduler collects offers whose native auto-update option is on. The kernel evaluates every policy at enqueue; a denial inserts a `blocked` row and records one journal row. There is no plan-time policy filter. The queue is dormant on this site (`queue_enabled=false`, `queue_live=false`). | When the queue is off, WordPress’s native loop runs; the queue does not write. | Yes. |
| Flow enforcement | `inc/update-engine/class-updatronix-flow-planner.php:60-110, 370-435`; `inc/update-flow/class-updatronix-flow-runner.php:386`; `inc/update-engine/class-updatronix-update-kernel.php:920-945` | The planner orders/dedupes chains and checks installed items/offers; it does not evaluate Shield policies. The runner enqueues each step; the kernel blocks a denied step and cancels downstream rows (`upstream_blocked`). The run becomes failed. | Flow uses the native upgraders through the kernel; it is not the native automatic loop. | Yes. |
| Native auto-update filter | `inc/update-shield/class-updatronix-safe-mode.php:120-122, 327-386`; `inc/update-engine/class-updatronix-shield-ownership.php:43-46, 107-130` | Safe Mode returns the incoming `$update` unchanged when `safe_mode` is false (intended: Version Lock is off when Update Shield is off). When on, it evaluates the adapter chain (compatibility, PHP, version control, abandoned). The ownership handshake at `PHP_INT_MAX` denies engine-owned items. | Hooks `auto_update_plugin|theme|core`; core applies them in `should_update()` (`class-wp-automatic-updater.php:239-260`). | Yes. |
| Native manual update | No Pro hook. Core applies `upgrader_pre_install` at `wp-admin/includes/class-wp-upgrader.php:556`. | Nothing blocks a manual plugin/theme update. This is the default state under the owner decision (manual hard block is an optional toggle, default off). The optional toggle and the full-lock guard do not exist yet. A live hook probe found only `Updatronix_Update_Logger::store_core_version_before` on `upgrader_pre_install`. | Manual `Plugin_Upgrader`/`Theme_Upgrader`/`Core_Upgrader` paths do not call `is_vcs_checkout()`; only the automatic updater does. | Yes: grep + live hook probe. |
| Native auto-update toggle | `inc/update-shield/class-updatronix-safe-mode.php:162-164, 1745-1812`; core `wp-admin/includes/class-wp-plugins-list-table.php:1457`; core `wp-admin/includes/class-wp-ms-themes-list-table.php:887` | Pro replaces the toggle HTML only when the item is in `get_blocked_items()` (incompatible). A version-controlled item keeps the native toggle. The theme filter is applied only by the network themes table; single-site `themes.php` uses the JS template at `wp-admin/themes.php:777-827`. | Plugin filter works on both single-site and network plugin tables. Theme filter does not cover single-site themes. | Yes: code + live probe (`plugin_toggle=ORIGINAL`, `theme_toggle=ORIGINAL`). |
| Updatronix Auto updates screen | Free `inc/classes/class-updatronix-autoupdates.php:284-305, 347-367`; Pro `inc/update-shield/class-updatronix-safe-mode.php:1814-1856`; Free `assets/src/js/components/autoUpdates/ItemsDataViewSection.js:107-120` | Free exposes `auto_update` and `update_data_available`. Pro adds `auto_update_locked`/`auto_update_locked_reason` only for incompatible items. A version-controlled item shows an enabled toggle. | The screen reads/writes the native `auto_update_*` option through Free’s REST routes (`inc/classes/class-updatronix-settings.php:1069-1110`). | Yes. |
| Shield screen card/list | `inc/update-engine/class-updatronix-shield-state.php:51-119`; `assets/src/js/components/UpdateShieldPage.js:318-320, 430-500`; `assets/src/js/shieldState.js:19-62` | Returns counts and item names for native/composer detected and unknown. No manager label, marker path, or reason for a `yes` item. The Composer switch is labeled “Composer lock”. | Read-only REST payload; no native interaction. | Yes. |
| Logging | `inc/update-engine/class-updatronix-update-journal.php:38-72, 100-135`; `inc/update-engine/class-updatronix-shield-enforcement.php:51-90`; `inc/update-shield/class-updatronix-safe-mode.php:1249-1345`; Free `inc/classes/class-updatronix-logger.php:103-107` | Engine/adapter denials write one `blocked` row with `action_type=prevented` for `native_vcs`/`version_controlled`. Fail-open writes an `info` row. Manual updates write nothing for Version Lock; that is expected in the default automatic-only state, and a gap in the full-lock state. Logger dedupes by event key and returns the existing row. | Free log is the audit trail; Pro does not write Free transients/options. | Yes. |
| Multisite | `inc/settings/options.php:13-21`; `inc/update-shield/class-updatronix-update-shield.php:102-128`; `inc/update-engine/class-updatronix-update-engine-bootstrap.php:91-107`; `inc/update-engine/class-updatronix-update-queue.php:169`; `inc/update-shield/class-updatronix-safe-mode.php:199-205` | Shield settings are a network option. The engine is main-site only. Shield REST requires Super Admin + `manage_updatronix_pro` + active license. Safe Mode enqueues its script on network plugin/theme/update-core screens. Version Lock has no network-specific UI. | Core’s automatic updater runs only on the main network/site (`class-wp-automatic-updater.php:644-648`). | Code-read; no live Multisite proof. |

### Code that decides the behavior

Native VCS context:

```php
// class-updatronix-version-control-detector.php:438-444
if ( 'plugin' === $type ) {
    return defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '';
}
$root = function_exists( 'get_theme_root' ) ? (string) get_theme_root( $id ) : '';
return $root;
```

Composer pair:

```php
// class-updatronix-version-control-detector.php:327-332
$composer  = $root_real . DIRECTORY_SEPARATOR . 'composer.json';
$installed = $root_real . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
if ( ! is_file( $composer ) || ! is_file( $installed ) ) {
    return $this->apply_filter( self::STATE_NO, $type, $id, $root_real );
}
```

Policy denial:

```php
// class-updatronix-version-control-policy.php:71-90
if ( $this->detector->is_native_vcs( $job->item_type, $job->item_id ) ) {
    return Updatronix_Policy_Result::deny( ..., 'native_vcs', ... );
}
if ( $this->composer_lock && ... && $this->detector->is_composer_controlled( ... ) ) {
    return Updatronix_Policy_Result::deny( ..., 'version_controlled', ... );
}
```

Safe Mode gate:

```php
// class-updatronix-safe-mode.php:328-330
if ( ! self::$safe_mode || ! is_object( $item ) ) {
    return $update;
}
```

Manual path is unhooked:

```php
// wp-admin/includes/class-wp-upgrader.php:556
$res = apply_filters( 'upgrader_pre_install', true, $args['hook_extra'] );
```

---

## 3. Native WordPress interaction

### 3.1 Automatic updater

`WP_Automatic_Updater::run()` returns early unless the request is the main
network and main site (`class-wp-automatic-updater.php:644-648`). For each
item, `should_update()`:

1. Checks the filesystem credentials and `is_vcs_checkout( $context )`
   (`:210-217`).
2. Reads the item’s native auto-update setting (`:219-232`).
3. Applies `auto_update_{$type}` (`:239-260`).
4. Checks PHP/WordPress requirements (`:262-276`).

The contexts are `WP_PLUGIN_DIR` for plugins, `get_theme_root( $item->theme )`
for themes, and `ABSPATH` for core (`:374-378`). `is_vcs_checkout()` walks up
from each context and also checks `ABSPATH` (`:127-178`). It looks for
`.svn`, `.git`, `.hg`, `.bzr` (`:133`). It is filterable through
`automatic_updates_is_vcs_checkout` (`:178`).

Consequences:

- A plugin directory with its own `.git` is **not** detected by WordPress’s
  automatic updater, because the context is `WP_PLUGIN_DIR`.
- A `.git` in `WP_PLUGIN_DIR` (or above) flags **every** plugin.
- A `.git` at `ABSPATH` flags every plugin, every theme, and core.
- The theme context is the theme root, not the individual theme directory, so
  a theme with its own `.git` is also invisible.
- A third-party filter can override the boolean.

### 3.2 Manual updater

There is no VCS check in the manual path. `WP_Upgrader::install_package()`
applies `upgrader_pre_install` before writing
(`wp-admin/includes/class-wp-upgrader.php:556`). `Plugin_Upgrader` and
`Theme_Upgrader` add their own callbacks to that filter
(`class-plugin-upgrader.php:211-214`, `class-theme-upgrader.php:316-317`), but
there is no core VCS check. A plugin/theme can be updated manually even when
its automatic update is refused.

`upgrader_pre_install` is the single server-side choke point for:

- `update.php?action=upgrade-plugin&plugin=…` / `upgrade-theme&theme=…`
- `update-core.php` single and bulk (`do-plugin-upgrade`, `do-theme-upgrade`)
- `plugins.php` / `themes.php` bulk actions
- WP-CLI `wp plugin update` / `wp theme update`
- Any third-party REST/AJAX route that calls the native upgrader

It receives `hook_extra` with the plugin file or theme stylesheet, the type,
and the action. Returning a `WP_Error` short-circuits the install
(`class-wp-upgrader.php:556-560`). `Plugin_Upgrader::bulk_upgrade()` records
the error and continues with the next item unless the result is `false`
(`class-plugin-upgrader.php:340-430`), so a per-item block does not have to
abort the whole bulk.

### 3.3 Update-core

`update-core.php` renders the plugin table at `:497` (`#update-plugins-table`)
and the theme table at `:673` (`#update-themes-table`). The plugin form posts
to `update-core.php?action=do-plugin-upgrade` (`:473`), the theme form to
`do-theme-upgrade` (`:647`). The handlers call `bulk_upgrade()`
(`:1188`, `:1229`, `:1287`). There is no per-row filter for the table rows;
`core_upgrade_preamble` fires before the tables (`:762-830` in Pro’s Safe Mode
is the existing template mechanism). A row-level Version Lock notice needs a
scoped JS augmentation or a core filter that does not exist.

### 3.4 Plugin list table

`wp_plugin_update_row()` outputs the update notice and the “update now” link:

- The link is `wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file, ... )` with `class="update-link"` (`wp-admin/includes/update.php:562-570`).
- `in_plugin_update_message-{$file}` fires inside `.update-message`
  (`update.php:614`), so a server-side notice can be appended without
  replacing the row.
- The auto-update toggle is filtered by
  `plugin_auto_update_setting_html` (`class-wp-plugins-list-table.php:1457`).
- The update row is `tr.plugin-update-tr` with `data-slug` and `data-plugin`
  (`update.php:506-514`).

### 3.5 Theme list table and theme cards

`wp_theme_update_row()` outputs the theme update notice and the “update now”
link (`wp-admin/includes/update.php:690-768`; link at `:754-762`).
`in_theme_update_message-{$theme}` fires inside `.update-message`
(`:837`). But single-site `themes.php` does not render a list table; it loops
theme cards (`wp-admin/themes.php:429-620`) and renders the update message at
`:446-459`. The `after_theme_row` action is not fired by that page. The
auto-update toggle on single-site is a JS template
(`wp-admin/themes.php:777-827`), and the `theme_auto_update_setting_html`
filter is applied only by the network themes table
(`wp-admin/includes/class-wp-ms-themes-list-table.php:887`).

`wp_prepare_themes_for_js()` applies a `wp_prepare_themes_for_js` filter
(`wp-admin/includes/theme.php:812`), which is the clean place to add a lock
state to the theme data. `theme_auto_update_setting_template` is filterable
(`wp-admin/themes.php:821-827`), which is the clean place to render a locked
toggle for single-site themes.

### 3.6 WP-CLI

WP-CLI uses `Plugin_Upgrader`/`Theme_Upgrader`; it reaches
`upgrader_pre_install` like the admin. It does not run the automatic updater’s
`is_vcs_checkout()`. A Version Lock callback on `upgrader_pre_install` blocks
CLI updates too, unless the callback deliberately exempts `WP_CLI` or a
documented constant/filter.

### 3.7 Multisite

- `WP_Automatic_Updater::run()` is main-network/main-site only
  (`class-wp-automatic-updater.php:644-648`).
- The queue table and engine are main-site only
  (`class-updatronix-update-engine-bootstrap.php:91-107`,
  `class-updatronix-update-queue.php:169`).
- Shield settings are a network option
  (`inc/settings/options.php:13-21`), so one lock decision applies to the
  network.
- Shield REST requires Super Admin on Multisite
  (`class-updatronix-update-shield.php:116-121`).
- Safe Mode enqueues its script on `plugins-network`, `themes-network`, and
  `update-core-network` (`class-updatronix-safe-mode.php:199-205`), but
  Version Lock has no network-specific surface.
- The native plugin/theme screens on a subsite are capability-restricted; the
  network admin is the relevant manual surface. This needs a live test.

---

## 4. Owner expectation

The owner’s five statements, restated in the owner’s terms:

1. **Automatic lock.** Version Lock must prevent automatic updates for
   extensions managed by a version system such as Git or SVN.
2. **Native lock.** Automatic updates must be locked on the Updatronix screens
   **and** on the native WordPress screens. The item must not be updatable
   normally from the native update notification; the auto-update toggle must
   not be activatable.
3. **Surgical override.** The native admin must be overridden by a specific
   Updatronix script loaded on those pages. The override must change only the
   relevant row/toggle/notice; it must never replace or break the native admin.
4. **Notice.** A short notice must say: “The update of this item is managed via
   {management system, e.g. Git}. Please update through your manager.”
5. **Composer provenance.** Do not lock an item merely because it has Composer
   inside. Many plugins use Composer to pull external packages and must update
   normally. Lock an item only when the item itself was installed through
   Composer (e.g., wp-packagist or an equivalent). Audit the need and fix the
   current behavior, which is wrong.

These are owner statements. The grilling record is separate and settled; the
decisions and conflicts are in §9.

**Owner decisions on 2026-10-28 narrow point 2.** The manual hard block is an
additional optional toggle, default off. When it is off, only automatic updates
are locked; the native manual update remains available and the row notice says
so. When it is on, native admin manual updates are hard-blocked. Version Lock
is subordinate to Update Shield: when Update Shield is off, Version Lock does
not operate. The auto-update toggle remains locked whenever Version Lock is on,
independent of the manual toggle. See §9 for the exact answers and conflicts.

---

## 5. Gap analysis

| # | Owner expectation | Verdict | Evidence |
|---|---|---|---|
| 1 | Automatic lock for version-managed extensions | **Partial** | The engine/Flow policy denies native VCS and Composer-controlled items, and the Safe Mode adapter denies them for native automatic items. But item-root VCS is invisible (`get_native_context` uses `WP_PLUGIN_DIR`), Composer provenance is wrong, unknown fails open for the positive-evidence case, and the engine/Flow policy is not gated by `safe_mode` even though the owner decided Version Lock must be off when Update Shield is off. |
| 2 | Native screens locked; item not normally updatable; toggle not activatable | **Mismatch** | No manual block exists; under the owner decision this is a gap only when the optional manual toggle is on, and that toggle does not exist yet. The unconditional parts are also missing: no version-lock row notice or toggle lock (QA-06), the theme single-site path is dead (QA-07), and native AJAX/REST can write the option (QA-12). |
| 3 | Specific surgical script on the native pages | **Mismatch** | There is a Safe Mode augmentation script, but it only handles incompatibility. No Version Lock script exists. The existing script uses fragile update-core selectors and inline markup (QA-14, QA-25). |
| 4 | Short “managed via …” notice | **Mismatch** | No notice is rendered for a version-controlled item. The policy message is generic (“WordPress blocks updates in Git, SVN, Mercurial, or Bazaar checkouts.”) and the detector has no manager label. |
| 5 | Composer provenance, not bundled Composer | **Mismatch** | The current detector is the opposite: it locks bundled Composer and misses project-level provenance. Both Updatronix plugins are flagged on this site; the tests encode the false positive (QA-02, QA-03, QA-16). |

---

## 6. Detection audit

### 6.1 Native VCS

**Current algorithm.** `native_state()` calls
`( new WP_Automatic_Updater() )->is_vcs_checkout( $context )` with
`WP_PLUGIN_DIR`/theme root/`ABSPATH`. `is_vcs_checkout()` walks up from the
context and also checks `ABSPATH`. It returns a boolean; the detector stores
`yes`/`no`/`unknown` and a generic reason. It does not record which marker was
found or where.

**What it proves.** It proves what WordPress’s automatic updater would treat as
a VCS checkout for that context. It does **not** prove that the individual item
is a checkout. It also inherits the `automatic_updates_is_vcs_checkout` filter,
so a third-party plugin can turn a checkout into `no`.

**What the owner needs.** The item root must be checked first, then each
ancestor up to the WordPress root. The result must carry the marker type
(`.git`, `.svn`, `.hg`, `.bzr`), the marker path, and whether the marker is at
the item root, a parent, or the site root. That is the only way to produce
“managed via Git” and to distinguish an item checkout from a site-wide one.

**Recommended algorithm.**

1. Resolve the item root with `realpath`; if it cannot be resolved, return
   `unknown` with the path and apply the Q9 fail-closed rule.
2. Walk from the item root up to (and including) `ABSPATH`, checking for
   `.git`, `.svn`, `.hg`, `.bzr` in each directory. Stop at the first
   (nearest) marker; record `manager`, `marker_path`, and `scope`
   (`item`, `parent`, `site`).
3. Also ask `is_vcs_checkout()` for the native context and `ABSPATH`; if the
   direct scan and the core check disagree, record both. The direct scan is
   the authority for the item-root/manager label; the core check is the
   authority for “WordPress itself would refuse this automatic update”.
4. Do not follow a symlink outside the allowed roots. If the item root is a
   symlink, resolve it and record `symlinked`; apply the Composer provenance
   rule (6.2) to the resolved path.
5. Cache by item root + context, not by `WP_PLUGIN_DIR` alone; a site-wide
   result may be shared only when the marker is above every item root.

**Settled decision (2026-10-28).** A site-wide checkout locks every item and is
labeled “site-wide”; a parent checkout locks every item under it; an item-root
checkout locks that item. The owner delegated the “what counts as managed”
choice and the recommendation was adopted. See §9.

### 6.2 Composer provenance

**Current algorithm.** `composer_state()` checks the item root for
`composer.json` and `vendor/composer/installed.json`. It rejects a symlinked
marker and a marker that resolves outside the item root. It returns `yes` on
the pair, `no` on a partial pair or a missing root, `unknown` on an unreadable
root or a marker escape.

**What it proves.** It proves that the item ships Composer metadata and a
vendor directory. It does **not** prove that the project’s Composer installed
the item. A distributed plugin that bundles its dependencies has exactly this
shape. Conversely, an item installed by Composer into `wp-content/plugins` or
a Bedrock path usually has no `vendor/composer/installed.json` inside its own
root, so the current detector misses it.

**Authoritative evidence.**

| Evidence | What it proves | Lock? |
|---|---|---|
| Item root `.git/.svn/.hg/.bzr` | The item is a checkout. | Yes, manager label. |
| Parent `.git` (plugins dir, theme root) | Every item under it is in a checkout. | Yes, scope `parent`. |
| `ABSPATH/.git` | The site is a checkout. | Yes; manual follows the manual toggle. |
| Item root `composer.json` | The package has Composer metadata. | No. |
| Item root `vendor/composer/installed.json` | The item bundles dependencies. | No. |
| Project `vendor/composer/installed.json` entry with `type` `wordpress-plugin`/`wordpress-theme` and `install-path` resolving to the item root | Composer installed this item at this path. | Yes. |
| Project `vendor/composer/installed.php` with the same `install_path` | Composer 2 runtime metadata; same proof. | Yes. |
| Project `composer.lock` entry only | The project declared the package; it may not be installed. | No. |
| `composer/installers` present | The project can install WordPress packages. | No, not per item. |
| wp-packagist package name (`wpackagist-plugin/*`, `wpackagist-theme/*`) + path match | Composer provenance; label “WP Packagist”. | Yes. |
| Bedrock project (`roots/wordpress`, `roots/bedrock`, `installer-paths`) + path match | Composer provenance; label “Bedrock”. | Yes. |
| Custom installer with a matching `install-path` | Composer provenance; label “Composer”. | Yes. |
| Symlinked item root + matching project install path | Composer provenance. | Yes. |
| Symlinked item root, no matching path | External management is likely, but not proven. | Fail closed (Q9). |
| Unreadable item root | Cannot inspect. | Fail closed (Q9). |
| Missing `WP_Automatic_Updater` | The native check is unavailable. | Unverified; fail closed unless there is no evidence (Q9). |

**Recommended algorithm.**

1. Resolve the item root (plugin dir or theme dir) with `realpath`.
2. Find the project Composer root: walk up from `ABSPATH` (and from
   `WP_CONTENT_DIR`/`WP_PLUGIN_DIR` if they are outside `ABSPATH`) until a
   `composer.json` with a `config.vendor-dir` or a `vendor/composer` directory
   is found. Bound the walk by `open_basedir` and the filesystem root; do not
   walk into an item’s own `vendor/` unless that item is the project root.
3. Read `vendor/composer/installed.json` (Composer 2 `packages` list or
   Composer 1 flat list). If it is absent or unreadable, read
   `vendor/composer/installed.php` (`versions` array).
4. For each package, accept `type` `wordpress-plugin` or `wordpress-theme`
   (and, if the owner wants, `wordpress-muplugin`/`wordpress-dropin`), resolve
   `install-path` relative to `vendor/composer`, `realpath` it, and compare it
   to the item root. A match is provenance; record package name, version, and
   type.
5. Use `composer.lock` only to corroborate the package name/version; never as
   the sole proof. A root-level `composer.json` plus a bundled `vendor/` is
   “bundled”, not “installed”.
6. If the project has a Composer root but the installed file is unreadable or
   malformed, return `unknown` for items whose path might be managed and apply
   the Q9 fail-closed rule; do not guess `no`.
7. If there is no project Composer root, return `no` (unless the item root is
   a symlink or a checkout, which is handled by 6.1).
8. Cache the parsed project metadata once per request; cache the per-item
   result by item root.

**Settled decision (2026-10-28).** Adopt provenance, not the item-root pair.
The current tests that assert the pair must be replaced with a project fixture
and a “bundled vendor stays updatable” fixture. See §9.

### 6.3 Manager label

The current detector has no manager label. The policy message is generic. To
implement the owner’s notice, the detection result must carry:

- `manager`: `Git`, `SVN`, `Mercurial`, `Bazaar`, `Composer`, `WP Packagist`,
  `Bedrock`, or `external`.
- `scope`: `item`, `parent`, `site`, `composer`, `unknown`.
- `reason`: a short path or package name, for the tooltip/log.
- `evidence`: the marker/package that proved it.

A single item can have multiple sources (for example, a Git checkout that is
also a Composer package). Show one primary label (the nearest/strongest) and
mention the other in the reason: “Git checkout; also installed by Composer
(wpackagist-plugin/foo).” Do not show two competing notices.

---

## 7. Exhaustive case matrix

Legend: “Auto” = native auto-update option. “Blocking?” = does the current
code block the update? “Decision” references the grilling questions. Every
decision is settled in §9; the column is kept as a trace, not as an open
question. The manual hard-block rows (VL-02–VL-06, VL-39, VL-40) apply only
when the optional `version_lock_manual_updates` toggle is on; when it is off,
manual updates are allowed by the owner’s 2026-10-28 decision.

| ID | Item type | Detection evidence | Auto | Surface | Expected behavior | Current behavior | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|
| VL-01 | Plugin | Item-root `.git` | On | Native automatic | Locked; one notice; no update | `native=no`, `composer=no`; automatic update proceeds (unless delay/compat blocks first) | No | Q3 |
| VL-02 | Plugin | Item-root `.git` | Off | Native single “update now” | Link disabled; direct attempt hard-blocked | Link active; manual update proceeds | No | Q1, Q8 |
| VL-03 | Plugin | Item-root `.git` | Off | Native bulk | Locked row skipped; others update | All selected items update | No | Q1, Q8 |
| VL-04 | Plugin | Item-root `.git` | Off | `update-core.php` | Row disabled; checkbox removed; notice | Row selectable; update proceeds | No | Q1, Q8 |
| VL-05 | Plugin | Item-root `.git` | Off | Direct `update.php` URL | Hard block with notice | Update proceeds | No | Q1, Q8 |
| VL-06 | Plugin | Item-root `.git` | On | WP-CLI | Block or documented escape hatch | Update proceeds | No | Q1 |
| VL-07 | Plugin | `WP_PLUGIN_DIR/.git` | On | Native automatic | All plugins locked; scope `parent` | `native=yes` for all; adapter blocks when Safe Mode on; core blocks automatic regardless | Partial | Q3 |
| VL-08 | Theme | Theme-root `.git` | On | Native automatic | All themes locked; scope `parent` | `native=yes` for all; core blocks automatic | Yes | Q3 |
| VL-09 | Core | `ABSPATH/.git` | On | Native automatic | Core locked | `native=yes`; core blocks automatic | Yes | Q3 |
| VL-10 | Plugin/theme/core | Site-wide `ABSPATH/.git` | On | All | All locked; scope `site` | `native=yes`; automatic blocked; manual not blocked | Partial | Q3, Q1 |
| VL-11 | Plugin | Item-root `.svn` | On | Native automatic | Locked, label SVN | `native=no`; update proceeds | No | Q3 |
| VL-12 | Plugin | Item-root `.hg`/`.bzr` | On | Native automatic | Locked, label Mercurial/Bazaar | `native=no`; update proceeds | No | Q3 |
| VL-13 | Plugin | Project `installed.json` package + path match | On | Native automatic | Locked, label Composer | `composer=no` (no item-root pair); update proceeds | No | Q4 |
| VL-14 | Plugin | wp-packagist package + path match | On | Native automatic | Locked, label WP Packagist | `composer=no`; update proceeds | No | Q4, Q5 |
| VL-15 | Theme | Bedrock project + path match | On | Native automatic | Locked, label Bedrock | `composer=no`; update proceeds | No | Q4, Q5 |
| VL-16 | Plugin | Bundled `composer.json` + `vendor/composer/installed.json`, no project entry | On | Native automatic | Update normally; “bundled” is informational | `composer=yes`; automatic update denied | Yes (wrong) | Q4, Q5 |
| VL-17 | Plugin | Only `composer.json` | On | Native automatic | No lock | `composer=no`; update proceeds | Yes (accidental) | Q4 |
| VL-18 | Plugin | Project `installed.php` only, path match | On | Native automatic | Locked | `composer=no`; update proceeds | No | Q4 |
| VL-19 | Plugin | Symlinked item root to Composer path | On | Native automatic | Locked if path matches; otherwise `unknown` | `realpath` follows; item-root pair may or may not exist; no provenance check | No/unknown | Q3, Q4, Q9 |
| VL-20 | Plugin | Unreadable root / `open_basedir` | On | Native automatic | Fail closed or visibly unverified | `unknown` → allowed; or `no` from `is_allowed_dir` → allowed | No | Q9 |
| VL-21 | Plugin | Missing `WP_Automatic_Updater` | On | Native automatic | Unverified; decision | `unknown` → allowed | No | Q9 |
| VL-22 | Plugin | Version-controlled + incompatible | On | Native row / log | One primary reason; manager named; one row | Compatibility wins first; version lock not surfaced | Yes (compat) | Q13 |
| VL-23 | Plugin | Version-controlled + delayed | On | Native row / log | One primary reason; manager named; one row | Free delay at priority 21 wins; log says delayed | Yes (delay) | Q13 |
| VL-24 | Plugin | Version-controlled | On | Native toggle | Lock label; not activatable | Toggle active; option can be enabled | No | Q7 |
| VL-25 | Plugin | Version-controlled | On | Native AJAX toggle | Write rejected/stripped | `update_site_option` writes the item | No | Q7, Q12 |
| VL-26 | Plugin | Version-controlled | Off | Updatronix Auto updates | Disabled toggle + notice | Toggle enabled; no notice | No | Q6, Q7 |
| VL-27 | Plugin | Version-controlled | — | Shield card | Per-item manager/reason | Name only; no reason | Partial | Q5, Q6 |
| VL-28 | Plugin | Version-controlled | On | Queue plan | Excluded at plan, or one blocked row per target | Enqueued then blocked at enqueue; one row per target; queue dormant | Partial | Q10 |
| VL-29 | Plugin | Version-controlled | On | Queue off / native automatic | Adapter blocks | Blocks only when Safe Mode on and detector says yes | Partial | Q2, Q5 |
| VL-30 | Plugin | Version-controlled predecessor | On | Flow | Downstream waits; one notification | Step blocked; downstream canceled; run failed | No | Q10 |
| VL-31 | Plugin | Version-controlled, no offer | On | Flow | Waiting for manager/offer | `skipped`; chain continues | No | Q10 |
| VL-32 | Plugin | Cache stale after `git pull`/Composer install | On | Long-running worker | Re-detect after recheck | Request cache may be stale until process restart | Partial | Q11 |
| VL-33 | Plugin | Item deleted | On | Queue/state | Lock removed cleanly | `deleted_plugin` clears the cache; a queue row may remain and fail | Partial | Q11 |
| VL-34 | Plugin | Version-controlled, Multisite network admin | On | Network native screens | Locked on network screens | Engine main-site only; adapter per site; no Version Lock UI | Ambiguous | Q12 |
| VL-35 | Plugin | Version-controlled, subsite admin | On | Subsite screens | No update action or a clear lock | Native capability restrictions; no Version Lock UI | Partial | Q12 |
| VL-36 | Plugin | Composer-installed, lock off | On | Native automatic | Allowed (native VCS may still block) | Allowed by version policy; native VCS still denies if present | Partial | Q2 |
| VL-37 | Plugin | Native VCS + Composer | On | Native automatic | Native reason wins | Native checked first; native wins | Yes | Q5, Q13 |
| VL-38 | Plugin | Version-controlled, repeated cron | On | Log | One row per target, not per tick | Event key dedupes; one row per target/attempt | Yes | Q11, Q13 |
| VL-39 | Plugin | Version-controlled + updatable sibling | Off | Bulk | Locked item skipped; sibling updates | Both update | No | Q1, Q8 |
| VL-40 | Theme | Version-controlled | Off | `update-core.php` theme table | Row notice + disabled checkbox | No lock; row selectable | No | Q1, Q6 |

---

## 8. Blocking cases and settled handling

The owner answered the blocking questions on 2026-10-28. “Blocking” here means
“would have blocked planning”; the handling is now settled.

| ID | Case | Settled handling | Decision |
|---|---|---|---|
| B1 | Manual native update (single, bulk, update-core, direct URL) | Add an optional `version_lock_manual_updates` toggle, default **off**. When off, only automatic updates are locked; when on, hard-block on `upgrader_pre_install`, remove the UI action, and write one log row. | Q1/Q8 owner override |
| B2 | WP-CLI | Documented escape hatch (constant or filter). It is not controlled by the optional manual toggle. | Q1 recommendation |
| B3 | Composer provenance | Project `installed.json`/`installed.php`, package type + install path; `composer.lock` corroborates; bundled `vendor/` is not proof. | Q4 accepted |
| B4 | Item-root vs parent vs site-wide VCS | Item-root first, ancestors to `ABSPATH`; parent and site-wide scopes labeled and locked. | Q3 delegated; recommendation adopted |
| B5 | Unknown/unreadable | Fail closed for positive evidence; fail open only when there is no evidence, with a visible warning and a log row. | Q9 accepted |
| B6 | Toggle storage | Replace the toggle with a lock label, leave the option unchanged, block writes. | Q7 accepted |
| B7 | Reason precedence | Version Lock first, then compatibility, then delay; one row; secondary reason in details. | Q13 accepted |
| B8 | Flow downstream | `waiting_for_manager`, not failed/canceled; downstream waits; one notification. | Q10 accepted |
| B9 | Queue plan-time exclusion | Exclude at plan time; one informational/blocked row per target, not per tick. | Q10 accepted |
| B10 | Manager label and notice | One primary label + reason; two notice variants (full lock vs automatic-only lock). | Q5/Q6 accepted; variant is a consequence of Q1 |
| B11 | Multisite scope | Network-wide decision, network admin, main-site engine, Super Admin. | Q12 accepted |
| B12 | Theme single-site UI | `wp_prepare_themes_for_js` + `theme_auto_update_setting_template` + scoped JS. | Q6/Q7 accepted |
| B13 | Master switch / Safe Mode | Version Lock is subordinate to Update Shield. Both the native adapter and the engine/Flow policy are gated by `safe_mode`; the UI says “Requires Update Shield.” No decoupling. | Q2 owner conflict; owner wins |
| B14 | Email | No separate email; optional one row in the existing digest. | Q14 accepted |
| B15 | Auto-update toggle | Always replace with a lock label when Version Lock is on, independent of the manual toggle. | Q7 accepted |
| B16 | Engine/Flow gating | Add the `safe_mode` gate to the engine/Flow version policy; the current code is not gated. | Q2 owner conflict; owner wins |

---

## 9. Decisions settled by grilling

The owner answered on 2026-10-28: **“Apply all recommendations.”** Three items
were called out and settled with overrides. The exact words are quoted; the
conflicts are recorded rather than silently corrected.

| Q | Settled decision | Owner input |
|---|---|---|
| Q1 · Scope | Automatic everywhere; native admin manual is an **optional** hard block (`version_lock_manual_updates`, default **off**); WP-CLI remains a documented escape hatch. | “Manual scope : Hard block should be implemented as additionnal optional toggle. If it's not selected, only automatic.” |
| Q2 · Master switch | One master switch (`version_control_lock`, relabeled “Version Lock”) inside Update Shield. **Version Lock does not work when Update Shield is off**; do not decouple. | “Master switch : No it not work when update shield is off.” |
| Q3 · What counts as managed | Item root first, then ancestors to `ABSPATH`; parent and site-wide checkouts labeled and locked; symlinks resolved; unreadable follows Q9. | “Counts as managed : Pick the best choice for count as managed, in dont know.” (delegated; recommendation adopted) |
| Q4 · Composer provenance | Project `installed.json`/`installed.php`, type + install path; `composer.lock` corroborates; bundled `vendor/` is not proof. | “Apply all recommendations.” |
| Q5 · Manager label | One primary label + reason; multiple sources show the primary and “also …”; unknown is “an external manager” and logged. | “Apply all recommendations.” |
| Q6 · Notice copy and placement | Row-level notices on native plugin row, native theme card/row, update-core, Updatronix row, Shield card; no global banner. Two variants: full lock and automatic-only lock. | “Apply all recommendations.” |
| Q7 · Toggle behavior | Replace the toggle, leave the stored option unchanged, block execution and writes, no restore ledger; the auto-update toggle is always locked when Version Lock is on. | “Apply all recommendations.” |
| Q8 · Manual attempt | Hard block + notice + one log row **only when the optional manual toggle is on**; otherwise manual updates are allowed. | “Apply all recommendations.” + Q1 override |
| Q9 · Unknown / fail-open | Fail closed for positive evidence/unreadable; fail open only when there is no evidence, with a visible warning. | “Apply all recommendations.” |
| Q10 · Flow and queue | Exclude at plan time; locked predecessor = `waiting_for_manager`; downstream waits; one notification. | “Apply all recommendations.” |
| Q11 · Cache | Request-scoped + current hooks + settings save/recheck; persistent only with a versioned key/TTL. | “Apply all recommendations.” |
| Q12 · Multisite | Network-wide decision, network admin, main-site engine, Super Admin. | “Apply all recommendations.” |
| Q13 · Reason precedence | Version Lock first, then compatibility, then delay; one row. | “Apply all recommendations.” |
| Q14 · Email | No separate email; optional digest row. | “Apply all recommendations.” |
| Q15 · Tests | Local fixtures only, no network; unit + integration + JS + E2E. | “Apply all recommendations.” |

### Conflicts recorded

1. **Q2 — Safe Mode decoupling.** The recommendation was to decouple Version
   Lock from `safe_mode`. The owner rejected it: “Master switch : No it not
   work when update shield is off.” Resolution: owner wins. Both the native
   adapter and the engine/Flow policy are gated by `safe_mode`; the UI must
   say “Requires Update Shield.”
2. **Q1/Q8 — Manual hard block default.** The recommendation was a default
   hard block for native admin manual updates, with WP-CLI as the escape
   hatch. The owner made the hard block an optional toggle, default off.
   Resolution: owner wins. The automatic lock and the toggle lock remain
   unconditional; the manual block is opt-in.
3. **Original expectation #2.** “The item must not be updatable normally from
   the native update notification” is narrowed by the manual toggle: when the
   toggle is off, the native manual update remains available and the notice
   says “Automatic updates for this item are managed via …; manual updates are
   allowed.” The auto-update toggle stays locked either way.

**Scope of the Q2 gate.** Turning Update Shield off disables Updatronix’s
extra Version Lock (item-root/ancestor detection, Composer provenance, native
UI lock, engine/Flow policy). It does not disable WordPress’s own automatic
VCS refusal in the contexts WordPress checks; that behavior belongs to core.

### What this changes in the current code

- The native adapter already honors `safe_mode`; that part matches the owner.
- The engine/Flow version policy does **not** honor `safe_mode`; it must be
  gated. This is a behavior change required by Q2.
- There is no manual toggle and no `upgrader_pre_install` guard; both must be
  added for the optional full-lock state.
- There is no version-lock notice, toggle lock, or manager label; add them for
  both the automatic-only and full-lock states.

---

## 10. Open questions

**No owner decision remains open.** The three items the owner called unresolved
are settled in §9. The following are implementation assumptions the plan must
state; they are not reopened owner decisions.

### Residual implementation assumptions

1. **“Update Shield off” = `safe_mode` false.** The owner named the Shield
   switch, not an inactive license. The license gate already disables the
   engine and Pro REST. `OPEN — assumption`, not owner decision.
2. **Manual toggle key and default.** Proposed `version_lock_manual_updates`,
   default `false`, stored in the network Shield settings. `OPEN —
   assumption`.
3. **Manual toggle scope.** It covers native admin single, bulk, update-core,
   and direct `update.php` URLs. WP-CLI remains a documented escape hatch.
   Third-party REST routes that call the upgrader are covered by the same
   guard when the toggle is on. `OPEN — assumption`.
4. **Two notice variants.** Full lock uses the owner’s string; automatic-only
   lock uses “Automatic updates for this item are managed via %s. Manual
   updates are allowed.” `OPEN — assumption`.
5. **Auto-update toggle.** Always locked when Version Lock is on, regardless
   of the manual toggle. `OPEN — assumption`.
6. **Site-wide checkout.** Locks every item; with the manual toggle off,
   automatic is locked and manual is allowed. `OPEN — assumption`.
7. **Engine/Flow gate.** The `safe_mode` gate is applied to the version policy
   in both chains. `OPEN — assumption` (required by Q2).
8. **No separate email.** Version Lock blocks do not send their own email;
   they may join the existing digest. `OPEN — assumption` (accepted
   recommendation).
9. **One master switch.** The current Composer lock is folded into the
   Version Lock master; there is no second Composer switch. The stored
   `version_control_lock` value is kept. `OPEN — assumption`.

---

## 11. Contracts with other features

### What Version Lock provides

| Consumer | Contract |
|---|---|
| Queue | A per-item decision (`allowed`, `native_vcs`, `version_controlled`, `unknown`) with `manager`, `scope`, `reason`, `evidence`. A locked **automatic** item is never executed. Manual execution follows `version_lock_manual_updates`. The queue may exclude a locked item at plan time; if it enqueues it, the row is `blocked` with `action_type=prevented`, not `failed`. |
| Flow | A plan-time predicate and a `locked`/`waiting_for_manager` state. A locked predecessor does not make the run `failed`; downstream steps wait. The run summary names the manager. |
| Schedule | A `locked` state that suppresses “next automatic update” claims; the native countdown must not promise an update for a locked item. |
| Logger | One row per item/target/attempt with `action_type=prevented`, `status=warning`, `performed_as=automatic|manual`, `update_context=single|bulk`, and a manager label. A manual block is logged only in the full-lock state. No duplicate row when Safe Mode and Version Lock both apply. |
| Shield | The `version_control_state` payload includes per-item `manager`, `scope`, `reason`, `evidence`; the card can render a truthful list. Version Lock only operates when Update Shield is on. |
| Free | The auto-update data filter can mark an item `version_control_locked` with a reason; the REST toggle and the native AJAX write are guarded. |

### What Version Lock needs

| Provider | Need |
|---|---|
| Queue | A plan-time hook to exclude locked items; an execution context (`Updatronix_Update_Executor::is_executing()`) so the manual guard allows the engine’s own write. |
| Flow | A plan-time hook and a `waiting_for_manager` state instead of `canceled` for a locked predecessor. |
| Schedule | A display hook for “managed externally”; do not compute a next-eligible time for a locked item. |
| Logger | Event-key dedupe and a reason/source field; do not write a second row for the same block. |
| Free | `pre_update_site_option_auto_update_plugins|themes` guards; the existing `updatronix_auto_update_*_data` filters; the existing REST toggle routes. |
| Shield | The settings/state surface; reason precedence with Safe Mode and delay. |

### Overlap rules

- **Safe Mode compatibility vs Version Lock.** One item, one primary reason.
  Settled: Version Lock first, then compatibility, then delay. The engine chain
  must be reordered and the native adapter needs a Version Lock filter before
  Free’s delay priority 21 (or an equivalent first-denial path).
- **Free delay vs Version Lock.** Free’s delay filter runs at priority 21
  (`class-updatronix-autoupdatedelay.php:42`); the current engine chain also
  puts delay before version control. A delayed, version-managed item is
  reported as delayed today. After the decision it is reported as
  version-controlled, with the delay in the details.
- **Update Shield gate.** Version Lock only operates when `safe_mode` is on.
  The native adapter already returns early; the engine/Flow policy must be
  gated too. The UI says “Requires Update Shield.”
- **Shield ledger.** The ledger is written only by compatibility `disable`
  (`class-updatronix-shield-enforcement.php:436-465`). Version Lock must not
  write it; a version-locked item that was disabled for incompatibility stays
  in the ledger and the re-enable evaluator denies it with `version_controlled`
  (`:354-375`). That is correct, but the UI should say why.
- **Queue ownership.** The ownership handshake denies engine-owned items at
  `PHP_INT_MAX`; the adapter passes them through to avoid a second policy
  evaluation. A manual guard must exempt the executor, not the handshake.

---

## 12. Implementation constraints

1. **Surgical native override.** Do not replace `plugins.php`, `themes.php`,
   `update-core.php`, or their tables. Use:
   - `in_plugin_update_message-{$file}` (`wp-admin/includes/update.php:614`)
     for the plugin row notice.
   - `in_theme_update_message-{$theme}` (`update.php:837`) for the network
     theme row notice.
   - `plugin_auto_update_setting_html`
     (`class-wp-plugins-list-table.php:1457`) for the plugin toggle.
   - `theme_auto_update_setting_html`
     (`class-wp-ms-themes-list-table.php:887`) for the network theme toggle.
   - `wp_prepare_themes_for_js` (`wp-admin/includes/theme.php:812`) and
     `theme_auto_update_setting_template` (`themes.php:821-827`) for
     single-site theme cards.
   - A scoped JS augmentation for the single-site theme card update link and
     the update-core table rows/checkboxes.
2. **One script, scoped.** A `version-lock-augment.js` loaded only on
   `plugins.php`, `themes.php`, `plugins-network`, `themes-network`,
   `update-core.php`, and `update-core-network`, behind the same capability
   checks as the screens. It must not touch unrelated rows. Keep it separate
   from `safe-mode-augment.js`; share a small helper only if it stays
   stateless. It must render the automatic-only state (manual link stays) and
   the full-lock state (manual link removed) from the same data.
3. **Server-side enforcement is conditional.** The JS is UX; the guarantee is
   `upgrader_pre_install` (and optionally `upgrader_pre_download`) returning a
   `WP_Error`. The guard runs only when `version_lock_manual_updates` is on.
   It must allow `Updatronix_Update_Executor::is_executing()` and must have a
   documented WP-CLI escape hatch (constant or filter).
4. **Update Shield gate.** Version Lock is subordinate to Update Shield. The
   native adapter already returns early when `safe_mode` is false; the
   engine/Flow version policy must also be gated. The UI must say “Requires
   Update Shield” and disable the Version Lock switch when Update Shield is
   off. This is the owner’s Q2 decision.
5. **No destructive option writes.** Leave `auto_update_plugins` and
   `auto_update_themes` unchanged. Guard writes with
   `pre_update_site_option_auto_update_plugins|themes`
   (`wp-includes/option.php:2418`) and strip locked items from the incoming
   value. Do not add a restore ledger.
6. **No page replacement, no global banner.** Row-level notices only. Use two
   truthful variants: the owner’s “managed via …; please update through your
   manager” for the full lock, and “Automatic updates for this item are
   managed via …; manual updates are allowed” for the automatic-only lock.
   The Shield card may summarize; it must not repeat the same warning as a
   global banner on update-core.
7. **i18n.** New strings need translator comments; the text domain is
   `updatronix-pro` for Pro strings. Do not reword existing strings inside
   `__()`, `_e()`, `_n()`, `_x()`, `esc_html__()`, or `esc_attr__()` without
   owner approval. The manager name is a proper noun; the surrounding sentence
   is translated.
8. **Multisite.** Use `get_site_option()` for the decision, `is_main_site()`
   for the engine, and Super Admin checks for Pro REST. Do not schedule the
   queue on subsites. Test network admin and subsite admin.
9. **Performance.** Cache per request; parse the project Composer metadata
   once; bound the ancestor walk; respect `open_basedir`; do not perform
   network calls. The Shield state already caps at 200 items
   (`class-updatronix-shield-state.php:27`); keep a bound and show truncation.
10. **Cache.** Clear on `upgrader_process_complete`, activation, theme switch,
    deletion, settings save, and manual recheck. If a persistent cache is
    added, use a versioned key and a short TTL; a manual `git pull` cannot be
    observed.
11. **Security.** Resolve paths with `realpath`, contain them inside the
    allowed roots, do not follow a symlink outside the item/project root
    without recording it, sanitize every label, and capability-check every
    REST/AJAX route.
12. **Backward compatibility.** Keep the `version_control_lock` option key and
    the `updatronix_pro_is_version_controlled` filter. The key becomes the
    master switch; migrate the label/description, not the stored value. Add
    `version_lock_manual_updates` with default `false`.
13. **No version bump, no task file, no code in this analysis.**

---

## 13. Test strategy

### Unit

- Detector: item-root `.git`, `.svn`, `.hg`, `.bzr`; plugins-dir `.git`;
  site-wide `.git`; theme-root `.git`; core `ABSPATH/.git`; item-root
  Composer pair only (must be `no`); project `installed.json` match; project
  `installed.php` match; wp-packagist package name; Bedrock project;
  `composer.lock` only; custom installer path; symlinked item root; symlinked
  marker; unreadable root; missing `WP_Automatic_Updater`; single-file plugin;
  multiple sources.
- Policy: native first; Composer lock off; unknown behavior; manager/scope/
  reason metadata; first denial wins; no duplicate result.
- Update Shield gate: `safe_mode` off returns allow from the version policy in
  both the engine and adapter chains; `safe_mode` on with Version Lock on
  evaluates it.
- Manual toggle: `version_lock_manual_updates` off allows native admin manual
  updates; on blocks them; WP-CLI escape hatch still works; executor still
  allowed.
- Manual guard: `upgrader_pre_install` returns `WP_Error` for locked, allows
  unlocked, allows executor, allows the escape hatch; bulk continues.
- Option guard: `pre_update_site_option_auto_update_plugins|themes` strips a
  locked item and leaves the old value intact.
- Labels/notice: primary label, multiple sources, unknown, the automatic-only
  variant and the full-lock variant, translator comments.

### Integration

- Real WordPress with fixture plugins/themes: `auto_update_plugin` filter;
  `wp_plugin_update_row` output contains the notice and no active
  `.update-link` when the manual toggle is on; `plugin_auto_update_setting_html`
  returns the lock label; `theme_auto_update_setting_template` renders the
  locked branch; `upgrader_pre_install` blocks a real
  `Plugin_Upgrader::upgrade()` without writing files when the manual toggle is
  on.
- States: Update Shield off means no Version Lock in the native adapter or the
  engine/Flow chain; Update Shield on + manual toggle off yields the
  automatic-only notice and an active manual link; Update Shield on + manual
  toggle on yields the full-lock notice and a blocked manual path.
- Queue: kernel enqueue creates one `blocked` row; duplicate enqueue does not
  create a second row; queue off uses the adapter; ownership handshake yields
  one row.
- Flow: locked predecessor yields `waiting_for_manager`, not `failed`;
  downstream waits; one notification.
- Multisite: network admin plugin/theme/update-core screens; subsite
  `themes.php`; Super Admin vs site admin; main-site engine.

### JavaScript

- DOM fixtures for `plugins.php` row, `themes.php` card, network theme row,
  `update-core.php` plugin table, `update-core.php` theme table.
- Exact identity matching (`data-plugin`, stylesheet), no `nth-child`; no
  injection into a sibling or an unrelated row.
- Toggle replacement, update-link removal (full lock), update-link retention
  (automatic-only lock), checkbox removal (full lock), no-op when the row is
  absent.
- Two notice variants; the manager label is shared.
- Duplicate-template guard; idempotent run; i18n strings.
- Accessibility: `aria-disabled`, `aria-describedby`, focus order.

### E2E

- One local fixture plugin with an item-root `.git` marker and a pending local
  update: native row notice, locked toggle, automatic-only state (manual link
  active), then full-lock state (manual link removed, direct URL error),
  update-core row, Updatronix screen, Shield card.
- Update Shield off: no Version Lock row notice or toggle lock; native
  WordPress behavior only.
- One local fixture plugin with a bundled `vendor/` and no project entry:
  updates normally; no false positive.
- One local fixture plugin with a project `installed.json` entry: locked.
- Multisite network admin and subsite theme card.
- No network package downloads; local zips only.

### Fixtures

- Local only. Create marker directories, `composer.json`, and
  `vendor/composer/installed.json`/`installed.php` under the test plugin/theme
  roots; do not run Composer or fetch packages.
- `chmod( 000 )` for the unreadable case; restore in `tearDown`; skip on
  platforms where the mode is not enforced.
- `symlink()` for the symlink case; skip when unavailable.
- Multisite via `WP_MULTISITE=1 bin/harness integration`.
- No `assertTrue( true )`, no skipped test without a reason, no assertion in
  a `try/catch` (the anti-pattern gate).

---

## 14. Evidence

### Owner decision input

On 2026-10-28 the owner answered: **“Apply all recommendations.”** The three
unresolved items were answered as quoted in §9: the manual hard block is an
additional optional toggle (default off), the “what counts as managed” choice
was delegated and adopted as recommended, and Version Lock does not work when
Update Shield is off. The conflicts with the recommendations are recorded in
§9; none was silently corrected.

### Commands run

```bash
bin/harness graft ask "version control detector" --source
bin/harness graft ask "shield ownership handshake" --source
bin/harness graft grep "version_controlled"
bin/harness graft callers Updatronix_Version_Control_Policy
bin/harness graft ask "engine enqueue policy evaluation" --source
bin/harness graft ask "flow step evaluation" --source
```

Read-only WordPress probes through `bin/harness wp eval`:

```text
ABSPATH=/var/www/html/
WP_PLUGIN_DIR=/var/www/html/wp-content/plugins
plugins_git=no
item_git=yes
abspath_git=no
vcs_plugins_dir=no
vcs_item_root=yes

updatronix/updatronix.php native=no composer=yes source=composer
updatronix-pro/updatronix-pro.php native=no composer=yes source=composer

plugin_toggle=ORIGINAL
theme_toggle=ORIGINAL

upgrader_pre_install:
  5 Updatronix_Update_Logger::store_core_version_before
upgrader_pre_download:
  5 Updatronix_Update_Logger::init_core_feedback_on_download
  10 Updatronix_Update_Logger::start_bulk_post_flush_buffer
  10 Updatronix_ErrorHandler::capture_download_error

safe_mode=on
version_control_lock=on
queue_enabled=false
queue_live=false
license=inactive
auto_update_plugin=false
```

The last probe returned `false` for Updatronix because the detector reports it
as Composer-managed; Free’s delay gate is also active and can win earlier.

### Files read

Pro:

- `inc/update-engine/class-updatronix-version-control-detector.php`
- `inc/update-engine/class-updatronix-version-control-policy.php`
- `inc/update-engine/class-updatronix-shield-state.php`
- `inc/update-engine/class-updatronix-shield-enforcement.php`
- `inc/update-engine/class-updatronix-policy-chain.php`
- `inc/update-engine/class-updatronix-update-kernel.php`
- `inc/update-engine/class-updatronix-update-engine.php`
- `inc/update-engine/class-updatronix-queue-scheduler.php`
- `inc/update-engine/class-updatronix-shield-ownership.php`
- `inc/update-engine/class-updatronix-update-journal.php`
- `inc/update-engine/class-updatronix-update-job.php`
- `inc/update-engine/class-updatronix-flow-planner.php`
- `inc/update-engine/class-updatronix-shield-ledger.php`
- `inc/update-shield/class-updatronix-safe-mode.php`
- `inc/update-shield/class-updatronix-update-shield.php`
- `inc/settings/options.php`
- `assets/src/js/components/UpdateShieldPage.js`
- `assets/src/js/shieldState.js`
- `assets/src/js/safe-mode-augment.js`
- `tests/Unit/UpdateShieldPoliciesTest.php`
- `tests/Unit/UpdateCounterfactualGapsTest.php`
- `tests/Integration/UpdateShield43Test.php`
- `tests/Integration/ShieldStateTest.php`

Free:

- `inc/classes/class-updatronix-autoupdates.php`
- `inc/classes/class-updatronix-settings.php`
- `inc/classes/class-updatronix-logger.php`
- `inc/classes/class-updatronix-autoupdatedelay.php`
- `assets/src/js/components/autoUpdates/ItemsDataViewSection.js`
- `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js`

WordPress core:

- `wp-admin/includes/class-wp-automatic-updater.php`
- `wp-admin/includes/class-wp-upgrader.php`
- `wp-admin/includes/class-plugin-upgrader.php`
- `wp-admin/includes/class-theme-upgrader.php`
- `wp-admin/includes/class-wp-plugins-list-table.php`
- `wp-admin/includes/class-wp-ms-themes-list-table.php`
- `wp-admin/includes/update.php`
- `wp-admin/includes/theme.php`
- `wp-admin/includes/ajax-actions.php`
- `wp-admin/themes.php`
- `wp-admin/update-core.php`
- `wp-includes/option.php`

Docs:

- `docs/wpds/45-update-engine-architecture.md` §2.4, §3.1, §4.2, §5.4,
  §7.1, §8.3.
- `docs/wpds/47-update-engine-4.3a-shield-policies.md` §2, §3, §7.
- `docs/wpds/22-update-shield-review.md`.
- `docs/wpds/51-update-shield-phase-5.1.md`.
- `docs/wpds/60-update-engine-rethink/README.md`.

### What was not verified

- A live Multisite pass.
- A real Bedrock/wp-packagist installation.
- A real `open_basedir` or chmod-000 environment.
- A real WP-CLI update against a locked fixture (the hook probe is code-level).
- A real native manual update (the code path is clear; no file was written).
- A browser pass on single-site `themes.php`.
- The existing test suites were not run; the tests that encode the Composer
  false positive were read, not executed.

### Bottom line

Version Lock is currently an automatic-only policy with a Composer heuristic
that is inverted for the owner’s use case. It does not lock the native admin,
it does not name the manager, and it can overwrite a managed checkout through
any manual update path. Under the 2026-10-28 decision, the manual hard block is
opt-in and Version Lock is subordinate to Update Shield; the backend guard and
the toggle do not exist yet. The detector must be rebuilt around item-root VCS
evidence and project-level Composer provenance before the UI work can be
trusted.

### Implementation results (2026-10-28)

- Detector: item/parent/site scan, project Composer provenance, symlink and
  unreadable fail-closed handling, no-evidence advisory; 17 unit fixtures.
- Policy: `decision()` facade, Safe Mode gate, ordered native adapter at 19,
  engine chain reorder, one-row dedupe; 397 unit / 127 integration tests.
- Admin: plugin/theme row notices, toggle locks, single-site theme branch,
  update-core map, Auto updates data, Shield card manager labels, optional
  manual guard, Free REST toggle guard.
- Live E2E (`pro-101`, `pro-106`) passed after the test license was
  reactivated. Multisite and pending-offer manual paths remain self-skipped or
  covered at the integration level; the owner should verify them in Local.
- Not changed: queue, Flow planner/runner, Schedule calculator/UI, Logger
  schema, WP-CLI blocking, version headers.

---

Analysis complete; do not implement. Ready for plan mode after the other tabs
finish.
