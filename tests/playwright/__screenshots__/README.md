# Baseline screenshots — Airpay Academy Playwright gate

Snapshot images committed under this folder are the **source of truth** for
Playwright's `toHaveScreenshot()` visual diffing. Layout is:

```
__screenshots__/
  <projectName>/                    e.g. chromium, firefox, webkit
    <testFilePath>/                 e.g. login.spec.ts/
      <snapshot-name>.png
```

Driven by `snapshotPathTemplate` in `../playwright.config.ts`.

## Regenerating baselines

```bash
cd tests/playwright
npm ci
npx playwright install --with-deps
PLAYWRIGHT_BASE_URL=http://localhost:8000 npx playwright test --update-snapshots
git add __screenshots__/
git commit -m "test(playwright): refresh visual baselines"
```

The 5 baseline specs in this gate (login, dashboard, navbar, dark-mode,
mobile-590) currently use functional assertions only — they do not call
`toHaveScreenshot()`. Add visual diffing surface-by-surface as the gate
graduates from advisory to blocking.

See `../../docs/ci/PLAYWRIGHT-GATE.md` for the full update / debug
runbook (especially **"How to update baselines"** §2).
