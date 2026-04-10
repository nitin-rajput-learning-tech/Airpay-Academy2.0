<?php
namespace local_airpay_compliance_report\task;

defined('MOODLE_INTERNAL') || die();

class refresh_snapshot extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('taskrefresh', 'local_airpay_compliance_report');
    }

    public function execute() {
        $stats = \local_airpay_compliance_report\compliance_engine::rebuild_snapshot();
        mtrace("Compliance snapshot: {$stats['total']} items, {$stats['completed']} completed, " .
               "{$stats['overdue']} overdue, {$stats['auto_enrolled']} auto-enrolled, " .
               "{$stats['emails_sent']} emails sent");
    }
}
