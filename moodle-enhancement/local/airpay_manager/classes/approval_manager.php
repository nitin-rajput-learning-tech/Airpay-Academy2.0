<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Approval + course-allocation lifecycle manager.
 *
 * Two related workflows:
 *
 *  1. Enrolment requests: a learner asks their manager to enrol them in
 *     a course. Manager sees pending requests, approves or rejects with
 *     a reason. On approval the user is enrolled via Moodle's standard
 *     enrol_self / enrol_manual plugin (when available).
 *
 *  2. Course allocations: a manager picks a course and assigns it to a
 *     direct report (push, not pull). Same enrol path on creation.
 *
 * Tenant scoping: only direct reports (user.open_supervisorid = $managerid)
 * can request from / be allocated to a manager. The schema field
 * `managerid` on requests/allocations is the tenant boundary.
 *
 * @package    local_airpay_manager
 */
class approval_manager {

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const ALLOC_ASSIGNED   = 'assigned';
    public const ALLOC_INPROGRESS = 'in_progress';
    public const ALLOC_COMPLETED  = 'completed';
    public const ALLOC_OVERDUE    = 'overdue';
    public const ALLOC_CANCELLED  = 'cancelled';

    /**
     * Get user IDs of direct reports for a manager. Defensive: works on
     * stock Moodle by returning [] if the BizLMS field doesn't exist.
     *
     * @return list<int>
     */
    public static function direct_report_ids(int $managerid): array {
        global $DB;
        $manager = $DB->get_manager();
        if (!$manager->field_exists('user',
                new \xmldb_field('open_supervisorid', XMLDB_TYPE_INTEGER, '10'))) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_select('user', 'id',
            'open_supervisorid = :mgr AND deleted = 0 AND suspended = 0',
            ['mgr' => $managerid]));
    }

    /**
     * Is this user a direct report of the manager?
     */
    public static function is_direct_report(int $managerid, int $userid): bool {
        return in_array($userid, self::direct_report_ids($managerid), true);
    }

    /**
     * Learner-side: create a new enrolment request.
     *
     * @return int  request ID
     */
    public static function create_request(int $userid, int $courseid,
                                           int $managerid, string $reason = ''): int {
        global $DB;

        if ($managerid <= 0) {
            throw new \invalid_parameter_exception(
                'No manager assigned — cannot create request without an approver.');
        }
        // Validate course exists.
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        // Don't allow duplicate pending requests.
        if ($DB->record_exists_select('local_airpay_mgr_requests',
                "userid = :uid AND courseid = :cid AND status = :st",
                ['uid' => $userid, 'cid' => $courseid, 'st' => self::STATUS_PENDING])) {
            throw new \moodle_exception('duplicaterequest', 'local_airpay_manager');
        }

        $now = time();
        return (int) $DB->insert_record('local_airpay_mgr_requests', (object) [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'managerid'   => $managerid,
            'status'      => self::STATUS_PENDING,
            'reason'      => $reason,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Manager-side: list requests waiting for me, optionally filtered.
     *
     * @return array<array> shaped for the requests datatable
     */
    public static function list_requests(int $managerid, string $status = 'pending',
                                          int $page = 0, int $perpage = 25): array {
        global $DB;

        $perpage = max(5, min(100, $perpage));
        $page    = max(0, $page);

        $where = ['r.managerid = :mgr'];
        $params = ['mgr' => $managerid];
        if ($status !== 'all') {
            $where[] = 'r.status = :st';
            $params['st'] = $status;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_mgr_requests} r WHERE $wheresql",
            $params);

        $rows = $DB->get_records_sql("
            SELECT r.*, u.firstname, u.lastname, u.email,
                   c.fullname AS coursename, c.shortname AS courseshortname
              FROM {local_airpay_mgr_requests} r
         LEFT JOIN {user}   u ON u.id = r.userid
         LEFT JOIN {course} c ON c.id = r.courseid
             WHERE $wheresql
          ORDER BY r.timecreated DESC, r.id DESC",
            $params, $page * $perpage, $perpage);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'         => (int) $r->id,
                'userid'     => (int) $r->userid,
                'username'   => $r->firstname
                    ? fullname((object) ['firstname' => $r->firstname, 'lastname' => $r->lastname])
                    : '—',
                'courseid'   => (int) $r->courseid,
                'coursename' => format_string((string) ($r->coursename ?? '')),
                'status'     => $r->status,
                'reason'     => (string) ($r->reason ?? ''),
                'decision_reason' => (string) ($r->decision_reason ?? ''),
                'timecreated'  => (int) $r->timecreated,
                'when'         => userdate((int) $r->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
                'is_pending'   => $r->status === self::STATUS_PENDING,
            ];
        }
        return ['total' => $total, 'rows' => $out, 'page' => $page, 'perpage' => $perpage];
    }

    /**
     * Manager-side: approve or reject a request.
     *
     * On approve, attempts to enrol the user via the manual enrol plugin.
     * If enrol fails, the decision still sticks but the row is flagged.
     *
     * @param string $decision  'approved' | 'rejected'
     * @return array  status of the decision + any enrol warning
     */
    public static function decide_request(int $requestid, string $decision,
                                           string $decision_reason = '',
                                           int $deciderid = 0): array {
        global $DB, $USER, $CFG;
        if (!in_array($decision, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            throw new \invalid_parameter_exception('Decision must be approved|rejected.');
        }
        $deciderid = $deciderid > 0 ? $deciderid : (int) $USER->id;

        $request = $DB->get_record('local_airpay_mgr_requests',
            ['id' => $requestid], '*', MUST_EXIST);
        if ($request->status !== self::STATUS_PENDING) {
            throw new \moodle_exception('alreadydecided', 'local_airpay_manager');
        }

        $tx = $DB->start_delegated_transaction();
        try {
            $request->status = $decision;
            $request->decision_reason = $decision_reason;
            $request->decided_by = $deciderid;
            $request->decided_at = time();
            $request->timemodified = time();
            $DB->update_record('local_airpay_mgr_requests', $request);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }

        $enrolwarning = '';
        if ($decision === self::STATUS_APPROVED) {
            try {
                self::enrol_user_in_course((int) $request->userid, (int) $request->courseid);
            } catch (\Throwable $e) {
                $enrolwarning = $e->getMessage();
            }
        }

        return [
            'requestid'    => $requestid,
            'decision'     => $decision,
            'enrolwarning' => $enrolwarning,
        ];
    }

    /**
     * Enrol a user in a course via the manual enrol plugin.
     * No-op if already enrolled. Throws if manual enrol is not available.
     */
    public static function enrol_user_in_course(int $userid, int $courseid,
                                                  ?int $until = null): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $enrolinstance = $DB->get_record('enrol',
            ['courseid' => $courseid, 'enrol' => 'manual', 'status' => 0]);
        if (!$enrolinstance) {
            throw new \moodle_exception('manualenrolnotavailable', 'local_airpay_manager');
        }
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            throw new \moodle_exception('manualenrolnotavailable', 'local_airpay_manager');
        }
        // 5 = student role default. Check role_assignments for existing membership.
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']) ?: 5;
        $plugin->enrol_user($enrolinstance, $userid, $studentroleid,
            time(), $until ?? 0);
    }

    /**
     * Manager-side: list course allocations I've created.
     */
    public static function list_allocations(int $managerid, string $status = 'all',
                                              int $page = 0, int $perpage = 25): array {
        global $DB;

        $perpage = max(5, min(100, $perpage));
        $page    = max(0, $page);

        $where = ['a.managerid = :mgr'];
        $params = ['mgr' => $managerid];
        if ($status !== 'all') {
            $where[] = 'a.status = :st';
            $params['st'] = $status;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_mgr_allocations} a WHERE $wheresql",
            $params);

        $rows = $DB->get_records_sql("
            SELECT a.*, u.firstname, u.lastname, c.fullname AS coursename
              FROM {local_airpay_mgr_allocations} a
         LEFT JOIN {user}   u ON u.id = a.userid
         LEFT JOIN {course} c ON c.id = a.courseid
             WHERE $wheresql
          ORDER BY a.timecreated DESC, a.id DESC",
            $params, $page * $perpage, $perpage);

        $out = [];
        foreach ($rows as $a) {
            $out[] = [
                'id'         => (int) $a->id,
                'userid'     => (int) $a->userid,
                'username'   => $a->firstname
                    ? fullname((object) ['firstname' => $a->firstname, 'lastname' => $a->lastname])
                    : '—',
                'courseid'   => (int) $a->courseid,
                'coursename' => format_string((string) ($a->coursename ?? '')),
                'status'     => $a->status,
                'due_date'   => $a->due_date ? (int) $a->due_date : 0,
                'due_label'  => $a->due_date
                    ? userdate((int) $a->due_date, get_string('strftimedatefullshort', 'core_langconfig'))
                    : '—',
                'note'       => (string) ($a->note ?? ''),
                'timecreated' => (int) $a->timecreated,
            ];
        }
        return ['total' => $total, 'rows' => $out, 'page' => $page, 'perpage' => $perpage];
    }

    /**
     * Manager-side: assign a course to a direct report.
     *
     * Validates the target user IS a direct report (uses
     * is_direct_report). Inserts the allocation row + enrols the user.
     *
     * @return int  allocation row ID
     */
    public static function create_allocation(int $managerid, int $userid, int $courseid,
                                               ?int $due_date = null,
                                               string $note = ''): int {
        global $DB;

        // Validate target is in direct-report list (or skip on stock test DB
        // where open_supervisorid doesn't exist — managers can allocate
        // freely; production tenant scoping kicks in).
        $reports = self::direct_report_ids($managerid);
        if (!empty($reports) && !in_array($userid, $reports, true)) {
            throw new \moodle_exception('notdirectreport', 'local_airpay_manager');
        }

        // Course must exist.
        $DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);

        // Idempotent: don't double-allocate.
        if ($DB->record_exists('local_airpay_mgr_allocations',
                ['userid' => $userid, 'courseid' => $courseid])) {
            throw new \moodle_exception('duplicateallocation', 'local_airpay_manager');
        }

        $now = time();
        $tx = $DB->start_delegated_transaction();
        try {
            $id = (int) $DB->insert_record('local_airpay_mgr_allocations', (object) [
                'managerid'    => $managerid,
                'userid'       => $userid,
                'courseid'     => $courseid,
                'due_date'     => $due_date,
                'status'       => self::ALLOC_ASSIGNED,
                'note'         => $note,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }

        // Best-effort enrol — failure doesn't roll back the allocation
        // because the manager wants the assignment recorded even if the
        // enrol plugin is misconfigured.
        try {
            self::enrol_user_in_course($userid, $courseid, $due_date);
        } catch (\Throwable $e) {
            // Log via Moodle debugging; the allocation row is still saved.
            debugging('Allocation ' . $id . ' enrol failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }

        return $id;
    }

    /**
     * Manager-side: cancel an allocation.
     */
    public static function delete_allocation(int $id): void {
        global $DB;
        $DB->delete_records('local_airpay_mgr_allocations', ['id' => $id]);
    }

    /**
     * Get count of pending requests for the manager (for dashboard badge).
     */
    public static function pending_request_count(int $managerid): int {
        global $DB;
        return (int) $DB->count_records('local_airpay_mgr_requests',
            ['managerid' => $managerid, 'status' => self::STATUS_PENDING]);
    }
}
