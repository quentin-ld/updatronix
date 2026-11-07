# Updatronix developer API

[Français](fr.md) · [Documentation index](../README.md) · [Updatronix documentation](../updatronix/en.md) · [Updatronix 3000 documentation](../updatronix-pro/en.md)

| Field | Value |
|---|---|
| Free plugin | 2.0.1 |
| Pro plugin | 0.8.1 |
| WordPress | 6.2 or later (verified on 7.1.4) |
| PHP | 8.1 or later |
| Last verified | 2026-11-07 |
| Status | verified |

This guide is for code that integrates Updatronix: actions, filters, REST
routes, the public PHP surface, and the stored data model. It covers both
the Free plugin and Updatronix 3000 (Pro).

The supported integration surface is hooks, filters, and REST routes. A PHP
class that this guide does not list under Public PHP API is internal. Do not call
an internal method as a stable API.

## Scope

- Free actions and filters, Pro actions and filters, and the two filters both
  plugins share.
- The `updatronix/v1` and `updatronix-pro/v1` REST namespaces.
- The stable PHP symbols: `updatronix_get_admin_tabs()` and
  `Updatronix_External_Log`.
- The log table, options, transients, retention rule, and schema version that
  an integration may read.
- Not covered: the admin UI, internal classes, the queue that Pro removed, the
  digest email that Pro removed, and any code change.

The hook count is 62 Updatronix entries: nine Free actions, 29 Free-only filters,
22 Pro-only filters, and two shared filters. The core `wp_maybe_auto_update` hook
is a note, not an entry; counting it gives the 63 rows the task brief names.

## Actions

Actions notify your code after an event. The callback receives the arguments in
the signature below. An action returns nothing.

### Free

#### `updatronix_admin_page_content`

- **Signature:** `do_action( 'updatronix_admin_page_content', string $active_tab )`
- **Parameters:** `$active_tab` is the slug of the current admin tab.
- **@since:** 1.1.1
- **Source:** `inc/admin/menu.php:169`
```php
add_action( 'updatronix_admin_page_content', function ( string $active_tab ) {
	if ( 'logs' === $active_tab ) {
		echo '<p>Your panel.</p>';
	}
} );
```

#### `updatronix_after_export_logs`

- **Signature:** `do_action( 'updatronix_after_export_logs', array $data )`
- **Parameters:** `$data` contains `user_id`, `site_id`, `row_count`,
  `merged_count`, `truncated`, `truncation_reason`, `columns`, `merge`,
  `filters_fingerprint`, `generated_at`, `locale`, `cursor_count`, and `version`.
- **@since:** 1.1.0
- **Source:** `inc/classes/class-updatronix-export.php:800`
```php
add_action( 'updatronix_after_export_logs', function ( array $data ) {
	error_log( 'Exported ' . (int) $data['row_count'] . ' rows.' );
} );
```

#### `updatronix_after_log`

- **Signature:** `do_action( 'updatronix_after_log', int $log_id, array $data )`
- **Parameters:** `$log_id` is the new row ID; `$data` is the stored snapshot.
  The original 12 keys are always present; the decision and identity keys follow
  them.
- **@since:** 1.0.0; 1.6.0 added the decision and identity keys.
- **Source:** `inc/classes/class-updatronix-logger.php:563`
```php
add_action( 'updatronix_after_log', function ( int $log_id, array $data ) {
	// Mirror the row, notify an external service, or update a dashboard.
}, 10, 2 );
```

#### `updatronix_after_save_network_schedule`

- **Signature:** `do_action( 'updatronix_after_save_network_schedule' )`
- **Parameters:** none.
- **@since:** 1.1.0
- **Source:** `inc/settings/options.php:549`
```php
add_action( 'updatronix_after_save_network_schedule', function () {
	// The Schedule recurrence, time, or delay window changed.
} );
```

#### `updatronix_after_save_settings`

- **Signature:** `do_action( 'updatronix_after_save_settings' )`
- **Parameters:** none.
- **@since:** since not declared
- **Source:** `inc/settings/options.php:629`, `:645`
```php
add_action( 'updatronix_after_save_settings', function () {
	// A settings write completed. The Schedule subtree has its own action.
} );
```

#### `updatronix_before_update_check`

- **Signature:** `do_action( 'updatronix_before_update_check' )`
- **Parameters:** none.
- **@since:** since not declared
- **Source:** `inc/classes/class-updatronix-cron.php:391`
```php
add_action( 'updatronix_before_update_check', function () {
	// Prime your own update data before WordPress runs the check.
} );
```

#### `updatronix_enqueue_admin_assets`

- **Signature:** `do_action( 'updatronix_enqueue_admin_assets', string $admin_page )`
- **Parameters:** `$admin_page` is the current admin page hook suffix.
- **@since:** 1.1.1
- **Source:** `inc/admin/enqueue.php:374`
```php
add_action( 'updatronix_enqueue_admin_assets', function ( string $admin_page ) {
	wp_enqueue_script( 'my-updatronix-panel', plugin_dir_url( __FILE__ ) . 'panel.js', array( 'updatronix-scripts' ), '1.0.0', true );
} );
```

#### `updatronix_external_update_logged`

- **Signature:** `do_action( 'updatronix_external_update_logged', int $log_id, array $args )`
- **Parameters:** `$log_id` is the stored row ID; `$args` is the normalized
  external update payload.
- **@since:** 1.4.0
- **Source:** `inc/classes/class-updatronix-external-log.php:224`
```php
add_action( 'updatronix_external_update_logged', function ( int $log_id, array $args ) {
	// Notify a deployment dashboard that the external update was recorded.
}, 10, 2 );
```

#### `updatronix_log_updated`

- **Signature:** `do_action( 'updatronix_log_updated', int $log_id, array $payload )`
- **Parameters:** `$log_id` is the row ID; `$payload` contains the updated
  columns, with `context` decoded.
- **@since:** 1.6.0
- **Source:** `inc/classes/class-updatronix-logger.php:879`
```php
add_action( 'updatronix_log_updated', function ( int $log_id, array $payload ) {
	// Refresh your mirror with the enriched decision context.
}, 10, 2 );
```

### Pro

The Pro plugin registers no actions.

### Shared

The two plugins share no action.

### Core hook triggered by Pro

`wp_maybe_auto_update` is a WordPress core action. Pro triggers it from
`../updatronix-pro/inc/update-engine/class-updatronix-update-pass-runner.php:89`,
and Safe mode listens to it from
`../updatronix-pro/inc/update-shield/class-updatronix-safe-mode.php:179`. It is
not an Updatronix action and has no Updatronix signature or `@since`. For the
core contract, see the WordPress developer reference.

## Filters

Filters receive a value, may change it, and must return it. The value shape and
the return contract are listed for every entry. A filter that returns
`null` where the plugin expects an array is ignored or falls back to the
original value; return the same type.

### Free

#### `updatronix_admin_brand`

- **Signature:** `apply_filters( 'updatronix_admin_brand', array $brand )`
- **Value:** `array{name: string, menu_label: string, logoUrl: string, color: string, menuColor: string, replace: bool}`
- **Returns:** the brand array.
- **@since:** since not declared
- **Source:** `inc/admin/enqueue.php:385`
```php
add_filter( 'updatronix_admin_brand', function ( array $brand ) {
	$brand['name'] = 'My update service';
	$brand['replace'] = true;
	return $brand;
} );
```

#### `updatronix_admin_menu_label`

- **Signature:** `apply_filters( 'updatronix_admin_menu_label', string $label )`
- **Value:** the menu label.
- **Returns:** the label string.
- **@since:** 1.0.0; the network-admin call site is 1.1.0.
- **Source:** `inc/admin/menu.php:59`, `:96`
```php
add_filter( 'updatronix_admin_menu_label', function ( string $label ) {
	return 'Update center';
} );
```

#### `updatronix_admin_tabs`

- **Signature:** `apply_filters( 'updatronix_admin_tabs', array $tabs )`
- **Value:** `array<string, array{slug: string, label: string, icon: string, priority?: int}>`
- **Returns:** the tab map; tabs are sorted by `priority` after filtering.
- **@since:** 1.1.1
- **Source:** `inc/core/tabs.php:89`
```php
add_filter( 'updatronix_admin_tabs', function ( array $tabs ) {
	$tabs['my-tab'] = array( 'slug' => 'my-tab', 'label' => 'My tab', 'icon' => '', 'priority' => 50 );
	return $tabs;
} );
```

#### `updatronix_auto_update_can_toggle`

- **Signature:** `apply_filters( 'updatronix_auto_update_can_toggle', bool $can_toggle, string $type, string $id, bool $enable )`
- **Value:** `true` by default. `$type` is `plugin` or `theme`; `$id` is the
  plugin file or theme stylesheet; `$enable` is the requested state.
- **Returns:** `true` to allow, `false` to veto with a 403, or a `WP_Error` whose
  message becomes the 403 message.
- **@since:** since not declared
- **Source:** `inc/classes/class-updatronix-settings.php:1466`, `:1530`
```php
add_filter( 'updatronix_auto_update_can_toggle', function ( $can_toggle, string $type, string $id, bool $enable ) {
	if ( 'plugin' === $type && 'koko-analytics/koko-analytics.php' === $id ) {
		return false;
	}
	return $can_toggle;
}, 10, 4 );
```

#### `updatronix_auto_update_delay_seconds`

- **Signature:** `apply_filters( 'updatronix_auto_update_delay_seconds', int $delay_seconds, string $type, object $item )`
- **Value:** the soak window in seconds from the Schedule settings.
- **Returns:** the delay in seconds; a value of zero or less allows the update
  immediately.
- **@since:** 1.1.0
- **Source:** `inc/classes/class-updatronix-autoupdatedelay.php:343`
```php
add_filter( 'updatronix_auto_update_delay_seconds', function ( int $delay_seconds, string $type, object $item ) {
	return 3600; // One hour for every offer.
}, 10, 3 );
```

#### `updatronix_auto_update_plugin_data`

- **Signature:** `apply_filters( 'updatronix_auto_update_plugin_data', array $plugin_data, string $file )`
- **Value:** the plugin row the auto-update panel renders.
- **Returns:** the row array.
- **@since:** since not declared
- **Source:** `inc/classes/class-updatronix-autoupdates.php:342`
```php
add_filter( 'updatronix_auto_update_plugin_data', function ( array $plugin_data, string $file ) {
	$plugin_data['description'] .= ' Managed by our platform.';
	return $plugin_data;
}, 10, 2 );
```

#### `updatronix_auto_update_theme_data`

- **Signature:** `apply_filters( 'updatronix_auto_update_theme_data', array $theme_data, string $stylesheet )`
- **Value:** the theme row the auto-update panel renders.
- **Returns:** the row array.
- **@since:** since not declared
- **Source:** `inc/classes/class-updatronix-autoupdates.php:420`
```php
add_filter( 'updatronix_auto_update_theme_data', function ( array $theme_data, string $stylesheet ) {
	$theme_data['description'] .= ' Managed by our platform.';
	return $theme_data;
}, 10, 2 );
```

#### `updatronix_can_manage_pro`

- **Signature:** `apply_filters( 'updatronix_can_manage_pro', bool $can_manage_pro )`
- **Value:** `false` by default; Pro returns its capability check.
- **Returns:** whether the current user may manage the Pro screens.
- **@since:** since not declared
- **Source:** `inc/admin/enqueue.php:462`
```php
add_filter( 'updatronix_can_manage_pro', function ( bool $can_manage_pro ) {
	return $can_manage_pro || current_user_can( 'manage_options' );
} );
```

#### `updatronix_export_formats`

- **Signature:** `apply_filters( 'updatronix_export_formats', array $formats )`
- **Value:** the registered export formats, keyed by slug.
- **Returns:** the format map. Pro adds `csv`, `json`, and `xml`; Free serves
  `txt`.
- **@since:** 1.3.0
- **Source:** `inc/settings/options.php:846`
```php
add_filter( 'updatronix_export_formats', function ( array $formats ) {
	$formats['jsonl'] = 'JSON Lines';
	return $formats;
} );
```

#### `updatronix_export_max_rows`

- **Signature:** `apply_filters( 'updatronix_export_max_rows', int $max_rows )`
- **Value:** the total row cap for one export.
- **Returns:** the cap as an integer.
- **@since:** since not declared
- **Source:** `inc/classes/class-updatronix-export.php:346`
```php
add_filter( 'updatronix_export_max_rows', function ( int $max_rows ) {
	return 20000;
} );
```

#### `updatronix_export_rate_limit_bypass_cap`

- **Signature:** `apply_filters( 'updatronix_export_rate_limit_bypass_cap', string $capability, int $user_id, int $site_id )`
- **Value:** an empty string, which disables the bypass.
- **Returns:** a capability slug; a user holding it skips the export limiter.
- **@since:** 1.1.0
- **Source:** `inc/classes/class-updatronix-export-rate-limiter.php:140`
```php
add_filter( 'updatronix_export_rate_limit_bypass_cap', function ( string $capability, int $user_id, int $site_id ) {
	return 'export_updatronix_logs';
}, 10, 3 );
```

#### `updatronix_export_rate_limit_per_hour`

- **Signature:** `apply_filters( 'updatronix_export_rate_limit_per_hour', int $limit, int $user_id, int $site_id )`
- **Value:** the default hourly limit.
- **Returns:** the limit, clamped to `[1, 100000]`.
- **@since:** 1.1.0
- **Source:** `inc/classes/class-updatronix-export-rate-limiter.php:85`
```php
add_filter( 'updatronix_export_rate_limit_per_hour', function ( int $limit, int $user_id, int $site_id ) {
	return 500;
}, 10, 3 );
```

#### `updatronix_export_rate_limit_per_minute`

- **Signature:** `apply_filters( 'updatronix_export_rate_limit_per_minute', int $limit, int $user_id, int $site_id )`
- **Value:** the default per-minute limit.
- **Returns:** the limit, clamped to `[1, 100000]`.
- **@since:** 1.1.0
- **Source:** `inc/classes/class-updatronix-export-rate-limiter.php:63`
```php
add_filter( 'updatronix_export_rate_limit_per_minute', function ( int $limit, int $user_id, int $site_id ) {
	return 20;
}, 10, 3 );
```

#### `updatronix_external_update_sources`

- **Signature:** `apply_filters( 'updatronix_external_update_sources', array $sources )`
- **Value:** `ftp`, `sftp`, `ssh`, `cli`, `custom`.
- **Returns:** the accepted source keys.
- **@since:** 1.4.0
- **Source:** `inc/classes/class-updatronix-external-log.php:50`
```php
add_filter( 'updatronix_external_update_sources', function ( array $sources ) {
	$sources[] = 'deploy';
	return $sources;
} );
```

#### `updatronix_filter_should_delay_offer`

- **Signature:** `apply_filters( 'updatronix_filter_should_delay_offer', bool $should_delay, string $type, object $item, int $first_seen, int $delay_seconds )`
- **Value:** the computed delay decision.
- **Returns:** `true` to keep the offer in the soak window, `false` to release it.
- **@since:** 1.1.0
- **Source:** `inc/classes/class-updatronix-autoupdatedelay.php:386`
```php
add_filter( 'updatronix_filter_should_delay_offer', function ( bool $should_delay, string $type, object $item, int $first_seen, int $delay_seconds ) {
	return false; // Release this offer now.
}, 10, 5 );
```

#### `updatronix_health_checks`

- **Signature:** `apply_filters( 'updatronix_health_checks', array $checks, array $failed )`
- **Value:** the Health check rows; `$failed` holds the failed log rows in the
  retention window.
- **Returns:** the check rows. Each row has `id`, `label`, `value`, `status`
  (`good`, `attention`, `critical`), `intent`, `detail`, and optional `href` and
  `link_label`.
- **@since:** 1.5.0
- **Source:** `inc/classes/class-updatronix-health.php:323`
```php
add_filter( 'updatronix_health_checks', function ( array $checks, array $failed ) {
	$checks[] = array(
		'id'     => 'my-check',
		'label'  => 'My check',
		'value'  => 'OK',
		'status' => 'good',
		'intent' => 'inform',
		'detail' => 'Checked by the integration.',
	);
	return $checks;
}, 10, 2 );
```

#### `updatronix_license_active`

- **Signature:** `apply_filters( 'updatronix_license_active', bool $active )`
- **Value:** `false` by default; Pro returns its licence state.
- **Returns:** whether the licence is active.
- **@since:** since not declared
- **Source:** `inc/admin/enqueue.php:458`
```php
add_filter( 'updatronix_license_active', function ( bool $active ) {
	return $active;
} );
```

#### `updatronix_log_action_types`

- **Signature:** `apply_filters( 'updatronix_log_action_types', array $types )`
- **Value:** the action-type allowlist.
- **Returns:** the allowlist. Additive: keep the existing types and append yours.
- **@since:** 1.4.0
- **Source:** `inc/classes/class-updatronix-security.php:164`
```php
add_filter( 'updatronix_log_action_types', function ( array $types ) {
	$types[] = 'deploy';
	return $types;
} );
```

#### `updatronix_log_context`

- **Signature:** `apply_filters( 'updatronix_log_context', array $event, string $log_type, string $item_slug, string $target_version )`
- **Value:** the decision-log event before sanitization.
- **Returns:** the event array. The native logger stores it before insert.
- **@since:** 1.6.0
- **Source:** `inc/classes/class-updatronix-logger.php:303`
```php
add_filter( 'updatronix_log_context', function ( array $event, string $log_type, string $item_slug, string $target_version ) {
	$event['context']['deployment'] = 'blue';
	return $event;
}, 10, 4 );
```

#### `updatronix_log_context_keys`

- **Signature:** `apply_filters( 'updatronix_log_context_keys', array $keys )`
- **Value:** the redacted context allowlist.
- **Returns:** the allowlist. Add a key before storing it in `context`.
- **@since:** 1.6.0
- **Source:** `inc/classes/class-updatronix-security.php:581`
```php
add_filter( 'updatronix_log_context_keys', function ( array $keys ) {
	$keys[] = 'deployment';
	return $keys;
} );
```

#### `updatronix_log_decision_layer_enabled`

- **Signature:** `apply_filters( 'updatronix_log_decision_layer_enabled', bool $enabled )`
- **Value:** whether the decision-layer writer is active.
- **Returns:** the boolean.
- **@since:** 1.6.0
- **Source:** `inc/classes/class-updatronix-logger.php:233`
```php
add_filter( 'updatronix_log_decision_layer_enabled', function ( bool $enabled ) {
	return true;
} );
```

#### `updatronix_log_decision_states`

- **Signature:** `apply_filters( 'updatronix_log_decision_states', array $states )`
- **Value:** the decision-state allowlist.
- **Returns:** the allowlist.
- **@since:** 1.6.0
- **Source:** `inc/classes/class-updatronix-security.php:322`
```php
add_filter( 'updatronix_log_decision_states', function ( array $states ) {
	$states[] = 'deferred';
	return $states;
} );
```

#### `updatronix_log_origins`

- **Signature:** `apply_filters( 'updatronix_log_origins', array $origins )`
- **Value:** the origin allowlist.
- **Returns:** the allowlist.
- **@since:** 1.6.0
- **Source:** `inc/classes/class-updatronix-security.php:345`
```php
add_filter( 'updatronix_log_origins', function ( array $origins ) {
	$origins[] = 'deploy';
	return $origins;
} );
```

#### `updatronix_log_query_args`

- **Signature:** `apply_filters( 'updatronix_log_query_args', array $args )`
- **Value:** the log query arguments.
- **Returns:** the arguments. Additive: you may add `action_type`,
  `action_types`, `created_at_after`, or `created_at_before`.
- **@since:** 1.4.0; the second call site at `:1079` has no local tag.
- **Source:** `inc/classes/class-updatronix-logger.php:1009`, `:1079`
```php
add_filter( 'updatronix_log_query_args', function ( array $args ) {
	$args['created_at_after'] = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
	return $args;
} );
```

#### `updatronix_log_reason_codes`

- **Signature:** `apply_filters( 'updatronix_log_reason_codes', array $codes )`
- **Value:** the canonical reason-code allowlist.
- **Returns:** the allowlist. Additive.
- **@since:** 1.6.0
- **Source:** `inc/classes/class-updatronix-security.php:302`
```php
add_filter( 'updatronix_log_reason_codes', function ( array $codes ) {
	$codes[] = 'deployment_policy';
	return $codes;
} );
```

#### `updatronix_log_stages`

- **Signature:** `apply_filters( 'updatronix_log_stages', array $stages )`
- **Value:** the decision-stage allowlist.
- **Returns:** the allowlist.
- **@since:** 1.6.0
- **Source:** `inc/classes/class-updatronix-security.php:368`
```php
add_filter( 'updatronix_log_stages', function ( array $stages ) {
	$stages[] = 'deploy';
	return $stages;
} );
```

#### `updatronix_native_update_time_enabled`

- **Signature:** `apply_filters( 'updatronix_native_update_time_enabled', bool $enabled )`
- **Value:** `true` by default.
- **Returns:** whether Updatronix replaces the native auto-update time text.
- **@since:** 1.3.0
- **Source:** `inc/admin/native-update-time.php:35`
```php
add_filter( 'updatronix_native_update_time_enabled', function ( bool $enabled ) {
	return false;
} );
```

#### `updatronix_schedule_cron_disabled`

- **Signature:** `apply_filters( 'updatronix_schedule_cron_disabled', bool $disabled )`
- **Value:** whether `DISABLE_WP_CRON` is true.
- **Returns:** whether the Schedule treats WP-Cron spawning as disabled.
- **@since:** 1.3.0
- **Source:** `inc/classes/class-updatronix-schedule-context.php:213`
```php
add_filter( 'updatronix_schedule_cron_disabled', function ( bool $disabled ) {
	return $disabled;
} );
```

#### `updatronix_skip_auto_update_delay`

- **Signature:** `apply_filters( 'updatronix_skip_auto_update_delay', bool $skip, string $type, object $item )`
- **Value:** `false` by default.
- **Returns:** `true` to release this offer immediately.
- **@since:** since not declared
- **Source:** `inc/classes/class-updatronix-autoupdatedelay.php:334`
```php
add_filter( 'updatronix_skip_auto_update_delay', function ( bool $skip, string $type, object $item ) {
	return $skip;
}, 10, 3 );
```

### Pro

#### `updatronix_email_brand_name`

- **Signature:** `apply_filters( 'updatronix_email_brand_name', string $name )`
- **Value:** the site name.
- **Returns:** the brand name used in plain-text emails.
- **@since:** 0.1.0
- **Source:** `../updatronix-pro/inc/update-shield/class-updatronix-safe-mode.php:883`, `:1973`
```php
add_filter( 'updatronix_email_brand_name', function ( string $name ) {
	return 'My update service';
} );
```

#### `updatronix_pro_abandoned_data`

- **Signature:** `apply_filters( 'updatronix_pro_abandoned_data', mixed $data, string $type, string $id )`
- **Value:** `null`, which uses the normal WordPress.org lookup.
- **Returns:** `null` or an array that must contain `last_updated`.
- **@since:** 0.9.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-abandoned-detector.php:319`
```php
add_filter( 'updatronix_pro_abandoned_data', function ( $data, string $type, string $id ) {
	if ( 'plugin' === $type && 'my-plugin/my-plugin.php' === $id ) {
		return array( 'last_updated' => '2024-01-01' );
	}
	return $data;
}, 10, 3 );
```

#### `updatronix_pro_abandoned_threshold_days`

- **Signature:** `apply_filters( 'updatronix_pro_abandoned_threshold_days', int $days )`
- **Value:** the default threshold, 365 days.
- **Returns:** the threshold in days.
- **@since:** 0.9.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-abandoned-detector.php:74`
```php
add_filter( 'updatronix_pro_abandoned_threshold_days', function ( int $days ) {
	return 180;
} );
```

#### `updatronix_pro_blocked_history_query`

- **Signature:** `apply_filters( 'updatronix_pro_blocked_history_query', array $query )`
- **Value:** the blocked-history query before it reaches the Free logger.
- **Returns:** the query array.
- **@since:** 0.9.0
- **Source:** `../updatronix-pro/inc/update-shield/class-updatronix-update-shield.php:291`
```php
add_filter( 'updatronix_pro_blocked_history_query', function ( array $query ) {
	$query['per_page'] = 20;
	return $query;
} );
```

#### `updatronix_pro_composer_project_roots`

- **Signature:** `apply_filters( 'updatronix_pro_composer_project_roots', array $roots )`
- **Value:** the candidate Composer project roots.
- **Returns:** the root paths.
- **@since:** 0.13.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-version-control-detector.php:1199`
```php
add_filter( 'updatronix_pro_composer_project_roots', function ( array $roots ) {
	$roots[] = '/srv/my-project';
	return $roots;
} );
```

#### `updatronix_pro_flow_max_chains`

- **Signature:** `apply_filters( 'updatronix_pro_flow_max_chains', int $max_chains )`
- **Value:** the default maximum chain count.
- **Returns:** the maximum, as an integer.
- **@since:** 0.10.0; the second call site at `class-updatronix-update-flow.php:461` has no local tag.
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-flow-planner.php:119`, `../updatronix-pro/inc/update-flow/class-updatronix-update-flow.php:461`
```php
add_filter( 'updatronix_pro_flow_max_chains', function ( int $max_chains ) {
	return 20;
} );
```

#### `updatronix_pro_flow_max_steps`

- **Signature:** `apply_filters( 'updatronix_pro_flow_max_steps', int $max_steps )`
- **Value:** the default maximum step count, 50.
- **Returns:** the maximum, as an integer.
- **@since:** 0.10.0; the second call site at `class-updatronix-update-flow.php:462` has no local tag.
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-flow-planner.php:120`, `../updatronix-pro/inc/update-flow/class-updatronix-update-flow.php:462`
```php
add_filter( 'updatronix_pro_flow_max_steps', function ( int $max_steps ) {
	return 100;
} );
```

#### `updatronix_pro_is_version_controlled`

- **Signature:** `apply_filters( 'updatronix_pro_is_version_controlled', bool $is_controlled, string $type, string $id, string $path )`
- **Value:** the detector result.
- **Returns:** whether the item is version controlled.
- **@since:** 0.9.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-version-control-detector.php:1727`
```php
add_filter( 'updatronix_pro_is_version_controlled', function ( bool $is_controlled, string $type, string $id, string $path ) {
	return $is_controlled;
}, 10, 4 );
```

#### `updatronix_pro_license_api_url`

- **Signature:** `apply_filters( 'updatronix_pro_license_api_url', string $url )`
- **Value:** the licence API endpoint.
- **Returns:** the endpoint URL.
- **@since:** 0.2.0
- **Source:** `../updatronix-pro/inc/core/license-options.php:41`
```php
add_filter( 'updatronix_pro_license_api_url', function ( string $url ) {
	return 'https://license.example.com/api';
} );
```

#### `updatronix_pro_native_vcs_cross_check`

- **Signature:** `apply_filters( 'updatronix_pro_native_vcs_cross_check', ?string $result, string $context )`
- **Value:** `null` to use WordPress.
- **Returns:** `yes`, `no`, `missing`, or `null`.
- **@since:** 0.13.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-version-control-detector.php:938`
```php
add_filter( 'updatronix_pro_native_vcs_cross_check', function ( ?string $result, string $context ) {
	return null;
}, 10, 2 );
```

#### `updatronix_pro_open_basedir_roots`

- **Signature:** `apply_filters( 'updatronix_pro_open_basedir_roots', ?array $roots )`
- **Value:** `null` to use `ini_get()`.
- **Returns:** `null` or an array of root paths.
- **@since:** 0.13.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-version-control-detector.php:274`
```php
add_filter( 'updatronix_pro_open_basedir_roots', function ( ?array $roots ) {
	return array( '/srv/www' );
} );
```

#### `updatronix_pro_shield_defaults`

- **Signature:** `apply_filters( 'updatronix_pro_shield_defaults', array $defaults )`
- **Value:** the shield defaults.
- **Returns:** the defaults array.
- **@since:** 0.1.0
- **Source:** `../updatronix-pro/inc/settings/options.php:51`
```php
add_filter( 'updatronix_pro_shield_defaults', function ( array $defaults ) {
	$defaults['safe_mode'] = true;
	return $defaults;
} );
```

#### `updatronix_pro_shield_state_item_limit`

- **Signature:** `apply_filters( 'updatronix_pro_shield_state_item_limit', int $limit )`
- **Value:** the maximum item count in the shield state.
- **Returns:** the limit.
- **@since:** 0.11.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-shield-state.php:68`
```php
add_filter( 'updatronix_pro_shield_state_item_limit', function ( int $limit ) {
	return 500;
} );
```

#### `updatronix_pro_update_cache_ttl`

- **Signature:** `apply_filters( 'updatronix_pro_update_cache_ttl', int $ttl )`
- **Value:** the update-discovery cache TTL in seconds; the default is 12 hours.
- **Returns:** the TTL.
- **@since:** 0.5.0
- **Source:** `../updatronix-pro/inc/license/class-updatronix-pro-license-updater.php:276`
```php
add_filter( 'updatronix_pro_update_cache_ttl', function ( int $ttl ) {
	return HOUR_IN_SECONDS;
} );
```

#### `updatronix_pro_update_engine_enabled`

- **Signature:** `apply_filters( 'updatronix_pro_update_engine_enabled', bool $enabled )`
- **Value:** `true` by default.
- **Returns:** the update-engine kill switch.
- **@since:** 0.8.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-update-engine-bootstrap.php:61`
```php
add_filter( 'updatronix_pro_update_engine_enabled', function ( bool $enabled ) {
	return $enabled;
} );
```

#### `updatronix_pro_update_package`

- **Signature:** `apply_filters( 'updatronix_pro_update_package', string $package, mixed $data, string $basename )`
- **Value:** the package URL from the licence API; it may be empty.
- **Returns:** the package URL to use.
- **@since:** 0.2.0
- **Source:** `../updatronix-pro/inc/license/class-updatronix-pro-license-updater.php:383`
```php
add_filter( 'updatronix_pro_update_package', function ( string $package, $data, string $basename ) {
	return $package;
}, 10, 3 );
```

#### `updatronix_pro_update_pass_enabled`

- **Signature:** `apply_filters( 'updatronix_pro_update_pass_enabled', bool $enabled )`
- **Value:** the stored switch, with a false constant as a hard off.
- **Returns:** whether the pass is enabled. The filter may override the stored
  value in either direction.
- **@since:** 0.8.0 (changelog `../updatronix-pro/readme.txt:111`); the call site
  at `class-updatronix-update-pass.php:1204` has no local tag.
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-update-pass.php:1204`
- **Note:** the network option of the same name is documented under Data model.
```php
add_filter( 'updatronix_pro_update_pass_enabled', function ( bool $enabled ) {
	return $enabled;
} );
```

#### `updatronix_pro_update_pass_executing`

- **Signature:** `apply_filters( 'updatronix_pro_update_pass_executing', bool $executing )`
- **Value:** `false` by default.
- **Returns:** whether the engine's pass or executor is writing.
- **@since:** 0.13.0
- **Source:** `../updatronix-pro/inc/update-shield/class-updatronix-version-lock-guard.php:214`
```php
add_filter( 'updatronix_pro_update_pass_executing', function ( bool $executing ) {
	return $executing;
} );
```

#### `updatronix_pro_update_pass_flow_order`

- **Signature:** `apply_filters( 'updatronix_pro_update_pass_flow_order', bool $enabled )`
- **Value:** `true` by default.
- **Returns:** whether the pass consumes the Flow plan.
- **@since:** 0.13.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-update-pass-order.php:580`
```php
add_filter( 'updatronix_pro_update_pass_flow_order', function ( bool $enabled ) {
	return $enabled;
} );
```

#### `updatronix_pro_update_pass_max_attempts`

- **Signature:** `apply_filters( 'updatronix_pro_update_pass_max_attempts', int $attempts )`
- **Value:** the per-node attempt cap.
- **Returns:** the cap, clamped to `[1, MAX_ATTEMPTS]`.
- **@since:** since not declared
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-update-pass.php:1217`
```php
add_filter( 'updatronix_pro_update_pass_max_attempts', function ( int $attempts ) {
	return 3;
} );
```

#### `updatronix_pro_update_pass_order`

- **Signature:** `apply_filters( 'updatronix_pro_update_pass_order', array $order, array $nodes )`
- **Value:** the ordered keys.
- **Returns:** the filtered order. Keys that are not in `$nodes` are dropped.
- **@since:** 0.13.0
- **Source:** `../updatronix-pro/inc/update-engine/class-updatronix-update-pass-order.php:154`
```php
add_filter( 'updatronix_pro_update_pass_order', function ( array $order, array $nodes ) {
	return $order;
}, 10, 2 );
```

#### `updatronix_pro_version_lock_update_context`

- **Signature:** `apply_filters( 'updatronix_pro_version_lock_update_context', string $context, string $action )`
- **Value:** `'bulk'` or `'single'`.
- **Returns:** the manual update context.
- **@since:** 0.13.0
- **Source:** `../updatronix-pro/inc/update-shield/class-updatronix-version-lock-guard.php:240`
```php
add_filter( 'updatronix_pro_version_lock_update_context', function ( string $context, string $action ) {
	return $context;
}, 10, 2 );
```

### Shared

#### `updatronix_schedule_item_state`

- **Signature:** `apply_filters( 'updatronix_schedule_item_state', array $facts, string $type, string $id )`
- **Value:** `lock`, `policy`, `flow`, and `queue`, each with a `state`.
- **Returns:** the facts array. Pro owns the single consumer; it must not write.
- **@since:** 1.3.0; the Pro call site at
  `class-updatronix-flow-planner.php:600` has no local tag.
- **Source:** Free `inc/classes/class-updatronix-schedule-context.php:293`; Pro
  `../updatronix-pro/inc/update-engine/class-updatronix-flow-planner.php:600`
```php
add_filter( 'updatronix_schedule_item_state', function ( array $facts, string $type, string $id ) {
	$facts['flow']['state'] = 'ready';
	return $facts;
}, 10, 3 );
```

#### `updatronix_schedule_offer_ttl`

- **Signature:** `apply_filters( 'updatronix_schedule_offer_ttl', int $ttl )`
- **Value:** the offer-freshness TTL, six hours by default.
- **Returns:** the TTL in seconds.
- **@since:** 1.3.0; the Pro call site at
  `class-updatronix-flow-readiness.php:752` has no local tag.
- **Source:** Free `inc/classes/class-updatronix-item-state.php:247`; Pro
  `../updatronix-pro/inc/update-flow/class-updatronix-flow-readiness.php:752`
```php
add_filter( 'updatronix_schedule_offer_ttl', function ( int $ttl ) {
	return 2 * HOUR_IN_SECONDS;
} );
```

## REST API

Both namespaces require an authenticated user with the capability named on the
route. A cookie-authenticated HTTP request sends the `wp_rest` nonce in the
`X-WP-Nonce` header; `apiFetch` adds it. The examples use
`wp_set_current_user( 6 )` and `rest_do_request()`, which set the user directly.

Free routes require `manage_updatronix`; on multisite they also require a Super
Admin (`inc/classes/class-updatronix-security.php:920-926`). Pro routes require
`manage_updatronix_pro` and, on multisite, a Super Admin
(`../updatronix-pro/inc/core/constants.php:33-34`). Shield, flow, and white
label also require an active licence. The licence routes do not, so a key can be
activated.

The read-only routes were smoke-tested at HTTP 200. The write routes were tested
for their permission contract with an anonymous user: 401, or 400 at argument
validation for `/license/activate`. Successful writes are shown by the tested
examples. The exact commands and output are in
`.agents/tasks/2026-11-07-docs/examples-developer-api-en.md`.

### Free: logs

#### `GET /logs`

- **Methods:** GET.
- **Callback:** `Updatronix_Settings::rest_get_logs()` (`inc/classes/class-updatronix-settings.php:482-572`).
- **Permission:** `rest_can_manage_logs`; capability `manage_updatronix`; Super Admin on multisite.
- **Args:** `per_page` integer, default 50, 1–200; `page` integer, default 1, min 1; `log_type` enum `''`, `core`, `plugin`, `theme`, `translation`, `system`; `performed_as` enum `''`, `manual`, `automatic`, `upload`; `status` enum `''`, `success`, `error`, `cancelled`, `delayed`, `info`, `warning`; `action_type`, `reason_code`, `decision_state`, `origin`, `pass_id`, `chain_id` strings; `user` integer, min 0; `created_at_after`, `created_at_before`, `next_eligible_at_after`, `next_eligible_at_before` date-time strings; `retained`, `network` integers, 0 or 1; `filters` string or array; `site_id` integer, default null; `search` string, default `''`, max 200.
- **Response 200:** `{ "logs": [ <log objects> ], "total": <int> }`. A log object is the stored row plus the display fields from `enrich_log_for_display()` (`:914-983`).
- **Errors:** 401/403 from the permission callback; 400 for an invalid argument.
- **Constraints:** `site_id` narrows the read; the default is the network-global scope for a Super Admin (`:1732-1754`).
- **Tested:** example 2 and the smoke run, HTTP 200, `total=17`, five rows.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
$request->set_query_params( array( 'per_page' => 5 ) );
$response = rest_do_request( $request );
```

#### `GET|DELETE /logs/{id}`

- **Methods:** GET, DELETE.
- **Callback:** `rest_get_log()` (`:580-606`), `rest_delete_log()` (`:1190-1224`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** `id` integer, required, min 1.
- **Response 200:** GET `{ "log": <enriched log> }`; DELETE `{ "deleted": true }`.
- **Errors:** 404 `{ "message": "The requested log entry could not be found." }`; DELETE 500 `{ "message": "The log entry could not be deleted..." }`.
- **Constraints:** Same site scope as `/logs`. Delete only a demo row.
- **Tested:** smoke run GET HTTP 200; DELETE permission contract 401 with an anonymous user. Example 1 and 3 delete their demo rows through the logger, not the route.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs/17' );
$response = rest_do_request( $request );

$delete = new WP_REST_Request( 'DELETE', '/updatronix/v1/logs/17' );
$deleted = rest_do_request( $delete );
```

#### `POST /logs/cleanup`

- **Methods:** POST.
- **Callback:** `rest_cleanup_logs()` (`:1232-1239`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** none.
- **Response 200:** `{ "deleted": <int> }`.
- **Errors:** 401/403 from the permission callback.
- **Constraints:** Deletes rows older than `retention_days`. Do not run it on demo data without a safe cutoff.
- **Tested:** permission contract 401 with an anonymous user; the destructive write was not executed.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/logs/cleanup' );
$response = rest_do_request( $request );
```

#### `DELETE /logs/all`

- **Methods:** DELETE.
- **Callback:** `rest_delete_all_logs()` (`:1247-1251`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** none.
- **Response 200:** `{ "deleted": <int> }`.
- **Errors:** 401/403 from the permission callback.
- **Constraints:** Destructive; it removes every log row in scope. Run it only on a disposable log.
- **Tested:** permission contract 401 with an anonymous user; the destructive write was not executed.
```php
$request = new WP_REST_Request( 'DELETE', '/updatronix/v1/logs/all' );
$response = rest_do_request( $request );
```

### Free: settings

#### `GET|POST|PUT|PATCH /settings`

- **Methods:** GET, POST, PUT, PATCH.
- **Callback:** `rest_get_settings()` (`:452-463`), `rest_update_settings()` (`:1259-1344`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** `logging_enabled` boolean; `delete_data_on_uninstall` boolean; `retention_days` integer 1–365; `notifications_mode` enum `default`, `disabled`; `notify_enabled` boolean; `notify_emails` string, max `UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES`; `notify_on` array of `core`, `plugin_theme`, `debug`, `technical`; `schedule` object with `update_check.recurrence` enum `''`, `hourly`, `twicedaily`, `daily`, `weekly`, `update_check.time` pattern `^(([01][0-9]|2[0-3]):[0-5][0-9])?$`, `delay_updates.enabled` boolean, `delay_updates.delay_value` integer 0–365.
- **Response 200:** GET `{ "options": <array>, "schedule_meta": <array>, "log_taxonomy": <array> }`; write `{ "options": <array>, "schedule_meta": <array> }`.
- **Errors:** 403 `{ "code": "updatronix_forbidden", "message": "...", "data": { "status": 403 } }` when `delete_data_on_uninstall` is turned on without `manage_options`.
- **Constraints:** `delete_data_on_uninstall` additionally needs `manage_options`. The schedule merge is partial.
- **Tested:** smoke run GET HTTP 200; write permission contract 401 with an anonymous user.
```php
$read = new WP_REST_Request( 'GET', '/updatronix/v1/settings' );
$data = rest_do_request( $read )->get_data();

$write = new WP_REST_Request( 'POST', '/updatronix/v1/settings' );
$write->set_body_params( array( 'retention_days' => 90 ) );
$updated = rest_do_request( $write );
```

### Free: auto updates

#### `GET /auto-updates`

- **Methods:** GET.
- **Callback:** `rest_get_auto_updates()` (`:1365-1367`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** none.
- **Response 200:** `Updatronix_AutoUpdates::get_data()`: `{ "constants": <array>, "dismissed_constants": <array>, "core": <array>, "plugins": <array>, "themes": <array>, "translations": <array> }` (`inc/classes/class-updatronix-autoupdates.php:46-64`).
- **Errors:** 401/403 from the permission callback.
- **Constraints:** None beyond the capability and multisite rule.
- **Tested:** smoke run HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/auto-updates' );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/core`

- **Methods:** POST.
- **Callback:** `rest_set_core_mode()` (`:1420-1447`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** `mode` string, required, enum `all`, `minor`, `disabled`.
- **Response 200:** the `get_data()` shape.
- **Errors:** 403 when `AUTOMATIC_UPDATER_DISABLED` or `DISALLOW_FILE_MODS` is set, or when a core constant controls the mode (`auto_update_globally_locked()`, `:1380-1412`).
- **Constraints:** The global constants win over the request.
- **Tested:** permission contract 401 with an anonymous user.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/core' );
$request->set_body_params( array( 'mode' => 'minor' ) );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/plugin`

- **Methods:** POST.
- **Callback:** `rest_toggle_plugin()` (`:1455-1511`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** `plugin` string, required; `enable` boolean, required.
- **Response 200:** the `get_data()` shape.
- **Errors:** 403 for a global lock or a `updatronix_auto_update_can_toggle` veto; 404 when the plugin is not installed.
- **Constraints:** The `updatronix_auto_update_can_toggle` filter can return `false` or a `WP_Error`; both become 403.
- **Tested:** example 4, HTTP 403 with the filter veto; smoke run permission contract 401 with an anonymous user.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/plugin' );
$request->set_body_params( array( 'plugin' => 'koko-analytics/koko-analytics.php', 'enable' => false ) );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/theme`

- **Methods:** POST.
- **Callback:** `rest_toggle_theme()` (`:1519-1575`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** `stylesheet` string, required; `enable` boolean, required.
- **Response 200:** the `get_data()` shape.
- **Errors:** 403 for a global lock or a filter veto; 404 when the theme is not installed.
- **Constraints:** Same filter path as the plugin toggle.
- **Tested:** permission contract 401 with an anonymous user.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/theme' );
$request->set_body_params( array( 'stylesheet' => 'twentytwentyfive', 'enable' => false ) );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/translation`

- **Methods:** POST.
- **Callback:** `rest_toggle_translation()` (`:1583-1595`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** `enable` boolean, required.
- **Response 200:** the `get_data()` shape.
- **Errors:** 403 for a global lock.
- **Constraints:** None beyond the capability and multisite rule.
- **Tested:** permission contract 401 with an anonymous user.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/translation' );
$request->set_body_params( array( 'enable' => true ) );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/dismiss-constant`

- **Methods:** POST.
- **Callback:** `rest_dismiss_constant()` (`:1606-1625`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** `constant` string, required.
- **Response 200:** the `get_data()` shape.
- **Errors:** 400 `{ "message": "Updatronix does not recognize that constant." }` when the name is not in the allowlist.
- **Constraints:** Only the dismissable constant allowlist is accepted.
- **Tested:** permission contract 401 with an anonymous user.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/dismiss-constant' );
$request->set_body_params( array( 'constant' => 'AUTOMATIC_UPDATER_DISABLED' ) );
$response = rest_do_request( $request );
```

#### `POST /run-check`

- **Methods:** POST.
- **Callback:** `rest_run_check()` (`:1637-1656`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** none.
- **Response 200:** `{ "message": "Update check started." }`.
- **Errors:** 401/403 from the permission callback.
- **Constraints:** Refreshes core, plugin, theme, and translation update data; the schedule is unchanged. It is in the code but not in the brief’s route list.
- **Tested:** permission contract 401 with an anonymous user.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/run-check' );
$response = rest_do_request( $request );
```

### Free: health

#### `GET /health`

- **Methods:** GET.
- **Callback:** `rest_get_health()` (`:1663-1665`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** none.
- **Response 200:** `{ "generated_at": <int>, "generated_at_human": <string>, "summary": <array>, "checks": <array>, "open_issues": <array>, "resolved": <array>, "counts": { "total": <int>, "passing": <int>, "open": <int> }, "logging_enabled": <bool>, "has_log_table": <bool> }` (`inc/classes/class-updatronix-health.php:70-105`).
- **Errors:** 401/403 from the permission callback.
- **Constraints:** The snapshot is cached for 300 seconds in `updatronix_health_snapshot`.
- **Tested:** smoke run HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/health' );
$response = rest_do_request( $request );
```

#### `POST /health/check`

- **Methods:** POST.
- **Callback:** `rest_run_health_check()` (`:1672-1674`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** none.
- **Response 200:** the freshly rebuilt snapshot, same shape as `/health`.
- **Errors:** 401/403 from the permission callback.
- **Constraints:** Forces a rebuild and rewrites the 300-second cache.
- **Tested:** permission contract 401 with an anonymous user.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/health/check' );
$response = rest_do_request( $request );
```

#### `POST /health/retry`

- **Methods:** POST.
- **Callback:** `rest_retry_health()` (`:1682-1707`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** `log_id` integer, required, min 1.
- **Response 200:** `{ "queued": true, "log_id": <int>, "message": <string> }`.
- **Errors:** 404 `updatronix_health_missing`; 400 `updatronix_health_not_failed`, `updatronix_health_expired`, `updatronix_health_unsupported`; 403 `updatronix_health_forbidden`; 409 `updatronix_health_busy`; 429 `updatronix_health_rate_limited`; 500 `updatronix_health_schedule_failed`. The body is `{ "code": <code>, "message": <message> }`.
- **Constraints:** Only plugin and theme rows with status `error`, created within 30 days, and with `update_plugins` or `update_themes`. The retry is queued once per item, with a 15-minute lock and a limit of three per hour (`inc/classes/class-updatronix-health.php:936-1063`).
- **Tested:** permission contract 401 with an anonymous user.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/health/retry' );
$request->set_body_params( array( 'log_id' => 1 ) );
$response = rest_do_request( $request );
```

#### `GET /health/diagnostics`

- **Methods:** GET.
- **Callback:** `rest_get_health_diagnostics()` (`:1714-1716`).
- **Permission:** `rest_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** none.
- **Response 200:** `{ "environment": [ { "label": <string>, "value": <string> } ], "text": <string> }` (`inc/classes/class-updatronix-health.php:834-928`).
- **Errors:** 401/403 from the permission callback.
- **Constraints:** Includes WordPress, PHP, database, server, memory, execution time, upload size, timezone, multisite, Updatronix version, log table, update-check, and logging values.
- **Tested:** smoke run HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/health/diagnostics' );
$response = rest_do_request( $request );
```

### Free: export

#### `POST /logs/export`

- **Methods:** POST.
- **Callback:** `Updatronix_Export::rest_export()` (`inc/classes/class-updatronix-export.php:251-512`).
- **Permission:** `Updatronix_Security::user_can_manage_logs`; `manage_updatronix`; Super Admin on multisite.
- **Args:** `view` object, required: `site_id` integer; `filters` array; `sort` object with `field` enum `date`, `created_at`, `log_type`, `item_name`, `status`, `performed_as` and `direction` enum `asc`, `desc`; `per_page` integer 1–200; `page` integer min 1; `search` string max 200. `merge` boolean, default true. `columns` object. `format` string, enum of the server formats, default `txt`. `cursor` string, default `''`.
- **Response 200:** `{ "status": "ready", "body": <string>, "transient_key": <string>, "truncated": <bool>, "truncation_reason": <string>, "next_cursor": <string|null>, "truncated_included": <int>, "truncated_total": <int|null>, "view_applied": <array>, "meta": { "generated_at": <int>, "rows_in_chunk": <int> } }`. A chunked response has `next_cursor` and `truncated: false` (`:624-683`); the terminal response sets `next_cursor` to null (`:711-846`).
- **Errors:** 400 `view_invalid`; 429 rate limit; 403 from the permission callback.
- **Constraints:** Capped by the server formats, the rate limiter, and the chunk cursor. Free returns plain text; Pro adds the structured formats.
- **Tested:** smoke run HTTP 200; the export transient and user pointer were deleted after the run.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/logs/export' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body( wp_json_encode( array( 'view' => array( 'per_page' => 10 ), 'format' => 'txt' ) ) );
$response = rest_do_request( $request );
```

### Pro: shield

#### `GET|POST /shield/settings`

- **Methods:** GET, POST.
- **Callback:** `Updatronix_Update_Shield::get_shield_settings()` and `save_shield_settings()` (`../updatronix-pro/inc/update-shield/class-updatronix-update-shield.php:164-253`).
- **Permission:** `check_shield_permission`; `manage_updatronix_pro`; Super Admin on multisite; active licence or dev mode.
- **Args:** `safe_mode` object with `enabled` boolean; `notify_on_block` boolean; `control_php_version` boolean; `control_wp_version` boolean; `incompatible_behavior` enum `disable`, `cancel`; `re_enable` boolean; `notify_email` boolean; `flag_abandoned` boolean; `version_control_lock` boolean; `version_lock_manual_updates` boolean. All optional.
- **Response 200:** `{ "safe_mode": { "enabled": <bool> }, "control_php_version": <bool>, "control_wp_version": <bool>, "notify_on_block": <bool>, "incompatible_behavior": "disable"|"cancel", "re_enable": <bool>, "notify_email": <bool>, "pass_state": <array>, "flag_abandoned": <bool>, "version_control_lock": <bool>, "version_lock_manual_updates": <bool>, "version_control_state": <mixed>, "abandoned_state": <mixed>, "reenable_state": <mixed>, "shield_status": { "blocked_plugins_count": <int>, "blocked_themes_count": <int>, "blocked_core": <bool>, "blocked_plugins": <array>, "blocked_themes": <array>, "blocked_core_details": <mixed>, "abandoned": <array> } }`.
- **Errors:** 401/403 from the permission callback; 400 for an invalid argument.
- **Constraints:** Licence required; dev mode is accepted. The save is a merge.
- **Tested:** example 5 and the smoke run, GET HTTP 200; POST permission contract 401 with an anonymous user.
```php
$read = new WP_REST_Request( 'GET', '/updatronix-pro/v1/shield/settings' );
$data = rest_do_request( $read )->get_data();

$write = new WP_REST_Request( 'POST', '/updatronix-pro/v1/shield/settings' );
$write->set_header( 'Content-Type', 'application/json' );
$write->set_body( wp_json_encode( array( 'safe_mode' => array( 'enabled' => true ) ) ) );
$updated = rest_do_request( $write );
```

#### `POST /shield/recheck`

- **Methods:** POST.
- **Callback:** `recheck_compatibility()` (`class-updatronix-update-shield.php:361-455`).
- **Permission:** `check_shield_permission`; `manage_updatronix_pro`; Super Admin on multisite; active licence or dev mode.
- **Args:** none.
- **Response 200:** `{ "shield_status": { "blocked_plugins_count": <int>, "blocked_themes_count": <int>, "blocked_core": <bool>, "blocked_plugins": <array>, "blocked_themes": <array>, "blocked_core_details": <mixed>, "abandoned": <array>, "requeued": 0 } }`.
- **Errors:** 502 `{ "code": "shield_recheck_unreachable", "message": "WordPress.org could not be reached. Try again later." }`; 401/403 from the permission callback.
- **Constraints:** Outbound WordPress.org requests with a 15-second timeout. It disables auto-updates for blocked items as a side effect.
- **Tested:** permission contract 401 with an anonymous user; the outbound write was not executed.
```php
$request = new WP_REST_Request( 'POST', '/updatronix-pro/v1/shield/recheck' );
$response = rest_do_request( $request );
```

#### `GET /shield/blocked`

- **Methods:** GET.
- **Callback:** `get_blocked_history()` (`class-updatronix-update-shield.php:267-352`).
- **Permission:** `check_shield_permission`; `manage_updatronix_pro`; Super Admin on multisite; active licence or dev mode.
- **Args:** `page` integer, min 1, default 1; `per_page` integer, min 1, max 50, default 50.
- **Response 200:** `{ "items": [ { "id": <int>, "item": <string>, "type": <string>, "versions": <string>, "reason": <string>, "date": <string>, "dateFormatted": <string>, "log_url": <string> } ], "days": 30, "page": <int>, "per_page": <int>, "total": <int>, "total_pages": <int> }`.
- **Errors:** 401/403 from the permission callback.
- **Constraints:** Reads the last 30 days of rows with action types `prevented`, `incompatible`, or `safe_mode_disabled`; filterable through `updatronix_pro_blocked_history_query`.
- **Tested:** smoke run HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix-pro/v1/shield/blocked' );
$request->set_query_params( array( 'per_page' => 10 ) );
$response = rest_do_request( $request );
```

### Pro: flow

#### `GET|POST /flow/chains`

- **Methods:** GET, POST.
- **Callback:** `Updatronix_Update_Flow::get_chains()` and `save_chains()` (`../updatronix-pro/inc/update-flow/class-updatronix-update-flow.php:187-198`, `:332-373`).
- **Permission:** `check_permission`; `manage_updatronix_pro`; Super Admin on multisite; active licence.
- **Args:** none in the route definition; POST reads the JSON body `{ "chains": <array> }`.
- **Response 200:** `{ "chains": <array>, "items": <array>, "active_pass": <array|null>, "last_pass": <array>, "automatic_updates": { "enabled": <bool>, "stored": <bool>, "editable": <bool>, "reason": <string> } }`.
- **Errors:** 400 `{ "code": <code>, "message": <message>, "errors": <mixed> }` for an invalid chain or an item that is not installed; 401/403 from the permission callback.
- **Constraints:** Chains are validated for cycles and installed items; the option is network-wide.
- **Tested:** smoke run GET HTTP 200; POST permission contract 401 with an anonymous user.
```php
$read = new WP_REST_Request( 'GET', '/updatronix-pro/v1/flow/chains' );
$data = rest_do_request( $read )->get_data();

$write = new WP_REST_Request( 'POST', '/updatronix-pro/v1/flow/chains' );
$write->set_header( 'Content-Type', 'application/json' );
$write->set_body( wp_json_encode( array( 'chains' => array() ) ) );
$updated = rest_do_request( $write );
```

#### `POST /flow/automatic-updates`

- **Methods:** POST.
- **Callback:** `save_automatic_updates()` (`class-updatronix-update-flow.php:241-275`).
- **Permission:** `check_permission`; `manage_updatronix_pro`; Super Admin on multisite; active licence.
- **Args:** `enabled` boolean, required in the JSON body.
- **Response 200:** `{ "enabled": <bool>, "stored": <bool>, "editable": <bool>, "reason": <string>, "active_pass": <array|null>, "last_pass": <array> }`.
- **Errors:** 400 `flow_invalid_enabled` when `enabled` is missing or not boolean; 500 `flow_pass_missing` when the pass class is unavailable; 401/403 from the permission callback.
- **Constraints:** The route works even when a chain is temporarily invalid; an off switch stops the active pass.
- **Tested:** permission contract 401 with an anonymous user.
```php
$request = new WP_REST_Request( 'POST', '/updatronix-pro/v1/flow/automatic-updates' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body( wp_json_encode( array( 'enabled' => false ) ) );
$response = rest_do_request( $request );
```

#### `GET /flow/items`

- **Methods:** GET.
- **Callback:** `get_items_endpoint()` (`class-updatronix-update-flow.php:319-324`).
- **Permission:** `check_permission`; `manage_updatronix_pro`; Super Admin on multisite; active licence.
- **Args:** none.
- **Response 200:** `{ "items": <array> }`.
- **Errors:** 401/403 from the permission callback.
- **Constraints:** Licence required.
- **Tested:** smoke run HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix-pro/v1/flow/items' );
$response = rest_do_request( $request );
```

### Pro: white label

#### `GET|POST /white-label`

- **Methods:** GET, POST.
- **Callback:** `Updatronix_White_Label::get_white_label()` and `save_white_label()` (`../updatronix-pro/inc/white-label/class-updatronix-white-label.php:132-163`).
- **Permission:** `check_permission`; `manage_updatronix_pro`; Super Admin on multisite; active licence.
- **Args:** none in the route definition; POST reads the JSON body. `logo_id` is validated as PNG, JPEG, or WebP when it changes.
- **Response 200:** `{ "settings": <array>, "logo_url": <string>, "defaults": <array>, "site_name": <string>, "can_upload_files": <bool>, "is_multisite": <bool>, "limits": { "brand_name": <int>, "menu_label": <int>, "logo_bytes": <int>, "logo_min_px": <int> } }`.
- **Errors:** 400 `updatronix_invalid_logo`; 401/403 from the permission callback.
- **Constraints:** The save merges with the existing settings; branding is network-wide.
- **Tested:** smoke run GET HTTP 200; POST permission contract 401 with an anonymous user.
```php
$read = new WP_REST_Request( 'GET', '/updatronix-pro/v1/white-label' );
$data = rest_do_request( $read )->get_data();

$write = new WP_REST_Request( 'POST', '/updatronix-pro/v1/white-label' );
$write->set_header( 'Content-Type', 'application/json' );
$write->set_body( wp_json_encode( array( 'brand_name' => 'Demo' ) ) );
$updated = rest_do_request( $write );
```

### Pro: license

#### `GET /license/status`

- **Methods:** GET.
- **Callback:** `Updatronix_Pro_License_REST::get_status()` (`../updatronix-pro/inc/license/class-updatronix-pro-license-rest.php:136-180`).
- **Permission:** `check_permission`; `manage_updatronix_pro`; Super Admin on multisite. No active licence required.
- **Args:** none.
- **Response 200:** `{ "status": <string>, "registeredDomain": <string|null>, "maskedKey": <string>, "lastChecked": <string|null>, "lastCheckedFormatted": <string|null>, "dev_mode": <bool>, "decryption_error": <bool> }`.
- **Errors:** 401/403 from the permission callback.
- **Constraints:** No licence required, so the route reports an inactive state. The key is always masked.
- **Tested:** example 5 and the smoke run, HTTP 200; `license_state=active`.
```php
$request = new WP_REST_Request( 'GET', '/updatronix-pro/v1/license/status' );
$response = rest_do_request( $request );
```

#### `POST /license/activate`

- **Methods:** POST.
- **Callback:** `activate()` (`class-updatronix-pro-license-rest.php:193-302`).
- **Permission:** `check_permission`; `manage_updatronix_pro`; Super Admin on multisite. No active licence required.
- **Args:** `license_key` string, required, sanitized with `sanitize_text_field`, validated with `Updatronix_Pro_License_Storage::is_valid_key_format`.
- **Response 200:** `{ "status": "active", "registeredDomain": <string>, "maskedKey": <string>, "message": "License activated." }`.
- **Errors:** 400 `{ "status": "error", "code": <string>, "message": <string> }` for an API rejection; 429 `{ "status": "error", "code": "e429", "message": <string>, "retry_after": <int> }` for the 60-second cooldown; 500 `license_save_failed`; 401/403 from the permission callback.
- **Constraints:** The key is never returned unmasked. Activation is rate-limited to one attempt per 60 seconds.
- **Tested:** permission contract 400 at argument validation with an anonymous user; no key was sent to the licence API. A real key is never printed.
```php
$request = new WP_REST_Request( 'POST', '/updatronix-pro/v1/license/activate' );
$request->set_body_params( array( 'license_key' => '<var>YOUR_LICENSE_KEY</var>' ) );
$response = rest_do_request( $request );
```

#### `POST /license/deactivate`

- **Methods:** POST.
- **Callback:** `deactivate()` (`class-updatronix-pro-license-rest.php:316-415`).
- **Permission:** `check_permission`; `manage_updatronix_pro`; Super Admin on multisite. No active licence required.
- **Args:** none.
- **Response 200:** `{ "status": "inactive", "message": "License deactivated." }`.
- **Errors:** 400 `rest_license_inactive` when the licence is already inactive; 400 for an API rejection; 500 `license_save_failed`; 401/403 from the permission callback.
- **Constraints:** A decryption error clears the local key and returns 200. A real deactivation changes licence state; record and restore it if the demo instance has an active licence.
- **Tested:** permission contract 401 with an anonymous user; the active demo licence was not changed.
```php
$request = new WP_REST_Request( 'POST', '/updatronix-pro/v1/license/deactivate' );
$response = rest_do_request( $request );
```

## Public PHP API

Hooks, filters, and REST routes are the supported surface. The four stable PHP
symbols below are the exception: `updatronix_get_admin_tabs()`, the
`Updatronix_External_Log` class, and its `sources()` and `log()` methods. Every
other class is internal and may change without a deprecation.

### Stable symbols

#### `updatronix_get_admin_tabs()`

- **Signature:** `function updatronix_get_admin_tabs(): array`
- **Returns:** `array<string, array{slug: string, label: string, icon: string, priority?: int}>`; tabs are sorted by priority.
- **@since:** 1.1.1
- **Source:** `inc/core/tabs.php:62-77`
- Use the `updatronix_admin_tabs` filter to add, remove, or reorder tabs.
```php
$tabs = updatronix_get_admin_tabs();
```

#### `Updatronix_External_Log::sources()`

- **Signature:** `public static function sources(): array`
- **Returns:** the accepted source keys; the defaults are `ftp`, `sftp`, `ssh`,
  `cli`, and `custom`.
- **@since:** 1.4.0
- **Source:** `inc/classes/class-updatronix-external-log.php:32-68`
```php
$sources = Updatronix_External_Log::sources();
```

#### `Updatronix_External_Log::log()`

- **Signature:** `public static function log( array $args ): int|\WP_Error`
- **Args:** `item_name` required. Optional: `source` (default `custom`),
  `log_type` (default `plugin`), `item_slug`, `version_before`, `version_after`,
  `status` (default `success`), `message`, `event_key`, and the decision fields
  `target_version`, `reason_code`, `decision_state`, `origin`, `decision_stage`,
  `pass_id`, `chain_id`, `predecessor`, `next_eligible_at`, `attempt`,
  `network`, and `context`.
- **Returns:** the log ID, or `WP_Error` on validation or storage failure.
- **@since:** 1.4.0; 1.6.0 added the optional decision fields.
- **Source:** `inc/classes/class-updatronix-external-log.php:70-227`
- Fires `updatronix_external_update_logged` after the insert.
```php
$log_id = Updatronix_External_Log::log(
	array(
		'item_name'      => 'Deploy 2026-11-07',
		'source'         => 'custom',
		'version_before' => '1.0.0',
		'version_after'  => '1.0.1',
	)
);
```

### Internal classes

Every other class is internal. That includes the Free classes
`Updatronix_Logger`, `Updatronix_Settings`, `Updatronix_Security`,
`Updatronix_Health`, `Updatronix_AutoUpdates`, `Updatronix_AutoUpdateDelay`,
`Updatronix_Cron`, `Updatronix_Notifications`, `Updatronix_Update_Logger`,
`Updatronix_Export`, `Updatronix_Export_Request_Schema`,
`Updatronix_Export_Query_Builder`, `Updatronix_Export_Body_Builder`,
`Updatronix_Export_Transient_Manager`, `Updatronix_Export_Rate_Limiter`,
`Updatronix_Export_Cursor`, `Updatronix_Export_Audit`, `Updatronix_Database`,
`Updatronix_Log_Retention`, `Updatronix_Log_Taxonomy`, `Updatronix_Log_Event`,
`Updatronix_Log_Query`, `Updatronix_UpdateLogState`,
`Updatronix_Schedule_Context`, `Updatronix_Item_State`,
`Updatronix_Schedule_State`, `Updatronix_ErrorHandler`,
`Updatronix_Core_Update_Log_Versions`, and
`Updatronix_Automatic_Update_Result_Notes`; and the Pro classes
`Updatronix_Pro_Connector`, `Updatronix_Update_Shield`,
`Updatronix_Update_Flow`, `Updatronix_Update_Pass_Order`,
`Updatronix_Schedule_Facts`, `Updatronix_Flow_Readiness`,
`Updatronix_White_Label`, and `Updatronix_Pro_License_REST`. Use the hook,
filter, or REST route that this guide names for the same feature.

## Data model

### Log table

- Table: `{prefix}updatronix_logs` (`Updatronix_Database::TABLE_LOGS`,
  `inc/classes/class-updatronix-database.php:49`; the prefixed name comes from
  `get_table_name()`, `:77-81`).
- The table lives on the main site on multisite; `install_schema()` runs through
  `updatronix_with_main_site()` (`:177-238`).
- Schema version: `3` (`SCHEMA_VERSION`, `:35`).
- The table has 30 columns.

| Column | Type and default | Stability |
|---|---|---|
| `id` | `bigint(20) unsigned NOT NULL AUTO_INCREMENT` | Stable |
| `site_id` | `bigint(20) unsigned NOT NULL DEFAULT 1` | Stable |
| `log_type` | `varchar(20) NOT NULL DEFAULT 'plugin'` | Stable |
| `action_type` | `varchar(32) NOT NULL DEFAULT 'update'` | Stable |
| `item_name` | `varchar(255) NOT NULL DEFAULT ''` | Stable |
| `item_slug` | `varchar(255) NOT NULL DEFAULT ''` | Stable |
| `version_before` | `varchar(64) NOT NULL DEFAULT ''` | Stable |
| `version_after` | `varchar(64) NOT NULL DEFAULT ''` | Stable |
| `target_version` | `varchar(64) NOT NULL DEFAULT ''` | Stable |
| `status` | `varchar(20) NOT NULL DEFAULT 'success'` | Stable |
| `event_key` | `varchar(191) DEFAULT NULL`, unique | Stable |
| `message` | `longtext DEFAULT NULL` | Internal |
| `trace` | `longtext DEFAULT NULL` | Internal |
| `reason_code` | `varchar(48) NOT NULL DEFAULT ''` | Stable |
| `decision_state` | `varchar(20) NOT NULL DEFAULT ''` | Stable |
| `origin` | `varchar(20) NOT NULL DEFAULT ''` | Stable |
| `decision_stage` | `varchar(20) NOT NULL DEFAULT ''` | Stable |
| `pass_id` | `char(32) NOT NULL DEFAULT ''` | Stable |
| `chain_id` | `varchar(64) DEFAULT NULL` | Stable |
| `predecessor` | `varchar(191) DEFAULT NULL` | Stable |
| `next_eligible_at` | `datetime DEFAULT NULL` | Stable |
| `attempt` | `smallint unsigned NOT NULL DEFAULT 0` | Stable |
| `network` | `tinyint(1) NOT NULL DEFAULT 0` | Stable |
| `context` | `longtext DEFAULT NULL` | Internal |
| `retained` | `tinyint(1) NOT NULL DEFAULT 0` | Stable, read-only |
| `user_id` | `bigint(20) unsigned NOT NULL DEFAULT 0` | Stable |
| `performed_by` | `varchar(20) NOT NULL DEFAULT 'system'` | Stable |
| `performed_as` | `varchar(20) NOT NULL DEFAULT 'manual'` | Stable |
| `update_context` | `varchar(20) NOT NULL DEFAULT ''` | Stable |
| `created_at` | `datetime NOT NULL DEFAULT '0000-00-00 00:00:00'` | Stable |

Indexes: `PRIMARY KEY (id)`, `site_id`, `log_type`, `status`, `created_at`,
`UNIQUE KEY event_key`, `created_type_status (created_at, log_type, status)`,
`reason_created (reason_code, created_at)`, `state_created (decision_state,
created_at)`, `origin_created (origin, created_at)`, `pass_chain (pass_id,
chain_id)`, `item_lookup (site_id, log_type, item_slug, target_version)`, and
`chain_created (chain_id, created_at)`.

`message`, `trace`, and `context` are free-form and may contain process output,
paths, or redacted tokens. Read them through the REST route; do not parse them
as a stable schema. `retained` is written by the retention job; read it, do not
write it.

### Options

| Option | Scope and shape | Stability |
|---|---|---|
| `updatronix_settings` | Per-site JSON. `logging_enabled` bool true; `retention_days` int 90 (1–365); `notifications_mode` `default`\|`disabled`; `notify_enabled` bool false; `notify_emails` string; `notify_on` array of `core`, `plugin_theme`, `debug`, `technical`; `auto_update_translations` bool true; `dismissed_constants` array; `delete_data_on_uninstall` bool false; `schedule` read from the network schedule | Stable for read; write through `/settings` |
| `updatronix_network_schedule` | Network site option on multisite; non-autoloaded option on single-site. `update_check.recurrence` `''`\|`hourly`\|`twicedaily`\|`daily`\|`weekly`; `update_check.time` `HH:MM` or `''`; `delay_updates.enabled` bool false; `delay_updates.delay_value` int 0–365 | Stable for read |
| `updatronix_update_logger_state` | Non-autoloaded pending event state, keyed by event key; pruned after 1800 seconds | Internal |
| `updatronix_log_db_version` | The stored schema version string, currently `3` | Internal |
| `updatronix_export_audit` | Non-autoloaded FIFO ring buffer, at most 100 entries | Internal |
| `auto_update_plugins` | Native site option; array of plugin files | Stable WordPress-native |
| `auto_update_themes` | Native site option; array of stylesheets | Stable WordPress-native |
| `auto_update_core_major` | Native site option; `unset`\|`enabled`\|`disabled` | Stable WordPress-native |
| `auto_update_core_minor` | Native site option; `enabled`\|`disabled` | Stable WordPress-native |
| `auto_update_core_dev` | Native site option; `enabled`\|`disabled` | Stable WordPress-native |

The Pro options are network-scoped site options and are internal:
`updatronix_pro_shield_settings`, `updatronix_pro_license`,
`updatronix_pro_white_label`, `updatronix_pro_flow_chains`,
`updatronix_pro_update_pass`, `updatronix_pro_update_pass_enabled`,
`updatronix_pro_last_pass`, `updatronix_pro_shield_ledger`, and
`updatronix_pro_version_lock_notice_seen`. Use the Pro REST routes.

The filter `updatronix_pro_update_pass_enabled` shares its name with the option
above. The option stores the switch; the filter overrides the effective value.

### Transients

| Key | Lifetime | Purpose |
|---|---|---|
| `updatronix_health_snapshot` | 300 seconds | Health snapshot cache; read the snapshot through `/health` |
| `updatronix_log_schema_upgrade_lock` | 60 seconds | Schema upgrade lock |
| `updatronix_log_export_{site}_{user}_{32 hex}` | 900 seconds | Export continuation payload |
| `updatronix_export_rl_min_{site}_{user}` | 60 seconds | Export rate limiter |
| `updatronix_export_rl_hour_{site}_{user}` | 3600 seconds | Export rate limiter |
| `updatronix_changelog_{md5(type\|slug)}` | 1 hour when empty; 12 hours when populated | Log-details changelog cache |
| `updatronix_health_retry_lock_{md5}` | 15 minutes | Health retry lock |
| `updatronix_health_retry_rate_{md5}` | 1 hour | Health retry rate counter |
| `updatronix_pro_activation_throttle` | 60 seconds | Pro licence activation cooldown |
| `updatronix_pro_abandoned_cache` | 7 days; max 200 entries | Pro abandoned-plugin cache |

The object-cache group `updatronix_logs` is an object cache, not a transient.
WordPress core transients `update_plugins`, `update_themes`, and `update_core`
are read and refreshed by the shield recheck; they are not owned by Updatronix.

### Retention

- Window: `retention_days` 1–365, default 90 (`inc/settings/options.php:31`,
  `:431-437`).
- `Updatronix_Log_Retention::cleanup( $days )` runs three steps
  (`inc/classes/class-updatronix-log-retention.php:19-33`):
  1. `pin()` marks the newest row per `(site_id, log_type, item_slug,
     target_version)` older than the cutoff, where `decision_state` is
     `blocked`, `failed`, or `uncertain`, and sets `retained = 1` (`:46-93`).
  2. `trim( 500 )` keeps at most 500 retained rows and deletes the older
     retained rows (`:97-125`; `RETAINED_CAP = 500`, `:21`).
  3. `delete_expired()` deletes rows older than the cutoff with `retained = 0`,
     except a pass summary row whose `pass_id` has a retained row (`:133-168`).
- Triggers: the retention cron and `POST /updatronix/v1/logs/cleanup`.
- Test: `tests/Integration/LogRetentionTest.php:89,97,122`.

### Schema version and upgrade path

- `SCHEMA_VERSION = '3'` (`inc/classes/class-updatronix-database.php:35`);
  `DB_VERSION` is an alias (`:42`).
- The stored version is the `updatronix_log_db_version` option
  (`OPTION_DB_VERSION`, `:25`; `inc/core/constants.php:56`).
- `maybe_upgrade()` compares the stored version, takes
  `updatronix_log_schema_upgrade_lock` for 60 seconds, runs `install_schema()`
  with `dbDelta`, backfills the delay prefix, and writes the new version
  (`:128-156`). A second call is a no-op.
- `create_table()` runs the same installation on activation (`:99-115`).
- `install_schema()` is idempotent; `dbDelta` adds missing columns and indexes
  (`:177-238`). `backfill_delay_prefix()` only backfills the deterministic
  `udly|` event-key prefix (`:250`).
- Test: `tests/Integration/LogSchemaMigrationTest.php:78,147,159,188,217`.

### Stable and internal fields

- Stable: the table name; every column except `message`, `trace`, and `context`;
  the schema version; the `updatronix_settings` and
  `updatronix_network_schedule` keys; the native `auto_update_*` options; and the
  retention rule.
- Internal: `message`, `trace`, and `context`; all pending-state, export,
  rate-limit, changelog, retry, and licence-throttle transients; the upgrade
  lock; the Pro options in the table above; and every write to `retained`.

## Examples

All five examples ran on the DDEV site with `wp_set_current_user( 6 )` and
`rest_do_request()`. The full commands, output, and cleanup are in
`.agents/tasks/2026-11-07-docs/examples-developer-api-en.md`.

### Example 1: log a custom external update

```php
wp_set_current_user( 6 );

add_action( 'updatronix_external_update_logged', function ( $log_id, $args ) {
	// The external update is stored; notify your deployment dashboard.
}, 10, 2 );

$result = Updatronix_External_Log::log(
	array(
		'item_name'      => 'Demo External Update',
		'item_slug'      => 'demo/external-update',
		'source'         => 'custom',
		'version_before' => '1.0.0',
		'version_after'  => '1.0.1',
		'status'         => 'success',
		'event_key'      => 'demo|external|' . time(),
	)
);

if ( is_wp_error( $result ) ) {
	// Handle the validation or storage error.
}
```

Observed: `log_id=20`, `hook_fired=yes`, `hook_source=custom`,
`cleanup_deleted=yes`. The demo row was deleted.

### Example 2: read the log through `updatronix/v1/logs`

```php
wp_set_current_user( 6 );

$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
$request->set_query_params( array( 'per_page' => 5 ) );
$response = rest_do_request( $request );
$data     = $response->get_data();

echo $response->get_status(); // 200
echo $data['total'];          // 17
```

Observed: `http_status=200`, `total=17`, `rows=5`, `first_id=17`.

### Example 3: react to a stored entry through `updatronix_after_log`

```php
wp_set_current_user( 6 );

add_action( 'updatronix_after_log', function ( $log_id, $data ) {
	// Mirror the new row.
}, 10, 2 );

$id = Updatronix_Logger::log(
	'plugin',
	'update',
	'Demo Hook Entry',
	'demo/hook-entry',
	'1.0.0',
	'1.0.1',
	'success',
	'Demo after_log entry.',
	'',
	'manual',
	'single',
	'demo|after-log|' . time()
);
```

Observed: `log_id=21`, `hook_fired=yes`, `hook_log_id=21`,
`hook_item_name=Demo Hook Entry`, `cleanup_deleted=yes`.

`Updatronix_Logger` is internal. For an integration, the supported surface is
the `updatronix_after_log` action; the example uses the logger only to create the
row that fires it.

### Example 4: change an auto-update decision through a filter

```php
wp_set_current_user( 6 );

add_filter( 'updatronix_auto_update_can_toggle', function ( $can_toggle, $type, $id, $enable ) {
	if ( 'plugin' === $type && 'koko-analytics/koko-analytics.php' === $id ) {
		return false;
	}
	return $can_toggle;
}, 99, 4 );

$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/plugin' );
$request->set_body_params(
	array(
		'plugin' => 'koko-analytics/koko-analytics.php',
		'enable' => false,
	)
);
$response = rest_do_request( $request );
```

Observed: `http_status=403`,
`message=This item is managed by an external update manager and cannot be toggled here.`
No option was written; the filter was removed after the call.

### Example 5: read the Pro shield state

```php
wp_set_current_user( 6 );

$request  = new WP_REST_Request( 'GET', '/updatronix-pro/v1/shield/settings' );
$response = rest_do_request( $request );
$data     = $response->get_data();

echo $response->get_status(); // 200 when the licence is active
echo $data['safe_mode']['enabled'];
```

Observed: `shield_http=200`, `safe_mode=on`, `blocked_plugins=1`;
`license_http=200`, `license_state=active`, `license_dev_mode=no`. If the licence
is inactive, the route returns 403; that is the documented contract.

## Versioning and deprecations

### `@since` convention

- Every hook, filter, and stable method carries `@since <plugin version>` in its
  docblock. The version is the first release that shipped the symbol.
- When the source has no tag and the changelog does not establish one, this guide
  says “since not declared.” It never invents a version.
- Eleven hook names have no declared version: `updatronix_after_save_settings`,
  `updatronix_before_update_check`, `updatronix_admin_brand`,
  `updatronix_auto_update_can_toggle`, `updatronix_auto_update_plugin_data`,
  `updatronix_auto_update_theme_data`, `updatronix_can_manage_pro`,
  `updatronix_export_max_rows`, `updatronix_license_active`,
  `updatronix_skip_auto_update_delay`, and
  `updatronix_pro_update_pass_max_attempts`.
- Six call sites lack a local tag while the hook name has a version elsewhere:
  `updatronix_log_query_args`, `updatronix_pro_flow_max_chains`,
  `updatronix_pro_flow_max_steps`, `updatronix_pro_update_pass_enabled`,
  `updatronix_schedule_item_state`, and `updatronix_schedule_offer_ttl`. The
  guide uses the established hook version and notes the call-site gap.

### Plugin versions

- Free plugin: 2.0.1.
- Pro plugin: 0.8.1.
- The log schema is versioned separately: `SCHEMA_VERSION = 3`.
- The guide is verified against WordPress 7.1.4 and PHP 8.4.27; the supported
  minimums are WordPress 6.2 and PHP 8.1.

### Renaming and removing

- A hook, filter, route, or stable method is deprecated before it is removed. The
  old name stays for at least one minor release and the new name is documented
  beside it.
- A removed hook is a breaking change; it is not silently dropped from this
  guide.
- The core `wp_maybe_auto_update` hook follows WordPress core's policy; this
  guide's deprecation policy does not apply to it.
- Internal classes are not covered by the deprecation policy. Use the hook,
  filter, or REST route instead.

## Verification

### Counts

| Item | Count | Source |
|---|---|---|
| Updatronix hook entries | 62 | 9 Free actions + 29 Free-only filters + 22 Pro-only filters + 2 shared |
| Core-hook note | 1 | `wp_maybe_auto_update` |
| Hook rows if the core note is counted | 63 | 62 + 1 |
| Free REST routes | 17 | `routes-verified-developer-api-en.md` |
| Pro REST routes | 10 | `routes-verified-developer-api-en.md` |
| Total REST routes | 27 | 17 + 10 |
| Stable PHP symbols | 4 | Public PHP API |
| Log table columns | 30 | `inc/classes/class-updatronix-database.php:185-229` |
| Log schema version | 3 | `:35` |

### Tested examples

Examples 1–5 ran on the DDEV site. The read-only routes returned 200 in the
smoke run; the write routes returned 401 (or 400 at argument validation) for an
anonymous user. The demo rows were deleted: `total_rows=17`, `demo_rows=0`,
`export_pointer=empty`.

### Checker results

- `bin/harness exec php tools/check_docs.php`: 12 files, 0 errors, 0 warnings.
- `bin/harness docs check`: `docs/user: OK`.
- `git diff --check`: no whitespace errors.
- The guide has nine H2 sections, matching `docs/developer-api/fr.md`.

### Findings

1. Eleven hook names have no `@since`; they are documented as “since not
   declared” and listed above.
2. Six call sites lack a local `@since`; the hook version is established at
   another call site or in the changelog.
3. `/run-check` is registered in the code but absent from the brief's route
   list; it is included.
4. The schema has 30 columns, not 28.
5. The 63-hook count includes the core `wp_maybe_auto_update` note; the
   Updatronix entries are 62.
6. Every route has a permission callback.
7. The demo licence is active, so the shield route returned 200; the 403
   contract is documented for an inactive licence.
8. The `updatronix_schedule_item_state` test through `pro_facts()` did not change
   the decision; the tested filter example uses
   `updatronix_auto_update_can_toggle` and the REST route.
