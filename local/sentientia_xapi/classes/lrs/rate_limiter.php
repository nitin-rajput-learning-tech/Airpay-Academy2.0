<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\lrs;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-client fixed-window rate limiter for the LRS statements endpoint.
 *
 * H3 fix — UAT-SECURITY-POSTURE-2026-09-03: the xAPI LRS is an
 * internet-reachable, Bearer/Basic-authenticated WRITE endpoint with no
 * rate limiting, unlike the SCIM endpoint in the same plugin family
 * (`local_sentientia_api\scim\client::rate_check()`, itself backed by
 * `local_sentientia_api\rate_limiter`). This class mirrors that exact
 * technique — one counter row per (client, window-start), atomically
 * incremented, budget exceeded => reject — generalised to a dedicated
 * table instead of columns on {local_sentientia_xapi_clients}, because
 * unlike SCIM this endpoint also accepts a site-wide fallback credential
 * (see lrs\authenticator::SITE_BEARER_CLIENTID / SITE_BASIC_CLIENTID)
 * that has no client row of its own to carry counter columns.
 *
 * Concurrency is handled the same way as the SCIM/API rate limiters:
 * INSERT-then-UPDATE with a unique (clientid, windowstart) index so two
 * racing requests can't create duplicate counter rows.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {

    /** @var string DB table holding the fixed-window counters. */
    private const TABLE = 'local_sentientia_xapi_lrs_rate';

    /**
     * Budget (max requests per window). Reads admin config, falls back
     * to 600 — the same default as local_sentientia_api's rate_limiter.
     */
    public static function budget(): int {
        $v = (int) get_config('local_sentientia_xapi', 'lrs_rate_limit');
        return $v > 0 ? $v : 600;
    }

    /**
     * Window length in seconds. Reads admin config, falls back to 60.
     */
    public static function window(): int {
        $v = (int) get_config('local_sentientia_xapi', 'lrs_rate_window');
        return $v > 0 ? $v : 60;
    }

    /**
     * Record one hit for the client and enforce the budget. MUST be
     * called before any statement body is parsed or stored, and only
     * after authentication has already succeeded (unauthenticated
     * requests get 401, never a rate-limit check).
     *
     * @param int $clientid Positive = a {local_sentientia_xapi_clients}
     *                       row id; negative = one of the
     *                       authenticator::SITE_*_CLIENTID sentinels.
     *                       0 (unauthenticated) is refused defensively.
     * @throws rate_limit_exceeded when the client is over budget.
     */
    public static function check_and_increment(int $clientid): void {
        global $DB;

        if ($clientid === 0) {
            // Should never be reached — statements.php returns 401 before
            // this runs. Fail safe: deny rather than allow an unmetered
            // request through on a code-path change elsewhere.
            throw new rate_limit_exceeded(self::budget(), self::window());
        }

        $window = self::window();
        $now = time();
        $windowstart = $now - ($now % $window);

        $existing = $DB->get_record(self::TABLE, [
            'clientid'    => $clientid,
            'windowstart' => $windowstart,
        ]);

        if (!$existing) {
            // Try to create the counter. A concurrent request may win the
            // race; catch the unique-key violation and fall through to update.
            try {
                $DB->insert_record(self::TABLE, (object) [
                    'clientid'     => $clientid,
                    'windowstart'  => $windowstart,
                    'hits'         => 1,
                    'timemodified' => $now,
                ]);
                return;
            } catch (\dml_exception $e) {
                $existing = $DB->get_record(self::TABLE, [
                    'clientid'    => $clientid,
                    'windowstart' => $windowstart,
                ]);
                if (!$existing) {
                    throw $e;  // genuinely unexpected
                }
            }
        }

        if ((int) $existing->hits >= self::budget()) {
            throw new rate_limit_exceeded(self::budget(), $windowstart + $window - $now);
        }

        // Atomic increment via SQL so concurrent hits each count.
        $DB->execute(
            "UPDATE {" . self::TABLE . "}
                SET hits = hits + 1, timemodified = :now
              WHERE id = :id",
            ['now' => $now, 'id' => $existing->id]
        );
    }

    /**
     * Prune counter rows old enough that their window has fully lapsed.
     * Called from the existing purge_old_statements scheduled task so
     * this fix doesn't need a brand-new cron entry.
     *
     * @return int Number of rows deleted.
     */
    public static function prune(): int {
        global $DB;
        $cutoff = time() - (self::window() * 4);
        $count = $DB->count_records_select(self::TABLE, 'windowstart < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select(self::TABLE, 'windowstart < :cutoff', ['cutoff' => $cutoff]);
        return $count;
    }
}
