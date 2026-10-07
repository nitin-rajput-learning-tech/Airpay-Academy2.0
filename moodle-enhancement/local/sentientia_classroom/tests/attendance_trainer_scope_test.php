<?php
// This file is part of Sentientia LMS.

/**
 * Who may take attendance for a session (owner decision 2026-09-30).
 *
 * The Sentientia/BizLMS `trainer` role is archetype `teacher`; :view and :attendance are granted at
 * system context, so on the capability alone a trainer could open and mark every classroom session
 * in their tenant. Without local/sentientia_classroom:update (the manager archetype, which the
 * tenant administrator role is, and which a site admin always has) the caller must now be the
 * assigned trainer of the session ({local_sentientia_classroom_sessions}.trainerid) or of its
 * classroom ({local_sentientia_classroom}.trainerid), or be listed as one of the classroom's other
 * trainers ({local_sentientia_classroom_trainers}, filled by the ADR-032 BizLMS import).
 * ADR-031 still bounds all of it to the tenant.
 *
 * The discriminator is :update, not :manage (follow-up 2026-09-30): on the prod-data copy the
 * BizLMS trainer role holds :manage but neither :create nor :update, so keyed on :manage it would
 * have been exempt. A role with :manage and no :update is restricted to its own sessions.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_classroom\session_manager::may_run_session
 * @covers \local_sentientia_classroom\session_manager::require_attendance_access
 * @covers \local_sentientia_classroom\external\bulk_mark_attendance
 * @covers \local_sentientia_classroom\external\mark_session_attendance
 * @covers \local_sentientia_classroom\external\list_session_attendance
 * @covers \local_sentientia_classroom\external\list_classroom_sessions
 * @group tenant_isolation
 */
final class attendance_trainer_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** A user in the given tenant path holding a system-level role with exactly these capabilities. */
    private function user_with(array $caps, string $path = '/1/2'): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $DB->set_field('user', 'open_designation', 'scopedesig', ['id' => $user->id]);
        $roleid = $this->getDataGenerator()->create_role();
        $sys = \context_system::instance();
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $sys->id, true);
        }
        role_assign($roleid, $user->id, $sys->id);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /** What the trainer role holds after the 2026093001 grant: view and attendance, never manage. */
    private function trainer(string $path = '/1/2'): \stdClass {
        return $this->user_with([
            'local/sentientia_classroom:view',
            'local/sentientia_classroom:attendance',
        ], $path);
    }

    /**
     * A manager-archetype holder (the tenant administrator role is one): manage, create and
     * update on top. :update is what frees them from the assigned-trainer rule.
     */
    private function manager(string $path = '/1/2'): \stdClass {
        return $this->user_with([
            'local/sentientia_classroom:manage',
            'local/sentientia_classroom:create',
            'local/sentientia_classroom:update',
            'local/sentientia_classroom:view',
            'local/sentientia_classroom:attendance',
        ], $path);
    }

    /**
     * The BizLMS trainer role as the prod-data copy has it: the legacy manageclassroom grant
     * became :manage, but it never held :create or :update.
     */
    private function trainer_with_manage(string $path = '/1/2'): \stdClass {
        return $this->user_with([
            'local/sentientia_classroom:manage',
            'local/sentientia_classroom:view',
            'local/sentientia_classroom:attendance',
        ], $path);
    }

    /** A role that holds :update and nothing of :manage: :update alone is the discriminator. */
    private function updater(string $path = '/1/2'): \stdClass {
        return $this->user_with([
            'local/sentientia_classroom:update',
            'local/sentientia_classroom:view',
            'local/sentientia_classroom:attendance',
        ], $path);
    }

    /** A classroom in tenant /1 (or $path) whose own trainer is $classroomtrainer (0 = none). */
    private function classroom(int $classroomtrainer = 0, string $path = '/1'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'Scope classroom', 'description' => '', 'costcenterid' => 0, 'open_path' => $path,
            'location' => 'Room', 'capacity' => 20, 'status' => session_manager::STATUS_ACTIVE,
            'trainerid' => $classroomtrainer > 0 ? $classroomtrainer : null,
            'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /** Lists $user as a trainer of the classroom the way the ADR-032 BizLMS import does (a trainers row). */
    private function add_co_trainer(int $classroomid, \stdClass $user): void {
        global $DB;
        $DB->insert_record('local_sentientia_classroom_trainers', (object) [
            'classroomid' => $classroomid, 'trainerid' => (int) $user->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /** A session of the classroom whose own trainer is $sessiontrainer (0 = none). */
    private function session(int $classroomid, int $sessiontrainer = 0, string $title = 'Day 1'): int {
        $start = time();
        return session_manager::create_session($classroomid, (object) [
            'title' => $title, 'starttime' => $start, 'endtime' => $start + HOURSECS,
            'trainerid' => $sessiontrainer,
        ]);
    }

    private function assert_refused(callable $call, string $code): void {
        try {
            $call();
            $this->fail('Expected ' . $code . ' but access was granted.');
        } catch (\moodle_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    // ═══ session_manager ═══

    public function test_the_trainer_of_a_session_may_open_it_and_another_trainer_may_not(): void {
        $own = $this->trainer();
        $other = $this->trainer();
        $classroomid = $this->classroom();
        $sessionid = $this->session($classroomid, (int) $own->id);

        $this->setUser($own);
        [$session, $classroom] = session_manager::require_attendance_access($sessionid);
        $this->assertSame($sessionid, (int) $session->id);
        $this->assertSame($classroomid, (int) $classroom->id);

        $this->setUser($other);
        $this->assert_refused(fn() => session_manager::require_attendance_access($sessionid), 'error_nottrainer');
    }

    public function test_the_trainer_of_the_classroom_may_open_all_its_sessions(): void {
        $lead = $this->trainer();
        $other = $this->trainer();
        $classroomid = $this->classroom((int) $lead->id);
        $nosession = $this->session($classroomid);
        $elsesession = $this->session($classroomid, (int) $other->id, 'Day 2');

        $this->setUser($lead);
        session_manager::require_attendance_access($nosession);
        session_manager::require_attendance_access($elsesession);

        // The session's own trainer runs it too, without being the classroom's.
        $this->setUser($other);
        session_manager::require_attendance_access($elsesession);
        $this->assert_refused(fn() => session_manager::require_attendance_access($nosession), 'error_nottrainer');
    }

    public function test_a_co_trainer_listed_on_the_classroom_may_open_and_mark_all_its_sessions(): void {
        global $DB;
        $lead = $this->trainer();
        $co = $this->trainer();
        $stranger = $this->trainer();
        $classroomid = $this->classroom((int) $lead->id);
        $sessionid = $this->session($classroomid, (int) $lead->id);
        $learner = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1/2', ['id' => $learner->id]);
        session_manager::enrol_users($classroomid, [(int) $learner->id]);

        // Before the trainers table lists them, they are neither the session's nor the classroom's trainer.
        $this->setUser($co);
        $this->assert_refused(fn() => session_manager::require_attendance_access($sessionid), 'error_nottrainer');

        // Listed on the classroom, the co-trainer runs a session that names somebody else.
        $this->add_co_trainer($classroomid, $co);
        $this->setUser($co);
        [$session, $classroom] = session_manager::require_attendance_access($sessionid);
        $this->assertSame($sessionid, (int) $session->id);
        $this->assertNotSame((int) $co->id, (int) $session->trainerid);
        $this->assertNotSame((int) $co->id, (int) $classroom->trainerid);

        // The web services and the session list agree with the page.
        $resp = external\bulk_mark_attendance::execute($sessionid, [
            ['userid' => (int) $learner->id, 'status' => session_manager::ATT_PRESENT, 'notes' => ''],
        ]);
        $this->assertSame(1, $resp['marked']);
        $this->assertSame(session_manager::ATT_LATE, external\mark_session_attendance::execute($sessionid,
            (int) $learner->id, session_manager::ATT_LATE, '')['status']);
        $this->assertSame(1, external\list_session_attendance::execute($sessionid)['total']);
        $rows = [];
        foreach (external\list_classroom_sessions::execute($classroomid)['rows'] as $row) {
            $rows[(int) $row['id']] = $row;
        }
        $this->assertStringContainsString('attendance.php?sessionid=' . $sessionid, $rows[$sessionid]['title']);

        // A user on no trainer row is still refused, and a row on another classroom grants nothing here.
        $this->setUser($stranger);
        $this->assert_refused(fn() => session_manager::require_attendance_access($sessionid), 'error_nottrainer');
        $this->add_co_trainer($this->classroom(), $stranger);
        $this->assert_refused(fn() => session_manager::require_attendance_access($sessionid), 'error_nottrainer');
        $this->assert_refused(fn() => external\list_session_attendance::execute($sessionid), 'error_nottrainer');
    }

    public function test_a_co_trainer_row_does_not_open_a_classroom_in_another_tenant(): void {
        $co = $this->trainer('/1/2');
        $classroomid = $this->classroom(0, '/77');
        $sessionid = $this->session($classroomid);
        $this->add_co_trainer($classroomid, $co);

        $this->setUser($co);
        $this->assert_refused(fn() => session_manager::require_attendance_access($sessionid), 'error_outoftenant');
    }

    public function test_a_session_and_classroom_with_no_trainer_are_for_managers_only(): void {
        $trainer = $this->trainer();
        $manager = $this->manager();
        $sessionid = $this->session($this->classroom());

        $this->setUser($trainer);
        $this->assert_refused(fn() => session_manager::require_attendance_access($sessionid), 'error_nottrainer');

        $this->setUser($manager);
        session_manager::require_attendance_access($sessionid);
    }

    public function test_a_manager_and_a_site_admin_open_any_session_in_their_tenant(): void {
        $manager = $this->manager();
        $someoneelse = $this->trainer();
        $sessionid = $this->session($this->classroom((int) $someoneelse->id), (int) $someoneelse->id);

        $this->setUser($manager);
        session_manager::require_attendance_access($sessionid);

        $this->setAdminUser();
        session_manager::require_attendance_access($sessionid);
    }

    public function test_a_role_holding_manage_without_update_is_restricted_to_its_own_sessions(): void {
        $lead = $this->trainer_with_manage();
        $other = $this->trainer_with_manage();
        $classroomid = $this->classroom();
        $own = $this->session($classroomid, (int) $lead->id, 'Own');
        $notmine = $this->session($classroomid, (int) $other->id, 'Not mine');
        $nobody = $this->session($classroomid, 0, 'Nobody');

        $this->setUser($lead);
        [$session] = session_manager::require_attendance_access($own);
        $this->assertSame($own, (int) $session->id, ':manage does not stop the assigned trainer opening their session.');
        $this->assert_refused(fn() => session_manager::require_attendance_access($notmine), 'error_nottrainer');
        $this->assert_refused(fn() => session_manager::require_attendance_access($nobody), 'error_nottrainer');

        // The web services and the session list apply the same rule.
        $this->assertSame(0, external\list_session_attendance::execute($own)['total']);
        $this->assert_refused(fn() => external\list_session_attendance::execute($notmine), 'error_nottrainer');
        $rows = [];
        foreach (external\list_classroom_sessions::execute($classroomid)['rows'] as $row) {
            $rows[(int) $row['id']] = $row;
        }
        $this->assertStringContainsString('attendance.php?sessionid=' . $own, $rows[$own]['title']);
        $this->assertStringNotContainsString('attendance.php', $rows[$notmine]['title']);
        $this->assertStringNotContainsString('attendance.php', $rows[$nobody]['title']);
    }

    public function test_a_role_holding_update_is_not_restricted_whether_or_not_it_holds_manage(): void {
        $updater = $this->updater();
        $someoneelse = $this->trainer();
        $assigned = $this->session($this->classroom((int) $someoneelse->id), (int) $someoneelse->id);
        $nobody = $this->session($this->classroom(), 0, 'Nobody');

        // :update alone (no :manage) frees the holder, on a session someone else runs and on one nobody runs.
        $this->setUser($updater);
        session_manager::require_attendance_access($assigned);
        session_manager::require_attendance_access($nobody);

        // ...and so does :update together with :manage, the manager archetype's pair.
        $this->setUser($this->manager());
        session_manager::require_attendance_access($assigned);
        session_manager::require_attendance_access($nobody);
    }

    public function test_the_tenant_guard_still_comes_first(): void {
        $trainer = $this->trainer('/1/2');
        $manager = $this->manager('/1/2');
        // Assigned to the trainer, but the classroom is in another tenant.
        $sessionid = $this->session($this->classroom((int) $trainer->id, '/77'), (int) $trainer->id);

        foreach ([$trainer, $manager] as $user) {
            $this->setUser($user);
            $this->assert_refused(fn() => session_manager::require_attendance_access($sessionid), 'error_outoftenant');
        }
    }

    public function test_may_run_session_answers_for_a_named_user_and_refuses_no_user(): void {
        global $DB;
        $trainer = $this->trainer();
        $other = $this->trainer();
        $manager = $this->manager();
        $classroomid = $this->classroom();
        $sessionid = $this->session($classroomid, (int) $trainer->id);
        $session = $DB->get_record('local_sentientia_classroom_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $classroom = $DB->get_record('local_sentientia_classroom', ['id' => $classroomid], '*', MUST_EXIST);

        $this->assertTrue(session_manager::may_run_session($session, $classroom, (int) $trainer->id));
        $this->assertFalse(session_manager::may_run_session($session, $classroom, (int) $other->id));
        $this->assertTrue(session_manager::may_run_session($session, $classroom, (int) $manager->id));
        // :manage without :update is not enough; :update without :manage is.
        $this->assertFalse(session_manager::may_run_session($session, $classroom,
            (int) $this->trainer_with_manage()->id));
        $this->assertTrue(session_manager::may_run_session($session, $classroom, (int) $this->updater()->id));
        // A trainers row makes a user a co-trainer of this classroom and of no other.
        $this->assertFalse(session_manager::may_run_session($session, $classroom, (int) $other->id));
        $this->add_co_trainer($classroomid, $other);
        $this->assertTrue(session_manager::may_run_session($session, $classroom, (int) $other->id));
        $this->assertFalse(session_manager::may_run_session($session,
            (object) ['id' => $classroomid + 1000, 'trainerid' => null], (int) $other->id));
        $this->setUser(null);
        $this->assertFalse(session_manager::may_run_session($session, $classroom), 'Nobody logged in: refused.');
    }

    // ═══ the entry points ═══

    public function test_the_attendance_web_services_refuse_a_trainer_who_is_not_assigned(): void {
        global $DB;
        $own = $this->trainer();
        $other = $this->trainer();
        $classroomid = $this->classroom();
        $sessionid = $this->session($classroomid, (int) $own->id);
        $learner = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1/2', ['id' => $learner->id]);
        session_manager::enrol_users($classroomid, [(int) $learner->id]);

        // The assigned trainer saves, marks one learner and reads the roster.
        $this->setUser($own);
        $resp = external\bulk_mark_attendance::execute($sessionid, [
            ['userid' => (int) $learner->id, 'status' => session_manager::ATT_PRESENT, 'notes' => ''],
        ]);
        $this->assertSame(1, $resp['marked']);
        $this->assertSame([], $resp['newermarks']);
        $resp = external\mark_session_attendance::execute($sessionid, (int) $learner->id,
            session_manager::ATT_LATE, '');
        $this->assertSame(session_manager::ATT_LATE, $resp['status']);
        $this->assertSame(1, external\list_session_attendance::execute($sessionid)['total']);

        // Another trainer in the same tenant is refused by all three, and nothing changes.
        $this->setUser($other);
        $this->assert_refused(fn() => external\bulk_mark_attendance::execute($sessionid, [
            ['userid' => (int) $learner->id, 'status' => session_manager::ATT_ABSENT, 'notes' => ''],
        ]), 'error_nottrainer');
        $this->assert_refused(fn() => external\mark_session_attendance::execute($sessionid, (int) $learner->id,
            session_manager::ATT_ABSENT, ''), 'error_nottrainer');
        $this->assert_refused(fn() => external\list_session_attendance::execute($sessionid), 'error_nottrainer');
        $this->assertSame(session_manager::ATT_LATE, (int) $DB->get_field('local_sentientia_classroom_attendance',
            'status', ['sessionid' => $sessionid, 'userid' => $learner->id]));

        // A manager is not bound to the session's trainer.
        $this->setUser($this->manager());
        $resp = external\bulk_mark_attendance::execute($sessionid, [
            ['userid' => (int) $learner->id, 'status' => session_manager::ATT_EXCUSED, 'notes' => ''],
        ]);
        $this->assertSame(1, $resp['marked']);
    }

    public function test_the_session_list_links_attendance_only_for_sessions_the_viewer_may_open(): void {
        $trainer = $this->trainer();
        $someoneelse = $this->trainer();
        $classroomid = $this->classroom();
        $mine = $this->session($classroomid, (int) $trainer->id, 'Mine');
        $theirs = $this->session($classroomid, (int) $someoneelse->id, 'Theirs');

        $this->setUser($trainer);
        $rows = [];
        foreach (external\list_classroom_sessions::execute($classroomid)['rows'] as $row) {
            $rows[(int) $row['id']] = $row;
        }
        $this->assertStringContainsString('attendance.php?sessionid=' . $mine, $rows[$mine]['title']);
        $this->assertStringContainsString('Mark attendance', $rows[$mine]['actions']);
        $this->assertStringNotContainsString('attendance.php', $rows[$theirs]['title'],
            'No link to a page that would refuse the viewer.');
        $this->assertStringNotContainsString('Mark attendance', $rows[$theirs]['actions']);
        $this->assertStringContainsString('Theirs', $rows[$theirs]['title'], 'The title itself still shows.');

        $this->setUser($this->manager());
        foreach (external\list_classroom_sessions::execute($classroomid)['rows'] as $row) {
            $this->assertStringContainsString('attendance.php?sessionid=' . (int) $row['id'], $row['title']);
            $this->assertStringContainsString('Mark attendance', $row['actions']);
        }
    }
}
