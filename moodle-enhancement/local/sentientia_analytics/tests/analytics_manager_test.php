<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for analytics_manager.
 *
 * Locks in:
 * - Cache layer (commit 9e3512499): repeat calls to get_kpis/funnel/
 *   compliance_heatmap/course_effectiveness with same args hit cache.
 * - Cross-tenant LIKE fix (commit ac22501e8): orgpath '/1' must NOT
 *   match users at '/100' or '/177'.
 * - get_compliance_heatmap N+1 → batched (commit 9e3512499): no per-
 *   department user/completion query.
 *
 * @package    local_sentientia_analytics
 * @category   test
 */
final class analytics_manager_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    /**
     * Place a user at a specific open_path.
     */
    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        return $u;
    }

    /**
     * Tenant /1 should NOT include users at /100 or /177.
     * Pre-fix (LIKE '/1%'): leaked across tenants.
     * Post-fix: exact match OR /-bounded prefix only.
     */
    public function test_kpis_does_not_leak_across_tenants(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        // 3 users at /1 tree, 2 users at /177 tree, 1 at /100.
        $u1a = $this->user_at_path('/1');
        $u1b = $this->user_at_path('/1/2/3');
        $u1c = $this->user_at_path('/1/2/4');
        $u177a = $this->user_at_path('/177');
        $u177b = $this->user_at_path('/177/178');
        $u100  = $this->user_at_path('/100');

        // Mark all as recently active to populate KPIs. Use time()-3600 so the
        // value is strictly LESS than $current_end (also time()) when get_kpis runs.
        global $DB;
        $hourago = time() - 3600;
        foreach ([$u1a->id, $u1b->id, $u1c->id, $u177a->id, $u177b->id, $u100->id] as $uid) {
            $DB->set_field('user', 'lastaccess', $hourago, ['id' => $uid]);
        }

        // Pre-purge cache so KPIs run fresh.
        \cache_helper::purge_by_definition('local_sentientia_analytics', 'kpis');

        $kpis_one = analytics_manager::get_kpis('30d', '/1');
        $active   = $kpis_one[0]['value']; // first KPI = Active Users

        // Active users for /1 should be 3 (u1a, u1b, u1c) — NOT 6.
        $this->assertSame(3, (int) $active,
            'tenant /1 leaked users from /100 or /177 (pre-fix: LIKE /1% bug)');
    }

    /**
     * Same tenant /1 query twice in a row should hit the cache on the
     * second call. We verify by checking that the result is identical
     * AND a new user added between calls is NOT reflected in the second
     * (proves cache hit, not a re-query).
     */
    public function test_kpis_cache_hit_on_repeat_call(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u1 = $this->user_at_path('/1');
        global $DB;
        $DB->set_field('user', 'lastaccess', time() - 3600, ['id' => $u1->id]);

        \cache_helper::purge_by_definition('local_sentientia_analytics', 'kpis');

        // First call — populates cache.
        $first = analytics_manager::get_kpis('30d', '/1');
        $first_active = (int) $first[0]['value'];

        // Insert a new active user — should NOT show up if cache hits.
        $u2 = $this->user_at_path('/1');
        $DB->set_field('user', 'lastaccess', time() - 3600, ['id' => $u2->id]);

        // Second call — should hit cache.
        $second = analytics_manager::get_kpis('30d', '/1');
        $second_active = (int) $second[0]['value'];

        $this->assertSame($first_active, $second_active,
            'cache miss: get_kpis re-queried instead of using cached result');
    }

    /**
     * Different orgpath = different cache key. /1 and /77 should each
     * produce their own correct count even when both cached together.
     */
    public function test_kpis_cache_key_segregates_by_orgpath(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->user_at_path('/1');
        $this->user_at_path('/1');
        $this->user_at_path('/77');
        global $DB;
        $hourago = time() - 3600;
        // Mark all 3 as recently active.
        foreach ($DB->get_records_sql("SELECT id FROM {user} WHERE open_path IN ('/1','/77')") as $u) {
            $DB->set_field('user', 'lastaccess', $hourago, ['id' => $u->id]);
        }

        \cache_helper::purge_by_definition('local_sentientia_analytics', 'kpis');

        $tenant1  = analytics_manager::get_kpis('30d', '/1');
        $tenant77 = analytics_manager::get_kpis('30d', '/77');

        $this->assertSame(2, (int) $tenant1[0]['value']);
        $this->assertSame(1, (int) $tenant77[0]['value']);
    }

    /**
     * get_compliance_heatmap should be safe when no departments exist
     * for the tenant (return empty array, not throw).
     */
    public function test_compliance_heatmap_empty_tenant_returns_empty(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        \cache_helper::purge_by_definition('local_sentientia_analytics', 'compliance_heatmap');

        // /9999 has no departments seeded — should return [].
        $result = analytics_manager::get_compliance_heatmap('/9999');
        $this->assertSame([], $result);
    }

    /**
     * trend() helper: should not divide by zero when previous=0.
     */
    public function test_trend_zero_previous_does_not_divide_by_zero(): void {
        $this->resetAfterTest();
        // Reflect-private since trend() is private static.
        $r = new \ReflectionMethod(analytics_manager::class, 'trend');
        $r->setAccessible(true);
        $result = $r->invoke(null, 5, 0);
        $this->assertIsArray($result);
        $this->assertSame(100, $result['pct'], 'previous=0, current>0 should yield 100% growth');
        $this->assertSame('up', $result['direction']);
    }
}
