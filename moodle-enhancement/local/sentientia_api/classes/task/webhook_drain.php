<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Every-minute drain of the outbound webhook queue (ADR-030 Wave A).
 *
 * Registered DISABLED in db/tasks.php — triple opt-in: feature flag ON for a
 * customer + this task enabled by an admin + at least one subscription.
 *
 * @package local_sentientia_api
 */
class webhook_drain extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_webhook_drain', 'local_sentientia_api');
    }

    public function execute(): void {
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            mtrace('local_sentientia_api webhook_drain: platform plugin absent, nothing to do.');
            return;
        }
        $stats = \local_sentientia_api\webhooks\queue::drain(100);
        mtrace(sprintf('local_sentientia_api webhook_drain: sent %d, failed %d, dead %d, skipped %d.',
            $stats['sent'], $stats['failed'], $stats['dead'], $stats['skipped']));
    }
}
