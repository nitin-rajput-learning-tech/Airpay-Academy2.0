<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * Push delivery logger — Phase B.3.c.
 *
 * Writes one row to mdl_local_sentientia_push_log per delivery attempt.
 * Called by push_sender::deliver_one() after each HTTP POST. Captures:
 *   - who got it (userid, sub_id, endpoint_host)
 *   - what they got (title, body truncated to 200 chars, url, tag)
 *   - how it went (http_code, result, optional error detail)
 *
 * The body is truncated to 200 chars to limit PII surface — admins
 * inspecting the log shouldn't see full message content. The result
 * column is enum-like for fast filtering: 'sent', 'failed', 'gone',
 * 'flag_off', 'truncated'.
 *
 * Retention: a cron task (push_log_retention, daily) purges rows older
 * than admin-configured retention_days (default 90).
 *
 * @package local_sentientia_pwa
 */
class push_logger {

    public const RESULT_SENT      = 'sent';
    public const RESULT_FAILED    = 'failed';
    public const RESULT_GONE      = 'gone';
    public const RESULT_FLAG_OFF  = 'flag_off';
    public const RESULT_TRUNCATED = 'truncated';

    /** Max body chars stored. Anything longer is truncated to limit PII surface. */
    public const BODY_TRUNCATE = 200;

    /**
     * Record one push delivery attempt.
     *
     * @param int    $userid
     * @param int|null $sub_id        Subscription row ID (may be null if it was deleted)
     * @param string $endpoint        Full push service endpoint URL (we extract host)
     * @param string $title
     * @param string $body
     * @param string $url             Click-through URL
     * @param string $tag             Collapse tag
     * @param int|null $http_code     HTTP response code, null on network error
     * @param string $result          One of RESULT_* constants
     * @param string|null $error_detail Optional exception/curl error message
     * @return int New log row ID, or 0 on insert failure
     */
    public static function log(int $userid, ?int $sub_id, string $endpoint,
                                string $title, string $body, string $url,
                                string $tag, ?int $http_code, string $result,
                                ?string $error_detail = null): int {
        global $DB;

        try {
            // Audit fix NB-11 (2026-05-22) — opt-in PII retention.
            // Reminder bodies often include employee names (e.g. "Hi
            // Anjali, your KYC Compliance course expires Monday"); the
            // log table previously kept these for 90 days by default.
            // Now: only the SHA-256 hash of (title + body) lands in the
            // DB unless the admin explicitly opts in to clear-text
            // retention via the `store_push_body_in_log` site config.
            // Forensics-mode deployments still have the option, but the
            // default is GDPR-safe.
            $store_body = (bool) get_config('local_sentientia_pwa',
                'store_push_body_in_log');

            $row = new \stdClass();
            $row->userid         = $userid;
            $row->sub_id         = $sub_id;
            $row->endpoint_host  = self::extract_host($endpoint);
            if ($store_body) {
                $row->title          = mb_substr($title, 0, 200);
                $row->body_truncated = mb_substr($body, 0, self::BODY_TRUNCATE);
            } else {
                // Hash so admins can still correlate by content shape
                // (same hash = same reminder template fired N times).
                $row->title          = '[hash:'
                    . substr(hash('sha256', $title . "\x00" . $body), 0, 16) . ']';
                $row->body_truncated = null;
            }
            $row->url            = mb_substr($url, 0, 500);
            $row->tag            = mb_substr($tag, 0, 100);
            $row->http_code      = $http_code;
            $row->result         = mb_substr($result, 0, 20);
            $row->error_detail   = $error_detail !== null
                ? mb_substr($error_detail, 0, 5000)
                : null;
            $row->sent_at        = time();

            return (int) $DB->insert_record('local_sentientia_push_log', $row);
        } catch (\Throwable $e) {
            // Logger failure must NOT propagate — push delivery is more
            // important than its audit trail. Surface to debug only.
            debugging(
                '[sentientia_pwa] push_logger::log failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return 0;
        }
    }

    /**
     * Recent rows for the admin viewer. Newest first.
     *
     * @param int $limit Page size
     * @param int $offset Offset for pagination
     * @param array $filters Optional: ['userid' => int, 'result' => string,
     *                                  'since' => int (unix timestamp)]
     * @return array Array of stdClass rows joined with user firstname+lastname.
     */
    public static function recent(int $limit = 50, int $offset = 0,
                                    array $filters = []): array {
        global $DB;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['userid'])) {
            $where[] = 'l.userid = :userid';
            $params['userid'] = (int) $filters['userid'];
        }
        if (!empty($filters['result'])) {
            $where[] = 'l.result = :result';
            $params['result'] = $filters['result'];
        }
        if (!empty($filters['since'])) {
            $where[] = 'l.sent_at >= :since';
            $params['since'] = (int) $filters['since'];
        }

        $sql = "SELECT l.*, u.firstname, u.lastname, u.email
                  FROM {local_sentientia_push_log} l
             LEFT JOIN {user} u ON u.id = l.userid
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY l.sent_at DESC, l.id DESC";

        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Count rows matching filters (for paginator).
     */
    public static function count(array $filters = []): int {
        global $DB;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['userid'])) {
            $where[] = 'userid = :userid';
            $params['userid'] = (int) $filters['userid'];
        }
        if (!empty($filters['result'])) {
            $where[] = 'result = :result';
            $params['result'] = $filters['result'];
        }
        if (!empty($filters['since'])) {
            $where[] = 'sent_at >= :since';
            $params['since'] = (int) $filters['since'];
        }
        $sql = "SELECT COUNT(*) FROM {local_sentientia_push_log}
                 WHERE " . implode(' AND ', $where);
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Aggregate stats for the admin dashboard widget.
     *
     * Returns:
     *   ['total_24h' => N, 'sent_24h' => N, 'failed_24h' => N,
     *    'gone_24h' => N, 'unique_users_24h' => N]
     */
    public static function stats_last_24h(): array {
        global $DB;
        $cutoff = time() - 86400;
        $row = $DB->get_record_sql(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN result = 'sent'   THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN result = 'gone'   THEN 1 ELSE 0 END) AS gone,
                    COUNT(DISTINCT userid) AS unique_users
               FROM {local_sentientia_push_log}
              WHERE sent_at >= :cutoff",
            ['cutoff' => $cutoff]
        );
        return [
            'total_24h'        => (int) ($row->total ?? 0),
            'sent_24h'         => (int) ($row->sent ?? 0),
            'failed_24h'       => (int) ($row->failed ?? 0),
            'gone_24h'         => (int) ($row->gone ?? 0),
            'unique_users_24h' => (int) ($row->unique_users ?? 0),
        ];
    }

    /**
     * Purge rows older than $days. Called by the retention cron.
     *
     * @param int $days
     * @return int Number of rows deleted.
     */
    public static function purge_older_than(int $days): int {
        global $DB;
        if ($days <= 0) {
            return 0;  // 0 = unlimited retention; skip purge
        }
        $cutoff = time() - ($days * 86400);
        $count = $DB->count_records_select('local_sentientia_push_log',
            'sent_at < :cutoff', ['cutoff' => $cutoff]);
        if ($count > 0) {
            $DB->delete_records_select('local_sentientia_push_log',
                'sent_at < :cutoff', ['cutoff' => $cutoff]);
        }
        return $count;
    }

    /**
     * Extract just the host part of an endpoint URL for grouping.
     */
    private static function extract_host(string $endpoint): string {
        $parts = parse_url($endpoint);
        return mb_substr($parts['host'] ?? 'unknown', 0, 100);
    }
}
