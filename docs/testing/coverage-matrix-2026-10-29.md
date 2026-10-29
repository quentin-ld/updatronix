# Coverage matrix — 2026-10-29

Current coverage per dimension in `tests/testbook/dimensions.yaml`. Status:
**covered** = a named test asserts the behavior; **partial** = one layer,
one value, or no combination; **gap** = no direct test found.

**Summary:** 50 covered, 33 partial, 5 gap.

## Dimensions

| Dimension | Owner | Risk | Status | Layers | Evidence |
|---|---|---|---|---|---|
| **free.settings** | | | | | |
| `free.logging_enabled` | free | high | partial | integration, e2e | SettingsUninstallOptInTest, free-settings.spec.js |
| `free.notifications_mode` | free | high | covered | integration, e2e | NotificationsModeDisabledTest, free-settings.spec.js |
| `free.notify_enabled` | free | high | covered | integration | NotificationsRecipientSanitisationTest |
| `free.notify_emails` | free | high | covered | integration | NotificationsRecipientSanitisationTest |
| `free.notify_on` | free | medium | partial | integration | NotificationsModeDisabledTest (per-event matrix not exhaustive) |
| `free.dismissed_constants` | free | low | partial | integration | AutoUpdatesRestApiTest (dismiss path) |
| `free.delete_data_on_uninstall` | free | critical | covered | integration, e2e | SettingsUninstallOptInTest, free-settings.spec.js |
| **free.schedule** | | | | | |
| `free.schedule.recurrence` | free | high | covered | unit, integration, e2e | ScheduleSanitizersTest, CronUnifiedScheduleTest, free-schedule.spec.js |
| `free.schedule.time` | free | high | covered | unit, integration, e2e | ScheduleSanitizersTest, CronUnifiedScheduleTest, free-schedule.spec.js |
| `free.schedule.delay_enabled` | free | high | covered | unit, integration, e2e | AutoUpdateDelayBehaviorTest, free-schedule.spec.js |
| `free.schedule.delay_value` | free | high | covered | unit, integration, e2e | ScheduleSanitizersTest, AutoUpdateDelayBehaviorTest |
| `free.schedule.cron_disabled` | free | high | covered | integration, e2e | CronUnifiedScheduleTest, free-schedule.spec.js |
| **free.auto_updates** | | | | | |
| `free.auto_update_translations` | free | medium | covered | integration, e2e | AutoUpdatesRestApiTest, free-auto-updates.spec.js |
| `wp.auto_update_core` | shared | critical | covered | integration, e2e | AutoUpdatesRestApiTest, free-auto-updates.spec.js |
| `wp.auto_update_plugins` | shared | critical | covered | integration, e2e | AutoUpdatesRestApiTest, free-auto-updates.spec.js |
| `wp.auto_update_themes` | shared | critical | covered | integration, e2e | AutoUpdatesRestApiTest, free-auto-updates.spec.js |
| `wp.auto_update_translations` | shared | medium | covered | integration, e2e | AutoUpdatesRestApiTest, free-auto-updates.spec.js |
| `wp.constants.automatic_updater_disabled` | shared | critical | partial | unit, e2e | AutoUpdateDelayFilterVisibilityTest, free-auto-updates.spec.js |
| `wp.constants.disallow_file_mods` | shared | critical | partial | unit, e2e | AutoUpdateDelayFilterVisibilityTest, free-auto-updates.spec.js |
| `wp.constants.wp_auto_update_core` | shared | critical | partial | unit, e2e | AutoUpdateDelayFilterVisibilityTest, free-auto-updates.spec.js |
| `wp.constants.disable_wp_cron` | shared | high | partial | integration, e2e | CronUnifiedScheduleTest, free-schedule.spec.js |
| **free.logging** | | | | | |
| `free.retention_days` | free | medium | covered | integration, e2e | LogRetentionTest, free-settings.spec.js |
| `free.kill_switch.log_decision_layer` | free | high | gap | - | no direct test of the filter |
| `free.log.row_kind` | free | high | covered | integration | LogSchemaMigrationTest, NativeLoggerRowTest, LogTruthfulnessTest |
| `free.log.filters` | free | medium | covered | integration, js, e2e | LogQueryFilterTest, logDecisionFilters.test.js, free-logs.spec.js |
| **free.exports** | | | | | |
| `free.export.format` | shared | high | covered | unit, integration, js, e2e | LogExportContractTest, exportFormats.test.js, free-logs.spec.js, pro-103-exports.spec.js |
| `free.export.scope` | free | high | covered | unit, js, e2e | ExportBodyBuilderTest, free-logs.spec.js |
| `free.export.merge` | free | medium | covered | unit, js | ExportBodyBuilderTest |
| `free.export.columns` | free | medium | covered | unit, js, e2e | ExportBodyBuilderTest, exportLogsDecision.test.js, free-logs.spec.js |
| `free.export.empty` | free | medium | partial | unit, integration | LogExportContractTest, ExportBodyBuilderTest (expiry/rate-limit E2E missing) |
| **free.health** | | | | | |
| `free.health.checks` | free | medium | covered | integration, e2e | free-health.spec.js, HealthRestApiTest |
| **free.ui** | | | | | |
| `free.kill_switch.native_update_time` | free | medium | covered | unit, js, integration | NativeUpdateTimeTest, nativeUpdateTime.test.js |
| `free.ui.surface` | shared | high | covered | e2e | all Free/Pro specs |
| `free.ui.viewport` | shared | low | partial | e2e | helpers/a11y.js across specs |
| **pro.shield** | | | | | |
| `pro.shield.safe_mode` | pro | critical | covered | unit, integration, e2e | UpdateShieldPoliciesTest, UpdateShield43Test, pro-101-shield.spec.js |
| `pro.shield.control_php_version` | pro | critical | covered | unit, integration | UpdateShieldPoliciesTest |
| `pro.shield.control_wp_version` | pro | critical | covered | unit, integration | UpdateShieldPoliciesTest |
| `pro.shield.incompatible_behavior` | pro | critical | partial | unit | UpdateShieldPoliciesTest |
| `pro.shield.re_enable` | pro | high | partial | integration | UpdateShield43Test |
| `pro.shield.notify_on_block` | pro | high | partial | unit, integration | UpdateShieldPoliciesTest (no mail capture) |
| `pro.shield.notify_email` | pro | high | partial | unit, integration | UpdateShieldPoliciesTest (no mail capture) |
| `pro.shield.flag_abandoned` | pro | medium | covered | unit, integration | UpdateShieldPoliciesTest |
| `pro.shield.blocked_history` | pro | low | partial | integration, e2e | pro-101-shield.spec.js, pro-107 matrix |
| **pro.version_lock** | | | | | |
| `pro.version_lock.enabled` | pro | critical | covered | unit, e2e | VersionLockStateTest, pro-106-version-lock.spec.js |
| `pro.version_lock.manual_updates` | pro | critical | covered | integration, e2e | VersionLockGuardTest, pro-107 matrix |
| `pro.version_lock.detection` | pro | critical | partial | unit | VersionControlDetectorTest (no Composer/symlink E2E) |
| `pro.version_lock.manager` | pro | medium | partial | unit, e2e | VersionControlDetectorTest, pro-107 matrix |
| **pro.pass** | | | | | |
| `pro.pass.enabled` | pro | critical | covered | unit, integration | UpdatePassTest, UpdatePassGateTest |
| `pro.pass.node_count` | pro | high | covered | unit, integration | UpdatePassTest, UpdatePassLiveFixtureTest |
| `pro.pass.state` | pro | critical | covered | unit, integration | UpdatePassTest, PassSummaryTest |
| `pro.pass.lock_wait` | pro | high | partial | unit, integration | UpdatePassGateTest, UpdatePassLiveFixtureTest |
| `pro.pass.max_attempts` | pro | high | partial | unit | UpdatePassTest |
| `pro.pass.last_pass` | pro | medium | covered | integration, e2e | PassSummaryTest, pro-102-flow.spec.js |
| **pro.flow** | | | | | |
| `pro.flow.chain_count` | pro | high | covered | unit, e2e | UpdateFlowPlannerTest, pro-102-flow.spec.js |
| `pro.flow.chain_enabled` | pro | high | covered | unit, e2e | UpdateFlowPlannerTest, pro-102-flow.spec.js |
| `pro.flow.step_count` | pro | high | covered | unit | UpdateFlowPlannerTest |
| `pro.flow.chain_shape` | pro | critical | covered | unit | FlowGraphTest, UpdateFlowValidationTest |
| `pro.flow.predecessor_state` | pro | critical | covered | unit, integration | UpdateFlowPlannerTest, FlowPassIntegrationTest |
| `pro.flow.non_opted` | pro | critical | covered | unit, integration | FlowReadinessTest, FlowPassIntegrationTest |
| **pro.license** | | | | | |
| `pro.license.status` | pro | critical | covered | unit, integration, e2e | license tests, pro-001-license.spec.js |
| `pro.license.mode` | pro | critical | covered | e2e | pro-000-gating.spec.js, pro-001-license.spec.js |
| `pro.license.activation` | pro | high | covered | unit, e2e | LicenseApiTest, pro-001-license.spec.js |
| **pro.white_label** | | | | | |
| `pro.white_label.enabled` | pro | medium | covered | e2e | pro-100-white-label.spec.js |
| `pro.white_label.brand` | pro | medium | covered | unit, e2e | WhiteLabelSanitizationTest, pro-100 |
| `pro.white_label.menu_label` | pro | low | partial | unit, e2e | WhiteLabelSanitizationTest, pro-100 |
| `pro.white_label.colors` | pro | low | partial | e2e | pro-100-white-label.spec.js |
| `pro.white_label.logo` | pro | medium | covered | unit, e2e | WhiteLabelSanitizationTest, pro-100 |
| `pro.white_label.email` | pro | low | partial | unit, e2e | WhiteLabelSanitizationTest (no mail capture) |
| **pro.planning** | | | | | |
| `pro.planning.offer_state` | pro | critical | covered | unit, integration | UpdatePassOrderTest, UpdatePassLiveFixtureTest |
| `pro.planning.gate_result` | pro | critical | covered | unit, integration | UpdatePassGateTest, UpdatePassLiveFixtureTest |
| **update** | | | | | |
| `update.type` | shared | critical | covered | integration, e2e | log/pass fixtures, changelog/external-log specs |
| `update.origin` | shared | critical | covered | integration | log taxonomy tests |
| `update.item_state` | shared | critical | partial | unit, integration, e2e | many states covered, not all 18 in one matrix |
| `update.package` | shared | critical | partial | integration | UpdatePassLiveFixtureTest |
| `update.email` | shared | critical | gap | - | no mail capture |
| **environment** | | | | | |
| `env.wp_version` | shared | high | partial | integration, e2e | 7.1 only |
| `env.php_version` | shared | high | partial | unit | PHP matrix is unit-only |
| `env.database` | shared | high | partial | integration | MariaDB only |
| `env.filesystem` | shared | critical | gap | - | no disk-full/ssh/read-only fixture |
| `env.vcs` | shared | critical | partial | unit, e2e | Git only; no Composer project/symlink E2E |
| `env.timezone` | shared | high | partial | e2e | Europe/Paris only; no DST/UTC case |
| `env.cron` | shared | critical | partial | integration, e2e | enabled/disabled; no overlap/manual-run |
| `env.mail` | shared | high | gap | - | no mail capture |
| `env.maintenance` | shared | high | gap | - | no recovery/maintenance E2E |
| **roles** | | | | | |
| `role.kind` | shared | critical | partial | integration | capability tests; no editor/subscriber E2E |
| `role.capability` | shared | critical | partial | integration | capability matrix tests; no E2E role matrix |
| **multisite** | | | | | |
| `multisite.scope` | shared | critical | partial | integration | six multisite files; no live network |
| `multisite.pass_scope` | pro | critical | partial | integration | UpdatePassMultisiteTest; no live network |

## Surfaces

| Surface | Free E2E | Pro E2E | Integration | Status |
|---|---|---|---|---|
| Update logs | free-logs.spec.js | pro-104, pro-105 | LogQueryFilterTest, LogExportContractTest | covered |
| Auto updates | free-auto-updates.spec.js | - | AutoUpdatesRestApiTest | covered |
| Schedule | free-schedule.spec.js, free-schedule-native.spec.js | - | CronUnifiedScheduleTest, ScheduleStateIntegrationTest | covered |
| Health | free-health.spec.js | - | HealthRestApiTest, pass health tests | covered |
| Settings | free-settings.spec.js, free-settings-dirty.spec.js | - | SettingsUninstallOptInTest | covered |
| Documentation | free-docs-support.spec.js | - | - | covered |
| Support | free-docs-support.spec.js | - | - | covered |
| Update Shield | - | pro-101-shield.spec.js, pro-106, pro-107 | UpdateShield43Test, TruthfulnessContractTest | covered |
| Update Flow | - | pro-102-flow.spec.js | FlowPassIntegrationTest, FlowPassLogTest | covered |
| White Label | - | pro-100-white-label.spec.js | WhiteLabelSanitizationTest | covered |
| License | - | pro-000-gating.spec.js, pro-001-license.spec.js, pro-999-teardown.spec.js | license tests | covered |
| Plugins screen | free-schedule-native.spec.js | pro-106, pro-107 | VersionLockAdminTest | partial |
| Themes screen | free-schedule-native.spec.js | pro-107 | VersionLockAdminTest | partial |
| Update-core screen | free-schedule-native.spec.js | pro-107 | VersionLockAdminTest | partial |
| Network Admin | - | - | multisite integration only | gap |

## Gaps and flake sources

### Gaps

- No live multisite browser path; multisite is integration-only.
- No native-update/email/disk oracle in E2E.
- No combination runner or pairwise matrix.
- No mail capture, clock/cron control, or DB snapshot reset.
- No community plugin/theme base.
- No role/permission E2E matrix.
- No full mutation/counterfactual run in the release hook (diff-scoped only).

### Flake sources

| Source | Finding | Effect |
|---|---|---|
| Shared E2E site and option-only reset | H-04, E-06 | A killed run leaves options/logs; suites are order-dependent. |
| Live license API | H-04 | Pro E2E needs the real key and the network; activation can throttle or fail. |
| Real wall clock and WP-Cron | H-07, E-04 | Hold, next-eligible, retries, and stale passes cannot be forced. |
| Host/DDEV split | E-02 | Unit/integration run on host PHP 8.4.27; the browser runs in DDEV PHP 8.4. |
| Environment-specific Git markers | H-16 | The Version Lock spec can pass because the workspace is a checkout, not because the fixture is locked. |
| Full multisite test mode | H-10 | Two single-site capability tests fail when the test admin is not a Super Admin. |
| Persistent admin users | E-08 | harness-e2e and qa-logger remain administrators; role tests can run over-privileged. |
| Selector and copy drift | H-13 | Role/label selectors and README/coverage docs can lag the UI. |

## Proposed case-ID schema

- Case ID: `TB-<GROUP>-<NNN>` — stable, never reused. Groups: `SET`, `SCHED`,
  `AUTO`, `LOG`, `EXPORT`, `HEALTH`, `UI`, `SHIELD`, `VLOCK`, `PASS`, `PLAN`,
  `FLOW`, `LIC`, `WL`, `UPDATE`, `ENV`, `ROLE`, `MS`.
- Combination ID: `COMBO-<NNN>` for a pairwise or high-risk-triple row, with
  `dimensions:` naming each `id=value` pair.
- Invariant ID: `INV-<NN>` for a rule that runs after every case.
- Tags: `@free`, `@pro`, `@multisite`, `@license`, `@pass`, `@flow`,
  `@version-lock`, `@schedule`, `@logger`, `@export`, `@a11y`, `@combo`,
  `@invariant`, `@smoke`, `@release-only`.
- Required fields: `id`, `title`, `owner`, `risk`, `tags`, `dimensions`,
  `preconditions`, `steps`, `assertions`, `oracle`, `cleanup`, `automation`.
- A retired case keeps its ID with `status: retired` and a `replaced_by` field;
  a new case takes a new number.

