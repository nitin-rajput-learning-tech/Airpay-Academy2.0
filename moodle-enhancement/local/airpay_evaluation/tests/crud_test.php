<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD tests for airpay_evaluation (delete + change_status + question CRUD + reorder).
 *
 * @package    local_airpay_evaluation
 * @category   test
 */
final class crud_test extends \advanced_testcase {

    private function seed_evaluation(string $name = 'Test Eval', int $status = 0): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_evaluation')) {
            $this->markTestSkipped('local_airpay_evaluation table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_evaluation', (object) [
            'name'              => $name,
            'description'       => '',
            'kirkpatrick_level' => 1,
            'trigger_event'     => 'manual',
            'days_after'        => 0,
            'costcenterid'      => 0,
            'open_path'         => '/1',
            'status'            => $status,
            'anonymous'         => 0,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);
    }

    private function seed_question(int $evalid, int $sortorder = 0): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_evaluation_questions')) {
            return 0;
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_evaluation_questions', (object) [
            'evaluationid' => $evalid,
            'questiontype' => 'rating',
            'questiontext' => 'How satisfied are you?',
            'options'      => '',
            'required'     => 1,
            'sortorder'    => $sortorder,
            'timecreated'  => $now,
        ]);
    }

    public function test_change_status_rejects_invalid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid = $this->seed_evaluation();
        try {
            evaluation_manager::change_status($eid, 99);
            $this->fail('Expected invalidstatus');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidstatus', $e->errorcode);
        }
    }

    public function test_change_status_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_evaluation('Foo', evaluation_manager::STATUS_DRAFT);

        evaluation_manager::change_status($eid, evaluation_manager::STATUS_ACTIVE);
        $this->assertEquals(evaluation_manager::STATUS_ACTIVE,
            (int) $DB->get_field('local_airpay_evaluation', 'status', ['id' => $eid]));
    }

    public function test_delete_removes_evaluation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_evaluation();

        evaluation_manager::delete($eid);
        $this->assertFalse($DB->record_exists('local_airpay_evaluation', ['id' => $eid]));
    }

    public function test_delete_cascades_to_questions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_evaluation();
        $qid = $this->seed_question($eid);
        if ($qid === 0) { $this->markTestSkipped('questions table missing'); }

        $this->assertTrue($DB->record_exists('local_airpay_evaluation_questions', ['id' => $qid]));

        evaluation_manager::delete($eid);

        $this->assertFalse($DB->record_exists('local_airpay_evaluation_questions', ['id' => $qid]));
    }

    public function test_delete_question_removes_question(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_evaluation();
        $qid = $this->seed_question($eid);
        if ($qid === 0) { $this->markTestSkipped('questions table missing'); }

        evaluation_manager::delete_question($qid);
        $this->assertFalse($DB->record_exists('local_airpay_evaluation_questions', ['id' => $qid]));
    }

    public function test_reorder_questions_updates_sortorder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_evaluation();
        $q1 = $this->seed_question($eid, 0);
        $q2 = $this->seed_question($eid, 1);
        $q3 = $this->seed_question($eid, 2);
        if (!$q1 || !$q2 || !$q3) { $this->markTestSkipped('questions table missing'); }

        // Reverse order: q3, q2, q1.
        evaluation_manager::reorder_questions($eid, [$q3, $q2, $q1]);

        $this->assertEquals(0, (int) $DB->get_field('local_airpay_evaluation_questions', 'sortorder', ['id' => $q3]));
        $this->assertEquals(1, (int) $DB->get_field('local_airpay_evaluation_questions', 'sortorder', ['id' => $q2]));
        $this->assertEquals(2, (int) $DB->get_field('local_airpay_evaluation_questions', 'sortorder', ['id' => $q1]));
    }

    public function test_external_delete_evaluation_capability_required(): void {
        $this->resetAfterTest();
        $eid = $this->seed_evaluation();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\delete_evaluation::execute($eid);
    }

    public function test_external_change_status_capability_required(): void {
        $this->resetAfterTest();
        $eid = $this->seed_evaluation();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\change_status::execute($eid, 1);
    }
}
