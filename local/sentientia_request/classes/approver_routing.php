<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

/**
 * Who a request goes to: the routing rules, read-only.
 *
 * These rules lived in request_manager (route_approver, route_approver_for_path and two private
 * helpers). They moved here, unchanged, so the BizLMS import (ADR-032, classes/bizlms/) can route
 * the legacy pending requests the same way a new submission is routed. The import may not call
 * request_manager - it submits, decides and notifies, and the static scan bans it - but routing
 * only READS, so it is shared through this class. request_manager delegates to it; there is one
 * copy of the rules.
 *
 * Nothing here writes, sends or triggers anything.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class approver_routing {

    /** Route label: the requester's direct supervisor decides. */
    public const ROUTE_MANAGER = 'manager';

    /** Route label: the owner of the course decides. */
    public const ROUTE_COURSEOWNER = 'courseowner';

    /** Route label: the default approver (typically a site admin) decides. */
    public const ROUTE_ADMIN = 'admin';

    /**
     * Route a course request.
     *
     * 1. The requester's live direct supervisor.
     * 2. The course owner (custom course field course_owner_userid).
     * 3. The default approver from settings.
     *
     * @param \stdClass $user The requester. It must carry open_supervisorid and open_managerid where the site
     *        has them: a record without them routes every request to the default approver.
     * @param int $courseid
     * @return array{0: string, 1: int} [route label, approver user id]
     */
    public static function for_course(\stdClass $user, int $courseid): array {
        $supervisorid = self::supervisor_of($user);
        if ($supervisorid > 0) {
            return [self::ROUTE_MANAGER, $supervisorid];
        }

        $ownerid = self::course_owner($courseid);
        if ($ownerid > 0) {
            return [self::ROUTE_COURSEOWNER, $ownerid];
        }

        return [self::ROUTE_ADMIN, self::default_approver()];
    }

    /**
     * Route a learning-path request. The same chain as for_course() without the course-owner step: a path
     * has no owner field yet.
     *
     * @param \stdClass $user The requester (see for_course()).
     * @param int $pathid
     * @return array{0: string, 1: int} [route label, approver user id]
     */
    public static function for_path(\stdClass $user, int $pathid): array {
        $supervisorid = self::supervisor_of($user);
        if ($supervisorid > 0) {
            return [self::ROUTE_MANAGER, $supervisorid];
        }
        return [self::ROUTE_ADMIN, self::default_approver()];
    }

    /**
     * Resolve the requester's direct supervisor (BizLMS convention).
     *
     * WF-018 (2026-06-11): the production mdl_user schema carries open_supervisorid - open_managerid does NOT
     * exist on BizLMS, so the original lookup never matched and every request silently fell through to the
     * course-owner / default-approver routes. open_supervisorid is authoritative; open_managerid stays as a
     * secondary lookup for deployments that add that column.
     *
     * @param \stdClass $user
     * @return int Approver user id, or 0 when no live supervisor is set.
     */
    public static function supervisor_of(\stdClass $user): int {
        global $DB;
        foreach (['open_supervisorid', 'open_managerid'] as $field) {
            if (empty($user->{$field})) {
                continue;
            }
            $supervisor = $DB->get_record('user',
                ['id' => $user->{$field}, 'deleted' => 0, 'suspended' => 0]);
            if ($supervisor) {
                return (int) $supervisor->id;
            }
        }
        return 0;
    }

    /**
     * The approver requests fall back to when nobody else is set.
     *
     * @return int User id.
     */
    public static function default_approver(): int {
        return (int) (get_config('local_sentientia_request', 'default_approver') ?: 2);
    }

    /**
     * Look up a course owner userid from a custom course field, if set.
     *
     * @param int $courseid
     * @return int User id, 0 when unset.
     */
    private static function course_owner(int $courseid): int {
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
}
