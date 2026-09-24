<?php
// This file is part of Sentientia LMS.

/**
 * Line managers see their reporting tree only (2026-09-24).
 *
 * Before this, a line manager - admitted to the compliance report only because
 * somebody reports to them - was scoped to their whole tenant, and could read
 * the compliance status of every employee in the company. The rule now: a
 * manager sees their direct reports and everyone below them, inside their own
 * tenant, and nobody else. Tenant admins, report viewers and export holders
 * keep the whole tenant; site admins keep everything.
 *
 * @package    local_sentientia_compliance_report
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_compliance_report;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_compliance_report\viewer_scope
 * @covers \local_sentientia_compliance_report\compliance_engine
 * @group tenant_isolation
 */
final class team_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var \stdClass[] The org chart, by name. */
    private $p = [];

    private function person(string $name, string $path, ?string $reportsto = null, bool $deleted = false): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user(['firstname' => $name]);
        $fields = ['open_path' => $path];
        if ($reportsto !== null) {
            $fields['open_supervisorid'] = $this->p[$reportsto]->id;
        }
        if ($deleted) {
            $fields['deleted'] = 1;
        }
        foreach ($fields as $f => $v) {
            $DB->set_field('user', $f, $v, ['id' => $u->id]);
        }
        $u = $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
        $this->p[$name] = $u;
        return $u;
    }

    /** An org unit with a fixed id, filling any NOT NULL column the schema adds. */
    private function org(int $id, string $name, int $parentid, string $path, int $depth): void {
        global $DB;
        $rec = ['id' => $id, 'fullname' => $name, 'shortname' => 'ORG' . $id,
                'parentid' => $parentid, 'path' => $path, 'depth' => $depth, 'visible' => 1];
        foreach ($DB->get_columns('local_sentientia_org') as $col) {
            if (isset($rec[$col->name]) || !$col->not_null || $col->has_default) {
                continue;
            }
            $rec[$col->name] = ($col->meta_type === 'C' || $col->meta_type === 'X') ? '' : 0;
        }
        $DB->insert_record_raw('local_sentientia_org', (object) $rec, true, false, true);
    }

    private function ids(string ...$names): array {
        $ids = array_map(fn($n) => (int) $this->p[$n]->id, $names);
        sort($ids);
        return $ids;
    }

    private function sorted(array $ids): array {
        $ids = array_map('intval', $ids);
        sort($ids);
        return $ids;
    }

    /** Grant a capability to a user through a fresh role at a context. */
    private function grant(string $name, string $capability, \context $context): void {
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $this->p[$name]->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Tenant 1 (orgs: 1 Airpay > 2 Dept 2 > {3 Dept 3, 4 Dept 4}; 1 > 5 Dept 5):
     *
     *   CEO (/1)
     *    ├ L2 (/1/2) ─┬ M1 (/1/2/3) ─┬ E1, E2
     *    │            │              └ Dm (deleted) ── E4
     *    │            └ M2 (/1/2/4) ── E3
     *    ├ Peer (/1/2)
     *    └ Other (/1/5)
     *
     * Tenant 177 (orgs 177 > 178): Zed, whose open_supervisorid points at L2 by mistake.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->org(1, 'Airpay', 0, '/1', 1);
        $this->org(2, 'Dept 2', 1, '/1/2', 2);
        $this->org(3, 'Dept 3', 2, '/1/2/3', 3);
        $this->org(4, 'Dept 4', 2, '/1/2/4', 3);
        $this->org(5, 'Dept 5', 1, '/1/5', 2);
        $this->org(177, 'ZEEA', 0, '/177', 1);
        $this->org(178, 'ZEEA Ops', 177, '/177/178', 2);

        $this->person('CEO', '/1');
        $this->person('L2', '/1/2', 'CEO');
        $this->person('Peer', '/1/2', 'CEO');
        $this->person('Other', '/1/5', 'CEO');
        $this->person('M1', '/1/2/3', 'L2');
        $this->person('M2', '/1/2/4', 'L2');
        $this->person('E1', '/1/2/3', 'M1');
        $this->person('E2', '/1/2/3', 'M1');
        $this->person('Dm', '/1/2/3', 'M1', true);
        $this->person('E4', '/1/2/3', 'Dm');
        $this->person('E3', '/1/2/4', 'M2');
        $this->person('Zed', '/177/178', 'L2');
    }

    /** One overdue assignment per active person (and a second for E1). */
    private function seed_snapshot(): void {
        global $DB;
        foreach ([5001 => 'POSH', 5002 => 'InfoSec'] as $courseid => $name) {
            $DB->insert_record('local_compliance_courses', (object) [
                'courseid' => $courseid, 'coursename' => $name, 'costcenterid' => 0, 'is_active' => 1,
                'sort_order' => $courseid, 'deadline_days' => 30, 'createdby' => 0,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $snap = function (\stdClass $u, int $courseid) use ($DB) {
            $DB->insert_record('local_compliance_snapshot', (object) [
                'userid' => $u->id, 'courseid' => $courseid, 'department_path' => $u->open_path,
                'status' => 'overdue', 'progress_percent' => 0, 'days_overdue' => 3,
                'matched_by' => 'userid', 'snapshot_date' => time(),
            ]);
        };
        foreach ($this->p as $u) {
            if (empty($u->deleted)) {
                $snap($u, 5001);
            }
        }
        $snap($this->p['E1'], 5002);
    }

    // ── The tree ─────────────────────────────────────────────────────────────

    public function test_the_tree_is_direct_reports_and_their_extended_teams(): void {
        $tree = compliance_engine::get_reporting_tree((int) $this->p['L2']->id, '/1');

        $this->assertSame($this->ids('M1', 'M2', 'E1', 'E2', 'E3', 'E4'), $this->sorted($tree),
            'Direct reports, their reports, and E4 - whose manager was deleted - but not the '
            . 'manager, a peer, the CEO, the deleted user, or anyone in another tenant.');
    }

    public function test_a_first_line_manager_sees_only_their_own_reports(): void {
        $this->assertSame($this->ids('E3'),
            $this->sorted(compliance_engine::get_reporting_tree((int) $this->p['M2']->id, '/1')));
    }

    public function test_a_reporting_cycle_terminates(): void {
        global $DB;
        // L2 now "reports to" E1, who is below L2: a loop.
        $DB->set_field('user', 'open_supervisorid', $this->p['E1']->id, ['id' => $this->p['L2']->id]);

        $tree = compliance_engine::get_reporting_tree((int) $this->p['L2']->id, '/1');

        $this->assertSame($this->ids('M1', 'M2', 'E1', 'E2', 'E3', 'E4'), $this->sorted($tree),
            'The walk must stop at people already seen, and never list the manager.');
    }

    public function test_no_tenant_means_no_tree(): void {
        // path_descendant_filter('') is '1=1'; an empty path must never reach it.
        $this->assertSame([], compliance_engine::get_reporting_tree((int) $this->p['L2']->id, ''));
        $this->assertSame([], compliance_engine::get_reporting_tree((int) $this->p['L2']->id, '/'));
        $this->assertSame([], compliance_engine::get_reporting_tree(0, '/1'));
    }

    // ── Who gets which scope ─────────────────────────────────────────────────

    public function test_a_line_manager_is_scoped_to_their_tree(): void {
        $scope = viewer_scope::for_user($this->p['L2']);

        $this->assertNotNull($scope);
        $this->assertSame(viewer_scope::LEVEL_TEAM, $scope->level);
        $this->assertSame('/1', $scope->orgpath);
        $this->assertSame($this->ids('M1', 'M2', 'E1', 'E2', 'E3', 'E4'), $this->sorted($scope->userids));
        $this->assertFalse($scope->is_admin_level());
        $this->assertFalse($scope->can_configure());
    }

    public function test_a_report_viewer_keeps_tenant_scope(): void {
        $this->grant('Peer', 'moodle/site:viewreports', \context_system::instance());

        $scope = viewer_scope::for_user($this->p['Peer']);

        $this->assertSame(viewer_scope::LEVEL_TENANT, $scope->level);
        $this->assertSame('/1', $scope->orgpath);
        $this->assertNull($scope->userids);
        $this->assertFalse($scope->can_configure(), 'Configuration is site-admin only.');
    }

    public function test_a_compliance_admin_keeps_tenant_scope(): void {
        global $DB;
        // The BizLMS compliance role (id 9) at a category, as UAT's tenant
        // admins hold it. Inserted directly: create_role() cannot promise id 9,
        // and the check reads role_assignments.roleid without joining {role}.
        $category = $this->getDataGenerator()->create_category();
        $DB->insert_record('role_assignments', (object) [
            'roleid' => 9, 'contextid' => \context_coursecat::instance($category->id)->id,
            'userid' => $this->p['E1']->id, 'timemodified' => time(), 'modifierid' => 0,
            'component' => '', 'itemid' => 0, 'sortorder' => 0,
        ]);

        $scope = viewer_scope::for_user($this->p['E1']);

        $this->assertSame(viewer_scope::LEVEL_TENANT, $scope->level);
        $this->assertNull($scope->userids);
    }

    public function test_an_admission_to_the_tenant_outranks_managing_people(): void {
        // L2 manages people AND can view site reports: the tenant view wins, as
        // before the change.
        $this->grant('L2', 'moodle/site:viewreports', \context_system::instance());
        $this->assertSame(viewer_scope::LEVEL_TENANT, viewer_scope::for_user($this->p['L2'])->level);

        // M1 manages people AND may export (a category-level role): whoever may
        // download the tenant sees it on screen too.
        $category = $this->getDataGenerator()->create_category();
        $this->grant('M1', permission::EXPORT_CAPABILITY, \context_coursecat::instance($category->id));
        $this->assertSame(viewer_scope::LEVEL_TENANT, viewer_scope::for_user($this->p['M1'])->level);
    }

    public function test_the_site_admin_is_unscoped(): void {
        $scope = viewer_scope::for_user(get_admin());
        $this->assertSame(viewer_scope::LEVEL_SITE, $scope->level);
        $this->assertSame('', $scope->orgpath);
        $this->assertNull($scope->userids);
        $this->assertTrue($scope->can_configure());
    }

    public function test_nobody_else_gets_in_and_is_told_why(): void {
        global $DB;
        $this->assertNull(viewer_scope::for_user($this->p['E1']), 'A learner with no reports.');
        $this->assertSame(viewer_scope::REFUSED_NO_ACCESS, viewer_scope::refusal_reason($this->p['E1']));
        $this->assertNull(viewer_scope::for_user(guest_user()));

        // A manager whose tenant cannot be resolved must not become '' (= the whole site).
        $DB->set_field('user', 'open_path', '', ['id' => $this->p['M2']->id]);
        $m2 = $DB->get_record('user', ['id' => $this->p['M2']->id]);
        $this->assertNull(viewer_scope::for_user($m2));
        $this->assertSame(viewer_scope::REFUSED_NO_TENANT, viewer_scope::refusal_reason($m2));
    }

    public function test_a_tenant_viewer_with_no_tenant_is_refused(): void {
        global $DB;
        // The riskier case: a TENANT-level viewer would otherwise get '' - every tenant.
        $this->grant('Peer', 'moodle/site:viewreports', \context_system::instance());
        foreach (['', '/', 'garbage'] as $path) {
            $DB->set_field('user', 'open_path', $path, ['id' => $this->p['Peer']->id]);
            $peer = $DB->get_record('user', ['id' => $this->p['Peer']->id]);
            $this->assertNull(viewer_scope::for_user($peer), "open_path '{$path}' must not unlock the site.");
            $this->assertSame(viewer_scope::REFUSED_NO_TENANT, viewer_scope::refusal_reason($peer));
        }
    }

    public function test_cache_keys_follow_the_population(): void {
        $m1 = viewer_scope::for_user($this->p['M1']);
        $m2 = viewer_scope::for_user($this->p['M2']);
        $this->assertNotSame($m1->kpi_cache_key('/1'), $m2->kpi_cache_key('/1'),
            'Two managers in one tenant must not share cached figures.');
        $this->assertSame($m1->kpi_cache_key('/1'), viewer_scope::for_user($this->p['M1'])->kpi_cache_key('/1'));
        $this->assertNotSame($m1->kpi_cache_key('/1'), viewer_scope::for_user(get_admin())->kpi_cache_key('/1'));
    }

    // ── The drill-down clamp ─────────────────────────────────────────────────

    public function test_the_drill_down_is_checked_at_every_level(): void {
        // Own tenant, real parent/child chain: kept.
        $this->assertSame([1, 2, 3], compliance_engine::clamp_filter_to_tenant('/1', 1, 2, 3));
        // Another tenant's department under my BU: cut back to the BU.
        $this->assertSame([1, 0, 0], compliance_engine::clamp_filter_to_tenant('/1', 1, 178, 0));
        // A department with no BU chosen (?dept=2 alone) used to list its children.
        $this->assertSame([0, 0, 0], compliance_engine::clamp_filter_to_tenant('/77', 0, 2, 0));
        // A sub-department that is not a child of the department.
        $this->assertSame([1, 2, 0], compliance_engine::clamp_filter_to_tenant('/1', 1, 2, 5));
        // An org id that does not exist.
        $this->assertSame([1, 0, 0], compliance_engine::clamp_filter_to_tenant('/1', 1, 99999, 0));
        // Site admins are not clamped.
        $this->assertSame([177, 178, 0], compliance_engine::clamp_filter_to_tenant('', 177, 178, 0));
    }

    // ── Every query honours the team ─────────────────────────────────────────

    public function test_every_report_query_honours_the_team(): void {
        $this->seed_snapshot();
        $team = viewer_scope::for_user($this->p['L2'])->userids;
        $teamids = $this->sorted($team);

        $matrix = compliance_engine::get_compliance_matrix('/1', 0, 50, $team);
        $this->assertSame($teamids, $this->sorted(array_column($matrix['rows'], 'userid')));
        $this->assertEquals(count($teamids), $matrix['total']);

        // 6 people, 7 assignments (E1 has two).
        $this->assertEquals(7, compliance_engine::get_summary_kpis('/1', $team)['total']);

        // One row per overdue ASSIGNMENT: E1 appears twice.
        $defaulters = compliance_engine::get_defaulters('/1', 100, $team);
        $this->assertCount(7, $defaulters);
        $this->assertSame($teamids, $this->sorted(array_unique(array_column($defaulters, 'userid'))));

        // Unrestricted (null) still means the whole tenant (10 people, 11
        // assignments); [] means nobody.
        $this->assertEquals(11, compliance_engine::get_summary_kpis('/1')['total']);
        $this->assertEquals(0, compliance_engine::get_summary_kpis('/1', [])['total']);
        $this->assertSame([], compliance_engine::get_compliance_matrix('/1', 0, 50, [])['rows']);
    }

    public function test_the_scorecard_lists_only_the_teams_departments(): void {
        $this->seed_snapshot();
        $team = viewer_scope::for_user($this->p['L2'])->userids;

        $rows = compliance_engine::get_department_scorecard('/1', $team);
        $totals = array_column($rows, 'total', 'department');
        ksort($totals);

        // Dept 5 has nobody from the team, so it is not listed.
        $this->assertSame(['Dept 2' => 7, 'Dept 3' => 5, 'Dept 4' => 2], $totals);
    }

    public function test_the_manager_report_keeps_a_left_managers_team(): void {
        $this->seed_snapshot();
        $team = viewer_scope::for_user($this->p['L2'])->userids;

        // L2 (for M1, M2), M1 (for E1, E2), M2 (for E3) - and Dm, who left, for
        // E4: E4 is in every other tab, so must be in this one too.
        $report = compliance_engine::get_manager_report('/1', $team);
        $this->assertSame($this->ids('L2', 'M1', 'M2', 'Dm'), $this->sorted(array_column($report, 'managerid')));
        $left = array_values(array_filter($report, fn($r) => !empty($r->mgr_deleted)));
        $this->assertCount(1, $left);
        $this->assertEquals($this->p['Dm']->id, $left[0]->managerid);

        // The tenant view is unchanged: a manager who left is not listed.
        $tenant = compliance_engine::get_manager_report('/1');
        $this->assertNotContains((int) $this->p['Dm']->id, array_map('intval', array_column($tenant, 'managerid')));
    }

    public function test_dropdown_headcounts_are_the_teams(): void {
        $team = viewer_scope::for_user($this->p['L2'])->userids;

        $bus = compliance_engine::get_org_hierarchy_level(1, '/1', $team);
        $this->assertSame([1], array_column($bus, 'id'));
        $this->assertSame(6, $bus[0]['user_count'], 'Not the tenant\'s 10 active people.');

        $depts = array_column(compliance_engine::get_org_hierarchy_children(1, $team), 'user_count', 'id');
        $this->assertSame(6, $depts[2]);
        $this->assertSame(0, $depts[5]);

        // Tenant view: every active person in /1 (CEO, L2, Peer, Other, M1, M2, E1, E2, E3, E4).
        $this->assertSame(10, compliance_engine::get_org_hierarchy_level(1, '/1')[0]['user_count']);
    }
}
