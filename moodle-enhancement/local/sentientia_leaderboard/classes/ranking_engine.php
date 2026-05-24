<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Ranking engine — computes board rankings + writes the snapshot table.
 *
 * Per ADR-014, recomputes are batched (no per-action triggers) and run
 * wholesale: delete every row for a board, recompute, insert. Idempotent.
 *
 * The competition-ranking algorithm (1, 2, 2, 4) mirrors
 * local_airpay_challenge\leaderboard_manager — ties take the same rank,
 * the next rank skips. This matches FIDE chess + most sports usage.
 *
 * @package local_sentientia_leaderboard
 */
class ranking_engine {

    /**
     * Recompute one board. Wholesale: deletes existing entries, computes
     * fresh, inserts. Emits a `leaderboard.recomputed` event after a
     * successful commit so any open SSE clients pick up the change.
     *
     * Phase L.1: also captures pre/post rank maps and triggers a Moodle
     * `rankings_updated` event when one or more learners moved 5+
     * positions or entered the top 10. The observer (gated behind the
     * `sentientia.leaderboards.notifications.enabled` feature flag —
     * default OFF) routes those into Moodle messages.
     *
     * @param int|\stdClass $board Board id or row.
     * @throws \moodle_exception on invalid input or unknown type.
     */
    public static function recompute(int|\stdClass $board): void {
        global $DB;

        if (is_int($board)) {
            $board = board_manager::get($board);
            if (!$board) {
                throw new \moodle_exception('error_noboard',
                    'local_sentientia_leaderboard');
            }
        }
        $boardid = (int) $board->id;

        // Aggregate raw scores per user according to the board type.
        $rows = match ($board->type) {
            board_manager::TYPE_QUIZ       => self::aggregate_quiz($board),
            board_manager::TYPE_COMPLETION => self::aggregate_completion($board),
            board_manager::TYPE_SKILL      => self::aggregate_skill($board),
            default => throw new \moodle_exception('error_invalidtype',
                'local_sentientia_leaderboard'),
        };

        // Sort by points DESC, then secondary ASC (lower = better tiebreak —
        // shorter quiz time, lower seconds-to-complete, etc.), then userid
        // for stable order.
        usort($rows, function($a, $b) {
            if ((int) $a->points !== (int) $b->points) {
                return (int) $b->points - (int) $a->points;
            }
            if ((int) $a->secondary !== (int) $b->secondary) {
                return (int) $a->secondary - (int) $b->secondary;
            }
            return (int) $a->userid - (int) $b->userid;
        });

        // Phase L.1: snapshot the pre-recompute rank map so the
        // notification observer can compute deltas. Map is small
        // (one int per ranked user) so this is cheap even on the
        // 200-row cap.
        $old_ranks = $DB->get_records_menu('local_sentientia_lb_entries',
            ['boardid' => $boardid], '', 'userid, userrank');
        $old_ranks = array_map('intval', $old_ranks);

        $new_ranks = [];
        $tx = $DB->start_delegated_transaction();
        try {
            $DB->delete_records('local_sentientia_lb_entries',
                ['boardid' => $boardid]);

            $new_ranks = self::insert_ranked($rows, $boardid);
            board_manager::mark_recomputed($boardid);

            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
            return;
        }

        // After commit: emit the recomputed event so SSE clients refresh.
        // We DON'T compute per-user position_changed events on every recompute —
        // that would N-multiply the event volume. Clients refresh wholesale
        // on `leaderboard.recomputed`. Per-user position_changed events
        // remain reserved for a future incremental-update path.
        event_journal::write($boardid, 'leaderboard.recomputed', [
            'boardid'        => $boardid,
            'recomputed_at'  => time(),
            'entry_count'    => count($rows),
        ]);

        // Phase L.1: route the "interesting" rank changes (5+ positions
        // or a fresh top-10 entry) through the Moodle event bus so the
        // notification observer can fire messages. Empty change-set =
        // no event (defends Moodle's log noise).
        $changes = message_helper::compute_changes($old_ranks, $new_ranks);
        if (!empty($changes)) {
            event\rankings_updated::create([
                'context'  => \context_system::instance(),
                'objectid' => $boardid,
                'other'    => ['changes' => $changes],
            ])->trigger();
        }
    }

    /**
     * Recompute every active board whose last_recomputed is older than
     * its recompute_seconds. Called by the scheduled task.
     *
     * @return int Count of boards recomputed.
     */
    public static function recompute_due(): int {
        $due = board_manager::boards_due_for_recompute();
        foreach ($due as $b) {
            try {
                self::recompute($b);
            } catch (\Throwable $e) {
                debugging('ranking_engine: recompute failed for board '
                    . $b->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return count($due);
    }

    // ─── Type-specific aggregators ───────────────────────────────────

    /**
     * Quiz type: top scorer = highest sumgrades on a quiz attempt.
     * Tiebreak: shorter attempt time. Tenant scope is enforced on the
     * user join via the user's open_path.
     *
     * @return array<\stdClass> Each row: {userid, costcenterid, points, secondary}
     */
    private static function aggregate_quiz(\stdClass $board): array {
        global $DB;
        if ((int) $board->quizid <= 0) {
            return [];
        }
        $params = ['quizid' => (int) $board->quizid];

        // window filter on timestart (when the attempt was started).
        $window_sql = '';
        if ((int) $board->window_start > 0) {
            $window_sql .= ' AND a.timestart >= :wstart';
            $params['wstart'] = (int) $board->window_start;
        }
        if ((int) $board->window_end > 0) {
            $window_sql .= ' AND a.timestart <= :wend';
            $params['wend'] = (int) $board->window_end;
        }

        // tenant scope. Customer-wide boards (tenantid=0) skip the filter.
        $tenant_sql = '';
        if ((int) $board->tenantid > 0) {
            $tenant_sql = " AND (u.open_path = :tnexact OR u.open_path LIKE :tnprefix)";
            $params['tnexact']  = '/' . (int) $board->tenantid;
            $params['tnprefix'] = '/' . (int) $board->tenantid . '/%';
        }

        // mod_quiz attempts: state=finished, take MAX(sumgrades) per user.
        // tiebreak: MIN time spent on the best attempt.
        // points = sumgrades * 100 (integer for the rank column).
        $sql = "SELECT u.id AS userid,
                       u.open_path AS open_path,
                       FLOOR(MAX(a.sumgrades) * 100) AS points,
                       MIN(CASE WHEN a.sumgrades = mx.maxgrade
                                 THEN (a.timefinish - a.timestart)
                                 ELSE 999999 END) AS secondary
                  FROM {quiz_attempts} a
                  JOIN {user} u ON u.id = a.userid
                  JOIN (
                       SELECT userid, MAX(sumgrades) AS maxgrade
                         FROM {quiz_attempts}
                        WHERE quiz = :quizid2
                          AND state = 'finished'
                          AND preview = 0
                     GROUP BY userid
                  ) mx ON mx.userid = a.userid AND mx.maxgrade = a.sumgrades
                 WHERE a.quiz = :quizid
                   AND a.state = 'finished'
                   AND a.preview = 0
                   AND u.deleted = 0
                   AND u.suspended = 0
                   $window_sql
                   $tenant_sql
              GROUP BY u.id, u.open_path";
        $params['quizid2'] = (int) $board->quizid;
        $raw = $DB->get_records_sql($sql, $params);

        $out = [];
        foreach ($raw as $r) {
            $tn = board_manager::resolve_tenant_from_open_path((string) ($r->open_path ?? ''));
            $out[] = (object) [
                'userid'       => (int) $r->userid,
                'costcenterid' => $tn,
                'points'       => (int) $r->points,
                'secondary'    => (int) $r->secondary,
            ];
        }
        return $out;
    }

    /**
     * Completion type: fastest to complete a course. Points = -1 *
     * seconds-to-complete (negated so DESC orders fastest-first; the UI
     * converts back to a human duration for display). Secondary = 100 *
     * completion percentage at recompute time.
     *
     * @return array<\stdClass>
     */
    private static function aggregate_completion(\stdClass $board): array {
        global $DB;
        if ((int) $board->courseid <= 0) {
            return [];
        }
        $params = ['courseid' => (int) $board->courseid];

        $window_sql = '';
        if ((int) $board->window_start > 0) {
            $window_sql .= ' AND cc.timeenrolled >= :wstart';
            $params['wstart'] = (int) $board->window_start;
        }
        if ((int) $board->window_end > 0) {
            $window_sql .= ' AND cc.timecompleted <= :wend';
            $params['wend'] = (int) $board->window_end;
        }
        $tenant_sql = '';
        if ((int) $board->tenantid > 0) {
            $tenant_sql = " AND (u.open_path = :tnexact OR u.open_path LIKE :tnprefix)";
            $params['tnexact']  = '/' . (int) $board->tenantid;
            $params['tnprefix'] = '/' . (int) $board->tenantid . '/%';
        }

        $sql = "SELECT u.id AS userid,
                       u.open_path AS open_path,
                       (cc.timecompleted - cc.timeenrolled) AS duration,
                       cc.timecompleted AS timecompleted
                  FROM {course_completions} cc
                  JOIN {user} u ON u.id = cc.userid
                 WHERE cc.course = :courseid
                   AND cc.timecompleted IS NOT NULL
                   AND cc.timecompleted > 0
                   AND cc.timeenrolled > 0
                   AND u.deleted = 0
                   AND u.suspended = 0
                   $window_sql
                   $tenant_sql";
        $raw = $DB->get_records_sql($sql, $params);

        $out = [];
        foreach ($raw as $r) {
            $duration = max(0, (int) $r->duration);
            $tn = board_manager::resolve_tenant_from_open_path((string) ($r->open_path ?? ''));
            $out[] = (object) [
                'userid'       => (int) $r->userid,
                'costcenterid' => $tn,
                // Negate so the higher = better convention used by the
                // insert_ranked sort works. UI converts back to duration.
                'points'       => -1 * $duration,
                'secondary'    => 10000,  // 100% — only completed records reach here
            ];
        }
        return $out;
    }

    /**
     * Skill type: sum of skill-level changes that increase level, within
     * the window. Reuses local_airpay_user_skill_hist (P1 #22) — each
     * level upgrade is one row there with (previous_level, new_level).
     * Points = SUM(new_level - previous_level) where new > previous.
     * Secondary = COUNT(distinct skillid).
     *
     * @return array<\stdClass>
     */
    private static function aggregate_skill(\stdClass $board): array {
        global $DB;

        // Bail cleanly when local_airpay_skills isn't installed yet — the
        // skill board type is feature-flagged, so this path is only
        // reachable when the admin turned it on.
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_airpay_user_skill_hist')) {
            return [];
        }

        $params = [];
        $window_sql = '';
        if ((int) $board->window_start > 0) {
            $window_sql .= ' AND h.timecreated >= :wstart';
            $params['wstart'] = (int) $board->window_start;
        }
        if ((int) $board->window_end > 0) {
            $window_sql .= ' AND h.timecreated <= :wend';
            $params['wend'] = (int) $board->window_end;
        }
        $tenant_sql = '';
        if ((int) $board->tenantid > 0) {
            $tenant_sql = " AND (u.open_path = :tnexact OR u.open_path LIKE :tnprefix)";
            $params['tnexact']  = '/' . (int) $board->tenantid;
            $params['tnprefix'] = '/' . (int) $board->tenantid . '/%';
        }
        $skill_sql = '';
        if (!empty($board->skill_ids_json)) {
            $ids = json_decode((string) $board->skill_ids_json, true) ?: [];
            $ids = array_filter(array_map('intval', $ids));
            if (!empty($ids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($ids,
                    SQL_PARAMS_NAMED, 'sid');
                $skill_sql = " AND h.skillid $insql";
                $params = array_merge($params, $inparams);
            }
        }

        $sql = "SELECT u.id AS userid,
                       u.open_path AS open_path,
                       SUM(h.new_level - h.previous_level) AS points,
                       COUNT(DISTINCT h.skillid) AS skills_count
                  FROM {local_airpay_user_skill_hist} h
                  JOIN {user} u ON u.id = h.userid
                 WHERE h.new_level > h.previous_level
                   AND u.deleted = 0
                   AND u.suspended = 0
                   $window_sql
                   $tenant_sql
                   $skill_sql
              GROUP BY u.id, u.open_path";
        $raw = $DB->get_records_sql($sql, $params);

        $out = [];
        foreach ($raw as $r) {
            $tn = board_manager::resolve_tenant_from_open_path((string) ($r->open_path ?? ''));
            $out[] = (object) [
                'userid'       => (int) $r->userid,
                'costcenterid' => $tn,
                'points'       => (int) $r->points,
                'secondary'    => (int) $r->skills_count,
            ];
        }
        return $out;
    }

    /**
     * Insert ranked rows into the entries table. Rank is 1-based;
     * ties get the same rank with subsequent ranks skipped
     * (1, 2, 2, 4 — competition ranking).
     *
     * Caller has already sorted $rows DESC by points (+secondary tiebreak).
     *
     * Returns a map of userid => assigned rank — used by recompute()
     * to compute Phase L.1 rank-change deltas without re-reading the
     * table.
     *
     * @return array<int, int>
     */
    private static function insert_ranked(array $rows, int $boardid): array {
        global $DB;
        $rank = 0;
        $position = 0;
        $previous_points    = null;
        $previous_secondary = null;
        $now = time();
        $new_ranks = [];

        foreach ($rows as $r) {
            $position++;
            $points    = (int) $r->points;
            $secondary = (int) $r->secondary;
            if ($previous_points === null
                    || $points    !== $previous_points
                    || $secondary !== $previous_secondary) {
                $rank = $position;
            }
            $previous_points    = $points;
            $previous_secondary = $secondary;

            $userid = (int) $r->userid;
            $DB->insert_record('local_sentientia_lb_entries', (object) [
                'boardid'        => $boardid,
                'userid'         => $userid,
                'points'         => $points,
                'secondary'      => $secondary,
                'userrank'       => $rank,
                'costcenterid'   => (int) $r->costcenterid,
                'last_recomputed' => $now,
            ]);
            $new_ranks[$userid] = $rank;
        }
        return $new_ranks;
    }

    /**
     * Read top-N entries for a board, filtered by the opt-out table.
     *
     * @param int  $boardid
     * @param int  $top_n     1..200
     * @param bool $can_view_all If true, opt-outs are NOT filtered (HR view).
     * @param int  $customerid  Defaults to 1 (Airpay).
     * @return array{rows: array, total: int, optout_total: int}
     */
    public static function read_top(int $boardid, int $top_n = 10,
                                      bool $can_view_all = false,
                                      int $customerid = 1): array {
        global $DB;
        $top_n = max(1, min(200, $top_n));

        $where = ['e.boardid = :bid'];
        $params = ['bid' => $boardid];

        // Filter opted-out users at SQL time when the caller is NOT a
        // privileged viewer. Use NOT EXISTS so we never load the optout
        // table into memory for a learner-facing read.
        if (!$can_view_all) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM {local_sentientia_lb_optouts} o
                                     WHERE o.userid = e.userid
                                       AND o.customerid = :cust)';
            $params['cust'] = $customerid;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_lb_entries} e WHERE $wheresql",
            $params);

        $rows = $DB->get_records_sql("
            SELECT e.id, e.userid, e.points, e.secondary, e.userrank,
                   u.firstname, u.lastname, u.email, u.picture, u.imagealt
              FROM {local_sentientia_lb_entries} e
              JOIN {user} u ON u.id = e.userid
             WHERE $wheresql
          ORDER BY e.userrank ASC, e.userid ASC",
            $params, 0, $top_n);

        $optout_total = $can_view_all ? 0 : (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_lb_entries} e
              WHERE e.boardid = :bid
                AND EXISTS (SELECT 1 FROM {local_sentientia_lb_optouts} o
                             WHERE o.userid = e.userid
                               AND o.customerid = :cust)",
            ['bid' => $boardid, 'cust' => $customerid]);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'rank'      => (int) $r->userrank,
                'userid'    => (int) $r->userid,
                'fullname'  => fullname((object) [
                    'firstname' => $r->firstname,
                    'lastname'  => $r->lastname,
                ]),
                'points'    => (int) $r->points,
                'secondary' => (int) $r->secondary,
            ];
        }

        return [
            'rows'         => $out,
            'total'        => $total,
            'optout_total' => $optout_total,
        ];
    }

    /**
     * Get the viewer's own entry on a board (for "your rank: N" UI).
     * Returns null if the viewer is opted-out or has no row.
     */
    public static function read_my_rank(int $boardid, int $userid,
                                          int $customerid = 1): ?array {
        global $DB;
        if (optout_manager::is_opted_out($userid, $customerid)) {
            return null;
        }
        $row = $DB->get_record('local_sentientia_lb_entries', [
            'boardid' => $boardid,
            'userid'  => $userid,
        ]);
        if (!$row) {
            return null;
        }
        return [
            'rank'      => (int) $row->userrank,
            'userid'    => $userid,
            'points'    => (int) $row->points,
            'secondary' => (int) $row->secondary,
        ];
    }
}
