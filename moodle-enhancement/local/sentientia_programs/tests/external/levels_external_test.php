<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\external;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_programs\program_manager;

/**
 * G-03 — Capability + happy-path tests for the 7 new WS endpoints.
 *
 * Endpoints covered:
 * - list_program_levels       (read,  :view)
 * - delete_level              (write, :update)
 * - reorder_levels            (write, :update — bound check)
 * - list_level_courses        (read,  :view)
 * - unassign_level_course     (write, :update)
 * - list_program_users        (read,  :view)
 * - unenrol_program_user      (write, :enrol)
 *
 * @package    local_sentientia_programs
 * @category   test
 */
final class levels_external_test extends \advanced_testcase {

    private function seed_program_and_level(): array {
        global $DB;
        $now = time();
        $pid = (int) $DB->insert_record('local_sentientia_programs', (object) [
            'name'                => 'Cap Test',
            'description'         => '',
            'costcenterid'        => 0,
            'open_path'           => '/1',
            'status'              => 1,
            'visible'             => 1,
            'completion_required' => 1,
            'timecreated'         => $now,
            'timemodified'        => $now,
        ]);
        $lid = program_manager::create_level($pid, (object) ['name' => 'L1']);
        return ['programid' => $pid, 'levelid' => $lid];
    }

    // ─── list_program_levels ──────────────────────────────────────────────

    public function test_list_levels_requires_view(): void {
        $this->resetAfterTest();
        $ids = $this->seed_program_and_level();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_program_levels::execute($ids['programid']);
    }

    public function test_list_levels_admin_returns_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_program_and_level();

        $resp = list_program_levels::execute($ids['programid']);
        $this->assertGreaterThanOrEqual(1, $resp['total']);
    }

    // ─── delete_level ──────────────────────────────────────────────────────

    public function test_delete_level_requires_update(): void {
        $this->resetAfterTest();
        $ids = $this->seed_program_and_level();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        delete_level::execute($ids['levelid']);
    }

    public function test_delete_level_admin_succeeds(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_program_and_level();

        $resp = delete_level::execute($ids['levelid']);
        $this->assertSame($ids['levelid'], $resp['levelid']);
    }

    // ─── reorder_levels ────────────────────────────────────────────────────

    public function test_reorder_levels_requires_update(): void {
        $this->resetAfterTest();
        $ids = $this->seed_program_and_level();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        reorder_levels::execute($ids['programid'], [$ids['levelid']]);
    }

    public function test_reorder_levels_rejects_too_many(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_program_and_level();

        $levels = [];
        for ($i = 0; $i < 201; $i++) {
            $levels[] = $i + 1000;
        }
        try {
            reorder_levels::execute($ids['programid'], $levels);
            $this->fail('Expected toomanylevels');
        } catch (\moodle_exception $e) {
            $this->assertSame('toomanylevels', $e->errorcode);
        }
    }

    public function test_reorder_levels_admin_succeeds(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_program_and_level();

        $resp = reorder_levels::execute($ids['programid'], [$ids['levelid']]);
        $this->assertSame(1, $resp['reordered']);
    }

    // ─── list_level_courses ────────────────────────────────────────────────

    public function test_list_level_courses_requires_view(): void {
        $this->resetAfterTest();
        $ids = $this->seed_program_and_level();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_level_courses::execute($ids['levelid']);
    }

    public function test_list_level_courses_admin_returns_total(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_program_and_level();
        $c = $this->getDataGenerator()->create_course();
        program_manager::assign_courses_to_level($ids['levelid'], [(int) $c->id]);

        $resp = list_level_courses::execute($ids['levelid']);
        $this->assertSame(1, $resp['total']);
    }

    // ─── unassign_level_course ─────────────────────────────────────────────

    public function test_unassign_level_course_requires_update(): void {
        $this->resetAfterTest();
        $ids = $this->seed_program_and_level();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        unassign_level_course::execute($ids['levelid'], 999);
    }

    // ─── list_program_users ────────────────────────────────────────────────

    public function test_list_users_requires_view(): void {
        $this->resetAfterTest();
        $ids = $this->seed_program_and_level();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_program_users::execute($ids['programid']);
    }

    public function test_list_users_admin_returns_total(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_program_and_level();
        $u = $this->getDataGenerator()->create_user();
        program_manager::enrol_users($ids['programid'], [(int) $u->id]);

        $resp = list_program_users::execute($ids['programid']);
        $this->assertSame(1, $resp['total']);
    }

    // ─── unenrol_program_user ──────────────────────────────────────────────

    public function test_unenrol_user_requires_enrol(): void {
        $this->resetAfterTest();
        $ids = $this->seed_program_and_level();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        unenrol_program_user::execute($ids['programid'], (int) $u->id);
    }
}
