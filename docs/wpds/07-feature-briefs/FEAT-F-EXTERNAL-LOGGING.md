# FEAT-F-EXTERNAL-LOGGING — FTP and external update logging

**Edition:** Free
**Status:** Implemented as a public logging contract in Phase 5.5
**Screen:** Update logs (existing; no new screen)

## Problem

Updates installed by FTP, SFTP, SSH, CLI, or a deployment script bypass
WordPress's updater and therefore never reach the activity log. The owner
cannot tell whether an external deployment happened, what version changed, or
when.

## Contract

External deployment code calls:

```php
Updatronix_External_Log::log( array $args ): int|\WP_Error
```

Required:

- `item_name` — non-empty display name.

Optional:

- `source` — `custom` by default; built-ins are `ftp`, `sftp`, `ssh`, `cli`,
  and `custom`. Additive filter: `updratronix_external_update_sources`.
- `log_type` — `plugin` by default; also `core`, `theme`, `translation`.
- `item_slug`, `version_before`, `version_after`.
- `status` — existing logger statuses; `success`, `error`, `cancelled` are the
  expected deployment outcomes.
- `message` — free text; the helper prefixes `External source: <source>.`.
- `event_key` — optional idempotency key.

Returns the log id or `WP_Error` (`updratronix_external_log_source`,
`updratronix_external_log_item`, `updratronix_external_log_failed`).

## Security and ownership

- No FTP/SFTP/SSH client, no credential option, no secret storage.
- No REST route; the caller already has PHP execution on the site.
- The existing logger sanitizes, redacts, and validates every field.
- The existing log table is the only sink; no second table or schema change.
- `performed_as=upload` keeps the existing “File upload” trigger honest.
- `event_key` uses the existing logger dedupe.

## UI

The entry appears in the existing activity feed and details dialog. The source
is part of the message, so it is searchable. A dedicated source filter/column is
out of scope; it would be a later UX change, not a reason to add a column now.

## Acceptance criteria

1. An FTP-sourced plugin update produces a log row with `performed_as=upload`,
   the item slug, versions, and `External source: ftp.` in the message.
2. An unknown source returns `updratronix_external_log_source`.
3. A missing item name returns `updratronix_external_log_item`.
4. Repeating the same `event_key` returns the original log id.
5. The source filter is additive.
6. Logging disabled or table missing returns `updratronix_external_log_failed`.

## Out of scope

- Performing the FTP/SFTP/SSH transfer.
- Storing credentials.
- Detecting a file change automatically.
- A new external-source screen, column, or filter.
- E2E against a real FTP server.

## Tests

`tests/Integration/ExternalLogTest.php` covers criteria 1–5. Criterion 6 is
covered by the logger's existing guard and the helper's error path.
