<?php
// This file is part of Sentientia LMS.

/**
 * Trainers see only the classrooms they train (ADR-036 D10, owner decision D15) and external trainers
 * always do, by name only (ADR-036 D9). Session 5, 2026-11-20.
 *
 * With sentientia.classroom.trainer_own_scope.enabled OFF (the default) an internal trainer still sees
 * every classroom of their tenant, as before. With it ON, a holder of :view without :update sees the
 * list, the classroom page, its sessions, roster and waiting list only for a classroom where they are
 * the primary trainer, a co-trainer or a session trainer. Holders of :update (managers, the tenant
 * administrator role) and site admins are never limited. An external trainer (a row in
 * {local_sentientia_external_trainer_profile}, local_sentientia_users) is limited whatever the flag says,
 * sees rosters without e-mail, employee id or designation, and never gets the :update shortcut on
 * attendance. The ADR-031 tenant guard still runs first: the rule only narrows it.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * @covers \local_sentientia_classroom\session_manager::trainer_scope_applies
 * @covers \local_sentientia_classroom\session_manager::trainer_scope_sql
 * @covers \local_sentientia_classroom\session_manager::require_trainer_scope
 * @covers \local_sentientia_classroom\session_manager::is_trainer_of
 * @covers \local_sentientia_classroom\session_manager::roster_names_only
 * @covers \local_sentientia_classroom\session_manager::trainer_last_session_end
 * @covers \local_sentientia_classroom\session_manager::may_run_session
 * @covers \local_sentientia_classroom\external\list_classrooms
 * @covers \local_sentientia_classroom\external\list_classroom_users
 * @covers \local_sentientia_classroom\external\list_classroom_sessions
 * @covers \local_sentientia_classroom\external\list_waitlist
 * @covers \local_sentientia_classroom\external\waitlist_join
 * @group local_sentientia_classroom
 * @group tenant_isolation
 */
final class trainer_own_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        feature_flags::invalidate_caches();
    }

    /** A user in $path holding a system-level role with exactly these capabilities. */
    private function user_with(array $caps, string $path = '/1/2'): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'scope' . random_string(8) . '@example.com']);
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $DB->set_field('user', 'open_employeeid', 'EMP-' . $user->id, ['id' => $user->id]);
        $DB->set_field('user', 'open_designation', 'scopedesig', ['id' => $user->id]);
        if ($caps) {
            $roleid = $this->getDataGenerator()->create_role();
            $sys = \context_system::instance();
            foreach ($caps as $cap) {
                assign_capability($cap, CAP_ALLOW, $roleid, $sys->id, true);
            }
            role_assign($roleid, $user->id, $sys->id);
            accesslib_clear_all_caches_for_unit_testing();
        }
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /** What the trainer role holds after the 2026093001 grant: view and attendance. */
    private function trainer(string $path = '/1/2'): \stdClass {
        return $this->user_with(['local/sentientia_classroom:view', 'local/sentientia_classroom:attendance'], $path);
    }

    /** A manager-archetype holder: :update frees them from the rule. */
    private function manager(string $path = '/1/2'): \stdClass {
        return $this->user_with([
            'local/sentientia_classroom:manage', 'local/sentientia_classroom:create',
            'local/sentientia_classroom:update', 'local/sentientia_classroom:view',
            'local/sentientia_classroom:attendance',
        ], $path);
    }

    /** Mark an account as an external trainer the way local_sentientia_users does (its profile row). */
    private function make_external(\stdClass $user): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_external_trainer_profile')
                || !class_exists('\\local_sentientia_users\\external_trainer\\manager')) {
            $this->markTestSkipped('local_sentientia_users with the external-trainer table is not installed');
        }
        $now = time();
        $DB->insert_record('local_sentientia_external_trainer_profile', (object) [
            'userid' => (int) $user->id, 'organisation' => '', 'invitedby' => 0, 'open_path' => '/1',
            'validfrom' => $now, 'expires_at' => $now + 30 * DAYSECS, 'status' => 'active', 'remindedat' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    private function classroom(int $trainerid = 0, string $path = '/1', int $status = session_manager::STATUS_ACTIVE): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'Own scope ' . random_string(4), 'description' => '', 'costcenterid' => 0, 'open_path' => $path,
            'location' => 'Room', 'capacity' => 20, 'status' => $status,
            'trainerid' => $trainerid > 0 ? $trainerid : null,
            'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function session(int $classroomid, int $trainerid = 0, ?int $start = null, int $length = HOURSECS): int {
        $start = $start ?? time();
        return session_manager::create_session($classroomid, (object) [
            'title' => 'Day', 'starttime' => $start, 'endtime' => $start + $length, 'trainerid' => $trainerid,
        ]);
    }

    private function enrol(int $classroomid, \stdClass $user): void {
        global $DB;
        $DB->insert_record('local_sentientia_classroom_users', (object) [
            'classroomid' => $classroomid, 'userid' => (int) $user->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function switch_on(): void {
        global $USER;
        $previous = $USER;
        $this->setAdminUser();
        feature_flags::set(session_manager::OWN_SCOPE_FLAG, 0, true);
        $this->setUser($previous);
    }

    private function listed_ids(): array {
        $result = external\list_classrooms::execute('', 'name', 'asc', 0, 100, '{}');
        return array_map(fn($r) => (int) $r['id'], $result['rows']);
    }

    private function assert_refused(callable $call, string $code): void {
        try {
            $call();
            $this->fail('Expected ' . $code . ' but access was granted.');
        } catch (\moodle_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    // ═══ The flag ═══

    public function test_the_flag_is_registered_and_off_by_default(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey('sentientia.classroom.trainer_own_scope.enabled', $registry);
        $this->assertSame('sentientia.classroom.trainer_own_scope.enabled', session_manager::OWN_SCOPE_FLAG);
        $this->assertFalse($registry[session_manager::OWN_SCOPE_FLAG]['default']);
    }

    public function test_flag_off_a_trainer_still_sees_every_classroom_of_the_tenant(): void {
        $trainer = $this->trainer();
        $own = $this->classroom((int) $trainer->id);
        $other = $this->classroom();

        $this->setUser($trainer);
        $this->assertFalse(session_manager::trainer_scope_applies());
        $this->assertSame(['1=1', []], session_manager::trainer_scope_sql('c'));
        $this->assertEqualsCanonicalizing([$own, $other], $this->listed_ids());
        session_manager::require_trainer_scope(session_manager::get($other));
        $this->assertSame(0, external\list_classroom_users::execute($other)['total']);
        $this->assertFalse(session_manager::roster_names_only());
    }

    // ═══ Flag ON: internal trainers ═══

    public function test_flag_on_a_trainer_sees_and_opens_only_the_classrooms_they_train(): void {
        $trainer = $this->trainer();
        $own = $this->classroom((int) $trainer->id);
        $other = $this->classroom();
        $this->setUser($trainer);
        $this->switch_on();

        $this->assertTrue(session_manager::trainer_scope_applies());
        $this->assertSame([$own], $this->listed_ids());
        session_manager::require_trainer_scope(session_manager::get($own));
        $this->assert_refused(fn() => session_manager::require_trainer_scope(session_manager::get($other)),
            'error_notyourclassroom');
        $this->assert_refused(fn() => external\list_classroom_users::execute($other), 'error_notyourclassroom');
        $this->assert_refused(fn() => external\list_classroom_sessions::execute($other), 'error_notyourclassroom');
        $this->assert_refused(fn() => external\list_waitlist::execute($other), 'error_notyourclassroom');
        // Their own classroom still works.
        $this->assertSame(0, external\list_classroom_users::execute($own)['total']);
    }

    public function test_a_co_trainer_and_a_session_trainer_count_as_trainers_of_the_classroom(): void {
        global $DB;
        $co = $this->trainer();
        $sessiontrainer = $this->trainer();
        $stranger = $this->trainer();
        $classroomid = $this->classroom();
        $DB->insert_record('local_sentientia_classroom_trainers', (object) [
            'classroomid' => $classroomid, 'trainerid' => (int) $co->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->session($classroomid, (int) $sessiontrainer->id);
        $this->switch_on();

        foreach ([$co, $sessiontrainer] as $user) {
            $this->setUser($user);
            $this->assertTrue(session_manager::is_trainer_of($classroomid));
            $this->assertSame([$classroomid], $this->listed_ids());
        }
        $this->setUser($stranger);
        $this->assertFalse(session_manager::is_trainer_of($classroomid));
        $this->assertSame([], $this->listed_ids());
    }

    public function test_flag_on_a_holder_of_update_and_a_site_admin_are_not_limited(): void {
        $manager = $this->manager();
        $a = $this->classroom();
        $b = $this->classroom();
        $this->switch_on();

        $this->setUser($manager);
        $this->assertFalse(session_manager::trainer_scope_applies());
        $this->assertEqualsCanonicalizing([$a, $b], $this->listed_ids());

        $this->setAdminUser();
        $this->assertFalse(session_manager::trainer_scope_applies());
        session_manager::require_trainer_scope(session_manager::get($a));
    }

    public function test_the_rule_never_widens_the_tenant_guard(): void {
        $trainer = $this->trainer('/1/2');
        $elsewhere = $this->classroom((int) $trainer->id, '/77');
        $this->switch_on();
        $this->setUser($trainer);

        // They train it, but it is another tenant's classroom: ADR-031 refuses first.
        $this->assertSame([], $this->listed_ids());
        $this->assert_refused(fn() => external\list_classroom_users::execute($elsewhere), 'error_outoftenant');
    }

    public function test_the_tiles_and_the_list_count_the_same_set(): void {
        global $DB;
        $trainer = $this->trainer();
        $this->classroom((int) $trainer->id);
        $this->classroom();
        $this->switch_on();
        $this->setUser($trainer);

        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_filter('c');
        [$ownsql, $ownargs] = session_manager::trainer_scope_sql('c');
        $count = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {local_sentientia_classroom} c WHERE $tnsql AND $ownsql", $tnargs + $ownargs);
        $this->assertSame(1, $count);
        $this->assertSame(1, external\list_classrooms::execute('', 'name', 'asc', 0, 100, '{}')['total']);
    }

    public function test_the_rule_is_resolved_for_the_named_users_tenant(): void {
        global $DB;
        $onone = $this->trainer('/1/2');
        $onzeea = $this->trainer('/177/3');
        $this->setAdminUser();
        // ON for tenant 1 only; tenant 177 keeps the default (OFF).
        feature_flags::set(session_manager::OWN_SCOPE_FLAG, 1, true);

        $this->assertTrue(session_manager::trainer_scope_applies((int) $onone->id));
        $this->assertFalse(session_manager::trainer_scope_applies((int) $onzeea->id));
    }

    // ═══ External trainers (always limited, names only) ═══

    public function test_an_external_trainer_is_limited_with_the_flag_off(): void {
        $external = $this->trainer();
        $this->make_external($external);
        $own = $this->classroom((int) $external->id);
        $other = $this->classroom();

        $this->setUser($external);
        $this->assertTrue(session_manager::is_external_trainer((int) $external->id));
        $this->assertTrue(session_manager::trainer_scope_applies());
        $this->assertSame([$own], $this->listed_ids());
        $this->assert_refused(fn() => external\list_classroom_users::execute($other), 'error_notyourclassroom');
    }

    public function test_an_external_trainer_sees_the_roster_by_name_only(): void {
        $external = $this->trainer();
        $this->make_external($external);
        $classroomid = $this->classroom((int) $external->id);
        $learner = $this->user_with([], '/1/2');
        $this->enrol($classroomid, $learner);

        $this->setUser($external);
        $this->assertTrue(session_manager::roster_names_only());
        $result = external\list_classroom_users::execute($classroomid);
        $this->assertSame(1, $result['total']);
        $row = $result['rows'][0];
        $this->assertSame('', $row['email']);
        $this->assertSame('', $row['employeeid']);
        $this->assertSame('', $row['designation']);
        $this->assertStringContainsString(s(fullname($learner)), $row['name']);
        $this->assertStringNotContainsString('profile.php', $row['name']);
        $this->assertSame('', $row['actions']);

        // The search does not match e-mail addresses for them.
        $this->assertSame(0, external\list_classroom_users::execute($classroomid, $learner->email)['total']);
        $this->assertSame(1, external\list_classroom_users::execute($classroomid, $learner->lastname)['total']);

        // An internal trainer of the same classroom still sees the e-mail.
        $internal = $this->trainer();
        $this->setUser($internal);
        $this->assertSame($learner->email,
            external\list_classroom_users::execute($classroomid)['rows'][0]['email']);
    }

    public function test_an_external_trainer_attendance_list_has_no_email(): void {
        $external = $this->trainer();
        $this->make_external($external);
        $classroomid = $this->classroom((int) $external->id);
        $sessionid = $this->session($classroomid);
        $this->enrol($classroomid, $this->user_with([], '/1/2'));

        $this->setUser($external);
        $rows = external\list_session_attendance::execute($sessionid)['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['email']);
    }

    public function test_an_external_trainer_never_gets_the_update_shortcut(): void {
        $external = $this->manager();   // Holds :update by a hand-made role.
        $this->make_external($external);
        $classroomid = $this->classroom();
        $sessionid = $this->session($classroomid);

        $this->setUser($external);
        $this->assertTrue(session_manager::trainer_scope_applies());
        $this->assert_refused(fn() => session_manager::require_attendance_access($sessionid), 'error_nottrainer');
    }

    /**
     * Review fix 2026-11-20: waitlist_join needs only :view, which the external-trainer role holds, so an
     * external trainer could join any classroom of the tenant by id (learning that it exists and its
     * queue position) and be auto-promoted into it as a learner. They are never learners: refused for
     * every id, with the same answer for a missing id, another tenant's classroom, another classroom of
     * their tenant and their own, and nothing is written.
     */
    public function test_an_external_trainer_never_joins_a_waiting_list(): void {
        global $DB;
        $external = $this->trainer();
        $this->make_external($external);
        $own = $this->classroom((int) $external->id);
        $other = $this->classroom();
        $elsewhere = $this->classroom(0, '/77');
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_sentientia_classroom}') + 1000;

        $this->setUser($external);
        foreach (['other' => $other, 'own' => $own, 'another tenant' => $elsewhere, 'missing' => $missing] as $case => $id) {
            $this->assert_refused(fn() => external\waitlist_join::execute($id), 'error_notyourclassroom');
        }
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_waitlist', ['userid' => (int) $external->id]));

        // An internal trainer (who can be a learner elsewhere) still joins another classroom's list.
        $internal = $this->trainer();
        $this->setUser($internal);
        $result = external\waitlist_join::execute($other);
        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['position']);
    }

    // ═══ Expiry input ═══

    public function test_the_last_session_end_counts_every_trainer_route_and_skips_cancelled_classrooms(): void {
        global $DB;
        $trainer = $this->trainer();
        $this->assertNull(session_manager::trainer_last_session_end((int) $trainer->id));

        $base = time() + 10 * DAYSECS;
        $primary = $this->classroom((int) $trainer->id);
        $this->session($primary, 0, $base, HOURSECS);
        $this->assertSame($base + HOURSECS, session_manager::trainer_last_session_end((int) $trainer->id));

        $bysession = $this->classroom();
        $this->session($bysession, (int) $trainer->id, $base + DAYSECS, HOURSECS);
        $this->assertSame($base + DAYSECS + HOURSECS, session_manager::trainer_last_session_end((int) $trainer->id));

        $co = $this->classroom();
        $DB->insert_record('local_sentientia_classroom_trainers', (object) [
            'classroomid' => $co, 'trainerid' => (int) $trainer->id, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->session($co, 0, $base + 2 * DAYSECS, HOURSECS);
        $this->assertSame($base + 2 * DAYSECS + HOURSECS,
            session_manager::trainer_last_session_end((int) $trainer->id));

        // A later session in a cancelled classroom does not count.
        $cancelled = $this->classroom((int) $trainer->id, '/1', session_manager::STATUS_CANCELLED);
        $this->session($cancelled, 0, $base + 5 * DAYSECS, HOURSECS);
        $this->assertSame($base + 2 * DAYSECS + HOURSECS,
            session_manager::trainer_last_session_end((int) $trainer->id));
    }

    public function test_trainer_userids_of_classroom_lists_every_route_once(): void {
        global $DB;
        $lead = $this->trainer();
        $co = $this->trainer();
        $sess = $this->trainer();
        $classroomid = $this->classroom((int) $lead->id);
        $DB->insert_record('local_sentientia_classroom_trainers', (object) [
            'classroomid' => $classroomid, 'trainerid' => (int) $co->id, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->session($classroomid, (int) $sess->id);
        $this->session($classroomid, (int) $lead->id);
        $this->assertEqualsCanonicalizing([(int) $lead->id, (int) $co->id, (int) $sess->id],
            session_manager::trainer_userids_of_classroom($classroomid));
    }
}
