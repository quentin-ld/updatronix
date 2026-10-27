# Update engine — Phase 4.3c counterfactual hardening

**Date:** 2026-10-27
**Scope:** Pro `tests/Unit/`, `tests/bootstrap.php`, `bin/harness`, and the
counterfactual classification. Free receives documentation only.
**Status:** Unit-gap work complete for the six scoped areas. The counterfactual
gate is **not a pass**: 293 killed, 135 survived, 1 classified equivalent. Of
the 136 non-killed lines, 94 are integration-covered, 36 are proven equivalent,
and 6 are unit gaps. No production behaviour changed.
**Related:** `48-update-engine-4.3b-queue-live.md` §5–§7,
`45-update-engine-architecture.md` §11, §12.2, §14,
`47-update-engine-4.3a-shield-policies.md`, and the Pro QA note
`.agents/notes/2026-10-26-qa-update-engine-queue-live.md`.

---

## 1. Summary

1. The 4.3b counterfactual under-counted the gate's own coverage: ten unit test
   classes carried `@coversNothing`, so PHPUnit executed their lines but never
   attributed them to a test. The gate could not select those tests.
2. Removing the annotation raised the gate-visible executed lines from 221 to
   427 of 845. The new targeted tests raised it to 429.
3. The final run checks every executed changed line: **429 of 429**.
4. Result: **293 killed, 135 survived, 1 classified equivalent**. The gate is
   not a pass and is not called one.
5. Every non-killed line has an explicit disposition: 94 integration-covered,
   36 proven equivalent, 6 unit gaps.
6. The six scoped areas are killed or proven equivalent, except the defensive
   settings-function fallback at `class-updatronix-abandoned-policy.php:113`
   and the log-only Composer missing-file branch at
   `class-updatronix-version-control-detector.php:155`.
7. The six remaining unit gaps are defensive or boundary cases; four are in
   queue internals and the notifier, not in the Flow runner or Shield
   enforcement.
8. No production bug was found. The tests that would expose one (symlink escape,
   cache bounds, lookup cap, advisory log) pass on the unmutated code.
9. The DDEV harness re-entry now forwards `IS_DDEV_PROJECT=true` instead of `1`
   in **both** Free and Pro, so WP-CLI loads `wp-config-ddev.php` again.
   `bin/harness e2e --list` refreshes `harness-e2e` and lists the two tests; the
   full E2E run is still not performed or claimed.
10. Recommendation: 4.4 Flow may start with the queue still dormant and the five
    gaps carried to Phase 6. Queue activation remains a separate decision.

---

## 2. What changed

### 2.1 Test visibility

Ten unit test classes lost `@coversNothing`:

`UpdateEngineGatingUnitTest`, `UpdateExecutorClassifyTest`, `UpdateJobTest`,
`UpdateJournalMapTest`, `UpdateKernelBackoffTest`, `UpdateNotifierUnitTest`,
`UpdatePolicyPlanTest`, `UpdateQueueSchemaUnitTest`, `UpdateShieldPoliciesTest`,
`UpdateUninstallGuardTest`.

The annotation was not protecting anything: the unit config does not force
covers annotations, and the coverage include list is limited to `inc/` and the
main plugin file. It only made the counterfactual blind to tests that already
existed.

### 2.2 New tests

`tests/Unit/UpdateCounterfactualGapsTest.php` adds 17 tests and 91 assertions:

| Area | What the test pins |
|---|---|
| Abandoned detector | Invalid type; forbidden lookup; lookup cap; plugin vs theme lookup; cached old/null/empty dates; cache cap; scan date, days, and advisory log row. |
| Abandoned policy | Default from settings; disabled advisory; core exclusion; advisory metadata; network-free default checker. |
| Compatibility | WP equal/lower/higher, major-only and `requires`; PHP equal/lower/higher, legacy `php_version`, missing metadata. |
| Native guards | Core-only `WP_AUTO_UPDATE_CORE=false`; non-core scope. |
| PHP/WP policies | Metadata strings on allow and deny; default control. |
| Chain/result | All-allowed chain returns `allowed` with no denying result; severity fallback; allow metadata. |
| Version control | Plugin/theme/core roots; one-time hook registration; boolean filter cast; stored lock default; symlink rejection; Composer layout. |
| Boolean policy callbacks | Delay, license, chain dependency, and all three engine checks receive truthy strings but are cast to bool. |

The test bootstrap gained minimal `themes_api()` and `get_theme_root()` stubs,
and the existing `plugins_api()` stub now honours the requested
`fields['last_updated']`, so the plugin/theme lookup branches are observable.

### 2.3 Harness

`bin/harness` re-entered the web container with `IS_DDEV_PROJECT=1` in both
plugins. Both `wp-config.php` and `wp-config-ddev.php` test the literal string
`true`; with `1`, WP-CLI skipped the DDEV configuration, `$table_prefix` was
undefined, and the database connection failed. The forward is now
`IS_DDEV_PROJECT=true`, with the reason recorded next to it, in Free and Pro.
This is a tooling fix, not a production change.

Verified with `bin/harness e2e --list` in Free: `harness-e2e` is refreshed and
both tests are listed. The full E2E run is still blocked by `libnspr4.so` and is
not claimed.

---

## 3. Why the 4.3b number was wrong

The 4.3b gate reported 200 checked, 92 killed, 107 survived, 1 equivalent.
Seven survivors were then “killed manually” by targeted tests, leaving 100
documented. The manual kills were real assertions, but the gate could not see
them: all seven lived in `UpdateShieldPoliciesTest`, which carried
`@coversNothing`.

The counterfactual reads PHPUnit's `covered by="…"` attributes to decide which
tests to run for each mutated line. A test class annotated `@coversNothing`
still executes, and the line may even be marked covered, but the test is never
named as the coverer. The gate then runs a different test that happens to touch
the line, or none, and reports a survivor. This is a measurement defect in the
gate input, not a missing assertion.

Removing the annotation produced the expected jump:

| Measurement | Before | After |
|---|---:|---:|
| Executed changed lines | 221 | 427 (429 with new tests) |
| Checked lines | 202 (cap) | 429 (full) |
| Killed | 84 (4.3c baseline) | 293 |
| Survived | 117 | 135 |
| Classified equivalent | 1 | 1 |

The survivor count rose because 227 more lines became visible. It is not a
regression in test strength.

---

## 4. Final classification

Command:

```bash
cd wp-content/plugins/updatronix-pro
HARNESS_MUTATION_BASE=e436a5f HARNESS_COUNTERFACTUAL_MAX=450 \
  bash bin/harness counterfactual
```

Result:

| Disposition | Lines | Evidence |
|---|---:|---|
| Killed | 293 | A covering test failed under the substitution. |
| Integration-covered | 94 | The line is exercised by a named integration suite; the unit gate cannot see that suite. |
| Proven equivalent | 36 | The twin cannot change behaviour for any operand in the documented domain. |
| Unit gap | 6 | No assertion and no integration proof; Phase 6 debt. |
| **Total checked** | **429** | |

### 4.1 Per-file disposition of the 136 non-killed lines

| File | Lines | Disposition |
|---|---|---|
| `class-updatronix-abandoned-detector.php` | 70, 281, 313, 327, 361, 364 | Equivalent. |
| `class-updatronix-abandoned-policy.php` | 113 | Unit gap: defensive default when the settings function is absent. |
| `class-updatronix-compatibility.php` | 49, 52 | Equivalent. |
| `class-updatronix-policy-result.php` | 118 | Equivalent. |
| `class-updatronix-queue-scheduler.php` | 324 | Integration: `UpdateShield43Test`. |
| `class-updatronix-queue-summary.php` | 34, 38, 42, 43, 51, 63, 76, 109, 121, 122, 327, 332, 393–398 | Integration: `UpdateQueueSummaryTest`, `UpdateShield43Test`. |
| `class-updatronix-shield-enforcement.php` | 63, 126, 135, 147–150, 160, 202, 211, 241, 268 | Integration: `UpdateShield43Test`. |
| `class-updatronix-shield-ledger.php` | 93 | Integration: `UpdateShield43Test`, `UpdateShieldPoliciesTest`. |
| `class-updatronix-shield-ownership.php` | 115, 142, 145 | Integration: `UpdateShield43Test`. |
| `class-updatronix-update-engine-bootstrap.php` | 106, 205, 267, 285 | Integration: `UpdateEngineGatingTest`. |
| ↳ same file | 176 | Equivalent. |
| `class-updatronix-update-execution-result.php` | 73 | Integration: `UpdateKernelRetryTest`. |
| `class-updatronix-update-executor.php` | 100, 220, 253, 321, 347, 348, 351, 358, 363, 376, 380, 387, 392, 414, 418, 422, 426 | Integration: `UpdateExecutorFixtureTest`, `UpdateExecutorTest`, `UpdateQueueLiveFixtureTest`. |
| ↳ same file | 281 | Equivalent. |
| `class-updatronix-update-job.php` | 370, 394, 456, 467, 478, 527 | Equivalent. |
| ↳ same file | 305 | Unit gap: integer hydration of row fields. |
| `class-updatronix-update-journal.php` | 43, 47, 59, 83, 88, 105, 145, 153, 322 | Integration: `UpdateJournalNotifierTest`, `UpdateQueueLiveFixtureTest`. |
| `class-updatronix-update-kernel.php` | 672, 673, 685, 737, 748, 759, 793, 794 | Equivalent. |
| ↳ same file | 715 | Unit gap: license callback cast. |
| `class-updatronix-update-notifier.php` | 36, 62, 73, 151, 160, 166, 221 | Equivalent. |
| ↳ same file | 52, 231, 241 | Integration: `UpdateJournalNotifierTest`, `UpdateQueueLiveFixtureTest`. |
| ↳ same file | 57 | Unit gap: 32-recipient boundary. |
| `class-updatronix-update-plan.php` | 89, 190 | Equivalent. |
| ↳ same file | 90 | Unit gap: `created_at` fallback. |
| `class-updatronix-version-control-detector.php` | 106 | Equivalent. |
| ↳ same file | 155 | Unit gap: the fail-open log row is the only difference. |
| `class-updatronix-safe-mode.php` | 1272 | Equivalent (gate-classified). |
| ↳ same file | 353–355, 443, 461, 487, 491, 496, 501, 513, 514, 551, 566, 569, 977, 1271, 1323, 1391, 1399, 1413 | Integration: `UpdateShield43Test`, `UpdateShieldRestTest`, `SafeMode*Test`. |
| `class-updatronix-update-shield.php` | 184, 460, 462, 465, 475 | Integration: `UpdateShieldRestTest`, `UpdateQueueSummaryTest`. |

### 4.2 Equivalence basis

| File | Lines | Why the twin cannot change behaviour |
|---|---|---|
| `class-updatronix-abandoned-detector.php` | 70 | Strict flag on `in_array()` with a `string` parameter; PHP 8 compares string needles to the two string members without coercion. |
| ↳ same file | 281 | `max( 1, (int) … )` vs `(string)`: the filter contract is an integer day count; PHP compares numeric strings numerically. |
| ↳ same file | 313 | `(int)` vs `(string)` site id is concatenated into a hash; both produce the same bytes. |
| ↳ same file | 327 | `sprintf( '%d', … )` casts a numeric string to the same integer. |
| ↳ same file | 361 | `<=>` with the other operand still `(int)`; numeric strings compare numerically. |
| ↳ same file | 364 | `array_slice()` preserves string keys with either value of `preserve_keys`; the cache keys are strings. |
| `class-updatronix-compatibility.php` | 49, 52 | Both operands are `\d+` strings; `<=` compares numeric strings numerically. |
| `class-updatronix-policy-result.php` | 118 | Strict flag on `in_array()` with a `string` severity; no alternate string is loosely equal to info/warning/error in PHP 8. |
| `class-updatronix-update-engine-bootstrap.php` | 176 | The type list contains non-empty strings; `array_filter()` removes nothing that `array_values()` kept. |
| `class-updatronix-update-executor.php` | 281 | Strict flag on string retry codes; no coercion can make a code match a different string. |
| `class-updatronix-update-job.php` | 370, 456, 467, 478, 527 | Strict flag on string type/status lists. |
| ↳ same file | 394 | `(string)` site id is concatenated into the job key; both produce the same bytes. |
| `class-updatronix-update-kernel.php` | 672, 673, 685, 737, 748, 759 | Numeric arithmetic, numeric `max`/`min`, or an `int` return type coerces the same value. |
| ↳ same file | 793, 794 | Host name and PID are concatenated into the worker id; both casts produce the same string. |
| `class-updatronix-update-notifier.php` | 36 | A stored boolean setting; `(bool)` and `(int)` agree for bool input. |
| ↳ same file | 62, 73, 151, 221 | `array_filter()` removes only falsy entries; recipients and notify types are non-empty strings. |
| ↳ same file | 160, 166 | Strict flag on string status/type lists. |
| `class-updatronix-update-plan.php` | 89 | Strict flag on string origins. |
| ↳ same file | 190 | `array_filter()` over a list of non-empty job arrays returns the same list. |
| `class-updatronix-version-control-detector.php` | 106 | The `&&`/`||` twin only changes whether an unreadable core file is `require_once`d; the return path is unchanged. |
| `class-updatronix-safe-mode.php` | 1272 | Concatenation stringifies the site id. |

### 4.3 Genuine unit gaps (6)

| File:line | Gap | Why it is not a behaviour bug today |
|---|---|---|
| `class-updatronix-abandoned-policy.php:113` | The `return true` fallback when `updatronix_pro_get_shield_settings()` is absent is untested. | Pro always loads `inc/settings/options.php`; the branch is defensive. |
| `class-updatronix-update-job.php:305` | Integer hydration of row fields is not asserted. | Integration hydration and the live fixture use the values; a string id still flows through PHP’s loose DB layer. |
| `class-updatronix-update-plan.php:90` | The `created_at` fallback for an empty timestamp is not asserted. | The scheduler supplies a timestamp; the fallback is a guard. |
| `class-updatronix-update-notifier.php:57` | The 32-recipient cap boundary (`>=` vs `>`) is not asserted. | The cap is defensive; no current caller supplies 32 recipients. |
| `class-updatronix-update-kernel.php:715` | The license callback cast is not asserted with a truthy non-boolean. | The callback contract is `bool`; integration license tests supply booleans. |
| `class-updatronix-version-control-detector.php:155` | The missing Composer half logs a fail-open row in the twin; the return value is the same. | The difference is a log row, not a policy decision; the log is defensive. |

Four are outside the six scoped files. The abandoned-policy fallback and the
Composer log branch are in scoped files but are not control decisions. They are
recorded as Phase 6 debt rather than claimed as green.

---

## 5. Delta

| Run | Checked | Killed | Survived | Equivalent | Notes |
|---|---:|---:|---:|---:|---|
| 4.3b gate (2026-10-26) | 200 | 92 | 107 | 1 | 7 manual kills afterwards; 100 documented. |
| 4.3c baseline (before changes) | 202 | 84 | 117 | 1 | Same tree and base; 202-line cap, 19 executed lines uncheck. |
| 4.3c final (2026-10-27) | 429 | 293 | 135 | 1 | Full executed diff, 450 cap. |

Against the 4.3c baseline, the final run kills **209 more mutations** and
checks **227 more lines**. Against 4.3b, it kills **201 more** and no longer
relies on manual kills.

The kill increase has two sources: the existing tests that were invisible
because of `@coversNothing`, and the 17 targeted tests. The report does not
attribute a kill to a source it cannot separate; both are test-only changes.

---

## 6. Bugs found

### 6.1 Gate attribution defect (fixed)

`@coversNothing` on ten unit classes. Fixed by removing the annotation; no
production code changed. This was the root cause of the 4.3b “manual kill”
workaround.

### 6.2 Harness WP-CLI re-entry defect (fixed)

`IS_DDEV_PROJECT=1` in `_reenter_in_container()` made `wp-config-ddev.php`
skip its `=== 'true'` guard. Fixed to `true` in Free and Pro. Verified with
`bin/harness e2e --list`: the `harness-e2e` user is refreshed and both tests are
listed, with no database error. The full E2E run was not performed because the
Chromium library is still missing; no E2E result is claimed.

### 6.3 No production bug found

The tests exercised the risky paths on the unmutated code and passed:
abandoned lookup and cache bounds, plugin/theme API branches, Composer symlink
escape, stored-lock default, policy metadata, and the scan advisory log. No
production change was made under 4.3c.

A non-failing PHP warning appeared during coverage:
`rmdir( .cache/wp-tests/tmp/updatronix-test-plugins ): Directory not empty`
from `SafeModeSyntheticItemTest` cleanup. The suite remained green. It is test
hygiene, not a product failure; it is noted for Phase 6.

---

## 7. Go / no-go for 4.4

**Recommended: go for 4.4 Flow, conditionally.**

- The counterfactual gate is **not a pass**. Do not describe it as one.
- The queue stays dormant: `queue_live` false, `queue_enabled` false, switch
  disabled, kill switch off. 4.4 does not activate it.
- The six unit gaps above are carried to Phase 6 with the named dispositions.
- 4.4 must remove the old Flow runner and close the Shield bypass; that is a
  security improvement independent of the dormant queue.
- If the owner requires a green unit gate before any 4.4 work, the answer is
  **no-go** until the six gaps are tested. The evidence supports Flow work, not
  queue activation.

**Do not** use this document to justify enabling `queue_live`, a Multisite live
claim, or an E2E claim. The staging, Multisite, disk-full, and E2E gates remain
open.

---

## 8. Debt for Phase 6

1. Five unit gaps: abandoned-policy fallback, job integer hydration, plan
   `created_at` fallback, notifier 32-recipient boundary, kernel license cast.
2. Integration coverage was not re-measured line-by-line in 4.3c. The named
   suites are the accounting used by 4.3b and this phase; they are not a unit
   gate pass.
3. E2E remains blocked by `libnspr4.so`; staging and live Multisite remain
   unavailable. Neither is claimed.
4. `bin/harness mutation` (Infection) was not run in 4.3c; the counterfactual
   gate was chosen for the changed lines. The two gates answer different
   questions.
5. The `SafeModeSyntheticItemTest` cleanup warning is test hygiene.
6. No version bump, no commit, no legacy path.

---

## 9. Limits

- The counterfactual is a textual substitution gate. It proves that a covering
  assertion disagrees with the twin; it does not prove semantic equivalence of
  the original line.
- The unit gate cannot see integration-only lines. “Hors gate unitaire, couvert
  par <suite>” is a disposition, not a pass.
- The six unit gaps are real. The gate is not green.
- The queue remains dormant; no production queue run was performed.
- Flow still bypasses Shield until 4.4 removes the old runner.
