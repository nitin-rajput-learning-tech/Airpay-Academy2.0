<?php
/**
 * Skillsoft Percipio content provider adapter.
 *
 * Skillsoft Percipio API — content catalog endpoint.
 * Auth: Bearer token (API key) with subdomain-based base URL.
 * Docs: https://documentation.skillsoft.com/en_us/percipio/Content/A_Administrator/int-percipio-api.htm
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market\adapter;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_content_market\catalog_item;

class skillsoft_provider implements provider_interface {

    private const TIMEOUT = 30;

    private bool $has_more = false;
    private ?string $next_offset = null;

    public function get_provider_key(): string {
        return 'skillsoft';
    }

    public function get_display_name(): string {
        return 'Skillsoft Percipio';
    }

    public function is_configured(): bool {
        $subdomain = get_config('local_sentientia_content_market', 'skillsoft_subdomain');
        $api_key   = get_config('local_sentientia_content_market', 'skillsoft_api_key');
        if (empty($subdomain) || empty($api_key)) {
            return false;
        }
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return false;
        }
        return \local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.enabled')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.skillsoft.enabled');
    }

    public function fetch_courses(int $page = 1, int $page_size = 100): array {
        $subdomain = get_config('local_sentientia_content_market', 'skillsoft_subdomain');
        $api_key   = get_config('local_sentientia_content_market', 'skillsoft_api_key');
        $org_id    = get_config('local_sentientia_content_market', 'skillsoft_org_id');

        if (empty($subdomain) || empty($api_key)) {
            $this->has_more = false;
            return [];
        }

        $base_url = "https://{$subdomain}.percipio.com/content-discovery/v1/organizations";
        $url = $base_url . "/{$org_id}/catalog/content?" . http_build_query([
            'offset'     => ($page - 1) * $page_size,
            'max'        => $page_size,
            'typeFilter' => 'COURSE,VIDEO,BOOK',
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
            debugging("skillsoft_provider: HTTP {$info['http_code']} on page {$page}", DEBUG_DEVELOPER);
            $this->has_more = false;
            return [];
        }

        $data = @json_decode($response, true);
        if (!is_array($data)) {
            $this->has_more = false;
            return [];
        }

        // Percipio returns totalItems to determine pagination.
        $total = (int) ($data['totalItems'] ?? 0);
        $this->has_more = (($page - 1) * $page_size + $page_size) < $total;

        $content_list = $data['contentList'] ?? $data ?? [];
        if (!is_array($content_list)) {
            $this->has_more = false;
            return [];
        }

        return array_values(array_filter(array_map(
            fn($raw) => $this->normalise($raw),
            $content_list
        )));
    }

    public function has_more_pages(): bool {
        return $this->has_more;
    }

    private function normalise(array $raw): ?catalog_item {
        $ext_id = (string) ($raw['id'] ?? '');
        $title  = trim($raw['localizedMetadata'][0]['title'] ?? $raw['title'] ?? '');
        if ($ext_id === '' || $title === '') {
            return null;
        }

        $desc = $raw['localizedMetadata'][0]['description'] ?? $raw['description'] ?? null;

        // Duration: Percipio reports in ISO 8601 or seconds.
        $duration_mins = null;
        if (isset($raw['duration'])) {
            // ISO 8601 duration format: PT1H30M → 90 minutes
            if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/u', $raw['duration'], $m)) {
                $duration_mins = ((int)($m[1] ?? 0)) * 60 + (int)($m[2] ?? 0);
            } elseif (is_numeric($raw['duration'])) {
                $duration_mins = (int) round((int) $raw['duration'] / 60);
            }
        }

        // Content type mapping.
        $type_map = [
            'COURSE'    => 'course',
            'VIDEO'     => 'video',
            'BOOK'      => 'article',
            'AUDIO'     => 'podcast',
            'CHANNEL'   => 'course',
        ];
        $raw_type = strtoupper($raw['contentType']['displayLabel'] ?? $raw['type'] ?? '');
        $content_type = $type_map[$raw_type] ?? 'course';

        // Skills/topics from Percipio associations.
        $skill_names = [];
        foreach (($raw['associations']['skills'] ?? []) as $skill) {
            $name = $skill['name'] ?? '';
            if ($name !== '') {
                $skill_names[] = trim($name);
            }
        }
        foreach (($raw['associations']['subjects'] ?? []) as $subj) {
            $name = $subj['name'] ?? '';
            if ($name !== '' && !in_array($name, $skill_names, true)) {
                $skill_names[] = trim($name);
            }
        }

        $item = catalog_item::from_array([
            'provider'      => 'skillsoft',
            'external_id'   => $ext_id,
            'title'         => $title,
            'description'   => $desc,
            'thumbnail_url' => $raw['imageUrl'] ?? null,
            'provider_url'  => $raw['link'] ?? null,
            'duration_mins' => $duration_mins,
            'language'      => strtolower(substr($raw['language'] ?? 'en', 0, 2)),
            'level'         => null,  // Percipio does not expose level in catalog.
            'content_type'  => $content_type,
            'price_usd'     => null,  // Subscription-included.
            'skill_names'   => $skill_names,
            'raw_payload'   => $raw,
        ]);

        return $item->is_valid() ? $item : null;
    }
}
