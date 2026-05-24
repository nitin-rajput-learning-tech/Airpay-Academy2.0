<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Encrypted-at-rest OAuth token storage — Tier 2.6 Phase 2.
 *
 * Wraps {local_sentientia_calendar_oauth} with a clean read/write API
 * and runs every access_token + refresh_token through {@see \core\encryption}
 * (Sodium AES-via-secretbox under the hood). Plaintext tokens never
 * appear in the database column, never appear in $DB log output, and
 * never appear in PHPUnit fixtures.
 *
 * Threat model
 * ------------
 * 1. DB dump leak → encrypted columns are opaque without
 *    `$CFG->dataroot/secret/key/sodium.key`. Recovering a token
 *    requires both the DB row AND that key file (chmod 0400 by Moodle).
 * 2. Process memory dump → plaintext is in PHP memory only for the
 *    request that fetched it. We do not statically cache decrypted
 *    values across requests.
 * 3. Privacy export → {@see provider::export_user_data()} emits
 *    `present`/`absent` flags + audit metadata; it deliberately does
 *    NOT include the plaintext access_token or refresh_token. The user
 *    knows the row exists; nobody else needs the value in their
 *    download archive.
 *
 * Round-trip contract
 * -------------------
 * For any string $T (including empty), {@see decrypt} ∘ {@see encrypt}
 * returns $T. Encryption of the empty string is a no-op (returns '')
 * per `\core\encryption::encrypt()`'s contract — we treat that as
 * "this user has no refresh_token yet" rather than as an error.
 *
 * @package local_sentientia_calendar
 */

namespace local_sentientia_calendar\oauth;

defined('MOODLE_INTERNAL') || die();

class token_vault {

    /** DB table holding the encrypted token rows. */
    public const TABLE = 'local_sentientia_calendar_oauth';

    /** Provider IDs allowed in the `provider` column (see oauth_base::PROVIDER). */
    public const PROVIDER_M365   = 'm365';
    public const PROVIDER_GOOGLE = 'google';

    /**
     * Whitelist of known providers — keep in sync with the *_oauth.php
     * subclasses of {@see oauth_base}. A row with provider outside this
     * list is treated as malformed (DB-level corruption) and rejected.
     *
     * @return string[]
     */
    public static function valid_providers(): array {
        return [self::PROVIDER_M365, self::PROVIDER_GOOGLE];
    }

    /**
     * Insert or update the user's tokens for one provider.
     *
     * The (userid, customerid, provider) triple is logically unique —
     * we model this with the `uk_user_provider` UNIQUE key on
     * (userid, provider). One row per provider per user; a second
     * connect from the same user UPDATES rather than inserting.
     *
     * @param int      $userid
     * @param int      $customerid     Sentientia customer (1 = Airpay) — orthogonal to tenants
     * @param string   $provider       Must be in {@see valid_providers()}
     * @param string   $access_token   Plaintext — encrypted before insert
     * @param string   $refresh_token  Plaintext (may be '' if provider didn't mint one)
     * @param int      $expires_at     Unix ts when the access_token expires
     * @param string   $scopes         Space-separated scope string returned by the provider
     * @return int     ID of the row that was inserted or updated
     * @throws \moodle_exception when provider is invalid
     */
    public static function store_tokens(int $userid, int $customerid, string $provider,
                                          string $access_token, string $refresh_token,
                                          int $expires_at, string $scopes): int {
        global $DB;

        self::assert_provider_valid($provider);

        $enc_access  = \core\encryption::encrypt($access_token);
        $enc_refresh = \core\encryption::encrypt($refresh_token);

        $now = time();
        $existing = $DB->get_record(self::TABLE, [
            'userid'   => $userid,
            'provider' => $provider,
        ]);

        if ($existing !== false) {
            $existing->customerid        = $customerid;
            $existing->access_token_enc  = $enc_access;
            $existing->refresh_token_enc = $enc_refresh;
            $existing->expires           = $expires_at;
            $existing->scopes            = $scopes;
            $existing->timemodified      = $now;
            $DB->update_record(self::TABLE, $existing);
            return (int) $existing->id;
        }

        $row = (object) [
            'userid'            => $userid,
            'customerid'        => $customerid,
            'provider'          => $provider,
            'access_token_enc'  => $enc_access,
            'refresh_token_enc' => $enc_refresh,
            'expires'           => $expires_at,
            'scopes'            => $scopes,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ];
        return (int) $DB->insert_record(self::TABLE, $row);
    }

    /**
     * Fetch + decrypt the stored tokens for one (user, provider) pair.
     *
     * Returns null when no row exists (treat as "user has not connected
     * this provider").
     *
     * The returned object carries plaintext access_token + refresh_token.
     * Callers should NOT pass this object around freely — clone/copy
     * just the fields you need and let the rest go out of scope.
     *
     * @param int    $userid
     * @param string $provider
     * @return \stdClass|null { id, userid, customerid, provider, access_token,
     *                          refresh_token, expires, scopes, timecreated,
     *                          timemodified } or null when no row.
     */
    public static function get_tokens(int $userid, string $provider): ?\stdClass {
        global $DB;
        self::assert_provider_valid($provider);

        $row = $DB->get_record(self::TABLE, [
            'userid'   => $userid,
            'provider' => $provider,
        ]);
        if ($row === false) {
            return null;
        }
        return (object) [
            'id'            => (int) $row->id,
            'userid'        => (int) $row->userid,
            'customerid'    => (int) $row->customerid,
            'provider'      => (string) $row->provider,
            'access_token'  => \core\encryption::decrypt((string) $row->access_token_enc),
            'refresh_token' => \core\encryption::decrypt((string) $row->refresh_token_enc),
            'expires'       => (int) $row->expires,
            'scopes'        => (string) $row->scopes,
            'timecreated'   => (int) $row->timecreated,
            'timemodified'  => (int) $row->timemodified,
        ];
    }

    /**
     * Does the user have stored tokens for this provider?
     *
     * Cheaper than {@see get_tokens} when the caller only needs the
     * boolean (e.g. rendering "Connect Outlook" vs "Disconnect Outlook"
     * on the settings page).
     *
     * @param int    $userid
     * @param string $provider
     * @return bool
     */
    public static function has_tokens(int $userid, string $provider): bool {
        global $DB;
        self::assert_provider_valid($provider);
        return $DB->record_exists(self::TABLE, [
            'userid'   => $userid,
            'provider' => $provider,
        ]);
    }

    /**
     * Delete the user's tokens for this provider.
     *
     * Note: this revokes ONLY our local copy. The provider may still
     * have a refresh_token alive on its side; Phase 2.1 will additionally
     * POST to the provider's revoke endpoint where one exists.
     *
     * @param int    $userid
     * @param string $provider
     * @return bool true when a row was deleted
     */
    public static function revoke_tokens(int $userid, string $provider): bool {
        global $DB;
        self::assert_provider_valid($provider);

        if (!$DB->record_exists(self::TABLE, ['userid' => $userid, 'provider' => $provider])) {
            return false;
        }
        $DB->delete_records(self::TABLE, ['userid' => $userid, 'provider' => $provider]);
        return true;
    }

    /**
     * Delete every OAuth token row for a user — used by the privacy
     * provider on a DPDP/GDPR right-to-erasure request.
     *
     * @param int $userid
     * @return int rows deleted
     */
    public static function delete_all_for_user(int $userid): int {
        global $DB;
        $count = $DB->count_records(self::TABLE, ['userid' => $userid]);
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
        return $count;
    }

    /**
     * Enumerate provider+expiry summary for one user — used by the
     * privacy export to surface "you have a Microsoft token stored" /
     * "you have a Google token stored" without leaking the actual tokens.
     *
     * @param int $userid
     * @return array<int, array{provider: string, expires: int, scopes: string,
     *                          timecreated: int, timemodified: int}>
     */
    public static function describe_for_user(int $userid): array {
        global $DB;
        $rows = $DB->get_records(self::TABLE, ['userid' => $userid],
            'provider ASC', 'id, provider, expires, scopes, timecreated, timemodified');
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'provider'     => (string) $row->provider,
                'expires'      => (int) $row->expires,
                'scopes'       => (string) $row->scopes,
                'timecreated'  => (int) $row->timecreated,
                'timemodified' => (int) $row->timemodified,
            ];
        }
        return $out;
    }

    /**
     * Verify the provider string is one of the known providers. Throws
     * a coding_exception otherwise — this is a programmer error, not a
     * user error.
     *
     * @param string $provider
     * @return void
     * @throws \coding_exception
     */
    private static function assert_provider_valid(string $provider): void {
        if (!in_array($provider, self::valid_providers(), true)) {
            throw new \coding_exception(
                "token_vault: unknown provider '$provider' — must be one of: "
                . implode(', ', self::valid_providers())
            );
        }
    }
}
