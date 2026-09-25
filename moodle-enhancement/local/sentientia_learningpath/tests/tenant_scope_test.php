<?php
// This file is part of Sentientia LMS.

/**
 * ADR-031: learning paths, their rosters and their courses stay inside the
 * caller's tenant (2026-09-25).
 *
 * :view, :enrol, :update and :create default to the manager archetype, and
 * tenant admins hold a manager-archetype role at system context. Every
 * pathid-keyed web service checked only the capability, so a tenant admin
 * could read any tenant's path roster (names, emails, employee ids) and
 * enrol, unenrol or restructure any tenant's paths; list_paths let a
 * client-chosen org replace the tenant filter; and a caller whose open_path
 * did not resolve got every tenant's users in the enrol picker and audience.
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_learningpath\external\assign_courses;
use local_sentientia_learningpath\external\enrol_users;
use local_sentientia_learningpath\external\list_path_courses;
use local_sentientia_learningpath\external\list_path_users;
use local_sentientia_learningpath\external\list_paths;
use local_sentientia_learningpath\external\reorder_courses;
use local_sentientia_learningpath\external\toggle_status;
use local_sentientia_learningpath\external\unassign_course;
use local_sentientia_learningpath\external\unenrol_user;

/**
 * @covers \local_sentientia_learningpath\path_manager
 * @covers \local_sentientia_learningpath\path_audience_enroller
 * @covers \local_sentientia_learningpath\external\list_paths
 * @covers \local_sentientia_learningpath\external\list_path_users
 * @covers \local_sentientia_learningpath\external\enrol_users
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(?string $path, string $designation = 'tsdesig'): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $DB->set_field('user', 'open_designation', $designation, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A manager-archetype role at system context, as UAT's tenant admins hold. */
    private function tenant_admin(?string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path, 'admin');
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        return $u;
    }

    private function learning_path(?string $openpath, string $name = 'Path'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => $name, 'description' => '', 'costcenterid' => 0, 'open_path' => $openpath,
            'status' => path_manager::STATUS_ACTIVE, 'visible' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function course_at(?string $openpath): int {
        global $DB;
        $c = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $openpath, ['id' => $c->id]);
        return (int) $c->id;
    }

    private function put_course_on_path(int $pathid, int $courseid): void {
        global $DB;
        $DB->insert_record('local_sentientia_learningpath_courses', (object) [
            'pathid' => $pathid, 'courseid' => $courseid, 'sortorder' => 0, 'mandatory' => 1,
            'timecreated' => time(),
        ]);
    }

    private function put_user_on_path(int $pathid, int $userid): void {
        global $DB;
        $DB->insert_record('local_sentientia_learningpath_users', (object) [
            'pathid' => $pathid, 'userid' => $userid, 'status' => 0, 'timecreated' => time(),
        ]);
    }

    private function org(string $path): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => 'Org ' . $path, 'parentid' => 0, 'path' => $path,
            'depth' => substr_count($path, '/'), 'visible' => 1, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function assert_refused(callable $call, string $what): void {
        try {
            $call();
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, "{$what}: refused for the wrong reason.");
            return;
        }
        $this->fail("{$what}: another tenant's path, user or course was not refused.");
    }

    public function test_view_is_not_granted_to_student_archetype_roles(): void {
        global $DB;
        foreach ($DB->get_records('role', ['archetype' => 'student']) as $role) {
            $this->assertFalse($DB->record_exists('role_capabilities', [
                'roleid' => $role->id, 'capability' => 'local/sentientia_learningpath:view',
            ]), "Role {$role->shortname} (student archetype) must not hold :view - it lists rosters with PII.");
        }
    }

    public function test_a_tenant_admin_cannot_touch_another_tenants_path_by_id(): void {
        global $DB;
        $theirs = $this->learning_path('/177/178', 'ZEEA path');
        $course = $this->course_at('/177');
        $this->put_course_on_path($theirs, $course);
        $learner = $this->user_at('/177/178');
        $this->put_user_on_path($theirs, (int) $learner->id);
        $mine = $this->user_at('/1/2');
        $owncourse = $this->course_at('/1');
        $admin = $this->tenant_admin('/1');

        $this->setUser($admin);

        $this->assert_refused(fn() => list_path_users::execute($theirs), 'list_path_users');
        $this->assert_refused(fn() => list_path_courses::execute($theirs), 'list_path_courses');
        $this->assert_refused(fn() => enrol_users::execute($theirs, [(int) $mine->id]), 'enrol_users');
        $this->assert_refused(fn() => unenrol_user::execute($theirs, (int) $learner->id), 'unenrol_user');
        $this->assert_refused(fn() => toggle_status::execute($theirs, false), 'toggle_status');
        $this->assert_refused(fn() => assign_courses::execute($theirs, [$owncourse]), 'assign_courses');
        $this->assert_refused(fn() => unassign_course::execute($theirs, $course), 'unassign_course');
        $this->assert_refused(fn() => reorder_courses::execute($theirs, [$course]), 'reorder_courses');
        $this->assert_refused(fn() => path_manager::require_path_tenant($theirs), 'view.php / exportcsv guard');
        $this->assert_refused(fn() => path_audience_enroller::enrol_by_filter($theirs,
            ['designation' => 'tsdesig'], (int) $admin->id), 'bulk_enrol_by_audience');
        $this->assert_refused(fn() => new form\edit_path(null, null, 'post', '', [], true,
            ['pathid' => $theirs], true), 'edit_path form');
        $this->assert_refused(fn() => new form\enrol_users_form(null, null, 'post', '', [], true,
            ['pathid' => $theirs], true), 'enrol_users_form');

        // Nothing was written.
        $this->assertEquals(path_manager::STATUS_ACTIVE,
            $DB->get_field('local_sentientia_learningpath', 'status', ['id' => $theirs]));
        $this->assertSame(1, $DB->count_records('local_sentientia_learningpath_users', ['pathid' => $theirs]));
        $this->assertSame(1, $DB->count_records('local_sentientia_learningpath_courses', ['pathid' => $theirs]));
    }

    public function test_writes_on_an_own_path_cannot_name_another_tenants_user_or_course(): void {
        global $DB;
        $mine = $this->learning_path('/1', 'Airpay path');
        $foreignuser = $this->user_at('/177');
        $foreigncourse = $this->course_at('/177');

        $this->setUser($this->tenant_admin('/1'));

        $this->assert_refused(fn() => enrol_users::execute($mine, [(int) $foreignuser->id]), 'enrol foreign user');
        $this->assert_refused(fn() => unenrol_user::execute($mine, (int) $foreignuser->id), 'unenrol foreign user');
        $this->assert_refused(fn() => assign_courses::execute($mine, [$foreigncourse]), 'assign foreign course');
        $this->assertSame(0, $DB->count_records('local_sentientia_learningpath_users', ['pathid' => $mine]));
        $this->assertSame(0, $DB->count_records('local_sentientia_learningpath_courses', ['pathid' => $mine]));
    }

    public function test_inside_their_own_tenant_a_tenant_admin_works_as_before(): void {
        global $DB;
        $mine = $this->learning_path('/1/5', 'Airpay path');
        $learner = $this->user_at('/1/5');
        $owncourse = $this->course_at('/1/5');
        $legacycourse = $this->course_at(null);

        $this->setUser($this->tenant_admin('/1'));

        $this->assertSame(2, (int) assign_courses::execute($mine, [$owncourse, $legacycourse])['inserted'],
            'Own-tenant and legacy unpathed courses stay assignable.');
        $this->assertSame(1, (int) enrol_users::execute($mine, [(int) $learner->id])['enrolled']);
        $this->assertSame(1, (int) list_path_users::execute($mine)['total']);
        toggle_status::execute($mine, false);
        $this->assertEquals(path_manager::STATUS_ARCHIVED,
            $DB->get_field('local_sentientia_learningpath', 'status', ['id' => $mine]));
    }

    public function test_the_org_cascade_cannot_widen_the_list_to_another_tenant(): void {
        $this->learning_path('/1', 'Airpay path');
        $this->learning_path('/177', 'ZEEA path');
        $zeeaorg = $this->org('/177');

        $this->setUser($this->tenant_admin('/1'));
        $r = list_paths::execute('', 'name', 'asc', 0, 25, json_encode(['org_l1' => $zeeaorg]));
        $this->assertSame(0, (int) $r['total'], 'filters.org_l1 must narrow the tenant scope, never replace it.');
        $this->assertSame(1, (int) list_paths::execute('', 'name', 'asc', 0, 25, '{}')['total']);
    }

    public function test_a_scoped_caller_cannot_file_a_path_under_another_tenants_org(): void {
        $zeeaorg = $this->org('/177');
        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame('/1', path_manager::org_path_for_caller(0));
        $this->assert_refused(fn() => path_manager::org_path_for_caller($zeeaorg), 'org_path_for_caller');
    }

    public function test_a_caller_with_no_tenant_gets_nothing(): void {
        $this->learning_path('/1', 'Airpay path');
        $theirs = $this->learning_path('/177', 'ZEEA path');
        $this->user_at('/1');
        $this->user_at('/177');

        foreach (['', 'garbage'] as $path) {
            $nobody = $this->tenant_admin($path);
            $this->setUser($nobody);
            $this->assertSame(0, (int) list_paths::execute('', 'name', 'asc', 0, 25, '{}')['total'],
                "open_path '{$path}' must not list every tenant.");
            $this->assert_refused(fn() => path_manager::require_path_tenant($theirs), "open_path '{$path}' view.php");
            $this->assertSame([], path_audience_enroller::resolve_audience(
                ['designation' => 'tsdesig'], (int) $nobody->id),
                "open_path '{$path}' must not resolve an audience across every tenant.");
            $this->assert_refused(fn() => path_manager::require_users_in_scope([(int) $nobody->id]),
                "open_path '{$path}' enrol");
        }
    }

    public function test_a_path_with_no_org_is_site_admin_only(): void {
        $pathless = $this->learning_path(null, 'Unowned');
        $this->setUser($this->tenant_admin('/1'));
        $this->assert_refused(fn() => list_path_users::execute($pathless), 'pathless path');

        $this->setAdminUser();
        $this->assertSame(0, (int) list_path_users::execute($pathless)['total']);
    }

    /**
     * An own path whose roster also holds a learner from another tenant and
     * one with no open_path - as a site admin, a request/approval flow or the
     * pre-ADR-031 fail-open could leave it.
     *
     * @return array{0: int, 1: \stdClass, 2: \stdClass, 3: \stdClass} path, own, foreign, pathless
     */
    private function mixed_roster(): array {
        $mine = $this->learning_path('/1', 'Airpay path');
        $own = $this->user_at('/1/2');
        $foreign = $this->user_at('/177/178');
        $pathless = $this->user_at(null);
        foreach ([$own, $foreign, $pathless] as $u) {
            $this->put_user_on_path($mine, (int) $u->id);
        }
        return [$mine, $own, $foreign, $pathless];
    }

    /** The user ids exportcsv.php mode=path_users would write for the current user. */
    private function export_userids(int $pathid): array {
        global $DB;
        [$rosql, $roparams] = path_manager::roster_scope(true, 'u');
        $ids = array_map('intval', $DB->get_fieldset_sql(
            "SELECT u.id
               FROM {local_sentientia_learningpath_users} lpu
               JOIN {user} u ON u.id = lpu.userid
              WHERE lpu.pathid = :pid AND u.deleted = 0 AND $rosql", ['pid' => $pathid] + $roparams));
        sort($ids);
        return $ids;
    }

    public function test_the_roster_of_an_own_path_lists_only_the_callers_tenant(): void {
        [$mine, $own, $foreign, $pathless] = $this->mixed_roster();

        $this->setUser($this->tenant_admin('/1'));
        $r = list_path_users::execute($mine);
        $this->assertSame(1, (int) $r['total'], 'The roster total counts only the caller\'s tenant.');
        $this->assertSame([(int) $own->id], array_map(fn($row) => (int) $row['id'], $r['rows']),
            'No other tenant\'s (or pathless) name, email, employee id or designation.');
        $this->assertSame([(int) $own->id], $this->export_userids($mine), 'exportcsv.php mode=path_users');

        $this->setUser($this->tenant_admin('garbage'));
        $this->assertSame(0, (int) path_manager::get_path_users($mine, '', 0, 25, true)['total']);
        $this->assertSame([], $this->export_userids($mine));

        // Cross-tenant callers, and library callers with no user, see everyone.
        $all = [(int) $own->id, (int) $foreign->id, (int) $pathless->id];
        sort($all);
        $this->setAdminUser();
        $this->assertSame(3, (int) list_path_users::execute($mine)['total']);
        $this->assertSame($all, $this->export_userids($mine));
        $this->setUser(null);
        $this->assertSame(3, (int) path_manager::get_path_users($mine)['total']);
    }

    public function test_a_tenant_admin_can_remove_a_legacy_learner_from_their_own_path(): void {
        global $DB;
        [$mine, $own, $foreign, $pathless] = $this->mixed_roster();
        $stranger = $this->user_at('/177');

        $this->setUser($this->tenant_admin('/1'));
        $this->assertTrue(unenrol_user::execute($mine, (int) $foreign->id)['removed'],
            'Removing someone from your own path reaches into no other tenant.');
        $this->assertTrue(unenrol_user::execute($mine, (int) $pathless->id)['removed']);
        $this->assert_refused(fn() => unenrol_user::execute($mine, (int) $stranger->id),
            'unenrol of a foreign user who is not on the roster');
        $this->assertSame([(int) $own->id], array_map('intval', $DB->get_fieldset_select(
            'local_sentientia_learningpath_users', 'userid', 'pathid = :p', ['p' => $mine])));
    }

    public function test_the_site_admin_still_sees_every_tenant(): void {
        $a = $this->learning_path('/1', 'A');
        $z = $this->learning_path('/177', 'Z');
        $zlearner = $this->user_at('/177');
        $this->put_user_on_path($z, (int) $zlearner->id);
        $mine = $this->user_at('/1/2');

        $this->setAdminUser();
        $this->assertSame(2, (int) list_paths::execute('', 'name', 'asc', 0, 25, '{}')['total']);
        $this->assertSame(1, (int) list_path_users::execute($z)['total']);
        $this->assertSame($a, (int) path_manager::require_path_tenant($a)->id);
        $this->assertSame(1, (int) enrol_users::execute($z, [(int) $mine->id])['enrolled'],
            'A cross-tenant caller may still enrol across tenants.');

        $all = path_audience_enroller::resolve_audience(['designation' => 'tsdesig'], (int) get_admin()->id);
        $this->assertContains((int) $zlearner->id, $all);
        $this->assertContains((int) $mine->id, $all);
    }
}
