# Test inventory — 2026-10-29

Raw inventory of every PHPUnit, Vitest, and Playwright case in both repos,
with the assertions the suite actually makes. This is the input to
`docs/testing/audit-2026-10-29.md`; it is not the test book.

**Method:** PHP test methods are matched by `public function test_*`; the
first four `assert*`, `expectException`, or `markTestSkipped` calls in each
method are recorded. JS/E2E cases are matched by `test()`/`it()`; the first
four `expect` or request assertions are recorded. Data providers are named
where declared. A row with no assertion is marked **NONE** and is a finding.

## Counts

| Repo | Suite | Files | Cases |
|---|---|---:|---:|
| free | Unit | 21 | 238 |
| free | Integration | 26 | 194 |
| free | JS | 15 | 132 |
| free | E2E | 9 | 29 |
| pro | Unit | 36 | 470 |
| pro | Integration | 16 | 94 |
| pro | JS | 6 | 80 |
| pro | E2E | 12 | 28 |

## Counts caveat

Counts are declared test methods/blocks. A PHP data provider or a JS `it.each` table expands one declaration into several runs, so the runner count is higher: Free unit 315 runs from 238 methods, Free integration 196 runs from 194 methods, Pro JS 88 runs from 80 blocks.

- `tests/js/shieldState.test.js` carries one 8-row `it.each` for `reenableItemState`: eligible, waiting_for_newer_release, no_offer, policy_blocked, paused, user_reenabled, toggle_failed, unknown_blocked_target.

## Extraction limits

- A test that asserts through a helper or a snapshot may show the helper call rather than the final value; the row still points to the file and method.
- PHP data-provider rows are recorded once with the provider name; the expanded rows are not listed individually.
- Pro JS `it.each` rows are recorded as one block; the eight states are listed above.

## FREE

### Unit (238 cases)

#### `tests/Unit/AdminTabsTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_default_tabs_returns_four_tabs` | test default tabs returns four tabs | $this->assertCount( 4, $tabs );<br>$this->assertArrayHasKey( 'logs', $tabs );<br>$this->assertArrayHasKey( 'auto-updates', $tabs );<br>$this->assertArrayHasKey( 'schedule', $tabs ); |
| `test_default_tabs_are_sorted_by_priority` | test default tabs are sorted by priority | $this->assertSame( array( 'logs', 'auto-updates', 'schedule', 'settings' ), $slugs ); |
| `test_each_tab_has_required_keys` | test each tab has required keys | $this->assertIsArray( $tab, "Tab {$slug} should be an array" );<br>$this->assertArrayHasKey( 'slug', $tab, "Tab {$slug} missing slug" );<br>$this->assertArrayHasKey( 'label', $tab, "Tab {$slug} missing label" );<br>$this->assertArrayHasKey( 'icon', $tab, "Tab {$slug} missing icon" ); |
| `test_active_tab_defaults_to_first_tab` | test active tab defaults to first tab | $this->assertSame( 'logs', $active ); |
| `test_active_tab_resolves_overrideable_filter_input_branch` | test active tab resolves overrideable filter input branch | $this->assertSame( 'settings', $active, 'The ?tab= input branch must resolve a known tab.' );<br>$this->assertSame( 'logs', $active, 'Without a tab param the first tab must win.' ); |
| `test_active_tab_falls_back_when_requested_tab_unknown` | test active tab falls back when requested tab unknown | $this->assertSame( 'logs', $active ); |
| `test_default_tab_definitions_have_priority` | test default tab definitions have priority | $this->assertArrayHasKey( 'priority', $tab, "Default tab {$slug} missing priority" );<br>$this->assertIsInt( $tab['priority'], "Default tab {$slug} priority must be int" ); |
| `test_filter_can_append_custom_tab` | test filter can append custom tab | $this->assertArrayHasKey( 'probe-tab', $tabs );<br>$this->assertSame( 'probe-tab', $tabs['probe-tab']['slug'] );<br>$this->assertSame( 'Probe', $tabs['probe-tab']['label'] );<br>$this->assertSame( '', $tabs['probe-tab']['icon'] ); |
| `test_filter_respects_priority_sort_after_append` | test filter respects priority sort after append | $this->assertSame( 'logs', $slugs[0] );<br>$this->assertSame( 'settings', $slugs[3] );<br>$this->assertSame( 'probe-tab', $slugs[4] ); |

#### `tests/Unit/AutoUpdateDelayBehaviorTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_register_filters_noop_when_delay_disabled` | test register filters noop when delay disabled | $this->assertArrayNotHasKey( $hook, $GLOBALS['_updatronix_filters'], "$hook must not be registered when delay is disabled." ); |
| `test_register_filters_hooks_all_types_when_enabled` | test register filters hooks all types when enabled | $this->assertNotEmpty( $GLOBALS['_updatronix_filters'][ $hook ], "$hook must be registered when delay is enabled." ); |
| `test_fresh_offer_is_deferred_and_audit_logged` | test fresh offer is deferred and audit logged | $this->assertFalse( $result, 'A freshly detected offer must be held back by the soak window.' );<br>$this->assertCount( 1, $ledger );<br>$this->assertCount( 1, Updatronix_Logger::$calls );<br>$this->assertSame( 'plugin', $log_call['type'] ); |
| `test_mature_offer_passes_after_soak_window` | test mature offer passes after soak window | $this->assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( true, $offer ) );<br>$this->assertTrue( $result, 'An offer past the soak window must be allowed through.' );<br>$this->assertArrayHasKey( $hash, $this->ledger(), 'The matured row must remain in the ledger.' ); |
| `test_schedule_slice_coerces_the_stored_flag_and_clamps_the_window` | test schedule slice coerces the stored flag and clamps the window | $this->assertSame( $case[3], $slice['days'] ?? null, "$label: the soak window must be clamped to 1..365 whole days." );<br>$this->assertSame( $case[2], $slice['enabled'] ?? null, "$label: the stored flag must reach the slice as a real boolean." ); |
| `test_prune_reads_bare_transients_without_running_over_them` | test prune reads bare transients without running over them | $this->assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() ), 'The first sighting is deferred, and the prune runs before the row is written.' ); |
| `test_ledger_is_written_with_autoload_off` | test ledger is written with autoload off | $this->assertSame( false, $GLOBALS['updatronix_test_option_autoload'][ Updatronix_AutoUpdateDelay::OPTION_LEDGER ] ?? null, 'The delay ledger must not autoload.' ); |
| `test_prune_keeps_a_theme_row_the_transient_still_offers` | test prune keeps a theme row the transient still offers | $this->assertTrue( Updatronix_AutoUpdateDelay::filter_theme( true, $offer ), 'An offer past the soak window must be allowed through.' );<br>$this->assertArrayHasKey( $hash, $ledger, 'The row the update API still offers must not be pruned.' );<br>$this->assertSame( $aged, $ledger[ $hash ], 'And it must keep the first-seen stamp it had: re-seeding restarts the soak window.' ); |
| `test_prune_keeps_a_core_row_the_transient_still_offers` | test prune keeps a core row the transient still offers | $this->assertTrue( Updatronix_AutoUpdateDelay::filter_core( true, $offer ), 'An offer past the soak window must be allowed through.' );<br>$this->assertArrayHasKey( $hash, $ledger, 'The row the update API still offers must not be pruned.' );<br>$this->assertSame( $aged, $ledger[ $hash ], 'And it must keep the first-seen stamp it had: re-seeding restarts the soak window.' ); |
| `test_distinct_offers_get_distinct_ledger_rows` | test distinct offers get distinct ledger rows | $this->assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer( 'akismet/akismet.php', '5.3' ) ) );<br>$this->assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer( 'hello.php', '1.1' ) ) );<br>$this->assertCount( 2, $ledger, 'Two distinct offers must produce two ledger rows.' );<br>$this->assertNotSame( $this->invoke_static( 'stable_ledger_hash', array( 'plugin', $this->plugin_offer( 'akismet/akismet.php', '5.3' ) ) ), $this->invoke_static( 'stable_ledger_hash', array( 'plugin', $this->plugin_offe… |
| `test_site_health_mock_bypasses_soak` | test site health mock bypasses soak | $this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $offer ) );<br>$this->assertSame( array(), $this->ledger() );<br>$this->assertSame( array(), Updatronix_Logger::$calls ); |
| `test_skip_delay_filter_bypasses_soak` | test skip delay filter bypasses soak | $this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() ) );<br>$this->assertSame( array(), $this->ledger() ); |
| `test_delay_seconds_filter_receives_default_and_zero_bypasses` | test delay seconds filter receives default and zero bypasses | $this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() ) );<br>$this->assertSame( 7 * DAY_IN_SECONDS, $captured, 'The filter must receive the canonical 7-day soak in seconds.' );<br>$this->assertSame( array(), $this->ledger(), 'A zero-length soak must not touch the ledger.' ); |
| `test_should_delay_filter_can_force_pass` | test should delay filter can force pass | $this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() ) ); |
| `test_filter_passthrough_when_update_not_true` | test filter passthrough when update not true | $this->assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( false, $this->plugin_offer() ) );<br>$this->assertNull( Updatronix_AutoUpdateDelay::filter_plugin( null, $this->plugin_offer() ) );<br>$this->assertSame( array(), $this->ledger(), 'A non-true incoming decision must not touch the ledger.' ); |
| `test_prune_removes_stale_rows_keeps_live_rows` | test prune removes stale rows keeps live rows | $this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $live_offer ) );<br>$this->assertArrayNotHasKey( $stale_hash, $ledger, 'A row for an offer no longer offered must be pruned.' );<br>$this->assertArrayHasKey( $live_hash, $ledger, 'A row for an offer still offered must survive pruning.' ); |
| `test_ledger_flush_trims_to_cap` | test ledger flush trims to cap | $this->assertCount( 384, $this->ledger(), 'ledger_flush must trim rows to MAX_LEDGER_ENTRIES.' ); |
| `test_filter_theme_and_core_pass_through_a_false_update` | test filter theme and core pass through a false update | $this->assertTrue( Updatronix_AutoUpdateDelay::filter_theme( true, $offer ) );<br>$this->assertFalse( Updatronix_AutoUpdateDelay::filter_theme( false, $offer ), 'a false decision must come back as false, not as the delay verdict' );<br>$this->assertFalse( Updatronix_AutoUpdateDelay::filter_core( false, $offer ), 'a false decision must come back as false, not as the delay verdict' ); |
| `test_resolve_offer_dimensions_rejects_path_traversal` | test resolve offer dimensions rejects path traversal | $this->assertSame( 'Unknown plugin', $result[0], $label );<br>$this->assertSame( '', $result[1], $label );<br>$this->assertSame( '9.9', $result[3], $label );<br>$this->assertSame( '99', $non_string_version[3], 'The version field must be returned as a string.' ); |

#### `tests/Unit/AutoUpdateDelayFilterVisibilityTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_hook_callback_is_public_callback` | test hook callback is public callback (data: `provide_hook_callbacks`) | self::assertTrue( $reflection->isPublic(), sprintf( '%s must be public to be a WordPress hook callback.', $method ) ); |
| `test_hook_callback_is_callable_from_global_scope` | test hook callback is callable from global scope (data: `provide_hook_callbacks`) | self::assertTrue( is_callable( $callable ), sprintf( '%s must be callable via call_user_func_array() from the global scope.', $method ) ); |

#### `tests/Unit/AutoUpdateDelayLedgerTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_plugin_hash_with_plugin_and_new_version` | test plugin hash with plugin and new version | self::assertSame( $this->parts_hash( 'plugin\|akismet/akismet.php\|5.3' ), $hash );<br>self::assertSame( $hash, $this->hash( 'plugin', $item ) ); |
| `test_plugin_hash_is_case_insensitive` | test plugin hash is case insensitive | self::assertSame( $lower, $upper );<br>self::assertSame( $this->parts_hash( 'plugin\|akismet/akismet.php\|5.3' ), $upper ); |
| `test_plugin_hash_without_new_version_falls_back` | test plugin hash without new version falls back | self::assertSame( 64, strlen( $partial ) );<br>self::assertNotSame( $complete, $partial ); |
| `test_plugin_hash_without_plugin_falls_back` | test plugin hash without plugin falls back | self::assertSame( 64, strlen( $partial ) );<br>self::assertNotSame( $complete, $partial ); |
| `test_theme_hash_with_theme_and_new_version` | test theme hash with theme and new version | self::assertSame( $this->parts_hash( 'theme\|twentytwentyfive\|2.0' ), $hash );<br>self::assertSame( $hash, $this->hash( 'theme', $item ) ); |
| `test_theme_hash_is_case_insensitive` | test theme hash is case insensitive | self::assertSame( $lower, $upper );<br>self::assertSame( $this->parts_hash( 'theme\|twentytwentyfive\|2.0' ), $upper ); |
| `test_core_hash_includes_current_and_version` | test core hash includes current and version | self::assertSame( $this->parts_hash( 'core\|6.5.0\|6.6.1' ), $hash );<br>self::assertSame( $hash, $this->hash( 'core', $item ) ); |
| `test_core_hash_differs_when_offered_version_differs` | test core hash differs when offered version differs | self::assertNotSame( $hash_a, $hash_b, 'Two different core offers for the same running version must produce different hashes.' ); |
| `test_core_hash_without_version_uses_current_only` | test core hash without version uses current only | self::assertSame( $this->parts_hash( 'core\|6.5.0\|' ), $hash ); |
| `test_core_hash_with_only_version` | test core hash with only version | self::assertSame( $this->parts_hash( 'core\|6.6.1' ), $hash ); |
| `test_core_hash_without_current_or_version_falls_back` | test core hash without current or version falls back | self::assertSame( 64, strlen( $hash ) );<br>self::assertNotSame( $this->hash( 'core', $complete ), $hash ); |
| `test_translation_hash_with_all_fields` | test translation hash with all fields | self::assertSame( $this->parts_hash( 'translation\|plugin\|akismet\|fr_FR\|1.0' ), $hash );<br>self::assertSame( $hash, $this->hash( 'translation', $item ) ); |
| `test_translation_hash_without_fields_falls_back` | test translation hash without fields falls back | self::assertSame( 64, strlen( $hash ) );<br>self::assertNotSame( $this->hash( 'translation', $complete ), $hash ); |
| `test_same_input_always_produces_same_hash` | test same input always produces same hash | self::assertSame( $this->parts_hash( 'plugin\|hello.php\|1.1' ), $first );<br>self::assertSame( $first, $this->hash( 'plugin', $item ) ); |

#### `tests/Unit/AutoUpdateToggleGuardTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_plugin_toggle_returns_403_for_a_wp_error` | test plugin toggle returns 403 for a wp error | self::assertSame( 403, $response->get_status() );<br>self::assertSame( 'Managed via Git.', $response->get_data()['message'] ); |
| `test_theme_toggle_returns_403_for_false` | test theme toggle returns 403 for false | self::assertSame( 403, $response->get_status() );<br>self::assertStringContainsString( 'external update manager', $response->get_data()['message'] ); |
| `test_plugin_toggle_proceeds_when_allowed` | test plugin toggle proceeds when allowed | self::assertSame( 200, $response->get_status() );<br>self::assertTrue( $response->get_data()['ok'] ); |
| `test_filter_receives_the_toggle_context` | test filter receives the toggle context | self::assertSame( array( 'plugin', 'acme/acme.php', false ), $seen ); |

#### `tests/Unit/AutomaticUpdateResultNotesTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_success_path_returns_only_skin_lines` | test success path returns only skin lines | self::assertSame( "Line one\nLine two", $out ); |
| `test_fs_unavailable_prepended_before_misleading_skin_messages` | test fs unavailable prepended before misleading skin messages | self::assertSame( "[fs_unavailable] Could not access filesystem.\n\nUpdating plugin: Foo\nYou already have the latest version of this plugin.", $out ); |
| `test_wp_error_only_when_messages_empty` | test wp error only when messages empty | self::assertSame( '[disk_full] No space left.', $out ); |
| `test_non_string_message_entries_skipped` | test non string message entries skipped | self::assertSame( 'OK', $out ); |

#### `tests/Unit/CoreUpdateLogVersionsTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_parse_wp_version_from_file_contents_extracts_literal` | test parse wp version from file contents extracts literal | self::assertSame( '6.9.4', Updatronix_Core_Update_Log_Versions::parse_wp_version_from_file_contents( $contents ) ); |
| `test_parse_wp_version_from_file_contents_double_quotes` | test parse wp version from file contents double quotes | self::assertSame( '7.0-RC2', Updatronix_Core_Update_Log_Versions::parse_wp_version_from_file_contents( $contents ) ); |
| `test_parse_wp_version_from_file_contents_empty_when_not_found` | test parse wp version from file contents empty when not found | self::assertSame( '', Updatronix_Core_Update_Log_Versions::parse_wp_version_from_file_contents( '' ) );<br>self::assertSame( '', Updatronix_Core_Update_Log_Versions::parse_wp_version_from_file_contents( 'no version here' ) ); |
| `test_resolve_core_version_after_triple_prefers_disk` | test resolve core version after triple prefers disk | self::assertSame( '6.9.2', Updatronix_Core_Update_Log_Versions::resolve_core_version_after_triple( '6.9.2', '6.9.1', '6.9.0' ) ); |
| `test_resolve_core_version_after_triple_falls_back_to_pending_then_bloginfo` | test resolve core version after triple falls back to pending then bloginfo | self::assertSame( '7.0-RC2', Updatronix_Core_Update_Log_Versions::resolve_core_version_after_triple( '', '7.0-RC2', '6.9.4' ) );<br>self::assertSame( '6.9.4', Updatronix_Core_Update_Log_Versions::resolve_core_version_after_triple( '', '', '6.9.4' ) ); |
| `test_resolve_action_type_downgrade_update_same_version` | test resolve action type downgrade update same version | self::assertSame( 'downgrade', Updatronix_Core_Update_Log_Versions::resolve_action_type( '7.0', '6.9', 'update' ) );<br>self::assertSame( 'update', Updatronix_Core_Update_Log_Versions::resolve_action_type( '6.9', '7.0', 'update' ) );<br>self::assertSame( 'same_version', Updatronix_Core_Update_Log_Versions::resolve_action_type( '6.9.4', '6.9.4', 'update' ) ); |
| `test_resolve_action_type_uses_default_when_either_version_empty` | test resolve action type uses default when either version empty | self::assertSame( 'update', Updatronix_Core_Update_Log_Versions::resolve_action_type( '', '7.0', 'update' ) );<br>self::assertSame( 'install', Updatronix_Core_Update_Log_Versions::resolve_action_type( '1.0', '', 'install' ) ); |

#### `tests/Unit/ExportBodyBuilderRenderTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_render_flat_renders_header_and_row` | test render flat renders header and row | $this->assertArrayHasKey( 'body', $result );<br>$this->assertArrayHasKey( 'rows_emitted', $result );<br>$this->assertArrayHasKey( 'merged_lines_added', $result );<br>$this->assertArrayHasKey( 'byte_cap_hit', $result ); |
| `test_render_merged_sectioned_merges_and_heads` | test render merged sectioned merges and heads | $this->assertSame( 2, $result['rows_emitted'] );<br>$this->assertSame( 1, $result['merged_lines_added'] );<br>$this->assertFalse( $result['byte_cap_hit'] );<br>$this->assertStringContainsString( '== PLUGINS ==', $result['body'] ); |
| `test_render_hits_byte_cap` | test render hits byte cap | $this->assertTrue( $result['byte_cap_hit'] );<br>$this->assertSame( 0, $result['rows_emitted'] );<br>$this->assertSame( 0, $result['merged_lines_added'] ); |
| `test_render_column_toggle_omits_action` | test render column toggle omits action | $this->assertStringNotContainsString( 'Action', $result['body'] );<br>$this->assertStringNotContainsString( 'Update', $result['body'] );<br>$this->assertStringContainsString( 'Akismet', $result['body'] );<br>$this->assertStringContainsString( 'Category', $result['body'] ); |
| `test_render_same_version_collapses_to_single` | test render same version collapses to single | $this->assertStringContainsString( '2.0', $result['body'] );<br>$this->assertStringNotContainsString( '2.0 → 2.0', $result['body'] ); |
| `test_render_delayed_status_labels_as_delayed` | test render delayed status labels as delayed | $this->assertStringContainsString( 'Delayed', $result['body'] );<br>$this->assertStringNotContainsString( 'Success', $result['body'] ); |
| `test_render_cancelled_status_labels_as_cancelled` | test render cancelled status labels as cancelled | $this->assertStringContainsString( 'Cancelled', $result['body'] );<br>$this->assertStringNotContainsString( 'Success', $result['body'] ); |

#### `tests/Unit/ExportBodyBuilderStatusTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_status_rank_parity_delayed_matches_cancelled` | test status rank parity delayed matches cancelled | self::assertSame( 1, $this->status_rank( 'delayed' ) );<br>self::assertSame( $this->status_rank( 'cancelled' ), $this->status_rank( 'delayed' ) ); |
| `test_status_rank_ordering_is_stable` | test status rank ordering is stable | self::assertSame( 0, $this->status_rank( 'success' ) );<br>self::assertSame( 2, $this->status_rank( 'error' ) );<br>self::assertSame( 1, $this->status_rank( 'cancelled' ) );<br>self::assertSame( 1, $this->status_rank( 'delayed' ) ); |
| `test_status_label_delayed_renders_delayed` | test status label delayed renders delayed | self::assertSame( 'Delayed', $this->status_label( 'delayed' ) ); |
| `test_status_label_cancelled_renders_cancelled` | test status label cancelled renders cancelled | self::assertSame( 'Cancelled', $this->status_label( 'cancelled' ) ); |
| `test_status_label_unknown_is_not_success` | test status label unknown is not success | self::assertSame( 'Mystery', $this->status_label( 'mystery' ) );<br>self::assertSame( 'Info', $this->status_label( '' ) ); |

#### `tests/Unit/ExportBodyBuilderTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_normalize_field_strips_control_characters` | test normalize field strips control characters | self::assertSame( 'abc', Updatronix_Export_Body_Builder::normalize_field( "a\x00b\x01c\x7f" ) ); |
| `test_normalize_field_flattens_crlf_and_cr_to_single_space` | test normalize field flattens crlf and cr to single space | self::assertSame( 'line1 line2', Updatronix_Export_Body_Builder::normalize_field( "line1\r\nline2" ) );<br>self::assertSame( 'a b', Updatronix_Export_Body_Builder::normalize_field( "a\rb" ) ); |
| `test_normalize_field_trims_outer_whitespace` | test normalize field trims outer whitespace | self::assertSame( 'hi', Updatronix_Export_Body_Builder::normalize_field( ' hi ' ) ); |
| `test_normalize_field_preserves_internal_tab` | test normalize field preserves internal tab | self::assertSame( "a\tb", Updatronix_Export_Body_Builder::normalize_field( "a\tb" ) ); |
| `test_default_export_columns_are_all_visible` | test default export columns are all visible | **NONE** |
| `test_normalize_export_columns_defaults_when_empty` | test normalize export columns defaults when empty | self::assertSame( Updatronix_Export_Body_Builder::default_export_columns(), Updatronix_Export_Body_Builder::normalize_export_columns( array() ) ); |
| `test_normalize_export_columns_overrides_known_keys` | test normalize export columns overrides known keys | self::assertFalse( $result['status'] );<br>self::assertTrue( $result['user'] ); |
| `test_normalize_export_columns_ignores_unknown_keys` | test normalize export columns ignores unknown keys | self::assertArrayNotHasKey( 'bogus', $result );<br>self::assertFalse( $result['user'] );<br>self::assertCount( 14, $result ); |
| `test_normalize_export_columns_coerces_booleans` | test normalize export columns coerces booleans (data: `boolean_coercion_provider`) | self::assertSame( $expected, $result['status'] ); |
| `test_truncation_footer_reports_counts` | test truncation footer reports counts | self::assertStringContainsString( '3 of 10', $footer ); |

#### `tests/Unit/ExportTransientManagerTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_replace_with_the_same_key_keeps_the_transient` | test replace with the same key keeps the transient | $this->assertTrue( $first );<br>$this->assertSame( $key, get_user_meta( $user_id, Updatronix_Export_Transient_Manager::POINTER_META_KEY, true ) );<br>$this->assertTrue( $second );<br>$this->assertSame( array( 'body' => 'one two' ), updatronix_get_plugin_transient( $key ) ); |
| `test_replace_with_a_new_key_replaces_the_pointer` | test replace with a new key replaces the pointer | $this->assertTrue( Updatronix_Export_Transient_Manager::replace( $user_id, $first, array( 'body' => 'one' ) ) );<br>$this->assertTrue( Updatronix_Export_Transient_Manager::replace( $user_id, $second, array( 'body' => 'two' ) ) );<br>$this->assertSame( $second, get_user_meta( $user_id, Updatronix_Export_Transient_Manager::POINTER_META_KEY, true ) );<br>$this->assertSame( array( 'body' => 'two' ), updatronix_get_plugin_transient( $second ) ); |

#### `tests/Unit/HooksShimTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_apply_filters_passes_value_and_extra_args_up_to_accepted_args` | test apply filters passes value and extra args up to accepted args | $this->assertSame( array( 'base', 'a', 'b' ), $received );<br>$this->assertSame( 'baseab', $result ); |
| `test_apply_filters_defaults_to_single_arg` | test apply filters defaults to single arg | **NONE** |

#### `tests/Unit/LogContextRedactionTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_unknown_keys_and_objects_are_dropped` | test unknown keys and objects are dropped | self::assertSame( 'php_compatibility', $context['policy_id'] );<br>self::assertSame( '8.3', $context['required_php'] );<br>self::assertSame( '8.1', $context['installed_php'] );<br>self::assertTrue( $context['blocking'] ); |
| `test_lists_are_capped` | test lists are capped | self::assertCount( 50, $context['secondary_reasons'] );<br>self::assertTrue( $context['context_truncated'] ); |
| `test_long_strings_are_cut_to_bytes` | test long strings are cut to bytes | self::assertLessThanOrEqual( 1024, strlen( $context['last_error'] ) );<br>self::assertSame( 512, mb_strlen( $context['last_error'] ) );<br>self::assertTrue( $context['context_truncated'] ); |
| `test_total_context_is_capped_at_8kb` | test total context is capped at 8kb | self::assertLessThanOrEqual( 8192, strlen( $json ) );<br>self::assertTrue( $decoded['context_truncated'] );<br>self::assertGreaterThan( 0, $decoded['omitted'] ); |
| `test_every_value_is_redacted` | test every value is redacted | self::assertStringNotContainsString( 'mirror.example.com', $context['policy_message'] );<br>self::assertStringNotContainsString( 'token=abc123', $context['policy_message'] );<br>self::assertStringContainsString( '[redacted-url]', $context['policy_message'] );<br>self::assertStringNotContainsString( '/srv/private', $context['evidence'] ); |
| `test_empty_context_stores_nothing` | test empty context stores nothing | self::assertSame( '', Updatronix_Security::sanitize_context( array() ) );<br>self::assertSame( '', Updatronix_Security::sanitize_context( array( 'unknown' => 'value' ) ) ); |
| `test_allowlist_filter_is_additive` | test allowlist filter is additive | self::assertSame( 'kept', $context['custom_fact'] );<br>self::assertArrayNotHasKey( 'other_fact', $context ); |
| `test_redact_text_covers_urls_and_package_paths` | test redact text covers urls and package paths | self::assertSame( '[redacted-url]', Updatronix_Security::redact_text( 'https://downloads.example.org/plugin/x.zip' ) );<br>self::assertSame( '[redacted-path]', Updatronix_Security::redact_text( '/var/cache/packages/x.zip' ) );<br>self::assertSame( 'Plain text stays.', Updatronix_Security::redact_text( 'Plain text stays.' ) ); |
| `test_invalid_json_decodes_to_empty` | test invalid json decodes to empty | self::assertSame( array(), Updatronix_Security::decode_context( 'not json' ) );<br>self::assertSame( array(), Updatronix_Security::decode_context( '' ) );<br>self::assertSame( array( 'a' => 'b' ), Updatronix_Security::decode_context( '{"a":"b"}' ) ); |

#### `tests/Unit/LogEventFactoryTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_named_events_are_complete` | test named events are complete | self::assertArrayHasKey( $key, $event );<br>self::assertNotSame( '', $event[ $key ], $key ); |
| `test_legacy_row_has_no_decision_fields` | test legacy row has no decision fields | self::assertArrayNotHasKey( 'reason_code', $legacy );<br>self::assertArrayNotHasKey( 'decision_state', $legacy );<br>self::assertSame( 'success', $legacy['status'] ); |
| `test_delay_row_matches_the_v1_shape` | test delay row matches the v1 shape | self::assertSame( 'delayed', $row['status'] );<br>self::assertStringStartsWith( 'udly\|', (string) $row['event_key'] );<br>self::assertArrayNotHasKey( 'next_eligible_at', $row );<br>self::assertStringContainsString( 'eligible no earlier than', (string) $row['message'] ); |
| `test_export_rows_are_generated_locally` | test export rows are generated locally | self::assertCount( 100, $rows );<br>self::assertSame( 'fixture/export-1.php', $rows[0]['item_slug'] );<br>self::assertSame( 'fixture/export-100.php', $rows[99]['item_slug'] ); |
| `test_pass_rows_hold_one_summary` | test pass rows hold one summary | self::assertCount( 51, $rows );<br>self::assertSame( 'system', $rows[50]['log_type'] );<br>self::assertSame( 'pass', $rows[50]['action_type'] ); |

#### `tests/Unit/LogEventKeyTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_same_event_produces_the_same_key` | test same event produces the same key | self::assertSame( $key, Updatronix_Log_Event::key( $event ) );<br>self::assertSame( $key, Updatronix_Log_Event::key( array_reverse( $event, true ) ) );<br>self::assertMatchesRegularExpression( '/^v2\\|[a-f0-9]{64}$/', $key );<br>self::assertLessThanOrEqual( 191, strlen( $key ) ); |
| `test_changed_dimensions_produce_a_new_key` | test changed dimensions produce a new key | self::assertNotSame( $base, Updatronix_Log_Event::key( $this->event( $override ) ), $label ); |
| `test_request_id_replaces_the_pass_for_manual_events` | test request id replaces the pass for manual events | self::assertNotSame( $first, $second );<br>self::assertSame( $first, Updatronix_Log_Event::key( $this->event( array( 'pass_id' => '', 'request_id' => 'req-a', 'origin' => 'manual', ) ) ) ); |
| `test_transition_falls_back_to_state_then_status` | test transition falls back to state then status | self::assertSame( 'blocked', Updatronix_Log_Event::transition( $from_state ) );<br>self::assertSame( 'warning', Updatronix_Log_Event::transition( $from_status ) );<br>self::assertSame( 'updated', Updatronix_Log_Event::transition( $explicit ) ); |
| `test_previous_state_comparator_ignores_the_pass` | test previous state comparator ignores the pass | self::assertTrue( Updatronix_Log_Event::is_same_state( $previous, $waiting ) );<br>self::assertTrue( Updatronix_Log_Event::is_same_state( $previous, array_merge( $waiting, array( 'pass_id' => '0a1b2c3d4e5f60718293a4b5c6d7e8f9' ) ) ), 'A later pass with the same state must compare equal.' );<br>self::assertFalse( Updatronix_Log_Event::is_same_state( $previous, array_merge( $waiting, array( 'reason_code' => 'queue_waiting_turn' ) ) ) );<br>self::assertFalse( Updatronix_Log_Event::is_same_state( $previous, array_merge( $waiting, array( 'decision_state' => 'done' ) ) ) ); |
| `test_missing_or_legacy_previous_row_is_not_the_same_state` | test missing or legacy previous row is not the same state | self::assertFalse( Updatronix_Log_Event::is_same_state( null, $event ) );<br>self::assertFalse( Updatronix_Log_Event::is_same_state( (object) array( 'decision_state' => '', 'reason_code' => '', ), $event ) ); |
| `test_identity_normalization` | test identity normalization | self::assertSame( '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e', Updatronix_Log_Event::normalize_pass_id( '8F3C0D9A-4B6E-4C2A-9F1D-3E5B7A9C0D1E' ) );<br>self::assertSame( str_repeat( 'a', 32 ), Updatronix_Log_Event::normalize_pass_id( str_repeat( 'A', 40 ) ) );<br>self::assertSame( '', Updatronix_Log_Event::normalize_pass_id( 'not-hex' ) );<br>self::assertSame( 'req-2026_10', Updatronix_Log_Event::normalize_request_id( 'REQ-2026_10!!' ) ); |
| `test_timestamp_and_datetime_share_a_key` | test timestamp and datetime share a key | self::assertSame( $from_string, $from_stamp ); |

#### `tests/Unit/LogTaxonomyTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_reason_table_matches_the_shared_snapshot` | test reason table matches the shared snapshot | self::assertSame( array_keys( $this->fixture['reasons'] ), array_keys( $reasons ) );<br>self::assertSame( $expected['state'], $reasons[ $code ]['state'], $code . ' state' );<br>self::assertSame( $expected['action'], $reasons[ $code ]['action'], $code . ' action' );<br>self::assertSame( $expected['status'], $reasons[ $code ]['status'], $code . ' status' ); |
| `test_labels_match_the_shared_snapshot` | test labels match the shared snapshot | self::assertSame( $this->fixture['state_order'], Updatronix_Log_Taxonomy::state_order() );<br>self::assertSame( $this->fixture['legacy_label'], Updatronix_Log_Taxonomy::legacy_label() );<br>self::assertSame( $expected['label'], Updatronix_Log_Taxonomy::state_label( $state ), $state . ' label' );<br>self::assertSame( $expected['intent'], Updatronix_Log_Taxonomy::state_intent( $state ), $state . ' intent' ); |
| `test_every_reason_uses_allowed_vocabulary` | test every reason uses allowed vocabulary | self::assertContains( $reason['state'], Updatronix_Security::ALLOWED_DECISION_STATES, $code );<br>self::assertContains( $reason['status'], Updatronix_Security::ALLOWED_STATUSES, $code );<br>self::assertContains( $reason['action'], Updatronix_Security::get_allowed_action_types(), $code );<br>self::assertContains( $origin, Updatronix_Security::ALLOWED_ORIGINS ); |
| `test_alias_map_resolves_to_canonical_reasons` | test alias map resolves to canonical reasons | self::assertSame( $this->fixture['aliases'], Updatronix_Log_Taxonomy::aliases() );<br>self::assertArrayHasKey( $canonical, $reasons, $alias );<br>self::assertSame( $canonical, Updatronix_Log_Taxonomy::canonical_reason( $alias ), $alias );<br>self::assertSame( 'schedule_hold', Updatronix_Log_Taxonomy::canonical_reason( 'delay_active' ) ); |
| `test_primary_reason_precedence` | test primary reason precedence | self::assertSame( 'version_locked_native', Updatronix_Log_Taxonomy::primary_reason( array( 'queue_waiting_turn', 'schedule_hold', 'shield_incompatible_php', 'version_locked_native' ) ) );<br>self::assertSame( 'shield_incompatible_wp', Updatronix_Log_Taxonomy::primary_reason( array( 'flow_waiting_predecessor', 'schedule_hold', 'wp_incompatible' ) ) );<br>self::assertSame( 'schedule_hold', Updatronix_Log_Taxonomy::primary_reason( array( 'queue_retry_backoff', 'delay_active' ) ) );<br>self::assertSame( 'flow_predecessor_failed', Updatronix_Log_Taxonomy::primary_reason( array( 'flow_predecessor_failed', 'flow_waiting_predecessor' ) ) ); |
| `test_unknown_values_fall_back_safely` | test unknown values fall back safely | self::assertSame( 'unknown', Updatronix_Security::sanitize_reason_code( 'made_up_code' ) );<br>self::assertSame( 'unknown', Updatronix_Security::sanitize_reason_code( 'Made Up Code' ) );<br>self::assertSame( '', Updatronix_Security::sanitize_reason_code( '' ) );<br>self::assertSame( 'schedule_hold', Updatronix_Security::sanitize_reason_code( 'delay_active' ) ); |
| `test_allowlist_filters_are_additive` | test allowlist filters are additive | self::assertContains( 'custom_reason', Updatronix_Security::get_allowed_reason_codes() );<br>self::assertSame( 'custom_reason', Updatronix_Security::sanitize_reason_code( 'custom_reason' ) );<br>self::assertSame( 'quarantined', Updatronix_Security::sanitize_decision_state( 'quarantined' ) );<br>self::assertSame( 'cron_runner', Updatronix_Security::sanitize_origin( 'cron_runner' ) ); |

#### `tests/Unit/LoggerActionTypesTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_unknown_action_type_falls_back_to_update` | test unknown action type falls back to update | self::assertSame( 'update', Updatronix_Security::sanitize_action_type( 'auto_update_reenabled' ) );<br>self::assertSame( 'prevented', Updatronix_Security::sanitize_action_type( 'prevented' ) ); |
| `test_filter_appends_an_action_type` | test filter appends an action type | self::assertContains( 'auto_update_reenabled', $allowed );<br>self::assertContains( 'prevented', $allowed );<br>self::assertSame( 'auto_update_reenabled', Updatronix_Security::sanitize_action_type( 'auto_update_reenabled' ) ); |
| `test_filter_output_is_normalized` | test filter output is normalized | self::assertContains( 'autoupdatereenabled', $allowed );<br>self::assertSame( 1, count( array_keys( $allowed, 'autoupdatereenabled', true ) ) ); |

#### `tests/Unit/ScheduleSanitizersTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_sanitize_wall_time_valid` | test sanitize wall time valid (data: `provide_valid_wall_times`) | $this->assertSame( $expected, updatronix_sanitize_schedule_wall_time( $input ) ); |
| `test_sanitize_wall_time_invalid_falls_back` | test sanitize wall time invalid falls back (data: `provide_invalid_wall_times`) | $this->assertSame( '03:00', updatronix_sanitize_schedule_wall_time( $input ) ); |
| `test_sanitize_schedule_array_empty_input` | test sanitize schedule array empty input | $this->assertSame( '', $result['update_check']['recurrence'] );<br>$this->assertSame( '', $result['update_check']['time'] );<br>$this->assertFalse( $result['delay_updates']['enabled'] );<br>$this->assertSame( 0, $result['delay_updates']['delay_value'] ); |
| `test_sanitize_schedule_array_missing_update_check` | test sanitize schedule array missing update check | $this->assertSame( '', $result['update_check']['recurrence'] );<br>$this->assertSame( '', $result['update_check']['time'] );<br>$this->assertTrue( $result['delay_updates']['enabled'] );<br>$this->assertSame( 3, $result['delay_updates']['delay_value'] ); |
| `test_sanitize_schedule_array_missing_delay_updates` | test sanitize schedule array missing delay updates | $this->assertSame( 'daily', $result['update_check']['recurrence'] );<br>$this->assertSame( '04:15', $result['update_check']['time'] );<br>$this->assertFalse( $result['delay_updates']['enabled'] );<br>$this->assertSame( 0, $result['delay_updates']['delay_value'] ); |
| `test_sanitize_schedule_array_invalid_recurrence` | test sanitize schedule array invalid recurrence | $this->assertSame( '', $result['update_check']['recurrence'] ); |
| `test_sanitize_schedule_array_delay_enabled_no_value` | test sanitize schedule array delay enabled no value | $this->assertFalse( $result['delay_updates']['enabled'] );<br>$this->assertSame( 0, $result['delay_updates']['delay_value'], 'An enabled hold with no days is off.' ); |
| `test_sanitize_schedule_array_hold_bounds` | test sanitize schedule array hold bounds (data: `provide_hold_bounds`) | $this->assertSame( $want_on, $result['delay_updates']['enabled'] );<br>$this->assertSame( $want_days, $result['delay_updates']['delay_value'] ); |
| `test_sanitize_schedule_array_delay_oversize` | test sanitize schedule array delay oversize | $this->assertSame( 365, $result['delay_updates']['delay_value'] ); |
| `test_sanitize_schedule_array_delay_disabled_clears_value` | test sanitize schedule array delay disabled clears value | $this->assertFalse( $result['delay_updates']['enabled'] );<br>$this->assertSame( 0, $result['delay_updates']['delay_value'] ); |
| `test_sanitize_schedule_array_preserves_time_for_every_recurrence` | test sanitize schedule array preserves time for every recurrence (data: `provide_recurrences`) | $this->assertSame( $recurrence, $result['update_check']['recurrence'] );<br>$this->assertSame( '02:17', $result['update_check']['time'] ); |
| `test_sanitize_schedule_array_fresh_install_keeps_empty_time` | test sanitize schedule array fresh install keeps empty time | $this->assertSame( '', $result['update_check']['time'] ); |
| `test_next_check_twicedaily_is_always_in_the_future` | test next check twicedaily is always in the future | $this->assertGreaterThan( time(), $next ); |
| `test_next_check_daily_is_in_the_future` | test next check daily is in the future | $this->assertGreaterThan( time(), $next );<br>$this->assertLessThanOrEqual( time() + DAY_IN_SECONDS, $next ); |
| `test_next_check_weekly_is_in_the_future` | test next check weekly is in the future | $this->assertGreaterThan( time() + 6 * DAY_IN_SECONDS, $next ); |
| `test_next_check_hourly_returns_now` | test next check hourly returns now | $this->assertLessThanOrEqual( 2, abs( updatronix_next_update_check_timestamp( 'hourly', '02:17' ) - time() ) ); |
| `test_next_check_daily_keeps_a_future_time_today` | test next check daily keeps a future time today | $this->assertLessThan( DAY_IN_SECONDS, $next - time() ); |
| `test_merge_partial_empty_returns_baseline` | test merge partial empty returns baseline | $this->assertSame( $baseline, $result ); |
| `test_merge_partial_partial_override` | test merge partial partial override | $this->assertSame( 'weekly', $result['update_check']['recurrence'] );<br>$this->assertSame( '03:00', $result['update_check']['time'], 'Time should remain from baseline.' );<br>$this->assertSame( true, $result['delay_updates']['enabled'] );<br>$this->assertSame( 7, $result['delay_updates']['delay_value'] ); |
| `test_merge_partial_explicit_false_overrides` | test merge partial explicit false overrides | $this->assertFalse( $result['delay_updates']['enabled'] );<br>$this->assertSame( 7, $result['delay_updates']['delay_value'], 'delay_value should stay from baseline.' ); |
| `test_merge_partial_non_array_update_check_ignored` | test merge partial non array update check ignored | $this->assertSame( $baseline, $result ); |
| `test_merge_partial_non_array_delay_updates_ignored` | test merge partial non array delay updates ignored | $this->assertSame( $baseline, $result ); |

#### `tests/Unit/ScheduleStateTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_held_state_and_earliest_install` | test held state and earliest install | self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] );<br>self::assertSame( 'earliest', $out['display_kind'] );<br>self::assertSame( 'schedule_hold', $out['reason_code'] );<br>self::assertSame( ScheduleInput::NOW - 26 * HOUR_IN_SECONDS + 2 * DAY_IN_SECONDS, $out['hold_until'] ); |
| `test_eligible_state_when_hold_expired` | test eligible state when hold expired | self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );<br>self::assertSame( 'exact', $out['display_kind'] );<br>self::assertSame( ScheduleInput::NOW + 6 * HOUR_IN_SECONDS, $out['execution_at'] );<br>self::assertStringContainsString( 'Next automatic install: 2026-10-29 19:00 (Europe/Paris).', $out['display'] ); |
| `test_hold_off_ignores_first_seen` | test hold off ignores first seen | self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );<br>self::assertNull( $out['hold_until'] );<br>self::assertSame( ScheduleInput::NOW + 6 * HOUR_IN_SECONDS, $out['eligible_at'] ); |
| `test_hold_zero_is_off` | test hold zero is off | self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );<br>self::assertNull( $out['hold_until'] ); |
| `test_hold_one_day` | test hold one day | self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );<br>self::assertSame( ScheduleInput::NOW - 26 * HOUR_IN_SECONDS + DAY_IN_SECONDS, $out['hold_until'] );<br>self::assertSame( ScheduleInput::NOW + 6 * HOUR_IN_SECONDS, $out['execution_at'] ); |
| `test_hold_365_days` | test hold 365 days | self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] );<br>self::assertSame( ScheduleInput::NOW - 26 * HOUR_IN_SECONDS + 365 * DAY_IN_SECONDS, $out['hold_until'] );<br>self::assertGreaterThan( $out['hold_until'], $out['eligible_at'] ); |
| `test_not_observed_when_first_seen_missing` | test not observed when first seen missing | self::assertSame( Updatronix_Schedule_State::STATE_NOT_OBSERVED, $out['state'] );<br>self::assertSame( 'not_observed', $out['display_kind'] );<br>self::assertNull( $out['hold_until'] );<br>self::assertNull( $out['eligible_at'] ); |
| `test_wp_cron_disabled_wins` | test wp cron disabled wins | self::assertSame( Updatronix_Schedule_State::STATE_WP_CRON_DISABLED, $out['state'] );<br>self::assertSame( 'cron', $out['display_kind'] );<br>self::assertNull( $out['execution_at'] );<br>self::assertStringContainsString( 'WP-Cron spawning is disabled', $out['display'] ); |
| `test_not_scheduled` | test not scheduled | self::assertSame( Updatronix_Schedule_State::STATE_NOT_SCHEDULED, $out['state'] );<br>self::assertNull( $out['eligible_at'] );<br>self::assertStringContainsString( 'not scheduled', $out['display'] ); |
| `test_overdue` | test overdue | self::assertSame( Updatronix_Schedule_State::STATE_OVERDUE, $out['state'] );<br>self::assertSame( '2 hours', $out['overdue_by'] );<br>self::assertNull( $out['eligible_at'] );<br>self::assertStringContainsString( 'overdue by 2 hours', $out['display'] ); |
| `test_network_state_wins_over_not_scheduled` | test network state wins over not scheduled | self::assertSame( Updatronix_Schedule_State::STATE_NETWORK, $out['state'] );<br>self::assertStringContainsString( 'network update schedule', $out['display'] ); |
| `test_locked_wins_over_hold_and_queue` | test locked wins over hold and queue | self::assertSame( Updatronix_Schedule_State::STATE_LOCKED, $out['state'] );<br>self::assertSame( 'managed', $out['display_kind'] );<br>self::assertSame( 'Git', $out['manager'] );<br>self::assertSame( 'Managed via Git. Update through your manager.', $out['display'] ); |
| `test_blocked_policy` | test blocked policy | self::assertSame( Updatronix_Schedule_State::STATE_BLOCKED, $out['state'] );<br>self::assertSame( 'Requires PHP 8.3', $out['policy_label'] );<br>self::assertSame( 'Blocked by Requires PHP 8.3. No automatic install time.', $out['display'] ); |
| `test_missing_lock_facts_are_unknown` | test missing lock facts are unknown | self::assertSame( Updatronix_Schedule_State::STATE_UNKNOWN, $out['state'] );<br>self::assertNull( $out['execution_at'] ); |
| `test_not_managed` | test not managed | self::assertSame( Updatronix_Schedule_State::STATE_NOT_MANAGED, $out['state'] );<br>self::assertSame( 'Automatic updates are off for this item.', $out['display'] );<br>self::assertNull( $out['eligible_at'] ); |
| `test_forced_off_is_named` | test forced off is named | self::assertSame( Updatronix_Schedule_State::STATE_NOT_MANAGED, $out['state'] );<br>self::assertSame( 'environment_disabled', $out['reason_code'] );<br>self::assertStringContainsString( 'site configuration', $out['display'] ); |
| `test_forced_on_is_managed` | test forced on is managed | self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] ); |
| `test_no_offer` | test no offer | self::assertSame( Updatronix_Schedule_State::STATE_NO_OFFER, $out['state'] );<br>self::assertSame( 'No automatic update pending.', $out['display'] ); |
| `test_no_core_offer_wording` | test no core offer wording | self::assertSame( 'No automatic core update pending.', $out['display'] ); |
| `test_unknown_offer` | test unknown offer | self::assertSame( Updatronix_Schedule_State::STATE_UNKNOWN_OFFER, $out['state'] );<br>self::assertNull( $out['eligible_at'] );<br>self::assertStringContainsString( 'stale or unknown', $out['display'] ); |
| `test_waiting_for_predecessor` | test waiting for predecessor | self::assertSame( Updatronix_Schedule_State::STATE_WAITING, $out['state'] );<br>self::assertSame( 'waiting', $out['display_kind'] );<br>self::assertSame( 'Waiting for Payment Gateway. No automatic install time yet.', $out['display'] );<br>self::assertNull( $out['execution_at'] ); |
| `test_waiting_for_locked_predecessor` | test waiting for locked predecessor | self::assertSame( 'Waiting for Payment Gateway to be updated by Composer. No automatic install time yet.', $out['display'] ); |
| `test_waiting_for_failed_predecessor` | test waiting for failed predecessor | self::assertSame( 'Waiting for Payment Gateway to recover. No automatic install time yet.', $out['display'] ); |
| `test_flow_done_does_not_wait` | test flow done does not wait | self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] ); |
| `test_queued_position_is_a_count` | test queued position is a count | self::assertSame( Updatronix_Schedule_State::STATE_QUEUED, $out['state'] );<br>self::assertSame( 3, $out['queue_position'] );<br>self::assertSame( ScheduleInput::NOW + 6 * HOUR_IN_SECONDS, $out['eligible_at'] );<br>self::assertStringContainsString( 'queued after 3 items', $out['display'] ); |
| `test_queued_next_in_pass` | test queued next in pass | self::assertSame( Updatronix_Schedule_State::STATE_QUEUED, $out['state'] );<br>self::assertStringContainsString( 'Next in the current pass', $out['display'] ); |
| `test_running` | test running | self::assertSame( Updatronix_Schedule_State::STATE_RUNNING, $out['state'] );<br>self::assertSame( 'running', $out['display_kind'] );<br>self::assertSame( 'Updating now.', $out['display'] ); |
| `test_failed_node` | test failed node | self::assertSame( Updatronix_Schedule_State::STATE_FAILED, $out['state'] );<br>self::assertSame( 'update_failed', $out['reason_code'] ); |
| `test_uncertain_node` | test uncertain node | self::assertSame( Updatronix_Schedule_State::STATE_UNCERTAIN, $out['state'] );<br>self::assertSame( 'partial_state', $out['reason_code'] ); |
| `test_unknown_when_eligible_cannot_be_computed` | test unknown when eligible cannot be computed | self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );<br>self::assertSame( ScheduleInput::NOW, $out['execution_at'] ); |
| `test_core_minor_label` | test core minor label | self::assertSame( 'minor', $out['core_kind'] );<br>self::assertStringContainsString( 'Core minor release.', $out['display'] );<br>self::assertStringNotContainsStringIgnoringCase( 'security', $out['display'] ); |
| `test_core_major_label` | test core major label | self::assertSame( 'major', $out['core_kind'] );<br>self::assertStringContainsString( 'Core major release.', $out['display'] ); |
| `test_core_major_off_is_not_managed` | test core major off is not managed | self::assertSame( Updatronix_Schedule_State::STATE_NOT_MANAGED, $out['state'] );<br>self::assertSame( 'major', $out['core_kind'] );<br>self::assertStringNotContainsString( 'Core major release.', $out['display'] ); |
| `test_core_constant_false_is_not_managed` | test core constant false is not managed | self::assertSame( Updatronix_Schedule_State::STATE_NOT_MANAGED, $out['state'] ); |
| `test_translation_is_details_only` | test translation is details only | self::assertSame( Updatronix_Schedule_State::STATE_NO_OFFER, $out['state'] );<br>self::assertSame( '', $out['core_kind'] ); |
| `test_interval_for_every_recurrence` | test interval for every recurrence | self::assertSame( 3600, Updatronix_Schedule_State::interval_for( 'hourly' ) );<br>self::assertSame( 43200, Updatronix_Schedule_State::interval_for( 'twicedaily' ) );<br>self::assertSame( 86400, Updatronix_Schedule_State::interval_for( 'daily' ) );<br>self::assertSame( 604800, Updatronix_Schedule_State::interval_for( 'weekly' ) ); |
| `test_first_check_at_or_after` | test first check at or after | self::assertSame( $now + 100, Updatronix_Schedule_State::first_check_at_or_after( $now, $now + 100, 3600, $now ) );<br>self::assertSame( $now + 3700, Updatronix_Schedule_State::first_check_at_or_after( $now + 2000, $now + 100, 3600, $now ) );<br>self::assertNull( Updatronix_Schedule_State::first_check_at_or_after( $now, $now - 1, 3600, $now ) );<br>self::assertNull( Updatronix_Schedule_State::first_check_at_or_after( $now, false, 3600, $now ) ); |
| `test_hourly_recurrence` | test hourly recurrence | self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] );<br>self::assertFalse( $out['dst_shift'] ); |
| `test_weekly_recurrence` | test weekly recurrence | self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] );<br>self::assertSame( ScheduleInput::NOW + 8 * HOUR_IN_SECONDS + 2 * WEEK_IN_SECONDS, $out['eligible_at'] ); |
| `test_preferred_time_is_preserved_in_output` | test preferred time is preserved in output | self::assertSame( Updatronix_Schedule_State::STATE_HELD, $hourly['state'] );<br>self::assertSame( Updatronix_Schedule_State::STATE_HELD, $daily['state'] ); |
| `test_dst_shift_is_flagged` | test dst shift is flagged | self::assertTrue( $out['dst_shift'] );<br>self::assertStringContainsString( 'daylight-saving shift', $out['display'] );<br>self::assertStringContainsString( '03:00', $out['display'] ); |
| `test_saved_time_mismatch_that_is_not_dst_is_silent` | test saved time mismatch that is not dst is silent | self::assertFalse( $out['dst_shift'] ); |
| `test_empty_formats_fall_back` | test empty formats fall back | self::assertStringContainsString( '2026-10-29 18:00', $out['display'] );<br>self::assertStringContainsString( '(UTC)', $out['display'] ); |
| `test_utc_timezone_label` | test utc timezone label | self::assertStringContainsString( '(UTC)', $out['display'] ); |
| `test_deterministic` | test deterministic | self::assertSame( $this->calc(), $this->calc() ); |
| `test_output_shape` | test output shape | self::assertArrayHasKey( $key, $out ); |
| `test_core_key_is_canonical` | test core key is canonical | self::assertSame( 'core:' . Updatronix_Item_State::CORE_ID, Updatronix_Item_State::normalize_key( 'core', 'core' ) );<br>self::assertSame( 'plugin:a/a.php', Updatronix_Item_State::normalize_key( 'plugin', 'a/a.php' ) ); |

#### `tests/Unit/SecurityTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_redact_sensitive_text_redacts_tokens` | test redact sensitive text redacts tokens (data: `provide_token_redaction`) | $this->assertStringContainsString( $expected, Updatronix_Security::sanitize_trace( $input ) ); |
| `test_redact_sensitive_text_does_not_redact_auth_substrings` | test redact sensitive text does not redact auth substrings (data: `provide_auth_false_positive`) | $this->assertStringNotContainsString( '[redacted]', $result ); |
| `test_redact_sensitive_text_redacts_email` | test redact sensitive text redacts email | $this->assertStringContainsString( '[redacted-email]', $result ); |
| `test_redact_sensitive_text_fails_closed_when_preg_replace_returns_null` | test redact sensitive text fails closed when preg replace returns null | self::assertStringNotContainsString( 'super-secret', $result );<br>self::assertStringContainsString( '[redacted: log text could not be safely redacted]', $result ); |
| `test_redact_sensitive_text_redacts_prefixed_secret_names` | test redact sensitive text redacts prefixed secret names (data: `provide_prefixed_secret_redaction`) | $this->assertStringContainsString( '[redacted]', $result );<br>$this->assertStringNotContainsString( 'abc123', $result ); |
| `test_redact_sensitive_text_redacts_bearer_tokens` | test redact sensitive text redacts bearer tokens | $this->assertStringContainsString( 'Bearer [redacted]', $result );<br>$this->assertStringNotContainsString( 'abc.def.ghi', $result ); |
| `test_redact_sensitive_text_redacts_paths` | test redact sensitive text redacts paths (data: `provide_path_redaction`) | $this->assertStringContainsString( $expected, $result ); |
| `test_redact_sensitive_text_does_not_expose_abspath` | test redact sensitive text does not expose abspath | $this->assertStringNotContainsString( untrailingslashit( ABSPATH ), $result ); |
| `test_sanitize_message_redacts_html_entity_encoded_token` | test sanitize message redacts html entity encoded token | $this->assertStringContainsString( '[redacted]', $result );<br>$this->assertStringNotContainsString( 'secret', $result ); |
| `test_sanitize_message_redacts_html_entity_hex_encoded_token` | test sanitize message redacts html entity hex encoded token | $this->assertStringContainsString( '[redacted]', $result );<br>$this->assertStringNotContainsString( 'secret', $result ); |
| `test_sanitize_message_preserves_innocuous_text` | test sanitize message preserves innocuous text | $this->assertSame( $input, $result ); |
| `test_sanitize_message_decodes_html_entities` | test sanitize message decodes html entities | $this->assertStringContainsString( "\u{2026}", $result ); |
| `test_sanitize_message_decodes_quote_entities` | test sanitize message decodes quote entities | $this->assertSame( 'He said "stop"', $result ); |
| `test_sanitize_message_strips_encoded_markup` | test sanitize message strips encoded markup | $this->assertStringNotContainsString( '<', $result );<br>$this->assertStringNotContainsString( '>', $result ); |
| `test_sanitize_trace_truncates_to_max_length` | test sanitize trace truncates to max length | $this->assertSame( 100, mb_strlen( $result ) ); |
| `test_sanitize_trace_default_limit_is_65535_characters` | test sanitize trace default limit is 65535 characters | $this->assertSame( 65535, mb_strlen( $result ) );<br>$this->assertSame( 65535 * 2, strlen( $result ) ); |
| `test_sanitize_trace_empty_input` | test sanitize trace empty input | $this->assertSame( '', Updatronix_Security::sanitize_trace( '' ) ); |
| `test_sanitize_message_empty_input` | test sanitize message empty input | $this->assertSame( '', Updatronix_Security::sanitize_message( '' ) ); |
| `test_sanitize_message_truncates_oversize` | test sanitize message truncates oversize | $this->assertSame( 65535, mb_strlen( $result ) ); |
| `test_sanitize_message_truncates_multibyte_at_the_default_limit` | test sanitize message truncates multibyte at the default limit | $this->assertSame( 65535, mb_strlen( $result ) );<br>$this->assertSame( 65535 * 2, strlen( $result ) ); |
| `test_sanitize_log_type_accepts_allowed` | test sanitize log type accepts allowed (data: `provide_log_types`) | $this->assertSame( $value, Updatronix_Security::sanitize_log_type( $value ) ); |
| `test_sanitize_log_type_normalises_case` | test sanitize log type normalises case | $this->assertSame( 'plugin', Updatronix_Security::sanitize_log_type( 'PLUGIN' ) ); |
| `test_sanitize_log_type_falls_back_for_unknown` | test sanitize log type falls back for unknown | $this->assertSame( 'plugin', Updatronix_Security::sanitize_log_type( 'evil' ) );<br>$this->assertSame( 'plugin', Updatronix_Security::sanitize_log_type( 'plugin()' ) ); |
| `test_sanitize_action_type_accepts_allowed` | test sanitize action type accepts allowed (data: `provide_action_types`) | $this->assertSame( $value, Updatronix_Security::sanitize_action_type( $value ) ); |
| `test_sanitize_action_type_falls_back_for_unknown` | test sanitize action type falls back for unknown | $this->assertSame( 'update', Updatronix_Security::sanitize_action_type( 'delete *' ) );<br>$this->assertSame( 'update', Updatronix_Security::sanitize_action_type( '' ) ); |
| `test_sanitize_status_accepts_allowed` | test sanitize status accepts allowed (data: `provide_statuses`) | $this->assertSame( $value, Updatronix_Security::sanitize_status( $value ) ); |
| `test_sanitize_status_falls_back_for_unknown` | test sanitize status falls back for unknown | $this->assertSame( 'success', Updatronix_Security::sanitize_status( 'hacked' ) ); |
| `test_sanitize_performed_as_accepts_allowed` | test sanitize performed as accepts allowed (data: `provide_performed_as`) | $this->assertSame( $value, Updatronix_Security::sanitize_performed_as( $value ) ); |
| `test_sanitize_performed_as_falls_back_for_unknown` | test sanitize performed as falls back for unknown | $this->assertSame( 'manual', Updatronix_Security::sanitize_performed_as( 'cron' ) ); |
| `test_sanitize_update_context_accepts_allowed` | test sanitize update context accepts allowed (data: `provide_update_contexts`) | $this->assertSame( $value, Updatronix_Security::sanitize_update_context( $value ) ); |
| `test_sanitize_update_context_falls_back_for_unknown` | test sanitize update context falls back for unknown | $this->assertSame( '', Updatronix_Security::sanitize_update_context( 'both' ) ); |
| `test_sanitize_string_strips_control_characters` | test sanitize string strips control characters | $this->assertSame( 'hi there', Updatronix_Security::sanitize_string( "\x00hi\x1f there" ) ); |
| `test_sanitize_string_truncates_to_default_max` | test sanitize string truncates to default max | $this->assertSame( 255, mb_strlen( $result ) ); |
| `test_sanitize_string_truncates_to_custom_max` | test sanitize string truncates to custom max | $this->assertSame( 10, mb_strlen( $result ) ); |
| `test_sanitize_string_redacts_secret_parameters` | test sanitize string redacts secret parameters | $this->assertStringContainsString( '[redacted]', $result );<br>$this->assertStringNotContainsString( 'secret', $result ); |
| `test_sanitize_string_truncates_on_character_boundaries` | test sanitize string truncates on character boundaries | $this->assertSame( str_repeat( 'é', 100 ), $result );<br>$this->assertSame( 100, mb_strlen( $result ) );<br>$this->assertSame( 200, strlen( $result ) ); |
| `test_sanitize_version_preserves_valid` | test sanitize version preserves valid | $this->assertSame( '1.2.3-beta.4', Updatronix_Security::sanitize_version( '1.2.3-beta.4' ) ); |
| `test_sanitize_version_keeps_a_version_that_starts_with_a_letter` | test sanitize version keeps a version that starts with a letter | $this->assertSame( 'v1.2.3', Updatronix_Security::sanitize_version( 'v1.2.3' ) ); |
| `test_sanitize_version_strips_illegal_characters` | test sanitize version strips illegal characters | **NONE** |
| `test_sanitize_version_blocks_path_traversal` | test sanitize version blocks path traversal | $this->assertStringNotContainsString( '/', $result );<br>$this->assertMatchesRegularExpression( '/^[a-zA-Z0-9._-]*$/', $result ); |
| `test_sanitize_version_truncates_to_64` | test sanitize version truncates to 64 | $this->assertSame( 64, mb_strlen( Updatronix_Security::sanitize_version( str_repeat( '1', 100 ) ) ) ); |
| `test_user_can_manage_logs_single_site_with_cap` | test user can manage logs single site with cap | $this->assertTrue( Updatronix_Security::user_can_manage_logs() ); |
| `test_user_can_manage_logs_single_site_without_cap` | test user can manage logs single site without cap | $this->assertFalse( Updatronix_Security::user_can_manage_logs() ); |
| `test_user_can_manage_logs_multisite_non_super_admin_denied` | test user can manage logs multisite non super admin denied | $this->assertFalse( Updatronix_Security::user_can_manage_logs() ); |
| `test_user_can_manage_logs_multisite_super_admin_without_cap_denied` | test user can manage logs multisite super admin without cap denied | $this->assertFalse( Updatronix_Security::user_can_manage_logs() ); |
| `test_user_can_manage_logs_multisite_super_admin_with_cap` | test user can manage logs multisite super admin with cap | $this->assertTrue( Updatronix_Security::user_can_manage_logs() ); |
| `test_redact_sensitive_text_redacts_a_site_path` | test redact sensitive text redacts a site path | $this->assertStringNotContainsString( '/tmp/some-dir/file.php', $traced ); |

#### `tests/Unit/SettingsResolveSiteIdTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_single_site_resolves_to_current_blog` | test single site resolves to current blog | $this->assertSame( 1, $this->resolve( $request ) ); |
| `test_multisite_non_super_admin_scopes_to_current_blog` | test multisite non super admin scopes to current blog | $this->assertSame( 3, $this->resolve( $request ), 'Non-super-admin must not widen scope via site_id.' ); |
| `test_multisite_super_admin_defaults_to_network_global` | test multisite super admin defaults to network global | $this->assertSame( 0, $this->resolve( $request ), 'Super-admin default must be the network-global sentinel.' ); |
| `test_multisite_super_admin_explicit_site_id_narrows_scope` | test multisite super admin explicit site id narrows scope | $this->assertSame( 7, $this->resolve( $request ) ); |
| `test_multisite_super_admin_zero_site_id_stays_network_global` | test multisite super admin zero site id stays network global | $this->assertSame( 0, $this->resolve( $request ) ); |

### Integration (194 cases)

#### `tests/Integration/AutoUpdatesRestApiTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_get_auto_updates_returns_expected_structure` | test get auto updates returns expected structure | self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertArrayHasKey( 'constants', $data );<br>self::assertArrayHasKey( 'core', $data ); |
| `test_get_auto_updates_returns_403_for_user_without_cap` | test get auto updates returns 403 for user without cap | self::assertSame( 403, $response->get_status() ); |
| `test_set_core_mode_with_valid_mode_returns_200` | test set core mode with valid mode returns 200 | self::assertSame( 200, $response->get_status() );<br>self::assertArrayHasKey( 'core', $data );<br>self::assertSame( 'minor', $data['core']['mode'] ); |
| `test_set_core_mode_with_invalid_mode_returns_400` | test set core mode with invalid mode returns 400 | self::assertSame( 400, $response->get_status() ); |
| `test_toggle_plugin_with_valid_plugin_returns_200` | test toggle plugin with valid plugin returns 200 | self::assertSame( 200, $response->get_status() );<br>self::assertArrayHasKey( 'plugins', $data ); |
| `test_toggle_plugin_with_invalid_plugin_returns_404` | test toggle plugin with invalid plugin returns 404 | self::assertSame( 404, $response->get_status() ); |
| `test_toggle_theme_with_invalid_stylesheet_returns_404` | test toggle theme with invalid stylesheet returns 404 | self::assertSame( 404, $response->get_status() ); |
| `test_toggle_translation_returns_200` | test toggle translation returns 200 | self::assertSame( 200, $response->get_status() );<br>self::assertArrayHasKey( 'translations', $data );<br>self::assertFalse( $data['translations']['auto_update'] ); |
| `test_dismiss_constant_with_valid_constant_returns_200` | test dismiss constant with valid constant returns 200 | self::assertSame( 200, $response->get_status() );<br>self::assertArrayHasKey( 'dismissed_constants', $data );<br>self::assertContains( 'WP_AUTO_UPDATE_CORE', $data['dismissed_constants'] ); |
| `test_dismiss_constant_with_invalid_constant_returns_400` | test dismiss constant with invalid constant returns 400 | self::assertSame( 400, $response->get_status() ); |

#### `tests/Integration/CronUnifiedScheduleTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_unified_schedule_keeps_wp_version_check_and_suppresses_plugin_theme_crons` | test unified schedule keeps wp version check and suppresses plugin theme crons | self::assertIsInt( wp_next_scheduled( 'wp_version_check' ), 'Test setup should register Core update crons.' );<br>self::assertIsInt( wp_next_scheduled( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK ) );<br>self::assertFalse( wp_next_scheduled( 'wp_update_plugins' ) );<br>self::assertFalse( wp_next_scheduled( 'wp_update_themes' ) ); |
| `test_schedule_meta_includes_driver_fields` | test schedule meta includes driver fields | self::assertArrayHasKey( 'schedule_driver', $meta );<br>self::assertArrayHasKey( 'unified_schedule_active', $meta );<br>self::assertContains( $meta['schedule_driver'], array( 'WordPress', 'updatronix' ) );<br>self::assertIsBool( $meta['unified_schedule_active'] ); |
| `test_unified_prime_does_not_double_run_version_check` | test unified prime does not double run version check | self::assertTrue( Updatronix_Cron::is_unified_schedule_active() );<br>self::assertSame( 1, $core_check_runs, 'wp_version_check() must run exactly once per cron tick when unified scheduling is active.' ); |
| `test_single_event_contamination_is_blocked_by_filter` | test single event contamination is blocked by filter | self::assertTrue( Updatronix_Cron::is_unified_schedule_active() );<br>self::assertNotFalse( $event_before, 'A recurring event should exist.' );<br>self::assertFalse( $result, 'The single event should be blocked by the filter.' );<br>self::assertNotFalse( $event_after, 'A recurring event should still exist.' ); |

#### `tests/Integration/ExternalLogTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_logs_ftp_update_with_source_and_upload_trigger` | test logs ftp update with source and upload trigger | self::assertIsInt( $log_id );<br>self::assertIsObject( $log );<br>self::assertSame( 'update', $log->action_type );<br>self::assertSame( 'upload', $log->performed_as ); |
| `test_optional_decision_fields_store` | test optional decision fields store | self::assertIsInt( $log_id );<br>self::assertIsObject( $log );<br>self::assertSame( 'external_update', $log->reason_code );<br>self::assertSame( 'done', $log->decision_state ); |
| `test_rejects_unknown_source` | test rejects unknown source | self::assertWPError( $result );<br>self::assertSame( 'updatronix_external_log_source', $result->get_error_code() ); |
| `test_requires_item_name` | test requires item name | self::assertWPError( $result );<br>self::assertSame( 'updatronix_external_log_item', $result->get_error_code() ); |
| `test_event_key_is_idempotent` | test event key is idempotent | self::assertIsInt( $first );<br>self::assertSame( $first, $second ); |
| `test_filter_can_add_a_source` | test filter can add a source | self::assertIsInt( $log_id ); |

#### `tests/Integration/HealthRestApiTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_health_snapshot_returns_expected_structure` | test health snapshot returns expected structure | self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertArrayHasKey( 'summary', $data );<br>self::assertArrayHasKey( 'checks', $data ); |
| `test_health_check_filter_appends_checks` | test health check filter appends checks | self::assertSame( 200, $response->get_status() );<br>self::assertCount( 7, $data['checks'] );<br>self::assertSame( 'test_extra', $data['checks'][6]['id'] );<br>self::assertSame( 'stable', $data['checks'][6]['intent'] ); |
| `test_diagnostics_returns_environment_and_text` | test diagnostics returns environment and text | self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertArrayHasKey( 'environment', $data );<br>self::assertArrayHasKey( 'text', $data ); |
| `test_retry_missing_log_returns_404` | test retry missing log returns 404 | self::assertSame( 404, $response->get_status() ); |
| `test_health_requires_capability` | test health requires capability | self::assertSame( 403, $response->get_status() ); |

#### `tests/Integration/LogEventDedupeTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_same_transition_writes_one_row` | test same transition writes one row | self::assertIsInt( $first );<br>self::assertSame( $first, $second );<br>self::assertSame( 1, $this->count_rows() ); |
| `test_repeated_wait_across_passes_is_one_row` | test repeated wait across passes is one row | self::assertIsInt( $first );<br>self::assertSame( $first, $second );<br>self::assertSame( 1, $this->count_rows() );<br>self::assertSame( LogEventFactory::PASS_ID, (string) $row->pass_id ); |
| `test_state_change_in_a_later_pass_writes_a_new_row` | test state change in a later pass writes a new row | self::assertIsInt( $waiting_id );<br>self::assertIsInt( $done );<br>self::assertNotSame( $waiting_id, $done );<br>self::assertSame( 2, $this->count_rows() ); |
| `test_changed_target_reason_or_attempt_writes_a_new_row` | test changed target reason or attempt writes a new row | self::assertIsInt( $first );<br>self::assertNotSame( $first, $new_target );<br>self::assertNotSame( $first, $new_reason );<br>self::assertNotSame( $first, $new_attempt ); |
| `test_new_fields_are_stored` | test new fields are stored | self::assertIsInt( $id );<br>self::assertIsObject( $row );<br>self::assertSame( 'flow_waiting_predecessor', $row->reason_code );<br>self::assertSame( 'waiting', $row->decision_state ); |
| `test_decision_fields_are_derived_from_the_reason` | test decision fields are derived from the reason | self::assertIsInt( $id );<br>self::assertSame( 'schedule_hold', $row->reason_code );<br>self::assertSame( 'delayed', $row->decision_state );<br>self::assertSame( 'update', $row->action_type ); |
| `test_legacy_log_keeps_empty_key_behavior` | test legacy log keeps empty key behavior | self::assertIsInt( $first );<br>self::assertIsInt( $second );<br>self::assertNotSame( $first, $second );<br>self::assertSame( 2, $this->count_rows() ); |
| `test_legacy_log_dedupes_by_event_key` | test legacy log dedupes by event key | self::assertIsInt( $first );<br>self::assertSame( $first, $second );<br>self::assertSame( 1, $this->count_rows() ); |
| `test_rollback_switch_falls_back_to_the_legacy_shape` | test rollback switch falls back to the legacy shape | self::assertIsInt( $id );<br>self::assertSame( '', $row->reason_code );<br>self::assertSame( '', $row->decision_state );<br>self::assertSame( '', $row->origin ); |
| `test_logging_disabled_writes_nothing` | test logging disabled writes nothing | self::assertFalse( $id );<br>self::assertSame( 0, $this->count_rows() ); |
| `test_get_latest_for_item_is_scoped` | test get latest for item is scoped | self::assertIsInt( $first );<br>self::assertIsObject( $latest );<br>self::assertSame( $first, (int) $latest->id );<br>self::assertNull( Updatronix_Logger::get_latest_for_item( get_current_blog_id(), 'plugin', 'fixture/shield-wp.php', '9.9.9' ) ); |
| `test_attach_context_enriches_without_changing_the_outcome` | test attach context enriches without changing the outcome | self::assertIsInt( $id );<br>self::assertIsObject( $before );<br>self::assertTrue( $updated );<br>self::assertIsObject( $after ); |
| `test_update_summary_updates_only_a_pass_summary` | test update summary updates only a pass summary | self::assertIsInt( $summary_id );<br>self::assertIsInt( $item_id );<br>self::assertTrue( Updatronix_Logger::update_summary( (int) $summary_id, array( 'status' => 'warning', 'decision_state' => 'blocked', 'context' => array( 'blocked' => 9 ), ) ) );<br>self::assertSame( 'warning', $row->status ); |

#### `tests/Integration/LogExportContractTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_allowlists_are_additive` | test allowlists are additive | self::assertContains( $token, Updatronix_Export::FILTER_FIELDS );<br>self::assertContains( $column, Updatronix_Export::COLUMN_KEYS ); |
| `test_decision_filter_matches_the_feed` | test decision filter matches the feed | self::assertStringContainsString( 'Shield WP fixture', $body );<br>self::assertStringNotContainsString( 'Automatic fixture', $body );<br>self::assertStringNotContainsString( 'Waiting fixture', $body );<br>self::assertSame( 1, Updatronix_Logger::get_logs_count( $feed_args ) ); |
| `test_state_origin_and_next_eligible_filters` | test state origin and next eligible filters | self::assertStringContainsString( 'Waiting fixture', $state );<br>self::assertStringNotContainsString( 'Shield WP fixture', $state );<br>self::assertStringContainsString( 'Waiting fixture', $origin );<br>self::assertStringNotContainsString( 'Automatic fixture', $origin ); |
| `test_json_body_is_additive` | test json body is additive | self::assertCount( 1, $rows );<br>self::assertNotEmpty( $lines );<br>self::assertIsArray( $payload );<br>self::assertArrayHasKey( $old, $payload, 'Old field removed: ' . $old ); |
| `test_status_map_is_truthful` | test status map is truthful | self::assertCount( 3, $rows );<br>self::assertSame( 1, substr_count( $body, '"status":"Success"' ) );<br>self::assertSame( 1, substr_count( $body, '"status":"Warning"' ) );<br>self::assertSame( 1, substr_count( $body, '"status":"Info"' ) ); |

#### `tests/Integration/LogExternalHookTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_legacy_insert_payload_keeps_the_original_keys` | test legacy insert payload keeps the original keys | self::assertIsInt( $id );<br>self::assertCount( 1, $this->inserted );<br>self::assertSame( $id, $log_id );<br>self::assertArrayHasKey( $key, $payload, $key ); |
| `test_decision_insert_payload_is_additive_and_complete` | test decision insert payload is additive and complete | self::assertIsInt( $id );<br>self::assertCount( 1, $this->inserted );<br>self::assertArrayHasKey( $key, $payload, $key );<br>self::assertSame( 'shield_incompatible_wp', $payload['reason_code'] ); |
| `test_enrichment_fires_only_the_update_hook` | test enrichment fires only the update hook | self::assertIsInt( $id );<br>self::assertCount( 1, $this->inserted );<br>self::assertCount( 0, $this->updated );<br>self::assertTrue( Updatronix_Logger::attach_context( (int) $id, array( 'chain_id' => 'chain-hook' ) ) ); |
| `test_summary_update_fires_the_update_hook` | test summary update fires the update hook | self::assertIsInt( $id );<br>self::assertTrue( Updatronix_Logger::update_summary( (int) $id, array( 'context' => array( 'skipped' => 7 ) ) ) );<br>self::assertCount( 1, $this->inserted );<br>self::assertCount( 1, $this->updated ); |
| `test_strict_consumer_accepts_both_payloads` | test strict consumer accepts both payloads | self::assertCount( 2, $this->inserted );<br>self::assertTrue( $strict( $this->inserted[0][1] ), 'The legacy payload must satisfy a strict consumer.' );<br>self::assertTrue( $strict( $this->inserted[1][1] ), 'The decision payload must satisfy a strict consumer.' ); |

#### `tests/Integration/LogMultisiteTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_network_decision_is_attributed_to_the_execution_site` | test network decision is attributed to the execution site | self::assertIsInt( $id );<br>self::assertSame( get_main_site_id(), (int) $row->site_id );<br>self::assertSame( 1, (int) $row->network );<br>self::assertSame( 'network', $context['scope'] ); |
| `test_subsite_decision_uses_the_subsite_id` | test subsite decision uses the subsite id | self::assertIsInt( $id );<br>self::assertSame( $subsite_id, (int) $row->site_id );<br>self::assertSame( 0, (int) $row->network ); |
| `test_network_global_sentinel_reads_every_site` | test network global sentinel reads every site | self::assertGreaterThanOrEqual( 1, count( $rows ) );<br>self::assertSame( 1, Updatronix_Logger::get_logs_count( array( 'site_id' => 0 ) ) ); |

#### `tests/Integration/LogPerformanceTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_fifty_item_pass_stays_bounded` | test fifty item pass stays bounded | self::assertNotFalse( Updatronix_Logger::log_event( $event ) );<br>self::assertSame( 51, $this->count_rows() );<br>self::assertCount( 50, $rows );<br>self::assertSame( 51, $total ); |
| `test_ten_thousand_row_export_is_chunked` | test ten thousand row export is chunked | self::assertSame( 10000, $this->count_rows() );<br>self::assertCount( 1000, $rows ); |

#### `tests/Integration/LogQueryFilterTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_reason_is_filters_in_sql_and_total` | test reason is filters in sql and total | self::assertCount( 1, $rows );<br>self::assertSame( 'shield_incompatible_wp', $rows[0]->reason_code );<br>self::assertSame( 1, Updatronix_Logger::get_logs_count( $args ) ); |
| `test_list_operators` | test list operators | self::assertSame( array( 'blocked', 'waiting' ), $states );<br>self::assertSame( 2, Updatronix_Logger::get_logs_count( $is_any ) );<br>self::assertSame( 5, Updatronix_Logger::get_logs_count( $is_not ) );<br>self::assertSame( 4, Updatronix_Logger::get_logs_count( $is_none ) ); |
| `test_action_pass_and_chain_filters` | test action pass and chain filters | self::assertCount( 1, $action_rows );<br>self::assertSame( 'system', $action_rows[0]->log_type );<br>self::assertSame( 6, Updatronix_Logger::get_logs_count( $pass ) );<br>self::assertSame( 2, Updatronix_Logger::get_logs_count( $chain ) ); |
| `test_date_ranges` | test date ranges | self::assertSame( 7, Updatronix_Logger::get_logs_count( $today ) );<br>self::assertSame( 0, Updatronix_Logger::get_logs_count( $past ) );<br>self::assertSame( 1, Updatronix_Logger::get_logs_count( $next ) ); |
| `test_user_filter_is_server_side` | test user filter is server side | self::assertIsInt( $id );<br>self::assertCount( 1, $rows );<br>self::assertSame( $first_user, (int) $rows[0]->user_id );<br>self::assertSame( 1, Updatronix_Logger::get_logs_count( $args ) ); |
| `test_legacy_scalar_args_still_work` | test legacy scalar args still work | self::assertSame( 1, Updatronix_Logger::get_logs_count( array( 'status' => 'warning' ) ) );<br>self::assertSame( 1, Updatronix_Logger::get_logs_count( array( 'log_type' => 'system' ) ) );<br>self::assertSame( 2, Updatronix_Logger::get_logs_count( array( 'action_types' => array( 'pass', 'disabled' ) ) ) );<br>self::assertSame( 0, Updatronix_Logger::get_logs_count( array( 'created_at_after' => '2999-01-01 00:00:00' ) ) ); |
| `test_rest_feed_filters_and_enriches` | test rest feed filters and enriches | self::assertSame( 200, $response->get_status() );<br>self::assertSame( 1, $data['total'] );<br>self::assertCount( 1, $data['logs'] );<br>self::assertSame( 'blocked', $log->decision_state ); |
| `test_rest_scalar_param_and_legacy_row` | test rest scalar param and legacy row | self::assertSame( 2, $data['total'] );<br>self::assertCount( 2, $data['logs'] );<br>self::assertSame( 1, $legacy_data['total'] );<br>self::assertTrue( $legacy->is_legacy ); |
| `test_details_carry_the_decision_context` | test details carry the decision context | self::assertCount( 1, $rows );<br>self::assertSame( 'wp_compatibility', $details->decision_context['policy_id'] );<br>self::assertSame( '6.2', $details->decision_context['installed_wp'] );<br>self::assertSame( 'WordPress 6.6 required (installed 6.2)', $details->policy_detail ); |
| `test_next_eligible_display_uses_the_site_timezone` | test next eligible display uses the site timezone | self::assertCount( 1, $rows );<br>self::assertSame( LogEventFactory::NEXT_ELIGIBLE_AT, $rows[0]->next_eligible_at );<br>self::assertSame( wp_date( 'Y-m-d H:i:s', (int) $utc ), $display );<br>self::assertSame( 'waiting', $rows[0]->decision_state ); |

#### `tests/Integration/LogRetentionTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_newest_blocking_row_survives_retention` | test newest blocking row survives retention | self::assertSame( 2, $deleted );<br>self::assertNull( $this->row( $old_block ) );<br>self::assertNull( $this->row( $normal ) );<br>self::assertIsObject( $kept ); |
| `test_retained_cap_drops_the_oldest` | test retained cap drops the oldest | self::assertCount( 2, $retained );<br>self::assertSame( $newest_two, $retained ); |
| `test_pass_summary_is_not_orphaned` | test pass summary is not orphaned | self::assertIsObject( $this->row( $item ) );<br>self::assertIsObject( $this->row( $summary ), 'The pass summary must outlive its retained item.' ); |

#### `tests/Integration/LogSchemaMigrationTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_v1_table_upgrades_once` | test v1 table upgrades once | self::assertTrue( Updatronix_Database::maybe_upgrade() );<br>self::assertSame( Updatronix_Database::SCHEMA_VERSION, updatronix_get_plugin_option( Updatronix_Database::OPTION_DB_VERSION, '' ) );<br>self::assertContains( $column, $columns, 'Missing column: ' . $column );<br>self::assertCount( 30, $columns ); |
| `test_second_run_is_a_noop` | test second run is a noop | self::assertTrue( Updatronix_Database::maybe_upgrade() );<br>self::assertFalse( Updatronix_Database::needs_upgrade() );<br>self::assertSame( '', $row->reason_code );<br>self::assertSame( '', $row->decision_state ); |
| `test_fresh_create_writes_the_option` | test fresh create writes the option | self::assertTrue( Updatronix_Database::create_table() );<br>self::assertSame( Updatronix_Database::SCHEMA_VERSION, updatronix_get_plugin_option( Updatronix_Database::OPTION_DB_VERSION, '' ) );<br>self::assertContains( 'decision_state', $this->get_column_names() ); |
| `test_schema_version_is_independent_of_the_plugin_version` | test schema version is independent of the plugin version | self::assertSame( '3', Updatronix_Database::SCHEMA_VERSION );<br>self::assertSame( Updatronix_Database::SCHEMA_VERSION, Updatronix_Database::DB_VERSION );<br>self::assertNotSame( UPDATRONIX_VERSION, Updatronix_Database::SCHEMA_VERSION );<br>self::assertSame( UPDATRONIX_VERSION, UPDATRONIX_DB_VERSION, 'The plugin DB-version constant must stay untouched.' ); |
| `test_multisite_upgrades_the_main_site_table` | test multisite upgrades the main site table | self::markTestSkipped( 'Multisite migration requires WP_MULTISITE=1.' );<br>self::assertTrue( Updatronix_Database::maybe_upgrade() );<br>self::assertSame( Updatronix_Database::SCHEMA_VERSION, updatronix_get_plugin_option( Updatronix_Database::OPTION_DB_VERSION, '' ) );<br>self::assertContains( 'decision_state', $columns ); |

#### `tests/Integration/LogTruthfulnessTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_schedule_held_state_matches_the_log` | test schedule held state matches the log | self::assertIsInt( $id );<br>self::assertTrue( $this->assert_log_state( $screen, array( 'reason_code' => 'schedule_hold', 'decision_state' => 'delayed', 'next_eligible_at' => LogEventFactory::NEXT_ELIGIBLE_AT, 'origin' => 'automatic', 'decision_sta… |
| `test_auto_updates_blocked_state_matches_the_log` | test auto updates blocked state matches the log | self::assertIsInt( $id );<br>self::assertTrue( $this->assert_log_state( array( 'site_id' => get_current_blog_id(), 'log_type' => 'plugin', 'item_slug' => 'fixture/shield-wp.php', 'target_version' => '2.0.0', ), array( 'reason_code' => 'shield_incom… |
| `test_log_pass_filter_matches_the_pass_rows` | test log pass filter matches the pass rows | self::assertCount( 2, $rows );<br>self::assertSame( 2, Updatronix_Logger::get_logs_count( array( 'filters' => array( array( 'field' => 'pass_id', 'operator' => 'is', 'value' => LogEventFactory::PASS_ID, ), ), ) ) ); |
| `test_legacy_row_is_not_guessed` | test legacy row is not guessed | self::assertIsInt( $id );<br>self::assertTrue( $log->is_legacy );<br>self::assertSame( 'Legacy — reason not recorded', $log->reason_label ); |
| `test_a_missing_row_fails_the_audit` | test a missing row fails the audit | self::assertFalse( $this->assert_log_state( $screen, array( 'decision_state' => 'blocked' ) ) ); |

#### `tests/Integration/LoggerActionTypeQueryTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_single_action_type_filter` | test single action type filter | self::assertContains( 'logger_action_type_prevented', $keys );<br>self::assertNotContains( 'logger_action_type_update', $keys );<br>self::assertSame( 1, Updatronix_Logger::get_logs_count( array( 'action_type' => 'prevented' ) ) ); |
| `test_action_type_list_filter` | test action type list filter | self::assertContains( 'logger_action_type_prevented', $keys );<br>self::assertContains( 'logger_action_type_safe_mode', $keys );<br>self::assertNotContains( 'logger_action_type_update', $keys );<br>self::assertSame( 2, Updatronix_Logger::get_logs_count( $args ) ); |
| `test_created_at_bound_filter` | test created at bound filter | self::assertNotContains( 'logger_action_type_update', $keys );<br>self::assertNotContains( 'logger_action_type_prevented', $keys ); |
| `test_query_args_filter_can_add_action_type` | test query args filter can add action type | self::assertContains( 'logger_action_type_prevented', $keys );<br>self::assertNotContains( 'logger_action_type_update', $keys ); |

#### `tests/Integration/LogsRestApiTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_logs_list_returns_enriched_rows` | test logs list returns enriched rows | self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertArrayHasKey( 'logs', $data );<br>self::assertArrayHasKey( 'total', $data ); |
| `test_logs_list_filters_by_delayed_and_cancelled_status` | test logs list filters by delayed and cancelled status | self::assertSame( 200, $response->get_status() );<br>self::assertSame( 1, $data['total'] );<br>self::assertSame( 'Delay Plugin', (string) $data['logs'][0]->item_name );<br>self::assertSame( 'Cancel Plugin', (string) $data['logs'][0]->item_name ); |
| `test_get_log_by_id_returns_enriched_detail` | test get log by id returns enriched detail | self::assertIsInt( $log_id );<br>self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertArrayHasKey( 'log', $data ); |
| `test_core_log_changelog_url_points_to_releases` | test core log changelog url points to releases | self::assertIsInt( $log_id );<br>self::assertSame( 200, $response->get_status() );<br>self::assertSame( 'https://wordpress.org/download/releases/', $data['log']->changelog_url ); |
| `test_get_log_by_missing_id_returns_404` | test get log by missing id returns 404 | self::assertSame( 404, $response->get_status() ); |
| `test_delete_log_by_id_removes_row` | test delete log by id removes row | self::assertIsInt( $log_id );<br>self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertTrue( $data['deleted'] ?? false ); |
| `test_delete_log_by_missing_id_returns_404` | test delete log by missing id returns 404 | self::assertSame( 404, $response->get_status() ); |
| `test_cleanup_logs_deletes_only_logs_older_than_retention` | test cleanup logs deletes only logs older than retention | self::assertIsInt( $fresh_id );<br>self::assertIsInt( $old_id );<br>self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data ); |
| `test_delete_all_logs_returns_deleted_count` | test delete all logs returns deleted count | self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertSame( 2, (int) ( $data['deleted'] ?? 0 ) );<br>self::assertSame( 0, Updatronix_Logger::get_logs_count( array() ) ); |
| `test_export_returns_ready_response` | test export returns ready response | self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertSame( 'ready', $data['status'] ?? null );<br>self::assertIsString( $data['body'] ?? null ); |
| `test_export_rejects_json_in_free_edition` | test export rejects json in free edition | self::assertSame( 400, $response->get_status() ); |
| `test_export_rejects_unknown_top_level_key` | test export rejects unknown top level key | self::assertSame( 400, $response->get_status() ); |
| `test_auto_update_routes_return_403_when_file_mods_disallowed` | test auto update routes return 403 when file mods disallowed | self::assertSame( 403, $response->get_status(), 'Updating core must be locked while file mods are disallowed.' );<br>self::assertIsArray( $data );<br>self::assertSame( 'File modifications are not allowed on this site via the DISALLOW_FILE_MODS constant in your wp-config.php file.', $data['message'] ?? null, 'The kill-switch 403 must carry the DISALLOW_FILE_MODS expla…<br>self::assertSame( 403, $toggle_response->get_status(), 'Toggling plugins must also be locked.' ); |
| `test_maybe_schedule_if_needed_schedules_cleanup_and_throttles` | test maybe schedule if needed schedules cleanup and throttles | self::assertIsInt( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'First call must schedule the daily cleanup hook.' );<br>self::assertFalse( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'Throttled call must not re-schedule the hook.' );<br>self::assertIsInt( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'After throttle expiry the cleanup hook must be re-scheduled.' ); |
| `test_run_cleanup_deletes_old_logs_via_cron` | test run cleanup deletes old logs via cron | self::assertIsInt( $fresh_id );<br>self::assertIsInt( $old_id );<br>self::assertNotNull( Updatronix_Logger::get_log( $fresh_id, false ), 'Fresh log must survive the cron cleanup.' );<br>self::assertNull( Updatronix_Logger::get_log( $old_id, false ), 'Backdated log must be purged by the cron cleanup.' ); |
| `test_clear_subsite_cron_artifacts_leaves_single_site_events_intact` | test clear subsite cron artifacts leaves single site events intact | self::markTestSkipped( 'Single-site behavior only.' );<br>self::assertIsInt( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'Main-site cleanup event must be untouched.' );<br>self::assertIsInt( wp_next_scheduled( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK ), 'Main-site version-check event must be untouched.' ); |
| `test_delete_older_than_guard_returns_zero_for_invalid_days` | test delete older than guard returns zero for invalid days | self::assertSame( 0, Updatronix_Logger::delete_older_than( 0 ) );<br>self::assertSame( 0, Updatronix_Logger::delete_older_than( -3 ) ); |

#### `tests/Integration/Multisite/MultisiteNetworkOnlyTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_subsite_should_not_load_plugin_runtime` | test subsite should not load plugin runtime | self::markTestSkipped( 'Multisite blog factory unavailable.' );<br>self::assertFalse( updatronix_should_load(), 'Subsite context must not load Updatronix runtime.' ); |
| `test_network_settings_use_site_option_across_subsites` | test network settings use site option across subsites | self::markTestSkipped( 'Multisite helpers unavailable.' );<br>self::assertSame( 'network@example.com', updatronix_get_settings()['notify_emails'] ); |
| `test_super_admin_schedule_write_persists_network_wide` | test super admin schedule write persists network wide | self::markTestSkipped( 'Multisite helpers unavailable.' );<br>self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertSame( 'weekly', $data['options']['schedule']['update_check']['recurrence'] ?? null, 'The settings POST response must echo the persisted network schedule.' ); |
| `test_network_admin_log_list_spans_all_originating_sites` | test network admin log list spans all originating sites | self::markTestSkipped( 'Multisite helpers unavailable.' );<br>self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertContains( 'Main Origin Plugin', $names, 'Default network-admin list must include main-site rows.' ); |
| `test_explicit_site_id_narrows_network_log_list` | test explicit site id narrows network log list | self::markTestSkipped( 'Multisite helpers unavailable.' );<br>self::assertSame( 200, $response->get_status() );<br>self::assertContains( 'Subsite Only Plugin', $names );<br>self::assertNotContains( 'Main Only Plugin', $names, 'Explicit site_id must scope to the requested subsite.' ); |
| `test_deleting_a_site_purges_its_log_rows` | test deleting a site purges its log rows | self::assertSame( 1, Updatronix_Logger::get_logs_count( array( 'site_id' => $foreign_site_id ) ) );<br>self::assertSame( 0, Updatronix_Logger::get_logs_count( array( 'site_id' => $foreign_site_id ) ) ); |
| `test_uninstall_clears_network_schedule_site_option` | test uninstall clears network schedule site option | self::assertNotSame( '', get_site_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE, '' ) );<br>self::assertFalse( get_site_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE, false ) ); |
| `test_uninstall_keeps_network_schedule_when_delete_data_is_off` | test uninstall keeps network schedule when delete data is off | self::assertNotFalse( get_site_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE, false ), 'Uninstall must keep the schedule when the Data opt-in is off.' ); |

#### `tests/Integration/Multisite/MultisiteNetworkScopeTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_subsite_runtime_gate_is_false` | test subsite runtime gate is false | self::assertFalse( updatronix_should_load(), 'Subsite context must not load Updatronix runtime.' );<br>self::assertFalse( updatronix_activation_allowed(), 'Subsite activation mutations must be rejected.' ); |
| `test_subsite_admin_cannot_manage_network_logs` | test subsite admin cannot manage network logs | self::assertFalse( Updatronix_Security::user_can_manage_logs(), 'A non-super-admin subsite admin must not manage network logs.' ); |
| `test_settings_saved_on_main_site_are_visible_from_subsite` | test settings saved on main site are visible from subsite | self::assertSame( 'shared-network@example.com', updatronix_get_settings()['notify_emails'], 'Network settings must be shared across subsites (sitemeta).' ); |
| `test_plugin_cron_lives_only_on_main_site` | test plugin cron lives only on main site | self::assertNotFalse( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'Main site must hold the cleanup event.' );<br>self::assertFalse( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'Subsite must not hold the plugin cleanup cron after artifact cleanup.' );<br>self::assertFalse( wp_next_scheduled( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK ), 'Subsite must not hold the plugin update-check cron.' );<br>self::assertNotFalse( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'Main-site cleanup event must survive cleanup.' ); |
| `test_super_admin_log_defaults_to_network_global_scope` | test super admin log defaults to network global scope | self::assertSame( 200, $response->get_status() );<br>self::assertContains( 'Main Site Item', $names, 'Network-global log scope must include main-site rows.' );<br>self::assertContains( 'Subsite Item', $names, 'Network-global log scope must include subsite-origin rows.' ); |

#### `tests/Integration/Multisite/MultisiteScheduleTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_subsite_reads_the_main_site_event` | test subsite reads the main site event | self::assertFalse( wp_next_scheduled( 'wp_version_check' ), 'The subsite has no local event.' );<br>self::assertTrue( Updatronix_Schedule_Context::is_subsite() );<br>self::assertFalse( Updatronix_Schedule_Context::has_local_schedule() );<br>self::assertSame( $main_event, Updatronix_Schedule_Context::next_check() ); |
| `test_subsite_item_state_is_network` | test subsite item state is network | self::assertSame( Updatronix_Schedule_State::STATE_NETWORK, $item['state'] );<br>self::assertNull( $item['eligible_at'] );<br>self::assertNull( $item['execution_at'] );<br>self::assertStringContainsString( 'network update schedule', $item['display'] ); |
| `test_subsite_plugin_row_shows_the_network_message` | test subsite plugin row shows the network message | self::assertStringContainsString( 'network update schedule', $html );<br>self::assertStringNotContainsString( 'Automatic update scheduled in 3 hours.', $html ); |
| `test_network_admin_uses_the_network_schedule` | test network admin uses the network schedule | self::assertFalse( Updatronix_Schedule_Context::is_subsite() );<br>self::assertSame( $main_event, Updatronix_Schedule_Context::next_check() );<br>self::assertSame( 'daily', updatronix_get_network_schedule()['update_check']['recurrence'] );<br>self::assertNotSame( Updatronix_Schedule_State::STATE_NETWORK, $item['state'] ); |
| `test_network_schedule_is_shared` | test network schedule is shared | self::assertSame( 'weekly', $schedule['update_check']['recurrence'] );<br>self::assertSame( '04:15', $schedule['update_check']['time'] );<br>self::assertSame( 7, $schedule['delay_updates']['delay_value'] ); |

#### `tests/Integration/NativeLoggerRowTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_reported_core_success_writes_one_row` | test reported core success writes one row | self::assertSame( 1, $this->count_rows() );<br>self::assertSame( 'core', $row->log_type );<br>self::assertSame( 'core:' . Updatronix_Item_State::CORE_ID, $row->item_slug );<br>self::assertSame( 'update', $row->action_type ); |
| `test_installed_target_is_not_flushed_as_an_error` | test installed target is not flushed as an error | self::assertSame( 1, $this->count_rows() );<br>self::assertSame( 'success', $row->status );<br>self::assertSame( 'done', $row->decision_state );<br>self::assertStringNotContainsString( 'may not have completed', (string) $row->message ); |
| `test_unfinished_core_update_writes_one_error_row` | test unfinished core update writes one error row | self::assertSame( 1, $this->count_rows() );<br>self::assertSame( 'failed', $row->action_type );<br>self::assertSame( 'error', $row->status );<br>self::assertSame( 'update_failed', $row->reason_code ); |
| `test_core_finalizer_is_idempotent` | test core finalizer is idempotent | self::assertSame( 1, $this->count_rows() ); |
| `test_context_filter_enriches_the_native_row` | test context filter enriches the native row | self::assertSame( LogEventFactory::PASS_ID, $row->pass_id );<br>self::assertSame( 'flow', $row->origin );<br>self::assertSame( LogEventFactory::CHAIN_ID, $row->chain_id );<br>self::assertSame( 'plugin:fixture/predecessor.php', $row->predecessor ); |
| `test_one_native_event_produces_one_row` | test one native event produces one row | self::assertSame( $first, $second );<br>self::assertSame( 1, $this->count_rows() ); |

#### `tests/Integration/NativeUpdateTimeTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_plugin_row_replaces_only_the_time_text` | test plugin row replaces only the time text | self::assertStringContainsString( 'Earliest automatic install:', $html );<br>self::assertStringNotContainsString( 'Automatic update scheduled in 3 hours.', $html );<br>self::assertStringContainsString( '<div class="auto-update-time">', $html );<br>self::assertStringContainsString( 'toggle-auto-update', $html ); |
| `test_plugin_row_preserves_the_hidden_class` | test plugin row preserves the hidden class | self::assertStringContainsString( 'class="auto-update-time hidden"', $html );<br>self::assertStringContainsString( 'Automatic updates are off for this item.', $html ); |
| `test_plugin_row_without_a_map_key_is_unchanged` | test plugin row without a map key is unchanged | self::assertSame( $in, $out ); |
| `test_plugin_row_without_the_container_is_unchanged` | test plugin row without the container is unchanged | self::assertSame( $in, $out ); |
| `test_plugin_row_rollback_switch` | test plugin row rollback switch | self::assertSame( $in, $out ); |
| `test_network_theme_row_replaces_only_the_time_text` | test network theme row replaces only the time text | self::assertStringContainsString( 'Earliest automatic install:', $html );<br>self::assertStringNotContainsString( 'Automatic update scheduled in 3 hours.', $html );<br>self::assertStringContainsString( '<div class="auto-update-time">', $html );<br>self::assertStringContainsString( 'toggle-auto-update', $html ); |
| `test_network_theme_row_preserves_the_hidden_class` | test network theme row preserves the hidden class | self::assertStringContainsString( 'class="auto-update-time hidden"', $html );<br>self::assertStringContainsString( 'Automatic updates are off for this item.', $html ); |
| `test_network_theme_row_without_a_map_key_is_unchanged` | test network theme row without a map key is unchanged | self::assertSame( $in, $out ); |
| `test_network_theme_row_ignores_another_themes_markup` | test network theme row ignores another themes markup | self::assertSame( $other, $out ); |
| `test_prepare_themes_for_js_adds_per_theme_text` | test prepare themes for js adds per theme text | self::assertArrayHasKey( 'updatronixAutoUpdateText', $themes[0] );<br>self::assertStringContainsString( 'Earliest automatic install:', (string) $themes[0]['updatronixAutoUpdateText'] );<br>self::assertStringNotContainsString( 'Automatic update scheduled', (string) $themes[0]['updatronixAutoUpdateText'] );<br>self::assertSame( wp_get_auto_update_message(), $themes[1]['updatronixAutoUpdateText'] ); |
| `test_prepare_themes_for_js_escapes_the_text` | test prepare themes for js escapes the text | self::assertStringNotContainsString( '<b>Composer</b>', (string) $themes[0]['updatronixAutoUpdateText'] ); |
| `test_theme_template_replaces_the_native_call` | test theme template replaces the native call | self::assertStringNotContainsString( '<br />' . $native . '</span>', $out, 'The plain native call is replaced.' );<br>self::assertStringContainsString( 'data.updatronixAutoUpdateText', $out );<br>self::assertStringContainsString( wp_json_encode( $native ), $out, 'The native sentence stays as the fallback.' ); |
| `test_theme_template_without_the_native_call_is_unchanged` | test theme template without the native call is unchanged | self::assertSame( $template, apply_filters( 'theme_auto_update_setting_template', $template ) ); |
| `test_theme_template_keeps_the_version_lock_branch` | test theme template keeps the version lock branch | self::assertStringContainsString( 'data.updatronixVersionLocked', $out );<br>self::assertStringContainsString( 'updatronixVersionLockLabel', $out );<br>self::assertStringContainsString( 'data.updatronixAutoUpdateText', $out );<br>self::assertStringNotContainsString( $native . '</span><# } #>', $out ); |
| `test_theme_template_rollback_switch` | test theme template rollback switch | self::assertSame( $template, apply_filters( 'theme_auto_update_setting_template', $template ) ); |
| `test_summary_renders_counts_and_schedule_link` | test summary renders counts and schedule link | self::assertSame( 1, substr_count( $html, '<p ' ), 'The summary is one paragraph, not a banner.' );<br>self::assertStringContainsString( 'data-updatronix-schedule-summary="1"', $html );<br>self::assertStringContainsString( 'Next automatic pass:', $html );<br>self::assertStringContainsString( sprintf( '%d eligible', $summary['eligible'] ), $html ); |
| `test_summary_renders_without_a_native_core_paragraph` | test summary renders without a native core paragraph | self::assertStringContainsString( 'Next automatic pass:', $html );<br>self::assertStringNotContainsString( 'Automatic update scheduled in', $html ); |
| `test_summary_names_blocked_items` | test summary names blocked items | self::assertGreaterThan( 0, $summary['blocked'] );<br>self::assertStringContainsString( sprintf( '%d items are blocked.', $summary['blocked'] ), $html ); |
| `test_summary_requires_the_capability` | test summary requires the capability | self::assertSame( '', $this->render_summary() ); |
| `test_summary_rollback_switch` | test summary rollback switch | self::assertSame( '', $this->render_summary() ); |
| `test_js_payload_shape` | test js payload shape | self::assertArrayNotHasKey( 'core:core', $payload['items'] );<br>self::assertArrayHasKey( 'theme:' . $this->theme(), $payload['items'] );<br>self::assertArrayHasKey( 'plugin:' . $this->plugin(), $payload['items'] );<br>self::assertSame( wp_get_auto_update_message(), $payload['nativePatterns']['message'] ); |
| `test_delay_banner_is_removed` | test delay banner is removed | self::assertFalse( function_exists( 'updatronix_render_delay_context_admin_notice' ) );<br>self::assertStringNotContainsString( 'updatronix-delay-context-notice', $html );<br>self::assertStringNotContainsString( '920px', $html ); |
| `test_cron_hint_is_shown_when_disabled` | test cron hint is shown when disabled | self::assertStringContainsString( 'updatronix-wp-cron-hint', $html );<br>self::assertSame( 1, substr_count( $html, '<p ' ), 'The hint is one line.' );<br>self::assertStringContainsString( 'a real cron job calls wp-cron.php', $html ); |
| `test_cron_hint_is_hidden_when_cron_is_healthy` | test cron hint is hidden when cron is healthy | self::assertStringNotContainsString( 'updatronix-wp-cron-hint', $html ); |
| `test_cron_hint_requires_the_screen_capability` | test cron hint requires the screen capability | self::assertStringNotContainsString( 'updatronix-wp-cron-hint', $this->render_notices() ); |
| `test_cron_hint_is_scoped_to_the_update_screens` | test cron hint is scoped to the update screens | self::assertStringNotContainsString( 'updatronix-wp-cron-hint', $this->render_notices() ); |
| `test_plugin_row_escapes_the_sentence` | test plugin row escapes the sentence | self::assertStringNotContainsString( '<b>Git</b>', $html ); |

#### `tests/Integration/NotificationsModeDisabledTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_disabled_mode_returns_false_for_core_send_email` | test disabled mode returns false for core send email | self::assertFalse( $send, 'Disabled mode must suppress the core update success email.' );<br>self::assertFalse( $send_fail, 'Disabled mode must suppress the core update fail email.' );<br>self::assertFalse( $send_critical, 'Disabled mode must suppress the critical core update email.' ); |
| `test_disabled_mode_returns_false_for_core_manual_notification_email` | test disabled mode returns false for core manual notification email | self::assertFalse( $send, 'Disabled mode must suppress the manual core update available email.' ); |
| `test_disabled_mode_returns_false_for_plugin_and_theme_send_email` | test disabled mode returns false for plugin and theme send email | self::assertFalse( $send_plugin, 'Disabled mode must suppress the plugin update batch email.' );<br>self::assertFalse( $send_theme, 'Disabled mode must suppress the theme update batch email.' ); |
| `test_disabled_mode_returns_false_for_debug_email` | test disabled mode returns false for debug email | self::assertFalse( $send, 'Disabled mode must suppress the debug summary email.' ); |
| `test_disabled_mode_preserves_recovery_mode_email_payload` | test disabled mode preserves recovery mode email payload | self::assertSame( $email, $filtered, 'Recovery-mode email is the safety net — disabled mode must not redirect, drop, or alter it.' ); |

#### `tests/Integration/NotificationsRecipientSanitisationTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_header_injection_payload_is_stripped` | test header injection payload is stripped | self::assertStringNotContainsString( "\r", $sanitised, 'Carriage returns must be stripped before storage.' );<br>self::assertStringNotContainsString( "\n", $sanitised, 'Line feeds must be stripped before storage.' );<br>self::assertStringNotContainsString( 'Bcc:', $sanitised, 'No header smuggling tokens may survive sanitisation.' );<br>self::assertStringNotContainsString( 'attacker@example.invalid', $sanitised, 'Smuggled recipient must not be persisted.' ); |
| `test_mixed_valid_and_invalid_addresses_keeps_only_valid` | test mixed valid and invalid addresses keeps only valid | self::assertStringContainsString( 'good@example.com', $sanitised );<br>self::assertStringContainsString( 'also@example.org', $sanitised );<br>self::assertStringNotContainsString( 'not-an-email', $sanitised ); |
| `test_recipient_count_cap_at_32` | test recipient count cap at 32 | self::assertLessThanOrEqual( UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS, $count, 'Recipient list must be capped at UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS.' );<br>self::assertSame( UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS, $count, 'When more than the cap of valid addresses are submitted, exactly the cap is kept.' ); |
| `test_raw_byte_length_cap_does_not_overflow` | test raw byte length cap does not overflow | self::assertGreaterThan( UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES, strlen( $input ), 'Test fixture must exceed the byte cap.' );<br>self::assertNotEmpty( $sanitised, 'Truncating at the byte cap must still leave at least one valid address.' );<br>self::assertLessThanOrEqual( UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS, $count, 'Even with a multi-megabyte payload, the recipient cap holds.' ); |
| `test_duplicate_addresses_are_deduplicated` | test duplicate addresses are deduplicated | self::assertSame( 'same@example.com, other@example.com', $sanitised, 'Duplicate addresses must be removed exactly once each.' ); |

#### `tests/Integration/RestSettingsAuthTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_get_settings_returns_403_when_not_logged_in` | test get settings returns 403 when not logged in | self::assertSame( 401, $response->get_status(), 'Anonymous user must not read settings (rest_authorization_required_code() returns 401 when unauthenticated).' ); |
| `test_get_settings_returns_200_for_administrator_with_cap` | test get settings returns 200 for administrator with cap | self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertArrayHasKey( 'options', $data );<br>self::assertIsArray( $data['options'] ); |
| `test_get_settings_returns_403_for_user_without_manage_cap` | test get settings returns 403 for user without manage cap (data: `low_privilege_roles_without_cap`) | self::assertSame( 403, $response->get_status(), 'User without ' . UPDATRONIX_CAP_MANAGE . ' must not read settings.' ); |
| `test_get_logs_returns_403_for_user_without_manage_cap` | test get logs returns 403 for user without manage cap (data: `low_privilege_roles_without_cap`) | self::assertSame( 403, $response->get_status(), 'User without ' . UPDATRONIX_CAP_MANAGE . ' must not read logs.' ); |
| `test_post_settings_with_cookie_auth_and_valid_nonce_returns_200` | test post settings with cookie auth and valid nonce returns 200 | self::assertInstanceOf( WP_REST_Response::class, $response );<br>self::assertSame( 200, $response->get_status() );<br>self::assertIsArray( $data );<br>self::assertArrayHasKey( 'options', $data ); |
| `test_post_settings_with_cookie_auth_and_missing_nonce_is_rejected` | test post settings with cookie auth and missing nonce is rejected | self::assertNotWPError( $auth, 'Missing nonce should not yield a WP_Error from check_authentication().' );<br>self::assertSame( 0, get_current_user_id(), 'Cookie auth without nonce clears the current user (see rest_cookie_check_errors()).' );<br>self::assertSame( 401, $response->get_status(), 'Mutating REST request with cookie auth but no nonce logs the user out and must be rejected with 401 (unauthenticated).' ); |
| `test_post_settings_with_cookie_auth_and_invalid_nonce_returns_403` | test post settings with cookie auth and invalid nonce returns 403 | self::assertWPError( $auth );<br>self::assertSame( 'rest_cookie_invalid_nonce', $auth->get_error_code() );<br>self::assertSame( 403, $response->get_status() );<br>self::assertIsArray( $data ); |

#### `tests/Integration/ScheduleStateIntegrationTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_map_is_capped_and_cached` | test map is capped and cached | self::assertLessThanOrEqual( 2, count( $first['items'] ) );<br>self::assertSame( $first, $second, 'The map must be cached per request.' );<br>self::assertGreaterThanOrEqual( count( $first['items'] ), $first['total'] );<br>self::assertSame( $first['total'] > 2, $first['truncated'] ); |
| `test_offer_states` | test offer states | self::assertSame( 'pending', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );<br>self::assertSame( 'stale', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );<br>self::assertSame( 'none', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );<br>self::assertSame( 'unknown', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) ); |
| `test_offer_ttl_filter` | test offer ttl filter | self::assertSame( 'pending', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );<br>self::assertSame( 'stale', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) ); |
| `test_held_item_state` | test held item state | self::assertSame( Updatronix_Schedule_State::STATE_HELD, $item['state'] );<br>self::assertIsInt( $item['hold_until'] );<br>self::assertGreaterThan( time(), $item['eligible_at'] );<br>self::assertNull( $item['execution_at'] ); |
| `test_auto_update_off_state` | test auto update off state | self::assertSame( Updatronix_Schedule_State::STATE_NOT_MANAGED, $item['state'] ); |
| `test_pro_facts_stub_blocks_the_item` | test pro facts stub blocks the item | self::assertSame( Updatronix_Schedule_State::STATE_BLOCKED, $item['state'] );<br>self::assertStringContainsString( 'Requires PHP 8.3', $item['display'] ); |
| `test_missing_facts_are_unknown_when_the_filter_is_active` | test missing facts are unknown when the filter is active | self::assertSame( Updatronix_Schedule_State::STATE_UNKNOWN, $item['state'] );<br>self::assertNull( $item['execution_at'] ); |
| `test_locked_fact_wins` | test locked fact wins | self::assertSame( Updatronix_Schedule_State::STATE_LOCKED, $item['state'] );<br>self::assertSame( 'Managed via Git. Update through your manager.', $item['display'] ); |
| `test_render_has_no_side_effects` | test render has no side effects | self::assertSame( $ledger_before, get_site_option( 'updatronix_auto_update_delay_ledger', null ) );<br>self::assertSame( $schedule_before, get_site_option( 'updatronix_network_schedule', null ) );<br>self::assertSame( $logs_before, $this->count_logs() ); |
| `test_two_hundred_item_map_budget` | test two hundred item map budget | self::markTestSkipped( 'The plugin directory is not writable in this environment.' );<br>self::markTestSkipped( 'The plugin directory could not be created.' );<br>self::assertGreaterThanOrEqual( 200, count( $map['items'] ) );<br>self::assertLessThan( 5.0, $elapsed, 'The 200-item map must stay within the test budget.' ); |
| `test_time_only_save_reschedules_the_event` | test time only save reschedules the event | self::assertIsInt( $first );<br>self::assertIsInt( $second );<br>self::assertNotSame( $first, $second );<br>self::assertSame( 'daily', wp_get_schedule( 'wp_version_check' ) ); |
| `test_rest_hold_round_trip` | test rest hold round trip | self::assertSame( 365, $data['options']['schedule']['delay_updates']['delay_value'] );<br>self::assertTrue( $data['options']['schedule']['delay_updates']['enabled'] );<br>self::assertSame( '02:17', $data['options']['schedule']['update_check']['time'] );<br>self::assertSame( 365, $reloaded['delay_updates']['delay_value'] ); |
| `test_rest_accepts_the_empty_fresh_install_time` | test rest accepts the empty fresh install time | self::assertSame( '', $data['options']['schedule']['update_check']['time'] );<br>self::assertSame( 3, $data['options']['schedule']['delay_updates']['delay_value'] ); |
| `test_rest_rejects_invalid_time` | test rest rejects invalid time | self::assertSame( 400, $response->get_status() ); |
| `test_core_offer_states` | test core offer states | self::assertSame( Updatronix_Schedule_State::STATE_NOT_OBSERVED, $item['state'], 'A held core offer with no ledger row is not observed.' );<br>self::assertSame( 'minor', $item['core_kind'] );<br>self::assertSame( Updatronix_Schedule_State::STATE_HELD, $held['state'] );<br>self::assertSame( Updatronix_Schedule_State::STATE_NOT_MANAGED, $major_item['state'], 'Major auto-updates are unset.' ); |
| `test_ledger_hash_parity` | test ledger hash parity | self::assertSame( hash( 'sha256', 'plugin\|a/a.php\|2.0' ), Updatronix_AutoUpdateDelay::ledger_hash( 'plugin', $plugin ) );<br>self::assertSame( hash( 'sha256', 'theme\|a-theme\|2.0' ), Updatronix_AutoUpdateDelay::ledger_hash( 'theme', $theme ) );<br>self::assertSame( hash( 'sha256', 'core\|6.9\|6.9' ), Updatronix_AutoUpdateDelay::ledger_hash( 'core', $core ) );<br>self::assertSame( hash( 'sha256', 'translation\|plugin\|a/a.php\|fr_FR\|2026' ), Updatronix_AutoUpdateDelay::ledger_hash( 'translation', $trans ) ); |
| `test_peek_returns_truth_and_never_writes` | test peek returns truth and never writes | self::assertSame( $now - 3600, Updatronix_AutoUpdateDelay::peek_first_seen( 'plugin', $offer ) );<br>self::assertSame( $before, get_site_option( 'updatronix_auto_update_delay_ledger', '' ), 'The peek must not write the ledger.' );<br>self::assertSame( 0, $this->count_logs(), 'The peek must not log.' );<br>self::assertNull( Updatronix_AutoUpdateDelay::peek_first_seen( 'plugin', $offer ), 'A row more than 300 seconds in the future is ignored.' ); |
| `test_peek_is_null_when_the_hold_is_off` | test peek is null when the hold is off | self::assertNull( Updatronix_AutoUpdateDelay::peek_first_seen( 'plugin', $offer ) ); |
| `test_observe_offer_is_idempotent` | test observe offer is idempotent | self::assertNotNull( $first );<br>self::assertSame( $first, $second, 'A second observation must not move first_seen.' );<br>self::assertSame( $logs, $this->count_logs(), 'observe_offer() must not log.' );<br>self::assertIsArray( $ledger ); |
| `test_observe_offer_is_null_when_the_hold_is_off` | test observe offer is null when the hold is off | self::assertNull( Updatronix_AutoUpdateDelay::observe_offer( 'plugin', ScheduleFixtures::plugin_offer( 'a/a.php', '1.0', '2.0' ) ) ); |
| `test_delayed_row_matches_the_calculator` | test delayed row matches the calculator | self::assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( true, $offer ) );<br>self::assertIsArray( $row );<br>self::assertSame( 'schedule_hold', $row['reason_code'] );<br>self::assertSame( 'delayed', $row['decision_state'] ); |
| `test_delayed_row_uses_the_hold_end_when_the_check_is_overdue` | test delayed row uses the hold end when the check is overdue | self::assertGreaterThanOrEqual( $expected - 2, strtotime( $next . ' UTC' ) );<br>self::assertLessThanOrEqual( $expected + 2, strtotime( $next . ' UTC' ) ); |

#### `tests/Integration/SettingsPostSaveActionTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_dismiss_constant_fires_post_save_action` | test dismiss constant fires post save action | self::assertSame( 1, $this->hits, 'Dismissing an allowlisted constant must fire updatronix_after_save_settings.' );<br>self::assertContains( 'WP_AUTO_UPDATE_CORE', $stored, 'Allowlisted constant must be persisted in dismissed_constants.' ); |
| `test_dismiss_constant_with_unknown_value_does_not_fire_action_or_persist` | test dismiss constant with unknown value does not fire action or persist | self::assertFalse( $ok, 'Unknown constants must not be accepted.' );<br>self::assertSame( 0, $this->hits, 'Rejected dismissals must not fire post-save action.' );<br>self::assertNotContains( 'NOT_A_REAL_CONSTANT', updatronix_get_settings()['dismissed_constants'], 'Unknown constants must not be persisted.' ); |
| `test_set_translations_fires_post_save_action` | test set translations fires post save action | self::assertSame( 1, $this->hits, 'Toggling translations off must fire updatronix_after_save_settings.' );<br>self::assertFalse( updatronix_get_settings()['auto_update_translations'] );<br>self::assertSame( 2, $this->hits, 'Toggling translations on must fire updatronix_after_save_settings again.' );<br>self::assertTrue( updatronix_get_settings()['auto_update_translations'] ); |
| `test_dismiss_constant_does_not_re_arm_cron_when_schedule_unchanged` | test dismiss constant does not re arm cron when schedule unchanged | self::assertIsInt( $first, 'Setup must register the unified update-check event.' );<br>self::assertSame( $first, $after, 'Unrelated settings writes must not reset the wp_version_check first-run timestamp.' ); |

#### `tests/Integration/SettingsUninstallOptInTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_opt_in_defaults_to_false` | test opt in defaults to false | self::assertFalse( updatronix_get_settings()['delete_data_on_uninstall'], 'The uninstall-data opt-in must default to false.' ); |
| `test_opt_in_persists_and_controls_uninstall_cleanup` | test opt in persists and controls uninstall cleanup | self::assertTrue( updatronix_get_settings()['delete_data_on_uninstall'], 'The opt-in must persist as true.' );<br>self::assertTrue( $method->invoke( null ), 'The uninstall runner must delete data when the opt-in is true.' );<br>self::assertFalse( updatronix_get_settings()['delete_data_on_uninstall'], 'The opt-in must persist as false.' );<br>self::assertFalse( $method->invoke( null ), 'The uninstall runner must keep data when the opt-in is false.' ); |
| `test_rest_route_persists_opt_in` | test rest route persists opt in | self::assertSame( 200, $response->get_status() );<br>self::assertTrue( (bool) ( $data['options']['delete_data_on_uninstall'] ?? false ), 'The REST route must persist the uninstall opt-in.' ); |

### JS (132 cases)

#### `tests/js/autoUpdatesDraft.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `maps the REST dataset to the editable draft` | maps the REST dataset to the editable draft | **NONE** |
| `falls back to safe defaults for a missing dataset` | falls back to safe defaults for a missing dataset | expect( buildAutoUpdateDraft( null ) ).toEqual( { coreMode: 'minor', translations: false, themes: {}, plugins: {}, } ); |
| `returns nothing when the draft matches the saved state` | returns nothing when the draft matches the saved state | expect( collectAutoUpdateMutations( { ...SAVED }, SAVED ) ).toEqual( [] ); |
| `emits core and translation changes in order` | emits core and translation changes in order | expect( collectAutoUpdateMutations( { ...SAVED, coreMode: 'all', translations: false }, SAVED ) ).toEqual( [ { path: '/core', data: { mode: 'all' } }, { path: '/translation', data: { enable: false } }, ] ); |
| `emits only changed themes and plugins` | emits only changed themes and plugins | **NONE** |
| `keeps the documented mutation order across all groups` | keeps the documented mutation order across all groups | expect( collectAutoUpdateMutations( { coreMode: 'all', translations: false, themes: { 'twenty-twenty-five': false }, plugins: { 'hello.php': true }, }, SAVED ).map( ( mutation ) => mutation.path ) ).toEqual( [ '/core', … |

#### `tests/js/exportFormats.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `keeps plain text for a Free-only payload` | keeps plain text for a Free-only payload | expect( filterExportFormats( [ 'txt' ] ) ).toEqual( [ 'txt' ] ); |
| `keeps the Pro formats in order` | keeps the Pro formats in order | expect( filterExportFormats( [ 'txt', 'csv', 'json', 'xml' ] ) ).toEqual( [ 'txt', 'csv', 'json', 'xml' ] ); |
| `drops unknown formats` | drops unknown formats | expect( filterExportFormats( [ 'pdf', 'txt', 'json' ] ) ).toEqual( [ 'txt', 'json', ] ); |
| `removes duplicates` | removes duplicates | expect( filterExportFormats( [ 'txt', 'txt', 'csv' ] ) ).toEqual( [ 'txt', 'csv', ] ); |
| `falls back to plain text for an empty payload` | falls back to plain text for an empty payload | expect( filterExportFormats( [] ) ).toEqual( [ 'txt' ] );<br>expect( filterExportFormats( null ) ).toEqual( [ 'txt' ] ); |
| `pins the known format list` | pins the known format list | expect( KNOWN_EXPORT_FORMATS ).toEqual( [ 'txt', 'csv', 'json', 'xml', ] ); |

#### `tests/js/exportLogsDecision.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `keeps the old columns first and appends the decision columns` | keeps the old columns first and appends the decision columns | expect( keys.slice( 0, 7 ) ).toEqual( [ 'headingTable', 'action', 'runContext', 'user', 'status', 'category', 'reason', ] );<br>expect( keys ).toContain( key );<br>expect( DEFAULT_EXPORT_COLUMNS.decisionContext ).toBe( false ); |
| `maps client columns to the REST payload keys` | maps client columns to the REST payload keys | expect( payload ).toMatchObject( { table_heading: true, action_type: true, run_context: true, user: true, status: true, category: true, reason: true, state: true, origin: true, pass: true, chain: true, next_eligible: tr… |
| `renders the decision fields in the XML report when present` | renders the decision fields in the XML report when present | expect( xml ).toContain( '<field name="Reason">Requires a newer WordPress version</field>' );<br>expect( xml ).toContain( '<field name="State">Blocked</field>' );<br>expect( xml ).toContain( '<field name="Origin">Automatic</field>' );<br>expect( xml ).toContain( 'name="Pass"' ); |
| `never labels a warning or info row as Success` | never labels a warning or info row as Success | expect( xml ).toContain( 'Warning' );<br>expect( xml ).toContain( 'Info' );<br>expect( xml ).not.toContain( 'Success' ); |

#### `tests/js/exportReports.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `wraps rows in a UTF-8 XML document` | wraps rows in a UTF-8 XML document | expect( xml ).toContain( '<?xml version="1.0" encoding="UTF-8"?>' );<br>expect( xml ).toContain( '<updatronix-export>' );<br>expect( xml ).toContain( '<entry>' );<br>expect( xml ).toContain( '<field name="Element">WooCommerce</field>' ); |
| `escapes XML special characters in values and labels` | escapes XML special characters in values and labels | **NONE** |
| `skips columns the row does not carry` | skips columns the row does not carry | expect( xml ).toContain( '<field name="Element">Only plugin</field>' );<br>expect( xml ).not.toContain( 'name="Status"' );<br>expect( xml ).not.toContain( 'name="Category"' ); |
| `returns an empty root for no rows` | returns an empty root for no rows | expect( xml ).toBe( [ '<?xml version="1.0" encoding="UTF-8"?>', '<updatronix-export>', '</updatronix-export>', ].join( '\n' ) ); |

#### `tests/js/healthDiagnostics.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `loads when no payload exists` | loads when no payload exists | expect( canLoadDiagnostics( null, false ) ).toBe( true ); |
| `does not reload a successful payload` | does not reload a successful payload | expect( canLoadDiagnostics( { environment: [], text: 'ok' }, false ) ).toBe( false ); |
| `retries an error payload` | retries an error payload | expect( canLoadDiagnostics( { error: 'down' }, false ) ).toBe( true ); |
| `blocks a second request while loading` | blocks a second request while loading | expect( canLoadDiagnostics( null, true ) ).toBe( false );<br>expect( canLoadDiagnostics( { error: 'down' }, true ) ).toBe( false ); |

#### `tests/js/logDecisionDetails.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `renders the state badge label and intent` | renders the state badge label and intent | expect( getStateLabel( 'blocked' ) ).toBe( 'Blocked' );<br>expect( stateToBadgeIntent( 'blocked' ) ).toBe( 'high' );<br>expect( getStateLabel( 'uncertain' ) ).toBe( 'Needs review' ); |
| `builds the decision facts in order` | builds the decision facts in order | expect( keys ).toEqual( [ 'state', 'reason', 'origin', 'stage', 'pass', 'chain', 'predecessor', 'target_version', 'policy', ] );<br>expect( facts[ 0 ].value ).toBe( 'Blocked' );<br>expect( facts[ 1 ].value ).toBe( 'Requires a newer PHP version' );<br>expect( facts[ 2 ].value ).toBe( 'Automatic' ); |
| `adds the next-eligible and wait facts for a waiting row` | adds the next-eligible and wait facts for a waiting row | expect( byKey.state ).toBe( 'Waiting' );<br>expect( byKey.reason ).toBe( 'Waiting for a predecessor' );<br>expect( byKey.next_eligible_at ).toBe( '3 November 2026, 03:00' );<br>expect( byKey.predecessor ).toBe( 'plugin:a/a.php' ); |
| `shows only the legacy label for a row with no reason` | shows only the legacy label for a row with no reason | expect( facts ).toHaveLength( 1 );<br>expect( facts[ 0 ].key ).toBe( 'reason' );<br>expect( facts[ 0 ].value ).toBe( 'Legacy — reason not recorded' );<br>expect( getReasonLabel( '' ) ).toBe( 'Legacy — reason not recorded' ); |
| `treats an empty row as legacy even without the server flag` | treats an empty row as legacy even without the server flag | expect( facts ).toHaveLength( 1 );<br>expect( facts[ 0 ].value ).toBe( 'Legacy — reason not recorded' ); |
| `shows the retained badge only for a pinned row` | shows the retained badge only for a pinned row | expect( shouldShowRetained( { retained: 1 } ) ).toBe( true );<br>expect( shouldShowRetained( { retained: '1' } ) ).toBe( true );<br>expect( shouldShowRetained( { retained: 0 } ) ).toBe( false );<br>expect( shouldShowRetained( {} ) ).toBe( false ); |

#### `tests/js/logDecisionFilters.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `declares Reason, State, Origin, Pass, Chain, and Next eligible` | declares Reason, State, Origin, Pass, Chain, and Next eligible | expect( byId[ id ] ).toBeDefined();<br>expect( byId[ id ].filterable ).toBe( true );<br>expect( byId[ id ].serverField ).toBeTruthy();<br>expect( byId.reason.serverField ).toBe( 'reason_code' ); |
| `keeps Type, Action, Status, User, and Day with server fields` | keeps Type, Action, Status, User, and Day with server fields | expect( byId.category.serverField ).toBe( 'log_type' );<br>expect( byId.actionType.serverField ).toBe( 'action_type' );<br>expect( byId.status.serverField ).toBe( 'status' );<br>expect( byId.user.serverField ).toBe( 'user_id' ); |
| `exposes every decision filter as a DataView control` | exposes every decision filter as a DataView control | expect( fields[ id ] ).toBeDefined();<br>expect( fields[ id ].filterBy.operators ).toContain( 'is' );<br>expect( fields.nextEligible.filterBy.operators ).toContain( 'after' );<br>expect( fields.state.elements ).toEqual( expect.arrayContaining( [ expect.objectContaining( { value: 'blocked' } ), expect.objectContaining( { value: 'waiting' } ), ] ) ); |
| `maps a DataView filter to the server field token` | maps a DataView filter to the server field token | **NONE** |
| `normalizes a user display name to an id for the server` | normalizes a user display name to an id for the server | expect( filters ).toEqual( [ { field: 'user_id', operator: 'is', value: 7 }, ] ); |
| `summarizes the new dimensions using translated labels` | summarizes the new dimensions using translated labels | expect( labels ).toEqual( [ 'Reason', 'State' ] );<br>expect( summary.dimensions[ 0 ].text ).toBe( 'schedule_hold' ); |
| `keeps the export normalization for the existing dimensions` | keeps the export normalization for the existing dimensions | expect( view.filters[ 0 ].value ).toBe( 'automatic' ); |
| `parses the pass and chain deep links and rejects junk` | parses the pass and chain deep links and rejects junk | expect( parseDeepLinkPassId( `?pass=${ PASS_ID }` ) ).toBe( PASS_ID );<br>expect( parseDeepLinkPassId( '?pass=abcd' ) ).toBe( '' );<br>expect( parseDeepLinkPassId( '' ) ).toBe( '' );<br>expect( parseDeepLinkChainId( '?chain=Chain_A' ) ).toBe( 'chain_a' ); |

#### `tests/js/logDeepLink.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `returns the log id from the query string` | returns the log id from the query string | expect( parseDeepLinkLogId( '?tab=logs&log=42' ) ).toBe( 42 ); |
| `accepts a query string without the leading question mark` | accepts a query string without the leading question mark | expect( parseDeepLinkLogId( 'tab=logs&log=7' ) ).toBe( 7 ); |
| `returns zero when the parameter is missing` | returns zero when the parameter is missing | expect( parseDeepLinkLogId( '?tab=logs' ) ).toBe( 0 ); |
| `returns zero for a non-numeric value` | returns zero for a non-numeric value | expect( parseDeepLinkLogId( '?log=abc' ) ).toBe( 0 ); |
| `returns zero for zero or a negative value` | returns zero for zero or a negative value | expect( parseDeepLinkLogId( '?log=0' ) ).toBe( 0 );<br>expect( parseDeepLinkLogId( '?log=-5' ) ).toBe( 0 ); |
| `returns zero for an empty search` | returns zero for an empty search | expect( parseDeepLinkLogId( '' ) ).toBe( 0 ); |

#### `tests/js/logTaxonomyFixture.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `declares the nine decision states with a label, intent, and status` | declares the nine decision states with a label, intent, and status | expect( taxonomy.state_order ).toHaveLength( 9 );<br>expect( taxonomy.states[ state ] ).toBeDefined();<br>expect( typeof taxonomy.states[ state ].label ).toBe( 'string' );<br>expect( taxonomy.states[ state ].label.length ).toBeGreaterThan( 0 ); |
| `declares origins, stages, and the system log type` | declares origins, stages, and the system log type | expect( Object.keys( taxonomy.origins ) ).toEqual( expect.arrayContaining( [ 'manual', 'automatic', 'flow', 'queue', 'external', 'health', 'system', 'cli', ] ) );<br>expect( Object.keys( taxonomy.stages ) ).toEqual( expect.arrayContaining( [ 'plan', 'policy', 'gate', 'execute', 'finalize', 'observe', ] ) );<br>expect( taxonomy.log_type_prefix.system ).toBeTruthy();<br>expect( taxonomy.action_labels.pass ).toBeTruthy(); |
| `maps every reason to a declared state and a declared action` | maps every reason to a declared state and a declared action | expect( states ).toContain( reason.state );<br>expect( actions ).toContain( reason.action );<br>expect( Object.keys( taxonomy.status_labels ) ).toContain( reason.status );<br>expect( typeof reason.blocking ).toBe( 'boolean' ); |
| `resolves every alias to a canonical reason` | resolves every alias to a canonical reason | expect( taxonomy.reasons[ canonical ] ).toBeDefined(); |
| `keeps precedence groups limited to declared reasons` | keeps precedence groups limited to declared reasons | expect( group.length ).toBeGreaterThan( 0 );<br>expect( taxonomy.reasons[ code ] ).toBeDefined(); |
| `carries the legacy label` | carries the legacy label | expect( taxonomy.legacy_label ).toBe( 'Legacy — reason not recorded' ); |

#### `tests/js/logTaxonomyLabels.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `matches the shared snapshot for states` | matches the shared snapshot for states | expect( Object.keys( STATE_LABELS ) ).toEqual( fixture.state_order );<br>expect( STATE_LABELS[ state ] ).toBe( meta.label );<br>expect( STATE_INTENTS[ state ] ).toBe( meta.intent ); |
| `matches the shared snapshot for reasons` | matches the shared snapshot for reasons | expect( Object.keys( REASON_LABELS ).sort() ).toEqual( Object.keys( fixture.reasons ).sort() );<br>expect( REASON_LABELS[ code ] ).toBe( meta.label ); |
| `matches the shared snapshot for origins, stages, and actions` | matches the shared snapshot for origins, stages, and actions | expect( ORIGIN_LABELS ).toEqual( fixture.origins );<br>expect( STAGE_LABELS ).toEqual( fixture.stages );<br>expect( ACTION_LABELS ).toEqual( fixture.action_labels );<br>expect( LOG_TYPE_PREFIX.system ).toBe( fixture.log_type_prefix.system ); |
| `renders the legacy label when no reason was recorded` | renders the legacy label when no reason was recorded | expect( getReasonLabel( '' ) ).toBe( fixture.legacy_label );<br>expect( getReasonLabel( null ) ).toBe( fixture.legacy_label );<br>expect( getReasonLabel( '', 'Server label' ) ).toBe( 'Server label' ); |
| `falls back to the server label and then the raw code` | falls back to the server label and then the raw code | expect( getReasonLabel( 'shield_incompatible_php' ) ).toBe( 'Requires a newer PHP version' );<br>expect( getReasonLabel( 'custom_reason', 'Custom' ) ).toBe( 'Custom' );<br>expect( getReasonLabel( 'custom_reason' ) ).toBe( 'custom_reason' );<br>expect( getStateLabel( 'uncertain' ) ).toBe( 'Needs review' ); |
| `formats the next-eligible time from the server or UTC` | formats the next-eligible time from the server or UTC | expect( getNextEligibleLabel( '2026-11-03 02:00:00', '3 November 2026, 03:00' ) ).toBe( '3 November 2026, 03:00' );<br>expect( getNextEligibleLabel( '', '' ) ).toBe( '—' );<br>expect( getNextEligibleLabel( '2026-11-03 02:00:00' ) ).toMatch( /2026/ ); |

#### `tests/js/nativeUpdateTime.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `maps the legacy core alias to core:wordpress` | maps the legacy core alias to core:wordpress | expect( normalizeKey( 'core:core' ) ).toBe( 'core:wordpress' );<br>expect( normalizeKey( 'core:wordpress' ) ).toBe( 'core:wordpress' ); |
| `returns the sentence for a known item and empty for an unknown one` | returns the sentence for a known item and empty for an unknown one | expect( itemText( DATA, 'core:core' ) ).toBe( DATA.items[ 'core:wordpress' ].text );<br>expect( itemText( DATA, 'plugin:nope/nope.php' ) ).toBe( '' ); |
| `replaces only the plugin row time text` | replaces only the plugin row time text | expect( changed ).toBe( 1 );<br>expect( cell.textContent ).toBe( DATA.items[ 'plugin:example/example.php' ].text );<br>expect( cell.getAttribute( 'data-updatronix-native-text' ) ).toBe( NATIVE );<br>expect( cell.getAttribute( 'data-updatronix-enhanced' ) ).toBe( '1' ); |
| `keeps the hidden class` | keeps the hidden class | expect( cell.className ).toBe( 'auto-update-time hidden' );<br>expect( cell.textContent ).toBe( DATA.items[ 'plugin:example/example.php' ].text ); |
| `replaces the network theme row text` | replaces the network theme row text | expect( document.querySelector( '.auto-update-time' ).textContent ).toBe( DATA.items[ 'theme:example-theme' ].text ); |
| `leaves a row with no identity untouched` | leaves a row with no identity untouched | expect( changed ).toBe( 0 );<br>expect( document.querySelector( '.auto-update-time' ).textContent ).toBe( NATIVE ); |
| `leaves text that is not the native sentence untouched` | leaves text that is not the native sentence untouched | expect( changed ).toBe( 0 );<br>expect( document.querySelector( '.auto-update-time' ).textContent ).toBe( 'Managed by another plugin.' ); |
| `is idempotent` | is idempotent | expect( first ).toBe( 1 );<br>expect( second ).toBe( 0 );<br>expect( document.querySelector( '.auto-update-time' ).textContent ).toBe( DATA.items[ 'plugin:example/example.php' ].text ); |
| `does not reinsert into an emptied cell` | does not reinsert into an emptied cell | expect( changed ).toBe( 0 );<br>expect( document.querySelector( '.auto-update-time' ).textContent ).toBe( '' ); |
| `replaces the core paragraph next to the status paragraph` | replaces the core paragraph next to the status paragraph | expect( changed ).toBe( 1 );<br>expect( document.querySelector( 'p:not(.auto-update-status)' ).textContent ).toBe( DATA.items[ 'core:wordpress' ].text ); |
| `leaves a paragraph that is not the status sibling` | leaves a paragraph that is not the status sibling | expect( changed ).toBe( 0 );<br>expect( document.querySelector( 'p' ).textContent ).toBe( NATIVE ); |
| `leaves another plugin notice untouched` | leaves another plugin notice untouched | expect( changed ).toBe( 0 );<br>expect( document.querySelector( 'p' ).textContent ).toContain( NATIVE ); |
| `replaces the plugin row part after the pipe` | replaces the plugin row part after the pipe | expect( changed ).toBe( 1 );<br>expect( document.querySelector( 'td.plugin-title p' ).textContent ).toBe( `You have version 1.0.0 installed. Update to 1.1.0. \| ${ DATA.items[ 'plugin:example/example.php' ].text }` ); |
| `replaces the theme row part after the pipe` | replaces the theme row part after the pipe | expect( changed ).toBe( 1 );<br>expect( document.querySelector( 'td.plugin-update p' ).textContent ).toContain( DATA.items[ 'theme:example-theme' ].text ); |
| `leaves a row without a checkbox untouched` | leaves a row without a checkbox untouched | expect( changed ).toBe( 0 );<br>expect( document.querySelector( 'td.plugin-update p' ).textContent ).toContain( NATIVE ); |
| `replaces the fallback overlay text when the slug is known` | replaces the fallback overlay text when the slug is known | expect( changed ).toBe( 1 );<br>expect( document.querySelector( '#theme-overlay .auto-update-time' ) .textContent ).toBe( DATA.items[ 'theme:example-theme' ].text ); |
| `leaves the overlay alone without a slug` | leaves the overlay alone without a slug | expect( enhanceNativeTime( document, DATA ) ).toBe( 0 ); |

#### `tests/js/routes.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `defines the eleven destinations once, with unique slugs` | defines the eleven destinations once, with unique slugs | expect( DESTINATIONS ).toHaveLength( 11 );<br>expect( new Set( slugs ).size ).toBe( 11 ); |
| `puts every destination in a known group with a label and icon` | puts every destination in a known group with a label and icon | expect( groupIds.has( destination.group ) ).toBe( true );<br>expect( destination.label ).toBeTruthy();<br>expect( destination.description ).toBeTruthy();<br>expect( destination.icon ).toBeTruthy(); |
| `keeps the four groups in the rail order` | keeps the four groups in the rail order | expect( GROUPS.map( ( group ) => group.id ) ).toEqual( [ 'updates', 'automations', 'system', 'help', ] ); |
| `appends an unknown PHP tab to the System group` | appends an unknown PHP tab to the System group | expect( destinations ).toHaveLength( 12 );<br>expect( destinations[ 11 ] ).toMatchObject( { slug: 'third-party', label: 'Third party', group: 'system', icon: 'settings', edition: 'free', } ); |
| `does not duplicate a built-in destination` | does not duplicate a built-in destination | expect( destinations ).toHaveLength( 11 ); |
| `marks a Pro tab and keeps its panel key` | marks a Pro tab and keeps its panel key | expect( extra ).toMatchObject( { group: 'automations', icon: 'shield', panel: 'shield', edition: 'pro', } ); |
| `finds a destination by slug` | finds a destination by slug | expect( getDestination( 'health', DESTINATIONS )?.label ).toBe( 'Health' ); |
| `returns undefined for an unknown slug` | returns undefined for an unknown slug | expect( getDestination( 'missing', DESTINATIONS ) ).toBeUndefined(); |
| `uses a valid requested slug` | uses a valid requested slug | expect( getInitialSlug( DESTINATIONS, 'logs' ) ).toBe( 'settings' ); |
| `falls back when the requested slug is unknown` | falls back when the requested slug is unknown | expect( getInitialSlug( DESTINATIONS, 'schedule' ) ).toBe( 'schedule' ); |
| `falls back to the first destination when the fallback is unknown` | falls back to the first destination when the fallback is unknown | expect( getInitialSlug( DESTINATIONS, 'missing' ) ).toBe( 'logs' ); |
| `sets the page and tab parameters and preserves the others` | sets the page and tab parameters and preserves the others | expect( url.searchParams.get( 'page' ) ).toBe( 'updatronix' );<br>expect( url.searchParams.get( 'tab' ) ).toBe( 'support' );<br>expect( url.searchParams.get( 'foo' ) ).toBe( 'bar' ); |
| `keeps only the Updatronix 3000 presentation on a Free-only install` | keeps only the Updatronix 3000 presentation on a Free-only install | expect( filterDestinationsForUser( sample, { isPro: false, canManagePro: false, licenseActive: false, } ).map( ( item ) => item.slug ) ).toEqual( [ 'logs', 'updatronix-3000' ] ); |
| `hides all Pro destinations when Pro is active without capability` | hides all Pro destinations when Pro is active without capability | expect( filterDestinationsForUser( sample, { isPro: true, canManagePro: false, licenseActive: true, } ).map( ( item ) => item.slug ) ).toEqual( [ 'logs' ] ); |
| `keeps only the activation screen when the license is inactive` | keeps only the activation screen when the license is inactive | expect( filterDestinationsForUser( sample, { isPro: true, canManagePro: true, licenseActive: false, } ).map( ( item ) => item.slug ) ).toEqual( [ 'logs', 'updatronix-3000' ] ); |
| `keeps every Pro destination when Pro, capability, and license are active` | keeps every Pro destination when Pro, capability, and license are active | expect( filterDestinationsForUser( sample, { isPro: true, canManagePro: true, licenseActive: true, } ).map( ( item ) => item.slug ) ).toEqual( [ 'logs', 'updatronix-3000', 'update-shield', 'update-flow', 'white-label', … |

#### `tests/js/scheduleDraft.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `returns the schedule subtree` | returns the schedule subtree | expect( getSchedule( { schedule: SAVED } ) ).toBe( SAVED ); |
| `falls back when the payload is missing` | falls back when the payload is missing | expect( getSchedule( null ).update_check.time ).toBe( '03:00' ); |
| `returns the enabled hold` | returns the enabled hold | expect( getHoldDays( SAVED ) ).toBe( 3 ); |
| `returns zero when the hold is disabled` | returns zero when the hold is disabled | expect( getHoldDays( { ...SAVED, delay_updates: { enabled: false, delay_value: 3 }, } ) ).toBe( 0 ); |
| `is clean for the saved schedule` | is clean for the saved schedule | expect( isScheduleDirty( { ...SAVED }, SAVED ) ).toBe( false ); |
| `becomes clean again when the recurrence is reverted` | becomes clean again when the recurrence is reverted | expect( isScheduleDirty( dirty, SAVED ) ).toBe( true );<br>expect( isScheduleDirty( { ...dirty, update_check: { ...dirty.update_check, recurrence: 'daily', }, }, SAVED ) ).toBe( false ); |
| `detects a hold change` | detects a hold change | expect( isScheduleDirty( { ...SAVED, delay_updates: { enabled: true, delay_value: 5 }, }, SAVED ) ).toBe( true ); |
| `offers every hour and every minute` | offers every hour and every minute | expect( HOURS ).toHaveLength( 24 );<br>expect( MINUTES ).toHaveLength( 60 );<br>expect( HOURS[ 0 ] ).toBe( '00' );<br>expect( HOURS[ 23 ] ).toBe( '23' ); |
| `splits a stored HH:MM into the two picker values` | splits a stored HH:MM into the two picker values | expect( splitTime( '02:17' ) ).toEqual( { hour: '02', minute: '17', } ); |
| `joins the picker values back into HH:MM` | joins the picker values back into HH:MM | expect( joinTime( '02', '17' ) ).toBe( '02:17' );<br>expect( joinTime( '23', '59' ) ).toBe( '23:59' );<br>expect( joinTime( '0', '5' ) ).toBe( '00:05' ); |
| `rejects an invalid join instead of storing a partial value` | rejects an invalid join instead of storing a partial value | expect( joinTime( '24', '00' ) ).toBe( '03:00' );<br>expect( joinTime( '12', '60' ) ).toBe( '03:00' );<br>expect( joinTime( '', '' ) ).toBe( '03:00' ); |
| `validates only the canonical HH:MM shape` | validates only the canonical HH:MM shape | expect( isTimeValue( '00:00' ) ).toBe( true );<br>expect( isTimeValue( '23:59' ) ).toBe( true );<br>expect( isTimeValue( '9:05' ) ).toBe( false );<br>expect( isTimeValue( '24:00' ) ).toBe( false ); |
| `falls back to 03:00 for an empty stored value` | falls back to 03:00 for an empty stored value | expect( normalizeTime( '' ) ).toBe( '03:00' );<br>expect( normalizeTime( 'custom' ) ).toBe( '03:00' );<br>expect( normalizeTime( '02:17' ) ).toBe( '02:17' ); |
| `marks a time change as dirty` | marks a time change as dirty | expect( isScheduleDirty( { ...SAVED, update_check: { ...SAVED.update_check, time: '02:17' }, }, SAVED ) ).toBe( true ); |
| `treats an empty stored time like the 03:00 fallback` | treats an empty stored time like the 03:00 fallback | expect( isScheduleDirty( { ...SAVED, update_check: { ...SAVED.update_check, time: '' }, }, SAVED ) ).toBe( false ); |
| `disables the picker only for the frequencies that ignore the time` | disables the picker only for the frequencies that ignore the time | expect( recurrenceUsesPreferredTime( 'daily' ) ).toBe( true );<br>expect( recurrenceUsesPreferredTime( 'twicedaily' ) ).toBe( true );<br>expect( recurrenceUsesPreferredTime( 'weekly' ) ).toBe( true );<br>expect( recurrenceUsesPreferredTime( 'hourly' ) ).toBe( false ); |
| `explains each recurrence in one sentence` | explains each recurrence in one sentence | expect( recurrenceDescription( 'daily' ) ).toContain( 'every day' );<br>expect( recurrenceDescription( 'twicedaily' ) ).toContain( '12 hours later' );<br>expect( recurrenceDescription( 'hourly' ) ).toContain( 'ignores the preferred time' );<br>expect( recurrenceDescription( 'weekly' ) ).toContain( 'seven days' ); |
| `keeps the stored time when the frequency switches to hourly and back` | keeps the stored time when the frequency switches to hourly and back | expect( splitTime( hourly.update_check.time ) ).toEqual( { hour: '02', minute: '17', } );<br>expect( isScheduleDirty( back, saved ) ).toBe( true );<br>expect( back.update_check.time ).toBe( '02:17' ); |
| `states the hold rule and the earliest install for a positive hold` | states the hold rule and the earliest install for a positive hold | expect( holdRuleCopy( 1 ) ).toContain( 'held for 1 day ' ); |
| `says no hold applies when it is off` | says no hold applies when it is off | expect( holdRuleCopy( 0 ) ).toBe( 'No hold is applied. New releases install at the next automatic check.' ); |
| `never presents the check as the install time` | never presents the check as the install time | expect( holdRuleCopy( 3 ) ).not.toMatch( /install time:/i ); |
| `explains that a manual check does not start the hold` | explains that a manual check does not start the hold | expect( copy ).toContain( 'does not start the hold' );<br>expect( copy ).toContain( 'next automatic pass' ); |
| `has one core row without a security exemption` | has one core row without a security exemption | expect( core ).toHaveLength( 1 );<br>expect( core[ 0 ].type ).toBe( 'Core releases' );<br>expect( core[ 0 ].withHold ).toBe( 'Deferred by the selected number of days' );<br>expect( all ).not.toContain( 'Installed immediately' ); |
| `states the hold applies to every core automatic update` | states the hold applies to every core automatic update | expect( note ).toContain( 'every core automatic update' );<br>expect( note ).not.toContain( 'skip' );<br>expect( note ).toContain( 'security-release flag' ); |
| `accepts the canonical 0..365 bounds` | accepts the canonical 0..365 bounds | expect( validateHoldDays( 0 ) ).toBe( 0 );<br>expect( validateHoldDays( 1 ) ).toBe( 1 );<br>expect( validateHoldDays( 30 ) ).toBe( 30 );<br>expect( validateHoldDays( 31 ) ).toBe( 31 ); |
| `rejects out-of-range values` | rejects out-of-range values | expect( validateHoldDays( -1 ) ).toBeNull();<br>expect( validateHoldDays( 366 ) ).toBeNull(); |
| `rejects non-integers and empty input` | rejects non-integers and empty input | expect( validateHoldDays( 1.5 ) ).toBeNull();<br>expect( validateHoldDays( '' ) ).toBeNull();<br>expect( validateHoldDays( 'abc' ) ).toBeNull(); |
| `pins the validation copy` | pins the validation copy | expect( HOLD_VALIDATION_MESSAGE ).toBe( 'Enter a whole number from 0 to 365.' ); |
| `exposes the canonical maximum` | exposes the canonical maximum | expect( MAX_HOLD_DAYS ).toBe( 365 ); |

#### `tests/js/settingsDraft.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `is clean when the draft matches the saved snapshot` | is clean when the draft matches the saved snapshot | expect( isSettingsDirty( { ...SAVED }, SAVED ) ).toBe( false ); |
| `becomes clean again when the uninstall opt-in is reverted` | becomes clean again when the uninstall opt-in is reverted | expect( isSettingsDirty( dirty, SAVED ) ).toBe( true );<br>expect( isSettingsDirty( { ...dirty, delete_data_on_uninstall: false }, SAVED ) ).toBe( false ); |
| `ignores keys the Settings surface does not edit` | ignores keys the Settings surface does not edit | expect( isSettingsDirty( { ...SAVED, auto_update_translations: false, dismissed_constants: [], }, SAVED ) ).toBe( false ); |
| `treats a changed recipient as dirty` | treats a changed recipient as dirty | expect( isSettingsDirty( { ...SAVED, notify_emails: 'other@example.com' }, SAVED ) ).toBe( true ); |
| `treats a changed notification type as dirty regardless of order` | treats a changed notification type as dirty regardless of order | expect( isSettingsDirty( { ...SAVED, notifyOn: [ 'plugin_theme', 'core' ] }, SAVED ) ).toBe( false );<br>expect( isSettingsDirty( { ...SAVED, notifyOn: [ 'core' ] }, SAVED ) ).toBe( true ); |
| `normalizes an empty retention value to the saved default` | normalizes an empty retention value to the saved default | expect( isSettingsDirty( { ...SAVED, retention_days: 0 }, SAVED ) ).toBe( false ); |
| `is stable for the same fields in a different object order` | is stable for the same fields in a different object order | expect( editableSignature( reordered ) ).toBe( editableSignature( SAVED ) ); |
| `allows navigation when the draft is clean without asking` | allows navigation when the draft is clean without asking | expect( canLeaveDraft( { dirty: false, confirm } ) ).toBe( true );<br>expect( confirm ).not.toHaveBeenCalled(); |
| `allows navigation when the user confirms discarding` | allows navigation when the user confirms discarding | expect( canLeaveDraft( { dirty: true, confirm: () => true } ) ).toBe( true ); |
| `blocks navigation when the user cancels` | blocks navigation when the user cancels | expect( canLeaveDraft( { dirty: true, confirm: () => false } ) ).toBe( false ); |
| `uses the shared discard message` | uses the shared discard message | expect( confirm ).toHaveBeenCalledWith( DISCARD_CHANGES_MESSAGE ); |

#### `tests/js/staticLinks.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `are three unique HTTPS URLs on the documentation host` | are three unique HTTPS URLs on the documentation host | expect( urls ).toHaveLength( 3 );<br>expect( new Set( urls ).size ).toBe( 3 );<br>expect( parsed.protocol ).toBe( 'https:' );<br>expect( parsed.hostname ).toBe( 'holdmywp.com' ); |
| `names the plugin, Pro, and developer guides` | names the plugin, Pro, and developer guides | expect( DOCUMENTATION_URLS.plugin ).toContain( 'updatronix-documentation' );<br>expect( DOCUMENTATION_URLS.pro ).toContain( 'updatronix-3000' );<br>expect( DOCUMENTATION_URLS.developer ).toContain( 'developer-reference' ); |
| `points at the Free plugin forum over HTTPS` | points at the Free plugin forum over HTTPS | expect( parsed.protocol ).toBe( 'https:' );<br>expect( parsed.hostname ).toBe( 'wordpress.org' );<br>expect( parsed.pathname ).toBe( '/support/plugin/updatronix/' ); |

### E2E (29 cases)

#### `tests/e2e/admin.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `loads for an administrator and renders its markup` | loads for an administrator and renders its markup | expect( page.locator( '#updatronix-settings' ) ).toBeVisible(); |
| `enqueues the plugin bundle` | enqueues the plugin bundle | expect( scripts.length ).toBeGreaterThan( 0 ); |

#### `tests/e2e/free-auto-updates.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `shows the refresh error and Retry when the API fails` | shows the refresh error and Retry when the API fails | expect( page.getByText( /Auto-updates could not be (loaded\|refreshed)/i ) ).toBeVisible();<br>expect( page.getByRole( 'button', { name: 'Retry' } ) ).toBeVisible(); |
| `surfaces a partial failure` | surfaces a partial failure | expect( page .getByText( /could not be saved\|Some auto-update changes/i ) .first() ).toBeVisible(); |
| `sends one mutation per staged change in save-all order` | sends one mutation per staged change in save-all order | expect( page.getByText( 'Auto-update settings saved.' ).first() ).toBeVisible();<br>expect( posts.length ).toBeGreaterThanOrEqual( 2 );<br>expect( new Set( posts ).size ).toBe( posts.length ); |
| `saves a staged toggle` | saves a staged toggle | expect( page.getByRole( 'heading', { name: 'Core update mode' } ) ).toBeVisible();<br>expect( page.getByText( 'Auto-update settings saved.' ).first() ).toBeVisible(); |

#### `tests/e2e/free-docs-support.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `renders the guides with the Pro badge` | renders the guides with the Pro badge | expect( page.getByRole( 'heading', { name: 'Updatronix documentation' } ) ).toBeVisible();<br>expect( page.getByText( 'Pro', { exact: true } ).first() ).toBeVisible();<br>expect( page.getByRole( 'link', { name: /Updatronix 3000 documentation/ } ) ).toBeVisible(); |
| `links to the WordPress.org support forum` | links to the WordPress.org support forum | expect( page .locator( '#updatronix-main' ) .getByText( 'WordPress.org', { exact: true } ) ).toBeVisible();<br>expect( link ).toBeVisible(); |

#### `tests/e2e/free-health.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `runs a check and opens diagnostics` | runs a check and opens diagnostics | expect( page.getByRole( 'button', { name: 'Run check' } ) ).toBeVisible();<br>expect( page.getByText( /Health check complete/i ).first() ).toBeVisible();<br>expect( page.getByText( /Diagnostics/i ).first() ).toBeVisible();<br>expect( page.getByText( /does not include filesystem paths/i ) ).toBeVisible(); |
| `recovers from a load failure with Try again` | recovers from a load failure with Try again | expect( page.getByText( 'Health checks failed' ) ).toBeVisible();<br>expect( page.getByText( /Checks/i ).first() ).toBeVisible(); |

#### `tests/e2e/free-logs.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `shows the seeded entry and its details` | shows the seeded entry and its details | expect( page.locator( '#updatronix-settings' ) ).toBeVisible();<br>expect( page.getByText( 'E2E plugin' ) ).toBeVisible();<br>expect( page.getByRole( 'button', { name: 'Export logs' } ) ).toBeVisible(); |
| `opens the deep link in the details view` | opens the deep link in the details view | expect( page.getByText( 'E2E seeded log' ) ).toBeVisible(); |
| `filters the feed by search` | filters the feed by search | expect( page.getByText( 'E2E plugin' ) ).toBeVisible(); |
| `offers only the Free txt format` | offers only the Free txt format | expect( page.getByText( 'Export update logs' ) ).toBeVisible();<br>expect( page.getByText( /CSV\|JSON\|XML/i ) ).toHaveCount( 0 ); |
| `shows the decision state and reason in the feed` | shows the decision state and reason in the feed | expect( page.getByText( 'E2E blocked plugin' ) ).toBeVisible();<br>expect( page.getByText( 'Requires a newer PHP version' ).first() ).toBeVisible();<br>expect( page.getByText( 'Blocked' ).first() ).toBeVisible(); |
| `shows the decision section in the details` | shows the decision section in the details | expect( details.getByText( 'Decision' ) ).toBeVisible();<br>expect( details.getByText( 'Requires a newer PHP version' ) ).toBeVisible();<br>expect( details.getByText( 'PHP 8.3 required (installed 8.1)' ) ).toBeVisible();<br>expect( details.getByText( 'Policy' ).first() ).toBeVisible(); |
| `filters the feed by a pass deep link` | filters the feed by a pass deep link | expect( page.getByText( 'E2E blocked plugin' ) ).toBeVisible();<br>expect( page.locator( '.updatronix-log-entry-name' ).filter( { hasText: /^E2E plugin$/, } ) ).toBeHidden(); |
| `offers the decision columns in the export` | offers the decision columns in the export | expect( page.getByText( 'Export update logs' ) ).toBeVisible();<br>expect( page.getByText( 'Reason', { exact: true } ) ).toBeVisible();<br>expect( page.getByText( 'State', { exact: true } ) ).toBeVisible(); |
| `confirms deletion of a log entry` | confirms deletion of a log entry | expect( page.getByText( 'Delete log entry' ) ).toBeVisible();<br>expect( page.getByText( 'Delete log entry' ) ).toBeHidden();<br>expect( page .locator( '.updatronix-log-entry-name' ) .filter( { hasText: String( firstRowName \|\| '' ) } ) ).toBeHidden(); |

#### `tests/e2e/free-schedule-native.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `plugin row shows the hold state, not the next check` | plugin row shows the hold state, not the next check | expect( row ).toContainText( 'Earliest automatic install:' );<br>expect( row ).not.toContainText( 'Automatic update scheduled in' );<br>expect( page.locator( `tr[data-plugin="${ fixtures.plugin }"] .toggle-auto-update` ) ).toBeVisible(); |
| `theme card carries its own state` | theme card carries its own state | expect( text ).toContain( 'Earliest automatic install:' ); |
| `update-core shows the next-pass summary` | update-core shows the next-pass summary | expect( summary ).toContainText( 'Next automatic pass:' );<br>expect( summary ).toContainText( 'eligible' );<br>expect( summary ).toContainText( 'delayed' );<br>expect( summary.getByRole( 'link', { name: 'Schedule' } ) ).toBeVisible(); |
| `WP-Cron hint is one line and the banner is gone` | WP-Cron hint is one line and the banner is gone | expect( page.locator( '.updatronix-wp-cron-hint' ) ).toBeVisible();<br>expect( page.locator( '.updatronix-delay-context-notice' ) ).toHaveCount( 0 ); |

#### `tests/e2e/free-schedule.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `validates the hold period and saves a valid value` | validates the hold period and saves a valid value | expect( page.getByRole( 'heading', { name: 'Update checks' } ) ).toBeVisible();<br>expect( page.getByText( /Times use/i ) ).toBeVisible();<br>expect( page.getByText( 'Enter a whole number from 0 to 365.' ) ).toBeVisible();<br>expect( hold ).toHaveValue( '2' ); |
| `picks an exact hour and minute` | picks an exact hour and minute | expect( hour ).toBeEnabled();<br>expect( minute ).toBeEnabled();<br>expect( page.getByText( /saved/i ).first() ).toBeVisible();<br>expect( page.getByRole( 'combobox', { name: 'Hour' } ) ).toContainText( '02' ); |
| `disables the picker for hourly while keeping the saved time` | disables the picker for hourly while keeping the saved time | expect( page.getByText( 'This frequency ignores the preferred time' ) ).toBeVisible(); |
| `shows the WP-Cron disabled notice` | shows the WP-Cron disabled notice | expect( page.getByText( 'WP-Cron is disabled' ) ).toBeVisible(); |

#### `tests/e2e/free-settings-dirty.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `warns before leaving an unsaved draft` | warns before leaving an unsaved draft | expect( page.getByRole( 'region', { name: 'Settings' } ) ).toBeVisible();<br>expect( page.getByText( 'Logging', { exact: true } ) ).toBeVisible();<br>expect( page ).toHaveURL( /tab=settings/ ); |

#### `tests/e2e/free-settings.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `saves retention and the uninstall opt-in, and announces a bad recipient` | saves retention and the uninstall opt-in, and announces a bad recipient | expect( page.locator( '#updatronix-settings' ) ).toBeVisible();<br>expect( page.getByText( 'Logging', { exact: true } ) ).toBeVisible();<br>expect( page.getByText( 'Uninstalling permanently deletes all Updatronix data. This cannot be undone.' ) ).toBeVisible();<br>expect( page.getByRole( 'region', { name: 'Settings' } ) ).toBeVisible(); |

## PRO

### Unit (470 cases)

#### `tests/Unit/DecisionLogTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_transitions_map_to_canonical_reasons` | test transitions map to canonical reasons | self::assertSame( $reason, Updatronix_Decision_Log::reason_for_transition( $job, $transition ), $transition );<br>self::assertSame( 'already_installed', Updatronix_Decision_Log::reason_for_transition( $this->job( array( 'result_code' => 'already_installed' ) ), 'succeeded' ) ); |
| `test_policy_codes_resolve_through_the_alias_map` | test policy codes resolve through the alias map | self::assertSame( $expected, Updatronix_Decision_Log::reason_code_for_result( (string) $code ), (string) $code );<br>self::assertSame( 'native_vcs', Updatronix_Decision_Log::reason_code_for_result( $result ) ); |
| `test_identity_helpers` | test identity helpers | self::assertSame( '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e', Updatronix_Decision_Log::pass_id( $job ) );<br>self::assertSame( 2, Updatronix_Decision_Log::attempt( $job ) );<br>self::assertSame( 0, Updatronix_Decision_Log::network( $job ) );<br>self::assertNull( Updatronix_Decision_Log::next_eligible_at( $this->job( array( 'available_at' => '' ) ) ) ); |

#### `tests/Unit/DevModeTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_is_dev_mode_false_when_constant_not_defined` | test is dev mode false when constant not defined | $this->assertFalse( $method->invoke( null ) ); |
| `test_is_dev_mode_false_when_constant_falsy` | test is dev mode false when constant falsy | $this->assertFalse( $method->invoke( null ) ); |
| `test_is_dev_mode_true_for_localhost` | test is dev mode true for localhost | $this->assertTrue( $method->invoke( null ) );<br>$this->assertTrue( $method->invoke( null ), 'localhost:8080 should be detected as dev mode' );<br>$this->assertTrue( $method->invoke( null ), 'localhost:8080/subdir should be detected as dev mode' ); |
| `test_is_dev_mode_true_for_ipv6_loopback` | test is dev mode true for ipv6 loopback | $this->assertTrue( $method->invoke( null ), '::1 should be detected as dev mode' );<br>$this->assertTrue( $method->invoke( null ), '[::1] should be detected as dev mode' ); |
| `test_is_dev_mode_true_for_local_tlds` | test is dev mode true for local tlds | $this->assertTrue( $method->invoke( null ), "{$domain} should be detected as dev mode" ); |
| `test_is_dev_mode_false_for_production_domains` | test is dev mode false for production domains | $this->assertFalse( $method->invoke( null ), "{$domain} should NOT be detected as dev mode" ); |
| `test_is_dev_mode_false_for_private_ips` | test is dev mode false for private ips | $this->assertFalse( $method->invoke( null ), "{$ip} should NOT be detected as dev mode" ); |
| `test_is_license_active_true_when_dev_mode_active` | test is license active true when dev mode active | $this->assertTrue( Updatronix_Pro_License_Storage::is_license_active() ); |
| `test_get_license_status_active_when_dev_mode_active` | test get license status active when dev mode active | $this->assertSame( 'active', Updatronix_Pro_License_Storage::get_license_status() ); |
| `test_is_license_active_false_when_no_dev_mode` | test is license active false when no dev mode | $this->assertFalse( Updatronix_Pro_License_Storage::is_license_active() ); |
| `test_get_license_status_inactive_when_no_dev_mode` | test get license status inactive when no dev mode | $this->assertSame( 'inactive', Updatronix_Pro_License_Storage::get_license_status() ); |
| `test_license_decryption_notice_suppressed_when_dev_mode_active` | test license decryption notice suppressed when dev mode active | **NONE** |
| `test_license_decryption_notice_shown_when_no_dev_mode` | test license decryption notice shown when no dev mode | **NONE** |

#### `tests/Unit/FlowGraphTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_every_edge_is_kept` | test every edge is kept | self::assertSame( array( 'plugin:b/b.php' ), $edges['plugin:a/a.php'] );<br>self::assertSame( array( 'plugin:b/b.php' ), $edges['plugin:c/c.php'] );<br>self::assertSame( array( 'plugin:a/a.php', 'plugin:c/c.php' ), Updatronix_Flow_Graph::predecessors( $edges )['plugin:b/b.php'] ); |
| `test_duplicate_edge_is_deduped` | test duplicate edge is deduped | self::assertSame( array( 'plugin:b/b.php' ), $edges['plugin:a/a.php'] ); |
| `test_descendants_are_transitive` | test descendants are transitive | self::assertSame( array( 'plugin:b/b.php', 'plugin:c/c.php', 'plugin:d/d.php' ), Updatronix_Flow_Graph::descendants( 'plugin:a/a.php', $edges ) );<br>self::assertSame( array(), Updatronix_Flow_Graph::descendants( 'plugin:d/d.php', $edges ) ); |
| `test_snapshot_records_every_occurrence` | test snapshot records every occurrence | self::assertSame( 'Payments', $snapshot['chains']['a']['name'] );<br>self::assertSame( array( 'plugin:a/a.php', 'plugin:b/b.php' ), $snapshot['chains']['a']['steps'] );<br>self::assertCount( 2, $snapshot['nodes']['plugin:b/b.php'] );<br>self::assertSame( 'b', $snapshot['nodes']['plugin:b/b.php'][1]['chain_id'] ); |
| `test_compare_reports_deleted_disabled_and_edited` | test compare reports deleted disabled and edited | self::assertSame( array( 'c' ), $diff['deleted'] );<br>self::assertSame( array(), $diff['disabled'] );<br>self::assertSame( array( 'b' ), $diff['edited'] ); |
| `test_compare_reports_a_disabled_chain` | test compare reports a disabled chain | self::assertSame( array( 'a' ), Updatronix_Flow_Graph::compare( $snapshot, $current )['disabled'] ); |
| `test_nodes_for_deleted_chains_keep_a_shared_node` | test nodes for deleted chains keep a shared node | self::assertSame( array( 'plugin:a/a.php' ), Updatronix_Flow_Graph::nodes_for_deleted_chains( $snapshot, array( 'gone' ) ) ); |
| `test_chain_nodes` | test chain nodes | self::assertSame( array( 'plugin:a/a.php', 'plugin:b/b.php' ), Updatronix_Flow_Graph::chain_nodes( $snapshot, 'a' ) ); |
| `test_core_key_is_canonical` | test core key is canonical | self::assertSame( 'core:wordpress', Updatronix_Flow_Graph::key( 'core:core' ) );<br>self::assertSame( 'core:wordpress', Updatronix_Flow_Graph::key( 'core:wordpress' ) ); |

#### `tests/Unit/FlowReadinessTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_pending_offer_is_eligible` | test pending offer is eligible | self::assertSame( 'offer_eligible', $result['state'] );<br>self::assertSame( '', $result['reason_code'] );<br>self::assertSame( 'offer', $result['evidence'] );<br>self::assertSame( '2.0.0', $result['target'] ); |
| `test_pending_offer_at_installed_is_already_latest` | test pending offer at installed is already latest | self::assertSame( 'already_latest', $result['state'] );<br>self::assertSame( 'already_installed', $result['reason_code'] ); |
| `test_fresh_checked_evidence_is_already_latest` | test fresh checked evidence is already latest | self::assertSame( 'already_latest', $result['state'] );<br>self::assertSame( 'fresh_checked', $result['reason_code'] );<br>self::assertSame( 'checked', $result['evidence'] ); |
| `test_stale_checked_evidence_is_unknown` | test stale checked evidence is unknown | self::assertSame( 'unknown_offer', $result['state'] );<br>self::assertSame( 'offer_stale', $result['reason_code'] ); |
| `test_absent_evidence_is_unknown` | test absent evidence is unknown | self::assertSame( 'unknown_offer', $result['state'] );<br>self::assertSame( 'offer_absent', $result['reason_code'] ); |
| `test_premium_item_absent_from_checked_is_unknown` | test premium item absent from checked is unknown | self::assertSame( 'unknown_offer', $result['state'] );<br>self::assertSame( 'offer_unreadable', $result['reason_code'] ); |
| `test_core_fresh_checked_evidence` | test core fresh checked evidence | self::assertSame( 'already_latest', $result['state'] );<br>self::assertSame( 'core', $result['type'] );<br>self::assertSame( 'wordpress', $result['id'] ); |
| `test_hold_is_offer_held` | test hold is offer held | self::assertSame( 'offer_held', $result['state'] );<br>self::assertSame( 'schedule_hold', $result['reason_code'] );<br>self::assertSame( $until, $result['eligible_at'] ); |
| `test_expired_hold_is_eligible` | test expired hold is eligible | self::assertSame( 'offer_eligible', $result['state'] ); |
| `test_version_lock_denial_is_locked` | test version lock denial is locked | self::assertSame( 'locked', $result['state'] );<br>self::assertSame( 'version_locked', $result['reason_code'] );<br>self::assertSame( 'composer', $result['manager'] );<br>self::assertSame( 'site', $result['scope'] ); |
| `test_policy_denial_is_offer_blocked` | test policy denial is offer blocked | self::assertSame( 'offer_blocked', $result['state'] );<br>self::assertSame( 'shield_incompatible_wp', $result['reason_code'] ); |
| `test_environment_kill_switches_are_never_ready` | test environment kill switches are never ready | self::assertSame( 'never_ready', $automatic['state'] );<br>self::assertSame( 'automatic_updates_disabled', $automatic['reason_code'] );<br>self::assertSame( 'never_ready', $file_mods['state'] );<br>self::assertSame( 'file_mods_disabled', $file_mods['reason_code'] ); |
| `test_core_forced_off_is_never_ready` | test core forced off is never ready | self::assertSame( 'never_ready', $result['state'] );<br>self::assertSame( 'core_updates_disabled', $result['reason_code'] ); |
| `test_not_opted_in_is_not_managed` | test not opted in is not managed | self::assertSame( 'not_managed', $result['state'] );<br>self::assertSame( 'auto_updates_off', $result['reason_code'] ); |
| `test_missing_item_is_not_installed` | test missing item is not installed | self::assertSame( 'not_installed', $result['state'] );<br>self::assertSame( 'not_installed', $result['reason_code'] ); |
| `test_held_predecessor_is_a_transient_wait` | test held predecessor is a transient wait | self::assertSame( 'waiting_for_predecessor', $result['state'] );<br>self::assertSame( 'flow_waiting_predecessor', $result['reason_code'] );<br>self::assertSame( 'plugin:a/a.php', $result['waiting_for'] );<br>self::assertFalse( $result['blocking'] ); |
| `test_locked_predecessor_is_blocking` | test locked predecessor is blocking | self::assertSame( 'waiting_for_predecessor', $result['state'] );<br>self::assertSame( 'flow_predecessor_locked', $result['reason_code'] );<br>self::assertTrue( $result['blocking'] ); |
| `test_failed_predecessor_is_transient` | test failed predecessor is transient | self::assertSame( 'flow_predecessor_failed', $result['reason_code'] );<br>self::assertFalse( $result['blocking'] ); |
| `test_done_predecessor_does_not_wait` | test done predecessor does not wait | self::assertSame( 'offer_eligible', $result['state'] ); |
| `test_terminal_pass_state_is_passed_through` | test terminal pass state is passed through | self::assertSame( 'failed', $result['state'] );<br>self::assertSame( 'update_failed', $result['reason_code'] );<br>self::assertSame( 'pass', $result['evidence'] ); |
| `test_resolver_has_no_side_effects` | test resolver has no side effects | self::assertSame( $first, $second );<br>self::assertSame( $before, $after ); |
| `test_revalidate_sees_an_installed_target` | test revalidate sees an installed target | self::assertTrue( $result['changed'] );<br>self::assertSame( 'succeeded', $result['state'] );<br>self::assertSame( 'target_installed', $result['reason_code'] ); |
| `test_revalidate_sees_a_withdrawn_offer` | test revalidate sees a withdrawn offer | self::assertSame( 'skipped', $result['state'] );<br>self::assertSame( 'flow_offer_stale', $result['reason_code'] ); |
| `test_revalidate_keeps_an_unattempted_node_when_the_offer_is_absent` | test revalidate keeps an unattempted node when the offer is absent | self::assertFalse( $result['changed'] );<br>self::assertSame( 'offer_eligible', $result['state'] ); |
| `test_revalidate_sees_a_changed_target` | test revalidate sees a changed target | self::assertSame( 'offer_pending', $result['state'] );<br>self::assertSame( '3.0.0', $result['target'] );<br>self::assertSame( 'flow_target_changed', $result['reason_code'] ); |
| `test_revalidate_sees_a_removed_item` | test revalidate sees a removed item | self::assertSame( 'not_installed', $result['state'] ); |
| `test_waiting_transition_is_descendant_only` | test waiting transition is descendant only | self::assertSame( 'waiting_for_predecessor', $waiting['state'] );<br>self::assertSame( 'flow_predecessor_failed', $waiting['reason_code'] );<br>self::assertFalse( $waiting['blocking'] );<br>self::assertFalse( $free['changed'] ); |
| `test_waiting_transition_clears` | test waiting transition clears | self::assertTrue( $result['changed'] );<br>self::assertSame( 'offer_eligible', $result['state'] );<br>self::assertSame( '', $result['waiting_for'] ); |
| `test_hold_transition_starts_and_expires` | test hold transition starts and expires | self::assertTrue( $start['changed'] );<br>self::assertSame( 'offer_held', $start['state'] );<br>self::assertSame( $now + 3600, $start['eligible_at'] );<br>self::assertTrue( $end['changed'] ); |

#### `tests/Unit/LicenseApiTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_activate_request_contract` | test activate request contract | $this->assertSame( 'SLL-1234-5678-ABCD', $this->sent_request()['args']['body']['licence_key'] );<br>$this->assertSame( 'example.com', $this->sent_request()['args']['body']['domain'] ); |
| `test_deactivate_request_contract` | test deactivate request contract | $this->assertSame( 'SLL-1234-5678-ABCD', $this->sent_request()['args']['body']['licence_key'] );<br>$this->assertSame( 'example.com', $this->sent_request()['args']['body']['domain'] ); |
| `test_request_update_request_contract` | test request update request contract | $this->assertSame( '0.5.3', $body['version'] );<br>$this->assertSame( 'json', $body['responseType'] ); |
| `test_request_information_request_contract` | test request information request contract | $this->assertSame( '0.5.3', $body['version'] );<br>$this->assertSame( 'json', $body['responseType'] ); |
| `test_parse_api_response_unwraps_array_wrapper` | test parse api response unwraps array wrapper | $this->assertSame( 'success', $parsed['status'] );<br>$this->assertSame( 's100', $parsed['status_code'] ); |
| `test_parse_api_response_accepts_object_shape` | test parse api response accepts object shape | $this->assertSame( 'success', $parsed['status'] );<br>$this->assertSame( 's100', $parsed['status_code'] ); |
| `test_parse_api_response_invalid_json_returns_e000` | test parse api response invalid json returns e000 | $this->assertSame( 'error', $parsed['status'] );<br>$this->assertSame( 'e000', $parsed['code'] ); |
| `test_parse_api_response_unexpected_structure_returns_e000` | test parse api response unexpected structure returns e000 | $this->assertSame( 'error', $parsed['status'] );<br>$this->assertSame( 'e000', $parsed['code'] ); |
| `test_wp_error_transport_failure_returns_unreachable` | test wp error transport failure returns unreachable | $this->assertSame( 'error', $response['status'] );<br>$this->assertSame( 'e000', $response['code'] );<br>$this->assertSame( 'API unreachable.', $response['message'] ); |
| `test_non_200_http_response_returns_error` | test non 200 http response returns error | $this->assertSame( 'error', $response['status'] );<br>$this->assertSame( 'e000', $response['code'] );<br>$this->assertSame( 'API returned HTTP 503.', $response['message'] ); |
| `test_parse_api_response_accepts_a_response_with_only_status` | test parse api response accepts a response with only status | $this->assertSame( 'success', $parsed['status'] );<br>$this->assertArrayNotHasKey( 'code', $parsed ); |
| `test_parse_api_response_accepts_a_response_with_only_status_code` | test parse api response accepts a response with only status code | $this->assertSame( 's100', $parsed['status_code'] );<br>$this->assertArrayNotHasKey( 'code', $parsed ); |

#### `tests/Unit/LicenseConstantsTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_product_unique_id_matches_production` | test product unique id matches production | $this->assertSame( 'UPD-PRO-001', UPDATRONIX_PRO_PRODUCT_UNIQUE_ID ); |
| `test_license_option_name_matches_production` | test license option name matches production | $this->assertSame( 'updatronix_pro_license', UPDATRONIX_PRO_OPTION_LICENSE ); |
| `test_license_api_url_matches_production` | test license api url matches production | $this->assertSame( 'https://app.holdmywp.com/index.php', UPDATRONIX_PRO_LICENSE_API_URL ); |

#### `tests/Unit/LicenseDecryptionNoticeTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_a_non_array_option_renders_no_notice` | test a non array option renders no notice | $this->assertSame( '', $this->render() ); |

#### `tests/Unit/LicenseRestRouteTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_registers_the_three_license_routes` | test registers the three license routes | $this->assertCount( 3, $updatronix_test_rest_routes ); |
| `test_all_routes_share_the_permission_callback` | test all routes share the permission callback | $this->assertIsCallable( $args['permission_callback'] );<br>$this->assertIsArray( $args['permission_callback'] );<br>$this->assertInstanceOf( Updatronix_Pro_License_REST::class, $args['permission_callback'][0] );<br>$this->assertSame( 'check_permission', $args['permission_callback'][1] ); |
| `test_route_methods` | test route methods | $this->assertSame( 'GET', $this->route( '/license/status' )['methods'] );<br>$this->assertSame( 'POST', $this->route( '/license/activate' )['methods'] );<br>$this->assertSame( 'POST', $this->route( '/license/deactivate' )['methods'] ); |
| `test_activate_route_validates_the_real_key_shape` | test activate route validates the real key shape | $this->assertTrue( $schema['required'] );<br>$this->assertSame( 'string', $schema['type'] );<br>$this->assertSame( 'sanitize_text_field', $schema['sanitize_callback'] );<br>$this->assertIsCallable( $schema['validate_callback'] ); |

#### `tests/Unit/LicenseRestTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_check_permission_denied_without_capability` | test check permission denied without capability | $this->assertFalse( ( new Updatronix_Pro_License_REST() )->check_permission() ); |
| `test_check_permission_granted_with_capability_on_single_site` | test check permission granted with capability on single site | $this->assertTrue( ( new Updatronix_Pro_License_REST() )->check_permission() ); |
| `test_check_permission_denied_to_non_super_admin_on_multisite` | test check permission denied to non super admin on multisite | $this->assertFalse( ( new Updatronix_Pro_License_REST() )->check_permission() ); |
| `test_check_permission_granted_to_super_admin_on_multisite` | test check permission granted to super admin on multisite | $this->assertTrue( ( new Updatronix_Pro_License_REST() )->check_permission() ); |
| `test_get_status_active_license` | test get status active license | $this->assertSame( 200, $response->get_status() );<br>$this->assertSame( 'active', $data['status'] );<br>$this->assertSame( 'example.com', $data['registeredDomain'] );<br>$this->assertSame( 'XXXX-XXXX-XXXX-ABCD', $data['maskedKey'] ); |
| `test_get_status_inactive_license` | test get status inactive license | $this->assertSame( 200, $response->get_status() );<br>$this->assertSame( 'inactive', $data['status'] );<br>$this->assertSame( '', $data['maskedKey'] );<br>$this->assertNull( $data['lastChecked'] ); |
| `test_activate_rate_limited_within_cooldown` | test activate rate limited within cooldown | $this->assertSame( 429, $response->get_status() );<br>$this->assertSame( 'error', $data['status'] );<br>$this->assertSame( 'e429', $data['code'] );<br>$this->assertArrayHasKey( 'retry_after', $data ); |
| `test_activate_api_error_returns_400` | test activate api error returns 400 | $this->assertSame( 400, $response->get_status() );<br>$this->assertSame( 'error', $data['status'] );<br>$this->assertSame( 'e000', $data['code'] );<br>$this->assertSame( 'API unreachable.', $data['message'] ); |
| `test_activate_sll_error_codes_return_400` | test activate sll error codes return 400 | $this->assertSame( 400, $response->get_status(), "Code $code must map to HTTP 400." );<br>$this->assertSame( $code, $response->get_data()['code'] ); |
| `test_activate_success_persists_and_requests_correct_endpoint` | test activate success persists and requests correct endpoint | $this->assertSame( 200, $response->get_status() );<br>$this->assertSame( 'active', $data['status'] );<br>$this->assertSame( 'example.com', $data['registeredDomain'] );<br>$this->assertSame( 'XXXX-XXXX-XXXX-ABCD', $data['maskedKey'] ); |
| `test_activate_accepts_real_key_and_masks_it` | test activate accepts real key and masks it | $this->assertSame( 200, $response->get_status() );<br>$this->assertSame( 'active', $data['status'] );<br>$this->assertSame( 'UPD-PRO-••••••••-••••••••-••••ed14', $data['maskedKey'] );<br>$this->assertSame( $key, $stored['license_key'] ); |
| `test_activate_s101_success_persists` | test activate s101 success persists | $this->assertSame( 200, $response->get_status() );<br>$this->assertSame( 'active', Updatronix_Pro_License_Storage::get_license_data()['status'] ); |
| `test_activate_unknown_status_code_returns_400` | test activate unknown status code returns 400 | $this->assertSame( 400, $response->get_status() );<br>$this->assertSame( 's999', $response->get_data()['code'] );<br>$this->assertNotSame( 'active', Updatronix_Pro_License_Storage::get_license_data()['status'] ); |
| `test_deactivate_inactive_license_returns_400` | test deactivate inactive license returns 400 | $this->assertSame( 400, $response->get_status() );<br>$this->assertSame( 'error', $data['status'] );<br>$this->assertSame( 'rest_license_inactive', $data['code'] );<br>$this->assertCount( 0, $updatronix_test_request_log ); |
| `test_deactivate_api_error_returns_400` | test deactivate api error returns 400 | $this->assertSame( 400, $response->get_status() );<br>$this->assertSame( 'e000', $response->get_data()['code'] ); |
| `test_deactivate_success_clears_storage` | test deactivate success clears storage | $this->assertSame( 200, $response->get_status() );<br>$this->assertSame( 'inactive', $response->get_data()['status'] );<br>$this->assertSame( 'inactive', $stored['status'] );<br>$this->assertSame( '', $stored['license_key'] ); |
| `test_deactivate_rejected_returns_400_and_keeps_storage` | test deactivate rejected returns 400 and keeps storage | $this->assertSame( 400, $response->get_status() );<br>$this->assertSame( 's203', $response->get_data()['code'] );<br>$this->assertSame( 'active', $stored['status'] );<br>$this->assertSame( 'SLL-1234-5678-ABCD', $stored['license_key'] ); |
| `test_activate_is_not_rate_limited_after_the_cooldown` | test activate is not rate limited after the cooldown | $this->assertNotSame( 429, $response->get_status() ); |
| `test_activate_is_not_throttled_by_a_recent_update_check` | test activate is not throttled by a recent update check | $this->assertNotSame( 429, $response->get_status() ); |
| `test_deactivate_clears_an_undecryptable_key` | test deactivate clears an undecryptable key | $this->assertSame( 200, $response->get_status() );<br>$this->assertSame( '', $updatronix_test_license_option['encrypted_key'] );<br>$this->assertSame( 'inactive', $updatronix_test_license_option['status'] ); |
| `test_activate_ignores_an_expired_throttle` | test activate ignores an expired throttle | $this->assertNotSame( 429, $response->get_status() ); |
| `test_activate_treats_a_non_numeric_throttle_as_no_attempt` | test activate treats a non numeric throttle as no attempt | $this->assertNotSame( 429, $response->get_status() ); |
| `test_get_status_reports_decryption_error_with_invalid_status` | test get status reports decryption error with invalid status | $this->assertSame( 200, $response->get_status() );<br>$this->assertTrue( $data['decryption_error'] );<br>$this->assertSame( 'inactive', $data['status'] );<br>$this->assertSame( '', $data['maskedKey'] ); |
| `test_get_status_never_returns_the_full_key` | test get status never returns the full key | $this->assertStringNotContainsString( $key, $payload );<br>$this->assertSame( 'UPD-PRO-••••••••-••••••••-••••ed14', $data['maskedKey'] ); |
| `test_activate_error_message_redacts_the_key` | test activate error message redacts the key | $this->assertSame( 400, $response->get_status() );<br>$this->assertStringNotContainsString( $key, $data['message'] );<br>$this->assertStringContainsString( 'UPD-PRO-••••••••-••••••••-••••ed14', $data['message'] ); |
| `test_deactivate_error_message_redacts_the_key` | test deactivate error message redacts the key | $this->assertSame( 400, $response->get_status() );<br>$this->assertStringNotContainsString( $key, $data['message'] );<br>$this->assertStringContainsString( 'UPD-PRO-••••••••-••••••••-••••ed14', $data['message'] ); |

#### `tests/Unit/LicenseStorageTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_save_license_data_encrypts_and_never_persists_plaintext` | test save license data encrypts and never persists plaintext | $this->assertTrue( Updatronix_Pro_License_Storage::save_license_data( array( 'license_key' => $key, 'status' => 'active', ) ) );<br>$this->assertIsArray( $updatronix_test_license_option );<br>$this->assertArrayNotHasKey( 'license_key', $updatronix_test_license_option );<br>$this->assertNotEmpty( $updatronix_test_license_option['encrypted_key'] ); |
| `test_get_license_data_decrypts_stored_key` | test get license data decrypts stored key | $this->assertSame( $key, $data['license_key'] );<br>$this->assertSame( 'active', $data['status'] ); |
| `test_get_license_data_returns_defaults_when_nothing_stored` | test get license data returns defaults when nothing stored | $this->assertSame( array( 'license_key' => '', 'status' => 'inactive', 'registered_domain' => '', 'last_checked' => 0, 'encrypted_key' => '', ), $data ); |
| `test_get_license_data_returns_defaults_when_the_option_is_not_an_array` | test get license data returns defaults when the option is not an array | $this->assertSame( array( 'license_key' => '', 'status' => 'inactive', 'registered_domain' => '', 'last_checked' => 0, 'encrypted_key' => '', ), Updatronix_Pro_License_Storage::get_license_data() ); |
| `test_save_license_data_partial_update_preserves_existing_fields` | test save license data partial update preserves existing fields | $this->assertSame( 'AAAA-BBBB-CCCC-DDDD', $data['license_key'] );<br>$this->assertSame( 'inactive', $data['status'] );<br>$this->assertSame( 'example.com', $data['registered_domain'] );<br>$this->assertSame( 123, $data['last_checked'] ); |
| `test_save_license_data_empty_key_clears_encrypted_key` | test save license data empty key clears encrypted key | $this->assertSame( '', $updatronix_test_license_option['encrypted_key'] );<br>$this->assertSame( '', $data['license_key'] ); |
| `test_encrypt_decrypt_round_trip` | test encrypt decrypt round trip | $this->assertIsString( $encrypted );<br>$this->assertNotSame( $plain, $encrypted );<br>$this->assertStringNotContainsString( 'SECRET', $encrypted );<br>$this->assertSame( $plain, $this->call_private( 'decrypt', $encrypted ) ); |
| `test_get_encryption_key_is_32_bytes` | test get encryption key is 32 bytes | $this->assertIsString( $key );<br>$this->assertSame( 32, strlen( $key ) ); |
| `test_decrypt_invalid_base64_returns_empty` | test decrypt invalid base64 returns empty | $this->assertSame( '', $this->call_private( 'decrypt', '!!!not-base64!!!' ) ); |
| `test_decrypt_corrupt_ciphertext_returns_empty` | test decrypt corrupt ciphertext returns empty | $this->assertSame( '', $this->call_private( 'decrypt', $corrupt ) ); |
| `test_get_license_data_handles_decryption_failure` | test get license data handles decryption failure | $this->assertSame( '', $data['license_key'] );<br>$this->assertSame( 'inactive', $data['status'] );<br>$this->assertSame( '', $data['encrypted_key'] );<br>$this->assertSame( 'old.example.com', $data['registered_domain'] ); |
| `test_has_decryption_error_true_when_stored_key_cannot_be_decrypted` | test has decryption error true when stored key cannot be decrypted | $this->assertTrue( Updatronix_Pro_License_Storage::has_decryption_error() ); |
| `test_has_decryption_error_false_without_encrypted_key` | test has decryption error false without encrypted key | $this->assertFalse( Updatronix_Pro_License_Storage::has_decryption_error() ); |
| `test_has_decryption_error_true_when_status_invalid_and_key_is_corrupt` | test has decryption error true when status invalid and key is corrupt | $this->assertTrue( Updatronix_Pro_License_Storage::has_decryption_error() ); |
| `test_has_decryption_error_false_when_status_invalid_and_key_decrypts` | test has decryption error false when status invalid and key decrypts | $this->assertFalse( Updatronix_Pro_License_Storage::has_decryption_error() ); |
| `test_has_decryption_error_false_when_decryption_succeeds` | test has decryption error false when decryption succeeds | $this->assertFalse( Updatronix_Pro_License_Storage::has_decryption_error() ); |
| `test_mask_license_key_empty_string` | test mask license key empty string | $this->assertSame( '', Updatronix_Pro_License_Storage::mask_license_key( '' ) ); |
| `test_mask_license_key_short_key_unchanged` | test mask license key short key unchanged | $this->assertSame( 'abcd', Updatronix_Pro_License_Storage::mask_license_key( 'abcd' ) ); |
| `test_mask_license_key_preserves_hyphen_segments` | test mask license key preserves hyphen segments | $this->assertSame( 'XXXX-XXXX-XXXX-1234', Updatronix_Pro_License_Storage::mask_license_key( 'ABCD-EFGH-IJKL-1234' ) ); |
| `test_mask_license_key_plain_long_key` | test mask license key plain long key | $this->assertSame( 'XXXXXXXX1234', Updatronix_Pro_License_Storage::mask_license_key( 'ABCDEFGH1234' ) ); |
| `test_is_valid_key_format_accepts_real_key` | test is valid key format accepts real key | $this->assertTrue( Updatronix_Pro_License_Storage::is_valid_key_format( 'UPD-PRO-27ad60ff-e196c456-72deed14' ) ); |
| `test_is_valid_key_format_rejects_legacy_four_by_four` | test is valid key format rejects legacy four by four | $this->assertFalse( Updatronix_Pro_License_Storage::is_valid_key_format( 'ABCD-EFGH-IJKL-1234' ) ); |
| `test_mask_license_key_masks_real_format` | test mask license key masks real format | $this->assertSame( 'UPD-PRO-••••••••-••••••••-••••ed14', Updatronix_Pro_License_Storage::mask_license_key( 'UPD-PRO-27ad60ff-e196c456-72deed14' ) ); |
| `test_delete_license_data_clears_stored_option` | test delete license data clears stored option | $this->assertTrue( Updatronix_Pro_License_Storage::delete_license_data() );<br>$this->assertNull( $updatronix_test_license_option );<br>$this->assertSame( 'inactive', $data['status'] ); |
| `test_license_active_state` | test license active state | $this->assertTrue( Updatronix_Pro_License_Storage::is_license_active() );<br>$this->assertSame( 'active', Updatronix_Pro_License_Storage::get_license_status() ); |
| `test_license_inactive_state` | test license inactive state | $this->assertFalse( Updatronix_Pro_License_Storage::is_license_active() );<br>$this->assertSame( 'inactive', Updatronix_Pro_License_Storage::get_license_status() ); |
| `test_a_four_character_key_is_returned_unchanged` | test a four character key is returned unchanged | $this->assertSame( 'abcd', $this->call_private( 'mask_license_key', 'abcd' ) );<br>$this->assertSame( 'a-bc', $this->call_private( 'mask_license_key', 'a-bc' ) ); |
| `test_save_license_data_removes_a_legacy_plaintext_key` | test save license data removes a legacy plaintext key | $this->assertArrayNotHasKey( 'license_key', $updatronix_test_license_option );<br>$this->assertSame( 'inactive', $updatronix_test_license_option['status'] ); |
| `test_has_decryption_error_handles_a_non_array_option` | test has decryption error handles a non array option | $this->assertFalse( Updatronix_Pro_License_Storage::has_decryption_error() ); |
| `test_salts_available_requires_all_three_constants` | test salts available requires all three constants | $this->assertSame( 7 === $mask, $this->call_private( 'salts_available', $auth, $secure, $nonce ), sprintf( 'Mask %d must require all three salts.', $mask ) ); |

#### `tests/Unit/LicenseUpdaterMissingPackageNoticeTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_missing_package_check_caches_empty_package_and_notice` | test missing package check caches empty package and notice | $this->assertIsObject( $updatronix_test_site_transient );<br>$this->assertSame( '9.9.9', $updatronix_test_site_transient->new_version );<br>$this->assertSame( '', $updatronix_test_site_transient->package );<br>$this->assertTrue( $updater->should_show_missing_package_notice() ); |
| `test_package_rejected_by_url_validator_is_dropped` | test package rejected by url validator is dropped | $this->assertSame( '', $updatronix_test_site_transient->package );<br>$this->assertTrue( $updater->should_show_missing_package_notice() ); |
| `test_real_package_check_suppresses_notice` | test real package check suppresses notice | $this->assertFalse( $updater->should_show_missing_package_notice() ); |
| `test_notice_guard_true_on_plugin_screens` | test notice guard true on plugin screens | $this->assertTrue( $updater->should_show_missing_package_notice(), "Expected notice on screen {$screen_id}" ); |
| `test_notice_guard_false_with_real_package` | test notice guard false with real package | $this->assertFalse( $updater->should_show_missing_package_notice() ); |
| `test_notice_guard_false_without_newer_version` | test notice guard false without newer version | $this->assertFalse( $updater->should_show_missing_package_notice() ); |
| `test_notice_guard_false_when_license_inactive` | test notice guard false when license inactive | $this->assertFalse( $updater->should_show_missing_package_notice() ); |
| `test_notice_guard_false_on_other_screen` | test notice guard false on other screen | $this->assertFalse( $updater->should_show_missing_package_notice() ); |
| `test_notice_guard_false_without_capability` | test notice guard false without capability | $this->assertFalse( $updater->should_show_missing_package_notice() ); |
| `test_notice_guard_false_in_dev_mode` | test notice guard false in dev mode | $this->assertFalse( $updater->should_show_missing_package_notice() ); |
| `test_a_real_package_is_not_logged_as_missing` | test a real package is not logged as missing | $this->assertSame( array(), $GLOBALS['updatronix_test_log'] ); |

#### `tests/Unit/LicenseUpdaterStatusTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_success_s401_empty_update_data_keeps_active` | test success s401 empty update data keeps active | $this->assertSame( 'active', Updatronix_Pro_License_Storage::get_license_data()['status'] );<br>$this->assertIsObject( $updatronix_test_site_transient ); |
| `test_success_s411_empty_update_data_sets_invalid` | test success s411 empty update data sets invalid | $this->assertSame( 'invalid', Updatronix_Pro_License_Storage::get_license_data()['status'] ); |
| `test_success_s401_with_update_data_keeps_active_and_caches` | test success s401 with update data keeps active and caches | $this->assertSame( 'active', Updatronix_Pro_License_Storage::get_license_data()['status'] );<br>$this->assertIsObject( $updatronix_test_site_transient );<br>$this->assertSame( '9.9.9', $updatronix_test_site_transient->new_version );<br>$this->assertArrayHasKey( $basename, $result->response ); |
| `test_success_unknown_status_code_keeps_status_unchanged` | test success unknown status code keeps status unchanged | $this->assertSame( 'active', $data['status'] );<br>$this->assertSame( 12345, $data['last_checked'] ); |
| `test_error_keeps_status_unchanged` | test error keeps status unchanged | $this->assertSame( 'active', $data['status'] );<br>$this->assertSame( 12345, $data['last_checked'] );<br>$this->assertIsObject( $updatronix_test_site_transient ); |
| `test_http_error_keeps_status_and_caches` | test http error keeps status and caches | $this->assertSame( 'active', $data['status'] );<br>$this->assertSame( 12345, $data['last_checked'] );<br>$this->assertIsObject( $updatronix_test_site_transient ); |
| `test_update_data_new_version_without_package_caches_as_update` | test update data new version without package caches as update | $this->assertSame( 'active', Updatronix_Pro_License_Storage::get_license_data()['status'] );<br>$this->assertIsObject( $updatronix_test_site_transient );<br>$this->assertSame( '9.9.9', $updatronix_test_site_transient->new_version );<br>$this->assertSame( '', $updatronix_test_site_transient->package ); |
| `test_s411_does_not_bypass_dev_mode` | test s411 does not bypass dev mode | $this->assertSame( 'invalid', Updatronix_Pro_License_Storage::get_license_data()['status'] );<br>$this->assertTrue( Updatronix_Pro_License_Storage::is_license_active() );<br>$this->assertTrue( $handler->is_license_valid() ); |
| `test_transient_set_after_non_error_response` | test transient set after non error response | $this->assertIsObject( $updatronix_test_site_transient );<br>$this->assertObjectHasProperty( 'new_version', $updatronix_test_site_transient );<br>$this->assertObjectHasProperty( 'plugin', $updatronix_test_site_transient );<br>$this->assertObjectHasProperty( 'slug', $updatronix_test_site_transient ); |
| `test_update_package_filter_overrides_package` | test update package filter overrides package | $this->assertSame( 'https://downloads.example.com/updatronix-pro-9.9.9.zip', $updatronix_test_site_transient->package ); |
| `test_force_check_bypasses_cache_and_refetches` | test force check bypasses cache and refetches | $this->assertSame( '9.9.9', $updatronix_test_site_transient->new_version );<br>$this->assertArrayHasKey( $basename, $result->response ); |
| `test_force_check_without_update_refetches_and_caches_placeholder` | test force check without update refetches and caches placeholder | $this->assertSame( UPDATRONIX_PRO_VERSION, $updatronix_test_site_transient->new_version );<br>$this->assertArrayNotHasKey( $basename, $result->response );<br>$this->assertArrayHasKey( $basename, $result->no_update ); |
| `test_force_check_second_filter_invocation_serves_cache` | test force check second filter invocation serves cache | $this->assertSame( '9.9.9', $updatronix_test_site_transient->new_version );<br>$this->assertArrayHasKey( 'updatronix-pro/updatronix-pro.php', $result2->response ); |
| `test_update_cache_ttl_defaults_to_12h` | test update cache ttl defaults to 12h | $this->assertSame( 12 * HOUR_IN_SECONDS, $updater->get_update_cache_ttl() ); |
| `test_update_cache_ttl_respects_recurrence_and_delay` | test update cache ttl respects recurrence and delay | $this->assertSame( HOUR_IN_SECONDS, $updater->get_update_cache_ttl() );<br>$this->assertSame( DAY_IN_SECONDS, $updater->get_update_cache_ttl() );<br>$this->assertSame( 3 * DAY_IN_SECONDS, $updater->get_update_cache_ttl() ); |
| `test_update_cache_ttl_filter_overrides` | test update cache ttl filter overrides | $this->assertSame( 300, $updater->get_update_cache_ttl() ); |
| `test_force_check_get_param_alone_does_not_bypass_cache` | test force check get param alone does not bypass cache | $this->assertSame( UPDATRONIX_PRO_VERSION, $updatronix_test_site_transient->new_version );<br>$this->assertArrayNotHasKey( 'updatronix-pro/updatronix-pro.php', $result->response ); |
| `test_update_data_homepage_maps_to_url` | test update data homepage maps to url | $this->assertSame( 'https://example.com/updatronix-pro/', $updatronix_test_site_transient->url ); |
| `test_update_data_non_string_new_version_is_not_an_update` | test update data non string new version is not an update | $this->assertSame( UPDATRONIX_PRO_VERSION, $updatronix_test_site_transient->new_version );<br>$this->assertArrayNotHasKey( $basename, $result->response ); |
| `test_update_data_non_string_package_stays_empty` | test update data non string package stays empty | $this->assertSame( '', $updatronix_test_site_transient->package ); |
| `test_response_s401_saves_the_license_as_active` | test response s401 saves the license as active | $this->assertSame( 'active', $stored['status'] ); |
| `test_the_delay_window_clamps_only_when_enabled_and_positive` | test the delay window clamps only when enabled and positive | $this->assertSame( 3 * DAY_IN_SECONDS, $updater->get_update_cache_ttl() );<br>$this->assertSame( WEEK_IN_SECONDS, $updater->get_update_cache_ttl() ); |
| `test_check_for_updates_returns_a_non_object_unchanged` | test check for updates returns a non object unchanged | $this->assertFalse( $updater->check_for_updates( false ) ); |

#### `tests/Unit/ProBootstrapTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_init_registers_missing_free_notices_when_free_version_absent` | test init registers missing free notices when free version absent | self::assertFalse( defined( 'UPDATRONIX_VERSION' ), 'Unit bootstrap must not define UPDATRONIX_VERSION.' );<br>self::assertContains( 'updatronix_pro_missing_free_notice', $this->callbacksFor( 'admin_notices' ) );<br>self::assertContains( 'updatronix_pro_missing_free_notice', $this->callbacksFor( 'network_admin_notices' ) ); |
| `test_pass_health_requires_engine_and_main_site` | test pass health requires engine and main site | self::assertTrue( Updatronix_Pro_Bootstrap::should_register_pass_health( true ) );<br>self::assertFalse( Updatronix_Pro_Bootstrap::should_register_pass_health( false ) ); |
| `test_clear_pass_work_removes_last_pass_and_follow_up_event` | test clear pass work removes last pass and follow up event | self::assertSame( array(), Updatronix_Update_Pass::get_last_pass(), 'The stale last-pass cache is deleted.' );<br>self::assertFalse( wp_next_scheduled( Updatronix_Update_Pass::FOLLOW_UP_HOOK ), 'The follow-up event is cleared.' ); |
| `test_enqueue_registers_admin_assets_action` | test enqueue registers admin assets action | self::assertContains( 'Updatronix_Pro_Enqueue::enqueue_scripts', $this->callbacksFor( 'updatronix_enqueue_admin_assets' ), 'Enqueue must hook enqueue_scripts onto updatronix_enqueue_admin_assets.' ); |
| `test_license_updater_registers_update_hooks` | test license updater registers update hooks | self::assertContains( 'Updatronix_Pro_License_Updater::check_for_updates', $this->callbacksFor( 'pre_set_site_transient_update_plugins' ), 'The updater must filter pre_set_site_transient_update_plugins.' );<br>self::assertContains( 'Updatronix_Pro_License_Updater::plugin_info', $this->callbacksFor( 'plugins_api' ), 'The updater must filter plugins_api.' );<br>self::assertContains( 'Updatronix_Pro_License_Updater::missing_package_notice', $this->callbacksFor( 'admin_notices' ), 'The updater must show the missing-package notice on admin_notices.' ); |

#### `tests/Unit/ProConnectorTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_single_site_with_capability_is_allowed` | test single site with capability is allowed | $this->assertTrue( Updatronix_Pro_Connector::can_manage_pro() ); |
| `test_single_site_without_capability_is_denied` | test single site without capability is denied | $this->assertFalse( Updatronix_Pro_Connector::can_manage_pro() ); |
| `test_multisite_non_super_admin_is_denied` | test multisite non super admin is denied | $this->assertFalse( Updatronix_Pro_Connector::can_manage_pro() ); |
| `test_multisite_super_admin_is_allowed` | test multisite super admin is allowed | $this->assertTrue( Updatronix_Pro_Connector::can_manage_pro() ); |

#### `tests/Unit/ProGatingTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_version_lock_defaults_and_stored_master` | test version lock defaults and stored master | self::assertArrayHasKey( 'version_lock_manual_updates', UPDATRONIX_PRO_SHIELD_DEFAULTS );<br>self::assertFalse( UPDATRONIX_PRO_SHIELD_DEFAULTS['version_lock_manual_updates'] );<br>self::assertFalse( $settings['version_control_lock'], 'The stored false value is preserved.' );<br>self::assertFalse( $settings['version_lock_manual_updates'], 'The new key defaults off.' ); |
| `test_shield_defaults_are_exact` | test shield defaults are exact | self::assertSame( $value, UPDATRONIX_PRO_SHIELD_DEFAULTS[ $key ], $key ); |
| `test_saving_shield_settings_clears_the_detector_cache` | test saving shield settings clears the detector cache | self::assertSame( 'no', $detector->get_state( 'plugin', $id )['native'] );<br>self::assertSame( 'yes', $detector->get_state( 'plugin', $id )['native'], 'Saving settings clears the detector cache.' ); |
| `test_uninstall_removes_version_lock_storage` | test uninstall removes version lock storage | self::assertFalse( get_site_option( UPDATRONIX_PRO_OPTION_SHIELD, false ) );<br>self::assertFalse( get_site_option( 'updatronix_pro_version_lock_notice_seen', false ) ); |
| `test_version_lock_migration_notice_is_shown_once` | test version lock migration notice is shown once | self::assertStringContainsString( 'Version Lock is off', $first );<br>self::assertSame( '1', get_site_option( Updatronix_Version_Lock_Admin::NOTICE_OPTION ) );<br>self::assertSame( '', $second ); |
| `test_inactive_license_skips_pro_subsystems` | test inactive license skips pro subsystems | self::assertContains( 'Updatronix_Pro_Enqueue::enqueue_scripts', $this->callbacksFor( 'updatronix_enqueue_admin_assets' ), 'The activation screen still needs the Pro bundle.' );<br>self::assertNotContains( 'Updatronix_Safe_Mode::filter_auto_update_plugin', $this->callbacksFor( 'auto_update_plugin' ), 'Safe Mode must not filter updates without an active license.' );<br>self::assertNotContains( 'Updatronix_White_Label::filter_brand', $this->callbacksFor( 'updatronix_admin_brand' ), 'White label must not filter the brand without an active license.' );<br>self::assertNotContains( 'Updatronix_Update_Flow::define_rest_routes', $this->callbacksFor( 'rest_api_init' ), 'Flow REST routes must not register without an active license.' ); |
| `test_active_license_registers_pro_subsystems` | test active license registers pro subsystems | self::assertContains( 'Updatronix_Safe_Mode::filter_auto_update_plugin', $this->callbacksFor( 'auto_update_plugin' ) );<br>self::assertContains( 'Updatronix_White_Label::filter_brand', $this->callbacksFor( 'updatronix_admin_brand' ) );<br>self::assertContains( 'Updatronix_Update_Flow::define_rest_routes', $this->callbacksFor( 'rest_api_init' ) );<br>self::assertContains( 'Updatronix_Update_Shield::define_rest_routes', $this->callbacksFor( 'rest_api_init' ) ); |
| `test_shield_defaults_keep_the_composer_lock` | test shield defaults keep the composer lock | self::assertArrayNotHasKey( 'queue_enabled', UPDATRONIX_PRO_SHIELD_DEFAULTS );<br>self::assertTrue( UPDATRONIX_PRO_SHIELD_DEFAULTS['version_control_lock'] ); |
| `test_export_formats_follow_license` | test export formats follow license | self::assertSame( array(), Updatronix_Pro_Bootstrap::export_formats( array() ) );<br>self::assertSame( array( 'csv', 'json', 'xml' ), Updatronix_Pro_Bootstrap::export_formats( array() ) ); |

#### `tests/Unit/SafeModeBulkShieldTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_filter_auto_update_core_passes_through_when_safe_mode_off` | test filter auto update core passes through when safe mode off | $this->assertSame( true, Updatronix_Safe_Mode::filter_auto_update_core( true, $item ) );<br>$this->assertSame( false, Updatronix_Safe_Mode::filter_auto_update_core( false, $item ) );<br>$this->assertCount( 0, $this->blocked() ); |
| `test_filter_auto_update_core_passes_through_when_update_falsy` | test filter auto update core passes through when update falsy | $this->assertSame( false, Updatronix_Safe_Mode::filter_auto_update_core( false, $item ) );<br>$this->assertCount( 0, $this->blocked() ); |
| `test_filter_auto_update_core_blocks_incompatible_update` | test filter auto update core blocks incompatible update | $this->assertFalse( $result );<br>$this->assertCount( 1, $blocked );<br>$this->assertSame( 'core', $blocked[0]['type'] );<br>$this->assertSame( 'filter', $blocked[0]['context'] ); |
| `test_filter_auto_update_core_passes_compatible_update` | test filter auto update core passes compatible update | $this->assertSame( true, Updatronix_Safe_Mode::filter_auto_update_core( true, $item ) );<br>$this->assertCount( 0, $this->blocked() ); |
| `test_filter_auto_update_core_passes_when_wp_control_disabled` | test filter auto update core passes when wp control disabled | $this->assertSame( true, Updatronix_Safe_Mode::filter_auto_update_core( true, $item ) );<br>$this->assertCount( 0, $this->blocked() ); |
| `test_get_blocked_items_empty_when_no_transients` | test get blocked items empty when no transients | $this->assertSame( array( 'plugins' => array(), 'themes' => array(), 'core' => array(), ), $blocked ); |
| `test_get_blocked_items_plugins_pending_and_header_metadata` | test get blocked items plugins pending and header metadata | $this->assertArrayHasKey( 'plug/plug.php', $blocked['plugins'] );<br>$this->assertSame( 'Plug', $blocked['plugins']['plug/plug.php']['name'] );<br>$this->assertArrayHasKey( 'slug', $blocked['plugins']['plug/plug.php'] );<br>$this->assertArrayHasKey( 'older/older.php', $blocked['plugins'] ); |
| `test_get_blocked_items_themes_pending_and_installed` | test get blocked items themes pending and installed | $this->assertArrayHasKey( 'mytheme', $blocked['themes'] );<br>$this->assertSame( 'My Theme', $blocked['themes']['mytheme']['name'] );<br>$this->assertSame( '2.0', $blocked['themes']['mytheme']['version'] );<br>$this->assertArrayHasKey( 'oldtheme', $blocked['themes'] ); |
| `test_get_blocked_items_core_latest_non_dev_offer` | test get blocked items core latest non dev offer | $this->assertArrayHasKey( 'core', $blocked );<br>$this->assertSame( 'WordPress', $blocked['core']['name'] );<br>$this->assertSame( '6.6', $blocked['core']['version'] );<br>$this->assertStringContainsString( 'requires WordPress 6.6', $blocked['core']['reason'] ); |
| `test_get_blocked_items_core_skipped_when_constant_disabled` | test get blocked items core skipped when constant disabled | $this->assertArrayHasKey( 'core', $blocked );<br>$this->assertSame( array(), $blocked['core'] ); |
| `test_get_blocked_items_empty_when_safe_mode_off` | test get blocked items empty when safe mode off | $this->assertSame( array( 'plugins' => array(), 'themes' => array(), 'core' => array(), ), $blocked ); |
| `test_disable_blocked_auto_updates_disables_enabled_plugins` | test disable blocked auto updates disables enabled plugins | $this->assertCount( 1, $blocked_log );<br>$this->assertSame( 'plugin', $blocked_log[0]['type'] );<br>$this->assertSame( 'recheck', $blocked_log[0]['context'] );<br>$this->assertTrue( $blocked_log[0]['auto_update_disabled'] ); |
| `test_disable_blocked_auto_updates_skips_plugins_not_enabled` | test disable blocked auto updates skips plugins not enabled | $this->assertCount( 0, $this->blocked() );<br>$this->assertCount( 0, $this->pending() ); |
| `test_disable_blocked_auto_updates_disables_enabled_themes` | test disable blocked auto updates disables enabled themes | $this->assertCount( 1, $blocked_log );<br>$this->assertSame( 'theme', $blocked_log[0]['type'] );<br>$this->assertSame( 'recheck', $blocked_log[0]['context'] );<br>$this->assertTrue( $blocked_log[0]['auto_update_disabled'] ); |
| `test_disable_blocked_auto_updates_ignores_core` | test disable blocked auto updates ignores core | $this->assertCount( 0, $this->blocked() );<br>$this->assertCount( 0, $this->pending() ); |
| `test_upgrader_process_complete_returns_when_safe_mode_off` | test upgrader process complete returns when safe mode off | $this->assertSame( array(), $GLOBALS['updatronix_test_update_calls'] );<br>$this->assertCount( 0, $this->blocked() ); |
| `test_upgrader_process_complete_returns_without_recognized_type` | test upgrader process complete returns without recognized type | $this->assertSame( array(), $GLOBALS['updatronix_test_update_calls'] );<br>$this->assertCount( 0, $this->blocked() ); |
| `test_upgrader_plugin_batch_records_and_defers_for_enabled_blocked` | test upgrader plugin batch records and defers for enabled blocked | $this->assertSame( array( 'plugins' ), $GLOBALS['updatronix_test_update_calls'] );<br>$this->assertCount( 1, $blocked );<br>$this->assertSame( 'plugin', $blocked[0]['type'] );<br>$this->assertSame( 'upgrader', $blocked[0]['context'] ); |
| `test_upgrader_plugin_single_records_when_auto_update_not_enabled` | test upgrader plugin single records when auto update not enabled | $this->assertSame( array( 'plugins' ), $GLOBALS['updatronix_test_update_calls'] );<br>$this->assertCount( 1, $blocked );<br>$this->assertSame( 'plugin', $blocked[0]['type'] );<br>$this->assertFalse( $blocked[0]['auto_update_disabled'] ); |
| `test_upgrader_theme_records_and_defers_for_enabled_blocked` | test upgrader theme records and defers for enabled blocked | $this->assertSame( array( 'themes' ), $GLOBALS['updatronix_test_update_calls'] );<br>$this->assertCount( 1, $blocked );<br>$this->assertSame( 'theme', $blocked[0]['type'] );<br>$this->assertSame( 'upgrader', $blocked[0]['context'] ); |
| `test_upgrader_theme_not_blocked_logs_nothing` | test upgrader theme not blocked logs nothing | $this->assertSame( array( 'themes' ), $GLOBALS['updatronix_test_update_calls'] );<br>$this->assertCount( 0, $this->blocked() );<br>$this->assertCount( 0, $this->pending() ); |
| `test_admin_notices_returns_when_safe_mode_off` | test admin notices returns when safe mode off | $this->assertSame( '', $html ); |
| `test_admin_notices_returns_off_update_core_page` | test admin notices returns off update core page | $this->assertSame( '', $html ); |
| `test_admin_notices_lists_blocked_items` | test admin notices lists blocked items | $this->assertStringContainsString( 'notice notice-warning', $html );<br>$this->assertStringContainsString( 'items are potentially incompatible', $html );<br>$this->assertStringContainsString( 'Plug', $html );<br>$this->assertStringContainsString( 'My Theme', $html ); |
| `test_admin_notices_no_output_when_nothing_blocked` | test admin notices no output when nothing blocked | $this->assertSame( '', $html ); |
| `test_blocked_notification_sent_with_recipient_and_body` | test blocked notification sent with recipient and body | $this->assertCount( 1, $GLOBALS['updatronix_test_mail_log'] );<br>$this->assertSame( 'admin@example.com', $mail['to'] );<br>$this->assertStringContainsString( 'Safe mode blocked automatic updates', $mail['subject'] );<br>$this->assertStringContainsString( 'Test Plugin', $mail['message'] ); |
| `test_blocked_notification_not_sent_when_nothing_blocked` | test blocked notification not sent when nothing blocked | $this->assertSame( array(), $GLOBALS['updatronix_test_mail_log'] ); |
| `test_blocked_notification_not_sent_when_notify_disabled` | test blocked notification not sent when notify disabled | $this->assertSame( array(), $GLOBALS['updatronix_test_mail_log'] ); |
| `test_blocked_notification_not_sent_when_free_notifications_disabled` | test blocked notification not sent when free notifications disabled | $this->assertSame( array(), $GLOBALS['updatronix_test_mail_log'] ); |
| `test_blocked_notification_uses_custom_emails` | test blocked notification uses custom emails | $this->assertCount( 1, $GLOBALS['updatronix_test_mail_log'] );<br>$this->assertSame( 'ops@example.com', $GLOBALS['updatronix_test_mail_log'][0]['to'] ); |
| `test_email_body_reports_found_incompatible_when_not_disabled` | test email body reports found incompatible when not disabled | $this->assertStringContainsString( 'found incompatible items', $body );<br>$this->assertStringContainsString( 'WordPress 6.5.4', $body );<br>$this->assertStringContainsString( 'Test Plugin (plugin)', $body );<br>$this->assertStringContainsString( 'Notification sent to admin@example.com', $body ); |
| `test_email_body_reports_blocked_when_disabled` | test email body reports blocked when disabled | $this->assertStringContainsString( 'has blocked automatic updates due to incompatibility', $body ); |
| `test_record_blocked_uses_the_plugin_slug` | test record blocked uses the plugin slug | $this->assertCount( 1, $blocked );<br>$this->assertSame( 'my-plugin-slug', $blocked[0]['slug'] ); |
| `test_record_blocked_uses_the_theme_slug` | test record blocked uses the theme slug | $this->assertCount( 1, $blocked );<br>$this->assertSame( 'my-theme-slug', $blocked[0]['slug'] ); |
| `test_resolve_email_recipient_ignores_custom_emails_without_notify_enabled` | test resolve email recipient ignores custom emails without notify enabled | $this->assertSame( 'admin@example.com', $this->call_private( 'resolve_email_recipient' ) ); |
| `test_the_log_records_the_incompatible_action_when_auto_update_was_off` | test the log records the incompatible action when auto update was off | $this->assertCount( 1, $log );<br>$this->assertSame( 'incompatible', $log[0]['action'] ); |
| `test_the_log_records_safe_mode_disabled_when_auto_update_was_on` | test the log records safe mode disabled when auto update was on | $this->assertCount( 1, $log );<br>$this->assertSame( 'safe_mode_disabled', $log[0]['action'] ); |
| `test_the_log_records_safe_mode_disabled_for_the_recheck_context` | test the log records safe mode disabled for the recheck context | $this->assertCount( 1, $log );<br>$this->assertSame( 'safe_mode_disabled', $log[0]['action'] ); |
| `test_the_log_message_names_the_queued_notification_only_with_a_recipient` | test the log message names the queued notification only with a recipient | $this->assertStringContainsString( 'Email notification was queued', $GLOBALS['updatronix_test_log'][0]['message'] );<br>$this->assertStringNotContainsString( 'Email notification was queued', $GLOBALS['updatronix_test_log'][0]['message'] ); |
| `test_is_compatible_wp_version_accepts_a_major_only_tested_value` | test is compatible wp version accepts a major only tested value | $this->assertTrue( Updatronix_Safe_Mode::is_compatible_wp_version( (object) array( 'tested' => '6' ) ), 'a major-only "tested" of 6 against WordPress 6.4 is compatible' );<br>$this->assertFalse( Updatronix_Safe_Mode::is_compatible_wp_version( (object) array( 'tested' => '5' ) ), 'a major-only "tested" of 5 against WordPress 6.4 is not' ); |
| `test_upgrader_plugin_batch_records_the_flag_from_the_option` | test upgrader plugin batch records the flag from the option | $this->assertCount( 1, $blocked );<br>$this->assertFalse( $blocked[0]['auto_update_disabled'], 'plug/plug.php is not in auto_update_plugins, so auto-update was not enabled' ); |
| `test_upgrader_theme_records_the_flag_from_the_option` | test upgrader theme records the flag from the option | $this->assertCount( 1, $blocked );<br>$this->assertFalse( $blocked[0]['auto_update_disabled'], 'mytheme is not in auto_update_themes, so auto-update was not enabled' ); |
| `test_upgrader_refresh_leaves_a_transient_without_last_checked_alone` | test upgrader refresh leaves a transient without last checked alone | $this->assertFalse( isset( $transient->last_checked ), 'the plugin refresh must not add a last_checked the transient did not have' );<br>$this->assertFalse( isset( $transient->last_checked ), 'the theme refresh must not add a last_checked the transient did not have' ); |

#### `tests/Unit/SafeModeSyntheticItemTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_plugin_synthetic_mock_in_auto_update_plugins_is_not_blocked` | test plugin synthetic mock in auto update plugins is not blocked | $this->assertSame( $update, $result, 'Synthetic mock items in auto_update_plugins must pass through unchanged.' );<br>$this->assertCount( 0, $this->blocked(), 'Synthetic mock items must not be logged as blocked.' );<br>$this->assertCount( 0, $this->pending(), 'Synthetic mock items must not be deferred for disable.' ); |
| `test_theme_synthetic_mock_in_auto_update_themes_is_not_blocked` | test theme synthetic mock in auto update themes is not blocked | $this->assertSame( $update, $result, 'Synthetic mock themes in auto_update_themes must pass through unchanged.' );<br>$this->assertCount( 0, $this->blocked(), 'Synthetic mock themes must not be logged as blocked.' );<br>$this->assertCount( 0, $this->pending(), 'Synthetic mock themes must not be deferred for disable.' ); |
| `test_plugin_not_in_auto_update_plugins_is_not_blocked_even_when_incompatible` | test plugin not in auto update plugins is not blocked even when incompatible | $this->assertSame( $update, $result, 'Real plugins outside auto_update_plugins must pass through unchanged.' );<br>$this->assertCount( 0, $this->blocked(), 'Real plugins outside auto_update_plugins must not be logged as blocked.' );<br>$this->assertCount( 0, $this->pending(), 'Real plugins outside auto_update_plugins must not be deferred for disable.' ); |
| `test_theme_not_in_auto_update_themes_is_not_blocked_even_when_incompatible` | test theme not in auto update themes is not blocked even when incompatible | $this->assertSame( $update, $result, 'Real themes outside auto_update_themes must pass through unchanged.' );<br>$this->assertCount( 0, $this->blocked(), 'Real themes outside auto_update_themes must not be logged as blocked.' );<br>$this->assertCount( 0, $this->pending(), 'Real themes outside auto_update_themes must not be deferred for disable.' ); |
| `test_filter_auto_update_plugin_empty_plugin_property` | test filter auto update plugin empty plugin property | $this->assertSame( $update, $result, 'Items with no plugin property must not be excluded or blocked.' );<br>$this->assertCount( 0, $this->blocked(), 'Items with no plugin property must not be logged as blocked.' ); |
| `test_filter_auto_update_plugin_blocks_when_update_false_and_auto_update_enabled` | test filter auto update plugin blocks when update false and auto update enabled | $this->assertFalse( $result, 'When plugin is incompatible and auto-update is enabled, the filter must return false even if $update is false.' );<br>$this->assertCount( 1, $this->blocked(), 'When plugin is incompatible and auto-update is enabled, record_blocked must be called.' );<br>$this->assertSame( array( array( 'plugin', $plugin_file, false ) ), $GLOBALS['updatronix_test_auto_updates_toggles'], 'When plugin is incompatible and auto-update is enabled, the native toggle must be disabled.' ); |
| `test_filter_auto_update_plugin_does_not_block_when_compatible_and_auto_update_enabled` | test filter auto update plugin does not block when compatible and auto update enabled | $this->assertSame( $update, $result, 'When plugin is compatible and auto-update is enabled, the filter must pass through $update unchanged.' );<br>$this->assertCount( 0, $this->blocked(), 'When plugin is compatible, record_blocked must not be called.' );<br>$this->assertCount( 0, $this->pending(), 'When plugin is compatible, defer_disable must not be called.' ); |
| `test_filter_auto_update_theme_blocks_when_update_false_and_auto_update_enabled` | test filter auto update theme blocks when update false and auto update enabled | $this->assertFalse( $result, 'When theme is incompatible and auto-update is enabled, the filter must return false even if $update is false.' );<br>$this->assertCount( 1, $this->blocked(), 'When theme is incompatible and auto-update is enabled, record_blocked must be called.' );<br>$this->assertSame( array( array( 'theme', 'real-theme', false ) ), $GLOBALS['updatronix_test_auto_updates_toggles'], 'When theme is incompatible and auto-update is enabled, the native toggle must be disabled.' ); |
| `test_filter_auto_update_theme_does_not_block_when_compatible_and_auto_update_enabled` | test filter auto update theme does not block when compatible and auto update enabled | $this->assertSame( $update, $result, 'When theme is compatible and auto-update is enabled, the filter must pass through $update unchanged.' );<br>$this->assertCount( 0, $this->blocked(), 'When theme is compatible, record_blocked must not be called.' );<br>$this->assertCount( 0, $this->pending(), 'When theme is compatible, defer_disable must not be called.' ); |
| `test_filter_auto_update_plugin_records_the_block_and_its_reason` | test filter auto update plugin records the block and its reason | $this->assertCount( 1, $blocked );<br>$this->assertTrue( $blocked[0]['auto_update_disabled'], 'the filter context disables auto-updates, and the record says so.' );<br>$this->assertSame( '1.8.16', $blocked[0]['version'] );<br>$this->assertSame( 'filter', $blocked[0]['context'] ); |
| `test_filter_auto_update_theme_records_the_block_and_its_reason` | test filter auto update theme records the block and its reason | $this->assertCount( 1, $blocked );<br>$this->assertTrue( $blocked[0]['auto_update_disabled'], 'the theme filter context disables auto-updates, and the record says so.' );<br>$this->assertStringContainsString( 'requires WordPress', $blocked[0]['reason'] ); |
| `test_filter_auto_update_plugin_reports_the_php_reason` | test filter auto update plugin reports the php reason | $this->assertCount( 1, $blocked );<br>$this->assertStringContainsString( 'requires PHP 99.0', $blocked[0]['reason'] ); |

#### `tests/Unit/UpdateCounterfactualGapsTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_wp_compatibility_equal_lower_higher_and_unknown` | test wp compatibility equal lower higher and unknown | self::assertTrue( Updatronix_Compatibility::is_compatible_wp( $tested, '6.4' ), 'Equal minor is compatible.' );<br>self::assertTrue( Updatronix_Compatibility::is_compatible_wp( $tested, '6.3' ), 'An older site is compatible.' );<br>self::assertFalse( Updatronix_Compatibility::is_compatible_wp( $tested, '6.5' ), 'A newer site is not.' );<br>self::assertFalse( Updatronix_Compatibility::is_compatible_wp( $tested, '7.0' ), 'A newer major is not.' ); |
| `test_php_compatibility_equal_lower_higher_and_missing` | test php compatibility equal lower higher and missing | self::assertTrue( Updatronix_Compatibility::is_compatible_php( $requires, '8.1' ), 'Equal versions are compatible.' );<br>self::assertTrue( Updatronix_Compatibility::is_compatible_php( $requires, '8.2' ), 'A newer PHP is compatible.' );<br>self::assertFalse( Updatronix_Compatibility::is_compatible_php( $requires, '8.0' ), 'An older PHP is not.' );<br>self::assertFalse( Updatronix_Compatibility::is_compatible_php( $legacy, '7.4' ), 'The legacy php_version field is read.' ); |
| `test_abandoned_detector_lookup_cap_and_cache_gates` | test abandoned detector lookup cap and cache gates | self::assertFalse( $detector->is_abandoned( 'core', 'core', true ), 'An unsupported type is never abandoned.' );<br>self::assertFalse( $detector->is_abandoned( 'plugin', 'no-lookup/no-lookup.php', false ) );<br>self::assertSame( 0, $this->lookup_count( $detector ), 'A forbidden lookup is not performed.' );<br>self::assertFalse( $detector->is_abandoned( 'plugin', 'cap-item/cap-item.php', true ), 'The lookup cap is bounded.' ); |
| `test_abandoned_detector_cached_date_boundaries` | test abandoned detector cached date boundaries | self::assertTrue( $detector->is_abandoned( 'plugin', 'old-cache/old-cache.php', false ) );<br>self::assertFalse( $detector->is_abandoned( 'plugin', 'no-date/no-date.php', false ) );<br>self::assertFalse( $detector->is_abandoned( 'plugin', 'empty-date/empty-date.php', false ) ); |
| `test_abandoned_cache_keeps_newest_and_preserves_keys` | test abandoned cache keeps newest and preserves keys | self::assertIsArray( $stored );<br>self::assertCount( Updatronix_Abandoned_Detector::MAX_CACHE, $stored );<br>self::assertArrayNotHasKey( 'plugin:old/old.php', $stored, 'The oldest timestamp is dropped under numeric ordering.' );<br>self::assertArrayHasKey( 'plugin:new-0/new-0.php', $stored, 'The cache keeps its type:id keys.' ); |
| `test_abandoned_policy_default_advisory_and_network_free` | test abandoned policy default advisory and network free | self::assertTrue( $result->allowed );<br>self::assertFalse( $result->metadata['abandoned'], 'The stored flag disables the advisory.' );<br>self::assertArrayNotHasKey( 'advisory', $result->metadata );<br>self::assertTrue( $result->allowed, 'The advisory never denies.' ); |
| `test_abandoned_policy_defaults_enabled_when_key_is_missing` | test abandoned policy defaults enabled when key is missing | self::assertTrue( $result->allowed );<br>self::assertTrue( $result->metadata['abandoned'], 'A missing flag defaults to advisory on.' ); |
| `test_native_guards_core_constant_and_item_scope` | test native guards core constant and item scope | self::assertTrue( $core_disabled->evaluate( $this->job() )->allowed, 'A plugin is unaffected by the core toggle.' );<br>self::assertSame( 'environment_disabled', $core_disabled->evaluate( $this->job( array( 'item_type' => 'core', 'item_id' => 'core', ) ) )->code );<br>self::assertSame( 'environment_disabled', $constant->evaluate( $this->job( array( 'item_type' => 'core', 'item_id' => 'core', ) ) )->code, 'WP_AUTO_UPDATE_CORE=false denies core.' );<br>self::assertTrue( $constant->evaluate( $this->job() )->allowed, 'WP_AUTO_UPDATE_CORE=false does not deny a plugin.' ); |
| `test_policy_result_severity_and_allow_metadata` | test policy result severity and allow metadata | self::assertSame( Updatronix_Policy_Result::SEVERITY_ERROR, Updatronix_Policy_Result::deny( 'p', 'c', 'm', 'error' )->severity );<br>self::assertSame( Updatronix_Policy_Result::SEVERITY_INFO, Updatronix_Policy_Result::deny( 'p', 'c', 'm', 'info' )->severity );<br>self::assertSame( Updatronix_Policy_Result::SEVERITY_WARNING, Updatronix_Policy_Result::deny( 'p', 'c', 'm', 'bogus' )->severity, 'An unknown severity falls back to warning.' );<br>self::assertTrue( $allow->allowed ); |
| `test_policy_chain_all_allowed_has_no_result` | test policy chain all allowed has no result | self::assertTrue( $result['allowed'] );<br>self::assertNull( $result['result'], 'No denial means no winning result.' );<br>self::assertCount( 1, $result['results'] ); |
| `test_version_control_detector_roots_and_registration` | test version control detector roots and registration | self::assertSame( rtrim( WP_PLUGIN_DIR, '/\\' ) . '/plug', $detector->get_item_root( 'plugin', 'plug/plug.php' ) );<br>self::assertNull( $detector->get_item_root( 'plugin', 'single.php' ), 'A single-file plugin has no item root.' );<br>self::assertSame( '/tmp/updatronix-themes/mytheme', $detector->get_item_root( 'theme', 'mytheme' ) );<br>self::assertNull( $detector->get_item_root( 'theme', 'mytheme' ), 'An unknown theme root has no path.' ); |
| `test_version_control_filter_is_boolean` | test version control filter is boolean | self::assertTrue( $detector->is_composer_controlled( 'plugin', $id ), 'A truthy filter value survives the cast.' ); |
| `test_version_control_policy_reads_default_lock` | test version control policy reads default lock | self::assertFalse( $unlocked->evaluate( $this->job() )->metadata['composer_lock'], 'The stored false disables the Composer lock.' );<br>self::assertTrue( $locked->evaluate( $this->job() )->metadata['composer_lock'], 'The default lock is on.' ); |
| `test_compatibility_policy_metadata_preserves_strings` | test compatibility policy metadata preserves strings | self::assertTrue( $result->allowed );<br>self::assertSame( '8.1', $result->metadata['requires_php'] );<br>self::assertSame( '8.3', $result->metadata['site_php'] );<br>self::assertFalse( $result->allowed ); |
| `test_abandoned_scan_records_date_days_and_log` | test abandoned scan records date days and log | self::assertArrayHasKey( 'plugin:scan-item/scan-item.php', $flagged );<br>self::assertSame( $old, $flagged['plugin:scan-item/scan-item.php']['last_updated'] );<br>self::assertGreaterThanOrEqual( 400, $flagged['plugin:scan-item/scan-item.php']['days'] );<br>self::assertCount( 1, $GLOBALS['updatronix_test_log'] ); |
| `test_policy_boolean_filters_are_cast` | test policy boolean filters are cast | self::assertTrue( ( new Updatronix_Delay_Policy( $truthy ) )->evaluate( $this->job() )->allowed );<br>self::assertTrue( ( new Updatronix_License_Policy( $truthy ) )->evaluate( $this->job() )->allowed );<br>self::assertTrue( ( new Updatronix_Chain_Dependency_Policy( $truthy ) )->evaluate( $this->job( array( 'chain_id' => 'chain-1', 'sequence' => 2, ) ) )->allowed );<br>self::assertTrue( ( new Updatronix_Engine_Policy( $truthy, $truthy ) )->evaluate( $this->job() )->allowed ); |
| `test_version_control_native_boolean_and_symlink_escape` | test version control native boolean and symlink escape | self::assertTrue( $native->is_native_vcs( 'plugin', 'plug/plug.php' ) );<br>self::assertFalse( $detector->is_composer_controlled( 'plugin', $id ), 'A symlinked installed.json is rejected.' );<br>self::assertFalse( $detector->is_composer_controlled( 'plugin', $id ), 'A vendor symlink outside the item root is rejected.' ); |
| `test_version_control_policy_missing_lock_key_defaults_on` | test version control policy missing lock key defaults on | self::assertTrue( $policy->evaluate( $this->job() )->metadata['composer_lock'], 'A missing lock key defaults on.' ); |

#### `tests/Unit/UpdateEngineGatingUnitTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_kill_switch` | test kill switch | self::assertTrue( Updatronix_Update_Engine_Bootstrap::is_enabled() );<br>self::assertFalse( Updatronix_Update_Engine_Bootstrap::is_enabled() ); |
| `test_registration_is_inert_by_default` | test registration is inert by default | self::assertFalse( Updatronix_Update_Engine_Bootstrap::register() );<br>self::assertFalse( has_filter( 'updatronix_log_action_types', array( Updatronix_Update_Engine_Bootstrap::class, 'allow_action_types' ) ) ); |
| `test_can_manage_matrix` | test can manage matrix | self::assertFalse( Updatronix_Update_Engine_Bootstrap::can_manage( true, false, true, true ) );<br>self::assertTrue( Updatronix_Update_Engine_Bootstrap::can_manage( true, true, true, true ) );<br>self::assertFalse( Updatronix_Update_Engine_Bootstrap::can_manage( true, true, false, true ) );<br>self::assertFalse( Updatronix_Update_Engine_Bootstrap::can_manage( false, false, false, true ) ); |
| `test_main_site_guard` | test main site guard | self::assertTrue( Updatronix_Update_Engine_Bootstrap::is_main_site() ); |
| `test_allow_action_types` | test allow action types | self::assertContains( 'update', $types );<br>self::assertContains( 'prevented', $types );<br>self::assertContains( 'auto_update_reenabled', $types );<br>self::assertSame( 1, count( array_keys( $types, 'auto_update_reenabled', true ) ) ); |

#### `tests/Unit/UpdateFlowPlannerTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_topological_order_is_stable` | test topological order is stable | self::assertSame( array(), $plan->errors );<br>self::assertSame( array( 'plugin:a', 'plugin:d', 'plugin:b', 'plugin:c' ), $this->job_ids( $plan ) );<br>self::assertSame( array( 1, 2, 3, 4 ), array_map( static fn ( Updatronix_Update_Job $job ): int => $job->priority, $plan->jobs ) );<br>self::assertSame( 'a', $plan->jobs[0]->chain_id ); |
| `test_cross_chain_duplicate_is_deduplicated` | test cross chain duplicate is deduplicated | self::assertSame( array( 'plugin:a', 'plugin:b', 'plugin:c' ), $this->job_ids( $plan ) );<br>self::assertCount( 1, $plan->deduplicated );<br>self::assertSame( 'plugin', $plan->deduplicated[0]['item']['type'] );<br>self::assertSame( 'a', $plan->deduplicated[0]['item']['id'] ); |
| `test_unknown_item_is_blocked` | test unknown item is blocked | self::assertSame( array( 'plugin:a' ), $this->job_ids( $plan ) );<br>self::assertCount( 1, $plan->blocked );<br>self::assertSame( 'flow_item_unknown', $plan->blocked[0]['reason'] );<br>self::assertSame( 'catalogue', $plan->blocked[0]['policy'] ); |
| `test_cycle_is_rejected` | test cycle is rejected | self::assertSame( 'failed', $plan->status );<br>self::assertSame( 'flow_cycle', $plan->errors[0]['code'] );<br>self::assertSame( array(), $plan->jobs );<br>self::assertSame( array( 'plugin:a', 'plugin:b', 'plugin:a' ), $plan->errors[0]['loop'] ); |
| `test_duplicate_and_chain_limit` | test duplicate and chain limit | self::assertSame( 'flow_steps_duplicate', $duplicate->flow_errors[0]['code'] );<br>self::assertSame( 'flow_no_valid_chains', $duplicate->errors[0]['code'] );<br>self::assertSame( 'flow_chain_limit', $limited->errors[0]['code'] ); |
| `test_core_must_be_last` | test core must be last | self::assertSame( 'flow_core_last', $plan->flow_errors[0]['code'] );<br>self::assertSame( 'flow_no_valid_chains', $plan->errors[0]['code'] ); |
| `test_missing_offer_is_skipped` | test missing offer is skipped | self::assertSame( array( 'plugin:a' ), $this->job_ids( $plan ) );<br>self::assertCount( 1, $plan->skipped );<br>self::assertSame( 'no_pending_offer', $plan->skipped[0]['reason'] );<br>self::assertSame( 'a', $plan->skipped[0]['chain_id'] ); |
| `test_default_context_includes_disabled_chains` | test default context includes disabled chains | self::assertSame( array( 'plugin:a', 'plugin:b', 'plugin:c', 'plugin:d' ), $this->job_ids( $plan ) ); |
| `test_enabled_only_keeps_an_enabled_chain` | test enabled only keeps an enabled chain | self::assertSame( array( 'plugin:a', 'plugin:b' ), $this->job_ids( $plan ) );<br>self::assertSame( array(), $plan->errors ); |
| `test_flow_graph_metadata_is_emitted` | test flow graph metadata is emitted | self::assertSame( array( 'plugin:a', 'plugin:c', 'plugin:b' ), array_keys( $plan->flow_nodes ) );<br>self::assertSame( array( 'plugin:a', 'plugin:c' ), $plan->flow_nodes['plugin:b']['predecessors'] );<br>self::assertSame( array( 'plugin:b' ), $plan->flow_nodes['plugin:a']['successors'] );<br>self::assertSame( array( 'plugin:b' ), $plan->flow_edges['plugin:a'] ); |
| `test_flow_snapshot_is_emitted` | test flow snapshot is emitted | self::assertSame( array( 'plugin:a', 'plugin:b' ), $plan->flow_snapshot['chains']['a']['steps'] );<br>self::assertTrue( $plan->flow_snapshot['chains']['a']['enabled'] );<br>self::assertSame( array( 'plugin:b' ), $plan->flow_snapshot['edges']['plugin:a'] );<br>self::assertSame( 2, $plan->flow_snapshot['nodes']['plugin:b'][0]['sequence'] ); |
| `test_per_chain_invalid_does_not_block_valid_chains` | test per chain invalid does not block valid chains | self::assertSame( array( 'plugin:a', 'plugin:b' ), $this->job_ids( $plan ) );<br>self::assertSame( 'flow_core_last', $plan->flow_errors[0]['code'] );<br>self::assertSame( 'bad', $plan->flow_errors[0]['chain_id'] );<br>self::assertSame( array(), $plan->errors ); |
| `test_target_conflict_is_recorded` | test target conflict is recorded | self::assertContains( 'flow_target_conflict', $codes );<br>self::assertSame( '2.0.0', $plan->flow_nodes['plugin:a']['target'] ); |
| `test_overflow_is_reported` | test overflow is reported | self::assertSame( 60, $plan->flow_overflow['requested'] );<br>self::assertSame( 50, $plan->flow_overflow['limit'] );<br>self::assertCount( 10, $plan->flow_overflow['dropped'] );<br>self::assertCount( 60, $plan->flow_nodes ); |
| `test_shared_node_keeps_every_chain_occurrence` | test shared node keeps every chain occurrence | self::assertCount( 1, array_filter( $plan->flow_nodes, static fn ( array $node ): bool => 'plugin:b' === $node['type'] . ':' . $node['id'] ) );<br>self::assertSame( array( 'one', 'two' ), array_column( $plan->flow_nodes['plugin:b']['chain_ids'], 'chain_id' ) );<br>self::assertSame( array( 'plugin:a', 'plugin:c' ), $plan->flow_nodes['plugin:b']['predecessors'] );<br>self::assertCount( 1, $plan->deduplicated ); |

#### `tests/Unit/UpdateFlowValidationTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_valid_chains_are_normalized` | test valid chains are normalized | $this->assertIsArray( $result );<br>$this->assertCount( 1, $result );<br>$this->assertSame( 'paymentsfirst', $result[0]['id'] );<br>$this->assertSame( 'Payments first', $result[0]['name'] ); |
| `test_empty_name_is_rejected` | test empty name is rejected | $this->assertInstanceOf( WP_Error::class, $result );<br>$this->assertSame( 'flow_name_required', $result->get_error_code() ); |
| `test_chain_needs_two_steps` | test chain needs two steps | $this->assertInstanceOf( WP_Error::class, $result );<br>$this->assertSame( 'flow_steps_min', $result->get_error_code() ); |
| `test_duplicate_steps_are_rejected` | test duplicate steps are rejected | $this->assertInstanceOf( WP_Error::class, $result );<br>$this->assertSame( 'flow_steps_duplicate', $result->get_error_code() ); |
| `test_cross_chain_cycle_is_rejected` | test cross chain cycle is rejected | $this->assertInstanceOf( WP_Error::class, $result );<br>$this->assertSame( 'flow_cycle', $result->get_error_code() ); |
| `test_core_must_be_last` | test core must be last | $this->assertInstanceOf( WP_Error::class, $result );<br>$this->assertSame( 'flow_core_last', $result->get_error_code() ); |
| `test_chain_limit` | test chain limit | $this->assertInstanceOf( WP_Error::class, $result );<br>$this->assertSame( 'flow_chain_limit', $result->get_error_code() ); |
| `test_step_limit` | test step limit | $this->assertInstanceOf( WP_Error::class, $result );<br>$this->assertSame( 'flow_step_limit', $result->get_error_code() ); |
| `test_installed_item_validation` | test installed item validation | $this->assertTrue( Updatronix_Update_Flow::validate_installed_items( $chains ) );<br>$this->assertInstanceOf( WP_Error::class, $result );<br>$this->assertSame( 'flow_item_unknown', $result->get_error_code() );<br>$this->assertStringContainsString( 'plugin:missing/missing.php', $result->get_error_message() ); |
| `test_storage_migration_is_one_shot` | test storage migration is one shot | $this->assertTrue( Updatronix_Update_Flow::maybe_migrate() );<br>$this->assertSame( 1, $stored['version'] );<br>$this->assertCount( 1, $stored['chains'] );<br>$this->assertFalse( Updatronix_Update_Flow::maybe_migrate(), 'The migration is one-shot.' ); |
| `test_migration_rejects_non_array_and_associative_values` | test migration rejects non array and associative values | $this->assertFalse( Updatronix_Update_Flow::maybe_migrate() );<br>$this->assertSame( 'not-an-array', get_site_option( Updatronix_Update_Flow::OPTION ) );<br>$this->assertSame( array( 'foo' => 'bar' ), get_site_option( Updatronix_Update_Flow::OPTION ) ); |
| `test_future_schema_version_is_not_read` | test future schema version is not read | $this->assertSame( array(), Updatronix_Update_Flow::get_saved_chains() ); |
| `test_legacy_list_is_not_read` | test legacy list is not read | $this->assertSame( array(), Updatronix_Update_Flow::get_saved_chains() ); |
| `test_flow_routes_are_registered` | test flow routes are registered | $this->assertSame( 2, count( $routes ), 'The two run routes are removed in Step 4.' );<br>$this->assertSame( '/flow/chains', $routes[0]['route'] );<br>$this->assertSame( '/flow/items', $routes[1]['route'] ); |

#### `tests/Unit/UpdateJobTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_status_sets` | test status sets | self::assertContains( 'pending', Updatronix_Update_Job::STATUSES );<br>self::assertContains( 'canceled', Updatronix_Update_Job::STATUSES );<br>self::assertNotContains( 'skipped', Updatronix_Update_Job::STATUSES, 'Skipped is a plan fact, not a queue status.' );<br>self::assertContains( $status, Updatronix_Update_Job::STATUSES ); |
| `test_status_classification` | test status classification | self::assertTrue( $job->is_active() );<br>self::assertFalse( $job->is_terminal() );<br>self::assertTrue( $job->is_barrier() );<br>self::assertFalse( $job->is_active() ); |
| `test_allowed_transitions` | test allowed transitions | self::assertTrue( $job->can_transition_to( Updatronix_Update_Job::STATUS_CLAIMED ) );<br>self::assertTrue( $job->can_transition_to( Updatronix_Update_Job::STATUS_CANCELED ) );<br>self::assertFalse( $job->can_transition_to( Updatronix_Update_Job::STATUS_SUCCEEDED ) );<br>self::assertTrue( $job->can_transition_to( Updatronix_Update_Job::STATUS_PENDING ) ); |
| `test_can_retry` | test can retry | self::assertTrue( $job->can_retry() );<br>self::assertFalse( $job->can_retry() ); |
| `test_job_key_is_origin_independent` | test job key is origin independent | self::assertSame( $automatic->compute_job_key(), $flow->compute_job_key() );<br>self::assertSame( hash( 'sha256', '7\|plugin\|foo/foo.php\|2.0.0\|shared' ), $automatic->compute_job_key() );<br>self::assertNotSame( $automatic->compute_job_key(), $newer->compute_job_key() ); |
| `test_origin_scope` | test origin scope | self::assertSame( 'automatic', ( new Updatronix_Update_Job() )->origin_scope() );<br>self::assertSame( 'manual', ( new Updatronix_Update_Job( array( 'origin' => 'manual' ) ) )->origin_scope() );<br>self::assertSame( 'flow:payments', ( new Updatronix_Update_Job( array( 'origin' => 'flow', 'chain_id' => 'payments', ) ) )->origin_scope() ); |
| `test_from_row` | test from row | self::assertSame( 42, $job->id );<br>self::assertSame( 2, $job->attempts );<br>self::assertNull( $job->chain_id );<br>self::assertNull( $job->log_id ); |
| `test_from_row_casts_every_numeric_field_to_int` | test from row casts every numeric field to int | self::assertSame( 17, $job->id );<br>self::assertSame( 3, $job->site_id );<br>self::assertSame( 9, $job->sequence );<br>self::assertSame( 4, $job->priority ); |
| `test_item_type_normalization` | test item type normalization | self::assertSame( 'plugin', Updatronix_Update_Job::normalize_item_type( 'PLUGIN' ) );<br>self::assertSame( 'theme', Updatronix_Update_Job::normalize_item_type( ' theme ' ) );<br>self::assertSame( 'core', Updatronix_Update_Job::normalize_item_type( 'core' ) );<br>self::assertSame( 'plugin', Updatronix_Update_Job::normalize_item_type( 'widget' ) ); |

#### `tests/Unit/UpdatePassGateTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_selects_one_item_and_denies_the_rest` | test selects one item and denies the rest | self::assertTrue( $first, 'The selected item keeps its incoming true.' );<br>self::assertNotNull( $pass );<br>self::assertSame( Updatronix_Update_Pass::STATE_RUNNING, $pass->node( 'plugin:alpha/alpha.php' )['state'] );<br>self::assertSame( 1, $pass->node( 'plugin:alpha/alpha.php' )['attempts'] ); |
| `test_gate_never_returns_true_for_a_null_incoming_value` | test gate never returns true for a null incoming value | self::assertNull( $result, 'The incoming null is returned unchanged.' );<br>self::assertSame( Updatronix_Update_Pass::STATE_NOT_MANAGED, $node['state'] ); |
| `test_vetoed_item_keeps_false_and_records_the_reason` | test vetoed item keeps false and records the reason | self::assertFalse( $result );<br>self::assertSame( 1, $node['attempts'], 'The veto marks the attempt so the pass moves on.' );<br>self::assertSame( Updatronix_Update_Pass::STATE_OFFER_BLOCKED, $node['state'] );<br>self::assertSame( 'native_policy_refused', $node['reason_code'] ); |
| `test_locked_node_is_not_selected` | test locked node is not selected | self::assertFalse( Updatronix_Update_Pass_Gate::filter_plugin( true, (object) array( 'plugin' => 'alpha/alpha.php' ) ) );<br>self::assertSame( Updatronix_Update_Pass::STATE_LOCKED, Updatronix_Update_Pass::load()->node( 'plugin:alpha/alpha.php' )['state'] ); |
| `test_item_outside_the_order_is_denied` | test item outside the order is denied | self::assertFalse( Updatronix_Update_Pass_Gate::filter_plugin( true, (object) array( 'plugin' => 'new/new.php' ) ) ); |
| `test_bypass_returns_the_incoming_value` | test bypass returns the incoming value | self::assertTrue( Updatronix_Update_Pass_Gate::filter_plugin( true, (object) array( 'plugin' => 'alpha/alpha.php' ) ) );<br>self::assertTrue( Updatronix_Update_Pass_Gate::filter_plugin( true, (object) array( 'plugin' => 'bravo/bravo.php' ) ), 'The bypass runs the native loop unchanged.' ); |
| `test_no_pass_returns_the_incoming_value` | test no pass returns the incoming value | self::assertTrue( Updatronix_Update_Pass_Gate::filter_plugin( true, (object) array( 'plugin' => 'alpha/alpha.php' ) ) );<br>self::assertSame( '', Updatronix_Update_Pass_Gate::selection()['key'] ); |
| `test_translations_are_not_gated` | test translations are not gated | self::assertFalse( has_filter( 'auto_update_translation' ), 'Translations stay native and last.' );<br>self::assertNotFalse( has_filter( 'auto_update_plugin', array( 'Updatronix_Update_Pass_Gate', 'filter_plugin' ) ) );<br>self::assertNotFalse( has_filter( 'auto_update_theme', array( 'Updatronix_Update_Pass_Gate', 'filter_theme' ) ) );<br>self::assertNotFalse( has_filter( 'auto_update_core', array( 'Updatronix_Update_Pass_Gate', 'filter_core' ) ) ); |
| `test_executing_global_marks_the_pass` | test executing global marks the pass | self::assertNotNull( $pass );<br>self::assertSame( $pass->id(), $GLOBALS['updatronix_pro_update_pass_executing'] ?? '' );<br>self::assertArrayNotHasKey( 'updatronix_pro_update_pass_executing', $GLOBALS ); |

#### `tests/Unit/UpdatePassOrderTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_native_order_is_plugins_then_themes_then_core` | test native order is plugins then themes then core | self::assertSame( array( 'plugin:alpha/alpha.php', 'plugin:bravo/bravo.php', 'theme:beta', 'core:wordpress' ), $result['order'] );<br>self::assertSame( '1.1.0', $result['nodes']['plugin:alpha/alpha.php']['target'] );<br>self::assertSame( Updatronix_Update_Pass::STATE_OFFER_ELIGIBLE, $result['nodes']['core:wordpress']['state'] );<br>self::assertSame( 0, $result['overflow'] ); |
| `test_flow_nodes_come_first_and_dedupe` | test flow nodes come first and dedupe | self::assertSame( array( 'plugin:gamma/gamma.php', 'plugin:alpha/alpha.php', 'plugin:delta/delta.php' ), $result['order'] );<br>self::assertSame( array( 'plugin:gamma/gamma.php' ), $result['nodes']['plugin:alpha/alpha.php']['predecessors'] );<br>self::assertSame( 'payments', $result['flow_snapshot']['plugin:gamma/gamma.php']['chain'] );<br>self::assertSame( 2, $result['flow_snapshot']['plugin:alpha/alpha.php']['sequence'] ); |
| `test_locked_item_is_not_schedulable` | test locked item is not schedulable | self::assertSame( Updatronix_Update_Pass::STATE_LOCKED, $node['state'] );<br>self::assertSame( 'version_locked_native', $node['reason_code'] );<br>self::assertSame( 'Git', $node['manager'] );<br>self::assertNotNull( $pass ); |
| `test_not_managed_item_is_kept_but_not_schedulable` | test not managed item is kept but not schedulable | self::assertSame( Updatronix_Update_Pass::STATE_NOT_MANAGED, $result['nodes']['plugin:alpha/alpha.php']['state'] ); |
| `test_missing_offer_is_not_a_node` | test missing offer is not a node | self::assertSame( array(), $result['order'] );<br>self::assertSame( array(), $result['nodes'] ); |
| `test_stale_offer_is_unknown` | test stale offer is unknown | self::assertSame( Updatronix_Update_Pass::STATE_UNKNOWN_OFFER, $result['nodes']['plugin:alpha/alpha.php']['state'] ); |
| `test_overflow_is_reported` | test overflow is reported | self::assertCount( Updatronix_Update_Pass::MAX_NODES, $result['order'] );<br>self::assertSame( 5, $result['overflow'] ); |
| `test_order_filter_can_exclude_a_node` | test order filter can exclude a node | self::assertSame( array( 'plugin:alpha/alpha.php' ), $result['order'] );<br>self::assertArrayNotHasKey( 'plugin:bravo/bravo.php', $result['nodes'] ); |
| `test_removed_item_is_not_installed` | test removed item is not installed | self::assertSame( Updatronix_Update_Pass::STATE_NOT_INSTALLED, $result['nodes']['plugin:alpha/alpha.php']['state'] ); |
| `test_flow_plan_nodes_come_first` | test flow plan nodes come first | self::assertSame( array( 'plugin:gamma/gamma.php', 'plugin:alpha/alpha.php', 'plugin:delta/delta.php' ), $result['order'] );<br>self::assertSame( Updatronix_Update_Pass::STATE_OFFER_HELD, $result['nodes']['plugin:gamma/gamma.php']['state'] );<br>self::assertSame( time() + 3600, $result['nodes']['plugin:gamma/gamma.php']['eligible_at'] );<br>self::assertSame( Updatronix_Update_Pass::STATE_LOCKED, $result['nodes']['plugin:alpha/alpha.php']['state'] ); |
| `test_flow_node_without_an_offer_is_kept` | test flow node without an offer is kept | self::assertSame( array( 'plugin:gone/gone.php' ), $result['order'] );<br>self::assertSame( Updatronix_Update_Pass::STATE_NOT_INSTALLED, $result['nodes']['plugin:gone/gone.php']['state'] ); |
| `test_core_position_and_translations` | test core position and translations | self::assertSame( array( 'plugin:a/a.php', 'core:wordpress' ), $result['order'] );<br>self::assertArrayNotHasKey( 'translation:a', $result['nodes'] ); |
| `test_flow_overflow_names_dropped_keys` | test flow overflow names dropped keys | self::assertCount( Updatronix_Update_Pass::MAX_NODES, $result['order'] );<br>self::assertSame( 5, $result['overflow'] );<br>self::assertSame( 50, $result['flow_overflow']['limit'] );<br>self::assertSame( 55, $result['flow_overflow']['requested'] ); |
| `test_flow_errors_are_returned` | test flow errors are returned | self::assertSame( array( 'plugin:alpha/alpha.php' ), $result['order'] );<br>self::assertSame( 'flow_core_last', $result['flow_errors'][0]['code'] ); |
| `test_native_fallback_without_flow` | test native fallback without flow | self::assertSame( array( 'plugin:alpha/alpha.php', 'theme:beta', 'core:wordpress' ), $result['order'] ); |

#### `tests/Unit/UpdatePassTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_create_and_load_round_trip` | test create and load round trip | self::assertNotNull( $pass );<br>self::assertTrue( Updatronix_Update_Pass::exists() );<br>self::assertArrayHasKey( Updatronix_Update_Pass::OPTION, $GLOBALS['updatronix_test_site_options'] );<br>self::assertFalse( $GLOBALS['updatronix_test_option_autoload'][ Updatronix_Update_Pass::OPTION ], 'The pass option must not autoload.' ); |
| `test_fifty_one_nodes_are_rejected` | test fifty one nodes are rejected | self::assertNull( Updatronix_Update_Pass::create( $order, $nodes ) );<br>self::assertArrayNotHasKey( Updatronix_Update_Pass::OPTION, $GLOBALS['updatronix_test_site_options'] );<br>self::assertFalse( Updatronix_Update_Pass::exists() ); |
| `test_invalid_state_is_rejected` | test invalid state is rejected | self::assertSame( array( 'nodes' => 'invalid_state' ), Updatronix_Update_Pass::validate( $data ) ); |
| `test_unknown_top_level_key_is_rejected` | test unknown top level key is rejected | self::assertSame( array( 'attempted' => 'unknown_key' ), Updatronix_Update_Pass::validate( $data ) ); |
| `test_invalid_pass_id_is_rejected` | test invalid pass id is rejected | self::assertSame( array( 'pass_id' => 'invalid_pass_id' ), Updatronix_Update_Pass::validate( $data ) ); |
| `test_delete_removes_option` | test delete removes option | self::assertNotNull( $pass );<br>self::assertTrue( $pass->delete() );<br>self::assertArrayNotHasKey( Updatronix_Update_Pass::OPTION, $GLOBALS['updatronix_test_site_options'] );<br>self::assertFalse( Updatronix_Update_Pass::exists() ); |
| `test_option_is_absent_when_no_pass_exists` | test option is absent when no pass exists | self::assertNull( Updatronix_Update_Pass::load() );<br>self::assertFalse( Updatronix_Update_Pass::exists() );<br>self::assertArrayNotHasKey( Updatronix_Update_Pass::OPTION, $GLOBALS['updatronix_test_site_options'] ); |
| `test_summary_counts_each_state` | test summary counts each state | self::assertNotNull( $pass );<br>self::assertSame( 5, $summary['total'] );<br>self::assertSame( 1, $summary['done'] );<br>self::assertSame( 1, $summary['delayed'] ); |
| `test_schedulable_requires_eligible_and_done_predecessor` | test schedulable requires eligible and done predecessor | self::assertNotNull( $pass );<br>self::assertSame( array( 'plugin:alpha/alpha.php' ), $pass->schedulable() );<br>self::assertSame( array( 'plugin:gamma/gamma.php' ), $pass->schedulable() );<br>self::assertSame( array(), $pass->schedulable() ); |
| `test_blocking_states_are_never_schedulable` | test blocking states are never schedulable | self::assertNotNull( $pass );<br>self::assertSame( array(), $pass->schedulable() ); |
| `test_failed_predecessor_blocks_only_its_descendants` | test failed predecessor blocks only its descendants | self::assertNotNull( $pass );<br>self::assertSame( array( 'plugin:c/c.php' ), $pass->schedulable(), 'The independent branch continues.' );<br>self::assertNotContains( 'plugin:b/b.php', $pass->schedulable() ); |
| `test_a_failed_node_is_not_retried_in_the_pass` | test a failed node is not retried in the pass | self::assertNotNull( $pass );<br>self::assertSame( array( 'plugin:a/a.php' ), $pass->schedulable() );<br>self::assertSame( array(), $pass->schedulable() );<br>self::assertNotNull( $next ); |
| `test_waiting_node_keeps_its_facts` | test waiting node keeps its facts | self::assertNotNull( $pass );<br>self::assertSame( 'plugin:a/a.php', $node['waiting_for'] );<br>self::assertSame( 'flow_predecessor_locked', $node['reason_code'] );<br>self::assertTrue( $node['blocking'] ); |
| `test_mark_attempted_is_bounded` | test mark attempted is bounded | self::assertNotNull( $pass );<br>self::assertTrue( $pass->mark_attempted( 'plugin:alpha/alpha.php' ) );<br>self::assertSame( 1, $pass->node( 'plugin:alpha/alpha.php' )['attempts'] );<br>self::assertSame( 'plugin:alpha/alpha.php', $pass->last_item() ); |
| `test_is_expired_needs_old_heartbeat_and_due_check` | test is expired needs old heartbeat and due check | self::assertNotNull( $fresh );<br>self::assertFalse( $fresh->is_expired() );<br>self::assertNotNull( $old_heartbeat );<br>self::assertFalse( $old_heartbeat->is_expired(), 'A due check is required as well as an old heartbeat.' ); |
| `test_enabled_filter_is_the_emergency_bypass` | test enabled filter is the emergency bypass | self::assertTrue( Updatronix_Update_Pass::is_enabled() );<br>self::assertFalse( Updatronix_Update_Pass::is_enabled() ); |
| `test_reconcile_outcome_maps_installed_version` | test reconcile outcome maps installed version | self::assertSame( Updatronix_Update_Pass::STATE_SUCCEEDED, Updatronix_Update_Pass::reconcile_outcome( $node, '1.1.0', true ) );<br>self::assertSame( Updatronix_Update_Pass::STATE_FAILED, Updatronix_Update_Pass::reconcile_outcome( $node, '1.0.0', true ) );<br>self::assertSame( Updatronix_Update_Pass::STATE_UNCERTAIN, Updatronix_Update_Pass::reconcile_outcome( $node, '1.0.5', true ) );<br>self::assertSame( Updatronix_Update_Pass::STATE_NOT_INSTALLED, Updatronix_Update_Pass::reconcile_outcome( $node, '', false ) ); |

#### `tests/Unit/UpdatePolicyPlanTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_policy_result` | test policy result | self::assertTrue( $allow->allowed );<br>self::assertSame( 'allowed', $allow->code );<br>self::assertSame( 'info', $allow->severity );<br>self::assertSame( 'license', $allow->policy ); |
| `test_policy_result_round_trip` | test policy result round trip | self::assertSame( $result->to_array(), $again->to_array() ); |
| `test_plan_deduplicates` | test plan deduplicates | self::assertTrue( $plan->add_job( $first ) );<br>self::assertFalse( $plan->add_job( $second ) );<br>self::assertCount( 1, $plan->jobs );<br>self::assertCount( 1, $plan->deduplicated ); |
| `test_plan_facts` | test plan facts | self::assertSame( 'run-2', $array['run_id'] );<br>self::assertSame( 'automatic', $array['origin'] );<br>self::assertSame( 'planned', $array['status'] );<br>self::assertSame( 'no_pending_offer', $array['skipped'][0]['reason'] ); |
| `test_plan_origin_fallback` | test plan origin fallback | self::assertSame( 'automatic', ( new Updatronix_Update_Plan( 'run-3', 'unknown' ) )->origin );<br>self::assertSame( 'flow', ( new Updatronix_Update_Plan( 'run-4', 'flow' ) )->origin ); |
| `test_plan_created_at_explicit_and_fallback` | test plan created at explicit and fallback | self::assertSame( '2026-01-02 03:04:05', $explicit->created_at );<br>self::assertNotSame( '', $plan->created_at );<br>self::assertGreaterThanOrEqual( $before, $plan->created_at );<br>self::assertLessThanOrEqual( $after, $plan->created_at ); |

#### `tests/Unit/UpdateShieldPoliciesTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_engine_chain_order_and_short_circuit` | test engine chain order and short circuit | self::assertSame( array( 'license', 'engine_enabled', 'native_guards', 'version_control', 'wp_compatibility', 'php_compatibility', 'free_delay', 'abandoned', 'pending_offer', 'chain_dependency', ), array_map( static fn …<br>self::assertTrue( $result['allowed'] );<br>self::assertCount( 10, $result['results'] );<br>self::assertFalse( $result['allowed'] ); |
| `test_license_policy` | test license policy | self::assertTrue( $allow->evaluate( $this->job() )->allowed );<br>self::assertFalse( $result->allowed );<br>self::assertSame( 'license_inactive', $result->code ); |
| `test_engine_policy` | test engine policy | self::assertTrue( $policy->evaluate( $this->job() )->allowed );<br>self::assertTrue( $policy->evaluate( $this->job( array( 'origin' => Updatronix_Update_Job::ORIGIN_FLOW ) ) )->allowed );<br>self::assertSame( 'engine_disabled', $unavailable->evaluate( $this->job( array( 'origin' => Updatronix_Update_Job::ORIGIN_FLOW ) ) )->code );<br>self::assertSame( 'auto_update_disabled', $opted_out->evaluate( $this->job() )->code ); |
| `test_native_guards` | test native guards | self::assertSame( 'environment_disabled', $blocked->evaluate( $this->job() )->code );<br>self::assertSame( 'filesystem_unavailable', $fs->evaluate( $this->job() )->code );<br>self::assertTrue( $core->evaluate( $this->job() )->allowed );<br>self::assertFalse( $core->evaluate( $this->job( array( 'item_type' => 'core', 'item_id' => 'core', ) ) )->allowed ); |
| `test_wp_compatibility` | test wp compatibility | self::assertFalse( $result->allowed );<br>self::assertSame( 'wp_incompatible', $result->code );<br>self::assertStringContainsString( 'last tested version is 5.9', $result->message );<br>self::assertTrue( $compatible->evaluate( $this->job() )->allowed ); |
| `test_php_compatibility` | test php compatibility | self::assertFalse( $result->allowed );<br>self::assertSame( 'php_incompatible', $result->code );<br>self::assertStringContainsString( 'requires PHP 8.3', $result->message );<br>self::assertTrue( $compatible->evaluate( $this->job() )->allowed ); |
| `test_delay_policy` | test delay policy | self::assertTrue( $allowed->evaluate( $this->job() )->allowed );<br>self::assertFalse( $result->allowed );<br>self::assertSame( 'delay_active', $result->code ); |
| `test_pending_offer` | test pending offer | self::assertSame( 'stale_offer', $missing->evaluate( $this->job() )->code );<br>self::assertSame( 'stale_offer', $mismatch->evaluate( $this->job( array( 'target_version' => '2.0.0' ) ) )->code );<br>self::assertTrue( $same->evaluate( $this->job( array( 'target_version' => '2.0.0' ) ) )->allowed ); |
| `test_chain_dependency` | test chain dependency | self::assertTrue( $policy->evaluate( $this->job() )->allowed, 'No chain means no dependency.' );<br>self::assertTrue( $enqueue->allowed, 'Enqueue must not block a downstream row before the first step runs.' );<br>self::assertFalse( $result->allowed );<br>self::assertSame( 'dependency_failed', $result->code ); |
| `test_version_control_policy` | test version control policy | self::assertFalse( $result->allowed );<br>self::assertSame( 'native_vcs', $result->code );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_LOCKED_NATIVE, $result->metadata['reason_code'] );<br>self::assertSame( 'Git', $result->metadata['manager'] ); |
| `test_abandoned_policy_is_advisory` | test abandoned policy is advisory | self::assertTrue( $result->allowed, 'Advisory never denies.' );<br>self::assertTrue( $result->metadata['abandoned'] );<br>self::assertFalse( $policy->evaluate( $this->job() )->metadata['abandoned'] );<br>self::assertFalse( $off->evaluate( $this->job() )->metadata['abandoned'] ); |
| `test_disable_vs_cancel` | test disable vs cancel | self::assertTrue( $result['disabled'] );<br>self::assertSame( 'safe_mode_disabled', $result['action_type'] );<br>self::assertSame( array(), $GLOBALS['updatronix_test_auto_update_plugins'] );<br>self::assertNotNull( Updatronix_Shield_Ledger::get( 'plugin', $plugin ) ); |
| `test_reenable_requires_a_newer_target` | test reenable requires a newer target | self::assertSame( 1, Updatronix_Shield_Enforcement::maybe_reenable() );<br>self::assertContains( $plugin, $GLOBALS['updatronix_test_auto_update_plugins'] );<br>self::assertNull( Updatronix_Shield_Ledger::get( 'plugin', $plugin ) );<br>self::assertSame( 0, Updatronix_Shield_Enforcement::maybe_reenable() ); |
| `test_ownership_shim_is_a_noop` | test ownership shim is a noop | self::assertFalse( Updatronix_Shield_Ownership::is_owned( 'plugin', 'plug/plug.php', '2.0.0' ) );<br>self::assertFalse( Updatronix_Shield_Ownership::is_owned( 'plugin', 'plug/plug.php', '2.0.0' ), 'The shim remembers nothing.' );<br>self::assertTrue( apply_filters( 'auto_update_plugin', true, $item ), 'The handshake registers no filter.' ); |
| `test_abandoned_detector_cache_and_cap` | test abandoned detector cache and cap | self::assertTrue( $detector->is_abandoned( 'plugin', 'cached/cached.php', false ), 'A cache hit is advisory without a lookup.' );<br>self::assertFalse( $detector->is_abandoned( 'plugin', 'unknown/unknown.php', true ) );<br>self::assertFalse( $detector->is_abandoned( 'plugin', 'over-cap/over-cap.php', true ), 'The lookup cap is bounded.' ); |
| `test_abandoned_scan_skips_invalid_items` | test abandoned scan skips invalid items | self::assertArrayHasKey( 'plugin:cached/cached.php', $flagged );<br>self::assertArrayNotHasKey( 'plugin:', $flagged, 'An empty item id is skipped.' );<br>self::assertArrayHasKey( 'plugin:lookup/lookup.php', $flagged, 'An uncached item is looked up.' );<br>self::assertCount( 2, $flagged ); |
| `test_composer_detector` | test composer detector | self::assertFalse( $detector->is_composer_controlled( 'plugin', $id ), 'composer.json alone is not proof.' );<br>self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $state['composer'], 'A bundled vendor directory is not provenance.' );<br>self::assertTrue( $state['bundled'] );<br>self::assertTrue( $detector->is_composer_controlled( 'plugin', $id ) ); |
| `test_version_control_detector_tri_state_and_partial_pair_log` | test version control detector tri state and partial pair log | self::assertArrayHasKey( $key, $state, 'The state must expose ' . $key . '.' );<br>self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $state['native'] );<br>self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $state['composer'], 'A partial Composer pair is a determinate no.' );<br>self::assertSame( 'none', $state['source'] ); |
| `test_abandoned_state_threshold_and_unknown` | test abandoned state threshold and unknown | self::assertSame( 30, $detector->get_threshold_days() );<br>self::assertSame( 'abandoned', $detector->get_state( 'plugin', 'old/old.php' )['state'] );<br>self::assertSame( 'current', $detector->get_state( 'plugin', 'new/new.php' )['state'] );<br>self::assertSame( 'unknown', $detector->get_state( 'plugin', 'unknown/unknown.php' )['state'] ); |
| `test_reenable_status_and_attempts` | test reenable status and attempts | self::assertTrue( $status['enabled'] );<br>self::assertSame( 'disable', $status['behavior'] );<br>self::assertSame( '', $status['paused_reason'] );<br>self::assertSame( 'eligible', $items['plugin:eligible/eligible.php']['state'] ); |
| `test_reenable_records_a_toggle_that_did_not_stick` | test reenable records a toggle that did not stick | self::assertSame( 0, Updatronix_Shield_Enforcement::maybe_reenable() );<br>self::assertNotNull( $entry );<br>self::assertSame( 'toggle_failed', $entry['last_attempt_result'] );<br>self::assertSame( array(), $GLOBALS['updatronix_test_auto_update_plugins'] ); |
| `test_reenable_status_uses_safe_defaults_when_filtered_out` | test reenable status uses safe defaults when filtered out | self::assertFalse( $status['safe_mode'], 'A missing safe_mode key defaults to off.' );<br>self::assertFalse( $status['re_enable'], 'A missing re_enable key defaults to off in the defensive path.' );<br>self::assertFalse( $status['enabled'] ); |
| `test_ordered_adapter_blocks_version_lock_before_compatibility` | test ordered adapter blocks version lock before compatibility | self::assertTrue( $found, 'The ordered adapter is registered at priority 19.' );<br>self::assertFalse( $result );<br>self::assertCount( 1, $GLOBALS['updatronix_test_log'] );<br>self::assertStringContainsString( 'Managed via Git', (string) ( $GLOBALS['updatronix_test_log'][0]['message'] ?? '' ) ); |
| `test_ordered_adapter_allows_unlocked_and_keeps_compatibility` | test ordered adapter allows unlocked and keeps compatibility | self::assertTrue( Updatronix_Version_Control_Adapter::filter_plugin( true, $compatible ) );<br>self::assertCount( 0, $GLOBALS['updatronix_test_log'] );<br>self::assertFalse( Updatronix_Version_Control_Adapter::filter_plugin( true, $incompatible ) );<br>self::assertCount( 1, $GLOBALS['updatronix_test_log'] ); |
| `test_shield_off_gates_version_lock_in_adapter_and_engine` | test shield off gates version lock in adapter and engine | self::assertTrue( Updatronix_Version_Control_Adapter::filter_plugin( true, $offer ) );<br>self::assertCount( 0, $GLOBALS['updatronix_test_log'] );<br>self::assertTrue( Updatronix_Policy_Chain::evaluate( $policies, $this->job() )['allowed'] ); |
| `test_safe_mode_fallback_does_not_double_log` | test safe mode fallback does not double log | self::assertFalse( Updatronix_Version_Control_Adapter::filter_plugin( true, $offer ) );<br>self::assertSame( 1, $after_adapter );<br>self::assertFalse( Updatronix_Safe_Mode::filter_auto_update_plugin( false, $offer ) );<br>self::assertCount( $after_adapter, $GLOBALS['updatronix_test_log'] ); |
| `test_adapter_chain_order` | test adapter chain order | self::assertSame( array( 'native_guards', 'version_control', 'wp_compatibility', 'php_compatibility', 'abandoned' ), array_map( static fn ( $policy ): string => $policy->get_id(), $policies ) ); |

#### `tests/Unit/UpdateShieldRestTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_check_manage_permission_requires_capability_on_single_site` | test check manage permission requires capability on single site | $this->assertTrue( Updatronix_Update_Shield::check_manage_permission() );<br>$this->assertFalse( Updatronix_Update_Shield::check_manage_permission() ); |
| `test_check_manage_permission_multisite_requires_super_admin` | test check manage permission multisite requires super admin | $this->assertFalse( Updatronix_Update_Shield::check_manage_permission() );<br>$this->assertTrue( Updatronix_Update_Shield::check_manage_permission() ); |
| `test_check_shield_permission_requires_active_license` | test check shield permission requires active license | $this->markTestSkipped( 'License storage is not loaded.' );<br>$this->assertFalse( Updatronix_Update_Shield::check_shield_permission() ); |
| `test_get_shield_settings_returns_defaults` | test get shield settings returns defaults | $this->assertSame( 200, $response->get_status() );<br>$this->assertFalse( $data['safe_mode']['enabled'] );<br>$this->assertTrue( $data['control_php_version'] );<br>$this->assertTrue( $data['control_wp_version'] ); |
| `test_get_shield_settings_reflects_stored_options_and_blocked_items` | test get shield settings reflects stored options and blocked items | $this->assertTrue( $data['safe_mode']['enabled'] );<br>$this->assertFalse( $data['notify_on_block'] );<br>$this->assertSame( 1, $data['shield_status']['blocked_plugins_count'] );<br>$this->assertArrayHasKey( 'plug/plug.php', $data['shield_status']['blocked_plugins'] ); |
| `test_save_shield_settings_persists_booleans` | test save shield settings persists booleans | $this->assertSame( 200, $response->get_status() );<br>$this->assertTrue( $data['safe_mode']['enabled'] );<br>$this->assertFalse( $data['notify_on_block'] );<br>$this->assertTrue( $data['control_php_version'] ); |
| `test_save_shield_settings_persists_new_fields` | test save shield settings persists new fields | $this->assertSame( 'cancel', $data['incompatible_behavior'] );<br>$this->assertFalse( $data['re_enable'] );<br>$this->assertFalse( $data['notify_email'] );<br>$this->assertFalse( $data['notify_on_block'] ); |
| `test_version_lock_manual_updates_round_trip_preserves_master` | test version lock manual updates round trip preserves master | $this->assertTrue( $data['version_lock_manual_updates'] );<br>$this->assertFalse( $data['version_control_lock'], 'A stored false master value is preserved.' );<br>$this->assertFalse( $data['version_lock_manual_updates'] ); |
| `test_save_shield_settings_partial_update_preserves_other_keys` | test save shield settings partial update preserves other keys | $this->assertTrue( $data['safe_mode']['enabled'] );<br>$this->assertFalse( $data['notify_on_block'] );<br>$this->assertTrue( $updatronix_test_shield_option['control_php_version'] );<br>$this->assertTrue( $updatronix_test_shield_option['control_wp_version'] ); |
| `test_save_shield_settings_ignores_non_array_json` | test save shield settings ignores non array json | $this->assertSame( 200, $response->get_status() );<br>$this->assertFalse( $response->get_data()['safe_mode']['enabled'] );<br>$this->assertTrue( $response->get_data()['notify_on_block'] );<br>$this->assertTrue( $response->get_data()['control_php_version'] ); |
| `test_define_rest_routes_registers_shield_endpoints` | test define rest routes registers shield endpoints | $this->assertCount( 3, $updatronix_test_rest_routes );<br>$this->assertSame( 'updatronix-pro/v1', $settings['namespace'] );<br>$this->assertSame( '/shield/settings', $settings['route'] );<br>$this->assertCount( 2, $settings['args'] ); |
| `test_recheck_compatibility_success_disables_blocked` | test recheck compatibility success disables blocked | $this->assertSame( 200, $response->get_status() );<br>$this->assertSame( 1, $data['shield_status']['blocked_plugins_count'] );<br>$this->assertArrayHasKey( 'plug/plug.php', $data['shield_status']['blocked_plugins'] );<br>$this->assertCount( 1, $this->pending() ); |
| `test_recheck_compatibility_unreachable_when_transients_missing` | test recheck compatibility unreachable when transients missing | $this->assertSame( 502, $response->get_status() );<br>$this->assertSame( 'shield_recheck_unreachable', $response->get_data()['code'] ); |
| `test_get_shield_settings_falls_back_when_a_default_key_is_filtered_away` | test get shield settings falls back when a default key is filtered away | $this->assertFalse( $data['safe_mode']['enabled'] );<br>$this->assertTrue( $data['control_php_version'] );<br>$this->assertTrue( $data['control_wp_version'] );<br>$this->assertTrue( $data['notify_on_block'] ); |
| `test_shield_routes_mark_no_argument_required` | test shield routes mark no argument required | $this->assertNotEmpty( $routes, 'the shield routes must be registered' );<br>$this->assertFalse( $value['required'], "argument {$name} must not be required" ); |
| `test_save_shield_settings_without_enabled_clears_safe_mode` | test save shield settings without enabled clears safe mode | $this->assertFalse( $GLOBALS['updatronix_test_shield_option']['safe_mode'] ); |
| `test_recheck_succeeds_when_only_one_transient_was_refreshed` | test recheck succeeds when only one transient was refreshed | $this->assertSame( 200, $response->get_status() );<br>$this->assertArrayHasKey( 'shield_status', $response->get_data() ); |
| `test_recheck_unreachable_when_the_transients_have_no_last_checked` | test recheck unreachable when the transients have no last checked | $this->assertSame( array(), $updatronix_test_site_transients, 'no transient had a last_checked, so none may have been rewritten' );<br>$this->assertSame( 502, $response->get_status() );<br>$this->assertSame( 'shield_recheck_unreachable', $response->get_data()['code'] ); |
| `test_recheck_succeeds_when_only_plugins_was_refreshed` | test recheck succeeds when only plugins was refreshed | $this->assertSame( 200, $response->get_status() );<br>$this->assertArrayHasKey( 'shield_status', $response->get_data() ); |

#### `tests/Unit/UpdateTransientShimTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_a_recent_check_is_not_refreshed` | test a recent check is not refreshed | $this->assertFalse( wp_update_plugins() );<br>$this->assertSame( $checked, $GLOBALS['updatronix_test_site_transients']['update_plugins']->last_checked, 'core returns early and leaves last_checked where it was' );<br>$this->assertSame( array( 'plugins' ), $GLOBALS['updatronix_test_update_calls'] ); |
| `test_a_stale_check_is_refreshed` | test a stale check is refreshed | $this->assertTrue( wp_update_themes() );<br>$this->assertGreaterThan( 0, $GLOBALS['updatronix_test_site_transients']['update_themes']->last_checked ); |
| `test_a_transient_without_last_checked_is_refreshed` | test a transient without last checked is refreshed | $this->assertTrue( wp_version_check() );<br>$this->assertGreaterThan( 0, $GLOBALS['updatronix_test_site_transients']['update_core']->last_checked ); |
| `test_an_absent_transient_stays_absent` | test an absent transient stays absent | $this->assertTrue( wp_update_plugins() );<br>$this->assertArrayNotHasKey( 'update_plugins', $GLOBALS['updatronix_test_site_transients'] ); |

#### `tests/Unit/UpdateUninstallGuardTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_guard_matrix` | test guard matrix | self::assertFalse( Updatronix_Pro_Uninstall::should_skip_cleanup( false, false, false, false ) );<br>self::assertFalse( Updatronix_Pro_Uninstall::should_skip_cleanup( false, false, false, true ), 'Single site cleans up.' );<br>self::assertTrue( Updatronix_Pro_Uninstall::should_skip_cleanup( true, false, false, true ), 'A Multisite subsite admin is skipped.' );<br>self::assertFalse( Updatronix_Pro_Uninstall::should_skip_cleanup( true, true, false, true ), 'The network admin cleans up.' ); |

#### `tests/Unit/VersionControlDetectorTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_item_root_markers_lock_with_manager_labels` | test item root markers lock with manager labels | self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['native'], $marker . ' must lock the item.' );<br>self::assertSame( $manager, $state['manager'] );<br>self::assertSame( 'item', $state['scope'] );<br>self::assertSame( 'native', $state['source'] ); |
| `test_parent_and_site_scopes` | test parent and site scopes | self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $state['state'] );<br>self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['state'] );<br>self::assertSame( 'parent', $state['scope'] );<br>self::assertSame( 'Git', $state['manager'] ); |
| `test_theme_root_marker` | test theme root marker | self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['native'] );<br>self::assertSame( 'Git', $state['manager'] );<br>self::assertSame( 'item', $state['scope'] ); |
| `test_composer_installed_json_provenance` | test composer installed json provenance | self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['composer'] );<br>self::assertSame( 'composer', $state['source'] );<br>self::assertSame( 'Composer', $state['manager'] );<br>self::assertSame( 'acme/plugin', $state['composer_package'] ); |
| `test_composer_installed_php_and_wpackagist_label` | test composer installed php and wpackagist label | self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['composer'] );<br>self::assertSame( 'WP Packagist', $state['manager'] );<br>self::assertSame( 'WP Packagist', $state['composer_project'] );<br>self::assertStringContainsString( 'installed.php', $state['evidence'] ); |
| `test_composer_bedrock_label` | test composer bedrock label | self::assertSame( 'Bedrock', $state['manager'] );<br>self::assertSame( 'Bedrock', $state['composer_project'] ); |
| `test_configured_vendor_dir_is_used` | test configured vendor dir is used | self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['composer'] );<br>self::assertSame( 'acme/custom', $state['composer_package'] ); |
| `test_configured_absent_vendor_dir_is_not_an_error` | test configured absent vendor dir is not an error | self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $state['composer'] ); |
| `test_two_project_roots_continue_after_a_non_match` | test two project roots continue after a non match | self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['composer'] );<br>self::assertSame( 'acme/two-roots', $state['composer_package'] ); |
| `test_symlinked_default_vendor_dir_fails_closed` | test symlinked default vendor dir fails closed | self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['composer'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNKNOWN, $state['reason_code'] ); |
| `test_out_of_root_vendor_dir_fails_closed` | test out of root vendor dir fails closed | self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['composer'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNKNOWN, $state['reason_code'] ); |
| `test_bundled_composer_is_not_provenance` | test bundled composer is not provenance | self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $state['composer'] );<br>self::assertTrue( $state['bundled'] );<br>self::assertSame( 'none', $state['source'] ); |
| `test_composer_lock_alone_does_not_lock` | test composer lock alone does not lock | self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $state['composer'] ); |
| `test_unresolved_marker_fails_closed` | test unresolved marker fails closed | self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['native'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNKNOWN, $state['reason_code'] ); |
| `test_unreadable_root_fails_closed` | test unreadable root fails closed | self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['native'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNKNOWN, $state['reason_code'] ); |
| `test_missing_detector_fails_closed` | test missing detector fails closed | self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['native'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNKNOWN, $state['reason_code'] ); |
| `test_external_checkout_fails_closed` | test external checkout fails closed | self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['native'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNKNOWN, $state['reason_code'] );<br>self::assertSame( 'external', $state['manager'] );<br>self::assertSame( 'unknown', $state['source'] ); |
| `test_no_evidence_read_path_writes_no_advisory` | test no evidence read path writes no advisory | self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['native'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNVERIFIED, $state['reason_code'] );<br>self::assertCount( 0, $GLOBALS['updatronix_test_log'], 'get_state() is a read and must not write a log row.' );<br>self::assertCount( 0, $GLOBALS['updatronix_test_log'], 'Repeated reads must still not write a log row.' ); |
| `test_shield_state_read_writes_no_advisory` | test shield state read writes no advisory | self::assertArrayHasKey( 'native', $state );<br>self::assertSame( 1, $state['native']['unknown'], 'The ghost plugin is unverified in the Shield read.' );<br>self::assertCount( 0, $GLOBALS['updatronix_test_log'], 'A page render must not write an advisory row.' ); |
| `test_policy_path_writes_one_advisory` | test policy path writes one advisory | self::assertTrue( $result->allowed );<br>self::assertCount( 1, $GLOBALS['updatronix_test_log'] );<br>self::assertStringContainsString( 'unverified', strtolower( (string) ( $GLOBALS['updatronix_test_log'][0]['message'] ?? '' ) ) );<br>self::assertCount( 1, $GLOBALS['updatronix_test_log'], 'The advisory row is written once per item, target, and reason.' ); |
| `test_extension_point_cannot_make_unknown_safe` | test extension point cannot make unknown safe | self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $state['native'] );<br>self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['native'] ); |
| `test_native_wins_over_composer` | test native wins over composer | self::assertSame( 'native', $state['source'] );<br>self::assertSame( 'Git', $state['manager'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_LOCKED_NATIVE, $state['reason_code'] ); |
| `test_single_file_plugin_matches_install_path` | test single file plugin matches install path | self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['composer'] );<br>self::assertSame( 'acme/single', $state['composer_package'] ); |
| `test_symlinked_item_root` | test symlinked item root | self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['composer'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNKNOWN, $state['reason_code'] );<br>self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['composer'] );<br>self::assertSame( 'acme/symlinked', $state['composer_package'] ); |
| `test_theme_composer_provenance` | test theme composer provenance | self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['composer'] );<br>self::assertSame( 'acme/theme', $state['composer_package'] );<br>self::assertSame( 'Composer', $state['manager'] ); |
| `test_decision_facade_and_gates` | test decision facade and gates | self::assertFalse( $locked['allowed'] );<br>self::assertSame( 'native_vcs', $locked['code'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_LOCKED_NATIVE, $locked['reason_code'] );<br>self::assertSame( 'Git', $locked['manager'] ); |
| `test_schedule_item_state_contract` | test schedule item state contract | self::assertSame( 'locked', $state['lock']['state'] );<br>self::assertSame( 'Git', $state['lock']['manager'] );<br>self::assertSame( 'item', $state['lock']['scope'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_LOCKED_NATIVE, $state['lock']['reason_code'] ); |
| `test_policy_source_metadata_for_unknown` | test policy source metadata for unknown | self::assertFalse( $result->allowed );<br>self::assertSame( 'unknown', $result->metadata['source'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNKNOWN, $result->metadata['reason_code'] ); |
| `test_cache_clears_after_marker_appears` | test cache clears after marker appears | self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $detector->get_state( 'plugin', $id )['native'] );<br>self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $detector->get_state( 'plugin', $id )['native'] ); |
| `test_state_string_fields_and_defaults` | test state string fields and defaults | self::assertIsString( $state[ $key ], $key );<br>self::assertIsBool( $state['bundled'] );<br>self::assertIsBool( $state['active'] );<br>self::assertFalse( $state['bundled'] ); |
| `test_is_active_requires_both_switches` | test is active requires both switches | self::assertTrue( ( new Updatronix_Version_Control_Detector( null, $settings ) )->is_active() );<br>self::assertFalse( ( new Updatronix_Version_Control_Detector( null, $settings ) )->is_active() ); |
| `test_get_item_path_covers_theme_plugin_and_core` | test get item path covers theme plugin and core | self::assertSame( rtrim( WP_PLUGIN_DIR, '/\\' ) . '/plug/plug.php', $detector->get_item_path( 'plugin', 'plug/plug.php' ) );<br>self::assertSame( $themes . '/mytheme', $detector->get_item_path( 'theme', 'mytheme' ) );<br>self::assertSame( ABSPATH, $detector->get_item_path( 'core', 'wordpress' ) ); |
| `test_forced_native_state_uses_external_manager` | test forced native state uses external manager | self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $state['native'] );<br>self::assertSame( 'external', $state['manager'] );<br>self::assertSame( 'unknown', $state['scope'] );<br>self::assertIsString( $state['reason'] ); |
| `test_malformed_installed_json_is_a_no_match` | test malformed installed json is a no match | self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $state['composer'] ); |
| `test_installed_php_missing_versions_fails_closed` | test installed php missing versions fails closed | self::assertSame( Updatronix_Version_Control_Detector::STATE_UNKNOWN, $state['composer'] );<br>self::assertSame( Updatronix_Version_Control_Detector::REASON_UNKNOWN, $state['reason_code'] ); |
| `test_composer_project_cache_changes_with_roots` | test composer project cache changes with roots | self::assertSame( Updatronix_Version_Control_Detector::STATE_NO, $detector->get_state( 'plugin', basename( $root_a ) . '/' . basename( $root_a ) . '.php' )['composer'] );<br>self::assertSame( Updatronix_Version_Control_Detector::STATE_YES, $detector->get_state( 'plugin', basename( $root_b ) . '/' . basename( $root_b ) . '.php' )['composer'] ); |

#### `tests/Unit/VersionLockGuardTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_full_lock_blocks_manual_plugin_update` | test full lock blocks manual plugin update | self::assertTrue( is_wp_error( $result ) );<br>self::assertSame( 'updatronix_version_locked', $result->get_error_code() );<br>self::assertStringContainsString( 'managed via Git', $result->get_error_message() );<br>self::assertCount( 1, $GLOBALS['updatronix_test_log'] ); |
| `test_automatic_only_allows_manual_update` | test automatic only allows manual update | self::assertTrue( $result );<br>self::assertCount( 0, $GLOBALS['updatronix_test_log'] ); |
| `test_fresh_install_is_allowed` | test fresh install is allowed | self::assertTrue( $result ); |
| `test_pass_execution_flag_is_exempt` | test pass execution flag is exempt | self::assertTrue( $result ); |
| `test_option_write_guard_strips_locked_items` | test option write guard strips locked items | self::assertSame( array( $unlocked_id ), array_values( $value ) ); |
| `test_can_toggle_returns_wp_error_for_locked_item` | test can toggle returns wp error for locked item | self::assertTrue( is_wp_error( $result ) );<br>self::assertSame( 'updatronix_version_locked', $result->get_error_code() );<br>self::assertTrue( Updatronix_Version_Lock_Guard::filter_can_toggle( true, 'plugin', $free_id, true ) ); |
| `test_core_manual_guard` | test core manual guard | self::assertTrue( is_wp_error( $result ) );<br>self::assertStringContainsString( 'external manager', $result->get_error_message() ); |
| `test_shield_off_disables_the_guard` | test shield off disables the guard | self::assertTrue( $result ); |
| `test_wp_cli_escape_hatch` | test wp cli escape hatch | self::assertTrue( $result ); |

#### `tests/Unit/VersionLockStateTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_version_control_summary_gates` | test version control summary gates | self::assertSame( $active, $state['active'] );<br>self::assertSame( $requires, $state['requires_shield'] );<br>self::assertSame( $manual, $state['manual_updates'] );<br>self::assertSame( $lock, $state['lock'] ); |
| `test_native_item_metadata` | test native item metadata | self::assertSame( 1, $state['native']['detected'] );<br>self::assertSame( 'Git', $item['manager'] );<br>self::assertSame( 'item', $item['scope'] );<br>self::assertIsString( $item['reason'] ); |

#### `tests/Unit/WhiteLabelPermissionTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_permission_requires_capability` | test permission requires capability | $this->assertFalse( Updatronix_White_Label::check_permission() ); |
| `test_permission_requires_active_license` | test permission requires active license | $this->assertFalse( Updatronix_White_Label::check_permission() );<br>$this->assertTrue( Updatronix_White_Label::check_permission() ); |
| `test_permission_fails_closed_without_storage` | test permission fails closed without storage | $this->assertSame( 'denied', trim( (string) $output ) ); |
| `test_multisite_requires_super_admin` | test multisite requires super admin | $this->assertFalse( Updatronix_White_Label::check_permission() );<br>$this->assertTrue( Updatronix_White_Label::check_permission() ); |

#### `tests/Unit/WhiteLabelSanitizationTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_defaults_are_opt_in` | test defaults are opt in | $this->assertFalse( Updatronix_White_Label::DEFAULTS['replace_branding'] );<br>$this->assertFalse( Updatronix_White_Label::DEFAULTS['apply_to_emails'] );<br>$this->assertSame( 0, Updatronix_White_Label::DEFAULTS['logo_id'] ); |
| `test_sanitize_settings_normalizes_input` | test sanitize settings normalizes input | $this->assertSame( 'Acme', $result['brand_name'] );<br>$this->assertSame( 'Site updates', $result['menu_label'] );<br>$this->assertSame( '#123456', $result['brand_color'] );<br>$this->assertSame( '#1E1E1E', $result['menu_color'] ); |
| `test_text_is_capped` | test text is capped | $this->assertSame( Updatronix_White_Label::MAX_BRAND_NAME_LENGTH, strlen( $result['brand_name'] ) );<br>$this->assertSame( Updatronix_White_Label::MAX_MENU_LABEL_LENGTH, strlen( $result['menu_label'] ) ); |
| `test_text_strips_tags_and_newlines` | test text strips tags and newlines | $this->assertSame( 'Acme Studio', $result['brand_name'] );<br>$this->assertSame( 'alert(1)Updates', $result['menu_label'] ); |
| `test_invalid_color_falls_back` | test invalid color falls back | $this->assertSame( '#3858E9', $result['brand_color'] );<br>$this->assertSame( '#1E1E1E', $result['menu_color'] );<br>$this->assertSame( '#ABC', $short['brand_color'] ); |
| `test_boolean_strings_are_not_truthy` | test boolean strings are not truthy | $this->assertFalse( $result['replace_branding'] );<br>$this->assertFalse( $result['apply_to_emails'] );<br>$this->assertTrue( $enabled['replace_branding'] );<br>$this->assertTrue( $enabled['apply_to_emails'] ); |
| `test_partial_update_preserves_existing` | test partial update preserves existing | $this->assertSame( 'Acme Studio', $result['brand_name'] );<br>$this->assertSame( 'Site updates', $result['menu_label'] );<br>$this->assertTrue( $result['replace_branding'] ); |
| `test_logo_accepts_valid_png` | test logo accepts valid png | $this->assertSame( $logo_id, $result['logo_id'] ); |
| `test_logo_rejects_svg` | test logo rejects svg | $this->assertSame( 0, $result['logo_id'] ); |
| `test_logo_rejects_missing_or_non_image` | test logo rejects missing or non image | $this->assertSame( 0, $missing['logo_id'] );<br>$this->assertSame( 0, $non_image['logo_id'] ); |
| `test_logo_rejects_too_large` | test logo rejects too large | $this->assertSame( 0, $result['logo_id'] ); |
| `test_logo_rejects_too_small` | test logo rejects too small | $this->assertSame( 0, $result['logo_id'] ); |
| `test_logo_requires_upload_files` | test logo requires upload files | $this->assertSame( 0, $result['logo_id'] ); |
| `test_logo_requires_read_access` | test logo requires read access | $this->assertSame( 0, $result['logo_id'] ); |
| `test_unchanged_logo_is_not_revalidated` | test unchanged logo is not revalidated | $this->assertSame( 77, $result['logo_id'] ); |
| `test_get_settings_normalizes_stored_values` | test get settings normalizes stored values | $this->assertSame( 'Acme', $settings['brand_name'] );<br>$this->assertSame( 40, strlen( $settings['menu_label'] ) );<br>$this->assertSame( 3, $settings['logo_id'] );<br>$this->assertSame( '#3858E9', $settings['brand_color'] ); |
| `test_filters_apply_and_respect_switches` | test filters apply and respect switches | $this->assertSame( 'Acme Studio', $brand['name'] );<br>$this->assertSame( 'Site updates', $brand['menu_label'] );<br>$this->assertSame( 'https://example.test/logo.png', $brand['logoUrl'] );<br>$this->assertSame( '#0F766E', $brand['color'] ); |
| `test_email_filter_requires_both_switches_and_brand` | test email filter requires both switches and brand | $this->assertSame( 'Site', Updatronix_White_Label::filter_email_brand_name( 'Site' ) );<br>$this->assertSame( 'Acme', Updatronix_White_Label::filter_email_brand_name( 'Site' ) ); |
| `test_payload_includes_meta` | test payload includes meta | $this->assertSame( 'Acme', $data['settings']['brand_name'] );<br>$this->assertSame( 'https://example.test/logo.png', $data['logo_url'] );<br>$this->assertFalse( $data['defaults']['replace_branding'] );<br>$this->assertSame( 'Test Site', $data['site_name'] ); |
| `test_save_persists_sanitized_settings` | test save persists sanitized settings | $this->assertInstanceOf( WP_REST_Response::class, $response );<br>$this->assertSame( 'Acme', $data['settings']['brand_name'] );<br>$this->assertSame( $logo_id, $data['settings']['logo_id'] );<br>$this->assertTrue( $data['settings']['replace_branding'] ); |
| `test_save_rejects_invalid_logo` | test save rejects invalid logo | $this->assertInstanceOf( WP_Error::class, $result );<br>$this->assertSame( 'updatronix_invalid_logo', $result->get_error_code() );<br>$this->assertSame( 400, $result->get_status() );<br>$this->assertNull( $updatronix_test_white_label_option ); |
| `test_white_label_route_is_registered` | test white label route is registered | $this->assertCount( 1, $routes );<br>$this->assertSame( 'updatronix-pro/v1', $routes[0]['namespace'] );<br>$this->assertSame( '/white-label', $routes[0]['route'] );<br>$this->assertCount( 2, $routes[0]['args'] ); |

#### `tests/Unit/WpdsPackageAlignmentTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_wpds_versions_match_free` | test wpds versions match free | self::markTestSkipped( 'The Free plugin package.json is not available in this checkout.' );<br>self::assertIsArray( $pro );<br>self::assertIsArray( $free );<br>self::assertArrayHasKey( $package, $free['dependencies'], 'Free must pin ' . $package ); |

### Integration (94 cases)

#### `tests/Integration/DecisionWritePointsTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_shield_block_writes_one_row_per_item_target_policy_pass` | test shield block writes one row per item target policy pass | self::assertIsInt( $first );<br>self::assertSame( $first, $second, 'A repeated block in the same state is one row.' );<br>self::assertSame( 1, $this->count_rows() );<br>self::assertSame( 'shield_incompatible_php', $row->reason_code ); |
| `test_core_block_uses_the_canonical_core_key` | test core block uses the canonical core key | self::assertIsInt( $id );<br>self::assertSame( 'core', $row->log_type );<br>self::assertSame( 'core:wordpress', $row->item_slug );<br>self::assertSame( 'shield_incompatible_php', $row->reason_code ); |
| `test_core_block_is_deduplicated_through_the_adapter` | test core block is deduplicated through the adapter | self::assertTrue( (bool) $first['new'], 'The first core block is new.' );<br>self::assertFalse( (bool) $second['new'], 'The same core block is not new again.' );<br>self::assertSame( $first['log_id'], $second['log_id'] );<br>self::assertSame( 'core', $row->log_type ); |
| `test_wait_row_names_predecessor_and_time` | test wait row names predecessor and time | self::assertIsInt( $id );<br>self::assertSame( 'waiting', $row->decision_state );<br>self::assertSame( 'flow_waiting_predecessor', $row->reason_code );<br>self::assertSame( 'plugin:fixture/a.php', $row->predecessor ); |
| `test_cancel_row_names_reason_and_chain` | test cancel row names reason and chain | self::assertIsInt( $id );<br>self::assertSame( 'canceled', $row->decision_state );<br>self::assertSame( 'license_inactive', $row->reason_code );<br>self::assertSame( 'cancelled', $row->status ); |
| `test_reenable_row_is_a_done_fact` | test reenable row is a done fact | self::assertIsInt( $id );<br>self::assertSame( 'done', $row->decision_state );<br>self::assertSame( 'shield_reenabled', $row->reason_code );<br>self::assertSame( 'auto_update_reenabled', $row->action_type ); |
| `test_advisory_is_not_an_update` | test advisory is not an update | self::assertIsInt( $id );<br>self::assertSame( 'advisory', $row->action_type );<br>self::assertSame( 'abandoned_advisory', $row->reason_code );<br>self::assertSame( 'info', $row->decision_state ); |
| `test_item_transitions_map_to_states` | test item transitions map to states | self::assertSame( 'done', Updatronix_Logger::get_log( (int) $succeeded, true )->decision_state );<br>self::assertSame( 'updated', Updatronix_Logger::get_log( (int) $succeeded, true )->reason_code );<br>self::assertSame( 'failed', Updatronix_Logger::get_log( (int) $failed, true )->decision_state );<br>self::assertSame( 'update_failed', Updatronix_Logger::get_log( (int) $failed, true )->reason_code ); |

#### `tests/Integration/FlowPassIntegrationTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_flow_order_is_one_item_at_a_time` | test flow order is one item at a time | self::assertSame( Updatronix_Update_Pass::STATE_OFFER_ELIGIBLE, $built['nodes'][ $this->alpha() ]['state'] );<br>self::assertNotNull( $pass );<br>self::assertSame( array( $this->alpha() ), $pass->schedulable(), 'Only the head of the chain runs first.' );<br>self::assertSame( array( $this->bravo() ), $pass->schedulable(), 'The successor follows on the next invocation.' ); |
| `test_deleted_chain_cancels_its_nodes` | test deleted chain cancels its nodes | self::assertNotNull( $pass );<br>self::assertSame( Updatronix_Update_Pass::STATE_CANCELED, $alpha['state'] );<br>self::assertSame( 'flow_chain_canceled', $alpha['reason_code'] );<br>self::assertSame( array( 'payments' ), $pass->flow_snapshot()['changes']['deleted'] ); |
| `test_target_change_replans_and_descendants_wait` | test target change replans and descendants wait | self::assertNotNull( $pass );<br>self::assertSame( Updatronix_Update_Pass::STATE_OFFER_PENDING, $alpha['state'] );<br>self::assertSame( '9.9.9', $alpha['target'] );<br>self::assertSame( 'flow_target_changed', $alpha['reason_code'] ); |
| `test_withdrawn_offer_is_a_skip` | test withdrawn offer is a skip | self::assertNotNull( $pass );<br>self::assertSame( Updatronix_Update_Pass::STATE_SKIPPED, $alpha['state'] );<br>self::assertSame( 'flow_offer_stale', $alpha['reason_code'] );<br>self::assertTrue( Updatronix_Update_Pass::load()->is_done( $this->alpha() ) ); |
| `test_removed_item_is_not_installed` | test removed item is not installed | self::assertSame( Updatronix_Update_Pass::STATE_NOT_INSTALLED, $built['nodes']['plugin:gone/gone.php']['state'] );<br>self::assertSame( Updatronix_Update_Pass::STATE_WAITING_FOR_PREDECESSOR, $built['nodes'][ $this->alpha() ]['state'] ); |
| `test_not_opted_in_is_not_managed_and_never_forced` | test not opted in is not managed and never forced | self::assertSame( Updatronix_Update_Pass::STATE_NOT_MANAGED, $built['nodes'][ $this->alpha() ]['state'] );<br>self::assertSame( array( $this->file( $this->bravo() ) ), get_site_option( 'auto_update_plugins' ), 'The pass never forces the opt-in.' ); |
| `test_failure_affects_only_descendants` | test failure affects only descendants | self::assertNotNull( $pass );<br>self::assertNotNull( $reloaded );<br>self::assertSame( Updatronix_Update_Pass::STATE_WAITING_FOR_PREDECESSOR, $reloaded->node( $this->bravo() )['state'] );<br>self::assertSame( 'flow_predecessor_failed', $reloaded->node( $this->bravo() )['reason_code'] ); |
| `test_one_selection_per_invocation` | test one selection per invocation | self::assertNotNull( $pass );<br>self::assertSame( array( $this->alpha() ), $pass->schedulable() );<br>self::assertSame( array(), $pass->schedulable(), 'An attempted node is not selected twice.' ); |
| `test_manual_update_to_target_is_done` | test manual update to target is done | self::assertSame( Updatronix_Update_Pass::STATE_ALREADY_LATEST, $built['nodes'][ $fixture ]['state'] );<br>self::assertSame( Updatronix_Update_Pass::STATE_OFFER_ELIGIBLE, $built['nodes'][ $this->alpha() ]['state'] ); |

#### `tests/Integration/FlowPassLogTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_wait_row_names_the_flow_facts` | test wait row names the flow facts | self::assertIsInt( $id );<br>self::assertSame( 'plugin', $row->log_type );<br>self::assertSame( 'info', $row->status );<br>self::assertSame( 'waiting', $row->decision_state ); |
| `test_a_repeated_wait_is_not_a_tick_row` | test a repeated wait is not a tick row | self::assertIsInt( Updatronix_Update_Pass_Log::record_item( $pass, 'plugin:a/a.php' ) );<br>self::assertFalse( Updatronix_Update_Pass_Log::record_item( $pass, 'plugin:a/a.php' ) );<br>self::assertCount( 1, $this->rows_for( 'plugin', 'a/a.php' ) ); |
| `test_a_permanent_wait_writes_a_second_row` | test a permanent wait writes a second row | self::assertIsInt( $id );<br>self::assertCount( 2, $rows );<br>self::assertSame( 'flow_predecessor_blocked', $rows[0]->reason_code ); |
| `test_canceled_node_writes_a_cancel_row` | test canceled node writes a cancel row | self::assertIsInt( $id );<br>self::assertSame( 'canceled', $row->decision_state );<br>self::assertSame( 'cancelled', $row->status );<br>self::assertSame( 'flow_chain_canceled', $row->reason_code ); |
| `test_locked_node_writes_a_wait_row` | test locked node writes a wait row | self::assertIsInt( $id );<br>self::assertSame( 'waiting', $row->decision_state );<br>self::assertSame( 'version_locked_native', $row->reason_code );<br>self::assertSame( 'chain-a', $row->chain_id ); |
| `test_pass_summary_is_one_row` | test pass summary is one row | self::assertIsInt( $first );<br>self::assertSame( $first, $second, 'The logger dedupes the same pass summary.' );<br>self::assertSame( 'system', $row->log_type );<br>self::assertSame( 'pass', $row->action_type ); |

#### `tests/Integration/PassSummaryTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_pass_summary_row_carries_the_counts` | test pass summary row carries the counts | self::assertIsInt( $id );<br>self::assertIsObject( $row );<br>self::assertSame( 'system', $row->log_type );<br>self::assertSame( 'pass', $row->action_type ); |
| `test_one_summary_row_per_pass` | test one summary row per pass | self::assertSame( $first, $second );<br>self::assertSame( 1, $this->count_rows() ); |
| `test_update_summary_merges_later_counts` | test update summary merges later counts | self::assertIsInt( $id );<br>self::assertTrue( Updatronix_Logger::update_summary( (int) $id, array( 'status' => 'error', 'decision_state' => 'failed', 'context' => array( 'done' => 8, 'failed' => 2, ), ) ) );<br>self::assertSame( 'error', $row->status );<br>self::assertSame( 'failed', $row->decision_state ); |
| `test_truncated_pass_records_overflow` | test truncated pass records overflow | self::assertIsInt( $id );<br>self::assertTrue( $context['overflow'] );<br>self::assertSame( 50, $context['done'] ); |

#### `tests/Integration/ScheduleFactsContractTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_callback_returns_the_full_shape` | test callback returns the full shape | self::assertArrayHasKey( 'lock', $facts );<br>self::assertArrayHasKey( 'policy', $facts );<br>self::assertArrayHasKey( 'flow', $facts );<br>self::assertArrayHasKey( 'queue', $facts ); |
| `test_incoming_lock_fact_is_preserved` | test incoming lock fact is preserved | self::assertSame( 'locked', $facts['lock']['state'] );<br>self::assertSame( 'Git', $facts['lock']['manager'] ); |
| `test_incompatible_offer_is_blocked` | test incompatible offer is blocked | self::assertSame( 'blocked', $facts['policy']['state'] );<br>self::assertSame( 'shield_incompatible_wp', $facts['policy']['reason_code'] );<br>self::assertSame( 'blocked', $out['state'] );<br>self::assertStringContainsString( 'Requires WordPress 99.0', $out['display'] ); |
| `test_php_incompatibility_blocks` | test php incompatibility blocks | self::assertSame( 'shield_incompatible_php', $facts['policy']['reason_code'] ); |
| `test_policy_is_none_when_the_shield_is_off` | test policy is none when the shield is off | self::assertSame( 'none', $facts['policy']['state'] ); |
| `test_queue_fact_from_the_active_pass` | test queue fact from the active pass | self::assertSame( 'none', $none['queue']['state'] );<br>self::assertNotNull( $pass );<br>self::assertSame( 'active', $facts['queue']['state'] );<br>self::assertSame( 'running', $facts['queue']['node_state'] ); |
| `test_flow_waiting_fact_is_consumed` | test flow waiting fact is consumed | self::assertSame( 'waiting_for_predecessor', $out['state'] );<br>self::assertSame( 'Waiting for Payment Gateway. No automatic install time yet.', $out['display'] ); |
| `test_contract_is_read_only` | test contract is read only | self::assertSame( $shield_before, get_site_option( UPDATRONIX_PRO_OPTION_SHIELD, null ) );<br>self::assertSame( $pass_before, get_site_option( 'updatronix_pro_update_pass', null ) );<br>self::assertSame( $logs_before, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $log_table ) ); |

#### `tests/Integration/ShieldStateTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_get_returns_version_control_abandoned_and_reenable` | test get returns version control abandoned and reenable | self::assertSame( array( 'version_control', 'abandoned', 'reenable' ), array_keys( $state ) );<br>self::assertIsArray( $state['version_control'] );<br>self::assertIsArray( $state['abandoned'] );<br>self::assertIsArray( $state['reenable'] ); |
| `test_version_control_reports_real_counts` | test version control reports real counts | self::assertIsBool( $state['lock'] );<br>self::assertIsInt( $state['checked'] );<br>self::assertIsInt( $state['total'] );<br>self::assertLessThanOrEqual( $state['total'], $state['checked'] ); |
| `test_abandoned_uses_the_threshold_and_counts_rows` | test abandoned uses the threshold and counts rows | self::assertSame( 400, $state['threshold_days'] );<br>self::assertSame( count( $state['abandoned'] ), $state['flagged_count'] );<br>self::assertSame( count( $state['unknown'] ), $state['unknown_count'] );<br>self::assertIsArray( $state['abandoned'] ); |
| `test_reenable_returns_a_read_only_shape` | test reenable returns a read only shape | self::assertArrayHasKey( 'enabled', $state );<br>self::assertArrayHasKey( 'items', $state );<br>self::assertIsArray( $state['items'] ); |
| `test_get_does_not_write_shield_settings` | test get does not write shield settings | self::assertSame( $before, updatronix_pro_get_shield_settings() ); |

#### `tests/Integration/TruthfulnessContractTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_shield_php_block_matches_the_log` | test shield php block matches the log | self::assertTrue( $this->log_state_matches( 'fixture/plugin.php', array( 'reason_code' => 'shield_incompatible_php', 'decision_state' => 'blocked', 'origin' => 'automatic', 'decision_stage' => 'policy', ) ) ); |
| `test_shield_wp_block_matches_the_log` | test shield wp block matches the log | self::assertTrue( $this->log_state_matches( 'fixture/wp.php', array( 'reason_code' => 'shield_incompatible_wp', 'decision_state' => 'blocked', ) ) ); |
| `test_safe_mode_disable_matches_the_log` | test safe mode disable matches the log | self::assertTrue( $this->log_state_matches( 'fixture/safe.php', array( 'reason_code' => 'shield_disabled_auto_update', 'decision_state' => 'blocked', 'decision_stage' => 'policy', ) ) ); |
| `test_version_lock_matches_the_log` | test version lock matches the log | self::assertTrue( $this->log_state_matches( 'fixture/locked.php', array( 'reason_code' => 'native_vcs', 'decision_state' => 'blocked', 'decision_stage' => 'policy', ) ) );<br>self::assertSame( 'native_vcs', $context['policy_id'] ); |
| `test_flow_waiting_matches_the_log` | test flow waiting matches the log | self::assertTrue( $this->log_state_matches( 'fixture/waiting.php', array( 'reason_code' => 'flow_waiting_predecessor', 'decision_state' => 'waiting', 'predecessor' => 'plugin:fixture/a.php', 'next_eligible_at' => '2026-… |
| `test_flow_canceled_matches_the_log` | test flow canceled matches the log | self::assertTrue( $this->log_state_matches( 'fixture/canceled.php', array( 'reason_code' => 'flow_chain_canceled', 'decision_state' => 'canceled', 'chain_id' => 'chain-a', 'predecessor' => 'plugin:fixture/a.php', ) ) ); |
| `test_queue_waiting_matches_the_log` | test queue waiting matches the log | self::assertTrue( $this->log_state_matches( 'fixture/queued.php', array( 'reason_code' => 'queue_waiting_turn', 'decision_state' => 'waiting', 'origin' => 'queue', ) ) ); |
| `test_missing_row_fails_the_audit` | test missing row fails the audit | self::assertTrue( Updatronix_Logger::delete_log( (int) $row->id ) );<br>self::assertFalse( $this->log_state_matches( 'fixture/gone.php', array( 'decision_state' => 'blocked' ) ) ); |

#### `tests/Integration/UpdateEngineGatingTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_engine_is_disabled_by_default` | test engine is disabled by default | self::assertTrue( Updatronix_Update_Engine::is_enabled() );<br>self::assertFalse( Updatronix_Update_Engine::is_available() );<br>self::assertFalse( Updatronix_Update_Engine_Bootstrap::register() ); |
| `test_filter_enables_the_engine` | test filter enables the engine | self::assertTrue( Updatronix_Update_Engine::is_enabled() ); |
| `test_registration_requires_license` | test registration requires license | self::assertFalse( Updatronix_Update_Engine_Bootstrap::register() );<br>self::assertTrue( Updatronix_Update_Engine_Bootstrap::register() );<br>self::assertNotFalse( has_filter( 'updatronix_log_action_types', array( Updatronix_Update_Engine_Bootstrap::class, 'allow_action_types' ) ) ); |
| `test_can_manage_matrix` | test can manage matrix | self::assertFalse( Updatronix_Update_Engine::can_manage( true, false, true, true ), 'Multisite non-Super-Admin is denied.' );<br>self::assertTrue( Updatronix_Update_Engine::can_manage( true, true, true, true ), 'Multisite Super Admin with cap and license is allowed.' );<br>self::assertFalse( Updatronix_Update_Engine::can_manage( true, true, false, true ), 'Multisite Super Admin without the Pro cap is denied.' );<br>self::assertFalse( Updatronix_Update_Engine::can_manage( false, false, false, true ), 'No capability is denied.' ); |
| `test_can_manage_live_capability_and_license` | test can manage live capability and license | self::assertFalse( Updatronix_Update_Engine::can_manage(), 'A subscriber without the cap is denied.' );<br>self::assertTrue( Updatronix_Update_Engine::can_manage() );<br>self::assertFalse( Updatronix_Update_Engine::can_manage(), 'An inactive license is denied.' ); |
| `test_no_queue_cron_or_rest_routes` | test no queue cron or rest routes | self::assertFalse( wp_next_scheduled( 'updatronix_pro_process_queue' ) );<br>self::assertStringNotContainsString( '/queue', $route );<br>self::assertStringNotContainsString( '/flow/run', $route ); |
| `test_main_site_guard` | test main site guard | self::assertTrue( Updatronix_Update_Engine_Bootstrap::is_main_site() );<br>self::markTestSkipped( 'The subsite denial needs a Multisite test run (WP_MULTISITE=1).' );<br>self::assertFalse( Updatronix_Update_Engine_Bootstrap::is_main_site() ); |

#### `tests/Integration/UpdateEngineSmokeTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_free_and_pro_are_loaded` | test free and pro are loaded | self::assertTrue( defined( 'UPDATRONIX_VERSION' ) );<br>self::assertTrue( defined( 'UPDATRONIX_PRO_VERSION' ) );<br>self::assertTrue( class_exists( 'Updatronix_Logger' ) );<br>self::assertTrue( class_exists( 'Updatronix_Update_Job' ) ); |
| `test_queue_table_and_schema_option_are_gone` | test queue table and schema option are gone | self::assertNull( $found, 'No queue table survives the upgrade.' );<br>self::assertFalse( get_site_option( 'updatronix_pro_queue_db_version', false ) ); |
| `test_pass_option_is_absent_and_non_autoloaded_when_written` | test pass option is absent and non autoloaded when written | self::assertNull( Updatronix_Update_Pass::load() );<br>self::assertFalse( Updatronix_Update_Pass::exists() );<br>self::assertNotNull( $pass );<br>self::assertSame( 'off', $autoload, 'The pass option must not autoload.' ); |

#### `tests/Integration/UpdateFlowRestTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_capability_matrix` | test capability matrix | self::assertFalse( Updatronix_Update_Flow::check_manage_permission( true, false, true ), 'Multisite non-Super-Admin is denied.' );<br>self::assertTrue( Updatronix_Update_Flow::check_manage_permission( true, true, true ), 'Multisite Super Admin with the cap is allowed.' );<br>self::assertFalse( Updatronix_Update_Flow::check_manage_permission( true, true, false ), 'Missing capability is denied.' );<br>self::assertFalse( Updatronix_Update_Flow::check_manage_permission( false, false, false ) ); |
| `test_subscriber_is_denied` | test subscriber is denied | self::assertFalse( Updatronix_Update_Flow::check_manage_permission() );<br>self::assertFalse( Updatronix_Update_Flow::check_permission() ); |
| `test_admin_with_capability_is_allowed` | test admin with capability is allowed | self::assertTrue( Updatronix_Update_Flow::check_manage_permission() );<br>self::assertTrue( Updatronix_Update_Flow::check_permission() ); |
| `test_inactive_license_is_denied` | test inactive license is denied | self::assertFalse( Updatronix_Update_Flow::check_permission() ); |
| `test_run_routes_are_not_registered` | test run routes are not registered | self::assertSame( 404, $run->get_status(), 'POST /flow/run must be gone.' );<br>self::assertSame( 404, $summary->get_status(), 'GET /flow/runs/{id} must be gone.' ); |
| `test_chains_route_reports_active_and_last_pass` | test chains route reports active and last pass | self::assertNotNull( $pass );<br>self::assertSame( 200, $response->get_status() );<br>self::assertArrayNotHasKey( 'frozen', $data );<br>self::assertSame( $pass->id(), $data['active_pass']['pass_id'] ); |

#### `tests/Integration/UpdatePassDeletionTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_migration_removes_the_legacy_queue_once` | test migration removes the legacy queue once | self::assertTrue( Updatronix_Update_Pass_Migration::run() );<br>self::assertFalse( $this->legacy_table_exists(), 'The queue table is dropped.' );<br>self::assertFalse( get_site_option( 'updatronix_pro_queue_db_version', false ) );<br>self::assertFalse( get_site_option( 'updatronix_pro_update_runs', false ) ); |
| `test_uninstall_cleans_the_pass_and_the_legacy_state` | test uninstall cleans the pass and the legacy state | self::assertNotNull( $pass );<br>self::assertFalse( Updatronix_Update_Pass::exists(), 'The pass option is deleted.' );<br>self::assertFalse( get_site_option( Updatronix_Update_Pass::OPTION, false ) );<br>self::assertFalse( get_site_option( Updatronix_Update_Pass::LAST_PASS_OPTION, false ) ); |
| `test_no_queue_surface_remains` | test no queue surface remains | self::assertFalse( class_exists( 'Updatronix_Update_Queue' ) );<br>self::assertFalse( class_exists( 'Updatronix_Queue_Scheduler' ) );<br>self::assertFalse( class_exists( 'Updatronix_Queue_REST' ) );<br>self::assertFalse( $this->legacy_table_exists() ); |

#### `tests/Integration/UpdatePassLiveFixtureTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_two_plugins_install_one_per_invocation` | test two plugins install one per invocation | self::assertSame( 2, $result['installs'], 'One native install per invocation.' );<br>self::assertSame( 2, count( $result['per_request'] ), 'Two invocations, not a grouped run.' );<br>self::assertSame( 2, count( $this->mails ), 'Two native emails, one per item.' );<br>self::assertSame( '1.1.0', $this->plugin_version( $alpha ) ); |
| `test_plugin_and_theme_are_serialized` | test plugin and theme are serialized | self::assertSame( 2, $result['installs'], (string) wp_json_encode( $result ) );<br>self::assertSame( 2, count( $this->mails ), (string) wp_json_encode( $result ) );<br>self::assertSame( '1.1.0', $this->plugin_version( $plugin ) );<br>self::assertSame( '1.1.0', $this->theme_version( $theme ) ); |
| `test_failure_is_skipped_and_the_next_pass_retries` | test failure is skipped and the next pass retries | self::assertSame( '1.0.0', $this->plugin_version( $alpha ), 'The failed item is unchanged for this pass.' );<br>self::assertSame( '1.1.0', $this->plugin_version( $bravo ), 'The next item still runs.' );<br>self::assertCount( 1, $this->pass_rows(), 'One pass summary.' );<br>self::assertSame( 1, (int) ( $last['failed'] ?? 0 ), 'The pass reports the failure.' ); |
| `test_held_item_never_bypasses_the_hold` | test held item never bypasses the hold | self::assertSame( '1.0.0', $this->plugin_version( $plugin ), 'The held item is not installed.' );<br>self::assertSame( 1, (int) ( $last['delayed'] ?? 0 ), 'The pass records the hold, not a failure.' );<br>self::assertSame( 0, (int) ( $last['failed'] ?? 0 ) ); |
| `test_version_locked_item_is_not_executed` | test version locked item is not executed | self::assertSame( '1.0.0', $this->plugin_version( $plugin ), 'A locked item is not installed.' );<br>self::assertFalse( Updatronix_Update_Pass::exists() );<br>self::assertSame( 1, (int) ( $last['blocked'] ?? 0 ), 'The pass records the lock as blocked.' ); |
| `test_pass_disabled_runs_the_native_loop` | test pass disabled runs the native loop | self::assertSame( '1.1.0', $this->plugin_version( $alpha ) );<br>self::assertSame( '1.1.0', $this->plugin_version( $bravo ) );<br>self::assertSame( 2, $result['installs'] );<br>self::assertCount( 1, $result['per_request'], 'One native invocation runs both items.' ); |
| `test_manual_update_during_a_pass_is_succeeded` | test manual update during a pass is succeeded | self::assertSame( '1.1.0', $this->plugin_version( $plugin ) );<br>self::assertFalse( Updatronix_Update_Pass::exists() );<br>self::assertSame( 0, (int) ( $last['failed'] ?? 0 ), 'An already-installed target is not a failure.' );<br>self::assertCount( 1, $this->pass_rows() ); |
| `test_lock_busy_waits_and_does_not_double_run` | test lock busy waits and does not double run | self::assertSame( '1.0.0', $this->plugin_version( $plugin ), 'The lock blocks the native run.' );<br>self::assertNotNull( $pass );<br>self::assertSame( 1, $pass->lock_waits(), 'The wait is recorded.' );<br>self::assertSame( Updatronix_Update_Pass::STATE_OFFER_ELIGIBLE, $pass->node( 'plugin:' . $plugin )['state'], 'A busy lock is not a failure.' ); |
| `test_stale_pass_is_replaced` | test stale pass is replaced | self::assertSame( '1.1.0', $this->plugin_version( $plugin ), 'The replacement pass installs the item.' );<br>self::assertGreaterThanOrEqual( 1, count( $this->pass_rows() ), 'The replacement pass writes its own summary.' );<br>self::assertCount( 0, $recovered ); |
| `test_item_removed_mid_pass_is_skipped` | test item removed mid pass is skipped | self::assertSame( '1.1.0', $this->plugin_version( $bravo ), 'The remaining item still installs.' );<br>self::assertFalse( Updatronix_Update_Pass::exists() ); |
| `test_no_duplicate_updated_row` | test no duplicate updated row | self::assertCount( 1, $rows, 'One row for the one item.' );<br>self::assertSame( 'update', $rows[0]->action_type );<br>self::assertSame( 'updated', $rows[0]->reason_code );<br>self::assertSame( 1, count( $this->mails ) ); |
| `test_admin_render_does_not_create_a_pass` | test admin render does not create a pass | self::assertNull( $forced, 'The native toggle payload is returned unchanged.' );<br>self::assertFalse( Updatronix_Update_Pass::exists(), 'A page render never builds a pass.' );<br>self::assertSame( '', Updatronix_Update_Pass_Gate::selection()['key'] );<br>self::assertNotNull( $pass ); |

#### `tests/Integration/UpdatePassMultisiteTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_pass_option_is_main_site_only` | test pass option is main site only | self::assertNotNull( $pass );<br>self::assertNotNull( Updatronix_Update_Pass::load() );<br>self::assertNull( Updatronix_Update_Pass::load(), 'A subsite has no pass option.' );<br>self::assertFalse( Updatronix_Update_Pass::exists() ); |
| `test_gate_is_main_site_only` | test gate is main site only | self::assertTrue( Updatronix_Update_Engine_Bootstrap::is_available(), 'The engine is available on the main site.' );<br>self::assertFalse( Updatronix_Update_Engine_Bootstrap::is_available(), 'No engine, no gate, on a subsite.' ); |

#### `tests/Integration/UpdateShield43Test.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_policy_blocks_an_incompatible_plugin` | test policy blocks an incompatible plugin | self::assertFalse( $allowed, 'The incompatible plugin is denied.' );<br>self::assertNotContains( $plugin, (array) get_site_option( 'auto_update_plugins', array() ) );<br>self::assertNotNull( Updatronix_Shield_Ledger::get( 'plugin', $plugin ) );<br>self::assertNotEmpty( $logs ); |
| `test_cancel_behavior_keeps_the_native_toggle` | test cancel behavior keeps the native toggle | self::assertFalse( Updatronix_Safe_Mode::filter_auto_update_plugin( true, $item ) );<br>self::assertContains( $plugin, (array) get_site_option( 'auto_update_plugins', array() ) );<br>self::assertNull( Updatronix_Shield_Ledger::get( 'plugin', $plugin ) );<br>self::assertNotEmpty( $logs ); |
| `test_blocked_theme_fixture` | test blocked theme fixture | self::assertFalse( Updatronix_Safe_Mode::filter_auto_update_theme( true, $item ) ); |
| `test_blocked_history_is_db_level_and_paginated` | test blocked history is db level and paginated | self::assertSame( 3, $page_one['total'], 'The 40-day-old row is excluded by the DB filter.' );<br>self::assertSame( 2, $page_one['total_pages'] );<br>self::assertCount( 2, $page_one['items'] );<br>self::assertCount( 1, $page_two['items'] ); |
| `test_shield_payload_exposes_composer_and_abandoned_state` | test shield payload exposes composer and abandoned state | self::assertArrayHasKey( 'version_control_state', $data );<br>self::assertArrayHasKey( 'abandoned_state', $data );<br>self::assertArrayHasKey( 'reenable_state', $data );<br>self::assertArrayHasKey( 'pass_state', $data, 'The queue payload is replaced by the read-only pass state.' ); |
| `test_reenable_status_and_same_target_stays_manual` | test reenable status and same target stays manual | self::assertTrue( $status['enabled'] );<br>self::assertSame( 'eligible', $item['state'] );<br>self::assertTrue( $item['eligible'] );<br>self::assertSame( '2.0.0', $item['current_target'] ); |

#### `tests/Integration/VersionLockAdminTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_plugin_toggle_is_locked` | test plugin toggle is locked | self::assertStringContainsString( 'updatronix-version-lock-toggle', $html );<br>self::assertStringContainsString( 'managed via Git', $html );<br>self::assertStringNotContainsString( 'Enable auto-updates', $html ); |
| `test_theme_template_gains_a_locked_branch` | test theme template gains a locked branch | self::assertStringContainsString( 'data.updatronixVersionLocked', $template );<br>self::assertStringContainsString( 'aria-describedby', $template );<br>self::assertStringContainsString( 'data.updatronixVersionLockLabel', $template );<br>self::assertStringNotContainsString( 'aria-disabled', $template ); |
| `test_auto_update_data_is_marked` | test auto update data is marked | self::assertTrue( $data['auto_update_locked'] );<br>self::assertStringContainsString( 'managed via Git', $data['auto_update_locked_reason'] );<br>self::assertSame( 'Git', $data['auto_update_locked_manager'] ); |
| `test_localized_map_contains_the_locked_item` | test localized map contains the locked item | self::assertTrue( $data['enabled'] );<br>self::assertTrue( $data['full'] );<br>self::assertArrayHasKey( 'plugin:' . $this->plugin, $data['items'] );<br>self::assertSame( 'Git', $data['items'][ 'plugin:' . $this->plugin ]['manager'] ); |
| `test_option_write_guard_strips_locked_items` | test option write guard strips locked items | self::assertSame( array( $this->free_plugin ), array_values( $value ) ); |
| `test_toggle_guard_returns_wp_error` | test toggle guard returns wp error | self::assertTrue( is_wp_error( $result ) );<br>self::assertSame( 'updatronix_version_locked', $result->get_error_code() ); |

#### `tests/Integration/VersionLockMultisiteTest.php`

| Test | Pins | Assertion |
|---|---|---|
| `test_network_option_and_main_site_scope` | test network option and main site scope | self::markTestSkipped( 'VersionLockMultisiteTest requires WP_MULTISITE=1.' );<br>self::assertTrue( $settings['safe_mode'] );<br>self::assertTrue( $settings['version_control_lock'] );<br>self::assertTrue( $settings['version_lock_manual_updates'] ); |

### JS (80 cases)

#### `tests/js/adminReload.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `stores a message under the reload key` | stores a message under the reload key | expect( storage.values[ ADMIN_NOTICE_KEY ] ).toBe( 'License activated.' ); |
| `ignores empty and non-string messages` | ignores empty and non-string messages | expect( storage.values ).toEqual( {} ); |
| `does not throw when storage is blocked` | does not throw when storage is blocked | expect( () => queueAdminNotice( 'Message', storage ) ).not.toThrow(); |
| `returns the message and removes it` | returns the message and removes it | expect( takeAdminNotice( storage ) ).toBe( 'License deactivated.' );<br>expect( storage.values ).toEqual( {} ); |
| `returns null when no message is pending` | returns null when no message is pending | expect( takeAdminNotice( makeStorage() ) ).toBeNull(); |
| `does not throw when storage is blocked` | does not throw when storage is blocked | expect( takeAdminNotice( storage ) ).toBeNull(); |
| `writes the message before reloading` | writes the message before reloading | expect( storage.values[ ADMIN_NOTICE_KEY ] ).toBe( 'License activated.' );<br>expect( reload ).toHaveBeenCalledTimes( 1 ); |
| `still reloads when storage is blocked` | still reloads when storage is blocked | expect( reload ).toHaveBeenCalledTimes( 1 ); |

#### `tests/js/flowRunState.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `exports no manual-run helper` | exports no manual-run helper | expect( flowRunState.canRunChain ).toBeUndefined();<br>expect( flowRunState.isRunActive ).toBeUndefined();<br>expect( flowRunState.runCounts ).toBeUndefined(); |
| `distinguishes a waiting and a blocked predecessor` | distinguishes a waiting and a blocked predecessor | expect( stepStatus( { state: 'waiting_for_predecessor', blocking: false, } ) ).toBe( 'waiting' );<br>expect( stepStatus( { state: 'waiting_for_predecessor', blocking: true } ) ).toBe( 'blocked' ); |
| `maps a hold to waiting and a lock to blocked` | maps a hold to waiting and a lock to blocked | expect( stepStatus( { state: 'offer_held' } ) ).toBe( 'waiting' );<br>expect( stepStatus( { state: 'locked' } ) ).toBe( 'blocked' ); |
| `maps done, failed, and unknown states` | maps done, failed, and unknown states | expect( stepStatus( { state: 'already_latest' } ) ).toBe( 'succeeded' );<br>expect( stepStatus( { state: 'succeeded' } ) ).toBe( 'succeeded' );<br>expect( stepStatus( { state: 'failed' } ) ).toBe( 'failed' );<br>expect( stepStatus( { state: 'uncertain' } ) ).toBe( 'failed' ); |
| `uses Blocked for a permanent reason and Waiting for a transient one` | uses Blocked for a permanent reason and Waiting for a transient one | expect( stepLabel( { state: 'waiting_for_predecessor', blocking: true } ) ).toBe( 'Blocked' );<br>expect( stepLabel( { state: 'waiting_for_predecessor', blocking: false } ) ).toBe( 'Waiting' ); |
| `names a lock and a hold` | names a lock and a hold | expect( stepLabel( { state: 'locked' } ) ).toBe( 'Locked' );<br>expect( stepLabel( { state: 'offer_held' } ) ).toBe( 'Waiting for its hold' ); |
| `maps statuses to intents` | maps statuses to intents | expect( badgeIntent( 'succeeded' ) ).toBe( 'stable' );<br>expect( badgeIntent( 'failed' ) ).toBe( 'high' );<br>expect( badgeIntent( 'blocked' ) ).toBe( 'high' );<br>expect( badgeIntent( 'waiting' ) ).toBe( 'informational' ); |
| `maps a blocked node to the high intent` | maps a blocked node to the high intent | expect( stepBadgeIntent( { state: 'waiting_for_predecessor', blocking: true, } ) ).toBe( 'high' ); |
| `returns the predecessor label, reason, eligible time, and manager` | returns the predecessor label, reason, eligible time, and manager | expect( detail.predecessor ).toBe( 'Gateway' );<br>expect( detail.reason ).toBe( 'Its predecessor is managed by another tool.' );<br>expect( detail.eligibleAt ).toBe( 1700000000 );<br>expect( detail.manager ).toBe( 'Composer' ); |
| `returns empty fields for a node without a wait` | returns empty fields for a node without a wait | expect( waitingDetail( { state: 'offer_eligible' } ) ).toEqual( { predecessor: '', reason: '', eligibleAt: 0, manager: '', } ); |
| `returns the eight counters in display order and defaults to zero` | returns the eight counters in display order and defaults to zero | expect( lastPassCounts( { counts: { done: 2, failed: 1, waiting: 3 } } ) ).toEqual( [ { key: 'done', value: 2 }, { key: 'blocked', value: 0 }, { key: 'waiting', value: 3 }, { key: 'delayed', value: 0 }, { key: 'skipped'… |
| `handles a null summary` | handles a null summary | expect( lastPassCounts( null ).every( ( counter ) => counter.value === 0 ) ).toBe( true ); |
| `labels the first waiting reason and names the predecessor` | labels the first waiting reason and names the predecessor | **NONE** |
| `reads nodes from an active pass or a last-pass summary` | reads nodes from an active pass or a last-pass summary | expect( passNodes( { nodes: { a: {} } } ) ).toEqual( { a: {} } );<br>expect( passNodes( { flow_nodes: { b: {} } } ) ).toEqual( { b: {} } );<br>expect( passNodes( null ) ).toEqual( {} ); |
| `detects a last pass` | detects a last pass | expect( hasLastPass( { pass_id: 'abc' } ) ).toBe( true );<br>expect( hasLastPass( {} ) ).toBe( false );<br>expect( hasLastPass( null ) ).toBe( false ); |
| `truncates long ids and handles empty values` | truncates long ids and handles empty values | expect( shortRunId( 'abcdef0123456789' ) ).toBe( 'abcdef012345…' );<br>expect( shortRunId( 'abc' ) ).toBe( 'abc' );<br>expect( shortRunId( null ) ).toBe( '—' ); |
| `formats a timestamp and ignores zero` | formats a timestamp and ignores zero | expect( eligibleLabel( 0 ) ).toBe( '' );<br>expect( eligibleLabel( 1700000000 ) ).not.toBe( '' ); |

#### `tests/js/licenseKey.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `accepts the real Updatronix 3000 key` | accepts the real Updatronix 3000 key | expect( isValidLicenseKey( REAL_KEY ) ).toBe( true ); |
| `accepts uppercase payloads` | accepts uppercase payloads | expect( isValidLicenseKey( 'UPD-PRO-27AD60FF-E196C456-72DEED14' ) ).toBe( true ); |
| `rejects the legacy 4x4 shape` | rejects the legacy 4x4 shape | expect( isValidLicenseKey( 'ABCD-EFGH-IJKL-1234' ) ).toBe( false ); |
| `rejects a key with the wrong number of groups` | rejects a key with the wrong number of groups | expect( isValidLicenseKey( 'UPD-PRO-27ad60ff-e196c456' ) ).toBe( false ); |
| `rejects a key with a short group` | rejects a key with a short group | expect( isValidLicenseKey( 'UPD-PRO-27ad60ff-e196c456-72deed1' ) ).toBe( false ); |
| `ignores surrounding whitespace` | ignores surrounding whitespace | expect( isValidLicenseKey( ` ${ REAL_KEY } ` ) ).toBe( true ); |
| `trims but preserves the payload case` | trims but preserves the payload case | expect( normalizeLicenseKey( ` ${ REAL_KEY } ` ) ).toBe( REAL_KEY ); |
| `returns an empty string for non-string input` | returns an empty string for non-string input | expect( normalizeLicenseKey( null ) ).toBe( '' ); |
| `uses the real key shape in the placeholder` | uses the real key shape in the placeholder | expect( LICENSE_KEY_PLACEHOLDER ).toBe( 'UPD-PRO-XXXXXXXX-XXXXXXXX-XXXXXXXX' ); |
| `uses the real key shape in the mask` | uses the real key shape in the mask | expect( LICENSE_KEY_MASK ).toBe( 'UPD-PRO-••••••••-••••••••-••••••••' ); |

#### `tests/js/shieldState.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `names the manager and evidence` | names the manager and evidence | expect( versionLockItemLabel( { name: 'Acme', manager: 'Git', evidence: '.git at the item root', } ) ).toBe( 'Acme — Git (.git at the item root)' );<br>expect( versionLockItemLabel( { id: 'acme/acme.php', manager: 'none' } ) ).toBe( 'acme/acme.php' ); |
| `returns an empty unavailable state for null` | returns an empty unavailable state for null | expect( state.available ).toBe( false );<br>expect( state.lock ).toBe( false );<br>expect( state.checked ).toBe( 0 );<br>expect( state.total ).toBe( 0 ); |
| `uses the active gate for the badge when Safe Mode is off` | uses the active gate for the badge when Safe Mode is off | expect( versionControlBadge( { lock: true, active: false, requires_shield: true, } ).label ).toBe( 'Off' );<br>expect( versionControlBadge( { lock: true, active: true } ).label ).toBe( 'Locked' ); |
| `normalizes the Version Lock gates and manual toggle` | normalizes the Version Lock gates and manual toggle | expect( state.active ).toBe( true );<br>expect( state.requiresShield ).toBe( false );<br>expect( state.manualUpdates ).toBe( true ); |
| `normalizes counts and deduplicates unknown items by type and id` | normalizes counts and deduplicates unknown items by type and id | expect( state.available ).toBe( true );<br>expect( state.lock ).toBe( true );<br>expect( state.checked ).toBe( 12 );<br>expect( state.total ).toBe( 15 ); |
| `reflects the Composer lock switch` | reflects the Composer lock switch | expect( versionControlBadge( { lock: true } ) ).toEqual( { label: 'Locked', intent: 'informational', } );<br>expect( versionControlBadge( null ) ).toEqual( { label: 'Off', intent: 'draft', } ); |
| `falls back to 365 days and empty arrays` | falls back to 365 days and empty arrays | expect( state.available ).toBe( false );<br>expect( state.thresholdDays ).toBe( 365 );<br>expect( state.thresholdLabel ).toBe( '365 days' );<br>expect( state.abandoned ).toEqual( [] ); |
| `keeps the backend threshold and counts` | keeps the backend threshold and counts | expect( state.thresholdDays ).toBe( 400 );<br>expect( state.thresholdLabel ).toBe( '400 days' );<br>expect( state.flaggedCount ).toBe( 1 );<br>expect( state.unknownCount ).toBe( 2 ); |
| `uses the display name, then the id, and a numeric day count` | uses the display name, then the id, and a numeric day count | expect( abandonedItemLabel( { name: 'Old plugin', id: 'old/old.php', days: '500', } ) ).toBe( 'Old plugin — 500 days since the last release' );<br>expect( abandonedItemLabel( { id: 'theme-b' } ) ).toBe( 'theme-b — 0 days since the last release' ); |
| `counts only eligible items` | counts only eligible items | expect( state.available ).toBe( true );<br>expect( state.enabled ).toBe( true );<br>expect( state.eligibleCount ).toBe( 2 ); |
| `returns an empty unavailable state for null` | returns an empty unavailable state for null | expect( state.available ).toBe( false );<br>expect( state.eligibleCount ).toBe( 0 );<br>expect( state.behavior ).toBe( 'disable' );<br>expect( reenableItemState( { state } ) ).toMatchObject( { label, intent, } ); |
| `keeps an unknown state visible instead of pretending it is eligible` | keeps an unknown state visible instead of pretending it is eligible | expect( state.label ).toBe( 'future_state' );<br>expect( state.intent ).toBe( 'draft' ); |
| `keeps disable and cancel distinct` | keeps disable and cancel distinct | expect( options.map( ( option ) => option.value ) ).toEqual( [ 'disable', 'cancel', ] );<br>expect( options[ 0 ].label ).not.toBe( options[ 1 ].label );<br>expect( options[ 0 ].description ).not.toBe( options[ 1 ].description ); |

#### `tests/js/versionLockAugment.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `looks up an item by exact type and id` | looks up an item by exact type and id | expect( lockFor( data, 'plugin', 'acme/acme.php' ) ).toBeTruthy();<br>expect( lockFor( data, 'plugin', 'other/other.php' ) ).toBeNull();<br>expect( lockFor( { ...data, enabled: false }, 'plugin', 'acme/acme.php' ) ).toBeNull(); |
| `adds one notice to a plugin row` | adds one notice to a plugin row | expect( notice ).not.toBeNull();<br>expect( notice.textContent ).toContain( 'managed via Git' );<br>expect( document.querySelector( 'a.update-link' ).getAttribute( 'href' ) ).toBe( '/update' ); |
| `removes the manual link in full lock` | removes the manual link in full lock | expect( link.hasAttribute( 'href' ) ).toBe( false );<br>expect( link.getAttribute( 'aria-disabled' ) ).toBe( 'true' ); |
| `does not disable the bulk-select checkbox or a theme action` | does not disable the bulk-select checkbox or a theme action | expect( document.querySelector( 'input[type="checkbox"]' ).disabled ).toBe( false );<br>expect( document.querySelector( 'a.button' ).getAttribute( 'href' ) ).toBe( '/activate' ); |
| `disables the single-site theme Update button in full lock` | disables the single-site theme Update button in full lock | expect( document.querySelector( 'button.button-link' ).disabled ).toBe( true );<br>expect( document.querySelector( 'a.button' ).getAttribute( 'href' ) ).toBe( '/activate' ); |
| `augments a theme card and a network theme row` | augments a theme card and a network theme row | expect( document.querySelectorAll( '[data-updatronix-version-lock-notice]' ) ).toHaveLength( 2 ); |
| `augments the update-core plugin and theme tables by exact value` | augments the update-core plugin and theme tables by exact value | expect( boxes[ 0 ].disabled ).toBe( true );<br>expect( boxes[ 0 ].checked ).toBe( false );<br>expect( boxes[ 1 ].disabled ).toBe( true );<br>expect( boxes[ 2 ].disabled ).toBe( false ); |
| `ignores plugin rows that also carry data-slug and no-ops without data` | ignores plugin rows that also carry data-slug and no-ops without data | expect( document.querySelector( '[data-updatronix-version-lock-notice]' ) ).toBeNull();<br>expect( document.querySelector( 'a.update-link' ).getAttribute( 'href' ) ).toBe( '/update' ); |
| `is idempotent and no-ops when disabled` | is idempotent and no-ops when disabled | expect( document.querySelectorAll( '[data-updatronix-version-lock-notice]' ) ).toHaveLength( 1 );<br>expect( document.querySelector( '[data-updatronix-version-lock-notice]' ) ).toBeNull(); |

#### `tests/js/whiteLabelDraft.test.js`

| Test | Pins | Assertion |
|---|---|---|
| `keeps white label opt-in` | keeps white label opt-in | expect( DEFAULT_SETTINGS.replace_branding ).toBe( false );<br>expect( DEFAULT_SETTINGS.apply_to_emails ).toBe( false ); |
| `exposes the server-aligned limits` | exposes the server-aligned limits | expect( DEFAULT_LIMITS.brand_name ).toBe( 60 );<br>expect( DEFAULT_LIMITS.menu_label ).toBe( 40 );<br>expect( DEFAULT_LIMITS.logo_bytes ).toBe( 1048576 );<br>expect( DEFAULT_LIMITS.logo_min_px ).toBe( 64 ); |
| `shows the license spinner while the status is loading` | shows the license spinner while the status is loading | expect( getPageState( { ...base, licenseLoading: true } ) ).toBe( 'license-loading' ); |
| `shows the status error before the inactive state` | shows the status error before the inactive state | expect( getPageState( { ...base, status: 'inactive', statusError: 'Service unavailable', } ) ).toBe( 'status-error' ); |
| `shows the inactive state for a non-active license` | shows the inactive state for a non-active license | expect( getPageState( { ...base, status: 'inactive' } ) ).toBe( 'inactive' ); |
| `shows the load error before the spinner when settings are missing` | shows the load error before the spinner when settings are missing | expect( getPageState( { ...base, loading: false, error: 'Could not load', settings: null, } ) ).toBe( 'load-error' ); |
| `shows the spinner while settings load` | shows the spinner while settings load | expect( getPageState( { ...base, loading: true, settings: null } ) ).toBe( 'loading' ); |
| `shows the ready state when settings are present` | shows the ready state when settings are present | expect( getPageState( base ) ).toBe( 'ready' ); |
| `compares the draft with the saved snapshot` | compares the draft with the saved snapshot | expect( isDirty( SETTINGS, { ...SETTINGS } ) ).toBe( false );<br>expect( isDirty( { ...SETTINGS, brand_name: 'Other' }, SETTINGS ) ).toBe( true ); |
| `treats a missing snapshot as clean` | treats a missing snapshot as clean | expect( isDirty( null, SETTINGS ) ).toBe( false );<br>expect( isDirty( SETTINGS, null ) ).toBe( false ); |
| `matches the light menu palette case-insensitively` | matches the light menu palette case-insensitively | expect( isLightMenuColor( '#F0F0F1' ) ).toBe( true );<br>expect( isLightMenuColor( '#f0f0f1' ) ).toBe( true );<br>expect( isLightMenuColor( '#1E1E1E' ) ).toBe( false );<br>expect( isLightMenuColor( null ) ).toBe( false ); |
| `accepts PNG, JPEG, and WebP` | accepts PNG, JPEG, and WebP | expect( validateLogoFile( { type: 'image/png', size: 100 } ) ).toEqual( { ok: true, reason: '' } );<br>expect( validateLogoFile( { type: 'image/jpeg', size: 100 } ) ).toEqual( { ok: true, reason: '' } );<br>expect( validateLogoFile( { type: 'image/webp', size: 100 } ) ).toEqual( { ok: true, reason: '' } ); |
| `rejects SVG and other types` | rejects SVG and other types | expect( validateLogoFile( { type: 'image/svg+xml', size: 100 } ) ).toEqual( { ok: false, reason: 'type' } );<br>expect( validateLogoFile( { type: 'image/gif', size: 100 } ) ).toEqual( { ok: false, reason: 'type' } ); |
| `rejects files over the size limit` | rejects files over the size limit | expect( validateLogoFile( { type: 'image/png', size: DEFAULT_LIMITS.logo_bytes + 1, } ) ).toEqual( { ok: false, reason: 'size' } ); |
| `rejects a missing file` | rejects a missing file | expect( validateLogoFile( null ) ).toEqual( { ok: false, reason: 'missing', } ); |
| `accepts the minimum and larger images` | accepts the minimum and larger images | expect( isLogoDimensionsValid( { width: 64, height: 64 } ) ).toBe( true );<br>expect( isLogoDimensionsValid( { width: 128, height: 64 } ) ).toBe( true ); |
| `rejects an image below the minimum on either edge` | rejects an image below the minimum on either edge | expect( isLogoDimensionsValid( { width: 63, height: 64 } ) ).toBe( false );<br>expect( isLogoDimensionsValid( { width: 64, height: 63 } ) ).toBe( false ); |
| `lets unknown dimensions pass to the server` | lets unknown dimensions pass to the server | expect( isLogoDimensionsValid( null ) ).toBe( true ); |
| `formats the server limits` | formats the server limits | expect( formatBytes( 1048576 ) ).toBe( '1 MB' );<br>expect( formatBytes( 1536 ) ).toBe( '1.5 KB' );<br>expect( formatBytes( 500 ) ).toBe( '500 B' ); |
| `reflects the draft when replacement is on` | reflects the draft when replacement is on | expect( preview.displayBrand ).toBe( 'Acme Studio' );<br>expect( preview.displayMenu ).toBe( 'Site updates' );<br>expect( preview.previewColor ).toBe( '#0F766E' );<br>expect( preview.previewMenuColor ).toBe( '#16233A' ); |
| `falls back to Updatronix when replacement is off` | falls back to Updatronix when replacement is off | expect( preview.displayBrand ).toBe( 'Updatronix' );<br>expect( preview.displayMenu ).toBe( 'Updatronix' );<br>expect( preview.previewColor ).toBe( DEFAULT_SETTINGS.brand_color );<br>expect( preview.previewMenuColor ).toBe( DEFAULT_SETTINGS.menu_color ); |
| `uses the site name when the email switch is off or the brand is empty` | uses the site name when the email switch is off or the brand is empty | expect( emailsOff.showEmailBrand ).toBe( false );<br>expect( emailsOff.emailBrandName ).toBe( 'Example Site' );<br>expect( emptyBrand.showEmailBrand ).toBe( false );<br>expect( emptyBrand.emailBrandName ).toBe( 'Example Site' ); |
| `marks the light menu palette` | marks the light menu palette | expect( preview.lightMenu ).toBe( true ); |

### E2E (28 cases)

#### `tests/e2e/pro-000-gating.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `shows Updatronix 3000 but hides Shield, Flow, and White label` | shows Updatronix 3000 but hides Shield, Flow, and White label | expect( page.locator( '.updatronix-license-page' ) ).toBeVisible();<br>expect( page.getByLabel( 'License key' ) ).toBeVisible();<br>expect( rail.filter( { hasText: 'Updatronix 3000' } ).first() ).toBeVisible();<br>expect( rail.filter( { hasText: /Update Shield\|Update Flow\|White label/i } ) ).toHaveCount( 0 ); |
| `hides the Pro export formats on the Free log export` | hides the Pro export formats on the Free log export | expect( page.getByText( 'Export update logs' ) ).toBeVisible();<br>expect( page.getByText( /CSV\|JSON\|XML/i ) ).toHaveCount( 0 ); |

#### `tests/e2e/pro-001-license.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `rejects an invalid key, then activates the real key` | rejects an invalid key, then activates the real key | expect( page.getByText( /format/i ).first() ).toBeVisible();<br>expect( page.getByText( 'Updatronix 3000 is active' ) ).toBeVisible( { timeout: 45_000 } );<br>expect( page.getByText( /UPD-PRO-.*ed14/ ).first() ).toBeVisible();<br>expect( rail.filter( { hasText: 'Update Shield' } ).first() ).toBeVisible(); |

#### `tests/e2e/pro-100-white-label.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `renders and saves the brand draft` | renders and saves the brand draft | expect( page.getByText( 'Brand', { exact: true } ).first() ).toBeVisible();<br>expect( page.getByLabel( 'Brand name' ) ).toBeVisible();<br>expect( page.getByText( 'Admin menu' ).first() ).toBeVisible();<br>expect( page.getByText( 'Email', { exact: true } ).first() ).toBeVisible(); |
| `resets the branding draft` | resets the branding draft | expect( page.getByText( 'Reset branding?' ) ).toBeVisible();<br>expect( page .getByText( 'Branding reset. Save your changes to apply it.' ) .first() ).toBeVisible(); |
| `rejects a non-image logo` | rejects a non-image logo | expect( page.getByRole( 'alert' ).first() ).toBeVisible(); |
| `sanitizes the saved brand name` | sanitizes the saved brand name | expect( page.getByText( 'White label settings saved.' ).first() ).toBeVisible();<br>expect( page.getByLabel( 'Brand name' ) ).toHaveValue( 'E2E' ); |

#### `tests/e2e/pro-101-shield.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `renders protection and Version Lock without a queue surface` | renders protection and Version Lock without a queue surface | expect( page.getByText( 'Protection', { exact: true } ).first() ).toBeVisible();<br>expect( page.getByText( 'Version Lock', { exact: true } ).first() ).toBeVisible();<br>expect( page.getByText( 'Update queue', { exact: true } ) ).toHaveCount( 0 );<br>expect( page.getByRole( 'switch', { name: 'Queue automatic updates' } ) ).toHaveCount( 0 ); |

#### `tests/e2e/pro-102-flow.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `renders the chains surface` | renders the chains surface | expect( page.getByText( 'Chains', { exact: true } ).first() ).toBeVisible();<br>expect( page.getByText( 'New update chain' ).first() ).toBeVisible(); |
| `has no manual run and reads the pass` | has no manual run and reads the pass | expect( page.getByText( 'E2E Chain', { exact: true } ).first() ).toBeVisible();<br>expect( page.getByRole( 'button', { name: 'Run now' } ) ).toHaveCount( 0 );<br>expect( page.getByText( /Flow execution is paused/i ) ).toHaveCount( 0 );<br>expect( page.getByText( /Enabled chains order automatic updates/i ).first() ).toBeVisible(); |
| `shows the read-only last pass and its waiting step` | shows the read-only last pass and its waiting step | expect( page.getByText( 'Last pass', { exact: true } ).first() ).toBeVisible();<br>expect( page.getByText( /Pass 0123456789ab/ ).first() ).toBeVisible();<br>expect( page.getByText( /Done: 0/ ).first() ).toBeVisible();<br>expect( page.getByText( /Waiting: 1/ ).first() ).toBeVisible(); |

#### `tests/e2e/pro-103-exports.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `offers CSV, JSON, and XML` | offers CSV, JSON, and XML | expect( page.getByText( 'Export update logs' ) ).toBeVisible();<br>expect( format ).toBeVisible();<br>expect( page.getByRole( 'option', { name: /CSV/i } ).first() ).toBeVisible();<br>expect( page.getByRole( 'option', { name: /JSON/i } ).first() ).toBeVisible(); |
| `downloads CSV, JSON, and XML reports` | downloads CSV, JSON, and XML reports | expect( download.suggestedFilename() ).toMatch( new RegExp( `\\.${ extension }$`, 'i' ) ); |

#### `tests/e2e/pro-104-changelog.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `opens the plugin changelog from log details` | opens the plugin changelog from log details | expect( link ).toBeVisible();<br>expect( link ).toHaveAttribute( 'href', /#changelog/ ); |
| `opens theme and core changelog variants` | opens theme and core changelog variants | expect( page.getByRole( 'link', { name: /Open changelog/i } ) ).toHaveAttribute( 'href', /themes\/twentytwentyfour\/#changelog/ );<br>expect( page.getByRole( 'link', { name: /Open release notes/i } ) ).toHaveAttribute( 'href', /wordpress\.org\/download\/releases/ ); |

#### `tests/e2e/pro-105-external-log.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `renders FTP, SFTP, SSH, and CLI entries` | renders FTP, SFTP, SSH, and CLI entries | expect( page.getByText( `E2E ${ source } item` ).first() ).toBeVisible(); |

#### `tests/e2e/pro-106-version-lock.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `locks the native row and names the manager on the Shield card` | locks the native row and names the manager on the Shield card | expect( page.getByText( 'Version Lock', { exact: true } ).first() ).toBeVisible();<br>expect( page.getByText( 'Block manual updates', { exact: true } ).first() ).toBeVisible();<br>expect( page.getByText( 'Settings saved' ).first() ).toBeVisible();<br>expect( page.getByText( /— Git/ ).first() ).toBeVisible( { timeout: 15_000, } ); |

#### `tests/e2e/pro-107-version-lock-matrix.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `Shield card: manager labels, unverified list, Requires Update Shield` | Shield card: manager labels, unverified list, Requires Update Shield | expect( page.getByText( 'VL Matrix Locked — Git', { exact: false } ).first() ).toBeVisible();<br>expect( page.getByText( /could not be inspected/i ).first() ).toBeVisible();<br>expect( page.getByText( 'VL Matrix Unknown', { exact: false } ).first() ).toBeVisible();<br>expect( page.getByText( 'Requires Update Shield', { exact: false } ).first() ).toBeVisible(); |
| `plugins.php full lock: notice, manager, toggle lock, no update link` | plugins.php full lock: notice, manager, toggle lock, no update link | expect( locked ).toContainText( 'The update of this item is managed via Git.' );<br>expect( locked.locator( '.updatronix-version-lock-toggle' ) ).toContainText( 'managed via Git' );<br>expect( lockedLinks.first() ).not.toHaveAttribute( 'href', /.+/ );<br>expect( page.locator( `tr[data-plugin="${ FREE }"] a.update-link` ).first() ).toHaveAttribute( 'href', /update\.php/ ); |
| `themes.php single-site: theme card lock and Update button disabled` | themes.php single-site: theme card lock and Update button disabled | expect( card ).toBeVisible();<br>expect( updateButton ).toBeVisible();<br>expect( updateButton ).toBeDisabled();<br>expect( page .locator( '.theme-overlay .updatronix-version-lock-toggle' ) .first() ).toContainText( 'managed via Git' ); |
| `update-core full lock: plugin and theme rows, checkbox removed` | update-core full lock: plugin and theme rows, checkbox removed | expect( lockedBox ).toBeDisabled();<br>expect( page.getByText( 'VL Matrix Locked', { exact: false } ).first() ).toBeVisible();<br>expect( page .getByText( 'The update of this item is managed via Git.' ) .first() ).toBeVisible();<br>expect( page.locator( `input[type="checkbox"][value="${ THEME }"]` ) ).toBeDisabled(); |
| `Auto updates: locked toggle disabled with the manager reason` | Auto updates: locked toggle disabled with the manager reason | expect( page.getByText( 'VL Matrix Locked', { exact: false } ).first() ).toBeVisible();<br>expect( page .getByText( 'The update of this item is managed via Git. Please update through your manager.' ) .first() ).toBeVisible();<br>expect( page.getByRole( 'switch', { name: 'Auto-updates for VL Matrix Locked', } ) ).toBeDisabled(); |
| `manual toggle off: manual update remains available` | manual toggle off: manual update remains available | expect( page .locator( `tr[data-plugin="${ LOCKED }"] a.update-link` ) .first() ).toHaveAttribute( 'href', /update\.php/ );<br>expect( locked ).toContainText( 'Automatic updates for this item are managed via Git. Manual updates are allowed.' );<br>expect( locked.locator( '.updatronix-version-lock-toggle' ) ).toContainText( 'managed via Git' );<br>expect( page.locator( `input[type="checkbox"][value="${ LOCKED }"]` ) ).toBeEnabled(); |
| `manual toggle on: direct update URL is blocked` | manual toggle on: direct update URL is blocked | expect( page.getByText( /managed via Git/i ).first() ).toBeVisible();<br>expect( version ).toBe( '1.0.0' ); |
| `bulk: locked item skipped, sibling plugin updates` | bulk: locked item skipped, sibling plugin updates | expect( freeVersion ).toBe( '2.0.0' );<br>expect( lockedVersion ).toBe( '1.0.0' ); |
| `Shield off: Version Lock inactive and native behavior restored` | Shield off: Version Lock inactive and native behavior restored | expect( locked.locator( '.updatronix-version-lock-toggle' ) ).toHaveCount( 0 );<br>expect( page .locator( `tr[data-plugin="${ LOCKED }"] a.update-link` ) .first() ).toHaveAttribute( 'href', /update\.php/ );<br>expect( locked.locator( '.updatronix-version-lock-notice' ) ).toHaveCount( 0 ); |

#### `tests/e2e/pro-200-smoke.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `renders the license destination` | renders the license destination | expect( page.locator( '#updatronix-settings' ) ).toBeVisible();<br>expect( page.locator( '.updatronix-license-page' ) ).toBeVisible(); |

#### `tests/e2e/pro-999-teardown.spec.js`

| Test | Pins | Assertion |
|---|---|---|
| `deactivates the license` | deactivates the license | expect( page.getByLabel( 'License key' ) ).toBeVisible(); |

