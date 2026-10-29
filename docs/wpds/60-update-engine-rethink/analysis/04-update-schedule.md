# Update Schedule — analysis (Update Shield)

**Date:** 2026-10-28
**Status:** Analysis only. No production code changed, no task file created, no
version bumped.
**Owner:** Quentin.
**Feature owner:** Free (Schedule screen, cron, delay ledger, native override),
with Pro display contracts for Queue, Flow, and Version Lock.
**Shared docs read:** 16, 38, 45 §2.2/§5.4, and analyses 01–03.
**Grilling record:** `updatronix/.agents/notes/2026-10-28-grill-update-schedule.md`.
**QA record:** `updatronix/.agents/notes/2026-10-28-qa-update-schedule.md`.

---

## 1. Scope and non-goals

### In scope

- The Schedule screen: recurrence, preferred time, hold duration, next-run
  summary, and the copy that explains the hold.
- A precise hour-and-minute picker for the preferred time.
- The canonical hold range on the client, REST schema, sanitizer, runtime gate,
  log copy, and tests.
- A read-only time calculator for one item: next check, first seen, hold until,
  eligible at, execution at, and the display state.
- The native WordPress surfaces that print “Automatic update scheduled in …”:
  the plugin list table, the network themes list table, single-site theme cards
  and overlay, and the three update-core locations.
- The big blue delay banner and the one-line WP-Cron-disabled replacement.
- Timezone, DST, WP-Cron disabled, overdue, and no-scheduled-event behavior.
- Multisite: network schedule, main-site cron, network admin display, subsite
  display.
- Contracts with Version Lock, Queue, Flow, and Logger.
- The implementation constraints, tests, and evidence for the above.

### Non-goals

- Implementing the change. This document is analysis only.
- Replacing the WordPress update engine or the native admin pages.
- Re-deciding Queue execution, Flow readiness, Version Lock detection, or the
  Logger schema. This document consumes the settled decisions in analyses
  01–03 and states what Schedule needs from them.
- A new Schedule screen, a new settings surface, or a visual redesign beyond
  the picker, the hold range, and the removal of the false security claim.
- Changing existing i18n strings without owner approval. The owner approved the
  removal of the core-security claim in this round; the implementation plan
  must still list the exact string changes.
- A security-release exemption. The owner decided to remove the claim because
  WordPress exposes no reliable security signal.
- A new REST route unless the read-only display map cannot be localized. The
  preferred shape is a server-side map plus a filter for Pro facts.

---

## 2. Current behavior

### 2.1 Current-state map

| Surface | Implementation (file:line) | Actual behavior | Native interaction | Owner match? |
|---|---|---|---|---|
| Recurrence | `assets/src/js/components/schedule/SchedulePanel.js:30-36`; `inc/settings/options.php:67-69`; `inc/classes/class-updatronix-cron.php:158-163, 211-244, 312-327` | Five choices: daily, twice daily, hourly, weekly, WordPress default. The stored recurrence drives `wp_version_check` when non-empty. Hourly ignores the preferred time; default restores core’s events. | Reschedules core’s `wp_version_check` and suppresses `wp_update_plugins`/`wp_update_themes` while unified. | Partial: the choices exist, but the semantics are not explained and hourly/default discard the time. |
| Preferred time picker | `SchedulePanel.js:38, 79-86`; `inc/settings/options.php:214-225` | A `SelectControl` with four values: `03:00`, `06:00`, `12:00`, `21:00`. A saved custom value is appended, but the UI cannot choose a new one. The server sanitizer accepts any valid `HH:MM` and defaults to `03:00`. | The stored time anchors the first recurring event. | Mismatch: the server supports 1,440 values; the UI exposes four. |
| Hold bounds | `assets/src/js/components/schedule/scheduleDraft.js:10, 78-95`; `inc/settings/options.php:191-193`; `inc/classes/class-updatronix-settings.php:191-196` | UI: integer 0–30; 0 means off. Server: enabled values clamp to 1–365; 0 stores `delay_value=0` only when disabled. REST schema: 0–365. | The gate uses `max(1, min(365, value))` when enabled. | Mismatch: three different ranges. |
| Next-check calculation | `inc/settings/options.php:240-277`; `inc/classes/class-updatronix-cron.php:211-244, 312-327, 486-498` | First event from the site timezone and preferred wall time. If today’s time has passed, daily +1 day, twice daily +12 hours, weekly +7 days. Hourly and default return `time()`. `get_schedule_rest_meta()` reads `wp_next_scheduled( 'wp_version_check' )`. | The native message reads the same `wp_next_scheduled` value. | Partial: the next check is correct, but it is presented as the install time. |
| Delay ledger | `inc/classes/class-updatronix-autoupdatedelay.php:21-32, 42, 96-105, 185-251, 219-228, 259-291, 327-346, 351-414, 424-470` | A bounded map of `sha256(type, item, target)` → first-seen Unix time. The row is written the first time the `auto_update_*` gate evaluates the offer. Pruning happens only when the gate runs. A deferred log row is written on each held evaluation, deduped by event key. | The gate is called from `WP_Automatic_Updater::should_update()` only when the item’s native auto-update option is on. | Partial: the ledger is authoritative for the hold, but there is no read-only display API and no “not observed” state. |
| Core security exemption | `SchedulePanel.js:372-385, 427-432`; `class-updatronix-autoupdatedelay.php:101-104, 185-251` | The screen says “Core security releases: Installed immediately, the hold is ignored.” The gate hooks `auto_update_core` and applies the same soak window to every core offer. There is no security branch. | The core offer has no reliable “security” field. | Mismatch: the UI promises an exemption the code does not implement. |
| Native plugin row text | `wp-admin/includes/class-wp-plugins-list-table.php:1435-1457`; `wp-admin/includes/update.php:1097-1135` | A `.auto-update-time` div inside the auto-updates column, only when the plugin has an update. It prints `wp_get_auto_update_message()`: “Automatic update scheduled in …” or “overdue by …” or “not scheduled”. The whole HTML is filterable by `plugin_auto_update_setting_html`. | The message is the next `wp_version_check`, not the install. | Mismatch: false install time; no filter on the message itself. |
| Native theme row text | `wp-admin/includes/class-wp-ms-themes-list-table.php:868-887`; `wp-admin/themes.php:783-829` | Network themes: `.auto-update-time` inside the auto-updates column, filterable by `theme_auto_update_setting_html`. Single-site themes: no list table; a JavaScript template embeds `wp_get_auto_update_message()` at page render, filterable by `theme_auto_update_setting_template`. | Same native message. | Mismatch: false install time; the single-site template is global, not per item. |
| Native update-core text | `wp-admin/update-core.php:400, 511, 610, 686, 788` | Three locations: the core auto-update section prints a `<p>` when major auto-updates are on and a newer core version exists; the plugin and theme update tables append a pipe separator plus the message to each row whose item is in the native auto-update option. | Same native message. No per-row filter. | Mismatch: false install time; no server-side row hook. |
| Blue notice | `inc/admin/native-update-delay-notice.php:15-19, 26-113, 43-60`; `updatronix.php:176` | A 920px inline-styled `notice-info` on `update-core`, `plugins`, and `themes’ network equivalents. It says countdowns are the next check, not the install, and links to Schedule and Update logs. | It does not change the native text; it explains the mismatch. | Mismatch: the owner wants the native surface corrected, not a banner that documents the defect. |
| WP-Cron disabled/overdue | `inc/classes/class-updatronix-cron.php:486-498`; `SchedulePanel.js:308-323`; `wp-admin/includes/update.php:1101-1131` | The Schedule screen warns when `DISABLE_WP_CRON` is true. The native message reports “overdue by …” or “not scheduled”. It does not distinguish “WP-Cron spawning disabled” from “a real cron job is running”. | The native message is the only native health signal. | Partial: the warning exists on Schedule, but the native surfaces still show a misleading countdown. |
| Timezone/DST | `inc/settings/options.php:249-273`; `inc/classes/class-updatronix-cron.php:220-227`; `SchedulePanel.js:260-270` | The first event is created in `wp_timezone()` from the preferred wall time. WP-Cron repeats at fixed second intervals. The heal path checks the recurrence slug, not the timestamp. The screen prints “Times use {timezone}”. | The native message uses a UTC timestamp and a relative diff; it does not show the timezone. | Partial: the first event respects DST; later events can shift the local wall clock by an hour. |
| Flow interaction | `inc/update-engine/class-updatronix-flow-planner.php:370-404`; `inc/update-flow/class-updatronix-flow-runner.php:364-497`; `inc/update-engine/class-updatronix-queue-scheduler.php:99-130` | Flow plans at `wp_maybe_auto_update` priority 1. A missing offer is `skipped`; a held or blocked step becomes `blocked` and downstream rows are canceled. There is no `waiting_for_predecessor` state. | Flow uses the kernel, not the native `run()`; the queue analysis recommends one native pass. | Mismatch: a waiting item still shows the native countdown. |
| Queue interaction | `inc/update-engine/class-updatronix-queue-scheduler.php:89-130, 214-266, 545-613`; `inc/update-engine/class-updatronix-update-kernel.php:149-235` | The scheduler plans every offer before `run()` refreshes discovery. A held offer becomes a `blocked` row. The ownership handshake denies native; the `job_key` dedupe prevents re-planning; terminal rows are not rechecked by the scheduler. The queue is dormant by default. | The queue is a second path; the native loop is the observed behavior on this site. | Mismatch: the hold freezes the item instead of deferring it, and the display has no queue position. |
| Version Lock interaction | `docs/wpds/60-update-engine-rethink/analysis/01-version-lock.md` §11; Pro `class-updatronix-policy-chain.php:29-67`; `class-updatronix-delay-policy.php:68-111` | A locked item is denied in the engine/adapter chains. The native row still counts down. The owner decided Version Lock is subordinate to Update Shield and runs only when `safe_mode` is on. | The adapter denies the native automatic update; the native row text is unchanged. | Mismatch: a locked item shows a countdown. |
| Multisite | `inc/settings/options.php:16-22`; `inc/classes/class-updatronix-cron.php:440-472, 486-498`; `inc/admin/native-update-delay-notice.php:15-18` | The schedule is a network option. The cron event is on the main site; subsite `wp_version_check` events are cleared. `get_schedule_rest_meta()` reads the current site’s event. The banner is network-admin only. | `WP_Automatic_Updater::run()` is main-network/main-site only. | Partial: a subsite can show a false “not scheduled” message. |
| Schedule save / next-run card | `SchedulePanel.js:165-196, 300-346`; `inc/classes/class-updatronix-settings.php:935-993`; `assets/src/js/hooks/usePluginSettings.js:199-202` | The card uses the saved schedule, not the draft. The header meta is refreshed from the save response. The draft is local until Save. | None. | Partial: the next-run card does not preview the draft; it is saved-schedule truth. |
| Manual check | `inc/classes/class-updatronix-settings.php:1172-1191` | `POST /run-check` calls `wp_version_check()`, `wp_update_plugins()`, `wp_update_themes()`, and translations. It does not fire `wp_maybe_auto_update` because the request is not cron. It does not write `first_seen`. | Refreshes the update transients; does not run the automatic pass. | Partial: an admin may expect the manual check to start the hold; it does not. |

### 2.2 Code that decides the behavior

The native message is the next check:

```php
// wp-admin/includes/update.php:1097-1135
$next_update_time = wp_next_scheduled( 'wp_version_check' );
...
$time_to_next_update = human_time_diff( (int) $next_update_time );
...
$message = sprintf(
    /* translators: %s: Time until the next update. */
    __( 'Automatic update scheduled in %s.' ),
    $time_to_next_update
);
```

The schedule anchor is a local wall time:

```php
// inc/settings/options.php:249-273
$now        = new \DateTimeImmutable( 'now', $tz );
$today      = $now->format( 'Y-m-d' );
$normalized = updatronix_sanitize_schedule_wall_time( $time );
...
$run = \DateTimeImmutable::createFromFormat(
    'Y-m-d H:i:s',
    sprintf( '%s %02d:%02d:00', $today, $hour, $minute ),
    $tz
);
if ( $run->getTimestamp() <= $now->getTimestamp() ) {
    if ( 'weekly' === $recurrence ) {
        $run = $run->modify( '+7 days' );
    } elseif ( 'twicedaily' === $recurrence ) {
        $run = $run->modify( '+12 hours' );
    } else {
        $run = $run->modify( '+1 day' );
    }
}
```

The ledger is written by the gate, not by discovery:

```php
// inc/classes/class-updatronix-autoupdatedelay.php:219-228
if ( ! array_key_exists( $stable, $ledger ) ) {
    $ledger[ $stable ]  = $now;
    self::$ledger_cache = null;
    self::ledger_flush( $ledger );
    $ledger = self::ledger_decode_mutate();
}

$first_seen = (int) $ledger[ $stable ];
$should_delay = ( $now - $first_seen ) < $delay_seconds;
```

The picker exposes four values:

```js
// assets/src/js/components/schedule/SchedulePanel.js:38
const PREFERRED_TIMES = [ '03:00', '06:00', '12:00', '21:00' ];
```

The hold bounds disagree:

```php
// inc/settings/options.php:191-193
$delay_enabled   = (bool) ( $du_in['enabled'] ?? false );
$delay_value_raw = isset( $du_in['delay_value'] ) ? (int) $du_in['delay_value'] : (int) $defaults['delay_updates']['delay_value'];
$delay_value     = $delay_enabled ? max( 1, min( 365, $delay_value_raw ) ) : 0;
```

```js
// assets/src/js/components/schedule/scheduleDraft.js:10, 78-95
export const MAX_HOLD_DAYS = 30;
...
parsed < 0 || parsed > MAX_HOLD_DAYS
```

The Flow planner treats “no offer” as skipped and a held step as blocked:

```php
// inc/update-engine/class-updatronix-flow-planner.php:374-404
if ( null === $offer || '' === $target || $target === $from ) {
    ...
    $plan->add_skipped( ..., array( 'reason' => $target === $from ? 'already_installed' : 'no_pending_offer' ) );
    continue;
}
```

```php
// inc/update-flow/class-updatronix-flow-runner.php:394-402
if ( null !== $result['denied'] ) {
    $plan->add_blocked( $job, $result['denied'] );
    $queue->cancel_flow_after( $job, 'upstream_blocked' );
    ...
    ++$blocked;
    continue;
}
```

The queue plans before `run()` refreshes discovery:

```php
// inc/update-engine/class-updatronix-queue-scheduler.php:89-130
$flow = self::plan_flow();
...
$run_id = Updatronix_Update_Kernel::new_run_id();
$result = self::plan( Updatronix_Update_Engine::runtime_kernel(), $run_id );
```

### 2.3 What the current code proves

1. The next check and the install time are different facts. The native message
   exposes only the first.
2. The hold is measured from the first automatic gate evaluation, not from the
   release date and not from a manual check.
3. A fresh offer can have no ledger row. The display must be able to say “not
   yet observed” without creating the row.
4. The picker and the server disagree on the meaningful hold range.
5. The core security claim has no code path behind it.
6. The native message has no filter; the plugin and network-theme list tables
   have per-item HTML filters; single-site themes have a template filter;
   update-core has no per-row filter.
7. Flow and the queue can make the install later than the next check, or make
   it conditional on another item. The native text cannot express either.
8. Version Lock can make the automatic install impossible. The native text
   still counts down.
9. A held offer in the current queue becomes a permanent `blocked` row until a
   manual recheck. The queue does not silently bypass the hold; it freezes the
   item. That is a different defect, and the display must not hide it.

---

## 3. Native WordPress interaction and the exact surfaces

### 3.1 The native message source

`wp_get_auto_update_message()` (`wp-admin/includes/update.php:1097-1135`) has
three outputs:

| Condition | Output |
|---|---|
| `wp_next_scheduled( 'wp_version_check' )` is `false` | “Automatic update not scheduled. There may be a problem with WP-Cron.” |
| The timestamp is in the past | “Automatic update overdue by {human diff}. There may be a problem with WP-Cron.” |
| The timestamp is in the future | “Automatic update scheduled in {human diff}.” |

The function has no parameters and no filter. It does not know about the hold,
the queue, Flow, Version Lock, the target version, or the site timezone string.
It always describes the next check.

`wp_version_check()` fires `wp_maybe_auto_update` only during cron
(`wp-includes/update.php:331-337`). `wp_maybe_auto_update()` runs
`WP_Automatic_Updater::run()` (`wp-includes/update.php:885-891`). The next
`wp_version_check` is therefore the next automatic pass, not the install.

### 3.2 Plugin list table

`wp-admin/includes/class-wp-plugins-list-table.php:1380-1457`:

- The auto-updates column is rendered only when `show_autoupdates` is true and
  the plugin is not must-use or a drop-in.
- The toggle is a link or a label. The `.auto-update-time` div is added only
  when `! empty( $plugin_data['update'] )`.
- The `$time_class` is ` hidden` when auto-updates are off or unavailable.
- The whole HTML is passed through `plugin_auto_update_setting_html`
  (`:1457`) with the plugin file and plugin data.

Exact DOM:

```html
<tr data-slug="..." data-plugin="...">
  <td class="column-auto-updates">
    <a class="toggle-auto-update" data-wp-action="disable">...</a>
    <div class="auto-update-time">Automatic update scheduled in 6 hours.</div>
  </td>
</tr>
```

The message is in the main plugin row, not in the separate `.plugin-update-tr`
row. A per-item server-side replacement can operate on the filter HTML. A JS
fallback can target `tr[data-plugin] .auto-update-time`.

### 3.3 Network themes list table

`wp-admin/includes/class-wp-ms-themes-list-table.php:840-887`:

- The `.auto-update-time` div is added only when the stylesheet is in the
  update transient’s `response`.
- The whole HTML is passed through `theme_auto_update_setting_html` (`:887`)
  with the stylesheet and `WP_Theme`.

Exact DOM is the same shape as the plugin row, with
`tr[data-slug] .auto-update-time` and `td.column-auto-updates`.

### 3.4 Single-site themes

`wp-admin/includes/class-wp-themes-list-table.php` does not render an
auto-update time; it is not a surface for this feature. Single-site themes use
cards and an overlay instead.

`wp-admin/themes.php` does not render a list table for single-site themes; it
renders theme cards and an overlay. The auto-update template is:

```php
// wp-admin/themes.php:810-814
<span class="auto-update-time">
...
<br />' . wp_get_auto_update_message() . '</span>
```

The template is filtered by `theme_auto_update_setting_template` (`:829`) and
printed once at `:1235`. The overlay is rendered client-side from the template;
the data comes from `wp_prepare_themes_for_js()` (`wp-admin/includes/theme.php:647`).
The template message is global: every theme with an update sees the same text.
There is no per-theme server-side HTML filter on this path.

Exact DOM after the overlay renders:

```html
<div class="theme-autoupdate">
  <button class="toggle-auto-update" data-slug="theme-slug">...</button>
  <span class="auto-update-time">Automatic update scheduled in 6 hours.</span>
</div>
```

The overlay root carries `data-slug` (`wp-admin/js/theme.js:433`), so a JS
lookup can associate the message with a theme. A cleaner server-side approach
is to add a per-theme display string in `wp_prepare_themes_for_js()` and use a
filtered template that renders `data.updatronixAutoUpdateText` instead of the
global `wp_get_auto_update_message()`.

### 3.5 Update-core

`wp-admin/update-core.php` has three locations:

| Location | Code | Markup | Identification |
|---|---|---|---|
| Core section | `:400` | `<p>{wp_get_auto_update_message()}</p>` immediately before `<p class="auto-update-status">` | The previous element sibling of `p.auto-update-status`; the text starts with “Automatic update”. The `<p>` is printed only when `$upgrade_major` is true and a newer core version exists. |
| Plugin table | `:511`, `:610` | The `$auto_update_notice` value (a pipe plus the message) is appended to `td.plugin-title p` when the plugin is in `auto_updates`. | `#update-plugins-table tbody.plugins tr`; the checkbox value is the plugin file; the text is a text node after the compatibility string. |
| Theme table | `:686`, `:788` | Same, appended to `td.plugin-title p` when the theme is in `auto_updates` | `#update-themes-table tbody.plugins tr`; the checkbox value is the stylesheet; the text is a text node. |

There is no per-row filter. The plugin/theme message is plain text, not wrapped
in an element. The core message is a standalone `<p>`, but it has no class or
data attribute. A server-side replacement is impossible without output
buffering, which is out of scope. The override must be a scoped JS text
replacement, or a small admin notice summary that does not claim to replace the
native text. The owner chose replacement.

### 3.6 JavaScript that clears or toggles the text

- After a successful plugin update, `updates.js:583` empties
  `$pluginRow.find( '.auto-update-time' )`.
- After a successful theme update, `updates.js:1630, 1637` empties the theme’s
  `.auto-update-time`.
- Toggling auto-updates shows or hides `.auto-update-time`
  (`updates.js:3464, 3475`). It does not rewrite the text.

Consequences:

1. The override can replace the text once at DOM ready. A toggle does not
   destroy it.
2. After an update, WordPress empties the element; the override must not
   reinsert a countdown for an item that no longer has an offer.
3. The plugin/theme list tables are server-rendered; the single-site theme
   overlay is rendered later. The override needs a DOM-ready pass plus a
   re-run when the theme overlay is rendered.
4. The update-core page has no AJAX row refresh; one DOM-ready pass is enough.

### 3.7 What can be filtered, and what cannot

| Surface | Server-side hook | Per-item? | Can replace the message server-side? |
|---|---|---|---|
| Plugin list table | `plugin_auto_update_setting_html` | Yes | Yes, by replacing the `.auto-update-time` inner text in the filter HTML. |
| Network themes table | `theme_auto_update_setting_html` | Yes | Yes, same. |
| Single-site theme overlay | `theme_auto_update_setting_template` + `wp_prepare_themes_for_js` | Template is global; data is per theme | Yes, by adding a per-theme text to the JS data and rendering it in the filtered template. |
| Update-core core section | None | No | No. JS only. |
| Update-core plugin rows | None | No | No. JS only. |
| Update-core theme rows | None | No | No. JS only. |
| Native message function | None | No | No. JS only. |

### 3.8 Override strategy

Use a hybrid, in this order:

1. **Server-side filters for the list tables.** Replace only the
   `.auto-update-time` inner text with the calculator’s item state. Leave the
   toggle link, the hidden class, and the surrounding HTML untouched.
2. **Server-side template for single-site themes.** Add
   `updatronix_auto_update_text` to each theme in `wp_prepare_themes_for_js()`
   and filter `theme_auto_update_setting_template` to render that value when
   present. Fall back to the native message when it is absent.
3. **One scoped vanilla JS file for update-core and as a fallback.** It reads a
   localized map, walks only the known containers, replaces only text that
   matches the native message pattern, and leaves everything else alone.
4. **No output buffering, no page replacement, no global `str_replace`.**

This satisfies the owner’s “surgical script” requirement without making the
list tables depend on DOM fragility.

### 3.9 Exact selectors and replacement rules

| Surface | Selector | Item key | Replacement rule |
|---|---|---|---|
| Plugin row | `tr[data-plugin] .auto-update-time` | `data-plugin` | Replace the element’s text with the item display. Keep the existing hidden class. |
| Network theme row | `tr[data-slug] .auto-update-time` | `data-slug` | Same. |
| Single-site theme overlay | `.theme-autoupdate .auto-update-time` inside `[data-slug]` | Closest `[data-slug]` | Replace the text; re-run when the overlay renders. |
| Update-core core | `p.auto-update-status` previous element sibling `<p>` | `core` | Replace only when the text starts with one of the three native variants: “Automatic update scheduled in”, “Automatic update overdue by”, or “Automatic update not scheduled”. If the previous sibling is not that `<p>`, leave it. |
| Update-core plugin row | `#update-plugins-table tbody.plugins tr` → `td.plugin-title p` | `input[name="checked[]"]` value | Find the text node containing the pipe-prefixed “Automatic update …”; replace the substring after the pipe. If there is no checkbox or no match, leave the native text and rely on the global summary. |
| Update-core theme row | `#update-themes-table tbody.plugins tr` → `td.plugin-title p` | `input[name="checked[]"]` value | Same. |

Rules:

- Use `textContent`, never `innerHTML`, for the replacement.
- Preserve the native text in `data-updatronix-native-text` for debugging and
  fallback; do not show it.
- Mark the element with `data-updatronix-enhanced="1"` to make the pass
  idempotent.
- Never replace text outside the known container.
- Never touch the version-lock notice, the Safe Mode notice, the compatibility
  notice, or another plugin’s row.
- If the map has no entry, leave the native text.
- If the native text does not match the pattern, leave it and record a debug
  message, not a user-facing error.
- If the element is hidden because auto-updates are off, replace the text but
  keep it hidden. Do not unhide it.

### 3.10 Failure modes and graceful degradation

| Failure | Behavior |
|---|---|
| WordPress changes the native message wording | The pattern does not match; the native text remains. The update-core summary still appears if the page can render it. |
| WordPress changes the `.auto-update-time` class | The server-side filter still works on the list tables; the JS finds nothing and leaves the native text. |
| The theme overlay is rendered after DOM ready | A `MutationObserver` scoped to the theme overlay, or a `wp-auto-update-setting-changed`/overlay event, re-runs the pass. If the observer is unavailable, the native text remains. |
| Another plugin also replaces the text | The last writer wins; the owner sees one text. The version-lock and schedule scripts use different data attributes and append their own notices rather than replacing each other. |
| The map is missing or empty | No replacement; native text remains. |
| The page is not one of the six native screens | The script is not enqueued. |
| The user lacks the screen capability | The script is not enqueued; the native text remains. |

---

## 4. Owner expectation

### 4.1 The owner’s statements, restated

1. **The Schedule is close, but the time picker is too coarse.** The owner
   wants an hour selector with all 24 hours and a minute selector with all
   minutes, so any `HH:MM` can be chosen.
2. **The native interface must be surgically overridden.** The owner wants a
   script that changes the “in how long the automatic update will happen” text,
   not a big blue notice that explains the schedule.
3. **The native moment is false while the Schedule is active.** The displayed
   information must become true and more precise.
4. **Every use case and edge case must be listed** before code, because the
   Schedule interacts with many features.

The owner also decided, in this round:

5. The hold range is `0..365`, `0` = off.
6. The core-security exemption claim is removed. The hold applies to every core
   automatic update.
7. The display is state-first: the earliest execution, not a single exact time
   when Flow or the queue makes it uncertain.
8. The big blue notice is removed; only a one-line WP-Cron-disabled hint
   remains.
9. Waiting, locked, and queued items never show a countdown.

### 4.2 Match matrix

| # | Owner expectation | Current | Verdict |
|---|---|---|---|
| 1 | All 24 hours and all 60 minutes | Four values | **Does not match** |
| 2 | Surgical native override, not a banner | A banner explains the mismatch; the native text is unchanged | **Does not match** |
| 3 | True and more precise native moment | Next check shown as install | **Does not match** |
| 4 | Exhaustive cases before code | This document | **This document** |
| 5 | Canonical hold range 0–365 | UI 0–30, server 1–365, REST 0–365 | **Does not match** |
| 6 | No false core-security claim | Claim present, no exemption | **Does not match** |
| 7 | State + earliest execution | Native relative countdown | **Does not match** |
| 8 | Remove the big notice; WP-Cron hint only | Big notice on three screens | **Does not match** |
| 9 | No countdown for waiting/locked/queued | Native countdown | **Does not match** |

---

## 5. Gap analysis

| # | Gap | Impact | Evidence |
|---|---|---|---|
| G1 | The picker has four values. | The owner cannot choose a precise time; the server already accepts any `HH:MM`. | `SchedulePanel.js:38, 79-86`; `options.php:214-225`. |
| G2 | The hold bounds disagree in three places. | A saved 31-day hold cannot be edited; a 0-day enabled hold becomes 1 day; REST and UI disagree. | `scheduleDraft.js:10, 78-95`; `options.php:191-193`; `class-updatronix-settings.php:191-196`. |
| G3 | The core-security claim is false. | The owner expects immediate security installs; the hold applies to all core offers. | `SchedulePanel.js:372-385, 427-432`; `class-updatronix-autoupdatedelay.php:101-104, 185-251`. |
| G4 | The native message is the next check. | The owner sees a false install time on plugin, theme, and update-core surfaces. | `wp-admin/includes/update.php:1097-1135`; `class-wp-plugins-list-table.php:1435-1438`; `class-wp-ms-themes-list-table.php:869-871`; `update-core.php:400, 511, 686`. |
| G5 | The native message has no filter. | A server-side correction is impossible; update-core rows are plain text. | `update.php:1097`; `update-core.php:511, 610, 686, 788`. |
| G6 | No first-seen read path. | A fresh offer has no ledger row; a naive display either lies or writes a row. | `class-updatronix-autoupdatedelay.php:219-228`. |
| G7 | No state for Flow waiting. | B’s row shows a countdown while B cannot run until A. | `flow-planner.php:370-404`; `flow-runner.php:394-430`. |
| G8 | No state for queue position. | The row cannot say “queued after N items” or “may run later”. | `queue-scheduler.php:214-266`; `update-kernel.php:149-235`. |
| G9 | No state for Version Lock. | A locked item shows a countdown. | `01-version-lock.md` §11; `policy-chain.php:29-67`. |
| G10 | A held offer freezes in the queue. | The item stays blocked after the hold expires until a manual recheck. | `queue-scheduler.php:214-266`; `update-queue.php:1143-1172`. |
| G11 | The blue notice is a workaround. | It documents the false native text instead of correcting it. | `native-update-delay-notice.php:43-60`. |
| G12 | DST can shift the local wall clock. | A 01:00 schedule can run at 02:00 after a transition. | `options.php:249-273`; `class-updatronix-cron.php:220-227`. |
| G13 | Multisite subsites can show a false “not scheduled”. | Subsite `wp_version_check` events are cleared; the network schedule is on the main site. | `class-updatronix-cron.php:440-472`; `options.php:16-22`. |
| G14 | The preferred time is discarded for hourly/default. | Switching recurrence erases the saved time. | `options.php:185-189`; `SchedulePanel.js:101-110`. |
| G15 | No per-row caching plan. | A naive display calls detectors/planner per row, causing N+1 work. | `shield-state.php:51-119`; `flow-planner.php:274-313`. |
| G16 | The log’s “eligible” time is not the next check. | The log and the native display can disagree. | `class-updatronix-autoupdatedelay.php:424-470`. |
| G17 | The native message appears only with an offer and auto-updates on. | A missing message is not proof of no update. | `class-wp-plugins-list-table.php:1435`; `update-core.php:399-401`. |
| G18 | The update-core core message only appears when major auto-updates are on. | A minor-only site has no core message at all, so a global summary needs another hook. | `update-core.php:399-401`. |
| G19 | The single-site theme template is global. | Per-theme state needs `wp_prepare_themes_for_js` plus a filtered template. | `themes.php:783-829`; `theme.php:647`. |
| G20 | No tests cover the native override or the calculator. | The defects can return unnoticed. | `tests/js/scheduleDraft.test.js`; no native DOM fixture. |

---

## 6. Time calculator specification

### 6.1 Purpose

One pure, read-only function answers: “When may this item install
automatically, and what should the native surface say?” It is used by the
Schedule screen, the native list-table filters, the single-site theme template,
and the update-core JS map. It never writes the ledger, never runs an update,
and never calls the network.

### 6.2 Inputs

| Input | Type | Source | Notes |
|---|---|---|---|
| `type` | `plugin`, `theme`, `core`, or `translation` | Caller | Translation is details-only; native rows do not show it. |
| `id` | string | Plugin file, theme stylesheet, `core` | The stable item id. |
| `installed_version` | string | `get_plugins()` / `wp_get_theme()` / `get_bloginfo('version')` | Empty means the item is not installed. |
| `target_version` | string | Current update transient | Empty means no offer. |
| `offer_state` | `none`, `pending`, `stale`, or `unknown` | Transient `response` and `last_checked` | `stale`/`unknown` means the data is too old or the item is absent from `checked`. |
| `auto_update` | `on`, `off`, `forced_off`, or `forced_on` | Native auto-update option and constants | `forced_off` includes `AUTOMATIC_UPDATER_DISABLED` and `DISALLOW_FILE_MODS`. |
| `recurrence` | `hourly`, `twicedaily`, `daily`, `weekly`, or `''` | Schedule settings | `''` means WordPress default. |
| `preferred_time` | `HH:MM` | Schedule settings | Ignored for hourly/default. |
| `hold_enabled` | bool | Schedule settings | Canonical `0..365`. |
| `hold_days` | int | Schedule settings | `0` means off. |
| `now` | int | `time()` | UTC. |
| `next_check` | int or false | `wp_next_scheduled( 'wp_version_check' )` | UTC. |
| `cron_disabled` | bool | `DISABLE_WP_CRON` | Spawning disabled; a real cron job may still call `wp-cron.php`. |
| `first_seen` | int or null | Read-only ledger peek | UTC. Null when not observed. |
| `flow` | array | Pro filter | `state`, `waiting_for`, `reason`, `manager`. |
| `queue` | array | Pro filter | `state`, `position`, `total`, `pass_id`, `started_at`, `next_follow_up`. |
| `lock` | array | Pro filter | `state`, `manager`, `scope`, `reason`. |
| `timezone` | `DateTimeZone` | `wp_timezone()` | Display timezone. |
| `date_format` | string | `get_option('date_format')` | Fallback `Y-m-d`. |
| `time_format` | string | `get_option('time_format')` | Fallback `H:i`. |

### 6.3 Outputs

| Output | Type | Meaning |
|---|---|---|
| `state` | enum | The single primary state. |
| `next_check` | int or false | As input. |
| `first_seen` | int or null | As input. |
| `hold_until` | int or null | `first_seen + hold_days*DAY_IN_SECONDS` when the hold is on and the offer is observed; otherwise null. |
| `eligible_at` | int or null | The first nominal check at or after `hold_until` (or the next check when the hold is off). Null when it cannot be known. |
| `execution_at` | int or null | The earliest execution, only when the state is eligible and the cron is healthy; adjusted by queue/Flow. Null for waiting, locked, not managed, no offer, or a cron problem. |
| `queue_position` | int or null | The number of items ahead in the current pass, when known. |
| `waiting_for` | string | The predecessor item id/label, when waiting. |
| `manager` | string | The version-lock manager, when locked. |
| `display` | string | The localized sentence for the surface. |
| `display_kind` | `exact`, `earliest`, `waiting`, `managed`, `none`, `not_observed`, or `cron` | How to render the timestamp. |
| `reason_code` | string | Stable code for tests and logs. |
| `details_url` | string | Optional link to Schedule/Flow. |

### 6.4 Formulas

Let `DAY = 86400`.

**Interval.** Use the actual recurrence interval:

```text
hourly      -> 3600
twicedaily  -> 43200
daily       -> 86400
weekly      -> 604800
'' (default)-> the interval of the current wp_version_check event,
               or 43200 when no event is available
```

Use `wp_get_schedules()` for the filtered interval when available. Do not
hard-code a different value from the one WordPress uses.

**First seen.**

```text
first_seen = ledger_peek( type, id, target_version )
```

`ledger_peek()` is read-only. It returns `null` when the hold is off, the offer
is not observed, or the hash is absent. It never creates a row. The hash is the
same `sha256` input the gate uses: `type|lower(id)|target` for plugins and
themes, `core|current|target` for core, and the translation tuple for
translations.

**Hold until.**

```text
if hold_enabled and hold_days > 0 and first_seen is not null:
    hold_until = first_seen + hold_days * DAY
else if first_seen is not null:
    hold_until = first_seen          # informational echo; the hold is off
else:
    hold_until = null
```

`hold_days = 0` means off, even if `hold_enabled` is true. The canonical range
is `0..365`; the server must reject or clamp outside it consistently. When the
hold is off, `first_seen` is not an eligibility floor: the item is eligible at
the next check. The echo is kept because the specification asks for
`hold_until = first_seen` when the hold is off; the display does not use it as a
gate.

**Next check at or after a floor.**

```text
function first_check_at_or_after( floor, next_check, interval, now ):
    if next_check is false: return null
    if next_check < now: return null        # overdue; the actual next run is unknown
    t = next_check
    while t < floor:
        t = t + interval
    return t
```

**Eligible at.**

```text
if offer_state is not pending: eligible_at = null
else if auto_update is not on: eligible_at = null
else if hold is off:          eligible_at = next_check (if future), else null
else if first_seen is null:   eligible_at = null
else:                         eligible_at = first_check_at_or_after(hold_until, next_check, interval, now)
```

When the hold is off, `first_seen` is not used. The floor is `now`; if the next
check is in the future, it is the earliest check.

**Execution at.**

```text
if state is eligible:
    execution_at = eligible_at
    if queue.state is active and position > 0:
        # execution_at is still the earliest; the position is a qualifier,
        # not a timestamp. The item may run later in the pass.
        execution_at = eligible_at
    if flow.state is waiting:
        execution_at = null
else:
    execution_at = null
```

If the Flow predecessor is not done, the state is `waiting_for_predecessor`,
not a timestamp. If the item is in the current pass but not next, the state is
`queued`; `execution_at` is the earliest eligible check plus “may run later”.
The queue position does not shift the timestamp; it changes the confidence.

**Queue position.**

```text
position = number of unattempted items before this item in the pass order
```

The pass may drain one item per invocation. The position is a count, not a
duration. Never compute `execution_at = eligible_at + position * estimate`.

**State precedence.** The first matching row wins:

1. `wp_cron_disabled` — `DISABLE_WP_CRON` is true.
2. `not_scheduled` — `next_check === false`.
3. `overdue` — `next_check < now`.
4. `locked` — Version Lock manages the item.
5. `not_managed` — auto-updates are off or forced off.
6. `no_offer` / `unknown_offer` — no pending offer or stale data.
7. `waiting_for_predecessor` — Flow predecessor is not done.
8. `not_observed` — hold is on, offer pending, `first_seen` is null.
9. `held` — hold is on, `first_seen` present, `now < hold_until`.
10. `queued` — eligible, pass active, not next.
11. `eligible` — all conditions met.
12. `unknown` — inconsistent or missing data.

Why this order:

- A cron problem makes every timestamp unreliable, so it wins over the item
  state. The display can still show the hold date as information.
- Version Lock and “not managed” are own-item impossibilities. They win over a
  hold or a queue position; there is no automatic install to schedule.
- No offer means there is no automatic install to describe. The native message
  is absent in that case; details can say “no update pending.”
- A Flow predecessor is a dependency on another item. It wins over the item’s
  own hold because the item cannot run even if its hold ends. The display can
  name the predecessor and the hold date.
- A hold wins over the queue because the item is not eligible yet. The queue
  position is secondary.
- The queue wins over “eligible” because the pass may run later.

**Core-specific mapping.**

| Core offer | Automatic state | Display label |
|---|---|---|
| No newer version | `no_offer` | “No automatic core update pending.” |
| Minor/dev offer, auto-updates on | `held` / `eligible` | “Core minor release. WordPress does not expose a security flag; the hold applies.” |
| Major offer, major auto-updates off | `not_managed` | “No automatic core update; major auto-updates are off.” |
| Major offer, major auto-updates on | `held` / `eligible` | “Core major release.” |
| Any core offer, `WP_AUTO_UPDATE_CORE === false` | `not_managed` | “Core automatic updates are disabled by `WP_AUTO_UPDATE_CORE`.” |

There is no `core_security` state. The owner removed the claim. If a future
WordPress release exposes a reliable security flag, the mapping can add a
state; until then, “security” must not appear in the display.

### 6.5 Display mapping

| State | `display_kind` | Display (English; localized in code) |
|---|---|---|
| `eligible` | `exact` | “Next automatic install: {date} ({timezone}).” |
| `eligible` with queue | `earliest` | “Eligible at {date}; queued after {N} items in the current pass. It may run later.” |
| `held` | `earliest` | “Earliest automatic install: {eligible_at} or later ({timezone}). Hold ends {hold_until}.” |
| `not_observed` | `not_observed` | “The hold starts when the next check first sees this release. Next check: {next_check}. The earliest install is not known yet.” |
| `waiting_for_predecessor` | `waiting` | “Waiting for {A} to finish. No automatic install time yet.” |
| `locked` | `managed` | “Managed via {manager}. Update through your manager.” |
| `not_managed` | `none` | “Automatic updates are off for this item.” |
| `no_offer` | `none` | “No automatic update pending.” |
| `unknown_offer` | `none` | “Update data is stale or unknown. The next automatic decision is unknown.” |
| `wp_cron_disabled` | `cron` | “WP-Cron spawning is disabled. A real cron job must call `wp-cron.php`; the next automatic install time is unknown.” |
| `overdue` | `cron` | “The next check is overdue by {X}. WP-Cron may be stuck; the next automatic install time is unknown.” |
| `not_scheduled` | `cron` | “Automatic update not scheduled. There may be a problem with WP-Cron.” |
| `unknown` | `none` | “The next automatic update time is unknown.” |

The native row should use the compact form of the same sentence. The
update-core summary uses the global form:

> Next update pass: {next_check}. {N} eligible, {M} held, {K} waiting for Flow.
> [Schedule] [Flow]

The per-item update-core rows use the item display, not the global summary.

### 6.6 Timezone, date format, and DST

- **Calculation:** UTC timestamps. `first_seen`, `next_check`, `hold_until`,
  `eligible_at`, and `execution_at` are integers.
- **Display timezone:** `wp_timezone()`. Show `wp_timezone_string()` with the
  date when the surface has room. On this site, that is `Europe/Paris`.
- **Date format:** `get_option('date_format')` plus `get_option('time_format')`,
  trimmed. If empty, `Y-m-d H:i`. On this site, `j F Y` + `G\hi` yields
  “31 October 2026 1h00”.
- **Do not** use `date_i18n()` with the default timezone or `date()`; use
  `wp_date()` with the site timezone.
- **DST:** the first event is anchored in local wall time. WP-Cron repeats at
  fixed second intervals. After a DST transition, a daily 01:00 event can run
  at 02:00 or 00:00 local. The display must use the actual `wp_next_scheduled`
  timestamp, not a recomputed “01:00”. If the saved preferred time and the
  actual local time differ by an hour on a DST transition, show a small note:
  “The next check is at 02:00 because of the daylight-saving shift. Save the
  schedule to re-anchor it.” The plan may instead re-anchor once per
  transition; that is an implementation choice, not a display lie.
- **Site timezone change:** the stored UTC timestamp does not change; the local
  display does. The preferred time may no longer match. Show the actual local
  time and the same re-anchor note.
- **Weekly:** the next run is the same weekday and wall time in seven days
  *only if* the recurring event is rescheduled from local time. WP-Cron adds
  604800 seconds; across DST, the wall time can shift. Use the actual event.
- **Hourly:** the preferred time is ignored. The next check is the next hourly
  event, not “now” unless the event is due.
- **WordPress default:** the event is whatever core schedules. Do not apply the
  saved preferred time.

### 6.7 Missing data and fallbacks

| Missing data | Fallback |
|---|---|
| `next_check === false` | `not_scheduled`; no timestamp. |
| `next_check < now` | `overdue`; no future timestamp. Show the overdue duration from `human_time_diff`. |
| `first_seen` null, hold on | `not_observed`; show the next check and say the hold starts then. Do not show a hold expiry. |
| `first_seen` null, hold off | The hold is irrelevant; use the next check. |
| `target_version` empty | `no_offer`; no native message. |
| `installed_version` empty | Item removed; no row. |
| `offer_state` stale/unknown | `unknown_offer`; no exact time. |
| Flow facts absent | Treat as no predecessor, but keep the state `unknown` if the item is in a Flow chain and the facts are expected. |
| Queue facts absent | Treat as no queue position; use the next check. |
| Lock facts absent | Treat as unlocked; the display may still be wrong if Pro is inactive. The contract requires Pro to provide the facts when it is active. |
| `date_format`/`time_format` empty | `Y-m-d H:i`. |
| `wp_timezone_string()` empty | `UTC`. |
| `first_seen > now + 300` | Clock skew; ignore the row and show `not_observed`; log a warning. |
| Ledger hash for an old target | Ignore; the new target is `not_observed`. |

### 6.8 Worked example on the current site

The live probe on 2026-10-28 returned:

```json
{"update_check":{"recurrence":"daily","time":"01:00"},
 "delay_updates":{"enabled":true,"delay_value":2}}
```

The timezone is `Europe/Paris`; the next `wp_version_check` was about 6 hours
away; the ledger had one entry at `2026-10-28 13:01:05 Europe/Paris`.

| Value | Result |
|---|---|
| `now` | 2026-10-28 17:39 Europe/Paris (16:39 UTC) |
| `next_check` | 2026-10-29 01:00 Europe/Paris (00:00 UTC) |
| `first_seen` | 2026-10-28 13:01 Europe/Paris (12:01 UTC) |
| `hold_days` | 2 |
| `hold_until` | 2026-10-30 13:01 Europe/Paris (12:01 UTC) |
| `eligible_at` | 2026-10-31 01:00 Europe/Paris (00:00 UTC) — the first daily check at or after `hold_until` |
| `execution_at` | 2026-10-31 01:00 Europe/Paris, assuming no queue, Flow, or lock |
| Native row today | “Automatic update scheduled in 6 hours 20 minutes.” |
| Correct display | “Earliest automatic install: 31 October 2026 1h00 (Europe/Paris) or later. Hold ends 30 October 2026 13h01.” |

The difference is two days. The native text is not merely imprecise; it names
the wrong event.

If the ledger entry were missing:

> “The hold starts when the next check first sees this release. Next check: 29
> October 2026 1h00 (Europe/Paris). The earliest install is not known yet.”

If the item were version-locked:

> “Managed via Git. Update through your manager.”

If a Flow predecessor were waiting:

> “Waiting for Payment Gateway to finish. No automatic install time yet.”

### 6.9 Read-only ledger API

The display must not call `Updatronix_AutoUpdateDelay::filter_plugin()` or any
sibling. Those methods write `first_seen` and a deferred log row. Add:

```php
Updatronix_AutoUpdateDelay::peek_first_seen( string $type, object $offer ): ?int
```

Behavior:

- Decode the ledger once per request.
- Compute the same stable hash as the gate.
- Return the timestamp when the row exists and is plausible (`<= now + 300`).
- Return `null` when the hold is off, the row is absent, the target changed, or
  the timestamp is in the future beyond the skew allowance.
- Never write, never prune, never log.
- Expose the ledger key and the hash input for tests.

The calculator calls this method once per item. The gate remains the only writer.

### 6.10 Pseudocode

```php
function updatronix_schedule_item_state( array $in ): array {
    $now = (int) $in['now'];
    $next = $in['next_check'];
    $cron_disabled = (bool) $in['cron_disabled'];
    $hold_on = (bool) $in['hold_enabled'] && (int) $in['hold_days'] > 0;
    $first_seen = $hold_on ? $in['first_seen'] : null;
    $hold_until = ( $hold_on && null !== $first_seen )
        ? $first_seen + (int) $in['hold_days'] * DAY_IN_SECONDS
        : null;

    if ( $cron_disabled ) {
        return state( 'wp_cron_disabled', null, $hold_until, null );
    }
    if ( false === $next ) {
        return state( 'not_scheduled', null, $hold_until, null );
    }
    if ( (int) $next < $now ) {
        return state( 'overdue', null, $hold_until, null );
    }
    if ( 'locked' === $in['lock']['state'] ) {
        return state( 'locked', null, $hold_until, null );
    }
    if ( 'on' !== $in['auto_update'] ) {
        return state( 'not_managed', null, $hold_until, null );
    }
    if ( 'pending' !== $in['offer_state'] ) {
        return state( 'no_offer', null, $hold_until, null );
    }
    if ( 'waiting' === $in['flow']['state'] ) {
        return state( 'waiting_for_predecessor', null, $hold_until, null );
    }
    if ( $hold_on && null === $first_seen ) {
        return state( 'not_observed', null, null, null );
    }

    $floor = $hold_on ? $hold_until : $now;
    $eligible = first_check_at_or_after( $floor, (int) $next, $in['interval'], $now );

    if ( $hold_on && $now < $hold_until ) {
        return state( 'held', $eligible, $hold_until, null );
    }
    if ( 'active' === $in['queue']['state'] && (int) $in['queue']['position'] > 0 ) {
        return state( 'queued', $eligible, $hold_until, $eligible );
    }

    return state( 'eligible', $eligible, $hold_until, $eligible );
}
```

`state()` fills `display`, `display_kind`, `reason_code`, and `details_url`.

---

## 7. Truth table for the display

The table covers the meaningful combinations. The precedence in §6.4 resolves
any row not listed. “Native row” means the plugin row, the network theme row,
or the single-site theme overlay. “Update-core” means the core `<p>`, the
plugin row, or the theme row.

Legend: `—` = none/not applicable; `FS` = first seen; `P` = pending offer;
`U` = unknown/stale offer; `N` = no offer.

| ID | Offer | Auto | Hold | FS | Next check | Flow | Queue | Lock | Cron | Core | Native row / update-core text |
|---|---|---|---|---|---|---|---|---|---|---|---|
| T01 | N | any | any | any | any | any | any | any | any | any | No automatic update pending. Do not inject a message where native has none. |
| T02 | P | On | Off | any | Future | — | — | — | OK | plugin/theme | “Next automatic install: {next_check}.” |
| T03 | P | On | Off | any | Overdue | — | — | — | Overdue | plugin/theme | “The next check is overdue by {X}. WP-Cron may be stuck.” |
| T04 | P | On | Off | any | None | — | — | — | Missing | plugin/theme | “Automatic update not scheduled. There may be a problem with WP-Cron.” |
| T05 | P | On | Off | any | any | — | — | — | Disabled | plugin/theme | “WP-Cron spawning is disabled. A real cron job must call wp-cron.php.” |
| T06 | P | Off/Forced off | any | any | any | — | — | — | any | any | “Automatic updates are off for this item.” Native message is absent. |
| T07 | P | On | On | FS | Future | — | — | — | OK | any | “Earliest automatic install: {eligible_at} or later. Hold ends {hold_until}.” |
| T08 | P | On | On | FS | Future | — | — | — | OK | any | Hold expired: “Next automatic install: {eligible_at}.” |
| T09 | P | On | On | missing | Future | — | — | — | OK | any | “The hold starts when the next check first sees this release. Next check: {next_check}. Earliest install unknown.” |
| T10 | P | On | any | any | any | Wait(A) | — | — | OK | any | “Waiting for A to finish. No automatic install time yet.” |
| T11 | P | On | any | any | any | Blocked/Failed(A) | — | — | OK | any | “Waiting for A to recover. No automatic install time yet.” |
| T12 | P | On | any | any | any | Locked(A) | — | — | OK | any | “Waiting for A to be updated by {manager}. No automatic install time yet.” |
| T13 | P | On | any | any | Future | — | Pass, N ahead | — | OK | any | “Eligible at {eligible_at}; queued after N items in the current pass. It may run later.” |
| T14 | P | On | any | any | Future | — | Pass, next | — | OK | any | “Next in the current pass. It may run after the current item.” |
| T15 | P | On | any | any | Future | — | — | Locked | OK | any | “Managed via {manager}. Update through your manager.” |
| T16 | P | On | Off | any | Future | — | — | — | OK | core minor | “Next automatic install: {next_check}. Core minor release; security status not exposed.” |
| T17 | P | On | On | FS | Future | — | — | — | OK | core minor | Held/eligible text plus “Core minor release; security status not exposed.” |
| T18 | P | On | any | any | any | — | — | — | any | core major, major off | “No automatic core update; major auto-updates are off.” |
| T19 | P | On | any | any | Future | — | — | — | OK | core major, major on | Held/eligible text plus “Core major release.” |
| T20 | N | any | any | any | any | any | any | any | any | core | “No automatic core update pending.” |
| T21 | P | On | On | missing | Overdue | — | — | — | Overdue | any | “The next check is overdue by X. The hold has not started; the next automatic install time is unknown.” |
| T22 | P | On | On | FS | Overdue | — | — | — | Overdue | any | “The next check is overdue by X. Hold ends {hold_until}; the next automatic install time is unknown.” |
| T23 | any | any | any | any | any | any | any | any | any | any | Multisite subsite: “This site uses the network update schedule. Manage it in Network Admin.” No local timestamp. |
| T24 | P | On | any | any | Future | — | — | — | OK | any | DST mismatch: actual timestamp plus “The saved 01:00 has shifted to 02:00 because of daylight saving. Save the schedule to re-anchor.” |
| T25 | P | On | On | FS | Future | — | — | — | OK | translation | Details only: “Translation update; the hold applies if enabled.” Native rows do not show translations. |
| T26 | U | On | any | any | any | — | — | — | any | any | “Update data is stale or unknown. The next automatic decision is unknown.” |
| T27 | P | On | Off | any | Future | Done(A) | — | — | OK | any | “Next automatic install: {next_check}.” Flow does not add a qualifier when the predecessor is done. |
| T28 | P | On | On | FS | Future | Done(A) | Pass, N ahead | — | OK | any | Held text, then queue qualifier if the hold has expired; if the hold is active, held wins. |
| T29 | P | On | On | FS | Future | Wait(A) | Pass, N ahead | — | OK | any | Waiting wins; “Waiting for A. Hold ends {hold_until}; no automatic install time yet.” |
| T30 | P | On | any | any | any | — | — | Locked | any | any | Locked wins over hold and queue; “Managed via {manager}.” |

---

## 8. Exhaustive case matrix

Legend: “Blocking?” asks whether the current implementation blocks the expected
display. `Yes` means the current display is wrong or the expected state cannot
be shown; `Partial` means the happy path works but an important case does not;
`No` means the current behavior is already acceptable. “Decision” points at the
settled group or the calculator rule.

| ID | Item type | Offer state | Hold | First seen | Next check | Flow/queue/lock | Expected display | Current display | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|---|
| SC-01 | Plugin | Pending | Off | — | Future | — | Next automatic install: {next}. | “scheduled in X” (next check) | No | Q6 |
| SC-02 | Plugin | Pending | On | Present | Future | — | Earliest install after the hold. | Next check | Yes | Q6 |
| SC-03 | Plugin | Pending | On | Present, expired | Future | — | Next automatic install: {next}. | Next check (correct by accident) | No | Q6 |
| SC-04 | Plugin | Pending | On | Missing | Future | — | Not observed; hold starts at next check. | Next check | Yes | Q4/Q6 |
| SC-05 | Plugin | Pending | On | Present | Overdue | — | Overdue; hold date, no install time. | “overdue by X” (no hold) | Partial | Q6 |
| SC-06 | Plugin | Pending | On | Missing | Overdue | — | Overdue; hold not started. | “overdue by X” | Partial | Q4 |
| SC-07 | Plugin | Pending | On | any | Missing | — | WP-Cron problem. | “not scheduled” | No | Q6 |
| SC-08 | Plugin | Pending | any | any | any | — | WP-Cron spawning disabled. | “scheduled in X” | Yes | Q6 |
| SC-09 | Plugin | None | any | any | any | — | No automatic update pending. | No message | No | Q6 |
| SC-10 | Plugin | Unknown/stale | any | any | any | — | Update data stale; unknown. | Native message | Yes | Q6 |
| SC-11 | Plugin | Pending | any | any | any | Auto off | No message; details “auto updates off.” | No message | No | Q6 |
| SC-12 | Plugin | Pending | any | any | any | Forced off | No message; details constant. | No message | No | Q6 |
| SC-13 | Plugin | Pending | any | any | any | Flow wait A | Waiting for A; no install time. | Countdown | Yes | Q9 |
| SC-14 | Plugin | Pending | On | Present | any | Flow wait A (held) | Waiting for A; hold date. | Countdown | Yes | Q9 |
| SC-15 | Plugin | Pending | any | any | any | Flow wait A (locked) | Waiting for manager. | Countdown | Yes | Q11 |
| SC-16 | Plugin | Pending | any | any | any | Flow wait A (failed) | Waiting for A to recover. | Countdown | Yes | Q9 |
| SC-17 | Plugin | Pending | any | any | any | Flow wait A (not installed) | Blocked by predecessor. | Countdown | Yes | Q9 |
| SC-18 | Plugin | Pending | any | any | any | Queue pass, 3 ahead | Eligible at X; queued after 3; may run later. | Countdown | Yes | Q10 |
| SC-19 | Plugin | Pending | any | any | any | Queue pass, next | Next in the pass. | Countdown | Yes | Q10 |
| SC-20 | Plugin | Pending | any | any | any | Queue follow-up | Queued in the follow-up; may run later. | Countdown | Yes | Q10 |
| SC-21 | Plugin | Pending | any | any | any | Version locked | Managed via Git; no countdown. | Countdown | Yes | Q11 |
| SC-22 | Plugin | Pending | any | any | any | Version locked, manual allowed | Managed via Git; manual allowed. | Countdown | Yes | Q11 |
| SC-23 | Theme | Pending | On | Present | Future | — | Earliest install after hold. | Countdown | Yes | Q6 |
| SC-24 | Theme | None | any | any | any | — | No update; no message. | No message | No | Q6 |
| SC-25 | Theme | Pending | any | any | any | Auto off | No message. | No message | No | Q6 |
| SC-26 | Theme | Pending | any | any | Future | — | Network schedule exact. | Countdown | Yes | Q14 |
| SC-27 | Theme | Pending | any | any | any | Subsite | Network schedule link; no local timestamp. | “not scheduled” | Yes | Q14 |
| SC-28 | Core minor | Pending | On | Present | Future | — | Held/eligible; security unknown. | Countdown | Yes | Q5/Q6 |
| SC-29 | Core major | Pending | any | any | any | — | Major off: no automatic update. | No core message | No | Q5 |
| SC-30 | Core major | Pending | On | Present | Future | — | Held/eligible; major label. | Countdown | Yes | Q5/Q6 |
| SC-31 | Core any | Pending | On | Present | Future | — | No security exemption; hold applies. | UI says security skips | Yes | Q5 |
| SC-32 | Core | None | any | any | any | — | No core update. | No message | No | Q5 |
| SC-33 | Translation | Pending | On | Present | Future | — | Details only; no native countdown. | No native message | No | Q3 |
| SC-34 | Plugin | Pending | 0 (off) | — | Future | — | Next automatic install. | 0–30 UI; server 1 if enabled | Partial | Q4 |
| SC-35 | Plugin | Pending | 365 | Present | Future | — | Eligible after 365 days. | UI rejects >30 | Yes | Q4 |
| SC-36 | Plugin | Pending, new target | On | Old target entry | Future | — | Not observed for the new target. | A naive lookup may reuse old | Yes | Q4 |
| SC-37 | Plugin | Pending | On | Future (clock skew) | Future | — | Not observed; warn. | A naive lookup holds longer | Yes | Q4 |
| SC-38 | Plugin | Pending | any | any | Hourly | — | Hourly; preferred time ignored. | Four-value picker | Partial | Q3 |
| SC-39 | Plugin | Pending | any | any | Twice daily | — | Anchor + 12h. | Four-value picker | Partial | Q3 |
| SC-40 | Plugin | Pending | any | any | Weekly | — | Same weekday/time +7d. | Four-value picker | Partial | Q3 |
| SC-41 | Plugin | Pending | any | any | Default | — | Core schedule; time ignored. | Four-value picker | Partial | Q3 |
| SC-42 | Plugin | Pending | any | any | DST transition | — | Actual timestamp; note. | Native relative; screen may recompute | Partial | Q6 |
| SC-43 | Plugin | Pending | any | any | Timezone change | — | Actual timestamp; new local time. | Meta reads current TZ | Partial | Q6 |
| SC-44 | Any | any | any | any | any | — | Native DOM changes: leave native text. | Native text | No | Q12 |
| SC-45 | Core | Pending | any | any | Future | — | Update-core global summary. | Native core `<p>` | Yes | Q7 |
| SC-46 | Plugin | Pending | any | any | Future | Update-core row without checkbox | Leave native + global summary. | Native row | Partial | Q7 |
| SC-47 | Plugin | Manual update | — | — | any | — | No offer; no message. | No message | No | Q6 |
| SC-48 | Plugin | WP-CLI update | — | — | any | — | No offer; no message. | No message | No | Q6 |
| SC-49 | Any | any | any | any | any | — | No big blue notice. | Big blue notice | Yes | Q8 |
| SC-50 | Any | any | any | any | any | WP-Cron disabled | One-line hint. | Big blue notice | Yes | Q8 |
| SC-51 | 200 plugins | Pending | any | any | Future | — | One precomputed map; fast. | Native per row | Partial | Q13 |
| SC-52 | Any | any | any | any | any | — | Localized strings; translator comments. | Native English only | No | Q12 |
| SC-53 | Any | any | any | any | any | — | Text replaced in place; no focus change. | Native text | No | Q12 |
| SC-54 | Any | any | any | any | any | Network admin | Network schedule. | Network option | Partial | Q14 |
| SC-55 | Any | any | any | any | any | Subsite | Network link; no false local message. | “not scheduled” | Yes | Q14 |
| SC-56 | Any | Pending | any | any | Future | — | Map refreshed after toggle/save. | Native text | Partial | Q13 |
| SC-57 | Plugin | Target changes | On | Old target entry | Future | — | New target: not observed. | Native countdown | Yes | Q4 |
| SC-58 | Plugin/theme | Deleted | — | — | any | — | No row; no message. | No row | No | Q6 |
| SC-59 | Core | Pending | On | Present | Future | — | Held; UI no longer says security skips. | UI claim | Yes | Q5 |
| SC-60 | Plugin | Pending | On, 0 days | — | Future | — | Treat as off; exact next check. | UI 0 = off; server 0 = off | No | Q4 |

### 8.1 Coverage of the prompt’s required cases

| Required case | Rows |
|---|---|
| Pending offer | SC-01–SC-08, SC-10, SC-13–SC-23, SC-28, SC-30, SC-31, SC-34–SC-43, SC-54–SC-57 |
| Auto-update on/off | SC-01, SC-11, SC-12, SC-25 |
| Hold off/on/expired/not observed | SC-01–SC-06, SC-23, SC-28, SC-34–SC-37 |
| First seen present/missing/stale | SC-02–SC-04, SC-21–SC-23, SC-36, SC-37, SC-57 |
| Next check future/overdue/missing/disabled | SC-01, SC-05–SC-08, SC-21, SC-22 |
| Flow waiting/blocked/locked/failed | SC-13–SC-17 |
| Queue position/follow-up | SC-18–SC-20 |
| Version lock | SC-21, SC-22, SC-30 |
| Core security/minor/major | SC-28–SC-32, SC-59 |
| Multisite network/subsite | SC-26, SC-27, SC-54, SC-55 |
| Timezone/DST | SC-42, SC-43 |
| Native DOM change | SC-44 |
| Update-core surfaces | SC-45, SC-46 |
| Blue notice | SC-49, SC-50 |
| Performance | SC-51 |
| i18n/a11y | SC-52, SC-53 |
| Manual/WP-CLI | SC-47, SC-48 |
| Deleted item | SC-58 |
| Hold bounds | SC-34, SC-35, SC-60 |

---

## 9. Blocking cases and proposed handling

“Blocking” means the current design cannot express the expected display
without a new concept, or a naive implementation would write data, lie, or break
another screen. The owner settled the product choices; the handling below is
the implementation contract.

| ID | Case | Why it blocks today | Proposed handling | Decision |
|---|---|---|---|---|
| B1 | No filter on `wp_get_auto_update_message()` | The native text cannot be corrected server-side. | Server-side filters where they exist; one scoped JS layer for update-core and fallback. | Q12 |
| B2 | Update-core rows are plain text with no per-row filter | A server-side replacement would need output buffering. | JS text-node replacement; identify plugin/theme rows by checkbox value; leave native text when identity is missing. | Q7/Q12 |
| B3 | No reliable core security flag | The UI claim cannot be implemented truthfully. | Remove the claim; the hold applies to every core automatic update. | Q5 |
| B4 | `first_seen` missing before the first automatic pass | A display that reads through the gate would write a row; one that ignores the row would lie. | Read-only ledger peek; `not_observed` state; next check shown; no hold expiry. | Q4 |
| B5 | Flow predecessor not done | The item cannot run even if its own hold ends. | `waiting_for_predecessor`; name the predecessor; no timestamp; link to Flow. | Q9 |
| B6 | Queue pass active | The item may run later than the eligible check. | Show the pass start/eligible time and the queue position; “may run later”; no exact per-item promise. | Q10 |
| B7 | Version-locked item | No automatic path. | `locked`; manager label; no countdown. | Q11 |
| B8 | Hold bounds disagree | The UI cannot represent the server’s range. | Canonical `0..365`, `0` = off; mirror everywhere. | Q4 |
| B9 | Multisite subsite has no local `wp_version_check` event | The native “not scheduled” is false. | Network schedule link; no local timestamp; network admin only for changes. | Q14 |
| B10 | DST shifts the local wall clock | A recomputed “01:00” would be wrong. | Display the actual `wp_next_scheduled` timestamp; warn or re-anchor on transition. | Q6 |
| B11 | Stale ledger row after a target change | A lookup by item id would reuse the old first-seen. | Hash by type + item + target; ignore old hashes; prune on read/maintenance. | Q4 |
| B12 | Native DOM changes | The override could inject into the wrong row or fail silently. | Feature-detect selectors and text patterns; leave native text on failure; tests with fixtures. | Q12 |
| B13 | i18n and a11y | A JS string with no translator comment or a focus change would regress. | Localize every string; replace text in place; no live region; no focus change. | Q12 |
| B14 | Per-row N+1 | A detector/planner call per row is expensive. | One request-scoped map; no network; no detector calls in the row loop. | Q13 |
| B15 | Big blue notice | It documents the false text instead of correcting it. | Remove; one-line WP-Cron hint only. | Q8 |
| B16 | Held item frozen in the queue | A blocked row prevents re-planning after the hold expires. | Adopt the queue analysis’s pass model; if not, recheck blocked rows each tick and show “blocked until recheck.” | Q10 + Queue |
| B17 | Manual check does not set `first_seen` | An admin may expect the hold to start at the manual check. | Document that the hold starts at the next automatic pass; display `not_observed` until then. | Q4 |
| B18 | Core major auto-updates off | The core message may not appear at all. | `not_managed`; no core message; the global summary can say “core major auto-updates off.” | Q5/Q7 |
| B19 | Update-core core message only when major is on | A minor-only site has no core message to replace. | Add the global summary through `core_upgrade_preamble` or a small notice; do not invent a core row. | Q7 |
| B20 | Single-site theme template is global | A per-theme message cannot be rendered by the native template alone. | Add the per-theme string in `wp_prepare_themes_for_js()`; filter the template; JS fallback. | Q7/Q12 |
| B21 | Other plugins also alter the native row | Two scripts can overwrite each other. | Distinct data attributes; append notices; do not replace the whole cell. | Q12 |
| B22 | Site timezone change | The stored timestamp is unchanged; the local display changes. | Display the actual local time; note that the preferred time may need a save. | Q6 |
| B23 | Clock skew / future first seen | A bad row could hold an offer longer than configured. | Ignore future rows beyond 300 seconds; log a warning; `not_observed`. | Q4 |
| B24 | Translation offers | The native surfaces do not show translations, but the gate holds them. | Details-only state; do not add a native translation row. | Q3 |
| B25 | Large plugin list | A per-row detector loop can time out. | Bound the map; use the transients; cache per request; no network. | Q13 |

---

## 10. Decisions settled by grilling

The owner answered four grouped decisions on 2026-10-28. The full record is
`updatronix/.agents/notes/2026-10-28-grill-update-schedule.md`.

| Group | Owner answer | Settled decision |
|---|---|---|
| Time picker | Two WPDS selects: 24 hours + 60 minutes. | The picker exposes every hour and every minute. The stored value stays `HH:MM`. |
| Hold policy | 0–365 days, 0 = off; remove the security-exemption claim. | One canonical range. The hold applies to every core automatic update. No security exemption. |
| True moment | State + earliest execution; per-item rows get item state, update-core gets a next-pass summary and link. | No exact time when Flow or the queue makes execution uncertain. Per-item rows get the item state. Update-core gets a summary and a link. |
| Notice and states | Remove the big notice; one-line WP-Cron-disabled hint; waiting/locked/queued show state, never a countdown. | The banner is removed. The WP-Cron hint is one line. Waiting, locked, and queued states do not show a countdown. |

### 10.1 The fifteen seed questions

| Seed | Settled outcome |
|---|---|
| Q1 · Picker shape | Two WPDS selects (24 hours + 60 minutes). |
| Q2 · Minute step | Every minute. |
| Q3 · Recurrence semantics | Keep the five options. Explain hourly ignores time; twice daily anchors +12h; weekly same weekday/time +7d; default follows core. Preserve the time when inactive. |
| Q4 · Hold bounds | 0–365, 0 = off, canonical client/server/REST. |
| Q5 · Core security exemption | Remove the claim; no exemption. |
| Q6 · True moment | State + earliest execution; no exact when uncertain. |
| Q7 · Per-item vs global | Per-item rows get item state; update-core gets a next-pass summary and link; rows get item state where identifiable. |
| Q8 · Blue notice | Remove; one-line WP-Cron-disabled hint only. |
| Q9 · Flow waiting | “Waiting for A; no automatic install time yet.” |
| Q10 · Queue position | “Eligible at X; queued after N; may run later.” |
| Q11 · Version-locked items | “Managed via {manager}; update through your manager.” |
| Q12 · Native admin safety | Scoped vanilla JS + server-side filters; feature-detect; native fallback; localized. |
| Q13 · Caching/performance | One server-side map per request; no N+1; invalidate on settings/offer/pass/lock changes. |
| Q14 · Multisite | Network schedule, main-site cron, network admin; subsite link or no countdown. |
| Q15 · Tests | PHP unit, JS DOM, integration, E2E, multisite. |

---

## 11. Open questions and residual assumptions

**No owner decision remains open.** The grilling round settled the four product
groups and the fifteen seed questions.

### Owner decisions (settled 2026-10-28)

No owner decision remains open. The four grouped decisions were settled on
2026-10-28. The three implementation choices with the largest scope are called
out at the end of this section; they are assumptions, not reopened owner
decisions.

### OPEN — assumption

1. **Preferred time storage for hourly/default.** Preserve the normalized
   `HH:MM` even when the recurrence is hourly or WordPress default. The server
   currently stores `''`; the plan must decide whether to change the stored
   shape or keep a separate draft value. `OPEN — assumption`.
2. **DST re-anchor.** The plan must choose between (a) display the actual
   timestamp and warn when the wall clock shifts, and (b) re-anchor the
   recurring event once per DST transition. Recommendation: (a) first, because
   it is truthful and does not churn cron; add (b) only if the owner wants the
   wall clock preserved. `OPEN — assumption`.
3. **Native row date format.** Use the site `date_format`/`time_format` with the
   timezone string. If the row becomes too wide, test a compact variant
   (`M j, Y \a\t H:i T`) and ask the owner before introducing a second format.
   `OPEN — assumption`.
4. **Update-core summary placement.** Use `core_upgrade_preamble` for a small
   summary above the tables, or replace the core `<p>` only when it exists.
   Recommendation: both, with the summary always present and the core `<p>`
   replaced when present. `OPEN — assumption`.
5. **Read-only ledger API name.** `peek_first_seen()` is proposed; the plan may
   choose a different name. `OPEN — assumption`.
6. **Pro facts filter name.** `updatronix_schedule_item_state` is proposed; the
   plan may choose a different name. It must be read-only and bounded.
   `OPEN — assumption`.
7. **Queue adoption.** If the queue analysis’s pass model is not implemented
   before the Schedule display, the Schedule must show the current blocked-row
   state as “blocked until recheck,” not a hold expiry. `OPEN — assumption`.
8. **Flow contract timing.** The Flow analysis is implemented last. Until then,
   the Schedule display can use the current Flow plan facts; the contract is
   forward-compatible. `OPEN — assumption`.
9. **Translation display.** Translations are not native rows; the Schedule/Flow
   details may show them. `OPEN — assumption`.
10. **Multisite subsite link.** The link target is the network Schedule tab; the
    exact URL helper is an implementation detail. `OPEN — assumption`.
11. **Caching TTL.** Prefer request-scoped. If a persistent cache is used, TTL
    ≤ 5 minutes and the key includes settings, target, first_seen, and pass id.
    `OPEN — assumption`.
12. **Core security label.** Do not use the word “security” for a minor release
    unless WordPress exposes a reliable flag. The current label is “core minor
    release; security status not exposed.” `OPEN — assumption`.

The three assumptions with the largest scope, if the owner wants to confirm
them, are DST re-anchoring, preserving the preferred time for hourly/default,
and the update-core summary placement.

---

## 12. Contracts with Version Lock, Queue, Flow, and Logger

### 12.1 Version Lock

| Direction | Contract |
|---|---|
| Version Lock provides to Schedule | A per-item decision: `allowed`, `native_vcs`, `version_controlled`, `unknown`, with `manager`, `scope`, `reason`, and `evidence`. The decision is gated by `safe_mode` (analysis 01, Q2). |
| Schedule provides to Version Lock | A display slot on the native row and update-core; the Schedule must not compute a next-eligible time for a locked item. The item shows “Managed via {manager}; update through your manager.” |
| Precedence | Version Lock first, then compatibility, then delay. One primary reason. A locked item never shows a countdown, even if it is also held or queued. |
| Manual updates | Version Lock’s optional manual block is independent of the Schedule. The Schedule display says “Manual updates are allowed” only when the automatic-only lock is active and the manual toggle is off. |
| Data | The manager label and scope come from Version Lock; the Schedule does not re-detect VCS or Composer. |

### 12.2 Queue

| Direction | Contract |
|---|---|
| Queue provides to Schedule | The pass id, `started_at`, the ordered items, the current item, the per-item state (`pending`, `held`, `blocked`, `running`, `succeeded`, `failed`, `skipped`), the queue position, and the next follow-up time. |
| Schedule provides to Queue | The next check timestamp, the per-offer hold, `first_seen`, and `eligible_at`. The Schedule must not promise an install before the item is eligible. |
| Exactness | The queue position is a count, not a duration. The display says “queued after N items; may run later.” It never computes `eligible_at + N * estimate`. |
| Hold | A follow-up may drain an already-started pass, but it must not bypass a hold. The delay policy is re-evaluated at execution. The display must show the hold state, not the queue’s blocked row as a promise. |
| Freeze | If the current blocked-row model survives, the display shows “blocked until recheck” after the hold expires; it does not show a past hold date as an install time. The preferred handling is the pass model in analysis 02. |
| Translations | Translations are not pass items; they run natively last. The Schedule does not show a translation countdown. |

### 12.3 Flow

| Direction | Contract |
|---|---|
| Flow provides to Schedule | The pass/plan facts: `state` (`waiting_for_predecessor`, `offer_held`, `offer_blocked`, `locked`, `failed`, `done`), `waiting_for`, the predecessor label, the reason, and the predecessor’s hold/lock state. |
| Schedule provides to Flow | The per-item `eligible_at`, the next check, and the display state. A held predecessor makes the downstream’s true moment “after the predecessor,” not the downstream’s own hold expiry. |
| Waiting | If the predecessor is not done, the state is `waiting_for_predecessor`; there is no timestamp. The display names the predecessor and links to Flow. |
| Hold and Flow | If B waits for A and B also has a hold, display “Waiting for A; B’s hold ends {date}; no automatic install time yet.” Waiting wins; the hold date is secondary. |
| Failure | A failed predecessor makes the downstream wait for the next pass; the display says “Waiting for A to recover.” It does not show a countdown. |
| Manual updates | A manual update to the target makes the node done; the Schedule re-evaluates on the next page load. A manual update to another version is logged, not blocked. |

### 12.4 Logger

| Direction | Contract |
|---|---|
| Logger provides to Schedule | One Free log row per delayed offer, with `first_seen`, `hold_until`, `eligible_at`, and the reason. The existing `delayed` row is the anchor. |
| Schedule provides to Logger | No writes from the display. The display must not create a `delayed` row by reading the ledger. |
| Dedupe | One row per offer/target, not per tick. The event key includes the offer hash. A later pass with the same target must not duplicate the row; a new target is a new row. |
| Waiting | A Flow wait writes one row when the wait starts and one when it becomes permanent, per analysis 03. The Schedule display reads the pass state, not the log, for its sentence. |
| Pass row | The pass summary is the queue/Flow contract; the Schedule does not write a second summary. |
| Copy | The log’s “eligible no earlier than” must use the calculator’s `eligible_at`, not `max(first_seen + delay, time())`. One source of truth. |

### 12.5 Shared facts filter

Free owns the calculator. Pro supplies the facts through one read-only filter:

```php
$facts = apply_filters(
    'updatronix_schedule_item_state',
    array(
        'lock'  => array( 'state' => 'none' ),
        'flow'  => array( 'state' => 'none' ),
        'queue' => array( 'state' => 'none' ),
    ),
    $type,
    $id,
    $target_version
);
```

Pro returns:

```php
array(
    'lock'  => array( 'state' => 'locked', 'manager' => 'Git', 'scope' => 'item', 'reason' => '...' ),
    'flow'  => array( 'state' => 'waiting', 'waiting_for' => 'plugin:a/a.php', 'reason' => 'offer_held' ),
    'queue' => array( 'state' => 'active', 'position' => 3, 'total' => 5, 'pass_id' => '...', 'started_at' => 1761706800 ),
);
```

Rules:

- The filter is read-only and bounded. It must not write an option, enqueue a
  job, or run a network request.
- Free calls it once per item and caches the result per request.
- If Pro is inactive, the filter returns the defaults; the display is truthful
  for Free-only features but cannot know about Pro locks/flows. The native
  surfaces should say “Update Shield is not active” only if that is useful; the
  default is to show the Free schedule state.
- The filter must be documented in the Pro bootstrap and tested with a
  Pro-inactive fixture.

---

## 13. Implementation constraints

### 13.1 Native admin surgical override

- Do not replace `plugins.php`, `themes.php`, `update-core.php`, or their
  tables.
- Do not use output buffering or a global `str_replace`.
- Do not add a global banner. The only native notice is the one-line WP-Cron
  hint.
- Use the server-side filters where they exist:
  - `plugin_auto_update_setting_html` (`class-wp-plugins-list-table.php:1457`).
  - `theme_auto_update_setting_html` (`class-wp-ms-themes-list-table.php:887`).
  - `wp_prepare_themes_for_js` (`wp-admin/includes/theme.php:647`) plus
    `theme_auto_update_setting_template` (`themes.php:829`).
- Use one small vanilla JS file for update-core and as a fallback. Enqueue it
  only on `plugins.php`, `plugins-network`, `themes.php`, `themes-network`,
  `update-core.php`, and `update-core-network`, behind the same capability
  checks as the screens.
- Keep the version-lock augment and the Safe Mode augment separate. Use
  distinct classes and data attributes. The Schedule script replaces only the
  native time text; it appends no policy notices.

### 13.2 Selectors and replacement rules

Use the selectors in §3.9. Additional rules:

- Match the native text pattern only. Do not replace arbitrary text in the row.
- Preserve the hidden class. Do not unhide a message for an item whose
  auto-updates are off.
- Preserve the native text in a data attribute for fallback and tests.
- Make the pass idempotent with `data-updatronix-enhanced`.
- Do not reinsert a message after WordPress empties `.auto-update-time` post
  update.
- Do not touch `tr.plugin-update-tr`, `.update-message`, the version-lock
  notice, the Safe Mode notice, or another plugin’s row.
- For update-core plugin/theme rows, require a checkbox value to identify the
  item. If the checkbox is missing, leave the native text and use the global
  summary.

### 13.3 Data and localization

- Compute one map server-side:

```php
array(
    'plugin:foo/foo.php' => array(
        'display'      => '...',
        'display_kind' => 'earliest',
        'timestamp'    => 1761793200,
        'timezone'     => 'Europe/Paris',
        'state'        => 'held',
        'reason'       => 'hold_active',
    ),
    'theme:bar' => array( ... ),
    'core:core' => array( ... ),
)
```

- Localize it with `wp_localize_script` or `wp_add_inline_script` plus
  `wp_json_encode`. Do not fetch per row.
- Include the site timezone string and the formatted timestamp. Do not format
  dates in JS from a raw timestamp unless the map also includes the format;
  server formatting is preferred.
- Include the native text pattern in the JS for matching; do not rely on a
  single English string.
- Localize every string with `__()`/`esc_html__()` and a translator comment.
  Text domain `updatronix` for Free, `updatronix-pro` for Pro.
- Do not reword existing strings without owner approval. The core-security
  claim removal is approved; the plan must list the exact old and new strings.

### 13.4 Accessibility

- Replace text in place. Do not move focus, open a dialog, or add an
  `aria-live` region for a static page load.
- Keep the native reading order. The message remains in the same cell/paragraph.
- If a details link is added, use a real `<a>` with an accessible name; do not
  use a clickable `<span>`.
- Do not use color alone to convey held/waiting/locked. Use words.
- Keep the text short enough to wrap; do not truncate the state.
- Test with a screen reader and keyboard only; the picker uses two WPDS
  selects, each with a visible label.

### 13.5 Performance and caching

- One pass over the installed plugins/themes and the three update transients.
- No `wp_update_plugins()`/`wp_update_themes()`/`wp_version_check()` on page
  render. Use the current transients and mark stale data.
- No VCS/Composer detector calls per row. Version Lock facts come from the Pro
  filter, which should cache per request.
- No Flow planner call per row. Flow facts come from the pass/plan filter.
- Bound the map to 200 items; if the site has more, show the first 200 and a
  “details” link. Do not silently truncate (report it).
- Request-scoped static cache keyed by `type:id|target`. Invalidate on:
  `updatronix_after_save_network_schedule`, `updatronix_after_save_settings`,
  `upgrader_process_complete`, `automatic_updates_complete`,
  `deleted_plugin`, `deleted_theme`, `switch_theme`, `activated_plugin`, and
  the Pro pass/flow actions. A persistent cache, if any, has a TTL ≤ 5 minutes.
- The native list-table filters run per row; use the precomputed map, not a
  fresh calculation per filter call.

### 13.6 Multisite

- The Schedule is a network option (`updatronix_network_schedule`). The cron
  event is on the main site. `WP_Automatic_Updater::run()` is main-site only.
- Network admin screens use the network schedule and the main-site event.
- A subsite screen must not read its own empty `wp_version_check` event as
  “not scheduled.” Show “This site uses the network update schedule. Manage it
  in Network Admin.” with a network link, or suppress the message.
- The native override must respect `is_network_admin()` and the network
  capability. Do not enqueue the JS on a subsite where it cannot produce a
  truthful timestamp.
- Test with `WP_MULTISITE=1 bin/harness integration --filter Multisite`.

### 13.7 Graceful degradation

- If the map is missing, the selectors do not match, or the native text pattern
  changes, leave the native text. Do not throw, do not blank the row, do not
  show a red error.
- If the theme overlay is rendered after DOM ready, re-run the pass with a
  scoped observer or the overlay event; if that fails, leave the native text.
- If another plugin replaces the same text, the last writer wins; do not fight
  it. Use a data attribute and a distinct class.
- If the site is in a language other than English, match the localized native
  pattern or use the `.auto-update-time` element and replace its text without a
  pattern match; for update-core, the pattern is English-only, so use the
  element/row identity and a conservative fallback. Add a translated pattern
  list from `__()` for the three native variants.

### 13.8 Security

- Escape all server output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
- The map contains only display data; no secrets, no file paths, no license
  data.
- The JS reads a localized object; it does not accept user input.
- The native screens require `update_core`, `update_plugins`, or
  `update_themes`; the enqueue checks the same capability.
- No new REST route is required. If one is added for the map, it needs the
  existing `updatronix/v1` permission callback and a nonce; do not expose
  per-item data to unprivileged users.
- No `innerHTML` with the map; use `textContent`.

### 13.9 What not to do

- Do not change `wp_get_auto_update_message()` in core.
- Do not replace the native update tables.
- Do not use the blue banner as the fix.
- Do not call `Updatronix_AutoUpdateDelay::filter_*()` from a display.
- Do not compute the hold from the release date.
- Do not show a countdown for a locked, waiting, or not-managed item.
- Do not promise an exact per-item time when the queue or Flow is active.
- Do not introduce a second date format without owner approval.
- Do not read Pro options directly from Free; use the facts filter.
- Do not run a detector or planner per row.
- Do not log on page render.

---

## 14. Test strategy

### 14.1 Unit

- Calculator table: every state in §6.4, every row in §7, and the boundary
  cases in §8.
- `hold_until`: 0, 1, 30, 365; first_seen null; first_seen in the future;
  first_seen old.
- `eligible_at`: next check before/after hold; daily, twice daily, weekly,
  hourly, default; DST gap and overlap; site timezone change.
- `execution_at`: no queue, queue position 0/1/N, Flow waiting/done/locked/
  failed, lock, not managed.
- `display`: exact, earliest, waiting, managed, none, not observed, cron
  problem; site date format and fallback.
- Ledger peek: reads without writing; old target ignored; stale row ignored;
  future row ignored; bounded map.
- Core mapping: no offer, minor, major, major off, `WP_AUTO_UPDATE_CORE` false,
  security label removed.
- Translation mapping: details only; no native message.

### 14.2 Integration (real WordPress, local fixtures)

- Save the schedule: recurrence and time persist; `wp_version_check` is
  rescheduled; the next check matches the calculator.
- Hold 0: gate off; no ledger row; display exact.
- Hold 365: gate on; ledger row; display after 365 days.
- First automatic pass: ledger row created; display changes from not observed
  to held.
- Manual `POST /run-check`: transients refresh; no ledger row; display remains
  not observed.
- Hold expiry: the next pass allows the update; display eligible.
- Core offer: the gate holds it; the UI no longer claims a security exemption.
- Multisite: network option; main-site event; subsite does not show a false
  “not scheduled”.
- Native filters: plugin and network-theme HTML contain the item state; the
  toggle and hidden class survive.
- Single-site theme template: per-theme text is rendered; fallback is native.
- Update-core map: core/plugin/theme entries; missing checkbox fallback.
- Pro filter: lock/flow/queue facts override the Free defaults; no write.
- Logger: one delayed row per offer/target; no row from display.

### 14.3 JavaScript

- jsdom fixtures for:
  - plugin row `.auto-update-time`;
  - network theme row;
  - single-site theme overlay;
  - update-core core `<p>`;
  - update-core plugin row with ` | Automatic update ...`;
  - update-core theme row;
  - a row with no native message;
  - a row with another plugin’s notice;
  - a changed native pattern;
  - a hidden auto-update time;
  - a post-update emptied element.
- Assertions:
  - the target text is replaced;
  - the native text is preserved in the data attribute;
  - other rows are unchanged;
  - the hidden class is unchanged;
  - the pass is idempotent;
  - no `innerHTML` is used;
  - the fallback leaves the native text;
  - the theme overlay re-run works;
  - the strings are localized (no hard-coded English in the JS bundle).

### 14.4 E2E

- Plugin screen: a held item shows the earliest install; a locked item shows
  “Managed via”; a waiting item shows “Waiting for”.
- Theme screen: network and single-site.
- Update-core: core summary, plugin row, theme row.
- WP-Cron disabled: one-line hint; no big blue banner.
- Schedule: two selects, every hour/minute, save, reload, time preserved.
- Hold 0 and 365: save and reload.
- Multisite network admin and subsite.
- Screen reader: the message is read in place; the picker labels are visible.

### 14.5 Anti-tautology

A test must fail if:

- the native text is the next check while the hold is active;
- a locked, waiting, or queued item shows a countdown;
- the hold is computed from the release date or a manual check;
- the display calls the gate and writes `first_seen`;
- the override replaces another row or another plugin’s notice;
- the update-core core message is missed because the previous sibling changed;
- the picker still has four values;
- the hold range is 0–30 or 1–365 on either side;
- the core security claim returns;
- the big blue banner returns;
- the display runs a detector per row.

### 14.6 Commands and fixtures

- `bin/harness test` (unit).
- `bin/harness integration` (real WordPress).
- `bin/harness test:js` (Vitest/jsdom).
- `bin/harness e2e` (Playwright, when the environment is available).
- `WP_MULTISITE=1 bin/harness integration --filter Multisite`.
- Fixtures: local plugin/theme zips; `pre_set_site_transient_update_plugins`,
  `pre_set_site_transient_update_themes`, `pre_set_site_transient_update_core`;
  ledger option fixtures; cron event fixtures; Pro pass option fixtures; Flow
  chain fixtures; lock detector fixtures; a 200-item fixture.
- No network access. No `assertTrue( true )`. No skipped test without a reason.
  No assertion inside a `try/catch`.

---

## 15. Evidence

### 15.1 Commands run

```bash
cd wp-content/plugins/updatronix
bin/harness graft ask "unified schedule next run" --source
bin/harness graft ask "delay ledger first seen" --source
bin/harness graft grep "wp_get_auto_update_message"
bin/harness graft callers Updatronix_AutoUpdateDelay
bin/harness wp option get updatronix_network_schedule --format=json
bin/harness wp cron event list --fields=hook,next_run_relative,recurrence
bin/harness wp option get updatronix_auto_update_delay_ledger --format=json
bin/harness wp option get timezone_string
bin/harness wp option get date_format
bin/harness wp option get time_format
bin/harness wp eval 'echo is_multisite() ? "multisite" : "single"; echo " "; echo wp_date("c");'
```

Live probe results:

- Schedule: `{"update_check":{"recurrence":"daily","time":"01:00"},"delay_updates":{"enabled":true,"delay_value":2}}`.
- Timezone: `Europe/Paris`; date format `j F Y`; time format `G\hi`.
- Single site; current local time 2026-10-28 17:39.
- `wp_version_check`: daily, next run ~6h20m (2026-10-29 01:00 local).
- Ledger: one entry at `2026-10-28 13:01:05` local.
- Pro: license decryption failed, so the engine is not registered on this site.
- No plugin/theme offers currently; the core transient has one `latest` offer
  (no update). The ledger entry is therefore from an offer that is no longer
  live, which demonstrates the stale-row pruning gap.

### 15.2 Files read

Free:

- `AGENTS.md`, `workflow.md`.
- `docs/wpds/16-schedule-review.md`, `38-schedule-state-of-play.md`,
  `45-update-engine-architecture.md` §2.2/§5.4,
  `60-update-engine-rethink/analysis/01-version-lock.md`,
  `02-update-queue.md`, `03-update-flow.md`.
- `inc/classes/class-updatronix-cron.php`
- `inc/classes/class-updatronix-autoupdatedelay.php`
- `inc/classes/class-updatronix-autoupdates.php`
- `inc/classes/class-updatronix-settings.php`
- `inc/settings/options.php`
- `inc/admin/native-update-delay-notice.php`
- `inc/admin/enqueue.php`
- `assets/src/js/components/schedule/SchedulePanel.js`
- `assets/src/js/components/schedule/scheduleDraft.js`
- `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js`
- `assets/src/js/hooks/usePluginSettings.js`

Pro:

- `inc/update-engine/class-updatronix-delay-policy.php`
- `inc/update-engine/class-updatronix-policy-chain.php`
- `inc/update-engine/class-updatronix-shield-state.php`
- `inc/update-engine/class-updatronix-queue-scheduler.php`
- `inc/update-engine/class-updatronix-flow-planner.php`
- `inc/update-flow/class-updatronix-flow-runner.php`
- `inc/update-engine/class-updatronix-update-kernel.php`
- `inc/update-shield/class-updatronix-safe-mode.php` (native augment pattern)
- `assets/src/js/safe-mode-augment.js`
- `docs/wpds/60-update-engine-rethink/analysis/01-version-lock.md` §11

WordPress core:

- `wp-admin/includes/update.php`
- `wp-admin/includes/class-wp-plugins-list-table.php`
- `wp-admin/includes/class-wp-themes-list-table.php`
- `wp-admin/includes/class-wp-ms-themes-list-table.php`
- `wp-admin/update-core.php`
- `wp-admin/themes.php`
- `wp-admin/js/updates.js`
- `wp-admin/js/theme.js`
- `wp-includes/update.php`

### 15.3 What was not verified

- No live capture of a held plugin/theme offer on this site; there are no
  plugin/theme offers and the core offer is `latest`.
- No live Multisite network-admin or subsite capture; the Pro engine is not
  registered because the license decryption fails.
- No DST transition test and no site-timezone-change test.
- No screen-reader pass.
- No large-plugin-list performance measurement.
- No native DOM fixture from a real WordPress offer; the update-core selectors
  are derived from source reading.
- No test of the current queue freeze on a held offer; the behavior is proven
  by code reading (`blocked` row + ownership handshake + `job_key` dedupe).

### 15.4 Bottom line

The native “Automatic update scheduled in …” is the next check, not the
install. With the Schedule active, it is false on the plugin row, the theme row
or overlay, and all three update-core locations. A truthful replacement needs a
read-only calculator, a canonical hold range, a `not_observed` state, Flow and
queue facts, and a lock state. The picker is a small UI change; the native
override is the hard part because WordPress exposes no filter for the message
and no per-row filter on update-core.

The owner’s decisions settle the product: two selects for every hour and
minute, hold `0..365` with `0` = off, no core-security exemption claim, a
state-first display with the earliest execution, per-item rows plus an
update-core summary, no big blue banner, and no countdown for waiting, locked,
or queued items.

---

Analysis complete; do not implement. Ready for plan mode after the other tabs
finish.
