<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Persona pass D4 (2026-09-30): the Team performance page loaded for a line
 * manager and then showed "Failed to load data", because the web service behind
 * it refused them.
 *
 * Two defects sat in local_sentientia_manager_team_performance:
 *
 *   - It called require_capability('local/sentientia_manager:view'). A supervisor
 *     who was never given the Moodle `manager` role does not hold it, so the
 *     people the page exists for (about 100 of the 110 supervisors on the
 *     production data) were refused. The page itself, list_requests and
 *     list_allocations already used team_manager::require_manage(), the
 *     supervisor-aware gate; this service had been missed.
 *   - Once past the gate it selected u.open_managerid, a column that does not
 *     exist, and answered "team detection unavailable". The reporting line is
 *     user.open_supervisorid, read through the local_sentientia_core\org seam.
 *
 * These tests fix the observable behaviour: a line manager sees their own
 * direct reports and nobody else, a user who manages nobody is refused, and a
 * manager in one tenant cannot see (or ask for) another tenant's team.
 *
 * Most tests build the reporting line as org-model edges
 * (local_sentientia_org_member.managerid, org_legacy OFF), like
 * team_manager_test. The seam caches "is open_supervisorid queryable?" for the
 * life of the PHP process, and local_sentientia_core's own tests run against a
 * schema without the column, so the legacy (open_supervisorid) path has one
 * test of its own that skips itself rather than fail on that cache.
 *
 * @package    local_sentientia_manager
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_manager\external\team_performance
 * @covers \local_sentientia_manager\team_manager
 * @group tenant_isolation
 */
final class team_performance_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        // The org model is the source of the reporting line in these tests.
        set_config('org_legacy', 0, 'local_sentientia_core');
    }

    /** A user at $path, reloaded so the record carries open_path. */
    private function user_at(string $path, array $record = []): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user($record);
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** Insert an org unit, return its id. */
    private function make_unit(): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_org_unit', (object) [
            'parentid' => 0,
            'tenantrootid' => 1,
            'name' => 'Unit',
            'status' => 'active',
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    /** Make $mgr the direct line manager of $report (an org-model edge). */
    private function report_to(\stdClass $report, \stdClass $mgr, int $unitid): void {
        global $DB;
        $DB->insert_record('local_sentientia_org_member', (object) [
            'userid' => $report->id,
            'unitid' => $unitid,
            'role' => 'member',
            'managerid' => $mgr->id,
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    /** A tenant admin as UAT has them: a manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $admin = $this->user_at($path);
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $admin->id, \context_system::instance()->id);
        return $admin;
    }

    /** Call the web service as $as. */
    private function call(\stdClass $as, int $managerid = 0, int $days = 30): array {
        $this->setUser($as);
        return external\team_performance::execute($managerid, $days);
    }

    /** The user ids in a response, in the order returned. */
    private function team_ids(array $result): array {
        return array_map('intval', array_column($result['team'], 'userid'));
    }

    /** Assert $fn is refused by the supervisor-aware manager gate. */
    private function assert_refused(callable $fn, string $message): void {
        try {
            $fn();
            $this->fail($message . ' (no exception)');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode, $message);
        }
    }

    public function test_a_line_manager_sees_their_direct_reports_and_nobody_else(): void {
        global $DB;
        $unit = $this->make_unit();
        $mgr = $this->user_at('/1/2');
        $alpha = $this->user_at('/1/2', ['firstname' => 'Asha', 'lastname' => 'Alpha']);
        $beta = $this->user_at('/1/2', ['firstname' => 'Bala', 'lastname' => 'Beta']);
        $suspended = $this->user_at('/1/2', ['firstname' => 'Sam', 'lastname' => 'Suspended']);
        $deleted = $this->user_at('/1/2', ['firstname' => 'Dee', 'lastname' => 'Deleted']);
        $bystander = $this->user_at('/1/2');          // Same tenant, same unit, not a report.
        $othermgr = $this->user_at('/1/2');
        $othersreport = $this->user_at('/1/2');
        foreach ([$alpha, $beta, $suspended, $deleted] as $r) {
            $this->report_to($r, $mgr, $unit);
        }
        $this->report_to($othersreport, $othermgr, $unit);
        $DB->set_field('user', 'suspended', 1, ['id' => $suspended->id]);
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

        $result = $this->call($mgr);

        $this->assertSame([(int) $alpha->id, (int) $beta->id], $this->team_ids($result),
            'Active direct reports only, by last name; not a suspended or deleted report, '
            . 'a colleague, the manager, or another manager\'s report.');
        $this->assertSame((int) $mgr->id, $result['managerid']);
        $this->assertSame('', $result['message'], 'No "team detection unavailable" answer.');
        $this->assertSame(30, $result['period_days']);
    }

    public function test_a_supervisor_who_was_never_given_the_manager_role_is_admitted(): void {
        $unit = $this->make_unit();
        $supervisor = $this->user_at('/1/2');
        $report = $this->user_at('/1/2');
        $this->report_to($report, $supervisor, $unit);

        // The D4 precondition: this is a supervisor, not a Moodle manager.
        $this->assertFalse(has_capability('local/sentientia_manager:view',
            \context_system::instance(), $supervisor->id),
            'A supervisor with no role holds no :view - the capability the service used to demand.');
        $this->assertTrue(team_manager::can_manage((int) $supervisor->id));

        $result = $this->call($supervisor);
        $this->assertSame([(int) $report->id], $this->team_ids($result),
            'The page and its web service now agree about who may look.');
    }

    public function test_a_user_who_manages_nobody_is_refused(): void {
        $unit = $this->make_unit();
        $mgr = $this->user_at('/1/2');
        $report = $this->user_at('/1/2');
        $learner = $this->user_at('/1/2');
        $this->report_to($report, $mgr, $unit);

        $this->assertFalse(team_manager::can_manage((int) $learner->id), 'Precondition.');
        $this->assert_refused(fn() => $this->call($learner),
            'A user with no reports and no :view is not a manager.');
        $this->assert_refused(fn() => $this->call($report),
            'Being managed is not managing.');
        $this->assert_refused(fn() => $this->call($learner, (int) $mgr->id),
            'Naming a manager is not a way in.');
    }

    public function test_a_manager_of_another_tenant_sees_nothing_of_this_one(): void {
        $unit = $this->make_unit();
        $airpaymgr = $this->user_at('/1/2');
        $airpayreport = $this->user_at('/1/2');
        $publicmgr = $this->user_at('/77');
        $publicreport = $this->user_at('/77');
        $this->report_to($airpayreport, $airpaymgr, $unit);
        $this->report_to($publicreport, $publicmgr, $unit);

        // Each manager sees their own team and none of the other tenant's.
        $mine = $this->call($publicmgr);
        $this->assertSame([(int) $publicreport->id], $this->team_ids($mine));
        $this->assertStringNotContainsString($airpayreport->email, json_encode($mine),
            'Nothing of the other tenant is in the payload.');
        $theirs = $this->call($airpaymgr);
        $this->assertSame([(int) $airpayreport->id], $this->team_ids($theirs));
        $this->assertStringNotContainsString($publicreport->email, json_encode($theirs));

        // Naming the other tenant's manager does not open their team.
        try {
            $this->call($publicmgr, (int) $airpaymgr->id);
            $this->fail('A /77 manager read a /1 manager\'s team.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        // Neither does a tenant admin, whose :view comes from the manager
        // archetype - they see their own (empty) team, not another manager's.
        $publicadmin = $this->tenant_admin('/77');
        $this->assertTrue(has_capability('local/sentientia_manager:view',
            \context_system::instance(), $publicadmin->id), 'Precondition: tenant admins hold :view.');
        $this->assertSame([], $this->team_ids($this->call($publicadmin)));
        foreach ([$airpaymgr, $publicmgr] as $named) {
            try {
                $this->call($publicadmin, (int) $named->id);
                $this->fail('A tenant admin read another manager\'s team.');
            } catch (\moodle_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
    }

    public function test_the_site_admin_can_still_read_any_managers_team(): void {
        $unit = $this->make_unit();
        $airpaymgr = $this->user_at('/1/2');
        $airpayreport = $this->user_at('/1/2');
        $publicmgr = $this->user_at('/77');
        $publicreport = $this->user_at('/77');
        $this->report_to($airpayreport, $airpaymgr, $unit);
        $this->report_to($publicreport, $publicmgr, $unit);

        $admin = get_admin();
        $this->assertSame([(int) $airpayreport->id], $this->team_ids($this->call($admin, (int) $airpaymgr->id)));
        $this->assertSame([(int) $publicreport->id], $this->team_ids($this->call($admin, (int) $publicmgr->id)));
        // With no managerid the admin gets their own (empty) team.
        $this->assertSame([], $this->team_ids($this->call($admin)));
    }

    public function test_the_service_does_not_need_an_open_managerid_column(): void {
        global $DB;
        if (array_key_exists('open_managerid', $DB->get_columns('user'))) {
            $this->markTestSkipped('This database has a user.open_managerid column, so it proves nothing.');
        }
        $unit = $this->make_unit();
        $mgr = $this->user_at('/1/2');
        $report = $this->user_at('/1/2');
        $this->report_to($report, $mgr, $unit);

        $result = $this->call($mgr);
        $this->assertSame('', $result['message'],
            'The old service answered "open_managerid column missing" here.');
        $this->assertSame([(int) $report->id], $this->team_ids($result));
    }

    public function test_the_row_carries_the_members_learning_numbers(): void {
        global $DB;
        $unit = $this->make_unit();
        $mgr = $this->user_at('/1/2');
        $active = $this->user_at('/1/2', ['firstname' => 'Asha', 'lastname' => 'Active']);
        $idle = $this->user_at('/1/2', ['firstname' => 'Ivan', 'lastname' => 'Idle']);
        $this->report_to($active, $mgr, $unit);
        $this->report_to($idle, $mgr, $unit);
        $DB->set_field('user', 'open_employeeid', 'EMP-7', ['id' => $active->id]);
        $DB->set_field('user', 'open_designation', 'Analyst', ['id' => $active->id]);
        $DB->set_field('user', 'lastaccess', time() - 3600, ['id' => $active->id]);

        // Asha finished one of her two courses an hour ago.
        $done = $this->getDataGenerator()->create_course();
        $open = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($active->id, $done->id);
        $this->getDataGenerator()->enrol_user($active->id, $open->id);
        $DB->insert_record('course_completions', (object) [
            'userid' => $active->id, 'course' => $done->id, 'timeenrolled' => 0,
            'timestarted' => 0, 'timecompleted' => time() - 3600, 'reaggregate' => 0,
        ]);

        $result = $this->call($mgr);
        // The response must satisfy the structure the service declares.
        $clean = \core_external\external_api::clean_returnvalue(
            external\team_performance::execute_returns(), $result);
        $this->assertCount(2, $clean['team']);

        $rows = array_column($clean['team'], null, 'userid');
        $row = $rows[(int) $active->id];
        $this->assertSame(trim($active->firstname . ' ' . $active->lastname), $row['fullname']);
        $this->assertSame($active->email, $row['email']);
        $this->assertSame('EMP-7', $row['employee_id']);
        $this->assertSame('Analyst', $row['designation']);
        $this->assertSame(1, $row['completions']);
        $this->assertSame(0, $row['attempts']);
        $this->assertSame(2, $row['enrolled']);
        $this->assertSame(1, $row['completed_all']);
        $this->assertEqualsWithDelta(50.0, $row['completion_pct'], 0.01);
        $this->assertTrue((bool) $row['is_active']);
        $this->assertNotSame('Never', $row['last_access']);

        $quiet = $rows[(int) $idle->id];
        $this->assertSame(0, $quiet['enrolled']);
        $this->assertEqualsWithDelta(0.0, $quiet['completion_pct'], 0.01);
        $this->assertFalse((bool) $quiet['is_active']);
        $this->assertSame('Never', $quiet['last_access']);
    }

    public function test_the_period_bounds_the_recent_completions_only(): void {
        global $DB;
        $unit = $this->make_unit();
        $mgr = $this->user_at('/1/2');
        $report = $this->user_at('/1/2');
        $this->report_to($report, $mgr, $unit);
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($report->id, $course->id);
        $DB->insert_record('course_completions', (object) [
            'userid' => $report->id, 'course' => $course->id, 'timeenrolled' => 0,
            'timestarted' => 0, 'timecompleted' => time() - (40 * DAYSECS), 'reaggregate' => 0,
        ]);

        $short = $this->call($mgr, 0, 30)['team'][0];
        $this->assertSame(0, $short['completions'], 'Forty days ago is outside a 30-day window.');
        $this->assertSame(1, $short['completed_all'], 'It still counts towards all-time completion.');
        $long = $this->call($mgr, 0, 90);
        $this->assertSame(90, $long['period_days']);
        $this->assertSame(1, $long['team'][0]['completions'], 'And inside a 90-day one.');
    }

    public function test_the_team_is_the_open_supervisorid_reporting_line(): void {
        global $DB;
        // The production path: org_legacy ON reads user.open_supervisorid.
        set_config('org_legacy', 1, 'local_sentientia_core');
        $mgr = $this->user_at('/1/2');
        $other = $this->user_at('/1/2');
        $report = $this->user_at('/1/2');
        $strangers = $this->user_at('/1/2');
        $DB->set_field('user', 'open_supervisorid', $mgr->id, ['id' => $report->id]);
        $DB->set_field('user', 'open_supervisorid', $other->id, ['id' => $strangers->id]);
        if (\local_sentientia_core\org::direct_reports((int) $mgr->id) !== [(int) $report->id]) {
            $this->markTestSkipped('The org seam cached open_supervisorid as absent earlier in this '
                . 'PHP process (another suite ran on the vanilla schema first); run this class alone.');
        }

        $this->assertFalse(has_capability('local/sentientia_manager:view',
            \context_system::instance(), $mgr->id), 'Precondition: a supervisor, not a manager role.');
        $this->assertSame([(int) $report->id], $this->team_ids($this->call($mgr)));
        $this->assertSame([(int) $strangers->id], $this->team_ids($this->call($other)));
    }

    public function test_the_member_refusal_message_exists_in_both_languages(): void {
        // member.php's gate is team_manager::can_view_member(); its refusal used
        // to name the undefined core string 'nopermission' and rendered
        // "[[nopermission]]". It now names a string this plugin ships.
        foreach (['en', 'hi'] as $lang) {
            $string = [];
            include(__DIR__ . '/../lang/' . $lang . '/local_sentientia_manager.php');
            $this->assertArrayHasKey('error_cannotviewmember', $string, "Missing from the {$lang} pack.");
            $this->assertNotSame('', trim($string['error_cannotviewmember']));
        }
        // Legacy seam for this walk: with org_legacy OFF an unmapped target makes the
        // seam log a developer fallback notice, which is not what this test is about.
        set_config('org_legacy', 1, 'local_sentientia_core');
        $unrelated = $this->user_at('/1/2');
        $target = $this->user_at('/1/3');
        $this->assertFalse(team_manager::can_view_member((int) $unrelated->id, (int) $target->id),
            'An unrelated viewer is refused - with that message.');
    }
}
