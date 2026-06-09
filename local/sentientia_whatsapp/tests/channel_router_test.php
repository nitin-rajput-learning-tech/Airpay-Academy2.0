<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_whatsapp\channel_router
 * @covers \local_sentientia_whatsapp\whatsapp_client
 * @covers \local_sentientia_whatsapp\sms_client
 *
 * Phase A1 iters 3-5. Regression suite for the cascading channel router
 * and the WhatsApp + SMS clients in mock mode.
 */
class channel_router_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Seed an approved WhatsApp template that resolve checks against.
        $id = dlt_template_registry::upsert([
            'template_key' => 'test_msg',
            'channel'      => 'whatsapp',
            'body'         => 'Hi {{firstname}}.',
        ]);
        dlt_template_registry::transition_status($id, 'approved');

        $id2 = dlt_template_registry::upsert([
            'template_key' => 'test_msg',
            'channel'      => 'sms',
            'body'         => 'Hi {{firstname}}.',
        ]);
        dlt_template_registry::transition_status($id2, 'approved');
    }

    public function test_dispatch_falls_back_to_email_when_no_optin(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = channel_router::dispatch($user->id, 'test_msg',
            ['firstname' => 'A']);

        $this->assertSame('email', $result['channel']);
    }

    public function test_whatsapp_client_logs_mock_when_flag_off(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Opt the user in to WhatsApp with mobile + consent.
        preference_manager::set($user->id, [
            'mobile_number'    => '+919876543210',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
            'prefer_channel'   => 'whatsapp',
        ]);

        // Phase A0 default has engagement.whatsapp.enabled OFF, so even
        // with full opt-in + consent + approved template, the client
        // should still mock (not actually send).
        $result = whatsapp_client::send_template($user->id, 'test_msg',
            ['firstname' => 'A']);

        $this->assertSame('mocked', $result['status']);
        $this->assertTrue($result['sent']);

        $row = $DB->get_record('local_sentientia_send_log', ['id' => $result['log_id']]);
        $this->assertSame(send_log::STATUS_MOCKED, $row->status);
        $this->assertSame(1, (int) $row->mock_mode);
        $this->assertStringContainsString('flag OFF', $row->failure_reason);
    }

    public function test_whatsapp_client_logs_opted_out_when_user_didnt_consent(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // User has not opted in. Default state.
        $result = whatsapp_client::send_template($user->id, 'test_msg');
        $this->assertSame('opted_out', $result['status']);
        $this->assertFalse($result['sent']);

        $row = $DB->get_record('local_sentientia_send_log', ['id' => $result['log_id']]);
        $this->assertSame(send_log::STATUS_OPTED_OUT, $row->status);
    }

    public function test_whatsapp_fails_when_no_mobile_on_file(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Opt user in but without a mobile number. The set() will reject
        // an empty mobile if we try to opt in with consent — but a
        // direct row write skipping the manager can produce this state,
        // so we craft the row manually via the manager's set with
        // mobile + then clear it via DB.
        preference_manager::set($user->id, [
            'mobile_number'    => '+919876543210',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
        ]);
        global $DB;
        $DB->set_field('local_sentientia_user_channel_prefs', 'mobile_number', '',
            ['userid' => $user->id]);

        $result = whatsapp_client::send_template($user->id, 'test_msg');
        $this->assertSame('failed', $result['status']);
        $this->assertSame('no_mobile_number', $result['error']);
    }

    public function test_whatsapp_no_template_when_dlt_not_approved(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        preference_manager::set($user->id, [
            'mobile_number'    => '+919876543210',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
        ]);

        // Request a template key that doesn't exist for whatsapp.
        $result = whatsapp_client::send_template($user->id, 'never_seeded');
        $this->assertSame('no_template', $result['status']);
    }

    public function test_analytics_channel_mix_aggregates(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        preference_manager::set($user->id, [
            'mobile_number'    => '+919876543210',
            'whatsapp_optin'   => 1,
            'sms_optin'        => 1,
            'dlt_consent_text' => 'I agree.',
        ]);

        // Generate a few mock sends.
        whatsapp_client::send_template($user->id, 'test_msg');
        whatsapp_client::send_template($user->id, 'test_msg');
        sms_client::send_template($user->id, 'test_msg');

        $mix = analytics::channel_mix(time() - 86400);
        $this->assertGreaterThanOrEqual(2,
            $mix['whatsapp']['mocked'] ?? 0);
        $this->assertGreaterThanOrEqual(1,
            $mix['sms']['mocked'] ?? 0);
        $this->assertSame(100, $mix['totals']['mocked_pct'],
            'In mock-only environment, every send should be mocked.');
    }
}
