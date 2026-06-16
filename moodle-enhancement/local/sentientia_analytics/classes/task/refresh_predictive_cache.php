<?php
/**
 * Scheduled task — pre-warm predictive and ROI caches for all tenant roots.
 *
 * Runs once per hour (configured in db/tasks.php). Iterates over
 * known tenant roots, invalidates stale cache entries, and rebuilds
 * them in the background so dashboard page-loads always hit warm cache.
 *
 * Performance matrix justification (database.md §Performance Decision Matrix):
 *   - At-risk aggregation spans 3,500+ users × multiple JOINs
 *     → dashboard-level, cron-refresh pattern (application cache + cron).
 *   - Skill-gap projection similarly expensive.
 *   - ROI computation is lighter but also benefits from pre-warm.
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_analytics\task;

defined('MOODLE_INTERNAL') || die();

class refresh_predictive_cache extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_refresh_predictive_cache', 'local_sentientia_analytics');
    }

    public function execute(): void {
        global $DB;

        // Get all top-level org paths (tenant roots) that have users.
        $roots = $DB->get_records_sql(
            "SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING(open_path, 2), '/', 1) AS root_id
               FROM {user}
              WHERE deleted = 0 AND suspended = 0
                AND open_path IS NOT NULL AND open_path != ''
                AND open_path REGEXP '^/[0-9]'
           ORDER BY root_id");

        $tenant_paths = ['']; // '' = all-tenants aggregate
        foreach ($roots as $row) {
            if (!empty($row->root_id) && ctype_digit($row->root_id)) {
                $tenant_paths[] = '/' . $row->root_id;
            }
        }

        // Invalidate first, then refresh for each tenant.
        \local_sentientia_analytics\predictive_engine::invalidate_caches();
        \local_sentientia_analytics\roi_calculator::invalidate_caches();

        foreach ($tenant_paths as $orgpath) {
            try {
                \local_sentientia_analytics\predictive_engine::get_at_risk_users($orgpath, 50, true);
                \local_sentientia_analytics\predictive_engine::get_skill_gap_projection($orgpath, true);
            } catch (\Throwable $e) {
                // Log but continue — one tenant failure should not block others.
                mtrace("predictive_cache: error for orgpath '{$orgpath}': " . $e->getMessage());
            }
        }

        // Refresh ROI for each tenant × each standard time range.
        foreach ($tenant_paths as $orgpath) {
            foreach (['7d', '30d', '90d', 'ytd'] as $range) {
                try {
                    \local_sentientia_analytics\roi_calculator::compute($range, $orgpath, true);
                } catch (\Throwable $e) {
                    mtrace("roi_cache: error for orgpath '{$orgpath}', range '{$range}': "
                        . $e->getMessage());
                }
            }
        }

        mtrace('refresh_predictive_cache: completed for '
            . count($tenant_paths) . ' org scope(s).');
    }
}
