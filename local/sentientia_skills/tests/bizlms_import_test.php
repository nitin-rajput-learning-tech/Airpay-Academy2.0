<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer as importer_contract_subject;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_skills\bizlms\importer;
use local_sentientia_skills\tests\bizlms\stub_dependency;

/**
 * The skills feature of the BizLMS import (ADR-032, mapping doc section 14).
 *
 * The importer contract (dry run writes nothing, apply reconciles, a second apply is a no-op, resume after a
 * crash, a changed source is detected, a PRESERVE collision blocks and a header copy is adopted, no side
 * effects, privacy declares every new person column) runs against a seed built from the mapping doc's fixture
 * section, then the feature's own rules are checked one by one: the level map the owner signs (and the block
 * when it is missing), the category merge, the catalogue tenant, the course links, the learner levels and their
 * history (current completions and the recompletion archive), the learner that already has a native row, the
 * interests, and the catalogue label that reads the imported level name.
 *
 * Numbers a test may rely on (legacy ids, so the seed and the assertions read the same):
 *
 *  course levels   5 Basic /1 (code L-BASIC), 9 Intermediate /177, 12 Advanced '0'+cost centre 77 (blank code),
 *                  14 "Basic duplicate" '0' and no cost centre (code l-basic, a repeat). Owner map 5=2, 9=3, 12=4, 14=1.
 *  categories      1 " LEADERSHIP " (folds into the seeded Leadership), 2 /1, 3 /177 (has a parent), 4 '0'+77,
 *                  5 '0'+none, 6 blank (skipped).
 *  skills          1 Python, 2 Regional Law (/177), 3 category 999 (no such category), 4 blank category,
 *                  5 blank name (skipped), 6 Python again, 7 "Team Management" (the name of a seeded skill),
 *                  8 a 150-character name.
 *  courses         cA skill 1 level 9, cB skill 1 level 5, cC skill 2 (/177), cD skill 999, cE none, cF skill 3
 *                  level 77 (not in the map), cG skill 5, cH skill 7 level 14.
 *  completions     u1 cB at T1 and cA at T2 (cF in progress), u2 cC at T3, u3 (deleted) cA, u4 cE (no skill),
 *                  u5 cH at T1, u6 cF in progress plus an archived cA, an unknown user, u7 an archive only.
 *  interests       rows 1-8, see seed_interests().
 *
 * @package    local_sentientia_skills
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_skills\bizlms\importer
 * @covers     \local_sentientia_skills\bizlms\levels_step
 * @covers     \local_sentientia_skills\bizlms\categories_step
 * @covers     \local_sentientia_skills\bizlms\skills_step
 * @covers     \local_sentientia_skills\bizlms\course_links_step
 * @covers     \local_sentientia_skills\bizlms\user_skills_step
 * @covers     \local_sentientia_skills\bizlms\interests_step
 * @covers     \local_sentientia_skills\bizlms\level_map
 *
 * @group local_sentientia_skills
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture {
        setUp as protected legacy_fixture_setup;
    }
    use importer_contract;
    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** @var int[] Seeded user ids by label (u1 ... u7, deleted, orphan). */
    private array $u = [];

    /** @var int[] Seeded course ids by label (a ... h). */
    private array $c = [];

    /** @var int[] Lowest course_completions id of each learner's group, by label: the group's map key. */
    private array $firstcompletion = [];

    protected static function legacy_fixture_definition(): array {
        $costcenter = static fn(): \xmldb_field => new \xmldb_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        return [
            'xml' => __DIR__ . '/fixtures/bizlms/skillrepository.install.xml',
            // Production-only columns: skillrepository db/upgrade.php:38-43 and the indexes at :60-101.
            'extrafields' => [
                'local_skill' => [$costcenter()],
                'local_skill_categories' => [$costcenter()],
                'local_course_levels' => [$costcenter()],
            ],
        ];
    }

    protected function setUp(): void {
        $this->legacy_fixture_setup();
        $this->ensure_bizlms_schema();
        $this->ensure_course_columns();
    }

    // The contract.

    protected function contract_importer(): importer_contract_subject {
        return new importer();
    }

    /**
     * Register the importer together with stand-ins for the two features it depends on.
     *
     * @return importer_contract_subject
     */
    protected function contract_begin(): importer_contract_subject {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([new stub_dependency('org'), new stub_dependency('recompletion'), $importer]);
        return $importer;
    }

    protected function contract_decisions(): decisions {
        return $this->decisions_with([]);
    }

    protected function contract_seed(): void {
        $this->seed_all();
    }

    protected function contract_mutate_source(): void {
        global $DB;
        // The failed run stops in the first step that has two batches, the course levels, so that is the source
        // that changes (a step's source is compared when that step is resumed). The new id is in the owner's map
        // already, so the preflight passes and the drift check is what refuses.
        $DB->import_record('local_course_levels', (object) [
            'id' => 20, 'name' => 'Late level', 'code' => 'LATE', 'open_path' => '/1', 'usercreated' => $this->admin_id(),
            'timecreated' => self::T0, 'usermodified' => 0, 'timemodified' => 0, 'sortorder' => 50, 'costcenterid' => 1,
        ]);
    }

    protected function contract_collision(): ?array {
        return ['table' => 'local_sentientia_course_levels', 'row' => (object) [
            'id' => 5, 'name' => 'Somebody else', 'code' => 'SOMEONE', 'open_path' => null, 'proficiency' => null,
            'sortorder' => null, 'timecreated' => 5, 'timemodified' => 5,
        ]];
    }

    protected function contract_adoptable(): ?array {
        // What migrate_all.php wrote: the same id, name and timecreated as the source row.
        return ['table' => 'local_sentientia_course_levels', 'sourcetable' => 'local_course_levels', 'row' => (object) [
            'id' => 9, 'name' => 'Intermediate', 'code' => 'HEADER-COPY', 'open_path' => null, 'proficiency' => null,
            'sortorder' => null, 'timecreated' => self::T0 + 9, 'timemodified' => 7,
        ]];
    }

    protected function contract_user_columns(): array {
        return [
            'local_sentientia_user_skills' => ['userid'],
            'local_sentientia_user_skill_hist' => ['userid', 'changed_by_userid'],
            'local_sentientia_skill_interest' => ['userid'],
        ];
    }

    /**
     * Put the import's own rows back to nothing between two runs of one test, and leave the rest.
     *
     * The platform's version deletes every row of every target table. The skills targets also hold the platform's
     * seed categories and skills, which the category merge reads: a seed the clear removed would turn the second run's
     * fold into an insert and make two runs differ for a reason that has nothing to do with resume.
     *
     * @param importer_contract_subject $importer
     * @return void
     */
    protected function contract_clear_import(importer_contract_subject $importer): void {
        global $DB;
        foreach ($importer->target_tables() as $table) {
            $ids = $DB->get_fieldset_select(legacymap::TABLE, 'targetid',
                "targettable = :t AND outcome IN ('imported', 'adopted') AND targetid IS NOT NULL", ['t' => $table]);
            if ($ids) {
                [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $DB->delete_records_select($table, "id {$insql}", $params);
            }
        }
        $DB->delete_records(legacymap::TABLE);
        $DB->delete_records('local_sentientia_legacystep');
        $DB->delete_records('local_sentientia_legacyrun');
        unset_config('bizlms_complete_' . $importer->feature(), 'local_sentientia_platform');
        unset_config('bizlms_tripped_' . $importer->feature(), 'local_sentientia_platform');
    }

    /**
     * The contract's "not applicable" test drops every table the importer claims. This importer also claims
     * course_completions (a core table, only while a BizLMS skill table exists), which a test must never drop, so
     * the test drops the four legacy tables and checks the same thing.
     */
    public function test_contract_not_applicable_without_tables(): void {
        global $DB;
        $importer = $this->contract_begin();
        foreach (['local_skill_categories', 'local_skill', 'local_course_levels', 'local_interested_skills'] as $table) {
            self::drop_legacy_table($table);
        }
        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], 'nothing applicable is not a failure');
        $this->assertSame('not_applicable', $result['features'][$importer->feature()]);
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertFalse(legacymap::feature_complete($importer->feature()), 'no marker for a feature that did nothing');
        $this->assertArrayNotHasKey('course_completions', $importer->sources(),
            'a site without BizLMS skill tables never claims its own completions');
    }

    // The feature.

    public function test_the_level_map_must_be_written_out_before_anything_runs(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();

        // The signed decisions file today: the rule is approved, the csv is null.
        $nocsv = $this->decisions_with(['csv' => null]);
        [$result] = $this->contract_run(true, ['decisions' => $nocsv]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('level_proficiency_csv_missing', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE), 'nothing is written while the map is missing');
        $this->assertSame(0, $DB->count_records('local_sentientia_course_levels'));

        // A map that misses a level names it, and the suggestion applies the owner's heuristic to the level names.
        $partial = $this->decisions_with(['csv' => "5,2\n9,3"]);
        [$result] = $this->contract_run(true, ['decisions' => $partial]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('level_proficiency_csv_incomplete:12,14', implode(' ', $result['blockers']));

        // A value out of range is refused, not clamped.
        $range = $this->decisions_with(['csv' => "5,2\n9,3\n12,4\n14,9"]);
        [$result] = $this->contract_run(true, ['decisions' => $range]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('level_proficiency_csv_invalid', implode(' ', $result['blockers']));
    }

    public function test_the_signed_decisions_file_blocks_the_feature_until_the_level_map_is_filled(): void {
        global $DB;
        $path = \core_component::get_component_directory('local_sentientia_platform')
            . '/tests/fixtures/bizlms/bizlms-import-decisions.copy.json';
        if (!is_readable($path)) {
            $this->markTestSkipped('the platform\'s copy of the signed decisions file is not deployed');
        }
        $signed = decisions::load($path);
        $rule = $signed->get('skills.level_proficiency');
        if (is_array($rule) && ($rule['csv'] ?? null) !== null) {
            $this->markTestSkipped('the owner has filled in the level map; the block no longer applies');
        }

        $importer = $this->contract_begin();
        $this->contract_seed();
        // Every owner choice the importer declares is in the signed file, accepted (a missing one would block too).
        foreach ($importer->decisions() as $decision) {
            $this->assertTrue($signed->has($decision->key), $decision->key . ' is an accepted decision in the signed file');
        }
        [$result] = $this->contract_run(true, ['decisions' => $signed]);
        $this->assertSame(1, $result['exit'], 'the skills feature does not run on the signed file as it stands');
        $this->assertStringContainsString('level_proficiency_csv_missing', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertSame(0, $DB->count_records('local_sentientia_course_levels'));
    }

    /**
     * Owner decision LRN-07 (2026-10-07): the signed file carries the concrete map, so skills (and learningplan, which
     * depends on it) can run at Stage B. The map is the owner's name rule applied to the 17 April levels and reviewed
     * by hand; the preflight warns whenever an entry differs from the rule.
     */
    public function test_the_signed_decisions_file_carries_a_complete_level_map(): void {
        $path = \core_component::get_component_directory('local_sentientia_platform')
            . '/tests/fixtures/bizlms/bizlms-import-decisions.copy.json';
        if (!is_readable($path)) {
            $this->markTestSkipped('the platform\'s copy of the signed decisions file is not deployed');
        }
        $rule = decisions::load($path)->get('skills.level_proficiency');
        $this->assertIsArray($rule);
        $this->assertNotNull($rule['csv'] ?? null, 'the owner\'s map is written out');

        $parsed = bizlms\level_map::parse($rule);
        $this->assertSame([], $parsed['problems'], 'every pair is a level id and a proficiency of 1 to 5');
        $this->assertSame(
            [1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 1, 7 => 2, 8 => 3, 9 => 4, 10 => 5, 11 => 2, 12 => 3, 13 => 4, 14 => 5,
                15 => 1, 16 => 2, 17 => 3, 18 => 4],
            $parsed['map'], 'the 17 April levels (ids 1-5 and 7-18)');
        $this->assertSame(1, $parsed['map'][5], 'a general, non-levelled label takes the default 1');
        $this->assertSame(2, $parsed['map'][16],
            'level 16 is the plural of "basic": the literal word match gives 1, the review sets 2, the first rung of the /177 ladder');
        $this->assertSame(bizlms\level_map::suggest('Basic', $rule), $parsed['map'][16],
            'the rule itself agrees once the plural is read as the rule word');
    }

    public function test_a_csv_entry_that_differs_from_the_owners_rule_is_reported_on_every_run(): void {
        $this->contract_begin();
        $this->contract_seed();

        // Seeded levels: 5 Basic, 9 Intermediate, 12 Advanced, 14 "Basic duplicate". The rule gives 2, 3, 4, 2.
        // The default decisions map 14 to 1 (a reviewed deviation), so it is named.
        [$result, $report] = $this->contract_run(false);
        $this->assertContains($result['exit'], [0, 2]);
        $text = json_encode($report->to_array());
        $this->assertStringContainsString('level_proficiency_differs_from_rule:14', $text);
        $this->assertStringNotContainsString('level_proficiency_differs_from_rule:5', $text);

        // A csv that is the rule exactly has nothing to report.
        $rule = $this->decisions_with(['csv' => "5,2\n9,3\n12,4\n14,2\n20,5"]);
        [$result, $report] = $this->contract_run(false, ['decisions' => $rule]);
        $this->assertContains($result['exit'], [0, 2]);
        $this->assertStringNotContainsString('level_proficiency_differs_from_rule', json_encode($report->to_array()));

        // Two entries that differ are both named, in level order, and the run is still allowed to go on: it is
        // a warning (the owner reviews it), never a block.
        $two = $this->decisions_with(['csv' => "5,3\n9,3\n12,1\n14,2"]);
        [$result, $report] = $this->contract_run(false, ['decisions' => $two]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertStringContainsString('level_proficiency_differs_from_rule:5,12', json_encode($report->to_array()));
    }

    public function test_level_map_parses_text_and_json_and_suggests_from_the_names(): void {
        $parsed = bizlms\level_map::parse(['csv' => "levelid,proficiency\n5, 2\n9=3;12:4"]);
        $this->assertSame([5 => 2, 9 => 3, 12 => 4], $parsed['map']);
        $this->assertSame([], $parsed['problems']);

        $json = bizlms\level_map::parse(['csv' => [5 => 2, '9' => '3']]);
        $this->assertSame([5 => 2, 9 => 3], $json['map']);

        $this->assertSame(['level_proficiency_csv_missing'], bizlms\level_map::parse(['csv' => null])['problems']);
        $this->assertSame(['level_proficiency_csv_missing'], bizlms\level_map::parse(null)['problems']);
        $conflict = bizlms\level_map::parse(['csv' => "5,2\n5,3"]);
        $this->assertSame(['level_proficiency_csv_conflict:5'], $conflict['problems']);

        $rules = $this->level_rules();
        $this->assertSame(1, bizlms\level_map::suggest('Awareness', $rules));
        $this->assertSame(2, bizlms\level_map::suggest('Foundation course', $rules));
        $this->assertSame(3, bizlms\level_map::suggest('intermediate', $rules));
        $this->assertSame(4, bizlms\level_map::suggest('Advanced Level', $rules));
        $this->assertSame(5, bizlms\level_map::suggest('EXPERT', $rules));
        $this->assertSame(1, bizlms\level_map::suggest('Something else', $rules), 'the default is 1');

        // A level the map does not name teaches level 1, as BizLMS courses without a level did.
        $this->assertSame(1, bizlms\level_map::proficiency([5 => 2], 0));
        $this->assertSame(1, bizlms\level_map::proficiency([5 => 2], 77));
        $this->assertSame(2, bizlms\level_map::proficiency([5 => 2], 5));
    }

    public function test_the_seed_imports_as_the_mapping_says(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        $seedcats = $DB->count_records('local_sentientia_skill_cats');
        $seedskills = $DB->count_records('local_sentientia_skills');
        $seedleadership = (int) $DB->get_field('local_sentientia_skill_cats', 'id', ['name' => 'Leadership']);
        $seedteam = (int) $DB->get_field('local_sentientia_skills', 'id', ['name' => 'Team Management']);

        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features'][$importer->feature()]);

        $this->assert_levels();
        $this->assert_categories($seedcats, $seedleadership);
        $this->assert_skills($seedskills, $seedleadership, $seedteam);
        $this->assert_course_links();
        $this->assert_learner_skills();
        $this->assert_interests();

        // The owner sees every row that did not import, by reason, in the report; ids and codes only.
        $tally = $report->to_array()['features']['skills'];
        $this->assertArrayHasKey('steps', $tally);
    }

    public function test_the_catalogue_tenant_follows_the_row_then_the_cost_centre_then_nothing(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);

        $path = fn(string $table, int $legacyid): ?string => $DB->get_field($table, 'open_path',
            ['id' => $this->target('local_skill_categories', $legacyid)]);
        $this->assertSame('/1', $path('local_sentientia_skill_cats', 2), 'a usable row path is kept');
        $this->assertSame('/177', $path('local_sentientia_skill_cats', 3));
        $this->assertSame('/77', $path('local_sentientia_skill_cats', 4), "'0' falls back to the cost centre");
        $this->assertNull($path('local_sentientia_skill_cats', 5), "'0' with no cost centre is the shared catalogue");

        // The decision says what a row with no tenant does. 'skip' leaves it in the legacy table with a reason.
        $this->contract_clear_import($this->contract_importer());
        [$skipped] = $this->contract_run(true, ['decisions' => $this->decisions_with([], ['tenant.unresolved.skills' => 'skip'])]);
        $this->assertContains($skipped['exit'], [0, 2], implode('; ', $skipped['blockers']));
        $row = $this->mapped('local_skill_categories', 5);
        $this->assertSame('skipped', $row->outcome);
        $this->assertSame('tenant_unresolved', $row->reason);
        // A level is never skipped for that: course.open_level points at its id.
        $this->assertSame('imported', $this->mapped('local_course_levels', 14)->outcome);
    }

    public function test_a_learner_with_a_native_row_keeps_it_and_the_history_is_still_written(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);

        // Put u6 back to "before the import", with a row the learner earned natively in the window (cron was off,
        // but an admin may have rated the learner): level 2, source 'self'.
        $skill1 = $this->target('local_skill', 1);
        $group = $this->firstcompletion['u6'];
        $DB->delete_records('local_sentientia_user_skills', ['userid' => $this->u['u6']]);
        $DB->delete_records('local_sentientia_user_skill_hist', ['userid' => $this->u['u6']]);
        $DB->delete_records(legacymap::TABLE, ['sourcetable' => '#course_completions.userid', 'sourceid' => $group]);
        $DB->insert_record('local_sentientia_user_skills', (object) [
            'userid' => $this->u['u6'], 'skillid' => $skill1, 'current_level' => 2, 'source' => 'self',
            'source_id' => null, 'timecreated' => self::T0 + 5, 'timemodified' => self::T0 + 5,
        ]);

        [$again] = $this->contract_run(true);
        $this->assertContains($again['exit'], [0, 2], implode('; ', $again['blockers']));

        $native = $DB->get_record('local_sentientia_user_skills', ['userid' => $this->u['u6'], 'skillid' => $skill1], '*', MUST_EXIST);
        $this->assertSame(2, (int) $native->current_level, 'a native level is never overwritten');
        $this->assertSame('self', $native->source);
        $this->assertSame(1, $DB->count_records('local_sentientia_user_skills', ['userid' => $this->u['u6']]));

        $entry = $this->mapped('#course_completions.userid', $group);
        $this->assertSame('folded', $entry->outcome);
        $this->assertSame('native_row_kept', $entry->reason);
        $this->assertSame((int) $native->id, (int) $entry->targetid);

        $history = $DB->get_records('local_sentientia_user_skill_hist', ['userid' => $this->u['u6']]);
        $this->assertCount(1, $history, 'the missing history row is still written');
        $row = reset($history);
        $this->assertSame(0, (int) $row->previous_level);
        $this->assertSame(3, (int) $row->new_level);
        $this->assertSame('import', $row->source);
    }

    public function test_the_source_label_is_the_owners(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true, ['decisions' => $this->decisions_with([], ['skills.source_label' => 'course'])]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_user_skills', ['source' => 'import']));
        $this->assertGreaterThan(0, $DB->count_records('local_sentientia_user_skills', ['userid' => $this->u['u1'], 'source' => 'course']));
    }

    public function test_history_from_the_archive_is_the_owners_choice(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true, ['decisions' => $this->decisions_with([], ['skills.history_from_archive' => false])]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        // u6 has a group (an in-progress current row) and an archived completion: with the archive off it earns nothing.
        $this->assertSame(0, $DB->count_records('local_sentientia_user_skills', ['userid' => $this->u['u6']]));
        $this->assertSame('archived', $this->mapped('#course_completions.userid', $this->firstcompletion['u6'])->outcome);
        $this->assertSame(1, $DB->count_records('local_sentientia_user_skills', ['userid' => $this->u['u1']]));
    }

    public function test_the_preflight_reports_what_the_owner_should_see(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->contract_run(false);
        $this->assertContains($result['exit'], [0, 2]);
        $pf = $report->to_array();
        $text = json_encode($pf);
        // Facts counted and never named: ids and counts only.
        $this->assertStringContainsString('category_parent_ignored', $text);
        $this->assertStringContainsString('course_skill_dangling', $text);
        $this->assertStringContainsString('interest_duplicate_rows', $text);
        $this->assertStringContainsString('archive_only_learners_not_imported', $text);
        $this->assertStringContainsString('declined_rows:local_skillmatrix', $text);
    }

    public function test_the_catalogue_label_reads_the_imported_level_name(): void {
        if (!class_exists('\local_sentientia_catalog\catalog_manager')) {
            $this->markTestSkipped('local_sentientia_catalog is not installed');
        }
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);

        // course.open_level holds the legacy id; the label is the imported level's own name, not the old 1-3 map.
        $this->assertSame('Intermediate', \local_sentientia_catalog\catalog_manager::course_level_label(9));
        $this->assertSame('Basic', \local_sentientia_catalog\catalog_manager::course_level_label(5));
        $this->assertSame('', \local_sentientia_catalog\catalog_manager::course_level_label(2), 'no level 2 was imported');
        $this->assertSame('', \local_sentientia_catalog\catalog_manager::course_level_label(0));
    }

    // Assertions per table.

    /**
     * @return void
     */
    private function assert_levels(): void {
        global $DB;
        $levels = $DB->get_records('local_sentientia_course_levels', null, 'id ASC');
        $this->assertSame([5, 9, 12, 14], array_map('intval', array_keys($levels)), 'the legacy ids are kept');

        $this->assertSame('Basic', $levels[5]->name);
        $this->assertSame('L-BASIC', $levels[5]->code);
        $this->assertSame('/1', $levels[5]->open_path);
        $this->assertSame(2, (int) $levels[5]->proficiency);
        $this->assertSame(10, (int) $levels[5]->sortorder);
        $this->assertSame(self::T0 + 5, (int) $levels[5]->timecreated, 'source timestamps are kept');
        $this->assertSame(self::T0 + 105, (int) $levels[5]->timemodified);

        $this->assertSame(3, (int) $levels[9]->proficiency);
        $this->assertSame('/177', $levels[9]->open_path);

        $this->assertSame('level_12', $levels[12]->code, 'a blank code is generated');
        $this->assertSame('/77', $levels[12]->open_path, 'the cost centre stands in for a path of 0');
        $this->assertSame(4, (int) $levels[12]->proficiency);

        $this->assertSame('l-basic_14', $levels[14]->code, 'a repeated code is made unique');
        $this->assertNull($levels[14]->open_path, 'no path and no cost centre: the shared catalogue, pathless');
        $this->assertSame((int) $levels[14]->timecreated, (int) $levels[14]->timemodified, 'no modified time: the created time');
        $this->assertSame(1, (int) $levels[14]->proficiency);

        foreach ([5, 9, 12, 14] as $id) {
            $entry = $this->mapped('local_course_levels', $id);
            $this->assertSame('imported', $entry->outcome);
            $this->assertSame($id, (int) $entry->targetid, 'PRESERVE: the target id is the legacy id');
        }

        // After finalise a native level takes an id above the legacy maximum.
        $new = $DB->insert_record('local_sentientia_course_levels', (object) [
            'name' => 'Native', 'code' => 'NATIVE', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertGreaterThan(14, (int) $new);
        $DB->delete_records('local_sentientia_course_levels', ['id' => $new]);
    }

    /**
     * @param int $seedcats Categories before the import.
     * @param int $seedleadership The seeded Leadership category.
     * @return void
     */
    private function assert_categories(int $seedcats, int $seedleadership): void {
        global $DB;

        $fold = $this->mapped('local_skill_categories', 1);
        $this->assertSame('folded', $fold->outcome, 'an exact name match (ignoring case and spaces) folds into the seed');
        $this->assertSame('seed_category_match', $fold->reason);
        $this->assertSame($seedleadership, (int) $fold->targetid);

        $blank = $this->mapped('local_skill_categories', 6);
        $this->assertSame('skipped', $blank->outcome);
        $this->assertSame('empty_name', $blank->reason);

        $risk = $DB->get_record('local_sentientia_skill_cats', ['id' => $this->target('local_skill_categories', 2)], '*', MUST_EXIST);
        $this->assertSame('Risk Culture', $risk->name);
        $this->assertSame('RISK', $risk->idnumber, 'the legacy shortname becomes idnumber');
        $this->assertSame('/1', $risk->open_path);
        $this->assertSame(2, (int) $risk->sort_order, 'the char sort order becomes an integer');
        $this->assertSame(self::T0 + 2, (int) $risk->timecreated);

        $regional = $DB->get_record('local_sentientia_skill_cats', ['id' => $this->target('local_skill_categories', 3)], '*', MUST_EXIST);
        $this->assertSame('/177', $regional->open_path);
        $this->assertSame('imported', $this->mapped('local_skill_categories', 3)->outcome, 'a parent was never written by BizLMS: ignored');

        $shared = $DB->get_record('local_sentientia_skill_cats', ['id' => $this->target('local_skill_categories', 4)], '*', MUST_EXIST);
        $this->assertSame(0, (int) $shared->sort_order, 'a sort order that is not a number becomes 0');

        // Four categories import (2, 3, 4, 5) and the fallback for skills that lost theirs; the seed is untouched.
        $fallback = $this->mapped('local_skill_categories', 1, 'fallback');
        $this->assertSame('imported', $fallback->outcome);
        $this->assertSame($seedcats + 5, $DB->count_records('local_sentientia_skill_cats'));
        $fallbackrow = $DB->get_record('local_sentientia_skill_cats', ['id' => $fallback->targetid], '*', MUST_EXIST);
        $this->assertSame('Imported - uncategorised', $fallbackrow->name);
        $this->assertNull($fallbackrow->open_path);
        $this->assertSame('Leadership', $DB->get_field('local_sentientia_skill_cats', 'name', ['id' => $seedleadership]));
    }

    /**
     * @param int $seedskills Skills before the import.
     * @param int $seedleadership The seeded Leadership category.
     * @param int $seedteam The seeded skill "Team Management".
     * @return void
     */
    private function assert_skills(int $seedskills, int $seedleadership, int $seedteam): void {
        global $DB;
        $fallbackcat = (int) $this->mapped('local_skill_categories', 1, 'fallback')->targetid;

        $python = $DB->get_record('local_sentientia_skills', ['id' => $this->target('local_skill', 1)], '*', MUST_EXIST);
        $this->assertSame('Python', $python->name);
        $this->assertSame('PY', $python->idnumber);
        $this->assertSame('<p>General purpose</p>', $python->description, 'the description keeps its HTML');
        $this->assertSame('/1', $python->open_path);
        $this->assertSame((int) $this->target('local_skill_categories', 2), (int) $python->categoryid);
        $this->assertSame(5, (int) $python->max_level);
        $this->assertSame(self::T0 + 1, (int) $python->timecreated);

        $this->assertSame((int) $fallbackcat, (int) $DB->get_field('local_sentientia_skills', 'categoryid',
            ['id' => $this->target('local_skill', 3)]), 'a skill whose category is gone lands in the fallback');
        $this->assertSame((int) $fallbackcat, (int) $DB->get_field('local_sentientia_skills', 'categoryid',
            ['id' => $this->target('local_skill', 4)]), 'so does one whose category was skipped');

        $blank = $this->mapped('local_skill', 5);
        $this->assertSame('skipped', $blank->outcome);
        $this->assertSame('empty_name', $blank->reason);

        // Two skills called Python are two rows, as they were in BizLMS.
        $this->assertNotSame($this->target('local_skill', 1), $this->target('local_skill', 6));
        $this->assertSame(2, $DB->count_records('local_sentientia_skills', ['name' => 'Python']));

        // Never merged into a seeded skill, and its category is the seed it folded into.
        $team = $this->target('local_skill', 7);
        $this->assertNotSame($seedteam, $team);
        $this->assertSame(2, $DB->count_records('local_sentientia_skills', ['name' => 'Team Management']));
        $this->assertSame($seedleadership, (int) $DB->get_field('local_sentientia_skills', 'categoryid', ['id' => $team]));

        $long = $DB->get_field('local_sentientia_skills', 'name', ['id' => $this->target('local_skill', 8)]);
        $this->assertSame(str_repeat('x', 150), $long);

        // Seven skills import (1, 2, 3, 4, 6, 7, 8); the seed is untouched.
        $this->assertSame($seedskills + 7, $DB->count_records('local_sentientia_skills'));
        $this->assertSame(1, $DB->count_records('local_sentientia_skills', ['id' => $seedteam]));
    }

    /**
     * @return void
     */
    private function assert_course_links(): void {
        global $DB;
        $links = function (int $legacyskill): array {
            global $DB;
            $rows = $DB->get_records('local_sentientia_course_skills', ['skillid' => $this->target('local_skill', $legacyskill)],
                'courseid ASC');
            $out = [];
            foreach ($rows as $row) {
                $out[(int) $row->courseid] = (int) $row->teaches_level;
            }
            return $out;
        };

        // Skill 1: cA at level 9 (map 3) and cB at level 5 (map 2). Skill 2: cC has no level, so 1. Skill 3: cF's level
        // is not in the map, so 1. Skill 7: cH at level 14 (map 1).
        $this->assertSame([$this->c['a'] => 3, $this->c['b'] => 2], $links(1));
        $this->assertSame([$this->c['c'] => 1], $links(2));
        $this->assertSame([$this->c['f'] => 1], $links(3));
        $this->assertSame([$this->c['h'] => 1], $links(7));
        $this->assertSame(self::T0 + 51, (int) $DB->get_field('local_sentientia_course_skills', 'timecreated',
            ['courseid' => $this->c['a']]), 'the link is as old as the course');

        // No link for a dangling skill, for no skill, or for a skill that was not imported.
        foreach (['d', 'e', 'g'] as $label) {
            $this->assertSame(0, $DB->count_records('local_sentientia_course_skills', ['courseid' => $this->c[$label]]), $label);
        }
        $this->assertSame(5, $DB->count_records('local_sentientia_course_skills', [
            'skillid' => $this->target('local_skill', 1)]) + $DB->count_records('local_sentientia_course_skills', [
            'skillid' => $this->target('local_skill', 2)]) + $DB->count_records('local_sentientia_course_skills', [
            'skillid' => $this->target('local_skill', 3)]) + $DB->count_records('local_sentientia_course_skills', [
            'skillid' => $this->target('local_skill', 7)]));

        // The accounting unit is the legacy skill: one primary row per skill, sub-rows per further course.
        $this->assertSame('imported', $this->mapped('#local_skill.courses', 1)->outcome);
        $this->assertSame('imported', $this->mapped('#local_skill.courses', 1, 'course:' . $this->c['b'])->outcome);
        foreach ([4, 6, 8] as $legacy) {
            $entry = $this->mapped('#local_skill.courses', $legacy);
            $this->assertSame('archived', $entry->outcome);
            $this->assertSame('no_course_links', $entry->reason);
        }
        $blank = $this->mapped('#local_skill.courses', 5);
        $this->assertSame('skipped', $blank->outcome);
        $this->assertSame('skill_not_imported', $blank->reason);
    }

    /**
     * @return void
     */
    private function assert_learner_skills(): void {
        global $DB;
        $skill1 = $this->target('local_skill', 1);

        // u1: cB (level 2) at T1, then cA (level 3) at T2. cF is in progress and earns nothing.
        $rows = $DB->get_records('local_sentientia_user_skills', ['userid' => $this->u['u1']]);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame($skill1, (int) $row->skillid);
        $this->assertSame(3, (int) $row->current_level);
        $this->assertSame('import', $row->source);
        $this->assertSame($this->c['a'], (int) $row->source_id, 'the course that set the highest level');
        $this->assertSame(self::T0 + 1000, (int) $row->timecreated, 'the earliest completion');
        $this->assertSame(self::T0 + 2000, (int) $row->timemodified, 'the completion that set the level');

        $history = array_values($DB->get_records('local_sentientia_user_skill_hist', ['userid' => $this->u['u1']], 'timecreated ASC'));
        $this->assertCount(2, $history);
        $this->assertSame([0, 2, self::T0 + 1000, $this->c['b']],
            [(int) $history[0]->previous_level, (int) $history[0]->new_level, (int) $history[0]->timecreated, (int) $history[0]->source_id]);
        $this->assertSame([2, 3, self::T0 + 2000, $this->c['a']],
            [(int) $history[1]->previous_level, (int) $history[1]->new_level, (int) $history[1]->timecreated, (int) $history[1]->source_id]);
        $this->assertNull($history[0]->changed_by_userid, 'nobody acted: the import is not a person');
        $this->assertSame('import', $history[1]->source);

        // u2: one completion of a course that teaches level 1 (no level).
        $u2 = $DB->get_record('local_sentientia_user_skills', ['userid' => $this->u['u2']], '*', MUST_EXIST);
        $this->assertSame($this->target('local_skill', 2), (int) $u2->skillid);
        $this->assertSame(1, (int) $u2->current_level);
        $this->assertSame(self::T0 + 3000, (int) $u2->timecreated);

        // u5: skill 7 at level 1.
        $u5 = $DB->get_record('local_sentientia_user_skills', ['userid' => $this->u['u5']], '*', MUST_EXIST);
        $this->assertSame($this->target('local_skill', 7), (int) $u5->skillid);

        // u6: an in-progress current row and an archived completion of cA: the archive is history.
        $u6 = $DB->get_record('local_sentientia_user_skills', ['userid' => $this->u['u6']], '*', MUST_EXIST);
        $this->assertSame(3, (int) $u6->current_level);
        $this->assertSame(self::T0 + 900, (int) $u6->timecreated);
        $this->assertSame(self::T0 + 900, (int) $u6->timemodified);
        $this->assertSame(1, $DB->count_records('local_sentientia_user_skill_hist', ['userid' => $this->u['u6']]));

        // Nothing for the deleted learner, the learner with no skill course, the unknown user or the archive-only one.
        foreach (['u3', 'u4', 'orphan', 'u7'] as $label) {
            $this->assertSame(0, $DB->count_records('local_sentientia_user_skills', ['userid' => $this->u[$label]]), $label);
            $this->assertSame(0, $DB->count_records('local_sentientia_user_skill_hist', ['userid' => $this->u[$label]]), $label);
        }

        $entry = $this->mapped('#course_completions.userid', $this->firstcompletion['u3']);
        $this->assertSame('archived', $entry->outcome);
        $this->assertSame('user_deleted', $entry->reason);
        $entry = $this->mapped('#course_completions.userid', $this->firstcompletion['u4']);
        $this->assertSame('archived', $entry->outcome);
        $this->assertSame('no_skill_courses', $entry->reason);
        $entry = $this->mapped('#course_completions.userid', $this->firstcompletion['orphan']);
        $this->assertSame('skipped', $entry->outcome);
        $this->assertSame('orphan_user', $entry->reason);

        // The map keys the group by its lowest completion id, never by the learner.
        $this->assertSame('imported', $this->mapped('#course_completions.userid', $this->firstcompletion['u1'])->outcome);
        $this->assertSame(4, $DB->count_records(legacymap::TABLE, [
            'targettable' => 'local_sentientia_user_skills', 'outcome' => 'imported']));
        $this->assertSame(5, $DB->count_records(legacymap::TABLE, [
            'targettable' => 'local_sentientia_user_skill_hist', 'outcome' => 'imported']));
    }

    /**
     * @return void
     */
    private function assert_interests(): void {
        global $DB;
        $skill3 = $this->target('local_skill', 3);
        $skill7 = $this->target('local_skill', 7);

        // u1: two rows, the lowest id is the list BizLMS read. Ids 3 and 7 import, the rest is dropped and counted.
        $rows = $DB->get_records('local_sentientia_skill_interest', ['userid' => $this->u['u1']], 'skillid ASC');
        $this->assertSame([min($skill3, $skill7), max($skill3, $skill7)], array_values(array_map(
            static fn($r): int => (int) $r->skillid, $rows)));
        foreach ($rows as $row) {
            $this->assertSame(self::T0 + 10, (int) $row->timecreated);
            $this->assertSame(self::T0 + 20, (int) $row->timemodified);
        }
        $dup = $this->mapped('local_interested_skills', 2);
        $this->assertSame('merged', $dup->outcome);
        $this->assertSame('dup_learner_row', $dup->reason);
        $this->assertSame('imported', $this->mapped('local_interested_skills', 1)->outcome);
        $this->assertSame('imported', $this->mapped('local_interested_skills', 1, 'skill:7')->outcome);

        // u2: the lowest row is a withdrawn list, so the learner has none; the other row is a duplicate of it.
        $this->assertSame(0, $DB->count_records('local_sentientia_skill_interest', ['userid' => $this->u['u2']]));
        $this->assertSame('withdrawn', $this->mapped('local_interested_skills', 3)->reason);
        $this->assertSame('dup_learner_row', $this->mapped('local_interested_skills', 4)->reason);

        $orphan = $this->mapped('local_interested_skills', 5);
        $this->assertSame('skipped', $orphan->outcome);
        $this->assertSame('orphan_user', $orphan->reason);

        // u4: '5,6': skill 5 was not imported (blank name), skill 6 was.
        $u4 = $DB->get_records('local_sentientia_skill_interest', ['userid' => $this->u['u4']]);
        $this->assertCount(1, $u4);
        $this->assertSame($this->target('local_skill', 6), (int) reset($u4)->skillid);

        $none = $this->mapped('local_interested_skills', 7);
        $this->assertSame('skipped', $none->outcome);
        $this->assertSame('no_valid_skills', $none->reason);

        // u6: no modified time, so the created one.
        $u6 = $DB->get_records('local_sentientia_skill_interest', ['userid' => $this->u['u6']]);
        $this->assertCount(1, $u6);
        $this->assertSame($this->target('local_skill', 2), (int) reset($u6)->skillid);
        $this->assertSame((int) reset($u6)->timecreated, (int) reset($u6)->timemodified);

        $this->assertSame(4, $DB->count_records('local_sentientia_skill_interest'));
        // The unique key (userid, skillid) would have refused a repeat.
    }

    // Seed.

    /**
     * Everything the assertions above rely on.
     *
     * @return void
     */
    private function seed_all(): void {
        $this->ensure_bizlms_schema();
        $this->ensure_course_columns();
        $this->seed_platform();
        $this->seed_users();
        $this->seed_levels();
        $this->seed_categories();
        $this->seed_skills();
        $this->seed_courses();
        $this->seed_completions();
        $this->seed_interests();
        $this->seed_skillmatrix();
    }

    /**
     * The platform's own seed the import must leave alone: a Leadership category and a Team Management skill in it.
     * The phpunit install normally has them; this makes sure whatever it did.
     *
     * @return void
     */
    private function seed_platform(): void {
        global $DB;
        if (!$DB->record_exists('local_sentientia_skill_cats', ['name' => 'Leadership'])) {
            $DB->insert_record('local_sentientia_skill_cats', (object) [
                'name' => 'Leadership', 'description' => '', 'icon' => 'fa-users', 'color' => '#0066A7',
                'sort_order' => 5, 'timecreated' => self::T0 - 100,
            ]);
        }
        $catid = (int) $DB->get_field('local_sentientia_skill_cats', 'id', ['name' => 'Leadership']);
        if (!$DB->record_exists('local_sentientia_skills', ['name' => 'Team Management'])) {
            $DB->insert_record('local_sentientia_skills', (object) [
                'categoryid' => $catid, 'name' => 'Team Management', 'description' => '', 'max_level' => 5,
                'sort_order' => 1, 'timecreated' => self::T0 - 100,
            ]);
        }
    }

    /**
     * @return void
     */
    private function seed_users(): void {
        global $DB;
        $paths = ['u1' => '/1', 'u2' => '/177', 'u3' => '/1', 'u4' => null, 'u5' => '/1', 'u6' => '/1', 'u7' => '/1'];
        foreach ($paths as $label => $path) {
            $user = $this->getDataGenerator()->create_user();
            $this->u[$label] = (int) $user->id;
            if ($path !== null) {
                $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
            }
        }
        // Deleted, the way the legacy data has it: the row stays with deleted = 1.
        $DB->set_field('user', 'deleted', 1, ['id' => $this->u['u3']]);
        $this->u['orphan'] = 987654;
    }

    /**
     * @return void
     */
    private function seed_levels(): void {
        global $DB;
        $t = self::T0;
        $rows = [
            [5, 'Basic', 'L-BASIC', '/1', 1, 10, $t + 5, $t + 105],
            [9, 'Intermediate', 'L-INTER', '/177', 177, 20, $t + 9, $t + 109],
            [12, 'Advanced', '', '0', 77, 30, $t + 12, $t + 112],
            [14, 'Basic duplicate', 'l-basic', '0', null, 40, $t + 14, 0],
        ];
        foreach ($rows as [$id, $name, $code, $path, $costcenter, $sort, $created, $modified]) {
            $DB->import_record('local_course_levels', (object) [
                'id' => $id, 'name' => $name, 'code' => $code, 'open_path' => $path, 'usercreated' => $this->admin_id(),
                'timecreated' => $created, 'usermodified' => 0, 'timemodified' => $modified, 'sortorder' => $sort,
                'costcenterid' => $costcenter,
            ]);
        }
    }

    /**
     * @return void
     */
    private function seed_categories(): void {
        global $DB;
        $t = self::T0;
        // id, name, shortname, parentid, sortorder, open_path, costcenterid.
        $rows = [
            [1, ' LEADERSHIP ', 'LEAD', 0, '1', '/1', 1],
            [2, 'Risk Culture', 'RISK', 0, '2', '/1', 1],
            [3, 'Regional Law', 'REG', 2, '3', '/177', 177],
            [4, 'Shared Basics', 'SHARED', 0, 'x', '0', 77],
            [5, 'Unscoped', 'UNS', 0, '5', '0', null],
            [6, '  ', 'BLANK', 0, '6', '/1', 1],
        ];
        foreach ($rows as [$id, $name, $short, $parent, $sort, $path, $costcenter]) {
            $DB->import_record('local_skill_categories', (object) [
                'id' => $id, 'name' => $name, 'shortname' => $short, 'parentid' => $parent, 'sortorder' => $sort,
                'depth' => 0, 'path' => '', 'open_path' => $path, 'usercreated' => $this->admin_id(),
                'timecreated' => $t + $id, 'usermodified' => 0, 'timemodified' => 0, 'costcenterid' => $costcenter,
            ]);
        }
    }

    /**
     * @return void
     */
    private function seed_skills(): void {
        global $DB;
        $t = self::T0;
        // id, category, name, shortname, description, open_path, costcenterid.
        $rows = [
            [1, 2, 'Python', 'PY', '<p>General purpose</p>', '/1', 1],
            [2, 3, 'Regional Law', 'RL', '', '/177', 177],
            [3, 999, 'Orphaned skill', 'OR', 'Has a picture <img src="@@PLUGINFILE@@/x.png">', '/1', 1],
            [4, 6, 'Blank category skill', 'BC', '', '/1', 1],
            [5, 2, '', 'NONAME', '', '/1', 1],
            [6, 2, 'Python', 'PY2', '', '/1', 1],
            [7, 1, 'Team Management', 'TM', '', '/1', 1],
            [8, 2, str_repeat('x', 150), 'LONG', '', '/1', 1],
        ];
        foreach ($rows as [$id, $category, $name, $short, $description, $path, $costcenter]) {
            $DB->import_record('local_skill', (object) [
                'id' => $id, 'category' => $category, 'name' => $name, 'shortname' => $short, 'parentid' => 0,
                'description' => $description, 'open_path' => $path, 'usercreated' => $this->admin_id(),
                'timecreated' => $t + $id, 'usermodified' => 0, 'timemodified' => 0, 'costcenterid' => $costcenter,
            ]);
        }
    }

    /**
     * @return void
     */
    private function seed_courses(): void {
        global $DB;
        // label, open_skill, open_level, open_path.
        $rows = [
            'a' => [1, 9, '/1'], 'b' => [1, 5, '/1'], 'c' => [2, 0, '/177'], 'd' => [999, 0, '/1'],
            'e' => [0, 0, '/1'], 'f' => [3, 77, '/1'], 'g' => [5, 0, '/1'], 'h' => [7, 14, '/1'],
        ];
        $n = 0;
        foreach ($rows as $label => [$skill, $level, $path]) {
            $course = $this->getDataGenerator()->create_course();
            $DB->update_record('course', (object) [
                'id' => $course->id, 'open_skill' => $skill, 'open_level' => $level, 'open_path' => $path,
                'timecreated' => self::T0 + 51 + $n++,
            ]);
            $this->c[$label] = (int) $course->id;
        }
        // cA is the first, so its timecreated is T0 + 51.
    }

    /**
     * @return void
     */
    private function seed_completions(): void {
        $t = self::T0;
        $t1 = $t + 1000;
        $this->complete('u1', 'b', $t1);
        $this->complete('u1', 'a', $t + 2000);
        $this->complete('u1', 'f', null);
        $this->complete('u2', 'c', $t + 3000);
        $this->complete('u3', 'a', $t1);
        $this->complete('u4', 'e', $t1);
        $this->complete('u5', 'h', $t1);
        $this->complete('u6', 'f', null);
        $this->complete('orphan', 'a', $t1);
        // The recompletion archive: u6 completed cA and was reset (so its current row is in progress), u7 has an
        // archived completion and no current row at all.
        $this->archive('u6', 'a', $t + 900);
        $this->archive('u7', 'b', $t1);
    }

    /**
     * @return void
     */
    private function seed_interests(): void {
        global $DB;
        $t = self::T0;
        // id, list, cost centre, active, modified, learner.
        $rows = [
            [1, '3, 7,999,,x', 1, 1, $t + 20, 'u1'],
            [2, '1', 1, 1, $t + 21, 'u1'],
            [3, '1,2', 177, 0, $t + 22, 'u2'],
            [4, '1', 177, 1, $t + 23, 'u2'],
            [5, '1', 1, 1, $t + 24, 'orphan'],
            [6, '5,6', 77, 1, $t + 25, 'u4'],
            [7, '999', 177, 1, 0, 'u5'],
            [8, '2', 1, 1, 0, 'u6'],
        ];
        foreach ($rows as [$id, $list, $costcenter, $active, $modified, $learner]) {
            $DB->import_record('local_interested_skills', (object) [
                'id' => $id, 'interested_skill_ids' => $list, 'open_costcenterid' => $costcenter, 'active' => $active,
                'timecreated' => $t + 10, 'timemodified' => $modified, 'usercreated' => $this->u[$learner], 'usermodified' => 0,
            ]);
        }
    }

    /**
     * @return void
     */
    private function seed_skillmatrix(): void {
        global $DB;
        foreach ([1, 2] as $id) {
            $DB->import_record('local_skillmatrix', (object) [
                'id' => $id, 'costcenterid' => 1, 'skill_categoryid' => 2, 'skillid' => 1, 'positionid' => $id, 'levelid' => 5,
                'levelname' => 'Basic', 'usercreated' => $this->admin_id(), 'timecreated' => self::T0, 'usermodified' => 0,
                'timemodified' => 0, 'skilllevel' => 5,
            ]);
        }
    }

    // Helpers.

    /**
     * A completion of a course, kept by its learner label; $time null is a course in progress.
     *
     * @param string $learner
     * @param string $course
     * @param int|null $time
     * @return void
     */
    private function complete(string $learner, string $course, ?int $time): void {
        global $DB;
        $id = (int) $DB->insert_record('course_completions', (object) [
            'userid' => $this->u[$learner], 'course' => $this->c[$course], 'timeenrolled' => 0, 'timestarted' => 0,
            'timecompleted' => $time, 'reaggregate' => 0,
        ]);
        $this->firstcompletion[$learner] ??= $id;
    }

    /**
     * A completion the recompletion plugin archived before it reset it.
     *
     * @param string $learner
     * @param string $course
     * @param int $time
     * @return void
     */
    private function archive(string $learner, string $course, int $time): void {
        global $DB;
        $DB->import_record('local_recompletion_cc', (object) [
            'id' => (int) $DB->get_field_sql('SELECT COALESCE(MAX(id), 0) + 1 FROM {local_recompletion_cc}'),
            'userid' => $this->u[$learner], 'course' => $this->c[$course], 'timeenrolled' => 0, 'timestarted' => 0,
            'timecompleted' => $time, 'reaggregate' => 0,
        ]);
    }

    /**
     * The signed decisions, with the level rule's csv (and anything else) replaced.
     *
     * @param array $rule Keys of the level rule to replace ('csv' is the usual one).
     * @param array $other Other decisions to replace.
     * @return decisions
     */
    private function decisions_with(array $rule, array $other = []): decisions {
        $levelrule = array_replace($this->level_rules(), ['csv' => "5,2\n9,3\n12,4\n14,1\n20,5"], $rule);
        return decisions::from_array(array_replace([
            'skills.catalogue_scope' => 'shared',
            'skills.merge_categories' => 'exact_name',
            'skills.seed_rows' => 'keep',
            'skills.level_proficiency' => $levelrule,
            'skills.source_label' => 'import',
            'skills.history_from_archive' => true,
            'skills.skillmatrix' => 'decline',
            'skills.interests' => 'reader_behind_flag',
            'tenant.unresolved.skills' => 'pathless',
        ], $other));
    }

    /**
     * The approved rule, as the signed file has it (without a csv).
     *
     * @return array
     */
    private function level_rules(): array {
        return [
            'method' => 'name_heuristic',
            'rules' => ['awareness' => 1, 'basic' => 2, 'beginner' => 2, 'foundation' => 2, 'intermediate' => 3,
                'advanced' => 4, 'expert' => 5],
            'default' => 1,
            'csv' => null,
        ];
    }

    /**
     * The map row of a source row.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @param string $subkey
     * @return \stdClass
     */
    private function mapped(string $sourcetable, int $sourceid, string $subkey = ''): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE, ['sourcetable' => $sourcetable, 'sourceid' => $sourceid, 'subkey' => $subkey],
            '*', MUST_EXIST);
    }

    /**
     * The target id a legacy row went to.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @return int
     */
    private function target(string $sourcetable, int $sourceid): int {
        return (int) $this->mapped($sourcetable, $sourceid)->targetid;
    }

    /**
     * @return int The admin user, for the legacy columns that name a creator.
     */
    private function admin_id(): int {
        global $DB;
        return (int) $DB->get_field('user', 'id', ['username' => 'admin']);
    }

    /**
     * mdl_course.open_skill and open_level, the BizLMS columns the importer reads.
     *
     * @return void
     */
    private function ensure_course_columns(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('course');
        foreach (['open_skill', 'open_level'] as $name) {
            $field = new \xmldb_field($name, XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
    }
}
