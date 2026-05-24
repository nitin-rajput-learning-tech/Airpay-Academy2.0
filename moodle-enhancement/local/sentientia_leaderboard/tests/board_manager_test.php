<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for {@see board_manager}.
 *
 * Locks in:
 *   - create() pins customer + tenant from the owner's open_path
 *   - list_visible() filters by tenant unless :viewall
 *   - boards_due_for_recompute() respects the per-board recompute_seconds
 *   - delete() cascades to entries + events
 *
 * @package    local_sentientia_leaderboard
 * @category   test
 * @covers     \local_sentientia_leaderboard\board_manager
 */
final class board_manager_test extends \advanced_testcase {

    private function user_in_tenant(int $tenantid): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $tenantid, ['id' => $u->id]);
        // Reload so the cached object has the new open_path.
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    public function test_create_pins_tenant_from_owner(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->user_in_tenant(77);
        $course = $this->getDataGenerator()->create_course();
        $boardid = board_manager::create([
            'name'     => 'Tenant77',
            'type'     => board_manager::TYPE_COMPLETION,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner->id,
        ]);
        $row = $DB->get_record('local_sentientia_lb_boards',
            ['id' => $boardid], '*', MUST_EXIST);
        $this->assertSame(77, (int) $row->tenantid,
            'create() must pin tenantid from the owner\'s open_path');
    }

    public function test_list_visible_filters_by_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner1 = $this->user_in_tenant(1);
        $owner77 = $this->user_in_tenant(77);
        $course = $this->getDataGenerator()->create_course();

        board_manager::create([
            'name'     => 'B1', 'type' => board_manager::TYPE_COMPLETION,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner1->id,
            'tenantid' => 1,
        ]);
        board_manager::create([
            'name'     => 'B77', 'type' => board_manager::TYPE_COMPLETION,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner77->id,
            'tenantid' => 77,
        ]);

        // Tenant-1 view (no :viewall): only sees B1.
        $rows = board_manager::list_visible(1, false);
        $this->assertCount(1, $rows);
        $this->assertSame('B1', $rows[0]->name);

        // :viewall (HR): sees both.
        $rows_all = board_manager::list_visible(1, true);
        $this->assertCount(2, $rows_all);
    }

    public function test_list_visible_includes_customer_wide_for_anyone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->user_in_tenant(1);
        $course = $this->getDataGenerator()->create_course();

        // Customer-wide board (tenantid=0) — should be visible to any tenant.
        board_manager::create([
            'name'     => 'CWide',
            'type'     => board_manager::TYPE_COMPLETION,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner->id,
            'tenantid' => 0,
        ]);

        $rows = board_manager::list_visible(77, false);
        $this->assertCount(1, $rows,
            'customer-wide board (tenantid=0) must be visible to tenant 77');
        $this->assertSame('CWide', $rows[0]->name);
    }

    public function test_boards_due_for_recompute(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->user_in_tenant(1);
        $course = $this->getDataGenerator()->create_course();

        $fresh = board_manager::create([
            'name'     => 'Fresh',
            'type'     => board_manager::TYPE_COMPLETION,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner->id,
            'tenantid' => 1,
            'recompute_seconds' => 60,
        ]);
        $stale = board_manager::create([
            'name'     => 'Stale',
            'type'     => board_manager::TYPE_COMPLETION,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner->id,
            'tenantid' => 1,
            'recompute_seconds' => 60,
        ]);

        // Fresh: just recomputed.
        $DB->set_field('local_sentientia_lb_boards', 'last_recomputed',
            time(), ['id' => $fresh]);
        // Stale: recomputed 10 minutes ago.
        $DB->set_field('local_sentientia_lb_boards', 'last_recomputed',
            time() - 600, ['id' => $stale]);

        $due = board_manager::boards_due_for_recompute();
        $ids = array_map(fn($r) => (int) $r->id, $due);
        $this->assertContains($stale, $ids,
            'stale board (older than recompute_seconds) must be due');
        $this->assertNotContains($fresh, $ids,
            'just-recomputed board must NOT be due');
    }

    public function test_delete_cascades(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->user_in_tenant(1);
        $course = $this->getDataGenerator()->create_course();
        $boardid = board_manager::create([
            'name'     => 'Doomed',
            'type'     => board_manager::TYPE_COMPLETION,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner->id,
            'tenantid' => 1,
        ]);

        // Seed entries + events.
        $DB->insert_record('local_sentientia_lb_entries', (object) [
            'boardid' => $boardid, 'userid' => 1, 'points' => 100,
            'secondary' => 0, 'userrank' => 1, 'costcenterid' => 1,
            'last_recomputed' => time(),
        ]);
        event_journal::write($boardid, 'leaderboard.recomputed', []);

        board_manager::delete($boardid);

        $this->assertFalse($DB->record_exists('local_sentientia_lb_boards',
            ['id' => $boardid]));
        $this->assertSame(0, $DB->count_records('local_sentientia_lb_entries',
            ['boardid' => $boardid]));
        $this->assertSame(0, $DB->count_records('local_sentientia_lb_events',
            ['boardid' => $boardid]));
    }

    public function test_resolve_tenant_from_open_path(): void {
        $this->assertSame(1,   board_manager::resolve_tenant_from_open_path('/1'));
        $this->assertSame(77,  board_manager::resolve_tenant_from_open_path('/77'));
        $this->assertSame(177, board_manager::resolve_tenant_from_open_path('/177/5/9'));
        $this->assertSame(0,   board_manager::resolve_tenant_from_open_path(''));
        $this->assertSame(0,   board_manager::resolve_tenant_from_open_path('/not_a_number'));
    }
}
