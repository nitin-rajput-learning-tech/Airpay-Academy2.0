<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * An archived course completion (what the legacy plugin copied out of course_completions before it reset one).
 *
 * Each row becomes an evidence row of type course_completion. The reset that ended the cycle is usually in the
 * log and then already imported by the events step. When it is not (the log row was purged), the cycle would
 * leave no history row at all, and the only surviving evidence that the person completed and was then reset
 * would be a payload nobody reads; so the row also gets an INFERRED history row, marked as such, with a reset
 * time worked out from the completion (see mapper::inferred_time) and never later than the import, nor earlier
 * than the end of the cycle before it (evidence::floor_before). The import time is never the value: when the
 * completion plus the period and the next cycle's evidence give no time before the import, the reset is dated one
 * second after the cycle's latest evidence and the row carries the warning inferred_reset_from_last_evidence. When
 * that completion or evidence is itself at or after the import (a completion dated in the future, or a learner active
 * during the cutover) the only time the data allows is the import time, so the row also carries the warning
 * evidence_at_or_after_import and is listed in the report.
 * When the same learner and course also have a
 * logged reset that fits no cycle, the row carries the warning reset_pairing_unclear: the pairing cannot tell
 * which cycle that reset ended (see pairing), so the report counts the pairs the owner may want to look at.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_completion_step extends archive_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.cc';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::CC;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $rows, context $ctx): array {
        $evidence = evidence::of($ctx);
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $user = (int) $row->userid;
            $course = (int) $row->course;
            if (!self::known_user($ctx, $user)) {
                $out[] = self::orphan_user($id);
                continue;
            }

            $completed = mapper::timestamp($row->timecompleted);
            $started = mapper::timestamp($row->timestarted);
            $enrolled = mapper::timestamp($row->timeenrolled);
            // When the cycle ran from: its completion, or for one never completed its start or its enrolment.
            $ranfrom = $completed ?? $started ?? $enrolled;

            $out[] = outcome::insert($id, sources::ARCHIVE, self::archive_row($ctx, $row, $user, $course,
                mapper::COURSE_COMPLETION, [
                    'state' => $completed !== null ? 'complete' : 'incomplete',
                    'timeevent' => $ranfrom,
                ]));

            $pair = $evidence->pairing($user, $course);
            if (($pair['cc'][$id] ?? null) !== null) {
                // The log holds the reset: the events step has imported it.
                continue;
            }

            // The evidence worked this out once for the run, because the next cycle's inferred time needs this
            // one's end as its floor.
            [$time, $fallback, $fromevidence, $atimport] = $evidence->inferred_reset($user, $course, $id);
            $config = $evidence->config($course);
            // The window of this cycle starts where the one before it ended, logged or inferred.
            $since = max($evidence->reset_before($user, $course, $time), $evidence->floor_before($user, $course, $id));
            $attempted = $evidence->attempted_between($user, $course, $since, $time);
            $inferred = outcome::insert($id, sources::HISTORY, (object) [
                'ruleid' => (int) ($ctx->map->resolve(sources::RULE_UNIT, $course) ?? 0),
                'userid' => $user,
                'courseid' => $course,
                'reason' => 'legacy',
                'reset_by_userid' => null,
                'previous_timecompleted' => $completed,
                'reset_grades' => mapper::switch_on($config['deletegradedata'] ?? null),
                'reset_attempts' => $attempted ? 1 : mapper::switch_on($config['quiz'] ?? null),
                'dryrun' => 0,
                'timecreated' => $time,
                'source' => sources::LEGACY,
                'time_inferred' => 1,
            ], 'history');
            $inferred->warn('derived_timestamp');
            if ($fromevidence) {
                // Neither the completion plus the period nor the next cycle's evidence gave a time before the
                // import: the reset is dated one second after the cycle's last evidence, the earliest moment the
                // data allows (owner decision recompletion.inferred_reset_without_evidence). Never the import time.
                $inferred->warn('inferred_reset_from_last_evidence');
            }
            if ($atimport) {
                // The cycle's completion or latest evidence is at or after the import (a completion dated in the future, or
                // a learner active during the cutover): there is no second after it yet, so the only time the data allows
                // IS the import time. The decision says that is never the value; the row is listed so the owner sees it.
                $inferred->warn('evidence_at_or_after_import');
            }
            if (in_array(null, $pair['event'], true)) {
                // This cycle has no reset in the log while a logged reset of the same learner and course fits no
                // cycle at all (a purged log row, or archiving switched off for a while). Which cycle that reset
                // really ended cannot be told from the data, so the owner is shown the pair in the report.
                $inferred->warn('reset_pairing_unclear');
            }
            if ($fallback) {
                $inferred->warn('duration_fallback');
            }
            $out[] = $inferred;
        }
        return $out;
    }
}
