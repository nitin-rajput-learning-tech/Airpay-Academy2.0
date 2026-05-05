<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_reports;

defined('MOODLE_INTERNAL') || die();

/**
 * Report manager — CRUD for saved reports + built-in query runners.
 *
 * Each report type has a corresponding `run_*()` method that returns
 * structured rows. UI/templates call run_report($id) and render rows.
 *
 * @package    local_airpay_reports
 */
class report_manager {

    private const TABLE = 'local_airpay_reports';

    public const STATUS_ARCHIVED = 0;
    public const STATUS_ACTIVE   = 1;

    /** Built-in report types and their human labels. */
    public const REPORT_TYPES = [
        'course_completion'   => 'Course Completion — by user, course, and tenant',
        'compliance_overview' => 'Compliance Overview — mandatory training summary',
        'user_activity'       => 'User Activity — login + access stats',
        'enrolment_trend'     => 'Enrolment Trend — new enrolments over time',
    ];

    /** Quick-access labels for report cards (shorter). */
    public const REPORT_TYPE_SHORT = [
        'course_completion'   => 'Course Completion',
        'compliance_overview' => 'Compliance Overview',
        'user_activity'       => 'User Activity',
        'enrolment_trend'     => 'Enrolment Trend',
    ];

    public static function get(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    public static function count_reports(?int $status = null): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::TABLE)) return 0;
        if ($status === null) {
            return $DB->count_records(self::TABLE);
        }
        return $DB->count_records(self::TABLE, ['status' => $status]);
    }

    /**
     * Create a saved report definition.
     */
    public static function create(object $data): int {
        global $DB, $USER;

        if (empty($data->name) || empty($data->report_type)) {
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_reports');
        }

        if (!array_key_exists($data->report_type, self::REPORT_TYPES)) {
            throw new \moodle_exception('invalidreporttype', 'local_airpay_reports');
        }

        // Sanitise filter_config if provided (must be valid JSON).
        $filter_json = null;
        if (!empty($data->filter_config)) {
            if (is_array($data->filter_config)) {
                $filter_json = json_encode($data->filter_config);
            } else if (is_string($data->filter_config) && self::is_valid_json($data->filter_config)) {
                $filter_json = $data->filter_config;
            }
        }

        $record = (object) [
            'name'          => trim($data->name),
            'description'   => $data->description ?? '',
            'report_type'   => $data->report_type,
            'filter_config' => $filter_json,
            'costcenterid'  => (int) ($data->costcenterid ?? 0),
            'status'        => (int) ($data->status ?? self::STATUS_ACTIVE),
            'created_by'    => (int) $USER->id,
            'runcount'      => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ];

        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            if ($org) {
                $record->open_path = $org->path;
            }
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        $record = (object) ['id' => $id, 'timemodified' => time()];

        if (isset($data->name))         $record->name = trim($data->name);
        if (isset($data->description))  $record->description = $data->description;
        if (isset($data->report_type)) {
            if (!array_key_exists($data->report_type, self::REPORT_TYPES)) {
                throw new \moodle_exception('invalidreporttype', 'local_airpay_reports');
            }
            $record->report_type = $data->report_type;
        }
        if (isset($data->filter_config)) {
            if (is_array($data->filter_config)) {
                $record->filter_config = json_encode($data->filter_config);
            } else if (is_string($data->filter_config)) {
                $record->filter_config = self::is_valid_json($data->filter_config)
                    ? $data->filter_config : null;
            }
        }
        if (isset($data->costcenterid)) $record->costcenterid = (int) $data->costcenterid;
        if (isset($data->status))       $record->status = (int) $data->status;

        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            $record->open_path = $org ? $org->path : '';
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    public static function toggle_status(int $id, ?bool $active = null): bool {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['id' => $id], 'id, status', MUST_EXIST);
        $newstate = $active ?? !((bool) $existing->status);
        $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'status' => $newstate ? self::STATUS_ACTIVE : self::STATUS_ARCHIVED,
            'timemodified' => time(),
        ]);
        return $newstate;
    }

    public static function delete(int $id): bool {
        global $DB;
        $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        $DB->delete_records(self::TABLE, ['id' => $id]);
        return true;
    }

    /**
     * Execute a saved report — dispatches to the type-specific runner.
     *
     * @return array{columns: array, rows: array, summary: array}
     */
    public static function run_report(int $id): array {
        global $DB;
        $report = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $config = !empty($report->filter_config)
            ? (json_decode($report->filter_config, true) ?: [])
            : [];

        // Tenant scope from saved report.
        $org_path = '';
        if (!empty($report->open_path)) {
            $org_path = $report->open_path;
        }

        $result = match ($report->report_type) {
            'course_completion'   => self::run_course_completion($org_path, $config),
            'compliance_overview' => self::run_compliance_overview($org_path, $config),
            'user_activity'       => self::run_user_activity($org_path, $config),
            'enrolment_trend'     => self::run_enrolment_trend($org_path, $config),
            default => ['columns' => [], 'rows' => [], 'summary' => []],
        };

        // Update lastrun + runcount.
        $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'lastrun' => time(),
            'runcount' => (int) $report->runcount + 1,
        ]);

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Built-in report runners
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Course completion — rows per user x course with completion status.
     */
    private static function run_course_completion(string $org_path, array $config): array {
        global $DB;

        $where = ['c.id > 1', 'c.visible = 1', 'u.deleted = 0'];
        $params = [];

        if (!empty($org_path)) {
            $where[] = "(u.open_path = :orgexact OR u.open_path LIKE :orgpath)";
            $params["orgexact"] = rtrim($org_path, "/");
            $params['orgpath'] = $DB->sql_like_escape(rtrim($org_path, '/') . '/') . '%';
        }

        $sql = "SELECT cc.id, u.firstname, u.lastname, u.email, u.open_employeeid,
                       u.open_path, c.fullname AS coursename, c.shortname,
                       cc.timecompleted, cc.timestarted
                  FROM {course_completions} cc
                  JOIN {user} u ON u.id = cc.userid
                  JOIN {course} c ON c.id = cc.course
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY cc.timecompleted DESC, u.lastname ASC
                 LIMIT 500";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        $completed = 0;
        $in_progress = 0;

        foreach ($records as $r) {
            $is_complete = !empty($r->timecompleted);
            $rows[] = [
                'fullname'    => trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')),
                'email'       => $r->email,
                'employeeid'  => $r->open_employeeid ?? '',
                'coursename'  => $r->coursename,
                'coursecode'  => $r->shortname,
                'started'     => $r->timestarted ? userdate($r->timestarted, '%d %b %Y') : '—',
                'completed'   => $is_complete ? userdate($r->timecompleted, '%d %b %Y') : '—',
                'status'      => $is_complete ? 'Completed' : 'In Progress',
            ];
            if ($is_complete) $completed++;
            else $in_progress++;
        }

        return [
            'columns' => [
                ['key' => 'fullname',   'label' => 'Name'],
                ['key' => 'email',      'label' => 'Email'],
                ['key' => 'employeeid', 'label' => 'Emp ID'],
                ['key' => 'coursename', 'label' => 'Course'],
                ['key' => 'coursecode', 'label' => 'Code'],
                ['key' => 'started',    'label' => 'Started'],
                ['key' => 'completed',  'label' => 'Completed'],
                ['key' => 'status',     'label' => 'Status'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Total Records', 'value' => count($rows)],
                ['label' => 'Completed',     'value' => $completed],
                ['label' => 'In Progress',   'value' => $in_progress],
            ],
        ];
    }

    /**
     * Compliance overview — completion rate per course (mandatory training focus).
     */
    private static function run_compliance_overview(string $org_path, array $config): array {
        global $DB;

        $where = ['c.id > 1', 'c.visible = 1'];
        $params = [];

        if (!empty($org_path)) {
            $where[] = "(c.open_path = :orgexact OR c.open_path LIKE :orgpath)";
            $params["orgexact"] = rtrim($org_path, "/");
            $params['orgpath'] = $DB->sql_like_escape(rtrim($org_path, '/') . '/') . '%';
        }

        $sql = "SELECT c.id, c.fullname, c.shortname,
                       COUNT(DISTINCT ue.userid) AS enrolled,
                       SUM(CASE WHEN cc.timecompleted IS NOT NULL THEN 1 ELSE 0 END) AS completed,
                       AVG(CASE WHEN cc.timecompleted IS NOT NULL THEN 1.0 ELSE 0.0 END) * 100 AS rate
                  FROM {course} c
             LEFT JOIN {enrol} e ON e.courseid = c.id
             LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id
             LEFT JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY c.id, c.fullname, c.shortname
                HAVING enrolled > 0
              ORDER BY rate ASC, c.fullname ASC
                 LIMIT 200";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        $total_rate_sum = 0;
        $count_with_data = 0;

        foreach ($records as $r) {
            $rate = $r->enrolled > 0 ? round((float) $r->rate, 1) : 0;
            $rows[] = [
                'coursename' => $r->fullname,
                'coursecode' => $r->shortname,
                'enrolled'   => (int) $r->enrolled,
                'completed'  => (int) $r->completed,
                'rate'       => $rate . '%',
                'rate_class' => $rate >= 80 ? 'text-success' : ($rate >= 50 ? 'text-warning' : 'text-danger'),
            ];
            if ($r->enrolled > 0) {
                $total_rate_sum += $rate;
                $count_with_data++;
            }
        }

        $avg_rate = $count_with_data > 0 ? round($total_rate_sum / $count_with_data, 1) : 0;

        return [
            'columns' => [
                ['key' => 'coursename', 'label' => 'Course'],
                ['key' => 'coursecode', 'label' => 'Code'],
                ['key' => 'enrolled',   'label' => 'Enrolled'],
                ['key' => 'completed',  'label' => 'Completed'],
                ['key' => 'rate',       'label' => 'Completion Rate'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Courses Tracked', 'value' => count($rows)],
                ['label' => 'Avg Completion Rate', 'value' => $avg_rate . '%'],
            ],
        ];
    }

    /**
     * User activity — login stats per user.
     */
    private static function run_user_activity(string $org_path, array $config): array {
        global $DB;

        $where = ['u.deleted = 0', 'u.suspended = 0', 'u.id > 2'];
        $params = [];

        if (!empty($org_path)) {
            $where[] = "(u.open_path = :orgexact OR u.open_path LIKE :orgpath)";
            $params["orgexact"] = rtrim($org_path, "/");
            $params['orgpath'] = $DB->sql_like_escape(rtrim($org_path, '/') . '/') . '%';
        }

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.open_employeeid,
                       u.open_designation, u.lastaccess, u.firstaccess
                  FROM {user} u
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY u.lastaccess DESC
                 LIMIT 500";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        $active_30d = 0;
        $never_logged = 0;
        $cutoff = time() - (30 * 86400);

        foreach ($records as $r) {
            $rows[] = [
                'fullname'    => trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')),
                'email'       => $r->email,
                'employeeid'  => $r->open_employeeid ?? '',
                'designation' => $r->open_designation ?? '—',
                'firstaccess' => $r->firstaccess ? userdate($r->firstaccess, '%d %b %Y') : 'Never',
                'lastaccess'  => $r->lastaccess ? userdate($r->lastaccess, '%d %b %Y, %H:%M') : 'Never',
                'status'      => $r->lastaccess && $r->lastaccess > $cutoff ? 'Active' : 'Inactive',
            ];
            if ($r->lastaccess && $r->lastaccess > $cutoff) $active_30d++;
            if (empty($r->lastaccess)) $never_logged++;
        }

        return [
            'columns' => [
                ['key' => 'fullname',    'label' => 'Name'],
                ['key' => 'email',       'label' => 'Email'],
                ['key' => 'employeeid',  'label' => 'Emp ID'],
                ['key' => 'designation', 'label' => 'Designation'],
                ['key' => 'firstaccess', 'label' => 'First Login'],
                ['key' => 'lastaccess',  'label' => 'Last Access'],
                ['key' => 'status',      'label' => 'Status'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Total Users',           'value' => count($rows)],
                ['label' => 'Active (last 30 days)', 'value' => $active_30d],
                ['label' => 'Never Logged In',       'value' => $never_logged],
            ],
        ];
    }

    /**
     * Enrolment trend — new enrolments grouped by month.
     */
    private static function run_enrolment_trend(string $org_path, array $config): array {
        global $DB;

        $where = ['ue.timestart > 0'];
        $params = [];

        if (!empty($org_path)) {
            $where[] = "(u.open_path = :orgexact OR u.open_path LIKE :orgpath)";
            $params["orgexact"] = rtrim($org_path, "/");
            $params['orgpath'] = $DB->sql_like_escape(rtrim($org_path, '/') . '/') . '%';
        }

        // Last 12 months.
        $cutoff = strtotime('-12 months');
        $where[] = "ue.timestart >= :cutoff";
        $params['cutoff'] = $cutoff;

        $sql = "SELECT FROM_UNIXTIME(ue.timestart, '%Y-%m') AS yyyymm,
                       COUNT(DISTINCT ue.id) AS enrolments,
                       COUNT(DISTINCT ue.userid) AS unique_users,
                       COUNT(DISTINCT e.courseid) AS courses
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY yyyymm
              ORDER BY yyyymm DESC
                 LIMIT 24";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        $total_enrolments = 0;

        foreach ($records as $r) {
            $rows[] = [
                'period'       => date('M Y', strtotime($r->yyyymm . '-01')),
                'enrolments'   => (int) $r->enrolments,
                'unique_users' => (int) $r->unique_users,
                'courses'      => (int) $r->courses,
            ];
            $total_enrolments += (int) $r->enrolments;
        }

        $avg_per_month = count($rows) > 0 ? round($total_enrolments / count($rows)) : 0;

        return [
            'columns' => [
                ['key' => 'period',       'label' => 'Month'],
                ['key' => 'enrolments',   'label' => 'New Enrolments'],
                ['key' => 'unique_users', 'label' => 'Unique Learners'],
                ['key' => 'courses',      'label' => 'Distinct Courses'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Months Tracked',      'value' => count($rows)],
                ['label' => 'Total Enrolments',    'value' => number_format($total_enrolments)],
                ['label' => 'Avg per Month',       'value' => $avg_per_month],
            ],
        ];
    }

    /**
     * Convert report rows to CSV string.
     */
    public static function rows_to_csv(array $report_data): string {
        $out = fopen('php://temp', 'r+');

        // Header row.
        $headers = array_map(fn($c) => $c['label'], $report_data['columns']);
        fputcsv($out, $headers);

        // Data rows.
        foreach ($report_data['rows'] as $row) {
            $line = [];
            foreach ($report_data['columns'] as $col) {
                $line[] = $row[$col['key']] ?? '';
            }
            fputcsv($out, $line);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    private static function is_valid_json(string $s): bool {
        json_decode($s, true);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
