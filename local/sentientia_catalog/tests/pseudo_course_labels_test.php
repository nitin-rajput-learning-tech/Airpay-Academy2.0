<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * Owner decision CRS-14 (2026-10-07) and the "readers that count enrolments" item of the same day.
 *
 * CRS-14. A BizLMS exam or forum pseudo-course (open_coursetype 1, open_module 'online_exams' or 'forum'):
 *  - is left out of the public guest storefront, in its COUNT and in its SELECT (a guest who saw an exam there could
 *    neither buy it nor enrol: no fee instance, guest and self enrolment disabled);
 *  - stays in a learner's in-progress rail when the learner is enrolled in it (Sentientia's exam pages are manager and
 *    teacher only, so the enrolled course is a learner's only path to an assigned exam), labelled "Exam" or "Forum"
 *    from open_module instead of "E-Learning".
 *
 * Readers. A learner who holds two enrolments in one course (an imported BizLMS method and its converted manual twin)
 * is one learner: the in-progress rail lists the course once, the popularity counts count the learner once, and a
 * suspended enrolment, or one on a disabled instance, counts for nothing.
 *
 * @package    local_sentientia_catalog
 * @category   test
 * @covers     \local_sentientia_catalog\catalog_manager
 * @covers     \local_sentientia_catalog\commerce
 * @group      local_sentientia_catalog
 */
final class pseudo_course_labels_test extends \advanced_testcase {

    // Provisions {user}.open_path, {course}.open_path and the open_level, open_skill and open_coursetype course columns.
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * course.open_module, the BizLMS column that says which kind of pseudo-course a course is. Nullable, as the
     * substrate creates it. Idempotent.
     *
     * @return void
     */
    private function ensure_open_module(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('course');
        $field = new \xmldb_field('open_module', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    /**
     * A visible course in the Public tenant (/77), with its BizLMS course type and module.
     *
     * @param string $name
     * @param int|null $type open_coursetype.
     * @param string|null $module open_module.
     * @return int
     */
    private function make_course(string $name, ?int $type, ?string $module): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course([
            'fullname' => $name,
            'shortname' => 'pcl_' . strtolower(preg_replace('/[^a-z0-9]/i', '', $name)) . '_' . random_int(1000, 9999),
            'visible' => 1,
        ]);
        $DB->set_field('course', 'open_path', '/77', ['id' => $course->id]);
        if ($type !== null) {
            $DB->set_field('course', 'open_coursetype', $type, ['id' => $course->id]);
        }
        if ($module !== null) {
            $DB->set_field('course', 'open_module', $module, ['id' => $course->id]);
        }
        return (int) $course->id;
    }

    /**
     * A second, BizLMS-style enrol instance in a course, and one enrolment of a learner on it.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $instancestatus 0 enabled, 1 disabled.
     * @param int $enrolmentstatus 0 active, 1 suspended.
     * @return void
     */
    private function add_bizlms_enrolment(int $courseid, int $userid, int $instancestatus = 0, int $enrolmentstatus = 0): void {
        global $DB;
        $enrolid = $DB->insert_record('enrol', (object) [
            'enrol' => 'learningplan', 'status' => $instancestatus, 'courseid' => $courseid, 'sortorder' => 90,
            'roleid' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('user_enrolments', (object) [
            'status' => $enrolmentstatus, 'enrolid' => $enrolid, 'userid' => $userid, 'timestart' => time() - 60,
            'timeend' => 0, 'modifierid' => 0, 'timecreated' => time() - 60, 'timemodified' => time() - 60,
        ]);
    }

    /**
     * @param array $rows Formatted courses.
     * @return array<int, array> id => card
     */
    private function by_id(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    public function test_the_public_storefront_leaves_out_pseudo_courses_in_the_list_and_the_total(): void {
        $this->resetAfterTest();
        $this->ensure_open_module();
        $ordinary = $this->make_course('Ordinary public course', null, null);
        $typed0 = $this->make_course('Typed zero public course', 0, null);
        $exam = $this->make_course('Public exam', 1, 'online_exams');
        $forum = $this->make_course('Public forum', 1, 'forum');

        $result = commerce::get_public_catalog('', 'newest', 0, 50);
        $ids = array_map(fn($c) => (int) $c['id'], $result['courses']);

        $this->assertContains($ordinary, $ids, 'a course BizLMS never typed is an ordinary course');
        $this->assertContains($typed0, $ids);
        $this->assertNotContains($exam, $ids, 'a guest cannot buy or join an exam, so it is not offered');
        $this->assertNotContains($forum, $ids);
        $this->assertSame(count($ids), (int) $result['total'], 'the total counts the same courses the page lists');
    }

    public function test_the_ordinary_condition_is_a_plain_sql_condition_for_any_alias(): void {
        $this->resetAfterTest();
        $condition = catalog_manager::ordinary_courses_condition('k');
        $this->assertSame('(k.open_coursetype IS NULL OR k.open_coursetype = 0)', $condition);
        $this->expectException(\coding_exception::class);
        catalog_manager::ordinary_courses_condition('k; DROP TABLE x');
    }

    public function test_the_in_progress_rail_keeps_a_pseudo_course_and_labels_it_by_its_module(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_open_module();
        $learner = $this->getDataGenerator()->create_user();
        $ordinary = $this->make_course('Learner ordinary course', 0, null);
        $exam = $this->make_course('Learner exam', 1, 'online_exams');
        $forum = $this->make_course('Learner forum', 1, 'forum');
        $unknown = $this->make_course('Learner other pseudo-course', 1, 'something_else');
        foreach ([$ordinary, $exam, $forum, $unknown] as $courseid) {
            $this->getDataGenerator()->enrol_user($learner->id, $courseid);
        }
        \cache::make('local_sentientia_catalog', 'in_progress')->purge();
        $this->setUser($learner);

        $cards = $this->by_id(catalog_manager::get_in_progress((int) $learner->id, 20));

        $this->assertArrayHasKey($exam, $cards, 'the enrolled exam is the learner\'s only path to it, so it stays');
        $this->assertSame(get_string('coursetype_exam', 'local_sentientia_catalog'), $cards[$exam]['type']);
        $this->assertSame(get_string('coursetype_forum', 'local_sentientia_catalog'), $cards[$forum]['type']);
        $this->assertSame('E-Learning', $cards[$ordinary]['type'], 'an ordinary course keeps its label');
        $this->assertSame('E-Learning', $cards[$unknown]['type'], 'a pseudo-course of an unknown module keeps the old label');
        $this->assertSame('Exam', get_string('coursetype_exam', 'local_sentientia_catalog'));
        $this->assertSame('Forum', get_string('coursetype_forum', 'local_sentientia_catalog'));
    }

    public function test_the_exam_and_forum_labels_have_a_hindi_string(): void {
        $en = [];
        $hi = [];
        $string = [];
        include(__DIR__ . '/../lang/en/local_sentientia_catalog.php');
        $en = $string;
        $string = [];
        include(__DIR__ . '/../lang/hi/local_sentientia_catalog.php');
        $hi = $string;
        foreach (['coursetype_exam', 'coursetype_forum'] as $key) {
            $this->assertArrayHasKey($key, $en);
            $this->assertArrayHasKey($key, $hi);
            $this->assertNotSame($en[$key], $hi[$key], "{$key} is translated, not copied");
        }
    }

    public function test_a_course_with_two_enrolments_of_one_learner_is_in_progress_once(): void {
        $this->resetAfterTest();
        $this->ensure_open_module();
        $learner = $this->getDataGenerator()->create_user();
        $course = $this->make_course('Twin enrolments course', 0, null);
        $other = $this->make_course('Single enrolment course', 0, null);
        $this->getDataGenerator()->enrol_user($learner->id, $course);
        $this->add_bizlms_enrolment($course, (int) $learner->id);
        $this->getDataGenerator()->enrol_user($learner->id, $other);
        \cache::make('local_sentientia_catalog', 'in_progress')->purge();
        $this->setUser($learner);

        $rows = catalog_manager::get_in_progress((int) $learner->id, 6);

        $ids = array_map(fn($c) => (int) $c['id'], $rows);
        $this->assertSame(2, count($ids), 'two courses, each once: the limit is not spent on a duplicate');
        $this->assertSame($ids, array_values(array_unique($ids)));
    }

    public function test_a_suspended_enrolment_or_a_disabled_instance_is_not_in_progress(): void {
        $this->resetAfterTest();
        $this->ensure_open_module();
        $learner = $this->getDataGenerator()->create_user();
        $active = $this->make_course('Active enrolment course', 0, null);
        $suspended = $this->make_course('Suspended enrolment course', 0, null);
        $disabled = $this->make_course('Disabled instance course', 0, null);
        $this->getDataGenerator()->enrol_user($learner->id, $active);
        $this->getDataGenerator()->enrol_user($learner->id, $suspended, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->add_bizlms_enrolment($disabled, (int) $learner->id, 1, 0);
        \cache::make('local_sentientia_catalog', 'in_progress')->purge();
        $this->setUser($learner);

        $ids = array_map(fn($c) => (int) $c['id'], catalog_manager::get_in_progress((int) $learner->id, 10));

        $this->assertSame([$active], $ids);
    }

    public function test_the_popularity_count_counts_a_learner_once_and_only_active_enrolments(): void {
        $this->resetAfterTest();
        $this->ensure_open_module();
        $course = $this->make_course('Popular public course', 0, null);
        $twin = $this->getDataGenerator()->create_user();
        $suspended = $this->getDataGenerator()->create_user();
        $disabledonly = $this->getDataGenerator()->create_user();
        // One learner on two instances, one suspended learner, one learner only on a disabled instance.
        $this->getDataGenerator()->enrol_user($twin->id, $course);
        $this->add_bizlms_enrolment($course, (int) $twin->id);
        $this->getDataGenerator()->enrol_user($suspended->id, $course, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->add_bizlms_enrolment($course, (int) $disabledonly->id, 1, 0);

        $rows = commerce::get_public_catalog('Popular public course', 'popular', 0, 5)['courses'];

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]['enrolled_count'], 'one active learner, however many rows');
    }
}
