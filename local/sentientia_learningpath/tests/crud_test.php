<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD tests for sentientia_learningpath.
 *
 * @package    local_sentientia_learningpath
 * @category   test
 */
final class crud_test extends \advanced_testcase {

    private function seed_path(string $name = 'Test Path'): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_learningpath')) {
            $this->markTestSkipped('local_sentientia_learningpath table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name'         => $name,
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => '/1',
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
        $pid = $this->seed_path();

        path_manager::toggle_status($pid, false);   // archive
        $status = (int) $DB->get_field('local_sentientia_learningpath', 'status', ['id' => $pid]);
        $this->assertSame(path_manager::STATUS_ARCHIVED, $status);

        path_manager::toggle_status($pid, true);    // activate
        $status = (int) $DB->get_field('local_sentientia_learningpath', 'status', ['id' => $pid]);
        $this->assertSame(path_manager::STATUS_ACTIVE, $status);
    }

    public function test_delete_removes_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();

        path_manager::delete($pid);
        $this->assertFalse($DB->record_exists('local_sentientia_learningpath', ['id' => $pid]));
    }

    public function test_external_delete_path_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\delete_path::execute($pid);
    }

    public function test_external_toggle_status_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\toggle_status::execute($pid, true);
    }
}
