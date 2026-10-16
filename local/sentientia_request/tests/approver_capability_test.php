<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

/**
 * Visual-pass finding 7 (b), 2026-10-15: the line manager a request is routed to holds :approve.
 *
 * approver_routing picks the requester's line manager (user.open_supervisorid). On BizLMS data a line manager
 * is an ordinary employee, not a holder of the Moodle manager role, and :approve was granted to the manager
 * archetype only. So the named approver could not open approvals.php, was refused by the list_pending and
 * decide services, and never received request_pending (message_send() drops a notification whose provider
 * capability the user lacks). db/access.php now lists the authenticated-user archetype for :approve, as it
 * already did for :request; upgrade step 2026101501 back-fills existing sites (db/upgradelib.php).
 *
 * What this does NOT widen is authority: request_manager::decide() lets only the assigned approver (or a
 * tenant-checked :overrideroute holder) decide, and list_pending lists only what is assigned to the caller.
 * The tests pin both halves, and that the approvals entry is shown only to those a request can reach.
 *
 * @package    local_sentientia_request
 * @category   test
 * @covers     ::local_sentientia_request_backfill_approve_cap
 * @covers     \local_sentientia_request\request_manager::has_approvals_entry
 * @covers     \local_sentientia_request\request_manager::decide
 * @covers     \local_sentientia_request\external\list_pending
 * @covers     \local_sentientia_request\external\decide
 *
 * @group      local_sentientia_request
 * @group      tenant_isolation
 */
final class approver_capability_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var string The capability under test. */
    private const CAP = 'local/sentientia_request:approve';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** A user at $path, reloaded so the record carries open_path and open_supervisorid. */
    private function user_at(string $path, ?int $supervisorid = null): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        if ($supervisorid !== null) {
            $DB->set_field('user', 'open_supervisorid', $supervisorid, ['id' => $u->id]);
        }
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A visible course in the tenant's tree. */
    private function course_at(string $path): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /** The one message of $event sent to $userid, or null. */
    private function message_to(array $messages, int $userid, string $event): ?\stdClass {
        $found = array_values(array_filter($messages,
            fn($m) => (int) $m->useridto === $userid && $m->eventtype === $event));
        return $found[0] ?? null;
    }

    // The grant.

    public function test_the_authenticated_user_archetype_lists_approve_and_nothing_else_widened(): void {
        $user = get_default_capabilities('user');
        $this->assertSame(CAP_ALLOW, $user[self::CAP] ?? null, ':approve is granted to the authenticated user.');
        // :request was already there; the two that carry authority over other people's requests were not widened.
        $this->assertSame(CAP_ALLOW, $user['local/sentientia_request:request']);
        $this->assertArrayNotHasKey('local/sentientia_request:viewall', $user);
        $this->assertArrayNotHasKey('local/sentientia_request:overrideroute', $user);
        // The manager archetype keeps it.
        $this->assertSame(CAP_ALLOW, get_default_capabilities('manager')[self::CAP] ?? null);
    }

    public function test_an_ordinary_employee_holds_the_capability(): void {
        $employee = $this->user_at('/1/3');
        $this->setUser($employee);
        $this->assertTrue(has_capability(self::CAP, \context_system::instance()));
        $this->assertFalse(has_capability('local/sentientia_request:viewall', \context_system::instance()),
            'The grant is :approve only; the all-requests view stays with managers and tenant admins.');
    }

    // The defect: routed to a line manager who is an ordinary employee.

    public function test_a_plain_line_manager_is_notified_sees_and_decides_the_request_routed_to_them(): void {
        global $DB;
        $supervisor = $this->user_at('/1/2');
        $learner = $this->user_at('/1/3', (int) $supervisor->id);
        $course = $this->course_at('/1/5');
        $this->setUser($learner);
        $sink = $this->redirectMessages();

        $rec = request_manager::submit((int) $learner->id, (int) $course->id,
            'I need this course for my onboarding plan this month.');
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame('manager', $rec->route);
        $this->assertSame((int) $supervisor->id, (int) $rec->approver_userid);
        $this->assertNotNull($this->message_to($messages, (int) $supervisor->id, 'request_pending'),
            'The routed approver is sent request_pending; before the grant message_send() dropped it.');

        // They open the approvals page's service and see exactly that request.
        $this->setUser($supervisor);
        $this->assertTrue(has_capability(self::CAP, \context_system::instance()));
        $listed = external\list_pending::execute();
        $this->assertSame([(int) $rec->id], array_map('intval', array_column($listed['rows'], 'id')));

        // And decide it through the service, which enrols the learner.
        $sink = $this->redirectMessages();
        $result = external\decide::execute((int) $rec->id, 'approved', 'Go ahead.');
        $sink->close();
        $this->assertSame(['success' => true, 'status' => 'approved'], $result);
        $this->assertSame('approved', $DB->get_field('local_sentientia_request', 'status', ['id' => $rec->id]));
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), (int) $learner->id));
    }

    // What the grant does not widen.

    public function test_holding_the_capability_gives_no_authority_over_someone_elses_request(): void {
        global $DB;
        $supervisor = $this->user_at('/1/2');
        $learner = $this->user_at('/1/3', (int) $supervisor->id);
        $stranger = $this->user_at('/1/4');
        $course = $this->course_at('/1/5');
        $this->setUser($learner);
        $sink = $this->redirectMessages();
        $rec = request_manager::submit((int) $learner->id, (int) $course->id,
            'I need this course for my onboarding plan this month.');

        // A colleague holds :approve too, but sees nothing and is refused when they try to decide.
        $this->setUser($stranger);
        $this->assertTrue(has_capability(self::CAP, \context_system::instance()));
        $this->assertSame(0, external\list_pending::execute()['total'],
            'list_pending lists only what is assigned to the caller.');
        try {
            external\decide::execute((int) $rec->id, 'approved', 'I am not the approver.');
            $this->fail('decide() must refuse a user the request is not assigned to.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $sink->close();
        $this->assertSame('pending', $DB->get_field('local_sentientia_request', 'status', ['id' => $rec->id]));
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), (int) $learner->id));
    }

    // The approvals entry is offered only to those a request can reach.

    public function test_the_approvals_entry_is_not_shown_to_every_employee(): void {
        $employee = $this->user_at('/1/3');
        $this->assertFalse(request_manager::has_approvals_entry((int) $employee->id),
            'No reports and nothing assigned: a capability alone is not a reason to show the link.');
    }

    public function test_the_approvals_entry_is_shown_to_a_line_manager_and_to_an_assigned_approver(): void {
        global $DB;
        $supervisor = $this->user_at('/1/2');
        $this->assertFalse(request_manager::has_approvals_entry((int) $supervisor->id));

        // Somebody now reports to them.
        $learner = $this->user_at('/1/3', (int) $supervisor->id);
        $this->assertTrue(request_manager::has_approvals_entry((int) $supervisor->id),
            'A line manager is offered the page.');

        // A course owner or default approver with no reports: offered it once a request waits on them.
        $owner = $this->user_at('/1/4');
        $this->assertFalse(request_manager::has_approvals_entry((int) $owner->id));
        $DB->insert_record('local_sentientia_request', (object) [
            'userid'          => $learner->id,
            'item_type'       => 'course',
            'itemid'          => $this->course_at('/1/5')->id,
            'courseid'        => 0,
            'costcenterid'    => 1,
            'reason'          => 'A request routed to the course owner.',
            'status'          => 'pending',
            'route'           => 'courseowner',
            'approver_userid' => $owner->id,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        $this->assertTrue(request_manager::has_approvals_entry((int) $owner->id));
    }

    public function test_the_approvals_entry_is_kept_for_managers_and_denied_without_the_capability(): void {
        global $DB;
        // A manager-archetype user: no reports, no assigned request, still sees it, as before the grant.
        $manager = $this->user_at('/1/2');
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $manager->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(request_manager::has_approvals_entry((int) $manager->id));

        // An administrator can switch :approve off for a person; the entry follows the capability.
        $supervisor = $this->user_at('/1/6');
        $this->user_at('/1/7', (int) $supervisor->id);
        $this->assertTrue(request_manager::has_approvals_entry((int) $supervisor->id));
        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability(self::CAP, CAP_PROHIBIT, $userrole, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(request_manager::has_approvals_entry((int) $supervisor->id));
    }

    // The upgrade back-fill (step 2026101501).

    public function test_the_backfill_grants_the_authenticated_user_role_once_and_overrides_nothing(): void {
        global $DB;
        require_once(__DIR__ . '/../db/upgradelib.php');
        $sys = \context_system::instance();
        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);

        // A site as it was before the change: the role has no setting for :approve.
        unassign_capability(self::CAP, $userrole, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();
        $learner = $this->user_at('/1/3');
        $this->setUser($learner);
        $this->assertFalse(has_capability(self::CAP, $sys));

        $this->assertGreaterThanOrEqual(1, local_sentientia_request_backfill_approve_cap());
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::CAP, $sys));
        $this->assertEquals(CAP_ALLOW, $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $userrole, 'capability' => self::CAP, 'contextid' => $sys->id]));

        // Idempotent.
        $this->assertSame(0, local_sentientia_request_backfill_approve_cap());

        // An administrator's explicit PREVENT stands: the back-fill only fills a gap.
        assign_capability(self::CAP, CAP_PREVENT, $userrole, $sys->id, true);
        $this->assertSame(0, local_sentientia_request_backfill_approve_cap());
        $this->assertEquals(CAP_PREVENT, $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $userrole, 'capability' => self::CAP, 'contextid' => $sys->id]));
    }

    public function test_the_role_moodle_calls_the_default_user_role_is_back_filled_even_if_renamed(): void {
        global $CFG, $DB;
        require_once(__DIR__ . '/../db/upgradelib.php');
        $sys = \context_system::instance();
        // A restored BizLMS database can rename Authenticated user and clear its archetype; Moodle still
        // identifies it by $CFG->defaultuserroleid (and, usually, by shortname 'user').
        $renamed = create_role('Renamed authenticated', 'renamedauth', '', '');
        $other = create_role('Some other custom role', 'someothercustom', '', '');
        unassign_capability(self::CAP, $renamed, $sys->id);
        unassign_capability(self::CAP, $other, $sys->id);
        set_config('defaultuserroleid', $renamed);
        $CFG->defaultuserroleid = $renamed;

        local_sentientia_request_backfill_approve_cap();

        $this->assertEquals(CAP_ALLOW, $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $renamed, 'capability' => self::CAP, 'contextid' => $sys->id]),
            'The role the site treats as Authenticated user gets :approve, so its line managers can approve.');
        $this->assertFalse($DB->record_exists('role_capabilities',
            ['roleid' => $other, 'capability' => self::CAP, 'contextid' => $sys->id]),
            'A custom role that is not the default user role is still left alone.');
    }

    public function test_an_administrators_prohibit_on_the_default_user_role_stands(): void {
        global $CFG, $DB;
        require_once(__DIR__ . '/../db/upgradelib.php');
        $sys = \context_system::instance();
        $renamed = create_role('Renamed authenticated', 'renamedauth', '', '');
        assign_capability(self::CAP, CAP_PROHIBIT, $renamed, $sys->id, true);
        set_config('defaultuserroleid', $renamed);
        $CFG->defaultuserroleid = $renamed;

        local_sentientia_request_backfill_approve_cap();

        $this->assertEquals(CAP_PROHIBIT, $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $renamed, 'capability' => self::CAP, 'contextid' => $sys->id]));
    }

    public function test_the_backfill_touches_only_the_authenticated_user_archetype_and_only_approve(): void {
        global $DB;
        require_once(__DIR__ . '/../db/upgradelib.php');
        $sys = \context_system::instance();
        // A custom role of another archetype must not be granted anything.
        $studentlike = create_role('Plain student', 'plainstudent', 'A role of the student archetype.', 'student');
        $before = $DB->count_records('role_capabilities', ['roleid' => $studentlike]);

        local_sentientia_request_backfill_approve_cap();

        $this->assertSame($before, $DB->count_records('role_capabilities', ['roleid' => $studentlike]));
        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        foreach (['viewall', 'overrideroute'] as $name) {
            $this->assertFalse($DB->record_exists('role_capabilities', [
                'roleid' => $userrole, 'capability' => 'local/sentientia_request:' . $name,
                'contextid' => $sys->id, 'permission' => CAP_ALLOW,
            ]), ":{$name} is not given to every employee.");
        }
    }
}
