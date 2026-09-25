<?php
namespace local_sentientia_learningpath\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Daily adaptive sweep cron task.
 *
 * P0.2 (2026-06-16) — Adaptive Learning Journeys.
 *
 * Runs once per day and re-evaluates velocity for all learners enrolled
 * in adaptive paths (adaptive_mode = 1). This catches learners who have
 * fallen behind without triggering a quiz attempt event (e.g., inactive
 * learners who never opened the next course).
 *
 * The task is registered in db/tasks.php and is a no-op when the
 * feature flag sentientia.learningpath.adaptive.enabled is OFF.
 *
 * @package local_sentientia_learningpath
 */
class adaptive_sweep extends \core\task\scheduled_task {

    /**
     * Returns the task name shown in the admin scheduled-tasks UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_adaptive_sweep', 'local_sentientia_learningpath');
    }

    /**
     * Execute the sweep.
     *
     * @return void
     */
    public function execute(): void {
        $pivots = \local_sentientia_learningpath\adaptive\journey_engine::velocity_sweep();

        if ($pivots > 0) {
            mtrace("local_sentientia_learningpath adaptive_sweep: {$pivots} pivot(s) logged.");
        } else {
            mtrace('local_sentientia_learningpath adaptive_sweep: no pivots (flag OFF or no adaptive paths).');
        }
    }
}
