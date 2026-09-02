<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\webhooks;

defined('MOODLE_INTERNAL') || die();

/**
 * Delivery queue: drain with exponential backoff, dead-letter, retry, prune
 * (ADR-030 Wave A).
 *
 * Attempt schedule: fail #1 → +60 s, #2 → +300 s, #3 → +1800 s, #4 → +7200 s,
 * fail #5 → dead. A row whose subscription is gone/disabled, or whose tenant
 * has since turned webhooks OFF, is dead-lettered without a send — flag OFF
 * means nothing leaves the platform.
 *
 * @package local_sentientia_api
 */
class queue {

    /** @var string */
    public const TABLE = 'local_sentientia_api_whdel';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT   = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD   = 'dead';

    /** @var int[] Seconds to wait after failure number N (index N-1). */
    public const BACKOFF = [60, 300, 1800, 7200];

    /** @var int Failures before dead-letter. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Rows eligible for sending now.
     *
     * @param int $limit
     * @return \stdClass[]
     */
    public static function due(int $limit = 50): array {
        global $DB;
        return $DB->get_records_select(self::TABLE,
            "status IN ('queued','failed') AND nextattempt <= :now",
            ['now' => time()], 'nextattempt ASC, id ASC', '*', 0, $limit);
    }

    /**
     * Send everything due. Returns counters.
     *
     * @param int $limit
     * @return array{sent:int,failed:int,dead:int,skipped:int}
     */
    public static function drain(int $limit = 50): array {
        global $DB;
        $stats = ['sent' => 0, 'failed' => 0, 'dead' => 0, 'skipped' => 0];
        $subs = [];

        foreach (self::due($limit) as $row) {
            $now = time();
            if (!array_key_exists($row->subid, $subs)) {
                $subs[$row->subid] = $DB->get_record(subscription::TABLE, ['id' => $row->subid]) ?: null;
            }
            $sub = $subs[$row->subid];

            if (!$sub || empty($sub->enabled)) {
                self::mark_dead($row, 'subscription missing or disabled', $now);
                $stats['skipped']++;
                continue;
            }
            if (!dispatcher::enabled_for((int) $sub->costcenterid)) {
                self::mark_dead($row, 'webhooks disabled for tenant', $now);
                $stats['skipped']++;
                continue;
            }

            $result = sender::deliver($row, $sub);
            $row->attempts    = (int) $row->attempts + 1;
            $row->httpstatus  = $result['status'];
            $row->timeupdated = $now;

            if ($result['ok']) {
                $row->status    = self::STATUS_SENT;
                $row->lasterror = null;
                $DB->set_field(subscription::TABLE, 'lastsuccess', $now, ['id' => $sub->id]);
                $stats['sent']++;
            } else {
                $row->lasterror = $result['error'];
                $DB->set_field(subscription::TABLE, 'lastfailure', $now, ['id' => $sub->id]);
                if ($row->attempts >= self::MAX_ATTEMPTS) {
                    $row->status = self::STATUS_DEAD;
                    $stats['dead']++;
                } else {
                    $row->status      = self::STATUS_FAILED;
                    $idx              = min($row->attempts - 1, count(self::BACKOFF) - 1);
                    $row->nextattempt = $now + self::BACKOFF[$idx];
                    $stats['failed']++;
                }
            }
            $DB->update_record(self::TABLE, $row);
        }
        return $stats;
    }

    /**
     * Re-queue a dead/failed row for immediate retry (admin action).
     *
     * @param int $id
     * @return void
     */
    public static function retry(int $id): void {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        $row->status      = self::STATUS_QUEUED;
        $row->attempts    = 0;
        $row->nextattempt = time();
        $row->lasterror   = null;
        $row->timeupdated = time();
        $DB->update_record(self::TABLE, $row);
    }

    /**
     * Delete sent/dead rows older than the plugin's log retention.
     *
     * @return int Rows deleted
     */
    public static function prune(): int {
        global $DB;
        $days = (int) get_config('local_sentientia_api', 'log_retention_days');
        if ($days <= 0) {
            $days = 90;
        }
        $cutoff = time() - ($days * DAYSECS);
        $select = "status IN ('sent','dead') AND timeupdated < :cutoff";
        $count  = $DB->count_records_select(self::TABLE, $select, ['cutoff' => $cutoff]);
        $DB->delete_records_select(self::TABLE, $select, ['cutoff' => $cutoff]);
        return $count;
    }

    /**
     * Most recent deliveries for the admin log view.
     *
     * @param int $limit
     * @return \stdClass[]
     */
    public static function recent(int $limit = 50): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'timecreated DESC', '*', 0, $limit);
    }

    /**
     * Row counts per status.
     *
     * @return array<string,int>
     */
    public static function counts(): array {
        global $DB;
        $out = [self::STATUS_QUEUED => 0, self::STATUS_SENT => 0, self::STATUS_FAILED => 0, self::STATUS_DEAD => 0];
        $rows = $DB->get_records_sql("SELECT status, COUNT(1) AS n FROM {" . self::TABLE . "} GROUP BY status");
        foreach ($rows as $r) {
            $out[$r->status] = (int) $r->n;
        }
        return $out;
    }

    /**
     * @param \stdClass $row
     * @param string    $reason
     * @param int       $now
     * @return void
     */
    private static function mark_dead(\stdClass $row, string $reason, int $now): void {
        global $DB;
        $row->status      = self::STATUS_DEAD;
        $row->lasterror   = \core_text::substr($reason, 0, 255);
        $row->timeupdated = $now;
        $DB->update_record(self::TABLE, $row);
    }
}
