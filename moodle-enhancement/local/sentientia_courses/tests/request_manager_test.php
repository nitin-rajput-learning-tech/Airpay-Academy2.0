<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_courses\request_manager
 *
 * Sprint D unit tests for the pull/request workflow.
 *
 * Coverage:
 *   - create_request inserts a pending row
 *   - create_request is idempotent on duplicate pending
 *   - create_request short-circuits to 0 when already shared
 *   - create_request rejects when course doesn't exist
 *   - create_request rejects when user has no tenant root
 *   - create_request rejects when user is in the same tenant as the course
 *   - approve_request flips status + inserts a share row
 *   - approve_request is no-op on already-approved
 *   - reject_request flips status + stores reason
 *   - reject_request is no-op on already-rejected
 *   - request_state returns the right enum value
 *   - list_pending_requests + list_tenant_requests basic shape
 *
 * Tests touch mdl_user.open_path so they need the BizLMS column;
 * skip when it's not present (vanilla PHPUnit fixture).
 */
class request_manager_test extends \advanced_testcase {

    // Day-3 (2026-05-14): pull in the airpay_core open_path fixture
    // trait. Replaces the old skip_if_no_open_path() guards — the
    // trait ensures the column exists on every test run.
    use \local_airpay_core\phpunit\open_path_fixture_trait;

    /**
     * Kept for backwards compatibility — now a no-op. The trait
     * guarantees the column is present so the skip never fires.
     */
    private function skip_if_no_open_path(): void {
        // Trait ensured the column exists. Nothing to do.
    }

    /**
     * Create a course in the Airpay tenant (open_path='/1').
     * Returns the course object.
     *
     * Day-3 update: the open_path_fixture_trait now ensures the
     * `course.open_path` column exists before any test runs, so the
     * pre-existing markTestSkipped guard is no longer needed.
     */
    private function make_airpay_course(): object {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id]);
    }

    /**
     * Create a user belonging to a specific tenant (open_path='/N').
     */
    private function make_tenant_user(int $tenant_root): object {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $tenant_root, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id]);
    }

    public function test_create_request_inserts_pending_row(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);

        $id = request_manager::create_request((int) $course->id);
        $this->assertGreaterThan(0, $id);

        $row = $DB->get_record('local_sentientia_courses_requests', ['id' => $id]);
        $this->assertSame('pending', $row->status);
        $this->assertSame(77, (int) $row->requesting_tenant);
        $this->assertSame((int) $manager->id, (int) $row->requester_userid);
    }

    public function test_create_request_idempotent_on_pending(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);

        $id1 = request_manager::create_request((int) $course->id);
        $id2 = request_manager::create_request((int) $course->id);
        $this->assertSame($id1, $id2);
    }

    public function test_create_request_returns_zero_when_already_shared(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $this->setAdminUser();
        sharing_manager::share_course((int) $course->id, [77]);

        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);

        $id = request_manager::create_request((int) $course->id);
        $this->assertSame(0, $id);
    }

    public function test_create_request_throws_on_unknown_course(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);

        $this->expectException(\moodle_exception::class);
        request_manager::create_request(999999);
    }

    public function test_create_request_throws_when_user_in_same_tenant_as_course(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        // An Airpay user (open_path=/1) cannot request their own course.
        $airpay_user = $this->make_tenant_user(1);
        $this->setUser($airpay_user);

        $this->expectException(\moodle_exception::class);
        request_manager::create_request((int) $course->id);
    }

    public function test_approve_request_flips_status_and_inserts_share(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);
        $rid = request_manager::create_request((int) $course->id);

        $this->setAdminUser();
        $changed = request_manager::approve_request($rid);
        $this->assertTrue($changed);

        $row = $DB->get_record('local_sentientia_courses_requests', ['id' => $rid]);
        $this->assertSame('approved', $row->status);
        $this->assertNotEmpty($row->decided_by);

        // And the share row should now exist.
        $share = $DB->get_record('local_sentientia_courses_tenant_share',
            ['courseid' => $course->id, 'tenant_id' => 77]);
        $this->assertNotEmpty($share);
        $this->assertSame('active', $share->status);
    }

    public function test_approve_request_is_noop_on_already_approved(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);
        $rid = request_manager::create_request((int) $course->id);
        $this->setAdminUser();
        request_manager::approve_request($rid);
        $second = request_manager::approve_request($rid);
        $this->assertFalse($second);
    }

    public function test_reject_request_flips_status_and_stores_reason(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);
        $rid = request_manager::create_request((int) $course->id);

        $this->setAdminUser();
        $changed = request_manager::reject_request($rid, 'Out of scope for your tenant');
        $this->assertTrue($changed);

        $row = $DB->get_record('local_sentientia_courses_requests', ['id' => $rid]);
        $this->assertSame('rejected', $row->status);
        $this->assertSame('Out of scope for your tenant', $row->decision_reason);

        // And no share row was inserted.
        $share = $DB->record_exists('local_sentientia_courses_tenant_share',
            ['courseid' => $course->id, 'tenant_id' => 77]);
        $this->assertFalse($share);
    }

    public function test_reject_request_is_noop_on_already_rejected(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);
        $rid = request_manager::create_request((int) $course->id);
        $this->setAdminUser();
        request_manager::reject_request($rid, 'first');
        $second = request_manager::reject_request($rid, 'second');
        $this->assertFalse($second);
    }

    public function test_request_state_enum(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        // none — never requested.
        $this->assertSame('none',
            request_manager::request_state((int) $course->id, 77));

        // pending — request created.
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);
        request_manager::create_request((int) $course->id);
        $this->assertSame('pending',
            request_manager::request_state((int) $course->id, 77));

        // approved — admin approves; state becomes 'already_shared'
        // because the share row trumps the request status check.
        $this->setAdminUser();
        $rid = (int) ($GLOBALS['DB']->get_field('local_sentientia_courses_requests',
            'id', ['courseid' => $course->id, 'requesting_tenant' => 77]));
        request_manager::approve_request($rid);
        $this->assertSame('already_shared',
            request_manager::request_state((int) $course->id, 77));
    }

    public function test_request_state_after_admin_unshares_an_approved_course(): void {
        // The edge case: a request was filed → approved → share row is
        // now active → admin LATER unshares → the manager should see
        // 'none' so they can re-request. Without this guard the old
        // request_state would still return 'approved' from the
        // historical request row and the UI would say "In your catalog"
        // when the catalog no longer has the course.
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);
        request_manager::create_request((int) $course->id);

        $this->setAdminUser();
        $rid = (int) ($GLOBALS['DB']->get_field('local_sentientia_courses_requests',
            'id', ['courseid' => $course->id, 'requesting_tenant' => 77]));
        request_manager::approve_request($rid);
        $this->assertSame('already_shared',
            request_manager::request_state((int) $course->id, 77));

        // Now admin unshares the course.
        sharing_manager::unshare_course((int) $course->id, 77);

        // State should be 'none' — the historical 'approved' request
        // row is deliberately ignored once the share is no longer
        // active. (The whole point of request_state being decoupled
        // from request-row status post-approval.)
        $this->assertSame('none',
            request_manager::request_state((int) $course->id, 77));
    }

    public function test_request_state_pending_request_wins_over_old_rejected(): void {
        // Older rejected → newer pending should yield 'pending'.
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $manager = $this->make_tenant_user(77);
        $this->setUser($manager);

        $rid1 = request_manager::create_request((int) $course->id);
        $this->setAdminUser();
        request_manager::reject_request($rid1, 'try again later');
        $this->assertSame('rejected',
            request_manager::request_state((int) $course->id, 77));

        // Manager re-requests.
        $this->setUser($manager);
        request_manager::create_request((int) $course->id);
        $this->assertSame('pending',
            request_manager::request_state((int) $course->id, 77));
    }

    public function test_list_pending_requests_returns_only_pending(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $managerA = $this->make_tenant_user(77);
        $this->setUser($managerA);
        request_manager::create_request((int) $course->id);

        $managerB = $this->make_tenant_user(177);
        $this->setUser($managerB);
        request_manager::create_request((int) $course->id);

        $pending = request_manager::list_pending_requests();
        $this->assertCount(2, $pending);
        foreach ($pending as $r) {
            $this->assertSame('pending', $r->status);
        }

        // Approve one — the other should still be in the pending list.
        $this->setAdminUser();
        request_manager::approve_request((int) $pending[0]->id);
        $pending2 = request_manager::list_pending_requests();
        $this->assertCount(1, $pending2);
    }

    public function test_list_tenant_requests_filters_by_tenant(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        $course = $this->make_airpay_course();
        $managerA = $this->make_tenant_user(77);
        $this->setUser($managerA);
        request_manager::create_request((int) $course->id);

        $managerB = $this->make_tenant_user(177);
        $this->setUser($managerB);
        request_manager::create_request((int) $course->id);

        $rs77  = request_manager::list_tenant_requests(77);
        $rs177 = request_manager::list_tenant_requests(177);
        $this->assertCount(1, $rs77);
        $this->assertCount(1, $rs177);
        $this->assertSame(77,  (int) $rs77[0]->requesting_tenant);
        $this->assertSame(177, (int) $rs177[0]->requesting_tenant);
    }
}
