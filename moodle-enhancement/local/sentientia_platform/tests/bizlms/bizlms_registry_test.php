<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\registry_error;
use local_sentientia_platform\tests\bizlms\toy_importer;

/**
 * The importer registry: discovery, validation and dependency order (ADR-032,
 * "Importer interface").
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\registry
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class bizlms_registry_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        toy_importer::reset();
    }

    protected function tearDown(): void {
        registry::set_testing_importers(null);
        toy_importer::reset();
        parent::tearDown();
    }

    /**
     * @param toy_importer[] $importers
     * @return string[] Problems the registry reports for them.
     */
    private function problems(array $importers): array {
        registry::set_testing_importers($importers);
        try {
            registry::load();
            return [];
        } catch (registry_error $e) {
            return $e->problems;
        }
    }

    public function test_a_valid_registry_loads_in_feature_order(): void {
        registry::set_testing_importers([
            new toy_importer('toyitem', ['toyorg'], ['local_toy_item']),
            new toy_importer('toyorg', [], ['local_toy_org']),
        ]);
        $importers = registry::load();
        $this->assertSame(['toyitem', 'toyorg'], array_keys($importers));
        $this->assertSame(['toyorg', 'toyitem'], registry::sorted($importers));
    }

    public function test_sorted_adds_dependencies_and_breaks_ties_by_feature_key(): void {
        registry::set_testing_importers([
            new toy_importer('c', ['a'], ['local_toy_fan']),
            new toy_importer('b', ['a'], ['local_toy_dup']),
            new toy_importer('a', [], ['local_toy_org']),
            new toy_importer('z', [], ['local_toy_event']),
        ]);
        $importers = registry::load();
        $this->assertSame(['a', 'b', 'c', 'z'], registry::sorted($importers), 'whole registry: b before c, a first');
        $this->assertSame(['a', 'c'], registry::sorted($importers, ['c']), 'the dependency is added');
        $this->assertSame(['c'], registry::sorted($importers, ['c'], false), 'a single-feature dry run leaves it out');
    }

    public function test_an_unknown_feature_is_reported(): void {
        registry::set_testing_importers([new toy_importer()]);
        $this->expectException(registry_error::class);
        $this->expectExceptionMessage('unknown_feature:nothere');
        registry::sorted(registry::load(), ['nothere']);
    }

    public function test_a_duplicated_feature_key_is_refused(): void {
        $problems = $this->problems([new toy_importer('toy', [], ['local_toy_org']), new toy_importer('toy', [], ['local_toy_item'])]);
        $this->assertContains('duplicate_feature:toy', $problems);
    }

    public function test_a_legacy_table_has_exactly_one_owner(): void {
        $problems = $this->problems([
            new toy_importer('one', [], ['local_toy_org']),
            new toy_importer('two', [], ['local_toy_org', 'local_toy_item']),
        ]);
        $this->assertContains('table_owned_twice:local_toy_org:one,two', $problems);
    }

    public function test_an_unknown_dependency_is_refused(): void {
        $problems = $this->problems([new toy_importer('toychild', ['nothere'], ['local_toy_fan'])]);
        $this->assertContains('unknown_dependency:toychild->nothere', $problems);
    }

    public function test_a_cyclic_dependency_is_refused(): void {
        $problems = $this->problems([
            new toy_importer('a', ['b'], ['local_toy_org']),
            new toy_importer('b', ['a'], ['local_toy_item']),
        ]);
        $this->assertNotEmpty(preg_grep('/^cyclic_dependency:/', $problems));
    }

    public function test_a_plugin_below_the_required_version_is_refused(): void {
        toy_importer::$requiresversion = 9999999999;
        $this->assertContains('plugin_below_required_version:local_sentientia_platform', $this->problems([new toy_importer()]));
    }

    public function test_a_map_step_may_not_declare_external_refs(): void {
        toy_importer::$maprefs = true;
        $this->assertContains('map_step_declares_external_refs:toy.item', $this->problems([new toy_importer()]));
    }

    public function test_a_preserve_step_must_declare_external_refs(): void {
        toy_importer::$preservenorefs = true;
        $this->assertContains('preserve_step_without_external_refs:toy.org', $this->problems([new toy_importer()]));
    }

    public function test_a_legacy_table_cannot_be_a_target(): void {
        toy_importer::$legacytarget = true;
        $this->assertContains('target_is_read_only:toy:local_costcenter', $this->problems([new toy_importer()]));
    }

    public function test_every_problem_is_reported_at_once(): void {
        toy_importer::$maprefs = true;
        toy_importer::$preservenorefs = true;
        $problems = $this->problems([new toy_importer()]);
        $this->assertContains('map_step_declares_external_refs:toy.item', $problems);
        $this->assertContains('preserve_step_without_external_refs:toy.org', $problems);
    }

    public function test_disk_discovery_walks_the_installed_plugins_without_failing(): void {
        registry::set_testing_importers(null);
        // Whatever importers the installed plugins register through db/bizlms_import.php must validate.
        $this->assertIsArray(registry::load());
    }
}
