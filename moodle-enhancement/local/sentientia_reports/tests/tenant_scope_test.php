<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_reports;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: saved reports are run, exported, edited and listed only inside
 * the caller's tenant; "All organisations" reports are cross-tenant only.
 *
 * Tenant admins hold :view / :export / :manage through a manager-archetype
 * role at system context. Until 2026-09-25 run.php and export.php ran ANY
 * report id - an "All organisations" report returned every tenant's users
 * (names, emails, employee ids) - the edit form offered every tenant's orgs,
 * and list_reports' org cascade replaced the tenant filter.
 *
 * @package    local_sentientia_reports
 * @category   test
 * @covers     \local_sentientia_reports\report_manager
 * @covers     \local_sentientia_reports\external\list_reports
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(string $path, string $email): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user(['email' => $email]);
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A user at $path holding the manager role at system context (a tenant admin). */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path, 'admin' . random_string(6) . '@example.com');
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function org(string $path, string $name): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname'     => $name,
            'parentid'     => 0,
            'path'         => $path,
            'depth'        => substr_count(trim($path, '/'), '/') + 1,
            'visible'      => 1,
            'sortorder'    => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    private function report(?string $path, string $name = 'Report'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_reports', (object) [
            'name'         => $name,
            'report_type'  => 'user_activity',
            'costcenterid' => 0,
            'open_path'    => $path,
            'status'       => 1,
            'created_by'   => 2,
            'runcount'     => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    private function assert_refused(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    private function emails(array $result): array {
        return array_column($result['rows'], 'email');
    }

    public function test_tenant_admin_runs_only_their_tenants_reports(): void {
        $this->user_at('/1/5', 'airpay.learner@example.com');
        $this->user_at('/177/178', 'zeea.learner@example.com');
        $allorgs = $this->report(null, 'All organisations');
        $zeea = $this->report('/177', 'ZEEA report');
        $own = $this->report('/1', 'Airpay report');
        $this->setUser($this->tenant_admin('/1'));

        foreach ([$allorgs, $zeea] as $reportid) {
            $this->assert_refused(fn() => report_manager::run_report($reportid),
                'run.php / export.php must refuse an all-organisations or other-tenant report.');
            $this->assert_refused(fn() => report_manager::require_report_access(report_manager::get($reportid)),
                'The page-level guard must refuse it too.');
        }

        $emails = $this->emails(report_manager::run_report($own));
        $this->assertContains('airpay.learner@example.com', $emails);
        $this->assertNotContains('zeea.learner@example.com', $emails);
    }

    public function test_caller_with_no_tenant_gets_nothing(): void {
        $own = $this->report('/1', 'Airpay report');
        $this->report(null, 'All organisations');
        $this->setUser($this->tenant_admin(''));

        $this->assert_refused(fn() => report_manager::run_report($own),
            'A caller whose tenant does not resolve must not run any report.');
        $this->assertSame(0, report_manager::count_reports());
        $this->assertSame(0, external\list_reports::execute()['total']);
    }

    public function test_site_admin_still_runs_all_organisations_reports(): void {
        $this->user_at('/1/5', 'airpay.learner@example.com');
        $this->user_at('/177/178', 'zeea.learner@example.com');
        $allorgs = $this->report(null, 'All organisations');
        $this->setAdminUser();

        $emails = $this->emails(report_manager::run_report($allorgs));
        $this->assertContains('airpay.learner@example.com', $emails);
        $this->assertContains('zeea.learner@example.com', $emails);
    }

    public function test_org_cascade_narrows_rather_than_replaces_the_tenant_scope(): void {
        $zeeaorg = $this->org('/177', 'ZEEA');
        $ownorg = $this->org('/1', 'Airpay');
        $this->report('/177', 'ZEEA report');
        $own = $this->report('/1', 'Airpay report');
        $this->setUser($this->tenant_admin('/1'));

        $foreign = external\list_reports::execute('', 'name', 'asc', 0, 25, json_encode(['org_l1' => $zeeaorg]));
        $this->assertSame(0, $foreign['total'], 'An org filter must not reach into another tenant.');

        $mine = external\list_reports::execute('', 'name', 'asc', 0, 25, json_encode(['org_l1' => $ownorg]));
        $this->assertSame([$own], array_column($mine['rows'], 'id'));
        $this->assertSame(1, report_manager::count_reports());
    }

    public function test_tenant_admin_cannot_save_reports_outside_their_tenant(): void {
        global $DB;
        $zeeaorg = $this->org('/177', 'ZEEA');
        $ownorg = $this->org('/1/5', 'Airpay sales');
        $zeea = $this->report('/177', 'ZEEA report');
        $this->setUser($this->tenant_admin('/1'));

        foreach ([0, $zeeaorg] as $orgid) {
            $this->assert_refused(fn() => report_manager::create((object) [
                'name' => 'Sneaky', 'report_type' => 'user_activity', 'costcenterid' => $orgid]),
                'A scoped caller must not create an all-organisations or other-tenant report.');
        }
        $this->assert_refused(fn() => report_manager::update($zeea, (object) ['name' => 'Renamed']),
            'A scoped caller must not edit another tenant\'s report.');
        $this->assertSame('ZEEA report', $DB->get_field('local_sentientia_reports', 'name', ['id' => $zeea]));

        $id = report_manager::create((object) [
            'name' => 'Own', 'report_type' => 'user_activity', 'costcenterid' => $ownorg]);
        $this->assertSame('/1/5', $DB->get_field('local_sentientia_reports', 'open_path', ['id' => $id]));
        $this->assert_refused(fn() => report_manager::update($id, (object) ['costcenterid' => $zeeaorg]),
            'Re-scoping an own report into another tenant must be refused.');
    }

    public function test_site_admin_can_still_create_all_organisations_reports(): void {
        global $DB;
        $this->setAdminUser();
        $id = report_manager::create((object) ['name' => 'All', 'report_type' => 'user_activity', 'costcenterid' => 0]);
        $this->assertNull($DB->get_field('local_sentientia_reports', 'open_path', ['id' => $id]));
    }
}
