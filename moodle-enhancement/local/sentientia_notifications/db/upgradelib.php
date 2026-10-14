<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_notifications.
 *
 * @package   local_sentientia_notifications
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Remove the 'sending' claim rows that rule_engine::send() left next to the
 * row that records the same delivery.
 *
 * Before 2026100900 send() inserted a claim row (status 'sending', no
 * message) and then inserted a SECOND row for the outcome, so every delivery
 * left two rows and the claim stayed 'sending' for good. send() now updates
 * the claim row instead; this folds the history it already wrote.
 *
 * A claim row is removed only when the outcome row of the same delivery
 * exists. That is: a row with the same rule, user, course (NULL counts as 0,
 * which is how the old outcome row stored "no course"), channel and subject,
 * written after the claim by at most an hour (the outcome insert followed the
 * claim within the same call), carrying a message, with status 'sent' or
 * 'read' (mark_read turns a 'sent' row into 'read'). The 24-hour duplicate
 * window means no second delivery of the same rule + user + course can start
 * within that hour, so the match cannot cross two deliveries.
 *
 * A claim row WITHOUT such a row is kept untouched. Under the old code the
 * outcome row was inserted after the preference checks and before the message
 * was sent, so a claim with no outcome row is a delivery that never reached
 * the messaging system: almost always one the recipient's preferences
 * suppressed (channel off, rule type opted out, quiet hours), otherwise one
 * interrupted between the claim and the outcome insert. The data cannot say
 * which, and "suppressed" or "failed" would be a guess, so the row stays as
 * it is. Nothing new joins them: send() now records 'suppressed' or 'failed'
 * itself.
 *
 * Deleting a claim row would shorten send()'s 24-hour duplicate window for a
 * delivery whose outcome row has a NULL courseid (the old outcome row stored
 * NULL where the claim stored 0, and the window compares courseid = 0). For a
 * claim young enough to still matter, the outcome row's courseid is set to 0
 * first so the window holds; older claims no longer take part in it.
 *
 * Idempotent (a second run finds no claim with an outcome row) and safe to
 * interrupt (each batch is one DELETE). It walks the claim rows once in id
 * order, `$batch` at a time, and looks the outcome row up through
 * idx_ruleid_userid, so the cost is one pass over the table.
 *
 * Used by upgrade step 2026100900; a function so
 * tests/notif_log_claim_rows_test.php can prove it without replaying the
 * whole upgrade.
 *
 * @param int|null $now Current time; tests pass a fixed one.
 * @param int $batch Claim rows examined per query.
 * @return array{removed: int, kept: int} claim rows deleted; claim rows left
 *         because no outcome row matched.
 */
function local_sentientia_notifications_fold_claim_rows(?int $now = null, int $batch = 500): array {
    global $DB;

    $now = $now ?? time();
    // send()'s duplicate window is 24 hours; a claim younger than this can still be inside one.
    $youngerthan = $now - 2 * DAYSECS;
    $batch = max(1, $batch);

    $removed = 0;
    $kept = 0;
    $lastid = 0;
    do {
        \core_php_time_limit::raise(300);
        $claims = $DB->get_records_select('local_sentientia_notif_log',
            'id > :lastid AND status = :sending AND message IS NULL',
            ['lastid' => $lastid, 'sending' => 'sending'], 'id ASC',
            'id, ruleid, userid, courseid, channel, subject, timecreated', 0, $batch);

        $drop = [];
        foreach ($claims as $claim) {
            $lastid = (int) $claim->id;
            $outcomes = $DB->get_records_select('local_sentientia_notif_log',
                'id > :claimid AND ruleid = :ruleid AND userid = :userid AND COALESCE(courseid, 0) = :courseid
                 AND channel = :channel AND subject = :subject AND message IS NOT NULL
                 AND status IN (:stsent, :stread) AND timecreated >= :tstart AND timecreated <= :tend',
                [
                    'claimid'  => $claim->id,
                    'ruleid'   => $claim->ruleid,
                    'userid'   => $claim->userid,
                    'courseid' => (int) $claim->courseid,
                    'channel'  => $claim->channel,
                    'subject'  => $claim->subject,
                    'stsent'   => 'sent',
                    'stread'   => 'read',
                    'tstart'   => $claim->timecreated,
                    'tend'     => $claim->timecreated + HOURSECS,
                ], 'id ASC', 'id, courseid', 0, 1);
            if (!$outcomes) {
                $kept++;
                continue;
            }
            $outcome = reset($outcomes);
            if ($outcome->courseid === null && $claim->timecreated > $youngerthan) {
                $DB->set_field('local_sentientia_notif_log', 'courseid', 0, ['id' => $outcome->id]);
            }
            $drop[] = (int) $claim->id;
        }

        if ($drop) {
            $DB->delete_records_list('local_sentientia_notif_log', 'id', $drop);
            $removed += count($drop);
        }
    } while (count($claims) === $batch);

    return ['removed' => $removed, 'kept' => $kept];
}
