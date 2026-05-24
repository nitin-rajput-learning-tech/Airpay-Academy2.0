<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard\task;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_leaderboard\ranking_engine;

/**
 * Recompute every board whose last_recomputed is older than its
 * recompute_seconds. Cron tick is every 2 minutes.
 *
 * Master feature flag check: if `sentientia.leaderboards.enabled` is OFF,
 * skip the whole pass — saves load when the plugin is installed but
 * disabled in a tenant.
 *
 * @package local_sentientia_leaderboard
 */
class recompute_due_boards extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_recompute_due_boards',
            'local_sentientia_leaderboard');
    }

    public function execute(): void {
        // Master flag check — skip the pass if globally disabled.
        if (class_exists('\\local_airpay_core\\feature_flags')) {
            if (!\local_airpay_core\feature_flags::is_enabled(
                    'sentientia.leaderboards.enabled')) {
                mtrace('sentientia_leaderboard: master flag OFF — skipping recompute');
                return;
            }
        }
        $count = ranking_engine::recompute_due();
        mtrace("sentientia_leaderboard: recomputed $count board(s)");
    }
}
