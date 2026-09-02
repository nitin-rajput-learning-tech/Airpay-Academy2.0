<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

/**
 * Provisioning attestation log (ADR-030 Wave C): an append-only record of
 * every account change an identity provider made through SCIM — the
 * deprovisioning evidence enterprise buyers ask for in security reviews.
 *
 * Stores ids and an action only (no names/emails); joined to {user} at
 * display time. Pruned by the nightly cleanup task after the plugin's
 * log retention.
 *
 * @package local_sentientia_api
 */
class attestation {

    /** @var string */
    public const TABLE = 'local_sentientia_api_scimevt';

    public const CREATED     = 'created';
    public const REACTIVATED = 'reactivated';
    public const DEACTIVATED = 'deactivated';
    public const UPDATED     = 'updated';
    public const MOVED       = 'moved';

    /** @var string[] */
    public const ACTIONS = [self::CREATED, self::REACTIVATED, self::DEACTIVATED, self::UPDATED, self::MOVED];

    /**
     * Append one event. Never throws — attestation must not break provisioning.
     *
     * @param int         $cliid
     * @param int         $userid
     * @param string      $action     One of self::ACTIONS
     * @param string|null $externalid
     * @param string|null $detail     Short non-PII context (e.g. target org path)
     * @return void
     */
    public static function record(int $cliid, int $userid, string $action, ?string $externalid = null, ?string $detail = null): void {
        global $DB;
        if (!in_array($action, self::ACTIONS, true)) {
            $action = self::UPDATED;
        }
        try {
            $DB->insert_record(self::TABLE, (object) [
                'cliid'       => $cliid,
                'userid'      => $userid,
                'action'      => $action,
                'externalid'  => $externalid === null ? null : \core_text::substr($externalid, 0, 191),
                'detail'      => $detail === null ? null : \core_text::substr($detail, 0, 255),
                'timecreated' => time(),
            ]);
        } catch (\Throwable $e) {
            debugging('sentientia_api scim attestation write failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Recent events joined with user + client labels for display/export.
     *
     * @param int $limit
     * @param int $cliid 0 = all clients
     * @return \stdClass[] id, action, externalid, detail, timecreated, userid, username, firstname, lastname, clientname
     */
    public static function recent(int $limit = 100, int $cliid = 0): array {
        global $DB;
        $where = $cliid > 0 ? 'WHERE e.cliid = :cli' : '';
        $params = $cliid > 0 ? ['cli' => $cliid] : [];
        return $DB->get_records_sql(
            "SELECT e.id, e.cliid, e.userid, e.action, e.externalid, e.detail, e.timecreated,
                    u.username, u.firstname, u.lastname, c.name AS clientname
               FROM {" . self::TABLE . "} e
          LEFT JOIN {user} u ON u.id = e.userid
          LEFT JOIN {" . client::TABLE . "} c ON c.id = e.cliid
              $where
           ORDER BY e.timecreated DESC, e.id DESC",
            $params, 0, $limit);
    }

    /**
     * CSV body (header + rows) for the attestation export.
     *
     * @param int $limit
     * @return string
     */
    public static function to_csv(int $limit = 5000): string {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['time_utc', 'action', 'client', 'userid', 'username', 'externalid', 'detail']);
        foreach (self::recent($limit) as $r) {
            fputcsv($out, [
                gmdate('Y-m-d\TH:i:s\Z', (int) $r->timecreated),
                $r->action,
                (string) ($r->clientname ?? ''),
                (int) $r->userid,
                (string) ($r->username ?? ''),
                (string) ($r->externalid ?? ''),
                (string) ($r->detail ?? ''),
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    /**
     * Delete events older than the plugin's log retention.
     *
     * @return int
     */
    public static function prune(): int {
        global $DB;
        $days = (int) get_config('local_sentientia_api', 'log_retention_days');
        if ($days <= 0) {
            $days = 90;
        }
        $cutoff = time() - ($days * DAYSECS);
        $count = $DB->count_records_select(self::TABLE, 'timecreated < :c', ['c' => $cutoff]);
        $DB->delete_records_select(self::TABLE, 'timecreated < :c', ['c' => $cutoff]);
        return $count;
    }
}
