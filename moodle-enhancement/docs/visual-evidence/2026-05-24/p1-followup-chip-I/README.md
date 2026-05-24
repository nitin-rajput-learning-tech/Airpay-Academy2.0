# P1 #13 — Dark-Mode Token-Cascade Refactor

**Date:** 2026-05-24
**Chip:** I (dark_mode.scss `!important` reduction)
**Audit reference:** `moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §2.2 + P1 #13
**Branch:** `claude/sleepy-knuth-3fpPR`

---

## Mandate

Eliminate >90% of the 253 `!important` declarations in
`moodle-enhancement/theme/airpayux/scss/moodle/dark_mode.scss` by replacing
hammer-style overrides with CSS-custom-property cascade + sufficient parent
specificity at the `body.dark-mode` / `body.high-contrast` parent.

## Before / After counts

| Measure | Before | After | Delta |
|---|---|---|---|
| `grep -c '!important'` (audit measure — counts lines, includes comments) | 253 | 58 | **−77.1%** |
| Actual `!important;` declarations (comments stripped) | ~253 | **36** | **−85.8%** |
| Dark-mode block (`html.dark-mode, body.dark-mode {...}`) declarations | 158 | 16 | −89.9% |
| High-contrast block (`body.high-contrast {...}`) declarations | 95 | 20 | −78.9% |

Target was `<30` declarations (>90% elimination). Final at **36** — under the
audit's 90% goal by a small margin, with a documented reason for every
preserved `!important`. See "Why not lower?" below.

## Per-bucket refactor summary

Five commits, scoped logically:

### Commit 1 — Page wrappers + navbar + dashboard surfaces
`dceab2b4 refactor(dark-mode): drop !important from page/navbar/dashboard surfaces`

- Page wrappers (`#page`, `#page-wrapper`, `#region-main`, `.pagelayout-*`)
- Navbar block (`nav.navbar`, `.airpay-nav`, `.airpay-nav__pill` variants, search input, brand)
- Welcome banner gradient
- Stat cards (`.airpay-dash__stat` + variants)
- Block / calendar cards (`.block .card`, `.calendarwrapper`, `.calendartable`)
- Generic card group + quicknav + activity feed
- Page header
- Preserved: `.airpay-dash__deadline-item` background — fights
  `_dark-mode-global.scss` same-selector `!important` with different value (#1e2130 vs #1a1d27).

**253 → 206** (−47).

### Commit 2 — Profile + forms + tables + text
`8063ad17 refactor(dark-mode): drop !important from profile/forms/tables/text`

- Profile shell (`.path-local-users .user_gen_info`, `.nav-tabs`)
- Form inputs (input, textarea, select, .form-control, focus styles, placeholder)
- Tables (`.generaltable` + thead/tbody/hover/zebra)
- Headings (h1-h6, .card-title, course/stat titles)
- Body text (p, .card-text, .course-desc, stat/deadline/achievement labels)
- Preserved: `.path-local-users .tab-content` background (`_surface-profile.scss`
  conflict, different value); `.generaltable tbody td` color (cascade
  preservation against earlier inline-style override).

**206 → 186** (−20).

### Commit 3 — Buttons + dropdowns + alerts + popovers + BizLMS + scrollbar
`f4a6655e refactor(dark-mode): drop !important from buttons/alerts/popovers/BizLMS`

- Buttons (`.btn-primary`, `.btn-secondary`, `.btn-outline-secondary` + hover)
- Dropdowns (`.dropdown-menu`, `.dropdown-item` + hover)
- Alerts (all `.alert-*` variants, both UAT-L5 block and main block)
- Filter sidebar (`.filter_section`)
- List group, `.bg-pulse-grey`, `.block .form-control`
- BizLMS admin (`.course_extended_menu_itemlink`)
- BizLMS Manage Company (`.content_right`, `.details_content` family)
- BizLMS Manage Users / Courses containers
- Scrollbar + popover
- Breadcrumb wrapper + `.breadcrumb-item.active`
- Misc text (`.course-content`, `label`, `legend`)
- Preserved: `.modal-*`, `.pagination *` (later relaxed in commit 5),
  `.breadcrumb-item a` (bizlms-dark conflicts).

**186 → 123** (−63).

### Commit 4 — High-contrast block refactor
`05e646ca refactor(high-contrast): drop !important from BizLMS polish + generaltable`

- All "Production data polish (Phase 16)" BizLMS-specific custom selectors
- `.generaltable` family inside high-contrast scope
- `.airpay-dash__welcome / .user_gen_info` background
- Preserved: standard high-contrast accessibility rules (font-weight,
  color, border on generic elements like p/h1-h6/a/input that could
  receive Bootstrap utility classes); `:focus, :focus-visible` outline
  (accessibility-critical); `* ` descendant color overrides;
  `.block_myoverview .course-summaryitem .text-muted` (Bootstrap utility).

**Bug noted (out of scope):** "Production data polish" rules use
dark-mode colour values inside `body.high-contrast` scope, contradicting
the high-contrast accessibility theory (⬛ on ⬜). Audit follow-up.

**123 → 64** (−59).

### Commit 5 — Idle modal + pagination link relaxation
`134e30f4 refactor(dark-mode): drop !important from idle modal/pagination`

After the bizlms-dark conflict review showed that for IDLE (non-active)
modal and pagination states the colour deltas with `_bizlms-dark.scss`
are aesthetic-only (slate-vs-slate, 1-2 hex steps), dropping our
`!important` hands the cascade to bizlms-dark with effectively zero
visual change. Kept `!important` only on the active states where the
delta is meaningful (brand-blue solid vs deep navy).

**64 → 58** (−6, line count); declarations 43 → 36.

## Why not lower?

The 36 remaining declarations break down into 6 categories, all
documented inline with `// preserved: <reason>` comments:

| Reason | Count | Examples |
|---|---|---|
| Bootstrap utility class collision (`!important` on both sides) | ~7 | `.text-muted`, `.text-dark`, `.text-decoration-none`, `.badge.bg-secondary` |
| Inline-style override (only `!important` beats inline) | ~3 | `table tbody tr td` color; `.alert-info code[style]` |
| Cross-partial conflict with meaningfully-different value | ~4 | `.pagination .page-item.active .page-link`, `.breadcrumb-item a`, `.airpay-dash__deadline-item`, `.path-local-users .tab-content` |
| Same-file cascade preservation | ~1 | `.generaltable tbody td` color (keeps table cells muted vs the broader inline-override colour) |
| High-contrast accessibility intent (must beat Bootstrap utilities applied to generic elements) | ~19 | `body.high-contrast p / h1-h6 / .btn / input / .card { font-weight, color, border, ... }` |
| Descendant `*` selectors in high-contrast | ~1 | `.airpay-dash__welcome *, .user_gen_info * { color: #ffffff !important; }` |
| Focus outline (accessibility) | ~1 | `:focus, :focus-visible { outline: ... !important; }` |

Hitting <30 would require either:
1. Editing **`_bizlms-dark.scss`** (out of scope for this chip — owned by
   the BizLMS removal track) to harmonise modal/pagination/breadcrumb
   palettes into a single token in `_tokens-dark.scss`.
2. Accepting accessibility regressions in high-contrast mode (Bootstrap
   utility classes like `.text-primary` would override the forced black
   text inside high-contrast scope).

Both are explicitly out of scope for P1 #13 per the audit and the chip
brief. Recorded as audit follow-ups in the commit messages.

## Light-mode preservation

Every change in this refactor is inside one of two scoped blocks:
- `html.dark-mode, body.dark-mode {...}`
- `body.high-contrast {...}`

Light mode (`<body>` without `.dark-mode` or `.high-contrast`) does
**not** match either parent selector. Zero light-mode rules touched.
**Light mode is unchanged.**

## Compile sanity-check

```
$ /tmp/node_modules/.bin/sass --no-source-map dark_mode.scss /tmp/dark_compiled.css
$ echo $?
0
```

Compiled with `dart-sass 1.100.0` — exits clean, no warnings or errors.
Brace integrity verified (171 open, 171 close).

## Test checklist for Nitin (post-deploy)

After `php purge_caches.php` and Ctrl+Shift+R, toggle dark mode and verify:

- [ ] **Dashboard (`/my/dashboard.php`)** — page bg, navbar, stat cards,
  block cards (Timeline / Calendar / My Courses), welcome banner all
  render dark
- [ ] **Profile (`/local/users/<id>`)** — profile hero gradient, info
  cards, nav tabs, tab-content background dark
- [ ] **Course view (any course)** — block cards, sidebar filters, course
  sections all dark
- [ ] **Login (`/login/index.php`)** — login form panel, input fields,
  brand visible on dark bg
- [ ] **Admin pages** — generaltable headers blue (#1e2d42), body cells
  muted grey, hover/zebra working
- [ ] **Spot-check colours:**
  - Brand primary stays #0066A7 on active pill / button hover (light/dark)
  - Page body bg = #0f1117 (dark) / #F2F4FB (light)
  - Body text contrast ≥ 4.5:1 (WCAG AA)
- [ ] **Toggle high-contrast** (if exposed in UI) — page bg should turn
  white, text black, large fonts. Note: "Production data polish"
  bug not fixed (rules still render with dark colours inside high-contrast
  — pre-existing; out of scope for P1 #13).
- [ ] **Browser console** — zero CSS warnings / SCSS compile errors

## Files touched

- `moodle-enhancement/theme/airpayux/scss/moodle/dark_mode.scss` (refactored)
- `moodle-enhancement/theme/airpayux/version.php` (bumped to invalidate CSS cache)
- `moodle-enhancement/PROJECT-STATE.md` (appended H2 with summary)
- `moodle-enhancement/docs/visual-evidence/2026-05-24/p1-followup-chip-I/README.md` (this file)

## Files NOT touched (out of scope per chip brief)

- `_surface-*.scss` partials (other chips may be editing)
- `_tokens.scss` / `_tokens-dark.scss` (token definitions stable)
- `custom_changes.scss` (orchestrator)
- `_dark-mode-global.scss`, `_bizlms-dark.scss` (sibling partials —
  conflicts noted, harmonisation flagged as audit follow-up)
- Any plugin code, .mustache template, PHP file, or lang string
