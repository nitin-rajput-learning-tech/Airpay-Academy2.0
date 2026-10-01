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
 * time the cycle ran from (its completion, or for a cycle never completed its start or enrolment). One
 * refinement: a reset later than the first evidence of the NEXT cycle (that cycle's start or completion)
 * cannot be the one that ended this cycle, so a purged log row leaves this cycle without a reset instead of
 * handing it the next cycle's.
 *
 * The cycles are taken in the order of their archived row's id, NOT in the order of the time they ran from.
 * The legacy plugin inserted the archived row at the moment of the reset, so id order is reset order. Time is
 * not reliable for that: core's completion cron recreates the completion row after a reset with timeenrolled
 * set to the ORIGINAL enrolment and timestarted 0 until the learner does something, so a later cycle that was
 * reset without ever being started "ran from" a date before the first cycle even completed, sorted ahead of
 * it, and was left with no reset at all. Because the cycles are in chronological order, a cycle can never run
 * from earlier than the cycle before it did, which is what the running maximum of the lower bound says.
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

        $result = ['cc' => [], 'event' => []];
        foreach ($resets as $reset) {
            $result['event'][$reset['id']] = null;
        }

        $count = count($resets);
        $next = 0;
        foreach ($cycles as $position => $cycle) {
            $result['cc'][$cycle['id']] = null;

            // The first evidence of the following cycle: its start, or its completion if that came first.
            $upper = PHP_INT_MAX;
            if (isset($cycles[$position + 1])) {
                $following = array_filter([$cycles[$position + 1]['completed'], $cycles[$position + 1]['started']],
                    static fn(int $time): bool => $time > 0);
                if ($following) {
                    $upper = min($following);
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
