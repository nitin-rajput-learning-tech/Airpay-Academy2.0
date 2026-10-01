<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\recompute_step;

/**
 * Give a history row whose reset time was inferred the real time once the log holds the reset.
 *
 * A cycle whose reset was missing from the log gets an inferred history row (time_inferred = 1). If a later
 * run finds the log row, the events step folds it into that row instead of adding a second one, and this step
 * gives the row the log row's time, reason and actor and takes the mark off. It works forward only: from the
 * inferred row to the learner's archived completions, to the reset the pairing now gives each, and to whether
 * the map says that completion's inferred row is this one.
 *
 * It is idempotent: a row that is already real is not selected, and an inferred row whose cycle still has no
 * reset in the log is left as it is.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class inferred_resets extends recompute_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.inferred_resets';
    }

    /**
     * {@inheritdoc}
     */
    public function targettable(): string {
        return sources::HISTORY;
    }

    /**
     * {@inheritdoc}
     */
    public function recompute(array $targetids, context $ctx): array {
        global $DB;
        if (!$targetids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'blmhist');
        $params['blmsource'] = sources::LEGACY;
        $rows = $DB->get_records_select(sources::HISTORY, "id $insql AND time_inferred = 1 AND source = :blmsource",
            $params, '', 'id, userid, courseid');

        $evidence = evidence::of($ctx);
        $out = [];
        foreach ($rows as $row) {
            $user = (int) $row->userid;
            $course = (int) $row->courseid;
            foreach ($evidence->pairing($user, $course)['cc'] as $ccid => $eventid) {
                if ($eventid === null || $ctx->map->resolve(sources::CC, (int) $ccid, 'history') !== (int) $row->id) {
                    continue;
                }
                $reset = $evidence->reset_row($user, $course, $eventid);
                if ($reset === null) {
                    continue;
                }
                [$reason, $resetby] = mapper::reset_source($reset['origin'], $reset['actor'], $user);
                $out[] = outcome::update(sources::HISTORY, (int) $row->id, (object) [
                    'reason' => $reason,
                    'reset_by_userid' => $resetby,
                    'timecreated' => $reset['time'],
                    'time_inferred' => 0,
                ]);
                break;
            }
        }
        return $out;
    }
}
