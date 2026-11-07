# Updatronix documentation

Canonical Markdown source for the Updatronix documentation set. The pages on
holdmywp.com are a published mirror; edit these files first, then publish.

| Documentation | Français | English | Scope |
|---|---|---|---|
| Updatronix (Free) | [fr.md](updatronix/fr.md) | [en.md](updatronix/en.md) | Update logs, auto updates, schedule, health, settings, multisite. |
| Updatronix 3000 (Pro) | [fr.md](updatronix-pro/fr.md) | [en.md](updatronix-pro/en.md) | License, Update Shield, Safe mode, Version Lock, Update Flow, white label, exports. |
| WordPress update system | [fr.md](wordpress-update-system/fr.md) | [en.md](wordpress-update-system/en.md) | Core developer reference: discovery, application, automation, hooks, files. |
| Engineering Documentation Library | [fr.md](engineering-documentation-library/fr.md) | [en.md](engineering-documentation-library/en.md) | WordPress plugin and theme development reference. |
| Developer API | [fr.md](developer-api/fr.md) | [en.md](developer-api/en.md) | Updatronix actions, filters, REST routes, public PHP API, data model. |

## How to read this set

- The Free guide is the starting point for a site administrator.
- The Pro guide assumes the Free guide and documents what Updatronix 3000 adds.
- The WordPress update system reference is for developers working on update
  code, not for site administrators.
- The Engineering Documentation Library is the shared WordPress engineering
  reference.
- The Developer API guide is for integrations that call Updatronix hooks,
  filters, REST routes, or public classes.

Each guide has a French and an English file. The language selector at the top of
each file links to the other language.

## Source of truth

- These Markdown files are canonical. holdmywp.com is a mirror.
- Free and Pro facts are verified against `updatronix` 2.0.1 and
  `updatronix-pro` 0.8.1, running on WordPress 7.1.4 and PHP 8.1+.
- The native WordPress reference is verified against the installed core in the
  DDEV site.
- Every UI label, default, capability, hook, and REST route must be traceable to
  the code or to a screenshot. A claim without a source is a defect.

## Working material

Agents do not write working documents in `docs/`. Task files, notes, source
maps, screenshot manifests, and per-document prompts live under `.agents/`.
`docs/images/` holds only the images used by the published guides.

## Images

Store screenshots in `docs/images/<guide>/<language>/<screen>.png` and link them
relatively. Every image needs alt text that says what the reader should notice.
No personal data, no real customer data, no third-party screenshot without a
clear right to use it.
