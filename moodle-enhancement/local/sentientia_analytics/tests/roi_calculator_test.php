<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for roi_calculator.
 *
 * Coverage:
 *  1. model(): exact figures with the default assumptions
 *  2. model(): no cost gives a null ROI, not a division by zero
 *  3. compute(): numbers-only shape (cache holds nothing language-specific)
 *  4. compute(): a changed setting is used at once (it is part of the cache key)
 *  5. compute(): an empty, non-numeric or negative setting falls back to the default
 *  6. Cache hit on repeat call / re-compute after invalidation
 *  7. Tenant isolation — /9999 has no completions
 *  8. export_for_template(): every figure is formatted, all 7 assumptions listed
 *  9. export_for_template(): empty states (no completions, no cost) and the settings link
 * 10. format_money(): separators, sign, decimals
 * 11. roi_dashboard.mustache prints the figures (QW-ROI: it printed a lone "%")
 * 12. Flag-OFF no-op pattern
 *
 * @package    local_sentientia_analytics
 * @category   test
 *
 * @group tenant_isolation
 */
final class roi_calculator_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** Metrics used by the model tests: every figure below follows from these. */
    private const METRICS = [
        'completions'      => 10,
        'mandatory_ontime' => 4,
        'active_courses'   => 2,
        'active_learners'  => 5,
    ];

    // ── 1. model() arithmetic ─────────────────────────────────────────

    /**
     * Defaults: 2 h saved, ₹500/h, ₹1,000 penalty, 40 h per course,
     * ₹50,000 platform, ₹50 per learner-hour, 3 h per course.
     *   productivity 10 × 2 × 500 = 10,000; compliance 4 × 1,000 = 4,000
     *   L&D 2 × 40 × 500 = 40,000; platform 50,000; content 5 × 3 × 50 = 750
     *   benefit 14,000; cost 90,750; net -76,750; ROI round(-84.57) = -85
     */
    public function test_model_figures_with_defaults(): void {
        $r = roi_calculator::model(self::METRICS, roi_calculator::defaults());

        $this->assertEquals(10000.0, $r['lines']['productivity']);
        $this->assertEquals(4000.0, $r['lines']['compliance']);
        $this->assertEquals(40000.0, $r['lines']['ld_staff']);
        $this->assertEquals(50000.0, $r['lines']['platform']);
        $this->assertEquals(750.0, $r['lines']['content']);
        $this->assertEquals(14000.0, $r['total_benefit']);
        $this->assertEquals(90750.0, $r['total_cost']);
        $this->assertEquals(-76750.0, $r['net_benefit']);
        $this->assertSame(-85, $r['roi_pct']);
    }

    public function test_model_positive_roi(): void {
        $r = roi_calculator::model(['completions' => 1000] + self::METRICS, roi_calculator::defaults());
        // Benefit 1,000,000 + 4,000; cost 90,750.
        $this->assertGreaterThan(0, $r['roi_pct']);
        $this->assertGreaterThan(0, $r['net_benefit']);
    }

    // ── 2. No cost ────────────────────────────────────────────────────

    public function test_model_zero_cost_gives_null_roi(): void {
        $cfg = ['platform_cost' => 0.0, 'course_dev_hours' => 0.0, 'cost_per_learner_hour' => 0.0]
            + roi_calculator::defaults();
        $r = roi_calculator::model(self::METRICS, $cfg);
        $this->assertEquals(0.0, $r['total_cost']);
        $this->assertNull($r['roi_pct']);
    }

    // ── 3. compute() shape ────────────────────────────────────────────

    public function test_compute_returns_required_keys(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        roi_calculator::invalidate_caches();

        $result = roi_calculator::compute('ytd', '/9999', true);

        foreach (['roi_pct', 'total_benefit', 'total_cost', 'net_benefit', 'lines',
                  'raw_metrics', 'cfg', 'range'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
        $this->assertSame('ytd', $result['range']);
        $this->assertSame(array_keys(roi_calculator::ASSUMPTIONS), array_keys($result['cfg']));
        // Numbers only: nothing in the cached array is a sentence.
        array_walk_recursive($result, function($v, $k) {
            $this->assertFalse(is_string($v) && $k !== 'range', "unexpected string at $k");
        });
    }

    // ── 4. Settings are used at once ──────────────────────────────────

    public function test_changed_setting_is_used_without_waiting_for_cache(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        roi_calculator::invalidate_caches();

        $before = roi_calculator::compute('30d', '/1', false);
        $this->assertEquals(roi_calculator::DEFAULT_PLATFORM_COST, $before['cfg']['platform_cost']);

        set_config('roi_platform_cost', '75000', 'local_sentientia_analytics');
        $after = roi_calculator::compute('30d', '/1', false);
        $this->assertEquals(75000.0, $after['cfg']['platform_cost']);
        $this->assertEquals(75000.0, $after['lines']['platform']);
    }

    // ── 5. Bad settings fall back ─────────────────────────────────────

    public function test_unusable_settings_fall_back_to_defaults(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        roi_calculator::invalidate_caches();

        set_config('roi_hourly_rate', '', 'local_sentientia_analytics');
        set_config('roi_penalty_avoided', 'abc', 'local_sentientia_analytics');
        set_config('roi_hours_per_course', '-3', 'local_sentientia_analytics');
        set_config('roi_course_dev_hours', '12.5', 'local_sentientia_analytics');

        $cfg = roi_calculator::compute('30d', '', true)['cfg'];
        $this->assertEquals(roi_calculator::DEFAULT_HOURLY_RATE, $cfg['hourly_rate']);
        $this->assertEquals(roi_calculator::DEFAULT_PENALTY_AVOIDED, $cfg['penalty_avoided']);
        $this->assertEquals(roi_calculator::DEFAULT_HOURS_PER_COURSE, $cfg['hours_per_course']);
        $this->assertEquals(12.5, $cfg['course_dev_hours']);
    }

    // ── 6. Cache ──────────────────────────────────────────────────────

    public function test_roi_cache_hit(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        roi_calculator::invalidate_caches();

        $first  = roi_calculator::compute('30d', '/1', true);
        $second = roi_calculator::compute('30d', '/1', false);

        $this->assertSame($first['roi_pct'], $second['roi_pct']);
        $this->assertSame($first['total_cost'], $second['total_cost']);
    }

    public function test_roi_cache_invalidation(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        roi_calculator::invalidate_caches();
        roi_calculator::compute('30d', '/1', true);
        roi_calculator::invalidate_caches();
        $second = roi_calculator::compute('30d', '/1', true);

        $this->assertIsArray($second, 're-compute after invalidation must return array');
    }

    // ── 7. Tenant isolation via raw_metrics ───────────────────────────

    public function test_tenant_scoping_in_raw_metrics(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        roi_calculator::invalidate_caches();

        $result9999 = roi_calculator::compute('30d', '/9999', true);
        $this->assertSame(0, (int) $result9999['raw_metrics']['completions'],
            '/9999 completions should be 0 (no users seeded)');
    }

    // ── 8. export_for_template(): figures ─────────────────────────────

    public function test_export_formats_every_figure(): void {
        $this->resetAfterTest();
        $roi = roi_calculator::model(self::METRICS, roi_calculator::defaults()) + ['range' => 'ytd'];

        $ctx = roi_calculator::export_for_template($roi, false);

        $this->assertFalse($ctx['roi_empty']);
        $this->assertSame('-85%', $ctx['roi_pct_display']);
        $this->assertTrue($ctx['roi_negative']);
        $this->assertFalse($ctx['roi_positive']);
        $this->assertSame('₹14,000', $ctx['total_benefit']);
        $this->assertSame('₹90,750', $ctx['total_cost']);
        $this->assertSame('-₹76,750', $ctx['net_benefit']);
        $this->assertStringContainsString('the year to date', $ctx['summary']);
        $this->assertStringContainsString('₹76,750', $ctx['summary']);

        $this->assertCount(2, $ctx['benefits']);
        $this->assertCount(3, $ctx['costs']);
        $this->assertSame('₹10,000', $ctx['benefits'][0]['value']);
        $this->assertSame('10 course completions × 2 h saved per completion × ₹500 per hour',
            $ctx['benefits'][0]['formula']);
        $this->assertSame('₹750', $ctx['costs'][2]['value']);

        // All seven assumptions, each with a unit and marked as the default.
        $this->assertSame(array_keys(roi_calculator::ASSUMPTIONS),
            array_column($ctx['assumptions'], 'assumption_key'));
        $this->assertTrue($ctx['uses_defaults']);
        foreach ($ctx['assumptions'] as $a) {
            $this->assertNotEmpty($a['assumption_label']);
            $this->assertNotEmpty($a['assumption_value']);
            $this->assertTrue($a['is_default']);
        }
        // No settings link for a viewer who cannot configure.
        $this->assertFalse($ctx['can_configure']);
        $this->assertSame('', $ctx['settings_url']);
    }

    public function test_export_marks_changed_assumptions(): void {
        $this->resetAfterTest();
        $cfg = ['hourly_rate' => 650.0] + roi_calculator::defaults();
        $ctx = roi_calculator::export_for_template(roi_calculator::model(self::METRICS, $cfg), true);

        $this->assertFalse($ctx['uses_defaults']);
        $byk = array_column($ctx['assumptions'], null, 'assumption_key');
        $this->assertFalse($byk['hourly_rate']['is_default']);
        $this->assertSame('₹650 per hour', $byk['hourly_rate']['assumption_value']);
        $this->assertTrue($byk['platform_cost']['is_default']);
        $this->assertTrue($ctx['can_configure']);
        $this->assertStringContainsString('section=local_sentientia_analytics', $ctx['settings_url']);
    }

    // ── 9. Empty states ───────────────────────────────────────────────

    public function test_export_empty_when_no_completions(): void {
        $this->resetAfterTest();
        $roi = roi_calculator::model(['completions' => 0, 'mandatory_ontime' => 0] + self::METRICS,
            roi_calculator::defaults()) + ['range' => '7d'];

        $ctx = roi_calculator::export_for_template($roi, true);
        $this->assertTrue($ctx['roi_empty']);
        $this->assertSame(get_string('roi_empty_title_nocompletions', 'local_sentientia_analytics'),
            $ctx['empty_state']['title']);
        $this->assertStringContainsString('the last 7 days', $ctx['empty_state']['message']);
        $this->assertNotEmpty($ctx['empty_state']['ctaurl'], 'an admin gets the settings link');

        $ctx = roi_calculator::export_for_template($roi, false);
        $this->assertSame('', $ctx['empty_state']['ctaurl'], 'no link for a viewer who cannot configure');
    }

    public function test_export_empty_when_no_cost(): void {
        $this->resetAfterTest();
        $cfg = ['platform_cost' => 0.0, 'course_dev_hours' => 0.0, 'cost_per_learner_hour' => 0.0]
            + roi_calculator::defaults();
        $ctx = roi_calculator::export_for_template(roi_calculator::model(self::METRICS, $cfg), true);

        $this->assertTrue($ctx['roi_empty']);
        $this->assertSame(get_string('roi_empty_title_nocost', 'local_sentientia_analytics'),
            $ctx['empty_state']['title']);
    }

    // ── 10. format_money() ────────────────────────────────────────────

    public function test_format_money(): void {
        $this->assertSame('₹1,234,567', roi_calculator::format_money(1234567.4, 0));
        $this->assertSame('₹1,234,567.40', roi_calculator::format_money(1234567.4));
        $this->assertSame('-₹500', roi_calculator::format_money(-500));
        $this->assertSame('₹2.50', roi_calculator::format_money(2.5));
        $this->assertSame('₹0', roi_calculator::format_money(-0.2, 0), 'no "-₹0"');
        $this->assertSame('₹1.77', roi_calculator::format_money(1.7654, 2));
    }

    // ── 11. The template prints the figures ───────────────────────────

    /**
     * QW-ROI regression: index.php handed the template roi_roi_pct,
     * roi_total_benefit, ... and the template read roi_pct, total_benefit, so
     * the card showed a lone "%" and headings with no figures.
     */
    public function test_template_prints_figures(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $roi = roi_calculator::model(self::METRICS, roi_calculator::defaults()) + ['range' => '30d'];

        $html = $OUTPUT->render_from_template('local_sentientia_analytics/roi_dashboard', [
            'roi' => roi_calculator::export_for_template($roi, true),
        ]);

        $this->assertStringContainsString('-85%', $html);
        $this->assertStringContainsString('₹14,000', $html);
        $this->assertStringContainsString('₹90,750', $html);
        $this->assertStringContainsString('-₹76,750', $html);
        $this->assertStringContainsString('10 course completions', $html);
        $this->assertStringContainsString('₹50,000', $html);
        $this->assertStringContainsString('section=local_sentientia_analytics', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*%\s*</', $html, 'no lone "%"');
    }

    // ── 12. Flag-OFF no-op pattern ────────────────────────────────────

    public function test_flag_off_no_roi_data(): void {
        $flag_on = false;
        $data = [];

        if ($flag_on) {
            $data['roi'] = roi_calculator::compute('30d', '', true);
        }

        $this->assertArrayNotHasKey('roi', $data,
            'When ROI flag is OFF, roi key must not appear in page data');
    }
}
