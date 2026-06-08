<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * List users enrolled in a given course.
 *
 * Used by enrolledusers.php datatable. Includes per-user completion
 * status + last access for quick admin overview.
 */
class list_course_enrolments extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'lastname'),
            'sortdir' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'lastname',
                                    string $sortdir = 'asc', int $page = 0,
                                    int $perpage = 25, string $filters = '{}'): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_courses:view', $ctx);

        $client = json_decode($params['filters'] ?: '{}', true) ?: [];
        $courseid = (int) ($client['courseid'] ?? 0);
        if ($courseid <= 0) {
            throw new \moodle_exception('invalidcourse');
        }

        // Tenant scope: non-siteadmin can only inspect courses in their
        // tree. Phase 9.7 — back-ported to
        // `\local_airpay_core\tenant::require_path_access()`. The helper
        // throws `error_outoftenant` on mismatch, ignores rows with
        // empty open_path (legacy unscoped tolerance), and short-
        // circuits for siteadmins.
        $course = $DB->get_record('course', ['id' => $courseid], 'open_path');
        if ($course) {
            \local_airpay_core\tenant::require_path_access(
                (string) ($course->open_path ?? ''));
        }

        $allowed_sort = ['lastname', 'firstname', 'email', 'lastaccess', 'completed'];
        $sort = in_array($params['sort'], $allowed_sort, true) ? $params['sort'] : 'lastname';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        // Sort by completion needs to use the subquery alias.
        $orderbycol = $sort === 'completed' ? 'completed' : "u.{$sort}";

        // Where: enrolled in this course.
        $where = ['u.deleted = 0', 'e.courseid = :cid'];
        $args  = ['cid' => $courseid];

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('u.firstname', ':s1', false) . ' OR '
                . $DB->sql_like('u.lastname', ':s2', false) . ' OR '
                . $DB->sql_like('u.email', ':s3', false) . ' OR '
                . $DB->sql_like("COALESCE(u.open_employeeid, '')", ':s4', false) . ')';
            $args['s1'] = $term; $args['s2'] = $term;
            $args['s3'] = $term; $args['s4'] = $term;
        }

        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->get_field_sql(
            "SELECT COUNT(DISTINCT u.id)
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE $wheresql",
            $args);

        $rows = [];
        if ($total > 0) {
            $sql = "SELECT u.id, u.firstname, u.lastname, u.email,
                           u.open_employeeid, u.lastaccess, u.suspended,
                           e.enrol AS enrol_method,
                           ue.id AS ueid,
                           ue.status AS enrol_status,
                           ue.timestart AS enrol_start,
                           ue.timeend AS enrol_end,
                           (SELECT timecompleted FROM {course_completions}
                             WHERE course = e.courseid AND userid = u.id
                             LIMIT 1) AS completed
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE $wheresql
                     ORDER BY $orderbycol $sortdir, u.id ASC";

            $records = $DB->get_records_sql($sql, $args,
                $params['page'] * $params['perpage'], $params['perpage']);
            foreach ($records as $r) {
                $is_completed = !empty($r->completed);
                $is_manual = $r->enrol_method === 'manual';
                $status_label = $r->enrol_status == 0 ? 'Active' : 'Suspended';
                $status_css   = $r->enrol_status == 0 ? 'badge-success' : 'badge-warning';
                $comp_label = $is_completed ? 'Completed' : 'In progress';
                $comp_css   = $is_completed ? 'badge-success' : 'badge-secondary';

                $actions = '';
                if ($is_manual) {
                    $username = trim($r->firstname . ' ' . $r->lastname);
                    $actions = '<a href="#" class="btn btn-sm btn-link text-danger p-1" '
                        . 'data-action="unenrol-user" data-userid="' . (int) $r->id . '" '
                        . 'data-username="' . s($username) . '" '
                        . 'title="Unenrol"><i class="fa fa-user-times"></i></a>';
                }

                $rows[] = [
                    'userid'         => (int) $r->id,
                    'fullname'       => trim($r->firstname . ' ' . $r->lastname),
                    'email'          => (string) $r->email,
                    'employee_id'    => (string) ($r->open_employeeid ?? ''),
                    'enrol_method'   => (string) $r->enrol_method,
                    'status'         => $status_label,
                    'statuslabel'    => $status_label,
                    'statuscss'      => $status_css,
                    'enrol_start'    => $r->enrol_start ? userdate($r->enrol_start, '%d %b %Y') : '',
                    'last_access'    => $r->lastaccess ? userdate($r->lastaccess, '%d %b %Y %H:%M') : 'Never',
                    'completed'      => $r->completed ? userdate($r->completed, '%d %b %Y') : '',
                    'is_completed'   => $is_completed,
                    'completionlabel' => $comp_label,
                    'completioncss'  => $comp_css,
                    'ueid'           => (int) $r->ueid,
                    'actions'        => $actions,
                ];
            }
        }

        return ['total' => $total, 'rows' => $rows,
                'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, ''),
            'rows'  => new external_multiple_structure(new external_single_structure([
                'userid'          => new external_value(PARAM_INT, ''),
                'fullname'        => new external_value(PARAM_TEXT, ''),
                'email'           => new external_value(PARAM_TEXT, ''),
                'employee_id'     => new external_value(PARAM_TEXT, ''),
                'enrol_method'    => new external_value(PARAM_TEXT, ''),
                'status'          => new external_value(PARAM_TEXT, ''),
                'statuslabel'     => new external_value(PARAM_TEXT, ''),
                'statuscss'       => new external_value(PARAM_TEXT, ''),
                'enrol_start'     => new external_value(PARAM_TEXT, ''),
                'last_access'     => new external_value(PARAM_TEXT, ''),
                'completed'       => new external_value(PARAM_TEXT, ''),
                'is_completed'    => new external_value(PARAM_BOOL, ''),
                'completionlabel' => new external_value(PARAM_TEXT, ''),
                'completioncss'   => new external_value(PARAM_TEXT, ''),
                'ueid'            => new external_value(PARAM_INT, ''),
                'actions'         => new external_value(PARAM_RAW, ''),
            ])),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
