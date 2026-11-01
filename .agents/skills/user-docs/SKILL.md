---
name: user-docs
description: >-
  Write the project's user guide under docs/user/ — structure, screenshots and
  the WordPress style guide — and run `bin/harness docs check`.
---

# User documentation

The guide lives in the project, in `docs/user/`. It is for a person who uses the
plugin or theme, not for the agent and not for the developer: `AGENTS.md` and
`workflow.md` are those.

## Structure

| file | what it answers |
|---|---|
| `index.md` | what this is, who it is for, and where to start |
| `installation.md` | requirements and the install steps |
| `getting-started.md` | the first task, end to end |
| `tasks/<name>.md` | one page per job the user does |
| `reference/<name>.md` | settings, hooks, shortcodes, CLI |
| `troubleshooting.md` | the failures a user can hit and fix |
| `faq.md` | the questions that repeat |
| `images/` | the screenshots, PNG or SVG |

`index.md` is the door: every page is linked from it, or from a page it links.

## Prose

The `writing` skill carries the WordPress Documentation Style Guide. Read it
before the first page, and run `humanizer` on the finished text (or
`humaniseur-fr` for French). User-facing prose is not the place for the agent's
register.

## Screenshots

- PNG or SVG, one image per task, full window, no PII and no real customer data.
- Every image carries alt text; the alt says what the reader should notice.
- Put them in `docs/user/images/` and link them relatively.
- Prefer the `.wordpress-org/screenshot-*.png` files the plugin already ships;
  do not retake a screen that already exists.

## The gate

```bash
bin/harness docs check
```

It checks links, images and alt text, and heading hierarchy. It is manual: it is
not in `pre-push`, because a project without a guide is not a broken gate. A
finding is a fix, not a note.

`docs/` does not ship in the release archive (`wp dist-archive` excludes it), so
the guide is a repository and GitHub document. `readme.txt` stays the
WordPress.org listing, and its facts must agree with the guide.
