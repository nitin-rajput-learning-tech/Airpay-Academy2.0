<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_learningpath\external\unenrol_user;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\feature_flags;

/**
 * Owner decisions LRN-08 and LRN-10 (2026-10-07) on a learning path.
 *
 * LRN-10: an admin may unenrol an imported enrolment that carries no history yet (not started, no per-course progress,
 * path active); everything that carries history stays blocked. The learner keeps the manual course enrolments the G6
 * import converted from that plan's BizLMS instances (BizLMS removed them), so the result lists them with a link to
 * each course's participants page; nothing is removed automatically.
 *
 * LRN-08: the BizLMS cover image shows on the admin path page only while the learner paths flag is ON.
 *
 * "Imported" is what the platform's map says, so these tests write map rows by hand (bizlms_import_test runs the
 * importer).
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_learningpath\path_manager
 * @covers     \local_sentientia_learningpath\external\unenrol_user
 * @group local_sentientia_learningpath
 * @group bizlms_import
 * @group tenant_isolation
 */
final class imported_unenrol_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        feature_flags::invalidate_caches();
    }

    private function path(int $status = path_manager::STATUS_ACTIVE, string $name = 'Path'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => $name, 'description' => '', 'costcenterid' => 0, 'open_path' => '/1', 'status' => $status,
            'visible' => 1, 'timecreated' => 100, 'timemodified' => 100,
        ]);
    }

    private function enrolment(int $pathid, \stdClass $user, int $status = path_manager::ENROL_NEW, ?int $completed = null): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath_users', (object) [
            'pathid' => $pathid, 'userid' => $user->id, 'status' => $status, 'timecompleted' => $completed,
            'timecreated' => 100, 'timemodified' => 100,
        ]);
    }

    private function course_status(int $pathid, \stdClass $user, int $courseid, array $values): void {
        global $DB;
        $DB->insert_record('local_sentientia_lp_course_status', (object) ($values + [
            'pathid' => $pathid, 'courseid' => $courseid, 'userid' => $user->id, 'status' => 0, 'percentage' => 0,
            'timecreated' => 100, 'timemodified' => 100,
        ]));
    }

    private function map(string $feature, string $sourcetable, int $sourceid, string $targettable, int $targetid,
                         string $outcome = 'imported'): void {
        global $DB;
        $DB->insert_record(legacymap::TABLE, (object) ['feature' => $feature, 'sourcetable' => $sourcetable,
            'sourceid' => $sourceid, 'subkey' => '', 'targettable' => $targettable, 'targetid' => $targetid,
            'outcome' => $outcome, 'reason' => null, 'detail' => null, 'runid' => 0, 'timecreated' => time()]);
    }

    private function imported(int $enrolmentid): void {
        static $n = 0;
        $n++;
        $this->map('learningplan', 'local_learningplan_user', $enrolmentid + 1000 * $n,
            'local_sentientia_learningpath_users', $enrolmentid);
    }

    /**
     * Insert a row into a core table, filling every NOT NULL column that has no default.
     *
     * @param string $table
     * @param array $values
     * @return int
     */
    private function core_row(string $table, array $values): int {
        global $DB;
        $row = new \stdClass();
        foreach ($DB->get_columns($table) as $name => $column) {
            if ($name === 'id') {
                continue;
            }
            if (array_key_exists($name, $values)) {
                $row->$name = $values[$name];
            } else if ($column->not_null && !$column->has_default) {
                $row->$name = in_array($column->meta_type, ['I', 'R', 'N', 'F'], true) ? 0 : '';
            }
        }
        return (int) $DB->insert_record($table, $row);
    }

    /**
     * What the G6 import did for one learner on one plan: the BizLMS enrolment on the plan's instance (left in
     * place), the manual enrolment it was converted to, and the map row that says so.
     *
     * @param int $pathid
     * @param \stdClass $user
     * @param \stdClass $course
     * @param string $outcome imported or folded
     * @return void
     */
    private function converted(int $pathid, \stdClass $user, \stdClass $course, string $outcome = 'imported'): void {
        global $DB;
        $planinstance = $this->core_row('enrol', ['enrol' => 'learningplan', 'courseid' => $course->id, 'status' => 1,
            'customint1' => $pathid, 'sortorder' => 50]);
        $manual = (int) $DB->get_field('enrol', 'id', ['enrol' => 'manual', 'courseid' => $course->id], IGNORE_MULTIPLE);
        if (!$manual) {
            $manual = $this->core_row('enrol', ['enrol' => 'manual', 'courseid' => $course->id, 'status' => 0, 'sortorder' => 1]);
        }
        $legacy = $this->core_row('user_enrolments', ['enrolid' => $planinstance, 'userid' => $user->id, 'status' => 0,
            'timestart' => 0, 'timeend' => 0, 'timecreated' => 100, 'timemodified' => 100]);
        $new = $this->core_row('user_enrolments', ['enrolid' => $manual, 'userid' => $user->id, 'status' => 0,
            'timestart' => 0, 'timeend' => 0, 'timecreated' => 100, 'timemodified' => 100]);
        $this->map('enrolments', '#user_enrolments.id', $legacy, 'user_enrolments', $new, $outcome);
    }

    private function refused(int $pathid, \stdClass $user, string $why): void {
        global $DB;
        try {
            path_manager::unenrol_user($pathid, (int) $user->id);
            $this->fail($why . ': history was removed');
        } catch (\moodle_exception $e) {
            $this->assertSame('imported_history_protected', $e->errorcode, $why);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_learningpath_users',
            ['pathid' => $pathid, 'userid' => $user->id]), $why . ': the row is still there');
    }

    // LRN-10.

    public function test_an_imported_enrolment_with_no_history_on_an_active_path_can_be_removed(): void {
        global $DB;
        $pathid = $this->path();
        $user = $this->getDataGenerator()->create_user();
        $id = $this->enrolment($pathid, $user);
        $this->imported($id);
        // A per-course status row that only says "not started" is no progress.
        $course = $this->getDataGenerator()->create_course();
        $this->course_status($pathid, $user, (int) $course->id, []);

        $this->assertTrue(path_manager::unenrol_user($pathid, (int) $user->id));
        $this->assertFalse($DB->record_exists('local_sentientia_learningpath_users', ['id' => $id]));
        $this->assertTrue($DB->record_exists(legacymap::TABLE, ['targettable' => 'local_sentientia_learningpath_users',
            'targetid' => $id]), 'the import\'s map entry stays as the record of what was imported');
    }

    public function test_an_imported_enrolment_that_carries_history_stays_protected(): void {
        $pathid = $this->path();
        $course = $this->getDataGenerator()->create_course();

        $started = $this->getDataGenerator()->create_user();
        $this->imported($this->enrolment($pathid, $started, path_manager::ENROL_INPROGRESS));
        $this->refused($pathid, $started, 'a learner in progress');

        $done = $this->getDataGenerator()->create_user();
        $this->imported($this->enrolment($pathid, $done, path_manager::ENROL_COMPLETED, 500));
        $this->refused($pathid, $done, 'a completed learner');

        $stamped = $this->getDataGenerator()->create_user();
        $this->imported($this->enrolment($pathid, $stamped, path_manager::ENROL_NEW, 500));
        $this->refused($pathid, $stamped, 'a completion date on a row that says not started');

        foreach (['percentage' => ['percentage' => 40], 'status' => ['status' => 1], 'start' => ['startdate' => 300],
                'completion' => ['completiondate' => 400]] as $name => $values) {
            $user = $this->getDataGenerator()->create_user();
            $this->imported($this->enrolment($pathid, $user));
            $this->course_status($pathid, $user, (int) $course->id, $values);
            $this->refused($pathid, $user, 'per-course progress: ' . $name);
        }
    }

    public function test_an_imported_enrolment_on_an_archived_path_stays_protected(): void {
        $archived = $this->path(path_manager::STATUS_ARCHIVED, 'Old');
        $user = $this->getDataGenerator()->create_user();
        $this->imported($this->enrolment($archived, $user));
        $this->refused($archived, $user, 'a pending learner of an archived path');
    }

    public function test_a_native_enrolment_is_removed_as_before_whatever_it_holds(): void {
        global $DB;
        $pathid = $this->path();
        $user = $this->getDataGenerator()->create_user();
        $this->enrolment($pathid, $user, path_manager::ENROL_COMPLETED, 500);
        $this->assertTrue(path_manager::unenrol_user($pathid, (int) $user->id));
        $this->assertFalse($DB->record_exists('local_sentientia_learningpath_users', ['userid' => $user->id]));
    }

    public function test_the_pure_pending_rule(): void {
        $active = (object) ['status' => path_manager::STATUS_ACTIVE];
        $row = (object) ['id' => 0, 'pathid' => 0, 'userid' => 0, 'status' => path_manager::ENROL_NEW, 'timecompleted' => null];
        $this->assertTrue(path_manager::imported_enrolment_is_pending($active, $row));
        $this->assertFalse(path_manager::imported_enrolment_is_pending((object) ['status' => path_manager::STATUS_ARCHIVED], $row));

        $inprogress = clone $row;
        $inprogress->status = path_manager::ENROL_INPROGRESS;
        $this->assertFalse(path_manager::imported_enrolment_is_pending($active, $inprogress));
        $completed = clone $row;
        $completed->status = path_manager::ENROL_COMPLETED;
        $this->assertFalse(path_manager::imported_enrolment_is_pending($active, $completed));
        $stamped = clone $row;
        $stamped->timecompleted = 500;
        $this->assertFalse(path_manager::imported_enrolment_is_pending($active, $stamped));
    }

    public function test_the_result_lists_the_course_enrolments_that_stay_and_removes_none(): void {
        global $DB;
        $pathid = $this->path();
        $other = $this->path(path_manager::STATUS_ACTIVE, 'Other plan');
        $user = $this->getDataGenerator()->create_user();
        $bystander = $this->getDataGenerator()->create_user();
        $kept = $this->getDataGenerator()->create_course(['fullname' => 'Annual AML & KYC']);
        $second = $this->getDataGenerator()->create_course(['fullname' => 'Anti Bribery']);
        $unrelated = $this->getDataGenerator()->create_course(['fullname' => 'From another plan']);
        $folded = $this->getDataGenerator()->create_course(['fullname' => 'Had it already']);

        $this->imported($this->enrolment($pathid, $user));
        $this->converted($pathid, $user, $kept);
        $this->converted($pathid, $user, $second);
        $this->converted($other, $user, $unrelated);
        $this->converted($pathid, $user, $folded, 'folded');
        $this->converted($pathid, $bystander, $kept);
        $before = $DB->count_records('user_enrolments');

        $remaining = path_manager::plan_course_enrolments($pathid, (int) $user->id);

        $this->assertSame(['Annual AML & KYC', 'Anti Bribery'], array_column($remaining, 'name'),
            'this plan\'s converted enrolments of this learner only, by course name; plain text');
        $this->assertSame([(int) $kept->id, (int) $second->id], array_column($remaining, 'courseid'));
        $this->assertStringContainsString('/user/index.php?id=' . $kept->id, $remaining[0]['url']);
        $this->assertSame([], path_manager::plan_course_enrolments($pathid, (int) $this->getDataGenerator()->create_user()->id));
        $this->assertSame([], path_manager::plan_course_enrolments($other + 100, (int) $user->id));

        $notice = path_manager::unenrol_notice($remaining);
        $this->assertStringContainsString('Annual AML &amp; KYC', $notice, 'escaped once for HTML');
        $this->assertStringNotContainsString('&amp;amp;', $notice);
        $this->assertStringContainsString('<a href="', $notice);
        $this->assertStringContainsString('Anti Bribery', $notice);
        $this->assertStringNotContainsString('From another plan', $notice);
        $this->assertSame('', path_manager::unenrol_notice([]));

        // Unenrolling removes the path row and not one course enrolment.
        $this->assertTrue(path_manager::unenrol_user($pathid, (int) $user->id));
        $this->assertSame($before, $DB->count_records('user_enrolments'), 'nothing is removed automatically');
    }

    public function test_the_web_service_returns_the_remaining_courses_and_the_notice(): void {
        $pathid = $this->path();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Annual AML']);
        $this->imported($this->enrolment($pathid, $user));
        $this->converted($pathid, $user, $course);

        $result = unenrol_user::execute($pathid, (int) $user->id);
        $this->assertTrue($result['removed']);
        $this->assertCount(1, $result['remaining']);
        $this->assertSame((int) $course->id, $result['remaining'][0]['courseid']);
        $this->assertStringContainsString('Annual AML', $result['notice']);
        $this->assertStringContainsString('user/index.php?id=' . $course->id, $result['notice']);

        // A native enrolment has nothing to report; a learner not on the path is not removed.
        $native = $this->getDataGenerator()->create_user();
        $this->enrolment($pathid, $native);
        $this->assertSame(['pathid' => $pathid, 'userid' => (int) $native->id, 'removed' => true, 'remaining' => [], 'notice' => ''],
            unenrol_user::execute($pathid, (int) $native->id));
        $this->assertFalse(unenrol_user::execute($pathid, (int) $native->id)['removed']);
    }

    public function test_the_notice_is_worded_in_english_and_hindi(): void {
        $dir = \core_component::get_component_directory('local_sentientia_learningpath');
        foreach (['en', 'hi'] as $lang) {
            $string = [];
            include($dir . '/lang/' . $lang . '/local_sentientia_learningpath.php');
            $this->assertArrayHasKey('unenrol_courses_remain', $string, $lang);
            $this->assertStringContainsString('{$a}', $string['unenrol_courses_remain'], $lang);
        }
    }

    // LRN-08.

    public function test_the_cover_shows_on_the_admin_path_page_only_with_the_learner_paths_flag_on(): void {
        $pathid = $this->path();
        $none = $this->path(path_manager::STATUS_ACTIVE, 'No cover');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_sentientia_learningpath', 'filearea' => 'summaryfile', 'itemid' => $pathid,
            'filepath' => '/', 'filename' => 'cover.png'], $png);

        $this->assertFalse(learner_paths::enabled());
        $this->assertNull(path_manager::admin_cover_url($pathid), 'flag OFF: the page is what it was before the import');
        $this->assertNotNull(path_manager::cover_url($pathid), 'the file is still copied and served');

        feature_flags::set(learner_paths::FLAG, 0, true);
        $this->assertTrue(learner_paths::enabled());
        $url = path_manager::admin_cover_url($pathid);
        $this->assertNotNull($url);
        $this->assertStringContainsString('cover.png', $url->out(false));
        $this->assertNull(path_manager::admin_cover_url($none), 'a path with no cover has none');

        feature_flags::set(learner_paths::FLAG, 0, null);
        $this->assertNull(path_manager::admin_cover_url($pathid));
    }

    public function test_the_flag_description_covers_the_admin_cover(): void {
        $registry = feature_flags::load_registry();
        $this->assertStringContainsString('cover image', $registry[learner_paths::FLAG]['description']);
        $this->assertFalse($registry[learner_paths::FLAG]['default']);
    }
}
