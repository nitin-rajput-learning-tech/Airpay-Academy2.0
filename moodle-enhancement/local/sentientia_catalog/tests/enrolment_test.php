<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_catalog\enrolment
 *
 * QA-walk P1 (2026-05-29) regression suite — Airpay-tenant employees could
 * not self-enrol in "Free" courses.
 *
 * What this locks in
 * ------------------
 *   - The POLICY ({@see enrolment::should_offer_oneclick()}): one-click
 *     "Enrol now" is offered ONLY to logged-in, internal-tenant users on
 *     FREE courses, and ONLY when the feature flag is ON for that tenant.
 *     The Public storefront tenant (/77) and guests keep the cart; paid
 *     courses always keep the cart.
 *   - The MECHANISM ({@see enrolment::enrol_now()}): a free course is
 *     enrolled immediately via the MANUAL enrol plugin, bypassing a
 *     self-enrol enrolment key (the exact config that broke course 71 in
 *     production). Idempotent, and refuses paid courses.
 */
final class enrolment_test extends \advanced_testcase {

    use \local_airpay_core\phpunit\open_path_fixture_trait;

    /**
     * Create a FREE course with a key-gated, enabled self-enrol instance —
     * the exact shape of production course 71 ("Aptitude Test Advanced"):
     * self status=ENABLED, allownew=1, with an enrolment key set.
     */
    private function make_keyed_free_course(int $tenant_root): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $DB->set_field('course', 'open_path', '/' . $tenant_root, ['id' => $course->id]);

        $self = enrol_get_plugin('self');
        $self->add_instance($course, [
            'status'     => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,                // allow new enrolments
            'password'   => 'SECRET-KEY-123', // the key that broke prod
            'roleid'     => 5,
        ]);

        return $DB->get_record('course', ['id' => $course->id]);
    }

    private function make_user(int $tenant_root): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $tenant_root, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id]);
    }

    /** Turn the one-click flag ON for a given tenant root. */
    private function enable_flag(int $tenant_root): void {
        \local_airpay_core\feature_flags::set(enrolment::FLAG, $tenant_root, true, null, 'phpunit');
        \local_airpay_core\feature_flags::invalidate_caches();
    }

    private function free_pricing(): array {
        return ['is_free' => true, 'price' => 0, 'display' => 'Free'];
    }

    private function paid_pricing(): array {
        return ['is_free' => false, 'price' => 499, 'display' => '₹499'];
    }

    // ── POLICY: should_offer_oneclick() ──────────────────────────────────

    public function test_internal_tenant_user_gets_oneclick_when_flag_on(): void {
        $airpay = $this->make_user(1);          // Airpay internal tenant
        $this->enable_flag(1);
        $this->assertTrue(
            enrolment::should_offer_oneclick($airpay, $this->free_pricing()),
            'Airpay (/1) employee on a free course with the flag ON should get one-click');
    }

    public function test_public_tenant_user_keeps_cart_even_with_flag_on(): void {
        $public = $this->make_user(77);         // Public storefront tenant
        $this->enable_flag(77);                 // even if someone flips it on for /77
        $this->assertFalse(
            enrolment::should_offer_oneclick($public, $this->free_pricing()),
            'Public (/77) tenant must always keep the cart — protects the B2C funnel');
    }

    public function test_no_oneclick_when_flag_off(): void {
        $airpay = $this->make_user(1);
        // Flag NOT enabled — default OFF reproduces today's production behaviour.
        $this->assertFalse(
            enrolment::should_offer_oneclick($airpay, $this->free_pricing()),
            'Flag OFF (default) must preserve the cart route');
    }

    public function test_no_oneclick_for_paid_course(): void {
        $airpay = $this->make_user(1);
        $this->enable_flag(1);
        $this->assertFalse(
            enrolment::should_offer_oneclick($airpay, $this->paid_pricing()),
            'Paid courses must always use the cart/checkout');
    }

    public function test_no_oneclick_for_guest(): void {
        global $CFG;
        $this->enable_flag(1);
        $guest = \core_user::get_user($CFG->siteguest);
        $guest->open_path = '/1';   // even if a guest somehow carries a tenant path
        $this->assertFalse(
            enrolment::should_offer_oneclick($guest, $this->free_pricing()),
            'Guests must never get one-click — they fall through to the signup/cart funnel');
    }

    public function test_no_oneclick_for_unclassified_tenant(): void {
        $orphan = $this->make_user(0);  // open_path '/0' → root 0 (unclassified)
        $this->enable_flag(1);
        $this->assertFalse(
            enrolment::should_offer_oneclick($orphan, $this->free_pricing()),
            'A user with no real tenant root must fail closed');
    }

    // ── MECHANISM: enrol_now() ───────────────────────────────────────────

    public function test_enrol_now_bypasses_self_enrol_key(): void {
        $course = $this->make_keyed_free_course(1);
        $user   = $this->make_user(1);
        $context = \context_course::instance($course->id);

        $this->assertFalse(is_enrolled($context, $user->id),
            'precondition: user starts unenrolled');

        $ok = enrolment::enrol_now((int) $course->id, (int) $user->id);

        $this->assertTrue($ok, 'enrol_now should succeed despite the enrolment key');
        $this->assertTrue(is_enrolled($context, $user->id),
            'user must be actually enrolled (the key must NOT block manual enrol)');
    }

    public function test_enrol_now_is_idempotent(): void {
        $course = $this->make_keyed_free_course(1);
        $user   = $this->make_user(1);
        $context = \context_course::instance($course->id);

        $this->assertTrue(enrolment::enrol_now((int) $course->id, (int) $user->id));
        $this->assertTrue(enrolment::enrol_now((int) $course->id, (int) $user->id),
            'a second call is a no-op success');

        // Exactly one user_enrolment row for this user across the course.
        $count = $GLOBALS['DB']->count_records_sql(
            "SELECT COUNT(ue.id) FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid",
            ['cid' => $course->id, 'uid' => $user->id]);
        $this->assertEquals(1, $count, 'must not create duplicate enrolments');
    }

    public function test_enrol_now_refuses_paid_course(): void {
        $course = $this->make_keyed_free_course(1);
        // Make it paid.
        commerce::set_course_price((int) $course->id, 499);
        $user   = $this->make_user(1);
        $context = \context_course::instance($course->id);

        $ok = enrolment::enrol_now((int) $course->id, (int) $user->id);

        $this->assertFalse($ok, 'enrol_now must refuse a paid course');
        $this->assertFalse(is_enrolled($context, $user->id),
            'a paid course must never be enrolled via the free path');
    }

    public function test_enrol_now_creates_manual_instance_when_missing(): void {
        global $DB;
        // A free course with NO enrol instances at all (the 40-course slice
        // from the tenant-coverage diagnostic).
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        $DB->delete_records('enrol', ['courseid' => $course->id]); // strip generator defaults
        $user = $this->make_user(1);

        $ok = enrolment::enrol_now((int) $course->id, (int) $user->id);

        $this->assertTrue($ok, 'enrol_now should self-provision a manual instance');
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id));
    }
}
