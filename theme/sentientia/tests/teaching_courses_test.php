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

use theme_sentientia\local\teaching_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the learner dashboard's "Courses you teach" split (V16, a bug fix with no flag: Nitin, decision D3).
 *
 * Surface walk 2026-11-18: trainers and authors enrolled only as editingteacher saw their own courses as learning
 * ("Not started 0%" cards, a "0 of 1" ring). A course is a taught one when the user holds a course-context role with
 * the editingteacher or teacher archetype there and no student role there. Roles held at system level never count.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \theme_sentientia\local\teaching_courses
 * @group theme_sentientia
 */
final class teaching_courses_test extends \advanced_testcase {

    /**
     * The id of a standard role by shortname.
     *
     * @param string $shortname
     * @return int
     */
    private function roleid(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * Sorted ints, so the assertions do not depend on the order the database returns rows in.
     *
     * @param int[] $ids
     * @return int[]
     */
    private function sorted(array $ids): array {
        $ids = array_map('intval', $ids);
        sort($ids);
        return $ids;
    }

    /**
     * An editingteacher enrolment makes the course a taught one; a student enrolment does not.
     */
    public function test_editingteacher_is_taught_and_student_is_not(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $taught = $gen->create_course();
        $learnt = $gen->create_course();
        $gen->enrol_user($user->id, $taught->id, 'editingteacher');
        $gen->enrol_user($user->id, $learnt->id, 'student');

        $this->assertSame([(int) $taught->id],
            teaching_courses::course_ids((int) $user->id, [(int) $taught->id, (int) $learnt->id]));
        $this->assertSame([], teaching_courses::course_ids((int) $user->id, [(int) $learnt->id]));
    }

    /**
     * A user who teaches and takes the same course is still learning it.
     */
    public function test_teacher_and_student_in_the_same_course_is_not_taught(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($user->id, $course->id, 'student');
        role_assign($this->roleid('editingteacher'), $user->id, \context_course::instance($course->id));

        $this->assertSame([], teaching_courses::course_ids((int) $user->id, [(int) $course->id]));
    }

    /**
     * A teacher role held at system level does not make a course the user takes a taught one.
     */
    public function test_system_teacher_role_with_a_student_enrolment_is_not_taught(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        role_assign($this->roleid('teacher'), $user->id, \context_system::instance());
        role_assign($this->roleid('editingteacher'), $user->id, \context_system::instance());
        $gen->enrol_user($user->id, $course->id, 'student');

        $this->assertSame([], teaching_courses::course_ids((int) $user->id, [(int) $course->id]));
    }

    /**
     * A custom role with the teacher archetype (as the Airpay trainer role) makes the course a taught one.
     */
    public function test_custom_role_with_teacher_archetype_is_taught(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $roleid = $gen->create_role(['shortname' => 'v16trainer', 'archetype' => 'teacher']);
        role_assign($roleid, $user->id, \context_course::instance($course->id));

        $this->assertSame([(int) $course->id], teaching_courses::course_ids((int) $user->id, [(int) $course->id]));
    }

    /**
     * The non-editing teacher role counts as teaching too.
     */
    public function test_non_editing_teacher_is_taught(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($user->id, $course->id, 'teacher');

        $this->assertSame([(int) $course->id], teaching_courses::course_ids((int) $user->id, [(int) $course->id]));
    }

    /**
     * No candidate courses, no query and no result.
     */
    public function test_empty_input_returns_empty(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertSame([], teaching_courses::course_ids((int) $user->id, []));
    }

    /**
     * Only ids from the passed list come back, and only for the user asked about.
     */
    public function test_only_passed_ids_are_returned(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $other = $gen->create_user();
        $a = $gen->create_course();
        $b = $gen->create_course();
        $c = $gen->create_course();
        $gen->enrol_user($user->id, $a->id, 'editingteacher');
        $gen->enrol_user($user->id, $b->id, 'editingteacher');
        $gen->enrol_user($other->id, $c->id, 'editingteacher');

        $this->assertSame([(int) $a->id], teaching_courses::course_ids((int) $user->id, [(int) $a->id]));
        // Another user's taught course is not this user's.
        $this->assertSame([(int) $a->id], teaching_courses::course_ids((int) $user->id, [(int) $a->id, (int) $c->id]));
        // String ids (as array_keys of a course list can be) are accepted and returned as ints.
        $this->assertSame($this->sorted([$a->id, $b->id]),
            $this->sorted(teaching_courses::course_ids((int) $user->id, [(string) $a->id, (string) $b->id])));
        foreach (teaching_courses::course_ids((int) $user->id, [(string) $a->id, (string) $b->id]) as $id) {
            $this->assertIsInt($id);
        }
    }
}
