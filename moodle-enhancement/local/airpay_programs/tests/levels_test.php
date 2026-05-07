<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_programs;

defined('MOODLE_INTERNAL') || die();

/**
 * G-03 — Level CRUD + course assignment + program enrolment tests.
 *
 * Locks in:
 * - program_manager::create_level auto-assigns next sortorder
 * - program_manager::create_level rejects empty name
 * - program_manager::update_level persists field changes
 * - program_manager::delete_level cascades to courses + reflows sortorder
 * - program_manager::reorder_levels handles outsider IDs gracefully
 * - program_manager::assign_courses_to_level is idempotent + skips site course
 * - program_manager::unassign_course_from_level no-ops on non-member
 * - program_manager::enrol_users idempotent + rejects deleted/system users
 * - program_manager::unenrol_user no-ops on non-member
 * - program_manager::delete cascades through levels, courses, enrolments
 *
 * @package    local_airpay_programs
 * @category   test
 */
final class levels_test extends \advanced_testcase {

    private function seed_program(string $name = 'Test Program'): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_programs')) {
            $this->markTestSkipped('local_airpay_programs not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_programs', (object) [
            'name'                => $name,
            'description'         => '',
            'costcenterid'        => 0,
            'open_path'           => '/1',
            'status'              => program_manager::STATUS_ACTIVE,
            'visible'             => 1,
            'completion_required' => 1,
            'timecreated'         => $now,
            'timemodified'        => $now,
        ]);
    }

    // ─── Level CRUD ──────────────────────────────────────────────────────

    public function test_create_level_persists_with_auto_sortorder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_program();
        $first  = program_manager::create_level($pid, (object) ['name' => 'Level A']);
        $second = program_manager::create_level($pid, (object) ['name' => 'Level B']);

        $this->assertGreaterThan(0, $first);
        $this->assertGreaterThan(0, $second);
        $this->assertSame(2, program_manager::count_levels($pid));

        global $DB;
        $a = $DB->get_record('local_airpay_programs_levels', ['id' => $first], '*', MUST_EXIST);
        $b = $DB->get_record('local_airpay_programs_levels', ['id' => $second], '*', MUST_EXIST);
        $this->assertSame(0, (int) $a->sortorder);
        $this->assertSame(1, (int) $b->sortorder);
    }

    public function test_create_level_rejects_empty_name(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_program();

        try {
            program_manager::create_level($pid, (object) ['name' => '']);
            $this->fail('Expected missingrequiredfields');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingrequiredfields', $e->errorcode);
        }
    }

    public function test_update_level_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $lid = program_manager::create_level($pid, (object) ['name' => 'Old Name']);

        program_manager::update_level($lid, (object) [
            'name' => 'New Name',
            'completion_required' => 0,
        ]);

        $row = $DB->get_record('local_airpay_programs_levels', ['id' => $lid], '*', MUST_EXIST);
        $this->assertSame('New Name', $row->name);
        $this->assertEquals(0, (int) $row->completion_required);
    }

    public function test_delete_level_cascades_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $lid = program_manager::create_level($pid, (object) ['name' => 'L1']);
        $course = $this->getDataGenerator()->create_course();
        program_manager::assign_courses_to_level($lid, [(int) $course->id]);

        $this->assertSame(1, program_manager::count_level_courses($lid));

        program_manager::delete_level($lid);

        $this->assertFalse($DB->record_exists('local_airpay_programs_levels', ['id' => $lid]));
        $this->assertFalse($DB->record_exists('local_airpay_programs_courses', ['levelid' => $lid]));
    }

    public function test_delete_level_reflows_sortorder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $a = program_manager::create_level($pid, (object) ['name' => 'A']);   // sortorder 0
        $b = program_manager::create_level($pid, (object) ['name' => 'B']);   // sortorder 1
        $c = program_manager::create_level($pid, (object) ['name' => 'C']);   // sortorder 2

        // Delete the middle one — remaining two should have sortorder 0, 1.
        program_manager::delete_level($b);

        $this->assertEquals(0, (int) $DB->get_field('local_airpay_programs_levels', 'sortorder', ['id' => $a]));
        $this->assertEquals(1, (int) $DB->get_field('local_airpay_programs_levels', 'sortorder', ['id' => $c]));
    }

    public function test_reorder_levels_swaps_positions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $a = program_manager::create_level($pid, (object) ['name' => 'A']);
        $b = program_manager::create_level($pid, (object) ['name' => 'B']);
        $c = program_manager::create_level($pid, (object) ['name' => 'C']);

        // Original order [a=0, b=1, c=2]. New order: [c, a, b].
        $reordered = program_manager::reorder_levels($pid, [$c, $a, $b]);
        $this->assertSame(3, $reordered);

        $this->assertEquals(0, (int) $DB->get_field('local_airpay_programs_levels', 'sortorder', ['id' => $c]));
        $this->assertEquals(1, (int) $DB->get_field('local_airpay_programs_levels', 'sortorder', ['id' => $a]));
        $this->assertEquals(2, (int) $DB->get_field('local_airpay_programs_levels', 'sortorder', ['id' => $b]));
    }

    public function test_reorder_levels_skips_outsider_ids(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $a = program_manager::create_level($pid, (object) ['name' => 'A']);
        $b = program_manager::create_level($pid, (object) ['name' => 'B']);

        // Pass an outsider ID (99999) interleaved.
        $reordered = program_manager::reorder_levels($pid, [99999, $b, $a]);
        // Outsider is skipped (sortorder counter NOT incremented), so b=0, a=1.
        $this->assertSame(2, $reordered);
        $this->assertEquals(0, (int) $DB->get_field('local_airpay_programs_levels', 'sortorder', ['id' => $b]));
        $this->assertEquals(1, (int) $DB->get_field('local_airpay_programs_levels', 'sortorder', ['id' => $a]));
    }

    // ─── Course-per-level CRUD ───────────────────────────────────────────

    public function test_assign_courses_to_level_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_program();
        $lid = program_manager::create_level($pid, (object) ['name' => 'L1']);
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();

        $first  = program_manager::assign_courses_to_level($lid, [(int) $c1->id, (int) $c2->id]);
        $second = program_manager::assign_courses_to_level($lid, [(int) $c1->id]);

        $this->assertSame(2, $first);
        $this->assertSame(0, $second);   // already assigned — skipped
        $this->assertSame(2, program_manager::count_level_courses($lid));
    }

    public function test_assign_courses_skips_site_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_program();
        $lid = program_manager::create_level($pid, (object) ['name' => 'L1']);

        $count = program_manager::assign_courses_to_level($lid, [1]);   // site course
        $this->assertSame(0, $count);
    }

    public function test_unassign_course_from_level(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $lid = program_manager::create_level($pid, (object) ['name' => 'L1']);
        $c = $this->getDataGenerator()->create_course();
        program_manager::assign_courses_to_level($lid, [(int) $c->id]);

        $this->assertTrue($DB->record_exists('local_airpay_programs_courses',
            ['levelid' => $lid, 'courseid' => $c->id]));

        program_manager::unassign_course_from_level($lid, (int) $c->id);

        $this->assertFalse($DB->record_exists('local_airpay_programs_courses',
            ['levelid' => $lid, 'courseid' => $c->id]));
    }

    public function test_unassign_course_noop_on_non_member(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_program();
        $lid = program_manager::create_level($pid, (object) ['name' => 'L1']);

        // Pretend course id 99999 is on the level — it's not.
        $ok = program_manager::unassign_course_from_level($lid, 99999);
        $this->assertTrue($ok);   // returns true even when nothing was deleted.
    }

    // ─── Program enrolment ───────────────────────────────────────────────

    public function test_enrol_users_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_program();
        $u = $this->getDataGenerator()->create_user();

        $first = program_manager::enrol_users($pid, [(int) $u->id]);
        $second = program_manager::enrol_users($pid, [(int) $u->id]);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame(1, program_manager::count_enrolled($pid));
    }

    public function test_enrol_users_skips_system_and_deleted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $u->id]);

        $count = program_manager::enrol_users($pid, [1, 2, (int) $u->id]);
        $this->assertSame(0, $count);
    }

    public function test_unenrol_user_removes_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $u = $this->getDataGenerator()->create_user();
        program_manager::enrol_users($pid, [(int) $u->id]);

        $this->assertTrue($DB->record_exists('local_airpay_programs_users',
            ['programid' => $pid, 'userid' => $u->id]));

        program_manager::unenrol_user($pid, (int) $u->id);

        $this->assertFalse($DB->record_exists('local_airpay_programs_users',
            ['programid' => $pid, 'userid' => $u->id]));
    }

    public function test_get_enrolled_users_search(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_program();
        $a = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'A']);
        $b = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'B']);
        program_manager::enrol_users($pid, [(int) $a->id, (int) $b->id]);

        $rows = program_manager::get_enrolled_users($pid, 'alice');
        $this->assertCount(1, $rows);
        $first = reset($rows);
        $this->assertSame('Alice', $first->firstname);
    }

    public function test_count_enrolled_filtered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_program();
        $a = $this->getDataGenerator()->create_user(['firstname' => 'Alice']);
        $b = $this->getDataGenerator()->create_user(['firstname' => 'Bob']);
        program_manager::enrol_users($pid, [(int) $a->id, (int) $b->id]);

        $this->assertSame(2, program_manager::count_enrolled_filtered($pid));
        $this->assertSame(1, program_manager::count_enrolled_filtered($pid, 'alice'));
    }

    // ─── Cascade delete ──────────────────────────────────────────────────

    public function test_delete_program_cascades_through_all_tables(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $lid = program_manager::create_level($pid, (object) ['name' => 'L1']);
        $c = $this->getDataGenerator()->create_course();
        program_manager::assign_courses_to_level($lid, [(int) $c->id]);
        $u = $this->getDataGenerator()->create_user();
        program_manager::enrol_users($pid, [(int) $u->id]);

        // All three child tables should have rows.
        $this->assertTrue($DB->record_exists('local_airpay_programs_levels', ['programid' => $pid]));
        $this->assertTrue($DB->record_exists('local_airpay_programs_courses', ['levelid' => $lid]));
        $this->assertTrue($DB->record_exists('local_airpay_programs_users', ['programid' => $pid]));

        program_manager::delete($pid);

        $this->assertFalse($DB->record_exists('local_airpay_programs', ['id' => $pid]));
        $this->assertFalse($DB->record_exists('local_airpay_programs_levels', ['programid' => $pid]));
        $this->assertFalse($DB->record_exists('local_airpay_programs_courses', ['levelid' => $lid]));
        $this->assertFalse($DB->record_exists('local_airpay_programs_users', ['programid' => $pid]));
    }
}
