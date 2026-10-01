<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer as importer_contract_subject;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_platform\tests\bizlms\static_scanner;
use local_sentientia_ratings\bizlms\importer;
use local_sentientia_ratings\bizlms\oracle;
use local_sentientia_ratings\tests\bizlms\stub_dependency;

/**
 * The ratings feature of the BizLMS import (ADR-032, mapping doc section 20).
 *
 * The importer contract (dry run writes nothing, apply reconciles, a second apply is a no-op, resume after a
 * crash, a changed source is detected, no side effects, privacy declares every new person column) runs against
 * a seed built from the mapping doc's fixture section, then the feature's own rules are checked one by one:
 * the area and item map, the skips with their reasons, the duplicate winner, source timestamps, the native
 * row that must survive, the owner's choices, the single-feature dry run, the preflight report, the verify
 * and the parity oracle. The importer has no PRESERVE step, so the contract's collision and adoption tests
 * skip themselves.
 *
 * Numbers a test may rely on (legacy ids, so the seed and the assertions read the same). Users: u1 and u2 in
 * /1, u3 in /77, u4 in /177, a deleted user in /1. Courses: c1 and c3 in /1, c2 in /77. Parents (map rows the
 * classroom, program and learningplan importers would have written): classroom 10, program 20, learning plan 30
 * imported, classroom 11 archived.
 *
 *  local_rating   20 rows. Imported: 1 (u1 c1 4), 3 (u2 c1 5, the winner), 8 (classroom 10), 12 (u2 c2 4), 13 (the
 *                 deleted user, c2, 2), 15 (program 20), 16 (learning plan 30), 19 (u4 c3, no timestamps),
 *                 20 (u4 c2, only timemodified). Merged: 2 (into 3). Skipped: 4 (rating NULL), 5 (rating 7),
 *                 6 (user 0), 7 (course 99999), 9 (certification area), 10 (unknown area), 11 (150-character area),
 *                 14 (the guest), 17 (classroom 11, archived), 18 (no such user).
 *  local_comment  12 rows. Imported: 1, 2 (blank), 3, 4 (NULL), 5 (white space), 8 (classroom 10), 10 (deleted
 *                 user), 11 (u3), 12 (two lines). Skipped: 6 (user 0), 7 (certification), 9 (course 99999).
 *  local_like     11 rows. Imported: 1, 2, 3 (NULL status, wins over 10), 5 (wins over 4), 6 (status 5), 8.
 *                 Merged: 4, 10. Skipped: 7 (no user), 9 (unknown area), 11 (course 99999).
 *  cache          5 rows of local_ratings_likes, see seed_cache().
 *
 * @package    local_sentientia_ratings
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_ratings\bizlms\importer
 * @covers     \local_sentientia_ratings\bizlms\ratings_step
 * @covers     \local_sentientia_ratings\bizlms\reviews_step
 * @covers     \local_sentientia_ratings\bizlms\reactions_step
 * @covers     \local_sentientia_ratings\bizlms\area_map
 * @covers     \local_sentientia_ratings\bizlms\row_rules
 * @covers     \local_sentientia_ratings\bizlms\native_probe
 * @covers     \local_sentientia_ratings\bizlms\oracle
 *
 * @group local_sentientia_ratings
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture {
        setUp as protected legacy_fixture_setup;
    }
    use importer_contract {
        contract_clear_import as protected contract_clear_import_base;
    }
    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** @var int[] Seeded user ids by label (u1 ... u4, deleted). */
    private array $u = [];

    /** @var int[] Seeded course ids by label (c1 ... c3). */
    private array $c = [];

    protected static function legacy_fixture_definition(): array {
        return [
            'xml' => __DIR__ . '/fixtures/bizlms/ratings.install.xml',
            // The 2013-era creation time the table's install file no longer declares (RA comment.php writes it).
            'extrafields' => [
                'local_comment' => [new \xmldb_field('time', XMLDB_TYPE_INTEGER, '10', null, null, null, null)],
            ],
        ];
    }

    protected function setUp(): void {
        $this->legacy_fixture_setup();
        $this->ensure_bizlms_schema();
    }

    // The contract.

    protected function contract_importer(): importer_contract_subject {
        return new importer();
    }

    /**
     * Register the importer together with stand-ins for the three features it depends on.
     *
     * @return importer_contract_subject
     */
    protected function contract_begin(): importer_contract_subject {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([
            new stub_dependency('classroom', 'local_classroom'),
            new stub_dependency('program', 'local_program'),
            new stub_dependency('learningplan', 'local_learningplan'),
            $importer,
        ]);
        return $importer;
    }

    protected function contract_decisions(): decisions {
        return $this->decisions_with([]);
    }

    protected function contract_seed(): void {
        $this->seed_all();
    }

    protected function contract_mutate_source(): void {
        // A new row changes the count and the max id on every engine, so detection does not depend on the CRC.
        $this->rating(500, $this->u['u1'], $this->c['c1'], 'local_courses', 3, self::T0 + 500, self::T0 + 500);
    }

    protected function contract_user_columns(): array {
        return [
            'local_sentientia_ratings' => ['userid'],
            'local_sentientia_ratings_reviews' => ['userid'],
            'local_sentientia_ratings_reactions' => ['userid'],
        ];
    }

    /**
     * Put the import back to nothing between two runs of one test, and keep the parents.
     *
     * The platform's version deletes every map row, which also removes the rows the seed wrote as the classroom,
     * program and learningplan importers: the second run would then skip those ratings as orphans and differ from
     * the first for a reason that has nothing to do with resume. The parents go back in.
     *
     * @param importer_contract_subject $importer
     * @return void
     */
    protected function contract_clear_import(importer_contract_subject $importer): void {
        $this->contract_clear_import_base($importer);
        $this->seed_parent_map();
    }

    // The map.

    public function test_every_rating_gets_the_outcome_the_map_says(): void {
        $this->contract_begin();
        $this->seed_all();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $imported = [1, 3, 8, 12, 13, 15, 16, 19, 20];
        foreach ($imported as $id) {
            $this->assertSame('imported', $this->map_row('local_rating', $id)->outcome, "rating {$id}");
        }
        $merged = $this->map_row('local_rating', 2);
        $this->assertSame('merged', $merged->outcome);
        $this->assertSame('dup_natural_key', $merged->reason);

        $skipped = [
            4 => ['invalid_rating', 'rating_missing'],
            5 => ['invalid_rating', 'rating_out_of_range'],
            6 => ['orphan_user', 'user_not_real'],
            7 => ['orphan_item', 'course_not_found'],
            9 => ['unknown_area', 'certification_area'],
            10 => ['unknown_area', 'area_not_mapped'],
            11 => ['unknown_area', 'area_too_long'],
            14 => ['orphan_user', 'user_not_real'],
            17 => ['orphan_item', 'parent_not_imported'],
            18 => ['orphan_user', 'user_not_found'],
        ];
        foreach ($skipped as $id => [$reason, $detail]) {
            $row = $this->map_row('local_rating', $id);
            $this->assertSame('skipped', $row->outcome, "rating {$id}");
            $this->assertSame($reason, $row->reason, "rating {$id}");
            $this->assertSame($detail, $row->detail, "rating {$id}");
            $this->assertSame('', $row->targettable, "a skipped row has no target");
        }
        $this->assertSame(20, $this->primary_map_count('local_rating'));
    }

    public function test_area_and_item_are_mapped(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        $this->contract_run(true);

        $expected = [
            1 => ['local_sentientia_courses', $this->c['c1']],
            8 => ['local_sentientia_classroom', 10],
            15 => ['local_sentientia_programs', 20],
            16 => ['local_sentientia_learningpath', 30],
            19 => ['local_sentientia_courses', $this->c['c3']],
        ];
        foreach ($expected as $id => [$area, $item]) {
            $target = $DB->get_record('local_sentientia_ratings', ['id' => $this->map_row('local_rating', $id)->targetid],
                '*', MUST_EXIST);
            $this->assertSame($area, $target->ratearea, "rating {$id}");
            $this->assertSame($item, (int) $target->itemid, "rating {$id}");
        }
        // Nothing was written under a BizLMS-era area name.
        $this->assertSame(0, $DB->count_records_select('local_sentientia_ratings',
            "ratearea IN ('local_courses', 'local_classroom', 'local_program', 'local_learningplan')"));
    }

    public function test_source_timestamps_are_kept(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        [, $report] = $this->contract_run(true);

        $row = $DB->get_record('local_sentientia_ratings', ['id' => $this->map_row('local_rating', 1)->targetid]);
        $this->assertSame([4, self::T0 + 1, self::T0 + 11],
            [(int) $row->rating, (int) $row->timecreated, (int) $row->timemodified]);

        // Row 20 has only timemodified: it stands in for the creation time, and the row is reported.
        $row = $DB->get_record('local_sentientia_ratings', ['id' => $this->map_row('local_rating', 20)->targetid]);
        $this->assertSame([self::T0 + 40, self::T0 + 40], [(int) $row->timecreated, (int) $row->timemodified]);

        // Row 19 has neither: both are 0, reported, and the legacy row keeps the truth.
        $row = $DB->get_record('local_sentientia_ratings', ['id' => $this->map_row('local_rating', 19)->targetid]);
        $this->assertSame([0, 0], [(int) $row->timecreated, (int) $row->timemodified]);

        $warnings = $report->to_array()['features']['ratings']['steps']['ratings.ratings']['warnings'];
        $this->assertSame(1, $warnings['derived_timestamp']);
        $this->assertSame(1, $warnings['missing_timestamp']);
    }

    public function test_duplicate_rows_collapse_to_the_latest_change(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        $this->contract_run(true);

        $winner = $this->map_row('local_rating', 3);
        $loser = $this->map_row('local_rating', 2);
        $this->assertSame('local_sentientia_ratings', $loser->targettable);
        $this->assertSame((int) $winner->targetid, (int) $loser->targetid, 'the duplicate points at the surviving row');
        $row = $DB->get_record('local_sentientia_ratings', ['id' => $winner->targetid]);
        $this->assertSame([5, self::T0 + 3, self::T0 + 30], [(int) $row->rating, (int) $row->timecreated, (int) $row->timemodified]);

        // One row per (user, item, area): the target's unique key holds by construction.
        $this->assertSame(1, $DB->count_records('local_sentientia_ratings', [
            'userid' => $this->u['u2'], 'itemid' => $this->c['c1'], 'ratearea' => 'local_sentientia_courses',
        ]));
    }

    public function test_the_imported_ratings_read_back_through_the_manager(): void {
        $this->contract_begin();
        $this->seed_all();
        $this->contract_run(true);

        // Row 1 (4) and the winner of the duplicate pair (5): the 3 that lost, the NULL and the guest are not in it.
        $average = rating_manager::get_average($this->c['c1'], 'local_sentientia_courses');
        $this->assertSame(2, $average->count);
        $this->assertEqualsWithDelta(4.5, $average->average, 0.001);
        $this->assertSame(4, rating_manager::get_user_rating($this->c['c1'], 'local_sentientia_courses', $this->u['u1']));
        $this->assertSame(5, rating_manager::get_user_rating($this->c['c1'], 'local_sentientia_courses', $this->u['u2']));
        $this->assertSame(0, rating_manager::get_user_rating($this->c['c1'], 'local_sentientia_courses', $this->u['u3']),
            'a NULL rating was not imported');
    }

    public function test_the_rating_manager_no_longer_falls_back_to_the_legacy_table(): void {
        $this->contract_begin();
        $this->seed_all();
        // Before the import the BizLMS rows are all there and Sentientia's table is empty. The fallback used to
        // answer from local_rating (six valid rows for course c1 under the old area name); now nothing does,
        // until the import has run.
        $this->assertSame(0, rating_manager::get_average($this->c['c1'], 'local_courses')->count);
        $this->assertSame(0, rating_manager::get_average($this->c['c1'], 'local_sentientia_courses')->count);
        $this->assertSame(0, rating_manager::get_user_rating($this->c['c1'], 'local_courses', $this->u['u1']));
        $this->assertSame(0, rating_manager::get_user_rating($this->c['c1'], 'local_sentientia_courses', $this->u['u1']));
    }

    public function test_a_deleted_users_rating_is_kept_unless_the_owner_says_skip(): void {
        $this->contract_begin();
        $this->seed_all();
        $this->contract_run(true);
        $this->assertSame('imported', $this->map_row('local_rating', 13)->outcome);
        $this->assertSame('imported', $this->map_row('local_comment', 10)->outcome);

        $this->contract_clear_import(new importer());
        [$result] = $this->contract_run(true, ['decisions' => $this->decisions_with(['ratings.deleted_users' => 'skip'])]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        foreach ([['local_rating', 13], ['local_comment', 10]] as [$table, $id]) {
            $row = $this->map_row($table, $id);
            $this->assertSame('skipped', $row->outcome, "{$table} {$id}");
            $this->assertSame('user_deleted', $row->reason);
        }
    }

    // A row Sentientia already holds.

    public function test_a_native_rating_is_never_overwritten(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        // A learner who rated on Sentientia before the import: one key BizLMS also holds (u1, c1) and one it does not.
        $conflict = $DB->insert_record('local_sentientia_ratings', (object) [
            'itemid' => $this->c['c1'], 'ratearea' => 'local_sentientia_courses', 'userid' => $this->u['u1'],
            'rating' => 2, 'timecreated' => self::T0 + 900, 'timemodified' => self::T0 + 901,
        ]);
        $other = $DB->insert_record('local_sentientia_ratings', (object) [
            'itemid' => $this->c['c3'], 'ratearea' => 'local_sentientia_courses', 'userid' => $this->u['u1'],
            'rating' => 1, 'timecreated' => self::T0 + 902, 'timemodified' => self::T0 + 903,
        ]);

        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $row = $this->map_row('local_rating', 1);
        $this->assertSame('folded', $row->outcome);
        $this->assertSame('native_row_kept', $row->reason);
        $this->assertSame($conflict, (int) $row->targetid);
        $this->assertSame([2, self::T0 + 900, self::T0 + 901],
            array_values(array_map('intval', (array) $DB->get_record('local_sentientia_ratings', ['id' => $conflict],
                'rating, timecreated, timemodified'))), 'the native rating is untouched');
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_ratings', 'rating', ['id' => $other]));
        $this->assertSame(2, rating_manager::get_user_rating($this->c['c1'], 'local_sentientia_courses', $this->u['u1']));
    }

    public function test_a_native_reaction_is_never_overwritten(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        $conflict = $DB->insert_record('local_sentientia_ratings_reactions', (object) [
            'itemid' => $this->c['c1'], 'ratearea' => 'local_sentientia_courses', 'userid' => $this->u['u1'],
            'likestatus' => 2, 'timecreated' => self::T0 + 900, 'timemodified' => self::T0 + 901,
        ]);

        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $row = $this->map_row('local_like', 1);
        $this->assertSame('folded', $row->outcome);
        $this->assertSame('native_row_kept', $row->reason);
        $this->assertSame($conflict, (int) $row->targetid);
        $this->assertSame(2, (int) $DB->get_field('local_sentientia_ratings_reactions', 'likestatus', ['id' => $conflict]));
    }

    // Reviews.

    public function test_reviews_are_stored_verbatim_and_a_learner_may_have_several(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        $this->contract_run(true);

        $text = static fn(int $id): ?string => $GLOBALS['DB']->get_field('local_sentientia_ratings_reviews', 'review',
            ['id' => $GLOBALS['DB']->get_field(legacymap::TABLE, 'targetid',
                ['sourcetable' => 'local_comment', 'sourceid' => $id, 'subkey' => ''])]);
        $this->assertSame('<script>x</script>good', $text(1), 'raw input is kept; the reader escapes it');
        $this->assertSame('Second thoughts', $text(3));
        $this->assertSame("Line one\nLine two", $text(12));
        $this->assertSame('', $text(2), 'blank reviews are imported (the reader hides them)');
        $this->assertNull($text(4));
        $this->assertSame('   ', $text(5));

        // Two distinct reviews by u1 on c1: both are there.
        $this->assertSame(3, $DB->count_records('local_sentientia_ratings_reviews', [
            'userid' => $this->u['u1'], 'itemid' => $this->c['c1'], 'ratearea' => 'local_sentientia_courses',
        ]));

        foreach ([6 => 'orphan_user', 7 => 'unknown_area', 9 => 'orphan_item'] as $id => $reason) {
            $row = $this->map_row('local_comment', $id);
            $this->assertSame('skipped', $row->outcome, "review {$id}");
            $this->assertSame($reason, $row->reason, "review {$id}");
        }
        $this->assertSame(9, $DB->count_records('local_sentientia_ratings_reviews'));
        $this->assertSame(12, $this->primary_map_count('local_comment'));
    }

    public function test_a_review_takes_the_2013_time_column_when_timecreated_is_missing(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        $this->contract_run(true);
        $row = $DB->get_record('local_sentientia_ratings_reviews', ['id' => $this->map_row('local_comment', 1)->targetid]);
        $this->assertSame([self::T0 + 100, self::T0 + 100], [(int) $row->timecreated, (int) $row->timemodified]);
    }

    public function test_the_importer_runs_on_a_comment_table_without_the_2013_time_column(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_people();
        $DB->get_manager()->drop_field(new \xmldb_table('local_comment'), new \xmldb_field('time'));
        $this->comment(1, $this->u['u1'], $this->c['c1'], 'local_courses', 'No time column', null, null);
        $this->comment(2, $this->u['u1'], $this->c['c1'], 'local_courses', 'With dates', self::T0 + 5, self::T0 + 6);

        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $row = $DB->get_record('local_sentientia_ratings_reviews', ['id' => $this->map_row('local_comment', 1)->targetid]);
        $this->assertSame([0, 0], [(int) $row->timecreated, (int) $row->timemodified]);
        $row = $DB->get_record('local_sentientia_ratings_reviews', ['id' => $this->map_row('local_comment', 2)->targetid]);
        $this->assertSame([self::T0 + 5, self::T0 + 6], [(int) $row->timecreated, (int) $row->timemodified]);
        $warnings = $report->to_array()['features']['ratings']['steps']['ratings.reviews']['warnings'];
        $this->assertSame(1, $warnings['missing_timestamp']);
    }

    public function test_blank_reviews_stay_in_the_legacy_table_when_the_owner_says_skip(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        [$result] = $this->contract_run(true, ['decisions' => $this->decisions_with(['ratings.blank_reviews' => 'skip'])]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        foreach ([2, 4, 5] as $id) {
            $row = $this->map_row('local_comment', $id);
            $this->assertSame('archived', $row->outcome, "review {$id}");
            $this->assertSame('blank_review', $row->reason);
        }
        $this->assertSame(6, $DB->count_records('local_sentientia_ratings_reviews'));
    }

    // Reactions.

    public function test_reactions_collapse_to_the_latest_and_keep_the_stored_status(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $status = static fn(int $id): int => (int) $GLOBALS['DB']->get_field('local_sentientia_ratings_reactions',
            'likestatus', ['id' => $GLOBALS['DB']->get_field(legacymap::TABLE, 'targetid',
                ['sourcetable' => 'local_like', 'sourceid' => $id, 'subkey' => ''])]);
        $this->assertSame(1, $status(1));
        $this->assertSame(2, $status(2));
        $this->assertSame(0, $status(3), 'a NULL status is 0, and the later row wins over row 10');
        $this->assertSame(2, $status(5), 'row 5 changed after row 4');
        $this->assertSame(5, $status(6), 'a status BizLMS never counted is carried over as stored');
        $this->assertSame(1, $status(8));

        foreach ([4, 10] as $id) {
            $row = $this->map_row('local_like', $id);
            $this->assertSame('merged', $row->outcome, "reaction {$id}");
            $this->assertSame('dup_natural_key', $row->reason);
        }
        foreach ([7 => 'orphan_user', 9 => 'unknown_area', 11 => 'orphan_item'] as $id => $reason) {
            $row = $this->map_row('local_like', $id);
            $this->assertSame('skipped', $row->outcome, "reaction {$id}");
            $this->assertSame($reason, $row->reason, "reaction {$id}");
        }
        $this->assertSame(6, $DB->count_records('local_sentientia_ratings_reactions'));
        $this->assertSame(11, $this->primary_map_count('local_like'));

        // What a reader counts: likes and dislikes only.
        $c1 = reaction_manager::get_counts($this->c['c1'], 'local_sentientia_courses');
        $this->assertSame([1, 1], [$c1->likes, $c1->dislikes]);
        $c2 = reaction_manager::get_counts($this->c['c2'], 'local_sentientia_courses');
        $this->assertSame([0, 1], [$c2->likes, $c2->dislikes]);
    }

    // The run.

    public function test_a_single_feature_dry_run_defers_rows_whose_parent_has_not_run(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        $DB->delete_records(legacymap::TABLE);

        $report = new report();
        $runner = new runner(['decisions' => $this->decisions_with([]), 'report' => $report, 'batch' => 2]);
        $result = $runner->run(['ratings']);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $steps = $report->to_array()['features']['ratings']['steps'];
        // Ratings 8, 15, 16, 17 name a classroom, program or learning plan; review 8 and reaction 8 a classroom.
        $this->assertSame(4, $steps['ratings.ratings']['skipped_by_reason']['deferred']);
        $this->assertSame(1, $steps['ratings.reviews']['skipped_by_reason']['deferred']);
        $this->assertSame(1, $steps['ratings.reactions']['skipped_by_reason']['deferred']);
        // A deferred row is not reported as a failure: the only orphans left are the rows about the missing course.
        $this->assertSame(1, $steps['ratings.ratings']['skipped_by_reason']['orphan_item']);
        $this->assertSame(1, $steps['ratings.reviews']['skipped_by_reason']['orphan_item']);
        $this->assertSame(1, $steps['ratings.reactions']['skipped_by_reason']['orphan_item']);
    }

    public function test_the_preflight_reports_the_values_and_blocks_nothing(): void {
        $this->contract_begin();
        $this->seed_all();
        $pf = $this->preflight();

        $this->assertSame([], $pf->blockers());
        $counts = $pf->counts();
        $this->assertSame(20, $counts['rows:ratings.ratings']);
        $this->assertSame(12, $counts['rows:ratings.reviews']);
        $this->assertSame(11, $counts['rows:ratings.reactions']);
        $this->assertSame(2, $counts['rating_rows_invalid']);
        $this->assertSame(2, $counts['rating_moduleid_set']);
        $this->assertSame(5, $counts['cache_rows']);
        $this->assertSame(5, $counts['declined_rows:local_ratings_likes']);
        $this->assertArrayNotHasKey('declined_rows:block_trending_modules', $counts, 'that table does not exist here');

        $warnings = $pf->warnings();
        $this->assertContains('rating_moduleid_differs_from_itemid:1', $warnings);
        $this->assertContains('cache_disagrees_with_legacy_rows:4', $warnings);
        // The two facts about BizLMS reviews the owner must read: the author was asserted, and a delete needed no login.
        $this->assertContains('review_author_asserted_not_authenticated:12', $warnings);
        $this->assertContains('review_history_may_be_incomplete:unauthenticated_delete', $warnings);
        // Raters whose tenant is not their course's: rows 4, 12, 13, 19 and 20 of local_rating.
        $this->assertContains('cross_tenant_ratings:5', $warnings);

        $areas = $pf->histograms()['local_rating.ratearea'];
        $this->assertSame(13, $areas['local_courses']);
        $this->assertSame(1, $areas['local_certification']);
        $this->assertSame(1, $pf->histograms()['local_like.likestatus']['']);
        $this->assertSame(1, $pf->histograms()['local_like.likestatus']['5']);
    }

    public function test_the_signed_decisions_file_carries_every_choice_the_importer_needs(): void {
        global $CFG;
        $this->contract_begin();
        $this->seed_all();
        $file = $CFG->dirroot . '/local/sentientia_platform/tests/fixtures/bizlms/bizlms-import-decisions.copy.json';
        $this->assertFileExists($file);
        $pf = $this->preflight(decisions::load($file));
        $this->assertSame([], $pf->blockers(), 'the signed file answers every decision the importer declares');
    }

    public function test_an_unsigned_choice_blocks_the_feature(): void {
        $this->contract_begin();
        $this->seed_all();
        $pf = $this->preflight(decisions::from_array([
            'ratings.invalid_rows' => 'skip', 'ratings.blank_reviews' => 'import_hidden',
            'ratings.deleted_users' => 'keep',
        ]));
        $this->assertSame(['missing_decision:ratings.certification_area'], $pf->blockers());

        $pf = $this->preflight($this->decisions_with(['ratings.invalid_rows' => 'quarantine']));
        $this->assertSame(['decision_value_not_allowed:ratings.invalid_rows'], $pf->blockers(),
            'only skipping invalid rows is built');
    }

    public function test_verify_passes_after_an_import_and_names_a_corrupted_row(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->seed_all();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $ctx = context::build($importer, false, 0, $this->decisions_with([]));
        $this->assertSame([], $importer->verify($ctx));

        $DB->set_field('local_sentientia_ratings', 'rating', 9, ['id' => $this->map_row('local_rating', 1)->targetid]);
        $DB->set_field('local_sentientia_ratings_reviews', 'ratearea', 'local_courses',
            ['id' => $this->map_row('local_comment', 3)->targetid]);
        $DB->set_field('local_sentientia_ratings_reactions', 'itemid', 99999,
            ['id' => $this->map_row('local_like', 1)->targetid]);
        $failures = $importer->verify($ctx);
        $this->assertContains('rating_out_of_range:1', $failures);
        $this->assertContains('row_outside_the_map:local_sentientia_ratings_reviews:1', $failures);
        $this->assertContains('course_missing:local_sentientia_ratings_reactions:1', $failures);
    }

    public function test_the_legacy_tables_are_left_exactly_as_they_were(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_all();
        $before = [];
        foreach (['local_rating', 'local_comment', 'local_like', 'local_ratings_likes'] as $table) {
            $before[$table] = $DB->get_records($table, null, 'id');
        }
        $this->contract_run(true);
        foreach ($before as $table => $rows) {
            $this->assertEquals($rows, $DB->get_records($table, null, 'id'), "{$table} is the archive and is never written");
        }
    }

    public function test_the_importer_code_passes_the_static_scan(): void {
        $dir = \core_component::get_component_directory('local_sentientia_ratings') . '/classes/bizlms';
        $files = static_scanner::php_files($dir);
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertSame([], static_scanner::scan((string) file_get_contents($file), false, false), basename($file));
        }
    }

    // The parity oracle.

    public function test_the_oracle_explains_each_difference(): void {
        $differences = oracle::compare(
            [
                'a|1' => ['avg' => 4.5, 'users' => 2],   // agrees with the imported average
                'a|2' => ['avg' => 3.0, 'users' => 3],   // skipped rows and a stale count
                'a|3' => ['avg' => 4.0, 'users' => 1],   // nothing explains it
                'a|4' => ['avg' => 2.0, 'users' => 4],   // never imported
                'a|5' => ['avg' => 4.45, 'users' => 2],  // 0.05 away: within the tolerance
                'a|6' => ['avg' => 3.0, 'users' => 4],   // duplicates collapsed
            ],
            [
                'a|1' => ['avg' => 4.5, 'count' => 2],
                'a|2' => ['avg' => 3.666, 'count' => 3],
                'a|3' => ['avg' => 5.0, 'count' => 1],
                'a|5' => ['avg' => 4.5, 'count' => 2],
                'a|6' => ['avg' => 4.0, 'count' => 2],
            ],
            [
                'a|1' => ['total' => 2, 'valid' => 2],
                'a|2' => ['total' => 4, 'valid' => 3],
                'a|3' => ['total' => 1, 'valid' => 1],
                'a|5' => ['total' => 2, 'valid' => 2],
                'a|6' => ['total' => 4, 'valid' => 4],
            ]);
        $byKey = [];
        foreach ($differences as $difference) {
            $byKey[$difference['key']] = $difference;
        }
        $this->assertSame(['a|2', 'a|3', 'a|4', 'a|6'], array_keys($byKey));
        $this->assertSame(['skipped_rows', 'cache_count_stale'], $byKey['a|2']['because']);
        $this->assertSame(3.7, $byKey['a|2']['imported']);
        $this->assertSame(['unexplained'], $byKey['a|3']['because']);
        $this->assertSame(['no_imported_ratings', 'cache_count_stale'], $byKey['a|4']['because']);
        $this->assertNull($byKey['a|4']['imported']);
        $this->assertSame(['merged_duplicates'], $byKey['a|6']['because']);
    }

    public function test_the_oracle_compares_the_imported_averages_with_the_cache(): void {
        $this->contract_begin();
        $this->seed_all();
        $this->contract_run(true);

        $result = oracle::imported_vs_cache();
        $this->assertSame(5, $result['compared']);
        $byKey = [];
        foreach ($result['differences'] as $difference) {
            $byKey[$difference['key']] = $difference['because'];
        }
        $expected = [
            'local_sentientia_courses|12345' => ['no_imported_ratings', 'cache_count_stale'],
            'local_sentientia_courses|' . $this->c['c2'] => ['skipped_rows', 'cache_count_stale'],
            'local_sentientia_programs|20' => ['unexplained'],
        ];
        ksort($byKey);
        ksort($expected);
        $this->assertSame($expected, $byKey);
        $this->assertSame(1, $result['unexplained']);
    }

    // Seeds and helpers.

    /**
     * Run the preflight of the feature alone and return it.
     *
     * @param decisions|null $decisions
     * @return \local_sentientia_platform\bizlms\preflight
     */
    private function preflight(?decisions $decisions = null): \local_sentientia_platform\bizlms\preflight {
        $runner = new runner(['decisions' => $decisions ?? $this->decisions_with([]), 'report' => new report()]);
        return $runner->preflight(['ratings'])['preflights']['ratings'];
    }

    /**
     * The four owner choices, as the signed file carries them, with overrides.
     *
     * @param array<string, mixed> $overrides
     * @return decisions
     */
    private function decisions_with(array $overrides): decisions {
        return decisions::from_array($overrides + [
            'ratings.invalid_rows' => 'skip',
            'ratings.blank_reviews' => 'import_hidden',
            'ratings.deleted_users' => 'keep',
            'ratings.certification_area' => 'skip_and_report',
        ]);
    }

    /**
     * The primary map row of a legacy row.
     *
     * @param string $table
     * @param int $id
     * @return \stdClass
     */
    private function map_row(string $table, int $id): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE, ['sourcetable' => $table, 'sourceid' => $id, 'subkey' => ''], '*', MUST_EXIST);
    }

    /**
     * @param string $table
     * @return int Primary map rows of a source table.
     */
    private function primary_map_count(string $table): int {
        global $DB;
        return $DB->count_records(legacymap::TABLE, ['sourcetable' => $table, 'subkey' => '']);
    }

    private function seed_all(): void {
        $this->seed_people();
        $this->seed_ratings();
        $this->seed_reviews();
        $this->seed_reactions();
        $this->seed_cache();
    }

    /**
     * Users, courses and the parent map rows.
     *
     * @return void
     */
    private function seed_people(): void {
        $this->u = [
            'u1' => $this->make_user('/1'),
            'u2' => $this->make_user('/1'),
            'u3' => $this->make_user('/77'),
            'u4' => $this->make_user('/177'),
            'deleted' => $this->make_user('/1', true),
        ];
        $this->c = [
            'c1' => $this->make_course('/1'),
            'c2' => $this->make_course('/77'),
            'c3' => $this->make_course('/1'),
        ];
        $this->seed_parent_map();
    }

    /**
     * The map rows the classroom, program and learningplan importers would have written. They are 'folded',
     * not 'imported', because the contract checks that every imported row's target exists, and the stand-in
     * features write no target. A classroom, program or learning plan id resolves to the same id either way.
     *
     * @return void
     */
    private function seed_parent_map(): void {
        global $DB;
        $rows = [
            ['classroom', 'local_classroom', 10, 'local_sentientia_classroom', 10, 'folded', 'stub_parent'],
            ['classroom', 'local_classroom', 11, '', null, 'archived', 'stub_parent'],
            ['program', 'local_program', 20, 'local_sentientia_programs', 20, 'folded', 'stub_parent'],
            ['learningplan', 'local_learningplan', 30, 'local_sentientia_learningpath', 30, 'folded', 'stub_parent'],
        ];
        foreach ($rows as [$feature, $table, $sourceid, $targettable, $targetid, $outcome, $reason]) {
            $DB->insert_record(legacymap::TABLE, (object) [
                'feature' => $feature, 'sourcetable' => $table, 'sourceid' => $sourceid, 'subkey' => '',
                'targettable' => $targettable, 'targetid' => $targetid, 'outcome' => $outcome, 'reason' => $reason,
                'detail' => null, 'runid' => 0, 'timecreated' => self::T0,
            ]);
        }
    }

    private function make_user(string $path, bool $deleted = false): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        if ($deleted) {
            $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);
        }
        return (int) $user->id;
    }

    private function make_course(string $path): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return (int) $course->id;
    }

    private function rating(int $id, ?int $user, ?int $item, ?string $area, ?int $rating, ?int $created, ?int $modified,
                            ?int $moduleid = null): void {
        global $DB;
        $DB->import_record('local_rating', (object) [
            'id' => $id, 'itemid' => $item, 'ratearea' => $area, 'userid' => $user, 'rating' => $rating,
            'timecreated' => $created, 'timemodified' => $modified, 'moduleid' => $moduleid,
        ]);
    }

    private function comment(int $id, ?int $user, ?int $item, ?string $area, ?string $text, ?int $created, ?int $modified,
                             ?int $time = null): void {
        global $DB;
        $row = [
            'id' => $id, 'itemid' => $item, 'commentarea' => $area, 'comment' => $text, 'userid' => $user,
            'timecreated' => $created, 'timemodified' => $modified,
        ];
        if ($time !== null) {
            $row['time'] = $time;
        }
        $DB->import_record('local_comment', (object) $row);
    }

    private function like(int $id, ?int $user, ?int $item, ?string $area, ?int $status, ?int $created, ?int $modified): void {
        global $DB;
        $DB->import_record('local_like', (object) [
            'id' => $id, 'itemid' => $item, 'likearea' => $area, 'likestatus' => $status, 'userid' => $user,
            'timecreated' => $created, 'timemodified' => $modified,
        ]);
    }

    private function seed_ratings(): void {
        $t = self::T0;
        [$u1, $u2, $u3, $u4, $gone] = [$this->u['u1'], $this->u['u2'], $this->u['u3'], $this->u['u4'], $this->u['deleted']];
        [$c1, $c2, $c3] = [$this->c['c1'], $this->c['c2'], $this->c['c3']];
        $this->rating(1, $u1, $c1, 'local_courses', 4, $t + 1, $t + 11);
        $this->rating(2, $u2, $c1, 'local_courses', 3, $t + 2, $t + 2);
        $this->rating(3, $u2, $c1, 'local_courses', 5, $t + 3, $t + 30);
        $this->rating(4, $u3, $c1, 'local_courses', null, $t + 4, $t + 4);
        $this->rating(5, $u3, $c2, 'local_courses', 7, $t + 5, $t + 5);
        $this->rating(6, 0, $c1, 'local_courses', 5, $t + 6, $t + 6);
        $this->rating(7, $u1, 99999, 'local_courses', 4, $t + 7, $t + 7);
        $this->rating(8, $u4, 10, 'local_classroom', 5, $t + 8, $t + 8);
        $this->rating(9, $u1, 5, 'local_certification', 4, $t + 9, $t + 9);
        $this->rating(10, $u1, 5, 'local_unknown', 4, $t + 10, $t + 10);
        $this->rating(11, $u1, 5, str_repeat('a', 150), 4, $t + 11, $t + 11);
        $this->rating(12, $u2, $c2, 'local_courses', 4, $t + 12, $t + 12, $c2);
        $this->rating(13, $gone, $c2, 'local_courses', 2, $t + 13, $t + 13, 999);
        $this->rating(14, 1, $c1, 'local_courses', 3, $t + 14, $t + 14);
        $this->rating(15, $u1, 20, 'local_program', 5, $t + 15, $t + 15);
        $this->rating(16, $u1, 30, 'local_learningplan', 3, $t + 16, $t + 16);
        $this->rating(17, $u3, 11, 'local_classroom', 4, $t + 17, $t + 17);
        $this->rating(18, 987654, $c1, 'local_courses', 5, $t + 18, $t + 18);
        $this->rating(19, $u4, $c3, 'local_courses', 5, null, null);
        $this->rating(20, $u4, $c2, 'local_courses', 5, null, $t + 40);
    }

    private function seed_reviews(): void {
        $t = self::T0;
        [$u1, $u2, $u3, $u4, $gone] = [$this->u['u1'], $this->u['u2'], $this->u['u3'], $this->u['u4'], $this->u['deleted']];
        $c1 = $this->c['c1'];
        $this->comment(1, $u1, $c1, 'local_courses', '<script>x</script>good', null, null, $t + 100);
        $this->comment(2, $u1, $c1, 'local_courses', '', $t + 101, $t + 101);
        $this->comment(3, $u1, $c1, 'local_courses', 'Second thoughts', $t + 200, $t + 210);
        $this->comment(4, $u2, $c1, 'local_courses', null, $t + 102, $t + 102);
        $this->comment(5, $u2, $c1, 'local_courses', '   ', $t + 103, $t + 103);
        $this->comment(6, 0, $c1, 'local_courses', 'Nobody wrote this', $t + 104, $t + 104);
        $this->comment(7, $u3, 5, 'local_certification', 'About a certificate', $t + 105, $t + 105);
        $this->comment(8, $u4, 10, 'local_classroom', 'Great trainer', $t + 106, $t + 106);
        $this->comment(9, $u1, 99999, 'local_courses', 'Course is gone', $t + 107, $t + 107);
        $this->comment(10, $gone, $c1, 'local_courses', 'Left before I could finish', $t + 108, $t + 108);
        $this->comment(11, $u3, $c1, 'local_courses', 'From the public tenant', $t + 109, $t + 109);
        $this->comment(12, $u2, $c1, 'local_courses', "Line one\nLine two", $t + 110, $t + 110);
    }

    private function seed_reactions(): void {
        $t = self::T0;
        [$u1, $u2, $u3, $u4] = [$this->u['u1'], $this->u['u2'], $this->u['u3'], $this->u['u4']];
        [$c1, $c2] = [$this->c['c1'], $this->c['c2']];
        $this->like(1, $u1, $c1, 'local_courses', 1, $t + 1, $t + 2);
        $this->like(2, $u2, $c1, 'local_courses', 2, $t + 3, $t + 3);
        $this->like(3, $u3, $c1, 'local_courses', null, $t + 4, $t + 50);
        $this->like(4, $u1, $c2, 'local_courses', 1, $t + 5, $t + 6);
        $this->like(5, $u1, $c2, 'local_courses', 2, $t + 7, $t + 8);
        $this->like(6, $u4, $c1, 'local_courses', 5, $t + 9, $t + 9);
        $this->like(7, null, $c1, 'local_courses', 1, $t + 10, $t + 10);
        $this->like(8, $u2, 10, 'local_classroom', 1, $t + 11, $t + 11);
        $this->like(9, $u1, $c1, 'local_unknown', 1, $t + 12, $t + 12);
        $this->like(10, $u3, $c1, 'local_courses', 1, $t + 5, $t + 5);
        $this->like(11, $u1, 99999, 'local_courses', 1, $t + 13, $t + 13);
    }

    /**
     * The BizLMS cache: what course tiles showed.
     *
     * c1 agrees with the imported average (4.5). c2 shows 3.0 where the import has 3.7 over three ratings, and
     * the cache counted three of the four rows the table holds. Program 20 shows 4.0 where the import has 5.0
     * and the cache's count is right: nothing explains that one. Course 12345 has a figure and no rating.
     *
     * @return void
     */
    private function seed_cache(): void {
        global $DB;
        $rows = [
            ['local_courses', $this->c['c1'], '4.50', 3],
            ['local_courses', $this->c['c2'], '3.00', 3],
            ['local_classroom', 10, '5.00', 1],
            ['local_program', 20, '4.00', 1],
            ['local_courses', 12345, '2.00', 4],
        ];
        foreach ($rows as [$area, $item, $rating, $users]) {
            $DB->insert_record('local_ratings_likes', (object) [
                'module_id' => $item, 'module_area' => $area, 'module_rating' => $rating, 'module_rating_users' => $users,
                'module_like' => 0, 'module_like_users' => 0, 'timecreated' => self::T0, 'timemodified' => self::T0,
            ]);
        }
    }
}
