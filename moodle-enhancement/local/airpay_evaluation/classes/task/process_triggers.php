<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_evaluation\task;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-5 (2026-05-15) — scheduled task that drains the evaluation trigger queue.
 *
 * Fires due triggers (status=pending AND fire_after <= now), creating a
 * response-shell row + sending a Moodle notification per trigger. Capped at
 * 500 rows per run to keep cron passes short — the queue clears across
 * multiple runs if a backlog builds up.
 *
 * Default cron schedule: every 15 minutes. Can be adjusted from the admin
 * UI (Site Administration → Server → Scheduled tasks).
 *
 * @package local_airpay_evaluation
 */
class process_triggers extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_process_triggers', 'local_airpay_evaluation');
    }

    public function execute(): void {
        $result = \local_airpay_evaluation\evaluation_engine::process_due_triggers(500);
        mtrace('local_airpay_evaluation: '
            . $result['fired'] . ' triggers fired, '
            . $result['skipped'] . ' skipped (eval archived).');
    }
}
