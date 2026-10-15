<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

use local_sentientia_platform\feature_flags;

defined('MOODLE_INTERNAL') || die();

/**
 * Visual-pass finding 7 (a), 2026-10-15: when is "Request enrolment" the learner's way in on the catalogue?
 *
 * amd/src/request_button.js listens for [data-action="request-course"], but no page rendered one, so a learner
 * could not start a request. catalog_cta::state() is the rule for when the button belongs; the catalogue (course
 * cards and course.php) renders it, adding only its own routes (a paid course, one-click free enrolment).
 *
 * Locked in here: the flag is registered, default OFF, and OFF offers nothing; the offer needs :request, a real
 * logged-in viewer, no enrolment already, a course submit() would accept, and no self-enrolment of the course's
 * own the viewer can use; a pending request shows as pending rather than as a second button (and a decided or
 * cancelled one does not); a pending request imported from BizLMS counts only while the imported_history flag
 * shows it; and can_request_course() agrees with submit().
 *
 * @package    local_sentientia_request
 * @category   test
 * @covers     \local_sentientia_request\catalog_cta
 * @covers     \local_sentientia_request\request_manager::can_request_course
 *
 * @group      local_sentientia_request
 * @group      tenant_isolation
 */
final class catalog_cta_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        feature_flags::invalidate_caches();
    }

    protected function tearDown(): void {
        feature_flags::invalidate_caches();
        parent::tearDown();
    }

    // Helpers.

    private function flag(string $key, bool $on): void {
        feature_flags::set($key, 0, $on, (int) get_admin()->id, 'test');
        feature_flags::invalidate_caches();
    }

    /** The button flag, ON, and the viewer logged in as a learner at $path. */
    private function learner_with_flag(string $path = '/1/3'): \stdClass {
        $this->flag(catalog_cta::FLAG, true);
        $learner = $this->user_at($path);
        $this->setUser($learner);
        return $learner;
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A course at $path. create_course() leaves a manual instance and a DISABLED self instance, i.e. nothing the
     * learner can enrol through on their own.
     */
    private function course_at(?string $path, bool $visible = true): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => $visible ? 1 : 0]);
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /** An enabled self-enrolment instance, with an enrolment key when $key is given. */
    private function enable_self_enrolment(\stdClass $course, ?string $key = null): void {
        global $DB;
        $plugin = enrol_get_plugin('self');
        $fields = [
            'status'     => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'roleid'     => (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST),
        ];
        if ($key !== null) {
            $fields['password'] = $key;
        }
        $plugin->add_instance($course, $fields);
    }

    private function pending_request(int $userid, int $courseid, array $fields = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_request', (object) ($fields + [
            'userid'          => $userid,
            'item_type'       => 'course',
            'itemid'          => $courseid,
            'courseid'        => $courseid,
            'costcenterid'    => 1,
            'reason'          => 'I need this course for my onboarding plan this month.',
            'status'          => 'pending',
            'route'           => 'admin',
            'approver_userid' => (int) get_admin()->id,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]));
    }

    // The flag.

    public function test_the_flag_is_registered_and_defaults_off(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(catalog_cta::FLAG, $registry);
        $this->assertFalse($registry[catalog_cta::FLAG]['default'], 'A new learner-visible element ships OFF.');
        $this->assertFalse(catalog_cta::enabled());
    }

    public function test_nothing_is_offered_while_the_flag_is_off(): void {
        $learner = $this->user_at('/1/3');
        $this->setUser($learner);
        $course = $this->course_at('/1/5');
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($learner, (int) $course->id));
    }

    // The offer.

    public function test_a_course_with_no_way_in_of_its_own_offers_the_request(): void {
        $learner = $this->learner_with_flag();
        $course = $this->course_at('/1/5');
        $this->assertSame(catalog_cta::REQUEST, catalog_cta::state($learner, (int) $course->id));
    }

    public function test_a_self_enrolment_the_learner_can_use_is_a_way_in_with_or_without_a_key(): void {
        $learner = $this->learner_with_flag();
        $open = $this->course_at('/1/5');
        $this->enable_self_enrolment($open);
        $keyed = $this->course_at('/1/5');
        $this->enable_self_enrolment($keyed, 'SECRET-KEY-123');

        $this->assertSame(catalog_cta::NONE, catalog_cta::state($learner, (int) $open->id),
            'Self-enrolment is open: nothing to request.');
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($learner, (int) $keyed->id),
            'An enrolment key is a way in: the learner types the key.');
    }

    public function test_a_disabled_self_enrolment_is_not_a_way_in(): void {
        global $DB;
        $learner = $this->learner_with_flag();
        $course = $this->course_at('/1/5');
        $this->enable_self_enrolment($course);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $course->id, 'enrol' => 'self']);
        $this->assertSame(catalog_cta::REQUEST, catalog_cta::state($learner, (int) $course->id));
    }

    public function test_a_self_enrolment_the_learner_fails_is_not_a_way_in(): void {
        global $DB;
        $learner = $this->learner_with_flag();
        $course = $this->course_at('/1/5');
        $this->enable_self_enrolment($course);
        // The enrolment window closed yesterday.
        $DB->set_field('enrol', 'enrolenddate', time() - DAYSECS, ['courseid' => $course->id, 'enrol' => 'self']);
        $this->assertSame(catalog_cta::REQUEST, catalog_cta::state($learner, (int) $course->id));
    }

    public function test_an_enrolled_learner_is_offered_nothing(): void {
        $learner = $this->learner_with_flag();
        $course = $this->course_at('/1/5');
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($learner, (int) $course->id));
    }

    // Pending.

    public function test_a_pending_request_shows_as_pending_and_a_decided_one_does_not(): void {
        global $DB;
        $learner = $this->learner_with_flag();
        $course = $this->course_at('/1/5');
        $id = $this->pending_request((int) $learner->id, (int) $course->id);
        $this->assertSame(catalog_cta::PENDING, catalog_cta::state($learner, (int) $course->id),
            'submit() would refuse a second request, so no second button.');

        foreach (['rejected', 'cancelled', 'expired'] as $status) {
            $DB->set_field('local_sentientia_request', 'status', $status, ['id' => $id]);
            $this->assertSame(catalog_cta::REQUEST, catalog_cta::state($learner, (int) $course->id),
                "A {$status} request does not block a new one.");
        }
    }

    public function test_somebody_elses_pending_request_is_not_mine(): void {
        $learner = $this->learner_with_flag();
        $other = $this->user_at('/1/4');
        $course = $this->course_at('/1/5');
        $this->pending_request((int) $other->id, (int) $course->id);
        $this->assertSame(catalog_cta::REQUEST, catalog_cta::state($learner, (int) $course->id));
    }

    public function test_an_imported_pending_request_counts_only_while_the_imported_history_flag_shows_it(): void {
        $learner = $this->learner_with_flag();
        $course = $this->course_at('/1/5');
        $this->pending_request((int) $learner->id, (int) $course->id, ['legacy_source' => 'bizlms']);

        $this->assertSame(catalog_cta::REQUEST, catalog_cta::state($learner, (int) $course->id),
            'With imported_history OFF the lists leave the row out, and so does the duplicate guard of submit().');
        $this->flag(imported_history::FLAG, true);
        $this->assertSame(catalog_cta::PENDING, catalog_cta::state($learner, (int) $course->id));
    }

    // Who and what.

    public function test_a_course_submit_would_refuse_is_not_offered(): void {
        $learner = $this->learner_with_flag('/1/3');
        $foreign = $this->course_at('/177');
        $hidden = $this->course_at('/1/5', false);
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($learner, (int) $foreign->id),
            'Another tenant\'s course: submit() would say error_outoftenant.');
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($learner, (int) $hidden->id));
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($learner, 99999999), 'A course id that is not there.');
    }

    public function test_a_learner_with_no_tenant_is_offered_nothing(): void {
        $learner = $this->learner_with_flag('');
        $course = $this->course_at('/1/5');
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($learner, (int) $course->id));
    }

    public function test_without_the_request_capability_nothing_is_offered(): void {
        global $DB;
        $learner = $this->learner_with_flag();
        $course = $this->course_at('/1/5');
        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('local/sentientia_request:request', CAP_PROHIBIT, $userrole,
            \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($learner, (int) $course->id));
    }

    public function test_it_answers_for_the_logged_in_viewer_only_and_never_for_a_guest(): void {
        $learner = $this->learner_with_flag();
        $other = $this->user_at('/1/4');
        $course = $this->course_at('/1/5');
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($other, (int) $course->id),
            'The self-enrolment test reads $USER, so it never answers for another user.');

        $this->setGuestUser();
        global $USER;
        $this->assertSame(catalog_cta::NONE, catalog_cta::state($USER, (int) $course->id));
        $this->setUser($learner);
        $this->assertSame(catalog_cta::REQUEST, catalog_cta::state($learner, (int) $course->id));
    }

    // can_request_course() is submit()'s own test.

    public function test_can_request_course_agrees_with_submit(): void {
        $learner = $this->user_at('/1/3');
        $this->setUser($learner);
        $own = $this->course_at('/1/5');
        $legacy = $this->course_at(null);
        $foreign = $this->course_at('/177');
        $hidden = $this->course_at('/1/5', false);

        $this->assertTrue(request_manager::can_request_course($learner, (int) $own->id));
        $this->assertTrue(request_manager::can_request_course($learner, (int) $legacy->id), 'A legacy course with no open_path.');
        $this->assertFalse(request_manager::can_request_course($learner, (int) $foreign->id));
        $this->assertFalse(request_manager::can_request_course($learner, (int) $hidden->id));
        $this->assertFalse(request_manager::can_request_course($learner, 99999999));

        // And submit() says the same, with the same error code, for the two that are refused.
        $sink = $this->redirectMessages();
        foreach ([$foreign, $hidden] as $refused) {
            try {
                request_manager::submit((int) $learner->id, (int) $refused->id,
                    'I need this course for my onboarding plan this month.');
                $this->fail('submit() must refuse what can_request_course() refuses.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        }
        request_manager::submit((int) $learner->id, (int) $own->id, 'I need this course for my onboarding plan this month.');
        $sink->close();
    }
}
