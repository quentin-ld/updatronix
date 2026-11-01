# UI copy second pass — WordPress Documentation Style Guide

**Date:** 2026-11-01
**Scope:** Every reader-visible string in Free and Pro, re-opened after the first
pass: the eleven Free destinations, the shared shell, PHP admin notices, Site
Health, export headers, the four Pro destinations, the Pro license and Shield
messages, and both plugin header descriptions.
**Status:** Contract for the second-pass delta. Doc 61 is the first-pass record;
this document lists only the strings that change again.

## How this pass ran

1. `grilling` recorded the second-pass decisions in
   `.agents/notes/2026-11-01-grill-ui-copy-pass2.md` in both plugins.
2. `writing` rebuilt the 1,544-row inventory, scanned every string for the five
   tricolon shapes, and re-audited the guide categories the glossary test cannot
   see.
3. `humanizer` applies the new tricolon rule and the read-aloud pass to the
   changed sentences.
4. `qa` audits the delta without fixing it, in
   `.agents/notes/2026-11-01-qa-ui-copy-pass2.md`.

## Header changes

The Free description was split across two docblock lines. WordPress reads a
plugin header as one line, so the plugins screen showed only
`Enhanced Update Manager for WordPress. Monitor every change,`; the second line
was never part of the description. The localization code at
`class-updatronix-autoupdates.php:113` compared the raw value against a third,
older string, so it never matched and the description was never translated.

The new text is one line, and the comparison and the `__()` call use the same
literal.

| File | Before | After |
|---|---|---|
| `updatronix.php:12-13` | Enhanced Update Manager for WordPress. Monitor every change, control all updates, and fine-tune your website maintenance flow. | Keeps a record of every update and puts the automatic update settings in one place. |
| `class-updatronix-autoupdates.php:113,116` | Manage your WordPress updates with confidence. Control auto-updates, capture technical logs, and route alerts to the right places. | Keeps a record of every update and puts the automatic update settings in one place. |
| `updatronix-pro.php:12` | Extends Updatronix with professional-grade features for developers and agencies. | Adds Update Shield and Update Flow, plus White label, to Updatronix. |

The Pro description is a known literal too. The helper now has an
`updatronix-pro` branch and translates it under the Free `updatronix` domain,
so the Free POT extracts it.

Only the `Description:` lines change. `UPDATRONIX_VERSION`,
`UPDATRONIX_PRO_VERSION`, `Stable tag:`, and every other header stay put.

## Delta

| File | Before | After | Guide section |
|---|---|---|---|
| `assets/src/js/shell/routes.js:70` | Stop incompatible updates, order automatic updates, and protect version-controlled extensions. | Blocks incompatible updates and protects version-controlled extensions. | Tricolon: the imperative triple |
| `updatronix.php:12` | Enhanced Update Manager for WordPress. Monitor every change, control all updates, and fine-tune your website maintenance flow. | Keeps a record of every update and puts the automatic update settings in one place. | Claims; tricolon |
| `inc/classes/class-updatronix-autoupdates.php:113,116` | Manage your WordPress updates with confidence. Control auto-updates, capture technical logs, and route alerts to the right places. | Keeps a record of every update and puts the automatic update settings in one place. | Claims; stale literal; tricolon |
| `assets/src/js/components/autoUpdates/ItemsDataViewSection.js:125` | Disable automatic updates for %s | Turn off automatic updates for %s | UI verbs: switch |
| `assets/src/js/components/autoUpdates/ItemsDataViewSection.js:134` | Enable automatic updates for %s | Turn on automatic updates for %s | UI verbs: switch |
| `assets/src/js/components/SettingsPanel.js:399` | Disable all update emails | Turn off all update emails | UI verbs: switch |
| `assets/src/js/components/SettingsPanel.js:427` | They contain the recovery link. Do not disable them, or you could lock yourself out of the site. | They contain the recovery link. Do not turn them off, or you could lock yourself out of the site. | UI verbs: switch |
| `assets/src/js/components/UpdateFlowPage.js:1033` | Enable %s | Turn on %s | UI verbs: switch |
| `assets/src/js/components/SettingsPanel.js:279` | Between 1 and 365 days. | Between 1 and 365\u00A0days. | Units: nonbreaking space |
| `assets/src/js/components/schedule/SchedulePanel.js:283` | …Twice daily runs again 12 hours later. | …Twice daily runs again 12\u00A0hours later. | Units: nonbreaking space |
| `assets/src/js/components/schedule/scheduleDraft.js:176` | …the second runs 12 hours later. | …the second runs 12\u00A0hours later. | Units: nonbreaking space |
| `assets/src/js/components/activityLog/ExportLogsModal.js:399` | The export could not be generated. Try again, or adjust your filters and try again. | The export could not be generated. Adjust your filters and try again. | Prose: repeated action |
| `assets/src/js/components/activityLog/ExportLogsModal.js:496` | …It expires after 15 minutes. | …It expires after 15\u00A0minutes. | Units: nonbreaking space |
| `assets/src/js/components/activityLog/ExportLogsModal.js:683` | Last 30 days | Last 30\u00A0days | Units: nonbreaking space |
| `assets/src/js/components/activityLog/ExportLogsModal.js:687` | Records from the last 30 days. | Records from the last 30\u00A0days. | Units: nonbreaking space |
| `assets/src/js/components/activityLog/ExportLogsModal.js:852` | …It expires after 15 minutes. | …It expires after 15\u00A0minutes. | Units: nonbreaking space |
| `assets/src/js/pages/DocumentationPage.js:21` | Install Updatronix, understand each update setting, and read the update log. | Install Updatronix, then use the guide to understand each update setting and read the update log. | Tricolon: the imperative sequence |
| `assets/src/js/pages/HealthPage.js:650` | Failures cleared in the last 30 days, with the action that resolved them. | Failures cleared in the last 30\u00A0days, with the action that resolved them. | Units: nonbreaking space |
| `assets/src/js/pages/HealthPage.js:657` | No failures were resolved in the last 30 days. | No failures were resolved in the last 30\u00A0days. | Units: nonbreaking space |
| `inc/classes/class-updatronix-health.php:962` | This failure is older than 30 days and cannot be retried. | This failure is older than 30\u{00A0}days and cannot be retried. | Units: nonbreaking space |
| `assets/src/js/pages/ProDestination.js:185` | …White label, and advanced exports (CSV and JSON). … | …White label, and exports in CSV and JSON. … | Claims: “advanced” |
| `assets/src/js/components/LicensePanel.js:57` | Advanced exports | CSV and JSON exports | Claims: “advanced” |
| `assets/src/js/components/LicensePanel.js:58` | Exports the update log as CSV or JSON. | Creates a downloadable report from the update log. | Claims: repetition |
| `assets/src/js/components/UpdateShieldPage.js:982` | No blocked updates in the last 30 days. | No blocked updates in the last 30\u00A0days. | Units: nonbreaking space |
| `updatronix-pro.php:12` | Extends Updatronix with professional-grade features for developers and agencies. | Adds Update Shield and Update Flow, plus White label, to Updatronix. | Claims: “professional-grade” |

The `\u00A0` in JavaScript is the U+00A0 nonbreaking space. PHP now embeds the
literal U+00A0, because the extractor kept `\u{00A0}` as an escape instead of
the character; the runtime text is unchanged apart from that space.

## Tricolon decisions

- **Ornamental, rewritten:** the Free Update Shield description, the Free
  header, and the stale Free localization literal.
- **Borderline, rewritten:** the Documentation Page imperative sequence and the
  repeated “try again” in the export error.
- **Real lists, kept:** every other candidate from the scan, including
  `name, mark, and colors`; `Git, SVN, Mercurial, and Bazaar`;
  `single, bulk, and update-core`; `installed, failed, and skipped`;
  `Hooks, functions, and a REST API`; and the run-count summaries. Four-item
  lists the regex misread as triples also stay.

## Humanizer pass

The updated humanizer ran over every changed sentence, with the tricolon rule
first.

| Surface | Changed sentence | Result |
|---|---|---|
| Free Update Shield description | Blocks incompatible updates and protects version-controlled extensions. | Two clauses; no triple; keep. |
| Free plugin header and localization | Keeps a record of every update and puts the automatic update settings in one place. | Two factual clauses; no claim; keep. |
| Free Documentation Page | Install Updatronix, then use the guide to understand each update setting and read the update log. | A sequence with “then”; the imperative triple is gone; keep. |
| Free export error | The export could not be generated. Adjust your filters and try again. | Two sentences; the repeated action is gone; keep. |
| Free switch labels and units | Turn off/turn on …; 12\u00A0hours; 15\u00A0minutes; 30\u00A0days | Labels and spacing; no prose rhythm. |
| Free 3000 preview | Updatronix 3000 adds Update Shield, Update Flow, White label, and exports in CSV and JSON. No Updatronix 3000 feature is active on this site. | Four real features; the short final sentence is a fact, not a closer. |
| Pro header | Adds Update Shield and Update Flow, plus White label, to Updatronix. | A 2+1 real-feature list; no claim. |
| Pro feature name and switch label | CSV and JSON exports; Turn on %s | Labels; no prose rhythm. |
| Pro blocked summary | No blocked updates in the last 30\u00A0days. | Fact plus unit; no prose rhythm. |

The five tells: not-X-but-Y 0; one-line closer 0 (the short final sentences are
an instruction and a fact); dash 0; ornamental tricolon 0; bold label 0.

Genuine three-item lists kept: `Git, SVN, Mercurial, and Bazaar`;
`single, bulk, and update-core`; `name, mark, and colors`;
`installed, failed, and skipped`; `Hooks, functions, and a REST API`. The
four-item lists the regex misread as triples also stay.

`humaniseur-fr` was not applied to the English UI source; it governs French
notes and replies only.

## Checked and kept

- **Sentence case:** 0 title-case candidates in the labels and headings.
- **Serial commas:** 0 missing serial commas.
- **Second person:** one “your users” in the White label preview is the site
  owner's users, not a failure to address the reader.
- **Present tense:** every `will` describes a real future event (retry, next
  run, reactivation), not a habit or a promise.
- **Active voice:** the remaining passives are state descriptions or have no
  actor worth naming; the humanizer pass re-checks the changed sentences.
- **Dates and times:** no hardcoded date format; the UI uses the WordPress date
  helpers.
- **Link text:** no vague link text; the `here` hits are adverbial, not link
  labels.
- **Abbreviations:** `PHP`, `CSV`, `JSON`, `XML`, `REST API`, `WP-CLI`, `HTTP`,
  and `URL` are technical terms the audience knows; `VCS` is gone.
- **UI nouns:** `toggle` as a noun is allowed by the guide; `page` and `screen`
  are both correct in their contexts.
- **Inclusive language, slashes, exclamation points, ellipses, curly quotes,
  `e.g.`, `Please`:** none; the glossary test covers them.

## Translation

The pass-1 decision stands. The local
`wp-content/languages/plugins/updatronix-fr_FR.po` is not patched; changed
strings fall back to English until translate.wordpress.org updates. This table
is the list to send.

## Verification

- `bin/harness pot` in both plugins, then confirm every changed `msgid` in this
  document appears in the new `.pot`.
- `bin/harness lint`, `bin/harness test`, `bin/harness test:js` in both.
- `tests/Unit/HeaderDescriptionTest.php` pins both header descriptions to their
  localization literals.
- `tests/Unit/PotNbSpTest.php` rejects a literal NBSP escape in either POT.
- `tests/e2e/free-settings.spec.js` uses the current retention phrase.
- The E2E run is environment-limited: the site loads, but the `wp` CLI helper
  cannot reach the database, so 6 tests fail at setup and 17 do not run. The
  selector fix is kept.
- `git diff --check` in both.
- No commit and no version bump. The rendered 390 px / 1280 px check and the
  screen-reader pass remain manual.

## Follow-up — findings fixed

The second-pass QA findings are fixed in this follow-up.

| Finding | Fix | Guard |
|---|---|---|
| PHP `\u{00A0}` msgids did not match the runtime NBSP | `class-updatronix-health.php` now embeds the literal U+00A0 | `PotNbSpTest` |
| `HeaderDescriptionTest` used a raw substring count | The test checks the comparison and the `__()` call | `HeaderDescriptionTest` |
| Pro header had no localization path | `class-updatronix-autoupdates.php` now has an `updatronix-pro` branch under the `updatronix` domain | `HeaderDescriptionTest` |
| Pro export description repeated the feature name | It now reads “Creates a downloadable report from the update log.” | Pro POT |
| Pro readme claimed “professional-grade” | The top line, Features, and FAQ now match the shipped UI | readme diff |
| Free readme kept the “Not what, not when, not how” staccato | The Description is two plain paragraphs | readme diff |
| E2E looked for an old retention phrase | The selector uses the current phrase | `tests/e2e/free-settings.spec.js` |

The French pack remains unpatched and external; this document is still the list
to send.

## Outside scope

- `readme.txt` prose beyond the recorded findings. The Description findings and
  the Pro Features/FAQ contradictions are fixed; the rest remains release copy,
  not interface text.
- Code comments, docblocks, identifiers, option keys, REST fields, and version
  headers.
