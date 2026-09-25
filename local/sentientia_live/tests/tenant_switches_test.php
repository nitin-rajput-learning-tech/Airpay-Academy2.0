<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * The per-tenant Live kill switch flips one tenant, audited, and nothing else.
 *
 * Until 2026-09-25 admin/tenant_switches.php could not flip anything: it read
 * flag_key with the non-existent parameter type PARAM_ALPHANUMEXT . '.', so
 * required_param() threw before the whitelist, and past that it called the
 * non-existent feature_flags::invalidate_cache(). Its hand-rolled upsert
 * wrote no audit row, and its whitelist named the unregistered
 * live.questiontype.scale instead of live.questiontype.ranking. The write now
 * goes through tenant_switches::flip() -> feature_flags::set().
 *
 * What these tests pin, tenant-wise: switching Live for tenant 77 changes
 * tenant 77's resolution only - Airpay (1), ZEEA (177) and the global default
 * keep theirs - and only site configuration may flip at all (a tenant admin
 * cannot switch Live for any tenant, their own included).
 *
 * @package    local_sentientia_live
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_live\tenant_switches
 * @group      tenant_isolation
 */
final class tenant_switches_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        // Start from registered defaults for every Live flag.
        $DB->delete_records_select('local_sentientia_feature_flags',
            $DB->sql_like('flag_key', ':livekey'), ['livekey' => 'live.%']);
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    private function audit_count(string $key): int {
        global $DB;
        return $DB->count_records('local_sentientia_feature_flag_audit', ['flag_key' => $key]);
    }

    /** Assert $fn throws a moodle_exception carrying $errorcode. */
    private function assert_refused(callable $fn, string $errorcode, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $why);
        }
    }

    public function test_a_flip_changes_one_tenant_and_nothing_else(): void {
        global $DB;
        $this->setAdminUser();
        $flags = \local_sentientia_platform\feature_flags::class;
        $before = $this->audit_count('live.enabled');
        // Prime the resolver's cache: the flip has to invalidate it.
        $this->assertFalse($flags::is_enabled_for('live.enabled', 0, 77));

        tenant_switches::flip('live.enabled', 0, 77, true);

        $this->assertTrue($flags::is_enabled_for('live.enabled', 0, 77), 'Visible on the next read.');
        $this->assertFalse($flags::is_enabled_for('live.enabled', 0, 1), 'Airpay keeps its value.');
        $this->assertFalse($flags::is_enabled_for('live.enabled', 0, 177), 'So does ZEEA.');
        $this->assertFalse($flags::is_enabled_for('live.enabled', 0, 0), 'And the global default.');

        $rows = array_values($DB->get_records('local_sentientia_feature_flags', ['flag_key' => 'live.enabled']));
        $this->assertCount(1, $rows, 'Exactly one override row, for (customer 0, tenant 77).');
        $this->assertSame([0, 77, 1],
            [(int) $rows[0]->customer_id, (int) $rows[0]->tenant_id, (int) $rows[0]->is_enabled]);

        $audit = $DB->get_records('local_sentientia_feature_flag_audit', ['flag_key' => 'live.enabled'], 'id DESC');
        $this->assertSame($before + 1, count($audit), 'The flip is audited.');
        $last = reset($audit);
        $this->assertSame(77, (int) $last->tenant_id);
        $this->assertSame(1, (int) $last->new_value);
        $this->assertSame((int) get_admin()->id, (int) $last->changed_by);
        $this->assertSame(tenant_switches::AUDIT_REASON, (string) $last->reason);

        // And back off again.
        tenant_switches::flip('live.enabled', 0, 77, false);
        $this->assertFalse($flags::is_enabled_for('live.enabled', 0, 77));
        $this->assertSame($before + 2, $this->audit_count('live.enabled'));
    }

    public function test_the_whitelist_is_exactly_the_registered_live_flags(): void {
        global $DB;
        $this->setAdminUser();
        $registered = array_values(array_filter(
            array_keys(\local_sentientia_platform\feature_flags::load_registry()),
            fn($key) => strpos($key, 'live.') === 0));
        $this->assertEqualsCanonicalizing($registered, tenant_switches::FLAGS,
            'Every registered live.* flag is flippable, and nothing that is not registered.');

        tenant_switches::flip('live.questiontype.ranking', 0, 1, true);
        $this->assertTrue(\local_sentientia_platform\feature_flags::is_enabled_for(
            'live.questiontype.ranking', 0, 1), 'The ranking gate, which the old whitelist missed.');

        foreach (['live.questiontype.scale', \local_sentientia_platform\feature_flags::CUSTOMER_LEVEL_FLAG,
                'live.enabled.x', ''] as $key) {
            $this->assert_refused(fn() => tenant_switches::flip($key, 0, 1, true),
                'invalidflag', "'{$key}' is not a Live switch.");
        }
        $this->assertSame(1, $DB->count_records_select('local_sentientia_feature_flags',
            $DB->sql_like('flag_key', ':livekey'), ['livekey' => 'live.%']));
        $this->assertFalse($DB->record_exists('local_sentientia_feature_flags',
            ['flag_key' => \local_sentientia_platform\feature_flags::CUSTOMER_LEVEL_FLAG, 'tenant_id' => 1]));
    }

    public function test_a_tenant_admin_cannot_flip_any_tenant(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/77', ['id' => $u->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST));

        foreach ([77, 1, 0] as $tenant) {
            try {
                tenant_switches::flip('live.enabled', 0, $tenant, true);
                $this->fail("A tenant admin must not switch Live for tenant {$tenant}.");
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
        $this->assertSame(0, $DB->count_records('local_sentientia_feature_flags', ['flag_key' => 'live.enabled']));
    }

    public function test_a_customer_scoped_flip_is_refused_while_the_customer_layer_is_off(): void {
        global $DB;
        $this->setAdminUser();
        $this->assertFalse(\local_sentientia_platform\feature_flags::is_enabled_for(
            \local_sentientia_platform\feature_flags::CUSTOMER_LEVEL_FLAG, 0, 0),
            'Precondition: the customer layer is off by default.');

        $this->assert_refused(fn() => tenant_switches::flip('live.enabled', 1, 77, true),
            'customer_layer_disabled', 'feature_flags::set() guards customer-scoped writes.');
        $this->assertSame(0, $DB->count_records('local_sentientia_feature_flags', ['flag_key' => 'live.enabled']));
    }
}
