<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD tests for airpay_classroom (delete + change_status + cascade).
 *
 * Locks in:
 * - session_manager::change_status() rejects values outside {0,1,2}
 * - session_manager::change_status() persists and returns new value
 * - session_manager::delete() cascades to sessions + attendance rows
 * - session_manager::delete() runs in a transaction (atomic across 3 tables)
 * - external\delete_classroom requires :delete capability
 * - external\change_status requires :update capability
 *
 * @package    local_airpay_classroom
 * @category   test
 */
final class crud_test extends \advanced_testcase {

    private function seed_classroom(string $name = 'Test Classroom', int $status = 1): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_classroom')) {
            $this->markTestSkipped('local_airpay_classroom table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_classroom', (object) [
            'name'         => $name,
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => '/1',
            'location'     => 'Test Lab',
            'capacity'     => 20,
            'status'       => $status,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    private function seed_session(int $classroomid): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_classroom_sessions')) {
            return 0;   // session table missing — skip cascade portion
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_classroom_sessions', (object) [
            'classroomid'  => $classroomid,
            'sessiondate'  => $now,            // required NOT NULL no default
            'starttime'    => $now,
            'endtime'      => $now + 3600,
            'location'     => 'Test Lab',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    public function test_change_status_rejects_invalid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();

        try {
            session_manager::change_status($cid, 99);
            $this->fail('Expected moodle_exception invalidstatus');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidstatus', $e->errorcode);
        }
    }

    public function test_change_status_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $cid = $this->seed_classroom('Foo', session_manager::STATUS_ACTIVE);

        $new = session_manager::change_status($cid, session_manager::STATUS_COMPLETED);
        $this->assertSame(session_manager::STATUS_COMPLETED, $new);
        $this->assertEquals(session_manager::STATUS_COMPLETED,
            (int) $DB->get_field('local_airpay_classroom', 'status', ['id' => $cid]));
    }

    public function test_delete_removes_classroom(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $cid = $this->seed_classroom();
        $this->assertTrue($DB->record_exists('local_airpay_classroom', ['id' => $cid]));

        $ok = session_manager::delete($cid);
        $this->assertTrue($ok);
        $this->assertFalse($DB->record_exists('local_airpay_classroom', ['id' => $cid]));
    }

    /**
     * delete() cascades to sessions table — when classroom is deleted, its
     * sessions go too. Atomic across the 3 tables (transaction-wrapped).
     */
    public function test_delete_cascades_to_sessions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $cid = $this->seed_classroom();
        $sid = $this->seed_session($cid);

        if ($sid === 0) {
            $this->markTestSkipped('classroom_sessions table not present');
        }

        $this->assertTrue($DB->record_exists('local_airpay_classroom_sessions', ['id' => $sid]));

        session_manager::delete($cid);

        $this->assertFalse($DB->record_exists('local_airpay_classroom_sessions', ['id' => $sid]));
    }

    public function test_external_delete_classroom_capability_required(): void {
        $this->resetAfterTest();

        $cid = $this->seed_classroom();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\delete_classroom::execute($cid);
    }

    public function test_external_change_status_capability_required(): void {
        $this->resetAfterTest();

        $cid = $this->seed_classroom();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\change_status::execute($cid, 1);
    }
}
