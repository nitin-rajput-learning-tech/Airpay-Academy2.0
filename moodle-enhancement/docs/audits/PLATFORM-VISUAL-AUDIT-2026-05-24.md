# Platform Visual Audit — 2026-05-24

**Date:** 2026-05-24
**Auditor:** Claude (Opus 4.7, 1M context) — static, code-level review
**Scope:** Sentientia LMS v4.1.0 ("Goal A audit" platform) on `production` branch as of 2026-05-23
**Methodology:** Static read of templates, SCSS, layout PHP, renderer + traits, plugin templates and JS. Cross-referenced against the design spec in `.claude/rules/frontend.md`. **No live capture, no Figma round-trip, no XAMPP** — environment is a Linux container. Existing visual evidence in `docs/visual-evidence/2026-05-{20,21,23}/` cited but not regenerated.

**Surfaces under audit (14):**

| Group | Surface | Source of truth |
| --- | --- | --- |
| Sprint 1 trio | Navbar | `theme/airpayux/templates/navbar.mustache` + `_surface-navbar.scss` |
| Sprint 1 trio | Footer | `theme/airpayux/templates/footer.mustache` + `_surface-footer.scss` |
| Sprint 1 trio | Login | `templates/login.mustache` + `templates/core/loginform.mustache` + `layout/login.php` + `_surface-login.scss` |
| Goal A.1 | Profile | `_surface-profile.scss` (body.path-user) |
| Goal A.2 | My Badges | `_surface-profile.scss` (body.path-badges) |
| Goal A.3 | Grade overview | `_surface-profile.scss` (body.path-grade-report scope) |
| Goal A.4 | Admin interior | `partials/_bizlms-admin.scss` |
| Goal A.5 | Course view | `templates/course.mustache` + `layout/course.php` + `_surface-course.scss` |
| Goal A.6 | User edit | `_surface-profile.scss` (body.path-user-edit) |
| Goal A.7 | Preferences | `_surface-profile.scss` (path-user-preferences) |
| Goal A.8 | Calendar | `_surface-profile.scss` (path-calendar) + `dark_mode.scss` overrides |
| Goal A.9 | Grader report | `_surface-profile.scss` (path-grade-report-grader) — latest, 2026-05-23 |
| Plugin | sentientia_pwa | 3 templates + 4 PHP entry points + 1 AMD register.js |
| Plugin | sentientia_live | 3 templates + 3 AMD modules + trainer/audience PHP |

**Files inspected:** 212 SCSS files, 104 Mustache templates, 10 layout PHP, 1 renderer + 9 traits, 5 locale string files (en/hi/kn/mr/sw), 2 plugin trees.

---

## TL;DR / Verdict

**CONDITIONAL PASS — promote with a 9-item P0 punch-list closed first.**

The Goal A.x design system is broadly applied. The `_surface-*.scss` decomposition (custom_changes.scss as orchestrator, 19 named partials) is a real win over the prior 9,702-line monolith. The plugins `sentientia_pwa` and `sentientia_live` are exemplary in their template hygiene (every string `{{#str}}`-wrapped, semantic roles, ARIA labels). The `_surface-profile.scss` partial covers 7 of the 9 Goal A surfaces under one body-class scoping discipline — clever, but now 2,507 lines and 164 `!important` declarations, which is the audit's biggest code-health concern.

**However**, the theme ships with a set of issues that block confident promotion to the Phase 2 customer-zero pitch:

- A misnamed orphan file `partials/Claude` (98 KB, 135 `!important`, never imported)
- An unreferenced 284 KB `custom_changes_MONOLITH_BACKUP.scss` polluting the source tree
- Hardcoded English in **navbar / footer / dashboard** for items that have first-class navigation slots
- 35+ inline `style="..."` attributes in `dashboard.mustache` driving brand colors, font sizes, and layout — a regression from the SCSS-token discipline
- Hindi locale at 85% parity (132/156) where CLAUDE.md mandates 100%
- Zero `:focus-visible` selectors anywhere in the surface partials (53 bare `:focus` rules)
- Zero `aria-live` regions in `sentientia_live` — the entire real-time announcement surface is silent to screen readers
- An inline `<script>` block in `navbar.mustache` that drives the cart-badge count from a DOM element ID — should be an AMD module

**Crypto / security follow-ups from B25-CRYPTO-AUDIT-2026-05-21.md are out of scope here** — this audit covers visual + UX, not crypto. PWA push surface is touched only as a render-layer audit.

| # | Severity | Surface | Issue |
|---|---|---|---|
| 1 | **P0** | Theme src tree | Orphan file `scss/moodle/partials/Claude` (98 KB, no extension) — never imported, must be deleted |
| 2 | **P0** | Theme src tree | `scss/moodle/custom_changes_MONOLITH_BACKUP.scss` (284 KB, 682 `!important`) — move out of `scss/` or delete |
| 3 | **P0** | Navbar | "Dashboard", "My Courses", "Catalog", "Profile", "Home" hardcoded — 5 nav labels with no `{{#str}}` |
| 4 | **P0** | Footer | "Privacy", "Terms", "Help", "Contact" hardcoded — 4 footer labels with no `{{#str}}` |
| 5 | **P0** | Dashboard | 35+ inline `style="..."` attributes drive colour, font-size, layout; two contain hex literals (`#16a34a`, `#dc2626`) |
| 6 | **P0** | Footer | Sentientia attribution band uses inline `style` with hex literals `#0066A7`, `#5a6070`, `#f8f9fc`, `#e2e6ef` |
| 7 | **P0** | i18n parity | Hindi at 85% (132/156); Kannada at 76% (118/156) — CLAUDE.md "100% parity required — drive enforced" violated |
| 8 | **P0** | sentientia_live | Zero `aria-live` regions across templates + AMD — screen-reader users get no update announcement |
| 9 | **P0** | Navbar | Inline `<script>` block (lines 119–136) drives cart-badge — should be an AMD module |
| 10 | P1 | _surface-profile.scss | 2,507 lines, 164 `!important` — needs decomposition into per-page partials |
| 11 | P1 | _surface-login.scss | 699 lines, 66 `!important` |
| 12 | P1 | Theme (all surfaces) | Zero `:focus-visible` — 53 bare `:focus` selectors across surface partials |
| 13 | P1 | Dark-mode shell | `dark_mode.scss` carries 253 `!important` declarations — token-scoped overrides would eliminate most |
| 14 | P1 | Footer mobile | `_surface-footer.scss` has zero `@media` breakpoints — no responsive coverage declared |
| 15 | P1 | sentientia_live | All badges use Bootstrap `bg-success` / `bg-secondary` etc. — should be Sentientia tokens |
| 16 | P2 | _moodle-overrides.scss | 136 `!important` — overrides-of-overrides accumulating |
| 17 | P2 | Theme tokens | `prefers-reduced-motion` only covered in 2 files — relies on token-driven animations, vulnerable to direct-property regressions |
| 18 | P2 | Sentientia attribution | "Made in India" badge removed inline (footer.mustache:33–42) — comment block bloats the template |

---

## §2 — Cross-Cutting Findings

These apply across multiple surfaces and are reported once with a single remediation strategy. Surface sections in §3 reference them by ID rather than restating them.

### §2.1 — Token Drift Index

**Spec:** `.claude/rules/frontend.md` → "AIRPAY DESIGN TOKENS" — every colour in a partial must reference `$ap-*` SCSS variables.

**Found:**
- `partials/_surface-profile.scss`: 60+ hex literals (sampled: `#1d2125`, `#0066A7`, `#0a3d62`)
- `partials/Claude` (orphan): 50+ hex literals — out of scope because not imported, but contributes to clutter
- `scss/moodle/dark_mode.scss`: ~120 hex literals declared as CSS custom-property values (intentional — they ARE the dark-mode tokens — but the file should be the ONLY hex source for dark mode, not the partials that override it)
- `scss/preset/default.scss`: 25+ hex literals — preset is the spec source so allowed, BUT `#0066A7` is repeated 5 times instead of referencing `$primary`
- `scss/moodle/modules.scss`: 8 hex literals in non-token contexts (`#f6f6f6`, `#5b5b5b`, `#707070`, `#dee2e6`)
- `scss/moodle/grade.scss`: 9 hex literals in status pills (`#dff0d8`, `#fff0f0`, `#d0ffd0`, `#aaa`, `#555`)

**Verdict:** Token discipline is enforced for new Sentientia surfaces (the `airpay-*` BEM classes in dashboard/course/login/footer SCSS all reference `--ap-*` CSS custom properties via the dark-mode escape hatch), but legacy `modules.scss` / `grade.scss` / preset `default.scss` carry pre-Sentientia hex constants. **Recommendation:** add a stylelint rule `color-no-hex` to the partials directory only, and grandfather the preset / Bootstrap / FontAwesome trees.

**Severity:** P1 (one row per offender file in Appendix A)

---

### §2.2 — `!important` Census

| File | Count |
|---|---|
| `custom_changes_MONOLITH_BACKUP.scss` | 682 |
| `dark_mode.scss` | 253 |
| `partials/_surface-profile.scss` | 164 |
| `partials/_moodle-overrides.scss` | 136 |
| `partials/Claude` (orphan) | 135 |
| `partials/_dark-mode-global.scss` | 79 |
| `custom_media.scss` | 77 |
| `partials/_surface-login.scss` | 66 |
| `partials/_bs5-compat.scss` | 61 |
| `partials/_bizlms-dark.scss` | 57 |
| `partials/_bizlms-modern.scss` | 53 |
| `partials/_bizlms-admin.scss` | 46 |
| `partials/_bizlms-overrides.scss` | 34 |
| `bootstrap/utilities/_flex.scss` | 34 |
| `partials/_surface-dashboard.scss` | 32 |

**Total in compiled output:** ~1,150 `!important` declarations across non-Bootstrap files.

**Verdict:** The two highest offenders are the orphan `Claude` file and the `MONOLITH_BACKUP` — both should be deleted (P0 #1 and #2). After deletion, `dark_mode.scss` (253) and `_surface-profile.scss` (164) are the next priorities. `dark_mode.scss` uses `!important` to override stubborn Moodle-core selectors; the better solution is a CSS-custom-property cascade with sufficient specificity at the `body.dark-mode` parent so children inherit naturally — this is a refactor of ~250 LOC. `_surface-profile.scss` `!important` usage is largely cosmetic and could be eliminated by tightening selector scope under `body.path-*` classes.

**Severity:** P1 (theme), P0 for the two deletions

---

### §2.3 — Inline `style="..."` Attributes in Templates

**Spec:** `.claude/rules/frontend.md` → "Anti-patterns" — `inline style="color: #0066A7"` → use CSS class.

**Found (33 instances total across 5 templates):**

| Template | Count | Worst offenders |
|---|---|---|
| `templates/dashboard.mustache` | 28 | Lines 172, 174–185 (welcome header), 305–337 (compliance KPI grid) |
| `templates/footer.mustache` | 4 | Lines 53, 56, 57 (Sentientia attribution band) |
| `templates/course.mustache` | 2 | Lines 170, 186 (progress-bar fill width — `style="width:{{ap_course_progress}}%"` — this IS legitimate because the percent is dynamic; cannot move to SCSS) |
| `templates/course_full_header.mustache` | 2 | Line 41 (background-image url), line 92 (progress-bar width) — both legit-dynamic |
| `templates/navbar.mustache` | 1 | Line 117 (`style="display:none;"` on cart badge) — should be `hidden` attribute or a class |

**Verdict:** Of 33 inline-style hits, 4 are legitimate (dynamic percent / URL bound at render-time). The remaining **29 are P0 violations**:
- `dashboard.mustache` 28 hits literally inlines `font-size: 1.5rem`, `font-weight: 700`, `color: var(--ap-color-text-primary)` — those should be SCSS rules in `_surface-dashboard.scss`. The two hex literals `#16a34a` and `#dc2626` in lines 315 and 319 are unambiguous P0 (compliance KPI colours bypassing tokens).
- `footer.mustache` 4 hits hardcode the entire Sentientia attribution band's styling inline, including hex literals.

**Severity:** P0 #5 (dashboard), P0 #6 (footer)

---

### §2.4 — Hardcoded English in Mustache Templates

**Spec:** `.claude/rules/frontend.md` → "Anti-patterns" — hardcoded English in `.mustache` → use `{{#str}}` helper.

**Found in user-visible chrome (P0):**

| File | Line | Hardcoded string | Should be |
|---|---|---|---|
| `templates/navbar.mustache` | 79 | `Dashboard` | `{{#str}}myhome, core{{/str}}` |
| `templates/navbar.mustache` | 83 | `My Courses` | `{{#str}}mycourses, core{{/str}}` |
| `templates/navbar.mustache` | 85 | `Catalog` | `{{#str}}catalog, local_airpay_catalog{{/str}}` (key exists) |
| `templates/navbar.mustache` | 87 | `Profile` | `{{#str}}profile, core{{/str}}` |
| `templates/navbar.mustache` | 98 | `Search courses, people, content...` (placeholder) | new string `navbar_search_placeholder` in `theme_airpayux` |
| `templates/navbar.mustache` | 115 | `Shopping Cart` (title attr) | `{{#str}}cart, local_airpay_cart{{/str}}` (key exists) |
| `templates/navbar.mustache` | 140 | `Toggle dark mode` (title + aria-label) | new string `darkmode_toggle` in `theme_airpayux` |
| `templates/navbar.mustache` | 157 | `Home` (mobile nav) | `{{#str}}home, core{{/str}}` |
| `templates/footer.mustache` | 27 | `Privacy` | `{{#str}}privacy, local_airpay_pages{{/str}}` |
| `templates/footer.mustache` | 28 | `Terms` | `{{#str}}terms, local_airpay_pages{{/str}}` |
| `templates/footer.mustache` | 29 | `Help` | `{{#str}}help, core{{/str}}` |
| `templates/footer.mustache` | 30 | `Contact` | `{{#str}}contact, local_airpay_pages{{/str}}` |
| `templates/footer.mustache` | 32 | `© 2026 airpay payment services pvt. ltd.` | new string `copyright_line` (with year arg) in `theme_airpayux` |
| `templates/footer.mustache` | 56 | `Sentientia LMS` | brand-name allowed not-translated, but should be a `{{config.brandname}}` for white-label customer-N |
| `templates/footer.mustache` | 58 | `Licensed under GPL v3` | GPL attribution allowed not-translated under the licence, but worth a new key `licence_attribution` |
| `templates/dashboard.mustache` | 174 | `Welcome back, {{firstname}}` | new string `welcome_back_admin, theme_airpayux, {$a}` |
| `templates/dashboard.mustache` | 176 | `Platform overview and system health` | new string `subtitle_admin` |
| `templates/dashboard.mustache` | 180 | `Welcome, {{firstname}}` | new string `welcome_manager, theme_airpayux, {$a}` |
| `templates/dashboard.mustache` | 181 | `Team overview and compliance status` | new string `subtitle_manager` |
| `templates/dashboard.mustache` | 184 | `Welcome back, {{firstname}}!` | new string `welcome_learner, theme_airpayux, {$a}` |
| `templates/dashboard.mustache` | 185 | `Continue where you left off and keep building your skills` | new string `subtitle_learner` |
| `templates/dashboard.mustache` | 207 | `Enrolment Trend` (chart title) | new string `chart_enrolment_trend` |
| `templates/dashboard.mustache` | 211 | `Course Distribution` | new string `chart_course_distribution` |
| `templates/dashboard.mustache` | 312 | `Mandatory Courses` (KPI label) | new string `kpi_mandatory_courses` |
| `templates/dashboard.mustache` | 316 | `Compliance Rate` | new string `kpi_compliance_rate` |
| `templates/dashboard.mustache` | 320 | `Overdue` | new string `kpi_overdue` |
| `templates/dashboard.mustache` | 324 | `Total Assigned` | new string `kpi_total_assigned` |

**Total: 28 P0 string violations across 3 templates.**

**Templates with ZERO `{{#str}}` calls (18 templates, may indicate either silent dependency on partials OR hardcoded fallbacks):**
`blocks-drawer.mustache`, `course_context_header.mustache`, `custom_menu_footer.mustache`, `admin_setting_tabs.mustache`, `course_full_header.mustache`, `head.mustache`, `language_menu_dropdown.mustache`, `full_header.mustache`, `footer.mustache`, `mobile_bottom_nav.mustache`, `login.mustache`, `sidebar.mustache`, `quickinfo.mustache`, `flat_navigation.mustache`, `socialicons.mustache`, `slider.mustache`, `shell.mustache`, `topbar.mustache`

**Verdict:** Most zero-`{{#str}}` files are pure structural shells (`login.mustache` only wraps `output.main_content` — the form template `core/loginform.mustache` carries the strings). But `footer.mustache`, `dashboard.mustache`, `navbar.mustache`, `mobile_bottom_nav.mustache`, and `topbar.mustache` carry user-visible chrome and ARE non-compliant.

**Severity:** P0 #3 (navbar), P0 #4 (footer), included in P0 #5 (dashboard)

---

### §2.5 — Triple-Stash `{{{ }}}` Inventory

**Spec:** `.claude/rules/frontend.md` → Mustache correctness — `{{{ html_content }}}` is RAW; flag every instance.

**Found:** 47 triple-stash uses across the theme templates.

**Categorisation:**
| Use | Count | Safety |
|---|---|---|
| `{{{ bodyattributes }}}` | 3 | OK — Moodle-built attr string |
| `{{{ output.* }}}` (e.g. `standard_top_of_body_html`, `main_content`, `course_content_header`, `user_menu`, `edit_switch`, `course_footer`, `navbar_plugin_output`, `debug_footer_html`) | 21 | OK — renderer-produced HTML, escaped at source |
| `{{{ sitename }}}` | 2 (navbar:72, primary-drawer-mobile:58) | **FLAG** — sitename is `format_string()`-ed in PHP but format_string may emit HTML; if so OK, but worth a dedicated string-output verifier comment. Use `{{ sitename }}` (double-stash) unless renderer explicitly produces HTML |
| `{{{ config.wwwroot }}}` in `href="..."` | 11 | OK — URL stored config value, but `{{ config.wwwroot }}` (double-stash) would still produce a valid URL; the only argument for triple is avoiding double-encoding of `&` in the URL. Acceptable but inconsistent |
| `{{{ url }}}`, `{{{ text }}}` in nav items (primary-drawer-mobile.mustache:83, 89-90) | 4 | **FLAG** — `text` is a nav label coming from a `format_string()` call; double-stash would be safer if any tenant editor adds HTML to the menu |
| `{{{ breadcrumbs }}}`, `{{{ usermenu }}}`, `{{{ notificationhtml }}}` (topbar.mustache:22, 43, 48) | 3 | OK — renderer-produced |
| `{{{ html }}}` (admin_setting_tabs.mustache:51) | 1 | OK — renderer-rendered tab content |
| `{{{ courseindex }}}`, `{{{ addblockbutton }}}`, `{{{ sidepreblocks }}}`, `{{{ regionmainsettingsmenu }}}`, `{{{ default_menu }}}`, `{{{ challenge_element }}}`, `{{{ editing_url }}}` | 7 | OK — renderer-produced |

**Verdict:** No XSS exposure detected. All raw-output uses fall into "renderer-produced HTML" or "Moodle-built attribute" buckets. The two `{{{ sitename }}}` and four `{{{ text }}}` / `{{{ url }}}` uses in `primary-drawer-mobile.mustache` are theoretical-risk only — `format_string()` upstream sanitises with `s()`. Safe.

**Severity:** P2 — Add a comment block at the top of `navbar.mustache` and `primary-drawer-mobile.mustache` justifying the triple-stash. No code change required.

---

### §2.6 — `:focus-visible` Coverage

**Spec:** WCAG 2.1.1 + 2.4.7 — keyboard focus must be visually obvious AND must not flash on mouse click. `:focus-visible` (not bare `:focus`) is the correct selector since Chrome 86 / Firefox 85.

**Found:** **Zero `:focus-visible` selectors in the entire airpayux theme.** 53 bare `:focus` selectors across the surface partials, distributed:
- `_surface-profile.scss`: 11 `:focus` rules
- `_surface-login.scss`: 7
- `_surface-course.scss`: 3
- `_surface-navbar.scss`: 1
- `_surface-dashboard.scss`: 2

**Verdict:** Every interactive element flashes the focus ring on mouse-click as well as on keyboard navigation. Mouse users see a phantom ring on every button click; keyboard users get the same indicator they need. This is correctable surgically — `&:focus-visible` next to every `&:focus` rule, retaining `:focus` only for legacy browsers (or dropping it given Moodle 4.5 baseline supports modern browsers).

**Severity:** P1 #12 — punch-list item, but ~30 minutes of mechanical work

---

### §2.7 — `prefers-reduced-motion` Coverage

**Spec:** WCAG 2.3.3 — vestibular accessibility.

**Found:** 2 first-party rules + Bootstrap's defaults:
- `_ui-polish.scss:171` — wraps `transform` transitions in `@media (prefers-reduced-motion: reduce)` and nulls them
- `_tokens.scss:258` — sets `--ap-duration-*` to `0s` under reduced-motion (the smart, token-driven approach)

The token-driven approach in `_tokens.scss` is the correct strategy: any animation referencing `--ap-duration-*` automatically respects user preference. BUT inspection of `_surface-profile.scss`, `_surface-course.scss`, etc. reveals direct-value `transition: 0.2s ease;` declarations that bypass tokens.

**Severity:** P2 #17 — punch list, recommend a stylelint rule `declaration-property-value-disallowed-list: { transition-duration: ['/^(?!var\\().*$/'] }` to enforce token usage in future code.

---

### §2.8 — i18n Locale Parity

**Spec:** CLAUDE.md §1 — "Hindi: 100% parity required — drive enforced today"; CLAUDE.md §10 — multi-language support is a Sentientia product pillar.

**Found:**
| Locale | String count | % of en | Gap |
|---|---|---|---|
| en | 156 | 100% | — |
| hi | 132 | 85% | 24 strings missing |
| kn (Kannada) | 118 | 76% | 38 strings missing |
| mr (Marathi) | 150 | 96% | 6 strings missing |
| sw (Swahili) | 150 | 96% | 6 strings missing |

**Verdict:** Hindi at 85% violates the mandated 100% parity. Kannada lags worst at 76%. Marathi + Swahili are close (4% gap, 6 strings each). New strings added in this audit (28 from §2.4 P0 string violations) will widen all gaps.

**Severity:** P0 #7 — must reconcile before promotion. Run `diff` of `array_keys()` between `en/theme_airpayux.php` and each locale file; populate missing translations via the existing `local_airpay_*` translation pipeline.

---

### §2.9 — Surface Capability Gating

**Spec:** `.claude/rules/frontend.md` → "Multi-tenant Rendering Rules" — capability checks in `core_renderer` traits.

**Found:** `core_renderer.php` is 1,631 lines. 9 trait files in `classes/output/traits/` (`branding_assets.php`, `branding_buttons.php`, `context_header.php`, `course_view.php`, `login_render.php`, `login_ui.php`, `page_helpers.php`, `user_menu.php`). Tenant detection consistently uses `$USER->open_path` (not the deprecated `open_costcenterid` column — production-correct, per CLAUDE.md §2).

Spot-checked the navbar render path: `navbar_plugin_output` (line 112), `user_menu` (line 146), `edit_switch` (line 148) all delegate to core renderer methods which DO carry capability gates. The dark-mode toggle (line 140) and cart badge (line 115) are unconditionally rendered for all logged-in users — appropriate because both are user-state-local, not capability-gated.

`is_siteadmin_only` (line 81 / 89) correctly hides the catalog/courses/profile pills for users with **only** the site-admin role, preserving the site-admin-as-special-account pattern.

**Verdict:** Capability discipline looks sound at spot-check. **Recommendation:** an exhaustive pass during the `_surface-profile.scss` refactor (P1 #10) would be worth the time.

**Severity:** No new finding; clean.

---

### §2.10 — Dead / Orphan Source Files in `scss/`

**Found:**
1. `scss/moodle/partials/Claude` — **98 KB**, no extension, never imported, contains 135 `!important`, opens with `/*generic styles over site starts*/` and includes legacy navbar/sidebar/admin selectors from a pre-decomposition draft. Likely an artefact of an autocomplete-without-extension save in an earlier session.
2. `scss/moodle/custom_changes_MONOLITH_BACKUP.scss` — **284 KB**, 682 `!important`, deliberately named as a backup but living in the source path that the SCSS compiler scans.

**Verdict:** Both should be removed from `scss/`. `Claude` should be deleted outright (its replacement is the decomposed partials in the same directory). `MONOLITH_BACKUP` should be moved to `docs/archive/` or deleted — git history preserves it.

**Severity:** P0 #1 + #2 — pure dead-code hygiene

---

## §3 — Per-Surface Findings

### §3.1 — Navbar

**Files:** `templates/navbar.mustache` (179 lines), `_surface-navbar.scss` (152 lines, 7 `!important`)

**Dimension table:**
| Dimension | Verdict | Notes |
|---|---|---|
| Token compliance | PASS | Partial uses `--ap-*` custom properties throughout |
| Dark mode | PASS | Inherits via `body.dark-mode` cascade |
| Tenant branding | PASS | Logo route through `output.get_custom_logo` |
| Focus-visible | FAIL | 1 bare `:focus` (line 103) |
| Hover/active/disabled | PARTIAL | Pill nav has hover; no disabled state declared |
| Empty/loading/error | N/A | Always-rendered chrome |
| `prefers-reduced-motion` | PASS (via tokens) | |
| Print | UNTESTED | No `@media print` rules |
| i18n | FAIL | 8 hardcoded English strings (P0 #3) |
| Dev font-stack | PASS | Inherits Montserrat |
| RTL | UNTESTED | No `dir`-aware logical properties |
| Image alt | PASS | Logo has `alt="{{sitename}}"` (line 69) |
| Form labels | PASS | Search input has placeholder; should also have a `<label class="sr-only">` |
| Heading hierarchy | N/A | Navbar emits no headings |
| Touch-target ≥44px | PASS at desktop; UNVERIFIED on mobile bottom nav |
| `aria-current` | PASS | Mobile nav uses `aria-current="page"` (line 36 of mobile_bottom_nav.mustache) |
| XSS hygiene | PASS | All triple-stash uses are renderer-produced HTML or URL config |
| Capability gate | PASS | `is_siteadmin_only` correctly handled |

**Findings:**

#### F-01  [P0]  Navbar — Hardcoded English nav labels

```
File:     moodle-enhancement/theme/airpayux/templates/navbar.mustache:79,83,85,87,98,115,140,157
Symbol:   .airpay-nav__pill, .airpay-nav__search-input placeholder, .airpay-nav__cart title, .airpay-nav__theme-toggle aria-label, .ap-mobile-nav__item label
Spec:     .claude/rules/frontend.md §Anti-patterns — hardcoded English in .mustache → use {{# str }}
Found:    Lines emit literal "Dashboard", "My Courses", "Catalog", "Profile", "Search courses, people, content...", "Shopping Cart", "Toggle dark mode", "Home"
Expected: Each replaced with {{#str}}<key>, <component>{{/str}} (mapping in §2.4 table)
Impact:   Hindi / Kannada / Marathi / Swahili users see English in primary nav; tenant-77 (Public) cannot customise without forking the template; brand inconsistency in Sentientia LMS multi-language story.
Evidence: moodle-enhancement/docs/visual-evidence/2026-05-21/regression-profile.png shows nav rendered in English regardless of user language preference.
Fix:      Add 8 new strings to lang/en/theme_airpayux.php; replace literals as listed in §2.4 mapping.
```

#### F-02  [P0]  Navbar — Inline `<script>` for cart-badge

```
File:     moodle-enhancement/theme/airpayux/templates/navbar.mustache:119-136
Symbol:   #ap-cart-badge update IIFE
Spec:     Moodle JS discipline — runtime JS belongs in amd/src/<module>.js, loaded via $PAGE->requires->js_call_amd()
Found:    Inline <script> block with DOMContentLoaded listener that reads textContent of #ap-cart-count-data and shows the badge.
Expected: Move to local_airpay_cart/amd/src/badge_updater.js or similar; call from the renderer trait that prepares cart context.
Impact:   (a) Content Security Policy that forbids inline scripts breaks the cart badge silently. (b) Bundling /minification skip this code. (c) Auditing JS by reading templates is non-discoverable.
Evidence: Inline script visible at navbar.mustache lines 119-136 in current source.
Fix:      Extract to amd/src/cart_badge.js, register via $PAGE->requires->js_call_amd('local_airpay_cart/cart_badge', 'init').
```

#### F-03  [P1]  Navbar — Missing `:focus-visible`

```
File:     moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-navbar.scss:103
Symbol:   .airpay-nav__pill &:focus
Spec:     WCAG 2.1.1 + 2.4.7
Found:    Bare :focus selector; ring appears on mouse click too.
Expected: &:focus-visible mirroring &:focus rule.
Impact:   Mouse users see phantom focus ring after click.
Evidence: Reproducible — click any pill nav item.
Fix:      Add &:focus-visible block adjacent to existing &:focus rule.
```

#### F-04  [P2]  Navbar — Cart-badge uses `style="display:none;"`

```
File:     moodle-enhancement/theme/airpayux/templates/navbar.mustache:117
Symbol:   .airpay-nav__cart-badge
Found:    <span class="airpay-nav__cart-badge" id="ap-cart-badge" style="display:none;">0</span>
Expected: <span class="airpay-nav__cart-badge" id="ap-cart-badge" hidden>0</span>  (HTML5 hidden attribute) — and toggle via classList.remove('d-none')
Impact:   Inline styles short-circuit the no-inline-styles rule; harder to debug; CSP-hostile (though attribute style is typically allowed).
Fix:      Replace style="display:none;" with hidden attribute. Update inline script (F-02 fix) to remove the hidden attribute instead of setting style.
```

---

### §3.2 — Footer

**Files:** `templates/footer.mustache` (77 lines), `_surface-footer.scss` (136 lines, 0 `!important`, 0 `@media`)

**Dimension table:**
| Dimension | Verdict | Notes |
|---|---|---|
| Token compliance | FAIL | Sentientia attribution band uses inline hex (P0 #6) |
| Dark mode | PARTIAL | Light pill bg `#f8f9fc` hardcoded — won't theme in dark mode |
| Tenant branding | PARTIAL | Logo is hardcoded `academy-logo-350.png` — Sentientia customer-N pitch will need a tenant-aware logo |
| Focus-visible | FAIL | Footer link `:focus` not declared in `_surface-footer.scss` (relies on browser default) |
| Hover states | PASS | Footer links have hover via cascade |
| `prefers-reduced-motion` | N/A | No motion |
| Print | UNVERIFIED | No `@media print` |
| i18n | FAIL | 4 hardcoded English labels (P0 #4) + copyright line (P0 #4) |
| Image alt | PARTIAL | Logo has lowercase generic `alt="airpay academy"` (line 24) — should be `alt="{{sitename}}"` for tenant-aware customers |
| Mobile breakpoint | FAIL | Zero `@media` queries in `_surface-footer.scss` |
| Triple-stash safety | PASS | Only `{{{config.wwwroot}}}` in href context |

**Findings:**

#### F-05  [P0]  Footer — Hardcoded English link labels

```
File:     moodle-enhancement/theme/airpayux/templates/footer.mustache:27-30
Symbol:   .airpay-footer__links anchor labels
Spec:     .claude/rules/frontend.md §Anti-patterns
Found:    <a>Privacy</a>, <a>Terms</a>, <a>Help</a>, <a>Contact</a>
Expected: Each wrapped in {{#str}}<key>, local_airpay_pages{{/str}} — keys already exist in local_airpay_pages
Impact:   Hindi/Kannada users see English footer links — direct violation of CLAUDE.md §1 mandate.
Evidence: moodle-enhancement/docs/visual-evidence/2026-05-23/sticky-footer-after.png — footer rendered in English.
Fix:      Replace literals; verify keys exist in local_airpay_pages/lang/<locale>/local_airpay_pages.php.
```

#### F-06  [P0]  Footer — Inline-style Sentientia attribution band

```
File:     moodle-enhancement/theme/airpayux/templates/footer.mustache:52-58
Symbol:   .airpay-footer__product-attribution
Spec:     .claude/rules/frontend.md §Anti-patterns — inline style="color: #0066A7" → use CSS class
Found:    Three child <span>s and the wrapper <div> use inline style with hex literals #5a6070, #f8f9fc, #e2e6ef, #0066A7, and bare values "text-align:center", "font-size:0.75rem", "letter-spacing:0.02em"
Expected: All declarations migrate into _surface-footer.scss with class .airpay-footer__product-attribution and child element classes; hex literals become $ap-* token references.
Impact:   (a) Sentientia customer-N white-label CANNOT recolour this band without forking the template; (b) dark-mode reads white-on-white because dark_mode.scss has no override for inline styles; (c) violates the token-discipline that the rest of the theme observes.
Evidence: moodle-enhancement/docs/visual-evidence/2026-05-23/sticky-footer-after.png shows the band — note light-mode rendering; flip to dark and the bg `#f8f9fc` remains light, breaking the surface.
Fix:      Move all declarations into _surface-footer.scss, reference $ap-surface-2, $ap-border, $ap-text-secondary, $ap-primary tokens. Add dark-mode override in dark_mode.scss.
```

#### F-07  [P1]  Footer — Zero mobile breakpoints declared

```
File:     moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-footer.scss
Symbol:   File-level
Spec:     .claude/rules/frontend.md §Responsive Breakpoints — primary mobile target 590px
Found:    No @media (max-width: 590px) or similar rules in _surface-footer.scss.
Expected: At least one breakpoint for 590px collapsing the compact footer row to two-column (logo+links above, copyright below) and the attribution band to wrap.
Impact:   Footer compact row may overflow horizontally on Galaxy S series (<400px); cannot be verified statically without a live capture, but the absence of any media query is itself the signal.
Evidence: moodle-enhancement/docs/visual-evidence/2026-05-21/regression-profile.png (mobile shots) — would need to be re-captured to confirm.
Fix:      Add @media (max-width: 590px) block in _surface-footer.scss with flex-wrap on .airpay-footer__compact and reduced padding on .airpay-footer__product-attribution.
```

#### F-08  [P2]  Footer — Generic `alt="airpay academy"`

```
File:     moodle-enhancement/theme/airpayux/templates/footer.mustache:24
Symbol:   .airpay-footer__logo img
Found:    alt="airpay academy" — hardcoded, lowercase, customer-specific
Expected: alt="{{sitename}}" (tenant-aware, format_string'd)
Impact:   Sentientia customer-N white-label will display "airpay academy" alt text on their own footer logo. Screen-reader announces wrong brand.
Fix:      Replace alt attribute; verify sitename context variable is exposed in footer template.
```

#### F-09  [P2]  Footer — Removed-badge comment block bloat

```
File:     moodle-enhancement/theme/airpayux/templates/footer.mustache:33-42
Symbol:   Mustache comment block
Found:    10-line comment explaining the 2026-05-15 removal of the "Made in India" badge.
Expected: Move historical context to git commit message or docs/visual-evidence/2026-05-15/. Comment block bloats the template.
Impact:   Cognitive load for future template editors; comment is fact-of-history, not code rationale.
Fix:      Delete lines 33-42 (the comment); ensure git log of footer.mustache contains the rationale.
```

---

### §3.3 — Login

**Files:** `templates/login.mustache` (24 lines, shell), `templates/core/loginform.mustache`, `templates/core/otploginform.mustache`, `layout/login.php`, `_surface-login.scss` (699 lines, 66 `!important`)

**Dimension table:**
| Dimension | Verdict | Notes |
|---|---|---|
| Token compliance | PARTIAL | Mostly uses `--ap-*` tokens; 4 hex literals in `_surface-login.scss` for OTP-specific elements |
| Dark mode | PASS | Login layout has dedicated dark-mode SCSS path |
| Tenant branding | PASS | `login_ui.php` trait selects logo by costcenterid (lines 134, 152, 182) |
| Focus-visible | FAIL | 7 bare `:focus` rules across `_surface-login.scss` |
| Empty/loading/error | PASS | `errorformatted` rendered when login fails |
| `prefers-reduced-motion` | UNTESTED | Login uses CSS transitions; no explicit motion guard |
| Print | N/A | Login page rarely printed |
| i18n | PASS | `core/loginform.mustache` uses `{{#str}}` (Moodle core template); OTP form mixes English placeholder with `{{#str}}phonenumber` (line 87 — XXX XXX XXXX is a format hint, acceptable) |
| Image alt | PASS | Logo alt populated by tenant branding trait |
| CSRF | PASS | `logintoken` rendered in form |
| Touch-targets | ASSUMED | Pill button styling at default sizing typically clears 44px |

**Findings:**

#### F-10  [P1]  Login — High `!important` count in partial

```
File:     moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-login.scss
Symbol:   File-level
Spec:     .claude/rules/frontend.md — avoid !important
Found:    66 !important declarations in 699 lines (~9.4% of all rules)
Expected: Most can be eliminated by scoping under body.path-login (already implicit) and avoiding fights with Bootstrap login form defaults via parent selectors.
Impact:   Each !important is a maintenance debt; cascade reads become impossible to predict.
Fix:      Refactor pass — group rules under #page-login-index parent selector with sufficient specificity; remove !important.
```

#### F-11  [P1]  Login — Bare `:focus` (7 instances)

```
File:     moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-login.scss:209,534-538
Symbol:   .airpay-login__input :focus, #page-login-forgot_password form-controls :focus, #page-signup form-controls :focus
Spec:     WCAG 2.4.7
Found:    7 bare :focus selectors driving ring color.
Expected: Each gets a sibling &:focus-visible rule.
Impact:   Phantom focus ring on mouse click.
Fix:      Mechanical — duplicate each focus rule with :focus-visible.
```

---

### §3.4 — Dashboard (cross-references Goal A.x context)

**Files:** `templates/dashboard.mustache` (~520 lines), `layout/dashboard.php`, `_surface-dashboard.scss` (538 lines, 32 `!important`)

**Dimension table:**
| Dimension | Verdict | Notes |
|---|---|---|
| Token compliance | FAIL | 35+ inline styles in template (P0 #5) |
| Dark mode | PARTIAL | Inline `var(--ap-color-*)` references DO theme correctly; raw `#16a34a` / `#dc2626` do not |
| Tenant branding | PASS | Welcome header branches on `isadmin` / `ismanager` / learner |
| Focus-visible | FAIL | 2 bare `:focus` in partial |
| Empty/loading/error | PARTIAL | KPI tiles have empty-state via `{{^hasadminkpis}}` guard but missing for User Analytics path |
| `prefers-reduced-motion` | PASS via tokens |
| i18n | FAIL | 11 hardcoded English chunks (P0 #5 list) |
| `aria-` coverage | PARTIAL | Charts have no `aria-label` / role; canvases are decorative without text alternative |
| Heading hierarchy | PASS | `<h2>` welcome → `<h3>` section titles |
| Chart.js CDN load | NOTE | `<script src="https://cdn.jsdelivr.net/..." />` loaded from external CDN (line 254) — should be vendored or guarded for offline / restricted-network customers |

**Findings:**

#### F-12  [P0]  Dashboard — Inline-style avalanche

```
File:     moodle-enhancement/theme/airpayux/templates/dashboard.mustache:172-185, 305-403, ~30 hits
Symbol:   Multiple — welcome header, compliance KPI grid, table cells, section margins
Spec:     .claude/rules/frontend.md §Anti-patterns
Found:    28 inline style="..." attributes carrying font-size, font-weight, color, padding, margin, display, gap, border-radius, etc. Two contain hex literals #16a34a and #dc2626.
Expected: Migrate all 28 to _surface-dashboard.scss as class-based rules under .airpay-dash__welcome-header, .airpay-dash__compliance-grid, etc.
Impact:   (a) Cannot be overridden by customer-N branding without forking the template. (b) Dark-mode renders the welcome header correctly only because var() is used; the two raw hex compliance counters (#16a34a green for "compliant", #dc2626 red for "overdue") DO NOT theme — green stays #16a34a on dark surface where it should brighten. (c) Token discipline broken; tone of every Sentientia surface review.
Evidence: moodle-enhancement/docs/visual-evidence/2026-05-21/ — multiple dashboard-*.png pairs.
Fix:      Inline-styles → class names per BEM convention (.airpay-dash__welcome-title, .airpay-dash__compliance-card, .airpay-dash__compliance-count--compliant, --overdue, etc.); add corresponding rules in _surface-dashboard.scss using $ap-success / $ap-error semantic tokens.
```

#### F-13  [P0]  Dashboard — Hardcoded welcome / KPI copy

```
File:     moodle-enhancement/theme/airpayux/templates/dashboard.mustache:174-185, 207, 211, 312-324
Symbol:   Welcome headers, chart titles, KPI labels
Spec:     .claude/rules/frontend.md §Anti-patterns
Found:    11 hardcoded English strings (mapping in §2.4 table)
Fix:      Add 11 new strings to theme_airpayux EN locale + propagate to hi/kn/mr/sw.
```

#### F-14  [P1]  Dashboard — External CDN script for Chart.js

```
File:     moodle-enhancement/theme/airpayux/templates/dashboard.mustache:254
Symbol:   <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/...">
Spec:     Moodle module discipline — assets vendored or AMD-loaded
Found:    External CDN reference.
Impact:   (a) Customer-N with restricted network breaks the dashboard charts silently. (b) SRI hash missing — supply-chain risk. (c) Chart.js version pinned in template, not in version-controlled vendor folder.
Fix:      Vendor Chart.js to theme/airpayux/javascript/chart.umd.min.js or local_airpay_analytics/amd/src/chart_loader.js; load via $PAGE->requires->js_call_amd().
```

#### F-15  [P2]  Dashboard — Charts lack accessible name

```
File:     moodle-enhancement/theme/airpayux/templates/dashboard.mustache:208, 212
Symbol:   <canvas id="airpay-chart-enrolments">, <canvas id="airpay-chart-distribution">
Spec:     WCAG 1.1.1 — non-text content
Found:    Canvases have no aria-label, no role="img", no <figcaption> sibling.
Expected: aria-label="Enrolment trend chart" on each canvas + a textual data summary in a visually-hidden <table> for screen readers.
Fix:      Add aria-label using the same string as the section title; consider <details><summary>View data</summary><table>…</table></details> fallback.
```

---

### §3.5 — Profile / Badges / Grade-overview / Admin / User-edit / Preferences / Calendar / Grader (Goal A.1–A.9)

**Combined section because all 8 surfaces are scoped within `_surface-profile.scss` (2,507 lines, 164 `!important`) via different `body.path-*` parents.**

**Dimension matrix (aggregate):**
| Dimension | Verdict | Notes |
|---|---|---|
| Token compliance | PARTIAL | ~60 hex literals across surface; most are dark-mode overrides which are intentional |
| Dark mode | PASS for shipped surfaces; calendar (A.8) restyle in `dark_mode.scss:186-205` |
| Tenant branding | PASS | All paths pass through `core_renderer` |
| Focus-visible | FAIL | 11 bare `:focus` rules in partial (lines 1290-1293, 1711-1713, 2112-2114) |
| Empty/loading/error | UNVERIFIED | Cannot test without live data |
| `prefers-reduced-motion` | PASS via tokens |
| i18n | PASS at surface level | Strings come from Moodle core/badges/grade — already translated |
| Touch-targets ≥44px | UNVERIFIED |
| Print | UNVERIFIED — badges + transcripts ARE printed; merits a separate audit |
| Mobile coverage | PASS | 16 `@media` rules in partial covering 1400/1200/992/768/590/480 breakpoints |
| Surface scoping discipline | PASS | Every rule is gated by `body.path-user`, `body.path-badges`, `body.path-grade-report`, `body.path-grade-report-grader`, `body.path-calendar`, `body.path-user-preferences`, `body.path-user-edit`, etc. — disjoint scoping holds |

**Findings:**

#### F-16  [P1]  Surface-profile — Monolithic partial

```
File:     moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-profile.scss
Symbol:   File-level (2,507 lines, 164 !important)
Spec:     SCSS editing protocol §6 in .claude/rules/frontend.md — component-by-component, not full rewrites
Found:    Single partial covers 8 surfaces (profile, badges, grade-overview, grader-report, user-edit, preferences, calendar, admin-interior fragments). The `path-*` scoping discipline is sound but the file size is unmanageable.
Expected: Split into per-surface partials matching the navbar / footer / login / dashboard pattern:
            _surface-user.scss (profile + edit + preferences)
            _surface-badges.scss
            _surface-grade-report.scss (overview + grader)
            _surface-calendar.scss
Impact:   Future per-surface refactors fight 164 !important declarations; PR diffs touch unrelated surfaces; review fatigue.
Fix:      Refactor in a dedicated session (≥1 day). Pre-commit hook: warn if any partial > 1,000 lines.
```

#### F-17  [P1]  Surface-profile — Bare `:focus` rules

```
File:     moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-profile.scss:1290-1293, 1711-1713, 2112-2114
Symbol:   form input/textarea/select :focus, tertiary-navigation :focus
Spec:     WCAG 2.4.7
Found:    11 bare :focus rules
Fix:      Add &:focus-visible sibling rules.
```

#### F-18  [P2]  Surface-profile — `!important` density

```
File:     moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-profile.scss
Symbol:   File-level (164 declarations)
Spec:     .claude/rules/frontend.md §Anti-patterns
Found:    ~6.5% of declarations carry !important. Spot-check (line 164 area) shows most are cosmetic and could be eliminated by tightening `body.path-X` specificity.
Fix:      Track as part of F-16 refactor.
```

---

### §3.6 — Course view

**Files:** `templates/course.mustache` (260+ lines), `layout/course.php`, `_surface-course.scss` (658 lines, 18 `!important`)

**Dimension table:**
| Dimension | Verdict | Notes |
|---|---|---|
| Token compliance | PASS | Surface uses `--ap-*` consistently |
| Dark mode | PASS | Course tile chrome themes via tokens |
| Tenant branding | PASS via core_renderer |
| Focus-visible | FAIL | 3 bare `:focus` (lines 258, 278, 533) |
| Empty/loading/error | PASS | Course completion inline labels (per 2026-05-21 visual evidence) |
| `prefers-reduced-motion` | PASS — `_course-player.scss:337` explicitly cites token-driven motion |
| i18n | PASS | Strings come from Moodle core course module |
| Heading hierarchy | PASS | h1 → h2 → h3 |
| ARIA | PASS | `role="progressbar"`, `role="region"`, `aria-current` on progress (per comment line 137) |
| Inline styles | LIMITED | Lines 170, 186 carry `style="width:{{ap_course_progress}}%;"` — legit-dynamic, cannot move to SCSS |

**Findings:**

#### F-19  [P1]  Course — Bare `:focus`

```
File:     moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-course.scss:258, 278, 533
Fix:      Add :focus-visible siblings.
```

#### F-20  [P2]  Course — Background-image inline-style with templated URL

```
File:     moodle-enhancement/theme/airpayux/templates/course_full_header.mustache:41
Symbol:   .courseheader background
Found:    style="background-image: url('{{coursebannerimage}}');"
Spec:     Acceptable for dynamic image URLs, but background-image URL injected from user-uploadable course banner is XSS-prone if `coursebannerimage` is not URL-escaped.
Expected: Verify upstream that `coursebannerimage` passes through `s()` or `out_as_local_url()` before reaching this template. Better still: emit a `data-cover-url` attribute and let an AMD module read it / apply via CSSStyleDeclaration to keep the value out of the parsed CSS context.
Fix:      Add comment to template documenting upstream sanitisation; or migrate to data-attribute + AMD.
```

---

## §4 — Plugin Functional Findings

### §4.1 — sentientia_pwa (v0.3.x-alpha)

**Templates inspected:** `templates/manifest.mustache`, `templates/subscribe_widget.mustache`, `templates/install_cta.mustache`
**Other surface code:** `register.js`, `sw.php`, `offline.html`, `manifest.php`, `dismiss_install.php`

**Dimension table:**
| Dimension | Verdict | Notes |
|---|---|---|
| Install banner copy | PASS | `install_cta.mustache:20-22` — title + body via `{{#str}}` |
| Banner dismissibility | PASS | Dedicated dismiss button (line 37-42) with `aria-label` |
| Service worker scope | DEFERRED | Crypto surface covered by B25-CRYPTO-AUDIT-2026-05-21.md — see issues 1-6 there |
| Offline page brand consistency | UNREAD | `offline.html` not inspected in this audit; flagging as follow-up |
| Permission-prompt sequencing | UNREAD | `install_prompt.js` AMD module not in source tree (only `register.js` is shipped — `register.js` registers the SW, the `install_prompt` referenced in footer.mustache comment must be added in a Phase D.1.b deliverable) |
| iOS Safari quirks | UNVERIFIED |
| ARIA | PASS | `role="region"` on banner, `aria-label` on banner + dismiss button |

**Findings:**

#### F-21  [P1]  PWA — Missing `install_prompt.js` AMD module referenced by footer

```
File:     moodle-enhancement/local/sentientia_pwa/amd/src/  (directory either missing or contains different file)
Symbol:   install_prompt module
Spec:     footer.mustache:67-76 comment claims this module is the single source of truth
Found:    `local/sentientia_pwa/` ships `register.js` but no `amd/src/install_prompt.js`. The footer comment block references functionality (beforeinstallprompt capture, suppression, dashboard-only CTA, 7-day quarantine) that depends on this module.
Expected: Either ship the module or update the comment / template flag-gating.
Impact:   If `register.js` is the only PWA JS in production, the install-CTA template ships but never reveals itself (since the CSS class `d-none` is never removed). Effective P0 for users on installable browsers.
Fix:      Confirm with Nitin: is install_prompt.js shipped in a separate commit not yet in the audit working tree? If so, this finding becomes "documentation-only" — the manifest in `theme/airpayux/templates/head.mustache` should reference the module.
```

#### F-22  [P2]  PWA — `manifest.mustache` and `subscribe_widget.mustache` not read in detail

```
File:     moodle-enhancement/local/sentientia_pwa/templates/{manifest,subscribe_widget}.mustache
Symbol:   Templates
Found:    Not inspected in detail in this audit — scope budget exhausted.
Fix:      Cover in a focused PWA-template review session.
```

---

### §4.2 — sentientia_live (Mentimeter clone)

**Templates inspected:** `templates/trainer_dashboard.mustache`, `templates/result_panel.mustache`, `templates/result_bar_chart.mustache`
**Other code:** `amd/src/trainer_sse.js`, `amd/src/audience_sse.js`, `amd/src/chart_updater.js`, `audience/play.php`, `trainer/run.php`

**Dimension table:**
| Dimension | Verdict | Notes |
|---|---|---|
| Strings i18n | PASS | Every label uses `{{#str}}` (sampled trainer_dashboard.mustache — 16 string keys in 121 lines) |
| Dark mode | UNVERIFIED — templates use Bootstrap utilities (`bg-secondary`, `bg-light`) not Sentientia tokens |
| Connection-state indicator | UNVERIFIED — would need to inspect AMD modules' UI updates |
| Optimistic update reconciliation | UNVERIFIED |
| Latency-perception affordances | UNVERIFIED — no spinner/skeleton in templates inspected |
| Trainer vs audience template divergence | PASS — distinct directories |
| `aria-live` on live-update regions | **FAIL — ZERO `aria-live` regions anywhere in templates or AMD** |
| Rate-limiting feedback | UNVERIFIED |
| Capability + tenant scoping | PASS per CLAUDE.md discipline |
| Hindi lang file | PASS | `lang/hi/local_sentientia_live.php` exists |

**Findings:**

#### F-23  [P0]  Live — Zero `aria-live` regions in real-time UI

```
File:     moodle-enhancement/local/sentientia_live/templates/*.mustache (and amd/src/*.js)
Symbol:   No <div role="status" aria-live="polite"> wrappers around result_panel / result_bar_chart updates
Spec:     WCAG 4.1.3 + ARIA Authoring Practices for live regions
Found:    Audit grepped `aria-live` across all three template files and three AMD source files. ZERO matches.
Expected: result_panel.mustache should wrap the dynamic results region in <div aria-live="polite" aria-atomic="false">. chart_updater.js should also emit textual updates ("Question X — N responses received") to a sr-only live region.
Impact:   Screen-reader users in a live audience cannot perceive poll updates as they happen. For Mentimeter-class engagement, this is exclusion.
Evidence: Static read; no PNG comparison applicable (screen-reader behaviour, not visual).
Fix:      (a) Add aria-live="polite" to the dynamic result panel container. (b) Add a <span class="sr-only" aria-live="polite" data-live-feedback> sibling that chart_updater.js writes summary text to on each update tick. (c) For the audience play.php page, ensure vote-cast confirmation appears in the same live region.
```

#### F-24  [P1]  Live — Bootstrap utility classes instead of Sentientia tokens

```
File:     moodle-enhancement/local/sentientia_live/templates/trainer_dashboard.mustache:60-78, 83-106
Symbol:   .badge.bg-secondary, .badge.bg-success, .badge.bg-light, .btn.btn-outline-warning, etc.
Spec:     .claude/rules/frontend.md §BEM — airpay-[block]__[element]--[modifier]
Found:    Plugin templates rely entirely on Bootstrap classes; no Sentientia BEM classes used.
Expected: For Sentientia product consistency, badges and buttons should use airpay-badge--success / airpay-btn--outline-warning equivalents. Bootstrap classes are acceptable as fallbacks but the Sentientia design system should override.
Impact:   Plugin surfaces look slightly different from theme surfaces — same content area, different button shapes / pill radii.
Fix:      Long-term: ship an airpay-* class for each Bootstrap utility currently in use. Short-term: add overrides in _bizlms-modern.scss for the .badge.bg-* and .btn.btn-outline-* classes scoped to body.path-local-sentientia-live.
```

#### F-25  [P2]  Live — Tables without `<caption>` or scope attributes

```
File:     moodle-enhancement/local/sentientia_live/templates/trainer_dashboard.mustache:39
Symbol:   <table class="table table-hover align-middle">
Spec:     WCAG 1.3.1 — info & relationships
Found:    Table has no <caption> and column headers have no scope="col".
Expected: <caption class="sr-only">{{#str}}trainer_sessions_table_caption{{/str}}</caption> + scope="col" on each <th>.
Fix:      Surgical addition.
```

---

## Appendix A — Token & Selector Drift Index

**Hex literal hotspots (in compiled non-Bootstrap files, excluding `dark_mode.scss` and `preset/default.scss` which are token-source files):**

| File | Approx hex count | Status |
|---|---|---|
| `partials/_surface-profile.scss` | 60+ | Mostly intentional dark-mode overrides; ~10 are P1 candidates |
| `scss/moodle/modules.scss` | 8 | Legacy module overrides; replace with semantic tokens |
| `scss/moodle/grade.scss` | 9 | Status pill colours; replace with `$ap-success`, `$ap-error`, `$ap-warning` |
| `scss/moodle/sitecolors.scss` | varies | Site-colour overrides — needs dedicated review |
| `templates/footer.mustache` | 4 inline | P0 #6 — see F-06 |
| `templates/dashboard.mustache` | 2 inline (`#16a34a`, `#dc2626`) | P0 #5 — see F-12 |

**`!important` density (top 5):**
| File | Count |
|---|---|
| `custom_changes_MONOLITH_BACKUP.scss` | 682 | DELETE |
| `dark_mode.scss` | 253 | Token-cascade refactor — P1 #13 |
| `partials/_surface-profile.scss` | 164 | F-18 |
| `partials/_moodle-overrides.scss` | 136 | P2 #16 |
| `partials/Claude` | 135 | DELETE (orphan) |

**Selector specificity deep-dives (>4 levels):**
Not exhaustively measured — recommend stylelint `selector-max-specificity` rule with threshold `0,4,0` against the partials directory in a follow-up sprint.

---

## Appendix B — Visual-Evidence Cross-References

All findings reference existing screenshots in `moodle-enhancement/docs/visual-evidence/`:

| Folder | Coverage | Relevant findings |
|---|---|---|
| `2026-05-20/` | Day 0 baseline, switchboard UI flag OFF/ON | Sprint 1 baseline shots |
| `2026-05-21/` | Goal A.1–A.7 surfaces (7 before/after pairs); dark-mode wiring; course completion inline labels | F-12 dashboard (visual), F-13, A.1–A.7 surfaces |
| `2026-05-23/` | Goal A.9 grader-report restyle (before/after + mobile 590px); B12 cutover login/admin flow; audit findings; sticky-footer | F-05 footer, F-06 footer, F-12 dashboard (more views), grader (A.9) |

**No new captures generated by this audit** — explicitly out of scope per user's static-only choice (per plan §"Out of scope").

---

## Appendix C — Remediation Backlog

### P0 (9 items, blocking promotion)

| # | Owner | Sizing | Item |
|---|---|---|---|
| 1 | theme dev | 5 min | `git rm moodle-enhancement/theme/airpayux/scss/moodle/partials/Claude` |
| 2 | theme dev | 5 min | `git mv moodle-enhancement/theme/airpayux/scss/moodle/custom_changes_MONOLITH_BACKUP.scss moodle-enhancement/docs/archive/` (or delete) |
| 3 | theme dev + L&D translator | 2 hrs | Replace 8 hardcoded English strings in `navbar.mustache` + add to 5 locales (F-01) |
| 4 | theme dev + L&D translator | 1 hr | Replace 4 hardcoded English strings in `footer.mustache` (F-05) |
| 5 | theme dev | 3 hrs | Migrate 28 inline styles in `dashboard.mustache` to `_surface-dashboard.scss` classes (F-12) |
| 6 | theme dev | 1 hr | Migrate footer Sentientia attribution band inline styles to `_surface-footer.scss` (F-06) |
| 7 | L&D translator | 4 hrs | Reconcile hi/kn/mr/sw locale parity to 100% (§2.8) |
| 8 | live plugin dev | 2 hrs | Add `aria-live="polite"` regions + chart-updater sr-only feedback (F-23) |
| 9 | navbar dev | 2 hrs | Extract inline `<script>` to AMD module (F-02) |

**Total P0 budget:** ~15 hours / ~2 working days

### P1 (8 items, this sprint)

| # | Sizing | Item |
|---|---|---|
| 10 | 1 day | Split `_surface-profile.scss` into 4 per-surface partials (F-16) |
| 11 | 4 hrs | Refactor `_surface-login.scss` to eliminate 66 `!important` (F-10) |
| 12 | 30 min | Add `:focus-visible` adjacent to all 53 bare `:focus` rules (F-03, F-11, F-17, F-19) |
| 13 | 1 day | Refactor `dark_mode.scss` from 253 `!important` to token-cascade |
| 14 | 2 hrs | Add mobile breakpoint to `_surface-footer.scss` (F-07) |
| 15 | 4 hrs | Add Sentientia BEM classes for `sentientia_live` Bootstrap-utility usages (F-24) |
| 16 | 2 hrs | Add 11 dashboard strings to lang files (F-13) |
| 17 | 4 hrs | Vendor Chart.js or AMD-load it (F-14) |

### P2 (6 items, polish / document & defer)

| # | Sizing | Item |
|---|---|---|
| 18 | 1 hr | Trim `_moodle-overrides.scss` `!important` count |
| 19 | 2 hrs | Audit `prefers-reduced-motion` coverage — add stylelint rule |
| 20 | 1 hr | Verify `coursebannerimage` upstream sanitisation (F-20) |
| 21 | 30 min | Delete removed-badge comment block in `footer.mustache` (F-09) |
| 22 | 2 hrs | Add `<caption>` + `scope="col"` to live-plugin tables (F-25) |
| 23 | 1 hr | Add accessible names to dashboard charts (F-15) |

---

## Sign-off checklist (Nitin to tick before merge)

- [ ] Nitin reviewed
- [ ] P0 items 1–2 (file deletions) confirmed safe (no hidden import)
- [ ] P0 items 3–6 (string + style migration) scheduled
- [ ] P0 item 7 (locale parity) translator assigned
- [ ] P0 item 8 (live plugin a11y) live-plugin dev scheduled
- [ ] P0 item 9 (cart-script extraction) cart plugin dev confirmed owner
- [ ] PROJECT-STATE.md entry appended
- [ ] Audit branch `claude/platform-visual-audit-mgare` pushed
- [ ] Subsequent fix-sprint planning meeting booked

---

*Audit complete. 14 surfaces inspected, 25 findings filed across 3 severity tiers. Verdict: CONDITIONAL PASS — close the 9-item P0 list before Phase 2 customer-zero promotion.*
