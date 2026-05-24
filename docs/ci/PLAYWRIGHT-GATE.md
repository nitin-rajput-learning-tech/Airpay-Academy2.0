# PLAYWRIGHT-GATE.md — Airpay Academy / Sentientia LMS

**Owner:** Nitin Rajput · **Workstream:** P2 cutover-prep · **Status:** advisory gate (continue-on-error: true) · **Created:** 2026-05-24

This runbook covers the **Linux-based Playwright CI gate** that runs visual +
functional E2E against a containerised Moodle 5.x + the `airpayux` theme. The
gate lives in:

- Workflow job — `playwright-linux` in `.github/workflows/ci.yml`
- Spec source — `tests/playwright/`
- Baseline screenshots — `tests/playwright/__screenshots__/`
- Local config — `tests/playwright/playwright.config.ts`

It is **not** the same as `moodle-enhancement/audit/playwright/` — that
folder is the audit harness (one-off probes + tier-based UAT scripts).
This gate is the always-on smoke that runs every CI build.

---

## 1. What the gate runs

| Spec | Surface | Asserts |
|------|---------|---------|
| `login.spec.ts` | `/login/index.php` | username + password + submit visible · CSRF `logintoken` present · password input has `type=password` |
| `dashboard.spec.ts` | `/my/` after admin login | login redirect resolves · `#region-main` visible · body class names contain dashboard hint |
| `navbar.spec.ts` | `/login/index.php` | airpayux navbar renders · brand visible · ≥ 1 anchor with non-empty href · navbar has positive height |
| `dark-mode.spec.ts` | `/login/index.php` with `colorScheme: 'dark'` | body background is not a known light token · luminance < 0.5 |
| `mobile-590.spec.ts` | `/login/index.php` viewport 590×900 | no horizontal overflow (`scrollWidth ≤ clientWidth + 2`) · navbar height ≤ 120px · login form fits within viewport |

All 5 specs run against **3 browsers** (chromium, firefox, webkit) — so 15
test executions per CI build at default settings.

Each spec is **happy path only** and under 50 lines.

---

## 2. How to update baselines

The baseline screenshot folder is `tests/playwright/__screenshots__/`. Layout
follows the `snapshotPathTemplate` in `playwright.config.ts`:

```
__screenshots__/<projectName>/<testFile>/<snapshot>.png
```

The 5 specs currently committed do NOT call `toHaveScreenshot()`; baselines
land in this folder only once you opt-in to visual diffing.

### 2.1 Add visual diffing to a spec

```ts
test('navbar visual baseline', async ({ page }) => {
    await page.goto('/login/index.php');
    await expect(page.locator('header')).toHaveScreenshot('navbar.png', {
        maxDiffPixels: 200,           // small tolerance for font rendering
        animations: 'disabled',
    });
});
```

### 2.2 Generate the baseline (first time + after intentional UI change)

Run against a clean local Moodle that matches CI as closely as possible:

```bash
cd tests/playwright
npm ci
npx playwright install --with-deps

# Against XAMPP
PLAYWRIGHT_BASE_URL=http://localhost:8080/moodle npx playwright test --update-snapshots

# Against the docker stack the CI uses (start it via .github/workflows snippet)
PLAYWRIGHT_BASE_URL=http://localhost:8000 npx playwright test --update-snapshots
```

### 2.3 Commit the new baselines

```bash
git add tests/playwright/__screenshots__/
git commit -S -m "test(playwright): refresh baselines after <surface> redesign"
git push
```

**Rule:** every commit that updates baselines must reference the visual
change it follows (an ADR, a Mustache diff, an SCSS chip). Screenshot
churn without a code change tends to be flake — investigate before
committing.

---

## 3. How to debug locally

### 3.1 Mirror the CI stack (closest to truth)

```bash
docker run -d --name moodle-debug \
  -p 8000:8000 \
  -v "$PWD:/var/www/html" \
  moodlehq/moodle-php-apache:8.2
# then install via admin/cli/install_database.php — see .github/workflows/ci.yml
```

Once Moodle is up at `http://localhost:8000/`:

```bash
cd tests/playwright
PLAYWRIGHT_BASE_URL=http://localhost:8000 npx playwright test --headed --workers=1
```

### 3.2 Mirror against XAMPP (fastest dev loop)

```bash
cd tests/playwright
PLAYWRIGHT_BASE_URL=http://localhost:8080/moodle npx playwright test --headed
```

### 3.3 Run a single spec + open trace viewer

```bash
npx playwright test login.spec.ts --project=chromium --headed
npx playwright show-trace test-results/.../trace.zip
npx playwright show-report  # open last HTML report
```

### 3.4 Reproduce a CI failure trace locally

```bash
# Download the artifact from the failed GitHub run → unzip → open trace.zip
unzip playwright-traces-<runid>.zip -d /tmp/pw-fail
npx playwright show-trace /tmp/pw-fail/test-results/.../trace.zip
```

### 3.5 Common local failures + fixes

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| `net::ERR_CONNECTION_REFUSED` | XAMPP / docker not running | Start Apache; re-curl `$PLAYWRIGHT_BASE_URL` |
| `Timeout 30000ms waiting for navigation` | Moodle install incomplete | Re-run `admin/cli/install_database.php`; purge moodledata |
| `webkit.launch: Browser closed` | Missing system deps | `npx playwright install-deps webkit` |
| Snapshot mismatch on every run | Font rendering drift Linux ↔ Windows | Always regenerate baselines on **Linux** (CI parity); never on Windows |
| Dashboard spec fails — login redirects to `/login/index.php` | `PLAYWRIGHT_ADMIN_PASS` mismatch with install | Re-export `PLAYWRIGHT_ADMIN_PASS` to whatever you set during install |

---

## 4. Flaky test skip protocol

While the gate is advisory (`continue-on-error: true` on the job), flaky
specs surface as red but don't block merge. The protocol is the same once
the gate goes blocking:

### 4.1 Mark a spec flaky (24-hour TTL)

```ts
test.fixme('flaky on webkit — see #ISSUE-1234', async ({ page, browserName }) => {
    test.skip(browserName === 'webkit', 'webkit-only flake');
    // ... existing test body
});
```

- `test.fixme()` registers it as a known issue (still reported, won't pass).
- `test.skip()` skips conditionally.
- **Every skip must reference an issue + commit a TODO with a date.**

### 4.2 Quarantine pattern (24h-7d)

For a recurring flake that needs a longer investigation window:

1. Move the spec to `tests/playwright/quarantine/` (gitignored from default
   test run via `testIgnore: '**/quarantine/**'` — add to config when first
   needed).
2. Open a tracking issue with the failed trace attached.
3. Re-introduce within 7 days. Older quarantines → delete or rewrite.

### 4.3 Graduating the gate from advisory to blocking

Checklist before flipping `continue-on-error: true` → removing it:

- [ ] 5 consecutive green `playwright-linux` runs on `production`
- [ ] Visual baselines committed for at least 2 surfaces (`login`, `navbar`)
- [ ] Mean job duration < 12 minutes
- [ ] Flake rate < 5 % over a rolling 20-run window
- [ ] PROJECT-STATE.md entry recording the graduation, with the run-IDs

When the boxes are checked, delete `continue-on-error: true` from
`.github/workflows/ci.yml`, add a `concurrency:` group to deduplicate
re-runs, and announce in the team channel.

---

## 5. Architecture decisions captured here

- **Why moodlehq/moodle-php-apache:8.2 + sidecar MariaDB** — the upstream
  Moodle CI image is the closest match to what production runs (PHP 8.2,
  same Apache config). We provision MariaDB 10.11 as a service to match
  Airpay's RDS engine family (MySQL 8.0.44 / MariaDB 10.11 wire-compatible).
- **Why `network=host`** — avoids docker DNS noise between the test runner
  and the webserver container, and lets Playwright reach `localhost:8000`
  the same way it does in dev.
- **Why three browsers** — chromium catches blink-only bugs (the majority
  of Airpay users), firefox catches gecko regressions, webkit catches
  Safari + iOS bugs ahead of the PWA wrapper phase. Cost: ~3× test time.
- **Why specs at folder root, not `specs/`** — Playwright convention plus
  the user-facing instruction in the chip spec. The `testMatch: '*.spec.ts'`
  scan stays simple.
- **Why `snapshotPathTemplate: '__screenshots__/{projectName}/...'`** —
  groups baselines by browser so a Linux/macOS rendering drift on webkit
  only affects the webkit baselines and a chromium-only regression is
  obvious from the file path.

---

## 6. Cross-references

- `tests/playwright/README.md` — quick-start (developer-facing)
- `tests/playwright/__screenshots__/README.md` — baseline folder layout
- `moodle-enhancement/audit/playwright/HARNESS_RUNBOOK.md` — sibling
  audit harness (one-off probes, NOT this gate)
- `.github/workflows/ci.yml` — `playwright-linux` job definition
- `CLAUDE.md` §4 (Workflow rules) — "every UI-touching session ends with
  visual evidence" — the CI gate is one of two evidence channels (the
  other is `docs/visual-evidence/YYYY-MM-DD/` per-session screenshots).
