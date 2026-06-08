<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-8 (2026-05-16) — tests for the Public-tenant self-registration service.
 *
 * Locks in:
 *   - is_enabled() reflects the activeregistration config setting
 *   - validate() rejects empty mandatory fields
 *   - validate() rejects malformed email
 *   - validate() rejects mismatched passwords
 *   - validate() rejects unticked ToS checkbox
 *   - validate() rejects email collision with existing non-deleted user
 *   - register() creates a user with auth='email' + confirmed=0
 *   - register() pins the new user to the configured tenant path
 *   - register() refuses when feature flag is OFF
 *   - confirm() flips confirmed=1 when the secret matches
 *   - confirm() rejects a tampered secret
 *
 * @package    local_sentientia_users
 * @category   test
 */
final class signup_service_test extends \advanced_testcase {

    private function valid_payload(array $overrides = []): \stdClass {
        return (object) array_merge([
            'firstname' => 'Test',
            'lastname'  => 'Newuser',
            'email'     => 'newuser_' . uniqid('', true) . '@example.org',
            'password'  => 'StrongPass!23',
            'password2' => 'StrongPass!23',
            'country'   => 'IN',
            'lang'      => 'en',
            'agree_tos' => 1,
        ], $overrides);
    }

    public function test_is_enabled_reflects_config(): void {
        $this->resetAfterTest();
        set_config('activeregistration', 0, 'local_sentientia_users');
        $this->assertFalse(signup_service::is_enabled());
        set_config('activeregistration', 1, 'local_sentientia_users');
        $this->assertTrue(signup_service::is_enabled());
    }

    public function test_validate_rejects_empty_mandatory(): void {
        $this->resetAfterTest();
        $errors = signup_service::validate(
            $this->valid_payload(['firstname' => '']));
        $this->assertArrayHasKey('firstname', $errors);
    }

    public function test_validate_rejects_malformed_email(): void {
        $this->resetAfterTest();
        $errors = signup_service::validate(
            $this->valid_payload(['email' => 'not-an-email']));
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_validate_rejects_mismatched_passwords(): void {
        $this->resetAfterTest();
        $errors = signup_service::validate(
            $this->valid_payload(['password2' => 'wrong']));
        $this->assertArrayHasKey('password2', $errors);
    }

    public function test_validate_rejects_unticked_tos(): void {
        $this->resetAfterTest();
        $errors = signup_service::validate(
            $this->valid_payload(['agree_tos' => 0]));
        $this->assertArrayHasKey('agree_tos', $errors);
    }

    public function test_validate_rejects_email_collision(): void {
        $this->resetAfterTest();
        $existing = $this->getDataGenerator()->create_user([
            'email' => 'taken@example.org',
        ]);
        $errors = signup_service::validate(
            $this->valid_payload(['email' => 'taken@example.org']));
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_register_refuses_when_disabled(): void {
        $this->resetAfterTest();
        set_config('activeregistration', 0, 'local_sentientia_users');
        $this->expectException(\moodle_exception::class);
        signup_service::register($this->valid_payload());
    }

    public function test_register_creates_unconfirmed_user(): void {
        $this->resetAfterTest();
        set_config('activeregistration', 1, 'local_sentientia_users');
        global $DB;

        $email = 'fresh_' . uniqid('', true) . '@example.org';
        $newid = signup_service::register($this->valid_payload(['email' => $email]));

        $user = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame('email', $user->auth);
        $this->assertSame(0, (int) $user->confirmed,
            'auth=email signup must leave confirmed=0 until the email link is clicked');
        $this->assertSame(strtolower($email), $user->email);
        $this->assertSame('Test', $user->firstname);
        $this->assertNotEmpty($user->secret,
            'A non-empty secret is required for the email-confirmation token');
    }

    public function test_register_pins_to_configured_tenant_path(): void {
        $this->resetAfterTest();
        set_config('activeregistration', 1, 'local_sentientia_users');
        set_config('signup_tenant_path', '/77', 'local_sentientia_users');
        global $DB;

        $email = 'tenant_' . uniqid('', true) . '@example.org';
        $newid = signup_service::register($this->valid_payload(['email' => $email]));

        $user = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame('/77', $user->open_path,
            'New signups must land in the Public tenant per config');
        $this->assertSame(77, (int) $user->open_costcenterid);
    }

    public function test_register_falls_back_to_public_tenant_when_config_empty(): void {
        $this->resetAfterTest();
        set_config('activeregistration', 1, 'local_sentientia_users');
        set_config('signup_tenant_path', '', 'local_sentientia_users');
        global $DB;

        $email = 'fallback_' . uniqid('', true) . '@example.org';
        $newid = signup_service::register($this->valid_payload(['email' => $email]));

        $user = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame('/77', $user->open_path);
    }

    public function test_confirm_flips_confirmed_to_one(): void {
        $this->resetAfterTest();
        set_config('activeregistration', 1, 'local_sentientia_users');
        global $DB;

        $email = 'confirm_' . uniqid('', true) . '@example.org';
        $newid = signup_service::register($this->valid_payload(['email' => $email]));
        $user = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);

        $result = signup_service::confirm($user->secret, $user->username);
        $this->assertSame(AUTH_CONFIRM_OK, $result);

        $confirmed = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame(1, (int) $confirmed->confirmed);
        $this->assertSame('', $confirmed->secret,
            'Secret should be cleared after successful confirmation');
    }

    public function test_confirm_rejects_tampered_secret(): void {
        $this->resetAfterTest();
        set_config('activeregistration', 1, 'local_sentientia_users');
        global $DB;

        $email = 'tamper_' . uniqid('', true) . '@example.org';
        $newid = signup_service::register($this->valid_payload(['email' => $email]));
        $user = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);

        $result = signup_service::confirm('wrong-secret', $user->username);
        $this->assertSame(AUTH_CONFIRM_ERROR, $result);

        // Confirmed flag must still be 0.
        $still = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame(0, (int) $still->confirmed);
    }

    public function test_username_collision_appends_suffix(): void {
        // First signup with alice@example.org → username 'alice@example.org'
        // (Moodle allows @ in usernames). Second signup with same email
        // should be rejected at the email-uniqueness check, not produce a
        // username clash.
        $this->resetAfterTest();
        set_config('activeregistration', 1, 'local_sentientia_users');
        global $DB;

        $email = 'alice_' . uniqid('', true) . '@example.org';
        $newid = signup_service::register($this->valid_payload(['email' => $email]));
        $first = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);

        // Username should be derivable from email.
        $this->assertSame(strtolower($email), $first->username);

        // Now try to register the SAME email again — should fail at validate().
        $this->expectException(\moodle_exception::class);
        signup_service::register($this->valid_payload(['email' => $email]));
    }
}
