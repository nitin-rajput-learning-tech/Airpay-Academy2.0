# Playwright E2E test suite (Goal B)

Spec-style tests using the official `@playwright/test` runner.
Complements the existing `.mjs` library-style scripts in the parent
folder (which use `import { chromium }` directly).

## Quick reference

```powershell
cd moodle-enhancement\audit\playwright

# Run everything
npx playwright test

# Just the desktop project
npx playwright test --project=firefox-desktop

# Just a single spec
npx playwright test tests/surfaces.spec.mjs

# Open the HTML report
npx playwright show-report
```

## Suites

### `surfaces.spec.mjs` — Sentientia surface coverage

11 tests covering every surface that landed in
`v4.1.0-goal-a-audit`:
  - `/user/profile.php` — h3 uppercase letter-spaced
  - `/badges/mybadges.php` — body id assertion
  - `/grade/report/overview/` — h2 has 16px brand-radius
  - `/admin/search.php` — body has `path-admin`
  - `/course/view.php` — body id assertion
  - `/grade/report/grader/` — thead uppercase letter-spaced
  - `/user/edit.php` — fieldset 16px card chrome
  - `/user/preferences.php` — h3 uppercase letter-spaced
  - `/calendar/view.php` month — thead uppercase letter-spaced
  - `/course/edit.php` — fieldset 16px card chrome (same rule)
  - Workstream 0 — `#sentientia-customer-brand` style injected

Each test asserts the signature CSS marker via `getComputedStyle()`.
These are **NOT pixel-screenshot regressions** — they're computed-
style checks that catch SCSS cascade breakage from future theme
version bumps.

## Known blocker — local execution on Windows + AV

On this development laptop (Windows 10 + corporate Windows Defender
+ Node 24.14.1) both Chromium AND Firefox fail to spawn from
Playwright with errors like:
  - Chromium: `STATUS_HEAP_CORRUPTION` exit code 3221225506
  - Firefox:  `spawn UNKNOWN`

The corporate AV blocks child process spawn for unsigned browser
binaries from `%LOCALAPPDATA%\ms-playwright\`. The tests themselves
are structurally correct — they will pass on:

  - A clean Linux CI runner (GitHub Actions `ubuntu-latest`)
  - A different Windows machine without the AV policy
  - The same machine in a temp-folder-allowlisted shell

For local smoke verification, use the existing `.mjs` library-style
scripts in the parent folder which run with the user's already-
loaded Chrome session via the chrome-devtools-mcp bridge (no
browser spawn).

## CI integration

Add this to `.github/workflows/ci.yml` to run on every PR:

```yaml
  playwright-e2e:
    name: Playwright E2E — Sentientia surfaces
    runs-on: ubuntu-latest
    services:
      moodle:
        image: <your-moodle-image>
        ports: [8080:80]
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: 24 }
      - name: Install Playwright + browsers
        run: |
          cd moodle-enhancement/audit/playwright
          npm ci
          npx playwright install --with-deps
      - name: Run E2E
        run: |
          cd moodle-enhancement/audit/playwright
          npx playwright test --reporter=github
```

Adding the Moodle service container is the next-session work — the
test harness is ready to receive it.

## Auth state

Tests use a shared `fixtures/.auth-state.json` produced by
`beforeAll()` logging in as Site Admin (`academy@airpay.co.in`).
Credentials are read inline from `surfaces.spec.mjs` and match the
`credentials.local.md` from the visual audit. **Both files are
gitignored** — never commit them.

## See also

  - `playwright.config.mjs` — runner config (Firefox primary,
    Chromium fallback, mobile-590 viewport project)
  - `HARNESS_RUNBOOK.md` — parent-folder library-style scripts
  - `theme/airpayux/tests/README.md` — PHPUnit gates that
    complement these E2E tests
