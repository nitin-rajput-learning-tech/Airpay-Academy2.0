<?php
/**
 * Predictive Analytics Engine — at-risk completion forecasting and
 * team skill-gap projection using transparent, explainable heuristics.
 *
 * Model transparency
 * ------------------
 * This class uses NO black-box ML or external services. Every score is
 * built from weighted, named signals that are individually retrievable
 * via get_at_risk_users() → signals[]. The signals and weights are
 * documented inline so any L&D analyst can audit and adjust them.
 *
 * At-risk scoring (0–100, higher = higher risk):
 *   - days_since_last_access   weight 0.30  (recency of engagement)
 *   - completion_rate_gap      weight 0.25  (enrolled minus completed, normalised)
 *   - overdue_courses          weight 0.25  (courses past end-date, not completed)
 *   - login_frequency_drop     weight 0.20  (rolling-window velocity drop)
 *
 * Skill-gap projection (0–100% gap per skill):
 *   - covered = skills tagged on completed courses / total tagged skills
 *     needed by the user's designation; gap = 1 - covered
 *   - Uses local_sentientia_skillsai\skill_gap_provider when present
 *     (class_exists-guarded); degrades to course-category proxy when absent.
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_analytics;

defined('MOODLE_INTERNAL') || die();

class predictive_engine {

    // ── Signal weights (must sum to 1.0) ─────────────────────────────
    private const W_RECENCY    = 0.30;
    private const W_COMP_GAP   = 0.25;
    private const W_OVERDUE    = 0.25;
    private const W_VELOCITY   = 0.20;

    // Days of inactivity that saturates the recency signal (= max risk).
    private const RECENCY_SATURATE_DAYS = 60;

    // Rolling windows for velocity comparison (shorter / longer).
    private const VELOCITY_WINDOW_SHORT  = 14; // days
    private const VELOCITY_WINDOW_LONG   = 42; // days

    // Risk thresholds for banding.
    public const BAND_HIGH   = 70;
    public const BAND_MEDIUM = 40;

    /**
     * Get at-risk users for a tenant, sorted by risk score descending.
     *
     * Each row: {userid, firstname, lastname, email, open_path,
     *            risk_score, band, band_high, band_med, band_low, signals[]}
     *
     * @param string $orgpath  Tenant/org path filter (e.g. '/1')
     * @param int    $limit    Max rows (default 50)
     * @param bool   $refresh  Force cache bypass (used by scheduled task)
     * @return array
     */
    public static function get_at_risk_users(string $orgpath = '', int $limit = 50,
                                              bool $refresh = false): array {
        $cache    = \cache::make('local_sentientia_analytics', 'predictive_atrisk');
        $cachekey = 'atrisk_' . md5($orgpath . '|' . $limit);

        if (!$refresh) {
            $cached = $cache->get($cachekey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $signals = self::compute_signals_bulk($orgpath);
        $result  = [];
        foreach ($signals as $uid => $s) {
            $score    = self::compute_score($s);
            $band     = self::band($score);
            $result[] = [
                'userid'     => (int) $uid,
                'firstname'  => format_string($s['firstname']),
                'lastname'   => format_string($s['lastname']),
                'email'      => $s['email'],
                'open_path'  => $s['open_path'],
                'risk_score' => $score,
                'band'       => $band,
                'band_high'  => ($band === 'high'),
                'band_med'   => ($band === 'medium'),
                'band_low'   => ($band === 'low'),
                'signals'    => self::signals_for_display($s),
            ];
        }

        // Sort by risk descending.
        usort($result, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
        $result = array_slice($result, 0, $limit);

        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Get team skill-gap projection — percentage of required skills
     * not yet covered per team / department under the given org path.
     *
     * If local_sentientia_skillsai\skill_gap_provider exists its feed is
     * consumed (class_exists-guarded). Falls back to course-category
     * coverage proxy when absent.
     *
     * Each row: {team_name, team_path, required_skills, covered_skills,
     *            gap_pct, gap_band, gap_high, gap_med, gap_low, skill_gaps[]}
     *
     * @param string $orgpath   Tenant root path (e.g. '/1')
     * @param bool   $refresh   Force cache bypass
     * @return array
     */
    public static function get_skill_gap_projection(string $orgpath = '',
                                                      bool $refresh = false): array {
        $cache    = \cache::make('local_sentientia_analytics', 'predictive_skillgap');
        $cachekey = 'skillgap_' . md5($orgpath ?: 'all');

        if (!$refresh) {
            $cached = $cache->get($cachekey);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Optional: consume local_sentientia_skillsai when installed.
        if (class_exists('\local_sentientia_skillsai\skill_gap_provider')) {
            try {
                $result = \local_sentientia_skillsai\skill_gap_provider::get_team_gaps($orgpath);
                $cache->set($cachekey, $result);
                return $result;
            } catch (\Throwable $e) {
                // Degrade gracefully — fall through to built-in heuristic.
                debugging('predictive_engine: skillsai provider failed: ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }

        // Built-in heuristic: course-category coverage proxy.
        $result = self::compute_skill_gap_heuristic($orgpath);
        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Invalidate all predictive caches (called by the scheduled task and
     * when bulk data loads happen).
     */
    public static function invalidate_caches(): void {
        \cache_helper::purge_by_definition('local_sentientia_analytics', 'predictive_atrisk');
        \cache_helper::purge_by_definition('local_sentientia_analytics', 'predictive_skillgap');
    }

    // ── Public: score + band helpers (used directly by tests) ─────────

    /**
     * Compute composite risk score (0–100) from a named signal array.
     *
     * Signal keys required: lastaccess, enrolled, completed, overdue,
     *                       velocity_drop.
     *
     * @param array $s  Signal array (from compute_signals_bulk)
     * @return int      Risk score 0–100
     */
    public static function compute_score(array $s): int {
        $now = time();

        // Signal 1: recency (0–1). Days inactive, capped at RECENCY_SATURATE_DAYS.
        $days_inactive = ((int) $s['lastaccess'] > 0)
            ? min(self::RECENCY_SATURATE_DAYS, ($now - (int) $s['lastaccess']) / 86400)
            : self::RECENCY_SATURATE_DAYS;
        $recency_score = $days_inactive / self::RECENCY_SATURATE_DAYS;

        // Signal 2: completion gap (0–1). Fraction of enrolled courses not completed.
        $enrolled  = max(1, (int) $s['enrolled']);
        $gap_score = ($enrolled - min($enrolled, (int) $s['completed'])) / $enrolled;

        // Signal 3: overdue (0–1). Each overdue course adds risk; saturates at 5.
        $overdue_score = min(1.0, (int) $s['overdue'] / 5.0);

        // Signal 4: velocity drop (0–1). Already normalised ∈ [0, 1].
        $velocity_score = (float) $s['velocity_drop'];

        $raw = self::W_RECENCY  * $recency_score
             + self::W_COMP_GAP * $gap_score
             + self::W_OVERDUE  * $overdue_score
             + self::W_VELOCITY * $velocity_score;

        return (int) round($raw * 100);
    }

    /**
     * Classify a risk score into a named band.
     *
     * @param int $score  0–100
     * @return string     'high' | 'medium' | 'low'
     */
    public static function band(int $score): string {
        if ($score >= self::BAND_HIGH)   return 'high';
        if ($score >= self::BAND_MEDIUM) return 'medium';
        return 'low';
    }

    /**
     * Band a skill-gap percentage for display.
     *
     * @param int $gap_pct  0–100
     * @return string       'high' | 'medium' | 'low'
     */
    public static function gap_band(int $gap_pct): string {
        if ($gap_pct >= 60) return 'high';
        if ($gap_pct >= 30) return 'medium';
        return 'low';
    }

    // ── Private: signal computation ───────────────────────────────────

    /**
     * Bulk-compute raw signals for all active users under $orgpath.
     * Returns array keyed by userid. Uses three queries — no N+1 per user.
     *
     * Q1 — base user rows + enrolment counts + completion counts
     * Q2 — overdue course counts per user
     * Q3 — recent login-event counts in two rolling windows
     */
    private static function compute_signals_bulk(string $orgpath): array {
        global $DB;

        $orgparams = [];
        $orgfilter = '';
        if (!empty($orgpath)) {
            $orgfilter = "AND (u.open_path = :aporgexact OR u.open_path LIKE :aporgprefix)";
            $orgparams['aporgexact']  = $orgpath;
            $orgparams['aporgprefix'] = $DB->sql_like_escape($orgpath) . '/%';
        }

        $now = time();

        // Q1: base + enrolment summary per user.
        $users = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email, u.open_path,
                    u.lastaccess,
                    COUNT(DISTINCT ue.enrolid)        AS enrolled,
                    COUNT(DISTINCT cc.id)             AS completed
               FROM {user} u
          LEFT JOIN {user_enrolments} ue ON ue.userid = u.id
          LEFT JOIN {course_completions} cc ON cc.userid = u.id
                    AND cc.timecompleted IS NOT NULL
              WHERE u.deleted = 0 AND u.suspended = 0 $orgfilter
           GROUP BY u.id, u.firstname, u.lastname, u.email, u.open_path, u.lastaccess",
            $orgparams);

        if (empty($users)) {
            return [];
        }

        $uids = array_keys($users);
        [$insql, $inparams] = $DB->get_in_or_equal($uids, SQL_PARAMS_NAMED, 'uid');

        // Q2: overdue courses per user (end-date passed, not completed).
        $overdue_rows = $DB->get_records_sql(
            "SELECT ue.userid, COUNT(*) AS cnt
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
          LEFT JOIN {course_completions} cc ON cc.course = c.id
                    AND cc.userid = ue.userid AND cc.timecompleted IS NOT NULL
              WHERE ue.userid $insql
                AND c.enddate > 0 AND c.enddate < :apnow
                AND cc.id IS NULL
           GROUP BY ue.userid",
            array_merge($inparams, ['apnow' => $now]));

        // Q3: login-event counts in two rolling windows per user.
        $short_start = $now - (self::VELOCITY_WINDOW_SHORT * 86400);
        $long_start  = $now - (self::VELOCITY_WINDOW_LONG  * 86400);

        $login_rows = $DB->get_records_sql(
            "SELECT userid,
                    SUM(CASE WHEN timecreated >= :apss THEN 1 ELSE 0 END) AS short_logins,
                    SUM(CASE WHEN timecreated >= :apls THEN 1 ELSE 0 END) AS long_logins
               FROM {logstore_standard_log}
              WHERE userid $insql
                AND timecreated >= :apls2
                AND action = 'loggedin'
           GROUP BY userid",
            array_merge($inparams, [
                'apss'  => $short_start,
                'apls'  => $long_start,
                'apls2' => $long_start,
            ]));

        // Merge into signal arrays.
        $signals = [];
        foreach ($users as $uid => $u) {
            $enrolled  = (int) ($u->enrolled  ?? 0);
            $completed = (int) ($u->completed ?? 0);

            $overdue_cnt = isset($overdue_rows[$uid]) ? (int) $overdue_rows[$uid]->cnt : 0;

            $short = isset($login_rows[$uid]) ? (float) $login_rows[$uid]->short_logins : 0.0;
            $long  = isset($login_rows[$uid]) ? (float) $login_rows[$uid]->long_logins  : 0.0;

            // Velocity: logins per day in short window vs long window.
            $short_rate = $short / self::VELOCITY_WINDOW_SHORT;
            $long_rate  = $long  / self::VELOCITY_WINDOW_LONG;
            // Normalised velocity drop ∈ [0, 1]. 1 = complete dropout.
            $velocity_drop = ($long_rate > 0.001)
                ? max(0.0, min(1.0, ($long_rate - $short_rate) / $long_rate))
                : ($short_rate > 0.001 ? 0.0 : 0.5); // no history → moderate risk

            $signals[$uid] = [
                'firstname'     => $u->firstname,
                'lastname'      => $u->lastname,
                'email'         => $u->email,
                'open_path'     => $u->open_path,
                'lastaccess'    => (int) $u->lastaccess,
                'enrolled'      => $enrolled,
                'completed'     => $completed,
                'overdue'       => $overdue_cnt,
                'velocity_drop' => $velocity_drop,
            ];
        }

        return $signals;
    }

    /**
     * Build a display-friendly signals breakdown for templates.
     *
     * @param array $s  Signal array (from compute_signals_bulk)
     * @return array
     */
    private static function signals_for_display(array $s): array {
        $now          = time();
        $days_inactive = ((int) $s['lastaccess'] > 0)
            ? (int) round(($now - (int) $s['lastaccess']) / 86400)
            : 999;
        $incomplete    = max(0, (int) $s['enrolled'] - (int) $s['completed']);

        return [
            [
                'signal_name'  => 'Days inactive',
                'signal_value' => ($days_inactive >= 999) ? 'Never logged in' : $days_inactive . 'd',
                'weight_pct'   => (int) (self::W_RECENCY * 100),
            ],
            [
                'signal_name'  => 'Incomplete courses',
                'signal_value' => $incomplete . ' of ' . (int) $s['enrolled'],
                'weight_pct'   => (int) (self::W_COMP_GAP * 100),
            ],
            [
                'signal_name'  => 'Overdue courses',
                'signal_value' => (string) (int) $s['overdue'],
                'weight_pct'   => (int) (self::W_OVERDUE * 100),
            ],
            [
                'signal_name'  => 'Engagement velocity drop',
                'signal_value' => (int) round((float) $s['velocity_drop'] * 100) . '%',
                'weight_pct'   => (int) (self::W_VELOCITY * 100),
            ],
        ];
    }

    // ── Private: skill-gap heuristic ──────────────────────────────────

    /**
     * Built-in skill-gap heuristic when local_sentientia_skillsai is absent.
     *
     * Uses course categories as a proxy for skill domains. For each
     * team (depth-3 org node under $orgpath), compute:
     *   required_categories = distinct categories of enrolled courses
     *   covered_categories  = distinct categories of completed courses
     *   gap_pct             = (required - covered) / required * 100
     *
     * All aggregation is done in two queries (no N+1 per team).
     */
    private static function compute_skill_gap_heuristic(string $orgpath): array {
        global $DB;

        $parts  = explode('/', trim($orgpath ?: '/1', '/'));
        $toporg = '/' . ($parts[0] ?? '1');

        // Get teams (depth 3) under the org root.
        $teams = $DB->get_records_sql(
            "SELECT cc.id, cc.fullname, cc.path
               FROM {local_sentientia_org} cc
              WHERE cc.path LIKE :pp AND cc.depth = 3
           ORDER BY cc.fullname",
            ['pp' => $toporg . '/%']);

        if (empty($teams)) {
            return [];
        }

        // Q1: distinct category enrolments per user path.
        $enrolled_cats = $DB->get_records_sql(
            "SELECT u.open_path AS p, c.category AS catid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} u ON u.id = ue.userid
              WHERE u.deleted = 0 AND u.open_path LIKE :prefix
           GROUP BY u.open_path, c.category",
            ['prefix' => $toporg . '/%']);

        // Q2: distinct category completions per user path.
        $completed_cats = $DB->get_records_sql(
            "SELECT u.open_path AS p, c.category AS catid
               FROM {course_completions} cc
               JOIN {course} c ON c.id = cc.course
               JOIN {user} u ON u.id = cc.userid
              WHERE u.deleted = 0 AND u.open_path LIKE :prefix
                AND cc.timecompleted IS NOT NULL
           GROUP BY u.open_path, c.category",
            ['prefix' => $toporg . '/%']);

        // Roll up by team path.
        $team_data = [];
        foreach ($teams as $team) {
            $team_data[$team->path] = ['enrolled' => [], 'completed' => []];
        }

        foreach ($enrolled_cats as $row) {
            foreach ($team_data as $tpath => &$td) {
                if ($row->p === $tpath || str_starts_with($row->p, $tpath . '/')) {
                    $td['enrolled'][(int) $row->catid] = true;
                }
            }
            unset($td);
        }
        foreach ($completed_cats as $row) {
            foreach ($team_data as $tpath => &$td) {
                if ($row->p === $tpath || str_starts_with($row->p, $tpath . '/')) {
                    $td['completed'][(int) $row->catid] = true;
                }
            }
            unset($td);
        }

        $result = [];
        foreach ($teams as $team) {
            $td       = $team_data[$team->path];
            $required = count($td['enrolled']);
            if ($required === 0) {
                continue;
            }
            $covered = count(array_intersect_key($td['completed'], $td['enrolled']));
            $gap_pct = (int) round((($required - $covered) / $required) * 100);
            $gap_band = self::gap_band($gap_pct);

            // Identify uncovered category ids for the display list.
            $uncovered_ids = array_keys(array_diff_key($td['enrolled'], $td['completed']));
            $skill_gaps    = [];
            if (!empty($uncovered_ids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($uncovered_ids, SQL_PARAMS_NAMED, 'cat');
                $cats = $DB->get_records_select('course_categories', "id $insql", $inparams,
                    'name', 'id, name');
                foreach ($cats as $cat) {
                    $skill_gaps[] = ['skill_name' => format_string($cat->name)];
                }
            }

            $result[] = [
                'team_name'       => format_string($team->fullname),
                'team_path'       => $team->path,
                'required_skills' => $required,
                'covered_skills'  => $covered,
                'gap_pct'         => $gap_pct,
                'gap_band'        => $gap_band,
                'gap_high'        => ($gap_band === 'high'),
                'gap_med'         => ($gap_band === 'medium'),
                'gap_low'         => ($gap_band === 'low'),
                'skill_gaps'      => $skill_gaps,
            ];
        }

        // Sort by gap descending.
        usort($result, fn($a, $b) => $b['gap_pct'] <=> $a['gap_pct']);
        return $result;
    }
}
