# Audit Runner — Goal A + Goal B harness

Playwright-driven walker that logs into Airpay Academy as each of the
9 personas (from Section 10 of the May-12 master doc) and captures
desktop + mobile-590px screenshots of every page surface in that
persona's feature set.

Outputs land in `../moodle-enhancement/docs/visual-audit-2026-05-22/`,
bucketed per persona, where the docs site (`../docs-site/`) references
them inline.

## One-time setup

```
npm install
npx playwright install chromium
```

## Per-persona walk

Each persona has its own walk script — they only differ in login
credentials and the per-persona surface list (drawn from Section 10
of the master doc).

```
node walk-learner.mjs
node walk-manager.mjs
node walk-ld-administrator.mjs
... etc
```

Credentials come from `../moodle-enhancement/docs/visual-audit-2026-05-22/credentials.local.md`
(gitignored). The scripts hard-code the values today; future iterations
should read them from a `.env` so the scripts can ship publicly with
credentials externalised.

## Why Playwright, not the Chrome MCP

Two reasons:

1. The MCP Chrome profile conflicts with the user's daily-driver Chrome
   (`The browser is already running for ...chrome-devtools-mcp\chrome-profile`)
   which means we can't drive both at once.
2. Playwright is repeatable — these scripts run on every PR if/when we
   wire them into CI, catching UI regressions automatically. The audit
   methodology README explicitly calls this out as Option 2 for the
   audit's second pass (after Goal A.x ships).

## What's NOT in here

- **Visual grading.** The screenshots come out of these scripts;
  grading (🟢🟡🟠🔴) is a human task captured in
  `../moodle-enhancement/docs/visual-audit-2026-05-22/AUDIT-REPORT.md`.
- **E2E click-through assertions (Goal B).** Those will live in
  per-feature `test/*.spec.mjs` files using `@playwright/test`'s
  `expect()` assertions, separately from these screenshot walks.
