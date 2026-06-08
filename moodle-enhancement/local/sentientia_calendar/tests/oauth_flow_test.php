<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Tier 2.6 Phase 2.1 — live OAuth flow tests (Wave C4).
 *
 * Where {@see token_vault_test} covers encryption round-trip + privacy,
 * this file covers the lifecycle branches that Phase 2.1 newly wires:
 *
 *   - Authorization-code exchange happy path (mocked endpoint).
 *   - State / CSRF rejection on the callback (wrong, missing, replayed).
 *   - Token refresh: access-token replacement, refresh_token rotation,
 *     and the `invalid_grant` → drop-local-row → re-prompt branch.
 *   - Revoke: Google POSTs the provider revoke endpoint; Microsoft is
 *     local-only (no standalone endpoint).
 *   - Expiry-triggered refresh via get_valid_access_token().
 *   - Connection-status snapshot (connected / expired / disconnected).
 *   - Feature-flag kill switch on every live entry point.
 *   - Transport + malformed-response error mapping.
 *
 * Every outbound HTTP call is routed through a mock handler registered
 * with {@see oauth_base::set_http_handler_for_testing()} — NO live
 * provider traffic ever leaves CI. tearDown() clears the handler so a
 * mock can't leak between tests.
 *
 * @package local_sentientia_calendar
 * @category test
 * @covers \local_sentientia_calendar\oauth\oauth_base
 * @covers \local_sentientia_calendar\oauth\m365_oauth
 * @covers \local_sentientia_calendar\oauth\google_oauth
 */

namespace local_sentientia_calendar;

use local_sentientia_calendar\oauth\oauth_base;
use local_sentientia_calendar\oauth\m365_oauth;
use local_sentientia_calendar\oauth\google_oauth;
use local_sentientia_calendar\oauth\token_vault;

defined('MOODLE_INTERNAL') || die();

final class oauth_flow_test extends \advanced_testcase {

    /** @var int A test user we create in setUp. */
    private int $userid = 0;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->userid = (int) $this->getDataGenerator()->create_user()->id;
    }

    public function tearDown(): void {
        oauth_base::set_http_handler_for_testing(null);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────
    // Authorization-code exchange.
    // ─────────────────────────────────────────────────────────────────

    public function test_callback_happy_path_google_stores_encrypted_tokens(): void {
        $this->enable_oauth_flag();

        oauth_base::set_http_handler_for_testing(function (string $url, array $params): array {
            $this->assertSame('https://oauth2.googleapis.com/token', $url);
            $this->assertSame('authorization_code', $params['grant_type']);
            $this->assertSame('g-auth-code', $params['code']);
            // Confidential-client: secret must be in the POST body.
            $this->assertSame('g-secret', $params['client_secret']);
            // PKCE verifier must round-trip into the exchange.
            $this->assertNotEmpty($params['code_verifier']);
            return [
                'http_code' => 200,
                'body'      => json_encode([
                    'access_token'  => 'g-access',
                    'refresh_token' => 'g-refresh',
                    'expires_in'    => 3599,
                    'scope'         => 'https://www.googleapis.com/auth/calendar.events.owned',
                ]),
            ];
        });

        set_config('google_client_id', 'g-id.apps.googleusercontent.com', 'local_sentientia_calendar');
        set_config('google_client_secret', 'g-secret', 'local_sentientia_calendar');

        $url = google_oauth::build_authorize_url($this->userid);
        $state = $this->extract_state($url->out(false));

        google_oauth::handle_callback($this->userid, 'g-auth-code', $state);

        $stored = token_vault::get_tokens($this->userid, 'google');
        $this->assertSame('g-access', $stored->access_token);
        $this->assertSame('g-refresh', $stored->refresh_token);

        // The encrypted column must NOT contain the plaintext.
        global $DB;
        $raw = $DB->get_record(token_vault::TABLE,
            ['userid' => $this->userid, 'provider' => 'google']);
        $this->assertStringNotContainsString('g-access', (string) $raw->access_token_enc);
    }

    public function test_callback_falls_back_to_requested_scopes_when_response_omits_scope(): void {
        $this->enable_oauth_flag();
        oauth_base::set_http_handler_for_testing(fn() => [
            'http_code' => 200,
            'body'      => json_encode(['access_token' => 'a', 'expires_in' => 3600]),
        ]);

        $verifier = oauth_base::generate_pkce_verifier();
        oauth_base::store_pending_state($this->userid, 'm365', 'st', $verifier);
        m365_oauth::handle_callback($this->userid, 'code', 'st');

        $stored = token_vault::get_tokens($this->userid, 'm365');
        $this->assertSame(m365_oauth::get_scopes(), $stored->scopes,
            'Missing scope in response should default to the requested scopes');
    }

    // ─────────────────────────────────────────────────────────────────
    // State / CSRF rejection.
    // ─────────────────────────────────────────────────────────────────

    public function test_callback_rejects_wrong_state_and_stores_nothing(): void {
        $this->enable_oauth_flag();
        // Mock would only fire if state passed — assert it never does.
        oauth_base::set_http_handler_for_testing(function (): array {
            $this->fail('Token endpoint must not be hit on a state mismatch');
        });

        $verifier = oauth_base::generate_pkce_verifier();
        oauth_base::store_pending_state($this->userid, 'm365', 'real-state', $verifier);

        try {
            m365_oauth::handle_callback($this->userid, 'code', 'forged-state');
            $this->fail('Expected moodle_exception for state mismatch');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('state mismatch', $e->getMessage());
        }
        $this->assertNull(token_vault::get_tokens($this->userid, 'm365'));
    }

    public function test_callback_rejects_missing_pending_state(): void {
        $this->enable_oauth_flag();
        oauth_base::set_http_handler_for_testing(function (): array {
            $this->fail('Token endpoint must not be hit without a pending state');
        });

        // No store_pending_state() call → nothing to match.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/state mismatch/');
        m365_oauth::handle_callback($this->userid, 'code', 'any-state');
    }

    public function test_callback_rejects_replayed_state(): void {
        $this->enable_oauth_flag();
        $hits = 0;
        oauth_base::set_http_handler_for_testing(function () use (&$hits): array {
            $hits++;
            return ['http_code' => 200,
                'body' => json_encode(['access_token' => 'a', 'expires_in' => 3600])];
        });

        $verifier = oauth_base::generate_pkce_verifier();
        oauth_base::store_pending_state($this->userid, 'm365', 'one-shot', $verifier);

        // First use succeeds.
        m365_oauth::handle_callback($this->userid, 'code', 'one-shot');
        // Replay must fail — the state was consumed.
        try {
            m365_oauth::handle_callback($this->userid, 'code', 'one-shot');
            $this->fail('Replayed state must be rejected');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('state mismatch', $e->getMessage());
        }
        $this->assertSame(1, $hits, 'Token endpoint must be hit exactly once');
    }

    public function test_callback_rejects_empty_code(): void {
        $this->enable_oauth_flag();
        $verifier = oauth_base::generate_pkce_verifier();
        oauth_base::store_pending_state($this->userid, 'm365', 'st', $verifier);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/authorisation code/');
        m365_oauth::handle_callback($this->userid, '', 'st');
    }

    // ─────────────────────────────────────────────────────────────────
    // Refresh — rotation + invalid_grant.
    // ─────────────────────────────────────────────────────────────────

    public function test_refresh_rotates_refresh_token_when_provider_returns_one(): void {
        $this->enable_oauth_flag();
        token_vault::store_tokens($this->userid, 1, 'google',
            'old-a', 'old-r', time() - 5, 'scope-x');

        oauth_base::set_http_handler_for_testing(fn() => [
            'http_code' => 200,
            'body'      => json_encode([
                'access_token'  => 'new-a',
                'refresh_token' => 'new-r',   // rotation
                'expires_in'    => 3600,
            ]),
        ]);

        google_oauth::refresh_token($this->userid);

        $stored = token_vault::get_tokens($this->userid, 'google');
        $this->assertSame('new-a', $stored->access_token);
        $this->assertSame('new-r', $stored->refresh_token,
            'A rotated refresh_token from the provider must overwrite the old one');
    }

    public function test_refresh_invalid_grant_drops_row_and_throws(): void {
        $this->enable_oauth_flag();
        token_vault::store_tokens($this->userid, 1, 'm365',
            'a', 'stale-refresh', time() - 5, 'openid');

        oauth_base::set_http_handler_for_testing(fn() => [
            'http_code' => 400,
            'body'      => json_encode(['error' => 'invalid_grant',
                'error_description' => 'refresh token expired']),
        ]);

        try {
            m365_oauth::refresh_token($this->userid);
            $this->fail('invalid_grant must raise');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('revoked our access', $e->getMessage());
        }
        // The row must be GONE so the next interactive use re-prompts.
        $this->assertNull(token_vault::get_tokens($this->userid, 'm365'));
    }

    public function test_refresh_transient_error_keeps_row(): void {
        $this->enable_oauth_flag();
        token_vault::store_tokens($this->userid, 1, 'm365',
            'a', 'good-refresh', time() - 5, 'openid');

        oauth_base::set_http_handler_for_testing(fn() => [
            'http_code' => 503,
            'body'      => json_encode(['error' => 'temporarily_unavailable']),
        ]);

        try {
            m365_oauth::refresh_token($this->userid);
            $this->fail('5xx must raise');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('unexpected response', $e->getMessage());
        }
        // Row must SURVIVE a transient failure — only invalid_grant drops it.
        $this->assertNotNull(token_vault::get_tokens($this->userid, 'm365'));
    }

    // ─────────────────────────────────────────────────────────────────
    // Revoke — provider call + local clear.
    // ─────────────────────────────────────────────────────────────────

    public function test_revoke_google_calls_provider_then_clears_local(): void {
        $this->enable_oauth_flag();
        token_vault::store_tokens($this->userid, 1, 'google',
            'a', 'revoke-me', time() + 3600, 'scope');

        $revoke_hit = null;
        oauth_base::set_http_handler_for_testing(function (string $url, array $params) use (&$revoke_hit): array {
            $revoke_hit = ['url' => $url, 'token' => $params['token'] ?? null];
            return ['http_code' => 200, 'body' => ''];
        });

        $this->assertTrue(google_oauth::revoke($this->userid));
        $this->assertSame('https://oauth2.googleapis.com/revoke', $revoke_hit['url']);
        $this->assertSame('revoke-me', $revoke_hit['token'],
            'Revoke must send the refresh_token to invalidate the whole consent');
        $this->assertNull(token_vault::get_tokens($this->userid, 'google'));
    }

    public function test_revoke_m365_is_local_only(): void {
        $this->enable_oauth_flag();
        token_vault::store_tokens($this->userid, 1, 'm365',
            'a', 'r', time() + 3600, 'openid');

        oauth_base::set_http_handler_for_testing(function (): array {
            $this->fail('Microsoft has no revoke endpoint — provider call must be skipped');
        });

        $this->assertTrue(m365_oauth::revoke($this->userid));
        $this->assertNull(token_vault::get_tokens($this->userid, 'm365'));
    }

    public function test_revoke_swallows_provider_failure_but_clears_local(): void {
        $this->enable_oauth_flag();
        token_vault::store_tokens($this->userid, 1, 'google',
            'a', 'r', time() + 3600, 'scope');

        oauth_base::set_http_handler_for_testing(function (): array {
            throw new \moodle_exception('error_oauth_http_failure', 'local_sentientia_calendar');
        });

        // Provider unreachable, but local revoke MUST still succeed.
        $this->assertTrue(google_oauth::revoke($this->userid));
        $this->assertNull(token_vault::get_tokens($this->userid, 'google'));
    }

    public function test_revoke_no_row_returns_false(): void {
        $this->enable_oauth_flag();
        $this->assertFalse(m365_oauth::revoke($this->userid));
    }

    // ─────────────────────────────────────────────────────────────────
    // Expiry-triggered refresh via get_valid_access_token().
    // ─────────────────────────────────────────────────────────────────

    public function test_get_valid_access_token_refreshes_when_expired(): void {
        $this->enable_oauth_flag();
        token_vault::store_tokens($this->userid, 1, 'm365',
            'expired-access', 'r', time() - 100, 'openid');

        oauth_base::set_http_handler_for_testing(fn() => [
            'http_code' => 200,
            'body'      => json_encode(['access_token' => 'refreshed-access', 'expires_in' => 3600]),
        ]);

        $token = m365_oauth::get_valid_access_token($this->userid);
        $this->assertSame('refreshed-access', $token);
    }

    public function test_get_valid_access_token_returns_existing_when_fresh(): void {
        $this->enable_oauth_flag();
        token_vault::store_tokens($this->userid, 1, 'm365',
            'still-good', 'r', time() + 3600, 'openid');

        oauth_base::set_http_handler_for_testing(function (): array {
            $this->fail('A fresh token must not trigger a refresh');
        });

        $this->assertSame('still-good', m365_oauth::get_valid_access_token($this->userid));
    }

    public function test_get_valid_access_token_null_when_not_connected(): void {
        $this->enable_oauth_flag();
        $this->assertNull(m365_oauth::get_valid_access_token($this->userid));
    }

    // ─────────────────────────────────────────────────────────────────
    // Connection-status snapshot.
    // ─────────────────────────────────────────────────────────────────

    public function test_describe_connection_states(): void {
        set_config('microsoft_client_id', 'azure-id', 'local_sentientia_calendar');

        // Disconnected.
        $d = m365_oauth::describe_connection($this->userid);
        $this->assertFalse($d['connected']);
        $this->assertFalse($d['expired']);
        $this->assertTrue($d['client_configured']);

        // Connected + fresh.
        token_vault::store_tokens($this->userid, 1, 'm365',
            'a', 'r', time() + 3600, 'openid');
        $c = m365_oauth::describe_connection($this->userid);
        $this->assertTrue($c['connected']);
        $this->assertFalse($c['expired']);

        // Connected + expired.
        token_vault::store_tokens($this->userid, 1, 'm365',
            'a', 'r', time() - 100, 'openid');
        $e = m365_oauth::describe_connection($this->userid);
        $this->assertTrue($e['connected']);
        $this->assertTrue($e['expired']);
    }

    public function test_describe_connection_client_not_configured(): void {
        // No client_id set.
        $d = google_oauth::describe_connection($this->userid);
        $this->assertFalse($d['client_configured']);
    }

    // ─────────────────────────────────────────────────────────────────
    // Feature-flag kill switch (NoLiveAPI guarantee).
    // ─────────────────────────────────────────────────────────────────

    public function test_callback_blocked_when_flag_off(): void {
        // Flag default OFF — handler must never fire.
        oauth_base::set_http_handler_for_testing(function (): array {
            $this->fail('No HTTP may occur while the flag is OFF');
        });
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/not currently enabled/');
        m365_oauth::handle_callback($this->userid, 'code', 'state');
    }

    public function test_refresh_blocked_when_flag_off(): void {
        token_vault::store_tokens($this->userid, 1, 'm365',
            'a', 'r', time() - 5, 'openid');
        oauth_base::set_http_handler_for_testing(function (): array {
            $this->fail('No HTTP may occur while the flag is OFF');
        });
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/not currently enabled/');
        m365_oauth::refresh_token($this->userid);
    }

    public function test_revoke_skips_provider_call_when_flag_off_but_clears_local(): void {
        // A token row can exist if an admin flipped the flag OFF after a
        // user connected. Local revoke must still work; provider call is
        // skipped (no point hitting a provider for a disabled feature).
        token_vault::store_tokens($this->userid, 1, 'google',
            'a', 'r', time() + 3600, 'scope');
        oauth_base::set_http_handler_for_testing(function (): array {
            $this->fail('Provider revoke must be skipped while the flag is OFF');
        });
        $this->assertTrue(google_oauth::revoke($this->userid));
        $this->assertNull(token_vault::get_tokens($this->userid, 'google'));
    }

    // ─────────────────────────────────────────────────────────────────
    // Transport + malformed-response error mapping.
    // ─────────────────────────────────────────────────────────────────

    public function test_malformed_200_response_is_rejected(): void {
        $this->enable_oauth_flag();
        oauth_base::set_http_handler_for_testing(fn() => [
            'http_code' => 200,
            'body'      => json_encode(['no_token_here' => true]),
        ]);
        $verifier = oauth_base::generate_pkce_verifier();
        oauth_base::store_pending_state($this->userid, 'm365', 'st', $verifier);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/unexpected response/');
        m365_oauth::handle_callback($this->userid, 'code', 'st');
    }

    public function test_provider_class_rejects_unknown_provider(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Unknown OAuth provider/');
        oauth_base::provider_class('dropbox');
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers.
    // ─────────────────────────────────────────────────────────────────

    private function enable_oauth_flag(): void {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            $this->markTestSkipped('local_sentientia_platform feature_flags resolver not installed in this fixture');
        }
        \local_sentientia_platform\feature_flags::set(
            oauth_base::FEATURE_FLAG, 0, true, null,
            'PHPUnit oauth_flow_test.php — enabling OAuth for assertion'
        );
    }

    /** Pull the `state` query param out of a built authorize URL. */
    private function extract_state(string $url): string {
        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        parse_str($query, $params);
        return (string) ($params['state'] ?? '');
    }
}
