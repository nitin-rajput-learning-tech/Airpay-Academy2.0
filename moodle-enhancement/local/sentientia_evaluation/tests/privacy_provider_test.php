<?php
// This file is part of Sentientia LMS.

/**
 * Privacy provider tests for local_sentientia_evaluation.
 *
 * Written 2026-09-24 after a pre-deploy review ran delete_data_for_user() and
 * found it had never worked for two of its three tables: $userid was never
 * assigned, so it deleted "WHERE userid IS NULL" and anonymised every row
 * whose assigned_by_userid IS NULL - other people's rows. No test had ever
 * called it. These do, and assert on rows belonging to a second user too.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\tests\provider_testcase;
use local_sentientia_evaluation\privacy\provider;

/**
 * @covers \local_sentientia_evaluation\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {

    /**
     * An assignment row: $userid was asked to answer, $assignedby (or NULL for
     * the system) asked them.
     */
    private function assign(int $userid, ?int $assignedby, int $evaluationid = 1): int {
        global $DB;
        // UNIQUE(evaluationid, userid, trigger_event, source_id): a second row
        // for the same person needs another evaluation.
        return $DB->insert_record('local_sentientia_evaluation_assign', (object) [
            'evaluationid' => $evaluationid, 'userid' => $userid, 'trigger_event' => 'course_completed',
            'status' => 'pending', 'assigned_by_userid' => $assignedby,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function trigger(int $userid): int {
        global $DB;
        return $DB->insert_record('local_sentientia_evaluation_triggers', (object) [
            'evaluationid' => 1, 'userid' => $userid, 'trigger_event' => 'course_completed',
            // status is an INT column here (0 = pending), unlike assign.status.
            'fire_after' => time(), 'status' => 0, 'timecreated' => time(),
        ]);
    }

    public function test_someone_assigned_but_never_answering_is_reported(): void {
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $this->assign((int) $subject->id, null);

        $contextids = provider::get_contexts_for_userid((int) $subject->id)->get_contextids();

        $this->assertContains((int) \context_system::instance()->id, array_map('intval', $contextids),
            'An assignment with no response is still data about this person.');
    }

    public function test_an_assigner_is_reported(): void {
        $this->resetAfterTest();
        $assigner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->assign((int) $other->id, (int) $assigner->id);

        $this->assertNotEmpty(provider::get_contexts_for_userid((int) $assigner->id)->get_contextids());
    }

    public function test_erasure_removes_the_subjects_rows_and_only_theirs(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();

        // The subject's own data.
        $this->assign((int) $subject->id, null);
        $this->trigger((int) $subject->id);
        // Another person's rows: one assigned BY the subject, one assigned by
        // the system (NULL assigner - what the broken code rewrote), and a
        // trigger of their own.
        $bysubject = $this->assign((int) $other->id, (int) $subject->id);
        $bysystem = $this->assign((int) $other->id, null, 2);
        $this->trigger((int) $other->id);

        $syscontext = \context_system::instance();
        provider::delete_data_for_user(new approved_contextlist(
            $subject, 'local_sentientia_evaluation', [$syscontext->id]));

        $this->assertEquals(0, $DB->count_records('local_sentientia_evaluation_assign',
            ['userid' => $subject->id]));
        $this->assertEquals(0, $DB->count_records('local_sentientia_evaluation_triggers',
            ['userid' => $subject->id]));

        // The other person's rows all survive...
        $this->assertEquals(2, $DB->count_records('local_sentientia_evaluation_assign',
            ['userid' => $other->id]));
        $this->assertEquals(1, $DB->count_records('local_sentientia_evaluation_triggers',
            ['userid' => $other->id]));
        // ...the one the subject assigned is anonymised to 0...
        $this->assertEquals(0, $DB->get_field('local_sentientia_evaluation_assign',
            'assigned_by_userid', ['id' => $bysubject]));
        // ...and the system-assigned one keeps its NULL, untouched.
        $this->assertNull($DB->get_field('local_sentientia_evaluation_assign',
            'assigned_by_userid', ['id' => $bysystem]));
    }

    public function test_export_includes_assignments_with_no_response(): void {
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $this->assign((int) $subject->id, null);
        $this->trigger((int) $subject->id);
        $syscontext = \context_system::instance();

        $this->export_context_data_for_user((int) $subject->id, $syscontext,
            'local_sentientia_evaluation');

        $writer = \core_privacy\local\request\writer::with_context($syscontext);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data(['sentientia_evaluation_assignments']);
        $this->assertCount(1, $data->assigned_to_you);
        $this->assertCount(1, $data->triggers);
    }
}
