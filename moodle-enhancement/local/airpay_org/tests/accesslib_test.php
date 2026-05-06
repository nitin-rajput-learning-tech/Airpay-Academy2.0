<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_org;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the BizLMS-ported accesslib methods.
 *
 * Locks in:
 * - costcenterpath_match_sql with LOWER_AND_SAME (descendants only)
 * - costcenterpath_match_sql with UPPER_AND_SAME (path + ancestors)
 * - userpath_match_sql for non-siteadmin (returns user's path filter)
 * - userpath_match_sql for siteadmin (returns empty — see all)
 * - costcenterpath_contextdata returns coursecat context for known path
 * - costcenterpath_contextdata falls back to system for unknown path
 *
 * @package    local_airpay_org
 * @category   test
 */
final class accesslib_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    /**
     * LOWER_AND_SAME: '/1' matches /1 and /1/foo but NOT /10 or /177.
     */
    public function test_costcenterpath_match_sql_lower_and_same(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        global $DB;
        $u_in_root  = $this->user_at_path('/1');
        $u_in_sub   = $this->user_at_path('/1/2/3');
        $u_decoy    = $this->user_at_path('/10');     // numeric-prefix decoy
        $u_other    = $this->user_at_path('/2');

        [$sql, $params] = accesslib::costcenterpath_match_sql('/1', 'u.open_path',
            accesslib::LOWER_AND_SAME);

        $this->assertNotEmpty($sql, 'expected non-empty SQL fragment');
        $this->assertGreaterThanOrEqual(2, count($params),
            'expected exact + like params');

        // Run the actual query against {user} to verify match set is correct.
        // Strip leading "AND " so we can use it as a standalone WHERE condition.
        $whereclause = preg_replace('/^\s*AND\s*/', '', $sql);
        $rows = $DB->get_records_sql(
            "SELECT u.id FROM {user} u WHERE {$whereclause} AND u.deleted = 0 ORDER BY u.id",
            $params);

        $matched = array_keys($rows);
        $this->assertContains((int) $u_in_root->id, $matched, 'exact /1 should match');
        $this->assertContains((int) $u_in_sub->id,  $matched, '/1/2/3 should match (descendant)');
        $this->assertNotContains((int) $u_decoy->id, $matched, '/10 should NOT match (numeric-prefix decoy)');
        $this->assertNotContains((int) $u_other->id, $matched, '/2 should NOT match');
    }

    /**
     * UPPER_AND_SAME: '/1/2/3' matches /1/2/3, /1/2/3/X, /1/2, /1.
     */
    public function test_costcenterpath_match_sql_upper_and_same(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        global $DB;
        $u_at_target  = $this->user_at_path('/1/2/3');
        $u_at_descend = $this->user_at_path('/1/2/3/4');
        $u_at_anc1    = $this->user_at_path('/1/2');
        $u_at_anc2    = $this->user_at_path('/1');
        $u_sibling    = $this->user_at_path('/2/2/3');     // same suffix, different root
        $u_unrelated  = $this->user_at_path('/9');

        [$sql, $params] = accesslib::costcenterpath_match_sql('/1/2/3', 'u.open_path',
            accesslib::UPPER_AND_SAME);

        $whereclause = preg_replace('/^\s*AND\s*/', '', $sql);
        $rows = $DB->get_records_sql(
            "SELECT u.id FROM {user} u WHERE {$whereclause} AND u.deleted = 0 ORDER BY u.id",
            $params);
        $matched = array_keys($rows);

        $this->assertContains((int) $u_at_target->id,  $matched, '/1/2/3 itself');
        $this->assertContains((int) $u_at_descend->id, $matched, '/1/2/3/4 descendant');
        $this->assertContains((int) $u_at_anc1->id,    $matched, '/1/2 ancestor');
        $this->assertContains((int) $u_at_anc2->id,    $matched, '/1 ancestor');
        $this->assertNotContains((int) $u_sibling->id, $matched, '/2/2/3 sibling-tree');
        $this->assertNotContains((int) $u_unrelated->id, $matched, '/9 unrelated');
    }

    /**
     * Empty path returns empty SQL (no filtering).
     */
    public function test_costcenterpath_match_sql_empty_path_returns_empty(): void {
        $this->resetAfterTest();
        [$sql, $params] = accesslib::costcenterpath_match_sql('', 'x.col');
        $this->assertSame('', $sql);
        $this->assertSame([], $params);
    }

    /**
     * userpath_match_sql for siteadmin returns empty (sees everything).
     */
    public function test_userpath_match_sql_siteadmin_returns_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$sql, $params] = accesslib::userpath_match_sql('u.open_path');

        $this->assertSame('', $sql, 'siteadmin gets no scope filter');
        $this->assertSame([], $params);
    }

    /**
     * userpath_match_sql for a non-siteadmin returns their path filter.
     */
    public function test_userpath_match_sql_uses_user_open_path(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        global $DB;
        $caller = $this->user_at_path('/1');
        $this->setUser($caller);

        [$sql, $params] = accesslib::userpath_match_sql('u.open_path',
            accesslib::LOWER_AND_SAME);

        $this->assertNotEmpty($sql, 'expected scope filter for non-siteadmin');
        // Params should include /1 as exact + /1/% as like.
        $values = array_values($params);
        $this->assertContains('/1',   $values);
        $this->assertContains('/1/%', $values);
    }

    /**
     * costcenterpath_contextdata returns context_system for unknown path.
     */
    public function test_costcenterpath_contextdata_unknown_path_returns_system(): void {
        $this->resetAfterTest();
        $context = accesslib::costcenterpath_contextdata('/nonexistent/9999/8888');
        $this->assertInstanceOf(\context_system::class, $context);
    }

    /**
     * costcenterpath_contextdata returns context_coursecat for known path.
     *
     * Note: the category column lives on legacy local_costcenter (BizLMS),
     * not on our airpay_org. This test creates a costcenter row in test
     * DB if local_costcenter is present, otherwise asserts that the
     * fallback to system context still works.
     */
    public function test_costcenterpath_contextdata_known_path_returns_coursecat(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_costcenter')) {
            $this->markTestSkipped('local_costcenter (BizLMS legacy) not present in test DB; '
                . 'method correctly falls back to system context — covered by '
                . 'test_costcenterpath_contextdata_unknown_path_returns_system');
        }

        $catid = $this->getDataGenerator()->create_category()->id;
        $DB->insert_record('local_costcenter', (object)[
            'fullname'    => 'TestCC',
            'parentid'    => 0,
            'path'        => '/9001',
            'depth'       => 1,
            'category'    => $catid,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $context = accesslib::costcenterpath_contextdata('/9001');
        $this->assertInstanceOf(\context_coursecat::class, $context);
        $this->assertSame($catid, (int) $context->instanceid);
    }

    /**
     * Helper: create user at a given open_path.
     */
    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        return $u;
    }
}
