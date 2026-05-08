# Playwright Harness Runbook

**Last update:** 2026-05-08

The harness files in this directory automate visual + a11y + workflow
testing for every airpay plugin shipped during the 2026-05-07 / 05-08
stretches.

## Files

| File | Purpose |
|---|---|
| `p1_phase_h_a11y_axe.mjs` | Original axe-core harness for dashboard / users / catalog |
| `p1_phase_h_keyboard_nav.mjs` | Keyboard-only navigation walkthrough |
| `p1_phase_d_extended.mjs` | Deep CRUD workflows (WX-01..WX-07) |
| `p2_a11y_2026_05_08.mjs` | **NEW** — axe scan over 13 admin surfaces shipped this stretch |
| `p2_workflows_2026_05_08.mjs` | **NEW** — WX-08..WX-11 deep workflows for the 4 newly-shipped plugins |

## Prerequisites

1. **Local Moodle running on http://localhost:8080/moodle**
   ```powershell
   # XAMPP must be running with Apache + MariaDB.
   # Verify: curl http://localhost:8080/moodle/  → HTTP 200
   ```

2. **Test users present in DB**
   - `academy@airpay.co.in` (siteadmin, id=2) — must have password
     `Airpay@Test2026!` (set via /admin/user.php → Login as → reset)
   - `rasika.thakare@airpay.co.in` (learner, id=3113) — same password
   - Optional: `kunal@airpay.co.in` (manager), `joseph.mandapati@airpay.co.in` (LD admin)

3. **Playwright browser binaries installed**
   ```powershell
   cd moodle-enhancement\audit\playwright
   npx playwright install chromium
   # If DLL errors appear (STATUS_DLL_NOT_FOUND / exit 3221225506),
   # install the full Chromium instead of the headless-shell variant:
   npx playwright install chromium --with-deps
   ```

4. **Output directory exists**
   ```powershell
   New-Item -ItemType Directory -Force "C:\Users\nitin.rajput\airpay_p0\screenshots"
   ```

## Run order

```powershell
cd moodle-enhancement\audit\playwright

# 1. axe-core a11y on the 13 new admin surfaces (~3 min)
node p2_a11y_2026_05_08.mjs

# 2. Deep workflows on the 4 newly-shipped plugins (~5 min)
node p2_workflows_2026_05_08.mjs

# 3. Original baseline (still runs, covers older surfaces)
node p1_phase_h_a11y_axe.mjs
node p1_phase_d_extended.mjs
```

## Output

- `C:\Users\nitin.rajput\airpay_p0\p2_a11y_2026_05_08.json` — per-surface
  axe report with violation counts by impact (critical / serious /
  moderate)
- `C:\Users\nitin.rajput\airpay_p0\p2_workflows_2026_05_08.json` —
  per-workflow case results + console errors + failed network requests
- `C:\Users\nitin.rajput\airpay_p0\screenshots\p2axe_*.png` — full-page
  screenshots per surface (visual baseline)
- `C:\Users\nitin.rajput\airpay_p0\screenshots\p2wf_*.png` — workflow
  step screenshots

## Exit codes

Both harnesses exit:
- `0` — production-ready (zero critical + zero serious axe violations
  for a11y; all workflow cases pass with no console errors / no 5xx)
- `1` — failures detected (see JSON output for specifics)
- `2` — runner error (browser launch failed, etc.)

CI-gateable: failures keep the production branch from merging.

## Known issues

### Playwright headless-shell DLL error (Windows)

If `node p2_*.mjs` exits with `STATUS_DLL_NOT_FOUND` or exit code
`3221225506`:

```
browserType.launch: Target page, context or browser has been closed
[pid=NNNN] <process did exit: exitCode=3221225506, signal=null>
```

This is a Microsoft Visual C++ Runtime dependency missing for the
headless-shell binary. Two fixes:

1. **Install full Chromium** (preferred):
   ```
   npx playwright install chromium --with-deps
   ```
2. **Install MS VC++ Redistributable**:
   <https://aka.ms/vs/17/release/vc_redist.x64.exe>

Then re-run the harness.

### Test users missing on fresh Moodle install

The harness assumes specific usernames. On a fresh local DB without
production data import, you'll need to create them manually OR adapt
the `ADMIN`/`CALLERS` constants at the top of each `.mjs` file to
match your local users.

## What this harness does NOT cover

- **Manual NVDA / VoiceOver pass** — A11Y-2 requires a real screen-reader
  user; axe can't test screen-reader semantics
- **Cross-browser** — Currently chromium-only. Add `webkit`/`firefox`
  contexts in `main()` for Safari + Firefox coverage
- **Mobile viewport** — Currently 1440×900. Add a second pass with
  `viewport: { width: 590, height: 1024 }` for mobile baseline
- **Visual diff** — Screenshots are baseline-only; no automated
  pixel-diff comparison yet. Future: integrate Percy / Applitools or
  Playwright's built-in `expect(...).toHaveScreenshot()`
- **Logical end-to-end as 5 personas** — `p2_workflows` runs as
  siteadmin only. To close the L axis fully, add per-persona harnesses
  using a `personas.mjs` helper module.

## Future extensions (Phase 2 of test infrastructure)

```
p2_visual_diff.mjs      — pixel-diff vs baseline screenshots
p2_persona_walks.mjs    — 5 persona-flavoured deep workflows
p2_load_test.mjs        — concurrent N-user simulation
p2_cross_browser.mjs    — same harness on webkit + firefox
```
