<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: the "All requests" list is confined to the caller's tenant.
 *
 * :viewall defaults to the manager archetype (and db/install.php grants it
 * to the 'administrator' role), so every tenant admin holds it. Until
 * 2026-09-25 list_all started from 1=1 and took the tenant from the client's
 * filters JSON: every tenant's requesters, emails, reasons and decision notes.
 *
 * It also pins decide(): an :overrideroute holder (db/install.php grants it
 * to the 'administrator' tenant-admin role) decides only their own tenant's
 * requests, and one whose tenant does not resolve decides nothing - root 0
 * used to equal a costcenterid-0 request and let them approve it.
 *
 * And the item (2026-09-25, follow-up 3): submit() and submit_path() took any
 * course or path id and never compared it with the requester's tenant, and
 * decide() never compared it with anyone's. A /77 tenant admin (who holds
 * :request, :approve and :overrideroute) could request a /1 course and approve
 * it themselves; an in-tenant supervisor could approve a report into another
 * tenant's course. Now the item must be in the requester's enrolment scope
 * (tree, share or legacy; visible, for a course) at submit AND at approval,
 * and a scoped decider - the assigned approver included - decides only for a
 * requester in their own tenant.
 *
 * @package    local_sentientia_request
 * @category   test
 * @covers     \local_sentientia_request\external\list_all
 * @covers     \local_sentientia_request\request_manager::decide
 * @covers     \local_sentientia_request\request_manager::submit
 * @covers     \local_sentientia_request\request_manager::submit_path
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** A user at $path holding the manager role at system context (a tenant admin). */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A pending course request stored for tenant $tenant, by a requester who sits in that tenant. */
    private function request(int $tenant): int {
        global $DB;
        $requester = $this->getDataGenerator()->create_user();
        if ($tenant > 0) {
            // decide() also checks that a scoped decider's requester is in
            // their tenant, so the requester carries the tenant the row claims.
            $DB->set_field('user', 'open_path', '/' . $tenant, ['id' => $requester->id]);
        }
        $course = $this->getDataGenerator()->create_course();
        return (int) $DB->insert_record('local_sentientia_request', (object) [
            'userid'       => $requester->id,
            'item_type'    => 'course',
            'itemid'       => $course->id,
            'courseid'     => $course->id,
            'costcenterid' => $tenant,
            'reason'       => 'Need it for tenant ' . $tenant,
            'status'       => 'pending',
            'route'        => 'admin',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    private function listed_ids(string $filters = '{}'): array {
        $result = external\list_all::execute('', 'timecreated', 'desc', 0, 100, $filters);
        return array_map('intval', array_column($result['rows'], 'id'));
    }

    public function test_tenant_admin_lists_only_their_tenant(): void {
        $own = $this->request(1);
        $foreign = $this->request(177);
        $orphan = $this->request(0);
        $this->setUser($this->tenant_admin('/1'));

        foreach (['{}', '{"tenant":177}', '{"tenant":0}'] as $filters) {
            $ids = $this->listed_ids($filters);
            $this->assertContains($own, $ids);
            $this->assertNotContains($foreign, $ids, "filters={$filters} must not reach tenant 177.");
            $this->assertNotContains($orphan, $ids);
        }
    }

    public function test_caller_with_no_tenant_gets_nothing(): void {
        $this->request(1);
        $this->request(0);
        $this->setUser($this->tenant_admin(''));

        $result = external\list_all::execute();
        $this->assertSame(0, $result['total'],
            'No tenant must not mean every tenant - nor the costcenterid 0 rows.');
        $this->assertSame([], $result['rows']);
    }

    public function test_site_admin_still_lists_every_tenant(): void {
        $own = $this->request(1);
        $foreign = $this->request(177);
        $this->setAdminUser();

        $ids = $this->listed_ids();
        $this->assertContains($own, $ids);
        $this->assertContains($foreign, $ids);
        $this->assertSame([$foreign], $this->listed_ids('{"tenant":177}'));
    }

    public function test_list_all_page_size_is_bounded_and_stays_in_tenant(): void {
        $own = [$this->request(1), $this->request(1), $this->request(1)];
        $foreign = $this->request(177);
        $this->setUser($this->tenant_admin('/1'));

        $all = external\list_all::execute('', 'timecreated', 'desc', 0, 100000);
        $this->assertSame(external\list_all::MAX_PERPAGE, $all['perpage']);
        $this->assertSame(3, $all['total']);
        $ids = array_map('intval', array_column($all['rows'], 'id'));
        sort($ids);
        $this->assertSame($own, $ids);
        $this->assertNotContains($foreign, $ids);

        $one = external\list_all::execute('', 'timecreated', 'desc', -3, 0);
        $this->assertSame(1, $one['perpage'], 'perpage 0 or less is one row, not an unbounded or broken query.');
        $this->assertSame(0, $one['page']);
        $this->assertCount(1, $one['rows']);
    }

    /** A user at $path holding :overrideroute, as db/install.php grants it to the tenant-admin role. */
    private function override_router(string $path): \stdClass {
        global $DB;
        $u = $this->tenant_admin($path);
        $sys = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sentientia_request:overrideroute', CAP_ALLOW, $roleid, $sys->id);
        role_assign($roleid, $u->id, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function assert_decide_refused(int $requestid, int $deciderid, string $why): void {
        global $DB;
        try {
            request_manager::decide($requestid, $deciderid, 'approved', 'ok');
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
        $this->assertSame('pending', $DB->get_field('local_sentientia_request', 'status', ['id' => $requestid]),
            $why . ' The request must still be pending.');
    }

    public function test_override_router_with_no_tenant_cannot_decide_a_tenantless_request(): void {
        $orphan = $this->request(0);
        $own = $this->request(1);
        $router = $this->override_router('');
        $this->setUser($router);

        $this->assert_decide_refused($orphan, (int) $router->id,
            'Root 0 must not match a costcenterid-0 request.');
        $this->assert_decide_refused($own, (int) $router->id,
            'A decider with no tenant is in nobody\'s tenant.');
    }

    public function test_override_router_decides_only_their_own_tenant(): void {
        global $DB;
        $own = $this->request(1);
        $foreign = $this->request(177);
        $orphan = $this->request(0);
        $router = $this->override_router('/1');
        $this->setUser($router);
        $sink = $this->redirectMessages();

        $this->assert_decide_refused($foreign, (int) $router->id, 'Another tenant\'s request is refused.');
        $this->assert_decide_refused($orphan, (int) $router->id,
            'A tenant-less request is cross-tenant only, even for a tenant-scoped router.');

        request_manager::decide($own, (int) $router->id, 'rejected', 'Not this quarter');
        $this->assertSame('rejected', $DB->get_field('local_sentientia_request', 'status', ['id' => $own]));
        $sink->close();
    }

    public function test_site_admin_can_still_decide_a_tenantless_request(): void {
        global $DB;
        $orphan = $this->request(0);
        $this->setAdminUser();
        $sink = $this->redirectMessages();

        request_manager::decide($orphan, (int) get_admin()->id, 'rejected', 'Out of scope');
        $this->assertSame('rejected', $DB->get_field('local_sentientia_request', 'status', ['id' => $orphan]));
        $sink->close();
    }

    // ── The item: submit(), submit_path() and approval (follow-up 3) ─────

    /** A user at $path, reloaded so the record carries open_path and open_supervisorid. */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A course at $path (null = no path at all), visible unless told otherwise. */
    private function course_at(?string $path, bool $visible = true): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => $visible ? 1 : 0]);
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /** An active learning path at $path (null = no path at all). */
    private function path_at(?string $path): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name'         => 'Path at ' . ($path ?? 'none'),
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => $path,
            'status'       => \local_sentientia_learningpath\path_manager::STATUS_ACTIVE,
            'visible'      => 1,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A pending request stored directly - what a row submitted before submit()
     * checked the item looks like (UAT may hold such rows).
     */
    private function stored_request(\stdClass $requester, string $type, int $itemid,
                                    int $approverid, string $route = 'manager'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_request', (object) [
            'userid'          => $requester->id,
            'item_type'       => $type,
            'itemid'          => $itemid,
            'courseid'        => $type === request_manager::ITEM_COURSE ? $itemid : 0,
            'costcenterid'    => \local_sentientia_platform\tenant::root_for_user($requester),
            'reason'          => 'Stored before submit() checked the item.',
            'status'          => 'pending',
            'route'           => $route,
            'approver_userid' => $approverid,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /** Assert $fn throws error_outoftenant. */
    private function assert_outoftenant(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    public function test_submit_refuses_a_course_outside_the_requesters_tenant(): void {
        global $DB;
        $learner = $this->user_at('/77/80');
        $foreign = $this->course_at('/1');
        $prefixtrap = $this->course_at('/770');
        $hidden = $this->course_at('/77', false);
        $this->setUser($learner);
        $sink = $this->redirectMessages();
        $reason = 'I need this course for my quarterly certification plan.';

        $refused = [
            'a /1 course'                      => (int) $foreign->id,
            'a course at the /770 prefix trap' => (int) $prefixtrap->id,
            'a hidden course of their tenant'  => (int) $hidden->id,
            'a missing course id'              => 999999,
        ];
        foreach ($refused as $label => $courseid) {
            $this->assert_outoftenant(
                fn() => request_manager::submit((int) $learner->id, $courseid, $reason),
                "A /77 learner must not request {$label}.");
        }
        $this->assertSame(0, $DB->count_records('local_sentientia_request'));
        $this->assertSame(0, $sink->count(), 'A refused request notifies nobody.');

        // What stays requestable: their own tenant's course, a legacy course
        // with no path, and (with local_sentientia_courses) a course shared
        // to their tenant.
        $own = $this->course_at('/77/81');
        $legacy = $this->course_at(null);
        $rec = request_manager::submit((int) $learner->id, (int) $own->id, $reason);
        $this->assertSame('pending', $rec->status);
        $this->assertSame(77, (int) $rec->costcenterid);
        request_manager::submit((int) $learner->id, (int) $legacy->id, $reason);
        $expected = 2;
        if ($DB->get_manager()->table_exists('local_sentientia_courses_tenant_share')) {
            $DB->insert_record('local_sentientia_courses_tenant_share', (object) [
                'courseid'     => $foreign->id,
                'tenant_id'    => 77,
                'shared_by'    => get_admin()->id,
                'status'       => 'active',
                'timeshared'   => time(),
                'timemodified' => time(),
            ]);
            request_manager::submit((int) $learner->id, (int) $foreign->id, $reason);
            $expected++;
        }
        $this->assertSame($expected, $DB->count_records('local_sentientia_request',
            ['userid' => $learner->id, 'status' => 'pending']));
        $sink->close();
    }

    public function test_a_requester_with_no_tenant_can_request_nothing(): void {
        global $DB;
        $legacy = $this->course_at(null);
        $pathid = $this->path_at('/1');
        foreach (['', 'garbage'] as $path) {
            $nobody = $this->user_at($path);
            $this->setUser($nobody);
            $this->assert_outoftenant(fn() => request_manager::submit((int) $nobody->id,
                (int) $legacy->id, 'Even a legacy course is not for a user with no tenant.'),
                "open_path '{$path}' may request no course, not every tenant's.");
            $this->assert_outoftenant(fn() => request_manager::submit_path((int) $nobody->id,
                $pathid, 'Nor a learning path, for a user with no tenant.'),
                "open_path '{$path}' may request no path.");
        }
        $this->assertSame(0, $DB->count_records('local_sentientia_request'));
    }

    public function test_submit_path_refuses_another_tenants_path(): void {
        global $DB;
        $learner = $this->user_at('/77/80');
        $foreign = $this->path_at('/1');
        $unscoped = $this->path_at(null);
        $own = $this->path_at('/77');
        $this->setUser($learner);
        $sink = $this->redirectMessages();
        $reason = 'I need this learning path for my certification plan.';

        $this->assert_outoftenant(fn() => request_manager::submit_path((int) $learner->id,
            $foreign, $reason), 'A /77 learner must not request a /1 path.');
        $this->assert_outoftenant(fn() => request_manager::submit_path((int) $learner->id,
            $unscoped, $reason), 'Nor a path with no open_path (cross-tenant only).');
        $this->assert_outoftenant(fn() => request_manager::submit_path((int) $learner->id,
            999999, $reason), 'A missing path is refused like a foreign one.');
        $DB->set_field('local_sentientia_learningpath', 'status', 0, ['id' => $foreign]);
        $this->assert_outoftenant(fn() => request_manager::submit_path((int) $learner->id,
            $foreign, $reason), 'An archived foreign path does not reveal that it is archived.');
        $this->assertSame(0, $DB->count_records('local_sentientia_request'));

        $rec = request_manager::submit_path((int) $learner->id, $own, $reason);
        $this->assertSame(request_manager::ITEM_PATH, $rec->item_type);
        $this->assertSame(77, (int) $rec->costcenterid);
        $sink->close();
    }

    public function test_a_tenant_admin_cannot_approve_their_own_request_for_another_tenants_course(): void {
        global $DB;
        // The UAT repro: a /77 tenant admin holding :overrideroute (db/install.php
        // grants it, with :request and :approve, to the 'administrator' role).
        $admin = $this->override_router('/77');
        $foreign = $this->course_at('/1');
        $this->setUser($admin);
        $sink = $this->redirectMessages();

        $id = $this->stored_request($admin, request_manager::ITEM_COURSE, (int) $foreign->id,
            (int) get_admin()->id, 'admin');
        $this->assert_decide_refused($id, (int) $admin->id,
            'Approving their own request into a /1 course is refused.');
        $this->assertFalse(is_enrolled(\context_course::instance($foreign->id), (int) $admin->id));
        $this->assertSame(0, $sink->count(), 'A refused decision notifies nobody.');
        $sink->close();
    }

    public function test_an_assigned_approver_cannot_approve_a_report_into_another_tenants_item(): void {
        global $DB;
        $supervisor = $this->user_at('/77/80');
        $learner = $this->user_at('/77/80');
        $DB->set_field('user', 'open_supervisorid', $supervisor->id, ['id' => $learner->id]);
        $foreign = $this->course_at('/1');
        $foreignpath = $this->path_at('/1');
        $this->setUser($supervisor);
        $sink = $this->redirectMessages();

        $courserequest = $this->stored_request($learner, request_manager::ITEM_COURSE,
            (int) $foreign->id, (int) $supervisor->id);
        $pathrequest = $this->stored_request($learner, request_manager::ITEM_PATH,
            $foreignpath, (int) $supervisor->id);
        $this->assert_decide_refused($courserequest, (int) $supervisor->id,
            'Being the assigned approver is no exemption: the course is in /1.');
        $this->assert_decide_refused($pathrequest, (int) $supervisor->id,
            'Nor for a /1 learning path.');
        $this->assertFalse(is_enrolled(\context_course::instance($foreign->id), (int) $learner->id));
        $this->assertFalse($DB->record_exists('local_sentientia_learningpath_users',
            ['userid' => $learner->id]));

        // A rejection enrols nobody, so the approver can still turn it down.
        request_manager::decide($courserequest, (int) $supervisor->id, 'rejected',
            'That course belongs to another tenant.');
        $this->assertSame('rejected', $DB->get_field('local_sentientia_request', 'status',
            ['id' => $courserequest]));
        $sink->close();
    }

    public function test_an_approver_in_another_tenant_decides_nothing_for_this_one(): void {
        global $DB;
        $learner = $this->user_at('/77/80');
        $outsider = $this->user_at('/1/2');
        $course = $this->course_at('/77');
        $this->setUser($outsider);

        // Routed to a /1 user (a course owner, a stale supervisor link, a
        // scoped default approver): they may not decide for a /77 learner.
        $id = $this->stored_request($learner, request_manager::ITEM_COURSE, (int) $course->id,
            (int) $outsider->id, 'courseowner');
        foreach (['approved', 'rejected'] as $decision) {
            try {
                request_manager::decide($id, (int) $outsider->id, $decision, 'Deciding across tenants.');
                $this->fail("A /1 approver must not {$decision} a /77 learner's request.");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        }
        $this->assertSame('pending', $DB->get_field('local_sentientia_request', 'status', ['id' => $id]));
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), (int) $learner->id));
    }

    public function test_in_tenant_approval_still_enrols_and_a_cross_tenant_decider_is_not_scoped(): void {
        global $DB;
        $supervisor = $this->user_at('/1/2');
        $learner = $this->user_at('/1/3');
        $DB->set_field('user', 'open_supervisorid', $supervisor->id, ['id' => $learner->id]);
        $learner = $DB->get_record('user', ['id' => $learner->id], '*', MUST_EXIST);
        $own = $this->course_at('/1/5');
        $this->setUser($learner);
        $sink = $this->redirectMessages();

        // Airpay today: submit, routed to the supervisor, approved, enrolled.
        $rec = request_manager::submit((int) $learner->id, (int) $own->id,
            'I need this course for my onboarding plan this month.');
        $this->assertSame('manager', $rec->route);
        $this->assertSame((int) $supervisor->id, (int) $rec->approver_userid);
        $this->setUser($supervisor);
        request_manager::decide((int) $rec->id, (int) $supervisor->id, 'approved', 'Go ahead.');
        $this->assertSame('approved', $DB->get_field('local_sentientia_request', 'status', ['id' => $rec->id]));
        $this->assertTrue(is_enrolled(\context_course::instance($own->id), (int) $learner->id));

        // A cross-tenant decider (the site admin) is not held to the requester's scope.
        $zeea = $this->user_at('/177/178');
        $foreign = $this->course_at('/1');
        $id = $this->stored_request($zeea, request_manager::ITEM_COURSE, (int) $foreign->id,
            (int) get_admin()->id, 'admin');
        $this->setAdminUser();
        request_manager::decide($id, (int) get_admin()->id, 'approved', 'Platform L&D approves.');
        $this->assertTrue(is_enrolled(\context_course::instance($foreign->id), (int) $zeea->id));
        $sink->close();
    }
}
