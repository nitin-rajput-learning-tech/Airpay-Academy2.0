<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_catalog\catalog_manager
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
     * 2026-05-29 broader-sweep follow-up: drop the per-request tenant
     * category cache on accesslib so each test gets a clean resolver
     * lookup. Without this, the cache would hold a category id from
     * test N that's invalid in test N+1 after resetAfterTest() rolls
     * the categories table back.
     */
    public function setUp(): void {
        parent::setUp();
        \local_airpay_org\accesslib::reset_tenant_category_cache();
    }

    /**
     * Prime the accesslib resolver chain for a given tenant root.
     *
     * Creates (idempotently):
     *   - course_categories row at depth=1 with idnumber='t' . $tenant_root
     *   - local_airpay_org row with id=$tenant_root, shortname matching
     *     the category idnumber, depth=1
     *
     * Result: \local_airpay_org\accesslib::get_tenant_category_id('/N')
     * resolves to the category id returned here. Required for the v2
     * catalog tenant filter (2026-05-29) because v2 keys off
     * course_categories.idnumber via the Sentientia-native fallback in
     * the resolver chain (no BizLMS local_costcenter on PHPUnit fixture).
     *
     * @return int course_categories.id for this tenant
     */
    private function setup_tenant_topology(int $tenant_root): int {
        global $DB;

        $shortname = 't' . $tenant_root;

        // (1) Category at depth=1 with the right idnumber.
        $existing_cat_id = (int) $DB->get_field('course_categories', 'id',
            ['idnumber' => $shortname]);
        if ($existing_cat_id) {
            $cat_id = $existing_cat_id;
        } else {
            $cat = $this->getDataGenerator()->create_category([
                'name'     => 'Tenant ' . $tenant_root,
                'idnumber' => $shortname,
            ]);
            $cat_id = (int) $cat->id;
        }

        // (2) local_airpay_org row with explicit id=$tenant_root. Raw SQL
        // because Moodle's insert_record() honours the sequence=true on
        // the id column and would auto-assign a different id, breaking
        // the resolver's lookup-by-id contract.
        if ($DB->get_manager()->table_exists('local_airpay_org')
                && !$DB->record_exists('local_airpay_org', ['id' => $tenant_root])) {
            $DB->execute(
                "INSERT INTO {local_airpay_org}
                   (id, fullname, shortname, parentid, path, depth, visible,
                    sortorder, timecreated, timemodified)
                 VALUES (:id, :fn, :sn, 0, :path, 1, 1, 0, :t, :t)",
                [
                    'id'   => $tenant_root,
                    'fn'   => 'Tenant ' . $tenant_root,
                    'sn'   => $shortname,
                    'path' => '/' . $tenant_root,
                    't'    => time(),
                ]
            );
        }

        return $cat_id;
    }

    /**
     * Helper: create a course owned by a specific tenant.
     *
     * Day-3 contract: also set c.open_path so v1 sharing_manager filter
     * and format_course's is_borrowed/is_owned logic both still work.
     * Broader-sweep contract (2026-05-29): place the course in the
     * tenant's primed course_category so the v2 cc.path filter matches.
     */
    private function make_tenant_course(int $tenant_root, string $name): object {
        global $DB;
        $cat_id = $this->setup_tenant_topology($tenant_root);
        $course = $this->getDataGenerator()->create_course([
            'fullname'  => $name,
            'shortname' => 'sc_' . strtolower(preg_replace('/[^a-z0-9]/i', '', $name))
                . '_' . random_int(1000, 9999),
            'visible'   => 1,
            'category'  => $cat_id,
        ]);
        $DB->set_field('course', 'open_path', '/' . $tenant_root,
            ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id]);
    }

    /**
     * Helper: create a user belonging to a specific tenant.
     *
     * Also primes the resolver chain so this user's tenant resolves to
     * a real course_categories.id (used when this user becomes the
     * `setUser` viewer in the test).
     */
    private function make_tenant_user(int $tenant_root): object {
        global $DB;
        $this->setup_tenant_topology($tenant_root);
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
        \local_airpay_courses\sharing_manager::share_course((int) $airpay->id, [77]);

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
        \local_airpay_courses\sharing_manager::share_course((int) $airpay->id, [77]);

        // Now withdraw.
        \local_airpay_courses\sharing_manager::unshare_course((int) $airpay->id, 77);

        $this->setUser($u);
        $result = catalog_manager::get_courses((int) $u->id);
        $this->assertNotContains((int) $airpay->id, $this->ids($result),
            'Withdrawn share rows (status=withdrawn) must not gate catalog access');
    }

    public function test_format_course_flags_borrowed_courses(): void {
        $airpay = $this->make_tenant_course(1, 'Airpay course');

        $u = $this->make_tenant_user(77);
        $this->setAdminUser();
        \local_airpay_courses\sharing_manager::share_course((int) $airpay->id, [77]);

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
        // every course under Airpay's tenant — the OR cc.path LIKE
        // :catpathwild clause in v2 covers descendants of the tenant
        // category root. Previously this test exercised c.open_path
        // LIKE '/1/%'; v2 keys off course_categories.path instead, so
        // the deep course is now placed in Airpay's tenant category
        // (rather than the default Miscellaneous cat) to match.
        global $DB;
        $u = $this->make_tenant_user(1);
        // Override to deep path.
        $DB->set_field('user', 'open_path', '/1/183/45', ['id' => $u->id]);
        $u = $DB->get_record('user', ['id' => $u->id]);
        $this->setUser($u);

        $airpay_root  = $this->make_tenant_course(1,  'Airpay root course');
        $airpay_deep  = $this->make_tenant_course(1,  'Course in a deeper Airpay subtree');
        // Mark the deep course's open_path as a sub-org path so any v1
        // consumer (format_course's is_owned check) still recognises it
        // as Airpay-owned.
        $DB->set_field('course', 'open_path', '/1/183',
            ['id' => $airpay_deep->id]);

        $result = catalog_manager::get_courses((int) $u->id);
        $ids = $this->ids($result);

        $this->assertContains((int) $airpay_root->id, $ids,
            'Deep Airpay user should see root-level Airpay courses');
        $this->assertContains((int) $airpay_deep->id, $ids,
            'Deep Airpay user should see intermediate-level Airpay courses');
    }
}
