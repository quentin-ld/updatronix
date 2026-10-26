# Documentation — state of play

**Date:** 2026-10-26
**Screen:** Documentation (`documentation`, Free)
**Contract:** `04-screen-contracts.md` §10
**Status:** Audit complete for code and automated tests; the manual
keyboard/screen-reader/browser and link-reachability passes remain for Phase 6.

## Contract

Three static guide cards: the Updatronix plugin guide, the Updatronix 3000 guide
with a Pro badge, and the WordPress update-system developer reference. Every
card opens on holdmywp.com in a new tab. A closing note links to Support.

## Claims matrix

| Claim | Where | Code path | Runtime proof | Verdict |
|---|---|---|---|---|
| Three official guides | Card grid | `GUIDES` has plugin, Pro, and developer entries. | 3 static-link cases. | True |
| The Pro guide carries a Pro badge | Pro card | `guide.pro` renders a WPDS `Badge`. | Code path; Pro-card review. | True |
| All guides open in a new tab | Card link | `target="_blank"` + `rel="noopener noreferrer"`; the aria-label says “opens in a new tab”. | Static-link tests + review 19/32. | True |
| Links use HTTPS on the documentation host | Card hrefs | `DOCUMENTATION_URLS` is the single source. | 3 static-link cases assert HTTPS, host, uniqueness. | True |
| The closing note links to Support | Closing note | `getTabUrl('support')` + `onNavigate('support')`. | Shell routing tests. | True |
| No REST or remote asset is needed | Screen | Static page, no `apiFetch`. | Code path. | True |

## Internal mechanics

| Layer | Path | Notes |
|---|---|---|
| Screen | `assets/src/js/pages/DocumentationPage.js` | Three cards, WPDS `Card`, `Icon`, `Text`, `Badge`. |
| Links | `assets/src/js/pages/staticLinks.js` | `DOCUMENTATION_URLS` and the Support forum URL. |
| Navigation | `shell/routes.js` | Internal Support link uses the shell URL builder. |
| Data | None | No REST, option, or transient. |

## Findings and fixes

| ID | Severity | Finding | Fix |
|---|---|---|---|
| DOC-01 | MEDIUM | The Pro guide had a Pro icon treatment but no `Pro` badge, while `FEAT-F-DOCS` says the Pro card carries one. | Added a WPDS `Badge` on the Pro card. |
| DOC-02 | MEDIUM | The three guide URLs were embedded in the page and had no test; a downgrade or duplicate would ship unnoticed. | Moved them to `staticLinks.js` and added 3 static-link cases. |
| DOC-03 | INFO | The prior typography/kicker/read-line/grid findings were fixed in review 19. | Recorded as fixed; no new visual defect. |
| DOC-04 | INFO | The “3 guides” header badge is intentionally absent. | Accepted owner decision. |

No P0/P1 functional defect was found. The page has no REST or user input.

## Native vs custom

| Surface | Implementation | Native option | Decision |
|---|---|---|---|
| External links | Native `<a>` / WPDS `ButtonLink` | Browser | Native |
| Cards | WPDS `Card` | WPDS | Native |
| Navigation | WPDS `Link` + shell router | No native router | Custom shell contract |
| Data | Static | None | Native |

## Test inventory

| Suite | Coverage |
|---|---|
| JS | `tests/js/staticLinks.test.js` (3), plus the shared 60 |
| Integration | Shell routes; no screen-specific REST |
| Unit | None specific |

## Feature development guide

1. **Content** — add the guide to `GUIDES` and its URL to `staticLinks.js`.
2. **Pro state** — set `pro: true` to get the badge and icon treatment.
3. **Accessibility** — keep the card link label, new-tab notice, and heading
   semantics.
4. **Navigation** — use `getTabUrl()` for internal links and `onNavigate()`.
5. **Tests** — add the URL to the static-link test; add E2E when available.
6. **Docs** — update this file, the contract, and the implementation log.

## Known gaps

- E2E remains blocked by `libnspr4.so`.
- No manual screen-reader pass on the card links or the closing note.
- Link reachability was not network-tested in this pass.
- No visual regression test.

## Exit

- Claims: all true or explicitly accepted.
- P0/P1: DOC-01/02 fixed; DOC-03/04 recorded.
- Tests: JS 63, build pass, lint pass.
- Review/security: static page; no new surface.
- No version bump.
