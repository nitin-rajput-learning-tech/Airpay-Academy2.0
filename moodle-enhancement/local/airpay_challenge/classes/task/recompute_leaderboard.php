<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge\task;

defined('MOODLE_INTERNAL') || die();

use local_airpay_challenge\leaderboard_manager;

/**
 * Scheduled task — recompute the entire leaderboard snapshot every
 * 15 minutes. Catches anything the event observer missed (events
 * disabled, observer error, completions backfilled by cron).
 */
class recompute_leaderboard extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_recompute_leaderboard', 'local_airpay_challenge');
    }

    public function execute(): void {
        leaderboard_manager::recompute_all();
    }
}
