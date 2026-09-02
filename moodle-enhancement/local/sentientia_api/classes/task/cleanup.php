<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Nightly cleanup: prune rate-limit counters, expired LTI nonces, and
 * request-log rows older than retention.
 *
 * @package local_sentientia_api
 */
class cleanup extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_cleanup', 'local_sentientia_api');
    }

    public function execute(): void {
        $rates  = \local_sentientia_api\rate_limiter::prune();
        $nonces = \local_sentientia_api\lti\registration::prune_nonces();
        $logs   = \local_sentientia_api\request_log::prune();
        $dels   = \local_sentientia_api\webhooks\queue::prune();
        mtrace("local_sentientia_api cleanup: pruned $rates rate rows, $nonces nonces, $logs log rows, $dels webhook deliveries.");
    }
}
