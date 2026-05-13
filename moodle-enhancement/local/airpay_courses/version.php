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
 * Plugin version — Airpay Course Engine.
 *
 * Replaces BizLMS local_courses with Airpay-owned course management,
 * progress tracking, and open_* course field ownership.
 *
 * @package    local_airpay_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_courses';
$plugin->version   = 2026051302;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.7.0';
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
// Release history:
// 1.6.0  Phase F.5 — native enrol modal (replaces deep-link)
// 1.7.0  Sprint C (2026-05-13) — cross-tenant course sharing:
//          + local_airpay_courses_tenant_share table
//          + share_course / unshare_course / list_course_shares WS
//          + local/airpay_courses:share_to_tenant capability
//          + share.php admin page + share_modal.mustache template
//          + sharing_manager class (CRUD + catalog-filter builder)
//          + audit log entry per share/unshare
