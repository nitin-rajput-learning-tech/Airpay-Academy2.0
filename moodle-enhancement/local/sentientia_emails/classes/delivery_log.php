<?php
/**
 * Delivery log queries and statistics.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

class delivery_log {

    const TABLE = 'local_sentientia_email_log';

    /**
     * Log a notification delivery.
     *
     * Sprint B (2026-05-13): two new fields are accepted —
     *   attachment_filename   nullable string, e.g. "certificate.pdf"
     *   certificate_issue_id  nullable int, FK to tool_certificate_issues
     * Both fall back to NULL when the delivery had no attachment.
     *
     * @param array $data {rule_id, userid, courseid, tenant_id, channel,
     *                     subject, template_key, status, error_message,
     *                     attachment_filename?, certificate_issue_id?}
     * @return int log ID
     */
    public static function log(array $data): int {
        global $DB, $CFG;

        // If noemailever is set, mark as suppressed.
        if (!empty($CFG->noemailever) && ($data['channel'] ?? 'email') === 'email') {
            $data['status'] = 'suppressed';
            $data['error_message'] = 'Local dev: $CFG->noemailever = true';
        }

        $record = (object)array_merge([
            'rule_id'              => null,
            'legacy_type'          => null,
            'userid'               => 0,
            'courseid'             => null,
            'tenant_id'            => 1,
            'channel'              => 'email',
            'subject'              => '',
            'template_key'         => null,
            'status'               => 'sent',
            'error_message'        => null,
            'attachment_filename'  => null,
            'certificate_issue_id' => null,
            'timecreated'          => time(),
        ], $data);

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Mark all pending/sent reminders for a (user, course) as
     * `suppressed_completion` — used by the course-completion observer
     * to flag that subsequent ramping reminders for this user/course
     * pair should be considered "obsolete due to completion".
     *
     * This is mostly for the audit trail: the rule processor's
     * dedup check already catches the case ("user completed → skip"),
     * but stamping the existing logs makes the dashboards correctly
     * answer "did this learner finish?" without re-running queries
     * against course_completions.
     *
     * Sprint B addition.
     *
     * @param int $userid
     * @param int $courseid
     * @return int Number of rows updated.
     */
    public static function mark_reminders_suppressed_on_completion(int $userid,
                                                                    int $courseid): int {
        global $DB;
        if ($userid <= 0 || $courseid <= 0) {
            return 0;
        }
        $sql = "UPDATE {" . self::TABLE . "}
                   SET status = :newstatus,
                       error_message = :errmsg
                 WHERE userid = :uid
                   AND courseid = :cid
                   AND status = :oldstatus
                   AND template_key NOT LIKE :complete_tpl";
        $params = [
            'newstatus'    => 'suppressed_completion',
            'errmsg'       => 'User completed the course; reminder no longer relevant',
            'uid'          => $userid,
            'cid'          => $courseid,
            'oldstatus'    => 'sent',
            // Don't downgrade the completion email itself — only the
            // incomplete/reminder rows.
            'complete_tpl' => '%course_completed%',
        ];
        return $DB->execute($sql, $params) ? 1 : 0;
    }

    /**
     * Get filtered log entries with pagination.
     *
     * @param array $filters {tenant_id, status, channel, from_date, to_date, userid}
     * @param int $page
     * @param int $perpage
     * @return object {records, total}
     */
    public static function get_logs(array $filters = [], int $page = 0, int $perpage = 50): object {
        global $DB;

        // ADR-031: the tenant filter is mandatory for anyone who is not
        // cross-tenant - whatever the caller passed. These rows carry
        // recipients' names and email addresses. A reader with no
        // resolvable tenant gets nothing (the tenant_id = 0 rows are the
        // recipients who had no tenant, not "every tenant").
        $readertenant = tenant_scope::reader_tenant();
        if ($readertenant !== null) {
            if ($readertenant <= 0) {
                return (object)['records' => [], 'total' => 0];
            }
            $filters['tenant_id'] = $readertenant;
        }

        $conditions = [];
        $params = [];

        if (!empty($filters['tenant_id'])) {
            $conditions[] = "l.tenant_id = :tid";
            $params['tid'] = $filters['tenant_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = "l.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['channel'])) {
            $conditions[] = "l.channel = :channel";
            $params['channel'] = $filters['channel'];
        }
        if (!empty($filters['from_date'])) {
            $conditions[] = "l.timecreated >= :fromdate";
            $params['fromdate'] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $conditions[] = "l.timecreated <= :todate";
            $params['todate'] = $filters['to_date'];
        }
        if (!empty($filters['userid'])) {
            $conditions[] = "l.userid = :uid";
            $params['uid'] = $filters['userid'];
        }

        $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

        $sql = "SELECT l.*, u.firstname, u.lastname, u.email
                  FROM {" . self::TABLE . "} l
             LEFT JOIN {user} u ON u.id = l.userid
                 WHERE $where
              ORDER BY l.timecreated DESC";

        $countsql = "SELECT COUNT(*) FROM {" . self::TABLE . "} l WHERE $where";

        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        $total = $DB->count_records_sql($countsql, $params);

        return (object)['records' => array_values($records), 'total' => $total];
    }

    /**
     * Get dashboard statistics.
     *
     * ADR-031: scoped to $tenantid (0 = every tenant, cross-tenant callers
     * only). A scoped reader is always confined to their own tenant.
     *
     * @param int $tenantid
     * @return object {total, sent_today, sent_week, failed, suppressed, by_status, by_channel}
     */
    public static function get_stats(int $tenantid = 0): object {
        global $DB;

        $readertenant = tenant_scope::reader_tenant();
        if ($readertenant !== null) {
            $tenantid = $readertenant;
        }
        if ($tenantid > 0) {
            $where = 'tenant_id = :tid';
            $params = ['tid' => $tenantid];
        } else if ($readertenant === null) {
            $where = '1=1';
            $params = [];
        } else {
            $where = '1=0';
            $params = [];
        }

        $today = strtotime('today');
        $weekago = time() - (7 * 86400);

        $total = $DB->count_records_select(self::TABLE, $where, $params);
        $senttoday = $DB->count_records_select(self::TABLE,
            "$where AND status = 'sent' AND timecreated >= :today", $params + ['today' => $today]);
        $sentweek = $DB->count_records_select(self::TABLE,
            "$where AND status = 'sent' AND timecreated >= :week", $params + ['week' => $weekago]);
        $failed = $DB->count_records_select(self::TABLE, "$where AND status = 'failed'", $params);
        $suppressed = $DB->count_records_select(self::TABLE, "$where AND status = 'suppressed'", $params);

        $bystatus = $DB->get_records_sql(
            "SELECT status, COUNT(*) AS cnt FROM {" . self::TABLE . "} WHERE $where GROUP BY status",
            $params
        );
        $bychannel = $DB->get_records_sql(
            "SELECT channel, COUNT(*) AS cnt FROM {" . self::TABLE . "} WHERE $where GROUP BY channel",
            $params
        );

        return (object)[
            'total'      => $total,
            'sent_today' => $senttoday,
            'sent_week'  => $sentweek,
            'failed'     => $failed,
            'suppressed' => $suppressed,
            'by_status'  => array_values($bystatus),
            'by_channel' => array_values($bychannel),
        ];
    }

    /**
     * Export logs as CSV data.
     *
     * @param array $filters same as get_logs()
     * @return string CSV content
     */
    public static function export_csv(array $filters = []): string {
        $result = self::get_logs($filters, 0, 10000);
        $lines = ["ID,Date,User,Email,Tenant,Channel,Subject,Template,Status,Error"];
        foreach ($result->records as $r) {
            $lines[] = implode(',', [
                $r->id,
                date('Y-m-d H:i', $r->timecreated),
                '"' . s(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')) . '"',
                s($r->email ?? ''),
                $r->tenant_id,
                $r->channel,
                '"' . str_replace('"', '""', s($r->subject)) . '"',
                s($r->template_key ?? ''),
                $r->status,
                '"' . str_replace('"', '""', s($r->error_message ?? '')) . '"',
            ]);
        }
        return implode("\n", $lines);
    }
}
