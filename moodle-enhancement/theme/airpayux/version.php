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
$plugin->version   = 2026052324;
$plugin->requires  = 2022041900;
$plugin->component = 'theme_airpayux';
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.24-beta';  // Phase B.3 hook migration
