<?php
/**
 * Udemy Business content provider adapter.
 *
 * Udemy Business API v2 — organisation course access.
 * Auth: Bearer token using account_id + API key.
 * Docs: https://business.udemy.com/api-2.0/
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market\adapter;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_content_market\catalog_item;

class udemy_business_provider implements provider_interface {

    private const BASE_URL = 'https://business.udemy.com/api-2.0';
    private const TIMEOUT  = 30;

    private bool $has_more = false;

    public function get_provider_key(): string {
        return 'udemy_business';
    }

    public function get_display_name(): string {
        return 'Udemy Business';
    }

    public function is_configured(): bool {
        $account_id = get_config('local_sentientia_content_market', 'udemy_account_id');
        $api_key    = get_config('local_sentientia_content_market', 'udemy_api_key');
        if (empty($account_id) || empty($api_key)) {
            return false;
        }
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return false;
        }
        return \local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.enabled')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.udemy_business.enabled');
    }

    /**
     * Fetch a page of Udemy Business catalog items.
     *
     * Uses cursor-based pagination via the next URL in the response.
     * page_size maps to page_size param. Returns [] on any error.
     */
    public function fetch_courses(int $page = 1, int $page_size = 100): array {
        $account_id = get_config('local_sentientia_content_market', 'udemy_account_id');
        $api_key    = get_config('local_sentientia_content_market', 'udemy_api_key');
        if (empty($account_id) || empty($api_key)) {
            $this->has_more = false;
            return [];
        }

        // Udemy uses page + page_size parameters.
        $url = self::BASE_URL . '/organizations/' . urlencode($account_id)
            . '/courses/list/?' . http_build_query([
                'page'       => $page,
                'page_size'  => $page_size,
                'fields[course]' => 'id,title,description,image_480x270,url,estimated_content_length,locale,difficulty_level,primary_category,price',
            ]);

        $curl = new \curl();
        // Basic auth: account_id:api_key per Udemy Business docs.
        $curl->setHeader([
            'Authorization: Basic ' . base64_encode("{$account_id}:{$api_key}"),
            'Accept: application/json',
        ]);
        $curl->setopt('CURLOPT_TIMEOUT', self::TIMEOUT);

        $response = $curl->get($url);
        $info = $curl->get_info();

        if ($curl->get_errno() || ($info['http_code'] ?? 0) !== 200) {
            debugging("udemy_business_provider: HTTP {$info['http_code']} on page {$page}", DEBUG_DEVELOPER);
            $this->has_more = false;
            return [];
        }

        $data = @json_decode($response, true);
        if (!is_array($data)) {
            $this->has_more = false;
            return [];
        }

        $this->has_more = !empty($data['next']);
        $results = $data['results'] ?? [];

        return array_values(array_filter(array_map(
            fn($raw) => $this->normalise($raw),
            $results
        )));
    }

    public function has_more_pages(): bool {
        return $this->has_more;
    }

    private function normalise(array $raw): ?catalog_item {
        $ext_id = (string) ($raw['id'] ?? '');
        $title  = trim($raw['title'] ?? '');
        if ($ext_id === '' || $title === '') {
            return null;
        }

        // Duration: Udemy reports estimated_content_length in minutes already.
        $duration_mins = null;
        if (isset($raw['estimated_content_length']) && is_numeric($raw['estimated_content_length'])) {
            $duration_mins = (int) $raw['estimated_content_length'];
        }

        // Level mapping.
        $level_map = [
            'beginner'     => 'beginner',
            'intermediate' => 'intermediate',
            'expert'       => 'advanced',
            'all'          => null,
        ];
        $raw_level = strtolower($raw['difficulty_level'] ?? '');
        $level = $level_map[$raw_level] ?? null;

        // Price: Udemy returns a price object or 0 for free.
        $price_usd = null;
        if (isset($raw['price']) && $raw['price'] !== 0 && $raw['price'] !== '0') {
            $price_usd = (float) preg_replace('/[^0-9.]/', '', (string) $raw['price']);
            if ($price_usd <= 0) {
                $price_usd = null;
            }
        }

        // Skills: Udemy typically includes primary_category and topics.
        $skill_names = [];
        if (!empty($raw['primary_category']['title'])) {
            $skill_names[] = $raw['primary_category']['title'];
        }
        foreach (($raw['primary_subcategory']['title'] ?? []) as $sub) {
            if (is_string($sub) && $sub !== '') {
                $skill_names[] = $sub;
            }
        }

        $lang = '';
        if (isset($raw['locale']['locale'])) {
            $lang = strtolower(substr($raw['locale']['locale'], 0, 2));
        }

        $item = catalog_item::from_array([
            'provider'      => 'udemy_business',
            'external_id'   => $ext_id,
            'title'         => $title,
            'description'   => $raw['description'] ?? null,
            'thumbnail_url' => $raw['image_480x270'] ?? null,
            'provider_url'  => !empty($raw['url']) ? 'https://www.udemy.com' . $raw['url'] : null,
            'duration_mins' => $duration_mins,
            'language'      => $lang ?: 'en',
            'level'         => $level,
            'content_type'  => 'course',
            'price_usd'     => $price_usd,
            'skill_names'   => $skill_names,
            'raw_payload'   => $raw,
        ]);

        return $item->is_valid() ? $item : null;
    }
}
