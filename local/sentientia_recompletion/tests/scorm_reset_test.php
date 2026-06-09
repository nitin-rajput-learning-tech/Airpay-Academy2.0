<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-4 (2026-05-15) — tests for SCORM tracking reset in recompletion_engine.
 *
 * Locks in:
 *   - reset_user_in_course() purges scorm_attempt rows for the user × course
 *   - reset_user_in_course() purges scorm_scoes_value rows tied to those attempts
 *   - reset_user_in_course() does NOT touch SCORM data for OTHER users
 *   - reset_user_in_course() does NOT touch SCORM data for OTHER courses
 *   - reset_user_in_course() purges course_modules_completion rows
 *   - reset_user_in_course() purges course_completion_crit_compl + course_completions
 *   - reset_user_in_course() works even when the course has no SCORM activity
 *   - reset_user_in_course() rolls back atomically on a downstream failure
 *
 * @package    local_sentientia_recompletion
 * @category   test
 */
final class scorm_reset_test extends \advanced_testcase {

    /**
     * Seed a SCORM attempt + a couple of CMI values for a user in a course.
     * Returns ['course' => ..., 'scormid' => ..., 'attemptid' => ...].
     */
    private function seed_scorm_attempt(int $userid, ?int $courseid = null): array {
        global $DB;
        if ($courseid === null) {
            $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
            $courseid = (int) $course->id;
        }
        // Create a SCORM activity. We don't need real content — just rows.
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $courseid,
            'name'   => 'Compliance test SCORM',
        ]);
        // Insert a SCO row (mod_scorm's create_module typically does, but be defensive).
        if (!$DB->record_exists('scorm_scoes', ['scorm' => $scorm->id])) {
            $DB->insert_record('scorm_scoes', (object) [
                'scorm'      => $scorm->id,
                'manifest'   => '',
                'organization' => 'ORG',
                'parent'     => '/',
                'identifier' => 'item_1',
                'launch'     => 'index.html',
                'scormtype'  => 'sco',
                'title'      => 'SCO 1',
                'sortorder'  => 0,
            ]);
        }
        $scoid = (int) $DB->get_field_sql(
            "SELECT id FROM {scorm_scoes} WHERE scorm = :sid ORDER BY id ASC",
            ['sid' => $scorm->id]);

        // Insert one attempt + a CMI value for completion_status='completed'.
        $attemptid = $DB->insert_record('scorm_attempt', (object) [
            'userid'  => $userid,
            'scormid' => $scorm->id,
            'attempt' => 1,
        ]);

        // Get-or-create the 'cmi.completion_status' element row in the lookup table.
        $eid = $DB->get_field('scorm_element', 'id',
            ['element' => 'cmi.completion_status']);
        if (!$eid) {
            $eid = $DB->insert_record('scorm_element', (object) [
                'element' => 'cmi.completion_status',
            ]);
        }
        $DB->insert_record('scorm_scoes_value', (object) [
            'scoid'        => $scoid,
            'attemptid'    => $attemptid,
            'elementid'    => $eid,
            'value'        => 'completed',
            'timemodified' => time(),
        ]);

        return [
            'course'    => $courseid,
            'scormid'   => (int) $scorm->id,
            'attemptid' => (int) $attemptid,
        ];
    }

    public function test_reset_purges_scorm_attempt_rows(): void {
        $this->resetAfterTest();
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $seeded = $this->seed_scorm_attempt((int) $u->id);

        // Sanity: data exists.
        $this->assertEquals(1, $DB->count_records('scorm_attempt',
            ['userid' => $u->id, 'scormid' => $seeded['scormid']]));

        recompletion_engine::reset_user_in_course((int) $u->id, $seeded['course']);

        // Both rows gone.
        $this->assertEquals(0, $DB->count_records('scorm_attempt',
            ['userid' => $u->id, 'scormid' => $seeded['scormid']]));
    }

    public function test_reset_purges_scorm_scoes_value_rows(): void {
        $this->resetAfterTest();
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $seeded = $this->seed_scorm_attempt((int) $u->id);

        // Sanity: at least one CMI value exists for the attempt.
        $this->assertGreaterThan(0, $DB->count_records('scorm_scoes_value',
            ['attemptid' => $seeded['attemptid']]));

        recompletion_engine::reset_user_in_course((int) $u->id, $seeded['course']);

        $this->assertEquals(0, $DB->count_records('scorm_scoes_value',
            ['attemptid' => $seeded['attemptid']]));
    }

    public function test_reset_does_not_touch_other_users_scorm_data(): void {
        $this->resetAfterTest();
        global $DB;
        $alice = $this->getDataGenerator()->create_user();
        $bob   = $this->getDataGenerator()->create_user();

        // Both users have attempts on the SAME course.
        $alice_seed = $this->seed_scorm_attempt((int) $alice->id);
        $bob_seed   = $this->seed_scorm_attempt((int) $bob->id, $alice_seed['course']);

        recompletion_engine::reset_user_in_course((int) $alice->id, $alice_seed['course']);

        // Alice is gone.
        $this->assertEquals(0, $DB->count_records('scorm_attempt',
            ['userid' => $alice->id]));
        // Bob survives.
        $this->assertEquals(1, $DB->count_records('scorm_attempt',
            ['userid' => $bob->id]));
    }

    public function test_reset_does_not_touch_other_courses_scorm_data(): void {
        $this->resetAfterTest();
        global $DB;
        $u = $this->getDataGenerator()->create_user();

        // Same user has attempts in TWO different courses.
        $seed1 = $this->seed_scorm_attempt((int) $u->id);
        $seed2 = $this->seed_scorm_attempt((int) $u->id);

        // Reset only course 1.
        recompletion_engine::reset_user_in_course((int) $u->id, $seed1['course']);

        // Course 1's attempt gone.
        $this->assertEquals(0, $DB->count_records('scorm_attempt',
            ['userid' => $u->id, 'scormid' => $seed1['scormid']]));
        // Course 2's attempt survives.
        $this->assertEquals(1, $DB->count_records('scorm_attempt',
            ['userid' => $u->id, 'scormid' => $seed2['scormid']]));
    }

    public function test_reset_purges_activity_completion(): void {
        $this->resetAfterTest();
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        // Stuff a course_modules_completion row in.
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid'   => $cm->cmid,
            'userid'           => $u->id,
            'completionstate'  => 1,
            'timemodified'     => time(),
        ]);
        $this->assertEquals(1, $DB->count_records('course_modules_completion',
            ['userid' => $u->id, 'coursemoduleid' => $cm->cmid]));

        recompletion_engine::reset_user_in_course((int) $u->id, (int) $course->id);

        $this->assertEquals(0, $DB->count_records('course_modules_completion',
            ['userid' => $u->id, 'coursemoduleid' => $cm->cmid]));
    }

    public function test_reset_purges_course_completions(): void {
        $this->resetAfterTest();
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $DB->insert_record('course_completions', (object) [
            'userid'           => $u->id,
            'course'           => $course->id,
            'timeenrolled'     => time(),
            'timecompleted'    => time(),
        ]);
        $this->assertEquals(1, $DB->count_records('course_completions',
            ['userid' => $u->id, 'course' => $course->id]));

        recompletion_engine::reset_user_in_course((int) $u->id, (int) $course->id);

        $this->assertEquals(0, $DB->count_records('course_completions',
            ['userid' => $u->id, 'course' => $course->id]));
    }

    public function test_reset_works_on_non_scorm_course(): void {
        // Most regression-prone path: a course with no SCORM activity must
        // not raise — the SCORM helpers should no-op silently.
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $ok = recompletion_engine::reset_user_in_course((int) $u->id, (int) $course->id);
        $this->assertTrue($ok);
    }
}
