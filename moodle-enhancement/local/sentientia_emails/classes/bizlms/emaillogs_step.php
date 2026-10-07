<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * local_emaillogs -> local_sentientia_email_log (the BizLMS queue and sent log).
 *
 * Production shape: classroom's db/install.php (courses, evaluation, learningplan,
 * program, request and users create the same table if they install first), plus the
 * columns the sender task reads that the install file does not declare (courseid,
 * time_created). Both are optional here. The April 2026 production copy has neither
 * of them, so every row's course comes from moduleid (COMMS-N3); it also has
 * moduleid and teammemberid, which the importer reads (moduleid for that course
 * link, teammemberid to tell a manager copy). Both are optional on a snapshot that
 * does not declare them.
 *
 * Columns that are not read stay in the legacy table (the archive): the e-mail
 * addresses, the admin copy of the body, the CC target, the batch id, the reminder
 * settings, the attachment path, the sent_by and usercreated/usermodified actors. See
 * the mapping doc, section 11, "not copied".
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class emaillogs_step extends log_step {

    /**
     * @return string
     */
    public function key(): string {
        return 'notifications.emaillogs';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_emaillogs';
    }

    /**
     * Not the admin copy of the body, the e-mail addresses or the attachment path.
     *
     * @return string[]
     */
    public function columns(): array {
        return [
            'id', 'notification_infoid', 'from_userid', 'to_userid', 'moduletype', 'moduleid', 'teammemberid', 'subject',
            'emailbody', 'status', 'timecreated', 'time_created', 'timemodified', 'sent_date', 'courseid',
        ];
    }

    /**
     * status 1 is delivered: the sender task sets it after message_send() returned. A row it marks 1 for a
     * recipient who was already deleted when it ran was never sent, which log_step notes (a recipient deleted
     * since was delivered to; see delivered_to_deleted_recipient()).
     *
     * @param \stdClass $row
     * @return array
     */
    protected function candidate(\stdClass $row): array {
        return [
            'recipient' => (int) ($row->to_userid ?? 0),
            'sender' => (int) ($row->from_userid ?? 0),
            'courseid' => (int) ($row->courseid ?? 0),
            'subject' => (string) ($row->subject ?? ''),
            'body' => (string) ($row->emailbody ?? ''),
            'delivered' => isset($row->status) && (string) $row->status === '1',
            'sentdate' => self::epoch($row->sent_date ?? 0),
            'created' => [
                self::epoch($row->timecreated ?? 0),
                self::epoch($row->time_created ?? 0),
                self::epoch($row->timemodified ?? 0),
            ],
            'prefersent' => true,
            'infoid' => (int) ($row->notification_infoid ?? 0),
            'moduletype' => (string) ($row->moduletype ?? ''),
            'moduleid' => self::numeric_id($row->moduleid ?? ''),
            'teammember' => max(0, (int) ($row->teammemberid ?? 0)),
        ];
    }

    /**
     * moduleid is a TEXT column (a course id for a course template, but BizLMS also kept lists and other ids in it).
     * It counts as an id only when it is one whole, plain number.
     *
     * @param mixed $value
     * @return int The id, 0 when the value is not a plain positive integer.
     */
    private static function numeric_id(mixed $value): int {
        $value = trim((string) $value);
        // epoch() is the one place that knows what an INT(10) holds: a larger number is garbage, so 0.
        return ($value !== '' && strlen($value) <= 10 && ctype_digit($value)) ? self::epoch($value) : 0;
    }
}
