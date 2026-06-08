<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * Sprint D — pull/request workflow for cross-tenant course sharing.
 *
 * Pairs with Sprint C's sharing_manager. The flow:
 *
 *   1. A receiving-tenant manager (e.g. Public/77) sees an Airpay
 *      course in /local/sentientia_courses/browse_airpay.php and clicks
 *      "Request access". The page calls request_manager::create_request.
 *
 *   2. The Airpay Super Admin opens /local/sentientia_courses/manage_requests.php,
 *      reviews pending requests, and clicks Approve or Reject. The page
 *      calls request_manager::approve_request or reject_request.
 *
 *   3. On approval, this class ALSO calls
 *      sharing_manager::share_course() to insert the active share
 *      row. So the catalog query immediately picks up the new
 *      borrowed course for the requesting tenant.
 *
 *   4. On rejection, the request stays in the DB with status='rejected'
 *      so the manager can see the decision (and optional reason) in
 *      their requests outbox.
 *
 * Dedup rule: a tenant can only have ONE pending request per (course)
 * at a time. Re-requesting an already-pending course is a no-op
 * (returns the existing request id). Re-requesting a previously
 * rejected/approved course CREATES a new row (admins might re-decide).
 *
 * @package local_sentientia_courses
 */
class request_manager {

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * File a new request from a tenant's manager.
     *
     * Dedup: if a pending request already exists for the same
     * (course, tenant), return its id rather than creating a duplicate.
     *
     * @param int $courseid
     * @param int|null $requester_userid Defaults to $USER->id
     * @return int request id (existing pending one, or freshly created)
     */
    public static function create_request(int $courseid,
                                            ?int $requester_userid = null): int {
        global $DB, $USER;
        $requester_userid = $requester_userid ?? (int) $USER->id;

        if ($courseid <= 0 || $requester_userid <= 0) {
            throw new \moodle_exception('invalidparameter', 'local_sentientia_courses');
        }
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            throw new \moodle_exception('invalidcourse', 'local_sentientia_courses');
        }

        // Derive requesting tenant from the requester's open_path.
        $requester = $DB->get_record('user', ['id' => $requester_userid],
            'id, open_path, deleted, suspended', MUST_EXIST);
        if ($requester->deleted || $requester->suspended) {
            throw new \moodle_exception('invaliduser', 'local_sentientia_courses');
        }
        $parts = explode('/', trim($requester->open_path ?? '', '/'));
        $requesting_tenant = isset($parts[0]) && ctype_digit($parts[0])
            ? (int) $parts[0] : 0;
        if ($requesting_tenant <= 0) {
            throw new \moodle_exception('invalidtenant', 'local_sentientia_courses');
        }

        // Defensive: an Airpay user cannot request an Airpay course for
        // their own tenant — they already have it.
        $course = $DB->get_record('course', ['id' => $courseid],
            'id, open_path', MUST_EXIST);
        $course_parts = explode('/', trim($course->open_path ?? '', '/'));
        $course_owner = isset($course_parts[0]) && ctype_digit($course_parts[0])
            ? (int) $course_parts[0] : 0;
        if ($course_owner === $requesting_tenant) {
            throw new \moodle_exception('cannotrequestowncourse',
                'local_sentientia_courses');
        }

        // Dedup — pending request for the same (course, tenant)?
        $existing = $DB->get_record('local_sentientia_courses_requests', [
            'courseid'          => $courseid,
            'requesting_tenant' => $requesting_tenant,
            'status'            => self::STATUS_PENDING,
        ]);
        if ($existing) {
            return (int) $existing->id;
        }

        // Defensive: if a share is ALREADY active for (course, tenant),
        // there's nothing to request — the course is already in the
        // catalog. Return 0 so the UI can render a "already shared"
        // message rather than queue a redundant request.
        if (sharing_manager::is_course_shared_to($courseid, $requesting_tenant)) {
            return 0;
        }

        $now = time();
        $id = (int) $DB->insert_record('local_sentientia_courses_requests', (object) [
            'courseid'          => $courseid,
            'requesting_tenant' => $requesting_tenant,
            'requester_userid'  => $requester_userid,
            'status'            => self::STATUS_PENDING,
            'timecreated'       => $now,
        ]);

        // Audit event. We omit the top-level `courseid` key because the
        // event's context is CONTEXT_SYSTEM (tenant-administrative
        // action) and Moodle's event base warns "Inconsistent courseid
        // - context combination" when the two are mixed. The course id
        // stays inside `other` so downstream consumers can filter.
        $event = event\course_share_requested::create([
            'objectid' => $id,
            'context'  => \context_system::instance(),
            'userid'   => $requester_userid,
            'other'    => [
                'request_id'        => $id,
                'courseid'          => $courseid,
                'requesting_tenant' => $requesting_tenant,
            ],
        ]);
        $event->trigger();

        return $id;
    }

    /**
     * Approve a pending request.
     *
     * Side effect: inserts the matching active row into the Sprint C
     * share table via sharing_manager::share_course(). The share
     * insertion will itself fire a `course_share_created` event, so
     * the audit log records BOTH events for a single approval.
     *
     * Idempotent: re-approving an already-approved request is a no-op
     * (returns false). Re-approving a rejected request is allowed —
     * admin may change their mind.
     *
     * @param int $request_id
     * @param int|null $by_userid
     * @return bool true if the request was newly approved, false otherwise
     */
    public static function approve_request(int $request_id,
                                             ?int $by_userid = null): bool {
        global $DB, $USER;
        $by_userid = $by_userid ?? (int) $USER->id;

        $req = $DB->get_record('local_sentientia_courses_requests',
            ['id' => $request_id], '*', MUST_EXIST);
        if ($req->status === self::STATUS_APPROVED) {
            return false;
        }

        $req->status         = self::STATUS_APPROVED;
        $req->decided_by     = $by_userid;
        $req->decision_reason = null;
        $req->timedecided    = time();
        $DB->update_record('local_sentientia_courses_requests', $req);

        // Insert the share row. If the row already exists (e.g. an
        // admin previously pushed the same share), share_course is
        // idempotent and just returns the "unchanged" outcome.
        sharing_manager::share_course((int) $req->courseid,
            [(int) $req->requesting_tenant], $by_userid);

        // Audit event. courseid stays in `other` only — see
        // create_request for the explanation.
        $event = event\course_share_request_approved::create([
            'objectid' => (int) $req->id,
            'context'  => \context_system::instance(),
            'userid'   => $by_userid,
            'other'    => [
                'request_id'        => (int) $req->id,
                'courseid'          => (int) $req->courseid,
                'requesting_tenant' => (int) $req->requesting_tenant,
            ],
        ]);
        $event->trigger();

        // Bust catalog caches so the borrowed course appears immediately.
        \cache_helper::purge_by_definition('local_sentientia_catalog', 'trending');
        \cache_helper::purge_by_definition('local_sentientia_catalog', 'new_courses');
        \cache_helper::purge_by_definition('local_sentientia_catalog', 'categories');

        return true;
    }

    /**
     * Reject a pending request.
     *
     * The request row stays with status='rejected' so the manager
     * can see "Why was my request rejected?" in their outbox.
     *
     * @param int    $request_id
     * @param string $reason     Optional rejection rationale (PARAM_TEXT)
     * @param int|null $by_userid
     * @return bool true if the request was newly rejected
     */
    public static function reject_request(int $request_id, string $reason = '',
                                            ?int $by_userid = null): bool {
        global $DB, $USER;
        $by_userid = $by_userid ?? (int) $USER->id;

        $req = $DB->get_record('local_sentientia_courses_requests',
            ['id' => $request_id], '*', MUST_EXIST);
        if ($req->status === self::STATUS_REJECTED) {
            return false;
        }

        $req->status          = self::STATUS_REJECTED;
        $req->decided_by      = $by_userid;
        $req->decision_reason = $reason !== '' ? $reason : null;
        $req->timedecided     = time();
        $DB->update_record('local_sentientia_courses_requests', $req);

        $event = event\course_share_request_rejected::create([
            'objectid' => (int) $req->id,
            'context'  => \context_system::instance(),
            'userid'   => $by_userid,
            'other'    => [
                'request_id'        => (int) $req->id,
                'courseid'          => (int) $req->courseid,
                'requesting_tenant' => (int) $req->requesting_tenant,
                'has_reason'        => $reason !== '',
            ],
        ]);
        $event->trigger();

        return true;
    }

    /**
     * List pending requests across all tenants — the Airpay Super
     * Admin's inbox. Newest first.
     *
     * @param int $limit
     * @return object[] rows joined with user + course for display
     */
    public static function list_pending_requests(int $limit = 100): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.courseid, r.requesting_tenant, r.requester_userid,
                    r.status, r.timecreated,
                    u.firstname, u.lastname, u.email,
                    c.fullname AS coursename, c.shortname AS courseshort
               FROM {local_sentientia_courses_requests} r
               JOIN {user}   u ON u.id = r.requester_userid
               JOIN {course} c ON c.id = r.courseid
              WHERE r.status = :status
           ORDER BY r.timecreated DESC",
            ['status' => self::STATUS_PENDING], 0, $limit);
        return array_values($rows);
    }

    /**
     * List requests filed FROM a particular tenant — the manager
     * outbox view. Includes all statuses, newest first.
     *
     * @param int $tenant_id
     * @param int $limit
     * @return object[]
     */
    public static function list_tenant_requests(int $tenant_id,
                                                  int $limit = 100): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.courseid, r.requesting_tenant, r.requester_userid,
                    r.status, r.decision_reason, r.timecreated, r.timedecided,
                    c.fullname AS coursename, c.shortname AS courseshort
               FROM {local_sentientia_courses_requests} r
               JOIN {course} c ON c.id = r.courseid
              WHERE r.requesting_tenant = :tid
           ORDER BY r.timecreated DESC",
            ['tid' => $tenant_id], 0, $limit);
        return array_values($rows);
    }

    /**
     * Quick lookup for the browse_airpay UI — "what's the current
     * request state for this (course, tenant) pair?". Returns one
     * of `none`, `pending`, `rejected`, or `already_shared`.
     *
     * Notes on the state machine
     * --------------------------
     * - `already_shared` is determined by the CURRENT state of the
     *   share table (status='active' on a row in
     *   local_sentientia_courses_tenant_share). NOT by historical request
     *   approval — if an admin approved a request then later
     *   unshared the course, the share row is now 'withdrawn' and
     *   the manager should be able to re-request.
     *
     * - We look at the most recent PENDING or REJECTED request only.
     *   Historical `approved` rows are deliberately ignored — once
     *   approved, the share row is the source of truth. If the share
     *   was later withdrawn, the manager should see "Not requested"
     *   (and be able to re-request via the same button).
     *
     * @param int $courseid
     * @param int $tenant_id
     * @return string one of: none | pending | rejected | already_shared
     */
    public static function request_state(int $courseid, int $tenant_id): string {
        global $DB;

        // already_shared takes precedence — the course is currently
        // in the receiving tenant's catalog (active share row exists).
        if (sharing_manager::is_course_shared_to($courseid, $tenant_id)) {
            return 'already_shared';
        }

        // Find the most recent OPEN-STATUS request row (pending or
        // rejected). Approved requests are deliberately excluded —
        // once approved, the share row is the source of truth, and
        // if it later becomes withdrawn the manager should be able
        // to re-request from a clean slate.
        //
        // Sort by (timecreated DESC, id DESC) — the secondary key on
        // id breaks ties when two rows share the same `timecreated`
        // value. That happens in PHPUnit (two inserts within the same
        // second on a fast machine) and is possible in production
        // during a bulk re-request from the same manager. Without the
        // tie-breaker, MariaDB's ORDER BY is undefined and a stale
        // 'rejected' row could shadow a brand-new 'pending' row.
        // Day-3 bugfix surfaced by request_manager_test.
        $rows = $DB->get_records_select('local_sentientia_courses_requests',
            'courseid = :cid AND requesting_tenant = :tid'
                . " AND status IN ('pending', 'rejected')",
            ['cid' => $courseid, 'tid' => $tenant_id],
            'timecreated DESC, id DESC', 'id, status', 0, 1);
        if (empty($rows)) {
            return 'none';
        }
        $row = reset($rows);
        return (string) $row->status;
    }
}
