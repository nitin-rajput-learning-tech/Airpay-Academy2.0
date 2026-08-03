<?php
/**
 * Training ROI Calculator — transparent, explainable model.
 *
 * ROI Formula
 * -----------
 * Return on Investment is calculated as:
 *
 *   ROI% = ((Benefits − Costs) / Costs) × 100
 *
 * Benefits are composed of two measurable proxies:
 *
 *   1. Productivity gain proxy
 *      Completed learners × avg_hours_saved_per_completion × blended_hourly_rate
 *      avg_hours_saved_per_completion = configurable (default 2h per course)
 *      blended_hourly_rate            = configurable (default ₹500/hr)
 *
 *   2. Compliance avoidance proxy
 *      Mandatory completions on time × penalty_avoided_per_on_time_completion
 *      penalty_avoided = configurable (default ₹1,000 per avoided incident)
 *
 * Costs are derived from:
 *   - L&D staff cost: active courses × avg_course_dev_cost_hrs × hourly_rate
 *   - Platform cost:  a flat configurable figure for the period
 *   - Content cost:   enrolled_learner_hours × cost_per_learner_hour
 *
 * Every assumption is surfaced in the returned array so the dashboard
 * can render an "assumptions" panel. Admins can override defaults via
 * the plugin settings page.
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_analytics;

defined('MOODLE_INTERNAL') || die();

class roi_calculator {

    // ── Default assumption constants (overridable via settings) ───────

    /** Average hours saved per course completion (productivity proxy). */
    public const DEFAULT_HOURS_SAVED_PER_COMPLETION = 2.0;

    /** Blended employee hourly rate in currency units (e.g. INR). */
    public const DEFAULT_HOURLY_RATE = 500.0;

    /** Compliance penalty avoided per on-time mandatory completion (INR). */
    public const DEFAULT_PENALTY_AVOIDED = 1000.0;

    /** Average hours to develop + maintain one course (L&D cost). */
    public const DEFAULT_COURSE_DEV_HOURS = 40.0;

    /** Flat platform + infrastructure cost for the period (INR). */
    public const DEFAULT_PLATFORM_COST = 50000.0;

    /** Cost per learner-hour of content consumption (INR). */
    public const DEFAULT_COST_PER_LEARNER_HOUR = 50.0;

    /** Average hours of content per course (to estimate learner-hours). */
    public const DEFAULT_HOURS_PER_COURSE = 3.0;

    /**
     * Compute Training ROI for the given org scope and time range.
     *
     * Returns an array with:
     *   - roi_pct          (int)   Calculated ROI as a percentage
     *   - total_benefit    (float) Total estimated benefit in currency units
     *   - total_cost       (float) Total estimated cost in currency units
     *   - net_benefit      (float) benefit − cost
     *   - currency_symbol  (string)
     *   - components       (array) Named cost + benefit line items for display
     *   - assumptions      (array) Every assumption used, for transparency panel
     *   - summary_sentence (string) Plain-language ROI summary
     *
     * @param string $range    Time range: 7d, 30d, 90d, ytd
     * @param string $orgpath  Tenant/org path filter (e.g. '/1')
     * @param bool   $refresh  Force cache bypass
     * @return array
     */
    public static function compute(string $range = '30d', string $orgpath = '',
                                    bool $refresh = false): array {
        $cache    = \cache::make('local_sentientia_analytics', 'roi');
        $cachekey = 'roi_' . md5($range . '|' . ($orgpath ?: 'all'));

        if (!$refresh) {
            $cached = $cache->get($cachekey);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Load configurable assumptions (fall back to constants).
        $cfg = self::load_assumptions();

        // ── Gather raw data ───────────────────────────────────────────
        global $DB;

        [$current_start, $current_end] = self::range_dates($range);

        $orgfilter = '';
        $orgparams = [];
        if (!empty($orgpath)) {
            $orgfilter = "AND (u.open_path = :aporgexact OR u.open_path LIKE :aporgprefix)";
            $orgparams['aporgexact']  = $orgpath;
            $orgparams['aporgprefix'] = $DB->sql_like_escape($orgpath) . '/%';
        }

        // Completions in period.
        $completions = (int) $DB->count_records_sql(
            "SELECT COUNT(cc.id) FROM {course_completions} cc
               JOIN {user} u ON u.id = cc.userid
              WHERE cc.timecompleted >= :apts AND cc.timecompleted < :apte
                AND cc.timecompleted IS NOT NULL $orgfilter",
            array_merge($orgparams, ['apts' => $current_start, 'apte' => $current_end]));

        // Mandatory (dated) completions on time.
        $mandatory_ontime = (int) $DB->count_records_sql(
            "SELECT COUNT(cc.id) FROM {course_completions} cc
               JOIN {course} c ON c.id = cc.course
               JOIN {user} u ON u.id = cc.userid
              WHERE cc.timecompleted >= :apts AND cc.timecompleted < :apte
                AND cc.timecompleted IS NOT NULL
                AND c.enddate > 0 AND cc.timecompleted <= c.enddate $orgfilter",
            array_merge($orgparams, ['apts' => $current_start, 'apte' => $current_end]));

        // Active visible courses in scope — tenant-scoped to match every sibling query in
        // this method. course.open_path is the BizLMS tenant path (used the same way in
        // analytics_manager::get_course_effectiveness). Distinct param names (corg*) avoid
        // colliding with the user-based aporg* binds above. Empty $orgpath => site-wide,
        // identical to the prior behaviour, so empty-scope callers/tests are unaffected.
        $coursefilter = '';
        $courseparams = [];
        if (!empty($orgpath)) {
            $coursefilter = "AND (c.open_path = :corgexact OR c.open_path LIKE :corgprefix)";
            $courseparams['corgexact']  = $orgpath;
            $courseparams['corgprefix'] = $DB->sql_like_escape($orgpath) . '/%';
        }
        $active_courses = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT c.id) FROM {course} c
              WHERE c.visible = 1 AND c.id > 1 $coursefilter",
            $courseparams);

        // Unique learners with at least one enrolment in the period.
        $active_learners = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid
              WHERE ue.timestart >= :apts AND ue.timestart < :apte $orgfilter",
            array_merge($orgparams, ['apts' => $current_start, 'apte' => $current_end]));

        // ── Benefits ──────────────────────────────────────────────────

        // Benefit 1: productivity gain proxy.
        $productivity_gain = $completions
            * $cfg['hours_saved_per_completion']
            * $cfg['hourly_rate'];

        // Benefit 2: compliance avoidance proxy.
        $compliance_saving = $mandatory_ontime * $cfg['penalty_avoided'];

        $total_benefit = $productivity_gain + $compliance_saving;

        // ── Costs ─────────────────────────────────────────────────────

        // Cost 1: L&D staff cost for content development.
        $ld_staff_cost = $active_courses
            * $cfg['course_dev_hours']
            * $cfg['hourly_rate'];

        // Cost 2: Platform / infrastructure (flat for the period).
        $platform_cost = (float) $cfg['platform_cost'];

        // Cost 3: Content consumption cost.
        $learner_hours  = $active_learners * $cfg['hours_per_course'];
        $content_cost   = $learner_hours   * $cfg['cost_per_learner_hour'];

        $total_cost = $ld_staff_cost + $platform_cost + $content_cost;

        // ── ROI ───────────────────────────────────────────────────────
        $net_benefit = $total_benefit - $total_cost;
        $roi_pct     = ($total_cost > 0)
            ? (int) round(($net_benefit / $total_cost) * 100)
            : 0;

        $result = [
            'roi_pct'         => $roi_pct,
            'roi_positive'    => ($roi_pct >= 0),
            'roi_negative'    => ($roi_pct < 0),
            'total_benefit'   => round($total_benefit, 2),
            'total_cost'      => round($total_cost, 2),
            'net_benefit'     => round($net_benefit, 2),
            'currency_symbol' => get_string('roi_currency_symbol', 'local_sentientia_analytics'),
            'components'      => [
                'benefits' => [
                    [
                        'name'        => get_string('roi_benefit_productivity', 'local_sentientia_analytics'),
                        'value'       => round($productivity_gain, 2),
                        'formula'     => $completions . ' completions × '
                            . $cfg['hours_saved_per_completion'] . 'h × ₹'
                            . $cfg['hourly_rate'] . '/hr',
                    ],
                    [
                        'name'        => get_string('roi_benefit_compliance', 'local_sentientia_analytics'),
                        'value'       => round($compliance_saving, 2),
                        'formula'     => $mandatory_ontime . ' on-time completions × ₹'
                            . $cfg['penalty_avoided'] . ' penalty avoided',
                    ],
                ],
                'costs' => [
                    [
                        'name'    => get_string('roi_cost_ld_staff', 'local_sentientia_analytics'),
                        'value'   => round($ld_staff_cost, 2),
                        'formula' => $active_courses . ' courses × '
                            . $cfg['course_dev_hours'] . 'h × ₹' . $cfg['hourly_rate'],
                    ],
                    [
                        'name'    => get_string('roi_cost_platform', 'local_sentientia_analytics'),
                        'value'   => round($platform_cost, 2),
                        'formula' => get_string('roi_cost_platform_flat', 'local_sentientia_analytics'),
                    ],
                    [
                        'name'    => get_string('roi_cost_content', 'local_sentientia_analytics'),
                        'value'   => round($content_cost, 2),
                        'formula' => $active_learners . ' learners × '
                            . $cfg['hours_per_course'] . 'h/course × ₹'
                            . $cfg['cost_per_learner_hour'] . '/hr',
                    ],
                ],
            ],
            'assumptions' => [
                [
                    'assumption_key'   => 'hours_saved_per_completion',
                    'assumption_label' => get_string('roi_assm_hours_saved', 'local_sentientia_analytics'),
                    'assumption_value' => $cfg['hours_saved_per_completion'] . 'h',
                ],
                [
                    'assumption_key'   => 'hourly_rate',
                    'assumption_label' => get_string('roi_assm_hourly_rate', 'local_sentientia_analytics'),
                    'assumption_value' => '₹' . $cfg['hourly_rate'],
                ],
                [
                    'assumption_key'   => 'penalty_avoided',
                    'assumption_label' => get_string('roi_assm_penalty', 'local_sentientia_analytics'),
                    'assumption_value' => '₹' . $cfg['penalty_avoided'],
                ],
                [
                    'assumption_key'   => 'platform_cost',
                    'assumption_label' => get_string('roi_assm_platform_cost', 'local_sentientia_analytics'),
                    'assumption_value' => '₹' . $cfg['platform_cost'],
                ],
                [
                    'assumption_key'   => 'hours_per_course',
                    'assumption_label' => get_string('roi_assm_hours_per_course', 'local_sentientia_analytics'),
                    'assumption_value' => $cfg['hours_per_course'] . 'h',
                ],
            ],
            'raw_metrics' => [
                'completions'       => $completions,
                'mandatory_ontime'  => $mandatory_ontime,
                'active_courses'    => $active_courses,
                'active_learners'   => $active_learners,
            ],
            'summary_sentence' => self::build_summary($roi_pct, $net_benefit, $range),
        ];

        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Invalidate ROI cache (called by scheduled task).
     */
    public static function invalidate_caches(): void {
        \cache_helper::purge_by_definition('local_sentientia_analytics', 'roi');
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * Load configurable assumption values from plugin settings,
     * falling back to class constants when not configured.
     */
    private static function load_assumptions(): array {
        $config = get_config('local_sentientia_analytics');
        return [
            'hours_saved_per_completion' =>
                (float) ($config->roi_hours_saved_per_completion ?? self::DEFAULT_HOURS_SAVED_PER_COMPLETION),
            'hourly_rate' =>
                (float) ($config->roi_hourly_rate ?? self::DEFAULT_HOURLY_RATE),
            'penalty_avoided' =>
                (float) ($config->roi_penalty_avoided ?? self::DEFAULT_PENALTY_AVOIDED),
            'course_dev_hours' =>
                (float) ($config->roi_course_dev_hours ?? self::DEFAULT_COURSE_DEV_HOURS),
            'platform_cost' =>
                (float) ($config->roi_platform_cost ?? self::DEFAULT_PLATFORM_COST),
            'cost_per_learner_hour' =>
                (float) ($config->roi_cost_per_learner_hour ?? self::DEFAULT_COST_PER_LEARNER_HOUR),
            'hours_per_course' =>
                (float) ($config->roi_hours_per_course ?? self::DEFAULT_HOURS_PER_COURSE),
        ];
    }

    /**
     * Get start/end timestamps for a named range.
     * Returns [current_start, current_end].
     */
    private static function range_dates(string $range): array {
        $now = time();
        switch ($range) {
            case '7d':
                return [$now - (7 * 86400), $now];
            case '90d':
                return [$now - (90 * 86400), $now];
            case 'ytd':
                return [strtotime(date('Y') . '-01-01'), $now];
            default: // 30d
                return [$now - (30 * 86400), $now];
        }
    }

    /**
     * Build a plain-language ROI summary sentence.
     */
    private static function build_summary(int $roi_pct, float $net_benefit, string $range): string {
        $range_label = match ($range) {
            '7d'  => 'the last 7 days',
            '90d' => 'the last 90 days',
            'ytd' => 'year to date',
            default => 'the last 30 days',
        };
        if ($roi_pct >= 0) {
            return "For {$range_label}, every ₹1 spent on training returned ₹"
                . number_format(1 + ($roi_pct / 100), 2)
                . " — a {$roi_pct}% ROI (net benefit: ₹"
                . number_format($net_benefit, 0, '.', ',') . ').';
        }
        return "For {$range_label}, training costs exceeded estimated benefits by ₹"
            . number_format(abs($net_benefit), 0, '.', ',')
            . " ({$roi_pct}% ROI). Review course completion rates to improve returns.";
    }
}
