<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

/**
 * Fixed-window per-user rate limiter for the public API.
 *
 * Strategy: a counter row per (userid, window-start). The window start is
 * computed by flooring now() to the window length. On each call we upsert
 * the counter; if hits would exceed the budget, we throw a 429-style
 * exception. Concurrency is handled with an INSERT-then-UPDATE pattern and
 * the unique (userid, windowstart) index so two racing requests can't
 * create duplicate rows.
 *
 * Rate-limit awareness (per the gap spec): callers learn their remaining
 * budget + reset time via {@see headers()}, which the REST layer can echo
 * as X-RateLimit-* response headers.
 *
 * @package local_sentientia_api
 */
class rate_limiter {

    /**
     * Budget (max requests) for a window. Reads admin config, falls back to 600.
     *
     * @return int
     */
    public static function budget(): int {
        $v = (int) get_config('local_sentientia_api', 'rate_limit');
        return $v > 0 ? $v : 600;
    }

    /**
     * Window length in seconds. Reads admin config, falls back to 60.
     *
     * @return int
     */
    public static function window(): int {
        $v = (int) get_config('local_sentientia_api', 'rate_window');
        return $v > 0 ? $v : 60;
    }

    /**
     * Record one hit for the user and enforce the budget.
     *
     * @param int $userid
     * @return void
     * @throws \moodle_exception 'ratelimited' when the budget is exceeded.
     */
    public static function check_and_increment(int $userid): void {
        global $DB;

        if ($userid <= 0) {
            // Anonymous calls should never reach here (loginrequired=true),
            // but fail safe: deny rather than allow an unmetered request.
            throw new \moodle_exception('error_notauthenticated', 'local_sentientia_api');
        }

        $window = self::window();
        $now = time();
        $windowstart = $now - ($now % $window);

        $existing = $DB->get_record('local_sentientia_api_rate', [
            'userid'      => $userid,
            'windowstart' => $windowstart,
        ]);

        if (!$existing) {
            // Try to create the counter. A concurrent request may win the
            // race; catch the unique-key violation and fall through to update.
            try {
                $DB->insert_record('local_sentientia_api_rate', (object) [
                    'userid'       => $userid,
                    'windowstart'  => $windowstart,
                    'hits'         => 1,
                    'timemodified' => $now,
                ]);
                return;
            } catch (\dml_exception $e) {
                $existing = $DB->get_record('local_sentientia_api_rate', [
                    'userid'      => $userid,
                    'windowstart' => $windowstart,
                ]);
                if (!$existing) {
                    throw $e;  // genuinely unexpected
                }
            }
        }

        if ((int) $existing->hits >= self::budget()) {
            throw new \moodle_exception('ratelimited', 'local_sentientia_api', '',
                self::budget());
        }

        // Atomic increment via SQL so concurrent hits each count.
        $DB->execute(
            "UPDATE {local_sentientia_api_rate}
                SET hits = hits + 1, timemodified = :now
              WHERE id = :id",
            ['now' => $now, 'id' => $existing->id]
        );
    }

    /**
     * Current rate-limit headers for a user (for the REST layer to echo).
     *
     * @param int $userid
     * @return array{limit:int, remaining:int, reset:int}
     */
    public static function headers(int $userid): array {
        global $DB;
        $window = self::window();
        $budget = self::budget();
        $now = time();
        $windowstart = $now - ($now % $window);
        $row = $DB->get_record('local_sentientia_api_rate', [
            'userid'      => $userid,
            'windowstart' => $windowstart,
        ]);
        $hits = $row ? (int) $row->hits : 0;
        return [
            'limit'     => $budget,
            'remaining' => max(0, $budget - $hits),
            'reset'     => $windowstart + $window,
        ];
    }

    /**
     * Prune counter rows older than a couple of windows. Called by cron.
     *
     * @return int Number of rows deleted.
     */
    public static function prune(): int {
        global $DB;
        $cutoff = time() - (self::window() * 4);
        $count = $DB->count_records_select('local_sentientia_api_rate',
            'windowstart < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select('local_sentientia_api_rate',
            'windowstart < :cutoff', ['cutoff' => $cutoff]);
        return $count;
    }
}
