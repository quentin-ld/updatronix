# Release gate — Phase 6 final check

**Date:** 2026-10-27
**Status:** Prepared and committed. Not released. No version bump.
**Decision:** Prepare only. Keep Free `1.3.0` and Pro `0.7.0`.

## Final checkpoints

| Repository | Commit |
|---|---|
| Free | `a56cf95` (`feat: finish Free audit, exports, external logging, and E2E`) |
| Pro | `40d0e17` (`feat: finish Shield/Flow state, policies, and license/white-label audits`) |

The release gate itself is committed as the Free docs follow-up after these
checkpoints.

## Verification

| Check | Result |
|---|---|
| Free unit | 217 tests, 424 assertions, pass |
| Free integration | 84 tests, 230 assertions, 13 skips, pass |
| Free JS | 67 tests, pass |
| Pro unit | 357 tests, 1371 assertions, pass |
| Pro integration | 120 tests, 1073 assertions, 1 skip, pass |
| Pro JS | 81 tests, pass |
| Free E2E | 2/2 Playwright tests pass |
| Free/Pro lint | WPCS 0 errors; PHPStan 0 errors |
| Free/Pro build | Pass; existing asset-size warnings remain |
| Free/Pro POT | Regenerated after the vocabulary pass |
| Anti-tautology | Clean in both repos |
| Whitespace | `git diff --check` clean in both repos |
| Pro counterfactual | 82 checked, 74 killed, 0 survived, 8 classified equivalent |
| Version headers | Free `1.3.0`, Pro `0.7.0`; unchanged |
| Stable tags | Free `1.3.0`, Pro `0.7.0`; unchanged |

## Persistent E2E proof

Playwright runs in the DDEV web container, not on the host. The fix is stored in
the DDEV project configuration and survives a restart:

- `.ddev/web-build/Dockerfile.30-playwright-deps` installs the Chromium runtime
  libraries in the web image.
- `.ddev/docker-compose.playwright-cache.yaml` keeps
  `/home/quentin/.cache/ms-playwright` in a named volume.
- `.ddev/config.e2e.yml` runs `npx playwright install chromium` after start; it
  is idempotent and restores a missing browser.

`docs/wpds/58-e2e-infrastructure.md` records the setup and commands. The full
suite passed 2/2 after `ddev restart` and after the image rebuild. The old
`.cache/playwright-libs`, `LD_LIBRARY_PATH`, and harness workarounds are gone.
The host-level package installs are pre-existing system packages and are not
used by the DDEV test path.

## Documentation reconciliation

- `51a-update-shield-phase-5.1-corrections.md` was merged into
  `51-update-shield-phase-5.1.md` and deleted.
- Docs 51/34/11/45 now use the final 357/120/81 Pro counts, the Composer switch
  as usable, the 82/74/0/8 counterfactual, and the E2E result.
- Docs 55/56 record the vocabulary audit and Batch B copy pass; the readme
  Unreleased bullets are retained.
- No `348/118` count and no “Composer disabled when Shield is off” claim remains.

## Remaining external limitations

1. No staged host run with real cron; the local fixture and single-site E2E are
   the evidence.
2. No live Multisite pass. The pure Super-Admin matrix and main-site guard are
   present; the live test is skipped.
3. Pro has no Playwright suite of its own.
4. The queue remains dormant: `queue_live=false`, `queue_enabled=false`, the
   Shield switch disabled, and the kill switch off. Activation needs a staged
   run and a new explicit owner decision.
5. No version bump or release archive is authorized.

## Release checklist

Only after an explicit owner decision:

1. Bump `UPDATRONIX_VERSION` / `UPDATRONIX_PRO_VERSION`, plugin headers,
   `Stable tag`, package metadata, readme, and changelog together.
2. Regenerate POTs, rebuild, package, and verify the archive.
3. Publish only from the reviewed commits above.
4. Do not enable `queue_live` or `queue_enabled` without the staged run and the
   separate owner decision.
