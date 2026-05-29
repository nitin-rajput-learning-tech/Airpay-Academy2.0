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
dashboard.spec.ts             post-login /my/ landing (admin)
navbar.spec.ts                airpayux navbar render
dark-mode.spec.ts             prefers-color-scheme: dark applies
mobile-590.spec.ts            primary mobile breakpoint, no overflow
persona-helpers.ts            shared login + assertions (NOT a spec)
learner.spec.ts               learner: dashboard + catalog (F-033)
manager.spec.ts               manager: dashboard + approve (F-033)
compliance.spec.ts            compliance: dashboard + compliance area (F-033)
author.spec.ts                author: dashboard + create-course (F-033)
__screenshots__/              baseline images (see __screenshots__/README.md)
```

## Persona journeys (F-033)

The four persona specs add an authenticated regression net beyond the
admin-only `dashboard.spec.ts`. Each runs a real login + `/my/`
dashboard smoke (catches "this persona can't log in" / "dashboard
crashes for this persona" / "broken post-login redirect"), plus
persona-landmark or `fixme`-staged deep-journey tests.

**Credentials are env-var driven (never hardcoded).** A spec's tests
**skip cleanly** when its vars are unset, so the gate stays green until
per-persona accounts are provisioned:

| Persona | Env vars | Real tests today |
|---------|----------|------------------|
| Learner | `PLAYWRIGHT_LEARNER_USER` / `_PASS` | dashboard smoke + catalog reachability |
| Manager | `PLAYWRIGHT_MANAGER_USER` / `_PASS` | dashboard smoke |
| Compliance | `PLAYWRIGHT_COMPLIANCE_USER` / `_PASS` | dashboard smoke |
| Author | `PLAYWRIGHT_AUTHOR_USER` / `_PASS` | dashboard smoke |

Deeper mutating journeys (enrol, approve, compliance-sidebar reach,
create-course) are staged as `test.fixme()` with the intended steps in
comments — they need run-to-green fixtures (seeded free course, pending
request, sandbox category + cleanup). Provision accounts and run, e.g.:

```bash
export PLAYWRIGHT_LEARNER_USER=demo.learner PLAYWRIGHT_LEARNER_PASS='…'
PLAYWRIGHT_BASE_URL=http://localhost:8080/moodle npx playwright test learner.spec.ts
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
