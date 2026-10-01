<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * Rules the three ratings steps share: who the row belongs to, what time it carries and which of two rows for
 * the same key wins (mapping doc section 20).
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class row_rules {

    /**
     * Can this row's user own a rating, review or reaction?
     *
     * BizLMS stored the user as a plain integer that could be NULL, 0, or the guest (the web service took the
     * caller's word for it), so the id is checked against the users the restored database really has. A
     * deleted user's rows are history and are kept (decision ratings.deleted_users = keep); the owner may
     * choose to skip them instead.
     *
     * @param context $ctx
     * @param mixed $userid The legacy user id, as read.
     * @return array{0: ?string, 1: string} [reason, detail]; the reason is null when the user is usable.
     */
    public static function user(context $ctx, mixed $userid): array {
        $id = ($userid === null || $userid === '') ? 0 : (int) $userid;
        if ($id <= 1) {
            // 0 is nobody and 1 is the guest: neither can rate (rating_manager refuses both).
            return ['orphan_user', 'user_not_real'];
        }
        if (!$ctx->lookups->user_exists($id)) {
            return ['orphan_user', 'user_not_found'];
        }
        if (!$ctx->lookups->user_active($id) && $ctx->decision('ratings.deleted_users') === 'skip') {
            return ['user_deleted', ''];
        }
        return [null, ''];
    }

    /**
     * The two timestamps of a target row, from the source row's own columns.
     *
     * created is the first of timecreated, the 2013-era time column (when the table still has it) and
     * timemodified that is set; modified is the first of timemodified, timecreated and time. A column is "set"
     * when it is above zero. When one had to stand in for the other the row is reported as derived_timestamp;
     * when none is set both are 0, reported as missing_timestamp, and the legacy row keeps the truth.
     *
     * @param \stdClass $row
     * @return array{0: int, 1: int, 2: bool, 3: bool} [created, modified, derived, missing]
     */
    public static function times(\stdClass $row): array {
        $c = (int) ($row->timecreated ?? 0);
        $m = (int) ($row->timemodified ?? 0);
        $t = (int) ($row->time ?? 0);
        $created = $c > 0 ? $c : ($t > 0 ? $t : ($m > 0 ? $m : 0));
        $modified = $m > 0 ? $m : ($c > 0 ? $c : ($t > 0 ? $t : 0));
        $derived = ($created > 0 && $c <= 0) || ($modified > 0 && $m <= 0);
        return [$created, $modified, $derived, $created <= 0 && $modified <= 0];
    }

    /**
     * Attach the timestamp warnings of a row to its outcome.
     *
     * @param outcome $outcome
     * @param \stdClass $row The source row the timestamps came from.
     * @return outcome
     */
    public static function warn_times(outcome $outcome, \stdClass $row): outcome {
        [, , $derived, $missing] = self::times($row);
        if ($derived) {
            $outcome->warn('derived_timestamp');
        }
        if ($missing) {
            $outcome->warn('missing_timestamp');
        }
        return $outcome;
    }

    /**
     * The row that wins when several rows hold the same (user, item, area): the latest change
     * (COALESCE(timemodified, timecreated)), then the highest id. The others are merged into it.
     *
     * @param \stdClass[] $rows At least one source row.
     * @return \stdClass
     */
    public static function winner(array $rows): \stdClass {
        $best = null;
        $bestkey = null;
        foreach ($rows as $row) {
            $key = [self::times($row)[1], (int) $row->id];
            if ($best === null || $key > $bestkey) {
                $best = $row;
                $bestkey = $key;
            }
        }
        return $best;
    }
}
