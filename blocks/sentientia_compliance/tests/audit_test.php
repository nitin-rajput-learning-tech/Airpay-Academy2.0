<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace block_sentientia_compliance;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031 (2026-09-25): the compliance block and its audit export stay inside
 * the viewer's tenant.
 *
 * Until this date the block's matrix counted every tenant's mandatory courses
 * for any holder of core moodle/site:viewreports (every tenant admin, every
 * trainer) and for any user with a direct report, and export.php streamed
 * every tenant's employees - employee id, name, email, per-course status - to
 * any holder of local/sentientia_courses:manage.
 *
 * @package    block_sentientia_compliance
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_sentientia_compliance\audit
 * @group      tenant_isolation
 */
final class audit_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int manager-archetype role, as UAT's tenant-admin role 9 */
    private $tenantadminrole;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->tenantadminrole = (int) $this->getDataGenerator()->create_role([
            'shortname' => 'adr031cmpadmin', 'archetype' => 'manager']);
    }

    private function user_at(string $path, int $supervisorid = 0): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        if ($supervisorid > 0) {
            $DB->set_field('user', 'open_supervisorid', $supervisorid, ['id' => $u->id]);
        }
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function tenant_admin(string $path): \stdClass {
        $u = $this->user_at($path);
        role_assign($this->tenantadminrole, $u->id, \context_system::instance()->id);
        return $u;
    }

    /** A mandatory (deadline) course at $path. */
    private function mandatory_course(?string $path): \stdClass {
        global $DB;
        $c = $this->getDataGenerator()->create_course([
            'visible' => 1, 'startdate' => time() - DAYSECS, 'enddate' => time() + 30 * DAYSECS]);
        $DB->set_field('course', 'open_path', $path, ['id' => $c->id]);
        return $c;
    }

    private function enrol(\stdClass $u, \stdClass $c, bool $completed = false): void {
        global $DB;
        $this->getDataGenerator()->enrol_user($u->id, $c->id, 'student', 'manual');
        if ($completed) {
            $DB->insert_record('course_completions', (object) [
                'userid' => $u->id, 'course' => $c->id, 'timeenrolled' => time(),
                'timestarted' => time(), 'timecompleted' => time(), 'reaggregate' => 0]);
        }
    }

    private function stats_by_course(array $stats): array {
        $out = [];
        foreach ($stats as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    public function test_viewer_scope_levels(): void {
        $admin = $this->tenant_admin('/1');
        $this->assertSame(['path' => '/1', 'userids' => null], audit::viewer_scope($admin),
            'a manager-archetype tenant admin sees their own tenant, not every tenant');

        foreach (['', 'garbage'] as $path) {
            $this->assertNull(audit::viewer_scope($this->tenant_admin($path)),
                "open_path '{$path}' must fall back to the learner view, never the site-wide matrix");
        }

        $manager = $this->user_at('/1');
        $report = $this->user_at('/1/2', (int) $manager->id);
        $this->user_at('/177', (int) $manager->id);  // mis-keyed supervisor in another tenant
        $scope = audit::viewer_scope($manager);
        $this->assertSame('/1', $scope['path']);
        $this->assertSame([(int) $report->id], $scope['userids'],
            'a line manager sees their own team inside their tenant');

        $this->assertNull(audit::viewer_scope($this->user_at('/1')), 'a plain learner sees their own status');
        $this->assertSame(['path' => '', 'userids' => null], audit::viewer_scope(get_admin()));
    }

    public function test_tenant_matrix_counts_only_the_tenants_people_and_courses(): void {
        $own = $this->mandatory_course('/1');
        $zeea = $this->mandatory_course('/177');
        $legacy = $this->mandatory_course(null);
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177');
        $this->enrol($mine, $own, true);
        $this->enrol($theirs, $zeea, true);
        $this->enrol($mine, $legacy);
        $this->enrol($theirs, $legacy, true);

        $this->setUser($this->tenant_admin('/1'));
        $stats = $this->stats_by_course(audit::course_stats('/1', null));
        $this->assertArrayNotHasKey((int) $zeea->id, $stats, 'no /177 course name in a /1 matrix');
        $this->assertSame(1, $stats[(int) $own->id]['enrolled']);
        $this->assertSame(1, $stats[(int) $own->id]['completed']);
        $this->assertSame(1, $stats[(int) $legacy->id]['enrolled'], 'the /177 learner is not counted');
        $this->assertSame(0, $stats[(int) $legacy->id]['completed']);

        // '' means every tenant and is for cross-tenant viewers only.
        $this->assertSame([], audit::course_stats('', null));
    }

    public function test_team_matrix_counts_only_the_team(): void {
        $own = $this->mandatory_course('/1');
        $manager = $this->user_at('/1');
        $report = $this->user_at('/1/2', (int) $manager->id);
        $peer = $this->user_at('/1/2');
        $this->enrol($report, $own);
        $this->enrol($peer, $own, true);

        $this->setUser($manager);
        $scope = audit::viewer_scope($manager);
        $stats = $this->stats_by_course(audit::course_stats($scope['path'], $scope['userids']));
        $this->assertSame(1, $stats[(int) $own->id]['enrolled']);
        $this->assertSame(0, $stats[(int) $own->id]['completed'], 'the peer outside the team is not counted');
    }

    public function test_site_admin_matrix_is_unchanged(): void {
        $own = $this->mandatory_course('/1');
        $zeea = $this->mandatory_course('/177');
        $this->enrol($this->user_at('/1'), $own);
        $this->enrol($this->user_at('/177'), $zeea);

        $this->setAdminUser();
        $stats = $this->stats_by_course(audit::course_stats('', null));
        $this->assertSame(1, $stats[(int) $own->id]['enrolled']);
        $this->assertSame(1, $stats[(int) $zeea->id]['enrolled']);
    }

    public function test_audit_export_people_are_the_callers_tenant(): void {
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177');

        $this->setUser($this->tenant_admin('/1'));
        $path = audit::export_scope_path();
        $this->assertSame('/1', $path);
        $ids = array_map('intval', array_keys(audit::export_users($path)));
        $this->assertContains((int) $mine->id, $ids);
        $this->assertNotContains((int) $theirs->id, $ids, 'no /177 employee in a /1 export');

        $this->setUser($this->tenant_admin(''));
        try {
            audit::export_scope_path();
            $this->fail('a caller with no tenant must be refused, not handed every tenant');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $this->assertSame([], audit::export_users(''));

        $this->setAdminUser();
        $this->assertSame('', audit::export_scope_path());
        $ids = array_map('intval', array_keys(audit::export_users('')));
        $this->assertContains((int) $theirs->id, $ids);
    }
}
