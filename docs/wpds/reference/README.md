# Reference

A read-only mirror of the Open Design field guide and the screen sources. The
original project remains the source of truth; this copy exists so a fresh clone
can read the design contract without the workspace-root directory.

## Provenance

| | |
|---|---|
| Source | Open Design project `2dc80110-eba2-4562-acea-441ae2821939` |
| Guide | `WordPress Design System — Updatronix Field Guide`, reviewed 23 October 2026 |
| Package set at review | `@wordpress/theme` 3.0.0, `@wordpress/ui` 0.25.0, `@wordpress/components` 43.0.0, `@wordpress/dataviews` 21.0.0, `@wordpress/admin-ui` 3.0.0, `@wordpress/icons` 19.0.0 |
| Vendored on | 24 October 2026 |

## Included

```
reference/
  index.html                 # the field guide
  assets/css/guide.css       # guide layout
  assets/css/wpds-tokens.css # token snapshot
  assets/js/guide.js         # guide interactions
  assets/img/                # the two Updatronix logos and the item logos
  screens/src/               # the React screen sources, CSS, and data
  screens/package.json       # the screen build's dependency set
  screens/build.mjs          # the screen build script
```

## Excluded on purpose

- The screenshot set (`assets/img/current/`, the root `Screenshot-*.png`, and the
  `drawing-*.png` files). They add about 10 MB and are not implementation inputs.
- The built screen bundle (`screens/build/app.js`, `app.css`). It is generated
  output, not source.
- `.file-versions/`, `.od-frames/`, `.od-skills/`, and the Open Design metadata.

Because the build is excluded, the guide's links to `screens/*.html` do not
resolve here. The guide's inline “Applied” shell still works. Use the screen
sources for the target markup and the guide for the rules.

## How to view

Open `index.html` in a browser. The shell preview, the token tables, and the
component examples are inline. The screen links require the original project or
a local rebuild:

```bash
cd reference/screens
npm install
npm run build
```

Do not commit the rebuilt `build/` directory.

## How to refresh

The original lives outside both plugin repositories, at the workspace root:

```bash
open-design-project/2dc80110-eba2-4562-acea-441ae2821939/
```

When the design changes, copy the same subset again and update the provenance
date. Do not edit the vendored files; record decisions in
[`../02-decisions.md`](../02-decisions.md) instead.

## What is authoritative

| Question | Authority |
|---|---|
| Visual rules, tokens, component choices | This guide. |
| Screen behavior, data, and acceptance | [`../04-screen-contracts.md`](../04-screen-contracts.md) and the feature briefs. |
| Architecture and boundaries | [`../03-target-architecture.md`](../03-target-architecture.md). |
| Conflicts between the guide and the code | The decisions in [`../02-decisions.md`](../02-decisions.md), then the owner. |
