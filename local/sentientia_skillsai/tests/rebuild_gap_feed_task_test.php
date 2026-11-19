<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;
use local_sentientia_skillsai\task\rebuild_gap_feed;

/**
 * PHPUnit tests for the nightly gap-feed rebuild task.
 *
 * Post-demo plan 2.4 (2026-11-19): the task read the first BATCH_SIZE (500)
 * matching users by id and stopped, every night, so nobody after them had a
 * gap feed rebuilt. It now walks every matching user, batch by batch. The
 * batching is exercised through rebuild_all() with a small batch size, so the
 * tests need a handful of users, not 500.
 *
 * @package    local_sentientia_skillsai
 * @covers     \local_sentientia_skillsai\task\rebuild_gap_feed
 */
final class rebuild_gap_feed_task_test extends \advanced_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /** Designation with one role requirement in every test. */
    private const DESIGNATION = 'Phpunit Teller';

    /**
     * Production carries user.open_designation (BizLMS); the PHPUnit site does
     * not. Same definition as local_sentientia_org\test\bizlms_fixture.
     */
    private static function ensure_designation_column(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('user');
        $field = new \xmldb_field('open_designation', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    /**
     * One skill required at level 3 for DESIGNATION; nobody holds it, so
     * every rebuilt user gets exactly one gap row.
     *
     * @return int skill id
     */
    private function seed_requirement(): int {
        global $DB;
        $now = time();
        $skillid = (int) $DB->insert_record('local_sentientia_skills', (object) [
            'categoryid' => 1, 'name' => 'Cash Handling', 'max_level' => 5,
            'sort_order' => 0, 'timecreated' => $now,
        ]);
        $DB->insert_record('local_sentientia_role_skills', (object) [
            'designation' => self::DESIGNATION, 'skillid' => $skillid,
            'required_level' => 3, 'timecreated' => $now,
        ]);
        return $skillid;
    }

    /**
     * Create users holding a designation, on tenant 1.
     *
     * @param int $count
     * @param string $designation
     * @param array $extra extra user fields (suspended, deleted)
     * @return int[] user ids
     */
    private function users(int $count, string $designation = self::DESIGNATION, array $extra = []): array {
        global $DB;
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $u = $this->getDataGenerator()->create_user();
            $DB->set_field('user', 'open_path', '/1', ['id' => $u->id]);
            $DB->set_field('user', 'open_designation', $designation, ['id' => $u->id]);
            foreach ($extra as $name => $value) {
                $DB->set_field('user', $name, $value, ['id' => $u->id]);
            }
            $ids[] = (int) $u->id;
        }
        return $ids;
    }

    /**
     * Users with a rebuilt feed (at least one gap row).
     *
     * @param int[] $userids
     * @return int how many of $userids have gap rows
     */
    private static function with_feed(array $userids): int {
        global $DB;
        if (!$userids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {" . gap_engine::GAP_TABLE . "} WHERE userid $insql", $params);
    }

    /**
     * Five matching users with a batch size of 2: three batches, all five
     * rebuilt (the old task stopped after its first batch). Suspended and
     * deleted users and other designations are left out.
     */
    public function test_rebuild_all_walks_every_batch(): void {
        self::ensure_designation_column();
        $this->seed_requirement();

        $active    = $this->users(5);
        $suspended = $this->users(1, self::DESIGNATION, ['suspended' => 1]);
        $deleted   = $this->users(1, self::DESIGNATION, ['deleted' => 1]);
        $other     = $this->users(1, 'Phpunit Driver');

        $result = rebuild_gap_feed::rebuild_all([self::DESIGNATION], 2);

        $this->assertSame(['processed' => 5, 'batches' => 3, 'failed' => 0], $result);
        $this->assertSame(5, self::with_feed($active), 'every matching user is rebuilt, past the first batch');
        $this->assertSame(0, self::with_feed(array_merge($suspended, $deleted, $other)));
    }

    /**
     * A user count that is an exact multiple of the batch size ends cleanly:
     * the empty query after the last full batch is not counted as a batch.
     */
    public function test_rebuild_all_exact_multiple_of_batch_size(): void {
        self::ensure_designation_column();
        $this->seed_requirement();
        $active = $this->users(4);

        $result = rebuild_gap_feed::rebuild_all([self::DESIGNATION], 2);

        $this->assertSame(['processed' => 4, 'batches' => 2, 'failed' => 0], $result);
        $this->assertSame(4, self::with_feed($active));
    }

    /**
     * No designations: nothing is read or rebuilt.
     */
    public function test_rebuild_all_without_designations_is_a_no_op(): void {
        self::ensure_designation_column();
        $this->assertSame(['processed' => 0, 'batches' => 0, 'failed' => 0], rebuild_gap_feed::rebuild_all([]));
    }

    /**
     * The scheduled task, flag ON: rebuilds every matching user and says so.
     */
    public function test_execute_with_flag_on_rebuilds_every_matching_user(): void {
        self::ensure_designation_column();
        $this->setAdminUser();
        feature_flags::set('sentientia.skillsai.gap_engine', 0, true, (int) get_admin()->id, 'phpunit');
        $this->seed_requirement();
        $active = $this->users(3);

        $this->expectOutputRegex('/rebuilt gap feed for 3 users in 1 batch\(es\) of up to '
            . rebuild_gap_feed::BATCH_SIZE . '\./');
        (new rebuild_gap_feed())->execute();

        $this->assertSame(3, self::with_feed($active));
    }

    /**
     * The scheduled task, flag OFF (the default): touches nothing.
     */
    public function test_execute_with_flag_off_does_nothing(): void {
        self::ensure_designation_column();
        $this->seed_requirement();
        $active = $this->users(2);

        $this->expectOutputRegex('/gap_engine flag OFF/');
        (new rebuild_gap_feed())->execute();

        $this->assertSame(0, self::with_feed($active));
    }
}
