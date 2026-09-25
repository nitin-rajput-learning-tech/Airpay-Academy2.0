<?php
// This file is part of Sentientia LMS.

/**
 * ADR-031: the notification management surfaces are confined to the
 * caller's tenant.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * A tenant admin holds :manage / :manage_templates / :manage_rules through a
 * manager-archetype role at system context. Until 2026-09-25 that let them
 * read every tenant's delivery log (names + emails), pick any ?tenant=, and
 * rewrite global rules and the global template override. These tests pin:
 * a /1 tenant admin sees and changes nothing of tenant 177 or the global
 * scope; a caller with no tenant gets nothing; a site admin is unchanged.
 *
 * @covers \local_sentientia_emails\tenant_scope
 * @covers \local_sentientia_emails\delivery_log
 * @covers \local_sentientia_emails\rule_manager
 * @covers \local_sentientia_emails\external\template_api
 * @covers \local_sentientia_emails\external\rule_api
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const TPL = 'compliance/deadline_warning';

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

    private function recipient(string $path, string $email): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user(['email' => $email]);
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function log_row(\stdClass $user, int $tenant): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_email_log', (object) [
            'userid'      => $user->id,
            'tenant_id'   => $tenant,
            'channel'     => 'email',
            'subject'     => 'Reminder for ' . $user->email,
            'status'      => 'sent',
            'timecreated' => time(),
        ]);
    }

    private function rule(int $tenant): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_email_rules', (object) [
            'rule_name'    => 'Rule for tenant ' . $tenant,
            'rule_type'    => 'course_not_started',
            'channel'      => 'email',
            'audience'     => 'learner',
            'tenant_id'    => $tenant,
            'enabled'      => 1,
            'priority'     => 50,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    private function override(int $tenant, string $body): void {
        global $DB;
        $DB->insert_record('local_sentientia_email_overrides', (object) [
            'tenant_id'    => $tenant,
            'template_key' => self::TPL,
            'subject'      => 'S',
            'body_html'    => $body,
            'is_active'    => 1,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    private function assert_refused(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    private function seed_logs(): array {
        $airpay = $this->recipient('/1/5', 'airpay.learner@example.com');
        $zeea = $this->recipient('/177/178', 'zeea.learner@example.com');
        return [$this->log_row($airpay, 1), $this->log_row($zeea, 177)];
    }

    public function test_tenant_admin_sees_only_their_tenants_delivery_log(): void {
        [$airpaylog, $zeealog] = $this->seed_logs();
        $this->setUser($this->tenant_admin('/1'));

        // Asking for "all" or for tenant 177 both come back as tenant 1 only.
        foreach ([[], ['tenant_id' => 177], ['tenant_id' => 0]] as $filters) {
            $ids = array_map(fn($r) => (int) $r->id, delivery_log::get_logs($filters)->records);
            $this->assertContains($airpaylog, $ids);
            $this->assertNotContains($zeealog, $ids, 'A /1 tenant admin must not read tenant 177 delivery rows.');
        }
        $csv = delivery_log::export_csv(['tenant_id' => 177]);
        $this->assertStringNotContainsString('zeea.learner@example.com', $csv);
        $this->assertSame(1, (int) delivery_log::get_stats(177)->total);

        // ?tenant=177 on manage.php is clamped to the caller's own tenant.
        $this->assertSame(1, tenant_scope::resolve(177));
        $this->assertSame(1, tenant_scope::resolve(-1));
    }

    public function test_caller_with_no_tenant_gets_nothing(): void {
        $this->seed_logs();
        $this->rule(1);
        $this->setUser($this->tenant_admin(''));

        $this->assertSame(0, delivery_log::get_logs([])->total);
        $this->assertSame(0, (int) delivery_log::get_stats(0)->total);
        $this->assertSame([], rule_manager::get_rules(0));
        $this->assertSame(0, (int) rule_manager::get_stats(0)->total);
        $this->assert_refused(fn() => tenant_scope::resolve(0),
            'A caller whose tenant does not resolve must not get "All Tenants".');
    }

    public function test_site_admin_still_sees_every_tenant(): void {
        [$airpaylog, $zeealog] = $this->seed_logs();
        $this->setAdminUser();

        $ids = array_map(fn($r) => (int) $r->id, delivery_log::get_logs([])->records);
        $this->assertContains($airpaylog, $ids);
        $this->assertContains($zeealog, $ids);
        $this->assertSame(0, tenant_scope::resolve(0));
        $this->assertSame(177, tenant_scope::resolve(177));
        $ids = array_map(fn($r) => (int) $r->id,
            delivery_log::get_logs(['tenant_id' => 177])->records);
        $this->assertSame([$zeealog], $ids);
    }

    public function test_tenant_admin_cannot_change_global_or_other_tenants_rules(): void {
        $global = $this->rule(0);
        $own = $this->rule(1);
        $foreign = $this->rule(177);
        $this->setUser($this->tenant_admin('/1'));

        $this->assertSame($own, (int) tenant_scope::modifiable_rule($own)->id);
        foreach ([$global, $foreign] as $ruleid) {
            $this->assert_refused(fn() => tenant_scope::modifiable_rule($ruleid),
                'Global and other-tenant rules are cross-tenant writes.');
            $this->assert_refused(fn() => external\rule_api::toggle_rule($ruleid, 0),
                'The toggle web service must refuse it too.');
        }
        // A global rule is readable (it fires for this tenant), a foreign one is not.
        tenant_scope::require_can_view_rule(rule_manager::get_rule($global));
        $this->assert_refused(fn() => tenant_scope::require_can_view_rule(rule_manager::get_rule($foreign)),
            'Another tenant\'s rule must not be readable.');
        $this->assert_refused(fn() => tenant_scope::require_can_write_tenant(0),
            'A scoped caller must not create a global rule.');

        $visible = array_map('intval', array_keys(rule_manager::get_rules(tenant_scope::resolve(177))));
        $this->assertNotContains($foreign, $visible);

        global $DB;
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_email_rules', 'enabled', ['id' => $foreign]));
    }

    public function test_tenant_admin_cannot_write_global_or_other_tenants_templates(): void {
        global $DB;
        $this->override(177, 'SECRET-177-BODY');
        $this->setUser($this->tenant_admin('/1'));

        foreach ([0, 177] as $tenant) {
            $this->assert_refused(
                fn() => external\template_api::save_template(self::TPL, $tenant, 'X', '<a href="https://evil">x</a>'),
                "Saving the tenant-{$tenant} override must be refused for a /1 tenant admin.");
            $this->assert_refused(fn() => external\template_api::revert_template(self::TPL, $tenant),
                "Reverting the tenant-{$tenant} override must be refused for a /1 tenant admin.");
        }
        $this->assertTrue($DB->record_exists('local_sentientia_email_overrides',
            ['tenant_id' => 177, 'template_key' => self::TPL]));
        $this->assertFalse($DB->record_exists('local_sentientia_email_overrides',
            ['tenant_id' => 0, 'template_key' => self::TPL]));

        // Reading asks for 177, gets its own tenant's view instead.
        $data = external\template_api::get_template(self::TPL, 177)['data'];
        $this->assertStringNotContainsString('SECRET-177-BODY', $data);

        // Its own tenant is fine.
        external\template_api::save_template(self::TPL, 1, 'Own', '<p>Own tenant</p>');
        $this->assertTrue($DB->record_exists('local_sentientia_email_overrides',
            ['tenant_id' => 1, 'template_key' => self::TPL]));
    }

    public function test_site_admin_can_still_write_global_templates(): void {
        global $DB;
        $this->setAdminUser();
        external\template_api::save_template(self::TPL, 0, 'Global', '<p>Global</p>');
        $this->assertTrue($DB->record_exists('local_sentientia_email_overrides',
            ['tenant_id' => 0, 'template_key' => self::TPL]));
    }

    public function test_recipient_tenant_decides_which_override_is_delivered(): void {
        $this->override(0, 'GLOBAL');
        $this->override(177, 'ZEEA-ONLY');
        $zeea = $this->recipient('/177/178', 'z@example.com');
        $airpay = $this->recipient('/1/5', 'a@example.com');

        $this->assertSame(177, tenant_config::tenant_id_for_user((int) $zeea->id));
        $this->assertSame('ZEEA-ONLY', template_manager::get_override(self::TPL,
            tenant_config::tenant_id_for_user((int) $zeea->id))->body_html);
        $this->assertSame('GLOBAL', template_manager::get_override(self::TPL,
            tenant_config::tenant_id_for_user((int) $airpay->id))->body_html);
    }

    public function test_a_tenant_rule_only_reaches_that_tenants_users(): void {
        global $DB;
        $zeea = $this->recipient('/177/178', 'z@example.com');
        $airpay = $this->recipient('/1/5', 'a@example.com');
        $boundary = $this->recipient('/1770', 'b@example.com');

        $method = new \ReflectionMethod(task\process_rules::class, 'rule_tenant_filter');
        [$sql, $params] = $method->invoke(new task\process_rules(), (object) ['tenant_id' => 177]);
        [$insql, $inparams] = $DB->get_in_or_equal([$zeea->id, $airpay->id, $boundary->id], SQL_PARAMS_NAMED);
        $ids = array_map('intval', array_keys($DB->get_records_sql(
            "SELECT u.id FROM {user} u WHERE u.id $insql AND $sql", $inparams + $params)));
        $this->assertSame([(int) $zeea->id], $ids);

        // A global rule stays unrestricted.
        $this->assertSame(['1=1', []], $method->invoke(new task\process_rules(), (object) ['tenant_id' => 0]));
    }
}
