<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-032 exams code fix 3 (decision exams.forum_pseudocourses = exclude_from_catalog): the catalog lists ordinary
 * courses only.
 *
 * BizLMS stored its online exams and its forums as courses with open_coursetype = 1 and listed only courses with
 * open_coursetype 0 or NULL. A restored database holds those rows (and the exams importer wraps the exam ones), so
 * without the condition each exam and forum would be offered as a course to enrol in. The browse lists the catalog
 * builds (get_courses, get_new, get_categories; get_trending carries the same condition) must leave them out and
 * keep every ordinary course, including those with no open_coursetype at all.
 *
 * @package    local_sentientia_catalog
 * @category   test
 * @covers     \local_sentientia_catalog\catalog_manager
 * @group      local_sentientia_catalog
 */
final class pseudo_course_exclusion_test extends \advanced_testcase {

    // Provisions {user}.open_path and {course}.open_path, and the open_level, open_skill and open_coursetype
    // BizLMS course columns, on the test DB (the same trait the other catalog suites use).
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * A course in its own category.
     *
     * @param string $name
     * @param int|null $type open_coursetype; null leaves it unset (a course BizLMS never typed).
     * @param int $categoryid
     * @return int
     */
    private function make_course(string $name, ?int $type, int $categoryid): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course([
            'fullname' => $name,
            'shortname' => 'pce_' . strtolower(preg_replace('/[^a-z0-9]/i', '', $name)) . '_' . random_int(1000, 9999),
            'category' => $categoryid,
            'visible' => 1,
        ]);
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        if ($type !== null) {
            // An untyped course keeps the column's own default (NULL, or 0 where another suite made it NOT NULL).
            $DB->set_field('course', 'open_coursetype', $type, ['id' => $course->id]);
        }
        return (int) $course->id;
    }

    /**
     * @param array $result get_courses() or a list of formatted courses.
     * @return int[]
     */
    private function ids(array $result): array {
        $courses = $result['courses'] ?? $result;
        return array_map(fn($c) => (int) $c['id'], $courses);
    }

    /**
     * Two categories: A holds an ordinary course, one typed 0, an exam and a forum; B holds an exam and a forum only.
     *
     * @return array<string, int>
     */
    private function seed(): array {
        $gen = $this->getDataGenerator();
        $a = (int) $gen->create_category(['name' => 'Catalog A'])->id;
        $b = (int) $gen->create_category(['name' => 'Catalog B'])->id;
        foreach (['categories', 'new_courses', 'trending'] as $area) {
            \cache::make('local_sentientia_catalog', $area)->purge();
        }
        return [
            'categorya' => $a,
            'categoryb' => $b,
            'untyped' => $this->make_course('Untyped course', null, $a),
            'typed0' => $this->make_course('Typed zero course', 0, $a),
            'exam' => $this->make_course('Online exam course', 1, $a),
            'forum' => $this->make_course('Forum course', 1, $a),
            'examonly' => $this->make_course('Exam in B', 1, $b),
            'forumonly' => $this->make_course('Forum in B', 1, $b),
        ];
    }

    public function test_the_course_list_leaves_out_exam_and_forum_pseudo_courses(): void {
        $this->resetAfterTest();
        $c = $this->seed();
        $this->setAdminUser();

        $result = catalog_manager::get_courses(2, '', [], 'newest', 0, 100);
        $ids = $this->ids($result);
        $this->assertContains($c['untyped'], $ids, 'a course with no open_coursetype is an ordinary course');
        $this->assertContains($c['typed0'], $ids);
        foreach (['exam', 'forum', 'examonly', 'forumonly'] as $name) {
            $this->assertNotContains($c[$name], $ids, $name . ' is a pseudo-course');
        }
        $this->assertSame(count($ids), (int) $result['total'], 'the total counts the same courses the page lists');
    }

    public function test_new_courses_leave_out_pseudo_courses(): void {
        $this->resetAfterTest();
        $c = $this->seed();
        $this->setAdminUser();

        $ids = $this->ids(catalog_manager::get_new(2, 50));
        $this->assertContains($c['untyped'], $ids);
        $this->assertContains($c['typed0'], $ids);
        $this->assertNotContains($c['exam'], $ids);
        $this->assertNotContains($c['forum'], $ids);
    }

    public function test_category_counts_leave_out_pseudo_courses(): void {
        $this->resetAfterTest();
        $c = $this->seed();
        $this->setAdminUser();

        $counts = [];
        foreach (catalog_manager::get_categories() as $row) {
            $counts[(int) $row->id] = (int) $row->course_count;
        }
        $this->assertSame(2, $counts[$c['categorya']] ?? 0, 'the untyped and the typed-zero course, not the exam or the forum');
        $this->assertArrayNotHasKey($c['categoryb'], $counts, 'a category that holds only pseudo-courses is not listed');
    }
}
