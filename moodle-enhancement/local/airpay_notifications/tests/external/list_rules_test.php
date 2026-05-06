<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_notifications\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for list_rules WS.
 *
 * Notification rules are global (not tenant-scoped) — they fire across
 * all tenants based on rule_type triggers. The test surface:
 *
 * - Capability gate: callers without local/airpay_notifications:manage are rejected
 * - Sort whitelist: bogus sort columns fall back to 'name'
 * - JSON filter bounds: > 4KB rejected with 'filterstoolong'
 * - Search escapes LIKE wildcards: '%' is treated as literal text
 * - enabled filter from JSON correctly scopes results
 *
 * @package    local_airpay_notifications
 * @category   test
 */
final class list_rules_test extends \advanced_testcase {

    /**
     * Insert a rule directly.
     */
    private function seed_rule(string $name, int $enabled = 1, string $rule_type = 'deadline_warning'): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_notif_rules')) {
            $this->markTestSkipped('local_airpay_notif_rules table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_notif_rules', (object)[
            'name'         => $name,
            'rule_type'    => $rule_type,
            'channel'      => 'inapp',
            'trigger_days' => 3,
            'audience'     => 'learner',
            'enabled'      => $enabled,
            'template'     => '',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * The notifications plugin's db/install.php seeds ~7 default rules. We need
     * a clean slate so our test seeds aren't drowned in pre-existing rows.
     */
    private function wipe_rules(): void {
        global $DB;
        if ($DB->get_manager()->table_exists('local_airpay_notif_rules')) {
            $DB->delete_records('local_airpay_notif_rules');
        }
    }

    /**
     * Create a user with local/airpay_notifications:manage capability.
     */
    private function user_with_manage(): \stdClass {
        $u = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/airpay_notifications:manage', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        return $u;
    }

    /**
     * A user without :manage gets a required_capability_exception.
     */
    public function test_capability_required_for_listing(): void {
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_rules::execute('', 'name', 'asc', 0, 25, '{}');
    }

    /**
     * Sort whitelist: bogus sort key falls back to 'name'.
     */
    public function test_sort_whitelist_rejects_bogus_column(): void {
        $this->resetAfterTest();
        $this->wipe_rules();

        $this->seed_rule('Charlie Rule');
        $this->seed_rule('Alpha Rule');
        $this->seed_rule('Bravo Rule');

        $u = $this->user_with_manage();
        $this->setUser($u);

        $result = list_rules::execute('', 'notarealcolumn', 'asc', 0, 25, '{}');

        $this->assertSame(3, (int) $result['total']);
        $this->assertSame('Alpha Rule',   $result['rows'][0]['name']);
        $this->assertSame('Bravo Rule',   $result['rows'][1]['name']);
        $this->assertSame('Charlie Rule', $result['rows'][2]['name']);
    }

    /**
     * JSON filter bounds: > 4KB rejected.
     */
    public function test_json_filter_rejects_oversized_payload(): void {
        $this->resetAfterTest();

        $u = $this->user_with_manage();
        $this->setUser($u);

        $bigjson = '{' . str_repeat('"key":"' . str_repeat('x', 100) . '",', 50) . '"end":1}';

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/filterstoolong/');
        list_rules::execute('', 'name', 'asc', 0, 25, $bigjson);
    }

    /**
     * Search escapes LIKE wildcards.
     */
    public function test_search_escapes_like_wildcards(): void {
        $this->resetAfterTest();
        $this->wipe_rules();

        $this->seed_rule('Reminder 50% Done');
        $this->seed_rule('Reminder Full');

        $u = $this->user_with_manage();
        $this->setUser($u);

        $result = list_rules::execute('50%', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Reminder 50% Done', $result['rows'][0]['name']);
    }

    /**
     * enabled filter from JSON: '0' returns disabled rules only;
     * '1' returns enabled only; 'all' (or absent) returns both.
     */
    public function test_enabled_filter_scopes_results(): void {
        $this->resetAfterTest();
        $this->wipe_rules();

        $this->seed_rule('Active Rule',   1);
        $this->seed_rule('Disabled Rule', 0);

        $u = $this->user_with_manage();
        $this->setUser($u);

        // Enabled only.
        $result = list_rules::execute('', 'name', 'asc', 0, 25, json_encode(['enabled' => '1']));
        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Active Rule', $result['rows'][0]['name']);

        // Disabled only.
        $result = list_rules::execute('', 'name', 'asc', 0, 25, json_encode(['enabled' => '0']));
        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Disabled Rule', $result['rows'][0]['name']);

        // All (default).
        $result = list_rules::execute('', 'name', 'asc', 0, 25, '{}');
        $this->assertSame(2, (int) $result['total']);
    }
}
