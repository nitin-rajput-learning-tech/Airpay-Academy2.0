<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the ADR-019 tenant-identity seam.
 *
 * @package    local_sentientia_core
 * @covers     \local_sentientia_core\tenant_identity
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_identity_test extends \advanced_testcase {

    public function test_legacy_flag_defaults_on_when_unset(): void {
        $this->resetAfterTest();
        unset_config('tenant_identity_legacy', 'local_sentientia_core');
        $this->assertTrue(
            tenant_identity::use_legacy_open_path(),
            'Unset config must be treated as ON so production behaviour never changes implicitly.'
        );
    }

    public function test_legacy_flag_respects_explicit_off(): void {
        $this->resetAfterTest();
        set_config('tenant_identity_legacy', 0, 'local_sentientia_core');
        $this->assertFalse(tenant_identity::use_legacy_open_path());
    }

    public function test_root_for_user_parses_open_path(): void {
        $this->resetAfterTest();
        set_config('tenant_identity_legacy', 1, 'local_sentientia_core');
        $this->assertSame(77, tenant_identity::root_for_user((object) ['open_path' => '/77/5/2']));
        $this->assertSame(1, tenant_identity::root_for_user((object) ['open_path' => '/1']));
        $this->assertSame(177, tenant_identity::root_for_user((object) ['open_path' => '/177']));
    }

    public function test_root_for_user_invalid_path_returns_no_tenant(): void {
        $this->resetAfterTest();
        $this->assertSame(tenant_identity::NO_TENANT,
            tenant_identity::root_for_user((object) ['open_path' => '']));
        $this->assertSame(tenant_identity::NO_TENANT,
            tenant_identity::root_for_user((object) ['open_path' => null]));
        $this->assertSame(tenant_identity::NO_TENANT,
            tenant_identity::root_for_user((object) ['open_path' => '/abc']));
    }

    public function test_off_path_falls_back_to_legacy_until_registry_exists(): void {
        $this->resetAfterTest();
        set_config('tenant_identity_legacy', 0, 'local_sentientia_core');
        // No Sentientia registry yet → resolves via legacy fallback + emits a
        // developer-debug note. Behaviour must still be correct (not break auth).
        $result = tenant_identity::root_for_user((object) ['open_path' => '/77']);
        $this->assertDebuggingCalled();
        $this->assertSame(77, $result);
    }

    public function test_root_for_current_user_zero_when_logged_out(): void {
        global $USER;
        $this->resetAfterTest();
        $USER = new \stdClass();
        $this->assertSame(tenant_identity::NO_TENANT, tenant_identity::root_for_current_user());
    }
}
