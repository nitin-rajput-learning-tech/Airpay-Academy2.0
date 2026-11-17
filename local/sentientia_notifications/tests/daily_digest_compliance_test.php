<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_notifications;

use local_sentientia_notifications\task\daily_digest;
use local_sentientia_platform\feature_flags;

defined('MOODLE_INTERNAL') || die();

/**
 * The daily digest's "compliance deadlines approaching" section.
 *
 * Until 2026-11-16 the section read local_sentientia_compliance_snapshot
 * (column deadline), a table no plugin creates, so it never ran. It now reads
 * local_sentientia_compliance_report's local_compliance_snapshot (one row per
 * user and course, deadline_date) behind daily_digest::DEADLINES_FLAG, default
 * OFF, resolved per learner tenant.
 *
 * Locks in: a not-completed course due within 7 days is listed (at most two,
 * soonest first); one due later, one completed, and another learner's rows are
 * not; rows the hourly rebuild leaves behind (course taken off the compliance
 * list, configured for another tenant, completed or unenrolled since, hidden,
 * deadline passed) are not; a learner with no tenant never gets the section;
 * an exempted learner gets no line (all-courses exemption, courseid 0, or one
 * for that course) unless the exemption has expired or was lifted; only an
 * active enrolment counts (not suspended, method enabled, started, not ended);
 * a course on the compliance list twice, or enrolled by two methods, gives one
 * line. The streak line is read only where its table exists.
 *
 * Line assertions compare the exact ordered list of lines. build_digest()
 * never passes a repeated line on, so a duplicate would show up indirectly:
 * with the limit at 2, the next course would drop out of the list.
 *
 * build_digest() is private and is called through reflection: execute() would
 * also send the message, and its learner query needs BizLMS columns.
 *
 * Written 2026-11-16 while another PHPUnit run held the local database:
 * linted, not yet run.
 *
 * @package    local_sentientia_notifications
 * @category   test
 * @covers     \local_sentientia_notifications\task\daily_digest
 * @group      tenant_isolation
 */
final class daily_digest_compliance_test extends \advanced_testcase {

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_compliance_snapshot')
                || !$dbman->table_exists('local_compliance_courses')
                || !$dbman->table_exists('local_compliance_exemptions')) {
            $this->markTestSkipped('local_sentientia_compliance_report is not installed.');
        }
        // The resolver's statics survive resetAfterTest's rollback.
        feature_flags::invalidate_caches();
    }

    protected function tearDown(): void {
        feature_flags::invalidate_caches();
        parent::tearDown();
    }

    /** Override the flag for one tenant root (0 = global), as the Switchboard would. */
    private function set_flag(int $tenant, ?bool $value): void {
        feature_flags::set(daily_digest::DEADLINES_FLAG, $tenant, $value,
            (int) get_admin()->id, 'phpunit', 0);
        feature_flags::invalidate_caches();
    }

    /**
     * A learner at $path. open_path is set on the record only: build_digest()
     * reads it from the record execute() hands it, never from the database.
     */
    private function learner(string $path): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $user->open_path = $path;
        return $user;
    }

    /** A course on the compliance list for tenant $costcenterid (0 = every tenant). */
    private function compliance_course(int $costcenterid = 0, int $active = 1, int $visible = 1): \stdClass {
        $course = $this->getDataGenerator()->create_course(['visible' => $visible]);
        $this->list_course($course, $costcenterid, $active);
        return $course;
    }

    /** Put $course on the compliance list for $costcenterid (unique per course and costcenterid). */
    private function list_course(\stdClass $course, int $costcenterid, int $active = 1): void {
        global $DB;
        $DB->insert_record('local_compliance_courses', (object) [
            'courseid'      => $course->id,
            'coursename'    => $course->fullname,
            'deadline_days' => 30,
            'costcenterid'  => $costcenterid,
            'is_active'     => $active,
            'sort_order'    => 0,
            'createdby'     => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);
    }

    /**
     * The snapshot row rebuild_snapshot() writes for $user and $course, due
     * $duein seconds from now; enrols the learner first (active, manual)
     * unless $enrol is false.
     */
    private function snapshot(\stdClass $user, \stdClass $course, string $status, int $duein,
            bool $enrol = true): void {
        global $DB;
        if ($enrol) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }
        $now = time();
        $done = $status === 'completed';
        $DB->insert_record('local_compliance_snapshot', (object) [
            'userid'           => $user->id,
            'courseid'         => $course->id,
            'costcenterid'     => \local_sentientia_platform\tenant::root_for_user($user),
            'department_path'  => $user->open_path,
            'status'           => $status,
            'completion_date'  => $done ? $now : null,
            'progress_percent' => $done ? 100 : 0,
            'enrol_date'       => $now - 20 * DAYSECS,
            'deadline_date'    => $now + $duein,
            'days_overdue'     => 0,
            'matched_by'       => 'userid',
            'snapshot_date'    => $now,
        ]);
    }

    /**
     * An exemption row as the report writes it: courseid 0 is exclude_user()'s
     * "excluded from all compliance tracking"; $expiry null = permanent.
     */
    private function exempt(\stdClass $user, int $courseid, ?int $expiry = null, int $active = 1): void {
        global $DB;
        $DB->insert_record('local_compliance_exemptions', (object) [
            'userid'      => $user->id,
            'courseid'    => $courseid,
            'reason'      => 'phpunit',
            'approved_by' => (int) get_admin()->id,
            'expiry_date' => $expiry,
            'is_active'   => $active,
            'timecreated' => time(),
        ]);
    }

    /** A second active enrolment of $user in $course, through its own enabled instance. */
    private function second_enrolment(\stdClass $user, \stdClass $course): void {
        global $DB;
        $now = time();
        $enrolid = $DB->insert_record('enrol', (object) [
            'enrol'        => 'self',
            'status'       => ENROL_INSTANCE_ENABLED,
            'courseid'     => $course->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('user_enrolments', (object) [
            'enrolid'      => $enrolid,
            'userid'       => $user->id,
            'status'       => ENROL_USER_ACTIVE,
            'timestart'    => 0,
            'timeend'      => 0,
            'modifierid'   => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /** build_digest()'s items for $user, from $task (a fresh task unless given). */
    private function digest_items(\stdClass $user, ?daily_digest $task = null): array {
        $build = new \ReflectionMethod(daily_digest::class, 'build_digest');
        return $build->invoke($task ?? new daily_digest(), $user)['items'];
    }

    /** The digest's compliance items for $user, in digest order, repeats kept: [['courseid', 'text']]. */
    private function due_items(\stdClass $user): array {
        $due = [];
        foreach ($this->digest_items($user) as $item) {
            if (!str_starts_with($item['text'], 'Due in ')) {
                continue;
            }
            parse_str((string) parse_url($item['url'], PHP_URL_QUERY), $query);
            $due[] = ['courseid' => (int) $query['id'], 'text' => $item['text']];
        }
        return $due;
    }

    /** Texts of the digest's compliance lines for $user, in digest order, repeats kept. */
    private function due_lines(\stdClass $user): array {
        return array_column($this->due_items($user), 'text');
    }

    /** Course ids of the digest's compliance lines for $user, in digest order, repeats kept. */
    private function due_courses(\stdClass $user): array {
        return array_column($this->due_items($user), 'courseid');
    }

    /** The line build_digest() writes for $course due in $days days. */
    private function line(int $days, \stdClass $course): string {
        return 'Due in ' . $days . 'd: ' . format_string($course->fullname);
    }

    public function test_flag_is_registered_and_off_by_default(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(daily_digest::DEADLINES_FLAG, $registry);
        $this->assertFalse($registry[daily_digest::DEADLINES_FLAG]['default']);

        $learner = $this->learner('/1/5');
        $this->snapshot($learner, $this->compliance_course(), 'not_started', 3 * DAYSECS);

        $this->assertSame([], $this->due_courses($learner), 'flag OFF: no compliance lines');
    }

    public function test_due_within_seven_days_is_listed_due_later_or_completed_is_not(): void {
        $this->set_flag(1, true);
        $learner = $this->learner('/1/5');

        $soon = $this->compliance_course(1);
        $this->snapshot($learner, $soon, 'not_started', 3 * DAYSECS);
        $mid = $this->compliance_course();
        $this->snapshot($learner, $mid, 'in_progress', 5 * DAYSECS);
        $third = $this->compliance_course();
        $this->snapshot($learner, $third, 'in_progress', 6 * DAYSECS);
        $later = $this->compliance_course();
        $this->snapshot($learner, $later, 'not_started', 10 * DAYSECS);
        $done = $this->compliance_course();
        $this->snapshot($learner, $done, 'completed', 3 * DAYSECS);

        // Soonest first, at most two: the 6-day course is cut by the limit.
        $this->assertSame([$this->line(3, $soon), $this->line(5, $mid)], $this->due_lines($learner));
        $this->assertSame([(int) $soon->id, (int) $mid->id], $this->due_courses($learner));
    }

    public function test_another_learners_rows_never_appear(): void {
        $this->set_flag(1, true);
        $this->set_flag(77, true);
        $alice = $this->learner('/1/5');
        $bob = $this->learner('/1/6');
        $carol = $this->learner('/77');

        // Same compliance course for Alice and Bob; one more for Bob only.
        $shared = $this->compliance_course(1);
        $this->snapshot($alice, $shared, 'not_started', 3 * DAYSECS);
        $this->snapshot($bob, $shared, 'not_started', 3 * DAYSECS);
        $bobonly = $this->compliance_course(1);
        $this->snapshot($bob, $bobonly, 'in_progress', 2 * DAYSECS);
        // Another tenant's learner, due sooner than anything of Alice's.
        $public = $this->compliance_course(77);
        $this->snapshot($carol, $public, 'not_started', 1 * DAYSECS);

        $this->assertSame([(int) $shared->id], $this->due_courses($alice),
            'Alice sees her own row once, not Bob\'s or Carol\'s');
        $this->assertSame([(int) $bobonly->id, (int) $shared->id], $this->due_courses($bob));
        $this->assertSame([(int) $public->id], $this->due_courses($carol));
    }

    public function test_flag_resolves_per_learner_tenant(): void {
        $this->set_flag(77, true);
        $airpay = $this->learner('/1/5');
        $public = $this->learner('/77');
        $course = $this->compliance_course();
        $this->snapshot($airpay, $course, 'not_started', 3 * DAYSECS);
        $this->snapshot($public, $course, 'not_started', 3 * DAYSECS);

        $this->assertSame([], $this->due_courses($airpay), 'flag OFF for tenant 1');
        $this->assertSame([(int) $course->id], $this->due_courses($public), 'flag ON for tenant 77');
    }

    public function test_learner_without_tenant_never_gets_the_section(): void {
        $this->set_flag(0, true);
        $notenant = $this->learner('');
        $course = $this->compliance_course();
        $this->snapshot($notenant, $course, 'not_started', 3 * DAYSECS);

        $this->assertSame([], $this->due_courses($notenant));
    }

    public function test_rows_the_rebuild_left_behind_are_ignored(): void {
        global $DB;
        $this->set_flag(1, true);
        $learner = $this->learner('/1/5');

        // Taken off the compliance list (remove_compliance_course() only deactivates).
        $removed = $this->compliance_course(1, 0);
        $this->snapshot($learner, $removed, 'not_started', 3 * DAYSECS);
        // On the list for another tenant only (the learner has moved tenant).
        $othertenant = $this->compliance_course(77);
        $this->snapshot($learner, $othertenant, 'not_started', 3 * DAYSECS);
        // Completed after the last rebuild.
        $completed = $this->compliance_course(1);
        $this->snapshot($learner, $completed, 'in_progress', 3 * DAYSECS);
        // course_completions is unique per user and course: complete the row
        // enrolment may have left, or write one.
        $cc = $DB->get_record('course_completions',
            ['userid' => $learner->id, 'course' => $completed->id]);
        if ($cc) {
            $DB->set_field('course_completions', 'timecompleted', time(), ['id' => $cc->id]);
        } else {
            $DB->insert_record('course_completions', (object) [
                'userid'        => $learner->id,
                'course'        => $completed->id,
                'timeenrolled'  => 0,
                'timestarted'   => 0,
                'timecompleted' => time(),
                'reaggregate'   => 0,
            ]);
        }
        // Unenrolled after the last rebuild.
        $unenrolled = $this->compliance_course(1);
        $this->snapshot($learner, $unenrolled, 'not_started', 3 * DAYSECS, false);
        // Hidden from learners.
        $hidden = $this->compliance_course(1, 1, 0);
        $this->snapshot($learner, $hidden, 'not_started', 3 * DAYSECS);
        // Deadline passed since the last rebuild (it would now say overdue).
        $passed = $this->compliance_course(1);
        $this->snapshot($learner, $passed, 'not_started', -1 * HOURSECS);

        // The one row that still counts.
        $current = $this->compliance_course(1);
        $this->snapshot($learner, $current, 'not_started', 4 * DAYSECS);

        $this->assertSame([$this->line(4, $current)], $this->due_lines($learner));
    }

    public function test_exempted_learners_get_no_line(): void {
        $this->set_flag(1, true);
        $first = $this->compliance_course(1);
        $second = $this->compliance_course();

        // Excluded from all compliance tracking: exclude_user() writes courseid 0,
        // no expiry. The rows are seeded directly as not_started, the state a
        // learner is in between being excluded and the next hourly rebuild
        // (compliance_report 1.0.7 then marks them exempted).
        $excluded = $this->learner('/1/5');
        // Exempted from $first only, until next month (granted since the last
        // rebuild, so the row still says not_started).
        $percourse = $this->learner('/1/6');
        // Exempted from $first until an hour ago; the next rebuild has not yet
        // set is_active = 0.
        $expired = $this->learner('/1/7');
        // Excluded, then re-included: include_user() sets is_active = 0.
        $reincluded = $this->learner('/1/8');

        foreach ([$excluded, $percourse, $expired, $reincluded] as $learner) {
            $this->snapshot($learner, $first, 'not_started', 3 * DAYSECS);
            $this->snapshot($learner, $second, 'in_progress', 4 * DAYSECS);
        }
        $this->exempt($excluded, 0);
        $this->exempt($percourse, (int) $first->id, time() + 30 * DAYSECS);
        $this->exempt($expired, (int) $first->id, time() - HOURSECS);
        $this->exempt($reincluded, 0, null, 0);

        $both = [$this->line(3, $first), $this->line(4, $second)];
        $this->assertSame([], $this->due_lines($excluded), 'all-courses exemption: no line at all');
        $this->assertSame([$this->line(4, $second)], $this->due_lines($percourse),
            'per-course exemption: that course only');
        $this->assertSame($both, $this->due_lines($expired), 'expired exemption: still listed');
        $this->assertSame($both, $this->due_lines($reincluded), 'lifted exclusion: listed again');
    }

    public function test_only_active_enrolments_count(): void {
        global $DB;
        $this->set_flag(1, true);
        $learner = $this->learner('/1/5');
        $gen = $this->getDataGenerator();
        $now = time();

        // Rows that must not count are due sooner, so any of them listed by
        // mistake would also push a counted one out of the two-line limit.

        // Enrolment suspended.
        $suspended = $this->compliance_course(1);
        $gen->enrol_user($learner->id, $suspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->snapshot($learner, $suspended, 'not_started', 2 * DAYSECS, false);
        // Enrolment method disabled.
        $disabled = $this->compliance_course(1);
        $gen->enrol_user($learner->id, $disabled->id, 'student');
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED,
            ['courseid' => $disabled->id, 'enrol' => 'manual']);
        $this->snapshot($learner, $disabled, 'not_started', 2 * DAYSECS, false);
        // Enrolment ended an hour ago.
        $ended = $this->compliance_course(1);
        $gen->enrol_user($learner->id, $ended->id, 'student', 'manual', $now - 10 * DAYSECS, $now - HOURSECS);
        $this->snapshot($learner, $ended, 'in_progress', 3 * DAYSECS, false);
        // Enrolment starts tomorrow.
        $notyet = $this->compliance_course(1);
        $gen->enrol_user($learner->id, $notyet->id, 'student', 'manual', $now + DAYSECS, 0);
        $this->snapshot($learner, $notyet, 'not_started', 3 * DAYSECS, false);

        // Counted: suspended in one method but active in another.
        $other = $this->compliance_course(1);
        $gen->enrol_user($learner->id, $other->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->second_enrolment($learner, $other);
        $this->snapshot($learner, $other, 'not_started', 4 * DAYSECS, false);
        // Counted: started, ends next month.
        $current = $this->compliance_course(1);
        $gen->enrol_user($learner->id, $current->id, 'student', 'manual', $now - DAYSECS, $now + 30 * DAYSECS);
        $this->snapshot($learner, $current, 'in_progress', 5 * DAYSECS, false);

        $this->assertSame([$this->line(4, $other), $this->line(5, $current)], $this->due_lines($learner));
    }

    public function test_one_line_per_course_however_often_it_is_listed_or_enrolled(): void {
        $this->set_flag(1, true);
        $learner = $this->learner('/1/5');

        // On the compliance list for every tenant AND for the learner's tenant
        // (the list's unique key is courseid + costcenterid), and enrolled
        // through two methods.
        $twice = $this->compliance_course(0);
        $this->list_course($twice, 1);
        $this->snapshot($learner, $twice, 'not_started', 3 * DAYSECS);
        $this->second_enrolment($learner, $twice);
        // Due later: a repeated first line would also push this one out.
        $later = $this->compliance_course(1);
        $this->snapshot($learner, $later, 'in_progress', 5 * DAYSECS);

        $this->assertSame([$this->line(3, $twice), $this->line(5, $later)], $this->due_lines($learner),
            'exactly one line for the course listed twice and enrolled twice');
    }

    public function test_streak_line_is_read_only_where_its_table_exists(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_streaks')) {
            $this->markTestSkipped('local_sentientia_gamification is not installed.');
        }
        // Flag OFF and no enrolment: the streak would be the only line.
        $learner = $this->learner('/1/5');
        $DB->insert_record('local_sentientia_streaks', (object) [
            'userid'          => $learner->id,
            'current_streak'  => 4,
            'longest_streak'  => 4,
            'last_login_date' => date('Y-m-d'),
            'total_points'    => 0,
            'timemodified'    => time(),
        ]);

        // Gamification installed: unchanged.
        $this->assertSame(['4-day streak! Keep it going today.'],
            array_column($this->digest_items($learner), 'text'));

        // A run that found the table absent never reads it (an install without
        // gamification used to throw here and stop the whole run).
        $task = new daily_digest();
        (new \ReflectionProperty(daily_digest::class, 'tablespresent'))
            ->setValue($task, ['local_sentientia_streaks' => false]);
        $this->assertSame([], $this->digest_items($learner, $task));
    }
}
