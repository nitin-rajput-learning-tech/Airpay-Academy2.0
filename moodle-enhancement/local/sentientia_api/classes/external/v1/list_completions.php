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
 * v1: GET /courses/{id}/completions — course completion records, tenant-scoped.
 *
 * @package local_sentientia_api
 */
class list_completions extends base {

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

        $callerroot = self::open_v1('local_sentientia_api_v1_list_completions', 'local/sentientia_api:read');

        $page = max(0, $params['page']);
        $perpage = min(200, max(1, $params['perpage']));

        $course = $DB->get_record('course', ['id' => $params['courseid']],
            'id, open_path', MUST_EXIST);
        tenant::require_path_access((string) ($course->open_path ?? ''));

        $userwhere = '';
        $userargs = [];
        if (!is_siteadmin() && $callerroot > 0) {
            $userwhere = ' AND (u.open_path = :pexact OR ' . $DB->sql_like('u.open_path', ':pprefix') . ')';
            $userargs = ['pexact' => '/' . $callerroot, 'pprefix' => '/' . $callerroot . '/%'];
        }

        $sql = "SELECT cc.id, cc.userid, cc.timecompleted, u.firstname, u.lastname
                  FROM {course_completions} cc
                  JOIN {user} u ON u.id = cc.userid
                 WHERE cc.course = :cid
                   AND u.deleted = 0
                   $userwhere
              ORDER BY cc.timecompleted DESC, cc.id ASC";
        $args = array_merge(['cid' => $params['courseid']], $userargs);

        $records = $DB->get_records_sql($sql, $args, $page * $perpage, $perpage);

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'userid'        => (int) $r->userid,
                'fullname'      => format_string(fullname($r)),
                'completed'     => !empty($r->timecompleted),
                'timecompleted' => (int) ($r->timecompleted ?? 0),
            ];
        }

        return ['courseid' => (int) $params['courseid'], 'completions' => $rows];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'    => new external_value(PARAM_INT, 'Course id'),
            'completions' => new external_multiple_structure(
                new external_single_structure([
                    'userid'        => new external_value(PARAM_INT,  'User id'),
                    'fullname'      => new external_value(PARAM_TEXT, 'User full name'),
                    'completed'     => new external_value(PARAM_BOOL, 'Whether the course is completed'),
                    'timecompleted' => new external_value(PARAM_INT,  'Completion time (epoch, 0 if not completed)'),
                ])
            ),
        ]);
    }
}
