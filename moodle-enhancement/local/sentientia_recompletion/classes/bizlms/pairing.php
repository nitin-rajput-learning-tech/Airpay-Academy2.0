<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Which reset ended which archived cycle of one learner in one course (mapping doc, section 12).
 *
 * The legacy plugin archived a course_completions row each time it reset one, and fired a completion_reset
 * event, so a (learner, course) pair has archived completions and log rows of resets, and the two lists meet
 * only by time. The rule: each archived completion belongs to the earliest unmatched reset at or after the
 * time the cycle ran from (its completion, or for a cycle never completed its start or enrolment).
 *
 * The cycles are taken in the order of their archived row's id, NOT in the order of the time they ran from.
 * The legacy plugin inserted the archived row at the moment of the reset, so id order is reset order. Time is
 * not reliable for that: core's completion cron recreates the completion row after a reset with timeenrolled
 * set to the ORIGINAL enrolment and timestarted 0 until the learner does something, so a later cycle that was
 * reset without ever being started "ran from" a date before the first cycle even completed, sorted ahead of
 * it, and was left with no reset at all. Because the cycles are in chronological order, a cycle can never run
 * from earlier than the cycle before it did, which is what the running maximum of the lower bound says.
 *
 * A refinement exists for a purged log, and it is used ONLY when the rule above leaves a cycle without a reset
 * (a reset really is missing, so something has to say which cycle it belonged to). A reset later than the
 * START of the next cycle cannot be the one that ended this cycle, because the next cycle's row was created by
 * the reset that ended this one; so a surviving later reset is not handed to the earlier cycle. Two limits keep
 * the refinement honest, both because core's cron rebuilds a row after a reset with OLD times:
 *
 * - It looks at the next cycle's start only, never at its completion. The cron re-marks the criteria that carry
 *   a fixed date (a course end date, a kept grade, a prerequisite course) complete with their old times, and
 *   completes the course at the latest of them, so a rebuilt row can be "completed" long before it was rebuilt.
 * - It uses that start only when it is later than the time this cycle ran from. The same cron stamps the start
 *   with the criterion's old time too (completion_criteria_completion::mark_complete calls mark_inprogress with
 *   it), and a start that is not after this cycle's own completion cannot be the next cycle's real beginning.
 *
 * Some old start dates still look real (a kept grade changed after the completion). That is why the refinement
 * is not used at all when every cycle has a reset without it: nothing is missing, so a backdated start must not
 * cost a cycle its reset, which would invent a reset for it and hand its real one to the cycle after. When the
 * next cycle has no usable start, a lone surviving reset goes to the earlier cycle: the data cannot say
 * otherwise, and the importer reports such a pair (course_completion_step, warning reset_pairing_unclear).
 *
 * The function is pure, so the step that imports the log rows and the step that imports the archived
 * completions reach the same answer without talking to each other.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pairing {

    /**
     * Pair the archived completions and the resets of one learner in one course.
     *
     * @param array<array{id: int, completed: int, started: int, enrolled: int}> $completions Zero means unknown.
     * @param array<array{id: int, time: int}> $resets
     * @return array{cc: array<int, int|null>, event: array<int, int|null>} completion id => reset id (or null),
     *         reset id => completion id (or null)
     */
    public static function pair(array $completions, array $resets): array {
        usort($completions, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
        $cycles = [];
        $floor = 0;
        foreach ($completions as $completion) {
            $own = $completion['completed'] > 0 ? $completion['completed']
                : ($completion['started'] > 0 ? $completion['started'] : $completion['enrolled']);
            // A cycle begins no earlier than the one before it did.
            $floor = max($floor, $own);
            $cycles[] = $completion + ['lower' => $floor];
        }
        usort($resets, static fn(array $a, array $b): int => [$a['time'], $a['id']] <=> [$b['time'], $b['id']]);
        $resets = array_values($resets);

        // First without the refinement: if that gives every cycle a reset, no reset is missing.
        $plain = self::walk($cycles, $resets, false);
        if (!in_array(null, $plain['cc'], true)) {
            return $plain;
        }
        return self::walk($cycles, $resets, true);
    }

    /**
     * One pass over the cycles in id order, each taking the earliest unmatched reset it can.
     *
     * @param array<array{id: int, completed: int, started: int, enrolled: int, lower: int}> $cycles In id order.
     * @param array<array{id: int, time: int}> $resets In time order.
     * @param bool $capped Stop a cycle at the start of the cycle after it (see the class comment).
     * @return array{cc: array<int, int|null>, event: array<int, int|null>}
     */
    private static function walk(array $cycles, array $resets, bool $capped): array {
        $result = ['cc' => [], 'event' => []];
        foreach ($resets as $reset) {
            $result['event'][$reset['id']] = null;
        }

        $count = count($resets);
        $next = 0;
        foreach ($cycles as $position => $cycle) {
            $result['cc'][$cycle['id']] = null;

            // The start of the following cycle, when there is one and it is later than this cycle ran from.
            $upper = PHP_INT_MAX;
            if ($capped && isset($cycles[$position + 1])) {
                $started = $cycles[$position + 1]['started'];
                if ($started > $cycle['lower']) {
                    $upper = $started;
                }
            }

            while ($next < $count && $resets[$next]['time'] < $cycle['lower']) {
                $next++;
            }
            if ($next < $count && $resets[$next]['time'] <= $upper) {
                $result['cc'][$cycle['id']] = $resets[$next]['id'];
                $result['event'][$resets[$next]['id']] = $cycle['id'];
                $next++;
            }
        }
        return $result;
    }
}
