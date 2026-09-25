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
 * They also pin the rules and templates tabs to what the server does: the
 * templates tab reports the override a tenant actually receives, tenant labels
 * come from the org registry rather than a hardcoded customer, and a read-only
 * global rule renders a form that cannot be submitted.
 *
 * @covers \local_sentientia_emails\tenant_scope
 * @covers \local_sentientia_emails\template_manager
 * @covers \local_sentientia_emails\delivery_log
 * @covers \local_sentientia_emails\rule_manager
 * @covers \local_sentientia_emails\external\template_api
 * @covers \local_sentientia_emails\external\rule_api
 * @covers \local_sentientia_emails\manage_controller
 * @covers \local_sentientia_emails\legacy_bridge
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const TPL = 'compliance/deadline_warning';

    /** @var \xmldb_table[] temporary BizLMS tables this test created; dropped in tearDown(). */
    private array $temptables = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    protected function tearDown(): void {
        global $DB;
        foreach ($this->temptables as $table) {
            $DB->get_manager()->drop_table($table);
        }
        $this->temptables = [];
        parent::tearDown();
    }

    /**
     * The read-only BizLMS notification tables legacy_bridge reads. They are
     * not part of this codebase; when the test site does not have them, stand
     * in temporary tables with the columns legacy_bridge selects.
     */
    private function ensure_legacy_tables(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $type = new \xmldb_table('local_notification_type');
        if (!$dbman->table_exists($type)) {
            $type->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $type->add_field('name', XMLDB_TYPE_CHAR, '255');
            $type->add_field('shortname', XMLDB_TYPE_CHAR, '255');
            $type->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_temp_table($type);
            $this->temptables[] = $type;
        }
        $info = new \xmldb_table('local_notification_info');
        if (!$dbman->table_exists($info)) {
            $info->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $info->add_field('notificationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $info->add_field('subject', XMLDB_TYPE_CHAR, '255');
            $info->add_field('body', XMLDB_TYPE_TEXT);
            $info->add_field('adminbody', XMLDB_TYPE_TEXT);
            $info->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $info->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $info->add_field('completiondays', XMLDB_TYPE_INTEGER, '10');
            $info->add_field('reminderdays', XMLDB_TYPE_INTEGER, '10');
            $info->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $info->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $info->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_temp_table($info);
            $this->temptables[] = $info;
        }
    }

    /**
     * Insert into a BizLMS table whose real schema this codebase does not
     * own: fill every NOT NULL column that has no default with a neutral value.
     */
    private function legacy_row(string $table, array $data): int {
        global $DB;
        $row = [];
        foreach ($DB->get_columns($table, false) as $name => $column) {
            if ($name === 'id') {
                continue;
            }
            if (array_key_exists($name, $data)) {
                $row[$name] = $data[$name];
            } else if ($column->not_null && !$column->has_default) {
                $row[$name] = in_array($column->meta_type, ['I', 'N', 'F', 'R', 'L'], true) ? 0 : '';
            }
        }
        return (int) $DB->insert_record($table, (object) $row);
    }

    /** One BizLMS legacy template per costcenter; returns [costcenterid => info id]. */
    private function seed_legacy_templates(): array {
        $this->ensure_legacy_tables();
        $typeid = $this->legacy_row('local_notification_type',
            ['name' => 'Course enrolment', 'shortname' => 'course_enrol']);
        $ids = [];
        foreach ([1 => 'AIRPAY-LEGACY', 177 => 'ZEEA-LEGACY-SECRET', 0 => 'SITE-LEGACY'] as $cc => $subject) {
            $ids[$cc] = $this->legacy_row('local_notification_info', [
                'notificationid' => $typeid,
                'subject'        => $subject,
                'body'           => '<p>Body of ' . $subject . '</p>',
                'costcenterid'   => $cc,
                'active'         => 1,
                'timecreated'    => time(),
                'timemodified'   => time(),
            ]);
        }
        return $ids;
    }

    private function legacy_subjects(): array {
        return array_column(legacy_bridge::get_bizlms_templates(), 'subject');
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

    /** One override of self::TPL; (tenant_id, template_key) is a unique index. */
    private function override(int $tenant, string $body, int $active = 1): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_email_overrides', (object) [
            'tenant_id'    => $tenant,
            'template_key' => self::TPL,
            'subject'      => 'S',
            'body_html'    => $body,
            'is_active'    => $active,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /** A tenant root in the org registry (local_sentientia_org), as the migration seeds it. */
    private function org_root(int $root, string $name): void {
        global $DB;
        $DB->insert_record('local_sentientia_org', (object) [
            'fullname'     => $name,
            'shortname'    => 'org' . $root,
            'parentid'     => 0,
            'path'         => '/' . $root,
            'depth'        => 1,
            'visible'      => 1,
            'sortorder'    => $root,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    private static function str(string $identifier, $a = null): string {
        return get_string($identifier, 'local_sentientia_emails', $a);
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

    public function test_tenant_admin_sees_only_their_tenants_legacy_templates(): void {
        $ids = $this->seed_legacy_templates();
        $this->setUser($this->tenant_admin('/1'));

        $this->assertSame(['AIRPAY-LEGACY'], $this->legacy_subjects(),
            'The templates tab must not list another costcenter\'s legacy subjects or bodies.');
        $this->assertSame(1, manage_controller::get_templates_data(1)['total_bizlms']);
        $this->assertNull(legacy_bridge::get_bizlms_template($ids[177]));
        $this->assertSame('AIRPAY-LEGACY', legacy_bridge::get_bizlms_template($ids[1])->subject);
    }

    public function test_caller_with_no_tenant_sees_no_legacy_templates(): void {
        $this->seed_legacy_templates();
        $this->setUser($this->tenant_admin(''));

        $this->assertSame([], $this->legacy_subjects());
    }

    public function test_site_admin_still_sees_every_legacy_template(): void {
        $this->seed_legacy_templates();
        $this->setAdminUser();

        $subjects = $this->legacy_subjects();
        sort($subjects);
        $this->assertSame(['AIRPAY-LEGACY', 'SITE-LEGACY', 'ZEEA-LEGACY-SECRET'], $subjects);
    }

    public function test_rule_ui_offers_a_scoped_admin_only_what_the_server_accepts(): void {
        $global = $this->rule(0);
        $own = $this->rule(1);
        $foreign = $this->rule(177);
        $this->setUser($this->tenant_admin('/1'));

        $rows = [];
        foreach (manage_controller::get_rules_data(tenant_scope::resolve(0))['rules'] as $row) {
            $rows[(int) $row['id']] = $row;
        }
        $this->assertArrayNotHasKey($foreign, $rows);
        $this->assertFalse($rows[$global]['can_modify'],
            'No toggle/edit/delete on a global rule: the server refuses it.');
        $this->assertTrue($rows[$own]['can_modify']);

        // The scope select holds their own tenant only, pre-selected - even
        // when editing a global rule - so "All Tenants (Global)" is never offered.
        // (No org registry row for tenant 1 here, so it carries the neutral label.)
        $own = self::str('rule_scope_tenant', self::str('tenant_n', 1));
        foreach ([null, 0, 177, 1] as $ruletenant) {
            $this->assertSame([['id' => 1, 'name' => $own, 'selected' => true]],
                manage_controller::rule_scope_options(1, $ruletenant));
        }
        $this->assertSame([1], array_column(manage_controller::tenant_selector_options(1), 'id'));

        // And the save refuses what the form no longer offers.
        $this->assert_refused(fn() => tenant_scope::require_can_write_tenant(0),
            'A posted rule_tenant=0 is refused, no longer rewritten to the caller\'s tenant.');
    }

    public function test_rule_ui_for_a_caller_with_no_tenant_offers_nothing(): void {
        $this->setUser($this->tenant_admin(''));
        $this->assertSame([], manage_controller::rule_scope_options(0));
        $this->assertSame([], manage_controller::tenant_selector_options(0));
    }

    public function test_rule_ui_for_site_admin_keeps_every_scope_and_preselects_the_rule(): void {
        $foreign = $this->rule(177);
        $this->setAdminUser();

        $options = manage_controller::rule_scope_options(0, 177);
        $this->assertSame([0, 1, 77, 177], array_column($options, 'id'));
        $this->assertSame([177], array_column(array_filter($options, fn($o) => $o['selected']), 'id'),
            'Editing a tenant rule must not default its scope to Global.');
        $this->assertSame([0], array_column(array_filter(manage_controller::rule_scope_options(0),
            fn($o) => $o['selected']), 'id'));
        // A new rule from manage.php?tenant=177 starts scoped to 177, not Global.
        $this->assertSame([177], array_column(array_filter(manage_controller::rule_scope_options(177),
            fn($o) => $o['selected']), 'id'));
        $this->assertSame([0, 1, 77, 177], array_column(manage_controller::tenant_selector_options(0), 'id'));
        foreach (manage_controller::get_rules_data(0)['rules'] as $row) {
            $this->assertTrue($row['can_modify']);
        }
        $this->assertContains($foreign, array_map(fn($r) => (int) $r['id'],
            manage_controller::get_rules_data(0)['rules']));
    }

    public function test_tenant_admin_keeps_the_template_preview(): void {
        // template_api::preview_template now requires :preview. It defaults to
        // the manager archetype, which is what a tenant admin's role is.
        $this->setUser($this->tenant_admin('/1'));
        $html = external\template_api::preview_template(self::TPL, 1, '<p>Hello {{firstname}}</p>', 'Hi')['html'];
        $this->assertStringContainsString('Hello', $html);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        external\template_api::preview_template(self::TPL, 1, '<p>x</p>', 'Hi');
    }

    public function test_templates_tab_reports_the_override_the_tenant_actually_receives(): void {
        global $DB;
        // The 2026092501 upgrade switched tenant 177's override off; the
        // global one is still active, so 177's learners receive the global.
        $global = $this->override(0, 'GLOBAL');
        $tenantrow = $this->override(177, 'SWITCHED-OFF', 0);
        $status = fn(int $tenant): array =>
            array_column(template_manager::get_templates_with_status($tenant), null, 'key')[self::TPL];

        $this->assertSame('global_override', template_manager::get_override(self::TPL, 177)->source);
        $row = $status(177);
        $this->assertTrue($row['has_override'],
            'An inactive tenant row must not make the tab say "no override" while the global one is delivered.');
        $this->assertSame('global', $row['source']);
        $this->assertSame($global, (int) $row['override_id']);

        // An active tenant row wins, exactly as it does for delivery.
        $DB->set_field('local_sentientia_email_overrides', 'is_active', 1, ['id' => $tenantrow]);
        $this->assertSame('tenant_override', template_manager::get_override(self::TPL, 177)->source);
        $row = $status(177);
        $this->assertSame('tenant', $row['source']);
        $this->assertSame($tenantrow, (int) $row['override_id']);

        // The global scope's own rows are labelled global, not tenant.
        $this->assertSame('global', $status(0)['source']);
        $this->assertSame($global, (int) $status(0)['override_id']);
    }

    public function test_tenant_labels_come_from_the_org_registry_not_a_hardcoded_customer(): void {
        $this->org_root(1, 'Northwind Learning');
        $this->org_root(77, 'Contoso Partners');
        // Tenant 177 has no registry row: it gets the neutral "Tenant N" label.
        $fallback = self::str('tenant_n', 177);

        $this->setAdminUser();
        $selector = manage_controller::tenant_selector_options(0);
        $scopes = manage_controller::rule_scope_options(0);
        $this->assertSame([self::str('tenant_all'), 'Northwind Learning', 'Contoso Partners', $fallback],
            array_column($selector, 'name'));
        $this->assertSame([self::str('rule_scope_global'), self::str('rule_scope_tenant', 'Northwind Learning'),
            self::str('rule_scope_tenant', 'Contoso Partners'), self::str('rule_scope_tenant', $fallback)],
            array_column($scopes, 'name'));
        foreach (array_merge($selector, $scopes) as $option) {
            $this->assertStringNotContainsString('Airpay', $option['name'],
                'A white-label deployment must not show another customer\'s name as a tenant label.');
        }

        // A scoped caller is offered their own tenant, under its registry name.
        $this->setUser($this->tenant_admin('/77/80'));
        $this->assertSame([['id' => 77, 'name' => self::str('rule_scope_tenant', 'Contoso Partners'), 'selected' => true]],
            manage_controller::rule_scope_options(77));
        $this->assertSame([['id' => 77, 'name' => 'Contoso Partners', 'selected' => true]],
            manage_controller::tenant_selector_options(0));
    }

    public function test_readonly_global_rule_form_cannot_be_submitted(): void {
        global $OUTPUT;
        $this->setUser($this->tenant_admin('/1'));
        $context = fn(bool $readonly): array => [
            'show_form'          => true,
            'has_editrule'       => true,
            'editrule_readonly'  => $readonly,
            'editrule'           => ['id' => 42, 'rule_name' => 'Global reminder', 'enabled' => true,
                                     'trigger_days' => 7, 'priority' => 50],
            'rule_scope_options' => [['id' => 1, 'name' => 'Own tenant', 'selected' => true]],
            'template_options'   => [],
            'tabdata'            => ['rule_count' => 1, 'rules' => [[
                'id' => 42, 'rule_name' => 'Global reminder', 'rule_type' => 'custom', 'channel' => 'email',
                'audience' => 'Learner', 'trigger_days' => 7, 'priority' => 50, 'template_key' => '',
                'tenant_id' => 0, 'enabled' => true, 'is_global' => true, 'can_modify' => !$readonly,
            ]]],
            'sesskey'            => sesskey(),
            'manage_url'         => 'https://example.invalid/local/sentientia_emails/manage.php',
            'tenant_id'          => 1,
        ];

        // Read-only (a global rule seen by a scoped admin): nothing is editable or postable.
        $html = $OUTPUT->render_from_template('local_sentientia_emails/manage/tab_rules', $context(true));
        $this->assertStringContainsString('<fieldset class="ap-manage-fieldset" disabled', $html,
            'Every field of a read-only rule sits in a disabled fieldset.');
        $this->assertStringNotContainsString('value="saverule"', $html, 'A read-only form carries no save action.');
        $this->assertStringNotContainsString('name="sesskey"', $html);
        $this->assertStringNotContainsString('name="ruleid"', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
        $locked = get_string('rule_locked', 'local_sentientia_emails');
        $this->assertStringContainsString('aria-label="' . s($locked) . '"', $html,
            'The disabled toggle has an accessible name, not only a title.');
        $this->assertSame(2, substr_count($html, 'class="fa fa-lock" aria-hidden="true"'),
            'Both lock icons are decorative; the text beside them carries the meaning.');
        $this->assertStringNotContainsString('<i class="fa fa-lock"></i>', $html);

        // The editable form still posts saverule with its sesskey.
        $html = $OUTPUT->render_from_template('local_sentientia_emails/manage/tab_rules', $context(false));
        $this->assertStringContainsString('<fieldset class="ap-manage-fieldset">', $html);
        $this->assertStringContainsString('name="action" value="saverule"', $html);
        $this->assertStringContainsString('name="ruleid" value="42"', $html);
    }
}
