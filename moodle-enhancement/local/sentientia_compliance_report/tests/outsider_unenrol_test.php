<?php
// This file is part of Sentientia LMS.

/**
 * Decision D-10 (Nitin, 20 Nov 2026, "do all"): cli/pin_compliance_courses.php
 * --unenrol-outsiders removes the enrolments the old compliance auto-enrol
 * made for people outside a tracked course's tenants.
 *
 * Fixture: Airpay courses (course.open_path '/1') tracked for all tenants
 * with a 30-day deadline. The old auto-enrol's mark is a manual enrolment
 * whose end is start + deadline days. Course ids are whatever the generator
 * gives.
 *
 * @package    local_sentientia_compliance_report
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_compliance_report;

use local_sentientia_compliance_report\local\compliance_pinning;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_compliance_report\local\compliance_pinning::outsider_enrolments
 * @covers \local_sentientia_compliance_report\local\compliance_pinning::unenrol_outsiders
 * @covers \local_sentientia_compliance_report\local\compliance_pinning::render_outsiders
 */
final class outsider_unenrol_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var \stdClass the tracked Airpay course */
    private $course;

    /** @var int start of every enrolment in the fixture */
    private $start;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        $this->redirectMessages();
        set_config('auto_enrol', 0, 'local_sentientia_compliance_report');
        $this->start = time() - 40 * DAYSECS;
        $this->course = $this->course('Anti Money Laundering', 'AMLI01', '/1');
        $this->track($this->course, 0, 30);
    }

    private function course(string $fullname, string $shortname, ?string $path): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['fullname' => $fullname, 'shortname' => $shortname]);
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    private function track(\stdClass $course, int $costcenterid, int $days, int $active = 1): int {
        global $DB;
        return (int) $DB->insert_record('local_compliance_courses', (object) [
            'courseid' => $course->id, 'coursename' => $course->fullname, 'deadline_days' => $days,
            'costcenterid' => $costcenterid, 'is_active' => $active, 'sort_order' => 1,
            'createdby' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /** A user at a BizLMS org path; null leaves open_path NULL (no tenant). */
    private function person(?string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** Enrol with the old auto-enrol's mark: manual, end = start + $days. */
    private function marked(\stdClass $user, ?\stdClass $course = null, int $days = 30, ?int $status = null): void {
        $this->assertTrue($this->getDataGenerator()->enrol_user($user->id, ($course ?? $this->course)->id, 'student',
            'manual', $this->start, $this->start + $days * DAYSECS, $status));
    }

    private function enrolled(\stdClass $user, ?\stdClass $course = null): bool {
        global $DB;
        return $DB->record_exists_sql(
            "SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid",
            ['cid' => ($course ?? $this->course)->id, 'uid' => $user->id]);
    }

    private static function ids(array $rows): array {
        $ids = array_map(fn($ue) => (int) $ue->userid, $rows);
        sort($ids);
        return $ids;
    }

    private static function sorted(array $ids): array {
        $ids = array_map('intval', $ids);
        sort($ids);
        return $ids;
    }

    /**
     * Everyone in the fixture, by role in the test:
     *   airpay, airpayroot: the course's own tenant (never touched);
     *   public, zeea, suspended: marked outsiders (removed);
     *   notenant (NULL path), emptypath (''): marked, no tenant (only with --include-no-tenant);
     *   noend, customend, selfenrol: outsiders without the mark (left alone);
     *   teacher: a marked outsider who also teaches the course (left alone).
     *
     * @return \stdClass[] by name
     */
    private function fixture(): array {
        global $DB;
        $p = [
            'airpay'     => $this->person('/1/2'),
            'airpayroot' => $this->person('/1'),
            'public'     => $this->person('/77'),
            // '/177/178' must not be taken for tenant 1 ('/1' is a prefix of '/177').
            'zeea'       => $this->person('/177/178'),
            'suspended'  => $this->person('/77/80'),
            'notenant'   => $this->person(null),
            'emptypath'  => $this->person(''),
            'noend'      => $this->person('/77'),
            'customend'  => $this->person('/77'),
            'selfenrol'  => $this->person('/77'),
            'teacher'    => $this->person('/177'),
        ];
        foreach (['airpay', 'airpayroot', 'public', 'zeea', 'notenant', 'emptypath', 'teacher'] as $name) {
            $this->marked($p[$name]);
        }
        $this->marked($p['suspended'], null, 30, ENROL_USER_SUSPENDED);
        $gen = $this->getDataGenerator();
        $this->assertTrue($gen->enrol_user($p['noend']->id, $this->course->id, 'student', 'manual', $this->start, 0));
        $this->marked($p['customend'], null, 10);
        if (!$DB->record_exists('enrol', ['courseid' => $this->course->id, 'enrol' => 'self'])) {
            enrol_get_plugin('self')->add_instance($this->course);
        }
        $this->assertTrue($gen->enrol_user($p['selfenrol']->id, $this->course->id, 'student', 'self',
            $this->start, $this->start + 30 * DAYSECS));
        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST),
            $p['teacher']->id, \context_course::instance($this->course->id)->id);
        return $p;
    }

    /** The dry run finds the marked outsiders, counts the rest, names nobody and writes nothing. */
    public function test_plan_finds_marked_outsiders_and_writes_nothing(): void {
        global $DB;
        $p = $this->fixture();
        $before = $DB->get_records('user_enrolments', null, 'id');

        $out = compliance_pinning::outsider_enrolments();

        $this->assertTrue($out['available']);
        $this->assertFalse($out['includenotenant']);
        $entry = $out['courses'][$this->course->id];
        $this->assertSame(1, $entry['tenant']);
        $this->assertSame([], $entry['sharedto']);
        $this->assertSame(self::sorted([$p['public']->id, $p['zeea']->id, $p['suspended']->id]),
            self::ids($entry['remove']));
        $this->assertSame([77 => 2, 177 => 1], $entry['bytenant']);
        $this->assertSame(1, $entry['suspended']);
        $this->assertSame(0, $entry['completed']);
        $this->assertSame(2, $entry['notenant']);
        $this->assertSame(1, $entry['otherrole']);
        $this->assertSame(3, $entry['unsigned']);
        $this->assertSame([], $out['unowned']);

        $text = implode("\n", compliance_pinning::render_outsiders($out, false));
        $this->assertStringContainsString('3 would be removed (tenant 77: 2, tenant 177: 1)', $text);
        $this->assertStringContainsString('3 enrolments would be removed', $text);
        $this->assertStringContainsString('2 marked enrolments belong to accounts with no tenant', $text);
        $this->assertStringContainsString('Add --include-no-tenant', $text);
        $this->assertStringContainsString('1 people outside also hold another role', $text);
        $this->assertStringContainsString('3 enrolments of people outside were not made by the old auto-enrol', $text);
        foreach ($p as $user) {
            $this->assertStringNotContainsString($user->username, $text, 'the plan names a person');
            $this->assertStringNotContainsString($user->email, $text, 'the plan names a person');
        }
        $this->assertStringContainsString('WILL be removed',
            implode("\n", compliance_pinning::render_outsiders($out, true)));

        $this->assertEquals($before, $DB->get_records('user_enrolments', null, 'id'));
    }

    /** --execute removes exactly the plan's enrolments, through unenrol_user (events, roles), and a second run removes none. */
    public function test_unenrol_removes_only_the_plan(): void {
        global $DB;
        $p = $this->fixture();
        $context = \context_course::instance($this->course->id);
        $sink = $this->redirectEvents();

        $result = compliance_pinning::unenrol_outsiders(compliance_pinning::outsider_enrolments());

        $deleted = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\user_enrolment_deleted);
        $sink->close();
        $this->assertSame(3, $result['removed']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([(int) $this->course->id => 3], $result['percourse']);
        $this->assertCount(3, $deleted);
        $this->assertStringContainsString('Removed 3 enrolments', implode("\n", $result['lines']));
        foreach (['public', 'zeea', 'suspended'] as $name) {
            $this->assertFalse($this->enrolled($p[$name]), "{$name} is still enrolled");
            $this->assertFalse($DB->record_exists('role_assignments',
                ['userid' => $p[$name]->id, 'contextid' => $context->id]), "{$name} kept a role in the course");
        }
        foreach (['airpay', 'airpayroot', 'notenant', 'emptypath', 'noend', 'customend', 'selfenrol', 'teacher'] as $name) {
            $this->assertTrue($this->enrolled($p[$name]), "{$name} was unenrolled");
        }

        $again = compliance_pinning::outsider_enrolments();
        $this->assertSame([], $again['courses'][$this->course->id]['remove']);
        $this->assertSame(0, compliance_pinning::unenrol_outsiders($again)['removed']);
    }

    /** --include-no-tenant: accounts with no org path (NULL or '') count as outside too. */
    public function test_include_no_tenant(): void {
        $p = $this->fixture();

        $out = compliance_pinning::outsider_enrolments(true);

        $entry = $out['courses'][$this->course->id];
        $this->assertSame(self::sorted([$p['public']->id, $p['zeea']->id, $p['suspended']->id,
            $p['notenant']->id, $p['emptypath']->id]), self::ids($entry['remove']));
        $this->assertSame([0 => 2, 77 => 2, 177 => 1], $entry['bytenant']);
        $text = implode("\n", compliance_pinning::render_outsiders($out, false));
        $this->assertStringContainsString('2 of them belong to accounts with no tenant (--include-no-tenant)', $text);
        $this->assertStringNotContainsString('Add --include-no-tenant', $text);

        $this->assertSame(5, compliance_pinning::unenrol_outsiders($out)['removed']);
        $this->assertFalse($this->enrolled($p['notenant']));
        $this->assertFalse($this->enrolled($p['emptypath']));
        $this->assertTrue($this->enrolled($p['airpay']));
        $this->assertTrue($this->enrolled($p['teacher']));
    }

    /** A tenant the course is actively shared to counts as its own; a share that is no longer active does not. */
    public function test_shared_tenant_is_not_outside(): void {
        global $DB;
        $p = $this->fixture();
        $shareid = $DB->insert_record('local_sentientia_courses_tenant_share', (object) [
            'courseid' => $this->course->id, 'tenant_id' => 77, 'shared_by' => get_admin()->id,
            'status' => 'active', 'timeshared' => time(), 'timemodified' => time(),
        ]);

        $entry = compliance_pinning::outsider_enrolments()['courses'][$this->course->id];
        $this->assertSame([77], $entry['sharedto']);
        $this->assertSame([(int) $p['zeea']->id], self::ids($entry['remove']));
        // The Public users without the mark are reached now, so they are not counted either.
        $this->assertSame(0, $entry['unsigned']);
        $this->assertStringContainsString('reaches tenant 1; shared to 77',
            implode("\n", compliance_pinning::render_outsiders(compliance_pinning::outsider_enrolments(), false)));

        $DB->set_field('local_sentientia_courses_tenant_share', 'status', 'withdrawn', ['id' => $shareid]);
        $entry = compliance_pinning::outsider_enrolments()['courses'][$this->course->id];
        $this->assertSame([], $entry['sharedto']);
        $this->assertSame(self::sorted([$p['public']->id, $p['zeea']->id, $p['suspended']->id]),
            self::ids($entry['remove']));
    }

    /** A tracked course with no tenant reaches everyone: nobody is outside it. */
    public function test_course_without_tenant_is_skipped(): void {
        $legacy = $this->course('Legacy Induction', 'LEG01', null);
        $this->track($legacy, 0, 30);
        $publicuser = $this->person('/77');
        $this->marked($publicuser, $legacy);

        $out = compliance_pinning::outsider_enrolments(true);

        $this->assertArrayNotHasKey($legacy->id, $out['courses']);
        $this->assertSame([(int) $legacy->id], array_keys($out['unowned']));
        $this->assertStringContainsString('1 tracked courses have no tenant',
            implode("\n", compliance_pinning::render_outsiders($out, false)));
        compliance_pinning::unenrol_outsiders($out);
        $this->assertTrue($this->enrolled($publicuser, $legacy));
    }

    /** An enrolment that changed after the plan, or whose holder moved tenant, is skipped. */
    public function test_changed_since_plan_is_skipped(): void {
        global $DB;
        $p = $this->fixture();
        $plan = compliance_pinning::outsider_enrolments();

        // An admin extends the Public user's enrolment; the ZEEA user moves to Airpay.
        $DB->set_field_select('user_enrolments', 'timeend', $this->start + 90 * DAYSECS,
            'userid = :uid', ['uid' => $p['public']->id]);
        $DB->set_field('user', 'open_path', '/1/5', ['id' => $p['zeea']->id]);

        $result = compliance_pinning::unenrol_outsiders($plan);

        $this->assertSame(1, $result['removed']);
        $this->assertSame(2, $result['skipped']);
        $this->assertTrue($this->enrolled($p['public']));
        $this->assertTrue($this->enrolled($p['zeea']));
        $this->assertFalse($this->enrolled($p['suspended']));
    }

    /** A completed outsider is counted; removing the enrolment keeps the course completion record. */
    public function test_completed_outsider_keeps_completion(): void {
        global $DB;
        $p = $this->fixture();
        $DB->insert_record('course_completions', (object) [
            'userid' => $p['public']->id, 'course' => $this->course->id, 'timeenrolled' => $this->start,
            'timestarted' => $this->start, 'timecompleted' => $this->start + DAYSECS, 'reaggregate' => 0,
        ]);

        $out = compliance_pinning::outsider_enrolments();
        $this->assertSame(1, $out['courses'][$this->course->id]['completed']);
        $this->assertStringContainsString('1 of the people to remove completed the course',
            implode("\n", compliance_pinning::render_outsiders($out, false)));

        compliance_pinning::unenrol_outsiders($out);
        $this->assertFalse($this->enrolled($p['public']));
        $this->assertTrue($DB->record_exists('course_completions',
            ['userid' => $p['public']->id, 'course' => $this->course->id]));
    }

    /**
     * The mark follows the course's own rows: any of its deadlines (an
     * inactive row too), not another course's.
     */
    public function test_mark_follows_each_course_rows(): void {
        $phishing = $this->course('Phishing Awareness Training', 'PAT01', '/1');
        $this->track($phishing, 0, 45);
        $this->track($phishing, 1, 60, 0);
        $at45 = $this->person('/77');
        $at60 = $this->person('/77');
        $at30 = $this->person('/77');
        $this->marked($at45, $phishing, 45);
        $this->marked($at60, $phishing, 60);
        // 30 days is the AML deadline, not one of this course's.
        $this->marked($at30, $phishing, 30);

        $entry = compliance_pinning::outsider_enrolments()['courses'][$phishing->id];

        $this->assertSame(self::sorted([$at45->id, $at60->id]), self::ids($entry['remove']));
        $this->assertSame(1, $entry['unsigned']);
    }
}
