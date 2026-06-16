<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\lti;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for LTI registration resolution + nonce replay protection.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\lti\registration
 */
final class lti_registration_test extends \advanced_testcase {

    private function make_reg(int $costcenterid, string $iss, string $clientid, int $enabled = 1): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_api_lti_reg', (object)[
            'costcenterid' => $costcenterid,
            'regtype'      => 'provider',
            'name'         => 'Test',
            'issuer'       => $iss,
            'clientid'     => $clientid,
            'enabled'      => $enabled,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_find_is_tenant_scoped(): void {
        $this->resetAfterTest();
        $this->make_reg(1, 'https://p.example', 'c1');
        $this->make_reg(77, 'https://p.example', 'c1');

        // Caller in tenant 1 must only resolve the tenant-1 registration.
        $reg = registration::find('https://p.example', 'c1', 1);
        $this->assertNotNull($reg);
        $this->assertSame(1, (int) $reg->costcenterid);

        // Tenant 99 has no registration.
        $this->assertNull(registration::find('https://p.example', 'c1', 99));
    }

    public function test_disabled_registration_not_found(): void {
        $this->resetAfterTest();
        $this->make_reg(1, 'https://p.example', 'c1', 0);
        $this->assertNull(registration::find('https://p.example', 'c1', 1));
    }

    public function test_nonce_single_use(): void {
        $this->resetAfterTest();
        $regid = $this->make_reg(1, 'https://p.example', 'c1');
        $info = registration::new_nonce($regid);

        // First consume succeeds.
        $rec = registration::consume_nonce($info['nonce']);
        $this->assertNotNull($rec);

        // Replay fails.
        $this->assertNull(registration::consume_nonce($info['nonce']));
    }

    public function test_expired_nonce_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $regid = $this->make_reg(1, 'https://p.example', 'c1');
        $info = registration::new_nonce($regid);
        // Backdate the nonce beyond max age.
        $DB->set_field('local_sentientia_api_lti_nonce', 'timecreated', time() - 9999,
            ['nonce' => $info['nonce']]);
        $this->assertNull(registration::consume_nonce($info['nonce'], 600));
    }
}
