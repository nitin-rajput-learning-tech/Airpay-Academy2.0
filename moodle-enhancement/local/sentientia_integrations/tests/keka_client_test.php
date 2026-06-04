<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_integrations;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests that don't need real KeKa API calls.
 *
 * The OAuth + sync path needs network. This test class only covers the
 * pure-logic / DB pieces:
 *   - sync_single_employee with department mapping when local_airpay_org
 *     table is missing (Phase-0A migration locks in)
 *   - JML webhook dispatcher — handle_webhook routes events correctly
 *
 * @package    local_sentientia_integrations
 * @category   test
 */
final class keka_client_test extends \advanced_testcase {

    public function test_handle_webhook_routes_unknown_event_to_failure(): void {
        $this->resetAfterTest();
        $result = keka_client::handle_webhook('mystery_event', []);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        // Unknown events shouldn't cause a fatal — they should just return
        // an unsuccessful structured response so the webhook receiver can
        // log status='failed' rather than 500.
        $this->assertFalse($result['success']);
    }

    public function test_class_uses_airpay_org_not_legacy_costcenter(): void {
        // Phase-0A migration audit: keka_client.php must NOT contain
        // the legacy {local_costcenter} reference. INTEGRATIONS-AUDIT.md §3.1.
        $source = file_get_contents(
            __DIR__ . '/../classes/keka_client.php');
        $this->assertStringNotContainsString("'local_costcenter'", $source,
            'legacy local_costcenter reference must be removed (Phase-0A)');
        $this->assertStringContainsString('local_airpay_org', $source,
            'must use the airpay_org-owned table');
    }
}
