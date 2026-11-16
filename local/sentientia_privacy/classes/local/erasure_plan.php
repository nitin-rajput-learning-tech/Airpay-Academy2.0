<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_privacy\local;

defined('MOODLE_INTERNAL') || die();

/**
 * One subject's supplementary erasure plan: what the stock approval will drop, per component.
 *
 * Built by erasure_completeness::plan(). It holds ids and component names only, never personal data.
 *
 * For every component whose manager-equivalent discovery list is not empty:
 *   - position: where the component runs relative to local_sentientia_privacy in the privacy manager's order
 *     ('before' = its stock delete has already run when ours runs; 'after' = it has not);
 *   - full: every id the list holds (core's get_contextids(), reindexed);
 *   - iterated: the ids core's foreach reaches before the first key hole (what stock approves);
 *   - missed: full minus iterated (what stock silently drops).
 * Plus the completion gap (course contexts holding course_completions rows for the subject that core_course
 * never discovers) and the failures met while planning.
 *
 * @package    local_sentientia_privacy
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_plan {

    /** @var array<string, array{position: string, full: int[], iterated: int[], missed: int[]}> Per component. */
    public array $components = [];

    /** @var int[] Course context ids of the completion gap (only when the completion part was planned). */
    public array $completiongap = [];

    /** @var array<int, array{component: string, phase: string, exception: string}> Failures met while planning. */
    public array $failures = [];

    /**
     * @param int $userid The data subject (an int only: the subject may have no user row).
     * @param bool $truncation Whether the truncation part was planned.
     * @param bool $completion Whether the completion-gap part was planned.
     * @param int $createdat \core\clock time the plan was built.
     */
    public function __construct(
        /** @var int The data subject. */
        public readonly int $userid,
        /** @var bool Whether the truncation part was planned. */
        public readonly bool $truncation,
        /** @var bool Whether the completion-gap part was planned. */
        public readonly bool $completion,
        /** @var int When the plan was built. */
        public readonly int $createdat,
    ) {
    }

    /**
     * Every context id this plan adds to local_sentientia_privacy's own contextlist: all missed ids plus the
     * completion gap, unique, as a dense 0..n-1 list.
     *
     * @return int[]
     */
    public function supplementary_contextids(): array {
        $ids = $this->completiongap;
        foreach ($this->components as $entry) {
            $ids = array_merge($ids, $entry['missed']);
        }
        return erasure_completeness::dense($ids);
    }

    /**
     * The missed ids per component, components with nothing missed left out.
     *
     * @return array<string, int[]>
     */
    public function missed_by_component(): array {
        $missed = [];
        foreach ($this->components as $component => $entry) {
            if ($entry['missed']) {
                $missed[$component] = $entry['missed'];
            }
        }
        return $missed;
    }

    /**
     * Record a failure: the component, the phase and the exception class. Never the message, which can carry
     * ids or data.
     *
     * @param string $component Frankenstyle component.
     * @param string $phase erasure_completeness::PHASE_* value.
     * @param \Throwable $e The failure.
     * @return void
     */
    public function add_failure(string $component, string $phase, \Throwable $e): void {
        $this->failures[] = ['component' => $component, 'phase' => $phase, 'exception' => get_class($e)];
    }
}
