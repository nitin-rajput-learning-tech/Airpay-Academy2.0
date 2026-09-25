<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 batch (2026-05-16) — tests for tenant-scoped supervisor autocomplete +
 * server-side cross-tenant guard.
 *
 * Locks in:
 *   - search_supervisors WS: non-siteadmin only sees same-tenant users
 *   - search_supervisors WS: siteadmin sees across tenants
 *   - search_supervisors WS: empty/short query returns no rows
 *   - guard_supervisor_tenant_scope blocks cross-tenant assignment
 *   - guard_supervisor_tenant_scope allows same-tenant assignment
 *   - guard_supervisor_tenant_scope is bypassed for siteadmin
 *
 * @package    local_sentientia_users
 * @category   test
 *
 * @group tenant_isolation
 */
final class supervisor_scope_test extends \advanced_testcase {

    /** Helper: create a user with a specific open_path. */
    private function seed_user(string $name, string $open_path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user([
            'firstname' => $name,
            'lastname'  => 'Tester',
            'email'     => strtolower($name) . '_' . uniqid() . '@example.org',
        ]);
        $DB->set_field('user', 'open_path', $open_path, ['id' => $u->id]);
        $u->open_path = $open_path;
        return $u;
    }

    public function test_search_returns_empty_for_short_query(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = \local_sentientia_users\external\search_supervisors::execute('a', 0);
        $this->assertSame(0, $result['total'],
            'Short query (< MIN_QUERY_LEN) must return empty');
    }

    public function test_siteadmin_sees_users_across_tenants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed_user('Alpha', '/1');
        $this->seed_user('Alpha', '/77');

        $result = \local_sentientia_users\external\search_supervisors::execute('Alpha', 0);
        $this->assertGreaterThanOrEqual(2, $result['total'],
            'siteadmin should see Alpha-named users across tenants');
    }

    public function test_non_siteadmin_only_sees_own_tenant(): void {
        $this->resetAfterTest();
        global $DB;

        // The caller is a tenant admin as ADR-031 defines one: a
        // manager-archetype role at system context, which carries
        // local/sentientia_users:view (the WS capability) but NOT
        // local/sentientia_platform:crosstenant. Until 2026-09-25 this user
        // held no role at all, so the WS refused it at require_capability()
        // and the tenant filter below was never reached.
        $airpay_admin = $this->seed_user('Bravo', '/1');
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $airpay_admin->id, \context_system::instance()->id);
        $this->setUser($airpay_admin);
        $this->assertFalse(\local_sentientia_platform\tenant::is_cross_tenant((int) $airpay_admin->id),
            'Precondition: a tenant admin is not cross-tenant');

        // Seed: 2 users in /1, 2 in /77, and a ZEEA /177 decoy whose path
        // shares the '/1' prefix (the path-boundary defect class).
        $a = $this->seed_user('Bravo-A', '/1');
        $b = $this->seed_user('Bravo-B', '/1');
        $this->seed_user('Bravo-C', '/77');
        $this->seed_user('Bravo-D', '/77');
        $this->seed_user('Bravo-E', '/177');

        $result = \local_sentientia_users\external\search_supervisors::execute('Bravo', 0);

        // Should ONLY see the /1 users (including the admin themselves +
        // 2 seeded). NOT the /77 ones.
        $emails = array_column($result['rows'], 'email');
        foreach ($result['rows'] as $row) {
            $u = $DB->get_record('user', ['id' => $row['id']], 'open_path');
            $this->assertStringStartsWith('/1', $u->open_path,
                'Non-siteadmin must NEVER see /77 results: ' . $row['email']);
        }

        // Exactly the caller's tenant: without this the loop above passes
        // vacuously on an empty result, and '/177' would satisfy '/1'.
        $expected = [(int) $airpay_admin->id, (int) $a->id, (int) $b->id];
        sort($expected);
        $actual = array_map('intval', array_column($result['rows'], 'id'));
        sort($actual);
        $this->assertSame($expected, $actual,
            'Tenant admin at /1 must see exactly the /1 users, got: ' . implode(', ', $emails));
    }

    public function test_guard_blocks_cross_tenant_supervisor(): void {
        $this->resetAfterTest();
        global $DB;

        // Create a non-siteadmin caller in tenant /77.
        $caller = $this->seed_user('Caller', '/77');
        $this->setUser($caller);

        // Subordinate is in /77.
        $sub = $this->seed_user('Sub', '/77');

        // Supervisor is in /1 (different tenant).
        $sup = $this->seed_user('Sup', '/1');

        // Apply via user_manager::update (which calls apply_custom_fields,
        // which calls guard_supervisor_tenant_scope).
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/supervisor.+tenant/i');

        \local_sentientia_users\user_manager::update($sub->id, (object) [
            'open_supervisorid' => $sup->id,
        ]);
    }

    public function test_guard_allows_same_tenant_supervisor(): void {
        $this->resetAfterTest();
        global $DB;

        $caller = $this->seed_user('AllowCaller', '/1');
        $this->setUser($caller);

        $sub = $this->seed_user('AllowSub', '/1');
        $sup = $this->seed_user('AllowSup', '/1');

        \local_sentientia_users\user_manager::update($sub->id, (object) [
            'open_supervisorid' => $sup->id,
        ]);

        $updated = $DB->get_record('user', ['id' => $sub->id], 'open_supervisorid');
        $this->assertSame((int) $sup->id, (int) $updated->open_supervisorid);
    }

    public function test_guard_bypassed_for_siteadmin(): void {
        $this->resetAfterTest();
        global $DB;
        $this->setAdminUser();  // siteadmin

        // Subordinate in /77, supervisor in /1.
        $sub = $this->seed_user('AdminSub', '/77');
        $sup = $this->seed_user('AdminSup', '/1');

        // Siteadmin SHOULD be able to do this — bypasses the tenant rule.
        \local_sentientia_users\user_manager::update($sub->id, (object) [
            'open_supervisorid' => $sup->id,
        ]);

        $updated = $DB->get_record('user', ['id' => $sub->id], 'open_supervisorid');
        $this->assertSame((int) $sup->id, (int) $updated->open_supervisorid,
            'Siteadmin must be able to bypass tenant scoping');
    }

    public function test_subject_userid_scopes_to_subjects_tenant(): void {
        // Even if a siteadmin runs the search, when they pass subject_userid
        // we should scope results to the SUBJECT's tenant — that's the
        // "edit this user's manager" workflow.
        $this->resetAfterTest();
        $this->setAdminUser();

        $sub_in_77 = $this->seed_user('Subject', '/77');

        // Seed candidate supervisors in BOTH tenants.
        $this->seed_user('Bossy-A', '/1');
        $this->seed_user('Bossy-B', '/77');

        $result = \local_sentientia_users\external\search_supervisors::execute(
            'Bossy', (int) $sub_in_77->id);

        // Should only return /77 users.
        global $DB;
        foreach ($result['rows'] as $row) {
            $u = $DB->get_record('user', ['id' => $row['id']], 'open_path');
            $this->assertStringStartsWith('/77', $u->open_path,
                "subject_userid scoping leaked across tenants: {$row['email']}");
        }
    }
}
