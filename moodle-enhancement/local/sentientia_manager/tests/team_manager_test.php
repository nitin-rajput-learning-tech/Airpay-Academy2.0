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

    /**
     * Surface walk 2026-11-18: a report who never logged in showed "999d ago"
     * (a 999-day sentinel) in the team table. The row now says so with
     * never_loggedin, and "last active" is the latest of lastaccess and
     * lastlogin (lastlogin is the PREVIOUS login, 0 after a first login).
     */
    public function test_summarize_team_last_active(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $never = $this->getDataGenerator()->create_user();
        $once  = $this->getDataGenerator()->create_user();
        $stale = $this->getDataGenerator()->create_user();
        $DB->update_record('user', (object) ['id' => $never->id, 'lastaccess' => 0, 'lastlogin' => 0]);
        $DB->update_record('user', (object) ['id' => $once->id, 'lastaccess' => $now - HOURSECS, 'lastlogin' => 0]);
        $DB->update_record('user', (object) ['id' => $stale->id,
            'lastaccess' => $now - 20 * DAYSECS, 'lastlogin' => $now - 21 * DAYSECS]);

        $team = [];
        foreach ([$never, $once, $stale] as $u) {
            $team[(int) $u->id] = $DB->get_record('user', ['id' => $u->id]);
        }
        $rows = team_manager::summarize_team($team);

        $row = $rows[(int) $never->id];
        $this->assertTrue($row['never_loggedin']);
        $this->assertNull($row['inactive_days'], 'No 999-day sentinel.');
        $this->assertTrue($row['is_inactive'], 'Never logged in still counts as inactive.');
        $this->assertSame(get_string('never'), $row['lastlogin']);

        $row = $rows[(int) $once->id];
        $this->assertFalse($row['never_loggedin'], 'A first login leaves lastlogin 0; lastaccess is used.');
        $this->assertSame(0, $row['inactive_days']);
        $this->assertFalse($row['is_inactive']);

        $row = $rows[(int) $stale->id];
        $this->assertFalse($row['never_loggedin']);
        $this->assertSame(20, $row['inactive_days']);
        $this->assertTrue($row['is_inactive']);
    }

    /**
     * 2026-11-18: the Overdue column read local_sentientia_compliance_snapshot,
     * a table no plugin creates, so it was always 0. It now counts the
     * compliance report's overdue snapshot rows (local_compliance_snapshot) for
     * the team members only, and only for a course still on the compliance
     * list, as compliance_engine::get_defaulters() does.
     */
    public function test_summarize_team_counts_overdue_from_compliance_snapshot(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_compliance_snapshot') || !$dbman->table_exists('local_compliance_courses')) {
            $this->markTestSkipped('local_sentientia_compliance_report is not installed.');
        }
        $gen = $this->getDataGenerator();
        $c1 = $gen->create_course();
        $c2 = $gen->create_course();
        $c3 = $gen->create_course();
        $dropped = $gen->create_course();  // Has snapshot rows, but is not on the compliance list.
        foreach ([$c1, $c2, $c3] as $c) {
            $DB->insert_record('local_compliance_courses', (object) [
                'courseid' => $c->id, 'coursename' => 'Mandatory', 'costcenterid' => 0,
                'is_active' => 1, 'timecreated' => 1, 'timemodified' => 1,
            ]);
        }
        $two     = $gen->create_user();   // Overdue on two courses, completed on a third.
        $dropout = $gen->create_user();   // Overdue only on a course no longer listed.
        $clean   = $gen->create_user();   // No snapshot rows at all.
        $outside = $gen->create_user();   // Overdue, but not in the team.
        $snap = function (\stdClass $u, \stdClass $c, string $status) use ($DB): void {
            $DB->insert_record('local_compliance_snapshot', (object) [
                'userid' => $u->id, 'courseid' => $c->id, 'costcenterid' => 1, 'department_path' => '/1',
                'status' => $status, 'days_overdue' => ($status === 'overdue') ? 5 : 0, 'snapshot_date' => time(),
            ]);
        };
        $snap($two, $c1, 'overdue');
        $snap($two, $c2, 'overdue');
        $snap($two, $c3, 'completed');
        $snap($dropout, $dropped, 'overdue');
        $snap($outside, $c1, 'overdue');

        $team = [];
        foreach ([$two, $dropout, $clean] as $u) {
            $team[(int) $u->id] = $DB->get_record('user', ['id' => $u->id]);
        }
        $rows = team_manager::summarize_team($team);

        $this->assertCount(3, $rows, 'Only the team is summarised.');
        $this->assertArrayNotHasKey((int) $outside->id, $rows);
        $this->assertSame(2, $rows[(int) $two->id]['overdue']);
        $this->assertTrue($rows[(int) $two->id]['has_overdue']);
        $this->assertSame(0, $rows[(int) $dropout->id]['overdue'], 'A course off the compliance list is not counted.');
        $this->assertFalse($rows[(int) $dropout->id]['has_overdue']);
        $this->assertSame(0, $rows[(int) $clean->id]['overdue']);
        $this->assertFalse($rows[(int) $clean->id]['has_overdue']);
    }
}
