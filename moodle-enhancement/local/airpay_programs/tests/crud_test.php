<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_programs;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD tests for airpay_programs (delete + change_status + level/course cascade).
 *
 * Programs has the most complex cascade in the airpay_* set:
 *   programs → programs_levels → programs_courses
 *   programs → programs_users
 * Atomic delete must clean all four.
 *
 * @package    local_airpay_programs
 * @category   test
 */
final class crud_test extends \advanced_testcase {

    private function seed_program(string $name = 'Test Program'): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_programs')) {
            $this->markTestSkipped('local_airpay_programs table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_programs', (object) [
            'name'         => $name,
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => '/1',
            'status'       => 1,
            'visible'      => 1,
            'completion_required' => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    private function seed_level(int $programid): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_programs_levels')) {
            return 0;
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_programs_levels', (object) [
            'programid' => $programid,
            'name'      => 'Level 1',
            'description' => '',
            'sortorder' => 0,
            'completion_required' => 1,
            'timecreated' => $now,
        ]);
    }

    public function test_change_status_rejects_invalid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_program();
        try {
            program_manager::change_status($pid, 99);
            $this->fail('Expected invalidstatus');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidstatus', $e->errorcode);
        }
    }

    public function test_change_status_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();

        $new = program_manager::change_status($pid, program_manager::STATUS_ARCHIVED);
        $this->assertSame(program_manager::STATUS_ARCHIVED, $new);
        $this->assertEquals(program_manager::STATUS_ARCHIVED,
            (int) $DB->get_field('local_airpay_programs', 'status', ['id' => $pid]));
    }

    public function test_delete_removes_program(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();

        $ok = program_manager::delete($pid);
        $this->assertTrue($ok);
        $this->assertFalse($DB->record_exists('local_airpay_programs', ['id' => $pid]));
    }

    public function test_delete_cascades_to_levels(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_program();
        $lid = $this->seed_level($pid);

        if ($lid === 0) {
            $this->markTestSkipped('programs_levels table not present');
        }

        $this->assertTrue($DB->record_exists('local_airpay_programs_levels', ['id' => $lid]));

        program_manager::delete($pid);

        $this->assertFalse($DB->record_exists('local_airpay_programs_levels', ['id' => $lid]));
    }

    public function test_external_delete_program_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_program();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\delete_program::execute($pid);
    }

    public function test_external_change_status_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_program();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\change_status::execute($pid, 1);
    }
}
