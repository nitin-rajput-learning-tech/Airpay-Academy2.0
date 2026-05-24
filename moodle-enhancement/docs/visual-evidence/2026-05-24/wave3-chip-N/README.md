# P1 #17 + P2 #23 follow-up — wave3-chip-N (2026-05-24)

**Chip:** `claude/keen-galileo-AAnnn`
**Auditor:** Claude Opus 4.7 (1M context)
**Closes:** F-14 (P1 #17) + F-15 (P2 #23) from
`docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`
**Scope owner:** Dashboard chart surface — Chart.js vendoring + canvas
accessibility
**Plugin version:** `1.0.35-beta` (2026052404)

---

## Commits

```
27f28ed6  feat(theme): AMD-wrap Chart.js loader (P1 #17 / F-14)
5ee60488  feat(a11y): aria-label + sr-only data table on dashboard charts (P2 #23 / F-15)
????????  chore(theme): bump theme_airpayux to 1.0.35-beta + wave3-chip-N evidence
```

---

## Item 1 — Chart.js AMD-loader (F-14 / P1 #17)

### Strategy chosen: (b) AMD wrap → `core/chartjs`

The audit listed two options:
- (a) Vendor `chart.umd.min.js` as a static asset under
  `theme/airpayux/javascript/` and swap the `<script src>` for a local
  path.
- (b) Create `theme/airpayux/amd/src/chart_loader.js` (or
  `local/airpay_analytics/amd/src/chart_loader.js`) that exposes Chart
  via Moodle's AMD `define()` machinery; wire via
  `$PAGE->requires->js_call_amd()`.

This chip picked (b). Justification:
1. Moodle 5.x already vendors Chart.js v4.4.2 as the `core/chartjs`
   AMD module (`lib/amd/src/chartjs.js` → `lib/amd/src/chartjs-lazy.js`),
   with the upstream MIT licence already recorded in core's
   `thirdpartylibs.xml`. Re-vendoring our own copy would duplicate
   ~250 KB of JS in the theme bundle and drift independently of core
   upgrades.
2. A small theme-scoped wrapper module (`theme_airpayux/chart_loader`)
   gives us a documented seam where future theming defaults (colour
   palettes, font sizes, plugin registration for `ChartDataLabels`,
   etc.) can land without touching every consuming page.
3. The build chain currently has no grunt available; hand-minifying
   a ~250 KB Chart.js bundle is not practical. Delegating to the
   core module sidesteps the issue.

### Files changed

| File | Change |
|------|--------|
| `theme/airpayux/amd/src/chart_loader.js` | NEW — `define(['core/chartjs'], …)` wrapper, exposes `window.Chart` |
| `theme/airpayux/amd/build/chart_loader.min.js` | NEW — hand-minified mirror (Chip B `cart_badge` pattern) |
| `theme/airpayux/templates/dashboard.mustache` | REMOVED `<script src="https://cdn.jsdelivr.net/…">`; WRAPPED inline init in `require(['theme_airpayux/chart_loader'], …)` |
| `theme/airpayux/layout/dashboard.php` | ADDED `$PAGE->requires->js_call_amd('theme_airpayux/chart_loader', 'init')` next to the existing `cart_badge` wiring |

### Chart configuration left alone

The chart definitions (bar chart for enrolment trend, doughnut for
course distribution — types, data sources, colour arrays, options
hashes) are byte-identical to the pre-chip code. Only the
dependency-loader strategy flipped. This was an explicit scope rule
in the chip brief: "leave the chart-rendering logic alone, just make
Chart available to it via your chosen strategy".

### Post-deploy verification procedure

```powershell
# 1. Copy the build artefacts to XAMPP
Copy-Item -Force `
    "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\amd\src\chart_loader.js" `
    "C:\xampp\htdocs\moodle5\public\theme\airpayux\amd\src\chart_loader.js"
Copy-Item -Force `
    "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\amd\build\chart_loader.min.js" `
    "C:\xampp\htdocs\moodle5\public\theme\airpayux\amd\build\chart_loader.min.js"
Copy-Item -Force `
    "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\templates\dashboard.mustache" `
    "C:\xampp\htdocs\moodle5\public\theme\airpayux\templates\dashboard.mustache"
Copy-Item -Force `
    "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\layout\dashboard.php" `
    "C:\xampp\htdocs\moodle5\public\theme\airpayux\layout\dashboard.php"
Copy-Item -Force `
    "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\version.php" `
    "C:\xampp\htdocs\moodle5\public\theme\airpayux\version.php"

# 2. Bump version, purge cache
cd C:\xampp\htdocs\moodle5\public
php admin\cli\upgrade.php --non-interactive --allow-unstable
php admin\cli\purge_caches.php
```

Test plan (admin / L&D-admin account on local XAMPP):

1. **Online network — golden path**
   - Open `http://localhost:8080/moodle/my/dashboard.php`
   - Verify both charts render (Enrolment Trend bar chart left,
     Course Distribution doughnut right).
   - Browser devtools Network tab — confirm there is **no** request
     to `cdn.jsdelivr.net`. Confirm `lib/amd/build/chartjs-lazy.min.js`
     IS requested via the AMD loader chain.
   - Browser devtools Console tab — zero errors.

2. **Offline / restricted-network simulation**
   - Open Chrome DevTools → Network → block URL pattern
     `*://cdn.jsdelivr.net/*`.
   - Hard-reload the dashboard (Ctrl+Shift+R).
   - **Pre-chip behaviour:** chart canvases stay blank, "Chart is not
     defined" reference error in console.
   - **Post-chip expected:** both charts still render — `core/chartjs`
     ships with Moodle locally, no external dependency.

3. **CSP-strict simulation**
   - In Chrome DevTools → Application → enforce
     `Content-Security-Policy: script-src 'self'`.
   - Reload dashboard. Verify both charts render (the inline
     `<script>` is still inline; CSP-strict-no-unsafe-inline is a
     deeper refactor that lives outside this chip's scope — see
     audit §3.4 footnote).

4. **Screen-reader smoke (NVDA on Windows)**
   - Tab through the dashboard. When focus reaches each canvas, NVDA
     should announce *"Enrolment Trend, image"* and *"Course
     Distribution, image"* (the aria-labelledby resolves to the
     section <h3>).
   - Continue tabbing — focus lands on the `<details>` summary,
     announced as *"Enrolment Trend, button collapsed"*. Press Enter
     to expand; the table contents become navigable cell-by-cell.

5. **Mobile viewport regression check (590 px)**
   - Chrome DevTools → device toolbar → iPhone SE.
   - Both charts must keep responsive scaling (Chart.js' built-in
     `responsive: true` is unchanged).
   - The `<details>` block stacks below each canvas; default browser
     styling keeps it visually unobtrusive.

---

## Item 2 — Chart canvas a11y (F-15 / P2 #23)

### What changed

Both `<canvas>` elements in `templates/dashboard.mustache` now carry:

```html
<canvas id="airpay-chart-enrolments"
        height="220"
        role="img"
        aria-labelledby="airpay-chart-enrolments-title"
        aria-describedby="airpay-chart-enrolments-data"></canvas>
```

…and are followed by a `<details>` disclosure containing a `<table>`
with the same numbers Chart.js paints. The `<summary>` and `<caption>`
elements are wrapped in `.sr-only` so the disclosure surfaces only via
screen-reader / keyboard navigation — sighted users still see the
chart, no visual duplication.

`id="airpay-chart-{enrolments,distribution}-title"` was added to each
section `<h3>` so the aria-labelledby has a real anchor element rather
than a duplicated inline `aria-label` string.

### Lang-string discipline

Per chip scope, **no new theme_airpayux lang strings were added**.
The aria-label / summary / caption all reuse the existing
`chart_enrolment_trend` and `chart_course_distribution` keys that
Chip G shipped across en/hi/kn/mr/sw. Column headers use Moodle core
lang keys:

| Key | Component | Used as |
|-----|-----------|---------|
| `chart_enrolment_trend` | `theme_airpayux` | bar-chart aria-label + summary + caption |
| `chart_course_distribution` | `theme_airpayux` | doughnut aria-label + summary + caption |
| `month` | `core` | column header (bar chart) |
| `total` | `core` | column header (bar chart) |
| `category` | `core` | column header (doughnut) |
| `courses` | `core` | column header (doughnut) |

All four core keys are localised in every Moodle locale we ship.

### Data plumbing

`layout/dashboard.php` already json_encode'd the chart series for
Chart.js consumption. This chip adds two **iterable mirrors** —
`chart_enrolments_table` and `chart_distribution_table` — populated
from the same source arrays in the same loop. The table and the
chart cannot diverge by accident because they read identical PHP
state.

### Post-deploy verification procedure

1. **NVDA — canvas announcement**
   - Tab to the enrolment-trend canvas. NVDA announces
     *"Enrolment Trend, image. Enrolment Trend"* (the second clause
     is the aria-describedby pointing at the disclosure).
   - Tab to the doughnut canvas — same pattern, with "Course
     Distribution".

2. **NVDA — table navigation**
   - Tab past each canvas to land on the `<details>` summary. Press
     Enter to expand. NVDA enters table mode (Ctrl+Alt+arrows).
   - Verify column headers announce as *"Month, column header"* /
     *"Total, column header"* and row headers as the month label.
   - Verify the underlying numbers match the canvas bars / doughnut
     slices (cross-check against a screenshot of the same dashboard).

3. **Keyboard-only**
   - Use Tab to walk from the canvas → into the disclosure summary
     → press Enter / Space to toggle. The disclosure must open AND
     close on keyboard input alone (this is default `<details>`
     behaviour; we did not override it).

4. **Sighted-user regression check**
   - The visible page must look identical to pre-chip (the `<details>`
     element has default styling but the wrapper `<summary>` is
     `.sr-only`, so the disclosure widget is invisible at the
     pixel level).
   - Hard-reload (Ctrl+Shift+R) after deploying — Mustache template
     caches were purged in the deploy step.

---

## Safety + parity

- ✅ `php -l` clean on both `layout/dashboard.php` and `version.php`.
- ✅ Node syntax check clean on both `amd/src/chart_loader.js` and
  `amd/build/chart_loader.min.js`.
- ✅ Mustache template — zero new triple-stash `{{{ }}}` introduced;
  all existing triple-stash uses are renderer-produced HTML or
  config URLs (audited in §2.5 of the platform visual audit).
- ✅ NO new theme_airpayux lang strings (per chip scope).
- ✅ NO SCSS touched (per chip scope — `.sr-only` is provided by
  upstream Bootstrap utilities already imported into the theme).
- ✅ Chart configuration logic byte-identical to pre-chip code modulo
  the require() wrapper.
- ✅ Single plugin version bump (1.0.33-beta → 1.0.35-beta) covers
  both items.
- ✅ Cache key bumped → next page request re-compiles SCSS and
  re-bundles JS.

---

## Conflict notes

This chip touches:
- `theme/airpayux/amd/src/chart_loader.js` (NEW)
- `theme/airpayux/amd/build/chart_loader.min.js` (NEW)
- `theme/airpayux/templates/dashboard.mustache` (chart canvas section
  + inline init block)
- `theme/airpayux/layout/dashboard.php` (chart series array + AMD
  pre-warm)
- `theme/airpayux/version.php` (one bump)

No other Wave-3 chips overlap on these files at the time of this
commit (chips B, C, E, G, H, I, J, K, L, M complete). Should a future
chip touch the same canvas / chart-init region, the merge resolution
is to keep the role=img + aria-* attributes from this chip.

---

## Refs

- Audit report: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`
  §3.4 (Dashboard) — findings F-14 and F-15.
- Audit punch list: P1 #17 (Chart.js CDN) + P2 #23 (canvas a11y).
- Frontend rules: `.claude/rules/frontend.md` §Moodle JS / AMD
  discipline; §Mustache correctness.
- Prior art (AMD pattern): `theme/airpayux/amd/src/cart_badge.js`
  shipped by P0 follow-up chip B (2026-05-24).
- Moodle core Chart.js: `lib/amd/src/chartjs.js` →
  `lib/amd/src/chartjs-lazy.js` (Chart.js v4.4.2, MIT, vendored by
  Moodle 5.x).
