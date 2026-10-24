# FEAT-P-EXPORTS · Advanced exports

| | |
|---|---|
| Edition | Pro |
| Status | Deferred under `D-14`. Lives in the Update logs export dialog; CSV/XML/JSON formats and limits are not in this release. |
| Rail | Inside Update logs, in the export dialog |
| Design | Export dialog in [`../reference/screens/src/screens/UpdateLog.jsx`](../reference/screens/src/screens/UpdateLog.jsx) |
| Plan task | `P6-02` (gate); implementation after approval |
| Decision | `D-14` |

## Problem

The Free export is a human-readable report. Agencies need CSV for spreadsheets,
JSON for pipelines, and XML for existing reporting tools. The Pro roadmap
promises all three.

## Outcome

The export dialog offers a format, columns, filters, and a preview. Free keeps
its current report. Pro adds CSV, XML, and JSON with stable schemas and safe
encoding.

## Target behavior

1. The export dialog keeps the current report option for Free.
2. Pro adds a format selector: report, CSV, XML, JSON.
3. The column selection applies to every format.
4. The current filters and date range apply to every format.
5. The preview shows the first rows in the selected format.
6. The download uses the correct content type and a predictable filename.
7. Large exports stream in chunks and do not exhaust memory.
8. A Pro badge marks the Pro formats; a Free user sees the upgrade path.

## Format contracts

### CSV

- RFC 4180, comma separated, double-quote escaping.
- Header row with stable column keys.
- UTF-8. A BOM is optional; if included, document it. Do not include a BOM in
  the JSON or XML export.
- Line ending: `\r\n` for spreadsheet compatibility.
- **Formula injection:** prefix a cell that starts with `=`, `+`, `-`, `@`, tab,
  or carriage return with a single quote, and document the behavior.
- Dates: ISO 8601 in UTC, with a separate site-timezone column if needed.

### JSON

- UTF-8, no BOM.
- Top-level object with `schema_version`, `generated_at`, `site`, `filters`, and
  `entries`.
- Dates: ISO 8601 with an explicit offset.
- No HTML in values; no pretty-print requirement, but a stable key order.

### XML

- UTF-8, no BOM.
- Root `<updatronix-log schema-version="1">`.
- One `<entry>` per row, with child elements for the columns.
- Escape every value; never emit raw HTML.
- No DTD, no external entity, no processing instruction from user data.
- Dates: ISO 8601 with an offset.

## Data and plumbing

- Reuse `/logs/export`, the cursor, the chunked body builder, the rate limiter,
  and the audit path. Do not build a second export engine.
- Add a `format` parameter with an allowlist. Free rejects Pro formats; Pro
  accepts all of them.
- Reuse the column contract from the existing export so the report and the
  machine formats agree.
- Enforce a maximum row count or a maximum byte size per request, with a clear
  error. Chunking is the preferred path for large logs.

## UI

- The existing export dialog gains a format control.
- Column selection stays.
- The preview renders the selected format's first rows.
- The primary action changes from `Download` to `Download <format>`.
- Pro formats carry a Pro badge when Pro is inactive.

## States

No rows; format unsupported without Pro; building; preview ready; download
started; size limit reached; export error; rate limited.

## Security

- Capability `UPDATRONIX_CAP_MANAGE` (Free) and the Pro license gate for the Pro
  formats.
- Nonce on the request.
- The filename is generated on the server; never accept a filename or path from
  the client.
- Correct `Content-Type` and `Content-Disposition`; no inline rendering.
- CSV formula-injection protection.
- XML escaping; no user-controlled DTD or entity.
- JSON encoding with `wp_json_encode`; do not hand-build JSON.
- The export includes only data the user can read.
- Rate limit and audit the export.

## Testing

- Golden-file tests for CSV, JSON, and XML with a fixed data set.
- A formula-injection test for each risky prefix.
- An XML escaping test with `<`, `&`, quotes, and a CDATA-like string.
- A JSON schema test.
- A large-export test that proves chunking and memory bounds.
- A permission test: a subscriber cannot export; a Free user cannot select a Pro
  format; a Pro user can.
- A filename test: no path traversal, no user input.

## Acceptance

- The three formats match their contracts.
- The preview matches the downloaded content for the first rows.
- CSV formula injection is neutralized.
- XML and JSON are well formed for hostile input.
- Large exports do not exhaust memory.
- Free behavior is unchanged.
- The Pro gate is enforced on the server.

## Dependencies and risks

- Depends on the Free log data and export engine.
- `R-16`: export size can exhaust memory or time out. Mitigation: chunking, a
  hard limit, and a clear message.
- `R-17`: spreadsheet formula injection. Mitigation: prefixing and a documented
  rule.
- `R-18`: format drift between the report and the machine formats. Mitigation:
  one column contract, golden tests.

## Out of scope

- Scheduled exports.
- Remote destinations such as S3 or email.
- A custom column builder.
- PDF.
