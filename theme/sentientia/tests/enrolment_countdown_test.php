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

use theme_sentientia\local\enrolment_countdown;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the course banner's enrolment-date countdown (core_renderer::full_header()).
 *
 * Until 2026-11-16 full_header() read the enrolment date with a query that interpolated $USER->id and $COURSE->id and
 * never tied ue.userid to the viewer, so every viewer got the latest enrolment of ANY user in the course, enrolled or
 * not. The lookup is now enrolment_countdown::latest_enrolment_time(); these tests pin that each user gets their own
 * date, that a user who is not enrolled gets none, and that several enrolment methods give the latest.
 *
 * due_text() is the old arithmetic moved unchanged; the table pins its strings and its boundaries (nothing on the
 * enrolment day or on the due day) in the timezone the site runs in. The expected strings are what the pre-fix code
 * printed for the same dates.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \theme_sentientia\local\enrolment_countdown
 * @group theme_sentientia
 */
final class enrolment_countdown_test extends \advanced_testcase {

    /**
     * The enrolment instance of a method on the course, added when the course has none.
     *
     * @param \stdClass $course
     * @param string $method enrolment plugin name
     * @return \stdClass the {enrol} record
     */
    private function instance(\stdClass $course, string $method): \stdClass {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => $method]);
        if (!$instance) {
            $id = enrol_get_plugin($method)->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }
        return $instance;
    }

    /**
     * Enrol a user with a method and set the enrolment's timecreated.
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param string $method enrolment plugin name
     * @param int $timecreated the user_enrolments.timecreated to store
     */
    private function enrol_at(\stdClass $user, \stdClass $course, string $method, int $timecreated): void {
        global $DB;
        $instance = $this->instance($course, $method);
        enrol_get_plugin($method)->enrol_user($instance, $user->id, $instance->roleid ?: null);
        $DB->set_field('user_enrolments', 'timecreated', $timecreated,
            ['enrolid' => $instance->id, 'userid' => $user->id]);
    }

    /**
     * Two learners enrolled at different times each get their own date, not the course's latest.
     */
    public function test_each_user_gets_their_own_enrolment_time(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $early = $generator->create_user();
        $late = $generator->create_user();

        $this->enrol_at($early, $course, 'manual', 1760000000);
        $this->enrol_at($late, $course, 'manual', 1770000000);
        // A later enrolment of the same user in another course must not leak into this course.
        $this->enrol_at($early, $other, 'manual', 1780000000);

        $this->assertSame(1760000000, enrolment_countdown::latest_enrolment_time((int) $course->id, (int) $early->id),
            'the earlier learner must get their own date, not the latest enrolment in the course');
        $this->assertSame(1770000000, enrolment_countdown::latest_enrolment_time((int) $course->id, (int) $late->id));
        $this->assertSame(1780000000, enrolment_countdown::latest_enrolment_time((int) $other->id, (int) $early->id));
    }

    /**
     * A viewer who is not enrolled in the course (a manager, a tenant admin, the guest user) gets no date.
     */
    public function test_a_user_who_is_not_enrolled_gets_null(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $learner = $generator->create_user();
        $viewer = $generator->create_user();
        $elsewhere = $generator->create_user();

        $this->enrol_at($learner, $course, 'manual', 1770000000);
        $this->enrol_at($elsewhere, $other, 'manual', 1775000000);

        $this->assertNull(enrolment_countdown::latest_enrolment_time((int) $course->id, (int) $viewer->id));
        $this->assertNull(enrolment_countdown::latest_enrolment_time((int) $course->id, (int) $elsewhere->id));
        $this->assertNull(enrolment_countdown::latest_enrolment_time((int) $course->id, (int) guest_user()->id));
    }

    /**
     * A user enrolled by several methods gets the latest of their enrolments, whichever method it was.
     */
    public function test_several_enrolment_methods_give_the_latest(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $manualfirst = $generator->create_user();
        $selffirst = $generator->create_user();

        $this->enrol_at($manualfirst, $course, 'manual', 1760000000);
        $this->enrol_at($manualfirst, $course, 'self', 1765000000);
        $this->enrol_at($selffirst, $course, 'self', 1761000000);
        $this->enrol_at($selffirst, $course, 'manual', 1766000000);

        $this->assertSame(1765000000,
            enrolment_countdown::latest_enrolment_time((int) $course->id, (int) $manualfirst->id));
        $this->assertSame(1766000000,
            enrolment_countdown::latest_enrolment_time((int) $course->id, (int) $selffirst->id));
    }

    /**
     * A timecreated of 0 ("not set") gives null, as the old !empty() check in full_header() treated it.
     */
    public function test_an_unset_enrolment_time_gives_null(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();

        $this->enrol_at($user, $course, 'manual', 0);

        $this->assertNull(enrolment_countdown::latest_enrolment_time((int) $course->id, (int) $user->id));
    }

    /**
     * Rows for test_due_text: enrolment time and completion days against now = 2026-11-16 10:00:00 Asia/Kolkata.
     *
     * @return array [enrolment time 'Y-m-d H:i:s' in Asia/Kolkata, completion days, expected text or null]
     */
    public static function due_text_provider(): array {
        return [
            'enrolled this moment: 0 days, nothing' => ['2026-11-16 10:00:00', 30, null],
            'enrolled at midnight today: 0 days, nothing' => ['2026-11-16 00:00:00', 30, null],
            'enrolled later today (1 s before midnight): 0 days, nothing' => ['2026-11-16 23:59:59', 30, null],
            'yesterday 1 s before midnight (less than 24 h): 1 day' =>
                ['2026-11-15 23:59:59', 30, 'Due In : <strong>29 days </strong>'],
            'yesterday: 1 day' => ['2026-11-15 10:00:00', 30, 'Due In : <strong>29 days </strong>'],
            '29 of 30 days: due in 1' => ['2026-10-18 10:00:00', 30, 'Due In : <strong>1 days </strong>'],
            'the due day (30 of 30): nothing' => ['2026-10-17 10:00:00', 30, null],
            'the due day at midnight: nothing' => ['2026-10-17 00:00:00', 30, null],
            '31 days, 1 s before midnight: overdue by 1' => ['2026-10-16 23:59:59', 30, 'Overdue by : 1 days'],
            '31 of 30 days: overdue by 1' => ['2026-10-16 10:00:00', 30, 'Overdue by : 1 days'],
            '45 of 30 days: overdue by 15' => ['2026-10-02 10:00:00', 30, 'Overdue by : 15 days'],
            'enrolment 2 days in the future counts as 2 days (abs)' =>
                ['2026-11-18 10:00:00', 30, 'Due In : <strong>28 days </strong>'],
            '1-day course, same day: nothing' => ['2026-11-16 09:00:00', 1, null],
            '1-day course, the due day: nothing' => ['2026-11-15 10:00:00', 1, null],
            '1-day course, 2 days: overdue by 1' => ['2026-11-14 10:00:00', 1, 'Overdue by : 1 days'],
            '1-day course, 229 days: overdue by 228' => ['2026-04-01 10:00:00', 1, 'Overdue by : 228 days'],
            '15-day course, 14 days: due in 1' => ['2026-11-02 10:00:00', 15, 'Due In : <strong>1 days </strong>'],
            '15-day course, the due day: nothing' => ['2026-11-01 10:00:00', 15, null],
            '15-day course, 16 days: overdue by 1' => ['2026-10-31 10:00:00', 15, 'Overdue by : 1 days'],
        ];
    }

    /**
     * The countdown text, its numbers and its boundaries are those full_header() has always printed.
     *
     * @dataProvider due_text_provider
     * @param string $enrolled enrolment time, 'Y-m-d H:i:s' in Asia/Kolkata
     * @param int $completiondays {course}.open_coursecompletiondays
     * @param string|null $expected
     */
    public function test_due_text(string $enrolled, int $completiondays, ?string $expected): void {
        $this->resetAfterTest();
        // The site's timezone; due_text() works in the PHP default timezone, as full_header() always has.
        $this->setTimezone('Asia/Kolkata', 'Asia/Kolkata');
        $tz = new \DateTimeZone('Asia/Kolkata');
        $now = (new \DateTimeImmutable('2026-11-16 10:00:00', $tz))->getTimestamp();
        $enroltime = (new \DateTimeImmutable($enrolled, $tz))->getTimestamp();

        $this->assertSame($expected, enrolment_countdown::due_text($enroltime, $now, $completiondays));
    }
}
