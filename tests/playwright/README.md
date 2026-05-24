# Playwright Gate — Airpay Academy / Sentientia LMS

Linux-based Playwright E2E + visual scaffold that runs in the
`playwright-linux` job of `.github/workflows/ci.yml` and locally
against XAMPP or a docker'd Moodle.

## Quick start

```bash
cd tests/playwright
npm ci
npx playwright install --with-deps

# Against local XAMPP (most common dev loop)
PLAYWRIGHT_BASE_URL=http://localhost:8080/moodle npx playwright test

# Against CI-style docker stack on default port
PLAYWRIGHT_BASE_URL=http://localhost:8000 npx playwright test

# Single project
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit
```

## Layout

```
playwright.config.ts          chromium / firefox / webkit projects
tsconfig.json                 TS strict, no-emit (Playwright handles transpile)
package.json                  pinned @playwright/test ^1.49 + npm scripts
login.spec.ts                 login form happy path
dashboard.spec.ts             post-login /my/ landing
navbar.spec.ts                airpayux navbar render
dark-mode.spec.ts             prefers-color-scheme: dark applies
mobile-590.spec.ts            primary mobile breakpoint, no overflow
__screenshots__/              baseline images (see __screenshots__/README.md)
```

## Spec design

Specs are **happy path + functional assertions only**. Each is under
50 lines and avoids `toHaveScreenshot()` until the gate transitions
from advisory to blocking — see `docs/ci/PLAYWRIGHT-GATE.md` §2.

## Reports + artefacts

- HTML report → `playwright-report/index.html` (run `npm run report`)
- JUnit XML → `test-results/junit.xml` (CI only)
- Traces (failure only) → `test-results/<test>/trace.zip` — uploaded by CI

See `docs/ci/PLAYWRIGHT-GATE.md` for debugging, baseline refresh, and
the flaky-skip protocol.
