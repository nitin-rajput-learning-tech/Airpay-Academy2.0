<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_m365;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for msal_client — Phase C.1.
 *
 * Covers the four contracts the chip's ACCEPTANCE clause names:
 *   - Encryption round-trip via store_tokens / load_tokens / decrypt_token
 *   - Feature flag toggle (is_ready returns false without flag, true with
 *     flag + settings)
 *   - PKCE pair generation shape + entropy
 *   - exchange_code refuses to run when the feature flag is off, and
 *     throws confirm_required when it is on (Phase C.1 hard gate)
 *
 * NO test in this class issues HTTP — there is no path to
 * login.microsoftonline.com here. The cURL wiring lands in Phase C.2.
 *
 * @package    local_sentientia_m365
 * @covers     \local_sentientia_m365\msal_client
 */
final class msal_client_test extends \advanced_testcase {

    public function test_pkce_pair_has_correct_shape(): void {
        $pair = msal_client::generate_pkce_pair();

        $this->assertArrayHasKey('verifier', $pair);
        $this->assertArrayHasKey('challenge', $pair);
        $this->assertSame(msal_client::PKCE_VERIFIER_LENGTH, strlen($pair['verifier']));

        // RFC 7636 — verifier + challenge are URL-safe base64 (no '+' '/' '=').
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_\-]+$/', $pair['verifier']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_\-]+$/', $pair['challenge']);

        // Challenge is sha256(verifier) base64url — 43 chars (256 bits / 6 ≈ 43).
        $this->assertSame(43, strlen($pair['challenge']));
    }

    public function test_pkce_pairs_are_unique(): void {
        // Generate 10 pairs — each must have a distinct verifier.
        $verifiers = [];
        for ($i = 0; $i < 10; $i++) {
            $pair = msal_client::generate_pkce_pair();
            $verifiers[] = $pair['verifier'];
        }
        $this->assertSame(10, count(array_unique($verifiers)),
            'Each PKCE pair should produce a unique verifier');
    }

    public function test_store_and_load_round_trips_through_encryption(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user = $this->getDataGenerator()->create_user();

        $id = msal_client::store_tokens(
            $user->id, 1,
            'access-token-value-plain',
            'refresh-token-value-plain',
            3600,
            'openid profile offline_access User.Read'
        );
        $this->assertGreaterThan(0, $id);

        $row = msal_client::load_tokens($user->id, 1);
        $this->assertNotNull($row);
        $this->assertSame((int)$user->id, (int)$row->userid);
        $this->assertSame(1, (int)$row->customerid);

        // Encrypted columns are NOT the plaintext.
        $this->assertNotSame('access-token-value-plain', $row->access_token_enc);
        $this->assertNotSame('refresh-token-value-plain', $row->refresh_token_enc);

        // Decrypt path recovers the plaintext.
        $this->assertSame('access-token-value-plain',
            msal_client::decrypt_token($row->access_token_enc));
        $this->assertSame('refresh-token-value-plain',
            msal_client::decrypt_token($row->refresh_token_enc));
    }

    public function test_store_tokens_upserts_on_same_user_customer(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user = $this->getDataGenerator()->create_user();

        $id1 = msal_client::store_tokens($user->id, 1,
            'access-v1', 'refresh-v1', 3600,
            'openid profile offline_access User.Read');
        $id2 = msal_client::store_tokens($user->id, 1,
            'access-v2', 'refresh-v2', 7200,
            'openid profile offline_access User.Read Sites.Read.All');

        $this->assertSame($id1, $id2, 'Same (userid, customerid) must upsert');

        $row = msal_client::load_tokens($user->id, 1);
        $this->assertSame('access-v2', msal_client::decrypt_token($row->access_token_enc));
        $this->assertSame('refresh-v2', msal_client::decrypt_token($row->refresh_token_enc));
        $this->assertStringContainsString('Sites.Read.All', $row->scopes);
    }

    public function test_store_tokens_keeps_customer_scopes_isolated(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user = $this->getDataGenerator()->create_user();

        $id_a = msal_client::store_tokens($user->id, 1,
            'access-A', 'refresh-A', 3600, 'openid User.Read');
        $id_b = msal_client::store_tokens($user->id, 2,
            'access-B', 'refresh-B', 3600, 'openid User.Read');

        $this->assertNotSame($id_a, $id_b,
            'Same user under different customerids must be separate rows');

        $a = msal_client::load_tokens($user->id, 1);
        $b = msal_client::load_tokens($user->id, 2);
        $this->assertSame('access-A', msal_client::decrypt_token($a->access_token_enc));
        $this->assertSame('access-B', msal_client::decrypt_token($b->access_token_enc));
    }

    public function test_load_tokens_returns_null_when_no_row(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertNull(msal_client::load_tokens($user->id, 1));
    }

    public function test_decrypt_empty_string_passes_through(): void {
        $this->resetAfterTest();
        // Mirror \core\encryption::encrypt() / decrypt() empty-string contract.
        $this->assertSame('', msal_client::decrypt_token(''));
    }

    public function test_decrypt_invalid_ciphertext_throws_generic_exception(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $this->expectException(\moodle_exception::class);
        msal_client::decrypt_token('not-a-real-ciphertext-string');
    }

    public function test_store_tokens_rejects_empty_token(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        msal_client::store_tokens($user->id, 1, '', 'refresh', 3600, 'openid');
    }

    public function test_store_tokens_rejects_invalid_userid(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $this->expectException(\moodle_exception::class);
        msal_client::store_tokens(0, 1, 'a', 'r', 3600, 'openid');
    }

    public function test_needs_refresh_true_when_expired(): void {
        $row = (object)['expires' => time() - 1];
        $this->assertTrue(msal_client::needs_refresh($row));
    }

    public function test_needs_refresh_true_within_window(): void {
        $row = (object)['expires' => time() + 10];
        $this->assertTrue(msal_client::needs_refresh($row));
    }

    public function test_needs_refresh_false_when_well_in_future(): void {
        $row = (object)['expires' => time() + 3600];
        $this->assertFalse(msal_client::needs_refresh($row));
    }

    public function test_needs_refresh_true_when_expires_missing(): void {
        $row = (object)['expires' => 0];
        $this->assertTrue(msal_client::needs_refresh($row),
            'Missing expiry must be treated as expired (fail-safe).');
    }

    public function test_revoke_returns_true_when_row_deleted(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user = $this->getDataGenerator()->create_user();
        msal_client::store_tokens($user->id, 1, 'a', 'r', 3600, 'openid');

        $this->assertTrue(msal_client::revoke($user->id, 1));
        $this->assertNull(msal_client::load_tokens($user->id, 1));
    }

    public function test_revoke_returns_false_when_no_row(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(msal_client::revoke($user->id, 1));
    }

    public function test_is_ready_false_without_feature_flag(): void {
        $this->resetAfterTest();
        // Configure settings — but leave the flag OFF (default).
        set_config('azure_tenant_id', 'tenant-guid', 'local_sentientia_m365');
        set_config('azure_client_id', 'client-guid', 'local_sentientia_m365');
        set_config('redirect_uri',
            'https://example.test/local/sentientia_m365/callback.php',
            'local_sentientia_m365');

        $this->assertFalse(msal_client::is_ready(),
            'is_ready must return false when the master flag is OFF.');
    }

    public function test_is_ready_false_when_flag_on_but_settings_missing(): void {
        $this->resetAfterTest();
        // Enable the flag globally but leave settings empty.
        $this->set_master_flag_on();
        $this->assertFalse(msal_client::is_ready(),
            'is_ready must return false when the master flag is ON but ' .
            'azure_tenant_id / client_id / redirect_uri are missing.');
    }

    public function test_is_ready_true_when_flag_on_and_settings_present(): void {
        $this->resetAfterTest();
        $this->set_master_flag_on();
        set_config('azure_tenant_id', 'tenant-guid', 'local_sentientia_m365');
        set_config('azure_client_id', 'client-guid', 'local_sentientia_m365');
        set_config('redirect_uri',
            'https://example.test/local/sentientia_m365/callback.php',
            'local_sentientia_m365');

        $this->assertTrue(msal_client::is_ready());
    }

    public function test_build_authorize_url_throws_when_unconfigured(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        msal_client::build_authorize_url('csrf-state', 'pkce-challenge');
    }

    public function test_build_authorize_url_has_required_params(): void {
        $this->resetAfterTest();
        set_config('azure_tenant_id', 'tenant-guid', 'local_sentientia_m365');
        set_config('azure_client_id', 'client-guid', 'local_sentientia_m365');
        set_config('redirect_uri',
            'https://example.test/local/sentientia_m365/callback.php',
            'local_sentientia_m365');

        $url = msal_client::build_authorize_url('csrf-state', 'pkce-challenge');

        // Endpoint is Microsoft's authorize URL with our tenant.
        $this->assertStringStartsWith(
            'https://login.microsoftonline.com/tenant-guid/oauth2/v2.0/authorize?',
            $url);

        // Required params are present.
        $this->assertStringContainsString('client_id=client-guid', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('code_challenge=pkce-challenge', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringContainsString('state=csrf-state', $url);

        // Default scopes are always requested.
        $this->assertStringContainsString('scope=', $url);
        $this->assertStringContainsString('openid', $url);
        $this->assertStringContainsString('offline_access', $url);
        $this->assertStringContainsString('User.Read', $url);
    }

    public function test_build_authorize_url_appends_extra_scopes(): void {
        $this->resetAfterTest();
        set_config('azure_tenant_id', 'tenant-guid', 'local_sentientia_m365');
        set_config('azure_client_id', 'client-guid', 'local_sentientia_m365');
        set_config('redirect_uri',
            'https://example.test/local/sentientia_m365/callback.php',
            'local_sentientia_m365');

        $url = msal_client::build_authorize_url('s', 'c', ['Sites.Read.All', 'Calendars.Read']);
        $this->assertStringContainsString('Sites.Read.All', $url);
        $this->assertStringContainsString('Calendars.Read', $url);
    }

    public function test_exchange_code_short_circuits_when_flag_off(): void {
        $this->resetAfterTest();
        // No master-flag override — defaults to OFF.
        $result = msal_client::exchange_code('auth-code', 'verifier', 1);
        $this->assertSame('feature_off', $result['mode']);
        $this->assertNotEmpty($result['error']);
    }

    public function test_exchange_code_throws_confirm_required_when_flag_on(): void {
        $this->resetAfterTest();
        $this->set_master_flag_on();
        set_config('azure_tenant_id', 'tenant-guid', 'local_sentientia_m365');
        set_config('azure_client_id', 'client-guid', 'local_sentientia_m365');
        set_config('redirect_uri',
            'https://example.test/local/sentientia_m365/callback.php',
            'local_sentientia_m365');

        // Phase C.1 — flag ON but no live API gate exists yet, so the
        // call must throw `confirm_required` rather than hitting Microsoft.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/.*confirm.*/i');
        msal_client::exchange_code('auth-code', 'verifier', 1);
    }

    /**
     * Helper — enable the sentientia_m365_enabled feature flag globally.
     *
     * Writes directly to {local_sentientia_feature_flags} via set_field so the
     * test does not depend on the sentientia_platform admin Switchboard UI.
     */
    private function set_master_flag_on(): void {
        global $DB;
        $row = (object)[
            'flag_key'   => 'sentientia_m365_enabled',
            'tenant_id'  => 0,
            'enabled'    => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        // Some installs ship the customer_id column (Session 2 / ADR-002).
        $columns = $DB->get_columns('local_sentientia_feature_flags');
        if (isset($columns['customer_id'])) {
            $row->customer_id = 0;
        }
        // Avoid duplicates by deleting any prior row first.
        $where = ['flag_key' => 'sentientia_m365_enabled', 'tenant_id' => 0];
        if (isset($columns['customer_id'])) {
            $where['customer_id'] = 0;
        }
        $DB->delete_records('local_sentientia_feature_flags', $where);
        $DB->insert_record('local_sentientia_feature_flags', $row);
    }
}
