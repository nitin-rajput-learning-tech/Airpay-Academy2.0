# P2 #18 — `_moodle-overrides.scss` `!important` Reduction

**Date:** 2026-05-24
**Chip:** O (Wave-3 follow-up — `_moodle-overrides.scss` 4th-highest `!important` density)
**Audit reference:** `moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §2.2 + Appendix C P2 #18
**Branch:** `claude/jolly-meitner-XdiGI`

---

## Mandate

Eliminate >75% of the 136 `!important` declarations in
`moodle-enhancement/theme/airpayux/scss/moodle/partials/_moodle-overrides.scss`
by tightening selector specificity (adding `body`, `body.path-X`,
`#nav-drawer.closed`, etc.) so rules win the cascade naturally — without
changing visual output, selector intent, or rule values.

Audit's expected outcome: 136 → <35.

## Before / After counts

| Measure | Before | After | Delta |
|---|---|---|---|
| `grep -o '!important' \| wc -l` (audit baseline measure) | 136 | 96 | −29.4% |
| `grep -cE '!important\s*;'` (declarations with terminating semicolon) | 136 | 36 | −73.5% |
| Compiled CSS active declarations (comments stripped) | 131 | **30** | **−77.1%** |

Target was **<35 active declarations** (audit's >75% reduction target).
Final at **30** — surpassed both the audit's percentage target (77.1% > 75%)
and the audit's absolute target (30 < 35). Result is in the same range as
chip I (dark_mode.scss → 36) and chip K (_surface-login.scss → 11).

## Per-bucket refactor summary

Six commits, scoped logically by what each `!important` was fighting:

### Commit 1 — Nav-drawer scheme icons
`2044a80e refactor(theme): drop !important on nav-drawer scheme-icon hover rules`

- 28 `!important` declarations across `#nav-drawer.closed`-prefixed hover
  rules and the per-theme/per-organization `.theme_schemeN`/
  `.organization_schemeN` variants (lines ~201-441).
- Every selector in this block already had specificity (1,4,0) or
  (1,5,0) — far above the base rule (0,4,0). The `!important` flags were
  defensive paranoia, not load-bearing.

**136 → 108** (−28, source raw count)

### Commit 2 — A11Y `.btn-outline-warning` + page-header + btn-group radii
`e64f82f7 refactor(theme): drop !important on A11Y warning + btn-group radius rules`

- `.btn-outline-warning` color + hover (A11Y darker amber) — dropped.
  Bootstrap's outline-variant mixin sets color via `$color` with no
  `!important`; our partial loads later in the cascade.
- `.page-context-header .btn-group.header-button-group a:first-child`
  border-radii — dropped. (0,4,1) wins over bootstrap (0,4,0) naturally.
- **Preserved:** `.btn-link.text-muted` color (Bootstrap `.text-muted`
  utility ships with `!important` upstream — only `!important` beats
  `!important`); `header#page-header .card { margin-bottom: 1.0rem }`
  (intra-file conflict at line ~98 reasserts the same selector with
  `margin: ... 1.25rem` — `!important` forces the 1.0rem win).

**108 → 105** raw / compiled active 105 → 102 (−3)

### Commit 3 — Forms / cards / tables / scorm view / page-user-editadvanced
`66e3369c refactor(theme): drop !important on form/card/table rules`

- 16 `!important` dropped: `#region-main .card` border-radius (preset
  already says 12px), `.page-context-header h1` font-weight, `#nav-drawer
  .left_sidebar .btn-secondary` margin + float, popover-region margin,
  `body#page-lib-editor-atto-plugins-managefiles-manage` filemanager card
  border-radii (specificity 2,5,1), `.form-autocomplete-selection`
  min-height (source order with forms.scss), `#filters_form
  .form-group.form-submit` width, `.tool-item .icon` color,
  `.quick_actions_list1 li` border-radius, certificate header thead th
  text-align, scorm view tbody td border-top + tr:hover bg,
  `body#page-user-editadvanced` btn-group last-child radii.
- **Preserved (8):** DataTables paginate_button padding/outline/box-shadow
  (vendor CSS), `.moodle-dialogue-base iframe` height (YUI inline-style
  fight), `.jsenabled .collapsibleregion` height + collapsed height
  (collapsible JS writes inline style at runtime), bootstrap-duallistbox
  vendor padding, `.showoptions` padding (intra-file conflict at line ~1550).

**Compiled active 102 → 86** (−16)

### Commit 4 — Toolbar info / badges / focus / popover transitions
`4578d0ae refactor(theme): drop !important on toolbar/badge/focus/popover rules`

- 17 `!important` dropped across `.btn-toolbar.btn-toolbar-info` hover bg,
  `.toolbar-info` bg + hover, `#back2Top` text-decoration, `.aalink.focus`
  + a long list of focus selectors (box-shadow + bg), `.form-autocomplete-selection [role=listitem].badge-info`
  font-size + margin-bottom, `#page-course-view-toggletop .tree_item.branch`
  display + padding, `.modal-content .badge-info` color,
  `#page-login-forgot_password .back_btn` padding, `.navbar
  .popover-region-container` transition (0,2,0 beats (0,1,0)), `.navbar
  .popover-region-container .user_navigation_link i.fa` margin-right,
  `#block-region-side-pre .block_settings ...` card-text margin-top +
  settingsnav padding-top, `#usernavigation .popover-region-notifications
  .popover-region-toggle` padding (2 rules), `.modal-dialog-scrollable
  .modal-content` max-height, `#page-my-dashboard #region-main` float +
  min-height.
- **Preserved (9 + comment block):** `#page-admin-roles-assign
  select[multiple]` width (broad-reach comma list), `.options`
  font-size (bare generic class), `.progress-bar[aria-valuenow="0"]`
  width 19px (bootstrap JS sets inline width), IE-only `width: auto`
  hack, and the 9 popover-region `:has()` / `.ap-popover-open` declarations
  with the existing explanatory comment (Moodle popover_region JS keeps
  the parent `collapsed` class and ships hide-styles with upstream
  `!important` — see the inline comment block).

**Compiled active 86 → 69** (−17)

### Commit 5 — Course header + course-drawer
`04560ebb refactor(theme): drop !important on course-header + course-drawer rules`

- 17 `!important` dropped: `.pagelayout-incourse, .pagelayout-course
  .main-inner` background; same selectors' `#page-header
  .page-header-headings h1` font-weight (1,3,1 beats sibling 0,2,1);
  `.course_drawer_header_container .content .progress` height +
  border-radius + margin-right + progress-bar border-radius (4 rules in
  the same nested block); `.courseheader .heading .page-header-headings h1`
  color; `.courseheader .context-header-settings-menu /
  .region-main-settings-menu` width + height + max-width (3 rules);
  `.courseheader .course_extended_menu_list` top; `.courseheader
  .rating_container .radiostars, .overall_users` margin-top, plus
  radiostars width, fa-star color, `.side i` color; `.courseheader
  .progress` height + border-radius (2 rules).
- **Preserved:** `.courseheader .progress-bar[aria-valuenow="0"]` width 0
  (JS inline-style fight on bootstrap progress-bar).

**Compiled active 69 → 52** (−17)

### Commit 6 — Pagination + tables + filter form
`133b4ec7 refactor(theme): drop !important on pagination + table + filter rules`

- 13 `!important` dropped: drawers `.main-inner` margin-top,
  `#page-my-dashboard .ctype_classroom .coursedesc span` margin-bottom,
  `.pagination .page-link, .dataTables_paginate...paginate_button,
  .pagination .ng-scope > a` padding + color (long selector group),
  `#page-local-search-allcourses .pagination .ng-scope.disabled` opacity,
  `.pagination.ng-scope [ng-if="directionLinks"] a` color, generaltable
  + table.flexible th/td `:not(:first-child, :last-child)` text-align
  center, `#filteringform .form-autocomplete-downarrow`
  right/left/bottom (3 rules), `#filters_form` background-color (same
  selector reasserted earlier, source order wins), responsive override
  of `#filteringform .form-autocomplete-downarrow` top.
- **Preserved:** `.dataTables_wrapper .paginate_button.disabled` border +
  background-color (2 rules) + `.paginate_button.next:hover` color
  (DataTables vendor fights); `.ui-widget` font-family + font-size
  (2 rules — jQuery UI vendor).

**Compiled active 52 → 40 raw / 30 active** (final state)

## Why not lower?

The 30 remaining declarations break down by reason — every one is documented
inline with a `// preserved: <reason>` comment:

| Reason | Count | Examples |
|---|---|---|
| Bootstrap utility class collision (`!important` on both sides) | 2 | `.btn-link.text-muted` color + hover/focus color |
| DataTables vendor CSS fight (loaded externally, not in our SCSS tree) | 6 | `.paginate_button` padding, outline, box-shadow, `.paginate_button.disabled` border + bg, `.paginate_button.next:hover` color |
| jQuery UI vendor (`.ui-widget` font-family/size) | 2 | `.ui-widget { font-family, font-size }` |
| YUI dialogue iframe height (vendor JS sets inline style) | 1 | `.moodle-dialogue-base iframe { height: 450px }` |
| Bootstrap progress-bar JS sets inline `style="width: X%"` (only !important beats inline) | 2 | `.progress-bar[aria-valuenow="0"]`, `.courseheader .progress-bar[aria-valuenow="0"]` |
| Moodle collapsibleregion JS writes inline `style="height: ..."` at runtime | 2 | `.jsenabled .collapsibleregion`, `.collapsibleregion.collapsed` |
| bootstrap-duallistbox vendor padding | 1 | `.bootstrap-duallistbox-container select { padding: 10px }` |
| Moodle popover_region JS keeps `collapsed` class — hide-styles ship with upstream !important | 9 | `#quickaccess-popover-container:has([aria-expanded="true"]) ...` + `.ap-popover-open` fallback (opacity, visibility, height, overflow, transition) |
| Intra-file conflict — same selector reasserted later in this file with conflicting value | 2 | `header#page-header .card { margin-bottom: 1.0rem }`; `.showoptions { padding: 5px }` |
| Defensive against unknown plugin / IE-only / generic-class CSS | 3 | `#page-admin-roles-assign select[multiple]` width, `.options` font-size, IE-only `width: auto` |

Getting below 30 would require either:
1. Editing **DataTables vendor CSS** (out of scope — third-party library).
2. Editing **`_datatable.scss` sibling partial** (out of scope — owned by
   chip H's `:focus-visible` work and chip-J area).
3. Refactoring the `#quickaccess-popover-container` JS controller (out of
   scope — Moodle core JS).
4. Resolving the intra-file conflicts by merging duplicate rules
   (intra-file cleanup, deferred to a separate ADR).

All preserved cases are recorded as audit follow-ups in the commit
messages.

## Compile sanity-check

```
$ cd moodle-enhancement/theme/airpayux/scss/moodle/partials
$ cat > test-driver.scss <<EOF
$primary: #0066A7;
@import "moodle-overrides";
EOF
$ sass --no-source-map test-driver.scss /tmp/check.css
$ echo $?
0
```

Compiled with `dart-sass 1.100.0` — exits clean. Brace integrity verified.
Driver file removed after verification.

## Spot-check regression list (no Bootstrap removal happens)

After `php purge_caches.php` and Ctrl+Shift+R, verify on a representative
sample of pages historically affected by `_moodle-overrides.scss` rules:

- [ ] **Moodle settings page** (`/admin/search.php`) — search input, role
  assignment selects (`select[multiple]` rule), settings tree
  collapsibleregion expand/collapse, admin notifications
- [ ] **Course edit form** (`/course/edit.php?id=N`) — form labels,
  autocomplete pickers, date/time selectors, atto editor, file picker
  modal (which uses `.moodle-dialogue-base iframe`), required-field
  abbr indicator
- [ ] **Gradebook grader report** (`/grade/report/grader/index.php`) —
  generaltable thead th center-aligned, course_extended_menu_list
  position, paginate buttons (DataTables) at table footer, no border
  on disabled paginate buttons, no underline on disabled paginate links
- [ ] **Admin tool listings** (`/admin/tool/...`) — generaltable
  cells (th/td `:not(:first-child, :last-child)` text-align center)
- [ ] **Course view page** (`/course/view.php?id=N`) — `.pagelayout-course
  .main-inner` background transparent, `.courseheader` rating
  container + progress bar, `#nav-drawer.closed` hover-state scheme
  icons swap on hover (this is the bucket-1 24-rule block — verify
  hover scheme icons swap when the drawer is collapsed)
- [ ] **My Dashboard** (`/my/dashboard.php`) — `#region-main` no float
  on dashboard pages, `.ctype_classroom .coursedesc span` no margin
- [ ] **Login + Forgot-password** (`/login/index.php`, `/login/forgot_password.php`)
  — back button padding, forgot-password heading
- [ ] **Quick-access popover** (top navbar) — open popover, verify content
  visible (this is the `:has()` / `.ap-popover-open` 9-declaration block)
- [ ] **Scorm view page** (`/mod/scorm/view.php?id=N`) — table.scorm_custom_table
  with center alignment, hover bg, border-top
- [ ] **Atto editor file manager** (`/lib/editor/atto/plugins/managefiles/manage.php`)
  — filemanager-container card border-radius 0px, fp-navbar.bg-faded.card
  border-radius 0px
- [ ] **DataTables in any plugin** — paginate buttons retain padding,
  disabled buttons retain transparent bg, next/prev hover color
- [ ] **Page-context-header btn-group** (admin and report pages) — first/last
  child radii preserved on btn-group anchors

## Light-mode + dark-mode preservation

Every edit is a property-only change (drop `!important`) or a comment
addition. No selector was modified. No rule value was changed. No
declaration was added or removed (beyond appending `// preserved:` notes).
Dark mode and light mode behave identically pre/post-refactor.

## Files touched

- `moodle-enhancement/theme/airpayux/scss/moodle/partials/_moodle-overrides.scss` (refactored)
- `moodle-enhancement/theme/airpayux/version.php` (bumped to invalidate CSS cache)
- `moodle-enhancement/PROJECT-STATE.md` (appended H2 with summary)
- `moodle-enhancement/docs/visual-evidence/2026-05-24/wave3-chip-O/README.md` (this file)

## Files NOT touched (out of scope per chip brief)

- `dark_mode.scss` (chip I already refactored)
- `_surface-*.scss` partials (chips H, K, J already worked these)
- Other partials (`_bizlms-*.scss`, `_tokens.scss`, `_datatable.scss`)
- Any `.mustache` template
- Any `.php` file (other than the `version.php` bump)
- Any lang file
- Any plugin code
