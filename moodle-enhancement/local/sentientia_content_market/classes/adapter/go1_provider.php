<?php
/**
 * Go1 content provider adapter.
 *
 * Go1 REST API v3 — catalog search endpoint.
 * Auth: Bearer token via API key (per-tenant, stored in plugin config).
 * Docs: https://docs.go1.com/
 *
 * IMPORTANT: This adapter does NOT make live API calls in tests.
 * All HTTP calls use Moodle's curl wrapper with a 30-second timeout.
 * Credentials come exclusively from get_config() — never hardcoded.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market\adapter;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_content_market\catalog_item;

class go1_provider implements provider_interface {

    private const BASE_URL = 'https://api.go1.com/v3';
    private const TIMEOUT  = 30;

    /** @var bool Set by fetch_courses() based on response pagination. */
    private bool $has_more = false;

    public function get_provider_key(): string {
        return 'go1';
    }

    public function get_display_name(): string {
        return 'Go1';
    }

    public function is_configured(): bool {
        $api_key = get_config('local_sentientia_content_market', 'go1_api_key');
        if (empty($api_key)) {
            return false;
        }
        // Feature flag check — master + provider sub-flag must both be ON.
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return false;
        }
        return \local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.enabled')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.go1.enabled');
    }

    /**
     * Fetch a page of Go1 catalog items.
     *
     * Uses Go1's /catalog endpoint with offset-based pagination.
     * Returns [] and sets has_more = false on any error (never throws).
     */
    public function fetch_courses(int $page = 1, int $page_size = 100): array {
        $api_key = get_config('local_sentientia_content_market', 'go1_api_key');
        if (empty($api_key)) {
            debugging('go1_provider: api key not configured', DEBUG_DEVELOPER);
            $this->has_more = false;
            return [];
        }

        $offset = ($page - 1) * $page_size;
        $url = self::BASE_URL . '/catalog?' . http_build_query([
            'offset' => $offset,
            'limit'  => $page_size,
        ]);

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $api_key,
            'Accept: application/json',
        ]);
        $curl->setopt('CURLOPT_TIMEOUT', self::TIMEOUT);

        $response = $curl->get($url);
        $info = $curl->get_info();

        if ($curl->get_errno() || ($info['http_code'] ?? 0) !== 200) {
            debugging("go1_provider: HTTP {$info['http_code']} fetching page {$page}", DEBUG_DEVELOPER);
            $this->has_more = false;
            return [];
        }

        $data = @json_decode($response, true);
        if (!is_array($data)) {
            $this->has_more = false;
            return [];
        }

        $hits = $data['hits'] ?? $data['data'] ?? [];
        $total = $data['total'] ?? count($hits);
        $this->has_more = ($offset + $page_size) < $total;

        return array_values(array_filter(array_map(
            fn($raw) => $this->normalise($raw),
            $hits
        )));
    }

    public function has_more_pages(): bool {
        return $this->has_more;
    }

    /**
     * Normalise a raw Go1 API hit into a catalog_item.
     *
     * @param array $raw Raw Go1 course object
     * @return catalog_item|null null if required fields are missing
     */
    private function normalise(array $raw): ?catalog_item {
        $ext_id = (string) ($raw['id'] ?? '');
        $title  = trim($raw['title'] ?? '');
        if ($ext_id === '' || $title === '') {
            return null;
        }

        // Map Go1 content types to our normalised vocabulary.
        $type_map = [
            'video'       => 'video',
            'course'      => 'course',
            'learning'    => 'course',
            'playlist'    => 'course',
            'text'        => 'article',
            'interactive' => 'microlearning',
        ];
        $raw_type = strtolower($raw['type'] ?? '');
        $content_type = $type_map[$raw_type] ?? 'course';

        // Duration: Go1 reports in seconds.
        $duration_mins = null;
        if (isset($raw['duration']) && is_numeric($raw['duration'])) {
            $duration_mins = (int) round((int) $raw['duration'] / 60);
        }

        $skill_names = [];
        foreach (($raw['tags'] ?? []) as $tag) {
            $name = is_string($tag) ? $tag : ($tag['name'] ?? '');
            if ($name !== '') {
                $skill_names[] = trim($name);
            }
        }

        $item = catalog_item::from_array([
            'provider'      => 'go1',
            'external_id'   => $ext_id,
            'title'         => $title,
            'description'   => $raw['description'] ?? null,
            'thumbnail_url' => $raw['image'] ?? $raw['imageUrl'] ?? null,
            'provider_url'  => $raw['url'] ?? null,
            'duration_mins' => $duration_mins,
            'language'      => strtolower($raw['language'] ?? 'en'),
            'level'         => strtolower($raw['tags']['level'] ?? $raw['level'] ?? ''),
            'content_type'  => $content_type,
            'price_usd'     => isset($raw['pricing']['price']) ? (float) $raw['pricing']['price'] : null,
            'skill_names'   => $skill_names,
            'raw_payload'   => $raw,
        ]);

        return $item->is_valid() ? $item : null;
    }
}
