<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * ADR-032: what changes in the path code when the BizLMS learningplan history is imported.
 *
 *  - The two fallbacks that read BizLMS tables are gone (is_enrolled, count_paths).
 *  - Imported history is protected (decision framework.protect_imported_history = block): an imported
 *    enrolment that carries history cannot be unenrolled (one that carries none yet can: LRN-10, see
 *    imported_unenrol_test), an imported course row cannot be unassigned, and a path that holds
 *    imported rows cannot be deleted.
 *  - A path delete cascades over the per-course status rows too.
 *  - The plugin file callback refuses what it does not serve.
 *
 * "Imported" is what the import's map says, so these tests write map rows by hand instead of running the
 * importer (bizlms_import_test runs it).
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @covers     \local_sentientia_learningpath\path_manager
 * @group local_sentientia_learningpath
 * @group tenant_isolation
 */
final class imported_history_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use \local_sentientia_org\test\bizlms_fixture;

    protected static function legacy_fixture_definition(): array {
        return [
            'xml' => __DIR__ . '/fixtures/bizlms/learningplan.install.xml',
            'only' => ['local_learningplan', 'local_learningplan_user'],
        ];
    }

    private function path(?string $openpath, string $name = 'Path'): int {
        global $DB;
        // Not in setUp(): the legacy_schema_fixture trait owns setUp() and the class must not define one.
        $this->ensure_bizlms_schema();
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => $name, 'description' => '', 'costcenterid' => 0, 'open_path' => $openpath, 'status' => 1,
            'visible' => 1, 'timecreated' => 100, 'timemodified' => 100,
        ]);
    }

    private function enrolment(int $pathid, int $userid, int $status = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath_users', (object) [
            'pathid' => $pathid, 'userid' => $userid, 'status' => $status, 'timecreated' => 100, 'timemodified' => 100,
        ]);
    }

    /**
     * Record that the import created a target row.
     *
     * @param string $table Target table.
     * @param int $id Target id.
     * @return void
     */
    private function imported(string $table, int $id): void {
        global $DB;
        static $n = 0;
        $n++;
        $DB->insert_record(legacymap::TABLE, (object) ['feature' => 'learningplan', 'sourcetable' => 'test_' . $table,
            'sourceid' => $id + 1000 * $n, 'subkey' => '', 'targettable' => $table, 'targetid' => $id,
            'outcome' => 'imported', 'reason' => null, 'detail' => null, 'runid' => 0, 'timecreated' => time()]);
    }

    public function test_is_enrolled_no_longer_falls_back_to_the_bizlms_table(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $pathid = $this->path('/1');
        // A BizLMS row with planid = pathid, and nothing in the Sentientia table.
        $DB->import_record('local_learningplan_user', (object) ['id' => 1, 'planid' => $pathid, 'userid' => $user->id,
            'status' => null, 'completiondate' => null, 'timecreated' => 100, 'timemodified' => 0, 'usercreated' => 0,
            'usermodified' => 0]);
        $this->assertFalse(path_manager::is_enrolled($pathid, (int) $user->id));
        $this->enrolment($pathid, (int) $user->id);
        $this->assertTrue(path_manager::is_enrolled($pathid, (int) $user->id));
    }

    public function test_count_paths_has_no_bizlms_fallback_and_a_bounded_prefix(): void {
        global $DB;
        $this->resetAfterTest();
        // BizLMS plans exist and the Sentientia table is empty: the old fallback returned 2.
        foreach ([1, 2] as $id) {
            $DB->import_record('local_learningplan', (object) ['id' => $id, 'name' => 'P' . $id, 'shortname' => 's',
                'visible' => 1, 'lpsequence' => 0, 'selfenrol' => 0, 'open_path' => '/1', 'costcenter' => 1,
                'timecreated' => 100, 'timemodified' => 100, 'usercreated' => 0, 'usermodified' => 0]);
        }
        $this->assertSame(0, path_manager::count_paths());

        foreach (['/1', '/1/5', '/10', '/177', null] as $openpath) {
            $this->path($openpath);
        }
        $this->assertSame(5, path_manager::count_paths());
        $this->assertSame(2, path_manager::count_paths('/1'), '/1 and /1/5, never /10 or /177');
        $this->assertSame(2, path_manager::count_paths('/1/%'), 'the older "/1/%" form means the same');
        $this->assertSame(1, path_manager::count_paths('/1/5'));
        $this->assertSame(1, path_manager::count_paths('/10'));
    }

    public function test_an_imported_enrolment_cannot_be_unenrolled(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $pathid = $this->path('/1');
        $importedid = $this->enrolment($pathid, (int) $user->id, path_manager::ENROL_COMPLETED);
        $nativeid = $this->enrolment($pathid, (int) $other->id);
        $this->imported('local_sentientia_learningpath_users', $importedid);

        try {
            path_manager::unenrol_user($pathid, (int) $user->id);
            $this->fail('an imported enrolment is history and is protected');
        } catch (\moodle_exception $e) {
            $this->assertSame('imported_history_protected', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_learningpath_users', ['id' => $importedid]));

        // A learner enrolled on the site is removed as before.
        $this->assertTrue(path_manager::unenrol_user($pathid, (int) $other->id));
        $this->assertFalse($DB->record_exists('local_sentientia_learningpath_users', ['id' => $nativeid]));
        // Not on the path at all: false, as before.
        $this->assertFalse(path_manager::unenrol_user($pathid, (int) $other->id));
    }

    public function test_an_imported_course_row_cannot_be_unassigned(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $imported = $this->getDataGenerator()->create_course();
        $native = $this->getDataGenerator()->create_course();
        $pathid = $this->path('/1');
        $rows = [];
        foreach ([$imported, $native] as $sortorder => $course) {
            $rows[(int) $course->id] = (int) $DB->insert_record('local_sentientia_learningpath_courses', (object) [
                'pathid' => $pathid, 'courseid' => $course->id, 'sortorder' => $sortorder, 'mandatory' => 1,
                'timecreated' => 100, 'timemodified' => 100,
            ]);
        }
        $this->imported('local_sentientia_learningpath_courses', $rows[(int) $imported->id]);

        // The imported row carries the plan's mandatory flag, order, creator and created time: protected.
        try {
            path_manager::unassign_course($pathid, (int) $imported->id);
            $this->fail('an imported course row is history and is protected');
        } catch (\moodle_exception $e) {
            $this->assertSame('imported_history_protected', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_learningpath_courses', ['id' => $rows[(int) $imported->id]]));

        // A course put on the path on the site is removed as before; one that is not on the path is false.
        $this->assertTrue(path_manager::unassign_course($pathid, (int) $native->id));
        $this->assertFalse($DB->record_exists('local_sentientia_learningpath_courses', ['id' => $rows[(int) $native->id]]));
        $this->assertFalse(path_manager::unassign_course($pathid, (int) $native->id));
    }

    public function test_a_path_with_imported_rows_cannot_be_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();

        // The path itself was imported.
        $imported = $this->path('/1', 'Imported');
        $this->imported('local_sentientia_learningpath', $imported);
        // A site path that holds one imported learner row.
        $mixed = $this->path('/1', 'Mixed');
        $this->imported('local_sentientia_learningpath_users', $this->enrolment($mixed, (int) $user->id));
        // A site path.
        $native = $this->path('/1', 'Native');

        foreach ([$imported, $mixed] as $pathid) {
            try {
                path_manager::delete($pathid);
                $this->fail('path ' . $pathid . ' holds imported history');
            } catch (\moodle_exception $e) {
                $this->assertSame('imported_history_protected', $e->errorcode);
            }
            $this->assertTrue($DB->record_exists('local_sentientia_learningpath', ['id' => $pathid]));
        }
        // Archiving is the way to retire it.
        $this->assertFalse(path_manager::toggle_status($imported, false));
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_learningpath', 'status', ['id' => $imported]));

        $this->assertTrue(path_manager::delete($native));
        $this->assertFalse($DB->record_exists('local_sentientia_learningpath', ['id' => $native]));
    }

    public function test_deleting_a_path_removes_its_course_status_rows_and_cover(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $pathid = $this->path('/1');
        $keep = $this->path('/1', 'Keep');
        foreach ([$pathid, $keep] as $id) {
            $DB->insert_record('local_sentientia_lp_course_status', (object) ['pathid' => $id, 'courseid' => $course->id,
                'userid' => $user->id, 'status' => 1, 'percentage' => 10, 'timecreated' => 100, 'timemodified' => 100]);
        }
        $fs = get_file_storage();
        $systemid = \context_system::instance()->id;
        $fs->create_file_from_string(['contextid' => $systemid, 'component' => 'local_sentientia_learningpath',
            'filearea' => 'summaryfile', 'itemid' => $pathid, 'filepath' => '/', 'filename' => 'cover.txt'], 'x');

        path_manager::delete($pathid);
        $this->assertSame(0, $DB->count_records('local_sentientia_lp_course_status', ['pathid' => $pathid]));
        $this->assertSame(1, $DB->count_records('local_sentientia_lp_course_status', ['pathid' => $keep]));
        $this->assertFalse($fs->file_exists($systemid, 'local_sentientia_learningpath', 'summaryfile', $pathid, '/', 'cover.txt'));
    }

    public function test_native_rows_record_who_made_them(): void {
        global $DB;
        $this->resetAfterTest();
        $admin = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($admin);

        $pathid = path_manager::create((object) ['name' => 'Made on site', 'costcenterid' => 0], '/1');
        $path = $DB->get_record('local_sentientia_learningpath', ['id' => $pathid], '*', MUST_EXIST);
        $this->assertSame([(int) $admin->id, (int) $admin->id], [(int) $path->usercreated, (int) $path->usermodified]);

        path_manager::assign_courses($pathid, [(int) $course->id]);
        $row = $DB->get_record('local_sentientia_learningpath_courses', ['pathid' => $pathid], '*', MUST_EXIST);
        $this->assertSame([(int) $admin->id, (int) $admin->id], [(int) $row->usercreated, (int) $row->usermodified]);
        $this->assertGreaterThan(0, (int) $row->timemodified);

        path_manager::enrol_users($pathid, [(int) $learner->id]);
        $enrolment = $DB->get_record('local_sentientia_learningpath_users', ['pathid' => $pathid], '*', MUST_EXIST);
        $this->assertSame((int) $admin->id, (int) $enrolment->enrolledby);
        $this->assertGreaterThan(0, (int) $enrolment->timemodified);
    }

    public function test_the_file_callback_serves_only_the_cover_area_in_the_system_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once(__DIR__ . '/../lib.php');
        $system = \context_system::instance();
        $this->assertFalse(local_sentientia_learningpath_pluginfile(null, null, $system, 'elsewhere', [1, 'a.png'], false));
        $course = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $this->assertFalse(local_sentientia_learningpath_pluginfile(null, null, $course, 'summaryfile', [1, 'a.png'], false));
    }
}
