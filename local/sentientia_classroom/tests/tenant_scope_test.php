<?php
// This file is part of Sentientia LMS.

/**
 * ADR-031: classrooms, sessions, rosters and attendance stay inside the
 * caller's tenant (2026-09-25).
 *
 * :view, :update, :attendance and :create default to the manager archetype,
 * and tenant admins hold a manager-archetype role at system context. Every
 * web service checked only the capability and then acted on whatever
 * classroomid/sessionid it was sent, so a tenant admin could read and rewrite
 * every tenant's classrooms; list_classrooms let a client-chosen org replace
 * the tenant filter; and a caller whose open_path did not resolve skipped the
 * page and picker checks altogether.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_classroom\external\bulk_mark_attendance;
use local_sentientia_classroom\external\change_status;
use local_sentientia_classroom\external\delete_session;
use local_sentientia_classroom\external\list_classroom_sessions;
use local_sentientia_classroom\external\list_classroom_users;
use local_sentientia_classroom\external\list_classrooms;
use local_sentientia_classroom\external\list_session_attendance;
use local_sentientia_classroom\external\list_waitlist;
use local_sentientia_classroom\external\mark_session_attendance;
use local_sentientia_classroom\external\unenrol_classroom_user;
use local_sentientia_classroom\external\waitlist_join;

/**
 * @covers \local_sentientia_classroom\session_manager
 * @covers \local_sentientia_classroom\classroom_audience_enroller
 * @covers \local_sentientia_classroom\external\list_classrooms
 * @covers \local_sentientia_classroom\external\list_classroom_users
 * @covers \local_sentientia_classroom\external\mark_session_attendance
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

    private function classroom(?string $path, string $name = 'Classroom'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => $name, 'description' => '', 'costcenterid' => 0, 'open_path' => $path,
            'location' => 'Room', 'capacity' => 20, 'status' => session_manager::STATUS_ACTIVE,
            'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function session(int $classroomid): int {
        $start = time() + DAYSECS;
        return session_manager::create_session($classroomid, (object) [
            'title' => 'S1', 'starttime' => $start, 'endtime' => $start + HOURSECS,
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
        $this->fail("{$what}: another tenant's classroom was not refused.");
    }

    public function test_a_tenant_admin_cannot_touch_another_tenants_classroom_by_id(): void {
        global $DB;
        $theirs = $this->classroom('/177/178', 'ZEEA ILT');
        $sid = $this->session($theirs);
        $learner = $this->user_at('/177/178');
        session_manager::enrol_users($theirs, [(int) $learner->id]);

        $this->setUser($this->tenant_admin('/1'));

        $this->assert_refused(fn() => list_classroom_users::execute($theirs), 'list_classroom_users');
        $this->assert_refused(fn() => list_classroom_sessions::execute($theirs), 'list_classroom_sessions');
        $this->assert_refused(fn() => list_session_attendance::execute($sid), 'list_session_attendance');
        $this->assert_refused(fn() => list_waitlist::execute($theirs), 'list_waitlist');
        $this->assert_refused(fn() => waitlist_join::execute($theirs), 'waitlist_join');
        $this->assert_refused(fn() => change_status::execute($theirs, session_manager::STATUS_CANCELLED),
            'change_status');
        $this->assert_refused(fn() => delete_session::execute($sid), 'delete_session');
        $this->assert_refused(fn() => unenrol_classroom_user::execute($theirs, (int) $learner->id),
            'unenrol_classroom_user');
        $this->assert_refused(fn() => mark_session_attendance::execute($sid, (int) $learner->id, 1),
            'mark_session_attendance');
        $this->assert_refused(fn() => bulk_mark_attendance::execute($sid,
            [['userid' => (int) $learner->id, 'status' => 1, 'notes' => '']]), 'bulk_mark_attendance');
        $this->assert_refused(fn() => session_manager::require_classroom_access($theirs), 'view.php guard');

        // Nothing was written.
        $this->assertEquals(session_manager::STATUS_ACTIVE,
            $DB->get_field('local_sentientia_classroom', 'status', ['id' => $theirs]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_sessions', ['id' => $sid]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users',
            ['classroomid' => $theirs, 'userid' => $learner->id]));
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_attendance', ['sessionid' => $sid]));
    }

    public function test_the_edit_and_enrol_forms_refuse_another_tenants_classroom(): void {
        $theirs = $this->classroom('/177', 'ZEEA ILT');
        $this->setUser($this->tenant_admin('/1'));
        $this->assert_refused(fn() => new form\edit_classroom(null, null, 'post', '', [], true,
            ['classroomid' => $theirs], true), 'edit_classroom');
        $this->assert_refused(fn() => new form\enrol_classroom_users(null, null, 'post', '', [], true,
            ['classroomid' => $theirs], true), 'enrol_classroom_users');
        $this->assert_refused(fn() => new form\edit_session(null, null, 'post', '', [], true,
            ['classroomid' => $theirs, 'sessionid' => 0], true), 'edit_session');
    }

    public function test_inside_their_own_tenant_a_tenant_admin_works_as_before(): void {
        global $DB;
        $mine = $this->classroom('/1/5', 'Airpay ILT');
        $sid = $this->session($mine);
        $learner = $this->user_at('/1/5');
        session_manager::enrol_users($mine, [(int) $learner->id]);

        $this->setUser($this->tenant_admin('/1'));

        $this->assertSame(1, (int) list_classroom_users::execute($mine)['total']);
        mark_session_attendance::execute($sid, (int) $learner->id, session_manager::ATT_PRESENT);
        $this->assertEquals(session_manager::ATT_PRESENT, $DB->get_field('local_sentientia_classroom_attendance',
            'status', ['sessionid' => $sid, 'userid' => $learner->id]));
        change_status::execute($mine, session_manager::STATUS_CANCELLED);
        $this->assertEquals(session_manager::STATUS_CANCELLED,
            $DB->get_field('local_sentientia_classroom', 'status', ['id' => $mine]));
    }

    public function test_attendance_cannot_name_a_learner_from_another_tenant(): void {
        global $DB;
        $mine = $this->classroom('/1', 'Airpay ILT');
        $sid = $this->session($mine);
        $foreign = $this->user_at('/177');

        $this->setUser($this->tenant_admin('/1'));
        $this->assert_refused(fn() => mark_session_attendance::execute($sid, (int) $foreign->id, 1),
            'mark_session_attendance (foreign learner)');
        $this->assert_refused(fn() => session_manager::require_users_in_scope([(int) $foreign->id]),
            'enrol picker submit (foreign learner)');
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_attendance', ['sessionid' => $sid]));
    }

    public function test_the_org_cascade_cannot_widen_the_list_to_another_tenant(): void {
        $this->classroom('/1', 'Airpay ILT');
        $this->classroom('/177', 'ZEEA ILT');
        $zeeaorg = $this->org('/177');

        $this->setUser($this->tenant_admin('/1'));
        $r = list_classrooms::execute('', 'name', 'asc', 0, 25, json_encode(['org_l1' => $zeeaorg]));
        $this->assertSame(0, (int) $r['total'], 'filters.org_l1 must narrow the tenant scope, never replace it.');
        $this->assertSame(1, (int) list_classrooms::execute('', 'name', 'asc', 0, 25, '{}')['total']);
    }

    public function test_a_scoped_caller_cannot_file_a_classroom_under_another_tenants_org(): void {
        $zeeaorg = $this->org('/177');
        $mineorg = $this->org('/1/9');
        $this->setUser($this->tenant_admin('/1'));

        $this->assertSame('/1', session_manager::org_path_for_caller(0),
            '"No specific organisation" belongs to the caller\'s tenant, not to nobody.');
        $this->assertSame('/1/9', session_manager::org_path_for_caller($mineorg));
        $this->assert_refused(fn() => session_manager::org_path_for_caller($zeeaorg), 'org_path_for_caller');
    }

    public function test_a_caller_with_no_tenant_gets_nothing(): void {
        $this->classroom('/1', 'Airpay ILT');
        $mine = $this->classroom('/177', 'ZEEA ILT');
        $this->user_at('/1');
        $this->user_at('/177');

        foreach (['', 'garbage'] as $path) {
            $nobody = $this->tenant_admin($path);
            $this->setUser($nobody);
            $this->assertSame(0, (int) list_classrooms::execute('', 'name', 'asc', 0, 25, '{}')['total'],
                "open_path '{$path}' must not list every tenant.");
            $this->assert_refused(fn() => session_manager::require_classroom_access($mine),
                "open_path '{$path}' view.php");
            $this->assertSame([], classroom_audience_enroller::resolve_audience(
                ['designation' => 'tsdesig'], (int) $nobody->id),
                "open_path '{$path}' must not resolve an audience across every tenant.");
        }
    }

    public function test_a_classroom_with_no_org_is_site_admin_only(): void {
        $pathless = $this->classroom(null, 'Unowned');
        $this->setUser($this->tenant_admin('/1'));
        $this->assert_refused(fn() => session_manager::require_classroom_access($pathless), 'pathless classroom');

        $this->setAdminUser();
        $this->assertSame($pathless, (int) session_manager::require_classroom_access($pathless)->id);
    }

    public function test_the_audience_is_the_callers_tenant(): void {
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177/178');
        $admin = $this->tenant_admin('/1');

        $ids = classroom_audience_enroller::resolve_audience(['designation' => 'tsdesig'], (int) $admin->id);
        $this->assertSame([(int) $mine->id], $ids);

        $all = classroom_audience_enroller::resolve_audience(['designation' => 'tsdesig'], (int) get_admin()->id);
        sort($all);
        $expected = [(int) $mine->id, (int) $theirs->id];
        sort($expected);
        $this->assertSame($expected, $all);
    }

    public function test_the_site_admin_still_sees_every_tenant(): void {
        $a = $this->classroom('/1', 'A');
        $z = $this->classroom('/177', 'Z');
        $sid = $this->session($z);
        $zeeaorg = $this->org('/177');

        $this->setAdminUser();
        $this->assertSame(2, (int) list_classrooms::execute('', 'name', 'asc', 0, 25, '{}')['total']);
        $this->assertSame(1, (int) list_classrooms::execute('', 'name', 'asc', 0, 25,
            json_encode(['org_l1' => $zeeaorg]))['total']);
        $this->assertSame($z, (int) session_manager::require_classroom_access($z)->id);
        $this->assertSame($a, (int) session_manager::require_classroom_access($a)->id);
        $this->assertSame(0, (int) list_session_attendance::execute($sid)['total']);
        $this->assertNull(session_manager::org_path_for_caller(0));
    }
}
