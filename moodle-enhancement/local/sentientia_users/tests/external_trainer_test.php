<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * External trainer accounts (ADR-036 D9, plan C6, session 5, 2026-11-20).
 *
 * Invite: only with sentientia.users.external_trainer.enabled ON and
 * local/sentientia_users:inviteexternaltrainer; into the inviter's own tenant (a scoped inviter cannot
 * pick another); a manual-login account with a forced password change, a profile row, the restricted
 * role (no archetype, exactly classroom :view and :attendance) and a logged event; the invite e-mail
 * carries a core reset-token link (caught by the e-mail sink: nothing is sent).
 *
 * Expiry: max(last session end, validfrom) + 30 days, recomputed when a session is saved; the daily task
 * reminds the inviter and the tenant's admins once (not another tenant's), suspends at expiry, logs it
 * and never deletes. An ended account comes back only through reactivate(): the ordinary activate paths
 * refuse it and the task suspends it again if anything else unsuspended it. The flag is checked for the
 * tenant the trainer lands in, not only the inviter's (review fixes 2026-11-20). Admin actions (extend, revoke, reactivate) are tenant-bound and logged. Privacy:
 * the trainer and the inviter are both found, exported and removed from the row, which is kept.
 *
 * @package    local_sentientia_users
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;
use local_sentientia_users\external_trainer\manager;
use local_sentientia_users\external_trainer\role;

/**
 * @covers \local_sentientia_users\external_trainer\manager
 * @covers \local_sentientia_users\external_trainer\role
 * @covers \local_sentientia_users\external_trainer\invite_mailer
 * @covers \local_sentientia_users\external_trainer\privacy
 * @covers \local_sentientia_users\task\external_trainer_expiry
 * @covers \local_sentientia_users\user_type\external_trainer_provider
 * @covers \local_sentientia_users\privacy\provider
 * @covers \local_sentientia_users\event\external_trainer_invited
 * @covers \local_sentientia_users\event\external_trainer_expired
 * @covers \local_sentientia_users\external\suspend_user
 * @covers \local_sentientia_users\external\bulk_action
 * @covers \local_sentientia_users\bulk_csv_processor
 * @group local_sentientia_users
 * @group tenant_isolation
 */
final class external_trainer_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        feature_flags::invalidate_caches();
    }

    /** A tenant L&D admin: a system role holding only the invite capability, in $path. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'admin' . random_string(6) . '@example.com']);
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(manager::CAPABILITY, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    private function switch_on(): void {
        global $USER;
        $previous = $USER;
        $this->setAdminUser();
        feature_flags::set(manager::FLAG, 0, true);
        $this->setUser($previous);
    }

    /** A system role holding exactly these capabilities, given to $user. */
    private function grant(\stdClass $user, array $caps): void {
        $sys = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $sys->id, true);
        }
        role_assign($roleid, $user->id, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /** A tenant user administrator (Manage Users): local/sentientia_users:edit, in $path. */
    private function user_editor(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'editor' . random_string(6) . '@example.com']);
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $this->grant($user, ['local/sentientia_users:edit']);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    private function invite_as(\stdClass $admin, string $email, array $extra = []): int {
        $this->setUser($admin);
        return manager::invite((object) ($extra + [
            'firstname' => 'Test', 'lastname' => 'Trainer', 'email' => $email, 'organisation' => 'Example Training Co',
        ]), false);
    }

    private function assert_refused(callable $call, string $code): void {
        try {
            $call();
            $this->fail('Expected ' . $code . ' but the call succeeded.');
        } catch (\moodle_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    private function profile(int $userid): \stdClass {
        global $DB;
        return $DB->get_record(manager::TABLE, ['userid' => $userid], '*', MUST_EXIST);
    }

    // ═══ Flag and capability ═══

    public function test_the_flag_is_registered_and_off_by_default(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey('sentientia.users.external_trainer.enabled', $registry);
        $this->assertFalse($registry[manager::FLAG]['default']);
        $this->assertNotEmpty(get_capability_info(manager::CAPABILITY));
    }

    public function test_with_the_flag_off_nothing_can_be_started(): void {
        $admin = $this->tenant_admin('/1/5');
        $this->setUser($admin);
        $this->assertFalse(manager::can_manage());
        $this->assert_refused(fn() => manager::require_can_manage(), 'exttrainer_disabled');
        $this->assert_refused(fn() => manager::invite((object) [
            'firstname' => 'Test', 'lastname' => 'Trainer', 'email' => 'off@example.com'], false), 'exttrainer_disabled');
    }

    public function test_the_capability_is_required(): void {
        global $DB;
        $this->switch_on();
        $plain = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1/5', ['id' => $plain->id]);
        $this->setUser($DB->get_record('user', ['id' => $plain->id]));
        $this->assertFalse(manager::can_manage());
        $this->expectException(\required_capability_exception::class);
        manager::invite((object) ['firstname' => 'Test', 'lastname' => 'Trainer', 'email' => 'nocap@example.com'], false);
    }

    public function test_the_capability_has_no_archetype_default(): void {
        global $DB;
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $this->assertFalse($DB->record_exists('role_capabilities',
            ['roleid' => $managerid, 'capability' => manager::CAPABILITY]));
    }

    // ═══ Invite ═══

    public function test_an_invite_creates_a_restricted_manual_account_in_the_inviters_tenant(): void {
        global $DB;
        $admin = $this->tenant_admin('/1/5');
        $this->switch_on();
        $events = $this->redirectEvents();

        // A scoped inviter cannot place the trainer in another tenant: the request is ignored.
        $userid = $this->invite_as($admin, 'Ext.Trainer1@example.com', ['tenantpath' => '/77']);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $this->assertSame('manual', $user->auth);
        $this->assertSame(1, (int) $user->confirmed);
        $this->assertSame('ext.trainer1@example.com', $user->username);
        $this->assertSame('/1', $user->open_path);
        $this->assertNotSame('', (string) $user->password, 'a random password hash is stored, never sent');
        $this->assertEquals(1, get_user_preferences('auth_forcepasswordchange', null, $userid));

        $profile = $this->profile($userid);
        $this->assertSame(manager::STATUS_ACTIVE, $profile->status);
        $this->assertSame((int) $admin->id, (int) $profile->invitedby);
        $this->assertSame('/1', $profile->open_path);
        $this->assertSame('Example Training Co', $profile->organisation);
        $this->assertSame((int) $profile->validfrom + manager::GRACE, (int) $profile->expires_at);
        $this->assertTrue(manager::is_external_trainer($userid));

        // The restricted role: no archetype, exactly the two classroom capabilities, at system context.
        $role = $DB->get_record('role', ['shortname' => role::SHORTNAME], '*', MUST_EXIST);
        $this->assertSame('', (string) $role->archetype);
        $caps = $DB->get_fieldset_select('role_capabilities', 'capability', 'roleid = :r AND permission = :p',
            ['r' => $role->id, 'p' => CAP_ALLOW]);
        $expected = array_values(array_filter(role::CAPABILITIES, fn($c) => (bool) get_capability_info($c)));
        $this->assertEqualsCanonicalizing($expected, $caps);
        $this->assertTrue($DB->record_exists('role_assignments', ['roleid' => $role->id, 'userid' => $userid,
            'contextid' => \context_system::instance()->id]));
        if (get_capability_info('local/sentientia_classroom:update')) {
            $this->assertFalse(has_capability('local/sentientia_classroom:update', \context_system::instance(), $userid));
            $this->assertFalse(has_capability('local/sentientia_classroom:manage', \context_system::instance(), $userid));
        }

        // Logged, without personal data in `other`.
        $invited = array_values(array_filter($events->get_events(),
            fn($e) => $e instanceof event\external_trainer_invited));
        $this->assertCount(1, $invited);
        $this->assertSame($userid, (int) $invited[0]->relateduserid);
        $this->assertSame((int) $admin->id, (int) $invited[0]->userid);
        $this->assertSame(['expires_at'], array_keys($invited[0]->other));

        // ADR-017: the type row is written only once the platform registers the type.
        if ($DB->get_manager()->table_exists('local_sentientia_user_type')) {
            $this->assertSame(manager::type_registered(),
                $DB->record_exists('local_sentientia_user_type', ['userid' => $userid]));
        }
    }

    public function test_the_invite_mail_carries_a_reset_link_and_no_password(): void {
        global $CFG, $DB;
        $admin = $this->tenant_admin('/1/5');
        $this->switch_on();
        $userid = $this->invite_as($admin, 'mail.trainer@example.com');

        // Local and CI configs set noemailever; the sink catches the message either way, nothing is sent
        // (the same pattern as welcome_mailer_test).
        $CFG->noemailever = false;
        $sink = $this->redirectEmails();
        $this->assertTrue(external_trainer\invite_mailer::send($userid));
        $mails = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $mails);
        $this->assertSame('mail.trainer@example.com', $mails[0]->to);
        $token = (string) $DB->get_field('user_password_resets', 'token', ['userid' => $userid], MUST_EXIST);
        $body = str_replace(["=\r\n", "=\n"], '', (string) $mails[0]->body);
        $this->assertTrue(strpos($body, 'forgot_password.php') !== false);
        $this->assertTrue(strpos($body, $token) !== false || strpos(quoted_printable_decode((string) $mails[0]->body),
            $token) !== false, 'the reset token is in the link');
        $this->assertStringNotContainsStringIgnoringCase('password:', $body);
    }

    public function test_a_cross_tenant_inviter_must_pick_a_tenant(): void {
        global $DB;
        $this->switch_on();
        $this->setAdminUser();
        $this->assert_refused(fn() => manager::invite((object) [
            'firstname' => 'Test', 'lastname' => 'Trainer', 'email' => 'nopick@example.com'], false),
            'exttrainer_error_tenant');

        $options = manager::tenant_options();
        $this->assertNotEmpty($options);
        $path = array_key_first($options);
        $userid = manager::invite((object) ['firstname' => 'Test', 'lastname' => 'Trainer',
            'email' => 'picked@example.com', 'tenantpath' => $path], false);
        $this->assertSame($path, $DB->get_field('user', 'open_path', ['id' => $userid]));
    }

    /**
     * Review fix 2026-11-20: the flag is resolved for the tenant the trainer lands in, not only for the
     * inviter's. A :crosstenant holder whose own tenant (1) has the flag ON is offered, and may invite
     * into, tenant 1 only; a site admin (who resolves flags site-wide) cannot invite into a tenant whose
     * flag is switched OFF.
     */
    public function test_the_flag_is_checked_for_the_tenant_the_trainer_lands_in(): void {
        global $DB;
        $inviter = $this->tenant_admin('/1/5');
        $this->grant($inviter, [\local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY]);

        // Every tenant the caller may pick (the flag ON everywhere), to find one that is not tenant 1.
        $this->switch_on();
        $this->setUser($inviter);
        $this->assertTrue(\local_sentientia_platform\tenant::is_cross_tenant());
        $root = fn(string $path) => \local_sentientia_platform\tenant::root_for_user((object) ['open_path' => $path]);
        $others = array_values(array_filter(array_keys(manager::tenant_options()), fn($p) => $root($p) !== 1));
        $this->assertNotEmpty($others, 'a cross-tenant caller is offered more than one tenant');
        $other = $others[0];
        $ones = array_values(array_filter(array_keys(manager::tenant_options()), fn($p) => $root($p) === 1));

        // Now ON for tenant 1 only.
        $this->setAdminUser();
        feature_flags::set(manager::FLAG, 0, null);
        feature_flags::set(manager::FLAG, 1, true);
        $this->setUser($inviter);

        $this->assertTrue(manager::can_manage(), 'ON for the inviter\'s own tenant');
        $this->assertTrue(manager::enabled_for_path('/1'));
        $this->assertFalse(manager::enabled_for_path($other));
        $this->assertFalse(manager::enabled_for_path(''), 'no tenant root, never enabled');
        foreach (array_keys(manager::tenant_options()) as $path) {
            $this->assertSame(1, $root($path), 'only tenants with the flag ON are offered');
        }

        $this->assert_refused(fn() => manager::invite((object) ['firstname' => 'Test', 'lastname' => 'Trainer',
            'email' => 'elsewhere@example.com', 'tenantpath' => $other], false), 'exttrainer_disabled');
        $this->assertFalse($DB->record_exists('user', ['email' => 'elsewhere@example.com']), 'no account was created');

        if ($ones) {
            $userid = manager::invite((object) ['firstname' => 'Test', 'lastname' => 'Trainer',
                'email' => 'here@example.com', 'tenantpath' => $ones[0]], false);
            $this->assertSame($ones[0], $DB->get_field('user', 'open_path', ['id' => $userid]));
        }

        // A site admin: ON site-wide, explicitly OFF for the other tenant.
        $this->setAdminUser();
        feature_flags::set(manager::FLAG, 0, true);
        feature_flags::set(manager::FLAG, $root($other), false);
        $this->assertTrue(manager::can_manage());
        $this->assertArrayNotHasKey($other, manager::tenant_options());
        $this->assert_refused(fn() => manager::invite((object) ['firstname' => 'Test', 'lastname' => 'Trainer',
            'email' => 'adminpick@example.com', 'tenantpath' => $other], false), 'exttrainer_disabled');
        $this->assertFalse($DB->record_exists('user', ['email' => 'adminpick@example.com']));
    }

    public function test_an_inviter_with_no_tenant_is_refused(): void {
        $admin = $this->tenant_admin('');
        $this->switch_on();
        $this->setUser($admin);
        $this->assert_refused(fn() => manager::invite((object) [
            'firstname' => 'Test', 'lastname' => 'Trainer', 'email' => 'notenant@example.com'], false),
            'error_outoftenant');
    }

    public function test_an_existing_email_is_refused(): void {
        $admin = $this->tenant_admin('/1/5');
        $this->getDataGenerator()->create_user(['email' => 'taken@example.com']);
        $this->switch_on();
        $this->setUser($admin);
        $this->assertSame('exttrainer_error_emailtaken', manager::email_problem('TAKEN@example.com'));
        $this->assertSame('exttrainer_error_invalidemail', manager::email_problem('not-an-address'));
        // A '+' address gets a username core accepts (cleaning would otherwise refuse it).
        $this->assertSame(empty($GLOBALS['CFG']->extendedusernamechars) ? 'first.lasttag@example.com'
            : 'first.last+tag@example.com', manager::username_for('First.Last+Tag@Example.com'));
        $this->assertNull(manager::email_problem('first.last+tag@example.com'));
        $this->assert_refused(fn() => manager::invite((object) [
            'firstname' => 'Test', 'lastname' => 'Trainer', 'email' => 'taken@example.com'], false),
            'exttrainer_error_emailtaken');
    }

    // ═══ Expiry ═══

    public function test_the_expiry_follows_the_last_session_they_train(): void {
        if (!class_exists('\\local_sentientia_classroom\\session_manager')) {
            $this->markTestSkipped('local_sentientia_classroom is not installed');
        }
        global $DB;
        $admin = $this->tenant_admin('/1/5');
        $this->switch_on();
        $userid = $this->invite_as($admin, 'session.trainer@example.com');

        $classroomid = (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'Expiry classroom', 'description' => '', 'costcenterid' => 0, 'open_path' => '/1',
            'location' => 'Room', 'capacity' => 20, 'status' => 1, 'trainerid' => null,
            'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $start = time() + 60 * DAYSECS;
        // Saving the session recomputes the expiry at once (session_manager::trainers_changed()).
        \local_sentientia_classroom\session_manager::create_session($classroomid, (object) [
            'title' => 'Day 1', 'starttime' => $start, 'endtime' => $start + 2 * HOURSECS, 'trainerid' => $userid,
        ]);
        $this->assertSame($start + 2 * HOURSECS + manager::GRACE, (int) $this->profile($userid)->expires_at);

        // Cancelling the classroom brings it back to validfrom + 30 days.
        \local_sentientia_classroom\session_manager::change_status($classroomid, 0);
        $profile = $this->profile($userid);
        $this->assertSame((int) $profile->validfrom + manager::GRACE, (int) $profile->expires_at);
    }

    public function test_the_task_suspends_an_expired_account_logs_it_and_keeps_it(): void {
        global $DB;
        $admin = $this->tenant_admin('/1/5');
        $this->switch_on();
        $userid = $this->invite_as($admin, 'old.trainer@example.com');
        $DB->set_field(manager::TABLE, 'validfrom', time() - 40 * DAYSECS, ['userid' => $userid]);

        $events = $this->redirectEvents();
        $this->redirectMessages();
        $result = manager::run_expiry(time());
        $this->assertSame(1, $result['expired']);

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $this->assertSame(1, (int) $user->suspended);
        $this->assertSame(0, (int) $user->deleted, 'never deleted');
        $this->assertSame(manager::STATUS_EXPIRED, $this->profile($userid)->status);
        $this->assertCount(1, array_filter($events->get_events(), fn($e) => $e instanceof event\external_trainer_expired));

        // A second run does nothing more.
        $this->assertSame(0, manager::run_expiry(time())['expired']);
    }

    /**
     * Review fix 2026-11-20: an ended (expired or revoked) trainer comes back only through reactivate().
     * The Manage Users toggle refuses, bulk and CSV activate skip them, and if anything else unsuspends
     * the account (core user admin, the HRMS sync) the daily task suspends it again. Until then the task
     * read active rows only, so such an account stayed live for good.
     */
    public function test_an_ended_trainer_comes_back_only_through_reactivate(): void {
        global $DB;
        $admin = $this->tenant_admin('/1/5');
        $editor = $this->user_editor('/1/7');
        $this->switch_on();
        $expired = $this->invite_as($admin, 'ended.trainer@example.com');
        $revoked = $this->invite_as($admin, 'revoked.trainer@example.com');
        $live = $this->invite_as($admin, 'live.trainer@example.com');
        $DB->set_field(manager::TABLE, 'validfrom', time() - 40 * DAYSECS, ['userid' => $expired]);
        $this->redirectMessages();
        $this->assertSame(1, manager::run_expiry(time())['expired']);
        $this->setUser($admin);
        manager::revoke($revoked);

        $suspended = fn(int $id) => (int) $DB->get_field('user', 'suspended', ['id' => $id]);
        foreach ([$expired, $revoked] as $id) {
            $this->assertSame(1, $suspended($id));
            $this->assertTrue(manager::activation_blocked($id));
        }
        $this->assertFalse(manager::activation_blocked($live), 'an active trainer is not blocked');
        $this->assertFalse(manager::activation_blocked((int) $editor->id), 'an ordinary account is not blocked');
        $this->assertEqualsCanonicalizing([$expired, $revoked],
            manager::ended_userids([$expired, $revoked, $live, (int) $editor->id]));

        // The Manage Users toggle refuses to activate them (suspending is unaffected).
        $this->setUser($editor);
        $this->assert_refused(fn() => external\suspend_user::execute($expired, false), 'exttrainer_error_activate');
        $this->assert_refused(fn() => external\suspend_user::execute($revoked, false), 'exttrainer_error_activate');
        $this->assertTrue(external\suspend_user::execute($expired, true)['suspended']);

        // Bulk activate skips them; the active trainer in the same batch is still acted on.
        $DB->set_field('user', 'suspended', 1, ['id' => $live]);
        $result = external\bulk_action::execute('activate', [$expired, $revoked, $live]);
        $this->assertSame(1, $result['count']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(0, $suspended($live));

        // CSV activate skips them with the reason.
        $summary = bulk_csv_processor::process("email,action\nended.trainer@example.com,activate\n", (int) $editor->id);
        $this->assertCount(0, $summary['succeeded']);
        $this->assertCount(1, $summary['skipped']);
        $this->assertSame(get_string('exttrainer_error_activate', 'local_sentientia_users'),
            $summary['skipped'][0]['reason']);

        foreach ([$expired, $revoked] as $id) {
            $this->assertSame(1, $suspended($id), 'still suspended after every ordinary path');
        }

        // Any other path (core user admin, the HRMS sync): the daily task suspends them again.
        foreach ([$expired, $revoked] as $id) {
            \local_sentientia_platform\compat\user_api::update((object) ['id' => $id, 'suspended' => 0], false, true);
        }
        $result = manager::run_expiry(time());
        $this->assertSame(2, $result['resuspended']);
        $this->assertSame(0, $result['expired']);
        foreach ([$expired, $revoked] as $id) {
            $this->assertSame(1, $suspended($id));
        }
        $this->assertSame(manager::STATUS_EXPIRED, $this->profile($expired)->status);
        $this->assertSame(manager::STATUS_REVOKED, $this->profile($revoked)->status);
        $this->assertSame(0, $suspended($live), 'an active trainer is left alone');
        $this->assertSame(0, manager::run_expiry(time())['resuspended'], 'nothing more to do');

        // Reactivate is the way back, and the task then leaves the account alone.
        $this->setUser($admin);
        manager::reactivate($revoked);
        $this->assertSame(0, $suspended($revoked));
        $this->assertFalse(manager::activation_blocked($revoked));
        $this->assertSame(0, manager::run_expiry(time())['resuspended']);
        $this->assertSame(0, $suspended($revoked));
    }

    public function test_the_task_reminds_the_inviter_and_the_tenants_admins_once(): void {
        global $DB;
        $inviter = $this->tenant_admin('/1/5');
        $colleague = $this->tenant_admin('/1/9');
        $othertenant = $this->tenant_admin('/77/2');
        $this->switch_on();
        $userid = $this->invite_as($inviter, 'soon.trainer@example.com');
        $DB->set_field(manager::TABLE, 'validfrom', time() - 25 * DAYSECS, ['userid' => $userid]);

        $sink = $this->redirectMessages();
        $result = manager::run_expiry(time());
        $this->assertSame(1, $result['reminded']);
        $this->assertSame(0, $result['expired']);
        $to = array_map(fn($m) => (int) $m->useridto, $sink->get_messages());
        $this->assertEqualsCanonicalizing([(int) $inviter->id, (int) $colleague->id], $to);
        $this->assertNotContains((int) $othertenant->id, $to);
        $this->assertNotContains($userid, $to);
        foreach ($sink->get_messages() as $message) {
            $this->assertSame('exttrainer_expiring', $message->eventtype);
        }

        $sink->clear();
        $this->assertSame(0, manager::run_expiry(time())['reminded'], 'reminded once per expiry date');
        $this->assertCount(0, $sink->get_messages());
    }

    public function test_the_scheduled_task_runs_the_expiry(): void {
        $task = new task\external_trainer_expiry();
        $this->assertNotEmpty($task->get_name());
        $this->expectOutputRegex('/accounts expired/');
        $task->execute();
    }

    // ═══ Admin actions ═══

    public function test_revoke_extend_and_reactivate_are_logged_and_tenant_bound(): void {
        global $DB;
        $admin = $this->tenant_admin('/1/5');
        $stranger = $this->tenant_admin('/77/3');
        $this->switch_on();
        $userid = $this->invite_as($admin, 'act.trainer@example.com');
        $events = $this->redirectEvents();

        // Another tenant's admin cannot touch them.
        $this->setUser($stranger);
        $this->assert_refused(fn() => manager::revoke($userid), 'error_outoftenant');

        $this->setUser($admin);
        $DB->set_field(manager::TABLE, 'validfrom', time() - 20 * DAYSECS, ['userid' => $userid]);
        $extended = manager::extend($userid);
        $this->assertGreaterThanOrEqual(time() + manager::GRACE - 5, (int) $extended->expires_at);

        manager::revoke($userid);
        $this->assertSame(1, (int) $DB->get_field('user', 'suspended', ['id' => $userid]));
        $this->assertSame(manager::STATUS_REVOKED, $this->profile($userid)->status);
        $this->assert_refused(fn() => manager::extend($userid), 'exttrainer_error_status');

        manager::reactivate($userid);
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $userid]));
        $this->assertSame(manager::STATUS_ACTIVE, $this->profile($userid)->status);
        $this->assert_refused(fn() => manager::reactivate($userid), 'exttrainer_error_status');

        $classes = array_map(fn($e) => get_class($e), $events->get_events());
        foreach (['extended', 'revoked', 'reactivated'] as $name) {
            $this->assertContains('local_sentientia_users\\event\\external_trainer_' . $name, $classes);
        }

        // An ordinary account is not an external trainer.
        $plain = $this->getDataGenerator()->create_user();
        $this->assert_refused(fn() => manager::revoke((int) $plain->id), 'exttrainer_error_notexternal');
    }

    public function test_the_list_shows_only_the_callers_tenant(): void {
        $one = $this->tenant_admin('/1/5');
        $other = $this->tenant_admin('/77/2');
        $this->switch_on();
        $mine = $this->invite_as($one, 'mine.trainer@example.com');
        $theirs = $this->invite_as($other, 'theirs.trainer@example.com');

        $this->setUser($one);
        $this->assertSame([$mine], array_map(fn($r) => (int) $r->userid, array_values(manager::list_for_caller())));
        $this->setAdminUser();
        $this->assertEqualsCanonicalizing([$mine, $theirs],
            array_map(fn($r) => (int) $r->userid, array_values(manager::list_for_caller())));
    }

    // ═══ User type (ADR-017) ═══

    public function test_the_provider_shows_no_learning_surfaces(): void {
        $provider = new user_type\external_trainer_provider();
        $this->assertInstanceOf(\local_sentientia_platform\user_type_provider::class, $provider);
        $this->assertSame('external_trainer', $provider::type_id());
        $this->assertNotSame('', $provider::label());
        foreach (['catalog', 'leaderboard', 'compliance', 'analytics', 'manage_users', 'self_enrol'] as $feature) {
            $this->assertFalse($provider->feature_supported($feature), $feature);
        }
        $this->assertContains('trainings_i_deliver', $provider->sidebar_items(new \stdClass()));
        $this->assertNotContains('catalog', $provider->sidebar_items(new \stdClass()));
        $this->assertContains('consent_capture', $provider->onboarding_steps(new \stdClass()));
    }

    // ═══ Privacy ═══

    public function test_privacy_finds_exports_and_anonymises_both_people_and_keeps_the_row(): void {
        global $DB;
        $admin = $this->tenant_admin('/1/5');
        $this->switch_on();
        $userid = $this->invite_as($admin, 'priv.trainer@example.com');
        $sys = \context_system::instance();

        foreach ([$userid, (int) $admin->id] as $id) {
            $this->assertEquals([$sys->id], privacy\provider::get_contexts_for_userid($id)->get_contextids());
        }
        $userlist = new \core_privacy\local\request\userlist($sys, 'local_sentientia_users');
        privacy\provider::get_users_in_context($userlist);
        $this->assertContains($userid, $userlist->get_userids());
        $this->assertContains((int) $admin->id, $userlist->get_userids());

        $trainer = \core_user::get_user($userid);
        privacy\provider::export_user_data(
            new \core_privacy\local\request\approved_contextlist($trainer, 'local_sentientia_users', [$sys->id]));
        $data = \core_privacy\local\request\writer::with_context($sys)->get_data(
            [get_string('pluginname', 'local_sentientia_users'), 'external_trainer']);
        $this->assertSame('Example Training Co', $data->own[0]['organisation']);

        privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($trainer, 'local_sentientia_users', [$sys->id]));
        $profile = $this->profile($userid);
        $this->assertSame('', $profile->organisation);
        $this->assertTrue(manager::is_external_trainer($userid), 'the row (and so the restrictions) stays');

        privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($admin, 'local_sentientia_users', [$sys->id]));
        $this->assertSame(0, (int) $this->profile($userid)->invitedby);
        $this->assertTrue($DB->record_exists(manager::TABLE, ['userid' => $userid]));
    }
}
