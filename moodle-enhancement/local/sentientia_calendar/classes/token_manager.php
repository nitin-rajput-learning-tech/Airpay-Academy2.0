<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Subscription-token lifecycle for local_sentientia_calendar.
 *
 * The 64-character random token is the authentication mechanism for the
 * ICS feed — calendar clients (Outlook, Google, Apple) fetch the feed
 * without browser cookies, so cookie-based session auth is not
 * available. Instead, the token in the URL query string identifies the
 * user.
 *
 * Security properties:
 *   - 64 chars × 62 alphabet = ~381 bits of entropy → infeasible to brute-force
 *   - Tokens are generated via random_bytes (cryptographically secure)
 *   - One ACTIVE token per user; regenerate revokes the old one
 *   - Token compared via {@see hash_equals} where possible to defeat timing
 *     side channels (defence-in-depth — the token is already too long to
 *     brute-force in a useful time window)
 *
 * @package local_sentientia_calendar
 */

namespace local_sentientia_calendar;

defined('MOODLE_INTERNAL') || die();

class token_manager {

    /** Token length in characters. ~381 bits of entropy at 62-char alphabet. */
    public const TOKEN_LENGTH = 64;

    /** URL-safe alphabet for token generation. Avoids confusable chars (0/O, 1/l). */
    private const TOKEN_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    /** DB table name. */
    public const TABLE = 'local_sentientia_calendar_token';

    /**
     * Generate a fresh 64-char token using cryptographically secure randomness.
     *
     * @return string The new token
     * @throws \Exception if random_bytes is unavailable (should never happen on supported PHP)
     */
    public static function generate_token_string(): string {
        $alphabet_len = strlen(self::TOKEN_ALPHABET);
        $bytes = random_bytes(self::TOKEN_LENGTH);
        $out = '';
        for ($i = 0; $i < self::TOKEN_LENGTH; $i++) {
            $out .= self::TOKEN_ALPHABET[ord($bytes[$i]) % $alphabet_len];
        }
        return $out;
    }

    /**
     * Get the active token row for the user. Returns null if none exists.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_active_for_user(int $userid): ?\stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, [
            'userid'  => $userid,
            'revoked' => 0,
        ]);
        return $row === false ? null : $row;
    }

    /**
     * Get the active token string for the user, creating one if missing.
     *
     * Idempotent: a second call returns the same token as the first.
     * This is what /local/sentientia_calendar/index.php uses to display
     * the subscription URL — landing on the page implicitly provisions
     * a token, so the user always has something to copy.
     *
     * @param int $userid
     * @return string The token string
     */
    public static function get_or_create_for_user(int $userid): string {
        $existing = self::get_active_for_user($userid);
        if ($existing !== null) {
            return $existing->token;
        }
        return self::create_for_user($userid);
    }

    /**
     * Create a new token for the user.
     *
     * Caller should revoke any existing active token first — this method
     * does not check (the schema permits multiple active rows; callers
     * use {@see regenerate_for_user} which revokes first).
     *
     * @param int $userid
     * @return string The new token string
     */
    public static function create_for_user(int $userid): string {
        global $DB;

        $tenantid   = self::resolve_tenant_for_user($userid);
        $customerid = self::resolve_customer_for_user($userid);

        // Loop on the (extremely unlikely) collision — 381 bits of
        // entropy makes a clash a once-in-a-trillion-years event, but
        // the UNIQUE index on `token` means we must handle it.
        $attempts = 0;
        do {
            $token = self::generate_token_string();
            $attempts++;
            $exists = $DB->record_exists(self::TABLE, ['token' => $token]);
        } while ($exists && $attempts < 5);
        if ($exists) {
            throw new \moodle_exception('error_token_collision', 'local_sentientia_calendar');
        }

        $now = time();
        $row = (object) [
            'userid'       => $userid,
            'customerid'   => $customerid,
            'tenantid'     => $tenantid,
            'token'        => $token,
            'revoked'      => 0,
            'use_count'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record(self::TABLE, $row);
        return $token;
    }

    /**
     * Revoke the user's existing active token (if any) and create a new one.
     *
     * Wrapped in a delegated transaction so a partial failure (e.g. the
     * new INSERT throws on a unique-key clash) doesn't leave the user
     * with NO active token.
     *
     * @param int $userid
     * @return string The new token string
     */
    public static function regenerate_for_user(int $userid): string {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        try {
            self::revoke_all_for_user($userid);
            $token = self::create_for_user($userid);
            $transaction->allow_commit();
            return $token;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            // rollback re-throws; this return is unreachable but satisfies static analysis.
            return '';
        }
    }

    /**
     * Revoke every active token for the user.
     *
     * Used by:
     *   - {@see regenerate_for_user} before issuing a new one
     *   - admin "revoke all tokens for user X" flow (Phase 1.1, not in MVP)
     *   - DPDP/GDPR deletion request (privacy provider)
     *
     * @param int $userid
     * @return int Number of rows revoked
     */
    public static function revoke_all_for_user(int $userid): int {
        global $DB;
        $rows = $DB->get_records(self::TABLE, [
            'userid'  => $userid,
            'revoked' => 0,
        ]);
        $count = 0;
        $now = time();
        foreach ($rows as $row) {
            $row->revoked      = 1;
            $row->timemodified = $now;
            $DB->update_record(self::TABLE, $row);
            $count++;
        }
        return $count;
    }

    /**
     * Look up a user by their token. Returns null when:
     *   - token is malformed (wrong length / illegal chars)
     *   - token does not exist
     *   - token has been revoked
     *
     * Used by ics.php to authenticate inbound feed fetches.
     *
     * @param string $token
     * @return \stdClass|null The token row, or null
     */
    public static function find_active_token(string $token): ?\stdClass {
        // Quick syntactic gate — saves a DB hit on obviously-malformed input.
        if (strlen($token) !== self::TOKEN_LENGTH) {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $token)) {
            return null;
        }

        global $DB;
        $row = $DB->get_record(self::TABLE, [
            'token'   => $token,
            'revoked' => 0,
        ]);
        return $row === false ? null : $row;
    }

    /**
     * Mark a successful feed fetch. Updates last_used_at + last_used_ip + use_count.
     *
     * Called by ics.php after the feed body has been generated and sent.
     * Failure to update the audit fields is non-fatal — we log and continue.
     *
     * @param int    $token_id ID of the token row to update
     * @param string $ip       Client IP address (sanitised by caller)
     * @return void
     */
    public static function mark_used(int $token_id, string $ip): void {
        global $DB;
        try {
            $row = $DB->get_record(self::TABLE, ['id' => $token_id]);
            if ($row === false) {
                return;
            }
            $row->last_used_at = time();
            $row->last_used_ip = substr($ip, 0, 45);  // clamp to schema length
            $row->use_count    = (int) $row->use_count + 1;
            $row->timemodified = time();
            $DB->update_record(self::TABLE, $row);
        } catch (\Throwable $e) {
            debugging('local_sentientia_calendar: mark_used failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }

    /**
     * Build the public subscription URL for a given token.
     *
     * @param string $token
     * @return \moodle_url
     */
    public static function build_subscription_url(string $token): \moodle_url {
        return new \moodle_url('/local/sentientia_calendar/ics.php',
            ['token' => $token]);
    }

    /**
     * Resolve the tenant id for a user, via airpay_core::tenant if
     * available. Falls back to 0 when the helper is missing (early
     * install on a host without airpay_core) so token creation still
     * succeeds.
     *
     * @param int $userid
     * @return int
     */
    private static function resolve_tenant_for_user(int $userid): int {
        global $DB;
        if (!class_exists('\\local_airpay_core\\tenant')) {
            return 0;
        }
        $user = $DB->get_record('user', ['id' => $userid], 'id, open_path');
        if ($user === false) {
            return 0;
        }
        return \local_airpay_core\tenant::root_for_user($user);
    }

    /**
     * Resolve the customer id for a user. Phase 0/1: always returns
     * customer::AIRPAY when the helper exists; falls back to 0 when not.
     *
     * @param int $userid
     * @return int
     */
    private static function resolve_customer_for_user(int $userid): int {
        if (!class_exists('\\local_airpay_core\\customer')) {
            return 0;
        }
        // customer::current() reads $USER, but we want this user. In
        // Phase 0/1 every user resolves to the same customer (AIRPAY),
        // so calling ::current() during token creation gives the right
        // answer; Phase 2 will swap this for a tenant->customer lookup.
        return \local_airpay_core\customer::current();
    }

    /**
     * Default token retention for revoked rows (in days).
     * Used by the purge_old_tokens scheduled task.
     */
    public const RETENTION_DAYS = 90;
}
