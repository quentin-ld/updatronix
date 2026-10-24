# FEAT-F-SUPPORT · Support

| | |
|---|---|
| Edition | Free |
| Status | Design only. No backend. |
| Rail | Help · `support` |
| Design | [`../reference/screens/src/screens/Support.jsx`](../reference/screens/src/screens/Support.jsx) |
| Plan task | `F4-03` |

## Problem

Support is currently a header link to the WordPress.org forum. The guide gives
Help group a Support destination that helps the user post a useful report.

## Outcome

One page with four pre-post checks, a primary link to the support forum, and a
cross-link to Documentation.

## Target behavior

1. The page header is `Support`, with the description “What to check before you
   post, and where to ask the WordPress community.”
2. Four checks:
   - **Update first.** Run the latest WordPress and Updatronix updates before troubleshooting.
   - **Bring the evidence.** Include the item, versions, error code, and time from the update log.
   - **Reproduce safely.** Try a default theme with other plugins disabled, on staging if possible.
   - **Search the forum.** An existing thread may already cover the same error or plugin.
3. Primary action: `Open support forum` → `https://wordpress.org/support/plugin/updatronix/`.
4. Secondary link: `Read the documentation` → the Documentation destination.
5. The page does not collect, send, or prefill site data.

## Data

Static constants. No REST call, no option, no transient.

## Security and privacy

- The forum URL is a constant and uses HTTPS.
- The Documentation link uses the shell's `navigate( 'documentation' )`, not a
  hardcoded admin URL.
- No diagnostic data is sent anywhere. “Bring the evidence” tells the user what
  to copy; it does not copy it for them.
- No telemetry.

## Internationalization and accessibility

- Text domain `updatronix`.
- The four checks are an ordered list; the numbers are announced.
- The forum link opens in a new tab with an accessible hint and
  `rel="noopener noreferrer"`.
- Buttons and links have visible focus.

## Tests

- A render test asserts four checks, one primary link, and one secondary link.
- A link check confirms the forum URL.
- A test asserts no REST request is made on mount.
- A navigation test asserts the Documentation link switches destination.

## Acceptance

- The four checks match the guide's copy.
- The forum URL matches the readme.
- The cross-link stays inside the shell.
- The page works with no JavaScript data dependency.
- No site data leaves the page.

## Out of scope

- A contact form.
- A ticket system.
- Automatic diagnostic upload.
- A knowledge base.
