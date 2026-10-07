<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\legacymap;
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
 * The archived course completions are the exception: each takes the reset the pairing gives it (see
 * ended_by_pairing), because their dates are not always the cycle's own.
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
            'id, historyid, itemtype, userid, courseid, timeevent, timecreated');

        // An archived completion is attached through the pairing, not by time (see ended_by_pairing).
        $paired = $this->ended_by_pairing($rows, $ctx);

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
            // "Archived at" is the reset's time when a reset is known and the row's own time when it is not, so a
            // row that loses its reset (an inferred one moved earlier) does not keep the old reset's time.
            $archived = $row->timeevent !== null ? (int) $row->timeevent : 0;
            if (isset($paired[(int) $row->id])) {
                $historyid = $paired[(int) $row->id]['id'];
                $archived = $paired[(int) $row->id]['time'];
            } else if ((int) $row->userid > 0 && $row->timeevent !== null) {
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

    /**
     * The reset that ended each archived completion of a batch, from the pairing.
     *
     * A completion's own dates cannot always say which reset ended it. Core recreates the completion row after a
     * reset with the ORIGINAL enrolment date, so a cycle that was never started "ran from" a date before the
     * cycle ahead of it, and by time it would be handed that cycle's reset. The pairing (evidence::pairing) takes
     * the cycles in the legacy row's id order, which is reset order, and has no such problem; so an archived
     * completion takes the history row of the reset it is paired with, or the inferred row made for it. Every
     * other archived row has real dates of its own and keeps the rule by time.
     *
     * @param \stdClass[] $rows Archive rows (id, itemtype, userid, courseid).
     * @param context $ctx
     * @return array<int, array{id: int, time: int}> Archive row id => its history row and that row's time. A row
     *         the pairing does not answer for is missing, and the caller falls back to time.
     */
    private function ended_by_pairing(array $rows, context $ctx): array {
        global $DB;
        $ids = [];
        foreach ($rows as $row) {
            if ((string) $row->itemtype === mapper::COURSE_COMPLETION && (int) $row->userid > 0) {
                $ids[] = (int) $row->id;
            }
        }
        if (!$ids) {
            return [];
        }
        // Which legacy completion each archive row came from: the map is the only record of it.
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'blmcc');
        $params += ['blmsource' => sources::CC, 'blmtarget' => sources::ARCHIVE, 'blmsubkey' => ''];
        $sources = $DB->get_records_sql_menu(
            'SELECT targetid, sourceid FROM {' . legacymap::TABLE . '}
              WHERE sourcetable = :blmsource AND targettable = :blmtarget AND subkey = :blmsubkey AND targetid ' . $insql,
            $params);

        $evidence = evidence::of($ctx);
        $wanted = [];
        foreach ($rows as $row) {
            $ccid = (int) ($sources[(int) $row->id] ?? 0);
            if ($ccid <= 0) {
                continue;
            }
            $eventid = $evidence->pairing((int) $row->userid, (int) $row->courseid)['cc'][$ccid] ?? null;
            $historyid = $eventid !== null
                ? $ctx->map->resolve(sources::EVENT_UNIT, $eventid)
                : $ctx->map->resolve(sources::CC, $ccid, 'history');
            if ($historyid !== null) {
                $wanted[(int) $row->id] = (int) $historyid;
            }
        }
        $times = $wanted ? $DB->get_records_list(sources::HISTORY, 'id', array_values(array_unique($wanted)), '',
            'id, timecreated') : [];
        $out = [];
        foreach ($wanted as $archiveid => $historyid) {
            if (isset($times[$historyid])) {
                $out[$archiveid] = ['id' => $historyid, 'time' => (int) $times[$historyid]->timecreated];
            }
        }
        return $out;
    }
}
