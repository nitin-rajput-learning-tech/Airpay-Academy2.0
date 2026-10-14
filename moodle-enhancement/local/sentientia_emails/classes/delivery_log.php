<?php
/**
 * Delivery log queries and statistics.
 *
 * ADR-032 (2026-09-30): the log also holds the BizLMS email history, imported by
 * classes/bizlms/importer.php and marked legacy_source = 'bizlms'. Until the flag
 * sentientia.emails.imported_history.enabled is ON every reader here leaves those
 * rows out, so the log and its numbers are what they were before the import.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

class delivery_log {

    const TABLE = 'local_sentientia_email_log';

    /** Rows per page of a streamed export. */
    const EXPORT_PAGE = 1000;

    /**
     * The columns a list reads: everything but body_html. A list of messages must never pull the bodies (the
     * detail view reads one), so these are named instead of l.*.
     */
    const LIST_COLUMNS = 'l.id, l.rule_id, l.legacy_type, l.userid, l.courseid, l.tenant_id, l.channel, l.subject,
                l.template_key, l.status, l.error_message, l.attachment_filename, l.certificate_issue_id,
                l.timecreated, l.legacy_source, l.sender_userid, l.timesent';

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
     * ADR-032: an imported BizLMS row is history, not a reminder this engine sent, so it is never restamped
     * (legacy_source IS NULL). Its NULL template_key already kept it out; the guard says so.
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
                   AND legacy_source IS NULL
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
     * The WHERE clause of a log query, with everything ADR-031 and ADR-032 require of it.
     *
     * ADR-031: the tenant filter is mandatory for anyone who is not cross-tenant - whatever the caller passed.
     * These rows carry recipients' names and email addresses. A reader with no resolvable tenant gets nothing
     * (the tenant_id = 0 rows are the recipients who had no tenant, not "every tenant").
     *
     * ADR-032: while the imported-history flag is OFF the imported rows are left out.
     *
     * @param array $filters {tenant_id, status, channel, from_date, to_date, userid}
     * @return array{0: string, 1: array}|null [where, params] over the alias l; null when this reader may see nothing
     */
    private static function scope(array $filters): ?array {
        $readertenant = tenant_scope::reader_tenant();
        if ($readertenant !== null) {
            if ($readertenant <= 0) {
                return null;
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
        if (!imported_history::history_enabled()) {
            $conditions[] = imported_history::native_only_sql('l');
        }

        return [!empty($conditions) ? implode(' AND ', $conditions) : '1=1', $params];
    }

    /**
     * Get filtered log entries with pagination.
     *
     * The records carry has_body (whether a body is stored) and the sender's name, never the body itself.
     *
     * @param array $filters {tenant_id, status, channel, from_date, to_date, userid}
     * @param int $page
     * @param int $perpage
     * @return object {records, total}
     */
    public static function get_logs(array $filters = [], int $page = 0, int $perpage = 50): object {
        global $DB;

        $scope = self::scope($filters);
        if ($scope === null) {
            return (object)['records' => [], 'total' => 0];
        }
        [$where, $params] = $scope;

        $sql = "SELECT " . self::LIST_COLUMNS . ",
                       u.firstname, u.lastname, u.email,
                       su.firstname AS sender_firstname, su.lastname AS sender_lastname,
                       CASE WHEN l.body_html IS NULL THEN 0 ELSE 1 END AS has_body
                  FROM {" . self::TABLE . "} l
             LEFT JOIN {user} u ON u.id = l.userid
             LEFT JOIN {user} su ON su.id = l.sender_userid
                 WHERE $where
              ORDER BY l.timecreated DESC, l.id DESC";

        $countsql = "SELECT COUNT(*) FROM {" . self::TABLE . "} l WHERE $where";

        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        $total = $DB->count_records_sql($countsql, $params);

        return (object)['records' => array_values($records), 'total' => $total];
    }

    /**
     * One imported message with its body, for the detail view (ADR-032).
     *
     * Null - as if the row did not exist - unless both flags are ON, the row is an imported one and the caller
     * may see its tenant: a scoped caller only their own, a caller whose tenant does not resolve nothing.
     *
     * @param int $id local_sentientia_email_log.id
     * @return \stdClass|null The list columns, body_html, the recipient's and the sender's names and address.
     */
    public static function get_imported_detail(int $id): ?\stdClass {
        global $DB;

        if (!imported_history::body_enabled()) {
            return null;
        }
        $conditions = ['l.id = :id', 'l.legacy_source IS NOT NULL'];
        $params = ['id' => $id];
        $readertenant = tenant_scope::reader_tenant();
        if ($readertenant !== null) {
            if ($readertenant <= 0) {
                return null;
            }
            $conditions[] = 'l.tenant_id = :tid';
            $params['tid'] = $readertenant;
        }

        $record = $DB->get_record_sql(
            "SELECT " . self::LIST_COLUMNS . ", l.body_html,
                    u.firstname, u.lastname, u.email,
                    su.firstname AS sender_firstname, su.lastname AS sender_lastname
               FROM {" . self::TABLE . "} l
          LEFT JOIN {user} u ON u.id = l.userid
          LEFT JOIN {user} su ON su.id = l.sender_userid
              WHERE " . implode(' AND ', $conditions),
            $params);
        return $record ?: null;
    }

    /**
     * Get dashboard statistics.
     *
     * ADR-031: scoped to $tenantid (0 = every tenant, cross-tenant callers
     * only). A scoped reader is always confined to their own tenant.
     *
     * ADR-032: imported rows are counted only while the imported-history flag is ON.
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
        if (!imported_history::history_enabled()) {
            $where .= ' AND ' . imported_history::native_only_sql();
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
     * The header line of the CSV export.
     *
     * @param bool $imported Include the columns that only imported BizLMS history fills.
     * @return string
     */
    private static function csv_header(bool $imported): string {
        return "ID,Date,User,Email,Tenant,Channel,Subject,Template,Status,Error"
            . ($imported ? ",LegacyType,SentFrom,SentOn" : '');
    }

    /**
     * One CSV line.
     *
     * @param \stdClass $r A get_logs() record.
     * @param bool $imported Include the columns that only imported BizLMS history fills.
     * @return string
     */
    private static function csv_line(\stdClass $r, bool $imported): string {
        $cells = [
            $r->id,
            date('Y-m-d H:i', $r->timecreated),
            '"' . self::csv_safe(s(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''))) . '"',
            self::csv_safe(s($r->email ?? '')),
            $r->tenant_id,
            $r->channel,
            '"' . str_replace('"', '""', self::csv_safe(s($r->subject))) . '"',
            self::csv_safe(s($r->template_key ?? '')),
            $r->status,
            '"' . str_replace('"', '""', self::csv_safe(s($r->error_message ?? ''))) . '"',
        ];
        if ($imported) {
            $cells[] = self::csv_safe(s($r->legacy_type ?? ''));
            $cells[] = '"' . self::csv_safe(s(trim(($r->sender_firstname ?? '') . ' ' . ($r->sender_lastname ?? '')))) . '"';
            $cells[] = !empty($r->timesent) ? date('Y-m-d H:i', (int) $r->timesent) : '';
        }
        return implode(',', $cells);
    }

    /**
     * Make a text safe to open in a spreadsheet (F-65).
     *
     * A cell that starts with =, +, - or @ (or a tab or a carriage return) is read as a formula by Excel and Calc, and the
     * subject of an old BizLMS mail is not ours to trust: the export would hand an administrator a file that runs it. The
     * cell is prefixed with a single quote, which the spreadsheet shows as nothing and does not evaluate. It is applied to
     * the text AFTER s() has escaped it, so the quote it adds is a plain one and stays in front of the cell.
     *
     * @param string $text
     * @return string
     */
    public static function csv_safe(string $text): string {
        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'" . $text : $text;
    }

    /**
     * Export logs as CSV data, at most 10 000 rows. Kept for callers that want a string;
     * the Export CSV button streams with stream_csv() and has no cap.
     *
     * @param array $filters same as get_logs()
     * @return string CSV content
     */
    public static function export_csv(array $filters = []): string {
        $imported = imported_history::history_enabled();
        $result = self::get_logs($filters, 0, 10000);
        $lines = [self::csv_header($imported)];
        foreach ($result->records as $r) {
            $lines[] = self::csv_line($r, $imported);
        }
        return implode("\n", $lines);
    }

    /**
     * Stream the whole log as CSV, a page at a time, to $write (mapping doc section 11, code fix 4).
     *
     * The same scope as get_logs(): a scoped caller gets their own tenant whatever they pass. Keyset paging on
     * the id (newest first), so memory stays one page however large the log is and no row is skipped or repeated
     * while rows are being added. The list columns only: a body is never exported.
     *
     * @param array $filters same as get_logs()
     * @param callable $write function(string $line): void, called once for the header and once per row; each
     *        line ends in a newline
     * @return int Rows written, header not counted.
     */
    public static function stream_csv(array $filters, callable $write): int {
        global $DB;

        $imported = imported_history::history_enabled();
        $write(self::csv_header($imported) . "\n");
        $scope = self::scope($filters);
        if ($scope === null) {
            return 0;
        }
        [$where, $params] = $scope;

        $written = 0;
        $after = null;
        do {
            $pageparams = $params;
            $pagewhere = $where;
            if ($after !== null) {
                $pagewhere .= ' AND l.id < :blmafter';
                $pageparams['blmafter'] = $after;
            }
            $records = $DB->get_records_sql(
                "SELECT " . self::LIST_COLUMNS . ",
                        u.firstname, u.lastname, u.email,
                        su.firstname AS sender_firstname, su.lastname AS sender_lastname
                   FROM {" . self::TABLE . "} l
              LEFT JOIN {user} u ON u.id = l.userid
              LEFT JOIN {user} su ON su.id = l.sender_userid
                  WHERE $pagewhere
               ORDER BY l.id DESC",
                $pageparams, 0, self::EXPORT_PAGE);
            foreach ($records as $r) {
                $write(self::csv_line($r, $imported) . "\n");
                $after = (int) $r->id;
                $written++;
            }
        } while (count($records) === self::EXPORT_PAGE);
        return $written;
    }
}
