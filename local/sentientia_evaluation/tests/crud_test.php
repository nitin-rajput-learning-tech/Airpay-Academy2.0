<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD tests for sentientia_evaluation (delete + change_status + question CRUD + reorder).
 *
 * @package    local_sentientia_evaluation
 * @category   test
 */
final class crud_test extends \advanced_testcase {

    private function seed_evaluation(string $name = 'Test Eval', int $status = 0): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_evaluation')) {
            $this->markTestSkipped('local_sentientia_evaluation table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_evaluation', (object) [
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
        if (!$DB->get_manager()->table_exists('local_sentientia_evaluation_questions')) {
            return 0;
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_evaluation_questions', (object) [
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
            (int) $DB->get_field('local_sentientia_evaluation', 'status', ['id' => $eid]));
    }

    public function test_delete_removes_evaluation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_evaluation();

        evaluation_manager::delete($eid);
        $this->assertFalse($DB->record_exists('local_sentientia_evaluation', ['id' => $eid]));
    }

    public function test_delete_cascades_to_questions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_evaluation();
        $qid = $this->seed_question($eid);
        if ($qid === 0) { $this->markTestSkipped('questions table missing'); }

        $this->assertTrue($DB->record_exists('local_sentientia_evaluation_questions', ['id' => $qid]));

        evaluation_manager::delete($eid);

        $this->assertFalse($DB->record_exists('local_sentientia_evaluation_questions', ['id' => $qid]));
    }

    public function test_delete_question_removes_question(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $eid = $this->seed_evaluation();
        $qid = $this->seed_question($eid);
        if ($qid === 0) { $this->markTestSkipped('questions table missing'); }

        evaluation_manager::delete_question($qid);
        $this->assertFalse($DB->record_exists('local_sentientia_evaluation_questions', ['id' => $qid]));
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

        $this->assertEquals(0, (int) $DB->get_field('local_sentientia_evaluation_questions', 'sortorder', ['id' => $q3]));
        $this->assertEquals(1, (int) $DB->get_field('local_sentientia_evaluation_questions', 'sortorder', ['id' => $q2]));
        $this->assertEquals(2, (int) $DB->get_field('local_sentientia_evaluation_questions', 'sortorder', ['id' => $q1]));
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

    /**
     * Export a form as a template and make a new evaluation from it, both ways (the JSON file the import page reads,
     * and a saved template row): every question comes back with the settings it had. Reusing a form this way is how
     * an imported BizLMS form is run again, and its numeric bounds were lost on the way (the export writes
     * {min, max}, the import joined them into a newline string and the question came back unbounded).
     */
    public function test_template_round_trip_keeps_every_question_setting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = evaluation_manager::create((object) ['name' => 'Round trip', 'anonymous' => 0]);
        $questions = [
            ['questiontype' => 'rating', 'questiontext' => 'Overall', 'required' => 1, 'sortorder' => 3],
            ['questiontype' => 'multichoice', 'questiontext' => 'Colour', 'options' => "Red\nGreen\nBlue",
                'required' => 0, 'sortorder' => 5],
            ['questiontype' => 'multichoice_multi', 'questiontext' => 'Topics', 'options' => "A\nB",
                'required' => 1, 'sortorder' => 7],
            ['questiontype' => 'numeric', 'questiontext' => 'Score', 'numeric_min' => 1, 'numeric_max' => 5,
                'required' => 0, 'sortorder' => 9],
            ['questiontype' => 'numeric', 'questiontext' => 'How many', 'required' => 1, 'sortorder' => 10],
            ['questiontype' => 'text', 'questiontext' => 'Comments', 'required' => 1, 'anonymous' => 1, 'sortorder' => 11],
        ];
        foreach ($questions as $question) {
            evaluation_manager::create_question((object) ($question + ['evaluationid' => $source]));
        }
        $original = array_values(evaluation_manager::get_questions($source));
        $this->assertCount(6, $original);

        // Way 1: the JSON file, as the import page reads it.
        $payload = json_decode(json_encode(evaluation_manager::export_template($source)), true);
        $fromfile = evaluation_manager::import_template($payload);

        // Way 2: a saved template row.
        $templateid = evaluation_manager::save_template_from_evaluation($source, 'Saved', '', (int) get_admin()->id);
        $fromrow = evaluation_manager::create_evaluation_from_template($templateid);

        foreach ([$fromfile, $fromrow] as $created) {
            $this->assertSame(6, $created['question_count']);
            $this->assertNotSame($source, $created['id']);
            $copies = array_values(evaluation_manager::get_questions($created['id']));
            $this->assertCount(6, $copies);
            foreach ($original as $i => $question) {
                $copy = $copies[$i];
                $label = $question->questiontype . ' "' . $question->questiontext . '"';
                $this->assertSame($question->questiontype, $copy->questiontype, $label);
                $this->assertSame($question->questiontext, $copy->questiontext, $label);
                $this->assertSame((int) $question->required, (int) $copy->required, $label . ' required');
                $this->assertSame((int) $question->anonymous, (int) $copy->anonymous, $label . ' anonymous');
                $this->assertSame((int) $question->sortorder, (int) $copy->sortorder, $label . ' sortorder');
                $this->assertSame(evaluation_manager::decode_options($question->options),
                    evaluation_manager::decode_options($copy->options), $label . ' options');
            }
            // The bounded number question keeps its range, the unbounded one stays unbounded.
            $this->assertSame(['min' => 1, 'max' => 5], evaluation_manager::decode_numeric_bounds($copies[3]->options));
            $this->assertSame(['min' => null, 'max' => null], evaluation_manager::decode_numeric_bounds($copies[4]->options));
        }
    }
}
