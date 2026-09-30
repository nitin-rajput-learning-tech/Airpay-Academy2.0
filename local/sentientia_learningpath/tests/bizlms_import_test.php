<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_learningpath\bizlms\importer as lp_importer;
use local_sentientia_learningpath\tests\bizlms\standalone_importer;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * ADR-032: the learningplan importer (mapping doc, section 17), run against a copy of the BizLMS schema.
 *
 * The contract tests come from the importer_contract trait. The tests below add what is specific to this
 * feature: the id and status rules, the tenant fallback order, the skill and level map, the decisions, the
 * cover file and the ways the feature must refuse. The importer under test is wrapped so it runs without
 * the org and skills importers, which belong to other plugins; the real dependency list is asserted on the
 * real class.
 *
 * Numbers a test may rely on (see seed_plans() and the other seed_* methods):
 *
 *  plans    8 rows: 10, 11, 12, 13, 14, 16 and 17 import, 15 has no name (skipped: no_name). Plan 14 is
 *           the one a retired copy script wrote a header for (the adoptable case).
 *  courses  8 rows: 1, 2, 6 and 7 import, 3 is merged into 2, 4 has no course, 5 a course that is gone,
 *           8 a plan that does not exist.
 *  users    9 rows: 1, 2, 4, 5, 6 and 9 import, 3 is merged into 2, 7 has no plan, 8 no user.
 *  status   3 rows: 2 imports, 1 is merged into 2, 3 has no plan.
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @covers     \local_sentientia_learningpath\bizlms\importer
 * @covers     \local_sentientia_learningpath\bizlms\path_step
 * @covers     \local_sentientia_learningpath\bizlms\course_step
 * @covers     \local_sentientia_learningpath\bizlms\user_step
 * @covers     \local_sentientia_learningpath\bizlms\course_status_step
 *
 * @group local_sentientia_learningpath
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use \local_sentientia_org\test\bizlms_fixture;

    /** Timestamp base of the seed. */
    private const T = 1700000000;

    /** A 1x1 PNG, for the cover file. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @var array<string, int> Users by key. */
    private array $u = [];

    /** @var array<int, int> Courses by number. */
    private array $c = [];

    /** @var array The owner choices the tests run with (the signed values of docs/cutover/bizlms-import-decisions.json). */
    private array $choices = [
        'tenant.unresolved.learningplan' => 'pathless',
        'learningplan.not_completed' => 'derive_in_progress',
        'learningplan.enforce_rules' => 'store_only',
        'learningplan.dates_as' => 'enrolment_window',
        'learningplan.history_on_archived_paths' => 'hidden_from_learners',
        'learningplan.tenant_fallback_order' => ['costcenter_column_when_valid', 'root_shared_by_all_enrolled_users',
            'creator_root', 'null_path_reported'],
    ];

    protected static function legacy_fixture_definition(): array {
        return [
            'xml' => __DIR__ . '/fixtures/bizlms/learningplan.install.xml',
            // local_learningplan_approval belongs to the request feature.
            'only' => ['local_learningplan', 'local_learningplan_courses', 'local_learningplan_user',
                'local_plan_course_status'],
        ];
    }

    protected function contract_importer(): importer {
        return new standalone_importer(new lp_importer());
    }

    protected function contract_requires_direct_org_dependency(): bool {
        // The wrapper has no dependencies on purpose; the real list is asserted in test_depends_on_org_and_skills.
        return false;
    }

    protected function contract_decisions(): decisions {
        return decisions::from_array($this->choices);
    }

    /**
     * The contract trait clears every map row between two runs, which would also clear the skills map rows a
     * feature test seeds. Clear only what this feature wrote.
     *
     * @param importer $importer
     * @return void
     */
    protected function contract_clear_import(importer $importer): void {
        global $DB;
        foreach ($importer->target_tables() as $table) {
            $DB->delete_records($table);
        }
        $DB->delete_records(legacymap::TABLE, ['feature' => $importer->feature()]);
        $DB->delete_records('local_sentientia_legacystep');
        $DB->delete_records('local_sentientia_legacyrun');
        unset_config('bizlms_complete_' . $importer->feature(), 'local_sentientia_platform');
        unset_config('bizlms_tripped_' . $importer->feature(), 'local_sentientia_platform');
    }

    protected function contract_seed(): void {
        $this->ensure_bizlms_schema();
        $this->seed_people();
        $this->seed_plans();
        $this->seed_courses();
        $this->seed_users();
        $this->seed_status();
    }

    protected function contract_mutate_source(): void {
        // A new row changes the count and the max id on every engine, so detection does not depend on the CRC.
        $this->plan(['id' => 50, 'name' => 'Late arrival', 'open_path' => '/1']);
    }

    protected function contract_collision(): ?array {
        return ['table' => lp_importer::PATHS, 'row' => (object) ['id' => 10, 'name' => 'Somebody else',
            'open_path' => null, 'status' => 1, 'visible' => 1, 'timecreated' => 5, 'timemodified' => 5]];
    }

    protected function contract_adoptable(): ?array {
        // What the retired migrate_all.php wrote: the same id, name and created time as the source row.
        return ['table' => lp_importer::PATHS, 'sourcetable' => lp_importer::SRC_PLAN,
            'row' => (object) ['id' => 14, 'name' => 'Adopt me', 'open_path' => null, 'status' => 1, 'visible' => 1,
                'timecreated' => self::T + 14, 'timemodified' => 7]];
    }

    protected function contract_user_columns(): array {
        return [
            lp_importer::PATHS => ['usercreated', 'usermodified'],
            lp_importer::COURSES => ['usercreated', 'usermodified'],
            lp_importer::USERS => ['enrolledby'],
            lp_importer::STATUS => ['userid', 'usercreated', 'usermodified'],
        ];
    }

    // Seed.

    /**
     * Organisations (what the org importer leaves), users and courses.
     *
     * @return void
     */
    private function seed_people(): void {
        global $DB;
        $t = self::T;
        $DB->delete_records('local_sentientia_org');
        foreach ([[1, 'Airpay', '/1', 1, 0], [5, 'Payments', '/1/5', 2, 1], [77, 'Public', '/77', 1, 0],
                  [177, 'ZEEA', '/177', 1, 0]] as [$id, $name, $path, $depth, $parent]) {
            $DB->import_record('local_sentientia_org', (object) ['id' => $id, 'fullname' => $name,
                'shortname' => strtolower($name), 'parentid' => $parent, 'path' => $path, 'depth' => $depth,
                'visible' => 1, 'sortorder' => 0, 'timecreated' => $t, 'timemodified' => $t]);
        }
        $make = function (string $path): int {
            global $DB;
            $user = $this->getDataGenerator()->create_user();
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
            return (int) $user->id;
        };
        $this->u = ['u1' => $make('/1/5'), 'u2' => $make('/77'), 'u3' => $make('/177'), 'u4' => $make('/1'),
            'creator' => $make('/1'), 'admin77' => $make('/77')];
        $DB->set_field('user', 'deleted', 1, ['id' => $this->u['u4']]);

        foreach ([1, 2, 3] as $n) {
            $this->c[$n] = (int) $this->getDataGenerator()->create_course()->id;
        }
        // u1 has completed course 1 (a course of plan 10).
        $DB->insert_record('course_completions', (object) ['userid' => $this->u['u1'], 'course' => $this->c[1],
            'timeenrolled' => 0, 'timestarted' => 0, 'timecompleted' => $t + 500, 'reaggregate' => 0]);
    }

    /**
     * Insert a legacy plan with the production defaults.
     *
     * @param array $fields
     * @return void
     */
    private function plan(array $fields): void {
        global $DB;
        $t = self::T;
        $DB->import_record('local_learningplan', (object) ($fields + [
            'shortname' => 'sn', 'visible' => 1, 'lpsequence' => 0, 'selfenrol' => 0, 'open_path' => '',
            'costcenter' => 0, 'timecreated' => $t, 'timemodified' => $t, 'usercreated' => 0, 'usermodified' => 0,
        ]));
    }

    private function seed_plans(): void {
        $t = self::T;
        $this->plan(['id' => 10, 'name' => 'Path ten', 'description' => '<p>Ten</p>', 'objective' => 'Learn it',
            'visible' => 1, 'open_path' => '/1/5', 'costcenter' => 1, 'approvalreqd' => 1, 'selfenrol' => 1,
            'lpsequence' => 1, 'learning_type' => 1, 'open_points' => 50, 'certificateid' => 7, 'open_skill' => 3,
            'open_level' => 2, 'open_categoryid' => 9, 'startdate' => $t + 10, 'enddate' => $t + 99999,
            'usercreated' => $this->u['creator'], 'usermodified' => $this->u['creator'], 'timecreated' => $t + 10,
            'timemodified' => $t + 20, 'summaryfile' => 4242]);
        // Hidden, and BizLMS never set its timemodified.
        $this->plan(['id' => 11, 'name' => 'Path eleven', 'visible' => 0, 'open_path' => '/77', 'costcenter' => 77,
            'timecreated' => $t + 11, 'timemodified' => 0]);
        // Empty path: the cost centre column decides.
        $this->plan(['id' => 12, 'name' => 'Path twelve', 'open_path' => '', 'costcenter' => 177,
            'timecreated' => $t + 12, 'timemodified' => $t + 12]);
        $this->plan(['id' => 13, 'name' => str_repeat('n', 255), 'open_path' => '/1', 'timecreated' => $t + 13,
            'timemodified' => $t + 13]);
        $this->plan(['id' => 14, 'name' => 'Adopt me', 'open_path' => '/1', 'timecreated' => $t + 14,
            'timemodified' => $t + 14]);
        $this->plan(['id' => 15, 'name' => '', 'open_path' => '/1', 'timecreated' => $t + 15, 'timemodified' => $t + 15]);
        // No path, no cost centre, nobody enrolled, no creator: no tenant at all.
        $this->plan(['id' => 16, 'name' => 'Path sixteen', 'open_path' => '', 'timecreated' => $t + 16,
            'timemodified' => $t + 16]);
        // A path whose root is not a tenant; the enrolled learner's tenant decides.
        $this->plan(['id' => 17, 'name' => 'Path seventeen', 'open_path' => '/99', 'timecreated' => $t + 17,
            'timemodified' => $t + 17]);
    }

    private function seed_courses(): void {
        global $DB;
        $t = self::T;
        $rows = [
            // Plan 10: 'and' + sort 0; 'or' + sort 1; a duplicate of course 2 with a later sort order; no course; a missing course.
            [1, 10, $this->c[1], 0, 'and', $t + 11],
            [2, 10, $this->c[2], 1, 'or', $t + 11],
            [3, 10, $this->c[2], 5, 'and', $t + 12],
            [4, 10, null, 2, 'or', $t + 11],
            [5, 10, 99999, 3, 'or', $t + 11],
            // Plan 11: upper-case operator and no sort order.
            [6, 11, $this->c[3], null, 'AND', $t + 11],
            // Plan 12: no operator and no created time (BizLMS rewrote it with time() on renumbering, or never set it).
            [7, 12, $this->c[1], null, null, 0],
            // A plan that is not in local_learningplan.
            [8, 999, $this->c[1], 0, 'or', $t + 11],
        ];
        foreach ($rows as [$id, $planid, $courseid, $sort, $operator, $created]) {
            $DB->import_record('local_learningplan_courses', (object) ['id' => $id, 'planid' => $planid,
                'courseid' => $courseid, 'moduletype' => '', 'sortorder' => $sort, 'nextsetoperator' => $operator,
                'timecreated' => $created, 'timemodified' => 0, 'usercreated' => 0, 'usermodified' => 0]);
        }
    }

    private function seed_users(): void {
        global $DB;
        $t = self::T;
        $u = $this->u;
        $rows = [
            // [id, plan, user, status, completiondate, created, usercreated]
            [1, 10, $u['u1'], null, null, $t + 30, $u['u1']],          // self-enrolled, course 1 done: in progress
            [2, 10, $u['u2'], 1, $t + 400, $t + 31, $u['creator']],    // completed
            [3, 10, $u['u2'], null, null, $t + 29, 0],                 // duplicate of 2, enrolled first
            [4, 10, $u['u4'], null, null, $t + 32, 0],                 // a deleted user: history is kept
            [5, 11, $u['u2'], null, null, $t + 33, 0],
            [6, 12, $u['u3'], 1, 0, $t + 34, 0],                       // completed, no date
            [7, 999, $u['u1'], null, null, $t + 35, 0],                // no such plan
            [8, 10, 987654, null, null, $t + 36, 0],                   // no such user
            [9, 17, $u['u3'], null, null, $t + 37, 0],
        ];
        foreach ($rows as [$id, $planid, $userid, $status, $date, $created, $by]) {
            $DB->import_record('local_learningplan_user', (object) ['id' => $id, 'planid' => $planid,
                'userid' => $userid, 'status' => $status, 'completiondate' => $date, 'timecreated' => $created,
                'timemodified' => 0, 'usercreated' => $by, 'usermodified' => 0]);
        }
    }

    private function seed_status(): void {
        global $DB;
        $t = self::T;
        $rows = [
            [1, 10, $this->c[1], $this->u['u1'], 3, 50, 0, $t + 600],
            [2, 10, $this->c[1], $this->u['u1'], 4, 100, $t + 5, $t + 700],
            [3, 999, $this->c[1], $this->u['u1'], 1, 10, 0, 0],
        ];
        foreach ($rows as [$id, $planid, $courseid, $userid, $status, $pct, $start, $done]) {
            $DB->import_record('local_plan_course_status', (object) ['id' => $id, 'planid' => $planid,
                'courseid' => $courseid, 'userid' => $userid, 'status' => $status, 'percentage' => $pct,
                'startdate' => $start, 'completiondate' => $done, 'timecreated' => $t + 40, 'timemodified' => 0,
                'usercreated' => 0, 'usermodified' => 0]);
        }
    }

    // Helpers.

    private function path_row(int $id): \stdClass {
        global $DB;
        return $DB->get_record(lp_importer::PATHS, ['id' => $id], '*', MUST_EXIST);
    }

    private function user_row(int $pathid, int $userid): \stdClass {
        global $DB;
        return $DB->get_record(lp_importer::USERS, ['pathid' => $pathid, 'userid' => $userid], '*', MUST_EXIST);
    }

    private function map_row(string $table, int $id): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE, ['sourcetable' => $table, 'sourceid' => $id, 'subkey' => ''],
            '*', MUST_EXIST);
    }

    /**
     * Apply once and expect success.
     *
     * @return array The runner result.
     */
    private function apply(): array {
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features']['learningplan']);
        return $result;
    }

    // The feature.

    public function test_depends_on_org_and_skills(): void {
        $this->assertSame(['org', 'skills'], (new lp_importer())->depends());
    }

    public function test_the_importer_passes_the_registry_rules(): void {
        $this->resetAfterTest();
        // The real importer, alone: it must fail only for its two dependencies, not for anything else.
        registry::set_testing_importers([new lp_importer()]);
        try {
            registry::load();
            $this->fail('the real importer cannot load without the org and skills importers');
        } catch (\local_sentientia_platform\bizlms\registry_error $e) {
            $this->assertEqualsCanonicalizing(['unknown_dependency:learningplan->org',
                'unknown_dependency:learningplan->skills'], $e->problems);
        }
        // The wrapper has the same sources, steps, targets, reasons and decisions and loads clean.
        registry::set_testing_importers([$this->contract_importer()]);
        $this->assertArrayHasKey('learningplan', registry::load());
    }

    public function test_paths_keep_their_ids_and_the_mapped_columns(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        $t = self::T;

        $this->assertEqualsCanonicalizing([10, 11, 12, 13, 14, 16, 17],
            array_map('intval', array_keys($DB->get_records(lp_importer::PATHS))));

        $p = $this->path_row(10);
        $this->assertSame('Path ten', $p->name);
        $this->assertSame('sn', $p->shortname);
        $this->assertSame('<p>Ten</p>', $p->description);
        $this->assertSame((string) FORMAT_HTML, (string) $p->descriptionformat);
        $this->assertSame('Learn it', $p->objective);
        $this->assertSame('/1/5', $p->open_path);
        $this->assertSame(5, (int) $p->costcenterid, 'the organisation at the path');
        $this->assertSame(5, (int) $p->departmentid, 'the second segment of a path two deep');
        $this->assertSame([1, 1], [(int) $p->status, (int) $p->visible]);
        $this->assertSame([$t + 10, $t + 99999], [(int) $p->startdate, (int) $p->enddate], 'the enrolment window');
        $this->assertSame([1, 1, 1], [(int) $p->approvalreqd, (int) $p->selfenrol, (int) $p->sequential]);
        $this->assertSame(1, (int) $p->learning_type);
        $this->assertSame(50, (int) $p->points);
        $this->assertSame(9, (int) $p->categoryid);
        $this->assertSame(7, (int) $p->certificateid);
        $this->assertSame([$this->u['creator'], $this->u['creator']], [(int) $p->usercreated, (int) $p->usermodified]);
        $this->assertSame([$t + 10, $t + 20], [(int) $p->timecreated, (int) $p->timemodified], 'source times are kept');
        // The adaptive journey engine stays off.
        $this->assertSame(0, (int) $p->adaptive_mode);
        $this->assertNull($p->score_threshold_low);
        $this->assertNull($p->score_threshold_high);
        // No skill or level mapped in this run: the plan is kept without them.
        $this->assertNull($p->skillid);
        $this->assertNull($p->levelid);

        // Hidden means archived, and a timemodified of 0 takes the created time.
        $hidden = $this->path_row(11);
        $this->assertSame([0, 0], [(int) $hidden->status, (int) $hidden->visible]);
        $this->assertSame([$t + 11, $t + 11], [(int) $hidden->timecreated, (int) $hidden->timemodified]);
        $this->assertSame(77, (int) $hidden->costcenterid);
        $this->assertNull($hidden->departmentid, 'a root path has no department');
        $this->assertNull($hidden->startdate, '0 means no date');

        $this->assertSame(255, \core_text::strlen($this->path_row(13)->name), 'a 255-character name fits whole');
    }

    public function test_tenant_fallback_order(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->contract_run(true);

        // The cost centre column fills an empty path; the enrolled learners' shared root fills an invalid one.
        $this->assertSame('/177', $this->path_row(12)->open_path);
        $this->assertSame('/177', $this->path_row(17)->open_path);
        // Nothing resolves: imported without a path, so only cross-tenant callers see it.
        $this->assertNull($this->path_row(16)->open_path);
        $this->assertSame(0, (int) $this->path_row(16)->costcenterid);
        $this->assertTrue($DB->record_exists(lp_importer::PATHS, ['id' => 16]));

        $methods = $report->to_array()['features']['learningplan']['steps']['learningplan.path']['tenant_methods'] ?? null;
        if ($methods !== null) {
            $this->assertArrayHasKey('unresolved', $methods);
            $this->assertArrayHasKey('fallback:costcenter', $methods);
            $this->assertArrayHasKey('fallback:enrolled_users', $methods);
        }
    }

    public function test_unresolved_tenant_can_be_skipped_by_decision(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->choices['tenant.unresolved.learningplan'] = 'skip';
        $this->apply();
        $this->assertFalse($DB->record_exists(lp_importer::PATHS, ['id' => 16]));
        $row = $this->map_row(lp_importer::SRC_PLAN, 16);
        $this->assertSame(['skipped', 'tenant_unresolved'], [$row->outcome, $row->reason]);
    }

    public function test_courses_are_deduplicated_ordered_and_flagged(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();

        $on10 = $DB->get_records(lp_importer::COURSES, ['pathid' => 10], 'sortorder ASC');
        $this->assertCount(2, $on10, 'the duplicate and the two unusable rows do not become rows');
        $first = array_values($on10)[0];
        $second = array_values($on10)[1];
        $this->assertSame([$this->c[1], 0, 1], [(int) $first->courseid, (int) $first->sortorder, (int) $first->mandatory]);
        $this->assertSame([$this->c[2], 1, 0], [(int) $second->courseid, (int) $second->sortorder, (int) $second->mandatory],
            'the duplicate with the lowest sort order wins, and "or" is optional');
        $this->assertSame(0, (int) $first->is_remedial + (int) $first->is_accelerator);
        $this->assertNull($first->remedial_for_courseid);

        $on11 = $DB->get_record(lp_importer::COURSES, ['pathid' => 11], '*', MUST_EXIST);
        $this->assertSame([0, 1], [(int) $on11->sortorder, (int) $on11->mandatory], 'NULL sort order, upper-case AND');

        $on12 = $DB->get_record(lp_importer::COURSES, ['pathid' => 12], '*', MUST_EXIST);
        $this->assertSame(0, (int) $on12->mandatory, 'no operator is optional');
        $this->assertSame([self::T + 12, self::T + 12], [(int) $on12->timecreated, (int) $on12->timemodified],
            'a course row with no created time takes the path created time');

        $merged = $this->map_row(lp_importer::SRC_COURSE, 3);
        $this->assertSame(['merged', 'dup_course'], [$merged->outcome, $merged->reason]);
        $this->assertSame((int) $this->map_row(lp_importer::SRC_COURSE, 2)->targetid, (int) $merged->targetid);
        $this->assertSame('no_course', $this->map_row(lp_importer::SRC_COURSE, 4)->reason);
        $this->assertSame('orphan_course', $this->map_row(lp_importer::SRC_COURSE, 5)->reason);
        $this->assertSame('orphan_plan', $this->map_row(lp_importer::SRC_COURSE, 8)->reason);
    }

    public function test_learner_rows_get_the_status_the_mapping_doc_says(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        $t = self::T;
        $u = $this->u;

        // Not completed and a course of the path is done: In progress. Enrolled by themselves.
        $inprogress = $this->user_row(10, $u['u1']);
        $this->assertSame([1, $u['u1'], $t + 30], [(int) $inprogress->status, (int) $inprogress->enrolledby,
            (int) $inprogress->timecreated]);
        $this->assertNull($inprogress->timecompleted);

        // Completed wins over the duplicate; the enrolment date is the earliest of the two rows.
        $completed = $this->user_row(10, $u['u2']);
        $this->assertSame([2, $t + 400, $t + 29, $u['creator']], [(int) $completed->status,
            (int) $completed->timecompleted, (int) $completed->timecreated, (int) $completed->enrolledby]);
        $merged = $this->map_row(lp_importer::SRC_USER, 3);
        $this->assertSame(['merged', 'dup_enrolment'], [$merged->outcome, $merged->reason]);

        // A deleted user's enrolment is history and is imported.
        $this->assertSame(0, (int) $this->user_row(10, $u['u4'])->status);
        // Not completed, no course done: Enrolled.
        $this->assertSame(0, (int) $this->user_row(11, $u['u2'])->status);
        // Completed without a date: Completed, no time.
        $nodate = $this->user_row(12, $u['u3']);
        $this->assertSame(2, (int) $nodate->status);
        $this->assertNull($nodate->timecompleted);

        $this->assertSame('orphan_plan', $this->map_row(lp_importer::SRC_USER, 7)->reason);
        $this->assertSame('orphan_user', $this->map_row(lp_importer::SRC_USER, 8)->reason);
        $this->assertSame(6, $DB->count_records(lp_importer::USERS));
    }

    public function test_not_completed_learners_can_all_be_enrolled_by_decision(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->choices['learningplan.not_completed'] = 'enrolled';
        $this->apply();
        $this->assertSame(0, (int) $this->user_row(10, $this->u['u1'])->status);
    }

    public function test_course_status_rows_are_kept_raw_and_the_latest_wins(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        $row = $DB->get_record(lp_importer::STATUS, ['pathid' => 10], '*', MUST_EXIST);
        $this->assertSame([$this->c[1], $this->u['u1'], 4, 100], [(int) $row->courseid, (int) $row->userid,
            (int) $row->status, (int) $row->percentage]);
        $this->assertSame([self::T + 5, self::T + 700], [(int) $row->startdate, (int) $row->completiondate]);
        $this->assertSame('dup_course_status', $this->map_row(lp_importer::SRC_STATUS, 1)->reason);
        $this->assertSame('orphan_plan', $this->map_row(lp_importer::SRC_STATUS, 3)->reason);
    }

    public function test_skill_and_level_resolve_through_the_skills_map(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // What the skills importer leaves: local_skill 3 became row 33, local_course_levels 2 kept its id.
        foreach ([['local_skill', 3, 33], ['local_course_levels', 2, 2]] as [$table, $sourceid, $targetid]) {
            $DB->insert_record(legacymap::TABLE, (object) ['feature' => 'skills', 'sourcetable' => $table,
                'sourceid' => $sourceid, 'subkey' => '', 'targettable' => 'local_sentientia_skill_seed',
                'targetid' => $targetid, 'outcome' => 'imported', 'reason' => null, 'detail' => null, 'runid' => 0,
                'timecreated' => time()]);
        }
        $this->apply();
        $path = $this->path_row(10);
        $this->assertSame([33, 2], [(int) $path->skillid, (int) $path->levelid]);
    }

    public function test_an_unknown_visible_value_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->plan(['id' => 60, 'name' => 'Strange', 'visible' => 7, 'open_path' => '/1']);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_learningplan.visible=7', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(lp_importer::PATHS), 'a blocked run writes nothing');
    }

    public function test_an_unknown_module_type_blocks_the_feature(): void {
        $this->contract_begin();
        $this->contract_seed();
        global $DB;
        $DB->set_field('local_learningplan_courses', 'moduletype', 'quiz', ['id' => 1]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_learningplan_courses.moduletype=quiz',
            implode(' ', $result['blockers']));
    }

    public function test_a_decision_the_code_does_not_implement_blocks_the_feature(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->choices['learningplan.enforce_rules'] = 'enforce';
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:learningplan.enforce_rules',
            implode(' ', $result['blockers']));
    }

    public function test_a_missing_decision_blocks_the_feature(): void {
        $this->contract_begin();
        $this->contract_seed();
        unset($this->choices['learningplan.dates_as']);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('missing_decision:learningplan.dates_as', implode(' ', $result['blockers']));
    }

    public function test_a_manual_change_is_not_undone_by_a_second_apply(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        $row = $this->user_row(10, $this->u['u1']);
        $DB->set_field(lp_importer::USERS, 'status', 2, ['id' => $row->id]);
        $DB->set_field(lp_importer::USERS, 'timecompleted', self::T + 900, ['id' => $row->id]);
        $this->contract_run(true);
        $after = $this->user_row(10, $this->u['u1']);
        $this->assertSame([2, self::T + 900], [(int) $after->status, (int) $after->timecompleted]);
    }

    public function test_the_cover_file_is_copied_once_and_the_source_is_left_alone(): void {
        $this->contract_begin();
        $this->contract_seed();
        $fs = get_file_storage();
        $systemid = \context_system::instance()->id;
        $fs->create_file_from_string(['contextid' => $systemid, 'component' => 'local_learningplan',
            'filearea' => lp_importer::FILEAREA, 'itemid' => 4242, 'filepath' => '/', 'filename' => 'cover.png'],
            base64_decode(self::PNG));

        $this->apply();
        $this->assertTrue($fs->file_exists($systemid, 'local_sentientia_learningpath', 'summaryfile', 10, '/', 'cover.png'),
            'copied to item id = path id, not the BizLMS draft item id');
        $this->assertTrue($fs->file_exists($systemid, 'local_learningplan', 'summaryfile', 4242, '/', 'cover.png'),
            'the legacy file stays');
        $this->assertNotNull(path_manager::cover_url(10));
        $this->assertNull(path_manager::cover_url(11), 'a path without a cover has none');

        $this->contract_run(true);
        $this->assertCount(1, $fs->get_area_files($systemid, 'local_sentientia_learningpath', 'summaryfile', 10, 'id', false));
    }

    public function test_no_enrolment_completion_or_message_comes_out_of_the_import(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $counts = [];
        foreach (['user_enrolments', 'enrol', 'role_assignments', 'course_completions', 'groups_members',
                  'cohort_members'] as $table) {
            $counts[$table] = $DB->count_records($table);
        }
        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();
        $this->apply();
        foreach ($counts as $table => $count) {
            $this->assertSame($count, $DB->count_records($table), $table . ' is untouched');
        }
        $this->assertSame(0, $events->count());
        $this->assertSame(0, $messages->count());
        $this->assertSame(0, $DB->count_records('local_sentientia_lp_adaptive_log'), 'the adaptive engine did not run');
        // The import never flips a flag: the learner page stays off.
        $this->setAdminUser();
        $this->assertFalse(learner_paths::enabled());
    }

    public function test_the_static_scan_finds_nothing_in_this_plugins_importer_code(): void {
        $dir = \core_component::get_component_directory('local_sentientia_learningpath') . '/classes/bizlms';
        $files = \local_sentientia_platform\tests\bizlms\static_scanner::php_files($dir);
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $findings = \local_sentientia_platform\tests\bizlms\static_scanner::scan(
                (string) file_get_contents($file), false, false);
            $this->assertSame([], $findings, basename($file));
        }
    }
}
