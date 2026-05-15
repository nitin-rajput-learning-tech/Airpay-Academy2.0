<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Channel analytics — Phase A1 iter 5.
 *
 * Reads from local_airpay_send_log to compute channel-mix metrics,
 * delivery rates, and cost estimates. Used by the admin dashboard.
 *
 * @package local_airpay_whatsapp
 */

namespace local_airpay_whatsapp;

defined('MOODLE_INTERNAL') || die();

class analytics {

    /**
     * Channel mix for a date range. Returns counts grouped by channel
     * and status.
     *
     * @param int $since  Unix timestamp; defaults to 30 days ago
     * @return array  [
     *   'whatsapp' => ['sent' => N, 'mocked' => N, 'failed' => N, ...],
     *   'sms'      => [...],
     *   'email'    => [...],
     *   'totals'   => ['attempted' => N, 'successful' => N, 'mocked_pct' => 87],
     * ]
     */
    public static function channel_mix(?int $since = null): array {
        global $DB;

        $since = $since ?? (time() - 30 * 86400);

        $rows = $DB->get_records_sql(
            "SELECT channel, status, COUNT(*) as n
               FROM {local_airpay_send_log}
              WHERE timecreated >= :since
           GROUP BY channel, status",
            ['since' => $since]
        );

        $result = [
            'whatsapp' => [],
            'sms'      => [],
            'email'    => [],
        ];
        $total = 0;
        $successful = 0;
        $mocked = 0;

        foreach ($rows as $r) {
            if (!isset($result[$r->channel])) {
                $result[$r->channel] = [];
            }
            $result[$r->channel][$r->status] = (int) $r->n;
            $total += (int) $r->n;
            if (in_array($r->status, ['sent', 'delivered', 'mocked'], true)) {
                $successful += (int) $r->n;
            }
            if ($r->status === 'mocked') {
                $mocked += (int) $r->n;
            }
        }

        $result['totals'] = [
            'attempted'   => $total,
            'successful'  => $successful,
            'mocked_pct'  => $total > 0 ? round(100 * $mocked / $total) : 0,
            'success_pct' => $total > 0 ? round(100 * $successful / $total) : 0,
        ];

        return $result;
    }

    /**
     * Estimated cost for the date range using the Phase A1 plan's
     * unit-cost assumptions:
     *   email                  ₹0.05 / msg
     *   sms                    ₹0.20 / msg
     *   whatsapp transactional ₹0.55 / msg
     * Costs are reported by the provider's webhook into the cost_paise
     * field; if missing, we estimate from the unit prices.
     *
     * @param int|null $since
     * @return array ['actual_paise' => N, 'estimated_paise' => N, 'total_inr' => N.NN]
     */
    public static function cost_summary(?int $since = null): array {
        global $DB;

        $since = $since ?? (time() - 30 * 86400);

        $actual = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(cost_paise), 0)
               FROM {local_airpay_send_log}
              WHERE timecreated >= :since
                AND cost_paise IS NOT NULL
                AND mock_mode = 0",
            ['since' => $since]
        );

        // Estimate from unit prices for rows without provider-reported cost.
        $unit_prices = ['whatsapp' => 55, 'sms' => 20, 'email' => 5];
        $rows = $DB->get_records_sql(
            "SELECT channel, COUNT(*) as n
               FROM {local_airpay_send_log}
              WHERE timecreated >= :since
                AND cost_paise IS NULL
                AND status IN ('sent', 'delivered')
                AND mock_mode = 0
           GROUP BY channel",
            ['since' => $since]
        );
        $estimated = 0;
        foreach ($rows as $r) {
            $unit = $unit_prices[$r->channel] ?? 0;
            $estimated += $unit * (int) $r->n;
        }

        $total_paise = $actual + $estimated;
        return [
            'actual_paise'    => $actual,
            'estimated_paise' => $estimated,
            'total_paise'     => $total_paise,
            'total_inr'       => round($total_paise / 100, 2),
        ];
    }

    /**
     * Most-recent N entries for the admin dashboard's live feed.
     */
    public static function recent_log(int $limit = 20): array {
        global $DB;
        return $DB->get_records('local_airpay_send_log', null,
            'timecreated DESC, id DESC', '*', 0, $limit);
    }
}
