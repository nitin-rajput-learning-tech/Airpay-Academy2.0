<?php
/**
 * Coursera for Business content provider adapter.
 *
 * Coursera API v1 — programs/courses endpoint.
 * Auth: Basic auth via client_id + client_secret → OAuth2 access token.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market\adapter;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_content_market\catalog_item;

class coursera_provider implements provider_interface {

    private const TOKEN_URL = 'https://api.coursera.org/oauth2/client_credentials/token';
    private const API_BASE  = 'https://api.coursera.org/api';
    private const TIMEOUT   = 30;

    private bool $has_more = false;
    private ?string $access_token = null;

    public function get_provider_key(): string {
        return 'coursera';
    }

    public function get_display_name(): string {
        return 'Coursera for Business';
    }

    public function is_configured(): bool {
        $client_id     = get_config('local_sentientia_content_market', 'coursera_client_id');
        $client_secret = get_config('local_sentientia_content_market', 'coursera_client_secret');
        if (empty($client_id) || empty($client_secret)) {
            return false;
        }
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return false;
        }
        return \local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.enabled')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.coursera.enabled');
    }

    public function fetch_courses(int $page = 1, int $page_size = 100): array {
        if (!$this->authenticate()) {
            $this->has_more = false;
            return [];
        }

        $start = ($page - 1) * $page_size;
        $url   = self::API_BASE . '/courses.v1?' . http_build_query([
            'start'  => $start,
            'limit'  => $page_size,
            'fields' => 'id,slug,name,description,photoUrl,workload,language,domainTypes,subtitleLanguages',
        ]);

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $this->access_token,
            'Accept: application/json',
        ]);
        $curl->setopt('CURLOPT_TIMEOUT', self::TIMEOUT);

        $response = $curl->get($url);
        $info = $curl->get_info();

        if ($curl->get_errno() || ($info['http_code'] ?? 0) !== 200) {
            debugging("coursera_provider: HTTP {$info['http_code']} on page {$page}", DEBUG_DEVELOPER);
            $this->has_more = false;
            return [];
        }

        $data = @json_decode($response, true);
        if (!is_array($data)) {
            $this->has_more = false;
            return [];
        }

        $this->has_more = ($data['paging']['next'] ?? '') !== '';
        $elements = $data['elements'] ?? [];

        return array_values(array_filter(array_map(
            fn($raw) => $this->normalise($raw),
            $elements
        )));
    }

    public function has_more_pages(): bool {
        return $this->has_more;
    }

    /**
     * Obtain OAuth2 client-credentials access token from Coursera.
     * Returns false on failure — caller must check before using access_token.
     */
    private function authenticate(): bool {
        if ($this->access_token !== null) {
            return true;  // Already have a valid token for this request.
        }

        $client_id     = get_config('local_sentientia_content_market', 'coursera_client_id');
        $client_secret = get_config('local_sentientia_content_market', 'coursera_client_secret');

        $curl = new \curl();
        $curl->setHeader(['Accept: application/json']);
        $curl->setopt('CURLOPT_TIMEOUT', self::TIMEOUT);
        $curl->setopt('CURLOPT_USERPWD', "{$client_id}:{$client_secret}");

        $response = $curl->post(self::TOKEN_URL, http_build_query([
            'grant_type' => 'client_credentials',
        ]));
        $info = $curl->get_info();

        if ($curl->get_errno() || ($info['http_code'] ?? 0) !== 200) {
            debugging("coursera_provider: token request failed HTTP {$info['http_code']}", DEBUG_DEVELOPER);
            return false;
        }

        $token_data = @json_decode($response, true);
        if (!isset($token_data['access_token'])) {
            return false;
        }

        $this->access_token = $token_data['access_token'];
        return true;
    }

    private function normalise(array $raw): ?catalog_item {
        $ext_id = (string) ($raw['id'] ?? '');
        $title  = trim($raw['name'] ?? '');
        if ($ext_id === '' || $title === '') {
            return null;
        }

        // Workload: Coursera reports "X hours/week" — convert 1 week of content to minutes.
        $duration_mins = null;
        if (!empty($raw['workload'])) {
            if (preg_match('/(\d+)/u', $raw['workload'], $m)) {
                $duration_mins = (int)$m[1] * 60;  // hours → minutes approximation
            }
        }

        $skill_names = [];
        foreach (($raw['domainTypes'] ?? []) as $dt) {
            if (!empty($dt['subdomainId'])) {
                $skill_names[] = str_replace('_', ' ', ucwords($dt['subdomainId'], '_'));
            }
        }

        $item = catalog_item::from_array([
            'provider'      => 'coursera',
            'external_id'   => $ext_id,
            'title'         => $title,
            'description'   => $raw['description'] ?? null,
            'thumbnail_url' => $raw['photoUrl'] ?? null,
            'provider_url'  => 'https://www.coursera.org/learn/' . ($raw['slug'] ?? $ext_id),
            'duration_mins' => $duration_mins,
            'language'      => strtolower($raw['language'] ?? 'en'),
            'level'         => null,  // Coursera API does not expose level directly.
            'content_type'  => 'course',
            'price_usd'     => null,  // Included in B2B subscription.
            'skill_names'   => $skill_names,
            'raw_payload'   => $raw,
        ]);

        return $item->is_valid() ? $item : null;
    }
}
