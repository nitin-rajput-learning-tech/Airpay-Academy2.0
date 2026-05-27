# Platform Visual Audit — 2026-05-25 (Wave B2 P1 re-audit)

**Date:** 2026-05-25
**Auditor:** Claude (Opus 4.7, 1M context) — static, code-level re-verification
**Scope:** Sentientia LMS / Airpay Academy on `production` tip `65c35b9a`, i.e. the
state of the tree *after* the 21-chip wave that landed between the 2026-05-24
backfill commit `8ac71879` and current HEAD.
**Predecessor:** `PLATFORM-VISUAL-AUDIT-2026-05-24.md` (25 findings, F-01…F-25; verdict
CONDITIONAL PASS).
**Purpose of this pass (4 goals):**
  1. Verify each F-01…F-25 closure is present in code + functionally wired.
  2. Find regressions introduced by the chip wave.
  3. Identify NEW findings now that the obvious issues are gone.
  4. Re-rank P0/P1/P2 for the next wave.

**Methodology — IMPORTANT (honesty note):** This re-audit was executed from the
Claude-Code-on-the-web **Linux cloud container**, which has **no network path to
the XAMPP instance on `localhost:8080/moodle`** (connection refused — XAMPP runs
on Nitin's Windows host, not in this container) and **no access to the XAMPP
`error.log`, persona credentials, or a browser that can reach the LMS**.
Verification is therefore **static + git-diff based**: every closure is confirmed
by reading the actual code the chip wave merged (templates, SCSS, layout PHP,
renderer traits, AMD modules, lang files) and by `git` archaeology of the chip
commits. **No live screenshots, no persona walk, no console/error-log capture were
possible from here.** The 10-surface × 5-persona browser walk requested in the
task is specified as a runnable local harness in
`docs/visual-evidence/2026-05-25/audit-walk/VISUAL-WALK-CHECKLIST.md` — run it on
the Windows host with Chrome to populate the PNGs. Appendix B lists the exact drop
paths; until then those cells read **"pending local capture."**

**Surfaces re-verified (code level):** Navbar, Footer, Login, Dashboard (admin /
manager / learner branches), Course view, Profile / User, Badges, Grade-report,
Calendar, plus plugins `sentientia_live` and `sentientia_pwa`. The task's
`message` and `course-catalog` surfaces are addressed in §2.11 / N-06.

---

## TL;DR / Verdict

**PASS — zero open P0. Promote to Phase 2 customer-zero.**

The 21-chip wave did what it set out to do. Of the 25 prior findings, **21 are
fully CLOSED**, **2 are PARTIAL** (improved, residual debt), **1 remains OPEN**
(F-08, a P2 cosmetic), and **1 was always a process note** (F-22, never a code
defect). **All nine P0 findings are closed**, so the single blocking condition of
the 2026-05-24 audit ("close the 9-item P0 list before promotion") is satisfied.

The cross-cutting wins are real and measurable, not cosmetic:

| Metric | 2026-05-24 | 2026-05-25 | Δ |
|---|---|---|---|
| Open P0 findings | 9 | **0** | −9 |
| `dark_mode.scss` active `!important` | 253 | **56** | −77.9% |
| `_surface-login.scss` active `!important` | 66 | **11** | −83.3% |
| `_moodle-overrides.scss` active `!important` | 136 | **39** | −71.3% |
| Monolithic `_surface-profile.scss` (lines) | 2,507 | **0 (split into 4)** | decomposed |
| `:focus-visible` selectors in surface partials | 0 | **18 (1:1 paired w/ `:focus`)** | +18 |
| Locale parity (hi / kn / mr / sw vs en) | 85 / 76 / 96 / 96 % | **100 / 100 / 100 / 100 %** | +parity |
| Orphan source files in `scss/` | 2 (`Claude`, `MONOLITH_BACKUP`) | **0** | deleted |
| `aria-live` regions in `sentientia_live` | 0 | **2 + sr-only feedback** | +a11y |
| Chart.js delivery | external CDN | **`theme_airpayux/chart_loader` AMD** | vendored |
| Conflict markers in tracked tree (CI gate) | n/a | **0** | clean |

**This pass surfaces 6 NEW findings** (N-01…N-06) and **2 carry-forward** items
(F-08, F-09). None is a P0. Two are P1 — both are *incomplete-closure* findings
(the wave closed the headline instance of a class but left a sibling instance):

- **N-01 [P1]** — `navbar.mustache:165` still carries an inline `<script>` (the
  mobile-nav active-state highlighter). Chip-B extracted the *cart-badge* IIFE to
  AMD (F-02) but left this sibling script. It is the **same CSP-hostile
  anti-pattern** F-02 named; the prior audit simply didn't grep for the second
  block. (Confirmed pre-existing via `git show production@{2026-05-23}` — 2
  `<script>` blocks then, 1 now.)
- **N-02 [P1]** — `dashboard.mustache` still hardcodes ~10 user-visible English
  strings (`Dark Mode`, `System Health`, the seven manager team-table column
  headers, `Continue Learning`). Chip-G localized the welcome / chart / KPI
  strings (F-13) but not these secondary labels, so a Hindi-preferring admin /
  manager still sees English chrome — the same CLAUDE.md 100%-parity mandate F-13
  was raised under.

**Verdict:** Promote. Schedule N-01 + N-02 as the P1 head of the next wave; the
remaining four new findings and two carry-forwards are P2/P3 polish.

| # | Sev | Surface | New finding (this pass) |
|---|---|---|---|
| N-01 | **P1** | Navbar | Inline `<script>` mobile-nav highlighter — extract to AMD (CSP) |
| N-02 | **P1** | Dashboard | ~10 hardcoded English strings i18n pass missed |
| N-03 | P2 | Dashboard | Chart palette hardcoded hex in `{{#js}}` — not token/theme-aware |
| N-04 | P2 | User surface | `_surface-user.scss` 69 active `!important` — top offender post-split |
| N-05 | P2 | Course | 2nd `coursebannerimage` instance (drawer) lacks F-20 security comment |
| N-06 | P3 | Messaging | No `_surface-message.scss` — messaging un-themed vs design system |
| F-08 | P2 | Footer | (carry-forward) `alt="airpay academy"` still hardcoded |
| F-09 | P2 | Footer | (carry-forward) historical-context comment bloat persists in new form |

---

## §2 — Cross-Cutting Findings

### §2.1 — Closure Scorecard (the 25 prior findings)

Each row verified by reading the post-wave code at the cited path. "Evidence"
gives the grep/line proof; "Chip" gives the merge that closed it.

| F | Sev | Status | Chip | Evidence (post-wave) |
|---|---|---|---|---|
| **F-01** | P0 | ✅ CLOSED | chip-B (`a5de1a1b`) | `navbar.mustache` 0 literal nav labels; 13 `{{#str}}` calls (`nav_profile` etc.) |
| **F-02** | P0 | ✅ CLOSED | chip-B (`9a5436b4`) | cart-badge IIFE → `theme_airpayux/cart_badge` AMD; *but* sibling script → **N-01** |
| **F-03** | P1 | ✅ CLOSED | chip-H/249 | `_surface-navbar.scss` 1 `:focus` / 1 `:focus-visible` paired |
| **F-04** | P2 | ✅ CLOSED | chip-B | `navbar.mustache` 0 `display:none` inline styles |
| **F-05** | P0 | ✅ CLOSED | chip-B | `footer.mustache:27-32` 4 link labels + copyright via `{{#str}}` |
| **F-06** | P0 | ✅ CLOSED | chip-B | `footer.mustache:45-49` attribution band → BEM classes, 0 inline `style=` |
| **F-07** | P1 | ✅ CLOSED | chip-L | `_surface-footer.scss` 2 `@media` breakpoints |
| **F-08** | P2 | ❌ **OPEN** | — | `footer.mustache:24` `alt="airpay academy"` still hardcoded (never scheduled — not in 2026-05-24 Appendix C) |
| **F-09** | P2 | ◑ PARTIAL | chip-L | "Made in India" comment gone; **2 new** history/rationale comment blocks at `footer.mustache:35-44` + `57-66` |
| **F-10** | P1 | ✅ CLOSED | chip-K | `_surface-login.scss` active `!important` 66 → **11** |
| **F-11** | P1 | ✅ CLOSED | chip-H/249 | `_surface-login.scss` 6 `:focus` / 6 `:focus-visible` paired |
| **F-12** | P0 | ✅ CLOSED | chip-C | `dashboard.mustache` inline `style=` 28 → **4** (3 dynamic `--level-*` CSS vars + 1 print `display:none`); raw KPI hex `#16a34a`/`#dc2626` gone |
| **F-13** | P0 | ✅ CLOSED | chip-G | welcome / subtitle / chart-title / KPI strings now `{{# str }}` (22 total); secondary labels remain → **N-02** |
| **F-14** | P1 | ✅ CLOSED | chip-C / P0-C | `layout/dashboard.php:84` `js_call_amd('theme_airpayux/chart_loader','init')`; 0 `cdn.jsdelivr`; `amd/src/chart_loader.js` vendored |
| **F-15** | P2 | ✅ CLOSED | wave3-chip-N | `dashboard.mustache:224-276` canvases carry `role="img"` + `aria-labelledby` + `<details>` data-table mirror |
| **F-16** | P1 | ✅ CLOSED | chip-J | `_surface-profile.scss` deleted; split into `_surface-{user,badges,calendar,grade-report}.scss` |
| **F-17** | P1 | ✅ CLOSED | chip-H/249 | split partials paired (`_surface-user` 3/3, `_surface-grade-report` 3/3) |
| **F-18** | P2 | ◑ PARTIAL | chip-J | profile `!important` 164 → **91** across split; `_surface-user.scss` still 69 → **N-04** |
| **F-19** | P1 | ✅ CLOSED | chip-H/249 | `_surface-course.scss` 3 `:focus` / 3 `:focus-visible` paired |
| **F-20** | P2 | ✅ CLOSED | chip-Q | `course_full_header.mustache:42-44` security comment; `course_view.php:74` returns escaped `make_pluginfile_url(...)->out()` (server-generated, not user free-text). 2nd instance → **N-05** |
| **F-21** | P1 | ✅ CLOSED | (PWA) | `local/sentientia_pwa/amd/src/install_prompt.js` present (+ `ios_install_hint.js`, `subscribe.js`) |
| **F-22** | P2 | ◑ N/A | — | Process note ("templates not read in detail"), never a code defect. `manifest.mustache` + `subscribe_widget.mustache` exist; deep review still optional |
| **F-23** | P0 | ✅ CLOSED | chip-E | `result_panel.mustache` + `result_bar_chart.mustache` carry `aria-live`; `chart_updater.js` writes localized tally to sr-only `aria-live="polite"` span |
| **F-24** | P1 | ✅ CLOSED | chip-M | `sentientia-bar-row/-count/-percent/-bar`, `airpay-badge--success` BEM classes replace bare Bootstrap utilities |
| **F-25** | P2 | ✅ CLOSED | chip-M | `trainer_dashboard.mustache:40` `<caption class="sr-only">` + `scope="col"` on all 7 `<th>` |

**Non-F backlog items (2026-05-24 Appendix C) also verified:**

| Item | Status | Evidence |
|---|---|---|
| P0 #1 — delete orphan `partials/Claude` | ✅ | file absent |
| P0 #2 — remove `custom_changes_MONOLITH_BACKUP.scss` | ✅ | file absent |
| P0 #7 — hi/kn/mr/sw locale parity → 100% | ✅ | all 5 `theme_airpayux.php` = **178 keys each** (chip-D, chip-F, chip-#255) |
| P1 #13 — `dark_mode.scss` token-cascade | ✅ | active `!important` 253 → **56** (chip-I) |
| P2 #16/#18 — `_moodle-overrides.scss` trim | ✅ | active `!important` 136 → **39** (chip-O); raw grep shows 75 but 36 are explanatory `// preserved:` comments |
| P2 #19 — `prefers-reduced-motion` stylelint rule | ✅ | `.stylelintrc.json` rule + 114-line `frontend.md` doc (chip-P, chip-#256) |

**Tally:** 21 CLOSED · 1 OPEN (F-08) · 2 PARTIAL (F-09, F-18) · 1 process-N/A (F-22). **0 open P0.**

---

### §2.2 — `!important` Census (re-measured, active declarations)

"Active" = `!important` surviving after stripping `//` line-comments (the chip-O
closeout added explanatory comments that themselves contain the literal
`!important`, which inflates a naïve `grep -c`). Baseline column from the
2026-05-24 §2.2 census.

| File | Active now | Baseline | Δ | Status |
|---|---|---|---|---|
| `custom_changes_MONOLITH_BACKUP.scss` | — | 682 | deleted | ✅ gone |
| `partials/Claude` | — | 135 | deleted | ✅ gone |
| `dark_mode.scss` | **56** | 253 | −77.9% | ✅ P1 #13 closed |
| `partials/_surface-user.scss` | **69** | (in 164) | — | ◑ **N-04** — new top offender |
| `partials/_moodle-overrides.scss` | **39** | 136 | −71.3% | ✅ P2 #16/#18 closed |
| `partials/_surface-dashboard.scss` | 32 | 32 | 0% | unchanged (never a target) |
| `partials/_surface-course.scss` | 18 | 18 | 0% | unchanged (never a target) |
| `partials/_surface-login.scss` | **11** | 66 | −83.3% | ✅ F-10 closed |
| `partials/_surface-grade-report.scss` | 6 | (in 164) | — | ✅ split |
| `partials/_surface-badges.scss` | 7 | (in 164) | — | ✅ split |
| `partials/_surface-calendar.scss` | 9 | (in 164) | — | ✅ split |

**Verdict:** The two highest historical offenders are deleted; `dark_mode.scss`
and `_surface-login.scss` saw 78–83% reductions. The post-split profile family
totals **91** active `!important` (was 164), but `_surface-user.scss` alone holds
69 of them — the new single-file top offender (**N-04**). Net theme `!important`
debt is down roughly an order of magnitude from the pre-wave baseline.

---

### §2.3 — Inline `style="…"` in Templates (re-measured)

| Template | 2026-05-24 | 2026-05-25 | Residual (all legitimate or print-only) |
|---|---|---|---|
| `dashboard.mustache` | 28 | **4** | `:549/:567/:602` dynamic `--level-color`/`--level-progress` CSS vars (render-time, cannot move to SCSS); `:722` `display:none` on `#page-content` print container |
| `footer.mustache` | 4 | **0** | attribution band fully classed (F-06) |
| `navbar.mustache` | 1 | **0** | cart badge no longer `display:none` inline (F-04) |
| `course.mustache` / `course_full_header.mustache` | 4 | 4 | all dynamic `width:{{…}}%` / `background-image:url('{{…}}')` — legitimate (unchanged) |

**Verdict:** F-12 closed. The 4 dashboard residuals are the correct pattern —
binding a *dynamic* value (level color / progress %) to a CSS custom property
inline is the idiomatic way to pass render-time data into the cascade, and the
`#page-content` `display:none` is a print-layout container, not brand styling.
**No raw hex remains in any template's `style=` attribute.** (Hardcoded hex *does*
survive inside the dashboard `{{#js}}` chart config — that is a different concern,
filed as **N-03** in §2.10.)

---

### §2.4 — Hardcoded English in Templates (re-measured → N-02)

Navbar (F-01) and footer (F-05) are clean. Dashboard headline strings (F-13) are
localized. **But a thorough re-scan of `dashboard.mustache` finds ~10 user-visible
English strings the i18n pass did not reach:**

| Line | Hardcoded string | Audience | Should be |
|---|---|---|---|
| 95 | `Dark Mode` (sidebar theme label) | all | new key `darkmode_label` |
| 287 | `System Health` (section `<h3>`) | siteadmin | new key `section_system_health` |
| 472 | `Team Member` (`<th>`) | manager | new key `col_team_member` |
| 473 | `Enrolled` (`<th>`) | manager | core/`enrolled` |
| 474 | `Completed` (`<th>`) | manager | core/`completed` |
| 475 | `Rate` (`<th>`) | manager | new key `col_rate` |
| 476 | `Pending` (`<th>`) | manager | new key `col_pending` |
| 477 | `Overdue` (`<th>`) | manager | reuse `kpi_overdue` |
| 478 | `Last Active` (`<th>`) | manager | new key `col_last_active` |
| 621 | `Continue Learning` (section `<h3>`) | learner | new key `section_continue_learning` |

**Verdict:** F-13 closed the *headline* (welcome / chart / KPI) strings, but the
manager team-table header row, the `System Health` and `Continue Learning` section
titles, and the `Dark Mode` toggle label are still literal English. A
Hindi-preferring manager sees an all-English team table. Same CLAUDE.md §1
100%-parity mandate F-13 was raised under. **Filed as N-02 (P1).**

---

### §2.5 — `:focus-visible` Coverage (re-measured)

**Found:** **18 `:focus-visible` selectors** across the surface partials, paired
**1:1** with the 18 remaining bare `:focus` rules (the bare `:focus` is correctly
retained as the legacy-browser fallback). Distribution: `_surface-login` 6,
`_surface-user` 3, `_surface-grade-report` 3, `_surface-course` 3,
`_surface-dashboard` 2, `_surface-navbar` 1. `_surface-footer`, `_surface-badges`,
`_surface-calendar` declare no focus rules (nothing to pair).

**Verdict:** F-03 / F-11 / F-17 / F-19 all closed. The "zero `:focus-visible`"
finding of 2026-05-24 is fully reversed; mouse users no longer get a phantom ring,
keyboard users keep the indicator. ✅

---

### §2.6 — i18n Locale Parity (re-measured → CLOSED)

| Locale | 2026-05-24 keys | 2026-05-25 keys | % of en |
|---|---|---|---|
| en | 156 | **178** | 100% |
| hi | 132 (85%) | **178** | **100%** |
| kn | 118 (76%) | **178** | **100%** |
| mr | 150 (96%) | **178** | **100%** |
| sw | 150 (96%) | **178** | **100%** |

**Verdict:** P0 #7 fully closed. All five `theme_airpayux.php` files carry an
identical 178-key set — and the count *grew* from 156 to 178 because chip-B/G
added the navbar / footer / dashboard keys (F-01, F-05, F-13) and chip-D/F/#255
back-translated every locale to match. The CLAUDE.md "Hindi 100% parity" mandate
is met for the theme component. **Caveat:** the N-02 strings, once keyed, will add
~9 new keys that must be propagated to all 5 locales to *hold* parity — fold that
into the N-02 fix.

---

### §2.7 — `prefers-reduced-motion` Coverage

**Found:** chip-P + chip-#256 landed a `declaration-property-value-disallowed-list`
stylelint rule (`theme/airpayux/.stylelintrc.json`, scoped to
`scss/moodle/partials/_surface-*.scss`) forcing `transition`/`transition-duration`
to consume `var(--ap-transition-*)` / `var(--ap-duration-*)` tokens, which collapse
to `0ms` under `@media (prefers-reduced-motion: reduce)` per
`_tokens.scss:195-264`. A 114-line `.claude/rules/frontend.md` section documents
the rule, correct usage, anti-patterns, and the per-line opt-out.

**Verdict:** P2 #19 closed — vestibular-accessibility regressions are now caught
at lint time, not just relied on by convention. ✅

---

### §2.8 — Dead / Orphan Source Files

**Found:** `scss/moodle/partials/Claude` (was 98 KB) and
`scss/moodle/custom_changes_MONOLITH_BACKUP.scss` (was 284 KB) are **both absent**
from the tree (chip-A). The `partials/` directory now contains only the 9 named
`_surface-*.scss` partials plus the supporting `_bizlms-*`, `_moodle-overrides`,
`_bs5-compat`, `_tokens`, `_ui-polish` partials.

**Verdict:** P0 #1 + #2 closed. ✅

---

### §2.9 — JS Discipline / Inline `<script>` (→ N-01)

**Spec:** Moodle JS discipline — runtime JS belongs in `amd/src/<module>.js`, loaded
via `$PAGE->requires->js_call_amd()`; inline `<script>` is CSP-hostile and
non-discoverable (the original rationale for F-02).

**Found:** chip-B correctly extracted the **cart-badge** IIFE to
`theme_airpayux/cart_badge` (F-02 ✅). However `navbar.mustache:165-179` still
contains an inline `<script>` — a second IIFE that highlights the active
`.ap-mobile-nav__item` by matching `window.location.pathname`. `git show
production@{2026-05-23}:…/navbar.mustache | grep -c '<script>'` returns **2**,
confirming this block **pre-existed the chip wave** and was simply never grepped
for by the 2026-05-24 audit (F-02 cited only lines 119-136). It is **not a
regression**, but it **is a live instance of the exact anti-pattern F-02 named**:
under a strict `script-src` CSP the mobile-nav active state silently fails.

**Verdict:** **N-01 (P1)** — extract to `theme_airpayux/mobile_nav` AMD (or fold
into `cart_badge` since both touch the navbar), call from the navbar render trait.

---

### §2.10 — Chart Palette Tokenization (→ N-03)

**Found:** F-14 moved Chart.js off the CDN into the `chart_loader` AMD module, and
P0-C moved the chart-init code into a `{{#js}}` block (good — it now bundles and
minifies). But the chart **palette is hardcoded hex inside that JS**:
`dashboard.mustache:355` `borderColor: '#0066A7'` and `:376`
`backgroundColor: ['#0066A7','#0f7a73','#7c3aed','#d97706','#dc2626']`.

This is the *same root concern* as F-12 (brand colors bypassing tokens), relocated
from inline CSS to inline JS. Consequences: (a) the charts do **not re-tint in dark
mode** — the palette is fixed regardless of `body.dark-mode`; (b) a Sentientia
customer-N white-label cannot recolor charts without editing template JS. Because
this is in a JS data context (Chart.js needs real color values, not CSS var
*names*, unless you `getComputedStyle`), it's lower-severity than a CSS-token
violation, but it is a genuine theming gap.

**Verdict:** **N-03 (P2)** — read `--ap-primary`/`--ap-accent`/etc. via
`getComputedStyle(document.documentElement)` in `chart_loader` and pass the
resolved values into the chart config, so the palette tracks theme + customer
branding.

---

### §2.11 — Regression Sweep

Looked specifically for damage the 21-chip wave could have introduced:

| Check | Result |
|---|---|
| Conflict markers (`<<<<<<<`/`=======`/`>>>>>>>`) in tracked `*.php/*.mustache/*.scss/*.js` | **0** — CI `conflict-marker-check` gate clean; the P0-A pre-commit hook held across all 21 merges |
| New `coursebannerimage` consumer from the drawer 5.2 backport (P2-I) | `core_courseformat/local/courseindex/course_drawer_header.mustache:2` adds a 2nd `background-image:url('{{coursebannerimage}}')`. **Safe** (same escaped server-side value, §F-20) but **undocumented** → **N-05 (P2)** |
| `_surface-profile.scss` split — did any `body.path-*` scope get dropped? | No — `_surface-user` (873 ln), `_surface-badges` (243), `_surface-calendar` (171), `_surface-grade-report` (428) preserve disjoint `path-*` scoping |
| Dashboard chart-init migration to `{{#js}}` — did chart wiring break? | Code path intact: `chart_loader` AMD exposes `window.Chart`; `{{#js}}` block calls `new Chart(...)` unchanged behind `{{#hascharts}}` guard |
| `PROJECT-STATE.md` "Net result" doc-drift | Claims `_moodle-overrides` trimmed to "30 active"; actual is 39 active (75 raw − 36 comment-mentions). Minor doc drift, not a code defect — noted, not filed |
| Locale parity held after key growth (156→178) | Yes — all 5 locales at 178; no locale lags |

**Verdict:** No functional regressions detected. One undocumented-but-safe new
banner consumer (N-05) and one trivial doc-drift line.

---

### §2.12 — New-Findings Summary & Re-Ranked Backlog

**Net new this pass: 6 findings + 2 carry-forward. Zero P0.**

| # | Sev | Surface | One-line | Sizing |
|---|---|---|---|---|
| N-01 | **P1** | Navbar | Inline `<script>` mobile-nav highlighter → `theme_airpayux/mobile_nav` AMD | 1 hr |
| N-02 | **P1** | Dashboard | Localize ~10 residual strings + propagate ~9 new keys × 5 locales | 3 hrs |
| N-03 | P2 | Dashboard | Resolve chart palette from CSS custom props in `chart_loader` (dark-mode + white-label) | 2 hrs |
| N-04 | P2 | User surface | Trim `_surface-user.scss` 69 → <30 `!important` (continue F-16/F-18) | 4 hrs |
| N-05 | P2 | Course | Add F-20 security comment to `course_drawer_header.mustache:2` | 10 min |
| N-06 | P3 | Messaging | Create `_surface-message.scss` so `/message/` matches the design system | 1 day |
| F-08 | P2 | Footer | (carry) `alt="airpay academy"` → `alt="{{sitename}}"` (tenant-aware) | 15 min |
| F-09 | P2 | Footer | (carry) move the two historical comment blocks to git/docs | 20 min |

**Re-ranked priority for the next wave:**
- **P0:** *(none — promotion gate clear)*
- **P1:** N-01, N-02 *(both incomplete-closure of a prior P0 class)*
- **P2:** F-08, F-09, N-03, N-04, N-05
- **P3:** N-06

---

## §3 — Per-Surface Re-Verification

Condensed dimension tables (only dimensions that *changed* vs 2026-05-24, or that
this pass re-examined). "Visual" rows are **pending local capture** (§Methodology).

### §3.1 — Navbar
| Dimension | 2026-05-24 | Now |
|---|---|---|
| i18n | FAIL (8 literals) | **PASS** — 13 `{{#str}}`, 0 literals (F-01) |
| JS discipline | FAIL (cart IIFE) | **PARTIAL** — cart → AMD (F-02 ✅); mobile-nav IIFE remains (**N-01**) |
| Focus-visible | FAIL | **PASS** — 1/1 paired (F-03) |
| Cart badge hide | inline `display:none` | **PASS** — attribute-based (F-04) |

### §3.2 — Footer
| Dimension | 2026-05-24 | Now |
|---|---|---|
| i18n | FAIL (4 literals) | **PASS** — 5 `{{#str}}` (F-05) |
| Inline style | FAIL (attribution band) | **PASS** — BEM classes, 0 inline (F-06) |
| Mobile breakpoint | FAIL (0 `@media`) | **PASS** — 2 `@media` (F-07) |
| Logo alt | generic lowercase | **FAIL (carry)** — still `alt="airpay academy"` (F-08) |
| Comment bloat | "Made in India" block | **PARTIAL** — 2 new history blocks (F-09) |

### §3.3 — Login
| Dimension | 2026-05-24 | Now |
|---|---|---|
| `!important` | FAIL (66) | **PASS** — 11 active (F-10) |
| Focus-visible | FAIL (7 bare) | **PASS** — 6/6 paired (F-11) |

### §3.4 — Dashboard
| Dimension | 2026-05-24 | Now |
|---|---|---|
| Inline style | FAIL (28) | **PASS** — 4 legit/print residual (F-12) |
| i18n (headline) | FAIL (11) | **PASS** — welcome/chart/KPI localized (F-13) |
| i18n (secondary) | not assessed | **FAIL** — ~10 strings (**N-02**) |
| Chart.js delivery | CDN | **PASS** — `chart_loader` AMD (F-14) |
| Chart a11y | FAIL (no name) | **PASS** — `role="img"` + `<details>` table (F-15) |
| Chart palette | not assessed | **PARTIAL** — hardcoded hex in `{{#js}}` (**N-03**) |

### §3.5 — Profile / User / Badges / Grade-report / Calendar
| Dimension | 2026-05-24 | Now |
|---|---|---|
| Monolith | FAIL (2,507 ln) | **PASS** — split into 4 partials (F-16) |
| Focus-visible | FAIL (11 bare) | **PASS** — user 3/3, grade-report 3/3 (F-17) |
| `!important` | 164 | **PARTIAL** — 91 across split; `_surface-user` 69 (F-18 → **N-04**) |

### §3.6 — Course view
| Dimension | 2026-05-24 | Now |
|---|---|---|
| Focus-visible | FAIL (3 bare) | **PASS** — 3/3 paired (F-19) |
| Banner XSS | flagged unverified | **PASS** — escaped pluginfile URL + comment (F-20); 2nd instance undocumented (**N-05**) |

---

## §4 — Plugin Re-Verification

### §4.1 — sentientia_live
| Dimension | 2026-05-24 | Now |
|---|---|---|
| `aria-live` | **FAIL (zero)** | **PASS** — `result_panel` + `result_bar_chart` regions; `chart_updater.js` writes localized tally to sr-only `aria-live="polite"` span (F-23) |
| BEM vs Bootstrap | FAIL (bare utilities) | **PASS** — `sentientia-bar-*`, `airpay-badge--success` (F-24) |
| Table a11y | FAIL (no caption/scope) | **PASS** — `<caption class="sr-only">` + `scope="col"` ×7 (F-25) |

### §4.2 — sentientia_pwa
| Dimension | 2026-05-24 | Now |
|---|---|---|
| `install_prompt.js` | FAIL (missing) | **PASS** — present (+ `ios_install_hint.js`, `subscribe.js`) (F-21) |
| Template deep-read | DEFERRED | **N/A** — process note; `manifest`/`subscribe_widget` templates exist (F-22) |

---

## Appendix A — `!important` & Token Re-Measure

See §2.2 for the full table. Headline: theme-wide active `!important` is down ~10×
from the pre-wave baseline; two 600–700-count orphan files were deleted; the new
single-file top offender is `_surface-user.scss` (69, N-04). No template `style=`
attribute carries raw hex; the only surviving hardcoded brand hex is the
dashboard chart palette inside `{{#js}}` (N-03).

---

## Appendix B — Visual-Evidence Cross-References

**No PNGs were captured by this pass** — the cloud container cannot reach
`localhost:8080/moodle` (see §Methodology). The 10-surface × 5-persona walk is
specified as a runnable harness at:

```
docs/visual-evidence/2026-05-25/audit-walk/VISUAL-WALK-CHECKLIST.md
```

Run it on the Windows host (Chrome) to populate the drop paths below. Until then,
every "visual" verdict in §3 is a **code-level inference, pending live confirmation.**

| Surface | Persona(s) | Drop path (PNG) | Status |
|---|---|---|---|
| Login | logged-out | `docs/visual-evidence/2026-05-25/audit-walk/login.png` | pending local capture |
| Dashboard | Site Admin | `…/dashboard-siteadmin.png` | pending local capture |
| Dashboard | Tenant Admin | `…/dashboard-tenantadmin.png` | pending local capture |
| Dashboard | Course Author | `…/dashboard-author.png` | pending local capture |
| Dashboard | Compliance Officer | `…/dashboard-compliance.png` | pending local capture |
| Dashboard | Learner | `…/dashboard-learner.png` | pending local capture |
| Course catalog | Learner | `…/catalog-learner.png` | pending local capture |
| Course view | Learner | `…/course-learner.png` | pending local capture |
| Profile | Learner | `…/profile-learner.png` | pending local capture |
| Badges | Learner | `…/badges-learner.png` | pending local capture |
| Grade report | Learner | `…/grade-report-learner.png` | pending local capture |
| Message | Learner | `…/message-learner.png` | pending local capture |
| Calendar | Learner | `…/calendar-learner.png` | pending local capture |
| Mobile 590px | Learner | `…/mobile-590-dashboard.png` | pending local capture |

Existing evidence still valid: `docs/visual-evidence/2026-05-{20,21,23,24}/`.

---

## Appendix C — Next-Wave Remediation Backlog (re-ranked)

### P1 (next-wave head — both incomplete-closure of a prior P0 class)
| # | Sizing | Item |
|---|---|---|
| N-01 | 1 hr | Extract `navbar.mustache:165` mobile-nav `<script>` to `theme_airpayux/mobile_nav` AMD; call from navbar trait |
| N-02 | 3 hrs | Localize 10 residual dashboard strings; add ~9 keys × 5 locales (hold parity) |

### P2 (this sprint, polish)
| # | Sizing | Item |
|---|---|---|
| N-03 | 2 hrs | Resolve chart palette from CSS custom props in `chart_loader` (dark-mode + white-label) |
| N-04 | 4 hrs | Trim `_surface-user.scss` 69 → <30 `!important` |
| N-05 | 10 min | Add F-20 security comment to `course_drawer_header.mustache:2` |
| F-08 | 15 min | `footer.mustache:24` `alt="airpay academy"` → `alt="{{sitename}}"` |
| F-09 | 20 min | Move the two `footer.mustache` history comment blocks to git/docs |

### P3 (document & defer)
| # | Sizing | Item |
|---|---|---|
| N-06 | 1 day | Create `_surface-message.scss`; theme `/message/` to the Sentientia design system |

---

## Sign-off checklist (Nitin to tick before merge)

- [ ] Nitin reviewed
- [ ] **PROMOTION GATE: 0 open P0 confirmed** — CONDITIONAL PASS → PASS
- [ ] Visual walk run on Windows host; PNGs dropped per Appendix B
- [ ] N-01 + N-02 (P1) scheduled for next wave
- [ ] N-03…N-06 + F-08/F-09 (P2/P3) backlogged
- [ ] PROJECT-STATE.md H2 appended
- [ ] CI green (conflict-marker + PHPUnit-5.2 + Playwright-Linux)
- [ ] Branch `claude/beautiful-carson-ZFRGf` pushed

---

*Re-audit complete. 25 prior findings re-verified (21 closed, 2 partial, 1 open,
1 process-N/A); 6 new findings + 2 carry-forward filed; 0 open P0. Verdict:
**PASS** — promote to Phase 2 customer-zero. Visual capture is a local follow-up
(Appendix B) because this pass ran in a network-isolated container.*
