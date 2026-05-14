# Learner Dashboard — Redesign Plan

> **STATUS (2026-05-14): Iters 1-8 SHIPPED — feature-complete.** Only Iter 9 (user-driven visual + a11y validation on staging) remains before the redesign is closed. See PROJECT-STATE.md → "Phase B0 — Iterations 5–9 Batch" for the full ship log.

**Surface:** `/my/dashboard.php` (Moodle's default `/my/` reroutes here for Airpay users)
**Status:** EXISTS · **Priority:** P0 (top of the 7-redesign list) · **Effort:** M (1-2 months total)
**Layout file:** `moodle-enhancement/theme/airpayux/layout/dashboard.php` (908 lines)
**Template:** `moodle-enhancement/theme/airpayux/templates/dashboard.mustache`
**SCSS:** `moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-dashboard.scss` (556 lines after iters 2-3-7 cleanup, was 683)

---

## 0. Why this is #1

The dashboard is the first thing every user sees after logging in. It's the highest-trafficked page on the platform. Whatever it looks like becomes the user's mental model of "what Airpay Academy is." If it feels modern, the whole product feels modern. If it feels 2018, the whole product feels 2018.

The current dashboard is functional but visually dated:
- 1,237 hex literals scattered across SCSS partials (66 just in `_surface-dashboard.scss`)
- 4-column stat grid that breaks at <992px
- Inline `style="..."` for things like activity-item colours
- No reduced-motion respect on the hover transforms
- KPI tiles render the same in admin / manager / learner views with no semantic distinction
- Mobile experience: same desktop layout shrunk down, not redesigned

The dashboard's **data layer is mature** — 4 role tiers (siteadmin / L&D admin / manager / learner), full tenant scoping via `open_path`, gamification integration, certificate tracking, deadline computation, manager team rollup, recommendations. That's not what needs work. The **presentation layer** is.

---

## 1. Current state audit (what's there today)

### Role-aware data layer
The layout PHP builds different `$airpay_dashboard` context based on role:

| Role | Sections shown |
|---|---|
| **Site Admin** | admin_kpis · admin_quicknav · charts (enrolment + category pie) · system_health · user_analytics · compliance_summary · recent_activity · top_courses |
| **L&D Admin** | same as Site Admin minus system_health, tenant-scoped to their `open_path` |
| **Manager** | learner sections + manager_kpis + team_compliance · team_overdue (sees both their own learning AND their team) |
| **Learner** | continuecourses (in-progress with progress %) · stats (enrolled/inprogress/completed/notstarted/certificates) · progress_ring · deadlines · achievements · timeline · recommendations · gamification (points/badges/streak) · leaderboard · streak_calendar |

This works. Don't break it.

### What's already-extracted (do NOT rebuild)
The codebase has partial component infrastructure:
- `templates/components/stat_card.mustache` — existed; enhanced in Phase A0.5 commit
- `templates/components/card.mustache` — generic card with image/title/body/footer
- `templates/components/button.mustache` — button partial
- `templates/components/badge.mustache` — badge partial
- `templates/components/progress.mustache` — progress bar partial
- `scss/moodle/_tokens.scss` — comprehensive design tokens (Phase A0.5)
- `scss/moodle/partials/_components-stat-card.scss` — tokens-aware KPI tile (Phase A0.5)

### What's NOT yet extracted (the redesign work)
- Course progress cards (the "Continue Learning" tiles)
- Activity feed items (the timeline entries)
- Deadline rows (urgent/normal)
- Gamification widgets (streak calendar, leaderboard rows, badge gallery)
- Section headers (the "Continue Learning · See all" pattern)
- Quick-nav buttons (the admin landing icons with mini-stats)
- Health indicator rows (the system_health green/yellow/red list)
- Recommendation cards (smaller variant of course progress card)

---

## 2. Redesign principles (locked from UI-UX-MANIFESTO §1)

1. **Mobile is the canonical surface.** Design the dashboard for a 360×640 iPhone Pro screen *first*, then progressive-enhance to tablet and laptop. The current 4-col grid that breaks at <992px is the inverse of this principle.

2. **Touch-first, even on laptops.** Every interactive tile must be ≥44×44pt (manifesto §9). The current stat tiles are tappable but have no `--ap-tap-target-*` enforcement. Linked variant must support keyboard focus visibly.

3. **Content is the interface.** Strip chrome that doesn't help the user know what to do next. The current dashboard has 4 sections of "what's already happened" (timeline / activity feed / achievements) and only 1 of "what should I do next" (deadlines + continue courses). Invert that.

4. **Motion is meaning.** Use the manifesto's motion taxonomy (§5.3) — e.g. trend indicators slide in on load, KPI numbers animate-count from 0, completion ring fills with `--ap-duration-deliberate + --ap-ease-spring`. No parallax. No autoplay.

5. **Empty states are scenes.** When a new learner sees the dashboard for the first time, they have zero deadlines, zero achievements, zero activity. The current dashboard shows empty rows. Manifesto says: empty state = inviting scene. "Start your first course → [Browse catalogue]"

---

## 3. The redesign — sequenced by impact

### 3.1 Iteration 1 — KPI tile component (✅ shipped this session)

The most-reused visual unit. Now exists as `airpay-stat-card` with:
- Tokens-aware (no hex literals)
- 6 colour variants (primary / accent / success / warning / danger / info)
- Mobile-responsive grid wrapper (`airpay-stat-grid`: 4 → 2 → 1 cols)
- Optional `href` makes whole tile a click target with `:focus-visible`
- Auto-built `aria-label` from value + label + trend
- Trend animation respects `prefers-reduced-motion`

**Status:** Available now. Demo in Style Guide → Components section.

**Migration target:** Three call sites in `dashboard.mustache` currently inline the old `.airpay-dash__stat` markup:
- Admin KPIs (lines 180-190) → 4 tiles
- Learner KPIs (lines 432-460) → 4 tiles
- Manager KPIs (line 376-385) → 4 tiles

Iteration 2 will replace these with `{{> theme_airpayux/components/stat_card }}` partial calls. Pure refactor — DOM stays identical because the new CSS class names map 1:1 to the old.

### 3.2 Iteration 2 — Mobile-first dashboard shell

The dashboard's outer layout. Current implementation uses 4-column Bootstrap grids that collapse poorly. Replace with:

```css
.airpay-dash-shell {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--ap-space-6);

    @media (min-width: $ap-bp-tablet) {
        grid-template-columns: minmax(0, 1fr) 320px;  /* main + sidebar */
    }
    @media (min-width: $ap-bp-laptop) {
        grid-template-columns: minmax(0, 1fr) 360px;
    }
}
```

The sidebar (right rail on tablet+, stacked bottom on mobile) houses:
- Streak / gamification mini-widget
- Recent activity feed (collapsed)
- Quick links (quick-nav for admins, "Browse catalogue" for learners)

The main column houses the action-oriented content:
- For learners: Continue Learning + Deadlines + Recommendations
- For managers: Team summary + own learning
- For admins: KPI strip + charts + section landing tiles

### 3.3 Iteration 3 — Course progress card

The "Continue Learning" tile. Currently inlined HTML at dashboard.mustache:432-470 (per-course):
- Course thumbnail (or category-coloured placeholder)
- Course title + shortname
- Progress bar with percentage
- "Continue →" CTA

Extract into `templates/components/course_progress_card.mustache`. Tokens-aware. Used in:
- Dashboard "Continue Learning"
- `/local/airpay_catalog/mycourses.php` (Surface 1.2)
- Manager team drilldown showing per-team-member progress

### 3.4 Iteration 4 — Activity feed item

The timeline rows. Currently inlined per-event with hex-literal icon colours.

Extract into `templates/components/activity_item.mustache` with semantic event types (`completion`, `enrolment`, `badge`, `quiz`, `comment`). Each type maps to a tokens-aware icon colour. Used in:
- Dashboard activity feed (timeline section)
- Manager "what's happening on my team" drawer
- Site Admin recent_activity widget

### 3.5 Iteration 5 — Deadline / overdue tile

The "Upcoming deadlines" rows. Currently inlined.

Extract into `templates/components/deadline_tile.mustache` with three states:
- `--urgent` (red bg, <24h to deadline)
- `--soon` (amber bg, <7d to deadline)
- `--normal` (neutral bg, >7d)

Used in:
- Dashboard deadlines section
- Manager team compliance view (per-report overdue badge)
- Notification email cadence engine (template inclusion)

### 3.6 Iteration 6 — Section header

Tiny but used everywhere. `<h3>Continue Learning</h3>` + `<a>See all →</a>` pattern.

Extract as `templates/components/section_header.mustache` so the typography + spacing + link styling stays consistent across every section on every page.

### 3.7 Iteration 7 — Empty states

When a learner has no enrolled courses / no deadlines / no achievements, instead of blank rows show:
- Illustration (SVG, tokens-aware fill)
- Headline ("No deadlines yet")
- Action ("Browse the catalogue →")

`templates/components/empty_state.mustache` with `icon`, `headline`, `description`, `cta` context.

---

## 4. What we're NOT changing

- The data layer in `dashboard.php`. It's mature, tenant-scoped, and has caught real bugs (the role-detection logic took two sprints to get right). Touch it only if a new component needs a new context key.
- The block-region structure (`layerone_full`, `layerone_one`, `layerone_two` etc.). Site admins customise their dashboard via these regions; removing them breaks every admin's saved layout.
- The Moodle-native completion / enrolment data structures. We read from them, we don't replace them.
- Tenant branding hooks in `core_renderer.php`. Logo, brand colour, footer copy are tenant-controllable — keep that surface intact.

---

## 5. Verification gates (every iteration must clear these)

1. **Visual regression test** — capture before/after screenshots at 360px, 768px, 1280px, 1600px for all 4 role tiers (siteadmin / L&D admin / manager / learner). Diff against baseline.
2. **A11y audit** — axe-core CI run shows 0 critical, 0 serious on the dashboard page for every role.
3. **PHPUnit pass** — no regression in the 91-test green baseline.
4. **Dark mode** — every new component renders correctly under `[data-theme="dark"]`.
5. **Reduced motion** — every transition respects `prefers-reduced-motion: reduce`. Tested via Chrome devtools "Emulate CSS media feature".
6. **Tenant rendering** — Airpay (id=1), Public (id=77), ZEEA (id=177) each render correctly with their tenant's branding.

---

## 6. Iteration ship log (as actually executed)

| Iter | Commit | Deliverable | Status |
|---|---|---|---|
| 1 | `d3ae87af0` | Stat card component + Style Guide demo + redesign plan | ✅ shipped |
| 2 | `153dd5556` | Replace inline stat-tile HTML in dashboard.mustache with partial (admin + manager + learner) | ✅ shipped |
| 3 | `6335f803c` | Course progress card + Continue Learning migration + status badges + dataset enrichment | ✅ shipped |
| 4 | `6883306c0` | Activity feed item (admin Recent Activity + learner Timeline normalised onto one component) | ✅ shipped |
| 5 | `9e7a4b89d` | Deadline tile with 4 urgency states + urgent-pulse animation | ✅ shipped |
| 6 | `42c32000b` | Section header partial + legacy class aliases (no template churn) | ✅ shipped |
| 7 | `f68f26b44` | Empty state component + fix for broken legacy tokens | ✅ shipped |
| 8 | `6552527e6` | User Analytics tiles → stat_card (closes iter-2 migration) | ✅ shipped |
| — | `ec4a1f1d7` | Dead-code sweep — strip iter-2/3 unreferenced CSS from `_surface-dashboard.scss` | ✅ shipped |
| 9 | — | Visual regression + a11y validation on staging | ⬜ user-driven |

Originally planned as 8-9 sessions; consolidated into one session with 8 commits + 1 cleanup commit on the `production` branch.

---

## 7. References

- `docs/platform-review-2026-05-14/UI-UX-MANIFESTO.md` — design principles
- `docs/platform-review-2026-05-14/SURFACE-ROADMAP.md` §1.1 — surface spec
- `moodle-enhancement/theme/airpayux/scss/moodle/_tokens.scss` — token source
- `moodle-enhancement/local/airpay_core/admin/styleguide.php` — live token reference
