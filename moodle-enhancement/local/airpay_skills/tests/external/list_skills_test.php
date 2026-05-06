<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_skills\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for list_skills WS.
 *
 * Skills are global definitions (no tenant scoping) — the test surface
 * is narrower than tenant-scoped plugins. We lock in:
 *
 * - Capability gate: callers without local/airpay_skills:manage are rejected
 * - Sort whitelist: bogus sort columns fall back to 'name'
 * - JSON filter bounds: > 4KB rejected with 'filterstoolong'
 * - Search escapes LIKE wildcards: '%' is treated as literal text
 * - categoryid filter from JSON correctly scopes results
 *
 * @package    local_airpay_skills
 * @category   test
 */
final class list_skills_test extends \advanced_testcase {

    /**
     * Insert a skill directly. Skills don't have open_path.
     */
    private function seed_skill(string $name, int $categoryid = 0, int $sortorder = 0): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_skills')) {
            $this->markTestSkipped('local_airpay_skills table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_skills', (object)[
            'name'        => $name,
            'description' => '',
            'categoryid'  => $categoryid,
            'max_level'   => 5,
            'sort_order'  => $sortorder,
            'timecreated' => $now,
        ]);
    }

    /**
     * The skills plugin's db/install.php seeds ~48 default skills. We need a
     * clean slate so our test seeds aren't drowned in pre-existing rows.
     * Called at the top of every test that asserts on counts.
     */
    private function wipe_skills(): void {
        global $DB;
        if ($DB->get_manager()->table_exists('local_airpay_skills')) {
            $DB->delete_records('local_airpay_skills');
        }
    }

    /**
     * Create a user with local/airpay_skills:manage capability.
     */
    private function user_with_manage(): \stdClass {
        $u = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/airpay_skills:manage', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        return $u;
    }

    /**
     * A user without :manage gets a required_capability_exception.
     */
    public function test_capability_required_for_listing(): void {
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();   // no extra cap
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_skills::execute('', 'name', 'asc', 0, 25, '{}');
    }

    /**
     * Sort whitelist: bogus sort key falls back to 'name'.
     */
    public function test_sort_whitelist_rejects_bogus_column(): void {
        $this->resetAfterTest();
        $this->wipe_skills();

        $this->seed_skill('Charlie');
        $this->seed_skill('Alpha');
        $this->seed_skill('Bravo');

        $u = $this->user_with_manage();
        $this->setUser($u);

        $result = list_skills::execute('', 'notarealcolumn', 'asc', 0, 25, '{}');

        $this->assertSame(3, (int) $result['total']);
        $this->assertSame('Alpha',   $result['rows'][0]['name']);
        $this->assertSame('Bravo',   $result['rows'][1]['name']);
        $this->assertSame('Charlie', $result['rows'][2]['name']);
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
        list_skills::execute('', 'name', 'asc', 0, 25, $bigjson);
    }

    /**
     * Search escapes LIKE wildcards.
     */
    public function test_search_escapes_like_wildcards(): void {
        $this->resetAfterTest();
        $this->wipe_skills();

        $this->seed_skill('PHP 50% Coverage');
        $this->seed_skill('PHP Mastery');

        $u = $this->user_with_manage();
        $this->setUser($u);

        $result = list_skills::execute('50%', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('PHP 50% Coverage', $result['rows'][0]['name']);
    }

    /**
     * categoryid filter from JSON: only skills in the chosen category come back.
     */
    public function test_categoryid_filter_scopes_results(): void {
        $this->resetAfterTest();
        $this->wipe_skills();

        $cat_a = 7001;
        $cat_b = 7002;
        $this->seed_skill('Skill in A1', $cat_a);
        $this->seed_skill('Skill in A2', $cat_a);
        $this->seed_skill('Skill in B',  $cat_b);

        $u = $this->user_with_manage();
        $this->setUser($u);

        $result = list_skills::execute('', 'name', 'asc', 0, 25,
            json_encode(['categoryid' => $cat_a]));

        $this->assertSame(2, (int) $result['total']);
        $names = array_map(fn($r) => $r['name'], $result['rows']);
        sort($names);
        $this->assertSame(['Skill in A1', 'Skill in A2'], $names);
    }
}
