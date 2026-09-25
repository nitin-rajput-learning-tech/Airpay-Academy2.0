<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_notifications;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: notification logs, rule writes, previews and test sends are
 * confined to the caller's tenant.
 *
 * Tenant admins hold a manager-archetype role at system context. Until
 * 2026-09-25 :viewlogs showed them every tenant's recipients and message
 * bodies, and :manage (also a manager default) let them rewrite the
 * platform-wide rules and message any user in any tenant.
 *
 * @package    local_sentientia_notifications
 * @category   test
 * @covers     \local_sentientia_notifications\log_access
 * @covers     \local_sentientia_notifications\rule_manager::require_rule_admin
 * @covers     \local_sentientia_notifications\external\preview_rule
 * @covers     \local_sentientia_notifications\external\test_send
 * @covers     \local_sentientia_notifications\external\toggle_rule
 * @covers     \local_sentientia_notifications\external\delete_rule
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const MANAGE = 'local/sentientia_notifications:manage';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** A user at $path holding the manager role at system context (a tenant admin). */
    private function tenant_admin(string $path, bool $withmanage = false): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $sys = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, $sys->id);
        if ($withmanage) {
            // :manage no longer has a default holder; grant it deliberately.
            $roleid = $this->getDataGenerator()->create_role();
            assign_capability(self::MANAGE, CAP_ALLOW, $roleid, $sys->id);
            role_assign($roleid, $u->id, $sys->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function log_for(\stdClass $user, string $status = 'sent'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_notif_log', (object) [
            'ruleid'      => 0,
            'userid'      => $user->id,
            'channel'     => 'inapp',
            'subject'     => 'Compliance overdue for ' . $user->id,
            'message'     => 'Body for ' . $user->id,
            'status'      => $status,
            'timecreated' => time(),
        ]);
    }

    private function rule(?string $template = null): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_notif_rules', (object) [
            'name'         => 'Tenant test rule',
            'rule_type'    => 'deadline_approaching',
            'channel'      => 'inapp',
            'trigger_days' => 3,
            'audience'     => 'learner',
            'enabled'      => 1,
            'template'     => $template,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /** Ids of the log rows the current viewer's scope admits, as logs.php queries them. */
    private function visible_log_ids(): array {
        global $DB;
        [$tnsql, $tnargs] = log_access::scope_sql();
        return array_map('intval', array_keys($DB->get_records_sql(
            "SELECT l.id FROM {local_sentientia_notif_log} l
          LEFT JOIN {user} u ON u.id = l.userid
              WHERE $tnsql", $tnargs)));
    }

    private function assert_refused(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    public function test_manage_has_no_default_holder(): void {
        global $DB;
        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => self::MANAGE]),
            'Rules are platform-wide: no role may hold :manage by default.');
        // :viewlogs stays a tenant-admin function.
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $this->assertTrue($DB->record_exists('role_capabilities', [
            'capability' => 'local/sentientia_notifications:viewlogs', 'roleid' => $managerid]));
    }

    public function test_tenant_admin_sees_only_their_tenants_logs(): void {
        $own = $this->log_for($this->user_at('/1/5'));
        $foreign = $this->log_for($this->user_at('/177/178'), 'failed');
        $orphan = $this->log_for($this->user_at(''));
        $this->setUser($this->tenant_admin('/1'));

        $ids = $this->visible_log_ids();
        $this->assertContains($own, $ids);
        $this->assertNotContains($foreign, $ids, 'A /1 tenant admin must not list tenant 177 rows.');
        $this->assertNotContains($orphan, $ids, 'A recipient with no tenant is not in anyone\'s tenant.');

        $this->assertSame($own, (int) log_access::get_visible_log($own)->id);
        $this->assert_refused(fn() => log_access::get_visible_log($foreign),
            'log_detail.php must refuse another tenant\'s row.');
        $this->assert_refused(fn() => log_access::get_visible_log($orphan),
            'log_detail.php must refuse a row whose recipient has no tenant.');
        $this->assertArrayNotHasKey('failed', log_access::status_counts(),
            'The status badges must not count other tenants\' rows.');
    }

    public function test_viewer_with_no_tenant_sees_nothing(): void {
        $own = $this->log_for($this->user_at('/1/5'));
        $this->setUser($this->tenant_admin(''));

        $this->assertSame([], $this->visible_log_ids());
        $this->assertSame([], log_access::status_counts());
        $this->assert_refused(fn() => log_access::get_visible_log($own),
            'A viewer with no tenant must not open any row.');
    }

    public function test_site_admin_still_sees_every_tenant(): void {
        $own = $this->log_for($this->user_at('/1/5'));
        $foreign = $this->log_for($this->user_at('/177/178'));
        $this->setAdminUser();

        $ids = $this->visible_log_ids();
        $this->assertContains($own, $ids);
        $this->assertContains($foreign, $ids);
        $this->assertSame($foreign, (int) log_access::get_visible_log($foreign)->id);
    }

    public function test_scoped_manage_holder_cannot_write_platform_rules(): void {
        global $DB;
        $ruleid = $this->rule();
        $this->setUser($this->tenant_admin('/1', true));

        $this->assert_refused(fn() => external\toggle_rule::execute($ruleid, false),
            'Toggling a platform-wide rule is a cross-tenant write.');
        $this->assert_refused(fn() => external\delete_rule::execute($ruleid),
            'Deleting a platform-wide rule is a cross-tenant write.');
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_notif_rules', 'enabled', ['id' => $ruleid]));

        $this->setAdminUser();
        $this->assertFalse(external\toggle_rule::execute($ruleid, false)['enabled']);
    }

    public function test_preview_cannot_target_another_tenants_user(): void {
        $ruleid = $this->rule();
        $colleague = $this->user_at('/1/7');
        $foreign = $this->user_at('/177/178');
        $this->setUser($this->tenant_admin('/1', true));

        $this->assertArrayHasKey('message', external\preview_rule::execute($ruleid, (int) $colleague->id));
        $this->assert_refused(fn() => external\preview_rule::execute($ruleid, (int) $foreign->id),
            'preview_rule must not render (and so name) a user in another tenant.');

        $this->setAdminUser();
        $this->assertArrayHasKey('message', external\preview_rule::execute($ruleid, (int) $foreign->id));
    }

    public function test_test_send_cannot_message_another_tenants_user(): void {
        global $DB;
        $ruleid = $this->rule('<p>Hello {{firstname}}</p>');
        $colleague = $this->user_at('/1/7');
        $foreign = $this->user_at('/177/178');
        $this->setUser($this->tenant_admin('/1', true));
        $_POST['sesskey'] = sesskey();
        $sink = $this->redirectMessages();

        $this->assert_refused(fn() => external\test_send::execute($ruleid, (int) $foreign->id),
            'test_send must not deliver admin-written content to a user in another tenant.');
        $this->assertSame(0, $sink->count(), 'Nothing may be sent before the refusal.');
        $this->assertFalse($DB->record_exists('local_sentientia_notif_log', ['userid' => $foreign->id]));

        // A colleague in the caller's own tenant is fine, and the summary
        // names them without handing back their email address.
        $result = external\test_send::execute($ruleid, (int) $colleague->id);
        $this->assertSame(1, $sink->count());
        $this->assertSame((int) $colleague->id, (int) $sink->get_messages()[0]->useridto);
        $this->assertStringNotContainsString($colleague->email, $result['sent_to']);
        $sink->close();
    }

    public function test_test_send_with_no_tenant_can_only_message_themselves(): void {
        $ruleid = $this->rule();
        $anyone = $this->user_at('/1/7');
        $this->setUser($this->tenant_admin('', true));
        $_POST['sesskey'] = sesskey();
        $sink = $this->redirectMessages();

        $this->assert_refused(fn() => external\test_send::execute($ruleid, (int) $anyone->id),
            'A caller with no tenant cannot be shown to share one with the target.');
        $this->assertSame(0, $sink->count());
        $sink->close();
    }

    public function test_preview_cleans_the_admin_written_template(): void {
        $ruleid = $this->rule('<p onclick="steal()">Hi {{firstname}}</p><script>steal()</script>');
        $this->setAdminUser();

        $message = external\preview_rule::execute($ruleid)['message'];
        $this->assertStringContainsString('Hi ', $message);
        $this->assertStringNotContainsString('<script', $message);
        $this->assertStringNotContainsString('onclick', $message);
    }
}
