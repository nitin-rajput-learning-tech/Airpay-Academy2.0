<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\recompute_step;

/**
 * Attach every archived row to the reset that ended its cycle.
 *
 * The legacy plugin archived a learner's rows at the moment it reset them, so a row belongs to the EARLIEST
 * reset of that learner in that course at or after the row's own time (strictly after, for a reset whose time was
 * inferred: that time is capped at the next cycle's first evidence, which belongs to the next cycle; see
 * evidence::ends_cycle_of). That needs every history row to exist,
 * so it is done here, after the load steps, and the archive row's historyid (and its "archived at", which is
 * that reset's time) are set. A row with no time, an anonymous row, and a row with no reset at or after it
 * (the log row was purged and no completion was archived to infer one from) keep historyid 0: the cycle is
 * not guessed.
 *
 * Only history rows the import wrote (source legacy) count as resets, so a reset the Sentientia engine makes
 * after cutover is never taken for the end of a BizLMS cycle.
 *
 * It is idempotent: it computes the same answer every time and only writes a row whose answer changed.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class archive_cycles extends recompute_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.archive_cycles';
    }

    /**
     * {@inheritdoc}
     */
    public function targettable(): string {
        return sources::ARCHIVE;
    }

    /**
     * {@inheritdoc}
     */
    public function recompute(array $targetids, context $ctx): array {
        global $DB;
        if (!$targetids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'blmarc');
        $rows = $DB->get_records_select(sources::ARCHIVE, "id $insql", $params, '',
            'id, historyid, userid, courseid, timeevent, timecreated');

        $users = [];
        $courses = [];
        foreach ($rows as $row) {
            if ((int) $row->userid > 0 && $row->timeevent !== null) {
                $users[(int) $row->userid] = true;
                $courses[(int) $row->courseid] = true;
            }
        }
        // The resets of the learners and courses in this batch, by pair. A pair the batch does not use is read
        // too (users x courses), and left unused.
        $resets = [];
        if ($users) {
            [$usql, $uparams] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'blmusr');
            [$csql, $cparams] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'blmcrs');
            $history = $DB->get_records_select(sources::HISTORY,
                "userid $usql AND courseid $csql AND source = :blmsource AND dryrun = 0",
                $uparams + $cparams + ['blmsource' => sources::LEGACY], 'timecreated ASC, id ASC',
                'id, userid, courseid, timecreated, time_inferred');
            foreach ($history as $reset) {
                $resets[mapper::pair_key((int) $reset->userid, (int) $reset->courseid)][] = [
                    'id' => (int) $reset->id, 'time' => (int) $reset->timecreated,
                    'inferred' => (int) $reset->time_inferred === 1,
                ];
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $historyid = 0;
            $archived = (int) $row->timecreated;
            if ((int) $row->userid > 0 && $row->timeevent !== null) {
                // The lists are ordered by time then id, so the first one at or after the row is the earliest.
                foreach ($resets[mapper::pair_key((int) $row->userid, (int) $row->courseid)] ?? [] as $reset) {
                    if (evidence::ends_cycle_of($reset, (int) $row->timeevent)) {
                        $historyid = $reset['id'];
                        $archived = $reset['time'];
                        break;
                    }
                }
            }
            if ($historyid !== (int) $row->historyid || $archived !== (int) $row->timecreated) {
                $out[] = outcome::update(sources::ARCHIVE, (int) $row->id, (object) [
                    'historyid' => $historyid,
                    'timecreated' => $archived,
                ]);
            }
        }
        return $out;
    }
}
