<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Decision 5 (2026-09-26): local_sentientia_mgr_allocations holds one
 * allocation per (userid, item_type, itemid), no longer one per
 * (userid, courseid).
 *
 * The course-only first schema made (userid, courseid) UNIQUE and nothing
 * relaxed it when typed allocations arrived. Classroom, program and learning
 * path allocations all store courseid 0, so a user could hold only ONE of
 * them: the second failed with a database duplicate-key error. idx_user_course
 * is now a plain lookup index; idx_user_item (userid, item_type, itemid, UNIQUE)
 * is the rule for every type, courses included (a course row carries
 * itemid = courseid). Upgrade step 2026092600 brings an existing site to the
 * same keys, resolving any duplicate rows first (oldest kept).
 *
 * @package    local_sentientia_manager
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_manager/db/upgradelib.php');

/**
 * @covers \local_sentientia_manager\approval_manager
 * @covers ::local_sentientia_manager_fix_allocation_keys
 * @covers ::local_sentientia_manager_index_uniqueness
 * @group tenant_isolation
 */
final class allocation_index_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var string the table under test */
    private const TABLE = 'local_sentientia_mgr_allocations';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        // Legacy org seam (the default): the supervisor walk reads open_supervisorid.
        set_config('org_legacy', 1, 'local_sentientia_core');
    }

    /** A user at $path, reloaded so the record carries open_path. */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A user at $path whose supervisor (open_supervisorid) is $mgr. */
    private function report_of(\stdClass $mgr, string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $DB->set_field('user', 'open_supervisorid', $mgr->id, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A visible course at $path with a manual enrol instance. */
    private function course_at(string $path): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        if (!$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $DB->insert_record('enrol', (object) [
                'enrol' => 'manual', 'courseid' => $course->id,
                'status' => 0, 'sortorder' => 0, 'roleid' => 5,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /**
     * A classroom, a program and a learning path, all in tenant /1, or a skip
     * when one of those plugins is not installed.
     *
     * @return int[] [classroomid, programid, pathid]
     */
    private function typed_items_in_tenant_one(): array {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (['local_sentientia_classroom', 'local_sentientia_programs',
                'local_sentientia_learningpath'] as $table) {
            if (!$dbman->table_exists($table)) {
                $this->markTestSkipped("{$table} is not installed.");
            }
        }
        $now = time();
        $classroomid = (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'ILT', 'costcenterid' => 1, 'open_path' => '/1',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $programid = (int) $DB->insert_record('local_sentientia_programs', (object) [
            'name' => 'Certification', 'costcenterid' => 1, 'open_path' => '/1',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $pathid = (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => 'Onboarding', 'description' => '', 'descriptionformat' => 1,
            'costcenterid' => 1, 'open_path' => '/1', 'status' => 1, 'visible' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        return [$classroomid, $programid, $pathid];
    }

    /** Write an allocation row directly, bypassing approval_manager. */
    private function insert_row(int $userid, string $itemtype, int $itemid, int $courseid,
                                int $timecreated = 1000): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'managerid'    => 2,
            'userid'       => $userid,
            'item_type'    => $itemtype,
            'itemid'       => $itemid,
            'courseid'     => $courseid,
            'status'       => approval_manager::ALLOC_ASSIGNED,
            'timecreated'  => $timecreated,
            'timemodified' => $timecreated,
        ]);
    }

    /**
     * Put the table's index on $fields into a chosen state, to recreate the
     * schema of an older site: true = UNIQUE, false = plain, null = absent.
     */
    private function set_index(string $name, array $fields, ?bool $unique): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(self::TABLE);
        $index = new \xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, $fields);
        // index_exists() and drop_index() match on the columns, not the name.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        if ($unique !== null) {
            $dbman->add_index($table, new \xmldb_index($name,
                $unique ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE, $fields));
        }
    }

    /** Whether the table's index on $fields is unique (true), plain (false) or absent (null). */
    private function index_state(array $fields): ?bool {
        return local_sentientia_manager_index_uniqueness(self::TABLE, $fields);
    }

    /** Bring the table back to the keys db/install.xml declares. */
    private function restore_install_keys(): void {
        global $DB;
        local_sentientia_manager_fix_allocation_keys($DB->get_manager());
    }

    /** Assert $fn throws a moodle_exception carrying $errorcode. */
    private function assert_refused(callable $fn, string $errorcode, string $message): void {
        try {
            $fn();
            $this->fail($message . ' (no exception)');
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $message);
        }
    }

    /** Assert inserting a row with these values is refused by a unique index. */
    private function assert_insert_refused(int $userid, string $itemtype, int $itemid,
                                           int $courseid, string $message): void {
        try {
            $this->insert_row($userid, $itemtype, $itemid, $courseid);
            $this->fail($message . ' (the row was written)');
        } catch (\dml_write_exception $e) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_a_fresh_install_has_the_keys_the_upgrade_step_makes(): void {
        global $DB;
        $this->assertTrue($this->index_state(['userid', 'item_type', 'itemid']),
            'idx_user_item is UNIQUE.');
        $this->assertFalse($this->index_state(['userid', 'courseid']),
            'idx_user_course is a plain index, not a unique one.');
        // Fresh install == upgraded site: the upgrade step finds nothing to do.
        $this->assertSame([], local_sentientia_manager_fix_allocation_keys($DB->get_manager()));
    }

    public function test_a_user_can_hold_a_classroom_a_program_and_a_path_allocation(): void {
        global $DB;
        [$classroomid, $programid, $pathid] = $this->typed_items_in_tenant_one();
        $mgr = $this->user_at('/1/2');
        $report = $this->report_of($mgr, '/1/2');
        $colleague = $this->report_of($mgr, '/1/3');
        $course = $this->course_at('/1');
        $this->setUser($mgr);
        // The enrol helpers of other plugins may open their own transactions.
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $ids = [
            approval_manager::create_classroom_allocation((int) $mgr->id, (int) $report->id, $classroomid),
            // The second typed allocation is the one the unique (userid, courseid)
            // index refused: it also carries courseid 0.
            approval_manager::create_program_allocation((int) $mgr->id, (int) $report->id, $programid),
            approval_manager::create_path_allocation((int) $mgr->id, (int) $report->id, $pathid),
            approval_manager::create_allocation((int) $mgr->id, (int) $report->id, (int) $course->id),
        ];
        $this->assertCount(4, array_unique($ids));
        $this->assertSame(4, $DB->count_records(self::TABLE, ['userid' => $report->id]));
        $this->assertSame(3, $DB->count_records(self::TABLE,
            ['userid' => $report->id, 'courseid' => 0]),
            'Three typed allocations for one user, every one with courseid 0.');
        foreach ([approval_manager::ITEM_CLASSROOM => $classroomid,
                approval_manager::ITEM_PROGRAM => $programid,
                approval_manager::ITEM_PATH => $pathid] as $type => $itemid) {
            $this->assertTrue($DB->record_exists(self::TABLE,
                ['userid' => $report->id, 'item_type' => $type, 'itemid' => $itemid]), $type);
        }

        // Another user may hold the same items.
        approval_manager::create_classroom_allocation((int) $mgr->id, (int) $colleague->id, $classroomid);
        approval_manager::create_program_allocation((int) $mgr->id, (int) $colleague->id, $programid);
        $this->assertSame(2, $DB->count_records(self::TABLE, ['userid' => $colleague->id]));
        $sink->close();
    }

    public function test_the_same_typed_allocation_is_still_refused(): void {
        global $DB;
        [$classroomid, $programid, $pathid] = $this->typed_items_in_tenant_one();
        $mgr = $this->user_at('/1/2');
        $report = $this->report_of($mgr, '/1/2');
        $this->setUser($mgr);
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        approval_manager::create_classroom_allocation((int) $mgr->id, (int) $report->id, $classroomid);
        approval_manager::create_program_allocation((int) $mgr->id, (int) $report->id, $programid);
        approval_manager::create_path_allocation((int) $mgr->id, (int) $report->id, $pathid);

        $this->assert_refused(fn() => approval_manager::create_classroom_allocation((int) $mgr->id,
            (int) $report->id, $classroomid), 'duplicateallocation', 'The same classroom twice.');
        $this->assert_refused(fn() => approval_manager::create_program_allocation((int) $mgr->id,
            (int) $report->id, $programid), 'duplicateallocation', 'The same program twice.');
        $this->assert_refused(fn() => approval_manager::create_path_allocation((int) $mgr->id,
            (int) $report->id, $pathid), 'duplicateallocation', 'The same path twice.');
        $this->assertSame(3, $DB->count_records(self::TABLE, ['userid' => $report->id]));
        $sink->close();
    }

    public function test_the_unique_index_is_per_user_type_and_item(): void {
        global $DB;
        $this->preventResetByRollback();
        $user = (int) $this->getDataGenerator()->create_user()->id;
        $other = (int) $this->getDataGenerator()->create_user()->id;

        // Same user, same item id, three types, all with courseid 0: allowed.
        $this->insert_row($user, approval_manager::ITEM_CLASSROOM, 5, 0);
        $this->insert_row($user, approval_manager::ITEM_PROGRAM, 5, 0);
        $this->insert_row($user, approval_manager::ITEM_PATH, 5, 0);
        $this->insert_row($user, approval_manager::ITEM_PATH, 6, 0);
        $this->insert_row($other, approval_manager::ITEM_PROGRAM, 5, 0);
        $this->assertSame(4, $DB->count_records(self::TABLE, ['userid' => $user, 'courseid' => 0]));

        // The database itself still refuses a repeat of (user, type, item),
        // even for a writer that skips approval_manager's check.
        $this->assert_insert_refused($user, approval_manager::ITEM_PROGRAM, 5, 0,
            'A second (user, program, 5) row.');
        // Courses are covered by the same index: a course row has itemid = courseid.
        $this->insert_row($user, approval_manager::ITEM_COURSE, 9, 9);
        $this->assert_insert_refused($user, approval_manager::ITEM_COURSE, 9, 9,
            'A second (user, course, 9) row.');
        $this->assertSame(5, $DB->count_records(self::TABLE, ['userid' => $user]));
    }

    public function test_the_upgrade_step_relaxes_the_legacy_unique_course_index(): void {
        global $DB;
        $this->preventResetByRollback();
        $user = (int) $this->getDataGenerator()->create_user()->id;
        try {
            // The keys of a site upgraded to 2026092501: idx_user_course UNIQUE.
            $this->set_index('idx_user_course', ['userid', 'courseid'], true);
            $this->assertTrue($this->index_state(['userid', 'courseid']));
            $this->insert_row($user, approval_manager::ITEM_COURSE, 11, 11);
            $this->insert_row($user, approval_manager::ITEM_CLASSROOM, 5, 0);
            // The defect: a second typed allocation collides on (userid, 0).
            $this->assert_insert_refused($user, approval_manager::ITEM_PROGRAM, 7, 0,
                'Precondition: the legacy index refuses a second typed allocation.');

            $lines = local_sentientia_manager_fix_allocation_keys($DB->get_manager());

            $this->assertSame([
                'dropped the unique index idx_user_course (userid, courseid)',
                'added idx_user_course (userid, courseid) as a non-unique index',
            ], $lines, 'Nothing else changes on a site whose rows are sound.');
            $this->assertFalse($this->index_state(['userid', 'courseid']));
            $this->assertTrue($this->index_state(['userid', 'item_type', 'itemid']));
            $this->insert_row($user, approval_manager::ITEM_PROGRAM, 7, 0);
            $this->insert_row($user, approval_manager::ITEM_PATH, 8, 0);
            $this->assertSame(4, $DB->count_records(self::TABLE, ['userid' => $user]));
            $this->assertSame([], local_sentientia_manager_fix_allocation_keys($DB->get_manager()),
                'A second run changes nothing.');
        } finally {
            $this->restore_install_keys();
        }
    }

    public function test_the_upgrade_step_resolves_duplicates_keeping_the_oldest(): void {
        global $DB;
        $this->preventResetByRollback();
        $a = (int) $this->getDataGenerator()->create_user()->id;
        $b = (int) $this->getDataGenerator()->create_user()->id;
        try {
            // A site where idx_user_item has lost its uniqueness, so duplicate
            // rows could be written. idx_user_course is already plain.
            $this->set_index('idx_user_item', ['userid', 'item_type', 'itemid'], false);
            $this->assertFalse($this->index_state(['userid', 'item_type', 'itemid']));

            $classroom = approval_manager::ITEM_CLASSROOM;
            $course = approval_manager::ITEM_COURSE;
            $r1 = $this->insert_row($a, $classroom, 7, 0, 300);
            $r2 = $this->insert_row($a, $classroom, 7, 0, 100);   // Oldest: kept.
            $r3 = $this->insert_row($a, $classroom, 7, 0, 100);   // Same age, larger id.
            $c1 = $this->insert_row($a, $course, 41, 41, 20);     // Oldest: kept.
            $c2 = $this->insert_row($a, $course, 0, 41, 50);      // itemid never filled: course 41 too.
            $c3 = $this->insert_row($a, $course, 0, 42, 60);      // Never filled, but course 42: no duplicate.
            $b1 = $this->insert_row($b, $classroom, 7, 0, 500);   // Another user: no duplicate.

            $lines = local_sentientia_manager_fix_allocation_keys($DB->get_manager());

            $this->assertEqualsCanonicalizing([$r2, $c1, $c3, $b1],
                array_map('intval', array_keys($DB->get_records(self::TABLE, null, '', 'id'))),
                'The oldest row of each key survives; ties go to the smaller id.');
            $this->assertSame(42, (int) $DB->get_field(self::TABLE, 'itemid', ['id' => $c3]),
                'A never-filled course row gets itemid = courseid.');
            $this->assertSame(41, (int) $DB->get_field(self::TABLE, 'itemid', ['id' => $c1]));

            $removed = array_values(array_filter($lines,
                fn($l) => strpos($l, 'removed duplicate allocation') === 0));
            $this->assertCount(3, $removed, 'One line for every row removed.');
            foreach ([[$r1, $r2], [$r3, $r2], [$c2, $c1]] as [$gone, $kept]) {
                $matches = array_filter($removed,
                    fn($l) => strpos($l, "removed duplicate allocation id={$gone} ") === 0
                        && substr($l, -strlen("kept id={$kept}")) === "kept id={$kept}");
                $this->assertCount(1, $matches, "Row {$gone} is reported as removed in favour of {$kept}.");
            }
            $this->assertContains('set itemid = courseid on 1 course allocation(s) written without it', $lines);
            $this->assertContains('dropped the non-unique index on (userid, item_type, itemid)', $lines);
            $this->assertContains('created the unique index idx_user_item (userid, item_type, itemid)', $lines);
            $this->assertCount(6, $lines, 'And nothing else.');

            $this->assertTrue($this->index_state(['userid', 'item_type', 'itemid']));
            $this->assertFalse($this->index_state(['userid', 'courseid']));
            $this->assertSame([], local_sentientia_manager_fix_allocation_keys($DB->get_manager()),
                'A second run changes nothing.');
        } finally {
            $this->restore_install_keys();
        }
    }

    public function test_course_allocations_are_unchanged(): void {
        global $DB;
        $mgr = $this->user_at('/1/2');
        $report = $this->report_of($mgr, '/1/2');
        $first = $this->course_at('/1');
        $second = $this->course_at('/1/5');
        $this->setUser($mgr);
        $sink = $this->redirectMessages();

        $id = approval_manager::create_allocation((int) $mgr->id, (int) $report->id, (int) $first->id);
        $row = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(approval_manager::ITEM_COURSE, (string) $row->item_type);
        $this->assertSame((int) $first->id, (int) $row->itemid, 'A course row carries itemid = courseid.');
        $this->assertSame((int) $first->id, (int) $row->courseid);
        $this->assertTrue(is_enrolled(\context_course::instance($first->id), $report));

        approval_manager::create_allocation((int) $mgr->id, (int) $report->id, (int) $second->id);
        $this->assert_refused(fn() => approval_manager::create_allocation((int) $mgr->id,
            (int) $report->id, (int) $first->id),
            'duplicateallocation', 'The same course twice is still refused.');
        $this->assertSame(2, $DB->count_records(self::TABLE, ['userid' => $report->id]));
        $sink->close();
    }
}
