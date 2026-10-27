# Vocabulary pass — Batch B

**Date:** 2026-10-27
**Scope:** Unclaimed Free screens and Pro Flow/License/White Label copy.
**Status:** Applied; `.pot` files regenerated; no version bump and no commit.
The Shield screen and docs 51/34/11/45 remain untouched while their write claim
is active.

## What changed

| File | Before | After |
|---|---|---|
| `SettingsPanel.js` | “Debug summary” | “Developer details” |
| `SettingsPanel.js` | “Update history.” | “Keep a record of each update.” |
| `AutoUpdatesPanel.js` | “Per-theme control.” | “Choose how each theme updates.” |
| `AutoUpdatesPanel.js` | “Per-plugin control.” | “Choose how each plugin updates.” |
| `SchedulePanel.js` | “WordPress cron.” | “WordPress runs update checks on its own schedule.” |
| `SchedulePanel.js` | “Cron is shared” | “WP-Cron runs other tasks too” |
| `SchedulePanel.js` | “What the hold does” | “What the hold period does” |
| `HealthPage.js` | “Open issues” | “Issues to review” |
| `HealthPage.js` | “Recently resolved” | “Recently fixed” |
| `DocumentationPage.js` | “Visit Support” | “Go to Support” |
| `DocumentationPage.js` | “Read guide” | “Read the guide” |
| `SupportPage.js` | “Bring the evidence” | “Include the details” |
| `SupportPage.js` | “Before you post” | “Before you ask” |
| `ExportLogsModal.js` | “Export update log” | “Export update logs” |
| `ExportLogsModal.js` | “Report columns” | “Columns” |
| `ExportLogsModal.js` | “Export output” | “Preview” |
| `LogDetailsContent.js` | “Latest changelog · readme.txt” | “Latest changelog” |
| `LogDetailsContent.js` | “Process details” | “Update details” |
| `LogDetailsContent.js` | “Advanced details” | “Technical details” |
| `UpdateFlowPage.js` | “The chain could not be started.” | “The update chain could not be started.” |
| `UpdateFlowPage.js` | “Chain renamed/updated/added/removed. Save to apply it.” | “… Save your changes to apply it.” |
| `UpdateFlowPage.js` | “Loop found” / “No loops” | “Dependency loop found” / “No dependency loops” |
| `UpdateFlowPage.js` | “No chains yet” | “No update chains yet” |
| `UpdateFlowPage.js` | “Give the chain a name.” | “Enter a name for the chain.” |
| `UpdateFlowPage.js` | “Add at least two steps.” | “Add at least two steps to the chain.” |
| `UpdateFlowPage.js` | “New chain” | “New update chain” |
| `UpdateFlowPage.js` | “Add step” | “Add a step” |
| `LicensePanel.js` | “Queue list” | “Update queue” |
| `LicensePanel.js` | “Development tools” | “Developer tools” |
| `LicensePanel.js` | “What Updatronix 3000 adds” | “What Updatronix 3000 includes” |
| `LicensePanel.js` | “Clear stored license” | “Remove stored license” |
| `LicensePanel.js` | “Stored license cleared.” | “Stored license removed.” |
| `LicensePanel.js` | “Clearing…” | “Removing…” |
| `WhiteLabelPage.js` | “Replace Updatronix” | “Replace Updatronix branding” |
| `WhiteLabelPage.js` | “Branding reset. Save to apply it.” | “Branding reset. Save your changes to apply it.” |

## What did not change

- Shield screen copy and state helpers: owned by the active 5.1 session.
- Docs 51/34/11/45: owned by the active session; the 5.1 draft still has stale
  348/118 counts and the Composer-lock claim. Reconcile when the claim clears.
- Status labels in PHP export, “Canceled/Cancelled” consistency, and the
  glossary-only Batch A items. Those need a separate pass and test updates.
- No version bump, no commit.

## Verification

- `bin/harness pot` for Free and Pro.
- Free and Pro test, JS, lint, and build suites.
- `git diff --check` on both repos.

The `.pot` files are the source of truth after the pass; every changed string is
listed in the readme `Unreleased` bullet.
