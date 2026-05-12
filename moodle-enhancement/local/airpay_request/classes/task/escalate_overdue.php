<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_request\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task — every 15 min — escalates overdue pending requests
 * to the next approver tier, and expires very old pending requests.
 */
class escalate_overdue extends \core\task\scheduled_task {

    public function get_name(): string {
        return 'Airpay course requests: escalate overdue + auto-expire';
    }

    public function execute() {
        $escalated = \local_airpay_request\request_manager::escalate_overdue();
        $expired   = \local_airpay_request\request_manager::auto_expire();
        mtrace("airpay_request: escalated=$escalated expired=$expired");
    }
}
