<?php
/**
 * Compliance Engine — builds snapshot, determines status, handles auto-enrol + escalation.
 *
 * 6-state model: completed | in_progress | overdue | not_started | not_enrolled | exempted
 *
 * @package    local_airpay_compliance_report
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_compliance_report;

defined('MOODLE_INTERNAL') || die();

class compliance_engine {

    /** Status constants. */
    const STATUS_COMPLETED    = 'completed';
    const STATUS_IN_PROGRESS  = 'in_progress';
    const STATUS_OVERDUE      = 'overdue';
    const STATUS_NOT_STARTED  = 'not_started';
    const STATUS_NOT_ENROLLED = 'not_enrolled';
    const STATUS_EXEMPTED     = 'exempted';

    /**
     * Rebuild the entire compliance snapshot table.
     * Called by cron every hour.
     *
     * @return array {total: int, completed: int, overdue: int, auto_enrolled: int, emails_sent: int}
     */
    public static function rebuild_snapshot(): array {
        global $DB;

        $now = time();
        $stats = ['total' => 0, 'completed' => 0, 'overdue' => 0,
                  'auto_enrolled' => 0, 'emails_sent' => 0];

        // Get active mandatory courses.
        $mandatory = $DB->get_records('local_compliance_courses', ['is_active' => 1], 'sort_order');
        if (empty($mandatory)) {
            return $stats;
        }

        // Get all active employees.
        $users = $DB->get_records_select('user',
            'deleted = 0 AND suspended = 0 AND id > 1',
            [], '', 'id, username, email, firstname, lastname, open_path');

        // Get active exemptions.
        $exemptions = $DB->get_records_select('local_compliance_exemptions',
            'is_active = 1 AND (expiry_date IS NULL OR expiry_date > :now)',
            ['now' => $now]);
        $exempt_map = []; // userid_courseid => true
        foreach ($exemptions as $ex) {
            $exempt_map[$ex->userid . '_' . $ex->courseid] = true;
        }

        // Expire old exemptions.
        $DB->execute(
            "UPDATE {local_compliance_exemptions} SET is_active = 0
              WHERE is_active = 1 AND expiry_date IS NOT NULL AND expiry_date <= :now",
            ['now' => $now]);

        // Build snapshot for each user × course.
        foreach ($users as $user) {
            $parts = explode('/', trim($user->open_path ?? '', '/'));
            $costcenterid = (int)($parts[0] ?? 0);
            $dept_path = $user->open_path ?? '';

            foreach ($mandatory as $mc) {
                // Check tenant scope: 0 = all tenants, else specific costcenter.
                if ($mc->costcenterid > 0 && $mc->costcenterid != $costcenterid) {
                    continue; // This course doesn't apply to this tenant.
                }

                $stats['total']++;
                $key = $user->id . '_' . $mc->courseid;

                // Check exemption.
                if (isset($exempt_map[$key])) {
                    self::upsert_snapshot($user->id, $mc->courseid, $costcenterid, $dept_path,
                        self::STATUS_EXEMPTED, null, 0, null, null, 0, $now);
                    continue;
                }

                // Check enrolment.
                $enrol = $DB->get_record_sql(
                    "SELECT ue.timestart, ue.id
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE e.courseid = :cid AND ue.userid = :uid
                      LIMIT 1",
                    ['cid' => $mc->courseid, 'uid' => $user->id]);

                if (!$enrol) {
                    // NOT ENROLLED — auto-enrol if enabled.
                    $auto_enrol = get_config('local_airpay_compliance_report', 'auto_enrol');
                    if ($auto_enrol) {
                        $enrolled = self::auto_enrol($user->id, $mc->courseid, $mc->deadline_days);
                        if ($enrolled) {
                            $stats['auto_enrolled']++;
                            // Set as not_started (just enrolled).
                            $deadline = $now + ($mc->deadline_days * 86400);
                            self::upsert_snapshot($user->id, $mc->courseid, $costcenterid, $dept_path,
                                self::STATUS_NOT_STARTED, null, 0, $now, $deadline, 0, $now);

                            // Send welcome email.
                            self::send_escalation($user, $mc, 'welcome', 'employee', $stats);
                            continue;
                        }
                    }

                    self::upsert_snapshot($user->id, $mc->courseid, $costcenterid, $dept_path,
                        self::STATUS_NOT_ENROLLED, null, 0, null, null, 0, $now);
                    continue;
                }

                // Enrolled — check completion.
                $cc = $DB->get_record('course_completions', [
                    'userid' => $user->id,
                    'course' => $mc->courseid,
                ]);

                $enrol_date = $enrol->timestart ?: $now;
                $deadline = $enrol_date + ($mc->deadline_days * 86400);
                $progress = 0;

                // Get progress percentage.
                try {
                    $course_obj = get_course($mc->courseid);
                    $progress_val = \core_completion\progress::get_course_progress_percentage($course_obj, $user->id);
                    $progress = $progress_val !== null ? round($progress_val) : 0;
                } catch (\Exception $e) {
                    $progress = 0;
                }

                if ($cc && $cc->timecompleted) {
                    // COMPLETED.
                    $stats['completed']++;
                    self::upsert_snapshot($user->id, $mc->courseid, $costcenterid, $dept_path,
                        self::STATUS_COMPLETED, $cc->timecompleted, 100, $enrol_date, $deadline, 0, $now);
                } else if ($deadline < $now) {
                    // OVERDUE — deadline passed.
                    $days_overdue = round(($now - $deadline) / 86400);
                    $stats['overdue']++;
                    self::upsert_snapshot($user->id, $mc->courseid, $costcenterid, $dept_path,
                        self::STATUS_OVERDUE, null, $progress, $enrol_date, $deadline, $days_overdue, $now);

                    // Escalation emails for overdue.
                    self::check_escalation($user, $mc, $days_overdue, $stats);
                } else if ($progress > 0) {
                    // IN PROGRESS.
                    self::upsert_snapshot($user->id, $mc->courseid, $costcenterid, $dept_path,
                        self::STATUS_IN_PROGRESS, null, $progress, $enrol_date, $deadline, 0, $now);

                    // Reminder if 50%+ time elapsed and <50% progress.
                    $time_elapsed_pct = ($now - $enrol_date) / max(1, $deadline - $enrol_date) * 100;
                    if ($time_elapsed_pct >= 50 && $progress < 50) {
                        self::send_escalation($user, $mc, 'reminder2', 'employee', $stats);
                    }
                } else {
                    // NOT STARTED (enrolled but zero progress).
                    self::upsert_snapshot($user->id, $mc->courseid, $costcenterid, $dept_path,
                        self::STATUS_NOT_STARTED, null, 0, $enrol_date, $deadline, 0, $now);

                    // Reminder after 7 days of no activity.
                    $days_since_enrol = round(($now - $enrol_date) / 86400);
                    if ($days_since_enrol >= 7) {
                        self::send_escalation($user, $mc, 'reminder1', 'employee', $stats);
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Insert or update a snapshot record.
     */
    private static function upsert_snapshot(int $userid, int $courseid, int $costcenterid,
            string $dept_path, string $status, ?int $completion_date, int $progress,
            ?int $enrol_date, ?int $deadline, int $days_overdue, int $now): void {
        global $DB;

        $existing = $DB->get_record('local_compliance_snapshot', [
            'userid' => $userid, 'courseid' => $courseid]);

        $record = (object)[
            'userid'           => $userid,
            'courseid'         => $courseid,
            'costcenterid'     => $costcenterid,
            'department_path'  => $dept_path,
            'status'           => $status,
            'completion_date'  => $completion_date,
            'progress_percent' => $progress,
            'enrol_date'       => $enrol_date,
            'deadline_date'    => $deadline,
            'days_overdue'     => $days_overdue,
            'matched_by'       => 'userid',
            'snapshot_date'    => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_compliance_snapshot', $record);
        } else {
            $DB->insert_record('local_compliance_snapshot', $record);
        }
    }

    /**
     * Auto-enrol a user in a mandatory course.
     */
    private static function auto_enrol(int $userid, int $courseid, int $deadline_days): bool {
        global $DB;

        $enrolplugin = enrol_get_plugin('manual');
        if (!$enrolplugin) {
            return false;
        }

        $instance = $DB->get_record('enrol', [
            'courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MISSING);

        if (!$instance) {
            $enrolid = $enrolplugin->add_instance(get_course($courseid));
            $instance = $DB->get_record('enrol', ['id' => $enrolid]);
        }

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $now = time();

        try {
            $enrolplugin->enrol_user($instance, $userid, $roleid, $now,
                $now + ($deadline_days * 86400));
            return true;
        } catch (\Exception $e) {
            debugging('Compliance auto-enrol failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check and send escalation emails for overdue users.
     */
    private static function check_escalation(\stdClass $user, \stdClass $mc,
                                              int $days_overdue, array &$stats): void {
        // Deadline warning (7 days before — already past if overdue, but check for initial).
        if ($days_overdue <= 0) {
            self::send_escalation($user, $mc, 'deadline_warning', 'employee', $stats);
        }

        // Overdue alert (first time overdue).
        if ($days_overdue >= 1 && $days_overdue <= 3) {
            self::send_escalation($user, $mc, 'overdue_alert', 'employee', $stats);
            self::send_escalation($user, $mc, 'overdue_alert', 'manager', $stats);
        }

        // Weekly escalation (every 7 days while overdue).
        if ($days_overdue > 0 && ($days_overdue % 7 === 0)) {
            self::send_escalation($user, $mc, 'weekly_escalation', 'manager', $stats);
        }
    }

    /**
     * Send an escalation email (with dedup check).
     */
    private static function send_escalation(\stdClass $user, \stdClass $mc,
                                             string $type, string $recipient, array &$stats): void {
        global $DB;

        // Dedup: same user + course + type within 24 hours.
        $exists = $DB->record_exists_select('local_compliance_email_log',
            "userid = :uid AND courseid = :cid AND email_type = :type AND timecreated > :since",
            ['uid' => $user->id, 'cid' => $mc->courseid, 'type' => $type,
             'since' => time() - 86400]);

        if ($exists) {
            return;
        }

        // Log the email.
        $DB->insert_record('local_compliance_email_log', (object)[
            'userid'      => $user->id,
            'courseid'    => $mc->courseid,
            'email_type'  => $type,
            'sent_to'     => $recipient,
            'timecreated' => time(),
        ]);

        // Map compliance types to branded email templates.
        $templatemap = [
            'welcome'           => 'compliance/welcome_enrolled',
            'reminder1'         => 'compliance/reminder_start',
            'reminder2'         => 'compliance/reminder_halfway',
            'deadline_warning'  => 'compliance/deadline_warning',
            'overdue_alert'     => 'compliance/overdue_alert',
            'weekly_escalation' => 'compliance/weekly_escalation',
        ];
        $subjects = [
            'welcome'           => 'Enrolled: ' . $mc->coursename,
            'reminder1'         => 'Reminder: Start ' . $mc->coursename,
            'reminder2'         => 'Halfway to deadline: ' . $mc->coursename,
            'deadline_warning'  => 'Due in 7 days: ' . $mc->coursename,
            'overdue_alert'     => 'OVERDUE: ' . $mc->coursename,
            'weekly_escalation' => 'Weekly overdue: ' . $mc->coursename,
        ];

        $target_userid = $user->id;
        if ($recipient === 'manager') {
            $mgr_id = $DB->get_field('user', 'open_supervisorid', ['id' => $user->id]);
            if ($mgr_id) {
                $target_userid = $mgr_id;
            } else {
                return; // No manager to escalate to.
            }
        }

        // Build context for the branded template.
        $context = [
            'firstname'     => format_string($user->firstname),
            'lastname'      => format_string($user->lastname),
            'fullname'      => format_string($user->firstname . ' ' . $user->lastname),
            'course_name'   => format_string($mc->coursename),
            'course_url'    => (new \moodle_url('/course/view.php', ['id' => $mc->courseid]))->out(false),
            'deadline_date' => $mc->deadline_date ? userdate($mc->deadline_date, '%d %B %Y') : '',
            'deadline_days' => $mc->deadline_days ?? 30,
            'subject'       => $subjects[$type] ?? 'Compliance: ' . $mc->coursename,
        ];

        // Render branded HTML using email template system (if available).
        $html = '';
        $templatekey = $templatemap[$type] ?? '';
        if ($templatekey && class_exists('\\local_airpay_emails\\email_renderer')) {
            try {
                $html = \local_airpay_emails\email_renderer::render(
                    'local_airpay_emails/' . $templatekey, $context, $target_userid
                );
            } catch (\Exception $e) {
                debugging('Template render fallback: ' . $e->getMessage());
            }
        }
        // Fallback to plain text if template rendering fails.
        if (empty($html)) {
            $html = '<p>' . s($context['fullname']) . ' — ' . s($mc->coursename) . ' (' . s($type) . ')</p>';
        }

        $message = new \core\message\message();
        $message->component         = 'local_airpay_compliance_report';
        $message->name              = 'compliance_alert';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $target_userid;
        $message->subject           = $subjects[$type] ?? 'Compliance: ' . $mc->coursename;
        $message->fullmessage       = html_to_text($html);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml   = $html;
        $message->smallmessage      = $message->subject;
        $message->notification      = 1;
        $message->contexturl        = new \moodle_url('/course/view.php', ['id' => $mc->courseid]);
        $message->contexturlname    = $mc->coursename;

        try {
            message_send($message);
            $stats['emails_sent']++;
        } catch (\Exception $e) {
            debugging('Compliance email failed: ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════
    // REPORT QUERIES (read from snapshot table)
    // ════════════════════════════════════════════════════

    /**
     * Get the compliance matrix — one row per user, one column per mandatory course.
     * This is the core report matching your Python script output.
     */
    public static function get_compliance_matrix(string $orgpath = '', int $page = 0, int $perpage = 50): array {
        global $DB;

        $conditions = ['1=1'];
        $params = [];

        if (!empty($orgpath)) {
            $conditions[] = "s.department_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
        }

        $where = implode(' AND ', $conditions);

        // Get mandatory courses.
        $courses = $DB->get_records('local_compliance_courses', ['is_active' => 1], 'sort_order');

        // Get distinct users in snapshot.
        $total = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT s.userid) FROM {local_compliance_snapshot} s WHERE $where", $params);

        $user_ids = $DB->get_records_sql(
            "SELECT DISTINCT s.userid
               FROM {local_compliance_snapshot} s
               JOIN {user} u ON u.id = s.userid
              WHERE $where
           ORDER BY u.lastname, u.firstname",
            $params, $page * $perpage, $perpage);

        $rows = [];
        foreach ($user_ids as $uid_rec) {
            $user = $DB->get_record('user', ['id' => $uid_rec->userid],
                'id, firstname, lastname, email, open_path, open_designation, open_employeeid');

            $row = [
                'userid'       => $user->id,
                'employee_id'  => $user->open_employeeid ?? '',
                'email'        => $user->email,
                'fullname'     => format_string($user->firstname . ' ' . $user->lastname),
                'designation'  => format_string($user->open_designation ?? ''),
                'department'   => $user->open_path ?? '',
                'courses'      => [],
                'all_completed' => true,
            ];

            foreach ($courses as $mc) {
                $snap = $DB->get_record('local_compliance_snapshot', [
                    'userid' => $user->id, 'courseid' => $mc->courseid]);

                $status = $snap ? $snap->status : 'not_enrolled';
                $row['courses'][] = [
                    'coursename' => $mc->coursename,
                    'courseid'   => $mc->courseid,
                    'status'     => $status,
                    'status_label' => self::status_label($status),
                    'status_class' => self::status_class($status),
                    'progress'   => $snap->progress_percent ?? 0,
                    'days_overdue' => $snap->days_overdue ?? 0,
                    'deadline'   => $snap->deadline_date ? userdate($snap->deadline_date, '%d %b %Y') : '',
                ];

                if ($status !== self::STATUS_COMPLETED && $status !== self::STATUS_EXEMPTED) {
                    $row['all_completed'] = false;
                }
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'total' => $total, 'courses' => array_values($courses)];
    }

    /**
     * Get department compliance scorecard.
     */
    public static function get_department_scorecard(string $orgpath = ''): array {
        global $DB;

        $orgfilter = '';
        $params = [];
        if (!empty($orgpath)) {
            $orgfilter = "AND s.department_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
        }

        // Get departments at depth 3 (or top-level if no specific org).
        $parts = explode('/', trim($orgpath ?: '/1', '/'));
        $toporg = '/' . ($parts[0] ?? '1');

        $departments = $DB->get_records_sql(
            "SELECT DISTINCT cc.id, cc.fullname, cc.path
               FROM {local_costcenter} cc
              WHERE cc.path LIKE :pathprefix AND cc.depth IN (2, 3)
           ORDER BY cc.fullname",
            ['pathprefix' => $toporg . '/%']);

        $results = [];
        foreach ($departments as $dept) {
            $total = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {local_compliance_snapshot}
                  WHERE department_path LIKE :path",
                ['path' => $dept->path . '%']);

            $completed = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {local_compliance_snapshot}
                  WHERE department_path LIKE :path AND status = 'completed'",
                ['path' => $dept->path . '%']);

            $overdue = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {local_compliance_snapshot}
                  WHERE department_path LIKE :path AND status = 'overdue'",
                ['path' => $dept->path . '%']);

            $rate = $total > 0 ? round(($completed / $total) * 100) : 0;
            $rag = ($rate >= 90) ? 'green' : (($rate >= 70) ? 'amber' : 'red');

            $results[] = [
                'department'  => format_string($dept->fullname),
                'total'       => $total,
                'completed'   => $completed,
                'overdue'     => $overdue,
                'rate'        => $rate,
                'rag'         => $rag,
                'is_green'    => ($rag === 'green'),
                'is_amber'    => ($rag === 'amber'),
                'is_red'      => ($rag === 'red'),
            ];
        }

        return $results;
    }

    /**
     * Get defaulters list — employees overdue on any mandatory course.
     */
    public static function get_defaulters(string $orgpath = '', int $limit = 100): array {
        global $DB;

        $orgfilter = '';
        $params = [];
        if (!empty($orgpath)) {
            $orgfilter = "AND s.department_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
        }

        return array_values($DB->get_records_sql(
            "SELECT s.userid, u.firstname, u.lastname, u.email, u.open_designation,
                    s.courseid, cc.coursename, s.days_overdue, s.progress_percent,
                    s.deadline_date, s.department_path
               FROM {local_compliance_snapshot} s
               JOIN {user} u ON u.id = s.userid
               JOIN {local_compliance_courses} cc ON cc.courseid = s.courseid
              WHERE s.status = 'overdue' $orgfilter
           ORDER BY s.days_overdue DESC",
            $params, 0, $limit));
    }

    /**
     * Get compliance summary KPIs.
     */
    public static function get_summary_kpis(string $orgpath = ''): array {
        global $DB;

        $orgfilter = '';
        $params = [];
        if (!empty($orgpath)) {
            $orgfilter = "AND department_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
        }

        $total = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_compliance_snapshot} WHERE 1=1 $orgfilter", $params);
        $completed = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_compliance_snapshot} WHERE status = 'completed' $orgfilter", $params);
        $overdue = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_compliance_snapshot} WHERE status = 'overdue' $orgfilter", $params);
        $not_enrolled = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_compliance_snapshot} WHERE status = 'not_enrolled' $orgfilter", $params);
        $exempted = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_compliance_snapshot} WHERE status = 'exempted' $orgfilter", $params);

        $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        return [
            'total'          => $total,
            'completed'      => $completed,
            'overdue'        => $overdue,
            'not_enrolled'   => $not_enrolled,
            'exempted'       => $exempted,
            'in_progress'    => $total - $completed - $overdue - $not_enrolled - $exempted,
            'compliance_rate' => $rate,
            'is_healthy'     => ($rate >= 80),
        ];
    }

    /**
     * Get manager compliance report — per manager with team stats.
     */
    public static function get_manager_report(string $orgpath = ''): array {
        global $DB;

        $orgfilter = '';
        $params = [];
        if (!empty($orgpath)) {
            $orgfilter = "AND u.open_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
        }

        return array_values($DB->get_records_sql(
            "SELECT u.open_supervisorid as managerid,
                    mgr.firstname as mgr_firstname, mgr.lastname as mgr_lastname,
                    COUNT(DISTINCT s.userid) as team_items,
                    SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as team_completed,
                    SUM(CASE WHEN s.status = 'overdue' THEN 1 ELSE 0 END) as team_overdue,
                    ROUND(SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) * 100.0 /
                          NULLIF(COUNT(*), 0)) as team_rate
               FROM {local_compliance_snapshot} s
               JOIN {user} u ON u.id = s.userid
               JOIN {user} mgr ON mgr.id = u.open_supervisorid
              WHERE u.open_supervisorid > 0 AND mgr.deleted = 0 $orgfilter
           GROUP BY u.open_supervisorid, mgr.firstname, mgr.lastname
             HAVING team_items > 0
           ORDER BY team_overdue DESC, team_rate ASC",
            $params));
    }

    /** Status label for display. */
    public static function status_label(string $status): string {
        $labels = [
            'completed'    => 'Completed',
            'in_progress'  => 'In Progress',
            'overdue'      => 'Overdue',
            'not_started'  => 'Not Started',
            'not_enrolled' => 'Not Enrolled',
            'exempted'     => 'Exempted',
        ];
        return $labels[$status] ?? ucfirst($status);
    }

    /** CSS class for status badge. */
    public static function status_class(string $status): string {
        $classes = [
            'completed'    => 'success',
            'in_progress'  => 'warning',
            'overdue'      => 'danger',
            'not_started'  => 'danger',
            'not_enrolled' => 'dark',
            'exempted'     => 'secondary',
        ];
        return $classes[$status] ?? 'secondary';
    }

    // ════════════════════════════════════════════════════
    // ORG HIERARCHY FILTERS
    // ════════════════════════════════════════════════════

    /**
     * Get org units at a given hierarchy level.
     *
     * BizLMS open_path: /BU/Dept/SubDept/...
     * Level 1 = Business Unit, Level 2 = Department, Level 3 = Sub-Department.
     *
     * @param int $level 1, 2, or 3
     * @param string $parentpath filter to children of this path
     * @return array [{id, name, user_count}]
     */
    public static function get_org_hierarchy_level(int $level, string $parentpath = ''): array {
        global $DB;

        // Level 1: top-level costcenters (BU).
        if ($level === 1) {
            $sql = "SELECT DISTINCT
                        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(u.open_path, '/', 2), '/', -1) AS UNSIGNED) AS id,
                        COUNT(DISTINCT u.id) AS user_count
                      FROM {user} u
                     WHERE u.deleted = 0 AND u.suspended = 0
                       AND u.open_path IS NOT NULL AND u.open_path != ''";
            $params = [];

            if (!empty($parentpath)) {
                $sql .= " AND u.open_path LIKE :ppath";
                $params['ppath'] = $parentpath . '%';
            }

            $sql .= " GROUP BY id HAVING id > 0 ORDER BY user_count DESC";
            $records = $DB->get_records_sql($sql, $params);
        } else {
            return []; // Use get_org_hierarchy_children for deeper levels.
        }

        // Resolve names from local_costcenter.
        $result = [];
        foreach ($records as $r) {
            $name = $DB->get_field('local_costcenter', 'fullname', ['id' => $r->id]);
            if ($name) {
                $result[] = [
                    'id'         => (int)$r->id,
                    'name'       => format_string($name),
                    'user_count' => (int)$r->user_count,
                    'selected'   => false,
                ];
            }
        }
        return $result;
    }

    /**
     * Get child costcenters of a given parent.
     *
     * @param int $parentid costcenter ID
     * @return array [{id, name, user_count}]
     */
    public static function get_org_hierarchy_children(int $parentid): array {
        global $DB;

        $children = $DB->get_records('local_costcenter', ['parentid' => $parentid], 'fullname');
        $result = [];
        foreach ($children as $c) {
            $usercount = $DB->count_records_select('user',
                "deleted = 0 AND suspended = 0 AND open_path LIKE :path",
                ['path' => '%/' . $c->id . '/%']);
            $result[] = [
                'id'         => (int)$c->id,
                'name'       => format_string($c->fullname),
                'user_count' => $usercount,
                'selected'   => false,
            ];
        }
        return $result;
    }

    // ════════════════════════════════════════════════════
    // COURSE CONFIGURATION (per-entity)
    // ════════════════════════════════════════════════════

    /**
     * Add a course to compliance tracking.
     *
     * @param int $courseid
     * @param int $entityid costcenter ID (0 = all entities)
     * @param int $deadlinedays
     */
    public static function add_compliance_course(int $courseid, int $entityid = 0, int $deadlinedays = 30): void {
        global $DB, $USER;

        // Check for duplicate.
        if ($DB->record_exists('local_compliance_courses', ['courseid' => $courseid, 'costcenterid' => $entityid])) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);
        $maxsort = $DB->get_field_sql("SELECT MAX(sort_order) FROM {local_compliance_courses}") ?? 0;

        $DB->insert_record('local_compliance_courses', (object)[
            'courseid'      => $courseid,
            'coursename'    => $course->fullname,
            'deadline_days' => $deadlinedays,
            'costcenterid'  => $entityid,
            'is_active'     => 1,
            'sort_order'    => $maxsort + 1,
            'createdby'     => $USER->id,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);
    }

    /**
     * Remove (deactivate) a course from compliance tracking.
     */
    public static function remove_compliance_course(int $id): void {
        global $DB;
        $DB->set_field('local_compliance_courses', 'is_active', 0, ['id' => $id]);
        $DB->set_field('local_compliance_courses', 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Get all managed compliance courses (active + inactive).
     *
     * @return array [{id, courseid, coursename, deadline_days, costcenterid, entity_name, is_active}]
     */
    public static function get_managed_courses(): array {
        global $DB;
        $courses = $DB->get_records('local_compliance_courses', null, 'is_active DESC, sort_order');
        $result = [];
        foreach ($courses as $c) {
            $entityname = 'All Entities';
            if ($c->costcenterid > 0) {
                $entityname = $DB->get_field('local_costcenter', 'fullname', ['id' => $c->costcenterid]) ?? 'Unknown';
            }
            $result[] = [
                'id'            => $c->id,
                'courseid'      => $c->courseid,
                'coursename'    => format_string($c->coursename),
                'deadline_days' => $c->deadline_days,
                'costcenterid'  => $c->costcenterid,
                'entity_name'   => format_string($entityname),
                'is_active'     => (bool)$c->is_active,
            ];
        }
        return $result;
    }

    // ════════════════════════════════════════════════════
    // USER EXCLUSION
    // ════════════════════════════════════════════════════

    /**
     * Exclude a user from compliance tracking.
     * Uses the exemption table with courseid=0 to mark global exclusion.
     */
    public static function exclude_user(int $userid, string $reason = ''): void {
        global $DB, $USER;

        if ($DB->record_exists('local_compliance_exemptions', ['userid' => $userid, 'courseid' => 0, 'is_active' => 1])) {
            return;
        }

        $DB->insert_record('local_compliance_exemptions', (object)[
            'userid'      => $userid,
            'courseid'    => 0, // 0 = excluded from ALL compliance tracking.
            'reason'      => $reason,
            'approved_by' => $USER->id,
            'is_active'   => 1,
            'timecreated' => time(),
        ]);
    }

    /**
     * Re-include a user in compliance tracking.
     */
    public static function include_user(int $userid): void {
        global $DB;
        $DB->set_field('local_compliance_exemptions', 'is_active', 0,
            ['userid' => $userid, 'courseid' => 0]);
    }

    /**
     * Get all globally excluded users.
     *
     * @return array [{userid, fullname, email, reason, excluded_date}]
     */
    public static function get_excluded_users(): array {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT e.id, e.userid, u.firstname, u.lastname, u.email, e.reason, e.timecreated
               FROM {local_compliance_exemptions} e
               JOIN {user} u ON u.id = e.userid
              WHERE e.courseid = 0 AND e.is_active = 1
           ORDER BY e.timecreated DESC"
        );
        $result = [];
        foreach ($records as $r) {
            $result[] = [
                'id'            => $r->id,
                'userid'        => $r->userid,
                'fullname'      => format_string($r->firstname . ' ' . $r->lastname),
                'email'         => s($r->email),
                'reason'        => s($r->reason),
                'excluded_date' => userdate($r->timecreated, '%d %b %Y'),
            ];
        }
        return $result;
    }
}
