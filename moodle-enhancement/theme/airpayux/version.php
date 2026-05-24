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
 * Airpay Academy UX theme — forked from epsilon (BizLMS).
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services (forked from eAbyas epsilon)
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
// The epsilon parent theme was removed when we made airpayux a
// standalone fork ($THEME->parents = []), but lib.php still had three
// hard-coded epsilon paths. Repointed to /theme/airpayux/scss/preset/*.
// All preset files (default.scss, plain.scss) already exist there.
//
// Phase B.3 hook migration (2026-05-23) — Moodle 5.2 web smoke surfaced
// a deprecation notice asking us to migrate
// `theme_airpayux_before_standard_top_of_body_html()` to the new hook
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
//     theme_airpayux/cart_badge AMD module (src + build); wired from
//     layout/dashboard.php + layout/course.php
// Hindi parity restored to 100% (161 keys en / 161 keys hi) en route.
// See docs/visual-evidence/2026-05-24/p0-followup-chip-B/README.md.
//
// P0 #9 follow-up verification (2026-05-24) — spawned chip audited the
// AMD-wiring scope of the cart_badge module introduced by chip B.
// Per-layout walkthrough of all 10 airpayux layouts found that the
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
$plugin->version   = 2026052403;
$plugin->requires  = 2022041900;
$plugin->component = 'theme_airpayux';
$plugin->maturity  = MATURITY_BETA;
// P0 #5 dashboard inline-style cleanup (2026-05-24, chip-C) — 34 inline
// style attrs (incl. 5 raw hex literals) migrated from dashboard.mustache
// into _surface-dashboard.scss as token-driven BEM rules; dynamic values
// flow via CSS custom properties. See F-12 in PLATFORM-VISUAL-AUDIT.
// F-13 dashboard i18n (2026-05-24, chip-G) — 12 new lang strings added
// to theme_airpayux across all 5 locales backing {{#str}} substitutions
// of hardcoded English in dashboard.mustache. Closes §2.4 P0 items.
// P1 #13 dark-mode token-cascade (2026-05-24, chip-I) — dark_mode.scss
// reduced from 253 to 36 !important declarations (-85.8%) via parent-
// class specificity over Bootstrap defaults. Preserved !important
// blocks documented inline.
// P1 #11 chip-K (2026-05-24) — _surface-login.scss 66 → 11 !important
// (83% reduction). Section 1 wrapped under body#page-login-index for
// ID-specificity. Bundled bugfix: dark-mode selectors used descendant
// combinator (never fired since #page-X IS body); now chained.
$plugin->release   = '1.0.33-beta';  // P0 #1+#2+#3+#4+#5+#6+#7+#9 + F-13 + P1 #11+#12+#13+#14 + P2 #21 + cart_badge audit
// P1 #10 chip-J (2026-05-24) — _surface-profile.scss (2,507 lines)
// decomposed into 4 per-surface partials: _surface-user, _surface-badges,
// _surface-grade-report, _surface-calendar. Admin fragments moved to
// _bizlms-admin.scss. Pure relocation refactor — no rule changes (F-16).
