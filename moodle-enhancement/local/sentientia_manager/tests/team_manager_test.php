<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for team_manager's org-seam-routed access checks (ADR-020 Wave 3.4
 * reader migration). Exercises the MODEL path (org_legacy OFF) — vanilla-DB
 * testable without the BizLMS open_supervisorid column. The legacy path (ON)
 * and get_team's rich-record load are validated on the prod-data DB instead.
 *
 * @package    local_sentientia_manager
 * @covers     \local_sentientia_manager\team_manager
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class team_manager_test extends \advanced_testcase {

    /** Insert an org unit, return its id. */
    private function make_unit(): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_org_unit', (object) [
            'parentid' => 0,
            'tenantrootid' => 1,
            'name' => 'Unit',
            'status' => 'active',
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    /** Add an org-model membership edge: $userid is in $unitid, reporting to $managerid. */
    private function add_edge(int $unitid, int $userid, int $managerid): void {
        global $DB;
        $DB->insert_record('local_sentientia_org_member', (object) [
            'userid' => $userid,
            'unitid' => $unitid,
            'role' => 'member',
            'managerid' => $managerid,
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    public function test_can_manage_via_model_when_has_reports(): void {
        $this->resetAfterTest();
        set_config('org_legacy', 0, 'local_sentientia_core');
        $mgr = $this->getDataGenerator()->create_user();
        $rep = $this->getDataGenerator()->create_user();
        $unit = $this->make_unit();
        $this->add_edge($unit, (int) $rep->id, (int) $mgr->id);   // rep reports to mgr

        $this->assertTrue(team_manager::can_manage((int) $mgr->id),
            'A user with a direct report (model edge) can manage.');
        $this->assertFalse(team_manager::can_manage((int) $rep->id),
            'A user with no reports and no capability cannot manage.');
    }

    public function test_can_view_member_walks_chain_via_model(): void {
        $this->resetAfterTest();
        set_config('org_legacy', 0, 'local_sentientia_core');
        $skip  = $this->getDataGenerator()->create_user();
        $mgr   = $this->getDataGenerator()->create_user();
        $rep   = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $unit = $this->make_unit();
        $this->add_edge($unit, (int) $mgr->id, (int) $skip->id);  // mgr -> skip
        $this->add_edge($unit, (int) $rep->id, (int) $mgr->id);   // rep -> mgr

        $this->assertTrue(team_manager::can_view_member((int) $rep->id, (int) $rep->id), 'Self.');
        $this->assertTrue(team_manager::can_view_member((int) $mgr->id, (int) $rep->id), 'Direct manager.');
        $this->assertTrue(team_manager::can_view_member((int) $skip->id, (int) $rep->id), 'Skip-level via chain.');
        $this->assertFalse(team_manager::can_view_member((int) $other->id, (int) $rep->id), 'Unrelated viewer.');
        // The unrelated-viewer walk reaches the top of the chain, where
        // manager_id_of's OFF path emits a DEBUG_DEVELOPER fallback notice.
        $this->assertDebuggingCalled();
    }
}
