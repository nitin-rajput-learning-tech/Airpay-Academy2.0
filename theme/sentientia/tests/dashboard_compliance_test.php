<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

use theme_sentientia\local\dashboard_compliance;
use theme_sentientia\local\tenant_scope;

/**
 * The admin dashboard's Compliance Overview shows the compliance report's figures.
 *
 * Surface walk 2026-11-18 (B5/B8): the dashboard said 72% while the report said 71% for the same
 * viewer. The dashboard now takes the report engine's KPI row (through the report's own KPI cache)
 * and rounds the rate once from the counts, as the report's index.php does. These tests pin:
 *
 *   - the rounding (once, from completed / total);
 *   - the tenant boundary ('/1' never counts '/177' rows) and the active-course count;
 *   - that a KPI row the report has cached is the one the dashboard shows, and that a row the
 *     dashboard computes is cached under the report's key;
 *   - that an L&D admin whose tenant cannot be resolved sees zeros, not the whole site.
 *
 * @covers \theme_sentientia\local\dashboard_compliance
 * @package    theme_sentientia
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      theme_sentientia
 * @group      tenant_isolation
 */
final class dashboard_compliance_test extends \advanced_testcase {

    /** @var string the report's engine */
    private const ENGINE = '\\local_sentientia_compliance_report\\compliance_engine';

    /** @var string the report's viewer scope */
    private const VIEWER_SCOPE = '\\local_sentientia_compliance_report\\viewer_scope';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!dashboard_compliance::available()) {
            $this->markTestSkipped('local_sentientia_compliance_report is not installed.');
        }
    }

    /**
     * A mandatory course row.
     *
     * @param int $courseid
     * @param int $costcenterid 0 = every tenant
     * @param int $active
     */
    private function add_mandatory_course(int $courseid, int $costcenterid, int $active = 1): void {
        global $DB;
        $DB->insert_record('local_compliance_courses', (object) [
            'courseid' => $courseid,
            'coursename' => 'Mandatory ' . $courseid,
            'deadline_days' => 30,
            'costcenterid' => $costcenterid,
            'is_active' => $active,
            'sort_order' => $courseid,
            'createdby' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Snapshot rows (one per assignment) in a department.
     *
     * @param string $deptpath
     * @param string $status
     * @param int $count
     */
    private function add_snapshots(string $deptpath, string $status, int $count): void {
        global $DB;
        static $userid = 1000;
        for ($i = 0; $i < $count; $i++) {
            $DB->insert_record('local_compliance_snapshot', (object) [
                'userid' => ++$userid,
                'courseid' => 101,
                'costcenterid' => (int) explode('/', trim($deptpath, '/'))[0],
                'department_path' => $deptpath,
                'status' => $status,
                'progress_percent' => 0,
                'days_overdue' => 0,
                'snapshot_date' => time(),
            ]);
        }
    }

    /**
     * Two tenants with assignments, and mandatory courses for each.
     *
     * Tenant 1: 7 assignments, 5 completed, 1 overdue (71.43%). Tenant 177: 3, 1, 2.
     * Active courses: one global, one for tenant 1, one for tenant 177; one inactive for tenant 1.
     */
    private function seed(): void {
        $this->add_mandatory_course(101, 0);
        $this->add_mandatory_course(102, 1);
        $this->add_mandatory_course(103, 177);
        $this->add_mandatory_course(104, 1, 0);

        $this->add_snapshots('/1', 'completed', 2);
        $this->add_snapshots('/1/5', 'completed', 3);
        $this->add_snapshots('/1/5/9', 'overdue', 1);
        $this->add_snapshots('/1/5', 'in_progress', 1);

        // '/177' starts with '/1': the boundary must keep it out of tenant 1.
        $this->add_snapshots('/177', 'completed', 1);
        $this->add_snapshots('/177/3', 'overdue', 2);
    }

    /**
     * An L&D-admin-like viewer of tenant 1, whom the report scopes to that tenant.
     *
     * @return \stdClass
     */
    private function tenant_viewer(): \stdClass {
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontext = \context_system::instance();
        assign_capability('moodle/site:viewreports', CAP_ALLOW, $roleid, $syscontext->id, true);
        role_assign($roleid, $viewer->id, $syscontext->id);
        // Only the object carries the tenant: neither scope class reads the column.
        $viewer->open_path = '/1/5';
        return $viewer;
    }

    /**
     * The report's KPI cache (local/sentientia_compliance_report/index.php).
     *
     * @return \cache
     */
    private function report_cache(): \cache {
        return \cache::make_from_params(\cache_store::MODE_APPLICATION,
            'local_sentientia_compliance_report', 'dashboard');
    }

    /**
     * Rounded once from the counts. 1429 of 2000 is 71.45%: the engine's one-decimal rate is 71.5,
     * which rounded again would print 72.
     */
    public function test_rate_is_rounded_once_from_the_counts(): void {
        $this->assertSame(71, dashboard_compliance::rate(1429, 2000));
        $this->assertSame(71, dashboard_compliance::rate(5, 7));
        $this->assertSame(72, dashboard_compliance::rate(143, 200));
        $this->assertSame(100, dashboard_compliance::rate(3, 3));
        $this->assertSame(0, dashboard_compliance::rate(0, 0));
    }

    /**
     * An L&D admin of tenant 1 sees tenant 1's figures, the same ones the report computes.
     */
    public function test_tenant_admin_sees_the_report_figures_for_their_tenant(): void {
        $this->seed();
        $viewer = $this->tenant_viewer();
        $scope = tenant_scope::for_user($viewer, false);
        $this->assertSame('/1', $scope->root());

        $summary = dashboard_compliance::summary($viewer, $scope);

        $this->assertSame(['mandatory' => 2, 'overdue' => 1, 'total' => 7, 'rate' => 71], $summary);

        // The report's own arithmetic over the report's own row.
        $engine = self::ENGINE;
        $kpis = $engine::get_summary_kpis('/1');
        $reportrate = ((int) $kpis['total']) > 0
            ? (int) round(((int) $kpis['completed']) / ((int) $kpis['total']) * 100)
            : 0;
        $this->assertSame($reportrate, $summary['rate']);
        $this->assertSame((int) $kpis['overdue'], $summary['overdue']);
        $this->assertSame((int) $kpis['total'], $summary['total']);
    }

    /**
     * The row the dashboard computes is cached under the key the report reads for this viewer.
     */
    public function test_computed_row_is_cached_under_the_report_key(): void {
        $this->seed();
        $viewer = $this->tenant_viewer();
        $viewerscope = self::VIEWER_SCOPE;
        $reportscope = $viewerscope::for_user($viewer);
        $this->assertNotNull($reportscope);
        $this->assertSame('/1', $reportscope->orgpath);
        $key = $reportscope->kpi_cache_key('/1');
        $this->assertFalse($this->report_cache()->get($key));

        dashboard_compliance::summary($viewer, tenant_scope::for_user($viewer, false));

        $engine = self::ENGINE;
        $this->assertEquals($engine::get_summary_kpis('/1'), $this->report_cache()->get($key));
    }

    /**
     * A KPI row the report has cached is the one the dashboard shows, even when the snapshot has
     * changed since (the report serves its cached row until caches are purged).
     */
    public function test_site_admin_sees_the_row_the_report_cached(): void {
        global $USER;
        $this->seed();
        $this->setAdminUser();
        $viewerscope = self::VIEWER_SCOPE;
        $key = $viewerscope::for_user($USER)->kpi_cache_key('');
        $this->report_cache()->set($key, ['total' => 20, 'completed' => 15, 'overdue' => 4]);

        $summary = dashboard_compliance::summary($USER, tenant_scope::for_user($USER, true));

        // Mandatory courses are not part of the cached row: every active course, all tenants.
        $this->assertSame(['mandatory' => 3, 'overdue' => 4, 'total' => 20, 'rate' => 75], $summary);
    }

    /**
     * Without a cached row a site admin sees every tenant: 10 assignments, 6 completed, 3 overdue.
     */
    public function test_site_admin_sees_every_tenant(): void {
        global $USER;
        $this->seed();
        $this->setAdminUser();

        $summary = dashboard_compliance::summary($USER, tenant_scope::for_user($USER, true));

        $this->assertSame(['mandatory' => 3, 'overdue' => 3, 'total' => 10, 'rate' => 60], $summary);
    }

    /**
     * An L&D admin whose tenant cannot be resolved sees zeros, not the whole site (the engine reads
     * an empty path as every tenant).
     */
    public function test_unresolved_tenant_fails_closed(): void {
        $this->seed();
        $viewer = $this->tenant_viewer();
        $viewer->open_path = '';
        $scope = tenant_scope::for_user($viewer, false);
        $this->assertTrue($scope->is_unresolved());

        $summary = dashboard_compliance::summary($viewer, $scope);

        $this->assertSame(['mandatory' => 0, 'overdue' => 0, 'total' => 0, 'rate' => 0], $summary);
    }
}
