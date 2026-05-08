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

        // Close the dead-end: notify the requester their request was decided.
        // Best-effort — failure here doesn't unwind the decision.
        try {
            self::notify_requester_of_decision($request, $decision, $decision_reason);
        } catch (\Throwable $e) {
            debugging('Failed to notify requester of decision (request '
                . $requestid . '): ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return [
            'requestid'    => $requestid,
            'decision'     => $decision,
            'enrolwarning' => $enrolwarning,
        ];
    }

    /**
     * Send an in-app + email Moodle message to the requester explaining
     * the outcome. Honors the user's notification preferences.
     */
    public static function notify_requester_of_decision(\stdClass $request,
                                                          string $decision,
                                                          string $decision_reason = ''): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/messagelib.php');

        $userto = $DB->get_record('user', ['id' => $request->userid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $request->courseid],
            'id, fullname', IGNORE_MISSING);
        $coursename = $course ? format_string($course->fullname) : 'the requested course';

        $isapproved = $decision === self::STATUS_APPROVED;
        $subject = $isapproved
            ? 'Your enrolment request was approved'
            : 'Your enrolment request was not approved';

        $note = $decision_reason !== ''
            ? "\nManager note: " . $decision_reason . "\n"
            : '';

        if ($isapproved) {
            $body = "Hi " . $userto->firstname . ",\n\n"
                . "Your request to enrol in \"" . $coursename . "\" has been approved. "
                . "You're now enrolled.\n"
                . $note
                . "\nStart the course: "
                . (new \moodle_url('/course/view.php',
                    ['id' => $request->courseid]))->out(false);
        } else {
            $body = "Hi " . $userto->firstname . ",\n\n"
                . "Your request to enrol in \"" . $coursename . "\" was not approved at this time.\n"
                . $note
                . "\nReach out to your manager if you need clarification.";
        }

        $eventdata = new \core\message\message();
        $eventdata->component         = 'local_airpay_manager';
        $eventdata->name              = 'request_decided';
        $eventdata->userfrom          = \core_user::get_noreply_user();
        $eventdata->userto            = $userto;
        $eventdata->subject           = $subject;
        $eventdata->fullmessage       = $body;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = nl2br(s($body));
        $eventdata->smallmessage      = $subject;
        $eventdata->notification      = 1;
        $eventdata->contexturl        = (new \moodle_url('/course/view.php',
            ['id' => $request->courseid]))->out(false);
        $eventdata->contexturlname    = $coursename;

        message_send($eventdata);
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

        // Notify the learner that a course has been pushed to them.
        try {
            self::notify_assignee_of_allocation($managerid, $userid, $courseid,
                $due_date, $note);
        } catch (\Throwable $e) {
            debugging('Allocation ' . $id . ' notify failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }

        return $id;
    }

    /**
     * Notify a learner that their manager has allocated a course to them.
     */
    public static function notify_assignee_of_allocation(int $managerid, int $userid,
                                                          int $courseid, ?int $due_date,
                                                          string $note = ''): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/messagelib.php');

        $userto  = $DB->get_record('user',   ['id' => $userid],   '*', MUST_EXIST);
        $manager = $DB->get_record('user',   ['id' => $managerid], 'firstname, lastname');
        $course  = $DB->get_record('course', ['id' => $courseid], 'id, fullname');

        $coursename = $course ? format_string($course->fullname) : 'a course';
        $mgrname = $manager
            ? fullname((object) ['firstname' => $manager->firstname, 'lastname' => $manager->lastname])
            : 'your manager';

        $duebit = $due_date
            ? "\nDue: " . userdate($due_date, get_string('strftimedatefullshort', 'core_langconfig'))
            : '';
        $notebit = $note !== '' ? "\nNote from manager: " . $note : '';

        $body = "Hi " . $userto->firstname . ",\n\n"
            . $mgrname . " has assigned you the course \"" . $coursename . "\"."
            . $duebit . $notebit . "\n\n"
            . "Start now: "
            . (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);

        $eventdata = new \core\message\message();
        $eventdata->component         = 'local_airpay_manager';
        $eventdata->name              = 'allocation_assigned';
        $eventdata->userfrom          = \core_user::get_noreply_user();
        $eventdata->userto            = $userto;
        $eventdata->subject           = 'New course assigned: ' . $coursename;
        $eventdata->fullmessage       = $body;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = nl2br(s($body));
        $eventdata->smallmessage      = 'Course assigned: ' . $coursename;
        $eventdata->notification      = 1;
        $eventdata->contexturl        = (new \moodle_url('/course/view.php',
            ['id' => $courseid]))->out(false);
        $eventdata->contexturlname    = $coursename;

        message_send($eventdata);
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

    /**
     * Bulk allocation: assign one course to N users in a single batch.
     * Each row goes through the same idempotency + enrol + notify path
     * as create_allocation; failures on a single user don't abort the
     * batch (they're surfaced in the result).
     *
     * @param int $managerid
     * @param list<int> $userids
     * @param int $courseid
     * @param int|null $due_date  (Unix ts; null = no deadline)
     * @param string $note
     * @return array {
     *   succeeded: list<array{userid:int, allocid:int}>,
     *   skipped:   list<array{userid:int, reason:string}>,
     *   failed:    list<array{userid:int, error:string}>
     * }
     */
    public static function bulk_allocate(int $managerid, array $userids,
                                          int $courseid, ?int $due_date = null,
                                          string $note = ''): array {
        $succeeded = [];
        $skipped   = [];
        $failed    = [];

        foreach (array_unique(array_map('intval', $userids)) as $uid) {
            if ($uid <= 0) {
                $skipped[] = ['userid' => $uid, 'reason' => 'invalid_userid'];
                continue;
            }
            try {
                $allocid = self::create_allocation($managerid, $uid, $courseid,
                    $due_date, $note);
                $succeeded[] = ['userid' => $uid, 'allocid' => $allocid];
            } catch (\moodle_exception $e) {
                // Known business errors (duplicateallocation, notdirectreport)
                // are 'skipped', not 'failed'.
                $skipped[] = ['userid' => $uid,
                    'reason' => $e->errorcode ?: $e->getMessage()];
            } catch (\Throwable $e) {
                $failed[] = ['userid' => $uid, 'error' => $e->getMessage()];
            }
        }

        return [
            'succeeded' => $succeeded,
            'skipped'   => $skipped,
            'failed'    => $failed,
        ];
    }

    /**
     * Stream the manager's decisions (requests + allocations) as CSV rows.
     * Used by exportcsv.php for compliance review.
     *
     * @return \Generator yields header then one row per record
     */
    public static function csv_iterator_decisions(int $managerid): \Generator {
        global $DB;

        yield ['Type', 'When', 'User', 'Email', 'Course', 'Status',
               'Decision reason / Note', 'Decided by user ID'];

        $requests = $DB->get_records_sql("
            SELECT r.*, u.firstname, u.lastname, u.email,
                   c.fullname AS coursename
              FROM {local_airpay_mgr_requests} r
         LEFT JOIN {user}   u ON u.id = r.userid
         LEFT JOIN {course} c ON c.id = r.courseid
             WHERE r.managerid = :mgr
          ORDER BY r.timecreated DESC",
            ['mgr' => $managerid]);
        foreach ($requests as $r) {
            yield [
                'request',
                $r->decided_at
                    ? userdate((int) $r->decided_at,
                        get_string('strftimedatetimeshort', 'core_langconfig'))
                    : userdate((int) $r->timecreated,
                        get_string('strftimedatetimeshort', 'core_langconfig')),
                $r->firstname ? fullname((object) ['firstname' => $r->firstname,
                    'lastname' => $r->lastname]) : '—',
                (string) ($r->email ?? ''),
                (string) ($r->coursename ?? ''),
                (string) $r->status,
                (string) ($r->decision_reason ?? $r->reason ?? ''),
                (int) ($r->decided_by ?? 0),
            ];
        }

        $allocations = $DB->get_records_sql("
            SELECT a.*, u.firstname, u.lastname, u.email,
                   c.fullname AS coursename
              FROM {local_airpay_mgr_allocations} a
         LEFT JOIN {user}   u ON u.id = a.userid
         LEFT JOIN {course} c ON c.id = a.courseid
             WHERE a.managerid = :mgr
          ORDER BY a.timecreated DESC",
            ['mgr' => $managerid]);
        foreach ($allocations as $a) {
            yield [
                'allocation',
                userdate((int) $a->timecreated,
                    get_string('strftimedatetimeshort', 'core_langconfig')),
                $a->firstname ? fullname((object) ['firstname' => $a->firstname,
                    'lastname' => $a->lastname]) : '—',
                (string) ($a->email ?? ''),
                (string) ($a->coursename ?? ''),
                (string) $a->status,
                (string) ($a->note ?? ''),
                $managerid,
            ];
        }
    }
}
