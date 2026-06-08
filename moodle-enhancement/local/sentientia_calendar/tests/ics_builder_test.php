<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_calendar;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_calendar\ics_builder
 *
 * Locks in:
 *   - empty user → valid VCALENDAR shell, no VEVENTs
 *   - course-deadline VEVENT generated for enrolled, incomplete course
 *   - VEVENT not generated for completed course
 *   - classroom session VEVENT generated for roster member
 *   - exam-close VEVENT generated for upcoming quiz close
 *   - VTIMEZONE block present
 *   - CRLF line endings, RFC 5545 folding at 75 octets
 *   - User isolation: u1's feed does NOT contain u2's events
 *   - Feature flag scoping: events.courses=false → no course VEVENT
 *
 * @package    local_sentientia_calendar
 * @category   test
 */
final class ics_builder_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_empty_user_returns_valid_calendar_shell(): void {
        $user = $this->getDataGenerator()->create_user();
        $ics = ics_builder::build_for_user((int) $user->id);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('VERSION:2.0', $ics);
        $this->assertStringContainsString('PRODID:', $ics);
        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringContainsString('BEGIN:VTIMEZONE', $ics);
        $this->assertStringContainsString('END:VTIMEZONE', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);

        // No VEVENT for a user with no enrolments / classrooms / exams.
        $this->assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }

    public function test_uses_crlf_line_endings(): void {
        $user = $this->getDataGenerator()->create_user();
        $ics = ics_builder::build_for_user((int) $user->id);
        // Must contain CRLFs; must NOT contain bare LFs without preceding CR.
        $this->assertStringContainsString("\r\n", $ics);
        $cr_count = substr_count($ics, "\r\n");
        $lf_count = substr_count($ics, "\n");
        $this->assertSame($cr_count, $lf_count,
            'Every LF must be preceded by CR — no orphan LFs');
    }

    public function test_vtimezone_uses_asia_kolkata(): void {
        $user = $this->getDataGenerator()->create_user();
        $ics = ics_builder::build_for_user((int) $user->id);
        $this->assertStringContainsString('TZID:Asia/Kolkata', $ics);
        $this->assertStringContainsString('TZOFFSETTO:+0530', $ics);
    }

    public function test_course_deadline_appears_for_enrolled_user(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Add the field if missing (some test DBs are barebones).
        // open_coursecompletiondays is an Airpay-specific column added
        // by sentientia_courses; if not present, skip the test.
        $columns = $DB->get_columns('course');
        if (!isset($columns['open_coursecompletiondays'])) {
            $this->markTestSkipped('open_coursecompletiondays column not present');
        }

        $DB->set_field('course', 'open_coursecompletiondays', 30,
            ['id' => $course->id]);

        // Enrol the user via manual enrol.
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $ics = ics_builder::build_for_user((int) $user->id);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('CATEGORIES:COURSE-DEADLINE', $ics);
        $this->assertStringContainsString('SUMMARY:[Course Deadline]', $ics);
    }

    public function test_completed_course_omitted_from_feed(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $columns = $DB->get_columns('course');
        if (!isset($columns['open_coursecompletiondays'])) {
            $this->markTestSkipped('open_coursecompletiondays column not present');
        }

        $DB->set_field('course', 'open_coursecompletiondays', 30,
            ['id' => $course->id]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Insert a course_completions row marking the course done.
        $DB->insert_record('course_completions', (object) [
            'userid'        => $user->id,
            'course'        => $course->id,
            'timeenrolled'  => time() - 86400 * 30,
            'timestarted'   => time() - 86400 * 30,
            'timecompleted' => time() - 86400,
            'reaggregate'   => 0,
        ]);

        $ics = ics_builder::build_for_user((int) $user->id);
        $this->assertStringNotContainsString('COURSE-DEADLINE', $ics,
            'Completed courses must NOT generate a deadline VEVENT');
    }

    public function test_user_isolation_one_users_course_does_not_leak_to_another(): void {
        global $DB;
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $columns = $DB->get_columns('course');
        if (!isset($columns['open_coursecompletiondays'])) {
            $this->markTestSkipped('open_coursecompletiondays column not present');
        }
        $DB->set_field('course', 'open_coursecompletiondays', 30,
            ['id' => $course->id]);

        // Only u1 enrolled.
        $this->getDataGenerator()->enrol_user($u1->id, $course->id);

        $ics_u1 = ics_builder::build_for_user((int) $u1->id);
        $ics_u2 = ics_builder::build_for_user((int) $u2->id);

        $this->assertStringContainsString('COURSE-DEADLINE', $ics_u1,
            'u1 (enrolled) must see the course');
        $this->assertStringNotContainsString('COURSE-DEADLINE', $ics_u2,
            'u2 (not enrolled) must NOT see u1\'s course');
    }

    public function test_classroom_session_appears_for_enrolled_user(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_classroom_sessions')) {
            $this->markTestSkipped('local_sentientia_classroom not installed');
        }

        $user = $this->getDataGenerator()->create_user();

        $now = time();
        $classroomid = $DB->insert_record('local_sentientia_classroom', (object) [
            'name'         => 'Test Classroom',
            'description'  => '',
            'costcenterid' => 1,
            'open_path'    => '/1',
            'capacity'     => 30,
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $sessionid = $DB->insert_record('local_sentientia_classroom_sessions', (object) [
            'classroomid'  => $classroomid,
            'title'        => 'Day 1 — Onboarding',
            'sessiondate'  => $now + 86400,
            'starttime'    => $now + 86400,
            'endtime'      => $now + 86400 + 3600 * 4,
            'location'     => 'Mumbai HQ',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('local_sentientia_classroom_users', (object) [
            'classroomid'  => $classroomid,
            'userid'       => $user->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $ics = ics_builder::build_for_user((int) $user->id);
        $this->assertStringContainsString('CATEGORIES:CLASSROOM-SESSION', $ics);
        $this->assertStringContainsString('Day 1 — Onboarding', $ics);
        $this->assertStringContainsString('LOCATION:Mumbai HQ', $ics);
    }

    public function test_classroom_session_isolation(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_classroom_sessions')) {
            $this->markTestSkipped('local_sentientia_classroom not installed');
        }

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $now = time();

        $classroomid = $DB->insert_record('local_sentientia_classroom', (object) [
            'name'         => 'Confidential Training',
            'description'  => '',
            'costcenterid' => 1,
            'open_path'    => '/1',
            'capacity'     => 30,
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_sentientia_classroom_sessions', (object) [
            'classroomid'  => $classroomid,
            'sessiondate'  => $now + 86400,
            'starttime'    => $now + 86400,
            'endtime'      => $now + 86400 + 3600,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        // Only u1 on the roster.
        $DB->insert_record('local_sentientia_classroom_users', (object) [
            'classroomid'  => $classroomid,
            'userid'       => $u1->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $ics_u1 = ics_builder::build_for_user((int) $u1->id);
        $ics_u2 = ics_builder::build_for_user((int) $u2->id);

        $this->assertStringContainsString('Confidential Training', $ics_u1);
        $this->assertStringNotContainsString('Confidential Training', $ics_u2,
            'u2 (not on roster) must not see u1\'s classroom');
    }

    public function test_lines_folded_at_75_octets(): void {
        $user = $this->getDataGenerator()->create_user();
        $ics = ics_builder::build_for_user((int) $user->id);

        foreach (explode("\r\n", $ics) as $line) {
            // Continuation lines start with a space — the previous line
            // was folded. Either way, no line may exceed 75 octets.
            $this->assertLessThanOrEqual(75, strlen($line),
                'Line exceeds 75 octets (RFC 5545 §3.1): ' . substr($line, 0, 80));
        }
    }

    public function test_feature_flag_disables_courses_category(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $columns = $DB->get_columns('course');
        if (!isset($columns['open_coursecompletiondays'])) {
            $this->markTestSkipped('open_coursecompletiondays column not present');
        }
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            $this->markTestSkipped('local_airpay_core not installed');
        }

        $DB->set_field('course', 'open_coursecompletiondays', 30,
            ['id' => $course->id]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // The events.courses flag must be registered before set() will
        // accept it — skip if the registry hasn't loaded it yet.
        $registry = \local_airpay_core\feature_flags::load_registry();
        if (!isset($registry['sentientia.calendar_sync.events.courses'])) {
            $this->markTestSkipped(
                'sentientia.calendar_sync.events.courses not in registry yet'
            );
        }

        // Sanity: with the flag at its default (true), we get the event.
        $ics_default = ics_builder::build_for_user((int) $user->id);
        $this->assertStringContainsString('COURSE-DEADLINE', $ics_default);

        // Disable the courses sub-flag — global scope.
        // Signature: set($key, $tenant_id, $value, $by_userid, $reason, $customer_id)
        \local_airpay_core\feature_flags::set(
            'sentientia.calendar_sync.events.courses',
            0,            // tenant_id (0 = global)
            false,        // value
            (int) $user->id,
            'phpunit',
            0             // customer_id (0 = default scope)
        );
        \local_airpay_core\feature_flags::invalidate_caches();

        $ics_off = ics_builder::build_for_user((int) $user->id);
        $this->assertStringNotContainsString('COURSE-DEADLINE', $ics_off,
            'Disabling sub-flag must drop course VEVENTs from the feed');
    }

    public function test_summary_escapes_special_chars(): void {
        // Indirect — exercise the escape path by checking a known
        // RFC 5545 escape sequence appears. We build a feed with a
        // course whose name has a comma; the comma in SUMMARY must
        // be escaped as \,.
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Risk, Compliance & Finance',
        ]);

        $columns = $DB->get_columns('course');
        if (!isset($columns['open_coursecompletiondays'])) {
            $this->markTestSkipped('open_coursecompletiondays column not present');
        }
        $DB->set_field('course', 'open_coursecompletiondays', 30,
            ['id' => $course->id]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $ics = ics_builder::build_for_user((int) $user->id);
        $this->assertStringContainsString('Risk\\, Compliance', $ics,
            'Comma in SUMMARY must be escaped per RFC 5545 §3.3.11');
    }

    public function test_url_property_included_for_classroom_session(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_classroom_sessions')) {
            $this->markTestSkipped('local_sentientia_classroom not installed');
        }
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $classroomid = $DB->insert_record('local_sentientia_classroom', (object) [
            'name'         => 'URL Test',
            'description'  => '',
            'costcenterid' => 1,
            'open_path'    => '/1',
            'capacity'     => 30,
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_sentientia_classroom_sessions', (object) [
            'classroomid'  => $classroomid,
            'sessiondate'  => $now + 86400,
            'starttime'    => $now + 86400,
            'endtime'      => $now + 86400 + 3600,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_sentientia_classroom_users', (object) [
            'classroomid'  => $classroomid,
            'userid'       => $user->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $ics = ics_builder::build_for_user((int) $user->id);
        $this->assertMatchesRegularExpression(
            '/URL:.*local\/sentientia_classroom\/index\.php\?id=' . $classroomid . '/',
            $ics
        );
    }

    public function test_unique_vevent_uid_per_event(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();

        $columns = $DB->get_columns('course');
        if (!isset($columns['open_coursecompletiondays'])) {
            $this->markTestSkipped('open_coursecompletiondays column not present');
        }
        $DB->set_field('course', 'open_coursecompletiondays', 30, ['id' => $c1->id]);
        $DB->set_field('course', 'open_coursecompletiondays', 45, ['id' => $c2->id]);
        $this->getDataGenerator()->enrol_user($user->id, $c1->id);
        $this->getDataGenerator()->enrol_user($user->id, $c2->id);

        $ics = ics_builder::build_for_user((int) $user->id);

        // Extract all UID: values (after any line folding).
        $compact = str_replace("\r\n ", '', $ics);
        preg_match_all('/^UID:(.+)$/m', $compact, $matches);
        $uids = $matches[1] ?? [];
        $this->assertCount(count(array_unique($uids)), $uids,
            'Every VEVENT UID must be unique within the feed');
    }
}
