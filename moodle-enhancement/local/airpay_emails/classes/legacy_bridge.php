<?php
/**
 * Read-only adapter for BizLMS notification tables.
 *
 * CRITICAL: This class NEVER writes to BizLMS tables. All operations are SELECT-only.
 *
 * @package    local_airpay_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

class legacy_bridge {

    /**
     * Get all BizLMS notification types with their latest template info.
     *
     * @return array [{id, type_name, type_shortname, subject, has_body, costcenterid, active}]
     */
    public static function get_bizlms_templates(): array {
        global $DB;

        $templates = [];

        // Check if BizLMS notification tables exist.
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_notification_type') ||
            !$dbman->table_exists('local_notification_info')) {
            return [];
        }

        try {
            $records = $DB->get_records_sql(
                "SELECT ni.id, nt.name AS type_name, nt.shortname AS type_shortname,
                        ni.subject, ni.body,
                        ni.costcenterid, ni.active,
                        ni.timemodified
                   FROM {local_notification_info} ni
                   JOIN {local_notification_type} nt ON nt.id = ni.notificationid
               ORDER BY nt.name ASC, ni.costcenterid ASC"
            );

            foreach ($records as $r) {
                $templates[] = [
                    'key'          => 'bizlms/' . $r->type_shortname . '_' . $r->id,
                    'label'        => format_string($r->type_name) .
                                     ($r->costcenterid ? ' (Tenant ' . $r->costcenterid . ')' : ''),
                    'category'     => 'BizLMS',
                    'catkey'       => 'bizlms',
                    'has_override' => false,
                    'override_id'  => 0,
                    'source'       => 'bizlms',
                    'is_bizlms'    => true,
                    'bizlms_id'    => $r->id,
                    'subject'      => format_string($r->subject ?? ''),
                    'body_preview' => shorten_text(strip_tags($r->body ?? ''), 100),
                    'active'       => (bool)$r->active,
                    'timemodified' => $r->timemodified,
                ];
            }
        } catch (\Exception $e) {
            debugging('Legacy bridge: ' . $e->getMessage());
        }

        return $templates;
    }

    /**
     * Get a single BizLMS template by its notification_info ID.
     * Returns full subject and body for preview.
     *
     * @param int $id local_notification_info.id
     * @return object|null {subject, body, type_name, type_shortname, costcenterid}
     */
    public static function get_bizlms_template(int $id): ?object {
        global $DB;

        try {
            return $DB->get_record_sql(
                "SELECT ni.id, ni.subject, ni.body, ni.adminbody,
                        nt.name AS type_name, nt.shortname AS type_shortname,
                        ni.costcenterid, ni.active, ni.completiondays, ni.reminderdays,
                        ni.timecreated, ni.timemodified
                   FROM {local_notification_info} ni
                   JOIN {local_notification_type} nt ON nt.id = ni.notificationid
                  WHERE ni.id = :id",
                ['id' => $id]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get BizLMS email log statistics.
     *
     * @return object {total, sent, pending}
     */
    public static function get_email_stats(): object {
        global $DB;

        try {
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists('local_emaillogs')) {
                return (object)['total' => 0, 'sent' => 0, 'pending' => 0];
            }

            $total = $DB->count_records('local_emaillogs');
            $sent = $DB->count_records('local_emaillogs', ['status' => 1]);
            return (object)[
                'total'   => $total,
                'sent'    => $sent,
                'pending' => $total - $sent,
            ];
        } catch (\Exception $e) {
            return (object)['total' => 0, 'sent' => 0, 'pending' => 0];
        }
    }
}
