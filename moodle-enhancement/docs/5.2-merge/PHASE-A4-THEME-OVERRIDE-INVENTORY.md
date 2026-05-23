# Phase A.4 — Theme override inventory (pre-5.2 source diff)

ADR-011 Phase A.4 deliverable. Snapshot at tag `v4.1.1-pre-merge`.

Maps every override surface in `theme/airpayux/` so we know the exact
conflict footprint going into the wholesale 5.2 merge. The actual
file-by-file conflict resolution (Phase A.4b) needs the 5.2 source on
disk to compute the upstream-changed set; this document is the
"our side" of that equation.

---

## Headline numbers

```
Total theme/airpayux files:          686
  scss/*                             215  (compiles to ~3 CSS bundles)
  pix/* + pix_plugins/* + pix_core/* 211  (images, mostly stable)
  templates/*                        104  (HIGH-RISK MERGE SURFACE)
  amd/* (src + build)                 89  (medium-risk)
  classes/*                           18  (renderer override + traits)
  tests/*                             21  (no upstream conflict)
  layout/*                            10  (medium-risk)
  lang/*                               6  (Hindi + English; no conflict)
  cli/*                                2  (theme-only utilities)
  scss-root + style/ + config.php      ~5
```

The merge-risk-weighted view:

| Surface | Files | Risk | Why |
|---------|-------|------|-----|
| `templates/core_form/` | 52 | HIGH | mform widget overrides — Boost theme rewrites these often. Each one needs a 3-way diff. |
| `templates/core/` | 5 | HIGH | loginform, block, maintenance, otplogin, email_html — Boost rewrites all of these in 5.2 |
| `templates/components/` | 11 | LOW | Our own components; no upstream version |
| `templates/core_courseformat/` | 2 | MEDIUM | P0 #2 + P0 #13 touched these; 5.2 ships its own iteration |
| `templates/block_myoverview/` | 1 | LOW | P0 #14 (just landed); 5.2 supersedes our override entirely |
| `templates/tool_certificate/` | 0 (empty) | NIL | empty dir; ignore |
| `templates/*.mustache` (33 root) | 33 | LOW | theme-only top-level templates (navbar, footer, dashboard, drawers, etc.) — no upstream equivalent |
| `layout/*.php` | 10 | MEDIUM | columns1/2, dashboard, login, course, drawers — pure theme code, but the Page Layouts API in Moodle 5.2 may change calling contract |
| `classes/output/*` | ~13 | HIGH | core_renderer override (2129 lines!), traits, role_detector |
| `scss/moodle/partials/*` | 27 | LOW | our own SCSS; no upstream conflict |
| `amd/src/*.js` | 18 | MEDIUM | Some borrow from 5.2 (announcement, page_title, deprecated, user_status_badge); others theme-only |

---

## High-risk merge sites (cross-reference for Phase B.3)

### 1. `templates/core_form/` (52 files)

mform widget overrides. Examples include:

```
editor_textarea.mustache         element-advcheckbox-inline.mustache
element-advcheckbox.mustache     element-button-inline.mustache
element-button.mustache          ... (47 more)
```

Risk: Moodle 5.2's Boost theme overhauls form widgets aggressively
(BS4 → BS5 + WCAG 2.1 AA pass). Most of these will conflict.

Strategy: for each conflicting mform widget, manually re-apply our
visual customisation on top of the new 5.2 markup. Likely a full
afternoon's work. Estimate 30-45 min per widget × 30 conflicting = 15-20h.

Mitigation: We can defer the visual polish of these widgets until
*after* the merge. The merge resolution can be "take theirs" and the
visual catch-up becomes a separate Goal A.x.2 sprint.

### 2. `templates/core/` (5 files)

```
core/block.mustache              ← block chrome (drawer + cards)
core/email_html.mustache         ← transactional email shell
core/loginform.mustache          ← P0 #5 + Sentientia login redesign
core/maintenance.mustache         ← maintenance-mode shell
core/otploginform.mustache       ← OTP mobile login (Sentientia bespoke)
```

Risk:
- `loginform.mustache` is heavily Sentientia (P0 #5 + redesign).
  Merge strategy: take ours + cherry-pick any 5.2 additions (e.g. new
  ARIA attributes, new auth-method buttons).
- `email_html.mustache` is touched but mostly cosmetic. Either side OK.
- `maintenance.mustache` is our maintenance-mode redesign. Take ours.
- `otploginform.mustache` has NO upstream equivalent — pure additive,
  no conflict.
- `block.mustache` — needs case-by-case look.

### 3. `classes/output/core_renderer.php` + 7 traits (1 file, ~2129 lines)

The big one. The renderer + traits implement:

```
- Branding (logo, favicon, copyright)         classes/output/traits/branding_*
- Context header (course context indicators)  classes/output/traits/context_header.php
- Course-view single-activity routing         classes/output/traits/course_view.php
- Login UI helpers                            classes/output/traits/login_render.php +login_ui.php
- Page helpers (sticky elements, breadcrumb)  classes/output/traits/page_helpers.php
- User-menu rendering                         classes/output/traits/user_menu.php
```

Risk: HIGH. Moodle's `core_renderer` evolves with every release. Our
fork extends it for ~50 methods. The merge strategy is to extract our
extensions into a trait that gets composed onto the new 5.2
`core_renderer` — basically a re-implementation, but the *interface*
is stable.

Mitigation: We already use traits! Most of our extension code is in
`classes/output/traits/`. The merge becomes "rewrite the thin
core_renderer.php glue + keep the traits unchanged". Estimate 4-6h
focused.

### 4. `layout/*.php` (10 files)

```
columns1.php          ← 1-column layout for embedded pages
columns2.php          ← main 2-column layout
course.php            ← course-page layout
dashboard.php         ← dashboard layout (Sentientia bespoke)
drawers.php           ← drawer-based layout (Sentientia bespoke)
embedded.php          ← embedded iframe layout
frontpage.php         ← site frontpage
login.php             ← login layout
maintenance.php       ← maintenance shell
secure.php            ← secure-page layout
```

Risk: MEDIUM. The Page Layouts API contract is generally stable but
5.2 may add new conventions. Strategy: keep ours; manually merge any
new options that arrive.

### 5. `amd/src/*.js` (18 files)

Our AMD modules. Three groups:

**5.2-borrow shims (these get DELETED on the merge):**
```
announcement.js           ← P0 #6 (replaced by core/toast in 5.2)
page_title.js             ← P0 #7 (replaced by core/page_title in 5.2)
deprecated.js             ← P0 #8 (replaced by core/deprecated in 5.2)
user_status_badge.js      ← P0 #10 (replaced by 5.2 report-renderer hook)
```

**Theme-only (no conflict):**
```
aria.js, carousel.js, datatable.js, drawer.js, drawers.js,
footer-popover.js, form-display-errors.js, index.js,
mobile-bottom-nav.js, navbar.js, password-toggle.js,
quickactions.js, sidebar.js
```

**Forked from upstream (POTENTIAL conflict — likely small):**
```
form-display-errors.js    ← fork of core/form_display_errors
```

Net plan for AMD: delete the 4 borrow shims on the merge, keep the
13 theme-only modules unchanged, manually re-merge form-display-errors
if it diverges.

---

## Easy/no-conflict surfaces

### `pix/` + `pix_plugins/` + `pix_core/` (211 files)

Icons, logos, brand assets. No upstream equivalent for our brand
assets. The only conflict would be `pix_core/` if we override default
Moodle icons — review during Phase B.

### `scss/moodle/partials/` (27 partials)

Our SCSS partials are entirely additive. No upstream conflict.

### Top-level mustaches (33 files)

```
admin_setting_tabs.mustache   columns1.mustache    custom_menu_footer.mustache
blocks-drawer.mustache         columns2.mustache    dashboard.mustache
course.mustache                drawer.mustache      drawers.mustache
embedded.mustache              flat_navigation.mustache    footer.mustache
full_header.mustache           head.mustache        language_menu.mustache
language_menu_dropdown.mustache  login.mustache    maintenance.mustache
... (16 more)
```

These are theme-namespaced templates with no `<component>/` prefix —
they live at `theme_airpayux/<name>` and have no upstream conflict.

---

## Pre-merge work order (when we have 5.2 source)

Phase A.4b — after `~/moodle-5.2-source/` exists:

1. For each `templates/core/<file>.mustache` in our theme — diff
   against `~/moodle-5.2-source/theme/boost/templates/core/<file>.mustache`.
   If unchanged → no merge action. If changed → record resolution
   strategy (take ours / take theirs / cherry-pick / re-implement).
2. Repeat for `templates/core_form/`, `templates/core_courseformat/`,
   `templates/block_myoverview/`.
3. Diff `classes/output/core_renderer.php` against `~/moodle-5.2-source/
   lib/outputrenderers.php::core_renderer` — record signature changes
   in our overridden methods.
4. Diff `layout/*.php` against `~/moodle-5.2-source/theme/boost/layout/*.php`
   for Page-Layouts API contract changes.

Output `docs/5.2-merge/PHASE-A4B-CONFLICT-MAP.md` with one row per
conflict + resolution strategy + estimate.

---

## Exit criteria for Phase A.4 (this document)

- [x] Theme surface counted (686 files)
- [x] Breakdown by type produced
- [x] High-risk merge sites identified (4 categories)
- [x] Low-risk / no-conflict surfaces flagged
- [x] AMD borrow-shim deletion plan documented
- [x] core_renderer trait-extraction strategy documented
- [ ] 5.2 upstream source on disk → Phase A.4b
- [ ] Per-file conflict matrix → Phase A.4b

A.4b is the next document. Blocked on 5.2 source pull (Phase A.2).

---

## Notes for Nitin

The `core_renderer.php` (2129 lines) and 52 mform overrides are the
heaviest part of the merge. If we want a faster merge:

1. Defer the visual polish of mform widgets to a post-merge sprint
   (12-20h sprint, separate from the merge).
2. Keep `core_renderer.php` resolution focused on signatures-only —
   any new visual customisation also lands in a post-merge sprint.

This is a "merge fast, beautify after" strategy. Trade-off: there's a
window where the LMS looks 80% like Sentientia and 20% like default
Boost. Acceptable if the merge happens during a quiet period.

Alternative: invest 30-40h up front into the merge so the visual is
unchanged on day 1. Decision matters when we schedule the production
deploy window.
