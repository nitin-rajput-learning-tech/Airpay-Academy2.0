<?php
/**
 * Scheduled task: sync all enabled content providers.
 *
 * Runs nightly at 02:00 server time (configured in db/tasks.php).
 * Checks the master feature flag — skips entirely when OFF.
 * Iterates all configured tenants and syncs each provider.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market\task;

defined('MOODLE_INTERNAL') || die();

class sync_providers extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_sync_providers', 'local_sentientia_content_market');
    }

    public function execute(): void {
        // Master feature flag check — skip entirely when OFF.
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            mtrace('local_sentientia_content_market: sentientia_platform not found — aborting.');
            return;
        }

        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.enabled')) {
            mtrace('local_sentientia_content_market: master flag OFF — sync skipped.');
            return;
        }

        $aggregator = new \local_sentientia_content_market\market_aggregator();

        // Determine the tenants to sync for.
        // Global sync (costcenterid=0) covers items accessible across all tenants.
        // Per-tenant syncs allow per-tenant provider credentials in the future.
        $tenant_ids = $this->get_sync_tenants();

        foreach ($tenant_ids as $costcenterid) {
            mtrace("local_sentientia_content_market: syncing tenant {$costcenterid}...");
            $results = $aggregator->sync_all($costcenterid);

            foreach ($results as $provider_key => $stats) {
                mtrace(sprintf(
                    "  [%s] status=%s fetched=%d created=%d updated=%d retired=%d%s",
                    $provider_key,
                    $stats['status'],
                    $stats['items_fetched'],
                    $stats['items_created'],
                    $stats['items_updated'],
                    $stats['items_retired'],
                    $stats['error'] ? " error={$stats['error']}" : ''
                ));
            }
        }
    }

    /**
     * Return the tenant IDs to iterate during a scheduled sync.
     *
     * Today: returns [0] (global pool). Future: reads from sentientia_platform
     * tenant registry to iterate each production tenant.
     *
     * @return int[]
     */
    private function get_sync_tenants(): array {
        // If sentientia_platform tenant class is available, use the known tenants.
        if (class_exists('\local_sentientia_platform\tenant')) {
            return \local_sentientia_platform\tenant::VALID_TENANTS;
        }
        return [0];  // Global fallback.
    }
}
