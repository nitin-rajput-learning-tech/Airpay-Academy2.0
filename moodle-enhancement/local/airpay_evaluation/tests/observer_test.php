<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-5 (2026-05-15) — tests for the trigger_event observer + engine.
 *
 * Locks in:
 *   - course_completion event queues a trigger for the user x course
 *   - the queue respects days_after (fire_after = event_time + delay)
 *   - the queue respects status — archived evals never queue
 *   - the queue respects costcenterid — out-of-scope users never queue
 *   - duplicate emission (same eval x user x item) does not create dup rows
 *   - process_due_triggers() fires only rows past fire_after
 *   - fired triggers create response shell rows + are marked status=1
 *   - if eval is archived before fire_after, trigger is marked SKIPPED
 *
 * @package    local_airpay_evaluation
 * @category   test
 */
final class observer_test extends \advanced_testcase {

    /**
     * Seed an active evaluation form with given trigger_event + days_after.
     */
    private function seed_evaluation(string $trigger_event, int $days_after = 0,
                                       int $costcenterid = 0,
                                       ?string $open_path = null): int {
        global $DB;
        $rec = (object) [
            'name'              => 'Test form ' . microtime(true),
            'description'       => '',
            'kirkpatrick_level' => 1,
            'trigger_event'     => $trigger_event,
            'days_after'        => $days_after,
            'costcenterid'      => $costcenterid,
            'open_path'         => $open_path,
            'status'            => evaluation_manager::STATUS_ACTIVE,
            'anonymous'         => 0,
            'timecreated'       => time(),
            'timemodified'      => time(),
        ];
        return (int) $DB->insert_record('local_airpay_evaluation', $rec);
    }

    public function test_course_completed_queues_trigger(): void {
        $this->resetAfterTest();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $eid = $this->seed_evaluation('course_completion', 0);

        evaluation_engine::on_trigger_event('course_completion',
            (int) $u->id, (int) $course->id, time());

        $row = $DB->get_record('local_airpay_evaluation_triggers', [
            'evaluationid' => $eid,
            'userid'       => $u->id,
            'itemid'       => $course->id,
        ]);
        $this->assertNotFalse($row);
        $this->assertSame(evaluation_engine::STATUS_PENDING, (int) $row->status);
    }

    public function test_days_after_delays_fire_after(): void {
        $this->resetAfterTest();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $eid = $this->seed_evaluation('course_completion', 7);  // 7-day delay

        $event_time = 1700000000;
        evaluation_engine::on_trigger_event('course_completion',
            (int) $u->id, (int) $course->id, $event_time);

        $row = $DB->get_record('local_airpay_evaluation_triggers', [
            'evaluationid' => $eid,
            'userid'       => $u->id,
            'itemid'       => $course->id,
        ]);
        $this->assertSame($event_time + (7 * 86400), (int) $row->fire_after);
    }

    public function test_archived_form_does_not_queue(): void {
        $this->resetAfterTest();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $eid = $this->seed_evaluation('course_completion');
        $DB->set_field('local_airpay_evaluation', 'status',
            evaluation_manager::STATUS_ARCHIVED, ['id' => $eid]);

        evaluation_engine::on_trigger_event('course_completion',
            (int) $u->id, (int) $course->id, time());

        $this->assertEquals(0, $DB->count_records('local_airpay_evaluation_triggers',
            ['evaluationid' => $eid]));
    }

    public function test_tenant_scoped_form_skips_out_of_scope_user(): void {
        $this->resetAfterTest();
        global $DB;

        // Form scoped to tenant 1 (Airpay).
        $eid = $this->seed_evaluation('course_completion', 0, 1, '/1');

        // User belongs to tenant 77 (Public).
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/77', ['id' => $u->id]);

        $course = $this->getDataGenerator()->create_course();
        evaluation_engine::on_trigger_event('course_completion',
            (int) $u->id, (int) $course->id, time());

        $this->assertEquals(0, $DB->count_records('local_airpay_evaluation_triggers',
            ['evaluationid' => $eid]));
    }

    public function test_tenant_scoped_form_includes_descendants(): void {
        // A user at /1/3/5 is in scope of a form scoped to /1.
        $this->resetAfterTest();
        global $DB;

        $eid = $this->seed_evaluation('course_completion', 0, 1, '/1');
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1/3/5', ['id' => $u->id]);

        $course = $this->getDataGenerator()->create_course();
        evaluation_engine::on_trigger_event('course_completion',
            (int) $u->id, (int) $course->id, time());

        $this->assertEquals(1, $DB->count_records('local_airpay_evaluation_triggers',
            ['evaluationid' => $eid]));
    }

    public function test_duplicate_emission_does_not_create_dup_rows(): void {
        $this->resetAfterTest();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $eid = $this->seed_evaluation('course_completion', 0);

        evaluation_engine::on_trigger_event('course_completion',
            (int) $u->id, (int) $course->id, time());
        evaluation_engine::on_trigger_event('course_completion',
            (int) $u->id, (int) $course->id, time());

        $this->assertEquals(1, $DB->count_records('local_airpay_evaluation_triggers', [
            'evaluationid' => $eid,
            'userid'       => $u->id,
            'itemid'       => $course->id,
        ]));
    }

    public function test_process_due_triggers_skips_future_rows(): void {
        $this->resetAfterTest();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $eid = $this->seed_evaluation('course_completion', 0);

        // Queue a trigger with fire_after in the FUTURE.
        $DB->insert_record('local_airpay_evaluation_triggers', (object) [
            'evaluationid'  => $eid,
            'userid'        => $u->id,
            'itemid'        => $course->id,
            'trigger_event' => 'course_completion',
            'fire_after'    => time() + 3600,  // 1 hour from now
            'status'        => evaluation_engine::STATUS_PENDING,
            'timecreated'   => time(),
        ]);

        $result = evaluation_engine::process_due_triggers();
        $this->assertSame(0, $result['fired']);
    }

    public function test_process_due_triggers_fires_past_rows(): void {
        $this->resetAfterTest();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $eid = $this->seed_evaluation('course_completion', 0);

        $DB->insert_record('local_airpay_evaluation_triggers', (object) [
            'evaluationid'  => $eid,
            'userid'        => $u->id,
            'itemid'        => $course->id,
            'trigger_event' => 'course_completion',
            'fire_after'    => time() - 60,
            'status'        => evaluation_engine::STATUS_PENDING,
            'timecreated'   => time(),
        ]);

        // Redirect messages so message_send doesn't try to actually deliver.
        $sink = $this->redirectMessages();

        $result = evaluation_engine::process_due_triggers();

        $this->assertSame(1, $result['fired']);

        // Response shell created.
        $this->assertEquals(1, $DB->count_records('local_airpay_evaluation_responses', [
            'evaluationid' => $eid,
            'userid'       => $u->id,
            'courseid'     => $course->id,
        ]));

        // Trigger row marked FIRED.
        $row = $DB->get_record('local_airpay_evaluation_triggers', [
            'evaluationid' => $eid,
            'userid'       => $u->id,
        ]);
        $this->assertSame(evaluation_engine::STATUS_FIRED, (int) $row->status);

        // Notification was attempted.
        $msgs = $sink->get_messages();
        $this->assertCount(1, $msgs);

        $sink->close();
    }

    public function test_process_due_triggers_skips_archived_form(): void {
        // Trigger was queued while form was active; form was then archived
        // before fire_after. Engine should mark trigger SKIPPED rather than
        // fire (admin intent: cancel pending fires when archiving).
        $this->resetAfterTest();
        global $DB;

        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $eid = $this->seed_evaluation('course_completion', 0);

        $DB->insert_record('local_airpay_evaluation_triggers', (object) [
            'evaluationid'  => $eid,
            'userid'        => $u->id,
            'itemid'        => $course->id,
            'trigger_event' => 'course_completion',
            'fire_after'    => time() - 60,
            'status'        => evaluation_engine::STATUS_PENDING,
            'timecreated'   => time(),
        ]);

        $DB->set_field('local_airpay_evaluation', 'status',
            evaluation_manager::STATUS_ARCHIVED, ['id' => $eid]);

        $result = evaluation_engine::process_due_triggers();
        $this->assertSame(0, $result['fired']);
        $this->assertSame(1, $result['skipped']);

        $row = $DB->get_record('local_airpay_evaluation_triggers', [
            'evaluationid' => $eid,
            'userid'       => $u->id,
        ]);
        $this->assertSame(evaluation_engine::STATUS_SKIPPED, (int) $row->status);
    }
}
