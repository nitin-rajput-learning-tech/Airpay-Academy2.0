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
 * time worked out from the completion (see mapper::inferred_time) and never later than the import.
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
        $now = time();
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

            [$duration, $fallback] = $evidence->duration($course);
            $next = $evidence->next_evidence($user, $course, $id, $ranfrom ?? 0);
            $time = mapper::inferred_time($completed, $duration, $next, $now);
            $config = $evidence->config($course);
            $attempted = $evidence->attempted_between($user, $course, $evidence->reset_before($user, $course, $time), $time);
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
            if ($fallback) {
                $inferred->warn('duration_fallback');
            }
            $out[] = $inferred;
        }
        return $out;
    }
}
