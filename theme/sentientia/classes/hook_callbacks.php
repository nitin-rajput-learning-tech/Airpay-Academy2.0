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

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for theme_sentientia.
 *
 * Replaces the legacy function-name callbacks (e.g.
 * `theme_sentientia_before_standard_top_of_body_html`) with proper hook
 * subscriptions per Moodle 5.2's new hook system.
 *
 * The legacy function in lib.php is kept (delegating to this class)
 * so the older callback signature still fires under 5.1 deployments
 * that haven't migrated yet. On 5.2 the legacy callback is
 * intentionally a no-op and this class is the canonical entry point.
 *
 * Migration record
 * ----------------
 * Phase B.3 web smoke (2026-05-23) on Moodle 5.2 surfaced this
 * deprecation notice:
 *   "Callback before_standard_top_of_body_html in theme_sentientia
 *    component should be migrated to new hook callback for
 *    core\hook\output\before_standard_top_of_body_html_generation"
 * This class is the migration target.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Inject the suspended-user status JSON blob into the page body.
     *
     * Powers P0 borrow #10 — the AMD decorator at
     * theme_sentientia/user_status_badge reads this JSON and paints
     * "Suspended" / "Deleted" pills next to user-name links on
     * report-like surfaces.
     *
     * Surface filter (same as the legacy implementation):
     *   - $PAGE->pagetype starts with `grade-report-`
     *   - $PAGE->pagetype starts with `report-`
     *   - $PAGE->pagetype === 'user-index'
     *   - $PAGE->pagetype === 'course-user'
     *
     * Tenant scope via $USER->open_path. Silently no-ops outside the
     * report-y surfaces or when the column isn't present.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        $html = self::build_user_status_html();
        if ($html !== '') {
            $hook->add_html($html);
        }
    }

    /**
     * Build the HTML snippet — identical contract to the legacy
     * function in lib.php so the AMD decorator works unchanged.
     *
     * @return string Hidden <script type="application/json"> blob, or ''
     */
    public static function build_user_status_html(): string {
        global $PAGE, $USER, $DB;

        // Anonymous / unset session — nothing to render.
        if (empty($USER->id) || isguestuser()) {
            return '';
        }
        if (empty($PAGE) || empty($PAGE->pagetype)) {
            return '';
        }

        $pt = $PAGE->pagetype;
        $needs = (
            str_starts_with($pt, 'grade-report-')
            || str_starts_with($pt, 'report-')
            || $pt === 'user-index'
            || $pt === 'course-user'
        );
        if (!$needs) {
            return '';
        }

        // Tenant scope — BizLMS open_path. If column missing (test
        // fixture without local_costcenter), silently no-op.
        $tenantpath = $USER->open_path ?? '';
        if (!$tenantpath) {
            return '';
        }

        try {
            // Fetch all suspended OR deleted users in the same tenant subtree.
            $sql = "SELECT id, suspended, deleted
                      FROM {user}
                     WHERE (suspended = 1 OR deleted = 1)
                       AND open_path LIKE :path";
            $rows = $DB->get_records_sql($sql, ['path' => $tenantpath . '%']);
        } catch (\Throwable $e) {
            // PHPUnit fixture without open_path, or DB hiccup.
            return '';
        }

        if (empty($rows)) {
            return '';
        }

        // Build compact userid → status map.
        $data = [];
        foreach ($rows as $r) {
            $data[(int)$r->id] = !empty($r->deleted) ? 'deleted' : 'suspended';
        }

        // Queue the AMD decorator. It picks up the JSON blob on init.
        $PAGE->requires->js_call_amd('theme_sentientia/user_status_badge', 'init');

        // Embed the data inline. Hidden from screen readers via
        // aria-hidden; type=application/json so it's never executed
        // as JS, only parsed.
        return '<script id="airpay-user-status-data" type="application/json" aria-hidden="true">'
            . json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            . '</script>';
    }
}
