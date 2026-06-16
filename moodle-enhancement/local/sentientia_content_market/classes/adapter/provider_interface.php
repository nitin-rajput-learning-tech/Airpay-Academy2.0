<?php
/**
 * Provider interface — every third-party content adapter must implement this.
 *
 * The adapter pattern mirrors the KeKa HRMS client in
 * local_sentientia_integrations\keka_client. Each concrete adapter:
 *   1. Authenticates with its provider using credentials from get_config().
 *   2. Fetches paginated course listings from the provider API.
 *   3. Normalises each raw item into a \local_sentientia_content_market\catalog_item DTO.
 *   4. Never throws for authentication failures — returns [] and logs.
 *
 * IMPORTANT: No concrete adapter may make a real HTTP call inside a PHPUnit
 * test. Use mock_provider for tests. Real providers read credentials from
 * get_config() — never from code or arguments.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market\adapter;

defined('MOODLE_INTERNAL') || die();

interface provider_interface {

    /**
     * Unique machine-readable provider key (matches the feature flag suffix).
     * Examples: 'go1', 'udemy_business', 'coursera', 'skillsoft', 'mock'.
     */
    public function get_provider_key(): string;

    /**
     * Human-readable display name shown in the admin UI and sync log.
     */
    public function get_display_name(): string;

    /**
     * Check whether the provider is configured (credentials present + feature
     * flag ON). Called before fetch_courses() to short-circuit disabled providers.
     *
     * @return bool true if the provider is ready to sync
     */
    public function is_configured(): bool;

    /**
     * Fetch a page of normalised catalog items from the provider.
     *
     * Implementations MUST:
     *   - Set a request timeout of at most 30 seconds per HTTP call.
     *   - Return an empty array (never throw) when the provider is unreachable.
     *   - Normalise all fields through \local_sentientia_content_market\catalog_item.
     *   - Validate and sanitise the provider response before returning.
     *
     * @param int $page      1-based page number
     * @param int $page_size Items per page (default 100)
     * @return \local_sentientia_content_market\catalog_item[] Normalised items (may be empty)
     */
    public function fetch_courses(int $page = 1, int $page_size = 100): array;

    /**
     * Return true when the previous fetch_courses() response indicated that
     * more pages are available. Called after each page to drive the sync loop.
     */
    public function has_more_pages(): bool;
}
