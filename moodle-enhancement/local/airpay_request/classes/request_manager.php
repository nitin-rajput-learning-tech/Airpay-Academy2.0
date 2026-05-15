<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_request;

defined('MOODLE_INTERNAL') || die();

/**
 * Request manager — orchestrates the course-request lifecycle.
 *
 * State machine:
 *   pending → approved   (decide: approver clicks approve → enrol)
 *           → rejected   (decide: approver clicks reject + note)
 *           → cancelled  (cancel: requester clicks cancel)
 *           → expired    (cron: auto_expire_days exceeded)
 *
 * Approver routing on submit:
 *   1. If requester has open_managerid set → manager
 *   2. Else if course has a designated owner (custom field) → courseowner
 *   3. Else → settings.default_approver (typically site admin)
 *
 * Escalation:
 *   If timedue passes and status is still pending, escalate to next
 *   tier (manager → admin). Auto-fires every 15 min via cron.
 *
 * @package local_airpay_request
 */
class request_manager {

    /**
     * Submit a new request.
     *
     * @throws \moodle_exception on validation failure
     */
    public static function submit(int $userid, int $courseid, string $reason): \stdClass {
        global $DB, $USER;

        // Validate reason length (enterprise: prevent low-effort requests).
        if (strlen(trim($reason)) < 20) {
            throw new \moodle_exception('error_reasonshort', 'local_airpay_request');
        }

        $user = $userid === $USER->id
            ? $USER
            : $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        // Already enrolled?
        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $userid)) {
            throw new \moodle_exception('error_alreadyenrolled', 'local_airpay_request');
        }

        // Already pending request for same course?
        if ($DB->record_exists('local_airpay_request',
            ['userid' => $userid, 'courseid' => $courseid, 'status' => 'pending'])) {
            throw new \moodle_exception('error_alreadyrequested', 'local_airpay_request');
        }

        // Tenant snapshot — request belongs to user's tenant at submit time.
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        $costcenterid = isset($parts[0]) && ctype_digit($parts[0])
            ? (int) $parts[0] : 0;

        // Route the request — pick approver based on the routing rule.
        [$route, $approverid] = self::route_approver($user, $courseid);

        $sla_hours = (int) (get_config('local_airpay_request', 'sla_hours') ?: 48);
        $now = time();
        $timedue = $now + ($sla_hours * 3600);

        $rec = (object) [
            'userid'          => $userid,
            'courseid'        => $courseid,
            'costcenterid'    => $costcenterid,
            'reason'          => trim($reason),
            'status'          => 'pending',
            'route'           => $route,
            'approver_userid' => $approverid,
            'timecreated'     => $now,
            'timedue'         => $timedue,
            'timemodified'    => $now,
        ];
        $rec->id = $DB->insert_record('local_airpay_request', $rec);

        // Notifications.
        notifier::request_submitted($rec);
        notifier::request_pending($rec);

        // W1-9 (2026-05-15) — audit-trail event.
        try {
            \local_airpay_request\event\request_submitted::create([
                'context'       => \context_system::instance(),
                'objectid'      => (int) $rec->id,
                'relateduserid' => (int) $rec->userid,
                'other'         => [
                    'courseid'        => (int) $rec->courseid,
                    'costcenterid'    => (int) $rec->costcenterid,
                    'approver_userid' => (int) $rec->approver_userid,
                    'route'           => $rec->route,
                ],
            ])->trigger();
        } catch (\Throwable $e) {
            debugging('local_airpay_request: failed to emit request_submitted: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $rec;
    }

    /**
     * Route a request to the right approver.
     *
     * Returns [route_label, approver_userid].
     */
    public static function route_approver(\stdClass $user, int $courseid): array {
        global $DB;

        // 1. Direct manager via open_managerid (BizLMS convention).
        if (!empty($user->open_managerid)) {
            $manager = $DB->get_record('user',
                ['id' => $user->open_managerid, 'deleted' => 0, 'suspended' => 0]);
            if ($manager) {
                return ['manager', (int) $manager->id];
            }
        }

        // 2. Course owner — custom field `course_owner_userid`.
        // (Falls through silently if not configured.)
        $ownerid = self::get_course_owner_userid($courseid);
        if ($ownerid > 0) {
            return ['courseowner', $ownerid];
        }

        // 3. Default approver — typically site admin.
        $default = (int) (get_config('local_airpay_request', 'default_approver') ?: 2);
        return ['admin', $default];
    }

    /**
     * Decide on a request — approve or reject.
     *
     * On approve: user is enrolled in the course via manual enrol.
     */
    public static function decide(int $requestid, int $deciderid,
                                   string $decision, string $note): \stdClass {
        global $DB;
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \moodle_exception('error_invalidstate', 'local_airpay_request');
        }
        $rec = $DB->get_record('local_airpay_request', ['id' => $requestid], '*', MUST_EXIST);
        if ($rec->status !== 'pending') {
            throw new \moodle_exception('error_invalidstate', 'local_airpay_request');
        }

        // Auth: the assigned approver OR someone with overrideroute cap.
        // ── B10 fix: tenant equality required even for override-route ────
        // The :overrideroute cap is system-context. Without the tenant
        // equality check below, a Public-tenant power user holding the
        // cap could approve Airpay-internal compliance requests.
        if ((int) $rec->approver_userid !== $deciderid
            && !is_siteadmin($deciderid)) {
            $has_override = has_capability('local/airpay_request:overrideroute',
                \context_system::instance(), $deciderid);
            if (!$has_override) {
                throw new \moodle_exception('error_outoftenant', 'local_airpay_request');
            }
            \local_airpay_core\tenant::require_access(
                (int) $rec->costcenterid, $deciderid);
        }

        // Reject requires a note.
        if ($decision === 'rejected' && trim($note) === '') {
            throw new \moodle_exception('error_invalidstate', 'local_airpay_request',
                '', 'Decision note required for rejection');
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $now = time();
            $rec->status            = $decision;
            $rec->decision_note     = $note;
            $rec->decided_by_userid = $deciderid;
            $rec->timedecided       = $now;
            $rec->timemodified      = $now;
            $DB->update_record('local_airpay_request', $rec);

            if ($decision === 'approved') {
                self::enrol_user($rec->userid, $rec->courseid);
            }

            notifier::request_decided($rec);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return $rec;
        }

        // W1-9 (2026-05-15) — audit-trail event (outside the txn so even a
        // logstore write failure doesn't roll back the actual decision).
        try {
            $event_other = [
                'courseid'        => (int) $rec->courseid,
                'costcenterid'    => (int) $rec->costcenterid,
                'route'           => $rec->route,
                'has_decision_note' => trim((string) ($rec->decision_note ?? '')) !== '',
            ];
            if ($decision === 'approved') {
                \local_airpay_request\event\request_approved::create([
                    'context'       => \context_system::instance(),
                    'objectid'      => (int) $rec->id,
                    'userid'        => $deciderid,
                    'relateduserid' => (int) $rec->userid,
                    'other'         => $event_other,
                ])->trigger();
            } else {
                \local_airpay_request\event\request_rejected::create([
                    'context'       => \context_system::instance(),
                    'objectid'      => (int) $rec->id,
                    'userid'        => $deciderid,
                    'relateduserid' => (int) $rec->userid,
                    'other'         => $event_other,
                ])->trigger();
            }
        } catch (\Throwable $e) {
            debugging('local_airpay_request: failed to emit decide event: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $rec;
    }

    /** Requester cancels their own pending request. */
    public static function cancel(int $requestid, int $userid): bool {
        global $DB;
        $rec = $DB->get_record('local_airpay_request',
            ['id' => $requestid], '*', MUST_EXIST);
        if ((int) $rec->userid !== $userid) {
            throw new \moodle_exception('error_outoftenant', 'local_airpay_request');
        }
        if ($rec->status !== 'pending') {
            throw new \moodle_exception('error_invalidstate', 'local_airpay_request');
        }
        $rec->status = 'cancelled';
        $rec->timemodified = time();
        $DB->update_record('local_airpay_request', $rec);
        return true;
    }

    /** Get count of pending requests for the current approver (for a badge). */
    public static function pending_count_for_approver(int $userid): int {
        global $DB;
        return (int) $DB->count_records('local_airpay_request',
            ['approver_userid' => $userid, 'status' => 'pending']);
    }

    /** Cron: escalate overdue pending requests to next tier. */
    public static function escalate_overdue(): int {
        global $DB;
        $now = time();
        $rows = $DB->get_records_select('local_airpay_request',
            "status = :s AND timedue < :now AND (timeescalated IS NULL OR timeescalated < timedue)",
            ['s' => 'pending', 'now' => $now]);
        $escalated = 0;
        foreach ($rows as $r) {
            // Manager → admin escalation. Once at admin tier, stay there.
            if ($r->route === 'manager' || $r->route === 'courseowner') {
                $admin = (int) (get_config('local_airpay_request', 'default_approver') ?: 2);
                $r->approver_userid = $admin;
                $r->route = 'admin';
                $r->timeescalated = $now;
                $r->timedue = $now + ((int) (get_config('local_airpay_request', 'sla_hours') ?: 48) * 3600);
                $r->timemodified = $now;
                $DB->update_record('local_airpay_request', $r);
                notifier::request_escalated($r);
                $escalated++;
            }
        }
        return $escalated;
    }

    /** Cron: auto-expire requests past auto_expire_days. */
    public static function auto_expire(): int {
        global $DB;
        $days = (int) (get_config('local_airpay_request', 'auto_expire_days') ?: 30);
        if ($days <= 0) return 0;
        $cutoff = time() - ($days * 86400);
        $rows = $DB->get_records_select('local_airpay_request',
            "status = :s AND timecreated < :cut",
            ['s' => 'pending', 'cut' => $cutoff]);
        $expired = 0;
        foreach ($rows as $r) {
            $r->status = 'expired';
            $r->timemodified = time();
            $DB->update_record('local_airpay_request', $r);
            $expired++;
        }
        return $expired;
    }

    /**
     * Look up a course owner userid from a custom course field, if set.
     * Returns 0 if unset.
     */
    private static function get_course_owner_userid(int $courseid): int {
        global $DB;
        // Check Moodle custom course fields shortname='course_owner_userid'.
        $row = $DB->get_record_sql(
            "SELECT cd.intvalue FROM {customfield_data} cd
               JOIN {customfield_field} cf ON cf.id = cd.fieldid
              WHERE cf.shortname = :sn AND cd.instanceid = :cid
              LIMIT 1",
            ['sn' => 'course_owner_userid', 'cid' => $courseid]);
        return $row ? (int) $row->intvalue : 0;
    }

    /** Enrol user via manual enrol — idempotent, mirrors cart_manager. */
    private static function enrol_user(int $userid, int $courseid): void {
        global $DB;
        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $userid)) return;

        $manual = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol',
            ['courseid' => $courseid, 'enrol' => 'manual', 'status' => 0]);
        if (!$instance) {
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $manual->add_default_instance($course);
            $instance = $DB->get_record('enrol',
                ['courseid' => $courseid, 'enrol' => 'manual', 'status' => 0]);
        }
        $studentroleid = (int) ($DB->get_field('role', 'id',
            ['shortname' => 'student']) ?: 5);
        $manual->enrol_user($instance, $userid, $studentroleid, time(), 0,
            ENROL_USER_ACTIVE);
    }
}
