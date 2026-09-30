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
 * flow keeps the subject's rows as they are and anonymises only an administrator named in somebody else's row.
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
        $systemid = \context_system::instance()->id;
        $this->assertContains($systemid, provider::get_contexts_for_userid((int) $this->subject->id)->get_contextids());
        $this->assertContains($systemid, provider::get_contexts_for_userid((int) $this->admin->id)->get_contextids(),
            'an erasure must reach the overrideby inside a payload, or it is never anonymised');
        $nobody = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid((int) $nobody->id)->get_contextids());

        $userlist = new userlist(\context_system::instance(), 'local_sentientia_recompletion');
        provider::get_users_in_context($userlist);
        foreach ([$this->subject, $this->other, $this->admin] as $user) {
            $this->assertContainsEquals($user->id, $userlist->get_userids());
        }
    }

    public function test_an_export_holds_the_persons_evidence_decoded(): void {
        $this->export_all_data_for_user((int) $this->subject->id, 'local_sentientia_recompletion');
        $root = get_string('pluginname', 'local_sentientia_recompletion');
        $data = writer::with_context(\context_system::instance())->get_data(
            [$root, get_string('privacy:export:evidence', 'local_sentientia_recompletion')]);
        $this->assertNotEmpty($data);
        $this->assertCount(2, $data->evidence);
        $types = array_column($data->evidence, 'item_type');
        sort($types);
        $this->assertSame(['activity_completion', 'questionnaire_answer'], $types);
        $answers = array_filter($data->evidence, static fn(array $r): bool => $r['item_type'] === 'questionnaire_answer');
        $this->assertSame('what I typed', reset($answers)['data']['response'], 'their own words are theirs to export');
    }

    public function test_core_erasure_takes_the_person_and_the_typed_text_out_and_keeps_the_row(): void {
        global $DB;
        provider::delete_data_for_user($this->approved($this->subject));

        $this->assertSame(0, $DB->count_records('local_sentientia_recompletion_archive', ['userid' => $this->subject->id]));
        $this->assertSame(4, $DB->count_records('local_sentientia_recompletion_archive'), 'the evidence rows survive');

        $completion = $this->payload('subject_completion');
        $this->assertSame('0', $completion['userid']);
        $this->assertSame('0', $completion['overrideby'], 'a row nobody can be blamed for names nobody');
        $this->assertSame('100', $completion['timemodified'], 'the rest of the row is untouched');
        $this->assertSame('', $this->payload('subject_answer')['response']);
        $this->assertSame('7', $this->payload('subject_answer')['question_id']);

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
        $this->assertSame(4, $DB->count_records('local_sentientia_recompletion_archive'));
        $this->assertSame('what I typed', $this->payload('subject_answer')['response']);
    }

    public function test_the_dpdp_flow_keeps_the_subjects_evidence_and_anonymises_only_the_actor(): void {
        global $DB;
        // As the subject: their rows stay exactly as they are (the flow keeps compliance records keyed to the
        // anonymised user row), including their own words.
        provider::anonymise_data_for_user($this->approved($this->subject));
        $this->assertEquals($this->subject->id, $DB->get_field('local_sentientia_recompletion_archive', 'userid',
            ['id' => $this->rows['subject_completion']]));
        $this->assertSame((string) $this->subject->id, $this->payload('subject_completion')['userid']);
        $this->assertSame((string) $this->admin->id, $this->payload('subject_completion')['overrideby']);
        $this->assertSame('what I typed', $this->payload('subject_answer')['response']);

        // As the administrator: named as the overrider in both people's rows.
        provider::anonymise_data_for_user($this->approved($this->admin));
        $this->assertSame('0', $this->payload('subject_completion')['overrideby']);
        $this->assertSame('0', $this->payload('other_completion')['overrideby']);
        $this->assertSame((string) $this->subject->id, $this->payload('subject_completion')['userid']);
    }

    public function test_erasing_everyone_redacts_every_row_and_keeps_them_all(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(4, $DB->count_records('local_sentientia_recompletion_archive'));
        $this->assertSame(0, $DB->count_records_select('local_sentientia_recompletion_archive', 'userid <> 0'));
        foreach (['subject_completion', 'other_completion'] as $name) {
            $this->assertSame('0', $this->payload($name)['userid']);
            $this->assertSame('0', $this->payload($name)['overrideby']);
        }
        $this->assertSame('', $this->payload('subject_answer')['response']);
    }
}
