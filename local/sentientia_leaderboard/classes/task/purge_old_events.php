<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard\task;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_leaderboard\event_journal;

/**
 * Daily purge of stale SSE events. Default retention: 7 days.
 *
 * @package local_sentientia_leaderboard
 */
class purge_old_events extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_purge_old_events',
            'local_sentientia_leaderboard');
    }

    public function execute(): void {
        $deleted = event_journal::purge_old();
        mtrace("sentientia_leaderboard: purged $deleted event row(s)");
    }
}
