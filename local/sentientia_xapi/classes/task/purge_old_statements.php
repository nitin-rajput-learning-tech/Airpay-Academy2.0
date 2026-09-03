<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\task;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_xapi\lrs\store;
use local_sentientia_xapi\lrs\rate_limiter;

/**
 * Scheduled task: purge old xAPI statements beyond the retention period,
 * and (H3 fix, UAT-SECURITY-POSTURE-2026-09-03) prune lapsed LRS
 * rate-limit counter rows — piggybacking here instead of registering a
 * brand-new task, mirroring local_sentientia_api's cleanup task which
 * does the same for its own rate_limiter.
 *
 * Runs nightly at 02:30. Statement purge no-ops when retention_days = 0
 * (keep forever) or when xAPI is disabled; rate-limit pruning always runs
 * regardless of those settings, since stale counter rows accumulate
 * whenever the LRS endpoint is used at all.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_old_statements extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_purge_old_statements', 'local_sentientia_xapi');
    }

    public function execute(): void {
        // H3 fix (UAT-SECURITY-POSTURE-2026-09-03) — always prune lapsed
        // LRS rate-limit counter rows, independent of the statement
        // purge's disabled/retention early-returns below: a client could
        // have hit the endpoint while xAPI was enabled and left rows
        // behind even if it's since been switched off.
        $rate_rows = rate_limiter::prune();
        mtrace("local_sentientia_xapi: pruned $rate_rows lapsed LRS rate-limit counter rows.");

        // No-op when xAPI is fully disabled.
        if (class_exists('\local_sentientia_platform\feature_flags')
                && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.xapi.enabled')) {
            mtrace('local_sentientia_xapi: xAPI disabled — skipping purge.');
            return;
        }

        $retention_days = (int) get_config('local_sentientia_xapi', 'retention_days');
        if ($retention_days <= 0) {
            mtrace('local_sentientia_xapi: retention_days = 0 — keeping all statements forever.');
            return;
        }

        global $DB;
        $cutoff = time() - ($retention_days * DAYSECS);

        $count = $DB->count_records_select(
            'local_sentientia_xapi_stmts',
            'timestored < :cutoff',
            ['cutoff' => $cutoff]
        );

        if ($count === 0) {
            mtrace("local_sentientia_xapi: no statements older than $retention_days days to purge.");
            return;
        }

        $DB->delete_records_select(
            'local_sentientia_xapi_stmts',
            'timestored < :cutoff',
            ['cutoff' => $cutoff]
        );

        mtrace("local_sentientia_xapi: purged $count statements older than $retention_days days.");
    }
}
