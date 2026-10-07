<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * local_email_logs -> local_sentientia_email_log (only if the table exists).
 *
 * The table is written by two dead BizLMS helpers (local/notifications/lib.php: the
 * ILT reminder and the custom e-mail) and no install file declares it, so its
 * shape is inferred from those writers and every column but the recipient, the
 * sender, the subject, the body and the sent date is optional. The writers set no
 * status: a row is delivered when sent_date is set.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class email_logs_step extends log_step {

    /**
     * @return string
     */
    public function key(): string {
        return 'notifications.email_logs';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_email_logs';
    }

    /**
     * @return string[]
     */
    public function columns(): array {
        return [
            'id', 'notification_infoid', 'from_userid', 'to_userid', 'courseid', 'subject', 'body_html',
            'sent_date', 'created_date', 'time_created',
        ];
    }

    /**
     * @param \stdClass $row
     * @return array
     */
    protected function candidate(\stdClass $row): array {
        $sent = self::epoch($row->sent_date ?? 0);
        return [
            'recipient' => (int) ($row->to_userid ?? 0),
            'sender' => (int) ($row->from_userid ?? 0),
            'courseid' => (int) ($row->courseid ?? 0),
            'subject' => (string) ($row->subject ?? ''),
            'body' => (string) ($row->body_html ?? ''),
            'delivered' => $sent > 0,
            'sentdate' => $sent,
            'created' => [self::epoch($row->created_date ?? 0), self::epoch($row->time_created ?? 0)],
            'prefersent' => false,
            'infoid' => (int) ($row->notification_infoid ?? 0),
            'moduletype' => '',
            'moduleid' => 0,
            'teammember' => 0,
        ];
    }
}
