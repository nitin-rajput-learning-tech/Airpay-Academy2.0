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
 */
final class supervisor_scope_test extends \advanced_testcase {

    /**
     * The suite exercises the BizLMS tenant columns, which a vanilla PHPUnit
     * site doesn't have — self-provision them (DDL persists for the run;
     * matches the production schema this suite exists to protect).
     */
    public static function setUpBeforeClass(): void {
        global $DB;
        parent::setUpBeforeClass();
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('user');
        $path = new \xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $path)) {
            $dbman->add_field($table, $path);
        }
        $sup = new \xmldb_field('open_supervisorid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $sup)) {
            $dbman->add_field($table, $sup);
        }
        // The search WS SELECTs these for display in the autocomplete rows.
        $emp = new \xmldb_field('open_employeeid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        if (!$dbman->field_exists($table, $emp)) {
            $dbman->add_field($table, $emp);
        }
        $des = new \xmldb_field('open_designation', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $des)) {
            $dbman->add_field($table, $des);
        }
    }

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

        $airpay_admin = $this->seed_user('Bravo', '/1');
        // Move admin into tenant /1.
        $DB->set_field('user', 'open_path', '/1', ['id' => $airpay_admin->id]);

        // The WS requires local/sentientia_users:view — grant it like a real
        // L&D-admin role would, WITHOUT siteadmin, so this test still proves
        // tenant scoping for non-siteadmins. Grant BEFORE setUser(): access
        // data is cached per-user at login time.
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'supsearch']);
        assign_capability('local/sentientia_users:view', CAP_ALLOW, $roleid,
            \context_system::instance());
        role_assign($roleid, $airpay_admin->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($airpay_admin);

        // Seed: 2 users in /1, 2 in /77.
        $this->seed_user('Bravo-A', '/1');
        $this->seed_user('Bravo-B', '/1');
        $this->seed_user('Bravo-C', '/77');
        $this->seed_user('Bravo-D', '/77');

        $result = \local_sentientia_users\external\search_supervisors::execute('Bravo', 0);

        // Should ONLY see the /1 users (including the admin themselves +
        // 2 seeded). NOT the /77 ones.
        $emails = array_column($result['rows'], 'email');
        foreach ($result['rows'] as $row) {
            $u = $DB->get_record('user', ['id' => $row['id']], 'open_path');
            $this->assertStringStartsWith('/1', $u->open_path,
                'Non-siteadmin must NEVER see /77 results: ' . $row['email']);
        }
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
