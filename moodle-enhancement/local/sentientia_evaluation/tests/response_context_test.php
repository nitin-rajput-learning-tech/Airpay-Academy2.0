<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * E1 + E2 (2026-11-19, post-demo plan 2.4, surveys scope): the course, program and classroom a response names.
 *
 * E1: the ids came from the browser and were stored as sent, so a learner could file feedback against any course,
 * program or classroom. E2: invitations carried the evaluation id only, so the responses they led to had no context.
 *
 * Locks in:
 *   - no context, zeros, junk values and unknown keys are stored as null and never refused;
 *   - a course is accepted when the user is enrolled (active or not) or completed it; refused when neither, and for
 *     the site course or a course that does not exist;
 *   - a classroom is accepted from its roster, a program from its members; any other is refused;
 *   - an invitation to THIS evaluation that names the item (assignment row, pending or fired trigger row) is enough;
 *     a manual assignment, a skipped trigger or an invitation to another evaluation is not;
 *   - a refused submission stores nothing and leaves the assignment open;
 *   - an anonymous form keeps the context, never the user;
 *   - the submit web service refuses too;
 *   - invitation_context() / respond_url(), and the trigger-queue invitation links to respond.php with the item, and
 *     a submission made from that link is accepted.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class response_context_test extends \advanced_testcase {

    /**
     * An active evaluation open to every tenant (costcenterid 0), with one required yes/no question.
     *
     * @param int    $anonymous
     * @param string $trigger a TRIGGER_EVENTS key
     * @return int[] [evaluation id, question id]
     */
    private function seed_form(int $anonymous = 0, string $trigger = 'manual'): array {
        $eid = (int) evaluation_manager::create((object) [
            'name'              => 'E1 context form',
            'kirkpatrick_level' => 1,
            'trigger_event'     => $trigger,
            'status'            => evaluation_manager::STATUS_ACTIVE,
            'anonymous'         => $anonymous,
        ]);
        $qid = (int) evaluation_manager::create_question((object) [
            'evaluationid' => $eid,
            'questiontype' => 'yesno',
            'questiontext' => 'Was it useful?',
            'required'     => 1,
        ]);
        return [$eid, $qid];
    }

    /**
     * Assert that $fn is refused with error_responsecontext.
     *
     * @param callable $fn
     * @param string   $why
     */
    private function assert_refused(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_responsecontext', $e->errorcode, $why);
        }
    }

    /**
     * Skip when an optional plugin's table is missing (the evaluation plugin does not depend on it).
     *
     * @param string $table
     */
    private function require_table(string $table): void {
        global $DB;
        if (!$DB->get_manager()->table_exists($table)) {
            $this->markTestSkipped("{$table} is not installed");
        }
    }

    public function test_no_context_is_stored_as_null(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_form(0, 'manual');
        $u = $this->getDataGenerator()->create_user();
        $v = $this->getDataGenerator()->create_user();

        // What response_actions.js sends when the page has no context.
        $rid = evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes'],
            ['courseid' => 0, 'programid' => 0, 'classroomid' => 0]);
        $row = $DB->get_record('local_sentientia_evaluation_responses', ['id' => $rid]);
        $this->assertNull($row->courseid);
        $this->assertNull($row->programid);
        $this->assertNull($row->classroomid);

        // No context at all.
        $rid = evaluation_manager::submit_response($eid, (int) $v->id, [$qid => 'no']);
        $this->assertNull($DB->get_field('local_sentientia_evaluation_responses', 'courseid', ['id' => $rid]));
    }

    public function test_unknown_keys_and_junk_values_are_dropped(): void {
        $this->resetAfterTest();
        [$eid] = $this->seed_form();
        $u = $this->getDataGenerator()->create_user();

        $this->assertSame(['courseid' => null, 'programid' => null, 'classroomid' => null],
            evaluation_manager::clean_response_context($eid, (int) $u->id,
                ['foo' => 5, 'courseid' => 'abc', 'programid' => -3, 'classroomid' => [7]]));
    }

    public function test_course_needs_an_enrolment_or_a_completion(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_form();
        $course = $this->getDataGenerator()->create_course();
        $outsider = $this->getDataGenerator()->create_user();
        $enrolled = $this->getDataGenerator()->create_user();
        $finished = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $enrolled->id, (int) $course->id);
        // Completed, and no longer enrolled.
        $DB->insert_record('course_completions', (object) [
            'userid' => $finished->id, 'course' => $course->id, 'timeenrolled' => 0,
            'timestarted' => 0, 'timecompleted' => time() - DAYSECS, 'reaggregate' => 0,
        ]);
        evaluation_manager::ensure_assignment($eid, (int) $outsider->id);

        $this->assert_refused(
            fn() => evaluation_manager::submit_response($eid, (int) $outsider->id, [$qid => 'yes'],
                ['courseid' => (int) $course->id]),
            'Feedback against a course the user is not enrolled in, never completed and was not invited for.');
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_responses', ['evaluationid' => $eid]),
            'the refused answer was not stored');
        $this->assertSame('assigned', $DB->get_field('local_sentientia_evaluation_assign', 'status',
            ['evaluationid' => $eid, 'userid' => $outsider->id]), 'the refused answer leaves the assignment open');

        $rid = evaluation_manager::submit_response($eid, (int) $enrolled->id, [$qid => 'yes'],
            ['courseid' => (int) $course->id]);
        $this->assertEquals($course->id, $DB->get_field('local_sentientia_evaluation_responses', 'courseid',
            ['id' => $rid]));

        $rid = evaluation_manager::submit_response($eid, (int) $finished->id, [$qid => 'yes'],
            ['courseid' => (int) $course->id]);
        $this->assertEquals($course->id, $DB->get_field('local_sentientia_evaluation_responses', 'courseid',
            ['id' => $rid]));
    }

    public function test_site_course_and_missing_course_are_refused(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid] = $this->seed_form();
        $u = $this->getDataGenerator()->create_user();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course}') + 1000;

        $this->assert_refused(
            fn() => evaluation_manager::clean_response_context($eid, (int) $u->id, ['courseid' => (int) SITEID]),
            'The site course is never a feedback context.');
        $this->assert_refused(
            fn() => evaluation_manager::clean_response_context($eid, (int) $u->id, ['courseid' => $missing]),
            'A course that does not exist is refused, not stored.');
    }

    public function test_classroom_follows_the_roster(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_table('local_sentientia_classroom_users');
        [$eid, $qid] = $this->seed_form(0, 'classroom_end');
        $u = $this->getDataGenerator()->create_user();
        $attended = 9001;
        $other = 9002;
        $DB->insert_record('local_sentientia_classroom_users', (object) [
            'classroomid' => $attended, 'userid' => $u->id, 'completion_status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->assert_refused(
            fn() => evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes'],
                ['classroomid' => $other]),
            'Feedback against a classroom the user is not on the roster of.');

        $rid = evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes'], ['classroomid' => $attended]);
        $this->assertEquals($attended, $DB->get_field('local_sentientia_evaluation_responses', 'classroomid',
            ['id' => $rid]));
    }

    public function test_program_follows_its_members(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_table('local_sentientia_programs_users');
        [$eid] = $this->seed_form(0, 'program_completion');
        $u = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_sentientia_programs_users', (object) [
            'programid' => 8001, 'userid' => $u->id, 'status' => 0, 'enrolledby' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->assertSame(['courseid' => null, 'programid' => 8001, 'classroomid' => null],
            evaluation_manager::clean_response_context($eid, (int) $u->id, ['programid' => 8001]));
        $this->assert_refused(
            fn() => evaluation_manager::clean_response_context($eid, (int) $u->id, ['programid' => 8002]),
            'Feedback against a program the user is not a member of.');
    }

    public function test_an_invitation_that_names_the_item_is_enough(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_form(0, 'classroom_end');
        [$othereid] = $this->seed_form(0, 'classroom_end');
        $invited = $this->getDataGenerator()->create_user();
        $manual = $this->getDataGenerator()->create_user();
        $elsewhere = $this->getDataGenerator()->create_user();
        $classroomid = 9101;

        // Not on any roster: the invitation alone ties them to the classroom.
        evaluation_manager::ensure_assignment($eid, (int) $invited->id, 'classroom_end', $classroomid);
        // A manual (or audience) assignment names no classroom.
        evaluation_manager::ensure_assignment($eid, (int) $manual->id);
        // Invited for this classroom, but to ANOTHER evaluation.
        evaluation_manager::ensure_assignment($othereid, (int) $elsewhere->id, 'classroom_end', $classroomid);

        $this->assert_refused(
            fn() => evaluation_manager::clean_response_context($eid, (int) $invited->id, ['classroomid' => 9102]),
            'An invitation for one classroom does not open another.');
        $this->assert_refused(
            fn() => evaluation_manager::clean_response_context($eid, (int) $manual->id,
                ['classroomid' => $classroomid]),
            'A manual assignment names no classroom.');
        $this->assert_refused(
            fn() => evaluation_manager::clean_response_context($eid, (int) $elsewhere->id,
                ['classroomid' => $classroomid]),
            'An invitation to another evaluation does not count.');
        $this->assert_refused(
            fn() => evaluation_manager::clean_response_context($eid, (int) $invited->id,
                ['courseid' => $classroomid]),
            'A classroom invitation does not open a course with the same id.');

        $rid = evaluation_manager::submit_response($eid, (int) $invited->id, [$qid => 'yes'],
            ['classroomid' => $classroomid]);
        $this->assertEquals($classroomid, $DB->get_field('local_sentientia_evaluation_responses', 'classroomid',
            ['id' => $rid]));
    }

    public function test_pending_or_fired_trigger_names_the_item_skipped_does_not(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid] = $this->seed_form(0, 'course_completion');
        $pending = $this->getDataGenerator()->create_user();
        $fired = $this->getDataGenerator()->create_user();
        $skipped = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        foreach ([[$pending, evaluation_engine::STATUS_PENDING], [$fired, evaluation_engine::STATUS_FIRED],
                [$skipped, evaluation_engine::STATUS_SKIPPED]] as [$user, $status]) {
            $DB->insert_record('local_sentientia_evaluation_triggers', (object) [
                'evaluationid' => $eid, 'userid' => $user->id, 'itemid' => $course->id,
                'trigger_event' => 'course_completion', 'fire_after' => time(), 'status' => $status,
                'timecreated' => time(),
            ]);
        }

        foreach ([$pending, $fired] as $user) {
            $this->assertSame((int) $course->id, evaluation_manager::clean_response_context($eid, (int) $user->id,
                ['courseid' => (int) $course->id])['courseid']);
        }
        $this->assert_refused(
            fn() => evaluation_manager::clean_response_context($eid, (int) $skipped->id,
                ['courseid' => (int) $course->id]),
            'A skipped trigger sent no invitation.');
    }

    public function test_anonymous_form_keeps_the_context_not_the_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_form(1, 'classroom_end');
        $u = $this->getDataGenerator()->create_user();
        evaluation_manager::ensure_assignment($eid, (int) $u->id, 'classroom_end', 9201);

        $this->assert_refused(
            fn() => evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes'], ['classroomid' => 9202]),
            'An anonymous form checks the context against the real respondent too.');

        $rid = evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes'], ['classroomid' => 9201]);
        $row = $DB->get_record('local_sentientia_evaluation_responses', ['id' => $rid]);
        $this->assertSame(0, (int) $row->userid);
        $this->assertSame(9201, (int) $row->classroomid);
    }

    public function test_web_service_refuses_a_foreign_context(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_form(0, 'classroom_end');
        $u = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sentientia_evaluation:respond', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        evaluation_manager::ensure_assignment($eid, (int) $u->id, 'classroom_end', 9301);
        $this->setUser($u);

        $this->assert_refused(
            fn() => external\submit_response::execute($eid, json_encode([$qid => 'yes']),
                json_encode(['courseid' => 0, 'programid' => 0, 'classroomid' => 9302])),
            'The web service must refuse a classroom the user was not invited for.');
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_responses', ['evaluationid' => $eid]));

        $result = external\submit_response::execute($eid, json_encode([$qid => 'yes']),
            json_encode(['courseid' => 0, 'programid' => 0, 'classroomid' => 9301]));
        $this->assertTrue($result['success']);
        $this->assertSame(9301, (int) $DB->get_field('local_sentientia_evaluation_responses', 'classroomid',
            ['id' => $result['responseid']]));
    }

    public function test_invitation_context_and_respond_url(): void {
        $this->assertSame(['courseid' => 7], evaluation_manager::invitation_context('course_completion', 7));
        $this->assertSame(['programid' => 8], evaluation_manager::invitation_context('program_completion', 8));
        $this->assertSame(['classroomid' => 9], evaluation_manager::invitation_context('classroom_end', 9));
        $this->assertSame([], evaluation_manager::invitation_context('manual', 9));
        $this->assertSame([], evaluation_manager::invitation_context('classroom_end', 0));
        $this->assertSame([], evaluation_manager::invitation_context('nonsense', 9));

        $url = evaluation_manager::respond_url(5, ['courseid' => 7, 'programid' => 0, 'classroomid' => null,
            'foo' => 3]);
        $this->assertTrue($url->compare(new \moodle_url('/local/sentientia_evaluation/respond.php'), URL_MATCH_BASE));
        $this->assertSame(['id' => '5', 'courseid' => '7'], $url->params());
        $this->assertSame(['id' => '5'], evaluation_manager::respond_url(5)->params());
    }

    public function test_trigger_invitation_carries_the_classroom_and_its_link_is_accepted(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_form(0, 'classroom_end');
        $u = $this->getDataGenerator()->create_user();
        $classroomid = 9401;
        $DB->insert_record('local_sentientia_evaluation_triggers', (object) [
            'evaluationid' => $eid, 'userid' => $u->id, 'itemid' => $classroomid,
            'trigger_event' => 'classroom_end', 'fire_after' => time() - 60,
            'status' => evaluation_engine::STATUS_PENDING, 'timecreated' => time(),
        ]);

        $sink = $this->redirectMessages();
        $this->assertSame(1, evaluation_engine::process_due_triggers()['fired']);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $link = new \moodle_url($messages[0]->contexturl);
        $this->assertTrue($link->compare(new \moodle_url('/local/sentientia_evaluation/respond.php'), URL_MATCH_BASE));
        $this->assertSame((string) $eid, $link->get_param('id'));
        $this->assertSame((string) $classroomid, $link->get_param('classroomid'));
        $this->assertNull($link->get_param('courseid'));
        $this->assertStringContainsString('classroomid=' . $classroomid, $messages[0]->fullmessage);

        // What respond.php and the submit carry from that link is accepted, and the response is tied to it.
        $context = ['courseid' => (int) $link->get_param('courseid'), 'programid' => (int) $link->get_param('programid'),
            'classroomid' => (int) $link->get_param('classroomid')];
        $rid = evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes'], $context);
        $this->assertSame($classroomid, (int) $DB->get_field('local_sentientia_evaluation_responses', 'classroomid',
            ['id' => $rid]));
    }
}
