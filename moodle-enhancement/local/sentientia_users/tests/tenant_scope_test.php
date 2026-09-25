<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031: every user-management write checks the TARGET's tenant.
 *
 * Tenant admins hold a manager-archetype role at system context, and with it
 * :create, :edit and :view. Those say what a tenant admin may do; until
 * 2026-09-25 several paths also let them decide where:
 *  - an HRMS CSV row matched an existing account site-wide and overwrote it
 *    (password, tenant, name, suspension), so any tenant admin could take over
 *    another tenant's account or the site admin's;
 *  - the single-user suspend web service took any user id;
 *  - the edit form offered, and accepted, every tenant's organisations;
 *  - a caller whose open_path did not resolve was treated as unscoped by the
 *    HRMS importer, its run history, the KPI counts and the filter chips.
 *
 * @package    local_sentientia_users
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_users\hrms_importer
 * @covers \local_sentientia_users\user_manager
 * @covers \local_sentientia_users\external\suspend_user
 * @covers \local_sentientia_users\external\bulk_action
 * @covers \local_sentientia_users\external\list_filter_options
 * @covers \local_sentientia_users\form\edit_user
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var \stdClass tenant A root org (the caller's tenant) */
    private $orga;
    /** @var \stdClass a department under tenant A */
    private $orgachild;
    /** @var \stdClass tenant B root org (someone else's tenant) */
    private $orgb;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->orga = $this->make_org('TENANTA');
        $this->orgachild = $this->make_org('TENANTA_DEPT', $this->orga);
        $this->orgb = $this->make_org('TENANTB');
    }

    /** Insert an org node; its path is built from real ids, as in production. */
    private function make_org(string $shortname, ?\stdClass $parent = null): \stdClass {
        global $DB;
        $id = (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => $shortname . ' name', 'shortname' => $shortname,
            'parentid' => $parent ? (int) $parent->id : 0, 'path' => '',
            'depth' => $parent ? (int) $parent->depth + 1 : 1, 'visible' => 1,
            'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $path = ($parent ? $parent->path : '') . '/' . $id;
        $DB->set_field('local_sentientia_org', 'path', $path, ['id' => $id]);
        return $DB->get_record('local_sentientia_org', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * A user at $path, reloaded so the record carries open_path. Core fields
     * go through the generator; open_* (BizLMS) fields are set afterwards.
     */
    private function user_at(string $path, array $fields = []): \stdClass {
        global $DB;
        $open = array_filter($fields, fn($k) => str_starts_with($k, 'open_'), ARRAY_FILTER_USE_KEY);
        $u = $this->getDataGenerator()->create_user(array_diff_key($fields, $open));
        foreach (['open_path' => $path] + $open as $field => $value) {
            $DB->set_field('user', $field, $value, ['id' => $u->id]);
        }
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin as UAT has them: a manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $admin = $this->user_at($path);
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $admin->id, \context_system::instance()->id);
        return $admin;
    }

    /** A second site admin, sitting in tenant A (id 2 is hard-protected in places). */
    private function site_admin_in_a(): \stdClass {
        global $CFG;
        $admin = $this->user_at($this->orga->path);
        set_config('siteadmins', $CFG->siteadmins . ',' . $admin->id);
        return $admin;
    }

    /** One HRMS CSV (header + rows); unspecified columns are blank. */
    private function hrms_csv(array ...$rows): string {
        $lines = [implode(',', hrms_importer::STANDARD_COLUMNS)];
        foreach ($rows as $row) {
            $row += ['gender' => 'M', 'employee_status' => 'Active', 'language' => 'en'];
            $lines[] = implode(',', array_map(fn($c) => $row[$c] ?? '', hrms_importer::STANDARD_COLUMNS));
        }
        return implode("\n", $lines) . "\n";
    }

    /** The fields an account takeover would change. */
    private function snapshot(int $userid): array {
        global $DB;
        return (array) $DB->get_record('user', ['id' => $userid], 'password, open_path, suspended, firstname, email');
    }

    /** Assert $fn throws a moodle_exception carrying $errorcode. */
    private function assert_refused(callable $fn, string $errorcode, string $message): void {
        try {
            $fn();
            $this->fail($message . ' (no exception)');
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $message);
        }
    }

    // ── HRMS import ───────────────────────────────────────────────────────

    public function test_an_hrms_row_cannot_take_over_another_tenants_account(): void {
        global $DB;
        $caller = $this->tenant_admin($this->orga->path);
        $victim = $this->user_at($this->orgb->path,
            ['username' => 'victim', 'email' => 'victim@b.test', 'password' => 'Original#123']);
        $nopath = $this->user_at('', ['username' => 'drifter', 'email' => 'drifter@x.test']);
        $siteadmin = get_admin();
        $DB->set_field('user', 'open_path', $this->orga->path, ['id' => $siteadmin->id]);
        $before = [$victim->id => $this->snapshot((int) $victim->id),
            $nopath->id => $this->snapshot((int) $nopath->id),
            $siteadmin->id => $this->snapshot((int) $siteadmin->id)];
        $this->setUser($caller);

        // The caller's own company_code, so the row's destination passes;
        // each row matches an account the caller must not overwrite.
        $csv = $this->hrms_csv(
            ['company_code' => 'TENANTA', 'username' => 'victim', 'email' => 'victim@b.test',
                'employee_code' => 'EMPX1', 'first_name' => 'Mallory', 'last_name' => 'Evil',
                'password' => 'Attacker123', 'employee_status' => 'Inactive'],
            ['company_code' => 'TENANTA', 'username' => 'admin', 'email' => 'fresh@a.test',
                'employee_code' => 'EMPX2', 'first_name' => 'Mallory', 'last_name' => 'Evil',
                'password' => 'Attacker123'],
            ['company_code' => 'TENANTA', 'username' => 'drifter', 'email' => 'drifter@x.test',
                'employee_code' => 'EMPX3', 'first_name' => 'Mallory', 'last_name' => 'Evil',
                'password' => 'Attacker123']);
        $runid = hrms_importer::import_csv($csv, (int) $caller->id, 'takeover.csv');

        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame(3, (int) $run->errorcount);
        $this->assertSame(0, (int) $run->updatedcount);
        $this->assertSame(0, (int) $run->insertedcount);
        foreach ($before as $userid => $snapshot) {
            $this->assertSame($snapshot, $this->snapshot((int) $userid),
                "Account {$userid} must be left exactly as it was.");
        }
    }

    public function test_an_hrms_row_still_updates_a_colleague_in_the_callers_tenant(): void {
        global $DB;
        $caller = $this->tenant_admin($this->orga->path);
        $colleague = $this->user_at($this->orgachild->path,
            ['username' => 'colleague', 'email' => 'colleague@a.test']);
        $this->setUser($caller);

        $csv = $this->hrms_csv(['company_code' => 'TENANTA', 'username' => 'colleague',
            'email' => 'colleague@a.test', 'employee_code' => 'EMPA1',
            'first_name' => 'Renamed', 'last_name' => 'Colleague']);
        $runid = hrms_importer::import_csv($csv, (int) $caller->id);

        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame(1, (int) $run->updatedcount);
        $this->assertSame(0, (int) $run->errorcount);
        $this->assertSame('Renamed', $DB->get_field('user', 'firstname', ['id' => $colleague->id]));
    }

    public function test_an_hrms_import_by_a_caller_with_no_tenant_is_refused(): void {
        global $DB;
        foreach (['', 'garbage'] as $path) {
            $nobody = $this->tenant_admin($path);
            $this->setUser($nobody);
            $runsbefore = $DB->count_records('local_sentientia_users_sync_runs');
            $csv = $this->hrms_csv(['company_code' => 'TENANTB', 'username' => 'newbie' . strlen($path),
                'email' => 'newbie' . strlen($path) . '@b.test', 'employee_code' => 'EMPN' . strlen($path),
                'first_name' => 'New', 'last_name' => 'Bie']);
            $this->assert_refused(fn() => hrms_importer::import_csv($csv, (int) $nobody->id),
                'invalidtenant', "open_path '{$path}' must not import into any tenant.");
            $this->assertSame($runsbefore, $DB->count_records('local_sentientia_users_sync_runs'),
                'No run row is written for a refused caller.');
        }
    }

    public function test_the_site_admin_still_imports_across_tenants(): void {
        global $DB;
        $victim = $this->user_at($this->orgb->path, ['username' => 'bee', 'email' => 'bee@b.test']);
        $this->setAdminUser();

        $csv = $this->hrms_csv(['company_code' => 'TENANTB', 'username' => 'bee', 'email' => 'bee@b.test',
            'employee_code' => 'EMPB1', 'first_name' => 'Updated', 'last_name' => 'Bee']);
        $runid = hrms_importer::import_csv($csv, (int) get_admin()->id);

        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame(1, (int) $run->updatedcount);
        $this->assertSame('Updated', $DB->get_field('user', 'firstname', ['id' => $victim->id]));
    }

    // ── Suspend / bulk suspend ────────────────────────────────────────────

    public function test_a_tenant_admin_can_only_suspend_inside_their_tenant(): void {
        global $DB;
        $caller = $this->tenant_admin($this->orga->path);
        $colleague = $this->user_at($this->orgachild->path);
        $theirs = $this->user_at($this->orgb->path);
        $siteadmin = $this->site_admin_in_a();
        $this->setUser($caller);

        foreach ([(int) $theirs->id => 'another tenant\'s user', 999999 => 'a missing id',
                (int) $siteadmin->id => 'a site admin in the caller\'s own tenant'] as $id => $what) {
            $this->assert_refused(fn() => external\suspend_user::execute($id, true),
                'error_profilenotavailable', "No suspending {$what}; one refusal for all.");
        }
        $this->assert_refused(fn() => external\suspend_user::execute((int) $caller->id, true),
            'cannotsuspendself', 'No suspending yourself.');
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $theirs->id]));
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $siteadmin->id]));

        $r = external\suspend_user::execute((int) $colleague->id, true);
        $this->assertTrue($r['suspended'], 'The in-tenant function survives.');
        $this->assertSame(1, (int) $DB->get_field('user', 'suspended', ['id' => $colleague->id]));
    }

    public function test_a_caller_with_no_tenant_suspends_nobody_and_the_site_admin_anybody(): void {
        global $DB;
        $theirs = $this->user_at($this->orgb->path);
        $nobody = $this->tenant_admin('');
        $this->setUser($nobody);
        $this->assert_refused(fn() => external\suspend_user::execute((int) $theirs->id, true),
            'error_profilenotavailable', 'A caller with no tenant suspends nobody.');
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $theirs->id]));

        $this->setAdminUser();
        $this->assertTrue(external\suspend_user::execute((int) $theirs->id, true)['suspended']);
    }

    public function test_bulk_suspend_skips_site_admins_even_in_the_callers_tenant(): void {
        global $DB;
        $caller = $this->tenant_admin($this->orga->path);
        $colleague = $this->user_at($this->orgachild->path);
        $theirs = $this->user_at($this->orgb->path);
        $siteadmin = $this->site_admin_in_a();
        $this->setUser($caller);

        $r = external\bulk_action::execute('suspend',
            [(int) $colleague->id, (int) $theirs->id, (int) $siteadmin->id]);
        $this->assertSame(1, $r['count']);
        $this->assertSame(1, (int) $DB->get_field('user', 'suspended', ['id' => $colleague->id]));
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $theirs->id]));
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $siteadmin->id]));
    }

    // ── Edit form: which organisations ────────────────────────────────────

    public function test_the_edit_form_offers_and_accepts_only_the_callers_tenant(): void {
        $this->setUser($this->tenant_admin($this->orga->path));
        $options = form\edit_user::org_options_for_current_user();
        $this->assertArrayHasKey((int) $this->orga->id, $options);
        $this->assertArrayHasKey((int) $this->orgachild->id, $options);
        $this->assertArrayNotHasKey((int) $this->orgb->id, $options, 'Another tenant\'s org is not offered.');
        $this->assertNotNull(form\edit_user::org_choice_error((int) $this->orgb->id),
            'Nor accepted when posted anyway.');
        $this->assertNull(form\edit_user::org_choice_error((int) $this->orgachild->id));
        $this->assertNull(form\edit_user::org_choice_error(0));

        $this->setUser($this->tenant_admin(''));
        $this->assertSame([0], array_keys(form\edit_user::org_options_for_current_user()),
            'A caller with no tenant is offered no organisation.');
        $this->assertNotNull(form\edit_user::org_choice_error((int) $this->orgachild->id));

        $this->setAdminUser();
        $this->assertArrayHasKey((int) $this->orgb->id, form\edit_user::org_options_for_current_user());
        $this->assertNull(form\edit_user::org_choice_error((int) $this->orgb->id));
    }

    // ── Read side: filter chips ───────────────────────────────────────────

    public function test_filter_chip_values_are_bounded_to_the_tenant(): void {
        $this->user_at($this->orgachild->path, ['open_designation' => 'Engineer A']);
        $this->user_at($this->orgb->path, ['open_designation' => 'Engineer B']);

        $this->setUser($this->tenant_admin($this->orga->path));
        $values = external\list_filter_options::execute('open_designation')['designation'];
        $this->assertContains('Engineer A', $values);
        $this->assertNotContains('Engineer B', $values);

        $this->setUser($this->tenant_admin(''));
        foreach (external\list_filter_options::execute('') as $field => $list) {
            $this->assertSame([], $list, "A caller with no tenant gets no {$field} values.");
        }

        $this->setAdminUser();
        $values = external\list_filter_options::execute('open_designation')['designation'];
        $this->assertContains('Engineer A', $values);
        $this->assertContains('Engineer B', $values);
    }
}
