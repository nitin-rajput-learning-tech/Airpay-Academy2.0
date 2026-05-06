<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_exams;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD tests for airpay_exams.
 *
 * @package    local_airpay_exams
 * @category   test
 */
final class crud_test extends \advanced_testcase {

    private function seed_exam(string $name = 'Test Exam'): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_exams')) {
            $this->markTestSkipped('local_airpay_exams table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_exams', (object) [
            'name'         => $name,
            'quizid'       => 0,
            'costcenterid' => 0,
            'open_path'    => '/1',
            'duration'     => 1800,
            'passinggrade' => 70,
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    public function test_toggle_status_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_exam();
        $original = (int) $DB->get_field('local_airpay_exams', 'status', ['id' => $eid]);

        exam_manager::toggle_status($eid);
        $newval = (int) $DB->get_field('local_airpay_exams', 'status', ['id' => $eid]);
        $this->assertNotEquals($original, $newval);
    }

    public function test_delete_removes_exam(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_exam();
        exam_manager::delete($eid);
        $this->assertFalse($DB->record_exists('local_airpay_exams', ['id' => $eid]));
    }

    public function test_external_delete_exam_capability_required(): void {
        $this->resetAfterTest();
        $eid = $this->seed_exam();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\delete_exam::execute($eid);
    }

    public function test_external_toggle_status_capability_required(): void {
        $this->resetAfterTest();
        $eid = $this->seed_exam();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\toggle_status::execute($eid, true);
    }
}
