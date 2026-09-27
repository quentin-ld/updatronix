<?php

/**
 * PHPUnit bootstrap for all suites.
 *
 * - **Unit** (default): minimal stubs, no WordPress — fast, no DB.
 * - **Integration**: set `UPDATRONIX_INTEGRATION_TESTS=1` (see `.config/phpunit.integration.xml.dist`) to load
 *   `wordpress-tests-lib` and the full plugin.
 *
 * @package updatronix
 */

declare(strict_types=1);

$updatronix_integration = getenv('UPDATRONIX_INTEGRATION_TESTS');
if ($updatronix_integration === '1' || $updatronix_integration === 'true') {
    if ('cli' !== \PHP_SAPI && 'phpdbg' !== \PHP_SAPI) {
        exit;
    }

    $updatronix_plugin_root = dirname(__DIR__);

    require_once $updatronix_plugin_root . '/vendor/autoload.php';
    require_once $updatronix_plugin_root . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

    $updatronix_wp_tests_dir = getenv('WP_TESTS_DIR');
    if (!is_string($updatronix_wp_tests_dir) || $updatronix_wp_tests_dir === '') {
        $updatronix_wp_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
    }

    if (!is_file($updatronix_wp_tests_dir . '/includes/functions.php')) {
        die(
            "WordPress test library not found.\n"
            . "Install it (from the plugin directory):\n"
            . "  bash bin/install-wp-tests.sh <db-name> <user> <pass> <host> latest\n"
            . "Or set WP_TESTS_DIR to an existing wordpress-tests-lib path.\n"
        );
    }

    require_once $updatronix_wp_tests_dir . '/includes/functions.php';

    /**
     * Load the plugin under test (same pattern as WP-CLI scaffold).
     *
     * @return void
     */
    function updatronix_tests_load_plugin(): void {
        require dirname(__DIR__) . '/updatronix.php';
    }

    tests_add_filter('muplugins_loaded', 'updatronix_tests_load_plugin');

    /**
     * Ensure Administrator has the plugin cap in tests (activation migration does not run here).
     *
     * @return void
     */
    function updatronix_tests_ensure_admin_cap(): void {
        if (!defined('UPDATRONIX_CAP_MANAGE')) {
            return;
        }
        $role = get_role('administrator');
        if ($role && !$role->has_cap(UPDATRONIX_CAP_MANAGE)) {
            $role->add_cap(UPDATRONIX_CAP_MANAGE);
        }
    }

    tests_add_filter('init', 'updatronix_tests_ensure_admin_cap', 0);

    require $updatronix_wp_tests_dir . '/includes/bootstrap.php';

    return;
}

// --- Unit suite (no WordPress) ---

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

require_once __DIR__ . '/stubs/WP_Error.php';
require_once __DIR__ . '/stubs/WP_REST_Request.php';

if (!function_exists('wp_strip_all_tags')) {
    /**
     * Stub for wp_strip_all_tags used in unit tests without WordPress loaded.
     *
     * @param string|mixed $text    Text to strip.
     * @param bool         $remove_breaks Whether to remove line breaks.
     * @return string
     */
    function wp_strip_all_tags($text, bool $remove_breaks = false): string {
        if (!is_string($text)) {
            return '';
        }

        return strip_tags($text);
    }
}

if (!function_exists('__')) {
    /**
     * Identity stub for the i18n `__()` function (unit suite has no WordPress).
     *
     * @param string $text   Text to translate.
     * @param string $domain Text domain (ignored).
     * @return string The text unchanged.
     */
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('add_filter')) {
    /**
     * Minimal filter registry stub for unit tests without WordPress.
     *
     * Holds registered callbacks in a static array keyed by tag.
     * Each callback is stored as [callable, int $priority, int $accepted_args].
     *
     * @var array<string, list<array{callable, int, int}>>
     */
    $GLOBALS['_updatronix_filters'] = [];

    /**
     * Register a filter callback.
     *
     * @param string   $tag             Filter name.
     * @param callable $callback        Callback.
     * @param int      $priority        Priority (default 10).
     * @param int      $accepted_args   Number of accepted args (default 1).
     * @return void
     */
    function add_filter(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): void {
        $GLOBALS['_updatronix_filters'][$tag][] = [$callback, $priority, $accepted_args];
    }

    /**
     * Remove a filter callback.
     *
     * @param string   $tag      Filter name.
     * @param callable $callback The exact callback to remove.
     * @param int      $priority Priority (default 10).
     * @return void
     */
    function remove_filter(string $tag, callable $callback, int $priority = 10): void {
        if (!isset($GLOBALS['_updatronix_filters'][$tag])) {
            return;
        }
        foreach ($GLOBALS['_updatronix_filters'][$tag] as $idx => [$cb, $prio]) {
            if ($prio === $priority && $cb === $callback) {
                unset($GLOBALS['_updatronix_filters'][$tag][$idx]);
                break;
            }
        }
    }

    /**
     * Apply registered filters to a value, in priority order.
     *
     * @param string $tag   Filter name.
     * @param mixed  $value The value to filter.
     * @param mixed  ...$_  Additional args (unused).
     * @return mixed The filtered value.
     */
    function apply_filters(string $tag, $value, ...$_) {
        if (!isset($GLOBALS['_updatronix_filters'][$tag])) {
            return $value;
        }
        // Sort by priority (stable sort kept by insertion order for equal priorities).
        $callbacks = $GLOBALS['_updatronix_filters'][$tag];
        usort($callbacks, static function (array $a, array $b): int {
            return $a[1] <=> $b[1];
        });
        // Mirror WordPress: each callback receives the filtered value plus up to
        // (accepted_args - 1) extra args, floored at the value itself.
        $full = array_merge([$value], array_values($_));
        foreach ($callbacks as [$cb, , $acceptedArgs]) {
            $cbArgs = array_slice($full, 0, max(1, (int) $acceptedArgs));
            $value  = $cb(...$cbArgs);
        }

        return $value;
    }
}

if (!function_exists('add_action')) {
    /**
     * Stub for add_action (same as add_filter for unit tests).
     *
     * @param string   $tag             Action name.
     * @param callable $callback        Callback.
     * @param int      $priority        Priority (default 10).
     * @param int      $accepted_args   Number of accepted args (default 1).
     * @return void
     */
    function add_action(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): void {
        $GLOBALS['_updatronix_filters'][$tag][] = [$callback, $priority, $accepted_args];
    }
}

if (!function_exists('remove_action')) {
    /**
     * Stub for remove_action (same as remove_filter for unit tests).
     *
     * @param string   $tag      Action name.
     * @param callable $callback The exact callback to remove.
     * @param int      $priority Priority (default 10).
     * @return void
     */
    function remove_action(string $tag, callable $callback, int $priority = 10): void {
        if (!isset($GLOBALS['_updatronix_filters'][$tag])) {
            return;
        }
        foreach ($GLOBALS['_updatronix_filters'][$tag] as $idx => [$cb, $prio]) {
            if ($prio === $priority && $cb === $callback) {
                unset($GLOBALS['_updatronix_filters'][$tag][$idx]);
                break;
            }
        }
    }
}

if (!function_exists('do_action')) {
    /**
     * Stub for do_action (runs callbacks registered via add_action/add_filter).
     *
     * @param string $tag Action name.
     * @param mixed  ...$_  Additional args (unused).
     * @return void
     */
    function do_action(string $tag, ...$_): void {
        if (!isset($GLOBALS['_updatronix_filters'][$tag])) {
            return;
        }
        $callbacks = $GLOBALS['_updatronix_filters'][$tag];
        usort($callbacks, static function (array $a, array $b): int {
            return $a[1] <=> $b[1];
        });
        foreach ($callbacks as [$cb]) {
            $cb();
        }
    }
}

if (!function_exists('register_setting')) {
    /**
     * Stub for register_setting used in unit tests.
     *
     * @param string $option_group Option group.
     * @param string $option_name  Option name.
     * @param array  $args         Registration args.
     * @return void
     */
    function register_setting(string $option_group, string $option_name, array $args = []): void {
    }
}

if (!function_exists('get_role')) {
    /**
     * Stub for get_role used in unit tests.
     *
     * @param string $role Role name.
     * @return null
     */
    function get_role(string $role) {
        return null;
    }
}

if (!function_exists('get_option')) {
    /**
     * Global test option store backing get_option()/get_site_option() in unit tests.
     *
     * @var array<string, mixed>
     */
    $GLOBALS['updatronix_test_options'] = [];

    /**
     * Autoload flag per option, as passed to update_option() by the code under test.
     *
     * @var array<string, bool|null>
     */
    $GLOBALS['updatronix_test_option_autoload'] = [];

    /**
     * Stub for get_option used in unit tests.
     *
     * Reads the test option store; falls back to $default when unset.
     *
     * @param string $option  Option name.
     * @param mixed  $default Default value.
     * @return mixed
     */
    function get_option(string $option, $default = false) {
        if (array_key_exists($option, $GLOBALS['updatronix_test_options'])) {
            return $GLOBALS['updatronix_test_options'][$option];
        }

        return $default;
    }
}

if (!function_exists('update_option')) {
    /**
     * Stub for update_option used in unit tests.
     *
     * Writes the test option store and reports success.
     *
     * @param string $option   Option name.
     * @param mixed  $value    Option value.
     * @param mixed  $autoload Autoload flag.
     * @return bool
     */
    function update_option(string $option, $value, $autoload = null): bool {
        $GLOBALS['updatronix_test_options'][$option] = $value;
        // Recorded, and not turned into an assertion here: whether an option is
        // written with autoload on is a decision of the code under test, and a
        // stub that drops the third argument cannot see it.
        $GLOBALS['updatronix_test_option_autoload'][$option] = $autoload;

        return true;
    }
}

if (!function_exists('delete_option')) {
    /**
     * Stub for delete_option used in unit tests.
     *
     * @param string $option Option name.
     * @return bool
     */
    function delete_option(string $option): bool {
        unset( $GLOBALS['updatronix_test_options'][ $option ] );

        return true;
    }
}

if (!function_exists('delete_site_option')) {
    /**
     * Stub for delete_site_option used in unit tests (single-site semantics).
     *
     * @param string $option Option name.
     * @return bool
     */
    function delete_site_option(string $option): bool {
        return delete_option( $option );
    }
}

if (!function_exists('get_site_option')) {
    /**
     * Stub for get_site_option used in unit tests (single-site semantics).
     *
     * @param string $option  Option name.
     * @param mixed  $default Default value.
     * @return mixed
     */
    function get_site_option(string $option, $default = false) {
        return get_option($option, $default);
    }
}

if (!function_exists('update_site_option')) {
    /**
     * Stub for update_site_option used in unit tests (single-site semantics).
     *
     * @param string $option Option name.
     * @param mixed  $value  Option value.
     * @return bool
     */
    function update_site_option(string $option, $value): bool {
        update_option($option, $value);

        return true;
    }
}

if (!function_exists('is_multisite')) {
    /**
     * Global test override for is_multisite().
     *
     * @var bool|null
     */
    $GLOBALS['updatronix_test_is_multisite'] = null;

    /**
     * Stub for is_multisite used in unit tests.
     *
     * @return bool
     */
    function is_multisite(): bool {
        return (bool) $GLOBALS['updatronix_test_is_multisite'];
    }
}

if (!function_exists('get_site_transient')) {
    /**
     * Global test site-transient store backing get_site_transient()/set_site_transient().
     *
     * @var array<string, mixed>
     */
    $GLOBALS['updatronix_test_site_transients'] = [];

    /**
     * Stub for get_site_transient used in unit tests.
     *
     * @param string $key Transient key.
     * @return mixed
     */
    function get_site_transient(string $key) {
        if (array_key_exists($key, $GLOBALS['updatronix_test_site_transients'])) {
            return $GLOBALS['updatronix_test_site_transients'][$key];
        }

        return false;
    }
}

if (!function_exists('set_site_transient')) {
    /**
     * Stub for set_site_transient used in unit tests.
     *
     * @param string $key        Transient key.
     * @param mixed  $value      Transient value.
     * @param int    $expiration Expiration in seconds.
     * @return bool
     */
    function set_site_transient(string $key, $value, int $expiration = 0): bool {
        $GLOBALS['updatronix_test_site_transients'][$key] = $value;

        return true;
    }
}

if (!function_exists('wp_get_translation_updates')) {
    /**
     * Global test override for wp_get_translation_updates().
     *
     * @var list<object>
     */
    $GLOBALS['updatronix_test_translation_updates'] = [];

    /**
     * Stub for wp_get_translation_updates used in unit tests.
     *
     * @return list<object>
     */
    function wp_get_translation_updates(): array {
        return $GLOBALS['updatronix_test_translation_updates'];
    }
}

if (!function_exists('updatronix_is_site_health_mock')) {
    /**
     * Stub for updatronix_is_site_health_mock() used in unit tests.
     *
     * Only the well-known synthetic probes bypass the soak gate; the filesystem
     * existence check is deliberately omitted so normal offers are never treated
     * as mocks in the unit environment.
     *
     * @param string $type Update type: 'plugin', 'theme', 'core', 'translation'.
     * @param object $item The update item object.
     * @return bool True for known Site Health synthetic probes.
     */
    function updatronix_is_site_health_mock(string $type, object $item): bool {
        if ('plugin' === $type && isset($item->plugin) && 'a-fake-plugin/a-fake-plugin.php' === $item->plugin) {
            return true;
        }

        if ('theme' === $type && isset($item->theme) && 'a-fake-theme' === $item->theme) {
            return true;
        }

        return false;
    }
}

if (!function_exists('get_plugin_data')) {
    /**
     * Minimal get_plugin_data() stand-in for the deferred-offer audit-log path.
     *
     * @param string $plugin_file Plugin file path (unused).
     * @param bool   $markup      Whether to markup (unused).
     * @return array{Name: string, Version: string}
     */
    function get_plugin_data(string $plugin_file, bool $markup = false): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        return array(
            'Name'    => 'Akismet Test',
            'Version' => '4.0',
        );
    }
}

if (!class_exists('Updatronix_Logger', false)) {
    /**
     * Stub for Updatronix_Logger used in unit tests.
     *
     * Records every log() call so tests can assert that deferred offers write an
     * audit-log row without a database.
     */
    class Updatronix_Logger {
        /**
         * Recorded log() invocations.
         *
         * @var list<array<string, mixed>>
         */
        public static array $calls = [];

        /**
         * Stub log method.
         *
         * @param string $type        Log type.
         * @param string $action      Action.
         * @param string $name        Name.
         * @param string $slug        Slug.
         * @param string $old_version Old version.
         * @param string $new_version New version.
         * @param string $level       Level.
         * @param string $message     Message.
         * @param string $file        File.
         * @param string $method      Method.
         * @param string $mode        Mode.
         * @param string $dedup_key   Dedup key.
         * @return void
         */
        public static function log(
            string $type,
            string $action,
            string $name,
            string $slug,
            string $old_version = '',
            string $new_version = '',
            string $level = 'info',
            string $message = '',
            string $file = '',
            string $method = 'automatic',
            string $mode = 'single',
            string $dedup_key = ''
        ): void {
            self::$calls[] = array(
                'type'        => $type,
                'action'      => $action,
                'name'        => $name,
                'slug'        => $slug,
                'old_version' => $old_version,
                'new_version' => $new_version,
                'level'       => $level,
                'message'     => $message,
                'file'        => $file,
                'method'      => $method,
                'mode'        => $mode,
                'dedup_key'   => $dedup_key,
            );
        }
    }
}

if (!function_exists('filter_input')) {
    /**
     * Stub for filter_input used in unit tests without WordPress loaded.
     *
     * Values are resolved from a test-controlled override registry
     * (`$GLOBALS['_updatronix_filter_input'][$type][$variable_name]`) first, then
     * from the real PHP superglobal matching `$type`. This makes HTTP-input
     * branches (e.g. `updatronix_get_active_tab()`'s `?tab=`) reachable in tests.
     *
     * @param int    $type          Input type (INPUT_GET, INPUT_POST, ...).
     * @param string $variable_name Variable name.
     * @param int    $filter        Filter (default FILTER_UNSAFE_RAW).
     * @param mixed  $options       Options (unused).
     * @return mixed|null The (optionally filtered) value, or null when absent.
     */
    function filter_input(int $type, string $variable_name, int $filter = 516, $options = null) {
        if (isset($GLOBALS['_updatronix_filter_input'][$type]) && array_key_exists($variable_name, $GLOBALS['_updatronix_filter_input'][$type])) {
            $value = $GLOBALS['_updatronix_filter_input'][$type][$variable_name];
        } else {
            switch ($type) {
                case INPUT_POST:
                    $superglobal = $_POST;
                    break;
                case INPUT_GET:
                    $superglobal = $_GET;
                    break;
                case INPUT_COOKIE:
                    $superglobal = $_COOKIE;
                    break;
                case INPUT_ENV:
                    $superglobal = $_ENV;
                    break;
                case INPUT_SERVER:
                    $superglobal = $_SERVER;
                    break;
                default:
                    return null;
            }
            if (!isset($superglobal[$variable_name])) {
                return null;
            }
            $value = $superglobal[$variable_name];
        }

        if (null === $value) {
            return null;
        }

        // Apply the small subset of filters used by the plugin; everything else
        // (including FILTER_UNSAFE_RAW, the default) is passed through raw.
        switch ($filter) {
            case FILTER_SANITIZE_NUMBER_INT:
                $value = (string) preg_replace('/[^0-9+-]/', '', (string) $value);
                break;
            case FILTER_SANITIZE_NUMBER_FLOAT:
                $value = (string) preg_replace('/[^0-9.+-]/', '', (string) $value);
                break;
            case FILTER_VALIDATE_BOOLEAN:
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
        }

        return $value;
    }
}

require_once dirname(__DIR__) . '/inc/core/tabs.php';

if (!function_exists('sanitize_key')) {
    /**
     * Minimal stub mirroring WordPress `sanitize_key()` for unit tests.
     *
     * @param string $key Raw key.
     * @return string Lowercased key limited to `a-z0-9_-`.
     */
    function sanitize_key($key): string {
        $key = strtolower((string) $key);

        return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

require_once dirname(__DIR__) . '/inc/classes/class-updatronix-core-update-log-versions.php';
require_once dirname(__DIR__) . '/inc/classes/class-updatronix-automatic-update-result-notes.php';
require_once dirname(__DIR__) . '/inc/classes/class-updatronix-export.php';
require_once dirname(__DIR__) . '/inc/classes/class-updatronix-export-body-builder.php';

if (!function_exists('wp_json_encode')) {
    /**
     * Minimal stub for wp_json_encode used in unit tests.
     *
     * @param mixed $data Data to encode.
     * @param int   $options Options (unused).
     * @param int   $depth Depth (unused).
     * @return string|false
     */
    function wp_json_encode($data, int $options = 0, int $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('sanitize_text_field')) {
    /**
     * Minimal stub for sanitize_text_field used in unit tests.
     *
     * @param string $str String to sanitize.
     * @return string
     */
    function sanitize_text_field(string $str): string {
        return trim(preg_replace('/[\x00-\x1F\x7F]/', '', $str) ?? $str);
    }
}

if (!function_exists('wp_date')) {
    /**
     * Minimal stub for wp_date used in unit tests.
     *
     * @param string $format Date format.
     * @param int    $timestamp Timestamp.
     * @return string
     */
    function wp_date(string $format, int $timestamp = 0): string {
        return date($format, $timestamp ?: time());
    }
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/tmp/plugins');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wp-content');
}

if (!function_exists('wp_unslash')) {
    /**
     * Minimal stub for wp_unslash used in unit tests.
     *
     * @param string $value String to unslash.
     * @return string
     */
    function wp_unslash(string $value): string {
        return stripslashes($value);
    }
}

if (!function_exists('untrailingslashit')) {
    /**
     * Minimal stub for untrailingslashit used in unit tests.
     *
     * @param string $value String to untrailingslashit.
     * @return string
     */
    function untrailingslashit(string $value): string {
        return rtrim($value, '/\\');
    }
}

if (!function_exists('get_current_user_id')) {
    /**
     * Stub for get_current_user_id used in unit tests (no session).
     *
     * @return int Always 0 in the unit sandbox.
     */
    function get_current_user_id(): int {
        return 0;
    }
}

if (!function_exists('switch_to_user_locale')) {
    /**
     * Stub for switch_to_user_locale used in unit tests.
     *
     * Returns false (no WP locale machinery) so callers with a `finally`-style
     * restore never invoke restore_previous_locale().
     *
     * @param string|false $locale Locale to switch to (ignored).
     * @return bool Always false.
     */
    function switch_to_user_locale($locale = false): bool {
        return false;
    }
}

if (!function_exists('restore_previous_locale')) {
    /**
     * Stub for restore_previous_locale used in unit tests.
     *
     * @return void
     */
    function restore_previous_locale(): void {
    }
}

if (!function_exists('get_userdata')) {
    /**
     * Stub for get_userdata used in unit tests (no WP user rows).
     *
     * @param int $user_id User ID.
     * @return false Always false.
     */
    function get_userdata(int $user_id) {
        return false;
    }
}

if (!function_exists('cache_users')) {
    /**
     * Stub for cache_users used in unit tests (no user cache to prime).
     *
     * @param array<int, int> $user_ids User IDs.
     * @return void
     */
    function cache_users(array $user_ids): void {
    }
}

if (!function_exists('wp_timezone')) {
    /**
     * Stub for wp_timezone used in unit tests.
     *
     * @return null No real timezone object; callers pass it to wp_date() which ignores it.
     */
    function wp_timezone() {
        return null;
    }
}

if ( ! defined( 'UPDATRONIX_VERSION' ) ) {
	$updatronix_version_source = (string) file_get_contents( dirname( __DIR__ ) . '/updatronix.php' );
	if ( preg_match( '/Version:\s*([0-9.]+)/', $updatronix_version_source, $updatronix_version_match ) ) {
		define( 'UPDATRONIX_VERSION', $updatronix_version_match[1] );
	} else {
		define( 'UPDATRONIX_VERSION', '0.0.0' );
	}
	unset( $updatronix_version_source, $updatronix_version_match );
}

if ( ! defined( 'UPDATRONIX_PLUGIN_FILE' ) ) {
	define( 'UPDATRONIX_PLUGIN_FILE', dirname( __DIR__ ) . '/updatronix.php' );
}

if ( ! defined( 'UPDATRONIX_PLUGIN_DIR' ) ) {
	define( 'UPDATRONIX_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

// Pin the plugin capability + DB constants from the production constants file, so the
// unit suite can never drift from what the plugin actually ships (no hard-coded copies).
require_once dirname( __DIR__ ) . '/inc/core/constants.php';

if (!function_exists('current_user_can')) {
    /**
     * Global test override for current_user_can(UPDATRONIX_CAP_MANAGE).
     *
     * @var bool
     */
    $GLOBALS['updatronix_test_can_manage'] = false;

    /**
     * Stub for current_user_can used in unit tests.
     *
     * Returns the test-controlled flag for the plugin cap; any other cap is false.
     *
     * @param string $capability Capability name.
     * @param mixed  ...$_        Extra args (ignored).
     * @return bool
     */
    function current_user_can(string $capability, ...$_): bool {
        if (UPDATRONIX_CAP_MANAGE === $capability) {
            return (bool) $GLOBALS['updatronix_test_can_manage'];
        }

        return false;
    }
}

if (!function_exists('is_super_admin')) {
    /**
     * Global test override for is_super_admin().
     *
     * @var bool
     */
    $GLOBALS['updatronix_test_is_super_admin'] = false;

    /**
     * Stub for is_super_admin used in unit tests.
     *
     * @param int|false $user_id User ID (ignored).
     * @return bool
     */
    function is_super_admin($user_id = false): bool {
        return (bool) $GLOBALS['updatronix_test_is_super_admin'];
    }
}

if (!function_exists('get_current_blog_id')) {
    /**
     * Global test override for get_current_blog_id().
     *
     * @var int
     */
    $GLOBALS['updatronix_test_current_blog_id'] = 1;

    /**
     * Stub for get_current_blog_id used in unit tests (defaults to the main site).
     *
     * @return int
     */
    function get_current_blog_id(): int {
        return (int) $GLOBALS['updatronix_test_current_blog_id'];
    }
}

if (!function_exists('get_main_site_id')) {
    /**
     * Global test override for get_main_site_id().
     *
     * @var int
     */
    $GLOBALS['updatronix_test_main_site_id'] = 1;

    /**
     * Stub for get_main_site_id used in unit tests.
     *
     * @return int
     */
    function get_main_site_id(): int {
        return (int) $GLOBALS['updatronix_test_main_site_id'];
    }
}

if (!function_exists('absint')) {
    /**
     * Stub for absint used in unit tests (no WordPress loaded).
     *
     * @param mixed $maybeint Value to coerce.
     * @return int Non-negative integer, or 0 when the value cannot be coerced.
     */
    function absint($maybeint): int {
        return abs((int) $maybeint);
    }
}

require_once dirname(__DIR__) . '/inc/classes/class-updatronix-autoupdatedelay.php';
require_once dirname(__DIR__) . '/inc/classes/class-updatronix-security.php';
