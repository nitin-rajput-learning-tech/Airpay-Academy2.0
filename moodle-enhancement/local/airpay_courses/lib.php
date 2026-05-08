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
 * Library functions — Airpay Course Engine.
 *
 * @package    local_airpay_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Phase F.2 (2026-05-08) — render the featured-courses widget HTML for
 * the current user. Returns empty string if the widget is empty (so
 * layout files can blindly include it).
 *
 * @param int|null $userid  Defaults to $USER->id when null
 * @param int $limit        Max courses to show (default 6)
 * @return string  Rendered HTML (already format_string'd)
 */
function local_airpay_courses_render_featured_widget(?int $userid = null,
                                                      int $limit = 6): string {
    global $USER, $OUTPUT;
    $uid = $userid ?? (int) $USER->id;
    if ($uid <= 0) {
        return '';
    }
    try {
        $ctx = \local_airpay_courses\featured_manager::get_widget_for_user(
            $uid, max(1, min(24, $limit)));
        if (!$ctx['has_courses']) {
            return '';
        }
        return $OUTPUT->render_from_template(
            'local_airpay_courses/featured_widget', $ctx);
    } catch (\Throwable $e) {
        debugging('Featured widget render failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER);
        return '';
    }
}
