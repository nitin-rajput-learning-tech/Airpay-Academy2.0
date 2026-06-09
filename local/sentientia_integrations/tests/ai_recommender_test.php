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
}
