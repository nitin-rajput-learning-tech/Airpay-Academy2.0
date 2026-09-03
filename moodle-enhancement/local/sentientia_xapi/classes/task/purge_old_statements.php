<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\task;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_xapi\lrs\store;

/**
 * Scheduled task: purge old xAPI statements beyond the retention period.
 *
 * Runs nightly at 02:30. No-ops when retention_days = 0 (keep forever)
 * or when xAPI is disabled.
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
