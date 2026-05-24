<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy opt-out manager.
 *
 * One row per (user, customer) in `local_sentientia_lb_optouts`. A user
 * is "opted out" if a row exists. There is no boolean — presence-only,
 * so reversal is a `delete_records` rather than a column flip. That makes
 * privacy export trivial (every row in the table represents an active
 * opt-out by definition) and means we can never accidentally restore a
 * leaderboard row for a previously-opted-out learner.
 *
 * Read-time semantics: the ranking_engine + WS reads filter their JOINs
 * against this table — an opted-out user's row is excluded from the
 * audience-facing payload but still computed internally (so HR analytics
 * under :viewall capability still sees them).
 *
 * @package local_sentientia_leaderboard
 */
class optout_manager {

    /**
     * Is the given user opted out from public listing in the given customer?
     *
     * @param int $userid
     * @param int $customerid Defaults to 1 (Airpay).
     */
    public static function is_opted_out(int $userid, int $customerid = 1): bool {
        global $DB;
        if ($userid <= 0) {
            return false;
        }
        return $DB->record_exists('local_sentientia_lb_optouts', [
            'userid'     => $userid,
            'customerid' => $customerid,
        ]);
    }

    /**
     * Opt the user out. Idempotent — calling twice doesn't create a
     * duplicate row.
     */
    public static function opt_out(int $userid, int $customerid = 1): void {
        global $DB;
        if ($userid <= 0) {
            throw new \moodle_exception('invaliduser');
        }
        if (self::is_opted_out($userid, $customerid)) {
            return;
        }
        $DB->insert_record('local_sentientia_lb_optouts', (object) [
            'userid'       => $userid,
            'customerid'   => $customerid,
            'timeoptedout' => time(),
        ]);
    }

    /**
     * Reverse the opt-out. Idempotent.
     */
    public static function opt_in(int $userid, int $customerid = 1): void {
        global $DB;
        if ($userid <= 0) {
            throw new \moodle_exception('invaliduser');
        }
        $DB->delete_records('local_sentientia_lb_optouts', [
            'userid'     => $userid,
            'customerid' => $customerid,
        ]);
    }

    /**
     * Get the set of opted-out userids in a customer (for the ranking
     * engine to filter results in bulk). Returns an array indexed by
     * userid for O(1) hash lookup.
     *
     * @param int $customerid
     * @return array<int, bool> Map: userid => true
     */
    public static function opted_out_userids(int $customerid = 1): array {
        global $DB;
        $rows = $DB->get_records('local_sentientia_lb_optouts',
            ['customerid' => $customerid], '', 'id, userid');
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->userid] = true;
        }
        return $out;
    }

    /**
     * Render the value for the /user/preferences.php preference (returns
     * '1' if opted out, '0' otherwise).
     *
     * Used by core_user\output\myprofile or the preferences page renderer
     * — wired up via lib.php callback.
     */
    public static function get_preference_value(int $userid, int $customerid = 1): string {
        return self::is_opted_out($userid, $customerid) ? '1' : '0';
    }

    /**
     * Apply a preference change submitted from /user/preferences.php.
     * $value === '1' or true → opt out. Anything else → opt in.
     */
    public static function set_preference_value(int $userid, $value,
                                                  int $customerid = 1): void {
        $opt_out = ($value === '1' || $value === 1 || $value === true);
        if ($opt_out) {
            self::opt_out($userid, $customerid);
        } else {
            self::opt_in($userid, $customerid);
        }
    }
}
