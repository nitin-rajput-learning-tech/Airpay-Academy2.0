<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

/**
 * Append-only request log for the public API.
 *
 * Records WHO (userid), WHEN, WHICH tenant, WHICH endpoint, and the logical
 * status only. It deliberately stores NO request/response bodies and NO PII
 * beyond the user id — the log itself must never become a cross-tenant data
 * leak vector.
 *
 * @package local_sentientia_api
 */
class request_log {

    /**
     * Write one log row.
     *
     * @param int    $userid
     * @param int    $costcenterid Tenant root the request was scoped to
     * @param string $endpoint     External function name
     * @param string $method       Logical method GET|POST
     * @param int    $status       Logical status (200/403/429/500)
     * @param string $apiversion
     * @return void
     */
    public static function record(int $userid, int $costcenterid, string $endpoint,
                                   string $method, int $status, string $apiversion = 'v1'): void {
        global $DB;
        $DB->insert_record('local_sentientia_api_log', (object) [
            'userid'       => $userid,
            'costcenterid' => $costcenterid,
            'apiversion'   => $apiversion,
            'endpoint'     => \core_text::substr($endpoint, 0, 120),
            'httpmethod'   => $method === 'POST' ? 'POST' : 'GET',
            'status'       => $status,
            'timecreated'  => time(),
        ]);
    }

    /**
     * Prune log rows older than the configured retention. Called by cron.
     *
     * @return int Number of rows deleted.
     */
    public static function prune(): int {
        global $DB;
        $days = (int) get_config('local_sentientia_api', 'log_retention_days');
        if ($days <= 0) {
            $days = 90;
        }
        $cutoff = time() - ($days * DAYSECS);
        $count = $DB->count_records_select('local_sentientia_api_log',
            'timecreated < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select('local_sentientia_api_log',
            'timecreated < :cutoff', ['cutoff' => $cutoff]);
        return $count;
    }
}
