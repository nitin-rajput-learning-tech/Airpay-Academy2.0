<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;
use local_sentientia_evaluation\privacy\provider;

/**
 * The subject of a supervisor evaluation (ADR-032, mapping doc section 18, code fix 1).
 *
 * A supervisor form is answered by the supervisor ABOUT a team member. The response keeps the supervisor in userid
 * and, since the BizLMS import, the team member in subject_userid. The team member is a data subject of that row:
 * a subject-access request must return what was said about them, and an erasure request must stop the row naming
 * them. The supervisor's row is the supervisor's record, so it stays; erasing the SUPERVISOR still removes it.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_evaluation\privacy\provider
 */
final class privacy_subject_test extends provider_testcase {

    /**
     * @param int $userid The responder.
     * @param int|null $subject The person the response is about.
     * @return int The response id.
     */
    private function response(int $userid, ?int $subject): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid' => 1, 'userid' => $userid, 'subject_userid' => $subject,
            'response_data' => '{"1":"said"}', 'timesubmitted' => 1700000000,
        ]);
    }

    public function test_the_subject_alone_is_a_data_subject(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $supervisor = $gen->create_user();
        $subject = $gen->create_user();
        $this->response((int) $supervisor->id, (int) $subject->id);

        $this->assertContains((int) \context_system::instance()->id,
            array_map('intval', provider::get_contexts_for_userid((int) $subject->id)->get_contextids()),
            'a person only ever spoken about still has data here');

        $userlist = new userlist(\context_system::instance(), 'local_sentientia_evaluation');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $subject->id, array_map('intval', $userlist->get_userids()));
        $this->assertContains((int) $supervisor->id, array_map('intval', $userlist->get_userids()));
    }

    public function test_export_returns_what_was_said_about_the_subject_and_not_who_said_it(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $supervisor = $gen->create_user();
        $subject = $gen->create_user();
        $this->response((int) $supervisor->id, (int) $subject->id);
        $syscontext = \context_system::instance();

        $this->export_context_data_for_user((int) $subject->id, $syscontext, 'local_sentientia_evaluation');

        $writer = \core_privacy\local\request\writer::with_context($syscontext);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data(['sentientia_evaluation_about_you']);
        $this->assertCount(1, $data->responses_about_you);
        $this->assertSame('{"1":"said"}', $data->responses_about_you[0]->answers);
        $this->assertStringNotContainsString($supervisor->username, json_encode($data),
            'the responder is not named to the person the response is about');
        $this->assertEmpty($writer->get_data(['sentientia_evaluation_responses']),
            'the subject did not submit anything');
    }

    public function test_erasing_the_subject_keeps_the_supervisors_row_but_unnames_them(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $supervisor = $gen->create_user();
        $subject = $gen->create_user();
        $bystander = $gen->create_user();
        $about = $this->response((int) $supervisor->id, (int) $subject->id);
        $other = $this->response((int) $supervisor->id, (int) $bystander->id);
        $own = $this->response((int) $subject->id, null);

        provider::delete_data_for_user(new approved_contextlist(
            $subject, 'local_sentientia_evaluation', [\context_system::instance()->id]));

        $this->assertTrue($DB->record_exists('local_sentientia_evaluation_responses', ['id' => $about]),
            'the supervisor\'s response is the supervisor\'s record and stays');
        $this->assertNull($DB->get_field('local_sentientia_evaluation_responses', 'subject_userid', ['id' => $about]),
            'but it no longer says who it was about');
        $this->assertEquals($supervisor->id, $DB->get_field('local_sentientia_evaluation_responses', 'userid', ['id' => $about]));
        $this->assertEquals($bystander->id,
            $DB->get_field('local_sentientia_evaluation_responses', 'subject_userid', ['id' => $other]),
            'somebody else\'s subject is untouched');
        $this->assertFalse($DB->record_exists('local_sentientia_evaluation_responses', ['id' => $own]),
            'what the subject submitted themselves is erased, as before');
    }

    public function test_erasing_the_supervisor_removes_the_response_even_though_it_names_a_subject(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $supervisor = $gen->create_user();
        $subject = $gen->create_user();
        $about = $this->response((int) $supervisor->id, (int) $subject->id);

        provider::delete_data_for_user(new approved_contextlist(
            $supervisor, 'local_sentientia_evaluation', [\context_system::instance()->id]));

        $this->assertFalse($DB->record_exists('local_sentientia_evaluation_responses', ['id' => $about]));
    }

    public function test_bulk_erasure_by_userlist_unnames_the_subjects(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $supervisor = $gen->create_user();
        $subject = $gen->create_user();
        $about = $this->response((int) $supervisor->id, (int) $subject->id);

        provider::delete_data_for_users(new approved_userlist(
            \context_system::instance(), 'local_sentientia_evaluation', [$subject->id]));

        $this->assertTrue($DB->record_exists('local_sentientia_evaluation_responses', ['id' => $about]));
        $this->assertNull($DB->get_field('local_sentientia_evaluation_responses', 'subject_userid', ['id' => $about]));
    }

    public function test_the_metadata_declares_the_column(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_sentientia_evaluation'));
        $fields = [];
        foreach ($collection->get_collection() as $item) {
            $fields[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertContains('subject_userid', $fields['local_sentientia_evaluation_responses']);
        $this->assertTrue(get_string_manager()->string_exists('privacy:metadata:responses:subject_userid',
            'local_sentientia_evaluation'));
    }
}
