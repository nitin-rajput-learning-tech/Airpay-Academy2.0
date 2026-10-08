<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\bizlms\cohort_scope_importer;
use local_sentientia_org\tests\bizlms\org_stub_importer;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The cohort_scope importer (ADR-032, mapping doc section 5): the importer contract plus the feature's own
 * fixture.
 *
 * The world it imports: a BizLMS organisation tree (/1, /1/5, /77, /177) with course categories, eleven
 * local_groups rows for ten cohorts, and one core cohort with no satellite row at all.
 *
 *  cohort  context          local_groups row                                   expected scope
 *  A       category /1/5    id 901, open_path /1/5, departments 5,12           /1/5, exact; actor and date kept
 *  B       category /77     id 902, open_path ' 77/ ', ' 5 , x, 12 ,5'        /77, normalised; departments 5,12 (cleaned)
 *  C       category /1/5    id 903, open_path '0'                              /1/5, from the context
 *  D       system           id 904, open_path '0'                              no path (pathless), reported
 *  E       system           id = A's COHORT id, open_path /1                   /1 (the row id means nothing)
 *  F       child of /1/5    id 906, open_path ''                               /1/5, from the parent category
 *  G       category /77     id 907, open_path /1/5                             /1/5 kept, path_context_mismatch
 *  H       category /1/5    ids 908 (/1/5) and 909 (/177, newer)               908 wins (agrees with the context), 909 merged
 *  I       (no such cohort) id 910                                             skipped: orphan_cohort
 *  J       category /1      id 911, timemodified 0                             /1, date from the cohort row
 *  K       system           none                                               no scope row
 *
 * @package    local_sentientia_org
 * @category   test
 * @covers     \local_sentientia_org\bizlms\cohort_scope_importer
 * @covers     \local_sentientia_org\bizlms\cohort_scope_step
 * @covers     \local_sentientia_org\bizlms\cohort_context
 * @covers     \local_sentientia_org\bizlms\cohort_files
 *
 * @group local_sentientia_org
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_cohort_scope_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;

    /** Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** @var array<string, int> Named ids of the seeded world. */
    private array $w = [];

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/groups.install.xml'];
    }

    protected function contract_importer(): importer {
        return new cohort_scope_importer();
    }

    /**
     * The org feature is a separate deliverable; its stand-in lets the registry accept a feature that depends on it.
     *
     * @return importer
     */
    protected function contract_begin(): importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([new org_stub_importer(), $importer]);
        return $importer;
    }

    protected function contract_decisions(): decisions {
        return decisions::from_array([cohort_scope_importer::DECISION_UNRESOLVED => 'pathless']);
    }

    protected function contract_user_columns(): array {
        return [cohort_scope_importer::TARGET => ['usermodified']];
    }

    protected function contract_mutate_source(): void {
        // A new row changes the count and the max id on every engine, so detection does not depend on the CRC.
        $this->add_group(990, $this->w['K'], '/1', 1, null, self::T0);
    }

    /**
     * Put the world in place.
     *
     * @return void
     */
    protected function contract_seed(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $t = self::T0;

        // The organisation tree the tenant resolver validates against (the org importer is not part of this test).
        foreach ([[1, 'Airpay', '/1', 0, 1], [77, 'Public', '/77', 0, 1], [177, 'ZEEA', '/177', 0, 1],
                [5, 'Payments', '/1/5', 1, 2], [12, 'Risk', '/1/5/12', 5, 3]] as [$id, $name, $path, $parent, $depth]) {
            if (!$DB->record_exists('local_sentientia_org', ['id' => $id])) {
                $DB->import_record('local_sentientia_org', (object) [
                    'id' => $id, 'fullname' => $name, 'shortname' => strtolower($name), 'parentid' => $parent,
                    'path' => $path, 'depth' => $depth, 'visible' => 1, 'sortorder' => $id * 10,
                    'timecreated' => $t, 'timemodified' => $t,
                ]);
            }
        }

        // BizLMS gave every organisation a course category; local_costcenter.category is the link.
        $cat1 = $gen->create_category(['name' => 'Airpay']);
        $cat5 = $gen->create_category(['name' => 'Payments', 'parent' => $cat1->id]);
        $cat77 = $gen->create_category(['name' => 'Public']);
        $catplain = $gen->create_category(['name' => 'Unowned', 'parent' => $cat5->id]);
        foreach ([[1, '/1', $cat1->id], [5, '/1/5', $cat5->id], [77, '/77', $cat77->id]] as [$id, $path, $category]) {
            $DB->import_record('local_costcenter', (object) [
                'id' => $id, 'fullname' => 'Org ' . $id, 'shortname' => 'org' . $id, 'parentid' => 0, 'path' => $path,
                'depth' => substr_count($path, '/'), 'category' => $category, 'visible' => 1,
                'timecreated' => $t, 'timemodified' => $t, 'usermodified' => 0,
            ]);
        }
        $ctx1 = \context_coursecat::instance($cat1->id)->id;
        $ctx5 = \context_coursecat::instance($cat5->id)->id;
        $ctx77 = \context_coursecat::instance($cat77->id)->id;
        $ctxplain = \context_coursecat::instance($catplain->id)->id;
        $sys = \context_system::instance()->id;

        $admin = $gen->create_user();
        $member = $gen->create_user();
        $this->w = ['admin' => (int) $admin->id, 'member' => (int) $member->id];
        $this->w['A'] = $this->make_cohort('A', $ctx5);
        $this->w['B'] = $this->make_cohort('B', $ctx77);
        $this->w['C'] = $this->make_cohort('C', $ctx5);
        $this->w['D'] = $this->make_cohort('D', $sys);
        $this->w['E'] = $this->make_cohort('E', $sys);
        $this->w['F'] = $this->make_cohort('F', $ctxplain);
        $this->w['G'] = $this->make_cohort('G', $ctx77);
        $this->w['H'] = $this->make_cohort('H', $ctx5);
        $this->w['J'] = $this->make_cohort('J', $ctx1);
        $this->w['K'] = $this->make_cohort('K', $sys);
        $this->w['ctx5'] = $ctx5;
        $this->w['ctx77'] = $ctx77;
        $this->w['sys'] = $sys;
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        // Moodle 5.x core testing_data_generator has no cohort-member helper: use the core API.
        cohort_add_member($this->w['A'], $member->id);

        // local_groups. Row ids are deliberately unrelated to cohort ids, and E's row id IS the cohort id of A:
        // local_groups_update_groups() updated the row whose id equals the cohort id, so an id proves nothing.
        $this->add_group(901, $this->w['A'], '/1/5', 1, '5,12', $t + 10, (int) $admin->id);
        $this->add_group(902, $this->w['B'], ' 77/ ', 77, ' 5 , x, 12 ,5', $t + 20);
        $this->add_group(903, $this->w['C'], '0', null, '7', $t + 30);
        $this->add_group(904, $this->w['D'], '0', null, null, $t + 40);
        $this->add_group($this->w['A'], $this->w['E'], '/1', 0, '', $t + 50);
        $this->add_group(906, $this->w['F'], '', null, '0', $t + 60);
        $this->add_group(907, $this->w['G'], '/1/5', 77, '12', $t + 70);
        $this->add_group(908, $this->w['H'], '/1/5', 1, '5', $t + 5);
        $this->add_group(909, $this->w['H'], '/177', 177, '9', $t + 500);
        $this->add_group(910, 987654, '/1', 1, null, $t + 80);
        $this->add_group(911, $this->w['J'], '/1', 1, null, 0);

        // BizLMS description files: component local_groups on an edit, groups on an add.
        $this->add_file($ctx5, 'local_groups', $this->w['A'], 'a.txt', 'alpha');
        $this->add_file($ctx77, 'local_groups', $this->w['B'], 'logo.png', 'edit');
        $this->add_file($ctx77, 'groups', $this->w['B'], 'logo.png', 'add');
        $this->add_file($ctx77, 'groups', $this->w['B'], 'extra.txt', 'extra');
        $this->add_file($ctx5, 'groups', $this->w['C'], 'c.txt', 'gamma');
        $this->add_file($sys, 'local_groups', $this->w['K'], 'k.txt', 'kappa');
    }

    /**
     * @param string $name
     * @param int $contextid
     * @return int The cohort id.
     */
    private function make_cohort(string $name, int $contextid): int {
        global $DB;
        $cohort = $this->getDataGenerator()->create_cohort([
            'name' => 'Cohort ' . $name, 'idnumber' => 'c' . strtolower($name), 'contextid' => $contextid,
        ]);
        $DB->set_field('cohort', 'timecreated', self::T0 + 1, ['id' => $cohort->id]);
        $DB->set_field('cohort', 'timemodified', self::T0 + 2, ['id' => $cohort->id]);
        return (int) $cohort->id;
    }

    /**
     * @param int $id Row id.
     * @param int $cohortid
     * @param string $path
     * @param int|null $costcenterid
     * @param string|null $departments
     * @param int $modified
     * @param int $usermodified
     * @return void
     */
    private function add_group(int $id, int $cohortid, string $path, ?int $costcenterid, ?string $departments,
                               int $modified, int $usermodified = 0): void {
        global $DB;
        $DB->import_record('local_groups', (object) [
            'id' => $id, 'cohortid' => $cohortid, 'costcenterid' => $costcenterid, 'departmentid' => $departments,
            'open_path' => $path, 'timemodified' => $modified, 'usermodified' => $usermodified,
        ]);
    }

    /**
     * @param int $contextid
     * @param string $component
     * @param int $itemid
     * @param string $name
     * @param string $content
     * @return void
     */
    private function add_file(int $contextid, string $component, int $itemid, string $name, string $content): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $contextid, 'component' => $component, 'filearea' => 'description', 'itemid' => $itemid,
            'filepath' => '/', 'filename' => $name,
        ], $content);
    }

    /**
     * Seed, apply and return the report.
     *
     * @param array $options Overrides for the runner options.
     * @return array{0: array, 1: \local_sentientia_platform\bizlms\report}
     */
    private function apply(array $options = []): array {
        $this->contract_begin();
        $this->contract_seed();
        return $this->contract_run(true, $options);
    }

    /**
     * @param string $key A cohort letter of the seed.
     * @return \stdClass|false The scope row of that cohort.
     */
    private function scope(string $key) {
        global $DB;
        return $DB->get_record(cohort_scope_importer::TARGET, ['cohortid' => $this->w[$key]]);
    }

    public function test_every_cohort_gets_the_scope_the_map_describes(): void {
        global $DB;
        [$result, $report] = $this->apply();
        $this->assertSame(0, $result['exit'], implode('; ', $result['blockers']));
        $t = self::T0;

        $a = $this->scope('A');
        $this->assertSame('/1/5', $a->open_path);
        $this->assertSame('5,12', $a->departmentids);
        $this->assertEquals($this->w['admin'], $a->usermodified, 'the actor is kept');
        $this->assertEquals($t + 10, $a->timemodified, 'the source date is kept, not the import time');

        $b = $this->scope('B');
        $this->assertSame('/77', $b->open_path, 'a path with stray spaces and slashes is normalised');
        $this->assertSame('5,12', $b->departmentids, 'junk and a repeat are dropped from the department list');

        $this->assertSame('/1/5', $this->scope('C')->open_path, "'0' is unknown: the path comes from the cohort's context");
        $this->assertSame('7', $this->scope('C')->departmentids);
        $this->assertNull($this->scope('D')->open_path, 'a system-context cohort no row can place has no tenant path');
        $this->assertNull($this->scope('D')->departmentids);
        $this->assertSame('/1', $this->scope('E')->open_path, 'joined on the cohort id, not on the row id');
        $this->assertNull($this->scope('E')->departmentids, 'an empty list is NULL');
        $this->assertSame('/1/5', $this->scope('F')->open_path, 'the nearest ancestor category that an organisation owns');
        $this->assertNull($this->scope('F')->departmentids, "'0' means none");
        $this->assertSame('/1/5', $this->scope('G')->open_path, 'a disagreement is reported, the row keeps its path');
        $this->assertSame('12', $this->scope('G')->departmentids);
        $this->assertEquals($t + 2, $this->scope('J')->timemodified, 'no date on the row: the cohort row date');

        $this->assertFalse($this->scope('K'), 'a cohort with no local_groups row gets no scope row');
        $this->assertSame(9, $DB->count_records(cohort_scope_importer::TARGET));

        foreach ($DB->get_records(cohort_scope_importer::TARGET) as $row) {
            if ($row->open_path !== null) {
                $this->assertTrue(runner::is_valid_tenant_value($row->open_path), 'a normalised path on a registered tenant');
            }
        }

        // The report says how every tenant was decided, and what was odd.
        $step = $report->to_array()['features']['cohort_scope']['steps']['cohort_scope.scope'];
        $this->assertEquals(['exact' => 5, 'normalised' => 1, 'fallback:context' => 2, 'unresolved' => 1],
            $step['tenant_methods']);
        $this->assertEquals(['tenant_unresolved' => 1, 'path_context_mismatch' => 1, 'departments_cleaned' => 1,
            'derived_timestamp' => 1], $step['warnings']);
        $this->assertEquals(['orphan_cohort' => 1, 'dup_cohort_row' => 1], $step['skipped_by_reason']);
    }

    public function test_two_rows_for_one_cohort_become_one_scope_row_and_one_merge(): void {
        global $DB;
        $this->apply();
        $scope = $this->scope('H');
        $this->assertSame('/1/5', $scope->open_path, 'the row that agrees with the cohort context wins, although the other is newer');
        $this->assertSame('5', $scope->departmentids);
        $this->assertEquals(self::T0 + 5, $scope->timemodified);

        $won = $DB->get_record(legacymap::TABLE, ['sourcetable' => 'local_groups', 'sourceid' => 908, 'subkey' => '']);
        $this->assertSame('imported', $won->outcome);
        $this->assertEquals($scope->id, $won->targetid);
        $lost = $DB->get_record(legacymap::TABLE, ['sourcetable' => 'local_groups', 'sourceid' => 909, 'subkey' => '']);
        $this->assertSame('merged', $lost->outcome);
        $this->assertSame('dup_cohort_row', $lost->reason);
        $this->assertEquals($scope->id, $lost->targetid, 'the duplicate points at the row that won');
        $this->assertSame(1, $DB->count_records(cohort_scope_importer::TARGET, ['cohortid' => $this->w['H']]));
    }

    public function test_a_row_for_a_cohort_that_does_not_exist_is_skipped_with_a_code(): void {
        global $DB;
        $this->apply();
        $skipped = $DB->get_record(legacymap::TABLE, ['sourcetable' => 'local_groups', 'sourceid' => 910, 'subkey' => '']);
        $this->assertSame('skipped', $skipped->outcome);
        $this->assertSame('orphan_cohort', $skipped->reason);
        $this->assertSame('cohort_not_found', $skipped->detail, 'codes only, no id');
        $this->assertNull($skipped->targetid);
        $this->assertFalse($DB->record_exists(cohort_scope_importer::TARGET, ['cohortid' => 987654]));
    }

    public function test_a_row_whose_id_is_another_cohorts_id_still_scopes_its_own_cohort(): void {
        global $DB;
        $this->apply();
        // E's local_groups row carries the id of A's cohort (BizLMS' update-by-cohort-id trap).
        $row = $DB->get_record('local_groups', ['id' => $this->w['A']]);
        $this->assertEquals($this->w['E'], $row->cohortid);
        $this->assertSame('/1', $this->scope('E')->open_path);
        $this->assertSame('/1/5', $this->scope('A')->open_path, "A's own row is not confused with it");
    }

    public function test_the_owner_can_choose_to_skip_cohorts_no_tenant_can_be_found_for(): void {
        global $DB;
        [$result] = $this->apply(['decisions' => decisions::from_array([cohort_scope_importer::DECISION_UNRESOLVED => 'skip'])]);
        $this->assertSame(0, $result['exit'], implode('; ', $result['blockers']));
        $this->assertFalse($this->scope('D'), 'skipped, not imported with no path');
        $this->assertSame(8, $DB->count_records(cohort_scope_importer::TARGET));
        $map = $DB->get_record(legacymap::TABLE, ['sourcetable' => 'local_groups', 'sourceid' => 904, 'subkey' => '']);
        $this->assertSame('skipped', $map->outcome);
        $this->assertSame('no_tenant', $map->reason);
        $this->assertSame('tenant_unresolved', $map->detail);
    }

    public function test_a_missing_owner_decision_blocks_the_feature_before_anything_is_written(): void {
        global $DB;
        [$result] = $this->apply(['decisions' => decisions::none()]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('missing_decision:' . cohort_scope_importer::DECISION_UNRESOLVED,
            implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(cohort_scope_importer::TARGET));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
    }

    public function test_a_scope_row_the_import_did_not_write_blocks_the_run(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // A native row for a cohort: the target's unique key would fail the first insert for it mid-run.
        $DB->insert_record(cohort_scope_importer::TARGET, (object) [
            'cohortid' => $this->w['A'], 'open_path' => '/1', 'departmentids' => null, 'usermodified' => 0,
            'timemodified' => self::T0,
        ]);
        [$result] = $this->contract_run(false);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('target_has_rows_this_import_did_not_write', implode(' ', $result['blockers']));
    }

    public function test_the_description_files_are_copied_and_the_originals_are_kept(): void {
        $this->apply();
        $fs = get_file_storage();
        $a = $this->w['A'];
        $b = $this->w['B'];
        $c = $this->w['C'];

        $copy = $fs->get_file($this->w['ctx5'], 'cohort', 'description', $a, '/', 'a.txt');
        $this->assertNotFalse($copy, 'a file BizLMS stored on an edit is served from the core cohort area');
        $this->assertSame('alpha', $copy->get_content());
        $this->assertTrue($fs->file_exists($this->w['ctx5'], 'local_groups', 'description', $a, '/', 'a.txt'),
            'the original stays: the legacy archive is never altered');

        $this->assertSame('edit', $fs->get_file($this->w['ctx77'], 'cohort', 'description', $b, '/', 'logo.png')->get_content(),
            'the edit path beats the add path when both hold a file of the same name');
        $this->assertSame('extra', $fs->get_file($this->w['ctx77'], 'cohort', 'description', $b, '/', 'extra.txt')->get_content());
        $this->assertSame('add', $fs->get_file($this->w['ctx77'], 'groups', 'description', $b, '/', 'logo.png')->get_content());

        $this->assertSame('gamma', $fs->get_file($this->w['ctx5'], 'cohort', 'description', $c, '/', 'c.txt')->get_content(),
            'a file BizLMS stored on an add (component groups) is copied too');

        $this->assertFalse($fs->file_exists($this->w['sys'], 'cohort', 'description', $this->w['K'], '/', 'k.txt'),
            'a cohort without a scope row was not imported, so its files are not copied');
    }

    public function test_the_description_copies_are_a_declared_side_effect_and_the_report_counts_them(): void {
        global $DB;
        [$result, $report] = $this->apply();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        // IDN-04: {files} is watched for every importer; this one declares core's cohort description area.
        $importer = new cohort_scope_importer();
        $this->assertInstanceOf(\local_sentientia_platform\bizlms\copies_files::class, $importer);
        $this->assertSame([
            ['local_groups', 'description', 'cohort', 'description'],
            ['groups', 'description', 'cohort', 'description'],
        ], $importer->allowed_file_areas());
        $this->assertSame([], $importer->core_writes(), 'a file copy is not a core write, so --purge-feature stays available');

        $feature = $report->to_array()['features']['cohort_scope'];
        $this->assertSame('clean', $feature['tripwire']);
        $copies = $DB->count_records_select('files', "component = 'cohort' AND filearea = 'description' AND filename <> '.'");
        $this->assertGreaterThan(0, $copies);
        $this->assertSame(['cohort/description' => $copies], $feature['files_copied']);
    }

    public function test_a_second_apply_copies_no_file_twice(): void {
        $this->apply();
        $fs = get_file_storage();
        $before = count($fs->get_area_files($this->w['ctx77'], 'cohort', 'description', $this->w['B'], 'id', false));
        $this->assertSame(2, $before);
        $this->contract_run(true);
        $this->assertCount($before, $fs->get_area_files($this->w['ctx77'], 'cohort', 'description', $this->w['B'], 'id', false));
    }

    public function test_cohorts_members_and_the_source_table_are_left_exactly_as_they_were(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $cohorts = $DB->get_records('cohort', null, 'id', 'id, name, contextid, timecreated, timemodified, component, visible');
        $members = $DB->get_records('cohort_members', null, 'id');
        $groups = $DB->get_records('local_groups', null, 'id');

        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', $result['blockers']));

        $this->assertEquals($cohorts, $DB->get_records('cohort', null, 'id', 'id, name, contextid, timecreated, timemodified, component, visible'));
        $this->assertEquals($members, $DB->get_records('cohort_members', null, 'id'));
        $this->assertEquals($groups, $DB->get_records('local_groups', null, 'id'), 'local_groups is the archive and is never written');
    }

    public function test_the_importer_declares_what_the_mapping_doc_says(): void {
        $importer = new cohort_scope_importer();
        $this->assertSame('cohort_scope', $importer->feature());
        $this->assertSame('local_sentientia_org', $importer->component());
        $this->assertSame(['org'], $importer->depends());
        $this->assertSame(['local_groups'], array_keys($importer->sources()));
        $this->assertSame([], $importer->declined_tables());
        $this->assertSame(['local_sentientia_cohort_scope'], $importer->target_tables());
        $this->assertSame([], $importer->core_writes(), 'core cohort and cohort_members are not written');
        $this->assertSame(['local_sentientia_cohort_scope' => 'open_path'], $importer->tenant_columns());
        $this->assertTrue($importer->atomic());
        $this->assertSame(['orphan_cohort', 'no_tenant', 'dup_cohort_row'],
            array_map(static fn($reason) => $reason->code, $importer->reasons()));
        $steps = $importer->steps();
        $this->assertCount(1, $steps);
        $this->assertSame(\local_sentientia_platform\bizlms\idpolicy::MAP, $steps[0]->idpolicy(), 'nothing stores a local_groups id');
        $this->assertSame(['cohortid'], $steps[0]->group_by());
        $this->assertGreaterThanOrEqual(cohort_scope_importer::REQUIRES_VERSION, (int) get_config('local_sentientia_org', 'version'));
    }

    public function test_verify_reports_a_scope_row_whose_cohort_is_gone(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx), 'a clean import verifies');

        $DB->delete_records('cohort', ['id' => $this->w['H']]);
        $this->assertSame(['scope_row_without_a_cohort:1'], $importer->verify($ctx));

        // Once the site is open (the runbook sets this) administrators may delete cohorts: the check stops there.
        set_config('bizlms_production_open', 1, 'local_sentientia_platform');
        $this->assertSame([], $importer->verify($ctx));
    }

    public function test_verify_reports_a_description_file_that_lost_its_copy_once_the_feature_is_complete(): void {
        $importer = $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());

        $copy = get_file_storage()->get_file($this->w['ctx5'], 'cohort', 'description', $this->w['A'], '/', 'a.txt');
        $copy->delete();
        $this->assertSame(['description_files_not_copied:1'], $importer->verify($ctx));

        // finalise() puts it back, and is safe to run again.
        $importer->finalise($ctx);
        $this->assertSame([], $importer->verify($ctx));
    }

    public function test_the_upgrade_step_and_install_xml_agree_on_the_table(): void {
        global $DB;
        $columns = array_keys($DB->get_columns(cohort_scope_importer::TARGET));
        $this->assertSame(['id', 'cohortid', 'open_path', 'departmentids', 'usermodified', 'timemodified'], $columns);
    }
}
