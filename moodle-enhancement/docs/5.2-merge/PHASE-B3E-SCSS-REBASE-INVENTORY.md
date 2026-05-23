# Phase B.3.e — SCSS variables rebase: INVENTORY + strategic finding

**Date:** 2026-05-23
**Status:** Inventory complete. Strategic finding flips the urgency:
**we self-bundle Bootstrap 4, so 5.2 doesn't break our SCSS today.**
The migration to BS5-native functions is a separate, optional theme
refresh decoupled from the 5.2 upgrade timeline.

---

## The headline finding

`theme/boost/scss/moodle/variables.scss` between 5.1.3+ and 5.2 looks
brutal at first glance — Bootstrap 4 → Bootstrap 5 migration cascaded
into the variables namespace:

| Before (BS4 / 5.1) | After (BS5 / 5.2) |
|--------------------|-------------------|
| `theme-color-level("X", -10)` | `shift-color($X, -80%)` |
| `($spacer / 4)` | `($spacer * 0.25)` |
| `$navbar-height: 70px` | `$navbar-height: 60px` |
| `$course-content-maxwidth: 100%` | `$course-content-maxwidth: 830px` |
| `"interface"` (activity icon key) | `"interactivecontent"` |

Our codebase has **63 occurrences of `theme-color-level` / `$spacer /
N` / `interface` across 16 SCSS files** — sounded like a major
migration.

But:

```
> grep "@function theme-color-level" theme/airpayux/scss/bootstrap/_functions.scss
99:    // Request a theme color level
101:   @function theme-color-level($color-name: "primary", $level: 0) {
```

**Our airpayux ships its own Bootstrap 4 — including
`theme-color-level()` defined locally.** We forked from boost back
when boost was BS4, and we carry the BS4 functions ourselves.
Moodle 5.2's boost rewrote on top of BS5, but airpayux still uses
its bundled BS4 functions — so the 5.2 upgrade does NOT silently
break our 63 `theme-color-level()` callsites.

Proof: phase B.3 web smoke compiled our SCSS cleanly on 5.2 — 1.45MB
of CSS, zero compiler errors in `docker logs moodle52web`. Same
visual output bytes as 5.1.

---

## Variables file size delta

|  | Lines | Variables |
|--|------:|----------:|
| 5.1 boost (ours) | 52 | ~9 |
| 5.2 boost (theirs) | 324 | ~90 |
| **Delta** | **+272** | **+81** |

5.2 added 81 NEW component-scoped variables (atto, backup-restore,
calendar, ...). These are *additive* hooks — we can adopt them as
needed to customise without overriding markup. Examples:

```scss
// New in 5.2 (we can override per-customer if desired):
$atto-toolbar-bg:                #f2f2f2 !default;
$backup-restore-state5-bg:       #999 !default;
$calendar-month-clickable-bg:    #ededed !default;
$courselisting-empty-bg:         #f8f9fa !default;
$grade-history-table-stripe-bg:  #f6f6f6 !default;
```

None of these are *required* — they all have sensible defaults.

---

## Codebase exposure summary

| File | `theme-color-level` count | Risk if 5.2 deprecates BS4-compat |
|------|--------------------------:|------------------------------------|
| scss/moodle/course.scss | 16 | Hot path — course view styling |
| scss/moodle/variables.scss | 13 | Foundation |
| scss/moodle/core.scss | 10 | Foundation |
| scss/moodle/modules.scss | 4 | Activity rendering |
| scss/bootstrap/_variables.scss | 4 | Our BS4 bundle |
| scss/moodle/courseindex.scss | 2 | Course index drawer |
| scss/moodle/question.scss | 3 | Quiz / question styling |
| scss/moodle/toasts.scss | 3 | Toast notifications |
| scss/moodle/buttons.scss | 1 | Button states |
| scss/moodle/forms.scss | 1 | Form chrome |
| scss/moodle/grade.scss | 1 | Gradebook |
| scss/bootstrap/_alert.scss | 1 | Alert chrome |
| scss/bootstrap/_functions.scss | 1 | **DEFINITION** of theme-color-level |
| scss/bootstrap/_images.scss | 1 | Image classes |
| scss/bootstrap/_list-group.scss | 1 | List group |
| scss/bootstrap/_tables.scss | 1 | Table chrome |
| **TOTAL** | **63** | All work today, no urgency |

---

## Strategic recommendation

**Decouple the Bootstrap 5 migration from the 5.2 upgrade.**

The 5.2 upgrade is gated on PHP 8.3+ and the merge proper. The BS4→BS5
migration is a strategic theme refresh that can ship later as a
"Sentientia design system v2" release. Doing them together compounds
risk and delays both.

### Migration paths when we DO get to BS5 (post-5.2-cutover)

1. **Add a `theme-color-level()` deprecation shim** in
   `scss/bootstrap/_functions.scss` that maps `level` to BS5
   `shift-color()` percentages. Then bulk-replace callsites file by
   file with no rendering change.

2. **Update Dart Sass division warnings**: replace `($spacer / 4)`
   with `($spacer * 0.25)` and similar — Dart Sass 2.x will reject
   the division syntax outright. ~30 occurrences.

3. **Activity icon key**: already fixed in this commit — we ship both
   `interface` and `interactivecontent` keys with the same Sentientia
   purple so the lookup works on both 5.1 and 5.2.

Estimated BS5 migration session: 6-8 hours when scheduled.

---

## Changes shipped in this commit

### `theme/airpayux/scss/moodle/variables.scss` — dual-key fix

```scss
// Before (5.1 only):
"interface": #a378ff

// After (5.1 + 5.2):
"interface": #a378ff,
"interactivecontent": #a378ff
```

5.1 boost looks up `"interface"` → finds Sentientia purple. ✅
5.2 boost looks up `"interactivecontent"` → finds Sentientia purple. ✅
Same colour, same visual outcome, zero regression risk.

Without this fix, the 5.2 instance falls back to upstream brown
(`#8d3d1b`) for any H5P/interactive-content activity icon — visible
on /course/view.php pages with interactive activities.

---

## Follow-up: 5.2 removed `flat_navigation.mustache` + `nav-drawer.mustache`

Per `theme/boost/UPGRADING.md` §5.2 (MDL-87425), upstream removed two
boost templates entirely:

- `public/theme/boost/templates/flat_navigation.mustache`
- `public/theme/boost/templates/nav-drawer.mustache`

Our airpayux ships its own copy of both:

- `theme/airpayux/templates/flat_navigation.mustache` (present)
- `theme/airpayux/templates/nav-drawer.mustache` (present)

**Impact assessment:** if anything in our codebase calls
`$OUTPUT->render_from_template('theme_boost/flat_navigation', ...)`
or `theme_boost/nav-drawer` by upstream name, that breaks on 5.2.
If callers use `theme_airpayux/<name>` (our local copy) or
`core/<name>` (component-agnostic — Moodle falls back to the theme's
template lookup), it still works.

The frontpage smoke test passes (HTTP 200, byte parity with 5.1), so
at least the frontpage's navigation rendering is fine.

**Sweep result (2026-05-23):** ZERO callsites in our entire codebase
reference `theme_boost/flat_navigation` or `theme_boost/nav-drawer` by
upstream name. Our forks at `theme/airpayux/templates/{flat_navigation,
nav-drawer}.mustache` are picked up via Moodle's template inheritance
lookup when callers use bare names or `theme_airpayux/<name>`. The
5.2 upstream removal is non-blocking for us.

No Phase B.3.c repoint action needed for these two templates.

---

## Decisions for Nitin's review

1. **Defer Bootstrap 5 migration** to a future "Sentientia design
   system v2" release — out of scope for the 5.2 upgrade?
2. **Adopt 5.2's 81 new component variables** opportunistically as
   we touch each component (e.g. when polishing the gradebook,
   import `$grade-history-table-stripe-bg` for stripe row colour
   instead of hardcoding).
3. **Activity icon dual-key pattern** generalises to any future
   Moodle renames — ship both old + new keys with the same value
   during the transition, drop the old key post-cutover.

---

## Refs

- ADR-011 §"Phase B work breakdown" — B.3.e listed at 4h estimate
- PHASE-A4B-CONFLICT-MAP.md §"L. boost/scss/moodle/*.scss" —
  rebase strategy noted at theme/boost level
- PHASE-B3-WEB-SMOKE-PASS.md — proves SCSS compiles cleanly on 5.2
- This file — strategic decoupling rationale

---

## Headline for the changelog

> Phase B.3.e SCSS variables inventory complete. We self-bundle
> Bootstrap 4 in `scss/bootstrap/_functions.scss`, so 5.2 boost's
> BS5 migration doesn't break our SCSS. 63 `theme-color-level()`
> callsites continue to work via our bundled definition. BS5
> migration deferred to a future Sentientia design system v2
> release. The only mechanical 5.2-required fix landed: dual-key
> for the `interface` → `interactivecontent` activity icon rename.
