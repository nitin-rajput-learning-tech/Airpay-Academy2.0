<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the ADR-020 Wave-3.1 org seam.
 *
 * @package    local_sentientia_core
 * @covers     \local_sentientia_core\org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class org_test extends \advanced_testcase {

    public function test_legacy_flag_defaults_on_when_unset(): void {
        $this->resetAfterTest();
        unset_config('org_legacy', 'local_sentientia_core');
        $this->assertTrue(
            org::use_legacy_costcenter(),
            'Unset config must be treated as ON so production behaviour never changes implicitly.'
        );
    }

    public function test_legacy_flag_respects_explicit_off(): void {
        $this->resetAfterTest();
        set_config('org_legacy', 0, 'local_sentientia_core');
        $this->assertFalse(org::use_legacy_costcenter());
    }

    public function test_manager_id_of_reads_open_supervisorid(): void {
        $this->resetAfterTest();
        set_config('org_legacy', 1, 'local_sentientia_core');
        $this->assertSame(42, org::manager_id_of((object) ['open_supervisorid' => 42]));
        $this->assertSame(42, org::manager_id_of((object) ['open_supervisorid' => '42']));
    }

    public function test_manager_id_of_no_manager_returns_zero(): void {
        $this->resetAfterTest();
        $this->assertSame(org::NO_MANAGER, org::manager_id_of((object) ['open_supervisorid' => null]));
        $this->assertSame(org::NO_MANAGER, org::manager_id_of((object) []));
        $this->assertSame(org::NO_MANAGER, org::manager_id_of((object) ['open_supervisorid' => 0]));
    }

    public function test_off_path_falls_back_to_legacy_until_model_exists(): void {
        $this->resetAfterTest();
        set_config('org_legacy', 0, 'local_sentientia_core');
        $result = org::manager_id_of((object) ['open_supervisorid' => 7]);
        $this->assertDebuggingCalled();
        $this->assertSame(7, $result);
    }

    public function test_manager_id_for_current_user_zero_when_logged_out(): void {
        global $USER;
        $this->resetAfterTest();
        $USER = new \stdClass();
        $this->assertSame(org::NO_MANAGER, org::manager_id_for_current_user());
    }

    public function test_manager_id_for_current_user_reads_global(): void {
        global $USER;
        $this->resetAfterTest();
        set_config('org_legacy', 1, 'local_sentientia_core');
        $USER = (object) ['id' => 100, 'open_supervisorid' => 55];
        $this->assertSame(55, org::manager_id_for_current_user());
    }
}
