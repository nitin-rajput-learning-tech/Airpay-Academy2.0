<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Tier 2.6 Phase 2 — OAuth scaffolding tests.
 *
 * Covers the three acceptance bars for the Phase 2 chip:
 *   1. Encrypted-at-rest token round-trip via {@see token_vault}
 *      (store → fetch → matches plaintext).
 *   2. Master feature flag (`sentientia.calendar_sync.oauth.enabled`)
 *      gates {@see oauth_base::build_authorize_url} and
 *      {@see oauth_base::handle_callback}.
 *   3. Privacy provider exports the OAuth row metadata while REDACTING
 *      the encrypted token columns, and deletes them on right-to-erasure.
 *
 * Also locks in two protocol invariants because they're easy to break
 * accidentally in Phase 2.1 wire-up: the PKCE verifier conforms to
 * RFC 7636's regex, and the S256 challenge is a base64url-encoded
 * SHA-256 digest of the verifier.
 *
 * @package local_sentientia_calendar
 * @category test
 * @covers \local_sentientia_calendar\oauth\oauth_base
 * @covers \local_sentientia_calendar\oauth\m365_oauth
 * @covers \local_sentientia_calendar\oauth\google_oauth
 * @covers \local_sentientia_calendar\oauth\token_vault
 * @covers \local_sentientia_calendar\privacy\provider
 */

namespace local_sentientia_calendar;

use local_sentientia_calendar\oauth\oauth_base;
use local_sentientia_calendar\oauth\m365_oauth;
use local_sentientia_calendar\oauth\google_oauth;
use local_sentientia_calendar\oauth\token_vault;

defined('MOODLE_INTERNAL') || die();

final class token_vault_test extends \advanced_testcase {

    /** @var int A test user we create in setUp. */
    private int $userid = 0;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->userid = (int) $user->id;
    }

    public function tearDown(): void {
        // The HTTP handler is a static on oauth_base — resetAfterTest()
        // does NOT clear static class state, so a mock leaking into the
        // next test (or into a live run) would be a footgun. Reset it.
        oauth_base::set_http_handler_for_testing(null);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────
    // (1) Encrypted-at-rest round-trip.
    // ─────────────────────────────────────────────────────────────────

    public function test_store_and_get_round_trips_access_and_refresh(): void {
        $access  = 'mock-access-token-' . bin2hex(random_bytes(8));
        $refresh = 'mock-refresh-token-' . bin2hex(random_bytes(8));
        $expires = time() + 3600;
        $scopes  = 'openid profile offline_access https://graph.microsoft.com/Calendars.ReadWrite';

        $id = token_vault::store_tokens($this->userid, 1,
            token_vault::PROVIDER_M365, $access, $refresh, $expires, $scopes);
        $this->assertGreaterThan(0, $id);

        $back = token_vault::get_tokens($this->userid, token_vault::PROVIDER_M365);
        $this->assertNotNull($back);
        $this->assertSame($access, $back->access_token,
            'Round-trip must preserve access_token plaintext');
        $this->assertSame($refresh, $back->refresh_token,
            'Round-trip must preserve refresh_token plaintext');
        $this->assertSame($expires, $back->expires);
        $this->assertSame($scopes,  $back->scopes);
        $this->assertSame(token_vault::PROVIDER_M365, $back->provider);
    }

    public function test_db_columns_are_NOT_plaintext(): void {
        global $DB;
        $access = 'should-not-appear-in-db-' . bin2hex(random_bytes(8));
        token_vault::store_tokens($this->userid, 1,
            token_vault::PROVIDER_M365, $access, '', time() + 3600, 'openid');

        $raw = $DB->get_record(token_vault::TABLE, ['userid' => $this->userid]);
        $this->assertNotFalse($raw);
        $this->assertNotSame($access, $raw->access_token_enc,
            'Encrypted column must not equal plaintext');
        $this->assertStringNotContainsString($access, (string) $raw->access_token_enc,
            'Encrypted column must not contain plaintext substring');
    }

    public function test_empty_refresh_token_round_trips_as_empty_string(): void {
        token_vault::store_tokens($this->userid, 1,
            token_vault::PROVIDER_GOOGLE, 'access-only', '',
            time() + 3600, 'https://www.googleapis.com/auth/calendar.events.owned');

        $back = token_vault::get_tokens($this->userid, token_vault::PROVIDER_GOOGLE);
        $this->assertNotNull($back);
        $this->assertSame('', $back->refresh_token,
            'Empty refresh_token plaintext must round-trip as empty string');
    }

    public function test_update_replaces_in_place_keeps_unique_user_provider_key(): void {
        global $DB;
        token_vault::store_tokens($this->userid, 1, token_vault::PROVIDER_M365,
            'first', 'r1', 1000, 'openid');
        $first_id = (int) $DB->get_field(token_vault::TABLE, 'id',
            ['userid' => $this->userid, 'provider' => 'm365']);

        token_vault::store_tokens($this->userid, 1, token_vault::PROVIDER_M365,
            'second', 'r2', 2000, 'openid profile');
        $second_id = (int) $DB->get_field(token_vault::TABLE, 'id',
            ['userid' => $this->userid, 'provider' => 'm365']);

        $this->assertSame($first_id, $second_id,
            'Re-store must UPDATE the same row, not INSERT a second');
        $back = token_vault::get_tokens($this->userid, token_vault::PROVIDER_M365);
        $this->assertSame('second', $back->access_token);
        $this->assertSame(2000, $back->expires);
    }

    public function test_user_isolation_per_provider(): void {
        $other = (int) $this->getDataGenerator()->create_user()->id;
        token_vault::store_tokens($this->userid, 1, token_vault::PROVIDER_M365,
            'mine', 'r1', time() + 3600, 'openid');
        token_vault::store_tokens($other, 1, token_vault::PROVIDER_M365,
            'theirs', 'r2', time() + 3600, 'openid');

        $this->assertSame('mine',
            token_vault::get_tokens($this->userid, 'm365')->access_token);
        $this->assertSame('theirs',
            token_vault::get_tokens($other, 'm365')->access_token);
    }

    public function test_two_providers_for_same_user_coexist(): void {
        token_vault::store_tokens($this->userid, 1, token_vault::PROVIDER_M365,
            'ms-token', 'ms-refresh', time() + 3600, 'openid');
        token_vault::store_tokens($this->userid, 1, token_vault::PROVIDER_GOOGLE,
            'g-token', 'g-refresh', time() + 3600, 'calendar.events.owned');

        $this->assertSame('ms-token',
            token_vault::get_tokens($this->userid, 'm365')->access_token);
        $this->assertSame('g-token',
            token_vault::get_tokens($this->userid, 'google')->access_token);
    }

    public function test_has_tokens_and_revoke(): void {
        $this->assertFalse(token_vault::has_tokens($this->userid, 'm365'));
        token_vault::store_tokens($this->userid, 1, 'm365', 'a', 'r',
            time() + 3600, 'openid');
        $this->assertTrue(token_vault::has_tokens($this->userid, 'm365'));

        $this->assertTrue(token_vault::revoke_tokens($this->userid, 'm365'));
        $this->assertFalse(token_vault::has_tokens($this->userid, 'm365'));
        $this->assertFalse(token_vault::revoke_tokens($this->userid, 'm365'),
            'Second revoke must report "no row" (false)');
    }

    public function test_invalid_provider_throws(): void {
        $this->expectException(\coding_exception::class);
        token_vault::store_tokens($this->userid, 1, 'evil', 'a', 'r',
            time() + 3600, 'openid');
    }

    public function test_get_tokens_returns_null_when_absent(): void {
        $this->assertNull(
            token_vault::get_tokens($this->userid, token_vault::PROVIDER_GOOGLE));
    }

    public function test_describe_for_user_omits_encrypted_columns(): void {
        token_vault::store_tokens($this->userid, 1, 'm365', 'secret-access',
            'secret-refresh', 9999, 'scope-a');
        token_vault::store_tokens($this->userid, 1, 'google', 'secret-google',
            'secret-g-refresh', 8888, 'scope-b');

        $rows = token_vault::describe_for_user($this->userid);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('access_token', $row);
            $this->assertArrayNotHasKey('refresh_token', $row);
            $this->assertArrayNotHasKey('access_token_enc', $row);
            $this->assertArrayNotHasKey('refresh_token_enc', $row);
            // Provider + expiry + scopes are surfaced — that's the
            // intentional metadata content.
            $this->assertArrayHasKey('provider', $row);
            $this->assertArrayHasKey('expires', $row);
            $this->assertArrayHasKey('scopes', $row);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // (2) Feature-flag toggle.
    // ─────────────────────────────────────────────────────────────────

    public function test_build_authorize_url_blocked_when_flag_off(): void {
        // Default in db/feature_flags.php is OFF — no override set.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Calendar sync is not currently enabled|Phase 2/');
        m365_oauth::build_authorize_url($this->userid);
    }

    public function test_build_authorize_url_blocked_for_google_when_flag_off(): void {
        $this->expectException(\moodle_exception::class);
        google_oauth::build_authorize_url($this->userid);
    }

    public function test_build_authorize_url_blocked_when_client_id_missing_even_with_flag_on(): void {
        $this->enable_oauth_flag();
        // No microsoft_client_id set — must still refuse.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/client ID is not configured/');
        m365_oauth::build_authorize_url($this->userid);
    }

    public function test_build_authorize_url_succeeds_when_flag_on_and_client_id_set(): void {
        $this->enable_oauth_flag();
        set_config('microsoft_client_id', 'azure-test-app-id',
            'local_sentientia_calendar');

        $url = m365_oauth::build_authorize_url($this->userid);
        $this->assertInstanceOf(\moodle_url::class, $url);

        $out = $url->out(false);
        $this->assertStringContainsString('login.microsoftonline.com', $out);
        $this->assertStringContainsString('client_id=azure-test-app-id', $out);
        $this->assertStringContainsString('response_type=code', $out);
        $this->assertStringContainsString('code_challenge_method=S256', $out);
        // PKCE challenge must be present and base64url-encoded.
        $this->assertMatchesRegularExpression('/code_challenge=[A-Za-z0-9_-]{43,}/', $out);
        // State must be present and ~43 base64url chars.
        $this->assertMatchesRegularExpression('/state=[A-Za-z0-9_-]{43}/', $out);
    }

    public function test_google_authorize_url_has_offline_and_consent(): void {
        $this->enable_oauth_flag();
        set_config('google_client_id', 'google-test-app-id.apps.googleusercontent.com',
            'local_sentientia_calendar');

        $url = google_oauth::build_authorize_url($this->userid);
        $out = $url->out(false);
        $this->assertStringContainsString('accounts.google.com', $out);
        $this->assertStringContainsString('access_type=offline', $out,
            'Google flow must request offline access to mint a refresh token');
        $this->assertStringContainsString('prompt=consent', $out,
            'Google flow must force consent to ensure refresh_token is reissued');
    }

    public function test_handle_callback_exchanges_code_and_stores_tokens(): void {
        $this->enable_oauth_flag();

        // Mock the token endpoint to return a canned token response — no
        // live HTTP. The handler asserts we POST the right grant + the
        // recovered PKCE verifier.
        oauth_base::set_http_handler_for_testing(function (string $url, array $params): array {
            $this->assertStringContainsString('login.microsoftonline.com', $url);
            $this->assertSame('authorization_code', $params['grant_type']);
            $this->assertSame('mock-auth-code', $params['code']);
            $this->assertArrayHasKey('code_verifier', $params);
            return [
                'http_code' => 200,
                'body'      => json_encode([
                    'access_token'  => 'fresh-access',
                    'refresh_token' => 'fresh-refresh',
                    'expires_in'    => 3600,
                    'scope'         => 'openid profile https://graph.microsoft.com/Calendars.ReadWrite',
                ]),
            ];
        });

        $verifier = oauth_base::generate_pkce_verifier();
        oauth_base::store_pending_state($this->userid, 'm365', 'state-XYZ', $verifier);

        m365_oauth::handle_callback($this->userid, 'mock-auth-code', 'state-XYZ');

        $stored = token_vault::get_tokens($this->userid, 'm365');
        $this->assertNotNull($stored);
        $this->assertSame('fresh-access', $stored->access_token);
        $this->assertSame('fresh-refresh', $stored->refresh_token);
        $this->assertGreaterThan(time() + 3000, $stored->expires);
    }

    public function test_refresh_token_replaces_access_token(): void {
        $this->enable_oauth_flag();
        token_vault::store_tokens($this->userid, 1, 'm365',
            'old-access', 'refresh-value', time() - 10, 'openid');

        oauth_base::set_http_handler_for_testing(function (string $url, array $params): array {
            $this->assertSame('refresh_token', $params['grant_type']);
            $this->assertSame('refresh-value', $params['refresh_token']);
            return [
                'http_code' => 200,
                'body'      => json_encode([
                    'access_token' => 'rotated-access',
                    'expires_in'   => 3600,
                ]),
            ];
        });

        m365_oauth::refresh_token($this->userid);

        $stored = token_vault::get_tokens($this->userid, 'm365');
        $this->assertSame('rotated-access', $stored->access_token);
        // Provider sent no new refresh_token → existing one is kept.
        $this->assertSame('refresh-value', $stored->refresh_token);
        $this->assertGreaterThan(time() + 3000, $stored->expires);
    }

    public function test_refresh_token_throws_no_refresh_token_when_row_missing(): void {
        $this->enable_oauth_flag();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/No stored refresh token/');
        m365_oauth::refresh_token($this->userid);
    }

    // ─────────────────────────────────────────────────────────────────
    // (3) Privacy provider — export + delete.
    // ─────────────────────────────────────────────────────────────────

    public function test_get_contexts_for_userid_picks_up_oauth_rows(): void {
        token_vault::store_tokens($this->userid, 1, 'm365',
            'a', 'r', time() + 3600, 'openid');

        $contextlist = privacy\provider::get_contexts_for_userid($this->userid);
        $contexts = iterator_to_array($contextlist->get_iterator());
        $this->assertNotEmpty($contexts,
            'User with an OAuth row must surface a context');
        $found = false;
        foreach ($contextlist as $ctx) {
            if ($ctx->contextlevel === CONTEXT_USER
                && (int) $ctx->instanceid === $this->userid) {
                $found = true;
            }
        }
        $this->assertTrue($found,
            'User context must be present in the contextlist');
    }

    public function test_export_user_data_redacts_encrypted_columns(): void {
        token_vault::store_tokens($this->userid, 1, 'm365',
            'secret-access-token', 'secret-refresh-token',
            123456, 'openid profile');

        $context = \context_user::instance($this->userid);
        $approved = new \core_privacy\local\request\approved_contextlist(
            \core_user::get_user($this->userid),
            'local_sentientia_calendar',
            [$context->id]
        );
        privacy\provider::export_user_data($approved);

        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $exported = $writer->get_data([
            get_string('pluginname', 'local_sentientia_calendar')
        ]);
        $this->assertObjectHasProperty('oauth_tokens', $exported);
        $this->assertCount(1, $exported->oauth_tokens);

        $row = $exported->oauth_tokens[0];
        $this->assertSame('m365', $row['provider']);
        $this->assertSame('openid profile', $row['scopes']);
        $this->assertSame(123456, $row['expires']);
        // The encrypted columns must NEVER reach the export — they're
        // long-lived credentials the user shouldn't have copies of in
        // their downloaded archive.
        $this->assertStringContainsString('REDACTED',
            (string) $row['access_token_enc']);
        $this->assertStringContainsString('REDACTED',
            (string) $row['refresh_token_enc']);
        // And the plaintext must never appear under any key name.
        $exportstr = json_encode($exported);
        $this->assertStringNotContainsString('secret-access-token', $exportstr);
        $this->assertStringNotContainsString('secret-refresh-token', $exportstr);
    }

    public function test_delete_data_for_user_drops_oauth_rows(): void {
        global $DB;
        token_vault::store_tokens($this->userid, 1, 'm365',
            'a', 'r', time() + 3600, 'openid');
        token_vault::store_tokens($this->userid, 1, 'google',
            'a', 'r', time() + 3600, 'calendar.events.owned');
        $this->assertSame(2, $DB->count_records(token_vault::TABLE,
            ['userid' => $this->userid]));

        $context = \context_user::instance($this->userid);
        $approved = new \core_privacy\local\request\approved_contextlist(
            \core_user::get_user($this->userid),
            'local_sentientia_calendar',
            [$context->id]
        );
        privacy\provider::delete_data_for_user($approved);

        $this->assertSame(0, $DB->count_records(token_vault::TABLE,
            ['userid' => $this->userid]));
    }

    public function test_delete_data_for_all_users_in_context_drops_oauth_rows(): void {
        global $DB;
        token_vault::store_tokens($this->userid, 1, 'm365',
            'a', 'r', time() + 3600, 'openid');
        $context = \context_user::instance($this->userid);

        privacy\provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records(token_vault::TABLE,
            ['userid' => $this->userid]));
    }

    public function test_metadata_declares_both_tables_and_both_providers(): void {
        $collection = new \core_privacy\local\metadata\collection(
            'local_sentientia_calendar');
        $collection = privacy\provider::get_metadata($collection);
        $items = $collection->get_collection();

        $names = [];
        foreach ($items as $it) {
            $names[] = $it->get_name();
        }
        $this->assertContains('local_sentientia_calendar_token', $names);
        $this->assertContains('local_sentientia_calendar_oauth', $names);
        $this->assertContains('microsoft_graph', $names);
        $this->assertContains('google_calendar', $names);
    }

    // ─────────────────────────────────────────────────────────────────
    // PKCE + state-token invariants.
    // ─────────────────────────────────────────────────────────────────

    public function test_pkce_verifier_conforms_to_rfc7636_alphabet(): void {
        for ($i = 0; $i < 50; $i++) {
            $verifier = oauth_base::generate_pkce_verifier();
            // RFC 7636 §4.1: 43..128 chars, [A-Za-z0-9-._~].
            $this->assertGreaterThanOrEqual(43, strlen($verifier));
            $this->assertLessThanOrEqual(128, strlen($verifier));
            $this->assertMatchesRegularExpression(
                '/^[A-Za-z0-9._~-]+$/', $verifier,
                'PKCE verifier alphabet must match RFC 7636 §4.1');
        }
    }

    public function test_pkce_challenge_is_s256_of_verifier(): void {
        $verifier = oauth_base::generate_pkce_verifier();
        $challenge = oauth_base::generate_pkce_challenge($verifier);
        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)),
            '+/', '-_'), '=');
        $this->assertSame($expected, $challenge,
            'S256 challenge must equal base64url(sha256(verifier))');
    }

    public function test_state_token_is_csrf_grade_entropy(): void {
        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $token = oauth_base::generate_state_token();
            $this->assertSame(43, strlen($token),
                'State token must be 43-char base64url (32 bytes)');
            $this->assertArrayNotHasKey($token, $seen,
                'State tokens must be unique across calls');
            $seen[$token] = true;
        }
    }

    public function test_consume_pending_state_round_trip_and_csrf_mismatch(): void {
        $verifier = oauth_base::generate_pkce_verifier();
        oauth_base::store_pending_state($this->userid, 'm365',
            'real-state', $verifier);

        // Wrong state — must return null AND consume the entry.
        $this->assertNull(
            oauth_base::consume_pending_state($this->userid, 'm365', 'wrong-state'));
        // Second call with the right state must also return null because
        // the failed first call consumed the entry (single-use).
        $this->assertNull(
            oauth_base::consume_pending_state($this->userid, 'm365', 'real-state'));
    }

    public function test_consume_pending_state_success_returns_verifier(): void {
        $verifier = oauth_base::generate_pkce_verifier();
        oauth_base::store_pending_state($this->userid, 'google',
            'state-correct', $verifier);

        $back = oauth_base::consume_pending_state($this->userid, 'google',
            'state-correct');
        $this->assertSame($verifier, $back);

        // Subsequent call must return null — single-use enforcement.
        $this->assertNull(oauth_base::consume_pending_state(
            $this->userid, 'google', 'state-correct'));
    }

    // ─────────────────────────────────────────────────────────────────
    // Helper.
    // ─────────────────────────────────────────────────────────────────

    /**
     * Switch the master OAuth flag to ON for the rest of this test.
     * Reset on tearDown via resetAfterTest(true).
     */
    private function enable_oauth_flag(): void {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            $this->markTestSkipped('local_sentientia_platform feature_flags resolver not installed in this fixture');
        }
        \local_sentientia_platform\feature_flags::set(
            oauth_base::FEATURE_FLAG, 0, true, null,
            'PHPUnit token_vault_test.php — enabling OAuth for assertion'
        );
    }
}
