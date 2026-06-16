<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use local_sentientia_platform\tenant;

/**
 * v1: GET /courses/{id}/enrolments — active enrolments, tenant-scoped.
 *
 * Returns only users who belong to the same tenant as the caller (and the
 * course must be in the caller's tenant). Email is included only when the
 * caller is a site admin — for tenant managers we expose id + fullname only,
 * to avoid leaking PII more broadly than necessary.
 *
 * @package local_sentientia_api
 */
class list_enrolments extends base {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'page'     => new external_value(PARAM_INT, 'Zero-based page index', VALUE_DEFAULT, 0),
            'perpage'  => new external_value(PARAM_INT, 'Page size (max 200)', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * @param int $courseid
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function execute(int $courseid, int $page = 0, int $perpage = 50): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'page', 'perpage'));

        $callerroot = self::open_v1('local_sentientia_api_v1_list_enrolments', 'local/sentientia_api:read');

        $page = max(0, $params['page']);
        $perpage = min(200, max(1, $params['perpage']));

        // Course must be in the caller's tenant.
        $course = $DB->get_record('course', ['id' => $params['courseid']],
            'id, open_path', MUST_EXIST);
        tenant::require_path_access((string) ($course->open_path ?? ''));

        $isadmin = is_siteadmin();

        // Scope enrolled users to the caller's tenant tree (unless admin).
        $userwhere = '';
        $userargs = [];
        if (!$isadmin && $callerroot > 0) {
            $exact = '/' . $callerroot;
            $prefix = '/' . $callerroot . '/%';
            $userwhere = ' AND (u.open_path = :pexact OR ' . $DB->sql_like('u.open_path', ':pprefix') . ')';
            $userargs = ['pexact' => $exact, 'pprefix' => $prefix];
        }

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, ue.timestart, ue.status
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                 WHERE e.courseid = :cid
                   AND u.deleted = 0
                   $userwhere
              ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC";
        $args = array_merge(['cid' => $params['courseid']], $userargs);

        $records = $DB->get_records_sql($sql, $args, $page * $perpage, $perpage);

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'userid'    => (int) $r->id,
                'fullname'  => format_string(fullname($r)),
                'email'     => $isadmin ? \core_user::clean_field($r->email, 'email') : '',
                'timestart' => (int) $r->timestart,
                'active'    => ((int) $r->status === ENROL_USER_ACTIVE),
            ];
        }

        return ['courseid' => (int) $params['courseid'], 'enrolments' => $rows];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'    => new external_value(PARAM_INT, 'Course id'),
            'enrolments'  => new external_multiple_structure(
                new external_single_structure([
                    'userid'    => new external_value(PARAM_INT,  'User id'),
                    'fullname'  => new external_value(PARAM_TEXT, 'User full name'),
                    'email'     => new external_value(PARAM_RAW,  'Email — only populated for site admins; empty for tenant managers'),
                    'timestart' => new external_value(PARAM_INT,  'Enrolment start (epoch)'),
                    'active'    => new external_value(PARAM_BOOL, 'Whether the enrolment is active'),
                ])
            ),
        ]);
    }
}
