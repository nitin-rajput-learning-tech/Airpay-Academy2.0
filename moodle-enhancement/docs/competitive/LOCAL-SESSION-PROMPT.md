# Local Claude Code session — paste-ready prompt for the XAMPP test pass

The cloud session that built the 11 gap plugins **cannot reach your laptop's XAMPP**.
To run the dynamic test pass, open Claude Code **on your laptop** (CLI or desktop app,
in the `Airpay-Academy2.0` repo) and paste the prompt below. It tells the local session
exactly what to do, in what order, with the guardrails from CLAUDE.md.

Two ways to run the pass:

- **Hands-off / deterministic:** just run the runner —
  `pwsh -File moodle-enhancement\tools\gap-test\Run-GapTests.ps1 -RunTests`
  (deploys, upgrades, purges, runs PHPUnit, writes a timestamped report under
  `moodle-enhancement\tools\gap-test\reports\`).
- **Agentic / fixes issues as it goes:** paste the prompt below into a local Claude Code session.

---

## ▼ COPY FROM HERE ▼

```
You are running on my laptop with access to the local XAMPP Moodle 5.1 instance
(C:\xampp\htdocs\moodle5\public, admin CLI at C:\xampp\htdocs\moodle5\admin\cli,
local URL http://localhost:8080/moodle/). Read CLAUDE.md and
moodle-enhancement/docs/competitive/GAP-BUILD-XAMPP-TEST-RUNBOOK-2026-06-16.md first.

GOAL: run the full local test pass for the 11-gap competitive build on branch
claude/gap-integration, fix what breaks, and report. Nothing touches live.

STEPS:
1. git fetch origin && git checkout claude/gap-integration && git pull origin claude/gap-integration
2. Deploy the gap plugins to the webroot (back up the 3 EXTENDED plugins first):
   NEW:      sentientia_skillsai, sentientia_authoring, sentientia_content_market,
             sentientia_xapi, sentientia_talent, sentientia_api
   EXTENDED: sentientia_learningpath, sentientia_analytics, sentientia_assistant
   (Or just run moodle-enhancement\tools\gap-test\Run-GapTests.ps1 -RunTests, which does
    deploy + upgrade + purge + PHPUnit and writes a report.)
3. Run: php admin\cli\upgrade.php --non-interactive ; then php admin\cli\purge_caches.php
   Confirm 6 new plugins install + 3 upgrade with no errors in the Apache log.
4. Init PHPUnit once (admin\tool\phpunit\cli\init.php) then run each suite:
   vendor\bin\phpunit --filter local_sentientia_<plugin> for all 9 plugins.
   For each failure: read it, fix the plugin code (keep the feature-flag-default-OFF,
   tenant-scoping, mock-mode, en/hi-parity rules from CLAUDE.md), re-run until green.
5. Smoke test as a LEARNER (not admin), desktop + 590px mobile, zero console errors.
   Enable each flag via /local/sentientia_platform/admin/switchboard.php and verify the
   surface listed in the runbook §4. Confirm flag-OFF = byte-identical to today for the
   3 extended plugins.
6. Verify the pre-deploy fixes already applied/needed (runbook §5): the
   sentientia_assistant privacy provider is now a REAL provider (chat_log + agent_audit) —
   confirm the privacy export/delete works for a test user.
7. Commit any fixes to claude/gap-integration with clear messages and push. Take
   screenshots into docs/visual-evidence/<today>/ for any UI surface you touched
   (per CLAUDE.md). Update PROJECT-STATE.md with the test results.

RULES: keep every new feature flag default OFF; AI/TTS stay in mock-mode (no live API
spend) unless I explicitly say otherwise; no live Moodle, no production deploy; $DB API
only; escape all output. Report a per-plugin PASS/FAIL table at the end.
```

## ▲ COPY TO HERE ▲

---

### Notes
- If `php` isn't on PATH, add `C:\xampp\php` to PATH (or call the full path).
- The runner writes everything (upgrade log, per-plugin PHPUnit logs, backups) to a
  timestamped folder so you can diff runs and roll back.
- Dependency order: install `sentientia_skillsai` first so adaptive / talent /
  content-market / analytics exercise the full skills-intelligence path (they degrade
  gracefully if it's absent, but you want the real path tested).
