<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_integrations;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for AI recommender BizLMS-fallback behaviour.
 *
 * The recommender uses two BizLMS-only schema fields ({course}.open_skill,
 * {user}.open_departmentid) that don't exist on stock Moodle. INTEGRATIONS-AUDIT.md
 * §3.3 — when those fields are missing, two of the four strategies must
 * silently degrade to an empty result rather than throw, and the admin
 * settings page must surface a warning.
 *
 * @package    local_sentientia_integrations
 * @category   test
 */
final class ai_recommender_test extends \advanced_testcase {

    public function test_bizlms_fields_status_returns_struct(): void {
        $this->resetAfterTest();
        $status = ai_recommender::bizlms_fields_status();
        $this->assertArrayHasKey('course_open_skill',      $status);
        $this->assertArrayHasKey('user_open_departmentid', $status);
        $this->assertArrayHasKey('all_present',            $status);
        $this->assertIsBool($status['course_open_skill']);
        $this->assertIsBool($status['user_open_departmentid']);
        $this->assertIsBool($status['all_present']);
    }

    public function test_all_present_is_logical_and(): void {
        $this->resetAfterTest();
        $status = ai_recommender::bizlms_fields_status();
        // all_present must equal (skill AND dept).
        $this->assertSame(
            $status['course_open_skill'] && $status['user_open_departmentid'],
            $status['all_present'],
            'all_present must reflect logical AND of the two bizlms fields');
    }

    public function test_recommendations_disabled_returns_empty_array(): void {
        $this->resetAfterTest();
        // is_enabled() reads config — without setting ai_enable, returns false.
        $recs = ai_recommender::get_recommendations(1);
        $this->assertSame([], $recs,
            'when AI is disabled, get_recommendations must short-circuit to []');
    }

    public function test_recommendations_enabled_for_unknown_user_returns_popular(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Enable AI features.
        set_config('ai_enable', 1, 'local_sentientia_integrations');
        set_config('ai_recommendations_enable', 1, 'local_sentientia_integrations');

        // User with no enrolments → falls through to get_popular_courses().
        $u = $this->getDataGenerator()->create_user();
        $recs = ai_recommender::get_recommendations((int) $u->id, 5);
        // Stock test DB has no courses with enrolments → empty array.
        // The important invariant is no exception is thrown.
        $this->assertIsArray($recs);
    }

    /**
     * Owner decision (2026-10-07, "readers that count enrolments"): popularity is learners, not enrolment rows. A learner
     * enrolled through an imported BizLMS method and its converted manual twin is one learner; a suspended enrolment and an
     * enrolment on a disabled instance count for nothing.
     */
    public function test_popular_courses_count_a_learner_once_and_only_active_enrolments(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('ai_enable', 1, 'local_sentientia_integrations');
        set_config('ai_recommendations_enable', 1, 'local_sentientia_integrations');
        $recent = time() - 3600;

        $course = $this->getDataGenerator()->create_course();
        $twin = $this->getDataGenerator()->create_user();
        $suspended = $this->getDataGenerator()->create_user();
        $disabledonly = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($twin->id, $course->id, 'student', 'manual', $recent);
        $plan = $DB->insert_record('enrol', (object) ['enrol' => 'learningplan', 'status' => 0, 'courseid' => $course->id,
            'sortorder' => 90, 'roleid' => 0, 'timecreated' => $recent, 'timemodified' => $recent]);
        $DB->insert_record('user_enrolments', (object) ['status' => 0, 'enrolid' => $plan, 'userid' => $twin->id,
            'timestart' => $recent, 'timeend' => 0, 'modifierid' => 0, 'timecreated' => $recent, 'timemodified' => $recent]);
        $this->getDataGenerator()->enrol_user($suspended->id, $course->id, 'student', 'manual', $recent, 0, ENROL_USER_SUSPENDED);
        $off = $DB->insert_record('enrol', (object) ['enrol' => 'program', 'status' => 1, 'courseid' => $course->id,
            'sortorder' => 91, 'roleid' => 0, 'timecreated' => $recent, 'timemodified' => $recent]);
        $DB->insert_record('user_enrolments', (object) ['status' => 0, 'enrolid' => $off, 'userid' => $disabledonly->id,
            'timestart' => $recent, 'timeend' => 0, 'modifierid' => 0, 'timecreated' => $recent, 'timemodified' => $recent]);

        // A learner with no enrolment of their own is shown the popular courses.
        $viewer = $this->getDataGenerator()->create_user();
        $recs = ai_recommender::get_recommendations((int) $viewer->id, 5);

        $this->assertArrayHasKey($course->id, $recs);
        $this->assertSame(1, (int) $recs[$course->id]->enrolcount, 'one active learner, however many rows');
    }
}
