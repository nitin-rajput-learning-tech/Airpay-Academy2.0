<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\task;

defined('MOODLE_INTERNAL') || die();

/**
 * The two deadline tasks count the completion clock from the enrolment date (Nitin, 2026-11-16).
 *
 * Before 2026111600 both tasks computed deadline = ue.timestart + {course}.open_coursecompletiondays days and
 * required ue.timestart > 0. On the production import 99.7% of the enrolments in courses with a completion window
 * have timestart 0, so almost nobody was ever reminded or escalated. BizLMS (local_courses course_reminder) counted
 * from ue.timecreated; the course-header countdown (theme_sentientia\local\enrolment_countdown) uses
 * MAX(ue.timecreated).
 *
 * Now: one deadline per (user, course) = MAX(ue.timecreated) over the user's ACTIVE enrolments in the course
 * (ue.status = 0 and e.status = 0) + open_coursecompletiondays days; timecreated 0 means "not set" and is skipped.
 *
 * Only get_candidates() is called here: it selects, it never sends. execute() is never run.
 *
 * Candidate row contract (one \stdClass per (user, course), a list, not keyed): userid, courseid, fullname,
 * days_to_deadline, enroltime, deadline_ts, bucket, and days_remaining (reminder) or days_past (overdue, plus
 * supervisorid, firstname, lastname).
 *
 * Buckets are the plugin defaults (reminder_days_before 7,3,1; overdue_days_after 1,7,14), which are also the
 * tasks' fallbacks; the tests do not set them.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_courses\task\course_reminder
 * @covers     \local_sentientia_courses\task\course_overdue
 * @group      local_sentientia_courses
 */
final class deadline_candidates_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int The fixed "now" every test passes to get_candidates(). */
    private const NOW = 1760000000;

    /** @var int One day in seconds. */
    private const DAY = 86400;

    /** @var int {course}.open_coursecompletiondays of the test courses. */
    private const DAYS = 30;

    /** @var \stdClass A live supervisor for the overdue task. */
    private \stdClass $supervisor;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();          // {user}.open_supervisorid, {course}.open_path.
        self::provision_completion_days();      // {course}.open_coursecompletiondays.
        $this->supervisor = $this->getDataGenerator()->create_user();
    }

    // Fixtures.

    /**
     * The BizLMS column {course}.open_coursecompletiondays, as bizlms_import_test::provision_course_columns() adds
     * it (bizlms_fixture does not). A vanilla PHPUnit database does not have it; on a site with local_sentientia_core
     * the substrate already created it, hence the check. Column DDL is not undone by the PHPUnit reset
     * (reset_database() only drops extra tables), so the column stays until the next init.
     *
     * @return void
     */
    private static function provision_completion_days(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('course');
        if (!isset($DB->get_columns('course')['open_coursecompletiondays'])) {
            $dbman->add_field($table, new \xmldb_field('open_coursecompletiondays', XMLDB_TYPE_INTEGER, '18',
                null, null, null, null));
        }
    }

    /**
     * A course with a completion window.
     *
     * @param int $days
     * @param int $visible
     * @return \stdClass
     */
    private function deadline_course(int $days = self::DAYS, int $visible = 1): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => $visible]);
        $DB->set_field('course', 'open_coursecompletiondays', $days, ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /**
     * A learner with a supervisor (the overdue task needs one).
     *
     * @param bool $withsupervisor
     * @return \stdClass
     */
    private function learner(bool $withsupervisor = true): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_supervisorid', $withsupervisor ? $this->supervisor->id : 0, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * The course's single instance of $method, switched on. The generator's course has a manual instance (on) and a
     * self instance (off by default).
     *
     * @param \stdClass $course
     * @param string $method 'manual' or 'self'
     * @return \stdClass
     */
    private function instance(\stdClass $course, string $method): \stdClass {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => $method]);
        if (!$instance) {
            $id = enrol_get_plugin($method)->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }
        if ((int) $instance->status !== ENROL_INSTANCE_ENABLED) {
            $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $instance->id]);
            $instance->status = ENROL_INSTANCE_ENABLED;
        }
        return $instance;
    }

    /**
     * Enrol through $method with timestart $timestart, then stamp the enrolment row's timecreated (the data
     * generator's enrol_user() only works when the method has exactly one instance and ignores its status).
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param int $timecreated
     * @param string $method
     * @param int $timestart
     * @return int user_enrolments.id
     */
    private function enrol_at(\stdClass $user, \stdClass $course, int $timecreated, string $method = 'manual',
            int $timestart = 0): int {
        global $DB;
        $instance = $this->instance($course, $method);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        enrol_get_plugin($method)->enrol_user($instance, $user->id, $roleid, $timestart, 0);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $user->id],
            MUST_EXIST);
        $DB->set_field('user_enrolments', 'timecreated', $timecreated, ['id' => $ueid]);
        return $ueid;
    }

    /**
     * A course_completions row with timecompleted set (updated if one exists already; the table has a unique
     * (userid, course) index).
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param int $timecompleted
     * @return void
     */
    private function mark_completed(\stdClass $user, \stdClass $course, int $timecompleted): void {
        global $DB;
        $existing = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);
        if ($existing) {
            $DB->set_field('course_completions', 'timecompleted', $timecompleted, ['id' => $existing->id]);
            return;
        }
        $DB->insert_record('course_completions', (object) ['userid' => $user->id, 'course' => $course->id,
            'timeenrolled' => 0, 'timestarted' => 0, 'timecompleted' => $timecompleted, 'reaggregate' => 0]);
    }

    /**
     * The timecreated that puts the deadline at NOW + $offset seconds.
     *
     * @param int $offset
     * @param int $days
     * @return int
     */
    private static function created_for_deadline(int $offset, int $days = self::DAYS): int {
        return self::NOW + $offset - $days * self::DAY;
    }

    /**
     * Reminder candidates keyed "userid:courseid"; fails on a second row for one pair.
     *
     * @param int $now
     * @return \stdClass[]
     */
    private function reminders(int $now = self::NOW): array {
        return $this->keyed((new course_reminder())->get_candidates($now, 5000));
    }

    /**
     * Overdue candidates keyed "userid:courseid"; fails on a second row for one pair.
     *
     * @param int $now
     * @return \stdClass[]
     */
    private function escalations(int $now = self::NOW): array {
        return $this->keyed((new course_overdue())->get_candidates($now, 5000));
    }

    /**
     * @param \stdClass[] $rows
     * @return \stdClass[]
     */
    private function keyed(array $rows): array {
        $this->assertTrue(array_is_list($rows), 'get_candidates() returns a list');
        $out = [];
        foreach ($rows as $row) {
            $key = (int) $row->userid . ':' . (int) $row->courseid;
            $this->assertArrayNotHasKey($key, $out, 'one candidate per (user, course), never one per enrolment row');
            $out[$key] = $row;
        }
        return $out;
    }

    /**
     * @param \stdClass $user
     * @param \stdClass $course
     * @return string
     */
    private static function key(\stdClass $user, \stdClass $course): string {
        return (int) $user->id . ':' . (int) $course->id;
    }

    // Tests.

    public function test_an_enrolment_with_timestart_zero_is_now_a_candidate(): void {
        global $DB;
        $course = $this->deadline_course();

        $user = $this->learner();
        $created = self::created_for_deadline(7 * self::DAY);
        $ueid = $this->enrol_at($user, $course, $created);
        $this->assertSame(0, (int) $DB->get_field('user_enrolments', 'timestart', ['id' => $ueid]));

        $late = $this->learner();
        $latecreated = self::created_for_deadline(-1 * self::DAY);
        $this->enrol_at($late, $course, $latecreated);

        $rows = $this->reminders();
        $this->assertArrayHasKey(self::key($user, $course), $rows);
        $row = $rows[self::key($user, $course)];
        $this->assertSame($created, (int) $row->enroltime);
        $this->assertSame($created + self::DAYS * self::DAY, (int) $row->deadline_ts);
        $this->assertSame(7, (int) $row->bucket);
        $this->assertSame(7, (int) $row->days_remaining);

        // The overdue task too: timestart 0, deadline one day past.
        $esc = $this->escalations();
        $this->assertArrayHasKey(self::key($late, $course), $esc);
        $this->assertSame($latecreated + self::DAYS * self::DAY, (int) $esc[self::key($late, $course)]->deadline_ts);
        $this->assertSame(1, (int) $esc[self::key($late, $course)]->days_past);
    }

    public function test_the_deadline_counts_from_timecreated_not_timestart(): void {
        $course = $this->deadline_course();

        // timestart says the deadline passed one day ago (the old rule would escalate it); timecreated puts it 3 days
        // ahead. timecreated wins: a 3-day reminder, no escalation.
        $user = $this->learner();
        $created = self::created_for_deadline(3 * self::DAY);
        $this->enrol_at($user, $course, $created, 'manual', self::created_for_deadline(-1 * self::DAY));

        // timecreated 0 is "not set": skipped by both tasks, even though timestart alone would put the deadline in
        // the 7-day reminder bucket (or one day past, for the overdue task).
        $unset = $this->learner();
        $this->enrol_at($unset, $course, 0, 'manual', self::created_for_deadline(7 * self::DAY));
        $unsetlate = $this->learner();
        $this->enrol_at($unsetlate, $course, 0, 'manual', self::created_for_deadline(-1 * self::DAY));

        $rows = $this->reminders();
        $this->assertArrayHasKey(self::key($user, $course), $rows);
        $this->assertSame($created + self::DAYS * self::DAY, (int) $rows[self::key($user, $course)]->deadline_ts);
        $this->assertSame(3, (int) $rows[self::key($user, $course)]->bucket);
        $this->assertArrayNotHasKey(self::key($unset, $course), $rows);

        $esc = $this->escalations();
        $this->assertArrayNotHasKey(self::key($unset, $course), $esc);
        $this->assertArrayNotHasKey(self::key($unsetlate, $course), $esc);
        $this->assertArrayNotHasKey(self::key($user, $course), $esc, 'its old timestart deadline is no longer used');
    }

    public function test_two_enrolments_give_one_candidate_from_the_later_one(): void {
        global $DB;
        $course = $this->deadline_course();

        // Reminder: manual puts the deadline 1 day ahead, self (enrolled later) 7 days ahead.
        $user = $this->learner();
        $this->enrol_at($user, $course, self::created_for_deadline(1 * self::DAY), 'manual');
        $later = self::created_for_deadline(7 * self::DAY);
        $this->enrol_at($user, $course, $later, 'self');
        $this->assertSame(2, $DB->count_records_sql('SELECT COUNT(1) FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = :c AND ue.userid = :u',
            ['c' => $course->id, 'u' => $user->id]));
        $rows = $this->reminders();
        $this->assertSame($later, (int) $rows[self::key($user, $course)]->enroltime);
        $this->assertSame($later + self::DAYS * self::DAY, (int) $rows[self::key($user, $course)]->deadline_ts);
        $this->assertSame(7, (int) $rows[self::key($user, $course)]->bucket);

        // Overdue: manual 14 days past, self (later) 1 day past -> one escalation, 1 day past.
        $other = $this->deadline_course();
        $late = $this->learner();
        $this->enrol_at($late, $other, self::created_for_deadline(-14 * self::DAY), 'manual');
        $this->enrol_at($late, $other, self::created_for_deadline(-1 * self::DAY), 'self');
        $esc = $this->escalations();
        $this->assertSame(1, (int) $esc[self::key($late, $other)]->days_past);
        $this->assertSame(1, (int) $esc[self::key($late, $other)]->bucket);

        // MAX is taken over ACTIVE enrolments only: suspend the later one and the earlier one decides.
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED,
            ['enrolid' => $this->instance($course, 'self')->id, 'userid' => $user->id]);
        $this->assertSame(1, (int) $this->reminders()[self::key($user, $course)]->bucket);
    }

    public function test_suspended_disabled_completed_deleted_and_hidden_are_excluded(): void {
        global $DB;
        foreach (['reminder' => 7 * self::DAY, 'overdue' => -1 * self::DAY] as $task => $offset) {
            $course = $this->deadline_course();
            $created = self::created_for_deadline($offset);

            $control = $this->learner();
            $this->enrol_at($control, $course, $created);

            $suspended = $this->learner();
            $ueid = $this->enrol_at($suspended, $course, $created);
            $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['id' => $ueid]);

            $completed = $this->learner();
            $this->enrol_at($completed, $course, $created);
            $this->mark_completed($completed, $course, self::NOW - 60);

            // set_field, not delete_user(): delete_user() unenrols, and the test would pass for the wrong reason.
            $deleted = $this->learner();
            $this->enrol_at($deleted, $course, $created);
            $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

            $suspendeduser = $this->learner();
            $this->enrol_at($suspendeduser, $course, $created);
            $DB->set_field('user', 'suspended', 1, ['id' => $suspendeduser->id]);

            // No supervisor: still reminded (the learner is the recipient), never escalated (nobody to tell).
            $nosupervisor = $this->learner(false);
            $this->enrol_at($nosupervisor, $course, $created);

            // A disabled enrolment method: its own course, so the control's instance stays on.
            $disabledcourse = $this->deadline_course();
            $viadisabled = $this->learner();
            $this->enrol_at($viadisabled, $disabledcourse, $created);
            $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED,
                ['id' => $this->instance($disabledcourse, 'manual')->id]);

            // A hidden course.
            $hiddencourse = $this->deadline_course(self::DAYS, 0);
            $inhidden = $this->learner();
            $this->enrol_at($inhidden, $hiddencourse, $created);

            // A course without a completion window (0 days).
            $nowindow = $this->deadline_course(0);
            $innowindow = $this->learner();
            $this->enrol_at($innowindow, $nowindow, $created);

            $rows = $task === 'reminder' ? $this->reminders() : $this->escalations();
            $this->assertArrayHasKey(self::key($control, $course), $rows, "$task: the control is a candidate");
            $this->assertArrayNotHasKey(self::key($suspended, $course), $rows, "$task: suspended enrolment");
            $this->assertArrayNotHasKey(self::key($completed, $course), $rows, "$task: completed course");
            $this->assertArrayNotHasKey(self::key($deleted, $course), $rows, "$task: deleted user");
            $this->assertArrayNotHasKey(self::key($suspendeduser, $course), $rows, "$task: suspended user");
            $this->assertArrayNotHasKey(self::key($viadisabled, $disabledcourse), $rows, "$task: disabled method");
            $this->assertArrayNotHasKey(self::key($inhidden, $hiddencourse), $rows, "$task: hidden course");
            $this->assertArrayNotHasKey(self::key($innowindow, $nowindow), $rows, "$task: no completion window");
            if ($task === 'reminder') {
                $this->assertArrayHasKey(self::key($nosupervisor, $course), $rows, 'reminder: no supervisor needed');
            } else {
                $this->assertArrayNotHasKey(self::key($nosupervisor, $course), $rows, 'overdue: no supervisor');
            }
        }
    }

    public function test_reminder_buckets_at_the_boundaries(): void {
        // Default buckets 7,3,1: the smallest bucket >= ceil(days remaining).
        $course = $this->deadline_course();
        $cases = [
            // Seconds until the deadline => expected bucket (null = not a candidate).
            8 * self::DAY + 1 => null,  // outside the SQL window (widest + 1 days).
            7 * self::DAY + 1 => null,  // 8 days remaining: inside the SQL window, above the widest bucket.
            7 * self::DAY     => 7,
            3 * self::DAY + 1 => 7,     // 4 days remaining.
            3 * self::DAY     => 3,
            1 * self::DAY + 1 => 3,     // 2 days remaining.
            1 * self::DAY     => 1,
            1                 => 1,
            0                 => null,  // the deadline is now: not a reminder.
            -1                => null,  // past: the overdue task's business.
        ];
        $users = [];
        foreach ($cases as $offset => $expected) {
            $users[$offset] = $this->learner();
            $this->enrol_at($users[$offset], $course, self::created_for_deadline($offset));
        }
        $rows = $this->reminders();
        foreach ($cases as $offset => $expected) {
            $key = self::key($users[$offset], $course);
            if ($expected === null) {
                $this->assertArrayNotHasKey($key, $rows, "deadline in {$offset}s");
            } else {
                $this->assertArrayHasKey($key, $rows, "deadline in {$offset}s");
                $this->assertSame($expected, (int) $rows[$key]->bucket, "deadline in {$offset}s");
                $this->assertSame(self::NOW + $offset, (int) $rows[$key]->deadline_ts);
                $this->assertSame((int) ceil($offset / self::DAY), (int) $rows[$key]->days_remaining);
            }
        }
        $this->assertCount(6, $rows);
    }

    public function test_overdue_windows_at_the_boundaries(): void {
        // Default buckets 1,7,14: the smallest bucket >= ceil(days past). That is the CURRENT mapping, kept as is:
        // it sends the '7 days' escalation on day 2 and the '14 days' one on day 8 (defect D1, reported for a
        // product decision, not changed by the 2026-11-16 enrolment-date change).
        $course = $this->deadline_course();
        $cases = [
            // Seconds since the deadline => expected bucket (null = not a candidate).
            -1                 => null,  // the deadline is still ahead.
            0                  => null,  // the deadline is now.
            1                  => 1,
            1 * self::DAY      => 1,
            1 * self::DAY + 1  => 7,     // 2 days past (D1).
            7 * self::DAY      => 7,
            7 * self::DAY + 1  => 14,    // 8 days past (D1).
            14 * self::DAY     => 14,
            14 * self::DAY + 1 => null,  // 15 days past: past the widest bucket, stop.
            15 * self::DAY + 1 => null,  // outside the SQL window (widest + 1 days).
        ];
        $users = [];
        foreach ($cases as $past => $expected) {
            $users[$past] = $this->learner();
            $this->enrol_at($users[$past], $course, self::created_for_deadline(-$past));
        }
        $rows = $this->escalations();
        foreach ($cases as $past => $expected) {
            $key = self::key($users[$past], $course);
            if ($expected === null) {
                $this->assertArrayNotHasKey($key, $rows, "{$past}s past the deadline");
            } else {
                $this->assertArrayHasKey($key, $rows, "{$past}s past the deadline");
                $this->assertSame($expected, (int) $rows[$key]->bucket, "{$past}s past the deadline");
                $this->assertSame(self::NOW - $past, (int) $rows[$key]->deadline_ts);
                $this->assertSame((int) ceil($past / self::DAY), (int) $rows[$key]->days_past);
                $this->assertSame((int) $this->supervisor->id, (int) $rows[$key]->supervisorid);
            }
        }
        $this->assertCount(6, $rows);
    }

    public function test_max_limits_the_list_oldest_enrolment_first(): void {
        $course = $this->deadline_course();
        $oldest = $this->learner();
        $this->enrol_at($oldest, $course, self::created_for_deadline(1 * self::DAY));
        $middle = $this->learner();
        $this->enrol_at($middle, $course, self::created_for_deadline(3 * self::DAY));
        $newest = $this->learner();
        $this->enrol_at($newest, $course, self::created_for_deadline(7 * self::DAY));

        $rows = (new course_reminder())->get_candidates(self::NOW, 2);
        $this->assertSame([(int) $oldest->id, (int) $middle->id],
            array_map(static fn($r) => (int) $r->userid, $rows));
        $this->assertCount(3, (new course_reminder())->get_candidates(self::NOW, 5000));
    }

    public function test_get_candidates_sends_nothing(): void {
        global $DB;
        $course = $this->deadline_course();
        $this->enrol_at($this->learner(), $course, self::created_for_deadline(7 * self::DAY));
        $this->enrol_at($this->learner(), $course, self::created_for_deadline(-1 * self::DAY));

        $config = [];
        foreach (['reminder_last_run', 'reminder_last_sent', 'overdue_last_run', 'overdue_last_sent'] as $name) {
            $config[$name] = get_config('local_sentientia_courses', $name);
        }
        $notifications = $DB->count_records('notifications');

        // Sinks opened after the enrolments, so only what get_candidates() might send is counted.
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();

        $this->assertCount(1, (new course_reminder())->get_candidates(self::NOW, 5000));
        $this->assertCount(1, (new course_overdue())->get_candidates(self::NOW, 5000));

        $this->assertSame(0, $messages->count(), 'no message or notification');
        $this->assertSame(0, $emails->count(), 'no email');
        $this->assertSame($notifications, $DB->count_records('notifications'), 'no notification row');
        $this->assertSame(0, $DB->count_records('local_sentientia_courses_remind_sent'), 'no dedupe row');
        foreach ($config as $name => $value) {
            $this->assertSame($value, get_config('local_sentientia_courses', $name), "$name unchanged: no run recorded");
        }
        $messages->close();
        $emails->close();
    }
}
