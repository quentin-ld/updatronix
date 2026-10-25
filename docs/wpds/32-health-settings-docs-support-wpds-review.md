# Health, Settings, Documentation, Support WPDS MCP review

**Date:** 2026-10-25
**Scope:** Free Health, Settings, Documentation, and Support screens.
**Basis:** Current live screens at 1280×900; reference/fixture measurements from `17-settings-review.md`, `18-health-review.md`, `19-documentation-review.md`, and `20-support-review.md`.
**Status:** Review only. No UI source changed.

## Health

Current live state: 2 open issues, 5 checks passing, checked `25 October 2026 18h31`; WordPress 7.1.3, PHP 8.4.24, disk 1.3 TB, WP-Cron, update lock clear, and automatic retries attention. Open issue rows expose View details and Retry; the summary, checks, open issues, and Recently resolved sections are in place.

Conforms: PageHeader actions (`View diagnostics`, `Run check`), summary counts, check cards, issue priority/actions, 30-day copy, and the resolved empty state. Accepted owner decisions: diagnostics dialog with Copy details, snackbar-based Run check, absolute site date/time, and retained large dialog.

## Settings

Current live state is notifications-off, so the recipient, checkboxes, and clear-all button are disabled; the owner decision remains. Enabled-state behavior was verified in the prior review: disabling the master switch enables the field and exposes Remove all recipients. Layout, Save action, cards, note, and Data section conform.

## Documentation

Current page renders the three guide cards (Plugin Guide, Pro Guide, Developer Reference), each with its icon, title, description, and Read guide arrow; the dashboard external pseudo-icon is suppressed, leaving one native new-tab indicator. The “3 guides” badge is absent. The closing note and Visit Support link are present.

## Support

Current page renders the four “Before you post” steps, WordPress.org forum section, and CTA with one new-tab indicator. The step numbers, grid breakpoint, CTA color, and support link behavior are the post-fix state. No duplicate icon is present.

## Cross-screen

- PageHeader/Page actions are consistent; Health uses View diagnostics + Run check, Settings uses Save, Documentation/Support have no save.
- Admin frame remains the accepted structural deviation.
- WPDS MCP coverage: `Button`, `Dialog`, `IconButton`, `DataViews`, and `ButtonLink` are not fully covered; `SwitchControl`, `CheckboxControl`, `InputControl`, `Fieldset`, `Card`, `EmptyState`, and `Link` match the local `@wordpress/ui` docs. Local types remain authoritative.
- No new visual defect was found in this verification pass.

## Grilling QA — gaps

- Current Health uses live fixtures (2 open issues, 5 passing); not re-run: no-issue state, retry success/error, diagnostics copy, and post-retry resolved state.
- Not re-run: Settings notifications-on save/error, 390/760 mobile layouts for all four screens, keyboard/screen-reader reading order, and RTL.
- E2E remains blocked by the host’s missing `libnspr4.so`; mutation reported no changed lines in scope.
- No version bump.

## Files

Review taskfile: `.agents/tasks/2026-10-25-health-settings-docs-support-wpds-review.md`.
