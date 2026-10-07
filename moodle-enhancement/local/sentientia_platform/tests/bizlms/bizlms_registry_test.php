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

    public function test_a_map_step_may_not_declare_target_children(): void {
        // A MAP step takes new ids, so no child can already name one of its legacy ids.
        toy_importer::$mapchildren = true;
        $this->assertContains('map_step_declares_target_children:toy.item', $this->problems([new toy_importer()]));
    }

    public function test_target_children_must_be_plain_table_and_column_names(): void {
        toy_importer::$badchildren = true;
        $this->assertContains('target_children_malformed:toy.org', $this->problems([new toy_importer()]));
        toy_importer::reset();
        toy_importer::$orgchildren = true;
        $this->assertSame([], $this->problems([new toy_importer()]), 'a PRESERVE step may declare them');
    }

    public function test_a_legacy_table_cannot_be_a_target(): void {
        toy_importer::$legacytarget = true;
        $this->assertContains('target_is_read_only:toy:local_costcenter', $this->problems([new toy_importer()]));
    }

    public function test_names_that_do_not_fit_the_framework_columns_are_refused_up_front(): void {
        $long = str_repeat('a', 41);
        $problems = $this->problems([new toy_importer($long, [], ['local_toy_org'])]);
        $this->assertContains("feature_key_too_long:{$long}", $problems);
        // A feature of exactly 40 characters is fine: its step keys stay well under the 64-character column.
        $this->assertSame([], $this->problems([new toy_importer(str_repeat('b', 40), [], ['local_toy_org'])]));
    }

    public function test_every_problem_is_reported_at_once(): void {
        toy_importer::$maprefs = true;
        toy_importer::$preservenorefs = true;
        $problems = $this->problems([new toy_importer()]);
        $this->assertContains('map_step_declares_external_refs:toy.item', $problems);
        $this->assertContains('preserve_step_without_external_refs:toy.org', $problems);
    }

    public function test_a_target_that_the_plugins_own_schema_does_not_define_is_refused(): void {
        // user_enrolments is a core history table: an importer never writes it as a target, and never another
        // plugin's table.
        toy_importer::$extratarget = 'user_enrolments';
        $this->assertContains('target_not_in_the_plugin_schema:toy:user_enrolments', $this->problems([new toy_importer()]));
    }

    public function test_a_legacy_table_cannot_be_a_target_however_it_is_found(): void {
        // Declined by the importer itself: still a legacy table.
        toy_importer::$extratarget = 'local_toy_unused';
        $this->assertContains('target_is_read_only:toy:local_toy_unused', $this->problems([new toy_importer()]));

        // Claimed by another importer.
        toy_importer::$extratarget = 'local_toy_fan';
        $problems = $this->problems([
            new toy_importer('one', [], ['local_toy_org']),
            new toy_importer('two', [], ['local_toy_fan']),
        ]);
        $this->assertContains('target_is_read_only:one:local_toy_fan', $problems);
    }

    public function test_a_core_write_must_be_on_the_list_reviewed_against_the_adr(): void {
        toy_importer::$corewritetable = 'grade_grades';
        $this->assertContains('core_write_not_reviewed:toy:grade_grades', $this->problems([new toy_importer()]));

        toy_importer::$corewritetable = null;
        toy_importer::$corewrites = true;
        $this->assertSame([], $this->problems([new toy_importer()]), 'course is on the list (the open_* backfill)');

        $this->assertSame(['course', 'enrol', 'role_assignments', 'tag_instance', 'user_enrolments'],
            array_keys(registry::CORE_WRITES_ALLOWED));
        // Each table is reviewed for named operations only, and every entry says why.
        foreach (registry::CORE_WRITES_ALLOWED as $table => $entry) {
            $this->assertNotEmpty($entry['operations'], $table);
            $this->assertEmpty(array_diff($entry['operations'], ['insert', 'update']), $table);
            $this->assertNotSame('', trim($entry['why']), $table);
        }
        $this->assertSame(['update'], registry::core_write_operations('course'));
        $this->assertSame(['update'], registry::core_write_operations('tag_instance'));
        $this->assertSame(['insert', 'update'], registry::core_write_operations('user_enrolments'));
        $this->assertSame([], registry::core_write_operations('grade_grades'));
        foreach (['course_completions', 'course_modules_completion', 'grade_grades', 'grade_items', 'logstore_standard_log',
                  'role_capabilities', 'messages', 'notifications', 'quiz_attempts', 'badge_issued'] as $history) {
            $this->assertArrayNotHasKey($history, registry::CORE_WRITES_ALLOWED,
                "{$history} is history or configuration, never an import target");
        }
    }

    public function test_a_map_step_may_not_write_the_table_a_preserve_step_owns(): void {
        toy_importer::$dupintoorg = true;
        $this->assertContains('map_step_targets_a_preserve_table:toy.dup:local_sentientia_toy_org',
            $this->problems([new toy_importer()]));
    }

    public function test_importer_and_step_code_must_live_where_the_static_scan_reads(): void {
        $toy = new toy_importer();
        // The toy is test scaffolding and lives in tests/classes/bizlms: a test registry accepts that, a real one does not.
        $this->assertNull(registry::code_location_problem($toy, 'local_sentientia_platform', true));
        $this->assertSame('code_outside_classes_bizlms', registry::code_location_problem($toy, 'local_sentientia_platform', false));
        foreach ($toy->steps() as $step) {
            $this->assertNull(registry::code_location_problem($step, 'local_sentientia_platform', true), 'an anonymous step is judged where it is written');
            $this->assertSame('code_outside_classes_bizlms', registry::code_location_problem($step, 'local_sentientia_platform'));
        }

        // Code in classes/bizlms/ is fine; anything else is not scanned.
        $this->assertNull(registry::code_location_problem(new \local_sentientia_platform\bizlms\text(), 'local_sentientia_platform'));
        $this->assertSame('code_outside_classes_bizlms', registry::code_location_problem($this, 'local_sentientia_platform'));
        $this->assertSame('code_location_unknown', registry::code_location_problem(new \stdClass(), 'local_sentientia_platform'));
        $this->assertSame('code_location_unknown', registry::code_location_problem($toy, 'local_nosuchplugin'));
    }

    public function test_an_importer_with_tenant_columns_must_have_the_org_feature_in_its_dependency_closure(): void {
        // Tenant resolution reads the organisation table the org importer fills. The toy declares a tenant column.
        $problems = $this->problems([
            new toy_importer('org', [], ['local_toy_org']),
            new toy_importer('toyitem', [], ['local_toy_item']),
        ]);
        $this->assertContains('tenant_resolution_needs_org:toyitem:org', $problems);
        $this->assertNotContains('tenant_resolution_needs_org:org:org', $problems, 'the owner itself is exempt');

        // Declared directly.
        $this->assertSame([], $this->problems([
            new toy_importer('org', [], ['local_toy_org']),
            new toy_importer('toyitem', ['org'], ['local_toy_item']),
        ]));
        // Declared through another feature: the closure counts.
        $this->assertSame([], $this->problems([
            new toy_importer('org', [], ['local_toy_org']),
            new toy_importer('toyitem', ['org'], ['local_toy_item']),
            new toy_importer('toyfan', ['toyitem'], ['local_toy_fan']),
        ]));
        // Not declared even though a sibling depends on it.
        $problems = $this->problems([
            new toy_importer('org', [], ['local_toy_org']),
            new toy_importer('toyitem', ['org'], ['local_toy_item']),
            new toy_importer('toyfan', [], ['local_toy_fan']),
        ]);
        $this->assertSame(['tenant_resolution_needs_org:toyfan:org'], $problems);
    }

    public function test_an_importer_without_tenant_columns_is_not_held_to_the_org_rule(): void {
        toy_importer::$notenantcolumns = true;
        $this->assertSame([], $this->problems([
            new toy_importer('org', [], ['local_toy_org']),
            new toy_importer('toyfan', [], ['local_toy_fan']),
        ]));
    }

    public function test_a_test_registry_with_no_org_feature_is_not_held_to_the_org_rule(): void {
        // A registry read from disk with no org importer refuses a tenant importer (tenant_owner_not_registered);
        // a test registry that never registered one is testing something else.
        $this->assertSame([], $this->problems([new toy_importer()]));
        $this->assertSame('org', registry::TENANT_OWNER);
    }

    public function test_disk_discovery_walks_the_installed_plugins_without_failing(): void {
        registry::set_testing_importers(null);
        // Whatever importers the installed plugins register through db/bizlms_import.php must validate.
        $this->assertIsArray(registry::load());
    }
}
