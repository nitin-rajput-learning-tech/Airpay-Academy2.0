<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_sentientia_learningpath\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests — proves export + erasure work against the REAL userid-keyed
 * tables (local_sentientia_learningpath_users + local_sentientia_lp_adaptive_log).
 *
 * Regression guard for the 2026-06 fix where the provider pointed at the non-existent
 * table 'local_airpay_lp_users', silently no-opping every export/erase path.
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @covers     \local_sentientia_learningpath\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {

    /** Seed one path-assignment row + one adaptive-decision row for a user. */
    private function seed(int $userid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_sentientia_learningpath_users', (object) [
            'pathid'        => 7,
            'userid'        => $userid,
            'status'        => 1,
            'timecreated'   => $now,
            'timecompleted' => 0,
        ]);
        $DB->insert_record('local_sentientia_lp_adaptive_log', (object) [
            'pathid'          => 7,
            'userid'          => $userid,
            'costcenterid'    => 1,
            'pivot_type'      => 'remediate',
            'trigger_type'    => 'quiz',
            'source_courseid' => 0,
            'target_courseid' => 0,
            'quiz_score'      => 42.5,
            'velocity_score'  => 0,
            'timecreated'     => $now,
            'timemodified'    => $now,
        ]);
    }

    public function test_metadata_declares_real_tables(): void {
        $this->resetAfterTest();
        $collection = provider::get_metadata(new collection('local_sentientia_learningpath'));
        $this->assertGreaterThanOrEqual(2, count($collection->get_collection()));
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        // assertContainsEquals (loose ==) — Moodle returns context ids as strings from some
        // code paths, so a strict assertContains(int, [...]) can spuriously fail.
        $this->assertContainsEquals(\context_system::instance()->id, $contextlist->get_contextids());
    }

    public function test_export_writes_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $systemcontext = \context_system::instance();
        $this->export_context_data_for_user((int) $user->id, $systemcontext, 'local_sentientia_learningpath');
        $this->assertTrue(writer::with_context($systemcontext)->has_any_data());
    }

    public function test_delete_for_user_targets_only_that_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $this->seed((int) $other->id);

        $approved = new approved_contextlist($user, 'local_sentientia_learningpath',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($approved);

        $this->assertEquals(0, $DB->count_records('local_sentientia_learningpath_users', ['userid' => $user->id]));
        $this->assertEquals(0, $DB->count_records('local_sentientia_lp_adaptive_log', ['userid' => $user->id]));
        $this->assertEquals(1, $DB->count_records('local_sentientia_learningpath_users', ['userid' => $other->id]));
        $this->assertEquals(1, $DB->count_records('local_sentientia_lp_adaptive_log', ['userid' => $other->id]));
    }

    // ADR-032 (2026-09-30): the columns and the table the BizLMS learningplan import adds.

    /**
     * A path, a path course, a learner row enrolled by someone else and a course-status row, with the user as the
     * actor / subject.
     *
     * @param int $actor The user who created and edited the rows and enrolled the learner.
     * @param int $learner The enrolled learner.
     * @return array{path: int, course: int, enrolment: int, status: int}
     */
    private function seed_actor_rows(int $actor, int $learner): array {
        global $DB;
        $now = time();
        $pathid = (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => 'Privacy path', 'status' => 1, 'visible' => 1, 'usercreated' => $actor, 'usermodified' => $actor,
            'timecreated' => $now, 'timemodified' => $now]);
        $courseid = (int) $this->getDataGenerator()->create_course()->id;
        return [
            'path' => $pathid,
            'course' => (int) $DB->insert_record('local_sentientia_learningpath_courses', (object) [
                'pathid' => $pathid, 'courseid' => $courseid, 'sortorder' => 0, 'mandatory' => 1,
                'usercreated' => $actor, 'usermodified' => $actor, 'timecreated' => $now, 'timemodified' => $now]),
            'enrolment' => (int) $DB->insert_record('local_sentientia_learningpath_users', (object) [
                'pathid' => $pathid, 'userid' => $learner, 'status' => 0, 'enrolledby' => $actor,
                'timecreated' => $now, 'timemodified' => $now]),
            'status' => (int) $DB->insert_record('local_sentientia_lp_course_status', (object) [
                'pathid' => $pathid, 'courseid' => $courseid, 'userid' => $learner, 'status' => 1, 'percentage' => 10,
                'usercreated' => $actor, 'usermodified' => $actor, 'timecreated' => $now, 'timemodified' => $now]),
        ];
    }

    public function test_metadata_declares_every_column_that_names_a_person(): void {
        $this->resetAfterTest();
        $collection = provider::get_metadata(new collection('local_sentientia_learningpath'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertContains('usercreated', $declared['local_sentientia_learningpath']);
        $this->assertContains('usermodified', $declared['local_sentientia_learningpath']);
        $this->assertContains('usercreated', $declared['local_sentientia_learningpath_courses']);
        $this->assertContains('usermodified', $declared['local_sentientia_learningpath_courses']);
        $this->assertContains('enrolledby', $declared['local_sentientia_learningpath_users']);
        foreach (['userid', 'usercreated', 'usermodified'] as $column) {
            $this->assertContains($column, $declared['local_sentientia_lp_course_status']);
        }
    }

    public function test_an_actor_with_no_learner_rows_still_has_data_to_export(): void {
        $this->resetAfterTest();
        $actor = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $this->seed_actor_rows((int) $actor->id, (int) $learner->id);

        $this->assertContainsEquals(\context_system::instance()->id,
            provider::get_contexts_for_userid((int) $actor->id)->get_contextids());
        $systemcontext = \context_system::instance();
        $this->export_context_data_for_user((int) $actor->id, $systemcontext, 'local_sentientia_learningpath');
        $data = writer::with_context($systemcontext)->get_data(['sentientia_learningpath', 'authored']);
        $this->assertNotEmpty($data->local_sentientia_learningpath);
        $this->assertNotEmpty($data->local_sentientia_learningpath_courses);
        // The enrolments the actor made are exported as a path and a time, never as the learner.
        $made = writer::with_context($systemcontext)->get_data(['sentientia_learningpath', 'enrolments_made']);
        $this->assertCount(1, $made->enrolments);
        $this->assertObjectNotHasProperty('userid', $made->enrolments[0]);
    }

    public function test_the_learner_export_includes_course_status_rows(): void {
        $this->resetAfterTest();
        $actor = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $this->seed_actor_rows((int) $actor->id, (int) $learner->id);
        $systemcontext = \context_system::instance();
        $this->export_context_data_for_user((int) $learner->id, $systemcontext, 'local_sentientia_learningpath');
        $data = writer::with_context($systemcontext)->get_data(['sentientia_learningpath', 'course_status']);
        $this->assertCount(1, $data->course_status);
    }

    public function test_erasing_a_learner_deletes_their_rows_and_clears_them_as_an_enroller(): void {
        global $DB;
        $this->resetAfterTest();
        $actor = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $rows = $this->seed_actor_rows((int) $actor->id, (int) $learner->id);

        provider::delete_data_for_user(new approved_contextlist($learner, 'local_sentientia_learningpath',
            [\context_system::instance()->id]));
        $this->assertFalse($DB->record_exists('local_sentientia_learningpath_users', ['id' => $rows['enrolment']]));
        $this->assertFalse($DB->record_exists('local_sentientia_lp_course_status', ['id' => $rows['status']]));
        // The path and the path course belong to the actor's work, not to the learner: they stay.
        $this->assertTrue($DB->record_exists('local_sentientia_learningpath', ['id' => $rows['path']]));

        // Erasing the ACTOR keeps the rows and removes the person from them.
        $rows = $this->seed_actor_rows((int) $actor->id, (int) $learner->id);
        provider::delete_data_for_user(new approved_contextlist($actor, 'local_sentientia_learningpath',
            [\context_system::instance()->id]));
        $this->assertTrue($DB->record_exists('local_sentientia_learningpath_users', ['id' => $rows['enrolment']]));
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_learningpath_users', 'enrolledby', ['id' => $rows['enrolment']]));
        foreach (['local_sentientia_learningpath' => $rows['path'], 'local_sentientia_learningpath_courses' => $rows['course'],
                  'local_sentientia_lp_course_status' => $rows['status']] as $table => $id) {
            $row = $DB->get_record($table, ['id' => $id], '*', MUST_EXIST);
            $this->assertSame([0, 0], [(int) $row->usercreated, (int) $row->usermodified], $table);
        }
    }

    public function test_anonymising_keeps_the_records_and_removes_the_person_from_actor_columns(): void {
        global $DB;
        $this->resetAfterTest();
        $actor = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $rows = $this->seed_actor_rows((int) $actor->id, (int) $learner->id);
        // A second enrolment the learner made for themselves.
        $self = (int) $DB->insert_record('local_sentientia_learningpath_users', (object) [
            'pathid' => $rows['path'], 'userid' => $actor->id, 'status' => 0, 'enrolledby' => $actor->id,
            'timecreated' => 100, 'timemodified' => 100]);

        provider::anonymise_data_for_user(new approved_contextlist($actor, 'local_sentientia_learningpath',
            [\context_system::instance()->id]));

        // Nothing is deleted.
        foreach (['local_sentientia_learningpath' => $rows['path'], 'local_sentientia_learningpath_courses' => $rows['course'],
                  'local_sentientia_learningpath_users' => $rows['enrolment'], 'local_sentientia_lp_course_status' => $rows['status']]
                 as $table => $id) {
            $this->assertTrue($DB->record_exists($table, ['id' => $id]), $table);
        }
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_learningpath_users', 'enrolledby', ['id' => $rows['enrolment']]),
            'the actor is removed from the enrolment they made for somebody else');
        $this->assertSame((int) $actor->id, (int) $DB->get_field('local_sentientia_learningpath_users', 'enrolledby', ['id' => $self]),
            'a self-enrolled row stays keyed to its own (anonymised) user');
        $path = $DB->get_record('local_sentientia_learningpath', ['id' => $rows['path']], '*', MUST_EXIST);
        $this->assertSame([0, 0], [(int) $path->usercreated, (int) $path->usermodified]);
    }
}
