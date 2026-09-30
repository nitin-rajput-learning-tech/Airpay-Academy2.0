<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_manager\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Team performance dashboard — aggregated metrics per team member.
 *
 * Returns per-direct-report rows with:
 *   - course completions (last 30d)
 *   - quiz attempts (last 30d)
 *   - active days (logged in)
 *   - allocated courses count + completion %
 *
 * Phase 4 B.10 (2026-05-11).
 *
 * Persona pass D4 (2026-09-30): the page (performance.php) opened for a line
 * manager but this service refused them, and would have returned nothing even
 * if it had not. Two defects, both fixed here:
 *
 *   1. Gate. It called require_capability('local/sentientia_manager:view'),
 *      which a supervisor who was never given the Moodle `manager` role does not
 *      hold. The 2026-05-22 fix (Goal A audit Bug #9b) moved list_requests and
 *      list_allocations to team_manager::require_manage() and missed this one.
 *      It now uses the same supervisor-aware gate as the page and its siblings.
 *   2. Team query. It read `user.open_managerid`, a column that has never
 *      existed on production (it is not in the BizLMS schema), and returned
 *      "team detection unavailable" whenever it was absent. The reporting line
 *      is `user.open_supervisorid` (the column approval_manager::direct_report_ids()
 *      reads, and the one local_sentientia_core\org::direct_reports() resolves
 *      under the default org_legacy flag). The team now comes from
 *      team_manager::get_team(), which is what the My Team dashboard (index.php)
 *      lists, so the two pages cannot disagree about who is on a team.
 */
class team_performance extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'managerid' => new external_value(PARAM_INT, '0 = current user', VALUE_DEFAULT, 0),
            'period_days' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 30),
        ]);
    }

    public static function execute(int $managerid = 0, int $period_days = 30): array {
        global $USER, $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('managerid', 'period_days'));

        $context = \context_system::instance();
        self::validate_context($context);
        // D4: supervisor-or-capability, exactly as performance.php, list_requests
        // and list_allocations gate. Site admins, holders of :view, and anyone
        // who is somebody's supervisor pass; everybody else is refused.
        \local_sentientia_manager\team_manager::require_manage();

        // Default to current user; siteadmin can specify any manager. Nobody
        // else may read another manager's team, so a manager in one tenant can
        // never ask for a manager in another tenant.
        $target_mid = $params['managerid'] ?: (int) $USER->id;
        if ($target_mid !== (int) $USER->id && !is_siteadmin()) {
            throw new \moodle_exception('nopermissions', 'error',
                '', 'view another manager\'s team');
        }

        $cutoff = time() - ($params['period_days'] * 86400);

        // The team: direct reports through the org seam (open_supervisorid under
        // org_legacy ON), active and not deleted, ordered by name. It is the list
        // the My Team dashboard shows. On a database with no reporting-line column
        // at all the seam degrades to an empty team, which the page renders as its
        // "no direct reports" empty state.
        $team = \local_sentientia_manager\team_manager::get_team((int) $target_mid);

        $rows = [];
        foreach ($team as $member) {
            $completions = (int) $DB->get_field_sql(
                "SELECT COUNT(*) FROM {course_completions}
                  WHERE userid = :uid AND timecompleted > :cutoff",
                ['uid' => $member->id, 'cutoff' => $cutoff]);

            $attempts = (int) $DB->get_field_sql(
                "SELECT COUNT(*) FROM {quiz_attempts}
                  WHERE userid = :uid AND state = 'finished'
                    AND timefinish > :cutoff",
                ['uid' => $member->id, 'cutoff' => $cutoff]);

            // Total enrolled courses + completed count.
            $enrolled = (int) $DB->get_field_sql(
                "SELECT COUNT(DISTINCT e.courseid)
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE ue.userid = :uid AND ue.status = 0",
                ['uid' => $member->id]);

            $completed_all = (int) $DB->get_field_sql(
                "SELECT COUNT(*) FROM {course_completions}
                  WHERE userid = :uid AND timecompleted IS NOT NULL
                    AND timecompleted > 0",
                ['uid' => $member->id]);

            $completion_pct = $enrolled > 0
                ? round(100 * $completed_all / $enrolled, 1) : 0;

            // Active in period?
            $is_active = $member->lastaccess > $cutoff;

            $rows[] = [
                'userid'        => (int) $member->id,
                'fullname'      => trim($member->firstname . ' ' . $member->lastname),
                'email'         => (string) $member->email,
                'employee_id'   => (string) ($member->open_employeeid ?? ''),
                'designation'   => (string) ($member->open_designation ?? ''),
                'completions'   => $completions,
                'attempts'      => $attempts,
                'enrolled'      => $enrolled,
                'completed_all' => $completed_all,
                'completion_pct' => $completion_pct,
                'is_active'     => $is_active,
                'last_access'   => $member->lastaccess
                    ? userdate($member->lastaccess, '%d %b %Y') : 'Never',
            ];
        }

        return [
            'period_days' => $params['period_days'],
            'managerid'   => $target_mid,
            'team'        => $rows,
            'message'     => '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'period_days' => new external_value(PARAM_INT, ''),
            'managerid'   => new external_value(PARAM_INT, ''),
            'message'     => new external_value(PARAM_TEXT, ''),
            'team'        => new external_multiple_structure(new external_single_structure([
                'userid'         => new external_value(PARAM_INT, ''),
                'fullname'       => new external_value(PARAM_TEXT, ''),
                'email'          => new external_value(PARAM_TEXT, ''),
                'employee_id'    => new external_value(PARAM_TEXT, ''),
                'designation'    => new external_value(PARAM_TEXT, ''),
                'completions'    => new external_value(PARAM_INT, ''),
                'attempts'       => new external_value(PARAM_INT, ''),
                'enrolled'       => new external_value(PARAM_INT, ''),
                'completed_all'  => new external_value(PARAM_INT, ''),
                'completion_pct' => new external_value(PARAM_FLOAT, ''),
                'is_active'      => new external_value(PARAM_BOOL, ''),
                'last_access'    => new external_value(PARAM_TEXT, ''),
            ])),
        ]);
    }
}
