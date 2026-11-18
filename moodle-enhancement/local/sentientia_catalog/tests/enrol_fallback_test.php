<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * Persona walk 2026-11-18: course.php's free-course fallback and its inactive-enrolment notice.
 *
 * The theme never renders /enrol/index.php: core_renderer::custom_secured_redirection() sends it to course.php. So
 * course.php's old fallback for a free course ("Enroll Now" linking to /enrol/index.php) only reloaded course.php,
 * and "Continue Learning" on a suspended enrolment went to /course/view.php, /enrol/index.php and back. This locks in
 * what replaced them:
 *
 *   1. bizlms_allows_self_enrol(): BizLMS's own rule (selfenrol = 1, approvalreqd != 1) where the restored columns
 *      exist, and no opinion where they do not.
 *   2. self_enrol_instance(): only an enabled, key-less 'self' instance that core's can_self_enrol() accepts for
 *      the viewer, on a course the viewer's catalogue shows; never for the guest account or another tenant.
 *   3. self_enrol(): enrols through THAT instance (no manual instance, no key bypass), refuses a priced course and
 *      an enrolment already held that is not active (no way round a suspension), and is idempotent.
 *   4. inactive_enrolment(): the reason course.php shows (not started, suspended, ended), in step with core's own
 *      is_enrolled(.., $onlyactive = true).
 *
 * @package    local_sentientia_catalog
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_catalog\enrolment::bizlms_allows_self_enrol
 * @covers     \local_sentientia_catalog\enrolment::self_enrol_instance
 * @covers     \local_sentientia_catalog\enrolment::self_enrol
 * @covers     \local_sentientia_catalog\enrolment::inactive_enrolment
 * @group      tenant_isolation
 */
final class enrol_fallback_test extends \advanced_testcase {

    // Provisions {user}.open_path and {course}.open_path on the test DB.
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    private function learner(int $root = 1): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $root . '/3', ['id' => $u->id]);
        $learner = $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
        $this->setUser($learner);
        return $learner;
    }

    /** A visible course in tenant $root (the generator gives it a manual and a DISABLED self instance). */
    private function course(int $root = 1): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $DB->set_field('course', 'open_path', '/' . $root . '/5', ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /** The course's self-enrolment instance, enabled, open to new enrolments and key-less unless $settings say not. */
    private function enable_self(\stdClass $course, array $settings = []): \stdClass {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $id = enrol_get_plugin('self')->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }
        $settings += [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'password' => '',
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
        ];
        foreach ($settings as $field => $value) {
            $instance->$field = $value;
        }
        $DB->update_record('enrol', $instance);
        return $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /** An enabled enrol_fee instance: the course's price (cart.price_source). */
    private function add_fee(int $courseid, string $cost): void {
        global $DB;
        $DB->insert_record('enrol', (object) [
            'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $courseid, 'sortorder' => 9,
            'cost' => $cost, 'currency' => 'INR',
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    // 1. BizLMS's per-course rule.

    public function test_the_bizlms_rule_needs_selfenrol_and_no_approval_where_the_columns_exist(): void {
        $this->assertTrue(enrolment::bizlms_allows_self_enrol((object) ['id' => 2]),
            'A vanilla course record (no BizLMS columns) leaves the decision to the self instance');
        $this->assertTrue(enrolment::bizlms_allows_self_enrol((object) ['id' => 2, 'selfenrol' => 1, 'approvalreqd' => 0]));
        $this->assertTrue(enrolment::bizlms_allows_self_enrol((object) ['id' => 2, 'selfenrol' => '1', 'approvalreqd' => null]));
        $this->assertFalse(enrolment::bizlms_allows_self_enrol((object) ['id' => 2, 'selfenrol' => 0, 'approvalreqd' => 0]));
        $this->assertFalse(enrolment::bizlms_allows_self_enrol((object) ['id' => 2, 'selfenrol' => null]),
            'BizLMS asked for selfenrol = 1; NULL is not 1');
        $this->assertFalse(enrolment::bizlms_allows_self_enrol((object) ['id' => 2, 'selfenrol' => 1, 'approvalreqd' => 1]),
            'An approval-required course is entered through a request, not self-enrolment');
    }

    // 2 and 3. The course's own self-enrolment.

    public function test_a_free_course_with_a_keyless_self_instance_is_offered_and_enrolled_through_it(): void {
        global $DB;
        $learner = $this->learner(1);
        $course = $this->course(1);
        $instance = $this->enable_self($course);
        $context = \context_course::instance($course->id);
        $manualbefore = $DB->count_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);

        $offered = enrolment::self_enrol_instance((int) $course->id);
        $this->assertNotNull($offered);
        $this->assertSame((int) $instance->id, (int) $offered->id);

        $this->assertTrue(enrolment::self_enrol((int) $course->id));
        $this->assertTrue(is_enrolled($context, $learner->id, '', true));
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $learner->id]),
            'The enrolment is the self instance\'s own, not a manual one');
        $this->assertSame($manualbefore, $DB->count_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']),
            'No manual instance is created');

        // Idempotent, and once enrolled there is nothing left to offer.
        $this->assertTrue(enrolment::self_enrol((int) $course->id));
        $this->assertNull(enrolment::self_enrol_instance((int) $course->id));
    }

    public function test_nothing_is_offered_or_enrolled_without_a_usable_self_instance(): void {
        $learner = $this->learner(1);

        $cases = [
            'self-enrolment disabled (the generator default)' => null,
            'an enrolment key' => ['password' => 'KEY-123'],
            'new enrolments not allowed' => ['customint6' => 0],
            'the enrolment window closed' => ['enrolenddate' => time() - HOURSECS],
            'the enrolment window not open yet' => ['enrolstartdate' => time() + DAYSECS],
        ];
        foreach ($cases as $label => $settings) {
            $course = $this->course(1);
            if ($settings !== null) {
                $this->enable_self($course, $settings);
            }
            $this->assertNull(enrolment::self_enrol_instance((int) $course->id), $label);
            $this->assertFalse(enrolment::self_enrol((int) $course->id), $label);
            $this->assertFalse(is_enrolled(\context_course::instance($course->id), $learner->id), $label);
        }
    }

    public function test_another_tenants_course_is_never_offered_or_enrolled(): void {
        $learner = $this->learner(77);
        $course = $this->course(1);
        $this->enable_self($course);

        $this->assertNull(enrolment::self_enrol_instance((int) $course->id));
        $this->assertFalse(enrolment::self_enrol((int) $course->id));
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $learner->id));
    }

    public function test_a_priced_course_is_never_self_enrolled(): void {
        $learner = $this->learner(1);
        $course = $this->course(1);
        $this->enable_self($course);
        $this->add_fee((int) $course->id, '499.00');

        $this->assertNull(enrolment::self_enrol_instance((int) $course->id), 'No Enroll button for a paid course');
        $this->assertFalse(enrolment::self_enrol((int) $course->id), 'A paid course goes to the cart, never free');
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $learner->id));
    }

    public function test_an_enrolment_that_does_not_let_the_learner_in_is_never_self_enrolled_around(): void {
        global $DB;
        $learner = $this->learner(1);
        $course = $this->course(1);
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $instance = $this->enable_self($course);

        // course.php shows the suspended notice and no button; a hand-made POST to its 'enrolnow' action must not get
        // round the suspension through the self instance (core's can_self_enrol() looks at that instance only).
        $this->assertFalse(enrolment::self_enrol((int) $course->id));
        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $learner->id]));
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $learner->id, '', true));
        $this->assertSame('suspended', enrolment::inactive_enrolment((int) $course->id, (int) $learner->id)->reason);
    }

    public function test_the_guest_and_a_logged_out_visitor_get_nothing(): void {
        $course = $this->course(77);
        $this->enable_self($course);

        $this->setGuestUser();
        $this->assertNull(enrolment::self_enrol_instance((int) $course->id));
        $this->assertFalse(enrolment::self_enrol((int) $course->id));

        $this->setUser(null);
        $this->assertNull(enrolment::self_enrol_instance((int) $course->id));
        $this->assertFalse(enrolment::self_enrol((int) $course->id));
    }

    // 4. Why an enrolment does not let the learner in.

    public function test_inactive_enrolment_names_the_reason_core_agrees_with(): void {
        global $DB;
        $learner = $this->learner(1);
        $gen = $this->getDataGenerator();
        $now = time();

        // No enrolment at all: nothing to explain.
        $none = $this->course(1);
        $this->assertNull(enrolment::inactive_enrolment((int) $none->id, (int) $learner->id));

        // Active: nothing to explain.
        $active = $this->course(1);
        $gen->enrol_user($learner->id, $active->id, 'student');
        $this->assertTrue(is_enrolled(\context_course::instance($active->id), $learner->id, '', true));
        $this->assertNull(enrolment::inactive_enrolment((int) $active->id, (int) $learner->id));

        // Suspended enrolment.
        $suspended = $this->course(1);
        $gen->enrol_user($learner->id, $suspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->assertFalse(is_enrolled(\context_course::instance($suspended->id), $learner->id, '', true));
        $this->assertSame('suspended',
            enrolment::inactive_enrolment((int) $suspended->id, (int) $learner->id)->reason);

        // Active enrolment on a disabled instance: suspended too.
        $disabled = $this->course(1);
        $gen->enrol_user($learner->id, $disabled->id, 'student');
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $disabled->id, 'enrol' => 'manual']);
        $this->assertFalse(is_enrolled(\context_course::instance($disabled->id), $learner->id, '', true));
        $this->assertSame('suspended',
            enrolment::inactive_enrolment((int) $disabled->id, (int) $learner->id)->reason);

        // Not started yet: the start date.
        $future = $this->course(1);
        $gen->enrol_user($learner->id, $future->id, 'student', 'manual', $now + 3 * DAYSECS);
        $this->assertFalse(is_enrolled(\context_course::instance($future->id), $learner->id, '', true));
        $reason = enrolment::inactive_enrolment((int) $future->id, (int) $learner->id);
        $this->assertSame('notstarted', $reason->reason);
        $this->assertSame($now + 3 * DAYSECS, $reason->time);

        // Ended: the end date.
        $ended = $this->course(1);
        $gen->enrol_user($learner->id, $ended->id, 'student', 'manual', $now - 10 * DAYSECS, $now - DAYSECS);
        $this->assertFalse(is_enrolled(\context_course::instance($ended->id), $learner->id, '', true));
        $reason = enrolment::inactive_enrolment((int) $ended->id, (int) $learner->id);
        $this->assertSame('ended', $reason->reason);
        $this->assertSame($now - DAYSECS, $reason->time);

        // Any enrolment that is active now wins over an inactive one.
        $self = $this->enable_self($suspended);
        enrol_get_plugin('self')->enrol_user($self, $learner->id, $self->roleid);
        $this->assertTrue(is_enrolled(\context_course::instance($suspended->id), $learner->id, '', true));
        $this->assertNull(enrolment::inactive_enrolment((int) $suspended->id, (int) $learner->id));
    }
}
