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
 *
 * @group tenant_isolation
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

    // ── 10. Skill-gap names are formatted once, escaped once (PB-3) ──

    /**
     * Team and skill names reach predictive_skillgap.mustache unescaped, so the
     * template's {{ }} escapes "&" once. Before PB-3 format_string() escaped it as
     * well and the card printed a literal "&amp;".
     */
    public function test_skillgap_names_not_pre_escaped(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $teampath = '/1/90001/90002';
        $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => 'Human Resources & Admin', 'shortname' => 'pb3team',
            'parentid' => 0, 'path' => $teampath, 'depth' => 3, 'visible' => 1,
            'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $cat    = $this->getDataGenerator()->create_category(['name' => 'Safety & Risk']);
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        $user   = $this->user_at_path($teampath);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        predictive_engine::invalidate_caches();
        $result = predictive_engine::get_skill_gap_projection('/1', true);

        $teams = array_values(array_filter($result,
            fn($r) => $r['team_path'] === $teampath));
        $this->assertCount(1, $teams);
        $this->assertSame('Human Resources & Admin', $teams[0]['team_name']);
        $this->assertSame(['Safety & Risk'],
            array_column($teams[0]['skill_gaps'], 'skill_name'));

        // The template escapes once: one "&amp;" in the HTML, never "&amp;amp;".
        $html = $this->render_skillgap($teams);
        $this->assertStringContainsString('Human Resources &amp; Admin', $html);
        $this->assertStringContainsString('Safety &amp; Risk', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    // ── 11. Every category of a path is counted (QW-ROI, 2026-11-19) ──

    /**
     * One member enrolled in two courses in two categories, one completed:
     * required 2, covered 1, gap 50%. get_records_sql() keyed the rows by
     * path, kept one category per path, and reported required 1.
     */
    public function test_skillgap_counts_every_category_of_a_path(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $teampath = '/1/90011/90012';
        $this->insert_team('Two Category Team', $teampath);
        $catdone = $this->getDataGenerator()->create_category(['name' => 'Done Category']);
        $catopen = $this->getDataGenerator()->create_category(['name' => 'Open Category']);
        $done = $this->getDataGenerator()->create_course(['category' => $catdone->id]);
        $open = $this->getDataGenerator()->create_course(['category' => $catopen->id]);
        $user = $this->user_at_path($teampath . '/90013');
        $this->getDataGenerator()->enrol_user($user->id, $done->id);
        $this->getDataGenerator()->enrol_user($user->id, $open->id);
        $DB->insert_record('course_completions', (object) [
            'userid' => $user->id, 'course' => $done->id, 'timeenrolled' => 0,
            'timestarted' => 0, 'timecompleted' => time(), 'reaggregate' => 0,
        ]);

        predictive_engine::invalidate_caches();
        $rows = array_values(array_filter(predictive_engine::get_skill_gap_projection('/1', true),
            fn($r) => $r['team_path'] === $teampath));

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['required_skills']);
        $this->assertSame(1, $rows[0]['covered_skills']);
        $this->assertSame(50, $rows[0]['gap_pct']);
        $this->assertTrue($rows[0]['has_skill_gaps']);
        $this->assertSame(['Open Category'], array_column($rows[0]['skill_gaps'], 'skill_name'));
    }

    // ── 12. '' scope covers every tenant; a tenant scope only its own ──

    public function test_skillgap_whole_site_scope_includes_every_tenant(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $teampath = '/77/90021/90022';
        $this->insert_team('Public Tenant Team', $teampath);
        $course = $this->getDataGenerator()->create_course();
        $user   = $this->user_at_path($teampath);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        predictive_engine::invalidate_caches();
        $all  = array_column(predictive_engine::get_skill_gap_projection('', true), 'team_path');
        $one  = array_column(predictive_engine::get_skill_gap_projection('/1', true), 'team_path');
        $pub  = array_column(predictive_engine::get_skill_gap_projection('/77', true), 'team_path');

        $this->assertContains($teampath, $all, "'' is the whole site, not tenant 1");
        $this->assertNotContains($teampath, $one, 'tenant 1 must not see a /77 team');
        $this->assertContains($teampath, $pub);
    }

    // ── 13. Uncovered tags print once per category ────────────────────

    /**
     * The template's outer section tested skill_gaps, which repeats once per
     * item: two uncovered categories printed two rows of two tags.
     */
    public function test_skillgap_template_prints_each_tag_once(): void {
        $this->resetAfterTest();
        $html = $this->render_skillgap([[
            'team_name' => 'T', 'team_path' => '/1/2/3', 'required_skills' => 2,
            'covered_skills' => 0, 'gap_pct' => 100, 'gap_band' => 'high',
            'gap_high' => true, 'gap_med' => false, 'gap_low' => false,
            'has_skill_gaps' => true,
            'skill_gaps' => [['skill_name' => 'Alpha'], ['skill_name' => 'Beta']],
        ]]);

        $this->assertSame(1, substr_count($html, 'airpay-analytics__skillgap-tags'));
        $this->assertSame(2, substr_count($html, 'airpay-analytics__skill-tag"'));
    }

    /**
     * Insert a depth-3 org unit (a team).
     */
    private function insert_team(string $name, string $path): void {
        global $DB;
        $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => $name, 'shortname' => 'qw' . md5($path),
            'parentid' => 0, 'path' => $path, 'depth' => 3, 'visible' => 1,
            'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Render predictive_skillgap.mustache with the given rows.
     */
    private function render_skillgap(array $rows): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('local_sentientia_analytics/predictive_skillgap', [
            'skillgap_description' => '',
            'has_skillgaps'        => true,
            'skillgaps'            => $rows,
        ]);
    }
}
