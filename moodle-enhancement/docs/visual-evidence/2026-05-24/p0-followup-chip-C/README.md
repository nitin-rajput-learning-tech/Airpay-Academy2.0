# P0 #5 Follow-up — Dashboard Inline-Style Cleanup (2026-05-24)

**Chip:** C — dashboard inline-style avalanche
**Branch:** `claude/funny-einstein-fUaIE`
**Audit ref:** `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §3.4 F-12 / §2.3

This chip closes P0 #5 from the platform visual audit. The dashboard
template was carrying 34 inline `style=""` attributes (5 of which contained
raw hex literals — `#16a34a`, `#dc2626`, `#1a1a2e`, `#5a6070`, `#f97316`,
`#d97706`, `#16a34a` repeated in cells) that bypassed the design-token
cascade. Dark mode rendered the compliance KPI counters and progress-
summary copy incorrectly because the literals never themed.

Every static inline style is now a token-driven SCSS rule in
`theme/airpayux/scss/moodle/partials/_surface-dashboard.scss`. Every
dynamic value (gamification `level_color`, `level_progress`) flows
through a CSS custom property carried on the element so the cascade
itself stays in SCSS.

---

## Inline-style count

| Stage | Count | Hex literals |
|---|---:|---:|
| Before (audit baseline) | 38 | 7 |
| After  | 4   | 0 |

The 4 surviving `style=""` attributes are:
1. `style="--level-color: ..."` on `.airpay-gamification__level` (line 462) — dynamic-value carrier
2. `style="--level-progress: ...; --level-color: ..."` on `.airpay-gamification__progress-bar` (line 480) — dynamic-value carrier
3. `style="--level-color: ..."` on `.airpay-gamification__leaderboard-avatar` (line 515) — dynamic-value carrier
4. `style="display: none;"` on `#page-content` (line 635) — DOM layout state, JS-toggled when the sidebar shell is active

The first three are CSS-custom-property carriers for server-controlled
hex values from `local_airpay_gamification/classes/points_manager.php`
(a 5-step lookup table — not user input). The actual visual cascade
lives in `.airpay-gamification__level`, `.airpay-gamification__progress-bar`,
`.airpay-gamification__leaderboard-avatar` rules in the SCSS partial.

---

## Commits

| # | Hash | Scope |
|---:|---|---|
| 1 | `724906e9` | Welcome header — 7 attrs → `.airpay-dash__welcome-header` BEM block |
| 2 | `faa9c7cd` | Compliance KPI grid — 14 attrs + 2 hex literals → `.airpay-dash__compliance-*` with semantic tokens |
| 3 | `5044c9d8` | Top-courses list (4) + section spacing modifiers (3) |
| 4 | `332f120e` | Team-compliance table cells — 4 attrs + 3 hex literals → BEM modifiers + semantic tokens |
| 5 | `fc3a3247` | Progress-summary + gamification — 13 attrs (incl. 4 dynamic via CSS vars) |
| 6 | (this) | version.php bump + visual-evidence + PROJECT-STATE H2 |

Each commit covers one logical block of the template, so reviewers can
inspect them independently.

---

## Token mappings

| Original literal | Replaced with | Rationale |
|---|---|---|
| `#16a34a` (compliant count) | `var(--ap-color-success)` (= `#15803d`) | Semantic + WCAG 2.1 AA bumped (3.5:1 → 4.5:1) |
| `#dc2626` (overdue count + pending) | `var(--ap-color-danger)` | Semantic |
| `#1a1a2e` (progress-summary title) | `var(--ap-color-text-primary)` | Semantic |
| `#5a6070` (progress-summary meta) | `var(--ap-color-text-secondary)` (= neutral-600, a11y-bumped) | Semantic + WCAG AA bumped |
| `#f97316` (fa-fire / streak icon) | `.airpay-dash__streak-icon { color: #f97316 }` in partial | Decorative gamification semantic — not a brand primitive, kept literal in SCSS rather than promoted to global token |
| `#d97706` (fa-trophy icon) | `.airpay-dash__trophy-icon { color: #d97706 }` in partial | Same reasoning |
| `1.5rem` / `0.875rem` / `28px` / `12px` / `13px` | `var(--ap-text-2xl/sm/xs)` etc. | Token discipline; ≤2px visual delta where the original used non-standard literals |
| `20px` / `16px` / `12px` / `8px` margins+paddings | `var(--ap-space-5/4/3/2)` etc. | 8px-base grid alignment |
| `10px` border-radius | `var(--ap-radius-sm)` (= 8px) | Closest token; 2px visual delta |

---

## Visual deltas vs the pre-cleanup render

The work is a refactor — visually equivalent to within a 1–2 px tolerance
for the cases where the inline style used a non-standard literal that
no token matches exactly (13px → 14px text-sm, 10px → 8px radius-sm,
10px → 12px space-3 padding). The semantic-token swap for
`#16a34a → --ap-color-success #15803d` and the text-secondary bump from
neutral-500 to neutral-600 are **a11y improvements** the rest of the
theme already adopted (the dashboard inline styles were the last
hold-outs).

No structural DOM changes — the same `<div>`/`<h2>`/`<p>` tree renders
across admin / manager / learner personas. The personas branch via
`{{#isadmin}}` / `{{#ismanager}}` / `{{^isadmin}}{{^ismanager}}`
mustache sections (preserved verbatim from before).

---

## Screenshots — environment note

This container is a Linux execution environment with no XAMPP / live
Moodle, so live before/after captures are not produced here. The
reference visuals for the affected surfaces are:

- `docs/visual-evidence/2026-05-21/regression-profile.png` — admin
  dashboard with welcome header + compliance KPI grid
- `docs/visual-evidence/2026-05-22/` — additional dashboard captures
- The 22 C-suite-approved prototypes at
  `D:\Claude Local\Moodle Backup\03-prototypes\preview\dashboard-*.html`

When Nitin deploys the version-bumped theme to local XAMPP for the
final visual-regression pass, the test checklist is:

- [ ] Admin dashboard at `/my/dashboard.php` — welcome header, charts,
      compliance KPI grid (verify the 4 counters render with the
      semantic-token colours: primary / success / danger / text-primary)
- [ ] Admin dashboard — top-courses card, recent-activity section
- [ ] Manager dashboard — team-compliance table, pending / no-pending /
      no-overdue cells render with semantic-token colours
- [ ] Learner dashboard — progress-summary panel + gamification widgets
      (level badge / progress bar / leaderboard avatars all carry the
      per-user `level_color` via CSS custom property)
- [ ] Mobile breakpoint 590px — no inline-style remnants
- [ ] Dark mode — compliance counters re-theme correctly (this was
      broken before because of the hex literals)
- [ ] Tenants Airpay (id=1), Public (id=77), ZEEA (id=177)
