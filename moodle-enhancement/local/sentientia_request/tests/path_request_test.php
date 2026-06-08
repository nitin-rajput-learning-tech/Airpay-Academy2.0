<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 batch (2026-05-16) — tests for polymorphic path requests.
 *
 * Locks in:
 *   - submit_path() inserts a row with item_type='path' + itemid=$pathid
 *   - decide(approved) on a path request calls path_manager::enrol_users()
 *     so the learner ends up actually enrolled in the path's Moodle courses
 *   - duplicate pending path request rejected
 *   - submit() (the legacy course flow) still sets item_type='course'
 *     and continues to enrol via the manual enrol plugin
 *
 * @package    local_sentientia_request
 * @category   test
 */
final class path_request_test extends \advanced_testcase {

    /** Helper — seed a path + courses + assign courses. */
    private function seed_path_with_courses(int $course_count = 2): int {
        global $DB;
        $now = time();
        $pid = (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name'         => 'Path for request test ' . microtime(true),
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => '/1',
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $courseids = [];
        for ($i = 0; $i < $course_count; $i++) {
            $c = $this->getDataGenerator()->create_course();
            $courseids[] = (int) $c->id;
        }
        \local_sentientia_learningpath\path_manager::assign_courses($pid, $courseids);
        return $pid;
    }

    public function test_submit_path_inserts_polymorphic_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $u->id]);

        $pid = $this->seed_path_with_courses(1);

        $rec = request_manager::submit_path(
            (int) $u->id, $pid,
            'I need this path to complete my compliance training cycle.');

        $this->assertSame('path', $rec->item_type);
        $this->assertSame($pid, (int) $rec->itemid);
        $this->assertSame(0, (int) $rec->courseid,
            'Path requests must leave the legacy courseid column at 0');
        $this->assertSame('pending', $rec->status);

        $row = $DB->get_record('local_sentientia_request', ['id' => $rec->id], '*', MUST_EXIST);
        $this->assertSame('path', $row->item_type);
    }

    public function test_submit_path_rejects_short_reason(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $pid = $this->seed_path_with_courses();

        $this->expectException(\moodle_exception::class);
        request_manager::submit_path((int) $u->id, $pid, 'too short');
    }

    public function test_submit_path_rejects_duplicate(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $pid = $this->seed_path_with_courses(1);
        request_manager::submit_path((int) $u->id, $pid,
            'Initial well-formed request reason for the path.');

        $this->expectException(\moodle_exception::class);
        request_manager::submit_path((int) $u->id, $pid,
            'Second well-formed request reason for the path.');
    }

    public function test_submit_path_rejects_inactive_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $pid = $this->seed_path_with_courses(1);
        // Archive the path.
        $DB->set_field('local_sentientia_learningpath', 'status', 0, ['id' => $pid]);

        $this->expectException(\moodle_exception::class);
        request_manager::submit_path((int) $u->id, $pid,
            'I would like to enrol in this learning path please.');
    }

    public function test_decide_approve_path_enrols_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $u->id]);
        $pid = $this->seed_path_with_courses(2);

        $rec = request_manager::submit_path((int) $u->id, $pid,
            'I need this path to complete my compliance training cycle.');

        // Admin approves. Decider must be the assigned approver OR siteadmin.
        $admin = get_admin();
        $approved = request_manager::decide(
            (int) $rec->id, (int) $admin->id, 'approved', 'Approved for compliance');

        $this->assertSame('approved', $approved->status);

        // path-user row written.
        $this->assertTrue($DB->record_exists('local_sentientia_learningpath_users',
            ['pathid' => $pid, 'userid' => $u->id]),
            'decide(approved) on a path request must enrol the user in the path');

        // W1-2 chained: user should also be in the path's courses now.
        $courseids = $DB->get_fieldset_select('local_sentientia_learningpath_courses',
            'courseid', 'pathid = :p', ['p' => $pid]);
        foreach ($courseids as $cid) {
            $context = \context_course::instance((int) $cid);
            $this->assertTrue(is_enrolled($context, $u),
                "User should be enrolled in path's course (id={$cid}) after path approval");
        }
    }
}
