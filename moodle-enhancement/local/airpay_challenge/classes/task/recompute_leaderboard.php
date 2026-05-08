<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge\task;

defined('MOODLE_INTERNAL') || die();

use local_airpay_challenge\challenge_engine;
use local_airpay_challenge\leaderboard_manager;

/**
 * Scheduled task — recompute the entire leaderboard snapshot every
 * 15 minutes + expire any past-end-date attempts. Catches anything
 * the event observer missed (events disabled, observer error,
 * completions backfilled by cron).
 */
class recompute_leaderboard extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_recompute_leaderboard', 'local_airpay_challenge');
    }

    public function execute(): void {
        // Phase 2 — expire past-end-date attempts before recomputing so
        // the leaderboard reflects current state.
        $expired = challenge_engine::expire_overdue_attempts();
        if ($expired > 0) {
            mtrace("airpay_challenge: expired $expired past-end-date attempts");
        }
        leaderboard_manager::recompute_all();
    }
}
