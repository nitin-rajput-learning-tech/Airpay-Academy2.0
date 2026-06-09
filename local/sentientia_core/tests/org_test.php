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

    // ── Wave 3.2a — Sentientia org model read API ───────────────────────────

    /** Insert an org unit, return its id. */
    private function make_unit(int $parentid = 0, string $name = 'Unit', int $tenantrootid = 1): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_org_unit', (object) [
            'parentid' => $parentid,
            'tenantrootid' => $tenantrootid,
            'name' => $name,
            'status' => 'active',
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    /** Add a user to a unit with a role. */
    private function add_member(int $userid, int $unitid, string $role = 'member', int $managerid = 0): void {
        global $DB;
        $DB->insert_record('local_sentientia_org_member', (object) [
            'userid' => $userid,
            'unitid' => $unitid,
            'role' => $role,
            'managerid' => $managerid,
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    public function test_model_available_in_test_db(): void {
        $this->resetAfterTest();
        $this->assertTrue(org::model_available(),
            'install.xml ships the org tables, so they exist in the PHPUnit DB.');
    }

    public function test_manager_via_model_reads_the_direct_edge(): void {
        $this->resetAfterTest();
        $unit = $this->make_unit(0, 'Engineering');
        $this->add_member(10, $unit, 'member', 20);   // 10 reports to 20 (edge)
        $this->add_member(20, $unit, 'member', 0);    // 20 has no manager set

        $this->assertSame(20, org::manager_via_model(10), 'Reads the managerid edge.');
        $this->assertSame(org::NO_MANAGER, org::manager_via_model(20), 'No edge -> no manager.');
        $this->assertSame(org::NO_MANAGER, org::manager_via_model(999), 'Unmapped user has no manager.');
    }

    public function test_manager_id_of_off_path_uses_model_when_seeded(): void {
        $this->resetAfterTest();
        set_config('org_legacy', 0, 'local_sentientia_core');
        $unit = $this->make_unit(0, 'Risk');
        $this->add_member(11, $unit, 'member', 22);   // 11 reports to 22 (edge)

        // The model edge wins over the legacy open_supervisorid value, no debugging.
        $result = org::manager_id_of((object) ['id' => 11, 'open_supervisorid' => 999]);
        $this->assertSame(22, $result);
        $this->assertDebuggingNotCalled();
    }

    public function test_manager_id_of_off_path_falls_back_when_user_not_in_model(): void {
        $this->resetAfterTest();
        set_config('org_legacy', 0, 'local_sentientia_core');
        // User has an id but no membership row — fall back to legacy + debugging.
        $result = org::manager_id_of((object) ['id' => 12345, 'open_supervisorid' => 7]);
        $this->assertDebuggingCalled();
        $this->assertSame(7, $result);
    }

    public function test_tree_walk_parent_ancestors_children(): void {
        $this->resetAfterTest();
        $root = $this->make_unit(0, 'Root');
        $mid  = $this->make_unit($root, 'Division');
        $leaf = $this->make_unit($mid, 'Team');

        $this->assertSame($mid, org::parent_of($leaf));
        $this->assertSame(0, org::parent_of($root));
        $this->assertSame([$mid, $root], org::ancestors($leaf), 'Ancestors are nearest-first.');
        $this->assertSame([], org::ancestors($root));
        $this->assertEqualsCanonicalizing([$leaf], org::children($mid));
        $this->assertEqualsCanonicalizing([$mid], org::children($root));
        $this->assertSame([], org::children($leaf));
    }

    public function test_units_of_and_members_of(): void {
        $this->resetAfterTest();
        $u1 = $this->make_unit(0, 'Alpha');
        $u2 = $this->make_unit(0, 'Beta');
        $this->add_member(30, $u1, 'member');
        $this->add_member(30, $u2, 'manager');
        $this->add_member(31, $u1, 'member');

        $this->assertEqualsCanonicalizing([$u1, $u2], org::units_of(30));
        $this->assertEqualsCanonicalizing([$u1], org::units_of(31));
        $this->assertEqualsCanonicalizing([30, 31], org::members_of($u1));
        $this->assertEqualsCanonicalizing([30], org::members_of($u2));
        $this->assertSame([], org::units_of(999));
    }

    public function test_is_manager_and_direct_reports(): void {
        $this->resetAfterTest();
        // Reverse lookups are flag-aware (W3.4); exercise the model path with OFF.
        set_config('org_legacy', 0, 'local_sentientia_core');
        $unit = $this->make_unit(0, 'Sales');
        // 41 and 42 report to 40 via the edge; 40 reports to nobody.
        $this->add_member(40, $unit, 'member', 0);
        $this->add_member(41, $unit, 'member', 40);
        $this->add_member(42, $unit, 'member', 40);

        $this->assertTrue(org::is_manager(40));
        $this->assertFalse(org::is_manager(41));
        $this->assertFalse(org::is_manager(999));
        $this->assertEqualsCanonicalizing([41, 42], org::direct_reports(40),
            'Direct reports are users whose managerid edge points at 40.');
        $this->assertSame([], org::direct_reports(41), 'A non-manager has no reports.');
    }

    public function test_reports_by_manager_model_path(): void {
        $this->resetAfterTest();
        set_config('org_legacy', 0, 'local_sentientia_core');
        $unit = $this->make_unit(0, 'Ops');
        $this->add_member(50, $unit, 'member', 0);
        $this->add_member(51, $unit, 'member', 50);
        $this->add_member(52, $unit, 'member', 50);
        $this->add_member(53, $unit, 'member', 60);

        $map = org::reports_by_manager();
        $this->assertEqualsCanonicalizing([51, 52], $map[50] ?? []);
        $this->assertEqualsCanonicalizing([53], $map[60] ?? []);
        $this->assertArrayNotHasKey(0, $map, 'managerid 0 (no manager) is never a key.');

        // Restricted to a manager subset.
        $only = org::reports_by_manager([50]);
        $this->assertSame([50], array_keys($only));
        $this->assertEqualsCanonicalizing([51, 52], $only[50]);
        $this->assertSame([], org::reports_by_manager([0]), 'No positive manager ids -> empty.');
    }

    public function test_reverse_lookups_legacy_path_without_bizlms_column(): void {
        $this->resetAfterTest();
        // Default flag = ON -> legacy reverse path. The vanilla PHPUnit user table
        // has no BizLMS open_supervisorid column, so the guarded legacy lookups
        // degrade to empty rather than erroring (Enterprise-N portability). The
        // model rows below are deliberately ignored because the flag is ON.
        set_config('org_legacy', 1, 'local_sentientia_core');
        $unit = $this->make_unit(0, 'Legacy');
        $this->add_member(70, $unit, 'member', 0);
        $this->add_member(71, $unit, 'member', 70);

        $this->assertFalse(org::is_manager(70), 'ON path uses legacy; column absent -> false.');
        $this->assertSame([], org::direct_reports(70));
        $this->assertSame([], org::reports_by_manager());
    }
}
