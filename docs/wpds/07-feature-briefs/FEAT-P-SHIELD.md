# FEAT-P-SHIELD · Update Shield

| | |
|---|---|
| Edition | Pro |
| Status | Backend partial. Existing settings and safe-mode filters; the target fields and the blocked table are new. |
| Rail | Automations · `update-shield` |
| Design | [`../reference/screens/src/screens/UpdateShield.jsx`](../reference/screens/src/screens/UpdateShield.jsx) |
| Plan tasks | `P3-01` to `P3-04` |
| Decisions | `A-06`, `D-14` |

## Problem

Update Shield exists as a set of filters and four settings inside the
Updatronix 3000 tab. The design gives it a dedicated destination, a protection
model with a version control lock, an automatic-update queue, email alerts, an
abandoned-plugin flag, and a blocked-history table. The current backend covers
only the WordPress and PHP compatibility checks.

## Outcome

A dedicated Update Shield page with one manual save, the target protection
fields, a read-only blocked table, and a license gate enforced on the server.

## Existing backend contract

Preserve these option keys and their defaults:

| Key | Current meaning |
|---|---|
| `safe_mode` | Master switch for the Shield behavior. |
| `notify_on_block` | Email notification on a block. |
| `control_php_version` | Check the PHP compatibility data. |
| `control_wp_version` | Check the WordPress compatibility data. |

The option is `updatronix_pro_shield_settings`, network-scoped. The safe-mode
filters run late on `auto_update_plugin`, `auto_update_theme`, and
`auto_update_core`. The existing REST routes are `/shield/settings` and
`/shield/recheck`.

## Target fields

Add these keys without breaking the existing four:

| Key | Type | Default | Maps to |
|---|---|---|---|
| `incompatible_behavior` | `disable` \| `cancel` | `disable` | The radio group “When an update is incompatible”. |
| `re_enable` | bool | true | Re-enable auto-updates automatically. Only valid with `disable`. |
| `notify_email` | bool | true | Email the technical manager. Uses the Free notification recipients. |
| `queue_enabled` | bool | true | Queue automatic updates. See `FEAT-P-QUEUE`. |
| `flag_abandoned` | bool | true | Flag items with no release for 12 months. |
| `version_control_lock` | bool | true | Block updates for version-controlled items. |

The design's “Protect this site” switch maps to `safe_mode`. Do not rename it.

## Version control lock

This is a new detector. Requirements:

- Detect a plugin or theme tracked by Git or Composer. Candidate signals:
  a `.git` directory in the item root, a `composer.json` plus
  `vendor/composer/installed.json`, or a documented filter.
- Cache the detection per item and invalidate on plugin/theme changes.
- Never scan the whole filesystem on every request.
- Provide `updatronix_pro_is_version_controlled` as the documented extension point.
- When the lock is on, block the update and record the reason.
- When the lock is off, allow the update and show the item as unlocked.

The detector must fail open with a logged reason if it cannot determine the
answer; a false lock is worse than a missed lock.

## Blocked history

The table shows item, type, versions, reason, blocked date, and a link to the
log. Last 30 days.

Preferred source: the existing Free log table. Record a block through the
existing logger with a distinct status and a reason. If the logger cannot carry
the status, add a dedicated Pro table; do not create a second source of truth
for the same event.

Add `GET /shield/blocked` with a capability check and a bounded query.

## Execution rules

1. Run before an automatic update installs.
2. Check the enabled controls only.
3. On an incompatible item:
   - `disable`: cancel the update and turn off auto-updates for that item.
   - `cancel`: cancel this update only.
4. Record the decision and the reason.
5. Email the manager when `notify_email` is on and the block is new.
6. Re-enable auto-updates only when `re_enable` is on and a later release passes.
7. Never block a manual update silently; the UI must explain the block.

## UI composition

- `PageHeader` with `Save changes`.
- Protection card: `SwitchControl`, version control lock panel with a `Badge`,
  `RadioGroupControl`, and three `SwitchControl` rows.
- Update queue card: `SwitchControl`.
- Recently blocked card: table with item, version, reason, date; link to the log.
- `Note` on data provenance: WordPress.org compatibility data; unknown data is
  allowed but flagged.
- License-inactive state: the destination is visible but the panel is replaced
  by a license gate with a link to Updatronix 3000.

## States

Loading; license inactive; protection off; recheck running; recheck error;
no blocked items; save error; save success; multisite non-Super-Admin.

## Security

- Capability `UPDATRONIX_PRO_CAP_MANAGE`; Super Admin on multisite.
- License validity is checked on the server for every Shield action.
- Sanitize every field; reject an unknown behavior value.
- Do not trust an item slug from the client for blocked-history queries.
- The version-control detector must not follow symlinks outside the item root.
- Email only to the configured recipients; never to a client-supplied address.

## Internationalization and accessibility

- Text domain `updatronix-pro`.
- Each switch has a visible label and a description.
- The lock panel's status is text (`Locked` / `Off`), not color alone.
- The radio group has a fieldset and a legend.
- The table has a caption and column headers.
- Recheck and save announce their result.

## Tests

- Unit: defaults merge, partial updates, unknown keys, invalid behavior.
- Unit: existing option values survive the schema extension.
- Integration: the safe-mode filters honor each control; a block is logged.
- Integration: the version-control detector handles a Git item, a Composer item,
  an untracked item, and an unreadable path.
- Integration: `/shield/blocked` filters by date and capability.
- E2E: save, recheck, blocked table, license gate.
- Security: no block without a license; no email to an arbitrary address.

## Acceptance

- Existing settings survive an upgrade.
- One save commits every field atomically.
- The blocked table is read-only and linked.
- The version control lock fails open with a logged reason.
- The license gate is enforced on the server.
- No critical accessibility finding.

## Dependencies and risks

- Depends on `FEAT-P-LICENSE` for the license state.
- `R-08`: version-control detection is environment-specific. Mitigation: a
  documented filter, caching, and fail-open behavior.
- `R-09`: disabling auto-updates on a block can surprise users. Mitigation: the
  behavior is explicit, reversible, and recorded.

## Out of scope

- Queue processing. See `FEAT-P-QUEUE`.
- Remote compatibility data beyond WordPress.org.
- Manual update blocking.
