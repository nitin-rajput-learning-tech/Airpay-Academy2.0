<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

defined('MOODLE_INTERNAL') || die();

/**
 * Question-type registry — Phase E.4.scaffold (2026-05-24).
 *
 * Maps a slide.type slug to the FQCN of its concrete
 * abstract_question_type subclass. The slug is the canonical name
 * stored in {local_sentientia_live_slides}.type and exposed via the
 * REST API; the FQCN is an implementation detail callers don't pin.
 *
 * Adding a 7th type:
 *   1. Drop a new subclass in this directory with SLUG = 'foo'.
 *   2. Add an entry to TYPES below.
 *   3. Add the lang strings (en + hi) for name + description.
 *   4. Register the type's feature flag in
 *      local_sentientia_platform/db/feature_flags.php (default OFF).
 *   5. Add the slug to slide_manager::VALID_TYPES + the schema's
 *      type column comment (purely documentary).
 *
 * Steps 1-4 are the only code edits required. The registry pulls the
 * class up lazily, so no shotgun-modify of callers.
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_type_registry {

    /**
     * Slug → FQCN map. Order is the canonical display order on the
     * slide-type picker.
     */
    private const TYPES = [
        'multichoice' => multiple_choice::class,
        'wordcloud'   => word_cloud::class,
        'openended'   => open_ended::class,
        'rating'      => rating_scale::class,
        'quiz'        => quiz::class,
        'ranking'     => ranking::class,
    ];

    /**
     * Return every registered type as a fresh instance, keyed by slug.
     * Iteration order matches the TYPES map (= slide-picker order).
     *
     * Use this when you need to enumerate types — e.g. the slide
     * type picker UI, the registration check in tests.
     *
     * @return array<string, abstract_question_type>
     */
    public static function get_all(): array {
        $out = [];
        foreach (self::TYPES as $slug => $class) {
            $out[$slug] = new $class();
        }
        return $out;
    }

    /**
     * Return only types whose feature flag resolves to ON. Used by the
     * slide-type picker so disabled types simply don't appear.
     *
     * @return array<string, abstract_question_type>
     */
    public static function get_enabled(): array {
        $out = [];
        foreach (self::get_all() as $slug => $inst) {
            if ($inst->is_enabled()) {
                $out[$slug] = $inst;
            }
        }
        return $out;
    }

    /**
     * Resolve a slug to a fresh instance. Returns null if the slug is
     * not registered.
     *
     * @param string $slug The slide.type value.
     * @return abstract_question_type|null
     */
    public static function get_by_slug(string $slug): ?abstract_question_type {
        $class = self::TYPES[$slug] ?? null;
        if ($class === null) {
            return null;
        }
        return new $class();
    }

    /**
     * Return the list of registered slugs in canonical order.
     *
     * @return string[]
     */
    public static function list_slugs(): array {
        return array_keys(self::TYPES);
    }

    /**
     * Cheap existence check — does NOT instantiate the class.
     */
    public static function exists(string $slug): bool {
        return isset(self::TYPES[$slug]);
    }
}
