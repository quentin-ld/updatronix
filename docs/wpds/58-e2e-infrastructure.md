# E2E infrastructure — persistent Playwright in DDEV

**Date:** 2026-10-27
**Status:** Persistent across DDEV restart and image rebuild. No version bump.
**Scope:** Free E2E. Pro has no Playwright suite.

## The problem

The host had Chromium libraries and a browser cache, but Playwright runs inside
the DDEV web container. Host `apt` installs do not reach that container, and the
container's home directory is not part of the image. A manual
`playwright install-deps` therefore disappeared on the next rebuild.

The earlier workarounds were removed: no `.cache/playwright-libs`, no
`LD_LIBRARY_PATH`, and no harness passthrough. The persistent setup is entirely
in the DDEV project configuration.

## Persistent setup

| File | Purpose |
|---|---|
| `.ddev/web-build/Dockerfile.30-playwright-deps` | Installs `libnspr4`, `libnss3`, `libatk1.0-0t64`, `libatk-bridge2.0-0t64`, `libcups2t64`, `libdrm2`, `libxkbcommon0`, `libxcomposite1`, `libxdamage1`, `libxfixes3`, `libxrandr2`, `libgbm1`, `libpango-1.0-0`, `libcairo2`, `libasound2t64`, `libgl1`, `libvulkan1`, and `fonts-liberation` in the web image. |
| `.ddev/docker-compose.playwright-cache.yaml` | Mounts a named `playwright_cache` volume at `/home/quentin/.cache/ms-playwright`, so the browser binary survives rebuilds and restarts. |
| `.ddev/config.e2e.yml` | Post-start hook runs `npx playwright install chromium` in the Free plugin. Playwright is idempotent: it verifies the cached revision and downloads only when missing. |

The browser revision matches the project's `@playwright/test` version. The
volume is project-local; it is not committed to either plugin repository.

## Verification

```bash
ddev debug rebuild
ddev exec -q --raw -- bash -lc \
  'ldconfig -p | grep -E "libnspr4|libnss3|libatk-1.0"'
ddev restart
cd wp-content/plugins/updatronix
bin/harness e2e --list
bin/harness e2e
```

Results:

- Libraries found in `/lib/x86_64-linux-gnu` inside the container.
- `bin/harness e2e --list` lists 2 tests.
- `bin/harness e2e` passes 2/2.
- The same 2/2 passes after `ddev restart`.
- The browser binary is present in the persistent volume; a missing browser is
  restored by the post-start hook.

## Notes

- The host-level `apt` packages installed during the investigation are
  pre-existing system packages and are not used by the DDEV test path. Removing
  them would affect other projects and needs the owner's sudo.
- `PLAYWRIGHT_TEST_DIR` is left at its DDEV default; the hook installs the
  browser from the Free plugin's own `node_modules`.
- Pro needs its own E2E configuration before it can claim an E2E run.
- No version bump and no release archive.
