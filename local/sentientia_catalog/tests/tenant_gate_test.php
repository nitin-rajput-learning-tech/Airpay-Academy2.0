<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_catalog\catalog_manager
 * @covers \local_sentientia_catalog\commerce
 * @covers \local_sentientia_catalog\enrolment
 *
 * C2 regression suite — UAT-SECURITY-POSTURE-2026-09-03.md, Critical finding
 * C2 ("Cross-tenant self-enrolment").
 *
 * What broke
 * ----------
 * `course.php`, `commerce::add_to_cart()`, and `enrolment::enrol_now()` each
 * resolved a course with only `['id' => $id, 'visible' => 1]` — no tenant /
 * `open_path` scoping — while every catalog BROWSE query
 * (`catalog_manager::get_courses()` etc.) already gates through
 * `sharing_manager::build_catalog_filter_sql()`. A self-registered
 * Public-tenant (/77) learner could browse straight to an Airpay (/1) or
 * ZEEA (/177) internal course id (sequential, guessable) and self-enrol,
 * completely bypassing the tenant-sharing model.
 *
 * What this suite locks in
 * -------------------------
 *   1. `catalog_manager::assert_course_visible_to_viewer()` — the new
 *      single reusable guard — refuses a cross-tenant course and allows a
 *      same-tenant / shared / site-admin-viewed course.
 *   2. `commerce::add_to_cart()` refuses to queue a cross-tenant course.
 *   3. `enrolment::enrol_now()` — the actual enrolment write, and the last
 *      line of defence — refuses a cross-tenant enrolment and still
 *      succeeds for a same-tenant one. This is the exact exploit path from
 *      the audit: a /77 user hitting a /1 course id.
 */
final class tenant_gate_test extends \advanced_testcase {

    // Same trait catalog_manager_test.php and enrolment_test.php already
    // use in this plugin — provisions {user}.open_path + {course}.open_path
    // (plus the open_level/open_skill/open_coursetype BizLMS course columns)
    // on the test DB so this suite runs in vanilla PHPUnit.
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * Helper: create a course owned by a specific tenant root.
     */
    private function make_tenant_course(int $tenant_root, string $name): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course([
            'fullname'  => $name,
            'shortname' => 'sc_' . strtolower(preg_replace('/[^a-z0-9]/i', '', $name))
                . '_' . random_int(1000, 9999),
            'visible'   => 1,
        ]);
        $DB->set_field('course', 'open_path', '/' . $tenant_root, ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id]);
    }

    /**
     * Helper: create a user belonging to a specific tenant root.
     */
    private function make_tenant_user(int $tenant_root): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $tenant_root, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id]);
    }

    private function has_open_path_column(): bool {
        global $DB;
        return $DB->get_manager()->field_exists('course', 'open_path');
    }

    // ── catalog_manager::assert_course_visible_to_viewer() ───────────────

    public function test_guard_refuses_cross_tenant_course(): void {
        if (!$this->has_open_path_column()) {
            $this->markTestSkipped('course.open_path column not present.');
        }

        $airpay = $this->make_tenant_course(1, 'Airpay internal course');
        $public = $this->make_tenant_user(77);
        $this->setUser($public);

        $this->expectException(\moodle_exception::class);
        catalog_manager::assert_course_visible_to_viewer((int) $airpay->id);
    }

    public function test_guard_allows_same_tenant_course(): void {
        if (!$this->has_open_path_column()) {
            $this->markTestSkipped('course.open_path column not present.');
        }

        $public = $this->make_tenant_course(77, 'Public storefront course');
        $u = $this->make_tenant_user(77);
        $this->setUser($u);

        $course = catalog_manager::assert_course_visible_to_viewer((int) $public->id);
        $this->assertEquals((int) $public->id, (int) $course->id);
    }

    public function test_guard_allows_shared_course(): void {
        if (!$this->has_open_path_column()) {
            $this->markTestSkipped('course.open_path column not present.');
        }

        $airpay = $this->make_tenant_course(1, 'Airpay course shared to Public');
        $u = $this->make_tenant_user(77);

        $this->setAdminUser();
        \local_sentientia_courses\sharing_manager::share_course((int) $airpay->id, [77]);

        $this->setUser($u);
        $course = catalog_manager::assert_course_visible_to_viewer((int) $airpay->id);
        $this->assertEquals((int) $airpay->id, (int) $course->id,
            'A course actively shared to the viewer\'s tenant must be allowed');
    }

    public function test_guard_allows_site_admin_for_any_tenant(): void {
        if (!$this->has_open_path_column()) {
            $this->markTestSkipped('course.open_path column not present.');
        }

        $zeea = $this->make_tenant_course(177, 'ZEEA internal course');
        $this->setAdminUser();

        $course = catalog_manager::assert_course_visible_to_viewer((int) $zeea->id);
        $this->assertEquals((int) $zeea->id, (int) $course->id);
    }

    public function test_guard_refuses_hidden_course_even_same_tenant(): void {
        // Unchanged pre-fix behaviour: visible=0 is refused regardless of
        // tenant. Uses a real tenant user so we're testing visibility, not
        // the tenant branch.
        $u = $this->make_tenant_user(1);
        $this->setUser($u);

        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);
        global $DB;
        if ($this->has_open_path_column()) {
            $DB->set_field('course', 'open_path', '/1', ['id' => $hidden->id]);
        }

        $this->expectException(\moodle_exception::class);
        catalog_manager::assert_course_visible_to_viewer((int) $hidden->id);
    }

    // ── commerce::add_to_cart() ───────────────────────────────────────────

    public function test_add_to_cart_refuses_cross_tenant_course(): void {
        if (!$this->has_open_path_column()) {
            $this->markTestSkipped('course.open_path column not present.');
        }

        $airpay = $this->make_tenant_course(1, 'Airpay course for cart test');
        $public = $this->make_tenant_user(77);
        $this->setUser($public);

        $ok = commerce::add_to_cart((int) $airpay->id);

        $this->assertFalse($ok, 'A cross-tenant course must never be added to the cart');
        $this->assertEmpty(commerce::get_cart(), 'Cart must stay empty after a refused add');
    }

    public function test_add_to_cart_allows_same_tenant_course(): void {
        if (!$this->has_open_path_column()) {
            $this->markTestSkipped('course.open_path column not present.');
        }

        $public = $this->make_tenant_course(77, 'Public course for cart test');
        $u = $this->make_tenant_user(77);
        $this->setUser($u);

        $ok = commerce::add_to_cart((int) $public->id);

        $this->assertTrue($ok, 'A same-tenant course must still be addable to the cart');
        $this->assertCount(1, commerce::get_cart());
    }

    // ── enrolment::enrol_now() — the actual exploit path from C2 ─────────

    public function test_enrol_now_refuses_cross_tenant_enrolment(): void {
        if (!$this->has_open_path_column()) {
            $this->markTestSkipped('course.open_path column not present.');
        }

        // The exact exploit from the audit: a Public-tenant (/77) learner
        // self-enrolling into an Airpay-internal (/1) course by id.
        $airpay = $this->make_tenant_course(1, 'Airpay internal training');
        $public = $this->make_tenant_user(77);
        $context = \context_course::instance($airpay->id);

        $ok = enrolment::enrol_now((int) $airpay->id, (int) $public->id);

        $this->assertFalse($ok, 'enrol_now must refuse a cross-tenant enrolment');
        $this->assertFalse(is_enrolled($context, $public->id),
            'A /77 user must never actually be enrolled into a /1 internal course');
    }

    public function test_enrol_now_allows_same_tenant_enrolment(): void {
        if (!$this->has_open_path_column()) {
            $this->markTestSkipped('course.open_path column not present.');
        }

        // Same-tenant baseline: Airpay Academy behaviour for a genuine
        // same-tenant learner must be unchanged by this fix.
        $airpay = $this->make_tenant_course(1, 'Airpay internal free course');
        $employee = $this->make_tenant_user(1);
        $context = \context_course::instance($airpay->id);

        $ok = enrolment::enrol_now((int) $airpay->id, (int) $employee->id);

        $this->assertTrue($ok, 'enrol_now must still succeed for a same-tenant learner');
        $this->assertTrue(is_enrolled($context, $employee->id));
    }

    public function test_enrol_now_allows_shared_cross_tenant_enrolment(): void {
        if (!$this->has_open_path_column()) {
            $this->markTestSkipped('course.open_path column not present.');
        }

        // A course explicitly shared to /77 must remain enrollable by a
        // /77 learner — the fix must not break the Sprint C sharing model.
        $airpay = $this->make_tenant_course(1, 'Airpay course shared for enrolment');
        $public = $this->make_tenant_user(77);

        $this->setAdminUser();
        \local_sentientia_courses\sharing_manager::share_course((int) $airpay->id, [77]);

        $context = \context_course::instance($airpay->id);
        $ok = enrolment::enrol_now((int) $airpay->id, (int) $public->id);

        $this->assertTrue($ok, 'A shared course must remain enrollable by the tenant it was shared to');
        $this->assertTrue(is_enrolled($context, $public->id));
    }
}
