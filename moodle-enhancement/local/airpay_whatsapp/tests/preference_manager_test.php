<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_whatsapp;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_whatsapp\preference_manager
 *
 * Phase A1 iter 1 (2026-05-15). Regression suite for the user-channel
 * preference manager. Covers:
 *   - default-shape behaviour when no row exists
 *   - mobile-number validation (the lone format check before iter 3
 *     wires real provider integration)
 *   - opt-in flow + DLT-consent gating
 *   - audit-trail completeness across insert + update paths
 *   - resolve_channel fall-back chain (the function the cadence engine
 *     will call in iter 3+)
 *   - delete_user_data for DPDP / GDPR right-to-erasure
 */
class preference_manager_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_get_returns_defaults_when_user_has_no_row(): void {
        $user = $this->getDataGenerator()->create_user();
        $prefs = preference_manager::get($user->id);

        $this->assertNull($prefs->id);
        $this->assertSame($user->id, (int) $prefs->userid);
        $this->assertSame('', $prefs->mobile_number);
        $this->assertSame(0, (int) $prefs->whatsapp_optin);
        $this->assertSame(0, (int) $prefs->sms_optin);
        $this->assertSame(1, (int) $prefs->email_optin);
        $this->assertSame('email', $prefs->prefer_channel);
        $this->assertNull($prefs->dlt_consent_at);
    }

    public function test_is_valid_mobile_accepts_country_code_format(): void {
        $this->assertTrue(preference_manager::is_valid_mobile('+919876543210'));
        $this->assertTrue(preference_manager::is_valid_mobile('+14155552671'));
        $this->assertTrue(preference_manager::is_valid_mobile('+447911123456'));
        // Whitespace stripped before validation:
        $this->assertTrue(preference_manager::is_valid_mobile('+91 98765 43210'));
    }

    public function test_is_valid_mobile_rejects_bad_input(): void {
        $this->assertFalse(preference_manager::is_valid_mobile(''));
        $this->assertFalse(preference_manager::is_valid_mobile('9876543210'));  // no +
        $this->assertFalse(preference_manager::is_valid_mobile('+'));
        $this->assertFalse(preference_manager::is_valid_mobile('+12'));         // too short
        $this->assertFalse(preference_manager::is_valid_mobile('not a number'));
        $this->assertFalse(preference_manager::is_valid_mobile('+91-9876-543210'));  // hyphens not stripped
    }

    public function test_set_creates_row_on_first_save(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        preference_manager::set($user->id, [
            'mobile_number' => '+919876543210',
            'prefer_channel' => 'email',
        ]);

        $row = $DB->get_record('local_airpay_user_channel_prefs',
            ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame('+919876543210', $row->mobile_number);
        $this->assertSame('email', $row->prefer_channel);
    }

    public function test_set_with_invalid_mobile_throws(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        preference_manager::set($user->id, [
            'mobile_number' => 'not-a-mobile',
        ]);
    }

    public function test_optin_without_consent_throws(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        // Attempting to opt-in to WhatsApp without DLT consent — must throw.
        preference_manager::set($user->id, [
            'whatsapp_optin' => 1,
            'mobile_number'  => '+919876543210',
        ]);
    }

    public function test_optin_with_consent_succeeds(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        preference_manager::set($user->id, [
            'whatsapp_optin'   => 1,
            'mobile_number'    => '+919876543210',
            'dlt_consent_text' => 'I agree to receive transactional WhatsApp messages.',
        ]);

        $row = $DB->get_record('local_airpay_user_channel_prefs',
            ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame(1, (int) $row->whatsapp_optin);
        $this->assertNotEmpty($row->dlt_consent_at);
        $this->assertNotEmpty($row->dlt_consent_text);
        // consent_at was auto-stamped to ~now.
        $this->assertGreaterThan(time() - 5, (int) $row->dlt_consent_at);
    }

    public function test_set_writes_audit_row_per_changed_field(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = $DB->count_records('local_airpay_user_channel_audit',
            ['userid' => $user->id]);

        // First save — 3 changed fields (mobile, whatsapp_optin, dlt_consent_text).
        // dlt_consent_at gets auto-set so that's a 4th implicit change.
        preference_manager::set($user->id, [
            'mobile_number'    => '+919876543210',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
        ]);

        $after = $DB->count_records('local_airpay_user_channel_audit',
            ['userid' => $user->id]);
        $this->assertGreaterThan($before, $after);

        // Verify the audit captured at least the mobile_number transition.
        $rows = $DB->get_records('local_airpay_user_channel_audit',
            ['userid' => $user->id, 'field_name' => 'mobile_number']);
        $this->assertNotEmpty($rows);
        $first = reset($rows);
        $this->assertNull($first->old_value);
        $this->assertSame('+919876543210', $first->new_value);
    }

    public function test_idempotent_set_no_extra_audit_rows(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        preference_manager::set($user->id, [
            'mobile_number'    => '+919876543210',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
        ]);
        $first = $DB->count_records('local_airpay_user_channel_audit',
            ['userid' => $user->id]);

        // Re-save the exact same values — should be a no-op for audit.
        preference_manager::set($user->id, [
            'mobile_number'  => '+919876543210',
            'whatsapp_optin' => 1,
        ]);
        $second = $DB->count_records('local_airpay_user_channel_audit',
            ['userid' => $user->id]);
        $this->assertSame($first, $second,
            'Re-saving identical values must not write audit duplicates');
    }

    public function test_resolve_channel_falls_back_to_email_when_no_optin(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // User has set prefer_channel = whatsapp but isn't actually
        // opted in. Should fall back to email.
        preference_manager::set($user->id, [
            'prefer_channel' => 'whatsapp',
            'mobile_number'  => '+919876543210',
            // No whatsapp_optin = 1, no consent.
        ]);

        $this->assertSame('email', preference_manager::resolve_channel($user->id));
    }

    public function test_resolve_channel_falls_back_when_feature_flag_off(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Even with opt-in + mobile + consent, if engagement.whatsapp.enabled
        // is OFF (default in Phase A0), resolve_channel falls back to email.
        preference_manager::set($user->id, [
            'prefer_channel'   => 'whatsapp',
            'whatsapp_optin'   => 1,
            'mobile_number'    => '+919876543210',
            'dlt_consent_text' => 'I agree.',
        ]);

        // The feature flag for engagement.whatsapp.enabled defaults to OFF
        // per Phase A0's db/feature_flags.php seed, so this should fall
        // back to email.
        $this->assertSame('email', preference_manager::resolve_channel($user->id));
    }

    public function test_delete_user_data_clears_both_tables(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        preference_manager::set($user->id, [
            'mobile_number'    => '+919876543210',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
        ]);

        $this->assertTrue($DB->record_exists('local_airpay_user_channel_prefs',
            ['userid' => $user->id]));
        $this->assertNotEmpty($DB->get_records('local_airpay_user_channel_audit',
            ['userid' => $user->id]));

        preference_manager::delete_user_data($user->id);

        $this->assertFalse($DB->record_exists('local_airpay_user_channel_prefs',
            ['userid' => $user->id]));
        $this->assertEmpty($DB->get_records('local_airpay_user_channel_audit',
            ['userid' => $user->id]));
    }

    public function test_recent_audit_returns_newest_first(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Generate two distinct audit events. Different reasons let us
        // verify ordering.
        preference_manager::set($user->id, [
            'mobile_number'    => '+919876543210',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
        ], $user->id, 'first save');
        preference_manager::set($user->id, [
            'mobile_number' => '+919876543211',
        ], $user->id, 'phone number changed');

        $rows = preference_manager::recent_audit($user->id, 5);
        $this->assertNotEmpty($rows);
        $first_row = reset($rows);
        // The newest row should be the phone-number change.
        $this->assertSame('phone number changed', $first_row->reason);
    }
}
