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

namespace theme_airpayux;

defined('MOODLE_INTERNAL') || die();

/**
 * Single source of truth for role detection across the airpayux theme.
 *
 * Background — Bug #11 (2026-05-22 Goal A audit):
 * The dashboard layout (`layout/dashboard.php`) and the sidebar navigation
 * (`classes/sidebar_navigation.php`) each implemented their own copy of
 * role detection. They drifted: dashboard recognised Joseph Mandapati
 * (Compliance Officer, id 627) as L&D Admin via his BizLMS administrator
 * role at category context, but sidebar only checked the system cap and
 * fell back to Learner. So Joseph saw the L&D Admin dashboard wrapped in
 * a Learner-shaped sidebar — incoherent UI.
 *
 * The bug fix landed in 40fb6fb3b restored consistency by copying
 * dashboard's logic into sidebar. This class promotes that pattern to
 * an actual architectural invariant: ANY future caller that needs role
 * tier should consume `role_detector::detect()` rather than reimplement.
 *
 * Detection rules (mirrors `local/sentientia_compliance_report/index.php`
 * page-layer auth and `layout/dashboard.php` view selection):
 *
 *   issiteadmin = is_siteadmin($USER)
 *   isldadmin   = !issiteadmin && !switched_to_employee && (
 *                   has_capability('local/airpay_courses:manage', system) ||
 *                   record_exists in {role_assignments} with
 *                     role.shortname = 'administrator' AND
 *                     context.contextlevel = 40 (category)
 *                 )
 *
 * Cap-rename note (2026-05-28 stabilization audit F-080/F-088): we used to
 * also probe the non-namespaced `local/courses:manage` here — but the cap
 * was never registered in any db/access.php, so every call logged a debug
 * Notice. The fallback has been removed; the airpay_courses-namespaced
 * cap is the only one we need (it covers the L&D Admin and Operations
 * Administrator roles that grant manage on the org subtree).
 *   ismanager   = !isadmin && (
 *                   has_capability('moodle/site:viewreports', system) ||
 *                   count_records(user open_supervisorid = $USER->id) > 0
 *                 )
 *   islearner   = !isadmin && !ismanager
 *
 * switched_to_employee respects the BizLMS role-switch flow: a user who
 * is normally L&D Admin can switch into the employee role to test the
 * learner experience; the detector then returns Learner for them until
 * they switch back.
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_detector {

    /**
     * Detect the role tier of the current user (or a specific user).
     *
     * @param int|null $userid Optional — defaults to $USER->id.
     * @return array Keys: issiteadmin, isldadmin, ismanager, islearner,
     *               isadmin (= siteadmin || ldadmin),
     *               switched_to_employee
     */
    public static function detect(?int $userid = null): array {
        global $USER, $SESSION, $DB;

        if ($userid === null) {
            $userid = (int) ($USER->id ?? 0);
        }

        $systemcontext = \context_system::instance();
        $issiteadmin = is_siteadmin($userid);

        // ═══ BizLMS Role Switch Detection ═══
        // If user has switched to a lower role (e.g., admin → employee),
        // respect it. BizLMS stores the active role in
        // $USER->useraccess['currentroleinfo'] or in our session.
        $switchedtoemployee = false;
        $employeeroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'employee']);

        // Session-based switch from /my/switchrole.php — only honour when
        // the detection is for the *current* user (the session is theirs).
        if ($userid === (int) ($USER->id ?? 0)
                && !empty($SESSION->airpay_switchrole->roleid)) {
            $switchedroleid = (int) $SESSION->airpay_switchrole->roleid;
            if ($switchedroleid === $employeeroleid) {
                $switchedtoemployee = true;
            }
        }

        // BizLMS $USER->useraccess role-switch (set during BizLMS login).
        // Same: only honour for the current user — $USER->useraccess is
        // per-session data.
        if (!$switchedtoemployee
                && $userid === (int) ($USER->id ?? 0)
                && !empty($USER->useraccess['currentroleinfo']['contextinfo'])) {
            $firstrole = current($USER->useraccess['currentroleinfo']['contextinfo']);
            $activeroleid = (int) ($firstrole['roleid'] ?? 0);
            if ($activeroleid === $employeeroleid) {
                $switchedtoemployee = true;
            }
        }

        // L&D Admin: cap-based OR BizLMS administrator role at category context.
        // Skip if user has switched to employee role.
        $isldadmin = false;
        if (!$issiteadmin && !$switchedtoemployee) {
            // Phase 1 stabilization fix (F-080/F-088, 2026-05-28): removed
            // `local/courses:manage` probe — cap was never registered, so it
            // logged a debug Notice every page render and silently locked
            // L&D Admins out of the course-management sidebar. The
            // airpay_courses-namespaced cap is the canonical one.
            $isldadmin = has_capability('local/airpay_courses:manage', $systemcontext, $userid);
            if (!$isldadmin) {
                try {
                    // Note: no LIMIT clause — record_exists_sql adds it
                    // internally; doubling triggers dml_read_exception.
                    // (Caught the hard way in Bug #11's first-pass fix.)
                    $isldadmin = $DB->record_exists_sql(
                        "SELECT 1 FROM {role_assignments} ra
                           JOIN {context} ctx ON ctx.id = ra.contextid
                           JOIN {role} r ON r.id = ra.roleid
                          WHERE ra.userid = :uid
                            AND r.shortname = :rolename
                            AND ctx.contextlevel = 40",
                        ['uid' => $userid, 'rolename' => 'administrator']);
                } catch (\Throwable $e) {
                    // Schema mismatch on stock Moodle without BizLMS —
                    // fail closed (not L&D Admin).
                }
            }
        }

        $isadmin = $issiteadmin || $isldadmin;

        // Manager: cap OR direct reports via open_supervisorid.
        $ismanager = false;
        if (!$isadmin && !$switchedtoemployee) {
            $ismanager = has_capability('moodle/site:viewreports', $systemcontext, $userid);
            if (!$ismanager) {
                // Guard the open_supervisorid column — it's a BizLMS
                // extension that may not exist on stock Moodle.
                try {
                    $manager = $DB->get_manager();
                    $usertable = new \xmldb_table('user');
                    $superfield = new \xmldb_field('open_supervisorid');
                    if ($manager->field_exists($usertable, $superfield)) {
                        $directreports = $DB->count_records_select('user',
                            'open_supervisorid = :uid AND deleted = 0 AND suspended = 0',
                            ['uid' => $userid]);
                        $ismanager = $directreports > 0;
                    }
                } catch (\Throwable $e) {
                    // Stock Moodle without BizLMS — not a manager.
                }
            }
        }

        $islearner = !$isadmin && !$ismanager;

        return [
            'issiteadmin'           => $issiteadmin,
            'isldadmin'             => $isldadmin,
            'isadmin'               => $isadmin,
            'ismanager'             => $ismanager,
            'islearner'             => $islearner,
            'switched_to_employee'  => $switchedtoemployee,
        ];
    }
}
