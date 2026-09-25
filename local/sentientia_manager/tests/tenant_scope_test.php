<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031: a team-member drill-down, and every allocation, is bounded to the
 * manager's tenant.
 *
 * can_view_member() returned true for any target once the viewer held
 * local/sentientia_users:view, which every tenant admin does (manager
 * archetype, system context). member.php then showed that user's name, email,
 * employee id, org, courses, progress and certificate codes - in any tenant.
 *
 * approval_manager::create_allocation() (and bulk_allocate(), which goes
 * through it) skipped the direct-report check whenever the manager had no
 * reports, and never checked the course's tenant. local/sentientia_manager:allocate
 * defaults to the manager archetype, so every tenant admin could enrol any
 * user of any tenant into any course of any tenant, and notify them.
 *
 * @package    local_sentientia_manager
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_manager\team_manager
 * @covers \local_sentientia_manager\approval_manager
 * @covers \local_sentientia_manager\external\create_allocation
 * @covers \local_sentientia_manager\external\bulk_allocate
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        // Legacy org seam (the default): the supervisor walk reads open_supervisorid.
        set_config('org_legacy', 1, 'local_sentientia_core');
    }

    /** A user at $path, reloaded so the record carries open_path. */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
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

    /** A user at $path whose supervisor (open_supervisorid) is $mgr. */
    private function report_of(\stdClass $mgr, string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $DB->set_field('user', 'open_supervisorid', $mgr->id, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A visible course at $path (null = no path) with a manual enrol instance. */
    private function course_at(?string $path): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        if (!$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $DB->insert_record('enrol', (object) [
                'enrol' => 'manual', 'courseid' => $course->id,
                'status' => 0, 'sortorder' => 0, 'roleid' => 5,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
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

    public function test_a_tenant_admin_sees_only_their_own_tenants_members(): void {
        $admin = $this->tenant_admin('/1');
        $this->assertTrue(has_capability('local/sentientia_users:view', \context_system::instance(), $admin->id),
            'Precondition: tenant admins hold sentientia_users:view by default.');
        $mine = $this->user_at('/1/2');
        $zeea = $this->user_at('/177/178');
        $public = $this->user_at('/77');
        $prefixtrap = $this->user_at('/10');

        $this->assertTrue(team_manager::can_view_member((int) $admin->id, (int) $mine->id));
        $this->assertFalse(team_manager::can_view_member((int) $admin->id, (int) $zeea->id),
            'Holding :view must not open another tenant\'s member page.');
        $this->assertFalse(team_manager::can_view_member((int) $admin->id, (int) $public->id));
        $this->assertFalse(team_manager::can_view_member((int) $admin->id, (int) $prefixtrap->id),
            '/1 never matches /10.');
        $this->assertFalse(team_manager::can_view_member((int) $admin->id, 999999), 'Nor a missing id.');
    }

    public function test_a_viewer_with_no_tenant_sees_no_other_member(): void {
        $target = $this->user_at('/1/2');
        foreach (['', 'garbage'] as $path) {
            $nobody = $this->tenant_admin($path);
            $this->assertFalse(team_manager::can_view_member((int) $nobody->id, (int) $target->id),
                "open_path '{$path}' must not open anybody's member page.");
            $this->assertTrue(team_manager::can_view_member((int) $nobody->id, (int) $nobody->id),
                'Their own page is still theirs.');
        }
    }

    public function test_the_supervisor_chain_and_the_site_admin_are_unchanged(): void {
        global $DB;
        $manager = $this->user_at('/1/2');
        $report = $this->user_at('/1/2');
        $DB->set_field('user', 'open_supervisorid', $manager->id, ['id' => $report->id]);
        $zeea = $this->user_at('/177/178');

        $this->assertTrue(team_manager::can_view_member((int) $manager->id, (int) $report->id),
            'A direct supervisor still sees their report.');
        $this->assertFalse(team_manager::can_view_member((int) $report->id, (int) $manager->id));
        $this->assertTrue(team_manager::can_view_member((int) get_admin()->id, (int) $zeea->id),
            'The site admin still sees every tenant.');
    }

    public function test_a_tenant_admin_with_no_reports_cannot_allocate_across_tenants(): void {
        global $DB;
        $admin = $this->tenant_admin('/1');
        $syscontext = \context_system::instance();
        $this->assertTrue(has_capability('local/sentientia_manager:allocate', $syscontext, $admin->id),
            'Precondition: tenant admins hold :allocate by default.');
        $this->assertSame([], approval_manager::direct_report_ids((int) $admin->id),
            'Precondition: this tenant admin has no direct reports.');
        $theirs = $this->user_at('/177/178');
        $colleague = $this->user_at('/1/2');
        $mycourse = $this->course_at('/1');
        $theircourse = $this->course_at('/177');
        $this->setUser($admin);
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $this->assert_refused(fn() => approval_manager::create_allocation((int) $admin->id,
            (int) $theirs->id, (int) $theircourse->id),
            'error_outoftenant', 'No enrolling a /177 user into a /177 course.');
        $this->assert_refused(fn() => approval_manager::create_allocation((int) $admin->id,
            (int) $theirs->id, (int) $mycourse->id),
            'error_outoftenant', 'Nor a /177 user into a /1 course.');
        $this->assert_refused(fn() => approval_manager::create_allocation((int) $admin->id,
            (int) $colleague->id, (int) $theircourse->id),
            'error_outoftenant', 'Nor an in-tenant colleague into a /177 course.');
        $this->assert_refused(fn() => approval_manager::create_allocation((int) $admin->id,
            999999, (int) $mycourse->id),
            'error_outoftenant', 'A missing user is refused exactly like an out-of-tenant one.');

        $result = approval_manager::bulk_allocate((int) $admin->id,
            [(int) $theirs->id, 999999], (int) $mycourse->id);
        $this->assertSame([], $result['succeeded']);
        $this->assertSame(['error_outoftenant', 'error_outoftenant'],
            array_column($result['skipped'], 'reason'));

        // Through the web services, as the allocation page calls them.
        $_POST['sesskey'] = sesskey();
        $this->assert_refused(fn() => external\create_allocation::execute((int) $theirs->id,
            (int) $theircourse->id),
            'error_outoftenant', 'Nor through the create_allocation web service.');
        $ws = external\bulk_allocate::execute([(int) $theirs->id], (int) $theircourse->id);
        $this->assertSame(0, $ws['succeeded_count']);
        $this->assertSame(1, $ws['skipped_count']);

        $this->assertSame(0, $DB->count_records('local_sentientia_mgr_allocations'));
        $this->assertFalse(is_enrolled(\context_course::instance($theircourse->id), $theirs));
        $this->assertFalse(is_enrolled(\context_course::instance($mycourse->id), $theirs));
        $this->assertSame(0, $sink->count(), 'Nobody was notified.');

        // The in-tenant function Airpay has today survives: with no reports,
        // a tenant admin still allocates any user of their own tenant.
        $id = approval_manager::create_allocation((int) $admin->id,
            (int) $colleague->id, (int) $mycourse->id);
        $this->assertTrue($DB->record_exists('local_sentientia_mgr_allocations',
            ['id' => $id, 'userid' => $colleague->id, 'courseid' => $mycourse->id]));
        $this->assertTrue(is_enrolled(\context_course::instance($mycourse->id), $colleague));
        $sink->close();
    }

    public function test_a_manager_with_reports_is_still_held_to_them(): void {
        global $DB;
        $mgr = $this->tenant_admin('/1');
        $report = $this->report_of($mgr, '/1/2');
        $colleague = $this->user_at('/1/3');
        $mycourse = $this->course_at('/1');
        $this->setUser($mgr);
        $sink = $this->redirectMessages();

        $this->assert_refused(fn() => approval_manager::create_allocation((int) $mgr->id,
            (int) $colleague->id, (int) $mycourse->id),
            'notdirectreport', 'A manager with reports allocates only to them, even in-tenant.');
        $this->assertSame(0, $DB->count_records('local_sentientia_mgr_allocations'));
        approval_manager::create_allocation((int) $mgr->id, (int) $report->id, (int) $mycourse->id);
        $this->assertSame(1, $DB->count_records('local_sentientia_mgr_allocations',
            ['userid' => $report->id]));
        $sink->close();
    }

    public function test_a_manager_cannot_allocate_a_course_outside_their_tenant(): void {
        global $DB;
        $mgr = $this->user_at('/1/2');
        $report = $this->report_of($mgr, '/1/2');
        // Supervisor data can drift: a "report" who sits in another tenant.
        $driftedreport = $this->report_of($mgr, '/177/178');
        $mine = $this->course_at('/1/5');
        $this->setUser($mgr);

        foreach (['/177' => '/177', '/10' => 'the /10 prefix trap', '' => 'an empty path',
                'null' => 'no path at all'] as $path => $label) {
            $course = $this->course_at($path === 'null' ? null : (string) $path);
            $this->assert_refused(fn() => approval_manager::create_allocation((int) $mgr->id,
                (int) $report->id, (int) $course->id),
                'error_outoftenant', "A course at {$label} is outside the manager's tenant.");
        }
        $this->assert_refused(fn() => approval_manager::create_allocation((int) $mgr->id,
            (int) $report->id, 999999),
            'error_outoftenant', 'A missing course is refused exactly like an out-of-tenant one.');
        $this->assert_refused(fn() => approval_manager::create_allocation((int) $mgr->id,
            (int) $driftedreport->id, (int) $mine->id),
            'error_outoftenant', 'A direct report in another tenant is still another tenant\'s user.');
        $this->assertSame(0, $DB->count_records('local_sentientia_mgr_allocations'));

        // The in-tenant function survives: my report, a course in my tenant.
        $id = approval_manager::create_allocation((int) $mgr->id, (int) $report->id, (int) $mine->id);
        $this->assertTrue($DB->record_exists('local_sentientia_mgr_allocations',
            ['id' => $id, 'userid' => $report->id, 'courseid' => $mine->id]));
        $this->assertTrue(is_enrolled(\context_course::instance($mine->id), $report));
    }

    public function test_a_typed_allocation_checks_the_items_tenant(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_classroom')) {
            $this->markTestSkipped('local_sentientia_classroom is not installed.');
        }
        $mgr = $this->user_at('/1/2');
        $report = $this->report_of($mgr, '/1/2');
        $now = time();
        $classroom = fn(?string $path, int $costcenterid) => (int) $DB->insert_record(
            'local_sentientia_classroom', (object) [
                'name' => 'ILT ' . ($path ?? 'none'), 'costcenterid' => $costcenterid,
                'open_path' => $path, 'timecreated' => $now, 'timemodified' => $now,
            ]);
        $theirs = $classroom('/177', 177);
        $unscoped = $classroom(null, 0);
        $mine = $classroom('/1', 1);
        $this->setUser($mgr);

        $this->assert_refused(fn() => approval_manager::create_classroom_allocation((int) $mgr->id,
            (int) $report->id, $theirs),
            'error_outoftenant', 'No allocating another tenant\'s classroom.');
        $this->assert_refused(fn() => approval_manager::create_classroom_allocation((int) $mgr->id,
            (int) $report->id, $unscoped),
            'error_outoftenant', 'Nor a classroom with no path.');
        $this->assertSame(0, $DB->count_records('local_sentientia_mgr_allocations'));
        $this->assertSame(0, $DB->count_records('local_sentientia_classroom_users'),
            'A refused allocation puts nobody on any roster.');

        $sink = $this->redirectMessages();
        $id = approval_manager::create_classroom_allocation((int) $mgr->id, (int) $report->id, $mine);
        $this->assertTrue($DB->record_exists('local_sentientia_mgr_allocations',
            ['id' => $id, 'item_type' => approval_manager::ITEM_CLASSROOM, 'itemid' => $mine]));
        // The allocation also puts the learner on the classroom roster. It
        // used to call a session_manager method that never existed, so the
        // learner was told they were allocated and never enrolled.
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users',
            ['classroomid' => $mine, 'userid' => $report->id]),
            'The allocated learner is on the in-tenant classroom\'s roster.');
        $this->assertSame(1, $DB->count_records('local_sentientia_classroom_users'),
            'And on no other classroom\'s.');
        $sink->close();
    }

    public function test_a_classroom_allocation_puts_the_learner_on_the_roster_once(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_classroom')) {
            $this->markTestSkipped('local_sentientia_classroom is not installed.');
        }
        // Any tenant, not just Airpay: a ZEEA manager and their ZEEA report.
        $mgr = $this->user_at('/177/178');
        $report = $this->report_of($mgr, '/177/178');
        $now = time();
        $classroomid = (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'ZEEA ILT', 'costcenterid' => 177, 'open_path' => '/177',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->setUser($mgr);
        $sink = $this->redirectMessages();

        approval_manager::create_classroom_allocation((int) $mgr->id, (int) $report->id, $classroomid);
        $roster = $DB->get_records('local_sentientia_classroom_users', ['classroomid' => $classroomid]);
        $this->assertCount(1, $roster);
        $row = reset($roster);
        $this->assertSame((int) $report->id, (int) $row->userid);
        $this->assertSame((int) $mgr->id, (int) $row->enrolledby, 'Recorded as enrolled by the allocating manager.');

        $this->assert_refused(fn() => approval_manager::create_classroom_allocation((int) $mgr->id,
            (int) $report->id, $classroomid),
            'duplicateallocation', 'A second allocation of the same classroom is refused.');
        $this->assertSame(1, $DB->count_records('local_sentientia_classroom_users',
            ['classroomid' => $classroomid]), 'And the roster still holds one row.');
        $sink->close();
    }

    public function test_a_path_allocation_links_the_learner_to_their_courses_not_the_admin_page(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_learningpath')) {
            $this->markTestSkipped('local_sentientia_learningpath is not installed.');
        }
        // 2026-09-25 review: ADR-031 took learningpath:view away from learners
        // because learningpath/view.php lists path rosters with PII. The
        // allocation message still linked there, so every assignee got
        // "required capability". It now links to My courses.
        $mgr = $this->user_at('/1/2');
        $report = $this->report_of($mgr, '/1/2');
        $now = time();
        $pathid = (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => 'Onboarding', 'description' => '', 'descriptionformat' => 1,
            'costcenterid' => 1, 'open_path' => '/1', 'status' => 1, 'visible' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->setUser($mgr);
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        approval_manager::create_path_allocation((int) $mgr->id, (int) $report->id, $pathid);

        $messages = array_values(array_filter($sink->get_messages(),
            fn($m) => (int) $m->useridto === (int) $report->id));
        $sink->close();
        $this->assertCount(1, $messages, 'The assignee is told about the allocation.');
        $this->assertStringContainsString('/local/sentientia_catalog/mycourses.php',
            (string) $messages[0]->contexturl);
        $this->assertStringNotContainsString('sentientia_learningpath/view.php',
            (string) $messages[0]->contexturl . (string) $messages[0]->fullmessagehtml);
        // Why the old link was dead: a learner holds no :view on the admin page.
        $this->assertFalse(has_capability('local/sentientia_learningpath:view',
            \context_system::instance(), $report->id));
    }

    public function test_a_manager_with_no_tenant_allocates_nothing(): void {
        global $DB;
        $course = $this->course_at('/1');
        foreach (['', 'garbage'] as $path) {
            $nobody = $this->tenant_admin($path);
            $report = $this->report_of($nobody, '/1/2');
            $this->setUser($nobody);
            $this->assert_refused(fn() => approval_manager::create_allocation((int) $nobody->id,
                (int) $report->id, (int) $course->id),
                'error_outoftenant', "open_path '{$path}' must not allocate to anybody.");
            $this->assertSame([], approval_manager::allocatable_user_options((int) $nobody->id));
            $this->assertSame([], approval_manager::allocatable_course_options((int) $nobody->id));
        }
        $this->assertSame(0, $DB->count_records('local_sentientia_mgr_allocations'));
    }

    public function test_the_allocation_pickers_offer_only_the_managers_tenant(): void {
        global $DB;
        $admin = $this->tenant_admin('/1');
        $report = $this->report_of($admin, '/1/2');
        $driftedreport = $this->report_of($admin, '/177/178');
        $this->user_at('/1/3');   // A colleague, not a report.
        $zeea = $this->user_at('/177/179');
        $mine = $this->course_at('/1');
        $mychild = $this->course_at('/1/5');
        $prefixtrap = $this->course_at('/10');
        $theirs = $this->course_at('/177');
        $unscoped = $this->course_at(null);
        $this->setUser($admin);

        $this->assertSame([(int) $report->id],
            array_keys(approval_manager::allocatable_user_options((int) $admin->id)),
            'Only a direct report inside the tenant - never another tenant\'s users or emails.');
        $this->assertEqualsCanonicalizing([(int) $mine->id, (int) $mychild->id],
            array_keys(approval_manager::allocatable_course_options((int) $admin->id)));

        // A tenant admin with no reports is offered their own tenant's active
        // users (it used to be 200 users of every tenant) - never ZEEA's.
        $noreports = $this->tenant_admin('/1');
        $suspended = $this->user_at('/1/4');
        $DB->set_field('user', 'suspended', 1, ['id' => $suspended->id]);
        $this->setUser($noreports);
        $offered = array_keys(approval_manager::allocatable_user_options((int) $noreports->id));
        $this->assertContains((int) $report->id, $offered);
        foreach ([$driftedreport, $zeea, $suspended] as $u) {
            $this->assertNotContains((int) $u->id, $offered);
        }
        foreach ($offered as $uid) {
            $path = (string) $DB->get_field('user', 'open_path', ['id' => $uid]);
            $this->assertTrue($path === '/1' || strpos($path, '/1/') === 0,
                "Offered user {$uid} at '{$path}' is outside /1.");
        }

        // The site admin keeps the unscoped pickers.
        $this->setAdminUser();
        $siteadminid = (int) get_admin()->id;
        $this->assertArrayHasKey((int) $zeea->id, approval_manager::allocatable_user_options($siteadminid));
        $courses = approval_manager::allocatable_course_options($siteadminid);
        foreach ([$mine, $mychild, $prefixtrap, $theirs, $unscoped] as $c) {
            $this->assertArrayHasKey((int) $c->id, $courses);
        }
        $this->assertArrayHasKey((int) $driftedreport->id,
            approval_manager::allocatable_user_options($siteadminid));
    }

    public function test_the_site_admin_can_still_allocate_in_any_tenant(): void {
        global $DB;
        $theirs = $this->user_at('/177/178');
        $theircourse = $this->course_at('/177');
        $unscoped = $this->course_at(null);
        $this->setAdminUser();
        $adminid = (int) get_admin()->id;

        $id = approval_manager::create_allocation($adminid, (int) $theirs->id, (int) $theircourse->id);
        $this->assertTrue($DB->record_exists('local_sentientia_mgr_allocations', ['id' => $id]));
        $this->assertTrue(is_enrolled(\context_course::instance($theircourse->id), $theirs));
        // A cross-tenant caller is not held to a course path either.
        approval_manager::create_allocation($adminid, (int) $theirs->id, (int) $unscoped->id);
        $this->assertSame(2, $DB->count_records('local_sentientia_mgr_allocations',
            ['userid' => $theirs->id]));
    }
}
