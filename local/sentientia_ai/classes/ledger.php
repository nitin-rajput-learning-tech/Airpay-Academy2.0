<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Spend ledger — every gateway call (mock, live, failed, denied) becomes
 * one row in local_sentientia_ai_ledger. The quota checks read live-row
 * aggregates from here; the index.php admin page renders it.
 *
 * Quota windows use calendar boundaries in SERVER time (a budgeting
 * convention, documented in the settings help): "today" = since local
 * midnight, "this month" = since the 1st, 00:00.
 *
 * @package local_sentientia_ai
 */
class ledger {

    /**
     * Record one gateway call.
     *
     * @param array $req Normalised request (client::normalise()).
     * @param string $mode mock|live|failed|denied
     * @param int $tokensin
     * @param int $tokensout
     * @param float $estcost Estimated USD
     * @param string $error Reason for failed/denied ('' otherwise)
     * @param string $model Model used ('' for mock/denied)
     * @return int New ledger row id
     */
    public static function record(array $req, string $mode, int $tokensin,
            int $tokensout, float $estcost, string $error, string $model = ''): int {
        global $DB;

        $row = new \stdClass();
        $row->component        = substr($req['component'], 0, 100);
        $row->purpose          = substr($req['purpose'], 0, 100);
        $row->userid           = (int) $req['userid'];
        $row->customerid       = (int) $req['customerid'];
        $row->tenantid         = (int) $req['tenantid'];
        $row->model            = substr($model, 0, 100);
        $row->prompttokens     = $tokensin;
        $row->completiontokens = $tokensout;
        $row->estcost          = $estcost;
        $row->mode             = $mode;
        $row->error            = substr($error, 0, 255);
        $row->timecreated      = time();

        return (int) $DB->insert_record('local_sentientia_ai_ledger', $row);
    }

    /**
     * Live tokens (prompt + completion) consumed since local midnight —
     * globally, or for one customer when $customerid > 0.
     *
     * Failed rows count too when they reported usage (tokens were billed
     * even if the body was unusable) — conservative by design.
     *
     * @param int $customerid 0 = global
     * @return int
     */
    public static function tokens_today(int $customerid = 0): int {
        global $DB;
        $params = [
            'since' => self::day_start(),
            'mock'  => 'mock',
            'denied' => 'denied',
        ];
        $customersql = '';
        if ($customerid > 0) {
            $customersql = ' AND customerid = :cid';
            $params['cid'] = $customerid;
        }
        return (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(prompttokens + completiontokens), 0)
               FROM {local_sentientia_ai_ledger}
              WHERE timecreated >= :since
                AND mode <> :mock AND mode <> :denied" . $customersql,
            $params);
    }

    /**
     * Estimated USD spend since the 1st of the current month (live +
     * token-reporting failed rows).
     *
     * @return float
     */
    public static function cost_this_month(): float {
        global $DB;
        return (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(estcost), 0)
               FROM {local_sentientia_ai_ledger}
              WHERE timecreated >= :since
                AND mode <> :mock AND mode <> :denied",
            ['since' => self::month_start(), 'mock' => 'mock', 'denied' => 'denied']);
    }

    /**
     * Per-component roll-up for the admin page.
     *
     * @param int $since Unix timestamp lower bound
     * @return array of records {component, calls, tokens, estcost}
     */
    public static function component_summary(int $since): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT component,
                    COUNT(1) AS calls,
                    SUM(prompttokens + completiontokens) AS tokens,
                    SUM(estcost) AS estcost
               FROM {local_sentientia_ai_ledger}
              WHERE timecreated >= :since
           GROUP BY component
           ORDER BY estcost DESC, calls DESC",
            ['since' => $since]);
    }

    /**
     * Unix timestamp of local midnight today.
     */
    public static function day_start(): int {
        return mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
    }

    /**
     * Unix timestamp of the 1st of the current month, 00:00 local.
     */
    public static function month_start(): int {
        return mktime(0, 0, 0, (int) date('n'), 1, (int) date('Y'));
    }
}
