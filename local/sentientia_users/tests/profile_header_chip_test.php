<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * Surface walk 2026-11-18: the profile header chip read "Employee" for a public-tenant learner (over a grid of N/A
 * employee fields), for a tenant admin and for a line manager.
 *
 * - user_manager::profile_type_provider(): an account with no user_type row in the self-signup tenant (default /77)
 *   is a consumer; a row always wins; anyone else keeps the factory default (employee).
 * - user_manager::profile_role_labels(): non-learner system roles (plain text) and "Line manager" for a user with
 *   direct reports.
 *
 * @package    local_sentientia_users
 * @category   test
 * @covers     \local_sentientia_users\user_manager::profile_type_provider
 * @covers     \local_sentientia_users\user_manager::profile_role_labels
 * @covers     \local_sentientia_users\signup_service::signup_tenant_root
 *
 * @group local_sentientia_users
 */
final class profile_header_chip_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!class_exists(\local_sentientia_platform\user_type_factory::class)) {
            $this->markTestSkipped('local_sentientia_platform is not installed.');
        }
        \local_sentientia_platform\user_type_factory::clear_cache();
    }

    /** A user record carrying an open_path (the property is read from the record; no BizLMS column is needed). */
    private function user_in(string $openpath): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $user->open_path = $openpath;
        return $user;
    }

    public function test_signup_tenant_root_defaults_to_public_and_follows_the_setting(): void {
        $this->assertSame(77, signup_service::signup_tenant_root());
        set_config(signup_service::SETTING_TENANT_PATH, '/9/4', 'local_sentientia_users');
        $this->assertSame(9, signup_service::signup_tenant_root());
    }

    public function test_public_tenant_user_without_a_row_is_a_consumer(): void {
        $this->assertSame('consumer', user_manager::profile_type_provider($this->user_in('/77'))::type_id());
        $this->assertSame('consumer', user_manager::profile_type_provider($this->user_in('/77/12'))::type_id());
    }

    public function test_other_tenants_without_a_row_keep_the_factory_default(): void {
        $this->assertSame('employee', user_manager::profile_type_provider($this->user_in('/1'))::type_id());
        $this->assertSame('employee', user_manager::profile_type_provider($this->user_in('/177'))::type_id());
        // Prefix, not a path segment: /770 is not the public tenant.
        $this->assertSame('employee', user_manager::profile_type_provider($this->user_in('/770'))::type_id());
        $this->assertSame('employee', user_manager::profile_type_provider($this->user_in(''))::type_id());
        // A site admin is never made a consumer, even in the public tenant.
        $admin = get_admin();
        $admin->open_path = '/77';
        $this->assertSame('employee', user_manager::profile_type_provider($admin)::type_id());
    }

    public function test_a_user_type_row_always_wins(): void {
        global $DB;
        $user = $this->user_in('/77');
        $now = time();
        $DB->insert_record('local_sentientia_user_type', (object) [
            'userid' => $user->id, 'user_type' => 'employee', 'provisioning_source' => 'test',
            'provisioned_at' => $now, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        \local_sentientia_platform\user_type_factory::clear_cache();
        $this->assertSame('employee', user_manager::profile_type_provider($user)::type_id());
    }

    public function test_role_labels_skip_learner_roles_and_keep_names_plain(): void {
        $syscontext = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame([], user_manager::profile_role_labels((int) $user->id), 'No role, no label.');

        $learnerrole = $this->getDataGenerator()->create_role(['shortname' => 'chipemployee', 'name' => 'Employee',
            'archetype' => 'student']);
        role_assign($learnerrole, $user->id, $syscontext->id);
        $this->assertSame([], user_manager::profile_role_labels((int) $user->id), 'A learner role is not shown.');

        $adminrole = $this->getDataGenerator()->create_role(['shortname' => 'chipadmin', 'name' => 'Tenant & Admin',
            'archetype' => 'manager']);
        role_assign($adminrole, $user->id, $syscontext->id);
        $this->assertSame(['Tenant & Admin'], user_manager::profile_role_labels((int) $user->id),
            'An admin role is shown once, as plain text (the template escapes it).');
    }

    public function test_a_user_with_direct_reports_is_a_line_manager(): void {
        global $DB;
        if (!class_exists(\local_sentientia_core\org::class)) {
            $this->markTestSkipped('local_sentientia_core is not installed.');
        }
        set_config('org_legacy', 0, 'local_sentientia_core');
        $manager = $this->getDataGenerator()->create_user();
        $report = $this->getDataGenerator()->create_user();
        $unitid = (int) $DB->insert_record('local_sentientia_org_unit', (object) [
            'parentid' => 0, 'tenantrootid' => 1, 'name' => 'Unit', 'status' => 'active',
            'timecreated' => 1, 'timemodified' => 1,
        ]);
        $DB->insert_record('local_sentientia_org_member', (object) [
            'userid' => $report->id, 'unitid' => $unitid, 'role' => 'member', 'managerid' => $manager->id,
            'timecreated' => 1, 'timemodified' => 1,
        ]);

        $this->assertSame([get_string('profile_role_linemanager', 'local_sentientia_users')],
            user_manager::profile_role_labels((int) $manager->id));
        $this->assertSame([], user_manager::profile_role_labels((int) $report->id));
    }
}
