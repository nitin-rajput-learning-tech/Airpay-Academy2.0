<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_manager\external;

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
        require_capability('local/airpay_manager:view', $context);

        // Default to current user; siteadmin can specify any manager.
        $target_mid = $params['managerid'] ?: (int) $USER->id;
        if ($target_mid !== (int) $USER->id && !is_siteadmin()) {
            throw new \moodle_exception('nopermissions', 'error',
                '', 'view another manager\'s team');
        }

        $cutoff = time() - ($params['period_days'] * 86400);

        // Defensive: open_managerid may not exist on production Moodle 5.
        $cols = $DB->get_columns('user');
        if (!isset($cols['open_managerid'])) {
            return [
                'period_days' => $params['period_days'],
                'managerid'   => $target_mid,
                'team'        => [],
                'message'     => 'open_managerid column missing — team detection unavailable',
            ];
        }

        // Get team members.
        $team = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email,
                    u.open_employeeid, u.open_designation, u.lastaccess
               FROM {user} u
              WHERE u.open_managerid = :mid
                AND u.deleted = 0 AND u.suspended = 0
              ORDER BY u.lastname ASC",
            ['mid' => $target_mid]);

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
