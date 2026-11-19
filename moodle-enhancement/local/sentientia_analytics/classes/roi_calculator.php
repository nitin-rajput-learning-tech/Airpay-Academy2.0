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
 *      Completions in the period × hours_saved_per_completion × hourly_rate
 *
 *   2. Compliance avoidance proxy
 *      Completions in the period on or before the course end date
 *      × penalty_avoided
 *
 * Costs are derived from:
 *   - L&D staff cost: visible courses in scope × course_dev_hours × hourly_rate
 *                     (every visible course, whatever the period)
 *   - Platform cost:  platform_cost, a flat figure counted in full for every period
 *   - Content cost:   learners whose enrolment started in the period
 *                     × hours_per_course × cost_per_learner_hour
 *
 * Every assumption is a site setting (settings.php, config names roi_<key>)
 * with the DEFAULT_* constant below as its default, and every one is listed
 * in the card's assumptions panel, marked when it is still the default.
 *
 * compute() returns numbers only, so its cache (pre-warmed by the CLI task)
 * holds nothing language-specific; export_for_template() builds every label,
 * formula and sentence in the viewer's language.
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
     * Every assumption, in display order: key => [label string id, unit].
     * The key is also the setting name without its 'roi_' prefix.
     * Units: hours | money | money_per_hour.
     */
    public const ASSUMPTIONS = [
        'hours_saved_per_completion' => ['roi_assm_hours_saved', 'hours'],
        'hourly_rate'                => ['roi_assm_hourly_rate', 'money_per_hour'],
        'penalty_avoided'            => ['roi_assm_penalty', 'money'],
        'course_dev_hours'           => ['roi_assm_course_dev_hours', 'hours'],
        'platform_cost'              => ['roi_assm_platform_cost', 'money'],
        'cost_per_learner_hour'      => ['roi_assm_cost_per_learner_hour', 'money_per_hour'],
        'hours_per_course'           => ['roi_assm_hours_per_course', 'hours'],
    ];

    /** Ranges the summary sentence can name; anything else reads as 30d. */
    private const RANGES = ['7d', '30d', '90d', 'ytd'];

    /**
     * The default value of every assumption, keyed like ASSUMPTIONS.
     *
     * @return array<string, float>
     */
    public static function defaults(): array {
        return [
            'hours_saved_per_completion' => self::DEFAULT_HOURS_SAVED_PER_COMPLETION,
            'hourly_rate'                => self::DEFAULT_HOURLY_RATE,
            'penalty_avoided'            => self::DEFAULT_PENALTY_AVOIDED,
            'course_dev_hours'           => self::DEFAULT_COURSE_DEV_HOURS,
            'platform_cost'              => self::DEFAULT_PLATFORM_COST,
            'cost_per_learner_hour'      => self::DEFAULT_COST_PER_LEARNER_HOUR,
            'hours_per_course'           => self::DEFAULT_HOURS_PER_COURSE,
        ];
    }

    /**
     * Compute Training ROI for the given org scope and time range.
     *
     * Returns numbers only (see model()) plus 'range'. Render it with
     * export_for_template().
     *
     * @param string $range    Time range: 7d, 30d, 90d, ytd
     * @param string $orgpath  Tenant/org path filter (e.g. '/1'); '' = whole site
     * @param bool   $refresh  Force cache bypass
     * @return array
     */
    public static function compute(string $range = '30d', string $orgpath = '',
                                    bool $refresh = false): array {
        // Load configurable assumptions (fall back to constants).
        $cfg = self::load_assumptions();

        // v2 (2026-11-19): numbers only. The assumptions are part of the key so
        // a changed setting shows at once instead of after the hour-long TTL (db/caches.php).
        $cache    = \cache::make('local_sentientia_analytics', 'roi');
        $cachekey = 'roi_v2_' . md5($range . '|' . ($orgpath ?: 'all') . '|' . json_encode($cfg));

        if (!$refresh) {
            $cached = $cache->get($cachekey);
            if ($cached !== false) {
                return $cached;
            }
        }

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

        $result = self::model([
            'completions'      => $completions,
            'mandatory_ontime' => $mandatory_ontime,
            'active_courses'   => $active_courses,
            'active_learners'  => $active_learners,
        ], $cfg);
        $result['range'] = in_array($range, self::RANGES, true) ? $range : '30d';

        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * The ROI arithmetic, with no database and no language strings.
     *
     * @param array $metrics completions, mandatory_ontime, active_courses, active_learners
     * @param array $cfg     Assumptions keyed like ASSUMPTIONS (missing keys use defaults)
     * @return array roi_pct (int, or null when there is no cost to divide by),
     *               total_benefit, total_cost, net_benefit, lines[], raw_metrics[], cfg[]
     */
    public static function model(array $metrics, array $cfg): array {
        $cfg = $cfg + self::defaults();
        $m = [
            'completions'      => (int) ($metrics['completions'] ?? 0),
            'mandatory_ontime' => (int) ($metrics['mandatory_ontime'] ?? 0),
            'active_courses'   => (int) ($metrics['active_courses'] ?? 0),
            'active_learners'  => (int) ($metrics['active_learners'] ?? 0),
        ];

        // Benefits.
        $productivity = $m['completions'] * $cfg['hours_saved_per_completion'] * $cfg['hourly_rate'];
        $compliance   = $m['mandatory_ontime'] * $cfg['penalty_avoided'];

        // Costs.
        $ldstaff  = $m['active_courses'] * $cfg['course_dev_hours'] * $cfg['hourly_rate'];
        $platform = (float) $cfg['platform_cost'];
        $content  = $m['active_learners'] * $cfg['hours_per_course'] * $cfg['cost_per_learner_hour'];

        $totalbenefit = $productivity + $compliance;
        $totalcost    = $ldstaff + $platform + $content;
        $net          = $totalbenefit - $totalcost;

        return [
            'roi_pct'       => ($totalcost > 0) ? (int) round(($net / $totalcost) * 100) : null,
            'total_benefit' => round($totalbenefit, 2),
            'total_cost'    => round($totalcost, 2),
            'net_benefit'   => round($net, 2),
            'lines'         => [
                'productivity' => round($productivity, 2),
                'compliance'   => round($compliance, 2),
                'ld_staff'     => round($ldstaff, 2),
                'platform'     => round($platform, 2),
                'content'      => round($content, 2),
            ],
            'raw_metrics'   => $m,
            'cfg'           => $cfg,
        ];
    }

    /**
     * Template context for roi_dashboard.mustache, in the viewer's language.
     *
     * Until 2026-11-19 index.php flattened compute() into roi_roi_pct,
     * roi_total_benefit, ... while the template read roi_pct, total_benefit,
     * components and assumptions: none of them existed, so the card printed a
     * lone "%" and two headings with no figures.
     *
     * @param array $roi          compute() or model() output
     * @param bool  $canconfigure Viewer may change the assumptions (moodle/site:config)
     * @return array
     */
    public static function export_for_template(array $roi, bool $canconfigure = false): array {
        $component = 'local_sentientia_analytics';
        $defaults  = self::defaults();
        $cfg       = ($roi['cfg'] ?? []) + $defaults;
        $metrics   = ($roi['raw_metrics'] ?? []) + [
            'completions' => 0, 'mandatory_ontime' => 0, 'active_courses' => 0, 'active_learners' => 0,
        ];
        $lines = ($roi['lines'] ?? []) + [
            'productivity' => 0.0, 'compliance' => 0.0, 'ld_staff' => 0.0, 'platform' => 0.0, 'content' => 0.0,
        ];
        $range  = in_array($roi['range'] ?? '', self::RANGES, true) ? $roi['range'] : '30d';
        $period = get_string('roi_period_' . $range, $component);

        $settingsurl = $canconfigure
            ? (new \moodle_url('/admin/settings.php', ['section' => 'local_sentientia_analytics']))->out(false)
            : '';

        $assumptions  = [];
        $usesdefaults = true;
        foreach (self::ASSUMPTIONS as $key => [$labelid, $unit]) {
            $isdefault    = abs((float) $cfg[$key] - $defaults[$key]) < 0.00001;
            $usesdefaults = $usesdefaults && $isdefault;
            $assumptions[] = [
                'assumption_key'   => $key,
                'assumption_label' => get_string($labelid, $component),
                'assumption_value' => self::format_assumption($unit, (float) $cfg[$key]),
                'is_default'       => $isdefault,
            ];
        }

        $ctx = [
            'roi_description' => get_string('roi_description', $component),
            'can_configure'   => ($settingsurl !== ''),
            'settings_url'    => $settingsurl,
            'uses_defaults'   => $usesdefaults,
            'assumptions'     => $assumptions,
            'roi_empty'       => false,
            'empty_state'     => null,
        ];

        // Honest empty states: no cost means no ROI at all (division by zero);
        // no completion means no benefit, so the figure would be a meaningless -100%.
        $reason = null;
        if (($roi['roi_pct'] ?? null) === null || (float) ($roi['total_cost'] ?? 0) <= 0) {
            $reason = 'nocost';
        } else if ((int) $metrics['completions'] === 0) {
            $reason = 'nocompletions';
        }
        if ($reason !== null) {
            $ctx['roi_empty']   = true;
            $ctx['empty_state'] = [
                'icon'     => 'line-chart',
                'size'     => 'sm',
                'title'    => get_string('roi_empty_title_' . $reason, $component),
                'message'  => get_string('roi_empty_' . $reason, $component, $period),
                'ctalabel' => ($settingsurl !== '') ? get_string('roi_settings_link', $component) : '',
                'ctaurl'   => $settingsurl,
                'ctaicon'  => 'sliders',
            ];
            return $ctx;
        }

        $pct = (int) $roi['roi_pct'];
        $net = (float) ($roi['net_benefit'] ?? 0);
        $hoursper = fn(float $h) => self::format_assumption('hours', $h);
        $rateper  = fn(float $r) => self::format_assumption('money_per_hour', $r);

        $ctx += [
            'roi_pct_display' => number_format($pct) . '%',
            'roi_positive'    => ($pct >= 0),
            'roi_negative'    => ($pct < 0),
            'total_benefit'   => self::format_money((float) $roi['total_benefit'], 0),
            'total_cost'      => self::format_money((float) $roi['total_cost'], 0),
            'net_benefit'     => self::format_money($net, 0),
            'summary'         => get_string($pct >= 0 ? 'roi_summary_positive' : 'roi_summary_negative',
                $component, (object) [
                    'period' => $period,
                    'unit'   => self::format_money(1, 0),
                    'ret'    => self::format_money(max(0, 1 + $pct / 100), 2),
                    'pct'    => number_format($pct),
                    'net'    => self::format_money(abs($net), 0),
                ]),
            'benefits'        => [
                [
                    'name'    => get_string('roi_benefit_productivity', $component),
                    'value'   => self::format_money($lines['productivity'], 0),
                    'formula' => get_string('roi_formula_productivity', $component, (object) [
                        'completions' => number_format($metrics['completions']),
                        'hours'       => $hoursper((float) $cfg['hours_saved_per_completion']),
                        'rate'        => $rateper((float) $cfg['hourly_rate']),
                    ]),
                ],
                [
                    'name'    => get_string('roi_benefit_compliance', $component),
                    'value'   => self::format_money($lines['compliance'], 0),
                    'formula' => get_string('roi_formula_compliance', $component, (object) [
                        'count'   => number_format($metrics['mandatory_ontime']),
                        'penalty' => self::format_money((float) $cfg['penalty_avoided']),
                    ]),
                ],
            ],
            'costs'           => [
                [
                    'name'    => get_string('roi_cost_ld_staff', $component),
                    'value'   => self::format_money($lines['ld_staff'], 0),
                    'formula' => get_string('roi_formula_ld_staff', $component, (object) [
                        'courses' => number_format($metrics['active_courses']),
                        'hours'   => $hoursper((float) $cfg['course_dev_hours']),
                        'rate'    => $rateper((float) $cfg['hourly_rate']),
                    ]),
                ],
                [
                    'name'    => get_string('roi_cost_platform', $component),
                    'value'   => self::format_money($lines['platform'], 0),
                    'formula' => get_string('roi_formula_platform', $component),
                ],
                [
                    'name'    => get_string('roi_cost_content', $component),
                    'value'   => self::format_money($lines['content'], 0),
                    'formula' => get_string('roi_formula_content', $component, (object) [
                        'learners' => number_format($metrics['active_learners']),
                        'hours'    => $hoursper((float) $cfg['hours_per_course']),
                        'rate'     => $rateper((float) $cfg['cost_per_learner_hour']),
                    ]),
                ],
            ],
        ];
        return $ctx;
    }

    /**
     * An amount with the currency symbol and comma thousands separators in
     * threes, sign first: ₹123,456, -₹500, ₹2.50.
     *
     * @param float    $amount
     * @param int|null $decimals Null = 0 for whole amounts, else 2
     * @return string
     */
    public static function format_money(float $amount, ?int $decimals = null): string {
        if ($decimals === null) {
            $decimals = (abs($amount - round($amount)) < 0.005) ? 0 : 2;
        }
        $symbol  = get_string('roi_currency_symbol', 'local_sentientia_analytics');
        $rounded = round(abs($amount), $decimals);
        $sign    = ($amount < 0 && $rounded > 0) ? '-' : '';
        return $sign . $symbol . number_format($rounded, $decimals);
    }

    /**
     * Invalidate ROI cache (called by scheduled task).
     */
    public static function invalidate_caches(): void {
        \cache_helper::purge_by_definition('local_sentientia_analytics', 'roi');
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * One assumption value with its unit, in the viewer's language.
     *
     * @param string $unit hours | money | money_per_hour
     * @param float  $value
     * @return string
     */
    private static function format_assumption(string $unit, float $value): string {
        $component = 'local_sentientia_analytics';
        switch ($unit) {
            case 'hours':
                $qty = rtrim(rtrim(number_format($value, 2), '0'), '.');
                return get_string('roi_unit_hours', $component, $qty);
            case 'money_per_hour':
                return get_string('roi_unit_per_hour', $component, self::format_money($value));
            default:
                return self::format_money($value);
        }
    }

    /**
     * Load configurable assumption values from plugin settings, falling back
     * to the defaults when a value is unset, empty, not a number or negative.
     */
    private static function load_assumptions(): array {
        $config = get_config('local_sentientia_analytics');
        $out = [];
        foreach (self::defaults() as $key => $default) {
            $raw = $config->{'roi_' . $key} ?? null;
            $out[$key] = (is_numeric($raw) && (float) $raw >= 0) ? (float) $raw : $default;
        }
        return $out;
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
}
