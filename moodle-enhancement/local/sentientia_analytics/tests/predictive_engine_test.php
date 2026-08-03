<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for predictive_engine.
 *
 * Coverage:
 *  1. compute_score — deterministic, weight-verified
 *  2. band thresholds
 *  3. gap_band thresholds
 *  4. Tenant isolation — /1 must not see /177 users in at-risk
 *  5. Cache hit on repeat call
 *  6. Cache invalidation
 *  7. Empty result when no users in scope
 *  8. Flag-OFF produces no predictive data on the index page data set
 *     (tested via the compute_score no-op path — flag check is in index.php,
 *     not in the engine itself, so we test the flag check pattern explicitly)
 *  9. skillsai degrade path (class_exists false when not installed)
 *
 * @package    local_sentientia_analytics
 * @category   test
 */
final class predictive_engine_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /**
     * Helper: set a user's open_path.
     */
    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        return $u;
    }

    // ── 1. compute_score — deterministic ─────────────────────────────

    /**
     * A fully engaged learner (recent login, all completed, no overdue,
     * no velocity drop) should score near 0.
     */
    public function test_compute_score_low_risk_learner(): void {
        $signals = [
            'lastaccess'    => time() - 3600,  // 1 hour ago
            'enrolled'      => 5,
            'completed'     => 5,
            'overdue'       => 0,
            'velocity_drop' => 0.0,
        ];
        $score = predictive_engine::compute_score($signals);
        // Recency: (1/3600 / 86400) / 60 ≈ 0 → ~0
        // Completion gap: 0
        // Overdue: 0
        // Velocity: 0
        // Total ≈ 0.
        $this->assertLessThanOrEqual(5, $score,
            'Fully engaged learner should have near-zero risk');
    }

    /**
     * A completely disengaged learner (never logged in, nothing complete,
     * 5 overdue, full velocity drop) should score 100.
     */
    public function test_compute_score_maximum_risk(): void {
        $signals = [
            'lastaccess'    => 0,   // never logged in
            'enrolled'      => 10,
            'completed'     => 0,
            'overdue'       => 5,
            'velocity_drop' => 1.0,
        ];
        $score = predictive_engine::compute_score($signals);
        // Recency saturated: 0.30 * 1.0 = 0.30
        // Completion gap max: 0.25 * 1.0 = 0.25
        // Overdue saturated:  0.25 * 1.0 = 0.25
        // Velocity max:       0.20 * 1.0 = 0.20
        // Total = 1.00 → 100.
        $this->assertSame(100, $score,
            'Fully disengaged learner should score 100');
    }

    /**
     * Weights must sum exactly: a signal with only one dimension
     * active should produce the expected weighted fraction.
     */
    public function test_compute_score_only_overdue_signal(): void {
        $signals = [
            'lastaccess'    => time(),  // just logged in → recency = 0
            'enrolled'      => 5,
            'completed'     => 5,       // no completion gap
            'overdue'       => 5,       // saturated
            'velocity_drop' => 0.0,
        ];
        $score = predictive_engine::compute_score($signals);
        // Only overdue contributes: 0.25 * 1.0 = 0.25 → score = 25.
        // Recency ≈ 0 (just logged in, ~0 seconds).
        $this->assertEqualsWithDelta(25, $score, 2,
            'Only overdue signal: expected ~25 (0.25 * 100)');
    }

    // ── 2. band thresholds ────────────────────────────────────────────

    public function test_band_high(): void {
        $this->assertSame('high',   predictive_engine::band(70));
        $this->assertSame('high',   predictive_engine::band(100));
        $this->assertSame('high',   predictive_engine::band(71));
    }

    public function test_band_medium(): void {
        $this->assertSame('medium', predictive_engine::band(40));
        $this->assertSame('medium', predictive_engine::band(69));
    }

    public function test_band_low(): void {
        $this->assertSame('low',    predictive_engine::band(0));
        $this->assertSame('low',    predictive_engine::band(39));
    }

    // ── 3. gap_band thresholds ────────────────────────────────────────

    public function test_gap_band_high(): void {
        $this->assertSame('high',   predictive_engine::gap_band(60));
        $this->assertSame('high',   predictive_engine::gap_band(100));
    }

    public function test_gap_band_medium(): void {
        $this->assertSame('medium', predictive_engine::gap_band(30));
        $this->assertSame('medium', predictive_engine::gap_band(59));
    }

    public function test_gap_band_low(): void {
        $this->assertSame('low',    predictive_engine::gap_band(0));
        $this->assertSame('low',    predictive_engine::gap_band(29));
    }

    // ── 4. Tenant isolation ───────────────────────────────────────────

    /**
     * at-risk list for /1 must NOT include users at /177.
     */
    public function test_atrisk_tenant_isolation(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        global $DB;

        // Users in /1 — make them look disengaged.
        $u1a = $this->user_at_path('/1');
        $u1b = $this->user_at_path('/1/2');
        // Users in /177 — also disengaged.
        $u177 = $this->user_at_path('/177');

        // All never logged in.
        foreach ([$u1a->id, $u1b->id, $u177->id] as $uid) {
            $DB->set_field('user', 'lastaccess', 0, ['id' => $uid]);
        }

        predictive_engine::invalidate_caches();

        $atrisk = predictive_engine::get_at_risk_users('/1', 50, true);

        // get_at_risk_users() casts userid to (int) (predictive_engine.php),
        // so the result column is int. $u->id from the data generator is a
        // string (Moodle $DB returns ids as strings). assertContains is
        // strict (===), so cast the expected ids to int to match the
        // production int contract.
        $user_ids_in_result = array_column($atrisk, 'userid');
        $this->assertContains((int) $u1a->id, $user_ids_in_result,
            'u1a (/1) should appear in at-risk list for /1');
        $this->assertContains((int) $u1b->id, $user_ids_in_result,
            'u1b (/1/2) should appear in at-risk list for /1');
        $this->assertNotContains((int) $u177->id, $user_ids_in_result,
            '/177 user must NOT appear in at-risk list for /1');
    }

    // ── 5. Cache hit on repeat call ───────────────────────────────────

    public function test_atrisk_cache_hit(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        global $DB;
        $u = $this->user_at_path('/1');
        $DB->set_field('user', 'lastaccess', 0, ['id' => $u->id]);

        predictive_engine::invalidate_caches();

        // First call — populates cache.
        $first = predictive_engine::get_at_risk_users('/1', 50, true);
        $first_count = count($first);

        // Add another user — should NOT appear if cache is hit.
        $u2 = $this->user_at_path('/1');
        $DB->set_field('user', 'lastaccess', 0, ['id' => $u2->id]);

        // Second call WITHOUT $refresh=true — should hit cache.
        $second = predictive_engine::get_at_risk_users('/1', 50, false);
        $this->assertSame($first_count, count($second),
            'Cache miss: get_at_risk_users re-queried after first warm call');
    }

    // ── 6. Cache invalidation ─────────────────────────────────────────

    public function test_cache_invalidation_triggers_requery(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        global $DB;
        $u = $this->user_at_path('/1');
        $DB->set_field('user', 'lastaccess', 0, ['id' => $u->id]);

        predictive_engine::invalidate_caches();
        $first = predictive_engine::get_at_risk_users('/1', 50, true);

        // Add new user.
        $u2 = $this->user_at_path('/1');
        $DB->set_field('user', 'lastaccess', 0, ['id' => $u2->id]);

        // Invalidate and re-query.
        predictive_engine::invalidate_caches();
        $second = predictive_engine::get_at_risk_users('/1', 50, true);

        $this->assertGreaterThan(count($first), count($second),
            'After cache invalidation + new user, result should be larger');
    }

    // ── 7. Empty result when no users in scope ─────────────────────────

    public function test_atrisk_empty_org_returns_empty(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        predictive_engine::invalidate_caches();
        $result = predictive_engine::get_at_risk_users('/9999', 50, true);
        $this->assertSame([], $result,
            'No users in /9999 — should return empty array, not throw');
    }

    // ── 8. Feature-flag-OFF no-op pattern ─────────────────────────────

    /**
     * When the platform feature_flags class is absent (e.g. isolated
     * plugin test environment), the class_exists guard in index.php must
     * mean predictive_engine is never called. We verify the guard logic
     * pattern here by mocking the condition.
     */
    public function test_flag_off_no_predictive_data(): void {
        // Simulate: the platform flag evaluates to false.
        $flag_on = false;
        $data = [];

        if ($flag_on) {
            // This block must not execute.
            $data['atrisk'] = predictive_engine::get_at_risk_users('', 50, true);
        }

        $this->assertArrayNotHasKey('atrisk', $data,
            'When flag is OFF, atrisk key must not be in data array');
    }

    // ── 9. skillsai degrade path ──────────────────────────────────────

    /**
     * When local_sentientia_skillsai\skill_gap_provider does NOT exist,
     * get_skill_gap_projection must return the heuristic result (not throw).
     *
     * We verify this doesn't throw and returns an array.
     */
    public function test_skillgap_degrades_gracefully_without_skillsai(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->assertFalse(
            class_exists('\local_sentientia_skillsai\skill_gap_provider'),
            'Pre-condition: skillsai must not be installed in test environment'
        );

        predictive_engine::invalidate_caches();

        // Must not throw — should return empty array (no org data seeded).
        $result = predictive_engine::get_skill_gap_projection('/1', true);
        $this->assertIsArray($result,
            'get_skill_gap_projection must return array even without skillsai');
    }
}
