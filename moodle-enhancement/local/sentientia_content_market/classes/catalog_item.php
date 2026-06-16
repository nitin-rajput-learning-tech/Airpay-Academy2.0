<?php
/**
 * Normalised catalog item DTO.
 *
 * Every provider adapter normalises its raw API response into instances of
 * this class before returning from fetch_courses(). The market_aggregator
 * persists these to {local_sentientia_cm_item}. The DTO is intentionally
 * simple — plain typed properties, no business logic.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market;

defined('MOODLE_INTERNAL') || die();

class catalog_item {

    /** @var string Provider key (go1 | udemy_business | coursera | skillsoft | mock) */
    public string $provider = '';

    /** @var string Provider's unique course ID */
    public string $external_id = '';

    /** @var string Course title */
    public string $title = '';

    /** @var string|null Long-form description */
    public ?string $description = null;

    /** @var string|null URL to thumbnail image */
    public ?string $thumbnail_url = null;

    /** @var string|null Deep-link URL to launch course on provider platform */
    public ?string $provider_url = null;

    /** @var int|null Duration in minutes */
    public ?int $duration_mins = null;

    /** @var string ISO 639-1 language code (default: en) */
    public string $language = 'en';

    /** @var string|null beginner | intermediate | advanced */
    public ?string $level = null;

    /** @var string|null video | course | microlearning | podcast | article */
    public ?string $content_type = null;

    /** @var float|null Price in USD; null = subscription-included / free */
    public ?float $price_usd = null;

    /**
     * Skill names as returned by the provider (pre-taxonomy-mapping strings).
     * The market_aggregator passes these to skills_mapper after saving the item.
     *
     * @var string[]
     */
    public array $skill_names = [];

    /**
     * Full normalised payload for storage in raw_payload column.
     * Set by the adapter before returning; allows re-processing without
     * re-fetching from the API.
     *
     * @var array
     */
    public array $raw_payload = [];

    /**
     * Factory: build from an associative array (adapter convenience method).
     *
     * @param array $data Assoc array of field => value
     * @return static
     */
    public static function from_array(array $data): static {
        $item = new static();
        foreach ($data as $key => $value) {
            if (property_exists($item, $key)) {
                $item->{$key} = $value;
            }
        }
        return $item;
    }

    /**
     * Basic validation — must have provider, external_id, and title.
     *
     * @return bool
     */
    public function is_valid(): bool {
        return $this->provider !== ''
            && $this->external_id !== ''
            && $this->title !== '';
    }
}
