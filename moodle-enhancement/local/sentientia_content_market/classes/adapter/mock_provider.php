<?php
/**
 * Mock content provider — used in PHPUnit tests and as a live demo in dev.
 *
 * Returns synthetic catalog items seeded from a static fixture array.
 * Never makes any HTTP calls. Credentials not required.
 * Mirror of the KeKa test-double pattern in sentientia_integrations tests.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market\adapter;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_content_market\catalog_item;

class mock_provider implements provider_interface {

    /** @var bool Flag controlling has_more_pages() return value. */
    private bool $has_more = false;

    /** @var catalog_item[]|null Optional override fixture set by tests. */
    private ?array $fixture_items = null;

    /**
     * Inject a custom fixture for tests.
     *
     * @param catalog_item[] $items
     */
    public function set_fixture(array $items): void {
        $this->fixture_items = $items;
    }

    public function get_provider_key(): string {
        return 'mock';
    }

    public function get_display_name(): string {
        return 'Mock Provider (Test/Demo)';
    }

    public function is_configured(): bool {
        // Mock provider is always "configured" — no credentials needed.
        return true;
    }

    /**
     * Return a page of synthetic catalog items.
     *
     * Page 1: returns the fixture / default items.
     * Page 2+: returns empty (simulates single-page provider).
     */
    public function fetch_courses(int $page = 1, int $page_size = 100): array {
        if ($page > 1) {
            $this->has_more = false;
            return [];
        }

        $this->has_more = false;

        if ($this->fixture_items !== null) {
            return $this->fixture_items;
        }

        return $this->default_fixture();
    }

    public function has_more_pages(): bool {
        return $this->has_more;
    }

    /**
     * Default fixture — 5 realistic synthetic courses covering common skill areas.
     *
     * @return catalog_item[]
     */
    private function default_fixture(): array {
        $fixtures = [
            [
                'provider'      => 'mock',
                'external_id'   => 'mock-001',
                'title'         => 'Introduction to Compliance & AML',
                'description'   => 'A foundational course covering Anti-Money Laundering regulations and compliance essentials for financial services professionals.',
                'thumbnail_url' => null,
                'provider_url'  => 'https://example.com/courses/mock-001',
                'duration_mins' => 45,
                'language'      => 'en',
                'level'         => 'beginner',
                'content_type'  => 'course',
                'price_usd'     => null,
                'skill_names'   => ['Compliance', 'AML', 'Financial Regulation'],
            ],
            [
                'provider'      => 'mock',
                'external_id'   => 'mock-002',
                'title'         => 'Payment Systems Architecture',
                'description'   => 'Deep-dive into modern payment rails, UPI, RTGS, and NEFT for FinTech engineers.',
                'thumbnail_url' => null,
                'provider_url'  => 'https://example.com/courses/mock-002',
                'duration_mins' => 120,
                'language'      => 'en',
                'level'         => 'intermediate',
                'content_type'  => 'course',
                'price_usd'     => 29.99,
                'skill_names'   => ['Payment Systems', 'UPI', 'FinTech'],
            ],
            [
                'provider'      => 'mock',
                'external_id'   => 'mock-003',
                'title'         => 'Data Privacy & DPDP Act 2023',
                'description'   => 'Understanding India\'s Digital Personal Data Protection Act and its implications for enterprises.',
                'thumbnail_url' => null,
                'provider_url'  => 'https://example.com/courses/mock-003',
                'duration_mins' => 60,
                'language'      => 'en',
                'level'         => 'intermediate',
                'content_type'  => 'course',
                'price_usd'     => null,
                'skill_names'   => ['Data Privacy', 'DPDP', 'Legal Compliance'],
            ],
            [
                'provider'      => 'mock',
                'external_id'   => 'mock-004',
                'title'         => 'Effective Communication in Finance',
                'description'   => 'Build stakeholder communication skills specific to financial services environments.',
                'thumbnail_url' => null,
                'provider_url'  => 'https://example.com/courses/mock-004',
                'duration_mins' => 30,
                'language'      => 'en',
                'level'         => 'beginner',
                'content_type'  => 'microlearning',
                'price_usd'     => null,
                'skill_names'   => ['Communication', 'Stakeholder Management'],
            ],
            [
                'provider'      => 'mock',
                'external_id'   => 'mock-005',
                'title'         => 'Advanced SQL for Analytics',
                'description'   => 'Window functions, CTEs, and performance tuning for data analysts working with large datasets.',
                'thumbnail_url' => null,
                'provider_url'  => 'https://example.com/courses/mock-005',
                'duration_mins' => 180,
                'language'      => 'en',
                'level'         => 'advanced',
                'content_type'  => 'course',
                'price_usd'     => 49.99,
                'skill_names'   => ['SQL', 'Data Analytics', 'Database'],
            ],
        ];

        return array_map(
            fn($d) => catalog_item::from_array(array_merge($d, ['raw_payload' => $d])),
            $fixtures
        );
    }
}
