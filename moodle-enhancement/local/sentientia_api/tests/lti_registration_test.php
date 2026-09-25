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
 * @covers     \local_sentientia_api\lti\launch
 *
 * @group tenant_isolation
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

    /**
     * ADR-031 fix-forward (2026-09-25). An LTI login arrives from a browser
     * that is not signed in here, so costcenterid is 0 and the lookup spans
     * every tenant. When two tenants registered the same platform (issuer +
     * client_id), get_record() bound the launch - nonce, public key, redirect
     * - to whichever tenant's row came first. An ambiguous lookup now fails
     * closed; a unique one still resolves, and a tenant-scoped one is unchanged.
     */
    public function test_an_ambiguous_tenantless_lookup_fails_closed(): void {
        $this->resetAfterTest();
        $one = $this->make_reg(1, 'https://shared.example', 'c1');
        $this->make_reg(77, 'https://shared.example', 'c1');

        $this->assertNull(registration::find('https://shared.example', 'c1', 0),
            'Two tenants share this issuer + client_id: a tenantless launch must not pick one.');
        $this->assertSame($one, (int) registration::find('https://shared.example', 'c1', 1)->id,
            'A tenant-scoped lookup still resolves its own registration.');

        // A registration nobody else shares still resolves without a tenant.
        $solo = $this->make_reg(177, 'https://solo.example', 'c9');
        $this->assertSame($solo, (int) registration::find('https://solo.example', 'c9', 0)->id);

        // A disabled duplicate does not make an enabled registration ambiguous.
        $this->make_reg(1, 'https://solo.example', 'c9', 0);
        $this->assertSame($solo, (int) registration::find('https://solo.example', 'c9', 0)->id);
    }

    public function test_an_ambiguous_launch_is_refused_before_any_key_is_used(): void {
        $this->resetAfterTest();
        $this->make_reg(1, 'https://shared.example', 'c1');
        $this->make_reg(77, 'https://shared.example', 'c1');
        $b64 = static fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $token = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.'
            . $b64(json_encode(['iss' => 'https://shared.example', 'aud' => 'c1', 'nonce' => 'n']))
            . '.' . $b64('sig');

        try {
            launch::process($token, 'state', 0);
            $this->fail('An ambiguous registration must refuse the launch.');
        } catch (\moodle_exception $e) {
            $this->assertSame('lti_no_registration', $e->errorcode);
        }
    }
}
