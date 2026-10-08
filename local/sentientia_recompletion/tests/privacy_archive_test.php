<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_sentientia_recompletion\privacy\provider;

/**
 * The evidence archive (ADR-032) and the right to erasure.
 *
 * The archive is compliance evidence: a reset deleted the live rows, so these rows are what is left of a person's
 * earlier cycles. So erasure takes out the PERSON (the userid column, the learner and the overriding
 * administrator inside the JSON payload, what a learner typed into a questionnaire) and leaves the ROW. The DPDP
 * flow keeps the subject's rows, with every structured value, and empties the free text in them (what they typed,
 * what a grader wrote about them); it anonymises an administrator or grader named in somebody else's row.
 * The table must also be declared, or a null-provider-style claim that no personal data is held would come back.
 *
 * @package    local_sentientia_recompletion
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_recompletion\privacy\provider
 * @covers     \local_sentientia_recompletion\archive_privacy
 *
 * @group local_sentientia_recompletion
 * @group bizlms_import
 */
final class privacy_archive_test extends provider_testcase {

    /** @var \stdClass The person being erased. */
    private $subject;
    /** @var \stdClass Somebody else. */
    private $other;
    /** @var \stdClass An administrator who overrode both people's activity completions. */
    private $admin;
    /** @var \stdClass A teacher who graded both people and is named ONLY in the gradebook rows. */
    private $grader;
    /** @var int Course id. */
    private $courseid;
    /** @var array<string, int> Archive row ids by name. */
    private $rows = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $g = $this->getDataGenerator();
        $this->subject = $g->create_user();
        $this->other = $g->create_user();
        $this->admin = $g->create_user();
        $this->grader = $g->create_user();
        $this->courseid = (int) $g->create_course(['enablecompletion' => 1])->id;

        $completion = fn(\stdClass $user) => [
            'id' => '1', 'coursemoduleid' => '5', 'userid' => (string) $user->id, 'completionstate' => '1',
            'overrideby' => (string) $this->admin->id, 'timemodified' => '100', 'course' => (string) $this->courseid,
        ];
        $this->rows['subject_completion'] = $this->archive($this->subject, 'activity_completion', $completion($this->subject));
        $this->rows['subject_answer'] = $this->archive($this->subject, 'questionnaire_answer',
            ['id' => '2', 'response_id' => '3', 'question_id' => '7', 'response' => 'what I typed']);
        $this->rows['other_completion'] = $this->archive($this->other, 'activity_completion', $completion($this->other));
        $this->rows['other_quiz'] = $this->archive($this->other, 'quiz_attempt',
            ['id' => '3', 'userid' => (string) $this->other->id, 'quiz' => '9']);

        // What the engine archives before a reset that clears grades: the whole grade_grades row, the grader and
        // the words they wrote about the learner included.
        $grade = fn(\stdClass $user) => [
            'id' => '9', 'itemid' => '4', 'userid' => (string) $user->id, 'finalgrade' => '8.50000',
            'usermodified' => (string) $this->grader->id, 'feedback' => 'Well done', 'information' => 'Re-marked',
            'overridden' => '0',
        ];
        $this->rows['subject_grade'] = $this->archive($this->subject, 'gradebook_grade', $grade($this->subject));
        $this->rows['other_grade'] = $this->archive($this->other, 'gradebook_grade', $grade($this->other));
    }

    /**
     * @param \stdClass $user
     * @param string $type
     * @param array $payload
     * @return int
     */
    private function archive(\stdClass $user, string $type, array $payload): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_recompletion_archive', (object) [
            'historyid' => 0, 'userid' => $user->id, 'courseid' => $this->courseid, 'itemtype' => $type,
            'state' => 'complete', 'payload' => json_encode($payload), 'timecreated' => 1000,
        ]);
    }

    /**
     * @param \stdClass $user
     * @return approved_contextlist
     */
    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, 'local_sentientia_recompletion', [\context_system::instance()->id]);
    }

    /**
     * @param string $name
     * @return array The decoded payload of a seeded row.
     */
    private function payload(string $name): array {
        global $DB;
        return json_decode($DB->get_field('local_sentientia_recompletion_archive', 'payload', ['id' => $this->rows[$name]],
            MUST_EXIST), true);
    }

    public function test_the_archive_table_and_its_person_columns_are_declared(): void {
        $collection = provider::get_metadata(new collection('local_sentientia_recompletion'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertArrayHasKey('local_sentientia_recompletion_archive', $declared);
        foreach (['userid', 'courseid', 'itemtype', 'state', 'grade', 'payload'] as $field) {
            $this->assertContains($field, $declared['local_sentientia_recompletion_archive'], $field);
        }
        $this->assertFalse(in_array(\core_privacy\local\metadata\null_provider::class, class_implements(provider::class), true));
        // Every declared string exists in the English pack and in the Hindi pack (read from the plugin's files:
        // the Hindi pack is not an installed language in the test site).
        $dir = \core_component::get_component_directory('local_sentientia_recompletion');
        foreach (['en', 'hi'] as $lang) {
            $string = [];
            include($dir . '/lang/' . $lang . '/local_sentientia_recompletion.php');
            foreach ($collection->get_collection() as $item) {
                $this->assertArrayHasKey($item->get_summary(), $string, $lang . ' ' . $item->get_summary());
                foreach ($item->get_privacy_fields() as $field) {
                    $this->assertArrayHasKey($field, $string, $lang . ' ' . $field);
                }
            }
        }
    }

    public function test_a_learner_and_an_administrator_named_only_in_a_payload_are_reachable(): void {
        $systemid = (int) \context_system::instance()->id;
        // contextlist::get_contextids() hands back what the database returned, which is strings, and assertContains
        // compares strictly: the ids are cast, as they are in privacy_anonymise_test through assertContainsEquals.
        $reachable = static fn(int $userid): array => array_map('intval',
            provider::get_contexts_for_userid($userid)->get_contextids());
        $this->assertContains($systemid, $reachable((int) $this->subject->id));
        $this->assertContains($systemid, $reachable((int) $this->admin->id),
            'an erasure must reach the overrideby inside a payload, or it is never anonymised');
        $this->assertContains($systemid, $reachable((int) $this->grader->id),
            'a teacher named only as the grader of a gradebook row is reachable too');
        $nobody = $this->getDataGenerator()->create_user();
        $this->assertSame([], $reachable((int) $nobody->id));

        $userlist = new userlist(\context_system::instance(), 'local_sentientia_recompletion');
        provider::get_users_in_context($userlist);
        foreach ([$this->subject, $this->other, $this->admin, $this->grader] as $user) {
            $this->assertContainsEquals($user->id, $userlist->get_userids());
        }
    }

    public function test_an_export_holds_the_persons_evidence_decoded(): void {
        $this->export_all_data_for_user((int) $this->subject->id, 'local_sentientia_recompletion');
        $root = get_string('pluginname', 'local_sentientia_recompletion');
        $data = writer::with_context(\context_system::instance())->get_data(
            [$root, get_string('privacy:export:evidence', 'local_sentientia_recompletion')]);
        $this->assertNotEmpty($data);
        $this->assertCount(3, $data->evidence);
        $types = array_column($data->evidence, 'item_type');
        sort($types);
        $this->assertSame(['activity_completion', 'gradebook_grade', 'questionnaire_answer'], $types);
        $answers = array_filter($data->evidence, static fn(array $r): bool => $r['item_type'] === 'questionnaire_answer');
        $this->assertSame('what I typed', reset($answers)['data']['response'], 'their own words are theirs to export');

        // Their own record, without the id of the other people it names.
        $byType = array_column($data->evidence, 'data', 'item_type');
        $this->assertArrayNotHasKey('overrideby', $byType['activity_completion'], 'the administrator\'s id is theirs');
        $this->assertArrayNotHasKey('usermodified', $byType['gradebook_grade'], 'so is the grader\'s');
        $this->assertSame('Well done', $byType['gradebook_grade']['feedback'], 'what was written to the learner is theirs');
        $this->assertSame((string) $this->subject->id, $byType['gradebook_grade']['userid']);
    }

    public function test_core_erasure_takes_the_person_and_the_typed_text_out_and_keeps_the_row(): void {
        global $DB;
        provider::delete_data_for_user($this->approved($this->subject));

        $this->assertSame(0, $DB->count_records('local_sentientia_recompletion_archive', ['userid' => $this->subject->id]));
        $this->assertSame(6, $DB->count_records('local_sentientia_recompletion_archive'), 'the evidence rows survive');

        $completion = $this->payload('subject_completion');
        $this->assertSame('0', $completion['userid']);
        $this->assertSame('0', $completion['overrideby'], 'a row nobody can be blamed for names nobody');
        $this->assertSame('100', $completion['timemodified'], 'the rest of the row is untouched');
        $this->assertSame('', $this->payload('subject_answer')['response']);
        $this->assertSame('7', $this->payload('subject_answer')['question_id']);

        $grade = $this->payload('subject_grade');
        $this->assertSame('0', $grade['userid']);
        $this->assertSame('0', $grade['usermodified'], 'the grader is another person\'s data in a row that is now nobody\'s');
        $this->assertSame('', $grade['feedback'], 'what the teacher wrote about the learner goes with the learner');
        $this->assertSame('', $grade['information']);
        $this->assertSame('8.50000', $grade['finalgrade'], 'the grade is the evidence and stays');
        $theirgrade = $this->payload('other_grade');
        $this->assertSame((string) $this->grader->id, $theirgrade['usermodified'], 'another person\'s grade is not redacted');
        $this->assertSame('Well done', $theirgrade['feedback']);

        $theirs = $this->payload('other_completion');
        $this->assertSame((string) $this->other->id, $theirs['userid'], 'another person\'s row is not redacted');
        $this->assertSame((string) $this->admin->id, $theirs['overrideby'], 'nor is the administrator of another person\'s row');
        $this->assertEquals($this->other->id, $DB->get_field('local_sentientia_recompletion_archive', 'userid',
            ['id' => $this->rows['other_quiz']]));
    }

    public function test_erasing_an_administrator_changes_only_the_rows_that_name_them(): void {
        global $DB;
        provider::delete_data_for_user($this->approved($this->admin));

        foreach (['subject_completion', 'other_completion'] as $name) {
            $payload = $this->payload($name);
            $this->assertSame('0', $payload['overrideby'], $name);
            $this->assertNotSame('0', $payload['userid'], $name . ': the learner is untouched');
        }
        $this->assertSame(6, $DB->count_records('local_sentientia_recompletion_archive'));
        $this->assertSame('what I typed', $this->payload('subject_answer')['response']);
        $this->assertSame((string) $this->grader->id, $this->payload('subject_grade')['usermodified'],
            'an administrator is not the grader');
    }

    public function test_erasing_a_grader_changes_only_the_gradebook_rows_that_name_them(): void {
        global $DB;
        provider::delete_data_for_user($this->approved($this->grader));

        foreach (['subject_grade', 'other_grade'] as $name) {
            $payload = $this->payload($name);
            $this->assertSame('0', $payload['usermodified'], $name);
            $this->assertNotSame('0', $payload['userid'], $name . ': the learner is untouched');
            $this->assertSame('Well done', $payload['feedback'], $name . ': what was written stays with the learner');
        }
        $this->assertSame((string) $this->admin->id, $this->payload('subject_completion')['overrideby']);
        $this->assertSame(6, $DB->count_records('local_sentientia_recompletion_archive'));
    }

    public function test_the_dpdp_flow_keeps_the_subjects_evidence_and_anonymises_only_the_actor(): void {
        global $DB;
        // As the subject: their rows stay keyed to the anonymised user row with every structured value (the flow
        // keeps compliance records); only the words that can name them are emptied (see the next test).
        provider::anonymise_data_for_user($this->approved($this->subject));
        $this->assertEquals($this->subject->id, $DB->get_field('local_sentientia_recompletion_archive', 'userid',
            ['id' => $this->rows['subject_completion']]));
        $this->assertSame((string) $this->subject->id, $this->payload('subject_completion')['userid']);
        $this->assertSame((string) $this->admin->id, $this->payload('subject_completion')['overrideby']);
        $this->assertSame('', $this->payload('subject_answer')['response']);
        $this->assertSame('', $this->payload('subject_grade')['feedback']);

        // As the administrator: named as the overrider in both people's rows.
        provider::anonymise_data_for_user($this->approved($this->admin));
        $this->assertSame('0', $this->payload('subject_completion')['overrideby']);
        $this->assertSame('0', $this->payload('other_completion')['overrideby']);
        $this->assertSame((string) $this->subject->id, $this->payload('subject_completion')['userid']);

        // As the grader: named in both people's gradebook rows.
        provider::anonymise_data_for_user($this->approved($this->grader));
        $this->assertSame('0', $this->payload('subject_grade')['usermodified']);
        $this->assertSame('0', $this->payload('other_grade')['usermodified']);
        $this->assertSame('Well done', $this->payload('other_grade')['feedback']);
    }

    /**
     * Owner decision recompletion.dpdp_archive_free_text: the DPDP flow keeps the record and clears the free text,
     * as the platform does for attendance notes and exemption reasons (erasure_scope_test). Core's erasure empties
     * the same keys, so both erasure paths treat the words that can name a person the same way.
     */
    public function test_the_dpdp_flow_keeps_the_record_and_clears_the_free_text(): void {
        global $DB;
        $scorm = fn(\stdClass $user, string $element, string $value): int => $this->archive($user, 'scorm_track',
            ['id' => '12', 'userid' => (string) $user->id, 'element' => $element, 'value' => $value, 'timemodified' => '200']);
        $this->rows['subject_suspend'] = $scorm($this->subject, 'cmi.suspend_data', 'page=4;note=call me Priya');
        $this->rows['subject_status'] = $scorm($this->subject, 'cmi.core.lesson_status', 'completed');
        $this->rows['other_suspend'] = $scorm($this->other, 'cmi.suspend_data', 'page=2');
        $table = 'local_sentientia_recompletion_archive';
        $before = $DB->count_records($table);

        provider::anonymise_data_for_user($this->approved($this->subject));

        $this->assertSame($before, $DB->count_records($table), 'no evidence row is deleted');

        // Free text goes: a typed answer, the grader's words about them, the text typed into a SCORM package.
        $this->assertSame('', $this->payload('subject_answer')['response']);
        $grade = $this->payload('subject_grade');
        $this->assertSame('', $grade['feedback']);
        $this->assertSame('', $grade['information']);
        $this->assertSame('', $this->payload('subject_suspend')['value']);

        // The record stays: who, what, when and the result.
        $this->assertEquals($this->subject->id, $DB->get_field($table, 'userid', ['id' => $this->rows['subject_grade']]));
        $this->assertSame((string) $this->subject->id, $grade['userid'], 'the payload still names the anonymised user row');
        $this->assertSame('8.50000', $grade['finalgrade'], 'the grade is the evidence');
        $this->assertSame('4', $grade['itemid']);
        $this->assertSame((string) $this->grader->id, $grade['usermodified'], 'the grader is scrubbed only when THEY are erased');
        $this->assertSame('7', $this->payload('subject_answer')['question_id']);
        $this->assertSame('complete', $DB->get_field($table, 'state', ['id' => $this->rows['subject_grade']]));
        $this->assertEquals(1000, $DB->get_field($table, 'timecreated', ['id' => $this->rows['subject_grade']]));
        $this->assertSame('completed', $this->payload('subject_status')['value'], 'a SCORM status is not typed text');
        $this->assertSame('cmi.suspend_data', $this->payload('subject_suspend')['element']);

        // Another person's rows are not touched.
        $this->assertSame('Well done', $this->payload('other_grade')['feedback']);
        $this->assertSame('page=2', $this->payload('other_suspend')['value']);
        $this->assertSame((string) $this->admin->id, $this->payload('other_completion')['overrideby']);

        // Running it twice changes nothing more.
        provider::anonymise_data_for_user($this->approved($this->subject));
        $this->assertSame('', $this->payload('subject_answer')['response']);
        $this->assertSame('8.50000', $this->payload('subject_grade')['finalgrade']);
    }

    public function test_erasing_everyone_redacts_every_row_and_keeps_them_all(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(6, $DB->count_records('local_sentientia_recompletion_archive'));
        $this->assertSame(0, $DB->count_records_select('local_sentientia_recompletion_archive', 'userid <> 0'));
        foreach (['subject_completion', 'other_completion'] as $name) {
            $this->assertSame('0', $this->payload($name)['userid']);
            $this->assertSame('0', $this->payload($name)['overrideby']);
        }
        $this->assertSame('', $this->payload('subject_answer')['response']);
        foreach (['subject_grade', 'other_grade'] as $name) {
            $this->assertSame('0', $this->payload($name)['usermodified'], $name);
            $this->assertSame('', $this->payload($name)['feedback'], $name);
            $this->assertSame('', $this->payload($name)['information'], $name);
        }
    }
}
