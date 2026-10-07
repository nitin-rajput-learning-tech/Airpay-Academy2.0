<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * local_sentientia_evaluation.evaluationmode (EV-17): the form says whether it is a supervisor evaluation.
 *
 * Before, the plugin guessed from the responses: a form was a supervisor evaluation when some response named a
 * subject. That missed an ANONYMOUS supervisor evaluation (the subject is deliberately not kept) and a completion
 * from before BizLMS recorded who filled the form in (evaluatedby 0), so the person evaluated was listed as having
 * "responded". The form itself now carries SE (every native form, and a BizLMS self evaluation) or SP.
 *
 * Nothing here runs the importer: the forms and rows are written the way the importer writes them, and the plugin is
 * asked what it makes of them. tests/bizlms_import_test.php checks what the importer writes.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_evaluation\learner_history
 * @covers     \local_sentientia_evaluation\evaluation_manager
 *
 * @group local_sentientia_evaluation
 * @group bizlms_import
 */
final class evaluation_mode_test extends \advanced_testcase {

    /**
     * @param string $name
     * @param string|null $mode null leaves the column to its default, as evaluation_manager::create() does
     * @param int $anonymous
     * @return int
     */
    private function form(string $name, ?string $mode = null, int $anonymous = 0): int {
        global $DB;
        $id = evaluation_manager::create((object) ['name' => $name, 'anonymous' => $anonymous]);
        if ($mode !== null) {
            $DB->set_field('local_sentientia_evaluation', 'evaluationmode', $mode, ['id' => $id]);
        }
        return $id;
    }

    private function assign(int $form, int $user, string $status, int $responded): void {
        global $DB;
        $DB->insert_record('local_sentientia_evaluation_assign', (object) [
            'evaluationid' => $form, 'userid' => $user, 'trigger_event' => 'manual', 'source_id' => 0,
            'status' => $status, 'assigned_by_userid' => null, 'due_at' => null, 'responded_at' => $responded,
            'timecreated' => $responded, 'timemodified' => $responded,
        ]);
    }

    private function respond(int $form, int $user, ?int $subject, int $time): void {
        global $DB;
        $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid' => $form, 'userid' => $user, 'subject_userid' => $subject, 'response_data' => '{}',
            'timesubmitted' => $time,
        ]);
    }

    public function test_the_column_exists_with_the_definition_install_xml_and_the_upgrade_step_share(): void {
        global $DB;
        $this->resetAfterTest();
        $column = $DB->get_columns('local_sentientia_evaluation')['evaluationmode'] ?? null;
        $this->assertNotNull($column, 'the column exists after an install or an upgrade');
        $this->assertSame(2, (int) $column->max_length);
        $this->assertTrue((bool) $column->not_null);
        $this->assertSame('SE', $column->default_value);

        // install.xml and the guarded upgrade step describe the same column.
        $xml = new \DOMDocument();
        $this->assertTrue($xml->load(__DIR__ . '/../db/install.xml'));
        $found = null;
        foreach ($xml->getElementsByTagName('TABLE') as $table) {
            if ($table->getAttribute('NAME') !== 'local_sentientia_evaluation') {
                continue;
            }
            foreach ($table->getElementsByTagName('FIELD') as $field) {
                if ($field->getAttribute('NAME') === 'evaluationmode') {
                    $found = $field;
                }
            }
        }
        $this->assertNotNull($found, 'install.xml declares it on the form table');
        $this->assertSame('char', $found->getAttribute('TYPE'));
        $this->assertSame('2', $found->getAttribute('LENGTH'));
        $this->assertSame('true', $found->getAttribute('NOTNULL'));
        $this->assertSame('SE', $found->getAttribute('DEFAULT'));

        $upgrade = (string) file_get_contents(__DIR__ . '/../db/upgrade.php');
        $this->assertStringContainsString("new xmldb_field('evaluationmode', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, 'SE', 'anonymous')",
            $upgrade);
        $this->assertMatchesRegularExpression('/if \(\$oldversion < 2026100701\) \{.*?field_exists\(\$table, \$field\).*?'
            . "upgrade_plugin_savepoint\\(true, 2026100701, 'local', 'sentientia_evaluation'\\)/s", $upgrade,
            'a guarded step with its savepoint');
        $this->assertStringContainsString('2026100701', (string) file_get_contents(__DIR__ . '/../version.php'));
        $this->assertSame(2026100701, \local_sentientia_evaluation\bizlms\importer::REQUIRES_VERSION,
            'the importer refuses to run below the version that adds the column it writes');
    }

    public function test_a_native_form_is_a_self_evaluation(): void {
        global $DB;
        $this->resetAfterTest();
        $id = evaluation_manager::create((object) ['name' => 'Native']);
        $this->assertSame('SE', $DB->get_field('local_sentientia_evaluation', 'evaluationmode', ['id' => $id]));
        $this->assertSame(evaluation_manager::MODE_SELF, evaluation_manager::get($id)->evaluationmode);
        // Editing it never makes it a supervisor evaluation: only the import writes SP.
        evaluation_manager::update($id, (object) ['name' => 'Renamed', 'evaluationmode' => 'SP']);
        $this->assertSame('SE', $DB->get_field('local_sentientia_evaluation', 'evaluationmode', ['id' => $id]));
    }

    public function test_the_subject_column_needs_a_supervisor_form(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $supervisor = (int) $gen->create_user()->id;
        $subject = (int) $gen->create_user()->id;

        $sp = $this->form('Supervisor review', 'SP');
        $this->respond($sp, $supervisor, $subject, 1700000100);
        $this->assertTrue(evaluation_manager::shows_subject(evaluation_manager::get($sp)));

        // The same rows on a form that does not say it is a supervisor evaluation: no Subject column.
        $se = $this->form('Self evaluation');
        $this->respond($se, $supervisor, $subject, 1700000100);
        $this->assertFalse(evaluation_manager::shows_subject(evaluation_manager::get($se)));

        // A supervisor form where nobody was named: nothing to show.
        $empty = $this->form('Supervisor review, no subject yet', 'SP');
        $this->respond($empty, $supervisor, null, 1700000100);
        $this->assertFalse(evaluation_manager::shows_subject(evaluation_manager::get($empty)));

        // A protected supervisor form never names the subject.
        $anonymous = $this->form('Anonymous supervisor review', 'SP', 1);
        $this->respond($anonymous, $supervisor, $subject, 1700000100);
        $this->assertFalse(evaluation_manager::shows_subject(evaluation_manager::get($anonymous)));
    }

    public function test_the_person_evaluated_is_never_listed_as_having_responded_to_a_supervisor_form(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $me = (int) $gen->create_user()->id;
        $supervisor = (int) $gen->create_user()->id;
        $t = 1700000000;

        // A named supervisor evaluation: the assignment names the person evaluated, the SUPERVISOR responded.
        $named = $this->form('Named supervisor review', 'SP');
        $this->assign($named, $me, 'responded', $t + 100);
        $this->respond($named, $supervisor, $me, $t + 110);
        // An ANONYMOUS supervisor evaluation: the response keeps no subject, so the old rule could not tell it.
        $anonymous = $this->form('Anonymous supervisor review', 'SP', 1);
        $this->assign($anonymous, $me, 'responded', $t + 200);
        $this->respond($anonymous, 0, null, $t + 210);
        // An old completion that names no evaluator: the response names the person evaluated as the responder, and
        // there is no subject either.
        $old = $this->form('Old supervisor review', 'SP');
        $this->assign($old, $me, 'responded', $t + 300);
        $this->respond($old, $me, null, $t + 310);
        // A self evaluation of the same person, from the same system: still listed.
        $self = $this->form('Self evaluation', 'SE');
        $this->assign($self, $me, 'responded', $t + 400);
        $this->respond($self, $me, null, $t + 410);
        // A native form has no mode set by anybody and is a self evaluation too.
        $native = $this->form('Native survey');
        $this->assign($native, $me, 'assigned', 0);

        $names = array_map(static fn(\stdClass $r): string => $r->name, learner_history::for_user($me));
        sort($names);
        $this->assertSame(['Native survey', 'Self evaluation'], $names,
            'no supervisor evaluation is listed, named or anonymous, with or without a subject');

        // The supervisor is not shown the form either: BizLMS listed only self evaluations to a learner, and
        // "responded" there means they answered ABOUT somebody.
        $this->assertSame([], learner_history::for_user($supervisor));
    }
}
