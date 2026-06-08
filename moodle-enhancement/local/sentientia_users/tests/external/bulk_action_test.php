<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Security regression tests for bulk_action.
 *
 * Locks in fixes from the May 5 2026 security audit:
 * - C1 cross-tenant scope: caller can only act on users beneath their
 *   own top-level tenant in open_path.
 * - M1 enumeration oracle: response.count is the post-tenant-filter
 *   request set size, not the actually-changed count.
 * - Self / guest / admin protection: $USER->id, 1, 2 always skipped.
 * - Capability check: caller without local/sentientia_users:edit fails.
 *
 * Tests use synthetic tenant IDs (8000+) to avoid collision.
 *
 * @package    local_sentientia_users
 * @category   test
 */
final class bulk_action_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    private function user_at_path(string $path, bool $suspended = false): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user(['suspended' => $suspended ? 1 : 0]);
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        return $u;
    }

    private function manager_at_path(string $path): \stdClass {
        $u = $this->user_at_path($path);
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/sentientia_users:edit', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        return $u;
    }

    /**
     * C1: a manager in /8001 cannot suspend users with open_path under
     * /8002 or /8003.
     */
    public function test_c1_cannot_suspend_outside_own_tenant(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $caller = $this->manager_at_path('/8001');
        $this->setUser($caller);

        $own_target    = $this->user_at_path('/8001/9001', false);
        $other_target1 = $this->user_at_path('/8002',      false);
        $other_target2 = $this->user_at_path('/8003',      false);

        $r = bulk_action::execute('suspend', [
            $own_target->id, $other_target1->id, $other_target2->id,
        ]);

        $this->assertSame(1, $r['count'],
            'Only the in-tenant target should be reported as acted on');
        $this->assertSame(2, $r['skipped'],
            'The two cross-tenant targets must be skipped');

        $this->assertEquals(1, $DB->get_field('user', 'suspended', ['id' => $own_target->id]));
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $other_target1->id]),
            'C1 leak: cross-tenant user was suspended');
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $other_target2->id]),
            'C1 leak: cross-tenant user was suspended');
    }

    /**
     * Self protection: a caller cannot suspend themselves.
     */
    public function test_self_protected(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $caller = $this->manager_at_path('/8001');
        $this->setUser($caller);

        $r = bulk_action::execute('suspend', [$caller->id]);
        $this->assertSame(0, $r['count']);
        $this->assertSame(1, $r['skipped']);
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $caller->id]));
    }

    /**
     * Guest (id=1) and admin (id=2) are always protected.
     */
    public function test_guest_and_admin_protected(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $r = bulk_action::execute('suspend', [1, 2]);

        $this->assertSame(0, $r['count']);
        $this->assertSame(2, $r['skipped']);
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => 2]),
            'Admin id=2 must never be suspendable via bulk_action');
    }

    /**
     * M1: response 'count' is the request-set size after tenant filter,
     * not the actually-changed count.
     */
    public function test_m1_count_is_request_set_not_change_set(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $u1 = $this->user_at_path('/8001', false);
        $u2 = $this->user_at_path('/8001', false);
        $u3 = $this->user_at_path('/8001', false);

        // All 3 already active; calling activate flips zero rows but the
        // response should still report count=3.
        $r = bulk_action::execute('activate', [$u1->id, $u2->id, $u3->id]);

        $this->assertSame(3, $r['count'],
            'M1: count must equal post-filter request size, not change-set size — '
          . 'returning 0 here would let an attacker probe ID existence by counting deltas');
        $this->assertSame(0, $r['skipped']);
    }

    /**
     * Capability check: caller without local/sentientia_users:edit fails.
     */
    public function test_caller_without_cap_rejected(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        // create_user without any role grant.
        $caller = $this->user_at_path('/8001');
        $target = $this->user_at_path('/8001');
        $this->setUser($caller);

        $this->expectException(\required_capability_exception::class);
        bulk_action::execute('suspend', [$target->id]);
    }

    /**
     * Empty array returns count=0, skipped=0.
     */
    public function test_empty_input(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $r = bulk_action::execute('suspend', []);
        $this->assertSame(0, $r['count']);
        $this->assertSame(0, $r['skipped']);
    }

    /**
     * Invalid action ('delete') throws 'invalidaction'.
     */
    public function test_invalid_action_rejected(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalidaction');
        bulk_action::execute('delete', [3, 4, 5]);
    }
}
