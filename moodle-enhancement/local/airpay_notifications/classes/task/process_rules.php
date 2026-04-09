<?php
/**
 * Scheduled task — processes notification rules every hour.
 *
 * @package    local_airpay_notifications
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_notifications\task;

defined('MOODLE_INTERNAL') || die();

class process_rules extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('taskprocessrules', 'local_airpay_notifications');
    }

    public function execute() {
        $stats = \local_airpay_notifications\rule_engine::process_all();
        mtrace("Airpay Notifications: Processed {$stats['processed']} rules, " .
               "sent {$stats['sent']}, skipped {$stats['skipped']}");
    }
}
