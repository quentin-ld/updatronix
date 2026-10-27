# Update logs — state of play

**Date:** 2026-10-27
**Screen:** Update logs (`logs`, Free)
**Contract:** `04-screen-contracts.md` §1
**Status:** Audit complete for code and automated tests; the manual
keyboard/screen-reader/browser pass and a live export run remain for Phase 6.

## Contract

One activity feed grouped by day, a details preview on wide screens and a
dialog on narrow screens, a delete confirmation, and an export dialog. The
screen reads `updatronix/v1/logs`, writes deletes through the same namespace,
and links from Pro blocked-history rows with `?tab=logs&log=<id>`.

## Claims matrix

| Claim | Where | Code path | Runtime proof | Verdict |
|---|---|---|---|---|
| “Every core, plugin, theme, and translation update” | Page header | `{prefix}updatronix_logs` receives rows from the logger and the native update hooks. | Existing logger and integration coverage. | True |
| “Export logs” | Page header | `ExportLogsModal` → `POST /updatronix/v1/logs/export`. | The export endpoint returns a ready session; Free accepts `txt` only. | True |
| “Retention <n> days” | Page header badge | Reads `retention_days` from `updatronix_settings`. | Settings round-trip covered. | True |
| “Search by item or version” | Toolbar | `useLogs.fetchLogs()` sends the search term; the table also filters client-side. | Existing REST tests. | True |
| “View details” opens the matching entry | Row action | `LogDetailsContent` fetches `GET /logs/{id}` and falls back to the row. | `LogsRestApiTest` covers the enriched detail. | True |
| Pro blocked-history link opens the entry | `?tab=logs&log=<id>` | `parseDeepLinkLogId()` → `ActivityLogPanel` detail state → `fetchLogDetails()`. | 6 new JS cases plus the existing detail endpoint. | True |
| CSV/JSON/XML are not offered in Free | Export dialog | `filterExportFormats()` keeps `txt`; the server allowlist rejects `json` in Free. | 6 `exportFormats` cases and the Free export integration test. | True |
| Pro CSV, JSON, and XML are real downloads | Export dialog | CSV/XML are rendered client-side from the same NDJSON payload; XML uses `buildXmlReport()` and downloads `.xml`; JSON downloads `.json`. | 4 `exportReports` XML cases; format gate tests. | True |
| Direct changelog link opens the item’s changelog | Details | `changelog_url` from `item_slug`: plugin/theme `#changelog`, core release archive. | `LogsRestApiTest` covers plugin and core. | True |
| Delete asks for confirmation | Row action | `AlertDialog` with the entry name and consequence. | Existing interaction review. | True |

## Internal mechanics

| Layer | Path | Notes |
|---|---|---|
| Feed | `assets/src/js/components/activityLog/ActivityLogPanel.js` | DataViews activity feed, day grouping, preview aside, narrow dialog. |
| Data | `assets/src/js/hooks/useLogs.js` | List fetch, detail fetch with 30 s cache, delete, clear all. |
| Details | `assets/src/js/components/activityLog/LogDetailsContent.js` | Fetches the enriched detail; renders summary, readme changelog, process, redacted trace. |
| Export | `assets/src/js/components/activityLog/ExportLogsModal.js` | Scope, merge, columns, format, chunked export, download/copy. |
| Format gate | `assets/src/js/components/activityLog/exportFormats.js` | Keeps known formats; Free boots with `txt`. |
| Export reports | `assets/src/js/components/activityLog/exportReports.js` | Builds the XML document from parsed NDJSON rows; CSV stays in the modal. |
| Deep link | `assets/src/js/components/activityLog/logDeepLink.js` | Positive integer only; invalid values fall back to no detail. |
| REST | `inc/classes/class-updatronix-settings.php` | Logs list/detail/delete/cleanup/all; capability `manage_updatronix`. |
| Export REST | `inc/classes/class-updatronix-export.php` | Capability check, format allowlist, rate limit, transient chunks, cursor. |
| Storage | `{prefix}updatronix_logs` | One row per update event; no second history table. |

## Findings and fixes

| ID | Severity | Finding | Fix |
|---|---|---|---|
| UL-01 | MEDIUM | The Pro blocked-history deep link had no regression test; a query parser change could silently stop opening the entry. | Extracted `parseDeepLinkLogId()` and added 6 JS cases covering valid, missing, invalid, zero, negative, and empty input. |
| UL-02 | MEDIUM | The Free export dialog depended on the boot payload to hide CSV/JSON; an unknown or duplicated value could leak a format. | Extracted `filterExportFormats()`; it keeps known formats, removes duplicates, and falls back to `txt`. Added 6 JS cases. |
| UL-03 | INFO | The 2026-10-24 QA note said the Free panel did not read `?log=`. | The current panel reads it and fetches the detail; UL-01 adds the missing regression guard. |
| UL-04 | INFO | The security pass noted that log redaction fails open if `preg_replace()` returns null on a backtrack limit. | Open follow-up; no new exposure was found in this screen pass. |
| UL-05 | INFO | A live export against a large log table was not run in this pass. | Recorded for Phase 6; the chunked export suite remains the safety net. |
| UL-06 | MEDIUM | Selecting XML rendered the CSV report and downloaded a `.txt` file. | Added `buildXmlReport()` and the `xml` download branch; 4 JS cases cover structure, escaping, missing columns, and empty rows. |
| UL-07 | MEDIUM | The details card rendered the readme changelog but had no direct link to the full WordPress.org changelog. | Added `changelog_url` and an “Open changelog” / “Open release notes” link; integration covers plugin and core. |

No P0/P1 defect was found in the filters, row actions, details dialog, or
delete dialog. Existing reviews 14, 25, and 27 remain the visual record.

## Native vs custom

| Surface | Implementation | Native option | Decision |
|---|---|---|---|
| Feed | DataViews | `@wordpress/dataviews` | Native component |
| REST | `updatronix/v1/logs` | REST API + capability | Native |
| Storage | `{prefix}updatronix_logs` | `$wpdb` custom table | Justified: the log is a first-class product surface |
| Details | WPDS dialog / aside | WPDS components | Native |
| Export text | Server body builder | No native export format | Custom, because WordPress has no update-log export API |
| Clipboard/download | `navigator.clipboard` / Blob | Browser APIs | Native |

## Test inventory

| Suite | Coverage |
|---|---|
| JS | 67 tests across 9 files, including `tests/js/logDeepLink.test.js` (6), `tests/js/exportFormats.test.js` (6), and `tests/js/exportReports.test.js` (4) |
| Integration | `LogsRestApiTest` (79 tests total: list, detail, changelog URL, delete, cleanup, export, auth), `SettingsUninstallOptInTest` for the adjacent settings path |
| Unit | `ExportBodyBuilder*`, `CoreUpdateLogVersionsTest`, `ExportTransientManagerTest` |

## Feature development guide

1. **Data** — add a logger field or a typed row, not a second table. Update the
   export body builder and the details renderer together.
2. **REST** — add the route under `updatronix/v1/logs`, a capability callback,
   an args schema, and a bounded response.
3. **UI** — add the control to `ActivityLogPanel` or the details component; use
   WPDS `DataViews`, `Dialog`, `AlertDialog`, `Notice`, and `Text`.
4. **Export** — add the format to `KNOWN_EXPORT_FORMATS` and the server
   allowlist filter; keep Free at `txt`.
5. **Deep link** — parse ids through `parseDeepLinkLogId()`; never trust the raw
   query value.
6. **Security** — capability, nonce through `apiFetch`, redaction before
   storage, no raw user HTML in the details.
7. **Tests** — unit for builders and parsers, integration for REST/storage,
   JS for format/deep-link behavior, E2E for the full export when available.
8. **Docs** — update this file, the screen contract, and the implementation log.

## Known gaps

- E2E remains blocked by the host's missing `libnspr4.so`.
- No live large-table export run in this pass.
- No manual screen-reader pass on the details dialog or export dialog.
- UL-04 redaction fail-open remains an open security follow-up.
- Pro CSV, JSON, and XML are implemented from the same NDJSON payload; Free stays on plain text. A live Pro export run remains Phase 6.

## Exit

- Claims: all true or explicitly accepted.
- P0/P1: none found; UL-01/02 covered, UL-03 verified, UL-04/05 recorded.
- Tests: JS 67, integration 79, unit 216, build pass.
- Review/security: screen diff reviewed; the redaction info item remains open.
- No version bump.
