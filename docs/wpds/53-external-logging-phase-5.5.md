# External update logging — Phase 5.5

**Date:** 2026-10-27
**Feature:** R-03 — log manual updates performed through FTP or any external source
**Edition:** Free
**Status:** Implemented as a public logging contract. No credentials, no FTP
client, no new table, no new screen. No commit and no version bump.

## Contract

External deployment code calls the same logger the native updater uses:

```php
$result = Updatronix_External_Log::log(
	array(
		'source'         => 'ftp',
		'log_type'       => 'plugin',
		'item_name'      => 'WooCommerce',
		'item_slug'      => 'woocommerce/woocommerce.php',
		'version_before' => '9.0.0',
		'version_after'  => '9.1.0',
		'status'         => 'success',
		'message'        => 'Uploaded by the deployment script.',
		'event_key'      => 'deploy-woocommerce-9.1.0',
	)
);
```

- `item_name` is required.
- `source` defaults to `custom`; accepted values are `ftp`, `sftp`, `ssh`,
  `cli`, and `custom`, filterable through
  `updratronix_external_update_sources`.
- `log_type` is one of `core`, `plugin`, `theme`, or `translation`.
- `status` is one of the existing logger statuses; `success`, `error`, and
  `cancelled` are the expected deployment outcomes.
- `event_key` is optional and makes the call idempotent through the existing
  logger dedupe.
- The function returns the log id or a `WP_Error`.

The entry uses `performed_as=upload`, so the existing activity log renders the
“File upload” trigger. The source is prefixed in the message:
`External source: ftp.` This keeps the source searchable without a schema change.

## Why no FTP client

Updatronix does not need to hold FTP/SFTP/SSH credentials to log an external
deployment. The deployment script already has the access it needs; it calls the
logger after the files are in place. This avoids storing secrets, implementing a
second transport, and duplicating the host's deployment rules.

WordPress has no native FTP update API. The log table remains the single sink,
and the existing `updratronix_after_log` action still fires.

## Claims

| Claim | Backend | Verdict |
|---|---|---|
| An FTP update appears in the activity log | `Updatronix_External_Log::log()` → `Updatronix_Logger::log()` | True |
| The source is visible | Message prefix `External source: <source>.` | True |
| The trigger is distinguishable | `performed_as=upload` → “File upload” | True |
| Retrying a deployment does not duplicate the entry | `event_key` → logger dedupe | True |
| No credentials are stored | No credential option, no transport | True |
| No second history table | Existing `{prefix}updatronix_logs` | True |

## Tests

`tests/Integration/ExternalLogTest.php` covers:

1. FTP update with source prefix and `upload` trigger.
2. Unknown source rejected with `updratronix_external_log_source`.
3. Missing item name rejected with `updratronix_external_log_item`.
4. `event_key` idempotency.
5. Additive source filter.

Full Free integration: 84 tests, 230 assertions, 13 skips. WPCS clean on the new
class and test.

## Known gaps

- No live FTP/SFTP/SSH deployment script was run; the contract is exercised
  directly.
- The source is in the message, not a dedicated filterable column. A source
  filter is Phase 6 UX, not a schema change now.
- E2E has not run.
- Logging must be enabled and the log table present; the helper returns a
  `WP_Error` otherwise.
