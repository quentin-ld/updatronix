# WordPress Update System — Developer Reference

[Français](fr.md) · [Documentation index](../README.md)

> **Status:** verified against WordPress 7.1.4 and the installed core.

**Version:** WordPress core inspected: 7.1.4

**Last updated:** 2026-11-07

**Audience:** WordPress plugin developers, theme developers, and technical writers.

**Scope:** WordPress core update system — discovery, application, safety mechanisms, configuration, and developer hooks.

**Verified against:** WordPress core 7.1.4, PHP 8.4.27 (project baseline PHP 8.1+), from the installed core under `/var/www/html`.

**The seven parts**

| Part | Covers |
|---|---|
| 1 | Discovery: update transients, scheduled and admin-triggered checks, API endpoints, `Update URI` sources, checksums, must-use plugins, and drop-ins. |
| 2 | Application: `WP_Upgrader` and its subclasses, the database upgrade step, skins, plugin dependencies, rollback, locks, maintenance mode, package signatures, and staging cleanup. |
| 3 | Automatic background updates: `WP_Automatic_Updater`, global disable conditions, per-item eligibility, core branch policy, opt-ins, and translation chaining. |
| 4 | Notifications and feedback: admin notices and menu counts, Site Health tests, background emails, the `automatic_updates_complete` payload, and Recovery Mode. |
| 5 | Entry points and configuration: the Updates screen, AJAX, WP-CLI, XML-RPC, REST, filesystem methods, `wp-config.php` constants, multisite, proxies, and downgrades. |
| 6 | Hook and filter reference: discovery, download, install, automatic-update, policy, notification, cache, and filesystem hooks, with parameters and source lines. |
| 7 | File and class reference: the core files involved and what each one does, plus the key classes. |

**Where to start**

- Find out why no update is offered: §1.2 and §1.6.
- Change something during download or install: Part 6, §6.3 and §6.4.
- Stop automatic updates for one plugin or theme: §3.3 and §3.8.
- Send update results somewhere else: §4.7.
- Look up a hook signature: Part 6.
- Find which core file does a job: Part 7.
- Diagnose a failed upgrade: Part 2, then §4.8 for fatal errors.

---

## PART 1 — Discovery: How WordPress Detects Available Updates

### 1.1 The discovery model

Discovery is the phase where WordPress learns what updates exist. Nothing installs until WordPress writes offers to site transients and the admin interface or automatic updater reads them.

#### How it works

WordPress separates discovery from application and feedback. The functions `wp_version_check()`, `wp_update_plugins()`, and `wp_update_themes()` check for updates — they do not install anything. Their results live in site transients. Application relies on the upgrader stack (see §2.1). Feedback uses notices, badges, skins, or email depending on the entry path (see §4.1–§4.2 and Part 5).

#### Process flow

1. An HTTP client contacts WordPress.org or another host for third-party metadata.
2. WordPress decodes the response into offer objects.
3. Core merges offers into transient payloads.
4. Admin screens and cron read the transients to decide what to display or attempt next.
5. If discovery fails, transients remain stale or empty and the site shows no updates until a later check succeeds.

#### Reference

| Concept | Role |
|---------|------|
| Discovery functions | Populate the `update_core`, `update_plugins`, and `update_themes` site transients |
| Application | `WP_Upgrader` subclasses; discovery alone does not invoke them |
| Feedback | Transients drive the in-admin interface; background email sends only after automatic updates (see §4.1) |

#### Developer resources

- [Plugin API: hooks, actions, and filters](https://developer.wordpress.org/plugins/) — how hooks attach to core behavior.

---

### 1.2 Site transients: the update data layer

Site transients hold the latest discovery results. When you alter update behavior, you typically interact with these structures or the filters that wrap them.

#### How it works

Core caches discovery results in site transients, which are shared network-wide on multisite. `wp_version_check()` sets the `update_core` transient. That transient holds the offers array in `updates`, plus `last_checked`, `version_checked`, and optional translation payloads. Checksum maps are not stored here; core fetches them on demand through `get_core_checksums()` (see §1.9).

`wp_update_plugins()` sets the `update_plugins` transient with `response`, `no_update`, `checked` versions, and translation lists. `wp_update_themes()` sets the `update_themes` transient following the same pattern for themes.

Dynamic filters such as `pre_set_site_transient_{$transient}` run before save. This filter is the primary integration point for adjusting or clearing structures before an update runs.

#### Process flow

1. A check function runs and builds or merges a payload.
2. Filters may alter the payload before storage.
3. WordPress stores the payload as a site transient.
4. Readers — including the Updates screen, `wp_get_update_data()`, and `WP_Automatic_Updater` — consume the transient.
5. Stale transients persist until the next successful check or a forced refresh (see §1.6).

#### Reference

| Transient | Set by | Typical contents |
|-----------|--------|------------------|
| `update_core` | `wp_version_check()` | `updates` (offers), `last_checked`, `version_checked`, translations |
| `update_plugins` | `wp_update_plugins()` | `response`, `no_update`, `checked`, translations |
| `update_themes` | `wp_update_themes()` | Same pattern for themes |

#### Developer resources

- [`wp_update_plugins()` reference](https://developer.wordpress.org/reference/functions/wp_update_plugins/) — plugin update check and transient storage.

---

### 1.3 Core branch policy options (`auto_update_core_*`)

Site options control which branches of WordPress core may receive auto-updates when the `WP_AUTO_UPDATE_CORE` constant is not defined in `wp-config.php`. These options work together with filters and the per-offer `auto_update_core` filter (see §3.7).

#### How it works

When `WP_AUTO_UPDATE_CORE` is undefined, core reads site options inside branch policy logic in `Core_Upgrader::should_update_to_version()`. `auto_update_core_dev` and `auto_update_core_minor` default to `enabled`; `auto_update_core_major` defaults to `unset` (`wp-admin/includes/class-core-upgrader.php:288-290`). Values are `enabled`, `disabled`, or unset. These options live in the options table (site options on multisite) — they are not stored inside the update transients. For precedence with `WP_AUTO_UPDATE_CORE` and filters, see §5.10 and §3.7.

#### Process flow

1. Core evaluates the running version branch: development, minor, or major.
2. Core reads the matching `auto_update_core_*` option unless the constant overrides policy.
3. Branch filters may change the boolean result.
4. The result feeds `should_update()` together with the offer and other guards.

#### Reference

| Name | Type | Default | Effect |
|------|------|---------|--------|
| `auto_update_core_dev` | site option | `enabled` | Development or nightly-style builds on the current branch |
| `auto_update_core_minor` | site option | `enabled` | Minor releases within the same x.y branch |
| `auto_update_core_major` | site option | `unset` | Major releases; the WordPress 5.6+ interface maps choices here |

#### Developer resources

- [`WP_AUTO_UPDATE_CORE` in `wp-config.php`](https://developer.wordpress.org/apis/wp-config-php/) — constant that overrides branch options when defined.

---

### 1.4 The `autoupdate` and `disable_autoupdate` offer flags

API responses attach flags to each offer. These flags determine whether an item is a candidate for unattended updates before per-item filters run.

#### How it works

When WordPress.org answers a plugin or theme update-check request, each entry in the response map is an offer object. Plugins and themes with an `Update URI` header can receive offers from `update_plugins_{$hostname}` or `update_themes_{$hostname}` instead; those objects may also include `autoupdate` and `disable_autoupdate`.

An `autoupdate` boolean signals that the build is eligible for unattended auto-update from the directory or third-party provider's perspective. In `WP_Automatic_Updater::should_update()`, for plugins and themes, core first sets the pending decision from `autoupdate`, then merges per-item site option opt-ins (`auto_update_plugins`, `auto_update_themes`) when auto-updates are enabled for the type. If `autoupdate` is empty and the site administrator has not opted the item in, the pending decision stays false.

The `disable_autoupdate` property forces the pending decision to false before `apply_filters( "auto_update_{$type}", … )` runs, but the filter may still return true to re-enable the update.

These flags are not durable site settings. They arrive on each HTTP response or from a filter callback. The transient stores whatever the last check returned. Refresh offers with `wp_update_plugins()` or `wp_update_themes()` before relying on offer objects for policy.

#### Process flow

1. Discovery returns offers, each carrying optional `autoupdate` and `disable_autoupdate` flags.
2. `should_update()` combines the flags with opt-in arrays and filters.
3. A false outcome skips automatic installation for that item unless a filter overrides the decision.

#### Reference

| Flag | Meaning |
|------|---------|
| `autoupdate` | The directory, third-party Update URI provider, or author signals eligibility for automatic update |
| `disable_autoupdate` | Vetoes the item before `auto_update_{$type}` runs; filters may override |

#### Developer resources

- [`WP_Automatic_Updater::should_update()` reference](https://developer.wordpress.org/reference/classes/wp_automatic_updater/should_update/) — eligibility stack for automatic updates.

---

### 1.5 Scheduled checks (WP-Cron)

WordPress schedules recurring update checks so discovery runs without a logged-in user. You need to understand cron behavior when `DISABLE_WP_CRON` or external schedulers replace the default mechanism.

#### How it works

`wp_schedule_update_checks()` runs on `init` and schedules three twicedaily events: `wp_version_check` calls `wp_version_check()`, `wp_update_plugins` calls `wp_update_plugins()`, and `wp_update_themes` calls `wp_update_themes()`.

When `DISABLE_WP_CRON` is true, WordPress does not spawn the cron runner on normal page loads. Scheduled hooks — including `wp_version_check`, `wp_update_plugins`, `wp_update_themes`, and by chaining `wp_maybe_auto_update` — do not run unless something invokes `wp-cron.php` (system cron, monitoring, or WP-CLI). Admin-triggered checks described in §1.6 still run on those requests because they are not cron-based.

#### Process flow

1. The `init` hook schedules events if they are not already scheduled.
2. On each cron run, due events fire.
3. Each handler calls the matching update function.
4. Throttling inside those functions may skip the HTTP request if a recent check is still valid, unless the caller forces a refresh (§1.6).
5. Failure leaves transients unchanged until the next successful run.

#### Reference

| Hook | Type | Parameters | Notes |
|------|------|------------|-------|
| `wp_version_check` | cron event | — | Calls `wp_version_check()` |
| `wp_update_plugins` | cron event | — | Calls `wp_update_plugins()` |
| `wp_update_themes` | cron event | — | Calls `wp_update_themes()` |

#### Developer resources

- [Disabling WP-Cron in `wp-config.php`](https://developer.wordpress.org/apis/wp-config-php/#disable-wordpress-cron) — behavior when cron is disabled.
- [`wp cron event run` CLI command](https://developer.wordpress.org/cli/commands/cron/event/run/) — running due events manually.

---

### 1.6 Admin-triggered checks and throttle bypass

Loading specific admin screens forces fresher checks with shorter throttles than the default long backoffs. If you add a custom "check for updates" control, mirror these patterns.

#### How it works

Hooks on admin screens such as `load-plugins.php`, `load-update.php`, and `load-update-core.php` trigger plugin or theme checks with context-dependent timeouts. The Updates screen uses roughly a one-minute backoff, list tables use roughly one hour, the default admin backoff is roughly 12 hours, and cron checks use roughly two hours (`wp-includes/update.php:382-394`).

`_maybe_update_plugins()` and `_maybe_update_themes()` on `admin_init` apply a 12-hour backoff when `last_checked` is recent. `_maybe_update_core()` uses the same 12-hour window only when the transient's `version_checked` still matches the running WordPress version — if the versions differ, a check runs immediately.

`wp_version_check()` accepts a second parameter `$force_check`. When true, the function skips the timeout against the stored `update_core` transient and issues a fresh HTTP request. `wp_update_plugins()` and `wp_update_themes()` accept `$extra_stats`; when that array is non-empty, the early-return path for "checked recently and nothing changed" is skipped, comparable to a forced check. A non-empty first argument to `wp_version_check()` can also set `$force_check` internally per `wp-includes/update.php`.

> **Note:** Do not force checks on every cron or background request. Reserve forced checks for user-initiated or infrequent jobs to avoid excessive requests to WordPress.org.

#### Process flow

1. An admin screen loads and its load hooks run.
2. Update functions run with short timeouts or with `$force_check` and non-empty extra stats.
3. The HTTP request returns new offers.
4. WordPress refreshes the transients.
5. The interface reflects new update counts.
6. If the user lacks sufficient capabilities, WordPress displays limited messaging.

#### Reference

| Function | File | Returns | Notes |
|----------|------|---------|-------|
| `wp_version_check()` | `wp-includes/update.php` | void | Pass `$force_check` as true to skip transient backoff for core |
| `wp_update_plugins()` | `wp-includes/update.php` | void | Non-empty `$extra_stats` bypasses the "recently checked" path |
| `wp_update_themes()` | `wp-includes/update.php` | void | Non-empty `$extra_stats` bypasses the "recently checked" path |
| `wp_clean_update_cache()` | `wp-includes/update.php` | void | Clears transients; pair with checks when you need a fresh HTTP request |

#### Developer resources

- [`wp_version_check()` reference](https://developer.wordpress.org/reference/functions/wp_version_check/) — core version check API.
- [`wp_clean_update_cache()` reference](https://developer.wordpress.org/reference/functions/wp_clean_update_cache/) — clears update transients.

---

### 1.7 API endpoints

Discovery depends on HTTP endpoints that return JSON offers. SSL availability affects transport and warnings.

#### How it works

Core sends POST requests to WordPress.org version and update-check endpoints. The URLs in source use `http://`; when `wp_http_supports( array( 'ssl' ) )` returns true, WordPress upgrades the scheme to HTTPS.

The core version check sends query-string parameters plus a POST body that includes encoded translations. Plugin and theme checks POST JSON bodies listing installed components and locales. A separate checksums endpoint serves integrity verification only, not update offers (see §1.9). When SSL is unavailable, core may fall back to HTTP with user-visible warnings in some failure paths.

#### Process flow

1. An update function builds the request body.
2. WordPress sends a POST request to the endpoint.
3. WordPress decodes the response.
4. On error, transients may remain stale or WordPress triggers fallback behavior.
5. On success, WordPress updates the transient payloads.

#### Reference

| URL path on `api.wordpress.org` | Method | Purpose |
|---------------------------------|--------|---------|
| `/core/version-check/1.7/` | POST | Core offers; query arguments plus body (such as translations) |
| `/plugins/update-check/1.1/` | POST | Per-plugin offers |
| `/themes/update-check/1.1/` | POST | Per-theme offers |
| `/core/checksums/1.0/` | GET | Path-to-hash map for a version and locale (§1.9) |

#### Developer resources

- [HTTP API overview](https://developer.wordpress.org/apis/http-api/) — outbound requests from WordPress.

---

### 1.8 Third-party update sources (`Update URI`)

Plugins and themes can declare an `Update URI` header so that metadata arrives from a host other than WordPress.org. This extends discovery without replacing the core transient model.

#### How it works

For plugins and themes that declare `Update URI`, core applies dynamic filters `update_plugins_{$hostname}` and `update_themes_{$hostname}`. This mechanism allows commercial or custom directories to supply update metadata. Core calls the filter only when WordPress.org has not already returned an offer for the item, and it requires at least a `version` in the returned array (`wp-includes/update.php:509-565`). Per-entity auto-update interface preferences are stored in site options and described in §5.4 and §3.8.

#### Process flow

1. Discovery runs.
2. Core resolves the hostname from the `Update URI` header.
3. The matching filter may inject or alter offers.
4. WordPress merges results into the standard site transients.
5. Automatic updater logic in Part 3 uses those offers like any other.

#### Reference

| Hook | Type | Parameters | Notes |
|------|------|------------|-------|
| `update_plugins_{$hostname}` | filter | `false\|array $update, array $plugin_data, string $plugin_file, string[] $locales` | Third-party plugin offers |
| `update_themes_{$hostname}` | filter | `false\|array $update, array $theme_data, string $theme_stylesheet, string[] $locales` | Third-party theme offers |

#### Developer resources

- [Theme handbook: `Update URI` header](https://developer.wordpress.org/themes/core-concepts/main-stylesheet/#update-uri) — header purpose for themes; plugins follow the same pattern in core.

---

### 1.9 Core integrity verification (checksums)

Checksum verification compares installed files to expected hashes for a given WordPress version. This supports diagnostics and CLI tooling. It does not gate the automatic updater before auto-updates run. Package signature verification on downloaded ZIP files before install is a separate mechanism (§2.12).

#### How it works

The WordPress.org checksums API returns JSON whose `checksums` entry maps relative file paths to expected hashes for a core build and locale. Core fetches this map through `get_core_checksums()` in `wp-admin/includes/update.php`.

`Core_Upgrader::check_files()` in `wp-admin/includes/class-core-upgrader.php` loads checksums and compares `md5_file()` output to each expected hash, skipping paths under `wp-content/`. Site Health and upgrade paths may use the same API. The `update_core` discovery transient does not embed the checksum map — core retrieves it separately when needed.

The WP-CLI command `wp core verify-checksums` performs a similar integrity check from the command line. Verification is an integrity audit. It is not the same as Ed25519 package signature checks on downloaded ZIP files (§2.12) and does not serve as a blanket pre-flight gate for `WP_Automatic_Updater`.

#### Process flow

1. A tool or function requests checksums for a specific version and locale.
2. Core compares installed files against the hash map.
3. A mismatch indicates modified or missing core files.
4. The outcome informs administrators but does not by itself control the automatic updater pipeline.

#### Reference

| Function | File | Returns | Notes |
|----------|------|---------|-------|
| `get_core_checksums()` | `wp-admin/includes/update.php` | `array<string, string>` or `false` | Fetches the path-to-hash map; returns `false` on failure |
| `Core_Upgrader::check_files()` | `wp-admin/includes/class-core-upgrader.php` | `bool` | Compares `ABSPATH` files to the map |

#### Developer resources

- [`get_core_checksums()` reference](https://developer.wordpress.org/reference/functions/get_core_checksums/) — checksum map retrieval.
- [`Core_Upgrader::check_files()` reference](https://developer.wordpress.org/reference/classes/core_upgrader/check_files/) — in-core file comparison.
- [`wp core verify-checksums` CLI command](https://developer.wordpress.org/cli/commands/core/verify-checksums/) — CLI integrity check.

---

### 1.10 Exclusions: must-use plugins and drop-ins

Must-use plugins and drop-ins live outside the normal plugin and theme discovery pipelines. They never appear in WordPress.org update check payloads and are not upgraded through the standard update interface or automatic updater paths for ordinary plugins and themes.

#### How it works

Must-use plugins load from `wp-content/mu-plugins/` (and nested PHP files where supported). Core does not enumerate them like regular plugins in `wp-content/plugins/`, so `wp_update_plugins()` does not retrieve offers for them from WordPress.org.

Drop-ins (for example `advanced-cache.php`, `db.php`, and `object-cache.php`) are single files or small sets of files placed directly under `wp-content/` and registered through `get_dropins()`. They are not packaged as directory plugins; core has no built-in "update this drop-in from the directory" flow.

Replacing must-use plugins or drop-ins requires manual file deployment, a custom deployment pipeline, or tooling outside the native update mechanisms.

#### Process flow

1. Discovery runs for standard plugins and themes.
2. Must-use plugins and drop-ins are excluded from the offer model.
3. An administrator or automation tool copies new files into `mu-plugins/` or `wp-content/` (often through version control or CI/CD).
4. Core does not record version metadata in the update transients for these components.
5. Must-use plugins continue loading early in bootstrap; drop-ins take effect when present and valid.

#### Reference

| Artifact | Discovery | Application |
|----------|-----------|-------------|
| Must-use plugins | Not listed in the standard plugin update API flow | Manual or external deployment |
| Drop-ins | Not listed as installable packages | Manual or external deployment |

#### Developer resources

- [Must-use plugins](https://developer.wordpress.org/plugins/must-use-plugins/) — loading rules and constraints.
- [`get_dropins()` reference](https://developer.wordpress.org/reference/functions/get_dropins/) — drop-in filenames and detection.

---

## PART 2 — Application: How WordPress Installs Updates

### 2.1 The application model

Application is the phase where WordPress downloads packages, unpacks them, replaces files, and completes follow-up work. When you debug failed updates, you need this model as distinct from discovery and from the database upgrade phase.

#### How it works

`WP_Upgrader` coordinates downloading, filesystem access, unpack locations under `wp-content/upgrade/`, `install_package()`, and the `upgrader_process_complete` action. Interactive interfaces use skins. Background updates use `Automatic_Upgrader_Skin` and `WP_Automatic_Updater`. Bulk operations track progress for skins through `update_count` and `update_current`. Replacing files is not the same as migrating the database; see §2.4. Package signature verification and staging directory behavior are covered in §2.12 and §2.13.

#### Process flow

1. An entry point constructs an upgrader and a skin.
2. Interactive flows may request filesystem credentials.
3. The package downloads unless a filter short-circuits the request.
4. Files move into place.
5. Hooks fire, and maintenance mode may toggle.
6. Plugin or theme caches clear after success (§2.11).
7. Core file replacement may still require a separate `wp_upgrade()` run for the schema (§2.4).

#### Reference

| Layer | Responsibility |
|-------|----------------|
| `WP_Upgrader` | Orchestration: download, unpack, install, hooks |
| Specialized subclasses | Core, plugin, theme, and language pack upgraders |
| Skins | Output channel: HTML, JSON, or buffered messages |

#### Developer resources

- [`WP_Upgrader` class reference](https://developer.wordpress.org/reference/classes/wp_upgrader/) — base class for all upgraders.

---

### 2.2 `WP_Upgrader`: the base class

`WP_Upgrader` provides the shared implementation for downloading, filesystem work, unpacking, and package installation. Most customization hooks attach here or in its subclasses.

#### How it works

The class in `wp-admin/includes/class-wp-upgrader.php` coordinates downloading packages to a temporary file through `download_url()`, working with `WP_Filesystem`, unpacking ZIP files into `wp-content/upgrade/` through `unpack_package()`, calling `install_package()`, and firing the `upgrader_process_complete` action. `run()` applies `upgrader_package_options` before filesystem work and `upgrader_install_package_result` after `install_package()` (`wp-admin/includes/class-wp-upgrader.php:849`, `:948`). Bulk operations increment counters for progress skins.

#### Process flow

1. The caller constructs an upgrader and a skin.
2. `download_package()` may trigger the `upgrader_pre_download` filter.
3. `run()` applies `upgrader_package_options`.
4. `install_package()` runs the pre-install, source selection, clear destination, and post-install filters in sequence.
5. `upgrader_install_package_result` filters the result.
6. On completion, `upgrader_process_complete` fires.
7. Errors return `WP_Error` objects to the skin or caller.

#### Reference

| Class | File | Extends | Role |
|-------|------|---------|------|
| `WP_Upgrader` | `class-wp-upgrader.php` | — | Base orchestration, locks, and maintenance helpers |

#### Developer resources

- [`WP_Upgrader::install_package()` reference](https://developer.wordpress.org/reference/classes/wp_upgrader/install_package/) — installation pipeline hooks.

---

### 2.3 Specialized upgraders

Each major package type has an upgrader class with behaviors suited to that type. When you extend update flows, you typically subclass or call these classes.

#### How it works

`Core_Upgrader` replaces WordPress core files and supports partial builds and rollback-related options in its `upgrade()` method. `Plugin_Upgrader` handles single-plugin paths, `bulk_upgrade()`, and ZIP or URL installs. `Theme_Upgrader` handles theme updates and installations. `Language_Pack_Upgrader` installs translation updates collected from transients.

Core file replacement through `Core_Upgrader` remains separate from the database upgrade routine in §2.4.

#### Process flow

1. The entry point selects the appropriate class.
2. The upgrader receives package metadata from transients or direct arguments.
3. File operations run under maintenance mode and locks as applicable.
4. `Language_Pack_Upgrader::async_upgrade()` hooks at priority 20 on `upgrader_process_complete`, so translations install after a core, plugin, or theme upgrade in the same request.
5. `WP_Automatic_Updater::run()` may remove and re-add those listeners to control ordering during automatic batches.

#### Reference

| Class | File | Extends | Role |
|-------|------|---------|------|
| `Core_Upgrader` | `class-core-upgrader.php` | `WP_Upgrader` | Core file replacement |
| `Plugin_Upgrader` | `class-plugin-upgrader.php` | `WP_Upgrader` | Plugin updates and installs |
| `Theme_Upgrader` | `class-theme-upgrader.php` | `WP_Upgrader` | Theme updates and installs |
| `Language_Pack_Upgrader` | `class-language-pack-upgrader.php` | `WP_Upgrader` | Translation packages |

#### Developer resources

- [`Core_Upgrader` class reference](https://developer.wordpress.org/reference/classes/core_upgrader/) — core updates.

---

### 2.4 Core database upgrade phase (`wp_upgrade()`)

Replacing core files and migrating the database are separate steps. Many problems arise from conflating them.

#### How it works

After `Core_Upgrader` replaces files, the database upgrade phase runs separately through `wp_upgrade()` in `wp-admin/includes/upgrade.php`, outside `WP_Upgrader::install_package()`. That routine compares the stored `db_version` option to the global `$wp_db_version`. If the values differ, the routine runs `pre_schema_upgrade()`, `make_db_current_silent()`, and `upgrade_all()`. On multisite, `upgrade_network()` runs on the main site when applicable, and core writes `db_version` and `db_last_updated` to site meta (`wp-admin/includes/upgrade.php:684-687`). After flushing caches, WordPress fires `do_action( 'wp_upgrade', $wp_db_version, $wp_current_db_version )` where the first argument is the new database version and the second is the old. Table and schema changes typically rely on `dbDelta()` for idempotent SQL.

> **Note:** Files may match a new release while `wp_upgrade()` never completed or errored mid-run. The WP-CLI command `wp core update-db` forces the database phase independently. After a core file update, confirm that `db_version` (and multisite site meta where applicable) matches expectations before treating the upgrade as complete.

#### Process flow

1. `Core_Upgrader` installs the new core file tree.
2. An admin page load or CLI run invokes `wp_upgrade()` when versions differ.
3. `wp_upgrade()` runs silent upgrades and network upgrades as needed.
4. Caches flush and the `wp_upgrade` action fires.
5. If step 2 is skipped, the site runs new PHP with an old schema.

#### Reference

| Function | File | Returns | Notes |
|----------|------|---------|-------|
| `wp_upgrade()` | `wp-admin/includes/upgrade.php` | void | Schema migration after core file replacement |
| `dbDelta()` | `wp-admin/includes/upgrade.php` | array | Applies SQL deltas idempotently |

#### Developer resources

- [`wp_upgrade()` reference](https://developer.wordpress.org/reference/functions/wp_upgrade/) — database upgrade routine.
- [`wp_upgrade` action reference](https://developer.wordpress.org/reference/hooks/wp_upgrade/) — fires after a database upgrade.
- [`wp core update-db` CLI command](https://developer.wordpress.org/cli/commands/core/update-db/) — CLI database upgrade.

---

### 2.5 Upgrader skins: the output layer

Skins adapt upgrader output to full-page HTML, AJAX JSON, or silent background runs. They form the channel between the upgrader and the runtime.

#### How it works

Skins subclass `WP_Upgrader_Skin` and implement the contract between an upgrader instance and its environment. Skins do not extend `WP_Upgrader` itself. Interactive admin uses skins that render progress, errors, and FTP credential forms. AJAX skins collect messages and errors for JSON. Background update skins suppress visible output and buffer credential prompts; their messages feed logs and email bodies.

The upgrader calls `feedback()`, `error()`, `header()`, `footer()`, and `request_filesystem_credentials()`. Strings often come from `$upgrader->strings`, set by subclass `upgrade_strings()` methods. `Automatic_Upgrader_Skin` buffers credential output and accumulates messages for notifications. `WP_Ajax_Upgrader_Skin` extends it for error collection on async responses.

#### Process flow

1. The upgrader sets a skin.
2. Each step calls skin methods to report progress.
3. Interactive flows print or return structured data.
4. Background flows buffer messages.
5. On failure, the skin or AJAX layer surfaces errors. Background runs email results only when the automatic updater handles the batch (Part 4).

#### Reference

| Class | File | Extends | Role |
|-------|------|---------|------|
| `WP_Upgrader_Skin` | `class-wp-upgrader-skin.php` | — | Base: credentials and default feedback |
| `Bulk_Upgrader_Skin` | `class-bulk-upgrader-skin.php` | `WP_Upgrader_Skin` | Multi-item progress |
| `Plugin_Upgrader_Skin` | `class-plugin-upgrader-skin.php` | `WP_Upgrader_Skin` | Single-plugin screen |
| `Theme_Upgrader_Skin` | `class-theme-upgrader-skin.php` | `WP_Upgrader_Skin` | Single-theme screen |
| `Bulk_Plugin_Upgrader_Skin` | `class-bulk-plugin-upgrader-skin.php` | `Bulk_Upgrader_Skin` | Bulk plugin update interface |
| `Bulk_Theme_Upgrader_Skin` | `class-bulk-theme-upgrader-skin.php` | `Bulk_Upgrader_Skin` | Bulk theme update interface |
| `Language_Pack_Upgrader_Skin` | `class-language-pack-upgrader-skin.php` | `WP_Upgrader_Skin` | Translation update screen |
| `Plugin_Installer_Skin` | `class-plugin-installer-skin.php` | `WP_Upgrader_Skin` | Plugin install and overwrite screen |
| `Theme_Installer_Skin` | `class-theme-installer-skin.php` | `WP_Upgrader_Skin` | Theme install and overwrite screen |
| `Automatic_Upgrader_Skin` | `class-automatic-upgrader-skin.php` | `WP_Upgrader_Skin` | No HTML; messages for email or logs |
| `WP_Ajax_Upgrader_Skin` | `class-wp-ajax-upgrader-skin.php` | `Automatic_Upgrader_Skin` | AJAX errors and messages |

#### Developer resources

- [`WP_Upgrader_Skin` class reference](https://developer.wordpress.org/reference/classes/wp_upgrader_skin/) — base skin class.

---

### 2.6 `WP_Plugin_Dependencies`: dependency resolution (WordPress 6.5+)

Plugin dependencies declared in headers are resolved by `WP_Plugin_Dependencies`. Core bulk update order does not topologically sort by dependency; this API helps your custom code order work correctly.

#### How it works

The `Requires Plugins` header lists comma-separated WordPress.org plugin slugs. `WP_Plugin_Dependencies` in `wp-includes/class-wp-plugin-dependencies.php` reads headers, fetches dependency metadata, and can detect circular graphs. The install interface disables the Install Now button until requirements are met. WP-CLI enforcement is strongest on activation; multiple commands may be needed.

Core bulk updates do not reorder by dependency graph. Public helpers such as `get_dependency_names()` and `get_dependency_filepath()` help you infer dependency-before-dependent ordering, though some internals are protected.

#### Process flow

1. Core loads dependency metadata from plugin headers.
2. Unmet dependencies block install actions in the interface.
3. Updates run in caller order unless external code reorders using dependency data.
4. Activation may still fail if constraints are unmet.

#### Reference

| Class | File | Extends | Role |
|-------|------|---------|------|
| `WP_Plugin_Dependencies` | `class-wp-plugin-dependencies.php` | — | Graph and metadata for required plugins |

#### Developer resources

- [`WP_Plugin_Dependencies` class reference](https://developer.wordpress.org/reference/classes/wp_plugin_dependencies/) — class reference.
- [Plugin dependencies merge announcement](https://make.wordpress.org/core/2024/02/15/merge-announcement-plugin-dependencies/) — feature background.

---

### 2.7 Rollback system (WordPress 6.2 and later)

Rollback support evolved across releases: safer directory moves, manual plugin and theme rollback with temporary backups, then automatic plugin rollback after fatal errors detected through loopback.

#### How it works

WordPress 6.2 introduced `move_dir()` to centralize atomic-style moves across `WP_Filesystem` backends.

WordPress 6.3 added manual plugin and theme rollback. Before replacement, previous directory trees move to `wp-content/upgrade-temp-backup/plugins/{slug}` or `.../themes/{slug}`. On `WP_Error` from `install_package()` or related failures, the temporary backup restores on shutdown (`wp-admin/includes/class-wp-upgrader.php:952-968`). Reactivation is a separate entry-point step (`wp-admin/update.php:86`), not part of the rollback path. Site Health gained backup-folder-writable and disk-space tests.

WordPress 6.6 added automatic rollback for active plugins. After an update, core may send a loopback request to the home URL to detect PHP fatals and call `restore_temp_backup()`. Inactive plugins skip fatal detection. Email may follow a rollback.

Temporary backups are not a general user-facing rollback product. On success or after restore, WordPress clears their contents. `WP_Upgrader::init()` schedules weekly `wp_delete_temp_updater_backups`; deletion can defer when locks or AJAX requests are active.

#### Process flow

1. An update starts and may create a temporary backup.
2. WordPress replaces the files.
3. On failure or fatal detection, the restore runs from the temporary backup.
4. Cron eventually deletes old temporary backups.
5. See §2.10 for loopback details.

#### Reference

| Milestone | Introduced in | Behavior |
|-----------|---------------|----------|
| Safer directory moves | 6.2 | `move_dir()` for atomic-style moves |
| Manual rollback | 6.3 | Plugin and theme rollback from temporary backup |
| Automatic active-plugin rollback | 6.6 | Rollback after fatal detected through loopback |

#### Developer resources

- [New in 6.3: rollback for failed manual plugin and theme updates](https://make.wordpress.org/core/2023/07/11/new-in-6-3-rollback-for-failed-manual-plugin-and-theme-updates/) — manual rollback feature.
- [Merge proposal: rollback after auto-update](https://make.wordpress.org/core/2024/04/19/merge-proposal-rollback-auto-update/) — automatic rollback path.

---

### 2.8 Upgrader locks

Locks prevent concurrent upgrade processes from corrupting state. The automatic updater and the core upgrader use different lock names and timeouts.

#### How it works

`WP_Upgrader::create_lock( $lock_name, $release_timeout )` stores a lock option with a Unix timestamp. The default release timeout when omitted is one hour. `WP_Automatic_Updater::run()` uses the `auto_updater` lock with the default timeout. `Core_Upgrader` uses `core_updater` with a 15-minute timeout. `release_lock()` deletes the option. Stale locks block new runs until expiry or manual deletion of the lock option through appropriate APIs.

#### Process flow

1. A run calls `create_lock()`.
2. If the lock exists and has not expired, the new run aborts or waits per caller logic.
3. On completion or fatal exit, `release_lock()` runs when the caller implements it.
4. If a process dies without releasing the lock, the lock expires after the timeout.

#### Reference

| Lock name | Typical timeout | Used by |
|-----------|-----------------|---------|
| `auto_updater` | Default (one hour) | `WP_Automatic_Updater::run()` |
| `core_updater` | 900 seconds (15 minutes) | `Core_Upgrader` |

#### Developer resources

- [`WP_Upgrader::create_lock()` reference](https://developer.wordpress.org/reference/classes/wp_upgrader/create_lock/) — lock acquisition.
- [`WP_Upgrader::release_lock()` reference](https://developer.wordpress.org/reference/classes/wp_upgrader/release_lock/) — lock release.

---

### 2.9 Maintenance mode

Maintenance mode limits front-end execution while files are inconsistent. Automatic batches may hold maintenance mode longer to avoid serving half-updated code.

#### How it works

`WP_Upgrader::maintenance_mode( true | false )` toggles the `.maintenance` file during updates. `wp_is_maintenance_mode()` reads the file, and the `enable_maintenance_mode` filter can veto it (`wp-includes/load.php:474`). For automatic updates, maintenance may remain enabled for the entire batch so that front-end requests do not hit partially updated code during rollback-related work.

#### Process flow

1. An upgrade begins and enables maintenance mode.
2. WordPress replaces files.
3. On success or controlled failure, WordPress disables maintenance mode.
4. Long batches keep maintenance mode active until the batch completes, per core behavior for that version.

#### Reference

| Mechanism | Role |
|-----------|------|
| `.maintenance` file | Short-circuits front-end requests during upgrade |

#### Developer resources

- [WordPress upgrade process overview](https://developer.wordpress.org/advanced-administration/upgrade/wordpress-upgrade-process/) — describes the `.maintenance` file behavior.

---

### 2.10 Loopback requests in the update pipeline

Loopback requests are HTTP requests from PHP to the same site's front end. They matter for Site Health and for automatic plugin rollback after fatal errors.

#### How it works

A loopback typically targets `home_url( '/' )` with scrape query arguments. Site Health's loopback test checks whether self-requests succeed.

WordPress 6.6+ automatic updater fatal detection uses `wp_remote_get()` on the home URL. If the response is a transport `WP_Error`, `WP_Automatic_Updater::has_fatal_error()` returns true (`wp-admin/includes/class-wp-automatic-updater.php:1809-1814`), so an infrastructure failure can trigger a restore.

Localhost environments, reverse proxies, or blocked outbound HTTP can break loopbacks.

#### Process flow

1. The updater completes a plugin change.
2. Core sends an HTTP request to the home URL.
3. Core inspects the response for fatal markers.
4. On a positive fatal signal, rollback may run.
5. On a transport failure, behavior follows the current core implementation.

#### Reference

| Context | Role |
|---------|------|
| Site Health | Diagnoses loopback capability |
| WordPress 6.6+ auto-update rollback | Fatal detection after active plugin update |

#### Developer resources

- [`wp_remote_get()` reference](https://developer.wordpress.org/reference/functions/wp_remote_get/) — HTTP GET primitive used in loopback checks.

---

### 2.11 Cache invalidation after updates

After file changes, WordPress must refresh plugin and theme runtime caches so the interface and APIs reflect new versions without unnecessary HTTP requests to WordPress.org.

#### How it works

After successful plugin or theme updates, core calls `wp_clean_plugins_cache()` and `wp_clean_themes_cache()`. Each function clears the relevant site transient by default and refreshes object caches or rescans theme directories.

`wp_clean_update_cache()` deletes the `update_core`, `update_plugins`, and `update_themes` transients in one call and invokes the plugin and theme cleaners when available. It does not by itself contact WordPress.org.

`wp_update_plugins()` and `wp_update_themes()` perform immediate POST requests subject to internal throttles. Use them when you need fresh offer payloads in the same request, not only cleared caches.

#### Process flow

1. An upgrade succeeds.
2. Cache cleaners run.
3. The next call to `get_plugins()` or `wp_get_themes()` returns fresh data.
4. Sidebar counts refresh on the next admin load if transients were cleared.
5. Custom code that upgrades outside core paths should call the same cleaners if versions appear stale.

#### Reference

| Function | File | Returns | Notes |
|----------|------|---------|-------|
| `wp_clean_plugins_cache()` | `wp-admin/includes/plugin.php` | void | Clears plugin data caches and the `update_plugins` transient |
| `wp_clean_themes_cache()` | `wp-includes/theme.php` | void | Rescans themes; clears the `update_themes` transient |
| `wp_clean_update_cache()` | `wp-includes/update.php` | void | Clears all three update transients and related caches |

#### Developer resources

- [`wp_clean_update_cache()` reference](https://developer.wordpress.org/reference/functions/wp_clean_update_cache/) — single-call invalidation.

---

### 2.12 Cryptographic signature verification (Ed25519)

From WordPress 5.2 onward, core can verify Ed25519 signatures on downloaded packages before trusting them for installation. This mitigates supply-chain tampering between WordPress.org and the filesystem by checking each package against keys distributed with WordPress.

#### How it works

`verify_file_signature()` in `wp-admin/includes/file.php` hashes the downloaded file with SHA-384 and verifies detached signatures using `sodium_crypto_sign_verify_detached()` against public keys from `wp_trusted_keys()`. Signatures are base64-encoded in transport; keys are base64-encoded public keys.

The native PHP sodium extension is used when available. Otherwise, WordPress may use the bundled `sodium_compat` polyfill subject to a runtime performance check. If verification cannot run in reasonable time, core returns a `WP_Error` with code `signature_verification_unsupported` rather than silently skipping crypto. If no signature accompanies the package, verification fails with `signature_verification_no_signature`. If no candidate signature matches any trusted key, verification fails with `signature_verification_failed`.

The trusted key list is filterable through `wp_trusted_keys`. HTTP download helpers such as `download_url()` integrate this path so verification occurs after the ZIP downloads and before the upgrader treats the package as trusted for extraction.

**Soft-fail behavior:** `WP_Upgrader::run()` treats a signature `WP_Error` that carries `softfail-filename` in its error data as a non-fatal case for every package type (`wp-admin/includes/class-wp-upgrader.php:886-904`). `Core_Upgrader::upgrade()` has the same path (`wp-admin/includes/class-core-upgrader.php:134-148`). The `wp_signature_softfail` filter controls whether a failure is marked soft (`wp-admin/includes/file.php:1348`). The failure may appear as feedback while the download path from the error data is used, allowing the upgrade to continue. Errors without that marker abort.

#### Process flow

1. A package URL downloads to a temporary file.
2. Signature material from the update response or accompanying metadata passes into `verify_file_signature()`.
3. On success, installation proceeds.
4. On `WP_Error`, the upgrade aborts for that package unless the error carries `softfail-filename`, which `WP_Upgrader::run()` treats as a soft failure.
5. On unsupported environments, the operation fails with a defined error code rather than completing an unverified install.

#### Reference

| Function | Role |
|----------|------|
| `verify_file_signature()` | Ed25519 verification over SHA-384 hash of file on disk |
| `wp_trusted_keys()` | Returns trusted base64-encoded public keys (filterable) |
| `sodium_crypto_sign_verify_detached()` | Primitive used when available; `sodium_compat` may substitute if fast enough |

#### Developer resources

- [`verify_file_signature()` reference](https://developer.wordpress.org/reference/functions/verify_file_signature/) — signature verification API.
- [`wp_trusted_keys()` reference](https://developer.wordpress.org/reference/functions/wp_trusted_keys/) — trusted signing keys.
- [Security in WordPress 5.2](https://make.wordpress.org/core/2019/05/17/security-in-5-2/) — release notes on signatures and trust.

---

### 2.13 Staging directory lifecycle and garbage collection

Downloaded archives and extracted working trees use two different locations. `download_url()` writes the ZIP to a general temporary directory through `wp_tempnam()` and `get_temp_dir()`. `WP_Upgrader::unpack_package()` stages unpacked files under `wp-content/upgrade/`. That directory is the primary on-disk workspace before files move into `wp-content/plugins/`, `wp-content/themes/`, or the core root. Contents are transient; failed or interrupted runs can leave debris that interferes with later upgrades.

#### How it works

`download_url()` stores the incoming file in the temporary directory (system temp, upload temp, `WP_CONTENT_DIR`, or `/tmp/` per `get_temp_dir()`, unless `WP_TEMP_DIR` overrides). `WP_Upgrader::download_package()` passes remote URLs to `download_url()`; the returned path then feeds `unpack_package()`.

`unpack_package()` clears `wp-content/upgrade/` through `WP_Filesystem` but keeps `index.php`, and creates that file when it is missing (`wp-admin/includes/class-wp-upgrader.php:384-406`). It then builds a working subdirectory name from the package basename plus a random 16-character suffix (`get_working_dir_name()`, private, `:349-359`) and runs `unzip_file()` into that folder. After extraction, WordPress usually deletes the ZIP when `$delete_package` is true. If `unzip_file()` fails, core deletes the working directory (`:424-430`).

A stuck or partial extraction leaves directories or partial files under `upgrade/`. Disk-full conditions, permission errors, or killed PHP workers can orphan temporary ZIP files or `upgrade/` trees. On the next run, `unpack_package()` attempts to clear `upgrade/` before unpacking, but collisions or leftover paths can still yield `WP_Error` from the filesystem layer.

Core does not guarantee immediate cleanup of every partial path after every failure. Administrators may need to remove stale `upgrade/` contents when troubleshooting. Rollback-related temporary trees under `wp-content/upgrade-temp-backup/` follow their own deletion and cron cleanup rules (see §2.7).

#### Process flow

1. The download lands in a temporary file (not necessarily under `upgrade/`).
2. `unpack_package()` unpacks the archive into `wp-content/upgrade/{package-basename}-{random}/` and keeps `index.php` in the parent directory.
3. On success, files move to the final destination and WordPress may remove the package file.
4. On failure, remnants may remain in both the temporary directory and `upgrade/`.
5. Manual deletion or filesystem cleanup resolves hard failures.
6. Free disk space and writable permissions remain prerequisites.

#### Reference

| Location | Typical contents | Risk if orphaned |
|----------|------------------|------------------|
| General temporary directory (`get_temp_dir()` or `wp_tempnam()`) | Downloaded `.zip` or `.tmp` before unpack | Leftover large files; disk pressure |
| `wp-content/upgrade/` | Cleared before unpack; extracted working folders during install | Name collisions; permission or disk errors on next run |
| `wp-content/upgrade-temp-backup/` | Rollback snapshots (see §2.7) | Space pressure; separate cleanup hooks |

#### Developer resources

- [`download_url()` reference](https://developer.wordpress.org/reference/functions/download_url/) — downloads through `wp_tempnam()`; path comes from `get_temp_dir()`.
- [`get_temp_dir()` reference](https://developer.wordpress.org/reference/functions/get_temp_dir/) — writable temporary directory for downloads.
- [`WP_Upgrader` class reference](https://developer.wordpress.org/reference/classes/wp_upgrader/) — `unpack_package()` uses `wp-content/upgrade/` for extraction.

---

## PART 3 — Automatic Background Updates

### 3.1 The automatic updater model

`WP_Automatic_Updater` applies updates on a schedule without interactive credentials. You hook its decisions and outcomes separately from manual upgrader use.

#### How it works

During cron, `wp_version_check()` may call `do_action( 'wp_maybe_auto_update' )` when not already inside that action. `wp_maybe_auto_update()` loads admin includes, instantiates `WP_Automatic_Updater`, and calls `run()`. The class uses `Automatic_Upgrader_Skin`, evaluates `is_disabled()` and per-item `should_update()`, selects packages from transients, and runs upgraders.

Inside `run()`, after acquiring the `auto_updater` lock, core processes updates in a fixed order: plugins (from `update_plugins` after `wp_update_plugins()`), then themes (`update_themes` after `wp_update_themes()`), then core (`wp_version_check()` plus `find_core_auto_update()`), then translation packs from `wp_get_translation_updates()`. On multisite, `run()` returns unless the current site is the main network and the main site (`wp-admin/includes/class-wp-automatic-updater.php:647-649`).

The `automatic_updates_complete` hook fires at the end of a batch only when `$update_results` is non-empty. If nothing ran or nothing recorded results, the hook does not fire. See §4.7 for emails and that hook.

#### Process flow

1. Cron or an external trigger runs `wp-cron.php`.
2. Update checks may chain to `wp_maybe_auto_update`.
3. `run()` checks `is_disabled()`. If that check passes, the updater walks the plugin, theme, core, and translation sequence.
4. Each item downloads and installs through the same upgrader stack as manual flows, with a non-interactive skin.
5. When results exist, completion fires notification paths and `automatic_updates_complete` (Part 4).

#### Reference

| Class | File | Extends | Role |
|-------|------|---------|------|
| `WP_Automatic_Updater` | `class-wp-automatic-updater.php` | — | Background decisions and execution; `run()` order: plugins, themes, core, translations |

#### Developer resources

- [`WP_Automatic_Updater` class reference](https://developer.wordpress.org/reference/classes/wp_automatic_updater/) — class reference.

---

### 3.2 Global disable conditions (`is_disabled()`)

The automatic updater short-circuits when the site cannot or must not modify files, or when you explicitly disable it.

#### How it works

`WP_Automatic_Updater::is_disabled()` returns true when `wp_is_file_mod_allowed( 'automatic_updater' )` is false (for example when `DISALLOW_FILE_MODS` is true unless filtered), when `wp_installing()` is true, or when `AUTOMATIC_UPDATER_DISABLED` is true or the `automatic_updater_disabled` filter returns true. Disabling the automatic updater also suppresses its notification emails in current core behavior.

#### Process flow

1. `run()` calls `is_disabled()`.
2. If the method returns true, no automatic updates execute.
3. Entry points that depend on background updates should surface this state in diagnostics.

> **Note:** `should_update()` also calls `is_disabled()` first on every item, so a global disable applies when evaluating offers inside `find_core_auto_update()` and elsewhere — not only at the start of `run()`.

#### Reference

| Condition | Effect |
|-----------|--------|
| `wp_is_file_mod_allowed( 'automatic_updater' )` returns false | Updates blocked |
| `wp_installing()` returns true | Updates blocked |
| `AUTOMATIC_UPDATER_DISABLED` is true or `automatic_updater_disabled` returns true | Updates blocked |

#### Developer resources

- [`automatic_updater_disabled` filter reference](https://developer.wordpress.org/reference/hooks/automatic_updater_disabled/) — filter to disable automatic updates.

---

### 3.3 Per-item eligibility (`should_update()`)

Each candidate item passes through `should_update( $type, $item, $context )`, which merges filesystem reality, VCS detection, API flags, opt-ins, filters, and compatibility checks.

#### How it works

`should_update()` begins by calling `is_disabled()` again. If the automatic updater is globally disabled, the method returns false before any per-item logic, so `find_core_auto_update()` and other callers also respect the global disable.

The remaining checks combine: non-interactive filesystem access (background cannot show FTP forms); VCS checkout detection (see §3.4); per-type allow rules (`Core_Upgrader::should_update_to_version()` for core, or `autoupdate` plus `auto_update_plugins` and `auto_update_themes` for plugins and themes, or `autoupdate` for translations); `disable_autoupdate` on the offer (see §1.4); `apply_filters( "auto_update_{$type}", … )`; then for core, PHP and MySQL checks against the offer; for plugins and themes, `requires_php` if set.

For core, the PHP and MySQL compatibility checks run after `disable_autoupdate` and `auto_update_core`, not at the start (see §3.7).

#### Process flow

1. `should_update()` calls `is_disabled()`. If true, the method returns false immediately.
2. Core checks whether the filesystem supports non-interactive writes.
3. VCS checkout detection runs for the relevant path.
4. Type-specific allow rules evaluate: branch policy for core, or `autoupdate` plus opt-in arrays for plugins and themes.
5. The `disable_autoupdate` property may force the result to false.
6. The `auto_update_{$type}` filter runs.
7. For core: PHP and MySQL compatibility checks evaluate against the offer. For plugins and themes: `requires_php` evaluates against runtime PHP.
8. A false result at any step aborts that item. A true result proceeds to download and install.
9. The `pre_auto_update` action fires once per item before the attempt (see §6.5).

#### Reference

| Check | Typical outcome if failed |
|-------|---------------------------|
| `is_disabled()` | Global disable; returns false (short-circuit) |
| Filesystem | No silent write capability; returns false |
| VCS | Checkout detected; returns false (unless filtered) |
| Type allow (branch policy, `autoupdate`, or opt-in lists) | Returns false |
| `disable_autoupdate` | Forces false before `auto_update_{$type}`; filter may re-enable |
| `auto_update_{$type}` | Filter returns false; returns false (core may still trigger notification email) |
| PHP or MySQL (core) or `requires_php` (plugin or theme) | Incompatible runtime; returns false |

#### Developer resources

- [`WP_Automatic_Updater::should_update()` reference](https://developer.wordpress.org/reference/classes/wp_automatic_updater/should_update/) — eligibility method.

---

### 3.4 VCS checkout detection

Automatic updates skip paths that look like version control working copies to avoid clobbering developer or deployment layouts.

#### How it works

`WP_Automatic_Updater::is_vcs_checkout()` walks upward from the package path and always checks `ABSPATH` for markers `.svn`, `.git`, `.hg`, or `.bzr`. Before it checks a directory, `is_allowed_dir()` skips paths outside `open_basedir` (`wp-admin/includes/class-wp-automatic-updater.php:73-107`). A match disables auto-updates for that item without an admin notice. The filter `automatic_updates_is_vcs_checkout` receives `( bool $checkout, string $context )` and may return false to override the detection (for example on CI or managed hosts).

A Git clone of core or a plugin under `wp-content` can silently block automatic updates for that path.

#### Process flow

1. `should_update()` calls VCS detection for the relevant context.
2. If detection returns true, the item is skipped unless the filter clears the flag.
3. Manual updates through the admin interface may still proceed with appropriate credentials.

#### Reference

| Hook | Type | Parameters | Notes |
|------|------|------------|-------|
| `automatic_updates_is_vcs_checkout` | filter | `$checkout`, `$context` | Override VCS detection |

#### Developer resources

- [`WP_Automatic_Updater::is_vcs_checkout()` reference](https://developer.wordpress.org/reference/classes/wp_automatic_updater/is_vcs_checkout/) — detection helper.

---

### 3.5 PHP and MySQL compatibility guards

Offers may declare required PHP or database versions. Automatic updates refuse incompatible targets before an update attempt begins.

#### How it works

For core, `should_update()` compares the PHP version to `$item->php_version` and MySQL to `$wpdb->db_version()` unless a drop-in replaces MySQL. A mismatch returns false with no update attempt. For plugins and themes, if `$item->requires_php` is set and runtime PHP is lower, the result is false.

These checks run inside `should_update()` after filesystem and VCS guards, type-specific allow rules, `disable_autoupdate`, and `auto_update_{$type}` — not as the first gate (see §3.3 and §3.7).

#### Process flow

1. Earlier guards may already return false.
2. If the item is still eligible, `should_update()` applies PHP and MySQL checks (core) or `requires_php` (plugins and themes).
3. An incompatible runtime returns false before `update()` runs.

#### Reference

| Type | Guard |
|------|-------|
| Core | PHP and MySQL version comparison against the offer |
| Plugin or theme | `requires_php` comparison against runtime |

#### Developer resources

- [`WP_Automatic_Updater::should_update()` reference](https://developer.wordpress.org/reference/classes/wp_automatic_updater/should_update/) — documents guard behavior.

---

### 3.6 `find_core_auto_update()`: selecting the core offer

Only one core automatic offer runs per batch. `find_core_auto_update()` picks it from the `update_core` transient.

#### How it works

The function reads the `update_core` site transient, iterates the `updates` array, and skips any offer whose `response` is not `autoupdate`. For each remaining offer, it calls `should_update( 'core', $update, ABSPATH )`. It then picks the highest version among passing offers using version comparison on the `current` property, or returns false if none qualify.

A core auto-update never runs unless the offer exists with `response === 'autoupdate'` and `should_update()` passes. Manual-only entries in the interface do not flow through this selector.

#### Process flow

1. Core loads the `update_core` transient.
2. Offers without `response === 'autoupdate'` are dropped.
3. Each candidate passes through `should_update()`.
4. The highest-passing version wins.
5. If none qualify, the function returns false and no automatic core update runs this batch.

#### Reference

| Function | File | Returns | Notes |
|----------|------|---------|-------|
| `find_core_auto_update()` | `wp-admin/includes/update.php` | object or false | Selects the automatic core offer |

#### Developer resources

- [`find_core_auto_update()` reference](https://developer.wordpress.org/reference/functions/find_core_auto_update/) — function reference.

---

### 3.7 Core branch policy and resolution order

Automatic core updates combine constant policy, site options, branch filters, offer vetoes, and the per-offer `auto_update_core` filter. The order below is the resolution chain you must reason about.

#### How it works

`find_core_auto_update()` considers only offers with `response === 'autoupdate'`, then calls `should_update( 'core', $update, ABSPATH )` for each candidate.

`Core_Upgrader::should_update_to_version()` applies the `WP_AUTO_UPDATE_CORE` constant when defined, or else the site options `auto_update_core_dev`, `auto_update_core_minor`, and `auto_update_core_major`. It also evaluates failure-history checks, branch logic, and the filters `allow_dev_auto_core_updates`, `allow_minor_auto_core_updates`, and `allow_major_auto_core_updates` depending on the branch. Each filter receives the boolean derived from constants and options.

The `disable_autoupdate` property on the offer can force the pending decision to false; filters may still re-enable the item. Then `apply_filters( 'auto_update_core', $update, $item )` runs. PHP and MySQL compatibility checks on the offer follow.

> **Note:** `apply_filters( 'wp_auto_update_core', … )` is not invoked by `Core_Upgrader` in core as of inspected versions. Do not confuse this filter name with the site option or constant handling. Policy for which classes of core update are allowed lives in `should_update_to_version()`. The dynamic hook `auto_update_core` is the per-offer gate inside `should_update()` after the initial allow or deny for that offer is computed.

#### Process flow

1. Only offers with `response === 'autoupdate'` are considered.
2. `Core_Upgrader::should_update_to_version()` evaluates the constant, `auto_update_core_*` options, failure history, branch logic, and `allow_*_auto_core_updates` filters — yielding a boolean.
3. `disable_autoupdate` on the offer can force false; filters may re-enable.
4. `apply_filters( 'auto_update_core', $update, $item )` runs.
5. PHP and MySQL compatibility checks on the offer follow.

#### Reference

| Hook | Type | Parameters | Notes |
|------|------|------------|-------|
| `allow_dev_auto_core_updates` | filter | bool | Development build installs |
| `allow_minor_auto_core_updates` | filter | bool | Same x.y branch |
| `allow_major_auto_core_updates` | filter | bool | x.y to x.y+1 |
| `auto_update_core` | filter | bool, object | After the initial core decision for the offer |

#### Developer resources

- [`auto_update_{$type}` filter reference](https://developer.wordpress.org/reference/hooks/auto_update_type/) — includes `auto_update_core`.

---

### 3.8 Per-plugin and per-theme auto-update preferences

Site options list which plugins and themes have opted into automatic updates. These options merge with `autoupdate` flags on offers.

#### How it works

`auto_update_plugins` stores an array of plugin basenames. `auto_update_themes` stores opted-in theme identifiers. The AJAX action `toggle-auto-updates` persists these options without running the upgrader. `should_update()` merges them with the offer's `autoupdate` flag when automatic updates are enabled for the type. `wp_is_auto_update_enabled_for_type()` combines `is_disabled()` with the `plugins_auto_update_enabled` and `themes_auto_update_enabled` filters (`wp-admin/includes/update.php:1042-1068`).

Multisite restricts who may change these toggles (see §5.11 and §5.4).

#### Process flow

1. A user toggles the interface control, or a network policy change updates the options.
2. The next `should_update()` call reads the opt-in arrays.
3. An offer without `autoupdate` still requires opt-in for that item unless a filter changes the outcome.

#### Reference

| Name | Type | Default | Effect |
|------|------|---------|--------|
| `auto_update_plugins` | site option (array) | empty | Plugin basenames opted into auto-updates |
| `auto_update_themes` | site option (array) | empty | Theme identifiers opted into auto-updates |

#### Developer resources

- [Controlling plugin and theme auto-updates UI in WordPress 5.5](https://make.wordpress.org/core/2020/07/15/controlling-plugin-and-theme-auto-updates-ui-in-wordpress-5-5/) — interface and storage.

---

### 3.9 Translation auto-updates and async chaining

Translations update automatically with their own filters and may install in a chained pass after another upgrade completes.

#### How it works

Two filters apply in different contexts.

`async_update_translation` runs inside `Language_Pack_Upgrader::async_upgrade()` only after a successful core, plugin, or theme upgrade in the same request. It governs whether to bulk-install translation packs in the follow-up pass; the default pending decision follows `autoupdate` on each language offer. Before installing, `async_upgrade()` returns without action if `WP_Automatic_Updater::is_vcs_checkout( WP_CONTENT_DIR )` detects a VCS checkout under `wp-content`, matching the cautious behavior for that code path.

`auto_update_translation` participates in `should_update()` for cron-driven automatic translation updates, like other `auto_update_{$type}` hooks.

Changing one filter does not automatically change the other.

#### Process flow

1. A primary upgrade completes.
2. The `upgrader_process_complete` action fires.
3. At priority 20, async translation upgrade may run (unless VCS detection short-circuits it).
4. Separately, cron-driven automatic updates evaluate translation items through `should_update()`, including the `auto_update_translation` filter.

#### Reference

| Hook | Type | Parameters | Notes |
|------|------|------------|-------|
| `async_update_translation` | filter | bool | Post-upgrade chain in same request |
| `auto_update_translation` | filter | bool | Background `should_update()` for translations |

#### Developer resources

- [`async_update_translation` filter reference](https://developer.wordpress.org/reference/hooks/async_update_translation/) — post-upgrade chain.

---

## PART 4 — Notifications and Feedback

### 4.1 The notification model

WordPress separates in-admin feedback from email. Manual updates surface in skins or AJAX; background updates can email the site administrator.

#### How it works

Logged-in users see notices, menu badges, and the Updates screen driven by transients and capabilities. `WP_Automatic_Updater` sends email for background outcomes through dedicated methods. Manual WP-CLI output is terminal-only unless hooks add behavior. Disabling the automatic updater also suppresses its notification emails per core documentation.

#### Process flow

1. Discovery updates transients.
2. The admin interface reads transients and displays update information.
3. If background updates run, email may send after completion.
4. The `automatic_updates_complete` action runs only when `WP_Automatic_Updater::run()` records at least one result in its internal `$update_results` array. If the batch produces no tracked attempts, the hook does not fire (§4.7).

#### Reference

| Path | Channel |
|------|---------|
| Manual admin | Skin or AJAX JSON |
| Background | Email and optional hooks |
| WP-CLI | Standard output |

#### Developer resources

- [`automatic_updates_complete` action reference](https://developer.wordpress.org/reference/hooks/automatic_updates_complete/) — fires after a background batch with non-empty results.

---

### 4.2 In-admin notices and menu badges

In-admin messaging uses hooks and helpers without `wp_mail()` for routine update availability notifications.

#### How it works

`update_nag()` in `wp-admin/includes/update.php` hooks to `admin_notices` and `network_admin_notices` at priority 3. It outputs a warning admin notice (through `wp_admin_notice()` with type `warning` and classes such as `update-nag`) when a core upgrade is available from `get_preferred_from_update_core()` with `response === 'upgrade'`. It skips on `update-core.php`. Users without the `update_core` capability may see text directing them to notify the site administrator.

The admin menu update count bubble uses `wp_get_update_data()` from `wp-admin/menu.php`. The At a Glance widget uses `update_right_now_message()` for an update button when appropriate.

#### Process flow

1. The admin page renders.
2. Transients supply update counts.
3. Capabilities gate which messages appear.
4. No email sends through these mechanisms alone.

#### Reference

| Mechanism | Purpose |
|-----------|---------|
| `update_nag()` | Core upgrade notice |
| Menu bubble | Aggregate update counts |
| At a Glance widget | Quick action button when the user has capabilities |

#### Developer resources

- [`wp_get_update_data()` reference](https://developer.wordpress.org/reference/functions/wp_get_update_data/) — update counts for the menu.

---

### 4.3 Site Health: update-related diagnostic tests

Site Health surfaces configuration risks. It does not block updates.

#### How it works

`WP_Site_Health` runs diagnostic tests. Background update tests reflect factors similar to `WP_Automatic_Updater::is_disabled()`: constants, filters, VCS detection, and related flags. The relevant tests are `background_updates` (`wp-admin/includes/class-wp-site-health.php:1740`), `plugin_theme_auto_updates` (`:1815`), `available_updates_disk_space` (`:1854`), `update_temp_backup_writable` (`:1940`), and `loopback_requests` (`:2079`). Loopback tests verify that the server can send an HTTP request to itself (relevant for editors and the WordPress 6.6+ fatal detection feature). WordPress 6.3+ adds backup-folder-writable and disk-space checks tied to manual rollback temporary backups. Results are visibility only — confirm behavior in updater code when debugging.

#### Process flow

1. The Site Health page loads tests.
2. Each test returns status and messages.
3. Administrators use the results to fix environment issues.

#### Reference

| Concern | Relevance |
|---------|-----------|
| Background updates | Mirrors disable conditions |
| Loopback | Async features and rollback |
| Backup folder and disk space | Manual rollback space |

#### Developer resources

- [`WP_Site_Health` class reference](https://developer.wordpress.org/reference/classes/wp_site_health/) — Site Health class.

---

### 4.4 Core background update emails

Core automatic updates email the site administrator for success, failure, critical failure, and sometimes a manual-update prompt when the automatic apply was skipped.

#### How it works

`WP_Automatic_Updater` implements paths such as `after_core_update()` and `send_core_update_notification_email()`. Types include `success`, `fail`, `critical`, and `manual` notification (when gated by the API `notify_email` flag and filters). Site options `auto_core_update_notified` and `auto_core_update_failed` deduplicate or schedule retries.

The filter `send_core_update_notification_email` controls whether to send the "new core version available" mail (the manual prompt). The filter `auto_core_update_send_email` applies to types `success`, `fail`, and `critical` only — it does not run for `manual` mails because `send_email()` skips that gate when `$type === 'manual'`. The filter `auto_core_update_email` alters the composed message for sends that use `send_email()`.

The recipient is typically `get_site_option( 'admin_email' )` with possible locale switching.

#### Process flow

1. A background core update completes or fails.
2. Core chooses the email type.
3. Relevant filters run.
4. `wp_mail()` sends the email unless a filter disables it.
5. Persistence options prevent duplicate emails for the same release.

#### Reference

| Hook | Type | Parameters | Notes |
|------|------|------------|-------|
| `send_core_update_notification_email` | filter | bool, object | Whether to send the manual-update-available mail (`notify_email` path) |
| `auto_core_update_send_email` | filter | bool, string, object, mixed | Gates success, fail, and critical background mails — not `manual` |
| `auto_core_update_email` | filter | array, string, object, mixed | Alters to, subject, body, and headers for `send_email()` |

#### Developer resources

- [`auto_core_update_email` filter reference](https://developer.wordpress.org/reference/hooks/auto_core_update_email/) — email payload filter.

---

### 4.5 Plugin and theme background update emails

Plugin and theme automatic batches can email summaries with success, failure, or mixed outcomes.

#### How it works

After plugin or theme auto-updates, `after_plugin_theme_update()` may call `send_plugin_theme_email()` with types `success`, `fail`, or `mixed`.

`auto_plugin_update_send_email` and `auto_theme_update_send_email` receive `( $enabled, $slice )` where `$slice` is `$update_results['plugin']` or `$update_results['theme']` (arrays of result objects for that type), not the full `$update_results` tree. `auto_plugin_theme_update_email` adjusts the final email payload.

The `auto_plugin_theme_update_emails` value is stored with `get_option()` and `update_option()` (per-site options API), not `get_site_option()`, when tracking failure versions to limit repeat messages. WordPress 6.6+ messaging may include rollback outcomes after fatal loopback.

#### Process flow

1. A batch completes.
2. Core decides whether to email.
3. Filters adjust or suppress the email.
4. WordPress sends the mail to the admin email unless a filter prevents it.

#### Reference

| Hook | Type | Parameters | Notes |
|------|------|------------|-------|
| `auto_plugin_update_send_email` | filter | bool, array | Plugin batch email gate; second argument is the plugin result list only |
| `auto_theme_update_send_email` | filter | bool, array | Theme batch email gate; second argument is the theme result list only |
| `auto_plugin_theme_update_email` | filter | array, string, array, array | Final to, subject, body, and headers; the last two arguments are the successful and failed update lists |

#### Developer resources

- [`auto_plugin_theme_update_email` filter reference](https://developer.wordpress.org/reference/hooks/auto_plugin_theme_update_email/) — combined plugin and theme email filter.

---

### 4.6 Debug emails (development builds)

Development WordPress builds may send a debug log email after background updates when enabled.

#### How it works

If the site runs a development version (a version string containing a hyphen, such as a release candidate suffix) and background update results exist, core may send a debug email. The filter `automatic_updates_send_debug_email` defaults to true only on development versions. The filter `automatic_updates_debug_email` adjusts the debug mail payload.

#### Process flow

1. A background batch finishes on a development build.
2. The debug email path evaluates filters.
3. An optional email sends with diagnostic content.

#### Reference

| Hook | Type | Parameters | Notes |
|------|------|------------|-------|
| `automatic_updates_send_debug_email` | filter | bool | Enables debug email |
| `automatic_updates_debug_email` | filter | array, array, array | Adjusts the email payload, failures, and full results |

#### Developer resources

- [`automatic_updates_debug_email` filter reference](https://developer.wordpress.org/reference/hooks/automatic_updates_debug_email/) — debug email content.

---

### 4.7 `automatic_updates_complete`: the results payload

The `automatic_updates_complete` action passes a structured array of results for logging and external notifications. When you consume this hook, inspect types, result objects, and error codes carefully.

#### How it works

`do_action( 'automatic_updates_complete', $update_results )` fires at the end of `WP_Automatic_Updater::run()` when results exist. The array matches the internal `update_results` property. Top-level keys are update type strings: `core`, `plugin`, `theme`, and `translation`. A key exists only if the batch attempted at least one update of that type. Each value is a numerically indexed array of result objects.

**When `messages` and `result` conflict on failure:** When an attempt fails with a `WP_Error` on `result` (common example: code `fs_unavailable` when `WP_Filesystem` cannot initialize), `messages` may still contain earlier or generic skin output — sometimes success-adjacent copy (such as "the site already has the latest version") that does not reflect the actual failure. **If you build operator-facing diagnostics, inspect `result` with `is_wp_error()` and surface the error code and message, not only `implode()` of `messages`.** The automatic-update debug email text draws from structured outcomes and `WP_Error`; achieving parity with that journal requires merging both sources.

**Rollback signals:** Core may set `result` to `WP_Error` with code `rollback_was_required` when `Core_Upgrader` rolls back after failure. Plugin automatic updates on WordPress 6.6+ may use codes such as `plugin_update_fatal_error_rollback_successful` or `plugin_update_fatal_error_rollback_failed` on the same `result` property, with `item` identifying the plugin.

#### Process flow

1. The batch run finishes.
2. Core populates result objects per attempt.
3. `automatic_updates_complete` fires only if `$update_results` is non-empty at the end of `run()`. Otherwise, listeners never run.
4. Your plugin can handle logging or outbound integrations from this hook.

#### Reference

**Top-level shape:**

| Shape | Details |
|-------|---------|
| Top-level keys | `'core'`, `'plugin'`, `'theme'`, `'translation'` — present only if attempted |
| Each value | Numeric array of result objects |

**Each result object:**

| Property | Type | Description |
|----------|------|-------------|
| `item` | object | Update offer from the relevant transient |
| `result` | `true`, `WP_Error`, or other falsy value | Success is typically boolean `true`; failures are usually `WP_Error`; other falsy values may indicate failure without a `WP_Error` in edge cases |
| `name` | string | Human-readable label |
| `messages` | array | Strings from the `Automatic_Upgrader_Skin` message buffer |

**Typical `item` fields by type:**

| Type | Identifiers | Version or package | Other (non-exhaustive) |
|------|-------------|---------------------|------------------------|
| `core` | — | `current` (offered ZIP version), often `version` display | `response` (`autoupdate` for automatic candidates), `locale`, `php_version`, `mysql_version`, `new_files`, download URLs, `autoupdate`, `disable_autoupdate` when present |
| `plugin` | `plugin`, `slug` | `new_version` | `package`, `url`, `id`, `requires_php`, `autoupdate`, `disable_autoupdate` |
| `theme` | `theme` (stylesheet slug) | `new_version` | `package`, `url`, `requires_php`, `autoupdate`, `disable_autoupdate` |
| `translation` | `type` (core, plugin, or theme), `slug`, `language` | `version` | `package`, `autoupdate` — objects from translation arrays through `wp_get_translation_updates()` |

#### Developer resources

- [`automatic_updates_complete` action reference](https://developer.wordpress.org/reference/hooks/automatic_updates_complete/) — hook reference.
- [`WP_Automatic_Updater::run()` reference](https://developer.wordpress.org/reference/classes/wp_automatic_updater/run/) — where results are produced.

---

### 4.8 Fatal error recovery email (`WP_Recovery_Mode`)

Fatal errors during bootstrap can trigger Recovery Mode, which emails a login link. This path is separate from automatic updater email.

#### How it works

`WP_Recovery_Mode` in `wp-includes/class-wp-recovery-mode.php` handles detection and email when a fatal error breaks the site after a change. Its triggers, options, and messaging differ from `WP_Automatic_Updater` emails. Do not conflate the two when debugging post-update failures.

#### Process flow

1. A fatal error occurs.
2. Recovery Mode may activate.
3. WordPress emails the site administrator with a secure login link.
4. The administrator resolves the fault using the recovery link.
5. This process is separate from background update success or failure emails.

#### Reference

| Class | File | Extends | Role |
|-------|------|---------|------|
| `WP_Recovery_Mode` | `class-wp-recovery-mode.php` | — | Fatal recovery and email |

#### Developer resources

- [`WP_Recovery_Mode` class reference](https://developer.wordpress.org/reference/classes/wp_recovery_mode/) — Recovery Mode class.

---

## PART 5 — Entry Points and Configuration

### 5.1 Entry points overview

Updates reach the same upgrader stack through the admin interface, AJAX (`admin-ajax.php`), cron (`WP_Automatic_Updater`), and WP-CLI. Core does not expose XML-RPC methods that run the plugin, theme, or core upgrade stack; treat XML-RPC as legacy for other workflows, not as a first-class update entry point (§5.7). Your choice of entry point determines the credentials, output channel, and capability context.

#### How it works

The Updates screen posts forms to upgrade actions. List tables use row and bulk actions with the `updates` script. AJAX actions call upgraders with `WP_Ajax_Upgrader_Skin`. Cron invokes `WP_Automatic_Updater`. WP-CLI loads admin includes and calls primitives directly. The bundled REST API does not offer a generic "upgrade an installed package to a new version" operation (§5.8).

#### Process flow

1. A user or process initiates an action.
2. Capabilities and nonces verify the request.
3. The appropriate skin attaches.
4. The upgrader runs.
5. The outcome returns through the skin, JSON, terminal, or email depending on the entry path.

#### Reference

| Entry | Output |
|-------|--------|
| `wp-admin` screens | HTML skin |
| AJAX | JSON |
| Cron | Background skin and email |
| WP-CLI | Terminal |

#### Developer resources

- [Managing plugins in the admin](https://developer.wordpress.org/apis/managing-plugins/) — plugin management context.

---

### 5.2 Dashboard Updates screen

`wp-admin/update-core.php` lists core, plugin, theme, and translation updates and submits bulk operations.

#### How it works

The screen loads through `admin.php`, enqueues the `updates` script, and checks capabilities (`update_core`, `update_plugins`, `update_themes`, and `update_languages` as appropriate). Forms POST to actions such as core upgrade and bulk plugin or theme updates. On multisite, non-network administrators may redirect to the network Updates screen per `update-core.php` logic.

#### Process flow

1. A user opens the Updates screen.
2. Core refreshes or displays cached offers.
3. The user submits a form.
4. The server runs the upgrader.
5. A redirect or message shows the outcome.

#### Reference

| Screen | Capability mix |
|--------|----------------|
| Updates | Varies by action: `update_core`, `update_plugins`, `update_themes`, `update_languages` |

#### Developer resources

- [Dashboard Updates screen](https://wordpress.org/documentation/article/dashboard-updates-screen/) — end-user documentation for the screen.

---

### 5.3 AJAX update flow

AJAX updates plugins and themes without a full page reload using `admin-ajax.php` and JSON responses.

#### How it works

Core registers actions such as `update-plugin` and `update-theme` in `wp-admin/admin-ajax.php`. Handlers verify the `updates` nonce, require capabilities, construct `Plugin_Upgrader` or `Theme_Upgrader` with `WP_Ajax_Upgrader_Skin`, and run `bulk_upgrade()` even for a single item. The plugin handler calls `wp_update_plugins()` on every update; the theme handler calls `wp_update_themes()` only when the `update_themes` transient is empty (`wp-admin/includes/ajax-actions.php:4364-4367`, `:4703`). JSON returns success or error for the async interface. The `toggle-auto-updates` action persists `auto_update_plugins` and `auto_update_themes` without invoking the upgrader.

#### Process flow

1. The browser posts to the AJAX handler.
2. WordPress verifies the nonce and capability.
3. The upgrader runs with the AJAX skin.
4. The skin collects errors and messages.
5. JSON returns to the browser.
6. The list interface updates the row state.

#### Reference

| Action | Effect |
|--------|--------|
| `update-plugin` | Plugin upgrade through AJAX |
| `update-theme` | Theme upgrade through AJAX |
| `toggle-auto-updates` | Toggle opt-in arrays only; no upgrader call |

#### Developer resources

- [AJAX in plugins](https://developer.wordpress.org/plugins/javascript/ajax/) — how admin AJAX handlers work with WordPress.

---

### 5.4 Per-plugin and per-theme auto-update interface (WordPress 5.5+)

List tables and theme details expose auto-update toggles stored in site options.

#### How it works

WordPress 5.5 added an Automatic Updates column and bulk actions on Installed Plugins; preferences persist in `auto_update_plugins`. Themes use `auto_update_themes` and controls in the theme details modal under Appearance, Themes.

`plugin_auto_update_setting_html` filters the markup in the plugins list table (`class-wp-plugins-list-table.php`). For single-site theme screens, `theme_auto_update_setting_template` filters the theme-details modal template (`wp-admin/themes.php`). The network themes list uses `theme_auto_update_setting_html` (`class-wp-ms-themes-list-table.php`).

Who may change toggles depends on capabilities and multisite role (network versus site administrator). Do not assume only super administrators in every setup.

#### Process flow

1. A user toggles the control.
2. AJAX or a form saves the options.
3. The next `should_update()` call reads the opt-in arrays together with offer flags.

#### Reference

| Hook | Type | Parameters | Notes |
|------|------|------------|-------|
| `plugin_auto_update_setting_html` | filter | string, string, array | Installed Plugins list: column and control HTML, plugin file, plugin data |
| `theme_auto_update_setting_template` | filter | string | Single-site theme modal: template fragment |
| `theme_auto_update_setting_html` | filter | string, string, `WP_Theme` | Network themes list table: control HTML, stylesheet, theme object |

#### Developer resources

- [Plugins and themes auto-updates](https://wordpress.org/documentation/article/plugins-themes-auto-updates/) — user-facing article with multisite rules.

---

### 5.5 Core major auto-update interface (WordPress 5.6+)

The Updates screen includes controls for major core automatic updates, stored separately from minor branch options.

#### How it works

WordPress 5.6 added interface controls on `update-core.php` to opt in or out of major core automatic updates. The preference is stored in the `auto_update_core_major` site option (`enabled`, `disabled`, or default `unset` semantics) — not a separate `wp_auto_update_core` option. See §1.3 and `update-core.php` together with `Core_Upgrader::should_update_to_version()`.

The `after_core_auto_updates_settings` action fires after the major core settings and receives the `dev`, `minor`, and `major` booleans (`wp-admin/update-core.php:454`). When `WP_AUTO_UPDATE_CORE` is defined in `wp-config.php`, the constant overrides branch policy and the `auto_update_core_*` site options are bypassed for that policy path.

#### Process flow

1. A user sets a preference on the Updates screen.
2. WordPress saves the option unless the constant overrides it.
3. `Core_Upgrader::should_update_to_version()` and `should_update()` apply the effective policy together with branch filters.

#### Reference

| Name | Type | Default | Effect |
|------|------|---------|--------|
| `auto_update_core_major` | site option | `unset` | Major core auto-update interface control (`enabled`, `disabled`, or `unset`); see §1.3 |

#### Developer resources

- [Core major versions auto-updates UI changes in WordPress 5.6 (correction)](https://make.wordpress.org/core/2020/11/24/core-major-versions-auto-updates-ui-changes-in-wordpress-5-6-correction/) — interface correction note.

---

### 5.6 WP-CLI

WP-CLI bootstraps WordPress and calls the same upgrade primitives as the admin after loading required files.

#### How it works

Typical commands include `wp core update`, `wp core update-db`, `wp plugin update`, `wp theme update`, and `wp language` update subcommands. WP-CLI is not bundled in core but is the supported CLI surface for scripted maintenance. Forced installation of an older core release than the current files (`--version` with `--force`) and schema implications are covered in §5.13.

#### Process flow

1. An operator runs a command.
2. WP-CLI loads WordPress.
3. The upgrader or upgrade routine executes.
4. Output prints to the terminal.
5. No core email sends unless hooks add that behavior.

#### Reference

| Command area | Examples |
|--------------|----------|
| Core | `wp core update`, `wp core update-db`, `wp core verify-checksums` |
| Packages | `wp plugin update`, `wp theme update` |
| Languages | `wp language core update`, `wp language plugin update`, `wp language theme update` |

#### Developer resources

- [WP-CLI command index](https://developer.wordpress.org/cli/commands/) — handbook index.

---

### 5.7 XML-RPC and Application Passwords

Legacy remote clients may trigger workflows that load admin code and require capabilities. XML-RPC is not the primary automation path for new projects.

#### How it works

XML-RPC and Application Password–authenticated requests can invoke supported remote procedures where enabled. Updates still require filesystem access and high-privilege checks. Prefer WP-CLI or controlled custom endpoints for new automation.

#### Process flow

1. A client authenticates.
2. WordPress routes the request.
3. If the procedure performs updates, the same capability and filesystem constraints apply as in the admin interface.

#### Reference

| Mechanism | Notes |
|-----------|-------|
| XML-RPC | Legacy breadth; capability-gated |
| Application Passwords | Modern REST authentication companion |

#### Developer resources

- [Application Passwords API](https://developer.wordpress.org/apis/application-passwords/) — authentication API.

---

### 5.8 REST API: no native update trigger

Core does not expose a REST endpoint that runs plugin, theme, or core upgrades as a generic operation.

#### How it works

The bundled `/wp/v2/plugins` and `/wp/v2/themes` routes expose metadata and support activation and deactivation (and directory installs by slug for plugins). The `update_item` handlers (PATCH or PUT on a plugin or theme) adjust status and fields. They do not download and install a newer version of an already-installed package.

Programmatic updates outside the browser typically use WP-CLI, custom admin code that loads upgrader classes with proper authentication, or patterns mirroring `admin-ajax.php` handlers.

#### Process flow

1. A REST client requests metadata or status changes allowed by the API.
2. The upgrade itself requires a different orchestration layer.

#### Reference

| Route | Upgrades installed package to newer release |
|-------|----------------------------------------------|
| `/wp/v2/plugins` | Not supported |
| `/wp/v2/themes` | Not supported |

#### Developer resources

- [Plugins REST API reference](https://developer.wordpress.org/rest-api/reference/plugins/) — supported operations.
- [Themes REST API reference](https://developer.wordpress.org/rest-api/reference/themes/) — supported operations.

---

### 5.9 Filesystem methods

`WP_Filesystem` abstracts direct, FTP, FTPS, and SSH access. Background updates fail closed without non-interactive credentials.

#### How it works

Core uses `WP_Filesystem` and `request_filesystem_credentials()`. The FTP and SSH filesystem classes expose a public `$options` property since 7.1.0 (`wp-admin/includes/class-wp-filesystem-ftpext.php:37`, `class-wp-filesystem-ssh2.php:72`). The `FS_METHOD` constant in `wp-config.php` can force `direct`, `ftpext`, `ftpsockets`, or `ssh2`. When the web server cannot write files, interactive flows show the credentials form. Background updates cannot prompt — they fail if credentials are not available non-interactively. `wp_is_file_mod_allowed( $context )` centralizes whether modifications are allowed, defaulting to `! DISALLOW_FILE_MODS` with the `file_mod_allowed` filter.

#### Process flow

1. The upgrader requests filesystem access.
2. A direct or credential-based method connects.
3. Writes proceed or return `WP_Error`.
4. The automatic updater aborts when file modifications are disallowed.

#### Reference

| Name | Type | Default | Effect |
|------|------|---------|--------|
| `FS_METHOD` | constant | auto | Forces a specific filesystem backend |
| `DISALLOW_FILE_MODS` | constant | false if unset | Blocks file modifications unless filtered |

#### Developer resources

- [Filesystem API overview](https://developer.wordpress.org/apis/filesystem-api/) — accessing the filesystem.

---

### 5.10 Configuration constants (`wp-config.php`)

Constants in `wp-config.php` override defaults for cron, HTTP, automatic updates, and filesystem behavior.

#### How it works

`AUTOMATIC_UPDATER_DISABLED` disables the background automatic updater pipeline including its notification emails when true. `WP_AUTO_UPDATE_CORE` as a boolean or string scopes core automatic updates (`true`, `false`, `minor`, or prerelease channel strings per version behavior). Before WordPress 5.6, `true` often implied minor-only; new installs since 5.6 default to major core auto-updates unless the user changes the setting.

When `WP_AUTO_UPDATE_CORE` is undefined, branch policy falls through to the `auto_update_core_*` options (§1.3). `DISALLOW_FILE_MODS` blocks file changes unless the `file_mod_allowed` filter overrides. `DISABLE_WP_CRON` stops cron spawning on page loads; scheduled checks need external `wp-cron.php` triggers (§1.5).

`WP_HTTP_BLOCK_EXTERNAL` and `WP_ACCESSIBLE_HOSTS` gate outbound HTTP. Blocking `api.wordpress.org` or `downloads.wordpress.org` breaks discovery unless you allowlist them. Outbound-only egress through a corporate HTTP proxy requires `WP_PROXY_*` constants (§5.12).

#### Process flow

1. PHP reads constants from `wp-config.php` before runtime.
2. Updaters consult them during `is_disabled()`, `should_update_to_version()`, and HTTP client calls.
3. Misconfiguration surfaces as missing updates or failed background runs.

#### Reference

| Name | Type | Default | Effect |
|------|------|---------|--------|
| `AUTOMATIC_UPDATER_DISABLED` | bool | false if unset | Disables the automatic updater and its notification emails |
| `WP_AUTO_UPDATE_CORE` | bool or string | See handbook | Overrides branch options when defined |
| `DISALLOW_FILE_MODS` | bool | false if unset | Disallows file modifications unless filtered |
| `FS_METHOD` | string | auto | Forces a specific filesystem backend |
| `DISABLE_WP_CRON` | bool | false if unset | Stops cron spawning on page loads |
| `WP_HTTP_BLOCK_EXTERNAL` | bool | false | Blocks outbound HTTP unless allowlisted |
| `WP_ACCESSIBLE_HOSTS` | string | empty | Hostnames allowed when blocking external requests |

#### Developer resources

- [`wp-config.php` configuration reference](https://developer.wordpress.org/apis/wp-config-php/) — full constant reference.

---

### 5.11 Multisite: update scope and database upgrade propagation

Multisite shares one codebase and site transients at the network level while database upgrades apply per site. After core updates, operators must run network upgrade flows.

#### How it works

Many update interface actions are network-admin only or redirect from site admin per `update-core.php`. Capabilities depend on user and site context; super administrator rules apply for network operations. Update transients are site transients, so the network shares one update cache.

Replacing core files is network-wide. `wp_upgrade()` runs `upgrade_all()` for the current site's tables, then `upgrade_network()` on the main site when `is_multisite() && is_main_site()`. On multisite, it also writes `db_version` and `db_last_updated` to site meta (`wp-admin/includes/upgrade.php:684-687`). Sub-sites do not all upgrade in one call.

`wp-admin/admin.php` may detect a `db_version` mismatch and trigger `do_mu_upgrade` (filter default true) with `upgrade.php?step=1` on large networks, using sampling when the blog count exceeds 50. Each site's `db_version` updates when that site's `wp-admin/upgrade.php` runs.

Network Admin, Upgrade Network (`wp-admin/network/upgrade.php`) walks sites in batches of five, switches blogs, and sends HTTP GET requests to each site's `admin_url( 'upgrade.php?step=upgrade_db' )`. WP-CLI `wp core update-db --network` runs the database upgrade across sites non-interactively.

After a network-wide core file update, each subsite may still show a pending database upgrade until the Upgrade Network screen, per-site upgrade, or network CLI completes.

#### Process flow

1. Core files update once (network-wide).
2. The main site may run partial upgrade routines.
3. Subsites upgrade lazily through the Network Upgrade screen or CLI.
4. Until each site's `db_version` matches, schema may lag on that site.

#### Reference

| Mechanism | Scope |
|-----------|-------|
| Codebase | Network-wide single tree |
| `wp_upgrade()` per request | Current site tables plus network routines on main |
| Network Upgrade screen | Batched per-site HTTP upgrade |
| `wp core update-db --network` | CLI upgrade for all sites |

#### Developer resources

- [`wp core update-db` CLI command](https://developer.wordpress.org/cli/commands/core/update-db/) — `--network` flag.
- [`upgrade_network()` reference](https://developer.wordpress.org/reference/functions/upgrade_network/) — network upgrade routines.

---

### 5.12 Enterprise HTTP proxy configuration

Hosts that egress only through an HTTP or HTTPS proxy must define proxy credentials and endpoints in `wp-config.php`. Without them, discovery requests to `api.wordpress.org` and package downloads fail, so no update offers appear and packages cannot download.

#### How it works

WordPress reads `WP_PROXY_HOST` and `WP_PROXY_PORT` to route outgoing HTTP requests through the corporate proxy. Optional `WP_PROXY_USERNAME` and `WP_PROXY_PASSWORD` supply authenticated proxy access. `WP_PROXY_BYPASS_HOSTS` lists hosts that should not use the proxy (comma-separated; wildcards such as `*.wordpress.org` are supported per `class-wp-http-proxy.php`).

These constants are consumed by the HTTP API when building requests (see `wp-includes/class-wp-http-proxy.php`). TLS interception, custom CA bundles, or server-level allowlists may still be required beyond WordPress constants. Discovery (`wp_version_check()`, `wp_update_plugins()`, `wp_update_themes()`) and package downloads all depend on the same HTTP stack; a misconfigured proxy blocks both.

#### Process flow

1. PHP loads `wp-config.php` constants before HTTP calls.
2. `WP_HTTP` uses proxy settings for each outbound request.
3. If the proxy rejects the connection or returns 407 without valid credentials, transients stay stale or downloads error.
4. After correcting proxy settings, a forced discovery refresh (§1.6) may be required to populate offers.

#### Reference

| Name | Type | Default | Effect |
|------|------|---------|--------|
| `WP_PROXY_HOST` | string | — | Proxy hostname or IP |
| `WP_PROXY_PORT` | string | — | Proxy port |
| `WP_PROXY_USERNAME` | string | — | Optional proxy user |
| `WP_PROXY_PASSWORD` | string | — | Optional proxy password |
| `WP_PROXY_BYPASS_HOSTS` | string | — | Comma-separated hosts that skip the proxy |

#### Developer resources

- [`WP_PROXY_HOST` and related constants](https://developer.wordpress.org/apis/wp-config-php/#wp_proxy) — `wp-config.php` proxy configuration.

---

### 5.13 WP-CLI: explicit core downgrades

WP-CLI can install a specific WordPress core version, including an older release than the one currently on disk, when invoked with `--version` and `--force`. This bypasses the normal "upgrade only" semantics of the admin interface and carries database and compatibility risks.

#### How it works

This section describes WP-CLI behavior, not a core guarantee. WP-CLI is not bundled with WordPress; the installed version here is WP-CLI 2.12.0. Verify the flags with `wp help core update` on your WP-CLI version.

The `wp core update` command downloads and installs the requested ZIP from WordPress.org (subject to signatures and filesystem checks in the CLI context). The `--force` flag is required when the target version is lower than the installed version so the operator explicitly accepts a downgrade.

Downgrading files does not automatically roll back the database schema. `db_version` in the options table may remain higher than the code in `wp-includes/version.php` expects, or the reverse if the operator runs steps out of order. Operators should run `wp core update-db` after file changes when appropriate, and understand that downgrading across major versions can leave incompatible schema or data. Automatic backups and staging are strongly advised. Plugin and theme compatibility with the older core is not validated by the command.

#### Process flow

1. The operator runs `wp core update --version=X.Y.Z --force`.
2. WP-CLI fetches and extracts core files.
3. File replacement completes.
4. The database version may still reflect the previous upgrade until `wp core update-db` runs or until `wp-admin` loads `wp_upgrade()` for the current code path.
5. A mismatch between files and schema can cause fatal errors or subtle data corruption until reconciled.

#### Reference

| Concern | Downgrade risk |
|---------|----------------|
| File version versus `db_version` | Schema newer than code, or code newer than schema |
| `--force` flag | Required for version regression; implies explicit acceptance |
| Rollback | Not a guaranteed atomic undo; restore from backup if needed |

#### Developer resources

- [`wp core update` CLI command](https://developer.wordpress.org/cli/commands/core/update/) — flags including `--version` and `--force`.
- [`wp core update-db` CLI command](https://developer.wordpress.org/cli/commands/core/update-db/) — database schema upgrade after core file changes.

---

## PART 6 — Hook and Filter Reference

### 6.1 Hook reference overview

Hooks let plugins alter discovery payloads, download behavior, installation steps, automatic decisions, and notifications. This part groups them by phase. Each table gives the hook type, parameters, and the line in the installed core that fires it.

#### How it works

Discovery uses `pre_set_site_transient_*` and related filters. Download and install use `upgrader_pre_download` and the `install_package()` filters. Automatic updates use `auto_update_{$type}`, `pre_auto_update`, and `automatic_updates_complete`. Email and filesystem hooks refine behavior. Cross-reference §6.2 through §6.8 for tables.

#### Process flow

1. WordPress reaches a hook point.
2. Registered callbacks run in priority order.
3. Filters return values; actions perform side effects.
4. Return values may abort steps with `WP_Error`.

#### Reference

| Category | Section |
|----------|---------|
| Discovery | §6.2 |
| Download and install | §6.3 |
| `$hook_extra` shapes | §6.4 |
| Automatic updater | §6.5 |
| Core policy | §6.6 |
| Notifications | §6.7 |
| Cache and filesystem | §6.8 |

#### Developer resources

- [Hooks overview: actions and filters](https://developer.wordpress.org/plugins/hooks/) — introduction to the hook system.

---

### 6.2 Discovery hooks

These hooks alter or observe transient payloads before they persist.

#### How it works

`pre_set_site_transient_update_plugins`, `pre_set_site_transient_update_themes`, and `pre_set_site_transient_update_core` let your code change the value about to be saved. Related `site_transient_update_*` filters apply when WordPress reads transients. Third-party hosts use `update_plugins_{$hostname}` and `update_themes_{$hostname}` (§1.8).

#### Process flow

1. A check function builds a payload.
2. `pre_set_*` filters run.
3. WordPress saves the transient.
4. Readers apply `site_transient_*` filters when loading the transient.

#### Reference

| Hook | Type | Parameters | Notes | Source |
|------|------|------------|-------|--------|
| `pre_set_site_transient_update_plugins` | filter | `mixed $value, string $transient` | Alters plugin update transient before save | `wp-includes/option.php:2654` |
| `pre_set_site_transient_update_themes` | filter | `mixed $value, string $transient` | Alters theme update transient before save | `wp-includes/option.php:2654` |
| `pre_set_site_transient_update_core` | filter | `mixed $value, string $transient` | Alters core update transient before save | `wp-includes/option.php:2654` |
| `site_transient_update_plugins` | filter | `mixed $value, string $transient` | Filters the read value | `wp-includes/option.php:2622` |
| `site_transient_update_themes` | filter | `mixed $value, string $transient` | Filters the read value | `wp-includes/option.php:2622` |
| `site_transient_update_core` | filter | `mixed $value, string $transient` | Filters the read value | `wp-includes/option.php:2622` |
| `update_plugins_{$hostname}` | filter | `false\|array $update, array $plugin_data, string $plugin_file, string[] $locales` | Third-party plugin metadata | `wp-includes/update.php:555` |
| `update_themes_{$hostname}` | filter | `false\|array $update, array $theme_data, string $theme_stylesheet, string[] $locales` | Third-party theme metadata | `wp-includes/update.php:833` |
| `core_version_check_locale` | filter | `string $locale` | Locale for the core version check | `wp-includes/update.php:73` |
| `core_version_check_query_args` | filter | `array $query` | Query arguments sent to the core endpoint | `wp-includes/update.php:209` |
| `plugins_update_check_locales` | filter | `string[] $locales` | Locales requested for plugin translations | `wp-includes/update.php:439` |
| `themes_update_check_locales` | filter | `string[] $locales` | Locales requested for theme translations | `wp-includes/update.php:726` |
| `wp_get_update_data` | filter | `array $update_data, array $titles` | Update counts and titles | `wp-includes/update.php:1021` |

#### Developer resources

- [`pre_set_site_transient_{$transient}` filter reference](https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/) — dynamic hook family.

---

### 6.3 Download and installation hooks (`upgrader_pre_download` and `install_package()` hooks)

`upgrader_pre_download` runs before HTTP download. Four filters run inside `install_package()` around file operations.

#### How it works

`upgrader_pre_download` fires in `WP_Upgrader::download_package()` before `download_url()`. Return a local filesystem path string to substitute the remote download entirely, false to proceed normally, or `WP_Error` to abort. If the filter returns `WP_Error`, `install_package()` hooks do not run.

Inside `install_package()`, `upgrader_pre_install` runs before file operations; `upgrader_source_selection` adjusts the unpacked path; `upgrader_clear_destination` gates clearing; `upgrader_post_install` runs after files are in place (plugin reactivation happens in the plugin upgrader path). Returning `WP_Error` from applicable filters aborts the step.

#### Process flow

1. The download step evaluates `upgrader_pre_download`.
2. Unpack runs.
3. `upgrader_pre_install` executes.
4. `upgrader_source_selection` adjusts the source path.
5. `upgrader_clear_destination` gates the destination clear.
6. `upgrader_post_install` executes.
7. The `upgrader_process_complete` action fires after processing (see §6.4 for the payload shape).

#### Reference

| Hook | Type | Parameters | Notes | Source |
|------|------|------------|-------|--------|
| `upgrader_pre_download` | filter | `false\|string\|WP_Error $reply, string $package, WP_Upgrader $upgrader, array $hook_extra` | Before `download_url()`; local path skips download; `WP_Error` aborts | `wp-admin/includes/class-wp-upgrader.php:322` |
| `upgrader_package_options` | filter | `array $options` | Before filesystem work in `run()` | `wp-admin/includes/class-wp-upgrader.php:849` |
| `upgrader_pre_install` | filter | `bool\|WP_Error $response, array $hook_extra` | Before file operations in `install_package()` | `wp-admin/includes/class-wp-upgrader.php:587` |
| `upgrader_source_selection` | filter | `string\|WP_Error $source, string $remote_source, WP_Upgrader $upgrader, array $hook_extra` | After unzip, before move | `wp-admin/includes/class-wp-upgrader.php:632` |
| `upgrader_clear_destination` | filter | `true\|WP_Error $removed, string $local_destination, string $remote_destination, array $hook_extra` | Before destination cleared | `wp-admin/includes/class-wp-upgrader.php:694` |
| `upgrader_post_install` | filter | `bool $response, array $hook_extra, array $result` | After files in place | `wp-admin/includes/class-wp-upgrader.php:761` |
| `upgrader_install_package_result` | filter | `array\|WP_Error $result, array $hook_extra` | After `install_package()` | `wp-admin/includes/class-wp-upgrader.php:948` |
| `upgrader_process_complete` | action | `WP_Upgrader $upgrader, array $hook_extra` | After processing | `wp-admin/includes/class-wp-upgrader.php:1019` |
| `upgrader_overwrote_package` | action | `string $package, array $new_plugin_data, string $type` | After overwriting a plugin or theme package | `wp-admin/includes/class-plugin-upgrader.php:169`; `wp-admin/includes/class-theme-upgrader.php:274` |
| `update_feedback` | filter | `string $feedback` | Core upgrade progress text | `wp-admin/includes/class-core-upgrader.php:137` |
| `install_plugin_overwrite_comparison` | filter | `string $table, array $current_plugin_data, array $new_plugin_data` | Overwrite comparison table | `wp-admin/includes/class-plugin-installer-skin.php:260` |
| `install_plugin_overwrite_actions` | filter | `array $install_actions, object $api, array $new_plugin_data` | Overwrite action links | `wp-admin/includes/class-plugin-installer-skin.php:343` |
| `install_theme_overwrite_comparison` | filter | `string $table, array $current_theme_data, array $new_theme_data` | Overwrite comparison table | `wp-admin/includes/class-theme-installer-skin.php:305` |
| `install_theme_overwrite_actions` | filter | `array $install_actions, object $api, array $new_theme_data` | Overwrite action links | `wp-admin/includes/class-theme-installer-skin.php:388` |

#### Developer resources

- [`upgrader_pre_download` filter reference](https://developer.wordpress.org/reference/hooks/upgrader_pre_download/) — download short-circuit.
- [`upgrader_process_complete` action reference](https://developer.wordpress.org/reference/hooks/upgrader_process_complete/) — fires after package processing.

---

### 6.4 `$hook_extra` structure reference

`$hook_extra` carries context for upgrader hooks. Its shape differs between `install_package()` filters and `upgrader_process_complete`.

#### How it works

The array is not identical on every hook. Verify the structure on your WordPress version.

`upgrader_pre_download` receives the same `$hook_extra` fourth parameter as in `download_package()`. For `install_package()` filters (`upgrader_pre_install`, `upgrader_source_selection`, `upgrader_clear_destination`, `upgrader_post_install`), keys include `action` (`install` or `update`), `type` (`plugin`, `theme`, `core`, or `translation`), `plugin` basename or `theme` slug when applicable, and `bulk` set to true when inside `bulk_upgrade()`.

The `upgrader_process_complete` action receives `$hook_extra` with `action`, `type`, and — for bulk plugin or theme runs — plural keys `plugins` or `themes` as arrays listing basenames or slugs processed, plus `bulk` when applicable. Singular `plugin` and `theme` keys apply to the current item inside `install_package()`; plural keys apply to batch context on `upgrader_process_complete`.

On the `$upgrader` instance passed to `upgrader_process_complete`, `$upgrader->result` holds the return value of the last `install_package()` call (`true` or `WP_Error`).

#### Process flow

1. The caller builds `$hook_extra`.
2. Each hook receives the array.
3. Consumers read keys appropriate to single versus batch context.

#### Reference

**Inside `install_package()` filters:**

| Key | Present for | Value | Source |
|-----|-------------|-------|--------|
| `action` | All | `install` or `update` | `wp-admin/includes/class-wp-upgrader.php:541-550` |
| `type` | All | `plugin`, `theme`, `core`, or `translation` | `wp-admin/includes/class-plugin-upgrader.php:230`; `class-theme-upgrader.php:334`; `class-core-upgrader.php:221`; `class-language-pack-upgrader.php:250` |
| `plugin` | Plugin updates and installs | Plugin basename | `wp-admin/includes/class-plugin-upgrader.php:230` |
| `theme` | Theme updates and installs | Theme slug | `wp-admin/includes/class-theme-upgrader.php:334` |
| `bulk` | Bulk operations | `true` inside `bulk_upgrade()` | `wp-admin/includes/class-plugin-upgrader.php:385` |
| `temp_backup` | Plugin or theme updates | Array with `slug`, `src`, `dir` | `wp-admin/includes/class-wp-upgrader.php:638`; `class-plugin-upgrader.php:230`; `class-theme-upgrader.php:334` |

**`upgrader_process_complete` action:**

| Key | Present for | Value | Source |
|-----|-------------|-------|--------|
| `action` | All | `update` or `install` | `wp-admin/includes/class-wp-upgrader.php:1019` |
| `type` | All | `plugin`, `theme`, `core`, or `translation` | `wp-admin/includes/class-plugin-upgrader.php:412-421`; `class-theme-upgrader.php:510-519`; `class-language-pack-upgrader.php:280-289` |
| `plugins` | Bulk plugin updates | Array of basenames | `wp-admin/includes/class-plugin-upgrader.php:412-421` |
| `themes` | Bulk theme updates | Array of slugs | `wp-admin/includes/class-theme-upgrader.php:510-519` |
| `translations` | Bulk translation updates | Array of translation results | `wp-admin/includes/class-language-pack-upgrader.php:280-289` |
| `bulk` | Bulk operations | `true` inside `bulk_upgrade()` | `wp-admin/includes/class-plugin-upgrader.php:412-421` |

#### Developer resources

- [`upgrader_process_complete` action reference](https://developer.wordpress.org/reference/hooks/upgrader_process_complete/) — action signature and parameters.

---

### 6.5 Automatic updater hooks

These hooks surround `WP_Automatic_Updater` decisions and completion.

#### How it works

`pre_auto_update` is an action that runs once per item inside `WP_Automatic_Updater::update()`, not once per batch. Arguments are the type string (`core`, `plugin`, `theme`, or `translation`), the offer object, and the filesystem path used for capability checks. It cannot cancel an update; use `auto_update_{$type}` filters to skip items.

`automatic_updates_complete` passes `$update_results` (§4.7).

For core offers, the per-offer filter is `auto_update_core` (the `auto_update_{$type}` hook with type `core`). It runs inside `should_update()` after the initial decision from `Core_Upgrader::should_update_to_version()` (branch policy, `WP_AUTO_UPDATE_CORE`, site options `auto_update_core_*`, and `allow_*_auto_core_updates`).

Core implements branch policy in `Core_Upgrader::should_update_to_version()`; it does not rely on a filter named `wp_auto_update_core` (that string is not a core hook). The site option `auto_update_core_major` (WordPress 5.6+ interface for major updates) is a stored preference — do not confuse it with the `auto_update_core` filter or the `WP_AUTO_UPDATE_CORE` constant (§5.5, §5.10, §3.7).

#### Process flow

1. Before each automatic item, `pre_auto_update` fires.
2. `should_update()` evaluates `auto_update_{$type}`.
3. The update runs or is skipped.
4. After the batch, `automatic_updates_complete` fires if results exist.

#### Reference

| Hook | Type | Parameters | Notes | Source |
|------|------|------------|-------|--------|
| `pre_auto_update` | action | `string $type, object $item, string $context` | Per item; no return value | `wp-admin/includes/class-wp-automatic-updater.php:401` |
| `auto_update_{$type}` | filter | `bool\|null $update, object $item` | Per-offer allow or deny; includes `auto_update_core` for core | `wp-admin/includes/class-wp-automatic-updater.php:268` |
| `automatic_updates_is_vcs_checkout` | filter | `bool $checkout, string $context` | Override VCS detection | `wp-admin/includes/class-wp-automatic-updater.php:178` |
| `plugins_auto_update_enabled` | filter | `bool $enabled` | Per-type UI enablement | `wp-admin/includes/update.php:1059` |
| `themes_auto_update_enabled` | filter | `bool $enabled` | Per-type UI enablement | `wp-admin/includes/update.php:1068` |
| `automatic_updates_complete` | action | `array $update_results` | Results payload (§4.7) | `wp-admin/includes/class-wp-automatic-updater.php:783` |

#### Developer resources

- [`pre_auto_update` action reference](https://developer.wordpress.org/reference/hooks/pre_auto_update/) — per-item action.

---

### 6.6 Core auto-update policy hooks

Branch policy uses `allow_*` filters, distinct from the per-offer `auto_update_core` filter, the `auto_update_core_major` site option (major interface), and other `auto_update_core_*` options.

#### How it works

Inside `Core_Upgrader::should_update_to_version()`, `allow_dev_auto_core_updates`, `allow_minor_auto_core_updates`, and `allow_major_auto_core_updates` each receive the boolean derived from `WP_AUTO_UPDATE_CORE` or the `auto_update_core_*` options before returning. They run before `auto_update_core` inside `should_update()` for core offers. See §3.7 for the full order.

#### Process flow

1. Core determines the branch (development, minor, or major).
2. The matching `allow_*` filter runs.
3. The result feeds `should_update()` together with `auto_update_core`.

#### Reference

| Hook | Type | Parameters | Notes | Source |
|------|------|------------|-------|--------|
| `allow_dev_auto_core_updates` | filter | `bool $upgrade_dev` | Development builds only | `wp-admin/includes/class-core-upgrader.php:357` |
| `allow_minor_auto_core_updates` | filter | `bool $upgrade_minor` | Same x.y branch | `wp-admin/includes/class-core-upgrader.php:373` |
| `allow_major_auto_core_updates` | filter | `bool $upgrade_major` | Major branch jumps | `wp-admin/includes/class-core-upgrader.php:386` |
| `after_core_auto_updates_settings` | action | `array $auto_update_settings` | After the major core settings; receives `dev`, `minor`, `major` | `wp-admin/update-core.php:454` |
| `core_upgrade_preamble` | action | none | Before the core upgrade list | `wp-admin/update-core.php:1139` |
| `update-core-custom-{$action}` | action | none | Custom core update action | `wp-admin/update-core.php:1331` |

#### Developer resources

- [`allow_major_auto_core_updates` filter reference](https://developer.wordpress.org/reference/hooks/allow_major_auto_core_updates/) — major branch filter.

---

### 6.7 Notification and email hooks

Email hooks adjust whether messages send and what they contain. Interface filters customize auto-update settings presentation.

#### How it works

Core email hooks: `send_core_update_notification_email`, `auto_core_update_send_email`, and `auto_core_update_email`. Plugin and theme batch email hooks: `auto_plugin_update_send_email`, `auto_theme_update_send_email`, and `auto_plugin_theme_update_email`. Debug hooks: `automatic_updates_send_debug_email` and `automatic_updates_debug_email`. Interface hooks: `plugin_auto_update_setting_html`, `theme_auto_update_setting_template`, `theme_auto_update_setting_html`, and `update_right_now_text` (see §5.4 and §5.5). Update-message actions: `in_plugin_update_message-{$file}` and `in_theme_update_message-{$theme_key}`. The `after_core_auto_updates_settings` action belongs to §6.6.

#### Process flow

1. The updater decides to send email.
2. Gate filters run.
3. Content filters adjust arrays before `wp_mail()` sends.

#### Reference

| Hook | Type | Parameters | Notes | Source |
|------|------|------------|-------|--------|
| `send_core_update_notification_email` | filter | `bool $notify, object $item` | Manual core update notification | `wp-admin/includes/class-wp-automatic-updater.php:345` |
| `auto_core_update_send_email` | filter | `bool $send, string $type, object $core_update, mixed $result` | After background core update | `wp-admin/includes/class-wp-automatic-updater.php:935` |
| `auto_core_update_email` | filter | `array $email, string $type, object $core_update, mixed $result` | Alters core email fields | `wp-admin/includes/class-wp-automatic-updater.php:1147` |
| `auto_plugin_update_send_email` | filter | `bool $enabled, array $update_results` | Plugin batch email gate | `wp-admin/includes/class-wp-automatic-updater.php:1177` |
| `auto_theme_update_send_email` | filter | `bool $enabled, array $update_results` | Theme batch email gate | `wp-admin/includes/class-wp-automatic-updater.php:1200` |
| `auto_plugin_theme_update_email` | filter | `array $email, string $type, array $successful_updates, array $failed_updates` | Alters plugin and theme batch email | `wp-admin/includes/class-wp-automatic-updater.php:1541` |
| `automatic_updates_send_debug_email` | filter | `bool $development_version` | Debug email enable | `wp-admin/includes/class-wp-automatic-updater.php:766` |
| `automatic_updates_debug_email` | filter | `array $email, array $failures, array $update_results` | Debug email payload | `wp-admin/includes/class-wp-automatic-updater.php:1741` |
| `plugin_auto_update_setting_html` | filter | `string $html, string $plugin_file, array $plugin_data` | Installed Plugins control HTML | `wp-admin/includes/class-wp-plugins-list-table.php:1457` |
| `theme_auto_update_setting_template` | filter | `string $template` | Theme details modal template | `wp-admin/themes.php:829` |
| `theme_auto_update_setting_html` | filter | `string $html, string $stylesheet, WP_Theme $theme` | Network themes control HTML | `wp-admin/includes/class-wp-ms-themes-list-table.php:887` |
| `in_plugin_update_message-{$file}` | action | `array $plugin_data, object $response` | Per-plugin update message | `wp-admin/includes/update.php:614` |
| `in_theme_update_message-{$theme_key}` | action | `WP_Theme $theme, object $response` | Per-theme update message | `wp-admin/includes/update.php:837` |
| `update_right_now_text` | filter | `string $content` | At a Glance version text | `wp-admin/includes/update.php:377` |

#### Developer resources

- [`auto_core_update_email` filter reference](https://developer.wordpress.org/reference/hooks/auto_core_update_email/) — core email filter.

---

### 6.8 Cache and filesystem hooks

Filesystem and modification gates interact with `WP_Filesystem` and updateability checks.

#### How it works

`file_mod_allowed` filters whether file modifications are permitted for a context. `automatic_updater_disabled` disables the automatic updater. The constants `DISALLOW_FILE_MODS` and `AUTOMATIC_UPDATER_DISABLED` participate before or alongside these hooks depending on the call site. `filesystem_method` and `request_filesystem_credentials` filters adjust how credentials are obtained for interactive flows.

#### Process flow

1. The upgrader asks whether modifications are allowed.
2. Filters and constants combine to produce the answer.
3. The Filesystem API selects the transport.
4. Background paths require non-interactive success.

#### Reference

| Hook | Type | Parameters | Notes | Source |
|------|------|------------|-------|--------|
| `file_mod_allowed` | filter | `bool $file_mod_allowed, string $context` | Gates file changes by context | `wp-includes/load.php:1838` |
| `automatic_updater_disabled` | filter | `bool $disabled` | Disables the automatic updater | `wp-admin/includes/class-wp-automatic-updater.php:58` |
| `filesystem_method` | filter | `string $method, array $args, string $context, bool $allow_relaxed_file_ownership` | Chooses the filesystem method | `wp-admin/includes/file.php:2342` |
| `request_filesystem_credentials` | filter | `mixed $req_cred, string $form_post, string\|false $type, bool\|WP_Error $error, string $context, array\|null $extra_fields, bool $allow_relaxed_file_ownership` | Credential form and return values | `wp-admin/includes/file.php:2400` |
| `filesystem_method_file` | filter | `string $abstraction_file, string $method` | Filesystem class file | `wp-admin/includes/file.php:2203` |
| `fs_ftp_connection_types` | filter | `array $types, array $credentials, string $type, bool\|WP_Error $error, string $context` | FTP and SSH connection types | `wp-admin/includes/file.php:2554` |
| `wp_opcache_invalidate_file` | filter | `bool $should_invalidate, string $filepath` | OPcache invalidation | `wp-admin/includes/file.php:2773` |
| `unzip_file_use_ziparchive` | filter | `bool $use_ziparchive` | Use ZipArchive for extraction | `wp-admin/includes/file.php:1578` |
| `pre_unzip_file` | filter | `null\|bool $pre, string $file, string $to, bool $needed_dirs, int $required_space` | Short-circuit extraction | `wp-admin/includes/file.php:1797` |
| `unzip_file` | filter | `bool $result, string $file, string $to, bool $needed_dirs, int $required_space` | Filter the extraction result | `wp-admin/includes/file.php:1853` |
| `wp_signature_hosts` | filter | `array $signed_hostnames` | Hosts whose downloads are signature-checked | `wp-admin/includes/file.php:1287` |
| `wp_signature_url` | filter | `string $signature_url, string $url` | Signature URL for a download | `wp-admin/includes/file.php:1316` |
| `wp_signature_softfail` | filter | `bool $signature_softfail, string $url` | Allow a signature failure to soft-fail | `wp-admin/includes/file.php:1348` |
| `wp_trusted_keys` | filter | `array $trusted_keys` | Trusted public keys | `wp-admin/includes/file.php:1562` |
| `https_local_ssl_verify` | filter | `bool $sslverify, string $url` | SSL verification for loopback requests | `wp-admin/includes/class-wp-automatic-updater.php:1806`; `wp-admin/includes/file.php:586` |
| `enable_maintenance_mode` | filter | `bool $enable, int\|false $upgrading` | Veto maintenance mode | `wp-includes/load.php:474` |

#### Developer resources

- [`file_mod_allowed` filter reference](https://developer.wordpress.org/reference/hooks/file_mod_allowed/) — filter file modification permission.

---

## PART 7 — File and Class Reference

### 7.1 Core files involved in the update system

These paths are relative to the WordPress installation root. The role column summarizes responsibilities.

#### How it works

Update logic spans `wp-includes` and `wp-admin/includes`. Cron and transient management live in `wp-includes/update.php`. Admin-only helpers and checksum functions live under `wp-admin/includes`. Interface screens live under `wp-admin`.

Interactive core upgrades are driven from `wp-admin/update-core.php` (the screen and `do_core_upgrade()`), while the low-level copy and rename routines live in `wp-admin/includes/update-core.php` (required during a core upgrade after the new package is unpacked).

#### Process flow

1. Load order depends on context: front end, admin, AJAX, or CLI.
2. Admin includes load before upgraders run.
3. Plugin and theme install, update, and delete operations often use AJAX handlers in `ajax-actions.php`.
4. Interactive core upgrade and reinstall use the `update-core.php` screen and includes file, not the AJAX entry points.

#### Reference

| Relative path | Role |
|---------------|------|
| `wp-includes/update.php` | Version and package checks, `wp_maybe_auto_update()`, cron hooks, `wp_get_update_data()` |
| `wp-admin/includes/update.php` | `get_core_updates()`, `find_core_auto_update()`, dismiss helpers, `update_nag()`, `get_core_checksums()` |
| `wp-admin/includes/upgrade.php` | `wp_upgrade()`, `dbDelta()`, `upgrade_all()`, schema migration |
| `wp-admin/includes/admin-filters.php` | Hooks `update_nag()` to admin notices |
| `wp-admin/includes/class-wp-upgrader-skin.php` | Base skin API |
| `wp-admin/includes/class-automatic-upgrader-skin.php` | Background and AJAX message capture |
| `wp-admin/includes/class-wp-ajax-upgrader-skin.php` | AJAX errors and messages |
| `wp-admin/includes/class-bulk-upgrader-skin.php` | Bulk progress interface |
| `wp-admin/includes/class-wp-upgrader.php` | Base upgrader, `upgrader_process_complete` |
| `wp-admin/includes/class-wp-automatic-updater.php` | Background update decisions and execution |
| `wp-admin/includes/class-plugin-upgrader.php` | Plugin upgrade and install |
| `wp-admin/includes/class-theme-upgrader.php` | Theme upgrade and install |
| `wp-admin/includes/class-core-upgrader.php` | Core upgrade; `Core_Upgrader::check_files()` compares installed files to `get_core_checksums()` |
| `wp-admin/includes/update-core.php` | Core upgrade routines (notably `update_core()`); required after unpacking (distinct from the `update-core.php` admin screen) |
| `wp-admin/includes/class-language-pack-upgrader.php` | Translation packages |
| `wp-admin/includes/file.php` | `WP_Filesystem`, `request_filesystem_credentials()`, `verify_file_signature()`, `wp_trusted_keys()`, `FS_METHOD` integration |
| `wp-admin/update-core.php` | Core updates admin interface; `do_core_upgrade()`, reinstall |
| `wp-admin/includes/ajax-actions.php` | AJAX handlers for plugin, theme, and core install, update, and delete (such as `wp_ajax_update_plugin`); not the interactive core upgrade path |
| `wp-includes/load.php` | `wp_is_file_mod_allowed()` |
| `wp-includes/class-wp-plugin-dependencies.php` | `WP_Plugin_Dependencies` (dependency graph for plugin list, activation, and related AJAX) |
| `wp-admin/includes/class-wp-site-health.php` | `WP_Site_Health` diagnostics |
| `wp-admin/includes/class-wp-site-health-auto-updates.php` | `WP_Site_Health_Auto_Updates` background-update tests |
| `wp-admin/includes/class-plugin-installer-skin.php` | `Plugin_Installer_Skin` install and overwrite screen |
| `wp-admin/includes/class-theme-installer-skin.php` | `Theme_Installer_Skin` install and overwrite screen |
| `wp-admin/includes/class-language-pack-upgrader-skin.php` | `Language_Pack_Upgrader_Skin` translation update screen |
| `wp-admin/includes/class-bulk-plugin-upgrader-skin.php` | `Bulk_Plugin_Upgrader_Skin` bulk plugin progress |
| `wp-admin/includes/class-bulk-theme-upgrader-skin.php` | `Bulk_Theme_Upgrader_Skin` bulk theme progress |
| `wp-admin/includes/class-file-upload-upgrader.php` | `File_Upload_Upgrader` for uploaded packages |
| `wp-includes/class-wp-recovery-mode-email-service.php` | `WP_Recovery_Mode_Email_Service` recovery email |
| `wp-includes/class-wp-recovery-mode.php` | `WP_Recovery_Mode` |

#### Developer resources

- [Code reference](https://developer.wordpress.org/reference/) — look up each file's functions and classes.

---

### 7.2 Key classes

Classes partition download, install, automatic decisions, skins, and recovery into distinct responsibilities.

#### How it works

`WP_Upgrader` subclasses specialize behavior per package type. `WP_Automatic_Updater` wraps scheduled application. Skins subclass `WP_Upgrader_Skin`. `WP_Site_Health_Auto_Updates` runs the background-update tests behind the Site Health screen. `WP_Recovery_Mode` handles fatal recovery separately from updates, and `WP_Recovery_Mode_Email_Service` sends the recovery email.

#### Process flow

1. The entry point selects a class through instantiation.
2. Methods call parent hooks.
3. Results propagate to skins or email layers.

#### Reference

| Class | File | Extends | Role |
|-------|------|---------|------|
| `WP_Upgrader` | `class-wp-upgrader.php` | — | Base download and install |
| `Core_Upgrader` | `class-core-upgrader.php` | `WP_Upgrader` | Core file replacement |
| `Plugin_Upgrader` | `class-plugin-upgrader.php` | `WP_Upgrader` | Plugin updates and installs |
| `Theme_Upgrader` | `class-theme-upgrader.php` | `WP_Upgrader` | Theme updates and installs |
| `Language_Pack_Upgrader` | `class-language-pack-upgrader.php` | `WP_Upgrader` | Translation packages |
| `WP_Automatic_Updater` | `class-wp-automatic-updater.php` | — | Background update decisions and execution |
| `WP_Site_Health_Auto_Updates` | `class-wp-site-health-auto-updates.php` | — | Background-update tests behind Site Health |
| `WP_Upgrader_Skin` | `class-wp-upgrader-skin.php` | — | Base skin |
| `Plugin_Installer_Skin` | `class-plugin-installer-skin.php` | `WP_Upgrader_Skin` | Plugin install and overwrite screen |
| `Theme_Installer_Skin` | `class-theme-installer-skin.php` | `WP_Upgrader_Skin` | Theme install and overwrite screen |
| `Language_Pack_Upgrader_Skin` | `class-language-pack-upgrader-skin.php` | `WP_Upgrader_Skin` | Translation update screen |
| `Bulk_Plugin_Upgrader_Skin` | `class-bulk-plugin-upgrader-skin.php` | `Bulk_Upgrader_Skin` | Bulk plugin progress |
| `Bulk_Theme_Upgrader_Skin` | `class-bulk-theme-upgrader-skin.php` | `Bulk_Upgrader_Skin` | Bulk theme progress |
| `File_Upload_Upgrader` | `class-file-upload-upgrader.php` | — | Uploaded package handling |
| `WP_Recovery_Mode` | `class-wp-recovery-mode.php` | — | Fatal error recovery |
| `WP_Recovery_Mode_Email_Service` | `class-wp-recovery-mode-email-service.php` | — | Recovery email composition and send |

#### Developer resources

- [`WP_Automatic_Updater` class reference](https://developer.wordpress.org/reference/classes/wp_automatic_updater/) — automatic updater class.

---
