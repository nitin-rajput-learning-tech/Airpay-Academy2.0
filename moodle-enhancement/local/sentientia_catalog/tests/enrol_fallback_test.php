<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * Persona walk 2026-11-18: course.php's inactive-enrolment notice.
 *
 * "Continue Learning" on a suspended, ended or not-yet-started enrolment went to /course/view.php, which sends a
 * learner without an active enrolment to /enrol/index.php, which the theme never renders
 * (core_renderer::custom_secured_redirection() sends it back to course.php). course.php now shows the reason
 * instead, and this locks it in: inactive_enrolment() names it (not started, suspended, ended) in step with core's
 * own is_enrolled(.., $onlyactive = true).
 *
 * The self-enrol fallback was added and withdrawn on 2026-11-18: a new enrolment path with no flag.
 *
 * @package    local_sentientia_catalog
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

    // Why an enrolment does not let the learner in.

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
