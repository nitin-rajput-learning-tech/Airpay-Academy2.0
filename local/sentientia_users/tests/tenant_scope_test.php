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
 *    HRMS importer, its run history, the KPI counts and the filter chips;
 *  - (follow-up) refusals told a tenant admin that an email, username or
 *    manager code exists in another tenant; the supervisor guard let a
 *    tenant-less supervisor or subordinate through; :crosstenant holders who
 *    are not site admins were locked out of other tenants' users; and the
 *    HRMS cron swallowed every importer failure as a success.
 *  - (second follow-up, 2026-09-25) photo.php let an :edit holder replace the
 *    picture of a site admin or a :crosstenant account in their tenant; the
 *    fail-closed supervisor guard also refused a re-save of the STORED
 *    supervisor (deleted, or with no tenant), and ran after user_update_user(),
 *    so a refusal left email, name and department saved and the rest lost.
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
 * @covers \local_sentientia_users\profile_access
 * @covers \local_sentientia_users\bulk_csv_processor
 * @covers \local_sentientia_users\external\list_users
 * @covers \local_sentientia_users\task\hrms_sync
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

    // ── ADR-031 follow-up (2026-09-25) ────────────────────────────────────

    /** A cross-tenant platform user: a tenant admin who ALSO holds :crosstenant. */
    private function cross_tenant_user(string $path): \stdClass {
        $u = $this->tenant_admin($path);
        $syscontext = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(\local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW,
            $roleid, $syscontext->id, true);
        role_assign($roleid, $u->id, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    /** User ids list_users returns to the current user (no filters). */
    private function listed_ids(): array {
        $rows = external\list_users::execute('', 'firstname', 'asc', 0, 100,
            json_encode(['status' => 'all']))['rows'];
        return array_map('intval', array_column($rows, 'id'));
    }

    public function test_the_airpay_tenant_admin_manages_only_slash_1_users(): void {
        global $DB;
        // The literal UAT tenants: Airpay /1, Public /77, ZEEA /177, and /10,
        // which a '/1%' prefix would wrongly admit.
        $admin = $this->tenant_admin('/1');
        $mine = $this->user_at('/1/2', ['open_designation' => 'Airpay role']);
        $zeea = $this->user_at('/177/178', ['open_designation' => 'ZEEA role']);
        $public = $this->user_at('/77');
        $trap = $this->user_at('/10', ['open_designation' => 'Trap role']);
        $this->setUser($admin);

        $ids = $this->listed_ids();
        $this->assertContains((int) $mine->id, $ids);
        foreach ([$zeea, $public, $trap] as $other) {
            $this->assertNotContains((int) $other->id, $ids, "{$other->open_path} must not be listed to /1.");
            $this->assertFalse(profile_access::can_view((int) $admin->id, (int) $other->id));
            $this->assert_refused(fn() => external\suspend_user::execute((int) $other->id, true),
                'error_profilenotavailable', "No suspending {$other->open_path} from /1.");
        }
        $this->assertSame(['Airpay role'],
            external\list_filter_options::execute('open_designation')['designation']);
        $r = external\bulk_action::execute('suspend', [(int) $mine->id, (int) $zeea->id, (int) $trap->id]);
        $this->assertSame(1, $r['count']);
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $zeea->id]));
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $trap->id]));
    }

    public function test_a_crosstenant_holder_manages_users_in_every_tenant(): void {
        $platform = $this->cross_tenant_user('/1');
        $zeea = $this->user_at('/177/178');
        $mine = $this->user_at('/1/2');
        $this->assertFalse(is_siteadmin($platform->id), 'Precondition: not a site admin.');

        // profile_access rule 2 is the ADR-031 authority, not is_siteadmin().
        $this->assertTrue(profile_access::can_view((int) $platform->id, (int) $zeea->id));
        $this->setUser($platform);
        // The edit form used to refuse them (require_can_view ran first).
        $form = new form\edit_user(null, null, 'post', '', [], true, ['userid' => (int) $zeea->id], true);
        $form->set_data_for_dynamic_submission();
        $ids = $this->listed_ids();
        $this->assertContains((int) $zeea->id, $ids, 'list_users is unscoped for :crosstenant.');
        $this->assertContains((int) $mine->id, $ids);

        // A plain tenant admin is still held to their own tenant.
        $tenantadmin = $this->tenant_admin('/1');
        $this->assertFalse(profile_access::can_view((int) $tenantadmin->id, (int) $zeea->id));
        $this->setUser($tenantadmin);
        $this->assertNotContains((int) $zeea->id, $this->listed_ids());
    }

    public function test_bulk_csv_answers_not_found_for_another_tenants_email(): void {
        global $DB;
        $caller = $this->tenant_admin('/1');
        $mine = $this->user_at('/1/2', ['email' => 'mine@a.test']);
        $zeea = $this->user_at('/177/178', ['email' => 'zeea@z.test']);
        $trap = $this->user_at('/10', ['email' => 'trap@t.test']);
        $siteadmin = get_admin();
        $DB->set_field('user', 'open_path', '/177', ['id' => $siteadmin->id]);
        $DB->set_field('user', 'email', 'siteadmin@z.test', ['id' => $siteadmin->id]);
        $siteadmin->email = 'siteadmin@z.test';
        $this->setUser($caller);

        $csv = "email,action\nmine@a.test,suspend\nzeea@z.test,suspend\ntrap@t.test,suspend\n"
            . "nobody@x.test,suspend\n" . $siteadmin->email . ",suspend\n";
        $r = bulk_csv_processor::process($csv, (int) $caller->id);

        $this->assertSame([(int) $mine->id], array_column($r['succeeded'], 'userid'));
        $reasons = array_column($r['skipped'], 'reason', 'email');
        foreach (['zeea@z.test', 'trap@t.test', 'nobody@x.test', $siteadmin->email] as $email) {
            $this->assertSame(bulk_csv_processor::NOT_FOUND_REASON, $reasons[$email] ?? null,
                "{$email}: out of tenant and missing must be the same answer.");
        }
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $zeea->id]));
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $trap->id]));
    }

    public function test_hrms_refusals_do_not_say_where_an_account_lives(): void {
        global $DB;
        $caller = $this->tenant_admin($this->orga->path);
        $this->user_at($this->orgb->path, ['username' => 'theirs', 'email' => 'theirs@b.test']);
        $this->user_at($this->orgb->path, ['open_employeeid' => 'MGRB']);
        $this->setUser($caller);

        $csv = $this->hrms_csv(
            // Matches tenant B's account: refused, generically.
            ['company_code' => 'TENANTA', 'username' => 'theirs', 'email' => 'theirs@b.test',
                'employee_code' => 'EMPG1', 'first_name' => 'Mallory', 'last_name' => 'Evil'],
            // New in-tenant users whose manager code exists only in tenant B, or nowhere.
            ['company_code' => 'TENANTA', 'username' => 'newa', 'email' => 'newa@a.test',
                'employee_code' => 'EMPG2', 'first_name' => 'New', 'last_name' => 'A',
                'reportingmanager_empid' => 'MGRB'],
            ['company_code' => 'TENANTA', 'username' => 'newb', 'email' => 'newb@a.test',
                'employee_code' => 'EMPG3', 'first_name' => 'New', 'last_name' => 'B',
                'reportingmanager_empid' => 'NOSUCH']);
        $runid = hrms_importer::import_csv($csv, (int) $caller->id, 'oracle.csv');

        $errors = array_values($DB->get_records('local_sentientia_users_sync_errors',
            ['runid' => $runid, 'severity' => 'error'], 'csv_line_number ASC'));
        $this->assertCount(1, $errors);
        $this->assertSame(hrms_importer::ROW_CONFLICT_ERROR, $errors[0]->error_message);
        $this->assertStringNotContainsStringIgnoringCase('tenant', $errors[0]->error_message);

        $warnings = array_values($DB->get_records('local_sentientia_users_sync_errors',
            ['runid' => $runid, 'severity' => 'warning'], 'csv_line_number ASC'));
        $this->assertCount(2, $warnings);
        $this->assertSame(str_replace('NOSUCH', 'CODE', $warnings[1]->error_message),
            str_replace('MGRB', 'CODE', $warnings[0]->error_message),
            'A manager code used only by another tenant reads exactly like one used by nobody.');
        $this->assertEmpty($DB->get_field('user', 'open_supervisorid', ['username' => 'newa']));
    }

    public function test_the_supervisor_guard_fails_closed_and_honours_crosstenant(): void {
        global $DB;
        $caller = $this->tenant_admin('/1');
        $sub = $this->user_at('/1/2');
        $colleague = $this->user_at('/1/3');
        $pathless = $this->user_at('');
        $zeea = $this->user_at('/177/178');
        $this->setUser($caller);

        foreach ([(int) $pathless->id => 'a supervisor with no tenant', (int) $zeea->id => 'a ZEEA supervisor',
                999999 => 'a missing supervisor id'] as $supid => $what) {
            try {
                user_manager::update((int) $sub->id, (object) ['open_supervisorid' => $supid]);
                $this->fail("Linking {$what} must be refused.");
            } catch (\moodle_exception $e) {
                $this->assertSame('supervisor_wrong_tenant', $e->errorcode, $what);
                $this->assertStringNotContainsString('177', $e->getMessage(),
                    'The refusal must not name the other tenant.');
            }
        }
        $nosub = $this->user_at('');
        $this->assert_refused(fn() => user_manager::update((int) $nosub->id,
            (object) ['open_supervisorid' => (int) $colleague->id]),
            'supervisor_wrong_tenant', 'A subordinate with no tenant cannot be linked either.');
        $this->assertEmpty($DB->get_field('user', 'open_supervisorid', ['id' => $sub->id]));

        // The in-tenant function survives.
        user_manager::update((int) $sub->id, (object) ['open_supervisorid' => (int) $colleague->id]);
        $this->assertSame((int) $colleague->id, (int) $DB->get_field('user', 'open_supervisorid', ['id' => $sub->id]));

        // A :crosstenant holder may link across tenants, like the site admin.
        $this->setUser($this->cross_tenant_user('/1'));
        user_manager::update((int) $sub->id, (object) ['open_supervisorid' => (int) $zeea->id]);
        $this->assertSame((int) $zeea->id, (int) $DB->get_field('user', 'open_supervisorid', ['id' => $sub->id]));
    }

    public function test_an_edit_that_keeps_a_stale_stored_supervisor_still_saves(): void {
        global $DB;
        $caller = $this->tenant_admin('/1');
        $gone = $this->user_at('/1/3');
        $pathless = $this->user_at('');
        $newboss = $this->user_at('/1/4');
        $this->setUser($caller);
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);  // the manager left

        foreach ([(int) $gone->id => 'a deleted supervisor', (int) $pathless->id => 'a supervisor with no tenant']
                as $staleid => $what) {
            $sub = $this->user_at('/1/2', ['open_supervisorid' => $staleid, 'open_designation' => 'Analyst']);

            // What the edit form posts: the stored supervisor, pre-filled, plus the real change.
            user_manager::update((int) $sub->id, (object) [
                'firstname' => 'Renamed', 'department' => 'Payments',
                'open_designation' => 'Senior Analyst', 'open_supervisorid' => $staleid,
            ]);
            $saved = $DB->get_record('user', ['id' => $sub->id],
                'id, firstname, department, open_designation, open_supervisorid');
            $this->assertSame('Renamed', $saved->firstname, "Re-saving {$what} must not block the edit.");
            $this->assertSame('Payments', $saved->department);
            $this->assertSame('Senior Analyst', $saved->open_designation, "The open_* fields are saved too ({$what}).");
            $this->assertSame($staleid, (int) $saved->open_supervisorid, 'The stored link is left as it was.');

            // Choosing it again is not new; choosing anyone new is checked as before.
            $this->assert_refused(fn() => user_manager::update((int) $sub->id,
                (object) ['open_supervisorid' => (int) $this->user_at('/177/178')->id]),
                'supervisor_wrong_tenant', "A NEW cross-tenant supervisor is still refused ({$what}).");
            user_manager::update((int) $sub->id, (object) ['open_supervisorid' => (int) $newboss->id]);
            $this->assertSame((int) $newboss->id, (int) $DB->get_field('user', 'open_supervisorid', ['id' => $sub->id]));

            // Once replaced, the stale id is a new choice again, and refused.
            $this->assert_refused(fn() => user_manager::update((int) $sub->id,
                (object) ['open_supervisorid' => $staleid]),
                'supervisor_wrong_tenant', "Switching back to {$what} is a new choice.");
        }
    }

    public function test_a_refused_supervisor_saves_nothing(): void {
        global $DB;
        $caller = $this->tenant_admin('/1');
        $zeea = $this->user_at('/177/178');
        $sub = $this->user_at('/1/2', ['firstname' => 'Before', 'department' => 'Ops',
            'open_designation' => 'Analyst']);
        $this->setUser($caller);
        $fields = 'id, firstname, email, department, password, open_designation, open_supervisorid, open_path';
        $before = $DB->get_record('user', ['id' => $sub->id], $fields);

        $this->assert_refused(fn() => user_manager::update((int) $sub->id, (object) [
            'firstname' => 'After', 'email' => 'after-' . $sub->id . '@a.test', 'department' => 'Payments',
            'open_designation' => 'Lead', 'open_supervisorid' => (int) $zeea->id,
            'newpassword' => 'Changed-Pa55word!',
        ]), 'supervisor_wrong_tenant', 'A cross-tenant supervisor is refused.');
        $this->assertEquals($before, $DB->get_record('user', ['id' => $sub->id], $fields),
            'The refusal must come before ANY write: no half-saved name, email, department or password.');

        // create() refuses before the account exists, so no half-made account is left.
        $this->assert_refused(fn() => user_manager::create((object) [
            'username' => 'halfmade', 'email' => 'halfmade@a.test', 'firstname' => 'Half', 'lastname' => 'Made',
            'open_path' => '/1/2', 'open_supervisorid' => (int) $zeea->id,
        ]), 'supervisor_wrong_tenant', 'create() refuses a cross-tenant supervisor.');
        $this->assertFalse($DB->record_exists('user', ['username' => 'halfmade']),
            'A refused create leaves no account behind.');
    }

    // ── Profile photo ─────────────────────────────────────────────────────

    public function test_a_tenant_admin_cannot_change_the_photo_of_a_site_admin_or_crosstenant_account(): void {
        $caller = $this->tenant_admin($this->orga->path);
        $siteadmin = $this->site_admin_in_a();
        $platform = $this->cross_tenant_user($this->orga->path);
        $colleague = $this->user_at($this->orgachild->path);
        $theirs = $this->user_at($this->orgb->path);
        $this->setUser($caller);
        $this->assertTrue(has_capability('local/sentientia_users:edit', \context_system::instance()),
            'Precondition: a tenant admin holds :edit.');

        foreach ([[$siteadmin, 'a site admin'], [$platform, 'a :crosstenant account']] as [$target, $what]) {
            $this->assertTrue(profile_access::can_view((int) $caller->id, (int) $target->id),
                "Precondition: {$what} sits in the caller's tenant, so the tenant rule alone lets it through.");
            $this->assert_refused(fn() => user_manager::require_can_change_photo((int) $target->id),
                profile_access::ERROR_STRING, "No replacing the photo of {$what}.");
        }
        $this->assert_refused(fn() => user_manager::require_can_change_photo((int) $theirs->id),
            profile_access::ERROR_STRING, 'Nor of another tenant\'s user.');

        // The in-tenant function survives, and so does your own photo.
        user_manager::require_can_change_photo((int) $colleague->id);
        user_manager::require_can_change_photo((int) $caller->id);

        // Without :edit: your own photo only.
        $learner = $this->user_at($this->orga->path);
        $this->setUser($learner);
        user_manager::require_can_change_photo((int) $learner->id);
        $this->assert_refused(fn() => user_manager::require_can_change_photo((int) $colleague->id),
            'nopermissions', 'A learner cannot change a colleague\'s photo.');

        // A :crosstenant holder may change anyone's but a site admin's; a site admin anyone's.
        $this->setUser($platform);
        user_manager::require_can_change_photo((int) $theirs->id);
        $this->assert_refused(fn() => user_manager::require_can_change_photo((int) $siteadmin->id),
            profile_access::ERROR_STRING, 'Nobody but a site admin acts on a site admin.');
        $this->setUser($siteadmin);
        foreach ([$platform, $theirs, $colleague, $caller] as $target) {
            user_manager::require_can_change_photo((int) $target->id);
        }
    }

    public function test_hrms_sync_swallows_only_the_tenant_refusal(): void {
        $file = make_request_directory() . DIRECTORY_SEPARATOR . 'hrms.csv';
        file_put_contents($file, $this->hrms_csv(['company_code' => 'TENANTA', 'username' => 'cronny',
            'email' => 'cronny@a.test', 'employee_code' => 'EMPC1', 'first_name' => 'Cron', 'last_name' => 'Ny']));
        set_config('hrms_sync_mode', 'filesystem', 'local_sentientia_users');
        set_config('hrms_sync_path', realpath($file), 'local_sentientia_users');
        set_config('hrms_sync_user_id', (int) get_admin()->id, 'local_sentientia_users');
        unset_config('hrms_sync_last_run', 'local_sentientia_users');

        // A task whose importer fails with $e.
        $failing = fn(\moodle_exception $e) => new class($e) extends task\hrms_sync {
            /** @var \moodle_exception */
            private $failure;
            public function __construct(\moodle_exception $failure) {
                $this->failure = $failure;
            }
            protected function import(string $csv, int $runner_userid, string $filename): int {
                throw $this->failure;
            }
        };
        $run = function(task\hrms_sync $task): string {
            ob_start();
            try {
                $task->execute();
            } finally {
                $output = ob_get_clean();
            }
            return $output;
        };

        // The tenant refusal: logged, and the task ends normally.
        $output = $run($failing(new \moodle_exception('invalidtenant', 'local_sentientia_users')));
        $this->assertStringContainsString('refused for runner user', $output);
        $this->assertFalse(get_config('local_sentientia_users', 'hrms_sync_last_run'),
            'A refused run is not recorded as a success.');

        // Anything else fails the task, so the task API reports and retries it.
        foreach ([new \dml_read_exception('connection lost'),
                new \moodle_exception('error_csv_header_missing', 'local_sentientia_users')] as $failure) {
            try {
                $run($failing($failure));
                $this->fail(get_class($failure) . ' ' . $failure->errorcode . ' must not be swallowed.');
            } catch (\moodle_exception $e) {
                $this->assertSame($failure, $e);
            }
            $this->assertFalse(get_config('local_sentientia_users', 'hrms_sync_last_run'));
        }
    }
}
