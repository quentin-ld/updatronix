# Decisions

This is the durable record of the planning interview. Each decision names the
question, the answer, and what it changes. Assumptions are decisions taken
without a round; every question is closed before its owning task starts.

## Decision log

| ID | Decision | Rationale | Consequence |
|---|---|---|---|
| `D-01` | The durable migration record lives in `updatronix/docs/wpds/` and covers both editions. | Free owns the shell and the shared runtime, so the architectural home is the Free repository. | Pro task files cite Free task IDs instead of duplicating architecture text. |
| `D-02` | Raise the WordPress floor. Target 7.1 verified; 7.0 absolute. | `wp-theme` first ships in 7.0, and the pinned set depends on `@wordpress/element ^10`. The 6.2 declaration is already false. | **Superseded by `D-22`** after the bundle stopped declaring `wp-theme`. |
| `D-03` | Free first, then Pro. | The rail is Free-owned; Pro consumes it once stable. | Pro phases P0 and P1 start only after Free gate `G-F2`. |
| `D-04` | Document all eleven destinations plus Queue list, Advanced exports, and Development tools. | Every announced feature needs an architecture contract, including the three without a screen. | Eleven feature briefs. Three are design-gated and do not enter implementation until a screen exists. |
| `D-05` | Standardize both plugins on the guide's pinned set: `@wordpress/ui` 0.25.0, `@wordpress/theme` 3.0.0, `@wordpress/components` 43.0.0, `@wordpress/dataviews` 21.0.0, `@wordpress/admin-ui` 3.0.0, `@wordpress/icons` 19.0.0. | The guide was tested against these versions. Two editions on two sets will drift. | One lockstep upgrade. Free and Pro share the same lockfile intent and the same compatibility gate. |
| `D-06` | Bundle with `wp-scripts` plus a custom `webpack.config.js`. | Keeps the harness, the lint stack, and the release tooling. A second build system would fork the pipeline. | Each plugin carries a small webpack config that overrides dependency extraction. |
| `D-07` | The Pro registry passes a React component, not a DOM mount function. | WPDS theming and portals depend on React context. A DOM mount sits outside Free's `ThemeProvider`. | `updatronixProPanelRegistry[slug]` becomes a component. Free renders it inside its providers and an error boundary. |
| `D-08` | Planning target: Free 2.0.0, Pro 0.8.0. | The shell is a major UI change for Free; Pro stays pre-1.0. | No version is bumped during planning. `/release` performs the bump after explicit authorization. |
| `D-09` | Free exposes one bundled WPDS runtime as `window.updatronix.wpds`; Pro consumes it. | Two bundled copies of `@wordpress/theme` mean two React contexts, so Pro panels cannot inherit Free's theme. | Pro's webpack config marks WPDS imports external and maps them to the global. Pro does not bundle WPDS. |
| `D-10` | Bundle `ui`, `theme`, `admin-ui`, `dataviews`, `components`, and `icons`. Keep React, `@wordpress/element`, and WordPress infrastructure external. | Guarantees the guide's tested component versions on every supported WordPress release without duplicating React. | One larger Free bundle, no runtime `wp-components` version drift. |
| `D-11` | Keep `wp-element` external and target 7.1. | Avoids a second React copy inside wp-admin. | A compatibility spike (`F0-01`) verifies the element and private-apis versions on 7.0 and 7.1 before the package upgrade lands. |
| `D-12` | Vendor the field guide and the screen sources in `docs/wpds/reference/`, without screenshots. | Links to the workspace-root Open Design project break on a fresh clone. Screenshots add about 10 MB with little implementation value. | The guide HTML, its CSS and JS, the logos, and `screens/src/` are committed. |
| `D-13` | The screens hub does not ship. It remains a design reference outside the rail. | It duplicates the rail and has no task of its own; the guide places it outside the product navigation. | The hub source stays vendored; no route, component, or dead data file ships. |
| `D-14` | Queue list lives inside Update Shield; Advanced exports lives in the Update logs export dialog; Development tools lives under Documentation. | These are the native homes of the behavior; adding rail destinations would duplicate existing surfaces. | No new rail item. Each feature extends the screen that already owns its data. |
| `D-15` | White label uses the network-wide Pro option model. | Pro is `Network: true` today; a per-site override would fork the option model and the capability rules. | One network option. Per-site branding is deferred until a product requirement exists. |
| `D-16` | Update Flow runs its chains before the regular update check on the same cron tick, using WordPress's own upgrader APIs. It does not reorder WordPress's internal queue, stops on the first failure, respects Shield, and skips items with no pending update. | This is the smallest change that honors the promise without replacing the update engine. | The run records each step. The definition of “dependency” stays user-declared. |
| `D-17` | Health uses the six checks; resolved issues are derived from logs for 30 days; the snapshot is cached for 5 minutes; no new table. | Reuses the log as the single source of truth and avoids schema weight. | A check that cannot be computed reports `Not available`, not a failure. |
| `D-18` | No runtime WordPress-version gate in Free; the `Requires at least` header is authoritative. Pro adds an admin notice when Free is missing or below the required version. | WordPress already blocks activation below the header; a second gate would contradict it. | Free updates the header and readme in the same change as the bundle. |
| `D-19` | Pro destinations stay visible on Free-only installs with a Pro badge and route to the Updatronix 3000 upgrade page. | The guide treats Upgrade as a rail destination, not a hidden feature. | A Free user can read what Pro adds; a Pro panel that fails falls back to the error boundary. |
| `D-20` | Bundle `@wordpress/element` 10 and the WPDS dependency closure in Free; keep React and ReactDOM external. | The compatibility spike found that WordPress 7.1 ships `@wordpress/element` 8.3.0, while the pinned `@wordpress/ui` 0.25.0 and `@wordpress/theme` 3.0.0 require element `^10.0.0`. Externalizing `wp-element` would pair the new components with the old wrapper. React 18.3.1 stays shared, so there is no second React. Supersedes `D-11` and the element part of `D-10`. | Element 10 is part of the shared runtime. The runtime exposes `element`, `ui`, `theme`, `adminUi`, `components`, and `icons`; dataviews stays route-level, and compose/private-apis are bundled internally rather than exposed. |
| `D-21` | External allowlist: React, ReactDOM, the JSX runtime, `api-fetch`, `data`, `i18n`, `notices`, `dom-ready`, `hooks`, `url`, and `date`. Bundle every other `@wordpress/*` package in the WPDS closure. | Shared stores, translations, filters, and the REST middleware must stay on the core instances. The WPDS UI packages and their private-API contract are version-sensitive and must ship with the plugin. `keycodes` must be bundled because core 7.1's `wp-keycodes` lacks `withIgnoreIMEEvents`, which `@wordpress/components` 43 calls. | `i18n` stays on the core module so Jed locale data applies to WPDS strings. `data` and `notices` stay on the core module so the stores are shared. `a11y`, `keycodes`, `warning`, `deprecated`, `compose`, `private-apis`, and `primitives` are bundled. Watch for duplicate live regions from the bundled `a11y`. |
| `D-22` | Keep the declared WordPress 6.2 floor until a 6.2 smoke test proves otherwise. Do not raise the header on the strength of the old `wp-theme` dependency. | `D-20` and `D-21` removed `wp-theme` from the built manifest. The remaining core handles (`api-fetch`, `data`, `i18n`, `notices`, `dom-ready`, `hooks`, `url`, `date`) exist in 6.2, and React 18 is shared. The 7.1 test is evidence, not a floor. | The header, `readme.txt`, and README stay at 6.2. A 6.2 smoke test is a release gate; if it fails, raise the floor with the evidence. |

## Assumptions

| ID | Assumption | Why | Revisit when |
|---|---|---|---|
| `A-01` | The screens hub is a design-review artifact and is not shipped. | The guide places it outside the rail and calls it an index. Shipping it would add a twelfth destination that the product does not need. | A user-facing “all screens” page is requested. |
| `A-02` | Health is a new Free feature, not a reskin of an existing errors page. | The current plugin logs failures but has no readiness checks, retry flow, or resolved archive. | The Health brief is implemented; the backend contract is confirmed there. |
| `A-03` | Documentation and Support are static pages with outbound links and no backend. | The design contains only guide cards, four checks, and a forum link. | Either page needs dynamic content. |
| `A-04` | Auto updates moves from immediate per-control saves to one manual save. | The guide mandates one save model on Settings, Schedule, and Auto updates. | The behavior change is rejected in review; then the guide needs an exception. |
| `A-05` | Pro destinations appear in the Free rail with a Pro badge even when Pro is inactive; selecting one opens the Updatronix 3000 page. | The guide puts Upgrade in the rail as a destination, not a header banner. | The product decides to hide Pro destinations on Free-only installs. |
| `A-06` | Queue list lives inside Update Shield; Advanced exports lives inside the Update logs export dialog; Development tools lives under Documentation. | The guide's rail has no separate destination for them, and their descriptions overlap existing surfaces. | A design adds dedicated destinations. |
| `A-07` | The dark rail uses the WordPress admin color scheme seed through `getAdminThemeColors()`, with WordPress blue as the default brand seed. | The guide recommends brand continuity and exposes four presets; the default preset is the WPDS default. | Brand direction changes. |
| `A-08` | Deep links keep the `?tab=` query parameter and the existing slugs. | Existing bookmarks and documentation links must survive. | The routing contract changes; then add redirects. |
| `A-09` | The migration ships without a runtime legacy-UI flag. | The guide forbids mixed surfaces. A flag would keep two systems alive indefinitely. | A staged rollout needs a temporary constant; it must be removed before release. |
| `A-10` | Free keeps its formatted report export; Pro adds CSV, XML, and JSON. | The Free README promises one-click export; the Pro roadmap promises the formats. | The export brief changes the split. |

## Resolved questions

No open question remains. Each one was closed before implementation; the decision
is the contract.

| ID | Question | Resolution |
|---|---|---|
| `O-01` | Is the screens hub ever a product page? | No. `D-13`. |
| `O-02` | What are the final rail homes for Queue list, Advanced exports, and Development tools? | Update Shield, the Update logs export dialog, and Documentation. `D-14`. |
| `O-03` | Is white label network-wide, per site, or both? | Network-wide, matching the existing Pro option model. `D-15`. |
| `O-04` | How does Update Flow reorder the update queue? | It does not reorder WordPress's queue. It runs chains before the regular check, on the same cron tick, and stops on failure. `D-16`. |
| `O-05` | Which Health checks are authoritative, and how long are resolved issues retained? | The six documented checks; 30 days derived from logs; a 5-minute snapshot cache. `D-17`. |
| `O-06` | Should the plugin show an admin notice below the verified floor? | No runtime gate in Free; the header is authoritative. Pro notices a missing or old Free. `D-18`. |
| `O-07` | Do Pro destinations stay visible on Free-only installs? | Yes, with a Pro badge and the upgrade page. `D-19`. |

## Rejected options

| Option | Why it was rejected |
|---|---|
| Keep the WordPress 6.2 floor and bundle React | A second React inside wp-admin risks hook and context duplication, and the maintenance cost is permanent. |
| Keep the 6.2 floor with a legacy fallback UI | Two interfaces to build, test, and support contradicts the one-system rule. |
| A shared design-system plugin | Adds a third release unit and contradicts the instruction to bundle the system into the plugin. |
| A separate esbuild or Vite build for the shell | Forks the harness, the lint stack, and the release pipeline. |
| Each plugin bundles its own WPDS | Two copies of `@wordpress/theme` mean two React contexts; Pro panels would not inherit the theme. |
| Pro ships its own rail | Duplicated navigation and two shells to keep in sync. |
| A runtime legacy-UI flag after release | Keeps the legacy shell alive and doubles the test matrix. |
| Vendor the full Open Design project | About 10 MB of screenshots that implementation does not use. |
