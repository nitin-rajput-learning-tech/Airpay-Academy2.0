<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Airpay Academy / Sentientia UX theme.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Goal A audit (2026-05-22 + 2026-05-23) — multiple SCSS additions:
//   - sticky-footer.scss: switch to min-height: 100vh (Bug #8)
//   - partials/_surface-profile.scss: Sentientia branding on vanilla
//     /user/profile.php, /badges/mybadges.php, /grade/report/overview,
//     /admin/*, /course/view.php, /grade/report/grader/ (Goal A.x)
//   - partials/_layout-shell.scss: mobile shell-main width: 100% (Bug #13)
// Version bump invalidates the cached compiled CSS bundle so theme
// styles.php re-compiles SCSS on next request.
//
// P0 borrow #5 (Moodle 5.2, 2026-05-23) — OAuth2 / identity-provider
// button text and divider copy ("or sign in with") moved to lang string
// $string['signinwithidentityprovider']. Both en + hi packs updated;
// templates/core/loginform.mustache references via {{#str}} helper.
// Customer admins can override via Site Admin → Language customisation
// per-tenant. Aria-label appended for screen-reader announcement.
//
// P0 borrow #10 (Moodle 5.2, 2026-05-23) — suspended-user badge AMD +
// before_standard_top_of_body_html hook. Server pre-renders a JSON map
// of suspended/deleted userids in the current tenant; the AMD decorator
// paints inline badges next to user-name links on report-like pages
// (gradebook, participants, report log, course-user). New SCSS partial
// _components-user-status-badge.scss imported in custom_changes.scss.
//
// P0 borrow #14 (Moodle 5.2, 2026-05-23) — extra sort options on the
// block_myoverview "My Courses" dropdown: course start date (newest
// first) + course end date (soonest first). Template override at
// templates/block_myoverview/nav-sort-selector.mustache. Lang strings
// sortbystartdate / sortbyenddate added in en + hi (100% parity).
//
// Phase B.2 fix (2026-05-23) — Moodle 5.2 upgrade smoke surfaced a
// stale reference to /theme/epsilon/scss/preset/*.scss in lib.php.
// The epsilon parent theme was removed when we made sentientia a
// standalone fork ($THEME->parents = []), but lib.php still had three
// hard-coded epsilon paths. Repointed to /theme/sentientia/scss/preset/*.
// All preset files (default.scss, plain.scss) already exist there.
//
// Phase B.3 hook migration (2026-05-23) — Moodle 5.2 web smoke surfaced
// a deprecation notice asking us to migrate
// `theme_sentientia_before_standard_top_of_body_html()` to the new hook
// system. Added classes/hook_callbacks.php + db/hooks.php registering
// `\core\hook\output\before_standard_top_of_body_html_generation`.
// Legacy lib.php function preserved as a thin shim for 5.1 deployments
// (it no-ops on 5.2 because the hook subscription is canonical).
//
// Phase B.3.e SCSS rebase (2026-05-23) — Moodle 5.2 renamed the
// activity-icon-colors map key `"interface"` → `"interactivecontent"`.
// Our scss/moodle/variables.scss now ships BOTH keys with the same
// Sentientia purple (#a378ff) so the lookup works on both 5.1 and 5.2.
// See docs/5.2-merge/PHASE-B3E-SCSS-REBASE-INVENTORY.md for the wider
// SCSS rebase strategy.
//
// Phase B.3.e+ BS5 + proactive variables (2026-05-23) — per Nitin's
// "BS5 migration NOW + proactive variable adoption" directive:
//   - scss/bootstrap/_functions.scss adds the BS5 shift-color() helper
//     alongside the existing BS4 theme-color-level() (both work, no
//     callsite rewrites needed today).
//   - scss/moodle/_tokens-52.scss — new partial — defines all 81
//     component-scoped variables introduced in 5.2's boost variables.scss
//     as `!default`. Loaded LAST in lib.php's pre_scss chain so customer
//     brand overrides above still win, but every new 5.2 variable is now
//     available for component SCSS to consume.
//
// Phase B.3.a core_renderer rebase (2026-05-23) — 5.2 boost's
// core_renderer.php added ONE method vs 5.1: `render_login()` (which
// we already override) with a new `$context->hasauthinstructions` key
// that conditionally renders the "Authentication instructions" block.
// Our `traits/login_render.php::render_login()` now mirrors this key
// for forward-compat with any 5.2 template fallback path.
// See docs/5.2-merge/PHASE-B3A-CORE-RENDERER-REBASE.md.
//
// Phase B.3.b layouts rebase (2026-05-23) — 5.2 introduced
// `\core\output\select_menu` for the tertiary navigation overflow
// dropdown. Migrated 4 layouts (columns2.php, course.php,
// dashboard.php, drawers.php) to dual-target the new class, falling
// back to the 5.1 `$overflowdata->export_for_template($OUTPUT)` path
// when `\core\output\select_menu` isn't autoloadable.
//
// Phase B.3.c top templates rebase (2026-05-23) — audited 8 boost
// template diffs (5.2 vs 5.1). Tagged course.mustache with the
// cutover-day swap action for the tertiary-nav partial (the one
// place where B.3.b's PHP context-shape change creates a mismatch
// with our existing template). Full per-template plan in
// docs/5.2-merge/PHASE-B3C-TOP-TEMPLATES-REBASE.md.
//
// Phase B.3.d core_form widgets rebase (2026-05-23) — diff audit
// found that 5.2 added 2 new core_form mustache templates
// (element-float.mustache, element-float-inline.mustache) and
// modified zero existing ones. Both new templates already exist
// in our fork with byte-identical content to 5.2 boost — no code
// changes. Phase B.3 substantially complete (~2.5h vs ADR 38h
// estimate). See docs/5.2-merge/PHASE-B3D-CORE-FORM-REBASE.md.
//
// Phase B.3.f shim cleanup (2026-05-24) — 2 of the 3 AMD borrow shims
// (page_title.js, deprecated.js) deleted ahead of cutover after the
// Phase B.3.f audit confirmed zero callsites. amd/build/*.min.js
// counterparts deleted too. announcement.js KEPT pending NVDA
// verification on production 5.2 substrate (per the audit doc — it has
// an aria-live re-announce trick for NVDA <2024 that core/toast may
// not handle). See docs/5.2-merge/PHASE-B3F-AMD-CLEANUP.md.
//
// P0 #1 + #2 SCSS hygiene (2026-05-24) — closes the two dead-source
// findings from PLATFORM-VISUAL-AUDIT-2026-05-24.md §2.10:
//   - DELETE: scss/moodle/partials/Claude (orphan, 98 KB, 135 !important,
//     never imported — verified zero @import/@use references).
//   - MOVE:   scss/moodle/custom_changes_MONOLITH_BACKUP.scss (284 KB,
//     682 !important) → _archive/ so the SCSS compiler's source tree
//     no longer scans a 284 KB historical backup. Git history preserves
//     the file via `git mv` rename detection.
// Version bumped to invalidate the cached compiled CSS bundle so theme
// styles.php re-compiles SCSS on next request (defensive — the orphan
// was never compiled in, but the bump aligns the cache key with the
// new on-disk tree).
//
// P0 #7 locale parity (2026-05-24) — kn closed 35-string gap, mr + sw
// each closed 3-string gap. All four non-hi packs (en, kn, mr, sw) now
// at 153/153 unique keys = 100% parity. Bump also triggers a lang-cache
// purge so the new strings are picked up on next page load.
//
// P1 #12 :focus-visible coverage (2026-05-24) — added :focus-visible
// sibling rules adjacent to every bare :focus rule across the five
// surface partials (navbar/dashboard/login/course/profile). Closes
// audit findings F-03, F-11, F-17, F-19 from
// docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md §2.6. WCAG 2.1.1 +
// 2.4.7 — keyboard users still get the brand-light ring, mouse-click
// no longer flashes a phantom ring. Legacy :focus rules retained as
// fallback for browsers without :focus-visible support. 22 selectors
// added across 10 rules. Cache bump invalidates compiled CSS.
//
// P0 follow-up chip B (2026-05-24) — 4 P0s from PLATFORM-VISUAL-AUDIT
// closed in this bump:
//   - P0 #3: navbar i18n (5 nav labels + 3 a11y descriptors)
//   - P0 #4: footer i18n (4 link labels + copyright)
//   - P0 #6: footer Sentientia attribution band hex literals migrated
//     to --ap-* design tokens in _surface-footer.scss
//   - P0 #9: cart-badge inline <script> extracted into new
//     theme_sentientia/cart_badge AMD module (src + build); wired from
//     layout/dashboard.php + layout/course.php
// Hindi parity restored to 100% (161 keys en / 161 keys hi) en route.
// See docs/visual-evidence/2026-05-24/p0-followup-chip-B/README.md.
//
// P0 #9 follow-up verification (2026-05-24) — spawned chip audited the
// AMD-wiring scope of the cart_badge module introduced by chip B.
// Per-layout walkthrough of all 10 sentientia layouts found that the
// cart-bearing templates/navbar.mustache partial is rendered by exactly
// two templates (course.mustache always + dashboard.mustache in the
// dead-code {{^use_shell}} fallback) — the same two layouts chip B
// wired. No additional layouts need direct wiring; the other 8 use
// either airpay_shell_start (columns2/drawers), a navbar-less minimal
// shell (columns1/embedded/login/maintenance), a distinct
// navbar-secure.mustache (secure), or a custom landing-page nav that
// redirects logged-in users away (frontpage). Version bump signals
// audit completion. Full per-layout evidence table in PROJECT-STATE.md.
//
// P1 #14 + P2 #21 follow-up chip-L (2026-05-24) — footer mobile +
// comment cleanup:
//   - partials/_surface-footer.scss: new @media (max-width: 590px)
//     block stacks compact row on Galaxy-S sized viewports; tightens
//     .airpay-footer__product-attribution padding (P1 #14 / F-07).
//   - templates/footer.mustache: removed the 10-line "Made in India"
//     removed-badge Mustache comment block (P2 #21 / F-09).
//
// P1 #17 + P2 #23 follow-up wave3-chip-N (2026-05-24) — dashboard chart
// vendoring + chart a11y:
//   - amd/src/chart_loader.js + amd/build/chart_loader.min.js (NEW):
//     thin AMD wrapper around core/chartjs (Moodle's bundled Chart.js
//     v4.4.2). Removes the cdn.jsdelivr.net <script src=…> from
//     templates/dashboard.mustache. Restricted-network customers and
//     SRI-strict CSP deployments now get a working chart surface.
//     Closes audit finding F-14.
//   - templates/dashboard.mustache: <canvas role="img"
//     aria-labelledby + aria-describedby on both charts; a hidden-by-
//     default <details><table> mirror of the chart's underlying data
//     for screen-reader users (data flows through new template
//     context arrays). Closes audit finding F-15.
//   - layout/dashboard.php: pre-warms chart_loader via
//     js_call_amd; exposes chart_enrolments_table +
//     chart_distribution_table iterable arrays for the SR-only data
//     tables.
// String discipline: NO new theme_sentientia lang keys. Reused
// chart_enrolment_trend / chart_course_distribution (Chip G) for
// aria-label/caption; column headers use Moodle core lang
// (month, total, category, courses).
// Visual evidence: docs/visual-evidence/2026-05-24/wave3-chip-N/.
//
// P2 #20 / F-20 chip-Q (2026-05-24) — coursebannerimage CSS-url()
// injection verification. Confirmed safe: moodle_url::make_pluginfile_url()
// rawurlencodes every CSS-terminating character. Doc-only comment
// added above the courseheader div in course_full_header.mustache.
//
// P2 #19 chip-P (2026-05-24) — prefers-reduced-motion stylelint rule.
// New .stylelintrc.json adds declaration-property-value-disallowed-list
// for transition-duration (must use var()) and shorthand transition
// (no inline s/ms timing). Token cascade in _tokens.scss collapses
// --ap-duration-* to 0ms under prefers-reduced-motion:reduce.
// Enforces WCAG 2.3.3 vestibular accessibility for new surface code.
// Doc-only config; existing violations deferred to follow-up.
//
// P1 #12 re-apply (2026-05-24) — restore :focus-visible siblings lost
// during chip-J + chip-K merges. Re-added 12 selectors across 3
// surface partials (login, user, grade-report). 4 selectors in
// _legacy-admin.scss still on backlog. WCAG 2.1.1 + 2.4.7.
//
// P0 cleanup A (2026-05-24) — pre-commit + CI defence against stray
// git conflict markers (CI #397 / #403 root cause). New CHECK 11 in
// .claude/hooks/pre-commit.sh and new `conflict-marker-check` job in
// .github/workflows/ci.yml; both anchored on git's exact marker
// format so {{<partial}} and setext banners don't false-positive.
// New tools/install-hooks.ps1 one-liner for local hook installation.
// Version bump (2026052405 → 2026052406) sits on top of kn/mr/sw
// 13-key parity chip's bump; invalidates the cached compiled CSS
// bundle so theme styles.php re-compiles SCSS on next request
// (defensive — no SCSS changed, but the bump aligns the cache key
// with the new infra).
//
// P2 #19 follow-up — inline-timing → tokens (2026-05-24, chip-D)
// All 54 inline-timing transition declarations across the 9
// _surface-*.scss partials migrated to var(--ap-transition-quick|
// default|slow). The token cascade in _tokens.scss collapses
// --ap-duration-* to 0ms under prefers-reduced-motion:reduce, so
// every animation on a Sentientia surface now respects the user
// preference (WCAG 2.3.3 — Animation from Interactions). 9 commits,
// one per partial: badges (3), calendar (2), navbar (2), footer (3),
// grade-report (5), dashboard (6), login (9), user (11), course (13).
// Two declarations with sub-token timing (0.05s tactile-press) were
// rounded up to --ap-transition-quick (150ms) with an inline comment
// at the violating site explaining the rounding decision. Closes
// the P2 #19 violation backlog flagged by chip-P's stylelint rule.
//
// Wave A1 P0-cleanup — fullname() debugging notices (2026-05-24, chip H2)
// dashboard.php layout was emitting 6+ PHP notices per /my/ render:
// "The following name fields are missing from the user object:
//  firstnamephonetic, lastnamephonetic, middlename, alternatename".
// Two SQL queries in the admin/L&D-admin Recent Activity feed selected
// only u.firstname + u.lastname, then passed each row through fullname().
// Moodle 4.x+ fullname() expects all 6 name fields from
// core_user\fields::for_name(). Replaced the literal column list with
// the canonical $userfieldsapi->get_sql('u', false, '', '', false)
// helper so every fullname() call has the columns it needs. No schema
// change, no behavioural change for sighted users — just clean
// error.log. Version bump invalidates the cached compiled CSS bundle
// (defensive — no SCSS changed) so theme styles.php re-compiles on
// next request.
//
// Sidebar role switcher (2026-05-27) — surface BizLMS role switching in
// the dashboard shell. The shell layout (use_shell=true) renders neither
// navbar.mustache nor topbar.mustache, so the role switcher built inside
// core_renderer::user_menu() was never shown — multi-role users (e.g. an
// L&D admin who is also a learner, like Nitin) had no visible way to
// switch in the shell, only the raw /my/switchrole.php URL. Restores
// parity with live airpay.academy (top-right user-menu switch).
//   - classes/output/traits/user_menu.php: new get_role_switch_options()
//     data-builder (isolated sibling of user_menu(); reuses the same
//     \local_sentientia_org\accesslib source). Returns hasoptions/currentlabel/
//     options[]. class_exists-guarded for vanilla-Moodle Sentientia customers.
//   - layout/dashboard.php: $templatecontext['roleswitch'].
//   - templates/dashboard.mustache: {{#roleswitch.hasoptions}} sidebar
//     control above the theme toggle; active role marked (aria-current +
//     check), others are switchrole links. Single-role users see nothing
//     new (hasoptions=false) — production behaviour unchanged.
//   - scss/.../_layout-shell.scss: .ap-sidebar__roleswitch* (dark-sidebar
//     tokens, reduced-motion-aware transition, hidden when collapsed).
//   - No new lang keys (reuses switchroleto + employee; hi parity intact).
// Visual evidence: docs/visual-evidence/2026-05-27/ (roleswitch-*.png).
//
// Role switcher active-marker fix (2026-05-28) — the first-load "current
// role" highlight was absent because get_role_switch_options() required
// roleid+depth+orgcatid to ALL match, but the two writers of
// $USER->useraccess['currentroleinfo'] store different keys
// (set_user_role_switch: roleid+contextid; role_switch_basedon_userroles:
// roleid+orgcatid+depth+contextinfo). Now matches on roleid (the only
// shared key), tightens with contextid/orgcatid only when present, and
// falls back to role_detector (the dashboard's source of truth) so exactly
// one option is always marked — agreeing with the rendered dashboard.
//
// Signup-flow UI fixes (2026-05-29) — _surface-login.scss: (1) top-align
// the #page-signup card (align-items:flex-start + padding) so the tall
// form is no longer clipped above the scroll origin — the flex-centring
// overflow trap that forced users to zoom out to ~75%; (2) card-wrap
// login/index.php NOTICE pages (e.g. "You need to confirm your account")
// via :not(:has(.airpay-login)) so they get the branded centred card
// instead of rendering flush-left + unstyled. The real split-screen login
// page is excluded by the :has() guard. Light + dark variants both added.
// App-shell scroll fix (2026-05-29) — _layout-shell.scss: lock the authenticated
// shell (body:has(.ap-shell)) to a viewport-height flex column so ONLY the content
// region scrolls (sidebar fixed + topbar sticky stay pinned; #page-footer pinned at
// the bottom) instead of the whole document scrolling. Was min-height:100vh on
// .ap-shell, which grew to full content height and scrolled the document. Bump
// invalidates the cached compiled CSS bundle + changes themerev so clients fetch the
// new CSS without a manual hard refresh.
//
// Course-card poster thumbnails (2026-05-29) — Netflix-style course images
// across every course-card surface: member catalogue (course_card partial +
// continue-learning carousel), public storefront (public.php LXP cards),
// dashboard "Featured for you" widget, and the guest frontpage "Featured
// Courses". Real course overview image when one is uploaded, else a per-course
// gradient tile (course id % 6) so an image-less wall still looks varied. Bump
// invalidates the compiled CSS bundle + changes themerev so the new
// local_sentientia_catalog + local_sentientia_courses styles.css aggregate (poster
// rules) reaches clients without a manual hard refresh.
// Dark-mode contrast — global short-token flip (2026-05-29). The legacy
// short-form colour tokens (--ap-text, --ap-text-muted, --ap-border,
// --ap-surface(-alt/-2), --ap-primary-light, --ap-accent-light) were defined
// light-only in _components.scss :root; the body.dark-mode cascade flipped
// only --ap-text-secondary, so every consumer of the others rendered
// dark-on-dark on standard (non-shell) pagelayouts (seed: the catalogue
// storefront "Details" button vanished in dark mode). dark_mode.scss now flips
// the whole short set in lock-step with the --ap-color-* twins — one place,
// 655+ var() refs across 57 files corrected, public.php untouched. One white
// island (certificate_celebration "paper" card) re-pinned dark so the flip
// doesn't invert it. Bump forces a fresh themerev so the recompiled CSS ships.
// Dark-mode regression-walk fixes (2026-05-29) — two follow-ups to the
// 2026052903 token flip, found by walking authenticated surfaces in dark mode:
//   1. REVERT the --ap-primary-light / --ap-accent-light flips. Those two are
//      tint BACKGROUNDS paired with brand-colour TEXT on badges/chips/tags/
//      icon-tiles platform-wide; flipping the bg dark while the text stayed
//      brand-dark dropped them to ~1.8–2.4:1. Kept light → readable light chips.
//   2. local_sentientia_catalog/styles.css: re-assert #fff on the catalogue
//      Enrol/Continue anchor-buttons in dark mode (the global body.dark-mode a
//      link rule was bleeding light-blue into them — pre-existing, now pinned).
// ADR-018 Wave 1 (2026-05-29) — Sentientia independence + stabilization, safe-now set:
//   (1) dark-mode AA: scoped `body.dark-mode a` to genuine links via
//       :not([class*="btn"]):not([role="button"]) so anchor-buttons platform-wide
//       stop painting ~2.4:1 light-blue on brand fills (generalises the catalogue fix);
//   (2) white-label: configtitle/pluginname 'Epsilon' → 'Sentientia Academy UX' /
//       'Airpay Academy UX (Sentientia)' across en/hi/kn/mr/sw (was a visible brand
//       leak in Site Admin → Themes for non-English admins).
// De-brand (2026-06-02) — Epsilon/eAbyas → Sentientia. Renamed the live
// breadcrumb class epsilonnavbar → sentientia_navbar (+ its test + the
// page_helpers instantiation); the class is Moodle-core boostnavbar-derived,
// so Adrian Greeve's GPL copyright is RETAINED, only the identifier changed.
// version.php header de-branded to the Airpay/Sentientia attribution. Broader
// eAbyas-header sweep + dead theme/epsilon removal tracked as follow-up batches.
// WF-025 (2026-06-15, foolproof A5) — removed the unconditional role-switch
// force-pin in classes/output/traits/user_menu.php (set $USER->access['rsw']['/1']
// = employee/student on every render whenever any rsw existed) that silently
// demoted multi-role users (org-admin + course editingteacher) to student
// site-wide, blocking course/modedit.php (add activity) with no escape banner.
// Version bump so the changed renderer trait is picked up (class cache reset).
// WF-025b (2026-06-15) — decouple the role-VIEW scoping context from the
// capability switch: roleswitch()/role_switch_basedon_userroles() gained an
// $applyrsw flag; the first-visit auto-call (user_menu.php) now passes false so
// it sets currentroleinfo (org-scoping) WITHOUT writing $USER->access['rsw'].
// Role-switching capabilities now change ONLY on an explicit user action.
// Revised Brand Book 2026-06 (BB-revamp-noweb.pdf) — Phase 1 token foundation:
// retire off-brand teal from --ap-color-accent (→ brand bright-blue #1985DD),
// add brand secondaries (#1985DD/#9cdbf4/#0d5da1/#ed692b/#6d58a5) + brand
// gradient tokens. Primary #0066A7 + Montserrat already matched the book.
// See docs/audits/brand-revamp-2026-06/REVISED-BRAND-BOOK-2026-06.md.
// 2026061601 — Phase 1 teal-gap closure (rgba/dark-teal forms) + Phase 2 login
// hero elevation: faint "a" monogram watermark + sparing brand-orange accent bar.
// 2026061602 — Phase 2 cont'd: frontpage/storefront hero elevation ("a" monogram
// watermark + sparing orange title accent) + card-thumb variety palette remapped
// to brand secondaries (blue-dominant, sparing purple/orange — no cyan/magenta/gold).
// 2026061603 — brand-verify closure: Bootstrap $info cyan->blue, dark-mode emerald
// accent->blue, decorative emerald/amber/red->brand, violet->purple, gold->orange, a11y.
// 2026072200 — mform element-template repair: label-text leak out of class/id attrs,
// float-sm-right(s) typo, new_req GIF -> core req FontAwesome icon; dark-mode
// autofill repaint on signup/forgot (see _surface-login.scss section 3b).
// 2026080301 — UI-NAV residue closure: (1) NEW pix/course_default.svg — the
// course_bannerimage() fallback (course_view.php) referenced a pix asset that
// was never shipped, 404ing on every course without an overview image; now a
// branded gradient banner. (2) dark_mode.scss component rules tokenized
// (104 hexes -> --ap-color-* vars, computed-value-identical; core remap block
// + high-contrast section deliberately untouched — the latter has no token
// remap of its own, see inline comments). Bump ships the new themerev for
// the SVG + recompiled CSS.
// 2026080302 — UI-NAV i18n closure: shell chrome internationalized (31 new
// en+hi string pairs). sidebar_navigation.php nav labels (40 call-sites,
// core strings reused where exact: myhome/reports/notifications/profile),
// course.mustache player strings + aria-labels ({$a} param forms), topbar
// "Open menu" + search placeholder. Restores the 100%-Hindi-parity policy
// in the flagship chrome; the new lang-parity CI gate prevents recurrence.
$plugin->version   = 2026090400;  // skills-first dashboard recs (ADR-028 P2.2, flag-gated)
$plugin->requires  = 2022041900;
$plugin->component = 'theme_sentientia';
$plugin->maturity  = MATURITY_BETA;
// P0 #5 dashboard inline-style cleanup (2026-05-24, chip-C) — 34 inline
// style attrs (incl. 5 raw hex literals) migrated from dashboard.mustache
// into _surface-dashboard.scss as token-driven BEM rules; dynamic values
// flow via CSS custom properties. See F-12 in PLATFORM-VISUAL-AUDIT.
// F-13 dashboard i18n (2026-05-24, chip-G) — 12 new lang strings added
// to theme_sentientia across all 5 locales backing {{#str}} substitutions
// of hardcoded English in dashboard.mustache. Closes §2.4 P0 items.
// P1 #13 dark-mode token-cascade (2026-05-24, chip-I) — dark_mode.scss
// reduced from 253 to 36 !important declarations (-85.8%) via parent-
// class specificity over Bootstrap defaults. Preserved !important
// blocks documented inline.
// P1 #11 chip-K (2026-05-24) — _surface-login.scss 66 → 11 !important
// (83% reduction). Section 1 wrapped under body#page-login-index for
// ID-specificity. Bundled bugfix: dark-mode selectors used descendant
// combinator (never fired since #page-X IS body); now chained.
$plugin->release   = '1.0.51-beta';  // course re-shell + breadcrumbs + topbar notifications + dark tokenization
// P1 #10 chip-J (2026-05-24) — _surface-profile.scss (2,507 lines)
// decomposed into 4 per-surface partials: _surface-user, _surface-badges,
// _surface-grade-report, _surface-calendar. Admin fragments moved to
// _legacy-admin.scss. Pure relocation refactor — no rule changes (F-16).
// P2 #18 chip-O (2026-05-24) — _moodle-overrides.scss reduced from 136 to
// 30 active !important declarations (-77.9%) via natural specificity wins.
// Six logical commit buckets: nav-drawer scheme icons, A11Y warning + btn-group
// radii, forms/cards/tables, toolbar/badge/focus/popovers, course-header/drawer,
// pagination/table-text-align/filter form. 26 preserved with inline rationale
// (Bootstrap utility .text-muted, DataTables vendor, YUI/JS inline styles,
// intra-file conflicts, jQuery UI font-family).
