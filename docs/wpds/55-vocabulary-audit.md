# Vocabulary audit — Phase 6

**Date:** 2026-10-27
**Scope:** Free and Pro user-facing strings, docs, and status labels.
**Status:** Audit only. No string was changed, no `.pot` was regenerated, and no
version was bumped. The 5.1 Shield strings and docs 51/34/11/45 are still owned
by another active session.

## House style

The fleet uses US English and the WordPress Documentation Style Guide:

- Sentence case for labels, headings, and descriptions.
- Second person, present tense, active voice.
- Straight apostrophes and quotes; em dash for a break, en dash for a range.
- Contractions are fine; exclamation points are not.
- No jargon when a common term exists.
- One product-area name per concept.

## Glossary candidates

| Concept | Preferred | Avoid / current variants |
|---|---|---|
| Automatic updates | “automatic updates” in prose; “Auto-updates” as a label | `Auto-update`, `Auto updates`, `Auto-updates` used interchangeably |
| Cancellation | “Canceled” (US) | Free uses `Cancelled`; Pro uses `Canceled` |
| License | “license” (noun), “licensed” (adjective) | `licence` |
| Changelog | “changelog” (one word) | `change log`, `readme.txt` in prose |
| Version control | “version-controlled” (adjective) | `VCS`, `git/svn` in user copy |
| Re-enable | “re-enable” (verb) | `reenable`, `re enable` |
| Update queue | “update queue” | `queue`, `worker`, `claim` in user copy |
| Update Shield | “Update Shield” | `Shield` alone when the area is ambiguous |
| Update Flow | “Update Flow” | `Flow` alone when the area is ambiguous |
| Updatronix 3000 | “Updatronix 3000” | `Pro` in user-facing copy |
| Health check | “Health check” / “Health checks” | `diagnostics` when the Health screen is meant |
| Readme | “readme” in prose; `readme.txt` only as a file name | `Readme.txt` as a label |

## Candidate batches

### Batch A — low-risk consistency

No meaning changes; safe to apply after the write claims clear.

1. Standardize cancellation to “Canceled” in Free status labels.
2. Standardize the automatic-update forms: “automatic updates” in prose,
   “Auto-updates” in labels.
3. Use “license” and “licensed”; remove `licence`.
4. Use “changelog” as one word; keep `readme.txt` only as a file name.
5. Use “Update logs”, “Update Shield”, “Update Flow”, and “Updatronix 3000”
   consistently.
6. Use “Health check(s)” for the Health screen; reserve “diagnostics” for the
   data payload.

### Batch B — copy edit

Needs the `writing` and `humanizer` passes; larger diff and review.

- Empty states and error messages that still read like implementation notes
  (`Cron is shared`, `Debug summary`, `Composer lock` without context).
- Long helper paragraphs that repeat the label instead of adding information.
- Developer terms (`VCS`, `worker`, `claim`, `lease`) in user-facing copy.
- Inconsistent sentence case in Pro cards and statuses.

### Batch C — deferred

- Strings and docs owned by the active 5.1 session: `UpdateShieldPage.js`,
  `shieldState.js`, docs 51/34/11/45. Do not edit until the claim clears.
- README and WordPress.org copy; these are release copy, not plugin UI.

## Hard rule

Any change inside `__()`, `_e()`, `_n()`, `_x()`, `esc_html__()`, or
`esc_attr__()` must be approved first, listed in the changelog, and followed by
a `.pot` regeneration. The audit does not authorize a rewrite by itself.

## Recommendation

Start with Batch A after the 5.1 claim clears. It removes the visible
inconsistencies without changing meaning or layout. Batch B needs a separate
copy review with the `writing`/`humanizer` skills and a fresh diff.
