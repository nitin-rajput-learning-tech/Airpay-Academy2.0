<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use local_sentientia_programs\privacy\provider;

/**
 * The privacy provider after the BizLMS import (ADR-032): the three history tables and the actor column.
 *
 * The import added local_sentientia_programs_lvlcomp (the learner's stored level completions),
 * _trainers and _trainerfb, and the enrolledby column on the enrolments. All of them name a person, so all of
 * them are declared, exported and handled by both erasure paths: core's (the person's rows go) and the Sentientia
 * DPDP flow (the certification record stays, the person as author of somebody else's record goes).
 *
 * @package    local_sentientia_programs
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_programs\privacy\provider
 *
 * @group local_sentientia_programs
 * @group bizlms_import
 */
final class privacy_history_test extends \advanced_testcase {

    /** A fixed time so kept rows can be compared exactly. */
    private const T0 = 1767225600;

    /** @var int[] Programs and levels of the seed. */
    private array $ids = [];

    /**
     * @param \stdClass $user
     * @return approved_contextlist
     */
    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, 'local_sentientia_programs', [\context_system::instance()->id]);
    }

    /**
     * One program, one level, and a world of rows that name people:
     *  - learner L: enrolment (enrolled by ACTOR), a stored level completion
     *  - trainer T: assigned by ACTOR; feedback about T given by learner L and by another learner M
     *  - another learner M with an enrolment and a completion, who must never be touched
     *
     * @return array<string, \stdClass> learner, trainer, actor, other
     */
    private function seed(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $people = [
            'learner' => $gen->create_user(), 'trainer' => $gen->create_user(), 'actor' => $gen->create_user(),
            'other' => $gen->create_user(),
        ];
        $programid = (int) $DB->insert_record('local_sentientia_programs', (object) [
            'name' => 'AML certification', 'costcenterid' => 1, 'status' => 1,
            'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
        $levelid = (int) $DB->insert_record('local_sentientia_programs_levels', (object) [
            'programid' => $programid, 'name' => 'Level 1', 'sortorder' => 0, 'timecreated' => self::T0,
        ]);
        $this->ids = ['program' => $programid, 'level' => $levelid];

        foreach (['learner', 'other'] as $key) {
            $DB->insert_record('local_sentientia_programs_users', (object) [
                'programid' => $programid, 'userid' => $people[$key]->id, 'currentlevelid' => $levelid, 'status' => 2,
                'timecreated' => self::T0, 'timecompleted' => self::T0 + 10, 'enrolledby' => $people['actor']->id,
                'timemodified' => self::T0 + 11,
            ]);
            $DB->insert_record('local_sentientia_programs_lvlcomp', (object) [
                'programid' => $programid, 'levelid' => $levelid, 'userid' => $people[$key]->id, 'status' => 1,
                'timecompleted' => self::T0 + 10, 'completedcourseids' => '5,6', 'source' => 'bizlms',
                'timecreated' => self::T0, 'timemodified' => self::T0,
            ]);
        }
        $trainerrow = (int) $DB->insert_record('local_sentientia_programs_trainers', (object) [
            'programid' => $programid, 'userid' => $people['trainer']->id, 'feedbackid' => 5, 'feedback_score' => '4.5',
            'assignedby' => $people['actor']->id, 'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
        foreach (['learner', 'other'] as $key) {
            $DB->insert_record('local_sentientia_programs_trainerfb', (object) [
                'programtrainerid' => $trainerrow, 'programid' => $programid, 'trainerid' => $people['trainer']->id,
                'userid' => $people[$key]->id, 'score' => '5', 'timecreated' => self::T0, 'timemodified' => self::T0,
            ]);
        }
        return $people;
    }

    public function test_every_table_and_column_that_names_a_person_is_declared(): void {
        $collection = provider::get_metadata(new collection('local_sentientia_programs'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertContains('userid', $declared['local_sentientia_programs_users']);
        $this->assertContains('enrolledby', $declared['local_sentientia_programs_users']);
        $this->assertContains('userid', $declared['local_sentientia_programs_lvlcomp']);
        $this->assertContains('userid', $declared['local_sentientia_programs_trainers']);
        $this->assertContains('assignedby', $declared['local_sentientia_programs_trainers']);
        $this->assertContains('trainerid', $declared['local_sentientia_programs_trainerfb']);
        $this->assertContains('userid', $declared['local_sentientia_programs_trainerfb']);
        foreach ($collection->get_collection() as $item) {
            // Every declared field has a string (the Hindi pack is held to parity by tools/check-lang-parity.php).
            foreach ($item->get_privacy_fields() as $stringkey) {
                $this->assertTrue(get_string_manager()->string_exists($stringkey, 'local_sentientia_programs'), $stringkey);
            }
        }
        $this->assertFalse(in_array(\core_privacy\local\metadata\null_provider::class,
            class_implements(provider::class) ?: [], true), 'this plugin holds personal data');
    }

    public function test_a_person_is_found_wherever_the_tables_name_them(): void {
        $this->resetAfterTest();
        $people = $this->seed();
        $nobody = $this->getDataGenerator()->create_user();

        foreach (['learner', 'trainer', 'actor', 'other'] as $key) {
            $contexts = provider::get_contexts_for_userid((int) $people[$key]->id)->get_contextids();
            $this->assertSame([(int) \context_system::instance()->id], array_map('intval', $contexts), $key);
        }
        $this->assertCount(0, provider::get_contexts_for_userid((int) $nobody->id)->get_contextids());

        $userlist = new userlist(\context_system::instance(), 'local_sentientia_programs');
        provider::get_users_in_context($userlist);
        $found = array_map('intval', $userlist->get_userids());
        foreach (['learner', 'trainer', 'actor', 'other'] as $key) {
            $this->assertContains((int) $people[$key]->id, $found, $key);
        }
        $this->assertNotContains((int) $nobody->id, $found);
    }

    public function test_the_export_covers_the_new_tables_and_leaves_out_the_other_partys_id(): void {
        $this->resetAfterTest();
        $people = $this->seed();
        provider::export_user_data($this->approved($people['learner']));
        $data = \core_privacy\local\request\writer::with_context(\context_system::instance())->get_data(['sentientia_programs']);

        $this->assertCount(1, $data->enrolments);
        $this->assertCount(1, $data->level_completions);
        $this->assertSame('5,6', $data->level_completions[0]->completedcourseids);
        $this->assertCount(1, $data->trainer_feedback_given, 'the feedback the learner gave');
        $this->assertCount(0, $data->trainer_feedback_received);
        $this->assertFalse(property_exists($data->trainer_feedback_given[0], 'trainerid'), "the trainer's id is not exported");
        $this->assertCount(0, $data->enrolments_made);

        \core_privacy\local\request\writer::reset();
        provider::export_user_data($this->approved($people['trainer']));
        $data = \core_privacy\local\request\writer::with_context(\context_system::instance())->get_data(['sentientia_programs']);
        $this->assertCount(1, $data->trainer_assignments);
        $this->assertCount(2, $data->trainer_feedback_received);
        $this->assertFalse(property_exists($data->trainer_feedback_received[0], 'userid'), "the giver's id is not exported");

        \core_privacy\local\request\writer::reset();
        provider::export_user_data($this->approved($people['actor']));
        $data = \core_privacy\local\request\writer::with_context(\context_system::instance())->get_data(['sentientia_programs']);
        $this->assertCount(0, $data->enrolments);
        $this->assertCount(2, $data->enrolments_made, 'what the person did, not whom it was done to');
        $this->assertCount(1, $data->trainer_assignments_made);
    }

    public function test_core_erasure_removes_the_persons_rows_and_clears_their_name_from_other_peoples_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $people = $this->seed();

        // The learner: their own rows go, the feedback they gave stays for the trainer with the giver emptied.
        provider::delete_data_for_user($this->approved($people['learner']));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_users', ['userid' => $people['learner']->id]));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_lvlcomp', ['userid' => $people['learner']->id]));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_users', ['userid' => $people['other']->id]));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_lvlcomp', ['userid' => $people['other']->id]));
        $this->assertSame(2, $DB->count_records('local_sentientia_programs_trainerfb'), 'the feedback stays');
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_trainerfb', ['userid' => $people['learner']->id]));
        $this->assertSame(1, $DB->count_records_select('local_sentientia_programs_trainerfb', 'userid IS NULL'));

        // The actor: nothing of theirs is a record of theirs, only a reference.
        provider::delete_data_for_user($this->approved($people['actor']));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_users', ['enrolledby' => $people['actor']->id]));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_trainers', ['assignedby' => $people['actor']->id]));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_users'), 'the other learner is still enrolled');
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_trainers'));

        // The trainer: the assignment and the feedback about them go together.
        provider::delete_data_for_user($this->approved($people['trainer']));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_trainers'));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_trainerfb'));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_lvlcomp'), "the other learner's completion");
    }

    public function test_erasing_a_list_of_users_and_a_whole_context(): void {
        global $DB;
        $this->resetAfterTest();
        $people = $this->seed();
        $userlist = new approved_userlist(\context_system::instance(), 'local_sentientia_programs',
            [(int) $people['learner']->id, (int) $people['other']->id]);
        provider::delete_data_for_users($userlist);
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_users'));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_lvlcomp'));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_trainers'), 'the trainer was not on the list');
        $this->assertSame(0, $DB->count_records_select('local_sentientia_programs_trainerfb', 'userid IS NOT NULL'));

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_trainers'));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_trainerfb'));
    }

    public function test_the_dpdp_flow_keeps_the_certification_record_and_clears_only_references(): void {
        global $DB;
        $this->resetAfterTest();
        $people = $this->seed();

        // The learner is anonymised in place: enrolment and level completion stay, keyed to the same user row.
        provider::anonymise_data_for_user($this->approved($people['learner']));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_users', ['userid' => $people['learner']->id]));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_lvlcomp', ['userid' => $people['learner']->id]));
        // The feedback they gave stays as the trainer's record, without the giver.
        $this->assertSame(2, $DB->count_records('local_sentientia_programs_trainerfb'));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_trainerfb', ['userid' => $people['learner']->id]));

        // The person who enrolled and assigned: the records stay, the reference goes.
        provider::anonymise_data_for_user($this->approved($people['actor']));
        $this->assertSame(2, $DB->count_records('local_sentientia_programs_users'));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_users', ['enrolledby' => $people['actor']->id]));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_trainers', ['assignedby' => $people['actor']->id]));

        // The trainer: their assignment and the feedback about them are their record, and stay.
        provider::anonymise_data_for_user($this->approved($people['trainer']));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_trainers'));
        $this->assertSame(2, $DB->count_records('local_sentientia_programs_trainerfb', ['trainerid' => $people['trainer']->id]));
    }
}
