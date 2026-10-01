<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_reports;

defined('MOODLE_INTERNAL') || die();

/**
 * Report manager — CRUD for saved reports + built-in query runners.
 *
 * Each report type has a corresponding `run_*()` method that returns
 * structured rows. UI/templates call run_report($id) and render rows.
 *
 * @package    local_sentientia_reports
 */
class report_manager {

    private const TABLE = 'local_sentientia_reports';

    public const STATUS_ARCHIVED = 0;
    public const STATUS_ACTIVE   = 1;

    /** Built-in report types and their human labels. */
    public const REPORT_TYPES = [
        'course_completion'   => 'Course Completion — by user, course, and tenant',
        'compliance_overview' => 'Compliance Overview — mandatory training summary',
        'user_activity'       => 'User Activity — login + access stats',
        'enrolment_trend'     => 'Enrolment Trend — new enrolments over time',
    ];

    /** ADR-032 (users): the earlier-training-records report type exists only while this flag is ON (default OFF). */
    public const FLAG_TRAINING_TRANSCRIPT = 'sentientia.reports.training_transcript';

    /** ADR-032 (users): the imported-login-days column of the User Activity report, while this flag is ON (default OFF). */
    public const FLAG_LOGIN_DAYS = 'sentientia.reports.login_days';

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

    /**
     * The report types a report can be created or edited with: the built-in ones, plus the earlier-training-records
     * report while its flag is ON (ADR-032, users). REPORT_TYPES stays the four that have always existed, so a
     * flag that is OFF changes nothing in the form or in validation.
     *
     * @return array<string, string> type => label
     */
    public static function report_types(): array {
        $types = self::REPORT_TYPES;
        if (\local_sentientia_platform\feature_flags::is_enabled(self::FLAG_TRAINING_TRANSCRIPT)) {
            $types['training_transcript'] = get_string('report_type_training_transcript', 'local_sentientia_reports');
        }
        return $types;
    }

    /**
     * ADR-031: may the current user run, export, edit, archive or delete
     * this saved report?
     *
     * :view / :export / :manage say WHAT a user may do; only
     * tenant::is_cross_tenant() lets them reach another tenant's report.
     * A report with no open_path is an "All organisations" report - it
     * returns every tenant's users - so it is cross-tenant only. The empty
     * check must come first: require_path_access('') lets anyone through.
     *
     * @param \stdClass $report a local_sentientia_reports row
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_report_access(\stdClass $report): void {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return;
        }
        if (trim((string) ($report->open_path ?? '')) === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        \local_sentientia_platform\tenant::require_path_access((string) $report->open_path);
    }

    /**
     * ADR-031: the org a report is being saved against, checked against the
     * caller's tenant. Cross-tenant callers may use any org, or 0 ("All
     * organisations"); everyone else must name an existing org inside their
     * own tenant.
     *
     * @param int $orgid local_sentientia_org.id (0 = all organisations)
     * @return \stdClass|null the org row, or null for "all organisations"
     * @throws \moodle_exception error_outoftenant
     */
    private static function org_for_save(int $orgid): ?\stdClass {
        global $DB;
        $org = $orgid > 0 ? $DB->get_record('local_sentientia_org', ['id' => $orgid]) : false;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return $org ?: null;
        }
        if (!$org || trim((string) $org->path) === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        \local_sentientia_platform\tenant::require_path_access((string) $org->path);
        return $org;
    }

    /**
     * ADR-031: WHERE fragment for the saved reports the current user may
     * see (1=1 cross-tenant, 1=0 with no tenant; "All organisations"
     * reports are cross-tenant only).
     *
     * @param string $alias table alias ('' for none)
     * @return array{0: string, 1: array}
     */
    public static function visible_sql(string $alias = ''): array {
        return \local_sentientia_platform\tenant::path_filter($alias);
    }

    public static function count_reports(?int $status = null): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::TABLE)) return 0;
        // ADR-031: the index KPI tiles count the caller's tenant's reports.
        [$where, $params] = self::visible_sql();
        if ($status !== null) {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }
        return $DB->count_records_select(self::TABLE, $where, $params);
    }

    /** Total runs across the reports the current user may see. */
    public static function total_runs(): int {
        global $DB;
        [$where, $params] = self::visible_sql();
        return (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(runcount), 0) FROM {" . self::TABLE . "} WHERE $where", $params);
    }

    /**
     * Create a saved report definition.
     */
    public static function create(object $data): int {
        global $DB, $USER;

        if (empty($data->name) || empty($data->report_type)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_reports');
        }

        if (!array_key_exists($data->report_type, self::report_types())) {
            throw new \moodle_exception('invalidreporttype', 'local_sentientia_reports');
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

        // ADR-031: a scoped caller must save against an org in their own
        // tenant; "All organisations" (0, no open_path) is cross-tenant only.
        $org = self::org_for_save($record->costcenterid);
        if ($org) {
            $record->open_path = $org->path;
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        // ADR-031: the report being edited must be in the caller's tenant.
        self::require_report_access($existing);
        $record = (object) ['id' => $id, 'timemodified' => time()];

        if (isset($data->name))         $record->name = trim($data->name);
        if (isset($data->description))  $record->description = $data->description;
        if (isset($data->report_type)) {
            if (!array_key_exists($data->report_type, self::report_types())) {
                throw new \moodle_exception('invalidreporttype', 'local_sentientia_reports');
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
            // ADR-031: re-scoping it must stay inside the caller's tenant.
            $org = self::org_for_save($record->costcenterid);
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
        // ADR-031: run.php / export.php ran ANY report id - including the
        // "All organisations" ones, whose empty scope means every tenant's
        // users. Guard here so no entry point can skip it.
        self::require_report_access($report);

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
            'training_transcript' => self::run_training_transcript($org_path, $config),
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

        // ADR-032 (users): days with a web login, from the history the BizLMS import copied. Default OFF
        // (sentientia.reports.login_days). It is a COUNT OF IMPORTED DAYS, all of them: nothing in Sentientia
        // writes the table after cutover, so a 'last 90 days' window would empty out within three months.
        $logindays = null;
        if ($records && self::login_days_enabled()) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED, 'ldu');
            $logindays = $DB->get_records_sql_menu(
                "SELECT userid, COUNT(1) FROM {local_sentientia_users_logindays} WHERE userid $insql GROUP BY userid",
                $inparams);
        }

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
            if ($logindays !== null) {
                $rows[count($rows) - 1]['logindays'] = (int) ($logindays[$r->id] ?? 0);
            }
            if ($r->lastaccess && $r->lastaccess > $cutoff) $active_30d++;
            if (empty($r->lastaccess)) $never_logged++;
        }

        $result = [
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
        if ($logindays !== null) {
            $result['columns'][] = ['key' => 'logindays', 'label' => get_string('report_col_logindays', 'local_sentientia_reports')];
        }
        return $result;
    }

    /**
     * Is the imported-login-days column on, and is there a table to read it from?
     *
     * @return bool
     */
    private static function login_days_enabled(): bool {
        global $DB;
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG_LOGIN_DAYS)
            && $DB->get_manager()->table_exists('local_sentientia_users_logindays');
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
     * Earlier training records - the BizLMS transcript history the users import copied into
     * local_sentientia_users_transcript (ADR-032, users). Default OFF (sentientia.reports.training_transcript).
     *
     * History only: the rows are never added to a completion total anywhere. The scope is the learner's CURRENT
     * org path, like the other runners, through the shared descendant filter; a row with no matched learner
     * (an off-platform record) has no path and shows only on an "All organisations" report, which only a
     * cross-tenant caller can run (require_report_access). A matched learner whose account was deleted is left out.
     *
     * @param string $org_path report scope; '' = all organisations
     * @param array $config saved filter config (unused)
     * @return array{columns: array, rows: array, summary: array}
     */
    private static function run_training_transcript(string $org_path, array $config): array {
        global $DB;

        $columns = [
            ['key' => 'fullname',   'label' => get_string('report_col_name', 'local_sentientia_reports')],
            ['key' => 'employeeid', 'label' => get_string('report_col_empid', 'local_sentientia_reports')],
            ['key' => 'title',      'label' => get_string('report_col_training', 'local_sentientia_reports')],
            ['key' => 'type',       'label' => get_string('report_col_type', 'local_sentientia_reports')],
            ['key' => 'completed',  'label' => get_string('report_col_completed', 'local_sentientia_reports')],
            ['key' => 'status',     'label' => get_string('report_col_status', 'local_sentientia_reports')],
            ['key' => 'statusraw',  'label' => get_string('report_col_statusraw', 'local_sentientia_reports')],
            ['key' => 'score',      'label' => get_string('report_col_score', 'local_sentientia_reports')],
            ['key' => 'hours',      'label' => get_string('report_col_hours', 'local_sentientia_reports')],
        ];
        if (!\local_sentientia_platform\feature_flags::is_enabled(self::FLAG_TRAINING_TRANSCRIPT)
                || !$DB->get_manager()->table_exists('local_sentientia_users_transcript')) {
            return ['columns' => $columns, 'rows' => [], 'summary' => []];
        }

        $where = ['(u.id IS NULL OR u.deleted = 0)'];
        $params = [];
        if (!empty($org_path)) {
            [$scopesql, $scopeparams] = \local_sentientia_platform\tenant::path_descendant_filter(
                $org_path, 'u', 'open_path', 'rtorg');
            $where[] = $scopesql;
            $params += $scopeparams;
        }

        $unamefields = implode(', ', array_map(
            fn($f) => 'u.' . $f,
            \core_user\fields::get_name_fields()
        ));
        $records = $DB->get_records_sql(
            "SELECT t.id, t.employee_id, t.learner_name, t.title, t.training_type, t.status, t.status_raw,
                    t.timecompleted, t.completion_date_raw, t.score, t.hours,
                    u.id AS matcheduser, $unamefields
               FROM {local_sentientia_users_transcript} t
          LEFT JOIN {user} u ON u.id = t.userid
              WHERE " . implode(' AND ', $where) . "
           ORDER BY CASE WHEN t.timecompleted IS NULL THEN 1 ELSE 0 END, t.timecompleted DESC, t.id DESC",
            $params, 0, 500);

        $statuses = ['completed', 'inprogress', 'failed', 'notstarted', 'cancelled', 'unknown'];
        $rows = [];
        $completed = 0;
        $hours = 0.0;
        foreach ($records as $r) {
            $status = in_array($r->status, $statuses, true) ? $r->status : 'unknown';
            $rows[] = [
                'fullname'   => !empty($r->matcheduser) ? fullname($r) : $r->learner_name,
                'employeeid' => $r->employee_id,
                'title'      => $r->title,
                'type'       => $r->training_type,
                'completed'  => $r->timecompleted !== null
                    ? userdate((int) $r->timecompleted, '%d %b %Y')
                    : ($r->completion_date_raw !== '' ? $r->completion_date_raw : '—'),
                'status'     => get_string('report_status_' . $status, 'local_sentientia_reports'),
                'statusraw'  => $r->status_raw,
                'score'      => $r->score !== null ? format_float((float) $r->score, 2) : '',
                'hours'      => $r->hours !== null ? format_float((float) $r->hours, 2) : '',
            ];
            if ($status === 'completed') {
                $completed++;
            }
            $hours += (float) $r->hours;
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'summary' => [
                ['label' => get_string('report_sum_records', 'local_sentientia_reports'),   'value' => count($rows)],
                ['label' => get_string('report_sum_completed', 'local_sentientia_reports'), 'value' => $completed],
                ['label' => get_string('report_sum_hours', 'local_sentientia_reports'),     'value' => format_float($hours, 2)],
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
