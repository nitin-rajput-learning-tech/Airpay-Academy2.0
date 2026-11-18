<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia\local;

defined('MOODLE_INTERNAL') || die();

/**
 * The admin dashboard's "Compliance Overview" tiles, taken from the compliance report.
 *
 * On the 2026-11-18 surface walk the dashboard said 72% while the compliance report said 71% for the
 * same viewer (checklist B5/B8). The dashboard counted the four figures itself, with its own rules:
 *
 *   - it scoped snapshot rows by the user's open_path, where the report scopes them by the snapshot's
 *     department_path;
 *   - it counted every row of local_compliance_courses as a mandatory course, where the report counts
 *     the active ones for the tenant (global courses plus the tenant's own);
 *   - the report printed (int) of a one-decimal rate (71.9 became 71).
 *
 * Now the figures come from the report's own engine (compliance_engine::get_summary_kpis() and
 * get_active_courses_for_scope()), the rate is rounded once from completed / total exactly as the
 * report's index.php does, and the KPI row is read from, and written to, the report's own KPI cache
 * under the key the report uses for this viewer. The report serves that cached row until caches are
 * purged; reading it here is what keeps the two pages equal after the snapshot task has run.
 *
 * Tenant boundary: an L&D admin whose tenant cannot be resolved gets zeros (the engine reads an empty
 * path as the whole site), the way every other tile on the dashboard fails closed (tenant_scope).
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dashboard_compliance {

    /** The compliance report's engine. */
    private const ENGINE = '\\local_sentientia_compliance_report\\compliance_engine';

    /** The compliance report's viewer scope (it keys the report's KPI cache). */
    private const VIEWER_SCOPE = '\\local_sentientia_compliance_report\\viewer_scope';

    /**
     * The report's KPI cache: an ad-hoc application cache that
     * local/sentientia_compliance_report/index.php reads and writes. Keep these two values in step
     * with that file.
     */
    private const REPORT_CACHE_COMPONENT = 'local_sentientia_compliance_report';
    /** @see self::REPORT_CACHE_COMPONENT */
    private const REPORT_CACHE_AREA = 'dashboard';

    /**
     * Whether the compliance report plugin and its tables are installed.
     *
     * Without them the dashboard shows no Compliance Overview, as before.
     *
     * @return bool
     */
    public static function available(): bool {
        global $DB;
        if (!class_exists(self::ENGINE)) {
            return false;
        }
        $dbman = $DB->get_manager();
        return $dbman->table_exists('local_compliance_courses') && $dbman->table_exists('local_compliance_snapshot');
    }

    /**
     * The four tile figures for a viewer, as the compliance report shows them.
     *
     * Call only when available() is true.
     *
     * @param \stdClass $viewer the viewing user (id, open_path)
     * @param tenant_scope $scope the dashboard's tenant scope for that viewer
     * @return array{mandatory: int, overdue: int, total: int, rate: int} rate is a whole percentage
     */
    public static function summary(\stdClass $viewer, tenant_scope $scope): array {
        if ($scope->is_unresolved()) {
            // Fail closed: '' would be read as the whole site.
            return ['mandatory' => 0, 'overdue' => 0, 'total' => 0, 'rate' => 0];
        }
        // '' for a site admin (every tenant), '/N' for an L&D admin: the report's own scope values.
        $orgpath = $scope->root();

        $engine = self::ENGINE;
        $kpis = self::report_kpis($viewer, $orgpath);
        $total = (int) ($kpis['total'] ?? 0);

        return [
            'mandatory' => count($engine::get_active_courses_for_scope($orgpath)),
            'overdue'   => (int) ($kpis['overdue'] ?? 0),
            'total'     => $total,
            'rate'      => self::rate((int) ($kpis['completed'] ?? 0), $total),
        ];
    }

    /**
     * Completed over assigned as a whole percentage, rounded once from the counts.
     *
     * The same arithmetic as local/sentientia_compliance_report/index.php. Rounding the engine's
     * one-decimal compliance_rate again would read 71.45 as 71.5, then 72.
     *
     * @param int $completed
     * @param int $total
     * @return int
     */
    public static function rate(int $completed, int $total): int {
        return $total > 0 ? (int) round($completed / $total * 100) : 0;
    }

    /**
     * The report's KPI row for this viewer and scope.
     *
     * Read through the report's cache when the report would show this viewer the same people
     * (site or tenant level, same path). Otherwise, for instance a viewer the report scopes to their
     * own team, computed for the dashboard's scope and not cached.
     *
     * @param \stdClass $viewer
     * @param string $orgpath '' or '/N'
     * @return array the row compliance_engine::get_summary_kpis() returns
     */
    private static function report_kpis(\stdClass $viewer, string $orgpath): array {
        $cache = null;
        $cachekey = '';
        $viewerscopeclass = self::VIEWER_SCOPE;
        if (class_exists($viewerscopeclass)) {
            $reportscope = $viewerscopeclass::for_user($viewer);
            if ($reportscope !== null && $reportscope->userids === null && $reportscope->orgpath === $orgpath) {
                $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION,
                    self::REPORT_CACHE_COMPONENT, self::REPORT_CACHE_AREA);
                $cachekey = $reportscope->kpi_cache_key($orgpath);
                $cached = $cache->get($cachekey);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }

        $engine = self::ENGINE;
        $kpis = $engine::get_summary_kpis($orgpath);
        if ($cache !== null) {
            $cache->set($cachekey, $kpis);
        }
        return $kpis;
    }
}
