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
$plugin->version   = 2026052212;
$plugin->requires  = 2022041900;
$plugin->component = 'theme_airpayux';
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.12-beta';  // +Workstream 0 customer_brand wiring
