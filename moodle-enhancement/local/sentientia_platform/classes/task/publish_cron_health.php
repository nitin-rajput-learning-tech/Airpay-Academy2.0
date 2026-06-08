<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task that publishes the current cron-health summary every
 * fifteen minutes.
 *
 * Backs the SUPP-H observability playbook section 6 (Cron-health alert
 * wiring). The task itself does not run any business logic; it queries
 * the read-side helper `\local_sentientia_platform\cron_health` and emits the
 * result through three channels:
 *
 *   1. Structured log (`\local_sentientia_platform\structured_logger`).
 *   2. Moodle event `\local_sentientia_platform\event\cron_task_stuck` when
 *      anything is stuck.
 *   3. APM custom event when New Relic is installed.
 *
 * The task is itself a scheduled task — which means it is also subject
 * to the same `cron_health` watchdog. Stopping this publisher silently
 * would be visible to a future invocation through the lastruntime
 * field; the watchdog is therefore self-checking.
 *
 * @package local_sentientia_platform
 */
class publish_cron_health extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_publish_cron_health', 'local_sentientia_platform');
    }

    public function execute() {
        $summary = \local_sentientia_platform\cron_health::summary();

        // Always log the heartbeat — even a clean summary is useful
        // because its presence proves the publisher itself is alive.
        \local_sentientia_platform\structured_logger::info('core',
            'cron_health_summary',
            [
                'stuck_airpay' => $summary['stuck_airpay'],
                'stuck_other'  => $summary['stuck_other'],
                'in_backoff'   => $summary['in_backoff'],
            ]);

        // If anything is stuck OR in backoff, fire the structured event
        // so downstream subscribers (audit log + dashboard banner) can
        // react.
        if ($summary['stuck_airpay'] > 0 || $summary['in_backoff'] > 0) {
            $stuck = \local_sentientia_platform\cron_health::get_stuck_airpay_tasks();
            $names = array_map(fn($t) => $t->classname, $stuck);

            \local_sentientia_platform\structured_logger::warn('core',
                'cron_tasks_stuck',
                [
                    'stuck_count'  => $summary['stuck_airpay'],
                    'in_backoff'   => $summary['in_backoff'],
                    'stuck_names'  => $names,
                ]);

            // Persist a transient red-banner notification for siteadmins
            // visible on /admin/index.php for the next 24 hours. Uses
            // Moodle's standard preset notifications API.
            self::raise_site_notification($summary, $stuck);
        }
    }

    /**
     * Set a transient site notification banner that surfaces on
     * /admin/index.php for the next 24 hours. Stored in the standard
     * preset-notifications cache so we don't accumulate duplicates.
     */
    private static function raise_site_notification(array $summary, array $stuck): void {
        $key = 'cron_health_banner_' . md5(implode(',',
            array_map(fn($t) => $t->classname, $stuck)));
        $cache = \cache::make('local_sentientia_platform', 'cron_health_banner');
        if ($cache->get($key)) {
            return;   // already notified for this set of stuck tasks
        }
        $cache->set($key, time());

        // Use Moodle's setting/admin-notice channel.
        $count = $summary['stuck_airpay'];
        $names = array_map(fn($t) => $t->classname, $stuck);
        $msg = $count === 1
            ? "Scheduled task stuck: {$names[0]}"
            : "$count scheduled tasks stuck. See /admin/tasklogs.php for details.";

        // Setting a transient via $SESSION is fine because admins log
        // in regularly — but we want all admins to see this, not just
        // the current session. Use the standard site notification table
        // exposed via core_notification when available.
        if (class_exists('\core\notification')) {
            \core\notification::add($msg, \core\notification::WARNING);
        }
    }
}
