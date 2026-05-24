<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds and dispatches the rank-change Moodle messages introduced in
 * Phase L.1 (2026-05-24).
 *
 * The class is intentionally side-effect-light at the static call boundary
 * — every public method either returns a verdict (`should_notify`,
 * `is_throttled`) or performs ONE atomic side effect (`record_notification`,
 * `send_one`). The orchestration loop lives in {@see dispatch()} so the
 * observer stays as thin as the rest of the airpay_* observer family.
 *
 * Throttle invariant
 * ------------------
 * `local_sentientia_lb_notify_log` holds at most one row per
 * (boardid, userid, customerid). The row's `last_sent` is updated on every
 * dispatched message. `is_throttled()` compares `time() - last_sent`
 * against THROTTLE_SECONDS (24h). The unique key on the table makes
 * concurrent recompute paths safe — the second writer falls into the
 * existing-row branch.
 *
 * @package local_sentientia_leaderboard
 */
class message_helper {

    /** Master feature flag — must be ON for any message to be sent. */
    public const FLAG_KEY = 'sentientia.leaderboards.notifications.enabled';

    /** Throttle window per (board, user). 24 hours. */
    public const THROTTLE_SECONDS = 86400;

    /** Minimum |old_rank - new_rank| to qualify as a "large move". */
    public const LARGE_MOVE_THRESHOLD = 5;

    /** Top-N gate for the "fresh entry" rule. */
    public const TOP_N_GATE = 10;

    /** Hard cap on changes processed per dispatch — matches the event payload cap. */
    public const MAX_CHANGES_PER_DISPATCH = 500;

    /** Reason strings — duplicated in the event payload + the log row. */
    public const REASON_TOP10_ENTRY = 'top10_entry';
    public const REASON_LARGE_MOVE  = 'large_move';

    /**
     * Decide which rank shifts deserve a Moodle message.
     *
     * Both rules combined fire when applicable; the predicate returns
     * the FIRST matching reason (top-10 entry beats large-move because
     * "you cracked the top 10" is the more meaningful celebration).
     *
     * @param int $old_rank 0 when the user had no prior entry on the board.
     * @param int $new_rank Strictly positive.
     * @return string|null One of the REASON_* constants, or null when no rule fires.
     */
    public static function classify_change(int $old_rank, int $new_rank): ?string {
        if ($new_rank <= 0) {
            return null;
        }
        // Fresh top-10 entry: user enters the top N from outside (or had
        // no previous row at all).
        $was_outside_topn = ($old_rank === 0) || ($old_rank > self::TOP_N_GATE);
        if ($new_rank <= self::TOP_N_GATE && $was_outside_topn) {
            return self::REASON_TOP10_ENTRY;
        }
        // Large move: at least LARGE_MOVE_THRESHOLD positions, in either
        // direction. Requires a prior rank (no movement is observable for
        // a brand-new ranked user).
        if ($old_rank > 0
                && abs($new_rank - $old_rank) >= self::LARGE_MOVE_THRESHOLD) {
            return self::REASON_LARGE_MOVE;
        }
        return null;
    }

    /**
     * Compute the deltas to ship in the event payload from before/after
     * rank maps. Caller passes the pre-recompute snapshot + the freshly
     * populated map; the function emits only the changes worth notifying.
     *
     * Result is bounded by {@see MAX_CHANGES_PER_DISPATCH} sorted by
     * absolute rank-move DESC, then by new_rank ASC so the most dramatic
     * moves survive truncation.
     *
     * @param array<int,int> $old_ranks Map of userid => prior rank
     * @param array<int,int> $new_ranks Map of userid => fresh rank
     * @return list<array{userid:int, old_rank:int, new_rank:int, reason:string}>
     */
    public static function compute_changes(array $old_ranks, array $new_ranks): array {
        $changes = [];
        foreach ($new_ranks as $userid => $new_rank) {
            $old_rank = (int) ($old_ranks[$userid] ?? 0);
            $new_rank = (int) $new_rank;
            $reason = self::classify_change($old_rank, $new_rank);
            if ($reason === null) {
                continue;
            }
            $changes[] = [
                'userid'   => (int) $userid,
                'old_rank' => $old_rank,
                'new_rank' => $new_rank,
                'reason'   => $reason,
            ];
        }
        if (count($changes) > self::MAX_CHANGES_PER_DISPATCH) {
            usort($changes, function($a, $b) {
                // top-10 entries trump large moves, then by absolute
                // move size, then by new_rank ASC (better ranks first).
                $ta = ($a['reason'] === self::REASON_TOP10_ENTRY) ? 1 : 0;
                $tb = ($b['reason'] === self::REASON_TOP10_ENTRY) ? 1 : 0;
                if ($ta !== $tb) {
                    return $tb - $ta;
                }
                $da = $a['old_rank'] > 0 ? abs($a['new_rank'] - $a['old_rank']) : 0;
                $db = $b['old_rank'] > 0 ? abs($b['new_rank'] - $b['old_rank']) : 0;
                if ($da !== $db) {
                    return $db - $da;
                }
                return $a['new_rank'] - $b['new_rank'];
            });
            $changes = array_slice($changes, 0, self::MAX_CHANGES_PER_DISPATCH);
        }
        return $changes;
    }

    /**
     * Top-level dispatch — called from the observer. Iterates the change
     * set, filters by throttle, sends a message, records the dispatch.
     *
     * Returns the count of messages actually sent (post-throttle filter).
     * Errors during a single send are caught + debugging()'d so one bad
     * user doesn't blow up the rest of the batch.
     */
    public static function dispatch(int $boardid, array $changes): int {
        global $DB;
        if ($boardid <= 0 || empty($changes)) {
            return 0;
        }
        $board = $DB->get_record('local_sentientia_lb_boards',
            ['id' => $boardid]);
        if (!$board) {
            return 0;
        }
        $customerid = (int) ($board->customerid ?: 1);
        $sent = 0;

        foreach ($changes as $change) {
            $userid   = (int) ($change['userid']   ?? 0);
            $old_rank = (int) ($change['old_rank'] ?? 0);
            $new_rank = (int) ($change['new_rank'] ?? 0);
            $reason   = (string) ($change['reason'] ?? '');

            if ($userid <= 0 || $new_rank <= 0 || $reason === '') {
                continue;
            }
            // Re-check classification at dispatch time — defends against
            // a stale payload that no longer satisfies the rule.
            if (self::classify_change($old_rank, $new_rank) === null) {
                continue;
            }
            // Privacy: opted-out users get no rank-change notifications.
            if (optout_manager::is_opted_out($userid, $customerid)) {
                continue;
            }
            if (self::is_throttled($boardid, $userid, $customerid)) {
                continue;
            }
            try {
                self::send_one($board, $userid, $old_rank, $new_rank, $reason);
                self::record_notification($boardid, $userid, $customerid,
                    $old_rank, $new_rank, $reason);
                $sent++;
            } catch (\Throwable $e) {
                debugging('local_sentientia_leaderboard message_helper: '
                    . 'send failed for board ' . $boardid . ' user '
                    . $userid . ' — ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }

        return $sent;
    }

    /**
     * Throttle check. Returns true when the (board, user, customer) row
     * has fired within THROTTLE_SECONDS.
     */
    public static function is_throttled(int $boardid, int $userid,
                                          int $customerid = 1): bool {
        global $DB;
        $row = $DB->get_record('local_sentientia_lb_notify_log', [
            'boardid'    => $boardid,
            'userid'     => $userid,
            'customerid' => $customerid,
        ]);
        if (!$row) {
            return false;
        }
        return (time() - (int) $row->last_sent) < self::THROTTLE_SECONDS;
    }

    /**
     * Upsert the throttle row after a successful send. The unique key
     * (boardid, userid, customerid) means we can safely fetch-then-update
     * or insert without an explicit transaction — a concurrent inserter
     * would lose to the UK and the update path runs instead.
     */
    public static function record_notification(int $boardid, int $userid,
                                                 int $customerid,
                                                 int $old_rank, int $new_rank,
                                                 string $reason): void {
        global $DB;
        $now = time();
        $existing = $DB->get_record('local_sentientia_lb_notify_log', [
            'boardid'    => $boardid,
            'userid'     => $userid,
            'customerid' => $customerid,
        ]);
        if ($existing) {
            $existing->last_sent     = $now;
            $existing->last_old_rank = $old_rank;
            $existing->last_new_rank = $new_rank;
            $existing->last_reason   = $reason;
            $existing->timemodified  = $now;
            $DB->update_record('local_sentientia_lb_notify_log', $existing);
            return;
        }
        try {
            $DB->insert_record('local_sentientia_lb_notify_log', (object) [
                'boardid'       => $boardid,
                'userid'        => $userid,
                'customerid'    => $customerid,
                'last_sent'     => $now,
                'last_old_rank' => $old_rank,
                'last_new_rank' => $new_rank,
                'last_reason'   => $reason,
                'timecreated'   => $now,
                'timemodified'  => $now,
            ]);
        } catch (\dml_write_exception $e) {
            // Race: another worker beat us to the insert. Fall through to
            // an update of the row that now exists.
            $row = $DB->get_record('local_sentientia_lb_notify_log', [
                'boardid'    => $boardid,
                'userid'     => $userid,
                'customerid' => $customerid,
            ]);
            if ($row) {
                $row->last_sent     = $now;
                $row->last_old_rank = $old_rank;
                $row->last_new_rank = $new_rank;
                $row->last_reason   = $reason;
                $row->timemodified  = $now;
                $DB->update_record('local_sentientia_lb_notify_log', $row);
            }
        }
    }

    /**
     * Build + send a single Moodle message via {@see message_send()}.
     *
     * Subject + body templates are language-strings so Hindi parity is
     * automatic. Three template variants:
     *   - top10_entry          — "You cracked the top 10!"
     *   - large_move (up)      — "You moved up N positions on …"
     *   - large_move (down)    — "You dropped N positions on …"
     */
    public static function send_one(\stdClass $board, int $userid,
                                      int $old_rank, int $new_rank,
                                      string $reason): void {
        $boardname = format_string($board->name);
        $a = (object) [
            'boardname' => $boardname,
            'old_rank'  => $old_rank,
            'new_rank'  => $new_rank,
            'delta'     => $old_rank > 0 ? abs($new_rank - $old_rank) : 0,
        ];

        if ($reason === self::REASON_TOP10_ENTRY) {
            $subject = get_string('msg_top10_subject',
                'local_sentientia_leaderboard', $a);
            $body_plain = get_string('msg_top10_body',
                'local_sentientia_leaderboard', $a);
        } else if ($new_rank < $old_rank) {
            // Lower number = better rank → moved up.
            $subject = get_string('msg_moveup_subject',
                'local_sentientia_leaderboard', $a);
            $body_plain = get_string('msg_moveup_body',
                'local_sentientia_leaderboard', $a);
        } else {
            $subject = get_string('msg_movedown_subject',
                'local_sentientia_leaderboard', $a);
            $body_plain = get_string('msg_movedown_body',
                'local_sentientia_leaderboard', $a);
        }
        $body_html = '<p>' . s($body_plain) . '</p>';

        $eventdata = new \core\message\message();
        $eventdata->component         = 'local_sentientia_leaderboard';
        $eventdata->name              = 'rank_change';
        $eventdata->userfrom          = \core_user::get_noreply_user();
        $eventdata->userto            = $userid;
        $eventdata->subject           = $subject;
        $eventdata->fullmessage       = $body_plain;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = $body_html;
        $eventdata->smallmessage      = $subject;
        $eventdata->notification      = 1;
        $eventdata->contexturl        = (new \moodle_url(
            '/local/sentientia_leaderboard/view.php',
            ['id' => (int) $board->id]
        ))->out(false);
        $eventdata->contexturlname    = $boardname;

        message_send($eventdata);
    }
}
