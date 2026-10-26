# Support — state of play

**Date:** 2026-10-26
**Screen:** Support (`support`, Free)
**Contract:** `04-screen-contracts.md` §11
**Status:** Audit complete for code and automated tests; the manual
keyboard/screen-reader/browser and link-reachability passes remain for Phase 6.

## Contract

Four “Before you post” steps, a WordPress.org forum card, a visible primary CTA,
an internal Documentation link, and a privacy note.

## Claims matrix

| Claim | Where | Code path | Runtime proof | Verdict |
|---|---|---|---|---|
| Four preparation steps | Before you post | `STEPS` has four entries and an ordered list. | Code path; review 20. | True |
| Support is handled on WordPress.org | Forum card | `SUPPORT_FORUM_URL` points at `/support/plugin/updatronix/`. | Static-link test asserts HTTPS, host, and path. | True |
| The primary CTA is visible | Forum card | `.updatronix-support-forum-cta` uses the inverse foreground on the blue background. | Review 20 SUP-001 fixed. | True |
| The forum link opens in a new tab | Forum card | `target="_blank"` + `rel="noopener noreferrer"`. | Code path; review 20. | True |
| The documentation link stays in the shell | Forum actions | `getTabUrl('documentation')` + `onNavigate`. | Shell routing tests. | True |
| The privacy note warns about secrets | Card footer | Static `Text`. | Code path. | True |
| Two columns above 900px | Layout | `@media (max-width: 900px)` stacks the layout. | Review 20 SUP-003 fixed. | True |

## Internal mechanics

| Layer | Path | Notes |
|---|---|---|
| Screen | `assets/src/js/pages/SupportPage.js` | Four steps, forum card, privacy note. |
| Links | `assets/src/js/pages/staticLinks.js` | `SUPPORT_FORUM_URL`. |
| Navigation | `shell/routes.js` | Internal Documentation link. |
| Data | None | No REST, option, or transient. |

## Findings and fixes

| ID | Severity | Finding | Fix |
|---|---|---|---|
| SUP-01 | HIGH | The forum CTA text was invisible because the link color matched the blue background. | Fixed in review 20; the inverse foreground is scoped to the CTA. |
| SUP-02 | MEDIUM | The forum icon was neutral instead of brand. | Fixed in review 20; brand foreground/background tokens. |
| SUP-03 | MEDIUM | The layout stacked at 960 instead of 900. | Fixed in review 20; the two-column layout holds through 960. |
| SUP-04 | MEDIUM | Step-number typography and mobile circle size drifted. | Fixed in review 20; 12px/12px and a 24px circle at ≤560. |
| SUP-05 | LOW | The static forum URL had no test. | Moved to `staticLinks.js` and added a static-link case. |
| SUP-06 | INFO | Card-height/wrapping delta from the admin frame. | Accepted deviation. |

No P0/P1 functional defect was found in this pass. The screen has no REST or
user input.

## Native vs custom

| Surface | Implementation | Native option | Decision |
|---|---|---|---|
| Steps | Ordered list + WPDS `Text` | Native HTML | Native |
| Forum CTA | WPDS `ButtonLink` | Browser link | Native |
| Card | WPDS `Card` | WPDS | Native |
| Navigation | WPDS `Link` + shell router | No native router | Custom shell contract |

## Test inventory

| Suite | Coverage |
|---|---|
| JS | `tests/js/staticLinks.test.js` (3), plus the shared 60 |
| Integration | Shell routes; no screen-specific REST |
| Unit | None specific |

## Feature development guide

1. **Content** — add a step or note; keep the ordered-list semantics.
2. **External link** — add the URL to `staticLinks.js`; keep HTTPS and the
   new-tab rel attributes.
3. **Internal link** — use `getTabUrl()` and `onNavigate()`.
4. **Accessibility** — keep the heading hierarchy, visible CTA text, and the
   privacy note.
5. **Tests** — extend the static-link test; add E2E when available.
6. **Docs** — update this file, the contract, and the implementation log.

## Known gaps

- E2E remains blocked by `libnspr4.so`.
- No manual screen-reader pass on the step list, CTA, or privacy note.
- Link reachability was not network-tested.
- No visual regression test.

## Exit

- Claims: all true or explicitly accepted.
- P0/P1: SUP-01–04 fixed; SUP-05 covered; SUP-06 accepted.
- Tests: JS 63, build pass, lint pass.
- Review/security: static page; no new surface.
- No version bump.
