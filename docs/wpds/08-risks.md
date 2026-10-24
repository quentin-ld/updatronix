# Risk register

Each risk has an owner, a likelihood, an impact, and a mitigation that is
already wired into a task or a gate. A risk without a task is not mitigated.

## Register

| ID | Risk | Likelihood | Impact | Mitigation | Owned by |
|---|---|---|---|---|---|
| `R-01` | The pinned WPDS set needs a WordPress or element version newer than the declared floor, and the shell is blank on a supported site. | High | High | `F0-01` compatibility spike on 7.0 and 7.1 before any package upgrade; declare the verified floor. | Free build |
| `R-02` | Free and Pro drift onto different WPDS versions. | Medium | Medium | `P0-04` boundary test compares both `package.json` files; release gate checks the lockstep set. | Both builds |
| `R-03` | Pro bundles its own `@wordpress/theme`, so panels sit outside Free's ThemeProvider. | Medium | High | `D-09`, `P0-02`, `P0-03`: one runtime global and a boundary test that fails if WPDS appears in Pro's bundle. | Pro build |
| `R-04` | Moving Auto updates to one manual save loses a change or applies a partial save. | Medium | High | `F3-03`: atomic batch save, dirty-state tests, a failure keeps the draft. | Free UI |
| `R-05` | Health checks produce false alarms on unusual hosting. | High | Medium | `F0-05`/`F4-01`: a `Not available` status, an explanation per check, and a short cache. | Free backend |
| `R-06` | A retry destabilizes a site or loops. | Medium | High | `F4-01`: capability, per-item lock, three attempts per hour, a log entry, no client package input. | Free backend |
| `R-07` | The license API is down during activation. | Medium | Medium | `P2-02`: no optimistic activation, a clear error, a retry, and the stored state unchanged. | Pro backend |
| `R-08` | Version-control detection locks the wrong item or misses one. | Medium | High | `P3-02`: documented detector, caching, a filter, fail open with a logged reason. | Pro backend |
| `R-09` | A Shield block disables auto-updates and surprises the owner. | Medium | Medium | `P3-02`: explicit behavior, re-enable option, visible block reason, log entry. | Pro backend |
| `R-10` | A wrong Flow order breaks a site. | Medium | High | `P4-02`: validation, order preview, per-step result, log link, stop on failure. | Pro backend |
| `R-11` | Users read “dependency” as automatic discovery. | High | Medium | `FEAT-P-FLOW`: the UI says the dependency is user-declared; no automatic discovery claim. | Pro product |
| `R-12` | White label branding leaks into screens the product does not own. | Medium | High | `P5-03`: an explicit apply-point list and negative tests for other plugins' menus. | Pro backend |
| `R-13` | Logo upload expands the attack surface. | Medium | High | `P5-01`: attachment-only, MIME and size checks, capability checks, no SVG. | Pro backend |
| `R-14` | The queue worker conflicts with WordPress cron or another update plugin. | High | High | `FEAT-P-QUEUE`: single worker, conditional claim, stale-lock recovery, documented `DISABLE_WP_CRON` behavior. | Pro backend |
| `R-15` | A custom queue table is left behind or its schema drifts. | Medium | Medium | `FEAT-P-QUEUE`: `dbDelta`, schema version, explicit uninstall rule. | Pro backend |
| `R-16` | A large export exhausts memory or times out. | High | Medium | `FEAT-P-EXPORTS`: reuse the chunked engine, a hard limit, a clear message. | Pro backend |
| `R-17` | CSV formula injection. | Medium | High | `FEAT-P-EXPORTS`: prefix risky cells, document the rule, test each prefix. | Pro backend |
| `R-18` | The report and the machine formats drift. | Medium | Medium | One column contract and golden tests for all formats. | Pro backend |
| `R-19` | The public API becomes a compatibility burden. | Medium | Medium | `FEAT-P-DEV-TOOLS`: small surface, versioned namespace, deprecation policy, contract tests. | Pro architecture |
| `R-20` | A public write endpoint triggers an update unexpectedly. | Low | High | Capability, idempotency key, rate limit, audit log, no client package. | Pro backend |
| `R-21` | A translation string is reworded and the French pack breaks. | Medium | Medium | `F5-02`: no reword without confirmation; `.pot` regeneration; translation smoke test. | Both |
| `R-22` | Accessibility regressions in the rail, dialogs, or tables. | Medium | High | `F1-06`, `F6-01`, `P7-01`: keyboard and screen-reader audits at each gate. | Both |
| `R-23` | A capability is wrong on multisite, exposing a Pro panel to a subsite admin. | Medium | High | `P1-03`: integration matrix; server-side checks; the UI only reflects the server. | Both |
| `R-24` | The bundle grows enough to slow the admin page. | Medium | Medium | `F0-03` baseline, per-phase comparison, route-level splitting, a 15% threshold. | Both builds |
| `R-25` | `@wordpress/ui` is experimental and a patch release breaks the build. | Medium | High | Pin the exact version, no caret range for the WPDS set, a compatibility gate before any upgrade. | Both builds |
| `R-26` | Free 2.0.0 and Pro 0.8.0 release out of sync. | Medium | High | Pro declares `Requires Plugins: updatronix`; the release order is Free first; Pro's runtime guard checks the shared runtime major. | Release |

## Risk matrix

| Impact ↓ / Likelihood → | Low | Medium | High |
|---|---|---|---|
| High | `R-20` | `R-03`, `R-06`, `R-08`, `R-10`, `R-12`, `R-13`, `R-22`, `R-23`, `R-25`, `R-26` | `R-01`, `R-14` |
| Medium |  | `R-02`, `R-05`, `R-07`, `R-09`, `R-11`, `R-15`, `R-18`, `R-19`, `R-21`, `R-24` | `R-16` |
| Low |  |  |  |

## Early warning signals

| Signal | Risk | Response |
|---|---|---|
| A blank admin page on the target WordPress version | `R-01` | Stop, run `F0-01`, do not upgrade packages. |
| Pro's bundle grows by more than a few KB | `R-03` | The externals map is wrong; fix `P0-02` before anything else. |
| A save reports success but a field resets | `R-04` | The batch payload is incomplete; fix the endpoint and the hook. |
| A Health check flips on every refresh | `R-05` | The check is not cached or the source is unstable; add a cache and a “not available” state. |
| A blocked item has no log entry | `R-08`, `R-09` | The decision path bypasses the logger; fix before release. |
| A Flow run applies steps out of order | `R-10` | Stop the run, record the failure, and re-open `D-16`. |
| Another plugin's menu label changes | `R-12` | The branding apply point is too broad; revert and narrow it. |
| An export includes a cell starting with `=` unescaped | `R-17` | Block the release; fix the CSV encoder. |
| A Pro panel is visible to a subsite admin | `R-23` | Treat as a security defect; fix the server check. |
| The initial bundle exceeds the budget | `R-24` | Check the externals map and the route chunks. |

## Residual risks accepted at planning

- `R-11`: user-declared dependencies are the product contract; the UI must say so.
- `R-19`: a public API is a long-term commitment; the surface is deliberately small.
- `R-25`: `@wordpress/ui` may break between minor versions; the response is to pin, not to fork.
- `R-26`: two repositories cannot be released atomically; the Free-first order and the Pro guard are the accepted control.
