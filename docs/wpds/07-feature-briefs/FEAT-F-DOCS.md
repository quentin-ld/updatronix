# FEAT-F-DOCS · Documentation

| | |
|---|---|
| Edition | Free |
| Status | Design only. No backend. |
| Rail | Help · `documentation` |
| Design | [`../reference/screens/src/screens/Documentation.jsx`](../reference/screens/src/screens/Documentation.jsx) |
| Plan task | `F4-02` |

## Problem

Help links currently live in the custom header. The guide moves them into the
Help group in the rail, but there is no Documentation page that explains the
product and the WordPress update system.

## Outcome

One page with three linked guide cards. No REST call, no stored data, no remote
asset.

## Target behavior

1. The page header is `Documentation`, with the description “Official guides for
   Updatronix, Updatronix 3000, and the WordPress update system.” and the meta
   badge `3 guides`.
2. Three cards:
   - **Plugin guide** · `Updatronix documentation` → `https://holdmywp.com/en/documents/updatronix-documentation/`
   - **Pro guide** · `Updatronix 3000 documentation` → `https://holdmywp.com/en/documents/updatronix-3000-documentation/`
   - **Developer reference** · `WordPress update system` → `https://holdmywp.com/en/documents/wordpress-update-system-developer-reference/`
3. Each card has an icon, a kicker, a title, a one-sentence description, and an
   external-link affordance.
4. The Pro card carries a `Pro` badge.
5. Links open in a new tab with `rel="noopener noreferrer"` and an accessible
   “opens in a new tab” hint.

## Data

A static array in the page module:

```js
const GUIDES = [
  { kicker, title, description, href, icon, pro? },
];
```

URLs are constants and are not translated. Titles and descriptions are
translatable.

## Security and privacy

- `esc_url()` for every href; reject a non-HTTPS URL at build time.
- No `target="_blank"` without `rel="noopener noreferrer"`.
- No remote request, iframe, or embedded asset.
- The page works with `DISALLOW_FILE_MODS` and in a restricted network.

## Internationalization and accessibility

- Text domain `updatronix`.
- Each card is a link with an accessible name; the icon is decorative.
- The external-link hint is available to a screen reader, not only as an icon.
- Focus order follows the visual order.

## Tests

- A unit test asserts the three URLs are present and HTTPS.
- A link check in CI confirms each URL resolves.
- A render test asserts three cards and one Pro badge.
- No network request other than the page's own assets.

## Acceptance

- Three cards render with the exact URLs.
- The Pro badge appears only on the Pro guide.
- Every link is keyboard reachable and announces that it opens a new tab.
- The page is translatable and the URLs are not.
- The page has no loading or error state because it has no data dependency.

## Out of scope

- Inline documentation content.
- Search.
- Versioned docs.
- A documentation editor.
