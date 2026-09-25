<?php
// This file is part of Sentientia LMS.

/**
 * ADR-031: certification programs, their levels, courses and rosters stay
 * inside the caller's tenant (2026-09-25).
 *
 * :view, :enrol, :update and :create default to the manager archetype, and
 * tenant admins hold a manager-archetype role at system context. Every
 * programid/levelid-keyed web service checked only the capability, so a
 * tenant admin could read any tenant's program roster (names, emails,
 * employee ids) and archive, restructure, enrol into or unenrol from any
 * tenant's programs; the cohort form pulled other tenants' users in;
 * list_programs let a client-chosen org replace the tenant filter; and a
 * caller whose open_path did not resolve skipped the page checks and got
 * every tenant's users in the picker and audience.
 *
 * @package    local_sentientia_programs
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_programs\external\change_status;
use local_sentientia_programs\external\delete_level;
use local_sentientia_programs\external\list_level_courses;
use local_sentientia_programs\external\list_program_levels;
use local_sentientia_programs\external\list_program_users;
use local_sentientia_programs\external\list_programs;
use local_sentientia_programs\external\reorder_levels;
use local_sentientia_programs\external\unassign_level_course;
use local_sentientia_programs\external\unenrol_program_user;

/**
 * @covers \local_sentientia_programs\program_manager
 * @covers \local_sentientia_programs\program_audience_enroller
 * @covers \local_sentientia_programs\external\list_programs
 * @covers \local_sentientia_programs\external\list_program_users
 * @covers \local_sentientia_programs\external\unenrol_program_user
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

    private function program(?string $openpath, string $name = 'Program'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_programs', (object) [
            'name' => $name, 'description' => '', 'costcenterid' => 0, 'open_path' => $openpath,
            'status' => program_manager::STATUS_ACTIVE, 'visible' => 1, 'completion_required' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function course_at(?string $openpath): int {
        global $DB;
        $c = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $openpath, ['id' => $c->id]);
        return (int) $c->id;
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
        $this->fail("{$what}: another tenant's program, user or course was not refused.");
    }

    public function test_a_tenant_admin_cannot_touch_another_tenants_program_by_id(): void {
        global $DB;
        $theirs = $this->program('/177/178', 'ZEEA certification');
        $levelid = program_manager::create_level($theirs, (object) ['name' => 'Level 1']);
        $course = $this->course_at('/177');
        program_manager::assign_courses_to_level($levelid, [$course]);
        $learner = $this->user_at('/177/178');
        program_manager::enrol_users($theirs, [(int) $learner->id]);
        $admin = $this->tenant_admin('/1');

        $this->setUser($admin);

        $this->assert_refused(fn() => list_program_users::execute($theirs), 'list_program_users');
        $this->assert_refused(fn() => list_program_levels::execute($theirs), 'list_program_levels');
        $this->assert_refused(fn() => list_level_courses::execute($levelid), 'list_level_courses');
        $this->assert_refused(fn() => change_status::execute($theirs, program_manager::STATUS_ARCHIVED),
            'change_status');
        $this->assert_refused(fn() => delete_level::execute($levelid), 'delete_level');
        $this->assert_refused(fn() => reorder_levels::execute($theirs, [$levelid]), 'reorder_levels');
        $this->assert_refused(fn() => unassign_level_course::execute($levelid, $course), 'unassign_level_course');
        $this->assert_refused(fn() => unenrol_program_user::execute($theirs, (int) $learner->id),
            'unenrol_program_user');
        $this->assert_refused(fn() => program_manager::require_program_access($theirs), 'view.php guard');
        $this->assert_refused(fn() => program_manager::require_level_access($levelid), 'levelcourses.php guard');
        $this->assert_refused(fn() => program_audience_enroller::enrol_by_filter($theirs,
            ['designation' => 'tsdesig'], (int) $admin->id), 'bulk_enrol_by_audience');
        $this->assert_refused(fn() => new form\edit_program(null, null, 'post', '', [], true,
            ['programid' => $theirs], true), 'edit_program form');
        $this->assert_refused(fn() => new form\enrol_program_cohort(null, null, 'post', '', [], true,
            ['programid' => $theirs], true), 'enrol_program_cohort form');
        $this->assert_refused(fn() => new form\assign_level_courses(null, null, 'post', '', [], true,
            ['levelid' => $levelid], true), 'assign_level_courses form');

        // Nothing was written.
        $this->assertEquals(program_manager::STATUS_ACTIVE,
            $DB->get_field('local_sentientia_programs', 'status', ['id' => $theirs]));
        $this->assertTrue($DB->record_exists('local_sentientia_programs_levels', ['id' => $levelid]));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_courses', ['levelid' => $levelid]));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_users', ['programid' => $theirs]));
    }

    public function test_writes_on_an_own_program_cannot_name_another_tenants_user_or_course(): void {
        global $DB;
        $mine = $this->program('/1', 'Airpay certification');
        $foreignuser = $this->user_at('/177');
        $foreigncourse = $this->course_at('/177');

        $this->setUser($this->tenant_admin('/1'));

        $this->assert_refused(fn() => program_manager::require_users_in_scope([(int) $foreignuser->id]),
            'enrol picker submit (foreign user)');
        $this->assert_refused(fn() => program_manager::require_courses_in_scope([$foreigncourse]),
            'level course picker submit (foreign course)');
        $this->assert_refused(fn() => unenrol_program_user::execute($mine, (int) $foreignuser->id),
            'unenrol foreign user');
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_users', ['programid' => $mine]));
    }

    public function test_a_cohort_enrol_takes_only_the_callers_tenant(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $mine = $this->program('/1', 'Airpay certification');
        $cohort = $this->getDataGenerator()->create_cohort();
        $own = $this->user_at('/1/2');
        $foreign = $this->user_at('/177/178');
        cohort_add_member($cohort->id, $own->id);
        cohort_add_member($cohort->id, $foreign->id);

        $this->setUser($this->tenant_admin('/1'));
        $r = program_manager::enrol_cohort($mine, (int) $cohort->id,
            \local_sentientia_platform\tenant::scope_path());

        $this->assertSame(1, $r['newly_enrolled']);
        $this->assertTrue($DB->record_exists('local_sentientia_programs_users',
            ['programid' => $mine, 'userid' => $own->id]));
        $this->assertFalse($DB->record_exists('local_sentientia_programs_users',
            ['programid' => $mine, 'userid' => $foreign->id]),
            'A mixed cohort must not pull another tenant\'s users into the program.');
    }

    public function test_inside_their_own_tenant_a_tenant_admin_works_as_before(): void {
        global $DB;
        $mine = $this->program('/1/5', 'Airpay certification');
        $learner = $this->user_at('/1/5');
        program_manager::enrol_users($mine, [(int) $learner->id]);
        $owncourse = $this->course_at('/1/5');
        $legacycourse = $this->course_at(null);

        $this->setUser($this->tenant_admin('/1'));

        $this->assertSame(1, (int) list_program_users::execute($mine)['total']);
        program_manager::require_courses_in_scope([$owncourse, $legacycourse]);
        change_status::execute($mine, program_manager::STATUS_ARCHIVED);
        $this->assertEquals(program_manager::STATUS_ARCHIVED,
            $DB->get_field('local_sentientia_programs', 'status', ['id' => $mine]));
        unenrol_program_user::execute($mine, (int) $learner->id);
        $this->assertFalse($DB->record_exists('local_sentientia_programs_users',
            ['programid' => $mine, 'userid' => $learner->id]));
    }

    public function test_the_org_cascade_cannot_widen_the_list_to_another_tenant(): void {
        $this->program('/1', 'Airpay certification');
        $this->program('/177', 'ZEEA certification');
        $zeeaorg = $this->org('/177');

        $this->setUser($this->tenant_admin('/1'));
        $r = list_programs::execute('', 'name', 'asc', 0, 25, json_encode(['org_l1' => $zeeaorg]));
        $this->assertSame(0, (int) $r['total'], 'filters.org_l1 must narrow the tenant scope, never replace it.');
        $this->assertSame(1, (int) list_programs::execute('', 'name', 'asc', 0, 25, '{}')['total']);
    }

    public function test_a_scoped_caller_cannot_file_a_program_under_another_tenants_org(): void {
        $zeeaorg = $this->org('/177');
        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame('/1', program_manager::org_path_for_caller(0));
        $this->assert_refused(fn() => program_manager::org_path_for_caller($zeeaorg), 'org_path_for_caller');
    }

    public function test_a_caller_with_no_tenant_gets_nothing(): void {
        $this->program('/1', 'Airpay certification');
        $theirs = $this->program('/177', 'ZEEA certification');
        $this->user_at('/1');
        $this->user_at('/177');

        foreach (['', 'garbage'] as $path) {
            $nobody = $this->tenant_admin($path);
            $this->setUser($nobody);
            $this->assertSame(0, (int) list_programs::execute('', 'name', 'asc', 0, 25, '{}')['total'],
                "open_path '{$path}' must not list every tenant.");
            $this->assert_refused(fn() => program_manager::require_program_access($theirs),
                "open_path '{$path}' view.php");
            $this->assertSame([], program_audience_enroller::resolve_audience(
                ['designation' => 'tsdesig'], (int) $nobody->id),
                "open_path '{$path}' must not resolve an audience across every tenant.");
        }
    }

    public function test_a_program_with_no_org_is_site_admin_only(): void {
        $pathless = $this->program(null, 'Unowned');
        $this->setUser($this->tenant_admin('/1'));
        $this->assert_refused(fn() => list_program_users::execute($pathless), 'pathless program');

        $this->setAdminUser();
        $this->assertSame(0, (int) list_program_users::execute($pathless)['total']);
    }

    /**
     * An own program whose roster also holds a learner from another tenant
     * and one with no open_path - as a site admin, an approval flow, the
     * pre-fix cohort path or the no-tenant fail-open could leave it.
     *
     * @return array{0: int, 1: \stdClass, 2: \stdClass, 3: \stdClass} program, own, foreign, pathless
     */
    private function mixed_roster(): array {
        $mine = $this->program('/1', 'Airpay certification');
        $own = $this->user_at('/1/2');
        $foreign = $this->user_at('/177/178');
        $pathless = $this->user_at(null);
        program_manager::enrol_users($mine, [(int) $own->id, (int) $foreign->id, (int) $pathless->id]);
        return [$mine, $own, $foreign, $pathless];
    }

    public function test_the_roster_of_an_own_program_lists_only_the_callers_tenant(): void {
        [$mine, $own, $foreign, $pathless] = $this->mixed_roster();

        $this->setUser($this->tenant_admin('/1'));
        $r = list_program_users::execute($mine);
        $this->assertSame(1, (int) $r['total'], 'The roster total counts only the caller\'s tenant.');
        $this->assertSame([(int) $own->id], array_map(fn($row) => (int) $row['userid'], $r['rows']),
            'No other tenant\'s (or pathless) name, email, employee id or designation.');
        $this->assertSame(0, (int) list_program_users::execute($mine, (string) $foreign->email)['total'],
            'Searching for them by email finds nothing either.');

        $this->setUser($this->tenant_admin('garbage'));
        $this->assertSame([], program_manager::get_enrolled_users($mine, '', 'lastname', 'ASC', 0, 100, true));
        $this->assertSame(0, program_manager::count_enrolled_filtered($mine, '', true));

        // Cross-tenant callers, and library callers with no user, see everyone.
        $this->setAdminUser();
        $this->assertSame(3, (int) list_program_users::execute($mine)['total']);
        $this->setUser(null);
        $this->assertSame(3, program_manager::count_enrolled_filtered($mine));
        $this->assertCount(3, program_manager::get_enrolled_users($mine));
    }

    public function test_a_tenant_admin_can_remove_a_legacy_learner_from_their_own_program(): void {
        global $DB;
        [$mine, $own, $foreign, $pathless] = $this->mixed_roster();
        $stranger = $this->user_at('/177');

        $this->setUser($this->tenant_admin('/1'));
        unenrol_program_user::execute($mine, (int) $foreign->id);
        unenrol_program_user::execute($mine, (int) $pathless->id);
        $this->assertFalse($DB->record_exists('local_sentientia_programs_users',
            ['programid' => $mine, 'userid' => $foreign->id]),
            'Removing someone from your own program reaches into no other tenant.');
        $this->assertFalse($DB->record_exists('local_sentientia_programs_users',
            ['programid' => $mine, 'userid' => $pathless->id]));
        $this->assert_refused(fn() => unenrol_program_user::execute($mine, (int) $stranger->id),
            'unenrol of a foreign user who is not on the roster');
        $this->assertTrue($DB->record_exists('local_sentientia_programs_users',
            ['programid' => $mine, 'userid' => $own->id]));
    }

    /**
     * The cohort picker applied "has a member in my tenant" in PHP after a
     * LIMIT 500, so other tenants' cohorts could crowd a tenant's own ones
     * off the list. cohort_options($limit) shows the fix with a small limit.
     */
    public function test_the_cohort_picker_limit_applies_after_the_tenant_filter(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $gen = $this->getDataGenerator();
        foreach (['A1', 'A2', 'A3', 'A4'] as $name) {
            $c = $gen->create_cohort(['name' => "{$name} ZEEA only"]);
            cohort_add_member($c->id, $this->user_at('/177/178')->id);
        }
        $gen->create_cohort(['name' => 'B empty']);
        $mixed = $gen->create_cohort(['name' => 'M mixed']);
        cohort_add_member($mixed->id, $this->user_at('/1/2')->id);
        cohort_add_member($mixed->id, $this->user_at('/177')->id);
        $own = $gen->create_cohort(['name' => 'Z Airpay only']);
        cohort_add_member($own->id, $this->user_at('/1/5')->id);

        $this->setUser($this->tenant_admin('/1'));
        $opts = program_manager::cohort_options(3);
        $this->assertSame([(int) $mixed->id, (int) $own->id], array_map('intval', array_keys($opts)),
            'Only cohorts with an Airpay member, and the limit counts only those.');
        $this->assertSame(1, (int) $opts[$mixed->id]->member_count, 'Only the members enrol_cohort() takes.');

        $this->setUser($this->tenant_admin(''));
        $this->assertSame([], program_manager::cohort_options(3), 'No tenant, no cohorts.');

        $this->setAdminUser();
        $this->assertCount(3, program_manager::cohort_options(3));
        $all = program_manager::cohort_options();
        $this->assertCount(7, $all, 'The site admin sees every visible cohort, as before.');
        $this->assertSame(2, (int) $all[$mixed->id]->member_count);
    }

    public function test_the_site_admin_still_sees_every_tenant(): void {
        $this->program('/1', 'A');
        $z = $this->program('/177', 'Z');
        $zlearner = $this->user_at('/177');
        program_manager::enrol_users($z, [(int) $zlearner->id]);
        $mine = $this->user_at('/1/2');

        $this->setAdminUser();
        $this->assertSame(2, (int) list_programs::execute('', 'name', 'asc', 0, 25, '{}')['total']);
        $this->assertSame(1, (int) list_program_users::execute($z)['total']);
        $this->assertNull(program_manager::org_path_for_caller(0));

        $all = program_audience_enroller::resolve_audience(['designation' => 'tsdesig'], (int) get_admin()->id);
        $this->assertContains((int) $zlearner->id, $all);
        $this->assertContains((int) $mine->id, $all);
    }
}
