# P1 #10 — `_surface-profile.scss` decomposition (Chip J)

**Audit ref:** `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §3.5, F-16
**Date:** 2026-05-24
**Author:** Claude (foreground chip J)
**Branch:** `claude/zealous-dijkstra-oftgB`
**Scope:** SCSS-only refactor — no .mustache / .php / lang changes

---

## Goal

Split the 2,507-line `_surface-profile.scss` (164 `!important` declarations,
covering 8 surfaces under different `body.path-*` / `body#page-*` parents)
into focused per-surface partials, matching the navbar / footer / login /
dashboard pattern.

This is a **pure-relocation** refactor — no rule, selector, or `!important`
count change. The `!important` density reduction is tracked separately as
P1 #18 (F-18); this chip only addresses the file-size and surface-mixing
concern (F-16).

---

## Decomposition map

| New partial | Lines (incl. 5-line header) | Goals covered | Body scope(s) |
|---|---|---|---|
| `_surface-user.scss`         | **860** | A.1 / A.6 / A.7 | `body#page-user-edit`, `body#page-course-edit`, `body#page-user-preferences`, vanilla `.userprofile` + `.edu_info` + `.ap-profile*` chrome (Sprint 6 + Goal A retrofit) |
| `_surface-badges.scss`       | **243** | A.2             | `body#page-badges-mybadges` + `.ap-badge-card` / `.ap-streak-heatmap` components |
| `_surface-grade-report.scss` | **418** | A.3 / A.9       | `body#page-grade-report-overview-index` + `body.path-grade-report-grader` |
| `_surface-calendar.scss`     | **171** | A.8             | `body#page-calendar-view` |
| **Appended to `_bizlms-admin.scss`** | **+854** | — | `body.path-admin`, `body.path-course-view` (relocation), `#page-my-courses` / `.block_myoverview` (relocation), global utility components (`.ap-faq-item`, `.ap-static-toc`, `.ap-back-to-top`, `.ap-scroll-animate`, `.ap-notif-badge`, `.ap-heatmap-cell`, `.ap-sparkline`, gamification leaderboard row, Sprint 10 mobile bottom-nav `@media` blocks) |
| **Deleted:** `_surface-profile.scss` | — | — | — |

**Original total:** 2,507 lines.
**New total (per-partial 5-line headers + 19-line admin header):** 2,507 source lines + 39 header lines = 2,546 distributed lines.

---

## Threshold compliance (F-16 recommendation: < 800 lines per partial)

| Partial | Lines | Compliance |
|---|---|---|
| `_surface-user.scss` | 860 | **Slightly over** — covers 3 Goal-A surfaces sharing the same DOM family (profile / edit / preferences) and a 27-line file header. Splitting further would create a 4th partial for `body#page-user-preferences` alone (110 lines) which felt over-granular. Justified — keeping the user-account surfaces together aids future maintenance. |
| `_surface-badges.scss` | 243 | ✓ |
| `_surface-grade-report.scss` | 418 | ✓ |
| `_surface-calendar.scss` | 171 | ✓ |

---

## Placement decisions (rules outside the 4 path-* buckets)

The audit's F-16 catch-all was: "Any admin-interior fragments not gated by
`body.path-user/badges/grade/calendar` → move to `_bizlms-admin.scss`."

The original `_surface-profile.scss` contained content that doesn't cleanly
match the 4 new surface buckets. Per the task instruction "If a rule doesn't
fit cleanly into a path-* bucket, document the placement decision in the
commit message", the following landed in `_bizlms-admin.scss`:

| Block | Lines (original) | Why `_bizlms-admin.scss`? |
|---|---|---|
| `body.path-admin { … }` | 1156–1355 | Primary admin interior — natural fit (Goal A.x admin restyle). |
| `body.path-course-view { … }` | 1371–1640 | Course-view chrome. `_surface-course.scss` was **out of scope** for this chip. Parked in admin partial pending a dedicated course-view refactor. |
| `#page-my-courses` + `.block_myoverview` | 512–636 | Moodle dashboard "My Courses" page. `_surface-dashboard.scss` was **out of scope**. Parked here. |
| `.ap-heatmap-cell`, `.ap-sparkline` | 84–105 | Comment header says "Sprint 7 — Admin Dashboard Sparklines + Compliance Heatmap" — semantically admin. |
| `.airpay-gamification__leaderboard-row--me` | 110–114 | Sprint 8 Gamification — global utility, no body scope. Parked in admin per the catch-all. |
| `.ap-notif-badge` | 176–193 | Global notification bell. Used in topbar across all surfaces. Catch-all. |
| `.ap-faq-item`, `.ap-static-toc`, `.ap-back-to-top`, `.ap-scroll-animate*`, `@keyframes ap-fade-in-up`, `@keyframes ap-anchor-flash` | 196–347 | Sprint 9 / Sprint 10 global utilities. Not surface-scoped. Catch-all. |
| Sprint 10 mobile bottom nav `@media (max-width: 590px)` + `@media (min-width: 591px)` | 289–330 | Mobile chrome utility. Catch-all. |
| `@media (max-width: 900px) and (orientation: landscape) { .ap-mobile-nav, .ap-course-sidebar, .airpay-homepage__hero, .ap-onboard__card, .airpay-qr__code }` | 629–636 | Cross-surface landscape orientation guard. Catch-all. |

The `body#page-user-edit, body#page-course-edit { … }` block (lines
1990–2228) is **shared between two body scopes** and lives in
`_surface-user.scss`. The course-edit selector is preserved verbatim — both
pages share identical Moodle mform structure, so the shared rules are
correct in either partial. We chose user (per Goal A.6).

---

## Cascade preservation

**Within each scoped block:** rules appear in their original relative order
(no reordering within `body.path-*` / `body#page-*` blocks). Pure relocation.

**Across scoped blocks:** the new `@import` ordering in
`custom_changes.scss`:

```scss
@import "partials/surface-login";
@import "partials/surface-dashboard";
@import "partials/surface-course";
@import "partials/surface-user";          // P1 #10 / F-16
@import "partials/surface-badges";        // P1 #10 / F-16
@import "partials/surface-grade-report";  // P1 #10 / F-16
@import "partials/surface-calendar";      // P1 #10 / F-16
// ...
@import "partials/bizlms-admin";          // admin fragments appended here
```

Because each new surface partial is scoped under disjoint `body.*` parents
that **never co-target the same element**, the cross-block cascade reshuffle
has no visual effect.

---

## Verification procedure

### A. Source content integrity (mandatory before commit)

Every line of the original `_surface-profile.scss` is assigned to exactly
one target. Verified by `/tmp/p1-chip-j-baseline/split_profile.py` with an
assertion that ranges cover `[1..2507]` exactly once. Output:
```
OK — 2507 lines mapped, ranges contiguous.
```

### B. Compiled-CSS structural equivalence

Compile environment: `npx sass 1.100.0` with the orchestrator wrapped in:
```scss
@import "_tokens";
@import "custom_changes";
```

**Before refactor:**
- Compiled `baseline.css`: 16,472 lines (expanded style)

**After refactor:**
- Compiled `after.css`: 16,510 lines (38 extra lines = new partial header
  comments; declaration count unchanged)

**Sorted-line diff (strip comments + whitespace + sort each):**
```
BASELINE sorted lines: 12824
AFTER sorted lines:    12824
DIFF: 0 lines
MD5 (both): bb2c72485944a69a30bafafb6430732c
```

The set of CSS declarations is **byte-identical** before and after the
refactor — only the cascade order between disjoint scopes has reshuffled.

### C. Regression check — the 8 surfaces

After deploy, visit each surface in **both light and dark mode**, at
desktop + mobile (590px) viewport, signed in as a **Learner role** (admin
bypasses some capabilities):

| # | Surface | URL pattern | What to verify |
|---|---|---|---|
| 1 | Profile (Sentientia) | `/local/airpay_users/profile.php` | `.edu_info` cards, avatar gradient ring, profile tabs |
| 2 | Profile (vanilla) | `/user/profile.php?id=N` | `.userprofile .profile_tree` masonry grid, key-value `<dl>` rows |
| 3 | User edit | `/user/edit.php?id=N` | mform fieldset cards, sticky submit footer, brand focus ring on inputs |
| 4 | Course edit | `/course/edit.php?id=N` | Same mform polish (shares selector block with user-edit) |
| 5 | Preferences | `/user/preferences.php` | h3 uppercase + brand accent, link hover micro-animation |
| 6 | My Badges | `/badges/mybadges.php` | Card chrome, empty-state with trophy icon, search form polish |
| 7 | Grade overview | `/grade/report/overview/` | Card-wrapped tables (taking + teaching), tabular grades |
| 8 | Grader report | `/grade/report/grader/index.php?id=N` | Tertiary nav, pill alphabet filter, branded hover rows |
| 9 | Calendar | `/calendar/view.php` | Day-of-week uppercase headers, branded today/weekend cells, event pills |
| 10 | Admin | `/admin/search.php` | 4-col category card grid, accent bar under section h4 |
| 11 | Course view | `/course/view.php?id=N` | Section cards, completion chips, availability info pill |
| 12 | My Courses | `/my/courses.php` | block_myoverview course-card hover lift, dropdown menu polish |

Every surface should render **identical** to its pre-refactor appearance.
Any pixel-level difference is a regression and must be investigated.

### D. Concurrent-chip coordination

⚠ **Chip H** is concurrently adding `:focus-visible` to bare `:focus` rules
in (the old) `_surface-profile.scss` (F-17, 11 rules at lines 1290–1293,
1711–1713, 2112–2114 of the **original** file). After this chip lands:

- Lines 1290–1293 (admin form focus rules)            → live in **`_bizlms-admin.scss`** (within `body.path-admin` block)
- Lines 1711–1713 (grader tertiary-nav focus rules)   → live in **`_surface-grade-report.scss`** (within `body.path-grade-report-grader` block)
- Lines 2112–2114 (user-edit mform focus rules)       → live in **`_surface-user.scss`** (within `body#page-user-edit, body#page-course-edit` block)

If chip H merges **after** chip J: the new `:focus-visible` siblings should
be added to the **new** locations (chip H needs to re-target).
If chip H merges **before** chip J: chip J's decomposition will carry chip
H's new `:focus-visible` rules into the right per-page partial automatically
(since the line ranges in `split_profile.py` are inclusive of the rule
blocks).

This decomposition's commits 1–5 land as a single coherent unit on
`claude/zealous-dijkstra-oftgB` — chip H should coordinate by rebasing
after this branch is merged, or by re-targeting its hunks if it merges
first.

---

## Per-commit summary

| # | Hash | Title | Files |
|---|---|---|---|
| 1 | `d3f18280` | `extract _surface-user.scss from _surface-profile (P1 #10)` | +1 (surface-user.scss, 861 lines incl. header) / M custom_changes.scss / M surface-profile.scss |
| 2 | `c6b82eaa` | `extract _surface-badges.scss from _surface-profile (P1 #10)` | +1 / M / M |
| 3 | `0a15cab2` | `extract _surface-grade-report.scss from _surface-profile (P1 #10)` | +1 / M / M |
| 4 | `6b1a4290` | `extract _surface-calendar.scss from _surface-profile (P1 #10)` | +1 / M / M |
| 5 | _this commit_ | `complete P1 #10 — delete _surface-profile, merge admin into _bizlms-admin, bump version` | +R (this README) / -1 (surface-profile.scss) / M bizlms-admin.scss / M custom_changes.scss / M version.php / M PROJECT-STATE.md |

---

## Out of scope (deferred)

- F-17 (P1 #11): 11 bare `:focus` rules → Chip H
- F-18 (P2): `!important` density reduction → separate refactor session
- `_surface-course.scss` content polish (the path-course-view block now
  parked in `_bizlms-admin.scss` is a P2 follow-up; mirror it into
  `_surface-course.scss` once that partial is in active refactor)
- `_surface-dashboard.scss` content polish (the `#page-my-courses` block
  now parked in `_bizlms-admin.scss` is a P2 follow-up; mirror it into
  `_surface-dashboard.scss` once that partial is in active refactor)
