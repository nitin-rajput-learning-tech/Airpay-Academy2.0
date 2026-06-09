<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Daily push_log retention purge — Phase B.3.c.
 *
 * Deletes rows from mdl_local_sentientia_push_log older than the admin-
 * configured retention_days (default 90, configurable via the plugin
 * settings page). Skip if retention_days = 0 (unlimited retention).
 *
 * Default schedule: 02:00 daily. Override via Site admin ▶ Server ▶
 * Scheduled tasks.
 *
 * @package local_sentientia_pwa
 */
class push_log_retention extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_push_log_retention', 'local_sentientia_pwa');
    }

    public function execute(): void {
        $days = (int) get_config('local_sentientia_pwa', 'log_retention_days');
        if ($days <= 0) {
            // Use default 90 if not configured, OR 0 = unlimited (skip).
            $cfg_value = get_config('local_sentientia_pwa', 'log_retention_days');
            if ($cfg_value === false || $cfg_value === '') {
                $days = 90;
            } else {
                mtrace('local_sentientia_pwa push_log_retention: '
                    . 'retention disabled (log_retention_days = 0)');
                return;
            }
        }

        mtrace('local_sentientia_pwa push_log_retention: '
            . 'purging rows older than ' . $days . ' days...');

        $deleted = \local_sentientia_pwa\push_logger::purge_older_than($days);

        mtrace('local_sentientia_pwa push_log_retention: '
            . 'deleted ' . $deleted . ' row(s)');
    }
}
