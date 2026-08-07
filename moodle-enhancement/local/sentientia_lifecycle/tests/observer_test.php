<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_lifecycle;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the joiner auto-enrolment observer (2026-08-07 rework).
 *
 * Locks in the fix for KeKa-JML work item 9: the old heuristic enrolled
 * every new user into EVERY visible course with a future enddate,
 * platform-wide. The new contract:
 *   - nothing happens while sentientia.lifecycle.autoenrol.enabled is OFF
 *     (the registered default);
 *   - mandatory = visible course carrying the configured tag;
 *   - tenant-scoped via course open_path vs the joiner's open_path root;
 *   - suspended users and dated-but-untagged courses are never touched.
 *
 * @package    local_sentientia_lifecycle
 * @category   test
 */
final class observer_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        // Flag overrides are process-cached across tests in one run.
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    /**
     * Tag a course as mandatory.
     */
    private function tag_course(\stdClass $course, string $tag = 'mandatory'): void {
        \core_tag_tag::set_item_tags('core', 'course', $course->id,
            \context_course::instance($course->id), [$tag]);
    }

    /**
     * Fire the observer for a user, exactly as the events API would.
     */
    private function fire_user_created(int $userid): void {
        $event = \core\event\user_created::create_from_userid($userid);
        observer::user_created($event);
    }

    /**
     * Flip the auto-enrol flag on globally (registered default is OFF).
     */
    private function enable_flag(): void {
        $this->setAdminUser();
        \local_sentientia_platform\feature_flags::set(observer::FLAG_AUTOENROL, 0, true);
    }

    public function test_flag_off_by_default_no_enrolment_at_all(): void {
        // A visible dated course — the OLD heuristic would have enrolled here.
        $course = $this->getDataGenerator()->create_course(
            ['enddate' => time() + WEEKSECS, 'visible' => 1]);
        $this->tag_course($course); // Even tagged mandatory: flag off = dark.

        $user = $this->getDataGenerator()->create_user();
        $this->fire_user_created($user->id);

        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user->id),
            'Observer must be a no-op while sentientia.lifecycle.autoenrol.enabled is OFF');
    }

    public function test_flag_on_enrols_tagged_courses_only(): void {
        $tagged = $this->getDataGenerator()->create_course(['visible' => 1]);
        $this->tag_course($tagged);
        // Dated but untagged — the retired heuristic's target. Must be skipped.
        $dated = $this->getDataGenerator()->create_course(
            ['enddate' => time() + WEEKSECS, 'visible' => 1]);
        // Tagged but hidden — must be skipped.
        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);
        $this->tag_course($hidden);

        $user = $this->getDataGenerator()->create_user();
        $this->enable_flag();
        $this->fire_user_created($user->id);

        $this->assertTrue(is_enrolled(\context_course::instance($tagged->id), $user->id));
        $this->assertFalse(is_enrolled(\context_course::instance($dated->id), $user->id),
            'Dated-but-untagged course must not auto-enrol (retired heuristic)');
        $this->assertFalse(is_enrolled(\context_course::instance($hidden->id), $user->id));
    }

    public function test_tenant_scoping_via_open_path(): void {
        global $DB;

        $sametenant = $this->getDataGenerator()->create_course(['visible' => 1]);
        $this->tag_course($sametenant);
        $DB->set_field('course', 'open_path', '/1/5', ['id' => $sametenant->id]);

        $othertenant = $this->getDataGenerator()->create_course(['visible' => 1]);
        $this->tag_course($othertenant);
        $DB->set_field('course', 'open_path', '/77/3', ['id' => $othertenant->id]);

        $global = $this->getDataGenerator()->create_course(['visible' => 1]);
        $this->tag_course($global); // No open_path → platform-wide mandatory.

        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1/2', ['id' => $user->id]);

        $this->enable_flag();
        $this->fire_user_created($user->id);

        $this->assertTrue(is_enrolled(\context_course::instance($sametenant->id), $user->id));
        $this->assertTrue(is_enrolled(\context_course::instance($global->id), $user->id));
        $this->assertFalse(is_enrolled(\context_course::instance($othertenant->id), $user->id),
            'A course rooted in another tenant must never auto-enrol this joiner');
    }

    public function test_custom_mandatory_tag_setting(): void {
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $this->tag_course($course, 'airpay-compliance');
        set_config('mandatory_tag', 'airpay-compliance', 'local_sentientia_lifecycle');

        $defaulttagged = $this->getDataGenerator()->create_course(['visible' => 1]);
        $this->tag_course($defaulttagged, 'mandatory'); // No longer the configured tag.

        $user = $this->getDataGenerator()->create_user();
        $this->enable_flag();
        $this->fire_user_created($user->id);

        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id));
        $this->assertFalse(is_enrolled(\context_course::instance($defaulttagged->id), $user->id));
    }

    public function test_suspended_user_not_enrolled(): void {
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $this->tag_course($course);

        $user = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $this->enable_flag();
        $this->fire_user_created($user->id);

        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user->id));
    }
}
