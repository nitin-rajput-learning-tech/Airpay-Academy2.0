<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_courses\bizlms\course_tags_importer;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_platform\tests\bizlms\static_scanner;

/**
 * The course_tags importer (ADR-032, mapping doc section 7): the importer contract plus the feature's own cases.
 *
 * The importer moves the tag instances of the BizLMS course tag area (local_courses / courses) to the core area
 * (core / course) IN PLACE. The source is core tag_instance, which the PHPUnit database already has, so the only
 * legacy tables here are local_tags and local_tag_mapping, which the importer declines and must leave alone
 * (tests/fixtures/bizlms/local_tags.install.xml).
 *
 * What the contract cannot say for an in-place step is overridden here, with the reason: the source is a core table
 * that cannot be dropped, and a clean run rewrites its own source, so the tests that run the import twice on one
 * seed put the moved rows back first (contract_clear_import).
 *
 * There is no tenant column, no PRESERVE step and no person column in this feature, so the org dependency, the
 * collision/adoption and the privacy cases of the contract skip themselves.
 *
 * The seed (every row has an explicit id so a re-seed gives the same map):
 *
 *   tag_instance 9001, 9002, 9003, 9008  local_courses/courses, move (9008 is a personal tag: tiuserid kept)
 *                9004                    twin of 9101 (same item, context, user and tag): folded, untouched
 *                9007                    twin of 9102, both without a context: folded, untouched
 *                9005                    its course does not exist: skipped (course_missing)
 *                9006                    its tag does not exist: skipped (tag_missing)
 *                9101, 9102              native core/course rows
 *                9201, 9202, 9203        other areas (classroom, user, local_courses/sessions): never touched
 *   local_tags and local_tag_mapping     one row each: never touched
 *
 * @package    local_sentientia_courses
 * @category   test
 * @covers     \local_sentientia_courses\bizlms\course_tags_importer
 * @covers     \local_sentientia_courses\bizlms\course_tags_step
 * @covers     \local_sentientia_courses\bizlms\course_tags_remap_step
 *
 * @group local_sentientia_courses
 * @group bizlms_import
 */
final class bizlms_import_course_tags_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract {
        contract_clear_import as protected trait_contract_clear_import;
    }

    /** @var int Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** @var int[] Instances that move. */
    private const MOVED = [9001, 9002, 9003, 9008];

    /** @var array<int, int> Instances folded into a native core row: legacy id => core id. */
    private const FOLDED = [9004 => 9101, 9007 => 9102];

    /** @var array<int, string> Instances skipped: id => reason. */
    private const SKIPPED = [9005 => 'course_missing', 9006 => 'tag_missing'];

    /** @var int[] Rows that belong to other areas and must never change. */
    private const BYSTANDERS = [9101, 9102, 9201, 9202, 9203];

    /** @var \stdClass|null What the last seed created. */
    private ?\stdClass $seeded = null;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/local_tags.install.xml'];
    }

    protected function contract_importer(): importer {
        return new course_tags_importer();
    }

    protected function contract_decisions(): decisions {
        return decisions::from_array(['gaps.other_tag_areas' => 'left_in_place']);
    }

    protected function contract_seed(): void {
        $this->seed();
    }

    protected function contract_mutate_source(): void {
        global $DB;
        // A new instance of the old area changes the count and the highest id on every engine.
        $seed = $this->seeded ?? $this->seed();
        $DB->import_record('tag_instance', (object) [
            'id' => 9050, 'tagid' => $seed->tags['safety'], 'component' => 'local_courses', 'itemtype' => 'courses',
            'itemid' => $seed->courses[3], 'contextid' => $seed->contexts[3], 'tiuserid' => 0, 'ordering' => 0,
            'timecreated' => self::T0 + 50, 'timemodified' => self::T0 + 150,
        ]);
    }

    /**
     * A clean run moves the rows of its own source, so put them back before a second run on the same seed.
     *
     * @param importer $importer
     * @return void
     */
    protected function contract_clear_import(importer $importer): void {
        global $DB;
        foreach (self::MOVED as $id) {
            $DB->update_record('tag_instance', (object) ['id' => $id, 'component' => 'local_courses', 'itemtype' => 'courses']);
        }
        $this->trait_contract_clear_import($importer);
    }

    /**
     * The contract's version drops the claimed tables. This importer claims core tag_instance, which always exists
     * and must not be dropped, so there is no "not applicable": on a database that never had BizLMS the feature is
     * applicable, finds nothing to move, and completes with an empty trail.
     *
     * @return void
     */
    public function test_contract_not_applicable_without_tables(): void {
        global $DB;
        $importer = $this->contract_begin();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features'][$importer->feature()]);
        $this->assertTrue(legacymap::feature_complete($importer->feature()));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertSame(0, $DB->count_records(course_tags_importer::LEDGER));
    }

    public function test_the_registry_accepts_the_importer(): void {
        $this->contract_begin();
        $importers = registry::load();
        $this->assertSame(['course_tags'], array_keys($importers));
        $this->assertSame([], $importers['course_tags']->depends());
        $this->assertSame([], $importers['course_tags']->tenant_columns());
        $this->assertSame(['tag_instance'], array_keys($importers['course_tags']->core_writes()));
        $this->assertSame([course_tags_importer::LEDGER], $importers['course_tags']->target_tables());
    }

    public function test_the_importer_code_passes_the_static_scan(): void {
        $files = static_scanner::php_files(__DIR__ . '/../classes/bizlms');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertSame([], static_scanner::scan((string) file_get_contents($file), false, false), basename($file));
        }
    }

    public function test_instances_move_in_place_and_keep_every_other_column(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $before = $DB->get_records_list('tag_instance', 'id', self::MOVED, 'id');
        $count = $DB->count_records('tag_instance');

        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $this->assertSame($count, $DB->count_records('tag_instance'), 'no row is added or deleted');
        foreach (self::MOVED as $id) {
            $after = $DB->get_record('tag_instance', ['id' => $id], '*', MUST_EXIST);
            $this->assertSame('core', $after->component);
            $this->assertSame('course', $after->itemtype);
            foreach (['tagid', 'itemid', 'contextid', 'tiuserid', 'ordering', 'timecreated', 'timemodified'] as $column) {
                $this->assertSame($before[$id]->{$column}, $after->{$column}, "{$id}.{$column} is unchanged");
            }
        }
        $this->assertSame((string) $seed->user, $DB->get_field('tag_instance', 'tiuserid', ['id' => 9008]),
            'a personal tag stays personal');

        // The trail names exactly the moved rows and keeps their timestamps.
        $trail = $DB->get_records(course_tags_importer::LEDGER, null, 'taginstanceid');
        $this->assertSame(self::MOVED, array_values(array_map(fn($r) => (int) $r->taginstanceid, $trail)));
        foreach ($trail as $row) {
            $this->assertSame($before[(int) $row->taginstanceid]->timecreated, $row->timecreated);
            $this->assertSame($before[(int) $row->taginstanceid]->timemodified, $row->timemodified);
        }
        foreach (self::MOVED as $id) {
            $map = $DB->get_record(legacymap::TABLE, ['sourcetable' => course_tags_importer::SOURCE_UNIT,
                'sourceid' => $id, 'subkey' => ''], '*', MUST_EXIST);
            $this->assertSame('imported', $map->outcome);
            $this->assertSame(course_tags_importer::LEDGER, $map->targettable);
            $this->assertSame((string) $id, $DB->get_field(course_tags_importer::LEDGER, 'taginstanceid',
                ['id' => $map->targetid]));
        }
    }

    public function test_a_twin_in_the_core_area_is_folded_and_both_rows_are_left_alone(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $before = $DB->get_records_list('tag_instance', 'id', array_merge(array_keys(self::FOLDED), array_values(self::FOLDED)), 'id');

        $this->contract_run(true);

        $after = $DB->get_records_list('tag_instance', 'id', array_keys($before), 'id');
        $this->assertEquals($before, $after, 'the duplicate and its twin are not touched, and nothing is deleted');
        foreach (self::FOLDED as $legacyid => $coreid) {
            $this->assertSame('local_courses', $after[$legacyid]->component);
            $map = $DB->get_record(legacymap::TABLE, ['sourcetable' => course_tags_importer::SOURCE_UNIT,
                'sourceid' => $legacyid, 'subkey' => ''], '*', MUST_EXIST);
            $this->assertSame('folded', $map->outcome);
            $this->assertSame('duplicate_core_instance', $map->reason);
            $this->assertSame('tag_instance', $map->targettable);
            $this->assertSame($coreid, (int) $map->targetid, 'the map names the surviving core row');
        }
        $this->assertSame(0, $DB->count_records(course_tags_importer::LEDGER, ['taginstanceid' => 9004]));
    }

    public function test_orphans_are_skipped_with_a_reason_and_left_in_the_old_area(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $before = $DB->get_records_list('tag_instance', 'id', array_keys(self::SKIPPED), 'id');

        $this->contract_run(true);

        $this->assertEquals($before, $DB->get_records_list('tag_instance', 'id', array_keys(self::SKIPPED), 'id'));
        foreach (self::SKIPPED as $id => $code) {
            $map = $DB->get_record(legacymap::TABLE, ['sourcetable' => course_tags_importer::SOURCE_UNIT,
                'sourceid' => $id, 'subkey' => ''], '*', MUST_EXIST);
            $this->assertSame('skipped', $map->outcome);
            $this->assertSame($code, $map->reason);
            $this->assertSame('', $map->targettable);
            $this->assertNull($map->targetid);
        }
    }

    public function test_every_source_row_has_exactly_one_primary_map_row(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $this->contract_run(true);

        $rows = $DB->get_records(legacymap::TABLE, ['sourcetable' => course_tags_importer::SOURCE_UNIT, 'subkey' => ''],
            'sourceid', 'sourceid, outcome');
        $expected = array_merge(self::MOVED, array_keys(self::FOLDED), array_keys(self::SKIPPED));
        sort($expected);
        $this->assertSame($expected, array_map('intval', array_keys($rows)));
        $this->assertSame(0, $DB->count_records_select(legacymap::TABLE, "sourcetable = :st AND subkey <> ''",
            ['st' => course_tags_importer::SOURCE_UNIT]), 'no fan-out sub-rows');
        // Nothing in the old area is unaccounted for: the importer's own verify says so.
        $importer = new course_tags_importer();
        $this->assertSame([], $importer->verify(context::build($importer, false, 0, $this->contract_decisions())));
    }

    public function test_other_areas_and_the_legacy_tag_tables_are_never_touched(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $DB->insert_record('local_tags', (object) ['tagid' => 1, 'taginstanceid' => 9001, 'open_costcenterid' => 1,
            'open_departmentid' => null, 'timemodified' => self::T0]);
        $DB->insert_record('local_tag_mapping', (object) ['tagid' => 1, 'tagitemid' => 5, 'usercreated' => 2,
            'timecreated' => self::T0, 'usermodified' => null, 'timemodified' => null]);
        $bystanders = $DB->get_records_list('tag_instance', 'id', self::BYSTANDERS, 'id');
        $localtags = $DB->get_records('local_tags', null, 'id');
        $mapping = $DB->get_records('local_tag_mapping', null, 'id');

        $this->contract_run(true);

        $this->assertEquals($bystanders, $DB->get_records_list('tag_instance', 'id', self::BYSTANDERS, 'id'));
        $this->assertEquals($localtags, $DB->get_records('local_tags', null, 'id'), 'the tag overlay is archive, not source');
        $this->assertEquals($mapping, $DB->get_records('local_tag_mapping', null, 'id'));
        foreach ([9201 => 'local_classroom', 9202 => 'core', 9203 => 'local_courses'] as $id => $component) {
            $this->assertSame($component, $DB->get_field('tag_instance', 'component', ['id' => $id]));
        }
    }

    public function test_the_import_fires_no_tag_event(): void {
        $this->contract_begin();
        $this->seed();
        $events = $this->redirectEvents();
        $this->contract_run(true);
        $classes = array_map('get_class', $events->get_events());
        $this->assertSame([], $classes, 'a direct update: no tag_added, tag_removed or tag_updated');
    }

    public function test_a_dry_run_decides_the_same_and_moves_nothing(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $before = $DB->get_records('tag_instance', null, 'id');

        [$result, $report] = $this->contract_run(false);

        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertEquals($before, $DB->get_records('tag_instance', null, 'id'));
        $counters = $report->to_array()['features']['course_tags']['steps']['course_tags.instances']['counters'];
        $this->assertSame(8, $counters['processed']);
        $this->assertSame(count(self::MOVED), $counters['imported']);
        $this->assertSame(count(self::FOLDED), $counters['folded']);
        $this->assertSame(count(self::SKIPPED), $counters['skipped']);
    }

    public function test_a_second_apply_writes_nothing_to_tag_instance(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $this->contract_run(true);
        $after = $DB->get_records('tag_instance', null, 'id');
        $trail = $DB->get_records(course_tags_importer::LEDGER, null, 'id');

        [$result] = $this->contract_run(true);

        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertEquals($after, $DB->get_records('tag_instance', null, 'id'));
        $this->assertEquals($trail, $DB->get_records(course_tags_importer::LEDGER, null, 'id'));
    }

    public function test_preflight_reports_what_will_happen(): void {
        $this->contract_begin();
        $this->seed();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);

        $out = $runner->preflight(['course_tags']);

        $pf = $out['preflights']['course_tags'];
        $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
        $counts = $pf->counts();
        $this->assertSame(8, $counts['legacy_tag_instances']);
        $this->assertSame(1, $counts['will_skip_course_missing']);
        $this->assertSame(1, $counts['will_skip_tag_missing']);
        $this->assertSame(2, $counts['will_fold_duplicate_core_instance']);
        $this->assertSame(4, $counts['will_move']);
        $this->assertSame(1, $counts['other_area_left_in_place:local_classroom/classroom']);
        $this->assertSame(0, $counts['other_area_left_in_place:local_evaluation/evaluation']);
        $this->assertSame(8, $counts['rows:course_tags.instances']);
    }

    public function test_preflight_warns_about_moves_that_become_joiner_mandatory_courses(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);

        // The seed carries no tag called "mandatory", which is the lifecycle default.
        $pf = $runner->preflight(['course_tags'])['preflights']['course_tags'];
        $this->assertSame(0, $pf->counts()['will_move_with_the_lifecycle_mandatory_tag']);
        $this->assertStringNotContainsString('carry_the_lifecycle_mandatory_tag', implode(' ', $pf->warnings()));

        // A BizLMS course tagged "mandatory" would become a joiner auto-enrol trigger once it is in the core area.
        $mandatory = (int) $DB->insert_record('tag', (object) ['userid' => 0,
            'tagcollid' => (int) $DB->get_field('tag', 'tagcollid', ['id' => $seed->tags['safety']]),
            'name' => 'mandatory', 'rawname' => 'Mandatory', 'isstandard' => 0, 'description' => null,
            'descriptionformat' => 0, 'flag' => 0, 'timemodified' => self::T0]);
        $DB->import_record('tag_instance', (object) ['id' => 9061, 'tagid' => $mandatory, 'component' => 'local_courses',
            'itemtype' => 'courses', 'itemid' => $seed->courses[1], 'contextid' => $seed->contexts[1], 'tiuserid' => 0,
            'ordering' => 6, 'timecreated' => self::T0, 'timemodified' => self::T0]);
        $pf = $runner->preflight(['course_tags'])['preflights']['course_tags'];
        $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
        $this->assertSame(1, $pf->counts()['will_move_with_the_lifecycle_mandatory_tag']);
        $this->assertStringContainsString('moved_instances_carry_the_lifecycle_mandatory_tag:1', implode(' ', $pf->warnings()));

        // The name is the lifecycle setting, read the way lifecycle reads it. Two instances of "safety" move; the third
        // (9005) points at a course that does not exist and the fourth (9203) is not in the old area.
        set_config('mandatory_tag', ' Safety ', 'local_sentientia_lifecycle');
        $pf = $runner->preflight(['course_tags'])['preflights']['course_tags'];
        $this->assertSame(2, $pf->counts()['will_move_with_the_lifecycle_mandatory_tag']);

        // A twin in the core area is no new trigger: the course already carries the tag there.
        set_config('mandatory_tag', 'compliance', 'local_sentientia_lifecycle');
        $pf = $runner->preflight(['course_tags'])['preflights']['course_tags'];
        $this->assertSame(1, $pf->counts()['will_move_with_the_lifecycle_mandatory_tag'], '9008 moves, 9004 is a twin');

        // The preflight changes nothing.
        $this->assertSame('local_courses', $DB->get_field('tag_instance', 'component', ['id' => 9061]));
    }

    public function test_a_tag_in_another_collection_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $other = $DB->insert_record('tag_coll', (object) ['name' => 'other', 'isdefault' => 0, 'component' => null,
            'sortorder' => 9, 'searchable' => 1, 'customurl' => null]);
        $tagid = $DB->insert_record('tag', (object) ['userid' => 0, 'tagcollid' => $other, 'name' => 'elsewhere',
            'rawname' => 'Elsewhere', 'isstandard' => 0, 'description' => null, 'descriptionformat' => 0, 'flag' => 0,
            'timemodified' => self::T0]);
        $DB->import_record('tag_instance', (object) ['id' => 9060, 'tagid' => $tagid, 'component' => 'local_courses',
            'itemtype' => 'courses', 'itemid' => $seed->courses[1], 'contextid' => $seed->contexts[1], 'tiuserid' => 0,
            'ordering' => 5, 'timecreated' => self::T0, 'timemodified' => self::T0]);

        [$result] = $this->contract_run(true);

        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('tag_collection_mismatch:1', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE), 'a blocked run writes nothing');
        $this->assertSame('local_courses', $DB->get_field('tag_instance', 'component', ['id' => 9001]));
    }

    public function test_a_legacy_tag_area_in_another_collection_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $other = $DB->insert_record('tag_coll', (object) ['name' => 'other', 'isdefault' => 0, 'component' => null,
            'sortorder' => 9, 'searchable' => 1, 'customurl' => null]);
        $DB->insert_record('tag_area', (object) ['component' => 'local_courses', 'itemtype' => 'courses', 'enabled' => 1,
            'tagcollid' => $other, 'callback' => null, 'callbackfile' => null, 'showstandard' => 0, 'multiplecontexts' => 0]);

        [$result] = $this->contract_run(true);

        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('tag_collection_differs', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
    }

    public function test_the_decision_about_other_tag_areas_must_be_the_accepted_one(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();

        [$none] = $this->contract_run(true, ['decisions' => decisions::none()]);
        $this->assertSame(1, $none['exit']);
        $this->assertStringContainsString('missing_decision:gaps.other_tag_areas', implode(' ', $none['blockers']));

        [$other] = $this->contract_run(true, ['decisions' => decisions::from_array(['gaps.other_tag_areas' => 'move_them'])]);
        $this->assertSame(1, $other['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:gaps.other_tag_areas', implode(' ', $other['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
    }

    public function test_verify_reports_a_moved_row_that_is_no_longer_in_the_core_area(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx));

        // The row is back in the old area, where the map says it was imported and moved.
        // Both columns: the old area is local_courses/courses (the core area is core/course), so resetting the
        // component alone would leave the row in neither area.
        $DB->update_record('tag_instance', (object) ['id' => 9001, 'component' => 'local_courses',
            'itemtype' => 'courses']);
        $failures = $importer->verify($ctx);
        $this->assertContains('old_area_rows_unaccounted:1', $failures);
        $this->assertContains('trail_rows_not_in_the_core_area:1', $failures);

        // Once the site is open an administrator may delete a course's tags: only the trail check is waived.
        $DB->update_record('tag_instance', (object) ['id' => 9001, 'component' => 'core', 'itemtype' => 'course']);
        $DB->delete_records('tag_instance', ['id' => 9002]);
        $this->assertContains('trail_rows_not_in_the_core_area:1', $importer->verify($ctx));
        set_config('bizlms_production_open', 1, 'local_sentientia_platform');
        $this->assertSame([], $importer->verify($ctx));
    }

    public function test_verify_reports_a_trail_that_differs_from_the_map(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());

        $DB->insert_record(course_tags_importer::LEDGER, (object) ['taginstanceid' => 9201, 'timecreated' => 1, 'timemodified' => 1]);

        $failures = $importer->verify($ctx);
        $this->assertContains('trail_rows_differ_from_imported_map_rows: trail=5 imported=4', $failures);
    }

    public function test_the_required_version_has_exactly_one_upgrade_step_and_the_trail_table_is_installed(): void {
        $dir = dirname(__DIR__);
        $required = course_tags_importer::REQUIRES_VERSION;

        // upgrade_plugin_savepoint() throws downgrade_exception when the stored version is already at or above the
        // savepoint, and a step whose number is not above what a database already took never runs there: the importers of
        // this plugin must not share a number, and the trail table must come from exactly this one step.
        $upgrade = (string) file_get_contents($dir . '/db/upgrade.php');
        $this->assertSame(1, substr_count($upgrade, "upgrade_plugin_savepoint(true, {$required}, 'local', 'sentientia_courses')"));
        $this->assertSame(1, preg_match_all('/\$oldversion < ' . $required . '\)/', $upgrade));
        $this->assertSame(1, substr_count($upgrade, "'" . course_tags_importer::LEDGER . "'"));

        $others = [\local_sentientia_courses\bizlms\course_lookups_importer::REQUIRES_VERSION,
            \local_sentientia_courses\bizlms\enrolments_importer::REQUIRES_VERSION];
        foreach ($others as $other) {
            $this->assertGreaterThan($other, $required, 'a database that took the other importer must still run this step');
        }

        $xml = (string) file_get_contents($dir . '/db/install.xml');
        $this->assertSame(1, substr_count($xml, 'TABLE NAME="' . course_tags_importer::LEDGER . '"'));

        $plugin = new \stdClass();
        include($dir . '/version.php');
        $this->assertGreaterThanOrEqual($required, (int) $plugin->version);
    }

    // Seed.

    /**
     * Put the seed into core tag_instance (and two rows into the declined legacy tables when a test asks).
     *
     * @return \stdClass courses, contexts, tags (by name), user.
     */
    private function seed(): \stdClass {
        global $DB;
        $gen = $this->getDataGenerator();
        $seed = (object) ['courses' => [], 'contexts' => [], 'tags' => [], 'user' => 0];
        foreach ([1, 2, 3] as $n) {
            $course = $gen->create_course();
            $seed->courses[$n] = (int) $course->id;
            $seed->contexts[$n] = (int) \context_course::instance($course->id)->id;
        }
        $user = $gen->create_user();
        $seed->user = (int) $user->id;

        // The tags live in the collection of the core course area, as they do after the real remap.
        $collection = (int) $DB->get_field('tag_area', 'tagcollid', ['component' => 'core', 'itemtype' => 'course'], MUST_EXIST);
        foreach (['safety' => 'Safety', 'fraud' => 'Fraud', 'compliance' => 'Compliance'] as $name => $raw) {
            $seed->tags[$name] = (int) $DB->insert_record('tag', (object) ['userid' => 0, 'tagcollid' => $collection,
                'name' => $name, 'rawname' => $raw, 'isstandard' => 0, 'description' => null, 'descriptionformat' => 0,
                'flag' => 0, 'timemodified' => self::T0]);
        }
        $missingcourse = $seed->courses[3] + 1000;
        $missingtag = max($seed->tags) + 1000;
        $userctx = (int) \context_user::instance($user->id)->id;

        // id, component, itemtype, itemid, tagid, contextid, tiuserid, ordering.
        $rows = [
            [9001, 'local_courses', 'courses', $seed->courses[1], $seed->tags['safety'], $seed->contexts[1], 0, 0],
            [9002, 'local_courses', 'courses', $seed->courses[1], $seed->tags['fraud'], $seed->contexts[1], 0, 1],
            [9003, 'local_courses', 'courses', $seed->courses[2], $seed->tags['safety'], $seed->contexts[2], 0, 0],
            [9004, 'local_courses', 'courses', $seed->courses[2], $seed->tags['compliance'], $seed->contexts[2], 0, 1],
            [9005, 'local_courses', 'courses', $missingcourse, $seed->tags['safety'], $seed->contexts[1], 0, 0],
            [9006, 'local_courses', 'courses', $seed->courses[3], $missingtag, $seed->contexts[3], 0, 0],
            [9007, 'local_courses', 'courses', $seed->courses[3], $seed->tags['fraud'], null, 0, 0],
            [9008, 'local_courses', 'courses', $seed->courses[1], $seed->tags['compliance'], $seed->contexts[1], $seed->user, 2],
            // Native rows of the core area: the twins of 9004 and 9007.
            [9101, 'core', 'course', $seed->courses[2], $seed->tags['compliance'], $seed->contexts[2], 0, 0],
            [9102, 'core', 'course', $seed->courses[3], $seed->tags['fraud'], null, 0, 0],
            // Other areas.
            [9201, 'local_classroom', 'classroom', 77, $seed->tags['safety'], $seed->contexts[1], 0, 0],
            [9202, 'core', 'user', $seed->user, $seed->tags['safety'], $userctx, $seed->user, 0],
            [9203, 'local_courses', 'sessions', $seed->courses[1], $seed->tags['safety'], $seed->contexts[1], 0, 0],
        ];
        foreach ($rows as [$id, $component, $itemtype, $itemid, $tagid, $contextid, $tiuserid, $ordering]) {
            $DB->import_record('tag_instance', (object) ['id' => $id, 'tagid' => $tagid, 'component' => $component,
                'itemtype' => $itemtype, 'itemid' => $itemid, 'contextid' => $contextid, 'tiuserid' => $tiuserid,
                'ordering' => $ordering, 'timecreated' => self::T0 + $id, 'timemodified' => self::T0 + 100 + $id]);
        }
        $this->seeded = $seed;
        return $seed;
    }
}
