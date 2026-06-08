<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_catalog\catalog_manager
 *
 * Day-3 tenant-isolation regression suite for the catalog query.
 *
 * Why this matters
 * ----------------
 * The catalog's tenant-scoping SQL is the seam between "what data
 * exists" and "what learners see". If `get_courses()` ever leaked
 * cross-tenant rows, an Airpay learner would see Public's internal
 * courses (or vice-versa) — a hard-to-debug compliance regression
 * that user testing might not catch (the wrong courses still LOOK
 * valid, they're just from the wrong tenant).
 *
 * Sprint C refactored the tenant filter from inline open_path checks
 * into `sharing_manager::build_catalog_filter_sql()`. Before Day-3
 * this was tested through the sharing_manager unit tests only — the
 * actual catalog query had zero direct coverage. This suite exercises
 * the join end-to-end:
 *
 *   1. Site admin sees every visible course (1=1 passthrough)
 *   2. Tenant-bound user sees their own tenant's courses
 *   3. Tenant-bound user sees shared courses from another tenant
 *   4. Withdrawn shares don't surface
 *   5. format_course() correctly tags is_borrowed/is_owned
 *   6. Cross-tenant isolation: Airpay user doesn't see Public's owned-only courses
 */
class catalog_manager_test extends \advanced_testcase {

    // Day-3 trait — provisions {user}.open_path + {course}.open_path
    // on the test DB so this suite runs in vanilla PHPUnit.
    use \local_airpay_core\phpunit\open_path_fixture_trait;

    /**
     * Helper: create a course owned by a specific tenant.
     */
    private function make_tenant_course(int $tenant_root, string $name): object {
        global $DB;
        $course = $this->getDataGenerator()->create_course([
            'fullname'  => $name,
            'shortname' => 'sc_' . strtolower(preg_replace('/[^a-z0-9]/i', '', $name))
                . '_' . random_int(1000, 9999),
            'visible'   => 1,
        ]);
        $DB->set_field('course', 'open_path', '/' . $tenant_root,
            ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id]);
    }

    /**
     * Helper: create a user belonging to a specific tenant.
     */
    private function make_tenant_user(int $tenant_root): object {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $tenant_root,
            ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id]);
    }

    /**
     * Helper: extract just the course IDs from a `get_courses()` return,
     * so tests can `assertContains/assertNotContains` cleanly.
     */
    private function ids(array $result): array {
        return array_map(fn($c) => (int) $c['id'], $result['courses']);
    }

    public function test_site_admin_sees_every_tenant_course(): void {
        $airpay = $this->make_tenant_course(1,   'Airpay-internal compliance');
        $public = $this->make_tenant_course(77,  'Public skill builder');
        $zeea   = $this->make_tenant_course(177, 'ZEEA security awareness');

        $this->setAdminUser();
        $result = catalog_manager::get_courses(2);

        $ids = $this->ids($result);
        $this->assertContains((int) $airpay->id, $ids);
        $this->assertContains((int) $public->id, $ids);
        $this->assertContains((int) $zeea->id,   $ids);
    }

    public function test_public_user_sees_only_public_owned_courses_by_default(): void {
        $airpay = $this->make_tenant_course(1,  'Airpay-only course');
        $public = $this->make_tenant_course(77, 'Public-only course');

        $u = $this->make_tenant_user(77);
        $this->setUser($u);

        $result = catalog_manager::get_courses((int) $u->id);
        $ids = $this->ids($result);

        $this->assertContains((int) $public->id, $ids,
            'Public user should see Public courses');
        $this->assertNotContains((int) $airpay->id, $ids,
            'Public user must NOT see Airpay-owned courses without an active share');
    }

    public function test_public_user_sees_airpay_course_after_share(): void {
        $airpay = $this->make_tenant_course(1,  'Airpay course about to be shared');

        $u = $this->make_tenant_user(77);
        // Site admin performs the share.
        $this->setAdminUser();
        \local_sentientia_courses\sharing_manager::share_course((int) $airpay->id, [77]);

        $this->setUser($u);
        $result = catalog_manager::get_courses((int) $u->id);
        $ids = $this->ids($result);

        $this->assertContains((int) $airpay->id, $ids,
            'Public user MUST see Airpay courses that have been shared to /77');
    }

    public function test_withdrawn_share_disappears_from_catalog(): void {
        $airpay = $this->make_tenant_course(1, 'Airpay course');

        $u = $this->make_tenant_user(77);
        $this->setAdminUser();
        \local_sentientia_courses\sharing_manager::share_course((int) $airpay->id, [77]);

        // Now withdraw.
        \local_sentientia_courses\sharing_manager::unshare_course((int) $airpay->id, 77);

        $this->setUser($u);
        $result = catalog_manager::get_courses((int) $u->id);
        $this->assertNotContains((int) $airpay->id, $this->ids($result),
            'Withdrawn share rows (status=withdrawn) must not gate catalog access');
    }

    public function test_format_course_flags_borrowed_courses(): void {
        $airpay = $this->make_tenant_course(1, 'Airpay course');

        $u = $this->make_tenant_user(77);
        $this->setAdminUser();
        \local_sentientia_courses\sharing_manager::share_course((int) $airpay->id, [77]);

        $this->setUser($u);
        $result = catalog_manager::get_courses((int) $u->id);

        $card = null;
        foreach ($result['courses'] as $c) {
            if ((int) $c['id'] === (int) $airpay->id) {
                $card = $c;
                break;
            }
        }
        $this->assertNotNull($card, 'Borrowed course should appear in result');
        $this->assertTrue($card['is_borrowed'],
            'Borrowed course must have is_borrowed=true');
    }

    public function test_format_course_flags_owned_courses_not_borrowed(): void {
        $public = $this->make_tenant_course(77, 'Public-owned course');

        $u = $this->make_tenant_user(77);
        $this->setUser($u);
        $result = catalog_manager::get_courses((int) $u->id);

        $card = null;
        foreach ($result['courses'] as $c) {
            if ((int) $c['id'] === (int) $public->id) {
                $card = $c;
                break;
            }
        }
        $this->assertNotNull($card);
        $this->assertFalse($card['is_borrowed'],
            'Owned course (in viewer tenant tree) must have is_borrowed=false');
    }

    public function test_cross_tenant_isolation_airpay_user_does_not_see_public(): void {
        // Defensive: ensure Public's courses don't leak into Airpay's
        // catalog even though Airpay is the "providing" tenant in the
        // sharing direction. There's no reason an Airpay user should
        // see Public's internal courses.
        $public = $this->make_tenant_course(77, 'Public internal course');

        $u = $this->make_tenant_user(1);
        $this->setUser($u);
        $result = catalog_manager::get_courses((int) $u->id);

        $this->assertNotContains((int) $public->id, $this->ids($result),
            'Airpay user must not see Public-owned courses (no implicit share)');
    }

    public function test_subtenant_user_sees_root_tenant_courses(): void {
        // A user at /1/183/45 (deep inside Airpay's tree) should see
        // all courses with open_path under /1 — the LIKE :prefix
        // clause uses '/1/%' so this is automatic.
        global $DB;
        $u = $this->make_tenant_user(1);
        // Override to deep path.
        $DB->set_field('user', 'open_path', '/1/183/45', ['id' => $u->id]);
        $u = $DB->get_record('user', ['id' => $u->id]);
        $this->setUser($u);

        $airpay_root  = $this->make_tenant_course(1,  'Airpay root course');
        $airpay_deep  = $this->getDataGenerator()->create_course([
            'fullname' => 'Course in a deeper Airpay subtree',
        ]);
        $DB->set_field('course', 'open_path', '/1/183',
            ['id' => $airpay_deep->id]);

        $result = catalog_manager::get_courses((int) $u->id);
        $ids = $this->ids($result);

        $this->assertContains((int) $airpay_root->id, $ids,
            'Deep Airpay user should see root-level Airpay courses (LIKE prefix)');
        $this->assertContains((int) $airpay_deep->id, $ids,
            'Deep Airpay user should see intermediate-level Airpay courses');
    }
}
