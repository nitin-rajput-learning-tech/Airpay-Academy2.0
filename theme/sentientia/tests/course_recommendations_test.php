<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_sentientia;

use theme_sentientia\local\course_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the learner dashboard's fallback "Recommended for You" rail.
 *
 * Surface walk 2026-11-18: the rail's same-category query had no tenant filter, and categories are shared between
 * tenants, so an Airpay (/1) learner was recommended a ZEEA (/177) course it could not open. These tests pin that the
 * rail offers only what the catalogue lets the learner open: the learner's own tenant tree ('/1', '/1/...', never
 * '/177'), courses shared to that tenant, visible ordinary courses, never an enrolled course, nothing for a learner
 * whose tenant cannot be resolved, and everything for a cross-tenant viewer.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \theme_sentientia\local\course_recommendations
 * @group theme_sentientia
 * @group tenant_isolation
 */
final class course_recommendations_test extends \advanced_testcase {

    // Provisions {user}.open_path, {course}.open_path and the BizLMS course columns on the test DB, as the catalogue's
    // suites do. Its setUp() also calls resetAfterTest().
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /** @var int Next timecreated, so "newest first" is deterministic. */
    private int $clock = 1700000000;

    /**
     * A course in $category owned by $path.
     *
     * @param int $category
     * @param string|null $path course.open_path
     * @param array $extra extra course fields (visible, open_coursetype)
     * @return \stdClass
     */
    private function course(int $category, ?string $path, array $extra = []): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['category' => $category]);
        $fields = ['open_path' => $path, 'timecreated' => $this->clock++] + $extra;
        foreach ($fields as $field => $value) {
            $DB->set_field('course', $field, $value, ['id' => $course->id]);
        }
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /**
     * A user whose open_path is $path.
     *
     * @param string $path
     * @return \stdClass
     */
    private function user(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * Ids of the recommended courses, in the order returned.
     *
     * @param \stdClass[] $rows
     * @return int[]
     */
    private function ids(array $rows): array {
        return array_map(static fn(\stdClass $row): int => (int) $row->id, $rows);
    }

    public function test_only_courses_the_learner_can_open_are_recommended(): void {
        if (!class_exists('\\local_sentientia_courses\\sharing_manager')
                || !class_exists('\\local_sentientia_catalog\\catalog_manager')) {
            $this->markTestSkipped('local_sentientia_courses and local_sentientia_catalog are needed');
        }
        $shared = $this->getDataGenerator()->create_category()->id;
        $other = $this->getDataGenerator()->create_category()->id;

        $enrolled = $this->course($shared, '/1');
        $owndept = $this->course($shared, '/1/183');
        $zeea = $this->course($shared, '/177');            // '/1' must not match '/177'.
        $public = $this->course($shared, '/77');
        $publicshared = $this->course($shared, '/77');
        $hidden = $this->course($shared, '/1', ['visible' => 0]);
        $exam = $this->course($shared, '/1', ['open_coursetype' => 1]);
        $legacy = $this->course($shared, null);            // The catalogue refuses a course with no tenant.
        $othercategory = $this->course($other, '/1');
        $ownroot = $this->course($shared, '/1');

        \local_sentientia_courses\sharing_manager::share_course((int) $publicshared->id, [1]);

        $learner = $this->user('/1/183/45');
        $ids = $this->ids(course_recommendations::same_category_courses($learner, [(int) $enrolled->id], 10));

        // Newest first: own-tenant root, the course shared to /1, own-tenant department.
        $this->assertSame([(int) $ownroot->id, (int) $publicshared->id, (int) $owndept->id], $ids);
        foreach ([$enrolled, $zeea, $public, $hidden, $exam, $legacy, $othercategory] as $excluded) {
            $this->assertNotContains((int) $excluded->id, $ids);
        }
    }

    public function test_the_limit_keeps_the_newest(): void {
        $category = $this->getDataGenerator()->create_category()->id;
        $enrolled = $this->course($category, '/1');
        $older = $this->course($category, '/1');
        $newer = $this->course($category, '/1');

        $learner = $this->user('/1');
        $ids = $this->ids(course_recommendations::same_category_courses($learner, [(int) $enrolled->id], 1));

        $this->assertSame([(int) $newer->id], $ids);
        $this->assertNotContains((int) $older->id, $ids);
    }

    public function test_a_learner_without_a_tenant_gets_nothing(): void {
        $category = $this->getDataGenerator()->create_category()->id;
        $enrolled = $this->course($category, '/1');
        $this->course($category, '/1');

        foreach (['', '/', 'abc'] as $path) {
            $learner = $this->user($path);
            $this->assertSame([], course_recommendations::same_category_courses($learner, [(int) $enrolled->id]),
                "open_path '{$path}' must fail closed");
            $this->assertNull(course_recommendations::course_visibility_sql($learner));
        }
    }

    public function test_a_cross_tenant_viewer_is_not_restricted(): void {
        global $DB;
        $category = $this->getDataGenerator()->create_category()->id;
        $enrolled = $this->course($category, '/1');
        $zeea = $this->course($category, '/177');

        $admin = get_admin();
        $DB->set_field('user', 'open_path', '/1', ['id' => $admin->id]);
        $admin = $DB->get_record('user', ['id' => $admin->id], '*', MUST_EXIST);

        $ids = $this->ids(course_recommendations::same_category_courses($admin, [(int) $enrolled->id]));
        $this->assertContains((int) $zeea->id, $ids);
        $this->assertSame(['1 = 1', []], course_recommendations::course_visibility_sql($admin));
    }

    public function test_no_enrolled_courses_means_no_recommendations(): void {
        $this->assertSame([], course_recommendations::same_category_courses($this->user('/1'), []));
    }

    public function test_cards_link_to_the_catalogue_course_page(): void {
        $url = course_recommendations::course_url(42);
        if (class_exists('\\local_sentientia_catalog\\catalog_manager')) {
            $this->assertStringEndsWith('/local/sentientia_catalog/course.php', $url->get_path());
        } else {
            $this->assertStringEndsWith('/course/view.php', $url->get_path());
        }
        $this->assertSame('42', (string) $url->get_param('id'));
    }
}
