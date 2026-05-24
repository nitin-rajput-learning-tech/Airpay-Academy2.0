<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

use local_sentientia_live\question_types\abstract_question_type;
use local_sentientia_live\question_types\multiple_choice;
use local_sentientia_live\question_types\open_ended;
use local_sentientia_live\question_types\question_type_registry;
use local_sentientia_live\question_types\quiz;
use local_sentientia_live\question_types\ranking;
use local_sentientia_live\question_types\rating_scale;
use local_sentientia_live\question_types\word_cloud;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for question_type_registry — Phase E.4-E.9 scaffold
 * (2026-05-24).
 *
 * Asserts the registry contract that future chips depend on:
 *
 *   1. All 6 question types resolve through get_by_slug().
 *   2. Each instance is an abstract_question_type subclass.
 *   3. get_all() returns the canonical 6-entry map.
 *   4. The slug each instance reports matches its registry key (no
 *      copy-paste drift in the SLUG constant).
 *   5. Unknown slugs return null (no exception thrown).
 *   6. The same slug resolves to the SAME concrete class on repeat
 *      calls (registry is stable, not state-dependent).
 *   7. The slug list matches slide_manager::VALID_TYPES so the storage
 *      layer and the type layer don't drift.
 *
 * Run via:
 *   cd /path/to/moodle/public
 *   vendor/bin/phpunit local/sentientia_live/tests/question_type_registry_test.php
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\question_types\question_type_registry
 * @covers     \local_sentientia_live\question_types\abstract_question_type
 */
final class question_type_registry_test extends \advanced_testcase {

    /**
     * The canonical 6 slugs + the FQCN each MUST resolve to.
     */
    private const EXPECTED_TYPES = [
        'multichoice' => multiple_choice::class,
        'wordcloud'   => word_cloud::class,
        'openended'   => open_ended::class,
        'rating'      => rating_scale::class,
        'quiz'        => quiz::class,
        'ranking'     => ranking::class,
    ];

    public function test_get_all_returns_six_registered_types(): void {
        $all = question_type_registry::get_all();
        $this->assertCount(6, $all,
            'Registry must publish exactly 6 question types (E.4-E.9 scaffold).');

        foreach (self::EXPECTED_TYPES as $slug => $fqcn) {
            $this->assertArrayHasKey($slug, $all,
                "Slug '$slug' missing from registry.");
            $this->assertInstanceOf($fqcn, $all[$slug],
                "Slug '$slug' must resolve to $fqcn.");
        }
    }

    public function test_get_by_slug_resolves_every_expected_slug(): void {
        foreach (self::EXPECTED_TYPES as $slug => $fqcn) {
            $instance = question_type_registry::get_by_slug($slug);
            $this->assertNotNull($instance,
                "Slug '$slug' must resolve to a concrete instance.");
            $this->assertInstanceOf($fqcn, $instance,
                "Slug '$slug' must instantiate $fqcn.");
        }
    }

    public function test_every_instance_is_abstract_question_type_subclass(): void {
        foreach (question_type_registry::get_all() as $slug => $instance) {
            $this->assertInstanceOf(abstract_question_type::class, $instance,
                "Type '$slug' must extend abstract_question_type.");
        }
    }

    public function test_each_instance_slug_matches_registry_key(): void {
        // Catches copy-paste drift: a subclass whose SLUG constant
        // disagrees with the registry key would silently break callers
        // that round-trip via $instance->get_slug().
        foreach (question_type_registry::get_all() as $key => $instance) {
            $this->assertSame($key, $instance->get_slug(),
                "Instance for key '$key' reports slug '"
                . $instance->get_slug() . "' — mismatch.");
        }
    }

    public function test_get_by_slug_returns_null_for_unknown(): void {
        $this->assertNull(question_type_registry::get_by_slug(''));
        $this->assertNull(question_type_registry::get_by_slug('not_a_type'));
        $this->assertNull(question_type_registry::get_by_slug('MULTICHOICE'));
        $this->assertNull(question_type_registry::get_by_slug(' multichoice '));
    }

    public function test_repeat_resolution_yields_same_concrete_class(): void {
        // Registry MUST be stateless — two resolutions of the same slug
        // produce instances of the same class. (Distinct instances are
        // fine; same-class is the invariant.)
        $a = question_type_registry::get_by_slug('quiz');
        $b = question_type_registry::get_by_slug('quiz');
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertSame(get_class($a), get_class($b),
            'Two get_by_slug() calls for the same slug must yield the same class.');
    }

    public function test_list_slugs_matches_slide_manager_valid_types(): void {
        // The storage layer (slide_manager::VALID_TYPES) and the type
        // layer (registry) must agree about which slugs are legal. If
        // they drift, slide_manager::add() will accept a slug the
        // registry can't resolve.
        $registry_slugs = question_type_registry::list_slugs();
        $storage_slugs = slide_manager::VALID_TYPES;

        sort($registry_slugs);
        sort($storage_slugs);
        $this->assertSame($storage_slugs, $registry_slugs,
            'Registry slug list MUST equal slide_manager::VALID_TYPES.');
    }
}
