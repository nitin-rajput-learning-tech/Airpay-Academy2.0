<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge;

defined('MOODLE_INTERNAL') || die();

/**
 * Leaderboard manager — owns the snapshot table.
 *
 * The snapshot table is recomputed wholesale every 15 minutes by the
 * scheduled task (idempotent), and incrementally for affected users
 * when a course completion event fires.
 *
 * Design choice: we precompute the rank rather than ORDER BY at read
 * time because (a) it lets us return ordered slices without forcing
 * a full table scan, and (b) it surfaces the specific user's rank
 * without a window-function dependency.
 *
 * @package    local_airpay_challenge
 */
class leaderboard_manager {

    /**
     * Recompute one challenge's leaderboard rows from the attempts table.
     *
     * Wholesale: deletes existing rows for this challenge, sums points
     * per user, ranks them, inserts new rows. Idempotent.
     */
    public static function recompute_challenge(int $challengeid): void {
        global $DB;

        $tx = $DB->start_delegated_transaction();
        try {
            $DB->delete_records('local_airpay_challenge_leaderboard',
                ['challengeid' => $challengeid]);

            // Sum the points awarded per user from completed attempts
            // (or in-progress attempts, but those contribute 0 points).
            $rows = $DB->get_records_sql("
                SELECT a.userid,
                       a.costcenterid,
                       SUM(a.pointsawarded) AS points,
                       SUM(CASE WHEN a.status = :completed THEN 1 ELSE 0 END) AS attemptscompleted
                  FROM {local_airpay_challenge_attempts} a
                 WHERE a.challengeid = :cid
              GROUP BY a.userid, a.costcenterid
              ORDER BY SUM(a.pointsawarded) DESC, a.userid ASC",
                ['completed' => challenge_engine::ATTEMPT_COMPLETED, 'cid' => $challengeid]);

            self::insert_ranked($rows, $challengeid);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
    }

    /**
     * Recompute the aggregate leaderboard (challengeid = 0) — total
     * points across all completed attempts.
     */
    public static function recompute_aggregate(): void {
        global $DB;

        $tx = $DB->start_delegated_transaction();
        try {
            $DB->delete_records('local_airpay_challenge_leaderboard',
                ['challengeid' => 0]);

            $rows = $DB->get_records_sql("
                SELECT a.userid,
                       a.costcenterid,
                       SUM(a.pointsawarded) AS points,
                       SUM(CASE WHEN a.status = :completed THEN 1 ELSE 0 END) AS attemptscompleted
                  FROM {local_airpay_challenge_attempts} a
                 WHERE a.pointsawarded > 0
              GROUP BY a.userid, a.costcenterid
              ORDER BY SUM(a.pointsawarded) DESC, a.userid ASC",
                ['completed' => challenge_engine::ATTEMPT_COMPLETED]);

            self::insert_ranked($rows, 0);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
    }

    /**
     * Helper: insert ranked rows. Rank is 1-based; ties get same rank
     * with subsequent ranks skipped (1, 2, 2, 4 — competition ranking).
     *
     * @param array<\stdClass> $rows  ordered DESC by points
     * @param int $challengeid 0 = aggregate
     */
    private static function insert_ranked(array $rows, int $challengeid): void {
        global $DB;
        $rank = 0;
        $previousPoints = null;
        $position = 0;
        $now = time();

        foreach ($rows as $r) {
            $position++;
            if ($previousPoints === null || (int) $r->points !== $previousPoints) {
                $rank = $position;
            }
            $previousPoints = (int) $r->points;

            $DB->insert_record('local_airpay_challenge_leaderboard', (object) [
                'challengeid'       => $challengeid,
                'userid'            => (int) $r->userid,
                'costcenterid'      => (int) $r->costcenterid,
                'points'            => (int) $r->points,
                'userrank'          => $rank,
                'attemptscompleted' => (int) $r->attemptscompleted,
                'lastrecomputed'    => $now,
            ]);
        }
    }

    /**
     * Recompute everything (called by the scheduled task).
     */
    public static function recompute_all(): void {
        global $DB;
        $challenges = $DB->get_records('local_airpay_challenge_challenges',
            ['status' => challenge_engine::STATUS_ACTIVE], 'id', 'id');
        foreach ($challenges as $c) {
            self::recompute_challenge((int) $c->id);
        }
        self::recompute_aggregate();
    }

    /**
     * Read top N for a challenge (or aggregate).
     *
     * @param int $challengeid 0 = aggregate
     * @param int $tenant      0 = no scoping (caller has :viewall);
     *                          >0 = restrict to that tenant
     * @param int $page        0-based
     * @param int $perpage     capped at 200
     */
    public static function get_top(int $challengeid, int $tenant = 0,
                                    int $page = 0, int $perpage = 25): array {
        global $DB;
        $perpage = max(5, min(200, $perpage));
        $page    = max(0, $page);

        $where = ['l.challengeid = :cid'];
        $params = ['cid' => $challengeid];
        if ($tenant > 0) {
            $where[] = 'l.costcenterid = :tn';
            $params['tn'] = $tenant;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_challenge_leaderboard} l WHERE $wheresql",
            $params);

        $rows = $DB->get_records_sql("
            SELECT l.id, l.userid, l.points, l.userrank, l.attemptscompleted,
                   u.firstname, u.lastname, u.email
              FROM {local_airpay_challenge_leaderboard} l
              JOIN {user} u ON u.id = l.userid
             WHERE $wheresql
          ORDER BY l.points DESC, l.userid ASC",
            $params, $page * $perpage, $perpage);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'rank'              => (int) $r->userrank,
                'userid'            => (int) $r->userid,
                'fullname'          => fullname((object) ['firstname' => $r->firstname, 'lastname' => $r->lastname]),
                'points'            => (int) $r->points,
                'attemptscompleted' => (int) $r->attemptscompleted,
            ];
        }
        return ['total' => $total, 'rows' => $out, 'page' => $page, 'perpage' => $perpage];
    }
}
