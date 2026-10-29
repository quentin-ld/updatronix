# Schedule — state of play

**Date:** 2026-10-26
**Screen:** Schedule (`schedule`, Free)
**Contract:** `04-screen-contracts.md` §3
**Status:** Audit complete for code and automated tests; the manual
keyboard/screen-reader/browser pass and a live cron run remain for Phase 6.

## Contract

One manually saved surface for the update-check frequency, preferred time, and
release hold. The next-run summary is computed from the saved schedule, not the
draft. The schedule subtree is network-scoped on Multisite.

## Claims matrix

| Claim | Where | Code path | Runtime proof | Verdict |
|---|---|---|---|---|
| “Choose how often WordPress checks for updates” | Header | `SetRecurrence` writes `schedule.update_check.recurrence`; `Updatronix_Cron` applies it. | Integration covers the unified schedule. | True |
| “How long new releases are held” | Header | Hold writes `delay_updates.enabled`/`delay_value`; the server, REST schema, and client sanitize 0–365, where 0 = off. | Unit sanitizer data provider + JS bounds tests + REST round-trip. | True |
| “The next-run summary uses the saved values” | Next run | `scheduleMeta` is refreshed from the save response; the draft is not used. | Code path; existing review 16. | True |
| “Times use the site timezone” | Preferred time | `scheduleMeta.timezone_string` is now printed under the select. | Code path; explicit copy. | True |
| “WP-Cron disabled” is visible | Next run | `scheduleMeta.wp_cron_disabled` renders a warning notice. | Code path; meta field covered by `CronUnifiedScheduleTest`. | True |
| An out-of-range hold is a validation error | Hold input | `validateHoldDays()` rejects non-integers and values outside 0–365; the panel shows an alert. | JS bounds tests. | True |
| One save for the whole surface | Surface note | The panel keeps a local draft and calls `saveSettings` only on Save. | Code path; `usePluginSettings` updates only on success. | True |

## Internal mechanics

| Layer | Path | Notes |
|---|---|---|
| Screen | `assets/src/js/components/schedule/SchedulePanel.js` | Frequency, hour/minute picker (00–23 / 00–59), hold 0–365, next-run summary, hold table. |
| Draft/validation | `assets/src/js/components/schedule/scheduleDraft.js` | `getSchedule`, `getHoldDays`, `scheduleSignature`, `isScheduleDirty`, `validateHoldDays`, `splitTime`, `joinTime`, `recurrenceDescription`. |
| Save | `assets/src/js/hooks/usePluginSettings.js` | `PUT /updatronix/v1/settings` with the schedule subtree. |
| REST/storage | `inc/classes/class-updatronix-settings.php`, `inc/settings/options.php` | Site option/network schedule option; server sanitizes recurrence and hold. |
| Cron | `inc/classes/class-updatronix-cron.php` | Unified schedule, next-run meta, timezone string, WP-Cron disabled flag. |

## Read-only Schedule API

The calculator and the facts filter are the public contract (analysis 04 §6,
synthesis §6.5/§6.6). Both are read-only: neither writes an option, a log row,
or a transient, and neither makes an HTTP request.

| Surface | Signature | Notes |
|---|---|---|
| Calculator | `Updatronix_Schedule_State::calculate( array $input ): array` | Pure; 18 display states; deterministic for the same input. |
| Item state | `Updatronix_Schedule_Context::get_item( string $type, string $id ): array` | One item; cached per request. |
| Map | `Updatronix_Schedule_Context::get_map( string $surface, int $limit = 0 ): array` | `plugins`, `themes`, `core`, or `all`; capped at 200 by `updatronix_schedule_map_limit`; reports `truncated`. |
| Canonical reader | `Updatronix_Item_State::get( string $type, string $id ): array` | The only reader for installed/target version and offer freshness. |
| Ledger peek | `Updatronix_AutoUpdateDelay::peek_first_seen( string $type, object $offer ): ?int` | Never writes, prunes, or logs. |
| Observation | `Updatronix_AutoUpdateDelay::observe_offer( string $type, object $offer ): ?int` | Idempotent; sets `first_seen` only when the row is absent. |

### `updatronix_schedule_item_state`

`apply_filters( 'updatronix_schedule_item_state', $facts, $type, $id )` returns
the read-only facts for one item. Keys are `plugin:{plugin_file}`,
`theme:{stylesheet}`, and `core:wordpress`; `core:core` is a read-only alias.

Free defaults:

```php
array(
    'lock'   => array( 'state' => 'none' ),
    'policy' => array( 'state' => 'none' ),
    'flow'   => array( 'state' => 'none' ),
    'queue'  => array( 'state' => 'none' ),
)
```

Pro's single callback, `Updatronix_Schedule_Facts::filter()` at priority 20,
returns:

| Fact | Shape |
|---|---|
| `lock` | `state` (`none`/`locked`), `manager`, `scope`, `reason`, `reason_code`. Version Lock owns it; an incoming lock fact is preserved. |
| `policy` | `state` (`none`/`blocked`), `reason_code` (`shield_incompatible_wp`/`shield_incompatible_php`), `message`, `policy_id`. |
| `flow` | `state` (`none` until Step 5). |
| `queue` | `state` (`none`/`active`), `node_state`, `position`, `total`, `pass_id`, `started_at`. Read from `updatronix_pro_update_pass` when it exists. |

Steps 4 and 5 extend `Updatronix_Schedule_Facts`; no second callback is
registered. When Pro is inactive, Free shows its own schedule state and cannot
know about Pro locks.

## Findings and fixes

| ID | Severity | Finding | Fix |
|---|---|---|---|
| SC-01 | HIGH | The contract requires an explicit timezone label; the screen never showed one. | The preferred-time section now prints “Times use <site timezone>”. |
| SC-02 | MEDIUM | `wp_cron_disabled` was in the meta but the screen never surfaced it. | A warning notice now explains that no scheduled run will happen until WP-Cron is enabled or a real cron job calls it. |
| SC-03 | MEDIUM | An out-of-range hold was silently clamped, so the user could not tell the value was rejected. | `validateHoldDays()` now rejects non-integers and values outside 0–30; the panel shows an alert and keeps the last valid draft. |
| SC-04 | MEDIUM | The dirty/validation logic had no JS coverage. | Extracted `scheduleDraft.js` and added 11 Vitest cases for fallback, hold, dirty/revert, and bounds. |
| SC-05 | LOW | `SaveBar` received an `onDiscard` prop it does not render. | Removed the dead prop and handler. |

No new REST route or capability surface was added. The screen reuses the
Settings route; the existing Settings review/security pass applies. No P0/P1
save defect was found: `saveSettings` updates the shared settings only after the
response, so a failed save keeps the draft and the dirty state.

## Native vs custom

| Surface | Implementation | Native option | Decision |
|---|---|---|---|
| Schedule storage | Network schedule option | Options API | Native |
| Cron | `Updatronix_Cron` + WP-Cron | Native cron | Native |
| Timezone | `wp_timezone_string()` | WordPress timezone | Native |
| Notices | `ConstantNotices` + WPDS `Notice` | WPDS component | Native |
| Validation | `validateHoldDays()` | No native range control | Custom, mirrored by the server sanitizer |

## Test inventory

| Suite | Coverage |
|---|---|
| JS | `tests/js/scheduleDraft.test.js` (11), plus the shared 45 |
| Integration | `CronUnifiedScheduleTest`, schedule settings save, `ScheduleSanitizersTest` |
| Unit | `ScheduleSanitizersTest` |

## Feature development guide

1. **Setting** — add the schedule key to `inc/settings/options.php` and the
   network schedule sanitizer; keep the 0–30 hold bound in both client and
   server.
2. **Draft** — add the field to `scheduleDraft.js` and its signature.
3. **UI** — use a WPDS control; keep the draft local; explain the next-tick
   behavior and the timezone.
4. **Cron** — update `Updatronix_Cron` and the REST meta if the new field
   changes scheduling.
5. **States** — WP-Cron disabled, no next run, out-of-range, save error.
6. **Tests** — unit for sanitizers, integration for cron/save, JS for
   dirty/validation, E2E when available.
7. **Docs** — update this file, the contract, and the implementation log.

## Known gaps

- E2E remains blocked by `libnspr4.so`.
- No manual screen-reader pass on the timezone label, hold error, or WP-Cron
  notice.
- No live cron run in this pass.
- No component-level test simulates a save 500 on this screen.
- No version bump.

## Exit

- Claims: all true or explicitly accepted.
- P0/P1: SC-01/02/03 fixed; SC-04 covered; SC-05 removed.
- Tests: JS 56, integration 69, unit 213, build pass.
- Review/security: Settings route reuse; no new surface.
- No version bump.
